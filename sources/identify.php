<?php

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      identify.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


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

if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir']) === true || $SETTINGS['cpassman_dir'] === '.') {
    $SETTINGS['cpassman_dir'] = '..';
}

// Load AntiXSS
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/voku/helper/AntiXSS.php';
$antiXss = new voku\helper\AntiXSS();

require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';

// init
$ldap_suffix = '';
$result = '';
$adldap = '';

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
$post_pwd = filter_input(INPUT_POST, 'pwd', FILTER_SANITIZE_STRING);
$post_sig_response = filter_input(INPUT_POST, 'sig_response', FILTER_SANITIZE_STRING);
$post_cardid = filter_input(INPUT_POST, 'cardid', FILTER_SANITIZE_STRING);
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
            $userInfo = DB::queryfirstrow(
                'SELECT id
                FROM ' . prefixTable('users') . '
                WHERE login = %s',
                $post_login
            );

            if (DB::count() === 0) {
                // Ask your administrator to create your account in Teampass
                // TODO
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
    if (empty($sessionPwdAttempts) === true) {
        $sessionPwdAttempts = 1;
    } else {
        ++$sessionPwdAttempts;
    }

    $superGlobal->put('pwd_attempts', $sessionPwdAttempts, 'SESSION');

    // manage brute force
    if ($sessionPwdAttempts <= 3) {
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
            array(
                'value' => 'bruteforce_wait',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => true,
                'message' => langHdl('error_bad_credentials_more_than_3_times'),
            ),
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
        array(
            'agses' => (isset($SETTINGS['agses_authentication_enabled']) === true
                && (int) $SETTINGS['agses_authentication_enabled'] === 1) ? true : false,
            'google' => (isset($SETTINGS['google_authentication']) === true
                && (int) $SETTINGS['google_authentication'] === 1) ? true : false,
            'yubico' => (isset($SETTINGS['yubico_authentication']) === true
                && (int) $SETTINGS['yubico_authentication'] === 1) ? true : false,
            'duo' => (isset($SETTINGS['duo']) === true
                && (int) $SETTINGS['duo'] === 1) ? true : false,
        ),
        'encode'
    );

    return false;
}

/**
 * Complete authentication of user through Teampass.
 *
 * @param string $sentData Credentials
 * @param array  $SETTINGS Teamapss settings
 *
 * @return boolean
 */
function identifyUser($sentData, $SETTINGS)
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

    // Brute force management
    if ($sessionPwdAttempts > 2) {
        $superGlobal->put('next_possible_pwd_attempts', (time() + 10), 'SESSION');
        $superGlobal->put('pwd_attempts', 0, 'SESSION');

        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
        echo prepareExchangedData(
            array(
                'value' => 'bruteforce_wait',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => 0,
                'error' => true,
                'message' => langHdl('error_bad_credentials_more_than_3_times'),
            ),
            'encode'
        );

        return false;
    }

    // Manage Maintenance mode
    if (
        isset($SETTINGS['maintenance_mode']) === true && (int) $SETTINGS['maintenance_mode'] === 1
        && (int) $userInfo['admin'] === 0
    ) {
        echo prepareExchangedData(
            array(
                'value' => '',
                'user_admin' => '',
                'initial_url' => '',
                'pwd_attempts' => '',
                'error' => 'maintenance_mode_enabled',
                'message' => '',
            ),
            'encode'
        );
        return false;
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        "Content of data sent '" . filter_var($sentData, FILTER_SANITIZE_STRING) . "'\n"
    );

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
        && isset($_SERVER['PHP_AUTH_USER']) === true
        && isset($SETTINGS['maintenance_mode']) === true
        && (int) $SETTINGS['maintenance_mode'] === 1
    ) {
        if (strpos($_SERVER['PHP_AUTH_USER'], '@') !== false) {
            $username = explode('@', filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING))[0];
        } elseif (strpos($_SERVER['PHP_AUTH_USER'], '\\') !== false) {
            $username = explode('\\', filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING))[1];
        } else {
            $username = filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING);
        }
        $passwordClear = $_SERVER['PHP_AUTH_PW'];
    } else {
        $passwordClear = filter_var($dataReceived['pw'], FILTER_SANITIZE_STRING);
        $username = filter_var($dataReceived['login'], FILTER_SANITIZE_STRING);
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
            array(
                'value' => '2fa_not_set',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => '2fa_not_set',
                'message' => langHdl('2fa_credential_not_correct'),
            ),
            'encode'
        );

        return false;
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        "Starting authentication of '" . $username . "'\n" .
            'LDAP status: ' . $SETTINGS['ldap_mode'] . "\n"
    );
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        "Get all LDAP params : \n" .
            'mode : ' . $SETTINGS['ldap_mode'] . "\n" .
            'type : ' . $SETTINGS['ldap_type'] . "\n" .
            'base_dn : ' . $SETTINGS['ldap_domain_dn'] . "\n" .
            'search_base : ' . $SETTINGS['ldap_search_base'] . "\n" .
            'bind_dn : ' . $SETTINGS['ldap_bind_dn'] . "\n" .
            'bind_passwd : ' . $SETTINGS['ldap_bind_passwd'] . "\n" .
            'user_attribute : ' . $SETTINGS['ldap_user_attribute'] . "\n" .
            'account_suffix : ' . $SETTINGS['ldap_suffix'] . "\n" .
            'domain_controllers : ' . $SETTINGS['ldap_domain_controler'] . "\n" .
            'ad_port : ' . $SETTINGS['ldap_port'] . "\n" .
            'use_ssl : ' . $SETTINGS['ldap_ssl'] . "\n" .
            'use_tls : ' . $SETTINGS['ldap_tls'] . "\n*********\n\n"
    );

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
            array(
                'error' => 'user_not_exists',
                'message' => langHdl('error_bad_credentials'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
            ),
            'encode'
        );
        return false;
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        'USer exists: ' . $counter . "\n"
    );

    $user_initial_creation_through_ldap = false;
    $userPasswordVerified = false;
    $ldapConnection = false;
    $return = '';

    // Prepare LDAP connection if set up
    if (
        isset($SETTINGS['ldap_mode']) === true
        && (int) $SETTINGS['ldap_mode'] === 1
        && $username !== 'admin'
        && $userInfo['auth_type'] !== 'local'
    ) {
        $ldapConnection = true;
        //Multiple Domain Names
        if (strpos(html_entity_decode($username), '\\') === true) {
            $ldap_suffix = '@' . substr(html_entity_decode($username), 0, strpos(html_entity_decode($username), '\\'));
            $username = substr(html_entity_decode($username), strpos(html_entity_decode($username), '\\') + 1);
        }
        if ($SETTINGS['ldap_type'] === 'posix-search') {
            $retLDAP = identifyViaLDAPPosixSearch(
                $username,
                $userInfo,
                $passwordClear,
                $counter,
                $SETTINGS
            );

            if ($retLDAP['error'] === true) {
                echo prepareExchangedData(
                    array(
                        'value' => '',
                        'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                        'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                        'pwd_attempts' => (int) $sessionPwdAttempts,
                        'error' => true,
                        'message' => $retLDAP['message'],
                    ),
                    'encode'
                );
                return false;
                // ---
            } elseif ($retLDAP['proceedIdentification'] !== true && $retLDAP['userInLDAP'] === true) {
                logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
                echo prepareExchangedData(
                    array(
                        'value' => '',
                        'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                        'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                        'pwd_attempts' => (int) $sessionPwdAttempts,
                        'error' => 'user_not_exists7',
                        'message' => langHdl('error_bad_credentials'),
                    ),
                    'encode'
                );
                return false;
            } elseif (isset($retLDAP['hashedPassword']) === true && empty($retLDAP['hashedPassword']) === false) {
                $userInfo['pw'] = $retLDAP['hashedPassword'];
            }
        } else {
            $retLDAP = identifyViaLDAPPosix(
                $username,
                $ldap_suffix,
                $passwordClear,
                $counter,
                $SETTINGS
            );

            if ($retLDAP['error'] === true) {
                echo prepareExchangedData(
                    array(
                        'value' => '',
                        'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                        'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                        'pwd_attempts' => (int) $sessionPwdAttempts,
                        'error' => true,
                        'message' => $retLDAP['message'],
                    ),
                    'encode'
                );
                return false;
                // ---
            } elseif ($retLDAP['proceedIdentification'] !== true && (int) $SETTINGS['ldap_and_local_authentication'] !== 1) {
                logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
                echo prepareExchangedData(
                    array(
                        'value' => '',
                        'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                        'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                        'pwd_attempts' => (int) $sessionPwdAttempts,
                        'error' => 'user_not_exists8',
                        'message' => langHdl('error_bad_credentials'),
                    ),
                    'encode'
                );
                return false;
            }
        }
    }

    // Check Yubico
    if (
        isset($SETTINGS['yubico_authentication']) === true
        && (int) $SETTINGS['yubico_authentication'] === 1
        && ((int) $userInfo['admin'] !== 1 || ((int) $SETTINGS['admin_2fa_required'] === 1 && (int) $userInfo['admin'] === 1))
        && $user_2fa_selection === 'yubico'
    ) {
        $ret = yubicoMFACheck(
            $dataReceived,
            $userInfo,
            $SETTINGS
        );

        if ($ret['error'] !== "") {
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
        && $user_2fa_selection === 'otp'
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
        } else {
            $proceedIdentification = $ret['proceedIdentification'];
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
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        'Proceed with Ident: ' . $proceedIdentification . "\n"
    );

    // If admin user then check if folder install exists
    // if yes then refuse connection
    if ((int) $userInfo['admin'] === 1 && is_dir('../install') === true) {
        echo prepareExchangedData(
            array(
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => true,
                'message' => langHdl('remove_install_folder'),
            ),
            'encode'
        );
        return false;
    }


    // Check user and password
    if (checkCredentials($passwordClear, $userInfo, $dataReceived, $username, $SETTINGS) !== true) {
        echo prepareExchangedData(
            array(
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => 'user_not_exists2',
                'message' => langHdl('error_bad_credentials'),
            ),
            'encode'
        );
        return false;
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        "User's password verified: " . $userPasswordVerified . "\n"
    );

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
            && $username == 'admin' && (int) $userInfo['disabled'] === 0)
        || (isset($SETTINGS['ldap_and_local_authentication']) === true && (int) $SETTINGS['ldap_and_local_authentication'] === 1
            && isset($SETTINGS['ldap_mode']) === true && in_array($SETTINGS['ldap_mode'], array('1', '2')) === true
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
        $arrAttempts = array();
        if (DB::count() > 0) {
            foreach ($rows as $record) {
                array_push(
                    $arrAttempts,
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], $record['date'])
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
            logEvents($SETTINGS, 'user_connection', 'connection', $userInfo['id'], stripslashes($username));
        }
        // Save account in SESSION
        $superGlobal->put('login', stripslashes($username), 'SESSION');
        $superGlobal->put('name', stripslashes($userInfo['name']), 'SESSION');
        $superGlobal->put('lastname', stripslashes($userInfo['lastname']), 'SESSION');
        $superGlobal->put('user_id', $userInfo['id'], 'SESSION');
        $superGlobal->put('admin', $userInfo['admin'], 'SESSION');
        $superGlobal->put('user_manager', $userInfo['gestionnaire'], 'SESSION');
        $superGlobal->put('user_can_manage_all_users', $userInfo['can_manage_all_users'], 'SESSION');
        $superGlobal->put('user_read_only', $userInfo['read_only'], 'SESSION');
        $superGlobal->put('last_pw_change', $userInfo['last_pw_change'], 'SESSION');
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
        if (!isset($userInfo['treeloadstrategy']) || empty($userInfo['treeloadstrategy'])) {
            $userInfo['treeloadstrategy'] = 'full';
        }
        $superGlobal->put('treeloadstrategy', $userInfo['treeloadstrategy'], 'SESSION', 'user');
        $superGlobal->put('agses-usercardid', $userInfo['agses-usercardid'], 'SESSION', 'user');
        $superGlobal->put('user_language', $userInfo['user_language'], 'SESSION', 'user');
        $superGlobal->put('encrypted_psk', $userInfo['encrypted_psk'], 'SESSION', 'user');
        $superGlobal->put('usertimezone', $userInfo['usertimezone'], 'SESSION', 'user');
        $superGlobal->put('session_duration', $dataReceived['duree_session'] * 60, 'SESSION', 'user');
        $superGlobal->put('api-key', $userInfo['user_api_key'], 'SESSION', 'user');

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
            $superGlobal->put('last_connection', time(), 'SESSION');
        } else {
            $superGlobal->put('last_connection', $userInfo['last_connexion'], 'SESSION');
        }

        if (empty($userInfo['latest_items']) === false) {
            $superGlobal->put('latest_items', explode(';', $userInfo['latest_items']), 'SESSION');
        } else {
            $superGlobal->put('latest_items', array(), 'SESSION');
        }
        if (empty($userInfo['favourites']) === false) {
            $superGlobal->put('favourites', explode(';', $userInfo['favourites']), 'SESSION');
        } else {
            $superGlobal->put('favourites', array(), 'SESSION');
        }

        if (empty($userInfo['groupes_visibles']) === false) {
            $superGlobal->put(
                'groupes_visibles',
                implode(';', $userInfo['groupes_visibles']),
                'SESSION'
            );
        } else {
            $superGlobal->put('groupes_visibles', array(), 'SESSION');
        }
        if (empty($userInfo['groupes_interdits']) === false) {
            $superGlobal->put(
                'no_access_folders',
                implode(';', $userInfo['groupes_visibles']),
                'SESSION'
            );
        } else {
            $superGlobal->put('no_access_folders', array(), 'SESSION');
        }
        // User's roles
        if (strpos($userInfo['fonction_id'], ',') !== -1) {
            // Convert , to ;
            $userInfo['fonction_id'] = str_replace(',', ';', $userInfo['fonction_id']);
            DB::update(
                prefixTable('users'),
                array(
                    'fonction_id' => $userInfo['fonction_id'],
                ),
                'id = %i',
                $superGlobal->get('user_id', 'SESSION')
            );
        }
        $superGlobal->put('fonction_id', $userInfo['fonction_id'], 'SESSION');
        $superGlobal->put('user_roles', array_filter(explode(';', $userInfo['fonction_id'])), 'SESSION');

        // build array of roles
        $superGlobal->put('user_pw_complexity', 0, 'SESSION');
        $superGlobal->put('arr_roles', array(), 'SESSION');
        foreach ($superGlobal->get('user_roles', 'SESSION') as $role) {
            $resRoles = DB::queryFirstRow(
                'SELECT title, complexity
                FROM ' . prefixTable('roles_title') . '
                WHERE id=%i',
                $role
            );
            $superGlobal->put(
                $role,
                array(
                    'id' => $role,
                    'title' => $resRoles['title'],
                ),
                'SESSION',
                'arr_roles'
            );
            // get highest complexity
            if (intval($superGlobal->get('user_pw_complexity', 'SESSION')) < intval($resRoles['complexity'])) {
                $superGlobal->put('user_pw_complexity', $resRoles['complexity'], 'SESSION');
            }
        }

        // build complete array of roles
        $superGlobal->put('arr_roles_full', array(), 'SESSION');
        $rows = DB::query('SELECT id, title FROM ' . prefixTable('roles_title') . ' ORDER BY title ASC');
        foreach ($rows as $record) {
            $superGlobal->put(
                $record['id'],
                array(
                    'id' => $record['id'],
                    'title' => $record['title'],
                ),
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
            $arrayUserKeys = array(
                'public_key' => $userKeys['public_key'],
                'private_key' => $userKeys['private_key'],
            );
        } else {
            // Uncrypt private key
            $superGlobal->put('private_key', decryptPrivateKey($passwordClear, $userInfo['private_key']), 'SESSION', 'user');
            $arrayUserKeys = [];
        }

        // Update table
        DB::update(
            prefixTable('users'),
            array_merge(
                array(
                    'key_tempo' => $superGlobal->get('key', 'SESSION'),
                    'last_connexion' => time(),
                    'timestamp' => time(),
                    'disabled' => 0,
                    'no_bad_attempts' => 0,
                    'session_end' => $superGlobal->get('sessionDuration', 'SESSION'),
                    'user_ip' => $dataReceived['client'],
                ),
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
                implode(';', $userInfo['groupes_visibles']),
                $superGlobal->get('no_access_folders', 'SESSION'),
                $userInfo['admin'],
                $userInfo['fonction_id'],
                $SETTINGS
            );
        } else {
            // is new LDAP user. Show only his personal folder
            if ($SETTINGS['enable_pf_feature'] === '1') {
                $superGlobal->put('personal_visible_groups', array($userInfo['id']), 'SESSION');
                $superGlobal->put('personal_folders', array($userInfo['id']), 'SESSION');
            } else {
                $superGlobal->put('personal_visible_groups', array(), 'SESSION');
                $superGlobal->put('personal_folders', array(), 'SESSION');
            }
            $superGlobal->put('all_non_personal_folders', array(), 'SESSION');
            $superGlobal->put('groupes_visibles', array(), 'SESSION');
            $superGlobal->put('read_only_folders', array(), 'SESSION');
            $superGlobal->put('list_folders_limited', '', 'SESSION');
            $superGlobal->put('list_folders_editable_by_role', array(), 'SESSION');
            $superGlobal->put('list_restricted_folders_for_items', array(), 'SESSION');
            $superGlobal->put('nb_folders', 1, 'SESSION');
            $superGlobal->put('nb_roles', 0, 'SESSION');
        }
        // Get some more elements
        $superGlobal->put('screenHeight', $dataReceived['screenHeight'], 'SESSION');
        // Get last seen items
        $superGlobal->put('latest_items_tab', array(), 'SESSION');
        $superGlobal->put('nb_roles', 0, 'SESSION');
        foreach ($superGlobal->get('latest_items', 'SESSION') as $item) {
            if (!empty($item)) {
                $dataLastItems = DB::queryFirstRow(
                    'SELECT id,label,id_tree
                    FROM ' . prefixTable('items') . '
                    WHERE id=%i',
                    $item
                );
                $superGlobal->put(
                    $item,
                    array(
                        'id' => $item,
                        'label' => $dataLastItems['label'],
                        'url' => 'index.php?page=items&amp;group=' . $dataLastItems['id_tree'] . '&amp;id=' . $item,
                    ),
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
                array(
                    'timestamp' => time(),
                    'subject' => $LANG['email_subject_on_user_login'],
                    'body' => str_replace(
                        array(
                            '#tp_user#',
                            '#tp_date#',
                            '#tp_time#',
                        ),
                        array(
                            ' ' . $superGlobal->get('login', 'SESSION') . ' (IP: ' . getClientIpServer() . ')',
                            date($SETTINGS['date_format'], $superGlobal->get('last_connection', 'SESSION')),
                            date($SETTINGS['time_format'], $superGlobal->get('last_connection', 'SESSION')),
                        ),
                        $LANG['email_body_on_user_login']
                    ),
                    'receivers' => $receivers,
                    'status' => 'not_sent',
                )
            );
        }

        // Ensure Complexity levels are translated
        if (defined('TP_PW_COMPLEXITY') === false) {
            define(
                'TP_PW_COMPLEXITY',
                array(
                    0 => array(0, langHdl('complex_level0'), 'fas fa-bolt text-danger'),
                    25 => array(25, langHdl('complex_level1'), 'fas fa-thermometer-empty text-danger'),
                    50 => array(50, langHdl('complex_level2'), 'fas fa-thermometer-quarter text-warning'),
                    60 => array(60, langHdl('complex_level3'), 'fas fa-thermometer-half text-warning'),
                    70 => array(70, langHdl('complex_level4'), 'fas fa-thermometer-three-quarters text-success'),
                    80 => array(80, langHdl('complex_level5'), 'fas fa-thermometer-full text-success'),
                    90 => array(90, langHdl('complex_level6'), 'far fa-gem text-success'),
                )
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
            array(
                'value' => $return,
                'user_id' => null !== $superGlobal->get('user_id', 'SESSION') ? (int) $superGlobal->get('user_id', 'SESSION') : '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => 0,
                'error' => 'user_is_locked',
                'message' => langHdl('account_is_locked'),
                'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
                'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
                'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                'private_key_conform' => null !== $superGlobal->get('private_key', 'SESSION', 'user')
                    && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                    && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none' ? true : false,
                'session_key' => $superGlobal->get('key', 'SESSION'),
                //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
                'can_create_root_folder' => null !== $superGlobal->get('can_create_root_folder', 'SESSION') ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
            ),
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
                logEvents($SETTINGS, 'user_locked', 'connection', $userInfo['id'], stripslashes($username));
            }
        }
        DB::update(
            prefixTable('users'),
            array(
                'key_tempo' => $superGlobal->get('key', 'SESSION'),
                'disabled' => $userIsLocked,
                'no_bad_attempts' => $nbAttempts,
            ),
            'id=%i',
            $userInfo['id']
        );
        // What return shoulb we do
        if ($userIsLocked === true) {
            echo prepareExchangedData(
                array(
                    'value' => $return,
                    'user_id' => null !== $superGlobal->get('user_id', 'SESSION') ? (int) $superGlobal->get('user_id', 'SESSION') : '',
                    'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                    'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                    'pwd_attempts' => 0,
                    'error' => 'user_is_locked',
                    'message' => langHdl('account_is_locked'),
                    'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
                    'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
                    'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                    'private_key_conform' => (null !== $superGlobal->get('user_id', 'SESSION')
                        && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                        && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none') ? true : false,
                    'session_key' => $superGlobal->get('key', 'SESSION'),
                    //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
                    'can_create_root_folder' => null !== $superGlobal->get('can_create_root_folder', 'SESSION') ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                    'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                    'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
                ),
                'encode'
            );

            return false;
        } else {
            echo prepareExchangedData(
                array(
                    'value' => $return,
                    'user_id' => null !== $superGlobal->get('user_id', 'SESSION') ? (int) $superGlobal->get('user_id', 'SESSION') : '',
                    'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                    'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                    'pwd_attempts' => (int) $sessionPwdAttempts,
                    'error' => 'user_not_exists3',
                    'message' => langHdl('error_bad_credentials'),
                    'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
                    'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
                    'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                    'private_key_conform' => (null !== $superGlobal->get('user_id', 'SESSION')
                        && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                        && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none') ? true : false,
                    'session_key' => $superGlobal->get('key', 'SESSION'),
                    //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
                    'can_create_root_folder' => null !== $superGlobal->get('can_create_root_folder', 'SESSION') ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                    'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                    'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
                ),
                'encode'
            );

            return false;
        }
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        "\n\n----\n" .
            'Identified : ' . filter_var($return, FILTER_SANITIZE_STRING) . "\n\n"
    );

    echo prepareExchangedData(
        array(
            'value' => $return,
            'user_id' => null !== $superGlobal->get('user_id', 'SESSION') ? (int) $superGlobal->get('user_id', 'SESSION') : '',
            'user_admin' => isset($sessionAdmin) ? (int) $antiXss->xss_clean($sessionAdmin) : '',
            'initial_url' => $antiXss->xss_clean($sessionUrl),
            'pwd_attempts' => 0,
            'error' => false,
            'message' => (null !== $superGlobal->get('user_upgrade_needed', 'SESSION', 'user') && (int) $superGlobal->get('user_upgrade_needed', 'SESSION', 'user') === 1) ? 'ask_for_otc' : '',
            'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
            'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
            'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
            'private_key_conform' => (null !== $superGlobal->get('user_id', 'SESSION')
                && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none') ? true : false,
            'session_key' => $superGlobal->get('key', 'SESSION'),
            //'has_psk' => empty($superGlobal->get('encrypted_psk', 'SESSION', 'user')) === false ? true : false,
            'can_create_root_folder' => null !== $superGlobal->get('can_create_root_folder', 'SESSION') ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
            'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
            'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
            'upgrade_needed' => isset($userInfo['upgrade_needed']) === true ? $userInfo['upgrade_needed'] : 0,
            'special' => isset($userInfo['special']) === true ? $userInfo['special'] : 0,
        ),
        'encode'
    );
}

/**
 * Undocumented function.
 *
 * @param string $username      Username
 * @param array  $userInfo      User account information
 * @param string $passwordClear Password
 * @param int    $counter       User exists in teampass
 * @param array  $SETTINGS      Teampass settings
 *
 * @return array
 */
function identifyViaLDAPPosixSearch($username, $userInfo, $passwordClear, $counter, $SETTINGS)
{
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/voku/helper/AntiXSS.php';
    $antiXss = new voku\helper\AntiXSS();

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    $ldapConnection = false;
    $ldapURIs = '';
    foreach (explode(',', $SETTINGS['ldap_domain_controler']) as $domainControler) {
        if ($SETTINGS['ldap_ssl'] == 1) {
            $ldapURIs .= 'ldaps://' . $domainControler . ':' . $SETTINGS['ldap_port'] . ' ';
        } else {
            $ldapURIs .= 'ldap://' . $domainControler . ':' . $SETTINGS['ldap_port'] . ' ';
        }
    }

    // Force
    $userInfo['login'] = $username;

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        'LDAP URIs : ' . $ldapURIs . "\n\n"
    );

    // Connect
    $ldapconn = ldap_connect($ldapURIs);

    // Case of LDAP over TLS
    if ($SETTINGS['ldap_tls']) {
        ldap_start_tls($ldapconn);
    }

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        'LDAP connection : ' . ($ldapconn ? 'Connected' : 'Failed') . "\n\n"
    );

    // Set options
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

    // Is LDAP connection ready?
    if ($ldapconn !== false) {
        // Should we bind the connection?
        if ($SETTINGS['ldap_bind_dn'] !== '' && $SETTINGS['ldap_bind_passwd'] !== '') {
            $ldapbind = ldap_bind($ldapconn, $SETTINGS['ldap_bind_dn'], $SETTINGS['ldap_bind_passwd']);

            // Debug
            debugIdentify(
                DEBUGLDAP,
                DEBUGLDAPFILE,
                'LDAP bind : ' . ($ldapbind ? 'Bound' : 'Failed') . "\n\n"
            );
        } else {
            $ldapbind = false;
        }
        if (($SETTINGS['ldap_bind_dn'] === '' && $SETTINGS['ldap_bind_passwd'] === '') || $ldapbind === true) {
            $filter = '(&(' . $SETTINGS['ldap_user_attribute'] . '=' . $userInfo['login'] . ')(objectClass=' . $SETTINGS['ldap_object_class'] . '))';
            $result = ldap_search(
                $ldapconn,
                $SETTINGS['ldap_search_base'],
                $filter,
                array('dn', 'mail', 'givenname', 'sn', 'samaccountname', 'shadowexpire', 'memberof')
            );

            // Debug
            debugIdentify(
                DEBUGLDAP,
                DEBUGLDAPFILE,
                'Search filter : ' . $filter . "\n" .
                    'Results : ' . print_r(ldap_get_entries($ldapconn, $result), true) . "\n\n"
            );

            // Check if user was found in AD
            if (ldap_count_entries($ldapconn, $result) > 0) {
                // Get user's info and especially the DN
                $result = ldap_get_entries($ldapconn, $result);
                $user_dn = $result[0]['dn'];

                // Debug
                debugIdentify(
                    DEBUGLDAP,
                    DEBUGLDAPFILE,
                    'User was found. ' . $user_dn . '\n'
                );

                // Check shadowexpire attribute - if === 1 then user disabled
                if (isset($result[0]['shadowexpire'][0]) === true && $result[0]['shadowexpire'][0] === '1') {
                    return array(
                        'error' => true,
                        'message' => array(
                            'value' => '',
                            'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                            'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                            'pwd_attempts' => (int) $sessionPwdAttempts,
                            'error' => 'user_not_exists5',
                            'message' => langHdl('error_bad_credentials'),
                        ),
                    );
                }

                // Should we restrain the search in specified user groups
                $GroupRestrictionEnabled = false;
                $commonGroupsLdapVsTeampass = array();
                $result['commonGroupsLdapVsTeampass'] = array();
                if (isset($SETTINGS['ldap_usergroup']) === true && empty($SETTINGS['ldap_usergroup']) === false) {
                    // New way to check User's group membership
                    $filter_group = 'memberUid=' . $userInfo['login'];
                    $result_group = ldap_search(
                        $ldapconn,
                        $SETTINGS['ldap_search_base'],
                        $filter_group,
                        array('dn', 'samaccountname')
                    );

                    if ($result_group) {
                        $ldapUserGroups = ldap_get_entries($ldapconn, $result_group);

                        // Debug
                        debugIdentify(
                            DEBUGLDAP,
                            DEBUGLDAPFILE,
                            'Search groups appartenance : ' . $SETTINGS['ldap_search_base'] . "\n" .
                                'Results : ' . print_r($ldapUserGroups, true) . "\n"
                        );

                        if ($ldapUserGroups['count'] > 0) {
                            // Get list of roles in Teampass
                            $rowsTeampassRoles = DB::query(
                                'SELECT id, title
                                FROM ' . prefixTable('roles_title') . '
                                ORDER BY title ASC'
                            );

                            // Now check if group fits
                            // Also identify groups in LDAP that exists in Teampass
                            for ($i = 0; $i < $ldapUserGroups['count']; ++$i) {
                                $groupName = str_replace(array('CN=', 'cn='), '', ldap_explode_dn($ldapUserGroups[$i]['dn'], 0)[0]);
                                if ($groupName === $SETTINGS['ldap_usergroup']) {
                                    $GroupRestrictionEnabled = true;
                                }

                                foreach ($rowsTeampassRoles as $teampassRole) {
                                    if ($teampassRole['title'] === $groupName) {
                                        array_push($commonGroupsLdapVsTeampass, $teampassRole['id']);
                                        break;
                                    }
                                }
                            }

                            $result[0]['commonGroupsLdapVsTeampass'] = implode(';', $commonGroupsLdapVsTeampass);
                        }
                    }

                    // Debug
                    debugIdentify(
                        DEBUGLDAP,
                        DEBUGLDAPFILE,
                        'Group was found : ' . var_export($GroupRestrictionEnabled, true) . ' -- \n' . var_export($result, true) . "\n"
                    );
                }

                // Is user in the LDAP?
                if (
                    $GroupRestrictionEnabled === true
                    || ($GroupRestrictionEnabled === false
                        && (isset($SETTINGS['ldap_usergroup']) === false
                            || (isset($SETTINGS['ldap_usergroup']) === true
                                && empty($SETTINGS['ldap_usergroup']) === true)))
                ) {
                    // Try to auth inside LDAP
                    $ldapbind = ldap_bind($ldapconn, $user_dn, $passwordClear);
                    if ($ldapbind === true) {
                        $ldapConnection = true;

                        $hashedPassword = $pwdlib->createPasswordHash($passwordClear);

                        debugIdentify(
                            DEBUGLDAP,
                            DEBUGLDAPFILE,
                            'User is authenticated on LDAP (meaning credentials are correct in AD)\n\n'
                        );

                        // Update user's password if it has changed in LDAP
                        // And if user exists in TP
                        if ($counter > 0 && $pwdlib->verifyPasswordHash(htmlspecialchars_decode($passwordClear), $userInfo['pw']) === false) {
                            // Update pwd in TP database
                            DB::update(
                                prefixTable('users'),
                                array(
                                    'pw' => $hashedPassword,
                                    'login' => $userInfo['login'],
                                    'upgrade_needed' => 1,
                                ),
                                'id = %i',
                                $userInfo['id']
                            );

                            debugIdentify(
                                DEBUGLDAP,
                                DEBUGLDAPFILE,
                                'User password was updated\n\n'
                            );

                            $proceedIdentification = true;
                            $userInLDAP = true;
                        }
                    } else {
                        // Debug
                        debugIdentify(
                            DEBUGLDAP,
                            DEBUGLDAPFILE,
                            'User not granted - bad password?\n\n'
                        );

                        // CLear the password in database with random token
                        DB::update(
                            prefixTable('users'),
                            array(
                                'pw' => $pwdlib->createPasswordHash($pwdlib->getRandomToken(12)),
                                'login' => $userInfo['login'],
                            ),
                            'id = %i',
                            $userInfo['id']
                        );

                        $ldapConnection = false;
                        $userInLDAP = false;
                    }
                } else {
                    $ldapConnection = false;
                    $userInLDAP = false;
                }
            } else {
                // Debug
                debugIdentify(
                    DEBUGLDAP,
                    DEBUGLDAPFILE,
                    'No Group is defined\n\n'
                );

                $ldapConnection = false;
                $userInLDAP = false;
            }
        } else {
            $ldapConnection = false;
            $userInLDAP = false;
        }
    } else {
        $ldapConnection = false;
        $userInLDAP = false;
    }

    return array(
        'error' => false,
        'message' => $ldapConnection,
        'auth_username' => $username,
        'user_info_from_ad' => $result,
        'proceedIdentification' => $proceedIdentification,
        'userInLDAP' => $userInLDAP,
        'userGroups' => $ldapUserGroups,
        'hashedPassword' => isset($hashedPassword) === true ? $hashedPassword : '',
    );
}

/**
 * Undocumented function.
 *
 * @param string $userInfo      Username
 * @param string $ldap_suffix   Suffix
 * @param string $passwordClear Password
 * @param int    $counter       User exists in teampass
 * @param array  $SETTINGS      Teampass settings
 *
 * @return array
 */
function identifyViaLDAPPosix($userInfo, $ldap_suffix, $passwordClear, $counter, $SETTINGS)
{
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/voku/helper/AntiXSS.php';
    $antiXss = new voku\helper\AntiXSS();

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        "Get all ldap params : \n" .
            'base_dn : ' . $SETTINGS['ldap_domain_dn'] . "\n" .
            'account_suffix : ' . $SETTINGS['ldap_suffix'] . "\n" .
            'domain_controllers : ' . $SETTINGS['ldap_domain_controler'] . "\n" .
            'ad_port : ' . $SETTINGS['ldap_port'] . "\n" .
            'use_ssl : ' . $SETTINGS['ldap_ssl'] . "\n" .
            'use_tls : ' . $SETTINGS['ldap_tls'] . "\n*********\n\n"
    );

    $adldap = new SplClassLoader('adLDAP', '../includes/libraries/LDAP');
    $adldap->register();
    $ldap_suffix = '';
    $ldapConnection = false;

    // Posix style LDAP handles user searches a bit differently
    if ($SETTINGS['ldap_type'] === 'posix') {
        $ldap_suffix = ',' . $SETTINGS['ldap_suffix'] . ',' . $SETTINGS['ldap_domain_dn'];
    } elseif ($SETTINGS['ldap_type'] === 'windows') {
        //Multiple Domain Names
        $ldap_suffix = $SETTINGS['ldap_suffix'];
    }

    // Ensure no double commas exist in ldap_suffix
    $ldap_suffix = str_replace(',,', ',', $ldap_suffix);

    // Create LDAP connection
    $adldap = new adLDAP\adLDAP(
        array(
            'base_dn' => $SETTINGS['ldap_domain_dn'],
            'account_suffix' => $ldap_suffix,
            'domain_controllers' => explode(',', $SETTINGS['ldap_domain_controler']),
            'ad_port' => $SETTINGS['ldap_port'],
            'use_ssl' => $SETTINGS['ldap_ssl'],
            'use_tls' => $SETTINGS['ldap_tls'],
        )
    );

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        'Create new adldap object : ' . $adldap->getLastError() . "\n\n\n"
    );

    // OpenLDAP expects an attribute=value pair
    if ($SETTINGS['ldap_type'] === 'posix') {
        $auth_username = $SETTINGS['ldap_user_attribute'] . '=' . $userInfo['login'];
    } else {
        $auth_username = $userInfo['login'];
    }

    // Authenticate the user
    if ($adldap->authenticate($auth_username, html_entity_decode($passwordClear))) {
        // Is user in allowed group
        if (
            isset($SETTINGS['ldap_allowed_usergroup']) === true
            && empty($SETTINGS['ldap_allowed_usergroup']) === false
        ) {
            if ($adldap->user()->inGroup($auth_username, $SETTINGS['ldap_allowed_usergroup']) === true) {
                $ldapConnection = true;
            } else {
                $ldapConnection = false;
            }
        } else {
            $ldapConnection = true;
        }

        // Is user expired?
        if (is_array($adldap->user()->passwordExpiry($auth_username)) === false) {
            return array(
                'error' => true,
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => 'user_not_exists6',
                'message' => langHdl('error_bad_credentials'),
            );
        }

        // Update user's password
        if ($ldapConnection === true) {
            $userInfo['pw'] = $pwdlib->createPasswordHash($passwordClear);

            // Do things if user exists in TP
            if ($counter > 0) {
                // Update pwd in TP database
                DB::update(
                    prefixTable('users'),
                    array(
                        'pw' => $userInfo['pw'],
                        'login' => $userInfo['login'],
                    ),
                    'id = %i',
                    $userInfo['id']
                );

                // No user creation is requested
                $proceedIdentification = true;
            }
        }
    } else {
        // Debug
        debugIdentify(
            DEBUGLDAP,
            DEBUGLDAPFILE,
            'User not granted - bad password?\n\n'
        );

        // CLear the password in database with random token
        DB::update(
            prefixTable('users'),
            array(
                'pw' => $pwdlib->createPasswordHash($pwdlib->getRandomToken(12)),
                'login' => $userInfo['login'],
            ),
            'id = %i',
            $userInfo['id']
        );

        $ldapConnection = false;
    }

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        'After authenticate : ' . $adldap->getLastError() . "\n\n\n" .
            'ldap status : ' . $ldapConnection . "\n\n\n"
    );

    return array(
        'error' => false,
        'message' => $ldapConnection,
        'auth_username' => $auth_username,
        'proceedIdentification' => $proceedIdentification,
        'user_info_from_ad' => $adldap->user()->info($auth_username, array('mail', 'givenname', 'sn')),
        'userGroups' => $ldapUserGroups,
    );
}

/**
 * Undocumented function.
 *
 * @param string|array $dataReceived Received data
 * @param string       $userInfo     Result of query
 * @param array        $SETTINGS     Teampass settings
 *
 * @return array
 */
function yubicoMFACheck($dataReceived, $userInfo, $SETTINGS)
{
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/voku/helper/AntiXSS.php';
    $antiXss = new voku\helper\AntiXSS();

    $yubico_key = htmlspecialchars_decode($dataReceived['yubico_key']);
    $yubico_user_key = htmlspecialchars_decode($dataReceived['yubico_user_key']);
    $yubico_user_id = htmlspecialchars_decode($dataReceived['yubico_user_id']);

    if (empty($yubico_user_key) === false && empty($yubico_user_id) === false) {
        // save the new yubico in user's account
        DB::update(
            prefixTable('users'),
            array(
                'yubico_user_key' => $yubico_user_key,
                'yubico_user_id' => $yubico_user_id,
            ),
            'id=%i',
            $userInfo['id']
        );
    } else {
        // Check existing yubico credentials
        if ($userInfo['yubico_user_key'] === 'none' || $userInfo['yubico_user_id'] === 'none') {
            return array(
                'error' => true,
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => 'no_user_yubico_credentials',
                'message' => '',
            );
        } else {
            $yubico_user_key = $userInfo['yubico_user_key'];
            $yubico_user_id = $userInfo['yubico_user_id'];
        }
    }

    // Now check yubico validity
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/Yubico/Yubico.php';
    $yubi = new Auth_Yubico($yubico_user_id, $yubico_user_key);
    $auth = $yubi->verify($yubico_key); //, null, null, null, 60

    if (PEAR::isError($auth)) {
        $proceedIdentification = false;

        return array(
            'error' => true,
            'value' => '',
            'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
            'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'error' => 'bad_user_yubico_credentials',
            'message' => langHdl('yubico_bad_code'),
        );
    } else {
        $proceedIdentification = true;
    }

    return array(
        'error' => false,
        'message' => '',
        'proceedIdentification' => $proceedIdentification,
    );
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
function ldapCreateUser($username, $passwordClear, $retLDAP, $SETTINGS)
{
    // Generate user keys pair
    $userKeys = generateUserKeys($passwordClear);

    // Insert user in DB
    DB::insert(
        prefixTable('users'),
        array(
            'login' => $username,
            'pw' => $retLDAP['hashedPassword'],
            'email' => (isset($retLDAP['user_info_from_ad'][0]['mail'][0]) === false) ? '' : $retLDAP['user_info_from_ad'][0]['mail'][0],
            'name' => $retLDAP['user_info_from_ad'][0]['givenname'][0],
            'lastname' => $retLDAP['user_info_from_ad'][0]['sn'][0],
            'admin' => '0',
            'gestionnaire' => '0',
            'can_manage_all_users' => '0',
            'personal_folder' => $SETTINGS['enable_pf_feature'] === '1' ? '1' : '0',
            'fonction_id' => (empty($retLDAP['user_info_from_ad'][0]['commonGroupsLdapVsTeampass']) === false ? $retLDAP['user_info_from_ad'][0]['commonGroupsLdapVsTeampass'] . ';' : '') . (isset($SETTINGS['ldap_new_user_role']) === true ? $SETTINGS['ldap_new_user_role'] : '0'),
            'groupes_interdits' => '',
            'groupes_visibles' => '',
            'last_pw_change' => time(),
            'user_language' => $SETTINGS['default_language'],
            'encrypted_psk' => '',
            'isAdministratedByRole' => (isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && empty($SETTINGS['ldap_new_user_is_administrated_by']) === false) ? $SETTINGS['ldap_new_user_is_administrated_by'] : 0,
            'public_key' => $userKeys['public_key'],
            'private_key' => $userKeys['private_key'],
        )
    );
    $newUserId = DB::insertId();
    // Create personnal folder
    if (isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] === '1') {
        DB::insert(
            prefixTable('nested_tree'),
            array(
                'parent_id' => '0',
                'title' => $newUserId,
                'bloquer_creation' => '0',
                'bloquer_modification' => '0',
                'personal_folder' => '1',
            )
        );

        // Rebuild tree
        $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'] . '/includes/libraries');
        $tree->register();
        $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        $tree->rebuild();
    }

    return array(
        'error' => false,
        'message' => '',
        'proceedIdentification' => true,
        'user_initial_creation_through_ldap' => true,
    );
}

/**
 * Undocumented function.
 *
 * @param string       $username     Username
 * @param string       $userInfo     Result of query
 * @param string|array $dataReceived DataReceived
 * @param array        $SETTINGS     Teampass settings
 *
 * @return array
 */
function googleMFACheck($username, $userInfo, $dataReceived, $SETTINGS)
{
    if (
        isset($dataReceived['GACode']) === true
        && empty($dataReceived['GACode']) === false
    ) {
        // load library
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php';

        // create new instance
        $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($SETTINGS['ga_website_name']);

        // Init
        $firstTime = array();

        // now check if it is the 1st time the user is using 2FA
        if ($userInfo['ga_temporary_code'] !== 'none' && $userInfo['ga_temporary_code'] !== 'done') {
            if ($userInfo['ga_temporary_code'] !== $dataReceived['GACode']) {
                return array(
                    'error' => true,
                    'message' => langHdl('ga_bad_code'),
                    'proceedIdentification' => false,
                    'mfaStatus' => '',
                );
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
                array(
                    'ga_temporary_code' => 'done',
                ),
                'id=%i',
                $userInfo['id']
            );

            $firstTime = array(
                'value' => '<img src="' . $new_2fa_qr . '">',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : '',
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => false,
                'message' => $mfaMessage,
                'mfaStatus' => $mfaStatus,
            );
        } else {
            // verify the user GA code
            if ($tfa->verifyCode($userInfo['ga'], $dataReceived['GACode'])) {
                $proceedIdentification = true;
            } else {
                return array(
                    'error' => true,
                    'message' => langHdl('ga_bad_code'),
                    'proceedIdentification' => false,
                );
            }
        }
    } else {
        return array(
            'error' => true,
            'message' => langHdl('ga_bad_code'),
            'proceedIdentification' => false,
        );
    }

    return array(
        'error' => false,
        'message' => '',
        'proceedIdentification' => $proceedIdentification,
        'firstTime' => $firstTime,
    );
}

/**
 * Undocumented function.
 *
 * @param string       $passwordClear Password in clear
 * @param array|string $userInfo      Array of user data
 * @param array|string $dataReceived  Received data
 * @param string       $username      User name
 * @param array        $SETTINGS      Teampass settings
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
            array(
                'pw' => $userInfo['pw'],
            ),
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
                    array(
                        'pw' => $userInfo['pw'],
                    ),
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
 *
 * @return void
 */
function debugIdentify($enabled, $dbgFile, $text)
{
    if ($enabled === true) {
        $fp = fopen($dbgFile, 'a');
        fwrite(
            $fp,
            $text
        );
    }
}
