<?php
/**
 *
 * @file          identify.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$debugLdap = 0; //Can be used in order to debug LDAP authentication
$debugDuo = 0; //Can be used in order to debug DUO authentication

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] !== 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir']) === true || $SETTINGS['cpassman_dir'] === ".") {
    $SETTINGS['cpassman_dir'] = "..";
}

// init
$dbgDuo = "";
$dbgLdap = "";
$ldap_suffix = "";
$result = "";
$adldap = "";

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
$post_sig_response = filter_input(INPUT_POST, 'sig_response', FILTER_SANITIZE_STRING);
$post_cardid = filter_input(INPUT_POST, 'cardid', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);

if ($post_type === "identify_duo_user") {
    //--------
    // DUO AUTHENTICATION
    //--------
    // This step creates the DUO request encrypted key

    include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    require_once SECUREPATH."/sk.php";

    // load library
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoSecurity/Duo.php';
    $sig_request = Duo::signRequest(IKEY, SKEY, AKEY, $post_login);

    if ($debugDuo == 1) {
        $dbgDuo = fopen($SETTINGS['path_to_files_folder']."/duo.debug.txt", "w");
        fputs(
            $dbgDuo,
            "\n\n-----\n\n".
            "sig request : ".$post_login."\n".
            'resp : '.$sig_request."\n"
        );
    }

    // load csrfprotector
    $csrfp_config = require_once $SETTINGS['cpassman_dir'].'/includes/libraries/csrfp/libs/csrfp.config.php';

    // return result
    echo '[{"sig_request" : "'.$sig_request.'" , "csrfp_token" : "'.$csrfp_config['CSRFP_TOKEN'].'" , "csrfp_key" : "'.filter_var($_COOKIE[$csrfp_config['CSRFP_TOKEN']], FILTER_SANITIZE_STRING).'"}]';
// DUO Identification
} elseif ($post_type === "identify_duo_user_check") {
    //--------
    // DUO AUTHENTICATION
    // this step is verifying the response received from the server
    //--------

    include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    require_once SECUREPATH."/sk.php";

    // load library
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/DuoSecurity/Duo.php';
    $resp = Duo::verifyResponse(IKEY, SKEY, AKEY, $post_sig_response);

    if ($debugDuo == 1) {
        $dbgDuo = fopen($SETTINGS['path_to_files_folder']."/duo.debug.txt", "a");
        fputs(
            $dbgDuo,
            "\n\n-----\n\n".
            "sig response : ".$post_sig_response."\n".
            'resp : '.$resp."\n"
        );
    }

    // return the response (which should be the user name)
    if ($resp === $post_login) {
        echo '[{"resp" : "'.$resp.'"}]';
    } else {
        echo '[{"resp" : "'.$resp.'"}]';
    }
} elseif ($post_type === "identify_user_with_agses") {
//--------
//-- AUTHENTICATION WITH AGSES
//--------

    require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    // connect to the server
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // do checks
    if (null !== $post_cardid && empty(post_cardid) === true) {
        // no card id is given
        // check if it is DB
        $row = DB::queryFirstRow(
            "SELECT `agses-usercardid` FROM ".prefix_table("users")."
            WHERE login = %s",
            $post_login
        );
    } elseif (empty($post_cardid) === false && is_numeric($post_cardid)) {
        // card id is given
        // save it in DB
        DB::update(
            prefix_table('users'),
            array(
                'agses-usercardid' => $post_cardid
                ),
            "login = %s",
            $post_login
        );
        $row['agses-usercardid'] = $post_cardid;
    } else {
        // error
        echo '[{"error" : "something_wrong" , "agses_message" : ""}]';
        return false;
    }

    //-- get AGSES hosted information
    $ret_agses_url = DB::queryFirstRow(
        "SELECT valeur FROM ".prefix_table("misc")."
        WHERE type = %s AND intitule = %s",
        'admin',
        'agses_hosted_url'
    );

    $ret_agses_id = DB::queryFirstRow(
        "SELECT valeur FROM ".prefix_table("misc")."
        WHERE type = %s AND intitule = %s",
        'admin',
        'agses_hosted_id'
    );

    $ret_agses_apikey = DB::queryFirstRow(
        "SELECT valeur FROM ".prefix_table("misc")."
        WHERE type = %s AND intitule = %s",
        'admin',
        'agses_hosted_apikey'
    );

    // if we have a card id and all agses credentials
    // then we try to generate the message for agsesflicker
    if (isset($row['agses-usercardid']) && empty($ret_agses_url['valeur']) === false
        && empty($ret_agses_id['valeur']) === false && empty($ret_agses_apikey['valeur']) === false
    ) {
        // check that card id is not empty or equal to 0
        if ($row['agses-usercardid'] !== "0" && !empty($row['agses-usercardid'])) {
            include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/agses/axs/AXSILPortal_V1_Auth.php';
            $agses = new AXSILPortal_V1_Auth();
            $agses->setUrl($ret_agses_url['valeur']);
            $agses->setAAId($ret_agses_id['valeur']);
            //for release there will be another api-key - this is temporary only
            $agses->setApiKey($ret_agses_apikey['valeur']);
            $agses->create();
            //create random salt and store it into session
            if (!isset($_SESSION['hedgeId']) || empty($_SESSION['hedgeId']) === true) {
                $_SESSION['hedgeId'] = md5(time());
            }
            $_SESSION['user_settings']['agses-usercardid'] = $row['agses-usercardid'];
            $agses_message = $agses->createAuthenticationMessage(
                (string) $row['agses-usercardid'],
                true,
                1,
                2,
                (string) $_SESSION['hedgeId']
            );

            echo '[{"agses_message" : "'.$agses_message.'" , "error" : ""}]';
        } else {
            echo '[{"agses_status" : "no_user_card_id" , "agses_message" : "" , "error" : ""}]';
        }
    } else {
        if (empty($ret_agses_apikey['valeur']) || empty($ret_agses_url['valeur']) || empty($ret_agses_id['valeur'])) {
            echo '[{"error" : "no_agses_info" , "agses_message" : ""}]';
        } else {
            echo '[{"error" : "something_wrong" , "agses_message" : ""}]'; // user not found but not displayed as this in the error message
        }
    }
} elseif ($post_type === "identify_user") {
//--------
// NORMAL IDENTICATION STEP
//--------

    // increment counter of login attempts
    if (empty($_SESSION["pwd_attempts"])) {
        $_SESSION["pwd_attempts"] = 1;
    } else {
        $_SESSION["pwd_attempts"]++;
    }

    // manage brute force
    if ($_SESSION["pwd_attempts"] <= 3) {
        // identify the user through Teampass process
        identifyUser(
            $post_data,
            $debugLdap,
            $debugDuo,
            $SETTINGS
        );
    } elseif (isset($_SESSION["next_possible_pwd_attempts"]) && time() > $_SESSION["next_possible_pwd_attempts"] && $_SESSION["pwd_attempts"] > 3) {
        $_SESSION["pwd_attempts"] = 1;
        // identify the user through Teampass process
        identifyUser(
            $post_data,
            $debugLdap,
            $debugDuo,
            $SETTINGS
        );
    } else {
        $_SESSION["next_possible_pwd_attempts"] = time() + 10;
        echo '[{"error" : "bruteforce_wait"}]';
        return false;
    }
} elseif ($post_type === "store_data_in_cookie") {
    //--------
    // STORE DATA IN COOKIE
    //--------
    //
    // not used any more (only development purpose)
    if ($post_key !== $_SESSION['key']) {
        echo '[{"error" : "something_wrong"}]';
        return false;
    }
    // store some connection data in cookie
    setcookie(
        "TeamPassC",
        $post_data,
        time() + 60 * 60,
        '/'
    );
}

/*
* Complete authentication of user through Teampass
*/
function identifyUser(
    $sentData,
    $debugLdap,
    $debugDuo,
    $SETTINGS
) {
    // Load config
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
    include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';

    header("Content-type: text/html; charset=utf-8");
    error_reporting(E_ERROR);
    include_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    // Load superGlobals
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Prepare GET variables
    $session_user_language = $superGlobal->get("user_language", "SESSION");

    if ($debugDuo == 1) {
        $dbgDuo = fopen($SETTINGS['path_to_files_folder']."/duo.debug.txt", "a");

        fputs(
            $dbgDuo,
            "Content of data sent '".filter_var($sentData, FILTER_SANITIZE_STRING)."'\n"
        );
    }

    // connect to the server
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'].'/includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    // User's language loading
    include_once $SETTINGS['cpassman_dir'].'/includes/language/'.$session_user_language.'.php';

    // decrypt and retreive data in JSON format
    $dataReceived = prepareExchangedData($sentData, "decode");

    // prepare variables
    if (isset($SETTINGS['enable_http_request_login']) === true
        && $SETTINGS['enable_http_request_login'] === '1'
        && isset($_SERVER['PHP_AUTH_USER']) === true
        && isset($SETTINGS['maintenance_mode']) === true
        && $SETTINGS['maintenance_mode'] === '1'
    ) {
        if (strpos($_SERVER['PHP_AUTH_USER'], '@') !== false) {
            $username = explode("@", $_SERVER['PHP_AUTH_USER'])[0];
        } elseif (strpos($_SERVER['PHP_AUTH_USER'], '\\') !== false) {
            $username = explode("\\", $_SERVER['PHP_AUTH_USER'])[1];
        } else {
            $username = $_SERVER['PHP_AUTH_USER'];
        }
        $passwordClear = $_SERVER['PHP_AUTH_PW'];
    } else {
        $passwordClear = htmlspecialchars_decode($dataReceived['pw']);
        $username = $antiXss->xss_clean(htmlspecialchars_decode($dataReceived['login']));
    }
    $logError = "";
    $userPasswordVerified = false;

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "Starting authentication of '".$username."'\n"
        );
    }

    $ldapConnection = false;

    /* LDAP connection */
    if ($debugLdap == 1) {
        // create temp file
        $dbgLdap = fopen($SETTINGS['path_to_files_folder']."/ldap.debug.txt", "w");
        fputs(
            $dbgLdap,
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
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "LDAP status: ".$SETTINGS['ldap_mode']."\n"
        );
    }

    // Check if user exists
    $data = DB::queryFirstRow(
        "SELECT * FROM ".prefix_table("users")." WHERE login=%s_login",
        array(
            'login' => $username
        )
    );
    $counter = DB::count();
    $user_initial_creation_through_ldap = false;
    $proceedIdentification = false;

    // Prepare LDAP connection if set up
    if (isset($SETTINGS['ldap_mode'])
        && $SETTINGS['ldap_mode'] === '1'
        && $username !== "admin"
    ) {
        //Multiple Domain Names
        if (strpos(html_entity_decode($username), '\\') === true) {
            $ldap_suffix = "@".substr(html_entity_decode($username), 0, strpos(html_entity_decode($username), '\\'));
            $username = substr(html_entity_decode($username), strpos(html_entity_decode($username), '\\') + 1);
        }
        if ($SETTINGS['ldap_type'] === 'posix-search') {
            $ldapURIs = "";
            foreach (explode(",", $SETTINGS['ldap_domain_controler']) as $domainControler) {
                if ($SETTINGS['ldap_ssl'] == 1) {
                    $ldapURIs .= "ldaps://".$domainControler.":".$SETTINGS['ldap_port']." ";
                } else {
                    $ldapURIs .= "ldap://".$domainControler.":".$SETTINGS['ldap_port']." ";
                }
            }
            if ($debugLdap == 1) {
                fputs($dbgLdap, "LDAP URIs : ".$ldapURIs."\n");
            }
            $ldapconn = ldap_connect($ldapURIs);

            if ($SETTINGS['ldap_tls']) {
                ldap_start_tls($ldapconn);
            }
            if ($debugLdap == 1) {
                fputs($dbgLdap, "LDAP connection : ".($ldapconn ? "Connected" : "Failed")."\n");
            }
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

            // Is LDAP connection ready?
            if ($ldapconn !== false) {
                // Should we bind the connection?
                if ($SETTINGS['ldap_bind_dn'] !== "" && $SETTINGS['ldap_bind_passwd'] !== "") {
                    $ldapbind = ldap_bind($ldapconn, $SETTINGS['ldap_bind_dn'], $SETTINGS['ldap_bind_passwd']);
                    if ($debugLdap == 1) {
                        fputs($dbgLdap, "LDAP bind : ".($ldapbind ? "Bound" : "Failed")."\n");
                    }
                }
                if (($SETTINGS['ldap_bind_dn'] === "" && $SETTINGS['ldap_bind_passwd'] === "") || $ldapbind === true) {
                    $filter = "(&(".$SETTINGS['ldap_user_attribute']."=".$username.")(objectClass=".$SETTINGS['ldap_object_class']."))";
                    $result = ldap_search(
                      $ldapconn,
                      $SETTINGS['ldap_search_base'],
                      $filter,
                      array('dn', 'mail', 'givenname', 'sn')
                    );
                    if ($debugLdap == 1) {
                        fputs(
                            $dbgLdap,
                            'Search filter : '.$filter."\n".
                            'Results : '.print_r(ldap_get_entries($ldapconn, $result), true)."\n"
                        );
                    }

                    // Check if user was found in AD
                    if (ldap_count_entries($ldapconn, $result) > 0) {
                        // Get user's info and especially the DN
                        $result = ldap_get_entries($ldapconn, $result);
                        $user_dn = $result[0]['dn'];

                        fputs(
                            $dbgLdap,
                            'User was found. '.$user_dn.'\n'
                        );

                        // Should we restrain the search in specified user groups
                        $GroupRestrictionEnabled = false;
                        if (isset($SETTINGS['ldap_usergroup']) === true && empty($SETTINGS['ldap_usergroup']) === false) {
                            // New way to check User's group membership
                            $filter_group = "memberUid=".$username;
                            $result_group = ldap_search(
                                $ldapconn,
                                $SETTINGS['ldap_search_base'],
                                $filter_group,
                                array('dn')
                            );

                            if ($result_group) {
                                $entries = ldap_get_entries($ldapconn, $result_group);

                                if ($debugLdap == 1) {
                                    fputs(
                                        $dbgLdap,
                                        'Search groups appartenance : '.$SETTINGS['ldap_search_base']."\n".
                                        'Results : '.print_r($entries, true)."\n"
                                    );
                                }

                                if ($entries['count'] > 0) {
                                    // Now check if group fits
                                    for ($i=0; $i<$entries['count']; $i++) {
                                      $parsr=ldap_explode_dn($entries[$i]['dn'], 0);
                                      if (str_replace(array('CN=','cn='), '', $parsr[0]) === $SETTINGS['ldap_usergroup']) {
                                        $GroupRestrictionEnabled = true;
                                        break;
                                      }
                                    }

                                }
                            }

                            if ($debugLdap == 1) {
                                fputs(
                                    $dbgLdap,
                                    'Group was found : '.$GroupRestrictionEnabled."\n"
                                );
                            }
                        }

                        // Is user in the LDAP?
                        if ($GroupRestrictionEnabled === true
                            || (
                                $GroupRestrictionEnabled === false
                                && (isset($SETTINGS['ldap_usergroup']) === false
                                    || (isset($SETTINGS['ldap_usergroup']) === true && empty($SETTINGS['ldap_usergroup']) === true)
                                )
                            )
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
                                        prefix_table('users'),
                                        array(
                                            'pw' => $data['pw']
                                        ),
                                        "login=%s",
                                        $username
                                    );

                                    // No user creation is requested
                                    $proceedIdentification = true;
                                }
                            } else {
                                $ldapConnection = false;
                            }
                        }
                    } else {
                        $ldapConnection = false;
                    }
                } else {
                    $ldapConnection = false;
                }
            } else {
                $ldapConnection = false;
            }
        } else {
            if ($debugLdap == 1) {
                fputs(
                    $dbgLdap,
                    "Get all ldap params : \n".
                    'base_dn : '.$SETTINGS['ldap_domain_dn']."\n".
                    'account_suffix : '.$SETTINGS['ldap_suffix']."\n".
                    'domain_controllers : '.$SETTINGS['ldap_domain_controler']."\n".
                    'ad_port : '.$SETTINGS['ldap_port']."\n".
                    'use_ssl : '.$SETTINGS['ldap_ssl']."\n".
                    'use_tls : '.$SETTINGS['ldap_tls']."\n*********\n\n"
                );
            }
            $adldap = new SplClassLoader('adLDAP', '../includes/libraries/LDAP');
            $adldap->register();

            // Posix style LDAP handles user searches a bit differently
            if ($SETTINGS['ldap_type'] === 'posix') {
                $ldap_suffix = ','.$SETTINGS['ldap_suffix'].','.$SETTINGS['ldap_domain_dn'];
            } elseif ($SETTINGS['ldap_type'] === 'windows' && empty($ldap_suffix) === true) {
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
                    'domain_controllers' => explode(",", $SETTINGS['ldap_domain_controler']),
                    'ad_port' => $SETTINGS['ldap_port'],
                    'use_ssl' => $SETTINGS['ldap_ssl'],
                    'use_tls' => $SETTINGS['ldap_tls']
                )
            );

            if ($debugLdap == 1) {
                fputs($dbgLdap, "Create new adldap object : ".$adldap->getLastError()."\n\n\n"); //Debug
            }

            // OpenLDAP expects an attribute=value pair
            if ($SETTINGS['ldap_type'] === 'posix') {
                $auth_username = $SETTINGS['ldap_user_attribute'].'='.$username;
            } else {
                $auth_username = $username;
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

                // Update user's password
                if ($ldapConnection === true) {
                    $data['pw'] = $pwdlib->createPasswordHash($passwordClear);

                    // Do things if user exists in TP
                    if ($counter > 0) {
                        // Update pwd in TP database
                        DB::update(
                            prefix_table('users'),
                            array(
                                'pw' => $data['pw']
                            ),
                            "login=%s",
                            $username
                        );

                        // No user creation is requested
                        $proceedIdentification = true;
                    }
                }
            } else {
                $ldapConnection = false;
            }
            if ($debugLdap == 1) {
                fputs(
                    $dbgLdap,
                    "After authenticate : ".$adldap->getLastError()."\n\n\n".
                    "ldap status : ".$ldapConnection."\n\n\n"
                ); //Debug
            }
        }
    } elseif (isset($SETTINGS['ldap_mode']) && $SETTINGS['ldap_mode'] == 2) {
        // nothing
    }
    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "USer exists: ".$counter."\n"
        );
    }


    // Check PSK
    if (isset($SETTINGS['psk_authentication'])
        && $SETTINGS['psk_authentication'] === "1"
        && $data['admin'] !== "1"
    ) {
        $psk = htmlspecialchars_decode($dataReceived['psk']);
        $pskConfirm = htmlspecialchars_decode($dataReceived['psk_confirm']);
        if (empty($psk)) {
            echo '[{"value" : "psk_required"}]';
            exit();
        } elseif (empty($data['psk'])) {
            if (empty($pskConfirm)) {
                echo '[{"value" : "bad_psk_confirmation"}]';
                exit();
            } else {
                $_SESSION['user_settings']['clear_psk'] = $psk;
            }
        } elseif ($pwdlib->verifyPasswordHash($psk, $data['psk']) === true) {
            echo '[{"value" : "bad_psk"}]';
            exit();
        }
    }


    // Create new LDAP user if not existing in Teampass
    // Don't create it if option "only localy declared users" is enabled
    if ($counter == 0 && $ldapConnection === true && isset($SETTINGS['ldap_elusers'])
        && ($SETTINGS['ldap_elusers'] == 0)
    ) {
        // If LDAP enabled, create user in TEAMPASS if doesn't exist

        // Get user info from LDAP
        if ($SETTINGS['ldap_type'] === 'posix-search') {
            //Because we didn't use adLDAP, we need to set the user info from the ldap_get_entries result
            $user_info_from_ad = $result;
        } else {
            $user_info_from_ad = $adldap->user()->info($auth_username, array("mail", "givenname", "sn"));
        }

        DB::insert(
            prefix_table('users'),
            array(
                'login' => $username,
                'pw' => $data['pw'],
                'email' => (isset($user_info_from_ad[0]['mail'][0]) === false) ? '' : $user_info_from_ad[0]['mail'][0],
                'name' => $user_info_from_ad[0]['givenname'][0],
                'lastname' => $user_info_from_ad[0]['sn'][0],
                'admin' => '0',
                'gestionnaire' => '0',
                'can_manage_all_users' => '0',
                'personal_folder' => $SETTINGS['enable_pf_feature'] === "1" ? '1' : '0',
                'fonction_id' => isset($SETTINGS['ldap_new_user_role']) === true ? $SETTINGS['ldap_new_user_role'] : '0',
                'groupes_interdits' => '',
                'groupes_visibles' => '',
                'last_pw_change' => time(),
                'user_language' => $SETTINGS['default_language'],
                'encrypted_psk' => '',
                'isAdministratedByRole' => (isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && empty($SETTINGS['ldap_new_user_is_administrated_by']) === false) ? $SETTINGS['ldap_new_user_is_administrated_by'] : 0
            )
        );
        $newUserId = DB::insertId();
        // Create personnal folder
        if (isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] === "1") {
            DB::insert(
                prefix_table("nested_tree"),
                array(
                    'parent_id' => '0',
                    'title' => $newUserId,
                    'bloquer_creation' => '0',
                    'bloquer_modification' => '0',
                    'personal_folder' => '1'
                )
            );
        }
        $proceedIdentification = true;
        $user_initial_creation_through_ldap = true;
    }

    // Check if user exists (and has been created in case of new LDAP user)
    $data = DB::queryFirstRow(
        "SELECT * FROM ".prefix_table("users")." WHERE login=%s_login",
        array(
            'login' => $username
        )
    );
    $counter = DB::count();
    if ($counter === 0) {
        logEvents('failed_auth', 'user_not_exists', "", stripslashes($username));
        echo '[{"value" : "user_not_exists '.$username.'", "text":""}]';
        exit();
    }

    // check GA code
    if (isset($SETTINGS['google_authentication']) && $SETTINGS['google_authentication'] == 1 && $username !== "admin") {
        if (isset($dataReceived['GACode']) && empty($dataReceived['GACode']) === false) {
            // load library
            include_once($SETTINGS['cpassman_dir']."/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php");

            // create new instance
            $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($SETTINGS['ga_website_name']);

            // now check if it is the 1st time the user is using 2FA
            if ($data['ga_temporary_code'] !== "none" && $data['ga_temporary_code'] !== "done") {
                if ($data['ga_temporary_code'] !== $dataReceived['GACode']) {
                    $proceedIdentification = false;
                    $logError = "ga_temporary_code_wrong";
                } else {
                    $proceedIdentification = false;
                    $logError = "ga_temporary_code_correct";

                    // generate new QR
                    $new_2fa_qr = $tfa->getQRCodeImageAsDataUri("Teampass - ".$username, $data['ga']);

                    // clear temporary code from DB
                    DB::update(
                        prefix_table('users'),
                        array(
                            'ga_temporary_code' => 'done'
                        ),
                        "id=%i",
                        $data['id']
                    );

                    echo '[{"value" : "<img src=\"'.$new_2fa_qr.'\">", "user_admin":"', isset($_SESSION['user_admin']) ? $antiXss->xss_clean($_SESSION['user_admin']) : "", '", "initial_url" : "'.@$_SESSION['initial_url'].'", "error" : "'.$logError.'"}]';

                    exit();
                }
            } else {
                // verify the user GA code
                if ($tfa->verifyCode($data['ga'], $dataReceived['GACode'])) {
                    $proceedIdentification = true;
                } else {
                    $proceedIdentification = false;
                    $logError = "ga_code_wrong";
                }
            }
        } else {
            $proceedIdentification = false;
            $logError = "ga_code_wrong";
        }
    } elseif ($counter > 0) {
        $proceedIdentification = true;
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "Proceed with Ident: ".$proceedIdentification."\n"
        );
    }


    // check AGSES code
    if (isset($SETTINGS['agses_authentication_enabled']) && $SETTINGS['agses_authentication_enabled'] == 1 && $username != "admin") {
        // load AGSES
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/agses/axs/AXSILPortal_V1_Auth.php';
        $agses = new AXSILPortal_V1_Auth();
        $agses->setUrl($SETTINGS['agses_hosted_url']);
        $agses->setAAId($SETTINGS['agses_hosted_id']);
        //for release there will be another api-key - this is temporary only
        $agses->setApiKey($SETTINGS['agses_hosted_apikey']);
        $agses->create();
        //create random salt and store it into session
        if (!isset($_SESSION['hedgeId']) || $_SESSION['hedgeId'] == "") {
            $_SESSION['hedgeId'] = md5(time());
        }

        $responseCode = $passwordClear;
        if ($responseCode != "" && strlen($responseCode) >= 4) {
            // Verify response code, store result in session
            $result = $agses->verifyResponse(
                (string) $_SESSION['user_settings']['agses-usercardid'],
                $responseCode,
                (string) $_SESSION['hedgeId']
            );

            if ($result == 1) {
                $return = "";
                $logError = "";
                $proceedIdentification = true;
                $userPasswordVerified = true;
                unset($_SESSION['hedgeId']);
                unset($_SESSION['flickercode']);
            } else {
                if ($result < -10) {
                    $logError = "ERROR: ".$result;
                } elseif ($result == -4) {
                    $logError = "Wrong response code, no more tries left.";
                } elseif ($result == -3) {
                    $logError = "Wrong response code, try to reenter.";
                } elseif ($result == -2) {
                    $logError = "Timeout. The response code is not valid anymore.";
                } elseif ($result == -1) {
                    $logError = "Security Error. Did you try to verify the response from a different computer?";
                } elseif ($result == 1) {
                    $logError = "Authentication successful, response code correct.
                          <br /><br />Authentification Method for SecureBrowser updated!";
                    // Add necessary code here for accessing your Business Application
                }
                $return = "agses_error";
                echo '[{"value" : "'.$return.'", "user_admin":"',
                isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "",
                '", "initial_url" : "'.@$_SESSION['initial_url'].'",
                "error" : "'.$logError.'"}]';

                exit();
            }
        } else {
            // We have an error here
            $return = "agses_error";
            $logError = "No response code given";

            echo '[{"value" : "'.$return.'", "user_admin":"',
            isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "",
            '", "initial_url" : "'.@$_SESSION['initial_url'].'",
            "error" : "'.$logError.'"}]';

            exit();
        }
    }

    // If admin user then check if folder install exists
    // if yes then refuse connection
    if ($data['admin'] === "1" && is_dir("../install")) {
        $return = "install_error";
        $logError = "Install folder has to be removed!";

        echo '[{"value" : "'.$return.'", "user_admin":"',
        isset($_SESSION['user_admin']) ? $antiXss->xss_clean($_SESSION['user_admin']) : "",
        '", "initial_url" : "'.@$_SESSION['initial_url'].'",
        "error" : "'.$logError.'"}]';

        exit();
    }

    if ($proceedIdentification === true) {
        // User exists in the DB
        if (crypt($passwordClear, $data['pw']) == $data['pw'] && !empty($data['pw'])) {
            //update user's password
            $data['pw'] = $pwdlib->createPasswordHash($passwordClear);
            DB::update(
                prefix_table('users'),
                array(
                    'pw' => $data['pw']
                ),
                "id=%i",
                $data['id']
            );
        }

        // check the given password
        if ($userPasswordVerified !== true) {
            if ($pwdlib->verifyPasswordHash($passwordClear, $data['pw']) === true) {
                $userPasswordVerified = true;
            } else {
                $userPasswordVerified = false;
                logEvents('failed_auth', 'user_password_not_correct', "", stripslashes($username));
            }
        }

        if ($debugDuo == 1) {
            fputs(
                $dbgDuo,
                "User's password verified: ".$userPasswordVerified."\n"
            );
        }

        // Can connect if
        // 1- no LDAP mode + user enabled + pw ok
        // 2- LDAP mode + user enabled + ldap connection ok + user is not admin
        // 3-  LDAP mode + user enabled + pw ok + usre is admin
        // This in order to allow admin by default to connect even if LDAP is activated
        if ((
                isset($SETTINGS['ldap_mode']) && $SETTINGS['ldap_mode'] === '0'
                && $userPasswordVerified === true && $data['disabled'] == 0
            )
            ||
            (
                isset($SETTINGS['ldap_mode']) && $SETTINGS['ldap_mode'] === '1'
                && $ldapConnection === true && $data['disabled'] === '0' && $username != "admin"
            )
            ||
            (
                isset($SETTINGS['ldap_mode']) && $SETTINGS['ldap_mode'] === '2'
                && $ldapConnection === true && $data['disabled'] === '0' && $username != "admin"
            )
            ||
            (
                isset($SETTINGS['ldap_mode']) && $SETTINGS['ldap_mode'] === '1'
                && $username == "admin" && $userPasswordVerified === true && $data['disabled'] === '0'
            )
            ||
            (
                isset($SETTINGS['ldap_and_local_authentication']) && $SETTINGS['ldap_and_local_authentication'] === '1'
                && isset($SETTINGS['ldap_mode']) && in_array($SETTINGS['ldap_mode'], array('1', '2')) === true
                && $userPasswordVerified === true && $data['disabled'] == 0
            )
        ) {
            $_SESSION['autoriser'] = true;
            $_SESSION["pwd_attempts"] = 0;

            // Generate a ramdom ID
            $key = GenerateCryptKey(50);

            if ($debugDuo == 1) {
                fputs(
                    $dbgDuo,
                    "User's token: ".$key."\n"
                );
            }

            // Log into DB the user's connection
            if (isset($SETTINGS['log_connections']) && $SETTINGS['log_connections'] === '1') {
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
            $_SESSION['key'] = $key;
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
                $data['treeloadstrategy'] = "full";
            }
            $_SESSION['user_settings']['treeloadstrategy'] = $data['treeloadstrategy'];
            $_SESSION['user_settings']['agses-usercardid'] = $data['agses-usercardid'];
            $_SESSION['user_settings']['user_language'] = $data['user_language'];
            $_SESSION['user_settings']['encrypted_psk'] = $data['encrypted_psk'];
            $_SESSION['user_settings']['usertimezone'] = $data['usertimezone'];
            $_SESSION['user_settings']['session_duration'] = $dataReceived['duree_session'] * 60;
            $_SESSION['user_settings']['api-key'] = $data['user_api_key'];


            // manage session expiration
            $_SESSION['fin_session'] = (integer) (time() + $_SESSION['user_settings']['session_duration']);

            /* If this option is set user password MD5 is used as personal SALTKey */
            if (isset($SETTINGS['use_md5_password_as_salt']) &&
                $SETTINGS['use_md5_password_as_salt'] == 1
            ) {
                $_SESSION['user_settings']['clear_psk'] = md5($passwordClear);
                setcookie(
                    "TeamPass_PFSK_".md5($_SESSION['user_id']),
                    encrypt($_SESSION['user_settings']['clear_psk'], ""),
                    time() + 60 * 60 * 24 * $SETTINGS['personal_saltkey_cookie_duration'],
                    '/'
                );
            }

            if (empty($data['last_connexion'])) {
                $_SESSION['derniere_connexion'] = time();
            } else {
                $_SESSION['derniere_connexion'] = $data['last_connexion'];
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
                $_SESSION['groupes_visibles'] = @implode(';', $data['groupes_visibles']);
            } else {
                $_SESSION['groupes_visibles'] = array();
            }
            if (!empty($data['groupes_interdits'])) {
                $_SESSION['groupes_interdits'] = @implode(';', $data['groupes_interdits']);
            } else {
                $_SESSION['groupes_interdits'] = array();
            }
            // User's roles
            $_SESSION['fonction_id'] = $data['fonction_id'];
            $_SESSION['user_roles'] = explode(";", $data['fonction_id']);
            // build array of roles
            $_SESSION['user_pw_complexity'] = 0;
            $_SESSION['arr_roles'] = array();
            foreach (array_filter(explode(';', $_SESSION['fonction_id'])) as $role) {
                $resRoles = DB::queryFirstRow("SELECT title, complexity FROM ".prefix_table("roles_title")." WHERE id=%i", $role);
                $_SESSION['arr_roles'][$role] = array(
                        'id' => $role,
                        'title' => $resRoles['title']
                );
                // get highest complexity
                if ($_SESSION['user_pw_complexity'] < $resRoles['complexity']) {
                    $_SESSION['user_pw_complexity'] = $resRoles['complexity'];
                }
            }
            // build complete array of roles
            $_SESSION['arr_roles_full'] = array();
            $rows = DB::query("SELECT id, title FROM ".prefix_table("roles_title")." ORDER BY title ASC");
            foreach ($rows as $record) {
                $_SESSION['arr_roles_full'][$record['id']] = array(
                        'id' => $record['id'],
                        'title' => $record['title']
                );
            }
            // Set some settings
            $_SESSION['user']['find_cookie'] = false;
            $SETTINGS['update_needed'] = "";
            // Update table
            DB::update(
                prefix_table('users'),
                array(
                    'key_tempo' => $_SESSION['key'],
                    'last_connexion' => time(),
                    'timestamp' => time(),
                    'disabled' => 0,
                    'no_bad_attempts' => 0,
                    'session_end' => $_SESSION['fin_session'],
                    'psk' => isset($psk) ? $pwdlib->createPasswordHash(htmlspecialchars_decode($psk)) : '',
                    'user_ip' =>  $dataReceived['client']
                ),
                "id=%i",
                $data['id']
            );

            if ($debugDuo == 1) {
                fputs(
                    $dbgDuo,
                    "Preparing to identify the user rights\n"
                );
            }

            // Get user's rights
            if ($user_initial_creation_through_ldap === false) {
                identifyUserRights(
                    $data['groupes_visibles'],
                    $_SESSION['groupes_interdits'],
                    $data['admin'],
                    $data['fonction_id'],
                    $server,
                    $user,
                    $pass,
                    $database,
                    $port,
                    $encoding,
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
                $_SESSION['groupes_visibles_list'] = "";
                $_SESSION['read_only_folders'] = array();
                $_SESSION['list_folders_limited'] = "";
                $_SESSION['list_folders_editable_by_role'] = array();
                $_SESSION['list_restricted_folders_for_items'] = array();
                $_SESSION['nb_folders'] = 1;
                $_SESSION['nb_roles'] = 0;
            }
            // Get some more elements
            $_SESSION['screenHeight'] = $dataReceived['screenHeight'];
            // Get last seen items
            $_SESSION['latest_items_tab'][] = "";
            foreach ($_SESSION['latest_items'] as $item) {
                if (!empty($item)) {
                    $data = DB::queryFirstRow("SELECT id,label,id_tree FROM ".prefix_table("items")." WHERE id=%i", $item);
                    $_SESSION['latest_items_tab'][$item] = array(
                        'id' => $item,
                        'label' => $data['label'],
                        'url' => 'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$item
                    );
                }
            }
            // send back the random key
            $return = $dataReceived['randomstring'];
            // Send email
            if (isset($SETTINGS['enable_send_email_on_user_login'])
                    && $SETTINGS['enable_send_email_on_user_login'] === '1'
                    && $_SESSION['user_admin'] != 1
            ) {
                // get all Admin users
                $receivers = "";
                $rows = DB::query("SELECT email FROM ".prefix_table("users")." WHERE admin = %i and email != ''", 1);
                foreach ($rows as $record) {
                    if (empty($receivers)) {
                        $receivers = $record['email'];
                    } else {
                        $receivers = ",".$record['email'];
                    }
                }
                // Add email to table
                DB::insert(
                    prefix_table("emails"),
                    array(
                        'timestamp' => time(),
                        'subject' => $LANG['email_subject_on_user_login'],
                        'body' => str_replace(
                            array(
                                '#tp_user#',
                                '#tp_date#',
                                '#tp_time#'
                            ),
                            array(
                                " ".$_SESSION['login']." (IP: ".get_client_ip_server().")",
                                date($SETTINGS['date_format'], $_SESSION['derniere_connexion']),
                                date($SETTINGS['time_format'], $_SESSION['derniere_connexion'])
                            ),
                            $LANG['email_body_on_user_login']
                        ),
                        'receivers' => $receivers,
                        'status' => "not_sent"
                    )
                );
            }
        } elseif ($data['disabled'] == 1) {
            // User and password is okay but account is locked
            $return = "user_is_locked";
        } else {
            // User exists in the DB but Password is false
            // check if user is locked
            $userIsLocked = 0;
            $nbAttempts = intval($data['no_bad_attempts'] + 1);
            if ($SETTINGS['nb_bad_authentication'] > 0
                    && intval($SETTINGS['nb_bad_authentication']) < $nbAttempts
            ) {
                $userIsLocked = 1;
                // log it
                if (isset($SETTINGS['log_connections'])
                        && $SETTINGS['log_connections'] === '1'
                ) {
                    logEvents('user_locked', 'connection', $data['id'], stripslashes($username));
                }
            }
            DB::update(
                prefix_table('users'),
                array(
                    'key_tempo' => $_SESSION['key'],
                    'last_connexion' => time(),
                    'disabled' => $userIsLocked,
                    'no_bad_attempts' => $nbAttempts
                ),
                "id=%i",
                $data['id']
            );
            // What return shoulb we do
            if ($userIsLocked == 1) {
                $return = "user_is_locked";
            } elseif ($SETTINGS['nb_bad_authentication'] === '0') {
                $return = "false";
            } else {
                $return = $nbAttempts;
            }
        }
    } else {
        if ($user_initial_creation_through_ldap === true) {
            $return = "new_ldap_account_created";
        } else {
            $return = "false";
        }
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "\n\n----\n".
            "Identified : ".filter_var($return, FILTER_SANITIZE_STRING)."\n\n"
        );
    }

    // manage bruteforce
    if ($_SESSION["pwd_attempts"] > 2) {
        $_SESSION["next_possible_pwd_attempts"] = time() + 10;
    }

    echo '[{"value" : "'.$return.'", "user_admin":"', isset($_SESSION['user_admin']) ? $antiXss->xss_clean($_SESSION['user_admin']) : "", '", "initial_url" : "'.@$_SESSION['initial_url'].'", "error" : "'.$logError.'", "pwd_attempts" : "'.$antiXss->xss_clean($_SESSION["pwd_attempts"]).'"}]';

    $_SESSION['initial_url'] = "";
    if ($SETTINGS['cpassman_dir'] === '..') {
        $SETTINGS['cpassman_dir'] = '.';
    }
}
