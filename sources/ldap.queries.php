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
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;


require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} elseif (file_exists('../../includes/config/tp.config.php')) {
    include_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'ldap', $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/tp.config.php';

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

// connect to the server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);

switch ($post_type) {
        //CASE for getting informations about the tool
    case 'ldap_test_configuration':
        // Check KEY and rights
        if ($post_key !== $_SESSION['key']) {
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            $post_data,
            'decode'
        );

        // prepare variables
        $post_username = filter_var($dataReceived['username'], FILTER_SANITIZE_STRING);
        $post_password = filter_var($dataReceived['password'], FILTER_SANITIZE_STRING);

        // Build ldap configuration array
        $config = [
            // Mandatory Configuration Options
            'hosts'            => [explode(",", $SETTINGS['ldap_hosts'])],
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
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_HARD
            ]
        ];

        // Load expected libraries
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/helpers.php';
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Traits/Macroable.php';
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Arr.php';
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Symfony/contracts/Translation/TranslatorInterface.php';
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

        $ad = new SplClassLoader('LdapRecord', '../includes/libraries');
        $ad->register();
        $connection = new Connection($config);

        try {
            $connection->connect();
        
        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();

            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => "Error : ".$error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage(),
                ),
                'encode'
            );
            break;
        }

        try {
            $usr = $connection->query()
                ->where($SETTINGS['ldap_user_attribute'], '=', $post_username)
                ->firstOrFail();

        } catch (\LdapRecord\LdapRecordException $e) {
            $error = $e->setDetailedError();
            
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => "Error : ".$e->withDetailedError($e),//->detailedError(),
                ),
                'encode'
            );
            break;
        }
        //TODO
        try {
            if ($SETTINGS['ldap_type'] === 'ActiveDirectory') {
                $connection->auth()->bind($usr[$SETTINGS['settings_ldap_user_adn_ttribute']][0], $post_password);
            } else {
                $connection->auth()->bind($SETTINGS['ldap_user_attribute'].'='.$post_username.','.(isset($SETTINGS['ldap_dn_additional_user_dn']) && !empty($SETTINGS['ldap_dn_additional_user_dn']) ? $SETTINGS['ldap_dn_additional_user_dn'].',' : '').$SETTINGS['ldap_bdn'], $post_password);
            }
        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();
            
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => "Error : ".$error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage(),
                ),
                'encode'
            );
            break;
        }
        
        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => "User is successfully authenticated",
                'extra' => $SETTINGS['ldap_user_attribute'].'='.$post_username.','.$SETTINGS['ldap_bdn'],
            ),
            'encode'
        );

    break;
}
