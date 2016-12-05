<?php
/**
 * @file          upgrade.ajax.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2016 Nils Laumaillé
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
require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
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

function cleanFields($txt) {
    $tmp = str_replace(",", ";", trim($txt));
    if ($tmp === "0" || empty($tmp)) return $tmp;
    if (strpos($tmp, ';') === 0) $tmp = substr($tmp, 1);
    if (substr($tmp, -1) !== ";") $tmp = $tmp.";";
    return $tmp;
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



// alter table Items
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."items` MODIFY pw_len INT(5) NOT NULL DEFAULT '0'");



// add field agses-usercardid to Users table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."users",
    "agses-usercardid",
    "VARCHAR(12) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field agses-usercardid to table Users! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


// add field encrypted_data to Categories table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."categories",
    "encrypted_data",
    "TINYINT(1) NOT NULL DEFAULT '1'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_data to table categories! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


// alter table USERS - user_language
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."users` MODIFY user_language VARCHAR(50) NOT NULL DEFAULT '0'");

// alter table USERS - just ensure correct naming of IsAdministratedByRole
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."users` CHANGE IsAdministratedByRole isAdministratedByRole tinyint(5) NOT NULL DEFAULT '0'");

// do clean of users table
$fieldsToUpdate = ['groupes_visibles', 'fonction_id', 'groupes_interdits'];
$result = mysqli_query($dbTmp, "SELECT id, groupes_visibles, fonction_id, groupes_interdits FROM `".$_SESSION['tbl_prefix']."users`");
while($row = mysqli_fetch_assoc($result)) {
    // check if field contains , instead of ;
    foreach($fieldsToUpdate as $field) {
        $tmp = cleanFields($row[$field]);
        if ($tmp !== $row[$field]) {
            mysqli_query($dbTmp,
                "UPDATE `".$_SESSION['tbl_prefix']."users`
                SET `".$field."` = '".$tmp."'
                WHERE id = '".$row['id']."'"
            );
        }
    }

}
mysqli_free_result($result);



// add field encrypted_data to CATEGORIES table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."categories",
    "encrypted_data",
    "TINYINT(1) NOT NULL DEFAULT '1'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_data to table CATEGORIES! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

mysqli_query($dbTmp,
                "UPDATE `".$_SESSION['tbl_prefix']."misc`
                    SET `valeur` = 'maintenance_mode'
                    WHERE type = 'admin' AND intitule = '".$_POST['no_maintenance_mode']."'"
                );


// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';