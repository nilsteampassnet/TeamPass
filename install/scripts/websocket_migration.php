<?php
declare(strict_types=1);

/**
 * TeamPass WebSocket Database Migration
 *
 * This script creates the necessary database tables for WebSocket functionality.
 * It should be run during TeamPass upgrade or can be executed standalone.
 *
 * Usage:
 *   - During upgrade: included by upgrade_run_X.X.X.php
 *   - Standalone: php install/scripts/websocket_migration.php
 */

// Determine if running standalone or included
$isStandalone = !defined('TP_UPGRADE');

if ($isStandalone) {
    // Standalone execution
    $rootPath = dirname(__DIR__, 2);

    // Check if settings file exists
    $settingsFile = $rootPath . '/includes/config/settings.php';
    if (!file_exists($settingsFile)) {
        die("Error: TeamPass must be installed first. Settings file not found.\n");
    }

    // Load Composer autoloader (includes MeekroDB)
    require_once $rootPath . '/vendor/autoload.php';

    // Load settings
    require_once $settingsFile;

    // Configure database connection
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = DB_PASSWD;
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    // Get table prefix
    $pre = DB_PREFIX;

    echo "TeamPass WebSocket Migration\n";
    echo "=============================\n\n";
} else {
    // Running as part of upgrade - $pre should already be defined
    if (!isset($pre)) {
        $pre = 'teampass_';
    }
}

try {
    // =========================================
    // Table: websocket_events
    // Stores events to be broadcast via WebSocket
    // =========================================

    $tableName = $pre . 'websocket_events';

    $query = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `event_type` VARCHAR(50) NOT NULL COMMENT 'Type of event (item_created, item_updated, etc.)',
        `target_type` ENUM('user', 'folder', 'broadcast') NOT NULL COMMENT 'Target type for routing',
        `target_id` INT UNSIGNED NULL COMMENT 'Target ID (user_id or folder_id)',
        `payload` JSON NOT NULL COMMENT 'Event payload data',
        `processed` TINYINT(1) UNSIGNED DEFAULT 0 COMMENT 'Has this event been broadcast?',
        `processed_at` TIMESTAMP NULL COMMENT 'When was this event processed',
        INDEX `idx_unprocessed` (`processed`, `created_at`),
        INDEX `idx_cleanup` (`processed`, `processed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='WebSocket events queue for real-time notifications';";

    DB::query($query);

    if ($isStandalone) {
        echo "[OK] Table '{$tableName}' created/verified\n";
    }

    // =========================================
    // Table: websocket_connections
    // For monitoring and debugging active connections
    // =========================================

    $tableName = $pre . 'websocket_connections';

    $query = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL COMMENT 'Connected user ID',
        `resource_id` VARCHAR(50) NOT NULL COMMENT 'Ratchet connection resource ID',
        `connected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `disconnected_at` TIMESTAMP NULL,
        `ip_address` VARCHAR(45) NULL COMMENT 'Client IP address',
        `user_agent` TEXT NULL COMMENT 'Client user agent',
        INDEX `idx_user` (`user_id`),
        INDEX `idx_active` (`disconnected_at`),
        INDEX `idx_resource` (`resource_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='WebSocket connection tracking for monitoring';";

    DB::query($query);

    if ($isStandalone) {
        echo "[OK] Table '{$tableName}' created/verified\n";
    }

    // =========================================
    // Settings in teampass_misc
    // =========================================

    $miscTable = $pre . 'misc';

    $settings = [
        [
            'type' => 'admin',
            'intitule' => 'websocket_enabled',
            'valeur' => '0',
        ],
        [
            'type' => 'admin',
            'intitule' => 'websocket_port',
            'valeur' => '8080',
        ],
        [
            'type' => 'admin',
            'intitule' => 'websocket_host',
            'valeur' => '127.0.0.1',
        ],
    ];

    foreach ($settings as $setting) {
        // Check if setting already exists
        $exists = DB::queryFirstRow(
            'SELECT id FROM %l WHERE intitule = %s',
            $miscTable,
            $setting['intitule']
        );

        if (!$exists) {
            DB::insert($miscTable, $setting);
            if ($isStandalone) {
                echo "[OK] Setting '{$setting['intitule']}' added\n";
            }
        } else {
            if ($isStandalone) {
                echo "[SKIP] Setting '{$setting['intitule']}' already exists\n";
            }
        }
    }

    // =========================================
    // Success
    // =========================================

    if ($isStandalone) {
        echo "\n=============================\n";
        echo "Migration completed successfully!\n";
        echo "\nNext steps:\n";
        echo "1. Configure websocket/config/websocket.php\n";
        echo "2. Start WebSocket server: php websocket/bin/server.php\n";
        echo "3. Enable WebSocket in TeamPass admin settings\n";
    }

} catch (Exception $e) {
    $errorMsg = "Migration failed: " . $e->getMessage();

    if ($isStandalone) {
        echo "\n[ERROR] $errorMsg\n";
        exit(1);
    } else {
        // During upgrade, log the error but don't halt the entire upgrade
        error_log($errorMsg);
    }
}
