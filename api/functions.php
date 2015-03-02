<?php
/**
 *
 * @file          configapi.php
 * @author        Nils Laumaillé
 * @version       2.1.23
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$_SESSION['CPM'] = 1;
require_once "../sources/main.functions.php";

function teampass_api_enabled() {
    teampass_connect();
    $response = DB::queryFirstRow(
        "SELECT `valeur` FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s",
        "admin",
        "api"
    );
    return $response['valeur'];
}

function teampass_whitelist() {
    teampass_connect();
    $apiip_pool = teampass_get_ips();
    if (count($apiip_pool) > 0 && array_search($_SERVER['REMOTE_ADDR'], $apiip_pool) === false) {
        rest_error('IPWHITELIST');
    }
}

function teampass_connect()
{
    global $server, $user, $pass, $database, $link, $port, $encoding;
    require_once("../includes/settings.php");
    require_once('../includes/libraries/Database/Meekrodb/db.class.php');
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = 'db_error_handler';
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);
}

function teampass_get_ips() {
    global $server, $user, $pass, $database, $link;
    $array_of_results = array();
    teampass_connect();
    $response = DB::query("select value from ".prefix_table("api")." WHERE type = %s", "ip");
    foreach ($response as $data)
    {
        array_push($array_of_results, $data['value']);
    }

    return $array_of_results;
}

function teampass_get_keys() {
    global $server, $user, $pass, $database, $link;
    teampass_connect();
    $response = DB::queryOneColumn("value", "select * from ".prefix_table("api")." WHERE type = %s", "key");

    return $response;
}

function teampass_get_randkey() {
    global $server, $user, $pass, $database, $link;
    teampass_connect();
    $response = DB::queryOneColumn("rand_key", "select * from ".prefix_table("keys")." limit 0,1");

    return $response;
}

function rest_head () {
    header('HTTP/1.1 402 Payment Required');
}

function addToCacheTable($id)
{
    global $server, $user, $pass, $database, $link;
    teampass_connect();
    // get data
    $data = DB::queryfirstrow(
        "SELECT i.label AS label, i.description AS description, i.id_tree AS id_tree, i.perso AS perso, i.restricted_to AS restricted_to, i.login AS login, i.id AS id
        FROM ".prefix_table("items")." AS i
        AND ".prefix_table("log_items")." AS l ON (l.id_item = i.id)
        WHERE i.id = %i
        AND l.action = %s",
        intval($id),
        at_creation
    );

    // Get all TAGS
    $tags = "";
    $data_tags = DB::query("SELECT tag FROM ".prefix_table("tags")." WHERE item_id=%i", $id);
    foreach ($data_tags as $itemTag) {
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
    DB::insert(
        prefix_table("cache"),
        array(
            "id" => $data['id'],
            "label" => $data['label'],
            "description" => $data['description'],
            "tags" => $tags,
            "id_tree" => $data['id_tree'],
            "perso" => $data['perso'],
            "restricted_to" => $data['restricted_to'],
            "login" => $data['login'],
            "folder" => "",
            "restricted_to" => "0",
            "author" => "9999999",
        )
    );
}

function rest_delete () {
    if(!@count($GLOBALS['request'])==0){
        $request_uri = $GLOBALS['_SERVER']['REQUEST_URI'];
        preg_match('/\/api(\/index.php|)\/(.*)\?apikey=(.*)/',$request_uri,$matches);
        if (count($matches) == 0) {
            rest_error ('REQUEST_SENT_NOT_UNDERSTANDABLE');
        }
        $GLOBALS['request'] =  explode('/',$matches[2]);
    }
    if(apikey_checker($GLOBALS['apikey'])) {
        global $server, $user, $pass, $database, $pre, $link;
        include "../sources/main.functions.php";
        teampass_connect();
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
                            $category_query .= "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[$slot]."' AND parent_id = 0";
                        } else {
                            $category_query .= "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[$slot]."' AND parent_id = (";
                        }
                    }
                    for ($i = 1; $i < count($array_category); $i++) { $category_query .= ")"; }
                } elseif (count($array_category) == 1) {
                    $category_query = "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[0]."' AND parent_id = 0";
                } else {
                    rest_error ('NO_CATEGORY');
                }

                // Delete items which in category
                $response = DB::delete(prefix_table("items"), "id_tree = (".$category_query.")");
                // Delete sub-categories which in category
                $response = DB::delete(prefix_table("nested_tree"), "parent_id = (".$category_query.")");
                // Delete category
                $response = DB::delete(prefix_table("nested_tree"), "id = (".$category_query.")");

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
                            $category_query .= "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[$slot]."' AND parent_id = 0";
                        } else {
                            $category_query .= "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[$slot]."' AND parent_id = (";
                        }
                    }
                    for ($i = 1; $i < count($array_category); $i++) { $category_query .= ")"; }
                } elseif (count($array_category) == 1) {
                    $category_query = "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[0]."' AND parent_id = 0";
                } else {
                    rest_error ('NO_CATEGORY');
                }

                // Delete item
                $response = DB::delete(prefix_table("items"), "id_tree = (".$category_query.") and label LIKE '".$item."'");
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
    $_SESSION['user_id'] = "'api'";
    if(!@count($GLOBALS['request'])==0){
        $request_uri = $GLOBALS['_SERVER']['REQUEST_URI'];
        preg_match('/\/api(\/index.php|)\/(.*)\?apikey=(.*)/', $request_uri, $matches);
        if (count($matches) == 0) {
            rest_error ('REQUEST_SENT_NOT_UNDERSTANDABLE');
        }
        $GLOBALS['request'] =  explode('/',$matches[2]);
    }
    if(apikey_checker($GLOBALS['apikey'])) {
        global $server, $user, $pass, $database, $pre, $link;
        teampass_connect();
        $rand_key = teampass_get_randkey();
        $category_query = "";

        if ($GLOBALS['request'][0] == "read") {
            if($GLOBALS['request'][1] == "category") {
                // get ids
                if (strpos($GLOBALS['request'][2],",") > 0) {
                    $condition = "id_tree IN %ls";
                    $condition_value = explode(',', $GLOBALS['request'][2]);
                } else {
                    $condition = "id_tree = %s";
                    $condition_value = $GLOBALS['request'][2];
                }
                DB::debugMode(false);
                

                /* load folders */
                $response = DB::query(
                    "SELECT id,parent_id,title,nleft,nright,nlevel FROM ".prefix_table("nested_tree")." WHERE parent_id=%i ORDER BY `title` ASC",
                    $GLOBALS['request'][2]
                );
                $rows = array();
                $i = 0;
                foreach ($response as $row)
                {
                    /*$json['folders'][$i]['id'] = $row['id'];
                    $json['folders'][$i]['parent_id'] = $row['parent_id'];
                    $json['folders'][$i]['title'] = $row['title'];
                    $json['folders'][$i]['nleft'] = $row['nleft'];
                    $json['folders'][$i]['nright'] = $row['nright'];
                    $json['folders'][$i]['nlevel'] = $row['nlevel'];*/
                    $i++;
                    
                    $response = DB::query("SELECT id,label,login,pw FROM ".prefix_table("items")." WHERE id_tree=%i", $row['id']);
                    foreach ($response as $data)
                    {
                        // get ITEM random key
                        $data_tmp = DB::queryFirstRow("SELECT rand_key FROM ".prefix_table("keys")." WHERE id = %i", $data['id']);

                        // prepare output
                        $id = $data['id'];
                        $json[$id]['label'] = utf8_encode($data['label']);
                        $json[$id]['login'] = utf8_encode($data['login']);
                        $json[$id]['pw'] = teampass_decrypt_pw($data['pw'], SALT, $data_tmp['rand_key']);
                    }
                }
            }
            elseif($GLOBALS['request'][1] == "items") {
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
                $response = DB::query("select id,label,login,pw,id_tree from ".prefix_table("items")." where id IN %ls", $items_list);
                foreach ($response as $data)
                {
                    // get ITEM random key
                    $data_tmp = DB::queryFirstRow("SELECT rand_key FROM ".prefix_table("keys")." WHERE id = %i", $data['id']);

                    // prepare output
                    $id = $data['id'];
                    $json[$id]['label'] = utf8_encode($data['label']);
                    $json[$id]['login'] = utf8_encode($data['login']);
                    $json[$id]['pw'] = teampass_decrypt_pw($data['pw'], SALT, $data_tmp['rand_key']);
                }
            }

            if (isset($json) && $json) {
                echo json_encode($json);
            } else {
                rest_error ('EMPTY');
            }
        } elseif ($GLOBALS['request'][0] == "find") {
            if($GLOBALS['request'][1] == "item") {
                $array_category = explode(';', $GLOBALS['request'][2]);
                $item = $GLOBALS['request'][3];
                foreach($array_category as $category) {
                    if(!preg_match_all("/^([\w\:\'\-\sàáâãäåçèéêëìíîïðòóôõöùúûüýÿ]+)$/i", $category,$result)) {
                        rest_error('CATEGORY_MALFORMED');
                    }
                }

                if(!preg_match_all("/^([\w\:\'\-\sàáâãäåçèéêëìíîïðòóôõöùúûüýÿ]+)$/i", $item, $result)) {
                    rest_error('ITEM_MALFORMED');
                } elseif (empty($item) || count($array_category) == 0) {
                    rest_error('MALFORMED');
                }

                if(count($array_category) > 1 && count($array_category) < 5) {
                    for ($i = count($array_category); $i > 0; $i--) {
                        $slot = $i - 1;
                        if (!$slot) {
                            $category_query .= "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[$slot]."' AND parent_id = 0";
                        } else {
                            $category_query .= "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[$slot]."' AND parent_id = (";
                        }
                    }
                    for ($i = 1; $i < count($array_category); $i++) { $category_query .= ")"; }
                } elseif (count($array_category) == 1) {
                    $category_query = "select id from ".prefix_table("nested_tree")." where title LIKE '".$array_category[0]."' AND parent_id = 0";
                } else {
                    rest_error ('NO_CATEGORY');
                }

                DB::debugMode(false);
                $response = DB::query(
                    "select id,label,login,pw,id_tree
                    from ".prefix_table("items")."
                    where id_tree = (%s)
                    and label LIKE %ss",
                    $category_query,
                    $item
                );
                foreach ($response as $data)
                {
                    // get ITEM random key
                    $data_tmp = DB::queryFirstRow("SELECT rand_key FROM ".prefix_table("keys")." WHERE id = %i", $data['id']);

                    // prepare output
                    $json['id'] = utf8_encode($data['id']);
                    $json['label'] = utf8_encode($data['label']);
                    $json['login'] = utf8_encode($data['login']);
                    $json['pw'] = teampass_decrypt_pw($data['pw'], SALT, $data_tmp['rand_key']);
                    $json['folder_id'] = $data['id_tree'];
                    $json['status'] = utf8_encode("OK");
                }
                if (isset($json) && $json) {
                    echo json_encode($json);
                } else {
                    rest_error ('EMPTY');
                }
            }
        } elseif ($GLOBALS['request'][0] == "add") {
            if($GLOBALS['request'][1] == "item") {

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

                // added so one can sent data including the http or https !
                // anyway we have to urlencode this data
                $item_url = urldecode($item_url);
                // same for the email
                $item_email = urldecode($item_email);

                // do some checks
                if (!empty($item_label) && !empty($item_pwd) && !empty($item_folder_id)) {
                    // Check length
                    if (strlen($item_pwd) > 50) {
                        rest_error ('BADDEFINITION');
                    }

                    // Check Folder ID
                    DB::query("SELECT * FROM ".prefix_table("nested_tree")." WHERE id = %i", $item_folder_id);
                    $counter = DB::count();
                    if ($counter == 0) {
                        rest_error ('BADDEFINITION');
                    }

                    // check if element doesn't already exist
                    DB::query("SELECT * FROM ".prefix_table("items")." WHERE label = %s AND inactif = %i", addslashes($item_label), "0");
                    $counter = DB::count();
                    if ($counter != 0) {
                        $itemExists = 1;
                        // prevent the error if the label already exists
                        // so lets just add the time() as a random factor
                        $item_label .= " (" . time() .")";
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
                            DB::insert(
                                prefix_table("items"),
                                array(
                                    "label" => $item_label,
                                    "description" => $item_desc,
                                    "pw" => $item_pwd,
                                    "email" => $item_email,
                                    "url" => $item_url,
                                    "id_tree" => intval($item_folder_id),
                                    "login" => $item_login,
                                    "inactif" => 0,
                                    "restricted_to" => "",
                                    "perso" => 0,
                                    "anyone_can_modify" => intval($item_anyonecanmodify)
                                )
                            );
                            $newID = DB::InsertId();

                            // Store generated key
                            DB::insert(
                                prefix_table("keys"),
                                array(
                                    "sql_table" => "items",
                                    "id" => $newID,
                                    "rand_key" => $randomKey
                                )
                            );

                            // log
                            DB::insert(
                                prefix_table("log_items"),
                                array(
                                    "id_item" => $newID,
                                    "date" => time(),
                                    "id_user" => "9999999",
                                    "action" => "at_creation"
                                )
                            );

                            // Add tags
                            $tags = explode(' ', $item_tags);
                            foreach ((array)$tags as $tag) {
                                if (!empty($tag)) {
                                    DB::insert(
                                        prefix_table("tags"),
                                        array(
                                            "item_id" => $newID,
                                            "tag" => strtolower($tag)
                                        )
                                    );
                                }
                            }

                            // Update CACHE table
                            DB::insert(
                                prefix_table("cache"),
                                array(
                                    "id" => $newID,
                                    "label" => $item_label,
                                    "description" => $item_desc,
                                    "tags" => $item_tags,
                                    "id_tree" => $item_folder_id,
                                    "perso" => "0",
                                    "restricted_to" => "",
                                    "login" => $item_login,
                                    "folder" => "",
                                    "author" => "9999999"
                                )
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
        } elseif ($GLOBALS['request'][0] == "auth") {
            /*
            ** FOR SECURITY PURPOSE, it is mandatory to use SSL to connect your teampass instance. The user password is not encrypted!
            **
            **
            ** Expected call format: .../api/index.php/auth/<PROTOCOL>/<URL>/<login>/<password>?apikey=<VALID API KEY>
            ** Example: https://127.0.0.1/teampass/api/index.php/auth/http/www.zadig-tge.adp.com/U1/test/76?apikey=chahthait5Aidood6johh6Avufieb6ohpaixain
            ** RESTRICTIONS:
            **              - <PROTOCOL>        ==> http|https|ftp|...
            **              - <URL>             ==> encode URL without protocol (example: http://www.teampass.net becomes www.teampass.net)
            **              - <login>           ==> user's login
            **              - <password>        ==> currently clear password
            **
            ** RETURNED ANSWER:
            **              - format sent back is JSON
            **              - Example: {"<item_id>":{"label":"<pass#1>","login":"<login#1>","pw":"<pwd#1>"},"<item_id>":{"label":"<pass#2>","login":"<login#2>","pw":"<pwd#2>"}}
            **
            */
            // get user credentials
            if(isset($GLOBALS['request'][3]) && isset($GLOBALS['request'][4])) {
                // get url
                if(isset($GLOBALS['request'][1]) && isset($GLOBALS['request'][2])) {
                    // is user granted?
                    $user = DB::queryFirstRow(
                        "SELECT `id`, `pw`, `groupes_interdits`, `groupes_visibles`, `fonction_id` FROM ".$pre."users WHERE login = %s",
                        $GLOBALS['request'][3]
                    );
                    if (crypt($GLOBALS['request'][4], $user['pw']) == $user['pw']) {
                        // define the restriction of "id_tree" of this user
                        $userDef = DB::queryOneColumn('folder_id',
                            "SELECT DISTINCT folder_id 
                            FROM ".prefix_table("roles_values")."
                            WHERE type IN ('R', 'W') ", empty($user['groupes_interdits']) ? "" : "
                            AND folder_id NOT IN (".str_replace(";", ",", $user['groupes_interdits']).")", " 
                            AND role_id IN %ls 
                            GROUP BY folder_id",
                            explode(";", $user['groupes_interdits'])
                        );
                        // complete with "groupes_visibles"
                        foreach (explode(";", $user['groupes_visibles']) as $v) {
                            array_push($userDef, $v);
                        }
                        
                        // find the item associated to the url
                        $response = DB::query(
                            "SELECT id, label, login, pw, id_tree, restricted_to
                            FROM ".prefix_table("items")." 
                            WHERE url LIKE %s
                            AND id_tree IN (".implode(",", $userDef).")
                            ORDER BY id DESC",
                            $GLOBALS['request'][1]."://".urldecode($GLOBALS['request'][2].'%')
                        );                        
                        $counter = DB::count();
                        
                        if ($counter > 0) {
                            $json = "";
                            foreach ($response as $data) {
                                // check if item visible
                                if (
                                    empty($data['restricted_to']) || 
                                    ($data['restricted_to'] != "" && in_array($user['id'], explode(";", $data['restricted_to'])))
                                ) {                                
                                    // get ITEM random key
                                    $data_tmp = DB::queryFirstRow("SELECT rand_key FROM ".prefix_table("keys")." WHERE id = %i", $data['id']);
                                    
                                    // prepare export
                                    $json[$data['id']]['label'] = utf8_encode($data['label']);
                                    $json[$data['id']]['login'] = utf8_encode($data['login']);
                                    $json[$data['id']]['pw'] = teampass_decrypt_pw($data['pw'], SALT, $data_tmp['rand_key']);
                                }
                            }
                            // prepare answer. If no access then inform
                            if (empty($json)) {
                                rest_error ('AUTH_NO_DATA');
                            } else {
                                echo json_encode($json);
                            }
                        } else {
                            rest_error ('AUTH_NO_DATA');
                        }
                    } else {
                        rest_error ('AUTH_NOT_GRANTED');
                    }
                } else {
                    rest_error ('AUTH_NO_URL');
                }
            } else {
                rest_error ('AUTH_NO_IDENTIFIER');
            }
        } else {
            rest_error ('METHOD');
        }
    }
}

function rest_put() {
    if(!@count($GLOBALS['request'])==0){
        $request_uri = $GLOBALS['_SERVER']['REQUEST_URI'];
        preg_match('/\/api(\/index.php|)\/(.*)\?apikey=(.*)/',$request_uri,$matches);
        if (count($matches) == 0) {
            rest_error ('REQUEST_SENT_NOT_UNDERSTANDABLE');
        }
        $GLOBALS['request'] =  explode('/',$matches[2]);
    }
    if(apikey_checker($GLOBALS['apikey'])) {
        global $server, $user, $pass, $database, $pre, $link;
        teampass_connect();
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
        case 'REQUEST_SENT_NOT_UNDERSTANDABLE':
            $message = Array('err' => 'URL format is not following requirements');
            break;
        case 'AUTH_NOT_GRANTED':
            $message = Array('err' => 'Bad credentials for user');
            break;
        case 'AUTH_NO_URL':
            $message = Array('err' => 'URL needed to grant access');
            break;
        case 'AUTH_NO_IDENTIFIER':
            $message = Array('err' => 'Credentials needed to grant access');
            break;
        case 'AUTH_NO_DATA':
            $message = Array('err' => 'Data not allowed for the user');
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
    teampass_connect();
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
    require_once '../includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    $encrypted = base64_decode($encrypted);
    $pass_salt = substr($encrypted, -64);
    $encrypted = substr($encrypted, 0, -64);
    //$key       = teampass_pbkdf2_hash($salt, $pass_salt, $itcount, 16, 32);
    $key = substr(pbkdf2('sha256', $salt, $pass_salt, $itcount, 16+32, true), 32, 16);
    $iv        = base64_decode(substr($encrypted, 0, 43) . '==');
    $encrypted = substr($encrypted, 43);
    $mac       = substr($encrypted, -64);
    $encrypted = substr($encrypted, 0, -64);
    if ($mac !== hash_hmac('sha256', $encrypted, $salt)) return null;
    //return substr(rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $encrypted, 'ctr', $iv), "\0\4"), strlen($rand_key));
    $result = substr(rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $encrypted, 'ctr', $iv), "\0\4"), strlen($rand_key));
    if ($result) {
        return $result;
    } else {
        return "";
    }
}
