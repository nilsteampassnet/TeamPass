<?php
/**
 * @file          suggestion.queries.php
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

require_once 'SecureHandler.php';
session_start();
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
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "suggestion")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
require_once 'main.functions.php';

// pw complexity levels
$_SESSION['settings']['pwComplexity'] = array(
    0 => array(0, $LANG['complex_level0']),
    25 => array(25, $LANG['complex_level1']),
    50 => array(50, $LANG['complex_level2']),
    60 => array(60, $LANG['complex_level3']),
    70 => array(70, $LANG['complex_level4']),
    80 => array(80, $LANG['complex_level5']),
    90 => array(90, $LANG['complex_level6'])
);

// connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

// load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// treatment by action
if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        case "add_new":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // decrypt and retrieve data in JSON format
            $data_received = prepareExchangedData($_POST['data'], "decode");

            // prepare variables
            $label = htmlspecialchars_decode($data_received['label']);
            $pwd = htmlspecialchars_decode($data_received['password']);
            $description = htmlspecialchars_decode($data_received['description']);
            $folder = htmlspecialchars_decode($data_received['folder']);
            $comment = htmlspecialchars_decode($data_received['comment']);

            // check if similar suggestion exists in same folder
            DB::query("SELECT * FROM ".prefix_table("suggestion")." WHERE label = %s AND folder_id = %i", $label, $folder);
            $counter = DB::count();
            if ($counter == 0) {
                // encrypt
                $encrypt = cryption($pwd, "", "encrypt");

                // query
                DB::insert(
                    prefix_table("suggestion"),
                    array(
                        'label' => $label,
                        'description' => ($description),
                        'author_id' => $_SESSION['user_id'],
                        'pw' => $encrypt['string'],
                        'pw_iv' => "",
                        'pw_len' => 0,
                        'comment' => $comment,
                        'folder_id' => $folder
                    )
                );

                // get some info to add to the notification email
                $resp_user = DB::queryfirstrow(
                    "SELECT login FROM ".prefix_table("users")." WHERE id = %i",
                    $_SESSION['user_id']
                );
                $resp_folder = DB::queryfirstrow(
                    "SELECT title FROM ".prefix_table("nested_tree")." WHERE id = %i",
                    $folder
                );

                // notify Managers
                $rows = DB::query(
                    "SELECT email
                    FROM ".prefix_table("users")."
                    WHERE `gestionnaire` = %i AND `email` IS NOT NULL",
                    1
                );
                foreach ($rows as $record) {
                    sendEmail(
                        $LANG['suggestion_notify_subject'],
                        str_replace(array('#tp_label#', '#tp_user#', '#tp_folder#'), array(addslashes($label), addslashes($resp_user['login']), addslashes($resp_folder['title'])), $LANG['suggestion_notify_body']),
                        $record['email']
                    );
                }

                echo '[ { "status" : "done" } ]';
            } else {
                echo '[ { "status" : "duplicate_suggestion" } ]';
            }
        break;

        case "delete_suggestion":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            DB::delete(prefix_table("suggestion"), "id = %i", $_POST['id']);
        break;

        case "duplicate_suggestion":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get suggestion details
            $suggestion = DB::queryfirstrow(
                "SELECT label, folder_id FROM ".prefix_table("suggestion")." WHERE id = %i",
                $_POST['id']
            );

            // check if similar exists
            DB::query(
                "SELECT id FROM ".prefix_table("items")." WHERE label = %s AND id_tree = %i",
                $suggestion['label'],
                $suggestion['folder_id']
            );
            $counter = DB::count();
            if ($counter > 0) {
                echo '[ { "status" : "duplicate" } ]';
            } else {
                echo '[ { "status" : "no" } ]';
            }
            break;

        case "validate_suggestion":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get suggestion details
            $suggestion = DB::queryfirstrow(
                "SELECT label, description, pw, folder_id, author_id, comment, pw_iv
                FROM ".prefix_table("suggestion")."
                WHERE id = %i",
                $_POST['id']
            );

            // check if similar Item exists (based upon Label and folder id)
            // if Yes, update the existing one
            // if Not, create new Item
            $existing_item_id = DB::queryfirstrow(
                "SELECT id
                FROM ".prefix_table("items")."
                WHERE label = %s AND id_tree = %i",
                $suggestion['label'],
                $suggestion['folder_id']
            );
            $counter = DB::count();
            if ($counter > 0) {
                // update existing item
                $updStatus = DB::update(
                    prefix_table("items"),
                    array(
                        'description' => !empty($suggestion['description']) ? $existing_item_id['id']."<br />----<br />".$suggestion['description'] : $existing_item_id['id'],
                        'pw' => $suggestion['pw'],
                        'pw_iv' => ""
                    ),
                    "id=%i",
                    $existing_item_id['id']
                );
                if ($updStatus) {
                    // update LOG
                    DB::insert(
                        prefix_table("log_items"),
                        array(
                            'id_item' => $existing_item_id['id'],
                            'date' => time(),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_suggestion'
                        )
                    );


                    // update cache table
                    updateCacheTable("update_value", $existing_item_id['id']);

                    // delete suggestion
                    DB::delete(prefix_table("suggestion"), "id = %i", $_POST['id']);

                    echo '[ { "status" : "done" } ]';
                } else {
                    echo '[ { "status" : "error_when_updating" } ]';
                }
            } else {
                // add as Item
                DB::insert(
                    prefix_table("items"),
                    array(
                        'label' => $suggestion['label'],
                        'description' => $suggestion['description'],
                        'pw' => $suggestion['pw'],
                        'id_tree' => $suggestion['folder_id'],
                        'inactif' => '0',
                        'perso' => '0',
                        'anyone_can_modify' => '0',
                        'pw_iv' => ""
                    )
                );
                $newID = DB::insertId();

                if (is_numeric($newID)) {
                    // update log
                    DB::insert(
                        prefix_table("log_items"),
                        array(
                            'id_item' => $newID,
                            'date' => time(),
                            'id_user' => $suggestion['author_id'],
                            'action' => 'at_creation'
                        )
                    );

                    // update cache table
                    updateCacheTable("add_value", $newID);

                    // delete suggestion
                    DB::delete(prefix_table("suggestion"), "id = %i", $_POST['id']);

                    echo '[ { "status" : "done" } ]';
                } else {
                    echo '[ { "status" : "error_when_creating" } ]';
                }
            }
            break;

        case "get_complexity_level":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $data = DB::queryfirstrow(
                "SELECT valeur FROM ".$pre."misc WHERE intitule = %s AND type = %s", $_POST['folder_id'], "complex"
            );
            if (isset($data['valeur']) && (!empty($data['valeur']) || $data['valeur'] == 0)) {
                $complexity = $_SESSION['settings']['pwComplexity'][$data['valeur']][1];
            } else {
                $complexity = $LANG['not_defined'];
            }

            echo '[ { "status" : "ok" , "complexity" : "'.$data['valeur'].'" , "complexity_text" : "'.$complexity.'" } ]';
        break;

        case "get_item_change_detail":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $data = DB::queryfirstrow(
                "SELECT * FROM ".$pre."items_change WHERE id = %i",
                $_POST['id']
            );
            $tmp = cryption($data['pw'], "", "decrypt");
            $data['pw'] = $tmp['string'];


            $data_current = DB::queryfirstrow(
                "SELECT * FROM ".$pre."items WHERE id = %i",
                $data['item_id']
            );
            $tmp = cryption($data_current['pw'], "", "decrypt");
            $data_current['pw'] = $tmp['string'];

            $html = '
            <table width="100%">
            <thead><th>Field</th><th>Current</th><th>Proposal</th><th>Confirm</th></thead>
            <tbody>
            <tr>
            <td>'.$LANG['label'].'</td>
            <td>'.$data_current['label'].'</td>
            ';
            if (!empty($data['label']) && $data['label'] !== $data_current['label']) {
                $html .= '<td style="text-align:center;">
                <input type="text" id="label_change" value="'.$data['label'].'" class="ui-widget-content ui-corner-all" style="width:95%; padding:1px;">
                </td>
                <td style="text-align:center;" id="confirm_label"><span class="fa fa-check mi-green fa-lg confirm_change tip" id="confirm_label-check" style="cursor:pointer;" title="'.addslashes($LANG['Dont_update_with_this_data']).'"></span></td>';
            } else {
                $html .= '<td></td><td></td>';
            }
            $html .= '
            </tr>
            <tr border-bottom="1px #009888 solid" margin-bottom="3px;">
            <td>'.$LANG['pw'].'</td>
            <td>'.$data_current['pw'].'</td>';
            if (!empty($data['pw']) && $data['pw'] !== $data_current['pw']) {
                $html .= '<td style="text-align:center;">
                <input type="text" id="pw_change" value="'.$data['pw'].'" class="ui-widget-content ui-corner-all" style="width:95%; padding:1px;">
                </td>
                <td style="text-align:center;" id="confirm_pw"><span class="fa fa-check mi-green fa-lg confirm_change tip" id="confirm_pw-check" style="cursor:pointer;" title="'.addslashes($LANG['Dont_update_with_this_data']).'"></span></td>';
            } else {
                $html .= '<td></td><td></td>';
            }
            $html .= '
            </tr>
            <tr>
            <td>'.$LANG['index_login'].'</td>
            <td>'.$data_current['login'].'</td>';
            if (!empty($data['login']) && $data['login'] !== $data_current['login']) {
                $html .= '<td style="text-align:center;">
                <input type="text" id="login_change" value="'.$data['login'].'" class="ui-widget-content ui-corner-all" style="width:95%; padding:1px;">
                </td>
                <td style="text-align:center;" id="confirm_login"><span class="fa fa-check mi-green fa-lg confirm_change tip" id="confirm_login-check" style="cursor:pointer;" title="'.addslashes($LANG['Dont_update_with_this_data']).'"></span></td>';
            } else {
                $html .= '<td></td><td></td>';
            }
            $html .= '
            </tr>
            <tr>
            <td>'.$LANG['email'].'</td>
            <td>'.$data_current['email'].'</td>';
            if (!empty($data['email']) && $data['email'] !== $data_current['email']) {
                $html .= '<td style="text-align:center;">
                <input type="text" id="email_change" value="'.$data['email'].'" class="ui-widget-content ui-corner-all" style="width:95%; padding:1px;">
                </td>
                <td style="text-align:center;" id="confirm_email"><span class="fa fa-check mi-green fa-lg confirm_change tip" id="confirm_email-check" style="cursor:pointer;" title="'.addslashes($LANG['Dont_update_with_this_data']).'"></span></td>';
            } else {
                $html .= '<td></td><td></td>';
            }
            $html .= '
            </tr>
            <tr>
            <td>'.$LANG['url'].'</td>
            <td>'.$data_current['url'].'</td>';
            if (!empty($data['url']) && $data['url'] !== $data_current['url']) {
                $html .= '
                <td style="text-align:center;"><input type="text" id="url_change" value="'.$data['url'].'" class="ui-widget-content ui-corner-all" style="width:95%; padding:1px;">
                </td>
                <td style="text-align:center;" id="confirm_url"><span class="fa fa-check mi-green fa-lg confirm_change tip" id="confirm_url-check" style="cursor:pointer;" title="'.addslashes($LANG['Dont_update_with_this_data']).'"></span></td>';
            } else {
                $html .= '<td></td><td></td>';
            }
            $html .= '
            </tr>
            </tbody>
            </table>

            <div style="margin-top:15px;">
            <label class="form_label_100" style="padding:4px;">'.$LANG['comment'].'</label><input type="text" id="comment_change" value="'.$data['comment'].'" class="input_text_80 ui-widget-content ui-corner-all">
            </div>

            <div id="suggestion_view_wait" class="ui-widget-content ui-state-focus ui-corner-all" style="margin-top:15px; padding:10px; text-align:center; font-size:16px; display:none;"></div>';

            echo prepareExchangedData(
                array(
                    "html" => $html,
                    "error" => ""
                ),
                "encode"
            );
        break;


        case "approve_item_change":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // read changes proposal
            $data = DB::queryfirstrow(
                "SELECT * FROM ".$pre."items_change WHERE id = %i",
                $_POST['id']
            );

            // read current item
            $current_item = DB::queryfirstrow(
                "SELECT * FROM ".$pre."items WHERE id = %i",
                $data['item_id']
            );

            // read item creator
            $ret_item_creator = DB::queryFirstRow(
                "SELECT id_user FROM ".$pre."log_items
                WHERE id_item = %i AND action = %s",
                intval($data['item_id']),
                "at_creation"
            );

            // get author login
            $author = DB::queryfirstrow(
                "SELECT login FROM ".$pre."users WHERE id = %i",
                $data['user_id']
            );

            // is user allowed?
            if (
                (isset($_SESSION['user_admin']) && $_SESSION['user_admin'] !== "1") &&
                (isset($_SESSION['user_manager']) && $_SESSION['user_manager'] !== "1") &&
                ($data['user_id'] !== $_SESSION['user_id']) &&
                ($ret_item_creator['id_user'] !== $_SESSION['user_id'])
            ) {
                // not allowed ... stop
                echo '[ { "error" : "not_allowed" } ]';
                break;
            }

            // prepare query
            $fields_array = array();
            $fields_to_update = explode(";", $_POST['data']);
            foreach ($fields_to_update as $field) {
                if (!empty($field)) {
                    $fields_array[$field] = $data[$field];
                }
            }

            // update item
            DB::update(
                prefix_table("items"),
                $fields_array,
                "id = %i",
                $data['item_id']
            );

            // Log all modifications done
            foreach ($fields_to_update as $field) {
                if (!empty($field)) {
                    if ($field !== "pw") {
                        logItems($data['item_id'], $current_item['label'], $data['user_id'], 'at_modification', $author['login'], 'at_'.$field.' : '.$current_item[$field].' => '.$data[$field]);
                    } else if ($field === "description") {
                        logItems($data['item_id'], $current_item['label'], $data['user_id'], 'at_modification', $author['login'], 'at_'.$field);
                    } else {
                        $oldPwClear = cryption(
                            $current_item['pw'],
                            "",
                            "decrypt"
                        );
                        logItems($data['item_id'], $current_item['label'], $data['user_id'], 'at_modification', $author['login'], 'at_'.$field.' : '.$oldPwClear);
                    }
                }
            }

            // Update CACHE table
            updateCacheTable("update_value", $data['item_id']);

            // delete change proposal
            DB::delete(
                $pre."items_change",
                "id = %i",
                $_POST['id']
            );

            echo '[ { "error" : "" } ]';
        break;


        case "reject_item_change":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // delete change proposal
            DB::delete(
                $pre."items_change",
                "id = %i",
                $_POST['id']
            );

        break;
    }
}
