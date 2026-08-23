<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static integration guards for administrator-only backup failure alerts.
 */
class BackupFailureNotificationTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . ltrim($relativePath, '/');
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    public function testRecipientFanOutIsRestrictedToActiveAdministrators(): void
    {
        $source = $this->source('app/sources/main.functions.php');

        self::assertStringContainsString('function tpNotifyBackupFailure(', $source);
        self::assertMatchesRegularExpression(
            '/function tpNotifyBackupFailure\(.*?WHERE admin = %i.*?AND disabled = %i.*?deleted_at IS NULL/s',
            $source
        );
        self::assertStringContainsString("'backup_failed'", $source);
        self::assertStringContainsString('notificationBackupFailureDedupeKey(', $source);
    }

    public function testWorkerCoversAllTerminalBackupFailurePaths(): void
    {
        $source = $this->source('app/scripts/background_tasks___worker.php');

        // Coverage, not an exact call count: a legitimate new terminal failure
        // path must be free to add its own alert without failing this guard.
        self::assertGreaterThanOrEqual(
            1,
            substr_count($source, "tpNotifyBackupFailure(\$this->taskId, 'scheduled'"),
            'A scheduler-originated database_backup failure must alert administrators.'
        );
        self::assertGreaterThanOrEqual(
            2,
            substr_count($source, "tpNotifyBackupFailure(\$this->taskId, 'externalized'"),
            'Both the terminal externalized failure and the chained queueing failure must alert.'
        );
        self::assertStringContainsString(
            "tpNotifyBackupFailure(\$this->taskId, 'externalized', \$externalizedMessage);",
            $source,
            'A chained externalization that cannot be queued must also alert administrators.'
        );
        // The two terminal alerts belong to handleTaskFailure()'s per-type branches.
        self::assertMatchesRegularExpression(
            '/processType === \'database_backup\'[^{]*\'scheduler\'\s*\)\s*\{.*?tpNotifyBackupFailure\(\$this->taskId, \'scheduled\'/s',
            $source
        );
        self::assertMatchesRegularExpression(
            '/processType === \'externalized_backup\'\s*\)\s*\{.*?tpNotifyBackupFailure\(\$this->taskId, \'externalized\'/s',
            $source
        );
    }

    public function testNotificationLinksAdministratorsToBackupPage(): void
    {
        $source = $this->source('app/core/notification-center.js.php');

        self::assertStringContainsString("'backup_failed': {", $source);
        self::assertStringContainsString("link: 'index.php?page=backups'", $source);
    }

    public function testHandlerCoversPreflightAndCrashedWorkerFailures(): void
    {
        $source = $this->source('app/scripts/background_tasks___handler.php');

        self::assertStringContainsString('function notifyBackupSchedulerFailure(', $source);
        self::assertStringContainsString("'scheduled', 'invalid_output_directory'", $source);
        self::assertStringContainsString("'externalized', 'unsupported_destination'", $source);
        self::assertStringContainsString("'externalized', 'invalid_destination'", $source);
        self::assertStringContainsString("'externalized', 'missing_instance_key'", $source);
        self::assertStringContainsString("'scheduled', 'queue_failed'", $source);
        self::assertStringContainsString("'externalized', 'queue_failed'", $source);
        self::assertMatchesRegularExpression(
            '/processType === \'externalized_backup\'.*?tpNotifyBackupFailure\(\$taskId, \'externalized\'/s',
            $source
        );
        self::assertMatchesRegularExpression(
            '/processType === \'database_backup\'.*?source.*?scheduler.*?tpNotifyBackupFailure\(\$taskId, \'scheduled\'/s',
            $source
        );
    }
}
