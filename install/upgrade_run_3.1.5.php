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
 * @copyright 2009-2025 Teampass.net
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

// Add new columns to users table
$columns_to_add = [
    'user_derivation_seed' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `user_derivation_seed` VARCHAR(64) NULL DEFAULT NULL AFTER `private_key`",
    'private_key_backup' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `private_key_backup` TEXT NULL DEFAULT NULL AFTER `user_derivation_seed`",
    'key_integrity_hash' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `key_integrity_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `private_key_backup`",
    'personal_items_migrated' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `personal_items_migrated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Personal items migrated to sharekeys system (0=not migrated, 1=migrated)' AFTER `is_ready_for_usage`"
];

foreach ($columns_to_add as $column => $query) {
    // Check if column exists
    $result = mysqli_query($db_link, "SHOW COLUMNS FROM `" . $pre . "users` LIKE '" . $column . "'");
    if (mysqli_num_rows($result) === 0) {
        mysqli_query($db_link, $query);
    }
}


// Add indexes on tables
$indexes_to_add = [    
    [
        'table' => 'users',
        'index' => 'idx_last_pw_change',
        'columns' => 'last_pw_change'
    ],
    [
        'table' => 'users',
        'index' => 'idx_personal_items_migrated',
        'columns' => 'personal_items_migrated'
    ]
];
foreach ($indexes_to_add as $index_config) {
    $table = $pre . $index_config['table'];
    $index_name = $index_config['index'];
    $columns = $index_config['columns'];
    
    // Check if index exists
    $result = mysqli_query(
        $db_link, 
        "SHOW INDEX FROM `{$table}` WHERE Key_name = '" . mysqli_real_escape_string($db_link, $index_name) . "'"
    );
    
    if ($result && mysqli_num_rows($result) === 0) {
        // Create index
        $query = "CREATE INDEX `{$index_name}` ON `{$table}` ({$columns})";
        
        if (!mysqli_query($db_link, $query)) {
            $error[] = "Failed to create index {$index_name} on {$table}: " . mysqli_error($db_link);
        }
    }
}


// Add new settings for transparent recovery
$settings = [
    ['transparent_key_recovery_enabled', '1'],
    ['transparent_key_recovery_pbkdf2_iterations', '100000'],
    ['transparent_key_recovery_integrity_check', '1'],
    ['transparent_key_recovery_max_age_days', '730']
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

//---<END 3.1.5


// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":"'.(count($error) > 0 ? json_encode($error) : '').'"}]';


//---< FUNCTIONS