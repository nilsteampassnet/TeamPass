<?php
/**
 * @file          categories.queries.php
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
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");
require_once 'main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to mysql server
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

if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        case "addNewCategory":
            // store key
            DB::insert(
                prefix_table("categories"),
                array(
                    'parent_id' => 0,
                    'title' => $_POST['title'],
                    'level' => 0,
                    'order' => 1
                )
            );
            echo '[{"error" : "", "id" : "'.DB::insertId().'"}]';
            break;

        case "deleteCategory":
            DB::delete(prefix_table("categories"), "id = %i", $_POST['id']);
            DB::delete(prefix_table("categories_folders"), "id_category = %i", $_POST['id']);
            echo '[{"error" : ""}]';
            break;

        case "addNewField":
            // store key
            if (!empty($_POST['title']) && !empty($_POST['id'])) {
                DB::insert(
                    prefix_table("categories"),
                    array(
                        'parent_id' => $_POST['id'],
                        'title' => $_POST['title'],
                        'level' => 1,
                        'type' => 'text',
                        'order' => 1
                    )
                );
                echo '[{"error" : "", "id" : "'.DB::insertId().'"}]';
            }
            break;

        case "renameItem":
            // update key
            if (!empty($_POST['data']) && !empty($_POST['id'])) {
                DB::update(
                    prefix_table("categories"),
                    array(
                        'title' => $_POST['data']
                       ),
                    "id=%i",
                    $_POST['id']
                );
                echo '[{"error" : "", "id" : "'.$_POST['id'].'"}]';
            }
            break;

        case "moveItem":
            // update key
            if (!empty($_POST['data']) && !empty($_POST['id'])) {
                DB::update(
                    prefix_table("categories"),
                    array(
                        'parent_id' => $_POST['data'],
                        'order' => 99
                       ),
                    "id=%i",
                    $_POST['id']
                );
                echo '[{"error" : "", "id" : "'.$_POST['id'].'"}]';
            }
            break;

        case "saveOrder":
            // update order
            if (!empty($_POST['data'])) {
                foreach (explode(';', $_POST['data']) as $data) {
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
                                ""
                            )
                        );
                    }
                }
            }
            echo json_encode($arrCategories, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            break;

        case "categoryInFolders":
            // update order
            if (!empty($_POST['foldersIds'])) {
                // delete all existing inputs
                DB::delete($pre."categories_folders", "id_category = %i", $_POST['id']);
                // create new list
                $list = "";
                foreach (explode(';', $_POST['foldersIds']) as $folder) {
                    DB::insert(
                        prefix_table("categories_folders"),
                        array(
                            'id_category' => $_POST['id'],
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
            // store key
            DB::update(
                prefix_table("categories"),
                array(
                    'encrypted_data' => $_POST['encrypt']
                   ),
                "id = %i",
                $_POST['id']
            );

            // encrypt/decrypt existing data
            $rowsF = DB::query(
                "SELECT i.id, i.data, i.data_iv
                FROM ".$pre."categories_items AS i
                INNER JOIN ".prefix_table("categories")." AS c ON (i.field_id = c.id)
                WHERE c.id = %i",
                $_POST['id']
            );
            foreach ($rowsF as $recordF) {
                // decrypt/encrypt
                if ($_POST['encrypt'] === "0") {
                    $encrypt = cryption(
                        $recordF['data'],
                        "",
                        "decrypt"
                    );
                } else {
                    $encrypt = cryption(
                        $recordF['data'],
                        "",
                        "encrypt"
                    );
                }

                // store in DB
                DB::update(
                    prefix_table("categories_items"),
                    array(
                        'data' => $encrypt['string'],
                        'data_iv' => ""
                       ),
                    "id = %i",
                    $recordF['id']
                );
            }

            echo '[{"error" : ""}]';
            break;
    }
}