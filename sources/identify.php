<?php

declare(strict_types=1);

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
 * @file      identify.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PasswordManager\PasswordManager;
use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;
use RobThree\Auth\TwoFactorAuth;
use TeampassClasses\LdapExtra\LdapExtra;
use TeampassClasses\LdapExtra\OpenLdapExtra;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;
use TeampassClasses\OAuth2Controller\OAuth2Controller;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);

// --------------------------------- //

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

if ($post_type === 'identify_user') {
    //--------
    // NORMAL IDENTICATION STEP
    //--------

    // Ensure Complexity levels are translated
    defineComplexity();

    // Identify the user through Teampass process
    identifyUser($post_data, $SETTINGS);

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
            [
                'agses' => isKeyExistingAndEqual('agses_authentication_enabled', 1, $SETTINGS) === true ? true : false,
                'google' => isKeyExistingAndEqual('google_authentication', 1, $SETTINGS) === true ? true : false,
                'yubico' => isKeyExistingAndEqual('yubico_authentication', 1, $SETTINGS) === true ? true : false,
                'duo' => isKeyExistingAndEqual('duo', 1, $SETTINGS) === true ? true : false,
            ],
            'encode'
        ),
        'key' => $session->get('key'),
    ]);
    return false;
} elseif ($post_type === 'initiateSSOLogin') {
    //--------
    // Do initiateSSOLogin
    //--------
    //

    // Création d'une instance du contrôleur
    $OAuth2 = new OAuth2Controller($SETTINGS);

    // Redirection vers Azure pour l'authentification
    $OAuth2->redirect();

    // Encrypt data to return
    echo json_encode([
        'key' => $session->get('key'),
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
    $antiXss = new AntiXSS();
    $session = SessionManager::getSession();
    $request = SymfonyRequest::createFromGlobals();
    $lang = new Language($session->get('user-language') ?? 'english');
    $session = SessionManager::getSession();

    // Prepare GET variables
    $sessionAdmin = $session->get('user-admin');
    $sessionPwdAttempts = $session->get('pwd_attempts');
    $sessionUrl = $session->get('user-initial_url');
    $server = [];
    $server['PHP_AUTH_USER'] =  $request->getUser();
    $server['PHP_AUTH_PW'] = $request->getPassword();
    
    // decrypt and retreive data in JSON format
    if ($session->get('key') === null) {
        $dataReceived = $sentData;
    } else {
        $dataReceived = prepareExchangedData(
            $sentData,
            'decode',
            $session->get('key')
        );
    }

    // Check if Duo auth is in progress and pass the pw and login back to the standard login process
    if(
        isKeyExistingAndEqual('duo', 1, $SETTINGS) === true
        && $dataReceived['user_2fa_selection'] === 'duo'
        && $session->get('user-duo_status') === 'IN_PROGRESS'
        && !empty($dataReceived['duo_state'])
    ){
        $key = hash('sha256', $dataReceived['duo_state']);
        $iv = substr(hash('sha256', $dataReceived['duo_state']), 0, 16);
        $duo_data_dec = openssl_decrypt(base64_decode($session->get('user-duo_data')), 'AES-256-CBC', $key, 0, $iv);
        // Clear the data from the Duo process to continue clean with the standard login process
        $session->set('user-duo_data','');
        if($duo_data_dec === false) {
            // Add failed authentication log
            addFailedAuthentication($username, getClientIpServer());

            echo prepareExchangedData(
                [
                    'error' => true,
                    'message' => $lang->get('duo_error_decrypt'),
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
                [
                    'error' => true,
                    'message' => $lang->get('ga_enter_credentials'),
                ],
                'encode'
            ),
            'key' => $session->get('key')
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
        // Add log on error unless skip_anti_bruteforce flag is set to true
        if (empty($userInitialData['skip_anti_bruteforce'])
            || !$userInitialData['skip_anti_bruteforce']) {

                error_log('test');

            // Add failed authentication log
            addFailedAuthentication($username, getClientIpServer());
        }

        echo prepareExchangedData(
            $userInitialData['array'],
            'encode'
        );
        return false;
    }

    $userInfo = $userInitialData['userInfo'] + $dataReceived;
    $return = '';

    // Check if LDAP is enabled and user is in AD
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
        // Add failed authentication log
        addFailedAuthentication($username, getClientIpServer());

        // deepcode ignore ServerLeak: File and path are secured directly inside the function decryptFile()
        echo prepareExchangedData(
            $userLdap['array'],
            'encode'
        );
        return false;
    }
    if (isset($userLdap['user_info']) === true && (int) $userLdap['user_info']['has_been_created'] === 1) {
        // Add failed authentication log
        addFailedAuthentication($username, getClientIpServer());

        echo json_encode([
            'data' => prepareExchangedData(
                [
                    'error' => true,
                    'message' => '',
                    'extra' => 'ad_user_created',
                ],
                'encode'
            ),
            'key' => $session->get('key')
        ]);
        return false;
    }

    // Should we create new oauth2 user?
    $userOauth2 = createOauth2User(
        (array) $SETTINGS,
        (array) $userInfo,
        (string) $username,
        (string) $passwordClear,
        (int) $userLdap['user_info']['has_been_created']
    );
    if ($userOauth2['error'] === true) {
        $session->set('userOauth2Info', '');

        // Add failed authentication log
        addFailedAuthentication($username, getClientIpServer());

        // deepcode ignore ServerLeak: File and path are secured directly inside the function decryptFile()        
        echo prepareExchangedData(
            [
                'error' => true,
                'message' => $lang->get($userOauth2['message']),
            ],
            'encode'
        );
        return false;
    }

    // Check user and password
    if ($userLdap['userPasswordVerified'] === false && $userOauth2['userPasswordVerified'] === false
        && checkCredentials($passwordClear, $userInfo) !== true
    ) {
        // Add failed authentication log
        addFailedAuthentication($username, getClientIpServer());

        echo prepareExchangedData(
            [
                'value' => '',
                'error' => true,
                'message' => $lang->get('error_bad_credentials'),
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
            // Add failed authentication log
            addFailedAuthentication($username, getClientIpServer());

            echo prepareExchangedData(
                [
                    'error' => true,
                    'message' => $userMfa['mfaData']['message'],
                    'mfaStatus' => $userMfa['mfaData']['mfaStatus'],
                ],
                'encode'
            );
            return false;
        } elseif ($userMfa['mfaQRCodeInfos'] === true) {
            // Add failed authentication log
            addFailedAuthentication($username, getClientIpServer());

            // Case where user has initiated Google Auth
            // Return QR code
            echo prepareExchangedData(
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
            // Add failed authentication log
            addFailedAuthentication($username, getClientIpServer());

            // Case where user has initiated Duo Auth
            // Return the DUO redirect URL
            echo prepareExchangedData(
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
        $session->set('pwd_attempts', 0);

        // Check if any unsuccessfull login tries exist
        $attemptsInfos = handleLoginAttempts(
            $userInfo['id'],
            $userInfo['login'],
            $userInfo['last_connexion'],
            $username,
            $SETTINGS,
        );

        // Avoid unlimited session.
        $max_time = isset($SETTINGS['maximum_session_expiration_time']) ? (int) $SETTINGS['maximum_session_expiration_time'] : 60;
        $session_time = max(60, min($dataReceived['duree_session'], $max_time));
        $lifetime = time() + ($session_time * 60);

        // Save old key
        $old_key = $session->get('key');

        // Good practice: reset PHPSESSID and key after successful authentication
        $session->migrate();
        $session->set('key', generateQuickPassword(30, false));

        // Save account in SESSION
        $session->set('user-login', stripslashes($username));
        $session->set('user-name', empty($userInfo['name']) === false ? stripslashes($userInfo['name']) : '');
        $session->set('user-lastname', empty($userInfo['lastname']) === false ? stripslashes($userInfo['lastname']) : '');
        $session->set('user-id', (int) $userInfo['id']);
        $session->set('user-admin', (int) $userInfo['admin']);
        $session->set('user-manager', (int) $userInfo['gestionnaire']);
        $session->set('user-can_manage_all_users', $userInfo['can_manage_all_users']);
        $session->set('user-read_only', $userInfo['read_only']);
        $session->set('user-last_pw_change', $userInfo['last_pw_change']);
        $session->set('user-last_pw', $userInfo['last_pw']);
        $session->set('user-force_relog', $userInfo['force-relog']);
        $session->set('user-can_create_root_folder', $userInfo['can_create_root_folder']);
        $session->set('user-email', $userInfo['email']);
        //$session->set('user-ga', $userInfo['ga']);
        $session->set('user-avatar', $userInfo['avatar']);
        $session->set('user-avatar_thumb', $userInfo['avatar_thumb']);
        $session->set('user-upgrade_needed', $userInfo['upgrade_needed']);
        $session->set('user-is_ready_for_usage', $userInfo['is_ready_for_usage']);
        $session->set('user-personal_folder_enabled', $userInfo['personal_folder']);
        $session->set(
            'user-tree_load_strategy',
            (isset($userInfo['treeloadstrategy']) === false || empty($userInfo['treeloadstrategy']) === true) ? 'full' : $userInfo['treeloadstrategy']
        );
        $session->set(
            'user-split_view_mode',
            (isset($userInfo['split_view_mode']) === false || empty($userInfo['split_view_mode']) === true) ? 0 : $userInfo['split_view_mode']
        );
        $session->set('user-language', $userInfo['user_language']);
        $session->set('user-timezone', $userInfo['usertimezone']);
        $session->set('user-keys_recovery_time', $userInfo['keys_recovery_time']);
        
        // manage session expiration
        $session->set('user-session_duration', (int) $lifetime);

        // User signature keys
        $returnKeys = prepareUserEncryptionKeys($userInfo, $passwordClear);  
        $session->set('user-private_key', $returnKeys['private_key_clear']);
        $session->set('user-public_key', $returnKeys['public_key']);

        // Automatically detect LDAP password changes.
        if ($userInfo['auth_type'] === 'ldap' && $returnKeys['private_key_clear'] === '') {
            // Add special "recrypt-private-key" in database profile.
            DB::update(
                prefixTable('users'),
                array(
                    'special' => 'recrypt-private-key',
                ),
                'id = %i',
                $userInfo['id']
            );

            // Store new value in userInfos.
            $userInfo['special'] = 'recrypt-private-key';
        }

        // API key
        $session->set(
            'user-api_key',
            empty($userInfo['api_key']) === false ? base64_decode(decryptUserObjectKey($userInfo['api_key'], $returnKeys['private_key_clear'])) : '',
        );
        
        $session->set('user-special', $userInfo['special']);
        $session->set('user-auth_type', $userInfo['auth_type']);

        // check feedback regarding user password validity
        $return = checkUserPasswordValidity(
            $userInfo,
            (int) $session->get('user-num_days_before_exp'),
            (int) $session->get('user-last_pw_change'),
            $SETTINGS
        );
        $session->set('user-validite_pw', $return['validite_pw']);
        $session->set('user-last_pw_change', $return['last_pw_change']);
        $session->set('user-num_days_before_exp', $return['numDaysBeforePwExpiration']);
        $session->set('user-force_relog', $return['user_force_relog']);
        
        $session->set('user-last_connection', empty($userInfo['last_connexion']) === false ? (int) $userInfo['last_connexion'] : (int) time());
        $session->set('user-latest_items', empty($userInfo['latest_items']) === false ? explode(';', $userInfo['latest_items']) : []);
        $session->set('user-favorites', empty($userInfo['favourites']) === false ? explode(';', $userInfo['favourites']) : []);
        $session->set('user-accessible_folders', empty($userInfo['groupes_visibles']) === false ? explode(';', $userInfo['groupes_visibles']) : []);
        $session->set('user-no_access_folders', empty($userInfo['groupes_interdits']) === false ? explode(';', $userInfo['groupes_interdits']) : []);
        
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
                $session->get('user-id')
            );
        }
        // Append with roles from AD groups
        if (is_null($userInfo['roles_from_ad_groups']) === false) {
            $userInfo['fonction_id'] = empty($userInfo['fonction_id'])  === true ? $userInfo['roles_from_ad_groups'] : $userInfo['fonction_id']. ';' . $userInfo['roles_from_ad_groups'];
        }
        // store
        $session->set('user-roles', $userInfo['fonction_id']);
        $session->set('user-roles_array', array_unique(array_filter(explode(';', $userInfo['fonction_id']))));
        
        // build array of roles
        $session->set('user-pw_complexity', 0);
        $session->set('system-array_roles', []);
        if (count($session->get('user-roles_array')) > 0) {
            $rolesList = DB::query(
                'SELECT id, title, complexity
                FROM ' . prefixTable('roles_title') . '
                WHERE id IN %li',
                $session->get('user-roles_array')
            );
            $excludeUser = isset($SETTINGS['exclude_user']) ? str_contains($session->get('user-login'), $SETTINGS['exclude_user']) : false;
            $adjustPermissions = ($session->get('user-id') >= 1000000 && !$excludeUser && (isset($SETTINGS['admin_needle']) || isset($SETTINGS['manager_needle']) || isset($SETTINGS['tp_manager_needle']) || isset($SETTINGS['read_only_needle'])));
            if ($adjustPermissions) {
                $userInfo['admin'] = $userInfo['gestionnaire'] = $userInfo['can_manage_all_users'] = $userInfo['read_only'] = 0;
            }
            foreach ($rolesList as $role) {
                SessionManager::addRemoveFromSessionAssociativeArray(
                    'system-array_roles',
                    [
                        'id' => $role['id'],
                        'title' => $role['title'],
                    ],
                    'add'
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
                if ($session->get('user-pw_complexity') < (int) $role['complexity']) {
                    $session->set('user-pw_complexity', (int) $role['complexity']);
                }
            }
            if ($adjustPermissions) {
                $session->set('user-admin', (int) $userInfo['admin']);
                $session->set('user-manager', (int) $userInfo['gestionnaire']);
                $session->set('user-can_manage_all_users',(int)  $userInfo['can_manage_all_users']);
                $session->set('user-read_only', (int) $userInfo['read_only']);
                DB::update(
                    prefixTable('users'),
                    [
                        'admin' => $userInfo['admin'],
                        'gestionnaire' => $userInfo['gestionnaire'],
                        'can_manage_all_users' => $userInfo['can_manage_all_users'],
                        'read_only' => $userInfo['read_only'],
                    ],
                    'id = %i',
                    $session->get('user-id')
                );
            }
        }

        // Set some settings
        $SETTINGS['update_needed'] = '';

        // Update table
        DB::update(
            prefixTable('users'),
            array_merge(
                [
                    'key_tempo' => $session->get('key'),
                    'last_connexion' => time(),
                    'timestamp' => time(),
                    'disabled' => 0,
                    'session_end' => $session->get('user-session_duration'),
                    'user_ip' => $dataReceived['client'],
                ],
                $returnKeys['update_keys_in_db']
            ),
            'id=%i',
            $userInfo['id']
        );
        
        // Get user's rights
        if ($userLdap['user_initial_creation_through_external_ad'] === true || $userOauth2['retExternalAD']['has_been_created'] === 1) {
            // is new LDAP user. Show only his personal folder
            if ($SETTINGS['enable_pf_feature'] === '1') {
                $session->set('user-personal_visible_folders', [$userInfo['id']]);
                $session->set('user-personal_folders', [$userInfo['id']]);
            } else {
                $session->set('user-personal_visible_folders', []);
                $session->set('user-personal_folders', []);
            }
            $session->set('user-all_non_personal_folders', []);
            $session->set('user-roles_array', []);
            $session->set('user-read_only_folders', []);
            $session->set('user-list_folders_limited', []);
            $session->set('system-list_folders_editable_by_role', []);
            $session->set('system-list_restricted_folders_for_items', []);
            $session->set('user-nb_folders', 1);
            $session->set('user-nb_roles', 1);
        } else {
            identifyUserRights(
                $userInfo['groupes_visibles'],
                $session->get('user-no_access_folders'),
                $userInfo['admin'],
                $userInfo['fonction_id'],
                $SETTINGS
            );
        }
        // Get some more elements
        $session->set('system-screen_height', $dataReceived['screenHeight']);

        // Get last seen items
        $session->set('user-latest_items_tab', []);
        $session->set('user-nb_roles', 0);
        foreach ($session->get('user-latest_items') as $item) {
            if (! empty($item)) {
                $dataLastItems = DB::queryFirstRow(
                    'SELECT id,label,id_tree
                    FROM ' . prefixTable('items') . '
                    WHERE id=%i',
                    $item
                );
                SessionManager::addRemoveFromSessionAssociativeArray(
                    'user-latest_items_tab',
                    [
                        'id' => $item,
                        'label' => $dataLastItems['label'],
                        'url' => 'index.php?page=items&amp;group=' . $dataLastItems['id_tree'] . '&amp;id=' . $item,
                    ],
                    'add'
                );
            }
        }

        // Get cahce tree info
        $cacheTreeData = DB::queryFirstRow(
            'SELECT visible_folders
            FROM ' . prefixTable('cache_tree') . '
            WHERE user_id=%i',
            (int) $session->get('user-id')
        );
        if (DB::count() > 0 && empty($cacheTreeData['visible_folders']) === true) {
            $session->set('user-cache_tree', '');
            // Prepare new task
            DB::insert(
                prefixTable('background_tasks'),
                array(
                    'created_at' => time(),
                    'process_type' => 'user_build_cache_tree',
                    'arguments' => json_encode([
                        'user_id' => (int) $session->get('user-id'),
                    ], JSON_HEX_QUOT | JSON_HEX_TAG),
                    'updated_at' => '',
                    'finished_at' => '',
                    'output' => '',
                )
            );
        } else {
            $session->set('user-cache_tree', $cacheTreeData['visible_folders']);
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
                    $lang->get('email_subject_on_user_login'),
                    str_replace(
                        [
                            '#tp_user#',
                            '#tp_date#',
                            '#tp_time#',
                        ],
                        [
                            ' ' . $session->get('user-login') . ' (IP: ' . getClientIpServer() . ')',
                            date($SETTINGS['date_format'], (int) $session->get('user-last_connection')),
                            date($SETTINGS['time_format'], (int) $session->get('user-last_connection')),
                        ],
                        $lang->get('email_body_on_user_login')
                    ),
                    $val['email'],
                    $lang->get('administrator')
                );
            }
        }
        
        // Ensure Complexity levels are translated
        defineComplexity();
        echo prepareExchangedData(
            [
                'value' => $return,
                'user_id' => $session->get('user-id') !== null ? $session->get('user-id') : '',
                'user_admin' => null !== $session->get('user-admin') ? $session->get('user-admin') : 0,
                'initial_url' => $antiXss->xss_clean($sessionUrl),
                'pwd_attempts' => 0,
                'error' => false,
                'message' => $session->has('user-upgrade_needed') && (int) $session->get('user-upgrade_needed') && (int) $session->get('user-upgrade_needed') === 1 ? 'ask_for_otc' : '',
                'first_connection' => $session->get('user-validite_pw') === 0 ? true : false,
                'password_complexity' => TP_PW_COMPLEXITY[$session->get('user-pw_complexity')][1],
                'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                'private_key_conform' => $session->get('user-id') !== null
                    && empty($session->get('user-private_key')) === false
                    && $session->get('user-private_key') !== 'none' ? true : false,
                'session_key' => $session->get('key'),
                'can_create_root_folder' => null !== $session->get('user-can_create_root_folder') ? (int) $session->get('user-can_create_root_folder') : '',
                'upgrade_needed' => isset($userInfo['upgrade_needed']) === true ? (int) $userInfo['upgrade_needed'] : 0,
                'special' => isset($userInfo['special']) === true ? (int) $userInfo['special'] : 0,
                'split_view_mode' => isset($userInfo['split_view_mode']) === true ? (int) $userInfo['split_view_mode'] : 0,
                'validite_pw' => $session->get('user-validite_pw') !== null ? $session->get('user-validite_pw') : '',
                'num_days_before_exp' => $session->get('user-num_days_before_exp') !== null ? (int) $session->get('user-num_days_before_exp') : '',
            ],
            'encode',
            $old_key
        );
    
        return true;

    } elseif ((int) $userInfo['disabled'] === 1) {
        // User and password is okay but account is locked
        echo prepareExchangedData(
            [
                'value' => $return,
                'user_id' => $session->get('user-id') !== null ? (int) $session->get('user-id') : '',
                'user_admin' => null !== $session->get('user-admin') ? $session->get('user-admin') : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => 0,
                'error' => 'user_is_locked',
                'message' => $lang->get('account_is_locked'),
                'first_connection' => $session->get('user-validite_pw') === 0 ? true : false,
                'password_complexity' => TP_PW_COMPLEXITY[$session->get('user-pw_complexity')][1],
                'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
                'private_key_conform' => $session->has('user-private_key') && null !== $session->get('user-private_key')
                    && empty($session->get('user-private_key')) === false
                    && $session->get('user-private_key') !== 'none' ? true : false,
                'session_key' => $session->get('key'),
                'can_create_root_folder' => null !== $session->get('user-can_create_root_folder') ? (int) $session->get('user-can_create_root_folder') : '',
            ],
            'encode'
        );
        return false;
    }

    echo prepareExchangedData(
        [
            'value' => $return,
            'user_id' => $session->get('user-id') !== null ? (int) $session->get('user-id') : '',
            'user_admin' => null !== $session->get('user-admin') ? $session->get('user-admin') : 0,
            'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'error' => true,
            'message' => $lang->get('error_not_allowed_to_authenticate'),
            'first_connection' => $session->get('user-validite_pw') === 0 ? true : false,
            'password_complexity' => TP_PW_COMPLEXITY[$session->get('user-pw_complexity')][1],
            'password_change_expected' => $userInfo['special'] === 'password_change_expected' ? true : false,
            'private_key_conform' => $session->get('user-id') !== null
                    && empty($session->get('user-private_key')) === false
                    && $session->get('user-private_key') !== 'none' ? true : false,
            'session_key' => $session->get('key'),
            'can_create_root_folder' => null !== $session->get('user-can_create_root_folder') ? (int) $session->get('user-can_create_root_folder') : '',
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
 * @param array $userInfo User account information
 * @param int $numDaysBeforePwExpiration Number of days before password expiration
 * @param int $lastPwChange Last password change
 * @param array $SETTINGS Teampass settings
 *
 * @return array
 */
function checkUserPasswordValidity(array $userInfo, int $numDaysBeforePwExpiration, int $lastPwChange, array $SETTINGS)
{
    if (isKeyExistingAndEqual('ldap_mode', 1, $SETTINGS) === true && $userInfo['auth_type'] !== 'local') {
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
        } elseif ((int) $SETTINGS['pw_life_duration'] > 0) {
            $numDaysBeforePwExpiration = (int) $SETTINGS['pw_life_duration'] - round(
                (mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')) - $userInfo['last_pw_change']) / (24 * 60 * 60)
            );
            return [
                'validite_pw' => $numDaysBeforePwExpiration <= 0 ? false : true,
                'last_pw_change' => $userInfo['last_pw_change'],
                'user_force_relog' => 'infinite',
                'numDaysBeforePwExpiration' => (int) $numDaysBeforePwExpiration,
            ];
        } else {
            return [
                'validite_pw' => false,
                'last_pw_change' => '',
                'user_force_relog' => '',
                'numDaysBeforePwExpiration' => '',
            ];
        }
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
 * Authenticate a user through AD/LDAP.
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
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    try {
        // Get LDAP connection and handler
        $ldapHandler = initializeLdapConnection($SETTINGS);
        
        // Authenticate user
        $authResult = authenticateUser($username, $passwordClear, $ldapHandler, $SETTINGS, $lang);
        if ($authResult['error']) {
            return $authResult;
        }
        
        $userADInfos = $authResult['user_info'];
        
        // Verify account expiration
        if (isAccountExpired($userADInfos)) {
            return [
                'error' => true,
                'message' => $lang->get('error_ad_user_expired'),
            ];
        }
        
        // Handle user creation if needed
        if ($userInfo['ldap_user_to_be_created']) {
            $userInfo = handleNewUser($username, $passwordClear, $userADInfos, $userInfo, $SETTINGS, $lang);
        }
        
        // Get and handle user groups
        $userGroupsData = getUserGroups($userADInfos, $ldapHandler, $SETTINGS);
        handleUserADGroups($username, $userInfo, $userGroupsData['userGroups'], $SETTINGS);
        
        // Finalize authentication
        finalizeAuthentication($userInfo, $passwordClear, $SETTINGS);
        
        return [
            'error' => false,
            'message' => '',
            'user_info' => $userInfo,
        ];
        
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => "Error: " . $e->getMessage(),
        ];
    }
}

/**
 * Initialize LDAP connection based on type
 * 
 * @param array $SETTINGS Teampass settings
 * @return array Contains connection and type-specific handler
 * @throws Exception
 */
function initializeLdapConnection(array $SETTINGS): array
{
    $ldapExtra = new LdapExtra($SETTINGS);
    $ldapConnection = $ldapExtra->establishLdapConnection();
    
    switch ($SETTINGS['ldap_type']) {
        case 'ActiveDirectory':
            return [
                'connection' => $ldapConnection,
                'handler' => new ActiveDirectoryExtra(),
                'type' => 'ActiveDirectory'
            ];
        case 'OpenLDAP':
            return [
                'connection' => $ldapConnection,
                'handler' => new OpenLdapExtra(),
                'type' => 'OpenLDAP'
            ];
        default:
            throw new Exception("Unsupported LDAP type: " . $SETTINGS['ldap_type']);
    }
}

/**
 * Authenticate user against LDAP
 * 
 * @param string $username Username
 * @param string $passwordClear Password
 * @param array $ldapHandler LDAP connection and handler
 * @param array $SETTINGS Teampass settings
 * @param Language $lang Language instance
 * @return array Authentication result
 */
function authenticateUser(string $username, string $passwordClear, array $ldapHandler, array $SETTINGS, Language $lang): array
{
    try {
        $userAttribute = $SETTINGS['ldap_user_attribute'] ?? 'samaccountname';
        $userADInfos = $ldapHandler['connection']->query()
            ->where($userAttribute, '=', $username)
            ->firstOrFail();
        
        // Verify user status for ActiveDirectory
        if ($ldapHandler['type'] === 'ActiveDirectory' && !$ldapHandler['handler']->userIsEnabled((string) $userADInfos['dn'], $ldapHandler['connection'])) {
            return [
                'error' => true,
                'message' => "Error: User is not enabled"
            ];
        }
        
        // Attempt authentication
        $authIdentifier = $ldapHandler['type'] === 'ActiveDirectory' 
            ? $userADInfos['userprincipalname'][0] 
            : $userADInfos['dn'];
            
        if (!$ldapHandler['connection']->auth()->attempt($authIdentifier, $passwordClear)) {
            return [
                'error' => true,
                'message' => "Error: User is not authenticated"
            ];
        }
        
        return [
            'error' => false,
            'user_info' => $userADInfos
        ];
        
    } catch (\LdapRecord\Query\ObjectNotFoundException $e) {
        return [
            'error' => true,
            'message' => $lang->get('error_bad_credentials')
        ];
    }
}

/**
 * Check if user account is expired
 * 
 * @param array $userADInfos User AD information
 * @return bool
 */
function isAccountExpired(array $userADInfos): bool
{
    return (isset($userADInfos['shadowexpire'][0]) && (int) $userADInfos['shadowexpire'][0] === 1)
        || (isset($userADInfos['accountexpires'][0]) 
            && (int) $userADInfos['accountexpires'][0] < time() 
            && (int) $userADInfos['accountexpires'][0] !== 0);
}

/**
 * Handle creation of new user
 * 
 * @param string $username Username
 * @param string $passwordClear Password
 * @param array $userADInfos User AD information
 * @param array $userInfo User information
 * @param array $SETTINGS Teampass settings
 * @param Language $lang Language instance
 * @return array User information
 */
function handleNewUser(string $username, string $passwordClear, array $userADInfos, array $userInfo, array $SETTINGS, Language $lang): array
{
    $userInfo = externalAdCreateUser(
        $username,
        $passwordClear,
        $userADInfos['mail'][0],
        $userADInfos['givenname'][0],
        $userADInfos['sn'][0],
        'ldap',
        [],
        $SETTINGS
    );

    handleUserKeys(
        (int) $userInfo['id'],
        $passwordClear,
        (int) ($SETTINGS['maximum_number_of_items_to_treat'] ?? NUMBER_ITEMS_IN_BATCH),
        uniqidReal(20),
        true,
        true,
        true,
        false,
        $lang->get('email_body_user_config_2')
    );

    $userInfo['has_been_created'] = 1;
    return $userInfo;
}

/**
 * Get user groups based on LDAP type
 * 
 * @param array $userADInfos User AD information
 * @param array $ldapHandler LDAP connection and handler
 * @param array $SETTINGS Teampass settings
 * @return array User groups
 */
function getUserGroups(array $userADInfos, array $ldapHandler, array $SETTINGS): array
{
    $dnAttribute = $SETTINGS['ldap_user_dn_attribute'] ?? 'distinguishedname';
    
    if ($ldapHandler['type'] === 'ActiveDirectory') {
        return $ldapHandler['handler']->getUserADGroups(
            $userADInfos[$dnAttribute][0],
            $ldapHandler['connection'],
            $SETTINGS
        );
    }
    
    if ($ldapHandler['type'] === 'OpenLDAP') {
        return $ldapHandler['handler']->getUserADGroups(
            $userADInfos['dn'],
            $ldapHandler['connection'],
            $SETTINGS
        );
    }
    
    throw new Exception("Unsupported LDAP type: " . $ldapHandler['type']);
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
                WHERE lgr.ldap_group_id = %s',
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
    $passwordManager = new PasswordManager();
    
    // Migrate password if needed
    $hashedPassword = $passwordManager->migratePassword(
        $userInfo['pw'],
        $passwordClear,
        (int) $userInfo['id']
    );
    
    if (empty($userInfo['pw']) === true || $userInfo['special'] === 'user_added_from_ldap') {
        // 2 cases are managed here:
        // Case where user has never been connected then erase current pwd with the ldap's one
        // Case where user has been added from LDAP and never being connected to TP
        DB::update(
            prefixTable('users'),
            [
                'pw' => $passwordManager->hashPassword($passwordClear),
            ],
            'id = %i',
            $userInfo['id']
        );
    } elseif ($passwordManager->verifyPassword($hashedPassword, $passwordClear) === false) {
        // Case where user is auth by LDAP but his password in Teampass is not synchronized
        // For example when user has changed his password in AD.
        // So we need to update it in Teampass and ask for private key re-encryption
        DB::update(
            prefixTable('users'),
            [
                'pw' => $passwordManager->hashPassword($passwordClear),
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
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    $sessionAdmin = $session->get('user-admin');
    $sessionUrl = $session->get('user-initial_url');
    $sessionPwdAttempts = $session->get('pwd_attempts');
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
                'message' => 'no_user_yubico_credentials',
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
            'message' => $lang->get('yubico_bad_code'),
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
function externalAdCreateUser(
    string $login,
    string $passwordClear,
    string $userEmail,
    string $userName,
    string $userLastname,
    string $authType,
    array $userGroups,
    array $SETTINGS
): array
{
    // Generate user keys pair
    $userKeys = generateUserKeys($passwordClear);

    // Create password hash
    $passwordManager = new PasswordManager();
    $hashedPassword = $passwordManager->hashPassword($passwordClear);
    
    // If any groups provided, add user to them
    if (count($userGroups) > 0) {
        $groupIds = [];
        foreach ($userGroups as $group) {
            // Check if exists in DB
            $groupData = DB::queryFirstRow(
                'SELECT id
                FROM ' . prefixTable('roles_title') . '
                WHERE title = %s',
                $group["displayName"]
            );

            if (DB::count() > 0) {
                array_push($groupIds, $groupData['id']);
            }
        }
        $userGroups = implode(';', $groupIds);
    } else {
        $userGroups = '';
    }

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
            'fonction_id' => $userGroups,
            'last_pw_change' => (int) time(),
            'user_language' => (string) $SETTINGS['default_language'],
            'encrypted_psk' => '',
            'isAdministratedByRole' => isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && empty($SETTINGS['ldap_new_user_is_administrated_by']) === false ? $SETTINGS['ldap_new_user_is_administrated_by'] : 0,
            'public_key' => $userKeys['public_key'],
            'private_key' => $userKeys['private_key'],
            'special' => 'none',
            'auth_type' => $authType,
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
            'allowed_to_read' => 1,
            'allowed_folders' => '',
            'enabled' => 0,
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
        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        $tree->rebuild();
    }


    return [
        'error' => false,
        'message' => '',
        'proceedIdentification' => true,
        'user_initial_creation_through_external_ad' => true,
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
    $session = SessionManager::getSession();    
    $lang = new Language($session->get('user-language') ?? 'english');

    if (
        isset($dataReceived['GACode']) === true
        && empty($dataReceived['GACode']) === false
    ) {
        $sessionAdmin = $session->get('user-admin');
        $sessionUrl = $session->get('user-initial_url');
        $sessionPwdAttempts = $session->get('pwd_attempts');
        // create new instance
        $tfa = new TwoFactorAuth($SETTINGS['ga_website_name']);
        // Init
        $firstTime = [];
        // now check if it is the 1st time the user is using 2FA
        if ($userInfo['ga_temporary_code'] !== 'none' && $userInfo['ga_temporary_code'] !== 'done') {
            if ($userInfo['ga_temporary_code'] !== $dataReceived['GACode']) {
                return [
                    'error' => true,
                    'message' => $lang->get('ga_bad_code'),
                    'proceedIdentification' => false,
                    'ga_bad_code' => true,
                    'firstTime' => $firstTime,
                ];
            }

            // If first time with MFA code
            $proceedIdentification = false;
            
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
                'message' => $lang->get('ga_flash_qr_and_login'),
                'mfaStatus' => 'ga_temporary_code_correct',
            ];
        } else {
            // verify the user GA code
            if ($tfa->verifyCode($userInfo['ga'], $dataReceived['GACode'])) {
                $proceedIdentification = true;
            } else {
                return [
                    'error' => true,
                    'message' => $lang->get('ga_bad_code'),
                    'proceedIdentification' => false,
                    'ga_bad_code' => true,
                    'firstTime' => $firstTime,
                ];
            }
        }
    } else {
        return [
            'error' => true,
            'message' => $lang->get('ga_bad_code'),
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
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    $sessionPwdAttempts = $session->get('pwd_attempts');
    $saved_state = null !== $session->get('user-duo_state') ? $session->get('user-duo_state') : '';
    $duo_status = null !== $session->get('user-duo_status') ? $session->get('user-duo_status') : '';

    // Ensure state and login are set
    if (
        (empty($saved_state) || empty($dataReceived['login']) || !isset($dataReceived['duo_state']) || empty($dataReceived['duo_state']))
        && $duo_status === 'IN_PROGRESS'
        && $dataReceived['duo_status'] !== 'start_duo_auth'
    ) {
        return [
            'error' => true,
            'message' => $lang->get('duo_no_data'),
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'proceedIdentification' => false,
        ];
    }

    // Ensure state matches from initial request
    if ($duo_status === 'IN_PROGRESS' && $dataReceived['duo_state'] !== $saved_state) {
        $session->set('user-duo_state', '');
        $session->set('user-duo_status', '');

        // We did not received a proper Duo state
        return [
            'error' => true,
            'message' => $lang->get('duo_error_state'),
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
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    try {
        $duo_client = new Client(
            $SETTINGS['duo_ikey'],
            $SETTINGS['duo_skey'],
            $SETTINGS['duo_host'],
            $SETTINGS['cpassman_url'].'/'.DUO_CALLBACK
        );
    } catch (DuoException $e) {
        return [
            'error' => true,
            'message' => $lang->get('duo_config_error'),
            'debug_message' => $e->getMessage(),
            'pwd_attempts' => (int) $sessionPwdAttempts,
            'proceedIdentification' => false,
        ];
    }
        
    try {
        $duo_error = $lang->get('duo_error_secure');
        $duo_failmode = "none";
        $duo_client->healthCheck();
    } catch (DuoException $e) {
        //Not implemented Duo Failmode in case the Duo services are not available
        /*if ($SETTINGS['duo_failmode'] == "safe") {
            # If we're failing open, errors in 2FA still allow for success
            $duo_error = $lang->get('duo_error_failopen');
            $duo_failmode = "safe";
        } else {
            # Duo has failed and is unavailable, redirect user to the login page
            $duo_error = $lang->get('duo_error_secure');
            $duo_failmode = "secure";
        }*/
        return [
            'error' => true,
            'message' => $duo_error . $lang->get('duo_error_check_config'),
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
        } catch (DuoException $e) {
            return [
                'error' => true,
                'message' => $duo_error . $lang->get('duo_error_url'),
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
            $session->set('user-duo_state', $duo_state);
            $session->set('user-duo_data', base64_encode($duo_data_enc));
            $session->set('user-duo_status', 'IN_PROGRESS');
            $session->set('user-login', $username);
            
            // If we got here we can reset the password attempts
            $session->set('pwd_attempts', 0);
            
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
                'message' => $duo_error . $lang->get('duo_error_url'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'proceedIdentification' => false,
            ];
        }
    } elseif ($duo_status === 'IN_PROGRESS' && $dataReceived['duo_code'] !== '') {
        try {
            // Check if the Duo code received is valid
            $decoded_token = $duo_client->exchangeAuthorizationCodeFor2FAResult($dataReceived['duo_code'], $username);
        } catch (DuoException $e) {
            return [
                'error' => true,
                'message' => $lang->get('duo_error_decoding'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'debug_message' => $e->getMessage(),
                'proceedIdentification' => false,
            ];
        }
        // return the response (which should be the user name)
        if ($decoded_token['preferred_username'] === $username) {
            $session->set('user-duo_status', 'COMPLET');
            $session->set('user-duo_state','');
            $session->set('user-duo_data','');
            $session->set('user-login', $username);

            return [
                'error' => false,
                'message' => '',
                'proceedIdentification' => true,
                'authenticated_username' => $decoded_token['preferred_username']
            ];
        } else {
            // Something wrong, username from the original Duo request is different than the one received now
            $session->set('user-duo_status','');
            $session->set('user-duo_state','');
            $session->set('user-duo_data','');

            return [
                'error' => true,
                'message' => $lang->get('duo_login_mismatch'),
                'pwd_attempts' => (int) $sessionPwdAttempts,
                'proceedIdentification' => false,
            ];
        }
    }
    // If we are here something wrong
    $session->set('user-duo_status','');
    $session->set('user-duo_state','');
    $session->set('user-duo_data','');
    return [
        'error' => true,
        'message' => $lang->get('duo_login_mismatch'),
        'pwd_attempts' => (int) $sessionPwdAttempts,
        'proceedIdentification' => false,
    ];
}

/**
 * Undocumented function.
 *
 * @param string                $passwordClear Password in clear
 * @param array|string          $userInfo      Array of user data
 *
 * @return bool
 */
function checkCredentials($passwordClear, $userInfo): bool
{
    $passwordManager = new PasswordManager();
    // Migrate password if needed
    $passwordManager->migratePassword(
        $userInfo['pw'],
        $passwordClear,
        (int) $userInfo['id']
    );

    if ($passwordManager->verifyPassword($userInfo['pw'], $passwordClear) === false) {
        // password is not correct
        return false;
    }

    return true;
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

    /**
     * Check if the user or his IP address is blocked due to a high number of
     * failed attempts.
     * 
     * @param string $username - The login tried to login.
     * @param string $ip - The remote address of the user.
     */
    public function isTooManyPasswordAttempts($username, $ip) {

        // Check for existing lock
        $unlock_at = DB::queryFirstField(
            'SELECT MAX(unlock_at)
             FROM ' . prefixTable('auth_failures') . '
             WHERE unlock_at > %s
             AND ((source = %s AND value = %s) OR (source = %s AND value = %s))',
            date('Y-m-d H:i:s', time()),
            'login',
            $username,
            'remote_ip',
            $ip
        );

        // Account or remote address locked
        if ($unlock_at) {
            throw new Exception((string) $unlock_at);
        }
    }

    public function getUserInfo($login, $enable_ad_user_auto_creation, $oauth2_enabled) {
        $session = SessionManager::getSession();

        // Get user info from DB
        $data = DB::queryFirstRow(
            'SELECT u.*, a.value AS api_key
            FROM ' . prefixTable('users') . ' AS u
            LEFT JOIN ' . prefixTable('api') . ' AS a ON (u.id = a.user_id)
            WHERE login = %s AND deleted_at IS NULL',
            $login
        );
        
        // User doesn't exist then return error
        // Except if user creation from LDAP is enabled
        if (DB::count() === 0 && ($enable_ad_user_auto_creation === false || $oauth2_enabled === false)) {
            throw new Exception(
                "error" 
            );
        }
        // We cannot create a user with LDAP if the OAuth2 login is ongoing
        $oauth2LoginOngoing = isset($session->get('userOauth2Info')['oauth2LoginOngoing']) ? $session->get('userOauth2Info')['oauth2LoginOngoing'] : false;
        $data['oauth2_login_ongoing'] = $oauth2LoginOngoing;
        $data['ldap_user_to_be_created'] = $enable_ad_user_auto_creation === true && DB::count() === 0 && $oauth2LoginOngoing !== true ? true : false;
        $data['oauth2_user_to_be_created'] = $oauth2_enabled === true && DB::count() === 0 && $oauth2LoginOngoing === true ? true : false;

        return $data;
    }

    public function isMaintenanceModeEnabled($maintenance_mode, $user_admin) {
        if ((int) $maintenance_mode === 1 && (int) $user_admin === 0) {
            throw new Exception(
                "error" 
            );
        }
    }

    public function is2faCodeRequired(
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

    public function isInstallFolderPresent($admin, $install_folder) {
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
 * @param string $user2faSelection
 * @param boolean $oauth2Token
 * @return array
 */
function identifyDoInitialChecks(
    $SETTINGS,
    int $sessionPwdAttempts,
    string $username,
    int $sessionAdmin,
    string $sessionUrl,
    string $user2faSelection
): array
{
    $session = SessionManager::getSession();
    $checks = new initialChecks();
    $enableAdUserAutoCreation = $SETTINGS['enable_ad_user_auto_creation'] ?? false;
    $oauth2Enabled = $SETTINGS['oauth2_enabled'] ?? false;
    $lang = new Language($session->get('user-language') ?? 'english');

    // Manage Maintenance mode
    try {
        $checks->isMaintenanceModeEnabled(
            $SETTINGS['maintenance_mode'],
            $userInfo['admin']
        );
    } catch (Exception $e) {
        return [
            'error' => true,
            'skip_anti_bruteforce' => true,
            'array' => [
                'value' => '',
                'error' => 'maintenance_mode_enabled',
                'message' => '',
            ]
        ];
    }

    // Brute force management
    try {
        $checks->isTooManyPasswordAttempts($username, getClientIpServer());
    } catch (Exception $e) {
        $session->set('userOauth2Info', '');
        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
        return [
            'error' => true,
            'skip_anti_bruteforce' => true,
            'array' => [
                'value' => 'bruteforce_wait',
                'error' => true,
                'message' => $lang->get('bruteforce_wait') . (string) $e->getMessage(),
            ]
        ];
    }

    // Check if user exists
    try {
        $userInfo = $checks->getUserInfo($username, $enableAdUserAutoCreation, $oauth2Enabled);
    } catch (Exception $e) {
        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
        return [
            'error' => true,
            'array' => [
                'error' => true,
                'message' => $lang->get('error_bad_credentials'),
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
        $checks->is2faCodeRequired(
            $SETTINGS['yubico_authentication'],
            $SETTINGS['google_authentication'],
            $SETTINGS['duo'],
            $userInfo['admin'],
            $SETTINGS['admin_2fa_required'],
            $userInfo['mfa_auth_requested_roles'],
            $user2faSelection,
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
                'message' => $lang->get('select_valid_2fa_credentials'),
            ]
        ];
    }
    // If admin user then check if folder install exists
    // if yes then refuse connection
    try {
        $checks->isInstallFolderPresent(
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
                'message' => $lang->get('remove_install_folder'),
            ]
        ];
    }

    // Return some usefull information about user
    return [
        'error' => false,
        'user_mfa_mode' => $user2faSelection,
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
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

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
                    'message' => $lang->get('error_bad_credentials'),
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


function shouldUserAuthWithOauth2(
    array $SETTINGS,
    array $userInfo,
    string $username
): array
{
    // Security issue without this return if an user auth_type == oauth2 and
    // oauth2 disabled : we can login as a valid user by using hashUserId(username)
    // as password in the login the form.
    if ((int) $SETTINGS['oauth2_enabled'] !== 1 && (bool) $userInfo['oauth2_login_ongoing'] === true) {
        return [
            'error' => true,
            'message' => 'user_not_allowed_to_auth_to_teampass_app',
            'oauth2Connection' => false,
            'userPasswordVerified' => false,
        ];
    }

    // Prepare Oauth2 connection if set up
    if ($username !== 'admin') {
        // User has started to auth with oauth2
        if ((bool) $userInfo['oauth2_login_ongoing'] === true) {
            // Case where user exists in Teampass password login type
            if ((string) $userInfo['auth_type'] === 'ldap' || (string) $userInfo['auth_type'] === 'local') {
                // Update user in database:
                DB::update(
                    prefixTable('users'),
                    array(
                        'special' => 'recrypt-private-key',
                        'auth_type' => 'oauth2',
                    ),
                    'id = %i',
                    $userInfo['id']
                );
                // Update session auth type
                $session = SessionManager::getSession();
                $session->set('user-auth_type', 'oauth2');
                // Accept login request
                return [
                    'error' => false,
                    'message' => '',
                    'oauth2Connection' => true,
                    'userPasswordVerified' => true,
                ];
            } elseif ((string) $userInfo['auth_type'] === 'oauth2') {
                // OAuth2 login request on OAuth2 user account.
                return [
                    'error' => false,
                    'message' => '',
                    'oauth2Connection' => true,
                    'userPasswordVerified' => true,
                ];
            } else {
                // Case where auth_type is not managed
                return [
                    'error' => true,
                    'message' => 'user_not_allowed_to_auth_to_teampass_app',
                    'oauth2Connection' => false,
                    'userPasswordVerified' => false,
                ];
            }
        } else {
            // User has started to auth the normal way
            if ((string) $userInfo['auth_type'] === 'oauth2') {
                // Case where user exists in Teampass but not allowed to auth with Oauth2
                return [
                    'error' => true,
                    'message' => 'error_bad_credentials',
                    'oauth2Connection' => false,
                    'userPasswordVerified' => false,
                ];
            }
        }
    }

    // return if no addmin
    return [
        'error' => false,
        'message' => '',
        'oauth2Connection' => false,
        'userPasswordVerified' => false,
    ];
}

function createOauth2User(
    array $SETTINGS,
    array $userInfo,
    string $username,
    string $passwordClear,
    int $userLdapHasBeenCreated
): array
{
    // Prepare creating the new oauth2 user in Teampass
    if ((int) $SETTINGS['oauth2_enabled'] === 1
        && $username !== 'admin'
        && (bool) $userInfo['oauth2_user_to_be_created'] === true
        && $userLdapHasBeenCreated !== 1
    ) {
        $session = SessionManager::getSession();
        $lang = new Language($session->get('user-language') ?? 'english');
        
        // Create Oauth2 user if not exists and tasks enabled
        $ret = externalAdCreateUser(
            $username,
            $passwordClear,
            $userInfo['mail'],
            is_null($userInfo['givenname']) ? (is_null($userInfo['givenName']) ? '' : $userInfo['givenName']) : $userInfo['givenname'],
            is_null($userInfo['surname']) ? '' : $userInfo['surname'],
            'oauth2',
            is_null($userInfo['groups']) ? [] : $userInfo['groups'],
            $SETTINGS
        );
        $userInfo = $userInfo + $ret;

        // prepapre background tasks for item keys generation  
        handleUserKeys(
            (int) $userInfo['id'],
            (string) $passwordClear,
            (int) (isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH),
            uniqidReal(20),
            true,
            true,
            true,
            false,
            $lang->get('email_body_user_config_2'),
        );

        // Complete $userInfo
        $userInfo['has_been_created'] = 1;

        if (WIP === true) error_log("--- USER CREATED ---");

        return [
            'error' => false,
            'retExternalAD' => $userInfo,
            'oauth2Connection' => true,
            'userPasswordVerified' => true,
        ];
    
    } elseif (isset($userInfo['id']) === true && empty($userInfo['id']) === false) {
        // CHeck if user should use oauth2
        $ret = shouldUserAuthWithOauth2(
            $SETTINGS,
            $userInfo,
            $username
        );
        if ($ret['error'] === true) {
            return [
                'error' => true,
                'message' => $ret['message'],
            ];
        }

        // login/password attempt on a local account:
        // Return to avoid overwrite of user password that can allow a user
        // to steal a local account.
        if (!$ret['oauth2Connection'] || !$ret['userPasswordVerified']) {
            return [
                'error' => false,
                'message' => $ret['message'],
                'ldapConnection' => false,
                'userPasswordVerified' => false,        
            ];
        }

        // Oauth2 user already exists and authenticated
        if (WIP === true) error_log("--- USER AUTHENTICATED ---");
        $userInfo['has_been_created'] = 0;

        $passwordManager = new PasswordManager();

        // Update user hash un database if needed
        if (!$passwordManager->verifyPassword($userInfo['pw'], $passwordClear)) {
            DB::update(
                prefixTable('users'),
                [
                    'pw' => $passwordManager->hashPassword($passwordClear),
                ],
                'id = %i',
                $userInfo['id']
            );
        }

        return [
            'error' => false,
            'retExternalAD' => $userInfo,
            'oauth2Connection' => $ret['oauth2Connection'],
            'userPasswordVerified' => $ret['userPasswordVerified'],
        ];
    }

    // return if no admin
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
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
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
                $session->set('user-duo_status','');
                $session->set('user-duo_state','');
                $session->set('user-duo_data','');
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
                'mfaData' => ['message' => $lang->get('wrong_mfa_code')],
                'mfaQRCodeInfos' => false,
            ];
    }

    // If something went wrong, let's catch and return an error
    logEvents($SETTINGS, 'failed_auth', 'wrong_mfa_code', '', stripslashes($username), stripslashes($username));
    return [
        'error' => true,
        'mfaData' => ['message' => $lang->get('wrong_mfa_code')],
        'mfaQRCodeInfos' => false,
    ];
}

function identifyDoAzureChecks(
    array $SETTINGS,
    $userInfo,
    string $username
): array
{
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    logEvents($SETTINGS, 'failed_auth', 'wrong_mfa_code', '', stripslashes($username), stripslashes($username));
    return [
        'error' => true,
        'mfaData' => ['message' => $lang->get('wrong_mfa_code')],
        'mfaQRCodeInfos' => false,
    ];
}

/**
 * Add a failed authentication attempt to the database.
 * If the number of failed attempts exceeds the limit, a lock is triggered.
 * 
 * @param string $source - The source of the failed attempt (login or remote_ip).
 * @param string $value  - The value for this source (username or IP address).
 * @param int    $limit  - The failure attempt limit after which the account/IP
 *                         will be locked.
 */
function handleFailedAttempts($source, $value, $limit) {
    // Count failed attempts from this source
    $count = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM ' . prefixTable('auth_failures') . '
        WHERE source = %s AND value = %s',
        $source,
        $value
    );

    // Add this attempt
    $count++;

    // Calculate unlock time if number of attempts exceeds limit
    $unlock_at = $count >= $limit
        ? date('Y-m-d H:i:s', time() + (($count - $limit + 1) * 600))
        : NULL;

    // Unlock account one time code
    $unlock_code = ($count >= $limit && $source === 'login')
        ? generateQuickPassword(30, false)
        : NULL;

    // Insert the new failure into the database
    DB::insert(
        prefixTable('auth_failures'),
        [
            'source' => $source,
            'value' => $value,
            'unlock_at' => $unlock_at,
            'unlock_code' => $unlock_code,
        ]
    );

    if ($unlock_at !== null && $source === 'login') {
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();
        $lang = new Language($SETTINGS['default_language']);

        // Get user email
        $userInfos = DB::QueryFirstRow(
            'SELECT email, name
             FROM '.prefixTable('users').'
             WHERE login = %s',
             $value
        );

        // No valid email address for user
        if (!$userInfos || !filter_var($userInfos['email'], FILTER_VALIDATE_EMAIL))
            return;

        $unlock_url = $SETTINGS['cpassman_url'].'/self-unlock.php?login='.$value.'&otp='.$unlock_code;

        sendMailToUser(
            $userInfos['email'],
            $lang->get('bruteforce_reset_mail_body'),
            $lang->get('bruteforce_reset_mail_subject'),
            [
                '#name#' => $userInfos['name'],
                '#reset_url#' => $unlock_url,
                '#unlock_at#' => $unlock_at,
            ],
            true
        );
    }
}

/**
 * Add failed authentication attempts for both user login and IP address.
 * This function will check the number of attempts for both the username and IP,
 * and will trigger a lock if the number exceeds the defined limits.
 * It also deletes logs older than 24 hours.
 * 
 * @param string $username - The username that was attempted to login.
 * @param string $ip       - The IP address from which the login attempt was made.
 */
function addFailedAuthentication($username, $ip) {
    $user_limit = 10;
    $ip_limit = 30;

    // Remove old logs (more than 24 hours)
    DB::delete(
        prefixTable('auth_failures'),
        'date < %s AND (unlock_at < %s OR unlock_at IS NULL)',
        date('Y-m-d H:i:s', time() - (24 * 3600)),
        date('Y-m-d H:i:s', time())
    );

    // Add attempts in database
    handleFailedAttempts('login', $username, $user_limit);
    handleFailedAttempts('remote_ip', $ip, $ip_limit);
}
