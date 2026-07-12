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
        foreach (['security_nudge', 'user_keys_ready', 'task_completed', 'folder_permission_changed'] as $type) {
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
