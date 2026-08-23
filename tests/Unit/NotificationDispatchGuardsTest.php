<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static integration guards for the notification dispatch boundary.
 *
 * Covers the two invariants that are cheap to break by editing a producer:
 *   - a fan-out whose recipient list is the whole user base never runs in the
 *     request thread;
 *   - the live WebSocket copy of a notification never carries more than what
 *     is persisted.
 */
class NotificationDispatchGuardsTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . ltrim($relativePath, '/');
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    public function testKnowledgeBasePublicationIsFannedOutInBackground(): void
    {
        $kbQueries = $this->source('app/sources/kb.queries.php');

        self::assertStringContainsString(
            'tpQueueKnowledgeBasePublicationNotification(',
            $kbQueries,
            'The article save must queue the fan-out, not perform it.'
        );
        self::assertStringNotContainsString(
            'tpNotifyKnowledgeBasePublication(',
            $kbQueries,
            'The fan-out walks every non-admin user and must never run in the request thread.'
        );

        $mainFunctions = $this->source('app/sources/main.functions.php');
        self::assertMatchesRegularExpression(
            '/function tpQueueKnowledgeBasePublicationNotification\(.*?\'process_type\' => \'kb_publication_notifications\'/s',
            $mainFunctions
        );

        $worker = $this->source('app/scripts/background_tasks___worker.php');
        self::assertStringContainsString("case 'kb_publication_notifications':", $worker);
        self::assertStringContainsString('tpNotifyKnowledgeBasePublication(', $worker);
    }

    public function testKnowledgeBaseRecipientsExcludeUnreadyAccounts(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function tpNotifyKnowledgeBasePublication\(.*?WHERE admin = 0.*?AND disabled = 0.*?AND is_ready_for_usage = 1.*?deleted_at IS NULL/s',
            $mainFunctions
        );
    }

    public function testNotifyUserSanitizesBeforeBroadcasting(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function tpNotifyUser\(.*?\$payload = notificationSanitizePayload\(\$eventType, \$payload\);.*?tpQueueWebSocketEvent\(\$eventType, \'user\', \$userId, \$payload\)/s',
            $mainFunctions,
            'The WebSocket copy must be the sanitized payload, not the caller payload.'
        );
    }

    public function testObsoletePasswordCleanupEscapesLikeWildcards(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function tpClearObsoleteLocalPasswordExpiryNotifications\(.*?str_replace\(\s*\[\'\\\\\\\\\', \'%\', \'_\'\]/s',
            $mainFunctions,
            'The dedupe-key prefix contains "_", a LIKE wildcard, and must be escaped.'
        );
    }
}
