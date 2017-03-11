<?php
/**
 * @file          suggestion.php
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
                ($listFoldersLimitedKeys != null || is_array($listFoldersLimitedKeys)) &&
                (
                    in_array(
                        $node,
                        array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items'])
                    )
                    || in_array($node, $listFoldersLimitedKeys)
                    || in_array($node, $listRestrictedFoldersForItemsKeys)
                )
            ) {
                $displayThisNode = true;
            }
        }

        if ($displayThisNode == true) {
            $ident = "";
            for ($x = 1; $x < $folder->nlevel; $x++) {
                //$ident .= "&nbsp;&nbsp;";
                $ident .= '<i class="fa fa-angle-right"></i>&nbsp;';
            }
            // get 1st folder
            if (empty($firstGroup)) {
                $firstGroup = $folder->id;
            }
            // If personal Folder, convert id into user name
            if (!($folder->title == $_SESSION['user_id'] && $folder->nlevel == 1)) {
                // resize title if necessary
                /*if (strlen($folder->title) > 40) {
                    $fldTitle = substr(str_replace("&", "&amp;", $folder->title), 0, 37)."...";
                } else {
                    $fldTitle = str_replace("&", "&amp;", $folder->title);
                }*/
                $fldTitle = str_replace("&", "&amp;", $folder->title);

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
    <button title="'.$LANG['suggestion_add'].'" onclick="OpenDialog(\'suggestion_form\'); $(\'#tabs\').tabs({ active: 0 });" class="button" style="font-size:16px;">
        <i class="fa fa-plus"></i>
    </button>
</div>';

// prepare tabs
echo '
<div id="tabs">
    <ul>
        <li><a href="#tabs-1">'.$LANG['show_suggestions'].'</a></li>
        <li><a href="#tabs-2">'.$LANG['show_changes'].'</a></li>
    </ul>
    <div id="tabs-1">
        <div style="margin:10px auto 25px auto;min-height:250px;" id="kb_page">
            <table id="t_suggestion" class="hover items_table" width="100%">
                <thead><tr>
                    <th></th>
                    <th>'.$LANG['label'].'</th>
                    <th>'.$LANG['description'].'</th>
                    <th>'.$LANG['group'].'</th>
                    <th>'.$LANG['author'].'</th>
                    <th>'.$LANG['comment'].'</th>
                </tr></thead>
                <tbody>
                    <tr><td></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="tabs-2">
        <div style="margin:10px auto 25px auto;min-height:250px;" id="kb_page_changes">
            <table id="t_change" class="hover items_table" width="100%" style="">
                <thead><tr>
                    <th></th>
                    <th>'.$LANG['author'].'</th>
                    <th>'.$LANG['date'].'</th>
                    <th>'.$LANG['item_id'].'</th>
                    <th>'.$LANG['label'].'</th>
                    <th>'.$LANG['group'].'</th>
                    <th>'.$LANG['comment'].'</th>
                </tr></thead>
                <tbody>
                    <tr><td></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>';

/* DIV FOR ADDING A SUGGESTION */
echo '
<div id="suggestion_form" style="display:none;">
    <div id="suggestion_error" class="ui-widget-content ui-corner-all" style="display:none;padding:3px;"></div>

    <label for="suggestion_label" class="label_cpm">'.$LANG['label'].'</label>
    <input type="text" id="suggestion_label" class="input text ui-widget-content ui-corner-all" style="width:100%;" />

    <label for="suggestion_description" class="label_cpm">'.$LANG['description'].'</label>
    <textarea rows="2" cols="70" name="suggestion_description" id="suggestion_description" class="input" style="width:100%;"></textarea>
    <br />

    <label for="suggestion_folder" class="label_cpm">'.$LANG['group'].'</label>
    <select name="suggestion_folder" id="suggestion_folder" onchange="GetRequiredComplexity()" style="width:100%;">
        '.$selectVisibleFoldersOptions.'
    </select>
    <br /><br />

    <label for="suggestion_pwd" class="label_cpm">'.$LANG['index_password'].
    '&nbsp;
    <span id="pw_wait" style="display:none;"><span class="fa fa-cog fa-spin fa-1x"></span></span>
    <span id="complexity_required_text"></span>
    </label>
    <input type="password" id="suggestion_pwd" class="input text ui-widget-content ui-corner-all" style="width:100%;" />
    <div style="width:100%;">
        <input type="hidden" id="complexity_required" />
        <div id="pw_strength" style="margin:5px 0 5px 120px;"></div>
        <input type="hidden" id="password_complexity" />
    </div>

    <label for="suggestion_comment" class="label_cpm">'.$LANG['comment'].'</label>
    <textarea rows="2" cols="70" name="suggestion_comment" id="suggestion_comment" class="input" style="width:100%;"></textarea>

    <div style="padding:5px; z-index:9999999; width:100%;" class="ui-widget-content ui-state-focus ui-corner-all" id="add_suggestion_wait">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;'.$LANG['please_wait'].'
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
    <div style="padding:5px; z-index:9999999;" class="ui-widget-content ui-state-focus ui-corner-all" id="suggestion_edit_wait">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;'.$LANG['please_wait'].'
    </div>
    <div style="margin:5px 0 5px 0; text-align:center; font-size:15px; font-weight:bold;" id="suggestion_add_label"></div>
    <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;">&nbsp;</span>'.$LANG['suggestion_validate'].'</p>
    <div style="display:none;margin-top:10px;" id="suggestion_is_duplicate">'.$LANG['suggestion_is_duplicate'].'</div>
</div>';

//VIEW DIALOG
echo '
<div id="div_suggestion_view" style="display:none;">
    <div style=" margin-top:10px;" id="div_suggestion_html"></div>
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
        openKB('.filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT).');
        -->
        </script>';
}
