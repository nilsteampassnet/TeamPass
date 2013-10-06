<?php
/**
 *
 * @file          admin.settings_categories.php
 * @author        Nils Laumaill�
 * @version       2.1.18
 * @copyright     (c) 2009-2013 Nils Laumaill�
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
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
        echo '
        <tr id="t_cat_'.$category[0].'">
            <td colspan="2">
                <input type="text" id="catOrd_'.$category[0].'" size="1" class="category_order" value="'.$category[2].'" />&nbsp;
                <input type="radio" name="sel_item" id="item_'.$category[0].'_cat" />
                <label for="item_'.$category[0].'_cat" id="item_'.$category[0].'">'.$category[1].'</label>
                <a href="#" title="'.$txt['field_add_in_category'].'" onclick="fieldAdd('.$category[0].')" class="cpm_button tip" style="margin-left:20px;">
                    <img  src="includes/images/zone--plus.png"  />
                </a>
            </td>
            <td></td>
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
        '.$txt['no_category_defined'].'
    </div>';
}

// add management button
echo '
    <div class="ui-state-highlight ui-corner-all" style="padding: 5px; margin-top:25px;">
        <div>
            '.$txt['new_category_label'].':
            <input type="text" id="new_category_label" class="ui-content" style="margin-left:5px; width: 200px;" />
            <input type="button" value="Add Category" onclick="categoryAdd()" style="margin-left:5px;" />
        </div>
        <div style="margin-top:5px;">
            '.$txt['for_selected_items'].':<br />
            <input type="text" id="new_item_title" class="ui-content" style="margin-left:30px; width: 200px;" />
            <input type="button" value="'.$txt['rename'].'" onclick="renameItem()" style="margin-left:5px;" />
            &nbsp;|&nbsp;
            <input type="button" value="'.$txt['delete'].'" onclick="deleteItem()" style="margin-left:5px;" />
            &nbsp;|&nbsp;
            <input type="button" value="'.$txt['move'].'" onclick="moveItem()" style="margin-left:5px;" />
            <select id="moveItemTo" style="margin-left:10px;">'.$categoriesSelect.'</select>
        </div>
        <div style="margin-top:5px;">
            <input type="button" value="'.$txt['save_categories_position'].'" onclick="storePosition()" style="margin-left:5px;" />
            <input type="button" value="'.$txt['reload_table'].'" onclick="loadFieldsList()" style="margin-left:5px;" />
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
        '.$txt['new_field_title'].'<input type="text" id="new_field_title" style="width: 200px; margin-left:20px;" />
    </div>';

require_once 'admin.settings.load.php';