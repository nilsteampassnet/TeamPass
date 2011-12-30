<?php
/**
 * @file 		main.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$debug_ldap = 0;	//Can be used in order to debug LDAP authentication

session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

global $k, $settings;
include('../includes/settings.php');
header("Content-type: text/html; charset=utf-8");
error_reporting (E_ERROR);
require_once('main.functions.php');

// connect to the server
    require_once("class.database.php");
    $db = new Database($server, $user, $pass, $database, $pre);
    $db->connect();

//User's language loading
$k['langage'] = @$_SESSION['user_language'];
require_once('../includes/language/'.$_SESSION['user_language'].'.php');

//Manage type of action asked
switch($_POST['type'])
{
    case "change_pw":

    	//decrypt and retreive data in JSON format
    	require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
    	require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
    	$data_received = json_decode(AesCtr::decrypt($_POST['data'], $_SESSION['key'], 256), true);

    	//Prepare variables
    	$new_pw = encrypt(htmlspecialchars_decode($data_received['new_pw']));

			if(isset($_POST['change_pw_origine']) && $_POST['change_pw_origine'] == "user_change"){
				//User has decided to change is PW

	      //Get a string with the old pw array
	      $last_pw = explode(';',$_SESSION['last_pw']);

        //if size is bigger then clean the array
        if ( sizeof($last_pw) > $_SESSION['settings']['number_of_used_pw'] && $_SESSION['settings']['number_of_used_pw'] > 0 ){
            for($x=0;$x<$_SESSION['settings']['number_of_used_pw'];$x++)
                unset($last_pw[$x]);

            //reinit SESSION
            $_SESSION['last_pw'] = implode(';',$last_pw);
        }
        //specific case where admin setting "number_of_used_pw" is 0
        else if ( $_SESSION['settings']['number_of_used_pw'] == 0 ){
            $_SESSION['last_pw'] = "";
            $last_pw = array();
        }

        //check if new pw is different that old ones
        if ( in_array($new_pw,$last_pw) ){
        	echo '[ { "error" : "already_used" } ]';
        }else{
            //update old pw with new pw
            if ( sizeof($last_pw) == ($_SESSION['settings']['number_of_used_pw']+1) ){
                unset($last_pw[0]);
            }else{
                array_push($last_pw,$new_pw);
            }

            //create a list of last pw based on the table
            $old_pw = "";
            foreach($last_pw as $elem){
                if ( !empty($elem) ){
                    if (empty($old_pw)) $old_pw = $elem;
                    else $old_pw .= ";".$elem;
                }
            }

            //update sessions
            $_SESSION['last_pw'] = $old_pw;
            $_SESSION['last_pw_change'] = mktime(0,0,0,date('m'),date('d'),date('y'));
            $_SESSION['validite_pw'] = true;

            //update DB
            $db->query_update(
                "users",
                array(
                    'pw' => $new_pw,
                    'last_pw_change' => mktime(0,0,0,date('m'),date('d'),date('y')),
                    'last_pw' => $old_pw
                ),
                "id = ".$_SESSION['user_id']
            );

            echo '[ { "error" : "none" } ]';
        }
			}else
			//ADMIN has decided to change the USER's PW
			if(isset($_POST['change_pw_origine']) && $_POST['change_pw_origine'] == "admin_change"){
				//Check KEY
      	if ($data_received['key'] != $_SESSION['key']) {
      		echo '[ { "error" : "key_not_conform" } ]';
      		exit();
      	}

				//update DB
        $db->query_update(
            "users",
            array(
                'pw' => $new_pw,
                'last_pw_change' => mktime(0,0,0,date('m'),date('d'),date('y'))
            ),
            "id = ".$data_received['user_id']
        );

        echo '[ { "error" : "none" } ]';
			}

			else{
				echo '[ { "error" : "nothing_to_do" } ]';
			}

    break;

    case "identify_user":
        require_once ("main.functions.php");
        require_once ("../sources/NestedTree.class.php");

    	//decrypt and retreive data in JSON format
    	require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
    	require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
    	$data_received = json_decode((AesCtr::decrypt($_POST['data'], SALT, 256)), true);

    	//Prepare variables
    	$password_clear = htmlspecialchars_decode($data_received['pw']);
	    $password = encrypt(htmlspecialchars_decode($data_received['pw']));
    	$username = htmlspecialchars_decode($data_received['login']);

		//GET SALT KEY LENGTH
        if ( strlen(SALT) > 32) {
            $_SESSION['error']['salt'] = TRUE;
        }

        $_SESSION['user_language'] = $k['langage'];
        $ldap_connection = false;

        //Build tree of folders
    	$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

        /* LDAP connection */
    	if ($debug_ldap == 1) {
    		$dbg_ldap = fopen("../files/ldap.debug.txt","w");	//create temp file
    	}

        if ( isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 && $username != "admin" ){
        	if ($debug_ldap == 1) {
        		fputs($dbg_ldap, "Get all ldap params : \n".
	        		'base_dn : ' . $_SESSION['settings']['ldap_domain_dn'] . "\n".
	        		'account_suffix : ' . $_SESSION['settings']['ldap_suffix'] . "\n".
	        		'domain_controllers : ' . $_SESSION['settings']['ldap_domain_controler'] . "\n".
	        		'use_ssl : ' . $_SESSION['settings']['ldap_ssl'] . "\n".
	        		'use_tls : ' . $_SESSION['settings']['ldap_tls'] . "\n*********\n\n"
	        	);
        	}

            require_once ("../includes/libraries/adLDAP/adLDAP.php");
            $adldap = new adLDAP(array(
            	'base_dn' => $_SESSION['settings']['ldap_domain_dn'],
	            'account_suffix' => $_SESSION['settings']['ldap_suffix'],
	            'domain_controllers' => array($_SESSION['settings']['ldap_domain_controler']),
	            'use_ssl' => $_SESSION['settings']['ldap_ssl'],
	            'use_tls' => $_SESSION['settings']['ldap_tls']
            ));
        	if ($debug_ldap == 1) {
        		fputs($dbg_ldap, "Create new adldap object : ".$adldap->get_last_error()."\n\n\n");	//Debug
        	}

            //authenticate the user
            if ($adldap -> authenticate($username,$password_clear)){
                $ldap_connection = true;
            }else{
                $ldap_connection = false;
            }
        	if ($debug_ldap == 1) {
        		fputs($dbg_ldap, "After authenticate : ".$adldap->get_last_error()."\n\n\n".
        		"ldap status : ".$ldap_connection."\n\n\n");	//Debug
        	}
        }

    	//Check if user exists in cpassman
        $sql="SELECT * FROM ".$pre."users WHERE login = '".$username."'";
        $row = $db->query($sql);
    	$proceed_identification = false;
        if (mysql_num_rows($row) > 0 ){
        	$proceed_identification = true;
         }
    	elseif (mysql_num_rows($row) == 0 && $ldap_connection == true) {
    		//If LDAP enabled, create user in CPM if doesn't exist
             $new_user_id = $db->query_insert(
                 "users",
                  array(
                      'login' => $username,
                      'pw' => $password,
                      'email' => "",
                      'admin' => '0',
                      'gestionnaire' => '0',
                      'personal_folder' =>  $_SESSION['settings']['enable_pf_feature']=="1" ? '1' : '0',
                      'fonction_id' =>  '0',
                      'groupes_interdits' =>  '0',
                      'groupes_visibles' =>  '0',
                      'last_pw_change' => mktime(date('h'),date('m'),date('s'),date('m'),date('d'),date('y')),
                      )
                  );

    		//Create personnal folder
    		if ( $_SESSION['settings']['enable_pf_feature']=="1" )
    			$db->query_insert(
	    			"nested_tree",
	    			array(
	    			    'parent_id' => '0',
	    			    'title' => $new_user_id,
	    			    'bloquer_creation' => '0',
	    			    'bloquer_modification' => '0',
	    			    'personal_folder' => '1'
	    			)
    			);

    		//Get info for user
			$sql="SELECT * FROM ".$pre."users WHERE login = '".$username."'";
			$row = $db->query($sql);
			$proceed_identification = true;
         }

         if ($proceed_identification === true){
            //User exists in the DB
            $data = $db->fetch_array($row);

        	// Can connect if
        	// 1- no LDAP mode + user enabled + pw ok
        	// 2- LDAP mode + user enabled + ldap connection ok + user is not admin
        	// 3-  LDAP mode + user enabled + pw ok + usre is admin
        	// This in order to allow admin by default to connect even if LDAP is activated
            if (
            	(isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 0 && $password == $data['pw'] && $data['disabled'] == 0)
             	||
             	(isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 && $ldap_connection == true && $data['disabled'] == 0 && $username != "admin")
            	||
            	(isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 && $username == "admin" && $password == $data['pw'] && $data['disabled'] == 0)
            ) {
                $_SESSION['autoriser'] = true;

                // Generate a ramdom ID
                $key = "";
                include('../includes/libraries/pwgen/pwgen.class.php');
	            $pwgen = new PWGen();
	            $pwgen->setLength(50);
	            $pwgen->setSecure(true);
                $pwgen->setSymbols(false);
                $pwgen->setCapitalize(true);
                $pwgen->setNumerals(true);
	            $key = $pwgen->generate();

                //Log into DB the user's connection
                if ( isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 )
                    logEvents('user_connection','connection',$data['id']);

                //Save account in SESSION
                $_SESSION['login'] = stripslashes($username);
                $_SESSION['user_id'] = $data['id'];
                $_SESSION['user_admin'] = $data['admin'];
                $_SESSION['user_gestionnaire'] = $data['gestionnaire'];
                $_SESSION['user_read_only'] = $data['read_only'];
                $_SESSION['last_pw_change'] = $data['last_pw_change'];
                $_SESSION['last_pw'] = $data['last_pw'];
                $_SESSION['can_create_root_folder'] = $data['can_create_root_folder'];
	            $_SESSION['key'] = $key;
	            $_SESSION['personal_folder'] = $data['personal_folder'];
                $_SESSION['fin_session'] = time() + $data_received['duree_session'] * 60;
                $_SESSION['user_language'] = $data['user_language'];

            	//user type
            	if($_SESSION['user_admin'] == 1) $_SESSION['user_privilege'] = $txt['god'];
            	else if($_SESSION['user_gestionnaire'] == 1) $_SESSION['user_privilege'] = $txt['gestionnaire'];
            	else if($_SESSION['user_read_only'] == 1) $_SESSION['user_privilege'] = $txt['read_only_account'];
            	else $_SESSION['user_privilege'] = $txt['user'];

                if ( empty($data['last_connexion']) ) $_SESSION['derniere_connexion'] = mktime(date('h'),date('m'),date('s'),date('m'),date('d'),date('y'));
                else $_SESSION['derniere_connexion'] = $data['last_connexion'];
                if ( !empty($data['latest_items']) ) $_SESSION['latest_items'] = explode(';',$data['latest_items']);
                else $_SESSION['latest_items'] = array();
                if ( !empty($data['favourites']) ) $_SESSION['favourites'] = explode(';',$data['favourites']);
                else $_SESSION['favourites'] = array();


        	    if (!empty($data['groupes_visibles'])) {
        		    $_SESSION['groupes_visibles'] = @implode(';',$data['groupes_visibles']);
        	    }else{
        		    $_SESSION['groupes_visibles'] = array();
        	    }
        	    if (!empty($data['groupes_interdits'])) {
        		    $_SESSION['groupes_interdits'] = @implode(';',$data['groupes_interdits']);
        	    }else{
        		    $_SESSION['groupes_interdits'] = array();
        	    }
				//User's roles
               	$_SESSION['fonction_id'] = $data['fonction_id'];
               	$_SESSION['user_roles'] = explode(";", $data['fonction_id']);


       		    //build array of roles
            	$_SESSION['user_pw_complexity'] = 0;
       		    $_SESSION['arr_roles'] = array();
        	    foreach(array_filter(explode(';', $_SESSION['fonction_id'])) as $role){
       			    $res_roles = $db->query_first("SELECT title, complexity FROM ".$pre."roles_title WHERE id = ".$role);
       			    $_SESSION['arr_roles'][$role] = array(
       				    'id' => $role,
       				    'title' => $res_roles['title']
       			    );
        	    	//get highest complexity
        	    	if($_SESSION['user_pw_complexity'] < $res_roles['complexity']) $_SESSION['user_pw_complexity'] = $res_roles['complexity'];
        	    }

           		//build complete array of roles
       		    $_SESSION['arr_roles_full'] = array();
            	$rows = $db->fetch_all_array("
								SELECT id, title
								FROM ".$pre."roles_title A
								ORDER BY title ASC");
            	foreach ($rows as $reccord){
            		$_SESSION['arr_roles_full'][$reccord['id']] = array(
       				    'id' => $reccord['id'],
       				    'title' => $reccord['title']
       			    );
            	}

            	//Set some settings
                $_SESSION['user']['find_cookie'] = false;
                $_SESSION['settings']['update_needed'] = "";

                // Update table
                $db->query_update(
                    "users",
                    array(
                        'key_tempo'=>$_SESSION['key'],
                    	'last_connexion'=>mktime(date("h"),date("i"),date("s"),date("m"),date("d"),date("Y")),
                    	'timestamp'=>mktime(date("h"),date("i"),date("s"),date("m"),date("d"),date("Y")),
                        'disabled'=>0,
                        'no_bad_attempts'=>0
                    ),
                    "id=".$data['id']
                );

                //Get user's rights
                IdentifyUserRights($data['groupes_visibles'],$_SESSION['groupes_interdits'],$data['admin'],$data['fonction_id'],false);

                //Get some more elements
                $_SESSION['screenHeight'] = $data_received['screenHeight'];

                //Get last seen items
                $_SESSION['latest_items_tab'][] = "";
                foreach($_SESSION['latest_items'] as $item){
                    if ( !empty($item) ){
                        $data = $db->query_first("SELECT label,id_tree FROM ".$pre."items WHERE id = ".$item);
                        $_SESSION['latest_items_tab'][$item] = array(
                            'label'=>$data['label'],
                            'url'=>'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$item
                        );
                    }
                }
                //send back the random key
                $return = $data_received['randomstring'];

            	//Send email
            	if(isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 && $_SESSION['user_admin'] != 1){
            		require_once("../includes/libraries/phpmailer/class.phpmailer.php");
            		//get all Admin users
            		$receivers = "";
            		$rows = $db->fetch_all_array("SELECT email FROM ".$pre."users WHERE admin = 1");
            		foreach( $rows as $reccord ) {
            			if(empty($receivers)) $receivers = $reccord['email'];
            			else $receivers = ",".$reccord['email'];
            		}
            		//Add email to table
            		$db->query_insert(
	            		'emails',
	            		array(
	            		    'timestamp' => mktime(date('h'),date('m'),date('s'),date('m'),date('d'),date('y')),
	            		    'subject' => $txt['email_subject_on_user_login'],
		            		'body' => str_replace(array(' #tp_user#', '#tp_date#', '#tp_time#'), array($_SESSION['login'], date($_SESSION['settings']['date_format'], $_SESSION['derniere_connexion']), date($_SESSION['settings']['time_format'], $_SESSION['derniere_connexion'])), $txt['email_body_on_user_login']),
		            		'receivers' => $receivers,
		            		'status' => "not sent"
	            		)
            		);
            	}
            }
            else if ($data['disabled'] == 1) {
                //User and password is okay but account is locked
                $return = "user_is_locked";
            }
            else{
                //User exists in the DB but Password is false
                //check if user is locked
                $user_is_locked = 0;
                $nb_attempts = intval($data['no_bad_attempts'] + 1);
                if ($_SESSION['settings']['nb_bad_authentication'] > 0 && intval($_SESSION['settings']['nb_bad_authentication']) < $nb_attempts) {
                    $user_is_locked = 1;

                    //log it
                    if ( isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 )
                    logEvents('user_locked','connection',$data['id']);
                }
                $db->query_update(
                    "users",
                    array(
                        'key_tempo'=>$_SESSION['key'],
                        'last_connexion'=>mktime(date("h"),date("i"),date("s"),date("m"),date("d"),date("Y")),
                        'disabled'=>$user_is_locked,
                        'no_bad_attempts'=>$nb_attempts
                    ),
                    "id=".$data['id']
                );

                //What return shoulb we do
                if ($user_is_locked == 1) {
                    $return = "user_is_locked";
                }else if ($_SESSION['settings']['nb_bad_authentication'] == 0) {
                    $return = "false";
                }else{
                    $return = $nb_attempts;
                }
            }
        }
        else{
            $return = "false";
        }
        echo $return;

    break;

    case "increase_session_time":
    	$_SESSION['fin_session'] = $_SESSION['fin_session']+3600;
    	echo '[{"new_value":"'.$_SESSION['fin_session'].'"}]';
    break;

    //Used in order to send the password to the user by email
    case "send_pw_by_email":
    	echo '$("#div_forgot_pw_alert").removeClass("ui-state-error");';
    	//found account and pw associated to email
    	$data = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."users WHERE email = '".mysql_real_escape_string(stripslashes(($_POST['email'])))."'");
    	if ( $data[0] != 0 ){
    		$data = $db->fetch_array("SELECT login,pw FROM ".$pre."users WHERE email = '".mysql_real_escape_string(stripslashes(($_POST['email'])))."'");

    		// Generate a ramdom ID
    		$key = "";
    		include('../includes/libraries/pwgen/pwgen.class.php');
    		$pwgen = new PWGen();
    		$pwgen->setLength(50);
    		$pwgen->setSecure(true);
    		$pwgen->setSymbols(false);
    		$pwgen->setCapitalize(true);
    		$pwgen->setNumerals(true);
    		$key = $pwgen->generate();

    		//load library
    		require_once("../includes/libraries/phpmailer/class.phpmailer.php");

    		//send to user
    		$mail = new PHPMailer();
    		$mail->SetLanguage("en","../includes/libraries/phpmailer/language/");
    		$mail->IsSMTP();	// send via SMTP
    		$mail->Host     = $smtp_server; // SMTP servers
    		$mail->SMTPAuth = $smtp_auth;     // turn on SMTP authentication
    		$mail->Username = $smtp_auth_username;  // SMTP username
    		$mail->Password = $smtp_auth_password; // SMTP password
    		$mail->From     = $email_from;
    		$mail->FromName = $email_from_name;
    		$mail->AddAddress($_POST['email']);     //Destinataire
    		$mail->WordWrap = 80;                              // set word wrap
    		$mail->IsHTML(true);                               // send as HTML
    		$mail->Subject  =  $txt['forgot_pw_email_subject'];
    		$mail->AltBody  =  $txt['forgot_pw_email_altbody_1']." ".$txt['at_login']." : ".$data['login']." - ".$txt['index_password']." : ".md5($data['pw']);
    		$mail->Body     =  $txt['forgot_pw_email_body_1']." ".$_SESSION['settings']['cpassman_url']."/index.php?action=password_recovery&key=".$key."&login=".$_POST['login'];

    		//Check if email has already a key in DB
    		$data = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."misc WHERE intitule = '".$_POST['login']."' AND type = 'password_recovery'");
    		if ( $data[0] != 0 ){
    			$db->query_update(
	    			"misc",
	    			array(
	    			    'valeur' => $key
	    			),
	    			array(
		    			'type' => 'password_recovery',
		    			'intitule' => $_POST['login']
		    		)
    			);
    		}else{
    			//store in DB the password recovery informations
    			$db->query_insert(
	    			'misc',
	    			array(
	    			    'type' => 'password_recovery',
	    			    'intitule' => $_POST['login'],
	    			    'valeur' => $key
	    			)
    			);
    		}

			//send email
    		if(!$mail->Send())
    		{
    			echo '[{"error":"error_mail_not_send" , "message":"'.$mail->ErrorInfo.'"}]';
    		}
    		else
    		{
    			echo '[{"error":"no" , "message":"'.$txt['forgot_my_pw_email_sent'].'"}]';
    		}
        }else{
            //no one has this email ... alert
        	echo '[{"error":"error_email" , "message":"'.$txt['forgot_my_pw_error_email_not_exist'].'"}]';
        }
    break;

    //Send to user his new pw if key is conform
    case "generate_new_password":
    	//check if key is okay
    	$data = $db->fetch_row("SELECT valeur FROM ".$pre."misc WHERE intitule = '".$_POST['login']."' AND type = 'password_recovery'");
    	if($_POST['key'] == $data[0]) {
    		//Generate and change pw
    		$new_pw = "";
    		include('../includes/libraries/pwgen/pwgen.class.php');
    		$pwgen = new PWGen();
    		$pwgen->setLength(10);
    		$pwgen->setSecure(true);
    		$pwgen->setSymbols(false);
    		$pwgen->setCapitalize(true);
    		$pwgen->setNumerals(true);
    		$new_pw_not_crypted = $pwgen->generate();
    		$new_pw = encrypt(string_utf8_decode($new_pw_not_crypted));

    		//update DB
    		$db->query_update(
	    		"users",
	    		array(
	    			'pw' => $new_pw
	    		),
	    		"login = '".$_POST['login']."'"
    		);

    		//Delete recovery in DB
    		$db->query_delete(
	    		"misc",
	    		array(
	    			'type' => 'password_recovery',
	    			'intitule' => $_POST['login'],
	    			'valeur' => $key
	    		)
    		);

    		//Get email
    		$data_user = $db->query_first("SELECT email FROM ".$pre."users WHERE login = '".$_POST['login']."'");

    		$_SESSION['validite_pw'] = false;

    		//load library
    		require_once("../includes/libraries/phpmailer/class.phpmailer.php");

    		//send to user
    		$mail = new PHPMailer();
    		$mail->SetLanguage("en","../includes/libraries/phpmailer/language/");
    		$mail->IsSMTP();						// send via SMTP
    		$mail->Host     = $smtp_server; 		// SMTP servers
    		$mail->SMTPAuth = $smtp_auth;     		// turn on SMTP authentication
    		$mail->Username = $smtp_auth_username;  // SMTP username
    		$mail->Password = $smtp_auth_password; 	// SMTP password
    		$mail->From     = $email_from;
    		$mail->FromName = $email_from_name;
    		$mail->AddAddress($data_user['email']); //Destinataire
    		$mail->WordWrap = 80;					// set word wrap
    		$mail->IsHTML(true);					// send as HTML
    		$mail->Subject  =  $txt['forgot_pw_email_subject_confirm'];
    		$mail->AltBody  =  strip_tags($txt['forgot_pw_email_body'])." ".$new_pw_not_crypted;
    		$mail->Body     =  $txt['forgot_pw_email_body']." ".$new_pw_not_crypted;

    		//send email
    		if($mail->Send())
    		{
    			echo 'done';
    		}
    		else
    		{
    			echo $mail->ErrorInfo;
    		}
    	}
    break;

    case "get_folders_list":
    	/* Get full tree structure */
    	require_once ("NestedTree.class.php");
    	$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
    	$folders = $tree->getDescendants();
    	$arrOutput = array();

		/* Build list of all folders */
    	$folders_list = "\'0\':\'".$txt['root']."\'";
		foreach($folders as $f){
			//Be sure that user can only see folders he/she is allowed to
			if ( !in_array($f->id,$_SESSION['forbiden_pfs']) ) {
				$display_this_node = false;
				// Check if any allowed folder is part of the descendants of this node
				$node_descendants = $tree->getDescendants($f->id, true, false, true);
				foreach ($node_descendants as $node){
					if (in_array($node, $_SESSION['groupes_visibles'])) {
						$display_this_node = true;
						break;
					}
				}

				if ($display_this_node == true) {
					if ( $f->title ==$_SESSION['user_id'] && $f->nlevel == 1 ) $f->title = $_SESSION['login'];
					$arrOutput[$f->id] = $f->title;
				}
			}
		}
    	echo json_encode($arrOutput,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
    	break;

    case "print_out_items":
    	$full_listing = array();

    	foreach (explode(';', $_POST['ids']) as $id){
    		if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {

	   			$rows = $db->fetch_all_array("
	                   SELECT i.id AS id, i.restricted_to AS restricted_to, i.perso AS perso, i.label AS label, i.description AS description, i.pw AS pw, i.login AS login,
	                       l.date AS date,
	                       n.renewal_period AS renewal_period,
	                       k.rand_key
	                   FROM ".$pre."items AS i
	                   INNER JOIN ".$pre."nested_tree AS n ON (i.id_tree = n.id)
	                   INNER JOIN ".$pre."log_items AS l ON (i.id = l.id_item)
	                   INNER JOIN ".$pre."keys AS k ON (i.id = k.id)
	                   WHERE i.inactif = 0
	                   AND i.id_tree=".$id."
	                   AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
	                   ORDER BY i.label ASC, l.date DESC
                ");

	   			$id_managed = '';
	   			$i = 0;
	   			$items_id_list = array();
	   			foreach( $rows as $reccord ) {
                    $restricted_users_array = explode(';',$reccord['restricted_to']);
	   				//exclude all results except the first one returned by query
	   				if ( empty($id_managed) || $id_managed != $reccord['id'] ){
	   					if (
                            (in_array($id, $_SESSION['personal_visible_groups']) && !($reccord['perso'] == 1 && $_SESSION['user_id'] == $reccord['restricted_to']) && !empty($reccord['restricted_to']))
                            ||
                            (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'],$restricted_users_array))
                        ){
	   						//exclude this case
	   					}else {
	   						//encrypt PW
	   						if ( !empty($_POST['salt_key']) && isset($_POST['salt_key']) ){
	   							$pw = decrypt($reccord['pw'], mysql_real_escape_string(stripslashes($_POST['salt_key'])));
	   						}else
	   							$pw = decrypt($reccord['pw']);

	   						$full_listing[$reccord['id']] = array(
		   						'id' => $reccord['id'],
		   						'label' => $reccord['label'],
		   						'pw' => substr(addslashes($pw), strlen($reccord['rand_key'])),
		   						'login' => $reccord['login']
							);
	   					}
	    			}
	   				$id_managed = $reccord['id'];
	   			}
   			}

    	}

    	//Build PDF
    	if (!empty($full_listing)) {
    		//Prepare the PDF file
    		include('../includes/libraries/tfpdf/tfpdf.php');
    		$pdf=new tFPDF();

    		//Add font for utf-8
    		$pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);

    		$pdf->AliasNbPages();
    		$pdf->AddPage();
    		$pdf->SetFont('DejaVu','',16);
    		$pdf->Cell(0,10,$txt['print_out_pdf_title'],0,1,'C',false);
    		$pdf->SetFont('DejaVu','',12);
    		$pdf->Cell(0,10,$txt['pdf_del_date']." ".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'],mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))).' '.$txt['by'].' '.$_SESSION['login'],0,1,'C',false);
    		$pdf->SetFont('DejaVu','',10);
    		$pdf->SetFillColor(192,192,192);
    		$pdf->cell(65,6,$txt['label'],1,0,"C",1);
    		$pdf->cell(55,6,$txt['login'],1,0,"C",1);
    		$pdf->cell(70,6,$txt['pw'],1,1,"C",1);
    		$pdf->SetFont('DejaVu','',9);

    		foreach( $full_listing as $item ){
   				$pdf->cell(65,6,stripslashes($item['label']),1,0,"L");
   				$pdf->cell(55,6,stripslashes($item['login']),1,0,"C");
   				$pdf->cell(70,6,stripslashes($item['pw']),1,1,"C");
    		}

    		$pdf_file = "print_out_pdf_".date("Y-m-d",mktime(0,0,0,date('m'),date('d'),date('y'))).".pdf";
    		//send the file
    		$pdf->Output($_SESSION['settings']['cpassman_dir']."/files/".$pdf_file);

    		echo '[{"output":"'.$_SESSION['settings']['cpassman_url'].'/files/'.$pdf_file.'"}]';
    	}
    	break;

		case "store_personal_saltkey":
			if($_POST['sk'] != "**************************"){
				$_SESSION['my_sk'] = str_replace(" ","+",urldecode($_POST['sk']));
			}
			break;

		case "change_personal_saltkey":
			$old_personal_saltkey = $_SESSION['my_sk'];
			$new_personal_saltkey = str_replace(" ","+",urldecode($_POST['sk']));

			//Change encryption
			$rows = mysql_query(
				"SELECT i.id AS id, i.pw AS pw
				FROM ".$pre."items AS i
				INNER JOIN ".$pre."log_items AS l ON (i.id=l.id_item)
				WHERE i.perso = 1
				AND l.id_user=".$_SESSION['user_id']."
				AND l.action = 'at_creation'");
			while($reccord = mysql_fetch_array($rows)){
				if(!empty($reccord['pw'])){
					//get pw
					$pw = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $old_personal_saltkey, base64_decode($reccord['pw']), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
					//encrypt
					$encrypted_pw = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $new_personal_saltkey, $pw, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
					//update pw in ITEMS table
					mysql_query("UPDATE ".$pre."items SET pw = '".$encrypted_pw."' WHERE id='".$reccord['id']."'") or die(mysql_error());
				}
			}
			//change salt
			$_SESSION['my_sk'] = $new_personal_saltkey;
		break;

		case "change_user_language":
			if(!empty($_SESSION['user_id'])){
				//update DB
	            $db->query_update(
	                "users",
	                array(
	                    'user_language' => $_POST['lang']
	                ),
	                "id = ".$_SESSION['user_id']
	            );
			}
		break;

	case "send_wainting_emails":
		if(isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1){
			$row = $db->query_first("SELECT valeur FROM ".$pre."misc WHERE type='cron' AND intitule='sending_emails'");
			if((mktime(date('h'),date('m'),date('s'),date('m'),date('d'),date('y')) - $row['valeur']) >= 300 || $row['valeur'] == 0){

				require_once("../includes/libraries/phpmailer/class.phpmailer.php");
				$mail = new PHPMailer();
				$mail->SetLanguage("en","../includes/libraries/phpmailer/language");
				$mail->IsSMTP();						// send via SMTP
				$mail->Host     = $smtp_server;			// SMTP servers
				$mail->SMTPAuth = $smtp_auth;     		// turn on SMTP authentication
				$mail->Username = $smtp_auth_username;  // SMTP username
				$mail->Password = $smtp_auth_password; 	// SMTP password
				$mail->From     = $email_from;
				$mail->FromName = $email_from_name;
				$mail->WordWrap = 80;					// set word wrap
				$mail->IsHTML(true);					// send as HTML

				$rows = $db->fetch_all_array("SELECT * FROM ".$pre."emails WHERE status='not sent'");
				foreach ($rows as $reccord){
					//send email
					$mail->AddAddress($reccord['receivers']);     		//Receivers
					$mail->Subject  =  $reccord['subject'];
					$mail->AltBody	=  "";
					$mail->Body  =  $reccord['body'];
					//$mail->Send();
					if(!$mail->Send()) $status = "not sent";
					else $status = "sent";
					//update item_id in files table
					$db->query_update(
						'emails',
						array(
						    'status' => $status
						),
						"timestamp='".$reccord['timestamp']."'"
					);
				}
				//update cron time
				$db->query_update(
					"misc",
					array(
					    'valeur' => mktime(date('h'),date('m'),date('s'),date('m'),date('d'),date('y'))
					),
					array(
						'intitule' => 'sending_emails',
						'type' => 'cron'
					)
				);
			}
		}
	break;

}

?>
