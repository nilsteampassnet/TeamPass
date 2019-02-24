<?php
/**
 * @author        Nils Laumaillé <nils@teampass.net>
 *
 * @version       2.1.27
 *
 * @copyright     2009-2019 Nils Laumaillé
 * @license       GNU GPL-3.0
 *
 * @see          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/*
** Upgrade script for release 2.1.27
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
} else {
    $res = 'Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "error":"Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error()).'!"}]';
    mysqli_close($db_link);
    exit();
}

$post_step = filter_input(INPUT_POST, 'step', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_NUMBER_INT);

if (null !== $post_step) {
    switch ($post_step) {
        /*
        * CASE
        * creating a new user's public/private keys
        */
        case 'step1':
            // Manage initial case that permit to catch the list of users to treat.
            $listOfUsers = array();
            if (empty($post_number) === true && $post_step === 'step1') {
                // Count users
                $users = mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM '.$pre.'users
                    WHERE (public_key = "none" OR public_key = "")
                    AND id NOT IN ('.OTV_USER_ID.','.SSH_USER_ID.','.API_USER_ID.')'
                );
                while ($user = mysqli_fetch_array($users)) {
                    array_push($listOfUsers, $user['id']);
                }

                // User id to treat now
                $post_number = $listOfUsers[0];

                // Remove this ID from the array
                array_shift($listOfUsers);
            }

            // Treat the 1st user in the list
            if (count($listOfUsers) > 0 || (count($listOfUsers) === 0 && empty($post_number) === false)) {
                // Get info about user
                $userQuery = mysqli_fetch_array(
                    mysqli_query(
                        $db_link,
                        'SELECT pw, public_key, private_key
                        FROM '.$pre.'users
                        WHERE id = '.(int) $post_number
                    )
                );

                // CHeck if user has already migrated to AES
                if (empty($userQuery['public_key']) === true || $userQuery['public_key'] === 'none') {
                    // Now create his keys pair
                    // Generate new user password
                    $small_letters = range('a', 'z');
                    $big_letters = range('A', 'Z');
                    $digits = range(0, 9);
                    $symbols = array('#', '_', '-', '@', '$', '+', '&');

                    $res = array_merge($small_letters, $big_letters, $digits, $symbols);
                    $c = count($res);
                    // first variant

                    $random_string_lenght = 15;
                    $random_string = '';
                    for ($i = 0; $i < $random_string_lenght; ++$i) {
                        $random_string .= $res[random_int(0, $c - 1)];
                    }

                    // Generate keys
                    $userKeys = generateUserKeys($random_string);

                    // load passwordLib library
                    include_once '../sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    // Store
                    mysqli_query(
                        $db_link,
                        'UPDATE '.$pre."users
                        SET public_key = '".$userKeys['public_key']."',
                        private_key = '".$userKeys['private_key']."',
                        last_connexion = '',
                        upgrade_needed = 1,
                        pw = '".$pwdlib->createPasswordHash($random_string)."',
                        special = 'password_change_expected'
                        WHERE id = ".$post_number
                    );

                    // Remove all sharekeys if exists
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM '.$pre.'sharekeys_items
                        WHERE user_id = '.(int) $userInfo['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM '.$pre.'sharekeys_logs
                        WHERE user_id = '.(int) $userInfo['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM '.$pre.'sharekeys_fields
                        WHERE user_id = '.(int) $userInfo['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM '.$pre.'sharekeys_suggestions
                        WHERE user_id = '.(int) $userInfo['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM '.$pre.'sharekeys_files
                        WHERE user_id = '.(int) $userInfo['id']
                    );

                    $usersArray = array(
                        'id' => $post_number,
                        'pwd' => $random_string,
                        'public_key' => $userKeys['public_key'],
                        'private_key' => $userKeys['private_key'],
                    );
                }

                // Return
                echo '[{"finish":"0" , "next":"step1", "error":"" , "data" : "'.base64_encode(json_encode($usersArray)).'" , "number":"'.((int) $post_number + 1).'" , "loop_finished" : "false" , "rest" : "'.base64_encode(json_encode($listOfUsers)).'"}]';
            } else {
                // No more user to treat
                echo '[{"finish":"0" , "next":"step2", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "true"}]';
            }

            exit();
        break;

        /*
        * CASE
        * creating user's items keys
        */
        case 'step2':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_STRING));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_STRING)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM '.$pre.'users
                    WHERE id = '.(int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT count(*)
                    FROM '.$pre.'items
                    WHERE perso = 0'
                )
            );

            //echo $post_start.' - '.$db_count[0];

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT id, pw, encryption_type
                    FROM '.$pre.'items
                    WHERE perso = 0
                    LIMIT '.$post_start.', '.$post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM '.$pre.'sharekeys_items
                            WHERE object_id = '.(int) $item['id'].' AND user_id = '.(int) $adminId
                        )
                    );

                    //echo $adminItem['share_key'].'   ***   '.base64_decode($adminQuery['private_key']);
                    // Decrypt itemkey with admin key
                    $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                    //echo '> '.base64_decode($itemKey)." \n\n ";

                    // Decrypt password
                    //$itemPwd = doDataDecryption($item['pw'], $itemKey);

                    //echo '>>> '.base64_decode($itemPwd)." \n\n ";

                    // Encrypt with Password
                    //$cryptedStuff = doDataEncryption($itemPwd);

                    // Encrypt Item key
                    $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

                    // Save the key in DB
                    mysqli_query(
                        $db_link,
                        'INSERT INTO `'.$pre.'sharekeys_items`(`increment_id`, `object_id`, `user_id`, `share_key`)
                        VALUES (NULL,'.(int) $item['id'].','.(int) $userInfo['id'].",'".$share_key_for_item."')"
                    );

                    //$objKey = decryptUserObjectKey($share_key_for_item, $$userInfo['private_key']);
                    //$decPwd = doDataDecryption($cryptedStuff['encrypted'], $objKey);

                    //echo $item['pw'].' ;; '.$share_key_for_item.' ;; '.$decPwd.' || ';
                }

                echo '[{"finish":"0" , "next":"step2", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step3", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "true"}]';
            }

            exit();
        break;

        /*
        * CASE
        * creating user's logs keys
        */
        case 'step3':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_STRING));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_STRING)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM '.$pre.'users
                    WHERE id = '.(int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT increment_id
                    FROM '.$pre."log_items
                    WHERE raison LIKE 'at_pw :%' AND encryption_type = 'teampass_aes'"
                )
            );

            //echo $post_start.' - '.$db_count[0];

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT increment_id
                    FROM '.$pre."log_items
                    WHERE raison LIKE 'at_pw :%' AND encryption_type = 'teampass_aes'
                    LIMIT ".$post_start.', '.$post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM '.$pre.'sharekeys_logs
                            WHERE object_id = '.(int) $item['id'].' AND user_id = '.(int) $adminId
                        )
                    );

                    // Decrypt itemkey with admin key
                    $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                    // Decrypt password
                    //$itemPwd = doDataDecryption($item['pw'], $itemKey);

                    // Encrypt with Password
                    //$cryptedStuff = doDataEncryption($itemPwd);

                    // Encrypt Item key
                    $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

                    // Save the key in DB
                    mysqli_query(
                        $db_link,
                        'INSERT INTO `'.$pre.'sharekeys_logs`(`increment_id`, `object_id`, `user_id`, `share_key`)
                        VALUES (NULL,'.(int) $item['id'].','.(int) $userInfo['id'].",'".$share_key_for_item."')"
                    );
                }

                echo '[{"finish":"0" , "next":"step3", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step4", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "true"}]';
            }

            exit();
        break;

        /*
        * CASE
        * creating user's fields keys
        */
        case 'step4':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_STRING));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_STRING)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM '.$pre.'users
                    WHERE id = '.(int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM '.$pre.'categories_items'
                )
            );

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT id, data, encryption_type
                    FROM '.$pre.'categories_items
                    LIMIT '.$post_start.', '.$post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM '.$pre.'sharekeys_fields
                            WHERE object_id = '.(int) $item['id'].' AND user_id = '.(int) $adminId
                        )
                    );

                    // Decrypt itemkey with admin key
                    $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                    // Decrypt password
                    //$itemPwd = doDataDecryption($item['pw'], $itemKey);

                    // Encrypt with Password
                    //$cryptedStuff = doDataEncryption($itemPwd);

                    // Encrypt Item key
                    $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

                    // Save the key in DB
                    mysqli_query(
                        $db_link,
                        'INSERT INTO `'.$pre.'sharekeys_fields`(`increment_id`, `object_id`, `user_id`, `share_key`)
                        VALUES (NULL,'.(int) $item['id'].','.(int) $userInfo['id'].",'".$share_key_for_item."')"
                    );
                }

                echo '[{"finish":"0" , "next":"step4", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step5", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "true"}]';
            }

            exit();
        break;

        /*
        * CASE
        * creating user's Suggestion keys
        */
        case 'step5':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_STRING));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_STRING)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM '.$pre.'users
                    WHERE id = '.(int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM '.$pre.'suggestion'
                )
            );

            //echo $post_start.' - '.$db_count[0];

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT id, data, encryption_type
                    FROM '.$pre.'suggestion
                    LIMIT '.$post_start.', '.$post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM '.$pre.'sharekeys_suggestions
                            WHERE object_id = '.(int) $item['id'].' AND user_id = '.(int) $adminId
                        )
                    );

                    // Decrypt itemkey with admin key
                    $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                    // Decrypt password
                    //$itemPwd = doDataDecryption($item['pw'], $itemKey);

                    // Encrypt with Password
                    //$cryptedStuff = doDataEncryption($itemPwd);

                    // Encrypt Item key
                    $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

                    // Save the key in DB
                    mysqli_query(
                        $db_link,
                        'INSERT INTO `'.$pre.'sharekeys_suggestions`(`increment_id`, `object_id`, `user_id`, `share_key`)
                        VALUES (NULL,'.(int) $item['id'].','.(int) $userInfo['id'].",'".$share_key_for_item."')"
                    );
                }

                echo '[{"finish":"0" , "next":"step5", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step6", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "true"}]';
            }

            exit();
        break;

        /*
        * CASE
        * creating user's FILES keys
        */
        case 'step6':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_STRING));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_STRING)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM '.$pre.'users
                    WHERE id = '.(int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM '.$pre.'files'
                )
            );

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM '.$pre."files
                    WHERE status = 'aes_encryption'
                    LIMIT ".$post_start.', '.$post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM '.$pre.'sharekeys_files
                            WHERE object_id = '.(int) $item['id'].' AND user_id = '.(int) $adminId
                        )
                    );

                    // Decrypt itemkey with admin key
                    $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                    // Decrypt password
                    //$itemPwd = doDataDecryption($item['pw'], $itemKey);

                    // Encrypt with Password
                    //$cryptedStuff = doDataEncryption($itemPwd);

                    // Encrypt Item key
                    $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

                    // Save the key in DB
                    mysqli_query(
                        $db_link,
                        'INSERT INTO `'.$pre.'sharekeys_files`(`increment_id`, `object_id`, `user_id`, `share_key`)
                        VALUES (NULL,'.(int) $item['id'].','.(int) $userInfo['id'].",'".$share_key_for_item."')"
                    );
                }

                echo '[{"finish":"0" , "next":"step6", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"nextUser", "error":"" , "data" : "" , "number":"'.$post_number.'" , "loop_finished" : "true"}]';
            }

            exit();
        break;

        /*
        * CASE
        * Sending email to user
        */
        case 'send_pwd_by_email':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_STRING));

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get language
            $_SESSION['teampass']['lang'] = include_once '../includes/language/english.php';

            // Loop on users list
            foreach ($userInfo as $user) {
                // Find user's email
                $userEmail = mysqli_fetch_array(
                    mysqli_query(
                        $db_link,
                        'SELECT email
                        FROM '.$pre.'users
                        WHERE id = '.(int) $user['id']
                    )
                );
                if (empty($userEmail['email']) === false) {
                    // Send email
                    try {
                        sendEmail(
                            '[Teampass] Login credential change',
                            str_replace(
                                array('#tp_password#'),
                                array($user['pwd']),
                                'Hello,<br><br>This is a generated email from Teampass passwords manager.<br><br>Teampass administrator has decided to reset your current password. Next time you will connect to Teampass, please use:<br><br><b>#tp_password#</b><br><br>We highly encourage you to change this password once connected.<br><br>Cheers'
                            ),
                            $userEmail['email'],
                            $SETTINGS,
                            '',
                            true
                        );
                    } catch (Exception $e) {
                        console.log(e);
                    }
                }
            }

            echo '[{"finish":"1" , "next":"", "error":"" , "data" : "" , "number":"" , "loop_finished" : "true"}]';

            exit();
        break;
    }
}

echo '[{"finish":"1" , "next":"'.$next.'", "error":""}]';
/*
if ($next >= $total) {
    $finish = 1;
}

echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';
*/
