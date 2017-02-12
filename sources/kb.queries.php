<?php
/**
 * @file          kb.queries.php
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
        || !isset($_SESSION['settings']['enable_kb'])
        || $_SESSION['settings']['enable_kb'] != 1)
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "kb")) {
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

//Connect to DB
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

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

function utf8Urldecode($value)
{
    $value = preg_replace('/%([0-9a-f]{2})/ie', 'chr(hexdec($1))', (string) $value);

    return $value;
}

// Construction de la requéte en fonction du type de valeur
if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        case "kb_in_db":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            //decrypt and retreive data in JSON format
            $data_received = prepareExchangedData($_POST['data'], "decode");

            //Prepare variables
            $id = htmlspecialchars_decode($data_received['id']);
            $label = htmlspecialchars_decode($data_received['label']);
            $category = htmlspecialchars_decode($data_received['category']);
            $anyone_can_modify = htmlspecialchars_decode($data_received['anyone_can_modify']);
            $kb_associated_to = htmlspecialchars_decode($data_received['kb_associated_to']);
            $description = htmlspecialchars_decode($data_received['description']);

            //check if allowed to modify
            if (isset($id) && !empty($id)) {
                $ret = DB::queryfirstrow("SELECT anyone_can_modify, author_id FROM ".prefix_table("kb")." WHERE id = %i", $id);
                if ($ret['anyone_can_modify'] == 1 || $ret['author_id'] == $_SESSION['user_id']) {
                    $manage_kb = true;
                } else {
                    $manage_kb = false;
                }
            } else {
                $manage_kb = true;
            }
            if ($manage_kb == true) {
                //Add category if new
                DB::query("SELECT * FROM ".prefix_table("kb_categories")." WHERE category = %s", $category);
                $counter = DB::count();
                if ($counter == 0) {
                    DB::insert(
                        prefix_table("kb_categories"),
                        array(
                            'category' => $category
                       )
                    );
                    $cat_id = DB::insertId();
                } else {
                    //get the ID of this existing category
                    $cat_id = DB::queryfirstrow("SELECT id FROM ".prefix_table("kb_categories")." WHERE category = %s", $category);
                    $cat_id = $cat_id['id'];
                }

                if (isset($id) && !empty($id)) {
                    //update KB
                    DB::update(
                        prefix_table("kb"),
                        array(
                            'label' => ($label),
                            'description' => ($description),
                            'author_id' => $_SESSION['user_id'],
                            'category_id' => $cat_id,
                            'anyone_can_modify' => $anyone_can_modify
                       ),
                        "id=%i", $id
                    );
                } else {
                    //add new KB
                    DB::insert(
                        prefix_table("kb"),
                        array(
                            'label' => $label,
                            'description' => ($description),
                            'author_id' => $_SESSION['user_id'],
                            'category_id' => $cat_id,
                            'anyone_can_modify' => $anyone_can_modify
                       )
                    );
                    $id = DB::insertId();
                }

                //delete all associated items to this KB
                DB::delete(prefix_table("kb_items"), "kb_id = %i", $id);

                //add all items associated to this KB
                foreach (explode(',', $kb_associated_to) as $item_id) {
                    DB::insert(
                        prefix_table("kb_items"),
                        array(
                            'kb_id' => $id,
                            'item_id' => $item_id
                       )
                    );
                }

                echo '[ { "status" : "done" } ]';
            } else {
                echo '[ { "status" : "none" } ]';
            }

            break;
        /**
         * Open KB
         */
        case "open_kb":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            $ret = DB::queryfirstrow(
                "SELECT k.id AS id, k.label AS label, k.description AS description, k.category_id AScategory_id, k.author_id AS author_id, k.anyone_can_modify AS anyone_can_modify, u.login AS login, c.category AS category
                FROM ".prefix_table("kb")." AS k
                INNER JOIN ".prefix_table("kb_categories")." AS c ON (c.id = k.category_id)
                INNER JOIN ".prefix_table("users")." AS u ON (u.id = k.author_id)
                WHERE k.id = %i",
                $_POST['id']
            );

            //select associated items
            $rows = DB::query("SELECT item_id FROM ".prefix_table("kb")."_items WHERE kb_id = %i", $_POST['id']);
            $arrOptions = array();
            foreach ($rows as $record) {
                array_push($arrOptions, $record['item_id']);
            }

            $arrOutput = array(
                "label"     => $ret['label'],
                "category"     => $ret['category'],
                "description"     => $ret['description'],
                "anyone_can_modify"     => $ret['anyone_can_modify'],
                "options"     => $arrOptions
            );

            echo json_encode($arrOutput, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            break;
        /**
         * Delete the KB
         */
        case "delete_kb":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            DB::delete(prefix_table("kb"), "id=%i", $_POST['id']);
            break;
    }
}
