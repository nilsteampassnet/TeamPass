<?php
/**
 *
 * @package       admin.settings_categories.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
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
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
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
$categoriesSelect = '<option value="">-- '.addslashes($LANG['select']).' --</option>';
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
$options_field_types = '<option value="">-- '.addslashes($LANG['select']).' --</option>'.
    '<option value="text">'.addslashes($LANG['text']).'</option>'.
    '<option value="textarea">'.addslashes($LANG['textarea']).'</option>';

// Build list of Roles
$options_roles = '<option value="all">'.addslashes($LANG['every_roles']).'</option>';
$rows = DB::query(
    "SELECT id, title FROM ".prefix_table("roles_title")."
    ORDER BY title ASC"
);
foreach ($rows as $record) {
    $options_roles .= '<option value="'.$record['id'].'">'.addslashes($record['title']).'</option>';
}


echo '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html><head><title>API Settings</title></head><body>
<div id="tabs-8">
    <!-- Enable CUSTOM FOLDERS (toggle) -->
    <div style="width:100%; height:30px;">
        <div style="float:left;">
            <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
            '.$LANG['settings_item_extra_fields'].'
            <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['settings_item_extra_fields_tip']), ENT_QUOTES).'"></i></span>
        </div>
        <div style="float:left; margin-left:20px;" class="toggle toggle-modern" id="item_extra_fields" data-toggle-on="', isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] == 1 ? 'true' : 'false', '"></div>
        <div style="float:left;"><input type="hidden" id="item_extra_fields_input" name="item_extra_fields_input" value="', isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] == 1 ? '1' : '0', '" /></div>
    </div>';
    
// Enable item_creation_templates
echo '
    <div style="width:100%; height:30px;">
        <div style="float:left;">
        <i class="fa fa-chevron-right mi-grey-1" style="margin-right: .3em;">&nbsp;</i>
            <label>
                '.$LANG['create_item_based_upon_template'].'
                <span style="margin-left:0px;">&nbsp;<i class="fa fa-question-circle tip" title="'.htmlentities(strip_tags($LANG['create_item_based_upon_template_tip']), ENT_QUOTES).'"></i></span>
            </label>
        </div>
        <div style="float:left; margin-left:20px;" class="toggle toggle-modern" id="item_creation_templates" data-toggle-on="', isset($SETTINGS['item_creation_templates']) && $SETTINGS['item_creation_templates'] == 1 ? 'true' : 'false', '"></div>
        <input type="hidden" id="item_creation_templates_input" name="item_creation_templates_input" value="', isset($SETTINGS['item_creation_templates']) && $SETTINGS['item_creation_templates'] == 1 ? '1' : '0', '" />
    </div>';

echo '
    <hr />

    <div class="ui-state-highlight ui-corner-all" style="padding: 5px; margin:5px 0 20px 0;">
        <div>
            '.$LANG['new_category_label'].':
            <input type="text" id="new_category_label" class="ui-content" style="margin-left:5px; width: 200px;" />
            <input type="button" value="'.$LANG['add_category'].'" onclick="categoryAdd()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
            <div style="margin-top:3px;">
                <input type="button" value="'.$LANG['save_categories_position'].'" onclick="storePosition()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
                <input type="button" value="'.$LANG['reload_table'].'" onclick="loadFieldsList()" style="margin-left:5px;" class="ui-state-default ui-corner-all pointer" />
            </div>
        </div>
    </div>
    
    <div id="categories_list">
    </div>
    
    <div class="ui-state-highlight ui-corner-all hidden" style="padding:2px;" id="no_category">
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
<div id="item_dialog" class="hidden">
    <div style="margin:0px 0 0px 30px;">
        <input type="hidden" id="field_is_category" value="" />
        <table>
            <tr>
                <td width="200px">
                    <label for="field_title">'.$LANG['at_label'].':</label>&nbsp;
                </td>
                <td>
                    <input type="text" id="field_title" class="ui-widget-content ui-corner-all field_edit" style="width: 330px; padding:3px;" />
                </td>
            </tr>
            <tr class="not_category">
                <td>
                    <label for="field_category">'.$LANG['category'].':</label>&nbsp;
                </td>
                <td>
                    <select id="field_category" class="ui-widget-content ui-corner-all field_edit" style="width: 340px; padding:3px;">
                        '.$categoriesSelect.'
                    </select>
                </td>
            </tr>
            <tr class="not_category">
                <td>
                    <label for="field_type">'.$LANG['type'].':</label>&nbsp;
                </td>
                <td>
                    <select id="field_type" class="ui-widget-content ui-corner-all field_edit" style="width: 340px; padding:3px;">
                        '.$options_field_types.'
                    </select>
                </td>
            </tr>
            <tr class="not_category">
                <td>
                    <label for="field_masked">'.$LANG['masked_text'].':</label>&nbsp;
                </td>
                <td>
                    <select id="field_masked" class="ui-widget-content ui-corner-all field_edit" style="width: 340px; padding:3px;">
                        <option value="0">'.$LANG['no'].'</option>
                        <option value="1">'.$LANG['yes'].'</option>
                    </select>
                </td>
            </tr>
            <tr class="not_category">
                <td>
                    <label for="field_is_mandatory">'.$LANG['is_mandatory'].':</label>&nbsp;
                </td>
                <td>
                    <select id="field_is_mandatory" class="ui-widget-content ui-corner-all" style="width: 340px; padding:3px;">
                        <option value="0">'.$LANG['no'].'</option>
                        <option value="1">'.$LANG['yes'].'</option>
                    </select>
                </td>
            </tr>
            <tr class="not_category">
                <td>
                    <label for="field_encrypted">'.$LANG['encrypted_data'].':</label>&nbsp;
                </td>
                <td>
                    <select id="field_encrypted" class="ui-widget-content ui-corner-all field_edit" style="width:340px; padding:3px;">
                        <option value="1">'.$LANG['yes'].'</option>
                        <option value="0">'.$LANG['no'].'</option>
                    </select>
                </td>
            </tr>
            <tr class="not_category">
                <td>
                    <label for="field_visibility">'.$LANG['restrict_visibility_to'].':</label>&nbsp;
                </td>
                <td>
                    <select id="field_visibility" class="ui-widget-content ui-corner-all field_edit" style="width:340px; padding:3px;" multiple="multiple">
                        '.$options_roles.'
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="field_order">'.$LANG['position_in_list'].':</label>&nbsp;
                </td>
                <td>
                    <input type="text" id="field_order" class="ui-widget-content ui-corner-all" style="width: 30px; padding:3px;" />
                </td>
            </tr>
        </table>
    </div>
</div>';

echo '
    <div id="category_confirm" style="display:none;">
        <span id="category_confirm_text"></span>?
    </div>';

echo '
    <div id="add_new_field" style="display:none;">
        <table>
            <tr>
                <td>
                    <label for="new_field_title">'.$LANG['new_field_title'].':</label>&nbsp;
                </td>
                <td>
                    <input type="text" id="new_field_title" class="ui-widget-content ui-corner-all" style="width: 330px; padding:3px;" />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="new_field_type">'.$LANG['type'].':</label>&nbsp;
                </td>
                <td>
                    <select id="new_field_type" class="ui-widget-content ui-corner-all" style="width: 340px; padding:3px;">
                        '.$options_field_types.'
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="new_field_encrypted">'.$LANG['encrypted_data'].':</label>&nbsp;
                </td>
                <td>
                    <select id="new_field_encrypted" class="ui-widget-content ui-corner-all" style="width:340px; padding:3px;">
                        <option value="1">'.$LANG['yes'].'</option>
                        <option value="0">'.$LANG['no'].'</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="new_field_masked">'.$LANG['masked_text'].':</label>&nbsp;
                </td>
                <td>
                    <select id="new_field_masked" class="ui-widget-content ui-corner-all" style="width: 340px; padding:3px;">
                        <option value="0">'.$LANG['no'].'</option>
                        <option value="1">'.$LANG['yes'].'</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="new_field_is_mandatory">'.$LANG['is_mandatory'].':</label>&nbsp;
                </td>
                <td>
                    <select id="new_field_is_mandatory" class="ui-widget-content ui-corner-all" style="width: 340px; padding:3px;">
                        <option value="0">'.$LANG['no'].'</option>
                        <option value="1">'.$LANG['yes'].'</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="new_field_visibility">'.$LANG['restrict_visibility_to'].':</label>&nbsp;
                </td>
                <td>
                    <select id="new_field_visibility" class="ui-widget-content ui-corner-all field_edit" style="width:340px; padding:3px;" multiple="multiple">
                        '.$options_roles.'
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="new_field_title">'.$LANG['position_in_list'].':</label>&nbsp;
                </td>
                <td>
                    <input type="text" id="new_field_order" class="ui-widget-content ui-corner-all" style="width: 30px; padding:3px;" />
                </td>
            </tr>
        </table>
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
<script type="text/javascript">
//<![CDATA[
    $(function() {
        loadFieldsList();
    });
//]]>
</script></body></html>';
