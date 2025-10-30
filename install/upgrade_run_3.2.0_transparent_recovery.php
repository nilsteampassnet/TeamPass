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
 * @file      upgrade_run_3.2.0_transparent_recovery.php
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

//---------------------------------------------------------------------

//--->BEGIN 3.2.0 - Transparent Key Recovery

// Add new columns to users table
$columns_to_add = [
    'user_derivation_seed' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `user_derivation_seed` VARCHAR(64) NULL DEFAULT NULL AFTER `private_key`",
    'private_key_backup' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `private_key_backup` TEXT NULL DEFAULT NULL AFTER `user_derivation_seed`",
    'key_integrity_hash' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `key_integrity_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `private_key_backup`",
    'last_password_change' => "ALTER TABLE `" . $pre . "users` ADD COLUMN `last_password_change` INT(12) NULL DEFAULT NULL AFTER `key_integrity_hash`"
];

foreach ($columns_to_add as $column => $query) {
    // Check if column exists
    $result = mysqli_query($db_link, "SHOW COLUMNS FROM `" . $pre . "users` LIKE '" . $column . "'");
    if (mysqli_num_rows($result) === 0) {
        mysqli_query($db_link, $query);
    }
}

// Add index on last_password_change
$result = mysqli_query($db_link, "SHOW INDEX FROM `" . $pre . "users` WHERE Key_name = 'idx_last_password_change'");
if (mysqli_num_rows($result) === 0) {
    mysqli_query($db_link, "CREATE INDEX idx_last_password_change ON `" . $pre . "users`(last_password_change)");
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

// Generate user_derivation_seed for existing users (will be finalized on first login)
// Process in batches of 50 users to avoid timeout
$batch_size = 50;
$offset = 0;

while (true) {
    $result = mysqli_query(
        $db_link,
        "SELECT id FROM `" . $pre . "users`
         WHERE user_derivation_seed IS NULL
         AND disabled = 0
         AND private_key IS NOT NULL
         AND private_key != 'none'
         LIMIT " . $offset . ", " . $batch_size
    );

    if (mysqli_num_rows($result) === 0) {
        break;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        // Generate unique seed for each user
        $user_seed = bin2hex(openssl_random_pseudo_bytes(32));

        mysqli_query(
            $db_link,
            "UPDATE `" . $pre . "users`
             SET user_derivation_seed = '" . $user_seed . "',
                 last_password_change = " . time() . "
             WHERE id = " . intval($row['id'])
        );
    }

    $offset += $batch_size;
}

// Add log entry for migration completion
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "log_system`
     (`date`, `label`, `qui`, `action`)
     VALUES
     ('" . time() . "', 'system', 'system', 'Transparent key recovery migration completed')"
);

//---<END 3.2.0

echo '[{"finish":"1" , "next":"", "error":""}]';
