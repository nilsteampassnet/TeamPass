<?php
/**
 *
 * @file          items.php
 * @author        Nils Laumaillé
 * @version       2.1.22
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
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

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$tree->rebuild();
$folders = $tree->getDescendants();

if ($_SESSION['user_admin'] == 1 && (isset($k['admin_full_right'])
    && $k['admin_full_right'] == true) || !isset($k['admin_full_right'])) {
    $_SESSION['groupes_visibles'] = $_SESSION['personal_visible_groups'];
    $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
}

// Get list of users
$usersList = array();
$usersString = "";
$rows = DB::query("SELECT id,login,email FROM ".$pre."users ORDER BY login ASC");
foreach ($rows as $record) {
    $usersList[$record['login']] = array(
        "id" => $record['id'],
        "login" => $record['login'],
        "email" => $record['email'],
       );
    $usersString .= $record['id']."#".$record['login'].";";
}
// Get list of roles
$arrRoles = array();
$listRoles = "";
$rows = DB::query("SELECT id,title FROM ".$pre."roles_title ORDER BY title ASC");
foreach ($rows as $reccord) {
    $arrRoles[$reccord['title']] = array(
        'id' => $reccord['id'],
        'title' => $reccord['title']
       );
    if (empty($listRoles)) {
        $listRoles = $reccord['id'].'#'.$reccord['title'];
    } else {
        $listRoles .= ';'.$reccord['id'].'#'.$reccord['title'];
    }
}

// Build list of visible folders
$selectVisibleFoldersOptions = $selectVisibleNonPersonalFoldersOptions = "";
// Hidden things
echo '
<input type="hidden" name="hid_cat" id="hid_cat" value="', isset($_GET['group']) ? htmlspecialchars($_GET['group']) : "", '" value="" />
<input type="hidden" id="complexite_groupe" value="" />
<input type="hidden" name="selected_items" id="selected_items" value="" />
<input type="hidden" id="bloquer_creation_complexite" value="" />
<input type="hidden" id="bloquer_modification_complexite" value="" />
<input type="hidden" id="error_detected" value="" />
<input type="hidden" name="random_id" id="random_id" value="" />
<input type="hidden" id="edit_wysiwyg_displayed" value="" />
<input type="hidden" id="richtext_on" value="1" />
<input type="hidden" id="query_next_start" value="0" />
<input type="hidden" id="display_categories" value="0" />
<input type="hidden" id="nb_items_to_display_once" value="', isset($_SESSION['settings']['nb_items_by_query']) ? htmlspecialchars($_SESSION['settings']['nb_items_by_query']) : 'auto', '" />
<input type="hidden" id="user_is_read_only" value="', isset($_SESSION['user_read_only']) && $_SESSION['user_read_only'] == 1 ? '1' : '', '" />
<input type="hidden" id="request_ongoing" value="" />
<input type="hidden" id="request_lastItem" value="" />
<input type="hidden" id="item_editable" value="" />
<input type="hidden" id="timestamp_item_displayed" value="" />
<input type="hidden" id="pf_selected" value="" />
<input type="hidden" id="user_ongoing_action" value="" />';
// Hidden objects for Item search
if (isset($_GET['group']) && isset($_GET['id'])) {
    echo '
    <input type="hidden" name="open_folder" id="open_folder" value="'.htmlspecialchars($_GET['group']).'" />
    <input type="hidden" name="open_id" id="open_id" value="'.htmlspecialchars($_GET['id']).'" />
    <input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array(htmlspecialchars($_GET['group']), $_SESSION['personal_visible_groups']) ? '1' : '', '" />
    <input type="hidden" name="open_item_by_get" id="open_item_by_get" value="true" />';
} elseif (isset($_GET['group']) && !isset($_GET['id'])) {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="'.htmlspecialchars($_GET['group']).'" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array(htmlspecialchars($_GET['group']), $_SESSION['personal_visible_groups']) ? '1' : '', '" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
} else {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
}
// Is personal SK available
echo '
<input type="hidden" name="personal_sk_set" id="personal_sk_set" value="', isset($_SESSION['my_sk']) && !empty($_SESSION['my_sk']) ? '1':'0', '" />';
// define what group todisplay in Tree
if (isset($_COOKIE['jstree_select']) && !empty($_COOKIE['jstree_select'])) {
    $firstGroup = str_replace("#li_", "", $_COOKIE['jstree_select']);
} else {
    $firstGroup = "";
}

echo '
<input type="hidden" name="jstree_group_selected" id="jstree_group_selected" value="'.htmlspecialchars($firstGroup).'" />';

echo '
<div id="div_items">';
// MAIN ITEMS TREE
echo '
    <div class="items_tree">
        <div>
            <div style="margin:3px;font-weight:bold;">
                '.$LANG['items_browser_title'].'
                <span id="jstree_open" class="pointer" ><img src="includes/images/chevron-small-expand.png" /></span>
                <span id="jstree_close" class="pointer"><img alt="" src="includes/images/chevron-small.png" /></span>
                <input type="text" name="jstree_search" id="jstree_search" class="text ui-widget-content ui-corner-all search_tree" value="'.$LANG['item_menu_find'].'" />
            </div>
        </div>
        <div id="sidebar" class="sidebar">';

$tabItems = array();
$cptTotal = 0;
$folderCpt = 1;
$prevLevel = 1;
if (isset($_COOKIE['jstree_select']) && !empty($_COOKIE['jstree_select'])) {
    $firstGroup = str_replace("#li_", "", $_COOKIE['jstree_select']);
} else {
    $firstGroup = "";
}
if (isset($_SESSION['list_folders_limited']) && count($_SESSION['list_folders_limited']) > 0) {
    $listFoldersLimitedKeys = @array_keys($_SESSION['list_folders_limited']);
} else {
    $listFoldersLimitedKeys = array();
}
// list of items accessible but not in an allowed folder
if (isset($_SESSION['list_restricted_folders_for_items'])
    && count($_SESSION['list_restricted_folders_for_items']) > 0) {
    $listRestrictedFoldersForItemsKeys = @array_keys($_SESSION['list_restricted_folders_for_items']);
} else {
    $listRestrictedFoldersForItemsKeys = array();
}

echo '
            <div id="jstree" style="overflow:auto;">
                <ul id="node_'.$folderCpt.'">'; //
foreach ($folders as $folder) {
    // Be sure that user can only see folders he/she is allowed to
    if (
        !in_array($folder->id, $_SESSION['forbiden_pfs'])
        || in_array($folder->id, $_SESSION['groupes_visibles'])
        || in_array($folder->id, $listFoldersLimitedKeys)
        || in_array($folder->id, $listRestrictedFoldersForItemsKeys)
    ) {
        $displayThisNode = false;
        $hide_node = false;
        $nbChildrenItems = 0;
        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($folder->id, true, false, true);
        foreach ($nodeDescendants as $node) {
            // manage tree counters
            if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1) {
                DB::query(
                    "SELECT * FROM ".$pre."items
                    WHERE inactif=%i AND id_tree = %i",
                    0,
                    $node
                );
                $nbChildrenItems += DB::count();
            }
            if (
                in_array(
                    $node,
                    array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items'])
                )
                || in_array($node, $listFoldersLimitedKeys)
                || in_array($node, $listRestrictedFoldersForItemsKeys)
            ) {
                $displayThisNode = true;
                //break;
            }
        }

        if ($displayThisNode == true) {
            $ident = "";
            for ($x = 1; $x < $folder->nlevel; $x++) {
                $ident .= "&nbsp;&nbsp;";
            }

            DB::query(
                "SELECT * FROM ".$pre."items
                WHERE inactif=%i AND id_tree = %i",
                0,
                $folder->id
            );
            $itemsNb = DB::count();

            // get 1st folder
            if (empty($firstGroup)) {
                $firstGroup = $folder->id;
            }
            // If personal Folder, convert id into user name
            if ($folder->title == $_SESSION['user_id'] && $folder->nlevel == 1) {
                $folder->title = $_SESSION['login'];
            }
        	// resize title if necessary
        	if (strlen($folder->title) > 20) {
        		$fldTitle = substr(str_replace("&", "&amp;", $folder->title), 0, 17)."...";
        	} else {
        		$fldTitle = str_replace("&", "&amp;", $folder->title);
        	}
            // Prepare folder
            $folderTxt = '
                    <li class="jstreeopen" id="li_'.$folder->id.'" title="ID ['.$folder->id.']">';
            if (in_array($folder->id, $_SESSION['groupes_visibles'])) {
                if (in_array($folder->id, $_SESSION['read_only_folders'])) {
                    $fldTitle = '<span style="text-decoration: line-through;">'.$fldTitle.'</span>';
                }
                $folderTxt .= '
                            <a id="fld_'.$folder->id.'" class="folder" onclick="ListerItems(\''.$folder->id.'\', \'\', 0);">'.$fldTitle.' (<span class="items_count" id="itcount_'.$folder->id.'">'.$itemsNb.'</span>';
                // display tree counters
                if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1) {
                    $folderTxt .= '|'.$nbChildrenItems.'|'.(count($nodeDescendants)-1);
                }
                $folderTxt .= ')</a>';
                // case for restriction_to_roles
            } elseif (in_array($folder->id, $listFoldersLimitedKeys)) {
                $folderTxt .= '
                            <a id="fld_'.$folder->id.'" class="folder" onclick="ListerItems(\''.$folder->id.'\', \'1\', 0);">'.$fldTitle.' (<span class="items_count" id="itcount_'.$folder->id.'">'.count($_SESSION['list_folders_limited'][$folder->id]).'</span>)</a>';
            } elseif (in_array($folder->id, $listRestrictedFoldersForItemsKeys)) {
                $folderTxt .= '
                            <a id="fld_'.$folder->id.'" class="folder" onclick="ListerItems(\''.$folder->id.'\', \'1\', 0);">'.$fldTitle.' (<span class="items_count" id="itcount_'.$folder->id.'">'.count($_SESSION['list_restricted_folders_for_items'][$folder->id]).'</span>)</a>';
            } else {
                $folderTxt .= '
                            <a id="fld_'.$folder->id.'">'.$fldTitle.'</a>';
                if (isset($_SESSION['settings']['show_only_accessible_folders']) && $_SESSION['settings']['show_only_accessible_folders'] == 1) {
                    $hide_node = true;
                }
            }
            // build select for all visible folders
            if (in_array($folder->id, $_SESSION['groupes_visibles'])) {
                if ($_SESSION['user_read_only'] == 0 || ($_SESSION['user_read_only'] == 1 && in_array($folder->id, $_SESSION['personal_visible_groups']))) {
                    //$selectVisibleFoldersOptions .= '<option value="'.$folder->id.'">'.$ident.str_replace("&", "&amp;", $folder->title).'</option>';
                    if ($folder->title == $_SESSION['login'] && $folder->nlevel == 1 ) {
                        $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
                    } else {
                        $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'">'.$ident.$fldTitle.'</option>';
                    }
                } else {
                    $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
                }
            } else {
                $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
            }
            // build select for non personal visible folders
            if (isset($_SESSION['all_non_personal_folders']) && in_array($folder->id, $_SESSION['all_non_personal_folders'])) {
                $selectVisibleNonPersonalFoldersOptions .= '<option value="'.$folder->id.'">'.$ident.$fldTitle.'</option>';
            } else {
                $selectVisibleNonPersonalFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
            }
            // build tree
            if ($hide_node == false) {
                if ($cptTotal == 0) {
                    // Force the name of the personal folder with the login name
                    if ($folder->title == $_SESSION['user_id'] && $folder->nlevel == 1) {
                        $folder->title = $_SESSION['login'];
                    }
                    echo $folderTxt;
                    $folderCpt++;
                } else {
                    // show tree
                    if ($prevLevel < $folder->nlevel) {
                        echo '
                    <ul  id="node_'.$folderCpt.'">'.$folderTxt;
                        $folderCpt++;
                    } elseif ($prevLevel == $folder->nlevel) {
                        echo '
                        </li>'.$folderTxt;
                        $folderCpt++;
                    } else {
                        $tmp = '';
                        // Afficher les items de la derni?eres cat s'ils existent
                        for ($x = $folder->nlevel; $x < $prevLevel; $x++) {
                            echo "
                        </li>
                    </ul>";
                        }
                        echo '
                        </li>'.$folderTxt;
                        $folderCpt++;
                    }
                }
            }
            $prevLevel = $folder->nlevel;

            $cptTotal++;
        }
    }
}
// clore toutes les balises de l'arbo
for ($x = 1; $x < $prevLevel; $x++) {
    echo "
                </li>
            </ul>";
}
echo '
                </li>
            </ul>
        </div>
        </div>
    </div>';
// Zone top right - items list
echo '
    <div id="items_content">
        <div id="items_center">
            <div id="items_path" class="ui-corner-all"><img src="includes/images/folder-open.png" />&nbsp;<span id="items_path_var"></span></div>
            <div id="items_list_loader" style="display:none; float:right;margin:-26px 10px 0 0; z-idex:1000;"><img src="includes/images/76.gif" /></div>
            <!--<div id="alpha_select">
                <span id="A" onclick="items_list_filter($(this).attr(\'id\'))">A</span>&nbsp;
                <span id="I" onclick="items_list_filter($(this).attr(\'id\'))">I</span>&nbsp;
                <span id="" onclick="items_list_filter()">**</span>
            </div>-->
            <div id="items_list"></div>
        </div>';
// Zone ITEM DETAIL
echo '
        <div id="item_details_ok">';

echo '
            <input type="hidden" id="id_categorie" value="" />
            <input type="hidden" id="id_item" value="" />
            <input type="hidden" id="hid_anyone_can_modify" value="" />
            <div style="height:210px;overflow-y:auto;" id="item_details_scroll">';
// Info
echo '
                <div style="cursor:pointer; float:right; margin:3px 3px 0 0;" id="item_extra_info"></div>';

echo'
                <div id="item_details_expired" style="display:none;background-color:white; margin:5px;">
                    <div class="ui-state-error ui-corner-all" style="padding:2px;">
                        <img src="includes/images/error.png" alt="" />&nbsp;<b>'.$LANG['pw_is_expired_-_update_it'].'</b>
                    </div>
                </div>
                <table>';
// Line for LABEL
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['label'].' :</td>
                    <td>
                        <input type="hidden" id="hid_label" value="', isset($dataItem) ? htmlspecialchars($dataItem['label']) : '', '" />
                        <div id="id_label" style="display:inline;"></div>
                    </td>
                </tr>';
// Line for DESCRIPTION
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['description'].' :</td>
                    <td>
                        <div id="id_desc" style="font-style:italic;display:inline;"></div><input type="hidden" id="hid_desc" value="', isset($dataItem) ? htmlspecialchars($dataItem['description']) : '', '" />
                    </td>
                </tr>';
// Line for PW
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['pw'].' :</td>
                    <td>
                        <div id="button_quick_pw_copy" style="float:left; width:16px; margin:0 5px 0 -21px; display:none;" title="'.$LANG['pw_copy_clipboard'].'"><img src="includes/images/broom.png" style=" cursor:pointer;" alt="" /></div>
                        <div id="id_pw" style="float:left; cursor:pointer;" onclick="ShowPassword()"></div>
                        <input type="hidden" id="hid_pw" value="" />
                    </td>
                </tr>';
// Line for LOGIN
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['index_login'].' :</td>
                    <td>
                        <div id="button_quick_login_copy" style="float:left; width:16px; margin:0 5px 0 -21px; display:none;" title="'.$LANG['login_copy'].'"><img src="includes/images/broom.png" style=" cursor:pointer;" alt="" /></div>
                        <div id="id_login" style="float:left;"></div>
                        <input type="hidden" id="hid_login" value="" />
                    </td>
                </tr>';
// Line for EMAIL
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['email'].' :</td>
                    <td>
                        <div id="id_email" style="display:inline;"></div><input type="hidden" id="hid_email" value="" />
                    </td>
                </tr>';
// Line for URL
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['url'].' :</td>
                    <td>
                        <div id="id_url" style="display:inline;"></div><input type="hidden" id="hid_url" value="" />
                    </td>
                </tr>';
// Line for FILES
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float:left; margin-right:.3em;">&nbsp;</span>'.$LANG['files_&_images'].' :</td>
                    <td>
                        <div id="id_files" style="display:inline;font-size:11px;"></div><input type="hidden" id="hid_files" />
                        <div id="dialog_files" style="display: none;">

                        </div>
                    </td>
                </tr>';
// Line for RESTRICTED TO
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['restricted_to'].' :</td>
                    <td>
                        <div id="id_restricted_to" style="display:inline;"></div><input type="hidden" id="hid_restricted_to" /><input type="hidden" id="hid_restricted_to_roles" />
                    </td>
                </tr>';
// Line for TAGS
echo '
                <tr>
                    <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['tags'].' :</td>
                    <td>
                        <div id="id_tags" style="display:inline;"></div><input type="hidden" id="hid_tags" />
                    </td>
                </tr>';
// Line for KBs
if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
    echo '
                    <tr>
                        <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['kbs'].' :</td>
                        <td>
                            <div id="id_kbs" style="display:inline;"></div><input type="hidden" id="hid_kbs" />
                        </td>
                    </tr>';
}
// lines for FIELDS
if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
    foreach ($_SESSION['item_fields'] as $elem) {
        $itemCatName = $elem[0];
    echo '
                    <tr class="tr_fields itemCatName_'.$itemCatName.'">
                        <td valign="top" class="td_title"><span class="ui-icon ui-icon-carat-1-e" style="float: left; margin-right: .3em;">&nbsp;</span>'.$elem[1].' :</td>
                        <td></td>
                    </tr>';
        foreach ($elem[2] as $field) {
                    echo '
                    <tr class="tr_fields itemCatName_'.$itemCatName.'">
                        <td valign="top" class="td_title">&nbsp;&nbsp;<span class="ui-icon ui-icon-carat-1-e" style="float: left; margin: 0 .3em 0 15px; font-size:9px;">&nbsp;</span><i>'.$field[1].'</i> :</td>
                        <td>
                            <div id="id_field_'.$field[0].'" style="display:inline;" class="fields_div"></div><input type="hidden" id="hid_field_'.htmlspecialchars($field[0]).'" class="fields" />
                        </td>
                    </tr>';
        }
    }
}
echo '
                </table>
            </div>
        </div>';
// # NOT ALLOWED
echo '
        <div id="item_details_nok" style="display:none; width:300px; margin:20px auto 20px auto;">
            <div class="ui-state-highlight ui-corner-all" style="padding:10px;">
                <img src="includes/images/lock.png" alt="" />&nbsp;<b>'.$LANG['not_allowed_to_see_pw'].'</b>
                <span id="item_details_nok_restriction_list"></span>
            </div>
        </div>';
// DATA EXPIRED
echo '
        <div id="item_details_expired_full" style="display:none; width:300px; margin:20px auto 20px auto;">
            <div class="ui-state-error ui-corner-all" style="padding:10px;">
                <img src="includes/images/error.png" alt="" />&nbsp;<b>'.$LANG['pw_is_expired_-_update_it'].'</b>
            </div>
        </div>';
// # NOT ALLOWED
echo '
        <div id="item_details_no_personal_saltkey" style="display:none; width:300px; margin:20px auto 20px auto; height:180px;">
            <div class="ui-state-highlight ui-corner-all" style="padding:10px;">
                <img src="includes/images/lock.png" alt="" />&nbsp;<b>'.$LANG['home_personal_saltkey_info'].'</b>
                <br />
                <div style="text-align:center;">
                    <u><a href="index.php">'.$LANG['home'].'</a></u>
                </div>
            </div>
        </div>';

echo '
    </div>';

echo '
</div>';


/********************************
* NEW Item Form
*/
echo '
<div id="div_formulaire_saisi" style="display:none;">
    <form method="post" name="new_item" action="">
        <div id="afficher_visibilite" style="text-align:center;margin-bottom:6px;height:20px;"></div>
        <div id="display_title" style="text-align:center;margin-bottom:6px;font-size:17px;font-weight:bold;height:25px;"></div>
        <div id="new_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>

        <div id="item_tabs">
        <ul>
            <li><a href="#tabs-01">'.$LANG['definition'].'</a></li>
            <li><a href="#tabs-02">'.$LANG['index_password'].' &amp; '.$LANG['visibility'].'</a></li>
            <li><a href="#tabs-03">'.$LANG['files_&_images'].'</a></li>
            ', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ?
            '<li id="form_tab_fields"><a href="#tabs-04">'.$LANG['more'].'</a></li>' :
            '', '
        </ul>
        <div id="tabs-01">';
// Line for LABEL
echo '
            <label for="" class="label_cpm">'.$LANG['label'].' : </label>
            <input type="text" name="label" id="label" onchange="checkTitleDuplicate(this.value, \'', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? 0 : 1, '\', \'', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? 0 : 1, '\', \'display_title\')" class="item_field input_text text ui-widget-content ui-corner-all" />';
// Line for DESCRIPTION
echo '
            <label for="" class="label_cpm">'.$LANG['description'].' : </label>
            <span id="desc_span">
                <textarea rows="5" name="desc" id="desc" class="input_text"></textarea>
            </span>
            <br />';
// Line for FOLDERS
echo '
            <label for="" class="">'.$LANG['group'].' : </label>
            <select name="categorie" id="categorie" onChange="RecupComplexite(this.value,0)" style="width:200px">' .
$selectVisibleFoldersOptions .
'</select>';
// Line for LOGIN
echo '
            <label for="" class="label_cpm" style="margin-top:10px;">'.$LANG['login'].' : </label>
            <input type="text" name="item_login" id="item_login" class="input_text text ui-widget-content ui-corner-all item_field" />';
// Line for EMAIL
echo '
            <label for="" class="label_cpm">'.$LANG['email'].' : </label>
            <input type="text" name="email" id="email" class="input_text text ui-widget-content ui-corner-all item_field" />';
// Line for URL
echo '
            <label for="" class="label_cpm">'.$LANG['url'].' : </label>
            <input type="text" name="url" id="url" class="input_text text ui-widget-content ui-corner-all item_field" />
        </div>';
// Tabs Items N?2
echo '
        <div id="tabs-02">';
// Line for folder complexity
echo'
            <div style="margin-bottom:10px;">
                <label for="" class="form_label_180">'.$LANG['complex_asked'].'</label>
                <span id="complex_attendue" style="color:#D04806; margin-left:40px;"></span>
            </div>';
// Line for PW
echo '
            <label class="label_cpm">'.$LANG['used_pw'].' :<span id="prout"></span>
				<span id="visible_pw" style="display:none;margin-left:10px;font-weight:bold;"></span>
                <span id="pw_wait" style="display:none;margin-left:10px;"><img src="includes/images/ajax-loader.gif" /></span>
            </label>
            <input type="password" id="pw1" class="input_text text ui-widget-content ui-corner-all item_field" />
            <input type="hidden" id="mypassword_complex" />
            <label for="" class="label_cpm">'.$LANG['index_change_pw_confirmation'].' :</label>
            <input type="password" name="pw2" id="pw2" class="input_text text ui-widget-content ui-corner-all item_field" />

            <div style="font-size:9px; text-align:center; width:100%;">
                <span id="custom_pw">
                    <input type="checkbox" id="pw_numerics" /><label for="pw_numerics">123</label>
                    <input type="checkbox" id="pw_maj" /><label for="pw_maj">ABC</label>
                    <input type="checkbox" id="pw_symbols" /><label for="pw_symbols">@#&amp;</label>
                    <input type="checkbox" id="pw_secure" checked /><label for="pw_secure">'.$LANG['secure'].'</label>
                    &nbsp;<label for="pw_size">'.$LANG['size'].' : </label>
                    &nbsp;<input type="text" size="2" id="pw_size" value="8" style="font-size:10px;" />
                </span>
                <a href="#" title="'.$LANG['pw_generate'].'" onclick="pwGenerate(\'\')" class="cpm_button tip">
                    <img  src="includes/images/arrow_refresh.png"  />
                </a>
                <a href="#" title="'.$LANG['copy'].'" onclick="pwCopy(\'\')" class="cpm_button tip">
                    <img  src="includes/images/paste_plain.png"  />
                </a>
                <a href="#" title="'.$LANG['mask_pw'].'" onclick="ShowPasswords_Form()" class="cpm_button tip">
                    <img  src="includes/images/eye.png"  />
                </a>
            </div>
            <div style="width:100%;">
                <div id="pw_strength" style="margin:5px 0 5px 120px;"></div>
            </div>';

// Line for RESTRICTED TO
if (isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1) {
    echo '
            <label for="" class="label_cpm">'.$LANG['restricted_to'].' : </label>
            <select name="restricted_to_list" id="restricted_to_list" multiple="multiple"></select>
            <input type="hidden" name="restricted_to" id="restricted_to" />
            <div style="line-height:10px;">&nbsp;</div>';
}
// Line for TAGS
echo '
            <label for="" class="label_cpm">'.$LANG['tags'].' : </label>
            <input type="text" name="item_tags" id="item_tags" class="input_text text ui-widget-content ui-corner-all" />';
// Line for Item modification
echo '
            <div style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="anyone_can_modify" id="anyone_can_modify"',
                    isset($_SESSION['settings']['anyone_can_modify_bydefault'])
                    && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ?
                    ' checked="checked"' : '', ' />
                <label for="anyone_can_modify">'.$LANG['anyone_can_modify'].'</label>
            </div>';
// Line for Item automatically deleted
echo '
            <div style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="enable_delete_after_consultation" id="enable_delete_after_consultation" />
                <label for="enable_delete_after_consultation">'.$LANG['enable_delete_after_consultation'].'</label>
                <input type="text" value="1" size="1" id="times_before_deletion" />&nbsp;'.$LANG['times'].'&nbsp;
                '.$LANG['automatic_del_after_date_text'].'&nbsp;<input type="text" value="" class="datepicker" readonly="readonly" size="10" id="deletion_after_date" onChange="$(\'#times_before_deletion\').val(\'\')" />
            </div>';
// Line for EMAIL
echo '
            <input type="checkbox" name="annonce" id="annonce" onChange="toggleDiv(\'annonce_liste\')" />
            <label for="annonce">'.$LANG['email_announce'].'</label>
            <div style="display:none; border:1px solid #808080; margin-left:30px; margin-top:6px;padding:5px;" id="annonce_liste">
                <h3>'.$LANG['email_select'].'</h3>
                <select id="annonce_liste_destinataires" multiple="multiple" size="10">';
foreach ($usersList as $user) {
    echo '<option value="'.$user['email'].'">'.$user['login'].'</option>';
}
echo '
                </select>
            </div>
        </div>';
// Tabs EDIT N?3
echo '
        <div id="tabs-03">
            <div id="item_upload">
                <div id="item_upload_list"></div><br />
                <div id="item_upload_wait" class="ui-state-focus ui-corner-all" style="display:none;padding:2px;margin:5px 0 5px 0;">'.$LANG['please_wait'].'...</div>
                <a id="item_attach_pickfiles" href="#" class="button">'.$LANG['select'].'</a>
                <a id="item_attach_uploadfiles" href="#" class="button">'.$LANG['start_upload'].'</a>
            </div>
        </div>';
// Tabs N°4
if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
echo '
        <div id="tabs-04">
            <div id="item_more">';
                // load all categories and fields
                foreach ($_SESSION['item_fields'] as $elem) {
                    $itemCatName = $elem[0];
                    echo '
                    <div id="newItemCatName_'.$itemCatName.'" class="newItemCat">
                        <div style="font-weight:bold;font-size:12px;">
                            <span class="ui-icon ui-icon-folder-open" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$elem[1].'
                        </div>';
                    foreach ($elem[2] as $field) {
                        echo '
                        <div style="margin:2px 0 2px 15px;">
                            <span class="ui-icon ui-icon-tag" style="float: left; margin-right: .1em;">&nbsp;</span>
                            <label class="cpm_label">'.$field[1].'</span>
                            <input type="text" id="field_'.$field[0].'" class="item_field input_text text ui-widget-content ui-corner-all" size="40">
                        </div>';
                    }
                    echo '
                    </div>';
                }
            echo '
            </div>
        </div>';
}
echo '
    </div>';
echo '
    </form>
    <div style="display:none;" id="div_formulaire_saisi_info" class="ui-state-default ui-corner-all"></div>
</div>';

/***************************
* Edit Item Form
*/
echo '
<div id="div_formulaire_edition_item" style="display:none;">
    <form method="post" name="form_edit" action="">
    <div id="edit_afficher_visibilite" style="text-align:center;margin-bottom:6px;height:25px;"></div>
    <div id="edit_display_title" style="text-align:center;margin-bottom:6px;font-size:17px;font-weight:bold;height:25px;"></div>
    <div id="edit_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="display:none;" id="div_formulaire_edition_item_info" class="ui-state-default ui-corner-all"></div>';
// Prepare TABS
echo '
    <div id="item_edit_tabs">
        <ul>
            <li><a href="#tabs-1">'.$LANG['definition'].'</a></li>
            <li><a href="#tabs-2">'.$LANG['index_password'].' &amp; '.$LANG['visibility'].'</a></li>
            <li><a href="#tabs-3">'.$LANG['files_&_images'].'</a></li>
            ', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ?
            '<li id="form_edit_tab_fields"><a href="#tabs-4">'.$LANG['more'].'</a></li>' :
            '', '
        </ul>
        <div id="tabs-1">
            <label for="" class="cpm_label">'.$LANG['label'].' : </label>
            <input type="text" size="60" id="edit_label" onchange="checkTitleDuplicate(this.value, \'', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? 0 : 1, '\', \'', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? 0 : 1, '\', \'edit_display_title\')" class="input_text text ui-widget-content ui-corner-all" />

            <label for="" class="cpm_label">'.$LANG['description'].'&nbsp;<img src="includes/images/broom.png" style="cursor:pointer;" onclick="clear_html_tags()" /> </label>
            <span id="edit_desc_span">
                <textarea rows="5" id="edit_desc" name="edit_desc" class="input_text"></textarea>
            </span>';
// Line for FOLDER
echo '
            <div style="margin:10px 0px 10px 0px;">
            <label for="" class="">'.$LANG['group'].' : </label>
            <select id="edit_categorie" onChange="RecupComplexite(this.value,1)" style="width:200px;">' .
$selectVisibleFoldersOptions .
'
            </select>
            </div>';
// Line for LOGIN
echo '
            <label for="" class="cpm_label">'.$LANG['login'].' : </label>
            <input type="text" id="edit_item_login" class="input_text text ui-widget-content ui-corner-all" />

            <label for="" class="cpm_label">'.$LANG['email'].' : </label>
            <input type="text" id="edit_email" class="input_text text ui-widget-content ui-corner-all" />

            <label for="" class="cpm_label">'.$LANG['url'].' : </label>
            <input type="text" id="edit_url" class="input_text text ui-widget-content ui-corner-all" />
        </div>';
// TABS edit n?2
echo '
        <div id="tabs-2">';
// Line for folder complexity
echo'
            <div style="margin-bottom:10px;">
                <label for="" class="cpm_label">'.$LANG['complex_asked'].'</label>
                <span id="edit_complex_attendue" style="color:#D04806;"></span>
            </div>';

echo '
            <div style="line-height:20px;">
                <label for="" class="label_cpm">'.$LANG['used_pw'].' :
					<span id="edit_visible_pw" style="display:none;margin-left:10px;font-weight:bold;"></span>
                    <span id="edit_pw_wait" style="display:none;margin-left:10px;"><img src="includes/images/ajax-loader.gif" /></span>
                </label>
                <input type="password" id="edit_pw1" class="input_text text ui-widget-content ui-corner-all" style="width:405px;" />
                <input type="hidden" id="edit_mypassword_complex" />
                <img src="includes/images/clipboard-list.png" style="cursor:pointer;" class="tip" id="edit_past_pwds" />

                <label for="" class="cpm_label">'.$LANG['confirm'].' : </label>
                <input type="password" size="30" id="edit_pw2" class="input_text text ui-widget-content ui-corner-all" />
            </div>
            <div style="font-size:9px; text-align:center; width:100%;">
                <span id="edit_custom_pw">
                    <input type="checkbox" id="edit_pw_numerics" /><label for="edit_pw_numerics">123</label>
                    <input type="checkbox" id="edit_pw_maj" /><label for="edit_pw_maj">ABC</label>
                    <input type="checkbox" id="edit_pw_symbols" /><label for="edit_pw_symbols">@#&amp;</label>
                    <input type="checkbox" id="edit_pw_secure" checked /><label for="edit_pw_secure">'.$LANG['secure'].'</label>
                    &nbsp;<label for="edit_pw_size">'.$LANG['size'].' : </label>
                    &nbsp;<input type="text" size="2" id="edit_pw_size" value="8" style="font-size:10px;" />
                </span>
                <a href="#" title="'.$LANG['pw_generate'].'" onclick="pwGenerate(\'edit\')" class="cpm_button tip">
                    <img  src="includes/images/arrow_refresh.png"  />
                </a>
                <a href="#" title="'.$LANG['copy'].'" onclick="pwCopy(\'edit\')" class="cpm_button tip">
                    <img  src="includes/images/paste_plain.png"  />
                </a>
                <a href="#" title="'.$LANG['mask_pw'].'" onclick="ShowPasswords_EditForm()" class="cpm_button tip">
                    <img  src="includes/images/eye.png"  />
                </a>
            </div>
            <div style="width:100%;">
                <div id="edit_pw_strength" style="margin:5px 0 5px 120px;"></div>
            </div>';

if (isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1) {
    echo '
            <div id="div_editRestricted">
                <label for="" class="label_cpm">'.$LANG['restricted_to'].' : </label>
                <select name="edit_restricted_to_list" id="edit_restricted_to_list" multiple="multiple"></select>
                <input type="hidden" size="50" name="edit_restricted_to" id="edit_restricted_to" />
            <input type="hidden" size="50" name="edit_restricted_to_roles" id="edit_restricted_to_roles" />
            <div style="line-height:10px;">&nbsp;</div>
            </div>';
}

echo '
            <label for="" class="cpm_label">'.$LANG['tags'].' : </label>
            <input type="text" size="50" name="edit_tags" id="edit_tags" class="input_text text ui-widget-content ui-corner-all" />';
// Line for Item modification
echo '
            <div style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="edit_anyone_can_modify" id="edit_anyone_can_modify"',
                    isset($_SESSION['settings']['anyone_can_modify_bydefault'])
                    && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ?
                    ' checked="checked"' : '', ' />
                <label for="edit_anyone_can_modify">'.$LANG['anyone_can_modify'].'</label>
            </div>';
// Line for Item automatically deleted
echo '
            <div id="edit_to_be_deleted" style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="edit_enable_delete_after_consultation" id="edit_enable_delete_after_consultation" />
                <label for="edit_enable_delete_after_consultation">'.$LANG['enable_delete_after_consultation'].'</label>
                <input type="text" value="" size="1" id="edit_times_before_deletion" onChange="$(\'#edit_deletion_after_date\').val(\'\')" />&nbsp;'.$LANG['times'].'&nbsp;
                '.$LANG['automatic_del_after_date_text'].'&nbsp;<input type="text" value="" class="datepicker" readonly="readonly" size="10" id="edit_deletion_after_date" onChange="$(\'#edit_times_before_deletion\').val(\'\')" />
            </div>';

echo '
            <input type="checkbox" name="edit_annonce" id="edit_annonce" onChange="toggleDiv(\'edit_annonce_liste\')" />
            <label for="edit_annonce">'.$LANG['email_announce'].'</label>
            <div style="display:none; border:1px solid #808080; margin-left:30px; margin-top:3px;padding:5px;" id="edit_annonce_liste">
                <h3>'.$LANG['email_select'].'</h3>
                <select id="edit_annonce_liste_destinataires" multiple="multiple" size="10">';
foreach ($usersList as $user) {
    echo '<option value="'.$user['email'].'">'.$user['login'].'</option>';
}
echo '
                </select>
            </div>
        </div>';
// Tab EDIT N°3
echo '
        <div id="tabs-3">
            <div style="font-weight:bold;font-size:12px;">
                <span class="ui-icon ui-icon-folder-open" style="float: left; margin-right: .3em;">&nbsp;</span>
                '.$LANG['uploaded_files'].'
            </div>
            <div id="item_edit_list_files" style="margin-left:25px;"></div>
            <div style="margin-top:10px;font-weight:bold;font-size:12px;">
                <span class="ui-icon ui-icon-folder-open" style="float: left; margin-right: .3em;">&nbsp;</span>
                '.$LANG['upload_files'].'
            </div>
            <div id="item_edit_upload">
                <div id="item_edit_upload_list"></div><br />
                <div id="item_edit_upload_wait" class="ui-state-focus ui-corner-all" style="display:none;padding:2px;margin:5px 0 5px 0;">'.$LANG['please_wait'].'...</div>
                <a id="item_edit_attach_pickfiles" href="#" class="button">'.$LANG['select'].'</a>
                <a id="item_edit_attach_uploadfiles" href="#" class="button">'.$LANG['start_upload'].'</a>
            </div>
        </div>';
// Tabs EDIT N°4 -> Categories
if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
echo '
        <div id="tabs-4">
            <div id="edit_item_more">';
                // load all categories and fields
                foreach ($_SESSION['item_fields'] as $elem) {
                    echo '
                    <div class="editItemCat" id="editItemCatName_'.$elem[0].'">
                        <div style="font-weight:bold;font-size:12px;">
                            <span class="ui-icon ui-icon-folder-open" style="float: left; margin-right: .3em;">&nbsp;</span>
                            '.$elem[1].'
                        </div>';
                    foreach ($elem[2] as $field) {
                        echo '
                        <div style="margin:2px 0 2px 15px;">
                            <span class="ui-icon ui-icon-tag" style="float: left; margin-right: .1em;">&nbsp;</span>
                            <label class="cpm_label">'.$field[1].'</label>
                            <input type="text" id="edit_field_'.$field[0].'" class="edit_item_field input_text text ui-widget-content ui-corner-all" size="40">
                        </div>';
                    }
                    echo '
                    </div>';
                }
            echo '
            </div>
        </div>
    </div>';
}
echo '
    </div>
    </form>
</div>';

/*
* ADD NEW FOLDER form
*/
echo '
<div id="div_ajout_rep" style="display:none;">
    <div id="new_rep_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <table>
        <tr>
            <td>'.$LANG['label'].' : </td>
            <td><input type="text" size="20" id="new_rep_titre" /></td>
        </tr>
        <tr>
            <td>'.$LANG['sub_group_of'].' : </td>
            <td><select id="new_rep_groupe">
                ', (isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1) ?
                '<option value="0">---</option>' : '', '' .
                $selectVisibleFoldersOptions .'
            </select></td>
        </tr>
        <tr>
            <td>'.$LANG['complex_asked'].' : </td>
            <td><select id="new_rep_complexite">';
foreach ($pwComplexity as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
            </select>
        </tr>';
/*
        if (count($_SESSION['arr_roles'])>1) {
            echo '
            <tr>
                <td>'.$LANG['associated_role'].'</td>
                <td><select id="new_rep_role">';
                foreach ($_SESSION['arr_roles'] as $role)
                    echo '<option value="'.$role['id'].'">'.$role['title'].'</option>';
                echo '
                </select>
            </tr>';
        }
       */
echo '
    </table>
</div>';
// Formulaire EDITER REPERTORIE
echo '
<div id="div_editer_rep" style="display:none;">
    <div id="edit_rep_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <table>
        <tr>
            <td>'.$LANG['new_label'].' : </td>
            <td><input type="text" size="20" id="edit_folder_title" /></td>
        </tr>
        <tr>
            <td>'.$LANG['group_select'].' : </td>
            <td><select id="edit_folder_folder">
                <option value="0">-choisir-</option>' .
$selectVisibleFoldersOptions .
'
            </select></td>
        </tr>
        <tr>
            <td>'.$LANG['complex_asked'].' : </td>
            <td><select id="edit_folder_complexity">
                <option value="">---</option>';
foreach ($pwComplexity as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
            </select>
        </tr>
    </table>
</div>';
// Formulaire SUPPRIMER REPERTORIE
echo '
<div id="div_supprimer_rep" style="display:none;">
    <table>
        <tr>
            <td>'.$LANG['group_select'].' : </td>
            <td><select id="delete_rep_groupe">
                <option value="0">-choisir-</option>' .
$selectVisibleFoldersOptions .
'
            </select></td>
        </tr>
    </table>
</div>';
// SUPPRIMER UN ELEMENT
echo '
<div id="div_del_item" style="display:none;">
    <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;">&nbsp;</span>'.$LANG['confirm_deletion'].'</p>
</div>';
// DIALOG INFORM USER THAT LINK IS COPIED
echo '
<div id="div_item_copied" style="display:none;">
    <p>
        <span class="ui-icon ui-icon-info" style="float:left; margin:0 7px 20px 0;">&nbsp;</span>'.$LANG['link_is_copied'].'
    </p>
    <div id="div_display_link"></div>
</div>';
// DIALOG TO WHAT FOLDER COPYING ITEM
echo '
<div id="div_copy_item_to_folder" style="display:none;">
    <div id="copy_item_to_folder_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="">'.$LANG['item_copy_to_folder'].'</div>
    <div style="margin:10px;">
        <select id="copy_in_folder">
            ', (isset($_SESSION['can_create_root_folder']) && $_SESSION['can_create_root_folder'] == 1) ? '<option value="0">---</option>' : '', '' .
$selectVisibleNonPersonalFoldersOptions .
'</select>
    </div>
</div>';
// DIALOG FOR HISTORY OF ITEM
echo '
<div id="div_item_history" style="display:none;">
    <div id="item_history_log"></div>
    ', (isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1) ?
'<div id="new_history_entry_form" style="display:none; margin-top:10px;"><hr>
        <div id="div_add_history_entry">
            <div id="item_history_log_error"></div>
            '.$LANG['label'].'&nbsp;<input type="text" id="add_history_entry_label" size="40" />&nbsp;
            <span class="button" style="margin-top:6px;" onclick="manage_history_entry(\'add_entry\',\'\')">'.$LANG['add_history_entry'].'</div>
        </div>
    </div>'
:'', '
</div>';
// DIALOG FOR ITEM SHARE
echo '
<div id="div_item_share" style="display:none;">
    <div id="div_item_share_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="">'.$LANG['item_share_text'].'</div>
    <input type="text" id="item_share_email" class="ui-corner-all" style="width:100%;" />
    <div id="div_item_share_status" style="text-align:center;margin-top:15px;display:none;" class="ui-corner-all"><img src="includes/images/76.gif" /></div>
</div>';
// DIALOG FOR ITEM IS UPDATED
echo '
<div id="div_item_updated" style="display:none;">
    <div style="">'.$LANG['item_updated_text'].'</div>
</div><br />';

require_once 'items.load.php';
