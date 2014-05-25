<?php
/**
 * @file          suggestion.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.20
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
    !isset($_SESSION['key']) || empty($_SESSION['key'])
    || !isset($_SESSION['settings']['enable_suggestion'])
    || $_SESSION['settings']['enable_suggestion'] != 1)
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "suggestion")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
include 'main.functions.php';

// pw complexity levels
$pwComplexity = array(
    0 => array(0, $LANG['complex_level0']),
    25 => array(25, $LANG['complex_level1']),
    50 => array(50, $LANG['complex_level2']),
    60 => array(60, $LANG['complex_level3']),
    70 => array(70, $LANG['complex_level4']),
    80 => array(80, $LANG['complex_level5']),
    90 => array(90, $LANG['complex_level6'])
);

// connect to DB
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

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
            $data = $db->queryCount(
                "suggestion",
                array(
                    "label" => addslashes($label),
                    "folder_id" => $folder
                )
            );
            if ($data[0] == 0) {
                // generate random key
                $randomKey = generateKey();

                // query
                $new_id = $db->queryInsert(
                    "suggestion",
                    array(
                        'label' => $label,
                        'description' => ($description),
                        'author_id' => $_SESSION['user_id'],
                        'password' => encrypt($randomKey.$pwd),
                        'comment' => $comment,
                        'folder_id' => $folder,
                        'key' => $randomKey
                    )
                );

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
            $db->queryDelete(
                "suggestion",
                array(
                    'id' => $_POST['id']
                )
            );
        break;

        case "duplicate_suggestion":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get suggestion details
            $suggestion = $db->queryGetRow(
                "suggestion",
                array(
                    "label",
                    "description",
                    "password",
                    "key",
                    "folder_id",
                    "author_id",
                    "comment"
                ),
                array(
                    "id" => $_POST['id']
                )
            );

            // check if similar exists
            $existing_item_id = $db->queryGetRow(
                "items",
                array(
                    "id",
                    "description"
                ),
                array(
                    "label" => addslashes($suggestion[0]),
                    "id_tree" => $suggestion[4]
                )
            );
            if ($existing_item_id[0] != null) {
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
            $suggestion = $db->queryGetRow(
                "suggestion",
                array(
                    "label",
                    "description",
                    "password",
                    "key",
                    "folder_id",
                    "author_id",
                    "comment"
                ),
                array(
                    "id" => $_POST['id']
                )
            );

            // check if similar Item exists (based upon Label and folder id)
            // if Yes, update the existing one
            // if Not, create new Item
            $existing_item_id = $db->queryGetRow(
                "items",
                array(
                    "id",
                    "description"
                ),
                array(
                    "label" => addslashes($suggestion[0]),
                    "id_tree" => $suggestion[4]
                )
            );
            if ($existing_item_id[0] != null) {
                // update existing item
                $updStatus = $db->queryUpdate(
                    'items',
                    array(
                        'description' => !empty($suggestion[1]) ? $existing_item_id[1]."<br />----<br />".$suggestion[1] : $existing_item_id[1],
                        'pw' => $suggestion[2]
                    ),
                    "id='".$existing_item_id[0]."'"
                );
                if ($updStatus) {
                    // update KEY
                    $updStatus = $db->queryUpdate(
                        'keys',
                        array(
                            'rand_key' => $suggestion[3]
                        ),
                        array(
                            "table" => "items",
                            "id" => $existing_item_id[0]
                        )
                    );

                    // update LOG
                    $db->queryInsert(
                        'log_items',
                        array(
                            'id_item' => $existing_item_id[0],
                            'date' => time(),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_modification',
                            'raison' => 'at_suggestion'
                        )
                    );


                    // update cache table
                    updateCacheTable("update_value", $existing_item_id[0]);

                    // delete suggestion
                    $db->queryDelete(
                        "suggestion",
                        array(
                            'id' => $_POST['id']
                        )
                    );

                    echo '[ { "status" : "done" } ]';
                } else {
                    echo '[ { "status" : "error_when_updating" } ]';
                }
            } else {
                // add as Item
                $newID = $db->queryInsert(
                    'items',
                    array(
                        'label' => $suggestion[0],
                        'description' => $suggestion[1],
                        'pw' => $suggestion[2],
                        'id_tree' => $suggestion[4],
                        'inactif' => '0',
                        'perso' => '0',
                        'anyone_can_modify' => '0'
                    )
                );

                if (is_numeric($newID)) {
                    // add Key
                    $db->queryInsert(
                        'keys',
                        array(
                            'table' => 'items',
                            'id' => $newID,
                            'rand_key' => $suggestion[3]
                        )
                    );

                    // update log
                    $db->queryInsert(
                        'log_items',
                        array(
                            'id_item' => $newID,
                            'date' => time(),
                            'id_user' => $suggestion[5],
                            'action' => 'at_creation'
                        )
                    );

                    // update cache table
                    updateCacheTable("add_value", $newID);

                    // delete suggestion
                    $db->queryDelete(
                        "suggestion",
                        array(
                            'id' => $_POST['id']
                        )
                    );

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

            $data = $db->queryGetRow(
                "misc",
                array(
                    "valeur"
                ),
                array(
                    "intitule" => $_POST['folder_id'],
                    "type" => "complex"
                )
            );
            if (isset($data[0]) && (!empty($data[0]) || $data[0] == 0)) {
                $complexity = $pwComplexity[$data[0]][1];
            } else {
                $complexity = $LANG['not_defined'];
            }

            echo '[ { "status" : "ok" , "complexity" : "'.$data[0].'" , "complexity_text" : "'.$complexity.'" } ]';
        break;
    }
}