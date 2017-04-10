<?php
/**
 * @file          upgrade.ajax.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/*
** Upgrade script for release 2.1.27
*/
require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
if (!file_exists("../includes/settings.php") && !file_exists("../includes/config/settings.php")) {
    echo 'document.getElementById("res_step1_error").innerHTML = "";';
    echo 'document.getElementById("res_step1_error").innerHTML = '.
        '"File settings.php does not exist in folder includes/! '.
        'If it is an upgrade, it should be there, otherwise select install!";';
    echo 'document.getElementById("loader").style.display = "none";';
    exit;
}

// handle file
if (file_exists("../includes/settings.php") && !file_exists("../includes/config/settings.php")) {
    // copy to config/
    copy("../includes/settings.php", "../includes/config/settings.php");
    unlink("../includes/settings.php");
} else if (file_exists("../includes/settings.php") && file_exists("../includes/config/settings.php")) {
    // remove as not used anymore
    unlink("../includes/settings.php");
}


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

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

$return_error = "";

// do initial upgrade

//include librairies
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $_SESSION['pre'].'nested_tree',
    'id',
    'parent_id',
    'title'
);

// dataBase
$res = "";

mysqli_connect(
    $_SESSION['server'],
    $_SESSION['user'],
    $_SESSION['pass'],
    $_SESSION['database'],
    $_SESSION['port']
);
$dbTmp = mysqli_connect(
    $_SESSION['server'],
    $_SESSION['user'],
    $_SESSION['pass'],
    $_SESSION['database'],
    $_SESSION['port']
);

// add field timestamp to cache table
$res = addColumnIfNotExist(
    $_SESSION['pre']."cache",
    "timestamp",
    "VARCHAR(50) NOT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field Timestamp to table Cache! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

// add field url to cache table
$res = addColumnIfNotExist(
    $_SESSION['pre']."cache",
    "url",
    "VARCHAR(500) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field Url to table Cache! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

// add field can_manage_all_users to users table
$res = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "can_manage_all_users",
    "tinyint(1) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field can_manage_all_users to table Users! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

// check that API doesn't exist
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."users` WHERE id = '".API_USER_ID."'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."users` (`id`, `login`, `read_only`) VALUES ('".API_USER_ID."', 'API', '1')"
    );
}

// check that SYSLOG doesn't exist
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'syslog_enable'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'syslog_enable', '0')"
    );
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'syslog_host', 'localhost')"
    );
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['pre']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'syslog_port', '514')"
    );
}


// alter table Items
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` MODIFY complexity_level VARCHAR(3)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` MODIFY label VARCHAR(500)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` MODIFY url VARCHAR(500)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` MODIFY restricted_to DEFAULT NULL");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` CHANGE `description` `description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` CHANGE `pw` `pw` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` CHANGE `pw_iv` `pw_iv` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL");

// alter table cache
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."cache` MODIFY label VARCHAR(500)");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."cache` MODIFY restricted_to DEFAULT NULL");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."cache` MODIFY tags DEFAULT NULL");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."cache` MODIFY timestamp DEFAULT NULL");

// alter table files
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."files` MODIFY type VARCHAR(255)");

// alter table USers
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."users`  ADD `usertimezone` VARCHAR(50) NOT NULL DEFAULT 'not_defined'");
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."users` MODIFY can_manage_all_users tinyint(1) NOT NULL DEFAULT '0'");

// alter table log_system
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."log_system` MODIFY qui VARCHAR(255)");

// create index in log_items - for performance
mysqli_query($dbTmp, "CREATE INDEX teampass_log_items_id_item_IDX ON ".$_SESSION['pre']."log_items (id_item,date);");

// change to true setting variable encryptClientServer
// this variable is not to be changed anymore
mysqli_query($dbTmp, "UPDATE `".$_SESSION['pre']."misc SET `valeur` = 1 WHERE `type` = 'admin' AND `intitule` = 'encryptClientServer'");

// create new table
mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."tokens` (
    `id` int(12) NOT NULL AUTO_INCREMENT,
    `user_id` int(10) NOT NULL,
    `token` varchar(255) NOT NULL,
    `reason` varchar(255) NOT NULL,
    `creation_timestamp` varchar(50) NOT NULL,
    `end_timestamp` varchar(50) NOT NULL,
    PRIMARY KEY (`id`)
    ) CHARSET=utf8;"
);

// change to 0 if auto_update_pwd_next_date empty in ITEMS table
$result = mysqli_query($dbTmp, "SELECT id FROM `".$_SESSION['pre']."items` WHERE auto_update_pwd_next_date = ''");
while($row = mysqli_fetch_assoc($result)) {
    mysqli_query($dbTmp,
        "UPDATE `".$_SESSION['pre']."items`
        SET `auto_update_pwd_next_date` = '0'
        WHERE id = '".$row['id']."'"
    );
}
mysqli_free_result($result);


// add Estonian
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."languages` WHERE name = 'estonian'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
    mysqli_query($dbTmp, "INSERT INTO `".$_SESSION['pre']."languages` VALUES (null, 'estonian', 'Estonian', 'ee', 'ee.png')");
}

// remove Estonia
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."languages` WHERE name = 'estonia'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
    mysqli_query($dbTmp, "DELETE FROM `".$_SESSION['pre']."languages` WHERE name = 'estonia'");
}

// ensure CSRFP config file is ready
if (!isset($_SESSION['upgrade']['csrfp_config_file']) || $_SESSION['upgrade']['csrfp_config_file'] != 1) {
    $csrfp_file_sample = "../includes/libraries/csrfp/libs/csrfp.config.sample.php";
    $csrfp_file = "../includes/libraries/csrfp/libs/csrfp.config.php";
    if (file_exists($csrfp_file)) {
        if (!copy($csrfp_file, $csrfp_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
            echo '[{"finish":"1" , "next":"", "error" : "csrfp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch."}]';
            return false;
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

/*
* Introduce new CONFIG file
*/
    $tp_config_file = "../includes/config/tp.config.php";
    if (file_exists($tp_config_file)) {
        if (!copy($tp_config_file, $tp_config_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
            echo '[{"error" : "includes/config/tp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
            return false;
        } else {
            unlink($tp_config_file);
        }
    }
    $fh = fopen($tp_config_file, 'w');
    $config_text = "<?php
    global \$SETTINGS;
    \$SETTINGS = array (";

    $result = mysqli_query($dbTmp, "SELECT * FROM `".$_SESSION['pre']."misc` WHERE type = 'admin'");
    while($row = mysqli_fetch_assoc($result)) {
        // append new setting in config file
        $config_text .= "
        '".$row['intitule']."' => '".$row['valeur']."',";
    }
    mysqli_free_result($result);

    // write to config file
    $result = fwrite(
        $fh,
        utf8_encode(
            substr_replace($config_text, "", -1)."
    );"
        )
    );
    fclose($fh);


// clean duplicate ldap_object_class from bad update script version
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'ldap_object_class'"));
if ($tmp[0] > 1 ) {
    mysqli_query($dbTmp, "DELETE FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'ldap_object_class' AND `valeur` = 0");
}
// add new setting - ldap_object_class
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'ldap_object_class'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
    mysqli_query($dbTmp, "INSERT INTO `".$_SESSION['pre']."misc` VALUES ('admin', 'ldap_object_class', '0')");
}

// convert 2factors_ to google_ due to illegal id, and for clarification of purpose
$tmp_googlecount = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = 'google_authentication'"));
$tmp_twocount = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = '2factors_authentication'"));

if ($tmp_googlecount[0] > 0 ) {
    mysqli_query($dbTmp, "DELETE FROM `".$_SESSION['pre']."misc` WHERE type = 'admin' AND intitule = '2factors_authentication'");
} else {
    if ($tmp_twocount[0] > 0 ) {
        mysqli_query($dbTmp, "UPDATE `".$_SESSION['pre']."misc` SET intitule = 'google_authentication' WHERE intitule = '2factors_authentication' ");
    } else {
        mysqli_query($dbTmp, "INSERT INTO `".$_SESSION['pre']."misc` VALUES ('admin', 'google_authentication', '0')");
    }
}


// Fix for #1510
// change the "personal_folder" field on all named folders back to "0" in nested_tree
$result = mysqli_query(
    $dbTmp,
    "SELECT title, id
    FROM `".$_SESSION['pre']."nested_tree`
    WHERE personal_folder = '1' AND nlevel = '1' AND parent_id = '0'"
);
while($row = mysqli_fetch_assoc($result)) {
    // only change non numeric folder title
    if (!is_numeric($row['title'])) {
        mysqli_query(
            $dbTmp,
            "UPDATE `".$_SESSION['pre']."nested_tree`
            SET personal_folder = '0'
            WHERE id = '".$row['id']."'"
        );
    }
}
mysqli_free_result($result);


// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';