<?php
/**
 * @file 		items.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

/**
 * Define Timezone
 */
if (isset($_SESSION['settings']['timezone'])) {
	date_default_timezone_set($_SESSION['settings']['timezone']);
}else{
	date_default_timezone_set('UTC');
}

require_once('../includes/language/'.$_SESSION['user_language'].'.php');
include('../includes/settings.php');
require_once('../includes/include.php');
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
include('main.functions.php');

//pw complexity levels
$pw_complexity = array(
    0=>array(0,$txt['complex_level0']),
    25=>array(25,$txt['complex_level1']),
    50=>array(50,$txt['complex_level2']),
    60=>array(60,$txt['complex_level3']),
    70=>array(70,$txt['complex_level4']),
    80=>array(80,$txt['complex_level5']),
    90=>array(90,$txt['complex_level6'])
);

$allowed_tags = '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul><blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>';

function is_utf8($string)
{
	return preg_match('%^(?:
	[\x09\x0A\x0D\x20-\x7E] # ASCII
	| [\xC2-\xDF][\x80-\xBF] # non-overlong 2-byte
	| \xE0[\xA0-\xBF][\x80-\xBF] # excluding overlongs
	| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
	| \xED[\x80-\x9F][\x80-\xBF] # excluding surrogates
	| \xF0[\x90-\xBF][\x80-\xBF]{2} # planes 1-3
	| [\xF1-\xF3][\x80-\xBF]{3} # planes 4-15
	| \xF4[\x80-\x8F][\x80-\xBF]{2} # plane 16
	)*$%xs', $string);
}

//Connect to mysql server
require_once("class.database.php");
$db = new Database($server, $user, $pass, $database, $pre);
$db->connect();

//Do asked action
if ( isset($_POST['type']) ){
    switch($_POST['type'])
    {
        /*
        * CASE
        * creating a new ITEM
        */
        case "new_item":
            require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
            require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
        	//Check KEY and rights
        	if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
        		$return_values = AesCtr::encrypt(json_encode(array("error" => "something_wrong"),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);
        		echo $return_values;
        		break;
        	}
            //decrypt and retreive data in JSON format
            $data_received = json_decode((AesCtr::decrypt($_POST['data'], $_SESSION['key'], 256)), true);

            //Prepare variables
            $label = htmlspecialchars_decode($data_received['label']);
            $url = htmlspecialchars_decode($data_received['url']);
            $pw = htmlspecialchars_decode($data_received['pw']);
            $login = htmlspecialchars_decode($data_received['login']);
            $tags = htmlspecialchars_decode($data_received['tags']);

        	if (!empty($pw)) {
        		//Check length
        		if(strlen($pw)>40){
        			$return_values = array("error" => "pw_too_long");
        			$return_values = AesCtr::encrypt(json_encode($return_values,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);
        			echo $return_values;
        			break;
        		}

	            //;check if element doesn't already exist
	            $item_exists = 0;
	            $new_id = "";
	        	$data = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."items WHERE label = '".addslashes($label)."' AND inactif=0");
	        	if ( $data[0] != 0 ){
	        		$item_exists = 1;
	        	}else{
	        		$item_exists = 0;
	        	}

	            if ( (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 0 && $item_exists == 0)
	            	||
	            	(isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1)
	            ){
	            	//set key if non personal item
	            	if($data_received['is_pf'] != 1){
	            		//generate random key
	            		$random_key = GenerateKey();
	            		$pw = $random_key.$pw;
	            	}

		            //encrypt PW
		            if ($data_received['salt_key_set']==1 && isset($data_received['salt_key_set']) && $data_received['is_pf']==1 && isset($data_received['is_pf'])){
		                $pw = encrypt($pw,mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
		                $resticted_to = $_SESSION['user_id'];
		            }else
		                $pw = encrypt($pw);

		            //ADD item
		            $new_id = $db->query_insert(
		                'items',
		                array(
		                    'label' => $label,
		                    'description' => $data_received['description'],
		                    'pw' => $pw,
		                    'email' => $data_received['email'],
		                    'url' => $url,
		                    'id_tree' => $data_received['categorie'],
		                    'login' => $login,
		                    'inactif' => '0',
							'restricted_to' => isset($data_received['restricted_to']) ? $data_received['restricted_to'] : '',
							'perso' => ( $data_received['salt_key_set']==1 && isset($data_received['salt_key_set']) && $data_received['is_pf']==1 && isset($data_received['is_pf'])) ? '1' : '0',
							'anyone_can_modify' => (isset($data_received['anyone_can_modify']) && $data_received['anyone_can_modify'] == "on") ? '1' : '0'
		                )
		            );

		            //If automatic deletion asked
		            if($data_received['to_be_deleted'] != 0 && !empty($data_received['to_be_deleted'])){
		            	$date_stamp = DateToStamp($data_received['to_be_deleted']);
		            	$db->query_insert(
			                'automatic_del',
			                array(
			                    'item_id' => $new_id,
			                    'del_enabled' => 1,	//0=deactivated;1=activated
			                    'del_type' => $date_stamp != false ? 2 : 1,	//1=counter;2=date
			                    'del_value' => $date_stamp != false ? $date_stamp : $data_received['to_be_deleted']
			                )
			            );
		            }

	            	//Store generated key
	            	if($data_received['is_pf'] != 1){
		            	$db->query_insert(
			            	'keys',
			            	array(
			            	    'table' => 'items',
			            	    'id' => $new_id,
			            	    'rand_key' => $random_key
			            	)
		            	);
	            	}

		        	//Manage retriction_to_roles
		        	if (isset($data_received['restricted_to_roles'])) {
		        		foreach (array_filter(explode(';', $data_received['restricted_to_roles'])) as $role){
		        			$db->query_insert(
			         			'restriction_to_roles',
			         			array(
			         			    'role_id' => $role,
			         			    'item_id' => $new_id
			         			)
		        			);
		        		}
		        	}

		            //log
		            $db->query_insert(
		                'log_items',
		                array(
		                    'id_item' => $new_id,
		                    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
		                    'id_user' => $_SESSION['user_id'],
		                    'action' => 'at_creation'
		                )
		            );

		            //Add tags
		            $tags = explode(' ', $tags);
		            foreach($tags as $tag){
		                if ( !empty($tag) )
		                    $db->query_insert(
		                        'tags',
		                        array(
		                            'item_id' => $new_id,
		                            'tag' => strtolower($tag)
		                        )
		                    );
		            }

		            // Check if any files have been added
		            if ( !empty($data_received['random_id_from_files']) ){
		                $sql = "SELECT id
		                        FROM ".$pre."files
		                        WHERE id_item=".$data_received['random_id_from_files'];
		                $rows = $db->fetch_all_array($sql);
		                foreach ($rows as $reccord){
		                    //update item_id in files table
		                    $db->query_update(
		                        'files',
		                        array(
		                            'id_item' => $new_id
		                        ),
		                        "id='".$reccord['id']."'"
		                    );
		                }
		            }

		            //Update CACHE table
		            UpdateCacheTable("add_value",$new_id);

		            //Announce by email?
		            if ( $data_received['annonce'] == 1 ){
		                require_once("../includes/libraries/phpmailer/class.phpmailer.php");
		                //send email
		                $destinataire= explode(';',$data_received['diffusion']);
		                foreach($destinataire as $mail_destinataire){
		                    //send it
		                    $mail = new PHPMailer();
		                    $mail->SetLanguage("en","../includes/libraries/phpmailer/language");
		                    $mail->IsSMTP();                                   // send via SMTP
		                    $mail->Host     = $smtp_server; // SMTP servers
		                    $mail->SMTPAuth = $smtp_auth;     // turn on SMTP authentication
		                    $mail->Username = $smtp_auth_username;  // SMTP username
		                    $mail->Password = $smtp_auth_password; // SMTP password
		                    $mail->From     = $email_from;
		                    $mail->FromName = $email_from_name;
		                    $mail->AddAddress($mail_destinataire);     //Destinataire
		                    $mail->WordWrap = 80;                              // set word wrap
		                    $mail->IsHTML(true);                               // send as HTML
		                    $mail->Subject  =  $txt['email_subject'];
		                    $mail->AltBody     =  $txt['email_altbody_1']." ".mysql_real_escape_string(stripslashes(($_POST['label'])))." ".$txt['email_altbody_2'];
		                    $corpsDeMail = $txt['email_body_1'].mysql_real_escape_string(stripslashes(($_POST['label']))).$txt['email_body_2'].$txt['email_body_3'];
		                    $mail->Body  =  $corpsDeMail;
		                    $mail->Send();
		                }
		            }
	            //Get Expiration date
                   	$expiration_flag = '';
                    if ( $_SESSION['settings']['activate_expiration'] == 1 ){
                    	$expiration_flag = '<img src="includes/images/flag-green.png">';
                    }

		            // Prepare full line
                    $html = '<li class="item_draggable'
                    	.'" id="'.$new_id.'" style="margin-left:-30px;">'
                    	.'<img src="includes/images/grippy.png" style="margin-right:5px;cursor:hand;" alt="" class="grippy"  />'
                    	.$expiration_flag.'<img src="includes/images/tag-small-green.png">'.
                    	'&nbsp;<a id="fileclass'.$new_id.'" class="file" onclick="AfficherDetailsItem(\''.$new_id.'\', \'0\', \'\', \'\', \'\')">'.
                    	stripslashes($data_received['label']);
                    if (!empty($data_received['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1)
                        $html .= '&nbsp;<font size=2px>['.strip_tags(stripslashes(substr(CleanString($data_received['description']),0,30))).']</font>';
                    $html .= '</a><span style="float:right;margin:2px 10px 0px 0px;">';

                    // display quick icon shortcuts ?
                    if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                    	$item_login = '<img src="includes/images/mini_user_disable.png" id="icon_login_'.$new_id.'" />';
                    	$item_pw = '<img src="includes/images/mini_lock_disable.png" id="icon_pw_'.$new_id.'" class="copy_clipboard" />';

                    	if (!empty($data_received['login'])) {
                    		$item_login = '<img src="includes/images/mini_user_enable.png" id="icon_login_'.$new_id.'" class="copy_clipboard" title="'.$txt['item_menu_copy_login'].'" />';
                    	}
                    	if (!empty($data_received['pw'])) {
                    		$item_pw = '<img src="includes/images/mini_lock_enable.png" id="icon_pw_'.$new_id.'" class="copy_clipboard" title="'.$txt['item_menu_copy_pw'].'" />';
                    	}
                    	$html .= $item_login.'&nbsp;'.$item_pw;
                    	//$html .= '<input type="hidden" id="item_pw_in_list_'.$new_id.'" value="'.$data_received['pw'].'"><input type="hidden" id="item_login_in_list_'.$new_id.'" value="'.$data_received['login'].'">';
                    }

                    // Prepare make Favorite small icon
                    $html .= '&nbsp;<span id="quick_icon_fav_'.$new_id.'" title="Manage Favorite" class="cursor">';
                    if (in_array($new_id, $_SESSION['favourites'])) {
                    	$html .= '<img src="includes/images/mini_star_enable.png" onclick="ActionOnQuickIcon('.$new_id.',0)" />';
                    }else {
                    	$html .= '<img src="includes/images/mini_star_disable.png"" onclick="ActionOnQuickIcon('.$new_id.',1)" />';
                    }

                    //mini icon for collab
                    if (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1) {
                    	if ($data_received['anyone_can_modify'] == 1) {
                    		$item_collab = '&nbsp;<img src="includes/images/mini_collab_enable.png" title="'.$txt['item_menu_collab_enable'].'" />';
                    	}else{
                    		$item_collab = '&nbsp;<img src="includes/images/mini_collab_disable.png" title="'.$txt['item_menu_collab_disable'].'" />';
                    	}
                    	$html .= '</span>'.$item_collab.'</span>';
                    }

                    $html .= '</li>';

                    //Build array with items
                    $items_id_list = array($new_id, $data_received['pw'], $login);

                	$return_values = array(
                		"item_exists" => $item_exists,
                		"error" => "no",
                		"new_id" => $new_id,
                		"new_pw" => $data_received['pw'],
                		"new_login" => $login,
                		"new_entry" => $html,
                		"array_items" => $items_id_list,
                		"show_clipboard_small_icons" => (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) ? 1 : 0
                	);
	            }

	        	else if (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 0 && $item_exists == 1) {
	        		$return_values = array("error" => "item_exists");
	        	}
        	}else{
        		$return_values = array("error" => "something_wrong");
        	}
        	//Encrypt data to return
        	$return_values = AesCtr::encrypt(json_encode($return_values,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);
        	echo $return_values;
        break;

        /*
        * CASE
        * update an ITEM
        */
        case "update_item":
            require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
            require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
        	//Check KEY and rights
        	if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
        		$return_values = AesCtr::encrypt(json_encode(array("error" => "something_wrong"),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);
        		echo $return_values;
        		break;
        	}
            //init
            $reload_page = false;
            $return_values = array();

            //decrypt and retreive data in JSON format
            $data_received = json_decode(AesCtr::decrypt($_POST['data'], $_SESSION['key'], 256), true);

            if (count($data_received) > 0) {
                //Prepare variables
                $label = htmlspecialchars_decode($data_received['label']);
                $url = htmlspecialchars_decode($data_received['url']);
                $pw = $original_pw = htmlspecialchars_decode($data_received['pw']);
                $login = htmlspecialchars_decode($data_received['login']);
                $tags = htmlspecialchars_decode($data_received['tags']);

            	//Check length
            	if(strlen($pw)>40){
            		$return_values = array("error" => "pw_too_long");
            		$return_values = AesCtr::encrypt(json_encode($return_values,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);
            		echo $return_values;
            		break;
            	}

                //Get existing values -> TODO
                $data = $db->query_first("
					SELECT i.id AS id, i.label AS label, i.description AS description, i.pw AS pw, i.url AS url, i.id_tree AS id_tree, i.perso AS perso, i.login AS login,
					i.inactif AS inactif, i.restricted_to AS restricted_to, i.anyone_can_modify AS anyone_can_modify, i.email AS email, i.notification AS notification,
					u.login AS user_login, u.email AS user_email
					FROM ".$pre."items AS i
					INNER JOIN ".$pre."log_items AS l ON (i.id=l.id_item)
					INNER JOIN ".$pre."users AS u ON (u.id=l.id_user)
					WHERE i.id=".$data_received['id']
                );

            	//Manage salt key
            	if($data['perso'] != 1){
            		//Get orginal key
            		$original_key = $db->query_first("
						SELECT `rand_key`
						FROM `".$pre."keys`
						WHERE `table` LIKE 'items' AND `id`=".$data_received['id']
            		);

            		$pw = $original_key['rand_key'].$pw;
            	}


                //encrypt PW
        	    if ($data_received['salt_key_set']==1 && isset($data_received['salt_key_set']) && $data_received['is_pf']==1 && isset($data_received['is_pf'])){
        		    $pw = encrypt($pw,mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
        		    $resticted_to = $_SESSION['user_id'];
        	    }else
        		    $pw = encrypt($pw);

                //---Manage tags
                    //deleting existing tags for this item
                    $db->query("DELETE FROM ".$pre."tags WHERE item_id = '".$data_received['id']."'");

                    //Add new tags
                    $tags = explode(' ',$tags);
                    foreach($tags as $tag){
                        if ( !empty($tag) )
                            $db->query_insert(
                                'tags',
                                array(
                                    'item_id' => $data_received['id'],
                                    'tag' => strtolower($tag)
                                )
                            );
                    }

                //update item
                $db->query_update(
                    'items',
                    array(
                        'label' => $label,
                        'description' => $data_received['description'],
                        'pw' => $pw,
		                'email' => $data_received['email'],
                        'login' => $login,
                        'url' => $url,
                        'id_tree' => $data_received['categorie'],
	                    'restricted_to' => $data_received['restricted_to'],
                        'anyone_can_modify' => (isset($data_received['anyone_can_modify']) && $data_received['anyone_can_modify'] == "on") ? '1' : '0'
                    ),
                    "id='".$data_received['id']."'"
                );

                //Update automatic deletion - Only by the creator of the Item
                if(isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1){
                	//check if elem exists in Table. If not add it or update it.
                	$data_tmp = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."automatic_del WHERE item_id = '".$data_received['id']."'");
                	if ($data_tmp[0] == 0){
                		//No automatic deletion for this item
                		if(!empty($data_received['to_be_deleted']) || ($data_received['to_be_deleted'] > 0 && is_numeric($data_received['to_be_deleted']))){
                			//Automatic deletion to be added
                			$db->query_insert(
	                			'automatic_del',
	                			array(
	                			    'item_id' => $data_received['id'],
	                			    'del_enabled' => 1,
	                			    'del_type' => is_numeric($data_received['to_be_deleted']) ? 1 : 2,
	                			    'del_value' => is_numeric($data_received['to_be_deleted']) ? $data_received['to_be_deleted'] : DateToStamp($data_received['to_be_deleted'])
	                			)
                			);
                			//update LOG
                			$db->query_insert(
	                			'log_items',
	                			array(
	                			    'id_item' => $data_received['id'],
	                			    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	                			    'id_user' => $_SESSION['user_id'],
	                			    'action' => 'at_modification',
	                			    'raison' => 'at_automatic_del : '.$data_received['to_be_deleted']
	                			)
                			);
                		}
                	}else{
                		//Automatic deletion exists for this item
                		if(!empty($data_received['to_be_deleted']) || ($data_received['to_be_deleted'] > 0 && is_numeric($data_received['to_be_deleted']))){
                			//Update automatic deletion
                			$db->query_update(
	                			"automatic_del",
	                			array(
	                				'del_type' => is_numeric($data_received['to_be_deleted']) ? 1 : 2,
	                			    'del_value' => is_numeric($data_received['to_be_deleted']) ? $data_received['to_be_deleted'] : DateToStamp($data_received['to_be_deleted'])
	                			),
	                			"item_id = ".$data_received['id']
                			);
                		}else{
                			//delete automatic deleteion for this item
                			$db->query("DELETE FROM ".$pre."automatic_del WHERE item_id = '".$data_received['id']."'");
                		}
                		//update LOG
                		$db->query_insert(
	                		'log_items',
	                		array(
	                		    'id_item' => $data_received['id'],
	                		    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	                		    'id_user' => $_SESSION['user_id'],
	                		    'action' => 'at_modification',
	                		    'raison' => 'at_automatic_del : '.$data_received['to_be_deleted']
	                		)
                		);
                	}
                }

            	//get readable list of restriction
            	$list_of_restricted = $old_restriction_list = "";
            	if(!empty($data_received['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1){
            		foreach(explode(';', $data_received['restricted_to']) as $user_rest){
            			if(!empty($user_rest)){
            				$data_tmp = $db->query_first("SELECT login FROM ".$pre."users WHERE id= ".$user_rest);
            				if(empty($list_of_restricted)) $list_of_restricted = $data_tmp['login'];
            				else $list_of_restricted .= ";".$data_tmp['login'];
            			}
            		}
            	}
            	if ($data['restricted_to'] != $data_received['restricted_to'] && $_SESSION['settings']['restricted_to'] == 1){
            		if(!empty($data['restricted_to'])){
            			foreach(explode(';', $data['restricted_to']) as $user_rest){
            				if(!empty($user_rest)){
            					$data_tmp = $db->query_first("SELECT login FROM ".$pre."users WHERE id= ".$user_rest);
            					if(empty($old_restriction_list)) $old_restriction_list = $data_tmp['login'];
            					else $old_restriction_list .= ";".$data_tmp['login'];
            				}
            			}
            		}
            	}

            	//Manage retriction_to_roles
            	if (isset($data_received['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1) {
            		//get values before deleting them
            		$rows = $db->fetch_all_array("
						SELECT t.title
						FROM ".$pre."roles_title AS t
						INNER JOIN ".$pre."restriction_to_roles AS r ON (t.id=r.role_id)
						WHERE r.item_id = ".$data_received['id']."
						ORDER BY t.title ASC");
            		foreach($rows as $reccord){
            			if(empty($old_restriction_list)) $old_restriction_list = $reccord['title'];
            			else $old_restriction_list .= ";".$reccord['title'];
            		}
            		//delete previous values
            		$db->query_delete(
	            		'restriction_to_roles',
	            		array(
		            		'item_id' => $data_received['id']
		            	)
            		);
            		//add roles for item
            		foreach (array_filter(explode(';', $data_received['restricted_to_roles'])) as $role){
            			$db->query_insert(
            			'restriction_to_roles',
            			array(
            			    'role_id' => $role,
            			    'item_id' => $data_received['id']
            			)
            			);
            			$data_tmp = $db->query_first("SELECT title FROM ".$pre."roles_title WHERE id= ".$role);
            			if(empty($list_of_restricted)) $list_of_restricted = $data_tmp['title'];
            			else $list_of_restricted .= ";".$data_tmp['title'];
            		}
            	}


                //Update CACHE table
                UpdateCacheTable("update_value", $data_received['id']);

                //Log all modifications done
                /*LABEL */
                if ( $data['label'] != $label )
                    $db->query_insert(
                        'log_items',
                        array(
                            'id_item' => $data_received['id'],
                            'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_label : '.$data['label'].' => '.$label
                        )
                    );
                /*LOGIN */
                if ( $data['login'] != $login )
                    $db->query_insert(
                        'log_items',
                        array(
                            'id_item' => $data_received['id'],
                            'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_login : '.$data['login'].' => '.$login
                        )
                    );
                /*EMAIL */
                if ( $data['email'] != $data_received['email'])
                    $db->query_insert(
                        'log_items',
                        array(
                            'id_item' => $data_received['id'],
                            'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_email : '.$data['email'].' => '.$data_received['email']
                        )
                    );
                /*URL */
                if ( $data['url'] != $url && $url != "http://")
                    $db->query_insert(
                        'log_items',
                        array(
                            'id_item' => $data_received['id'],
                            'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_url : '.$data['url'].' => '.$url
                        )
                    );
                /*DESCRIPTION */
                if ( $data['description'] != $data_received['description'] )
                    $db->query_insert(
                        'log_items',
                        array(
                            'id_item' => $data_received['id'],
                            'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_description'
                        )
                    );
                /*FOLDER */
                if ( $data['id_tree'] != $data_received['categorie'] ){
                    $db->query_insert(
                        'log_items',
                        array(
                            'id_item' => $data_received['id'],
                            'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_category : '.$data['id_tree'].' => '.$data_received['categorie']
                        )
                    );
                    //ask for page reloading
                    $reload_page = true;
                }
                /*PASSWORD */
                if ( $data['pw'] != $pw ){
                    if( isset($data_received['salt_key']) && !empty($data_received['salt_key']) ) $old_pw = decrypt($data['pw'],$data_received['salt_key']);
                    else $old_pw = decrypt($data['pw']);
                    $db->query_insert(
                        'log_items',
                        array(
                            'id_item' => $data_received['id'],
                            'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_pw : '.$data['pw']
                        )
                    );
                }
            	/*RESTRICTIONS */
            	if ($data['restricted_to'] != $data_received['restricted_to']){
            		$db->query_insert(
	            		'log_items',
	            		array(
	            		    'id_item' => $data_received['id'],
	            		    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	            		    'id_user' => $_SESSION['user_id'],
	            		    'action' => 'at_modification',
	            		    'raison' => 'at_restriction : '.$old_restriction_list.' => '.$list_of_restricted
	            		)
            		);
            	}

                //Reload new values
                $data_item = $db->query_first("
                    SELECT *
                    FROM ".$pre."items AS i
                    INNER JOIN ".$pre."log_items AS l ON (l.id_item = i.id)
                    WHERE i.id=".$data_received['id']."
                        AND l.action = 'at_creation'"
                );

                //Reload History
                $history = "";
                $rows = $db->fetch_all_array("
                    SELECT l.date AS date, l.action AS action, l.raison AS raison, u.login AS login
                    FROM ".$pre."log_items AS l
                    LEFT JOIN ".$pre."users AS u ON (l.id_user=u.id)
                    WHERE l.action <> 'at_shown' AND id_item=".$data_received['id']);
                foreach($rows as $reccord){
                	$reason = explode(':',$reccord['raison']);

                	if ( empty($history) ){
                		$history = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - ". $reccord['login'] ." - ".$txt[$reccord['action']].
                			" - ".(!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' : '.$reason[1] : $txt[trim($reason[0])] ):'');

                	}
                	else{
                		$history .= "<br />".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - ".
                        	$reccord['login'] ." - ".$txt[$reccord['action']]." - ".
                        	(!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' => '.$reason[1] : $txt[trim($reason[0])] ):'');
                	}
                }

                //decrypt PW
                if ( empty($data_received['salt_key']) ){
                    $pw = decrypt($data_item['pw']);
                }else{
                    $pw = decrypt($data_item['pw'],mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                }
                $pw = CleanString($pw);

            	//generate 2d key
            	include('../includes/libraries/pwgen/pwgen.class.php');
            	$pwgen = new PWGen();
            	$pwgen->setLength(20);
            	$pwgen->setSecure(true);
            	$pwgen->setSymbols(false);
            	$pwgen->setCapitalize(true);
            	$pwgen->setNumerals(true);
            	$_SESSION['key_tmp'] = $pwgen->generate();

                // Prepare files listing
                    $files = $files_edit = "";
                    // launch query
                    $rows = $db->fetch_all_array(
                        "SELECT *
                        FROM ".$pre."files
                        WHERE id_item=".$data_received['id']
                    );
                    foreach ($rows as $reccord){
                        // get icon image depending on file format
                        $icon_image = file_format_image($reccord['extension']);
                        // If file is an image, then prepare lightbox. If not image, then prepare donwload
                        if ( in_array($reccord['extension'],$k['image_file_ext']) )
                            $files .=   '<img src=\''.$_SESSION['settings']['cpassman_url'].'/includes/images/'.$icon_image.'\' /><a class=\'image_dialog\' href=\''.$_SESSION['settings']['url_to_upload_folder'].'/'.$reccord['file'].'\' title=\''.$reccord['name'].'\'>'.$reccord['name'].'</a><br />';
                        else
                            $files .=   '<img src="includes/images/'.$icon_image.'" /><a href=\'sources/downloadFile.php?name='.urlencode($reccord['name']).'&type=sub&file='.$reccord['file'].'&size='.$reccord['size'].'&type='.urlencode($reccord['type']).'&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'\' target=\'_blank\'>'.$reccord['name'].'</a><br />';
                        // Prepare list of files for edit dialogbox
                        $files_edit .= '<span id=\'span_edit_file_'.$reccord['id'].'\'><img src=\''.$_SESSION['settings']['cpassman_url'].'/includes/images/'.$icon_image.'\' /><img src=\''.$_SESSION['settings']['cpassman_url'].'/includes/images/document--minus.png\' style=\'cursor:pointer;\'  onclick=\'delete_attached_file(\"'.$reccord['id'].'\")\' />&nbsp;'.$reccord['name']."</span><br />";
                    }

                //Send email
                if ( !empty($_POST['diffusion']) ){
                    require_once("class.phpmailer.php");
                    $destinataire= explode(';',$data_received['diffusion']);
                    foreach($destinataire as $mail_destinataire){
                        //envoyer ay destinataire
                        $mail = new PHPMailer();
                        $mail->SetLanguage("en","../includes/libraries/phpmailer/language");
                        $mail->IsSMTP();                                   // send via SMTP
                        $mail->Host     = $smtp_server; // SMTP servers
                        $mail->SMTPAuth = $smtp_auth;     // turn on SMTP authentication
                        $mail->Username = $smtp_auth_username;  // SMTP username
                        $mail->Password = $smtp_auth_password; // SMTP password
                        $mail->From     = $email_from;
                        $mail->FromName = $email_from_name;
                        $mail->AddAddress($mail_destinataire);     //Destinataire
                        $mail->WordWrap = 80;                              // set word wrap
                        $mail->IsHTML(true);                               // send as HTML
                        $mail->Subject  =  "Password has been updated";
                        $mail->AltBody     =  "Password for ".$label." has been updated.";
                        $corpsDeMail = "Hello,<br><br>Password for '" .$label."' has been updated.<br /><br />".
                        "You can check it <a href=\"".$_SESSION['settings']['cpassman_url']."/index.php?page=items&group=".$data_received['categorie']."&id=".$data_received['id']."\">HERE</a><br /><br />".
                        "Cheers";
                        $mail->Body  =  $corpsDeMail;
                        $mail->Send();
                    }
                }

                //Prepare some stuff to return
                $arrData = array(
            	    "files" => str_replace('"','&quot;',$files),
            	    "history" => str_replace('"','&quot;',$history),
            	    "files_edit" => str_replace('"','&quot;',$files_edit),
            	    "id_tree" => $data_item['id_tree'],
            	    "id" => $data_item['id'],
            	    "reload_page" => $reload_page,
            	    "restriction_to" => $data_received['restricted_to'].$data_received['restricted_to_roles'],
            	    "list_of_restricted" => $list_of_restricted,
                	"error" => ""
                );

            }else{
                //an error appears on JSON format
            	$arrData = array("error" => "format");
            }

            //return data
            $return_values = AesCtr::encrypt(json_encode($arrData,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);
        	echo $return_values;
        break;


       	/*
       	   * CASE
       	   * Copy an Item
       	*/
        case "copy_item":
        	//Check KEY and rights
        	if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
        		$return_values = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($txt['error_not_allowed_to']).'"}]';
        		echo $return_values;
        		break;
        	}
        	$return_values = $pw = "";

        	if (isset($_POST['item_id']) && !empty($_POST['item_id']) && !empty($_POST['folder_id'])) {
        		// load the original record into an array
        		$original_record = $db->query_first("
					SELECT *
					FROM ".$pre."items
					WHERE id=".$_POST['item_id']
        		);

        		// insert the new record and get the new auto_increment id
        		$new_id = $db->query_insert(
        			'items',
        			array(
        				'label' => "duplicate"
        			)
        		);

        		//Check if item is PERSONAL
        		if($original_record['perso'] != 1){
        			//generate random key
        			$random_key = GenerateKey();

        			//Store generated key
        			$db->query_insert(
	        			'keys',
	        			array(
	        			    'table' => 'items',
	        			    'id' => $new_id,
	        			    'rand_key' => $random_key
	        			)
        			);

        			//get key for original pw
        			$original_key = $db->query_first('SELECT rand_key FROM `'.$pre.'keys` WHERE `table` LIKE "items" AND `id` ='.$_POST['item_id']);

        			//unsalt previous pw
        			$pw = substr(decrypt($original_record['pw']), strlen($original_key['rand_key']));
        		}


        		// generate the query to update the new record with the previous values
        		$query = "UPDATE ".$pre."items SET ";
        		foreach ($original_record as $key => $value) {
        			if($key == "id_tree"){
        				$query .= '`id_tree` = "'.$_POST['folder_id'].'", ';
        			}else if($key == "pw" && !empty($pw)){
        				$query .= '`pw` = "'.encrypt($random_key.$pw).'", ';
        			}else if ($key != "id" && $key != "key") {
        				$query .= '`'.$key.'` = "'.str_replace('"','\"',$value).'", ';
        			}
        		}
        		$query = substr($query,0,strlen($query)-2); # lop off the extra trailing comma
        		$query .= " WHERE id=".$new_id;
        		$db->query($query);


        		//Add attached itms
        		$rows = $db->fetch_all_array(
        		"SELECT *
                        FROM ".$pre."files
                        WHERE id_item=".$new_id
        		);
        		foreach ($rows as $reccord){
        			$db->query_insert(
	        			'files',
	        			array(
	        				'id_item' => $new_id,
	        				'name' => $reccord['name'],
	        				'size' => $reccord['size'],
	        				'extension' => $reccord['extension'],
	        				'type' => $reccord['type'],
	        				'file' => $reccord['file']
	        			)
        			);
        		}

        		//Add this duplicate in logs
        		$db->query_insert(
	        		'log_items',
	        		array(
	        		    'id_item' => $new_id,
	        		    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	        		    'id_user' => $_SESSION['user_id'],
	        		    'action' => 'at_creation'
	        		)
        		);

        		//Add the fact that item has been copied in logs
        		$db->query_insert(
	        		'log_items',
	        		array(
	        		    'id_item' => $_POST['item_id'],
	        		    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	        		    'id_user' => $_SESSION['user_id'],
		        		'action' => 'at_copy'
	        		)
        		);

        		//reload cache table
        		require_once("main.functions.php");
        		UpdateCacheTable("reload", "");

        		$return_values = '[{"status" : "ok"}, {"new_id" : "'.$new_id.'"}]';
        	}else{
        		//no item
        		$return_values = '[{"error" : "no_item"}, {"error_text" : "No item ID"}]';
        	}

       		//return data
       		echo $return_values;
       	break;


       	/*
       	   * CASE
       	   * Display informations of selected item
       	*/
        case "show_details_item":
            $arrData = array();

            //return ID
            $arrData['id'] = $_POST['id'];

        	//Check if item is deleted
        	$data_deleted = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item = '".$_POST['id']."' AND action = 'at_delete'");
        	$data_restored = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item = '".$_POST['id']."' AND action = 'at_restored'");
        	if ( $data_deleted[0] != 0 && $data_deleted[0] > $data_restored[0]){
        		//This item is deleted => exit
        		require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
        		require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
        		$return_values = AesCtr::encrypt(json_encode(array('show_detail_option'=>2),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);

        		echo $return_values;
        		break;
        	}

            //Get all informations for this item
            $sql = "SELECT *
                    FROM ".$pre."items AS i
                    INNER JOIN ".$pre."log_items AS l ON (l.id_item = i.id)
                    WHERE i.id=".$_POST['id']."
                    AND l.action = 'at_creation'";
            $data_item = $db->query_first($sql);
            //INNER JOIN ".$pre."automatic_del AS d ON (d.item_id = i.id)

            //Get all USERS infos
            $list_notif = array_filter(explode(";",$data_item['notification']));
            $list_rest = array_filter(explode(";",$data_item['restricted_to']));
            $liste_restriction = $list_notification= $list_notification_emails= "";
            $rows = $db->fetch_all_array(
        		"SELECT id, login, email
                FROM ".$pre."users"
        	);
        	foreach ($rows as $reccord){
        		//Get auhtor
        		if($reccord['id'] == $data_item['id_user']){
        			$arrData['author'] = $reccord['login'];
					$arrData['author_email'] = $reccord['email'];
					$arrData['id_user'] = $data_item['id_user'];
					if(in_array($reccord['id'], $list_notif))
						$arrData['notification_status'] = true;
					else
						$arrData['notification_status'] = false;
        		}
        		//Get restriction list for users
        		if(in_array($reccord['id'], $list_rest)){
        			$liste_restriction .= $reccord['login'].";";
        		}
        		//Get notification list for users
        		if(in_array($reccord['id'], $list_notif)){
        			$list_notification .= $reccord['login'].";";
        			$list_notification_emails .= $reccord['email'].",";
        		}
        	}

/*
			//Get auhtor
			$data_tmp = $db->query_first("SELECT login, email FROM ".$pre."users WHERE id= ".$data_item['id_user']);
			$arrData['author'] = $data_tmp['login'];
			$arrData['author_email'] = $data_tmp['email'];
			$arrData['id_user'] = $data_item['id_user'];
*/

            //Get all tags for this item
            $tags = "";
            $sql = "SELECT tag
                    FROM ".$pre."tags
                    WHERE item_id=".$_POST['id'];
            $rows = $db->fetch_all_array($sql);
            foreach ($rows as $reccord)
                $tags .= $reccord['tag']." ";

        	//TODO -> improve this check
            //check that actual user can access this item
            $restriction_active = true;
            $restricted_to = array_filter(explode(';',$data_item['restricted_to']));
            if ( in_array($_SESSION['user_id'],$restricted_to) ) $restriction_active = false;
            if ( empty($data_item['restricted_to']) ) $restriction_active = false;

        	//Check if user has a role that is accepted
        	$rows_tmp = $db->fetch_all_array("
                    SELECT role_id
                    FROM ".$pre."restriction_to_roles
                    WHERE item_id=".$_POST['id']);$myTest=0;
        	if ( in_array($_SESSION['user_id'],$rows_tmp) ) $myTest = 1;//echo $myTest." ;; ";


            //Uncrypt PW
            if ( isset($_POST['salt_key_required']) && $_POST['salt_key_required'] == 1 && isset($_POST['salt_key_set']) && $_POST['salt_key_set'] == 1){
	            $pw = decrypt($data_item['pw'],mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                $arrData['edit_item_salt_key'] = 1;
            }else{
                $pw = decrypt($data_item['pw']);
                $arrData['edit_item_salt_key'] = 0;
            }

        	//extract real pw from salt
        	if($data_item['perso'] != 1){
        		$data_item_key = $db->query_first('SELECT rand_key FROM `'.$pre.'keys` WHERE `table`="items" AND `id`='.$_POST['id']);
        		$pw = substr($pw, strlen($data_item_key['rand_key']));
        	}

            //check if item is expired
        	if ( isset($_POST['expired_item']) && $_POST['expired_item'] == 1 ) {
        		$item_is_expired = true;
        	}else {
        		$item_is_expired = false;
        	}


        	//check user is admin
        	if($_SESSION['user_admin'] == 1 && $data_item['perso']!=1 && (isset($k['admin_full_right']) && $k['admin_full_right'] == true) || !isset($k['admin_full_right'])){
        		$arrData['show_details'] = 0;
        	}
            //Check if actual USER can see this ITEM
            else if ((
            	( in_array($data_item['id_tree'],$_SESSION['groupes_visibles']) || $_SESSION['is_admin'] == 1 )
	                &&  ( $data_item['perso']==0 || ($data_item['perso']==1 && $data_item['id_user'] == $_SESSION['user_id'] ) )
	                && $restriction_active == false
            	)
            	||
            	(
            		isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 && $data_item['anyone_can_modify']==1
	            	&& ( in_array($data_item['id_tree'],$_SESSION['groupes_visibles']) || $_SESSION['is_admin'] == 1 )
	            	&& $restriction_active == false
            	)
            	||
            	(
            		@in_array($_POST['id'], $_SESSION['list_folders_limited'][$_POST['folder_id']])
            	)
            ){
            	//Allow show details
                $arrData['show_details'] = 1;

                //Display menu icon for deleting if user is allowed
                if ($data_item['id_user'] == $_SESSION['user_id'] ||
                	$_SESSION['is_admin'] == 1 ||
                	($_SESSION['user_gestionnaire'] == 1 && $_SESSION['settings']['manager_edit'] == 1) ||
                	$data_item['anyone_can_modify']==1 ||
                	in_array($data_item['id_tree'], $_SESSION['list_folders_editable_by_role']) ||
                	in_array($_SESSION['user_id'],$restricted_to)
                ){
                    $arrData['user_can_modify'] = 1;
                    $user_is_allowed_to_modify = true;
                }else{
                    $arrData['user_can_modify'] = 0;
                    $user_is_allowed_to_modify = false;
                }

                //GET Audit trail
                $historique = "";
            	$history_of_pwds = "";
                $rows = $db->fetch_all_array("
                    SELECT l.date AS date, l.action AS action, l.raison AS raison, u.login AS login
                    FROM ".$pre."log_items AS l
                    LEFT JOIN ".$pre."users AS u ON (l.id_user=u.id)
                    WHERE id_item=".$_POST['id']."
                    AND action <> 'at_shown'
                    ORDER BY date ASC"
                );
                foreach ( $rows as $reccord ){
                	$reason = explode(':',$reccord['raison']);
                	if($reccord['action'] == "at_modification" && $reason[0] == "at_pw "){
                		require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
                		require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
                		$reason[1] = substr(decrypt($reason[1]), strlen($data_item_key['rand_key']));
                		if(!is_utf8($reason[1])){
                			$reason[1] = "";
                		}
                	}
                	if(!empty($reason[1]) || $reccord['action'] == "at_copy" || $reccord['action'] == "at_creation"){
                		if ( empty($historique) )
                			$historique = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - ". $reccord['login'] ." - ".$txt[$reccord['action']]." - ".(!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' : '.$reason[1] : $txt[trim($reason[0])] ):'');
                    	else
                        	$historique .= "<br />".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - ". $reccord['login']  ." - ".$txt[$reccord['action']]." - ".(!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' => '.$reason[1] : $txt[trim($reason[0])] ):'');
	                	if(trim($reason[0]) == "at_pw"){
							if(empty($history_of_pwds)) $history_of_pwds = $txt['previous_pw']."<br>- ".$reason[1];
							else $history_of_pwds .= "<br>- ".$reason[1];
	                	}
                	}
                }
            	if(empty($history_of_pwds)) $history_of_pwds = $txt['no_previous_pw'];
/*
                //Get restriction list for users
            	$liste = explode(";",$data_item['restricted_to']);
            	$liste_restriction = "";
            	if (count($liste) > 0) {
            		foreach($liste as $elem){
            			if ( !empty($elem) ){
            				$data2 = $db->fetch_row("SELECT login FROM ".$pre."users WHERE id=".$elem);
            				$liste_restriction .= $data2[0].";";
            			}
            		}
            	}
*/

            	//Get restriction list for roles
            	$liste_restriction_roles = array();
            	if (isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1) {
            		//Add restriction if item is restricted to roles
            		$rows = $db->fetch_all_array("
					SELECT t.title
					FROM ".$pre."roles_title AS t
					INNER JOIN ".$pre."restriction_to_roles AS r ON (t.id=r.role_id)
					WHERE r.item_id = ".$_POST['id']."
					ORDER BY t.title ASC");
            		foreach($rows as $reccord){
            			if (!in_array($reccord['title'], $liste_restriction_roles)){
            				array_push($liste_restriction_roles, $reccord['title']);
            			}
            		}
            	}

				//Check if any KB is linked to this item
				if(isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1){
					$tmp = "";
					$rows = $db->fetch_all_array("
							SELECT k.label, k.id
							FROM ".$pre."kb_items AS i
							INNER JOIN ".$pre."kb AS k ON (i.kb_id=k.id)
							WHERE i.item_id = ".$_POST['id']."
							ORDER BY k.label ASC");
	          		foreach($rows as $reccord){
						if(empty($tmp)){
							$tmp = "<a href='".$_SESSION['settings']['cpassman_url']."/index.php?page=kb&id=".$reccord['id']."'>".$reccord['label']."</a>";
						}else{
							$tmp .= "&nbsp;-&nbsp;<a href='".$_SESSION['settings']['cpassman_url']."/index.php?page=kb&id=".$reccord['id']."'>".$reccord['label']."</a>";
						}
	          		}
					$arrData['links_to_kbs'] = $tmp;
				}


                //Prepare DIalogBox data
                if ( $item_is_expired == false ) {
                    $arrData['show_detail_option'] = 0;
                }else if ( $user_is_allowed_to_modify == true && $item_is_expired == true ){
                    $arrData['show_detail_option'] = 1;
                }else{
                    $arrData['show_detail_option'] = 2;
                }

                $arrData['label'] = $data_item['label'];
                $arrData['pw'] = $pw;
                $arrData['email'] = $data_item['email'];
                $arrData['url'] = $data_item['url'];
                if (!empty($data_item['url'])) {
                    $arrData['link'] = "&nbsp;<a href='". $data_item['url']."' target='_blank'><img src='includes/images/arrow_skip.png' style='border:0px;' title='".$txt['open_url_link']."'></a>";
                }

                $arrData['description'] = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '',strip_tags($data_item['description'],$allowed_tags));
                $arrData['login'] = str_replace('"','&quot;',$data_item['login']);
                $arrData['historique'] = str_replace('"','&quot;',$historique);
                $arrData['id_restricted_to'] = $liste_restriction;
            	$arrData['id_restricted_to_roles'] = count($liste_restriction_roles) > 0 ? implode(";", $liste_restriction_roles).";" : "";
                $arrData['tags'] = str_replace('"','&quot;',$tags);
                $arrData['folder'] = $data_item['id_tree'];
                $arrData['anyone_can_modify'] = $data_item['anyone_can_modify'];
            	$arrData['history_of_pwds'] = str_replace('"','&quot;',$history_of_pwds);

            	//Add this item to the latests list
                if ( isset($_SESSION['latest_items']) && isset($_SESSION['settings']['max_latest_items']) && !in_array($data_item['id'],$_SESSION['latest_items']) ){
                    if ( count($_SESSION['latest_items']) >= $_SESSION['settings']['max_latest_items'] ){
                        array_pop($_SESSION['latest_items']);   //delete last items
                    }
                    array_unshift($_SESSION['latest_items'],$data_item['id']);
                    //update DB
                    $db->query_update(
                        "users",
                        array(
                            'latest_items' => implode(';',$_SESSION['latest_items'])
                        ),
                        "id=".$_SESSION['user_id']
                    );
                }

            	//generate 2d key
            	include('../includes/libraries/pwgen/pwgen.class.php');
            	$pwgen = new PWGen();
            	$pwgen->setLength(20);
            	$pwgen->setSecure(true);
            	$pwgen->setSymbols(false);
            	$pwgen->setCapitalize(true);
            	$pwgen->setNumerals(true);
            	$_SESSION['key_tmp'] = $pwgen->generate();

                // Prepare files listing
                    $files = $files_edit = "";
                    // launch query
                    $rows = $db->fetch_all_array(
                        "SELECT *
                        FROM ".$pre."files
                        WHERE id_item=".$_POST['id']
                    );
                    foreach ($rows as $reccord){
                        // get icon image depending on file format
                        $icon_image = file_format_image($reccord['extension']);
                        // If file is an image, then prepare lightbox. If not image, then prepare donwload
                        if ( in_array($reccord['extension'],$k['image_file_ext']) )
                            $files .=   '<img src=\'includes/images/'.$icon_image.'\' /><a class=\'image_dialog\' href=\''.$_SESSION['settings']['url_to_upload_folder'].'/'.$reccord['file'].'\' title=\''.$reccord['name'].'\'>'.$reccord['name'].'</a><br />';
                        else
                            $files .=   '<img src=\'includes/images/'.$icon_image.'\' /><a href=\'sources/downloadFile.php?name='.urlencode($reccord['name']).'&type=sub&file='.$reccord['file'].'&size='.$reccord['size'].'&type='.urlencode($reccord['type']).'&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'\'>'.$reccord['name'].'</a><br />';
                        // Prepare list of files for edit dialogbox
                        $files_edit .= '<span id=\'span_edit_file_'.$reccord['id'].'\'><img src=\'includes/images/'.$icon_image.'\' /><img src=\'includes/images/document--minus.png\' style=\'cursor:pointer;\'  onclick=\'delete_attached_file("'.$reccord['id'].'")\' />&nbsp;'.$reccord['name']."</span><br />";
                    }
                    //display lists
                    $arrData['files_edit'] = str_replace('"','&quot;',$files_edit);
                    $arrData['files_id'] = $files;


                //Refresh last seen items
                    $text = $txt['last_items_title'].": ";
                    $_SESSION['latest_items_tab']= "";
                    foreach($_SESSION['latest_items'] as $item){
                        if ( !empty($item) ){
                            $data = $db->query_first("SELECT id,label,id_tree FROM ".$pre."items WHERE id = ".$item);
                            $_SESSION['latest_items_tab'][$item] = array(
                            	'id'	=> $item,
                                'label'	=>addslashes($data['label']),
                                'url'	=>'index.php?page=items&group='.$data['id_tree'].'&id='.$item
                            );
                            $text .= '<span class="last_seen_item" onclick="javascript:window.location.href = \''.$_SESSION['latest_items_tab'][$item]['url'].'\'"><img src="includes/images/tag-small.png" /><span id="last_items_'.$_SESSION['latest_items_tab'][$item]['id'].'">'.stripslashes($_SESSION['latest_items_tab'][$item]['label']).'</span></span>';
                        }
                    }
                    $arrData['div_last_items'] = str_replace('"','&quot;',$text);

                    //disable add bookmark if alread bookmarked
                    if ( in_array($_POST['id'],$_SESSION['favourites']) ) {
                        $arrData['favourite'] = 1;
                    }else{
                        $arrData['favourite'] = 0;
                    }

	            	//Manage user restriction
	            	if (isset($_POST['restricted'])) {
	            		$arrData['restricted'] = $_POST['restricted'];
	            	}else{
	            		$arrData['restricted'] = "";
	            	}

	            	//Add the fact that item has been copied in logs
	            	if(isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1){
	            		$db->query_insert(
		            		'log_items',
		            		array(
		            		    'id_item' => $_POST['id'],
		            		    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
		            		    'id_user' => $_SESSION['user_id'],
		            			'action' => 'at_shown'
		            		)
	            		);
	            	}

	            //Decrement the number before being deleted
	            $sql = "SELECT * FROM ".$pre."automatic_del WHERE item_id=".$_POST['id'];
            	$data_delete = $db->query_first($sql);
            	$arrData['to_be_deleted'] = $data_delete['del_value'];
            	$arrData['to_be_deleted_type'] = $data_delete['del_type'];

            	//$date = date_parse_from_format($_SESSION['settings']['date_format'], $data_delete['del_value']);
            	//echo $_SESSION['settings']['date_format']." ; ".$data_delete['del_value'] ." ; ".mktime(0, 0, 0, $date['month'], $date['day'], $date['year'])." ; ".mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y'))." ; ";

	            if(isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1){
	            	if($data_delete['del_enabled'] == 1 || $arrData['id_user'] != $_SESSION['user_id']){
	            		if($data_delete['del_type'] == 1 && $data_delete['del_value'] > 1){
	            			//decrease counter
		            		$db->query_update(
				                "automatic_del",
				                array(
				                    'del_value' => $data_delete['del_value']-1
				                ),
				                "item_id = ".$_POST['id']
				            );
				            //store value
                			$arrData['to_be_deleted'] = $data_delete['del_value']-1;
	            		}else if(
	            			$data_delete['del_type'] == 1 && $data_delete['del_value'] <= 1
	            			|| $data_delete['del_type'] == 2 && $data_delete['del_value'] < mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y'))
	            		){
	            			$arrData['show_details'] = 0;

	            			//delete item
	            			$db->query("DELETE FROM ".$pre."automatic_del WHERE item_id = '".$_POST['id']."'");
	            			//make inactive object
		            		$db->query_update(
				                "items",
				                array(
				                    'inactif' => '1',
				                ),
				                "id = ".$_POST['id']
				            );
				            //log
				            $db->query_insert(
				                "log_items",
				                array(
				                    'id_item' => $_POST['id'],
				                    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
				                    'id_user' => $_SESSION['user_id'],
				                    'action' => 'at_delete',
				                	'raison' => 'at_automatically_deleted'
				                )
				            );

				            $arrData['to_be_deleted'] = 0;
	            		}else if($data_delete['del_type'] == 2){
	            			$arrData['to_be_deleted'] = date($_SESSION['settings']['date_format'], $data_delete['del_value']);
	            		}
	            	}else{
	            		$arrData['to_be_deleted'] = "";
	            	}
	            }else{
	            	$arrData['to_be_deleted'] = "not_enabled";
	            }

				//send notification if enabled
				if(isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1){
					//send back infos
                	$arrData['notification_list'] = $list_notification;

					//Send email if activated
					if(!empty($list_notification_emails) && !in_array($_SESSION['login'], explode(';', $list_notification))){
						$db->query_insert(
		            		'emails',
		            		array(
		            		    'timestamp' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
		            		    'subject' => $txt['email_on_open_notification_subject'],
		            		    'body' => str_replace(array('#tp_item_author#', '#tp_user#', '#tp_item#'), array(" ".addslashes($arrData['author']), addslashes($_SESSION['login']), addslashes($data_item['label'])), $txt['email_on_open_notification_mail']),
		            			'receivers' => $list_notification_emails,
		            			'status' => ''
		            		)
	            		);
					}
				}else{
					$arrData['notification_list'] = "";
					$arrData['notification_status'] = "";
				}
            }else{
                $arrData['show_details'] = 0;
            	//get readable list of restriction
            	$list_of_restricted = "";
            	if(!empty($data_item['restricted_to'])){
            		foreach(explode(';', $data_item['restricted_to']) as $user_rest){
            			if(!empty($user_rest)){
            				$data_tmp = $db->query_first("SELECT login FROM ".$pre."users WHERE id= ".$user_rest);
            				if(empty($list_of_restricted)) $list_of_restricted = $data_tmp['login'];
            				else $list_of_restricted .= ";".$data_tmp['login'];
            			}
            		}
            	}
            	$arrData['restricted_to'] = $list_of_restricted;
            }
            //print_r($arrData);
            //Encrypt data to return
            require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
            require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
            $return_values = AesCtr::encrypt(json_encode($arrData,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);

            //return data
            echo $return_values;
        break;

        /*
        * CASE
        * Generate a password
        */
        case "pw_generate":
            include('../includes/libraries/pwgen/pwgen.class.php');
            $pwgen = new PWGen();

            // Set pw size
            $pwgen->setLength($_POST['size']);
            // Include at least one number in the password
            $pwgen->setNumerals( ($_POST['num'] == "true")? true : false);
            // Include at least one capital letter in the password
            $pwgen->setCapitalize( ($_POST['maj'] == "true")? true : false);
            // Include at least one symbol in the password
            $pwgen->setSymbols( ($_POST['symb'] == "true")? true : false);
            // Complete random, hard to memorize password
            if (isset($_POST['secure']) && $_POST['secure'] == "true"){
                $pwgen->setSecure(true);
                $pwgen->setSymbols(true);
                $pwgen->setCapitalize(true);
                $pwgen->setNumerals(true);
            }else
                $pwgen->setSecure(false);

        	echo json_encode(array("key" => $pwgen->generate()),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
        break;


       	/*
	 	* CASE
		* Delete an item
       	*/
        case "del_item":
        	//Check KEY and rights
        	if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
        		//error
        		break;
        	}
            //delete item consists in disabling it
            $db->query_update(
                "items",
                array(
                    'inactif' => '1',
                ),
                "id = ".$_POST['id']
            );
            //log
            $db->query_insert(
                "log_items",
                array(
                    'id_item' => $_POST['id'],
                    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                    'id_user' => $_SESSION['user_id'],
                    'action' => 'at_delete'
                )
            );

            //Update CACHE table
            UpdateCacheTable("delete_value",$_POST['id']);
        break;


       	/*
       	* CASE
       	* Update a Group
       	*/
        case "update_rep":
        	//Check KEY and rights
        	if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
        		//error
        		break;
        	}
        	//decrypt and retreive data in JSON format
        	require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
        	require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
        	$data_received = json_decode((AesCtr::decrypt($_POST['data'], $_SESSION['key'], 256)), true);

        	//Prepare variables
        	$title = htmlspecialchars_decode($data_received['title']);

            //Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
            	//send data
            	echo '[{"error" : "'.$txt['error_html_codes'].'"}]';
            }else{

                //update Folders table
                $db->query_update(
                    "nested_tree",
                    array(
                        'title' => $title
                    ),
                    'id='.$data_received['folder']
                );

                //update complixity value
                $db->query_update(
                    "misc",
                    array(
                        'valeur' => $data_received['complexity']
                    ),
                    'intitule = "'.$data_received['folder'].'" AND type = "complex"'
                );

                //rebuild fuild tree folder
                require_once('NestedTree.class.php');
                $tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
                $tree->rebuild();

                //send data
                echo '[{"error" : ""}]';
            }
        break;


        /*
        * CASE
        * Store hierarchic position of Group
        */
        case 'save_position':
            require_once ("NestedTree.class.php");
            $db->query_update(
                "nested_tree",
                array(
                    'parent_id' => $_POST['destination']
                ),
                'id = '.$_POST['source']
            );
            $tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();
        break;

        /*
        * CASE
        * List items of a group
        */
        case 'lister_items_groupe':
            $arbo_html = $html = "";
        	$folder_is_pf = $show_error = 0;
        	$items_id_list = $rights = array();
        	$html = '';
        	$returned_data = array();

        	//Build query limits
        	if (empty($_POST['start'])) {
        		$start = 0;
        	}else{
        		$start = $_POST['start'];
        	}

            //Prepare tree
            require_once ("NestedTree.class.php");
            $tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $arbo = $tree->getPath($_POST['id'], true);
            foreach($arbo as $elem){
            	if ( $elem->title == $_SESSION['user_id'] && $elem->nlevel == 1 ) {
            		$elem->title = $_SESSION['login'];
            		$folder_is_pf = 1;
            	}
            	$arbo_html_tmp = '<a id="path_elem_'.$elem->id.'"';
            	if(in_array($elem->id, $_SESSION['groupes_visibles'])) $arbo_html_tmp .= ' style="cursor:pointer;" onclick="ListerItems('.$elem->id.', \'\', 0)"';
            	$arbo_html_tmp .= '>'.htmlspecialchars(stripslashes($elem->title), ENT_QUOTES).'</a>';
            	if (empty($arbo_html)) {
            		$arbo_html = $arbo_html_tmp;
            	}else{
            		$arbo_html .= ' » '.$arbo_html_tmp;
            	}
            }

            //Check if ID folder send is valid
            if(!in_array($_POST['id'], $_SESSION['groupes_visibles']))
				$_POST['id'] = 1;

        	//check if this folder is a PF. If yes check if saltket is set
        	if ((!isset($_SESSION['my_sk']) || empty($_SESSION['my_sk'])) && $folder_is_pf == 1) {
            	$show_error = "is_pf_but_no_saltkey";
        	}

            //check if items exist
        	if (isset($_POST['restricted']) && $_POST['restricted'] == 1) {
        		$data_count[0] = count($_SESSION['list_folders_limited'][$_POST['id']]);
        		$where_arg = " AND i.id IN (".implode(',', $_SESSION['list_folders_limited'][$_POST['id']]).")";
        	}
			//check if this folder is visible
        	else if (!in_array($_POST['id'], array_merge($_SESSION['groupes_visibles'], @array_keys($_SESSION['list_restricted_folders_for_items'])))) {
        		require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
        		require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
        		echo AesCtr::encrypt(json_encode(array("error" => "not_authorized"), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);
        		break;
        	}else{
        		$data_count = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."items WHERE inactif = 0");
        		$where_arg = " AND i.id_tree=".$_POST['id'];
        	}

            if ($data_count[0] > 0 && empty($show_error)){
                //init variables
                $init_personal_folder = false;
                $expired_item = false;
            	$limited_to_items = "";

            	//
            	/*if (in_array($_POST['id'],@array_keys($_SESSION['list_restricted_folders_for_items']))){
            		foreach($_SESSION['list_restricted_folders_for_items'][$_POST['id']] as $reccord){
            			if(empty($limited_to_items)) $limited_to_items = $reccord;
            			else $limited_to_items .= ",".$reccord;
            		}
            	}*/

                //List all ITEMS
            	if($folder_is_pf == 0){
            		$query = "SELECT DISTINCT i.id AS id, i.restricted_to AS restricted_to, i.perso AS perso, i.label AS label,
                    	i.description AS description, i.pw AS pw, i.login AS login, i.anyone_can_modify AS anyone_can_modify,
                        l.date AS date,
                        n.renewal_period AS renewal_period,
                        l.action AS log_action, l.id_user AS log_user,
                        k.rand_key AS rand_key
                    FROM ".$pre."items AS i
                    INNER JOIN ".$pre."nested_tree AS n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items AS l ON (i.id = l.id_item)
					LEFT JOIN ".$pre."keys AS k ON (k.id = i.id)
                    WHERE i.inactif = 0".
            		$where_arg."
                    AND l.action = 'at_creation'";
            		if(!empty($limited_to_items)) $query .= "
					AND i.id IN (".$limited_to_items.")";
            		$query .= "
					ORDER BY i.label ASC, l.date DESC";
            		if($_POST['nb_items_to_display_once'] != 'max') $query .= "
					LIMIT ".$start.",".$_POST['nb_items_to_display_once'];

            		$rows = $db->fetch_all_array($query);
            	}else{
            		$query = "SELECT DISTINCT i.id AS id, i.restricted_to AS restricted_to, i.perso AS perso, i.label AS label, i.description AS description, i.pw AS pw, i.login AS login, i.anyone_can_modify AS anyone_can_modify,
                        l.date AS date,
                        n.renewal_period AS renewal_period,
                        l.action AS log_action, l.id_user AS log_user
                    FROM ".$pre."items AS i
                    INNER JOIN ".$pre."nested_tree AS n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items AS l ON (i.id = l.id_item)
                    WHERE i.inactif = 0".
            		$where_arg."
                    AND (l.action = 'at_creation')
                    ORDER BY i.label ASC, l.date DESC";
            		if($_POST['nb_items_to_display_once'] != 'max') $query .= "
						LIMIT ".$start.",".$_POST['nb_items_to_display_once'];

            		$rows = $db->fetch_all_array($query);
            	}
            	// REMOVED:  OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%')
                $id_managed = '';
                $i = 0;

                foreach( $rows as $reccord ) {
                    //exclude all results except the first one returned by query
                    if ( empty($id_managed) || $id_managed != $reccord['id'] ){
						//$returned_data[$i] = array('id' => $reccord['id']);
                        //Get Expiration date
                        $expiration_flag = '';
                        $expired_item = 0;
                        if ( $_SESSION['settings']['activate_expiration'] == 1 ){
                            $expiration_flag = '<img src="includes/images/flag-green.png">';
                            if ( $reccord['renewal_period']> 0 && ($reccord['date'] + ($reccord['renewal_period'] * $k['one_month_seconds'])) < time() ){
                                $expiration_flag = '<img src="includes/images/flag-red.png">';
                            	$expired_item = 1;
                            	//$returned_data[$i]['flag'] = 'red';
                            }//else
                        		//$returned_data[$i]['flag'] = 'green';
                        }

                        //list of restricted users
                        $restricted_users_array = explode(';',$reccord['restricted_to']);
                        $item_pw = $item_login = "";
                        $display_item = $need_sk = $can_move = $item_is_restricted_to_role = 0;

                        //TODO: Element is restricted to a group. Check if element can be seen by user
                    	//=> récupérer un tableau contenant les roles associés à cet ID (a partir table restriction_to_roles)
                    	$user_is_included_in_role = 0;
                        $roles = $db->fetch_all_array("SELECT role_id FROM ".$pre."restriction_to_roles WHERE item_id=".$reccord['id']);
                        if (count($roles) > 0){
                        	$item_is_restricted_to_role = 1;
                        	foreach ($roles as $val){
                        		if (in_array($val['role_id'], $_SESSION['user_roles'])){
                        			$user_is_included_in_role = 1;
                        			break;
                        		}
                        	}
                        }

                    	//Manage the restricted_to variable
                    	if (isset($_POST['restricted'])) {
                    		$restricted_to = $_POST['restricted'];
                    	}else{
                    		$restricted_to = "";
                    	}

                    	if (isset($_SESSION['list_folders_editable_by_role']) && in_array($_POST['id'], $_SESSION['list_folders_editable_by_role'])) {
                    		if (empty($restricted_to)) {
                    			$restricted_to = $_SESSION['user_id'];
                    		}else{
                    			$restricted_to .= ','.$_SESSION['user_id'];
                    		}
                    	}

                    	//Can user modify it?
                    	if ($reccord['anyone_can_modify'] == 1 || ($_SESSION['user_id'] == $reccord['log_user']) || ($_SESSION['user_read_only'] == 1 && $folder_is_pf == 0)) {
                    		$can_move = 1;
                    	}

                        //CASE where item is restricted to a role to which the user is not associated
                        if (isset($user_is_included_in_role) && $user_is_included_in_role == 0
                        		&& isset($item_is_restricted_to_role) && $item_is_restricted_to_role == 1
                        		&& !in_array($_SESSION['user_id'],$restricted_users_array)
                        ){
                        	$perso = '<img src="includes/images/tag-small-red.png">';
                        //	$returned_data[$i]['perso'] = 'red';
                        	$recherche_group_pf = 0;
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\', \'0\', \''.$expired_item.'\', \''.$restricted_to.'\', \'no_display\')';
                        	/*$returned_data[$i]['detail_item'] = 0;
                        	$returned_data[$i]['expired_item'] = $expired_item;
                        	$returned_data[$i]['restricted_to'] = $restricted_to;
                        	$returned_data[$i]['display'] = 'no_display';*/
                            $display_item = $need_sk = $can_move = 0;
                        }else
                        //Case where item is in own personal folder
                        if ( in_array($_POST['id'],$_SESSION['personal_visible_groups']) && $reccord['perso'] == 1 ){
                        	$perso = '<img src="includes/images/tag-small-alert.png">';
                        	//$returned_data[$i]['perso'] = 'alert';
                        	$recherche_group_pf = 1;
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\', \'1\', \''.$expired_item.'\', \''.$restricted_to.'\')';
                        	/*$returned_data[$i]['detail_item'] = 1;
                        	$returned_data[$i]['expired_item'] = $expired_item;
                        	$returned_data[$i]['restricted_to'] = $restricted_to;
                        	$returned_data[$i]['display'] = '';*/
                            $display_item = $need_sk = $can_move = 1;
                        }else
                        //CAse where item is restricted to a group of users included user
                        if ( !empty($reccord['restricted_to']) && in_array($_SESSION['user_id'],$restricted_users_array) || (isset($_SESSION['list_folders_editable_by_role']) && in_array($_POST['id'], $_SESSION['list_folders_editable_by_role'])) && in_array($_SESSION['user_id'],$restricted_users_array)){
                        	$perso = '<img src="includes/images/tag-small-yellow.png">';
                        	//$returned_data[$i]['perso'] = 'yellow';
                        	$recherche_group_pf = 0;
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restricted_to.'\')';
                        	/*$returned_data[$i]['detail_item'] = 0;
                        	$returned_data[$i]['expired_item'] = $expired_item;
                        	$returned_data[$i]['restricted_to'] = $restricted_to;
                        	$returned_data[$i]['display'] = '';*/
                            $display_item = 1;
                        }else
                        //CAse where item is restricted to a group of users not including user
                        if (
                        	$reccord['perso'] == 1
                        	|| (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'],$restricted_users_array))
                        	|| (isset($user_is_included_in_role) && isset($item_is_restricted_to_role) && $user_is_included_in_role == 0 && $item_is_restricted_to_role == 1)
                        ){
	                        if (isset($user_is_included_in_role) && isset($item_is_restricted_to_role) && $user_is_included_in_role == 0 && $item_is_restricted_to_role == 1){
	                        	$perso = '<img src="includes/images/tag-small-red.png">';
	                        	//$returned_data[$i]['perso'] = 'red';
	                        	$recherche_group_pf = 0;
	                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\', \'0\', \''.$expired_item.'\', \''.$restricted_to.'\', \'no_display\')';
	                        	/*$returned_data[$i]['detail_item'] = 0;
	                        	$returned_data[$i]['expired_item'] = $expired_item;
	                        	$returned_data[$i]['restricted_to'] = $restricted_to;
	                        	$returned_data[$i]['display'] = 'no_display';*/
	                            $display_item = $need_sk = $can_move = 0;
	                        }else{
	                        	$perso = '<img src="includes/images/tag-small-yellow.png">';
	                        	//$returned_data[$i]['perso'] = 'yellow';
	                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restricted_to.'\')';
	                        	/*$returned_data[$i]['detail_item'] = 0;
	                        	$returned_data[$i]['expired_item'] = $expired_item;
	                        	$returned_data[$i]['restricted_to'] = $restricted_to;
	                        	$returned_data[$i]['display'] = '';*/
	                            //reinit in case of not personal group
	                            if ( $init_personal_folder == false ){
	                            	$recherche_group_pf = "";
	                                $init_personal_folder = true;
	                            }
	                            //
	                            if ( !empty($reccord['restricted_to']) && in_array($_SESSION['user_id'],$restricted_users_array) )
	                            	$display_item = 1;
	                        }
	                    }
                        else{
                        	$perso = '<img src="includes/images/tag-small-green.png">';
                        	//$returned_data[$i]['perso'] = 'green';
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restricted_to.'\')';
                        	/*$returned_data[$i]['detail_item'] = 0;
                        	$returned_data[$i]['expired_item'] = $expired_item;
                        	$returned_data[$i]['restricted_to'] = $restricted_to;
                        	$returned_data[$i]['display'] = '';*/
                            $display_item = 1;
                            //reinit in case of not personal group
                            if ( $init_personal_folder == false ){
                                //echo 'document.getElementById("recherche_group_pf").value = "";';
                            	$recherche_group_pf = "";
                                $init_personal_folder = true;
                            }
                        }

                        // Prepare full line
                    	$html .= '<li name="'.strip_tags(stripslashes(CleanString($reccord['label']))).'" class="';
                        if ($can_move == 1) {
                        	$html .= 'item_draggable';
                        }else{
                        	$html .= 'item';
                        }

                    	$html .= '" id="'.$reccord['id'].'" style="margin-left:-30px;">';

                    	if ($can_move == 1) {
                    		$html .= '<img src="includes/images/grippy.png" style="margin-right:5px;cursor:hand;" alt="" class="grippy"  />';
                    		//$returned_data[$i]['grippy'] = 1;
                    	}else{
                    		$html .= '<span style="margin-left:11px;"></span>';
                    		//$returned_data[$i]['grippy'] = 0;
                    	}
						/*
                    	$returned_data[$i]['can_move'] = ($can_move == 1) ? 'item_draggable' : 'item';
                    	$returned_data[$i]['li_label'] = strip_tags(stripslashes(CleanString($reccord['label'])));
                    	$returned_data[$i]['label'] = "'".stripslashes($reccord['label'])."'";
                    	$returned_data[$i]['description'] = (!empty($reccord['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1) ? '&nbsp;<font size=2px>['.strip_tags(stripslashes(substr(CleanString($reccord['description']),0,30))).']</font>' : '';
                    	*/
						$html .= $expiration_flag.''.$perso.'&nbsp;<a id="fileclass'.$reccord['id'].'" class="file" onclick="'.$action.'">'.substr(stripslashes($reccord['label']), 0, 65);
                        if (!empty($reccord['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1)
                            $html .= '&nbsp;<font size=2px>['.strip_tags(stripslashes(substr(CleanString($reccord['description']),0,30))).']</font>';
                        $html .= '</a>';


                        // increment array for icons shortcuts (don't do if option is not enabled)
                    	if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
	                    	if ($need_sk == true && isset($_SESSION['my_sk'])) {
	                    		$pw = decrypt($reccord['pw'],mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
	                    	}else{
	                    		$pw = substr(decrypt($reccord['pw']), strlen($reccord['rand_key']));
	                    	}
                    	}else{
                    		$pw = "";
                    	}
                    	//test charset => may cause a json error if is not utf8
                    	if(!is_utf8($pw)){
                    		$pw = "";
                    		$html .= '&nbsp;<img src="includes/images/exclamation_small_red.png" title="'.$txt['pw_encryption_error'].'" />';
                    	}

                    	$html .= '<span style="float:right;margin:2px 10px 0px 0px;">';
                        // display quick icon shortcuts ?
                    	if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                    		$item_login = '<img src="includes/images/mini_user_disable.png" id="icon_login_'.$reccord['id'].'" />';
                    		$item_pw = '<img src="includes/images/mini_lock_disable.png" id="icon_pw_'.$reccord['id'].'" class="copy_clipboard" />';
                    		if ($display_item == true) {
                    			if (!empty($reccord['login'])) {
                    				$item_login = '<img src="includes/images/mini_user_enable.png" id="iconlogin_'.$reccord['id'].'" class="copy_clipboard" onclick="get_clipboard_item(\'login\','.$reccord['id'].')" title="'.$txt['item_menu_copy_login'].'" />';
                    			}
                    			if (!empty($reccord['pw'])) {
                    				$item_pw = '<img src="includes/images/mini_lock_enable.png" id="iconpw_'.$reccord['id'].'" class="copy_clipboard" onclick="get_clipboard_item(\'pw\','.$reccord['id'].')" title="'.$txt['item_menu_copy_pw'].'" />';
                    			}
                    		}
                    		$html .= $item_login.'&nbsp;'.$item_pw.
                    			'<input type="hidden" id="item_pw_in_list_'.$reccord['id'].'" value="'.$pw.'"><input type="hidden" id="item_login_in_list_'.$reccord['id'].'" value="'.$reccord['login'].'">';
                    	}

                    	// Prepare make Favorite small icon
                    	$html .= '&nbsp;<span id="quick_icon_fav_'.$reccord['id'].'" title="Manage Favorite" class="cursor">';
                    	if (in_array($reccord['id'], $_SESSION['favourites'])) {
                    		$html .= '<img src="includes/images/mini_star_enable.png" onclick="ActionOnQuickIcon('.$reccord['id'].',0)" />';
                    	}else {
                    		$html .= '<img src="includes/images/mini_star_disable.png"" onclick="ActionOnQuickIcon('.$reccord['id'].',1)" />';
                    	}

                    	//mini icon for collab
                    	if (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1) {
                    		if ($reccord['anyone_can_modify'] == 1) {
                    			$item_collab = '&nbsp;<img src="includes/images/mini_collab_enable.png" title="'.$txt['item_menu_collab_enable'].'" />';
                    		}else{
                    			$item_collab = '&nbsp;<img src="includes/images/mini_collab_disable.png" title="'.$txt['item_menu_collab_disable'].'" />';
                    		}
                    		$html .= '</span>'.$item_collab.'</span>';
                    	}else{
                    		$html .= '</span>';
                    	}

                    	$html .= '</span></li>';

                    	//Build array with items
                        array_push($items_id_list,array($reccord['id'], $pw, $reccord['login'], $display_item));

                        $i ++;
                    }
                    $id_managed = $reccord['id'];
                }

                $rights = RecupDroitCreationSansComplexite($_POST['id']);
            }

            //Identify of it is a personal folder
            if (in_array($_POST['id'],$_SESSION['personal_visible_groups'])){
                $recherche_group_pf = 1;
            }else{
                $recherche_group_pf = "";
            }

        	//count
        	$count_items = $db->fetch_row("
                    SELECT COUNT(*)
                    FROM ".$pre."items AS i
                    INNER JOIN ".$pre."nested_tree AS n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items AS l ON (i.id = l.id_item)
                    WHERE i.inactif = 0".
        	$where_arg."
                    AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
                    ORDER BY i.label ASC, l.date DESC");


        	//Check list to be continued status
        	if (($_POST['nb_items_to_display_once'] + $start) < $count_items[0] && $_POST['nb_items_to_display_once'] != "max" ) {
        		$list_to_be_continued = "yes";
        	}
        	else {
        		$list_to_be_continued = "end";
        	}

        	//Get folder complexity
        	$folder_complexity = $db->fetch_row("SELECT valeur FROM ".$pre."misc WHERE type = 'complex' AND intitule = '".$_POST['id']."'");

        	//print_r($returned_data);
        	//Prepare returned values
        	$return_values = array(
        		"recherche_group_pf" => $recherche_group_pf,
        		"arborescence" => "<img src='includes/images/folder-open.png' />&nbsp;".$arbo_html,
        		"array_items" => $items_id_list,
        		"items_html" => $html,
        		"error" => $show_error,
	        	"saltkey_is_required" => $folder_is_pf,
	        	"show_clipboard_small_icons" => isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? 1 : 0,
	        	"next_start" => $_POST['nb_items_to_display_once'] + $start,
	        	"list_to_be_continued" => $list_to_be_continued,
	        	"items_count" => $count_items[0],
	        	'folder_complexity' => $folder_complexity[0],
	        	//"items" => $returned_data
			);

        	//Check if $rights is not null
        	if (count( $rights) > 0) {
        		$return_values = array_merge($return_values, $rights);
        	}
//print_r($return_values);
        	//Encrypt data to return
        	require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
        	require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
        	$return_values = AesCtr::encrypt(json_encode($return_values,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), $_SESSION['key'], 256);

        	//return data
        	echo $return_values;

        break;



       	/*
       	* CASE
       	* Get complexity level of a group
       	*/
        case "recup_complex":
            $data = $db->fetch_row("SELECT valeur FROM ".$pre."misc WHERE type='complex' AND intitule = '".$_POST['groupe']."'");

        	if(isset($data[0]) && (!empty($data[0]) || $data[0] == 0)){
        		$complexity = $pw_complexity[$data[0]][1];
        	}else{
        		$complexity = $txt['not_defined'];
        	}

            //afficher la visibilité
            $visibilite = "";
            if ( !empty($data_pf[0]) ){
                $visibilite = $_SESSION['login'];
            }else{
            	$rows = $db->fetch_all_array("
								SELECT t.title
								FROM ".$pre."roles_values AS v
								INNER JOIN ".$pre."roles_title AS t ON (v.role_id = t.id)
								WHERE v.folder_id = '".$_POST['groupe']."'");
            	foreach ($rows as $reccord){
            		if ( empty($visibilite) ) $visibilite = $reccord['title'];
            		else $visibilite .= " - ".$reccord['title'];
            	}
            }

            RecupDroitCreationSansComplexite($_POST['groupe']);

        	$return_values = array(
        		"val" => $data[0],
        		"visibility" => $visibilite,
        		"complexity" => $complexity
			);

        	echo json_encode($return_values,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
        break;

       	/*
       	   * CASE
       	   * WANT TO CLIPBOARD PW/LOGIN OF ITEM
       	*/
        case "get_clipboard_item":
        	$sql = "SELECT pw,login,perso
                    FROM ".$pre."items
                    WHERE id=".$_POST['id'];
        	$data_item = $db->query_first($sql);

        	if($_POST['field'] == "pw"){
        		if($data_item['perso'] == 1){
        			$data = decrypt($data_item['pw'],mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
        		}else{
        			$pw = decrypt($data_item['pw']);
        			$data_item_key = $db->query_first('SELECT rand_key FROM `'.$pre.'keys` WHERE `table`="items" AND `id`='.$_POST['id']);
        			$data = substr($pw, strlen($data_item_key['rand_key']));
        		}
        	}else $data = $data_item['login'];
        	//Encrypt data to return
        	require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
        	require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
        	$return_values = AesCtr::encrypt($data, $_SESSION['key'], 256);
        	echo $return_values;
        	break;


       	/*
       	* CASE
       	* DELETE attached file from an item
       	*/
        case "delete_attached_file":
            //Get some info before deleting
            $data = $db->fetch_row("SELECT name,id_item,file FROM ".$pre."files WHERE id = '".$_POST['file_id']."'");
            if ( !empty($data[1]) ){

                //Delete from FILES table
                $db->query("DELETE FROM ".$pre."files WHERE id = '".$_POST['file_id']."'");

                //Update the log
                $db->query_insert(
                    'log_items',
                    array(
                        'id_item' => $data[1],
                        'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_modification',
                        'raison' => 'at_del_file : '. $data[0]
                    )
                );

                //Delete file from server
                @unlink($_SESSION['settings']['path_to_upload_folder']."/".$data[2]);
            }
        break;


    	/*
        * CASE
        * REBUILD the description editor
       	*/
        case "rebuild_description_textarea":
            $return_values = array();
            if ( isset($_SESSION['settings']['richtext']) && $_SESSION['settings']['richtext'] == 1 ){
            	if ( $_POST['id'] == "desc" ){
            		$return_values['desc'] = '$("#desc").ckeditor({toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]], height: 100,language: "'. $k['langs'][$_SESSION['user_language']].'"});';
            	}else if ( $_POST['id'] == "edit_desc" ){
            		$return_values['desc'] = 'CKEDITOR.replace("edit_desc",{toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]], height: 100,language: "'. $k['langs'][$_SESSION['user_language']].'"});';
            	}
            }

        	//Multselect
        	$return_values['multi_select'] = '$("#edit_restricted_to_list").multiselect({selectedList: 7, minWidth: 430, height: 145, checkAllText: "'.$txt['check_all_text'].'", uncheckAllText: "'.$txt['uncheck_all_text'].'",noneSelectedText: "'.$txt['none_selected_text'].'"});';

            //Display popup
            if ( $_POST['id'] == "edit_desc" )
                $return_values['dialog'] = '$("#div_formulaire_edition_item").dialog("open");';
            else
                $return_values['dialog'] = '$("#div_formulaire_saisi").dialog("open");';

            echo $return_values;
        break;


       	/*
       	* CASE
       	* Clear HTML tags
       	*/
    	case "clear_html_tags":
    		//Get information for this item
    		$sql = "SELECT description
                    FROM ".$pre."items
                    WHERE id=".$_POST['id_item'];
    		$data_item = $db->query_first($sql);

    		//Clean up the string
    		//echo '$("#edit_desc").val("'.stripslashes(str_replace('\n','\\\n',mysql_real_escape_string(strip_tags($data_item['description'])))).'");';
			echo json_encode(array("description" => strip_tags($data_item['description'])),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
    	break;

    	/*
    	   * FUNCTION
    	   * Launch an action when clicking on a quick icon
    	   * $action = 0 => Make not favorite
    	   * $action = 1 => Make favorite
    	*/
		case "action_on_quick_icon":
			if ($_POST['action'] == 1) {
				//Add new favourite
				array_push($_SESSION['favourites'], $_POST['id']);
				$db->query_update(
				"users",
				array(
				    'favourites' => implode(';', $_SESSION['favourites'])
				),
				'id = '.$_SESSION['user_id']
				);

				//Update SESSION with this new favourite
				$data = $db->query("SELECT label,id_tree FROM ".$pre."items WHERE id = ".$_POST['id']);
				$_SESSION['favourites_tab'][$_POST['id']] = array(
		            'label'=>$data['label'],
		            'url'=>'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$_POST['id']
		        );
			}else if ($_POST['action'] == 0) {
				//delete from session
				foreach ($_SESSION['favourites'] as $key => $value){
					if ($_SESSION['favourites'][$key] == $_POST['id']){
						unset($_SESSION['favourites'][$key]);
						break;
					}
				}

				//delete from DB
				$db->query("UPDATE ".$pre."users SET favourites = '".implode(';', $_SESSION['favourites'])."' WHERE id = '".$_SESSION['user_id']."'");
				//refresh session fav list
				foreach ($_SESSION['favourites_tab'] as $key => $value){
					if ($key == $_POST['id']){
						unset($_SESSION['favourites_tab'][$key]);
						break;
					}
				}
			}
		break;


		/*
		* CASE
		* Move an ITEM
		*/
    	case "move_item":
    		//Check KEY and rights
    		if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
    			//error
    			exit();
    		}
    		//get data about item
    		$data_source = $db->query_first("
					SELECT i.pw, f.personal_folder,i.id_tree, f.title
					FROM ".$pre."items AS i
					INNER JOIN ".$pre."nested_tree AS f ON (i.id_tree=f.id)
					WHERE i.id=".$_POST['item_id']
    		);

    		//get data about new folder
    		$data_destination = $db->query_first("SELECT personal_folder, title FROM ".$pre."nested_tree WHERE id = '".$_POST['folder_id']."'");

    		//update item
    		$db->query_update(
	    		'items',
	    		array(
	    		    'id_tree' => $_POST['folder_id']
	    		),
	    		"id='".$_POST['item_id']."'"
    		);

    		//previous is non personal folder and new too
    		if ($data_source['personal_folder'] == 0 && $data_destination['personal_folder'] == 0){
    			//just update is needed. Item key is the same
    		}

    		//previous is not personal folder and new is personal folder => item key exist on item => suppress it => OK !
    		else if ($data_source['personal_folder'] == 0 && $data_destination['personal_folder'] == 1){
    			//get key for original pw
    			$original_data = $db->query_first('
					SELECT k.rand_key, i.pw
					FROM `'.$pre.'keys` AS k
					INNER JOIN `'.$pre.'items` AS i ON (k.id=i.id)
					WHERE k.table LIKE "items"
					AND i.id='.$_POST['item_id']
    			);

    			//unsalt previous pw and encrupt with personal key
    			$pw = substr(decrypt($original_data['pw']), strlen($original_data['rand_key']));
    			$pw = encrypt($pw, mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));

    			//update pw
    			$db->query_update(
	    			'items',
	    			array(
	    			    'pw' => $pw,
	    			    'perso' => 1
	    			),
	    			"id='".$_POST['item_id']."'"
    			);

    			//Delete key
    			$db->query_delete(
	    			'keys',
	    			array(
		    			'id' => $_POST['item_id'],
		    			'table' => 'items'
		    		)
    			);
    		}

    		//If previous is personal folder and new is personal folder too => no key exist on item
			else if ($data_source['personal_folder'] == 1 && $data_destination['personal_folder'] == 1){
				//NOTHING TO DO => just update is needed. Item key is the same
			}

    		//If previous is personal folder and new is not personal folder => no key exist on item => add new
    		else if ($data_source['personal_folder'] == 1 && $data_destination['personal_folder'] == 0){
    			//generate random key
    			$random_key = GenerateKey();

    			//store key
    			$db->query_insert(
	    			'keys',
	    			array(
	    			    'table' => 'items',
	    			    'id' => $_POST['item_id'],
	    			    'rand_key' => $random_key
	    			)
    			);

    			//update item
    			$db->query_update(
	    			'items',
	    			array(
		    			'pw' => encrypt($random_key.decrypt($data_source['pw'], mysql_real_escape_string(stripslashes($_SESSION['my_sk'])))),
		    			'perso' => 0
	    			),
	    			"id='".$_POST['item_id']."'"
    			);
    		}

				//Log item moved
				$db->query_insert(
	          'log_items',
	          array(
	              'id_item' => $_POST['item_id'],
	              'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	              'id_user' => $_SESSION['user_id'],
								'action' => 'at_modification',
	              'raison' => 'at_moved : '.$data_source['title'].' -> '.$data_destination['title']
	          )
	      );

				echo '[{"from_folder":"'.$data_source['id_tree'].'" , "to_folder":"'.$_POST['folder_id'].'"}]';
    	break;


    	/*
    	   * CASE
    	   * Send email
    	*/
    	case "send_email":
    		if ($_POST['key'] != $_SESSION['key']){
    			echo '[{"error" : "something_wrong"}]';
    			break;
    		}else{
	    		if(!empty($_POST['content'])) $content = explode(',', $_POST['content']);
	    		if($_POST['cat'] == "request_access_to_author"){
	    			$data_author = $db->query_first("SELECT email,login FROM ".$pre."users WHERE id= ".$content[1]);
	    			$data_item = $db->query_first("SELECT label FROM ".$pre."items WHERE id= ".$content[0]);
	    			$ret = SendEmail(
	    				$txt['email_request_access_subject'],
	    				str_replace(array('#tp_item_author#', '#tp_user#', '#tp_item#'), array(" ".addslashes($data_author['login']), addslashes($_SESSION['login']), addslashes($data_item['label'])), $txt['email_request_access_mail']),
	    				$data_author['email']
					);
	    		}else if($_POST['cat'] == "share_this_item"){
	    			$data_item = $db->query_first("SELECT label,id_tree FROM ".$pre."items WHERE id= ".$_POST['id']);
	    			$ret = SendEmail(
	    				$txt['email_share_item_subject'],
	    				str_replace(
							array('#tp_link#', '#tp_user#', '#tp_item#'),
							array($_SESSION['settings']['cpassman_url'].'/index.php?page=items&group='.$data_item['id_tree'].'&id='.$_POST['id'], addslashes($_SESSION['login']), addslashes($data_item['label'])),
							$txt['email_share_item_mail']
						),
	    				$_POST['receipt']
					);
					echo '[{'.$ret.'}]';
	    		}
    		}
    	break;

    	/*
    	   * CASE
    	   * manage notification of an Item
    	*/
    	case "notify_a_user":
    		if ($_POST['key'] != $_SESSION['key']){
    			echo '[{"error" : "something_wrong"}]';
        		break;
    		}else{
    			if($_POST['notify_type'] == "on_show"){
	    			//Check if values already exist
	    			$data = $db->query_first("SELECT notification FROM ".$pre."items WHERE id = ".$_POST['item_id']);
	    			$notified_users = explode(';', $data['notification']);
	    			//User is not in actual notification list
	    			if($_POST['status'] == true && !in_array($_POST['user_id'], $notified_users)){
		    			//User is not in actual notification list and wants to be notified
	    				$db->query_update(
			    			'items',
			    			array(
				    			'notification' => empty($data['notification']) ? $_POST['user_id'].";" : $data['notification'].$_POST['user_id']
			    			),
			    			"id='".$_POST['item_id']."'"
		    			);
		    			echo '[{"error" : "", "new_status":"true"}]';
        				break;
	    			}else if($_POST['status'] == false && in_array($_POST['user_id'], $notified_users)){
	    				//TODO : delete user from array and store in DB
		    			//User is in actual notification list and doesn't want to be notified
		    			$db->query_update(
			    			'items',
			    			array(
				    			'notification' => empty($data['notification']) ? $_POST['user_id'] : $data['notification'].";".$_POST['user_id']
			    			),
			    			"id='".$_POST['item_id']."'"
		    			);
		    		}
    			}
    		}
    	break;


    }
}


// Build the QUERY in case of GET
if ( isset($_GET['type']) ){
    switch($_GET['type'])
    {
    	/*
    	* CASE
    	* Autocomplet for TAGS
    	*/
        case "autocomplete_tags":
            //Get a list off all existing TAGS
            $rows = $db->fetch_all_array("SELECT tag FROM ".$pre."tags GROUP BY tag");
            foreach ($rows as $reccord ){
                echo $reccord['tag']."|".$reccord['tag']."\n";
            }
        break;
    }
}


/*
* FUNCTION
* Identify if this group authorize creation of item without the complexit level reached
*/
function RecupDroitCreationSansComplexite($groupe){
    global $db, $pre;
    $data = $db->fetch_row("SELECT bloquer_creation,bloquer_modification,personal_folder FROM ".$pre."nested_tree WHERE id = '".$groupe."'");

    //Check if it's in a personal folder. If yes, then force complexity overhead.
    if ( $data[2] == 1 ){
        //echo 'document.getElementById("bloquer_modification_complexite").value = "1";';
        //echo 'document.getElementById("bloquer_creation_complexite").value = "1";';
    	return array("bloquer_modification_complexite"=>1,"bloquer_creation_complexite"=>1);
    }else{
        //echo 'document.getElementById("bloquer_creation_complexite").value = "'.$data[0].'";';
    	//echo 'document.getElementById("bloquer_modification_complexite").value = "'.$data[1].'";';
    	return array("bloquer_modification_complexite"=>$data[1],"bloquer_creation_complexite"=>$data[0]);
    }

}

/*
   * FUNCTION
* permits to identify what icon to display depending on file extension
*/
function file_format_image($ext){
	global $k;
	if ( in_array($ext,$k['office_file_ext']) ) $image = "document-office.png";
	else if ( $ext == "pdf" ) $image = "document-pdf.png";
	else if ( in_array($ext,$k['image_file_ext']) ) $image = "document-image.png";
	else if ( $ext == "txt" ) $image = "document-txt.png";
	else  $image = "document.png";
	return $image;
}

/*
   * FUNCTION
   * permits to remplace some specific characters in password
*/
function password_replacement($pw){
	$pw_patterns = array('/ETCOMMERCIAL/','/SIGNEPLUS/');
	$pw_remplacements = array('&','+');
	return preg_replace($pw_patterns,$pw_remplacements,$pw);
}


?>