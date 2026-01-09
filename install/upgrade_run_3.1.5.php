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
 * @file      upgrade_run_3.1.5.php
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

//--->BEGIN 3.1.5

// Do init checks
$columnNeedsPasswordMigrationExists = DB::queryFirstRow(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = '" . $pre . "usersusers'
    AND COLUMN_NAME = 'needs_password_migration'"
);

// --->
// Clean duplicate entries in items_otp table - keep only the most recent one
$duplicates = DB::query(
    'SELECT item_id, COUNT(*) as count
    FROM ' . prefixTable('items_otp') . '
    GROUP BY item_id
    HAVING count > 1'
);
if (count($duplicates) > 0) {
    foreach ($duplicates as $duplicate) {
        // Get the most recent entry (highest timestamp) for this item_id
        $keepEntry = DB::queryFirstRow(
            'SELECT increment_id
            FROM ' . prefixTable('items_otp') . '
            WHERE item_id = %i
            ORDER BY timestamp DESC
            LIMIT 1',
            $duplicate['item_id']
        );
        
        if ($keepEntry !== null) {
            // Delete all other entries for this item_id
            DB::query(
                'DELETE FROM ' . prefixTable('items_otp') . '
                WHERE item_id = %i
                AND increment_id != %i',
                $duplicate['item_id'],
                $keepEntry['increment_id']
            );
        }
    }
}
// ---<


// Add new columns to tables
$columns_to_add = [
    [
        'table' => 'users',
        'column' => 'user_derivation_seed',
        'query' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `user_derivation_seed` VARCHAR(64) NULL DEFAULT NULL AFTER `private_key`"
    ],
    [
        'table' => 'users',
        'column' => 'private_key_backup',
        'query' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `private_key_backup` TEXT NULL DEFAULT NULL AFTER `user_derivation_seed`"
    ],
    [
        'table' => 'users',
        'column' => 'key_integrity_hash',
        'query' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `key_integrity_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `private_key_backup`"
    ],
    [
        'table' => 'users',
        'column' => 'personal_items_migrated',
        'query' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `personal_items_migrated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Personal items migrated to sharekeys system (0=not migrated, 1=migrated)' AFTER `is_ready_for_usage`"
    ],
    [
        'table' => 'users',
        'column' => 'needs_password_migration',
        'query' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `needs_password_migration` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indicates if user password needs migration to new hashing system (0=no, 1=yes)' AFTER `personal_items_migrated`"
    ],
    [
        'table' => 'api',
        'column' => 'session_key',
        'query' => "ALTER TABLE `" . $pre . "api` ADD COLUMN `session_key` VARCHAR(64) NULL"
    ],
    [
        'table' => 'items',
        'column' => 'favicon_url',
        'query' => "ALTER TABLE `" . $pre . "items` ADD COLUMN `favicon_url` VARCHAR(500) NULL DEFAULT NULL AFTER `fa_icon`"
    ]
];

foreach ($columns_to_add as $column_info) {
    // Check if column exists
    $result = mysqli_query($db_link, "SHOW COLUMNS FROM `" . $pre . $column_info['table'] . "` LIKE '" . $column_info['column'] . "'");
    if (mysqli_num_rows($result) === 0) {
        mysqli_query($db_link, $column_info['query']);
    }
}

// --->
// Drop previous index
$tables = ['sharekeys_items', 'sharekeys_fields', 'sharekeys_files', 'sharekeys_suggestions', 'sharekeys_logs'];
foreach ($tables as $table) {
    // Check if index exists - fixed: use quotes for string values, not backticks
    $check_index = mysqli_query($db_link, "
        SELECT INDEX_NAME 
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '{$pre}{$table}'
        AND INDEX_NAME = 'idx_object_user'
        LIMIT 1
    ");

    // Verify query executed successfully before checking results
    if ($check_index !== false && mysqli_num_rows($check_index) > 0) {
        mysqli_query($db_link, "DROP INDEX `idx_object_user` ON `{$pre}{$table}`");
    }
    
    // Free result if query was successful
    if ($check_index !== false) {
        mysqli_free_result($check_index);
    }
}
// ---<


// --->
// Add indexes on tables
$indexes_to_add = [    
    [
        'table' => 'users',
        'index' => 'idx_last_pw_change',
        'columns' => 'last_pw_change',
        'unique' => false
    ],
    [
        'table' => 'users',
        'index' => 'idx_personal_items_migrated',
        'columns' => 'personal_items_migrated',
        'unique' => false
    ],
    [
        'table' => 'sharekeys_items',
        'index' => 'idx_unique_object_user',
        'columns' => 'object_id, user_id',
        'unique' => true
    ],
    [
        'table' => 'sharekeys_fields',
        'index' => 'idx_unique_object_user',
        'columns' => 'object_id, user_id',
        'unique' => true
    ],
    [
        'table' => 'sharekeys_files',
        'index' => 'idx_unique_object_user',
        'columns' => 'object_id, user_id',
        'unique' => true
    ],
    [
        'table' => 'sharekeys_suggestions',
        'index' => 'idx_unique_object_user',
        'columns' => 'object_id, user_id',
        'unique' => true
    ],
    [
        'table' => 'sharekeys_logs',
        'index' => 'idx_unique_object_user',
        'columns' => 'object_id, user_id',
        'unique' => true
    ],
    [
        'table' => 'items_otp',
        'index' => 'unique_item_id',
        'columns' => 'item_id',
        'unique' => true
    ],
    [
        'table' => 'log_system',
        'index' => 'idx_log_api_user_connection ',
        'columns' => 'qui, type, label, date DESC',
        'unique' => false
    ]
];
foreach ($indexes_to_add as $index_config) {
    $table = $pre . $index_config['table'];
    $index_name = $index_config['index'];
    $columns = $index_config['columns'];
    $is_unique = isset($index_config['unique']) && $index_config['unique'] === true;
    
    // Check if index exists
    $result = mysqli_query(
        $db_link, 
        "SHOW INDEX FROM `{$table}` WHERE Key_name = '" . mysqli_real_escape_string($db_link, $index_name) . "'"
    );
    
    if ($result && mysqli_num_rows($result) === 0) {
        // Create index (unique or standard)
        $index_type = $is_unique ? 'UNIQUE INDEX' : 'INDEX';
        $query = "CREATE {$index_type} `{$index_name}` ON `{$table}` ({$columns})";
                
        if (!mysqli_query($db_link, $query)) {
            $error[] = "Failed to create index {$index_name} on {$table}: " . mysqli_error($db_link);
        }
    }
}
// ---<

// --->
// Add new settings
$settings = [
    ['transparent_key_recovery_enabled', '1'],
    ['transparent_key_recovery_pbkdf2_iterations', '100000'],
    ['transparent_key_recovery_integrity_check', '1'],
    ['transparent_key_recovery_max_age_days', '730'],
    ['browser_extension_key', generateSecureToken(64)],
];

foreach ($settings as $setting) {
    $tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = '" . $setting[0] . "'"));
    if (intval($tmp) === 0) {
        mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', '" . $setting[0] . "', '" . $setting[1] . "')"
        );
    }
}
// ---<

// --> 
// REWORK USERS TABLE
$needsMigration = columnExists($db_link, $pre . 'users', 'groupes_visibles') ||
                  columnExists($db_link, $pre . 'users', 'groupes_interdits') ||
                  columnExists($db_link, $pre . 'users', 'fonction_id') ||
                  columnExists($db_link, $pre . 'users', 'roles_from_ad_groups') ||
                  columnExists($db_link, $pre . 'users', 'favourites') ||
                  columnExists($db_link, $pre . 'users', 'latest_items');

if ($needsMigration) {
    // Table users_groups (remplace groupes_visibles)
    mysqli_query(
        $db_link,
        'CREATE TABLE IF NOT EXISTS `' . $pre . 'users_groups` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `user_id` int(12) NOT NULL,
            `group_id` int(12) NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`increment_id`),
            UNIQUE KEY `user_group_unique` (`user_id`, `group_id`),
            KEY `user_idx` (`user_id`),
            KEY `group_idx` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    // Table users_groups_forbidden (remplace groupes_interdits)
    mysqli_query(
        $db_link,
        'CREATE TABLE IF NOT EXISTS `' . $pre . 'users_groups_forbidden` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `user_id` int(12) NOT NULL,
            `group_id` int(12) NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`increment_id`),
            UNIQUE KEY `user_group_unique` (`user_id`, `group_id`),
            KEY `user_idx` (`user_id`),
            KEY `group_idx` (`group_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    // Table users_roles (remplace fonction_id ET roles_from_ad_groups)
    mysqli_query(
        $db_link,
        'CREATE TABLE IF NOT EXISTS `' . $pre . 'users_roles` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `user_id` int(12) NOT NULL,
            `role_id` int(12) NOT NULL,
            `source` enum(\'manual\',\'ad\',\'ldap\',\'oauth2\') NOT NULL DEFAULT \'manual\',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`increment_id`),
            UNIQUE KEY `user_role_source_unique` (`user_id`, `role_id`, `source`),
            KEY `user_idx` (`user_id`),
            KEY `role_idx` (`role_id`),
            KEY `source_idx` (`source`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    // Table users_favorites (remplace favourites)
    mysqli_query(
        $db_link,
        'CREATE TABLE IF NOT EXISTS `' . $pre . 'users_favorites` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `user_id` int(12) NOT NULL,
            `item_id` int(12) NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`increment_id`),
            UNIQUE KEY `user_item_unique` (`user_id`, `item_id`),
            KEY `user_idx` (`user_id`),
            KEY `item_idx` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    // Table users_latest_items (remplace latest_items)
    mysqli_query(
        $db_link,
        'CREATE TABLE IF NOT EXISTS `' . $pre . 'users_latest_items` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `user_id` int(12) NOT NULL,
            `item_id` int(12) NOT NULL,
            `accessed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`increment_id`),
            UNIQUE KEY `user_item_unique` (`user_id`, `item_id`),
            KEY `user_idx` (`user_id`),
            KEY `item_idx` (`item_id`),
            KEY `accessed_idx` (`accessed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );

    // Migrate data from Users to Tables
    // Get all users
    $result = mysqli_query(
        $db_link,
        'SELECT id, groupes_visibles, groupes_interdits, fonction_id, roles_from_ad_groups, favourites, latest_items 
        FROM `' . $pre . 'users`'
    );

    if ($result) {
        while ($user = mysqli_fetch_assoc($result)) {
            $user_id = (int) $user['id'];
            
            // ----------------------------------------
            // 1. Migrate groupes_visibles → users_groups
            // ----------------------------------------
            if (!empty($user['groupes_visibles'])) {
                $groups = explode(';', $user['groupes_visibles']);
                foreach ($groups as $group_id) {
                    $group_id = trim($group_id);
                    if (!empty($group_id) && is_numeric($group_id)) {
                        mysqli_query(
                            $db_link,
                            'INSERT IGNORE INTO `' . $pre . 'users_groups` 
                            (user_id, group_id) 
                            VALUES (' . $user_id . ', ' . (int) $group_id . ')'
                        );
                    }
                }
            }
            
            // ----------------------------------------
            // 2. Migrate groupes_interdits → users_groups_forbidden
            // ----------------------------------------
            if (!empty($user['groupes_interdits'])) {
                $forbidden_groups = explode(';', $user['groupes_interdits']);
                foreach ($forbidden_groups as $group_id) {
                    $group_id = trim($group_id);
                    if (!empty($group_id) && is_numeric($group_id)) {
                        mysqli_query(
                            $db_link,
                            'INSERT IGNORE INTO `' . $pre . 'users_groups_forbidden` 
                            (user_id, group_id) 
                            VALUES (' . $user_id . ', ' . (int) $group_id . ')'
                        );
                    }
                }
            }
            
            // ----------------------------------------
            // 3. Migrate fonction_id → users_roles (source='manual')
            // ----------------------------------------
            if (!empty($user['fonction_id'])) {
                $roles = explode(';', $user['fonction_id']);
                foreach ($roles as $role_id) {
                    $role_id = trim($role_id);
                    if (!empty($role_id) && is_numeric($role_id)) {
                        mysqli_query(
                            $db_link,
                            'INSERT IGNORE INTO `' . $pre . 'users_roles` 
                            (user_id, role_id, source) 
                            VALUES (' . $user_id . ', ' . (int) $role_id . ', \'manual\')'
                        );
                    }
                }
            }
            
            // ----------------------------------------
            // 4. Migrate roles_from_ad_groups → users_roles (source='ad')
            // ----------------------------------------
            if (!empty($user['roles_from_ad_groups'])) {
                $ad_roles = explode(';', $user['roles_from_ad_groups']);
                foreach ($ad_roles as $role_id) {
                    $role_id = trim($role_id);
                    if (!empty($role_id) && is_numeric($role_id)) {
                        mysqli_query(
                            $db_link,
                            'INSERT IGNORE INTO `' . $pre . 'users_roles` 
                            (user_id, role_id, source) 
                            VALUES (' . $user_id . ', ' . (int) $role_id . ', \'ad\')'
                        );
                    }
                }
            }
            
            // ----------------------------------------
            // 5. Migrate favourites → users_favorites
            // ----------------------------------------
            if (!empty($user['favourites'])) {
                $favorites = explode(';', $user['favourites']);
                foreach ($favorites as $item_id) {
                    $item_id = trim($item_id);
                    if (!empty($item_id) && is_numeric($item_id)) {
                        mysqli_query(
                            $db_link,
                            'INSERT IGNORE INTO `' . $pre . 'users_favorites` 
                            (user_id, item_id) 
                            VALUES (' . $user_id . ', ' . (int) $item_id . ')'
                        );
                    }
                }
            }
            
            // ----------------------------------------
            // 6. Migrate latest_items → users_latest_items
            // ----------------------------------------
            if (!empty($user['latest_items'])) {
                $latest = explode(';', $user['latest_items']);
                // On inverse l'ordre pour que le plus récent ait le timestamp le plus récent
                $latest = array_reverse($latest);
                $counter = 0;
                foreach ($latest as $item_id) {
                    $item_id = trim($item_id);
                    if (!empty($item_id) && is_numeric($item_id)) {
                        // On ajoute un décalage de secondes pour préserver l'ordre
                        mysqli_query(
                            $db_link,
                            'INSERT IGNORE INTO `' . $pre . 'users_latest_items` 
                            (user_id, item_id, accessed_at) 
                            VALUES (' . $user_id . ', ' . (int) $item_id . ', 
                            DATE_SUB(NOW(), INTERVAL ' . $counter . ' SECOND))'
                        );
                        $counter++;
                    }
                }
            }
        }
        mysqli_free_result($result);
    }


    // Delete columns
    deleteColumnIfExists($pre . 'users', 'groupes_visibles');
    deleteColumnIfExists($pre . 'users', 'fonction_id');
    deleteColumnIfExists($pre . 'users', 'groupes_interdits');
    deleteColumnIfExists($pre . 'users', 'favourites');
    deleteColumnIfExists($pre . 'users', 'latest_items');
    deleteColumnIfExists($pre . 'users', 'roles_from_ad_groups');

    // Alter table users
    modifyColumn($pre . 'users', 'avatar', "avatar", "VARCHAR(250);");
    modifyColumn($pre . 'users', 'avatar_thumb', "avatar_thumb", "VARCHAR(250);");
    modifyColumn($pre . 'users', 'login', "login", "VARCHAR(250);");
    modifyColumn($pre . 'users', 'pw', "pw", "VARCHAR(250);");
    modifyColumn($pre . 'users', 'email', "email", "VARCHAR(250);");
    modifyColumn($pre . 'users', 'psk', "psk", "VARCHAR(250);");
    modifyColumn($pre . 'users', 'user_ip', "user_ip", "VARCHAR(50);");
    modifyColumn($pre . 'users', 'auth_type', "auth_type", "VARCHAR(50);");

    // Migrate DB to expected encoding
    migrateDBtoUtf8();

    // Migrate Tables to expected encoding
    migrateToUtf8mb4();
}
// --< END MIGRATION USERS

// Mark all local users as needing migration
// Users authenticated via LDAP or OAuth2 don't need migration
if (empty($columnNeedsPasswordMigrationExists)) {
    mysqli_query(
        $db_link,
        "UPDATE `" . $pre . "users` 
        SET `needs_password_migration` = 1
        WHERE (`auth_type` = 'local' OR `auth_type` IS NULL OR `auth_type` = '')"
    );
}

// Update treeloadstrategy default value
mysqli_query(
    $db_link,
    "UPDATE `" . $pre . "users` SET `treeloadstrategy` = 'full' WHERE `treeloadstrategy` = 'sequential';"
);

// -->


//---<END 3.1.5


// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":"'.(count($error) > 0 ? json_encode($error) : '').'"}]';


//---< FUNCTIONS