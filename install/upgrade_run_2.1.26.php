<?php
/**
 * @file          upgrade.ajax.php
 * @author        Nils Laumaillé
 * @version       2.1.26
 * @copyright     (c) 2009-2011 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
 
/*
** Upgrade script for release 2.1.26
*/
require_once('../sources/sessions.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/include.php';
if (!file_exists("../includes/settings.php")) {
    echo 'document.getElementById("res_step1_error").innerHTML = "";';
    echo 'document.getElementById("res_step1_error").innerHTML = '.
        '"File settings.php does not exist in folder includes/! '.
        'If it is an upgrade, it should be there, otherwise select install!";';
    echo 'document.getElementById("loader").style.display = "none";';
    exit;
}

require_once '../includes/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['settings']['loaded'] = "";

################
## Function permits to get the value from a line
################
function getSettingValue($val)
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";")-1)));
}

################
## Function permits to check if a column exists, and if not to add it
################
function addColumnIfNotExist($db, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $dbTmp;
    $exists = false;
    $columns = mysqli_query($dbTmp, "show columns from $db");
    while ($c = mysqli_fetch_assoc( $columns)) {
        if ($c['Field'] == $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists) {
        return mysqli_query($dbTmp, "ALTER TABLE `$db` ADD `$column`  $columnAttr");
    } else {
        return false;
    }
}

function addIndexIfNotExist($table, $index, $sql ) {
    global $dbTmp;

    $mysqli_result = mysqli_query($dbTmp, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query($dbTmp, "ALTER TABLE `$table` " . $sql);
    }

    return $res;
}

function tableExists($tablename, $database = false)
{
    global $dbTmp;

    $res = mysqli_query($dbTmp,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$_SESSION['db_bdd']."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) return true;
    else return false;
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

$return_error = "";

// do initial upgrade

//include librairies
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $_SESSION['tbl_prefix'].'nested_tree',
    'id',
    'parent_id',
    'title'
);

// dataBase
$res = "";

mysqli_connect(
    $_SESSION['db_host'],
    $_SESSION['db_login'],
    $_SESSION['db_pw'],
    $_SESSION['db_bdd'],
    $_SESSION['db_port']
);
$dbTmp = mysqli_connect(
    $_SESSION['db_host'],
    $_SESSION['db_login'],
    $_SESSION['db_pw'],
    $_SESSION['db_bdd'],
    $_SESSION['db_port']
);

// add field timestamp to cache table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."cache",
    "timestamp",
    "VARCHAR(50) NOT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field Timestamp to table Cache! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

// add field can_manage_all_users to users table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."users",
    "can_manage_all_users",
    "BOOLEAN NOT NULL DEFAULT FALSE"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field can_manage_all_users to table Users! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

// check that API doesn't exist
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['tbl_prefix']."users` WHERE id = '9999999'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
	mysqli_query($dbTmp,
		"INSERT INTO `".$_SESSION['tbl_prefix']."users` (`id`, `login`, `read_only`) VALUES ('9999999', 'API', '1')"
	);
}


// alter table Items
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."items` MODIFY complexity_level VARCHAR(3)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."items` MODIFY label VARCHAR(100)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."items` MODIFY url VARCHAR(500)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."items` MODIFY restricted_to DEFAULT NULL");

// alter table cache
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."cache` MODIFY label VARCHAR(100)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."cache` MODIFY restricted_to DEFAULT NULL");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."cache` MODIFY tags DEFAULT NULL");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."cache` MODIFY timestamp DEFAULT NULL");

// add Estonian
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['tbl_prefix']."languages` WHERE name = 'estonian'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
	mysqli_query($dbTmp, "INSERT INTO `".$_SESSION['tbl_prefix']."languages` VALUES (null, 'estonian', 'Estonian', 'ee', 'ee.png')");
}

// remove Estonia
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['tbl_prefix']."languages` WHERE name = 'estonia'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
	mysqli_query($dbTmp, "DELETE FROM `".$_SESSION['tbl_prefix']."languages` WHERE name = 'estonia'");
}

// ensure CSRFP config file is ready
if (!isset($_SESSION['upgrade']['csrfp_config_file']) || $_SESSION['upgrade']['csrfp_config_file'] != 1) {
    $csrfp_file_sample = "../includes/libraries/csrfp/libs/csrfp.config.sample.php";
    $csrfp_file = "../includes/libraries/csrfp/libs/csrfp.config.php";
    if (file_exists($csrfp_file)) {
        if (!copy($csrfp_file, $csrfp_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
            echo '[{"finish":"1" , "next":"", "error" : "csrfp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch."}]';
            break;
        } else {
            // "The file $csrfp_file already exist. A copy has been created.<br />";
        }
    }
    unlink($csrfp_file);    // delete existing csrfp.config file
    copy($csrfp_file_sample, $csrfp_file);  // make a copy of csrfp.config.sample file
    $data = file_get_contents("../includes/libraries/csrfp/libs/csrfp.config.php");
    $newdata = str_replace('"CSRFP_TOKEN" => ""', '"CSRFP_TOKEN" => "'.bin2hex(openssl_random_pseudo_bytes(25)).'"', $data);
    $newdata = str_replace('"tokenLength" => "25"', '"tokenLength" => "50"', $newdata);
    $jsUrl = $_SESSION['fullurl'].'/includes/libraries/csrfp/js/csrfprotector.js';
    $newdata = str_replace('"jsUrl" => ""', '"jsUrl" => "'.$jsUrl.'"', $newdata);
    file_put_contents("../includes/libraries/csrfp/libs/csrfp.config.php", $newdata);
    
    $_SESSION['upgrade']['csrfp_config_file'] = 1;
}

echo '[{"finish":"1" , "next":"", "error":""}]';