<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @version   3.0.0.22
 * @file      upgrade_run_3.0.0.php
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
define('DB_PASSWD_CLEAR', defuse_return_decrypted(DB_PASSWD));

//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $pre . 'nested_tree',
    'id',
    'parent_id',
    'title'
);

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

// Set Session
$superGlobal->put('db_encoding', 'utf8', 'SESSION');
$superGlobal->put('fullurl', $post_fullurl, 'SESSION');
$superGlobal->put('abspath', $abspath, 'SESSION');

// Get POST with user info
$post_user_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_STRING)));//print_r($post_user_info);
$userLogin = $post_user_info[0];
$userPassword = Encryption\Crypt\aesctr::decrypt(base64_decode($post_user_info[1]), 'cpm', 128);
$userId = $post_user_info[2];

// Get current version
$queryRes = mysqli_fetch_array(mysqli_query(
    $db_link,
    "SELECT valeur FROM `".$pre."misc` WHERE `type` = 'admin' AND `intitule` = 'cpassman_version';"
));
if (mysqli_error($db_link)) {
    echo '[{"finish":"1", "msg":"", "error":"MySQL Error! '.addslashes(mysqli_error($db_link)).'"}]';
    exit();
}
$CurrentTPversion = $queryRes['valeur'];
if ((int) $CurrentTPversion[0] === 3) {
    $TPIsBranch3 = true;
} else {
    $TPIsBranch3 = false;
}


// Populate table MISC
$val = array(
    array('admin', 'cpassman_version', TP_VERSION_FULL, 1),
    $TPIsBranch3 === true ? '' : array('admin', 'ldap_mode', 0, 1), // only disable if migrating from branch 2
);
foreach ($val as $elem) {
    if ($elem === '') continue;
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

// Add field public_key to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'public_key',
    "TEXT DEFAULT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field public_key to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field private_key to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'private_key',
    "TEXT DEFAULT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field private_key to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field special to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'special',
    "VARCHAR(250) NOT NULL DEFAULT 'none'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field special to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field auth_type to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'auth_type',
    "VARCHAR(200) NOT NULL DEFAULT 'local'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field auth_type to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field encryption_type to SUGGESTION table
$res = addColumnIfNotExist(
    $pre . 'suggestion',
    'encryption_type',
    "VARCHAR(20) NOT NULL DEFAULT 'not_set'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_type to table SUGGESTION! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add new table SHAREKEYS ITEMS
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'sharekeys_items` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `object_id` int(12) NOT NULL,
        `user_id` int(12) NOT NULL,
        `share_key` text NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Add new table SHAREKEYS LOGS
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'sharekeys_logs` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `object_id` int(12) NOT NULL,
        `user_id` int(12) NOT NULL,
        `share_key` text NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Add new table SHAREKEYS FIELDS
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'sharekeys_fields` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `object_id` int(12) NOT NULL,
        `user_id` int(12) NOT NULL,
        `share_key` text NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Add new table SHAREKEYS SUGGESTIONS
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'sharekeys_suggestions` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `object_id` int(12) NOT NULL,
        `user_id` int(12) NOT NULL,
        `share_key` text NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Add new table SHAREKEYS FILES
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'sharekeys_files` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `object_id` int(12) NOT NULL,
        `user_id` int(12) NOT NULL,
        `share_key` text NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Add new table defuse_passwords
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'defuse_passwords` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `type` varchar(100) NOT NULL,
        `object_id` int(12) NOT NULL,
        `password` text NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Add new table Notifications
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'notification` (
        `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
        `item_id` INT(12) NOT NULL,
        `user_id` INT(12) NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Alter table FILES
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'files` CHANGE `name` `name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;'
);

// Alter table CACHE
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'cache` CHANGE `folder` `folder` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;'
);

// Add field confirmed to FILES table
$res = addColumnIfNotExist(
    $pre . 'files',
    'confirmed',
    "INT(1) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field Confirmed to table FILES! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
} else {
    // Update all existing entries
    mysqli_query(
        $db_link,
        'UPDATE ' . $pre . "files
        SET confirmed = '1'"
    );
}

// Add field encrypted to OTV table
$res = addColumnIfNotExist(
    $pre . 'otv',
    'encrypted',
    "text NOT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field Encrypted to table OTV! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}


// Force attachment encryption 'enable_attachment_encryption'
mysqli_query(
    $db_link,
    'UPDATE ' . $pre . "misc
    SET valeur = '1'
    WHERE type = 'admin' AND intitule = 'enable_attachment_encryption'"
);

// Add new setting 'password_overview_delay'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'password_overview_delay'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'password_overview_delay', '4')"
    );
}

// Add new setting 'roles_allowed_to_print_select'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'roles_allowed_to_print_select'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'roles_allowed_to_print_select', '')"
    );
}

// Add new setting 'clipboard_life_duration'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'clipboard_life_duration'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'clipboard_life_duration', '30')"
    );
}

// Add new setting 'mfa_for_roles'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'mfa_for_roles'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'mfa_for_roles', '')"
    );
}

// Add new setting 'tree_counters'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'tree_counters'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'tree_counters', '0')"
    );
}

// Add new setting 'settings_offline_mode'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'settings_offline_mode'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'settings_offline_mode', '0')"
    );
}

// Add new setting 'settings_tree_counters'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'settings_tree_counters'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'settings_tree_counters', '0')"
    );
}

// Add new setting 'copy_to_clipboard_small_icons'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'copy_to_clipboard_small_icons'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'copy_to_clipboard_small_icons', '0')"
    );
}

// Add new setting 'enable_massive_move_delete'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'enable_massive_move_delete'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'enable_massive_move_delete', '0')"
    );
}

// Convert the roles_allowed_to_print value to an array
$roles_allowed_to_print = mysqli_fetch_row(mysqli_query(
    $db_link,
    'SELECT valeur
    FROM ' . $pre . 'misc
    WHERE type = "admin" AND intitule = "roles_allowed_to_print"'
));
if ($roles_allowed_to_print[0] !== null && empty($roles_allowed_to_print[0]) === false) {
    mysqli_query(
        $db_link,
        'UPDATE ' . $pre . "misc
        SET valeur = '" . json_encode(explode(';', $roles_allowed_to_print[0])) . "'
        WHERE type = 'admin' AND intitule = 'roles_allowed_to_print'"
    );
}

// Copy all items passwords
$db_count = mysqli_fetch_row(
    mysqli_query(
        $db_link,
        'SELECT count(*) FROM ' . $pre . 'defuse_passwords'
    )
);
if ((int) $db_count[0] === 0) {
    // Copy defuse strings into temporary table
    $rows = mysqli_query(
        $db_link,
        'SELECT id, pw, encryption_type
        FROM ' . $pre . 'items
        WHERE perso = 0'
    );
    if (!$rows) {
        echo '[{"finish":"1" , "error":"' . mysqli_error($db_link) . '"}]';
        exit();
    }
    while ($data = mysqli_fetch_array($rows)) {
        if ($data['encryption_type'] !== 'teampass_aes') {
            mysqli_query(
                $db_link,
                "INSERT INTO `" . $pre . "defuse_passwords` (`increment_id`, `type`, `object_id`, `password`) 
                VALUES (NULL, 'item', '" . $data['id'] . "', '" . $data['pw'] . "');"
            );
        }
    }

    // Copy all fields
    $rows = mysqli_query(
        $db_link,
        'SELECT id, data, encryption_type
        FROM ' . $pre . 'categories_items'
    );
    if (!$rows) {
        echo '[{"finish":"1" , "error":"' . mysqli_error($db_link) . '"}]';
        exit();
    }

    while ($data = mysqli_fetch_array($rows)) {
        if ($data['encryption_type'] !== 'teampass_aes') {
            mysqli_query(
                $db_link,
                "INSERT INTO `" . $pre . "defuse_passwords` (`increment_id`, `type`, `object_id`, `password`) 
                VALUES (NULL, 'field', '" . $data['id'] . "', '" . $data['data'] . "');"
            );
        }
    }

    // Copy all logs
    $rows = mysqli_query(
        $db_link,
        'SELECT increment_id, raison, encryption_type
        FROM ' . $pre . "log_items
        WHERE raison LIKE 'at_pw :def%'"
    );
    if (!$rows) {
        echo '[{"finish":"1" , "error":"' . mysqli_error($db_link) . '"}]';
        exit();
    }

    while ($data = mysqli_fetch_array($rows)) {
        if ($data['encryption_type'] !== 'teampass_aes') {
            mysqli_query(
                $db_link,
                "INSERT INTO `" . $pre . "defuse_passwords` (`increment_id`, `type`, `object_id`, `password`) 
                VALUES (NULL, 'log', '" . $data['increment_id'] . "', '" . explode('pw :', $data['raison'])[1] . "');"
            );
        }
    }

    // Copy all suggestion
    $rows = mysqli_query(
        $db_link,
        'SELECT id, pw
        FROM ' . $pre . 'suggestion'
    );
    if (!$rows) {
        echo '[{"finish":"1" , "error":"' . mysqli_error($db_link) . '"}]';
        exit();
    }

    while ($data = mysqli_fetch_array($rows)) {
        if ($data['encryption_type'] !== 'teampass_aes') {
            mysqli_query(
                $db_link,
                "INSERT INTO `" . $pre . "defuse_passwords` (`increment_id`, `type`, `object_id`, `password`) 
                VALUES (NULL, 'suggestion', '" . $data['id'] . "', '" . $data['pw'] . "');"
            );
        }
    }

    // Copy all files to "defuse' folder
    $mydir = $SETTINGS['path_to_upload_folder'] . '/';
    if (!is_dir($mydir . 'defuse')) {
        mkdir($mydir . 'defuse');
    }
    // Move all images files
    $files = glob($mydir . '*');
    foreach ($files as $file) {
        $file_to_go = str_replace($mydir, $mydir . 'defuse/', $file);
        copy($file, $file_to_go);
    }
}

// Generate keys pair for the admin
if (
    isset($userPassword) === false || empty($userPassword) === true
    || isset($userLogin) === false || empty($userLogin) === true
) {
    echo '[{"finish":"1", "msg":"", "error":"Error - The user is not identified! Please restart upgrade."}]';
    exit();
} else {
    // Get user info
    $user = mysqli_fetch_array(
        mysqli_query(
            $db_link,
            'SELECT id, public_key, private_key
            FROM ' . $pre . "users
            WHERE login='" . $userLogin . "'"
        )
    );
    if (
        isset($user['id']) === true && empty($user['id']) === false
        && (is_null($user['private_key']) === true || empty($user['private_key']) === true || $user['private_key'] === 'none')
    ) {
        $userKeys = generateUserKeys($userPassword);

        // Store in DB
        mysqli_query(
            $db_link,
            'UPDATE ' . $pre . "users
            SET public_key = '" . $userKeys['public_key'] . "', private_key = '" . $userKeys['private_key'] . "'
            WHERE id = " . $user['id']
        );

        // Store fact that admin has migrated
        /*mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "defuse_to_aes_migration` (`increment_id`, `user_id`, `rebuild_performed`, `timestamp`) 
            VALUES (NULL, '" . $user['id'] . "', 'done', '" . date_timestamp_get(date_create()) . "');"
        );*/
    } elseif (isset($user['id']) === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error - User not found in DB! Please restart upgrade."}]';
        exit();
    }
}

//---> 3.0.0.10
// no action on DB
//---<


//---> 3.0.0.11
$res = addColumnIfNotExist(
    $pre . 'nested_tree',
    'fa_icon',
    "VARCHAR(100) NOT NULL DEFAULT 'fas fa-folder'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field fa_icon to table NESTED_TREE! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

$res = addColumnIfNotExist(
    $pre . 'nested_tree',
    'fa_icon_selected',
    "VARCHAR(100) NOT NULL DEFAULT 'fas fa-folder-open'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field fa_icon_selected to table NESTED_TREE! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

$res = addColumnIfNotExist(
    $pre . 'items',
    'fa_icon',
    "VARCHAR(100) DEFAULT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field fa_icon to table ITEMS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add new setting 'email_debug_level'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'email_debug_level'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'email_debug_level', '0')"
    );
}
//---<


//---> 3.0.0.13
mysqli_query($db_link, "UPDATE `" . $pre . "nested_tree` SET `fa_icon` = 'fas fa-folder'  WHERE fa_icon = 'fa-folder'");
mysqli_query($db_link, "UPDATE `" . $pre . "nested_tree` SET `fa_icon_selected` = 'fas fa-folder-open'  WHERE fa_icon_selected = 'fa-folder-open'");

// Alter table nested_tree
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "nested_tree` CHANGE `fa_icon` `fa_icon` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'fas fa-folder';"
);
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "nested_tree` CHANGE `fa_icon_selected` `fa_icon_selected` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'fas fa-folder-open';"
);
//---<


//---> 3.0.0.14
mysqli_query($db_link, "UPDATE `" . $pre . "misc` SET `intitule` = 'ldap_user_dn_attribute'  WHERE intitule = 'settings_ldap_user_dn_attribute'");
mysqli_query($db_link, "DELETE FROM `" . $pre . "misc` WHERE `intitule` = 'ldap-test-config-username' AND type = 'admin'");
mysqli_query($db_link, "DELETE FROM `" . $pre . "misc` WHERE `intitule` = 'ldap-test-config-pwd' AND type = 'admin'");

// Manage folder complexity values
$rows = mysqli_query(
    $db_link,
    "SELECT valeur, increment_id
    FROM ".$pre."misc
    WHERE type = 'complex'"
);
while ($data = mysqli_fetch_array($rows)) {
    if ((int) $data['valeur'] > 0 && (int) $data['valeur'] <= TP_PW_STRENGTH_2) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."misc
            SET valeur = '".TP_PW_STRENGTH_2."'
            WHERE increment_id = ".$data['increment_id']
        );
    }
    elseif ((int) $data['valeur'] > TP_PW_STRENGTH_2 && (int) $data['valeur'] <= TP_PW_STRENGTH_3) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."misc
            SET valeur = '".TP_PW_STRENGTH_3."'
            WHERE increment_id = ".$data['increment_id']
        );
    }
    elseif ((int) $data['valeur'] > TP_PW_STRENGTH_3 && (int) $data['valeur'] <= TP_PW_STRENGTH_4) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."misc
            SET valeur = '".TP_PW_STRENGTH_4."'
            WHERE increment_id = ".$data['increment_id']
        );
    }
    elseif ((int) $data['valeur'] > TP_PW_STRENGTH_4) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."misc
            SET valeur = '".TP_PW_STRENGTH_5."'
            WHERE increment_id = ".$data['increment_id']
        );
    }
}

// Manage folder complexity values
$rows = mysqli_query(
    $db_link,
    "SELECT id, complexity
    FROM ".$pre."roles_title"
);
while ($data = mysqli_fetch_array($rows)) {
    if ((int) $data['complexity'] > 0 && (int) $data['complexity'] <= TP_PW_STRENGTH_2) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."roles_title
            SET complexity = '".TP_PW_STRENGTH_2."'
            WHERE id = ".$data['id']
        );
    }
    elseif ((int) $data['complexity'] > TP_PW_STRENGTH_2 && (int) $data['complexity'] <= TP_PW_STRENGTH_3) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."roles_title
            SET complexity = '".TP_PW_STRENGTH_3."'
            WHERE id = ".$data['id']
        );
    }
    elseif ((int) $data['complexity'] > TP_PW_STRENGTH_3 && (int) $data['complexity'] <= TP_PW_STRENGTH_4) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."roles_title
            SET complexity = '".TP_PW_STRENGTH_4."'
            WHERE id = ".$data['id']
        );
    }
    elseif ((int) $data['complexity'] > TP_PW_STRENGTH_4) {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."roles_title
            SET complexity = '".TP_PW_STRENGTH_5."'
            WHERE id = ".$data['id']
        );
    }
}
//---<

//---> 3.0.0.17
mysqli_query($db_link, "UPDATE `" . $pre . "misc` SET `valeur` = '0'  WHERE intitule = 'enable_server_password_change'");

// Add new setting 'ga_reset_by_user'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ga_reset_by_user'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ga_reset_by_user', '')"
    );
}
// Add new setting 'onthefly-backup-key'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'onthefly-backup-key'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'onthefly-backup-key', '')"
    );
}
// Add new setting 'onthefly-restore-key'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'onthefly-restore-key'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'onthefly-restore-key', '')"
    );
}
// Add new setting 'ldap_user_dn_attribute'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_user_dn_attribute'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_user_dn_attribute', '')"
    );
}
// Add new setting 'ldap_dn_additional_user_dn'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_dn_additional_user_dn'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_dn_additional_user_dn', '')"
    );
}
// Add new setting 'ldap_user_object_filter'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_user_object_filter'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_user_object_filter', '')"
    );
}
// Add new setting 'ldap_bdn'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_bdn'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_bdn', '')"
    );
}
// Add new setting 'ldap_hosts'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_hosts'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_hosts', '')"
    );
}
// Add new setting 'ldap_password'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_password'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_password', '')"
    );
}
// Add new setting 'ldap_username'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_username'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_username', '')"
    );
}
// Add new setting 'api_token_duration'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'api_token_duration'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'api_token_duration', '60')"
    );
}

//---< END 3.0.0.17


//---> 3.0.0.18
// Add new value 'last_folder_change' in table misc
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'timestamp' AND intitule = 'last_folder_change'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('timestamp', 'last_folder_change', '')"
    );
}

// Add new table CACHE_TREE
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'cache_tree` (
        `increment_id` int(32) NOT NULL AUTO_INCREMENT,
        `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
        `visible_folders` longtext NOT NULL,
        `timestamp` varchar(50) NOT NULL,
        `user_id` int(12) NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

mysqli_query(
    $db_link,
    'CREATE INDEX IF NOT EXISTS CACHE ON ' . $pre . 'cache_tree (increment_id, user_id)'
);

mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'cache_tree` MODIFY `increment_id` tinyint(32) NOT NULL AUTO_INCREMENT'
);

// Add field status to CACHE_TREE table
$res = addColumnIfNotExist(
    $pre . 'cache_tree',
    'visible_folders',
    "longtext NOT NULL"
);


// Add field status to FILES table
$res = addColumnIfNotExist(
    $pre . 'files',
    'status',
    "varchar(50) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field status to table FILES! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field content to FILES table
$res = addColumnIfNotExist(
    $pre . 'files',
    'content',
    "longblob DEFAULT NULL;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field content to table FILES! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add new setting 'enable_tasks_manager'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'enable_tasks_manager'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'enable_tasks_manager', '0')"
    );
}

// Add new setting 'task_maximum_run_time'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'task_maximum_run_time'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'task_maximum_run_time', '300')"
    );
}

// Add new setting 'maximum_number_of_items_to_treat'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'maximum_number_of_items_to_treat'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'maximum_number_of_items_to_treat', '300')"
    );
}

// Add new setting 'tasks_manager_refreshing_period'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'tasks_manager_refreshing_period'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'tasks_manager_refreshing_period', '".NUMBER_ITEMS_IN_BATCH."')"
    );
}

// Add field is_ready_for_usage to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'is_ready_for_usage',
    "BOOLEAN NOT NULL DEFAULT FALSE;"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field is_ready_for_usage to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

mysqli_query(
    $db_link,
    'UPDATE `' . $pre . 'users` SET is_ready_for_usage = 1 WHERE is_ready_for_usage = 0;'
);

// Add new table PROCESSES_TASKS
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'processes_tasks` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `process_id` int(12) NOT NULL,
        `created_at` varchar(50) NOT NULL,
        `updated_at` varchar(50) DEFAULT NULL,
        `finished_at` varchar(50) DEFAULT NULL,
        `task` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`task`)),
        `system_process_id` int(12) DEFAULT NULL,
        `is_in_progress` tinyint(1) NOT NULL DEFAULT 0,
        `sub_task_in_progress` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`increment_id`)
        ) CHARSET=utf8;'
);

// Add new table PROCESSES
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'processes` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `created_at` varchar(50) NOT NULL,
        `updated_at` varchar(50) DEFAULT NULL,
        `finished_at` varchar(50) DEFAULT NULL,
        `process_id` int(12) DEFAULT NULL,
        `process_type` varchar(20) NOT NULL,
        `output` text DEFAULT NULL,
        `arguments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`arguments`)),
        `is_in_progress` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`increment_id`)
        ) CHARSET=utf8;'
);

// Add field masked to CATEGORIES table
$res = addColumnIfNotExist(
    $pre . 'categories',
    'masked',
    "tinyint(1) NOT NULL default '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field masked to table CATEGORIES! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field role_visibility to CATEGORIES table
$res = addColumnIfNotExist(
    $pre . 'categories',
    'role_visibility',
    "varchar(255) NOT NULL DEFAULT 'all';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field role_visibility to table CATEGORIES! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field is_mandatory to CATEGORIES table
$res = addColumnIfNotExist(
    $pre . 'categories',
    'is_mandatory',
    "tinyint(1) NOT NULL default '0';"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field is_mandatory to table CATEGORIES! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add field categories to NESTED_TREE table
$res = addColumnIfNotExist(
    $pre . 'nested_tree',
    'categories',
    "longtext NOT NULL"
);

// Alter field subject in EMAILS table
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'emails` MODIFY `subject` text NOT NULL'
);

// Alter field receivers in EMAILS table
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'emails` MODIFY `receivers` text NOT NULL'
);

// --- DB consolidation from fretch install --- //
// Alter table api
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "api` MODIFY COLUMN `id` INT(20) AUTO_INCREMENT;"
);

// Add the Primary INDEX item_id to the automatic_del table
$res = checkIndexExist(
    $pre . 'automatic_del',
    'PRIMARY',
    "ADD PRIMARY KEY (`item_id`)"
);
if (!$res) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding the INDEX item_id to the automatic_del table! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add column increment_id to cache table
$res = addColumnIfNotExist(
    $pre . 'cache',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table cache! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table cache
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "cache`
        MODIFY COLUMN `id` INT(12) NOT NULL,
        MODIFY COLUMN `label` VARCHAR(500) NOT NULL,
        MODIFY COLUMN `tags` text DEFAULT NULL,
        MODIFY COLUMN `id_tree` INT(12) NOT NULL,
        MODIFY COLUMN `restricted_to` VARCHAR(200) DEFAULT NULL,
        MODIFY COLUMN `login` text DEFAULT NULL,
        MODIFY COLUMN `timestamp` VARCHAR(50) DEFAULT NULL,
        MODIFY COLUMN `encryption_type` VARCHAR(50) DEFAULT '0';"
);

// Alter table categories
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "categories`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `parent_id` INT(12) NOT NULL,
        MODIFY COLUMN `level` INT(2) NOT NULL,
        MODIFY COLUMN `masked` tinyint(1) NOT NULL DEFAULT 0 AFTER `type`,
        MODIFY COLUMN `order` INT(12) NOT NULL DEFAULT 0;"
);

// Add column increment_id to categories_folders table
$res = addColumnIfNotExist(
    $pre . 'categories_folders',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table categories_folders! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table categories_folders
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "categories_folders` 
        MODIFY COLUMN `id_category` INT(12) NOT NULL,
        MODIFY COLUMN `id_folder` INT(12) NOT NULL;"
);

// Add column is_mandatory to categories_items table
$res = addColumnIfNotExist(
    $pre . 'categories_items',
    'is_mandatory',
    "TINYINT(1) NOT NULL DEFAULT 0 AFTER `encryption_type`"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table categories_items! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table categories_items
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "categories_items`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT;"
);

// Add column increment_id to emails table
$res = addColumnIfNotExist(
    $pre . 'emails',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table emails! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table emails
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "emails`
        MODIFY COLUMN `timestamp` INT(30) NOT NULL;"
);

// Add column increment_id to export table
$res = addColumnIfNotExist(
    $pre . 'export',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table export! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}
// Alter table export
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "export`
        MODIFY COLUMN `id` int(12) NOT NULL,
        MODIFY COLUMN `label` VARCHAR(500) NOT NULL,
        MODIFY COLUMN `path` VARCHAR(500) NOT NULL;"
);

// Alter table files
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "files`
        MODIFY COLUMN `size` INT(10) NOT NULL,
        MODIFY COLUMN `type` VARCHAR(255) NOT NULL,
        MODIFY COLUMN `content` longblob DEFAULT NULL AFTER `status`;"
);

// Alter table items
mysqli_query(
    $db_link,
    "UPDATE `" . $pre . "items` SET `auto_update_pwd_next_date` = '0' WHERE `auto_update_pwd_next_date` IS NULL;"
);

// Alter table items
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "items`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `label` VARCHAR(500) NOT NULL,
        MODIFY COLUMN `pw_len` INT(5) NOT NULL DEFAULT 0,
        MODIFY COLUMN `restricted_to` VARCHAR(200) DEFAULT NULL,
        MODIFY COLUMN `viewed_no` INT(12) NOT NULL DEFAULT 0,
        MODIFY COLUMN `complexity_level` VARCHAR(3) NOT NULL DEFAULT '-1',
        MODIFY COLUMN `auto_update_pwd_frequency` tinyint(2) NOT NULL DEFAULT 0,
        MODIFY COLUMN `auto_update_pwd_next_date` VARCHAR(100) NOT NULL DEFAULT '0',
        MODIFY COLUMN `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set';"
);

// Alter table items_change
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "items_change`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `item_id` INT(12) NOT NULL,
        MODIFY COLUMN `folder_id` tinyint(12) NOT NULL,
        MODIFY COLUMN `user_id` INT(12) NOT NULL;"
);

// Add column increment_id to items_edition table
$res = addColumnIfNotExist(
    $pre . 'items_edition',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table items_edition! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add the INDEX item_id_idx to the items_edition table
$res = checkIndexExist(
    $pre . 'items_edition',
    'item_id_idx',
    "ADD KEY `item_id_idx` (`item_id`)"
);
if (!$res) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding the INDEX role_id_idx to the items_edition table! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table items_edition
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "items_edition`
        MODIFY COLUMN `user_id` INT(12) NOT NULL;"
);

// Alter table kb
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `category_id` INT(12) NOT NULL,
        MODIFY COLUMN `author_id` INT(12) NOT NULL;"
);

// Alter table kb_categories
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb_categories` MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT;"
);

// Add column increment_id to kb_items table
$res = addColumnIfNotExist(
    $pre . 'kb_items',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table kb_items! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table kb_items
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb_items`
        MODIFY COLUMN `kb_id` INT(12) NOT NULL,
        MODIFY COLUMN `item_id` INT(12) NOT NULL;"
);

// Alter table languages
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "languages` MODIFY COLUMN `id` INT(10) NOT NULL AUTO_INCREMENT;"
);

// Alter table log_items
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "log_items`
        MODIFY COLUMN `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `id_item` INT(8) NOT NULL,
        MODIFY COLUMN `id_user` INT(8) NOT NULL;"
);

// Alter table log_system
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "log_system`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `qui` VARCHAR(255) NOT NULL;"
);

// Alter table misc column name if necessary
$exists = false;
$columns = mysqli_query($db_link, "show columns from `" . $pre . "misc`");
while ($col = mysqli_fetch_assoc($columns)) {
    if ($col['Field'] == 'increment_id') {
        $exists = true;
    }
}
if (!$exists) {
    mysqli_query($db_link, "ALTER TABLE `" . $pre . "misc` CHANGE COLUMN `id` `increment_id` INT(12) NOT NULL AUTO_INCREMENT");
}

// Alter table misc
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "misc`
        MODIFY COLUMN `valeur` varchar(500) NOT NULL;"
);

// Alter table nested_tree
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "nested_tree` MODIFY COLUMN `renewal_period` int(5) NOT NULL DEFAULT 0;"
);

// Remove the INDEX id from the nested_tree table
$mysqli_result = mysqli_query($db_link, "SHOW INDEX FROM `" . $pre . "nested_tree` WHERE key_name LIKE \"id\"");
if (mysqli_fetch_row($mysqli_result)){
    $res = mysqli_query($db_link, "ALTER TABLE `" . $pre . "nested_tree` DROP INDEX `id`");
    if (!$res) {
        echo '[{"finish":"1", "msg":"", "error":"An error appears when removing the INDEX id from the nested_tree table! ' . mysqli_error($db_link) . '!"}]';
        mysqli_close($db_link);
        exit();
    }
}

// Alter table otv
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "otv`
        MODIFY COLUMN `id` INT(10) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `item_id` INT(12) NOT NULL,
        MODIFY COLUMN `originator` INT(12) NOT NULL;"
);

// Add column increment_id to restriction_to_roles table
$res = addColumnIfNotExist(
    $pre . 'restriction_to_roles',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table restriction_to_roles! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table restriction_to_roles
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "restriction_to_roles`
        MODIFY COLUMN `role_id` INT(12) NOT NULL,
        MODIFY COLUMN `item_id` INT(12) NOT NULL;"
);

// Remove the INDEX role_id_idx from the restriction_to_roles table
$mysqli_result = mysqli_query($db_link, "SHOW INDEX FROM `" . $pre . "restriction_to_roles` WHERE key_name LIKE \"role_id_idx\"");
if (mysqli_fetch_row($mysqli_result)){
    $res = mysqli_query($db_link, "ALTER TABLE `" . $pre . "restriction_to_roles` DROP INDEX `role_id_idx`");
    if (!$res) {
        echo '[{"finish":"1", "msg":"", "error":"An error appears when removing the INDEX role_id_idx from the restriction_to_roles table! ' . mysqli_error($db_link) . '!"}]';
        mysqli_close($db_link);
        exit();
    }
}

// Alter table rights
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "rights`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `tree_id` INT(12) NOT NULL,
        MODIFY COLUMN `fonction_id` INT(12) NOT NULL;"
);

// Alter table roles_title
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "roles_title`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `complexity` INT(5) NOT NULL DEFAULT 0;"
);

// Add column increment_id to roles_values table
$res = addColumnIfNotExist(
    $pre . 'roles_values',
    'increment_id',
    "INT(12) NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (increment_id)"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field increment_id to table roles_values! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table roles_values
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "roles_values`
        MODIFY COLUMN `role_id` INT(12) NOT NULL,
        MODIFY COLUMN `folder_id` INT(12) NOT NULL;"
);

// Add column suggestion_type to suggestion table (fresh install create this column)
$res = addColumnIfNotExist(
    $pre . 'suggestion',
    'suggestion_type',
    "VARCHAR(10) NOT NULL DEFAULT 'new'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field suggestion_type to table suggestion! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter table suggestion
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "suggestion`
        MODIFY COLUMN `id` tinyint(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `pw_len` INT(5) NOT NULL,
        MODIFY COLUMN `author_id` INT(12) NOT NULL,
        MODIFY COLUMN `folder_id` INT(12) NOT NULL;"
);

// Alter table tags
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "tags`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `item_id` INT(12) NOT NULL;"
);

// Remove the INDEX id from the tags table
$mysqli_result = mysqli_query($db_link, "SHOW INDEX FROM `" . $pre . "tags` WHERE key_name LIKE \"id\"");
if (mysqli_fetch_row($mysqli_result)){
    $res = mysqli_query($db_link, "ALTER TABLE `" . $pre . "tags` DROP INDEX `id`");
    if (!$res) {
        echo '[{"finish":"1", "msg":"", "error":"An error appears when removing the INDEX id from the tags table! ' . mysqli_error($db_link) . '!"}]';
        mysqli_close($db_link);
        exit();
    }
}

// Alter table templates
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "templates`
        MODIFY COLUMN `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `item_id` INT(12) NOT NULL,
        MODIFY COLUMN `category_id` INT(12) NOT NULL;"
);

// Alter table tokens
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "tokens`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `user_id` INT(12) NOT NULL;"
);

// Add column user_ip_lastdate to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'user_ip_lastdate',
    "VARCHAR(50) NULL DEFAULT NULL AFTER `user_ip`"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field user_ip_lastdate to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add column user_api_key to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'user_api_key',
    "VARCHAR(500) NOT NULL DEFAULT 'none' AFTER `user_ip_lastdate`"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field user_api_key to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add column yubico_user_key to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'yubico_user_key',
    "VARCHAR(100) NOT NULL DEFAULT 'none' AFTER `user_api_key`"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field yubico_user_key to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Add column yubico_user_id to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'yubico_user_id',
    "VARCHAR(100) NOT NULL DEFAULT 'none' AFTER `yubico_user_key`"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field yubico_user_id to table USERS! ' . mysqli_error($db_link) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Alter USERS table
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "users`
        MODIFY COLUMN `id` INT(12) NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `pw` VARCHAR(400) NOT NULL,
        MODIFY COLUMN `groupes_visibles` VARCHAR(1000) NOT NULL,
        MODIFY COLUMN `fonction_id` VARCHAR(1000) DEFAULT NULL,
        MODIFY COLUMN `groupes_interdits` VARCHAR(1000) DEFAULT NULL,
        MODIFY COLUMN `email` VARCHAR(300) NOT NULL DEFAULT 'none',
        MODIFY COLUMN `favourites` VARCHAR(1000) DEFAULT NULL,
        MODIFY COLUMN `latest_items` VARCHAR(1000) DEFAULT NULL,
        MODIFY COLUMN `personal_folder` INT(1) NOT NULL DEFAULT 0,
        MODIFY COLUMN `isAdministratedByRole` tinyint(5) NOT NULL DEFAULT 0,
        MODIFY COLUMN `avatar` VARCHAR(1000) DEFAULT NULL,
        MODIFY COLUMN `avatar_thumb` VARCHAR(1000) DEFAULT NULL,
        MODIFY COLUMN `agses-usercardid` VARCHAR(50) NOT NULL DEFAULT '0',
        MODIFY COLUMN `encrypted_psk` text DEFAULT NULL,
        MODIFY COLUMN `user_ip` VARCHAR(400) NOT NULL DEFAULT 'none';"
);
// --- End DB consolidation from fresh install --- //

// Add field otp_provided to USERS table
$res = addColumnIfNotExist(
    $pre . 'users',
    'otp_provided',
    "BOOLEAN NOT NULL DEFAULT FALSE"
);

// Update this new field
mysqli_query(
    $db_link,
    'UPDATE `' . $pre . 'users` SET otp_provided = 1 WHERE `last_connexion` != "";'
);

//---<END 3.0.0.18


//--->BEGIN 3.0.0.20

// Add new setting 'ldap_tls_certifacte_check'
$tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'ldap_tls_certifacte_check'"));
if (intval($tmp) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_tls_certifacte_check', 'LDAP_OPT_X_TLS_NEVER')"
    );
}

// Add new field in table languages
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'languages` ADD `code_poeditor` VARCHAR(50) NOT NULL AFTER `flag`'
    );
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`bg` WHERE `name`=`bulgarian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`ca` WHERE `name`=`catalan`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`zh-Hans` WHERE `name`=`chinese`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`cs` WHERE `name`=`czech`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`nl` WHERE `name`=`dutch`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`en` WHERE `name`=`english`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`et` WHERE `name`=`estonian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`fr` WHERE `name`=`french`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`de` WHERE `name`=`german`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`el` WHERE `name`=`greek`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`hu` WHERE `name`=`hungarian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`it` WHERE `name`=`italian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`ja` WHERE `name`=`japanese`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`no` WHERE `name`=`norwegian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`pl` WHERE `name`=`polish`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`pt` WHERE `name`=`portuguese`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`pt-br` WHERE `name`=`portuguese_br`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`ro` WHERE `name`=`romanian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`ru` WHERE `name`=`russian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`es` WHERE `name`=`spanish`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`sv` WHERE `name`=`swedish`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`tr` WHERE `name`=`turkish`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`uk` WHERE `name`=`ukrainian`;');
    mysqli_query($db_link, 'UPDATE `teampass_languages` SET `code_poeditor`=`vi` WHERE `name`=`vietnamese`;');
} catch (Exception $e) {
    // Do nothing
}

// Fix a potential issue in table log_items
try {
    mysqli_query($db_link, 'UPDATE `teampass_log_items` SET `action` = `at_modification` WHERE `action` = `at_modification2`;');
} catch (Exception $e) {
    // Do nothing
}

//---<END 3.0.0.20



//--->BEGIN 3.0.0.22
try {
    mysqli_query(
        $db_link,
        'ALTER TABLE `' . $pre . 'cache_tree` MODIFY column `increment_id` SMALLINT AUTO_INCREMENT;'
    );
} catch (Exception $e) {
    // Do nothing
}

// Add indexes
mysqli_query(
    $db_link,
    'CREATE INDEX IF NOT EXISTS `OBJECT` ON ' . $pre . 'sharekeys_items (object_id)'
);
mysqli_query(
    $db_link,
    'CREATE INDEX IF NOT EXISTS USER ON ' . $pre . 'sharekeys_items (user_id)'
);
mysqli_query(
    $db_link,
    'CREATE INDEX IF NOT EXISTS `OBJECT` ON ' . $pre . 'sharekeys_logs (object_id)'
);
mysqli_query(
    $db_link,
    'CREATE INDEX IF NOT EXISTS USER ON ' . $pre . 'sharekeys_logs (user_id)'
);

// Add field nb_items_in_folder to NESTED_TREE table
$res = addColumnIfNotExist(
    $pre . 'nested_tree',
    'nb_items_in_folder',
    "int(10) NOT NULL DEFAULT '0'"
);
// Add field nb_subfolders to NESTED_TREE table
$res = addColumnIfNotExist(
    $pre . 'nested_tree',
    'nb_subfolders',
    "int(10) NOT NULL DEFAULT '0'"
);
// Add field nb_items_in_subfolders to NESTED_TREE table
$res = addColumnIfNotExist(
    $pre . 'nested_tree',
    'nb_items_in_subfolders',
    "int(10) NOT NULL DEFAULT '0'"
);

// Add new setting enable_tasks_log
addNewSetting(
    $pre . 'misc',
    'admin',
    'enable_tasks_log',
    '0'
);

// remove setting ldap_elusers
removeSetting(
    $pre . 'misc',
    'admin',
    'ldap_elusers'
);

// Add new table PROCESSES_LOGS
mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'processes_logs` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `created_at` varchar(20) NOT NULL,
        `job` varchar(50) NOT NULL,
        `status` varchar(10) NOT NULL,
        `updated_at` varchar(20) DEFAULT NULL,
        `finished_at` varchar(20) DEFAULT NULL,
        PRIMARY KEY (`increment_id`)
        ) CHARSET=utf8;'
);


//---<END 3.0.0.22



// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';
