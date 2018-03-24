<?php
/**
 * @file          categories.queries.php
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

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

include $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");
require_once 'main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to mysql server
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

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Prepare POST variables
$post_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
$post_field_title = filter_input(INPUT_POST, 'field_title', FILTER_SANITIZE_STRING);
$post_field_type = filter_input(INPUT_POST, 'field_type', FILTER_SANITIZE_STRING);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

if (null !== $post_type) {
    switch ($post_type) {
        case "addNewCategory":
            // store key
            DB::insert(
                prefix_table("categories"),
                array(
                    'parent_id' => 0,
                    'title' => $post_title,
                    'level' => 0,
                    'order' => 1
                )
            );
            echo '[{"error" : "", "id" : "'.DB::insertId().'"}]';
            break;

        case "deleteCategory":
            DB::delete(prefix_table("categories"), "id = %i", $post_id);
            DB::delete(prefix_table("categories_folders"), "id_category = %i", $post_id);
            echo '[{"error" : ""}]';
            break;

        case "addNewField":
            // store key
            if (empty($post_field_title) === false
                && empty($post_id) === false
            ) {
                DB::insert(
                    prefix_table("categories"),
                    array(
                        'parent_id' => $post_id,
                        'title' => $post_field_title,
                        'type' => $post_field_type,
                        'level' => 1,
                        'order' => 1
                    )
                );
                echo '[{"error" : "", "id" : "'.DB::insertId().'"}]';
            }
            break;

        case "renameItem":
            // update key
            if (empty($post_data) === false
                && empty($post_id) === false
            ) {
                DB::update(
                    prefix_table("categories"),
                    array(
                        'title' => $post_data
                        ),
                    "id=%i",
                    $post_id
                );
                echo '[{"error" : "", "id" : "'.$post_id.'"}]';
            }
            break;

        case "moveItem":
            // update key
            if (empty($post_data) === false
                && empty($post_id) === false
            ) {
                DB::update(
                    prefix_table("categories"),
                    array(
                        'parent_id' => $post_data,
                        'order' => 99
                        ),
                    "id=%i",
                    $post_id
                );
                echo '[{"error" : "", "id" : "'.$post_id.'"}]';
            }
            break;

        case "changeFieldType":
            // update key
            if (empty($post_data) === false
                && empty($post_id) === false
            ) {
                DB::update(
                    prefix_table("categories"),
                    array(
                        'type' => $post_data
                        ),
                    "id=%i",
                    $post_id
                );
                echo '[{"error" : "", "id" : "'.$post_id.'"}]';
            }
            break;

        case "saveOrder":
            // update order
            if (empty($post_type) === false) {
                foreach (explode(';', $post_data) as $data) {
                    $elem = explode(':', $data);
                    DB::update(
                        prefix_table("categories"),
                        array(
                            'order' => $elem[1]
                            ),
                        "id=%i",
                        $elem[0]
                    );
                }
                echo '[{"error" : ""}]';
            }
            break;

        case "loadFieldsList":
            $categoriesSelect = "";
            $arrCategories = $arrFields = array();
            $rows = DB::query("SELECT * FROM ".$pre."categories WHERE level = %i ORDER BY ".$pre."categories.order ASC", 0);
            foreach ($rows as $record) {
                // get associated folders
                $foldersList = $foldersNumList = "";
                $rowsF = DB::query(
                    "SELECT t.title AS title, c.id_folder as id_folder
                    FROM ".$pre."categories_folders AS c
                    INNER JOIN ".prefix_table("nested_tree")." AS t ON (c.id_folder = t.id)
                    WHERE c.id_category = %i",
                    $record['id']
                );
                foreach ($rowsF as $recordF) {
                    if (empty($foldersList)) {
                        $foldersList = $recordF['title'];
                        $foldersNumList = $recordF['id_folder'];
                    } else {
                        $foldersList .= " | ".$recordF['title'];
                        $foldersNumList .= ";".$recordF['id_folder'];
                    }
                }

                // store
                array_push(
                    $arrCategories,
                    array(
                        '1',
                        $record['id'],
                        $record['title'],
                        $record['order'],
                        $foldersList,
                        $foldersNumList
                    )
                );
                $rows = DB::query(
                    "SELECT * FROM ".prefix_table("categories")."
                    WHERE parent_id = %i
                    ORDER BY ".$pre."categories.order ASC",
                    $record['id']
                );
                if (count($rows) > 0) {
                    foreach ($rows as $field) {
                        array_push(
                            $arrCategories,
                            array(
                                '2',
                                $field['id'],
                                $field['title'],
                                $field['order'],
                                $field['encrypted_data'],
                                "",
                                $field['type']
                            )
                        );
                    }
                }
            }
            echo json_encode($arrCategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            break;

        case "categoryInFolders":
            // Prepare POST variables
            $post_foldersIds = filter_input(INPUT_POST, 'foldersIds', FILTER_SANITIZE_STRING);
            $post_id = $post_id;

            // update order
            if (empty($post_foldersIds) === false) {
                // delete all existing inputs
                DB::delete(
                    $pre."categories_folders",
                    "id_category = %i",
                    $post_id
                );
                // create new list
                $list = "";
                foreach (explode(';', $post_foldersIds) as $folder) {
                    DB::insert(
                        prefix_table("categories_folders"),
                        array(
                            'id_category' => $post_id,
                            'id_folder' => $folder
                            )
                    );

                    // prepare a list
                    $row = DB::queryfirstrow("SELECT title FROM ".prefix_table("nested_tree")." WHERE id=%i", $folder);
                    if (empty($list)) {
                        $list = $row['title'];
                    } else {
                        $list .= " | ".$row['title'];
                    }
                }
                echo '[{"list" : "'.$list.'"}]';
            }
            break;

        case "dataIsEncryptedInDB":
            // Prepare POST variables
            $post_encrypt = filter_input(INPUT_POST, 'encrypt', FILTER_SANITIZE_STRING);

            // store key
            DB::update(
                prefix_table("categories"),
                array(
                    'encrypted_data' => $post_encrypt
                    ),
                "id = %i",
                $post_id
            );

            // encrypt/decrypt existing data
            $rowsF = DB::query(
                "SELECT i.id, i.data, i.data_iv, i.encryption_type
                FROM ".$pre."categories_items AS i
                INNER JOIN ".prefix_table("categories")." AS c ON (i.field_id = c.id)
                WHERE c.id = %i",
                $post_id
            );
            foreach ($rowsF as $recordF) {
                $encryption_type = "";
                // decrypt/encrypt
                if ($post_encrypt === "0" && $recordF['encryption_type'] === "defuse") {
                    $encrypt = cryption(
                        $recordF['data'],
                        "",
                        "decrypt"
                    );
                    $encryption_type = "none";
                } elseif ($recordF['encryption_type'] === "none" || $recordF['encryption_type'] === "") {
                    $encrypt = cryption(
                        $recordF['data'],
                        "",
                        "encrypt"
                    );
                    $encryption_type = "defuse";
                }

                // store in DB
                if ($encryption_type !== "") {
                    DB::update(
                        prefix_table("categories_items"),
                        array(
                            'data' => $encrypt['string'],
                            'data_iv' => "",
                            'encryption_type' => $encryption_type
                            ),
                        "id = %i",
                        $recordF['id']
                    );
                }
            }

            echo '[{"error" : ""}]';
            break;
    }
}
