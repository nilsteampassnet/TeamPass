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
 * @file      ldap.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use LdapRecord\Container;
use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$session = SessionManager::getSession();
$lang = new Language(); 

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => isset($_POST['type']) === true ? htmlspecialchars($_POST['type']) : '',
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
        $post_password = filter_var($dataReceived['password'], FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

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

        // Build ldap configuration array
        $config = [
            // Mandatory Configuration Options
            'hosts'            => explode(",", $SETTINGS['ldap_hosts']),
            'base_dn'          => $SETTINGS['ldap_bdn'],
            'username'         => $SETTINGS['ldap_username'],
            'password'         => $SETTINGS['ldap_password'],
            // Optional Configuration Options
            'port'             => $SETTINGS['ldap_port'],
            'use_ssl'          => (int) $SETTINGS['ldap_ssl'] === 1 ? true : false,
            'use_tls'          => (int) $SETTINGS['ldap_tls'] === 1 ? true : false,
            'version'          => 3,
            'timeout'          => 5,
            'follow_referrals' => false,
            // Custom LDAP Options
            'options' => [
                // See: http://php.net/ldap_set_option
                LDAP_OPT_X_TLS_REQUIRE_CERT => isset($SETTINGS['ldap_tls_certifacte_check']) === false ? 'LDAP_OPT_X_TLS_NEVER' : $SETTINGS['ldap_tls_certifacte_check'],
            ]
        ];
        //prepare connection
        $connection = new Connection($config);

        try {
            $connection->connect();
            Container::addConnection($connection);

        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();

            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => "Error : ".(isset($error) === true ? $error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage() : $e),
                ),
                'encode'
            );
            break;
        }
        
        // Get user info from AD
        // We want to isolate attribute ldap_user_attribute
        try {
            $user = $connection->query()
                ->where((isset($SETTINGS['ldap_user_attribute']) ===true && empty($SETTINGS['ldap_user_attribute']) === false) ? $SETTINGS['ldap_user_attribute'] : 'samaccountname', '=', $post_username)
                ->firstOrFail();
            
        } catch (\LdapRecord\LdapRecordException $e) {
            $error = $e->getDetailedError();
            
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error')." - ".(isset($error) === true ? $error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage() : $e),
                ),
                'encode'
            );
            break;
        }
        
        try {
            $userAuthAttempt = $connection->auth()->attempt(
                $SETTINGS['ldap_type'] === 'ActiveDirectory' ?
                    $user['userprincipalname'][0] :
                    $user['dn'],
                $post_password
            );
        } catch (\LdapRecord\LdapRecordException $e) {
            $error = $e->getDetailedError();
            
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error').' : '.(isset($error) === true ? $error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage() : $e),
                ),
                'encode'
            );
            break;
        }
        
        if ($userAuthAttempt === true) {
            // Update user info with his AD groups
            if ($SETTINGS['ldap_type'] === 'ActiveDirectory') {
                require_once 'ldap.activedirectory.php';
            } else {
                require_once 'ldap.openldap.php';
            }
            
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => "User is successfully authenticated",
                    'extra' => $SETTINGS['ldap_user_attribute'].'='.$post_username.','.$SETTINGS['ldap_bdn'],
                ),
                'encode'
            );
        } else {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => "Error : User could not be authentificated",
                ),
                'encode'
            );
        }

    break;
}
