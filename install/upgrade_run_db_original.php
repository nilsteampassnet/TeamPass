<?php
/**
 * @file          upgrade_run_db_original.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
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
## Function permits to check if a column exists, and if not to add it
################
function addColumnIfNotExist($dbname, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $db_link;
    $exists = false;
    $columns = mysqli_query($db_link, "show columns from $dbname");
    while ($col = mysqli_fetch_assoc($columns)) {
        if ($col['Field'] == $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists) {
        return mysqli_query($db_link, "ALTER TABLE `$dbname` ADD `$column`  $columnAttr");
    }

    return false;
}

function addIndexIfNotExist($table, $index, $sql)
{
    global $db_link;

    $mysqli_result = mysqli_query($db_link, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query(
            $db_link,
            "ALTER TABLE `$table` ".$sql
        );
    }

    return $res;
}

function tableExists($tablename)
{
    global $db_link, $database;

    $res = mysqli_query(
        $db_link,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$database."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) {
        return true;
    }

    return false;
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

$return_error = "";

// do initial upgrade

//include librairies
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $pre.'nested_tree',
    'id',
    'parent_id',
    'title'
);

// dataBase
$res = "";

// Test DB connexion
$pass = defuse_return_decrypted($pass);
if (mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
)
) {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
} else {
    $res = "Impossible to get connected to server. Error is: ".addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error()).'!"}]';
    mysqli_close($db_link);
    exit();
}

// 2.1.27 check with DEFUSE
// check if library defuse already on-going here
// if yes, then don't execute re-encryption
if (isset($_SESSION['tp_defuse_installed']) !== true) {
    $_SESSION['tp_defuse_installed'] = false;
    $columns = mysqli_query(
        $db_link,
        "show columns from ".$pre."items"
    );
    while ($c = mysqli_fetch_assoc($columns)) {
        if ($c['Field'] === "encryption_type") {
            $_SESSION['tp_defuse_installed'] = true;
        }
    }
}

## Populate table MISC
$val = array(
    array('admin', 'max_latest_items', '10', 0),
    array('admin', 'enable_favourites', '1', 0),
    array('admin', 'show_last_items', '1', 0),
    array('admin', 'enable_pf_feature', '0', 0),
    array('admin', 'menu_type', 'context', 0),
    array('admin', 'log_connections', '0', 0),
    array('admin', 'time_format', 'H:i:s', 0),
    array('admin', 'date_format', 'd/m/Y', 0),
    array('admin', 'duplicate_folder', '0', 0),
    array('admin', 'duplicate_item', '0', 0),
    array('admin', 'item_duplicate_in_same_folder', '0', 0),
    array('admin', 'number_of_used_pw', '3', 0),
    array('admin', 'manager_edit', '1', 0),
    array('admin', 'cpassman_dir', '', 0),
    array('admin', 'cpassman_url', '', 0),
    array('admin', 'favicon', '', 0),
    array('admin', 'activate_expiration', '0', 0),
    array('admin', 'pw_life_duration', '30', 0),
    //array('admin', 'maintenance_mode','1',1),
    array('admin', 'cpassman_version', $SETTINGS_EXT['version'], 1),
    array('admin', 'ldap_mode', '0', 0),
    array('admin', 'ldap_type', '0', 0),
    array('admin', 'ldap_suffix', '0', 0),
    array('admin', 'ldap_domain_dn', '0', 0),
    array('admin', 'ldap_domain_controler', '0', 0),
    array('admin', 'ldap_user_attribute', '0', 0),
    array('admin', 'ldap_ssl', '0', 0),
    array('admin', 'ldap_tls', '0', 0),
    array('admin', 'ldap_elusers', '0', 0),
    array('admin', 'richtext', 0, 0),
    array('admin', 'allow_print', 0, 0),
    array('admin', 'roles_allowed_to_print', 0, 0),
    array('admin', 'show_description', 1, 0),
    array('admin', 'anyone_can_modify', 0, 0),
    array('admin', 'anyone_can_modify_bydefault', 0, 0),
    array('admin', 'nb_bad_authentication', 0, 0),
    array('admin', 'restricted_to', 0, 0),
    array('admin', 'restricted_to_roles', 0, 0),
    array('admin', 'utf8_enabled', 1, 0),
    array('admin', 'custom_logo', '', 0),
    array('admin', 'custom_login_text', '', 0),
    array('admin', 'log_accessed', '1', 1),
    array('admin', 'default_language', 'english', 0),
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
            (strlen($_SERVER['DOCUMENT_ROOT']) - 1).substr(
                $_SERVER['PHP_SELF'],
                0,
                strlen($_SERVER['PHP_SELF']) - 25
            ).'/upload'
        :
        $_SERVER['DOCUMENT_ROOT'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strlen($_SERVER['PHP_SELF']) - 25
        ).'/upload',
        0
    ),
    array(
        'admin',
        'url_to_upload_folder',
        'http://'.$_SERVER['HTTP_HOST'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strrpos($_SERVER['PHP_SELF'], '/') - 8
        ).'/upload',
        0
    ),
    array('admin', 'enable_personal_saltkey_cookie', '0', 0),
    array('admin', 'personal_saltkey_cookie_duration', '31', 0),
    array(
        'admin',
        'path_to_files_folder',
        strrpos($_SERVER['DOCUMENT_ROOT'], "/") == 1 ?
        (strlen($_SERVER['DOCUMENT_ROOT']) - 1).substr(
            $_SERVER['PHP_SELF'],
            0,
            strlen($_SERVER['PHP_SELF']) - 25
        ).'/files'
        :
        $_SERVER['DOCUMENT_ROOT'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strlen($_SERVER['PHP_SELF']) - 25
        ).'/files',
        0
    ),
    array(
        'admin',
        'url_to_files_folder',
        'http://'.$_SERVER['HTTP_HOST'].substr(
            $_SERVER['PHP_SELF'],
            0,
            strrpos($_SERVER['PHP_SELF'], '/') - 8
        ).'/files',
        0
    ),
    array('admin', 'pwd_maximum_length', '40', 0),
    array('admin', 'ga_website_name', 'TeamPass for ChangeMe', 0),
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
    array('admin', 'allow_import', 0, 0),
    array('admin', 'proxy_port', 0, 0),
    array('admin', 'proxy_port', 0, 0),
    array('admin', 'upload_maxfilesize', '10mb', 0),
    array(
        'admin',
        'upload_docext',
        'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx',
        0
    ),
    array('admin', 'upload_imagesext', 'jpg,jpeg,gif,png', 0),
    array('admin', 'upload_pkgext', '7z,rar,tar,zip', 0),
    array('admin', 'upload_otherext', 'sql,xml', 0),
    array('admin', 'upload_imageresize_options', '1', 0),
    array('admin', 'upload_imageresize_width', '800', 0),
    array('admin', 'upload_imageresize_height', '600', 0),
    array('admin', 'upload_imageresize_quality', '90', 0),
    array('admin', 'enable_send_email_on_user_login', '0', 0),
    array('admin', 'enable_user_can_create_folders', '0', 0),
    array('admin', 'insert_manual_entry_item_history', '0', 0),
    array('admin', 'enable_kb', '0', 0),
    array('admin', 'enable_email_notification_on_item_shown', '0', 0),
    array('admin', 'enable_email_notification_on_user_pw_change', '0', 0),
    array('admin', 'enable_sts', '0', 0),
    array('admin', 'encryptClientServer', '1', 0),
    array('admin', 'use_md5_password_as_salt', '0', 0),
    array('admin', 'api', '0', 0),
    array('admin', 'subfolder_rights_as_parent', '0', 0),
    array('admin', 'show_only_accessible_folders', '0', 0),
    array('admin', 'enable_suggestion', '0', 0),
    array('admin', 'email_server_url', '', 0),
    array('admin', 'otv_expiration_period', '7', 0),
    array('admin', 'default_session_expiration_time', '60', 0),
    array('admin', 'duo', '0', 0),
    array('admin', 'enable_server_password_change', '0', 0),
    array('admin', 'bck_script_path', $_SESSION['abspath']."/backups", 0),
    array('admin', 'bck_script_filename', 'bck_cpassman', 0)
);
$res1 = "na";
foreach ($val as $elem) {
    //Check if exists before inserting
    $queryRes = mysqli_query(
        $db_link,
        "SELECT COUNT(*) FROM ".$pre."misc
        WHERE type='".$elem[0]."' AND intitule='".$elem[1]."'"
    );
    if (mysqli_error($db_link)) {
        echo '[{"finish":"1", "msg":"", "error":"MySQL Error! Last input is "'.$elem[1].' - '.
            addslashes($queryRes).'"}]';
        exit();
    } else {
        $resTmp = mysqli_fetch_row($queryRes);
        if ($resTmp[0] === 0) {
            $queryRes = mysqli_query(
                $db_link,
                "INSERT INTO `".$pre."misc`
                (`type`, `intitule`, `valeur`) VALUES
                ('".$elem[0]."', '".$elem[1]."', '".
                str_replace("'", "", $elem[2])."');"
            );
            if (mysqli_error($db_link)) {
                echo '[{"finish":"1", "msg":"", "error":"MySQL Error1! '.addslashes(mysqli_error($db_link)).'"}]';
                exit();
            }
        } else {
            // Force update for some settings
            if ($elem[3] === 1) {
                $queryRes = mysqli_query(
                    $db_link,
                    "UPDATE `".$pre."misc`
                    SET `valeur` = '".$elem[2]."'
                    WHERE `type` = '".$elem[0]."' AND `intitule` = '".$elem[1]."'"
                );
                if (mysqli_error($db_link)) {
                    echo '[{"finish":"1", "msg":"", "error":"MySQL Error2! '.addslashes(mysqli_error($db_link)).'"}]';
                    exit();
                }
            }
        }
    }
}


## Alter ITEMS table
$res2 = addColumnIfNotExist(
    $pre."items",
    "anyone_can_modify",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "email",
    "VARCHAR(100) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "notification",
    "VARCHAR(250) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "viewed_no",
    "INT(12) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "complexity_level",
    "varchar(2) NOT null DEFAULT '-1'"
);
$res2 = addColumnIfNotExist(
    $pre."roles_values",
    "type",
    "VARCHAR(5) NOT NULL DEFAULT 'R'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "upgrade_needed",
    "BOOLEAN NOT NULL DEFAULT FALSE"
);

$res2 = addIndexIfNotExist($pre.'items', 'restricted_inactif_idx', 'ADD INDEX `restricted_inactif_idx` (`restricted_to`,`inactif`)');

# Alter tables
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."log_items MODIFY id_user INT(8)"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."restriction_to_roles MODIFY role_id INT(12)"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."restriction_to_roles MODIFY item_id INT(12)"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."items MODIFY pw TEXT"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users MODIFY pw VARCHAR(400)"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."cache CHANGE `login` `login` VARCHAR( 200 ) CHARACTER NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."log_system CHANGE `field_1` `field_1` VARCHAR( 250 ) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."keys CHANGE `table` `sql_table` VARCHAR( 25 ) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users MODIFY `key_tempo` varchar(100) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."categories CHANGE `type` `type` varchar(50) NULL default ''"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."categories CHANGE `order` `order` int(12) NOT NULL default '0'"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `derniers` `derniers` text NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `key_tempo` `key_tempo` varchar(100) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `last_pw_change` `last_pw_change` varchar(30) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `last_pw` `last_pw` text NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `fonction_id` `fonction_id` varchar(255) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `groupes_interdits` `groupes_interdits` varchar(255) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `last_connexion` `last_connexion` varchar(30) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `favourites` `favourites` varchar(300) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `latest_items` `latest_items` varchar(300) NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `avatar` `avatar` varchar(255) NOT null DEFAULT ''"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."users CHANGE `avatar_thumb` `avatar_thumb` varchar(255) NOT null DEFAULT ''"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."log_items CHANGE `raison` `raison` text NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."log_items CHANGE `raison_iv` `raison_iv` text NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."roles_values CHANGE `type` `type` VARCHAR( 5 ) NOT NULL DEFAULT 'R'"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."suggestion CHANGE `suggestion_key` `pw_iv` TEXT NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."suggestion CHANGE `key` `pw_iv` TEXT NULL"
);
mysqli_query(
    $db_link,
    "ALTER TABLE ".$pre."suggestion CHANGE `password` `pw` TEXT NULL"
);

## Alter USERS table
$res2 = addColumnIfNotExist(
    $pre."users",
    "favourites",
    "VARCHAR(300)"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "latest_items",
    "VARCHAR(300)"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "personal_folder",
    "INT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "disabled",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "no_bad_attempts",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "can_create_root_folder",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "read_only",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "timestamp",
    "VARCHAR(30) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "user_language",
    "VARCHAR(30) NOT null DEFAULT 'english'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "name",
    "VARCHAR(100) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "lastname",
    "VARCHAR(100) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "session_end",
    "VARCHAR(30) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "isAdministratedByRole",
    "TINYINT(5) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "psk",
    "VARCHAR(400) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "ga",
    "VARCHAR(50) DEFAULT NULL"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "avatar",
    "VARCHAR(255) NOT null DEFAULT ''"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "avatar_thumb",
    "VARCHAR(255) NOT null DEFAULT ''"
);
$res2 = addColumnIfNotExist(
    $pre."users",
    "treeloadstrategy",
    "VARCHAR(30) NOT null DEFAULT 'full'"
);

$res2 = addColumnIfNotExist(
    $pre."log_items",
    "raison_iv",
    "TEXT null"
);
$res2 = addColumnIfNotExist(
    $pre."categories_items",
    "data_iv",
    "TEXT NOT null"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "pw_iv",
    "TEXT NOT null"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "pw_len",
    "INT(5) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "auto_update_pwd_frequency",
    "TINYINT(2) NOT NULL DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."items",
    "auto_update_pwd_next_date",
    "INT(15) NOT NULL DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."cache",
    "renewal_period",
    "TINYINT(4) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."suggestion",
    "pw_len",
    "int(5) NOT null DEFAULT '0'"
);

// Clean timestamp for users table
mysqli_query($db_link, "UPDATE ".$pre."users SET timestamp = ''");

## Alter nested_tree table
$res2 = addColumnIfNotExist(
    $pre."nested_tree",
    "personal_folder",
    "TINYINT(1) NOT null DEFAULT '0'"
);
$res2 = addColumnIfNotExist(
    $pre."nested_tree",
    "renewal_period",
    "TINYINT(4) NOT null DEFAULT '0'"
);

addIndexIfNotExist(
    $pre.'nested_tree',
    'personal_folder_idx',
    'ADD INDEX `personal_folder_idx` (`personal_folder`)'
);


#to 1.08
//include('upgrade_db_1.08.php');

## TABLE TAGS
$res8 = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."tags` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `tag` varchar(30) NOT NULL,
    `item_id` int(12) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `id` (`id`)
    );"
);
if (mysqli_error($db_link)) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table TAGS! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE LOG_SYSTEM
$res8 = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."log_system` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `type` varchar(20) NOT NULL,
    `date` varchar(30) NOT NULL,
    `label` text NOT NULL,
    `qui` varchar(30) NOT NULL,
    PRIMARY KEY (`id`)
    );"
);
if (empty(mysqli_error($db_link)) === true) {
    mysqli_query(
        $db_link,
        "ALTER TABLE ".$pre."log_system
        ADD `field_1` VARCHAR(250) NOT NULL"
    );
} else {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table LOG_SYSTEM! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE 10 - FILES
$res9 = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."files` (
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
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table FILES! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."files`
    CHANGE id id INT(11) AUTO_INCREMENT PRIMARY KEY;"
);
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."files`
    CHANGE name name VARCHAR(100) NOT NULL;"
);

## TABLE CACHE
mysqli_query($db_link, "DROP TABLE IF EXISTS `".$pre."cache`");
$res8 = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."cache` (
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
if (empty(mysqli_error($db_link)) === true) {
    //ADD VALUES
    $sql = "SELECT *
            FROM ".$pre."items as i
            INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
            AND l.action = 'at_creation'
            WHERE i.inactif=0";
    $rows = mysqli_query($db_link, $sql);
    while ($reccord = mysqli_fetch_array($rows)) {
        //Get all TAGS
        $tags = "";
        $itemsRes = mysqli_query(
            $db_link,
            "SELECT tag FROM ".$pre."tags
            WHERE item_id=".$reccord['id']
        ) or die(mysqli_error($db_link));
        $itemTags = mysqli_fetch_array($itemsRes);
        if (!empty($itemTags)) {
            foreach ($itemTags as $itemTag) {
                if (!empty($itemTag['tag'])) {
                    $tags .= $itemTag['tag']." ";
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
        mysqli_query(
            $db_link,
            "INSERT INTO ".$pre."cache
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
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table CACHE! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

/*
   *  Change table FUNCTIONS
   *  By 2 tables ROLES
*/
$res9 = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."roles_title` (
    `id` int(12) NOT NULL,
    `title` varchar(50) NOT NULL,
    `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
    `complexity` INT(5) NOT null DEFAULT '0',
    `creator_id` int(11) NOT null DEFAULT '0'
    );"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table roles_title! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}
addColumnIfNotExist(
    $pre."roles_title",
    "allow_pw_change",
    "TINYINT(1) NOT null DEFAULT '0'"
);
addColumnIfNotExist(
    $pre."roles_title",
    "complexity",
    "INT(5) NOT null DEFAULT '0'"
);
addColumnIfNotExist(
    $pre."roles_title",
    "creator_id",
    "INT(11) NOT null DEFAULT '0'"
);

$res10 = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."roles_values` (
    `role_id` int(12) NOT NULL,
    `folder_id` int(12) NOT NULL
    );"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table roles_values! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}
if (tableExists($pre."functions")) {
    $tableFunctionExists = true;
} else {
    $tableFunctionExists = false;
}
if ($tableFunctionExists === true) {
    //Get data from tables FUNCTIONS and populate new ROLES tables
    $rows = mysqli_query(
        $db_link,
        "SELECT * FROM ".$pre."functions"
    );
    while ($reccord = mysqli_fetch_array($rows)) {
        //Add new role title
        mysqli_query(
            $db_link,
            "INSERT INTO ".$pre."roles_title
            VALUES (
                '".$reccord['id']."',
                '".$reccord['title']."'
           )"
        );

        //Add each folder in roles_values
        foreach (explode(';', $reccord['groupes_visibles']) as $folderId) {
            if (!empty($folderId)) {
                mysqli_query(
                    $db_link,
                    "INSERT INTO ".$pre."roles_values
                    VALUES (
                    '".$reccord['id']."',
                    '".$folderId."'
                   )"
                );
            }
        }
    }

    //Now alter table roles_title in order to create a primary index
    mysqli_query(
        $db_link,
        "ALTER TABLE `".$pre."roles_title`
        ADD PRIMARY KEY(`id`)"
    );
    mysqli_query(
        $db_link,
        "ALTER TABLE `".$pre."roles_title`
        CHANGE `id` `id` INT(12) NOT null AUTO_INCREMENT "
    );
    addColumnIfNotExist(
        $pre."roles_title",
        "allow_pw_change",
        "TINYINT(1) NOT null DEFAULT '0'"
    );

    //Drop old table
    mysqli_query($db_link, "DROP TABLE ".$pre."functions");
}

## TABLE KB
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."kb` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `category_id` int(12) NOT NULL,
    `label` varchar(200) NOT NULL,
    `description` text NOT NULL,
    `author_id` int(12) NOT NULL,
    `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
    PRIMARY KEY (`id`)
    );"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table KB! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE KB_CATEGORIES
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."kb_categories` (
    `id` int(12) NOT null AUTO_INCREMENT,
    `category` varchar(50) NOT NULL,
    PRIMARY KEY (`id`)
    );"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table KB_CATEGORIES! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE KB_ITEMS
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."kb_items` (
    `kb_id` tinyint(12) NOT NULL,
    `item_id` tinyint(12) NOT NULL
     );"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table KB_ITEMS! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE restriction_to_roles
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."restriction_to_roles` (
    `role_id` tinyint(12) NOT NULL,
    `item_id` tinyint(12) NOT NULL
    ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table RESTRICTION_TO_ROLES! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
} else {
    $res = addIndexIfNotExist($pre.'restriction_to_roles', 'role_id_idx', 'ADD INDEX `role_id_idx` (`role_id`)');
}

## TABLE Languages
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."languages` (
    `id` INT(10) NOT null AUTO_INCREMENT PRIMARY KEY ,
    `name` VARCHAR(50) NOT null ,
    `label` VARCHAR(50) NOT null ,
    `code` VARCHAR(10) NOT null ,
    `flag` VARCHAR(30) NOT NULL
    ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table LANGUAGES! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}
$resTmp = mysqli_fetch_row(
    mysqli_query($db_link, "SELECT COUNT(*) FROM ".$pre."languages")
);
mysqli_query($db_link, "TRUNCATE TABLE ".$pre."languages");
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `".$pre."languages`
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

## TABLE EMAILS
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."emails` (
    `timestamp` INT(30) NOT null ,
    `subject` VARCHAR(255) NOT null ,
    `body` TEXT NOT null ,
    `receivers` VARCHAR(255) NOT null ,
    `status` VARCHAR(30) NOT NULL
    ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table EMAILS! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE AUTOMATIC DELETION
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."automatic_del` (
    `item_id` int(11) NOT NULL,
    `del_enabled` tinyint(1) NOT NULL,
    `del_type` tinyint(1) NOT NULL,
    `del_value` varchar(35) NOT NULL
    ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table AUTOMATIC_DEL! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE items_edition
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."items_edition` (
    `item_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `timestamp` varchar(50) NOT NULL
   ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table items_edition! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE categories
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."categories` (
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
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table CATEGORIES! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE categories_items
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."categories_items` (
    `id` int(12) NOT NULL AUTO_INCREMENT,
    `field_id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `data` text NOT NULL,
    PRIMARY KEY (`id`)
   ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table categories_items! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE categories_folders
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."categories_folders` (
    `id_category` int(12) NOT NULL,
    `id_folder` int(12) NOT NULL
   ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table categories_folders! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE api
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."api` (
    `id` int(20) NOT NULL AUTO_INCREMENT,
    `type` varchar(15) NOT NULL,
    `label` varchar(255) NOT NULL,
    `value` varchar(255) NOT NULL,
    `timestamp` varchar(50) NOT NULL,
    PRIMARY KEY (`id`)
   ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table API! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE otv
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."otv` (
    `id` int(10) NOT NULL AUTO_INCREMENT,
    `timestamp` text NOT NULL,
    `code` varchar(100) NOT NULL,
    `item_id` int(12) NOT NULL,
    `originator` tinyint(12) NOT NULL,
    PRIMARY KEY (`id`)
   ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table OTV! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

## TABLE suggestion
$res = mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."suggestion` (
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
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table SUGGESTIONS! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

# TABLE EXPORT
mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."export` (
    `id` int(12) NOT NULL,
    `label` varchar(255) NOT NULL,
    `login` varchar(100) NOT NULL,
    `description` text NOT NULL,
    `pw` text NOT NULL,
    `path` varchar(255) NOT NULL
    ) CHARSET=utf8;"
);
if (empty(mysqli_error($db_link)) === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears on table export! '.addslashes(mysqli_error($db_link)).'"}]';
    mysqli_close($db_link);
    exit();
}

//CLEAN UP ITEMS TABLE
$allowedTags = '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul>'.
    '<blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>';
$cleanRes = mysqli_query(
    $db_link,
    "SELECT id,description FROM `".$pre."items`"
);
while ($cleanData = mysqli_fetch_array($cleanRes)) {
    mysqli_query(
        $db_link,
        "UPDATE `".$pre."items`
        SET description = '".strip_tags($cleanData['description'], $allowedTags).
        "' WHERE id = ".$cleanData['id']
    );
}

// 2.1.23 - check if personal need to be upgraded
$tmpResult = mysqli_query(
    $db_link,
    "SELECT `pw`, `pw_iv` FROM ".$pre."items WHERE perso='1'"
);
$tmp = mysqli_fetch_row($tmpResult);
if ($tmp[1] === "" && substr($tmp[0], 0, 3) !== "def") {
    mysqli_query($db_link, "UPDATE ".$pre."users SET upgrade_needed = true WHERE 1 = 1");
}

/*// Since 2.1.17, encrypt process is changed.
// Previous PW need to be re-encrypted
if (@mysqli_query(
    $db_link,
    "SELECT valeur FROM ".$pre."misc
    WHERE type='admin' AND intitule = 'encryption_protocol'"
)) {
    $tmpResult = mysqli_query(
    $db_link,
        "SELECT valeur FROM ".$pre."misc
        WHERE type='admin' AND intitule = 'encryption_protocol'"
    );
    $tmp = mysqli_fetch_row($tmpResult);
    if ($tmp[0] != "ctr") {
        //count elem
        $res = mysqli_query(
    $db_link,
            "SELECT COUNT(*) FROM ".$pre."items
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

mysqli_close($db_link);

echo '[{"finish":"1", "msg":"Database has been populated with Original Data.", "error":""}]';
