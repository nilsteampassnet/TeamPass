<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Exception;

/**
 * Main WebSocket server implementation
 *
 * Handles WebSocket lifecycle events (open, message, close, error)
 * and coordinates between authentication, connection management,
 * and message handling components.
 */
class WebSocketServer implements MessageComponentInterface
{
    private ConnectionManager $connections;
    private AuthValidator $authValidator;
    private MessageHandler $messageHandler;
    private RateLimiter $rateLimiter;
    private Logger $logger;
    private array $config;

    public function __construct(
        ConnectionManager $connections,
        AuthValidator $authValidator,
        MessageHandler $messageHandler,
        RateLimiter $rateLimiter,
        Logger $logger,
        array $config
    ) {
        $this->connections = $connections;
        $this->authValidator = $authValidator;
        $this->messageHandler = $messageHandler;
        $this->rateLimiter = $rateLimiter;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Called when a new WebSocket connection is opened
     *
     * @param ConnectionInterface $conn The new connection
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->logger->debug('New connection attempt', [
            'resourceId' => $conn->resourceId,
        ]);

        // Extract query parameters from WebSocket handshake
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $params);

        // Extract cookies from HTTP headers
        $cookies = $this->extractCookies($conn);

        // Attempt authentication
        $userData = null;

        // Try WebSocket token authentication first (for web clients)
        if (!empty($params['token'])) {
            // First try as WebSocket token (64 hex chars)
            $userData = $this->authValidator->validateFromToken($params['token']);
            if ($userData) {
                $this->logger->debug('WebSocket token authentication successful', [
                    'resourceId' => $conn->resourceId,
                    'user_id' => $userData['user_id'],
                ]);
            } else {
                // Fall back to JWT authentication (for API clients)
                $userData = $this->authValidator->validateFromJwt($params['token']);
                if ($userData) {
                    $this->logger->debug('JWT authentication successful', [
                        'resourceId' => $conn->resourceId,
                        'user_id' => $userData['user_id'],
                    ]);
                }
            }
        }

        // Fall back to session authentication (legacy, may not work with encrypted sessions)
        if ($userData === null && !empty($cookies['PHPSESSID'])) {
            $userData = $this->authValidator->validateFromSession($cookies['PHPSESSID']);
            if ($userData) {
                $this->logger->debug('Session authentication successful', [
                    'resourceId' => $conn->resourceId,
                    'user_id' => $userData['user_id'],
                ]);
            }
        }

        // Reject unauthenticated connections
        if ($userData === null) {
            $this->logger->warning('Authentication failed', [
                'resourceId' => $conn->resourceId,
                'has_token' => !empty($params['token']),
                'has_session' => !empty($cookies['PHPSESSID']),
            ]);

            $conn->send(json_encode([
                'type' => 'error',
                'error_code' => 'auth_failed',
                'message' => 'Authentication required. Provide a valid session or JWT token.',
            ]));

            $conn->close();
            return;
        }

        // Check connection limit per user
        $existingConnections = $this->connections->getUserConnections($userData['user_id']);
        $maxConnections = $this->config['max_connections_per_user'] ?? 5;

        if (count($existingConnections) >= $maxConnections) {
            $this->logger->warning('Connection limit exceeded', [
                'resourceId' => $conn->resourceId,
                'user_id' => $userData['user_id'],
                'current_connections' => count($existingConnections),
                'max_connections' => $maxConnections,
            ]);

            $conn->send(json_encode([
                'type' => 'error',
                'error_code' => 'max_connections',
                'message' => "Maximum {$maxConnections} simultaneous connections per user.",
            ]));

            $conn->close();
            return;
        }

        // Store user data on connection object
        $conn->userData = $userData;
        $conn->connectedAt = time();
        $conn->lastPong = time();
        $conn->subscriptions = [];

        // Register the connection
        $this->connections->addConnection($userData['user_id'], $conn);

        $this->logger->info('Connection established', [
            'resourceId' => $conn->resourceId,
            'user_id' => $userData['user_id'],
            'user_login' => $userData['user_login'],
            'auth_method' => $userData['auth_method'],
            'total_connections' => $this->connections->getConnectionCount(),
        ]);

        // Send connection confirmation
        $conn->send(json_encode([
            'type' => 'connected',
            'user_id' => $userData['user_id'],
            'user_login' => $userData['user_login'],
            'server_time' => time(),
            'config' => [
                'ping_interval' => $this->config['ping_interval_sec'] ?? 30,
            ],
        ]));
    }

    /**
     * Called when a message is received from a client
     *
     * @param ConnectionInterface $conn The connection that sent the message
     * @param string $msg The message content
     */
    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        // Check message size
        $maxSize = $this->config['max_message_size'] ?? 65536;
        if (strlen($msg) > $maxSize) {
            $this->logger->warning('Message too large', [
                'resourceId' => $conn->resourceId,
                'size' => strlen($msg),
                'max_size' => $maxSize,
            ]);

            $conn->send(json_encode([
                'type' => 'error',
                'error_code' => 'message_too_large',
                'message' => "Message exceeds maximum size of {$maxSize} bytes.",
            ]));

            return;
        }

        // Parse JSON message
        $message = json_decode($msg, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Invalid JSON received', [
                'resourceId' => $conn->resourceId,
                'error' => json_last_error_msg(),
            ]);

            $conn->send(json_encode([
                'type' => 'error',
                'error_code' => 'invalid_json',
                'message' => 'Message must be valid JSON.',
            ]));

            return;
        }

        $this->logger->debug('Message received', [
            'resourceId' => $conn->resourceId,
            'action' => $message['action'] ?? 'unknown',
            'user_id' => $conn->userData['user_id'] ?? null,
        ]);

        // Process the message
        $response = $this->messageHandler->handle($conn, $message);

        // Send response if any
        if ($response !== null) {
            $conn->send(json_encode($response));
        }
    }

    /**
     * Called when a connection is closed
     *
     * @param ConnectionInterface $conn The connection that closed
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $userId = $conn->userData['user_id'] ?? 'unknown';
        $userLogin = $conn->userData['user_login'] ?? 'unknown';
        $connectedAt = $conn->connectedAt ?? null;
        $duration = $connectedAt ? time() - $connectedAt : 0;

        // Clean up
        $this->connections->removeConnection($conn);
        $this->rateLimiter->cleanup($conn);

        $this->logger->info('Connection closed', [
            'resourceId' => $conn->resourceId,
            'user_id' => $userId,
            'user_login' => $userLogin,
            'duration_seconds' => $duration,
            'remaining_connections' => $this->connections->getConnectionCount(),
        ]);
    }

    /**
     * Called when an error occurs on a connection
     *
     * @param ConnectionInterface $conn The connection with the error
     * @param Exception $e The exception that occurred
     */
    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $this->logger->error('Connection error', [
            'resourceId' => $conn->resourceId,
            'user_id' => $conn->userData['user_id'] ?? null,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $conn->close();
    }

    /**
     * Extract cookies from HTTP request headers
     *
     * @param ConnectionInterface $conn The connection with HTTP request
     * @return array Associative array of cookie name => value
     */
    private function extractCookies(ConnectionInterface $conn): array
    {
        $cookies = [];

        $cookieHeader = $conn->httpRequest->getHeader('Cookie');
        if (!empty($cookieHeader)) {
            $cookieString = is_array($cookieHeader) ? $cookieHeader[0] : $cookieHeader;

            foreach (explode(';', $cookieString) as $cookie) {
                $parts = explode('=', trim($cookie), 2);
                if (count($parts) === 2) {
                    $cookies[trim($parts[0])] = trim($parts[1]);
                }
            }
        }

        return $cookies;
    }

    /**
     * Get the connection manager instance
     *
     * @return ConnectionManager
     */
    public function getConnectionManager(): ConnectionManager
    {
        return $this->connections;
    }

    /**
     * Get server statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->connections->getStats();
    }
}
