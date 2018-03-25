<?php
/**
 *
 * @file          admin.settings_categories.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sources/SecureHandler.php');
session_start();
header("Content-type: text/html; charset=utf-8");

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser(@$_SESSION['user_id'], @$_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

include $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

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
$rows = DB::query(
    "SELECT * FROM ".prefix_table("categories")."
    WHERE level = %i
    ORDER BY ".$pre."categories.order ASC",
    '0'
);
foreach ($rows as $record) {
    array_push(
        $arrCategories,
        array(
            $record['id'],
            $record['title'],
            $record['order']
        )
    );
    // build selection list
    $categoriesSelect .= '<option value="'.$record['id'].'">'.($record['title']).'</option>';
}

// Build list of Field Types
$options_field_types = '<option value="text">'.$LANG['text'].'</option>'.
                '<option value="masked">'.$LANG['masked'].'</option>';

echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html><head><title>API Settings</title></head><body>
<div id="tabs-8">
    <!-- Enable CUSTOM FOLDERS (toggle) -->
    <div style="width:100%; height:30px;">
    <div style="float:left;">
        '.$LANG['settings_item_extra_fields'].'
        <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_item_extra_fields_tip']), ENT_QUOTES).'"></i></span>
    </div>
    <div style="float:left; margin-left:20px;" class="toggle toggle-modern" id="item_extra_fields" data-toggle-on="', isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] == 1 ? 'true' : 'false', '"></div>
    <div style="float:left;"><input type="hidden" id="item_extra_fields_input" name="item_extra_fields_input" value="', isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] == 1 ? '1' : '0', '" /></div>
    </div>
    <hr />


    <div id="categories_list">
        <table id="tbl_categories" style=""><tr style="display: none ! important;"><td>HTML validation placeholder</td></tr>';

if (isset($arrCategories) && count($arrCategories) > 0) {
    // build table
    foreach ($arrCategories as $category) {
        // get associated Folders
        $foldersList = $foldersNumList = "";
        $rows = DB::query(
            "SELECT t.title AS title, c.id_folder as id_folder
            FROM ".prefix_table("categories_folders")." AS c
            INNER JOIN ".prefix_table("nested_tree")." AS t ON (c.id_folder = t.id)
            WHERE c.id_category = %i",
            $category[0]
        );
        foreach ($rows as $record) {
            if (empty($foldersList)) {
                $foldersList = $record['title'];
                $foldersNumList = $record['id_folder'];
            } else {
                $foldersList .= " | ".$record['title'];
                $foldersNumList .= ";".$record['id_folder'];
            }
        }
        // display each cat and fields
        echo '
        <tr id="t_cat_'.$category[0].'">
            <td colspan="2">
                <input type="text" id="catOrd_'.$category[0].'" size="1" class="category_order" value="'.$category[2].'" />&nbsp;
                <span class="fa-stack tip" title="'.$LANG['field_add_in_category'].'" onclick="fieldAdd('.$category[0].')" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-plus fa-stack-1x fa-inverse"></i>
                </span>
                &nbsp;
                <input type="radio" name="sel_item" id="item_'.$category[0].'_cat" />
                <label for="item_'.$category[0].'_cat" id="item_'.$category[0].'" style="font-weight:bold;">'.$category[1].'</label>
            </td>
            <td>
                <span class="fa-stack tip" title="'.$LANG['category_in_folders'].'" onclick="catInFolders('.$category[0].')" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-edit fa-stack-1x fa-inverse"></i>
                </span>
                &nbsp;
                '.$LANG['category_in_folders_title'].':
                <span style="font-family:italic; margin-left:10px;" id="catFolders_'.$category[0].'">'.$foldersList.'</span>
                <input type="hidden" id="catFoldersList_'.$category[0].'" value="'.$foldersNumList.'" />
            </td>
        </tr>';
        $rows = DB::query(
            "SELECT * FROM ".prefix_table("categories")."
            WHERE parent_id = %i
            ORDER BY ".$pre."categories.order ASC",
            $category[0]
        );
        $counter = DB::count();
        if ($counter > 0) {
            foreach ($rows as $field) {
                echo '
        <tr id="t_field_'.$field['id'].'">
            <td width="60px"></td>
            <td colspan="2">
                <input type="text" id="catOrd_'.$field['id'].'" size="1" class="category_order" value="'.$field['order'].'" />&nbsp;
                <input type="radio" name="sel_item" id="item_'.$field['id'].'_cat" />
                <label for="item_'.$field['id'].'_cat" id="item_'.$field['id'].'">'.($field['title']).'</label>
                <span id="encryt_data_'.$field['id'].'" style="margin-left:4px; cursor:pointer;">', (isset($field['encrypted_data']) && $field['encrypted_data'] === "1") ? '<i class="fa fa-key tip" title="'.$LANG['encrypted_data'].'" onclick="changeEncrypMode(\''.$field['id'].'\', \'1\')"></i>' : '<span class="fa-stack" title="'.$LANG['not_encrypted_data'].'" onclick="changeEncrypMode(\''.$field['id'].'\', \'0\')"><i class="fa fa-key fa-stack-1x"></i><i class="fa fa-ban fa-stack-1x fa-lg" style="color:red;"></i></span>', '
                </span>';
                if (isset($field['type'])) {
                    if ($field['type'] === "text") {
                        echo '
                <span style="margin-left:4px;"><i class="fa fa-paragraph tip" title="'.$LANG['data_is_text'].'"></i></span>';
                    } elseif ($field['type'] === "masked") {
                        echo '
                <span style="margin-left:4px;"><i class="fa fa-eye-slash tip" title="'.$LANG['data_is_masked'].'"></i></span>';
                    }
                }
                echo '
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
    <div class="ui-state-highlight ui-corner-all" style="padding:2px;" id="no_category">
        '.$LANG['no_category_defined'].'
    </div>';
}

// add management button
echo '
    <div class="ui-state-highlight ui-corner-all" style="padding: 5px; margin-top:25px;">
        <div>
            '.$LANG['new_category_label'].':
            <input type="text" id="new_category_label" class="ui-content" style="margin-left:5px; width: 200px;" />
            <input type="button" value="'.$LANG['add_category'].'" onclick="categoryAdd()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
        </div>
        <div style="margin-top:5px;">
            '.$LANG['for_selected_items'].':<br />
            <input type="text" id="new_item_title" class="ui-content" style="margin-left:30px; width: 200px;" />
            <input type="button" value="'.$LANG['rename'].'" onclick="renameItem()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
            &nbsp;|&nbsp;
            <input type="button" value="'.$LANG['delete'].'" onclick="deleteItem()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
            &nbsp;|&nbsp;
            <input type="button" value="'.$LANG['move'].'" onclick="moveItem()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
            <select id="moveItemTo" style="margin-left:10px;">
                <option style="display: none ! important;" value="HTML validation placeholder"></option>
                '.$categoriesSelect.'
            </select>
            &nbsp;|&nbsp;
            <input type="button" value="'.$LANG['type'].'" onclick="changeFieldTypeNow()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
            <select id="changeFieldType" style="margin-left:10px;">
                <option style="display: none ! important;" value="HTML validation placeholder"></option>
                '.$options_field_types.'
            </select>
        </div>
        <div style="margin-top:5px;">
            <input type="button" value="'.$LANG['save_categories_position'].'" onclick="storePosition()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
            <input type="button" value="'.$LANG['reload_table'].'" onclick="loadFieldsList()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
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
        <div style="width:100%;">
            <label for="new_field_title" style="display:inline-block;width:220px;">'.$LANG['new_field_title'].'</label>
            <input type="text" id="new_field_title" style="width: 200px; margin-left:20px; padding:3px;" />
        </div>
        <div style="margin-top:3px;">
            <label for="new_field_type" style="display:inline-block;width:220px;">'.$LANG['select_type_of_field'].'</label>
            <select id="new_field_type" style="width: 200px; margin-left:20px; padding:3px;">'.
                $options_field_types.'
            </select>
        </div>
    </div>';

echo '
    <div id="category_in_folder" style="display:none;">
        '.$LANG['select_folders_for_category'].'
        &nbsp;&quot;<span style="font-weight:bold;" id="catInFolder_title"></span>&quot;&nbsp;:
        <br />
        <div style="margin-top:10px;">
            <input type="button" id="but_select_all" value="'.$LANG['select_all'].'" class="ui-state-default ui-corner-all pointer" />
            &nbsp;<input type="button" id="but_deselect_all" value="'.$LANG['deselect_all'].'" class="ui-state-default ui-corner-all pointer" />
        </div>
        <div style="margin-top:5px;">
            <select id="cat_folders_selection" multiple="multiple" size="15" class="ui-widget-content ui-corner-all" style="margin-top:5px;">';
    $folders = $tree->getDescendants();
    foreach ($folders as $folder) {
        DB::query(
            "SELECT * FROM ".prefix_table("nested_tree")."
            WHERE personal_folder = %i AND id = %i",
            '0',
            $folder->id
        );
        $counter = DB::count();
        if ($counter > 0) {
            $ident = "";
            for ($x = 1; $x < $folder->nlevel; $x++) {
                $ident .= "-";
            }
            echo '
            <option value="'.$folder->id.'">'.$ident.'&nbsp;'.str_replace("&", "&amp;", $folder->title).'</option>';
        }
    }
    echo '
            </select>
        </div>

        <div id="catInFolder_wait" class="ui-state-focus ui-corner-all" style="display:none;padding:2px;margin:5px 0 5px 0;">'.$LANG['please_wait'].'...</div>
    </div>';

require_once 'admin.settings.load.php';
echo '
</body></html>';
