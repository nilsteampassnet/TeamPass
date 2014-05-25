<?php
/**
 *
 * @file          configapi.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$teampass_config_file = "../includes/settings.php";
$_SESSION['CPM'] = 1;

function teampass_api_enabled() {
	$bdd = teampass_connect();
	$response = $bdd->query("select valeur from ".$GLOBALS['pre']."misc WHERE type = 'admin' AND intitule = 'api'");
	return $response->fetch(PDO::FETCH_ASSOC);
}

function teampass_whitelist() {
    $bdd = teampass_connect();
	$apiip_pool = teampass_get_ips();
	if (count($apiip_pool) > 0 && array_search($_SERVER['REMOTE_ADDR'], $apiip_pool) === false) {
		rest_error('IPWHITELIST');
	}
}

function teampass_connect() {
	require_once($GLOBALS['teampass_config_file']);
	try
	{
		$bdd = new PDO("mysql:host=".$GLOBALS['server'].";dbname=".$GLOBALS['database'], $GLOBALS['user'], $GLOBALS['pass']);
		return ($bdd);
	}
	catch (Exception $e)
	{
		rest_error('MYSQLERR', 'Error : ' . $e->getMessage());
	}

}

function teampass_get_ips() {
	$array_of_results = array();
	$bdd = teampass_connect();
	$response = $bdd->query("select value from ".$GLOBALS['pre']."api WHERE type = 'ip'");
	while ($data = $response->fetch())
	{
		array_push($array_of_results, $data['value']);
	}

	return $array_of_results;
}

function teampass_get_keys() {
	$bdd = teampass_connect();
	$response = $bdd->query("select value from ".$GLOBALS['pre']."api WHERE type = 'key'");

	return $response->fetch(PDO::FETCH_ASSOC);
}

function teampass_get_randkey() {
	$bdd = teampass_connect();
	$response = $bdd->query("select rand_key from ".$GLOBALS['pre']."keys limit 0,1");

	$array = $response->fetch(PDO::FETCH_OBJ);

	return $array->rand_key;
}

function rest_head () {
	header('HTTP/1.1 402 Payment Required');
}

function addToCacheTable($id)
{
    $bdd = teampass_connect();
    // get data
    $data = $bdd->query(
        "SELECT i.label AS label, i.description AS description, i.id_tree AS id_tree, i.perso AS perso, i.restricted_to AS restricted_to, i.login AS login, i.id AS id
        FROM ".$GLOBALS['pre']."items AS i
        AND ".$GLOBALS['pre']."log_items AS l ON (l.id_item = i.id)
        WHERE i.id = '".intval($id)."'
        AND l.action = 'at_creation'"
    );
    $data = $data->fetch();

    // Get all TAGS
    $tags = "";
    $data_tags = $bdd->query("SELECT tag FROM ".$GLOBALS['pre']."tags WHERE item_id=".$id);
    $itemTags = $bdd->mysql_fetch_array($data_tags);
    foreach ($itemTags as $itemTag) {
        if (!empty($itemTag['tag'])) {
            $tags .= $itemTag['tag']." ";
        }
    }
    // form id_tree to full foldername
    /*$folder = "";
    $arbo = $tree->getPath($data['id_tree'], true);
    foreach ($arbo as $elem) {
        if ($elem->title == $_SESSION['user_id'] && $elem->nlevel == 1) {
            $elem->title = $_SESSION['login'];
        }
        if (empty($folder)) {
            $folder = stripslashes($elem->title);
        } else {
            $folder .= " » ".stripslashes($elem->title);
        }
    }*/
    // finaly update
    $bdd->query(
        "INSERT INTO ".$GLOBALS['pre']."cache (id, label, description, tags, id_tree, perso, restricted_to, login, folder, restricted_to, author)
                            VALUES ('".$data['id']."', '".$data['label']."', '".$data['description']."', '".$tags."', '".$data['id_tree']."', '".$data['perso']."', '".$data['restricted_to']."', '".$data['login']."', '', '0', '9999999')"
    );
}

function rest_delete () {
	if(apikey_checker($GLOBALS['apikey'])) {
		$bdd = teampass_connect();
		$rand_key = teampass_get_randkey();
        $category_query = "";

		if ($GLOBALS['request'][0] == "write") {
			if($GLOBALS['request'][1] == "category") {
				$array_category = explode(';',$GLOBALS['request'][2]);

				foreach($array_category as $category) {
					if(!preg_match_all("/^([\w\:\'\-\sàáâãäåçèéêëìíîïðòóôõöùúûüýÿ]+)$/i", $category,$result)) {
						rest_error('CATEGORY_MALFORMED');
					}
				}

				if(count($array_category) > 1 && count($array_category) < 5) {
					for ($i = count($array_category); $i > 0; $i--) {
						$slot = $i - 1;
						if (!$slot) {
							$category_query .= "select id from ".$GLOBALS['pre']."nested_tree where title LIKE '".$array_category[$slot]."' AND parent_id = 0";
						} else {
							$category_query .= "select id from ".$GLOBALS['pre']."nested_tree where title LIKE '".$array_category[$slot]."' AND parent_id = (";
						}
					}
					for ($i = 1; $i < count($array_category); $i++) { $category_query .= ")"; }
				} elseif (count($array_category) == 1) {
					$category_query = "select id from ".$GLOBALS['pre']."nested_tree where title LIKE '".$array_category[0]."' AND parent_id = 0";
				} else {
					rest_error ('NO_CATEGORY');
				}

				// Delete items which in category
				$response = $bdd->query("delete from ".$GLOBALS['pre']."items where id_tree = (".$category_query.")");
				// Delete sub-categories which in category
				$response = $bdd->query("delete from ".$GLOBALS['pre']."nested_tree where parent_id = (".$category_query.")");
				// Delete category
				$response = $bdd->query("delete from ".$GLOBALS['pre']."nested_tree where id = (".$category_query.")");

				$json['type'] = 'category';
				$json['category'] = $GLOBALS['request'][2];
				if($response) {
					$json['status'] = 'OK';
				} else {
					$json['status'] = 'KO';
				}

			} elseif($GLOBALS['request'][1] == "item") {
				$array_category = explode(';',$GLOBALS['request'][2]);
				$item = $GLOBALS['request'][3];

				foreach($array_category as $category) {
					if(!preg_match_all("/^([\w\:\'\-\sàáâãäåçèéêëìíîïðòóôõöùúûüýÿ]+)$/i", $category,$result)) {
						rest_error('CATEGORY_MALFORMED');
					}
				}

				if(!preg_match_all("/^([\w\:\'\-\sàáâãäåçèéêëìíîïðòóôõöùúûüýÿ]+)$/i", $item,$result)) {
					rest_error('ITEM_MALFORMED');
				} elseif (empty($item) || count($array_category) == 0) {
					rest_error('MALFORMED');
				}

				if(count($array_category) > 1 && count($array_category) < 5) {
					for ($i = count($array_category); $i > 0; $i--) {
						$slot = $i - 1;
						if (!$slot) {
							$category_query .= "select id from ".$GLOBALS['pre']."nested_tree where title LIKE '".$array_category[$slot]."' AND parent_id = 0";
						} else {
							$category_query .= "select id from ".$GLOBALS['pre']."nested_tree where title LIKE '".$array_category[$slot]."' AND parent_id = (";
						}
					}
					for ($i = 1; $i < count($array_category); $i++) { $category_query .= ")"; }
				} elseif (count($array_category) == 1) {
					$category_query = "select id from ".$GLOBALS['pre']."nested_tree where title LIKE '".$array_category[0]."' AND parent_id = 0";
				} else {
					rest_error ('NO_CATEGORY');
				}

				// Delete item
				$response = $bdd->query("delete from ".$GLOBALS['pre']."items where id_tree = (".$category_query.") and label LIKE '".$item."'");
				$json['type'] = 'item';
				$json['item'] = $item;
				$json['category'] = $GLOBALS['request'][2];
				if($response) {
					$json['status'] = 'OK';
				} else {
					$json['status'] = 'KO';
				}
			}

			if ($json) {
				echo json_encode($json);
			} else {
				rest_error ('EMPTY');
			}
		} else {
			rest_error ('METHOD');
		}
	}
}

function rest_get () {
	if(apikey_checker($GLOBALS['apikey'])) {
		$bdd = teampass_connect();
		$rand_key = teampass_get_randkey();
        $category_query = "";

		if ($GLOBALS['request'][0] == "read") {
			if($GLOBALS['request'][1] == "category") {
				// get ids
				if (strpos($GLOBALS['request'][2],",") > 0) {
					$condition = "id_tree IN (".$GLOBALS['request'][2].")";
				} else {
					$condition = "id_tree = '".$GLOBALS['request'][2]."'";
				}
				$response = $bdd->query("select id,label,login,pw from ".$GLOBALS['pre']."items where ".$condition);
				while ($data = $response->fetch())
				{
					$id = $data['id'];
					$json[$id]['label'] = utf8_encode($data['label']);
					$json[$id]['login'] = utf8_encode($data['login']);
					$json[$id]['pw'] = teampass_decrypt_pw($data['pw'],SALT,$rand_key);
				}
			} elseif($GLOBALS['request'][1] == "items") {
                // only accepts numeric
				$array_items = explode(',',$GLOBALS['request'][2]);

                $items_list = "";

				foreach($array_items as $item) {
					if(!is_numeric($item)) {
						rest_error('ITEM_MALFORMED');
					}
				}

				if(count($array_items) > 1 && count($array_items) < 5) {
					foreach($array_items as $item) {
						if (empty($items_list)) {
							$items_list = $item;
						} else {
                            $items_list .= ",".$item;
						}
					}
				} elseif (count($array_items) == 1) {
                    $items_list = $item;
				} else {
					rest_error ('NO_ITEM');
				}
				$response = $bdd->query("select id,label,login,pw,id_tree from ".$GLOBALS['pre']."items where id IN (".$items_list.")");
				while ($data = $response->fetch())
				{
					$id = $data['id'];
					$json[$id]['label'] = utf8_encode($data['label']);
					$json[$id]['login'] = utf8_encode($data['login']);
					$json[$id]['pw'] = teampass_decrypt_pw($data['pw'],SALT,$rand_key);
				}
			}

			if (isset($json) && $json) {
				echo json_encode($json);
			} else {
				rest_error ('EMPTY');
			}
        } elseif ($GLOBALS['request'][0] == "add") {
            if($GLOBALS['request'][1] == "item") {
                include "../sources/main.functions.php";

                // get item definition
                $array_item = explode(';', $GLOBALS['request'][2]);
                if (count($array_item) != 9) {
                    rest_error ('BADDEFINITION');
                }
                $item_label = $array_item[0];
                $item_pwd = $array_item[1];
                $item_desc = $array_item[2];
                $item_folder_id = $array_item[3];
                $item_login = $array_item[4];
                $item_email = $array_item[5];
                $item_url = $array_item[6];
                $item_tags = $array_item[7];
                $item_anyonecanmodify = $array_item[8];

                // do some checks
                if (!empty($item_label) && !empty($item_pwd) && !empty($item_folder_id)) {
                    // Check length
                    if (strlen($item_pwd) > 50) {
                        rest_error ('BADDEFINITION');
                    }

                    // Check Folder ID
                    $data = $bdd->query("SELECT COUNT(*) FROM ".$GLOBALS['pre']."nested_tree WHERE id = '".$item_folder_id."'");
                    $data = $data->fetch();
                    if ($data[0] == 0) {
                        rest_error ('BADDEFINITION');
                    }

                    // check if element doesn't already exist
                    $data = $bdd->query("SELECT COUNT(*) FROM ".$GLOBALS['pre']."items WHERE label = '".addslashes($item_label)."' AND inactif = '0'");
                    $data = $data->fetch();
                    if ($data[0] != 0) {
                        $itemExists = 1;
                    } else {
                        $itemExists = 0;
                    }
                    if ($itemExists == 0) {
                        // prepare password and generate random key
                        $randomKey = substr(md5(rand().rand()), 0, 15);
                        $item_pwd = $randomKey.$item_pwd;
                        $item_pwd = encrypt($item_pwd);
                        if (empty($item_pwd)) {
                            rest_error ('BADDEFINITION');
                        }

                        // ADD item
                        try {
                            $result = $bdd->query(
                            "INSERT INTO ".$GLOBALS['pre']."items (`label`, `description`, `pw`, `email`, `url`, `id_tree`, `login`, `inactif`, `restricted_to`, `perso`, `anyone_can_modify`)
                            VALUES ('".$item_label."', '".$item_desc."', '".$item_pwd."', '".$item_email."', '".$item_url."', '".intval($item_folder_id)."', '".$item_login."', '0', '', '0', '".intval($item_anyonecanmodify)."')"
                        );
                            $newID = $bdd->lastInsertId();

                        // Store generated key
                            $result = $bdd->query("INSERT INTO ".$GLOBALS['pre']."keys (`table`, `id`, `rand_key`) VALUES ('items', ".$newID.", '".$randomKey."')");

                        // log
                            $result = $bdd->query("INSERT INTO ".$GLOBALS['pre']."log_items (`id_item`, `date`, `id_user`, `action`) VALUES (".$newID.", '".time()."', 9999999, 'at_creation')");

                        // Add tags
                            $tags = explode(' ', $item_tags);
                            foreach ($tags as $tag) {
                                if (!empty($tag)) {
                                    $result = $bdd->query("INSERT INTO ".$GLOBALS['pre']."tags (`item_id`, `tag`) VALUES ($newID, '".strtolower($tag)."')");
                                }
                            }

                        // Update CACHE table
                            $result = $bdd->exec(
                            "INSERT INTO ".$GLOBALS['pre']."cache (`id`, `label`, `description`, `tags`, `id_tree`, `perso`, `restricted_to`, `login`, `folder`, `author`)
                            VALUES ('".$newID."', '".$item_label."', '".$item_desc."', '".$item_tags."', '".$item_folder_id."', '0', '', '".$item_login."', '', '9999999')"
                        );

                            echo '{"status":"item added"}';
                        } catch(PDOException $ex) {
                            echo '<br />' . $ex->getMessage();
                        }
                    } else {
                        rest_error ('BADDEFINITION');
                    }
                } else {
                    rest_error ('BADDEFINITION');
                }
            }
		} else {
			rest_error ('METHOD');
		}
	}
}

function rest_put() {
    if(apikey_checker($GLOBALS['apikey'])) {
        $bdd = teampass_connect();
        $rand_key = teampass_get_randkey();
    }
}

function rest_error ($type,$detail = 'N/A') {
	switch ($type) {
  		case 'APIKEY':
		$message = Array('err' => 'This api_key '.$GLOBALS['apikey'].' doesn\'t exist');
		header('HTTP/1.1 405 Method Not Allowed');
    		break;
  		case 'NO_CATEGORY':
		$message = Array('err' => 'No category specified');
    		break;
        case 'NO_ITEM':
            $message = Array('err' => 'No item specified');
            break;
  		case 'EMPTY':
		$message = Array('err' => 'No results');
    		break;
		case 'IPWHITELIST':
		$message = Array('err' => 'Ip address '.$_SERVER['REMOTE_ADDR'].' not allowed.');
		header('HTTP/1.1 405 Method Not Allowed');
		break;
		case 'MYSQLERR':
		$message = Array('err' => $detail);
		header('HTTP/1.1 500 Internal Server Error');
		break;
		case 'METHOD':
		$message = Array('err' => 'Method not authorized');
		header('HTTP/1.1 405 Method Not Allowed');
		break;
        case 'BADDEFINITION':
            $message = Array('err' => 'Item definition not complete');
            header('HTTP/1.1 405 Method Not Allowed');
            break;
        case 'ITEM_MALFORMED':
            $message = Array('err' => 'Item definition not numeric');
            header('HTTP/1.1 405 Method Not Allowed');
            break;
		default:
		$message = Array('err' => 'Something happen ... but what ?');
		header('HTTP/1.1 500 Internal Server Error');
		break;
	}

	echo json_encode($message);
	exit(0);
}

function apikey_checker ($apikey_used) {
    $bdd = teampass_connect();
	$apikey_pool = teampass_get_keys();
	if (in_array($apikey_used, $apikey_pool)) {
		return(1);
	} else {
		rest_error('APIKEY',$apikey_used);
	}
}

function teampass_pbkdf2_hash($p, $s, $c, $kl, $st = 0, $a = 'sha256')
{
    $kb = $st + $kl;
    $dk = '';

    for ($block = 1; $block <= $kb; $block++) {
        $ib = $h = hash_hmac($a, $s . pack('N', $block), $p, true);
        for ($i = 1; $i < $c; $i++) {
            $ib ^= ($h = hash_hmac($a, $h, $p, true));
        }
        $dk .= $ib;
    }

    return substr($dk, $st, $kl);
}

function teampass_decrypt_pw($encrypted, $salt, $rand_key, $itcount = 2072)
{
    $encrypted = base64_decode($encrypted);
    $pass_salt = substr($encrypted, -64);
    $encrypted = substr($encrypted, 0, -64);
    $key       = teampass_pbkdf2_hash($salt, $pass_salt, $itcount, 16, 32);
    $iv        = base64_decode(substr($encrypted, 0, 43) . '==');
    $encrypted = substr($encrypted, 43);
    $mac       = substr($encrypted, -64);
    $encrypted = substr($encrypted, 0, -64);
    if ($mac !== hash_hmac('sha256', $encrypted, $salt)) return null;
    return substr(rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $encrypted, 'ctr', $iv), "\0\4"), strlen($rand_key));
}