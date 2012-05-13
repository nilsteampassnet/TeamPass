<?php
/**
 * @file 		find.php
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


//Build list of visible folders
$select_visible_folders_options = $select_visible_nonpersonal_folders_options = "";
if (isset($_SESSION['list_folders_limited']) && count($_SESSION['list_folders_limited']) > 0) {
	$list_folders_limited_keys = @array_keys($_SESSION['list_folders_limited']);
}else{
	$list_folders_limited_keys = array();
}
//list of items accessible but not in an allowed folder
if (isset($_SESSION['list_restricted_folders_for_items']) && count($_SESSION['list_restricted_folders_for_items']) > 0) {
	$list_restricted_folders_for_items_keys = @array_keys($_SESSION['list_restricted_folders_for_items']);
}else{
	$list_restricted_folders_for_items_keys = array();
}

//build select for non personal visible folders
require_once ("sources/NestedTree.class.php");
$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$tree->rebuild();
$folders = $tree->getDescendants();
foreach($folders as $folder){
	//Be sure that user can only see folders he/she is allowed to
	if ( !in_array($folder->id, $_SESSION['forbiden_pfs']) || in_array($folder->id, $_SESSION['groupes_visibles']) ||
	in_array($folder->id, $list_folders_limited_keys) || in_array($folder->id, $list_restricted_folders_for_items_keys)
	) {
		$display_this_node = false;
		// Check if any allowed folder is part of the descendants of this node
		$node_descendants = $tree->getDescendants($folder->id, true, false, true);
		foreach ($node_descendants as $node){
			if (in_array($node, array_merge($_SESSION['groupes_visibles'],$_SESSION['list_restricted_folders_for_items']))
				|| in_array($node, $list_folders_limited_keys) || in_array($node, $list_restricted_folders_for_items_keys)
			) {
				$display_this_node = true;
				break;
			}
		}

		if ($display_this_node == true) {
			$ident="";
			for($x=1;$x<$folder->nlevel;$x++) $ident .= "&nbsp;&nbsp;";
			if (isset($_SESSION['all_non_personal_folders']) && in_array($folder->id,$_SESSION['all_non_personal_folders'])) {
				$select_visible_nonpersonal_folders_options .= '<option value="'.$folder->id.'">'.$ident.str_replace("&","&amp;",$folder->title).'</option>';
			}else{
				$select_visible_nonpersonal_folders_options .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.str_replace("&","&amp;",$folder->title).'</option>';
			}
		}
	}
}

//Show the Items in a table view
echo '<input type="hidden" id="id_selected_item" />
    <div class="title ui-widget-content ui-corner-all">'.$txt['find'].'</div>
<div style="margin:10px auto 25px auto;min-height:250px;" id="find_page">
<table id="t_items" cellspacing="0" cellpadding="5" width="100%">
    <thead><tr>
        <th style="width-max:34px;"></th>
        <th style="width:15%;">'.$txt['label'].'</th>
		<th style="width:20%;">'.$txt['login'].'</th>
        <th style="width:25%;">'.$txt['description'].'</th>
        <th style="width:15%;">'.$txt['tags'].'</th>
        <th style="width:20%;">'.$txt['group'].'</th>
    </tr></thead>
    <tbody>
    	<tr><td></td></tr>
    </tbody>
</table>
</div>';

//DIALOG TO WHAT FOLDER COPYING ITEM
echo '
<div id="div_copy_item_to_folder" style="display:none;">
    <div id="copy_item_to_folder_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="">'.$txt['item_copy_to_folder'].'</div>
	<div style="margin:10px;">
		<select id="copy_in_folder">
            <option value="0">---</option>'.
    		$select_visible_nonpersonal_folders_options.
		'</select>
	</div>
</div>';

//DIALOG TO SEE ITEM DATA
echo '
<div id="div_item_data" style="display:none;">
    <div id="div_item_data_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style=""></div>
</div>';
    		

//Load file
require_once ("find.load.php");
?>