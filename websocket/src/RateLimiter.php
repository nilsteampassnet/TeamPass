<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Ratchet\ConnectionInterface;

/**
 * Rate limiter for WebSocket connections
 *
 * Prevents abuse by limiting the number of messages a client can send
 * within a configurable time window. Uses a sliding window algorithm.
 */
class RateLimiter
{
    /**
     * Maximum messages allowed per time window
     */
    private int $maxMessages;

    /**
     * Time window in milliseconds
     */
    private int $windowMs;

    /**
     * Message timestamps per connection: [resourceId => [timestamp1, timestamp2, ...]]
     */
    private array $messageHistory = [];

    /**
     * @param int $maxMessages Maximum messages per window (default: 10)
     * @param int $windowMs Window size in milliseconds (default: 1000ms = 1 second)
     */
    public function __construct(int $maxMessages = 10, int $windowMs = 1000)
    {
        $this->maxMessages = $maxMessages;
        $this->windowMs = $windowMs;
    }

    /**
     * Check if a connection is allowed to send a message
     *
     * @param ConnectionInterface $conn The connection to check
     * @return bool True if allowed, false if rate limited
     */
    public function check(ConnectionInterface $conn): bool
    {
        $id = $conn->resourceId;
        $now = $this->getCurrentTimeMs();
        $windowStart = $now - $this->windowMs;

        // Initialize history for new connections
        if (!isset($this->messageHistory[$id])) {
            $this->messageHistory[$id] = [];
        }

        // Remove timestamps outside the current window
        $this->messageHistory[$id] = array_values(array_filter(
            $this->messageHistory[$id],
            fn(int $ts): bool => $ts > $windowStart
        ));

        // Check if limit exceeded
        if (count($this->messageHistory[$id]) >= $this->maxMessages) {
            return false;
        }

        // Record this message
        $this->messageHistory[$id][] = $now;

        return true;
    }

    /**
     * Get remaining messages allowed for a connection
     *
     * @param ConnectionInterface $conn The connection to check
     * @return int Number of messages still allowed in current window
     */
    public function getRemaining(ConnectionInterface $conn): int
    {
        $id = $conn->resourceId;

        if (!isset($this->messageHistory[$id])) {
            return $this->maxMessages;
        }

        $now = $this->getCurrentTimeMs();
        $windowStart = $now - $this->windowMs;

        $recentCount = count(array_filter(
            $this->messageHistory[$id],
            fn(int $ts): bool => $ts > $windowStart
        ));

        return max(0, $this->maxMessages - $recentCount);
    }

    /**
     * Clean up history for a disconnected connection
     *
     * @param ConnectionInterface $conn The connection to clean up
     */
    public function cleanup(ConnectionInterface $conn): void
    {
        unset($this->messageHistory[$conn->resourceId]);
    }

    /**
     * Clean up stale entries (connections that no longer exist)
     * Should be called periodically to prevent memory leaks
     *
     * @param array $activeResourceIds List of currently active resource IDs
     */
    public function cleanupStale(array $activeResourceIds): void
    {
        $activeIds = array_flip($activeResourceIds);

        foreach (array_keys($this->messageHistory) as $id) {
            if (!isset($activeIds[$id])) {
                unset($this->messageHistory[$id]);
            }
        }
    }

    /**
     * Get current time in milliseconds
     */
    private function getCurrentTimeMs(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * Get current configuration
     *
     * @return array{max_messages: int, window_ms: int}
     */
    public function getConfig(): array
    {
        return [
            'max_messages' => $this->maxMessages,
            'window_ms' => $this->windowMs,
        ];
    }
}
