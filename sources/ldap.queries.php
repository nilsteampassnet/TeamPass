<?php
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
 * @copyright 2009-2019 Teampass.net
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
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
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
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
//$link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWD_CLEAR, DB_NAME, DB_PORT);
//$link->set_charset(DB_ENCODING);

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Load AntiXSS
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/voku/helper/AntiXSS.php';
$antiXss = new voku\helper\AntiXSS();

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
                array(
                    'error' => true,
                    'message' => langHdl('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData($post_data, 'decode');

        // prepare variables
        $post_username = filter_var($dataReceived['username'], FILTER_SANITIZE_STRING);
        $post_password = filter_var($dataReceived['password'], FILTER_SANITIZE_STRING);

        // Build ldap configuration array
        $config = [
            // Mandatory Configuration Options
            'hosts'            => [$SETTINGS['ldap_domain_controler']],
            'base_dn'          => $SETTINGS['ldap_search_base'],
            'username'         => $SETTINGS['ldap_bind_dn'],
            'password'         => $SETTINGS['ldap_bind_passwd'],
        
            // Optional Configuration Options
            'port'             => $SETTINGS['ldap_port'],
            'use_ssl'          => $SETTINGS['ldap_ssl'] === 1 ? true : false,
            'use_tls'          => $SETTINGS['ldap_tls'] === 1 ? true : false,
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
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Traits/Macroable.php';
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tightenco/Collect/Support/Arr.php';
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/DetectsErrors.php';
        require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/LdapRecord/Connection.php';

        $ad = new SplClassLoader('LdapRecord', '../includes/libraries');
        $ad->register();
        $connection = new Connection($config);

        try {
            $connection->connect();
        
        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();

            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => "Error : ".$error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage(),
                ),
                'encode'
            );
            break;
        }

        try {
            $connection->auth()->bind($SETTINGS['ldap_user_attribute'].'='.$post_username.',cn=users,'.$SETTINGS['ldap_bdn'], $post_password);

        } catch (\LdapRecord\Auth\BindException $e) {
            $error = $e->getDetailedError();
            
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => "Error : ".$error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage(),
                ),
                'encode'
            );
            break;
        }
        
        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => "Great",
            ),
            'encode'
        );

    break;
/*
        $debug_ldap = $ldap_suffix = '';

        //Multiple Domain Names
        if (strpos(html_entity_decode($dataReceived['username']), '\\') === true) {
            $ldap_suffix = '@' . substr(html_entity_decode($dataReceived['username']), 0, strpos(html_entity_decode($dataReceived['username']), '\\'));
            $dataReceived['username'] = substr(html_entity_decode($dataReceived['username']), strpos(html_entity_decode($dataReceived['username']), '\\') + 1);
        }
        if ($SETTINGS['ldap_type'] === 'posix-search') {
            $ldapURIs = '';
            foreach (explode(',', $SETTINGS['ldap_domain_controler']) as $domainControler) {
                if ((int) $SETTINGS['ldap_ssl'] === 1) {
                    $ldapURIs .= 'ldaps://' . $domainControler . ':' . $SETTINGS['ldap_port'] . ' ';
                } else {
                    $ldapURIs .= 'ldap://' . $domainControler . ':' . $SETTINGS['ldap_port'] . ' ';
                }
            }

            $debug_ldap .= 'LDAP URIs : ' . $ldapURIs . '<br/>';
            $ldapconn = ldap_connect($ldapURIs);

            if ($SETTINGS['ldap_ssl']) {
                ldap_start_tls($ldapconn);
            }

            $debug_ldap .= 'LDAP connection : ' . ($ldapconn ? 'Connected' : 'Failed') . '<br/>';

            if ($ldapconn) {
                $debug_ldap .= 'DN : ' . $SETTINGS['ldap_bind_dn'] . ' -- ' . $SETTINGS['ldap_bind_passwd'] . '<br/>';
                ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);
                $ldapbind = @ldap_bind($ldapconn, $SETTINGS['ldap_bind_dn'], $SETTINGS['ldap_bind_passwd']);

                $debug_ldap .= 'LDAP bind : ' . ($ldapbind === true ? 'Bound' : 'Failed') . '<br/>';

                if ($ldapbind === true) {
                    $filter = '(&(' . $SETTINGS['ldap_user_attribute'] . '=' . $dataReceived['username'] . ')(objectClass=' . $SETTINGS['ldap_object_class'] . '))';
                    //echo $filter;
                    $result = ldap_search(
                        $ldapconn,
                        $SETTINGS['ldap_search_base'],
                        $filter,
                        array('dn', 'mail', 'givenname', 'sn', 'uid', 'name', 'displayname')
                    );
                    if (isset($SETTINGS['ldap_usergroup'])) {
                        $GroupRestrictionEnabled = false;
                        $filter_group = 'memberUid=' . $dataReceived['username'];
                        $result_group = ldap_search(
                            $ldapconn,
                            $SETTINGS['ldap_search_base'],
                            $filter_group,
                            array('dn')
                        );

                        $debug_ldap .= 'Search filter (group): ' . $filter_group . '<br/>' .
                            'Results : ' . str_replace("\n", '<br>', print_r(ldap_get_entries($ldapconn, $result_group), true)) . '<br/>';

                        if ($result_group) {
                            $entries = ldap_get_entries($ldapconn, $result_group);

                            if ($entries['count'] > 0) {
                                // Now check if group fits
                                for ($i = 0; $i < $entries['count']; ++$i) {
                                    $parsr = ldap_explode_dn($entries[$i]['dn'], 0);
                                    if (str_replace(array('CN=', 'cn='), '', $parsr[0]) === $SETTINGS['ldap_usergroup']) {
                                        $GroupRestrictionEnabled = true;
                                        break;
                                    }
                                }
                            }
                        }

                        $debug_ldap .= 'Find user in Group: ' . $GroupRestrictionEnabled . '<br/>';
                    }

                    $debug_ldap .= 'Search filter : ' . $filter . '<br/>' .
                        'Results : ' . str_replace("\n", '<br>', print_r(ldap_get_entries($ldapconn, $result), true)) . '<br/>';

                    if (ldap_count_entries($ldapconn, $result)) {
                        // try auth
                        $result = ldap_get_entries($ldapconn, $result);
                        $user_dn = $result[0]['dn'];
                        $ldapbind = @ldap_bind($ldapconn, $user_dn, $dataReceived['password']);
                        if ($ldapbind) {
                            $debug_ldap .= 'Successfully connected';
                        } else {
                            $debug_ldap .= 'Error - Cannot connect user!';
                        }
                    }
                } else {
                    $debug_ldap .= 'Error - Could not bind server!';
                }
            } else {
                $debug_ldap .= 'Error - Could not connect to server!';
            }
        } else {
            $debug_ldap .= 'Get all ldap params: <br/>' .
                '  - base_dn : ' . $SETTINGS['ldap_domain_dn'] . '<br/>' .
                '  - account_suffix : ' . $SETTINGS['ldap_suffix'] . '<br/>' .
                '  - domain_controllers : ' . $SETTINGS['ldap_domain_controler'] . '<br/>' .
                '  - ad_port : ' . $SETTINGS['ldap_port'] . '<br/>' .
                '  - use_ssl : ' . $SETTINGS['ldap_ssl_input'] . '<br/>' .
                '  - use_tls : ' . $SETTINGS['ldap_ssl'] . '<br/>*********<br/>';

            $adldap = new SplClassLoader('adLDAP', '../includes/libraries/LDAP');
            $adldap->register();

            // Posix style LDAP handles user searches a bit differently
            if ($SETTINGS['ldap_type'] === 'posix') {
                $ldap_suffix = ',' . $SETTINGS['ldap_suffix'] . ',' . $SETTINGS['ldap_domain_dn'];
            } elseif ($SETTINGS['ldap_type'] === 'windows' && $ldap_suffix === '') { //Multiple Domain Names
                $ldap_suffix = $SETTINGS['ldap_suffix'];
            }
            $adldap = new adLDAP\adLDAP(
                array(
                    'base_dn' => $SETTINGS['ldap_domain_dn'],
                    'account_suffix' => $ldap_suffix,
                    'domain_controllers' => explode(',', $SETTINGS['ldap_domain_controler']),
                    'ad_port' => $SETTINGS['ldap_port'],
                    'use_ssl' => $SETTINGS['ldap_ssl_input'],
                    'use_tls' => $SETTINGS['ldap_ssl'],
                )
            );

            $debug_ldap .= 'Create new adldap object : ' . $adldap->getLastError() . '<br/><br/>';

            // openLDAP expects an attribute=value pair
            if ($SETTINGS['ldap_type'] === 'posix') {
                $auth_username = $SETTINGS['ldap_user_attribute'] . '=' . $dataReceived['username'];
            } else {
                $auth_username = $dataReceived['username'];
            }

            // authenticate the user
            if ($adldap->authenticate($auth_username, html_entity_decode($dataReceived['username_pwd']))) {
                $ldapConnection = 'Successfull';
            } else {
                $ldapConnection = 'Not possible to get connected with this user';
            }

            $debug_ldap .= 'After authenticate : ' . $adldap->getLastError() . '<br/><br/>' .
                'ldap status : ' . $ldapConnection; //Debug
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => ($debug_ldap),
            ),
            'encode'
        );

        break;*/
}
