<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\ConnectionInterface;

/**
 * Handles incoming WebSocket messages from clients
 *
 * Routes messages to appropriate handlers based on action type.
 * Validates permissions and enforces rate limiting.
 */
class MessageHandler
{
    private ConnectionManager $connections;
    private RateLimiter $rateLimiter;
    private Logger $logger;
    private AuthValidator $authValidator;

    /**
     * Allowed client actions
     */
    private const ALLOWED_ACTIONS = [
        'subscribe',          // Subscribe to a channel (folder)
        'unsubscribe',        // Unsubscribe from a channel
        'ping',               // Client heartbeat
        'pong',               // Response to server ping
        'get_status',         // Get connection status
        'get_stats',          // Get server stats (admin only)
        'renew_item_lock',    // Renew edition lock heartbeat
        'start_item_view',    // Mark an item as opened in read-only consultation
        'stop_item_view',     // Clear read-only consultation presence
        'renew_kb_lock',      // Renew KB edition lock heartbeat
        'start_kb_view',      // Mark a KB article as opened in consultation
        'stop_kb_view',       // Clear KB consultation presence
    ];

    public function __construct(
        ConnectionManager $connections,
        RateLimiter $rateLimiter,
        Logger $logger,
        AuthValidator $authValidator
    ) {
        $this->connections = $connections;
        $this->rateLimiter = $rateLimiter;
        $this->logger = $logger;
        $this->authValidator = $authValidator;
    }

    /**
     * Handle an incoming message from a client
     *
     * @param ConnectionInterface $conn The connection that sent the message
     * @param array $message Decoded message data
     * @return array|null Response to send back, or null for no response
     */
    public function handle(ConnectionInterface $conn, array $message): ?array
    {
        $action = $message['action'] ?? null;
        $requestId = $message['request_id'] ?? null;

        // Validate action
        if ($action === null || !in_array($action, self::ALLOWED_ACTIONS, true)) {
            return $this->error(
                'unknown_action',
                "Action '$action' is not recognized",
                $requestId
            );
        }

        // Rate limiting check (except for pong which is a response)
        if ($action !== 'pong' && !$this->rateLimiter->check($conn)) {
            $this->logger->warning('Rate limit exceeded', [
                'resourceId' => $conn->resourceId,
                'user_id' => $conn->userData['user_id'] ?? null,
                'action' => $action,
            ]);

            return $this->error(
                'rate_limited',
                'Too many requests, please slow down',
                $requestId
            );
        }

        // Route to appropriate handler
        return match ($action) {
            'subscribe' => $this->handleSubscribe($conn, $message, $requestId),
            'unsubscribe' => $this->handleUnsubscribe($conn, $message, $requestId),
            'ping' => $this->handlePing($conn, $requestId),
            'pong' => $this->handlePong($conn, $requestId),
            'get_status' => $this->handleGetStatus($conn, $requestId),
            'get_stats' => $this->handleGetStats($conn, $requestId),
            'renew_item_lock' => $this->handleRenewItemLock($conn, $message, $requestId),
            'start_item_view' => $this->handleStartItemView($conn, $message, $requestId),
            'stop_item_view' => $this->handleStopItemView($conn, $message, $requestId),
            'renew_kb_lock' => $this->handleRenewKbLock($conn, $message, $requestId),
            'start_kb_view' => $this->handleStartKbView($conn, $message, $requestId),
            'stop_kb_view' => $this->handleStopKbView($conn, $message, $requestId),
            default => null,
        };
    }

    /**
     * Handle subscribe action
     */
    private function handleSubscribe(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $channel = $message['channel'] ?? null;
        $data = $message['data'] ?? [];

        if ($channel === null) {
            return $this->error('invalid_request', 'Channel is required', $requestId);
        }

        // Currently only folder subscriptions are supported
        if ($channel === 'folder') {
            $folderId = isset($data['folder_id']) ? (int) $data['folder_id'] : null;

            if ($folderId === null) {
                return $this->error('invalid_request', 'folder_id is required', $requestId);
            }

            // Check permission
            $userData = $conn->userData ?? [];
            if (!$this->authValidator->canAccessFolder($userData, $folderId)) {
                $this->logger->warning('Folder access denied', [
                    'user_id' => $userData['user_id'] ?? null,
                    'folder_id' => $folderId,
                ]);

                return $this->error('forbidden', 'Access denied to this folder', $requestId);
            }

            // Subscribe
            $this->connections->subscribeToFolder(
                $userData['user_id'],
                $folderId,
                $conn
            );

            $this->logger->debug('Subscribed to folder', [
                'user_id' => $userData['user_id'],
                'folder_id' => $folderId,
            ]);

            // Fetch currently active edition locks for this folder so the client
            // can display lock badges for items already being edited by other users.
            $lockedItems = [];
            try {
                $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';
                $heartbeatTimeout = defined('EDITION_LOCK_HEARTBEAT_TIMEOUT')
                    ? (int) EDITION_LOCK_HEARTBEAT_TIMEOUT
                    : 300;
                $rows = \DB::query(
                    'SELECT ie.item_id, u.login AS user_login, u.name AS user_name, u.lastname AS user_lastname
                     FROM %l ie
                     JOIN %l i ON ie.item_id = i.id
                     JOIN %l u ON ie.user_id = u.id
                     WHERE i.id_tree = %i AND ie.timestamp >= %i AND ie.user_id <> %i',
                    $tablePrefix . 'items_edition',
                    $tablePrefix . 'items',
                    $tablePrefix . 'users',
                    $folderId,
                    time() - $heartbeatTimeout,
                    (int) $userData['user_id']
                );
                foreach ($rows as $row) {
                    $userLogin = (string) $row['user_login'];
                    $lockedItems[] = [
                        'item_id' => (int) $row['item_id'],
                        'user_login' => $userLogin,
                        'user_display_name' => $this->buildUserDisplayName(
                            (string) ($row['user_name'] ?? ''),
                            (string) ($row['user_lastname'] ?? ''),
                            $userLogin
                        ),
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to fetch locked items for folder', [
                    'folder_id' => $folderId,
                    'error'     => $e->getMessage(),
                ]);
            }

            return [
                'type'         => 'response',
                'status'       => 'success',
                'action'       => 'subscribed',
                'channel'      => 'folder',
                'folder_id'    => $folderId,
                'locked_items' => $lockedItems,
                'viewing_items' => $this->connections->getItemViewersForFolder($folderId),
                'request_id'   => $requestId,
            ];
        }

        if ($channel === 'kb') {
            $userData = $conn->userData ?? [];
            if (!$this->authValidator->canAccessKnowledgeBase($userData)) {
                $this->logger->warning('Knowledge base access denied', [
                    'user_id' => $userData['user_id'] ?? null,
                ]);

                return $this->error('forbidden', 'Access denied to knowledge base', $requestId);
            }

            $this->connections->subscribeToKb(
                (int) $userData['user_id'],
                $conn
            );

            $lockedKbs = [];
            try {
                $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';
                $heartbeatTimeout = defined('EDITION_LOCK_HEARTBEAT_TIMEOUT')
                    ? (int) EDITION_LOCK_HEARTBEAT_TIMEOUT
                    : 300;
                $rows = \DB::query(
                    'SELECT ke.kb_id, u.login AS user_login, u.name AS user_name, u.lastname AS user_lastname
                     FROM %l ke
                     JOIN %l k ON ke.kb_id = k.id
                     JOIN %l u ON ke.user_id = u.id
                     WHERE k.deleted_at IS NULL AND ke.timestamp >= %i AND ke.user_id <> %i',
                    $tablePrefix . 'kb_edition',
                    $tablePrefix . 'kb',
                    $tablePrefix . 'users',
                    time() - $heartbeatTimeout,
                    (int) $userData['user_id']
                );
                foreach ($rows as $row) {
                    $userLogin = (string) $row['user_login'];
                    $lockedKbs[] = [
                        'kb_id' => (int) $row['kb_id'],
                        'user_login' => $userLogin,
                        'user_display_name' => $this->buildUserDisplayName(
                            (string) ($row['user_name'] ?? ''),
                            (string) ($row['user_lastname'] ?? ''),
                            $userLogin
                        ),
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to fetch locked KB articles', [
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'subscribed',
                'channel' => 'kb',
                'locked_kbs' => $lockedKbs,
                'viewing_kbs' => $this->connections->getKbViewersForKb(),
                'request_id' => $requestId,
            ];
        }

        return $this->error('invalid_channel', "Channel '$channel' is not supported", $requestId);
    }

    /**
     * Handle unsubscribe action
     */
    private function handleUnsubscribe(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $channel = $message['channel'] ?? null;
        $data = $message['data'] ?? [];

        if ($channel === 'folder') {
            $folderId = isset($data['folder_id']) ? (int) $data['folder_id'] : null;

            if ($folderId === null) {
                return $this->error('invalid_request', 'folder_id is required', $requestId);
            }

            $userData = $conn->userData ?? [];
            $this->connections->unsubscribeFromFolder(
                $userData['user_id'] ?? 0,
                $folderId,
                $conn
            );

            $this->logger->debug('Unsubscribed from folder', [
                'user_id' => $userData['user_id'] ?? null,
                'folder_id' => $folderId,
            ]);

            $affectedViews = $this->connections->stopItemView($conn, $folderId);
            foreach ($affectedViews as $target) {
                $this->connections->broadcastItemViewersChanged($target['folder_id'], $target['item_id']);
            }

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'unsubscribed',
                'channel' => 'folder',
                'folder_id' => $folderId,
                'request_id' => $requestId,
            ];
        }

        if ($channel === 'kb') {
            $userData = $conn->userData ?? [];
            $this->connections->unsubscribeFromKb(
                (int) ($userData['user_id'] ?? 0),
                $conn
            );

            $affectedViews = $this->connections->stopKbView($conn);
            foreach ($affectedViews as $target) {
                $this->connections->broadcastKbViewersChanged($target['kb_id']);
            }

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'unsubscribed',
                'channel' => 'kb',
                'request_id' => $requestId,
            ];
        }

        return $this->error('invalid_channel', "Channel '$channel' is not supported", $requestId);
    }

    /**
     * Handle ping from client
     */
    private function handlePing(ConnectionInterface $conn, ?string $requestId): array
    {
        $conn->lastPong = time();

        return [
            'type' => 'pong',
            'timestamp' => time(),
            'request_id' => $requestId,
        ];
    }

    /**
     * Handle pong response from client (to server ping)
     */
    private function handlePong(ConnectionInterface $conn, ?string $requestId): ?array
    {
        $conn->lastPong = time();

        // No response needed for pong
        return null;
    }

    /**
     * Handle get_status request
     */
    private function handleGetStatus(ConnectionInterface $conn, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];

        return [
            'type' => 'status',
            'connected' => true,
            'user_id' => $userData['user_id'] ?? null,
            'user_login' => $userData['user_login'] ?? null,
            'user_display_name' => $userData['user_display_name'] ?? $userData['user_login'] ?? null,
            'auth_method' => $userData['auth_method'] ?? null,
            'subscriptions' => $conn->subscriptions ?? [],
            'connected_at' => $conn->connectedAt ?? null,
            'server_time' => time(),
            'request_id' => $requestId,
        ];
    }

    /**
     * Handle get_stats request (admin only)
     */
    private function handleGetStats(ConnectionInterface $conn, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];

        // Check admin permission
        if (!($userData['is_admin'] ?? false)) {
            return $this->error('forbidden', 'Admin access required', $requestId);
        }

        $stats = $this->connections->getStats();

        return [
            'type' => 'stats',
            'stats' => $stats,
            'server_time' => time(),
            'request_id' => $requestId,
        ];
    }

    /**
     * Handle renew_item_lock: refresh the edition lock timestamp for an item
     */
    private function handleRenewItemLock(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];
        $userId = $userData['user_id'] ?? null;

        if ($userId === null) {
            return $this->error('unauthorized', 'Authentication required', $requestId);
        }

        $data = $message['data'] ?? [];
        $itemId = isset($data['item_id']) ? (int) $data['item_id'] : null;

        if ($itemId === null || $itemId <= 0) {
            return $this->error('invalid_request', 'item_id is required', $requestId);
        }

        $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';

        try {
            // Only update if the lock belongs to the requesting user
            \DB::query(
                'UPDATE %l SET timestamp = %i WHERE item_id = %i AND user_id = %i',
                $tablePrefix . 'items_edition',
                time(),
                $itemId,
                (int) $userId
            );

            $affected = \DB::affectedRows();

            if ($affected === 0) {
                $this->logger->debug('Lock renewal ignored: no matching lock', [
                    'user_id' => $userId,
                    'item_id' => $itemId,
                ]);

                return $this->error('no_lock', 'No active lock found for this item', $requestId);
            }

            $this->logger->debug('Edition lock renewed', [
                'user_id' => $userId,
                'item_id' => $itemId,
            ]);

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'lock_renewed',
                'item_id' => $itemId,
                'request_id' => $requestId,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to renew edition lock', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('server_error', 'Failed to renew lock', $requestId);
        }
    }

    /**
     * Handle start_item_view: track read-only consultation presence.
     */
    private function handleStartItemView(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];
        $userId = $userData['user_id'] ?? null;

        if ($userId === null) {
            return $this->error('unauthorized', 'Authentication required', $requestId);
        }

        $data = $message['data'] ?? [];
        $itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;

        if ($itemId <= 0) {
            return $this->error('invalid_request', 'item_id is required', $requestId);
        }

        $folderId = $this->getItemFolderId($itemId);
        if ($folderId === null) {
            return $this->error('not_found', 'Item not found', $requestId);
        }

        if (!$this->authValidator->canAccessFolder($userData, $folderId)) {
            return $this->error('forbidden', 'Access denied to this item folder', $requestId);
        }

        $affectedViews = $this->connections->startItemView($conn, $folderId, $itemId);
        foreach ($affectedViews as $target) {
            $this->connections->broadcastItemViewersChanged($target['folder_id'], $target['item_id']);
        }

        return [
            'type' => 'response',
            'status' => 'success',
            'action' => 'item_view_started',
            'item_id' => $itemId,
            'folder_id' => $folderId,
            'viewers' => $this->connections->getItemViewers($folderId, $itemId),
            'request_id' => $requestId,
        ];
    }

    /**
     * Handle stop_item_view: clear read-only consultation presence.
     */
    private function handleStopItemView(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];
        if (!isset($userData['user_id'])) {
            return $this->error('unauthorized', 'Authentication required', $requestId);
        }

        $data = $message['data'] ?? [];
        $itemId = isset($data['item_id']) ? (int) $data['item_id'] : null;
        $folderId = isset($data['folder_id']) ? (int) $data['folder_id'] : null;
        $itemId = $itemId !== null && $itemId > 0 ? $itemId : null;
        $folderId = $folderId !== null && $folderId > 0 ? $folderId : null;

        if ($itemId !== null) {
            $actualFolderId = $this->getItemFolderId($itemId);
            if ($actualFolderId !== null) {
                $folderId = $actualFolderId;
            }
        }

        $affectedViews = $this->connections->stopItemView($conn, $folderId, $itemId);
        foreach ($affectedViews as $target) {
            $this->connections->broadcastItemViewersChanged($target['folder_id'], $target['item_id']);
        }

        return [
            'type' => 'response',
            'status' => 'success',
            'action' => 'item_view_stopped',
            'item_id' => $itemId,
            'folder_id' => $folderId,
            'request_id' => $requestId,
        ];
    }

    /**
     * Handle renew_kb_lock: refresh the edition lock timestamp for a KB article.
     */
    private function handleRenewKbLock(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];
        $userId = $userData['user_id'] ?? null;

        if ($userId === null) {
            return $this->error('unauthorized', 'Authentication required', $requestId);
        }

        if (!$this->authValidator->canAccessKnowledgeBase($userData)) {
            return $this->error('forbidden', 'Access denied to knowledge base', $requestId);
        }

        $data = $message['data'] ?? [];
        $kbId = isset($data['kb_id']) ? (int) $data['kb_id'] : 0;

        if ($kbId <= 0) {
            return $this->error('invalid_request', 'kb_id is required', $requestId);
        }

        $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';

        try {
            \DB::query(
                'UPDATE %l SET timestamp = %i WHERE kb_id = %i AND user_id = %i',
                $tablePrefix . 'kb_edition',
                time(),
                $kbId,
                (int) $userId
            );

            if (\DB::affectedRows() === 0) {
                return $this->error('no_lock', 'No active lock found for this KB article', $requestId);
            }

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'kb_lock_renewed',
                'kb_id' => $kbId,
                'request_id' => $requestId,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to renew KB edition lock', [
                'user_id' => $userId,
                'kb_id' => $kbId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('server_error', 'Failed to renew KB lock', $requestId);
        }
    }

    /**
     * Handle start_kb_view: track KB article consultation presence.
     */
    private function handleStartKbView(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];
        $userId = $userData['user_id'] ?? null;

        if ($userId === null) {
            return $this->error('unauthorized', 'Authentication required', $requestId);
        }

        if (!$this->authValidator->canAccessKnowledgeBase($userData)) {
            return $this->error('forbidden', 'Access denied to knowledge base', $requestId);
        }

        $data = $message['data'] ?? [];
        $kbId = isset($data['kb_id']) ? (int) $data['kb_id'] : 0;

        if ($kbId <= 0) {
            return $this->error('invalid_request', 'kb_id is required', $requestId);
        }

        if (!$this->kbExists($kbId)) {
            return $this->error('not_found', 'KB article not found', $requestId);
        }

        $affectedViews = $this->connections->startKbView($conn, $kbId);
        foreach ($affectedViews as $target) {
            $this->connections->broadcastKbViewersChanged($target['kb_id']);
        }

        return [
            'type' => 'response',
            'status' => 'success',
            'action' => 'kb_view_started',
            'kb_id' => $kbId,
            'viewers' => $this->connections->getKbViewers($kbId),
            'request_id' => $requestId,
        ];
    }

    /**
     * Handle stop_kb_view: clear KB consultation presence.
     */
    private function handleStopKbView(ConnectionInterface $conn, array $message, ?string $requestId): array
    {
        $userData = $conn->userData ?? [];
        if (!isset($userData['user_id'])) {
            return $this->error('unauthorized', 'Authentication required', $requestId);
        }

        $data = $message['data'] ?? [];
        $kbId = isset($data['kb_id']) ? (int) $data['kb_id'] : null;
        $kbId = $kbId !== null && $kbId > 0 ? $kbId : null;

        $affectedViews = $this->connections->stopKbView($conn, $kbId);
        foreach ($affectedViews as $target) {
            $this->connections->broadcastKbViewersChanged($target['kb_id']);
        }

        return [
            'type' => 'response',
            'status' => 'success',
            'action' => 'kb_view_stopped',
            'kb_id' => $kbId,
            'request_id' => $requestId,
        ];
    }

    /**
     * Resolve the current folder of an item from the database.
     */
    private function getItemFolderId(int $itemId): ?int
    {
        $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';

        try {
            $folderId = \DB::queryFirstField(
                'SELECT id_tree FROM %l WHERE id = %i',
                $tablePrefix . 'items',
                $itemId
            );
        } catch (\Exception $e) {
            $this->logger->warning('Failed to resolve item folder for presence', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return $folderId === null || $folderId === false ? null : (int) $folderId;
    }

    /**
     * Check if a knowledge base article exists and is not deleted.
     */
    private function kbExists(int $kbId): bool
    {
        $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';

        try {
            $count = \DB::queryFirstField(
                'SELECT COUNT(*) FROM %l WHERE id = %i AND deleted_at IS NULL',
                $tablePrefix . 'kb',
                $kbId
            );
        } catch (\Exception $e) {
            $this->logger->warning('Failed to resolve KB article for presence', [
                'kb_id' => $kbId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        return (int) $count > 0;
    }

    /**
     * Build a friendly user display name, falling back to the TeamPass login.
     */
    private function buildUserDisplayName(string $name, string $lastname, string $fallbackLogin): string
    {
        $displayName = trim(trim($name) . ' ' . trim($lastname));
        $displayName = preg_replace('/\s+/', ' ', $displayName) ?? '';

        return $displayName !== '' ? $displayName : $fallbackLogin;
    }

    /**
     * Create an error response
     */
    private function error(string $code, string $message, ?string $requestId): array
    {
        return [
            'type' => 'error',
            'error_code' => $code,
            'message' => $message,
            'request_id' => $requestId,
        ];
    }
}
