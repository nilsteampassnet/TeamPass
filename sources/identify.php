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
 * @file      identify.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
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
    //die('Hacking attempt...');
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

// Load libraries
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

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
DB::$ssl = DB_SSL;
DB::$connect_options = DB_CONNECT_OPTIONS;

if ($post_type === 'identify_user') {
    //--------
    // NORMAL IDENTICATION STEP
    //--------

    // Ensure Complexity levels are translated
    defineComplexity();

    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // If Debug then clean the files
    if (DEBUGLDAP === true) {
        define('DEBUGLDAPFILE', $SETTINGS['path_to_files_folder'] . '/ldap.debug.txt');
        file_put_contents(DEBUGLDAPFILE, '');
    }

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
            $SETTINGS['cpassman_dir'],
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
    echo json_encode([
        'ret' => prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            [
                'agses' => isKeyExistingAndEqual('agses_authentication_enabled', 1, $SETTINGS) === true ? true : false,
                'google' => isKeyExistingAndEqual('google_authentication', 1, $SETTINGS) === true ? true : false,
                'yubico' => isKeyExistingAndEqual('yubico_authentication', 1, $SETTINGS) === true ? true : false,
                'duo' => isKeyExistingAndEqual('duo', 1, $SETTINGS) === true ? true : false,
            ],
            'encode'
        ),
        'key' => $superGlobal->get('key', 'SESSION'),
    ]);
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
    if (findTpConfigFile() === false) {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
    include_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
    include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
    include_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
    
    header('Content-type: text/html; charset=utf-8');
    error_reporting(E_ERROR);

    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/anti-xss-master/src/voku/helper/ASCII.php';
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/anti-xss-master/src/voku/helper/UTF8.php';
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/anti-xss-master/src/voku/helper/AntiXSS.php';
    $antiXss = new voku\helper\AntiXSS();

    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Prepare GET variables
    $sessionUserLanguage = $superGlobal->get('user_language', 'SESSION', 'user');
    $sessionKey = $superGlobal->get('key', 'SESSION');
    $sessionAdmin = $superGlobal->get('user_admin', 'SESSION');
    $sessionPwdAttempts = $superGlobal->get('pwd_attempts', 'SESSION');
    $sessionUrl = $superGlobal->get('initial_url', 'SESSION');
    $server = [];
    $server['PHP_AUTH_USER'] = $superGlobal->get('PHP_AUTH_USER', 'SERVER');
    $server['PHP_AUTH_PW'] = $superGlobal->get('PHP_AUTH_PW', 'SERVER');

    // connect to the server
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defined('DB_PASSWD_CLEAR') === false ? defuseReturnDecrypted(DB_PASSWD, $SETTINGS) : DB_PASSWD_CLEAR;
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    DB::$ssl = DB_SSL;
    DB::$connect_options = DB_CONNECT_OPTIONS;
    // User's language loading
    include_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $sessionUserLanguage . '.php';
    
    // decrypt and retreive data in JSON format
    if (empty($sessionKey) === true) {
        $dataReceived = $sentData;
    } else {
        $dataReceived = prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            $sentData,
            'decode',
            $sessionKey
        );
        $superGlobal->put('key', $sessionKey, 'SESSION');
    }

    // Check if Duo auth is in progress and pass the pw and login back to the standard login process
    if(
        isKeyExistingAndEqual('duo', 1, $SETTINGS) === true
        && $dataReceived['user_2fa_selection'] === 'duo'
        && $superGlobal->get('duo_status','SESSION') === 'IN_PROGRESS'
        && !empty($dataReceived['duo_state'])
    ){
        $key = hash('sha256', $dataReceived['duo_state']);
        $iv = substr(hash('sha256', $dataReceived['duo_state']), 0, 16);
        $duo_data_dec = openssl_decrypt(base64_decode($superGlobal->get('duo_data','SESSION')), 'AES-256-CBC', $key, 0, $iv);
        // Clear the data from the Duo process to continue clean with the standard login process
        $superGlobal->forget('duo_data','SESSION');
        if($duo_data_dec === false){
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                [
                    'error' => true,
                    'message' => langHdl('duo_error_decrypt'),
                ],
                'encode'
            );
            return false;
        }
        $duo_data = unserialize($duo_data_dec);
        $dataReceived['pw'] = $duo_data['duo_pwd'];
        $dataReceived['login'] = $duo_data['duo_login'];
    }

    if(isset($dataReceived['pw']) === false || isset($dataReceived['login']) === false) {
        echo json_encode([
            'data' => prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                [
                    'error' => true,
                    'message' => langHdl('ga_enter_credentials'),
                ],
                'encode'
            ),
            'key' => $_SESSION['key']
        ]);
        return false;
    }

    // prepare variables    
    $userCredentials = identifyGetUserCredentials(
        $SETTINGS,
        (string) $server['PHP_AUTH_USER'],
        (string) $server['PHP_AUTH_PW'],
        (string) filter_var($dataReceived['pw'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        (string) filter_var($dataReceived['login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    );
    $username = $userCredentials['username'];
    $passwordClear = $userCredentials['passwordClear'];
    
    // DO initial checks
    $userInitialData = identifyDoInitialChecks(
        $SETTINGS,
        (int) $sessionPwdAttempts,
        (string) $username,
        (int) $sessionAdmin,
        (string) $sessionUrl,
        (string) filter_var($dataReceived['user_2fa_selection'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    );
    // if user doesn't exist in Teampass then return error
    if ($userInitialData['error'] === true) {
        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            $userInitialData['array'],
            'encode'
        );
        return false;
    }

    $userInfo = $userInitialData['userInfo'];
    $return = '';
    $userLdap = identifyDoLDAPChecks(
        $SETTINGS,
        $userInfo,
        (string) $username,
        (string) $passwordClear,
        (int) $sessionAdmin,
        (string) $sessionUrl,
        (int) $sessionPwdAttempts
    );
    if ($userLdap['error'] === true) {
        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            $userLdap['array'],
            'encode'
        );
        return false;
    }
    if (isset($userLdap['user_info']) === true && (int) $userLdap['user_info']['has_been_created'] === 1) {
        /*$userInfo = DB::queryfirstrow(
            'SELECT *
            FROM ' . prefixTable('users') . '
            WHERE login = %s',
            $username
        );*/
        //$userInfo = $userLdap['user_info'];
        echo json_encode([
            'data' => prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                [
                    'error' => true,
                    'message' => '',
                    'extra' => 'ad_user_created',
                ],
                'encode'
            ),
            'key' => $_SESSION['key']
        ]);
        return false;
    }

    // Check user and password
    if ($userLdap['userPasswordVerified'] === false && (int) checkCredentials($passwordClear, $userInfo, $dataReceived, $username, $SETTINGS) !== 1) {
        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            [
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => true,
                'message' => langHdl('error_bad_credentials'),
            ],
            'encode'
        );
        return false;
    }

    // Check if MFA is required
    if ((isOneVarOfArrayEqualToValue(
                [
                    (int) $SETTINGS['yubico_authentication'],
                    (int) $SETTINGS['google_authentication'],
                    (int) $SETTINGS['duo']
                ],
                1
            ) === true)
        && (((int) $userInfo['admin'] !== 1 && (int) $userInfo['mfa_enabled'] === 1) || ((int) $SETTINGS['admin_2fa_required'] === 1 && (int) $userInfo['admin'] === 1))
        && $userInfo['mfa_auth_requested_roles'] === true
    ) {
        // Check user against MFA method if selected
        $userMfa = identifyDoMFAChecks(
            $SETTINGS,
            $userInfo,
            $dataReceived,
            $userInitialData,
            (string) $username
        );
        if ($userMfa['error'] === true) {
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                [
                    'error' => true,
                    'message' => $userMfa['mfaData']['message'],
                    'mfaStatus' => $userMfa['mfaData']['mfaStatus'],
                ],
                'encode'
            );
            return false;
        } elseif ($userMfa['mfaQRCodeInfos'] === true) {
            // Case where user has initiated Google Auth
            // Return QR code
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                [
                    'value' => $userMfa['mfaData']['value'],
                    'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                    'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                    'pwd_attempts' => (int) $sessionPwdAttempts,
                    'error' => false,
                    'message' => $userMfa['mfaData']['message'],
                    'mfaStatus' => $userMfa['mfaData']['mfaStatus'],
                ],
                'encode'
            );
            return false;
        } elseif ($userMfa['duo_url_ready'] === true) {
            // Case where user has initiated Duo Auth
            // Return the DUO redirect URL
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                [
                    'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                    'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                    'pwd_attempts' => (int) $sessionPwdAttempts,
                    'error' => false,
                    'message' => $userMfa['mfaData']['message'],
                    'duo_url_ready' => $userMfa['mfaData']['duo_url_ready'],
                    'duo_redirect_url' => $userMfa['mfaData']['duo_redirect_url'],
                    'mfaStatus' => $userMfa['mfaData']['mfaStatus'],
                ],
                'encode'
            );
            return false;
        }
    }
/*
    print_r($userLdap);
    print_r($userInfo);
    return false;
    */
    // Can connect if
    // 1- no LDAP mode + user enabled + pw ok
    // 2- LDAP mode + user enabled + ldap connection ok + user is not admin
    // 3- LDAP mode + user enabled + pw ok + usre is admin
    // This in order to allow admin by default to connect even if LDAP is activated
    if (canUserGetLog(
            $SETTINGS,
            (int) $userInfo['disabled'],
            $username,
            $userLdap['ldapConnection']
        ) === true
    ) {
        $superGlobal->put('autoriser', true, 'SESSION');
        $superGlobal->put('pwd_attempts', 0, 'SESSION');

        // Check if any unsuccessfull login tries exist
        $attemptsInfos = handleLoginAttempts(
            $userInfo['id'],
            $userInfo['login'],
            $userInfo['last_connexion'],
            $username,
            $SETTINGS,
        );
            
        // Save account in SESSION
        $superGlobal->put('unsuccessfull_login_attempts_list', $attemptsInfos['attemptsList'], 'SESSION', 'user');
        $superGlobal->put('unsuccessfull_login_attempts_shown', $attemptsInfos['attemptsCount'] === 0 ? true : false, 'SESSION', 'user');
        $superGlobal->put('unsuccessfull_login_attempts_nb', DB::count(), 'SESSION', 'user');
        $superGlobal->put('login', stripslashes($username), 'SESSION');
        $superGlobal->put('name', empty($userInfo['name']) === false ? stripslashes($userInfo['name']) : '', 'SESSION');
        $superGlobal->put('lastname', empty($userInfo['lastname']) === false ? stripslashes($userInfo['lastname']) : '', 'SESSION');
        $superGlobal->put('user_id', (int) $userInfo['id'], 'SESSION');
        $superGlobal->put('user_pwd', $passwordClear, 'SESSION');
        $superGlobal->put('admin', $userInfo['admin'], 'SESSION');
        $superGlobal->put('user_manager', $userInfo['gestionnaire'], 'SESSION');
        $superGlobal->put('user_can_manage_all_users', $userInfo['can_manage_all_users'], 'SESSION');
        $superGlobal->put('user_read_only', (bool) $userInfo['read_only'], 'SESSION');
        $superGlobal->put('last_pw_change', (int) $userInfo['last_pw_change'], 'SESSION');
        $superGlobal->put('last_pw', $userInfo['last_pw'], 'SESSION');
        $superGlobal->put('can_create_root_folder', $userInfo['can_create_root_folder'], 'SESSION');
        $superGlobal->put('personal_folder', $userInfo['personal_folder'], 'SESSION');
        $superGlobal->put('user_email', $userInfo['email'], 'SESSION');
        $superGlobal->put('user_ga', $userInfo['ga'], 'SESSION');
        $superGlobal->put('user_avatar', $userInfo['avatar'], 'SESSION');
        $superGlobal->put('user_avatar_thumb', $userInfo['avatar_thumb'], 'SESSION');
        $superGlobal->put('user_upgrade_needed', $userInfo['upgrade_needed'], 'SESSION');
        $superGlobal->put('user_force_relog', $userInfo['force-relog'], 'SESSION');
        $superGlobal->put('is_ready_for_usage', $userInfo['is_ready_for_usage'], 'SESSION');
        $superGlobal->put(
            'user_treeloadstrategy',
            (isset($userInfo['treeloadstrategy']) === false || empty($userInfo['treeloadstrategy']) === true) ? 'full' : $userInfo['treeloadstrategy'],
            'SESSION',
            'user'
        );
        $superGlobal->put('user_agsescardid', $userInfo['agses-usercardid'], 'SESSION', 'user');
        $superGlobal->put('user_language', $userInfo['user_language'], 'SESSION', 'user');
        $superGlobal->put('user_timezone', $userInfo['usertimezone'], 'SESSION', 'user');
        $superGlobal->put('session_duration', $dataReceived['duree_session'] * 60, 'SESSION', 'user');

        // User signature keys
        $returnKeys = prepareUserEncryptionKeys($userInfo, $passwordClear);        
        $superGlobal->put('public_key', $returnKeys['public_key'], 'SESSION', 'user');
        $superGlobal->put('private_key', $returnKeys['private_key_clear'], 'SESSION', 'user');

        // API key
        $superGlobal->put(
            'api-key',
            empty($userInfo['api_key']) === false ? base64_decode(decryptUserObjectKey($userInfo['api_key'], $returnKeys['private_key_clear'])) : '',
            'SESSION',
            'user'
        );
        
        $superGlobal->put('special', $userInfo['special'], 'SESSION', 'user');
        $superGlobal->put('auth_type', $userInfo['auth_type'], 'SESSION', 'user');
        // manage session expiration
        $superGlobal->put('sessionDuration', (int) (time() + ($dataReceived['duree_session'] * 60)), 'SESSION');

        // check feedback regarding user password validity
        $return = checkUserPasswordValidity(
            $userInfo,
            $superGlobal->get('numDaysBeforePwExpiration', 'SESSION'),
            $superGlobal->get('last_pw_change', 'SESSION'),
            $SETTINGS
        );
        $superGlobal->put('validite_pw', $return['validite_pw'], 'SESSION');
        $superGlobal->put('last_pw_change', $return['last_pw_change'], 'SESSION');
        $superGlobal->put('numDaysBeforePwExpiration', $return['numDaysBeforePwExpiration'], 'SESSION');
        $superGlobal->put('user_force_relog', $return['user_force_relog'], 'SESSION');


        $superGlobal->put(
            'last_connection',
            empty($userInfo['last_connexion']) === false ? (int) $userInfo['last_connexion'] : (int) time(),
            'SESSION'
        );
        
        $superGlobal->put(
            'latest_items',
            empty($userInfo['latest_items']) === false ? explode(';', $userInfo['latest_items']) : [],
            'SESSION'
        );
        
        $superGlobal->put(
            'favourites',
            empty($userInfo['favourites']) === false ? explode(';', $userInfo['favourites']) : [],
            'SESSION'
        );
        
        $superGlobal->put(
            'groupes_visibles',
            empty($userInfo['groupes_visibles']) === false ? explode(';', $userInfo['groupes_visibles']) : [],
            'SESSION'
        );
        
        $superGlobal->put(
            'no_access_folders',
            empty($userInfo['groupes_interdits']) === false ? explode(';', $userInfo['groupes_interdits']) : [],
            'SESSION'
        );
        
        // User's roles
        if (strpos($userInfo['fonction_id'] !== NULL ? (string) $userInfo['fonction_id'] : '', ',') !== -1) {
            // Convert , to ;
            $userInfo['fonction_id'] = str_replace(',', ';', (string) $userInfo['fonction_id']);
            DB::update(
                prefixTable('users'),
                [
                    'fonction_id' => $userInfo['fonction_id'],
                ],
                'id = %i',
                $superGlobal->get('user_id', 'SESSION')
            );
        }
        // Append with roles from AD groups
        if (is_null($userInfo['roles_from_ad_groups']) === false) {
            $userInfo['fonction_id'] = empty($userInfo['fonction_id'])  === true ? $userInfo['roles_from_ad_groups'] : $userInfo['fonction_id']. ';' . $userInfo['roles_from_ad_groups'];
        }
        // store
        $superGlobal->put('fonction_id', $userInfo['fonction_id'], 'SESSION');
        $superGlobal->put('user_roles', array_filter(explode(';', $userInfo['fonction_id'])), 'SESSION');
        // build array of roles
        $superGlobal->put('user_pw_complexity', 0, 'SESSION');
        $superGlobal->put('arr_roles', [], 'SESSION');
        if (count($superGlobal->get('user_roles', 'SESSION')) > 0) {
            $rolesList = DB::query(
                'SELECT id, title, complexity
                FROM ' . prefixTable('roles_title') . '
                WHERE id IN %li',
                $superGlobal->get('user_roles', 'SESSION')
            );
            $excludeUser = isset($SETTINGS['exclude_user']) ? str_contains($superGlobal->get('login'), $SETTINGS['exclude_user']) : false;
            $adjustPermissions = ($superGlobal->get('user_id', 'SESSION') >= 1000000 && !$excludeUser && (isset($SETTINGS['admin_needle']) || isset($SETTINGS['manager_needle']) || isset($SETTINGS['tp_manager_needle']) || isset($SETTINGS['read_only_needle'])));
            if ($adjustPermissions) {
                $userInfo['admin'] = $userInfo['gestionnaire'] = $userInfo['can_manage_all_users'] = $userInfo['read_only'] = 0;
            }
            foreach ($rolesList as $role) {
                $superGlobal->put(
                    $role['id'],
                    [
                        'id' => $role['id'],
                        'title' => $role['title'],
                    ],
                    'SESSION',
                    'arr_roles'
                );
                
                if ($adjustPermissions) {
                    if (isset($SETTINGS['admin_needle']) && str_contains($role['title'], $SETTINGS['admin_needle'])) {
                         $userInfo['gestionnaire'] = $userInfo['can_manage_all_users'] = $userInfo['read_only'] = 0;
                         $userInfo['admin'] = 1;
                    }    
                    if (isset($SETTINGS['manager_needle']) && str_contains($role['title'], $SETTINGS['manager_needle'])) {
                         $userInfo['admin'] = $userInfo['can_manage_all_users'] = $userInfo['read_only'] = 0;
                         $userInfo['gestionnaire'] = 1;
                    }
                    if (isset($SETTINGS['tp_manager_needle']) && str_contains($role['title'], $SETTINGS['tp_manager_needle'])) {
                         $userInfo['admin'] = $userInfo['gestionnaire'] = $userInfo['read_only'] = 0;
                         $userInfo['can_manage_all_users'] = 1;
                    }
                    if (isset($SETTINGS['read_only_needle']) && str_contains($role['title'], $SETTINGS['read_only_needle'])) {
                         $userInfo['admin'] = $userInfo['gestionnaire'] = $userInfo['can_manage_all_users'] = 0;
                         $userInfo['read_only'] = 1;
                    }
                }

                // get highest complexity
                if (intval($superGlobal->get('user_pw_complexity', 'SESSION')) < intval($role['complexity'])) {
                    $superGlobal->put('user_pw_complexity', $role['complexity'], 'SESSION');
                }
            }
            if ($adjustPermissions) {
                $superGlobal->put('admin', $userInfo['admin'], 'SESSION');
                $superGlobal->put('user_manager', $userInfo['gestionnaire'], 'SESSION');
                $superGlobal->put('user_can_manage_all_users', $userInfo['can_manage_all_users'], 'SESSION');
                $superGlobal->put('user_read_only', (bool) $userInfo['read_only'], 'SESSION');
                DB::update(
                    prefixTable('users'),
                    [
                        'admin' => $userInfo['admin'],
                        'gestionnaire' => $userInfo['gestionnaire'],
                        'can_manage_all_users' => $userInfo['can_manage_all_users'],
                        'read_only' => $userInfo['read_only'],
                    ],
                    'id = %i',
                    $superGlobal->get('user_id', 'SESSION')
                );
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
                $returnKeys['update_keys_in_db']
            ),
            'id=%i',
            $userInfo['id']
        );
        
        // Get user's rights
        if ($userLdap['user_initial_creation_through_ldap'] !== false) {
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
        } else {
            identifyUserRights(
                $userInfo['groupes_visibles'],
                $superGlobal->get('no_access_folders', 'SESSION'),
                $userInfo['admin'],
                $userInfo['fonction_id'],
                $SETTINGS
            );
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
            isKeyExistingAndEqual('enable_send_email_on_user_login', 1, $SETTINGS) === true
            && (int) $sessionAdmin !== 1
        ) {
            // get all Admin users
            $val = DB::queryfirstrow('SELECT email FROM ' . prefixTable('users') . " WHERE admin = %i and email != ''", 1);
            if (DB::count() > 0) {
                // Add email to table
                prepareSendingEmail(
                    langHdl('email_subject_on_user_login'),
                    str_replace(
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
                    $val['email'],
                    langHdl('administrator'),
                    $SETTINGS
                );
            }
        }

        // Ensure Complexity levels are translated
        defineComplexity();

        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            [
                'value' => $return,
                'user_id' => $superGlobal->get('user_id', 'SESSION') !== null ? (int) $superGlobal->get('user_id', 'SESSION') : '',
                'user_admin' => $superGlobal->get('admin', 'SESSION') !== null ? (int) $superGlobal->get('admin', 'SESSION') : 0,
                'initial_url' => $antiXss->xss_clean($sessionUrl),
                'pwd_attempts' => 0,
                'error' => false,
                'message' => $superGlobal->get('user_upgrade_needed', 'SESSION') !== null && (int) $superGlobal->get('user_upgrade_needed', 'SESSION') === 1 ? 'ask_for_otc' : '',
                'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
                'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
                'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                'private_key_conform' => $superGlobal->get('user_id', 'SESSION') !== null
                    && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                    && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none' ? true : false,
                'session_key' => $superGlobal->get('key', 'SESSION'),
                'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
                'upgrade_needed' => isset($userInfo['upgrade_needed']) === true ? (int) $userInfo['upgrade_needed'] : 0,
                'special' => isset($userInfo['special']) === true ? (int) $userInfo['special'] : 0,
            ],
            'encode'
        );
    
        return true;

    } elseif ((int) $userInfo['disabled'] === 1) {
        // User and password is okay but account is locked
        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
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
                'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
            ],
            'encode'
        );
        return false;
    }

    // DEFAULT CASE
    // User exists in the DB but Password is false
    // check if user is locked
    if (isUserLocked(
            (int) $userInfo['no_bad_attempts'],
            $userInfo['id'],
            $username,
            $superGlobal->get('key', 'SESSION'),
            $SETTINGS
        ) === true
    ) {
        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
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
                'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
                'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
                'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
            ],
            'encode'
        );
        return false;
    }
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        [
            'value' => $return,
            'user_id' => $superGlobal->get('user_id', 'SESSION') !== null ? (int) $superGlobal->get('user_id', 'SESSION') : '',
            'user_admin' => $superGlobal->get('admin', 'SESSION') !== null ? (int) $superGlobal->get('admin', 'SESSION') : 0,
            'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'error' => true,
            'message' => langHdl('error_not_allowed_to_authenticate'),
            'first_connection' => $superGlobal->get('validite_pw', 'SESSION') === false ? true : false,
            'password_complexity' => TP_PW_COMPLEXITY[$superGlobal->get('user_pw_complexity', 'SESSION')][1],
            'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
            'private_key_conform' => $superGlobal->get('user_id', 'SESSION') !== null
                    && empty($superGlobal->get('private_key', 'SESSION', 'user')) === false
                    && $superGlobal->get('private_key', 'SESSION', 'user') !== 'none' ? true : false,
            'session_key' => $superGlobal->get('key', 'SESSION'),
            'can_create_root_folder' => $superGlobal->get('can_create_root_folder', 'SESSION') !== null ? (int) $superGlobal->get('can_create_root_folder', 'SESSION') : '',
            'shown_warning_unsuccessful_login' => $superGlobal->get('unsuccessfull_login_attempts_shown', 'SESSION', 'user'),
            'nb_unsuccessful_logins' => $superGlobal->get('unsuccessfull_login_attempts_nb', 'SESSION', 'user'),
        ],
        'encode'
    );
    return false;
}

/**
 * Check if any unsuccessfull login tries exist
 *
 * @param int       $userInfoId
 * @param string    $userInfoLogin
 * @param string    $userInfoLastConnection
 * @param string    $username
 * @param array     $SETTINGS
 * @return array
 */
function handleLoginAttempts(
    $userInfoId,
    $userInfoLogin,
    $userInfoLastConnection,
    $username,
    $SETTINGS
) : array
{
    $rows = DB::query(
        'SELECT date
        FROM ' . prefixTable('log_system') . "
        WHERE field_1 = %s
        AND type = 'failed_auth'
        AND label = 'password_is_not_correct'
        AND date >= %s AND date < %s",
        $userInfoLogin,
        $userInfoLastConnection,
        time()
    );
    $arrAttempts = [];
    if (DB::count() > 0) {
        foreach ($rows as $record) {
            array_push(
                $arrAttempts,
                date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date'])
            );
        }
    }
    

    // Log into DB the user's connection
    if (isKeyExistingAndEqual('log_connections', 1, $SETTINGS) === true) {
        logEvents($SETTINGS, 'user_connection', 'connection', (string) $userInfoId, stripslashes($username));
    }

    return [
        'attemptsList' => $arrAttempts,
        'attemptsCount' => count($rows),
    ];
}

/**
 * Permits to load config file
 *
 * @return boolean
 */
function findTpConfigFile() : bool
{
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
        return true;
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } elseif (file_exists('../../../includes/config/tp.config.php')) {
        include_once '../../../includes/config/tp.config.php';
    }
    return false;
}

/**
 * Can you user get logged into main page
 *
 * @param array     $SETTINGS
 * @param int       $userInfoDisabled
 * @param string    $username
 * @param bool      $ldapConnection
 *
 * @return boolean
 */
function canUserGetLog(
    $SETTINGS,
    $userInfoDisabled,
    $username,
    $ldapConnection
) : bool
{
    include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

    if ((int) $userInfoDisabled === 1) {
        return false;
    }

    if (isKeyExistingAndEqual('ldap_mode', 0, $SETTINGS) === true) {
        return true;
    }
    
    if (isKeyExistingAndEqual('ldap_mode', 1, $SETTINGS) === true 
        && (
            ($ldapConnection === true && $username !== 'admin')
            || $username === 'admin'
        )
    ) {
        return true;
    }

    if (isKeyExistingAndEqual('ldap_and_local_authentication', 1, $SETTINGS) === true
        && isset($SETTINGS['ldap_mode']) === true && in_array($SETTINGS['ldap_mode'], ['1', '2']) === true
    ) {
        return true;
    }

    return false;
}

/**
 * Manages if user is locked or not
 *
 * @param int       $nbAttempts
 * @param int       $userId
 * @param string    $username
 * @param string    $key
 * @param array     $SETTINGS
 *
 * @return boolean
 */
function isUserLocked(
    $nbAttempts,
    $userId,
    $username,
    $key,
    $SETTINGS
) : bool 
{
    $userIsLocked = false;
    $nbAttempts++;
    if (
        (int) $SETTINGS['nb_bad_authentication'] > 0
        && (int) $SETTINGS['nb_bad_authentication'] < $nbAttempts
    ) {
        // User is now locked as too many attempts
        $userIsLocked = true;

        // log it
        if (isKeyExistingAndEqual('log_connections', 1, $SETTINGS) === true) {
            logEvents($SETTINGS, 'user_locked', 'connection', (string) $userId, stripslashes($username));
        }
    }
    
    DB::update(
        prefixTable('users'),
        [
            'key_tempo' => $key,
            'disabled' => $userIsLocked,
            'no_bad_attempts' => $nbAttempts,
        ],
        'id=%i',
        $userId
    );

    return $userIsLocked;
}


/**
 * 
 * Prepare user keys
 * 
 * @param array $userInfo   User account information
 * @param string $passwordClear
 *
 * @return array
 */
function prepareUserEncryptionKeys($userInfo, $passwordClear) : array
{
    if (is_null($userInfo['private_key']) === true || empty($userInfo['private_key']) === true || $userInfo['private_key'] === 'none') {
        // No keys have been generated yet
        // Create them
        $userKeys = generateUserKeys($passwordClear);

        return [
            'public_key' => $userKeys['public_key'],
            'private_key_clear' => $userKeys['private_key_clear'],
            'update_keys_in_db' => [
                'public_key' => $userKeys['public_key'],
                'private_key' => $userKeys['private_key'],
            ],
        ];
    } 
    
    if ($userInfo['special'] === 'generate-keys') {
        return [
            'public_key' => $userInfo['public_key'],
            'private_key_clear' => '',
            'update_keys_in_db' => [],
        ];
    }
    
    // Don't perform this in case of special login action
    if ($userInfo['special'] === 'otc_is_required_on_next_login' || $userInfo['special'] === 'user_added_from_ldap') {
        return [
            'public_key' => $userInfo['public_key'],
            'private_key_clear' => '',
            'update_keys_in_db' => [],
        ];
    }
    
    // Uncrypt private key
    return [
        'public_key' => $userInfo['public_key'],
        'private_key_clear' => decryptPrivateKey($passwordClear, $userInfo['private_key']),
        'update_keys_in_db' => [],
    ];
}


/**
 * CHECK PASSWORD VALIDITY
 * Don't take into consideration if LDAP in use
 * 
 * @param array $userInfo                       User account information
 * @param int $numDaysBeforePwExpiration
 * @param int $lastPwChange
 * @param array $SETTINGS                       Teampass settings
 *
 * @return array
 */
function checkUserPasswordValidity($userInfo, $numDaysBeforePwExpiration, $lastPwChange, $SETTINGS)
{
    if (isKeyExistingAndEqual('ldap_mode', 1, $SETTINGS) === true) {
        return [
            'validite_pw' => true,
            'last_pw_change' => $userInfo['last_pw_change'],
            'user_force_relog' => '',
            'numDaysBeforePwExpiration' => '',
        ];
    }

    if (isset($userInfo['last_pw_change']) === true) {
        if ((int) $SETTINGS['pw_life_duration'] === 0) {
            return [
                'validite_pw' => true,
                'last_pw_change' => '',
                'user_force_relog' => 'infinite',
                'numDaysBeforePwExpiration' => '',
            ];
        }
        
        return [
            'validite_pw' => $numDaysBeforePwExpiration <= 0 ? false : true,
            'last_pw_change' => $userInfo['last_pw_change'],
            'user_force_relog' => 'infinite',
            'numDaysBeforePwExpiration' => $SETTINGS['pw_life_duration'] - round(
                (mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')) - $lastPwChange) / (24 * 60 * 60)),
        ];
    } else {
        return [
            'validite_pw' => false,
            'last_pw_change' => '',
            'user_force_relog' => '',
            'numDaysBeforePwExpiration' => '',
        ];
    }
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
    // Load expected libraries
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Illuminate/Contracts/Auth/Authenticatable.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Illuminate/Contracts/Support/Arrayable.php';
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
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/HandlesConnection.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/Ldap.php';
    $ad = new SplClassLoader('LdapRecord', '../includes/libraries');
    $ad->register();

    // Build ldap configuration array
    $config = [
        // Mandatory Configuration Options
        'hosts' => [explode(',', $SETTINGS['ldap_hosts'])],
        'base_dn' => $SETTINGS['ldap_bdn'],
        'username' => $SETTINGS['ldap_username'],
        'password' => $SETTINGS['ldap_password'],

        // Optional Configuration Options
        'port' => $SETTINGS['ldap_port'],
        'use_ssl' => (int) $SETTINGS['ldap_ssl'] === 1 ? true : false,
        'use_tls' => (int) $SETTINGS['ldap_tls'] === 1 ? true : false,
        'version' => 3,
        'timeout' => 5,
        'follow_referrals' => false,

        // Custom LDAP Options
        'options' => [
            // See: http://php.net/ldap_set_option
            LDAP_OPT_X_TLS_REQUIRE_CERT => (isset($SETTINGS['ldap_tls_certiface_check']) ? $SETTINGS['ldap_tls_certiface_check'] : LDAP_OPT_X_TLS_HARD),
        ],
    ];
    //prepare connection
    $connection = new Connection($config);
    
    try {
        // Connect to LDAP
        $connection->connect();
        Container::addConnection($connection);

        // Get user info from AD
        // We want to isolate attribute ldap_user_attribute
        $userADInfos = $connection->query()
            ->where((isset($SETTINGS['ldap_user_attribute']) ===true && empty($SETTINGS['ldap_user_attribute']) === false) ? strtolower($SETTINGS['ldap_user_attribute']) : 'distinguishedname', '=', $username)
            ->firstOrFail();

    } catch (\LdapRecord\Query\ObjectNotFoundException $e) {
        return [
            'error' => true,
            'message' => langHdl('error_bad_credentials')

        ];
    }

    // Check shadowexpire attribute - if === 1 then user disabled
    if (
        (isset($userADInfos['shadowexpire'][0]) === true && (int) $userADInfos['shadowexpire'][0] === 1)
        ||
        (isset($userADInfos['accountexpires'][0]) === true && (int) $userADInfos['accountexpires'][0] < time() && (int) $userADInfos['accountexpires'][0] != 0)
    ) {
        return [
            'error' => true,
            'message' => langHdl('error_ad_user_expired'),
        ];
    }

    // User auth attempt
    $userAuthAttempt = $connection->auth()->attempt(
        $SETTINGS['ldap_type'] === 'ActiveDirectory' ?
            $userADInfos[(isset($SETTINGS['ldap_user_dn_attribute']) === true && empty($SETTINGS['ldap_user_dn_attribute']) === false) ? $SETTINGS['ldap_user_dn_attribute'] : 'cn'][0] :
            $userADInfos['dn'],
        $passwordClear
    );
    
    // User is not auth then return error
    if ($userAuthAttempt === false) {
        return [
            'error' => true,
            'message' => "Error : User could not be authentificated",
        ];
    }

    // Create LDAP user if not exists and tasks enabled
    if ($userInfo['ldap_user_to_be_created'] === true) {   
        $userInfo = ldapCreateUser(
            $username,
            $passwordClear,
            $userADInfos['mail'][0],
            $userADInfos['givenname'][0],
            $userADInfos['sn'][0],
            $SETTINGS
        );

        // prepapre background tasks for item keys generation  
        handleUserKeys(
            (int) $userInfo['id'],
            (string) $passwordClear,
            (int) isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
            uniqidReal(20),
            true,
            true,
            true,
            false,
            langHdl('email_body_user_config_2'),
        );

        // Complete $userInfo
        $userInfo['has_been_created'] = 1;
    } else {
        $userInfo['has_been_created'] = 0;
    }

    // Update user info with his AD groups
    if ($SETTINGS['ldap_type'] === 'ActiveDirectory') {
        require_once 'ldap.activedirectory.php';
    } else {
        require_once 'ldap.openldap.php';
    }   
    $ret = getUserADGroups(
        $SETTINGS['ldap_type'] === 'ActiveDirectory' ?
            $userADInfos[(isset($SETTINGS['ldap_user_dn_attribute']) === true && empty($SETTINGS['ldap_user_dn_attribute']) === false) ? $SETTINGS['ldap_user_dn_attribute'] : 'cn'][0] :
            $userADInfos['dn'], 
        $connection, 
        $SETTINGS
    );
    
    handleUserADGroups(
        $username,
        $userInfo,
        $ret['userGroups'],
        $SETTINGS
    );

    // Finalize authentication
    finalizeAuthentication($userInfo, $passwordClear, $SETTINGS);

    return [
        'error' => false,
        'message' => '',
        'user_info' => $userInfo,
    ];
}

/**
 * Permits to update the user's AD groups with mapping roles
 *
 * @param string $username
 * @param array $userInfo
 * @param array $groups
 * @param array $SETTINGS
 * @return void
 */
function handleUserADGroups(string $username, array $userInfo, array $groups, array $SETTINGS): void
{
    if (isset($SETTINGS['enable_ad_users_with_ad_groups']) === true && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1) {
        // Get user groups from AD
        $user_ad_groups = [];
        foreach($groups as $group) {
            //print_r($group);
            // get relation role id for AD group
            $role = DB::queryFirstRow(
                'SELECT lgr.role_id
                FROM ' . prefixTable('ldap_groups_roles') . ' AS lgr
                WHERE lgr.ldap_group_id = %i',
                $group
            );
            if (DB::count() > 0) {
                array_push($user_ad_groups, $role['role_id']); 
            }
        }
        
        // save
        if (count($user_ad_groups) > 0) {
            $user_ad_groups = implode(';', $user_ad_groups);
            DB::update(
                prefixTable('users'),
                [
                    'roles_from_ad_groups' => $user_ad_groups,
                ],
                'id = %i',
                $userInfo['id']
            );

            $userInfo['roles_from_ad_groups'] = $user_ad_groups;
        } else {
            DB::update(
                prefixTable('users'),
                [
                    'roles_from_ad_groups' => null,
                ],
                'id = %i',
                $userInfo['id']
            );

            $userInfo['roles_from_ad_groups'] = [];
        }
    } else {
        // Delete all user's AD groups
        DB::update(
            prefixTable('users'),
            [
                'roles_from_ad_groups' => null,
            ],
            'id = %i',
            $userInfo['id']
        );
    }
}

/**
 * Permits to finalize the authentication process.
 *
 * @param array $userInfo
 * @param string $passwordClear
 * @param array $SETTINGS
 */
function finalizeAuthentication(
    array $userInfo,
    string $passwordClear,
    array $SETTINGS
): void
{
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
    } elseif ($userInfo['special'] === 'user_added_from_ldap') {
        // Case where user has been added from LDAP and never being connected to TP
        DB::update(
            prefixTable('users'),
            array(
                'pw' => $hashedPassword,
            ),
            'id = %i',
            $userInfo['id']
        );
    } elseif ($pwdlib->verifyPasswordHash($passwordClear, $userInfo['pw']) === false) {
        // Case where user is auth by LDAP but his password in Teampass is not synchronized
        // For example when user has changed his password in AD.
        // So we need to update it in Teampass and ask for private key re-encryption
        DB::update(
            prefixTable('users'),
            [
                'pw' => $hashedPassword,
            ],
            'id = %i',
            $userInfo['id']
        );
    }
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
 * @param array $retLDAP       Received data from LDAP
 * @param array $SETTINGS      Teampass settings
 *
 * @return array
 */
function ldapCreateUser(string $login, string $passwordClear, string $userEmail, string $userName, string $userLastname, array $SETTINGS): array
{
    // Generate user keys pair
    $userKeys = generateUserKeys($passwordClear);

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();
    $hashedPassword = $pwdlib->createPasswordHash($passwordClear);

    // Insert user in DB
    DB::insert(
        prefixTable('users'),
        [
            'login' => (string) $login,
            'pw' => (string) $hashedPassword,
            'email' => (string) $userEmail,
            'name' => (string) $userName,
            'lastname' => (string) $userLastname,
            'admin' => '0',
            'gestionnaire' => '0',
            'can_manage_all_users' => '0',
            'personal_folder' => $SETTINGS['enable_pf_feature'] === '1' ? '1' : '0',
            'groupes_interdits' => '',
            'groupes_visibles' => '',
            'last_pw_change' => (int) time(),
            'user_language' => (string) $SETTINGS['default_language'],
            'encrypted_psk' => '',
            'isAdministratedByRole' => isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && empty($SETTINGS['ldap_new_user_is_administrated_by']) === false ? $SETTINGS['ldap_new_user_is_administrated_by'] : 0,
            'public_key' => $userKeys['public_key'],
            'private_key' => $userKeys['private_key'],
            'special' => 'none',
            'auth_type' => 'ldap',
            'otp_provided' => '1',
            'is_ready_for_usage' => '0',
        ]
    );
    $newUserId = DB::insertId();

    // Create the API key
    DB::insert(
        prefixTable('api'),
        array(
            'type' => 'user',
            'user_id' => $newUserId,
            'value' => encryptUserObjectKey(base64_encode(base64_encode(uniqidReal(39))), $userKeys['public_key']),
            'timestamp' => time(),
        )
    );

    // Create personnal folder
    if (isKeyExistingAndEqual('enable_pf_feature', 1, $SETTINGS) === true) {
        DB::insert(
            prefixTable('nested_tree'),
            [
                'parent_id' => '0',
                'title' => $newUserId,
                'bloquer_creation' => '0',
                'bloquer_modification' => '0',
                'personal_folder' => '1',
                'categories' => '',
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
        'id' => $newUserId,
    ];
}

/**
 * Undocumented function.
 *
 * @param string                $username     Username
 * @param array                 $userInfo     Result of query
 * @param string|array|resource $dataReceived DataReceived
 * @param array                 $SETTINGS     Teampass settings
 *
 * @return array
 */
function googleMFACheck(string $username, array $userInfo, $dataReceived, array $SETTINGS): array
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
                    'ga_bad_code' => true,
                    'firstTime' => $firstTime,
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
                    'ga_bad_code' => true,
                    'firstTime' => $firstTime,
                ];
            }
        }
    } else {
        return [
            'error' => true,
            'message' => langHdl('ga_bad_code'),
            'proceedIdentification' => false,
            'ga_bad_code' => true,
            'firstTime' => [],
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
 * Perform DUO checks
 *
 * @param string $username
 * @param string|array|resource $dataReceived
 * @param array $SETTINGS
 * @return array
 */
function duoMFACheck(
    string $username,
    $dataReceived,
    array $SETTINGS
): array
{
    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';            
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    $sessionPwdAttempts = $superGlobal->get('pwd_attempts', 'SESSION');
    $saved_state = null !== $superGlobal->get('duo_state','SESSION') ? $superGlobal->get('duo_state','SESSION') : '';
    $duo_status = null !== $superGlobal->get('duo_status','SESSION') ? $superGlobal->get('duo_status','SESSION') : '';

    // Ensure state and login are set
    if (
        (empty($saved_state) || empty($dataReceived['login']) || !isset($dataReceived['duo_state']) || empty($dataReceived['duo_state']))
        && $duo_status === 'IN_PROGRESS'
        && $dataReceived['duo_status'] !== 'start_duo_auth'
    ) {
        return [
            'error' => true,
            'message' => langHdl('duo_no_data'),
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'proceedIdentification' => false,
        ];
    }

    // Ensure state matches from initial request
    if ($duo_status === 'IN_PROGRESS' && $dataReceived['duo_state'] !== $saved_state) {
        // We did not received a proper Duo state
        return [
            'error' => true,
            'message' => langHdl('duo_error_state'),
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'proceedIdentification' => false,
        ];
    }

    return [
        'error' => false,
        'pwd_attempts' => (int) $sessionPwdAttempts,
        'saved_state' => $saved_state,
        'duo_status' => $duo_status,
    ];
}


/**
 * Create the redirect URL or check if the DUO Universal prompt was completed successfully.
 *
 * @param string                $username               Username
 * @param string|array|resource $dataReceived           DataReceived
 * @param array                 $sessionPwdAttempts     Nb of pwd attempts
 * @param array                 $saved_state            Saved state
 * @param array                 $duo_status             Duo status
 * @param array                 $SETTINGS               Teampass settings
 *
 * @return array
 */
function duoMFAPerform(
    string $username,
    $dataReceived,
    int $sessionPwdAttempts,
    string $saved_state,
    string $duo_status,
    array $SETTINGS
): array
{
    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';            
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // load libraries
    require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/BeforeValidException.php';
    require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/ExpiredException.php';
    require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/SignatureInvalidException.php';
    require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/JWT.php';
    require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/php-jwt/Key.php';
    require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoUniversal/DuoException.php';
    require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoUniversal/Client.php';

    try {
        $duo_client = new Duo\DuoUniversal\Client(
            $SETTINGS['duo_ikey'],
            $SETTINGS['duo_skey'],
            $SETTINGS['duo_host'],
            $SETTINGS['cpassman_url'].'/'.DUO_CALLBACK
        );
    } catch (Duo\DuoUniversal\DuoException $e) {
        return [
            'error' => true,
            'message' => langHdl('duo_config_error'),
            'debug_message' => $e->getMessage(),
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'proceedIdentification' => false,
        ];
    }
        
    try {
        $duo_error = langHdl('duo_error_secure');
        $duo_failmode = "none";
        $duo_client->healthCheck();
    } catch (Duo\DuoUniversal\DuoException $e) {
        //Not implemented Duo Failmode in case the Duo services are not available
        /*if ($SETTINGS['duo_failmode'] == "safe") {
            # If we're failing open, errors in 2FA still allow for success
            $duo_error = langHdl('duo_error_failopen');
            $duo_failmode = "safe";
        } else {
            # Duo has failed and is unavailable, redirect user to the login page
            $duo_error = langHdl('duo_error_secure');
            $duo_failmode = "secure";
        }*/
        return [
            'error' => true,
            'message' => $duo_error . langHdl('duo_error_check_config'),
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'debug_message' => $e->getMessage(),
            'proceedIdentification' => false,
        ];
    }
    
    // Check if no one played with the javascript
    if ($duo_status !== 'IN_PROGRESS' && $dataReceived['duo_status'] === 'start_duo_auth') {
        # Create the Duo URL to send the user to
        try {
            $duo_state = $duo_client->generateState();
            $duo_redirect_url = $duo_client->createAuthUrl($username, $duo_state);
        } catch (Duo\DuoUniversal\DuoException $e) {
            return [
                'error' => true,
                'message' => $duo_error . langHdl('duo_error_url'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'debug_message' => $e->getMessage(),
                'proceedIdentification' => false,
            ];
        }
        
        // Somethimes Duo return success but fail to return a URL, double check if the URL has been created
        if (!empty($duo_redirect_url) && isset($duo_redirect_url) && filter_var($duo_redirect_url,FILTER_SANITIZE_URL)) {
            // Since Duo Universal requires a redirect, let's store some info when the user get's back after completing the Duo prompt
            $key = hash('sha256', $duo_state);
            $iv = substr(hash('sha256', $duo_state), 0, 16);
            $duo_data = serialize([
                'duo_login' => $username,
                'duo_pwd' => $dataReceived['pw'],
            ]);
            $duo_data_enc = openssl_encrypt($duo_data, 'AES-256-CBC', $key, 0, $iv);
            $superGlobal->put('duo_state', $duo_state, 'SESSION');
            $superGlobal->put('duo_data', base64_encode($duo_data_enc), 'SESSION');
            $superGlobal->put('duo_status', 'IN_PROGRESS', 'SESSION');
            // If we got here we can reset the password attempts
            $superGlobal->put('pwd_attempts', 0, 'SESSION');
            unset($superGlobal);
            return [
                'error' => false,
                'message' => '',
                'proceedIdentification' => false,
                'duo_url_ready' => true,
                'duo_redirect_url' => $duo_redirect_url,
                'duo_failmode' => $duo_failmode,
            ];
        } else {
            return [
                'error' => true,
                'message' => $duo_error . langHdl('duo_error_url'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'proceedIdentification' => false,
            ];
        }
    } elseif ($duo_status === 'IN_PROGRESS' && $dataReceived['duo_code'] !== '') {
        try {
            // Check if the Duo code received is valid
            $decoded_token = $duo_client->exchangeAuthorizationCodeFor2FAResult($dataReceived['duo_code'], $username);
        } catch (Duo\DuoUniversal\DuoException $e) {
            return [
                'error' => true,
                'message' => langHdl('duo_error_decoding'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'debug_message' => $e->getMessage(),
                'proceedIdentification' => false,
            ];
        }
        // return the response (which should be the user name)
        if ($decoded_token['preferred_username'] === $username) {
            $superGlobal->put('duo_status', 'COMPLET', 'SESSION');
            $superGlobal->forget('duo_state','SESSION');
            $superGlobal->forget('duo_data','SESSION');
            unset($superGlobal);

            return [
                'error' => false,
                'message' => '',
                'proceedIdentification' => true,
                'authenticated_username' => $decoded_token['preferred_username']
            ];
        } else {
            // Something wrong, username from the original Duo request is different than the one received now
            $superGlobal->forget('duo_status','SESSION');
            $superGlobal->forget('duo_state','SESSION');
            $superGlobal->forget('duo_data','SESSION');
            unset($superGlobal);

            return [
                'error' => true,
                'message' => langHdl('duo_login_mismatch'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'proceedIdentification' => false,
            ];
        }
    }
    // If we are here something wrong
    $superGlobal->forget('duo_status','SESSION');
    $superGlobal->forget('duo_state','SESSION');
    $superGlobal->forget('duo_data','SESSION');
    unset($superGlobal);
    return [
        'error' => true,
        'message' => langHdl('duo_login_mismatch'),
        'pwd_attempts' => (int) $sessionPwdAttempts,
        'proceedIdentification' => false,
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
                    'password_is_not_correct',
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



function identifyGetUserCredentials(
    array $SETTINGS,
    string $serverPHPAuthUser,
    string $serverPHPAuthPw,
    string $userPassword,
    string $userLogin
): array
{
    if ((int) $SETTINGS['enable_http_request_login'] === 1
        && $serverPHPAuthUser !== null
        && (int) $SETTINGS['maintenance_mode'] === 1
    ) {
        if (strpos($serverPHPAuthUser, '@') !== false) {
            return [
                'username' => explode('@', $serverPHPAuthUser)[0],
                'passwordClear' => $serverPHPAuthPw
            ];
        }
        
        if (strpos($serverPHPAuthUser, '\\') !== false) {
            return [
                'username' => explode('\\', $serverPHPAuthUser)[1],
                'passwordClear' => $serverPHPAuthPw
            ];
        }

        return [
            'username' => $serverPHPAuthPw,
            'passwordClear' => $serverPHPAuthPw
        ];
    }
    
    return [
        'username' => $userLogin,
        'passwordClear' => $userPassword
    ];
}


class initialChecks {
    // Properties
    public $login;

    // Methods
    public function get_is_too_much_attempts($attempts) {
        if ($attempts > 3) {
            throw new Exception(
                "error" 
            );
        }
    }

    public function get_user_info($login, $enable_ad_user_auto_creation) {
        $data = DB::queryFirstRow(
            'SELECT u.*, a.value AS api_key
            FROM ' . prefixTable('users') . ' AS u
            LEFT JOIN ' . prefixTable('api') . ' AS a ON (u.id = a.user_id)
            WHERE login = %s',
            $login
        );
        
        // User doesn't exist then return error
        // Except if user creation from LDAP is enabled
        if (DB::count() === 0 && $enable_ad_user_auto_creation === false) {
            throw new Exception(
                "error" 
            );
        }
        $data['ldap_user_to_be_created'] = $enable_ad_user_auto_creation === true && DB::count() === 0 ? true : false;

        // ensure user fonction_id is set to false if not existing
        /*if (is_null($data['fonction_id']) === true) {
            $data['fonction_id'] = '';
        }*/

        // Prepare user roles (fonction_id + roles_from_ad_groups)
        // Disable this as this happend repeadetly and is not necessary when working with AD groups
        //$data['fonction_id'] = is_null($data['roles_from_ad_groups']) === true ? $data['fonction_id'] : (empty($data['roles_from_ad_groups']) === true ? $data['fonction_id'] : $data['fonction_id'] . ';' . $data['roles_from_ad_groups']);

        return $data;
    }

    public function get_teampass_in_maintenance_mode($maintenance_mode, $user_admin) {
        if ((int) $maintenance_mode === 1 && (int) $user_admin === 0) {
            throw new Exception(
                "error" 
            );
        }
    }

    public function get_mfa_code_is_set(
        $yubico,
        $ga,
        $duo,
        $admin,
        $adminMfaRequired,
        $mfa,
        $userMfaSelection,
        $userMfaEnabled
    ) {
        if (
            (empty($userMfaSelection) === true &&
            isOneVarOfArrayEqualToValue(
                [
                    (int) $yubico,
                    (int) $ga,
                    (int) $duo
                ],
                1
            ) === true)
            && (((int) $admin !== 1 && $userMfaEnabled === true) || ((int) $adminMfaRequired === 1 && (int) $admin === 1))
            && $mfa === true
        ) {
            throw new Exception(
                "error" 
            );
        }
    }

    public function get_install_folder_is_not_present($admin, $install_folder) {
        if ((int) $admin === 1 && is_dir($install_folder) === true) {
            throw new Exception(
                "error" 
            );
        }
    }
}


/**
 * Permit to get info about user before auth step
 *
 * @param array $SETTINGS
 * @param integer $sessionPwdAttempts
 * @param string $username
 * @param integer $sessionAdmin
 * @param string $sessionUrl
 * @param string $user_2fa_selection
 * @return array
 */
function identifyDoInitialChecks(
    $SETTINGS,
    int $sessionPwdAttempts,
    string $username,
    int $sessionAdmin,
    string $sessionUrl,
    string $user_2fa_selection
): array
{
    $checks = new initialChecks();
    $enable_ad_user_auto_creation = isset($SETTINGS['enable_ad_user_auto_creation']) === true && (int) $SETTINGS['enable_ad_user_auto_creation'] === 1 ? true : false;
    
    // Brute force management
    try {
        $checks->get_is_too_much_attempts($sessionPwdAttempts);
    } catch (Exception $e) {
        // Load superGlobals
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
        $superGlobal = new protect\SuperGlobal\SuperGlobal();
        $superGlobal->put('next_possible_pwd_attempts', time() + 10, 'SESSION');
        $superGlobal->put('pwd_attempts', 0, 'SESSION');

        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));

        return [
            'error' => true,
            'array' => [
                'value' => 'bruteforce_wait',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => 0,
                'error' => true,
                'message' => langHdl('error_bad_credentials_more_than_3_times'),
            ]
        ];
    }

    // Check if user exists
    try {
        $userInfo = $checks->get_user_info($username, $enable_ad_user_auto_creation);
    } catch (Exception $e) {
        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
        return [
            'error' => true,
            'array' => [
                'error' => 'user_not_exists',
                'message' => langHdl('error_bad_credentials'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
            ]
        ];
    }
    
    // Manage Maintenance mode
    try {
        $checks->get_teampass_in_maintenance_mode(
            $SETTINGS['maintenance_mode'],
            $userInfo['admin']
        );
    } catch (Exception $e) {
        return [
            'error' => true,
            'array' => [
                'value' => '',
                'user_admin' => (int) $userInfo['admin'],
                'initial_url' => '',
                'pwd_attempts' => '',
                'error' => 'maintenance_mode_enabled',
                'message' => '',
            ]
        ];
    }

    // user should use MFA?
    $userInfo['mfa_auth_requested_roles'] = mfa_auth_requested_roles(
        (string) $userInfo['fonction_id'],
        is_null($SETTINGS['mfa_for_roles']) === true ? '' : (string) $SETTINGS['mfa_for_roles']
    );

    // Check if 2FA code is requested
    try {
        $checks->get_mfa_code_is_set(
            $SETTINGS['yubico_authentication'],
            $SETTINGS['google_authentication'],
            $SETTINGS['duo'],
            $userInfo['admin'],
            $SETTINGS['admin_2fa_required'],
            $userInfo['mfa_auth_requested_roles'],
            $user_2fa_selection,
            $userInfo['mfa_enabled']
        );
    } catch (Exception $e) {
        return [
            'error' => true,
            'array' => [
                'value' => '2fa_not_set',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => '2fa_not_set',
                'message' => langHdl('select_valid_2fa_credentials'),
            ]
        ];
    }

    // If admin user then check if folder install exists
    // if yes then refuse connection
    try {
        $checks->get_install_folder_is_not_present(
            $userInfo['admin'],
            '../install'
        );
    } catch (Exception $e) {
        return [
            'error' => true,
            'array' => [
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'error' => true,
                'message' => langHdl('remove_install_folder'),
            ]
        ];
    }

    // Return some usefull information about user
    return [
        'error' => false,
        'user_mfa_mode' => $user_2fa_selection,
        'userInfo' => $userInfo,
    ];
}

function identifyDoLDAPChecks(
    $SETTINGS,
    $userInfo,
    string $username,
    string $passwordClear,
    int $sessionAdmin,
    string $sessionUrl,
    int $sessionPwdAttempts
): array
{
    // Prepare LDAP connection if set up
    if ((int) $SETTINGS['ldap_mode'] === 1
        && $username !== 'admin'
        && ((string) $userInfo['auth_type'] === 'ldap' || $userInfo['ldap_user_to_be_created'] === true)
    ) {
        $retLDAP = authenticateThroughAD(
            $username,
            $userInfo,
            $passwordClear,
            $SETTINGS
        );
        if ($retLDAP['error'] === true) {
            return [
                'error' => true,
                'array' => [
                    'value' => '',
                    'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                    'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                    'pwd_attempts' => (int) $sessionPwdAttempts,
                    'error' => true,
                    'message' => "LDAP error: ".$retLDAP['message'],
                ]
            ];
        }
        return [
            'error' => false,
            'retLDAP' => $retLDAP,
            'ldapConnection' => true,
            'userPasswordVerified' => true,
        ];
    }

    // return if no addmin
    return [
        'error' => false,
        'retLDAP' => [],
        'ldapConnection' => false,
        'userPasswordVerified' => false,
    ];
}


function identifyDoMFAChecks(
    $SETTINGS,
    $userInfo,
    $dataReceived,
    $userInitialData,
    string $username
): array
{    
    switch ($userInitialData['user_mfa_mode']) {
        case 'google':
            $ret = googleMFACheck(
                $username,
                $userInfo,
                $dataReceived,
                $SETTINGS
            );
            if ($ret['error'] !== false) {
                logEvents($SETTINGS, 'failed_auth', 'wrong_mfa_code', '', stripslashes($username), stripslashes($username));
                return [
                    'error' => true,
                    'mfaData' => $ret,
                    'mfaQRCodeInfos' => false,
                ];
            }

            return [
                'error' => false,
                'mfaData' => $ret['firstTime'],
                'mfaQRCodeInfos' => $userInitialData['user_mfa_mode'] === 'google'
                && count($ret['firstTime']) > 0 ? true : false,
            ];

        case 'yubico':
            $ret = yubicoMFACheck(
                $dataReceived,
                $userInfo,
                $SETTINGS
            );
            if ($ret['error'] !== false) {
                return [
                    'error' => true,
                    'mfaData' => $ret,
                    'mfaQRCodeInfos' => false,
                ];
            }
            break;
        
        case 'duo':
            // Prepare Duo connection if set up
            $checks = duoMFACheck(
                $username,
                $dataReceived,
                $SETTINGS
            );

            if ($checks['error'] === true) {
                return [
                    'error' => true,
                    'mfaData' => $checks,
                    'mfaQRCodeInfos' => false,
                ];
            }

            // If we are here
            // Do DUO authentication
            $ret = duoMFAPerform(
                $username,
                $dataReceived,
                $checks['pwd_attempts'],
                $checks['saved_state'],
                $checks['duo_status'],
                $SETTINGS
            );

            if ($ret['error'] !== false) {
                logEvents($SETTINGS, 'failed_auth', 'bad_duo_mfa', '', stripslashes($username), stripslashes($username));
                // Load superGlobals
                include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
                # Retrieve the previously stored state and username from the session
                $superGlobal = new protect\SuperGlobal\SuperGlobal();
                $superGlobal->forget('duo_state','SESSION');
                $superGlobal->forget('duo_data','SESSION');
                $superGlobal->forget('duo_status','SESSION');
                unset($superGlobal);
                return [
                    'error' => true,
                    'mfaData' => $ret,
                    'mfaQRCodeInfos' => false,
                ];
            } else if ($ret['duo_url_ready'] === true){
                return [
                    'error' => false,
                    'mfaData' => $ret,
                    'duo_url_ready' => true,
                    'mfaQRCodeInfos' => false,
                ];
            } else if ($ret['error'] === false) {
                return [
                    'error' => false,
                    'mfaData' => $ret,
                    'mfaQRCodeInfos' => false,
                ];
            }
            break;
        
        default:
            logEvents($SETTINGS, 'failed_auth', 'wrong_mfa_code', '', stripslashes($username), stripslashes($username));
            return [
                'error' => true,
                'mfaData' => ['message' => langHdl('wrong_mfa_code')],
                'mfaQRCodeInfos' => false,
            ];
    }

    // If something went wrong, let's catch and return an error
    logEvents($SETTINGS, 'failed_auth', 'wrong_mfa_code', '', stripslashes($username), stripslashes($username));
    return [
        'error' => true,
        'mfaData' => ['message' => langHdl('wrong_mfa_code')],
        'mfaQRCodeInfos' => false,
    ];
}
