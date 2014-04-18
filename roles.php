<?php
/**
 * @file          roles.php
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
    !isset($_SESSION['key']) || empty($_SESSION['key'])
){
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

//load help
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_admin_help.php';

//Get full list of groups
$arr_groups = array();
$rows = $db->fetchAllArray("SELECT id,title FROM ".$pre."nested_tree");
foreach ($rows as $reccord) {
    $arr_groups[$reccord['id']] = $reccord['title'];
}

//display
echo '
<div class="title ui-widget-content ui-corner-all">
    '.$txt['admin_functions'].'&nbsp;&nbsp;
    &nbsp;<img src="includes/images/users--plus.png" title="'.$txt['add_role_tip'].'" onclick="OpenDialog(\'add_new_role\')" style="cursor:pointer;" />
    &nbsp;<a onClick="refresh_roles_matrix()"><img src="includes/images/arrow_refresh.png" style="cursor:pointer" title="'.$txt['refresh_matrix'].'" /></a>
    <span style="float:right;margin-right:5px;"><img src="includes/images/question-white.png" style="cursor:pointer" title="'.$txt['show_help'].'" onclick="OpenDialog(\'help_on_roles\')" /></span>
</div>
<div style="line-height:20px;" align="center">
    <div id="matrice_droits"></div>
    <div style="">
        <img src="includes/images/arrow-180.png" style="display:none; cursor:pointer" id="roles_previous" onclick="refresh_roles_matrix(\'previous\')">
        <img src="includes/images/arrow-0.png" style="display:none;cursor:pointer" id="roles_next" onclick="refresh_roles_matrix(\'next\')">
    </div>
</div>
<input type="hidden" id="selected_function" />
<input type="hidden" id="next_role" value="0" />
<input type="hidden" id="previous_role" value="0" />
<input type="hidden" id="role_start" value="0" />';

// DIV FOR ADDING A ROLE
echo '
<div id="add_new_role" style="">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="new_role_error"></div>
    <p>
    <label for="new_function" class="form_label_100">'.$txt['name'].'</label><input type="text" id="new_function" size="40" />
    </p>
    <p>
    <label for="new_role_complexity" class="form_label">'.$txt['complex_asked'].' :</label>
    <select id="new_role_complexity" class="input_text text ui-widget-content ui-corner-all">
        <option value="">---</option>';
foreach ($pwComplexity as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>
    </p>
</div>';

// DIV FOR DELETING A ROLE
echo '
<div id="delete_role" style="display:none;">
    <div>'.$txt['confirm_del_role'].'</div>
    <div style="font-weight:bold;text-align:center;color:#FF8000;text-align:center;font-size:13pt;" id="delete_role_show"></div>
    <input type="hidden" id="delete_role_id" />
</div>';

// DIV FOR EDITING A ROLE
echo '
<div id="edit_role" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="edit_role_error"></div>
    <div>'.$txt['confirm_edit_role'].'</div>
    <div style="font-weight:bold;text-align:center;color:#FF8000;text-align:center;font-size:13pt;" id="edit_role_show"></div>
    <input type="hidden" id="edit_role_id" />
    <label for="edit_role_title" class="form_label">'.$txt['new_role_title'].'</label><input type="text" id="edit_role_title" size="40" />
    <p>
    <label for="edit_role_complexity" class="form_label">'.$txt['complex_asked'].' :</label>
    <select id="edit_role_complexity" class="input_text text ui-widget-content ui-corner-all">
        <option value="">---</option>';
foreach ($pwComplexity as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>
    </p>
</div>';

// DIV FOR HELP
echo '
<div id="help_on_roles">
    <div>'.$txt['help_on_roles'].'</div>
</div>';

//call to roles.load.php
require_once 'roles.load.php';
