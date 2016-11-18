<?php
/**
 * @file          suggestion.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.26
 * @copyright     (c) 2009-2016 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'sessions.php';
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
                $encrypt = cryption($pwd, SALT, "", "encrypt");

                // query
                DB::insert(
                    prefix_table("suggestion"),
                    array(
                        'label' => $label,
                        'description' => ($description),
                        'author_id' => $_SESSION['user_id'],
                        'pw' => $encrypt['string'],
                        'pw_iv' => $encrypt['iv'],
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
                        'pw_iv' => $suggestion['pw_iv']
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
                        'pw_iv' => $suggestion['pw_iv']
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
    }
}
