<?php
/**
 * @file          suggestion.php
 * @author        Nils Laumaillé
 * @version       2.1.22
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
    || !isset($_SESSION['settings']['enable_suggestion'])
    || $_SESSION['settings']['enable_suggestion'] != 1)
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

// prepare folders list
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
$selectVisibleFoldersOptions = "<option value=\"\">--".$LANG['select']."--</option>";
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
            if (
                in_array(
                    $node,
                    array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items'])
                )
                || in_array($node, $listFoldersLimitedKeys)
                || in_array($node, $listRestrictedFoldersForItemsKeys)
            ) {
                $displayThisNode = true;
            }
        }

        if ($displayThisNode == true) {
            $ident = "";
            for ($x = 1; $x < $folder->nlevel; $x++) {
                $ident .= "&nbsp;&nbsp;";
            }
            // get 1st folder
            if (empty($firstGroup)) {
                $firstGroup = $folder->id;
            }
            // If personal Folder, convert id into user name
            if (!($folder->title == $_SESSION['user_id'] && $folder->nlevel == 1)) {
                // resize title if necessary
                if (strlen($folder->title) > 20) {
                    $fldTitle = substr(str_replace("&", "&amp;", $folder->title), 0, 17)."...";
                } else {
                    $fldTitle = str_replace("&", "&amp;", $folder->title);
                }

                // build select for all visible folders
                if (in_array($folder->id, $_SESSION['groupes_visibles'])) {
                    $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'">'.$ident.$fldTitle.'</option>';
                } else {
                    $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
                }
            }

            $prevLevel = $folder->nlevel;
        }
    }
}

echo '
<div class="title ui-widget-content ui-corner-all">
    '.$LANG['suggestion'].'&nbsp;&nbsp;&nbsp;
    <button title="'.$LANG['suggestion_add'].'" onclick="OpenDialog(\'suggestion_form\')" id="button_new_suggestion">
        <img src="includes/images/direction_plus.png" alt="" />
    </button>
</div>';

//Show the SUGGESTION in a table view
echo '
<div style="margin:10px auto 25px auto;min-height:250px;" id="kb_page">
<table id="t_suggestion" cellspacing="0" cellpadding="5" width="100%">
    <thead><tr>
        <th style="width:30px;"></th>
        <th style="width:20%;">'.$LANG['label'].'</th>
        <th style="width:30%;">'.$LANG['description'].'</th>
        <th style="width:15%;">'.$LANG['group'].'</th>
        <th style="width:10%;">'.$LANG['author'].'</th>
        <th style="width:15%;">'.$LANG['comment'].'</th>
    </tr></thead>
    <tbody>
        <tr><td></td></tr>
    </tbody>
</table>
</div>';

/* DIV FOR ADDING A SUGGESTION */
echo '
<div id="suggestion_form" style="display:none;">
    <div id="suggestion_error" class="ui-widget-content ui-corner-all" style="display:none;padding:3px;"></div>

    <label for="suggestion_label" class="label_cpm">'.$LANG['label'].'</label>
    <input type="text" id="suggestion_label" class="input text ui-widget-content ui-corner-all" />
    <br />

    <div style="float:left;width:100%;">
        <label for="suggestion_description" class="label_cpm">'.$LANG['description'].'</label>
        <textarea rows="2" name="suggestion_description" id="suggestion_description" class="input"></textarea>
    </div>

    <div style="float:left;width:100%;">
        <label for="suggestion_folder" class="label_cpm">'.$LANG['group'].'</label>
        <select  name="suggestion_folder" id="suggestion_folder" onChange="GetRequiredComplexity()">
            '.$selectVisibleFoldersOptions.'
        </select>
        <div style="margin-bottom:10px;">
            <label for="" class="form_label_180">'.$LANG['complex_asked'].'</label>
            <span id="complexity_required_text" style="color:#D04806; margin-left:40px;"></span>
            <span id="pw_wait" style="display:none;margin-left:10px;"><img src="includes/images/ajax-loader.gif" /></span>
            <input type="hidden" id="complexity_required" />
        </div>
    </div>

    <div style="float:left;width:100%;">
        <label for="suggestion_pwd" class="label_cpm">'.$LANG['index_password'].'</label>
        <input type="password" id="suggestion_pwd" class="input text ui-widget-content ui-corner-all" />
        <input type="hidden" id="password_complexity" />
        <div style="width:100%;">
            <div id="pw_strength" style="margin:5px 0 5px 120px;"></div>
        </div>
    </div>

    <div style="float:left;width:100%;">
        <label for="suggestion_comment" class="label_cpm">'.$LANG['comment'].'</label>
        <textarea rows="2" name="suggestion_comment" id="suggestion_comment" class="input"></textarea>
    </div>
</div>';

//DELETE DIALOG
echo '
<div id="div_suggestion_delete" style="display:none;">
    <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;">&nbsp;</span>'.$LANG['confirm_deletion'].'</p>
</div>';

//CONFIRM DIALOG
echo '
<div id="div_suggestion_validate" style="display:none;">
    <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;">&nbsp;</span>'.$LANG['suggestion_validate'].'</p>
    <div style="display:none;margin-top:10px;" id="suggestion_is_duplicate">'.$LANG['suggestion_is_duplicate'].'</div>
</div>';

// hidden
echo '
<input type="hidden" id="suggestion_id" />';

//Call javascript stuff
require_once 'suggestion.load.php';

//If redirection is done to a speoific KB then open it
if (isset($_GET['id']) && !empty($_GET['id'])) {
    echo '
        <script language="javascript" type="text/javascript">
        <!--
        openKB('.$_GET['id'].');
        -->
        </script>';
}
