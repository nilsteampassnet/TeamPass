<?php

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      upgrade_run_3.0.0_users.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use EZimuel\PHPSecureSession;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\PasswordManager\PasswordManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language();
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);
$_SESSION['CPM'] = 1;

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Load libraries
$superGlobal = new SuperGlobal();
$lang = new Language(); 

// Some init
$_SESSION['settings']['loaded'] = '';
$finish = false;
$next = ($post_nb + $post_start);

// Test DB connexion
$pass = defuse_return_decrypted(DB_PASSWD);
$server = (string) DB_HOST;
$pre = (string) DB_PREFIX;
$database = (string) DB_NAME;
$port = (int) DB_PORT;
$user = (string) DB_USER;

if ($db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    )
) {
    $db_link->set_charset(DB_ENCODING);
} else {
    $res = 'Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    exit();
}

$post_step = filter_input(INPUT_POST, 'step', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_NUMBER_INT);
$post_extra = filter_input(INPUT_POST, 'extra', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_tp_user = filter_input(INPUT_POST, 'tp_user_done', FILTER_SANITIZE_NUMBER_INT);

if (null !== $post_step) {
    switch ($post_step) {
            /*
        * CASE
        * creating a new user's public/private keys
        */
        case 'step1':
            // Manage initial case that permit to catch the list of users to treat.
            $listOfUsers = array();
            if (empty($post_number) === true && $post_step === 'step1' && $post_extra !== 'all_users_created') {
                // Count users
                $users = mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM ' . $pre . 'users
                    WHERE (public_key = "none" OR public_key = "" OR public_key IS NULL)
                    AND id NOT IN (' . OTV_USER_ID . ',' . SSH_USER_ID . ',' . API_USER_ID . ')'
                );
                while ($user = mysqli_fetch_array($users)) {
                    array_push($listOfUsers, $user['id']);
                }

                // User id to treat now
                $post_number = $listOfUsers[0];

                // Remove this ID from the array
                array_shift($listOfUsers);
            }

            // 3.0.0.23 - special case of TP user just created.
            // We need to create all its keys
            if ((int) $post_tp_user === 0) {
                // Create TP USER
                require_once '../includes/config/include.php';
                $tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `" . $pre . "users` WHERE id = '" . TP_USER_ID . "'"));
                if (intval($tmp) === 0) {
                    // generate key for password
                    $passwordManager = new PasswordManager();
                    $pwd = $passwordManager->generatePassword(25, true, true, true, true);
                    $salt = file_get_contents(rtrim(SECUREPATH, '/') . '/' . SECUREFILE);
                    $encrypted_pwd = cryption(
                        $pwd,
                        $salt,
                        'encrypt'
                    )['string'];

                    // GEnerate new public and private keys
                    $userKeys = generateUserKeys($pwd);

                    // Store
                    $mysqli_result = mysqli_query(
                        $db_link,
                        "INSERT INTO `" . $pre . "users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`, `public_key`, `private_key`, `is_ready_for_usage`, `otp_provided`) VALUES ('" . TP_USER_ID . "', 'TP', '".$encrypted_pwd."', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0', '".$userKeys['public_key']."', '".$userKeys['private_key']."', '1', '1')"
                    );
                }

                $TPUserKeysExist = mysqli_fetch_array(
                    mysqli_query(
                        $db_link,
                        'SELECT count(*)
                        FROM ' . $pre . 'sharekeys_items
                        WHERE user_id = ' . (int) TP_USER_ID
                    )
                );
                if ((int) $TPUserKeysExist[0] === 0) {
                    // this user is new and needs to be populated
                    $userQuery = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT pw, public_key, private_key, name, lastname, login
                            FROM ' . $pre . 'users
                            WHERE id = ' . (int) TP_USER_ID
                        )
                    );

                    // Generate new random string
                    $small_letters = range('a', 'z');
                    $big_letters = range('A', 'Z');
                    $digits = range(0, 9);
                    $symbols = array('#', '_', '-', '@', '$', '+', '&');

                    $res = array_merge($small_letters, $big_letters, $digits, $symbols);
                    $c = count($res);
                    $random_string_lenght = 20;
                    $random_string = '';
                    for ($i = 0; $i < $random_string_lenght; ++$i) {
                        $random_string .= $res[random_int(0, $c - 1)];
                    }

                    $usersArray = array(
                        'id' => TP_USER_ID,
                        'otp' => $random_string,
                        'public_key' => $userQuery['public_key'],
                        'private_key' => $userQuery['private_key'],
                        'login' => $userQuery['login'],
                        'name' => $userQuery['name'],
                        'lastname' => $userQuery['lastname'],
                    );

                    // Return
                    echo '[{"finish":"0" , "next":"step1", "error":"" , "data" : "' . base64_encode(json_encode($usersArray)) . '" , "number":"1" , "loop_finished" : "' . (count($listOfUsers) === 0 ? "true" : "false") . '" , "rest" : "' . base64_encode(json_encode($listOfUsers)) . '"}]';
                    mysqli_close($db_link);
                    exit();
                }
            }
            
            // Treat the 1st user in the list
            if (count($listOfUsers) > 0 || (count($listOfUsers) === 0 && empty($post_number) === false && $post_extra !== 'all_users_created')) {
                $usersArray = [];
                // Get info about user
                $userQuery = mysqli_fetch_array(
                    mysqli_query(
                        $db_link,
                        'SELECT pw, public_key, private_key, name, lastname, login
                        FROM ' . $pre . 'users
                        WHERE id = ' . (int) $post_number
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

                    $random_string_lenght = 20;
                    $random_string = '';
                    for ($i = 0; $i < $random_string_lenght; ++$i) {
                        $random_string .= $res[random_int(0, $c - 1)];
                    }

                    // Generate keys
                    $userKeys = generateUserKeys($random_string);

                    // Store
                    mysqli_query(
                        $db_link,
                        'UPDATE ' . $pre . "users
                        SET public_key = '" . $userKeys['public_key'] . "',
                        private_key = '" . $userKeys['private_key'] . "',
                        upgrade_needed = 1,
                        special = 'otc_is_required_on_next_login'
                        WHERE id = " . $post_number
                    );

                    // Remove all sharekeys if exists
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM ' . $pre . 'sharekeys_items
                        WHERE user_id = ' . (int) $userQuery['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM ' . $pre . 'sharekeys_logs
                        WHERE user_id = ' . (int) $userQuery['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM ' . $pre . 'sharekeys_fields
                        WHERE user_id = ' . (int) $userQuery['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM ' . $pre . 'sharekeys_suggestions
                        WHERE user_id = ' . (int) $userQuery['id']
                    );
                    mysqli_query(
                        $db_link,
                        'DELETE  
                        FROM ' . $pre . 'sharekeys_files
                        WHERE user_id = ' . (int) $userQuery['id']
                    );

                    $usersArray = array(
                        'id' => $post_number,
                        'otp' => $random_string,
                        'public_key' => $userKeys['public_key'],
                        'private_key' => $userKeys['private_key'],
                        'login' => $userQuery['login'],
                        'name' => $userQuery['name'],
                        'lastname' => $userQuery['lastname'],
                    );
                }

                // Return
                echo '[{"finish":"0" , "next":"step1", "error":"" , "data" : "' . base64_encode(json_encode($usersArray)) . '" , "number":"' . ((int) $post_number + 1) . '" , "loop_finished" : "' . (count($listOfUsers) === 0 ? "true" : "false") . '" , "rest" : "' . base64_encode(json_encode($listOfUsers)) . '"}]';
            } else {
                // No more user to treat
                echo '[{"finish":"0" , "next":"step2", "error":"" , "data" : "" , "number":"' . (empty($post_number) === true ? 0 : $post_number) . '" , "loop_finished" : "true" , "rest" : ""}]';
            }

            mysqli_close($db_link);
            exit();
            break;

            /*
        * CASE
        * creating user's items keys
        */
        case 'step2':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            if ($userInfo['public_key'] === null) {
                if ($userInfo['id'] !== TP_USER_ID) {
                    echo '[{"finish":"1" , "next":"step3", "error":"Public key is null; provided key is '.$post_user_info.'" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "true"}]';
                    mysqli_close($db_link);
                    exit();
                    break;
                } else {
                    /// get innffo for TP__USEER___IID
                    $userQuery = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT pw, public_key, private_key, name, lastname, login
                            FROM ' . $pre . 'users
                            WHERE id = ' . TP_USER_ID
                        )
                    );

                    $userInfo['public_key'] ==  $userQuery['public_key'];
                    $userInfo['private_key'] ==  $userQuery['private_key'];
                    $userInfo['login'] ==  $userQuery['login'];
                    $userInfo['name'] ==  $userQuery['name'];
                    $userInfo['lastname'] ==  $userQuery['lastname'];
                }
            }

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            // Get admin private key
            $adminPrivateKey = $_SESSION['admin_private_key'] ?? null;
            if ($adminPrivateKey === null) {
                $adminQuery = mysqli_fetch_array(
                    mysqli_query(
                        $db_link,
                        'SELECT private_key
                        FROM ' . $pre . 'users
                        WHERE id = ' . (int) $adminId
                    )
                );
                $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);
                if ($adminPrivateKey === false) {
                    echo '[{"finish":"1" , "next":"step3", "error":"Admin PWD is null; provided key is '.$post_admin_info[1].'" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "true"}]';
                    mysqli_close($db_link);
                    exit();
                    break;
                }
                $_SESSION['admin_private_key'] = $adminPrivateKey;
            }


            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT count(*)
                    FROM ' . $pre . 'items
                    WHERE perso = 0'
                )
            );

            // Get user public key
            if ($userInfo['public_key'] === null) {
                $dataTmp = mysqli_fetch_array(
                    mysqli_query(
                        $db_link,
                        'SELECT public_key
                        FROM ' . $pre . 'users
                        WHERE id = ' . (int) $userInfo['id']
                    )
                );
                $userInfo['public_key'] = $dataTmp['public_key'];
            }

            if ($post_start < $db_count[0]) {
                // Get items
                $query = '
                    SELECT items.id, items.pw, items.encryption_type, sharekeys_items.share_key
                        FROM ' . $pre . 'items AS items
                        LEFT JOIN ' . $pre . 'sharekeys_items AS sharekeys_items
                        ON items.id = sharekeys_items.object_id AND sharekeys_items.user_id = ' . (int) $adminId . '
                        WHERE items.perso = 0
                        LIMIT ' . $post_start . ', ' . $post_count_in_loop;
                $rows = mysqli_query($db_link, $query);

                while ($item = mysqli_fetch_array($rows)) {
                    // Short variables names
                    $item_id = $item['id'];
                    $itemKey = $_SESSION['items_object_keys'][$item_id];

                    // Create sharekey for user
                    $share_key_for_item = $userInfo['public_key'] !== null && $itemKey !== null ? encryptUserObjectKey($itemKey, $userInfo['public_key']) : '';

                    // Collect values for bulk insert
                    $insert_values[] = '(NULL, ' . (int) $item['id'] . ', ' . (int) $userInfo['id'] . ", '" . mysqli_real_escape_string($db_link, $share_key_for_item) . "')";
                }

                if (!empty($insert_values)) {
                    // Create a single bulk insert query
                    $insert_query = '
                        INSERT INTO `' . $pre . 'sharekeys_items`
                            (`increment_id`, `object_id`, `user_id`, `share_key`)
                            VALUES ' . implode(', ', $insert_values);

                    // Save all keys in DB
                    mysqli_query($db_link, $insert_query);
                }

                echo '[{"finish":"0" , "next":"step2", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step3", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "true"}]';
            }
            
            mysqli_close($db_link);
            exit();
            break;

            /*
        * CASE
        * creating user's logs keys
        */
        case 'step3':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM ' . $pre . 'users
                    WHERE id = ' . (int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT increment_id
                    FROM ' . $pre . "log_items
                    WHERE raison LIKE 'at_pw :%' AND encryption_type = 'teampass_aes'"
                )
            );

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT increment_id
                    FROM ' . $pre . "log_items
                    WHERE raison LIKE 'at_pw :%' AND encryption_type = 'teampass_aes'
                    LIMIT " . $post_start . ', ' . $post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM ' . $pre . 'sharekeys_logs
                            WHERE object_id = ' . (int) $item['id'] . ' AND user_id = ' . (int) $adminId
                        )
                    );

                    if ($adminItem !== null) {
                        // Decrypt itemkey with admin key
                        $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                        // Encrypt Item key
                        $share_key_for_item = $userInfo['public_key'] !== null ? encryptUserObjectKey($itemKey, $userInfo['public_key']) : '';

                        // Save the key in DB
                        mysqli_query(
                            $db_link,
                            'INSERT INTO `' . $pre . 'sharekeys_logs`(`increment_id`, `object_id`, `user_id`, `share_key`)
                            VALUES (NULL,' . (int) $item['id'] . ',' . (int) $userInfo['id'] . ",'" . $share_key_for_item . "')"
                        );
                    }
                }

                echo '[{"finish":"0" , "next":"step3", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step4", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "true"}]';
            }

            mysqli_close($db_link);
            exit();
            break;

            /*
        * CASE
        * creating user's fields keys
        */
        case 'step4':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM ' . $pre . 'users
                    WHERE id = ' . (int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM ' . $pre . 'categories_items'
                )
            );

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT id, data, encryption_type
                    FROM ' . $pre . 'categories_items
                    WHERE encryption_type = "' . TP_ENCRYPTION_NAME . '"
                    LIMIT ' . $post_start . ', ' . $post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM ' . $pre . 'sharekeys_fields
                            WHERE object_id = ' . (int) $item['id'] . ' AND user_id = ' . (int) $adminId
                        )
                    );

                    if ($adminItem !== null) {
                        // Decrypt itemkey with admin key
                        $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                        // Encrypt Item key
                        $share_key_for_item = $userInfo['public_key'] !== null ? encryptUserObjectKey($itemKey, $userInfo['public_key']) : '';

                        // Save the key in DB
                        mysqli_query(
                            $db_link,
                            'INSERT INTO `' . $pre . 'sharekeys_fields`(`increment_id`, `object_id`, `user_id`, `share_key`)
                            VALUES (NULL,' . (int) $item['id'] . ',' . (int) $userInfo['id'] . ",'" . $share_key_for_item . "')"
                        );
                    }
                }

                echo '[{"finish":"0" , "next":"step4", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step5", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "true"}]';
            }

            mysqli_close($db_link);
            exit();
            break;

            /*
        * CASE
        * creating user's Suggestion keys
        */
        case 'step5':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM ' . $pre . 'users
                    WHERE id = ' . (int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM ' . $pre . 'suggestion'
                )
            );

            //echo $post_start.' - '.$db_count[0];

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM ' . $pre . 'suggestion
                    LIMIT ' . $post_start . ', ' . $post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM ' . $pre . 'sharekeys_suggestions
                            WHERE object_id = ' . (int) $item['id'] . ' AND user_id = ' . (int) $adminId
                        )
                    );

                    if ($adminItem !== null) {
                        // Decrypt itemkey with admin key
                        $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                        // Encrypt Item key
                        $share_key_for_item = $userInfo['public_key'] !== null ? encryptUserObjectKey($itemKey, $userInfo['public_key']) : '';

                        // Save the key in DB
                        mysqli_query(
                            $db_link,
                            'INSERT INTO `' . $pre . 'sharekeys_suggestions`(`increment_id`, `object_id`, `user_id`, `share_key`)
                            VALUES (NULL,' . (int) $item['id'] . ',' . (int) $userInfo['id'] . ",'" . $share_key_for_item . "')"
                        );
                    }
                }

                echo '[{"finish":"0" , "next":"step5", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"step6", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "true"}]';
            }

            mysqli_close($db_link);
            exit();
            break;

            /*
        * CASE
        * creating user's FILES keys
        */
        case 'step6':
            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_count_in_loop = filter_input(INPUT_POST, 'count_in_loop', FILTER_SANITIZE_NUMBER_INT);

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get POST with admin info
            $post_admin_info = json_decode(base64_decode(filter_input(INPUT_POST, 'info', FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
            $adminId = $post_admin_info[2];
            $adminPwd = Encryption\Crypt\aesctr::decrypt(base64_decode($post_admin_info[1]), 'cpm', 128);
            $adminQuery = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT private_key
                    FROM ' . $pre . 'users
                    WHERE id = ' . (int) $adminId
                )
            );
            $adminPrivateKey = decryptPrivateKey($adminPwd, $adminQuery['private_key']);

            // Count Items
            $db_count = mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM ' . $pre . 'files'
                )
            );

            if ($post_start < $db_count[0]) {
                // Get items
                $rows = mysqli_query(
                    $db_link,
                    'SELECT id
                    FROM ' . $pre . 'files
                    WHERE status = "' . TP_ENCRYPTION_NAME . '"
                    LIMIT ' . $post_start . ', ' . $post_count_in_loop
                );

                while ($item = mysqli_fetch_array($rows)) {
                    // Get itemKey for admin
                    $adminItem = mysqli_fetch_array(
                        mysqli_query(
                            $db_link,
                            'SELECT share_key
                            FROM ' . $pre . 'sharekeys_files
                            WHERE object_id = ' . (int) $item['id'] . ' AND user_id = ' . (int) $adminId
                        )
                    );

                    if ($adminItem !== null) {
                        // Decrypt itemkey with admin key
                        $itemKey = decryptUserObjectKey($adminItem['share_key'], $adminPrivateKey);

                        // Decrypt password
                        //$itemPwd = doDataDecryption($item['pw'], $itemKey);

                        // Encrypt with Password
                        //$cryptedStuff = doDataEncryption($itemPwd);

                        // Encrypt Item key
                        $share_key_for_item = $userInfo['public_key'] !== null ? encryptUserObjectKey($itemKey, $userInfo['public_key']) : '';

                        // Save the key in DB
                        mysqli_query(
                            $db_link,
                            'INSERT INTO `' . $pre . 'sharekeys_files`(`increment_id`, `object_id`, `user_id`, `share_key`)
                            VALUES (NULL,' . (int) $item['id'] . ',' . (int) $userInfo['id'] . ",'" . $share_key_for_item . "')"
                        );
                    }
                }

                echo '[{"finish":"0" , "next":"step6", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "false"}]';
            } else {
                echo '[{"finish":"0" , "next":"nextUser", "error":"" , "data" : "" , "number":"' . $post_number . '" , "loop_finished" : "true"}]';
            }

            mysqli_close($db_link);
            exit();
            break;

            /*
        * CASE
        * Sending email to user
        */
        case 'send_pwd_by_email':
            // Continue if UPGRADE_SEND_EMAILS set to TRUE or undefined
            if (defined('UPGRADE_SEND_EMAILS') === true && UPGRADE_SEND_EMAILS === false) {
                break;
            }

            // Prepare post variables
            $post_user_info = base64_decode(filter_input(INPUT_POST, 'userInfo', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

            // Get POST with user info
            $userInfo = json_decode($post_user_info, true);

            // Get language
            $_SESSION['teampass']['lang'] = include_once '../includes/language/english.php';

            // Find user's email
            $userEmail = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT email, name as userName
                    FROM ' . $pre . 'users
                    WHERE id = ' . (int) $userInfo['id']
                )
            );
            if (empty($userEmail['email']) === false) {
                // Send email
                try {
                    sendMailToUser(
                        $userEmail['email'],
                        'Hello '.$userEmail['userName'].',<br><br>This is a generated email from Teampass passwords manager.<br><br>Teampass administrator has performed an update which includes a change of encryption protocol. During your next login in Teampass, a One Time Code will be asked in order to re-encrypt your data.<br><br>Please use on demand (it is unique for your account):<br><br><b>#tp_password#</b><br><br>This process might take a couple of minutes depending of the number of items existing in the database.<br><br>Cheers',
                        '[Teampass] Your One Time Code',
                        [
                            '#tp_password#' => $userInfo['otp'],
                        ],
                        true
                    );
                } catch (Exception $e) {
                    error_log('Error sending email to user '.$userEmail['email'].'. Error is: '.$e->getMessage());
                }
            }

            break;
    }
}

echo '[{"finish":"1" , "next":"' . $next . '", "error":""}]';
