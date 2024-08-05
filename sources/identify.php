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
use EZimuel\PHPSecureSession;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Auth\Events\Failed;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PasswordManager\PasswordManager;
use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;
use RobThree\Auth\TwoFactorAuth;
use TeampassClasses\LdapExtra\LdapExtra;
use TeampassClasses\LdapExtra\OpenLdapExtra;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;
use TeampassClasses\AzureAuthController\AzureAuthController;
use TheNetworg\OAuth2\Client\Provider\Azure;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
        'login' => isset($_POST['login']) === false ? null : $_POST['login'],
        'sso' => isset($_POST['sso']) === false ? null : $_POST['sso'],
    ]
);

// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

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

    /**
     * Permits to handle login attempts
     *
     * @param string $post_data
     * @param array $SETTINGS
     * @return bool|string
     */
    function handleAuthAttempts($post_data, $SETTINGS): bool|string
    {
        $session = SessionManager::getSession();
        $lang = new Language($session->get('user-language') ?? 'english');
        $sessionPwdAttempts = $session->get('pwd_attempts');
        $nextPossibleAttempts = (int) $session->get('next_possible_pwd_attempts');

        // Check if the user is currently within the waiting period
        if ($nextPossibleAttempts > 0 && time() < $nextPossibleAttempts) {
            // Brute force wait
            $remainingSeconds = $nextPossibleAttempts - time();
            $errorResponse = prepareExchangedData([
                'value' => 'bruteforce_wait',
                'user_admin' => null !== $session->get('user-admin') ? (int) $session->get('user-admin') : 0,
                'initial_url' => null !== $session->get('user-initial_url') ? $session->get('user-initial_url') : '',
                'pwd_attempts' => 0,
                'error' => true,
                'message' => $lang->get('error_bad_credentials_more_than_3_times'),
                'remaining_seconds' => $remainingSeconds,
            ], 'encode');

            echo $errorResponse;
            return false;
        }

        // Increment the counter of login attempts
        $sessionPwdAttempts = ($sessionPwdAttempts === '') ? 1 : ++$sessionPwdAttempts;
        $session->set('pwd_attempts', $sessionPwdAttempts);

        // Check for brute force attempts
        if ($sessionPwdAttempts <= 3) {
            // Identify the user through Teampass process
            identifyUser($post_data, $SETTINGS);
        } else {
            // Reset attempts and set waiting period on the fourth consecutive attempt
            $session->set('pwd_attempts', 0);

            if ($sessionPwdAttempts === 4) {
                // On the fourth consecutive attempt, trigger the waiting period
                $nextPossibleAttempts = time() + 10;
                $session->set('next_possible_pwd_attempts', $nextPossibleAttempts);

                // Send an error response indicating the waiting period
                $errorResponse = prepareExchangedData([
                    'value' => 'bruteforce_wait',
                    'user_admin' => null !== $session->get('user-admin') ? (int) $session->get('user-admin') : 0,
                    'initial_url' => null !== $session->get('user-initial_url') ? $session->get('user-initial_url') : '',
                    'pwd_attempts' => 0,
                    'error' => true,
                    'message' => $lang->get('error_bad_credentials_more_than_3_times'),
                    'remaining_seconds' => 10,
                ], 'encode');

                echo $errorResponse;
                return false;
            }
            
            // Identify the user through Teampass process
            identifyUser($post_data, $SETTINGS);
        }

        return true;
    }

    handleAuthAttempts($post_data, $SETTINGS);

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
    $azureAuth = new AzureAuthController($SETTINGS);

    // Redirection vers Azure pour l'authentification
    $azureAuth->redirect();

/*
    $provider = new TheNetworg\OAuth2\Client\Provider\Azure([
        'clientId'                => $SETTINGS['oauth2_azure_clientId'],
        'clientSecret'            => $SETTINGS['oauth2_azure_clientSecret'],
        'redirectUri'             => $SETTINGS['oauth2_azure_redirectUri'],
        'urlAuthorize'            => $SETTINGS['oauth2_azure_urlAuthorize'],
        'urlAccessToken'          => $SETTINGS['oauth2_azure_urlAccessToken'],
        'urlResourceOwnerDetails' => $SETTINGS['oauth2_azure_urlResourceOwnerDetails'],
        'scopes'                  => explode(",", $SETTINGS['oauth2_azure_scopes']),
        'defaultEndPointVersion' => '2.0'
    ]);
    $provider->defaultEndPointVersion = TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0;
    $baseGraphUri = $provider->getRootMicrosoftGraphUri(null);
    $provider->scope = 'openid profile email offline_access ' . $baseGraphUri . '/User.Read';
    if (isset($_GET['code']) && isset($_SESSION['OAuth2.state']) && isset($_GET['state'])) {
        if ($_GET['state'] == $_SESSION['OAuth2.state']) {
            unset($_SESSION['OAuth2.state']);
    
            $token = $provider->getAccessToken('authorization_code', [
                'scope' => $provider->scope,
                'code' => $_GET['code'],
            ]);
    
            // Verify token
            // Save it to local server session data
            
            return $token->getToken();
        } else {
            echo 'Invalid state';
    
            return null;
        }
    } else {
        $authorizationUrl = $provider->getAuthorizationUrl(['scope' => $provider->scope]);

        $_SESSION['OAuth2.state'] = $provider->getState();

        header('Location: ' . $authorizationUrl);

        exit;

        return $token->getToken();
    }
*/

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
        if($duo_data_dec === false){
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
        echo prepareExchangedData(
            $userInitialData['array'],
            'encode'
        );
        return false;
    }

    $userInfo = $userInitialData['userInfo'];
    $return = '';

    // If we have oauth2-azure enabled then we need to check if the user is in the Azure Entra ID
    /*if (isKeyExistingAndEqual('oauth2_azure', 1, $SETTINGS) === true) {
        $authAzure = identifyDoAzureChecks(
            $SETTINGS,
            $userInfo,
            (string) $username
        );
        if ($authAzure['error'] === true) {
            // deepcode ignore ServerLeak: File and path are secured directly inside the function decryptFile()
            echo prepareExchangedData(
                $authAzure['array'],
                'encode'
            );
            return false;
        }
    }*/

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
        // deepcode ignore ServerLeak: File and path are secured directly inside the function decryptFile()
        echo prepareExchangedData(
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

    // Check user and password
    if ($userLdap['userPasswordVerified'] === false && (int) checkCredentials($passwordClear, $userInfo) !== 1) {
        echo prepareExchangedData(
            [
                'value' => '',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => (int) $sessionPwdAttempts,
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
        $lifetime = time() + ($dataReceived['duree_session'] * 60);

        //--- Handle the session duration and ID
        //$cookieParams = session_get_cookie_params();
        //error_log('DEBUG: '.session_name()."=".session_id()."; lifetime=".$lifetime."; cookieParams=".print_r($cookieParams, true));
        /*setcookie(
            session_name(),
            session_id(),
            $lifetime,
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );*/
        //---

        
        // Save account in SESSION
        $session->set('user-unsuccessfull_login_attempts_list', $attemptsInfos['attemptsList'] === 0 ? true : false);
        $session->set('user-unsuccessfull_login_attempts_shown', $attemptsInfos['attemptsCount'] === 0 ? true : false);
        $session->set('user-unsuccessfull_login_attempts_nb', DB::count());
        $session->set('user-login', stripslashes($username));
        $session->set('user-name', empty($userInfo['name']) === false ? stripslashes($userInfo['name']) : '');
        $session->set('user-lastname', empty($userInfo['lastname']) === false ? stripslashes($userInfo['lastname']) : '');
        $session->set('user-id', (int) $userInfo['id']);
        $session->set('user-password', $passwordClear);
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
        $session->set('user-language', $userInfo['user_language']);
        $session->set('user-timezone', $userInfo['usertimezone']);
        $session->set('user-keys_recovery_time', $userInfo['keys_recovery_time']);
        
        // manage session expiration
        $session->set('user-session_duration', (int) $lifetime);

        // User signature keys
        $returnKeys = prepareUserEncryptionKeys($userInfo, $passwordClear);  
        $session->set('user-private_key', $returnKeys['private_key_clear']);
        $session->set('user-public_key', $returnKeys['public_key']);      

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
            $session->get('user-num_days_before_exp'),
            $session->get('user-last_pw_change'),
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
                    'no_bad_attempts' => 0,
                    'session_end' => $session->get('user-session_duration'),
                    'user_ip' => $dataReceived['client'],
                ],
                $returnKeys['update_keys_in_db']
            ),
            'id=%i',
            $userInfo['id']
        );
        
        // Get user's rights
        if ($userLdap['user_initial_creation_through_ldap'] === true) {
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
                'shown_warning_unsuccessful_login' => $session->get('user-unsuccessfull_login_attempts_shown'),
                'nb_unsuccessful_logins' => $session->get('user-unsuccessfull_login_attempts_nb'),
                'upgrade_needed' => isset($userInfo['upgrade_needed']) === true ? (int) $userInfo['upgrade_needed'] : 0,
                'special' => isset($userInfo['special']) === true ? (int) $userInfo['special'] : 0,
            ],
            'encode'
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
                'shown_warning_unsuccessful_login' => $session->get('user-unsuccessfull_login_attempts_shown'),
                'nb_unsuccessful_logins' => $session->get('user-unsuccessfull_login_attempts_nb'),
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
            $SETTINGS
        ) === true
    ) {
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
                'private_key_conform' => $session->get('user-id') !== null
                    && empty($session->get('user-private_key')) === false
                    && $session->get('user-private_key') !== 'none' ? true : false,
                'session_key' => $session->get('key'),
                'can_create_root_folder' => null !== $session->get('user-can_create_root_folder') ? (int) $session->get('user-can_create_root_folder') : '',
                'shown_warning_unsuccessful_login' => $session->get('user-unsuccessfull_login_attempts_shown'),
                'nb_unsuccessful_logins' => $session->get('user-unsuccessfull_login_attempts_nb'),
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
            'shown_warning_unsuccessful_login' => $session->get('user-unsuccessfull_login_attempts_shown'),
            'nb_unsuccessful_logins' => $session->get('user-unsuccessfull_login_attempts_nb'),
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
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    // 1- Connect to LDAP
    try {
        switch ($SETTINGS['ldap_type']) {
            case 'ActiveDirectory':
                $ldapExtra = new LdapExtra($SETTINGS);
                $ldapConnection = $ldapExtra->establishLdapConnection();
                $activeDirectoryExtra = new ActiveDirectoryExtra();
                break;
            case 'OpenLDAP':
                // Establish connection for OpenLDAP
                $ldapExtra = new LdapExtra($SETTINGS);
                $ldapConnection = $ldapExtra->establishLdapConnection();

                // Create an instance of OpenLdapExtra and configure it
                $openLdapExtra = new OpenLdapExtra();
                break;
            default:
                throw new Exception("Unsupported LDAP type: " . $SETTINGS['ldap_type']);
        }
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => "Error:".$e->getMessage(),
        ];
    }
    
    try {
        // 2- Get user info from AD
        // We want to isolate attribute ldap_user_attribute or mostly samAccountName
        $userADInfos = $ldapConnection->query()
            ->where((isset($SETTINGS['ldap_user_attribute']) ===true && empty($SETTINGS['ldap_user_attribute']) === false) ? $SETTINGS['ldap_user_attribute'] : 'samaccountname', '=', $username)
            ->firstOrFail();

        // Is user enabled? Only ActiveDirectory
        if ($SETTINGS['ldap_type'] === 'ActiveDirectory' && isset($activeDirectoryExtra) === true && $activeDirectoryExtra instanceof ActiveDirectoryExtra) {
            if ($activeDirectoryExtra->userIsEnabled((string) $userADInfos['dn'], $ldapConnection) === false) {
                return [
                    'error' => true,
                    'message' => "Error : User is not enabled",
                ];
            }
        }

        // 3- User auth attempt
        // For AD, we use attribute userPrincipalName
        // For OpenLDAP and others, we use attribute dn
        $userAuthAttempt = $ldapConnection->auth()->attempt(
            $SETTINGS['ldap_type'] === 'ActiveDirectory' ?
                $userADInfos['userprincipalname'][0] :  // refering to https://ldaprecord.com/docs/core/v2/authentication#basic-authentication
                $userADInfos['dn'],
            $passwordClear
        );

        // User is not auth then return error
        if ($userAuthAttempt === false) {
            return [
                'error' => true,
                'message' => "Error: User is not authenticated",
            ];
        }

    } catch (\LdapRecord\Query\ObjectNotFoundException $e) {
        $error = $e->getDetailedError();
        return [
            'error' => true,
            'message' => $lang->get('error_bad_credentials'),
        ];
    }

    // 4- Check shadowexpire attribute
    // if === 1 then user disabled
    if (
        (isset($userADInfos['shadowexpire'][0]) === true && (int) $userADInfos['shadowexpire'][0] === 1)
        ||
        (isset($userADInfos['accountexpires'][0]) === true && (int) $userADInfos['accountexpires'][0] < time() && (int) $userADInfos['accountexpires'][0] != 0)
    ) {
        return [
            'error' => true,
            'message' => $lang->get('error_ad_user_expired'),
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
    } else {
        $userInfo['has_been_created'] = 0;
    }

    // Update user info with his AD groups
    if ($SETTINGS['ldap_type'] === 'ActiveDirectory' && isset($activeDirectoryExtra) && $activeDirectoryExtra instanceof ActiveDirectoryExtra) {
        $userGroupsData = $activeDirectoryExtra->getUserADGroups(
            $userADInfos[(isset($SETTINGS['ldap_user_dn_attribute']) === true && empty($SETTINGS['ldap_user_dn_attribute']) === false) ? $SETTINGS['ldap_user_dn_attribute'] : 'distinguishedname'][0], 
            $ldapConnection, 
            $SETTINGS
        );
    } elseif ($SETTINGS['ldap_type'] == 'OpenLDAP' && isset($openLdapExtra) && $openLdapExtra instanceof OpenLdapExtra) {
        $userGroupsData = $openLdapExtra->getUserADGroups(
            $userADInfos['dn'],
            $ldapConnection,
            $SETTINGS
        );
    } else {
        // error
        return [
            'error' => true,
            'message' => "Error: Unsupported LDAP type: " . $SETTINGS['ldap_type'],
        ];
    }
    
    handleUserADGroups(
        $username,
        $userInfo,
        $userGroupsData['userGroups'],
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
    } elseif ($passwordManager->verifyPassword($userInfo['pw'], $passwordClear) === false) {
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
    if (WIP === true) error_log("finalizeAuthentication - hashedPassword: " . $hashedPassword. " | ".$passwordManager->verifyPassword($userInfo['pw'], $passwordClear));
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
function ldapCreateUser(string $login, string $passwordClear, string $userEmail, string $userName, string $userLastname, array $SETTINGS): array
{
    // Generate user keys pair
    $userKeys = generateUserKeys($passwordClear);

    // Create password hash
    $passwordManager = new PasswordManager();
    $hashedPassword = $passwordManager->hashPassword($passwordClear);

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
            'user-language' => (string) $SETTINGS['default_language'],
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
            'read_only' => 1,
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
function checkCredentials($passwordClear, $userInfo)
{
    $passwordManager = new PasswordManager();
    // Migrate password if needed
    $passwordManager->migratePassword(
        $userInfo['pw'],
        $passwordClear,
        (int) $userInfo['id']
    );
    if (WIP === true) error_log("checkCredentials - User ".$userInfo['id']." | verify pwd: ".$passwordManager->verifyPassword($userInfo['pw'], $passwordClear));

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

    // Methods
    public function get_is_too_much_attempts($attempts) {
        if ($attempts > 30) {
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
            WHERE login = %s AND deleted_at IS NULL',
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
    $session = SessionManager::getSession();
    $checks = new initialChecks();
    $enable_ad_user_auto_creation = isset($SETTINGS['enable_ad_user_auto_creation']) === true && (int) $SETTINGS['enable_ad_user_auto_creation'] === 1 ? true : false;
    $lang = new Language($session->get('user-language') ?? 'english');
    
    // Brute force management
    try {
        $checks->get_is_too_much_attempts($sessionPwdAttempts);
    } catch (Exception $e) {
        $session->set('next_possible_pwd_attempts', (time() + 10));
        $session->set('pwd_attempts', 0);

        logEvents($SETTINGS, 'failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));

        return [
            'error' => true,
            'array' => [
                'value' => 'bruteforce_wait',
                'user_admin' => isset($sessionAdmin) ? (int) $sessionAdmin : 0,
                'initial_url' => isset($sessionUrl) === true ? $sessionUrl : '',
                'pwd_attempts' => 0,
                'error' => true,
                'message' => $lang->get('error_bad_credentials_more_than_3_times'),
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
                'value' => 'user_not_exists',
                'error' => true,
                'message' => $lang->get('error_bad_credentials'),
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
                'message' => $lang->get('select_valid_2fa_credentials'),
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
                'message' => $lang->get('remove_install_folder'),
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