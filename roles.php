<?php
/**
 * @file          roles.php
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

require_once 'sources/SecureHandler.php';
session_start();

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
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");

// connect to DB
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

//Get full list of groups
$arr_groups = array();
$rows = DB::query("SELECT id,title FROM ".prefix_table("nested_tree"));
foreach ($rows as $reccord) {
    $arr_groups[$reccord['id']] = $reccord['title'];
}

//display
echo '
<div class="page-header">
    <h1>
        '.$LANG['admin_functions'].'&nbsp;&nbsp;&nbsp;
        <button title="'.htmlentities(strip_tags($LANG['add_role_tip']), ENT_QUOTES).'" onclick="OpenDialog(\'add_new_role\');" class="button btn btn-default" style="font-size:16px;">
            <span class="fa fa-plus"></span>
        </button>&nbsp;
        <button title="'.htmlentities(strip_tags($LANG['item_menu_del_rep']), ENT_QUOTES).'" onclick="refresh_roles_matrix()" class="button btn btn-default" style="font-size:16px;">
            <span class="fa fa-refresh"></span>
        </button>
    </h1>
</div>

<div style="line-height:20px;" align="center">
    <div id="matrice_droits"></div>
    <div style="">
        <span class="fa fa-arrow-left" style="display:none;cursor:pointer" id="roles_previous" onclick="refresh_roles_matrix(\'previous\')"></span>&nbsp;
        <span class="fa fa-arrow-right" style="display:none;cursor:pointer" id="roles_next" onclick="refresh_roles_matrix(\'next\')"></span>
    </div>
</div>
<input type="hidden" id="selected_function" />
<input type="hidden" id="next_role" value="0" />
<input type="hidden" id="previous_role" value="0" />
<input type="hidden" id="role_start" value="0" />
<input type="hidden" id="change_role" value="0" />
<input type="hidden" id="change_folder" value="0" />
<input type="hidden" id="change_line" value="0" />';

// DIV FOR ADDING A ROLE
echo '
<div id="add_new_role" style="">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="new_role_error"></div>
    <p>
    <label for="new_function" class="form_label_100">'.$LANG['name'].'</label><input type="text" id="new_function" size="40" />
    </p>
    <p>
    <label for="new_role_complexity" class="form_label">'.$LANG['complex_asked'].' :</label>
    <select id="new_role_complexity" class="input_text text ui-widget-content ui-corner-all">
        <option value="">---</option>';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>
    </p>
    <div id="add_role_loader" style="display:none;text-align:center;margin-top:20px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';

// DIV FOR DELETING A ROLE
echo '
<div id="delete_role" style="display:none;">
    <div>'.$LANG['confirm_del_role'].'</div>
    <div style="font-weight:bold;text-align:center;color:#FF8000;text-align:center;font-size:13pt;" id="delete_role_show"></div>
    <input type="hidden" id="delete_role_id" />
    <div id="delete_role_loader" style="display:none;text-align:center;margin-top:20px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';

// DIV FOR EDITING A ROLE
echo '
<div id="edit_role" style="display:none;">
    <div style="text-align:center;padding:2px;display:none;" class="ui-state-error ui-corner-all" id="edit_role_error"></div>
    <div>'.$LANG['confirm_edit_role'].'</div>
    <div style="font-weight:bold;text-align:center;color:#FF8000;text-align:center;font-size:13pt;" id="edit_role_show"></div>
    <input type="hidden" id="edit_role_id" />
    <label for="edit_role_title" class="form_label">'.$LANG['new_role_title'].'</label><input type="text" id="edit_role_title" size="40" />
    <p>
    <label for="edit_role_complexity" class="form_label">'.$LANG['complex_asked'].' :</label>
    <select id="edit_role_complexity" class="input_text text ui-widget-content ui-corner-all">
        <option value="">---</option>';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>
    </p>
    <div id="edit_role_loader" style="display:none;text-align:center;margin-top:20px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';

// DIV FOR TYPE OF RIGHTS
echo '
<div id="type_of_rights">
    <div>'.$LANG['right_types_label'].'</div>
    <div style="margin-top:10px; text-align:center;">
        <input type="radio" name="right_types_radio" id="right_write" /><label for="right_write">'.$LANG['write'].'</label>&nbsp;
        <input type="radio" name="right_types_radio" id="right_read" /><label for="right_read">'.$LANG['read'].'</label>&nbsp;
        <input type="radio" name="right_types_radio" id="right_noaccess" /><label for="right_noaccess">'.$LANG['no_access'].'</label>
    </div>
    <div style="margin:10px 0 0 30px; display:none;" id="div_delete_option">
        <input type="checkbox" id="right_nodelete" />&nbsp;'.$LANG['role_cannot_delete_item'].'<br />
        <input type="checkbox" id="right_noedit" />&nbsp;'.$LANG['role_cannot_edit_item'].'
    </div>
    <div id="role_rights_loader" style="display:none;text-align:center;margin-top:20px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';

//call to roles.load.php
require_once 'roles.load.php';
