<?php
/**
 *
 * @file          admin.settings.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

/*
* FUNCTION permitting to store into DB the settings changes
*/
function updateSettings ($setting, $val, $type = '')
{
    global $server, $user, $pass, $database, $pre;

    if (empty($type)) {
        $type = 'admin';
    }

    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    // Connect to database
    $db = new SplClassLoader('Database\Core', '../includes/libraries');
    $db->register();
    $db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
    $db->connect();

    // Check if setting is already in DB. If NO then insert, if YES then update.
    //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."misc WHERE type='".$type."' AND intitule = '".$setting."'");
    $data = $db->queryCount(
        "misc",
        array(
            "type" => $type,
            "intitule" => $setting
        )
    );
    if ($data[0] == 0) {
        $db->queryInsert(
            "misc",
            array(
                'valeur' => $val,
                'type' => $type,
                'intitule' => $setting
               )
        );
        // in case of stats enabled, add the actual time
        if ($setting == 'send_stats') {
            $db->queryInsert(
                "misc",
                array(
                    'valeur' => time(),
                    'type' => $type,
                    'intitule' => $setting.'_time'
                   )
            );
        }
    } else {
        $db->queryUpdate(
            "misc",
            array(
                'valeur' => $val
               ),
            "type='".$type."' AND intitule = '".$setting."'"
        );
        // in case of stats enabled, update the actual time
        if ($setting == 'send_stats') {
            // Check if previous time exists, if not them insert this value in DB
            //$data_time = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."misc WHERE type='".$type."' AND intitule = '".$setting."_time'");
            $data_time = $db->queryCount(
                "misc",
                array(
                    "type" => $type,
                    "intitule" => $setting."_time"
                )
            );
            if ($data_time[0] == 0) {
                $db->queryInsert(
                    "misc",
                    array(
                        'valeur' => 0,
                        'type' => $type,
                        'intitule' => $setting.'_time'
                       )
                );
            } else {
                $db->queryUpdate(
                    "misc",
                    array(
                        'valeur' => 0
                       ),
                    "type='".$type."' AND intitule = '".$setting."_time'"
                );
            }
        }
    }
    $_SESSION['settings'][$setting] = $val;
}
// SAVE CHANGES
if (isset($_POST['save_button'])) {
    // Update last seen items
    if (isset($_SESSION['settings']['max_latest_items']) && $_SESSION['settings']['max_latest_items'] != $_POST['max_last_items']) {
        updateSettings('max_latest_items', $_POST['max_last_items']);
    }
    // Update delay_item_edition
    if (isset($_SESSION['settings']['delay_item_edition']) && $_SESSION['settings']['delay_item_edition'] != $_POST['delay_item_edition']) {
        updateSettings('delay_item_edition', $_POST['delay_item_edition']);
    }
    // Update favourites
    if (isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] != $_POST['enable_favourites']) {
        updateSettings('enable_favourites', $_POST['enable_favourites']);
    }
    // Update last shown items
    if (isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] != $_POST['show_last_items']) {
        updateSettings('show_last_items', $_POST['show_last_items']);
    }
    // Update personal feature
    if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] != $_POST['enable_pf_feature']) {
        updateSettings('enable_pf_feature', $_POST['enable_pf_feature']);
    }
    // Update loggin connections setting
    if (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] != $_POST['log_connections']) {
        updateSettings('log_connections', $_POST['log_connections']);
    }
    // Update log_accessed setting
    if ((isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] != $_POST['log_accessed']) || !isset($_SESSION['settings']['log_accessed'])) {
        updateSettings('log_accessed', $_POST['log_accessed']);
    }
    // Update date format setting
    if (isset($_SESSION['settings']['date_format']) && $_SESSION['settings']['date_format'] != $_POST['date_format']) {
        updateSettings('date_format', $_POST['date_format']);
    }
    // Update time format setting
    if (isset($_SESSION['settings']['time_format']) && $_SESSION['settings']['time_format'] != $_POST['time_format']) {
        updateSettings('time_format', $_POST['time_format']);
    }
    // Update default language setting
    if (@$_SESSION['settings']['default_language'] != $_POST['default_language']) {
        updateSettings('default_language', $_POST['default_language']);
    }
    // Update duplicate folder setting
    if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] != $_POST['duplicate_folder']) {
        updateSettings('duplicate_folder', $_POST['duplicate_folder']);
    }
    // Update duplicate item setting
    if (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] != $_POST['duplicate_item']) {
        updateSettings('duplicate_item', $_POST['duplicate_item']);
    }
    // Update duplicate item setting
    if (isset($_SESSION['settings']['pwd_maximum_length']) && $_SESSION['settings']['pwd_maximum_length'] != $_POST['pwd_maximum_length']) {
        updateSettings('pwd_maximum_length', $_POST['pwd_maximum_length']);
    }
    // Update ga_website_name
    if (isset($_SESSION['settings']['ga_website_name']) && $_SESSION['settings']['ga_website_name'] != $_POST['ga_website_name']) {
        updateSettings('ga_website_name', $_POST['ga_website_name']);
    }
    // Update number_of_used_pw setting
    if (isset($_SESSION['settings']['number_of_used_pw']) && $_SESSION['settings']['number_of_used_pw'] != $_POST['number_of_used_pw']) {
        updateSettings('number_of_used_pw', $_POST['number_of_used_pw']);
    }
    // Update duplicate Manager edit
    if (isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] != $_POST['manager_edit']) {
        updateSettings('manager_edit', $_POST['manager_edit']);
    }
    // Update cpassman_dir
    if (isset($_SESSION['settings']['cpassman_dir']) && $_SESSION['settings']['cpassman_dir'] != $_POST['cpassman_dir']) {
        updateSettings('cpassman_dir', $_POST['cpassman_dir']);
    }
    // Update cpassman_url
    if (isset($_SESSION['settings']['cpassman_url']) && $_SESSION['settings']['cpassman_url'] != $_POST['cpassman_url']) {
        updateSettings('cpassman_url', $_POST['cpassman_url']);
    }
    // Update path_to_upload_folder
    if ((isset($_SESSION['settings']['path_to_upload_folder']) && $_SESSION['settings']['path_to_upload_folder'] != $_POST['path_to_upload_folder']) || (!isset($_SESSION['settings']['path_to_upload_folder']))) {
        updateSettings('path_to_upload_folder', $_POST['path_to_upload_folder']);
    }
    // Update url_to_upload_folder
    if ((isset($_SESSION['settings']['url_to_upload_folder']) && $_SESSION['settings']['url_to_upload_folder'] != $_POST['url_to_upload_folder']) || (!isset($_SESSION['settings']['url_to_upload_folder']))) {
        updateSettings('url_to_upload_folder', $_POST['url_to_upload_folder']);
    }
    // Update path_to_files_folder
    if ((isset($_SESSION['settings']['path_to_files_folder']) && $_SESSION['settings']['path_to_files_folder'] != $_POST['path_to_files_folder']) || (!isset($_SESSION['settings']['path_to_files_folder']))) {
        updateSettings('path_to_files_folder', $_POST['path_to_files_folder']);
    }
    // Update url_to_upload_folder
    if ((isset($_SESSION['settings']['url_to_files_folder']) && $_SESSION['settings']['url_to_files_folder'] != $_POST['url_to_files_folder']) || (!isset($_SESSION['settings']['url_to_files_folder']))) {
        updateSettings('url_to_files_folder', $_POST['url_to_files_folder']);
    }
    // Update pw_life_duration
    if (isset($_SESSION['settings']['pw_life_duration']) && $_SESSION['settings']['pw_life_duration'] != $_POST['pw_life_duration']) {
        updateSettings('pw_life_duration', $_POST['pw_life_duration']);
    }
    // Update favicon
    if (isset($_SESSION['settings']['favicon']) && $_SESSION['settings']['favicon'] != $_POST['favicon']) {
        updateSettings('favicon', $_POST['favicon']);
    }
    // Update activate_expiration setting
    if (isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] != $_POST['activate_expiration']) {
        updateSettings('activate_expiration', $_POST['activate_expiration']);
    }
    // Update maintenance mode
    if (@$_SESSION['settings']['maintenance_mode'] != $_POST['maintenance_mode']) {
        updateSettings('maintenance_mode', $_POST['maintenance_mode']);
    }
    // Update sts mode
    if ( @$_SESSION['settings']['enable_sts'] != $_POST['enable_sts'] ) {
        updateSettings('enable_sts', $_POST['enable_sts']);
    }
    // Update sts mode
    if ( @$_SESSION['settings']['encryptClientServer'] != $_POST['encryptClientServer'] ) {
        updateSettings('encryptClientServer', $_POST['encryptClientServer']);
    }
    // Update send_stats
    if (@$_SESSION['settings']['send_stats'] != $_POST['send_stats']) {
        updateSettings('send_stats', $_POST['send_stats']);
    }
    // Update get_tp_info
    if (@$_SESSION['settings']['get_tp_info'] != $_POST['get_tp_info']) {
        updateSettings('get_tp_info', $_POST['get_tp_info']);
    }
    // Update allow_print
    if (@$_SESSION['settings']['allow_print'] != $_POST['allow_print']) {
        updateSettings('allow_print', $_POST['allow_print']);
    }
    // Update allow_import
    if (@$_SESSION['settings']['allow_import'] != $_POST['allow_import']) {
        updateSettings('allow_import', $_POST['allow_import']);
    }
    // Update show_description
    if (@$_SESSION['settings']['show_description'] != $_POST['show_description']) {
        updateSettings('show_description', $_POST['show_description']);
    }
    // Update tree_counters
    if (@$_SESSION['settings']['tree_counters'] != $_POST['tree_counters']) {
        updateSettings('tree_counters', $_POST['tree_counters']);
    }
    // Update item_extra_fields
    if (@$_SESSION['settings']['item_extra_fields'] != $_POST['item_extra_fields']) {
        updateSettings('item_extra_fields', $_POST['item_extra_fields']);
    }
    // Update LDAP mode
    if (isset($_POST['ldap_mode']) && $_SESSION['settings']['ldap_mode'] != $_POST['ldap_mode']) {
        updateSettings('ldap_mode', $_POST['ldap_mode']);
    }
	// Update LDAP type
	if (isset($_POST['ldap_type']) && $_SESSION['settings']['ldap_type'] != $_POST['ldap_type']) {
        updateSettings('ldap_type', $_POST['ldap_type']);
    }
    // Update LDAP ldap_suffix
    if (@$_SESSION['settings']['ldap_suffix'] != $_POST['ldap_suffix']) {
        updateSettings('ldap_suffix', $_POST['ldap_suffix']);
    }
    // Update LDAP ldap_domain_dn
    if (@$_SESSION['settings']['ldap_domain_dn'] != $_POST['ldap_domain_dn']) {
        updateSettings('ldap_domain_dn', $_POST['ldap_domain_dn']);
    }
    // Update LDAP ldap_domain_controler
    if (@$_SESSION['settings']['ldap_domain_controler'] != $_POST['ldap_domain_controler']) {
        updateSettings('ldap_domain_controler', $_POST['ldap_domain_controler']);
    }
	// Update LDAP ldap_user_attribute
	if (@$_SESSION['settings']['ldap_user_attribute'] != @$_POST['ldap_user_attribute']) {
	    updateSettings('ldap_user_attribute', $_POST['ldap_user_attribute']);
	}
    // Update LDAP ssl
    if (@$_SESSION['settings']['ldap_ssl'] != $_POST['ldap_ssl']) {
        updateSettings('ldap_ssl', $_POST['ldap_ssl']);
    }
    // Update LDAP tls
    if (@$_SESSION['settings']['ldap_tls'] != $_POST['ldap_tls']) {
        updateSettings('ldap_tls', $_POST['ldap_tls']);
    }
    // Update LDAP ldap_elusers
    if (@$_SESSION['settings']['ldap_elusers'] != $_POST['ldap_elusers']) {
        updateSettings('ldap_elusers', $_POST['ldap_elusers']);
    }
	// Update LDAP ldap_group
	if (@$_SESSION['settings']['ldap_elusers'] != $_POST['ldap_elusers']) {
	    updateSettings('ldap_elusers', $_POST['ldap_elusers']);
	}
    // Update anyone_can_modify
    if (@$_SESSION['settings']['anyone_can_modify'] != $_POST['anyone_can_modify']) {
        updateSettings('anyone_can_modify', $_POST['anyone_can_modify']);
    }
    // Update anyone_can_modify_bydefault
    if (@$_SESSION['settings']['anyone_can_modify_bydefault'] != $_POST['anyone_can_modify_bydefault']) {
        updateSettings('anyone_can_modify_bydefault', $_POST['anyone_can_modify_bydefault']);
    }
    // Update enable_attachment_encryption
    if (@$_SESSION['settings']['enable_attachment_encryption'] != $_POST['enable_attachment_encryption']) {
        updateSettings('enable_attachment_encryption', $_POST['enable_attachment_encryption']);
    }
    // Update enable_kb
    if (@$_SESSION['settings']['enable_kb'] != $_POST['enable_kb']) {
        updateSettings('enable_kb', $_POST['enable_kb']);
    }
    // Update nb_bad_identification
    if (@$_SESSION['settings']['nb_bad_authentication'] != $_POST['nb_bad_authentication']) {
        updateSettings('nb_bad_authentication', $_POST['nb_bad_authentication']);
    }
    // Update restricted_to(if restricted_to is FALSE then restricted_to_roles needs to be FALSE
    if (@$_SESSION['settings']['restricted_to'] != $_POST['restricted_to']) {
        updateSettings('restricted_to', $_POST['restricted_to']);
        if ($_POST['restricted_to'] == 0) {
            updateSettings('restricted_to_roles', 0);
        }
    }
    // Update restricted_to_roles
    if (@$_SESSION['settings']['restricted_to_roles'] != $_POST['restricted_to_roles'] && (@$_SESSION['settings']['restricted_to'] == 1 OR $_POST['restricted_to'] == 1)) {
        updateSettings('restricted_to_roles', $_POST['restricted_to_roles']);
    }
    // Update copy_to_clipboard_small_icons
    if (@$_SESSION['settings']['copy_to_clipboard_small_icons'] != $_POST['copy_to_clipboard_small_icons']) {
        updateSettings('copy_to_clipboard_small_icons', $_POST['copy_to_clipboard_small_icons']);
    }
    // Update timezone_selection
    if (@$_SESSION['settings']['timezone'] != $_POST['timezone']) {
        updateSettings('timezone', $_POST['timezone']);
    }
    // Update enable_user_can_create_folders
    if (@$_SESSION['settings']['enable_user_can_create_folders'] != $_POST['enable_user_can_create_folders']) {
        updateSettings('enable_user_can_create_folders', $_POST['enable_user_can_create_folders']);
    }
    // Update enable_send_email_on_user_login
    if (@$_SESSION['settings']['enable_send_email_on_user_login'] != $_POST['enable_send_email_on_user_login']) {
        updateSettings('enable_send_email_on_user_login', $_POST['enable_send_email_on_user_login']);
    }
    // Update enable_email_notification_on_item_shown
    if (@$_SESSION['settings']['enable_email_notification_on_item_shown'] != $_POST['enable_email_notification_on_item_shown']) {
        updateSettings('enable_email_notification_on_item_shown', $_POST['enable_email_notification_on_item_shown']);
    }
    // Update custom_logo
    if (@$_SESSION['settings']['custom_logo'] != $_POST['custom_logo']) {
        updateSettings('custom_logo', $_POST['custom_logo']);
    }
    // Update custom_login_text
    if (@$_SESSION['settings']['custom_login_text'] != $_POST['custom_login_text']) {
        updateSettings('custom_login_text', htmlentities($_POST['custom_login_text'], ENT_QUOTES, "UTF-8"));
    }
    // Update nb of items to get in one query iterration
    if (@$_SESSION['settings']['nb_items_by_query'] != $_POST['nb_items_by_query']) {
        updateSettings('nb_items_by_query', $_POST['nb_items_by_query']);
    }
    // Update enable_delete_after_consultation
    if (@$_SESSION['settings']['enable_delete_after_consultation'] != $_POST['enable_delete_after_consultation']) {
        updateSettings('enable_delete_after_consultation', $_POST['enable_delete_after_consultation']);
    }
    // Update enable_personal_saltkey_cookie
    if (@$_SESSION['settings']['enable_personal_saltkey_cookie'] != $_POST['enable_personal_saltkey_cookie']) {
        updateSettings('enable_personal_saltkey_cookie', $_POST['enable_personal_saltkey_cookie']);
    }
    // Update use_md5_password_as_salt
    if (@$_SESSION['settings']['use_md5_password_as_salt'] != $_POST['use_md5_password_as_salt']) {
        updateSettings('use_md5_password_as_salt', $_POST['use_md5_password_as_salt']);
    }
    // Update personal_saltkey_cookie_duration
    if (@$_SESSION['settings']['personal_saltkey_cookie_duration'] != $_POST['personal_saltkey_cookie_duration']) {
        updateSettings('personal_saltkey_cookie_duration', $_POST['personal_saltkey_cookie_duration']);
    }
	// Update settings_offline_mode
	if (@$_SESSION['settings']['settings_offline_mode'] != $_POST['settings_offline_mode']) {
		updateSettings('settings_offline_mode', $_POST['settings_offline_mode']);
	}
	// Update offline_key_level
	if (@$_SESSION['settings']['offline_key_level'] != $_POST['offline_key_level']) {
		updateSettings('offline_key_level', $_POST['offline_key_level']);
	}
    // Update email_smtp_server
    if (@$_SESSION['settings']['email_smtp_server'] != $_POST['email_smtp_server']) {
        updateSettings('email_smtp_server', $_POST['email_smtp_server']);
    }
    // Update email_auth
    if (@$_SESSION['settings']['email_smtp_auth'] != $_POST['email_smtp_auth']) {
        updateSettings('email_smtp_auth', $_POST['email_smtp_auth']);
    }
    // Update email_auth_username
    if (@$_SESSION['settings']['email_auth_username'] != $_POST['email_auth_username']) {
        updateSettings('email_auth_username', $_POST['email_auth_username']);
    }
    // Update email_auth_pwd
    if (@$_SESSION['settings']['email_auth_pwd'] != $_POST['email_auth_pwd']) {
        updateSettings('email_auth_pwd', $_POST['email_auth_pwd']);
    }
    // Update email_from
    if (@$_SESSION['settings']['email_from'] != $_POST['email_from']) {
        updateSettings('email_from', $_POST['email_from']);
    }
    // Update email_from_name
    if (@$_SESSION['settings']['email_from_name'] != $_POST['email_from_name']) {
        updateSettings('email_from_name', $_POST['email_from_name']);
    }
    // Update email_port
    if (@$_SESSION['settings']['email_port'] != $_POST['email_port']) {
        updateSettings('email_port', $_POST['email_port']);
    }
    // store backups settings
    if (@$_SESSION['settings']['bck_script_filename'] != $_POST['bck_script_filename']) {
        updateSettings('bck_script_filename', $_POST['bck_script_filename'], 'settings');
    }
    if (@$_SESSION['settings']['bck_script_path'] != $_POST['bck_script_path']) {
        updateSettings('bck_script_path', $_POST['bck_script_path'], 'settings');
    }
    if (@$_SESSION['settings']['bck_script_key'] != $_POST['bck_script_key']) {
        updateSettings('bck_script_key', $_POST['bck_script_key'], 'settings');
    }
    // Update insert_manual_entry_item_history
    if (@$_SESSION['settings']['insert_manual_entry_item_history'] != $_POST['insert_manual_entry_item_history']) {
        updateSettings('insert_manual_entry_item_history', $_POST['insert_manual_entry_item_history']);
    }
    //2factors_authentication
    if (isset($_POST['2factors_authentication'])) {
        updateSettings('2factors_authentication', $_POST['2factors_authentication']);
    }
    //psk_authentication
    if (isset($_POST['psk_authentication'])) {
        updateSettings('psk_authentication', $_POST['psk_authentication']);
    }
    // Update proxy_ip setting
    if (@$_SESSION['settings']['proxy_ip'] != $_POST['proxy_ip']) {
        updateSettings('proxy_ip', $_POST['proxy_ip']);
    }
    // Update proxy_port setting
    if (@$_SESSION['settings']['proxy_port'] != $_POST['proxy_port']) {
        updateSettings('proxy_port', $_POST['proxy_port']);
    }

    // Update settings_upload_maxfilesize
    if (@$_SESSION['settings']['upload_maxfilesize'] != $_POST['upload_maxfilesize']) {
        updateSettings('upload_maxfilesize', $_POST['upload_maxfilesize']);
    }
    // Update upload_docext
    if (@$_SESSION['settings']['upload_docext'] != $_POST['upload_docext']) {
        updateSettings('upload_docext', $_POST['upload_docext']);
    }
    // Update upload_imagesext
    if (@$_SESSION['settings']['upload_imagesext'] != $_POST['upload_imagesext']) {
        updateSettings('upload_imagesext', $_POST['upload_imagesext']);
    }
    // Update upload_pkgext
    if (@$_SESSION['settings']['upload_pkgext'] != $_POST['upload_pkgext']) {
        updateSettings('upload_pkgext', $_POST['upload_pkgext']);
    }
    // Update upload_othext
    if (@$_SESSION['settings']['upload_otherext'] != $_POST['upload_otherext']) {
        updateSettings('upload_otherext', $_POST['upload_otherext']);
    }
    // Update upload_imageresize_options
    if (@$_SESSION['settings']['upload_imageresize_options'] != $_POST['upload_imageresize_options']) {
        updateSettings('upload_imageresize_options', $_POST['upload_imageresize_options']);
    }
    // Update upload_imageresize_width
    if (@$_SESSION['settings']['upload_imageresize_width'] != @$_POST['upload_imageresize_width']) {
        @updateSettings('upload_imageresize_width', $_POST['upload_imageresize_width']);
    }
    // Update upload_imageresize_height
    if (@$_SESSION['settings']['upload_imageresize_height'] != @$_POST['upload_imageresize_height']) {
        @updateSettings('upload_imageresize_height', $_POST['upload_imageresize_height']);
    }
    // Update upload_imageresize_quality
    if (@$_SESSION['settings']['upload_imageresize_quality'] != @$_POST['upload_imageresize_quality']) {
        @updateSettings('upload_imageresize_quality', $_POST['upload_imageresize_quality']);
    }
    // Update can_create_root_folder
    if (@$_SESSION['settings']['can_create_root_folder'] != $_POST['can_create_root_folder']) {
        updateSettings('can_create_root_folder', $_POST['can_create_root_folder']);
    }
	// Update use_md5_password_as_salt
	if (@$_SESSION['settings']['use_md5_password_as_salt'] != $_POST['use_md5_password_as_salt']) {
	    updateSettings('use_md5_password_as_salt', $_POST['use_md5_password_as_salt']);
	}
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
                <li><a href="#tabs-7">'.$txt['admin_upload_title'].'</a></li>
                <li><a href="#tabs-2">'.$txt['admin_actions_title'].'</a></li>
                <li><a href="#tabs-4">'.$txt['admin_ldap_menu'].'</a></li>
                <li><a href="#tabs-5">'.$txt['admin_backups'].'</a></li>
                <li><a href="#tabs-6">'.$txt['admin_emails'].'</a></li>
                <li><a href="admin.settings_categories.php">'.$txt['categories'].'</a></li>
            </ul>';
// --------------------------------------------------------------------------------
// TAB Né1
echo '
            <div id="tabs-1">
                <table border="0">';
// cpassman_dir
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="cpassman_dir">'.$txt['admin_misc_cpassman_dir'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="cpassman_dir" name="cpassman_dir" value="', isset($_SESSION['settings']['cpassman_dir']) ? $_SESSION['settings']['cpassman_dir'] : '', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// cpassman_url
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="cpassman_url">'.$txt['admin_misc_cpassman_url'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="cpassman_url" name="cpassman_url" value="', isset($_SESSION['settings']['cpassman_url']) ? $_SESSION['settings']['cpassman_url'] : '', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// path_to_upload_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="path_to_upload_folder">'.$txt['admin_path_to_upload_folder'].'</label>
                        &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_path_to_upload_folder_tip'].'" />
                    </td>
                    <td>
                        <input type="text" size="80" id="path_to_upload_folder" name="path_to_upload_folder" value="', isset($_SESSION['settings']['path_to_upload_folder']) ? $_SESSION['settings']['path_to_upload_folder'] : $_SESSION['settings']['cpassman_dir'].'/upload', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// url_to_upload_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="url_to_upload_folder">'.$txt['admin_url_to_upload_folder'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="url_to_upload_folder" name="url_to_upload_folder" value="', isset($_SESSION['settings']['url_to_upload_folder']) ? $_SESSION['settings']['url_to_upload_folder'] : $_SESSION['settings']['cpassman_url'].'/upload', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// path_to_files_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="path_to_files_folder">'.$txt['admin_path_to_files_folder'].'</label>
                        &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_path_to_files_folder_tip'].'" />
                    </td>
                    <td>
                        <input type="text" size="80" id="path_to_files_folder" name="path_to_files_folder" value="', isset($_SESSION['settings']['path_to_files_folder']) ? $_SESSION['settings']['path_to_files_folder'] : $_SESSION['settings']['cpassman_dir'].'/files', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// url_to_files_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="url_to_files_folder">'.$txt['admin_url_to_files_folder'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="url_to_files_folder" name="url_to_files_folder" value="', isset($_SESSION['settings']['url_to_files_folder']) ? $_SESSION['settings']['url_to_files_folder'] : $_SESSION['settings']['cpassman_url'].'/files', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// Favicon
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="favicon">'.$txt['admin_misc_favicon'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="favicon" name="favicon" value="', isset($_SESSION['settings']['favicon']) ? $_SESSION['settings']['favicon'] : '', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// custom_logo
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="cpassman_dir">'.$txt['admin_misc_custom_logo'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="custom_logo" name="custom_logo" value="', isset($_SESSION['settings']['custom_logo']) ? $_SESSION['settings']['custom_logo'] : '', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// custom_login_text
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="cpassman_dir">'.$txt['admin_misc_custom_login_text'].'</label>
                </td>
                <td>
                    <input type="text" size="80" id="custom_login_text" name="custom_login_text" value="', isset($_SESSION['settings']['custom_login_text']) ? $_SESSION['settings']['custom_login_text'] : '', '" class="text ui-widget-content" />
                <td>
            </tr>';

echo '
            </table>';

echo '
            <table>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Maintenance mode
echo '
            <tr style="margin-bottom:3px">
            <td>
                  <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                  <label>' .
$txt['settings_maintenance_mode'].'
                      &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_maintenance_mode_tip'].'" />
                  </label>
            </td>
            <td>
                <div class="div_radio">
                    <input type="radio" id="maintenance_mode_radio1" name="maintenance_mode" onclick="changeSettingStatus($(this).attr(\'name\'), 1)" value="1"', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ? ' checked="checked"' : '', ' /><label for="maintenance_mode_radio1">'.$txt['yes'].'</label>
                    <input type="radio" id="maintenance_mode_radio2" name="maintenance_mode" onclick="changeSettingStatus($(this).attr(\'name\'), 0)" value="0"', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['maintenance_mode']) ? ' checked="checked"':''), ' /><label for="maintenance_mode_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_maintenance_mode"><img src="includes/images/status', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ? '' : '-busy', '.png" /></span>
                </div>
              <td>
            </tr>';
echo '<tr><td colspan="3"><hr></td></tr>';
//Enable SSL STS
echo '
            <tr style="margin-bottom:3px">
                <td>
                      <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                      <label>' .
                          $txt['settings_enable_sts'] . '
                          &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="' . $txt['settings_enable_sts_tip'] . '" />
                      </label>
                </td>
                <td>
                    <div class="div_radio">
                        <input type="radio" id="enable_sts_radio1" name="enable_sts" onclick="changeSettingStatus($(this).attr(\'name\'), 1)" value="1"', isset( $_SESSION['settings']['enable_sts'] ) && $_SESSION['settings']['enable_sts'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_sts_radio1">' . $txt['yes'] . '</label>
                        <input type="radio" id="enable_sts_radio2" name="enable_sts" onclick="changeSettingStatus($(this).attr(\'name\'), 0)" value="0"', isset( $_SESSION['settings']['enable_sts'] ) && $_SESSION['settings']['enable_sts'] != 1 ? ' checked="checked"' : ( !isset( $_SESSION['settings']['enable_sts'] ) ? ' checked="checked"':'' ), ' /><label for="enable_sts_radio2">' . $txt['no'] . '</label>
                        <span class="setting_flag" id="flag_enable_sts"><img src="includes/images/status', isset($_SESSION['settings']['enable_sts']) && $_SESSION['settings']['enable_sts'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                <td>
            </tr>';
//Enable data exchange encryption
echo '
            <tr style="margin-bottom:3px">
                <td>
                      <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                      <label>' .
                          $txt['settings_encryptClientServer'] . '
                          &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="' . $txt['settings_encryptClientServer_tip'] . '" />
                      </label>
                </td>
                <td>
                    <div class="div_radio">
                        <input type="radio" id="encryptClientServer_radio1" name="encryptClientServer" onclick="changeSettingStatus($(this).attr(\'name\'), 1)" value="1"', isset( $_SESSION['settings']['encryptClientServer'] ) && $_SESSION['settings']['encryptClientServer'] == 1 ? ' checked="checked"' : '', ' /><label for="encryptClientServer_radio1">' . $txt['yes'] . '</label>
                        <input type="radio" id="encryptClientServer_radio2" name="encryptClientServer" onclick="changeSettingStatus($(this).attr(\'name\'), 0)" value="0"', isset( $_SESSION['settings']['encryptClientServer'] ) && $_SESSION['settings']['encryptClientServer'] != 1 ? ' checked="checked"' : ( !isset( $_SESSION['settings']['encryptClientServer'] ) ? ' checked="checked"':'' ), ' /><label for="encryptClientServer_radio2">' . $txt['no'] . '</label>
                        <span class="setting_flag" id="flag_encryptClientServer"><img src="includes/images/status', isset($_SESSION['settings']['encryptClientServer']) && $_SESSION['settings']['encryptClientServer'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                <td>
            </tr>';
/*
//Enable secure admin access
echo '
            <tr style="margin-bottom:3px">
                <td>
                      <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                      <label>' .
                          $txt['settings_encryptClientServer'] . '
                          &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="' . $txt['settings_encryptClientServer_tip'] . '" />
                      </label>
                </td>
                <td>
                    <div class="div_radio">
                        <input type="radio" id="encryptClientServer_radio1" name="encryptClientServer" onclick="changeSettingStatus($(this).attr(\'name\'), 1)" value="1"', isset( $_SESSION['settings']['encryptClientServer'] ) && $_SESSION['settings']['encryptClientServer'] == 1 ? ' checked="checked"' : '', ' /><label for="encryptClientServer_radio1">' . $txt['yes'] . '</label>
                        <input type="radio" id="encryptClientServer_radio2" name="encryptClientServer" onclick="changeSettingStatus($(this).attr(\'name\'), 0)" value="0"', isset( $_SESSION['settings']['encryptClientServer'] ) && $_SESSION['settings']['encryptClientServer'] != 1 ? ' checked="checked"' : ( !isset( $_SESSION['settings']['encryptClientServer'] ) ? ' checked="checked"':'' ), ' /><label for="encryptClientServer_radio2">' . $txt['no'] . '</label>
                        <span class="setting_flag" id="flag_encryptClientServer"><img src="includes/images/status', isset($_SESSION['settings']['encryptClientServer']) && $_SESSION['settings']['encryptClientServer'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                <td>
            </tr>';
*/
echo '<tr><td colspan="3"><hr></td></tr>';
//Proxy
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="proxy_ip">'.$txt['admin_proxy_ip'].'</label>
                    &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_proxy_ip_tip'].'" />
                </td>
                <td>
                    <input type="text" size="15" id="proxy_ip" name="proxy_ip" value="', isset($_SESSION['settings']['proxy_ip']) ? $_SESSION['settings']['proxy_ip'] : "", '" class="text ui-widget-content" />
                <td>
            </tr>
            <tr style="margin-bottom:3px">
                <td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="proxy_port">'.$txt['admin_proxy_port'].'</label>
                    &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_proxy_port_tip'].'" />
                </td>
                <td>
                    <input type="text" size="10" id="proxy_port" name="proxy_port" value="', isset($_SESSION['settings']['proxy_port']) ? $_SESSION['settings']['proxy_port'] : "", '" class="text ui-widget-content" />
                <td>
            </tr>';


echo '<tr><td colspan="3"><hr></td></tr>';
// pwd_maximum_length
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="pwd_maximum_length">'.$txt['admin_pwd_maximum_length'].'</label>
                    &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_pwd_maximum_length_tip'].'" />
                </td>
                <td>
                    <input type="text" size="10" id="pwd_maximum_length" name="pwd_maximum_length" value="', isset($_SESSION['settings']['pwd_maximum_length']) ? $_SESSION['settings']['pwd_maximum_length'] : 40, '" class="text ui-widget-content" />
                <td>
            </tr>';

// 2factors_code
echo '
            <tr style="margin-bottom:3px">
                <td>
                  <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                  <label>'.$txt['admin_2factors_authentication_setting'].'
                      &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_2factors_authentication_setting_tip'].'" />
                  </label>
            </td>
            <td>
                <div class="div_radio">
                    <input type="radio" id="2factors_authentication_radio1" name="2factors_authentication" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1 ? ' checked="checked"' : '', ' /><label for="2factors_authentication_radio1">'.$txt['yes'].'</label>
                    <input type="radio" id="2factors_authentication_radio2" name="2factors_authentication" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['2factors_authentication']) ? ' checked="checked"':''), ' /><label for="2factors_authentication_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_2factors_authentication"><img src="includes/images/status', isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1 ? '' : '-busy', '.png" /></span>
                </div>
              <td>
            </tr>';
// Google Authenticator website name
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="ga_website_name">'.$txt['admin_ga_website_name'].'</label>
                    &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_ga_website_name_tip'].'" />
                </td>
                <td>
                    <input type="text" size="30" id="ga_website_name" name="ga_website_name" value="', isset($_SESSION['settings']['ga_website_name']) ? $_SESSION['settings']['ga_website_name'] : 'TeamPass for ChangeMe', '" class="text ui-widget-content" />
                <td>
            </tr>';
/*
// psk_authentication
echo '
            <tr style="margin-bottom:3px">
                <td>
                  <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                  <label>'.$txt['admin_psk_authentication'].'
                      &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_psk_authentication_tip'].'" />
                  </label>
            </td>
            <td>
                <div class="div_radio">
                    <input type="radio" id="psk_authentication_radio1" name="psk_authentication" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1 ? ' checked="checked"' : '', ' /><label for="psk_authentication_radio1">'.$txt['yes'].'</label>
                    <input type="radio" id="psk_authentication_radio2" name="psk_authentication" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['psk_authentication']) ? ' checked="checked"':''), ' /><label for="psk_authentication_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_psk_authentication"><img src="includes/images/status', isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1 ? '' : '-busy', '.png" /></span>
                </div>
              <td>
            </tr>';
*/
echo '<tr><td colspan="3"><hr></td></tr>';
// TIMEZONE
// get list of all timezones
$zones = timezone_identifiers_list();
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="timezone">'.$txt['timezone_selection'].'</label>
                    </td>
                    <td>
                        <select id="timezone" name="timezone" class="text ui-widget-content">
                            <option value="">-- '.$txt['select'].' --</option>';
foreach ($zones as $zone) {
    echo '
    <option value="'.$zone.'"', isset($_SESSION['settings']['timezone']) && $_SESSION['settings']['timezone'] == $zone ? ' selected="selected"' : '', '>'.$zone.'</option>';
}
echo '
                        </select>
                    <td>
                </tr>';
// DATE format
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="date_format">'.$txt['date_format'].'</label>
                    </td>
                    <td>
                        <select id="date_format" name="date_format" class="text ui-widget-content">
                            <option value="d/m/Y"', !isset($_SESSION['settings']['date_format']) || $_SESSION['settings']['date_format'] == "d/m/Y" ? ' selected="selected"':"", '>d/m/Y</option>
                            <option value="m/d/Y"', $_SESSION['settings']['date_format'] == "m/d/Y" ? ' selected="selected"':"", '>m/d/Y</option>
                            <option value="d-M-Y"', $_SESSION['settings']['date_format'] == "d-M-Y" ? ' selected="selected"':"", '>d-M-Y</option>
                            <option value="d/m/y"', $_SESSION['settings']['date_format'] == "d/m/y" ? ' selected="selected"':"", '>d/m/y</option>
                            <option value="m/d/y"', $_SESSION['settings']['date_format'] == "m/d/y" ? ' selected="selected"':"", '>m/d/y</option>
                            <option value="d-M-y"', $_SESSION['settings']['date_format'] == "d-M-y" ? ' selected="selected"':"", '>d-M-y</option>
                            <option value="d-m-y"', $_SESSION['settings']['date_format'] == "d-m-y" ? ' selected="selected"':"", '>d-m-y</option>
                            <option value="Y-m-d"', $_SESSION['settings']['date_format'] == "Y-m-d" ? ' selected="selected"':"", '>Y-m-d</option>
                        </select>
                    <td>
                </tr>';
// TIME format
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="time_format">'.$txt['time_format'].'</label>
                    </td>
                    <td>
                        <select id="time_format" name="time_format" class="text ui-widget-content">
                            <option value="H:i:s"', !isset($_SESSION['settings']['time_format']) || $_SESSION['settings']['time_format'] == "H:i:s" ? ' selected="selected"':"", '>H:i:s</option>
                            <option value="h:m:s a"', $_SESSION['settings']['time_format'] == "h:m:s a" ? ' selected="selected"':"", '>h:m:s a</option>
                            <option value="g:i:s a"', $_SESSION['settings']['time_format'] == "g:i:s a" ? ' selected="selected"':"", '>g:i:s a</option>
                            <option value="G:i:s"', $_SESSION['settings']['time_format'] == "G:i:s" ? ' selected="selected"':"", '>G:i:s</option>
                        </select>
                    <td>
                </tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// LANGUAGES
$zones = timezone_identifiers_list();
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="default_language">'.$txt['settings_default_language'].'</label>
                    </td>
                    <td>
                        <select id="default_language" name="default_language" class="text ui-widget-content">
                            <option value="">-- '.$txt['select'].' --</option>';
foreach ($languagesList as $lang) {
    echo '
    <option value="'.$lang.'"', isset($_SESSION['settings']['default_language']) && $_SESSION['settings']['default_language'] == $lang ? ' selected="selected"' : '', '>'.$lang.'</option>';
}
echo '
                        </select>
                    <td>
                </tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Number of used pw
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="number_of_used_pw">'.$txt['number_of_used_pw'].'</label>
                    </td>
                    <td>
                        <input type="text" size="10" id="number_of_used_pw" name="number_of_used_pw" value="', isset($_SESSION['settings']['number_of_used_pw']) ? $_SESSION['settings']['number_of_used_pw'] : '5', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// Number days before changing pw
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="pw_life_duration">'.$txt['pw_life_duration'].'</label>
                    </td>
                    <td>
                        <input type="text" size="10" id="pw_life_duration" name="pw_life_duration" value="', isset($_SESSION['settings']['pw_life_duration']) ? $_SESSION['settings']['pw_life_duration'] : '5', '" class="text ui-widget-content" />
                    <td>
                </tr>';
// Number of bad authentication tentations before disabling user
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="nb_bad_authentication">'.$txt['nb_false_login_attempts'].'</label>
                    </td>
                    <td>
                        <input type="text" size="10" id="nb_bad_authentication" name="nb_bad_authentication" value="', isset($_SESSION['settings']['nb_bad_authentication']) ? $_SESSION['settings']['nb_bad_authentication'] : '0', '" class="text ui-widget-content" />
                    <td>
                </tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable log connections
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_log_connections'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="log_connections_radio1" name="log_connections" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 ? ' checked="checked"' : '', ' /><label for="log_connections_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="log_connections_radio2" name="log_connections" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['log_connections']) ? ' checked="checked"':''), ' /><label for="log_connections_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_log_connections"><img src="includes/images/status', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// Enable log accessed
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_log_accessed'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="log_accessed_radio1" name="log_accessed" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1 ? ' checked="checked"' : '', ' /><label for="log_accessed_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="log_accessed_radio2" name="log_accessed" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['log_accessed']) ? ' checked="checked"':''), ' /><label for="log_accessed_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_log_accessed"><img src="includes/images/status', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable PF
echo '
            <tr><td>
                <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                <label>'.$txt['enable_personal_folder_feature'].'</label>
                <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['enable_personal_folder_feature_tip'].'" /></span>
            </td><td>
            <div class="div_radio">
                <input type="radio" id="enable_pf_feature_radio1" name="enable_pf_feature" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_pf_feature_radio1">'.$txt['yes'].'</label>
                <input type="radio" id="enable_pf_feature_radio2" name="enable_pf_feature" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_pf_feature']) ? ' checked="checked"':''), ' /><label for="enable_pf_feature_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_pf_feature"><img src="includes/images/status', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? '' : '-busy', '.png" /></span>
            </div>
            </td</tr>';
// enable Use MD5 passowrd as Personal SALTKEY
echo '
        <tr><td>
            <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
            <label>'.$txt['use_md5_password_as_salt'].'</label>
        </td><td>
        <div class="div_radio">
            <input type="radio" id="use_md5_password_as_salt_radio1" name="use_md5_password_as_salt" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['use_md5_password_as_salt']) && $_SESSION['settings']['use_md5_password_as_salt'] == 1 ? ' checked="checked"' : '', ' /><label for="use_md5_password_as_salt_radio1">'.$txt['yes'].'</label>
            <input type="radio" id="use_md5_password_as_salt_radio2" name="use_md5_password_as_salt" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['use_md5_password_as_salt']) && $_SESSION['settings']['use_md5_password_as_salt'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['use_md5_password_as_salt']) ? ' checked="checked"':''), ' /><label for="use_md5_password_as_salt_radio2">'.$txt['no'].'</label>
                    <span class="setting_flag" id="flag_use_md5_password_as_salt"><img src="includes/images/status', isset($_SESSION['settings']['use_md5_password_as_salt']) && $_SESSION['settings']['use_md5_password_as_salt'] == 1 ? '' : '-busy', '.png" /></span>
        </div>
        </td</tr>';
// enable PF cookie for Personal SALTKEY
echo '
            <tr><td>
                <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                <label>'.$txt['enable_personal_saltkey_cookie'].'</label>
            </td><td>
            <div class="div_radio">
                <input type="radio" id="enable_personal_saltkey_cookie_radio1" name="enable_personal_saltkey_cookie" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_personal_saltkey_cookie']) && $_SESSION['settings']['enable_personal_saltkey_cookie'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_personal_saltkey_cookie_radio1">'.$txt['yes'].'</label>
                <input type="radio" id="enable_personal_saltkey_cookie_radio2" name="enable_personal_saltkey_cookie" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['enable_personal_saltkey_cookie']) && $_SESSION['settings']['enable_personal_saltkey_cookie'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_personal_saltkey_cookie']) ? ' checked="checked"':''), ' /><label for="enable_personal_saltkey_cookie_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_personal_saltkey_cookie"><img src="includes/images/status', isset($_SESSION['settings']['enable_personal_saltkey_cookie']) && $_SESSION['settings']['enable_personal_saltkey_cookie'] == 1 ? '' : '-busy', '.png" /></span>
            </div>
            </td</tr>';
// PF cookie for Personal SALTKEY duration
echo '
            <tr><td>
                <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                <label>'.$txt['personal_saltkey_cookie_duration'].'</label>
            </td><td>
            <div class="div_radio">
                <input type="text" size="10" id="personal_saltkey_cookie_duration" name="personal_saltkey_cookie_duration" value="', isset($_SESSION['settings']['personal_saltkey_cookie_duration']) ? $_SESSION['settings']['personal_saltkey_cookie_duration'] : '31', '" class="text ui-widget-content" />
            </div>
            </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Attachments encryption strategy
echo '
                    <tr><td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>
                            '.$txt['settings_attachments_encryption'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_attachments_encryption_tip'].'" /></span>
                        </label>
                        </td><td>
                        <div class="div_radio">
                            <input type="radio" id="enable_attachment_encryption_radio1" name="enable_attachment_encryption" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_attachment_encryption_radio1">'.$txt['yes'].'</label>
                            <input type="radio" id="enable_attachment_encryption_radio2" name="enable_attachment_encryption" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="0"', isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_attachment_encryption']) ? ' checked="checked"':''), ' /><label for="enable_attachment_encryption_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_attachment_encryption"><img src="includes/images/status', isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1 ? '' : '-busy', '.png" /></span>
                        </div>
                    </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable KB
echo '
                    <tr><td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>
                            '.$txt['settings_kb'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_kb_tip'].'" /></span>
                        </label>
                        </td><td>
                        <div class="div_radio">
                            <input type="radio" id="enable_kb_radio1" name="enable_kb" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_kb_radio1">'.$txt['yes'].'</label>
                            <input type="radio" id="enable_kb_radio2" name="enable_kb" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="0"', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_kb']) ? ' checked="checked"':''), ' /><label for="enable_kb_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_kb"><img src="includes/images/status', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1 ? '' : '-busy', '.png" /></span>
                        </div>
                    </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable send_stats
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>' .
$txt['settings_send_stats'].'
                            &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_send_stats_tip'].'" />
                        </label>
                    </td>
                    <td>
                        <div class="div_radio">
                            <input type="radio" id="send_stats_radio1" name="send_stats" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] == 1 ? ' checked="checked"' : '', ' /><label for="send_stats_radio1">'.$txt['yes'].'</label>
                            <input type="radio" id="send_stats_radio2" name="send_stats" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="0"', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['send_stats']) ? ' checked="checked"':''), ' /><label for="send_stats_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_send_stats"><img src="includes/images/status', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] == 1 ? '' : '-busy', '.png" /></span>
                        </div>
                    <td>
                </tr>';
// Enable GET TP Information
echo '
                    <tr><td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>
                            '.$txt['settings_get_tp_info'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_get_tp_info_tip'].'" /></span>
                        </label>
                        </td><td>
                        <div class="div_radio">
                            <input type="radio" id="get_tp_info_radio1" name="get_tp_info" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] == 1 ? ' checked="checked"' : '', ' /><label for="get_tp_info_radio1">'.$txt['yes'].'</label>
                            <input type="radio" id="get_tp_info_radio2" name="get_tp_info" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="0"', isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['get_tp_info']) ? ' checked="checked"':''), ' /><label for="get_tp_info_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_get_tp_info"><img src="includes/images/status', isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] == 1 ? '' : '-busy', '.png" /></span>
                        </div>
                    </td></tr>';

echo '
                <tr><td colspan="3"><hr></td></tr>
                </table>
            </div>';
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// TAB Né2
echo '
            <div id="tabs-2">';
// Update Personal folders for users
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_check_pf\')" style="cursor:pointer;">'.$txt['admin_action_check_pf'].'</a>
                    <span id="result_admin_action_check_pf" style="margin-left:10px;display:none;"><img src="includes/images/tick.png" alt="" /></span>
                </div>';
// Clean DB with orphan items
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_db_clean_items\')" style="cursor:pointer;">'.$txt['admin_action_db_clean_items'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_db_clean_items_tip'].'" /></span>
                    <span id="result_admin_action_db_clean_items" style="margin-left:10px;"></span>
                </div>';
// Optimize the DB
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_db_optimize\')" style="cursor:pointer;">'.$txt['admin_action_db_optimize'].'</a>
                    <span id="result_admin_action_db_optimize" style="margin-left:10px;"></span>
                </div>';
// Purge old files
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_purge_old_files\')" style="cursor:pointer;">'.$txt['admin_action_purge_old_files'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_purge_old_files_tip'].'" /></span>
                    <span id="result_admin_action_purge_old_files" style="margin-left:10px;"></span>
                </div>';
// Reload Cache Table
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_reload_cache_table\')" style="cursor:pointer;">'.$txt['admin_action_reload_cache_table'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_reload_cache_table_tip'].'" /></span>
                    <span id="result_admin_action_reload_cache_table" style="margin-left:10px;"></span>
                </div>';
// Change main SALT key
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
// Correct passwords prefix
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_pw_prefix_correct\')" style="cursor:pointer;">'.$txt['admin_action_pw_prefix_correct'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_pw_prefix_correct_tip'].'" /></span>
                    <span id="result_admin_action_pw_prefix_correct" style="margin-left:10px;"></span>
                </div>';
// Encrypt / decrypt attachments
echo '
                <div style="margin-bottom:3px">
                    <span style="float:left;">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    '.$txt['admin_action_attachments_cryption'].'
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_attachments_cryption_tip'].'" /></span>
                    </span>
                    <div class="div_radio" style="float:left;">
                        <input type="radio" id="attachments_cryption_radio1" name="attachments_cryption" value="encrypt" /><label for="attachments_cryption_radio1">'.$txt['encrypt'].'</label>
                        <input type="radio" id="attachments_cryption_radio2" name="attachments_cryption" value="decrypt" /><label for="attachments_cryption_radio2">'.$txt['decrypt'].'</label>
                    </div>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_attachments_cryption\')" style="cursor:pointer;">'.$txt['admin_action_db_backup_start_tip'].'</a>
                    <span id="result_admin_action_attachments_cryption" style="margin-left:10px;"></span>
                </div>';
echo '
            </div>';
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// TAB N°3
echo '
            <div id="tabs-3">
                <table width="100%">';
// After how long, edition is considered as failed or finished
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_delay_for_item_edition'].
                        '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_delay_for_item_edition_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="5" id="delay_item_edition" name="delay_item_edition" value="', isset($_SESSION['settings']['delay_item_edition']) ? $_SESSION['settings']['delay_item_edition'] : '0', '" class="text ui-widget-content" /></div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Managers can edit & delete items they are allowed to see
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_manager_edit'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="manager_edit_radio1" name="manager_edit" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] == 1 ? ' checked="checked"' : '', ' /><label for="manager_edit_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="manager_edit_radio2" name="manager_edit" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['manager_edit']) ? ' checked="checked"':''), ' /><label for="manager_edit_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_manager_edit"><img src="includes/images/status', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// max items
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="max_last_items">'.$txt['max_last_items'].'</label>
                    </td><td>
                    <input type="text" size="4" id="max_last_items" name="max_last_items" value="', isset($_SESSION['settings']['max_latest_items']) ? $_SESSION['settings']['max_latest_items'] : '', '" class="text ui-widget-content" />
                <tr><td>';
// Show last items
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['show_last_items'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="show_last_items_radio1" name="show_last_items" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] == 1 ? ' checked="checked"' : '', ' /><label for="show_last_items_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="show_last_items_radio2" name="show_last_items" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['show_last_items']) ? ' checked="checked"':''), ' /><label for="show_last_items_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_show_last_items"><img src="includes/images/status', isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Duplicate folder
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['duplicate_folder'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="duplicate_folder_radio1" name="duplicate_folder" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 1 ? ' checked="checked"' : '', ' /><label for="duplicate_folder_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="duplicate_folder_radio2" name="duplicate_folder" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['duplicate_folder']) ? ' checked="checked"':''), ' /><label for="duplicate_folder_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_duplicate_folder"><img src="includes/images/status', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// Duplicate item name
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['duplicate_item'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="duplicate_item_radio1" name="duplicate_item" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? ' checked="checked"' : '', ' /><label for="duplicate_item_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="duplicate_item_radio2" name="duplicate_item" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['duplicate_item']) ? ' checked="checked"':''), ' /><label for="duplicate_item_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_duplicate_item"><img src="includes/images/status', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// Enable extra fields for each Item
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_item_extra_fields'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_item_extra_fields_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="item_extra_fields_radio1" name="item_extra_fields" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ? ' checked="checked"' : '', ' /><label for="item_extra_fields_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="item_extra_fields_radio2" name="item_extra_fields" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['item_extra_fields']) ? ' checked="checked"':''), ' /><label for="item_extra_fields_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_item_extra_fields"><img src="includes/images/status', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable FAVOURITES
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['enable_favourites'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="enable_favourites_radio1" name="enable_favourites" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_favourites_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="enable_favourites_radio2" name="enable_favourites" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_favourites']) ? ' checked="checked"':''), ' /><label for="enable_favourites_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_favourites"><img src="includes/images/status', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// enable USER can create folders
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['enable_user_can_create_folders'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="enable_user_can_create_folders_radio1" name="enable_user_can_create_folders" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_user_can_create_folders_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="enable_user_can_create_folders_radio2" name="enable_user_can_create_folders" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_user_can_create_folders']) ? ' checked="checked"':''), ' /><label for="enable_user_can_create_folders_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_user_can_create_folders"><img src="includes/images/status', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// enable can_create_root_folder
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['setting_can_create_root_folder'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="can_create_root_folder_radio1" name="can_create_root_folder" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1 ? ' checked="checked"' : '', ' /><label for="can_create_root_folder_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="can_create_root_folder_radio2" name="can_create_root_folder" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['can_create_root_folder']) ? ' checked="checked"':''), ' /><label for="can_create_root_folder_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_can_create_root_folder"><img src="includes/images/status', isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable activate_expiration
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['admin_setting_activate_expiration'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_setting_activate_expiration_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="activate_expiration_radio1" name="activate_expiration" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] == 1 ? ' checked="checked"' : '', ' /><label for="activate_expiration_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="activate_expiration_radio2" name="activate_expiration" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['activate_expiration']) ? ' checked="checked"':''), ' /><label for="activate_expiration_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_activate_expiration"><img src="includes/images/status', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// Enable enable_delete_after_consultation
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['admin_setting_enable_delete_after_consultation'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_setting_enable_delete_after_consultation_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="enable_delete_after_consultation_radio1" name="enable_delete_after_consultation" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_delete_after_consultation_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="enable_delete_after_consultation_radio2" name="enable_delete_after_consultation" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_delete_after_consultation']) ? ' checked="checked"':''), ' /><label for="enable_delete_after_consultation_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_delete_after_consultation"><img src="includes/images/status', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable Printing
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_printing'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_printing_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="allow_print_radio1" name="allow_print" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 ? ' checked="checked"' : '', ' /><label for="allow_print_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="allow_print_radio2" name="allow_print" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['allow_print']) ? ' checked="checked"':''), ' /><label for="allow_print_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_allow_print"><img src="includes/images/status', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td></tr>';
// Enable IMPORT
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_importing'].'
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="allow_import_radio1" name="allow_import" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 ? ' checked="checked"' : '', ' /><label for="allow_import_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="allow_import_radio2" name="allow_import" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['allow_import']) ? ' checked="checked"':''), ' /><label for="allow_import_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_allow_import"><img src="includes/images/status', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable Item modification by anyone
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_anyone_can_modify'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_anyone_can_modify_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="anyone_can_modify_radio1" name="anyone_can_modify" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? ' checked="checked"' : '', ' /><label for="anyone_can_modify_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="anyone_can_modify_radio2" name="anyone_can_modify" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['anyone_can_modify']) ? ' checked="checked"':''), ' /><label for="anyone_can_modify_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_anyone_can_modify"><img src="includes/images/status', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td></tr>';
// Enable Item modification by anyone by default
echo '
                <tr id="tr_option_anyone_can_modify_bydefault" style="display:', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? 'inline':'none', ';"><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_anyone_can_modify_bydefault'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="anyone_can_modify_bydefault_radio1" name="anyone_can_modify_bydefault" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['anyone_can_modify_bydefault']) && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ? ' checked="checked"' : '', ' /><label for="anyone_can_modify_bydefault_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="anyone_can_modify_bydefault_radio2" name="anyone_can_modify_bydefault" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['anyone_can_modify_bydefault']) && $_SESSION['settings']['anyone_can_modify_bydefault'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['anyone_can_modify_bydefault']) ? ' checked="checked"':''), ' /><label for="anyone_can_modify_bydefault_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_anyone_can_modify_bydefault"><img src="includes/images/status', isset($_SESSION['settings']['anyone_can_modify_bydefault']) && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td></tr>';
// enable restricted_to option
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_restricted_to'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="restricted_to_radio1" name="restricted_to" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? ' checked="checked"' : '', ' /><label for="restricted_to_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="restricted_to_radio2" name="restricted_to" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['restricted_to']) ? ' checked="checked"':''), ' /><label for="restricted_to_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_restricted_to"><img src="includes/images/status', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// enable restricted_to_roles
echo '
                <tr id="tr_option_restricted_to_roles" style="display:', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? 'inline':'none', ';"><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['restricted_to_roles'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="restricted_to_roles_radio1" name="restricted_to_roles" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? ' checked="checked"' : '', ' /><label for="restricted_to_roles_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="restricted_to_roles_radio2" name="restricted_to_roles" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['restricted_to_roles']) ? ' checked="checked"':''), ' /><label for="restricted_to_roles_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_restricted_to_roles"><img src="includes/images/status', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable show copy to clipboard small icons
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['copy_to_clipboard_small_icons'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['copy_to_clipboard_small_icons_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="copy_to_clipboard_small_icons_radio1" name="copy_to_clipboard_small_icons" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? ' checked="checked"' : '', ' /><label for="copy_to_clipboard_small_icons_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="copy_to_clipboard_small_icons_radio2" name="copy_to_clipboard_small_icons" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['copy_to_clipboard_small_icons']) ? ' checked="checked"':''), ' /><label for="copy_to_clipboard_small_icons_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_copy_to_clipboard_small_icons"><img src="includes/images/status', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// Enable Show description in items list
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_show_description'].'
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="show_description_radio1" name="show_description" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1 ? ' checked="checked"' : '', ' /><label for="show_description_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="show_description_radio2" name="show_description" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['show_description']) ? ' checked="checked"':''), ' /><label for="show_description_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_show_description"><img src="includes/images/status', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td></tr>';
// In Tree, display number of Items in subfolders and number of subfolders - tree_counters
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_tree_counters'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_tree_counters_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="tree_counters_radio1" name="tree_counters" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1 ? ' checked="checked"' : '', ' /><label for="tree_counters_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="tree_counters_radio2" name="tree_counters" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['tree_counters']) ? ' checked="checked"':''), ' /><label for="tree_counters_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_tree_counters"><img src="includes/images/status', isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td></tr>';
// nb of items to display by ajax query
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['nb_items_by_query'].'</label>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['nb_items_by_query_tip'].'" /></span>
                    </td><td>
                    <input type="text" size="4" id="nb_items_by_query" name="nb_items_by_query" value="', isset($_SESSION['settings']['nb_items_by_query']) ? $_SESSION['settings']['nb_items_by_query'] : '', '" class="text ui-widget-content" />
                <tr><td>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable sending email on USER login
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['enable_send_email_on_user_login'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="enable_send_email_on_user_login_radio1" name="enable_send_email_on_user_login" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_send_email_on_user_login_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="enable_send_email_on_user_login_radio2" name="enable_send_email_on_user_login" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_send_email_on_user_login']) ? ' checked="checked"':''), ' /><label for="enable_send_email_on_user_login_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_send_email_on_user_login"><img src="includes/images/status', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// enable email notification on item shown
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['enable_email_notification_on_item_shown'].'</label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="enable_email_notification_on_item_shown_radio1" name="enable_email_notification_on_item_shown" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? ' checked="checked"' : '', ' /><label for="enable_email_notification_on_item_shown_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="enable_email_notification_on_item_shown_radio2" name="enable_email_notification_on_item_shown" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['enable_email_notification_on_item_shown']) ? ' checked="checked"':''), ' /><label for="enable_email_notification_on_item_shown_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_enable_email_notification_on_item_shown"><img src="includes/images/status', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable add manual entries in History
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_insert_manual_entry_item_history'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_insert_manual_entry_item_history_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="insert_manual_entry_item_history_radio1" name="insert_manual_entry_item_history" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1 ? ' checked="checked"' : '', ' /><label for="insert_manual_entry_item_history_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="insert_manual_entry_item_history_radio2" name="insert_manual_entry_item_history" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['insert_manual_entry_item_history']) ? ' checked="checked"':''), ' /><label for="insert_manual_entry_item_history_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_insert_manual_entry_item_history"><img src="includes/images/status', isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
echo '<tr><td colspan="3"><hr></td></tr>';
// OffLine mode options
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$txt['settings_offline_mode'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_offline_mode_tip'].'" /></span>
                    </label>
                    </td><td>
                    <div class="div_radio">
                        <input type="radio" id="settings_offline_mode_radio1" name="settings_offline_mode" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 ? ' checked="checked"' : '', ' /><label for="settings_offline_mode_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="settings_offline_mode_radio2" name="settings_offline_mode" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['settings_offline_mode']) ? ' checked="checked"':''), ' /><label for="settings_offline_mode_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_settings_offline_mode"><img src="includes/images/status', isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 ? '' : '-busy', '.png" /></span>
                    </div>
                </td</tr>';
// OffLne KEy Level
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="offline_key_level">'.$txt['offline_mode_key_level'].'</label>
                    </td>
                    <td>
                        <select id="offline_key_level" name="offline_key_level" class="text ui-widget-content">';
foreach ($pwComplexity as $complex) {
	echo '<option value="'.$complex[0].'"', isset($_SESSION['settings']['offline_key_level']) && $_SESSION['settings']['offline_key_level'] == $complex[0] ? ' selected="selected"' : '', '>'.$complex[1].'</option>';
}
echo '
                        </select>
                    <td>
                </tr>';

echo '
            </table>
            </div>';
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// TAB Né4
echo '
            <div id="tabs-4">';
// Check if LDAP extension is loaded
if (!extension_loaded('ldap')) {
    echo '
    <div style="margin-bottom:3px;">
        <div class="ui-widget-content ui-corner-all" style="padding:10px;">
            <img src="includes/images/error.png" alt="">&nbsp;&nbsp;'.$txt['ldap_extension_not_loaded'].'
        </div>
    </div>';
} else {
    // Enable LDAP mode
    echo '
    <div style="margin-bottom:3px;">
        <label for="ldap_mode">' .
    $txt['settings_ldap_mode'].'
            &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_ldap_mode_tip'].'" />
                    </label>
                    <span class="div_radio">
                        <input type="radio" id="ldap_mode_radio1" name="ldap_mode" onclick="changeSettingStatus($(this).attr(\'name\'), 1) " value="1"', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? ' checked="checked"' : '', ' onclick="javascript:$(\'#div_ldap_configuration\').show();" /><label for="ldap_mode_radio1">'.$txt['yes'].'</label>
                        <input type="radio" id="ldap_mode_radio2" name="ldap_mode" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['ldap_mode']) ? ' checked="checked"':''), ' onclick="javascript:$(\'#div_ldap_configuration\').hide();" /><label for="ldap_mode_radio2">'.$txt['no'].'</label>
                        <span class="setting_flag" id="flag_ldap_mode"><img src="includes/images/status', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? '' : '-busy', '.png" /></span>
                    </span>
                </div>';
	// Type
	$ldap_type = isset($_SESSION['settings']['ldap_type']) ? $_SESSION['settings']['ldap_type'] : '';
	echo '
<div style="margin-bottom:3px;">
    <label for="ldap_type">'.$txt['settings_ldap_type'].'</label>
    <select id="ldap_type" name="ldap_type" class="text ui-widget-content">
        <option value="windows">Windows / Active Directory</option>
        <option value="posix"', $ldap_type == 'posix' ? ' selected="selected"' : '', '>Posix / OpenLDAP (RFC2307)</option>
    </select>
</div>';
}
// LDAP inputs
echo '
            <div id="div_ldap_configuration" ', (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1) ? '':' style="display:none;"' , '>
                <div style="font-weight:bold;font-size:14px;margin:15px 0px 8px 0px;">'.$txt['admin_ldap_configuration'].'</div>
                <table>';
// Domain
if ($ldap_type != 'posix') {
echo '
                    <tr>
                        <td><label for="ldap_suffix">'.$txt['settings_ldap_domain'].'</label></td>
                        <td><input type="text" size="50" id="ldap_suffix" name="ldap_suffix" class="text ui-widget-content" title="@dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_suffix']) ? $_SESSION['settings']['ldap_suffix'] : '', '" /></td>
                    </tr>';
}

// Domain DN
echo '
                    <tr>
                        <td><label for="ldap_domain_dn">'.$txt['settings_ldap_domain_dn'].'</label></td>
                        <td><input type="text" size="50" id="ldap_domain_dn" name="ldap_domain_dn" class="text ui-widget-content" title="dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_domain_dn']) ? $_SESSION['settings']['ldap_domain_dn'] : '', '" /></td>
                    </tr>';

// Subtree for posix / openldap
if ($ldap_type == 'posix') {
		echo '
                <tr>
                    <td><label for="ldap_suffix">'.$txt['settings_ldap_domain_posix'].'</label></td>
                    <td><input type="text" size="50" id="ldap_suffix" name="ldap_suffix" class="text ui-widget-content" title="@dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_suffix']) ? $_SESSION['settings']['ldap_suffix'] : '', '" /></td>
                </tr>';
}

// LDAP username attribute
if ($ldap_type == 'posix') {
		echo '
                <tr>
                    <td><label for="ldap_user_attribute">'.$txt['settings_ldap_user_attribute'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.
                        $txt['settings_ldap_user_attribute_tip'].'" /></label></td>
                    <td><input type="text" size="50" id="ldap_user_attribute" name="ldap_user_attribute" class="text ui-widget-content" title="uid" value="',
                        isset($_SESSION['settings']['ldap_user_attribute']) ? $_SESSION['settings']['ldap_user_attribute'] : 'uid', '" /></td>
                </tr>';
}

// Domain controler
echo '
                    <tr>
                        <td><label for="ldap_domain_controler">'.$txt['settings_ldap_domain_controler'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_ldap_domain_controler_tip'].'" /></label></td>
                        <td><input type="text" size="50" id="ldap_domain_controler" name="ldap_domain_controler" class="text ui-widget-content" title="dc01.mydomain.local,dc02.mydomain.local" value="', isset($_SESSION['settings']['ldap_domain_controler']) ? $_SESSION['settings']['ldap_domain_controler'] : '', '" /></td>
                    </tr>';
// AD SSL
echo '
                    <tr>
                        <td><label>'.$txt['settings_ldap_ssl'].'</label></td>
                        <td>
                            <div class="div_radio" id="rad1">
                                <input type="radio" id="ldap_ssl_radio1" name="ldap_ssl" onclick="changeSettingStatus($(this).attr(\'name\'), 1);changeSettingStatus(\'ldap_tls\', 0);$(\'input[name=ldap_tls]\').val([\'0\']).button(\'refresh\');" value="1"', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] == 1 ? ' checked="checked"' : '', ' /><label for="ldap_ssl_radio1">'.$txt['yes'].'</label>
                                <input type="radio" id="ldap_ssl_radio2" name="ldap_ssl" onclick="changeSettingStatus($(this).attr(\'name\'), 0) " value="0"', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['ldap_ssl']) ? ' checked="checked"':''), ' /><label for="ldap_ssl_radio2">'.$txt['no'].'</label>
                                <span class="setting_flag" id="flag_ldap_ssl"><img src="includes/images/status', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] == 1 ? '' : '-busy', '.png" /></span>
                            </div>
                        </td>
                    </tr>';
// AD TLS
echo '
                    <tr>
                        <td><label>'.$txt['settings_ldap_tls'].'</label></td>
                        <td>
                            <div class="div_radio">
                                <input type="radio" id="ldap_tls_radio1" name="ldap_tls" onclick="changeSettingStatus($(this).attr(\'name\'), 1);changeSettingStatus(\'ldap_ssl\', 0);$(\'input[name=ldap_ssl]\').val([\'0\']).button(\'refresh\');" value="1"', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] == 1 ? ' checked="checked"' : '', ' /><label for="ldap_tls_radio1">'.$txt['yes'].'</label>
                                <input type="radio" id="ldap_tls_radio2" name="ldap_tls" onclick="changeSettingStatus($(this).attr(\'name\'), 0)" value="0"', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['ldap_tls']) ? ' checked="checked"':''), ' /><label for="ldap_tls_radio2">'.$txt['no'].'</label>
                                <span class="setting_flag" id="flag_ldap_tls"><img src="includes/images/status', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] == 1 ? '' : '-busy', '.png" /></span>
                            </div>
                        </td>
                    </tr>';
// Enable only localy declared users with tips help
echo '
                    <tr>
                        <td><label>'.$txt['settings_ldap_elusers'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_ldap_elusers_tip'].'" /></label></td>
                        <td>
                            <div class="div_radio">
                                <input type="radio" id="ldap_elusers_radio1" name="ldap_elusers" onclick="changeSettingStatus($(this).attr(\'name\'), 1)" value="1"', isset($_SESSION['settings']['ldap_elusers']) && $_SESSION['settings']['ldap_elusers'] == 1 ? ' checked="checked"' : '', ' /><label for="ldap_elusers_radio1">'.$txt['yes'].'</label>
                                <input type="radio" id="ldap_elusers_radio2" name="ldap_elusers" onclick="changeSettingStatus($(this).attr(\'name\'), 0)" value="0"', isset($_SESSION['settings']['ldap_elusers']) && $_SESSION['settings']['ldap_elusers'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['ldap_elusers']) ? ' checked="checked"':''), ' /><label for="ldap_elusers_radio2">'.$txt['no'].'</label>
                                <span class="setting_flag" id="flag_ldap_elusers"><img src="includes/images/status', isset($_SESSION['settings']['ldap_elusers']) && $_SESSION['settings']['ldap_elusers'] == 1 ? '' : '-busy', '.png" /></span>
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
// TAB Né5
echo '
            <div id="tabs-5">
                <div class="" style="padding: 0 .7em;">
                    <span class="ui-icon ui-icon-transferthick-e-w" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <b>'.$txt['admin_one_shot_backup'].'</b>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// Backup the DB
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
                            &nbsp;'.$txt['encrypt_key'].'<input type="password" size="20" id="result_admin_action_db_backup_key" />
                            <img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_key_tip'].'" />
                            <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_action_db_backup\')" style="cursor:pointer;" />
                        </span>
                        </td>
                    </tr>';
// Restore the DB
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                        '.$txt['admin_action_db_restore'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['admin_action_db_restore_tip'].'" /></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <div id="upload_container_restoreDB">
                            <div id="filelist_restoreDB"></div><br />
                            <a id="pickfiles_restoreDB" class="button" href="#">'.$txt['select'].'</a>
                        </div>
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
// Backups script path
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                        '.$txt['admin_script_backup_path'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backup_path_tip'].'</h2>" /></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_path" name="bck_script_path" type="text" size="80px" value="', isset($_SESSION['settings']['bck_script_path']) ? $_SESSION['settings']['bck_script_path'] : $_SESSION['settings']['cpassman_dir'].'/backups', '" />
                        </td>
                    </tr>';
// Backups script name
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                        '.$txt['admin_script_backup_filename'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backup_filename_tip'].'</h2>" /></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_filename" name="bck_script_filename" type="text" size="50px" value="', isset($_SESSION['settings']['bck_script_filename']) ? $_SESSION['settings']['bck_script_filename'] : 'bck_cpassman', '" />
                        </td>
                    </tr>';
// Backups script encryption
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                        '.$txt['admin_script_backup_encryption'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_script_backup_encryption_tip'].'</h2>" /></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_key" name="bck_script_key" type="password" size="50px" value="', isset($_SESSION['settings']['bck_script_key']) ? $_SESSION['settings']['bck_script_key'] : '', '" />
                        </td>
                    </tr>';
// Decrypt SQL file
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
// --------------------------------------------------------------------------------
// TAB Né6
echo '
            <div id="tabs-6">
                <div class="" style="padding: 0 .7em;">
                    <span class="ui-icon ui-icon-transferthick-e-w" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <b>'.$txt['admin_emails_configuration'].'</b>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// SMTP server
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_smtp_server'].'
                        </td>
                        <td>
                            <input id="email_smtp_server" name="email_smtp_server" type="text" size="40px" value="', !isset($_SESSION['settings']['email_smtp_server']) ? $smtp_server : $_SESSION['settings']['email_smtp_server'], '" />
                        </td>
                    </tr>';
// SMTP auth
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_auth'].'
                        </td>
                        <td>
                            <div class="div_radio">
                                <input type="radio" id="email_smtp_auth_radio1" name="email_smtp_auth" onclick="changeSettingStatus($(this).attr(\'name\'), 1)" value="true"', isset($_SESSION['settings']['email_smtp_auth']) && $_SESSION['settings']['email_smtp_auth'] == "true" ? ' checked="checked"' : '', ' /><label for="email_smtp_auth_radio1">'.$txt['yes'].'</label>
                                <input type="radio" id="email_smtp_auth_radio2" name="email_smtp_auth" onclick="changeSettingStatus($(this).attr(\'name\'), 0)" value="false"', isset($_SESSION['settings']['email_smtp_auth']) && $_SESSION['settings']['email_smtp_auth'] != "true" ? ' checked="checked"' : (!isset($_SESSION['settings']['email_smtp_auth']) ? ' checked="checked"':''), ' /><label for="email_smtp_auth_radio2">'.$txt['no'].'</label>
                                <span class="setting_flag" id="flag_email_smtp_auth"><img src="includes/images/status', isset($_SESSION['settings']['email_smtp_auth']) && $_SESSION['settings']['email_smtp_auth'] == 1 ? '' : '-busy', '.png" /></span>
                            </div>
                        </td>
                    </tr>';
// SMTP auth username
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_auth_username'].'
                        </td>
                        <td>
                            <input id="email_auth_username" name="email_auth_username" type="text" size="40px" value="', !isset($_SESSION['settings']['email_auth_username']) ? $smtp_auth_username : $_SESSION['settings']['email_auth_username'], '" />
                        </td>
                    </tr>';
// SMTP auth pwd
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_auth_pwd'].'
                        </td>
                        <td>
                            <input id="email_auth_pwd" name="email_auth_pwd" type="password" size="40px" value="', !isset($_SESSION['settings']['email_auth_pwd']) ? $smtp_auth_password : $_SESSION['settings']['email_auth_pwd'], '" />
                        </td>
                    </tr>';
// SMTP port
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_port'].'
                        </td>
                        <td>
                            <input id="email_port" name="email_port" type="text" size="40px" value="', !isset($_SESSION['settings']['email_port']) ? '25' : $_SESSION['settings']['email_port'], '" />
                        </td>
                    </tr>';
// SMTP from
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_from'].'
                        </td>
                        <td>
                            <input id="email_from" name="email_from" type="text" size="40px" value="', !isset($_SESSION['settings']['email_from']) ? $email_from : $_SESSION['settings']['email_from'], '" />
                        </td>
                    </tr>';
// SMTP from name
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_from_name'].'
                        </td>
                        <td>
                            <input id="email_from_name" name="email_from_name" type="text" size="40px" value="', !isset($_SESSION['settings']['email_from_name']) ? $email_from_name : $_SESSION['settings']['email_from_name'], '" />
                        </td>
                    </tr>';

echo '
                    </table>
                </div>';

echo '
                <div class="" style="0padding: 0 .7em;">
                    <span class="ui-icon ui-icon-transferthick-e-w" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <b>'.$txt['admin_emails_configuration_testing'].'</b>
                </div>
                <div id="email_testing_results" class="ui-state-error ui-corner-all" style="padding:5px;display:none;margin:2px;"></div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// Test email configuration
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$txt['admin_email_test_configuration'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_email_test_configuration_tip'].'</h2>" /></span>
                        </td>
                        <td>
                            <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_email_test_configuration\')" style="cursor:pointer;" />
                        </td>
                    </tr>';
// Send emails backlog
//$nb_emails = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."emails WHERE status = 'not_sent' OR status = ''");
$nb_emails = $db->queryCount(
    "emails",
    "status = 'not_sent' OR status = ''"
);
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.str_replace("#nb_emails#", $nb_emails[0], $txt['admin_email_send_backlog']).'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$txt['admin_email_send_backlog_tip'].'</h2>" /></span>
                        </td>
                        <td>
                            <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$txt['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_email_send_backlog\')" style="cursor:pointer;" />
                        </td>
                    </tr>';

echo '
                    </table>
                </div>
            </div>';
// --------------------------------------------------------------------------------
// TAB N°7
echo '
            <div id="tabs-7">
                <table width="100%">';
// Max file size
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_maxfilesize'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_upload_maxfilesize_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="5" id="upload_maxfilesize" name="upload_maxfilesize" value="', isset($_SESSION['settings']['upload_maxfilesize']) ? $_SESSION['settings']['upload_maxfilesize'] : '10', '" class="text ui-widget-content" /></div>
                </td</tr>';
// Extension for Documents
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_docext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_upload_docext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_docext" name="upload_docext" value="', isset($_SESSION['settings']['upload_docext']) ? $_SESSION['settings']['upload_docext'] : 'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx', '" class="text ui-widget-content" /></div>
                </td</tr>';
// Extension for Images
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_imagesext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_upload_imagesext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_imagesext" name="upload_imagesext" value="', isset($_SESSION['settings']['upload_imagesext']) ? $_SESSION['settings']['upload_imagesext'] : 'jpg,jpeg,gif,png', '" class="text ui-widget-content" /></div>
                </td</tr>';
// Extension for Packages
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_pkgext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_upload_pkgext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_pkgext" name="upload_pkgext" value="', isset($_SESSION['settings']['upload_pkgext']) ? $_SESSION['settings']['upload_pkgext'] : '7z,rar,tar,zip', '" class="text ui-widget-content" /></div>
                </td</tr>';
// Extension for Other
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_otherext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_upload_otherext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_otherext" name="upload_otherext" value="', isset($_SESSION['settings']['upload_otherext']) ? $_SESSION['settings']['upload_otherext'] : 'sql,xml', '" class="text ui-widget-content" /></div>
                </td</tr>';
echo '<tr><td colspan="3"><hr></td></tr>';
// Image resize width / height / quality
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>' .
                        $txt['settings_upload_imageresize_options'].'
                        &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$txt['settings_upload_imageresize_options_tip'].'" />
                        </label>
                    </td>
                    <td>
                        <div class="div_radio">
                            <input type="radio" id="upload_imageresize_options_radio1" name="upload_imageresize_options" onclick="changeSettingStatus($(this).attr(\'name\'), 1);" value="1"', isset($_SESSION['settings']['upload_imageresize_options']) && $_SESSION['settings']['upload_imageresize_options'] == 1 ? ' checked="checked"' : '', ' /><label for="upload_imageresize_options_radio1">'.$txt['yes'].'</label>
                            <input type="radio" id="upload_imageresize_options_radio2" name="upload_imageresize_options" onclick="changeSettingStatus($(this).attr(\'name\'), 0);" value="0"', isset($_SESSION['settings']['upload_imageresize_options']) && $_SESSION['settings']['upload_imageresize_options'] != 1 ? ' checked="checked"' : (!isset($_SESSION['settings']['upload_imageresize_options']) ? ' checked="checked"':''), ' /><label for="upload_imageresize_options_radio2">'.$txt['no'].'</label>
                                <span class="setting_flag" id="flag_upload_imageresize_options"><img src="includes/images/status', isset($_SESSION['settings']['upload_imageresize_options']) && $_SESSION['settings']['upload_imageresize_options'] == 1 ? '' : '-busy', '.png" /></span>
                        </div>
                    </td>
                </tr>
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_imageresize_options_w'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_width" name="upload_imageresize_width" value="',
                        isset($_SESSION['settings']['upload_imageresize_width']) ? $_SESSION['settings']['upload_imageresize_width'] :
                        '800', '" class="text ui-widget-content upl_img_opt" />
                    <td>
                </tr>
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_imageresize_options_h'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_height" name="upload_imageresize_height" value="',
                        isset($_SESSION['settings']['upload_imageresize_height']) ? $_SESSION['settings']['upload_imageresize_height'] :
                        '600', '" class="text ui-widget-content upl_img_opt" />
                    <td>
                </tr>
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$txt['settings_upload_imageresize_options_q'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_quality" name="upload_imageresize_quality" value="',
                        isset($_SESSION['settings']['upload_imageresize_quality']) ? $_SESSION['settings']['upload_imageresize_quality'] :
                        '90', '" class="text ui-widget-content upl_img_opt" />
                </td</tr>';
echo '
                <tr><td colspan="3"><hr></td></tr>';
echo '
                </table>
            </div>';
// --------------------------------------------------------------------------------

// Save button
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
include "admin.settings.load.php";
