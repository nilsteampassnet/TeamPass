<?php
/**
 * @file          categories.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset==utf-8");
include 'main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to mysql server
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        case "addNewCategory":
            // store key
            $id = $db->queryInsert(
                'categories',
                array(
                    'parent_id' => 0,
                    'title' => $_POST['title'],
                    'level' => 0,
                    'order' => 1
                )
            );
            echo '[{"error" : "", "id" : "'.$id.'"}]';
            break;
        case "deleteCategory":
            $db->query("DELETE FROM ".$pre."categories WHERE id = '".$_POST['id']."'");
            $db->query("DELETE FROM ".$pre."categories_folders WHERE category_id = '".$_POST['id']."'");
            echo '[{"error" : ""}]';
            break;
        case "addNewField":
            // store key
            if (!empty($_POST['title']) && !empty($_POST['id'])) {
                $id = $db->queryInsert(
                    'categories',
                    array(
                        'parent_id' => $_POST['id'],
                        'title' => $_POST['title'],
                        'level' => 1,
                        'type' => 'text',
                        'order' => 1
                    )
                );
                echo '[{"error" : "", "id" : "'.$id.'"}]';
            }
            break;
        case "renameItem":
            // update key
            if (!empty($_POST['data']) && !empty($_POST['id'])) {
                $db->queryUpdate(
                    'categories',
                    array(
                        'title' => $_POST['data']
                       ),
                    "id='".$_POST['id']."'"
                );
                echo '[{"error" : "", "id" : "'.$_POST['id'].'"}]';
            }
            break;
        case "moveItem":
            // update key
            if (!empty($_POST['data']) && !empty($_POST['id'])) {
                $db->queryUpdate(
                    'categories',
                    array(
                        'parent_id' => $_POST['data'],
                        'order' => 99
                       ),
                    "id='".$_POST['id']."'"
                );
                echo '[{"error" : "", "id" : "'.$_POST['id'].'"}]';
            }
            break;
        case "saveOrder":
            // update order
            if (!empty($_POST['data'])) {
                foreach (explode(';', $_POST['data']) as $data) {
                    $elem = explode(':', $data);
                    $db->queryUpdate(
                        'categories',
                        array(
                            'order' => $elem[1]
                           ),
                        "id='".$elem[0]."'"
                    );
                }
                echo '[{"error" : ""}]';
            }
            break;
        case "loadFieldsList":
            $categoriesSelect = "";
            $arrCategories = $arrFields = array();
            $rows = $db->fetchAllArray("SELECT * FROM ".$pre."categories WHERE level = 0 ORDER BY ".$pre."categories.order ASC");
            foreach ($rows as $reccord) {
                // get associated folders
                $foldersList = $foldersNumList = "";
                $rowsF = $db->fetchAllArray(
                    "SELECT t.title AS title, c.id_folder as id_folder
                    FROM ".$pre."categories_folders AS c
                    INNER JOIN ".$pre."nested_tree AS t ON (c.id_folder = t.id)
                    WHERE c.id_category = ".$reccord['id']
                );
                foreach ($rowsF as $reccordF) {
                    if (empty($foldersList)) {
                        $foldersList = $reccordF['title'];
                        $foldersNumList = $reccordF['id_folder'];
                    } else {
                        $foldersList .= " | ".$reccordF['title'];
                        $foldersNumList .= ";".$reccordF['id_folder'];
                    }
                }
                
                // store
                array_push(
                    $arrCategories,
                    array(
                        '1',
                        $reccord['id'],
                        $reccord['title'],
                        $reccord['order'],
                        $foldersList,
                        $foldersNumList
                    )
                );
                $rows = $db->fetchAllArray("SELECT * FROM ".$pre."categories WHERE parent_id = ".$reccord['id']." ORDER BY ".$pre."categories.order ASC");
                if (count($rows) > 0) {
                    foreach ($rows as $field) {
                        array_push(
                            $arrCategories,
                            array(
                                '2',
                                $field['id'],
                                $field['title'],
                                $field['order'],
                                '',
                                ''
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
                $db->query("DELETE FROM ".$pre."categories_folders WHERE id_category = '".$_POST['id']."'");
                // create new list
                $list = "";
                foreach (explode(';', $_POST['foldersIds']) as $folder) {
                    $db->queryInsert(
                        'categories_folders',
                        array(
                            'id_category' => $_POST['id'],
                            'id_folder' => $folder
                           )
                    );
                    
                    // prepare a list
                    //$row = $db->fetchRow("SELECT title FROM ".$pre."nested_tree WHERE id=".$folder);
                    $row = $db->queryGetRow(
                        "nested_tree",
                        array(
                            "title"
                        ),
                        array(
                            "id" => intval($folder)
                        )
                    );
                    if (empty($list)) {
                        $list = $row[0];
                    } else {
                        $list .= " | ".$row[0];
                    }
                }
                echo '[{"list" : "'.$list.'"}]';
            }
            break;
    }
}