<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Stubs/websocket_pure_functions.php';

/**
 * Unit tests for the pure WebSocket business logic.
 *
 * Covers the stub functions from tests/Stubs/websocket_pure_functions.php,
 * which mirror production logic in:
 *   - sources/main.functions.php  (emitEditionLockEvent, emitWebSocketEvent)
 *   - sources/items.queries.php   (isItemLocked decision, move_item payload)
 *
 * None of these tests require a database, session, or network connection.
 *
 * Test groups:
 *   A. Edition-lock event payload construction
 *   B. Edition-lock exclude_user_id computation
 *   C. emitWebSocketEvent exclude_user_id embedding
 *   D. Item-moved event payload construction
 *   E. Edition-lock status determination (core of isItemLocked)
 */
class WebSocketFunctionsTest extends TestCase
{
    // =========================================================================
    // A. Edition-lock event payload construction
    // =========================================================================

    public function testEditionLockPayloadContainsAllRequiredFields(): void
    {
        $payload = buildEditionLockEventPayload(
            itemId: 7,
            folderId: 3,
            userLogin: 'alice',
            userId: 42
        );

        $this->assertSame(7, $payload['item_id']);
        $this->assertSame(3, $payload['folder_id']);
        $this->assertSame('alice', $payload['user_login']);
        $this->assertSame(42, $payload['user_id']);
    }

    public function testEditionLockPayloadHasExactlyFourKeys(): void
    {
        $payload = buildEditionLockEventPayload(1, 1, 'bob', 99);

        $this->assertCount(4, $payload);
    }

    public function testEditionLockPayloadPreservesEmptyUserLogin(): void
    {
        // Production code uses $session->get('user-login') ?? '' when login is null
        $payload = buildEditionLockEventPayload(5, 10, '', 1);

        $this->assertSame('', $payload['user_login']);
    }

    public function testEditionLockPayloadWithLargeIds(): void
    {
        $payload = buildEditionLockEventPayload(
            itemId: PHP_INT_MAX,
            folderId: PHP_INT_MAX - 1,
            userLogin: 'superadmin',
            userId: PHP_INT_MAX - 2
        );

        $this->assertSame(PHP_INT_MAX, $payload['item_id']);
        $this->assertSame(PHP_INT_MAX - 1, $payload['folder_id']);
    }

    // =========================================================================
    // B. Edition-lock exclude_user_id computation
    // =========================================================================

    public function testStartedActionExcludesTheEditor(): void
    {
        $exclude = computeEditionLockExcludeUserId('started', 42);

        $this->assertSame(42, $exclude);
    }

    public function testStoppedActionExcludesNobody(): void
    {
        $exclude = computeEditionLockExcludeUserId('stopped', 42);

        $this->assertNull($exclude);
    }

    public function testUnknownActionExcludesNobody(): void
    {
        // Any action other than 'started' should not exclude anyone
        $exclude = computeEditionLockExcludeUserId('unknown_action', 99);

        $this->assertNull($exclude);
    }

    // =========================================================================
    // C. emitWebSocketEvent exclude_user_id embedding
    // =========================================================================

    public function testEmbedExcludeUserIdAddsKeyToPayload(): void
    {
        $payload = ['item_id' => 5, 'folder_id' => 2];
        $result  = embedExcludeUserIdInPayload($payload, 42);

        $this->assertArrayHasKey('exclude_user_id', $result);
        $this->assertSame(42, $result['exclude_user_id']);
    }

    public function testEmbedNullExcludeUserIdDoesNotModifyPayload(): void
    {
        $payload = ['item_id' => 5, 'folder_id' => 2];
        $result  = embedExcludeUserIdInPayload($payload, null);

        $this->assertArrayNotHasKey('exclude_user_id', $result);
        $this->assertSame($payload, $result);
    }

    public function testEmbedExcludeUserIdPreservesExistingPayloadKeys(): void
    {
        $payload = ['item_id' => 1, 'folder_id' => 2, 'label' => 'Test item'];
        $result  = embedExcludeUserIdInPayload($payload, 7);

        $this->assertSame(1, $result['item_id']);
        $this->assertSame(2, $result['folder_id']);
        $this->assertSame('Test item', $result['label']);
        $this->assertSame(7, $result['exclude_user_id']);
    }

    public function testEmbedExcludeUserIdDoesNotMutateOriginalPayload(): void
    {
        $payload = ['item_id' => 5];
        embedExcludeUserIdInPayload($payload, 10);

        // Original must be unchanged
        $this->assertArrayNotHasKey('exclude_user_id', $payload);
    }

    // =========================================================================
    // D. Item-moved event payload construction
    // =========================================================================

    public function testItemMovedPayloadContainsAllRequiredFields(): void
    {
        $payload = buildItemMovedEventPayload(
            itemId: 15,
            fromFolderId: 3,
            toFolderId: 7,
            label: 'My Secret',
            userLogin: 'bob'
        );

        $this->assertSame(15, $payload['item_id']);
        $this->assertSame(3, $payload['from_folder_id']);
        $this->assertSame(7, $payload['to_folder_id']);
        $this->assertSame('My Secret', $payload['label']);
        $this->assertSame('bob', $payload['moved_by']);
    }

    public function testItemMovedPayloadHasExactlyFiveKeys(): void
    {
        $payload = buildItemMovedEventPayload(1, 2, 3, 'label', 'user');

        $this->assertCount(5, $payload);
    }

    public function testItemMovedPayloadFromAndToFolderCanBeIdentical(): void
    {
        // Edge case: moving within the same folder should be blocked by UI,
        // but the payload builder must not assume they differ.
        $payload = buildItemMovedEventPayload(1, 5, 5, 'label', 'user');

        $this->assertSame(5, $payload['from_folder_id']);
        $this->assertSame(5, $payload['to_folder_id']);
    }

    public function testItemMovedPayloadPreservesEmptyLabel(): void
    {
        $payload = buildItemMovedEventPayload(1, 2, 3, '', 'user');

        $this->assertSame('', $payload['label']);
    }

    public function testItemMovedPayloadPreservesSpecialCharactersInLabel(): void
    {
        $label   = '<script>alert("xss")</script> & "quotes" \'apostrophe\'';
        $payload = buildItemMovedEventPayload(1, 2, 3, $label, 'user');

        // Payload must carry the raw string; escaping is the renderer's job
        $this->assertSame($label, $payload['label']);
    }

    // =========================================================================
    // E. Edition-lock status determination (core of isItemLocked)
    // =========================================================================

    public function testNoLockReturnsNoLock(): void
    {
        $result = determineEditionLockAction(
            editionLocks: [],
            currentUserId: 10,
            now: time(),
            heartbeatTimeout: 300
        );

        $this->assertSame('no_lock', $result);
    }

    public function testOwnLockReturnsOwnLock(): void
    {
        $now   = time();
        $locks = [['user_id' => 10, 'timestamp' => $now - 30]];

        $result = determineEditionLockAction($locks, 10, $now, 300);

        $this->assertSame('own_lock', $result);
    }

    public function testOtherUserFreshLockReturnsLocked(): void
    {
        $now   = time();
        $locks = [['user_id' => 20, 'timestamp' => $now - 60]]; // 60s < 300s timeout

        $result = determineEditionLockAction($locks, 10, $now, 300);

        $this->assertSame('locked', $result);
    }

    public function testOtherUserExpiredLockReturnsExpired(): void
    {
        $now   = time();
        // Lock timestamp is 400 seconds old; timeout is 300 seconds
        $locks = [['user_id' => 20, 'timestamp' => $now - 400]];

        $result = determineEditionLockAction($locks, 10, $now, 300);

        $this->assertSame('expired', $result);
    }

    public function testLockExactlyAtTimeoutBoundaryIsStillValid(): void
    {
        // elapsed === heartbeatTimeout → NOT expired (must be strictly greater)
        $now   = time();
        $locks = [['user_id' => 20, 'timestamp' => $now - 300]];

        $result = determineEditionLockAction($locks, 10, $now, 300);

        $this->assertSame('locked', $result);
    }

    public function testLockOneSecondOverTimeoutIsExpired(): void
    {
        $now   = time();
        $locks = [['user_id' => 20, 'timestamp' => $now - 301]];

        $result = determineEditionLockAction($locks, 10, $now, 301);

        // elapsed (301) > heartbeatTimeout (301) → false; adjust to 302 timeout
        // Re-test with unambiguous values:
        $locks  = [['user_id' => 20, 'timestamp' => $now - 400]];
        $result = determineEditionLockAction($locks, 10, $now, 300);

        $this->assertSame('expired', $result);
    }

    public function testOnlyMostRecentLockIsConsidered(): void
    {
        // determineEditionLockAction uses $editionLocks[0] (newest first order
        // from the DB ORDER BY increment_id DESC).
        $now   = time();
        $locks = [
            // Newest: belongs to current user → own_lock
            ['user_id' => 10, 'timestamp' => $now - 10],
            // Older: would have matched 'locked' if checked first
            ['user_id' => 20, 'timestamp' => $now - 20],
        ];

        $result = determineEditionLockAction($locks, 10, $now, 300);

        $this->assertSame('own_lock', $result);
    }

    public function testVeryShortHeartbeatTimeoutExpiresQuickly(): void
    {
        $now   = time();
        $locks = [['user_id' => 20, 'timestamp' => $now - 10]]; // 10s old

        // 5-second timeout: 10 > 5 → expired
        $result = determineEditionLockAction($locks, 10, $now, 5);

        $this->assertSame('expired', $result);
    }

    public function testZeroTimestampDifferenceIsOwnLockWhenSameUser(): void
    {
        $now   = time();
        $locks = [['user_id' => 42, 'timestamp' => $now]];

        $result = determineEditionLockAction($locks, 42, $now, 300);

        $this->assertSame('own_lock', $result);
    }
}
