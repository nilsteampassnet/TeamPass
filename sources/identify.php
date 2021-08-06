<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      identify.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2021 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use LdapRecord\Container;

require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (isset($_SESSION['CPM']) === false || (int) $_SESSION['CPM'] !== 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

if (! isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir']) === true || $SETTINGS['cpassman_dir'] === '.') {
    $SETTINGS = [];
    $SETTINGS['cpassman_dir'] = '..';
}

require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';

// If Debug then clean the files
if (DEBUGLDAP === true) {
    define('DEBUGLDAPFILE', $SETTINGS['path_to_files_folder'] . '/ldap.debug.txt');
    $fp = fopen(DEBUGLDAPFILE, 'w');
    fclose($fp);
}
if (DEBUGDUO === true) {
    define('DEBUGDUOFILE', $SETTINGS['path_to_files_folder'] . '/duo.debug.txt');
    $fp = fopen(DEBUGDUOFILE, 'w');
    fclose($fp);
}

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
$post_sig_response = filter_input(INPUT_POST, 'sig_response', FILTER_SANITIZE_STRING);
//$post_cardid = filter_input(INPUT_POST, 'cardid', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

// connect to the server
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
if ($post_type === 'identify_duo_user') {
    //--------
    // DUO AUTHENTICATION
    //--------
    // This step creates the DUO request encrypted key

    // Get DUO keys
    $duoData = DB::query(
        'SELECT intitule, valeur
        FROM ' . prefixTable('misc') . '
        WHERE type = %s',
        'duoSecurity'
    );
    $_GLOBALS = [];
    foreach ($duoData as $value) {
        $_GLOBALS[strtoupper($value['intitule'])] = $value['valeur'];
    }

    // load library
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/DuoSecurity/Duo.php';
    $sig_request = Duo::signRequest(
        $_GLOBALS['IKEY'],
        $_GLOBALS['SKEY'],
        $_GLOBALS['AKEY'],
        $post_login
    );
    if (DEBUGDUO === true) {
        debugIdentify(
            DEBUGDUO,
            DEBUGDUOFILE,
            "\n\n-----\n\n" .
                'sig request : ' . $post_login . "\n" .
                'resp : ' . $sig_request . "\n"
        );
    }

    // load csrfprotector
    $csrfp_config = include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/csrfp/libs/csrfp.config.php';
    // return result
    echo '[{"sig_request" : "' . $sig_request . '" , "csrfp_token" : "' . $csrfp_config['CSRFP_TOKEN'] . '" , "csrfp_key" : "' . filter_var($_COOKIE[$csrfp_config['CSRFP_TOKEN']], FILTER_SANITIZE_STRING) . '"}]';
// ---
    // ---
} elseif ($post_type === 'identify_duo_user_check') {
    //--------
    // DUO AUTHENTICATION
    // this step is verifying the response received from the server
    //--------

    // load library
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/DuoSecurity/Duo.php';
    $authenticated_username = Duo::verifyResponse(
        $SETTINGS['duo_ikey'],
        $SETTINGS['duo_skey'],
        $SETTINGS['duo_akey'],
        $post_sig_response
    );
    if (DEBUGDUO === true) {
        debugIdentify(
            DEBUGDUO,
            DEBUGDUOFILE,
            "\n\n-----\n\n" .
                'sig response : ' . $post_sig_response . "\n" .
                'resp : ' . $authenticated_username . "\n"
        );
    }

    // return the response (which should be the user name)
    if ($authenticated_username === $post_login) {
        // Check if this account exists in Teampass or only in LDAP
        if (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1) {
            // is user in Teampass?
            DB::queryfirstrow(
                'SELECT id
                FROM ' . prefixTable('users') . '
                WHERE login = %s',
                $post_login
            );
            if (DB::count() === 0) {
                // Ask your administrator to create your account in Teampass
                echo '[{"authenticated_username" : "ERR|This user is not yet imported inside Teampass."}]';
            }
        }

        echo '[{"authenticated_username" : "' . $authenticated_username . '"}]';
    } else {
        echo '[{"authenticated_username" : "' . $authenticated_username . '"}]';
    }
    // ---
    // ---
} elseif ($post_type === 'identify_user') {
    //--------
    // NORMAL IDENTICATION STEP
    //--------

    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();
    // Prepare GET variables
    $sessionPwdAttempts = $superGlobal->get('pwd_attempts', 'SESSION');
    // increment counter of login attempts
    if ($sessionPwdAttempts === '') {
        $sessionPwdAttempts = 1;
    } else {
        ++$sessionPwdAttempts;
    }

    $superGlobal->put('pwd_attempts', $sessionPwdAttempts, 'SESSION');
    // manage brute force
    if ($sessionPwdAttempts <= 3) {
        $sessionPwdAttempts = 0;
        // identify the user through Teampass process
        identifyUser(
            $post_data,
            $SETTINGS
        );
    } elseif (isset($_SESSION['next_possible_pwd_attempts']) && time() > $_SESSION['next_possible_pwd_attempts'] && $sessionPwdAttempts > 3) {
        $sessionPwdAttempts = 0;
        // identify the user through Teampass process
        identifyUser(
            $post_data,
            $SETTINGS
        );
    } else {
        $_SESSION['next_possible_pwd_attempts'] = time() + 10;
        // Encrypt data to return
        echo prepareExchangedData(
            [
                'value' => 'bruteforce_wait',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => true,
                'message' => langHdl('error_bad_credentials_more_than_3_times'),
            ],
            'encode'
        );
        return false;
    }
    // ---
    // ---
    // ---
} elseif ($post_type === 'get2FAMethods') {
    //--------
    // Get MFA methods
    //--------
    //

    // Encrypt data to return
    echo prepareExchangedData(
        [
            'agses' => isset($SETTINGS['agses_authentication_enabled']) === true
                && (int) $SETTINGS['agses_authentication_enabled'] === 1 ? true : false,
            'google' => isset($SETTINGS['google_authentication']) === true
                && (int) $SETTINGS['google_authentication'] === 1 ? true : false,
            'yubico' => isset($SETTINGS['yubico_authentication']) === true
                && (int) $SETTINGS['yubico_authentication'] === 1 ? true : false,
            'duo' => isset($SETTINGS['duo']) === true
                && (int) $SETTINGS['duo'] === 1 ? true : false,
        ],
        'encode'
    );
    return false;
}

/**
 * Complete authentication of user through Teampass
 *
 * @param string $sentData Credentials
 * @param array $SETTINGS Teampass settings
 *
 * @return bool
 */
function identifyUser(string $sentData, array $SETTINGS): bool
{
    // Load config
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
    include_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
    header('Content-type: text/html; charset=utf-8');
    error_reporting(E_ERROR);
    include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
    include_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/voku/helper/AntiXSS.php';
    $antiXss = new voku\helper\AntiXSS();
    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();
    // Prepare GET variables
    $sessionUserLanguage = $superGlobal->get('user_language', 'SESSION');
    $sessionKey = $superGlobal->get('key', 'SESSION');
    $sessionAdmin = $superGlobal->get('user_admin', 'SESSION');
    $sessionPwdAttempts = $superGlobal->get('pwd_attempts', 'SESSION');
    $sessionUrl = $superGlobal->get('initial_url', 'SESSION');
    $server = [];
    $server['PHP_AUTH_USER'] = $superGlobal->get('PHP_AUTH_USER', 'SERVER');
    $server['PHP_AUTH_PW'] = $superGlobal->get('PHP_AUTH_PW', 'SERVER');

    // connect to the server
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
    if (defined('DB_PASSWD_CLEAR') === false) {
        define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
    }
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = DB_PASSWD_CLEAR;
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    // User's language loading
    include_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $sessionUserLanguage . '.php';
    //echo $dataReceived." -->".empty($sessionKey)."<-- ".$sessionKey." ** " ;
    // decrypt and retreive data in JSON format
    if (empty($sessionKey) === true) {
        $dataReceived = $sentData;
    } else {
        $dataReceived = prepareExchangedData($sentData, 'decode', $sessionKey);
        $superGlobal->put('key', $sessionKey, 'SESSION');
    }

    // prepare variables
    if (
        isset($SETTINGS['enable_http_request_login']) === true
        && (int) $SETTINGS['enable_http_request_login'] === 1
        && isset($server['PHP_AUTH_USER']) === true
        && $server['PHP_AUTH_USER'] !== null
        && isset($SETTINGS['maintenance_mode']) === true
        && (int) $SETTINGS['maintenance_mode'] === 1
    ) {
        if (strpos($server['PHP_AUTH_USER'], '@') !== false) {
            $username = explode('@', filter_var($server['PHP_AUTH_USER'], FILTER_SANITIZE_STRING))[0];
        } elseif (strpos($server['PHP_AUTH_USER'], '\\') !== false) {
            $username = explode('\\', filter_var($server['PHP_AUTH_USER'], FILTER_SANITIZE_STRING))[1];
        } else {
            $username = filter_var($server['PHP_AUTH_USER'], FILTER_SANITIZE_STRING);
        }
        $passwordClear = $server['PHP_AUTH_PW'];
    } else {
        $passwordClear = filter_var($dataReceived['pw'], FILTER_SANITIZE_STRING);
        $username = filter_var($dataReceived['login'], FILTER_SANITIZE_STRING);
    }

    // Brute force management
    if ($sessionPwdAttempts > 3) {
        $superGlobal->put('next_possible_pwd_attempts', time() + 10, 'SESSION');
        $superGlobal->put('pwd_attempts', 0, 'SESSION');
        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
        echo prepareExchangedData(
            [
                'value' => 'bruteforce_wait',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => 0,
                'error' => true,
                'message' => langHdl('error_bad_credentials_more_than_3_times'),
            ],
            'encode'
        );
        return false;
    }

    // User's 2FA method
    $user_2fa_selection = filter_var($dataReceived['user_2fa_selection'], FILTER_SANITIZE_STRING);
    // Check 2FA
    if ((((int) $SETTINGS['yubico_authentication'] === 1 && empty($user_2fa_selection) === true)
            || ((int) $SETTINGS['google_authentication'] === 1 && empty($user_2fa_selection) === true)
            || ((int) $SETTINGS['duo'] === 1 && empty($user_2fa_selection) === true))
        && ($username !== 'admin' || ((int) $SETTINGS['admin_2fa_required'] === 1 && $username === 'admin'))
    ) {
        echo prepareExchangedData(
            [
                'value' => '2fa_not_set',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => '2fa_not_set',
                'message' => langHdl('2fa_credential_not_correct'),
            ],
            'encode'
        );
        return false;
    }

    // Check if user exists
    $userInfo = DB::queryFirstRow(
        'SELECT *
        FROM ' . prefixTable('users') . '
        WHERE login=%s',
        $username
    );
    $counter = DB::count();
    // User doesn't exist then stop
    if ($counter === 0) {
        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
        echo prepareExchangedData(
            [
                'error' => 'user_not_exists',
                'message' => langHdl('error_bad_credentials'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
            ],
            'encode'
        );
        return false;
    }

    // has user to be auth with mfa?
    if (is_null($userInfo['fonction_id']) === true) {
        $userInfo['fonction_id'] = false;
    } else {
        $userInfo['mfa_auth_requested'] = mfa_auth_requested(
            $userInfo['fonction_id'],
            is_null($SETTINGS['mfa_for_roles']) === true ? '' : $SETTINGS['mfa_for_roles']
        );
    }

    // Manage Maintenance mode
    if (
        isset($SETTINGS['maintenance_mode']) === true && (int) $SETTINGS['maintenance_mode'] === 1
        && (int) $userInfo['admin'] === 0
    ) {
        echo prepareExchangedData(
            [
                'value' => '',
                'user_admin' => (int) $userInfo['admin'],
                'initial_url' => '',
                'pwd_attempts' => '',
                'error' => 'maintenance_mode_enabled',
                'message' => '',
            ],
            'encode'
        );
        return false;
    }

    $user_initial_creation_through_ldap = false;
    $userPasswordVerified = false;
    $ldapConnection = false;
    $return = '';
    // Prepare LDAP connection if set up
    if (
        isset($SETTINGS['ldap_mode']) === true
        && (int) $SETTINGS['ldap_mode'] === 1
        && $username !== 'admin'
        && $userInfo['auth_type'] === 'ldap'
    ) {
        $ldapConnection = true;
        $retLDAP = authenticateThroughAD(
            $username,
            $userInfo,
            $passwordClear,
            $SETTINGS
        );
        if ($retLDAP['error'] === true) {
            echo prepareExchangedData(
                [
                    'value' => '',
                    'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                    'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                    'pwd_attempts' => (int) $sessionPwdAttempts,
                    'error' => true,
                    'message' => $retLDAP['message'],
                ],
                'encode'
            );
            return false;
        }
        $userPasswordVerified = true;
    }

    // Check Yubico
    if (
        isset($SETTINGS['yubico_authentication']) === true
        && (int) $SETTINGS['yubico_authentication'] === 1
        && ((int) $userInfo['admin'] !== 1 || ((int) $SETTINGS['admin_2fa_required'] === 1 && (int) $userInfo['admin'] === 1))
        && $user_2fa_selection === 'yubico'
        && $userInfo['mfa_auth_requested'] === true
    ) {
        $ret = yubicoMFACheck(
            $dataReceived,
            $userInfo,
            $SETTINGS
        );
        if ($ret['error'] !== '') {
            echo prepareExchangedData(
                $ret,
                'encode'
            );
            return false;
        }
    }

    // check GA code
    if (
        isset($SETTINGS['google_authentication']) === true
        && (int) $SETTINGS['google_authentication'] === 1
        && ($username !== 'admin' || ((int) $SETTINGS['admin_2fa_required'] === 1 && $username === 'admin'))
        && $user_2fa_selection === 'google'
        && $userInfo['mfa_auth_requested'] === true
    ) {
        $ret = googleMFACheck(
            $username,
            $userInfo,
            $dataReceived,
            $SETTINGS
        );
        if ($ret['error'] !== false) {
            logEvents($SETTINGS, 'failed_auth', 'wrong_mfa_code', '', stripslashes($username), stripslashes($username));
            echo prepareExchangedData(
                $ret,
                'encode'
            );
            return false;
            // ---
        }
        $user_initial_creation_through_ldap = $ret['user_initial_creation_through_ldap'];
        // Manage 1st usage of Google MFA
        if (count($ret['firstTime']) > 0) {
            echo prepareExchangedData(
                $ret['firstTime'],
                'encode'
            );
            return false;
        }
    }

    // If admin user then check if folder install exists
    // if yes then refuse connection
    if ((int) $userInfo['admin'] === 1 && is_dir('../install') === true) {
        echo prepareExchangedData(
            [
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => true,
                'message' => langHdl('remove_install_folder'),
            ],
            'encode'
        );
        return false;
    }

    // Check user and password
    if ($userPasswordVerified === false && (int) checkCredentials($passwordClear, $userInfo, $dataReceived, $username, $SETTINGS) !== 1) {
        echo prepareExchangedData(
            [
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => 'user_not_exists2',
                'message' => langHdl('error_bad_credentials'),
            ],
            'encode'
        );
        return false;
    }

    // Can connect if
    // 1- no LDAP mode + user enabled + pw ok
    // 2- LDAP mode + user enabled + ldap connection ok + user is not admin
    // 3-  LDAP mode + user enabled + pw ok + usre is admin
    // This in order to allow admin by default to connect even if LDAP is activated
    if ((isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 0
            && (int) $userInfo['disabled'] === 0)
        || (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1
            && $ldapConnection === true && (int) $userInfo['disabled'] === 0 && $username !== 'admin')
        || (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 2
            && $ldapConnection === true && (int) $userInfo['disabled'] === 0 && $username !== 'admin')
        || (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1
            && $username === 'admin' && (int) $userInfo['disabled'] === 0)
        || (isset($SETTINGS['ldap_and_local_authentication']) === true && (int) $SETTINGS['ldap_and_local_authentication'] === 1
            && isset($SETTINGS['ldap_mode']) === true && in_array($SETTINGS['ldap_mode'], ['1', '2']) === true
            && (int) $userInfo['disabled'] === 0)
    ) {
        $superGlobal->put('autoriser', true, 'SESSION');
        $superGlobal->put('pwd_attempts', 0, 'SESSION');
        /*// Debug
                debugIdentify(
                    DEBUGDUO,
                    DEBUGDUOFILE,
                    "User's token: " . $key . "\n"
                );*/

        // Check if any unsuccessfull login tries exist
        //$arrAttempts = array();
        $rows = DB::query(
            'SELECT date
            FROM ' . prefixTable('log_system') . "
            WHERE field_1 = %s
            AND type = 'failed_auth'
            AND label = 'user_password_not_correct'
            AND date >= %s AND date < %s",
            $userInfo['login'],
            $userInfo['last_connexion'],
            time()
        );
        //$arrAttempts['nb'] = DB::count();
        //$arrAttempts['shown'] = DB::count() === 0 ? true : false;
        $arrAttempts = [];
        if (DB::count() > 0) {
            foreach ($rows as $record) {
                array_push(
                    $arrAttempts,
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date'])
                );
            }
        }
        $superGlobal->put('unsuccessfull_login_attempts_list', $arrAttempts, 'SESSION', 'user');
        $superGlobal->put('unsuccessfull_login_attempts_shown', DB::count() === 0 ? true : false, 'SESSION', 'user');
        $superGlobal->put('unsuccessfull_login_attempts_nb', DB::count(), 'SESSION', 'user');
        // Log into DB the user's connection
        if (
            isset($SETTINGS['log_connections']) === true
            && (int) $SETTINGS['log_connections'] === 1
        ) {
            logEvents($SETTINGS, 'user_connection', 'connection', (string) $userInfo['id'], stripslashes($username));
        }
        // Save account in SESSION
        $superGlobal->put('login', stripslashes($username), 'SESSION');
        $superGlobal->put('name', stripslashes($userInfo['name']), 'SESSION');
        $superGlobal->put('lastname', stripslashes($userInfo['lastname']), 'SESSION');
        $superGlobal->put('user_id', (int) $userInfo['id'], 'SESSION');
        $superGlobal->put('user_pwd', $passwordClear, 'SESSION');
        $superGlobal->put('admin', $userInfo['admin'], 'SESSION');
        $superGlobal->put('user_manager', $userInfo['gestionnaire'], 'SESSION');
        $superGlobal->put('user_can_manage_all_users', $userInfo['can_manage_all_users'], 'SESSION');
        $superGlobal->put('user_read_only', $userInfo['read_only'], 'SESSION');
        $superGlobal->put('last_pw_change', (int) $userInfo['last_pw_change'], 'SESSION');
        $superGlobal->put('last_pw', $userInfo['last_pw'], 'SESSION');
        $superGlobal->put('can_create_root_folder', $userInfo['can_create_root_folder'], 'SESSION');
        $superGlobal->put('personal_folder', $userInfo['personal_folder'], 'SESSION');
        $superGlobal->put('user_language', $userInfo['user_language'], 'SESSION');
        $superGlobal->put('user_email', $userInfo['email'], 'SESSION');
        $superGlobal->put('user_ga', $userInfo['ga'], 'SESSION');
        $superGlobal->put('user_avatar', $userInfo['avatar'], 'SESSION');
        $superGlobal->put('user_avatar_thumb', $userInfo['avatar_thumb'], 'SESSION');
        $superGlobal->put('user_upgrade_needed', $userInfo['upgrade_needed'], 'SESSION');
        $superGlobal->put('user_force_relog', $userInfo['force-relog'], 'SESSION');
        // get personal settings
        if (! isset($userInfo['treeloadstrategy']) || empty($userInfo['treeloadstrategy'])) {
            $userInfo['treeloadstrategy'] = 'full';
        }
        $superGlobal->put('treeloadstrategy', $userInfo['treeloadstrategy'], 'SESSION', 'user');
        $superGlobal->put('agses-usercardid', $userInfo['agses-usercardid'], 'SESSION', 'user');
        $superGlobal->put('user_language', $userInfo['user_language'], 'SESSION', 'user');
        $superGlobal->put('usertimezone', $userInfo['usertimezone'], 'SESSION', 'user');
        $superGlobal->put('session_duration', $dataReceived['duree_session'] * 60, 'SESSION', 'user');
        $superGlobal->put('api-key', $userInfo['user_api_key'], 'SESSION', 'user');
        $superGlobal->put('special', $userInfo['special'], 'SESSION', 'user');
        $superGlobal->put('auth_type', $userInfo['auth_type'], 'SESSION', 'user');
        // manage session expiration
        $superGlobal->put('sessionDuration', (int) (time() + ($dataReceived['duree_session'] * 60)), 'SESSION');

        /*
        * CHECK PASSWORD VALIDITY
        * Don't take into consideration if LDAP in use
        */
        if (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1) {
            $superGlobal->put('validite_pw', true, 'SESSION');
            $superGlobal->put('last_pw_change', true, 'SESSION');
        } else {
            if (isset($userInfo['last_pw_change']) === true) {
                if ((int) $SETTINGS['pw_life_duration'] === 0) {
                    $superGlobal->put('user_force_relog', 'infinite', 'SESSION');
                    $superGlobal->put('validite_pw', true, 'SESSION');
                } else {
                    $superGlobal->put(
                        'numDaysBeforePwExpiration',
                        $SETTINGS['pw_life_duration'] - round(
                            (mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')) - $superGlobal->get('last_pw_change', 'SESSION')) / (24 * 60 * 60)
                        ),
                        'SESSION'
                    );
                    if ($superGlobal->get('numDaysBeforePwExpiration', 'SESSION') <= 0) {
                        $superGlobal->put('validite_pw', false, 'SESSION');
                    } else {
                        $superGlobal->put('validite_pw', true, 'SESSION');
                    }
                }
            } else {
                $superGlobal->put('validite_pw', false, 'SESSION');
            }
        }

        if (empty($userInfo['last_connexion'])) {
            $superGlobal->put('last_connection', (int) time(), 'SESSION');
        } else {
            $superGlobal->put('last_connection', (int) $userInfo['last_connexion'], 'SESSION');
        }

        if (empty($userInfo['latest_items']) === false) {
            $superGlobal->put('latest_items', explode(';', $userInfo['latest_items']), 'SESSION');
        } else {
            $superGlobal->put('latest_items', [], 'SESSION');
        }
        if (empty($userInfo['favourites']) === false) {
            $superGlobal->put('favourites', explode(';', $userInfo['favourites']), 'SESSION');
        } else {
            $superGlobal->put('favourites', [], 'SESSION');
        }

        if (empty($userInfo['groupes_visibles']) === false) {
            $superGlobal->put(
                'groupes_visibles',
                explode(';', $userInfo['groupes_visibles']),
                'SESSION'
            );
        } else {
            $superGlobal->put('groupes_visibles', [], 'SESSION');
        }
        if (empty($userInfo['groupes_interdits']) === false) {
            $superGlobal->put(
                'no_access_folders',
                explode(';', $userInfo['groupes_visibles']),
                'SESSION'
            );
        } else {
            $superGlobal->put('no_access_folders', [], 'SESSION');
        }
        
        // User's roles
        if (strpos($userInfo['fonction_id'] !== NULL ? (string) $userInfo['fonction_id'] : '', ',') !== -1) {
            // Convert , to ;
            $userInfo['fonction_id'] = str_replace(',', ';', $userInfo['fonction_id']);
            DB::update(
                prefixTable('users'),
                [
                    'fonction_id' => $userInfo['fonction_id'],
                ],
                'id = %i',
                $superGlobal->get('user_id', 'SESSION')
            );
        }
        $superGlobal->put('fonction_id', $userInfo['fonction_id'], 'SESSION');
        $superGlobal->put('user_roles', array_filter(explode(';', $userInfo['fonction_id'])), 'SESSION');
        // build array of roles
        $superGlobal->put('user_pw_complexity', 0, 'SESSION');
        $superGlobal->put('arr_roles', [], 'SESSION');
        foreach ($superGlobal->get('user_roles', 'SESSION') as $role) {
            $resRoles = DB::queryFirstRow(
                'SELECT title, complexity
                FROM ' . prefixTable('roles_title') . '
                WHERE id=%i',
                $role
            );
            $superGlobal->put(
                $role,
                [
                    'id' => $role,
                    'title' => $resRoles['title'],
                ],
                'SESSION',
                'arr_roles'
            );
            // get highest complexity
            if (intval($superGlobal->get('user_pw_complexity', 'SESSION')) < intval($resRoles['complexity'])) {
                $superGlobal->put('user_pw_complexity', $resRoles['complexity'], 'SESSION');
            }
        }

        // build complete array of roles
        $superGlobal->put('arr_roles_full', [], 'SESSION');
        $rows = DB::query('SELECT id, title FROM ' . prefixTable('roles_title') . ' ORDER BY title ASC');
        foreach ($rows as $record) {
            $superGlobal->put(
                $record['id'],
                [
                    'id' => $record['id'],
                    'title' => $record['title'],
                ],
                'SESSION',
                'arr_roles_full'
            );
        }
        // Set some settings
        $SETTINGS['update_needed'] = '';
        // User signature keys
        $superGlobal->put('public_key', $userInfo['public_key'], 'SESSION', 'user');
        if (is_null($userInfo['private_key']) === true || empty($userInfo['private_key']) === true || $userInfo['private_key'] === 'none') {
            // No keys have been generated yet
            // Create them
            $userKeys = generateUserKeys($passwordClear);
            $superGlobal->put('private_key', $userKeys['private_key_clear'], 'SESSION', 'user');
            $arrayUserKeys = [
                'public_key' => $userKeys['public_key'],
                'private_key' => $userKeys['private_key'],
            ];
        } elseif ($userInfo['special'] === 'generate-keys') {
            $arrayUserKeys = [];
        } else {
            // Uncrypt private key
            $superGlobal->put('private_key', decryptPrivateKey($passwordClear, $userInfo['private_key']), 'SESSION', 'user');
            $arrayUserKeys = [];
        }

        // Update table
        DB::update(
            prefixTable('users'),
            array_merge(
                [
                    'key_tempo' => $superGlobal->get('key', 'SESSION'),
                    'last_connexion' => time(),
                    'timestamp' => time(),
                    'disabled' => 0,
                    'no_bad_attempts' => 0,
                    'session_end' => $superGlobal->get('sessionDuration', 'SESSION'),
                    'user_ip' => $dataReceived['client'],
                ],
                $arrayUserKeys
            ),
            'id=%i',
            $userInfo['id']
        );
        // Debug
        debugIdentify(
            DEBUGDUO,
            DEBUGDUOFILE,
            "Preparing to identify the user rights\n"
        );
        // Get user's rights
        if ($user_initial_creation_through_ldap === false) {
            identifyUserRights(
                $userInfo['groupes_visibles'],
                $superGlobal->get('no_access_folders', 'SESSION'),
                $userInfo['admin'],
                $userInfo['fonction_id'],
                $SETTINGS
            );
        } else {
            // is new LDAP user. Show only his personal folder
            if ($SETTINGS['enable_pf_feature'] === '1') {
                $superGlobal->put('personal_visible_groups', [$userInfo['id']], 'SESSION');
                $superGlobal->put('personal_folders', [$userInfo['id']], 'SESSION');
            } else {
                $superGlobal->put('personal_visible_groups', [], 'SESSION');
                $superGlobal->put('personal_folders', [], 'SESSION');
            }
            $superGlobal->put('all_non_personal_folders', [], 'SESSION');
            $superGlobal->put('groupes_visibles', [], 'SESSION');
            $superGlobal->put('read_only_folders', [], 'SESSION');
            $superGlobal->put('list_folders_limited', '', 'SESSION');
            $superGlobal->put('list_folders_editable_by_role', [], 'SESSION');
            $superGlobal->put('list_restricted_folders_for_items', [], 'SESSION');
            $superGlobal->put('nb_folders', 1, 'SESSION');
            $superGlobal->put('nb_roles', 0, 'SESSION');
        }
        // Get some more elements
        $superGlobal->put('screenHeight', $dataReceived['screenHeight'], 'SESSION');
        // Get last seen items
        $superGlobal->put('latest_items_tab', [], 'SESSION');
        $superGlobal->put('nb_roles', 0, 'SESSION');
        foreach ($superGlobal->get('latest_items', 'SESSION') as $item) {
            if (! empty($item)) {
                $dataLastItems = DB::queryFirstRow(
                    'SELECT id,label,id_tree
                    FROM ' . prefixTable('items') . '
                    WHERE id=%i',
                    $item
                );
                $superGlobal->put(
                    $item,
                    [
                        'id' => $item,
                        'label' => $dataLastItems['label'],
                        'url' => 'index.php?page=items&amp;group=' . $dataLastItems['id_tree'] . '&amp;id=' . $item,
                    ],
                    'SESSION',
                    'latest_items_tab'
                );
            }
        }
        // send back the random key
        $return = $dataReceived['randomstring'];
        // Send email
        if (
            isset($SETTINGS['enable_send_email_on_user_login'])
            && (int) $SETTINGS['enable_send_email_on_user_login'] === 1
            && (int) $sessionAdmin !== 1
        ) {
            // get all Admin users
            $receivers = '';
            $rows = DB::query('SELECT email FROM ' . prefixTable('users') . " WHERE admin = %i and email != ''", 1);
            foreach ($rows as $record) {
                if (empty($receivers)) {
                    $receivers = $record['email'];
                } else {
                    $receivers = ',' . $record['email'];
                }
            }
            // Add email to table
            DB::insert(
                prefixTable('emails'),
                [
                    'timestamp' => time(),
                    'subject' => langHdl('email_subject_on_user_login'),
                    'body' => str_replace(
                        [
                            '#tp_user#',
                            '#tp_date#',
                            '#tp_time#',
                        ],
                        [
                            ' ' . $superGlobal->get('login', 'SESSION') . ' (IP: ' . getClientIpServer() . ')',
                            date($SETTINGS['date_format'], (int) $superGlobal->get('last_connection', 'SESSION')),
                            date($SETTINGS['time_format'], (int) $superGlobal->get('last_connection', 'SESSION')),
                        ],
                        langHdl('email_body_on_user_login')
                    ),
                    'receivers' => $receivers,
                    'status' => 'not_sent',
                ]
            );
        }

        // Ensure Complexity levels are translated
        if (defined('TP_PW_COMPLEXITY') === false) {
            define(
                'TP_PW_COMPLEXITY',
                [
                    0 => [0, langHdl('complex_level0'), 'fas fa-bolt text-danger'],
                    25 => [25, langHdl('complex_level1'), 'fas fa-thermometer-empty text-danger'],
                    50 => [50, langHdl('complex_level2'), 'fas fa-thermometer-quarter text-warning'],
                    60 => [60, langHdl('complex_level3'), 'fas fa-thermometer-half text-warning'],
                    70 => [70, langHdl('complex_level4'), 'fas fa-thermometer-three-quarters text-success'],
                    80 => [80, langHdl('complex_level5'), 'fas fa-thermometer-full text-success'],
                    90 => [90, langHdl('complex_level6'), 'far fa-gem text-success'],
                ]
            );
        }

        /*
        $sessionUrl = '';
        if ($SETTINGS['cpassman_dir'] === '..') {
            $SETTINGS['cpassman_dir'] = '.';
        }
        */
    } elseif ((int) $userInfo['disabled'] === 1) {
        // User and password is okay but account is locked
        echo prepareExchangedData(
            [
                'value' => $return,
                'user_id' => $superGlobal->get('user_id', 'SESSION') !== null ? (int) $superGlobal->get('user_id', 'SESSION') : '',
                'user_admin' => $superGlobal->get('admin', 'SESSION') !== null ? (int) $superGlobal->get('admin', 'SESSION') : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => 0,
                'error' => 'user_is_locked',
                'message' => langHdl('account_is_locked'),
                'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
                'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
                'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                'private_key_conform' => $superGlobal->get('private_key', 'SESSION', 'user') !== null
                    && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                    && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none' ? true : false,
                'session_key' => $superGlobal->get('key', 'SESSION'),
                //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
                'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
            ],
            'encode'
        );
        return false;
    } else {
        // User exists in the DB but Password is false
        // check if user is locked
        $userIsLocked = false;
        $nbAttempts = intval($userInfo['no_bad_attempts'] + 1);
        if (
            $SETTINGS['nb_bad_authentication'] > 0
            && intval($SETTINGS['nb_bad_authentication']) < $nbAttempts
        ) {
            $userIsLocked = true;
            // log it
            if (
                isset($SETTINGS['log_connections']) === true
                && (int) $SETTINGS['log_connections'] === 1
            ) {
                logEvents($SETTINGS, 'user_locked', 'connection', (string) $userInfo['id'], stripslashes($username));
            }
        }
        DB::update(
            prefixTable('users'),
            [
                'key_tempo' => $superGlobal->get('key', 'SESSION'),
                'disabled' => $userIsLocked,
                'no_bad_attempts' => $nbAttempts,
            ],
            'id=%i',
            $userInfo['id']
        );
        // What return shoulb we do
        if ($userIsLocked === true) {
            echo prepareExchangedData(
                [
                    'value' => $return,
                    'user_id' => $superGlobal->get('user_id', 'SESSION') !== null ? (int) $superGlobal->get('user_id', 'SESSION') : '',
                    'user_admin' => $superGlobal->get('admin', 'SESSION') !== null ? (int) $superGlobal->get('admin', 'SESSION') : 0,
                    'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                    'pwd_attempts' => 0,
                    'error' => 'user_is_locked',
                    'message' => langHdl('account_is_locked'),
                    'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
                    'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
                    'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                    'private_key_conform' => $superGlobal->get('user_id', 'SESSION') !== null
                        && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                        && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none' ? true : false,
                    'session_key' => $superGlobal->get('key', 'SESSION'),
                    //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
                    'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                    'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                    'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
                ],
                'encode'
            );
            return false;
        }
        echo prepareExchangedData(
            [
                'value' => $return,
                'user_id' => $superGlobal->get('user_id', 'SESSION') !== null ? (int) $superGlobal->get('user_id', 'SESSION') : '',
                'user_admin' => $superGlobal->get('admin', 'SESSION') !== null ? (int) $superGlobal->get('admin', 'SESSION') : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => 'user_not_exists3',
                'message' => langHdl('error_bad_credentials'),
                'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
                'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
                'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                'private_key_conform' => $superGlobal->get('user_id', 'SESSION') !== null
                        && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                        && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none' ? true : false,
                'session_key' => $superGlobal->get('key', 'SESSION'),
                    //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
                'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
            ],
            'encode'
        );
        return false;
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        "\n\n----\n" .
            'Identified : ' . filter_var($return, FILTER_SANITIZE_STRING) . "\n\n"
    );
    echo prepareExchangedData(
        [
            'value' => $return,
            'user_id' => $superGlobal->get('user_id', 'SESSION') !== null ? (int) $superGlobal->get('user_id', 'SESSION') : '',
            'user_admin' => $superGlobal->get('admin', 'SESSION') !== null ? (int) $superGlobal->get('admin', 'SESSION') : 0,
            'initial_url' => $antiXss->xss_clean($sessionUrl),
            'pwd_attempts' => 0,
            'error' => false,
            'message' => $superGlobal->get('user_upgrade_needed', 'SESSION', 'user') !== null && (int) $superGlobal->get('user_upgrade_needed', 'SESSION', 'user') === 1 ? 'ask_for_otc' : '',
            'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
            'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
            'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
            'private_key_conform' => $superGlobal->get('user_id', 'SESSION') !== null
                && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none' ? true : false,
            'session_key' => $superGlobal->get('key', 'SESSION'),
            //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
            'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
            'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
            'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
            'upgrade_needed' => isset($userInfo['upgrade_needed']) === true ? (int) $userInfo['upgrade_needed'] : 0,
            'special' => isset($userInfo['special']) === true ? (int) $userInfo['special'] : 0,
        ],
        'encode'
    );

    return true;
}

/**
 * Authenticate a user through AD.
 *
 * @param string $username      Username
 * @param array $userInfo       User account information
 * @param string $passwordClear Password
 * @param array $SETTINGS       Teampass settings
 *
 * @return array
 */
function authenticateThroughAD(string $username, array $userInfo, string $passwordClear, array $SETTINGS): array
{
    // Build ldap configuration array
    $config = [
        // Mandatory Configuration Options
        'hosts' => [explode(',', $SETTINGS['ldap_hosts'])],
        'base_dn' => $SETTINGS['ldap_bdn'],
        'username' => $SETTINGS['ldap_user_attribute'].'='.$username.','.(isset($SETTINGS['ldap_dn_additional_user_dn']) ? $SETTINGS['ldap_dn_additional_user_dn'].',' : '').$SETTINGS['ldap_bdn'],
        'password' => $passwordClear,

        // Optional Configuration Options
        'port' => $SETTINGS['ldap_port'],
        'use_ssl' => $SETTINGS['ldap_ssl'] === 1 ? true : false,
        'use_tls' => $SETTINGS['ldap_tls'] === 1 ? true : false,
        'version' => 3,
        'timeout' => 5,
        'follow_referrals' => false,

        // Custom LDAP Options
        'options' => [
            // See: http://php.net/ldap_set_option
            LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_HARD,
        ],
    ];
    // Load expected libraries
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Illuminate/Contracts/Auth/Authenticatable.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Traits/EnumeratesValues.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Traits/Macroable.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/helpers.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Arr.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Contracts/Support/Jsonable.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Contracts/Support/Arrayable.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Enumerable.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Collection.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/CarbonTimeZone.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Units.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Week.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Timestamp.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Test.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/ObjectInitialisation.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Serialization.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/IntervalRounding.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Rounding.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Localization.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Options.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Cast.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Mutability.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Modifiers.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Mixin.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Macro.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Difference.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Creator.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Converter.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Comparison.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Boundaries.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Traits/Date.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/CarbonInterface.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Carbon/Carbon.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/DetectsErrors.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/Connection.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/LdapInterface.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/LdapBase.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/Ldap.php';
    $ad = new SplClassLoader('LdapRecord', '../includes/libraries');
    $ad->register();
    $connection = new Connection($config);
    // COnnect to LDAP
    try {
        $connection->connect();
        Container::addConnection($connection);
    } catch (\LdapRecord\Auth\BindException $e) {
        $error = $e->getDetailedError();
        return [
            'error' => true,
            'message' => langHdl('error').' : '.$error->getErrorCode().' - '.$error->getErrorMessage(). '<br>'.$error->getDiagnosticMessage().' '.$config['username'],

        ];
    }

    $query = $connection->query();
    try {
        $entry = $query->findBy(
            $SETTINGS['ldap_user_attribute'],
            $username
        );
    } catch (\LdapRecord\Models\ModelNotFoundException $ex) {
        // Not found.
        return [
            'error' => true,
            'message' => langHdl('error_no_user_in_ad'),
        ];
    }

    // Check shadowexpire attribute - if === 1 then user disabled
    if (isset($entry['shadowexpire'][0]) === true && (int) $entry['shadowexpire'][0] === 1) {
        return [
            'error' => true,
            'message' => langHdl('error_ad_user_expired'),
        ];
    }

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();
    $hashedPassword = $pwdlib->createPasswordHash($passwordClear);
    //If user has never been connected then erase current pwd with the ldap's one
    if (empty($userInfo['pw']) === true) {
        // Password are similar in Teampass and AD
        DB::update(
            prefixTable('users'),
            [
                'pw' => $hashedPassword,
            ],
            'id = %i',
            $userInfo['id']
        );
    /*} elseif ($userInfo['special'] === 'user_added_from_ldap') {
        // Case where user has been added from LDAP and never being connected to TP
        // We need to create his keys
        $userKeys = generateUserKeys($passwordClear);

        // NOw update
        DB::update(
            prefixTable('users'),
            array(
                'pw' => $hashedPassword,
                'public_key' => $userKeys['public_key'],
                'private_key' => $userKeys['private_key'],
            ),
            'id = %i',
            $userInfo['id']
        );*/
    } elseif ($pwdlib->verifyPasswordHash($passwordClear, $userInfo['pw']) === false) {
        // Case where user is auth by LDAP but his password in Teampass is not synchronized
        // For example when user has changed his password in AD.
        // So we need to update it in Teampass and ask for private key re-encryption
        DB::update(
            prefixTable('users'),
            [
                'pw' => $hashedPassword,
                'special' => 'auth-pwd-change',
            ],
            'id = %i',
            $userInfo['id']
        );
    }

    $userInfo['pw'] = $hashedPassword;
    return [
        'error' => false,
        'message' => '',
    ];
}

/**
 * Undocumented function.
 *
 * @param string|array|resource $dataReceived Received data
 * @param string                $userInfo     Result of query
 * @param array                 $SETTINGS     Teampass settings
 *
 * @return array
 */
function yubicoMFACheck($dataReceived, string $userInfo, array $SETTINGS): array
{
    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();
    $sessionAdmin = $superGlobal->get('user_admin', 'SESSION');
    $sessionUrl = $superGlobal->get('initial_url', 'SESSION');
    $sessionPwdAttempts = $superGlobal->get('pwd_attempts', 'SESSION');
    // Init
    $yubico_key = htmlspecialchars_decode($dataReceived['yubico_key']);
    $yubico_user_key = htmlspecialchars_decode($dataReceived['yubico_user_key']);
    $yubico_user_id = htmlspecialchars_decode($dataReceived['yubico_user_id']);
    if (empty($yubico_user_key) === false && empty($yubico_user_id) === false) {
        // save the new yubico in user's account
        DB::update(
            prefixTable('users'),
            [
                'yubico_user_key' => $yubico_user_key,
                'yubico_user_id' => $yubico_user_id,
            ],
            'id=%i',
            $userInfo['id']
        );
    } else {
        // Check existing yubico credentials
        if ($userInfo['yubico_user_key'] === 'none' || $userInfo['yubico_user_id'] === 'none') {
            return [
                'error' => true,
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => 'no_user_yubico_credentials',
                'message' => '',
                'proceedIdentification' => false,
            ];
        }
        $yubico_user_key = $userInfo['yubico_user_key'];
        $yubico_user_id = $userInfo['yubico_user_id'];
    }

    // Now check yubico validity
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/Yubico/Yubico.php';
    $yubi = new Auth_Yubico($yubico_user_id, $yubico_user_key);
    $auth = $yubi->verify($yubico_key);
    //, null, null, null, 60

    if (PEAR::isError($auth)) {
        return [
            'error' => true,
            'value' => '',
            'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
            'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'error' => 'bad_user_yubico_credentials',
            'message' => langHdl('yubico_bad_code'),
            'proceedIdentification' => false,
        ];
    }

    return [
        'error' => false,
        'message' => '',
        'proceedIdentification' => true,
    ];
}

/**
 * Undocumented function.
 *
 * @param string $username      User name
 * @param string $passwordClear User password in clear
 * @param string $retLDAP       Received data from LDAP
 * @param array  $SETTINGS      Teampass settings
 *
 * @return array
 */
function ldapCreateUser(string $username, string $passwordClear, string $retLDAP, array $SETTINGS): array
{
    // Generate user keys pair
    $userKeys = generateUserKeys($passwordClear);
    // Insert user in DB
    DB::insert(
        prefixTable('users'),
        [
            'login' => $username,
            'pw' => $retLDAP['hashedPassword'],
            'email' => isset($retLDAP['user_info_from_ad'][0]['mail'][0]) === false ? '' : $retLDAP['user_info_from_ad'][0]['mail'][0],
            'name' => $retLDAP['user_info_from_ad'][0]['givenname'][0],
            'lastname' => $retLDAP['user_info_from_ad'][0]['sn'][0],
            'admin' => '0',
            'gestionnaire' => '0',
            'can_manage_all_users' => '0',
            'personal_folder' => $SETTINGS['enable_pf_feature'] === '1' ? '1' : '0',
            'fonction_id' => (empty($retLDAP['user_info_from_ad'][0]['commonGroupsLdapVsTeampass']) === false ? $retLDAP['user_info_from_ad'][0]['commonGroupsLdapVsTeampass'] . ';' : '') . (isset($SETTINGS['ldap_new_user_role']) === true ? $SETTINGS['ldap_new_user_role'] : '0'),
            'groupes_interdits' => '',
            'groupes_visibles' => '',
            'last_pw_change' => (int) time(),
            'user_language' => $SETTINGS['default_language'],
            'encrypted_psk' => '',
            'isAdministratedByRole' => isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && empty($SETTINGS['ldap_new_user_is_administrated_by']) === false ? $SETTINGS['ldap_new_user_is_administrated_by'] : 0,
            'public_key' => $userKeys['public_key'],
            'private_key' => $userKeys['private_key'],
        ]
    );
    $newUserId = DB::insertId();
    // Create personnal folder
    if (isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] === '1') {
        DB::insert(
            prefixTable('nested_tree'),
            [
                'parent_id' => '0',
                'title' => $newUserId,
                'bloquer_creation' => '0',
                'bloquer_modification' => '0',
                'personal_folder' => '1',
            ]
        );
        // Rebuild tree
        $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'] . '/includes/libraries');
        $tree->register();
        $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        $tree->rebuild();
    }

    return [
        'error' => false,
        'message' => '',
        'proceedIdentification' => true,
        'user_initial_creation_through_ldap' => true,
    ];
}

/**
 * Undocumented function.
 *
 * @param string                $username     Username
 * @param string                $userInfo     Result of query
 * @param string|array|resource $dataReceived DataReceived
 * @param array                 $SETTINGS     Teampass settings
 *
 * @return array
 */
function googleMFACheck(string $username, string $userInfo, $dataReceived, array $SETTINGS): array
{
    if (
        isset($dataReceived['GACode']) === true
        && empty($dataReceived['GACode']) === false
    ) {
        // Load superGlobals
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $sessionAdmin = $superGlobal->get('user_admin', 'SESSION');
        $sessionUrl = $superGlobal->get('initial_url', 'SESSION');
        $sessionPwdAttempts = $superGlobal->get('pwd_attempts', 'SESSION');
        // load library
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php';
        // create new instance
        $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($SETTINGS['ga_website_name']);
        // Init
        $firstTime = [];
        // now check if it is the 1st time the user is using 2FA
        if ($userInfo['ga_temporary_code'] !== 'none' && $userInfo['ga_temporary_code'] !== 'done') {
            if ($userInfo['ga_temporary_code'] !== $dataReceived['GACode']) {
                return [
                    'error' => true,
                    'message' => langHdl('ga_bad_code'),
                    'proceedIdentification' => false,
                    'mfaStatus' => '',
                ];
            }

            // If first time with MFA code
            $proceedIdentification = false;
            $mfaStatus = 'ga_temporary_code_correct';
            $mfaMessage = langHdl('ga_flash_qr_and_login');
            // generate new QR
            $new_2fa_qr = $tfa->getQRCodeImageAsDataUri(
                'Teampass - ' . $username,
                $userInfo['ga']
            );
            // clear temporary code from DB
            DB::update(
                prefixTable('users'),
                [
                    'ga_temporary_code' => 'done',
                ],
                'id=%i',
                $userInfo['id']
            );
            $firstTime = [
                'value' => '<img src="' . $new_2fa_qr . '">',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => false,
                'message' => $mfaMessage,
                'mfaStatus' => $mfaStatus,
            ];
        } else {
            // verify the user GA code
            if ($tfa->verifyCode($userInfo['ga'], $dataReceived['GACode'])) {
                $proceedIdentification = true;
            } else {
                return [
                    'error' => true,
                    'message' => langHdl('ga_bad_code'),
                    'proceedIdentification' => false,
                ];
            }
        }
    } else {
        return [
            'error' => true,
            'message' => langHdl('ga_bad_code'),
            'proceedIdentification' => false,
        ];
    }

    return [
        'error' => false,
        'message' => '',
        'proceedIdentification' => $proceedIdentification,
        'firstTime' => $firstTime,
    ];
}

/**
 * Undocumented function.
 *
 * @param string                $passwordClear Password in clear
 * @param array|string          $userInfo      Array of user data
 * @param array|string|resource $dataReceived  Received data
 * @param string                $username      User name
 * @param array                 $SETTINGS      Teampass settings
 *
 * @return bool
 */
function checkCredentials($passwordClear, $userInfo, $dataReceived, $username, $SETTINGS)
{
    // Set to false
    $userPasswordVerified = false;
    // load passwordLib library
    include_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();
    // Check if old encryption used
    if (
        crypt($passwordClear, $userInfo['pw']) === $userInfo['pw']
        && empty($userInfo['pw']) === false
    ) {
        $userPasswordVerified = true;
        //update user's password
        $userInfo['pw'] = $pwdlib->createPasswordHash($passwordClear);
        DB::update(
            prefixTable('users'),
            [
                'pw' => $userInfo['pw'],
            ],
            'id=%i',
            $userInfo['id']
        );
    }
    //echo $passwordClear." - ".$userInfo['pw']." - ".$pwdlib->verifyPasswordHash($passwordClear, $userInfo['pw'])." ;; ";
    // check the given password
    if ($userPasswordVerified !== true) {
        if ($pwdlib->verifyPasswordHash($passwordClear, $userInfo['pw']) === true) {
            $userPasswordVerified = true;
        } else {
            // 2.1.27.24 - manage passwords
            if ($pwdlib->verifyPasswordHash(htmlspecialchars_decode($dataReceived['pw']), $userInfo['pw']) === true) {
                // then the auth is correct but needs to be adapted in DB since change of encoding
                $userInfo['pw'] = $pwdlib->createPasswordHash($passwordClear);
                DB::update(
                    prefixTable('users'),
                    [
                        'pw' => $userInfo['pw'],
                    ],
                    'id=%i',
                    $userInfo['id']
                );
                $userPasswordVerified = true;
            } else {
                $userPasswordVerified = false;
                logEvents(
                    $SETTINGS,
                    'failed_auth',
                    'user_password_not_correct',
                    '',
                    '',
                    stripslashes($username)
                );
            }
        }
    }

    return $userPasswordVerified;
}

/**
 * Undocumented function.
 *
 * @param bool   $enabled text1
 * @param string $dbgFile text2
 * @param string $text    text3
 */
function debugIdentify(bool $enabled, string $dbgFile, string $text): void
{
    if ($enabled === true) {
        $fp = fopen($dbgFile, 'a');
        if ($fp !== false) {
            fwrite(
                $fp,
                $text
            );
        }
    }
}
