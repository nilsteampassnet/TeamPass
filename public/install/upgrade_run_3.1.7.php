<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      upgrade_run_3.1.7.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__.'/../sources/backup.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);
$_SESSION['CPM'] = 1;

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

//include librairies
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';

// DataBase
// Test DB connexion
$pass = defuse_return_decrypted(DB_PASSWD);
$server = (string) DB_HOST;
$pre = (string) DB_PREFIX;
$database = (string) DB_NAME;
$port = (int) DB_PORT;
$user = (string) DB_USER;

$db_link = mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
);
if ($db_link) {
    $db_link->set_charset(DB_ENCODING);
} else {
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    exit();
}

// Load libraries
$superGlobal = new SuperGlobal();
$lang = new Language(); 

//---------------------------------------------------------------------

//--->BEGIN 3.1.7

// ==========================================
// WebSocket: Create table and settings
// ==========================================
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "websocket_events` (
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
        COMMENT='WebSocket events queue for real-time notifications';"
);
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "websocket_connections` (
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
            COMMENT='WebSocket connection tracking for monitoring';"
);
// WebSocket authentication tokens table
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "websocket_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL COMMENT 'User ID',
                `token` VARCHAR(64) NOT NULL COMMENT 'Authentication token',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NOT NULL COMMENT 'Token expiration time',
                `used` TINYINT(1) UNSIGNED DEFAULT 0 COMMENT 'Has this token been used?',
                UNIQUE INDEX `idx_token` (`token`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='WebSocket authentication tokens';"
);
// --->


// <---
// ==========================================
// Options page: Favorites storage for administrators
// ==========================================
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "users_options_favorites` (
            `id` INT(12) NOT NULL AUTO_INCREMENT,
            `user_id` INT(12) NOT NULL,
            `option_key` VARCHAR(100) NOT NULL,
            `position` INT(12) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_option` (`user_id`, `option_key`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_user_position` (`user_id`, `position`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Options page favorites stored per administrator';"
);
// --->


// <---
// ==========================================
// Network ACL: tables and settings for IPv4 whitelist / blacklist
// ==========================================
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "network_acl` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` ENUM('whitelist', 'blacklist') NOT NULL,
            `rule_definition` VARCHAR(50) NOT NULL COMMENT 'IPv4 or CIDR IPv4 rule',
            `comment` VARCHAR(255) NOT NULL DEFAULT '',
            `enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
            `updated_by` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_type_rule` (`type`, `rule_definition`),
            KEY `idx_type_enabled` (`type`, `enabled`),
            KEY `idx_enabled` (`enabled`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Network ACL rules for IPv4 whitelist and blacklist';"
);

// Ensure indexes exist on network ACL table
$result = mysqli_query(
    $db_link,
    "SHOW INDEX FROM `" . $pre . "network_acl` WHERE Key_name = 'uniq_type_rule';"
);
if ($result !== false && mysqli_num_rows($result) === 0) {
    mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "network_acl`
        ADD UNIQUE INDEX `uniq_type_rule` (`type`, `rule_definition`);"
    );
}

$result = mysqli_query(
    $db_link,
    "SHOW INDEX FROM `" . $pre . "network_acl` WHERE Key_name = 'idx_type_enabled';"
);
if ($result !== false && mysqli_num_rows($result) === 0) {
    mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "network_acl`
        ADD INDEX `idx_type_enabled` (`type`, `enabled`);"
    );
}

$result = mysqli_query(
    $db_link,
    "SHOW INDEX FROM `" . $pre . "network_acl` WHERE Key_name = 'idx_enabled';"
);
if ($result !== false && mysqli_num_rows($result) === 0) {
    mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "network_acl`
        ADD INDEX `idx_enabled` (`enabled`);"
    );
}

// Insert default settings for Network ACL
$networkAclDefaults = [
    'network_blacklist_enabled' => '0',
    'network_whitelist_enabled' => '0',
    'network_security_mode'     => 'direct',
    'network_security_header'   => 'x-forwarded-for',
    'network_trusted_proxies'   => '',
];
foreach ($networkAclDefaults as $key => $value) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `{$pre}misc` (type, intitule, valeur, created_at)
        VALUES ('admin', '" . mysqli_real_escape_string($db_link, $key) . "',
                '" . mysqli_real_escape_string($db_link, $value) . "',
                UNIX_TIMESTAMP())"
    );
}
// --->

// <---
// ==========================================
// roles_values: Clean up duplicates and add UNIQUE constraint
// Ensures each (role_id, folder_id) pair exists only once
// ==========================================

// Step 1: Remove orphan entries where folder no longer exists
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "roles_values`
    WHERE folder_id NOT IN (
        SELECT id FROM `" . $pre . "nested_tree`
    );"
);

// Step 2: Remove orphan entries where role no longer exists
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "roles_values`
    WHERE role_id NOT IN (
        SELECT id FROM `" . $pre . "roles_title`
    );"
);

// Step 3: Remove duplicate entries, keep the one with highest increment_id
mysqli_query(
    $db_link,
    "DELETE rv1 FROM `" . $pre . "roles_values` rv1
    INNER JOIN `" . $pre . "roles_values` rv2
    ON rv1.role_id = rv2.role_id
        AND rv1.folder_id = rv2.folder_id
        AND rv1.increment_id < rv2.increment_id;"
);

// Step 4: Remove entries with empty type (no access = no row needed)
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "roles_values` WHERE type = '';"
);

// Step 5: Add UNIQUE constraint to prevent future duplicates
// Check if it already exists before adding
$result = mysqli_query(
    $db_link,
    "SHOW INDEX FROM `" . $pre . "roles_values` WHERE Key_name = 'idx_role_folder_unique';"
);
if ($result !== false && mysqli_num_rows($result) === 0) {
    mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "roles_values`
        ADD UNIQUE INDEX `idx_role_folder_unique` (`role_id`, `folder_id`);"
    );
}
// --->

// <---
// PERFORM STRUCTURAL DB CHANGES

modifyColumn($pre . 'users', 'agses-usercardid', "agses_usercardid", "VARCHAR(50) NOT NULL DEFAULT '0'");

// --->


// <---
// ==========================================

// Indexes for performance
$res = checkIndexExist(
    $pre . 'cache_tree',
    'idx_user_id',
    "ADD INDEX idx_user_id (user_id)"
);

// Add invalidated_at column for per-user targeted cache invalidation
addColumnIfNotExist(
    $pre . 'cache_tree',
    'invalidated_at',
    "INT UNSIGNED DEFAULT 0"
);

// Add show_subfolders column to users table if missing.
// Was introduced in upgrade_run_3.1.php but absent from the fresh-install schema,
// so instances installed at 3.1.x without going through that upgrade path are affected.
addColumnIfNotExist(
    $pre . 'users',
    'show_subfolders',
    "TINYINT(1) NOT NULL DEFAULT 0"
);
// --->


// misc deduplication and UNIQUE constraint are handled in upgrade_operations.php
// operation: deduplicate_misc (batched, loop-safe)

//---<END 3.1.7

// ==========================================
// Redis session storage settings (optional, disabled by default)
// INSERT IGNORE ensures idempotency on repeated upgrade runs
// ==========================================
$redisDefaults = [
    'redis_session_enabled' => '0',
    'redis_host'            => '127.0.0.1',
    'redis_port'            => '6379',
    'redis_prefix'          => 'teampass_sess_',
];
foreach ($redisDefaults as $key => $value) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `{$pre}misc` (type, intitule, valeur, created_at)
        VALUES ('admin', '" . mysqli_real_escape_string($db_link, $key) . "',
                '" . mysqli_real_escape_string($db_link, $value) . "',
                UNIX_TIMESTAMP())"
    );
}
// --->

// <---
// ==========================================
// Footer online users panel setting (disabled by default)
// ==========================================
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `{$pre}misc` (type, intitule, valeur, created_at)
    VALUES ('admin', 'show_online_users_list', '0', UNIX_TIMESTAMP())"
);
// --->

// <---
// ==========================================
// Health logs settings (auto-detect by default, no manual paths set)
// ==========================================
$healthLogsDefaults = [
    'health_logs_mode'          => 'auto',
    'health_teampass_log_path'  => '',
    'health_php_fpm_log_path'   => '',
];
foreach ($healthLogsDefaults as $key => $value) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `{$pre}misc` (type, intitule, valeur, created_at)
        VALUES ('admin', '" . mysqli_real_escape_string($db_link, $key) . "',
                '" . mysqli_real_escape_string($db_link, $value) . "',
                UNIX_TIMESTAMP())"
    );
}
// --->

// <---
// ==========================================
// Backup script passkey: repair empty values and archive legacy restore candidates
// ==========================================
if (function_exists('tpArchiveCurrentBackupScriptPasskeyState')) {
    tpArchiveCurrentBackupScriptPasskeyState($SETTINGS);
}
if (function_exists('tpResolveBackupScriptPasskey')) {
    tpResolveBackupScriptPasskey($SETTINGS, true);
}
// --->

// <---
// ==========================================
// Anti-bruteforce configurable settings defaults
// ==========================================
$bruteforceDefaults = [
    'nb_bad_authentication_by_ip' => '30',
    'bruteforce_lock_duration'    => '10',
];
foreach ($bruteforceDefaults as $key => $value) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `{$pre}misc` (type, intitule, valeur, created_at)
        VALUES ('admin', '" . mysqli_real_escape_string($db_link, $key) . "',
                '" . mysqli_real_escape_string($db_link, $value) . "',
                UNIX_TIMESTAMP())"
    );
}
// --->



// <---
// ==========================================
// Corrupted items persistence: table and related settings
// ==========================================
mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `{$pre}items_corruption` (
            `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
            `item_id` INT(12) NOT NULL,
            `reason_code` VARCHAR(50) NOT NULL,
            `severity` VARCHAR(20) NOT NULL,
            `status` VARCHAR(50) NOT NULL,
            `action_recommendation` VARCHAR(50) NOT NULL,
            `user_notice_mode` VARCHAR(20) NOT NULL DEFAULT 'none',
            `is_personal` TINYINT(1) NOT NULL DEFAULT 0,
            `len_stored` INT(12) NOT NULL DEFAULT 0,
            `len_actual` INT(12) NOT NULL DEFAULT 0,
            `exception_message` TEXT NULL,
            `first_detected_at` INT(12) NOT NULL,
            `last_detected_at` INT(12) NOT NULL,
            `last_scan_at` INT(12) NOT NULL,
            `confirmed_at` INT(12) NULL DEFAULT NULL,
            `resolved_at` INT(12) NULL DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `updated_at` INT(12) NOT NULL,
            PRIMARY KEY (`increment_id`),
            UNIQUE KEY `uk_item_id` (`item_id`),
            KEY `idx_reason_status` (`reason_code`, `status`),
            KEY `idx_is_active` (`is_active`),
            KEY `idx_last_detected_at` (`last_detected_at`),
            KEY `idx_is_personal` (`is_personal`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

$corruptedItemsDefaults = [
    'show_corrupted_items_in_list' => '0',
];
foreach ($corruptedItemsDefaults as $key => $value) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `{$pre}misc` (type, intitule, valeur, created_at)
        VALUES ('admin', '" . mysqli_real_escape_string($db_link, $key) . "',
                '" . mysqli_real_escape_string($db_link, $value) . "',
                UNIX_TIMESTAMP())"
    );
}
// --->

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS
