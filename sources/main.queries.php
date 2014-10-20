<?php
/**
 *
 * @file          main.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.21
 * @copyright     (c) 2009-2014 Nils Laumaillé
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
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (isset($_SESSION['user_id']) && !checkUser($_SESSION['user_id'], $_SESSION['key'], "home")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
} elseif (
    (isset($_SESSION['user_id']) && isset($_SESSION['key'])) ||
    (isset($_POST['type']) && $_POST['type'] == "change_user_language" && isset($_POST['data']))) {
    // continue
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
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
DB::$port = $port;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);

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

        // Prepare variables
        $newPw = bCrypt(htmlspecialchars_decode($dataReceived['new_pw']), COST);

        // User has decided to change is PW
        if (isset($_POST['change_pw_origine']) && $_POST['change_pw_origine'] == "user_change") {

            // check if expected security level is reached
            $data_roles = DB::queryfirstrow("SELECT fonction_id FROM ".$pre."users WHERE id = %i", $_SESSION['user_id']);
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
            // check if user is admin / Manager
            $userInfo = DB::queryFirstRow(
                "SELECT admin, gestionnaire
                FROM ".$pre."users
                WHERE id = %i",
                $_SESSION['user_id']
            );
            if ($userInfo['admin'] != 1 && $userInfo['gestionnaire'] != 1) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
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
            if (!empty($row['email']) && isset($_SESSION['settings']['enable_email_notification_on_user_pw_change']) && $_SESSION['settings']['enable_email_notification_on_user_pw_change'] == 1) {
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
    	$data = DB::queryfirstrow(
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
            "SELECT * FROM ".$pre."users WHERE email = %s",
            mysqli_escape_string($link, stripslashes($_POST['email']))
        );
        $counter = DB::count();
        if ($counter != 0) {
            $data = DB::query(
                "SELECT login,pw FROM ".$pre."users WHERE email = %s",
                mysqli_escape_string($link, stripslashes($_POST['email']))
            );
        	$textMail = $LANG['forgot_pw_email_body_1']." <a href=\"".
        	    $_SESSION['settings']['cpassman_url']."/index.php?action=password_recovery&key=".$key.
        	    "&login=".mysqli_escape_string($link, $_POST['login'])."\">".$_SESSION['settings']['cpassman_url'].
        	    "/index.php?action=password_recovery&key=".$key."&login=".mysqli_escape_string($link, $_POST['login'])."</a>.<br><br>".$LANG['thku'];
        	$textMailAlt = $LANG['forgot_pw_email_altbody_1']." ".$LANG['at_login']." : ".mysqli_escape_string($link, $_POST['login'])." - ".
        	    $LANG['index_password']." : ".md5($data['pw']);

            // Check if email has already a key in DB
            $data = DB::query(
                "SELECT * FROM ".$pre."misc WHERE intitule = %s AND type = %s",
                mysqli_escape_string($link, $_POST['login']),
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
                    mysqli_escape_string($link, $_POST['login'])
                );
            } else {
                // store in DB the password recovery informations
                DB::insert(
                    $pre.'misc',
                    array(
                        'type' => 'password_recovery',
                        'intitule' => mysqli_escape_string($link, $_POST['login']),
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
            mysqli_escape_string($link, $login),
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
                mysqli_escape_string($link, $login)
            );
            // Delete recovery in DB
            DB::delete(
                $pre."misc",
                "type = %s AND intitule = %s AND valeur = %s",
                "password_recovery",
                mysqli_escape_string($link, $login),
                $key
            );
            // Get email
            $dataUser = DB::queryFirstRow(
                "SELECT email FROM ".$pre."users WHERE login = %s",
                mysqli_escape_string($link, $login)
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
        //decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData($_POST['data'], "decode");

        //Prepare variables
        $newPersonalSaltkey = htmlspecialchars_decode($dataReceived['sk']);
        $oldPersonalSaltkey = htmlspecialchars_decode($dataReceived['old_sk']);
        if (empty($oldPersonalSaltkey)) {
            $oldPersonalSaltkey = $_SESSION['my_sk'];
        }

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
                require $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Email/Phpmailer/PHPMailerAutoload.php';
                // load PHPMailer
                $mail = new PHPMailer();

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
            mysqli_escape_string($link, stripslashes($_POST['userId']))
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
    /**
     * Make statistics on item
     */
    case "item_stat":
        if (isset($_POST['scope']) && $_POST['scope'] == "item") {
            $data = DB::queryfirstrow(
                "SELECT view FROM ".$pre."statistics WHERE scope = %s AND item_id = %i",
                'item',
                $_POST['id']
            );
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    $pre."statistics",
                    array(
                        'scope' => 'item',
                        'view' => '1',
                        'item_id' => $_POST['id']
                    )
                );
            } else {
                DB::update(
                    $pre."statistics",
                    array(
                        'scope' => 'item',
                        'view' => $data['view']+1
                    ),
                    "item_id = %i",
                    $_POST['id']
                );
            }
        }
        
        break;
}
