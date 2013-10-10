<?php
/**
 * @file          categories.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2013 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
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
                array_push(
                    $arrCategories,
                    array(
                        '1',
                        $reccord['id'],
                        $reccord['title'],
                        $reccord['order']
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
                                $field['order']
                            )
                        );
                    }
                }
            }
            echo json_encode($arrCategories, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            break;
    }
}