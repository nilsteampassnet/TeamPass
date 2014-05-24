<?php
/**
 * @file          kb.queries.php
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
        !isset($_SESSION['key']) || empty($_SESSION['key'])
        || !isset($_SESSION['settings']['enable_kb'])
        || $_SESSION['settings']['enable_kb'] != 1)
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "kb")) {
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

//Connect to DB
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

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
                $ret = $db->queryGetArray(
                    "kb",
                    array(
                        "anyone_can_modify",
                        "author_id"
                    ),
                    array(
                        "id" => intval($id)
                    )
                );
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
                //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."kb_categories WHERE category = '".mysql_real_escape_string($category)."'");
                $data = $db->queryCount(
                    "kb_categories",
                    array(
                        "category" => $category
                    )
                );
                if ($data[0] == 0) {
                    $cat_id = $db->queryInsert(
                        "kb_categories",
                        array(
                            'category' => mysql_real_escape_string($category)
                       )
                    );
                } else {
                    //get the ID of this existing category
                    $cat_id = $db->fetchRow("SELECT id FROM ".$pre."kb_categories WHERE category = '".mysql_real_escape_string($category)."'");
                    $cat_id = $cat_id[0];
                }

                if (isset($id) && !empty($id)) {
                    //update KB
                    $new_id = $db->queryUpdate(
                        "kb",
                        array(
                            'label' => ($label),
                            'description' => ($description),
                            'author_id' => $_SESSION['user_id'],
                            'category_id' => $cat_id,
                            'anyone_can_modify' => $anyone_can_modify
                       ),
                        "id='".$id."'"
                    );
                } else {
                    //add new KB
                    $new_id = $db->queryInsert(
                        "kb",
                        array(
                            'label' => $label,
                            'description' => ($description),
                            'author_id' => $_SESSION['user_id'],
                            'category_id' => $cat_id,
                            'anyone_can_modify' => $anyone_can_modify
                       )
                    );
                }

                //delete all associated items to this KB
                $db->queryDelete(
                    "kb_items",
                    array(
                        'kb_id' => $new_id
                   )
                );
                //add all items associated to this KB
                foreach (explode(',', $kb_associated_to) as $item_id) {
                    $db->queryInsert(
                        "kb_items",
                        array(
                            'kb_id' => $new_id,
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
            $ret = $db->queryGetArray(
                array(
                    "kb" => "k"
                ),
                array(
                    "k.id" => "id",
                    "k.label" => "label",
                    "k.description" => "description",
                    "k.category_id" => "category_id",
                    "k.author_id" => "author_id",
                    "k.anyone_can_modify" => "anyone_can_modify",
                    "u.login" => "login",
                    "c.category" => "category"
                ),
                array(
                    "k.id" => intval($_POST['id'])
                ),
                "",
                array(
                    "kb_categories AS c" => "(c.id = k.category_id)",
                    "users AS u" => "(u.id = k.author_id)"
                )
            );

            //select associated items
            $rows = $db->fetchAllArray(
                "SELECT item_id
                FROM ".$pre."kb_items
                WHERE kb_id = '".$_POST['id']."'"
            );
            $arrOptions = array();
            foreach ($rows as $reccord) {
                //echo '$("#kb_associated_to option[value='.$reccord['item_id'].']").attr("selected","selected");';
                array_push($arrOptions, $reccord['item_id']);
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
            $db->queryDelete(
                "kb",
                array(
                    'id' => $_POST['id']
               )
            );
            break;
    }
}
