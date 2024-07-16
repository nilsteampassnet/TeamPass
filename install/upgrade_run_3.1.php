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
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);
$_SESSION['CPM'] = 1;

//include librairies
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';
require_once '../includes/config/tp.config.php';

// Get the encrypted password
define('DB_PASSWD_CLEAR', defuse_return_decrypted(DB_PASSWD));

// DataBase
// Test DB connexion
$pass = DB_PASSWD_CLEAR;
$server = DB_HOST;
$pre = DB_PREFIX;
$database = DB_NAME;
$port = DB_PORT;
$user = DB_USER;

if ($db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    )
) {
    $db_link->set_charset(DB_ENCODING);
} else {
    $res = 'Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    mysqli_close($db_link);
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

//---<END 3.1.2


//---------------------------------------------------------------------



//---< END 3.1.X upgrade steps

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS
