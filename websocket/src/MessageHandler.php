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

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'subscribed',
                'channel' => 'folder',
                'folder_id' => $folderId,
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

            return [
                'type' => 'response',
                'status' => 'success',
                'action' => 'unsubscribed',
                'channel' => 'folder',
                'folder_id' => $folderId,
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
