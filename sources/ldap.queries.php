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
 * @file      ldap.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use LdapRecord\Container;
use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\LdapExtra\LdapExtra;
use TeampassClasses\LdapExtra\OpenLdapExtra;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;

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
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('ldap') === false ||
    $checkUserAccess->checkSession() === false
) {
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
set_time_limit(0);

// --------------------------------- //

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

switch ($post_type) {
    //CASE for getting informations about the tool
    case 'ldap_test_configuration':
        // Check KEY and rights
        if ($post_key !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $post_data,
            'decode'
        );

        // prepare variables
        $post_username = filter_var($dataReceived['username'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_password = $dataReceived['password']; // No filtering as password can contain special chars

        // Check if data is correct
        if (empty($post_username) === true && empty($post_password) === true) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => "Error : ".$lang->get('error_empty_data'),
                ),
                'encode'
            );
            break;
        }

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
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS Error - ldap - '.$e->getMessage());
            }
            // deepcode ignore ServerLeak: No important data is sent and is encrypted before being sent
            echo  prepareExchangedData(
                array(
                'error' => true,
                'message' => 'An error occurred while opening connection to AD server',
            ), 'encode');
            break;
        }

        try {
            // 2- Get user info from AD
            // We want to isolate attribute ldap_user_attribute or mostly samAccountName
            $userADInfos = $ldapConnection->query()
                ->where((isset($SETTINGS['ldap_user_attribute']) ===true && empty($SETTINGS['ldap_user_attribute']) === false) ? $SETTINGS['ldap_user_attribute'] : 'samaccountname', '=', $post_username)
                ->firstOrFail();

            // Is user enabled? Only ActiveDirectory
            if ($SETTINGS['ldap_type'] === 'ActiveDirectory' && isset($activeDirectoryExtra) === true && $activeDirectoryExtra instanceof ActiveDirectoryExtra) {
                //require_once 'ldap.activedirectory.php';
                if ($activeDirectoryExtra->userIsEnabled((string) $userADInfos['dn'], $ldapConnection) === false) {
                    echo prepareExchangedData(
                        array(
                        'error' => true,
                        'message' => "Error : User is not enabled",
                        ),
                        'encode'
                    );
                    break;
                }
            }
    
        } catch (\LdapRecord\Query\ObjectNotFoundException $e) {
            $error = $e->getDetailedError();
            if ($error && defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS Error - LDAP - '.$error->getErrorCode()." - ".$error->getErrorMessage(). " - ".$error->getDiagnosticMessage());
            } 
            // deepcode ignore ServerLeak: No important data is sent and is encrypted before being sent
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => 'An error occurred.',
                ),
                'encode'
            );
            break;
        }

        try {
            // 3- User auth attempt
            // For AD, we use attribute userPrincipalName
            // For OpenLDAP and others, we use attribute dn
            $userAuthAttempt = $ldapConnection->auth()->attempt(
                $SETTINGS['ldap_type'] === 'ActiveDirectory' ?
                    $userADInfos['userprincipalname'][0] :  // refering to https://ldaprecord.com/docs/core/v2/authentication#basic-authentication
                    $userADInfos['dn'],
                $post_password
            );
    
            // User is not auth then return error
            if ($userAuthAttempt === false) {
                echo  prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => "Error: User is not authenticated",
                    ),
                    'encode'
                );
                break;
            }
        } catch (\LdapRecord\Query\ObjectNotFoundException $e) {
            $error = $e->getDetailedError();
            if ($error && defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS Error - LDAP - '.$error->getErrorCode()." - ".$error->getErrorMessage(). " - ".$error->getDiagnosticMessage());
            }
            // deepcode ignore ServerLeak: No important data is sent and is encrypted before being sent
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => 'An error occurred.',
                ),
                'encode'
            );
        }
    
        // 4- Check shadowexpire attribute
        // if === 1 then user disabled
        if (
            (isset($userADInfos['shadowexpire'][0]) === true && (int) $userADInfos['shadowexpire'][0] === 1)
            ||
            (isset($userADInfos['accountexpires'][0]) === true && (int) $userADInfos['accountexpires'][0] < time() && (int) $userADInfos['accountexpires'][0] != 0)
        ) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_ad_user_expired'),
                ),
                'encode'
            );
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => "User is successfully authenticated",
                'extra' => $SETTINGS['ldap_user_attribute'].'='.$post_username.','.$SETTINGS['ldap_bdn'],
            ),
            'encode'
        );

    break;
}
