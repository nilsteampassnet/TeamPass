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


//include librairies
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

$_SESSION['settings']['loaded'] = "";
//define pbkdf2 iteration count
@define('ITCOUNT', '2072');
$return_error = "";
$res = "";


//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $pre.'nested_tree',
    'id',
    'parent_id',
    'title'
);


// Prepare POST variables
$post_no_maintenance_mode = filter_input(INPUT_POST, 'no_maintenance_mode', FILTER_SANITIZE_NUMBER_INT);
$post_index = filter_input(INPUT_POST, 'index', FILTER_SANITIZE_NUMBER_INT);
$post_multiple = filter_input(INPUT_POST, 'multiple', FILTER_SANITIZE_STRING);

// DataBase
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

// Load libraries
require_once '../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Set Session
$superGlobal->put("db_encoding", "utf8", "SESSION");
$_SESSION['settings']['loaded'] = "";
$superGlobal->put("fullurl", $post_fullurl, "SESSION");
$superGlobal->put("abspath", $abspath, "SESSION");

// Get Sessions
$session_tp_defuse_installed = $superGlobal->get("tp_defuse_installed", "SESSION");

/**
 * Function permits to get the value from a line
 * @param  string $val [description]
 * @return string      [description]
 */
function getSettingValue($val)
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";") - 1)));
}

/**
 * Function permits to check if a column exists, and if not to add it
 * @param string $db         [description]
 * @param string $column     [description]
 * @param string $columnAttr [description]
 */
function addColumnIfNotExist($db, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $db_link;
    $exists = false;
    $columns = mysqli_query($db_link, "show columns from $db");
    while ($c = mysqli_fetch_assoc($columns)) {
        if ($c['Field'] == $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists) {
        return mysqli_query($db_link, "ALTER TABLE `$db` ADD `$column`  $columnAttr");
    } else {
        return false;
    }
}

function addIndexIfNotExist($table, $index, $sql)
{
    global $db_link;

    $mysqli_result = mysqli_query($db_link, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query($db_link, "ALTER TABLE `$table` ".$sql);
    }

    return $res;
}

function tableExists($tablename)
{
    global $db_link;

    $res = mysqli_query(
        $db_link,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$_SESSION['db_bdd']."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) {
        return true;
    } else {
        return false;
    }
}

function cleanFields($txt)
{
    $tmp = str_replace(",", ";", trim($txt));
    if (empty($tmp)) {
        return $tmp;
    }
    if ($tmp === ";") {
        return "";
    }
    if (strpos($tmp, ';') === 0) {
        $tmp = substr($tmp, 1);
    }
    if (substr($tmp, -1) !== ";") {
        $tmp = $tmp.";";
    }
    return $tmp;
}

// 2.1.27 introduce new encryption protocol with DEFUSE library.
// Now evaluate if current instance has already this version
$tmp = mysqli_fetch_row(mysqli_query($db_link, "SELECT valeur FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'teampass_version'"));
if (count($tmp[0]) === 0 || empty($tmp[0])) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'teampass_version', '".$SETTINGS_EXT['version']."')"
    );
} else {
    mysqli_query(
        $db_link,
        "UPDATE `".$pre."misc`
        SET `valeur` = '".$SETTINGS_EXT['version']."'
        WHERE intitule = 'teampass_version' AND type = 'admin'"
    );
}

// add new admin setting "migration_to_2127"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'migration_to_2127'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'migration_to_2127', '0')"
    );
}


// check if library defuse already on-going here
// if yes, then don't execute re-encryption
if (isset($session_tp_defuse_installed) !== true) {
    $superGlobal->put("tp_defuse_installed", false, "SESSION");
    $columns = mysqli_query($db_link, "show columns from ".$pre."items");
    while ($c = mysqli_fetch_assoc($columns)) {
        if ($c['Field'] === "encryption_type") {
            $superGlobal->put("tp_defuse_installed", true, "SESSION");
        }
    }
}

// alter table Items
mysqli_query($db_link, "ALTER TABLE `".$pre."items` MODIFY pw_len INT(5) NOT NULL DEFAULT '0'");

// alter table misc to add an index
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."misc` ADD `id` INT(12) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)"
);

// alter table misc to add an index
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."log_items` ADD `increment_id` INT(12) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`increment_id`)"
);

// add field agses-usercardid to Users table
$res = addColumnIfNotExist(
    $pre."users",
    "agses-usercardid",
    "VARCHAR(12) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field agses-usercardid to table Users! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// add field encrypted_data to Categories table
$res = addColumnIfNotExist(
    $pre."categories",
    "encrypted_data",
    "TINYINT(1) NOT NULL DEFAULT '1'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_data to table categories! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// alter table USERS - user_language
mysqli_query($db_link, "ALTER TABLE `".$pre."users` MODIFY user_language VARCHAR(50) NOT NULL DEFAULT '0'");

// alter table USERS - just ensure correct naming of IsAdministratedByRole
mysqli_query($db_link, "ALTER TABLE `".$pre."users` CHANGE IsAdministratedByRole isAdministratedByRole tinyint(5) NOT NULL DEFAULT '0'");

// alter table OTV
mysqli_query($db_link, "ALTER TABLE `".$pre."otv` CHANGE originator originator int(12) NOT NULL DEFAULT '0'");

// do clean of users table
$fieldsToUpdate = ['groupes_visibles', 'fonction_id', 'groupes_interdits'];
$result = mysqli_query($db_link, "SELECT id, groupes_visibles, fonction_id, groupes_interdits FROM `".$pre."users`");
while ($row = mysqli_fetch_assoc($result)) {
    // check if field contains , instead of ;
    foreach ($fieldsToUpdate as $field) {
        $tmp = cleanFields($row[$field]);
        if ($tmp !== $row[$field]) {
            mysqli_query(
                $db_link,
                "UPDATE `".$pre."users`
                SET `".$field."` = '".$tmp."'
                WHERE id = '".$row['id']."'"
            );
        }
    }
}
mysqli_free_result($result);


// alter table KB_ITEMS
mysqli_query($db_link, "ALTER TABLE `".$pre."kb_items` CHANGE `kb_id` `kb_id` INT(12) NOT NULL");
mysqli_query($db_link, "ALTER TABLE `".$pre."kb_items` CHANGE `item_id` `item_id` INT(12) NOT NULL");


// add field encrypted_data to CATEGORIES table
$res = addColumnIfNotExist(
    $pre."categories",
    "encrypted_data",
    "TINYINT(1) NOT NULL DEFAULT '1'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_data to table CATEGORIES! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}

mysqli_query(
    $db_link,
    "UPDATE `".$pre."misc`
    SET `valeur` = 'maintenance_mode'
    WHERE type = 'admin' AND intitule = '".$post_no_maintenance_mode."'"
);


// add field encryption_type to ITEMS table
$res = addColumnIfNotExist(
    $pre."items",
    "encryption_type",
    "VARCHAR(20) NOT NULL DEFAULT 'not_set'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_type to table ITEMS! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// add field encryption_type to categories_items table
$res = addColumnIfNotExist(
    $pre."categories_items",
    "encryption_type",
    "VARCHAR(20) NOT NULL DEFAULT 'not_set'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_type to table categories_items! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// add field encryption_type to LOG_ITEMS table
$res = addColumnIfNotExist(
    $pre."log_items",
    "encryption_type",
    "VARCHAR(20) NOT NULL DEFAULT 'not_set'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_type to table LOG_ITEMS! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// add field URL to CACHE table
$res = addColumnIfNotExist(
    $pre."cache",
    "encryption_type",
    "VARCHAR(500) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field URL to table CACHE! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// add field timestamp to CACHE table
$res = addColumnIfNotExist(
    $pre."cache",
    "timestamp",
    "VARCHAR(50) DEFAULT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field url to table CACHE! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// add field url to CACHE table
$res = addColumnIfNotExist(
    $pre."cache",
    "url",
    "VARCHAR(500) DEFAULT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field timestamp to table CACHE! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}

//-- generate new DEFUSE key
if (!isset($session_tp_defuse_installed) || $session_tp_defuse_installed === false) {
    $filename = "../includes/config/settings.php";
    $settingsFile = file($filename);
    while (list($key, $val) = each($settingsFile)) {
        if (substr_count($val, 'require_once "') > 0 && substr_count($val, 'sk.php') > 0) {
            $superGlobal->put("sk_file", substr($val, 14, strpos($val, '";') - 14), "SESSION");
            $session_sk_file = $superGlobal->get("sk_file", "SESSION");
        }
    }

    copy(
        SECUREPATH."/teampass-seckey.txt",
        SECUREPATH."/teampass-seckey.txt".'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))).".".time()
    );
    $superGlobal->put("tp_defuse_new_key", true, "SESSION");
    $new_salt = defuse_generate_key();
    file_put_contents(
        SECUREPATH."/teampass-seckey.txt",
        $new_salt
    );
    $superGlobal->put("new_salt", $new_salt, "SESSION");

    // update sk.php file
    copy(
        $session_sk_file,
        $session_sk_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))).".".time()
    );
    $data = file($session_sk_file); // reads an array of lines
    function replace_a_line($data)
    {
        global $new_salt;
        if (stristr($data, "@define('SALT'")) {
            return "";
        }
        return $data;
    }
    $data = array_map('replace_a_line', $data);
    file_put_contents($session_sk_file, implode('', $data));

    //
    //
    //-- users need to perform re-encryption of their personal pwds
    $result = mysqli_query(
        $db_link,
        "SELECT valeur FROM `".$pre."misc` WHERE type='admin' AND intitule='encryption_type'"
    );
    $row = mysqli_fetch_assoc($result);
    if ($row['valeur'] !== "defuse") {
        $result = mysqli_query(
            $db_link,
            "SELECT id FROM `".$pre."users`"
        );
        while ($row_user = mysqli_fetch_assoc($result)) {
            $result_items = mysqli_query(
                $db_link,
                "SELECT i.id AS item_id
                FROM `".$pre."nested_tree` AS n
                INNER JOIN `".$pre."items` AS i ON (i.id_tree = n.id)
                WHERE n.title = ".$row_user['id']
            );
            if (mysqli_num_rows($result_items) > 0) {
                mysqli_query(
                    $db_link,
                    "UPDATE `".$pre."users`
                    SET `upgrade_needed` = '1'
                    WHERE id = ".$row_user['id']
                );
            } else {
                mysqli_query(
                    $db_link,
                    "UPDATE `".$pre."users`
                    SET `upgrade_needed` = '0'
                    WHERE id = ".$row_user['id']
                );
            }
        }

        mysqli_query(
            $db_link,
            "UPDATE `".$pre."misc`
            SET `valeur` = 'defuse'
            WHERE `type`='admin' AND `initule`='encryption_type'"
        );
    }
} else {
    $_SESSION['tp_defuse_new_key'] = false;
}
//--


// add field encrypted_psk to Users table
$res = addColumnIfNotExist(
    $pre."users",
    "encrypted_psk",
    "TEXT NOT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_psk to table Users! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}


// add new admin setting "manager_move_item"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'manager_move_item'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'manager_move_item', '0')"
    );
}

// add new admin setting "create_item_without_password"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'create_item_without_password'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'create_item_without_password', '0')"
    );
}

// add new admin setting "send_statistics_items"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'send_statistics_items'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'send_statistics_items', 'stat_country;stat_users;stat_items;stat_items_shared;stat_folders;stat_folders_shared;stat_admins;stat_managers;stat_ro;stat_mysqlversion;stat_phpversion;stat_teampassversion;stat_languages;stat_kb;stat_suggestion;stat_customfields;stat_api;stat_2fa;stat_agses;stat_duo;stat_ldap;stat_syslog;stat_stricthttps;stat_fav;stat_pf;')"
    );
}

// add new admin setting "send_stats_time"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'send_stats_time'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'send_stats_time', '".(time() - 2592000)."')"
    );
}

// add new admin setting "agses_authentication_enabled"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'agses_authentication_enabled'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'agses_authentication_enabled', '0')"
    );
}

// add new admin setting "timezone"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'timezone'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'timezone', 'UTC')"
    );
}

// add new language "portuges_br"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."languages` WHERE name = 'portuguese_br'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."languages` (`name`, `label`, `code`, `flag`) VALUES ('portuguese_br', 'Portuguese_br', 'pr-bt', 'pr-bt.png')"
    );
}


// alter table USERS to add a new field "ga_temporary_code"
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."users` ADD `ga_temporary_code` VARCHAR(20) NOT NULL DEFAULT 'none' AFTER `ga`;"
);
// alter table USERS to add a new field "user_ip"
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."users` ADD `user_ip` VARCHAR(60) NOT NULL DEFAULT 'none';"
);


// alter table EXPORT to add a new fields
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."export` ADD `email` VARCHAR(500) NOT NULL DEFAULT 'none';"
);
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."export` ADD `url` VARCHAR(500) NOT NULL DEFAULT 'none';"
);
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."export` ADD `kbs` VARCHAR(500) NOT NULL DEFAULT 'none';"
);
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."export` ADD `tags` VARCHAR(500) NOT NULL DEFAULT 'none';"
);

// alter table MISC
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."misc` ADD `id` INT(12) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`);"
);
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."misc` CHANGE valeur valeur VARCHAR(500) NOT NULL DEFAULT 'none'"
);

// alter table ITEMS_CHANGE
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."items_change` CHANGE user_id user_id INT(12) NOT NULL;"
);

// alter table ITEMS
mysqli_query(
    $db_link,
    "ALTER TABLE `".$pre."items` CHANGE auto_update_pwd_next_date auto_update_pwd_next_date VARCHAR(100) NOT NULL DEFAULT '0';"
);


// add new admin setting "otv_is_enabled"
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'otv_is_enabled'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'otv_is_enabled', '0')"
    );
}


// add new field for items_change
mysqli_query(
    $db_link,
    "CREATE TABLE IF NOT EXISTS `".$pre."items_change` (
    `id` int(12) NOT NULL AUTO_INCREMENT,
    `item_id` int(12) NOT NULL,
    `label` varchar(255) NOT NULL DEFAULT 'none',
    `pw` text NOT NULL,
    `login` varchar(255) NOT NULL DEFAULT 'none',
    `email` varchar(255) NOT NULL DEFAULT 'none',
    `url` varchar(255) NOT NULL DEFAULT 'none',
    `description` text NOT NULL,
    `comment` text NOT NULL,
    `folder_id` tinyint(12) NOT NULL,
    `user_id` tinyint(12) NOT NULL,
    `timestamp` varchar(50) NOT NULL DEFAULT 'none',
    PRIMARY KEY (`id`)
    ) CHARSET=utf8;"
);



// File encryption
// add field status to FILE table
$res = addColumnIfNotExist(
    $pre."files",
    "status",
    "VARCHAR(50) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field agses-usercardid to table Users! '.mysqli_error($db_link).'!"}]';
    mysqli_close($db_link);
    exit();
}

// fill in this new field with the current "encryption-file" status
$tmp = mysqli_fetch_row(mysqli_query($db_link, "SELECT valeur FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'enable_attachment_encryption'"));
if (!empty($tmp[0])) {
    if ($tmp[0] === "1") {
        $status = "encrypted";
    } else {
        $status = "clear";
    }
    mysqli_query($db_link, "update `".$pre."files` set status = '".$status."' where 1 = 1");
}


// add 2 generic users
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."users` WHERE id = '9999991' AND login = 'OTV'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`) VALUES ('9999991', 'OTV', '', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0')"
    );
}
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."users` WHERE id = '9999991' AND login = 'OTV'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `".$pre."users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`) VALUES ('9999999', 'API', '', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0')"
    );
}


// Update favico to favicon
$result = mysqli_query($db_link, "SELECT valeur FROM `".$pre."misc` WHERE intitule = 'cpassman_url' AND type = 'admin'");
$rows = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_query(
    $db_link,
    "UPDATE `".$pre."misc`
    SET `valeur` = '".$rows['valeur']."/favicon.ico'
    WHERE intitule = 'favicon' AND type = 'admin'"
);



/*
* Introduce new CONFIG file
*/
$tp_config_file = "../includes/config/tp.config.php";
if (file_exists($tp_config_file)) {
    if (!copy($tp_config_file, $tp_config_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
        echo '[{"error" : "includes/config/tp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
        return false;
    } else {
        unlink($tp_config_file);
    }
}
$file_handler = fopen($tp_config_file, 'w');
$config_text = "<?php
global \$SETTINGS;
\$SETTINGS = array (";

$result = mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin'");
while ($row = mysqli_fetch_assoc($result)) {
    // append new setting in config file
    $config_text .= "
    '".$row['intitule']."' => '".$row['valeur']."',";
}
mysqli_free_result($result);

// write to config file
$result = fwrite(
    $file_handler,
    utf8_encode(
        substr_replace($config_text, "", -1)."
);"
    )
);
fclose($file_handler);


// Encrypt the database password
if (substr($_SESSION['pass'], 0, 3) !== "def") {
    $encrypted_text = cryption($pass, "", "encrypt")['string'];

    $result = '';
    $lines = file('../includes/config/settings.php');
    foreach ($lines as $line) {
        if (substr($line, 0, 9) === '$pass = "') {
            $result .= '$pass = "'.$encrypted_text.'";'."\r\n";
        } else {
            $result .= $line;
        }
    }
    file_put_contents('../includes/config/settings.php', $result);
}

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';
