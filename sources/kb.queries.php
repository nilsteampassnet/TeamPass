<?php
/**
 * @file 		kb.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');


require_once('../includes/language/'.$_SESSION['user_language'].'.php');
include('../includes/settings.php');
require_once('../includes/include.php');
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
include('main.functions.php');

//Connect to mysql server
require_once("class.database.php");
$db = new Database($server, $user, $pass, $database, $pre);
$db->connect();

function utf8Urldecode($value)
{
	$value = preg_replace('/%([0-9a-f]{2})/ie', 'chr(hexdec($1))', (string) $value);
	return $value;
}

// Construction de la requête en fonction du type de valeur
if ( !empty($_POST['type']) ){
	switch($_POST['type'])
	{
		case "kb_in_db":
			//decrypt and retreive data in JSON format
			require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
			require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
			$data_received = json_decode((AesCtr::decrypt($_POST['data'], $_SESSION['key'], 256)), true);

			//Prepare variables
			$id = htmlspecialchars_decode($data_received['id']);
			$label = htmlspecialchars_decode($data_received['label']);
			$category = htmlspecialchars_decode($data_received['category']);
			$anyone_can_modify = htmlspecialchars_decode($data_received['anyone_can_modify']);
			$kb_associated_to = htmlspecialchars_decode($data_received['kb_associated_to']);
			$description = htmlspecialchars_decode($data_received['description']);

			//check if allowed to modify
            if (isset($id) && !empty($id)) {
			    $row = $db->query("SELECT anyone_can_modify, author_id FROM ".$pre."kb WHERE id = ".$id);
			    $ret = $db->fetch_array($row);
                if ($ret['anyone_can_modify'] == 1 || $ret['author_id'] == $_SESSION['user_id']) {
                    $manage_kb = true;
                }else{
                    $manage_kb = false;
                }
            }else{
                $manage_kb = true;
            }
			if ($manage_kb == true) {
				//Add category if new
				$data = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."kb_categories WHERE category = '".mysql_real_escape_string($category)."'");
				if ( $data[0] == 0 ){
					$cat_id = $db->query_insert(
					"kb_categories",
					array(
					    'category' => mysql_real_escape_string($category)
					)
					);
				}else{
					//get the ID of this existing category
					$cat_id = $db->fetch_row("SELECT id FROM ".$pre."kb_categories WHERE category = '".mysql_real_escape_string($category)."'");
					$cat_id = $cat_id[0];
				}

				if (isset($id) && !empty($id)) {
					//update KB
					$new_id = $db->query_update(
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
				}else{
					//add new KB
					$new_id = $db->query_insert(
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
                $db->query_delete(
                    "kb_items",
                    array(
                        'kb_id' => $new_id
                    )
                );
                //add all items associated to this KB
                foreach(explode(',', $kb_associated_to) as $item_id) {
                    $db->query_insert(
                        "kb_items",
                        array(
                            'kb_id' => $new_id,
                            'item_id' => $item_id
                        )
                    );
                }

				echo '[ { "status" : "done" } ]';
			}else{
				echo '[ { "status" : "none" } ]';
			}


		break;


		case "open_kb":
			$row = $db->query("SELECT k.id AS id, k.label AS label, k.description AS description, k.category_id AS category_id, k.author_id AS author_id, k.anyone_can_modify AS anyone_can_modify,
							u.login AS login, c.category AS category
							FROM ".$pre."kb AS k
							INNER JOIN ".$pre."kb_categories AS c ON (c.id = k.category_id)
							INNER JOIN ".$pre."users AS u ON (u.id = k.author_id)
							WHERE k.id = '".$_POST['id']."'
			");
			$ret = $db->fetch_array($row);

            //select associated items
            $rows = $db->fetch_all_array("SELECT item_id
                            FROM ".$pre."kb_items
                            WHERE kb_id = '".$_POST['id']."'
            ");
			$arrOptions = array();
            foreach( $rows as $reccord ) {
                //echo '$("#kb_associated_to option[value='.$reccord['item_id'].']").attr("selected","selected");';
            	array_push($arrOptions, $reccord['item_id']);
            }

			$arrOutput = array(
				"label" 	=> $ret['label'],
				"category" 	=> $ret['category'],
				"description" 	=> $ret['description'],
				"anyone_can_modify" 	=> $ret['anyone_can_modify'],
				"options" 	=> $arrOptions
			);

			echo json_encode($arrOutput,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
		break;


		case "delete_kb":
			$db->query_delete(
				"kb",
				array(
					'id' => $_POST['id']
				)
			);
			//echo 'oTable = $("#t_kb").dataTable();LoadingPage();oTable.fnDraw();';
			break;
	}
}
?>