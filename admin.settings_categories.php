<?php
/**
 *
 * @file          admin.settings_categories.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sources/sessions.php');
session_start();

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
header("Content-type: text/html; charset=utf-8");
include $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
$db = new SplClassLoader('Database\Core', 'includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$tree->rebuild();

/*
* Loads the Item Categories
*/

//Build tree of Categories
$categoriesSelect = "";
$arrCategories = array();
$rows = $db->fetchAllArray("SELECT * FROM ".$pre."categories WHERE level = 0 ORDER BY ".$pre."categories.order ASC");
foreach ($rows as $reccord) {
    array_push(
        $arrCategories,
        array(
            $reccord['id'],
            $reccord['title'],
            $reccord['order']
        )
    );
    // build selection list
    $categoriesSelect .= '<option value="'.$reccord['id'].'">'.($reccord['title']).'</option>';
}

echo '
<div id="tabs-8">
	<div id="categories_list">
    	<table id="tbl_categories" style="">';

if (isset($arrCategories) && count($arrCategories) > 0) {
    // build table
    foreach ($arrCategories as $category) {
        // get associated Folders
        $foldersList = $foldersNumList = "";
        $rows = $db->fetchAllArray(
            "SELECT t.title AS title, c.id_folder as id_folder
            FROM ".$pre."categories_folders AS c
            INNER JOIN ".$pre."nested_tree AS t ON (c.id_folder = t.id)
            WHERE c.id_category = ".$category[0]
        );
        foreach ($rows as $reccord) {
            if (empty($foldersList)) {
                $foldersList = $reccord['title'];
                $foldersNumList = $reccord['id_folder'];
            } else {
                $foldersList .= " | ".$reccord['title'];
                $foldersNumList .= ";".$reccord['id_folder'];
            }
        }
        // display each cat and fields
        echo '
        <tr id="t_cat_'.$category[0].'">
            <td colspan="2">
                <input type="text" id="catOrd_'.$category[0].'" size="1" class="category_order" value="'.$category[2].'" />&nbsp;
                <input type="radio" name="sel_item" id="item_'.$category[0].'_cat" />
                <label for="item_'.$category[0].'_cat" id="item_'.$category[0].'" style="font-weight:bold;">'.$category[1].'</label>
                <a href="#" title="'.$LANG['field_add_in_category'].'" onclick="fieldAdd('.$category[0].')" class="cpm_button tip" style="margin-left:20px;">
                    <img src="includes/images/zone--plus.png"  />
                </a>
            </td>
            <td>
                <a href="#" title="'.$LANG['category_in_folders'].'" onclick="catInFolders('.$category[0].')" class="cpm_button tip" style="margin-left:5px;">
                    <img src="includes/images/folder_edit.png"  />
                </a>
                '.$LANG['category_in_folders_title'].':
                <span style="font-family:italic; margin-left:10px;" id="catFolders_'.$category[0].'">'.$foldersList.'</span>
                <input type="hidden" id="catFoldersList_'.$category[0].'" value="'.$foldersNumList.'" />
            </td>
        </tr>';
        $rows = $db->fetchAllArray("SELECT * FROM ".$pre."categories WHERE parent_id = ".$category[0]." ORDER BY ".$pre."categories.order ASC");
        if (count($rows) > 0) {
            foreach ($rows as $field) {
                echo '
        <tr id="t_field_'.$field['id'].'">
            <td width="20px"></td>
            <td>
                <input type="text" id="catOrd_'.$field['id'].'" size="1" class="category_order" value="'.$field['order'].'" />&nbsp;
                <input type="radio" name="sel_item" id="item_'.$field['id'].'_cat" />
                <label for="item_'.$field['id'].'_cat" id="item_'.$field['id'].'">'.($field['title']).'</label>
            </td>
            <td></td>
        </tr>';
            }
        }
    }

}
echo '
    	</table>
    </div>';

if (!isset($arrCategories) || count($arrCategories) == 0) {
    echo '
    <div class=ui-state-highlight ui-corner-all" style="padding:2px;" id="no_category">
        '.$LANG['no_category_defined'].'
    </div>';
}

// add management button
echo '
    <div class="ui-state-highlight ui-corner-all" style="padding: 5px; margin-top:25px;">
        <div>
            '.$LANG['new_category_label'].':
            <input type="text" id="new_category_label" class="ui-content" style="margin-left:5px; width: 200px;" />
            <input type="button" value="Add Category" onclick="categoryAdd()" style="margin-left:5px;" />
        </div>
        <div style="margin-top:5px;">
            '.$LANG['for_selected_items'].':<br />
            <input type="text" id="new_item_title" class="ui-content" style="margin-left:30px; width: 200px;" />
            <input type="button" value="'.$LANG['rename'].'" onclick="renameItem()" style="margin-left:5px;" />
            &nbsp;|&nbsp;
            <input type="button" value="'.$LANG['delete'].'" onclick="deleteItem()" style="margin-left:5px;" />
            &nbsp;|&nbsp;
            <input type="button" value="'.$LANG['move'].'" onclick="moveItem()" style="margin-left:5px;" />
            <select id="moveItemTo" style="margin-left:10px;">'.$categoriesSelect.'</select>
        </div>
        <div style="margin-top:5px;">
            <input type="button" value="'.$LANG['save_categories_position'].'" onclick="storePosition()" style="margin-left:5px;" />
            <input type="button" value="'.$LANG['reload_table'].'" onclick="loadFieldsList()" style="margin-left:5px;" />
        </div>
    </div>';

echo '
</div>';

// hidden
echo '
    <input type="hidden" id="post_id" />
    <input type="hidden" id="post_data" />
    <input type="hidden" id="post_type" />
    <input type="hidden" id="selected_row" />';

// dialogboxes
echo '
    <div id="category_confirm" style="display:none;">
        <span id="category_confirm_text"></span>?
    </div>';

echo '
    <div id="add_new_field" style="display:none;">
        '.$LANG['new_field_title'].'<input type="text" id="new_field_title" style="width: 200px; margin-left:20px;" />
    </div>';

echo '
    <div id="category_in_folder" style="display:none;">
        '.$LANG['select_folders_for_category'].'
        &nbsp;&quot;<span style="font-weight:bold;" id="catInFolder_title"></span>&quot;&nbsp;:
        <br />
        <div style="text-align:center; margin-top:10px;">
        <select id="cat_folders_selection" multiple size="12">';
        $folders = $tree->getDescendants();
        foreach ($folders as $folder) {
            //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree WHERE personal_folder=0 AND id = ".$folder->id);
            $data = $db->queryCount(
                "nested_tree",
                array(
                    "personal_folder" => 0,
                    "id" => $folder->id
                )
            );
            if ($data[0] > 0) {
                echo '
                <option value="'.$folder->id.'">'.str_replace("&", "&amp;", $folder->title).'</option>';
            }
        }
echo '
        </div>
        <div id="catInFolder_wait" class="ui-state-focus ui-corner-all" style="display:none;padding:2px;margin:5px 0 5px 0;">'.$LANG['please_wait'].'...</div>
    </div>';

require_once 'admin.settings.load.php';