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
