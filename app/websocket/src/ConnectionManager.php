<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\ConnectionInterface;
use SplObjectStorage;

/**
 * Manages WebSocket connections and subscriptions
 *
 * Handles tracking of all active connections, user-to-connection mapping,
 * and folder subscriptions for targeted message broadcasting.
 */
class ConnectionManager
{
    /**
     * All active connections
     */
    private SplObjectStorage $connections;

    /**
     * Connections indexed by user ID: [userId => [resourceId => conn, ...]]
     */
    private array $userConnections = [];

    /**
     * Connections subscribed to folders: [folderId => [resourceId => conn, ...]]
     */
    private array $folderSubscriptions = [];

    /**
     * Item consultation presence by connection resource ID.
     *
     * @var array<int, array{folder_id: int, item_id: int, user_id: int, user_login: string}>
     */
    private array $itemViews = [];

    public function __construct()
    {
        $this->connections = new SplObjectStorage();
    }

    /**
     * Register a new authenticated connection
     *
     * @param int $userId The authenticated user's ID
     * @param ConnectionInterface $conn The WebSocket connection
     */
    public function addConnection(int $userId, ConnectionInterface $conn): void
    {
        $this->connections->attach($conn);

        if (!isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = [];
        }

        $this->userConnections[$userId][$conn->resourceId] = $conn;
    }

    /**
     * Remove a connection and clean up all its subscriptions
     *
     * @param ConnectionInterface $conn The connection to remove
     */
    public function removeConnection(ConnectionInterface $conn): void
    {
        $this->connections->detach($conn);
        $this->clearItemViewsForConnection($conn);

        // Get user ID from connection metadata
        $userId = $conn->userData['user_id'] ?? null;

        // Remove from user connections
        if ($userId !== null && isset($this->userConnections[$userId])) {
            unset($this->userConnections[$userId][$conn->resourceId]);

            // Clean up empty user entry
            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);
            }
        }

        // Remove from all folder subscriptions
        foreach ($this->folderSubscriptions as $folderId => &$subscribers) {
            unset($subscribers[$conn->resourceId]);

            // Clean up empty folder entry
            if (empty($subscribers)) {
                unset($this->folderSubscriptions[$folderId]);
            }
        }
    }

    /**
     * Subscribe a user's connections to a folder channel
     *
     * @param int $userId The user ID
     * @param int $folderId The folder ID to subscribe to
     * @param ConnectionInterface|null $specificConn Optional specific connection (otherwise all user connections)
     */
    public function subscribeToFolder(int $userId, int $folderId, ?ConnectionInterface $specificConn = null): void
    {
        if (!isset($this->folderSubscriptions[$folderId])) {
            $this->folderSubscriptions[$folderId] = [];
        }

        if ($specificConn !== null) {
            // Subscribe specific connection
            $this->folderSubscriptions[$folderId][$specificConn->resourceId] = $specificConn;
            $this->trackSubscription($specificConn, 'folder', $folderId);
        } elseif (isset($this->userConnections[$userId])) {
            // Subscribe all user connections
            foreach ($this->userConnections[$userId] as $resourceId => $conn) {
                $this->folderSubscriptions[$folderId][$resourceId] = $conn;
                $this->trackSubscription($conn, 'folder', $folderId);
            }
        }
    }

    /**
     * Unsubscribe a user from a folder channel
     *
     * @param int $userId The user ID
     * @param int $folderId The folder ID to unsubscribe from
     * @param ConnectionInterface|null $specificConn Optional specific connection
     */
    public function unsubscribeFromFolder(int $userId, int $folderId, ?ConnectionInterface $specificConn = null): void
    {
        if (!isset($this->folderSubscriptions[$folderId])) {
            return;
        }

        if ($specificConn !== null) {
            unset($this->folderSubscriptions[$folderId][$specificConn->resourceId]);
            $this->untrackSubscription($specificConn, 'folder', $folderId);
        } elseif (isset($this->userConnections[$userId])) {
            foreach ($this->userConnections[$userId] as $resourceId => $conn) {
                unset($this->folderSubscriptions[$folderId][$resourceId]);
                $this->untrackSubscription($conn, 'folder', $folderId);
            }
        }

        // Clean up empty folder entry
        if (empty($this->folderSubscriptions[$folderId])) {
            unset($this->folderSubscriptions[$folderId]);
        }
    }

    /**
     * Track subscription on connection object
     */
    private function trackSubscription(ConnectionInterface $conn, string $type, int $id): void
    {
        $subscriptions = $conn->subscriptions ?? [];

        $key = "{$type}:{$id}";
        if (!in_array($key, $subscriptions, true)) {
            $subscriptions[] = $key;
            $conn->subscriptions = $subscriptions;
        }
    }

    /**
     * Remove subscription tracking from connection object
     */
    private function untrackSubscription(ConnectionInterface $conn, string $type, int $id): void
    {
        if (!isset($conn->subscriptions)) {
            return;
        }

        $key = "{$type}:{$id}";
        $conn->subscriptions = array_values(array_filter(
            $conn->subscriptions,
            fn(string $sub): bool => $sub !== $key
        ));
    }

    /**
     * Get all connections for a specific user
     *
     * @param int $userId The user ID
     * @return ConnectionInterface[]
     */
    public function getUserConnections(int $userId): array
    {
        return $this->userConnections[$userId] ?? [];
    }

    /**
     * Get all connections subscribed to a folder
     *
     * @param int $folderId The folder ID
     * @return ConnectionInterface[]
     */
    public function getFolderSubscribers(int $folderId): array
    {
        return $this->folderSubscriptions[$folderId] ?? [];
    }

    /**
     * Mark a connection as viewing an item in read-only mode.
     *
     * @return array<int, array{folder_id: int, item_id: int}> Presence targets whose viewer list changed
     */
    public function startItemView(ConnectionInterface $conn, int $folderId, int $itemId): array
    {
        $resourceId = (int) $conn->resourceId;
        $affected = [];
        $existing = $this->itemViews[$resourceId] ?? null;

        if ($existing !== null
            && (int) $existing['folder_id'] === $folderId
            && (int) $existing['item_id'] === $itemId
        ) {
            return [];
        }

        if ($existing !== null) {
            $affected[] = [
                'folder_id' => (int) $existing['folder_id'],
                'item_id' => (int) $existing['item_id'],
            ];
        }

        $this->itemViews[$resourceId] = [
            'folder_id' => $folderId,
            'item_id' => $itemId,
            'user_id' => (int) ($conn->userData['user_id'] ?? 0),
            'user_login' => (string) ($conn->userData['user_login'] ?? ''),
        ];

        $affected[] = [
            'folder_id' => $folderId,
            'item_id' => $itemId,
        ];

        return $this->uniqueItemViewTargets($affected);
    }

    /**
     * Stop read-only item viewing for a connection.
     *
     * @return array<int, array{folder_id: int, item_id: int}> Presence targets whose viewer list changed
     */
    public function stopItemView(ConnectionInterface $conn, ?int $folderId = null, ?int $itemId = null): array
    {
        $resourceId = (int) $conn->resourceId;
        $existing = $this->itemViews[$resourceId] ?? null;

        if ($existing === null) {
            return [];
        }

        if ($folderId !== null && (int) $existing['folder_id'] !== $folderId) {
            return [];
        }

        if ($itemId !== null && (int) $existing['item_id'] !== $itemId) {
            return [];
        }

        unset($this->itemViews[$resourceId]);

        return [[
            'folder_id' => (int) $existing['folder_id'],
            'item_id' => (int) $existing['item_id'],
        ]];
    }

    /**
     * Clear all read-only item views for a connection.
     *
     * @return array<int, array{folder_id: int, item_id: int}> Presence targets whose viewer list changed
     */
    public function clearItemViewsForConnection(ConnectionInterface $conn): array
    {
        return $this->stopItemView($conn);
    }

    /**
     * Get unique users currently viewing an item.
     *
     * @return array<int, array{user_id: int, user_login: string}>
     */
    public function getItemViewers(int $folderId, int $itemId): array
    {
        $viewers = [];

        foreach ($this->itemViews as $view) {
            if ((int) $view['folder_id'] !== $folderId || (int) $view['item_id'] !== $itemId) {
                continue;
            }

            $userId = (int) $view['user_id'];
            if ($userId <= 0) {
                continue;
            }

            $viewers[$userId] = [
                'user_id' => $userId,
                'user_login' => (string) $view['user_login'],
            ];
        }

        return array_values($viewers);
    }

    /**
     * Get the item consultation presence state for a folder.
     *
     * @return array<int, array{item_id: int, viewers: array<int, array{user_id: int, user_login: string}>}>
     */
    public function getItemViewersForFolder(int $folderId): array
    {
        $items = [];

        foreach ($this->itemViews as $view) {
            if ((int) $view['folder_id'] !== $folderId) {
                continue;
            }

            $itemId = (int) $view['item_id'];
            $userId = (int) $view['user_id'];
            if ($itemId <= 0 || $userId <= 0) {
                continue;
            }

            if (!isset($items[$itemId])) {
                $items[$itemId] = [
                    'item_id' => $itemId,
                    'viewers' => [],
                ];
            }

            $items[$itemId]['viewers'][$userId] = [
                'user_id' => $userId,
                'user_login' => (string) $view['user_login'],
            ];
        }

        foreach ($items as &$item) {
            $item['viewers'] = array_values($item['viewers']);
        }
        unset($item);

        return array_values($items);
    }

    /**
     * @param array<int, array{folder_id: int, item_id: int}> $targets
     * @return array<int, array{folder_id: int, item_id: int}>
     */
    private function uniqueItemViewTargets(array $targets): array
    {
        $unique = [];

        foreach ($targets as $target) {
            $key = (int) $target['folder_id'] . ':' . (int) $target['item_id'];
            $unique[$key] = [
                'folder_id' => (int) $target['folder_id'],
                'item_id' => (int) $target['item_id'],
            ];
        }

        return array_values($unique);
    }

    /**
     * Get all active connections
     *
     * @return SplObjectStorage
     */
    public function getAllConnections(): SplObjectStorage
    {
        return $this->connections;
    }

    /**
     * Get total connection count
     */
    public function getConnectionCount(): int
    {
        return $this->connections->count();
    }

    /**
     * Get count of unique connected users
     */
    public function getUniqueUserCount(): int
    {
        return count($this->userConnections);
    }

    /**
     * Broadcast a message to a specific user (all their connections)
     *
     * @param int $userId Target user ID
     * @param array $message Message data to send
     * @return int Number of connections message was sent to
     */
    public function broadcastToUser(int $userId, array $message): int
    {
        $json = json_encode($message);
        $count = 0;

        foreach ($this->getUserConnections($userId) as $conn) {
            $conn->send($json);
            $count++;
        }

        return $count;
    }

    /**
     * Broadcast a message to all subscribers of a folder
     *
     * @param int $folderId Target folder ID
     * @param array $message Message data to send
     * @param int|null $excludeUserId Optional user ID to exclude from broadcast
     * @return int Number of connections message was sent to
     */
    public function broadcastToFolder(int $folderId, array $message, ?int $excludeUserId = null): int
    {
        $json = json_encode($message);
        $count = 0;

        foreach ($this->getFolderSubscribers($folderId) as $conn) {
            // Skip excluded user
            if ($excludeUserId !== null && ($conn->userData['user_id'] ?? null) === $excludeUserId) {
                continue;
            }

            $conn->send($json);
            $count++;
        }

        return $count;
    }

    /**
     * Broadcast a message to all connected clients
     *
     * @param array $message Message data to send
     * @param int|null $excludeUserId Optional user ID to exclude from broadcast
     * @return int Number of connections message was sent to
     */
    public function broadcastToAll(array $message, ?int $excludeUserId = null): int
    {
        $json = json_encode($message);
        $count = 0;

        foreach ($this->connections as $conn) {
            // Skip excluded user
            if ($excludeUserId !== null && ($conn->userData['user_id'] ?? null) === $excludeUserId) {
                continue;
            }

            $conn->send($json);
            $count++;
        }

        return $count;
    }

    /**
     * Get server statistics
     *
     * @return array{total_connections: int, unique_users: int, folder_subscriptions: int}
     */
    public function getStats(): array
    {
        return [
            'total_connections' => $this->connections->count(),
            'unique_users' => count($this->userConnections),
            'folder_subscriptions' => count($this->folderSubscriptions),
        ];
    }

    /**
     * Sync a user's folder subscriptions against a fresh set of accessible folder IDs.
     *
     * Unsubscribes from any folder no longer in $accessibleFolderIds and updates
     * conn->userData so subsequent permission checks reflect the current state.
     *
     * @param int   $userId              The user whose permissions changed
     * @param int[] $accessibleFolderIds Current authoritative list from DB
     */
    public function syncUserFolderAccess(int $userId, array $accessibleFolderIds): void
    {
        // Unsubscribe from every folder the user is no longer allowed to access
        foreach (array_keys($this->folderSubscriptions) as $folderId) {
            if (!in_array((int) $folderId, $accessibleFolderIds, true)) {
                $this->unsubscribeFromFolder($userId, (int) $folderId);
            }
        }

        // Refresh accessible_folders in all active connections of this user
        foreach ($this->getUserConnections($userId) as $conn) {
            if (isset($conn->userData)) {
                $conn->userData['accessible_folders'] = $accessibleFolderIds;
            }
        }
    }

    /**
     * Get list of all active resource IDs (for cleanup purposes)
     *
     * @return int[]
     */
    public function getActiveResourceIds(): array
    {
        $ids = [];
        foreach ($this->connections as $conn) {
            $ids[] = $conn->resourceId;
        }
        return $ids;
    }
}
