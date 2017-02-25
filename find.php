<?php
/**
 *
 * @file 		  find.php
 * @author 		  Nils Laumaillé
 * @version       2.1.27
 * @copyright 	  (c) 2009-2017 Nils Laumaillé
 * @licensing	  GNU AFFERO GPL 3.0
 * @link
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

// Build list of visible folders
$select_visible_folders_options = $select_visible_nonpersonal_folders_options = "";
if (isset($_SESSION['list_folders_limited']) && count($_SESSION['list_folders_limited']) > 0) {
    $list_folders_limited_keys = @array_keys($_SESSION['list_folders_limited']);
} else {
    $list_folders_limited_keys = array();
}
// list of items accessible but not in an allowed folder
if (isset($_SESSION['list_restricted_folders_for_items']) && count($_SESSION['list_restricted_folders_for_items']) > 0) {
    $list_restricted_folders_for_items_keys = @array_keys($_SESSION['list_restricted_folders_for_items']);
} else {
    $list_restricted_folders_for_items_keys = array();
}

// Is personal SK available
echo '
<input type="hidden" name="personal_sk_set" id="personal_sk_set" value="', isset($_SESSION['my_sk']) && !empty($_SESSION['my_sk']) ? '1':'0', '" />';

// Show the Items in a table view
echo '<input type="hidden" id="id_selected_item" />
    <input type="hidden" id="personalItem" />
    <div class="title ui-widget-content ui-corner-all">
    '.$LANG['find'].'&nbsp;&nbsp;&nbsp;
    <button title="'.htmlentities(strip_tags($LANG['move_items']), ENT_QUOTES).'" onclick="$(\'#div_mass_op\').data(\'action\', \'move\').dialog(\'open\');" class="button" style="font-size:16px;">
        <i class="fa fa-share"></i>
    </button>&nbsp;
    <button title="'.htmlentities(strip_tags($LANG['delete_items']), ENT_QUOTES).'" onclick="$(\'#div_mass_op\').data(\'action\', \'delete\').dialog(\'open\');" class="button" style="font-size:16px;">
        <i class="fa fa-trash"></i>
    </button>
    </div>
<div style="margin:10px auto 25px auto;min-height:250px;" id="find_page">
<table id="t_items" cellspacing="0" cellpadding="5" width="100%">
    <thead><tr>
        <th></th>
        <th style="width:15%;">'.$LANG['label'].'</th>
        <th style="width:20%;">'.$LANG['login'].'</th>
        <th style="width:25%;">'.$LANG['description'].'</th>
        <th style="width:13%;">'.$LANG['tags'].'</th>
        <th style="width:13%;">'.$LANG['url'].'</th>
        <th style="width:20%;">'.$LANG['group'].'</th>
    </tr></thead>
    <tbody>
        <tr><td></td></tr>
    </tbody>
</table>
</div>';
// DIALOG TO WHAT FOLDER COPYING ITEM
echo '
<div id="div_copy_item_to_folder" style="display:none;">
    <div id="copy_item_to_folder_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="">'.$LANG['item_copy_to_folder'].'</div>
    <div style="margin:10px;">
        <select id="copy_in_folder">
        </select>
    </div>
</div>';
// DIALOG TO SEE ITEM DATA
echo '
<div id="div_item_data" style="display:none;">
    <div id="div_item_data_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div id="div_item_data_text" style=""></div>
</div>';
// DIALOG TO MASS OPERATIONS
echo '
<div id="div_mass_op" style="display:none;">
    <div id="div_mass_html" style=""></div>
    <div id="div_mass_op_msg" style="text-align:center; margin-top:10px; display:none; padding:10px;" class="ui-corner-all"></div>
</div>';
// Load file
require_once 'find.load.php';
