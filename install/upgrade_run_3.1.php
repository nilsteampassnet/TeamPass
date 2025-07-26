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
 * @file      upgrade_run_3.1.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\PasswordManager\PasswordManager;

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


//---------------------------------------------------------------------

//--->BEGIN 3.1.0

// 
// Add new setting 'enable_refresh_task_last_execution'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'enable_refresh_task_last_execution'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'enable_refresh_task_last_execution', '1')"
    );
}

//---<END 3.1.0

//--->BEGIN 3.1.1

// Add new table ITEMS_OTP
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'items_otp` (
    `increment_id` int(12) NOT NULL AUTO_INCREMENT,
    `item_id` int(12) NOT NULL,
    `secret` text NOT NULL,
    `timestamp` varchar(100) NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT 0,
    `phone_number` varchar(25) NOT NULL,
    PRIMARY KEY (`increment_id`),
    KEY `ITEM` (`item_id`)
    ) CHARSET=utf8;'
);

// Alter table items_otp (see #4006)
modifyColumn(
    $pre . 'items_otp',
    'increment_id',
    "increment_id",
    "INT(12) NOT NULL AUTO_INCREMENT;"
);

// Alter table TOKENS
modifyColumn(
    $pre . 'tokens',
    'end_timestamp',
    "end_timestamp",
    "VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;"
);


// Alter table ldap_groups_roles
modifyColumn(
    $pre . 'ldap_groups_roles',
    'ldap_group_id',
    "ldap_group_id",
    "VARCHAR(500) NOT NULL;"
);


// Add new setting 'ldap_group_objectclasses_attibute'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_group_objectclasses_attibute'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_group_objectclasses_attibute', 'top,groupofuniquenames')"
    );
}


// Alter table users to ensure a start at 1000000
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'users` AUTO_INCREMENT = 1000000;'
);

// Add new setting 'pwd_default_length'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'pwd_default_length'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'pwd_default_length', '14')"
    );
}

// Rename table 'processes' to 'background_tasks'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SHOW TABLES LIKE '" . $pre . "background_tasks'"));
if (intval($tmp) === 0) {
    $tmp = mysqli_num_rows(mysqli_query($db_link, "SHOW TABLES LIKE '" . $pre . "processes'"));
    if (intval($tmp) > 0) {
        mysqli_query(
            $db_link,
            'RENAME TABLE `' . $pre . 'processes` TO `' . $pre . 'background_tasks`;'
        );
    }
}

// Rename table 'processes_tasks' to 'background_subtasks'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SHOW TABLES LIKE '" . $pre . "background_subtasks'"));
if (intval($tmp) === 0) {
    $tmp = mysqli_num_rows(mysqli_query($db_link, "SHOW TABLES LIKE '" . $pre . "processes_tasks'"));
    if (intval($tmp) > 0) {
        mysqli_query(
            $db_link,
            'RENAME TABLE `' . $pre . 'processes_tasks` TO `' . $pre . 'background_subtasks`;'
        );
    }
}

// Alter table background_subtasks
// If column task_id not exists, then
$query = "SELECT EXISTS (
    SELECT 1 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE 
      TABLE_SCHEMA = '" . $database . "' AND 
      TABLE_NAME = '" . $pre . "background_subtasks' AND 
      COLUMN_NAME = 'task_id'
  ) AS `Exists`";
$result = mysqli_query($db_link, $query);
if ($result === false) {
    // Change process_id to task_id
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'background_subtasks` CHANGE `process_id` `task_id` INT(12) NOT NULL;'
    );

    // Change system_process_id to process_id
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'background_subtasks` CHANGE `system_process_id` `process_id` varchar(100) NULL DEFAULT NULL;'
    );
}

// Add field can_create to api table
$res = addColumnIfNotExist(
    $pre . 'background_subtasks',
    'task_id',
    "INT(12) NOT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field background_subtasks to table background_subtasks! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Rename table 'processes_logs' to 'background_tasks_logs'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SHOW TABLES LIKE '" . $pre . "background_tasks_logs'"));
if (intval($tmp) === 0) {
    $tmp = mysqli_num_rows(mysqli_query($db_link, "SHOW TABLES LIKE '" . $pre . "processes_logs'"));
    if (intval($tmp) > 0) {
        mysqli_query(
            $db_link,
            'RENAME TABLE `' . $pre . 'processes_logs` TO `' . $pre . 'background_tasks_logs`;'
        );
    }
}

// Add new setting 'tasks_log_retention_delay'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'tasks_log_retention_delay'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'tasks_log_retention_delay', '3650')"
    );
}

//---<END 3.1.1



//--->BEGIN 3.1.2

// Add field allowed_folders to api table
$res = addColumnIfNotExist(
    $pre . 'api',
    'allowed_folders',
    "TEXT NOT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field allowed_folders to table api! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field enabled to api table
$res = addColumnIfNotExist(
    $pre . 'api',
    'enabled',
    "INT(1) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field enabled to table api! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field created_at to misc table
$res = addColumnIfNotExist(
    $pre . 'misc',
    'created_at',
    "VARCHAR(255) NULL DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field created_at to table misc! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field updated_at to misc table
$res = addColumnIfNotExist(
    $pre . 'misc',
    'updated_at',
    "VARCHAR(255) NULL DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field updated_at to table misc! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table background_subtasks 
modifyColumn(
    $pre . 'background_subtasks',
    'process_id',
    "process_id",
    "varchar(100) NULL DEFAULT NULL;"
);

// Add field allowed_to_delete to api table
$res = addColumnIfNotExist(
    $pre . 'api',
    'allowed_to_delete',
    "INT(1) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field allowed_to_delete to table api! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field allowed_to_update to api table
$res = addColumnIfNotExist(
    $pre . 'api',
    'allowed_to_update',
    "INT(1) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field allowed_to_update to table api! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field allowed_to_create to api table
$res = addColumnIfNotExist(
    $pre . 'api',
    'allowed_to_create',
    "INT(1) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field allowed_to_create to table api! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Rename column 'read_only' to 'allowed_to_read' in table api
$query = "SELECT EXISTS (
    SELECT 1 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE 
      TABLE_SCHEMA = '" . $database . "' AND 
      TABLE_NAME = '" . $pre . "api' AND 
      COLUMN_NAME = 'read_only'
  ) AS `Exists`";
$result = mysqli_query($db_link, $query);
$result = mysqli_query($db_link, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    if ($row['Exists'] == 1) {
        modifyColumn(
            $pre . 'api',
            'read_only',
            "allowed_to_read",
            "INT(1) NOT NULL DEFAULT '1';"
        );
    }
}

// Add field allowed_to_create to api table
$res = addColumnIfNotExist(
    $pre . 'api',
    'allowed_to_read',
    "INT(1) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field allowed_to_read to table api! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter type for column 'allowed_to_read' in table api
modifyColumn(
    $pre . 'api',
    'allowed_folders',
    "allowed_folders",
    "TEXT NULL DEFAULT NULL;"
);

// Add new setting 'oauth2_enabled'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'oauth2_enabled'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'oauth2_enabled', '0')"
    );
}

// Add new setting 'oauth2_client_appname'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'oauth2_client_appname'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'oauth2_client_appname', 'Login with Azure')"
    );
}
// Add new setting 'oauth2_client_scopes'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'oauth2_client_scopes'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'oauth2_client_scopes', 'openid,profile,email')"
    );
}

// Add index and change created/updated/finished_at type.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_tasks_logs`
        ADD INDEX idx_created_at (`created_at`),
        MODIFY `created_at` INT,
        MODIFY `updated_at` INT,
        MODIFY `finished_at` INT
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Add index on sharekeys_items.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "sharekeys_items`
        ADD INDEX idx_object_user (`object_id`, `user_id`)
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Add index on items.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "items`
        ADD INDEX items_perso_id_idx (`perso`, `id`)
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Add index on log_items.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "log_items`
        ADD INDEX log_items_item_action_user_idx (`id_item`, `action`, `id_user`)
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Add new setting 'show_item_data'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'show_item_data'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'show_item_data', '0')"
    );
}

// Add field split_view_mode to users table
$res = addColumnIfNotExist(
    $pre . 'users',
    'split_view_mode',
    "tinyint(1) NOT null DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field split_view_mode to table users! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add new setting 'limited_search_default'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'limited_search_default'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'limited_search_default', '0')"
    );
}

// Add new setting 'highlight_selected'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'highlight_selected'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'highlight_selected', '0')"
    );
}

// Add new setting 'highlight_favorites'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'highlight_favorites'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'highlight_favorites', '0')"
    );
}

// Add new setting 'number_users_build_cache_tree'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'number_users_build_cache_tree'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'number_users_build_cache_tree', '10')"
    );
}

// Add field is_encrypted to misc table
$res = addColumnIfNotExist(
    $pre . 'misc',
    'is_encrypted',
    "tinyint(1) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field is_encrypted to table misc! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Force some settings to be encrypted
mysqli_query(
    $db_link,
    "UPDATE `" . $pre . "misc` SET `is_encrypted` = '1' WHERE `intitule` IN ('onthefly-restore-key', 'onthefly-backup-key', 'bck_script_passkey', 'duo_akey', 'duo_ikey', 'duo_skey', 'oauth2_client_secret', 'ldap_password', 'email_auth_pwd')"
);

// We will remove tp.config.php file
// For this, we need first to check if it exists
// And copy each setting to the database if not already there or if different from the one in the database then update it
// Then rename it and remove the file
$configFilePath = __DIR__ . '/../includes/config/tp.config.php';
if (file_exists($configFilePath)) {
    include $configFilePath;
    
    foreach ($SETTINGS as $key => $value) {
        $escapedKey = mysqli_real_escape_string($db_link, $key);
        $escapedValue = mysqli_real_escape_string($db_link, $value);

        $query = "SELECT `valeur` FROM `" . $pre . "misc` WHERE `type` = 'admin' AND `intitule` = '$escapedKey'";
        $result = mysqli_query($db_link, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            if ($row['valeur'] !== $escapedValue) {
                $updateQuery = "UPDATE `" . $pre . "misc` SET `valeur` = '$escapedValue', `updated_at` = '" . time() . "' WHERE `type` = 'admin' AND `intitule` = '$escapedKey'";
                mysqli_query($db_link, $updateQuery);
            }
        } else {
            $insertQuery = "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`, `updated_at`) VALUES ('admin', '$escapedKey', '$escapedValue', '" . time() . "')";
            mysqli_query($db_link, $insertQuery);
        }
    }

    // Rename the file
    $newConfigFilePath = $configFilePath . '.bak';
    if (!rename($configFilePath, $newConfigFilePath)) {
        // Remove the file
        unlink($configFilePath);
    } else {
        // Remove the file
        unlink($newConfigFilePath);
    }    
}

// Clean a potential XSS payload present in the tags table
$replacements = [
    "&" => "&amp;",
    "<" => "&lt;",
    ">" => "&gt;",
    '"' => "&quot;",
    "'" => "&#39;"
];

$stmt = mysqli_prepare($db_link, 
    "UPDATE `" . $pre . "tags` 
    SET `tag` = REPLACE(`tag`, ?, ?)
    WHERE `tag` LIKE CONCAT('%', ?, '%')");

foreach ($replacements as $search => $replace) {
    // Link parameters to the prepared statement
    mysqli_stmt_bind_param($stmt, 'sss', $search, $replace, $search);
    mysqli_stmt_execute($stmt);
}

// Remove unused no_bad_attempts field
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "users`
        DROP COLUMN `no_bad_attempts`";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Table used to store authentication failures
mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `" . $pre . "auth_failures` (
    `id` int(12) NOT NULL AUTO_INCREMENT,
    `source` ENUM('login', 'remote_ip') NOT NULL,
    `value` VARCHAR(500) NOT NULL,
    `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `unlock_at` TIMESTAMP NULL DEFAULT NULL,
    `unlock_code` VARCHAR(50) NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) CHARSET=utf8;"
);

// Add unlock_code column
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "auth_failures`
        ADD COLUMN `unlock_code` VARCHAR(50) NULL DEFAULT NULL;";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

//---<END 3.1.2


//--->BEGIN 3.1.3

// Remove from roles_values folders without any access
$deleteQuery = "DELETE FROM `" . $pre . "roles_values` WHERE type = ''";
mysqli_query($db_link, $deleteQuery);

//---<END 3.1.3

//--->BEGIN 3.1.4

// Remove from roles_values folders without any access
if (tableHasColumn($pre . 'notification', 'id')) {
    // Drop the table
    mysqli_query($db_link, "DROP TABLE `" . $pre . "notification`");

    // Create the table
    mysqli_query(
        $db_link,
        "CREATE TABLE IF NOT EXISTS `" . $pre . "notification` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `item_id` int(12) NOT NULL,
            `user_id` int(12) NOT NULL,
            PRIMARY KEY (`increment_id`)
        ) CHARSET=utf8;"
    );
}

// Is TP_USER_ID created?
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "users` WHERE id = " . TP_USER_ID));
if (intval($tmp) === 0) {
    // Get encryption key
    $secureFilePath = rtrim(SECUREPATH, '/') . '/' . SECUREFILE;
    if (!file_exists($secureFilePath) || !is_readable($secureFilePath)) {
        return [
            'success' => false,
            'message' => "Encryption key file not found or not readable: " . $secureFilePath,
        ];
    }
    $encryptionKey = file_get_contents($secureFilePath);

    // generate key for password
    $passwordManager = new PasswordManager();
    $userPassword = $passwordManager->generatePassword(25, true, true, true, true);
    $encryptedUserPassword = cryption(
        $userPassword,
        $encryptionKey,
        'encrypt'
    )['string'];
    $userKeys = generateUserKeys($userPassword);

    // Insert the user into the database
    DB::insert($pre . 'users', [
        'id'                     => TP_USER_ID,
        'login'                  => 'TP',
        'pw'                     => $encryptedUserPassword,
        'groupes_visibles'       => '',
        'derniers'               => '',
        'key_tempo'              => '',
        'last_pw_change'         => '',
        'last_pw'                => '',
        'admin'                  => 1,
        'fonction_id'            => '',
        'groupes_interdits'      => '',
        'last_connexion'         => '',
        'gestionnaire'           => 0,
        'email'                  => '',
        'favourites'             => '',
        'latest_items'           => '',
        'personal_folder'        => 0,
        'public_key'             => $userKeys['public_key'],
        'private_key'            => $userKeys['private_key'],
        'is_ready_for_usage'     => 1,
        'otp_provided'           => 0,
        'created_at'             => time(),
    ]);
}

$tableImportationsExists = mysqli_query($db_link, "SHOW TABLES LIKE '" . $pre . "items_importations'");
if (mysqli_num_rows($tableImportationsExists) == 0) {
    mysqli_query(
        $db_link,
        "CREATE TABLE IF NOT EXISTS `" . $pre . "items_importations` (
        `increment_id` INT(12) AUTO_INCREMENT PRIMARY KEY,
        `operation_id` INT(12) NOT NULL,
        `label` VARCHAR(255) NOT NULL,
        `login` VARCHAR(255) NOT NULL,
        `pwd` TEXT NOT NULL,
        `url` TEXT NULL,
        `description` TEXT NULL,
        `folder` VARCHAR(255) NOT NULL,
        `folder_id` INT(12) NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `imported_at` INT(12) NULL DEFAULT NULL
        ) CHARSET=utf8;"
    );
}


// Add index on background_tasks.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_tasks`
        ADD INDEX idx_progress (is_in_progress)
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Add index on background_tasks.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_tasks`
        ADD INDEX idx_finished (finished_at)
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Add index on background_subtasks.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_subtasks`
        ADD INDEX idx_finished (finished_at)
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Add status field on background_tasks.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_tasks`
        ADD `status` VARCHAR(50) NULL DEFAULT NULL
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if field already exists.
    mysqli_rollback($db_link);
}

// Add error_message field on background_tasks.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_tasks`
        ADD `error_message` TEXT NULL DEFAULT NULL
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if field already exists.
    mysqli_rollback($db_link);
}

// Add status field on background_subtasks.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_subtasks`
        ADD `status` VARCHAR(50) NULL DEFAULT NULL
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if field already exists.
    mysqli_rollback($db_link);
}

// Add error_message field on background_subtasks.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_subtasks`
        ADD `error_message` TEXT NULL DEFAULT NULL
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if field already exists.
    mysqli_rollback($db_link);
}

// Add new setting 'tasks_history_delay'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'tasks_history_delay'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'tasks_history_delay', '604800')"
    );
}

// Add new setting 'oauth_new_user_is_administrated_by'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'oauth_new_user_is_administrated_by'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'oauth_new_user_is_administrated_by', '0')"
    );
}

// Add new setting 'oauth_selfregistered_user_belongs_to_role'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'oauth_selfregistered_user_belongs_to_role'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'oauth_selfregistered_user_belongs_to_role', '0')"
    );
}

// Add new setting 'oauth_self_register_groups'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'oauth_self_register_groups'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'oauth_self_register_groups', '')"
    );
}


// Releated to #4701
// Perform a clean up of table roles_values
// Ensure that all entries in roles_values are linked to a folder
// Delete all entries where folder doesn't exist anymore
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "roles_values`
    WHERE folder_id NOT IN (
        SELECT id FROM `" . $pre . "nested_tree`
    );"
);
// Delete entries in duplicate
// We keep the one with the highest increment_id
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "roles_values`
    WHERE increment_id NOT IN (
        SELECT MAX(trv2.increment_id)
        FROM `" . $pre . "roles_values` trv2
        GROUP BY trv2.folder_id, trv2.role_id
    );"
);

// Change field 'valeur' type in misc.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "misc`
        CHANGE `valeur` `valeur` MEDIUMTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if sql error.
    mysqli_rollback($db_link);
}

// Update setting from 'ldap_tls_certifacte_check' to 'ldap_tls_certificate_check'
$result = mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_tls_certifacte_check'");
$tmp = mysqli_num_rows($result);
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_tls_certificate_check', 'LDAP_OPT_X_TLS_NEVER')"
    );
} else {
    mysqli_query(
        $db_link,
        "UPDATE `" . $pre . "misc` SET intitule = 'ldap_tls_certificate_check' WHERE type = 'admin' AND intitule = 'ldap_tls_certifacte_check'"
    );
}

//---<END 3.1.4


//---------------------------------------------------------------------

//---< END 3.1.X upgrade steps


// Save timestamp
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'upgrade_timestamp'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', ".time().")"
    );
} else {
    mysqli_query(
        $db_link,
        "UPDATE `" . $pre . "misc` SET valeur = ".time()." WHERE type = 'admin' AND intitule = 'upgrade_timestamp'"
    );
}

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS
