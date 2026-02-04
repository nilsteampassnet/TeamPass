<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use React\EventLoop\LoopInterface;
use Exception;

/**
 * Broadcasts events from database to WebSocket clients
 *
 * Polls the websocket_events table for new events and dispatches them
 * to the appropriate clients based on target type (user, folder, broadcast).
 */
class EventBroadcaster
{
    private ConnectionManager $connections;
    private Logger $logger;
    private LoopInterface $loop;
    private array $config;

    /**
     * Database table prefix
     */
    private string $tablePrefix;

    /**
     * Flag to track if broadcaster is running
     */
    private bool $isRunning = false;

    public function __construct(
        ConnectionManager $connections,
        Logger $logger,
        LoopInterface $loop,
        array $config
    ) {
        $this->connections = $connections;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->config = $config;
        $this->tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';
    }

    /**
     * Start the event broadcaster
     *
     * Sets up periodic timers for polling events and cleanup.
     */
    public function start(): void
    {
        if ($this->isRunning) {
            $this->logger->warning('EventBroadcaster already running');
            return;
        }

        $this->isRunning = true;

        // Poll interval in seconds (config is in milliseconds)
        $pollInterval = ($this->config['poll_interval_ms'] ?? 200) / 1000;

        // Add periodic timer for polling events
        $this->loop->addPeriodicTimer($pollInterval, function () {
            $this->pollAndBroadcast();
        });

        // Add periodic timer for cleanup (default: every hour)
        $cleanupInterval = $this->config['cleanup_interval_sec'] ?? 3600;
        $this->loop->addPeriodicTimer($cleanupInterval, function () {
            $this->cleanupOldEvents();
        });

        $this->logger->info('EventBroadcaster started', [
            'poll_interval_ms' => $this->config['poll_interval_ms'] ?? 200,
            'cleanup_interval_sec' => $cleanupInterval,
        ]);
    }

    /**
     * Poll database for new events and broadcast them
     */
    public function pollAndBroadcast(): void
    {
        try {
            $batchSize = $this->config['poll_batch_size'] ?? 100;
            $tableName = $this->tablePrefix . 'websocket_events';

            // Fetch unprocessed events
            $events = \DB::query(
                'SELECT * FROM %l WHERE processed = 0 ORDER BY created_at ASC LIMIT %i',
                $tableName,
                $batchSize
            );

            if (empty($events)) {
                return;
            }

            $processedIds = [];
            $failedIds = [];

            foreach ($events as $event) {
                try {
                    $dispatched = $this->dispatchEvent($event);

                    if ($dispatched) {
                        $processedIds[] = (int) $event['id'];
                    } else {
                        // No clients to dispatch to, still mark as processed
                        $processedIds[] = (int) $event['id'];
                    }

                } catch (Exception $e) {
                    $this->logger->error('Failed to dispatch event', [
                        'event_id' => $event['id'],
                        'event_type' => $event['event_type'],
                        'error' => $e->getMessage(),
                    ]);
                    $failedIds[] = (int) $event['id'];
                }
            }

            // Mark processed events
            if (!empty($processedIds)) {
                \DB::query(
                    'UPDATE %l SET processed = 1, processed_at = NOW() WHERE id IN %li',
                    $tableName,
                    $processedIds
                );

                $this->logger->debug('Events processed', [
                    'count' => count($processedIds),
                ]);
            }

            // Log failures but don't retry immediately (will be picked up next poll)
            if (!empty($failedIds)) {
                $this->logger->warning('Some events failed to dispatch', [
                    'failed_count' => count($failedIds),
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Poll error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch a single event to appropriate clients
     *
     * @param array $event Event data from database
     * @return bool True if event was dispatched to at least one client
     */
    private function dispatchEvent(array $event): bool
    {
        $payload = json_decode($event['payload'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Invalid event payload JSON', [
                'event_id' => $event['id'],
            ]);
            return false;
        }

        $message = [
            'type' => 'event',
            'event' => $event['event_type'],
            'data' => $payload,
            'timestamp' => strtotime($event['created_at']),
        ];

        $count = 0;

        switch ($event['target_type']) {
            case 'user':
                if (!empty($event['target_id'])) {
                    $count = $this->connections->broadcastToUser(
                        (int) $event['target_id'],
                        $message
                    );
                }
                break;

            case 'folder':
                if (!empty($event['target_id'])) {
                    // Optionally exclude the user who triggered the event
                    $excludeUserId = $payload['exclude_user_id'] ?? null;
                    $count = $this->connections->broadcastToFolder(
                        (int) $event['target_id'],
                        $message,
                        $excludeUserId ? (int) $excludeUserId : null
                    );
                }
                break;

            case 'broadcast':
                $excludeUserId = $payload['exclude_user_id'] ?? null;
                $count = $this->connections->broadcastToAll(
                    $message,
                    $excludeUserId ? (int) $excludeUserId : null
                );
                break;

            default:
                $this->logger->warning('Unknown target type', [
                    'event_id' => $event['id'],
                    'target_type' => $event['target_type'],
                ]);
                return false;
        }

        if ($count > 0) {
            $this->logger->debug('Event dispatched', [
                'event_id' => $event['id'],
                'event_type' => $event['event_type'],
                'target_type' => $event['target_type'],
                'target_id' => $event['target_id'],
                'recipients' => $count,
            ]);
        }

        return true;
    }

    /**
     * Clean up old processed events from database
     */
    private function cleanupOldEvents(): void
    {
        try {
            $retentionHours = $this->config['event_retention_hours'] ?? 24;
            $tableName = $this->tablePrefix . 'websocket_events';

            $result = \DB::query(
                'DELETE FROM %l WHERE processed = 1 AND processed_at < DATE_SUB(NOW(), INTERVAL %i HOUR)',
                $tableName,
                $retentionHours
            );

            $deletedCount = \DB::affectedRows();

            if ($deletedCount > 0) {
                $this->logger->info('Cleaned up old events', [
                    'deleted_count' => $deletedCount,
                    'retention_hours' => $retentionHours,
                ]);
            }

        } catch (Exception $e) {
            $this->logger->error('Cleanup error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manually trigger an event broadcast (for testing)
     *
     * @param string $eventType Event type name
     * @param string $targetType Target type (user, folder, broadcast)
     * @param int|null $targetId Target ID
     * @param array $payload Event payload data
     * @return bool True if dispatched successfully
     */
    public function triggerEvent(
        string $eventType,
        string $targetType,
        ?int $targetId,
        array $payload
    ): bool {
        $event = [
            'id' => 0,
            'event_type' => $eventType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => json_encode($payload),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->dispatchEvent($event);
    }

    /**
     * Check if broadcaster is running
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }
}
