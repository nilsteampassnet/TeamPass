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
 * @file      upgrade_run_3.1.6.php
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

//--->BEGIN 3.1.6

$res = addColumnIfNotExist(
    $pre . 'users',
    'encryption_version',
    "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=phpseclib v1 (SHA-1), 3=phpseclib v3 (SHA-256)'"
);

if ($res === false) {
    $error[] = "Failed to add encryption_version to users table - MySQL Error: " . mysqli_error($db_link);
}

// Initialize all existing users to version 1 (phpseclib v1)
mysqli_query(
    $db_link,
    "UPDATE `" . $pre . "users`
     SET encryption_version = 1
     WHERE encryption_version = 0 OR encryption_version IS NULL"
);

// ============================================
// STEP 2: Add encryption_version to sharekeys tables
// ============================================
$sharekeys_tables = [
    'sharekeys_items',
    'sharekeys_logs',
    'sharekeys_fields',
    'sharekeys_suggestions',
    'sharekeys_files'
];

foreach ($sharekeys_tables as $table) {

    $res = addColumnIfNotExist(
        $pre . $table,
        'encryption_version',
        "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=phpseclib v1 (SHA-1), 3=phpseclib v3 (SHA-256)'"
    );

    if ($res === false) {
        $error[] = "Failed to add encryption_version to " . $table . " - MySQL Error: " . mysqli_error($db_link);
    }

    // Initialize existing sharekeys to version 1
    mysqli_query(
        $db_link,
        "UPDATE `" . $pre . $table . "`
         SET encryption_version = 1
         WHERE encryption_version = 0 OR encryption_version IS NULL"
    );
}


// ============================================
// STEP 3: Add index for performance
// ============================================

foreach ($sharekeys_tables as $table) {
    $res = checkIndexExist(
        $pre . $table,
        'encryption_version',
        "ADD KEY `encryption_version` (`encryption_version`)"
    );
}


// ============================================
// STEP 4: Create migration statistics table
// ============================================

mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'encryption_migration_stats` (
        `id` int(12) NOT NULL AUTO_INCREMENT,
        `table_name` varchar(100) NOT NULL,
        `total_records` int(12) NOT NULL DEFAULT 0,
        `v1_records` int(12) NOT NULL DEFAULT 0,
        `v3_records` int(12) NOT NULL DEFAULT 0,
        `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `table_name` (`table_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT="Tracks phpseclib v1 to v3 migration progress";'
);

if (mysqli_error($db_link)) {
        $error[] = "Failed to create migration statistics table - MySQL Error: " . mysqli_error($db_link);
} else {
    // Initialize statistics

    // Users statistics
    $result = mysqli_query(
        $db_link,
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN encryption_version = 1 THEN 1 ELSE 0 END) as v1,
            SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3
         FROM `" . $pre . "users`
         WHERE private_key IS NOT NULL AND private_key != ''"
    );
    $stats = mysqli_fetch_assoc($result);

    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "encryption_migration_stats`
         (table_name, total_records, v1_records, v3_records)
         VALUES ('users', " . $stats['total'] . ", " . $stats['v1'] . ", " . $stats['v3'] . ")
         ON DUPLICATE KEY UPDATE
            total_records = " . $stats['total'] . ",
            v1_records = " . $stats['v1'] . ",
            v3_records = " . $stats['v3']
    );

    // Sharekeys statistics
    foreach ($sharekeys_tables as $table) {
        $result = mysqli_query(
            $db_link,
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN encryption_version = 1 THEN 1 ELSE 0 END) as v1,
                SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3
             FROM `" . $pre . $table . "`"
        );
        $stats = mysqli_fetch_assoc($result);

        mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "encryption_migration_stats`
             (table_name, total_records, v1_records, v3_records)
             VALUES ('" . $table . "', " . $stats['total'] . ", " . $stats['v1'] . ", " . $stats['v3'] . ")
             ON DUPLICATE KEY UPDATE
                total_records = " . $stats['total'] . ",
                v1_records = " . $stats['v1'] . ",
                v3_records = " . $stats['v3']
        );
    }
}


// ============================================
// STEP 5: Add migration setting
// ============================================

$result = mysqli_query(
    $db_link,
    "SELECT * FROM `" . $pre . "misc`
     WHERE type = 'admin' AND intitule = 'phpseclib_migration_mode'"
);

if (mysqli_num_rows($result) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (type, intitule, valeur)
         VALUES ('admin', 'phpseclib_migration_mode', 'progressive')"
    );
}

// ============================================
// STEP 6: Add forced migration tracking columns
// ============================================

$res = addColumnIfNotExist(
    $pre . 'users',
    'phpseclibv3_migration_completed',
    "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Forced phpseclib v3 migration status (0=not done, 1=completed)'"
);

if ($res === false) {
    $error[] = "Failed to add phpseclibv3_migration_completed to users table - MySQL Error: " . mysqli_error($db_link);
}

$res = addColumnIfNotExist(
    $pre . 'users',
    'phpseclibv3_migration_task_id',
    "INT(12) NULL DEFAULT NULL COMMENT 'ID of the active phpseclib v3 migration background task'"
);


// <----
// ============================================
// STEP: Add inactive users management columns (3.1.6.5+ feature)
// ============================================

$res = addColumnIfNotExist(
    $pre . 'users',
    'inactivity_warned_at',
    "VARCHAR(30) NULL DEFAULT NULL COMMENT 'Inactive users mgmt: warning timestamp'"
);
if ($res === false) {
    $error[] = "Failed to add inactivity_warned_at to users table - MySQL Error: " . mysqli_error($db_link);
}

$res = addColumnIfNotExist(
    $pre . 'users',
    'inactivity_action_at',
    "VARCHAR(30) NULL DEFAULT NULL COMMENT 'Inactive users mgmt: action timestamp'"
);
if ($res === false) {
    $error[] = "Failed to add inactivity_action_at to users table - MySQL Error: " . mysqli_error($db_link);
}

$res = addColumnIfNotExist(
    $pre . 'users',
    'inactivity_action',
    "VARCHAR(20) NULL DEFAULT NULL COMMENT 'Inactive users mgmt: action (disable|soft_delete|hard_delete)'"
);
if ($res === false) {
    $error[] = "Failed to add inactivity_action to users table - MySQL Error: " . mysqli_error($db_link);
}

$res = addColumnIfNotExist(
    $pre . 'users',
    'inactivity_no_email',
    "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Inactive users mgmt: 1 if no email available to warn user'"
);
if ($res === false) {
    $error[] = "Failed to add inactivity_no_email to users table - MySQL Error: " . mysqli_error($db_link);
}

// Indexes for performance
$res = checkIndexExist(
    $pre . 'users',
    'idx_users_inactivity_action_at',
    "ADD KEY `idx_users_inactivity_action_at` (`inactivity_action_at`)"
);

$res = checkIndexExist(
    $pre . 'users',
    'idx_users_inactivity_warned_at',
    "ADD KEY `idx_users_inactivity_warned_at` (`inactivity_warned_at`)"
);

// ============================================
// STEP: Initialize default settings (type=settings)
// ============================================
$iumSettings = [
    ['inactive_users_mgmt_enabled', '0'],
    ['inactive_users_mgmt_inactivity_days', '90'],
    ['inactive_users_mgmt_grace_days', '7'],
    ['inactive_users_mgmt_action', 'disable'],
    ['inactive_users_mgmt_time', '02:00'],
    // optional runtime defaults (safe)
    ['inactive_users_mgmt_next_run_at', '0'],
    ['inactive_users_mgmt_last_run_at', '0'],
    ['inactive_users_mgmt_last_status', ''],
    ['inactive_users_mgmt_last_message', ''],
    ['inactive_users_mgmt_last_details', ''],
];

foreach ($iumSettings as $setting) {
    $tmp = mysqli_num_rows(mysqli_query(
        $db_link,
        "SELECT * FROM `" . $pre . "misc`
         WHERE type = 'settings' AND intitule = '" . $setting[0] . "'"
    ));
    if ((int)$tmp === 0) {
        mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`)
             VALUES ('settings', '" . $setting[0] . "', '" . $setting[1] . "')"
        );
    }
}

if ($res === false) {
    $error[] = "Failed to add phpseclibv3_migration_task_id to users table - MySQL Error: " . mysqli_error($db_link);
}
// --->

// ============================================
// Add new settings
// ============================================
$settings = [
    ['phpseclibv3_native', '0'],
    ['websocket_enabled', '0'],
    ['websocket_port', '8080'],
    ['websocket_host', '127.0.0.1'],
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


// <---
// ============================================
// STEP: Initialize background tasks trigger file
// ============================================
// Create the trigger file with proper permissions for the background task notification system
$triggerFile = __DIR__ . '/../files/teampass_background_tasks.trigger';
if (!file_exists($triggerFile)) {
    // Create the file with current timestamp
    if (@file_put_contents($triggerFile, (string) time()) !== false) {
        // Set permissions to allow web server and cron to read/write
        @chmod($triggerFile, 0664);
    }
}

// ============================================
// STEP: Initialize background tasks lock file
// ============================================
// Create the trigger file with proper permissions for the background task notification system
$lockFile = __DIR__ . '/../files/teampass_background_tasks.lock';
if (!file_exists($lockFile)) {
    // Create the file with current timestamp
    if (@file_put_contents($lockFile, (string) time()) !== false) {
        // Set permissions to allow web server and cron to read/write
        @chmod($lockFile, 0664);
    }
}


// Ensure TP_USER has his private_key stored into user_private_keys table for migration to work properly
$result = mysqli_query(
    $db_link,
    "SELECT id FROM `" . $pre . "users`
     WHERE login = '" . TP_USER_ID . "'
     AND (private_key IS NULL OR private_key = '')"
);
if (mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);

    // Check if a private key already exists in user_private_keys for this user
    $keyResult = mysqli_query(
        $db_link,
        "SELECT id FROM `" . $pre . "user_private_keys`
         WHERE user_id = " . TP_USER_ID
    );

    if (mysqli_num_rows($keyResult) > 0) {
        // If a key already exists, we can use it to update the users table
        $keyData = mysqli_fetch_assoc($keyResult);
        $privateKey = $keyData['private_key'];

        mysqli_query(
            $db_link,
            "UPDATE `" . $pre . "users`
             SET private_key = '" . $privateKey . "'
             WHERE id = " . TP_USER_ID
        );
    }
}

// --->


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

// ============================================
// STEP: Add private_key_xss_migration tracking column
// ============================================
// Tracks whether a user's private key has been re-encrypted with the raw password
// (fixing the historical bug where xss_clean() was applied to the password before
// AES key derivation in generateUserKeys/encryptPrivateKey, causing a mismatch
// between the stored Defuse-encrypted password and the actual AES key material).
// NULL = not yet attempted, 1 = migrated (key encrypted with raw password)
$res = addColumnIfNotExist(
    $pre . 'users',
    'private_key_xss_migration',
    "TINYINT(1) NULL DEFAULT NULL COMMENT 'NULL=not attempted, 1=done: private key uses raw password (xss_clean removed from AES key derivation)'"
);
if ($res === false) {
    $error[] = "Failed to add private_key_xss_migration to users table - MySQL Error: " . mysqli_error($db_link);
}

// ============================================
// STEP: Fix ldap_groups_roles data integrity (#3956)
// Remove duplicate and corrupted entries caused by the INT→VARCHAR
// migration bug where all group IDs were stored as '0', then add a
// UNIQUE constraint to prevent future duplicates.
// ============================================

// Step A: delete duplicates - keep only the most recent entry per ldap_group_id
mysqli_query(
    $db_link,
    "DELETE t1 FROM `" . $pre . "ldap_groups_roles` t1
     INNER JOIN `" . $pre . "ldap_groups_roles` t2
       ON t1.ldap_group_id = t2.ldap_group_id
      AND t1.increment_id < t2.increment_id"
);
if (mysqli_error($db_link)) {
    $error[] = "Failed to remove duplicate ldap_groups_roles entries - MySQL Error: " . mysqli_error($db_link);
}

// Step B: delete entries inherited from the INT(12) column bug (group_id stored as '0' or empty)
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "ldap_groups_roles`
     WHERE ldap_group_id = '0' OR ldap_group_id = ''"
);
if (mysqli_error($db_link)) {
    $error[] = "Failed to remove corrupted ldap_groups_roles entries - MySQL Error: " . mysqli_error($db_link);
}

// Step C: add UNIQUE constraint if it does not already exist
$res = checkIndexExist(
    $pre . 'ldap_groups_roles',
    'UNIQUE_LDAP_GROUP_ID',
    "ADD UNIQUE KEY `UNIQUE_LDAP_GROUP_ID` (`ldap_group_id`(255))"
);
if ($res === false) {
    $error[] = "Failed to add unique key on ldap_groups_roles.ldap_group_id - MySQL Error: " . mysqli_error($db_link);
}


//---<END 3.1.6

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":"'.(count($error) > 0 ? json_encode($error) : '').'"}]';


//---< FUNCTIONS
