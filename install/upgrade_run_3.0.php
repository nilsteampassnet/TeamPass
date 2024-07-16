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
 * @file      upgrade_run_3.0.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use EZimuel\PHPSecureSession;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
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

//--->BEGIN 3.0.1

// Ensure admin user is ready
mysqli_query(
    $db_link,
    "UPDATE ".$pre."users 
    SET is_ready_for_usage = 1, otp_provided = 1 
    WHERE id = 1"
);

//---<END 3.0.1

//---------------------------------------------------------------------

//--->BEGIN 3.0.5

// Add the INDEX process_id_idx to the processes_tasks table
$res = checkIndexExist(
    $pre . 'processes_tasks',
    'process_id_idx',
    "ADD KEY `process_id_idx` (`process_id`)"
);
if (!$res) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding the INDEX process_id_idx to the processes_tasks table! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

//---<END 3.0.5

//---------------------------------------------------------------------

//--->BEGIN 3.0.6
// Add new setting 'sending_emails_job_frequency'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'sending_emails_job_frequency'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'sending_emails_job_frequency', '2')"
    );
}
// Add new setting 'user_keys_job_frequency'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'user_keys_job_frequency'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'user_keys_job_frequency', '1')"
    );
}
// Add new setting 'items_statistics_job_frequency'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'enable_tasks_items_statistics_job_frequencymanager'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'items_statistics_job_frequency', '5')"
    );
}

// Add field ongoing_process_id to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'ongoing_process_id',
    "varchar(100) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field ongoing_process_id to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}
//---<END 3.0.6

//---------------------------------------------------------------------

//--->BEGIN 3.0.7
// Alter
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'cache_tree` CHANGE `data` `data` LONGTEXT DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Fix for #3679
mysqli_query(
    $db_link,
    "UPDATE `" . $pre . "users` SET `treeloadstrategy` = 'full' WHERE treeloadstrategy NOT IN ('full','sequential');"
);

//---<END 3.0.7

//---------------------------------------------------------------------

//--->BEGIN 3.0.8
// Add field mfa_disabled to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'mfa_enabled',
    "tinyint(1) NOT null DEFAULT '1';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field mfa_disabled to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

//---<END 3.0.8

//---------------------------------------------------------------------

//--->BEGIN 3.0.9
// Add new setting 'reload_cache_table_task'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'reload_cache_table_task'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'reload_cache_table_task', '')"
    );
}
// Add new setting 'rebuild_config_file'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'rebuild_config_file'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'rebuild_config_file', '')"
    );
}// Add new setting 'purge_temporary_files_task'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'purge_temporary_files_task'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'purge_temporary_files_task', '')"
    );
}
// Add new setting 'clean_orphan_objects_task'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'clean_orphan_objects_task'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'clean_orphan_objects_task', '')"
    );
}
// Add new setting 'users_personal_folder_task'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'users_personal_folder_task'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'users_personal_folder_task', '')"
    );
}

// Remove unused settings
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "misc` WHERE `intitule`='maintenance_job_tasks';"
);
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "misc` WHERE `intitule`='maintenance_job_frequency';"
);

// Add field item_key to ITEMS table
$res = addColumnIfNotExist(
    $pre . 'items',
    'item_key',
    "varchar(500) NOT NULL DEFAULT '-1';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field item_key to table ITEMS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Remove column unique from ITEMS table
$res = removeColumnIfNotExist(
    $pre . 'items',
    'unique'
);

// Add field export_tag to EXPORT table
$res = addColumnIfNotExist(
    $pre . 'export',
    'export_tag',
    "varchar(20) NOT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field export_tag to table EXPORT! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field folder_id to EXPORT table
$res = addColumnIfNotExist(
    $pre . 'export',
    'folder_id',
    "varchar(10) NOT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field folder_id to table EXPORT! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field perso to EXPORT table
$res = addColumnIfNotExist(
    $pre . 'export',
    'perso',
    "tinyint(1) NOT NULL default '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field perso to table EXPORT! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field restricted_to to EXPORT table
$res = addColumnIfNotExist(
    $pre . 'export',
    'restricted_to',
    "varchar(200) DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field restricted_to to table EXPORT! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Rename column id to item_id in EXPORT table
changeColumnName(
    $pre . 'export',
    'id',
    'item_id',
    "int(12) NOT NULL"
);

// Add field created_at to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'created_at',
    "varchar(30) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field created_at to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field updated_at to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'updated_at',
    "varchar(30) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field updated_at to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field deleted_at to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'deleted_at',
    "varchar(30) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field deleted_at to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// populate created_at, updated_at and deleted_at fields in USERS table
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "users` WHERE created_at IS NOT NULL"));
if (intval($tmp) === 0) {
    populateUsersTable($pre);
}
//---<END 3.0.9

//---------------------------------------------------------------------

//--->BEGIN 3.0.10

// Add field created_at to ITEMS table
$res = addColumnIfNotExist(
    $pre . 'items',
    'created_at',
    "varchar(30) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field created_at to table ITEMS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field updated_at to ITEMS table
$res = addColumnIfNotExist(
    $pre . 'items',
    'updated_at',
    "varchar(30) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field updated_at to table ITEMS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field deleted_at to ITEMS table
$res = addColumnIfNotExist(
    $pre . 'items',
    'deleted_at',
    "varchar(30) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field deleted_at to table ITEMS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'processes` CHANGE `process_type` `process_type` VARCHAR(100) NOT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Add new setting 'maximum_session_expiration_time'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'maximum_session_expiration_time'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'maximum_session_expiration_time', '60')"
    );
}

// Add field treated_objects to processes_logs table
$res = addColumnIfNotExist(
    $pre . 'processes_logs',
    'treated_objects',
    "varchar(20) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field treated_objects to table processes_logs! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Ensure admin has name and lastname
$resAdmin = mysqli_query(
    $db_link,
    "select name, lastname
    from `" . $pre . "users`
    WHERE id = 1"
);
$admin = mysqli_fetch_array($resAdmin);
if (is_null($admin['name']) === true && is_null($admin['lastname']) === true) {
    // update created_at field
    mysqli_query(
        $db_link,
        "UPDATE `" . $pre . "users` SET name = 'Change me', lastname = 'Change me' WHERE id = 1"
    );
}

// Add field started_at to processes table
$res = addColumnIfNotExist(
    $pre . 'processes',
    'started_at',
    "varchar(50) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field started_at to table processes! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field views to otv table
$res = addColumnIfNotExist(
    $pre . 'otv',
    'views',
    "INT(10) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field views to table otv! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field max_views to otv table
$res = addColumnIfNotExist(
    $pre . 'otv',
    'max_views',
    "INT(10) NULL DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field max_views to table otv! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field time_limit to otv table
$res = addColumnIfNotExist(
    $pre . 'otv',
    'time_limit',
    "varchar(100) DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field time_limit to table otv! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// delete old otv
mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "otv` WHERE max_views IS NULL;"
);

// Alter encrypted_psk in Users
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'users` CHANGE `encrypted_psk` `encrypted_psk` text NULL DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Alter url field in ITEMS
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'items` CHANGE `url` `url` TEXT NULL DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Alter url field in CACHE
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'cache` CHANGE `url` `url` TEXT NULL DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Add field item_id to processes table
$res = addColumnIfNotExist(
    $pre . 'processes',
    'item_id',
    "INT(12) NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field item_id to table processes! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter url field in ITEMS
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'users` CHANGE `login` `login` VARCHAR(500) NOT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Add new setting 'items_ops_job_frequency'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'items_ops_job_frequency'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'items_ops_job_frequency', '1')"
    );
}

// Alter raison_iv field in log_items
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'log_items` CHANGE `raison_iv` `old_value` MEDIUMTEXT NULL DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Alter description field in cache
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'cache` CHANGE `description` `description` MEDIUMTEXT NULL DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Add field shared_globaly to otv table
$res = addColumnIfNotExist(
    $pre . 'otv',
    'shared_globaly',
    "INT(1) NOT NULL DEFAULT '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field shared_globaly to table otv! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field keys_recovery_time to users table
$res = addColumnIfNotExist(
    $pre . 'users',
    'keys_recovery_time',
    "VARCHAR(500) NULL DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field keys_recovery_time to table users! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter description favourites in users
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'users` CHANGE `favourites` `favourites` varchar(1000) NULL DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Add field aes_iv to users table
$res = addColumnIfNotExist(
    $pre . 'users',
    'aes_iv',
    "TEXT NULL DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field aes_iv to table users! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter psk field in users
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'users` CHANGE `psk` `psk` VARCHAR(400) NULL DEFAULT NULL;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Now removing all old libraries
deleteAll([
    "Illuminate","Encryption","Tightenco","portable-ascii-master","Pdf","ForceUTF8","portable-utf8-master","GO","Symfony","Elegant","Firebase","Database","anti-xss-master","LdapRecord","misc","PasswordGenerator","Authentication","Goodby","Plupload","PHPMailer","TiBeN","protect","Cron","PasswordLib","Fork","Carbon","Tree","Webmozart"
]);

//---<END 3.0.10

//---------------------------------------------------------------------

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

//---< END 3.0.X upgrade steps

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS

function populateUsersTable($pre)
{
    global $db_link;
    // loop on users - created_at
    $users = mysqli_query(
        $db_link,
        "select u.id as uid, ls.date as datetime
        from `" . $pre . "users` as u
        inner join `" . $pre . "log_system` as ls on ls.field_1 = u.id
        WHERE ls.type = 'user_mngt' AND ls.label = 'at_user_added'"
    );
    while ($user = mysqli_fetch_assoc($users)) {
        if (empty((string) $user['datetime']) === false && is_null($user['datetime']) === false) {
            // update created_at field
            mysqli_query(
                $db_link,
                "UPDATE `" . $pre . "users` SET created_at = '".$user['datetime']."' WHERE id = ".$user['uid']
            );
        }
    }

    // loop on users - updated_at
    $users = mysqli_query(
        $db_link,
        "select u.id as uid, (select date from " . $pre . "log_system where type = 'user_mngt' and field_1=uid order by date DESC limit 1) as datetime from `" . $pre . "users` as u;"
    );
    while ($user = mysqli_fetch_assoc($users)) {
        if (empty((string) $user['datetime']) === false && is_null($user['datetime']) === false) {
            // update updated_at field
            mysqli_query(
                $db_link,
                "UPDATE `" . $pre . "users` SET updated_at = '".$user['datetime']."' WHERE id = ".$user['uid']
            );
        }
    }
}
