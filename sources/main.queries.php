<?php
/**
 *
 * @file          main.queries.php
 * @author        Nils LaumaillÃ©
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils LaumaillÃ©
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$debugLdap = 0; //Can be used in order to debug LDAP authentication

require_once('sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...1');
}

global $k;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
error_reporting(E_ERROR);
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database);

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// User's language loading
$k['langage'] = @$_SESSION['user_language'];
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
// Manage type of action asked
switch ($_POST['type']) {
    case "change_pw":
        // decrypt and retreive data in JSON format
    	$dataReceived = prepareExchangedData($_POST['data'], "decode");
        
        // check if expected securiy level is reached
        $data_roles = DB::queryfirstrow("SELECT fonction_id FROM ".$pre."users WHERE id = ".$_SESSION['user_id']);
        $data = DB::query(
            "SELECT complexity
            FROM ".$pre."roles_title
            WHERE id IN (".str_replace(';', ',', $data_roles['fonction_id']).")
            ORDER BY complexity DESC"
        );
        if (intval($_POST['complexity']) < intval($data[0]['complexity'])) {
            echo '[ { "error" : "complexity_level_not_reached" } ]';
            break;
        }
        // Prepare variables
        $newPw = bCrypt(htmlspecialchars_decode($dataReceived['new_pw']), COST);
        // User has decided to change is PW
        if (isset($_POST['change_pw_origine']) && $_POST['change_pw_origine'] == "user_change") {
            // Get a string with the old pw array
            $lastPw = explode(';', $_SESSION['last_pw']);
            // if size is bigger then clean the array
            if (sizeof($lastPw) > $_SESSION['settings']['number_of_used_pw']
                    && $_SESSION['settings']['number_of_used_pw'] > 0
            ) {
                for ($x = 0; $x < $_SESSION['settings']['number_of_used_pw']; $x++) {
                    unset($lastPw[$x]);
                }
                // reinit SESSION
                $_SESSION['last_pw'] = implode(';', $lastPw);
                // specific case where admin setting "number_of_used_pw"
            } elseif ($_SESSION['settings']['number_of_used_pw'] == 0) {
                $_SESSION['last_pw'] = "";
                $lastPw = array();
            } 
            // check if new pw is different that old ones
            if (in_array($newPw, $lastPw)) {
                echo '[ { "error" : "already_used" } ]';
                break;
            } else {
                // update old pw with new pw
                if (sizeof($lastPw) == ($_SESSION['settings']['number_of_used_pw'] + 1)) {
                    unset($lastPw[0]);
                } else {
                    array_push($lastPw, $newPw);
                }
                // create a list of last pw based on the table
                $oldPw = "";
                foreach ($lastPw as $elem) {
                    if (!empty($elem)) {
                        if (empty($oldPw)) {
                            $oldPw = $elem;
                        } else {
                            $oldPw .= ";".$elem;
                        }
                    }
                }
                // update sessions
                $_SESSION['last_pw'] = $oldPw;
                $_SESSION['last_pw_change'] = mktime(0, 0, 0, date('m'), date('d'), date('y'));
                $_SESSION['validite_pw'] = true;
                // update DB
                DB::update(
                    $pre."users",
                    array(
                        'pw' => $newPw,
                        'last_pw_change' => mktime(0, 0, 0, date('m'), date('d'), date('y')),
                        'last_pw' => $oldPw
                       ),
                    "id = %i",
                    $_SESSION['user_id']
                );
                // update LOG
                DB::insert(
                    $pre.'log_system',
                    array(
                        'type' => 'user_mngt',
                        'date' => time(),
                        'label' => 'at_user_pwd_changed',
                        'qui' => $_SESSION['user_id'],
                        'field_1' => $_SESSION['user_id']
                       )
                );

                echo '[ { "error" : "none" } ]';
                break;
            }
            // ADMIN has decided to change the USER's PW
        } elseif (isset($_POST['change_pw_origine']) && $_POST['change_pw_origine'] == "admin_change") {
            // Check KEY
            if ($dataReceived['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // update DB
            DB::update(
                $pre."users",
                array(
                    'pw' => $newPw,
                    'last_pw_change' => mktime(0, 0, 0, date('m'), date('d'), date('y'))
                   ),
                "id = %i",
                $dataReceived['user_id']
            );
            // update LOG
            DB::insert(
                $pre.'log_system',
                array(
                    'type' => 'user_mngt',
                    'date' => time(),
                    'label' => 'at_user_pwd_changed',
                    'qui' => $_SESSION['user_id'],
                    'field_1' => $_SESSION['user_id']
                   )
            );
            //Send email to user
            $row = DB::queryFirstRow(
                "SELECT email FROM ".$pre."users
                WHERE id = %i",
                $dataReceived['user_id']
            );
            if (!empty($row['email'])) {
                sendEmail(
                    $LANG['forgot_pw_email_subject'],
                    $LANG['forgot_pw_email_body'] . " " . htmlspecialchars_decode($dataReceived['new_pw']),
                    $row[0],
                    $LANG['forgot_pw_email_altbody_1'] . " " . htmlspecialchars_decode($dataReceived['new_pw'])
                );
            }

            echo '[ { "error" : "none" } ]';
            break;

            // ADMIN first login
        } elseif (isset($_POST['change_pw_origine']) && $_POST['change_pw_origine'] == "first_change") {
            // update DB
            DB::update(
                $pre."users",
                array(
                    'pw' => $newPw,
                    'last_pw_change' => mktime(0, 0, 0, date('m'), date('d'), date('y'))
                   ),
                "id = %i",
                $_SESSION['user_id']
            );
            $_SESSION['last_pw_change'] = mktime(0, 0, 0, date('m'), date('d'), date('y'));
            // update LOG
            DB::insert(
                $pre.'log_system',
                array(
                    'type' => 'user_mngt',
                    'date' => time(),
                    'label' => 'at_user_initial_pwd_changed',
                    'qui' => $_SESSION['user_id'],
                    'field_1' => $_SESSION['user_id']
                )
            );

            echo '[ { "error" : "none" } ]';
            break;
        } else {
            // DEFAULT case
            echo '[ { "error" : "nothing_to_do" } ]';
        }
        break;
    /**
    * This will generate the QR Google Authenticator
    */
    case "ga_generate_qr":
    	require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

    	// Check if user exists
    	$data = DB::query(
    		"SELECT login, email
    		FROM ".$pre."users
    		WHERE id = %i",
            $_POST['id']
    	);
    	$counter = DB::count();
    	if ($counter == 0) {
    		// not a registered user !
    		echo '[{"error" : "no_user"}]';
    	} else {
    		if (empty($data['email'])) {
    			echo '[{"error" : "no_email"}]';
    		} else {
    			// generate new GA user code
    			include_once($_SESSION['settings']['cpassman_dir']."/includes/libraries/Authentication/GoogleAuthenticator/FixedBitNotation.php");
    			include_once($_SESSION['settings']['cpassman_dir']."/includes/libraries/Authentication/GoogleAuthenticator/GoogleAuthenticator.php");
    			$g = new Authentication\GoogleAuthenticator\GoogleAuthenticator();
    			$gaSecretKey = $g->generateSecret();

    			// save the code
    			DB::update(
	    			$pre."users",
	    			array(
	    			    'ga' => $gaSecretKey
	    			   ),
	    			"id = %i",
                    $_POST['id']
    			);

    			// generate QR url
    			$gaUrl = $g->getURL($data['login'], $_SESSION['settings']['ga_website_name'], $gaSecretKey);

    			// send mail?
    			if (isset($_POST['send_email']) && $_POST['send_email'] == 1) {
    				sendEmail (
    					$LANG['email_ga_subject'],
    					str_replace("#link#", $gaUrl, $LANG['email_ga_text']),
    					$data['email']
					);
    			}

    			// send back
    			echo '[{ "error" : "0" , "ga_url" : "'.$gaUrl.'" }]';
    		}
    	}
    	break;
    /**
     * Identify the USer
     */
    case "identify_user":
        // decrypt and retreive data in JSON format
    	$dataReceived = prepareExchangedData($_POST['data'], "decode");
        // Prepare variables
        $passwordClear = htmlspecialchars_decode($dataReceived['pw']);
        $passwordOldEncryption = encryptOld(htmlspecialchars_decode($dataReceived['pw']));
        $username = htmlspecialchars_decode($dataReceived['login']);
    	$logError = "";

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
        }

        if (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1
                && $username != "admin"
        ) {
            if ($_SESSION['settings']['ldap_type'] == 'posix-search') {
                $ldapconn = ldap_connect($_SESSION['settings']['ldap_domain_controler']);
                ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
                if ($ldapconn) {
                    $ldapbind = ldap_bind($ldapconn, $_SESSION['settings']['ldap_bind_dn'], $_SESSION['settings']['ldap_bind_passwd'] );
                    if ($ldapbind) {
                        $filter="(&(" . $_SESSION[settings][ldap_user_attribute]. "=$username)(objectClass=posixAccount))";
                        $result=ldap_search($ldapconn, $_SESSION['settings']['ldap_search_base'], $filter, array('dn'));
                        if (ldap_count_entries($ldapconn, $result)) {
                            // try auth
                            $result = ldap_get_entries($ldapconn, $result);
                            $user_dn = $result[0]['dn'];
                            $ldapbind = ldap_bind($ldapconn, $user_dn, $passwordClear );
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
                        'use_ssl : '.$_SESSION['settings']['ldap_ssl']."\n" .
                        'use_tls : '.$_SESSION['settings']['ldap_tls']."\n*********\n\n"
                    );
                }
                $adldap = new SplClassLoader('LDAP\adLDAP', '../includes/libraries');
                $adldap->register();

                // Posix style LDAP handles user searches a bit differently
                if ($_SESSION['settings']['ldap_type'] == 'posix') {
                    $ldap_suffix = ','.$_SESSION['settings']['ldap_suffix'].','.$_SESSION['settings']['ldap_domain_dn'];
                }
                elseif ($_SESSION['settings']['ldap_type'] == 'windows') {
                    $ldap_suffix = $_SESSION['settings']['ldap_suffix'];
                }
                $adldap = new LDAP\adLDAP\adLDAP(
                    array(
                        'base_dn' => $_SESSION['settings']['ldap_domain_dn'],
                        'account_suffix' => $ldap_suffix,
                        'domain_controllers' => explode(",", $_SESSION['settings']['ldap_domain_controler']),
                        'use_ssl' => $_SESSION['settings']['ldap_ssl'],
                        'use_tls' => $_SESSION['settings']['ldap_tls']
                    )
                );

                /*try {
                    $adldap = new SplClassLoader('LDAP\adLDAP', '../includes/libraries');
                $adldap->register();
                    $adldap = new LDAP\adLDAP\adLDAP( array(
                            'base_dn' => $_SESSION['settings']['ldap_domain_dn'],
                            'account_suffix' => $_SESSION['settings']['ldap_suffix'],
                            'domain_controllers' => array( $_SESSION['settings']['ldap_domain_controler'] ),
                            'use_ssl' => $_SESSION['settings']['ldap_ssl'],
                            'use_tls' => $_SESSION['settings']['ldap_tls']
                    ) );
                }
                catch(Exception $e)
                {
                    echo $e->getMessage();
                }*/
                /*$adldap = new adLDAP( array(
                          'base_dn' => $_SESSION['settings']['ldap_domain_dn'],
                          'account_suffix' => $_SESSION['settings']['ldap_suffix'],
                          'domain_controllers' => array( $_SESSION['settings']['ldap_domain_controler'] ),
                          'use_ssl' => $_SESSION['settings']['ldap_ssl'],
                          'use_tls' => $_SESSION['settings']['ldap_tls']
                ) );*/
                if ($debugLdap == 1) {
                    fputs($dbgLdap, "Create new adldap object : ".$adldap->get_last_error()."\n\n\n"); //Debug
                }

                // openLDAP expects an attribute=value pair
                if ($_SESSION['settings']['ldap_type'] == 'posix') {
                    $auth_username = $_SESSION['settings']['ldap_user_attribute'].'='.$username;
                }
                else {
                    $auth_username = $username;
                }

                // authenticate the user
                if ($adldap->authenticate($auth_username, $passwordClear)) {
                    $ldapConnection = true;
                } else {
                    $ldapConnection = false;
                }
                if ($debugLdap == 1) {
                    fputs(
                        $dbgLdap,
                        "After authenticate : ".$adldap->get_last_error()."\n\n\n" .
                        "ldap status : ".$ldapConnection."\n\n\n"
                    ); //Debug
                }
            }
        } else if (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 2) {
            // nothing
        }
        // Check if user exists
        $data = DB::queryFirstRow("SELECT * FROM ".$pre."users WHERE login=%s_login",
            array(
                'login' => $username
            )
        );
        $counter = DB::count();
        if ($counter == 0) {
            echo '[{"value" : "error", "text":"user_not_exists"}]';
            exit;
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
            } elseif (crypt($psk, $data['psk']) != $data['psk']) {
                echo '[{"value" : "bad_psk"}]';
                //echo $psk." - ".crypt($psk, $data['psk']) ." - ". $data['psk']." - ".bCrypt(htmlspecialchars_decode($psk), COST);
                exit;
            }
        }

        $proceedIdentification = false;
        if ($counter > 0) {
            $proceedIdentification = true;
        } elseif ($counter == 0 && $ldapConnection == true && isset($_SESSION['settings']['ldap_elusers'])
                && ($_SESSION['settings']['ldap_elusers'] == 0)
        ) {
            // If LDAP enabled, create user in CPM if doesn't exist
            DB::insert(
                $pre.'users',
                array(
                    'login' => $username,
                    'pw' => $password,
                    'email' => "",
                    'admin' => '0',
                    'gestionnaire' => '0',
                    'personal_folder' => $_SESSION['settings']['enable_pf_feature'] == "1" ? '1' : '0',
                    'fonction_id' => '0',
                    'groupes_interdits' => '0',
                    'groupes_visibles' => '0',
                    'last_pw_change' => time()
                )
            );
            $newUserId = DB::insertId();
            // Create personnal folder
            if ($_SESSION['settings']['enable_pf_feature'] == "1") {
                DB::insert(
                    $pre."nested_tree",
                    array(
                        'parent_id' => '0',
                        'title' => $newUserId,
                        'bloquer_creation' => '0',
                        'bloquer_modification' => '0',
                        'personal_folder' => '1'
                       )
                );
            }
            // Get info for user
            //$sql = "SELECT * FROM ".$pre."users WHERE login = '".addslashes($username)."'";
            //$row = $db->query($sql);
            $proceedIdentification = true;
        }

    	// check GA code
    	if (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1 && $username != "admin") {
	    	if (isset($dataReceived['GACode']) && !empty($dataReceived['GACode'])) {
	    		include_once($_SESSION['settings']['cpassman_dir']."/includes/libraries/Authentication/GoogleAuthenticator/FixedBitNotation.php");
	    		include_once($_SESSION['settings']['cpassman_dir']."/includes/libraries/Authentication/GoogleAuthenticator/GoogleAuthenticator.php");
	    		$g = new Authentication\GoogleAuthenticator\GoogleAuthenticator();

	    		if ($g->checkCode($data['ga'], $dataReceived['GACode'])) {
	    			$proceedIdentification = true;
	    		} else {
	    			$proceedIdentification = false;
	    			$logError = "ga_code_wrong";
	    		}
	    	} else {
	    		$proceedIdentification = false;
	    		$logError = "ga_code_wrong";
	    	}
    	}

        if ($proceedIdentification === true) {
            // User exists in the DB
            //$data = $db->fetchArray($row);

            //v2.1.17 -> change encryption for users password
            if (
                    $passwordOldEncryption == $data['pw'] &&
                    !empty($data['pw'])
            ) {
                //update user's password
                $data['pw'] = bCrypt($passwordClear, COST);
                DB::update($pre.'users',
                        array(
                        'pw' => $data['pw']
                    ),
                    "id=%i",
                    $data['id']
                );
            }

            // Can connect if
            // 1- no LDAP mode + user enabled + pw ok
            // 2- LDAP mode + user enabled + ldap connection ok + user is not admin
            // 3-  LDAP mode + user enabled + pw ok + usre is admin
            // This in order to allow admin by default to connect even if LDAP is activated
            if (
                (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 0
                    && crypt($passwordClear, $data['pw']) == $data['pw'] && $data['disabled'] == 0
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
                    && $username == "admin" && crypt($passwordClear, $data['pw']) == $data['pw']
                    && $data['disabled'] == 0
                )
            ) {
                $_SESSION['autoriser'] = true;

                //Load PWGEN
                $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
                $pwgen->register();
                $pwgen = new Encryption\PwGen\pwgen();

                // Generate a ramdom ID
                $key = "";
                $pwgen->setLength(50);
                $pwgen->setSecure(true);
                $pwgen->setSymbols(false);
                $pwgen->setCapitalize(true);
                $pwgen->setNumerals(true);
                $key = $pwgen->generate();
                // Log into DB the user's connection
                if (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1) {
                    logEvents('user_connection', 'connection', $data['id']);
                }
                // Save account in SESSION
                $_SESSION['login'] = stripslashes($username);
                $_SESSION['name'] = stripslashes($data['name']);
                $_SESSION['lastname'] = stripslashes($data['lastname']);
                $_SESSION['user_id'] = $data['id'];
                $_SESSION['user_admin'] = $data['admin'];
                $_SESSION['user_manager'] = $data['gestionnaire'];
                $_SESSION['user_read_only'] = $data['read_only'];
                $_SESSION['last_pw_change'] = $data['last_pw_change'];
                $_SESSION['last_pw'] = $data['last_pw'];
                $_SESSION['can_create_root_folder'] = $data['can_create_root_folder'];
                $_SESSION['key'] = $key;
                $_SESSION['personal_folder'] = $data['personal_folder'];
                $_SESSION['fin_session'] = time() + $dataReceived['duree_session'] * 60;
                $_SESSION['user_language'] = $data['user_language'];
                $_SESSION['user_email'] = $data['email'];
            	$_SESSION['user']['ga'] = $data['ga'];

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

                @syslog(
                    LOG_WARNING,
                    "User logged in - ".$_SESSION['user_id']." - ".date("Y/m/d H:i:s").
                    " {$_SERVER['REMOTE_ADDR']} ({$_SERVER['HTTP_USER_AGENT']})"
                );
                // user type
                if ($_SESSION['user_admin'] == 1) {
                    $_SESSION['user_privilege'] = $LANG['god'];
                } elseif ($_SESSION['user_manager'] == 1) {
                    $_SESSION['user_privilege'] = $LANG['gestionnaire'];
                } elseif ($_SESSION['user_read_only'] == 1) {
                    $_SESSION['user_privilege'] = $LANG['read_only_account'];
                } else {
                    $_SESSION['user_privilege'] = $LANG['user'];
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
                    $resRoles = DB::queryFirstRow("SELECT title, complexity FROM ".$pre."roles_title WHERE id=%i", $role);
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
                $rows = DB::query("SELECT id, title FROM ".$pre."roles_title ORDER BY title ASC");
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
                    $pre.'users',
                    array(
                        'key_tempo' => $_SESSION['key'],
                        'last_connexion' => time(),
                        'timestamp' => time(),
                        'disabled' => 0,
                        'no_bad_attempts' => 0,
                        'session_end' => $_SESSION['fin_session'],
                        'psk' => bCrypt(htmlspecialchars_decode($psk), COST)
                    ),
                    "id=%i",
                    $data['id']
                );
                // Get user's rights
                identifyUserRights(
                    $data['groupes_visibles'],
                    $_SESSION['groupes_interdits'],
                    $data['admin'],
                    $data['fonction_id'],
                    false
                );
                // Get some more elements
                $_SESSION['screenHeight'] = $dataReceived['screenHeight'];
                // Get last seen items
                $_SESSION['latest_items_tab'][] = "";
                foreach ($_SESSION['latest_items'] as $item) {
                    if (!empty($item)) {
                        $data = DB::queryFirstRow("SELECT id,label,id_tree FROM ".$pre."items WHERE id=%i", $item);
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
                    $rows = DB::query("SELECT email FROM ".$pre."users WHERE admin = %i", 1);
                    foreach ($rows as $record) {
                        if (empty($receivers)) {
                            $receivers = $record['email'];
                        } else {
                            $receivers = ",".$record['email'];
                        }
                    }
                    // Add email to table
                    DB::insert(
                        $pre.'emails',
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
                                    " ".$_SESSION['login'],
                                    date($_SESSION['settings']['date_format'], $_SESSION['derniere_connexion']),
                                    date($_SESSION['settings']['time_format'], $_SESSION['derniere_connexion'])
                                ),
                                $LANG['email_body_on_user_login']
                            ),
                            'receivers' => $receivers,
                            'status' => "not sent"
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
                        logEvents('user_locked', 'connection', $data['id']);
                    }
                }
                DB::update(
                    $pre.'users',
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
            $return = "false";
        }
        echo '[{"value" : "'.$return.'", "user_admin":"',
            isset($_SESSION['user_admin']) ? $_SESSION['user_admin'] : "",
            '", "initial_url" : "'.@$_SESSION['initial_url'].'",
			"error" : "'.$logError.'"}]';
        $_SESSION['initial_url'] = "";
        break;
    /**
     * Increase the session time of User
     */
    case "increase_session_time":
        // check if session is not already expired.
        if ($_SESSION['fin_session'] > time()) {
            // Calculate end of session
            $_SESSION['fin_session'] = $_SESSION['fin_session'] + 3600;
            // Update table
            DB::update(
                $pre."users",
                array(
                    'session_end' => $_SESSION['fin_session']
                ),
                "id = %i",
                $_SESSION['user_id']
            );
            // Return data
            echo '[{"new_value":"'.$_SESSION['fin_session'].'"}]';
        } else {
            echo '[{"new_value":"expired"}]';
        }
        break;
    /**
     * Hide maintenance message
     */
    case "hide_maintenance":
        $_SESSION['hide_maintenance'] = 1;
        break;
    /**
     * Used in order to send the password to the user by email
     */
    case "send_pw_by_email":
        //Load PWGEN
        $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
        $pwgen->register();
        $pwgen = new Encryption\PwGen\pwgen();
        // Generate a ramdom ID
        $key = "";
        $pwgen->setLength(50);
        $pwgen->setSecure(true);
        $pwgen->setSymbols(false);
        $pwgen->setCapitalize(true);
        $pwgen->setNumerals(true);
        $key = $pwgen->generate();

        // Get account and pw associated to email
        DB::query(
            "SELECT $ FROM ".$pre."users WHERE email = %s",
            mysql_real_escape_string(stripslashes($_POST['email']))
        );
        $counter = DB::count();
        if ($counter != 0) {
            $data = DB::query(
                "SELECT login,pw FROM ".$pre."users WHERE email = %s",
                mysql_real_escape_string(stripslashes($_POST['email']))
            );

        	$textMail = $LANG['forgot_pw_email_body_1']." <a href=\"".
        	    $_SESSION['settings']['cpassman_url']."/index.php?action=password_recovery&key=".$key.
        	    "&login=".mysql_real_escape_string($_POST['login'])."\">".$_SESSION['settings']['cpassman_url'].
        	    "/index.php?action=password_recovery&key=".$key."&login=".mysql_real_escape_string($_POST['login'])."</a>.<br><br>".$LANG['thku'];
        	$textMailAlt = $LANG['forgot_pw_email_altbody_1']." ".$LANG['at_login']." : ".mysql_real_escape_string($_POST['login'])." - ".
        	    $LANG['index_password']." : ".md5($data['pw']);

            // Check if email has already a key in DB
            $data = $db->query(
                "SELECT * FROM ".$pre."misc WHERE intitule = %s AND type = %s",
                mysql_real_escape_string($_POST['login']),
                "password_recovery"
            );
            $counter = DB::count();
            if ($counter != 0) {
                DB::update(
                    $pre."misc",
                    array(
                        'valeur' => $key
                    ),
                    "type = %s and intitule = %s",
                    "password_recovery",
                    mysql_real_escape_string($_POST['login'])
                );
            } else {
                // store in DB the password recovery informations
                DB::insert(
                    $pre.'misc',
                    array(
                        'type' => 'password_recovery',
                        'intitule' => mysql_real_escape_string($_POST['login']),
                        'valeur' => $key
                    )
                );
            }

            echo '[{'.sendEmail($LANG['forgot_pw_email_subject'], $textMail, $_POST['email'], $textMailAlt).'}]';
        } else {
            // no one has this email ... alert
            echo '[{"error":"error_email" , "message":"'.$LANG['forgot_my_pw_error_email_not_exist'].'"}]';
        }
        break;
    // Send to user his new pw if key is conform
    case "generate_new_password":
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData($_POST['data'], "decode");
        // Prepare variables
        $login = htmlspecialchars_decode($dataReceived['login']);
        $key = htmlspecialchars_decode($dataReceived['key']);
        // check if key is okay
        $data = DB::queryFirstRow(
            "SELECT valeur FROM ".$pre."misc WHERE intitule = %s AND type = %s",
            mysql_real_escape_string($login),
            "password_recovery"
        );
        if ($key == $data['valeur']) {
            //Load PWGEN
            $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
            $pwgen->register();
            $pwgen = new Encryption\PwGen\pwgen();

            // Generate and change pw
            $newPw = "";
            $pwgen->setLength(10);
            $pwgen->setSecure(true);
            $pwgen->setSymbols(false);
            $pwgen->setCapitalize(true);
            $pwgen->setNumerals(true);
            $newPwNotCrypted = $pwgen->generate();
            $newPw = bCrypt(stringUtf8Decode($newPwNotCrypted), COST);
            // update DB
            DB::update(
                $pre."users",
                array(
                    'pw' => $newPw
                   ),
                "login = %s",
                mysql_real_escape_string($login)
            );
            // Delete recovery in DB
            DB::delete(
                $pre."misc",
                "type = %s AND intitule = %S AND valeur = %s",
                "password_recovery",
                mysql_real_escape_string($login),
                $key
            );
            // Get email
            $dataUser = DB::queryFirstRow(
                "SELECT email FROM ".$pre."users WHERE login = %s",
                mysql_real_escape_string($login)
            );

            $_SESSION['validite_pw'] = false;
            // send to user
            $ret = json_decode(
                @sendEmail(
                    $LANG['forgot_pw_email_subject_confirm'],
                    $LANG['forgot_pw_email_body']." ".$newPwNotCrypted,
                    $dataUser['email'],
                    strip_tags($LANG['forgot_pw_email_body'])." ".$newPwNotCrypted
                )
            );
            // send email
            if (empty($ret['error'])) {
                echo 'done';
            } else {
                echo $ret['message'];
            }
        }
        break;
    /**
     * Get the list of folders
     */
    case "get_folders_list":
        //Load Tree
        $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
        $tree->register();
        $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
        $folders = $tree->getDescendants();
        $arrOutput = array();

        /* Build list of all folders */
        $foldersList = "\'0\':\'".$LANG['root']."\'";
        foreach ($folders as $f) {
            // Be sure that user can only see folders he/she is allowed to
            if (!in_array($f->id, $_SESSION['forbiden_pfs'])) {
                $displayThisNode = false;
                // Check if any allowed folder is part of the descendants of this node
                $nodeDescendants = $tree->getDescendants($f->id, true, false, true);
                foreach ($nodeDescendants as $node) {
                    if (in_array($node, $_SESSION['groupes_visibles'])) {
                        $displayThisNode = true;
                        break;
                    }
                }

                if ($displayThisNode == true) {
                    if ($f->title == $_SESSION['user_id'] && $f->nlevel == 1) {
                        $f->title = $_SESSION['login'];
                    }
                    $arrOutput[$f->id] = $f->title;
                }
            }
        }
        echo json_encode($arrOutput, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
        break;
    /**
     * Store the personal saltkey
     */
    case "store_personal_saltkey":
        $dataReceived = json_decode(Encryption\Crypt\aesctr::decrypt(urldecode($_POST['sk']), $_SESSION['key'], 256), true);
        if ($dataReceived['psk'] != "**************************") {
            $_SESSION['my_sk'] = str_replace(" ", "+", urldecode($dataReceived['psk']));
            setcookie(
                "TeamPass_PFSK_".md5($_SESSION['user_id']),
                encrypt($_SESSION['my_sk'], ""),
                time() + 60 * 60 * 24 * $_SESSION['settings']['personal_saltkey_cookie_duration'],
                '/'
            );
        }
        break;
    /**
     * Change the personal saltkey
     */
    case "change_personal_saltkey":
        $oldPersonalSaltkey = $_SESSION['my_sk'];
        $newPersonalSaltkey = str_replace(" ", "+", urldecode($_POST['sk']));
        // Change encryption
        $rows = DB::query(
            "SELECT i.id as id, i.pw as pw
            FROM ".$pre."items as i
            INNER JOIN ".$pre."log_items as l ON (i.id=l.id_item)
            WHERE i.perso = %i AND l.id_user= %i AND l.action = %s",
            "1",
            $_SESSION['user_id'],
            "at_creation"
        );
        foreach ($rows as $record) {
            if (!empty($record['pw'])) {
                // get pw
                $pw = decrypt($record['pw'], $oldPersonalSaltkey);
                // encrypt
                $encryptedPw = encrypt($pw, $newPersonalSaltkey);
                // update pw in ITEMS table
                DB::update(
                    $pre.'items',
                    array(
                        'pw' => $encryptedPw
                       ),
                    "id=%i",
                    $record['id']
                );
            }
        }
        // change salt
        $_SESSION['my_sk'] = $newPersonalSaltkey;
        setcookie(
            "TeamPass_PFSK_".md5($_SESSION['user_id']),
            encrypt($_SESSION['my_sk'], ""),
            time() + 60 * 60 * 24 * $_SESSION['settings']['personal_saltkey_cookie_duration'],
            '/'
        );
        break;
    /**
     * Reset the personal saltkey
     */
    case "reset_personal_saltkey":
        if (!empty($_SESSION['user_id']) && !empty($_POST['sk'])) {
            // delete all previous items of this user
            $rows = DB::query(
                "SELECT i.id as id
                FROM ".$pre."items as i
                INNER JOIN ".$pre."log_items as l ON (i.id=l.id_item)
                WHERE i.perso = %i AND l.id_user= %i AND l.action = %s",
                "1",
                $_SESSION['user_id'],
                "at_creation"
            );
            foreach ($rows as $record) {
                // delete in ITEMS table
                DB::delete($pre."items", "id = %i", $record['id']);
                // delete in LOGS table
                DB::delete($pre."log_items", "id_item = %i", $record['id']);
            }
            // change salt
            $_SESSION['my_sk'] = str_replace(" ", "+", urldecode($_POST['sk']));
            setcookie(
                "TeamPass_PFSK_".md5($_SESSION['user_id']),
                encrypt($_SESSION['my_sk'], ""),
                time() + 60 * 60 * 24 * $_SESSION['settings']['personal_saltkey_cookie_duration'],
                '/'
            );
        }
        break;
    /**
     * Change the user's language
     */
    case "change_user_language":
        if (!empty($_SESSION['user_id'])) {
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");
            // Prepare variables
            $language = $dataReceived['lang'];
            // update DB
            DB::update(
                $pre."users",
                array(
                    'user_language' => $language
                   ),
                "id = %i",
                $_SESSION['user_id']
            );
            $_SESSION['user_language'] = $language;
        	echo "done";
        } else {
            $_SESSION['user_language'] = $language;
            echo "done";
        }
        break;
    /**
     * Send emails not sent
     */
    case "send_wainting_emails":
        if (isset($_SESSION['settings']['enable_send_email_on_user_login'])
            && $_SESSION['settings']['enable_send_email_on_user_login'] == 1
            && isset($_SESSION['key'])
        ) {
            $row = DB::queryFirstRow(
                "SELECT valeur FROM ".$pre."misc WHERE type = %s AND intitule = %s",
                "cron",
                "sending_emails"
            );
            if ((time() - $row['valeur']) >= 300 || $row['valeur'] == 0) {
                //load library
                $mail = new SplClassLoader(
                    'Email\PhpMailer',
                    $_SESSION['settings']['cpassman_dir'].'/includes/libraries'
                );
                $mail->register();
                $mail = new Email\PhpMailer\PHPMailer();

                $mail->setLanguage("en", "../includes/libraries/Email/Phpmailer/language");
                $mail->isSmtp(); // send via SMTP
                $mail->Host = $_SESSION['settings']['email_smtp_server']; // SMTP servers
                $mail->SMTPAuth = $_SESSION['settings']['email_smtp_auth']; // turn on SMTP authentication
                $mail->Username = $_SESSION['settings']['email_auth_username']; // SMTP username
                $mail->Password = $_SESSION['settings']['email_auth_pwd']; // SMTP password
                $mail->From = $_SESSION['settings']['email_from'];
                $mail->FromName = $_SESSION['settings']['email_from_name'];
                $mail->WordWrap = 80; // set word wrap
                $mail->isHtml(true); // send as HTML
                $status = "";
                $rows = DB::query("SELECT * FROM ".$pre."emails WHERE status != %s", "sent");
                foreach ($rows as $record) {
                    // send email
                    $ret = json_decode(
                        @sendEmail(
                            $record['subject'],
                            $record['body'],
                            $record['receivers']
                        )
                    );

                    if (!empty($ret['error'])) {
                        $status = "not sent";
                    } else {
                        $status = "sent";
                    }
                    // update item_id in files table
                    DB::update(
                        $pre.'emails',
                        array(
                            'status' => $status
                           ),
                        "timestamp = %s",
                        $record['timestamp']
                    );
                    if ($status == "not sent") {
                        break;
                    }
                }
            }
            // update cron time
            DB::update(
                $pre."misc",
                array(
                    'valeur' => time()
                   ),
                "intitule = %s AND type = %s",
                "sending_emails",
                "cron"
            );
        }
        break;
    /**
     * Store error
     */
    case "store_error":
        if (!empty($_SESSION['user_id'])) {
            // update DB
            DB::insert(
                $pre."log_system",
                array(
                    'type' => 'error',
                    'date' => time(),
                    'label' => urldecode($_POST['error']),
                    'qui' => $_SESSION['user_id']
                )
            );
        }
        break;
    /**
     * Generate a password generic
     */
    case "generate_a_password":
    	if ($_POST['size'] > $_SESSION['settings']['pwd_maximum_length']) {
    		echo prepareExchangedData(
	    		array(
		    		"error_msg" => "Password length is too long!",
		    		"error" => "true"
	    		),
	    		"encode"
    		);
    		break;
    	}

        //Load PWGEN
        $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
        $pwgen->register();
        $pwgen = new Encryption\PwGen\pwgen();

    	$pwgen->setLength($_POST['size']);
    	if (isset($_POST['secure']) && $_POST['secure'] == "true") {
    		$pwgen->setSecure(true);
    		$pwgen->setSymbols(true);
    		$pwgen->setCapitalize(true);
    		$pwgen->setNumerals(true);
    	} else {
    		$pwgen->setSecure(($_POST['secure'] == "true")? true : false);
    		$pwgen->setNumerals(($_POST['numerals'] == "true")? true : false);
    		$pwgen->setCapitalize(($_POST['capitalize'] == "true")? true : false);
    		$pwgen->setSymbols(($_POST['symbols'] == "true")? true : false);
    	}

        echo prepareExchangedData(
        	array(
	        	"key" => $pwgen->generate(),
	        	"error" => ""
        	),
        	"encode"
        );
        break;
    /**
     * Check if user exists and send back if psk is set
     */
    case "check_login_exists":
        $data = DB::query(
            "SELECT login, psk FROM ".$pre."users
            WHERE login = %i",
            mysql_real_escape_string(stripslashes($_POST['userId']))
        );
        if (empty($data['login'])) {
            $userOk = false;
        } else {
            $userOk = true;
        }
        if (
            isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1
            && !empty($data['psk'])
        ) {
            $pskSet = true;
        } else {
            $pskSet = false;
        }

        echo '[{"login" : "'.$userOk.'", "psk":"'.$pskSet.'"}]';
        break;
}
