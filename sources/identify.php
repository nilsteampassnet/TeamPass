<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @author    Nils Laumaillé <nils@teamapss.net>
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      https://www.teampass.net
 */
require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] !== 1) {
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
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
$antiXss = new protect\AntiXSS\AntiXSS();

require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';

// init
$ldap_suffix = '';
$result = '';
$adldap = '';

// If Debug then clean the files
if (DEBUGLDAP === true) {
    define('DEBUGLDAPFILE', $SETTINGS['path_to_files_folder'].'/ldap.debug.txt');
    $fp = fopen(DEBUGLDAPFILE, 'w');
    fclose($fp);
}
if (DEBUGDUO === true) {
    define('DEBUGDUOFILE', $SETTINGS['path_to_files_folder'].'/duo.debug.txt');
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
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
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
        FROM '.prefixTable('misc').'
        WHERE type = %s',
        'duoSecurity'
    );
    foreach ($duoData as $value) {
        $_GLOBALS[strtoupper($value['intitule'])] = $value['valeur'];
    }

    // load library
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoSecurity/Duo.php';
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
            "\n\n-----\n\n".
                'sig request : '.$post_login."\n".
                'resp : '.$sig_request."\n"
        );
    }

    // load csrfprotector
    $csrfp_config = include_once $SETTINGS['cpassman_dir'].'/includes/libraries/csrfp/libs/csrfp.config.php';

    // return result
    echo '[{"sig_request" : "'.$sig_request.'" , "csrfp_token" : "'.$csrfp_config['CSRFP_TOKEN'].'" , "csrfp_key" : "'.filter_var($_COOKIE[$csrfp_config['CSRFP_TOKEN']], FILTER_SANITIZE_STRING).'"}]';
// ---
    // ---
} elseif ($post_type === 'identify_duo_user_check') {
    //--------
    // DUO AUTHENTICATION
    // this step is verifying the response received from the server
    //--------

    // load library
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoSecurity/Duo.php';
    $resp = Duo::verifyResponse(
        $_GLOBALS['IKEY'],
        $_GLOBALS['SKEY'],
        $_GLOBALS['AKEY'],
        $post_sig_response
    );

    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        "\n\n-----\n\n".
            'sig response : '.$post_sig_response."\n".
            'resp : '.$resp."\n"
    );

    // return the response (which should be the user name)
    if ($resp === $post_login) {
        // Check if this account exists in Teampass or only in LDAP
        if (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1) {
            // is user in Teampass?
            $data = DB::queryfirstrow(
                'SELECT id
                FROM '.prefixTable('users').'
                WHERE login = %s',
                $post_login
            );

            if (DB::count() === 0) {
                // Get LDAP info for this user
                $ldap_info_user = json_decode(connectLDAP($post_login, $post_pwd, $SETTINGS));

                if ($ldap_info_user->{'user_found'} === true && $ldap_info_user->{'auth_success'} === true) {
                    // load passwordLib library
                    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'].'/includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    // Generate user keys pair
                    $userKeys = generateUserKeys($post_pwd);

                    // save an account in database
                    DB::insert(
                        prefixTable('users'),
                        array(
                            'login' => $post_login,
                            'pw' => $pwdlib->createPasswordHash($post_pwd),
                            'email' => $ldap_info_user->{'email'},
                            'name' => $ldap_info_user->{'name'},
                            'lastname' => $ldap_info_user->{'lastname'},
                            'admin' => '0',
                            'gestionnaire' => '0',
                            'can_manage_all_users' => '0',
                            'personal_folder' => $SETTINGS['enable_pf_feature'] === '1' ? '1' : '0',
                            'fonction_id' => isset($SETTINGS['ldap_new_user_role']) === true ? $SETTINGS['ldap_new_user_role'] : '0',
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
                    }
                }
            }
        }

        echo '[{"resp" : "'.$resp.'"}]';
    } else {
        echo '[{"resp" : "'.$resp.'"}]';
    }
    // ---
    // ---
} elseif ($post_type === 'identify_user') {
    //--------
    // NORMAL IDENTICATION STEP
    //--------

    // decrypt and retreive data in JSON format
    /*$dataReceived = prepareExchangedData(
        $post_data,
        'decode'
    );*/

    // increment counter of login attempts
    if (empty($_SESSION['pwd_attempts'])) {
        $_SESSION['pwd_attempts'] = 1;
    } else {
        ++$_SESSION['pwd_attempts'];
    }

    // manage brute force
    if ($_SESSION['pwd_attempts'] <= 3) {
        // identify the user through Teampass process
        identifyUser(
            $post_data,
            $SETTINGS
        );
    } elseif (isset($_SESSION['next_possible_pwd_attempts']) && time() > $_SESSION['next_possible_pwd_attempts'] && $_SESSION['pwd_attempts'] > 3) {
        $_SESSION['pwd_attempts'] = 1;
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
                'user_admin' => isset($_SESSION['user_admin']) ? /* @scrutinizer ignore-type */ (int) $antiXss->xss_clean($_SESSION['user_admin']) : '',
                'initial_url' => @$_SESSION['initial_url'],
                'pwd_attempts' => /* @scrutinizer ignore-type */ $antiXss->xss_clean($_SESSION['pwd_attempts']),
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
    include_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';

    header('Content-type: text/html; charset=utf-8');
    error_reporting(E_ERROR);
    include_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    // Load superGlobals
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Prepare GET variables
    $session_user_language = $superGlobal->get('user_language', 'SESSION');

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        "Content of data sent '".filter_var($sentData, FILTER_SANITIZE_STRING)."'\n"
    );

    // connect to the server
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
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
    include_once $SETTINGS['cpassman_dir'].'/includes/language/'.$session_user_language.'.php';

    // decrypt and retreive data in JSON format
    $dataReceived = prepareExchangedData($sentData, 'decode', $_SESSION['key']);
    /*echo $sentData;
    echo " ;; ";
    echo $dataReceived;
    print_r($dataReceived);*/

    // prepare variables
    if (isset($SETTINGS['enable_http_request_login']) === true
        && $SETTINGS['enable_http_request_login'] === '1'
        && isset($_SERVER['PHP_AUTH_USER']) === true
        && isset($SETTINGS['maintenance_mode']) === true
        && $SETTINGS['maintenance_mode'] === '1'
    ) {
        if (strpos($_SERVER['PHP_AUTH_USER'], '@') !== false) {
            $username = explode('@', filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING))[0];
        } elseif (strpos($_SERVER['PHP_AUTH_USER'], '\\') !== false) {
            $username = explode('\\', filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING))[1];
        } else {
            $username = filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING);
        }
        $passwordClear = $_SERVER['PHP_AUTH_PW'];
    //$usernameSanitized = '';
    } else {
        $passwordClear = filter_var($dataReceived['pw'], FILTER_SANITIZE_STRING);
        $username = filter_var($dataReceived['login'], FILTER_SANITIZE_STRING);
        //$usernameSanitized = filter_var($dataReceived['login_sanitized'], FILTER_SANITIZE_STRING);
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
                'user_admin' => isset($_SESSION['user_admin']) ? /* @scrutinizer ignore-type */ (int) $antiXss->xss_clean($_SESSION['user_admin']) : '',
                'initial_url' => @$_SESSION['initial_url'],
                'pwd_attempts' => /* @scrutinizer ignore-type */ $antiXss->xss_clean($_SESSION['pwd_attempts']),
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
        "Starting authentication of '".$username."'\n".
        'LDAP status: '.$SETTINGS['ldap_mode']."\n"
    );
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        "Get all LDAP params : \n".
            'mode : '.$SETTINGS['ldap_mode']."\n".
            'type : '.$SETTINGS['ldap_type']."\n".
            'base_dn : '.$SETTINGS['ldap_domain_dn']."\n".
            'search_base : '.$SETTINGS['ldap_search_base']."\n".
            'bind_dn : '.$SETTINGS['ldap_bind_dn']."\n".
            'bind_passwd : '.$SETTINGS['ldap_bind_passwd']."\n".
            'user_attribute : '.$SETTINGS['ldap_user_attribute']."\n".
            'account_suffix : '.$SETTINGS['ldap_suffix']."\n".
            'domain_controllers : '.$SETTINGS['ldap_domain_controler']."\n".
            'ad_port : '.$SETTINGS['ldap_port']."\n".
            'use_ssl : '.$SETTINGS['ldap_ssl']."\n".
            'use_tls : '.$SETTINGS['ldap_tls']."\n*********\n\n"
    );

    // Check if user exists
    $data = DB::queryFirstRow(
        'SELECT *
        FROM '.prefixTable('users').'
        WHERE login=%s',
        $username
    );
    $counter = DB::count();

    // 2.1.27.24 - in case of login encoding error
    /*if ($counter === 0) {
        // Test
        $data = DB::queryFirstRow(
            'SELECT *
            FROM '.prefixTable('users').'
            WHERE login=%s',
            $usernameSanitized
        );
        $counter = DB::count();
        if ($counter === 1) {
            // Adapt in DB
            DB::update(
                prefixTable('users'),
                array(
                    'login' => $username,
                ),
                'id=%i',
                $data['id']
            );
            $data['login'] = $username;
        }
    }*/

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        'USer exists: '.$counter."\n"
    );

    $user_initial_creation_through_ldap = false;
    $proceedIdentification = false;
    $userPasswordVerified = false;
    $ldapConnection = false;
    $logError = array();
    $user_info_from_ad = '';
    $return = '';

    // Prepare LDAP connection if set up
    if (isset($SETTINGS['ldap_mode'])
        && $SETTINGS['ldap_mode'] === '1'
        && $username !== 'admin'
    ) {
        //Multiple Domain Names
        if (strpos(html_entity_decode($username), '\\') === true) {
            $ldap_suffix = '@'.substr(html_entity_decode($username), 0, strpos(html_entity_decode($username), '\\'));
            $username = substr(html_entity_decode($username), strpos(html_entity_decode($username), '\\') + 1);
        }
        if ($SETTINGS['ldap_type'] === 'posix-search') {
            $ret = identifyViaLDAPPosixSearch(
                $data,
                $ldap_suffix,
                $passwordClear,
                $counter,
                $SETTINGS
            );

            if ($ret['error'] === true) {
                echo json_encode($ret['message']);
            } elseif ($ret['proceedIdentification'] !== true && $ret['userInLDAP'] === true) {
                logEvents('failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
                echo prepareExchangedData(
                    array(
                        'value' => '',
                        'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                        'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                        'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                        'error' => 'user_not_exists7',
                        'message' => langHdl('error_bad_credentials'),
                    ),
                    'encode'
                );

                return false;
            } else {
                $ldapConnection = true;
                $user_info_from_ad = $ret['user_info_from_ad'];
                $proceedIdentification = $ret['proceedIdentification'];
            }
        } else {
            $ret = identifyViaLDAPPosix(
                $username,
                $ldap_suffix,
                $passwordClear,
                $counter,
                $SETTINGS
            );

            if ($ret['error'] === true) {
                echo json_encode($ret['message']);
            } elseif ($ret['proceedIdentification'] !== true) {
                logEvents('failed_auth', 'user_not_exists', '', stripslashes($username), stripslashes($username));
                echo prepareExchangedData(
                    array(
                        'value' => '',
                        'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                        'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                        'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                        'error' => 'user_not_exists8',
                        'message' => langHdl('error_bad_credentials'),
                    ),
                    'encode'
                );

                return false;
            } else {
                $auth_username = $ret['auth_username'];
                $proceedIdentification = $ret['proceedIdentification'];
                $user_info_from_ad = $ret['user_info_from_ad'];
            }
        }
    }

    // Check Yubico
    if (isset($SETTINGS['yubico_authentication'])
        && $SETTINGS['yubico_authentication'] === '1'
        && ($data['admin'] !== '1' || ((int) $SETTINGS['admin_2fa_required'] === 1 && $data['admin'] === '1'))
        && $user_2fa_selection === 'yubico'
    ) {
        $ret = yubicoMFACheck(
            $username,
            $ldap_suffix,
            $dataReceived,
            $data,
            $SETTINGS
        );

        if ($ret['error'] === true) {
            echo prepareExchangedData($ret, 'encode');

            return;
        } else {
            $proceedIdentification = $ret['proceedIdentification'];
        }
    }

    // Create new LDAP user if not existing in Teampass
    // Don't create it if option "only localy declared users" is enabled
    if ($counter === 0 && $ldapConnection === true
        && isset($SETTINGS['ldap_elusers']) === true && ($SETTINGS['ldap_elusers'] == 0)
    ) {
        // If LDAP enabled, create user in TEAMPASS if doesn't exist

        /*// Get user info from LDAP
        if ($SETTINGS['ldap_type'] === 'posix-search') {
            //Because we didn't use adLDAP, we need to set the user info from the ldap_get_entries result
            $user_info_from_ad = $result;
        } else {
            $user_info_from_ad = $adldap->user()->info($auth_username, array('mail', 'givenname', 'sn'));
        }*/
        $ret = ldapCreateUser(
            $username,
            $passwordClear,
            $data,
            $user_info_from_ad,
            $SETTINGS
        );

        $proceedIdentification = $ret['proceedIdentification'];
        $user_initial_creation_through_ldap = $ret['user_initial_creation_through_ldap'];
    }

    // Check if user exists (and has been created in case of new LDAP user)
    $data = DB::queryFirstRow(
        'SELECT *
        FROM '.prefixTable('users').'
        WHERE login = %s',
        $username
    );

    /* echo '> '.$username.' - '.DB::count().' ; ';

     return;*/

    if (DB::count() === 0) {
        logEvents('failed_auth', 'user_not_exists', '', $username, $username);
        echo prepareExchangedData(
            array(
                'value' => '',
                'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                'error' => 'user_not_exists1 '.$username,
                'message' => langHdl('error_bad_credentials'),
            ),
            'encode'
        );

        return false;
    }

    // check GA code
    if (isset($SETTINGS['google_authentication']) === true
        && (int) $SETTINGS['google_authentication'] === 1
        && ($username !== 'admin' || ((int) $SETTINGS['admin_2fa_required'] === 1 && $username === 'admin'))
        && $user_2fa_selection === 'google'
    ) {
        $ret = googleMFACheck(
            $username,
            $data,
            $dataReceived,
            $SETTINGS
        );

        if ($ret['error'] === true) {
            logEvents('failed_auth', 'wrong_mfa_code', '', stripslashes($username), stripslashes($username));
            echo prepareExchangedData(
                $ret,
                'encode'
            );

            return;
        } else {
            $proceedIdentification = $ret['proceedIdentification'];
            $user_initial_creation_through_ldap = $ret['user_initial_creation_through_ldap'];

            // Manage 1st usage of Google MFA
            if (count($ret['firstTime']) > 0) {
                echo prepareExchangedData(
                    $ret['firstTime'],
                    'encode'
                );

                return;
            }
        }
    } elseif ($counter > 0) {
        $proceedIdentification = true;
    }

    // Debug
    debugIdentify(
        DEBUGDUO,
        DEBUGDUOFILE,
        'Proceed with Ident: '.$proceedIdentification."\n"
    );

    /*
    // check AGSES code
    if (isset($SETTINGS['agses_authentication_enabled']) === true
        && $SETTINGS['agses_authentication_enabled'] === '1'
        && ($username !== 'admin' || ((int) $SETTINGS['admin_2fa_required'] === 1 && $username === 'admin'))
        && $user_2fa_selection === 'agses'
        && empty($user_agses_code) === false
    ) {
        // load AGSES
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/agses/axs/AXSILPortal_V1_Auth.php';
        $agses = new AXSILPortal_V1_Auth();
        $agses->setUrl($SETTINGS['agses_hosted_url']);
        $agses->setAAId($SETTINGS['agses_hosted_id']);
        //for release there will be another api-key - this is temporary only
        $agses->setApiKey($SETTINGS['agses_hosted_apikey']);
        $agses->create();
        //create random salt and store it into session
        if (!isset($_SESSION['hedgeId']) || $_SESSION['hedgeId'] == '') {
            $_SESSION['hedgeId'] = md5(time());
        }

        $responseCode = $user_agses_code;
        if ($responseCode != '' && strlen($responseCode) >= 4) {
            // Verify response code, store result in session
            $result = $agses->verifyResponse(
                (string) $_SESSION['user_settings']['agses-usercardid'],
                $responseCode,
                (string) $_SESSION['hedgeId']
            );

            if ($result == 1) {
                $return = '';
                $proceedIdentification = true;
                $userPasswordVerified = false;
                unset($_SESSION['hedgeId']);
                unset($_SESSION['flickercode']);
            } else {
                if ($result < -10) {
                    $logError = array(
                        'error' => 'agses_error',
                        'message' => 'ERROR: '.$result,
                    );
                } elseif ($result == -4) {
                    $logError = array(
                        'error' => 'agses_error',
                        'message' => 'Wrong response code, no more tries left.',
                    );
                } elseif ($result == -3) {
                    $logError = array(
                        'error' => 'agses_error',
                        'message' => 'Wrong response code, try to reenter.',
                    );
                } elseif ($result == -2) {
                    $logError = array(
                        'error' => 'agses_error',
                        'message' => 'Timeout. The response code is not valid anymore.',
                    );
                } elseif ($result == -1) {
                    $logError = array(
                        'error' => 'agses_error',
                        'message' => 'Security Error. Did you try to verify the response from a different computer?',
                    );
                } elseif ($result == 1) {
                    $logError = array(
                        'error' => 'agses_error',
                        'message' => 'Authentication successful, response code correct.<br /><br />Authentification Method for SecureBrowser updated!',
                    );
                }
                echo json_encode(
                    array(
                        'value' => '',
                        'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                        'initial_url' => @$_SESSION['initial_url'],
                        'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                        'error' => $logError['error'],
                        'message' => $logError['message'],
                    )
                );

                return;
            }
        } else {
            echo json_encode(
                array(
                    'value' => '',
                    'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                    'initial_url' => @$_SESSION['initial_url'],
                    'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                    'error' => 'agses_error',
                    'message' => 'No response code given',
                )
            );

            return;
        }
    }
    */

    // If admin user then check if folder install exists
    // if yes then refuse connection
    if ((int) $data['admin'] === 1 && is_dir('../install') === true) {
        echo prepareExchangedData(
            array(
                'value' => '',
                'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                'initial_url' => @$_SESSION['initial_url'],
                'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                'error' => true,
                'message' => 'Install folder has to be removed!',
            ),
            'encode'
        );

        return false;
    }

    if ($proceedIdentification === true) {
        // Check user and password
        if (checkCredentials($passwordClear, $data, $dataReceived, $username, $SETTINGS) !== true) {
            echo prepareExchangedData(
                array(
                    'value' => '',
                    'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                    'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                    'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                    'error' => 'user_not_exists2',
                    'message' => langHdl('error_bad_credentials'),
                ),
                'encode'
            );

            return false;
        } else {
            $userPasswordVerified = true;
        }

        // Debug
        debugIdentify(
            DEBUGDUO,
            DEBUGDUOFILE,
            "User's password verified: ".$userPasswordVerified."\n"
        );

        // Can connect if
        // 1- no LDAP mode + user enabled + pw ok
        // 2- LDAP mode + user enabled + ldap connection ok + user is not admin
        // 3-  LDAP mode + user enabled + pw ok + usre is admin
        // This in order to allow admin by default to connect even if LDAP is activated
        if ((isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 0
            && $userPasswordVerified === true && (int) $data['disabled'] === 0)
            || (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1
            && $ldapConnection === true && (int) $data['disabled'] === 0 && $username !== 'admin')
            || (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 2
            && $ldapConnection === true && (int) $data['disabled'] === 0 && $username !== 'admin')
            || (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1
            && $username == 'admin' && $userPasswordVerified === true && (int) $data['disabled'] === 0)
            || (isset($SETTINGS['ldap_and_local_authentication']) === true && $SETTINGS['ldap_and_local_authentication'] === '1'
            && isset($SETTINGS['ldap_mode']) === true && in_array($SETTINGS['ldap_mode'], array('1', '2')) === true
            && $userPasswordVerified === true && (int) $data['disabled'] === 0)
        ) {
            $_SESSION['autoriser'] = true;
            $_SESSION['pwd_attempts'] = 0;

            // Generate a ramdom ID
            //$key = GenerateCryptKey(50, false, true, true, false);

            // Debug
            debugIdentify(
                DEBUGDUO,
                DEBUGDUOFILE,
                "User's token: ".$key."\n"
            );

            // Check if any unsuccessfull login tries exist
            $arrAttempts = array();
            $rows = DB::query(
                'SELECT date
                FROM '.prefixTable('log_system')."
                WHERE field_1 = %s
                AND type = 'failed_auth'
                AND label = 'user_password_not_correct'
                AND date >= %s AND date < %s",
                $data['login'],
                $data['last_connexion'],
                time()
            );
            $arrAttempts['nb'] = DB::count();
            $arrAttempts['shown'] = false;
            $arrAttempts['attempts'] = array();
            if (DB::count() > 0) {
                foreach ($rows as $record) {
                    array_push(
                        $arrAttempts['attempts'],
                        date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['date'])
                    );
                }
            }
            $_SESSION['unsuccessfull_login_attempts'] = $arrAttempts;

            // Log into DB the user's connection
            if (isset($SETTINGS['log_connections']) === true
                && (int) $SETTINGS['log_connections'] === 1
            ) {
                logEvents('user_connection', 'connection', $data['id'], stripslashes($username));
            }
            // Save account in SESSION
            $_SESSION['login'] = stripslashes($username);
            $_SESSION['name'] = stripslashes($data['name']);
            $_SESSION['lastname'] = stripslashes($data['lastname']);
            $_SESSION['user_id'] = $data['id'];
            $_SESSION['user_admin'] = $data['admin'];
            $_SESSION['user_manager'] = $data['gestionnaire'];
            $_SESSION['user_can_manage_all_users'] = $data['can_manage_all_users'];
            $_SESSION['user_read_only'] = $data['read_only'];
            $_SESSION['last_pw_change'] = $data['last_pw_change'];
            $_SESSION['last_pw'] = $data['last_pw'];
            $_SESSION['can_create_root_folder'] = $data['can_create_root_folder'];
            //$_SESSION['key'] = $key;
            $_SESSION['personal_folder'] = $data['personal_folder'];
            $_SESSION['user_language'] = $data['user_language'];
            $_SESSION['user_email'] = $data['email'];
            $_SESSION['user_ga'] = $data['ga'];
            $_SESSION['user_avatar'] = $data['avatar'];
            $_SESSION['user_avatar_thumb'] = $data['avatar_thumb'];
            $_SESSION['user_upgrade_needed'] = $data['upgrade_needed'];
            $_SESSION['user_force_relog'] = $data['force-relog'];
            // get personal settings
            if (!isset($data['treeloadstrategy']) || empty($data['treeloadstrategy'])) {
                $data['treeloadstrategy'] = 'full';
            }
            $_SESSION['user_settings']['treeloadstrategy'] = $data['treeloadstrategy'];
            $_SESSION['user_settings']['agses-usercardid'] = $data['agses-usercardid'];
            $_SESSION['user_settings']['user_language'] = $data['user_language'];
            $_SESSION['user_settings']['encrypted_psk'] = $data['encrypted_psk'];
            $_SESSION['user_settings']['usertimezone'] = $data['usertimezone'];
            $_SESSION['user_settings']['session_duration'] = $dataReceived['duree_session'] * 60;
            $_SESSION['user_settings']['api-key'] = $data['user_api_key'];

            // manage session expiration
            $_SESSION['sessionDuration'] = (int) (time() + $_SESSION['user_settings']['session_duration']);

            /*
            * CHECK PASSWORD VALIDITY
            * Don't take into consideration if LDAP in use
            */
            if (isset($SETTINGS['ldap_mode']) === true && $SETTINGS['ldap_mode'] === '1') {
                $_SESSION['validite_pw'] = true;
                $_SESSION['last_pw_change'] = true;
            } else {
                if (isset($data['last_pw_change']) === true) {
                    if ($SETTINGS['pw_life_duration'] === '0') {
                        $_SESSION['numDaysBeforePwExpiration'] = 'infinite';
                        $_SESSION['validite_pw'] = true;
                    } else {
                        $_SESSION['numDaysBeforePwExpiration'] = $SETTINGS['pw_life_duration'] - round(
                            (mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')) - $_SESSION['last_pw_change']) / (24 * 60 * 60)
                        );
                        if ($_SESSION['numDaysBeforePwExpiration'] <= 0) {
                            $_SESSION['validite_pw'] = false;
                        } else {
                            $_SESSION['validite_pw'] = true;
                        }
                    }
                } else {
                    $_SESSION['validite_pw'] = false;
                }
            }

            /* If this option is set user password MD5 is used as personal SALTKey */
            if (isset($SETTINGS['use_md5_password_as_salt']) &&
                $SETTINGS['use_md5_password_as_salt'] == 1
            ) {
                $_SESSION['user_settings']['clear_psk'] = md5($passwordClear);
                $tmp = encrypt($_SESSION['user_settings']['clear_psk'], '');
                if ($tmp !== false) {
                    setcookie(
                        'TeamPass_PFSK_'.md5($_SESSION['user_id']),
                        $tmp,
                        time() + 60 * 60 * 24 * $SETTINGS['personal_saltkey_cookie_duration'],
                        '/'
                    );
                }
            }

            if (empty($data['last_connexion'])) {
                $_SESSION['last_connection'] = time();
            } else {
                $_SESSION['last_connection'] = $data['last_connexion'];
            }

            if (!empty($data['latest_items'])) {
                $_SESSION['latest_items'] = explode(';', $data['latest_items']);
            } else {
                $_SESSION['latest_items'] = array();
            }
            if (!empty($data['favourites'])) {
                $_SESSION['favourites'] = explode(';', $data['favourites']);
            } else {
                $_SESSION['favourites'] = array();
            }

            if (!empty($data['groupes_visibles'])) {
                $_SESSION['groupes_visibles'] = implode(';', $data['groupes_visibles']);
            } else {
                $_SESSION['groupes_visibles'] = array();
            }
            if (!empty($data['groupes_interdits'])) {
                $_SESSION['no_access_folders'] = implode(';', $data['groupes_interdits']);
            } else {
                $_SESSION['no_access_folders'] = array();
            }
            // User's roles
            $_SESSION['fonction_id'] = $data['fonction_id'];
            $_SESSION['user_roles'] = array_filter(explode(',', $data['fonction_id']));

            // build array of roles
            $_SESSION['user_pw_complexity'] = 0;
            $_SESSION['arr_roles'] = array();
            foreach ($_SESSION['user_roles'] as $role) {
                $resRoles = DB::queryFirstRow(
                    'SELECT title, complexity
                    FROM '.prefixTable('roles_title').'
                    WHERE id=%i',
                    $role
                );
                $_SESSION['arr_roles'][$role] = array(
                    'id' => $role,
                    'title' => $resRoles['title'],
                );
                // get highest complexity
                if (intval($_SESSION['user_pw_complexity']) < intval($resRoles['complexity'])) {
                    $_SESSION['user_pw_complexity'] = $resRoles['complexity'];
                }
            }

            // build complete array of roles
            $_SESSION['arr_roles_full'] = array();
            $rows = DB::query('SELECT id, title FROM '.prefixTable('roles_title').' ORDER BY title ASC');
            foreach ($rows as $record) {
                $_SESSION['arr_roles_full'][$record['id']] = array(
                        'id' => $record['id'],
                        'title' => $record['title'],
                );
            }
            // Set some settings
            $_SESSION['user']['find_cookie'] = false;
            $SETTINGS['update_needed'] = '';

            // User signature keys
            $_SESSION['user']['public_key'] = $data['public_key'];
            if (is_null($data['private_key']) === true || empty($data['private_key']) === true || $data['private_key'] === 'none') {
                // No keys have been generated yet
                // Create them
                $userKeys = generateUserKeys($passwordClear);
                $_SESSION['user']['public_key'] = $userKeys['public_key'];
                $_SESSION['user']['private_key'] = $userKeys['private_key_clear'];
                $arrayUserKeys = array(
                    'public_key' => $userKeys['public_key'],
                    'private_key' => $userKeys['private_key'],
                );
            } else {
                // Uncrypt private key
                $_SESSION['user']['private_key'] = decryptPrivateKey($passwordClear, $data['private_key']);
                $arrayUserKeys = [];
            }

            // Update table
            DB::update(
                prefixTable('users'),
                array_merge(
                    array(
                        'key_tempo' => $_SESSION['key'],
                        'last_connexion' => time(),
                        'timestamp' => time(),
                        'disabled' => 0,
                        'no_bad_attempts' => 0,
                        'session_end' => $_SESSION['sessionDuration'],
                        'user_ip' => $dataReceived['client'],
                    ),
                    $arrayUserKeys
                ),
                'id=%i',
                $data['id']
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
                    implode(';', $data['groupes_visibles']),
                    $_SESSION['no_access_folders'],
                    $data['admin'],
                    $data['fonction_id'],
                    $SETTINGS
                );
            } else {
                // is new LDAP user. Show only his personal folder
                if ($SETTINGS['enable_pf_feature'] === '1') {
                    $_SESSION['personal_visible_groups'] = array($data['id']);
                    $_SESSION['personal_folders'] = array($data['id']);
                } else {
                    $_SESSION['personal_visible_groups'] = array();
                    $_SESSION['personal_folders'] = array();
                }
                $_SESSION['all_non_personal_folders'] = array();
                $_SESSION['groupes_visibles'] = array();
                $_SESSION['read_only_folders'] = array();
                $_SESSION['list_folders_limited'] = '';
                $_SESSION['list_folders_editable_by_role'] = array();
                $_SESSION['list_restricted_folders_for_items'] = array();
                $_SESSION['nb_folders'] = 1;
                $_SESSION['nb_roles'] = 0;
            }
            // Get some more elements
            $_SESSION['screenHeight'] = $dataReceived['screenHeight'];
            // Get last seen items
            $_SESSION['latest_items_tab'][] = '';
            foreach ($_SESSION['latest_items'] as $item) {
                if (!empty($item)) {
                    $dataLastItems = DB::queryFirstRow(
                        'SELECT id,label,id_tree
                        FROM '.prefixTable('items').'
                        WHERE id=%i',
                        $item
                    );
                    $_SESSION['latest_items_tab'][$item] = array(
                        'id' => $item,
                        'label' => $dataLastItems['label'],
                        'url' => 'index.php?page=items&amp;group='.$dataLastItems['id_tree'].'&amp;id='.$item,
                    );
                }
            }
            // send back the random key
            $return = $dataReceived['randomstring'];
            // Send email
            if (isset($SETTINGS['enable_send_email_on_user_login'])
                && (int) $SETTINGS['enable_send_email_on_user_login'] === 1
                && (int) $_SESSION['user_admin'] !== 1
            ) {
                // get all Admin users
                $receivers = '';
                $rows = DB::query('SELECT email FROM '.prefixTable('users')." WHERE admin = %i and email != ''", 1);
                foreach ($rows as $record) {
                    if (empty($receivers)) {
                        $receivers = $record['email'];
                    } else {
                        $receivers = ','.$record['email'];
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
                                ' '.$_SESSION['login'].' (IP: '.getClientIpServer().')',
                                date($SETTINGS['date_format'], $_SESSION['last_connection']),
                                date($SETTINGS['time_format'], $_SESSION['last_connection']),
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
            $_SESSION['initial_url'] = '';
            if ($SETTINGS['cpassman_dir'] === '..') {
                $SETTINGS['cpassman_dir'] = '.';
            }
            */
        } elseif ((int) $data['disabled'] === 1) {
            // User and password is okay but account is locked
            echo prepareExchangedData(
                array(
                    'value' => $return,
                    'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : '',
                    'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                    'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                    'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                    'error' => 'user_is_locked',
                    'message' => langHdl('account_is_locked'),
                    'first_connection' => $_SESSION['validite_pw'] === false ? true : false,
                    'password_complexity' => TP_PW_COMPLEXITY[$_SESSION['user_pw_complexity']][1],
                    'password_change_expected' => $data['special'] === 'password_change_expected' ? true : false,
                    'private_key_conform' => isset($_SESSION['user']['private_key']) === true
                        && empty($_SESSION['user']['private_key']) === false
                        && $_SESSION['user']['private_key'] !== 'none' ? true : false,
                    'session_key' => $_SESSION['key'],
                    'has_psk' => empty($_SESSION['user_settings']['encrypted_psk']) === false ? true : false,
                    //'debug' => $_SESSION['user']['private_key'],
                    //'action_on_login' => isset($data['special']) === true ? base64_encode($data['special']) : '',
                ),
                'encode'
            );

            return false;
        } else {
            // User exists in the DB but Password is false
            // check if user is locked
            $userIsLocked = false;
            $nbAttempts = intval($data['no_bad_attempts'] + 1);
            if ($SETTINGS['nb_bad_authentication'] > 0
                && intval($SETTINGS['nb_bad_authentication']) < $nbAttempts
            ) {
                $userIsLocked = true;
                // log it
                if (isset($SETTINGS['log_connections']) === true
                    && (int) $SETTINGS['log_connections'] === 1
                ) {
                    logEvents('user_locked', 'connection', $data['id'], stripslashes($username));
                }
            }
            DB::update(
                prefixTable('users'),
                array(
                    'key_tempo' => $_SESSION['key'],
                    'disabled' => $userIsLocked,
                    'no_bad_attempts' => $nbAttempts,
                ),
                'id=%i',
                $data['id']
            );
            // What return shoulb we do
            if ($userIsLocked === true) {
                echo prepareExchangedData(
                    array(
                        'value' => $return,
                        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : '',
                        'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                        'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                        'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                        'error' => 'user_is_locked',
                        'message' => langHdl('account_is_locked'),
                        'first_connection' => $_SESSION['validite_pw'] === false ? true : false,
                        'password_complexity' => TP_PW_COMPLEXITY[$_SESSION['user_pw_complexity']][1],
                        'password_change_expected' => $data['special'] === 'password_change_expected' ? true : false,
                        'private_key_conform' => (isset($_SESSION['user']['private_key']) === true
                            && empty($_SESSION['user']['private_key']) === false
                            && $_SESSION['user']['private_key'] !== 'none') ? true : false,
                        'session_key' => $_SESSION['key'],
                        'has_psk' => empty($_SESSION['user_settings']['encrypted_psk']) === false ? true : false,
                        //'debug' => $_SESSION['user']['private_key'],
                        //'action_on_login' => isset($data['special']) === true ? base64_encode($data['special']) : '',
                    ),
                    'encode'
                );

                return false;
            /*} elseif ($SETTINGS['nb_bad_authentication'] === '0') {
                return json_encode(
                    array(
                        'value' => $return,
                        'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                        'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                        'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                        'error' => 'user_not_exists',
                        'message' => langHdl('error_bad_credentials'),
                        'first_connection' => $_SESSION['validite_pw'] === false ? true : false,
                        'password_complexity' => TP_PW_COMPLEXITY[$_SESSION['user_pw_complexity']][1],
                    )
                );*/
            } else {
                echo prepareExchangedData(
                    array(
                        'value' => $return,
                        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : '',
                        'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                        'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                        'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                        'error' => 'user_not_exists3',
                        'message' => langHdl('error_bad_credentials'),
                        'first_connection' => $_SESSION['validite_pw'] === false ? true : false,
                        'password_complexity' => TP_PW_COMPLEXITY[$_SESSION['user_pw_complexity']][1],
                        'password_change_expected' => $data['special'] === 'password_change_expected' ? true : false,
                        'private_key_conform' => isset($_SESSION['user']['private_key']) === true
                            && empty($_SESSION['user']['private_key']) === false
                            && $_SESSION['user']['private_key'] !== 'none' ? true : false,
                        'session_key' => $_SESSION['key'],
                        'has_psk' => empty($_SESSION['user_settings']['encrypted_psk']) === false ? true : false,
                        //'debug' => $_SESSION['user']['private_key'],
                        //'action_on_login' => isset($data['special']) === true ? base64_encode($data['special']) : '',
                    ),
                    'encode'
                );

                return false;
            }
        }
    } else {
        // manage bruteforce
        if ($_SESSION['pwd_attempts'] > 2) {
            $_SESSION['next_possible_pwd_attempts'] = time() + 10;
        }

        if ($user_initial_creation_through_ldap === true) {
            $return = 'new_ldap_account_created';
        } else {
            echo prepareExchangedData(
                array(
                    'value' => $return,
                    'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : '',
                    'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : '',
                    'initial_url' => isset($_SESSION['initial_url']) === true ? $_SESSION['initial_url'] : '',
                    'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                    'error' => 'user_not_exists4',
                    'message' => langHdl('error_bad_credentials'),
                    'first_connection' => $_SESSION['validite_pw'] === false ? true : false,
                    'password_complexity' => TP_PW_COMPLEXITY[$_SESSION['user_pw_complexity']][1],
                    'password_change_expected' => $data['special'] === 'password_change_expected' ? true : false,
                    'private_key_conform' => isset($_SESSION['user']['private_key']) === true
                        && empty($_SESSION['user']['private_key']) === false
                        && $_SESSION['user']['private_key'] !== 'none' ? true : false,
                    'session_key' => $_SESSION['key'],
                    'has_psk' => empty($_SESSION['user_settings']['encrypted_psk']) === false ? true : false,
                    //'debug' => $_SESSION['user']['private_key'],
                    //'action_on_login' => isset($data['special']) === true ? base64_encode($data['special']) : '',
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
        "\n\n----\n".
        'Identified : '.filter_var($return, FILTER_SANITIZE_STRING)."\n\n"
    );

    echo prepareExchangedData(
        array(
            'value' => $return,
            'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : '',
            'user_admin' => isset($_SESSION['user_admin']) ? /* @scrutinizer ignore-type */ (int) $antiXss->xss_clean($_SESSION['user_admin']) : '',
            'initial_url' => @$_SESSION['initial_url'],
            'pwd_attempts' => /* @scrutinizer ignore-type */ $antiXss->xss_clean($_SESSION['pwd_attempts']),
            'error' => false,
            'message' => '',
            'first_connection' => $_SESSION['validite_pw'] === false ? true : false,
            'password_complexity' => TP_PW_COMPLEXITY[$_SESSION['user_pw_complexity']][1],
            'password_change_expected' => $data['special'] === 'password_change_expected' ? true : false,
            'private_key_conform' => isset($_SESSION['user']['private_key']) === true
                && empty($_SESSION['user']['private_key']) === false
                && $_SESSION['user']['private_key'] !== 'none' ? true : false,
            'session_key' => $_SESSION['key'],
            'has_psk' => empty($_SESSION['user_settings']['encrypted_psk']) === false ? true : false,
            //'action_on_login' => isset($data['special']) === true ? base64_encode($data['special']) : '',
        ),
        'encode'
    );
}

/**
 * Undocumented function.
 *
 * @param array  $data          Username
 * @param string $ldap_suffix   Suffix
 * @param string $passwordClear Password
 * @param int    $counter       User exists in teampass
 * @param array  $SETTINGS      Teampass settings
 *
 * @return array
 */
function identifyViaLDAPPosixSearch($data, $ldap_suffix, $passwordClear, $counter, $SETTINGS)
{
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'].'/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    $ldapConnection = false;
    $ldapURIs = '';
    foreach (explode(',', $SETTINGS['ldap_domain_controler']) as $domainControler) {
        if ($SETTINGS['ldap_ssl'] == 1) {
            $ldapURIs .= 'ldaps://'.$domainControler.':'.$SETTINGS['ldap_port'].' ';
        } else {
            $ldapURIs .= 'ldap://'.$domainControler.':'.$SETTINGS['ldap_port'].' ';
        }
    }

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        'LDAP URIs : '.$ldapURIs."\n\n"
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
        'LDAP connection : '.($ldapconn ? 'Connected' : 'Failed')."\n\n"
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
                'LDAP bind : '.($ldapbind ? 'Bound' : 'Failed')."\n\n"
            );
        } else {
            $ldapbind = false;
        }
        if (($SETTINGS['ldap_bind_dn'] === '' && $SETTINGS['ldap_bind_passwd'] === '') || $ldapbind === true) {
            $filter = '(&('.$SETTINGS['ldap_user_attribute'].'='.$data['login'].')(objectClass='.$SETTINGS['ldap_object_class'].'))';
            $result = ldap_search(
                $ldapconn,
                $SETTINGS['ldap_search_base'],
                $filter,
                array('dn', 'mail', 'givenname', 'sn', 'samaccountname', 'shadowexpire')
            );

            // Debug
            debugIdentify(
                DEBUGLDAP,
                DEBUGLDAPFILE,
                'Search filter : '.$filter."\n".
                'Results : '.print_r(ldap_get_entries($ldapconn, $result), true)."\n\n"
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
                    'User was found. '.$user_dn.'\n'
                );

                // Check shadowexpire attribute - if === 1 then user disabled
                if (isset($result[0]['shadowexpire'][0]) === true && $result[0]['shadowexpire'][0] === '1') {
                    return array(
                        'error' => true,
                        'message' => array(
                            'value' => '',
                            'user_admin' => isset($_SESSION['user_admin']) ? /* @scrutinizer ignore-type */ (int) $antiXss->xss_clean($_SESSION['user_admin']) : '',
                            'initial_url' => @$_SESSION['initial_url'],
                            'pwd_attempts' => /* @scrutinizer ignore-type */ $antiXss->xss_clean($_SESSION['pwd_attempts']),
                            'error' => 'user_not_exists5',
                            'message' => langHdl('error_bad_credentials'),
                        ),
                    );
                }

                // Should we restrain the search in specified user groups
                $GroupRestrictionEnabled = false;
                if (isset($SETTINGS['ldap_usergroup']) === true && empty($SETTINGS['ldap_usergroup']) === false) {
                    // New way to check User's group membership
                    $filter_group = 'memberUid='.$data['login'];
                    $result_group = ldap_search(
                        $ldapconn,
                        $SETTINGS['ldap_search_base'],
                        $filter_group,
                        array('dn', 'samaccountname')
                    );

                    if ($result_group) {
                        $entries = ldap_get_entries($ldapconn, $result_group);

                        // Debug
                        debugIdentify(
                            DEBUGLDAP,
                            DEBUGLDAPFILE,
                            'Search groups appartenance : '.$SETTINGS['ldap_search_base']."\n".
                                'Results : '.print_r($entries, true)."\n"
                        );

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

                    // Debug
                    debugIdentify(
                        DEBUGLDAP,
                        DEBUGLDAPFILE,
                        'Group was found : '.var_export($GroupRestrictionEnabled, true)."\n"
                    );
                }

                // Is user in the LDAP?
                if ($GroupRestrictionEnabled === true
                    || ($GroupRestrictionEnabled === false
                    && (isset($SETTINGS['ldap_usergroup']) === false
                    || (isset($SETTINGS['ldap_usergroup']) === true
                    && empty($SETTINGS['ldap_usergroup']) === true)))
                ) {
                    // Try to auth inside LDAP
                    $ldapbind = ldap_bind($ldapconn, $user_dn, $passwordClear);
                    if ($ldapbind === true) {
                        $ldapConnection = true;

                        // Update user's password
                        $data['pw'] = $pwdlib->createPasswordHash($passwordClear);

                        // Do things if user exists in TP
                        if ($counter > 0) {
                            // Update pwd in TP database
                            DB::update(
                                prefixTable('users'),
                                array(
                                    'pw' => $data['pw'],
                                    'login' => $data['login'],
                                ),
                                'id = %i',
                                $data['id']
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
                                'login' => $data['login'],
                            ),
                            'id = %i',
                            $data['id']
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
    );
}

/**
 * Undocumented function.
 *
 * @param string $data          Username
 * @param string $ldap_suffix   Suffix
 * @param string $passwordClear Password
 * @param int    $counter       User exists in teampass
 * @param array  $SETTINGS      Teampass settings
 *
 * @return array
 */
function identifyViaLDAPPosix($data, $ldap_suffix, $passwordClear, $counter, $SETTINGS)
{
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'].'/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        "Get all ldap params : \n".
        'base_dn : '.$SETTINGS['ldap_domain_dn']."\n".
        'account_suffix : '.$SETTINGS['ldap_suffix']."\n".
        'domain_controllers : '.$SETTINGS['ldap_domain_controler']."\n".
        'ad_port : '.$SETTINGS['ldap_port']."\n".
        'use_ssl : '.$SETTINGS['ldap_ssl']."\n".
        'use_tls : '.$SETTINGS['ldap_tls']."\n*********\n\n"
    );

    $adldap = new SplClassLoader('adLDAP', '../includes/libraries/LDAP');
    $adldap->register();
    $ldap_suffix = '';
    $ldapConnection = false;

    // Posix style LDAP handles user searches a bit differently
    if ($SETTINGS['ldap_type'] === 'posix') {
        $ldap_suffix = ','.$SETTINGS['ldap_suffix'].','.$SETTINGS['ldap_domain_dn'];
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
        'Create new adldap object : '.$adldap->getLastError()."\n\n\n"
    );

    // OpenLDAP expects an attribute=value pair
    if ($SETTINGS['ldap_type'] === 'posix') {
        $auth_username = $SETTINGS['ldap_user_attribute'].'='.$data['login'];
    } else {
        $auth_username = $data['login'];
    }

    // Authenticate the user
    if ($adldap->authenticate($auth_username, html_entity_decode($passwordClear))) {
        // Is user in allowed group
        if (isset($SETTINGS['ldap_allowed_usergroup']) === true
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
                'user_admin' => isset($_SESSION['user_admin']) ? /* @scrutinizer ignore-type */ (int) $antiXss->xss_clean($_SESSION['user_admin']) : '',
                'initial_url' => @$_SESSION['initial_url'],
                'pwd_attempts' => /* @scrutinizer ignore-type */ $antiXss->xss_clean($_SESSION['pwd_attempts']),
                'error' => 'user_not_exists6',
                'message' => langHdl('error_bad_credentials'),
            );
        }

        // Update user's password
        if ($ldapConnection === true) {
            $data['pw'] = $pwdlib->createPasswordHash($passwordClear);

            // Do things if user exists in TP
            if ($counter > 0) {
                // Update pwd in TP database
                DB::update(
                    prefixTable('users'),
                    array(
                        'pw' => $data['pw'],
                        'login' => $data['login'],
                    ),
                    'id = %i',
                    $data['id']
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
                'login' => $data['login'],
            ),
            'id = %i',
            $data['id']
        );

        $ldapConnection = false;
    }

    // Debug
    debugIdentify(
        DEBUGLDAP,
        DEBUGLDAPFILE,
        'After authenticate : '.$adldap->getLastError()."\n\n\n".
        'ldap status : '.$ldapConnection."\n\n\n"
    );

    return array(
        'error' => false,
        'message' => $ldapConnection,
        'auth_username' => $auth_username,
        'proceedIdentification' => $proceedIdentification,
        'user_info_from_ad' => $adldap->user()->info($auth_username, array('mail', 'givenname', 'sn')),
    );
}

/**
 * Undocumented function.
 *
 * @param string       $username     Username
 * @param string       $ldap_suffix  Suffix
 * @param string|array $dataReceived Received data
 * @param string       $data         Result of query
 * @param array        $SETTINGS     Teampass settings
 *
 * @return array
 */
function yubicoMFACheck($username, $ldap_suffix, $dataReceived, $data, $SETTINGS)
{
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

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
            $data['id']
        );
    } else {
        // Check existing yubico credentials
        if ($data['yubico_user_key'] === 'none' || $data['yubico_user_id'] === 'none') {
            return array(
                'error' => true,
                'value' => '',
                'user_admin' => isset($_SESSION['user_admin']) ? /* @scrutinizer ignore-type */ (int) $antiXss->xss_clean($_SESSION['user_admin']) : '',
                'initial_url' => @$_SESSION['initial_url'],
                'pwd_attempts' => /* @scrutinizer ignore-type */ $antiXss->xss_clean($_SESSION['pwd_attempts']),
                'error' => 'no_user_yubico_credentials',
                'message' => '',
            );
        } else {
            $yubico_user_key = $data['yubico_user_key'];
            $yubico_user_id = $data['yubico_user_id'];
        }
    }

    // Now check yubico validity
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/Yubico/Yubico.php';
    $yubi = new Auth_Yubico($yubico_user_id, $yubico_user_key);
    $auth = $yubi->verify($yubico_key); //, null, null, null, 60

    if (PEAR::isError($auth)) {
        $proceedIdentification = false;

        return array(
            'error' => true,
            'value' => '',
            'user_admin' => isset($_SESSION['user_admin']) ? /* @scrutinizer ignore-type */ (int) $antiXss->xss_clean($_SESSION['user_admin']) : '',
            'initial_url' => @$_SESSION['initial_url'],
            'pwd_attempts' => /* @scrutinizer ignore-type */ $antiXss->xss_clean($_SESSION['pwd_attempts']),
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
 * @param string $username          Username
 * @param string $passwordClear     User password in clear
 * @param string $data              Result of query
 * @param string $user_info_from_ad Received data
 * @param array  $SETTINGS          Teampass settings
 *
 * @return array
 */
function ldapCreateUser($username, $passwordClear, $data, $user_info_from_ad, $SETTINGS)
{
    // Generate user keys pair
    $userKeys = generateUserKeys($passwordClear);

    // Insert user in DB
    DB::insert(
        prefixTable('users'),
        array(
            'login' => $username,
            'pw' => $data['pw'],
            'email' => (isset($user_info_from_ad[0]['mail'][0]) === false) ? '' : $user_info_from_ad[0]['mail'][0],
            'name' => $user_info_from_ad[0]['givenname'][0],
            'lastname' => $user_info_from_ad[0]['sn'][0],
            'admin' => '0',
            'gestionnaire' => '0',
            'can_manage_all_users' => '0',
            'personal_folder' => $SETTINGS['enable_pf_feature'] === '1' ? '1' : '0',
            'fonction_id' => isset($SETTINGS['ldap_new_user_role']) === true ? $SETTINGS['ldap_new_user_role'] : '0',
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
        $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
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
 * @param string       $data         Result of query
 * @param string|array $dataReceived DataReceived
 * @param array        $SETTINGS     Teampass settings
 *
 * @return array
 */
function googleMFACheck($username, $data, $dataReceived, $SETTINGS)
{
    if (isset($dataReceived['GACode']) === true
        && empty($dataReceived['GACode']) === false
    ) {
        // load library
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php';

        // create new instance
        $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($SETTINGS['ga_website_name']);

        // Init
        $firstTime = array();

        // now check if it is the 1st time the user is using 2FA
        if ($data['ga_temporary_code'] !== 'none' && $data['ga_temporary_code'] !== 'done') {
            if ($data['ga_temporary_code'] !== $dataReceived['GACode']) {
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
                'Teampass - '.$username,
                $data['ga']
            );

            // clear temporary code from DB
            DB::update(
                prefixTable('users'),
                array(
                    'ga_temporary_code' => 'done',
                ),
                'id=%i',
                $data['id']
            );

            $firstTime = array(
                'value' => '<img src="'.$new_2fa_qr.'">',
                'user_admin' => isset($_SESSION['user_admin']) ? (int) $_SESSION['user_admin'] : 0,
                'initial_url' => @$_SESSION['initial_url'],
                'pwd_attempts' => (int) $_SESSION['pwd_attempts'],
                'error' => false,
                'message' => $mfaMessage,
                'mfaStatus' => $mfaStatus,
            );
        } else {
            // verify the user GA code
            if ($tfa->verifyCode($data['ga'], $dataReceived['GACode'])) {
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
 * @param array|string $data          Array of user data
 * @param array|string $dataReceived  Received data
 * @param string       $username      User name
 * @param array        $SETTINGS      Teampass settings
 *
 * @return bool
 */
function checkCredentials($passwordClear, $data, $dataReceived, $username, $SETTINGS)
{
    // Set to false
    $userPasswordVerified = false;

    // load passwordLib library
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'].'/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    // Check if old encryption used
    if (crypt($passwordClear, $data['pw']) === $data['pw']
        && empty($data['pw']) === false
    ) {
        $userPasswordVerified = true;

        //update user's password
        $data['pw'] = $pwdlib->createPasswordHash($passwordClear);
        DB::update(
            prefixTable('users'),
            array(
                'pw' => $data['pw'],
            ),
            'id=%i',
            $data['id']
        );
    }
    //echo $passwordClear." - ".$data['pw']." - ".$pwdlib->verifyPasswordHash($passwordClear, $data['pw'])." ;; ";
    // check the given password
    if ($userPasswordVerified !== true) {
        if ($pwdlib->verifyPasswordHash($passwordClear, $data['pw']) === true) {
            $userPasswordVerified = true;
        } else {
            // 2.1.27.24 - manage passwords
            if ($pwdlib->verifyPasswordHash(htmlspecialchars_decode($dataReceived['pw']), $data['pw']) === true) {
                // then the auth is correct but needs to be adapted in DB since change of encoding
                $data['pw'] = $pwdlib->createPasswordHash($passwordClear);
                DB::update(
                    prefixTable('users'),
                    array(
                        'pw' => $data['pw'],
                    ),
                    'id=%i',
                    $data['id']
                );
                $userPasswordVerified = true;
            } else {
                $userPasswordVerified = false;

                logEvents(
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
