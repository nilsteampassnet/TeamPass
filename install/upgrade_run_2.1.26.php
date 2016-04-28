<?php
/*
** For release 2.1.26
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
            return false;
        }
    }
    if (!$exists) {
        return mysqli_query($dbTmp, "ALTER TABLE `$db` ADD `$column`  $columnAttr");
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


echo '[{"finish":"1" , "next":"", "error":""}]';