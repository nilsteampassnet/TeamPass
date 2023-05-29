<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upgrade_run_3.0.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

set_time_limit(600);


require_once '../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['CPM'] = 1;

//include librairies
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';
require_once '../includes/config/tp.config.php';

// Get the encrypted password
define('DB_PASSWD_CLEAR', defuse_return_decrypted(DB_PASSWD));

/*
//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $pre . 'nested_tree',
    'id',
    'parent_id',
    'title'
);
*/

// DataBase
// Test DB connexion
$pass = DB_PASSWD_CLEAR;
$server = DB_HOST;
$pre = DB_PREFIX;
$database = DB_NAME;
$port = DB_PORT;
$user = DB_USER;

if (mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
)) {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
} else {
    $res = 'Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Load libraries
require_once '../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();


//--->BEGIN 3.0.1

// Ensure admin user is ready
mysqli_query(
    $db_link,
    "UPDATE ".$pre."users 
    SET is_ready_for_usage = 1, otp_provided = 1 
    WHERE id = 1"
);

//---<END 3.0.1


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

//---<END 3.0.9

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