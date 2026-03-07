#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * TeamPass WebSocket Server Daemon
 *
 * This is the main entry point for the WebSocket server.
 * Run this script to start the WebSocket daemon.
 *
 * Usage:
 *   php websocket/bin/server.php
 *
 * Or with systemd:
 *   systemctl start teampass-websocket
 */

// Ensure we're running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Define root path
$rootPath = dirname(__DIR__, 2);

// Load Composer autoloader and expected files
require_once $rootPath . '/sources/main.functions.php';
require_once $rootPath . '/includes/config/include.php';
require_once $rootPath . '/includes/config/settings.php';
require_once $rootPath . '/vendor/autoload.php';

// Decrypt database password if not already done
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD));
}

// Configure database connection
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

// Load WebSocket configuration
$configFile = dirname(__DIR__) . '/config/websocket.php';
if (!file_exists($configFile)) {
    die("Error: WebSocket configuration file not found: {$configFile}\n");
}
$config = require $configFile;

// Import classes
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use TeampassWebSocket\AuthValidator;
use TeampassWebSocket\ConnectionManager;
use TeampassWebSocket\EventBroadcaster;
use TeampassWebSocket\Logger;
use TeampassWebSocket\MessageHandler;
use TeampassWebSocket\RateLimiter;
use TeampassWebSocket\WebSocketServer;

// Initialize logger
$logger = new Logger(
    $config['log_file'] ?? dirname(__DIR__) . '/logs/websocket.log',
    $config['log_level'] ?? 'info'
);

$logger->info('=== TeamPass WebSocket Server Starting ===');
$logger->info('PHP Version: ' . PHP_VERSION);
$logger->info('Configuration loaded', [
    'host' => $config['host'],
    'port' => $config['port'],
    'log_level' => $config['log_level'] ?? 'info',
]);

// Create event loop
$loop = Loop::get();

// Initialize components
$connectionManager = new ConnectionManager();
$rateLimiter = new RateLimiter(
    $config['rate_limit_messages'] ?? 10,
    $config['rate_limit_window_ms'] ?? 1000
);
$authValidator = new AuthValidator($rootPath);
$messageHandler = new MessageHandler(
    $connectionManager,
    $rateLimiter,
    $logger,
    $authValidator
);

// Create WebSocket server
$wsServer = new WebSocketServer(
    $connectionManager,
    $authValidator,
    $messageHandler,
    $rateLimiter,
    $logger,
    $config
);

// Initialize event broadcaster (polls database for events)
$eventBroadcaster = new EventBroadcaster(
    $connectionManager,
    $logger,
    $loop,
    $config
);
$eventBroadcaster->start();

// Heartbeat timer - ping clients and detect dead connections
$pingInterval = $config['ping_interval_sec'] ?? 30;
$pongTimeout = $config['pong_timeout_sec'] ?? 60;

$loop->addPeriodicTimer($pingInterval, function () use ($connectionManager, $pongTimeout, $logger) {
    $now = time();
    $stats = $connectionManager->getStats();

    $logger->debug('Heartbeat check', [
        'connections' => $stats['total_connections'],
        'users' => $stats['unique_users'],
    ]);

    foreach ($connectionManager->getAllConnections() as $conn) {
        $lastPong = $conn->lastPong ?? $conn->connectedAt ?? $now;

        // Check if connection is dead (no pong received within timeout)
        if (($now - $lastPong) > $pongTimeout) {
            $logger->warning('Connection timeout, closing', [
                'resourceId' => $conn->resourceId,
                'user_id' => $conn->userData['user_id'] ?? null,
                'last_pong_ago' => $now - $lastPong,
            ]);

            $conn->close();
            continue;
        }

        // Send ping to client
        $conn->send(json_encode([
            'type' => 'ping',
            'timestamp' => $now,
        ]));
    }
});

// Periodic stats logging (every 5 minutes)
$loop->addPeriodicTimer(300, function () use ($connectionManager, $logger) {
    $stats = $connectionManager->getStats();
    $logger->info('Server statistics', $stats);
});

// Create socket server
$bindAddress = ($config['host'] ?? '127.0.0.1') . ':' . ($config['port'] ?? 8080);

try {
    $socket = new SocketServer($bindAddress, [], $loop);
} catch (Exception $e) {
    $logger->error('Failed to bind to address', [
        'address' => $bindAddress,
        'error' => $e->getMessage(),
    ]);
    die("Error: Failed to bind to {$bindAddress}: {$e->getMessage()}\n");
}

// Create HTTP/WebSocket server stack
$server = new IoServer(
    new HttpServer(
        new WsServer($wsServer)
    ),
    $socket,
    $loop
);

$logger->info('WebSocket server started', [
    'bind_address' => $bindAddress,
]);

// Console output
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║       TeamPass WebSocket Server                          ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║  Status:  RUNNING                                        ║\n";
echo "║  Address: ws://{$bindAddress}                        ║\n";
echo "║  Log:     {$config['log_file']}  ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║  Press Ctrl+C to stop                                    ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Handle shutdown signals gracefully
if (extension_loaded('pcntl')) {
    $shutdown = function (int $signal) use ($loop, $logger) {
        $signalName = match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            default => "Signal {$signal}",
        };

        echo "\nReceived {$signalName}, shutting down...\n";
        $logger->info("Received {$signalName}, initiating shutdown");

        $loop->stop();
    };

    pcntl_signal(SIGTERM, $shutdown);
    pcntl_signal(SIGINT, $shutdown);

    // Enable async signal handling
    pcntl_async_signals(true);

    $logger->info('Signal handlers registered (SIGTERM, SIGINT)');
}

// Run the event loop
$logger->info('Entering event loop');
$loop->run();

$logger->info('=== TeamPass WebSocket Server Stopped ===');
echo "Server stopped.\n";
