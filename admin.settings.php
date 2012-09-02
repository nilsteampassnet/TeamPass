<?php
/**
 * @file 		admin.settings.php
 * @author		Nils Laumaill�
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaill�
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');


/*
* FUNCTION permitting to store into DB the settings changes
*/
function UpdateSettings($setting, $val, $type=''){
    global $server, $user, $pass, $database, $pre;

    if ( empty($type) ) $type = 'admin';

    //Connect to database
    require_once("sources/class.database.php");
    $db = new Database($server, $user, $pass, $database, $pre);
    $db->connect();

    //Check if setting is already in DB. If NO then insert, if YES then update.
    $data = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."misc WHERE type='".$type."' AND intitule = '".$setting."'");
    if ( $data[0] == 0 ){
        $db->query_insert(
            "misc",
            array(
                'valeur' => $val,
                'type' => $type,
                'intitule' => $setting
            )
        );
        //in case of stats enabled, add the actual time
        if ( $setting == 'send_stats' )
            $db->query_insert(
                "misc",
                array(
                    'valeur' => time(),
                    'type' => $type,
                    'intitule' => $setting.'_time'
                )
            );
    }else{
        $db->query_update(
            "misc",
            array(
                'valeur' => $val
            ),
            "type='".$type."' AND intitule = '".$setting."'"
        );
        //in case of stats enabled, update the actual time
    	if ($setting == 'send_stats'){
    		//Check if previous time exists, if not them insert this value in DB
    		$data_time = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."misc WHERE type='".$type."' AND intitule = '".$setting."_time'");
    		if ( $data_time[0] == 0 ){
    			$db->query_insert(
	    			"misc",
	    			array(
	    			    'valeur' => 0,
	    			    'type' => $type,
	    			    'intitule' => $setting.'_time'
	    			)
    			);
    		}else {
    			$db->query_update(
	    			"misc",
	    			array(
	    			    'valeur' => 0
	    			),
	    			"type='".$type."' AND intitule = '".$setting."_time'"
    			);
    		}
    	}

    }
    //save in variable
    if ( $type == "admin" ) $_SESSION['settings'][$setting] = $val;
    else if ( $type == "settings" ) $settings[$setting] = $val;
}

//SAVE CHANGES
if (isset($_POST['save_button'])) {
    //Update last seen items
    if ( isset($_SESSION['settings']['max_latest_items']) && $_SESSION['settings']['max_latest_items'] != $_POST['max_last_items'] ){
        UpdateSettings('max_latest_items',$_POST['max_last_items']);
    }

    //Update favourites
    if ( isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] != $_POST['enable_favourites'] ){
        UpdateSettings('enable_favourites',$_POST['enable_favourites']);
    }

    //Update last shown items
    if ( isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] != $_POST['show_last_items'] ){
        UpdateSettings('show_last_items',$_POST['show_last_items']);
    }

    //Update personal feature
    if ( isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] != $_POST['enable_pf_feature'] ){
        UpdateSettings('enable_pf_feature',$_POST['enable_pf_feature']);
    }

    //Update loggin connections setting
    if ( isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] != $_POST['log_connections'] ){
        UpdateSettings('log_connections',$_POST['log_connections']);
    }

	//Update log_accessed setting
	if ( (isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] != $_POST['log_accessed']) || !isset($_SESSION['settings']['log_accessed']) ){
		UpdateSettings('log_accessed',$_POST['log_accessed']);
	}

    //Update date format setting
    if ( isset($_SESSION['settings']['date_format']) && $_SESSION['settings']['date_format'] != $_POST['date_format'] ){
        UpdateSettings('date_format',$_POST['date_format']);
    }

    //Update time format setting
    if ( isset($_SESSION['settings']['time_format']) && $_SESSION['settings']['time_format'] != $_POST['time_format'] ){
        UpdateSettings('time_format',$_POST['time_format']);
    }

    //Update default language setting
    if ( @$_SESSION['settings']['default_language'] != $_POST['default_language'] ){
        UpdateSettings('default_language',$_POST['default_language']);
    }

    //Update duplicate folder setting
    if ( isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] != $_POST['duplicate_folder'] ){
        UpdateSettings('duplicate_folder',$_POST['duplicate_folder']);
    }

    //Update duplicate item setting
    if ( isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] != $_POST['duplicate_item'] ){
        UpdateSettings('duplicate_item',$_POST['duplicate_item']);
    }

    //Update number_of_used_pw setting
    if ( isset($_SESSION['settings']['number_of_used_pw']) && $_SESSION['settings']['number_of_used_pw'] != $_POST['number_of_used_pw'] ){
        UpdateSettings('number_of_used_pw',$_POST['number_of_used_pw']);
    }

    //Update duplicate Manager edit
    if ( isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] != $_POST['manager_edit'] ){
        UpdateSettings('manager_edit',$_POST['manager_edit']);
    }

    //Update cpassman_dir
    if ( isset($_SESSION['settings']['cpassman_dir']) && $_SESSION['settings']['cpassman_dir'] != $_POST['cpassman_dir'] ){
        UpdateSettings('cpassman_dir',$_POST['cpassman_dir']);
    }

    //Update cpassman_url
    if ( isset($_SESSION['settings']['cpassman_url']) && $_SESSION['settings']['cpassman_url'] != $_POST['cpassman_url'] ){
        UpdateSettings('cpassman_url',$_POST['cpassman_url']);
    }

	//Update path_to_upload_folder
	if ( (isset($_SESSION['settings']['path_to_upload_folder']) && $_SESSION['settings']['path_to_upload_folder'] != $_POST['path_to_upload_folder']) || (!isset($_SESSION['settings']['path_to_upload_folder'])) ){
		UpdateSettings('path_to_upload_folder',$_POST['path_to_upload_folder']);
	}

	//Update url_to_upload_folder
	if ( (isset($_SESSION['settings']['url_to_upload_folder']) && $_SESSION['settings']['url_to_upload_folder'] != $_POST['url_to_upload_folder']) || (!isset($_SESSION['settings']['url_to_upload_folder'])) ){
		UpdateSettings('url_to_upload_folder',$_POST['url_to_upload_folder']);
	}

    //Update pw_life_duration
    if ( isset($_SESSION['settings']['pw_life_duration']) && $_SESSION['settings']['pw_life_duration'] != $_POST['pw_life_duration'] ){
        UpdateSettings('pw_life_duration',$_POST['pw_life_duration']);
    }

    //Update favicon
    if ( isset($_SESSION['settings']['favicon']) && $_SESSION['settings']['favicon'] != $_POST['favicon'] ){
        UpdateSettings('favicon',$_POST['favicon']);
    }

    //Update activate_expiration setting
    if ( isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] != $_POST['activate_expiration'] ){
        UpdateSettings('activate_expiration',$_POST['activate_expiration']);
    }

    //Update maintenance mode
    if ( @$_SESSION['settings']['maintenance_mode'] != $_POST['maintenance_mode'] ){
        UpdateSettings('maintenance_mode',$_POST['maintenance_mode']);
    }
/*
    //Update richtext
    if ( @$_SESSION['settings']['richtext'] != $_POST['richtext'] ){
        UpdateSettings('richtext',$_POST['richtext']);
    }
*/
    //Update send_stats
    if ( @$_SESSION['settings']['send_stats'] != $_POST['send_stats'] ){
        UpdateSettings('send_stats',$_POST['send_stats']);
    }

	//Update allow_print
	if ( @$_SESSION['settings']['allow_print'] != $_POST['allow_print'] ){
		UpdateSettings('allow_print',$_POST['allow_print']);
	}

  //Update show_description
  if ( @$_SESSION['settings']['show_description'] != $_POST['show_description'] ){
      UpdateSettings('show_description',$_POST['show_description']);
  }

	//Update LDAP mode
	if ( isset($_POST['ldap_mode']) && $_SESSION['settings']['ldap_mode'] != $_POST['ldap_mode'] ){
		UpdateSettings('ldap_mode',$_POST['ldap_mode']);
	}

	//Update LDAP ldap_suffix
	if ( @$_SESSION['settings']['ldap_suffix'] != $_POST['ldap_suffix'] ){
		UpdateSettings('ldap_suffix',$_POST['ldap_suffix']);
	}

	//Update LDAP ldap_domain_dn
	if ( @$_SESSION['settings']['ldap_domain_dn'] != $_POST['ldap_domain_dn'] ){
		UpdateSettings('ldap_domain_dn',$_POST['ldap_domain_dn']);
	}

	//Update LDAP ldap_domain_controler
	if ( @$_SESSION['settings']['ldap_domain_controler'] != $_POST['ldap_domain_controler'] ){
		UpdateSettings('ldap_domain_controler',$_POST['ldap_domain_controler']);
	}

	//Update LDAP ssl
	if ( @$_SESSION['settings']['ldap_ssl'] != $_POST['ldap_ssl'] ){
		UpdateSettings('ldap_ssl',$_POST['ldap_ssl']);
	}

	//Update LDAP tls
	if ( @$_SESSION['settings']['ldap_tls'] != $_POST['ldap_tls'] ){
		UpdateSettings('ldap_tls',$_POST['ldap_tls']);
	}

	//Update anyone_can_modify
	if ( @$_SESSION['settings']['anyone_can_modify'] != $_POST['anyone_can_modify'] ){
		UpdateSettings('anyone_can_modify',$_POST['anyone_can_modify']);
	}

	//Update enable_kb
	if ( @$_SESSION['settings']['enable_kb'] != $_POST['enable_kb'] ){
		UpdateSettings('enable_kb',$_POST['enable_kb']);
	}

    //Update nb_bad_identification
    if ( @$_SESSION['settings']['nb_bad_authentication'] != $_POST['nb_bad_authentication'] ){
        UpdateSettings('nb_bad_authentication',$_POST['nb_bad_authentication']);
    }

	//Update restricted_to(if restricted_to is FALSE then restricted_to_roles needs to be FALSE
	if ( @$_SESSION['settings']['restricted_to'] != $_POST['restricted_to'] ){
		UpdateSettings('restricted_to',$_POST['restricted_to']);
		if ( $_POST['restricted_to'] == 0 ){
			UpdateSettings('restricted_to_roles', 0);
		}
	}

	//Update restricted_to_roles
	if ( @$_SESSION['settings']['restricted_to_roles'] != $_POST['restricted_to_roles'] && (@$_SESSION['settings']['restricted_to'] == 1 OR $_POST['restricted_to'] == 1) ){
		UpdateSettings('restricted_to_roles',$_POST['restricted_to_roles']);
	}

	//Update copy_to_clipboard_small_icons
	if ( @$_SESSION['settings']['copy_to_clipboard_small_icons'] != $_POST['copy_to_clipboard_small_icons'] ){
		UpdateSettings('copy_to_clipboard_small_icons',$_POST['copy_to_clipboard_small_icons']);
	}

	//Update timezone_selection
	if ( @$_SESSION['settings']['timezone'] != $_POST['timezone'] ){
		UpdateSettings('timezone',$_POST['timezone']);
	}

	//Update enable_user_can_create_folders
	if ( @$_SESSION['settings']['enable_user_can_create_folders'] != $_POST['enable_user_can_create_folders'] ){
		UpdateSettings('enable_user_can_create_folders',$_POST['enable_user_can_create_folders']);
	}

	//Update enable_send_email_on_user_login
	if ( @$_SESSION['settings']['enable_send_email_on_user_login'] != $_POST['enable_send_email_on_user_login'] ){
		UpdateSettings('enable_send_email_on_user_login',$_POST['enable_send_email_on_user_login']);
	}

	//Update enable_email_notification_on_item_shown
	if ( @$_SESSION['settings']['enable_email_notification_on_item_shown'] != $_POST['enable_email_notification_on_item_shown'] ){
		UpdateSettings('enable_email_notification_on_item_shown',$_POST['enable_email_notification_on_item_shown']);
	}

	//Update custom_logo
	if ( @$_SESSION['settings']['custom_logo'] != $_POST['custom_logo'] ){
		UpdateSettings('custom_logo',$_POST['custom_logo']);
	}

	//Update custom_login_text
	if ( @$_SESSION['settings']['custom_login_text'] != $_POST['custom_login_text'] ){
		UpdateSettings('custom_login_text',htmlentities($_POST['custom_login_text'], ENT_QUOTES, "UTF-8"));
	}

	//Update nb of items to get in one query iterration
	if ( @$_SESSION['settings']['nb_items_by_query'] != $_POST['nb_items_by_query'] ){
		UpdateSettings('nb_items_by_query',$_POST['nb_items_by_query']);
	}

	//Update enable_delete_after_consultation
	if ( @$_SESSION['settings']['enable_delete_after_consultation'] != $_POST['enable_delete_after_consultation'] ){
		UpdateSettings('enable_delete_after_consultation',$_POST['enable_delete_after_consultation']);
	}

	//store backups settings
	if(isset($_POST['bck_script_filename'])) UpdateSettings('bck_script_filename', $_POST['bck_script_filename'], 'settings');
	if(isset($_POST['bck_script_path'])) UpdateSettings('bck_script_path', $_POST['bck_script_path'], 'settings');
	if(isset($_POST['bck_script_key'])) UpdateSettings('bck_script_key', $_POST['bck_script_key'], 'settings');
}

echo '
<div style="margin-top:10px;">
    <form name="form_settings" method="post" action="">';
        // Main div for TABS
        echo '
        <div style="width:900px;margin:auto; line-height:20px; padding:10px;" id="tabs">';
            // Tabs menu
            echo '
            <ul>
                <li><a href="#tabs-1">'.$txt['admin_settings_title'].'</a></li>
                <li><a href="#tabs-3">'.$txt['admin_misc_title'].'</a></li>
                <li><a href="#tabs-2">'.$txt['admin_actions_title'].'</a></li>
				<li><a href="#tabs-4">'.$txt['admin_ldap_menu'].'</a></li>
				<li><a href="#tabs-5">'.$txt['admin_backups'].'</a></li>
            </ul>';
            // --------------------------------------------------------------------------------
            // TAB N�1
            echo '
            <div id="tabs-1">
				<table border="0">';
                //cpassman_dir
                echo '
                <tr style="margin-bottom:3px">
                    <td>
                    	<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    	<label for="cpassman_dir">'.$txt['admin_misc_cpassman_dir'].'</label>
					</td>
					<td>
                    	<input type="text" size="80" id="cpassman_dir" name="cpassman_dir" value="', isset($_SESSION['settings']['cpassman_dir']) ? $_SESSION['settings']['cpassman_dir'] : '', '" class="text ui-widget-content ui-corner-all" />
					<td>
                </tr>';

                //cpassman_url
				echo '
				<tr style="margin-bottom:3px">
				    <td>
				    	<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    	<label for="cpassman_url">'.$txt['admin_misc_cpassman_url'].'</label>
					</td>
					<td>
                    	<input type="text" size="80" id="cpassman_url" name="cpassman_url" value="', isset($_SESSION['settings']['cpassman_url']) ? $_SESSION['settings']['cpassman_url'] : '', '" class="text ui-widget-content ui-corner-all" />
                	<td>
                </tr>';

				//path_to_upload_folder
				echo '
				<tr style="margin-bottom:3px">
				    <td>
				    	<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				    	<label for="path_to_upload_folder">'.$txt['admin_path_to_upload_folder'].'</label>
					</td>
					<td>
				    	<input type="text" size="80" id="path_to_upload_folder" name="path_to_upload_folder" value="', isset($_SESSION['settings']['path_to_upload_folder']) ? $_SESSION['settings']['path_to_upload_folder'] : $_SESSION['settings']['cpassman_dir'].'/upload', '" class="text ui-widget-content ui-corner-all" />
					<td>
				</tr>';

				//url_to_upload_folder
				echo '
				<tr style="margin-bottom:3px">
				    <td>
				    	<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				    	<label for="url_to_upload_folder">'.$txt['admin_url_to_upload_folder'].'</label>
					</td>
					<td>
				    	<input type="text" size="80" id="url_to_upload_folder" name="url_to_upload_folder" value="', isset($_SESSION['settings']['url_to_upload_folder']) ? $_SESSION['settings']['url_to_upload_folder'] : $_SESSION['settings']['cpassman_url'].'/upload', '" class="text ui-widget-content ui-corner-all" />
					<td>
				</tr>';

                //Favicon
                echo '
                <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label for="favicon">'.$txt['admin_misc_favicon'].'</label>
					</td>
					<td>
                    	<input type="text" size="80" id="favicon" name="favicon" value="', isset($_SESSION['settings']['favicon']) ? $_SESSION['settings']['favicon'] : '', '" class="text ui-widget-content ui-corner-all" />
					<td>
                </tr>';

				//custom_logo
				echo '
				<tr style="margin-bottom:3px">
				    <td>
				    	<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				    	<label for="cpassman_dir">'.$txt['admin_misc_custom_logo'].'</label>
					</td>
					<td>
				    	<input type="text" size="80" id="custom_logo" name="custom_logo" value="', isset($_SESSION['settings']['custom_logo']) ? $_SESSION['settings']['custom_logo'] : '', '" class="text ui-widget-content ui-corner-all" />
					<td>
				</tr>';

			//custom_login_text
			echo '
			<tr style="margin-bottom:3px">
			    <td>
			    	<span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
			    	<label for="cpassman_dir">'.$txt['admin_misc_custom_login_text'].'</label>
				</td>
				<td>
			    	<input type="text" size="80" id="custom_login_text" name="custom_login_text" value="', isset($_SESSION['settings']['custom_login_text']) ? $_SESSION['settings']['custom_login_text'] : '', '" class="text ui-widget-content ui-corner-all" />
				<td>
			</tr>';

			echo '
            </table>';

            echo '
			<table>';

            //Maintenance mode
            echo '
            <tr style="margin-bottom:3px">
		    <td>
                  <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                  <label>'.
                      $txt['settings_maintenance_mode'].'
                      &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_maintenance_mode_tip'].'" />
                  </label>
			</td>
			<td>
				<div class="div_radio">
					<input type="radio" id="maintenance_mode_radio1" name="maintenance_mode" value="1"', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ? ' checked="checked"' : '', ' /><label for="maintenance_mode_radio1">'.$txt['yes'].'</label>
					<input type="radio" id="maintenance_mode_radio2" name="maintenance_mode" value="0"', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['maintenance_mode']) ? ' checked="checked"':''), ' /><label for="maintenance_mode_radio2">'.$txt['no'].'</label>
				</div>
              <td>
            </tr>';

            //TIMEZONE
            //get list of all timezones
			$zones = timezone_identifiers_list();
			echo '
			    <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label for="timezone">'.$txt['timezone_selection'].'</label>
					</td>
					<td>
						<select id="timezone" name="timezone" class="text ui-widget-content ui-corner-all">
							<option value="">-- '.$txt['select'].' --</option>';
							foreach ($zones as $zone){
								echo '
								<option value="'.$zone.'"', isset($_SESSION['settings']['timezone']) && $_SESSION['settings']['timezone'] == $zone ? ' selected="selected"' : '', '>'.$zone.'</option>';
							}
			echo '
						</select>
			    	<td>
			    </tr>';
/*

*/

                //DATE format
                echo '
                <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label for="date_format">'.$txt['date_format'].'</label>
					</td>
					<td>
						<select id="date_format" name="date_format" class="text ui-widget-content ui-corner-all">
							<option value="d/m/Y"', !isset($_SESSION['settings']['date_format']) || $_SESSION['settings']['date_format'] == "d/m/Y" ? ' selected="selected"':"",'>d/m/Y</option>
							<option value="m/d/Y"', $_SESSION['settings']['date_format'] == "m/d/Y" ? ' selected="selected"':"",'>m/d/Y</option>
							<option value="d-M-Y"', $_SESSION['settings']['date_format'] == "d-M-Y" ? ' selected="selected"':"",'>d-M-Y</option>
							<option value="d/m/y"', $_SESSION['settings']['date_format'] == "d/m/y" ? ' selected="selected"':"",'>d/m/y</option>
							<option value="m/d/y"', $_SESSION['settings']['date_format'] == "m/d/y" ? ' selected="selected"':"",'>m/d/y</option>
							<option value="d-M-y"', $_SESSION['settings']['date_format'] == "d-M-y" ? ' selected="selected"':"",'>d-M-y</option>
							<option value="d-m-y"', $_SESSION['settings']['date_format'] == "d-m-y" ? ' selected="selected"':"",'>d-m-y</option>
							<option value="Y-m-d"', $_SESSION['settings']['date_format'] == "Y-m-d" ? ' selected="selected"':"",'>Y-m-d</option>
						</select>
                    <td>
                </tr>';

                //TIME format
                echo '
                <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label for="time_format">'.$txt['time_format'].'</label>
					</td>
					<td>
						<select id="time_format" name="time_format" class="text ui-widget-content ui-corner-all">
							<option value="H:i:s"', !isset($_SESSION['settings']['time_format']) || $_SESSION['settings']['time_format'] == "H:i:s" ? ' selected="selected"':"",'>H:i:s</option>
							<option value="h:m:s a"', $_SESSION['settings']['time_format'] == "h:m:s a" ? ' selected="selected"':"",'>h:m:s a</option>
							<option value="g:i:s a"', $_SESSION['settings']['time_format'] == "g:i:s a" ? ' selected="selected"':"",'>g:i:s a</option>
							<option value="G:i:s"', $_SESSION['settings']['time_format'] == "G:i:s" ? ' selected="selected"':"",'>G:i:s</option>
						</select>
                    <td>
                </tr>';

            //LANGUAGES
            $zones = timezone_identifiers_list();
			echo '
			    <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label for="default_language">'.$txt['settings_default_language'].'</label>
					</td>
					<td>
						<select id="default_language" name="default_language" class="text ui-widget-content ui-corner-all">
							<option value="">-- '.$txt['select'].' --</option>';
							foreach ($languages_list as $lang){
								echo '
								<option value="'.$lang.'"', isset($_SESSION['settings']['default_language']) && $_SESSION['settings']['default_language'] == $lang ? ' selected="selected"' : '', '>'.$lang.'</option>';
							}
			echo '
						</select>
			    	<td>
			    </tr>';

                //Number of used pw
                echo '
                <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label for="number_of_used_pw">'.$txt['number_of_used_pw'].'</label>
					</td>
					<td>
                    	<input type="text" size="10" id="number_of_used_pw" name="number_of_used_pw" value="', isset($_SESSION['settings']['number_of_used_pw']) ? $_SESSION['settings']['number_of_used_pw'] : '5', '" class="text ui-widget-content ui-corner-all" />
                	<td>
                </tr>';

                //Number days before changing pw
                echo '
                <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label for="pw_life_duration">'.$txt['pw_life_duration'].'</label>
					</td>
					<td>
                    	<input type="text" size="10" id="pw_life_duration" name="pw_life_duration" value="', isset($_SESSION['settings']['pw_life_duration']) ? $_SESSION['settings']['pw_life_duration'] : '5', '" class="text ui-widget-content ui-corner-all" />
                	<td>
                </tr>';

                //Number of bad authentication tentations before disabling user
                echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="nb_bad_authentication">'.$txt['nb_false_login_attempts'].'</label>
                    </td>
                    <td>
                        <input type="text" size="10" id="nb_bad_authentication" name="nb_bad_authentication" value="', isset($_SESSION['settings']['nb_bad_authentication']) ? $_SESSION['settings']['nb_bad_authentication'] : '0', '" class="text ui-widget-content ui-corner-all" />
                    <td>
                </tr>';

				//Enable log connections
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['settings_log_connections'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="log_connections_radio1" name="log_connections" value="1"', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 ? ' checked="checked"' : '', ' /><label for="log_connections_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="log_connections_radio2" name="log_connections" value="0"', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['log_connections']) ? ' checked="checked"':''), ' /><label for="log_connections_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';

				//Enable log accessed
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['settings_log_accessed'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="log_accessed_radio1" name="log_accessed" value="1"', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1 ? ' checked="checked"' : '', ' /><label for="log_accessed_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="log_accessed_radio2" name="log_accessed" value="0"', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['log_accessed']) ? ' checked="checked"':''), ' /><label for="log_accessed_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';

            //enable PF
            echo '
            <tr><td>
                <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                <label>'.$txt['enable_personal_folder_feature'].'</label>
		    </td><td>
		    <div class="div_radio">
				<input type="radio" id="enable_pf_feature_radio1" name="enable_pf_feature" value="1"', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_pf_feature_radio1">'.$txt['yes'].'</label>
				<input type="radio" id="enable_pf_feature_radio2" name="enable_pf_feature" value="0"', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_pf_feature']) ? ' checked="checked"':''), ' /><label for="enable_pf_feature_radio2">'.$txt['no'].'</label>
			</div>
            </td</tr>';

    				//Enable KB
    				echo '
    				<tr><td>
    				    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
    				    <label>
    				        '.$txt['settings_kb'].'
    				        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_kb_tip'].'" /></span>
    				    </label>
    				    </td><td>
    				    <div class="div_radio">
    						<input type="radio" id="enable_kb_radio1" name="enable_kb" value="1"', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_kb_radio1">'.$txt['yes'].'</label>
    						<input type="radio" id="enable_kb_radio2" name="enable_kb" value="0"', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_kb']) ? ' checked="checked"':''), ' /><label for="enable_kb_radio2">'.$txt['no'].'</label>
    					</div>
    				</td></tr>';

                //Enable send_stats
                echo '
                <tr style="margin-bottom:3px">
				    <td>
	                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
	                    <label>'.
	                        $txt['settings_send_stats'].'
	                        &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_send_stats_tip'].'" />
	                    </label>
					</td>
					<td>
						<div class="div_radio">
							<input type="radio" id="send_stats_radio1" name="send_stats" value="1"', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] == 1 ? ' checked="checked"' : '', ' /><label for="send_stats_radio1">'.$txt['yes'].'</label>
							<input type="radio" id="send_stats_radio2" name="send_stats" value="0"', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['send_stats']) ? ' checked="checked"':''), ' /><label for="send_stats_radio2">'.$txt['no'].'</label>
						</div>
	                <td>
                </tr>
                </table>
            </div>';
            // --------------------------------------------------------------------------------

            // --------------------------------------------------------------------------------
            // TAB N�2
            echo '
            <div id="tabs-2">';

                //Update Personal folders for users
                echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_check_pf\')" style="cursor:pointer;">'.$txt['admin_action_check_pf'].'</a>
                    <span id="result_admin_action_check_pf" style="margin-left:10px;display:none;"><img src="includes/images/tick.png" alt="" /></span>
                </div>';

                //Clean DB with orphan items
                echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_db_clean_items\')" style="cursor:pointer;">'.$txt['admin_action_db_clean_items'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_db_clean_items_tip'].'" /></span>
                    <span id="result_admin_action_db_clean_items" style="margin-left:10px;"></span>
                </div>';

                //Optimize the DB
                echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_db_optimize\')" style="cursor:pointer;">'.$txt['admin_action_db_optimize'].'</a>
                    <span id="result_admin_action_db_optimize" style="margin-left:10px;"></span>
                </div>';

                //Purge old files
                echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_purge_old_files\')" style="cursor:pointer;">'.$txt['admin_action_purge_old_files'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_purge_old_files_tip'].'" /></span>
                    <span id="result_admin_action_purge_old_files" style="margin-left:10px;"></span>
                </div>';

				//Reload Cache Table
				echo '
				<div style="margin-bottom:3px">
				    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <a href="#" onclick="LaunchAdminActions(\'admin_action_reload_cache_table\')" style="cursor:pointer;">'.$txt['admin_action_reload_cache_table'].'</a>
				    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_reload_cache_table_tip'].'" /></span>
				    <span id="result_admin_action_reload_cache_table" style="margin-left:10px;"></span>
				</div>';

				//Change main SALT key
				echo '
				<div style="margin-bottom:3px">
				    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <a href="#" onclick="$(\'#div_change_salt_key\').show()" style="cursor:pointer;">'.$txt['admin_action_change_salt_key'].'</a>
				    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_change_salt_key_tip'].'" /></span>
				    <span id="div_change_salt_key" style="margin-left:10px;display:none;">
				    	<input type="text" id="new_salt_key" size="50" value="'.SALT.'" /><img src="includes/images/cross.png" id="change_salt_key_image">&nbsp;
				    	<img src="includes/images/asterisk.png" alt="" style="cursor:pointer;display:none;" onclick="LaunchAdminActions(\'admin_action_change_salt_key\')" id="change_salt_key_but" />
				    </span>
				</div>';

            echo '
            </div>';
            // --------------------------------------------------------------------------------

            // --------------------------------------------------------------------------------
            // TAB N�3
            echo '
            <div id="tabs-3">
            	<table widht="100%">';

                //Managers can edit & delete items they are allowed to see
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_manager_edit'].'</label>
					</td><td>
				    <div class="div_radio">
						<input type="radio" id="manager_edit_radio1" name="manager_edit" value="1"', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] == 1 ? ' checked="checked"' : '', ' /><label for="manager_edit_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="manager_edit_radio2" name="manager_edit" value="0"', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['manager_edit']) ? ' checked="checked"':''), ' /><label for="manager_edit_radio2">'.$txt['no'].'</label>
					</div>
                </td</tr>';

                //max items
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="max_last_items">'.$txt['max_last_items'].'</label>
					</td><td>
                    <input type="text" size="4" id="max_last_items" name="max_last_items" value="', isset($_SESSION['settings']['max_latest_items']) ? $_SESSION['settings']['max_latest_items'] : '', '" class="text ui-widget-content ui-corner-all" />
                <tr><td>';

                //Show last items
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['show_last_items'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="show_last_items_radio1" name="show_last_items" value="1"', isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] == 1 ? ' checked="checked"' : '', ' /><label for="show_last_items_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="show_last_items_radio2" name="show_last_items" value="0"', isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['show_last_items']) ? ' checked="checked"':''), ' /><label for="show_last_items_radio2">'.$txt['no'].'</label>
					</div>
                </td</tr>';

                //Duplicate folder
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['duplicate_folder'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="duplicate_folder_radio1" name="duplicate_folder" value="1"', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 1 ? ' checked="checked"' : '', ' /><label for="duplicate_folder_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="duplicate_folder_radio2" name="duplicate_folder" value="0"', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['duplicate_folder']) ? ' checked="checked"':''), ' /><label for="duplicate_folder_radio2">'.$txt['no'].'</label>
					</div>
                </td</tr>';

                //Duplicate item name
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['duplicate_item'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="duplicate_item_radio1" name="duplicate_item" value="1"', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? ' checked="checked"' : '', ' /><label for="duplicate_item_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="duplicate_item_radio2" name="duplicate_item" value="0"', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['duplicate_item']) ? ' checked="checked"':''), ' /><label for="duplicate_item_radio2">'.$txt['no'].'</label>
					</div>
                </td</tr>';

                //enable FAVOURITES
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['enable_favourites'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="enable_favourites_radio1" name="enable_favourites" value="1"', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_favourites_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="enable_favourites_radio2" name="enable_favourites" value="0"', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_favourites']) ? ' checked="checked"':''), ' /><label for="enable_favourites_radio2">'.$txt['no'].'</label>
					</div>
                </td</tr>';

                //Enable activate_expiration
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['admin_setting_activate_expiration'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_setting_activate_expiration_tip'].'" /></span>
                    </label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="activate_expiration_radio1" name="activate_expiration" value="1"', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] == 1 ? ' checked="checked"' : '', ' /><label for="activate_expiration_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="activate_expiration_radio2" name="activate_expiration" value="0"', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['activate_expiration']) ? ' checked="checked"':''), ' /><label for="activate_expiration_radio2">'.$txt['no'].'</label>
					</div>
                </td</tr>';

                //Enable enable_delete_after_consultation
                echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['admin_setting_enable_delete_after_consultation'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_setting_enable_delete_after_consultation_tip'].'" /></span>
                    </label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="enable_delete_after_consultation_radio1" name="enable_delete_after_consultation" value="1"', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_delete_after_consultation_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="enable_delete_after_consultation_radio2" name="enable_delete_after_consultation" value="0"', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_delete_after_consultation']) ? ' checked="checked"':''), ' /><label for="enable_delete_after_consultation_radio2">'.$txt['no'].'</label>
					</div>
                </td</tr>';

				//Enable Printing
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>
				        '.$txt['settings_printing'].'
				        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_printing_tip'].'" /></span>
				    </label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="allow_print_radio1" name="allow_print" value="1"', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 ? ' checked="checked"' : '', ' /><label for="allow_print_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="allow_print_radio2" name="allow_print" value="0"', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['allow_print']) ? ' checked="checked"':''), ' /><label for="allow_print_radio2">'.$txt['no'].'</label>
					</div>
				</td></tr>';

				//Enable Item modification by anyone
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>
				        '.$txt['settings_anyone_can_modify'].'
				        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_anyone_can_modify_tip'].'" /></span>
				    </label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="anyone_can_modify_radio1" name="anyone_can_modify" value="1"', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? ' checked="checked"' : '', ' /><label for="anyone_can_modify_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="anyone_can_modify_radio2" name="anyone_can_modify" value="0"', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['anyone_can_modify']) ? ' checked="checked"':''), ' /><label for="anyone_can_modify_radio2">'.$txt['no'].'</label>
					</div>
				</td></tr>';


				//enable restricted_to option
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['settings_restricted_to'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="restricted_to_radio1" name="restricted_to" value="1"', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? ' checked="checked"' : '', ' /><label for="restricted_to_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="restricted_to_radio2" name="restricted_to" value="0"', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['restricted_to']) ? ' checked="checked"':''), ' /><label for="restricted_to_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';


				//enable restricted_to_roles
				echo '
				<tr id="tr_option_restricted_to_roles" style="display:', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? 'inline':'none', ';"><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['restricted_to_roles'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="restricted_to_roles_radio1" name="restricted_to_roles" value="1"', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? ' checked="checked"' : '', ' /><label for="restricted_to_roles_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="restricted_to_roles_radio2" name="restricted_to_roles" value="0"', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['restricted_to_roles']) ? ' checked="checked"':''), ' /><label for="restricted_to_roles_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';


				//enable show copy to clipboard small icons
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>
				    	'.$txt['copy_to_clipboard_small_icons'].'
						<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['copy_to_clipboard_small_icons_tip'].'" /></span>
					</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="copy_to_clipboard_small_icons_radio1" name="copy_to_clipboard_small_icons" value="1"', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? ' checked="checked"' : '', ' /><label for="copy_to_clipboard_small_icons_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="copy_to_clipboard_small_icons_radio2" name="copy_to_clipboard_small_icons" value="0"', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['copy_to_clipboard_small_icons']) ? ' checked="checked"':''), ' /><label for="copy_to_clipboard_small_icons_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';

				//Enable Show description in items list
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>
				        '.$txt['settings_show_description'].'
				    </label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="show_description_radio1" name="show_description" value="1"', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1 ? ' checked="checked"' : '', ' /><label for="show_description_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="show_description_radio2" name="show_description" value="0"', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['show_description']) ? ' checked="checked"':''), ' /><label for="show_description_radio2">'.$txt['no'].'</label>
					</div>
				</td></tr>';


				//enable USER can create folders
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['enable_user_can_create_folders'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="enable_user_can_create_folders_radio1" name="enable_user_can_create_folders" value="1"', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_user_can_create_folders_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="enable_user_can_create_folders_radio2" name="enable_user_can_create_folders" value="0"', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_user_can_create_folders']) ? ' checked="checked"':''), ' /><label for="enable_user_can_create_folders_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';


				//enable sending email on USER login
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['enable_send_email_on_user_login'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="enable_send_email_on_user_login_radio1" name="enable_send_email_on_user_login" value="1"', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_send_email_on_user_login_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="enable_send_email_on_user_login_radio2" name="enable_send_email_on_user_login" value="0"', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_send_email_on_user_login']) ? ' checked="checked"':''), ' /><label for="enable_send_email_on_user_login_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';

				//enable email notification on item shown
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['enable_email_notification_on_item_shown'].'</label>
				    </td><td>
				    <div class="div_radio">
						<input type="radio" id="enable_email_notification_on_item_shown_radio1" name="enable_email_notification_on_item_shown" value="1"', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_email_notification_on_item_shown_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="enable_email_notification_on_item_shown_radio2" name="enable_email_notification_on_item_shown" value="0"', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_email_notification_on_item_shown']) ? ' checked="checked"':''), ' /><label for="enable_email_notification_on_item_shown_radio2">'.$txt['no'].'</label>
					</div>
				</td</tr>';

				//nb of items to display by ajax query
				echo '
				<tr><td>
				    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
				    <label>'.$txt['nb_items_by_query'].'</label>
					<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['nb_items_by_query_tip'].'" /></span>
					</td><td>
				    <input type="text" size="4" id="nb_items_by_query" name="nb_items_by_query" value="', isset($_SESSION['settings']['nb_items_by_query']) ? $_SESSION['settings']['nb_items_by_query'] : '', '" class="text ui-widget-content ui-corner-all" />
				<tr><td>';

            echo '
			</table>
            </div>';
			// --------------------------------------------------------------------------------

			// --------------------------------------------------------------------------------
			// TAB N�4
			echo '
			<div id="tabs-4">';

			//Check if LDAP extension is loaded
			if (!extension_loaded('ldap')) {
				echo '
				<div style="margin-bottom:3px;">
					<div class="ui-widget-content ui-corner-all" style="padding:10px;">
						<img src="includes/images/error.png" alt="">&nbsp;&nbsp;'.$txt['ldap_extension_not_loaded'].'
					</div>
				</div>';
			}
			else
			{
				//Enable LDAP mode
				echo '
				<div style="margin-bottom:3px;">
				    <label for="ldap_mode">'.
						$txt['settings_ldap_mode'].'
						&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_ldap_mode_tip'].'" />
	                </label>
				    <span class="div_radio">
						<input type="radio" id="ldap_mode_radio1" name="ldap_mode" value="1"', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? ' checked="checked"' : '', ' onclick="javascript:$(\'#div_ldap_configuration\').show();" /><label for="ldap_mode_radio1">'.$txt['yes'].'</label>
						<input type="radio" id="ldap_mode_radio2" name="ldap_mode" value="0"', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['ldap_mode']) ? ' checked="checked"':''), ' onclick="javascript:$(\'#div_ldap_configuration\').hide();" /><label for="ldap_mode_radio2">'.$txt['no'].'</label>
					</span>
	            </div>';
			}

			// AD inputs
			echo '
			<div id="div_ldap_configuration" ', (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1) ? '':' style="display:none;"' ,'>
				<div style="font-weight:bold;font-size:14px;margin:15px 0px 8px 0px;">'.$txt['admin_ldap_configuration'].'</div>
				<table>';
				// Domain
				echo '
					<tr>
						<td><label for="ldap_suffix">'.$txt['settings_ldap_domain'].'</label></td>
						<td><input type="text" size="50" id="ldap_suffix" name="ldap_suffix" class="text ui-widget-content ui-corner-all" title="@dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_suffix']) ? $_SESSION['settings']['ldap_suffix'] : '', '" /></td>
					</tr>';
				// Domain DN
				echo '
					<tr>
						<td><label for="ldap_domain_dn">'.$txt['settings_ldap_domain_dn'].'</label></td>
						<td><input type="text" size="50" id="ldap_domain_dn" name="ldap_domain_dn" class="text ui-widget-content ui-corner-all" title="dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_domain_dn']) ? $_SESSION['settings']['ldap_domain_dn'] : '', '" /></td>
					</tr>';
				// Domain controler
				echo '
					<tr>
						<td><label for="ldap_domain_controler">'.$txt['settings_ldap_domain_controler'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_ldap_domain_controler_tip'].'" /></label></td>
						<td><input type="text" size="50" id="ldap_domain_controler" name="ldap_domain_controler" class="text ui-widget-content ui-corner-all" title="dc01.mydomain.local,dc02.mydomain.local" value="', isset($_SESSION['settings']['ldap_domain_controler']) ? $_SESSION['settings']['ldap_domain_controler'] : '', '" /></td>
					</tr>';
				// AD SSL
				echo '
					<tr>
						<td><label>'.$txt['settings_ldap_ssl'].'</label></td>
						<td>
						    <div class="div_radio">
								<input type="radio" id="ldap_ssl_radio1" name="ldap_ssl" value="1"', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] == 1 ? ' checked="checked"' : '', ' /><label for="ldap_ssl_radio1">'.$txt['yes'].'</label>
								<input type="radio" id="ldap_ssl_radio2" name="ldap_ssl" value="0"', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['ldap_ssl']) ? ' checked="checked"':''), ' /><label for="ldap_ssl_radio2">'.$txt['no'].'</label>
							</div>
			            </td>
					</tr>';
				// AD TLS
				echo '
					<tr>
						<td><label>'.$txt['settings_ldap_tls'].'</label></td>
						<td>
						    <div class="div_radio">
								<input type="radio" id="ldap_tls_radio1" name="ldap_tls" value="1"', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] == 1 ? ' checked="checked"' : '', ' /><label for="ldap_tls_radio1">'.$txt['yes'].'</label>
								<input type="radio" id="ldap_tls_radio2" name="ldap_tls" value="0"', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_tls'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['ldap_tls']) ? ' checked="checked"':''), ' /><label for="ldap_tls_radio2">'.$txt['no'].'</label>
							</div>
			            </td>
					</tr>';
				echo '
	            </table>
	        </div>';

			echo '
			</div>';
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------------
// TAB N�5
echo '
            <div id="tabs-5">
            	<div class="" style="padding: 0 .7em;">
            		<span class="ui-icon ui-icon-transferthick-e-w" style="float: left; margin-right: .3em;">&nbsp;</span>
            		<b>'.$txt['admin_one_shot_backup'].'</b>
				</div>
				<div style="margin:0 0 5px 20px;">
					<table>';

					//Backup the DB
					echo '
					<tr style="margin-bottom:3px">
						<td>
					    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
					    '.$txt['admin_action_db_backup'].'
					    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_tip'].'" /></span>
						</td>
						<td>
					    <span id="result_admin_action_db_backup" style="margin-left:10px;"></span>
					    <span id="result_admin_action_db_backup_get_key" style="margin-left:10px;">
					        &nbsp;'.$txt['encrypt_key'].'<input type="text" size="20" id="result_admin_action_db_backup_key" />
					        <img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_key_tip'].'" />
					        <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_action_db_backup\')" style="cursor:pointer;" />
					    </span>
					    </td>
					</tr>';

					//Restore the DB
					echo '
					<tr style="margin-bottom:3px">
						<td>
					    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
					    '.$txt['admin_action_db_restore'].'
					    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_db_restore_tip'].'" /></span>
						</td>
						<td>
					    <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
					    <span id="result_admin_action_db_restore_get_file" style="margin-left:10px;"><input id="fileInput_restore_sql" name="fileInput_restore_sql" type="file" /></span>
						</td>
					</tr>';

					echo '
					</table>
				</div>';

				echo '
				<div class="" style="0padding: 0 .7em;">
            		<span class="ui-icon ui-icon-transferthick-e-w" style="float: left; margin-right: .3em;">&nbsp;</span>
            		<b>'.$txt['admin_script_backups'].'</b>&nbsp;
					<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backups_tip'].'</h2>" /></span>
				</div>
				<div style="margin:0 0 5px 20px;">
					<table>';

					//Backups script path
					echo '
					<tr style="margin-bottom:3px">
						<td>
						<span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
						'.$txt['admin_script_backup_path'].'
						<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backup_path_tip'].'</h2>" /></span>
						</td>
						<td>
						<span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
						<input id="bck_script_path" name="bck_script_path" type="text" size="80px" value="', isset($settings['bck_script_path']) ? $settings['bck_script_path'] : $_SESSION['settings']['cpassman_dir'].'/backups', '" />
						</td>
					</tr>';

					//Backups script name
					echo '
					<tr style="margin-bottom:3px">
						<td>
						<span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
						'.$txt['admin_script_backup_filename'].'
						<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backup_filename_tip'].'</h2>" /></span>
						</td>
						<td>
						<span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
						<input id="bck_script_filename" name="bck_script_filename" type="text" size="50px" value="', isset($settings['bck_script_filename']) ? $settings['bck_script_filename'] : 'bck_cpassman', '" />
						</td>
					</tr>';

					//Backups script encryption
					echo '
					<tr style="margin-bottom:3px">
						<td>
						<span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
						'.$txt['admin_script_backup_encryption'].'
						<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backup_encryption_tip'].'</h2>" /></span>
						</td>
						<td>
						<span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
						<input id="bck_script_key" name="bck_script_key" type="text" size="50px" value="', isset($settings['bck_script_key']) ? $settings['bck_script_key'] : '', '" />
						</td>
					</tr>';

					//Decrypt SQL file
					echo '
					<tr style="margin-bottom:3px">
						<td>
						<span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
						'.$txt['admin_script_backup_decrypt'].'
						<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backup_decrypt_tip'].'</h2>" /></span>
						</td>
						<td>
						<span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
						<input id="bck_script_decrypt_file" name="bck_script_decrypt_file" type="text" size="50px" value="" />
						<img src="includes/images/asterisk.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_action_backup_decrypt\')" style="cursor:pointer;" />
						</td>
					</tr>';

			echo '
					</table>
				</div>
			</div>';
// --------------------------------------------------------------------------------


			//Save button
			echo '
			<div style="margin:auto;">
				<input type="submit" id="save_button" name="save_button" value="'.$txt['save_button'].'" />
			</div>';

        echo '
        </div>';

        echo '
    </form>
</div>';

echo '
<input id="restore_bck_fileObj" name="restore_bck_fileObj" type="hidden" value="" />
<div id="restore_bck_encryption_key_dialog" style="display:none;">
    <input id="restore_bck_encryption_key" name="restore_bck_encryption_key" type="text" value="" />
</div>';
?>