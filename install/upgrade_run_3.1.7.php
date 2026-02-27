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

$error = [];

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
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "websocket_tokens` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,                                                           
        `user_id` INT UNSIGNED NOT NULL,                                                                        
        `token` VARCHAR(64) NOT NULL,                                                                           
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,                                                       
        `expires_at` TIMESTAMP NOT NULL,                                                                        
        `used` TINYINT(1) UNSIGNED DEFAULT 0,                                                                   
        UNIQUE INDEX `idx_token` (`token`),                                                                     
        INDEX `idx_user` (`user_id`),                                                                           
        INDEX `idx_expires` (`expires_at`)                                                                      
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
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

//---<END 3.1.7


// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":"'.(count($error) > 0 ? json_encode($error) : '').'"}]';


//---< FUNCTIONS


