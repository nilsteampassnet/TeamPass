<?php
declare(strict_types=1);

/**
 * TeamPass WebSocket Server Configuration
 *
 * This file contains all configuration options for the WebSocket server.
 * Modify these values according to your environment.
 */

return [
    // ===================
    // Server Settings
    // ===================

    // Host to bind to (127.0.0.1 for local only, 0.0.0.0 for all interfaces)
    'host' => '127.0.0.1',

    // Port to listen on (should match reverse proxy configuration)
    'port' => 8080,

    // ===================
    // Event Polling
    // ===================

    // Interval between database polls for new events (milliseconds)
    'poll_interval_ms' => 200,

    // Maximum number of events to process per poll cycle
    'poll_batch_size' => 100,

    // ===================
    // Connection Limits
    // ===================

    // Maximum simultaneous WebSocket connections per user
    'max_connections_per_user' => 5,

    // Maximum message size in bytes (64KB)
    'max_message_size' => 65536,

    // ===================
    // Rate Limiting
    // ===================

    // Maximum messages per time window
    'rate_limit_messages' => 10,

    // Time window for rate limiting (milliseconds)
    'rate_limit_window_ms' => 1000,

    // ===================
    // Heartbeat / Keep-alive
    // ===================

    // Interval between server ping messages (seconds)
    'ping_interval_sec' => 30,

    // Time to wait for pong response before considering connection dead (seconds)
    'pong_timeout_sec' => 60,

    // ===================
    // Logging
    // ===================

    // Path to log file
    'log_file' => __DIR__ . '/../logs/websocket.log',

    // Log level: debug, info, warning, error
    'log_level' => 'info',

    // ===================
    // Cleanup
    // ===================

    // How long to keep processed events in database (hours)
    'event_retention_hours' => 24,

    // Interval between cleanup runs (seconds)
    'cleanup_interval_sec' => 3600,
];
