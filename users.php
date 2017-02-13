<?php
/**
 *
 * @file          users.php
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

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

// Load file
require_once 'users.load.php';
// load help
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_admin_help.php';

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

$treeDesc = $tree->getDescendants();
$foldersList = "";
foreach ($treeDesc as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
        $ident = "";
        for ($y = 1;$y < $t->nlevel;$y++) {
            $ident .= "&nbsp;&nbsp;";
        }
        $foldersList .= '<option value="'.$t->id.'">'.$ident.@htmlspecialchars($t->title, ENT_COMPAT, "UTF-8").'</option>';
        $prev_level = $t->nlevel;
    }
}

// Build ROLESTITLES list
$rolesList = array();
$rows = DB::query("SELECT id, title FROM ".prefix_table("roles_title")." ORDER BY title ASC");
foreach ($rows as $reccord) {
    $rolesList[$reccord['id']] = array('id' => $reccord['id'], 'title' => $reccord['title']);
}


// Display list of USERS
echo '
<div class="title ui-widget-content ui-corner-all">
    '.$LANG['admin_users'].'&nbsp;&nbsp;&nbsp;
    <button title="'.htmlentities(strip_tags($LANG['new_user_title']), ENT_QUOTES).'" onclick="OpenDialog(\'add_new_user\')" class="button" style="font-size:16px;">
        <i class="fa fa-plus"></i>
    </button>
    <button title="'.htmlentities(strip_tags($LANG['share_user_rights']), ENT_QUOTES).'" onclick="OpenDialog(\'share_rights_dialog\')" class="button" style="font-size:16px;">
        <i class="fa fa-share-alt"></i>
    </button>
</div>';


//Show the KB in a table view
echo '
<div style="margin:10px auto 25px auto;min-height:250px;" id="users_page">
<div id="t_users_alphabet" style="margin-top:25px;"></div>
<table id="t_users" class="hover" width="100%">
    <thead><tr>
        <th>'.$LANG['action'].'</th>
        <th>'.$LANG['user_login'].'</th>
        <th>'.$LANG['name'].'</th>
        <th>'.$LANG['lastname'].'</th>
        <th>'.$LANG['managed_by'].'</th>
        <th>'.$LANG['functions'].'</th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['god']), ENT_QUOTES).'"><i class="fa fa-user-secret" style="font-size:14px;"></i></th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['gestionnaire']), ENT_QUOTES).'"><i class="fa fa-child" style="font-size:14px;"></i></th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['read_only_account']), ENT_QUOTES).'"><i class="fa fa-eye" style="font-size:14px;"></i></th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['can_manage_all_users']), ENT_QUOTES).'"><i class="fa fa-group" style="font-size:14px;"></i></th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['can_create_root_folder']), ENT_QUOTES).'"><i class="fa fa-code-fork" style="font-size:14px;"></i></th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['enable_personal_folder']), ENT_QUOTES).'"><i class="fa fa-book" style="font-size:14px;"></i></th>
    </tr></thead>
    <tbody>
        <tr><td></td></tr>
    </tbody>
</table>
</div>';


echo '
<input type="hidden" id="selected_user" />
<input type="hidden" id="log_page" value="1" />';
// DIV FOR CHANGING FUNCTIONS
echo '
<div id="change_user_functions" style="display:none;">' .
$LANG['change_user_functions_info'].'
<form name="tmp_functions" action="">
<div id="change_user_functions_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING AUTHORIZED GROUPS
echo '
<div id="change_user_autgroups" style="display:none;">' .
$LANG['change_user_autgroups_info'].'
<form name="tmp_autgroups" action="">
<div id="change_user_autgroups_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING FUNCTIONS
echo '
<div id="change_user_forgroups" style="display:none;">' .
$LANG['change_user_forgroups_info'].'
<form name="tmp_forgroups" action="">
<div id="change_user_forgroups_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING ADMINISTRATED BY
echo '
<div id="change_user_adminby" style="display:none;">
    <div id="change_user_adminby_list" style="margin:20px 0 0 15px;">
        <select id="user_admin_by" class="input_text text ui-widget-content ui-corner-all">
            <option value="0">'.$LANG['administrators_only'].'</option>';
    foreach ($rolesList as $fonction) {
        if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
            echo '
            <option value="'.$fonction['id'].'">'.$LANG['managers_of'].' "'.htmlentities($fonction['title'], ENT_QUOTES, "UTF-8").'"</option>';
        }
    }
    echo '
        </select>
    </div>
</div>';

/* DIV FOR ADDING A USER */
echo '
<div id="add_new_user" style="display:none;">
    <div id="add_new_user_error" style="text-align:center;margin:2px;display:none; position:absolute; padding:3px; width:94%;" class="ui-state-error ui-corner-all"></div>
    <label for="new_name" class="label_cpm">'.$LANG['name'].'</label>
    <input type="text" id="new_name" class="input_text text ui-widget-content ui-corner-all" onchange="loginCreation()" style="margin-bottom:3px;" />

    <label for="new_lastname" class="label_cpm">'.$LANG['lastname'].'</label>
    <input type="text" id="new_lastname" class="input_text text ui-widget-content ui-corner-all" onchange="loginCreation()" style="margin-bottom:3px;" />

    <label for="new_login" class="label_cpm">'.$LANG['login'].'&nbsp;<span id="new_login_status"></span></label>
    <input type="text" id="new_login" class="input_text text ui-widget-content ui-corner-all" style="margin-bottom:3px;" />

    ', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? '' :
'<label for="new_pwd" class="label_cpm">'.$LANG['pw'].'&nbsp;<span class="fa fa-refresh"  onclick="pwGenerate(\'new_pwd\')" style="cursor:pointer;"></span></label>
    <input type="text" id="new_pwd" class="input_text text ui-widget-content ui-corner-all" style="margin-bottom:3px;" />', '

    <label for="new_email" class="label_cpm">'.$LANG['email'].'</label>
    <input type="text" id="new_email" class="input_text text ui-widget-content ui-corner-all" onchange="check_domain(this.value)" style="margin-bottom:3px;" />

    <label for="new_is_admin_by" class="label_cpm">'.$LANG['is_administrated_by_role'].'</label>
    <select id="new_is_admin_by" class="input_text text ui-widget-content ui-corner-all" style="margin-bottom:3px;">';
        // If administrator then all roles are shown
        // else only the Roles the users is associated to.
if ($_SESSION['is_admin']) {
    echo '
        <option value="0">'.$LANG['administrators_only'].'</option>';
}
foreach ($rolesList as $fonction) {
    if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
        echo '
        <option value="'.$fonction['id'].'">'.$LANG['managers_of'].' '.htmlentities($fonction['title'], ENT_QUOTES, "UTF-8").'</option>';
    }
}
echo '
    </select>
    <br />

    <label for="new_user_groups" class="form_label">'.$LANG['functions'].'</label>
    <select name="new_user_groups" id="new_user_groups" multiple="multiple">';

$functionsList = "";
// array of roles for actual user
$my_functions = explode(';', $_SESSION['fonction_id']);

$rows = DB::query("SELECT id,title,creator_id FROM ".prefix_table("roles_title"));
foreach ($rows as $record) {
    if ($_SESSION['is_admin'] == 1  || ($_SESSION['user_manager'] == 1 && (in_array($record['id'], $my_functions) || $record['creator_id'] == $_SESSION['user_id']))) {
        $functionsList .= '<option value="'.$record['id'].'" class="folder_rights_role">'.$record['title'].'</option>';
    }
}

    echo $functionsList.'
    </select>

    <label for="new_user_auth_folders" class="form_label" style="margin-top:3px;">'.$LANG['authorized_groups'].'</label>
    <select name="new_user_auth_folders" id="new_user_auth_folders" multiple="multiple">
        '.$foldersList.'
    </select>

    <label for="new_user_forbid_folders" class="form_label" style="margin-top:3px;">'.$LANG['forbidden_groups'].'</label>
    <select name="new_user_forbid_folders" id="new_user_forbid_folders" multiple="multiple">
        '.$foldersList.'
    </select>

    <div style="text-align:left;margin-top:5px;">
        <label style="">'.$LANG['admin_misc_title'].'</label>
        <div style="margin-top:5px;">
            <table border="0">
            <tr>
                <td>', $_SESSION['user_admin'] === "1" ? '
                    <input type="checkbox" id="new_admin" style="margin-bottom:3px;" />
                    <label for="new_admin">'.$LANG['is_admin'].'</label>

                    <input type="checkbox" id="new_super_manager" style="margin-bottom:3px;" />
                    <label for="new_super_manager">'.$LANG['is_super_manager'].'</label>

                    <input type="checkbox" id="new_manager" style="margin-bottom:3px;" />
                    <label for="new_manager">'.$LANG['is_manager'].'</label>
                    ' : '', '

                    <input type="checkbox" id="new_read_only" style="margin-bottom:3px;" />
                    <label for="new_read_only">'.$LANG['is_read_only'].'</label>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="checkbox" id="new_personal_folder"', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? ' checked="checked"':'', ' />
                    <label for="new_personal_folder">'.$LANG['personal_folder'].'</label>
                </td>
            </tr>
            <tr>
                <td>
                    <div id="auto_create_folder_role"  style="visibility:hidden;">
                        <input type="checkbox" id="new_folder_role_domain" disabled="disabled" />
                        <label for="new_folder_role_domain">'.$LANG['auto_create_folder_role'].'&nbsp;&quot;<span id="auto_create_folder_role_span"></span>&quot;</label>
                        <span id="ajax_loader_new_mail" style="display:none;margin-left:10px;"><span class="fa fa-cog fa-spin fa-1x"></span></span>
                        <input type="hidden" id="new_domain" />
                    </div>
                </td>
            </tr>
            </table>
        </div>
    </div>

    <div style="display:none; padding:4px; margin-top:5px;" id="add_new_user_info" class="ui-state-default ui-corner-all"></div>
</div>';
// DIV FOR DELETING A USER
echo '
<div id="delete_user" style="display:none;">
    <div id="user_action_html"></div>
    <div style="font-weight:bold;text-align:center;color:#FF8000;text-align:center;font-size:13pt;" id="delete_user_show_login"></div>
    <input type="hidden" id="delete_user_login" />
    <input type="hidden" id="delete_user_id" />
    <input type="hidden" id="delete_user_action" />
</div>';
// DIV FOR CHANGING PASWWORD
echo '
<div id="change_user_pw" style="display:none;">
    <div style="text-align:center; padding:2px; display:none; position:absolute; width:340px;" class="ui-state-error ui-corner-all" id="change_user_pw_error"></div>' .
$LANG['give_new_pw'].'
    <div style="font-weight:bold;text-align:center;color:#FF8000;display:inline;" id="change_user_pw_show_login"></div>
    <div style="margin-top:20px;">
        <table>
            <tr>
                <td style="width:120px;"><label for="change_user_pw_newpw">'.$LANG['index_new_pw'].'</label>&nbsp;</td>
                <td><input type="password" size="30" id="change_user_pw_newpw" /></td>
            </tr>
            <tr>
                <td><label for="change_user_pw_newpw_confirm">'.$LANG['index_change_pw_confirmation'].'</label>&nbsp;</td>
                <td><input type="password" size="30" id="change_user_pw_newpw_confirm" /></td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>
                    <div id="pw_strength" style="margin-top:5px;"></div>
                </td>
            </tr>
            <tr>
                <td><label for="generated_user_pw" id="generated_user_pw_title" style="display:none;">'.$LANG['generated_pw'].'</label>
                </td>
                <td>
                    <span style="text-align:center;margin-top:8px; display:none;" id="change_user_pw_wait"><span class="fa fa-cog fa-spin fa-1x"></span></span>
                    <span id="generated_user_pw" style="display:none;"></span>
                </td>
            </tr>
        </table>
    </div>

    <input type="hidden" id="change_user_pw_id" />
</div>';
// DIV FOR CHANGING EMAIL
echo '
<div id="change_user_email" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="change_user_email_error"></div>' .
$LANG['give_new_email'].'
    <div style="font-weight:bold;text-align:center;color:#FF8000;display:inline;" id="change_user_email_show_login"></div>
    <div style="margin-top:10px;text-align:center;">
        <input type="text" size="50" id="change_user_email_newemail" />
    </div>
    <input type="hidden" id="change_user_email_id" />
</div>';
// USER MANAGER
echo '
<div id="manager_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;" class="ui-state-error ui-corner-all" id="manager_dialog_error"></div>
</div>';

/*// MIGRATE PERSONAL ITEMS FROM ADMIN TO A USER
echo '
<div id="migrate_pf_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;margin-bottom:10px;" class="ui-state-error ui-corner-all" id="migrate_pf_dialog_error"></div>
    <div>
        <label>'.$LANG['migrate_pf_select_to'].'</label>:
        <select id="migrate_pf_to_user">
            <option value="">-- '.$LANG['select'].' --</option>'.$listAvailableUsers.'
        </select>
        <br /><br />
        <label>'.$LANG['migrate_pf_user_salt'].'</label>: <input type="text" id="migrate_pf_user_salt" size="30" /><br />
    </div>
</div>';*/
// USER LOGS
echo '
<div id="user_logs_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="user_logs"></div>
     <div>' .
$LANG['nb_items_by_page'].':
        <select id="nb_items_by_page" onchange="displayLogs(1,$(\'#activity\').val())">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        &nbsp;&nbsp;' .
$LANG['select'].':
        <select id="activity" onchange="show_user_log($(\'#activity\').val())">
            <option value="user_mngt">'.$LANG['user_mngt'].'</option>
            <option value="user_activity">'.$LANG['user_activity'].'</option>
        </select>
        <span id="span_user_activity_option" style="display:none;">&nbsp;&nbsp;' .
$LANG['activity'].':
            <select id="activity_filter" onchange="displayLogs(1,\'user_activity\')">
                <option value="all">'.$LANG['all'].'</option>
                <option value="at_modification">'.$LANG['at_modification'].'</option>
                <option value="at_creation">'.$LANG['at_creation'].'</option>
                <option value="at_delete">'.$LANG['at_delete'].'</option>
                <option value="at_import">'.$LANG['at_import'].'</option>
                <option value="at_restored">'.$LANG['at_restored'].'</option>
                <option value="at_pw">'.$LANG['at_pw'].'</option>
                <option value="at_password_shown">'.$LANG['at_password_shown'].'</option>
                <option value="at_shown">'.$LANG['at_shown'].'</option>
            </select>
        </span>
    </div>
    <table width="100%">
        <thead>
            <tr>
                <th width="20%">'.$LANG['date'].'</th>
                <th id="th_url" width="40%">'.$LANG['label'].'</th>
                <th width="20%">'.$LANG['user'].'</th>
                <th width="20%">'.$LANG['activity'].'</th>
            </tr>
        </thead>
        <tbody id="tbody_logs"><tr id="placeholder_tr" style="display: none;"><td></td></tr>
        </tbody>
    </table>
    <div id="log_pages" style="margin-top:10px;"></div>
</div>';


// USER EDIT DIALOG
echo '
<div id="user_management_dialog" style="display:none;">
    <div style="padding:5px; z-index:9999999;" class="ui-widget-content ui-state-focus ui-corner-all" id="user_edit_wait">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;'.$LANG['please_wait'].'
    </div>
    <div id="user_edit_div" style="display:none;">
    <div style="text-align:center;padding:2px;display:none; margin:0 0 15px 0;" class="ui-state-error ui-corner-all" id="user_edit_error"></div>

    <div style="width:100%;">
        <div style="width:70%; float:left;">
            <label class="form_label_100" for="user_edit_login">'.$LANG['user_login'].'</label>&nbsp;<input type="text" size="45" id="user_edit_login" class="ui-widget-content ui-corner-all form_text" /><br />
            <label class="form_label_100" for="user_edit_name">'.$LANG['name'].'</label>&nbsp;<input type="text" size="45" id="user_edit_name" class="ui-widget-content ui-corner-all form_text" /><br />
            <label class="form_label_100" for="user_edit_lastname">'.$LANG['lastname'].'</label>&nbsp;<input type="text" size="45" id="user_edit_lastname" class="ui-widget-content ui-corner-all form_text" />
            <br />
            <label class="form_label_100" for="user_edit_email">'.$LANG['email'].'</label>&nbsp;<input type="text" size="45" id="user_edit_email" class="ui-widget-content ui-corner-all form_text" />
        </div>
        <div style="width:30%; float:right;">
            <input type="hidden" id="confirm_deletion" value="" />
            <span id="user_edit_info" style="margin:20px 10px 0 0; text-align:center;"></span>
            <span id="user_edit_delete" style="margin:20px 10px 0 0; text-align:center; display:none;" class="ui-widget ui-corner-all">'.$LANG['user_info_delete'].'</span>
        </div>
    </div>
    <div style="width:100%; margin-top:10px;">
        <label for="user_edit_functions_list" class="form_label">'.$LANG['functions'].'</label>
        <select name="user_edit_functions_list" id="user_edit_functions_list" multiple="multiple"><option label="" style="display: none;"></option></select>
        <br />
        <label for="user_edit_managedby" class="form_label" style="margin-top:10px;">'.$LANG['managed_by'].'</label>
        <select name="user_edit_managedby" id="user_edit_managedby"><option label="" style="display: none;"></option></select>
        <br />
        <label for="user_edit_auth" class="form_label" style="margin-top:10px;">'.$LANG['authorized_groups'].'</label>
        <select name="user_edit_auth" id="user_edit_auth" multiple="multiple"><option label="" style="display: none;"></option></select>
        <br />
        <label for="user_edit_forbid" class="form_label" style="margin-top:10px;">'.$LANG['forbidden_groups'].'</label>
        <select name="user_edit_forbid" id="user_edit_forbid" multiple="multiple"><option label="" style="display: none;"></option></select>
        <br />
    </div>

    <div style="text-align:center;padding:2px;display:none; margin:0 0 15px 0;position: absolute; bottom: 0;" class="ui-state-error ui-corner-all" id="user_edit_warning_bottom"></div>
    <input type="hidden" id="user_edit_id" />
    </div>
</div>';

// USER FOLDERS RIGHTS DIALOG
echo '
<div id="user_folders_rights_dialog" style="display:none;">
    <div style="padding:5px; z-index:9999999;" class="ui-widget-content ui-state-focus ui-corner-all" id="user_folders_rights_dialog_wait">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;'.$LANG['please_wait'].'
    </div>

    <div id="user_folders_rights_dialog_txt"></div>

    <input type="hidden" id="user_folders_rights_dialog_id" />
</div>';

// PROPAGATE SETTINGS TO MULTIPLE USERS
echo '
<div id="share_rights_dialog" style="display:none;">

    <div id="share_rights_info" class="ui-widget-content ui-state-highlight ui-corner-all" style="padding:5px;"><span class="fa fa-info-circle fa-2x"></span>&nbsp;'.$LANG['share_rights_info'].'</div>

    <div id="" style="margin-top:10px;" class="">
        <label for="share_rights_from" class="form_label" style="font-size:14px; font-weight:bold;"><span class="fa fa-user"></span>&nbsp;'.$LANG['share_rights_source'].'</label>
        <select id="share_rights_from" onchange="get_user_rights()"></select>
    </div>

    <div id="share_rights_details" style="margin-top:5px; margin-left:20px;display:none; padding:3px;">
        <label for="share_rights_details_1" class="form_label"><span class="fa fa-hand-o-right"></span>&nbsp;'.$LANG['functions'].'</label>
        <span id="share_rights_details_1"></span>
        <input type="hidden" id="share_rights_details_ids_1" />
        <br>
        <label for="share_rights_details_2" class="form_label"><span class="fa fa-hand-o-right"></span>&nbsp;'.$LANG['managed_by'].'</label>
        <span id="share_rights_details_2"></span>
        <input type="hidden" id="share_rights_details_ids_2" />
        <br>
        <label for="share_rights_details_3" class="form_label"><span class="fa fa-hand-o-right"></span>&nbsp;'.$LANG['authorized_groups'].'</label>
        <span id="share_rights_details_3"></span>
        <input type="hidden" id="share_rights_details_ids_3" />
        <br>
        <label for="share_rights_details_4" class="form_label"><span class="fa fa-hand-o-right"></span>&nbsp;'.$LANG['forbidden_groups'].'</label>
        <span id="share_rights_details_4"></span>
        <input type="hidden" id="share_rights_details_ids_4" />        
        <input type="hidden" id="share_rights_details_other" />
    </div>

    <div id="" style="margin-top:5px;" class="">
        <label for="share_pres" class="form_label">&nbsp;</label>
        <span id="share_pres" style="text-align:center; margin-left:170px;"><span class="fa fa-long-arrow-down fa-2x"></span></span>
    </div>

    <div id="" style="margin-top:5px;" class="">
        <label for="share_rights_to" class="form_label" style="font-size:14px; font-weight:bold;"><span class="fa fa-users"></span>&nbsp;'.$LANG['share_rights_destination'].'</label>
        <select id="share_rights_to" multiple="multiple"></select>
    </div>

    <div style="text-align:center;padding:2px;display:none; margin:20px 0 0 0;" class="ui-corner-all" id="share_rights_dialog_msg"></div>

    <input type="hidden" id="share_rights_dialog_id" />
</div>';