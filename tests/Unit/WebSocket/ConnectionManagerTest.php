<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassWebSocket\ConnectionManager;

require_once __DIR__ . '/../../Stubs/MockWsConnection.php';

/**
 * Unit tests for TeampassWebSocket\ConnectionManager.
 *
 * ConnectionManager carries no DB or filesystem dependencies; every method
 * operates on in-memory SplObjectStorage / arrays only, making full isolation
 * straightforward.
 *
 * Covered scenarios:
 *   - Connection registration and removal (including cascade cleanup)
 *   - Folder subscription / unsubscription
 *   - Targeted broadcasting (user, folder, all) with optional exclusion
 *   - Stats counters
 *   - syncUserFolderAccess (permission revocation)
 */
class ConnectionManagerTest extends TestCase
{
    private ConnectionManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ConnectionManager();
    }

    // =========================================================================
    // addConnection
    // =========================================================================

    public function testAddConnectionIncreasesTotalCount(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);

        $this->assertSame(1, $this->manager->getConnectionCount());
    }

    public function testAddConnectionTracksUserConnections(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);

        $this->assertCount(1, $this->manager->getUserConnections(10));
    }

    public function testAddMultipleConnectionsForSameUser(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(10, $conn2);

        $this->assertSame(2, $this->manager->getConnectionCount());
        $this->assertCount(2, $this->manager->getUserConnections(10));
    }

    public function testAddConnectionsForDifferentUsers(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);

        $this->assertSame(2, $this->manager->getConnectionCount());
        $this->assertSame(2, $this->manager->getUniqueUserCount());
    }

    // =========================================================================
    // removeConnection
    // =========================================================================

    public function testRemoveConnectionDecrementsCount(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->removeConnection($conn);

        $this->assertSame(0, $this->manager->getConnectionCount());
    }

    public function testRemoveConnectionCleansUserConnections(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->removeConnection($conn);

        $this->assertCount(0, $this->manager->getUserConnections(10));
    }

    public function testRemoveConnectionDoesNotAffectOtherUsersConnections(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);

        $this->manager->removeConnection($conn1);

        $this->assertCount(1, $this->manager->getUserConnections(20));
    }

    public function testRemoveConnectionUnsubscribesFromFolders(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);

        $this->manager->removeConnection($conn);

        // Folder 42 should have no subscribers left
        $this->assertCount(0, $this->manager->getFolderSubscribers(42));
    }

    public function testRemoveConnectionWithoutUserIdMetadata(): void
    {
        // Connection without userData['user_id'] should not throw
        $conn = new MockWsConnection(99);
        $this->manager->addConnection(5, $conn);
        $this->manager->removeConnection($conn);

        $this->assertSame(0, $this->manager->getConnectionCount());
    }

    // =========================================================================
    // subscribeToFolder / unsubscribeFromFolder
    // =========================================================================

    public function testSubscribeSpecificConnectionToFolder(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);

        $this->assertCount(1, $this->manager->getFolderSubscribers(42));
    }

    public function testSubscribeAllUserConnectionsToFolder(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(10, $conn2);

        // No specificConn → subscribes all connections for user 10
        $this->manager->subscribeToFolder(10, 42);

        $this->assertCount(2, $this->manager->getFolderSubscribers(42));
    }

    public function testSubscribeSameConnectionTwiceIsIdempotent(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn); // duplicate

        $this->assertCount(1, $this->manager->getFolderSubscribers(42));
    }

    public function testUnsubscribeSpecificConnectionFromFolder(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);
        $this->manager->unsubscribeFromFolder(10, 42, $conn);

        $this->assertCount(0, $this->manager->getFolderSubscribers(42));
    }

    public function testUnsubscribeAllUserConnectionsFromFolder(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(10, $conn2);
        $this->manager->subscribeToFolder(10, 42);
        $this->manager->unsubscribeFromFolder(10, 42); // no specificConn

        $this->assertCount(0, $this->manager->getFolderSubscribers(42));
    }

    public function testUnsubscribeFromNonExistentFolderDoesNotThrow(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);

        // Should not throw
        $this->manager->unsubscribeFromFolder(10, 999, $conn);

        $this->assertCount(0, $this->manager->getFolderSubscribers(999));
    }

    public function testUnsubscribeOnlyAffectsTargetUser(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);
        $this->manager->subscribeToFolder(10, 42, $conn1);
        $this->manager->subscribeToFolder(20, 42, $conn2);

        $this->manager->unsubscribeFromFolder(10, 42, $conn1);

        // User 20 must still be subscribed
        $this->assertCount(1, $this->manager->getFolderSubscribers(42));
    }

    // =========================================================================
    // broadcastToFolder
    // =========================================================================

    public function testBroadcastToFolderSendsToAllSubscribers(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);
        $this->manager->subscribeToFolder(10, 42, $conn1);
        $this->manager->subscribeToFolder(20, 42, $conn2);

        $count = $this->manager->broadcastToFolder(42, ['type' => 'event', 'event' => 'item_updated']);

        $this->assertSame(2, $count);
        $this->assertCount(1, $conn1->sentMessages);
        $this->assertCount(1, $conn2->sentMessages);
    }

    public function testBroadcastToFolderExcludesSpecifiedUser(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);
        $this->manager->subscribeToFolder(10, 42, $conn1);
        $this->manager->subscribeToFolder(20, 42, $conn2);

        $count = $this->manager->broadcastToFolder(42, ['type' => 'event'], excludeUserId: 10);

        // Only user 20 should receive the message
        $this->assertSame(1, $count);
        $this->assertCount(0, $conn1->sentMessages);
        $this->assertCount(1, $conn2->sentMessages);
    }

    public function testBroadcastToFolderReturnsZeroWhenNoSubscribers(): void
    {
        $count = $this->manager->broadcastToFolder(999, ['type' => 'event']);

        $this->assertSame(0, $count);
    }

    public function testBroadcastToFolderPayloadIsValidJson(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);

        $payload = ['type' => 'event', 'event' => 'item_moved', 'data' => ['item_id' => 5]];
        $this->manager->broadcastToFolder(42, $payload);

        $decoded = json_decode($conn->sentMessages[0], true);
        $this->assertSame('item_moved', $decoded['event']);
        $this->assertSame(5, $decoded['data']['item_id']);
    }

    // =========================================================================
    // broadcastToUser
    // =========================================================================

    public function testBroadcastToUserSendsToAllUserConnections(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(10, $conn2);

        $count = $this->manager->broadcastToUser(10, ['type' => 'event', 'event' => 'session_expired']);

        $this->assertSame(2, $count);
        $this->assertCount(1, $conn1->sentMessages);
        $this->assertCount(1, $conn2->sentMessages);
    }

    public function testBroadcastToUserDoesNotReachOtherUsers(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);

        $this->manager->broadcastToUser(10, ['type' => 'event']);

        $this->assertCount(0, $conn2->sentMessages);
    }

    public function testBroadcastToUserReturnsZeroForUnknownUser(): void
    {
        $count = $this->manager->broadcastToUser(999, ['type' => 'event']);

        $this->assertSame(0, $count);
    }

    // =========================================================================
    // broadcastToAll
    // =========================================================================

    public function testBroadcastToAllReachesEveryConnection(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $conn3 = new MockWsConnection(3, ['user_id' => 30]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);
        $this->manager->addConnection(30, $conn3);

        $count = $this->manager->broadcastToAll(['type' => 'event', 'event' => 'system_maintenance']);

        $this->assertSame(3, $count);
    }

    public function testBroadcastToAllExcludesSpecifiedUser(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);

        $count = $this->manager->broadcastToAll(['type' => 'event'], excludeUserId: 10);

        $this->assertSame(1, $count);
        $this->assertCount(0, $conn1->sentMessages);
        $this->assertCount(1, $conn2->sentMessages);
    }

    public function testBroadcastToAllReturnsZeroWhenEmpty(): void
    {
        $count = $this->manager->broadcastToAll(['type' => 'event']);

        $this->assertSame(0, $count);
    }

    // =========================================================================
    // Stats
    // =========================================================================

    public function testGetConnectionCountReflectsAddRemoveCycle(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);
        $this->manager->removeConnection($conn1);

        $this->assertSame(1, $this->manager->getConnectionCount());
    }

    public function testGetUniqueUserCountDropsToZeroAfterLastRemoval(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->removeConnection($conn);

        $this->assertSame(0, $this->manager->getUniqueUserCount());
    }

    public function testGetStatsReturnsCorrectStructure(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);
        $this->manager->subscribeToFolder(10, 42, $conn1);

        $stats = $this->manager->getStats();

        $this->assertSame(2, $stats['total_connections']);
        $this->assertSame(2, $stats['unique_users']);
        $this->assertSame(1, $stats['folder_subscriptions']);
    }

    // =========================================================================
    // syncUserFolderAccess
    // =========================================================================

    public function testSyncUserFolderAccessUnsubscribesRevokedFolder(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10, 'accessible_folders' => [42, 43]]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);
        $this->manager->subscribeToFolder(10, 43, $conn);

        // User loses access to folder 42
        $this->manager->syncUserFolderAccess(10, [43]);

        $this->assertCount(0, $this->manager->getFolderSubscribers(42));
        $this->assertCount(1, $this->manager->getFolderSubscribers(43));
    }

    public function testSyncUserFolderAccessUpdatesUserDataOnConnection(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10, 'accessible_folders' => [42, 43]]);
        $this->manager->addConnection(10, $conn);

        $this->manager->syncUserFolderAccess(10, [43]);

        $this->assertSame([43], $conn->userData['accessible_folders']);
    }

    public function testSyncUserFolderAccessWithEmptyListRemovesAllSubscriptions(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);
        $this->manager->subscribeToFolder(10, 43, $conn);

        $this->manager->syncUserFolderAccess(10, []);

        $this->assertCount(0, $this->manager->getFolderSubscribers(42));
        $this->assertCount(0, $this->manager->getFolderSubscribers(43));
    }

    public function testSyncUserFolderAccessDoesNotAffectOtherUsers(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);
        $this->manager->subscribeToFolder(10, 42, $conn1);
        $this->manager->subscribeToFolder(20, 42, $conn2);

        // User 10 loses access to folder 42, user 20 keeps it
        $this->manager->syncUserFolderAccess(10, []);

        // User 20 must still be subscribed
        $this->assertCount(1, $this->manager->getFolderSubscribers(42));
    }

    // =========================================================================
    // getFolderSubscribers / getUserConnections edge cases
    // =========================================================================

    public function testGetFolderSubscribersReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->manager->getFolderSubscribers(99));
    }

    public function testGetUserConnectionsReturnsEmptyArrayForUnknownUser(): void
    {
        $this->assertSame([], $this->manager->getUserConnections(99));
    }

    // =========================================================================
    // getActiveResourceIds
    // =========================================================================

    public function testGetActiveResourceIdsReturnsRegisteredIds(): void
    {
        $conn1 = new MockWsConnection(10, ['user_id' => 1]);
        $conn2 = new MockWsConnection(20, ['user_id' => 2]);
        $this->manager->addConnection(1, $conn1);
        $this->manager->addConnection(2, $conn2);

        $ids = $this->manager->getActiveResourceIds();

        $this->assertContains(10, $ids);
        $this->assertContains(20, $ids);
        $this->assertCount(2, $ids);
    }

    // =========================================================================
    // getAllConnections
    // =========================================================================

    public function testGetAllConnectionsReturnsSplObjectStorage(): void
    {
        $conn1 = new MockWsConnection(1, ['user_id' => 10]);
        $conn2 = new MockWsConnection(2, ['user_id' => 20]);
        $this->manager->addConnection(10, $conn1);
        $this->manager->addConnection(20, $conn2);

        $all = $this->manager->getAllConnections();

        $this->assertInstanceOf(\SplObjectStorage::class, $all);
        $this->assertCount(2, $all);
        $this->assertTrue($all->contains($conn1));
        $this->assertTrue($all->contains($conn2));
    }

    public function testGetAllConnectionsReturnsEmptyStorageInitially(): void
    {
        $all = $this->manager->getAllConnections();

        $this->assertInstanceOf(\SplObjectStorage::class, $all);
        $this->assertCount(0, $all);
    }

    // =========================================================================
    // untrackSubscription early-exit guard
    // =========================================================================

    /**
     * Cover the early-return branch in untrackSubscription() when the
     * connection object has no $subscriptions property set.
     *
     * Ratchet connections are stdClass-like objects; the property may be
     * absent on the first unsubscribe if trackSubscription() was never called
     * for this connection.  MockWsConnection initialises $subscriptions = [],
     * so we unset it to reproduce that state.
     */
    public function testUnsubscribeConnectionWithUnsetSubscriptionsPropertyDoesNotThrow(): void
    {
        $conn = new MockWsConnection(1, ['user_id' => 10]);
        $this->manager->addConnection(10, $conn);
        $this->manager->subscribeToFolder(10, 42, $conn);

        // Simulate a connection where the subscriptions tracker was never
        // initialised — covers the `!isset($conn->subscriptions)` guard.
        unset($conn->subscriptions);

        $this->manager->unsubscribeFromFolder(10, 42, $conn);

        // The folder entry must still be cleaned up even though the
        // connection's internal tracker could not be updated.
        $this->assertCount(0, $this->manager->getFolderSubscribers(42));
    }
}
