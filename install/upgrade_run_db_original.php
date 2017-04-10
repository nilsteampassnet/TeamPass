<?php
/**
 * @file          upgrade_run_db_original.php
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

require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
if (!file_exists("../includes/config/settings.php")) {
    echo 'document.getElementById("res_step1_error").innerHTML = "";';
    echo 'document.getElementById("res_step1_error").innerHTML = '.
        '"File settings.php does not exist in folder includes/! '.
        'If it is an upgrade, it should be there, otherwise select install!";';
    echo 'document.getElementById("loader").style.display = "none";';
    exit;
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
        WHERE table_schema = '".$_SESSION['database']."'
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


$dbTmp = mysqli_connect(
    $_SESSION['server'],
    $_SESSION['user'],
    $_SESSION['pass'],
    $_SESSION['database'],
    $_SESSION['port']
);

// 2.1.27 check with DEFUSE
// check if library defuse already on-going here
// if yes, then don't execute re-encryption
$_SESSION['tp_defuse_installed'] = false;
$columns = mysqli_query($dbTmp, "show columns from ".$_SESSION['pre']."items");
while ($c = mysqli_fetch_assoc( $columns)) {
    if ($c['Field'] === "encryption_type") {
        $_SESSION['tp_defuse_installed'] = true;
    }
}


## Populate table MISC
$val = array(
    array('admin', 'max_latest_items', '10',0),
    array('admin', 'enable_favourites', '1',0),
    array('admin', 'show_last_items', '1',0),
    array('admin', 'enable_pf_feature', '0',0),
    array('admin', 'menu_type', 'context',0),
    array('admin', 'log_connections', '0',0),
    array('admin', 'time_format', 'H:i:s',0),
    array('admin', 'date_format', 'd/m/Y',0),
    array('admin', 'duplicate_folder', '0',0),
    array('admin', 'duplicate_item', '0',0),
    array('admin', 'item_duplicate_in_same_folder', '0',0),
    array('admin', 'number_of_used_pw', '3',0),
    array('admin', 'manager_edit', '1',0),
    array('admin', 'cpassman_dir', '',0),
    array('admin', 'cpassman_url', '',0),
    array('admin', 'favicon', '',0),
    array('admin', 'activate_expiration', '0',0),
    array('admin', 'pw_life_duration','30',0),
    //array('admin', 'maintenance_mode','1',1),
    array('admin', 'cpassman_version',$k['version'],1),
    array('admin', 'ldap_mode','0',0),
    array('admin','ldap_type','0',0),
    array('admin','ldap_suffix','0',0),
    array('admin','ldap_domain_dn','0',0),
    array('admin','ldap_domain_controler','0',0),
    array('admin','ldap_user_attribute','0',0),
    array('admin','ldap_ssl','0',0),
    array('admin','ldap_tls','0',0),
    array('admin','ldap_elusers','0',0),
    array('admin', 'richtext',0,0),
    array('admin', 'allow_print',0,0),
    array('admin', 'roles_allowed_to_print',0,0),
    array('admin', 'show_description',1,0),
    array('admin', 'anyone_can_modify',0,0),
    array('admin', 'anyone_can_modify_bydefault',0,0),
    array('admin', 'nb_bad_authentication',0,0),
    array('admin', 'restricted_to',0,0),
    array('admin', 'restricted_to_roles',0,0),
    array('admin', 'utf8_enabled',1,0),
    array('admin', 'custom_logo','',0),
    array('admin', 'custom_login_text','',0),
    array('admin', 'log_accessed', '1',1),
    array('admin', 'default_language', 'english',0),
    array(
        'admin',
        'send_stats',
        empty($_SESSION['send_stats']) ? '0' : $_SESSION['send_stats'],
        1
    ),
    array('admin', 'get_tp_info', '1', 0),
    array('admin', 'send_mail_on_user_login', '0', 0),
    array('cron', 'sending_emails', '0', 0),
    array('admin', 'nb_items_by_query', 'auto', 0),
    array('admin', 'enable_delete_after_consultation', '0', 0),
    array(
        'admin',
        'path_to_upload_folder',
        strrpos($_SERVER['DOCUMENT_ROOT'], "/") == 1 ?
            (strlen($_SERVER['DOCUMENT_ROOT'])-1).substr(
                $_SERVER['PHP_SELF'],
                0,
                strlen($_SERVER['PHP_SELF'])-25
            ).'/upload'
        :
        $_SERVER['DOCUMENT_ROOT'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strlen($_SERVER['PHP_SELF'])-25
        ).'/upload',
        0
    ),
    array(
        'admin',
        'url_to_upload_folder',
        'http://'.$_SERVER['HTTP_HOST'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strrpos($_SERVER['PHP_SELF'], '/')-8
        ).'/upload',
        0
    ),
    array('admin', 'enable_personal_saltkey_cookie', '0', 0),
    array('admin', 'personal_saltkey_cookie_duration', '31', 0),
    array(
        'admin',
        'path_to_files_folder',
        strrpos($_SERVER['DOCUMENT_ROOT'], "/") == 1 ?
        (strlen($_SERVER['DOCUMENT_ROOT'])-1).substr(
            $_SERVER['PHP_SELF'],
            0,
            strlen($_SERVER['PHP_SELF'])-25
        ).'/files'
        :
        $_SERVER['DOCUMENT_ROOT'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strlen($_SERVER['PHP_SELF'])-25
        ).'/files',
        0
    ),
    array(
        'admin',
        'url_to_files_folder',
        'http://'.$_SERVER['HTTP_HOST'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strrpos($_SERVER['PHP_SELF'], '/')-8
        ).'/files',
        0
    ),
    array('admin', 'pwd_maximum_length','40',0),
    array('admin', 'ga_website_name','TeamPass for ChangeMe',0),
    array('admin', 'email_smtp_server', @$_SESSION['smtp_server'], 0),
    array('admin', 'email_smtp_auth', @$_SESSION['smtp_auth'], 0),
    array('admin', 'email_auth_username', @$_SESSION['smtp_auth_username'], 0),
    array('admin', 'email_auth_pwd', @$_SESSION['smtp_auth_password'], 0),
    array('admin', 'email_port', @$_SESSION['smtp_port'], 0),
    array('admin', 'email_security', @$_SESSION['smtp_security'], 0),
    array('admin', 'email_from', @$_SESSION['email_from'], 0),
    array('admin', 'email_from_name', @$_SESSION['email_from_name'], 0),
    array('admin', 'google_authentication', 0, 0),
    array('admin', 'delay_item_edition', 0, 0),
    array('admin', 'allow_import',0,0),
    array('admin', 'proxy_port',0,0),
    array('admin', 'proxy_port',0,0),
    array('admin','upload_maxfilesize','10mb',0),
    array(
        'admin',
        'upload_docext',
        'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx',
        0
    ),
    array('admin','upload_imagesext','jpg,jpeg,gif,png',0),
    array('admin','upload_pkgext','7z,rar,tar,zip',0),
    array('admin','upload_otherext','sql,xml',0),
    array('admin','upload_imageresize_options','1',0),
    array('admin','upload_imageresize_width','800',0),
    array('admin','upload_imageresize_height','600',0),
    array('admin','upload_imageresize_quality','90',0),
    array('admin','enable_send_email_on_user_login','0', 0),
    array('admin','enable_user_can_create_folders','0', 0),
    array('admin','insert_manual_entry_item_history','0', 0),
    array('admin','enable_kb','0', 0),
    array('admin','enable_email_notification_on_item_shown','0', 0),
    array('admin','enable_email_notification_on_user_pw_change','0', 0),
    array('admin','enable_sts','0', 0),
    array('admin','encryptClientServer','1', 0),
    array('admin','use_md5_password_as_salt','0', 0),
    array('admin','api','0', 0),
    array('admin', 'subfolder_rights_as_parent', '0', 0),
    array('admin', 'show_only_accessible_folders', '0', 0),
    array('admin', 'enable_suggestion', '0', 0),
    array('admin', 'email_server_url', '', 0),
    array('admin','otv_expiration_period','7', 0),
    array('admin','default_session_expiration_time','60', 0),
    array('admin','duo','0', 0),
    array('admin','enable_server_password_change','0', 0),
    array('admin','bck_script_path', $_SESSION['abspath']."/backups", 0),
    array('admin','bck_script_filename', 'bck_cpassman', 0)
);
$res1 = "na";
foreach ($val as $elem) {
    //Check if exists before inserting
    $queryRes = mysqli_query($dbTmp,
        "SELECT COUNT(*) FROM ".$_SESSION['pre']."misc
        WHERE type='".$elem[0]."' AND intitule='".$elem[1]."'"
    );
    if (mysqli_error($dbTmp)) {
        echo '[{"finish":"1", "msg":"", "error":"MySQL Error! '.
            addslashes($queryRes).'"}]';
        exit();
    } else {
        $resTmp = mysqli_fetch_row($queryRes);
        if ($resTmp[0] == 0) {
            $queryRes = mysqli_query($dbTmp,
                "INSERT INTO `".$_SESSION['pre']."misc`
                (`type`, `intitule`, `valeur`) VALUES
                ('".$elem[0]."', '".$elem[1]."', '".
                str_replace("'", "", $elem[2])."');"
            );
            if (!$queryRes) {
                echo '[{"finish":"1", "msg":"", "error":"MySQL Error! '.addslashes($queryRes).'"}]';
                exit();
            }
        } else {
            // Force update for some settings
            if ($elem[3] == 1) {
                $queryRes = mysqli_query($dbTmp,
                    "UPDATE `".$_SESSION['pre']."misc`
                    SET `valeur` = '".$elem[2]."'
                    WHERE type = '".$elem[0]."' AND intitule = '".$elem[1]."'"
                );
                if (!$queryRes) {
                    echo '[{"finish":"1", "msg":"", "error":"MySQL Error! '.addslashes($queryRes).'"}]';
                    exit();
                }
            }
        }
    }
}

if ($queryRes) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when inserting datas! '.
            addslashes($queryError).'"}]';
    exit();
}

## Alter ITEMS table
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "anyone_can_modify",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "email",
    "VARCHAR(100) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "notification",
    "VARCHAR(250) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "viewed_no",
    "INT(12) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "complexity_level",
    "varchar(2) NOT null DEFAULT '-1'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."roles_values",
    "type",
    "VARCHAR(5) NOT NULL DEFAULT 'R'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "upgrade_needed",
    "BOOLEAN NOT NULL DEFAULT FALSE"
);

$res2 = addIndexIfNotExist($_SESSION['pre'].'items', 'restricted_inactif_idx', 'ADD INDEX `restricted_inactif_idx` (`restricted_to`,`inactif`)');

# Alter tables
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."log_items MODIFY id_user INT(8)"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."restriction_to_roles MODIFY role_id INT(12)"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."restriction_to_roles MODIFY item_id INT(12)"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."items MODIFY pw TEXT"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users MODIFY pw VARCHAR(400)"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."cache CHANGE `login` `login` VARCHAR( 200 ) CHARACTER NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."log_system CHANGE `field_1` `field_1` VARCHAR( 250 ) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."keys CHANGE `table` `sql_table` VARCHAR( 25 ) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users MODIFY `key_tempo` varchar(100) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."categories CHANGE `type` `type` varchar(50) NULL default ''"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."categories CHANGE `order` `order` int(12) NOT NULL default '0'"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `derniers` `derniers` text NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `key_tempo` `key_tempo` varchar(100) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `last_pw_change` `last_pw_change` varchar(30) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `last_pw` `last_pw` text NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `fonction_id` `fonction_id` varchar(255) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `groupes_interdits` `groupes_interdits` varchar(255) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `last_connexion` `last_connexion` varchar(30) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `favourites` `favourites` varchar(300) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `latest_items` `latest_items` varchar(300) NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `avatar` `avatar` varchar(255) NOT null DEFAULT ''"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."users CHANGE `avatar_thumb` `avatar_thumb` varchar(255) NOT null DEFAULT ''"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."log_items CHANGE `raison` `raison` text NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."log_items CHANGE `raison_iv` `raison_iv` text NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."roles_values CHANGE `type` `type` VARCHAR( 5 ) NOT NULL DEFAULT 'R'"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."suggestion CHANGE `suggestion_key` `pw_iv` TEXT NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."suggestion CHANGE `key` `pw_iv` TEXT NULL"
);
mysqli_query($dbTmp,
    "ALTER TABLE ".$_SESSION['pre']."suggestion CHANGE `password` `pw` TEXT NULL"
);

## Alter USERS table
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "favourites",
    "VARCHAR(300)"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "latest_items",
    "VARCHAR(300)"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "personal_folder",
    "INT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "disabled",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "no_bad_attempts",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "can_create_root_folder",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "read_only",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "timestamp",
    "VARCHAR(30) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "user_language",
    "VARCHAR(30) NOT null DEFAULT 'english'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "name",
    "VARCHAR(100) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "lastname",
    "VARCHAR(100) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "session_end",
    "VARCHAR(30) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "isAdministratedByRole",
    "TINYINT(5) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "psk",
    "VARCHAR(400) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "ga",
    "VARCHAR(50) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "avatar",
    "VARCHAR(255) NOT null DEFAULT ''"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "avatar_thumb",
    "VARCHAR(255) NOT null DEFAULT ''"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."users",
    "treeloadstrategy",
    "VARCHAR(30) NOT null DEFAULT 'full'"
);

$res2 = addColumnIfNotExist(
    $_SESSION['pre']."log_items",
    "raison_iv",
    "TEXT null"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."categories_items",
    "data_iv",
    "TEXT NOT null"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "pw_iv",
    "TEXT NOT null"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "pw_len",
    "INT(5) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "auto_update_pwd_frequency",
    "TINYINT(2) NOT NULL DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."items",
    "auto_update_pwd_next_date",
    "INT(15) NOT NULL DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."cache",
    "renewal_period",
    "TINYINT(4) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."suggestion",
    "pw_len",
    "int(5) NOT null DEFAULT '0'"
);

// Clean timestamp for users table
mysqli_query($dbTmp,"UPDATE ".$_SESSION['pre']."users SET timestamp = ''");

## Alter nested_tree table
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."nested_tree",
    "personal_folder",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $_SESSION['pre']."nested_tree",
    "renewal_period",
    "TINYINT(4) NOT null DEFAULT '0'"
);

addIndexIfNotExist(
    $_SESSION['pre'].'nested_tree',
    'personal_folder_idx',
    'ADD INDEX `personal_folder_idx` (`personal_folder`)'
);


#to 1.08
//include('upgrade_db_1.08.php');

## TABLE TAGS
$res8 = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."tags` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `tag` varchar(30) NOT NULL,
    `item_id` int(12) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `id` (`id`)
    );"
);
if ($res8) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table TAGS!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE LOG_SYSTEM
$res8 = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."log_system` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `type` varchar(20) NOT NULL,
    `date` varchar(30) NOT NULL,
    `label` text NOT NULL,
    `qui` varchar(30) NOT NULL,
    PRIMARY KEY (`id`)
    );"
);
if ($res8) {
    mysqli_query($dbTmp,
        "ALTER TABLE ".$_SESSION['pre']."log_system
        ADD `field_1` VARCHAR(250) NOT NULL"
    );
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table LOG_SYSTEM!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE 10 - FILES
$res9 = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."files` (
    `id` int(11) NOT null AUTO_INCREMENT,
    `id_item` int(11) NOT NULL,
    `name` varchar(100) NOT NULL,
    `size` int(10) NOT NULL,
    `extension` varchar(10) NOT NULL,
    `type` varchar(50) NOT NULL,
    `file` varchar(50) NOT NULL,
    PRIMARY KEY (`id`)
    );"
);
if ($res9) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table FILES!"}]';
    mysqli_close($dbTmp);
    exit();
}
mysqli_query($dbTmp,
    "ALTER TABLE `".$_SESSION['pre']."files`
    CHANGE id id INT(11) AUTO_INCREMENT PRIMARY KEY;"
);
mysqli_query($dbTmp,
    "ALTER TABLE `".$_SESSION['pre']."files`
    CHANGE name name VARCHAR(100) NOT NULL;"
);

## TABLE CACHE
mysqli_query($dbTmp,"DROP TABLE IF EXISTS `".$_SESSION['pre']."cache`");
$res8 = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."cache` (
    `id` int(12) NOT NULL,
    `label` varchar(50) NOT NULL,
    `description` text NOT NULL,
    `tags` text NOT NULL,
    `id_tree` int(12) NOT NULL,
    `perso` tinyint(1) NOT NULL,
    `restricted_to` varchar(200) NOT NULL,
    `login` varchar(200) NOT NULL,
    `folder` varchar(300) NOT NULL,
    `author` varchar(50) NOT NULL,
    `renewal_period` TINYINT(4) NOT null DEFAULT '0'
    );"
);
if ($res8) {
    //ADD VALUES
    $sql = "SELECT *
            FROM ".$_SESSION['pre']."items as i
            INNER JOIN ".$_SESSION['pre']."log_items as l ON (l.id_item = i.id)
            AND l.action = 'at_creation'
            WHERE i.inactif=0";
    $rows = mysqli_query($dbTmp,$sql);
    while ($reccord = mysqli_fetch_array($rows)) {
        //Get all TAGS
        $tags = "";
        $itemsRes = mysqli_query($dbTmp,
            "SELECT tag FROM ".$_SESSION['pre']."tags
            WHERE item_id=".$reccord['id']
        ) or die(mysqli_error($dbTmp));
        $itemTags = mysqli_fetch_array($itemsRes);
        if (!empty($itemTags)) {
            foreach ($itemTags as $itemTag) {
                if (!empty($itemTag['tag'])) {
                    $tags .= $itemTag['tag']. " ";
                }
            }
        }
        //form id_tree to full foldername
        $folder = "";
        $arbo = $tree->getPath($reccord['id_tree'], true);
        foreach ($arbo as $elem) {
            $folder .= htmlspecialchars(stripslashes($elem->title), ENT_QUOTES)." > ";
        }

        //store data
        mysqli_query($dbTmp,
            "INSERT INTO ".$_SESSION['pre']."cache
            VALUES (
            '".$reccord['id']."',
            '".$reccord['label']."',
            '".$reccord['description']."',
            '".$tags."',
            '".$reccord['id_tree']."',
            '".$reccord['perso']."',
            '".$reccord['restricted_to']."',
            '".$reccord['login']."',
            '".$folder."',
            '".$reccord['id_user']."',
            0
            )"
        );
    }
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table CACHE!"}]';
    mysqli_close($dbTmp);
    exit();
}

/*
   *  Change table FUNCTIONS
   *  By 2 tables ROLES
*/
$res9 = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."roles_title` (
    `id` int(12) NOT NULL,
    `title` varchar(50) NOT NULL,
    `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
    `complexity` INT(5) NOT null DEFAULT '0',
    `creator_id` int(11) NOT null DEFAULT '0'
    );"
);
addColumnIfNotExist(
    $_SESSION['pre']."roles_title",
    "allow_pw_change",
    "TINYINT(1) NOT null DEFAULT '0'"
);
addColumnIfNotExist(
    $_SESSION['pre']."roles_title",
    "complexity",
    "INT(5) NOT null DEFAULT '0'"
);
addColumnIfNotExist(
    $_SESSION['pre']."roles_title",
    "creator_id",
    "INT(11) NOT null DEFAULT '0'"
);

$res10 = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."roles_values` (
    `role_id` int(12) NOT NULL,
    `folder_id` int(12) NOT NULL
    );"
);
if (tableExists($_SESSION['pre']."functions")) {
    $tableFunctionExists = true;
} else {
    $tableFunctionExists = false;
}
if ($res9 && $res10 && $tableFunctionExists == true) {
    //Get data from tables FUNCTIONS and populate new ROLES tables
    $rows = mysqli_query($dbTmp,
        "SELECT * FROM ".$_SESSION['pre']."functions"
    );
    while ($reccord = mysqli_fetch_array($rows)) {
        //Add new role title
        mysqli_query($dbTmp,
            "INSERT INTO ".$_SESSION['pre']."roles_title
            VALUES (
                '".$reccord['id']."',
                '".$reccord['title']."'
           )"
        );

        //Add each folder in roles_values
        foreach (explode(';', $reccord['groupes_visibles']) as $folderId) {
            if (!empty($folderId)) {
                mysqli_query($dbTmp,
                    "INSERT INTO ".$_SESSION['pre']."roles_values
                    VALUES (
                    '".$reccord['id']."',
                    '".$folderId."'
                   )"
                );
            }
        }
    }

    //Now alter table roles_title in order to create a primary index
    mysqli_query($dbTmp,
        "ALTER TABLE `".$_SESSION['pre']."roles_title`
        ADD PRIMARY KEY(`id`)"
    );
    mysqli_query($dbTmp,
        "ALTER TABLE `".$_SESSION['pre']."roles_title`
        CHANGE `id` `id` INT(12) NOT null AUTO_INCREMENT "
    );
    addColumnIfNotExist(
        $_SESSION['pre']."roles_title",
        "allow_pw_change",
        "TINYINT(1) NOT null DEFAULT '0'"
    );

    //Drop old table
    mysqli_query($dbTmp,"DROP TABLE ".$_SESSION['pre']."functions");
} elseif ($tableFunctionExists == false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table ROLES!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE KB
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."kb` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `category_id` int(12) NOT NULL,
    `label` varchar(200) NOT NULL,
    `description` text NOT NULL,
    `author_id` int(12) NOT NULL,
    `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
    PRIMARY KEY (`id`)
    );"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table KB!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE KB_CATEGORIES
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."kb_categories` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `category` varchar(50) NOT NULL,
    PRIMARY KEY (`id`)
    );"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table KB_CATEGORIES!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE KB_ITEMS
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."kb_items` (
    `kb_id` tinyint(12) NOT NULL,
    `item_id` tinyint(12) NOT NULL
     );"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table KB_ITEMS!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE restriction_to_roles
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."restriction_to_roles` (
    `role_id` tinyint(12) NOT NULL,
    `item_id` tinyint(12) NOT NULL
    ) CHARSET=utf8;"
);

$res = addIndexIfNotExist($_SESSION['pre'].'restriction_to_roles', 'role_id_idx', 'ADD INDEX `role_id_idx` (`role_id`)');

if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table RESTRICTION_TO_ROLES!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE Languages
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."languages` (
    `id` INT(10) NOT null AUTO_INCREMENT PRIMARY KEY ,
    `name` VARCHAR(50) NOT null ,
    `label` VARCHAR(50) NOT null ,
    `code` VARCHAR(10) NOT null ,
    `flag` VARCHAR(30) NOT NULL
    ) CHARSET=utf8;"
);
$resTmp = mysqli_fetch_row(
    mysqli_query($dbTmp,"SELECT COUNT(*) FROM ".$_SESSION['pre']."languages")
);
mysqli_query($dbTmp,"TRUNCATE TABLE ".$_SESSION['pre']."languages");
mysqli_query($dbTmp,
    "INSERT IGNORE INTO `".$_SESSION['pre']."languages`
    (`id`, `name`, `label`, `code`, `flag`) VALUES
    ('', 'french', 'French' , 'fr', 'fr.png'),
    ('', 'english', 'English' , 'us', 'us.png'),
    ('', 'spanish', 'Spanish' , 'es', 'es.png'),
    ('', 'german', 'German' , 'de', 'de.png'),
    ('', 'czech', 'Czech' , 'cz', 'cz.png'),
    ('', 'italian', 'Italian' , 'it', 'it.png'),
    ('', 'russian', 'Russian' , 'ru', 'ru.png'),
    ('', 'turkish', 'Turkish' , 'tr', 'tr.png'),
    ('', 'norwegian', 'Norwegian' , 'no', 'no.png'),
    ('', 'japanese', 'Japanese' , 'ja', 'ja.png'),
    ('', 'portuguese', 'Portuguese' , 'pr', 'pr.png'),
    ('', 'chinese', 'Chinese' , 'cn', 'cn.png'),
    ('', 'swedish', 'Swedish' , 'se', 'se.png'),
    ('', 'dutch', 'Dutch' , 'nl', 'nl.png'),
    ('', 'catalan', 'Catalan' , 'ct', 'ct.png'),
    ('', 'vietnamese', 'Vietnamese' , 'vi', 'vi.png'),
    ('', 'estonian', 'Estonian' , 'ee', 'ee.png');"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table LANGUAGES!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE EMAILS
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."emails` (
    `timestamp` INT(30) NOT null ,
    `subject` VARCHAR(255) NOT null ,
    `body` TEXT NOT null ,
    `receivers` VARCHAR(255) NOT null ,
    `status` VARCHAR(30) NOT NULL
    ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table EMAILS!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE AUTOMATIC DELETION
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."automatic_del` (
    `item_id` int(11) NOT NULL,
    `del_enabled` tinyint(1) NOT NULL,
    `del_type` tinyint(1) NOT NULL,
    `del_value` varchar(35) NOT NULL
    ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table AUTOMATIC_DEL!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE items_edition
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."items_edition` (
    `item_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `timestamp` varchar(50) NOT NULL
   ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table items_edition! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE categories
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."categories` (
    `id` int(12) NOT NULL AUTO_INCREMENT,
    `parent_id` int(12) NOT NULL,
    `title` varchar(255) NOT NULL,
    `level` int(2) NOT NULL,
    `description` text NOT NULL,
    `type` varchar(50) NOT NULL,
    `order` int(12) NOT NULL,
    PRIMARY KEY (`id`)
   ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table CATEGORIES! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE categories_items
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."categories_items` (
    `id` int(12) NOT NULL AUTO_INCREMENT,
    `field_id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `data` text NOT NULL,
    PRIMARY KEY (`id`)
   ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table categories_items! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE categories_folders
$res = mysqli_query($dbTmp,
"CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."categories_folders` (
    `id_category` int(12) NOT NULL,
    `id_folder` int(12) NOT NULL
   ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table categories_folders! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE api
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."api` (
    `id` int(20) NOT NULL AUTO_INCREMENT,
    `type` varchar(15) NOT NULL,
    `label` varchar(255) NOT NULL,
    `value` varchar(255) NOT NULL,
    `timestamp` varchar(50) NOT NULL,
    PRIMARY KEY (`id`)
   ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table API! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE otv
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."otv` (
    `id` int(10) NOT NULL AUTO_INCREMENT,
    `timestamp` text NOT NULL,
    `code` varchar(100) NOT NULL,
    `item_id` int(12) NOT NULL,
    `originator` tinyint(12) NOT NULL,
    PRIMARY KEY (`id`)
   ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table OTV! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

## TABLE suggestion
$res = mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."suggestion` (
    `id` tinyint(12) NOT NULL AUTO_INCREMENT,
    `label` varchar(255) NOT NULL,
    `pw` text NOT NULL,
    `pw_iv` text NOT NULL,
    `pw_len` int(5) NOT NULL,
    `description` text NOT NULL,
    `author_id` int(12) NOT NULL,
    `folder_id` int(12) NOT NULL,
    `comment` text NOT NULL,
    PRIMARY KEY (`id`)
    ) CHARSET=utf8;"
);
if ($res) {
    //
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table SUGGESTIONS! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

# TABLE EXPORT
mysqli_query($dbTmp,
    "CREATE TABLE IF NOT EXISTS `".$_SESSION['pre']."export` (
    `id` int(12) NOT NULL,
    `label` varchar(255) NOT NULL,
    `login` varchar(100) NOT NULL,
    `description` text NOT NULL,
    `pw` text NOT NULL,
    `path` varchar(255) NOT NULL
    ) CHARSET=utf8;"
);

//CLEAN UP ITEMS TABLE
$allowedTags = '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul>'.
    '<blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>';
$cleanRes = mysqli_query($dbTmp,
    "SELECT id,description FROM `".$_SESSION['pre']."items`"
);
while ($cleanData = mysqli_fetch_array($cleanRes)) {
    mysqli_query($dbTmp,
        "UPDATE `".$_SESSION['pre']."items`
        SET description = '".strip_tags($cleanData['description'], $allowedTags).
        "' WHERE id = ".$cleanData['id']
    );
}

// 2.1.23 - check if personal need to be upgraded
$tmpResult = mysqli_query($dbTmp,
    "SELECT `pw_iv` FROM ".$_SESSION['pre']."items WHERE perso='1'"
);
$tmp = mysqli_fetch_row($tmpResult);
if ($tmp[0] == "") {
    mysqli_query($dbTmp, "UPDATE ".$_SESSION['pre']."users SET upgrade_needed = true WHERE 1 = 1");
}

/*// Since 2.1.17, encrypt process is changed.
// Previous PW need to be re-encrypted
if (@mysqli_query($dbTmp,
    "SELECT valeur FROM ".$_SESSION['pre']."misc
    WHERE type='admin' AND intitule = 'encryption_protocol'"
)) {
    $tmpResult = mysqli_query($dbTmp,
        "SELECT valeur FROM ".$_SESSION['pre']."misc
        WHERE type='admin' AND intitule = 'encryption_protocol'"
    );
    $tmp = mysqli_fetch_row($tmpResult);
    if ($tmp[0] != "ctr") {
        //count elem
        $res = mysqli_query($dbTmp,
            "SELECT COUNT(*) FROM ".$_SESSION['pre']."items
            WHERE perso = '0'"
        );
        $data = mysqli_fetch_row($res);
        if ($data[0] > 0) {
            echo '$("#change_pw_encryption, #change_pw_encryption_progress").show();';
            echo '$("#change_pw_encryption_progress").html('.
                '"Number of Passwords to re-encrypt: '.$data[0].'");';
            echo '$("#change_pw_encryption_total").val("'.$data[0].'")';
            exit();
        }

    }
}*/

mysqli_close($dbTmp);

echo '[{"finish":"1", "msg":"Database has been populated with Original Data.", "error":""}]';