<?php
/**
 * @file 		users.php
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

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

//Load file
require_once ("users.load.php");

//load help
require_once('includes/language/'.$_SESSION['user_language'].'_admin_help.php');

require_once ("sources/NestedTree.class.php");
$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$tree_desc = $tree->getDescendants();

//Build FUNCTIONS list
$liste_fonctions = array();
$rows = $db->fetch_all_array("SELECT id,title FROM ".$pre."roles_title ORDER BY title ASC");
foreach($rows as $reccord) {
    $liste_fonctions[$reccord['id']] = array('id'=>$reccord['id'],'title'=>$reccord['title']);
}


//Display list of USERS
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
                    <th>ID</th>
                    <th></th>
                    <th>'.$txt['index_login'].'</th>
                    <th>'.$txt['functions'].'</th>
                    <th>'.$txt['authorized_groups'].'</th>
                    <th>'.$txt['forbidden_groups'].'</th>
                    <th title="'.$txt['god'].'"><img src="includes/images/user-black.png" /></th>
                    <th title="'.$txt['gestionnaire'].'"><img src="includes/images/user-worker.png" /></th>
                    <th title="'.$txt['read_only_account'].'"><img src="includes/images/user_read_only.png" /></th>
                    <th title="'.$txt['can_create_root_folder'].'"><img src="includes/images/folder-network.png" /></th>
                    ', (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature']==1) ? '<th title="'.$txt['enable_personal_folder'].'"><img src="includes/images/folder-open-document-text.png" /></th>' : '', '
                    <th title="'.$txt['user_action'].'"><img src="includes/images/user-locked.png" /></th>
                    <th title="'.$txt['pw_change'].'"><img src="includes/images/lock__pencil.png" /></th>
                    <th title="'.$txt['email_change'].'"><img src="includes/images/mail.png" /></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>';

		$list_available_users = $list_admins = "";
        $x = 0;
        //Get through all users
        $rows = $db->fetch_all_array("SELECT * FROM ".$pre."users");
        foreach($rows as $reccord){
            //Get list of allowed functions
                $list_allo_fcts = "";
                if ( $reccord['admin'] != 1 ){
                    if ( count($liste_fonctions) > 0 ){
                        foreach($liste_fonctions as $fonction){
                            if ( in_array($fonction['id'],explode(";",$reccord['fonction_id'])) )
                                $list_allo_fcts .= '<img src="includes/images/arrow-000-small.png" />'.@htmlspecialchars($fonction['title'],ENT_COMPAT, "UTF-8").'<br />';
                        }
                    }
                    if ( empty($list_allo_fcts) ) $list_allo_fcts = '<img src="includes/images/error.png" title="'.$txt['user_alarm_no_function'].'" />';
                }

            //Get list of allowed groups
                $list_allo_grps = "";
                if ( $reccord['admin'] != 1 ){
                    if ( count($tree_desc) > 0 ){
                        foreach($tree_desc as $t){
                            if ( @!in_array($t->id,$_SESSION['groupes_interdits']) && in_array($t->id,$_SESSION['groupes_visibles']) ){
                                $ident="";
                                if ( in_array($t->id,explode(";",$reccord['groupes_visibles'])) )
                                    $list_allo_grps .= '<img src="includes/images/arrow-000-small.png" />'.@htmlspecialchars($ident.$t->title,ENT_COMPAT, "UTF-8").'<br />';
                                $prev_level = $t->nlevel;
                            }
                        }
                    }
                }

            //Get list of forbidden groups
                $list_forb_grps = "";
                if ( $reccord['admin'] != 1 ){
                    if ( count($tree_desc) > 0 ){
                        foreach($tree_desc as $t){
                            $ident="";
                            if ( in_array($t->id,explode(";",$reccord['groupes_interdits'])) )
                                $list_forb_grps .= '<img src="includes/images/arrow-000-small.png" />'.@htmlspecialchars($ident.$t->title,ENT_COMPAT, "UTF-8").'<br />';
                            $prev_level = $t->nlevel;
                        }
                    }
                }

            //is user locked?
            if ($reccord['disabled'] == 1) {

            }

        	//Check if user has the same roles accessible as the manager
        	if ($_SESSION['user_gestionnaire']) {
        		$show_user_folders = false;
        		//Check if the user is a manager. If yes, not allowed to modifier
        		if (($_SESSION['user_gestionnaire'] == 1 && $reccord['gestionnaire'] == 1) || $reccord['admin'] == 1 ) {
        			$show_user_folders = false;
        		}else{
        			//Check if the user has at least a same role as the manager
        			foreach($_SESSION['user_roles'] as $role_id){
        				if (in_array($role_id, explode(";",$reccord['fonction_id']))) {
        					$show_user_folders = true;
        					break;
        				}
        			}
        			//if user has no role, Manager could add
        			if(empty($reccord['fonction_id'])) $show_user_folders = true;
        		}
        	}else{
        		$show_user_folders = true;
        	}

        	//Build list of available users
        	if($reccord['admin'] != 1 && $reccord['disabled'] != 1){
        		$list_available_users .= '<option value="'.$reccord['id'].'">'.$reccord['login'].'</option>';
        	}


            //Display Grid
            //if ($_SESSION['user_gestionnaire'] == 1 && $reccord['admin'] == 1){
            echo '<tr', $reccord['disabled'] == 1 ? ' style="background-color:#FF8080;font-size:11px;"' : ' class="ligne'.($x%2).'"', '>
                    <td align="center">'.$reccord['id'].'</td>
                    <td align="center">';
		        	/*if($reccord['admin'] == 1 && $_SESSION['user_id'] == $reccord['id']) echo '
		        		<img src="includes/images/admin_migrate_arrow.png" onclick="migrate_pf(\''.$reccord['id'].'\')" class="button" style="padding:2px;" title="'.$txt['user_admin_migrate_pw'].'" />';
		        	else*/
					if($reccord['disabled'] == 1) echo '
						<img src="includes/images/error.png" style="cursor:pointer;" onclick="unlock_user(\''.$reccord['id'].'\')" class="button" style="padding:2px;" title="'.$txt['unlock_user'].'" />';
					echo '
					</td>
                    <td align="center">
                        <p ', ($_SESSION['user_admin'] == 1 || ($_SESSION['user_gestionnaire'] == 1 && $reccord['admin'] == 0 && $reccord['gestionnaire'] == 0) && $show_user_folders == true) ? 'class="editable_textarea"' : '', 'id="login_'.$reccord['id'].'">'.$reccord['login'].'</p>
                    </td>
                    <td>
                    	<div>
							<div id="list_function_user_'.$reccord['id'].'" style="text-align:center;">
								'.$list_allo_fcts.'
	                        </div>
	                        <div style="text-align:center;', $show_user_folders == false ? 'display:none;':'', '">
	                        	<img src="includes/images/cog_edit.png"  class="button" style="padding:2px;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'functions\')" title="'.$txt['change_function'].'" />
							</div>', '
						</div>
                    </td>
                    <td>
                    	<div', ($reccord['admin'] == 1) ? ' style="display:none;"':'', '>
	                        <div id="list_autgroups_user_'.$reccord['id'].'" style="text-align:center;">'
	                        .$list_allo_grps.'
	                        </div>
	                        <div style="text-align:center;', $show_user_folders == false ? 'display:none;':'', '">
								<img src="includes/images/cog_edit.png"  class="button" style="padding:2px;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'autgroups\')" title="'.$txt['change_authorized_groups'].'" />
							</div>
						</div>
                    </td>
                    <td>
                    	<div', ($reccord['admin'] == 1) ? ' style="display:none;"':'', '>
	                        <div id="list_forgroups_user_'.$reccord['id'].'" style="text-align:center;">'
	                            .$list_forb_grps. '
	                        </div>
	                        <div style="text-align:center;', $show_user_folders == false ? 'display:none;':'', '">
	                        	<img src="includes/images/cog_edit.png" class="button" style="padding:2px;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'forgroups\')" title="'.$txt['change_forbidden_groups'].'" />
							</div>
						</div>
                    </td>
                    <td align="center">
                        <input type="checkbox" id="admin_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'admin\')"', $reccord['admin']==1 ? 'checked' : '', ' ', $_SESSION['user_gestionnaire'] == 1 ? 'disabled':'' , ' />
                    </td>
                    <td align="center">
                        <input type="checkbox" id="gestionnaire_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'gestionnaire\')"', $reccord['gestionnaire']==1 ? 'checked' : '', ' ', ($_SESSION['user_gestionnaire'] == 1 || $reccord['admin'] == 1) ? 'disabled':'',' />
                    </td>';

					//Read Only privilege
						echo '
                    <td align="center">
                        <input type="checkbox" id="read_only_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'read_only\')"', $reccord['read_only']==1 ? 'checked' : '', ' ', ($_SESSION['user_gestionnaire'] == 1 || $reccord['admin'] == 1) ? 'disabled':'',' />
                    </td>';

                    if( isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature']==1)
                        echo '
                    <td align="center">
                        <input type="checkbox" id="can_create_root_folder_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'can_create_root_folder\')"', $reccord['can_create_root_folder']==1 ? 'checked' : '', '', $_SESSION['user_admin'] == 1 ? '':' disabled', ' />
                    </td>';
                    echo '
                    <td align="center">
                        <input type="checkbox" id="personal_folder_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'personal_folder\')"', $reccord['personal_folder']==1 ? 'checked' : '', '', $_SESSION['user_admin'] == 1 ? '':' disabled', ' />
                    </td>';

        			//If user is active, then you could lock it
        			//If user is locked, you could delete it
		        	if($reccord['disabled'] == 1){
		        		$action_on_user = "action_on_user('".$reccord['id']."','delete')";
		        		$user_icon = "user--minus";
		        		$user_txt = $txt['user_del'];
		        	}else{
		        		$action_on_user = "action_on_user('".$reccord['id']."','lock')";
		        		$user_icon = "user-locked";
		        		$user_txt = $txt['user_lock'];
		        	}

                    echo '
                    <td align="center">
                        <img ', ($show_user_folders == true) ? 'src="includes/images/'.$user_icon.'.png" onclick="'.$action_on_user.'" class="button" style="padding:2px;" title="'.$user_txt.'"':'src="includes/images/user--minus_disabled.png"', ' />
                    </td>
                    <td align="center">
                        &nbsp;<img ', ($show_user_folders == true) ? 'src="includes/images/lock__pencil.png" onclick="mdp_user(\''.$reccord['id'].'\')" class="button" style="padding:2px;"':'src="includes/images/lock__pencil_disabled.png"', ' />
                    </td>
                    <td align="center">
                        &nbsp;';
        				if ($show_user_folders != true) {
        					echo '<img src="includes/images/mail--pencil_disabled.png" />';
        				}else{
        					echo '<img src="includes/images/', empty($reccord['email']) ? 'mail--exclamation.png':'mail--pencil.png', '" onclick="mail_user(\''.$reccord['id'].'\',\''.addslashes($reccord['email']).'\')" class="button" style="padding:2px;" title="'.$reccord['email'].'"', ' />';
        				}
        			echo '
                    </td>
                    <td align="center">
                        &nbsp;<img ', ($show_user_folders != true) ? 'src="includes/images/report_disabled.png"':'src="includes/images/report.png" onclick="user_action_log_items(\''.$reccord['id'].'\')" class="button" style="padding:2px;" title="'.$txt['see_logs'].'"', ' />
                    </td>
                </tr>';
                $x++;
            //}
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
<div id="change_user_functions" style="display:none;">'.
$txt['change_user_functions_info'].'
<form name="tmp_functions" action="">
<div id="change_user_functions_list" style="margin-left:15px;"></div>
</form>
</div>';

// DIV FOR CHANGING AUTHORIZED GROUPS
echo '
<div id="change_user_autgroups" style="display:none;">'.
$txt['change_user_autgroups_info'].'
<form name="tmp_autgroups" action="">
<div id="change_user_autgroups_list" style="margin-left:15px;"></div>
</form>
</div>';

// DIV FOR CHANGING FUNCTIONS
echo '
<div id="change_user_forgroups" style="display:none;">'.
$txt['change_user_forgroups_info'].'
<form name="tmp_forgroups" action="">
<div id="change_user_forgroups_list" style="margin-left:15px;"></div>
</form>
</div>';

/* DIV FOR ADDING A USER */
echo '
<div id="add_new_user" style="display:none;">
    <div id="add_new_user_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <label for="new_login" class="label_cpm">'.$txt['name'].'</label>
	<input type="text" id="new_login" class="input_text text ui-widget-content ui-corner-all" />
	', isset($_SESSION['settings']['ldap_mode']) && $_SESSION['settings']['ldap_mode'] == 1 ? '' :
    '<label for="new_pwd" class="label_cpm">'.$txt['pw'].'&nbsp;<img src="includes/images/refresh.png" onclick="pwGenerate(\'new_pwd\')" style="cursor:pointer;" /></label>
	<input type="text" id="new_pwd" class="input_text text ui-widget-content ui-corner-all" />', '

   	<label for="new_email" class="label_cpm">'.$txt['email'].'</label>
	<input type="text" id="new_email" class="input_text text ui-widget-content ui-corner-all" onchange="check_domain(this.value)" />
	&nbsp;<img id="ajax_loader_new_mail" style="display:none;" src="includes/images/ajax-loader.gif" alt="" />
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
		<input type="hidden" id="new_domain">
	</div>
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
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="change_user_pw_error"></div>'.
    $txt['give_new_pw'].'
    <div style="font-weight:bold;text-align:center;color:#FF8000;display:inline;" id="change_user_pw_show_login"></div>
    <div style="margin-top:10px;text-align:center;">
        <input type="text" size="30" id="change_user_pw_newpw" /><br />
        '.$txt['index_change_pw_confirmation'].' : <input type="text" size="30" id="change_user_pw_newpw_confirm" />
    </div>
    <input type="hidden" id="change_user_pw_id" />
</div>';

// DIV FOR CHANGING EMAIL
echo '
<div id="change_user_email" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="change_user_email_error"></div>'.
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
    <div style="text-align:center;padding:2^x;display:none;" class="ui-state-error ui-corner-all" id="manager_dialog_error"></div>
</div>';


/*// MIGRATE PERSONAL ITEMS FROM ADMIN TO A USER
echo '
<div id="migrate_pf_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;margin-bottom:10px;" class="ui-state-error ui-corner-all" id="migrate_pf_dialog_error"></div>
    <div>
    	<label>'.$txt['migrate_pf_select_to'].'</label>:
    	<select id="migrate_pf_to_user">
    		<option value="">-- '.$txt['select'].' --</option>'.$list_available_users.'
    	</select>
    	<br /><br />
    	<label>'.$txt['migrate_pf_user_salt'].'</label>: <input type="text" id="migrate_pf_user_salt" size="30" /><br />
    </div>
</div>';*/

// USER LOGS
echo '
<div id="user_logs_dialog" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="user_logs"></div>
     <div>'.
		$txt['nb_items_by_page'].':
		<select id="nb_items_by_page" onChange="displayLogs(1)">
	    	<option value="10">10</option>
	    	<option value="25">25</option>
	    	<option value="50">50</option>
	    	<option value="100">100</option>
	    </select>
	    &nbsp;&nbsp;'.
		$txt['activity'].':
		<select id="activity" onChange="displayLogs(1)">
	    	<option value="all">'.$txt['all'].'</option>
	    	<option value="at_modification">'.$txt['at_modification'].'</option>
	    	<option value="at_creation">'.$txt['at_creation'].'</option>
	    	<option value="at_delete">'.$txt['at_delete'].'</option>
	    	<option value="at_import">'.$txt['at_import'].'</option>
	    	<option value="at_restored">'.$txt['at_restored'].'</option>
	    	<option value="at_pw">'.$txt['at_pw'].'</option>
	    	<option value="at_shown">'.$txt['at_shown'].'</option>
	    </select>
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
?>