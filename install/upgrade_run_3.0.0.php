<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upgrade_run_3.0.0.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


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
if ($CurrentTPversion[0] === '3') {
    $TPIsBranch3 = true;
} else {
    $TPIsBranch3 = false;
}


// Populate table MISC
$val = array(
    array('admin', 'cpassman_version', $SETTINGS_EXT['version'], 1),
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
    'CREATE TABLE `' . $pre . 'notification` (
        `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
        `item_id` INT(12) NOT NULL,
        `user_id` INT(12) NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) CHARSET=utf8;'
);

// Alter table FILES
mysqli_query(
    $db_link,
    'ALTER TABLE `' . $pre . 'files` CHANGE `name` `name` TEXT CHARACTER SET utf8 COLLATE NOT NULL;'
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
    "text NOT NULL default 'not_set'"
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
        mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "defuse_to_aes_migration` (`increment_id`, `user_id`, `rebuild_performed`, `timestamp`) 
            VALUES (NULL, '" . $user['id'] . "', 'done', '" . date_timestamp_get(date_create()) . "');"
        );
    } elseif (isset($user['id']) === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error - User not found in DB! Please restart upgrade."}]';
        exit();
    }
}

//---> 3.0.0.10
// no action on DB
//---<

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';
