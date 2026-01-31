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

if ($res === false) {
    $error[] = "Failed to add phpseclibv3_migration_task_id to users table - MySQL Error: " . mysqli_error($db_link);
}


// --->
// Add new settings
$settings = [
    ['phpseclibv3_native', '0'],
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


//---<END 3.1.6



// ============================================
// STEP 7: Cleanup legacy deleted objects not compatible with Recycle Bin restore
// ============================================
// Goal: avoid HTTP 500 during restore by purging legacy deleted folders/items missing required markers.
// - Legacy deleted folders with invalid payload in misc.type='folder_deleted' (not JSON and not legacy comma format) are removed.
// - Legacy deleted items belonging to deleted folders, but missing a valid deleted_at marker (or belonging to an invalid deleted folder) are permanently removed.
//   Associated orphan records in related tables are then cleaned up.

// 7.1 Permanently delete legacy deleted items in deleted folders that are not safely restorable
$sql = "DELETE i"
    . " FROM `" . $pre . "items` i"
    . " INNER JOIN `" . $pre . "misc` m ON m.type = 'folder_deleted' AND m.intitule = i.id_tree"
    . " WHERE i.inactif = 1"
    . "   AND ("
    . "        (i.deleted_at IS NULL OR i.deleted_at = '' OR i.deleted_at = '0' OR i.deleted_at NOT REGEXP '^[0-9]+$')"
    . "     OR (m.valeur IS NULL OR m.valeur = '' OR (m.valeur NOT LIKE '{%' AND m.valeur NOT LIKE '%,%'))"
    . "   )";

if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to purge legacy deleted items in deleted folders - MySQL Error: ' . mysqli_error($db_link);
}

// 7.2 Remove invalid legacy deleted folders payloads (these cannot be restored safely)
$sql = "DELETE FROM `" . $pre . "misc`"
    . " WHERE type = 'folder_deleted'"
    . "   AND (valeur IS NULL OR valeur = '' OR (valeur NOT LIKE '{%' AND valeur NOT LIKE '%,%'))";

if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to purge invalid folder_deleted entries - MySQL Error: ' . mysqli_error($db_link);
}

// 7.3 Cleanup orphans created by the purge (safe with soft-delete: only deletes rows referencing missing objects)
// Sharekeys for items
$sql = "DELETE s FROM `" . $pre . "sharekeys_items` s"
    . " LEFT JOIN `" . $pre . "items` i ON i.id = s.object_id"
    . " WHERE i.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan sharekeys_items - MySQL Error: ' . mysqli_error($db_link);
}

// Categories items
$sql = "DELETE c FROM `" . $pre . "categories_items` c"
    . " LEFT JOIN `" . $pre . "items` i ON i.id = c.item_id"
    . " WHERE i.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan categories_items - MySQL Error: ' . mysqli_error($db_link);
}

// Sharekeys for fields (object_id references categories_items.id)
$sql = "DELETE s FROM `" . $pre . "sharekeys_fields` s"
    . " LEFT JOIN `" . $pre . "categories_items` c ON c.id = s.object_id"
    . " WHERE c.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan sharekeys_fields - MySQL Error: ' . mysqli_error($db_link);
}

// OTP
$sql = "DELETE o FROM `" . $pre . "items_otp` o"
    . " LEFT JOIN `" . $pre . "items` i ON i.id = o.item_id"
    . " WHERE i.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan items_otp - MySQL Error: ' . mysqli_error($db_link);
}

// Change history
$sql = "DELETE ch FROM `" . $pre . "items_change` ch"
    . " LEFT JOIN `" . $pre . "items` i ON i.id = ch.item_id"
    . " WHERE i.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan items_change - MySQL Error: ' . mysqli_error($db_link);
}

// Editions
$sql = "DELETE e FROM `" . $pre . "items_edition` e"
    . " LEFT JOIN `" . $pre . "items` i ON i.id = e.item_id"
    . " WHERE i.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan items_edition - MySQL Error: ' . mysqli_error($db_link);
}

// Knowledge base links
$sql = "DELETE k FROM `" . $pre . "kb_items` k"
    . " LEFT JOIN `" . $pre . "items` i ON i.id = k.item_id"
    . " WHERE i.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan kb_items - MySQL Error: ' . mysqli_error($db_link);
}

// Items logs
$sql = "DELETE l FROM `" . $pre . "log_items` l"
    . " LEFT JOIN `" . $pre . "items` i ON i.id = l.id_item"
    . " WHERE i.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan log_items - MySQL Error: ' . mysqli_error($db_link);
}

// Files and sharekeys_files (object_id references files.id).
$filesItemColumn = null;
$resCols = mysqli_query($db_link, "SHOW COLUMNS FROM `" . $pre . "files`");
if ($resCols !== false) {
    while ($col = mysqli_fetch_assoc($resCols)) {
        if ($col['Field'] === 'id_item') {
            $filesItemColumn = 'id_item';
            break;
        }
        if ($col['Field'] === 'item_id') {
            $filesItemColumn = 'item_id';
        }
    }
}

if ($filesItemColumn !== null) {
    $sql = "DELETE f FROM `" . $pre . "files` f"
        . " LEFT JOIN `" . $pre . "items` i ON i.id = f." . $filesItemColumn
        . " WHERE i.id IS NULL";
    if (mysqli_query($db_link, $sql) === false) {
        $error[] = 'Failed to cleanup orphan files - MySQL Error: ' . mysqli_error($db_link);
    }
}

$sql = "DELETE s FROM `" . $pre . "sharekeys_files` s"
    . " LEFT JOIN `" . $pre . "files` f ON f.id = s.object_id"
    . " WHERE f.id IS NULL";
if (mysqli_query($db_link, $sql) === false) {
    $error[] = 'Failed to cleanup orphan sharekeys_files - MySQL Error: ' . mysqli_error($db_link);
}
// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":"'.(count($error) > 0 ? json_encode($error) : '').'"}]';


//---< FUNCTIONS

