<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      main.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

if (isset($_SESSION) === false) {
    include_once 'SecureHandler.php';
    session_name('teampass_session');
    session_start();
    $_SESSION['CPM'] = 1;
}

if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1) {
    $_SESSION['error']['code'] = '1004';
    include '../error.php';
    exit();
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// DO CHECKS
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
if (
    isset($post_type) === true
    && ($post_type === 'ga_generate_qr'
        //|| $post_type === 'recovery_send_pw_by_email'
        //|| $post_type === 'recovery_generate_new_password'
        || $post_type === 'get_teampass_settings')
) {
    // continue
    mainQuery($SETTINGS);
} elseif (
    isset($_SESSION['user_id']) === true
    && checkUser($_SESSION['user_id'], $_SESSION['key'], 'home', $SETTINGS) === false
) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
} elseif ((isset($_SESSION['user_id']) === true
        && isset($_SESSION['key'])) === true
    || (isset($post_type) === true
        //&& $post_type === 'change_user_language'
        && null !== filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES))
) {
    // continue
    mainQuery($SETTINGS);
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

/**
 * Undocumented function.
 */
function mainQuery(array $SETTINGS)
{
    header('Content-type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    error_reporting(E_ERROR);


    // Includes
    include_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
    include_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
    include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
    include_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';

    // Connect to mysql server
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';

    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defined('DB_PASSWD_CLEAR') === false ? defuseReturnDecrypted(DB_PASSWD, $SETTINGS) : DB_PASSWD_CLEAR;
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    // User's language loading
    include_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';

    // Prepare post variables
    $post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
    $post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

    // Check KEY
    if (isset($post_key) === false || empty($post_key) === true) {
        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('key_is_not_correct'),
            ),
            'encode'
        );
        return false;
    }

    // decrypt and retreive data in JSON format
    $dataReceived = prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        $post_data,
        'decode'
    );
    
    // Manage type of action asked
    switch ($post_type) {
        case 'change_pw':
            echo changePassword(
                (string) filter_var($dataReceived['new_pw'], FILTER_SANITIZE_STRING),
                isset($dataReceived['current_pw']) === true ? (string) filter_var($dataReceived['current_pw'], FILTER_SANITIZE_STRING) : '',
                (int) filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['change_request'], FILTER_SANITIZE_STRING),
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                $SETTINGS
            );
            
            break;

        /*
         * This will generate the QR Google Authenticator
         */
        case 'ga_generate_qr':
            echo generateQRCode(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['demand_origin'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['send_email'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['login'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['pwd'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );

            break;
        
            /*
         * Increase the session time of User
         */
        case 'increase_session_time':
            echo increaseSessionDuration(
                (int) filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT)
            );

            break;
        
            /*
         * Hide maintenance message
         */
        case 'hide_maintenance':
            $_SESSION['hide_maintenance'] = 1;

            break;
        
        /*
         * Send emails not sent
         */
        case 'send_waiting_emails':
            sendEmailsNotSent(
                $SETTINGS
            );
            
            break;

        /*
         * Generate a password generic
         */
        case 'generate_password':
            echo generateGenericPassword(
                (int) filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT),
                (bool) filter_input(INPUT_POST, 'secure_pwd', FILTER_SANITIZE_STRING),
                (bool) filter_input(INPUT_POST, 'lowercase', FILTER_SANITIZE_STRING),
                (bool) filter_input(INPUT_POST, 'capitalize', FILTER_SANITIZE_STRING),
                (bool) filter_input(INPUT_POST, 'numerals', FILTER_SANITIZE_STRING),
                (bool) filter_input(INPUT_POST, 'symbols', FILTER_SANITIZE_NUMBER_INT),
                $SETTINGS
            );
            
            break;

        /*
         * Refresh list of last items seen
         */
        case 'refresh_list_items_seen':
            echo refreshUserItemsSeenList(
                $SETTINGS
            );

            break;

        /*
         * Generates a KEY with CRYPT
         */
        case 'generate_new_key':
            // load passwordLib library
            $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();
            // generate key
            $key = $pwdlib->getRandomToken(filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT));
            echo '[{"key" : "' . htmlentities($key, ENT_QUOTES) . '"}]';
            break;

        /*
         * Generates a TOKEN with CRYPT
         */
        case 'save_token':
            $token = GenerateCryptKey(
                null !== filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) ? (int) filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) : 20,
                null !== filter_input(INPUT_POST, 'secure', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'secure', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                null !== filter_input(INPUT_POST, 'numeric', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'numeric', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                null !== filter_input(INPUT_POST, 'capital', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'capital', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                null !== filter_input(INPUT_POST, 'symbols', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'symbols', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                null !== filter_input(INPUT_POST, 'lowercase', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'lowercase', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                $SETTINGS
            );
            
            // store in DB
            DB::insert(
                prefixTable('tokens'),
                array(
                    'user_id' => (int) $_SESSION['user_id'],
                    'token' => $token,
                    'reason' => filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING),
                    'creation_timestamp' => time(),
                    'end_timestamp' => time() + filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT), // in secs
                )
            );

            echo '[{"token" : "' . $token . '"}]';
            break;


        /*
         * TODO Check if suggestions are existing
         */
        /*
        case 'is_existings_suggestions':
            if ($_SESSION['user_manager'] === '1' || $_SESSION['is_admin'] === '1') {
                $count = 0;
                DB::query('SELECT * FROM ' . prefixTable('items_change'));
                $count += DB::count();
                DB::query('SELECT * FROM ' . prefixTable('suggestion'));
                $count += DB::count();

                echo '[ { "error" : "" , "count" : "' . $count . '" , "show_sug_in_menu" : "0"} ]';
                break;
            }
            
            if (isset($_SESSION['nb_item_change_proposals']) && $_SESSION['nb_item_change_proposals'] > 0) {
                echo '[ { "error" : "" , "count" : "' . $_SESSION['nb_item_change_proposals'] . '" , "show_sug_in_menu" : "1"} ]';
                break;
            }
            
            echo '[ { "error" : "" , "count" : "" , "show_sug_in_menu" : "0"} ]';

            break;
        */

        /*
         * Sending statistics
         */
        case 'sending_statistics':
            sendingStatistics(
                $SETTINGS
            );

            break;

        /*
         * delete a file
         */
        case 'file_deletion':
            fileDelete(filter_input(INPUT_POST, 'filename', FILTER_SANITIZE_STRING), $SETTINGS);

            break;

        /*
         * Generate BUG report
         */
        case 'generate_bug_report':
            echo generateBugReport(
                (string) $post_data,
                $SETTINGS
            );
        
            break;

        /*
        * get_teampass_settings
        */
        case 'get_teampass_settings':
            // Encrypt data to return
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],$SETTINGS, 'encode');

            break;


        /*
        * test_current_user_password_is_correct
        */
        case 'test_current_user_password_is_correct':
            echo isUserPasswordCorrect(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['password'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );

            break;

        /*
         * User's public/private keys change
         */
        case 'change_private_key_encryption_password':
            echo changePrivateKeyEncryptionPassword(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['current_code'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['new_code'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['action_type'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );
        
            break;

        /*
         * User's password has to be initialized
         */
        case 'initialize_user_password':
            echo initializeUserPassword(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['special'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['password'], FILTER_SANITIZE_STRING),
                (bool) filter_var($dataReceived['self_change'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );

            break;

        /*
        * CASE
        * Send email
        */
        case 'mail_me':
            echo sendMailToUser(
                filter_var($dataReceived['receipt'], FILTER_SANITIZE_STRING),
                $dataReceived['body'],
                (string) filter_var($dataReceived['subject'], FILTER_SANITIZE_STRING),
                (array) filter_var_array(
                    $dataReceived['pre_replace'],
                    FILTER_SANITIZE_STRING
                ),
                $SETTINGS
            );

            break;

        /*
        * Generate a temporary encryption key for user
        */
        case 'generate_temporary_encryption_key':
            echo generateOneTimeCode(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                $SETTINGS
            );

            break;

        /*
        * user_sharekeys_reencryption_start
        */
        case 'user_sharekeys_reencryption_start':
            echo startReEncryptingUserSharekeys(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (bool) filter_var($dataReceived['self_change'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );

            break;

        /*
        * user_sharekeys_reencryption_next
        */
        case 'user_sharekeys_reencryption_next':
            echo continueReEncryptingUserSharekeys(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (bool) filter_var($dataReceived['self_change'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['action'], FILTER_SANITIZE_STRING),
                (int) filter_var($dataReceived['start'], FILTER_SANITIZE_NUMBER_INT),
                (int) filter_var($dataReceived['length'], FILTER_SANITIZE_NUMBER_INT),
                $SETTINGS
            );

            break;

        /*
        * user_psk_reencryption
        */
        case 'user_psk_reencryption':
            echo migrateTo3_DoUserPersonalItemsEncryption(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (int) filter_var($dataReceived['start'], FILTER_SANITIZE_NUMBER_INT),
                (int) filter_var($dataReceived['length'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['userPsk'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );
        
            break;

        /*
        * Get info 
        */
        case 'get_user_info':
            echo getUserInfo(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['fields'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );

            break;

        /*
        * Change user's authenticataion password
        */
        case 'change_user_auth_password':
            echo changeUserAuthenticationPassword(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                (string) filter_var($dataReceived['old_password'], FILTER_SANITIZE_STRING),
                (string) filter_var($dataReceived['new_password'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );

            break;


        /*
        * User's authenticataion password in LDAP has changed
        */
        case 'change_user_ldap_auth_password':
            echo /** @scrutinizer ignore-call */ changeUserLDAPAuthenticationPassword(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                filter_var($dataReceived['previous_password'], FILTER_SANITIZE_STRING),
                filter_var($dataReceived['current_password'], FILTER_SANITIZE_STRING),
                $SETTINGS
            );

            break;


        /*
        * How many items for this user
        */
        case 'get_number_of_items_to_treat':
            echo getNumberOfItemsToTreat(
                (int) filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT),
                $SETTINGS
            );

            break;
    }
}


/**
 * Provides the number of items
 *
 * @param int   $userId     User ID
 * @param array $SETTINGS   TeampassSettings
 *
 * @return string
 */
function getNumberOfItemsToTreat(
    int $userId,
    array $SETTINGS
): string
{
    // get number of items
    DB::queryFirstRow(
        'SELECT increment_id
        FROM ' . prefixTable('sharekeys_items') .
        ' WHERE user_id = %i',
        $userId
    );

    // Send back
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => false,
            'nbItems' => DB::count(),
        ),
        'encode'
    );
}


/**
 * 
 */
function changePassword(
    string $post_new_password,
    string $post_current_password,
    int $post_password_complexity,
    string $post_change_request,
    int $post_user_id,
    array $SETTINGS
): string
{
    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    // Prepare variables
    $post_new_password_hashed = $pwdlib->createPasswordHash($post_new_password);

    // User has decided to change is PW
    if ($post_change_request === 'reset_user_password_expected'
        || $post_change_request === 'user_decides_to_change_password'
    ) {
        // Check that current user is correct
        if ((int) $post_user_id !== (int) $_SESSION['user_id']) {
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('error_not_allowed_to'),
                ),
                'encode'
            );
        }

        // check if expected security level is reached
        $dataUser = DB::queryfirstrow(
            'SELECT *
            FROM ' . prefixTable('users') . ' WHERE id = %i',
            $post_user_id
        );

        // check if badly written
        $dataUser['fonction_id'] = array_filter(
            explode(',', str_replace(';', ',', $dataUser['fonction_id']))
        );
        $dataUser['fonction_id'] = implode(',', $dataUser['fonction_id']);
        DB::update(
            prefixTable('users'),
            array(
                'fonction_id' => $dataUser['fonction_id'],
            ),
            'id = %i',
            $post_user_id
        );

        if (empty($dataUser['fonction_id']) === false) {
            $data = DB::queryFirstRow(
                'SELECT complexity
                FROM ' . prefixTable('roles_title') . '
                WHERE id IN (' . $dataUser['fonction_id'] . ')
                ORDER BY complexity DESC'
            );
        } else {
            // In case user has no roles yet
            $data = array();
            $data['complexity'] = 0;
        }

        if ((int) $post_password_complexity < (int) $data['complexity']) {
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => '<div style="margin:10px 0 10px 15px;">' . langHdl('complexity_level_not_reached') . '.<br>' .
                        langHdl('expected_complexity_level') . ': <b>' . TP_PW_COMPLEXITY[$data['complexity']][1] . '</b></div>',
                ),
                'encode'
            );
        }

        // Check that the 2 passwords are differents
        if ($post_current_password === $post_new_password) {
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('password_already_used'),
                ),
                'encode'
            );
        }

        // update sessions
        $_SESSION['last_pw_change'] = mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y'));
        $_SESSION['validite_pw'] = true;

        // BEfore updating, check that the pwd is correct
        if ($pwdlib->verifyPasswordHash($post_new_password, $post_new_password_hashed) === true && empty($dataUser['private_key']) === false) {
            $special_action = 'none';
            if ($post_change_request === 'reset_user_password_expected') {
                $_SESSION['user']['private_key'] = decryptPrivateKey($post_current_password, $dataUser['private_key']);
            }

            // update DB
            DB::update(
                prefixTable('users'),
                array(
                    'pw' => $post_new_password_hashed,
                    'last_pw_change' => mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')),
                    'last_pw' => $post_current_password,
                    'special' => $special_action,
                    'private_key' => encryptPrivateKey($post_new_password, $_SESSION['user']['private_key']),
                ),
                'id = %i',
                $post_user_id
            );
            // update LOG
            logEvents($SETTINGS, 'user_mngt', 'at_user_pwd_changed', (string) $_SESSION['user_id'], $_SESSION['login'], $post_user_id);

            // Send back
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
        }
        // Send back
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('pw_hash_not_correct'),
            ),
            'encode'
        );
    }
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_not_allowed_to'),
        ),
        'encode'
    );
}



function generateQRCode(
    $post_id,
    $post_demand_origin,
    $post_send_mail,
    $post_login,
    $post_pwd,
    array $SETTINGS
): string
{
    // is this allowed by setting
    if ((isset($SETTINGS['ga_reset_by_user']) === false || (int) $SETTINGS['ga_reset_by_user'] !== 1)
        && (null === $post_demand_origin || $post_demand_origin !== 'users_management_list')
    ) {
        // User cannot ask for a new code
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('error_not_allowed_to'),
            ),
            'encode'
        );
    }

    // Check if user exists
    if (null === $post_id || empty($post_id) === true) {
        // Get data about user
        $data = DB::queryfirstrow(
            'SELECT id, email, pw
            FROM ' . prefixTable('users') . '
            WHERE login = %s',
            $post_login
        );
    } else {
        $data = DB::queryfirstrow(
            'SELECT id, login, email, pw
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_id
        );
        $post_login = $data['login'];
    }
    // Get number of returned users
    $counter = DB::count();

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    // Do treatment
    if ($counter === 0) {
        // Not a registered user !
        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($post_login), stripslashes($post_login));
        return prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('no_user'),
                'tst' => 1,
            ),
            'encode'
        );
    }

    if (
        isset($post_pwd) === true
        && isset($data['pw']) === true
        && $pwdlib->verifyPasswordHash($post_pwd, $data['pw']) === false
        && $post_demand_origin !== 'users_management_list'
    ) {
        // checked the given password
        logEvents($SETTINGS, 'failed_auth', 'password_is_not_correct', '', stripslashes($post_login), stripslashes($post_login));
        return prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('no_user'),
                'tst' => $post_demand_origin,
            ),
            'encode'
        );
    }
    
    if (empty($data['email']) === true) {
        return prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('no_email_set'),
            ),
            'encode'
        );
    }
    
    // generate new GA user code
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php';
    $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($SETTINGS['ga_website_name']);
    $gaSecretKey = $tfa->createSecret();
    $gaTemporaryCode = GenerateCryptKey(12, false, true, true, false, true, $SETTINGS);

    DB::update(
        prefixTable('users'),
        [
            'ga' => $gaSecretKey,
            'ga_temporary_code' => $gaTemporaryCode,
        ],
        'id = %i',
        $data['id']
    );

    // Log event
    logEvents($SETTINGS, 'user_connection', 'at_2fa_google_code_send_by_email', (string) $data['id'], stripslashes($post_login), stripslashes($post_login));

    // send mail?
    if ((int) $post_send_mail === 1) {
        sendEmail(
            langHdl('email_ga_subject'),
            str_replace(
                '#2FACode#',
                $gaTemporaryCode,
                langHdl('email_ga_text')
            ),
            $data['email'],
            $SETTINGS
        );

        // send back
        return prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => $post_send_mail,
                'email' => $data['email'],
                'email_result' => str_replace(
                    '#email#',
                    '<b>' . obfuscateEmail($data['email']) . '</b>',
                    addslashes(langHdl('admin_email_result_ok'))
                ),
            ),
            'encode'
        );
    }
    
    // send back
    return prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'error' => false,
            'message' => '',
            'email' => $data['email'],
            'email_result' => str_replace(
                '#email#',
                '<b>' . obfuscateEmail($data['email']) . '</b>',
                addslashes(langHdl('admin_email_result_ok'))
            ),
        ),
        'encode'
    );
}

function sendEmailsNotSent(
    array $SETTINGS
)
{
    if (isset($SETTINGS['enable_send_email_on_user_login'])
        && (int) $SETTINGS['enable_send_email_on_user_login'] === 1
    ) {
        $row = DB::queryFirstRow(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s',
            'cron',
            'sending_emails'
        );

        if ((int) (time() - $row['valeur']) >= 300 || (int) $row['valeur'] === 0) {
            $rows = DB::query(
                'SELECT *
                FROM ' . prefixTable('emails') .
                ' WHERE status != %s',
                'sent'
            );
            foreach ($rows as $record) {
                //echo $record['increment_id'] . " >> ";
                // Send email
                $ret = json_decode(
                    sendEmail(
                        $record['subject'],
                        $record['body'],
                        $record['receivers'],
                        $SETTINGS
                    ),
                    true
                );

                // update item_id in files table
                DB::update(
                    prefixTable('emails'),
                    array(
                        'status' => $ret['error'] === 'error_mail_not_send' ? 'not_sent' : 'sent',
                    ),
                    'timestamp = %s',
                    $record['timestamp']
                );
            }
        }
        // update cron time
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => time(),
            ),
            'intitule = %s AND type = %s',
            'sending_emails',
            'cron'
        );
    }
}

function generateGenericPassword(
    int $size,
    bool $secure,
    bool $lowercase,
    bool $capitalize,
    bool $numerals,
    bool $symbols,
    array $SETTINGS
): string
{
    if ((int) $size > (int) $SETTINGS['pwd_maximum_length']) {
        return prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            array(
                'error_msg' => 'Password length is too long! ',
                'error' => 'true',
            ),
            'encode'
        );
    }

    $generator = new SplClassLoader('PasswordGenerator\Generator', '../includes/libraries');
    $generator->register();
    $generator = new PasswordGenerator\Generator\ComputerPasswordGenerator();

    // Is PHP7 being used?
    if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
        $php7generator = new SplClassLoader('PasswordGenerator\RandomGenerator', '../includes/libraries');
        $php7generator->register();
        $generator->setRandomGenerator(new PasswordGenerator\RandomGenerator\Php7RandomGenerator());
    }

    // Manage size
    $generator->setLength(($size <= 0) ? 10 : $size);

    if ($secure === true) {
        $generator->setSymbols(true);
        $generator->setLowercase(true);
        $generator->setUppercase(true);
        $generator->setNumbers(true);
    } else {
        $generator->setLowercase($lowercase);
        $generator->setUppercase($capitalize);
        $generator->setNumbers($numerals);
        $generator->setSymbols($symbols);
    }

    return prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'key' => $generator->generatePasswords(),
            'error' => '',
        ),
        'encode'
    );
}

function refreshUserItemsSeenList(
    array $SETTINGS
): string
{
    // get list of last items seen
    $arr_html = array();
    $rows = DB::query(
        'SELECT i.id AS id, i.label AS label, i.id_tree AS id_tree, l.date, i.perso AS perso, i.restricted_to AS restricted
        FROM ' . prefixTable('log_items') . ' AS l
        RIGHT JOIN ' . prefixTable('items') . ' AS i ON (l.id_item = i.id)
        WHERE l.action = %s AND l.id_user = %i
        ORDER BY l.date DESC
        LIMIT 0, 100',
        'at_shown',
        $_SESSION['user_id']
    );
    if (DB::count() > 0) {
        foreach ($rows as $record) {
            if (in_array($record['id']->id, array_column($arr_html, 'id')) === false) {
                array_push(
                    $arr_html,
                    array(
                        'id' => $record['id'],
                        'label' => htmlspecialchars(stripslashes(htmlspecialchars_decode($record['label'], ENT_QUOTES)), ENT_QUOTES),
                        'tree_id' => $record['id_tree'],
                        'perso' => $record['perso'],
                        'restricted' => $record['restricted'],
                    )
                );
                if (count($arr_html) >= (int) $SETTINGS['max_latest_items']) {
                    break;
                }
            }
        }
    }

    // get wainting suggestions
    $nb_suggestions_waiting = 0;
    if (
        isset($SETTINGS['enable_suggestion']) === true && (int) $SETTINGS['enable_suggestion'] === 1
        && ((int) $_SESSION['user_admin'] === 1 || (int) $_SESSION['user_manager'] === 1)
    ) {
        DB::query('SELECT * FROM ' . prefixTable('suggestion'));
        $nb_suggestions_waiting = DB::count();
    }

    return json_encode(
        array(
            'error' => '',
            'existing_suggestions' => $nb_suggestions_waiting,
            'html_json' => $arr_html,
        ),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
}

function sendingStatistics(
    array $SETTINGS
): void
{
    if (
        isset($SETTINGS['send_statistics_items']) === true
        && isset($SETTINGS['send_stats']) === true
        && isset($SETTINGS['send_stats_time']) === true
        && (int) $SETTINGS['send_stats'] === 1
        && (int) ($SETTINGS['send_stats_time'] + TP_ONE_DAY_SECONDS) > time()
    ) {
        // get statistics data
        $stats_data = getStatisticsData($SETTINGS);

        // get statistics items to share
        $statsToSend = [];
        $statsToSend['ip'] = $_SERVER['SERVER_ADDR'];
        $statsToSend['timestamp'] = time();
        foreach (array_filter(explode(';', $SETTINGS['send_statistics_items'])) as $data) {
            if ($data === 'stat_languages') {
                $tmp = '';
                foreach ($stats_data[$data] as $key => $value) {
                    $tmp .= $tmp === '' ? $key . '-' . $value : ',' . $key . '-' . $value;
                }
                $statsToSend[$data] = $tmp;
            } elseif ($data === 'stat_country') {
                $tmp = '';
                foreach ($stats_data[$data] as $key => $value) {
                    $tmp .= $tmp === '' ? $key . '-' . $value : ',' . $key . '-' . $value;
                }
                $statsToSend[$data] = $tmp;
            } else {
                $statsToSend[$data] = $stats_data[$data];
            }
        }

        // connect to Teampass Statistics database
        $link2 = new MeekroDB(
            'teampass.pw',
            'teampass_user',
            'ZMlEfRzKzFLZNzie',
            'teampass_followup',
            '3306',
            'utf8'
        );

        $link2->insert(
            'statistics',
            $statsToSend
        );

        // update table misc with current timestamp
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => time(),
            ),
            'type = %s AND intitule = %s',
            'admin',
            'send_stats_time'
        );

        //permits to test only once by session
        $_SESSION['temporary']['send_stats_done'] = true;
        $SETTINGS['send_stats_time'] = time();

        // save change in config file
        handleConfigFile('update', $SETTINGS, 'send_stats_time', $SETTINGS['send_stats_time']);
    }
}

function generateBugReport(
    string $data,
    array $SETTINGS
): string
{
    $config_exclude_vars = array(
        'bck_script_passkey',
        'email_smtp_server',
        'email_auth_username',
        'email_auth_pwd',
        'email_from',
    );

    // Get data
    $post_data = json_decode($data, true);

    // Read config file
    $list_of_options = '';
    $url_found = '';
    $anonym_url = '';
    $tp_config_file = '../includes/config/tp.config.php';
    $data = file($tp_config_file);
    foreach ($data as $line) {
        if (substr($line, 0, 4) === '    ') {
            // Remove extra spaces
            $line = str_replace('    ', '', $line);

            // Identify url to anonymize it
            if (strpos($line, 'cpassman_url') > 0 && empty($url_found) === true) {
                $url_found = substr($line, 19, strlen($line) - 22);
                $tmp = parse_url($url_found);
                $anonym_url = $tmp['scheme'] . '://<anonym_url>' . $tmp['path'];
                $line = "'cpassman_url' => '" . $anonym_url . "\n";
            }

            // Anonymize all urls
            if (empty($anonym_url) === false) {
                $line = str_replace($url_found, $anonym_url, $line);
            }

            // Clear some vars
            foreach ($config_exclude_vars as $var) {
                if (strpos($line, $var) > 0) {
                    $line = "'".$var."' => '<removed>'\n";
                }
            }

            // Complete line to display
            $list_of_options .= $line;
        }
    }

    // Get error
    $err = error_get_last();

    // Get 10 latest errors in Teampass
    $teampass_errors = '';
    $rows = DB::query(
        'SELECT label, date AS error_date
        FROM ' . prefixTable('log_system') . "
        WHERE `type` LIKE 'error'
        ORDER BY `date` DESC
        LIMIT 0, 10"
    );
    if (DB::count() > 0) {
        foreach ($rows as $record) {
            if (empty($teampass_errors) === true) {
                $teampass_errors = ' * ' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['error_date']) . ' - ' . $record['label'];
            } else {
                $teampass_errors .= ' * ' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['error_date']) . ' - ' . $record['label'];
            }
        }
    }

    $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWD_CLEAR, DB_NAME, (int) DB_PORT, null);

    // Now prepare text
    $txt = '### Page on which it happened
' . $post_data['current_page'] . '

### Steps to reproduce
1.
2.
3.

### Expected behaviour
Tell us what should happen


### Actual behaviour
Tell us what happens instead

### Server configuration
**Operating system**: ' . php_uname() . '

**Web server:** ' . $_SERVER['SERVER_SOFTWARE'] . '

**Database:** ' . ($link === false ? langHdl('undefined') : mysqli_get_server_info($link)) . '

**PHP version:** ' . PHP_VERSION . '

**Teampass version:** ' . TP_VERSION_FULL . '

**Teampass configuration file:**
```
' . $list_of_options . '
```

**Updated from an older Teampass or fresh install:**

### Client configuration

**Browser:** ' . $post_data['browser_name'] . ' - ' . $post_data['browser_version'] . '

**Operating system:** ' . $post_data['os'] . ' - ' . $post_data['os_archi'] . 'bits

### Logs

#### Web server error log
```
' . $err['message'] . ' - ' . $err['file'] . ' (' . $err['line'] . ')
```

#### Teampass 10 last system errors
```
' . $teampass_errors . '
```

#### Log from the web-browser developer console (CTRL + SHIFT + i)
```
Insert the log here and especially the answer of the query that failed.
```
';

    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'html' => $txt,
            'error' => '',
        ),
        'encode'
    );
}

function isUserPasswordCorrect(
    int $post_user_id,
    string $post_user_password,
    array $SETTINGS
): string
{
    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Check if user exists
        $userInfo = DB::queryFirstRow(
            'SELECT public_key, private_key, pw, auth_type
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userInfo['private_key']) === false) {
            // Get one item
            $record = DB::queryFirstRow(
                'SELECT object_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE user_id = %i',
                $post_user_id
            );

            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE object_id = %i AND user_id = %i',
                $record['object_id'],
                $post_user_id
            );

            if ($currentUserKey !== null) {
                // Decrypt itemkey with user key
                // use old password to decrypt private_key
                $_SESSION['user']['private_key'] = decryptPrivateKey($post_user_password, $userInfo['private_key']);
                $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $_SESSION['user']['private_key']);

                if (empty(base64_decode($itemKey)) === false) {
                    // GOOD password
                    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                        array(
                            'error' => false,
                            'message' => '',
                            'debug' => '',
                        ),
                        'encode'
                    );
                }
            }
            
            // Use the password check
            // load passwordLib library
            $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'] . '/includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();
            
            if ($pwdlib->verifyPasswordHash(htmlspecialchars_decode($post_user_password), $userInfo['pw']) === true) {
                // GOOD password
                return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => false,
                        'message' => '',
                        'debug' => '',
                    ),
                    'encode'
                );
            }
        }
    }

    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('password_is_not_correct'),
            'debug' => isset($itemKey) === true ? base64_decode($itemKey) : '',
        ),
        'encode'
    );
}

function changePrivateKeyEncryptionPassword(
    int $post_user_id,
    string $post_current_code,
    string $post_new_code,
    string $post_action_type,
    array $SETTINGS
): string
{
    if (empty($post_new_code) === true) {
        if (empty($_SESSION['user_pwd']) === false) {
            $post_new_code = $_SESSION['user_pwd'];
        } else {
            // no user password???
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('error_no_user_password_exists'),
                    'debug' => '',
                ),
                'encode'
            );
        }
    }

    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['private_key']) === false) {
            if ($post_action_type === 'encrypt_privkey_with_user_password') {
                // Here the user has his private key encrypted with an OTC.
                // We need to encrypt it with his real password
                $privateKey = decryptPrivateKey($post_new_code, $userData['private_key']);
                $hashedPrivateKey = encryptPrivateKey($post_current_code, $privateKey);
            } else {
                $privateKey = decryptPrivateKey($post_current_code, $userData['private_key']);
                $hashedPrivateKey = encryptPrivateKey($post_new_code, $privateKey);
            }

            // Update user account
            DB::update(
                prefixTable('users'),
                array(
                    'private_key' => $hashedPrivateKey,
                    'special' => 'none',
                ),
                'id = %i',
                $post_user_id
            );

            // Load superGlobals
            include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
            $superGlobal = new protect\SuperGlobal\SuperGlobal();

            $superGlobal->put('private_key', $privateKey, 'SESSION', 'user');
        }

        // Return
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
    }
    
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
            'debug' => '',
        ),
        'encode'
    );
}

function initializeUserPassword(
    int $post_user_id,
    string $post_special,
    string $post_user_password,
    bool $post_self_change,
    array $SETTINGS
): string
{
    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT email, auth_type, login
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['email']) === false) {
            // If user pwd is empty then generate a new one and send it to user
            if (isset($post_user_password) === false || empty($post_user_password) === true) {
                // Generate new password
                $post_user_password = generateQuickPassword();
            }

            // If LDAP enabled, then
            // check that this password is correct
            $continue = true;
            if ($userData['auth_type'] === 'ldap' && (int) $SETTINGS['ldap_mode'] === 1) {
                $continue = ldapCheckUserPassword(
                    $userData['login'],
                    $post_user_password,
                    $SETTINGS
                );
            }

            if ($continue === true) {
                // Only change if email is successfull
                // GEnerate new keys
                $userKeys = generateUserKeys($post_user_password);

                // load passwordLib library
                $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                $pwdlib->register();
                $pwdlib = new PasswordLib\PasswordLib();

                // Update user account
                DB::update(
                    prefixTable('users'),
                    array(
                        'special' => $post_special,
                        'pw' => $pwdlib->createPasswordHash($post_user_password),
                        'public_key' => $userKeys['public_key'],
                        'private_key' => $userKeys['private_key'],
                        'last_pw_change' => time(),
                    ),
                    'id = %i',
                    $post_user_id
                );

                // Return
                return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => false,
                        'message' => '',
                        'user_pwd' => $post_user_password,
                        'user_email' => $userData['email'],
                    ),
                    'encode'
                );
            }
            // Return error
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('no_email_set'),
                    'debug' => '',
                    'self_change' => $post_self_change,
                ),
                'encode'
            );
        }

        // Error
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('no_email_set'),
                'debug' => '',
            ),
            'encode'
        );
    }
    
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
            'debug' => '',
        ),
        'encode'
    );
}

function sendMailToUser(
    string $post_receipt,
    string $post_body,
    string $post_subject,
    array $post_replace,
    array $SETTINGS
): string
{
    if (count($post_replace) > 0 && is_null($post_replace) === false) {
        $post_body = str_replace(
            array_keys($post_replace),
            array_values($post_replace),
            $post_body
        );
    }
    
    $ret = sendEmail(
        $post_subject,
        $post_body,
        $post_receipt,
        $SETTINGS,
        '',
        false
    );

    $ret = json_decode($ret, true);

    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => empty($ret['error']) === true ? false : true,
            'message' => $ret['message'],
        ),
        'encode'
    );
}

function generateOneTimeCode(
    int $post_user_id,
    array $SETTINGS
): string
{
    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT email, auth_type, login
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['email']) === false) {
            // Generate pwd
            $password = generateQuickPassword();

            // GEnerate new keys
            $userKeys = generateUserKeys($password);

            // Save in DB
            DB::update(
                prefixTable('users'),
                array(
                    'public_key' => $userKeys['public_key'],
                    'private_key' => $userKeys['private_key'],
                    'special' => 'generate-keys',
                ),
                'id=%i',
                $post_user_id
            );

            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'userTemporaryCode' => $password,
                ),
                'encode'
            );
        }
        
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('no_email_set'),
            ),
            'encode'
        );
    }
        
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
        ),
        'encode'
    );
}

function startReEncryptingUserSharekeys(
    int $post_user_id,
    bool $post_self_change,
    array $SETTINGS
): string
{
    //$post_user_id = is_null($post_user_id) === true ? $_SESSION['user_id'] : $post_user_id;

    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Check if user exists
        DB::queryFirstRow(
            'SELECT *
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0) {
            // Include libraries
            include_once $SETTINGS['cpassman_dir'] . '/sources/aes.functions.php';

            // CLear old sharekeys
            if ($post_self_change === false) {
                deleteUserObjetsKeys($post_user_id, $SETTINGS);
            }

            // Continu with next step
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'step' => 'step1',
                    'userId' => $post_user_id,
                    'start' => 0,
                    'self_change' => $post_self_change,
                ),
                'encode'
            );
        }
        // Nothing to do
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('error_no_user'),
            ),
            'encode'
        );
    }

    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
        ),
        'encode'
    );
}

function continueReEncryptingUserSharekeys(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    array $SETTINGS
): string
{
    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Check if user exists
        $userInfo = DB::queryFirstRow(
            'SELECT public_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (isset($userInfo['public_key']) === true) {
            // Init
            $next_start = '';
            $next_action = '';

            // Include libraries
            include_once $SETTINGS['cpassman_dir'] . '/sources/aes.functions.php';

            // WHAT STEP TO PERFORM?
            if ($post_action === 'step0') {
                // CLear old sharekeys
                if ($post_self_change === false) {
                    deleteUserObjetsKeys($post_user_id, $SETTINGS);
                }

                $next_action = 'step1';
            }
            
            // STEP 1 - ITEMS
            elseif ($post_action === 'step1') {
                $return = continueReEncryptingUserSharekeysStep1(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );

                $next_start = $return['next_start'];
                $next_action = $return['post_action'];
            }

            // STEP 2 - LOGS
            elseif ($post_action === 'step2') {
                $return = continueReEncryptingUserSharekeysStep2(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );

                $next_start = $return['next_start'];
                $next_action = $return['post_action'];
            }

            // STEP 3 - FIELDS
            elseif ($post_action === 'step3') {
                $return = continueReEncryptingUserSharekeysStep3(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );

                $next_start = $return['next_start'];
                $next_action = $return['post_action'];
            }
            
            // STEP 4 - SUGGESTIONS
            elseif ($post_action === 'step4') {
                $return = continueReEncryptingUserSharekeysStep4(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );

                $next_start = $return['next_start'];
                $next_action = $return['post_action'];
            }
            
            // STEP 5 - FILES
            elseif ($post_action === 'step5') {
                $return = continueReEncryptingUserSharekeysStep5(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );

                $next_start = $return['next_start'];
                $next_action = $return['post_action'];
            }
            
            // STEP 6 - PERSONAL ITEMS
            elseif ($post_action === 'step6') {
                $return = continueReEncryptingUserSharekeysStep6(
                    $post_user_id,
                    $post_self_change,
                    $post_action,
                    $post_start,
                    $post_length,
                    $userInfo['public_key'],
                    $SETTINGS
                );

                $next_start = $return['next_start'];
                $next_action = $return['post_action'];
            }
            
            // Continu with next step
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'step' => isset($next_action) === true ? $next_action : '',
                    'start' => isset($next_start) === true ? $next_start : 0,
                    'userId' => $post_user_id,
                    'self_change' => $post_self_change,
                ),
                'encode'
            );
        }
        
        // Nothing to do
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
                'step' => 'finished',
                'start' => 0,
                'userId' => $post_user_id,
                'self_change' => $post_self_change,
            ),
            'encode'
        );
    }
    
    // Nothing to do
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
            'extra' => $post_user_id,
        ),
        'encode'
    );
}

function continueReEncryptingUserSharekeysStep1(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array 
{
    // Loop on items
    $rows = DB::query(
        'SELECT id, pw
        FROM ' . prefixTable('items') . '
        WHERE perso = 0
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key, increment_id
            FROM ' . prefixTable('sharekeys_items') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $_SESSION['user_id']
        );
        if ($currentUserKey === null || count($currentUserKey) === 0) continue;

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $_SESSION['user']['private_key']);
        
        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);
        
        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_items'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $_SESSION['user_id']) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );

                if (DB::count() > 0) {
                    // NOw update
                    DB::update(
                        prefixTable('sharekeys_items'),
                        array(
                            'share_key' => $share_key_for_item,
                        ),
                        'increment_id = %i',
                        $currentUserKey['increment_id']
                    );
                } else {
                    DB::insert(
                        prefixTable('sharekeys_items'),
                        array(
                            'object_id' => (int) $record['id'],
                            'user_id' => (int) $post_user_id,
                            'share_key' => $share_key_for_item,
                        )
                    );
                }
            }
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('items') . '
        WHERE perso = 0'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step2' : 'step1',
    ];
}

function continueReEncryptingUserSharekeysStep2(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    // Loop on logs
    $rows = DB::query(
        'SELECT increment_id
        FROM ' . prefixTable('log_items') . '
        WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_logs') . '
            WHERE object_id = %i AND user_id = %i',
            $record['increment_id'],
            $_SESSION['user_id']
        );

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $_SESSION['user']['private_key']);

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_logs'),
                array(
                    'object_id' => (int) $record['increment_id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $_SESSION['user_id']) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_logs'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT increment_id
        FROM ' . prefixTable('log_items') . '
        WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step3' : 'step2',
    ];
}

function continueReEncryptingUserSharekeysStep3(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    // Loop on fields
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('categories_items') . '
        WHERE encryption_type = "teampass_aes"
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_fields') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $_SESSION['user_id']
        );

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $_SESSION['user']['private_key']);

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_fields'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $_SESSION['user_id']) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_fields'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('categories_items') . '
        WHERE encryption_type = "teampass_aes"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step4' : 'step3',
    ];
}

function continueReEncryptingUserSharekeysStep4(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    // Loop on suggestions
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('suggestion') . '
        LIMIT ' . $post_start . ', ' . $post_length
    );
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_suggestions') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $_SESSION['user_id']
        );

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $_SESSION['user']['private_key']);

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_suggestions'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $_SESSION['user_id']) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_suggestions'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('suggestion')
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step5' : 'step4',
    ];
}

function continueReEncryptingUserSharekeysStep5(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    // Loop on files
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('files') . '
        WHERE status = "' . TP_ENCRYPTION_NAME . '"
        LIMIT ' . $post_start . ', ' . $post_length
    ); //aes_encryption
    foreach ($rows as $record) {
        // Get itemKey from current user
        $currentUserKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_files') . '
            WHERE object_id = %i AND user_id = %i',
            $record['id'],
            $_SESSION['user_id']
        );

        // Decrypt itemkey with admin key
        $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $_SESSION['user']['private_key']);

        // Encrypt Item key
        $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

        // Save the key in DB
        if ($post_self_change === false) {
            DB::insert(
                prefixTable('sharekeys_files'),
                array(
                    'object_id' => (int) $record['id'],
                    'user_id' => (int) $post_user_id,
                    'share_key' => $share_key_for_item,
                )
            );
        } else {
            // Get itemIncrement from selected user
            if ((int) $post_user_id !== (int) $_SESSION['user_id']) {
                $currentUserKey = DB::queryFirstRow(
                    'SELECT increment_id
                    FROM ' . prefixTable('sharekeys_items') . '
                    WHERE object_id = %i AND user_id = %i',
                    $record['id'],
                    $post_user_id
                );
            }

            // NOw update
            DB::update(
                prefixTable('sharekeys_files'),
                array(
                    'share_key' => $share_key_for_item,
                ),
                'increment_id = %i',
                $currentUserKey['increment_id']
            );
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('files') . '
        WHERE status = "' . TP_ENCRYPTION_NAME . '"'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'step6' : 'step5',
    ];
}

function continueReEncryptingUserSharekeysStep6(
    int $post_user_id,
    bool $post_self_change,
    string $post_action,
    int $post_start,
    int $post_length,
    string $user_public_key,
    array $SETTINGS
): array
{
    // IF USER IS NOT THE SAME
    if ((int) $post_user_id === (int) $_SESSION['user_id']) {
        return [
            'next_start' => 0,
            'post_action' => 'finished',
        ];
    }
    
    // Loop on persoanl items
    if (count($_SESSION['personal_folders']) > 0) {
        $rows = DB::query(
            'SELECT id, pw
            FROM ' . prefixTable('items') . '
            WHERE perso = 1 AND id_tree IN %ls
            LIMIT ' . $post_start . ', ' . $post_length,
            $_SESSION['personal_folders']
        );
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $_SESSION['user_id']
            );

            // Decrypt itemkey with admin key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $_SESSION['user']['private_key']);

            // Encrypt Item key
            $share_key_for_item = encryptUserObjectKey($itemKey, $user_public_key);

            // Save the key in DB
            if ($post_self_change === false) {
                DB::insert(
                    prefixTable('sharekeys_items'),
                    array(
                        'object_id' => (int) $record['id'],
                        'user_id' => (int) $post_user_id,
                        'share_key' => $share_key_for_item,
                    )
                );
            } else {
                // Get itemIncrement from selected user
                if ((int) $post_user_id !== (int) $_SESSION['user_id']) {
                    $currentUserKey = DB::queryFirstRow(
                        'SELECT increment_id
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE object_id = %i AND user_id = %i',
                        $record['id'],
                        $post_user_id
                    );
                }

                // NOw update
                DB::update(
                    prefixTable('sharekeys_items'),
                    array(
                        'share_key' => $share_key_for_item,
                    ),
                    'increment_id = %i',
                    $currentUserKey['increment_id']
                );
            }
        }
    }

    // SHould we change step?
    DB::query(
        'SELECT *
        FROM ' . prefixTable('items') . '
        WHERE perso = 0'
    );

    $next_start = (int) $post_start + (int) $post_length;
    return [
        'next_start' => $next_start > DB::count() ? 0 : $next_start,
        'post_action' => $next_start > DB::count() ? 'finished' : 'step6',
    ];
}

function migrateTo3_DoUserPersonalItemsEncryption(
    int $post_user_id,
    int $post_start,
    int $post_length,
    string $post_user_psk,
    array $SETTINGS
) {
    $next_step = '';

    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Check if user exists
        $userInfo = DB::queryFirstRow(
            'SELECT public_key, encrypted_psk
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0) {
            // check if psk is correct.
            if (empty($userInfo['encrypted_psk']) === false) {
                $user_key_encoded = defuse_validate_personal_key(
                    $post_user_psk,
                    $userInfo['encrypted_psk']
                );

                if (strpos($user_key_encoded, "Error ") !== false) {
                    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                        array(
                            'error' => true,
                            'message' => langHdl('bad_psk'),
                        ),
                        'encode'
                    );
                }

                // Loop on persoanl items
                $rows = DB::query(
                    'SELECT id, pw
                    FROM ' . prefixTable('items') . '
                    WHERE perso = 1 AND id_tree IN %ls
                    LIMIT ' . $post_start . ', ' . $post_length,
                    $_SESSION['personal_folders']
                );
                $countUserPersonalItems = DB::count();
                foreach ($rows as $record) {
                    if ($record['encryption_type'] !== 'teampass_aes') {
                        // Decrypt with Defuse
                        $passwd = cryption(
                            $record['pw'],
                            $user_key_encoded,
                            'decrypt',
                            $SETTINGS
                        );

                        // Encrypt with Object Key
                        $cryptedStuff = doDataEncryption($passwd['string']);

                        // Store new password in DB
                        DB::update(
                            prefixTable('items'),
                            array(
                                'pw' => $cryptedStuff['encrypted'],
                                'encryption_type' => 'teampass_aes',
                            ),
                            'id = %i',
                            $record['id']
                        );

                        // Insert in DB the new object key for this item by user
                        DB::insert(
                            prefixTable('sharekeys_items'),
                            array(
                                'object_id' => (int) $record['id'],
                                'user_id' => (int) $post_user_id,
                                'share_key' => encryptUserObjectKey($cryptedStuff['objectKey'], $userInfo['public_key']),
                            )
                        );


                        // Does this item has Files?
                        // Loop on files
                        $rows = DB::query(
                            'SELECT id, file
                            FROM ' . prefixTable('files') . '
                            WHERE status != %s
                            AND id_item = %i',
                            TP_ENCRYPTION_NAME,
                            $record['id']
                        );
                        //aes_encryption
                        foreach ($rows as $record2) {
                            // Now decrypt the file
                            prepareFileWithDefuse(
                                'decrypt',
                                $SETTINGS['path_to_upload_folder'] . '/' . $record2['file'],
                                $SETTINGS['path_to_upload_folder'] . '/' . $record2['file'] . '.delete',
                                $SETTINGS,
                                $post_user_psk
                            );

                            // Encrypt the file
                            $encryptedFile = encryptFile($record2['file'] . '.delete', $SETTINGS['path_to_upload_folder']);

                            DB::update(
                                prefixTable('files'),
                                array(
                                    'file' => $encryptedFile['fileHash'],
                                    'status' => TP_ENCRYPTION_NAME,
                                ),
                                'id = %i',
                                $record2['id']
                            );

                            // Save key
                            DB::insert(
                                prefixTable('sharekeys_files'),
                                array(
                                    'object_id' => (int) $record2['id'],
                                    'user_id' => (int) $_SESSION['user_id'],
                                    'share_key' => encryptUserObjectKey($encryptedFile['objectKey'], $_SESSION['user']['public_key']),
                                )
                            );

                            // Unlink original file
                            unlink($SETTINGS['path_to_upload_folder'] . '/' . $record2['file']);
                        }
                    }
                }

                // SHould we change step?
                $next_start = (int) $post_start + (int) $post_length;
                if ($next_start > $countUserPersonalItems) {
                    // Now update user
                    DB::update(
                        prefixTable('users'),
                        array(
                            'special' => 'none',
                            'upgrade_needed' => 0,
                            'encrypted_psk' => '',
                        ),
                        'id = %i',
                        $post_user_id
                    );

                    $next_step = 'finished';
                    $next_start = 0;
                }

                // Continu with next step
                return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => false,
                        'message' => '',
                        'step' => $next_step,
                        'start' => $next_start,
                        'userId' => $post_user_id
                    ),
                    'encode'
                );
            }
        }
        
        // Nothing to do
        return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => true,
                'message' => langHdl('error_no_user'),
            ),
            'encode'
        );
    }
    
    // Nothing to do
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
        ),
        'encode'
    );
}


function getUserInfo(
    int $post_user_id,
    string $post_fields,
    array $SETTINGS
)
{
    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT '.$post_fields.'
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0) {
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'queryResults' => $userData,
                ),
                'encode'
            );
        }
    }
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
        ),
        'encode'
    );
}

function changeUserAuthenticationPassword(
    int $post_user_id,
    string $post_current_pwd,
    string $post_new_pwd,
    array $SETTINGS
)
{
    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT auth_type, login, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        if (DB::count() > 0 && empty($userData['private_key']) === false) {
            // Now check if current password is correct
            // For this, just check if it is possible to decrypt the privatekey
            // And compare it to the one in session
            $privateKey = decryptPrivateKey($post_current_pwd, $userData['private_key']);

            // Load superGlobals
            include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
            $superGlobal = new protect\SuperGlobal\SuperGlobal();

            if ($superGlobal->get('private_key', 'SESSION', 'user') === $privateKey) {
                // Encrypt it with new password
                $hashedPrivateKey = encryptPrivateKey($post_new_pwd, $privateKey);

                // Generate new hash for auth password
                // load passwordLib library
                $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                $pwdlib->register();
                $pwdlib = new PasswordLib\PasswordLib();

                // Prepare variables
                $newPw = $pwdlib->createPasswordHash($post_new_pwd);

                // Update user account
                DB::update(
                    prefixTable('users'),
                    array(
                        'private_key' => $hashedPrivateKey,
                        'pw' => $newPw,
                        'special' => 'none',
                    ),
                    'id = %i',
                    $post_user_id
                );

                $superGlobal->put('private_key', $privateKey, 'SESSION', 'user');

                return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => false,
                        'message' => langHdl('done'),'',
                    ),
                    'encode'
                );
            }
            
            // ERROR
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('bad_password'),
                ),
                'encode'
            );
        }
    }
        
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
        ),
        'encode'
    );
}

            
function changeUserLDAPAuthenticationPassword(
    int $post_user_id,
    string $post_previous_pwd,
    string $post_current_pwd,
    array $SETTINGS
)
{
    if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
        // Get user info
        $userData = DB::queryFirstRow(
            'SELECT auth_type, login, private_key, special
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $post_user_id
        );
        
        if (DB::count() > 0 && empty($userData['private_key']) === false) {
            // Now check if current password is correct (only if not ldap)
            if ($userData['auth_type'] === 'ldap' && $userData['special'] === 'auth-pwd-change') {
                // As it is a change for an LDAP user
                
                // Now check if current password is correct
                // For this, just check if it is possible to decrypt the privatekey
                // And compare it to the one in session
                $privateKey = decryptPrivateKey($post_previous_pwd, $userData['private_key']);

                // Encrypt it with new password
                $hashedPrivateKey = encryptPrivateKey($post_current_pwd, $privateKey);

                // Update user account
                DB::update(
                    prefixTable('users'),
                    array(
                        'private_key' => $hashedPrivateKey,
                        'special' => 'none',
                    ),
                    'id = %i',
                    $post_user_id
                );

                // Load superGlobals
                include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
                $superGlobal = new protect\SuperGlobal\SuperGlobal();
                $superGlobal->put('private_key', $privateKey, 'SESSION', 'user');

                return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => false,
                        'message' => langHdl('done'),'',
                    ),
                    'encode'
                );
            }

            // For this, just check if it is possible to decrypt the privatekey
            // And try to decrypt one existing key
            $privateKey = decryptPrivateKey($post_previous_pwd, $userData['private_key']);

            if (empty($privateKey) === true) {
                return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('password_is_not_correct'),
                    ),
                    'encode'
                );
            }

            // Test if possible to decvrypt one key
            // Get one item
            $record = DB::queryFirstRow(
                'SELECT id, pw
                FROM ' . prefixTable('items') . '
                WHERE perso = 0'
            );

            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $post_user_id
            );

            if (count($currentUserKey) > 0) {
                // Decrypt itemkey with user key
                // use old password to decrypt private_key
                $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $privateKey);
                
                if (empty(base64_decode($itemKey)) === false) {
                    // GOOD password
                    // Encrypt it with current password
                    $hashedPrivateKey = encryptPrivateKey($post_current_pwd, $privateKey);
                    
                    // Update user account
                    DB::update(
                        prefixTable('users'),
                        array(
                            'private_key' => $hashedPrivateKey,
                            'special' => 'none',
                        ),
                        'id = %i',
                        $post_user_id
                    );
                    
                    // Load superGlobals
                    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
                    $superGlobal = new protect\SuperGlobal\SuperGlobal();
                    $superGlobal->put('private_key', $privateKey, 'SESSION', 'user');

                    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                        array(
                            'error' => false,
                            'message' => langHdl('done'),
                        ),
                        'encode'
                    );
                }
            }
            
            // ERROR
            return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('bad_password'),
                ),
                'encode'
            );
        }
    }

    // ERROR
    return prepareExchangedData(
    $SETTINGS['cpassman_dir'],
        array(
            'error' => true,
            'message' => langHdl('error_no_user'),
        ),
        'encode'
    );
}


function increaseSessionDuration(
    int $duration
): string
{
    // check if session is not already expired.
    if ($_SESSION['sessionDuration'] > time()) {
        // Calculate end of session
        $_SESSION['sessionDuration'] = (int) ($_SESSION['sessionDuration'] + $duration);
        // Update table
        DB::update(
            prefixTable('users'),
            array(
                'session_end' => $_SESSION['sessionDuration'],
            ),
            'id = %i',
            $_SESSION['user_id']
        );
        // Return data
        return '[{"new_value":"' . $_SESSION['sessionDuration'] . '"}]';
    }
    
    return '[{"new_value":"expired"}]';
}