<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with emitWebSocketEvent() and the
// main.queries.php notification handlers (D2 — In-app Notification Centre).
require_once __DIR__ . '/../../app/sources/notifications.functions.php';

/**
 * Unit tests for the Notification Centre logic (D2).
 *
 * Covers:
 *   - notificationShouldPersist()   — event whitelist + target gating
 *   - notificationSanitizePayload() — per-type payload whitelists
 *   - password-expiry milestones and notification idempotency keys
 *   - notificationShapeRows()       — stored rows -> client rows
 *   - notificationSanitizeIds()     — mark-as-read input hardening
 */
class NotificationCenterLogicTest extends TestCase
{
    // -------------------------------------------------------------------
    // notificationShouldPersist()
    // -------------------------------------------------------------------

    public function testPersistsWhitelistedUserEvents(): void
    {
        foreach ([
            'security_nudge',
            'user_keys_ready',
            'task_completed',
            'folder_permission_changed',
            'local_password_expiring',
            'kb_article_created',
            'backup_failed',
        ] as $type) {
            $this->assertTrue(notificationShouldPersist($type, 'user', 42), $type);
        }
    }

    public function testSkipsTransientAndNonUserEvents(): void
    {
        // Transient: the user is being logged out / it is a heartbeat.
        $this->assertFalse(notificationShouldPersist('session_expired', 'user', 42));
        $this->assertFalse(notificationShouldPersist('task_progress', 'user', 42));
        // Non-user targets never land in a personal inbox.
        $this->assertFalse(notificationShouldPersist('item_updated', 'folder', 10));
        $this->assertFalse(notificationShouldPersist('system_maintenance', 'broadcast', null));
        // Missing/invalid target user.
        $this->assertFalse(notificationShouldPersist('task_completed', 'user', null));
        $this->assertFalse(notificationShouldPersist('task_completed', 'user', 0));
    }

    // -------------------------------------------------------------------
    // notificationSanitizePayload()
    // -------------------------------------------------------------------

    public function testSecurityNudgePayloadKeepsCountsOnly(): void
    {
        $clean = notificationSanitizePayload('security_nudge', [
            'breached' => 2, 'weak' => '3', 'reused' => -1, 'overdue' => 0, 'total' => 5,
            'exclude_user_id' => 7, 'server_timestamp' => 123, 'evil' => '<script>',
        ]);

        $this->assertSame(
            ['breached' => 2, 'weak' => 3, 'reused' => 0, 'overdue' => 0, 'total' => 5],
            $clean
        );
    }

    public function testTaskCompletedPayloadBoundsStrings(): void
    {
        $clean = notificationSanitizePayload('task_completed', [
            'task_type' => str_repeat('x', 300),
            'status' => 'completed',
            'message' => 'internal detail that must not be stored',
        ]);

        $this->assertSame(100, strlen($clean['task_type']));
        $this->assertSame('completed', $clean['status']);
        $this->assertArrayNotHasKey('message', $clean);
    }

    public function testTaskCompletedKeepsItemLabelAndDropsInternalKeys(): void
    {
        $clean = notificationSanitizePayload('task_completed', [
            'task_type' => 'Item encryption',
            'status' => 'completed',
            'item_label' => str_repeat('y', 300),
            'item_id' => 42,
            'process_type' => 'new_item',
            'message' => 'internal detail that must not be stored',
        ]);

        // The item label is kept (length-bounded) so the inbox can name the item.
        $this->assertSame(100, strlen($clean['item_label']));
        // Internal routing keys never reach the stored payload.
        $this->assertArrayNotHasKey('item_id', $clean);
        $this->assertArrayNotHasKey('process_type', $clean);
        $this->assertArrayNotHasKey('message', $clean);
    }

    public function testTaskCompletedWithoutItemLabelHasNoLabelKey(): void
    {
        $clean = notificationSanitizePayload('task_completed', [
            'task_type' => 'import',
            'status' => 'completed',
            'item_label' => '',
        ]);

        // Empty labels are not stored — generic tasks stay label-less.
        $this->assertArrayNotHasKey('item_label', $clean);
    }

    public function testFolderPermissionPayloadStoresCountNotIds(): void
    {
        $clean = notificationSanitizePayload('folder_permission_changed', [
            'role_id' => 3,
            'folders' => [10, 11, 12],
            'access' => 'W',
        ]);

        // Folder ids are entitlement metadata — only the count is kept.
        $this->assertSame(['folders_count' => 3], $clean);
    }

    public function testUnknownEventTypeStoresNothing(): void
    {
        $this->assertSame([], notificationSanitizePayload('something_new', ['a' => 1]));
    }

    public function testLocalPasswordExpiryPayloadIsTypedAndBounded(): void
    {
        $clean = notificationSanitizePayload('local_password_expiring', [
            'days_remaining' => '7',
            'threshold' => 7,
            'expires_at' => '1787500000',
            'auth_type' => 'local',
        ]);

        $this->assertSame([
            'days_remaining' => 7,
            'threshold' => 7,
            'expires_at' => 1787500000,
        ], $clean);
        $this->assertArrayNotHasKey('auth_type', $clean);
    }

    public function testKnowledgeBasePublicationPayloadIsSafeAndBounded(): void
    {
        $clean = notificationSanitizePayload('kb_article_created', [
            'kb_id' => '42',
            'label' => str_repeat('x', 250),
            'created_by' => 'alice',
        ]);

        $this->assertSame(42, $clean['kb_id']);
        $this->assertSame(200, mb_strlen($clean['label']));
        $this->assertArrayNotHasKey('created_by', $clean);
    }

    public function testBackupFailurePayloadIsTypedBoundedAndSingleLine(): void
    {
        $clean = notificationSanitizePayload('backup_failed', [
            'backup_type' => 'externalized',
            'message' => "Upload failed\n" . str_repeat('x', 400),
            'destination_password' => 'must-not-be-stored',
            'task_id' => 42,
        ]);

        $this->assertSame('externalized', $clean['backup_type']);
        $this->assertSame(300, mb_strlen($clean['message']));
        $this->assertStringNotContainsString("\n", $clean['message']);
        $this->assertArrayNotHasKey('destination_password', $clean);
        $this->assertArrayNotHasKey('task_id', $clean);
    }

    public function testBackupFailureMessageSurvivesInvalidUtf8(): void
    {
        // Backup failures quote shell / driver output that is often not UTF-8.
        // The /u modifier returns null on such input, which used to blank the
        // whole cause and leave administrators with "Backup failed: ".
        $clean = notificationSanitizePayload('backup_failed', [
            'backup_type' => 'externalized',
            'message' => "rsync: cannot open \xC3\x28file\x80\n  /srv/dump",
        ]);

        $this->assertStringContainsString('rsync: cannot open', $clean['message']);
        $this->assertStringContainsString('/srv/dump', $clean['message']);
        $this->assertStringNotContainsString("\n", $clean['message']);
        $this->assertTrue(mb_check_encoding($clean['message'], 'UTF-8'));
    }

    public function testPasswordExpiryMilestones(): void
    {
        $this->assertNull(notificationPasswordExpiryThreshold(15));
        $this->assertSame(14, notificationPasswordExpiryThreshold(14));
        $this->assertSame(14, notificationPasswordExpiryThreshold(8));
        $this->assertSame(7, notificationPasswordExpiryThreshold(7));
        $this->assertSame(3, notificationPasswordExpiryThreshold(2));
        $this->assertSame(1, notificationPasswordExpiryThreshold(1));
        $this->assertSame(0, notificationPasswordExpiryThreshold(0));
        $this->assertSame(0, notificationPasswordExpiryThreshold(-2));
    }

    public function testNotificationDedupeKeysAreStable(): void
    {
        $this->assertSame(
            'local_password_expiry:1787500000:7',
            notificationPasswordExpiryDedupeKey(1787500000, 7)
        );
        $this->assertSame('kb_article_created:42', notificationKbPublicationDedupeKey(42));
        $this->assertSame('backup_failed:scheduled:91', notificationBackupFailureDedupeKey(91, 'scheduled'));
        $this->assertSame('backup_failed:externalized:91', notificationBackupFailureDedupeKey(91, 'externalized'));
        $this->assertSame(
            'backup_failed:externalized:scheduler:2026-08-23:invalid_destination',
            notificationBackupFailureDedupeKey('scheduler:2026-08-23:invalid_destination', 'externalized')
        );
    }

    // -------------------------------------------------------------------
    // notificationShapeRows()
    // -------------------------------------------------------------------

    public function testShapeRowsDecodesPayloadAndCastsTypes(): void
    {
        $rows = notificationShapeRows([
            [
                'increment_id' => '12',
                'event_type' => 'task_completed',
                'payload' => '{"task_type":"Item encryption","status":"completed"}',
                'created_at' => '1760000000',
                'is_read' => '0',
            ],
            [
                'increment_id' => 13,
                'event_type' => 'user_keys_ready',
                'payload' => 'not-json',
                'created_at' => 1760000100,
                'is_read' => 1,
            ],
        ]);

        $this->assertSame(12, $rows[0]['id']);
        $this->assertSame('task_completed', $rows[0]['type']);
        $this->assertSame('Item encryption', $rows[0]['payload']['task_type']);
        $this->assertSame(0, $rows[0]['is_read']);
        // Corrupted payload degrades to an empty array, never breaks the client.
        $this->assertSame([], $rows[1]['payload']);
        $this->assertSame(1, $rows[1]['is_read']);
    }

    // -------------------------------------------------------------------
    // notificationSanitizeIds()
    // -------------------------------------------------------------------

    public function testSanitizeIdsFiltersAndCaps(): void
    {
        $this->assertSame([1, 2, 3], notificationSanitizeIds(['1', 2, '3', 0, -4, 'abc', 2]));
        $this->assertSame([], notificationSanitizeIds('not-an-array'));
        $this->assertSame([], notificationSanitizeIds(null));
        $this->assertCount(100, notificationSanitizeIds(range(1, 250)));
    }
}
