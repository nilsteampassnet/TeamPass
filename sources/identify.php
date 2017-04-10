<?php
/**
 *
 * @file          identify.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
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
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

if (!isset($_SESSION['settings']['cpassman_dir']) || $_SESSION['settings']['cpassman_dir'] == "" || $_SESSION['settings']['cpassman_dir'] == ".") {
    $_SESSION['settings']['cpassman_dir'] = "..";
}

if ($_POST['type'] === "identify_duo_user") {
//--------
// DUO AUTHENTICATION
//--------
    // This step creates the DUO request encrypted key

    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';

    // load library
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Authentication/DuoSecurity/Duo.php';
    $sig_request = Duo::signRequest(IKEY, SKEY, AKEY, $_POST['login']);

    if ($debugDuo == 1) {
        $dbgDuo = fopen($_SESSION['settings']['path_to_files_folder']."/duo.debug.txt", "w");
        fputs(
            $dbgDuo,
            "\n\n-----\n\n".
            "sig request : ".$_POST['login']."\n" .
            'resp : ' . $sig_request . "\n"
        );
    }

    // load csrfprotector
    $csrfp_config = include $_SESSION['settings']['cpassman_dir'].'/includes/libraries/csrfp/libs/csrfp.config.php';

    // return result
    echo '[{"sig_request" : "'.$sig_request.'" , "csrfp_token" : "'.$csrfp_config['CSRFP_TOKEN'].'" , "csrfp_key" : "'.$_COOKIE[$csrfp_config['CSRFP_TOKEN']].'"}]';

} elseif ($_POST['type'] == "identify_user_with_agses") {
//--------
//-- AUTHENTICATION WITH AGSES
//--------

    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
    // connect to the server
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = 'db_error_handler';
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // do checks
    if (isset($_POST['cardid']) && empty($_POST['cardid'])) {
        // no card id is given
        // check if it is DB
        $row = DB::queryFirstRow(
            "SELECT `agses-usercardid` FROM ".prefix_table("users")."
            WHERE login = %s",
            filter_var($_POST['login'], FILTER_SANITIZE_STRING)
        );
    } else if (!empty($_POST['cardid']) && is_numeric($_POST['cardid'])) {
        // card id is given
        // save it in DB
        DB::update(
            prefix_table('users'),
            array(
                'agses-usercardid' =>  filter_var($_POST['cardid'], FILTER_SANITIZE_NUMBER_INT)
               ),
            "login = %s",
            $_POST['login']
        );
        $row['agses-usercardid'] = filter_var($_POST['cardid'], FILTER_SANITIZE_NUMBER_INT);
    }else {
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
    if (isset($row['agses-usercardid']) && !empty($ret_agses_url['valeur']) && !empty($ret_agses_id['valeur']) && !empty($ret_agses_apikey['valeur'])) {
        // check that card id is not empty or equal to 0
        if ($row['agses-usercardid'] !== "0" && !empty($row['agses-usercardid'])) {
            include_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Authentication/agses/axs/AXSILPortal_V1_Auth.php';
            $agses = new AXSILPortal_V1_Auth();
            $agses->setUrl($ret_agses_url['valeur']);
            $agses->setAAId($ret_agses_id['valeur']);
            //for release there will be another api-key - this is temporary only
            $agses->setApiKey($ret_agses_apikey['valeur']);
            $agses->create();
            //create random salt and store it into session
            if (!isset($_SESSION['hedgeId']) || $_SESSION['hedgeId'] == "") {
                $_SESSION['hedgeId'] = md5(time());
            }
            $_SESSION['user_settings']['agses-usercardid'] = $row['agses-usercardid'];
            $agses_message = $agses->createAuthenticationMessage(
                $row['agses-usercardid'],
                true,
                1,
                2,
                $_SESSION['hedgeId']
            );

            echo '[{"agses_message" : "'.$agses_message.'" , "error" : ""}]';
        } else {
            echo '[{"agses_status" : "no_user_card_id" , "agses_message" : "" , "error" : ""}]';
        }
    } else {
        if (empty($ret_agses_apikey['valeur']) || empty($ret_agses_url['valeur']) || empty($ret_agses_id['valeur'])) {
            echo '[{"error" : "no_agses_info" , "agses_message" : ""}]';
        } else {
            echo '[{"error" : "something_wrong" , "agses_message" : ""}]';  // user not found but not displayed as this in the error message
        }
    }
} elseif ($_POST['type'] == "identify_duo_user_check") {
//--------
// DUO AUTHENTICATION
// this step is verifying the response received from the server
//--------

    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    // load library
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Authentication/DuoSecurity/Duo.php';
    $resp = Duo::verifyResponse(IKEY, SKEY, AKEY, $_POST['sig_response']);

    if ($debugDuo == 1) {
        $dbgDuo = fopen($_SESSION['settings']['path_to_files_folder'] . "/duo.debug.txt", "a");
        fputs(
            $dbgDuo,
            "\n\n-----\n\n" .
            "sig response : " . $_POST['sig_response'] . "\n" .
            'resp : ' . $resp . "\n"
        );
    }

    // return the response (which should be the user name)
    if ($resp === $_POST['login']) {
        echo '[{"resp" : "'.$resp.'"}]';
    } else {
        echo '[{"resp" : "'.$resp.'"}]';
    }
} elseif ($_POST['type'] == "identify_user") {
//--------
// NORMAL IDENTICATION STEP
//--------

    // increment counter of login attempts
    if (empty($_SESSION["pwd_attempts"])) $_SESSION["pwd_attempts"] = 1;
    else $_SESSION["pwd_attempts"] ++;

    // manage brute force
    if ($_SESSION["pwd_attempts"] <= 3) {
        // identify the user through Teampass process
        identifyUser($_POST['data']);
    } elseif (isset($_SESSION["next_possible_pwd_attempts"]) && time() > $_SESSION["next_possible_pwd_attempts"] && $_SESSION["pwd_attempts"] > 3) {
        $_SESSION["pwd_attempts"] = 1;
        // identify the user through Teampass process
        identifyUser($_POST['data']);
    } else {
        $_SESSION["next_possible_pwd_attempts"] = time() + 10;
        echo '[{"error" : "bruteforce_wait"}]';
        return false;
    }

} elseif ($_POST['type'] == "store_data_in_cookie") {
    // not used any more (only development purpose)
    if ($_POST['key'] != $_SESSION['key']) {
        echo '[{"error" : "something_wrong"}]';
        return false;
    }
    // store some connection data in cookie
    setcookie(
        "TeamPassC",
        $_POST['data'],
        time() + 60 * 60,
        '/'
    );
}

/*
* Complete authentication of user through Teampass
*/
function identifyUser($sentData)
{
    global $debugLdap, $debugDuo, $k;
    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    header("Content-type: text/html; charset=utf-8");
    error_reporting(E_ERROR);
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    if ($debugDuo == 1) {
        $dbgDuo = fopen($_SESSION['settings']['path_to_files_folder'] . "/duo.debug.txt", "a");
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "Content of data sent '" . $sentData . "'\n"
        );
    }

    // connect to the server
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = 'db_error_handler';
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    //Load AES
    $aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
    $aes->register();

    // load passwordLib library
    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
    $pwdlib->register();
    $pwdlib = new PasswordLib\PasswordLib();

    // User's language loading
    $k['langage'] = @$_SESSION['user_language'];
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

    // decrypt and retreive data in JSON format
    $dataReceived = prepareExchangedData($sentData, "decode");
    // Prepare variables
    $passwordClear = htmlspecialchars_decode($dataReceived['pw']);
    $passwordOldEncryption = encryptOld(htmlspecialchars_decode($dataReceived['pw']));
    $username = htmlspecialchars_decode($dataReceived['login']);
    $logError = "";

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "Starting authentication of '" . $username . "'\n"
        );
    }

    // GET SALT KEY LENGTH
    if (strlen(SALT) > 32) {
        $_SESSION['error']['salt'] = true;
    }

    $_SESSION['user_language'] = $k['langage'];
    $ldapConnection = false;

    /* LDAP connection */
    if ($debugLdap == 1) {
        // create temp file
        $dbgLdap = fopen($_SESSION['settings']['path_to_files_folder']."/ldap.debug.txt", "w");
        fputs(
            $dbgLdap,
            "Get all LDAP params : \n" .
            'mode : ' . $_SESSION['settings']['ldap_mode'] . "\n" .
            'type : ' . $_SESSION['settings']['ldap_type'] . "\n" .
            'base_dn : '.$_SESSION['settings']['ldap_domain_dn']."\n" .
            'search_base : ' . $_SESSION['settings']['ldap_search_base']."\n" .
            'bind_dn : ' . $_SESSION['settings']['ldap_bind_dn']."\n" .
            'bind_passwd : ' . $_SESSION['settings']['ldap_bind_passwd']."\n" .
            'user_attribute : ' . $_SESSION['settings']['ldap_user_attribute']."\n" .
            'account_suffix : '.$_SESSION['settings']['ldap_suffix']."\n" .
            'domain_controllers : '.$_SESSION['settings']['ldap_domain_controler']."\n" .
            'port : '.$_SESSION['settings']['ldap_port']."\n" .
            'use_ssl : '.$_SESSION['settings']['ldap_ssl']."\n" .
            'use_tls : '.$_SESSION['settings']['ldap_tls']."\n*********\n\n"
        );
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "LDAP status: " . $_SESSION['settings']['ldap_mode'] . "\n"
        );
    }

    if (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1
        && $username != "admin"
    ) {
        //Multiple Domain Names
        if (strpos(html_entity_decode($username), '\\') == true) {
            $ldap_suffix="@".substr(html_entity_decode($username), 0, strpos(html_entity_decode($username), '\\'));
            $username=substr(html_entity_decode($username), strpos(html_entity_decode($username), '\\') + 1);
        }
        if ($_SESSION['settings']['ldap_type'] == 'posix-search') {
            $ldapURIs = "";
            foreach(explode(",", $_SESSION['settings']['ldap_domain_controler']) as $domainControler) {
                if($_SESSION['settings']['ldap_ssl'] == 1) {
                    $ldapURIs .= "ldaps://".$domainControler.":".$_SESSION['settings']['ldap_port']." ";
                }
                else {
                    $ldapURIs .= "ldap://".$domainControler.":".$_SESSION['settings']['ldap_port']." ";
                }
            }
            if ($debugLdap == 1) {
                fputs($dbgLdap, "LDAP URIs : " . $ldapURIs . "\n");
            }
            $ldapconn = ldap_connect($ldapURIs);

            if ($_SESSION['settings']['ldap_tls']) {
               ldap_start_tls($ldapconn);
            }
            if ($debugLdap == 1) {
                fputs($dbgLdap, "LDAP connection : " . ($ldapconn ? "Connected" : "Failed") . "\n");
            }
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            if ($ldapconn) {
                $ldapbind = ldap_bind($ldapconn, $_SESSION['settings']['ldap_bind_dn'], $_SESSION['settings']['ldap_bind_passwd']);
                if ($debugLdap == 1) {
                    fputs($dbgLdap, "LDAP bind : " . ($ldapbind ? "Bound" : "Failed") . "\n");
                }
                if ($ldapbind) {
                    $filter="(&(" . $_SESSION['settings']['ldap_user_attribute']. "=$username)(objectClass=" . $_SESSION['settings']['ldap_object_class'] ."))";
                    $result=ldap_search($ldapconn, $_SESSION['settings']['ldap_search_base'], $filter, array('dn','mail','givenname','sn'));
                    if (isset($_SESSION['settings']['ldap_usergroup'])) {
                       $filter_group = "memberUid=".$username;
                       $result_group = ldap_search($ldapconn, $_SESSION['settings']['ldap_usergroup'],$filter_group, array('dn'));
                       if ($debugLdap == 1) {
                               fputs(
                                    $dbgLdap,
                                    'Search filter (group): ' . $filter_group . "\n" .
                                    'Results : ' . print_r(ldap_get_entries($ldapconn, $result_group), true) . "\n"
                               );
                       }
                       if (!ldap_count_entries($ldapconn, $result_group)) {
                               $ldapConnection = false;
                       }
                    }
                    if ($debugLdap == 1) {
                        fputs(
                            $dbgLdap,
                            'Search filter : ' . $filter . "\n" .
                            'Results : ' . print_r(ldap_get_entries($ldapconn, $result), true) . "\n"
                        );
                    }
                    if (ldap_count_entries($ldapconn, $result)) {
                        // try auth
                        $result = ldap_get_entries($ldapconn, $result);
                        $user_dn = $result[0]['dn'];
                        $ldapbind = ldap_bind($ldapconn, $user_dn, $passwordClear);
                        if ($ldapbind) {
                            $ldapConnection = true;
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
            if ($debugLdap == 1) {
                fputs(
                    $dbgLdap,
                    "Get all ldap params : \n" .
                    'base_dn : '.$_SESSION['settings']['ldap_domain_dn']."\n" .
                    'account_suffix : '.$_SESSION['settings']['ldap_suffix']."\n" .
                    'domain_controllers : '.$_SESSION['settings']['ldap_domain_controler']."\n" .
                    'port : '.$_SESSION['settings']['ldap_port']."\n" .
                    'use_ssl : '.$_SESSION['settings']['ldap_ssl']."\n" .
                    'use_tls : '.$_SESSION['settings']['ldap_tls']."\n*********\n\n"
                );
            }
            $adldap = new SplClassLoader('adLDAP', '../includes/libraries/LDAP');
            $adldap->register();

            // Posix style LDAP handles user searches a bit differently
            if ($_SESSION['settings']['ldap_type'] == 'posix') {
                $ldap_suffix = ','.$_SESSION['settings']['ldap_suffix'].','.$_SESSION['settings']['ldap_domain_dn'];
            }
            elseif ($_SESSION['settings']['ldap_type'] == 'windows' and $ldap_suffix == '') { //Multiple Domain Names
                $ldap_suffix = $_SESSION['settings']['ldap_suffix'];
            }
            $adldap = new adLDAP\adLDAP(
                array(
                    'base_dn' => $_SESSION['settings']['ldap_domain_dn'],
                    'account_suffix' => $ldap_suffix,
                    'domain_controllers' => explode(",", $_SESSION['settings']['ldap_domain_controler']),
                    'port' => $_SESSION['settings']['ldap_port'],
                    'use_ssl' => $_SESSION['settings']['ldap_ssl'],
                    'use_tls' => $_SESSION['settings']['ldap_tls']
                )
            );

            if ($debugLdap == 1) {
                fputs($dbgLdap, "Create new adldap object : ".$adldap->getLastError()."\n\n\n"); //Debug
            }

            // openLDAP expects an attribute=value pair
            if ($_SESSION['settings']['ldap_type'] == 'posix') {
                $auth_username = $_SESSION['settings']['ldap_user_attribute'].'='.$username;
            } else {
                $auth_username = $username;
            }

            // authenticate the user
            if ($adldap->authenticate($auth_username, html_entity_decode($passwordClear))) {
                $ldapConnection = true;
                //update user's password
                $data['pw'] = $pwdlib->createPasswordHash($passwordClear);
                DB::update(
                    prefix_table('users'),
                    array(
                        'pw' => $data['pw']
                    ),
                    "login=%s",
                    $username
                );
            } else {
                $ldapConnection = false;
            }
            if ($debugLdap == 1) {
                fputs(
                    $dbgLdap,
                    "After authenticate : ".$adldap->getLastError()."\n\n\n" .
                    "ldap status : ".$ldapConnection."\n\n\n"
                ); //Debug
            }
        }
    } else if (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 2) {
        // nothing
    }
    // Check if user exists
    $data = DB::queryFirstRow(
        "SELECT * FROM ".prefix_table("users")." WHERE login=%s_login",
        array(
            'login' => $username
        )
    );
    $counter = DB::count();

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "USer exists: " . $counter . "\n"
        );
    }

    // Check PSK
    if (
            isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1
            && $data['admin'] != 1
    ) {
        $psk = htmlspecialchars_decode($dataReceived['psk']);
        $pskConfirm = htmlspecialchars_decode($dataReceived['psk_confirm']);
        if (empty($psk)) {
            echo '[{"value" : "psk_required"}]';
            exit;
        } elseif (empty($data['psk'])) {
            if (empty($pskConfirm)) {
                echo '[{"value" : "bad_psk_confirmation"}]';
                exit;
            } else {
                $_SESSION['my_sk'] = $psk;
            }
        } elseif ($pwdlib->verifyPasswordHash($psk, $data['psk']) === true) {
            echo '[{"value" : "bad_psk"}]';
            exit;
        }
    }

    $user_initial_creation_through_ldap = false;
    $proceedIdentification = false;
    if ($counter > 0) {
        $proceedIdentification = true;
    } elseif ($counter == 0 && $ldapConnection == true && isset($_SESSION['settings']['ldap_elusers'])
            && ($_SESSION['settings']['ldap_elusers'] == 0)
    ) {
        // If LDAP enabled, create user in CPM if doesn't exist
        $data['pw'] = $pwdlib->createPasswordHash($passwordClear);  // create passwordhash

        // get user info from LDAP
        if ($_SESSION['settings']['ldap_type'] == 'posix-search') {
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
                'email' => @$user_info_from_ad[0]['mail'][0],
                'name' => $user_info_from_ad[0]['givenname'][0],
                'lastname' => $user_info_from_ad[0]['sn'][0],
                'admin' => '0',
                'gestionnaire' => '0',
                'can_manage_all_users' => '0',
                'personal_folder' => $_SESSION['settings']['enable_pf_feature'] == "1" ? '1' : '0',
                'fonction_id' => '0',
                'groupes_interdits' => '0',
                'groupes_visibles' => '0',
                'last_pw_change' => time(),
                'user_language' => $_SESSION['settings']['default_language']
            )
        );
        $newUserId = DB::insertId();
        // Create personnal folder
        if ($_SESSION['settings']['enable_pf_feature'] == "1") {
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
    if ($counter == 0) {
        logEvents('failed_auth', 'user_not_exists', "", stripslashes($username));
        echo '[{"value" : "user_not_exists", "text":""}]';
        exit;
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "User exists (confirm): " . $counter . "\n"
        );
    }

    // check GA code
    if (isset($_SESSION['settings']['google_authentication']) && $_SESSION['settings']['google_authentication'] == 1 && $username != "admin") {
        if (isset($dataReceived['GACode']) && !empty($dataReceived['GACode'])) {
            // load library
            include_once($_SESSION['settings']['cpassman_dir']."/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php");

            // create new instance
            $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($_SESSION['settings']['ga_website_name']);

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

                    echo '[{"value" : "<img src=\"'.$new_2fa_qr.'\">", "user_admin":"', isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "", '", "initial_url" : "'.@$_SESSION['initial_url'].'", "error" : "'.$logError.'"}]';

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
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "Proceed with Ident: " . $proceedIdentification . "\n"
        );
    }

    // check AGSES code
    if (isset($_SESSION['settings']['agses_authentication_enabled']) && $_SESSION['settings']['agses_authentication_enabled'] == 1 && $username != "admin") {
        // load AGSES
        include_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Authentication/agses/axs/AXSILPortal_V1_Auth.php';
            $agses = new AXSILPortal_V1_Auth();
            $agses->setUrl($_SESSION['settings']['agses_hosted_url']);
            $agses->setAAId($_SESSION['settings']['agses_hosted_id']);
            //for release there will be another api-key - this is temporary only
            $agses->setApiKey($_SESSION['settings']['agses_hosted_apikey']);
            $agses->create();
            //create random salt and store it into session
            if (!isset($_SESSION['hedgeId']) || $_SESSION['hedgeId'] == "") {
                $_SESSION['hedgeId'] = md5(time());
            }

        $responseCode = $passwordClear;
        if ($responseCode != "" && strlen($responseCode) >= 4) {
            // Verify response code, store result in session
            $result = $agses->verifyResponse(
                $_SESSION['user_settings']['agses-usercardid'],
                $responseCode,
                $_SESSION['hedgeId']
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
                    $logError =  "ERROR: ".$result;
                } else if ($result == -4) {
                    $logError =  "Wrong response code, no more tries left.";
                } else if ($result == -3) {
                    $logError =  "Wrong response code, try to reenter.";
                } else if ($result == -2) {
                    $logError =  "Timeout. The response code is not valid anymore.";
                } else if ($result == -1) {
                    $logError =  "Security Error. Did you try to verify the response from a different computer?";
                } else if ($result == 1) {
                    $logError =  "Authentication successful, response code correct.
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
        isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "",
        '", "initial_url" : "'.@$_SESSION['initial_url'].'",
        "error" : "'.$logError.'"}]';

        exit();
    }

    if ($proceedIdentification === true && $user_initial_creation_through_ldap == false) {
        // User exists in the DB
        //$data = $db->fetchArray($row);

        //v2.1.17 -> change encryption for users password
        if (
                $passwordOldEncryption == $data['pw'] &&
                !empty($data['pw'])
        ) {
            //update user's password
            $data['pw'] = bCrypt($passwordClear, COST);
            DB::update(
                prefix_table('users'),
                array(
                    'pw' => $data['pw']
                ),
                "id=%i",
                $data['id']
            );
        }
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
                "User's password verified: " . $userPasswordVerified . "\n"
            );
        }

        // Can connect if
        // 1- no LDAP mode + user enabled + pw ok
        // 2- LDAP mode + user enabled + ldap connection ok + user is not admin
        // 3-  LDAP mode + user enabled + pw ok + usre is admin
        // This in order to allow admin by default to connect even if LDAP is activated
        if (
                (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 0
                && $userPasswordVerified == true && $data['disabled'] == 0
                )
                ||
                (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1
                && $ldapConnection == true && $data['disabled'] == 0 && $username != "admin"
                )
                ||
                (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 2
                && $ldapConnection == true && $data['disabled'] == 0 && $username != "admin"
                )
                ||
                (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1
                && $username == "admin" && $userPasswordVerified == true && $data['disabled'] == 0
                )
        ) {
            $_SESSION['autoriser'] = true;
            $_SESSION["pwd_attempts"] = 0;

            // Generate a ramdom ID
            $key = GenerateCryptKey(50);

            if ($debugDuo == 1) {
                fputs(
                    $dbgDuo,
                    "User's token: " . $key . "\n"
                );
            }

            // Log into DB the user's connection
            if (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1) {
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
            // get personal settings
            if (!isset($data['treeloadstrategy']) || empty($data['treeloadstrategy'])) $data['treeloadstrategy'] = "full";
            $_SESSION['user_settings']['treeloadstrategy'] = $data['treeloadstrategy'];
            $_SESSION['user_settings']['agses-usercardid'] = $data['agses-usercardid'];
            $_SESSION['user_settings']['user_language'] = $data['user_language'];
            $_SESSION['user_settings']['encrypted_psk'] = $data['encrypted_psk'];
            $_SESSION['user_settings']['usertimezone'] = $data['usertimezone'];
            $_SESSION['user_settings']['session_duration'] = $dataReceived['duree_session'] * 60;


            // manage session expiration
            $_SESSION['fin_session'] = time() + $_SESSION['user_settings']['session_duration'];

            /* If this option is set user password MD5 is used as personal SALTKey */
            if (
                isset($_SESSION['settings']['use_md5_password_as_salt']) &&
                $_SESSION['settings']['use_md5_password_as_salt'] == 1
            )
            {
                $_SESSION['my_sk'] = md5($passwordClear);
                setcookie(
                    "TeamPass_PFSK_".md5($_SESSION['user_id']),
                    encrypt($_SESSION['my_sk'], ""),
                    time() + 60 * 60 * 24 * $_SESSION['settings']['personal_saltkey_cookie_duration'],
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
            $_SESSION['settings']['update_needed'] = "";
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
                    'psk' => $pwdlib->createPasswordHash(htmlspecialchars_decode($psk)),    //bCrypt(htmlspecialchars_decode($psk), COST)
                    'user_ip' =>  get_client_ip_server()
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
            if ($user_initial_creation_through_ldap != true) {
                identifyUserRights(
                    $data['groupes_visibles'],
                    $_SESSION['groupes_interdits'],
                    $data['admin'],
                    $data['fonction_id'],
                    false
                );
            } else {
                // is new LDAP user. Show only his personal folder
                if ($_SESSION['settings']['enable_pf_feature'] == "1") {
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
            if (isset($_SESSION['settings']['enable_send_email_on_user_login'])
                    && $_SESSION['settings']['enable_send_email_on_user_login'] == 1
                    && $_SESSION['user_admin'] != 1
            ) {
                // get all Admin users
                $receivers = "";
                $rows = DB::query("SELECT email FROM ".prefix_table("users")." WHERE admin = %i", 1);
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
                                date($_SESSION['settings']['date_format'], $_SESSION['derniere_connexion']),
                                date($_SESSION['settings']['time_format'], $_SESSION['derniere_connexion'])
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
            if ($_SESSION['settings']['nb_bad_authentication'] > 0
                    && intval($_SESSION['settings']['nb_bad_authentication']) < $nbAttempts
            ) {
                $userIsLocked = 1;
                // log it
                if (isset($_SESSION['settings']['log_connections'])
                        && $_SESSION['settings']['log_connections'] == 1
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
            } elseif ($_SESSION['settings']['nb_bad_authentication'] == 0) {
                $return = "false";
            } else {
                $return = $nbAttempts;
            }
        }
    } else {
        if ($user_initial_creation_through_ldap == true) {
            $return = "new_ldap_account_created";
        } else {
            $return = "false";
        }
    }

    if ($debugDuo == 1) {
        fputs(
            $dbgDuo,
            "\n\n----\n" .
            "Identified : " . $return . "\n\n"
        );
    }

    // manage bruteforce
    if ($_SESSION["pwd_attempts"] > 2) {
        $_SESSION["next_possible_pwd_attempts"] = time() + 10;
    }

    echo '[{"value" : "'.$return.'", "user_admin":"', isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "", '", "initial_url" : "'.@$_SESSION['initial_url'].'", "error" : "'.$logError.'", "pwd_attempts" : "'.$_SESSION["pwd_attempts"].'"}]';

    $_SESSION['initial_url'] = "";
    if ($_SESSION['settings']['cpassman_dir'] == "..") {
        $_SESSION['settings']['cpassman_dir'] = ".";
    }
}
