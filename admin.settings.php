<?php
/**
 *
 * @file          admin.settings.php
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

echo '
<input type="hidden" id="user_token" value="" />
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
                <li><a href="admin.settings_duo.php">'.$LANG['admin_2factor_authentication_tab'].'</a></li>
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
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="cpassman_dir">'.$LANG['admin_misc_cpassman_dir'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="cpassman_dir" name="cpassman_dir" value="', isset($_SESSION['settings']['cpassman_dir']) ? $_SESSION['settings']['cpassman_dir'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// cpassman_url
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="cpassman_url">'.$LANG['admin_misc_cpassman_url'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="cpassman_url" name="cpassman_url" value="', isset($_SESSION['settings']['cpassman_url']) ? $_SESSION['settings']['cpassman_url'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// path_to_upload_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="path_to_upload_folder">'.$LANG['admin_path_to_upload_folder'].'</label>
                        &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_path_to_upload_folder_tip']), ENT_QUOTES).'"></i>
                    </td>
                    <td>
                        <input type="text" size="80" id="path_to_upload_folder" name="path_to_upload_folder" value="', isset($_SESSION['settings']['path_to_upload_folder']) ? $_SESSION['settings']['path_to_upload_folder'] : $_SESSION['settings']['cpassman_dir'].'/upload', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// url_to_upload_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="url_to_upload_folder">'.$LANG['admin_url_to_upload_folder'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="url_to_upload_folder" name="url_to_upload_folder" value="', isset($_SESSION['settings']['url_to_upload_folder']) ? $_SESSION['settings']['url_to_upload_folder'] : $_SESSION['settings']['cpassman_url'].'/upload', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// path_to_files_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="path_to_files_folder">'.$LANG['admin_path_to_files_folder'].'</label>
                        &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_path_to_files_folder_tip']), ENT_QUOTES).'"></i>
                    </td>
                    <td>
                        <input type="text" size="80" id="path_to_files_folder" name="path_to_files_folder" value="', isset($_SESSION['settings']['path_to_files_folder']) ? $_SESSION['settings']['path_to_files_folder'] : $_SESSION['settings']['cpassman_dir'].'/files', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// url_to_files_folder
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="url_to_files_folder">'.$LANG['admin_url_to_files_folder'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="url_to_files_folder" name="url_to_files_folder" value="', isset($_SESSION['settings']['url_to_files_folder']) ? $_SESSION['settings']['url_to_files_folder'] : $_SESSION['settings']['cpassman_url'].'/files', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// Favicon
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="favicon">'.$LANG['admin_misc_favicon'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="favicon" name="favicon" value="', isset($_SESSION['settings']['favicon']) ? $_SESSION['settings']['favicon'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// custom_logo
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="cpassman_dir">'.$LANG['admin_misc_custom_logo'].'</label>
                    </td>
                    <td>
                        <input type="text" size="80" id="custom_logo" name="custom_logo" value="', isset($_SESSION['settings']['custom_logo']) ? $_SESSION['settings']['custom_logo'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// custom_login_text
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label for="cpassman_dir">'.$LANG['admin_misc_custom_login_text'].'</label>
                </td>
                <td>
                    <input type="text" size="80" id="custom_login_text" name="custom_login_text" value="', isset($_SESSION['settings']['custom_login_text']) ? $_SESSION['settings']['custom_login_text'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td>
            </tr>';

echo '
            </table>';

echo '
            <table>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Maintenance mode
echo '
            <tr style="margin-bottom:3px">
            <td>
                  <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                  <label>' .
$LANG['settings_maintenance_mode'].'
                      &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_maintenance_mode_tip']), ENT_QUOTES).'"></i>
                  </label>
            </td>
            <td>
                <div class="toggle toggle-modern" id="maintenance_mode" data-toggle-on="', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ? 'true' : 'false', '"></div><input type="hidden" name="maintenance_mode_input" id="maintenance_mode_input" value="', isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 ? '1' : '0', '" />
            </td>
            </tr>';
// default_session_expiration_time
echo '
            <tr style="margin-bottom:3px">
            <td>
                  <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                  <label>'.$LANG['settings_default_session_expiration_time'].'</label>
            </td>
            <td>
                <input type="text" size="15" id="default_session_expiration_time" name="default_session_expiration_time" value="', isset($_SESSION['settings']['default_session_expiration_time']) ? $_SESSION['settings']['default_session_expiration_time'] : "60", '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
             </td>
            </tr>';
echo '<tr><td colspan="3"><hr /></td></tr>';
//Enable SSL STS
echo '
            <tr style="margin-bottom:3px">
                <td>
                      <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                      <label>' .
                          $LANG['settings_enable_sts'] . '
                          &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_enable_sts_tip']), ENT_QUOTES).'"></i>
                      </label>
                </td>
                <td>
                    <div class="toggle toggle-modern" id="enable_sts" data-toggle-on="', isset($_SESSION['settings']['enable_sts']) && $_SESSION['settings']['enable_sts'] == 1 ? 'true' : 'false', '"></div><input type="hidden" name="enable_sts_input" id="enable_sts_input" value="', isset($_SESSION['settings']['enable_sts']) && $_SESSION['settings']['enable_sts'] == 1 ? '1' : '0', '" />
                </td>
            </tr>';
//Enable data exchange encryption
/*
echo '
            <tr style="margin-bottom:3px">
                <td>
                      <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                      <label>' .
                          $LANG['settings_encryptClientServer'] . '
                          &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_encryptClientServer_tip']), ENT_QUOTES).'"></i>
                      </label>
                </td>
                <td>
                    <div class="toggle toggle-modern" id="encryptClientServer" data-toggle-on="', isset($_SESSION['settings']['encryptClientServer']) && $_SESSION['settings']['encryptClientServer'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="encryptClientServer_input" name="encryptClientServer_input" value="', isset($_SESSION['settings']['encryptClientServer']) && $_SESSION['settings']['encryptClientServer'] == 1 ? '1' : '0', '" />
                </td>
            </tr>';
*/
echo '<tr><td colspan="3"><hr /></td></tr>';
//Proxy
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label for="proxy_ip">'.$LANG['admin_proxy_ip'].'</label>
                    &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_proxy_ip_tip']), ENT_QUOTES).'"></i>
                </td>
                <td>
                    <input type="text" size="15" id="proxy_ip" name="proxy_ip" value="', isset($_SESSION['settings']['proxy_ip']) ? $_SESSION['settings']['proxy_ip'] : "", '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td>
            </tr>
            <tr style="margin-bottom:3px">
                <td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label for="proxy_port">'.$LANG['admin_proxy_port'].'</label>
                    &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_proxy_port_tip']), ENT_QUOTES).'"></i>
                </td>
                <td>
                    <input type="text" size="10" id="proxy_port" name="proxy_port" value="', isset($_SESSION['settings']['proxy_port']) ? $_SESSION['settings']['proxy_port'] : "", '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td>
            </tr>';


echo '<tr><td colspan="3"><hr /></td></tr>';
// pwd_maximum_length
echo '
            <tr style="margin-bottom:3px">
                <td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label for="pwd_maximum_length">'.$LANG['admin_pwd_maximum_length'].'</label>
                    &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_pwd_maximum_length_tip']), ENT_QUOTES).'"></i>
                </td>
                <td>
                    <input type="text" size="10" id="pwd_maximum_length" name="pwd_maximum_length" value="', isset($_SESSION['settings']['pwd_maximum_length']) ? $_SESSION['settings']['pwd_maximum_length'] : 40, '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td>
            </tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// TIMEZONE
// get list of all timezones
$zones = timezone_identifiers_list();
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="timezone">'.$LANG['timezone_selection'].'</label>
                    </td>
                    <td>
                        <select id="timezone" name="timezone" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));">
                            <option value="">-- '.$LANG['select'].' --</option>';
foreach ($zones as $zone) {
    echo '
    <option value="'.$zone.'"', isset($_SESSION['settings']['timezone']) && $_SESSION['settings']['timezone'] == $zone ? ' selected="selected"' : '', '>'.$zone.'</option>';
}
echo '
                        </select>
                    </td>
                </tr>';
// DATE format
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="date_format">'.$LANG['date_format'].'</label>
                    </td>
                    <td>
                        <select id="date_format" name="date_format" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));">
                            <option value="d/m/Y"', !isset($_SESSION['settings']['date_format']) || $_SESSION['settings']['date_format'] == "d/m/Y" ? ' selected="selected"':"", '>d/m/Y</option>
                            <option value="m/d/Y"', $_SESSION['settings']['date_format'] == "m/d/Y" ? ' selected="selected"':"", '>m/d/Y</option>
                            <option value="d-M-Y"', $_SESSION['settings']['date_format'] == "d-M-Y" ? ' selected="selected"':"", '>d-M-Y</option>
                            <option value="d/m/y"', $_SESSION['settings']['date_format'] == "d/m/y" ? ' selected="selected"':"", '>d/m/y</option>
                            <option value="m/d/y"', $_SESSION['settings']['date_format'] == "m/d/y" ? ' selected="selected"':"", '>m/d/y</option>
                            <option value="d-M-y"', $_SESSION['settings']['date_format'] == "d-M-y" ? ' selected="selected"':"", '>d-M-y</option>
                            <option value="d-m-y"', $_SESSION['settings']['date_format'] == "d-m-y" ? ' selected="selected"':"", '>d-m-y</option>
                            <option value="Y-m-d"', $_SESSION['settings']['date_format'] == "Y-m-d" ? ' selected="selected"':"", '>Y-m-d</option>
                        </select>
                    </td>
                </tr>';
// TIME format
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="time_format">'.$LANG['time_format'].'</label>
                    </td>
                    <td>
                        <select id="time_format" name="time_format" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));">
                            <option value="H:i:s"', !isset($_SESSION['settings']['time_format']) || $_SESSION['settings']['time_format'] == "H:i:s" ? ' selected="selected"':"", '>H:i:s</option>
                            <option value="h:m:s a"', $_SESSION['settings']['time_format'] == "h:i:s a" ? ' selected="selected"':"", '>h:i:s a</option>
                            <option value="g:i:s a"', $_SESSION['settings']['time_format'] == "g:i:s a" ? ' selected="selected"':"", '>g:i:s a</option>
                            <option value="G:i:s"', $_SESSION['settings']['time_format'] == "G:i:s" ? ' selected="selected"':"", '>G:i:s</option>
                        </select>
                    </td>
                </tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// LANGUAGES
$zones = timezone_identifiers_list();
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="default_language">'.$LANG['settings_default_language'].'</label>
                    </td>
                    <td>
                        <select id="default_language" name="default_language" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));">
                            <option value="">-- '.$LANG['select'].' --</option>';
foreach ($languagesList as $lang) {
    echo '
    <option value="'.$lang.'"', isset($_SESSION['settings']['default_language']) && $_SESSION['settings']['default_language'] == $lang ? ' selected="selected"' : '', '>'.$lang.'</option>';
}
echo '
                        </select>
                    </td>
                </tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Number of used pw
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="number_of_used_pw">'.$LANG['number_of_used_pw'].'</label>
                    </td>
                    <td>
                        <input type="text" size="10" id="number_of_used_pw" name="number_of_used_pw" value="', isset($_SESSION['settings']['number_of_used_pw']) ? $_SESSION['settings']['number_of_used_pw'] : '5', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// Number days before changing pw
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="pw_life_duration">'.$LANG['pw_life_duration'].'</label>
                    </td>
                    <td>
                        <input type="text" size="10" id="pw_life_duration" name="pw_life_duration" value="', isset($_SESSION['settings']['pw_life_duration']) ? $_SESSION['settings']['pw_life_duration'] : '5', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';
// Number of bad authentication tentations before disabling user
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="nb_bad_authentication">'.$LANG['nb_false_login_attempts'].'</label>
                    </td>
                    <td>
                        <input type="text" size="10" id="nb_bad_authentication" name="nb_bad_authentication" value="', isset($_SESSION['settings']['nb_bad_authentication']) ? $_SESSION['settings']['nb_bad_authentication'] : '0', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Enable log connections
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_log_connections'].'</label>
                    </td>
                    <td>
                        <div class="toggle toggle-modern" id="log_connections" data-toggle-on="', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="log_connections_input" name="log_connections_input" value="', isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 ? '1' : '0', '" />
                    </td>
                </tr>';
// Enable log accessed
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_log_accessed'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="log_accessed" data-toggle-on="', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="log_accessed_input" name="log_accessed_input" value="', isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1 ? '1' : '0', '" />
                </td>
                </tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// enable PF
echo '
            <tr><td>
                <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                <label>'.$LANG['enable_personal_folder_feature'].'</label>
                <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['enable_personal_folder_feature_tip']), ENT_QUOTES).'"></i></span>
            </td><td>
                <div class="toggle toggle-modern" id="enable_pf_feature" data-toggle-on="', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_pf_feature_input" name="enable_pf_feature_input" value="', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? '1' : '0', '" />
            </td></tr>';
// enable Use MD5 passowrd as Personal SALTKEY
/* DISABLED FOR 2.1.27
echo '
        <tr><td>
            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
            <label>'.$LANG['use_md5_password_as_salt'].'</label>
        </td><td>
            <div class="toggle toggle-modern" id="use_md5_password_as_salt" data-toggle-on="', isset($_SESSION['settings']['use_md5_password_as_salt']) && $_SESSION['settings']['use_md5_password_as_salt'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="use_md5_password_as_salt_input" name="use_md5_password_as_salt_input" value="', isset($_SESSION['settings']['use_md5_password_as_salt']) && $_SESSION['settings']['use_md5_password_as_salt'] == 1 ? '1' : '0', '" />
        </td></tr>';
*/
// enable PF cookie for Personal SALTKEY
echo '
            <tr><td>
                <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                <label>'.$LANG['enable_personal_saltkey_cookie'].'</label>
            </td><td>
                <div class="toggle toggle-modern" id="enable_personal_saltkey_cookie" data-toggle-on="', isset($_SESSION['settings']['enable_personal_saltkey_cookie']) && $_SESSION['settings']['enable_personal_saltkey_cookie'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_personal_saltkey_cookie_input" name="enable_personal_saltkey_cookie_input" value="', isset($_SESSION['settings']['enable_personal_saltkey_cookie']) && $_SESSION['settings']['enable_personal_saltkey_cookie'] == 1 ? '1' : '0', '" />
            </td></tr>';
// PF cookie for Personal SALTKEY duration
echo '
            <tr><td>
                <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                <label>'.$LANG['personal_saltkey_cookie_duration'].'</label>
            </td><td>
            <div class="div_radio">
                <input type="text" size="10" id="personal_saltkey_cookie_duration" name="personal_saltkey_cookie_duration" value="', isset($_SESSION['settings']['personal_saltkey_cookie_duration']) ? $_SESSION['settings']['personal_saltkey_cookie_duration'] : '31', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
            </div>
            </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Attachments encryption strategy
echo '
                    <tr><td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label>
                            '.$LANG['settings_attachments_encryption'].'
                            <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_attachments_encryption_tip']), ENT_QUOTES).'"></i>&nbsp;</span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="enable_attachment_encryption" data-toggle-on="', isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_attachment_encryption_input" name="enable_attachment_encryption_input" value="', isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1 ? '1' : '0', '" />
                    </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Enable KB
echo '
                    <tr><td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label>
                            '.$LANG['settings_kb'].'
                            <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_kb_tip']), ENT_QUOTES).'"></i></span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="enable_kb" data-toggle-on="', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_kb_input" name="enable_kb_input" value="', isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1 ? '1' : '0', '" />
                    </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Enable SUGGESTION
echo '
                    <tr><td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label>
                            '.$LANG['settings_suggestion'].'
                            <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_suggestion_tip']), ENT_QUOTES).'"></i></span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="enable_suggestion" data-toggle-on="', isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_suggestion_input" name="enable_suggestion_input" value="', isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1 ? '1' : '0', '" />
                    </td></tr>';

// Enable GET TP Information
echo '
                    <tr><td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label>
                            '.$LANG['settings_get_tp_info'].'
                            <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_get_tp_info_tip']), ENT_QUOTES).'"></i></span>
                        </label>
                        </td><td>
                            <div class="toggle toggle-modern" id="get_tp_info" data-toggle-on="', isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="get_tp_info_input" name="get_tp_info_input" value="', isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] == 1 ? '1' : '0', '" />
                    </td></tr>';

echo '
                <tr><td colspan="3"><hr /></td></tr>
                </table>
            </div>';
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// TAB Né2
echo '
            <div id="tabs-2">';
// Rebuild Config file
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_rebuild_config_file\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['rebuild_config_file'].'</label>
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['rebuild_config_file_tip']), ENT_QUOTES).'"></i></span>
                    <span id="result_admin_rebuild_config_file" style="margin-left:10px;display:none;"></span>
                </div>';
// Update Personal folders for users
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_check_pf\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['admin_action_check_pf'].'</label>
                    <span id="result_admin_action_check_pf" style="margin-left:10px;display:none;"></span>
                </div>';
// Clean DB with orphan items
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_db_clean_items\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['admin_action_db_clean_items'].'</label>
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_clean_items_tip']), ENT_QUOTES).'"></i></span>
                    <span id="result_admin_action_db_clean_items" style="margin-left:10px;"></span>
                </div>';
// Optimize the DB
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_db_optimize\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['admin_action_db_optimize'].'</label>
                    <span id="result_admin_action_db_optimize" style="margin-left:10px;"></span>
                </div>';
// Purge old files
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_purge_old_files\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['admin_action_purge_old_files'].'</label>
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_purge_old_files_tip']), ENT_QUOTES).'"></i></span>
                    <span id="result_admin_action_purge_old_files" style="margin-left:10px;"></span>
                </div>';
// Reload Cache Table
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_reload_cache_table\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['admin_action_reload_cache_table'].'</label>
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_reload_cache_table_tip']), ENT_QUOTES).'"></i></span>
                    <span id="result_admin_action_reload_cache_table" style="margin-left:10px;"></span>
                </div>';
// Change main SALT key
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="confirmChangingSk()" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['admin_action_change_salt_key'].'</label>
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_change_salt_key_tip']), ENT_QUOTES).'"></i></span>
                        &nbsp;<span id="changeMainSaltKey_message"></span>
                    </span>
                    <input type="hidden" id="changeMainSaltKey_itemsCount" />
                </div>';
/*
// Correct passwords prefix
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_pw_prefix_correct\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <label>'.$LANG['admin_action_pw_prefix_correct'].'</label>
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_pw_prefix_correct_tip']), ENT_QUOTES).'"></i></span>
                    <span id="result_admin_action_pw_prefix_correct" style="margin-left:10px;"></span>
                </div>';
*/
                /*
// Encrypt / decrypt attachments
echo '
                <div style="margin-bottom:3px">
                    <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_attachments_cryption\')" style="cursor:pointer;">
                        <i class="fa fa-square fa-stack-2x"></i>
                        <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                    </span>
                    <div class="div_radio" style="float:left;">
                        <input type="radio" id="attachments_cryption_radio1" name="attachments_cryption" value="encrypt" /><label for="attachments_cryption_radio1">'.$LANG['encrypt'].'</label>
                        <input type="radio" id="attachments_cryption_radio2" name="attachments_cryption" value="decrypt" /><label for="attachments_cryption_radio2">'.$LANG['decrypt'].'</label>
                    </div>
                    '.$LANG['admin_action_attachments_cryption'].'
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_attachments_cryption_tip']), ENT_QUOTES).'"></i></span>
                    <span id="result_admin_action_attachments_cryption" style="margin-left:10px;"></span>
                </div>';
*/
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
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_delay_for_item_edition'].
    '<span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_delay_for_item_edition_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                    <input type="text" size="5" id="delay_item_edition" name="delay_item_edition" value="', isset($_SESSION['settings']['delay_item_edition']) ? $_SESSION['settings']['delay_item_edition'] : '0', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';
// OTV - otv_is_enabled
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['otv_is_enabled'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="otv_is_enabled" data-toggle-on="', isset($_SESSION['settings']['otv_is_enabled']) && $_SESSION['settings']['otv_is_enabled'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="otv_is_enabled_input" name="otv_is_enabled_input" value="', isset($_SESSION['settings']['otv_is_enabled']) && $_SESSION['settings']['otv_is_enabled'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Expired time for OTV - otv_expiration_period
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_otv_expiration_period'].'</label>
                    </td><td>
                    <input type="text" size="5" id="otv_expiration_period" name="otv_expiration_period" value="', isset($_SESSION['settings']['otv_expiration_period']) ? $_SESSION['settings']['otv_expiration_period'] : '7', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Managers can edit & delete items they are allowed to see
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_manager_edit'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="manager_edit" data-toggle-on="', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="manager_edit_input" name="manager_edit_input" value="', isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] == 1 ? '1' : '0', '" />
                </td></tr>';

// Managers can move items they are allowed to see
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_manager_move_item'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="manager_move_item" data-toggle-on="', isset($_SESSION['settings']['manager_move_item']) && $_SESSION['settings']['manager_move_item'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="manager_move_item_input" name="manager_move_item_input" value="', isset($_SESSION['settings']['manager_move_item']) && $_SESSION['settings']['manager_move_item'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// max items
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label for="max_last_items">'.$LANG['max_last_items'].'</label>
                    </td><td>
                    <input type="text" size="4" id="max_last_items" name="max_last_items" value="', isset($_SESSION['settings']['max_latest_items']) ? $_SESSION['settings']['max_latest_items'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Duplicate folder
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['duplicate_folder'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="duplicate_folder" data-toggle-on="', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="duplicate_folder_input" name="duplicate_folder_input" value="', isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Duplicate item name
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['duplicate_item'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="duplicate_item" data-toggle-on="', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="duplicate_item_input" name="duplicate_item_input" value="', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Duplicate item name in same folder - item_duplicate_in_same_folder
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['duplicate_item_in_folder'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="item_duplicate_in_same_folder" data-toggle-on="', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="item_duplicate_in_same_folder_input" name="item_duplicate_in_same_folder_input" value="', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable show_only_accessible_folders
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['show_only_accessible_folders'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['show_only_accessible_folders_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="show_only_accessible_folders" data-toggle-on="', isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="show_only_accessible_folders_input" name="show_only_accessible_folders_input" value="', isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable subfolder_rights_as_parent
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['subfolder_rights_as_parent'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['subfolder_rights_as_parent_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="subfolder_rights_as_parent" data-toggle-on="', isset($_SESSION['settings']['subfolder_rights_as_parent']) && $_SESSION['settings']['subfolder_rights_as_parent'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="subfolder_rights_as_parent_input" name="subfolder_rights_as_parent_input" value="', isset($_SESSION['settings']['subfolder_rights_as_parent']) && $_SESSION['settings']['subfolder_rights_as_parent'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable create_item_without_password
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['create_item_without_password'].'
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="create_item_without_password" data-toggle-on="', isset($_SESSION['settings']['create_item_without_password']) && $_SESSION['settings']['create_item_without_password'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="create_item_without_password_input" name="create_item_without_password_input" value="', isset($_SESSION['settings']['create_item_without_password']) && $_SESSION['settings']['create_item_without_password'] == 1 ? '1' : '0', '" />
                </td></tr>';
/*
// Enable extra fields for each Item
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_item_extra_fields'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_item_extra_fields_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="item_extra_fields" data-toggle-on="', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="item_extra_fields_input" name="item_extra_fields_input" value="', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ? '1' : '0', '" />
                </td></tr>';
*/
echo '<tr><td colspan="3"><hr /></td></tr>';
// enable FAVOURITES
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['enable_favourites'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_favourites" data-toggle-on="', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_favourites_input" name="enable_favourites_input" value="', isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable USER can create folders
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['enable_user_can_create_folders'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_user_can_create_folders" data-toggle-on="', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_user_can_create_folders_input" name="enable_user_can_create_folders_input" value="', isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable can_create_root_folder
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['setting_can_create_root_folder'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="can_create_root_folder" data-toggle-on="', isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="can_create_root_folder_input" name="can_create_root_folder_input" value="', isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable enable_massive_move_delete
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['enable_massive_move_delete'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['enable_massive_move_delete_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_massive_move_delete" data-toggle-on="', isset($_SESSION['settings']['enable_massive_move_delete']) && $_SESSION['settings']['enable_massive_move_delete'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_massive_move_delete_input" name="enable_massive_move_delete_input" value="', isset($_SESSION['settings']['enable_massive_move_delete']) && $_SESSION['settings']['enable_massive_move_delete'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Enable activate_expiration
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['admin_setting_activate_expiration'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_setting_activate_expiration_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="activate_expiration" data-toggle-on="', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="activate_expiration_input" name="activate_expiration_input" value="', isset($_SESSION['settings']['activate_expiration']) && $_SESSION['settings']['activate_expiration'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable enable_delete_after_consultation
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['admin_setting_enable_delete_after_consultation'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_setting_enable_delete_after_consultation_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_delete_after_consultation" data-toggle-on="', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_delete_after_consultation_input" name="enable_delete_after_consultation_input" value="', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Enable Printing
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_printing'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_printing_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="allow_print" data-toggle-on="', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="allow_print_input" name="allow_print_input" value="', isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 ? '1' : '0', '" />
                </td></tr>';

// Enable Printing Groups - roles_allowed_to_print
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_roles_allowed_to_print'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_roles_allowed_to_print_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                    <input type="hidden" id="roles_allowed_to_print" name="roles_allowed_to_print" value="', isset($_SESSION['settings']['roles_allowed_to_print']) ? $_SESSION['settings']['roles_allowed_to_print'] : '', '" />
                    <select id="roles_allowed_to_print_select" name="roles_allowed_to_print_select" class="text ui-widget-content" multiple="multiple" onchange="refreshInput()">';
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
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_importing'].'
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="allow_import" data-toggle-on="', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="allow_import_input" name="allow_import_input" value="', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// Enable Item modification by anyone
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_anyone_can_modify'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_anyone_can_modify_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="anyone_can_modify" data-toggle-on="', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="anyone_can_modify_input" name="anyone_can_modify_input" value="', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable Item modification by anyone by default
echo '
                <tr id="tr_option_anyone_can_modify_bydefault"', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '':' style="display:none;"', '><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.htmlentities(strip_tags($LANG['settings_anyone_can_modify_bydefault'])).'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="anyone_can_modify_bydefault" data-toggle-on="', isset($_SESSION['settings']['anyone_can_modify_bydefault']) && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="anyone_can_modify_bydefault_input" name="anyone_can_modify_bydefault_input" value="', isset($_SESSION['settings']['anyone_can_modify_bydefault']) && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable restricted_to option
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_restricted_to'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="restricted_to" data-toggle-on="', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="restricted_to_input" name="restricted_to_input" value="', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable restricted_to_roles
echo '
                <tr id="tr_option_restricted_to_roles" style="display:', isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1 ? 'inline':'none', ';"><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['restricted_to_roles'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="restricted_to_roles" data-toggle-on="', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="restricted_to_roles_input" name="restricted_to_roles_input" value="', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// enable show copy to clipboard small icons
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['copy_to_clipboard_small_icons'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['copy_to_clipboard_small_icons_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="copy_to_clipboard_small_icons" data-toggle-on="', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="copy_to_clipboard_small_icons_input" name="copy_to_clipboard_small_icons_input" value="', isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? '1' : '0', '" />
                </td></tr>';
// Enable Show description in items list
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_show_description'].'
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="show_description" data-toggle-on="', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="show_description_input" name="show_description_input" value="', isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1 ? '1' : '0', '" />
                </td></tr>';
// In Tree, display number of Items in subfolders and number of subfolders - tree_counters
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_tree_counters'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_tree_counters_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="tree_counters" data-toggle-on="', isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="tree_counters_input" name="tree_counters_input" value="', isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1 ? '1' : '0', '" />
                </td></tr>';
// nb of items to display by ajax query
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['nb_items_by_query'].'</label>
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['nb_items_by_query_tip']), ENT_QUOTES).'"></i></span>
                    </td><td>
                    <input type="text" size="4" id="nb_items_by_query" name="nb_items_by_query" value="', isset($_SESSION['settings']['nb_items_by_query']) ? $_SESSION['settings']['nb_items_by_query'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// enable sending email on USER login
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['enable_send_email_on_user_login'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_send_email_on_user_login" data-toggle-on="', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_send_email_on_user_login_input" name="enable_send_email_on_user_login_input" value="', isset($_SESSION['settings']['enable_send_email_on_user_login']) && $_SESSION['settings']['enable_send_email_on_user_login'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable email notification on item shown
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['enable_email_notification_on_item_shown'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_email_notification_on_item_shown" data-toggle-on="', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_email_notification_on_item_shown_input" name="enable_email_notification_on_item_shown_input" value="', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? '1' : '0', '" />
                </td></tr>';
// enable email notification when user password is changed
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['enable_email_notification_on_user_pw_change'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_email_notification_on_user_pw_change" data-toggle-on="', isset($_SESSION['settings']['enable_email_notification_on_user_pw_change']) && $_SESSION['settings']['enable_email_notification_on_user_pw_change'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_email_notification_on_user_pw_change_input" name="enable_email_notification_on_user_pw_change_input" value="', isset($_SESSION['settings']['enable_email_notification_on_user_pw_change']) && $_SESSION['settings']['enable_email_notification_on_user_pw_change'] == 1 ? '1' : '0', '" />
                </td></tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';
// enable add manual entries in History
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_insert_manual_entry_item_history'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_insert_manual_entry_item_history_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="insert_manual_entry_item_history" data-toggle-on="', isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="insert_manual_entry_item_history_input" name="insert_manual_entry_item_history_input" value="', isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1 ? '1' : '0', '" />
                </td></tr>';
echo '<tr><td colspan="3"><hr /></td></tr>';
// OffLine mode options
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>
                        '.$LANG['settings_offline_mode'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_offline_mode_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="settings_offline_mode" data-toggle-on="', isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="settings_offline_mode_input" name="settings_offline_mode_input" value="', isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 ? '1' : '0', '" />
                </td></tr>';
// OffLne KEy Level
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label for="offline_key_level">'.$LANG['offline_mode_key_level'].'</label>
                    </td>
                    <td>
                        <select id="offline_key_level" name="offline_key_level" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));">';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '<option value="'.$complex[0].'"', isset($_SESSION['settings']['offline_key_level']) && $_SESSION['settings']['offline_key_level'] == $complex[0] ? ' selected="selected"' : '', '>'.$complex[1].'</option>';
}
echo '
                        </select>
                    </td>
                </tr>';
echo '<tr><td colspan="3"><hr /></td></tr>';
// SYSLOG ENABLE
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['syslog_enable'].'</label>
                    </td><td>
                        <div class="toggle toggle-modern" id="syslog_enable" data-toggle-on="', isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="syslog_enable_input" name="syslog_enable_input" value="', isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1 ? '1' : '0', '" />
                </td></tr>';
// SYSLOG Host
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-long-arrow-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['syslog_host'].'
                        </td>
                        <td>
                            <input id="syslog_host" name="syslog_host" type="text" size="40px" value="', !isset($_SESSION['settings']['syslog_host']) ? 'localhost' : $_SESSION['settings']['syslog_host'], '" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// SYSLOG port
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-long-arrow-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['syslog_port'].'
                        </td>
                        <td>
                            <input id="syslog_port" name="syslog_port" type="text" size="40px" value="', !isset($_SESSION['settings']['syslog_port']) ? '514' : $_SESSION['settings']['syslog_port'], '" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';

echo '<tr><td colspan="3"><hr /></td></tr>';

// Automatic server password change
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['server_password_change_enable'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['server_password_change_enable_tip']), ENT_QUOTES).'"></i>&nbsp;</span>
                    </label>
                    </td><td>
                        <div class="toggle toggle-modern" id="enable_server_password_change" data-toggle-on="', isset($_SESSION['settings']['enable_server_password_change']) && $_SESSION['settings']['enable_server_password_change'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="enable_server_password_change_input" name="enable_server_password_change_input" value="', isset($_SESSION['settings']['enable_server_password_change']) && $_SESSION['settings']['enable_server_password_change'] == 1 ? '1' : '0', '" />
                </td></tr>';

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
            <i class="fa fa-warning fa-2x"></i>&nbsp;'.$LANG['ldap_extension_not_loaded'].'
        </div>
    </div>';
} else {
    // Enable LDAP mode
    echo '
    <div style="margin-bottom:3px;">
        <table><tr>
        <td><label for="ldap_mode">'.$LANG['settings_ldap_mode'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_mode_tip']), ENT_QUOTES).'"></i></label></td>
        <td><div class="toggle toggle-modern" id="ldap_mode" data-toggle-on="', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="ldap_mode_input" name="ldap_mode_input" value="', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? '1' : '0', '" /></td>
        </tr></table>
    </div>';
}
// LDAP inputs
echo '
            <div id="div_ldap_configuration" ', (isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1) ? '':' style="display:none;"' , '>
                <div style="font-weight:bold;font-size:14px;margin:15px 0px 8px 0px;">'.$LANG['admin_ldap_configuration'].'</div>
                <table>';
// Type
$ldap_type = isset($_SESSION['settings']['ldap_type']) ? $_SESSION['settings']['ldap_type'] : '';
echo '
                    <tr>
                        <td><label for="ldap_type">'.$LANG['settings_ldap_type'].'</label></td>
                        <td>
                            <select id="ldap_type" name="ldap_type" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));">
                                <option value="0">-- '.$LANG['select'].' --</option>
                                <option value="windows"', $ldap_type == 'windows' ? ' selected="selected"' : '', '>Windows / Active Directory</option>
                                <option value="posix"', $ldap_type == 'posix' ? ' selected="selected"' : '', '>Posix / OpenLDAP (RFC2307)</option>
                                <option value="posix-search"', $ldap_type == 'posix-search' ? ' selected="selected"' : '', '>Posix / OpenLDAP (RFC2307) Search Based</option>
                            </select>
                        </td>
                    </tr>';
// Domain
if (isset($ldap_type) && $ldap_type != 'posix' && $ldap_type != 'posix-search') {
echo '
                    <tr>
                        <td><label for="ldap_suffix">'.$LANG['settings_ldap_domain'].'</label></td>
                        <td><input type="text" size="50" id="ldap_suffix" name="ldap_suffix" class="text ui-widget-content" title="@dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_suffix']) ? $_SESSION['settings']['ldap_suffix'] : '', '" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                    </tr>';
}

if (isset($ldap_type) && $ldap_type != 'posix-search') {
// Domain DN
echo '
                    <tr>
                        <td><label for="ldap_domain_dn">'.$LANG['settings_ldap_domain_dn'].'</label></td>
                        <td><input type="text" size="50" id="ldap_domain_dn" name="ldap_domain_dn" class="text ui-widget-content" title="dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_domain_dn']) ? $_SESSION['settings']['ldap_domain_dn'] : '', '" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                    </tr>';
}

// Subtree for posix / openldap
if (isset($ldap_type) && $ldap_type == 'posix') {
        echo '
                <tr>
                    <td><label for="ldap_suffix">'.$LANG['settings_ldap_domain_posix'].'</label></td>
                    <td><input type="text" size="50" id="ldap_suffix" name="ldap_suffix" class="text ui-widget-content" title="@dc=example,dc=com" value="', isset($_SESSION['settings']['ldap_suffix']) ? $_SESSION['settings']['ldap_suffix'] : '', '" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                </tr>';
}

// LDAP username attribute
if (isset($ldap_type) && $ldap_type == 'posix-search') {
        // LDAP Object Class
        echo '
                <tr>
                    <td><label for="ldap_object_class">'.$LANG['settings_ldap_object_class'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_object_class_tip']), ENT_QUOTES).'"></i></label></td>
                    <td><input type="text" size="50" id="ldap_object_class" name="ldap_object_class" class="text ui-widget-content" title="Person" value="',
                    isset($_SESSION['settings']['ldap_object_class']) ? $_SESSION['settings']['ldap_object_class'] : 'posixAccount', '" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                </tr>';
        echo '
                <tr>
                    <td><label for="ldap_user_attribute">'.$LANG['settings_ldap_user_attribute'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_user_attribute_tip']), ENT_QUOTES).'"></i></label></td>
                    <td><input type="text" size="50" id="ldap_user_attribute" name="ldap_user_attribute" class="text ui-widget-content" title="uid" value="',
                        isset($_SESSION['settings']['ldap_user_attribute']) ? $_SESSION['settings']['ldap_user_attribute'] : 'uid', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                </tr>';
                // LDAP
                echo '
                <tr>
                    <td><label for="ldap_usergroup">'.$LANG['settings_ldap_usergroup'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_usergroup_tip']), ENT_QUOTES).'"></i></label></td>
                    <td><input type="text" size="50" id="ldap_usergroup" name="ldap_usergroup" class="text ui-widget-content" title="uid" value="',
                        isset($_SESSION['settings']['ldap_usergroup']) ? $_SESSION['settings']['ldap_usergroup'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                </tr>';
                // LDAP BIND DN for search
                echo '
                <tr>
                    <td><label for="ldap_bind_dn">'.$LANG['settings_ldap_bind_dn'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_bind_dn_tip']), ENT_QUOTES).'"></i></label></td>
                    <td><input type="text" size="50" id="ldap_bind_dn" name="ldap_bind_dn" class="text ui-widget-content" title="uid=teampass,ou=people,dc=mydomain,dc=local" value="', isset($_SESSION['settings']['ldap_bind_dn']) ? $_SESSION['settings']['ldap_bind_dn'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                </tr>';
                // LDAP BIND PASSWD for search
                echo '
                <tr>
                    <td><label for="ldap_bind_passwd">'.$LANG['settings_ldap_bind_passwd'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_bind_passwd_tip']), ENT_QUOTES).'"></i></label></td>
                    <td><input type="text" size="50" id="ldap_bind_passwd" name="ldap_bind_passwd" class="text ui-widget-content" title="123password456" value="', isset($_SESSION['settings']['ldap_bind_passwd']) ? $_SESSION['settings']['ldap_bind_passwd'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                </tr>';
                // LDAP BASE for search
                echo '
                <tr>
                    <td><label for="ldap_search_base">'.$LANG['settings_ldap_search_base'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_search_base_tip']), ENT_QUOTES).'"></i></label></td>
                    <td><input type="text" size="50" id="ldap_search_base" name="ldap_search_base" class="text ui-widget-content" title="ou=people,dc=octopoos,dc=local" value="', isset($_SESSION['settings']['ldap_search_base']) ? $_SESSION['settings']['ldap_search_base'] : '', '" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                </tr>';
}

// Domain controler
echo '
                    <tr>
                        <td><label for="ldap_domain_controler">'.$LANG['settings_ldap_domain_controler'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_domain_controler_tip']), ENT_QUOTES).'"></i></label></td>
                        <td><input type="text" size="50" id="ldap_domain_controler" name="ldap_domain_controler" class="text ui-widget-content" title="dc01.mydomain.local,dc02.mydomain.local" value="', isset($_SESSION['settings']['ldap_domain_controler']) ? $_SESSION['settings']['ldap_domain_controler'] : '', '" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                    </tr>';

// AD Port
 echo '
                    <tr>
                        <td><label for="ldap_port">'.$LANG['settings_ldap_port'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_port_tip']), ENT_QUOTES).'"></i></label></td>
                        <td><input type="text" size="50" id="ldap_port" name="ldap_port" class="text ui-widget-content" title="389" value="', isset($_SESSION['settings']['ldap_port']) ? $_SESSION['settings']['ldap_port'] : '389', '" onchange="updateSetting($(this).attr(\'id\'));" /></td>
                    </tr>';

// AD SSL
echo '
                    <tr>
                        <td><label>'.$LANG['settings_ldap_ssl'].'</label></td>
                        <td>
                            <div class="toggle toggle-modern" id="ldap_ssl" data-toggle-on="', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="ldap_ssl_input" name="ldap_ssl_input" value="', isset($_SESSION['settings']['ldap_ssl']) && $_SESSION['settings']['ldap_ssl'] == 1 ? '1' : '0', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// AD TLS
echo '
                    <tr>
                        <td><label>'.$LANG['settings_ldap_tls'].'</label></td>
                        <td>
                            <div class="toggle toggle-modern" id="ldap_tls" data-toggle-on="', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="ldap_tls_input" name="ldap_tls_input" value="', isset($_SESSION['settings']['ldap_tls']) && $_SESSION['settings']['ldap_tls'] == 1 ? '1' : '0', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// Enable only localy declared users with tips help
echo '
                    <tr>
                        <td><label>'.$LANG['settings_ldap_elusers'].'&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_ldap_elusers_tip']), ENT_QUOTES).'"></i></label></td>
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
                   <i class="fa fa-chevron-circle-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <b>'.$LANG['admin_one_shot_backup'].'</b>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table width="100%">';
// Backup the DB
echo '
                    <tr style="margin-bottom:3px">
                        <td width="35%">
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        '.$LANG['admin_action_db_backup'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_backup_get_key" style="margin-left:10px; text-align:left;">
                            &nbsp;'.$LANG['encrypt_key'].'<input type="password" size="20" id="result_admin_action_db_backup_key" />
                            &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_key_tip']), ENT_QUOTES).'"></i>&nbsp;
                            <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_db_backup\')" style="cursor:pointer;">
                                <i class="fa fa-square fa-stack-2x"></i>
                                <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                            </span>
                        </span>
                        <span id="result_admin_action_db_backup" style="margin-left:10px;"></span>
                        </td>
                    </tr>';
// Restore the DB
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        '.$LANG['admin_action_db_restore'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_restore_tip']), ENT_QUOTES).'"></i></span>
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
                   <i class="fa fa-chevron-circle-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <b>'.$LANG['admin_script_backups'].'</b>&nbsp;
                    <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_script_backups_tip']), ENT_QUOTES).'"></i></span>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table width="100%">';
// Backups script path
echo '
                    <tr style="margin-bottom:3px">
                        <td width="35%">
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        '.$LANG['admin_script_backup_path'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_script_backup_path_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_path" name="bck_script_path" type="text" size="60px" value="', isset($_SESSION['settings']['bck_script_path']) ? $_SESSION['settings']['bck_script_path'] : $_SESSION['settings']['cpassman_dir'].'/backups', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// Backups script name
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        '.$LANG['admin_script_backup_filename'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_script_backup_filename_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_filename" name="bck_script_filename" type="text" size="50px" value="', isset($_SESSION['settings']['bck_script_filename']) ? $_SESSION['settings']['bck_script_filename'] : 'bck_cpassman', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// Backups script encryption
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        '.$LANG['admin_script_backup_encryption'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_script_backup_encryption_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_key" name="bck_script_key" type="password" size="50px" value="', isset($_SESSION['settings']['bck_script_key']) ? $_SESSION['settings']['bck_script_key'] : '', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// Decrypt SQL file
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        '.$LANG['admin_script_backup_decrypt'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_script_backup_decrypt_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                        <span id="result_admin_action_db_restore" style="margin-left:10px;"></span>
                        <input id="bck_script_decrypt_file" name="bck_script_decrypt_file" type="text" size="50px" value="" />
                        &nbsp;
                        <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_action_backup_decrypt\')" style="cursor:pointer;">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                        </span>
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
                   <i class="fa fa-chevron-circle-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <b>'.$LANG['admin_emails_configuration'].'</b>
                </div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// SMTP server
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_smtp_server'].'
                        </td>
                        <td>
                            <input type="text" size="80" id="email_smtp_server" name="email_smtp_server" value="', !isset($_SESSION['settings']['email_smtp_server']) ? $smtp_server : $_SESSION['settings']['email_smtp_server'], '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// SMTP auth
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
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
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_auth_username'].'
                        </td>
                        <td>
                            <input id="email_auth_username" name="email_auth_username" type="text" size="40px" value="', !isset($_SESSION['settings']['email_auth_username']) ? $smtp_auth_username : $_SESSION['settings']['email_auth_username'], '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// SMTP auth pwd
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_auth_pwd'].'
                        </td>
                        <td>
                            <input id="email_auth_pwd" name="email_auth_pwd" type="password" size="40px" value="', !isset($_SESSION['settings']['email_auth_pwd']) ? $smtp_auth_password : $_SESSION['settings']['email_auth_pwd'], '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// SMTP server url
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_server_url'].'
                        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_email_server_url_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                            <input id="email_server_url" name="email_server_url" type="text" size="40px" value="', !isset($_SESSION['settings']['email_server_url']) ? $_SESSION['settings']['cpassman_url'] : $_SESSION['settings']['email_server_url'], '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// SMTP port
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_port'].'
                        </td>
                        <td>
                            <input id="email_port" name="email_port" type="text" size="40px" value="', !isset($_SESSION['settings']['email_port']) ? '25' : $_SESSION['settings']['email_port'], '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// SMTP security
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_security'].'
                        </td>
                        <td>
                            <select id="email_security" name="email_security" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));">
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
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_from'].'
                        </td>
                        <td>
                            <input id="email_from" name="email_from" type="text" size="40px" value="', !isset($_SESSION['settings']['email_from']) ? $email_from : $_SESSION['settings']['email_from'], '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';
// SMTP from name
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_from_name'].'
                        </td>
                        <td>
                            <input id="email_from_name" name="email_from_name" type="text" size="40px" value="', !isset($_SESSION['settings']['email_from_name']) ? $email_from_name : $_SESSION['settings']['email_from_name'], '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                        </td>
                    </tr>';

echo '
                    </table>
                </div>';

echo '
                <div class="" style="0padding: 0 .7em;">
                   <i class="fa fa-chevron-circle-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <b>'.$LANG['admin_emails_configuration_testing'].'</b>
                </div>
                <div id="email_testing_results" class="ui-state-error ui-corner-all" style="padding:5px;display:none;margin:2px;"></div>
                <div style="margin:0 0 5px 20px;">
                    <table>';
// Test email configuration
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.$LANG['admin_email_test_configuration'].'
                            <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_email_test_configuration_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                            <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_email_test_configuration\')" style="cursor:pointer;">
                                <i class="fa fa-square fa-stack-2x"></i>
                                <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                            </span>
                        </td>
                    </tr>';
// Send emails backlog
DB::query("SELECT * FROM ".prefix_table("emails")." WHERE status = %s OR status = %s", 'not_sent', '');
$nb_emails = DB::count();
echo '
                    <tr style="margin-bottom:3px">
                        <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                            '.str_replace("#nb_emails#", $nb_emails, $LANG['admin_email_send_backlog']).'
                            <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['admin_email_send_backlog_tip']), ENT_QUOTES).'"></i></span>
                        </td>
                        <td>
                            <span class="fa-stack tip" title="'.htmlentities(strip_tags($LANG['admin_action_db_backup_start_tip']), ENT_QUOTES).'" onclick="LaunchAdminActions(\'admin_email_send_backlog\')" style="cursor:pointer;">
                                <i class="fa fa-square fa-stack-2x"></i>
                                <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                            </span>
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
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_maxfilesize'].
                    '<span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_upload_maxfilesize_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                    <input type="text" size="5" id="upload_maxfilesize" name="upload_maxfilesize" value="', isset($_SESSION['settings']['upload_maxfilesize']) ? $_SESSION['settings']['upload_maxfilesize'] : '10', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';
// Extension for Documents
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_docext'].
                    '<span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_upload_docext_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_docext" name="upload_docext" value="', isset($_SESSION['settings']['upload_docext']) ? $_SESSION['settings']['upload_docext'] : 'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';
// Extension for Images
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_imagesext'].
                    '<span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_upload_imagesext_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_imagesext" name="upload_imagesext" value="', isset($_SESSION['settings']['upload_imagesext']) ? $_SESSION['settings']['upload_imagesext'] : 'jpg,jpeg,gif,png', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';
// Extension for Packages
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_pkgext'].
                    '<span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_upload_pkgext_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_pkgext" name="upload_pkgext" value="', isset($_SESSION['settings']['upload_pkgext']) ? $_SESSION['settings']['upload_pkgext'] : '7z,rar,tar,zip', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';
// Extension for Other
echo '
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_otherext'].
                    '<span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_upload_otherext_tip']), ENT_QUOTES).'"></i></span>
                    </label>
                    </td><td>
                    <input type="text" size="70" id="upload_otherext" name="upload_otherext" value="', isset($_SESSION['settings']['upload_otherext']) ? $_SESSION['settings']['upload_otherext'] : 'sql,xml', '" class="text ui-widget-content" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';
echo '<tr><td colspan="3"><hr /></td></tr>';
// Image resize width / height / quality
echo '
                <tr style="margin-bottom:3px">
                    <td>
                        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                        <label>' .
                        $LANG['settings_upload_imageresize_options'].'
                        &nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_upload_imageresize_options_tip']), ENT_QUOTES).'"></i>
                        </label>
                    </td>
                    <td>
                        <div class="toggle toggle-modern" id="upload_imageresize_options" data-toggle-on="', isset($_SESSION['settings']['upload_imageresize_options']) && $_SESSION['settings']['upload_imageresize_options'] == 1 ? 'true' : 'false', '"></div><input type="hidden" id="upload_imageresize_options_input" name="upload_imageresize_options_input" value="', isset($_SESSION['settings']['upload_imageresize_options']) && $_SESSION['settings']['upload_imageresize_options'] == 1 ? '1' : '0', '" />
                    </td>
                </tr>
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_imageresize_options_w'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_width" name="upload_imageresize_width" value="',
                        isset($_SESSION['settings']['upload_imageresize_width']) ? $_SESSION['settings']['upload_imageresize_width'] :
                        '800', '" class="text ui-widget-content upl_img_opt" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_imageresize_options_h'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_height" name="upload_imageresize_height" value="',
                        isset($_SESSION['settings']['upload_imageresize_height']) ? $_SESSION['settings']['upload_imageresize_height'] :
                        '600', '" class="text ui-widget-content upl_img_opt" onchange="updateSetting($(this).attr(\'id\'));" />
                    </td>
                </tr>
                <tr><td>
                    <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
                    <label>'.$LANG['settings_upload_imageresize_options_q'].
                    '</label>
                    </td><td>
                    <input type="text" size="5" id="upload_imageresize_quality" name="upload_imageresize_quality" value="',
                        isset($_SESSION['settings']['upload_imageresize_quality']) ? $_SESSION['settings']['upload_imageresize_quality'] :
                        '90', '" class="text ui-widget-content upl_img_opt" onchange="updateSetting($(this).attr(\'id\'));" />
                </td></tr>';
echo '
                <tr><td colspan="3"><hr /></td></tr>';
echo '
                </table>
            </div>';
// --------------------------------------------------------------------------------

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
