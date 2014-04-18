<?php
/**
 *
 * @file 		  find.php
 * @author 		  Nils Laumaillé
 * @version       2.1.19
 * @copyright 	  (c) 2009-2014 Nils Laumaillé
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
    include 'error.php';
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

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

// build select for non personal visible folders
$tree->rebuild();
$folders = $tree->getDescendants();
foreach ($folders as $folder) {
    // Be sure that user can only see folders he/she is allowed to
    if (!in_array($folder->id, $_SESSION['forbiden_pfs'])
            || in_array($folder->id, $_SESSION['groupes_visibles'])
            || in_array($folder->id, $list_folders_limited_keys)
            || in_array($folder->id, $list_restricted_folders_for_items_keys)
    ) {
        $display_this_node = false;
        // Check if any allowed folder is part of the descendants of this node
        $node_descendants = $tree->getDescendants($folder->id, true, false, true);
        foreach ($node_descendants as $node) {
            if (in_array($node, array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items']))
                || in_array($node, $list_folders_limited_keys)
                || in_array($node, $list_restricted_folders_for_items_keys)
            ) {
                $display_this_node = true;
                break;
            }
        }

        if ($display_this_node == true) {
            $ident = "";
            for ($x = 1; $x < $folder->nlevel; $x++) {
                $ident .= "&nbsp;&nbsp;";
            }
            if (isset($_SESSION['all_non_personal_folders']) && in_array($folder->id, $_SESSION['all_non_personal_folders'])) {
                $select_visible_nonpersonal_folders_options .= '<option value="'.$folder->id.'">'.$ident.str_replace("&", "&amp;", $folder->title).'</option>';
            } else {
                $select_visible_nonpersonal_folders_options .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.str_replace("&", "&amp;", $folder->title).'</option>';
            }
        }
    }
}
// Is personal SK available
echo '
<input type="hidden" name="personal_sk_set" id="personal_sk_set" value="', isset($_SESSION['my_sk']) && !empty($_SESSION['my_sk']) ? '1':'0', '" />';
// Show the Items in a table view
echo '<input type="hidden" id="id_selected_item" />
    <input type="hidden" id="personalItem" />
    <div class="title ui-widget-content ui-corner-all">'.$txt['find'].'</div>
<div style="margin:10px auto 25px auto;min-height:250px;" id="find_page">
<table id="t_items" cellspacing="0" cellpadding="5" width="100%">
    <thead><tr>
        <th style="width-max:38px;"></th>
        <th style="width:15%;">'.$txt['label'].'</th>
        <th style="width:20%;">'.$txt['login'].'</th>
        <th style="width:25%;">'.$txt['description'].'</th>
        <th style="width:13%;">'.$txt['tags'].'</th>
        <th style="width:20%;">'.$txt['group'].'</th>
    </tr></thead>
    <tbody>
        <tr><td></td></tr>
    </tbody>
</table>
</div>
<div style="width:100%;text-align:center;margin:1px;border:1px;" class="ui-widget ui-state-highlight ui-corner-all">
    <img src="includes/images/key_copy.png" />&nbsp;'.$txt['item_menu_copy_elem'].'&nbsp;&nbsp;|&nbsp;&nbsp;
    <img src="includes/images/eye.png" />&nbsp;'.$txt['show'].'&nbsp;&nbsp;|&nbsp;&nbsp;
    <img src="includes/images/key__arrow.png" />&nbsp;'.$txt['open_url_link'].'
</div>';
// DIALOG TO WHAT FOLDER COPYING ITEM
echo '
<div id="div_copy_item_to_folder" style="display:none;">
    <div id="copy_item_to_folder_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="">'.$txt['item_copy_to_folder'].'</div>
    <div style="margin:10px;">
        <select id="copy_in_folder">
            <option value="0">---</option>' .
$select_visible_nonpersonal_folders_options .
'</select>
    </div>
</div>';
// DIALOG TO SEE ITEM DATA
echo '
<div id="div_item_data" style="display:none;">
    <div id="div_item_data_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div id="div_item_data_text" style=""></div>
</div>';
// Load file
require_once 'find.load.php';
