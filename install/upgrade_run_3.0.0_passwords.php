<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upgrade_run_3.0.0_passwords.php
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
$_SESSION['db_encoding'] = 'utf8';
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';
require_once '../includes/config/tp.config.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Load libraries
require_once '../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Some init
$_SESSION['settings']['loaded'] = '';
$finish = false;
$next = ($post_nb + $post_start);

// Test DB connexion
$pass = defuse_return_decrypted(DB_PASSWD);
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
)
) {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
	$db_link->set_charset(DB_ENCODING);
} else {
    $res = 'Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "error":"Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error()).'!"}]';
    mysqli_close($db_link);
    exit();
}

// Get POST with user info
$post_user_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_STRING)));
$userLogin = $post_user_info[0];
$userPassword = Encryption\Crypt\aesctr::decrypt(base64_decode($post_user_info[1]), 'cpm', 128);
$userId = $post_user_info[2];
if (isset($userPassword) === false || empty($userPassword) === true
    || isset($userLogin) === false || empty($userLogin) === true
    || isset($userId) === false || empty($userId) === true
) {
    echo '[{"finish":"1", "msg":"", "error":"Error - The user is not identified! Please restart upgrade."}]';
    exit();
} else {
    // Get user's key
    // Get user info
    $userQuery = mysqli_fetch_array(
        mysqli_query(
            $db_link,
            'SELECT public_key, private_key
            FROM '.$pre.'users
            WHERE id = '.(int) $userId
        )
    );

    if (isset($userQuery['id']) === true && empty($userQuery['id']) === false
        && (is_null($userQuery['private_key']) === true || empty($userQuery['private_key']) === true || $userQuery['private_key'] === 'none')
    ) {
        echo '[{"finish":"1", "msg":"", "error":"Error - User has no private key! Please restart upgrade."}]';
        exit();
    } else {
        $userPrivateKey = decryptPrivateKey($userPassword, $userQuery['private_key']);
        $userPublicKey = $userQuery['public_key'];
    }
}

// Get total items
$rows = mysqli_query(
    $db_link,
    'SELECT id
    FROM '.$pre.'items
    WHERE perso = 0'
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
    exit();
}

$total = mysqli_num_rows($rows);

// Loop on Items
$rows = mysqli_query(
    $db_link,
    'SELECT id, pw, encryption_type
    FROM '.$pre.'items
    WHERE perso = 0
    LIMIT '.$post_start.', '.$post_nb
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
    exit();
}

while ($data = mysqli_fetch_array($rows)) {
    if ($data['encryption_type'] !== 'teampass_aes') {
        // Decrypt with Defuse
        $passwd = defuseCryption(
            $data['pw'],
            '',
            'decrypt'
        );

        // Encrypt with Object Key
        $cryptedStuff = doDataEncryption($passwd['string']);

/*
        echo 'Pwd to encrypt is : '.$passwd['string']."\n\n";
        
        echo 'Item key: '.base64_decode($cryptedStuff['objectKey'])."\n\n";
        $share_key_for_item = encryptUserObjectKey($cryptedStuff['objectKey'], $userPublicKey);

        $shareKey_for_decrypt = decryptUserObjectKey($share_key_for_item, $userPrivateKey);
        echo 'shareKey_for_decrypt: '.base64_decode($shareKey_for_decrypt)."\n\n";

        $my_pwd = doDataDecryption($cryptedStuff['encrypted'], $shareKey_for_decrypt);
        echo 'Password: '.base64_decode($my_pwd)."\n\n";

        echo '[{"finish":"1" , "error":">>>'.base64_decode($my_pwd).'  --  '.$data['id'].'"}]';
        exit();
        */

        // Store new password in DB
        mysqli_query(
            $db_link,
            'UPDATE '.$pre."items
            SET pw = '".$cryptedStuff['encrypted']."', encryption_type = 'teampass_aes'
            WHERE id = ".$data['id']
        );

        // Insert in DB the new object key for this item by user
        mysqli_query(
            $db_link,
            "INSERT INTO `".$pre."sharekeys_items` (`increment_id`, `object_id`, `user_id`, `share_key`) 
            VALUES (NULL, '".$data['id']."', '".$userId."', '".encryptUserObjectKey($cryptedStuff['objectKey'], $userPublicKey)."');"
        );
    }
}

if ($next >= $total) {
    $finish = 1;
}

echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';
