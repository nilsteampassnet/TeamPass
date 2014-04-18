<?php
/**
 *
 * @file          users.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
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
    include 'error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// Load file
require_once 'users.load.php';
// load help
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_admin_help.php';

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

$treeDesc = $tree->getDescendants();
// Build FUNCTIONS list
$rolesList = array();
$rows = $db->fetchAllArray("SELECT id,title FROM ".$pre."roles_title ORDER BY title ASC");
foreach ($rows as $reccord) {
    $rolesList[$reccord['id']] = array('id' => $reccord['id'], 'title' => $reccord['title']);
}
// Display list of USERS
echo '
<div class="title ui-widget-content ui-corner-all">
    '.$txt['admin_users'].'&nbsp;&nbsp;&nbsp;
    <img src="includes/images/user--plus.png" title="'.$txt['new_user_title'].'" onclick="OpenDialog(\'add_new_user\')"class="button" style="padding:2px;" />
    <span style="float:right;margin-right:5px;"><img src="includes/images/question-white.png" style="cursor:pointer" title="'.$txt['show_help'].'" onclick="OpenDialog(\'help_on_users\')" /></span>
</div>';

echo '
<form name="form_utilisateurs" method="post" action="">
    <div style="line-height:20px;"  align="center">
        <table cellspacing="0" cellpadding="2">
            <thead>
                <tr>
                    <th width="20px">ID</th>
                    <th></th>
                    <th>'.$txt['user_login'].'</th>
                    <th>'.$txt['name'].'</th>
                    <th>'.$txt['lastname'].'</th>
                    <th>'.$txt['managed_by'].'</th>
                    <th>'.$txt['functions'].'</th>
                    <th>'.$txt['authorized_groups'].'</th>
                    <th>'.$txt['forbidden_groups'].'</th>
                    <th title="'.$txt['god'].'"><img src="includes/images/user-black.png" /></th>
                    <th title="'.$txt['gestionnaire'].'"><img src="includes/images/user-worker.png" /></th>
                    <th title="'.$txt['read_only_account'].'"><img src="includes/images/user_read_only.png" /></th>
                    <th title="'.$txt['can_create_root_folder'].'"><img src="includes/images/folder-network.png" /></th>
                    ', (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) ?
                    	'<th title="'.$txt['enable_personal_folder'].'"><img src="includes/images/folder-open-document-text.png" /></th>' : ''
                    ,'
                    <th title="'.$txt['user_action'].'"><img src="includes/images/user-locked.png" /></th>
                    <th title="'.$txt['pw_change'].'"><img src="includes/images/lock__pencil.png" /></th>
                    <th title="'.$txt['email_change'].'"><img src="includes/images/mail.png" /></th>
                    <th title="'.$txt['logs'].'"><img src="includes/images/log.png" /></th>
					', (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1) ?
                    	'<th title="'.$txt['send_ga_code'].'"><img src="includes/images/phone.png" /></th>':''
                	,'
                </tr>
            </thead>
            <tbody>';

$listAvailableUsers = $listAdmins = "";
$x = 0;
// Get through all users
$rows = $db->fetchAllArray("SELECT * FROM ".$pre."users ORDER BY login ASC");
foreach ($rows as $reccord) {
    // Get list of allowed functions
    $listAlloFcts = "";
    if ($reccord['admin'] != 1) {
        if (count($rolesList) > 0) {
            foreach ($rolesList as $fonction) {
                if (in_array($fonction['id'], explode(";", $reccord['fonction_id']))) {
                    $listAlloFcts .= '<img src="includes/images/arrow-000-small.png" />'.@htmlspecialchars($fonction['title'], ENT_COMPAT, "UTF-8").'<br />';
                }
            }
        }
        if (empty($listAlloFcts)) {
            $listAlloFcts = '<img src="includes/images/error.png" title="'.$txt['user_alarm_no_function'].'" />';
        }
    }
    // Get list of allowed groups
    $listAlloGrps = "";
    if ($reccord['admin'] != 1) {
        if (count($treeDesc) > 0) {
            foreach ($treeDesc as $t) {
                if (@!in_array($t->id, $_SESSION['groupes_interdits']) && in_array($t->id, $_SESSION['groupes_visibles'])) {
                    $ident = "";
                    if (in_array($t->id, explode(";", $reccord['groupes_visibles']))) {
                        $listAlloGrps .= '<img src="includes/images/arrow-000-small.png" />'.@htmlspecialchars($ident.$t->title, ENT_COMPAT, "UTF-8").'<br />';
                    }
                    $prev_level = $t->nlevel;
                }
            }
        }
    }
    // Get list of forbidden groups
    $listForbGrps = "";
    if ($reccord['admin'] != 1) {
        if (count($treeDesc) > 0) {
            foreach ($treeDesc as $t) {
                $ident = "";
                if (in_array($t->id, explode(";", $reccord['groupes_interdits']))) {
                    $listForbGrps .= '<img src="includes/images/arrow-000-small.png" />'.@htmlspecialchars($ident.$t->title, ENT_COMPAT, "UTF-8").'<br />';
                }
                $prev_level = $t->nlevel;
            }
        }
    }
    // is user locked?
    if ($reccord['disabled'] == 1) {
    }

    //Show user only if can be administrated by the adapted Roles manager
    if (
        $_SESSION['is_admin'] ||
        ($reccord['isAdministratedByRole'] > 0 &&
        in_array($reccord['isAdministratedByRole'], $_SESSION['user_roles']))
    ) {
        $showUserFolders = true;
    } else {
        $showUserFolders = false;
    }

    /*// Check if user has the same roles accessible as the manager
    if ($_SESSION['user_manager']) {
        $showUserFolders = false;
        // Check if the user is a manager. If yes, not allowed to modifier
        if (($_SESSION['user_manager'] == 1 && $reccord['gestionnaire'] == 1) || $reccord['admin'] == 1) {
            $showUserFolders = false;
        } else {
            // Check if the user has at least a same role as the manager
            foreach ($_SESSION['user_roles'] as $role_id) {
                if (in_array($role_id, explode(";", $reccord['fonction_id']))) {
                    $showUserFolders = true;
                    break;
                }
            }
            // if user has no role, Manager could add
            if (empty($reccord['fonction_id'])) {
                $showUserFolders = true;
            }
        }
    } else {
        $showUserFolders = true;
    }*/
    // Build list of available users
    if ($reccord['admin'] != 1 && $reccord['disabled'] != 1) {
        $listAvailableUsers .= '<option value="'.$reccord['id'].'">'.$reccord['login'].'</option>';
    }
    // Display Grid
    if ($showUserFolders == true) {
        echo '<tr', $reccord['disabled'] == 1 ? ' style="background-color:#FF8080;font-size:11px;"' : ' class="ligne'.($x % 2).'"', '>
                    <td align="center">'.$reccord['id'].'</td>
                    <td align="center">', $reccord['disabled'] == 1 ?'
                        <img src="includes/images/error.png" style="cursor:pointer;" onclick="unlock_user(\''.$reccord['id'].'\')" class="button" style="padding:2px;" title="'.$txt['unlock_user'].'" />' :
                    '', '
                    </td>
                    <td align="center">
                        <p ', ($_SESSION['user_admin'] == 1 || ($_SESSION['user_manager'] == 1 && $reccord['admin'] == 0 && $reccord['gestionnaire'] == 0) && $showUserFolders == true) ? 'class="editable_textarea"' : '', 'id="login_'.$reccord['id'].'">'.$reccord['login'].'</p>
                    </td>
                    <td align="center">
                        <p ', ($_SESSION['user_admin'] == 1 || ($_SESSION['user_manager'] == 1 && $reccord['admin'] == 0 && $reccord['gestionnaire'] == 0) && $showUserFolders == true) ? 'class="editable_textarea"' : '', 'id="name_'.$reccord['id'].'">'.@$reccord['name'].'</p>
                    </td>
                    <td align="center">
                        <p ', ($_SESSION['user_admin'] == 1 || ($_SESSION['user_manager'] == 1 && $reccord['admin'] == 0 && $reccord['gestionnaire'] == 0) && $showUserFolders == true) ? 'class="editable_textarea"' : '', 'id="lastname_'.$reccord['id'].'">'.@$reccord['lastname'].'</p>
                    </td>
                    <td align="center">
                        <div', ($reccord['admin'] == 1) ? ' style="display:none;"':'', '>
                            <div id="list_adminby_'.$reccord['id'].'" style="text-align:center;">
                                ', isset($reccord['isAdministratedByRole']) && $reccord['isAdministratedByRole'] > 0 ?
                                $rolesList[$reccord['isAdministratedByRole']]['title']
                                :
                                '<span title="'.$txt['administrators_only'].'">'.$txt['admin_small'].'</span>', '
                            </div>
                            <div style="text-align:center;">
                                <img src="includes/images/cog_edit.png"  class="button" style="padding:2px;" onclick="ChangeUSerAdminBy(\''.$reccord['id'].'\')" />
                            </div>', '
                        </div>

                    </td>
                    <td>
                        <div', ($reccord['admin'] == 1) ? ' style="display:none;"':'', '>
                            <div id="list_function_user_'.$reccord['id'].'" style="text-align:center;">
                                '.$listAlloFcts.'
                            </div>
                            <div style="text-align:center;', $showUserFolders == false ? 'display:none;':'', '">
                                <img src="includes/images/cog_edit.png"  class="button" style="padding:2px;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'functions\')" title="'.$txt['change_function'].'" />
                            </div>', '
                        </div>
                    </td>
                    <td>
                        <div', ($reccord['admin'] == 1) ? ' style="display:none;"':'', '>
                            <div id="list_autgroups_user_'.$reccord['id'].'" style="text-align:center;">'
        .$listAlloGrps.'
                            </div>
                            <div style="text-align:center;', $showUserFolders == false ? 'display:none;':'', '">
                                <img src="includes/images/cog_edit.png"  class="button" style="padding:2px;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'autgroups\')" title="'.$txt['change_authorized_groups'].'" />
                            </div>
                        </div>
                    </td>
                    <td>
                        <div', ($reccord['admin'] == 1) ? ' style="display:none;"':'', '>
                            <div id="list_forgroups_user_'.$reccord['id'].'" style="text-align:center;">'
        .$listForbGrps.'
                            </div>
                            <div style="text-align:center;', $showUserFolders == false ? 'display:none;':'', '">
                                <img src="includes/images/cog_edit.png" class="button" style="padding:2px;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'forgroups\')" title="'.$txt['change_forbidden_groups'].'" />
                            </div>
                        </div>
                    </td>
                    <td align="center">
                        <input type="checkbox" id="admin_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'admin\')"', $reccord['admin'] == 1 ? 'checked' : '', ' ', $_SESSION['user_manager'] == 1 ? 'disabled="disabled"':'' , ' />
                    </td>
                    <td align="center">
                        <input type="checkbox" id="gestionnaire_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'gestionnaire\')"', $reccord['gestionnaire'] == 1 ? 'checked' : '', ' ', ($_SESSION['user_manager'] == 1 || $reccord['admin'] == 1) ? 'disabled="disabled"':'', ' />
                    </td>';
        // Read Only privilege
        echo '
                    <td align="center">
                        <input type="checkbox" id="read_only_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'read_only\')"', $reccord['read_only'] == 1 ? 'checked' : '', ' ', ($showUserFolders != true) ? 'disabled="disabled"':'', ' />
                    </td>';
        // Can create at root
            echo '
                    <td align="center">
                        <input type="checkbox" id="can_create_root_folder_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'can_create_root_folder\')"', $reccord['can_create_root_folder'] == 1 ? 'checked' : '', '', $_SESSION['user_admin'] == 1 ? '':' disabled="disabled"', ' />
                    </td>';
        if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) {
        echo '
                    <td align="center">
                        <input type="checkbox" id="personal_folder_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'personal_folder\')"', $reccord['personal_folder'] == 1 ? 'checked' : '', '', $_SESSION['user_admin'] == 1 ? '':' disabled="disabled"', ' />
                    </td>';
        }
        // If user is active, then you could lock it
        // If user is locked, you could delete it
        if ($reccord['disabled'] == 1) {
            $actionOnUser = "action_on_user('".$reccord['id']."','delete')";
            $userIcon = "user--minus";
            $userTxt = $txt['user_del'];
        } else {
            $actionOnUser = "action_on_user('".$reccord['id']."','lock')";
            $userIcon = "user-locked";
            $userTxt = $txt['user_lock'];
        }

        echo '
                    <td align="center">
                        <img ', ($showUserFolders == true) ? 'src="includes/images/'.$userIcon.'.png" onclick="'.$actionOnUser.'" class="button" style="padding:2px;" title="'.$userTxt.'"':'src="includes/images/user--minus_disabled.png"', ' />
                    </td>
                    <td align="center">
                        &nbsp;<img ', ($showUserFolders == true) ? 'src="includes/images/lock__pencil.png" onclick="mdp_user(\''.$reccord['id'].'\')" class="button" style="padding:2px;"':'src="includes/images/lock__pencil_disabled.png"', ' />
                    </td>
                    <td align="center">
                        &nbsp;';
        if ($showUserFolders != true) {
            echo '<img src="includes/images/mail--pencil_disabled.png" />';
        } else {
            echo '<img id="useremail_'.$reccord['id'].'" src="includes/images/', empty($reccord['email']) ? 'mail--exclamation.png':'mail--pencil.png', '" onclick="mail_user(\''.$reccord['id'].'\',\''.addslashes($reccord['email']).'\')" class="button" style="padding:2px;" title="'.$reccord['email'].'"', ' />';
        }
    	echo '
                    </td>';
    	// Log reports
        echo '
                    <td align="center">
                        &nbsp;<img ', ($showUserFolders != true) ? 'src="includes/images/report_disabled.png"':'src="includes/images/report.png" onclick="user_action_log_items(\''.$reccord['id'].'\')" class="button" style="padding:2px;" title="'.$txt['see_logs'].'"', ' />
                    </td>';
    	// GA code
    	if (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1) {
    		echo '
					<td>
						&nbsp;<img src="includes/images/', empty($reccord['ga']) ? 'phone_add' : 'phone_sound' ,'.png" onclick="user_action_ga_code(\''.$reccord['id'].'\')" class="button" style="padding:2px;" title="'.$txt['user_ga_code'].'" />
					</td>';
    	}
    	// end
    	echo '
                </tr>';
        $x++;
    }
}
echo '
            </tbody>
        </table>
    </div>
</form>
<input type="hidden" id="selected_user" />
<input type="hidden" id="log_page" value="1" />';
// DIV FOR CHANGING FUNCTIONS
echo '
<div id="change_user_functions" style="display:none;">' .
$txt['change_user_functions_info'].'
<form name="tmp_functions" action="">
<div id="change_user_functions_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING AUTHORIZED GROUPS
echo '
<div id="change_user_autgroups" style="display:none;">' .
$txt['change_user_autgroups_info'].'
<form name="tmp_autgroups" action="">
<div id="change_user_autgroups_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING FUNCTIONS
echo '
<div id="change_user_forgroups" style="display:none;">' .
$txt['change_user_forgroups_info'].'
<form name="tmp_forgroups" action="">
<div id="change_user_forgroups_list" style="margin-left:15px;"></div>
</form>
</div>';
// DIV FOR CHANGING ADMINISTRATED BY
echo '
<div id="change_user_adminby" style="display:none;">
    <div id="change_user_adminby_list" style="margin:20px 0 0 15px;">
        <select id="user_admin_by" class="input_text text ui-widget-content ui-corner-all">
            <option value="0">'.$txt['administrators_only'].'</option>';
    foreach ($rolesList as $fonction) {
        if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
            echo '
            <option value="'.$fonction['id'].'">'.$txt['managers_of'].' "'.$fonction['title'].'"</option>';
        }
    }
    echo '
        </select>
    </div>
</div>';

/* DIV FOR ADDING A USER */
echo '
<div id="add_new_user" style="display:none;">
    <div id="add_new_user_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <label for="new_name" class="label_cpm">'.$txt['name'].'</label>
    <input type="text" id="new_name" class="input_text text ui-widget-content ui-corner-all" onchange="loginCreation()" />
    <br />
    <label for="new_lastname" class="label_cpm">'.$txt['lastname'].'</label>
    <input type="text" id="new_lastname" class="input_text text ui-widget-content ui-corner-all" onchange="loginCreation()" />
    <br />
    <label for="new_login" class="label_cpm">'.$txt['login'].'</label>
    <input type="text" id="new_login" class="input_text text ui-widget-content ui-corner-all" />
    <br />
    ', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? '' :
'<label for="new_pwd" class="label_cpm">'.$txt['pw'].'&nbsp;<img src="includes/images/refresh.png" onclick="pwGenerate(\'new_pwd\')" style="cursor:pointer;" /></label>
    <input type="text" id="new_pwd" class="input_text text ui-widget-content ui-corner-all" />', '
    <label for="new_email" class="label_cpm">'.$txt['email'].'</label>
    <input type="text" id="new_email" class="input_text text ui-widget-content ui-corner-all" onchange="check_domain(this.value)" />
    <label for="new_is_admin_by" class="label_cpm">'.$txt['is_administrated_by_role'].'</label>
    <select id="new_is_admin_by" class="input_text text ui-widget-content ui-corner-all">';
        // If administrator then all roles are shown
        // else only the Roles the users is associated to.
if ($_SESSION['is_admin']) {
    echo '
        <option value="0">'.$txt['administrators_only'].'</option>';
}
foreach ($rolesList as $fonction) {
    if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
        echo '
        <option value="'.$fonction['id'].'">'.$txt['managers_of'].' "'.$fonction['title'].'"</option>';
    }
}
echo '
    </select>
    <br />
    <input type="checkbox" id="new_admin"', $_SESSION['user_admin'] == 1 ? '':' disabled', ' />
       <label for="new_admin">'.$txt['is_admin'].'</label>
    <br />
    <input type="checkbox" id="new_manager"', $_SESSION['user_admin'] == 1 ? '':' disabled', ' />
       <label for="new_manager">'.$txt['is_manager'].'</label>
    <br />
    <input type="checkbox" id="new_read_only" />
       <label for="new_read_only">'.$txt['is_read_only'].'</label>
    <br />
    <input type="checkbox" id="new_personal_folder"', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? ' checked':'', ' />
       <label for="new_personal_folder">'.$txt['personal_folder'].'</label>
    <div id="auto_create_folder_role">
    <input type="checkbox" id="new_folder_role_domain" disabled />
    <label for="new_folder_role_domain">'.$txt['auto_create_folder_role'].'&nbsp;`<span id="auto_create_folder_role_span"></span>`</label>
    <img id="ajax_loader_new_mail" style="display:none;" src="includes/images/ajax-loader.gif" alt="" />
    <input type="hidden" id="new_domain">
    </div>
    <div style="display:none;" id="add_new_user_info" class="ui-state-default ui-corner-all"></div>
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
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="change_user_pw_error"></div>' .
$txt['give_new_pw'].'
    <div style="font-weight:bold;text-align:center;color:#FF8000;display:inline;" id="change_user_pw_show_login"></div>
    <div style="margin-top:20px; width:100%;">
        <label class="form_label" for="change_user_pw_newpw">'.$txt['index_new_pw'].'</label>&nbsp;<input type="password" size="30" id="change_user_pw_newpw" /><br />
        <label class="form_label" for="change_user_pw_newpw_confirm">'.$txt['index_change_pw_confirmation'].'</label>&nbsp;<input type="password" size="30" id="change_user_pw_newpw_confirm" />
    </div>
    <div style="width:100%;height:20px;">
        <div id="pw_strength" style="margin:5px 0 5px 120px;"></div>
    </div>
    <div style="text-align:center;margin-top:8px; display:none;" id="change_user_pw_wait"><img src="includes/images/ajax-loader.gif" /></div>
    <input type="hidden" id="change_user_pw_id" />
</div>';
// DIV FOR CHANGING EMAIL
echo '
<div id="change_user_email" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="change_user_email_error"></div>' .
$txt['give_new_email'].'
    <div style="font-weight:bold;text-align:center;color:#FF8000;display:inline;" id="change_user_email_show_login"></div>
    <div style="margin-top:10px;text-align:center;">
        <input type="text" size="50" id="change_user_email_newemail" />
    </div>
    <input type="hidden" id="change_user_email_id" />
</div>';
// DIV FOR HELP
echo '
<div id="help_on_users" style="">
    <div>'.$txt['help_on_users'].'</div>
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
        <label>'.$txt['migrate_pf_select_to'].'</label>:
        <select id="migrate_pf_to_user">
            <option value="">-- '.$txt['select'].' --</option>'.$listAvailableUsers.'
        </select>
        <br /><br />
        <label>'.$txt['migrate_pf_user_salt'].'</label>: <input type="text" id="migrate_pf_user_salt" size="30" /><br />
    </div>
</div>';*/
// USER LOGS
echo '
<div id="user_logs_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="user_logs"></div>
     <div>' .
$txt['nb_items_by_page'].':
        <select id="nb_items_by_page" onChange="displayLogs(1,$(\'#activity\').val())">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        &nbsp;&nbsp;' .
$txt['select'].':
        <select id="activity" onChange="show_user_log($(\'#activity\').val())">
            <option value="user_mngt">'.$txt['user_mngt'].'</option>
            <option value="user_activity">'.$txt['user_activity'].'</option>
        </select>
        <span id="span_user_activity_option" style="display:none;">&nbsp;&nbsp;' .
$txt['activity'].':
            <select id="activity_filter" onChange="displayLogs(1,\'user_activity\')">
                <option value="all">'.$txt['all'].'</option>
                <option value="at_modification">'.$txt['at_modification'].'</option>
                <option value="at_creation">'.$txt['at_creation'].'</option>
                <option value="at_delete">'.$txt['at_delete'].'</option>
                <option value="at_import">'.$txt['at_import'].'</option>
                <option value="at_restored">'.$txt['at_restored'].'</option>
                <option value="at_pw">'.$txt['at_pw'].'</option>
                <option value="at_shown">'.$txt['at_shown'].'</option>
            </select>
        </span>
    </div>
    <table width="100%">
        <thead>
            <tr>
                <th width="20%">'.$txt['date'].'</th>
                <th id="th_url" width="40%">'.$txt['label'].'</th>
                <th width="20%">'.$txt['user'].'</th>
                <th width="20%">'.$txt['activity'].'</th>
            </tr>
        </thead>
        <tbody id="tbody_logs">
        </tbody>
    </table>
    <div id="log_pages" style="margin-top:10px;"></div>
</div>';
