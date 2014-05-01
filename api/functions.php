<?php

function teampass_api_enabled() {
	$bdd = teampass_connect();
	$response = $bdd->query("select valeur from ".$GLOBALS['pre']."misc WHERE type = 'admin' AND intitule = 'api'");
	return $response->fetch(PDO::FETCH_ASSOC);
}

function teampass_whitelist() {
    $bdd = teampass_connect();
	$apiip_pool = teampass_get_ips();
	if (count($apiip_pool) > 0 && !array_search($_SERVER['REMOTE_ADDR'], $apiip_pool)) {
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

function rest_delete () {
	if(apikey_checker($GLOBALS['apikey'])) {
		$bdd = teampass_connect();
		$rand_key = teampass_get_randkey();

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

		if ($GLOBALS['request'][0] == "read") {
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
				$response = $bdd->query("select id,label,login,pw from ".$GLOBALS['pre']."items where id_tree = (".$category_query.")");
				while ($data = $response->fetch())
				{
					$id = $data['id'];
					$json[$id]['label'] = utf8_encode($data['label']);
					$json[$id]['login'] = utf8_encode($data['login']);
//					$json[$id]['pw'] = teampass_decrypt_pw($data['pw'],SALT,$rand_key);
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
				$response = $bdd->query("select id,label,login,pw,id_tree from ".$GLOBALS['pre']."items where id_tree = (".$category_query.") and label LIKE '".$item."'");
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
		} else {
			rest_error ('METHOD');
		}
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

?>
