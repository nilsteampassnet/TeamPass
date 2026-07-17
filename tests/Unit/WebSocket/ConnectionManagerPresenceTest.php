<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassWebSocket\ConnectionManager;

require_once __DIR__ . '/../../Stubs/MockWsConnection.php';

/**
 * Unit tests for the read-only consultation presence tracking in
 * TeampassWebSocket\ConnectionManager (D3 — real-time collaboration).
 *
 * Presence drives the "viewed by" badges in the items list and detail
 * panel; these tests pin the state machine the badges rely on:
 *   - startItemView / stopItemView (affected targets returned)
 *   - getItemViewers (per item, unique users)
 *   - getItemViewersForFolder (initial sync on folder subscribe)
 *   - clearItemViewsForConnection (disconnect cleanup)
 */
class ConnectionManagerPresenceTest extends TestCase
{
    private ConnectionManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ConnectionManager();
    }

    private function connection(int $resourceId, int $userId, string $login): MockWsConnection
    {
        return new MockWsConnection($resourceId, [
            'user_id' => $userId,
            'user_login' => $login,
            'user_display_name' => ucfirst($login),
        ]);
    }

    // =========================================================================
    // startItemView
    // =========================================================================

    public function testStartItemViewReturnsAffectedTarget(): void
    {
        $conn = $this->connection(1, 10, 'alice');

        $affected = $this->manager->startItemView($conn, 5, 100);

        $this->assertSame([['folder_id' => 5, 'item_id' => 100]], $affected);
    }

    public function testStartItemViewIsIdempotentOnSameItem(): void
    {
        $conn = $this->connection(1, 10, 'alice');
        $this->manager->startItemView($conn, 5, 100);

        // Re-announcing the same view must not fan out any update.
        $this->assertSame([], $this->manager->startItemView($conn, 5, 100));
    }

    public function testStartItemViewOnNewItemReleasesPreviousTarget(): void
    {
        $conn = $this->connection(1, 10, 'alice');
        $this->manager->startItemView($conn, 5, 100);

        $affected = $this->manager->startItemView($conn, 5, 200);

        // Both the left item and the newly viewed one need a refresh.
        $this->assertSame(
            [['folder_id' => 5, 'item_id' => 100], ['folder_id' => 5, 'item_id' => 200]],
            $affected
        );
        // A connection views a single item at a time.
        $this->assertSame([], $this->manager->getItemViewers(5, 100));
        $this->assertCount(1, $this->manager->getItemViewers(5, 200));
    }

    // =========================================================================
    // getItemViewers
    // =========================================================================

    public function testViewersAreUniquePerUserAcrossConnections(): void
    {
        // Same user, two tabs on the same item -> one viewer entry.
        $this->manager->startItemView($this->connection(1, 10, 'alice'), 5, 100);
        $this->manager->startItemView($this->connection(2, 10, 'alice'), 5, 100);

        $viewers = $this->manager->getItemViewers(5, 100);

        $this->assertCount(1, $viewers);
        $this->assertSame(10, $viewers[0]['user_id']);
        $this->assertSame('alice', $viewers[0]['user_login']);
        $this->assertSame('Alice', $viewers[0]['user_display_name']);
    }

    public function testViewersListSeveralUsers(): void
    {
        $this->manager->startItemView($this->connection(1, 10, 'alice'), 5, 100);
        $this->manager->startItemView($this->connection(2, 20, 'bob'), 5, 100);

        $this->assertCount(2, $this->manager->getItemViewers(5, 100));
    }

    public function testViewersIgnoreAnonymousConnections(): void
    {
        // A connection without a user id must never appear as a viewer.
        $conn = new MockWsConnection(1, []);
        $this->manager->startItemView($conn, 5, 100);

        $this->assertSame([], $this->manager->getItemViewers(5, 100));
    }

    // =========================================================================
    // getItemViewersForFolder (initial sync on subscribe)
    // =========================================================================

    public function testFolderPresenceStateGroupsViewersByItem(): void
    {
        $this->manager->startItemView($this->connection(1, 10, 'alice'), 5, 100);
        $this->manager->startItemView($this->connection(2, 20, 'bob'), 5, 100);
        $this->manager->startItemView($this->connection(3, 30, 'carol'), 5, 200);
        $this->manager->startItemView($this->connection(4, 40, 'dave'), 9, 300); // other folder

        $state = $this->manager->getItemViewersForFolder(5);

        $this->assertCount(2, $state);
        $byItem = array_column($state, null, 'item_id');
        $this->assertCount(2, $byItem[100]['viewers']);
        $this->assertCount(1, $byItem[200]['viewers']);
        $this->assertArrayNotHasKey(300, $byItem);
    }

    // =========================================================================
    // stopItemView / clearItemViewsForConnection
    // =========================================================================

    public function testStopItemViewReturnsReleasedTarget(): void
    {
        $conn = $this->connection(1, 10, 'alice');
        $this->manager->startItemView($conn, 5, 100);

        $affected = $this->manager->stopItemView($conn, 5, 100);

        $this->assertSame([['folder_id' => 5, 'item_id' => 100]], $affected);
        $this->assertSame([], $this->manager->getItemViewers(5, 100));
    }

    public function testStopItemViewIgnoresMismatchedTarget(): void
    {
        $conn = $this->connection(1, 10, 'alice');
        $this->manager->startItemView($conn, 5, 100);

        // A stale stop for another item must not release the current view.
        $this->assertSame([], $this->manager->stopItemView($conn, 5, 999));
        $this->assertCount(1, $this->manager->getItemViewers(5, 100));
    }

    public function testStopItemViewWithoutActiveViewIsNoop(): void
    {
        $conn = $this->connection(1, 10, 'alice');

        $this->assertSame([], $this->manager->stopItemView($conn));
    }

    public function testClearItemViewsForConnectionReleasesPresence(): void
    {
        // Disconnect cleanup: the viewer badge must not survive the socket.
        $conn = $this->connection(1, 10, 'alice');
        $this->manager->startItemView($conn, 5, 100);

        $affected = $this->manager->clearItemViewsForConnection($conn);

        $this->assertSame([['folder_id' => 5, 'item_id' => 100]], $affected);
        $this->assertSame([], $this->manager->getItemViewers(5, 100));
    }
}
