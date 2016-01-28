<?php
/**
 *
 * @file          admin.settings.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
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
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

/*
* FUNCTION permitting to store into DB the settings changes
*/
function updateSettings ($setting, $val, $type = '')
{
    global $server, $user, $pass, $database, $pre, $port, $encoding;

    if (empty($type)) {
        $type = 'admin';
    }

    require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    // Connect to database
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

    // Check if setting is already in DB. If NO then insert, if YES then update.
    $data = DB::query(
        "SELECT * FROM ".prefix_table("misc")."
        WHERE type = %s AND intitule = %s",
        $type,
        $setting
    );
    $counter = DB::count();
    if ($counter == 0) {
        DB::insert(
            prefix_table("misc"),
            array(
                'valeur' => $val,
                'type' => $type,
                'intitule' => $setting
               )
        );
        // in case of stats enabled, add the actual time
        if ($setting == 'send_stats') {
            DB::insert(
                prefix_table("misc"),
                array(
                    'valeur' => time(),
                    'type' => $type,
                    'intitule' => $setting.'_time'
                   )
            );
        }
    } else {
        DB::update(
            prefix_table("misc"),
            array(
                'valeur' => $val
               ),
            "type = %s AND intitule = %s",
            $type,
            $setting
        );
        // in case of stats enabled, update the actual time
        if ($setting == 'send_stats') {
            // Check if previous time exists, if not them insert this value in DB
            $data_time = DB::query(
                "SELECT * FROM ".prefix_table("misc")."
                WHERE type = %s AND intitule = %s",
                $type,
                $setting.'_time'
            );
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'valeur' => 0,
                        'type' => $type,
                        'intitule' => $setting.'_time'
                       )
                );
            } else {
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => 0
                       ),
                    "type = %s AND intitule = %s",
                    $type,
                    $setting
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
    // Update otv_expiration_period
    if (isset($_SESSION['settings']['otv_expiration_period']) && $_SESSION['settings']['otv_expiration_period'] != $_POST['otv_expiration_period']) {
        updateSettings('otv_expiration_period', $_POST['otv_expiration_period']);
    }
    // Update favourites
    if (isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] != $_POST['enable_favourites_input']) {
        updateSettings('enable_favourites', $_POST['enable_favourites_input']);
    }
    /*
    // Update last shown items
    if (isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] != $_POST['show_last_items']) {
        updateSettings('show_last_items', $_POST['show_last_items']);
    }
    */
    // Update personal feature
    if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] != $_POST['enable_pf_feature_input']) {
        updateSettings('enable_pf_feature', $_POST['enable_pf_feature_input']);
    }
    // Update loggin connections setting
    if (isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] != $_POST['log_connections_input']) {
        updateSettings('log_connections', $_POST['log_connections_input']);
    }
    // Update log_accessed setting
    if ((isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] != $_POST['log_accessed_input']) || !isset($_SESSION['settings']['log_accessed'])) {
        updateSettings('log_accessed', $_POST['log_accessed_input']);
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
    if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] != $_POST['duplicate_folder_input']) {
        updateSettings('duplicate_folder', $_POST['duplicate_folder_input']);
    }
    // Update duplicate item setting
    if (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] != $_POST['duplicate_item_input']) {
        updateSettings('duplicate_item', $_POST['duplicate_item_input']);
    }
    // Update item_duplicate_in_same_folder setting
    if (@$_SESSION['settings']['item_duplicate_in_same_folder'] != $_POST['item_duplicate_in_same_folder_input']) {
        updateSettings('item_duplicate_in_same_folder', $_POST['item_duplicate_in_same_folder_input']);
    }
    // Update pwd_maximum_length setting
    if (isset($_SESSION['settings']['pwd_maximum_length']) && $_SESSION['settings']['pwd_maximum_length'] != $_POST['pwd_maximum_length']) {
        updateSettings('pwd_maximum_length', $_POST['pwd_maximum_length']);
    }
    // Update subfolder_rights_as_parent
    if (isset($_SESSION['settings']['subfolder_rights_as_parent']) && $_SESSION['settings']['subfolder_rights_as_parent'] != $_POST['subfolder_rights_as_parent_input']) {
        updateSettings('subfolder_rights_as_parent', $_POST['subfolder_rights_as_parent_input']);
    }
    // Update show_only_accessible_folders
    if (isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] != $_POST['show_only_accessible_folders_input']) {
        updateSettings('show_only_accessible_folders', $_POST['show_only_accessible_folders_input']);
    }
    // Update number_of_used_pw setting
    if (isset($_SESSION['settings']['number_of_used_pw']) && $_SESSION['settings']['number_of_used_pw'] != $_POST['number_of_used_pw']) {
        updateSettings('number_of_used_pw', $_POST['number_of_used_pw']);
    }
    // Update duplicate Manager edit
    if (isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] != $_POST['manager_edit_input']) {
        updateSettings('manager_edit', $_POST['manager_edit_input']);
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
    if (isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] != $_POST['activate_expiration_input']) {
        updateSettings('activate_expiration', $_POST['activate_expiration_input']);
    }
    // Update maintenance mode
    if (@$_SESSION['settings']['maintenance_mode'] != $_POST['maintenance_mode_input']) {
        updateSettings('maintenance_mode', $_POST['maintenance_mode_input']);
    }
    // default_session_expiration_time
    if (@$_SESSION['settings']['default_session_expiration_time'] != $_POST['default_session_expiration_time']) {
        updateSettings('default_session_expiration_time', $_POST['default_session_expiration_time']);
    }
    // Update sts mode
    if ( @$_SESSION['settings']['enable_sts'] != $_POST['enable_sts_input'] ) {
        updateSettings('enable_sts', $_POST['enable_sts_input']);
    }
    // Update sts mode
    if (@$_SESSION['settings']['encryptClientServer'] != $_POST['encryptClientServer_input']) {
        updateSettings('encryptClientServer', $_POST['encryptClientServer_input']);
    }
    // Update send_stats
    if (@$_SESSION['settings']['send_stats'] != $_POST['send_stats_input']) {
        updateSettings('send_stats', $_POST['send_stats_input']);
    }
    // Update get_tp_info
    if (@$_SESSION['settings']['get_tp_info'] != $_POST['get_tp_info_input']) {
        updateSettings('get_tp_info', $_POST['get_tp_info_input']);
    }
    // Update allow_print
    if (@$_SESSION['settings']['allow_print'] != $_POST['allow_print_input']) {
        updateSettings('allow_print', $_POST['allow_print_input']);
    }
    // Update roles_allowed_to_print
    if (@$_SESSION['settings']['roles_allowed_to_print'] != $_POST['roles_allowed_to_print']) {
        updateSettings('roles_allowed_to_print', $_POST['roles_allowed_to_print']);
    }
    // Update allow_import
    if (@$_SESSION['settings']['allow_import'] != $_POST['allow_import_input']) {
        updateSettings('allow_import', $_POST['allow_import_input']);
    }
    // Update show_description
    if (@$_SESSION['settings']['show_description'] != $_POST['show_description_input']) {
        updateSettings('show_description', $_POST['show_description_input']);
    }
    // Update tree_counters
    if (@$_SESSION['settings']['tree_counters'] != $_POST['tree_counters_input']) {
        updateSettings('tree_counters', $_POST['tree_counters_input']);
    }
    // Update item_extra_fields
    if (@$_SESSION['settings']['item_extra_fields'] != $_POST['item_extra_fields_input']) {
        updateSettings('item_extra_fields', $_POST['item_extra_fields_input']);
    }
    // Update LDAP mode
    if (isset($_POST['ldap_mode_input']) && @$_SESSION['settings']['ldap_mode'] != $_POST['ldap_mode_input']) {
        updateSettings('ldap_mode', $_POST['ldap_mode_input']);
    }
    // Update LDAP type
    if (isset($_POST['ldap_type']) && @$_SESSION['settings']['ldap_type'] != $_POST['ldap_type']) {
        updateSettings('ldap_type', $_POST['ldap_type']);
    }
    // Update LDAP ldap_suffix
    if (isset($_POST['ldap_suffix']) && @$_SESSION['settings']['ldap_suffix'] != $_POST['ldap_suffix']) {
        updateSettings('ldap_suffix', $_POST['ldap_suffix']);
    }
    // Update LDAP ldap_domain_dn
    if (isset($_POST['ldap_domain_dn']) && @$_SESSION['settings']['ldap_domain_dn'] != $_POST['ldap_domain_dn']) {
        updateSettings('ldap_domain_dn', $_POST['ldap_domain_dn']);
    }
    // Update LDAP ldap_domain_controler
    if (isset($_POST['ldap_domain_controler']) && @$_SESSION['settings']['ldap_domain_controler'] != $_POST['ldap_domain_controler']) {
        updateSettings('ldap_domain_controler', $_POST['ldap_domain_controler']);
    }
    // Update LDAP ldap_user_attribute
    if (isset($_POST['ldap_user_attribute']) && $_SESSION['settings']['ldap_user_attribute'] != @$_POST['ldap_user_attribute']) {
        updateSettings('ldap_user_attribute', $_POST['ldap_user_attribute']);
    }
    // Update LDAP ssl
    if (isset($_POST['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] != $_POST['ldap_ssl_input']) {
        updateSettings('ldap_ssl', $_POST['ldap_ssl_input']);
    }
    // Update LDAP tls
    if (@$_SESSION['settings']['ldap_tls'] != $_POST['ldap_tls_input']) {
        updateSettings('ldap_tls', $_POST['ldap_tls_input']);
    }
    // Update LDAP ldap_elusers
    if (@$_SESSION['settings']['ldap_elusers'] != $_POST['ldap_elusers_input']) {
        updateSettings('ldap_elusers', $_POST['ldap_elusers_input']);
    }
    // Update LDAP ldap_bind_dn
    if (isset($_POST['ldap_bind_dn']) && @$_SESSION['settings']['ldap_bind_dn'] != $_POST['ldap_bind_dn']) {
        updateSettings('ldap_bind_dn', $_POST['ldap_bind_dn']);
    }
    // Update LDAP ldap_user_attribute
    if (isset($_POST['ldap_user_attribute']) && $_SESSION['settings']['ldap_user_attribute'] != $_POST['ldap_user_attribute']) {
        updateSettings('ldap_user_attribute', $_POST['ldap_user_attribute']);
    }
    // Update LDAP ldap_search_base
    if (isset($_POST['ldap_search_base'])&& @$_SESSION['settings']['ldap_search_base'] != $_POST['ldap_search_base']) {
        updateSettings('ldap_search_base', $_POST['ldap_search_base']);
    }
    // Update LDAP ldap_bind_passwd
    if (isset($_POST['ldap_bind_passwd'])&& @$_SESSION['settings']['ldap_bind_passwd'] != $_POST['ldap_bind_passwd']) {
        updateSettings('ldap_bind_passwd', $_POST['ldap_bind_passwd']);
    }
    // Update anyone_can_modify
    if (@$_SESSION['settings']['anyone_can_modify'] != $_POST['anyone_can_modify_input']) {
        updateSettings('anyone_can_modify', $_POST['anyone_can_modify_input']);
    }
    // Update anyone_can_modify_bydefault
    if (@$_SESSION['settings']['anyone_can_modify_bydefault'] != $_POST['anyone_can_modify_bydefault_input']) {
        updateSettings('anyone_can_modify_bydefault', $_POST['anyone_can_modify_bydefault_input']);
    }
    // Update enable_attachment_encryption
    if (@$_SESSION['settings']['enable_attachment_encryption'] != $_POST['enable_attachment_encryption_input']) {
        updateSettings('enable_attachment_encryption', $_POST['enable_attachment_encryption_input']);
    }
    // Update enable_kb
    if (@$_SESSION['settings']['enable_kb'] != $_POST['enable_kb_input']) {
        updateSettings('enable_kb', $_POST['enable_kb_input']);
    }
    // Update enable_suggestion
    if (@$_SESSION['settings']['enable_suggestion'] != $_POST['enable_suggestion_input']) {
        updateSettings('enable_suggestion', $_POST['enable_suggestion_input']);
    }
    // Update nb_bad_identification
    if (@$_SESSION['settings']['nb_bad_authentication'] != $_POST['nb_bad_authentication']) {
        updateSettings('nb_bad_authentication', $_POST['nb_bad_authentication']);
    }
    // Update restricted_to(if restricted_to is FALSE then restricted_to_roles needs to be FALSE
    if (@$_SESSION['settings']['restricted_to'] != $_POST['restricted_to_input']) {
        updateSettings('restricted_to', $_POST['restricted_to_input']);
        if ($_POST['restricted_to_input'] == 0) {
            updateSettings('restricted_to_roles', 0);
        }
    }
    // Update restricted_to_roles
    if (@$_SESSION['settings']['restricted_to_roles'] != $_POST['restricted_to_roles_input'] && (@$_SESSION['settings']['restricted_to'] == 1 OR $_POST['restricted_to_input'] == 1)) {
        updateSettings('restricted_to_roles', $_POST['restricted_to_roles_input']);
    }
    // Update copy_to_clipboard_small_icons
    if (@$_SESSION['settings']['copy_to_clipboard_small_icons'] != $_POST['copy_to_clipboard_small_icons_input']) {
        updateSettings('copy_to_clipboard_small_icons', $_POST['copy_to_clipboard_small_icons_input']);
    }
    // Update timezone_selection
    if (@$_SESSION['settings']['timezone'] != $_POST['timezone']) {
        updateSettings('timezone', $_POST['timezone']);
    }
    // Update enable_user_can_create_folders
    if (@$_SESSION['settings']['enable_user_can_create_folders'] != $_POST['enable_user_can_create_folders_input']) {
        updateSettings('enable_user_can_create_folders', $_POST['enable_user_can_create_folders_input']);
    }
    // Update enable_send_email_on_user_login
    if (@$_SESSION['settings']['enable_send_email_on_user_login'] != $_POST['enable_send_email_on_user_login_input']) {
        updateSettings('enable_send_email_on_user_login', $_POST['enable_send_email_on_user_login_input']);
    }
    // Update enable_email_notification_on_item_shown
    if (@$_SESSION['settings']['enable_email_notification_on_item_shown'] != $_POST['enable_email_notification_on_item_shown_input']) {
        updateSettings('enable_email_notification_on_item_shown', $_POST['enable_email_notification_on_item_shown_input']);
    }
    // Update enable_email_notification_on_user_pw_change
    if (@$_SESSION['settings']['enable_email_notification_on_user_pw_change'] != $_POST['enable_email_notification_on_user_pw_change_input']) {
        updateSettings('enable_email_notification_on_user_pw_change', $_POST['enable_email_notification_on_user_pw_change_input']);
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
    if (@$_SESSION['settings']['enable_delete_after_consultation'] != $_POST['enable_delete_after_consultation_input']) {
        updateSettings('enable_delete_after_consultation', $_POST['enable_delete_after_consultation_input']);
    }
    // Update enable_personal_saltkey_cookie
    if (@$_SESSION['settings']['enable_personal_saltkey_cookie'] != $_POST['enable_personal_saltkey_cookie_input']) {
        updateSettings('enable_personal_saltkey_cookie', $_POST['enable_personal_saltkey_cookie_input']);
    }
    // Update use_md5_password_as_salt
    if (@$_SESSION['settings']['use_md5_password_as_salt'] != $_POST['use_md5_password_as_salt_input']) {
        updateSettings('use_md5_password_as_salt', $_POST['use_md5_password_as_salt_input']);
    }
    // Update personal_saltkey_cookie_duration
    if (@$_SESSION['settings']['personal_saltkey_cookie_duration'] != $_POST['personal_saltkey_cookie_duration']) {
        updateSettings('personal_saltkey_cookie_duration', $_POST['personal_saltkey_cookie_duration']);
    }
    // Update settings_offline_mode
    if (@$_SESSION['settings']['settings_offline_mode'] != $_POST['settings_offline_mode_input']) {
        updateSettings('settings_offline_mode', $_POST['settings_offline_mode_input']);
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
    if (@$_SESSION['settings']['email_smtp_auth'] != $_POST['email_smtp_auth_input']) {
        updateSettings('email_smtp_auth', $_POST['email_smtp_auth_input']);
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
    // Update email_security
    if (@$_SESSION['settings']['email_security'] != $_POST['email_security']) {
        updateSettings('email_security', $_POST['email_security']);
    }
    // Update email_server_url
    if (@$_SESSION['settings']['email_server_url'] != $_POST['email_server_url']) {
        updateSettings('email_server_url', $_POST['email_server_url']);
    }
    // store backups settings
    if (@$_SESSION['settings']['bck_script_filename'] != $_POST['bck_script_filename']) {
        updateSettings('bck_script_filename', $_POST['bck_script_filename']);
    }
    if (@$_SESSION['settings']['bck_script_path'] != $_POST['bck_script_path']) {
        updateSettings('bck_script_path', $_POST['bck_script_path']);
    }
    if (@$_SESSION['settings']['bck_script_key'] != $_POST['bck_script_key']) {
        updateSettings('bck_script_key', $_POST['bck_script_key']);
    }
    // Update insert_manual_entry_item_history
    if (@$_SESSION['settings']['insert_manual_entry_item_history'] != $_POST['insert_manual_entry_item_history_input']) {
        updateSettings('insert_manual_entry_item_history', $_POST['insert_manual_entry_item_history_input']);
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
    if (@$_SESSION['settings']['upload_imageresize_options'] != $_POST['upload_imageresize_options_input']) {
        updateSettings('upload_imageresize_options', $_POST['upload_imageresize_options_input']);
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
    if (@$_SESSION['settings']['can_create_root_folder'] != $_POST['can_create_root_folder_input']) {
        updateSettings('can_create_root_folder', $_POST['can_create_root_folder_input']);
    }
    // Update syslog_enable
    if (@$_SESSION['settings']['syslog_enable'] != $_POST['syslog_enable_input']) {
        updateSettings('syslog_enable', $_POST['syslog_enable_input']);
    }
    // Update syslog_host
    if (@$_SESSION['settings']['syslog_host'] != $_POST['syslog_host']) {
        updateSettings('syslog_host', $_POST['syslog_host']);
    }
    // Update syslog_port
    if (@$_SESSION['settings']['syslog_port'] != $_POST['syslog_port']) {
        updateSettings('syslog_port', $_POST['syslog_port']);
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
                <li><a href="#tabs-1">'.$LANG['admin_settings_title'].'</a></li>
                <li><a href="#tabs-3">'.$LANG['admin_misc_title'].'</a></li>
                <li><a href="#tabs-7">'.$LANG['admin_upload_title'].'</a></li>
                <li><a href="#tabs-2">'.$LANG['admin_actions_title'].'</a></li>
                <li><a href="#tabs-4">'.$LANG['admin_ldap_menu'].'</a></li>
                <li><a href="#tabs-5">'.$LANG['admin_backups'].'</a></li>
                <li><a href="#tabs-6">'.$LANG['admin_emails'].'</a></li>
                <li><a href="admin.settings_categories.php">'.$LANG['categories'].'</a></li>
                <li><a href="admin.settings_api.php">'.$LANG['admin_api'].'</a></li>
                <li><a href="admin.settings_duo.php">2FA Options</a></li>
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
                        <label for="cpassman_dir">'.$LANG['admin_misc_cpassman_dir'].'</label>
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
                        <label for="cpassman_url">'.$LANG['admin_misc_cpassman_url'].'</label>
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
                        <label for="path_to_upload_folder">'.$LANG['admin_path_to_upload_folder'].'</label>
                        &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_path_to_upload_folder_tip'].'" />
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
                        <label for="url_to_upload_folder">'.$LANG['admin_url_to_upload_folder'].'</label>
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
                        <label for="path_to_files_folder">'.$LANG['admin_path_to_files_folder'].'</label>
                        &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_path_to_files_folder_tip'].'" />
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
                        <label for="url_to_files_folder">'.$LANG['admin_url_to_files_folder'].'</label>
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
                        <label for="favicon">'.$LANG['admin_misc_favicon'].'</label>
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
                        <label for="cpassman_dir">'.$LANG['admin_misc_custom_logo'].'</label>
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
                    <label for="cpassman_dir">'.$LANG['admin_misc_custom_login_text'].'</label>
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
$LANG['settings_maintenance_mode'].'
                      &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_maintenance_mode_tip'].'" />
                  </label>
            </td>
            <td>
                <div class="toggle toggle-modern" id="maintenance_mode" data-toggle-on="', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ? 'true' : 'false', '"></div><input type="hidden" name="maintenance_mode_input" id="maintenance_mode_input" value="', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ? '1' : '0', '" />
              <td>
            </tr>';
// default_session_expiration_time
echo '
            <tr style="margin-bottom:3px">
            <td>
                  <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                  <label>'.$LANG['settings_default_session_expiration_time'].'</label>
            </td>
            <td>
                <input type="text" size="15" id="default_session_expiration_time" name="default_session_expiration_time" value="', isset($_SESSION['settings']['default_session_expiration_time']) ? $_SESSION['settings']['default_session_expiration_time'] : "60", '" class="text ui-widget-content" />
              <td>
            </tr>';
echo '<tr><td colspan="3"><hr></td></tr>';
//Enable SSL STS
echo '
            <tr style="margin-bottom:3px">
                <td>
                      <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                      <label>' .
                          $LANG['settings_enable_sts'] . '
                          &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="' . $LANG['settings_enable_sts_tip'] . '" />
                      </label>
                </td>
                <td>
                    <div class="toggle toggle-modern" id="enable_sts" data-toggle-on="', isset($_SESSION['settings']['enable_sts']) && $_SESSION['settings']['enable_sts'] == 1 ? 'true' : 'false', '"></div><input type="hidden" name="enable_sts_input" id="enable_sts_input" value="', isset($_SESSION['settings']['enable_sts']) && $_SESSION['settings']['enable_sts'] == 1 ? '1' : '0', '" />
                <td>
            </tr>';
//Enable data exchange encryption
echo '
            <tr style="margin-bottom:3px">
                <td>
                      <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                      <label>' .
                          $LANG['settings_encryptClientServer'] . '
                          &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="' . $LANG['settings_encryptClientServer_tip'] . '" />
                      </label>
                </td>
                <td>
                    <div class="toggle toggle-modern" id="encryptClientServer" data-toggle-on="', isset($_SESSION['settings']['encryptClientServer']) && $_SESSION['settings']['encryptClientServer'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="encryptClientServer_input" name="encryptClientServer_input" value="', isset($_SESSION['settings']['encryptClientServer']) && $_SESSION['settings']['encryptClientServer'] == 1 ? '1' : '0', '" />
                <td>
            </tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
//Proxy
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="proxy_ip">'.$LANG['admin_proxy_ip'].'</label>
                    &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_proxy_ip_tip'].'" />
                </td>
                <td>
                    <input type="text" size="15" id="proxy_ip" name="proxy_ip" value="', isset($_SESSION['settings']['proxy_ip']) ? $_SESSION['settings']['proxy_ip'] : "", '" class="text ui-widget-content" />
                <td>
            </tr>
            <tr style="margin-bottom:3px">
                <td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="proxy_port">'.$LANG['admin_proxy_port'].'</label>
                    &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_proxy_port_tip'].'" />
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
                    <label for="pwd_maximum_length">'.$LANG['admin_pwd_maximum_length'].'</label>
                    &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_pwd_maximum_length_tip'].'" />
                </td>
                <td>
                    <input type="text" size="10" id="pwd_maximum_length" name="pwd_maximum_length" value="', isset($_SESSION['settings']['pwd_maximum_length']) ? $_SESSION['settings']['pwd_maximum_length'] : 40, '" class="text ui-widget-content" />
                <td>
            </tr>';
            
echo '<tr><td colspan="3"><hr></td></tr>';
// TIMEZONE
// get list of all timezones
$zones = timezone_identifiers_list();
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="timezone">'.$LANG['timezone_selection'].'</label>
                    </td>
                    <td>
                        <select id="timezone" name="timezone" class="text ui-widget-content">
                            <option value="">-- '.$LANG['select'].' --</option>';
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
                        <label for="date_format">'.$LANG['date_format'].'</label>
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
                        <label for="time_format">'.$LANG['time_format'].'</label>
                    </td>
                    <td>
                        <select id="time_format" name="time_format" class="text ui-widget-content">
                            <option value="H:i:s"', !isset($_SESSION['settings']['time_format']) || $_SESSION['settings']['time_format'] == "H:i:s" ? ' selected="selected"':"", '>H:i:s</option>
                            <option value="h:m:s a"', $_SESSION['settings']['time_format'] == "h:i:s a" ? ' selected="selected"':"", '>h:i:s a</option>
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
                        <label for="default_language">'.$LANG['settings_default_language'].'</label>
                    </td>
                    <td>
                        <select id="default_language" name="default_language" class="text ui-widget-content">
                            <option value="">-- '.$LANG['select'].' --</option>';
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
                        <label for="number_of_used_pw">'.$LANG['number_of_used_pw'].'</label>
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
                        <label for="pw_life_duration">'.$LANG['pw_life_duration'].'</label>
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
                        <label for="nb_bad_authentication">'.$LANG['nb_false_login_attempts'].'</label>
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
                    <label>'.$LANG['settings_log_connections'].'</label>
                    </td>
                    <td>
                        <div class="toggle toggle-modern" id="log_connections" data-toggle-on="', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="log_connections_input" name="log_connections_input" value="', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 ? '1' : '0', '" />
                    </td>
                </tr>';
// Enable log accessed
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_log_accessed'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="log_accessed" data-toggle-on="', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="log_accessed_input" name="log_accessed_input" value="', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1 ? '1' : '0', '" />
                </td>
                </tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable PF
echo '
            <tr><td>
                <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                <label>'.$LANG['enable_personal_folder_feature'].'</label>
                <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['enable_personal_folder_feature_tip'].'" /></span>
            </td><td>
                <div class="toggle toggle-modern" id="enable_pf_feature" data-toggle-on="', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_pf_feature_input" name="enable_pf_feature_input" value="', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? '1' : '0', '" />
            </td></tr>';
// enable Use MD5 passowrd as Personal SALTKEY
echo '
        <tr><td>
            <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
            <label>'.$LANG['use_md5_password_as_salt'].'</label>
        </td><td>
            <div class="toggle toggle-modern" id="use_md5_password_as_salt" data-toggle-on="', isset($_SESSION['settings']['use_md5_password_as_salt']) && $_SESSION['settings']['use_md5_password_as_salt'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="use_md5_password_as_salt_input" name="use_md5_password_as_salt_input" value="', isset($_SESSION['settings']['use_md5_password_as_salt']) && $_SESSION['settings']['use_md5_password_as_salt'] == 1 ? '1' : '0', '" />
        </td></tr>';
// enable PF cookie for Personal SALTKEY
echo '
            <tr><td>
                <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                <label>'.$LANG['enable_personal_saltkey_cookie'].'</label>
            </td><td>
                <div class="toggle toggle-modern" id="enable_personal_saltkey_cookie" data-toggle-on="', isset($_SESSION['settings']['enable_personal_saltkey_cookie']) && $_SESSION['settings']['enable_personal_saltkey_cookie'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_personal_saltkey_cookie_input" name="enable_personal_saltkey_cookie_input" value="', isset($_SESSION['settings']['enable_personal_saltkey_cookie']) && $_SESSION['settings']['enable_personal_saltkey_cookie'] == 1 ? '1' : '0', '" />
            </td></tr>';
// PF cookie for Personal SALTKEY duration
echo '
            <tr><td>
                <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                <label>'.$LANG['personal_saltkey_cookie_duration'].'</label>
            </td><td>
            <div class="div_radio">
                <input type="text" size="10" id="personal_saltkey_cookie_duration" name="personal_saltkey_cookie_duration" value="', isset($_SESSION['settings']['personal_saltkey_cookie_duration']) ? $_SESSION['settings']['personal_saltkey_cookie_duration'] : '31', '" class="text ui-widget-content" />
            </div>
            </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Attachments encryption strategy
echo '
                    <tr><td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>
                            '.$LANG['settings_attachments_encryption'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_attachments_encryption_tip'].'" /></span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="log_accessed" data-toggle-on="', isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_attachment_encryption_input" name="enable_attachment_encryption_input" value="', isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1 ? '1' : '0', '" />
                    </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable KB
echo '
                    <tr><td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>
                            '.$LANG['settings_kb'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_kb_tip'].'" /></span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="enable_kb" data-toggle-on="', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_kb_input" name="enable_kb_input" value="', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1 ? '1' : '0', '" />
                    </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable SUGGESTION
echo '
                    <tr><td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>
                            '.$LANG['settings_suggestion'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_suggestion_tip'].'" /></span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="enable_suggestion" data-toggle-on="', isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_suggestion_input" name="enable_suggestion_input" value="', isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1 ? '1' : '0', '" />
                    </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable send_stats
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>' .
$LANG['settings_send_stats'].'
                            &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_send_stats_tip'].'" />
                        </label>
                    </td>
                    <td>
                        <div class="toggle toggle-modern" id="send_stats" data-toggle-on="', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="send_stats_input" name="send_stats_input" value="', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] == 1 ? '1' : '0', '" />
                    <td>
                </tr>';
// Enable GET TP Information
echo '
                    <tr><td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>
                            '.$LANG['settings_get_tp_info'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_get_tp_info_tip'].'" /></span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="get_tp_info" data-toggle-on="', isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="get_tp_info_input" name="get_tp_info_input" value="', isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] == 1 ? '1' : '0', '" />
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
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_check_pf\')" style="cursor:pointer;">'.$LANG['admin_action_check_pf'].'</a>
                    <span id="result_admin_action_check_pf" style="margin-left:10px;display:none;"><img src="includes/images/tick.png" alt="" /></span>
                </div>';
// Clean DB with orphan items
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_db_clean_items\')" style="cursor:pointer;">'.$LANG['admin_action_db_clean_items'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_db_clean_items_tip'].'" /></span>
                    <span id="result_admin_action_db_clean_items" style="margin-left:10px;"></span>
                </div>';
// Optimize the DB
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_db_optimize\')" style="cursor:pointer;">'.$LANG['admin_action_db_optimize'].'</a>
                    <span id="result_admin_action_db_optimize" style="margin-left:10px;"></span>
                </div>';
// Purge old files
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_purge_old_files\')" style="cursor:pointer;">'.$LANG['admin_action_purge_old_files'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_purge_old_files_tip'].'" /></span>
                    <span id="result_admin_action_purge_old_files" style="margin-left:10px;"></span>
                </div>';
// Reload Cache Table
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_reload_cache_table\')" style="cursor:pointer;">'.$LANG['admin_action_reload_cache_table'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_reload_cache_table_tip'].'" /></span>
                    <span id="result_admin_action_reload_cache_table" style="margin-left:10px;"></span>
                </div>';
// Change main SALT key
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="$(\'#div_change_salt_key\').show()" style="cursor:pointer;">'.$LANG['admin_action_change_salt_key'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_change_salt_key_tip'].'" /></span>
                    <span id="div_change_salt_key" style="margin-left:10px;display:none;">
                        <input type="text" id="new_salt_key" size="50" value="'.SALT.'" /><img src="includes/images/cross.png" id="change_salt_key_image">&nbsp;
                        <img src="includes/images/asterisk.png" alt="" style="cursor:pointer;display:none;" onclick="changeMainSaltKey(\'starting\')" id="change_salt_key_but" />
                        &nbsp;<span id="changeMainSaltKey_message"></span>
                    </span>
                    <input type="hidden" id="changeMainSaltKey_itemsCount" />
                </div>';
// Correct passwords prefix
echo '
                <div style="margin-bottom:3px">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_pw_prefix_correct\')" style="cursor:pointer;">'.$LANG['admin_action_pw_prefix_correct'].'</a>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_pw_prefix_correct_tip'].'" /></span>
                    <span id="result_admin_action_pw_prefix_correct" style="margin-left:10px;"></span>
                </div>';
// Encrypt / decrypt attachments
echo '
                <div style="margin-bottom:3px">
                    <span style="float:left;">
                    <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                    '.$LANG['admin_action_attachments_cryption'].'
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_attachments_cryption_tip'].'" /></span>
                    </span>
                    <div class="div_radio" style="float:left;">
                        <input type="radio" id="attachments_cryption_radio1" name="attachments_cryption" value="encrypt" /><label for="attachments_cryption_radio1">'.$LANG['encrypt'].'</label>
                        <input type="radio" id="attachments_cryption_radio2" name="attachments_cryption" value="decrypt" /><label for="attachments_cryption_radio2">'.$LANG['decrypt'].'</label>
                    </div>
                    <a href="#" onclick="LaunchAdminActions(\'admin_action_attachments_cryption\')" style="cursor:pointer;">'.$LANG['admin_action_db_backup_start_tip'].'</a>
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
                    <label>'.$LANG['settings_delay_for_item_edition'].
    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_delay_for_item_edition_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="5" id="delay_item_edition" name="delay_item_edition" value="', isset($_SESSION['settings']['delay_item_edition']) ? $_SESSION['settings']['delay_item_edition'] : '0', '" class="text ui-widget-content" />
                </td></tr>';
// Expired time for OTV - otv_expiration_period
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_otv_expiration_period'].'</label>
                    </td><td>
                    <input type="text" size="5" id="otv_expiration_period" name="otv_expiration_period" value="', isset($_SESSION['settings']['otv_expiration_period']) ? $_SESSION['settings']['otv_expiration_period'] : '7', '" class="text ui-widget-content" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Managers can edit & delete items they are allowed to see
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_manager_edit'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="manager_edit" data-toggle-on="', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="manager_edit_input" name="manager_edit_input" value="', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// max items
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label for="max_last_items">'.$LANG['max_last_items'].'</label>
                    </td><td>
                    <input type="text" size="4" id="max_last_items" name="max_last_items" value="', isset($_SESSION['settings']['max_latest_items']) ? $_SESSION['settings']['max_latest_items'] : '', '" class="text ui-widget-content" />
                <tr><td>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Duplicate folder
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['duplicate_folder'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="duplicate_folder" data-toggle-on="', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="duplicate_folder_input" name="duplicate_folder_input" value="', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Duplicate item name
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['duplicate_item'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="duplicate_item" data-toggle-on="', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="duplicate_item_input" name="duplicate_item_input" value="', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Duplicate item name in same folder - item_duplicate_in_same_folder
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['duplicate_item_in_folder'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="item_duplicate_in_same_folder" data-toggle-on="', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="item_duplicate_in_same_folder_input" name="item_duplicate_in_same_folder_input" value="', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable show_only_accessible_folders
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['show_only_accessible_folders'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['show_only_accessible_folders_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="show_only_accessible_folders" data-toggle-on="', isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="show_only_accessible_folders_input" name="show_only_accessible_folders_input" value="', isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable subfolder_rights_as_parent
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['subfolder_rights_as_parent'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['subfolder_rights_as_parent_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="subfolder_rights_as_parent" data-toggle-on="', isset($_SESSION['settings']['subfolder_rights_as_parent']) && $_SESSION['settings']['subfolder_rights_as_parent'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="subfolder_rights_as_parent_input" name="subfolder_rights_as_parent_input" value="', isset($_SESSION['settings']['subfolder_rights_as_parent']) && $_SESSION['settings']['subfolder_rights_as_parent'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable extra fields for each Item
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_item_extra_fields'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_item_extra_fields_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="item_extra_fields" data-toggle-on="', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="item_extra_fields_input" name="item_extra_fields_input" value="', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable FAVOURITES
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['enable_favourites'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_favourites" data-toggle-on="', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_favourites_input" name="enable_favourites_input" value="', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable USER can create folders
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['enable_user_can_create_folders'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_user_can_create_folders" data-toggle-on="', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_user_can_create_folders_input" name="enable_user_can_create_folders_input" value="', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable can_create_root_folder
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['setting_can_create_root_folder'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="can_create_root_folder" data-toggle-on="', isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="can_create_root_folder_input" name="can_create_root_folder_input" value="', isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable activate_expiration
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['admin_setting_activate_expiration'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_setting_activate_expiration_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="activate_expiration" data-toggle-on="', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="activate_expiration_input" name="activate_expiration_input" value="', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable enable_delete_after_consultation
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['admin_setting_enable_delete_after_consultation'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_setting_enable_delete_after_consultation_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_delete_after_consultation" data-toggle-on="', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_delete_after_consultation_input" name="enable_delete_after_consultation_input" value="', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable Printing
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_printing'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_printing_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="allow_print" data-toggle-on="', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="allow_print_input" name="allow_print_input" value="', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 ? '1' : '0', '" />
                </td></tr>';
                
// Enable Printing Groups - roles_allowed_to_print
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_roles_allowed_to_print'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_roles_allowed_to_print_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="hidden" id="roles_allowed_to_print" name="roles_allowed_to_print" value="', isset($_SESSION['settings']['roles_allowed_to_print']) ? $_SESSION['settings']['roles_allowed_to_print'] : '', '" />
                    <select id="roles_allowed_to_print_select" name="roles_allowed_to_print_select" class="text ui-widget-content" multiple onblur="refreshInput()">';
                    if (!isset($_SESSION['settings']['roles_allowed_to_print']) || empty($_SESSION['settings']['roles_allowed_to_print'])) {
                        $arrRolesToPrint = array();
                    } else {
                        $arrRolesToPrint = explode(";", $_SESSION['settings']['roles_allowed_to_print']);
                    }
                    $roles = DB::query("SELECT id, title FROM ".prefix_table("roles_title"));
                    foreach ($roles as $role) {
                        echo '<option value="'.$role['id'].'"', in_array($role['id'], $arrRolesToPrint) ? ' selected="selected"' : '', '>'.addslashes($role['title']).'</option>';
                    }
echo '
                        </select>
                </td></tr>';
// Enable IMPORT
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_importing'].'
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="allow_import" data-toggle-on="', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="allow_import_input" name="allow_import_input" value="', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// Enable Item modification by anyone
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_anyone_can_modify'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_anyone_can_modify_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="anyone_can_modify" data-toggle-on="', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="anyone_can_modify_input" name="anyone_can_modify_input" value="', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable Item modification by anyone by default
echo '
                <tr id="tr_option_anyone_can_modify_bydefault"', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '':' style="display:none;"', '><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_anyone_can_modify_bydefault'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="anyone_can_modify_bydefault" data-toggle-on="', isset($_SESSION['settings']['anyone_can_modify_bydefault']) && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="anyone_can_modify_bydefault_input" name="anyone_can_modify_bydefault_input" value="', isset($_SESSION['settings']['anyone_can_modify_bydefault']) && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable restricted_to option
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_restricted_to'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="restricted_to" data-toggle-on="', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="restricted_to_input" name="restricted_to_input" value="', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable restricted_to_roles
echo '
                <tr id="tr_option_restricted_to_roles" style="display:', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? 'inline':'none', ';"><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['restricted_to_roles'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="restricted_to_roles" data-toggle-on="', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="restricted_to_roles_input" name="restricted_to_roles_input" value="', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable show copy to clipboard small icons
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['copy_to_clipboard_small_icons'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['copy_to_clipboard_small_icons_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="copy_to_clipboard_small_icons" data-toggle-on="', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="copy_to_clipboard_small_icons_input" name="copy_to_clipboard_small_icons_input" value="', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable Show description in items list
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_show_description'].'
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="show_description" data-toggle-on="', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="show_description_input" name="show_description_input" value="', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1 ? '1' : '0', '" />
                </td></tr>';
// In Tree, display number of Items in subfolders and number of subfolders - tree_counters
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_tree_counters'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_tree_counters_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="tree_counters" data-toggle-on="', isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="tree_counters_input" name="tree_counters_input" value="', isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1 ? '1' : '0', '" />
                </td></tr>';
// nb of items to display by ajax query
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['nb_items_by_query'].'</label>
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['nb_items_by_query_tip'].'" /></span>
                    </td><td>
                    <input type="text" size="4" id="nb_items_by_query" name="nb_items_by_query" value="', isset($_SESSION['settings']['nb_items_by_query']) ? $_SESSION['settings']['nb_items_by_query'] : '', '" class="text ui-widget-content" />
                <tr><td>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable sending email on USER login
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['enable_send_email_on_user_login'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_send_email_on_user_login" data-toggle-on="', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_send_email_on_user_login_input" name="enable_send_email_on_user_login_input" value="', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable email notification on item shown
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['enable_email_notification_on_item_shown'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_email_notification_on_item_shown" data-toggle-on="', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_email_notification_on_item_shown_input" name="enable_email_notification_on_item_shown_input" value="', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable email notification when user password is changed
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['enable_email_notification_on_user_pw_change'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_email_notification_on_user_pw_change" data-toggle-on="', isset($_SESSION['settings']['enable_email_notification_on_user_pw_change']) && $_SESSION['settings']['enable_email_notification_on_user_pw_change'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_email_notification_on_user_pw_change_input" name="enable_email_notification_on_user_pw_change_input" value="', isset($_SESSION['settings']['enable_email_notification_on_user_pw_change']) && $_SESSION['settings']['enable_email_notification_on_user_pw_change'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr></td></tr>';
// enable add manual entries in History
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_insert_manual_entry_item_history'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_insert_manual_entry_item_history_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="insert_manual_entry_item_history" data-toggle-on="', isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="insert_manual_entry_item_history_input" name="insert_manual_entry_item_history_input" value="', isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1 ? '1' : '0', '" />
                </td></tr>';
echo '<tr><td colspan="3"><hr></td></tr>';
// OffLine mode options
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>
                        '.$LANG['settings_offline_mode'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_offline_mode_tip'].'" /></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="settings_offline_mode" data-toggle-on="', isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="settings_offline_mode_input" name="settings_offline_mode_input" value="', isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 ? '1' : '0', '" />
                </td></tr>';
// OffLne KEy Level
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label for="offline_key_level">'.$LANG['offline_mode_key_level'].'</label>
                    </td>
                    <td>
                        <select id="offline_key_level" name="offline_key_level" class="text ui-widget-content">';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '<option value="'.$complex[0].'"', isset($_SESSION['settings']['offline_key_level']) && $_SESSION['settings']['offline_key_level'] == $complex[0] ? ' selected="selected"' : '', '>'.$complex[1].'</option>';
}
echo '
                        </select>
                    <td>
                </tr>';
// SYSLOG ENABLE
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['syslog_enable'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="syslog_enable" data-toggle-on="', isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="syslog_enable_input" name="syslog_enable_input" value="', isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1 ? '1' : '0', '" />
                </td></tr>';
// SYSLOG Host
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['syslog_host'].'
                        </td>
                        <td>
                            <input id="syslog_server" name="syslog_host" type="text" size="40px" value="', !isset($_SESSION['settings']['syslog_host']) ? 'localhost' : $_SESSION['settings']['syslog_host'], '" />
                        </td>
                    </tr>';
// SYSLOG port
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['syslog_port'].'
                        </td>
                        <td>
                            <input id="syslog_port" name="syslog_port" type="text" size="40px" value="', !isset($_SESSION['settings']['syslog_port']) ? '514' : $_SESSION['settings']['syslog_port'], '" />
                        </td>
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
            <img src="includes/images/error.png" alt="">&nbsp;&nbsp;'.$LANG['ldap_extension_not_loaded'].'
        </div>
    </div>';
} else {
    // Enable LDAP mode
    echo '
    <div style="margin-bottom:3px;">
        <label for="ldap_mode">'.$LANG['settings_ldap_mode'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_ldap_mode_tip'].'" /></label>
		<div class="toggle toggle-modern" id="ldap_mode" data-toggle-on="', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="ldap_mode_input" name="ldap_mode_input" value="', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? '1' : '0', '" />
	</div>';
    // Type
    $ldap_type = isset($_SESSION['settings']['ldap_type']) ? $_SESSION['settings']['ldap_type'] : '';
    echo '
<div style="margin-bottom:3px;">
    <label for="ldap_type">'.$LANG['settings_ldap_type'].'</label>
    <select id="ldap_type" name="ldap_type" class="text ui-widget-content">
        <option value="windows">Windows / Active Directory</option>
        <option value="posix"', $ldap_type == 'posix' ? ' selected="selected"' : '', '>Posix / OpenLDAP (RFC2307)</option>
        <option value="posix-search"', $ldap_type == 'posix-search' ? ' selected="selected"' : '', '>Posix / OpenLDAP (RFC2307) Search Based</option>
    </select>
</div>';
}
// LDAP inputs
echo '
            <div id="div_ldap_configuration" ', (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1) ? '':' style="display:none;"' , '>
                <div style="font-weight:bold;font-size:14px;margin:15px 0px 8px 0px;">'.$LANG['admin_ldap_configuration'].'</div>
                <table>';
// Domain
if (isset($ldap_type) && $ldap_type != 'posix' && $ldap_type != 'posix-search') {
echo '
                    <tr>
                        <td><label for="ldap_suffix">'.$LANG['settings_ldap_domain'].'</label></td>
                        <td><input type="text" size="50" id="ldap_suffix" name="ldap_suffix" class="text ui-widget-content" title="@dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_suffix']) ? $_SESSION['settings']['ldap_suffix'] : '', '" /></td>
                    </tr>';
}

if (isset($ldap_type) && $ldap_type != 'posix-search') {
// Domain DN
echo '
                    <tr>
                        <td><label for="ldap_domain_dn">'.$LANG['settings_ldap_domain_dn'].'</label></td>
                        <td><input type="text" size="50" id="ldap_domain_dn" name="ldap_domain_dn" class="text ui-widget-content" title="dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_domain_dn']) ? $_SESSION['settings']['ldap_domain_dn'] : '', '" /></td>
                    </tr>';
}

// Subtree for posix / openldap
if (isset($ldap_type) && $ldap_type == 'posix') {
        echo '
                <tr>
                    <td><label for="ldap_suffix">'.$LANG['settings_ldap_domain_posix'].'</label></td>
                    <td><input type="text" size="50" id="ldap_suffix" name="ldap_suffix" class="text ui-widget-content" title="@dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_suffix']) ? $_SESSION['settings']['ldap_suffix'] : '', '" /></td>
                </tr>';
}

// LDAP username attribute
if (isset($ldap_type) && $ldap_type == 'posix-search') {
        echo '
                <tr>
                    <td><label for="ldap_user_attribute">'.$LANG['settings_ldap_user_attribute'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.
                        $LANG['settings_ldap_user_attribute_tip'].'" /></label></td>
                    <td><input type="text" size="50" id="ldap_user_attribute" name="ldap_user_attribute" class="text ui-widget-content" title="uid" value="',
                        isset($_SESSION['settings']['ldap_user_attribute']) ? $_SESSION['settings']['ldap_user_attribute'] : 'uid', '" /></td>
                </tr>';
                // LDAP BIND DN for search
                echo '
                <tr>
                    <td><label for="ldap_bind_dn">'.$LANG['settings_ldap_bind_dn'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_ldap_bind_dn_tip'].'" /></label></td>
                    <td><input type="text" size="50" id="ldap_bind_dn" name="ldap_bind_dn" class="text ui-widget-content" title="dc01.mydomain.local,dc02.mydomain.local" value="', isset($_SESSION['settings']['ldap_bind_dn']) ? $_SESSION['settings']['ldap_bind_dn'] : '', '" /></td>
                </tr>';
                // LDAP BIND PASSWD for search
                echo '
                <tr>
                    <td><label for="ldap_bind_passwd">'.$LANG['settings_ldap_bind_passwd'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_ldap_bind_passwd_tip'].'" /></label></td>
                    <td><input type="text" size="50" id="ldap_bind_passwd" name="ldap_bind_passwd" class="text ui-widget-content" title="dc01.mydomain.local,dc02.mydomain.local" value="', isset($_SESSION['settings']['ldap_bind_passwd']) ? $_SESSION['settings']['ldap_bind_passwd'] : '', '" /></td>
                </tr>';
                // LDAP BASE for search
                echo '
                <tr>
                    <td><label for="ldap_search_base">'.$LANG['settings_ldap_search_base'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_ldap_search_base_tip'].'" /></label></td>
                    <td><input type="text" size="50" id="ldap_search_base" name="ldap_search_base" class="text ui-widget-content" title="dc01.mydomain.local,dc02.mydomain.local" value="', isset($_SESSION['settings']['ldap_search_base']) ? $_SESSION['settings']['ldap_search_base'] : '', '" /></td>
                </tr>';
}

// Domain controler
echo '
                    <tr>
                        <td><label for="ldap_domain_controler">'.$LANG['settings_ldap_domain_controler'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_ldap_domain_controler_tip'].'" /></label></td>
                        <td><input type="text" size="50" id="ldap_domain_controler" name="ldap_domain_controler" class="text ui-widget-content" title="dc01.mydomain.local,dc02.mydomain.local" value="', isset($_SESSION['settings']['ldap_domain_controler']) ? $_SESSION['settings']['ldap_domain_controler'] : '', '" /></td>
                    </tr>';

// AD SSL
echo '
                    <tr>
                        <td><label>'.$LANG['settings_ldap_ssl'].'</label></td>
                        <td>
                            <div class="toggle toggle-modern" id="ldap_ssl" data-toggle-on="', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="ldap_ssl_input" name="ldap_ssl_input" value="', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] == 1 ? '1' : '0', '" />
                        </td>
                    </tr>';
// AD TLS
echo '
                    <tr>
                        <td><label>'.$LANG['settings_ldap_tls'].'</label></td>
                        <td>
                            <div class="toggle toggle-modern" id="ldap_tls" data-toggle-on="', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="ldap_tls_input" name="ldap_tls_input" value="', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] == 1 ? '1' : '0', '" />
                        </td>
                    </tr>';
// Enable only localy declared users with tips help
echo '
                    <tr>
                        <td><label>'.$LANG['settings_ldap_elusers'].'&nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_ldap_elusers_tip'].'" /></label></td>
                        <td>
                            <div class="toggle toggle-modern" id="ldap_elusers" data-toggle-on="', isset($_SESSION['settings']['ldap_elusers']) && $_SESSION['settings']['ldap_elusers'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="ldap_elusers_input" name="ldap_elusers_input" value="', isset($_SESSION['settings']['ldap_elusers']) && $_SESSION['settings']['ldap_elusers'] == 1 ? '1' : '0', '" />
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
                    <b>'.$LANG['admin_one_shot_backup'].'</b>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// Backup the DB
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                        '.$LANG['admin_action_db_backup'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_db_backup_tip'].'" /></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_backup" style="margin-left:10px;"></span>
                        <span id="result_admin_action_db_backup_get_key" style="margin-left:10px;">
                            &nbsp;'.$LANG['encrypt_key'].'<input type="password" size="20" id="result_admin_action_db_backup_key" />
                            <img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_db_backup_key_tip'].'" />
                            <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$LANG['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_action_db_backup\')" style="cursor:pointer;" />
                        </span>
                        </td>
                    </tr>';
// Restore the DB
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                        '.$LANG['admin_action_db_restore'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['admin_action_db_restore_tip'].'" /></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <div id="upload_container_restoreDB">
                            <div id="filelist_restoreDB"></div><br />
                            <a id="pickfiles_restoreDB" class="button" href="#">'.$LANG['select'].'</a>
                        </div>
                        </td>
                    </tr>';

echo '
                    </table>
                </div>';

echo '
                <div class="" style="0padding: 0 .7em;">
                    <span class="ui-icon ui-icon-transferthick-e-w" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <b>'.$LANG['admin_script_backups'].'</b>&nbsp;
                    <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_script_backups_tip'].'</h2>" /></span>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// Backups script path
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                        '.$LANG['admin_script_backup_path'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_script_backup_path_tip'].'</h2>" /></span>
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
                        '.$LANG['admin_script_backup_filename'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_script_backup_filename_tip'].'</h2>" /></span>
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
                        '.$LANG['admin_script_backup_encryption'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_script_backup_encryption_tip'].'</h2>" /></span>
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
                        '.$LANG['admin_script_backup_decrypt'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_script_backup_decrypt_tip'].'</h2>" /></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_decrypt_file" name="bck_script_decrypt_file" type="text" size="50px" value="" />
                        <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$LANG['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_action_backup_decrypt\')" style="cursor:pointer;" />
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
                    <b>'.$LANG['admin_emails_configuration'].'</b>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// SMTP server
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['admin_email_smtp_server'].'
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
                            '.$LANG['admin_email_auth'].'
                        </td>
                        <td>
                            <div class="toggle toggle-modern" id="email_smtp_auth" data-toggle-on="', isset($_SESSION['settings']['email_smtp_auth']) && $_SESSION['settings']['email_smtp_auth'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="email_smtp_auth_input" name="email_smtp_auth_input" value="', isset($_SESSION['settings']['email_smtp_auth']) && $_SESSION['settings']['email_smtp_auth'] == 1 ? '1' : '0', '" />
                        </td>
                    </tr>';
// SMTP auth username
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['admin_email_auth_username'].'
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
                            '.$LANG['admin_email_auth_pwd'].'
                        </td>
                        <td>
                            <input id="email_auth_pwd" name="email_auth_pwd" type="password" size="40px" value="', !isset($_SESSION['settings']['email_auth_pwd']) ? $smtp_auth_password : $_SESSION['settings']['email_auth_pwd'], '" />
                        </td>
                    </tr>';
// SMTP server url
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['admin_email_server_url'].'
                        <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_email_server_url_tip'].'</h2>" /></span>
                        </td>
                        <td>
                            <input id="email_server_url" name="email_server_url" type="text" size="40px" value="', !isset($_SESSION['settings']['email_server_url']) ? $_SESSION['settings']['cpassman_url'] : $_SESSION['settings']['email_server_url'], '" />
                        </td>
                    </tr>';
// SMTP port
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['admin_email_port'].'
                        </td>
                        <td>
                            <input id="email_port" name="email_port" type="text" size="40px" value="', !isset($_SESSION['settings']['email_port']) ? '25' : $_SESSION['settings']['email_port'], '" />
                        </td>
                    </tr>';
// SMTP security
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['admin_email_security'].'
                        </td>
                        <td>
                            <select id="email_security" name="email_security" class="text ui-widget-content">
                            <option value="none"', !isset($_SESSION['settings']['email_security']) || $_SESSION['settings']['email_security'] == "none" ? ' selected="selected"':"", '>None</option>
                            <option value="ssl"', isset($_SESSION['settings']['email_security']) && $_SESSION['settings']['email_security'] == "ssl" ? ' selected="selected"':"", '>SSL</option>
                            <option value="tls"', isset($_SESSION['settings']['email_security']) && $_SESSION['settings']['email_security'] == "tls" ? ' selected="selected"':"", '>TLS</option>
                        </select>
                        </td>
                    </tr>';
// SMTP from
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['admin_email_from'].'
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
                            '.$LANG['admin_email_from_name'].'
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
                    <b>'.$LANG['admin_emails_configuration_testing'].'</b>
                </div>
                <div id="email_testing_results" class="ui-state-error ui-corner-all" style="padding:5px;display:none;margin:2px;"></div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// Test email configuration
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$LANG['admin_email_test_configuration'].'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_email_test_configuration_tip'].'</h2>" /></span>
                        </td>
                        <td>
                            <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$LANG['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_email_test_configuration\')" style="cursor:pointer;" />
                        </td>
                    </tr>';
// Send emails backlog
DB::query("SELECT * FROM ".prefix_table("emails")." WHERE status = %s OR status = %s", 'not_sent', '');
$nb_emails = DB::count();
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <span class="ui-icon ui-icon-gear" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.str_replace("#nb_emails#", $nb_emails, $LANG['admin_email_send_backlog']).'
                            <span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" style="font-size:11px;" title="<h2>'.$LANG['admin_email_send_backlog_tip'].'</h2>" /></span>
                        </td>
                        <td>
                            <img src="includes/images/asterisk.png" class="tip" alt="" title="'.$LANG['admin_action_db_backup_start_tip'].'" onclick="LaunchAdminActions(\'admin_email_send_backlog\')" style="cursor:pointer;" />
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
                    <label>'.$LANG['settings_upload_maxfilesize'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_upload_maxfilesize_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="5" id="upload_maxfilesize" name="upload_maxfilesize" value="', isset($_SESSION['settings']['upload_maxfilesize']) ? $_SESSION['settings']['upload_maxfilesize'] : '10', '" class="text ui-widget-content" /></div>
                </td></tr>';
// Extension for Documents
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_upload_docext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_upload_docext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_docext" name="upload_docext" value="', isset($_SESSION['settings']['upload_docext']) ? $_SESSION['settings']['upload_docext'] : 'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx', '" class="text ui-widget-content" /></div>
                </td></tr>';
// Extension for Images
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_upload_imagesext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_upload_imagesext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_imagesext" name="upload_imagesext" value="', isset($_SESSION['settings']['upload_imagesext']) ? $_SESSION['settings']['upload_imagesext'] : 'jpg,jpeg,gif,png', '" class="text ui-widget-content" /></div>
                </td></tr>';
// Extension for Packages
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_upload_pkgext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_upload_pkgext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_pkgext" name="upload_pkgext" value="', isset($_SESSION['settings']['upload_pkgext']) ? $_SESSION['settings']['upload_pkgext'] : '7z,rar,tar,zip', '" class="text ui-widget-content" /></div>
                </td></tr>';
// Extension for Other
echo '
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_upload_otherext'].
                    '<span style="margin-left:0px;"><img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_upload_otherext_tip'].'" /></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_otherext" name="upload_otherext" value="', isset($_SESSION['settings']['upload_otherext']) ? $_SESSION['settings']['upload_otherext'] : 'sql,xml', '" class="text ui-widget-content" /></div>
                </td></tr>';
echo '<tr><td colspan="3"><hr></td></tr>';
// Image resize width / height / quality
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <span class="ui-icon ui-icon-disk" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <label>' .
                        $LANG['settings_upload_imageresize_options'].'
                        &nbsp;<img src="includes/images/question-small-white.png" class="tip" alt="" title="'.$LANG['settings_upload_imageresize_options_tip'].'" />
                        </label>
                    </td>
                    <td>
                        <div class="toggle toggle-modern" id="upload_imageresize_options" data-toggle-on="', isset($_SESSION['settings']['upload_imageresize_options']) && $_SESSION['settings']['upload_imageresize_options'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="upload_imageresize_options_input" name="upload_imageresize_options_input" value="', isset($_SESSION['settings']['upload_imageresize_options']) && $_SESSION['settings']['upload_imageresize_options'] == 1 ? '1' : '0', '" />
                    </td>
                </tr>
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_upload_imageresize_options_w'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_width" name="upload_imageresize_width" value="',
                        isset($_SESSION['settings']['upload_imageresize_width']) ? $_SESSION['settings']['upload_imageresize_width'] :
                        '800', '" class="text ui-widget-content upl_img_opt" />
                    <td>
                </tr>
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_upload_imageresize_options_h'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_height" name="upload_imageresize_height" value="',
                        isset($_SESSION['settings']['upload_imageresize_height']) ? $_SESSION['settings']['upload_imageresize_height'] :
                        '600', '" class="text ui-widget-content upl_img_opt" />
                    <td>
                </tr>
                <tr><td>
                    <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                    <label>'.$LANG['settings_upload_imageresize_options_q'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_quality" name="upload_imageresize_quality" value="',
                        isset($_SESSION['settings']['upload_imageresize_quality']) ? $_SESSION['settings']['upload_imageresize_quality'] :
                        '90', '" class="text ui-widget-content upl_img_opt" />
                </td></tr>';
echo '
                <tr><td colspan="3"><hr></td></tr>';
echo '
                </table>
            </div>';
// --------------------------------------------------------------------------------

// Save button
echo '
            <div style="margin:auto;">
                <input type="submit" id="save_button" name="save_button" value="'.$LANG['save_button'].'" />
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
