<?php
/**
 *
 * @file          folders.php
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


//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

/* Get full tree structure */
$tst = $tree->getDescendants();

// prepare options list
$prev_level = 0;
$droplist = '<option value="na">---'.$LANG['select'].'---</option>';
if ($_SESSION['is_admin'] === "1" || $_SESSION['user_manager'] === "1" || $_SESSION['can_create_root_folder'] === "1") {
    $droplist .= '<option value="0">'.$LANG['root'].'</option>';
}
foreach ($tst as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
        $ident = "";
        for ($x = 1; $x < $t->nlevel; $x++) {
            $ident .= "&nbsp;&nbsp;";
        }
        if ($prev_level < $t->nlevel) {
            $droplist .= '<option value="'.$t->id.'">'.$ident.addslashes($t->title).'</option>';
        } elseif ($prev_level == $t->nlevel) {
            $droplist .= '<option value="'.$t->id.'">'.$ident.addslashes($t->title).'</option>';
        } else {
            $droplist .= '<option value="'.$t->id.'">'.$ident.addslashes($t->title).'</option>';
        }
        $prev_level = $t->nlevel;
    }
}

/* Display header */
echo '
<div class="page-header">
    <h1>
        '.$LANG['admin_groups'].'&nbsp;&nbsp;&nbsp;
        <button title="'.htmlentities(strip_tags($LANG['item_menu_add_rep']), ENT_QUOTES).'" onclick="OpenDialog(\'div_add_group\');" class="button btn btn-default" style="font-size:16px;">
            <span class="fa fa-plus"></span>
        </button>&nbsp;
        <button title="'.htmlentities(strip_tags($LANG['item_menu_del_rep']), ENT_QUOTES).'" id="click_delete_multiple_folders" class="button btn btn-default" style="font-size:16px;">
            <span class="fa fa-trash-o"></span>
        </button>&nbsp;
        <button title="'.htmlentities(strip_tags($LANG['refresh']), ENT_QUOTES).'" id="click_refresh_folders_list" class="button btn btn-default" style="font-size:16px;">
            <span class="fa fa-refresh"></span>
        </button>
    </h1>
</div>';
// Hidden things
echo '
<input type="hidden" id="folder_id_to_edit" value="" />
<input type="hidden" id="action_on_going" value="" />';


//Show the KB in a table view
echo '
<div style="margin:10px auto 25px auto;min-height:250px;" id="folders_page">
<table id="t_folders" class="hover" width="100%">
    <thead><tr>
        <th></th>
        <th>ID</th>
        <th>'.$LANG['group'].'</th>
        <th style="width:20px;">'.$LANG['nb_items'].'</th>
        <th>'.$LANG['complexity'].'</th>
        <th>'.$LANG['group_parent'].'</th>
        <th>'.$LANG['level'].'</th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['group_pw_duration_tip']), ENT_QUOTES).'">'.$LANG['group_pw_duration'].'</th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['auth_creation_without_complexity']), ENT_QUOTES).'"><i class="fa fa-legal fa-lg"></i></th>
        <th style="width:20px;" title="'.htmlentities(strip_tags($LANG['auth_modification_without_complexity']), ENT_QUOTES).'"><i class="fa fa-clock-o fa-lg"></i></th>
    </tr></thead>
    <tbody><tr id="placeholder_tr" style="display: none;"><td></td></tr>
    </tbody>
</table>
</div>';

/* Form Add a folder */
echo '
<div id="div_add_group" style="display:none;">
    <div id="addgroup_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>

    <div class="input-group">
        <span class="input-group-addon" id="basic-addon1">'.$LANG['group_title'].'</span>
        <input type="text" class="form-control" id="ajouter_groupe_titre" aria-describedby="basic-addon1">
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon2">'.$LANG['group_parent'].'</span>
        <select id="parent_id" class="form-control" aria-describedby="basic-addon2">'.
            $droplist.'
        </select>
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon3">'.$LANG['complex_asked'].'</span>
        <select id="new_rep_complexite" class="form-control" aria-describedby="basic-addon3">';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '
            <option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon4">'.$LANG['group_pw_duration'].'</span>
        <input type="text" class="form-control" id="add_node_renewal_period" aria-describedby="basic-addon4" value="0">
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon5">'.$LANG['auth_creation_without_complexity'].'</span>
        <select id="folder_block_creation" class="form-control" aria-describedby="basic-addon5">
            <option value="0">'.$LANG['no'].'</option>
            <option value="1">'.$LANG['yes'].'</option>
        </select>
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon6">'.$LANG['auth_modification_without_complexity'].'</span>
        <select id="folder_block_modif" class="form-control" aria-describedby="basic-addon6">
            <option value="0">'.$LANG['no'].'</option>
            <option value="1">'.$LANG['yes'].'</option>
        </select>
    </div>

    <div style="margin-top:20px; padding:5px; width:100%;" class="ui-widget-content ui-state-focus ui-corner-all" id="new_folder_wait">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;'.$LANG['please_wait'].'
    </div>
</div>';

/* Form EDIT a folder */
echo '
<div id="div_edit_folder" style="display:none;">
    <div id="edit_folder_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>

    <div class="input-group">
        <span class="input-group-addon" id="basic-addon1">'.$LANG['group_title'].'</span>
        <input type="text" class="form-control" id="edit_folder_title" aria-describedby="basic-addon1">
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon2">'.$LANG['group_parent'].'</span>
        <select id="edit_parent_id" class="form-control" aria-describedby="basic-addon2">'.
            $droplist.'
        </select>
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon3">'.$LANG['complex_asked'].'</span>
        <select id="edit_folder_complexite" class="form-control" aria-describedby="basic-addon3">';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '
            <option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon4">'.$LANG['group_pw_duration'].'</span>
        <input type="text" class="form-control" id="edit_folder_renewal_period" aria-describedby="basic-addon4" value="0">
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon5">'.$LANG['auth_creation_without_complexity'].'</span>
        <select id="edit_folder_block_creation" class="form-control" aria-describedby="basic-addon5">
            <option value="0">'.$LANG['no'].'</option>
            <option value="1">'.$LANG['yes'].'</option>
        </select>
    </div>

    <div class="input-group space-top">
        <span class="input-group-addon" id="basic-addon6">'.$LANG['auth_modification_without_complexity'].'</span>
        <select id="edit_folder_block_modif" class="form-control" aria-describedby="basic-addon6">
            <option value="0">'.$LANG['no'].'</option>
            <option value="1">'.$LANG['yes'].'</option>
        </select>
    </div>

    <div style="margin-top:20px; padding:5px; width:100%;" class="ui-widget-content ui-state-focus ui-corner-all" id="edit_folder_wait">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;'.$LANG['please_wait'].'
    </div>
</div>';

require_once 'folders.load.php';
