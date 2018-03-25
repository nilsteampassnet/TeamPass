<?php
/**
 * @file          kb.queries.php
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

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key'])
) {
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

// Is KB enabled
if (isset($SETTINGS['enable_kb']) === false || $SETTINGS['enable_kb'] != 1) {
    die('Hacking attempt...');
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "kb")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
require_once 'main.functions.php';

//Connect to DB
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

function utf8Urldecode($value)
{
    $value = preg_replace('/%([0-9a-f]{2})/ie', 'chr(hexdec($1))', filter_var($value, FILTER_SANITIZE_STRING));

    return $value;
}

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);

// Construction de la requéte en fonction du type de valeur
if (null !== $post_type) {
    switch ($post_type) {
        case "kb_in_db":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            //decrypt and retreive data in JSON format
            $data_received = prepareExchangedData($post_data, "decode");

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
            if ($manage_kb === true) {
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
                        "id=%i",
                        $id
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
                    if (empty($item_id) === false) {
                        DB::insert(
                            prefix_table("kb_items"),
                            array(
                                'kb_id' => $id,
                                'item_id' => $item_id
                            )
                        );
                    }
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
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            $ret = DB::queryfirstrow(
                "SELECT k.id AS id, k.label AS label, k.description AS description, k.category_id AScategory_id, k.author_id AS author_id, k.anyone_can_modify AS anyone_can_modify, u.login AS login, c.category AS category
                FROM ".prefix_table("kb")." AS k
                INNER JOIN ".prefix_table("kb_categories")." AS c ON (c.id = k.category_id)
                INNER JOIN ".prefix_table("users")." AS u ON (u.id = k.author_id)
                WHERE k.id = %i",
                $post_id
            );

            //select associated items
            $rows = DB::query("SELECT item_id FROM ".prefix_table("kb")."_items WHERE kb_id = %i", $post_id);
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

            echo json_encode($arrOutput, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            break;
        /**
         * Delete the KB
         */
        case "delete_kb":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            DB::delete(prefix_table("kb"), "id=%i", $post_id);

            break;
    }
}
