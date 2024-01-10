<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upgrade_run_3.1.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use PasswordLib\PasswordLib;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
session_name('teampass_session');
session_start();
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
    `increment_id` int(12) NOT NULL,
    `item_id` int(12) NOT NULL,
    `secret` text NOT NULL,
    `timestamp` varchar(100) NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT 0,
    `phone_number` varchar(25) NOT NULL,
    PRIMARY KEY (`increment_id`),
    KEY `ITEM` (`item_id`)
    ) CHARSET=utf8;'
);

// Alter table TOKENS
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'tokens` CHANGE `end_timestamp` `end_timestamp` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL;'
);


// Alter table ldap_groups_roles
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'ldap_groups_roles` CHANGE `ldap_group_id` `ldap_group_id` VARCHAR(500) NOT NULL;'
);


// Add new setting 'ldap_group_objectclasses_attibute'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_group_objectclasses_attibute'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_group_objectclasses_attibute', 'top,groupofuniquenames')"
    );
}

//---<END 3.1.1

//---------------------------------------------------------------------



//---< END 3.1.X upgrade steps

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS
