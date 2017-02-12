<?php
/**
 *
 * @file          (api)functions.php
 * @author        Nils Laumaillé
 * @version       2.0
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$api_version = "2.0";
$_SESSION['CPM'] = 1;
require_once "../includes/config/include.php";
require_once "../sources/main.functions.php";

function get_ip() {
    if ( function_exists( 'apache_request_headers' ) ) {
        $headers = apache_request_headers();
    } else {
        $headers = $_SERVER;
    }
    if ( array_key_exists( 'X-Forwarded-For', $headers ) && filter_var( $headers['X-Forwarded-For'], FILTER_VALIDATE_IP ) ) {
        $the_ip = $headers['X-Forwarded-For'];
    } elseif ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ) && filter_var( $headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP ) ) {
        $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
    } else {
        $the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP );
    }
    return $the_ip;
}


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
    if (count($apiip_pool) > 0 && array_search(get_ip(), $apiip_pool) === false) {
        rest_error('IPWHITELIST');
    }
}

function teampass_connect()
{
    global $server, $user, $pass, $database, $link, $port, $encoding;
    require_once("../includes/config/settings.php");
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
            //"restricted_to" => "0",
            "author" => API_USER_ID,
            "renewal_period" => 0,
            "timestamp" => time(),
            "url" => 0
        )
    );
}


function getSettingValue($setting) {

    // get default language
    $set = DB::queryFirstRow(
        "SELECT `valeur` FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s",
        "admin",
        $setting
    );

    return $set['valeur'];
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
    global $api_version;

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
        $category_query = "";

        // define the API user through the LABEL of apikey
        $api_info = DB::queryFirstRow(
            "SELECT label
            FROM ".prefix_table("api")."
            WHERE value = %s",
            $GLOBALS['apikey']
        );

        if ($GLOBALS['request'][0] == "read") {
            if($GLOBALS['request'][1] == "folder") {
                /*
                * READ FOLDERS
                */

                // load library
                require_once '../sources/SplClassLoader.php';
                //Load Tree
                $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                $tree->register();
                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

                // get ids
                if (strpos($GLOBALS['request'][2],";") > 0) {
                    $condition = "id_tree IN %ls";
                    $condition_value = explode(';', $GLOBALS['request'][2]);
                } else {
                    $condition = "id_tree = %s";
                    $condition_value = $GLOBALS['request'][2];
                }

                // get items in this folder
                $response = DB::query(
                    "SELECT id, label, login, pw, pw_iv, url, id_tree, description, email
                    FROM ".prefix_table("items")."
                    WHERE inactif='0' AND ".$condition, $condition_value
                );
                $x = 0;
                foreach ($response as $data)
                {
                    // build the path to the Item
                    $path = "";
                    $arbo = $tree->getPath($data['id_tree'], true);
                    foreach ($arbo as $elem) {
                        if (empty($path)) {
                            $path = stripslashes($elem->title);
                        } else {
                            $path .= " > ".stripslashes($elem->title);
                        }
                    }

                    // prepare output
                    $json[$x]['id'] = $data['id'];
                    $json[$x]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                    $json[$x]['description'] = mb_convert_encoding($data['description'], mb_detect_encoding($data['description']), 'UTF-8');
                    $json[$x]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                    $json[$x]['email'] = mb_convert_encoding($data['email'], mb_detect_encoding($data['email']), 'UTF-8');
                    $json[$x]['url'] = mb_convert_encoding($data['url'], mb_detect_encoding($data['url']), 'UTF-8');
                    $crypt_pw = cryption(
                        $data['pw'],
                        "",
                        "decrypt"
                    );
                    $json[$x]['pw'] = $crypt_pw['string'];
                    $json[$x]['folder_id'] = $data['id_tree'];
                    $json[$x]['path'] = $path;

                    $x++;
                }
            }
            else if($GLOBALS['request'][1] == "userpw") {
                /*
                * READ USER ITEMS
                */

                // load library
                require_once '../sources/SplClassLoader.php';
                //Load Tree
                $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                $tree->register();
                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

                // about the user
                $username = $GLOBALS['request'][2];
                if(strcmp($username,"admin")==0) {
                    // forbid admin access
                }
                $response = DB::query("SELECT fonction_id FROM ".prefix_table("users")." WHERE login='".$username."'");
                if (count($response) === 0) {
                    rest_error('USER_NOT_EXISTS');
                }
                foreach ($response as $data)
                {
                    $role_str = $data['fonction_id'];
                }
                $folder_arr = array();
                $roles = explode(";", $role_str);
                foreach ($roles as $role)
                {
                    $response = DB::query("SELECT folder_id FROM ".prefix_table("roles_values")." WHERE role_id='".$role."'");
                    foreach ($response as $data)
                    {
                        $folder_id = $data['folder_id'];
                        if(!array_key_exists($folder_id,$folder_arr)) {
                            array_push($folder_arr,$folder_id);
                        }
                    }
                }
                $folder_str = array_filter(implode(";",$folder_arr));

                // get ids
                if (strpos($folder_str,";") > 0) {
                    $condition = "id_tree IN %ls";
                    $condition_value = explode(';', $folder_str);
                } else {
                    $condition = "id_tree = %s";
                    $condition_value = $folder_str;
                }

                $data = "";
                // get items in this module
                $response = DB::query(
                    "SELECT id,label,url,login,pw, pw_iv, url, id_tree, description, email
                    FROM ".prefix_table("items")."
                    WHERE inactif='0' AND ".$condition, $condition_value
                );
                $x = 0;
                foreach ($response as $data)
                {
                    // build the path to the Item
                    $path = "";
                    $arbo = $tree->getPath($data['id_tree'], true);
                    foreach ($arbo as $elem) {
                        if (empty($path)) {
                            $path = stripslashes($elem->title);
                        } else {
                            $path .= " > ".stripslashes($elem->title);
                        }
                    }

                    // prepare output
                    $json[$data['id']]['id'] = $data['id'];
                    $json[$data['id']]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                    $json[$data['id']]['description'] = mb_convert_encoding($data['description'], mb_detect_encoding($data['description']), 'UTF-8');
                    $json[$data['id']]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                    $json[$data['id']]['email'] = mb_convert_encoding($data['email'], mb_detect_encoding($data['email']), 'UTF-8');
                    $json[$data['id']]['url'] = mb_convert_encoding($data['url'], mb_detect_encoding($data['url']), 'UTF-8');
                    $crypt_pw = cryption($data['pw'], "", "decrypt");
                    $json[$data['id']]['pw'] = $crypt_pw['string'];
                    $json[$data['id']]['folder_id'] = $data['id_tree'];
                    $json[$data['id']]['path'] = $path;

                    $x++;
                }
            }
            else if($GLOBALS['request'][1] == "userfolders") {
                /*
                * READ USER FOLDERS
                * Sends back a list of folders
                */
                $json = "";
                $username = $GLOBALS['request'][2];
                if(strcmp($username,"admin")==0) {
                    // forbid admin access
                }
                $response = DB::query("SELECT fonction_id FROM ".prefix_table("users")." WHERE login='".$username."'");
                if (count($response) === 0) {
                    rest_error('USER_NOT_EXISTS');
                }
                foreach ($response as $data)
                {
                    $role_str = $data['fonction_id'];
                }

                $folder_arr = array();
                $roles = explode(";", $role_str);
                $x = 0;
                foreach ($roles as $role)
                {
                    $response = DB::query("SELECT folder_id, type FROM ".prefix_table("roles_values")." WHERE role_id='".$role."'");
                    foreach ($response as $data)
                    {
                        $folder_id = $data['folder_id'];
                        if(!array_key_exists($folder_id,$folder_arr)) {
                            array_push($folder_arr,$folder_id);

                            $response2 = DB::queryFirstRow("SELECT title, nlevel FROM ".prefix_table("nested_tree")." WHERE id='".$folder_id."'");

                            if (!empty($response2['title'])) {
                                $json[$folder_id]['id'] = $folder_id;
                                $json[$folder_id]['title'] = $response2['title'];
                                $json[$folder_id]['level'] = $response2['nlevel'];
                                $json[$folder_id]['access_type'] = $data['type'];
                                $x++;
                            }
                        }
                    }
                }
            }
            elseif($GLOBALS['request'][1] == "items") {
                /*
                * READ ITEMS asked
                */

                // load library
                require_once '../sources/SplClassLoader.php';
                //Load Tree
                $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                $tree->register();
                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

                // get parameters
                $array_items = explode(';',$GLOBALS['request'][2]);

                // check if not empty
                if (count($array_items) == 0) {
                    rest_error ('NO_ITEM');
                }

                // only accepts numeric
                foreach($array_items as $item) {
                    if(!is_numeric($item)) {
                        rest_error('ITEM_MALFORMED');
                    }
                }

                $response = DB::query(
                    "SELECT id,label,login,pw, pw_iv, url, id_tree, description, email
                    FROM ".prefix_table("items")."
                    WHERE inactif = %i AND id IN %ls", "0", $array_items
                );
                $x = 0;
                foreach ($response as $data)
                {
                    // build the path to the Item
                    $path = "";
                    $arbo = $tree->getPath($data['id_tree'], true);
                    foreach ($arbo as $elem) {
                        if (empty($path)) {
                            $path = stripslashes($elem->title);
                        } else {
                            $path .= " > ".stripslashes($elem->title);
                        }
                    }

                    // prepare output
                    $json[$x]['id'] = $data['id'];
                    $json[$x]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                    $json[$x]['description'] = mb_convert_encoding($data['description'], mb_detect_encoding($data['description']), 'UTF-8');
                    $json[$x]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                    $json[$x]['email'] = mb_convert_encoding($data['email'], mb_detect_encoding($data['email']), 'UTF-8');
                    $json[$x]['url'] = mb_convert_encoding($data['url'], mb_detect_encoding($data['url']), 'UTF-8');
                    $crypt_pw = cryption($data['pw'], "", "decrypt");
                    $json[$x]['pw'] = $crypt_pw['string'];
                    $json[$x]['folder_id'] = $data['id_tree'];
                    $json[$x]['path'] = $path;

                    $x++;
                }
            }

            if (isset($json) && $json) {
                echo json_encode($json);
            } else {
                rest_error ('EMPTY');
            }
        } elseif ($GLOBALS['request'][0] == "find") {
            if($GLOBALS['request'][1] == "item") {
                /*
                * FIND ITEMS in FOLDERS
                */

                // load library
                require_once '../sources/SplClassLoader.php';
                //Load Tree
                $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                $tree->register();
                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

                // get parameters
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

                if(count($array_category) === 0) {
                    rest_error ('NO_CATEGORY');
                }

                DB::debugMode(false);
                $response = DB::query(
                    "SELECT id, label, login, pw, pw_iv, url, id_tree, description, email
                    FROM ".prefix_table("items")."
                    WHERE
                    inactif = %i
                    AND id_tree IN %ls
                    AND label LIKE %ss",
                    "0",
                    $array_category,
                    $item
                );
                $x = 0;
                foreach ($response as $data) {
                    // build the path to the Item
                    $path = "";
                    $arbo = $tree->getPath($data['id_tree'], true);
                    foreach ($arbo as $elem) {
                        if (empty($path)) {
                            $path = stripslashes($elem->title);
                        } else {
                            $path .= " > ".stripslashes($elem->title);
                        }
                    }

                    // prepare output
                    $json[$x]['id'] = mb_convert_encoding($data['id'], mb_detect_encoding($data['id']), 'UTF-8');
                    $json[$x]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                    $json[$x]['description'] = mb_convert_encoding($data['description'], mb_detect_encoding($data['description']), 'UTF-8');
                    $json[$x]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                    $json[$x]['email'] = mb_convert_encoding($data['email'], mb_detect_encoding($data['email']), 'UTF-8');
                    $json[$x]['url'] = mb_convert_encoding($data['url'], mb_detect_encoding($data['url']), 'UTF-8');
                    $crypt_pw = cryption($data['pw'], "", "decrypt");
                    $json[$x]['pw'] = $crypt_pw['string'];
                    $json[$x]['folder_id'] = $data['id_tree'];
                    $json[$x]['path'] = $path;
                    $json[$x]['status'] = utf8_encode("OK");

                    $x++;
                }
                if (isset($json) && $json) {
                    echo json_encode($json);
                } else {
                    rest_error ('EMPTY');
                }
            }
        } elseif ($GLOBALS['request'][0] == "add") {
            if($GLOBALS['request'][1] == "item") {
                // get sent parameters
                $params = explode(';', base64_decode($GLOBALS['request'][2]));
                if (count($params) != 9) {
                    rest_error ('ITEMBADDEFINITION');
                }

                $item_label = $params[0];
                $item_pwd = $params[1];
                $item_desc = $params[2];
                $item_folder_id = $params[3];
                $item_login = $params[4];
                $item_email = $params[5];
                $item_url = $params[6];
                $item_tags = $params[7];
                $item_anyonecanmodify = $params[8];

                // do some checks
                if (!empty($item_label) && !empty($item_pwd) && !empty($item_folder_id)) {
                    // Check length
                    if (strlen($item_pwd) > 50) {
                        rest_error ('PASSWORDTOOLONG');
                    }

                    // Check Folder ID
                    DB::query("SELECT * FROM ".prefix_table("nested_tree")." WHERE id = %i", $item_folder_id);
                    $counter = DB::count();
                    if ($counter == 0) {
                        rest_error ('NOSUCHFOLDER');
                    }

                    // check if element doesn't already exist
                    $item_duplicate_allowed = getSettingValue("duplicate_item");
                    if ($item_duplicate_allowed !== "1") {
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
                    } else {
                        $itemExists = 0;
                    }
                    if ($itemExists === 0) {
                        $encrypt = cryption(
                            $item_pwd,
                            "",
                            "encrypt"
                        );
                        if (empty($encrypt['string'])) {
                            rest_error ('PASSWORDEMPTY');
                        }

                        // ADD item
                        try {
                            DB::insert(
                                prefix_table("items"),
                                array(
                                    "label" => $item_label,
                                    "description" => $item_desc,
                                    'pw' => $encrypt['string'],
                                    'pw_iv' => '',
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

                            // log
                            DB::insert(
                                prefix_table("log_items"),
                                array(
                                    "id_item" => $newID,
                                    "date" => time(),
                                    "id_user" => API_USER_ID,
                                    "action" => "at_creation",
                                    "raison" => $api_info['label']
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
                                    "author" => API_USER_ID,
                                    "renewal_period" => "0",
                                    "timestamp" => time(),
                                    "url" => "0"
                                )
                            );

                            echo '{"status":"item added" , "new_item_id" : "'.$newID.'"}';
                        } catch(PDOException $ex) {
                            echo '<br />' . $ex->getMessage();
                        }
                    } else {
                        rest_error ('ITEMEXISTS');
                    }
                } else {
                    rest_error ('ITEMMISSINGDATA');
                }
            }
            /*
             * Case where a new user has to be added
             *
             * Expected call format: .../api/index.php/add/user/<LOGIN>;<NAME>;<LASTNAME>;<PASSWORD>;<EMAIL>;<ADMINISTRATEDBY>;<READ_ONLY>;<ROLE1,ROLE2,...>;<IS_ADMIN>;<ISMANAGER>;<PERSONAL_FOLDER>?apikey=<VALID API KEY>
             * with:
             * for READ_ONLY, IS_ADMIN, IS_MANAGER, PERSONAL_FOLDER, accepted value is 1 for TRUE and 0 for FALSE
             * for ADMINISTRATEDBY and ROLE1, accepted value is the real label (not the IDs)
             *
             * Example: /api/index.php/add/user/U4;Nils;Laumaille;test;nils@laumaille.fr;Users;0;Managers,Users;0;1;1?apikey=sae6iekahxiseL3viShoo0chahc1ievei8aequi
             *
             */
            elseif($GLOBALS['request'][1] == "user") {

                // get user definition
                $array_user = explode(';', base64_decode($GLOBALS['request'][2]));
                if (count($array_user) != 11) {
                    rest_error ('USERBADDEFINITION');
                }

                $login = $array_user[0];
                $name = $array_user[1];
                $lastname = $array_user[2];
                $password = $array_user[3];
                $email = $array_user[4];
                $adminby = urldecode($array_user[5]);
                $isreadonly = urldecode($array_user[6]);
                $roles = urldecode($array_user[7]);
                $isadmin = $array_user[8];
                $ismanager = $array_user[9];
                $haspf = $array_user[10];

                // Empty user
                if (mysqli_escape_string($link, htmlspecialchars_decode($login)) == "") {
                    rest_error ('USERLOGINEMPTY');
                }
                // Check if user already exists
                $data = DB::query(
                    "SELECT id, fonction_id, groupes_interdits, groupes_visibles FROM ".prefix_table("users")."
            WHERE login LIKE %ss",
                    mysqli_escape_string($link, stripslashes($login))
                );

                if (DB::count() == 0) {
                    try {
                        // find AdminRole code in DB
                        $resRole = DB::queryFirstRow(
                            "SELECT id
                            FROM ".prefix_table("roles_title")."
                            WHERE title LIKE %ss",
                            mysqli_escape_string($link, stripslashes($adminby))
                        );

                        // get default language
                        $lang = DB::queryFirstRow(
                            "SELECT `valeur` FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s",
                            "admin",
                            "default_language"
                        );

                        // prepare roles list
                        $rolesList = "";
                        foreach (explode(',', $roles) as $role) {//echo $role."-";
                            $tmp = DB::queryFirstRow(
                                "SELECT `id` FROM ".prefix_table("roles_title")." WHERE title = %s",
                                $role
                            );
                            if (empty($rolesList)) $rolesList = $tmp['id'];
                            else $rolesList .= ";" . $tmp['id'];
                        }

                        // Add user in DB
                        DB::insert(
                            prefix_table("users"),
                            array(
                                'login' => $login,
                                'name' => $name,
                                'lastname' => $lastname,
                                'pw' => bCrypt(stringUtf8Decode($password), COST),
                                'email' => $email,
                                'admin' => intval($isadmin),
                                'gestionnaire' => intval($ismanager),
                                'read_only' => intval($isreadonly),
                                'personal_folder' => intval($haspf),
                                'user_language' => $lang['valeur'],
                                'fonction_id' => $rolesList,
                                'groupes_interdits' => '0',
                                'groupes_visibles' => '0',
                                'isAdministratedByRole' => empty($resRole) ? '0' : $resRole['id']
                            )
                        );
                        $new_user_id = DB::insertId();
                        // Create personnal folder
                        if (intval($haspf) == 1) {
                            DB::insert(
                                prefix_table("nested_tree"),
                                array(
                                    'parent_id' => '0',
                                    'title' => $new_user_id,
                                    'bloquer_creation' => '0',
                                    'bloquer_modification' => '0',
                                    'personal_folder' => '1'
                                )
                            );
                        }

                        // load settings
                        loadSettings();

                        // Send email to new user
                        @sendEmail(
                            $LANG['email_subject_new_user'],
                            str_replace(
                                array('#tp_login#', '#tp_pw#', '#tp_link#'),
                                array(" ".addslashes($login), addslashes($password), $_SESSION['settings']['email_server_url']), $LANG['email_new_user_mail']),
                            $email,
                            ""
                        );

                        // update LOG
                        logEvents('user_mngt', 'at_user_added', 'api - '.$GLOBALS['apikey'], $new_user_id, "");

                        echo '{"status":"user added"}';
                    } catch(PDOException $ex) {
                        echo '<br />' . $ex->getMessage();
                    }
                } else {
                    rest_error ('USERALREADYEXISTS');
                }
            }
            /*
            * ADDING A FOLDER
            * <url to teampass>/api/index.php/add/folder/<title>;<complexity_level>;<parent_id>;<renewal_period>;<personal>?apikey=<valid api key>
            */
            elseif($GLOBALS['request'][1] == "folder") {
                if (!empty($GLOBALS['request'][2])) {
                    // get sent parameters
                    $params = explode(';', base64_decode($GLOBALS['request'][2]));

                    if (!empty($params[0]) && !empty($params[1])) {
                        if (empty($params[3])) $params[3] = 0;
                        if (empty($params[4])) $params[4] = 0;
                        if ($params[2] < 0) {
                            rest_error ('NO_DATA_EXIST');
                        }

                        //Check if title doesn't contains html codes
                        if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $params[0], $out)) {
                            rest_error ('HTML_CODES_NOT_ALLOWED');
                        }

                        // check if title is numeric
                        if (is_numeric($params[0]) === true) {
                            rest_error ('TITLE_ONLY_WITH_NUMBERS');
                        }

                        //Check if duplicate folders name are allowed
                        $data = DB::queryfirstrow(
                            "SELECT valeur
                            FROM ".prefix_table("misc")."
                            WHERE type = %s AND intitule = %s",
                            "admin",
                            "duplicate_folder"
                        );
                        // if valeur = 0 then duplicate folders not allowed
                        if ($data === 0) {
                            DB::query("SELECT * FROM ".prefix_table("nested_tree")." WHERE title = %s", $params[0]);
                            $counter = DB::count();
                            if ($counter != 0) {
                                rest_error ('ALREADY_EXISTS');
                            }
                        }

                        //check if parent folder is personal
                        $data = DB::queryfirstrow(
                            "SELECT personal_folder
                            FROM ".prefix_table("nested_tree")."
                            WHERE id = %i",
                            $params[2]
                        );
                        if ($data['personal_folder'] === "1") {
                            $isPersonal = 1;
                        } else {
                            if ($params[4] === 1) {
                                $isPersonal = 1;
                            } else {
                                $isPersonal = 0;
                            }

                            // get complexity level for this folder
                            $data = DB::queryfirstrow(
                                "SELECT valeur
                                FROM ".prefix_table("misc")."
                                WHERE intitule = %i AND type = %s",
                                $params[2],
                                "complex"
                            );
                            if (intval($params[1]) < intval($data['valeur'])) {
                                rest_error ('COMPLEXICITY_LEVEL_NOT_REACHED');
                            }
                        }

                        try {
                            //create folder
                            DB::insert(
                                prefix_table("nested_tree"),
                                array(
                                    'parent_id' => $params[2],
                                    'title' => $params[0],
                                    'personal_folder' => $isPersonal,
                                    'renewal_period' => $params[3],
                                    'bloquer_creation' => '0',
                                    'bloquer_modification' => '0'
                               )
                            );
                            $newId = DB::insertId();

                            //Add complexity
                            DB::insert(
                                prefix_table("misc"),
                                array(
                                    'type' => 'complex',
                                    'intitule' => $newId,
                                    'valeur' => $params[1]
                                )
                            );

                            echo '{"status":"folder created" , "new_folder_id":"'.$newId.'"}';
                        } catch(PDOException $ex) {
                            echo '<br />' . $ex->getMessage();
                        }
                    } else {
                        rest_error ('NO_DATA_EXIST');
                    }
                } else {
                    rest_error('SET_NO_DATA');
                }
            }
        } elseif ($GLOBALS['request'][0] == "update") {
            /*
            * Section dedicated for UPDATING
            */
            if ($GLOBALS['request'][1] == "item") {
                /*
                * Expected call format: .../api/index.php/update/item/<item_id>/<label>;<password>;<description>;<folder_id>;<login>;<email>;<url>;<tags>;<any one can modify>?apikey=<VALID API KEY>
                */
                if ($GLOBALS['request'][2] !== "" && is_numeric($GLOBALS['request'][2])) {
                    // get sent parameters
                    $params = explode(';', base64_decode($GLOBALS['request'][3]));

                    if (!empty($params[0]) && !empty($params[1]) && !empty($params[3])) {
                        // Check length
                        if (strlen($params[1]) > 50) {
                            rest_error ('PASSWORDTOOLONG');
                        }

                        // Check Folder ID
                        DB::query("SELECT * FROM ".prefix_table("nested_tree")." WHERE id = %i", $params[3]);
                        $counter = DB::count();
                        if ($counter == 0) {
                            rest_error ('NOSUCHFOLDER');
                        }

                        // check if item exists
                        DB::query(
                            "SELECT * FROM ".prefix_table("items")." WHERE id = %i",
                            $GLOBALS['request'][2]
                        );
                        $counter = DB::count();
                        if ($counter > 0) {
                            // encrypt pwd
                            $encrypt = cryption(
                                $params[1],
                                "",
                                "encrypt"
                            );
                            if (empty($encrypt['string'])) {
                                rest_error ('PASSWORDEMPTY');
                            }

                            // ADD item
                            try {
                                DB::update(
                                    prefix_table("items"),
                                    array(
                                        "label" => $params[0],
                                        "description" => $params[2],
                                        'pw' => $encrypt['string'],
                                        'pw_iv' => '',
                                        "email" => $params[5],
                                        "url" => $params[6],
                                        "id_tree" => intval($params[3]),
                                        "login" => $params[4],
                                        "anyone_can_modify" => intval($params[8])
                                    ),
                                    "id = %i",
                                    $GLOBALS['request'][2]
                                );

                                // log
                                DB::insert(
                                    prefix_table("log_items"),
                                    array(
                                        "id_item" => $GLOBALS['request'][2],
                                        "date" => time(),
                                        "id_user" => API_USER_ID,
                                        "action" => "at_modification"
                                    )
                                );

                                // Add tags
                                $tags = explode(' ', $params[7]);
                                foreach ((array)$tags as $tag) {
                                    if (!empty($tag)) {
                                        // check if already exists
                                        DB::query(
                                            "SELECT * FROM ".prefix_table("tags")." WHERE tag = %s AND item_id = %i",
                                            strtolower($tag),
                                            $GLOBALS['request'][2]
                                        );
                                        $counter = DB::count();
                                        if ($counter === 0) {
                                            DB::insert(
                                                prefix_table("tags"),
                                                array(
                                                    "item_id" => $GLOBALS['request'][2],
                                                    "tag" => strtolower($tag)
                                                )
                                            );
                                        }
                                    }
                                }

                                // Update CACHE table
                                DB::update(
                                    prefix_table("cache"),
                                    array(
                                        "label" => $params[0],
                                        "description" => $params[2],
                                        "tags" => $params[7],
                                        "id_tree" => intval($params[3]),
                                        "perso" => "0",
                                        "restricted_to" => "",
                                        "login" => $params[4],
                                        "folder" => "",
                                        "author" => API_USER_ID,
                                        "renewal_period" => "0",
                                        "timestamp" => time(),
                                        "url" => $params[6],
                                    ),
                                    "id = %i",
                                    $GLOBALS['request'][2]
                                );

                                echo '{"status":"item updated"}';
                            } catch(PDOException $ex) {
                                echo '<br />' . $ex->getMessage();
                            }
                        } else {
                            rest_error ('NO_DATA_EXIST');
                        }
                    } else {
                        rest_error ('ITEMMISSINGDATA');
                    }
                } else {
                    rest_error('NO_ITEM');
                }
            }
            /*
            * UPDATING A FOLDER
            * <url to teampass>/api/index.php/update/folder/<folder_id>/<title>;<complexity_level>;<renewal_period>?apikey=<valid api key>
            */
            else if($GLOBALS['request'][1] == "folder") {
                if ($GLOBALS['request'][2] !== "" && is_numeric($GLOBALS['request'][2])) {
                    // get sent parameters
                    $params = explode(';', base64_decode($GLOBALS['request'][3]));

                    if (!empty($params[0])) {
                        if ($params[1] < 0) {
                            rest_error ('NO_DATA_EXIST');
                        }
                        if (empty($params[2])) $params[2] = 0;

                        // check if folder exists and get folder data
                        $data_folder = DB::queryfirstrow("SELECT * FROM ".prefix_table("nested_tree")." WHERE id = %s", $GLOBALS['request'][2]);
                        $counter = DB::count();
                        if ($counter === 0) {
                            rest_error ('NO_DATA_EXIST');
                        }

                        //Check if title doesn't contains html codes
                        if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $params[0], $out)) {
                            rest_error ('HTML_CODES_NOT_ALLOWED');
                        }

                        // check if title is numeric
                        if (is_numeric($params[0]) === true) {
                            rest_error ('TITLE_ONLY_WITH_NUMBERS');
                        }

                        // get complexity level for this folder
                        $data = DB::queryfirstrow(
                            "SELECT valeur
                            FROM ".prefix_table("misc")."
                            WHERE intitule = %i AND type = %s",
                            $data_folder['parent_id'],
                            "complex"
                        );
                        if (intval($params[1]) < intval($data['valeur'])) {
                            rest_error ('COMPLEXICITY_LEVEL_NOT_REACHED');
                        }

                        try {
                            DB::update(
                                prefix_table("nested_tree"),
                                array(
                                    'parent_id' => $data_folder['parent_id'],
                                    'title' => $params[0],
                                    'personal_folder' => 0,
                                    'renewal_period' => $params[2],
                                    'bloquer_creation' => '0',
                                    'bloquer_modification' => '0'
                                ),
                                "id = %i",
                                $GLOBALS['request'][2]
                            );

                            //Add complexity
                            DB::update(
                                prefix_table("misc"),
                                array(
                                    'valeur' => $params[1]
                                ),
                                "intitule = %s AND type = %s",
                                $GLOBALS['request'][2],
                                "complex"
                            );

                            echo '{"status":"folder updated"}';
                        } catch(PDOException $ex) {
                            echo '<br />' . $ex->getMessage();
                        }
                    } else {
                        rest_error ('ITEMMISSINGDATA');
                    }
                } else {
                    rest_error('NO_ITEM');
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
                    $userData = DB::queryFirstRow(
                        "SELECT `id`, `pw`, `groupes_interdits`, `groupes_visibles`, `fonction_id` FROM ".$pre."users WHERE login = %s",
                        $GLOBALS['request'][3]
                    );

                    // load passwordLib library
                    $_SESSION['settings']['cpassman_dir'] = "..";
                    require_once '../sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    if ($pwdlib->verifyPasswordHash($GLOBALS['request'][4], $userData['pw']) === true) {
                        // define the restriction of "id_tree" of this user
                        //db::debugMode(true);
                        $userDef = DB::queryOneColumn('folder_id',
                            "SELECT DISTINCT folder_id
                            FROM ".prefix_table("roles_values")."
                            WHERE type IN ('R', 'W', 'ND', 'NE', 'NDNE', 'NEND') ", empty($userData['groupes_interdits']) ? "" : "
                            AND folder_id NOT IN (".str_replace(";", ",", $userData['groupes_interdits']).")", "
                            AND role_id IN %ls
                            GROUP BY folder_id",
                            explode(";", $userData['groupes_interdits'])
                        );
                        // complete with "groupes_visibles"
                        foreach (explode(";", $userData['groupes_visibles']) as $v) {
                            array_push($userDef, $v);
                        }

                        // find the item associated to the url
                        $response = DB::query(
                            "SELECT id, label, login, pw, pw_iv, id_tree, restricted_to
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
                                    ($data['restricted_to'] != "" && in_array($userData['id'], explode(";", $data['restricted_to'])))
                                ) {
                                    // prepare export
                                    $json[$data['id']]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                                    $json[$data['id']]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                                    $crypt_pw = cryption(
                                        $data['pw'],
                                        "",
                                        "decrypt"
                                    );
                                    $json[$data['id']]['pw'] = $crypt_pw['string'];
                                }
                            }
                            // prepare answer. If no access then inform
                            if (empty($json)) {
                                rest_error ('AUTH_NO_DATA');
                            } else {
                                echo json_encode($json);
                            }
                        } else {
                            rest_error ('NO_DATA_EXIST');
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
        } elseif ($GLOBALS['request'][0] == "auth_tpc") {
            /*
            ** TO BE USED ONLY BY TEAMPASS-CONNECT
            **
            */
            // get user credentials
            if(isset($GLOBALS['request'][2]) && isset($GLOBALS['request'][3]) && isset($GLOBALS['request'][4])) {
                // get url
                if(isset($GLOBALS['request'][1])) {
                    // decode base64 criterium
                    $tpc_url = base64_decode($GLOBALS['request'][1]);
                    $user_pwd = base64_decode($GLOBALS['request'][3]);
                    $user_saltkey = base64_decode($GLOBALS['request'][4]);

                    // is user granted?
                    //db::debugMode(true);
                    $userData = DB::queryFirstRow(
                        "SELECT `id`, `pw`, `groupes_interdits`, `groupes_visibles`, `fonction_id`, `encrypted_psk` FROM ".$pre."users WHERE login = %s",
                        $GLOBALS['request'][2]
                    );

                    // check if psk is correct.
                    $user_saltkey = defuse_validate_personal_key(
                        $user_saltkey,
                        $userData['encrypted_psk']
                    );
                    if (strpos($user_saltkey, "Error ") !== false) {
                        // error
                        rest_error ('AUTH_NO_DATA');
                    }

                    // load passwordLib library
                    $_SESSION['settings']['cpassman_dir'] = "..";
                    require_once '../sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    if ($pwdlib->verifyPasswordHash($user_pwd, $userData['pw']) === true) {
                        // define the restriction of "id_tree" of this user
                        //db::debugMode(true);
                        $userDef = DB::queryOneColumn('folder_id',
                            "SELECT DISTINCT folder_id
                            FROM ".prefix_table("roles_values")."
                            WHERE type IN ('R', 'W', 'ND', 'NE', 'NDNE', 'NEND') ", empty($userData['groupes_interdits']) ? "" : "
                            AND folder_id NOT IN (".str_replace(";", ",", $userData['groupes_interdits']).")", "
                            AND role_id IN %ls
                            GROUP BY folder_id",
                            explode(";", $userData['groupes_interdits'])
                        );
                        // complete with "groupes_visibles"
                        foreach (explode(";", $userData['groupes_visibles']) as $v) {
                            array_push($userDef, $v);
                        }

                        // add PF
                        $userpf = DB::queryFirstRow(
                            "SELECT `id` FROM ".$pre."nested_tree WHERE title = %s",
                            $userData['id']
                        );
                        array_push($userDef, $userpf['id']);

                        // find the item associated to the url
                        $response = DB::query(
                            "SELECT id, label, login, pw, pw_iv, id_tree, restricted_to, perso
                            FROM ".prefix_table("items")."
                            WHERE url LIKE %s
                            AND id_tree IN (".implode(",", array_filter($userDef)).")
                            AND inactif = %i
                            ORDER BY id DESC",
                            $tpc_url.'%',
                            0
                        );
                        $counter = DB::count();

                        if ($counter > 0) {
                            $json = "";
                            foreach ($response as $data) {
                                // check if item visible
                                if (
                                    empty($data['restricted_to']) ||
                                    ($data['restricted_to'] != "" && in_array($userData['id'], explode(";", $data['restricted_to'])))
                                ) {
                                    // prepare export
                                    $json[$data['id']]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                                    $json[$data['id']]['login'] =  mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                                    if ($data['perso'] === "0") {
                                        $crypt_pw = cryption(
                                            $data['pw'],
                                            "",
                                            "decrypt"
                                        );
                                    } else if (empty($user_saltkey)) {
                                        $crypt_pw['string'] = "no_psk";
                                    } else {
                                        $crypt_pw = cryption(
                                            $data['pw'],
                                            $user_saltkey,
                                            "decrypt"
                                        );
                                    }
                                    $json[$data['id']]['pw'] = mb_detect_encoding($crypt_pw['string'], 'UTF-8', true) ? $crypt_pw['string'] : "not_utf8";
                                    $json[$data['id']]['perso'] = $data['perso'];
                                }
                            }
                            // prepare answer. If no access then inform
                            if (empty($json)) {
                                rest_error ('AUTH_NO_DATA');
                            } else {
                                echo json_encode($json);
                            }
                        } else {
                            rest_error ('NO_DATA_EXIST');
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
        } elseif ($GLOBALS['request'][0] == "set") {
            /*
             * Expected call format: .../api/index.php/set/<login_to_save>/<password_to_save>/<url>/<user_login>/<user_password>/<label>/<protocol>?apikey=<VALID API KEY>
             * Example: https://127.0.0.1/teampass/api/index.php/set/newLogin/newPassword/newUrl/myLogin/myPassword?apikey=gu6Eexaewaishooph6iethoh5woh0yoit6ohquo
             *
             * NEW ITEM WILL BE STORED IN SPECIFIC FOLDER
             */
            // get user credentials
            if(isset($GLOBALS['request'][4]) && isset($GLOBALS['request'][5])) {
                // get url
                if (isset($GLOBALS['request'][1]) && isset($GLOBALS['request'][2]) && isset($GLOBALS['request'][3])) {
                    // is user granted?
                    $userData = DB::queryFirstRow(
                        "SELECT `id`, `pw`, `groupes_interdits`, `groupes_visibles`, `fonction_id` FROM " . $pre . "users WHERE login = %s",
                        $GLOBALS['request'][4]
                    );
                    if (DB::count() == 0) {
                        rest_error ('AUTH_NO_IDENTIFIER');
                    }

                    // load passwordLib library
                    $_SESSION['settings']['cpassman_dir'] = "..";
                    require_once '../sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    // is user identified?
                    if ($pwdlib->verifyPasswordHash($GLOBALS['request'][5], $userData['pw']) === true) {
                        // does the personal folder of this user exists?
                        DB::queryFirstRow(
                            "SELECT `id`
                            FROM " . $pre . "nested_tree
                            WHERE title = %s AND personal_folder = 1",
                            $userData['id']
                        );
                        if (DB::count() > 0) {
                            // check if "teampass-connect" folder exists
                            // if not create it
                            $folder = DB::queryFirstRow(
                                "SELECT `id`
                                FROM " . $pre . "nested_tree
                                WHERE title = %s",
                                "teampass-connect"
                            );
                            if (DB::count() == 0) {
                                DB::insert(
                                    prefix_table("nested_tree"),
                                    array(
                                        'parent_id' => '0',
                                        'title' => "teampass-connect"
                                    )
                                );
                                $tpc_folder_id = DB::insertId();

                                //Add complexity
                                DB::insert(
                                    prefix_table("misc"),
                                    array(
                                        'type' => 'complex',
                                        'intitule' => $tpc_folder_id,
                                        'valeur' => '0'
                                    )
                                );

                                // rebuild tree
                                $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                                $tree->register();
                                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
                                $tree->rebuild();
                            } else {
                                $tpc_folder_id = $folder['id'];
                            }

                            // encrypt password
                            $encrypt = cryption(
                                $GLOBALS['request'][2],
                                "",
                                "encrypt"
                            );

                            // is there a protocol?
                            if (isset($GLOBALS['request'][7]) || empty($GLOBALS['request'][7])) {
                                $protocol = "http://";
                            } else {
                                $protocol = urldecode($GLOBALS['request'][7])."://";
                            }

                            // add new item
                            DB::insert(
                                prefix_table("items"),
                                array(
                                    'label' => "Credentials for ".urldecode($GLOBALS['request'][3]),
                                    'description' => "Imported with Teampass-Connect",
                                    'pw' => $encrypt['string'],
                                    'pw_iv' => "",
                                    'email' => "",
                                    'url' => urldecode($GLOBALS['request'][3]),
                                    'id_tree' => $tpc_folder_id,
                                    'login' => $GLOBALS['request'][1],
                                    'inactif' => '0',
                                    'restricted_to' => $userData['id'],
                                    'perso' => '0',
                                    'anyone_can_modify' => '0',
                                    'complexity_level' => '0'
                                )
                            );
                            $newID = DB::insertId();

                            // log
                            logItems(
                                $newID,
                                "Credentials for ".urldecode($GLOBALS['request'][3].'%'),
                                $userData['id'],
                                'at_creation',
                                $GLOBALS['request'][1]
                            );

                            $json['status'] = "ok";
                            // prepare answer. If no access then inform
                            if (empty($json)) {
                                rest_error ('AUTH_NO_DATA');
                            } else {
                                echo json_encode($json);
                            }
                        } else {
                            rest_error ('NO_PF_EXIST_FOR_USER');
                        }
                    } else {
                        rest_error ('AUTH_NOT_GRANTED');
                    }
                } else {
                    rest_error ('SET_NO_DATA');
                }
            } else {
                rest_error ('AUTH_NO_IDENTIFIER');
            }
        } elseif ($GLOBALS['request'][0] == "set_tpc") {
            /*
             * TO BE USED ONLY BY TEAMPASS-CONNECT
             */
            // get user credentials
            if(isset($GLOBALS['request'][2]) && isset($GLOBALS['request'][3])) {
                // get url
                if (isset($GLOBALS['request'][1])) {
                    // is user granted?
                    $userData = DB::queryFirstRow(
                        "SELECT `id`, `pw`, `groupes_interdits`, `groupes_visibles`, `fonction_id`, `encrypted_psk` FROM " . $pre . "users WHERE login = %s",
                        $GLOBALS['request'][2]
                    );
                    if (DB::count() == 0) {
                        rest_error ('AUTH_NO_IDENTIFIER');
                    }

                    // load passwordLib library
                    $_SESSION['settings']['cpassman_dir'] = "..";
                    require_once '../sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    // prepare TPC parameters
                    $tpc_param = explode(';@;', base64_decode($GLOBALS['request'][1]));
                    $tpc_param[5] = base64_decode($tpc_param[5]);

                    // is user identified?
                    if ($pwdlib->verifyPasswordHash(base64_decode($GLOBALS['request'][3]), $userData['pw']) === true) {
                        //
                        if ($tpc_param[4] !== "0") {
                            // it is not a personal folder
                            $salt = "";
                            $tpc_folder_id = $tpc_param[4];
                            $perso = '0';
                            $restricted_to = $userData['id'];

                        } else if ($tpc_param[4] === "0" && $tpc_param[5] !== "") {
                            // it is a personal folder
                            $salt = $tpc_param[5];

                            // check if psk is correct.
                            $salt = defuse_validate_personal_key(
                                $salt,
                                $userData['encrypted_psk']
                            );
                            if (strpos($user_key_encoded, "Error ") !== false) {
                                // error
                                rest_error ('AUTH_NO_DATA');
                            }


                            $perso = '1';
                            $restricted_to = "";

                            // does the personal folder of this user exists?
                            $user_folder = DB::queryFirstRow(
                                "SELECT `id`
                                FROM " . $pre . "nested_tree
                                WHERE title = %s AND personal_folder = 1",
                                $userData['id']
                            );
                            if (DB::count() === 0) {
                                // check if "teampass-connect" folder exists
                                // if not create it
                                $folder = DB::queryFirstRow(
                                    "SELECT `id`
                                    FROM " . $pre . "nested_tree
                                    WHERE title = %s",
                                    "teampass-connect"
                                );
                                if (DB::count() == 0) {
                                    DB::insert(
                                        prefix_table("nested_tree"),
                                        array(
                                            'parent_id' => '0',
                                            'title' => "teampass-connect"
                                        )
                                    );
                                    $tpc_folder_id = DB::insertId();

                                    //Add complexity
                                    DB::insert(
                                        prefix_table("misc"),
                                        array(
                                            'type' => 'complex',
                                            'intitule' => $tpc_folder_id,
                                            'valeur' => '0'
                                        )
                                    );

                                    // rebuild tree
                                    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                                    $tree->register();
                                    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
                                    $tree->rebuild();
                                } else {
                                    $tpc_folder_id = $folder['id'];
                                }
                            } else {
                                $tpc_folder_id = $user_folder['id'];
                            }
                        } else {
                            // there is an error in PSALT
                            rest_error ('NO_PSALTK_PROVIDED');
                        }

                        // now we continue
                        // encrypt password
                        $encrypt = cryption(
                            urldecode($tpc_param[1]),
                            $salt,
                            "encrypt"
                        );

                        // is there a label?
                        if (empty($tpc_param[3])) {
                            $label = "Credentials for ".urldecode($tpc_param[2]);
                        } else {
                            $label = urldecode($tpc_param[3]);
                        }

                        // add new item
                        DB::insert(
                            prefix_table("items"),
                            array(
                                'label' => $label,
                                'description' => "Imported with Teampass-Connect",
                                'pw' => $encrypt['string'],
                                'pw_iv' => "",
                                'email' => "",
                                'url' => urldecode($tpc_param[2]),
                                'id_tree' => $tpc_folder_id,
                                'login' => urldecode($tpc_param[0]),
                                'inactif' => '0',
                                'restricted_to' => $restricted_to,
                                'perso' => $perso,
                                'anyone_can_modify' => '0',
                                'complexity_level' => '0'
                            )
                        );
                        $newID = DB::insertId();

                        // log
                        logItems(
                            $newID,
                            $label,
                            $userData['id'],
                            'at_creation',
                            ''
                        );

                        $json['status'] = "ok";
                        $json['new_item_id'] = $newID;
                        // prepare answer. If no access then inform
                        if (empty($json)) {
                            rest_error ('AUTH_NO_DATA');
                        } else {
                            echo json_encode($json);
                        }
                    } else {
                        rest_error ('AUTH_NOT_GRANTED');
                    }
                } else {
                    rest_error ('SET_NO_DATA');
                }
            } else {
                rest_error ('AUTH_NO_IDENTIFIER');
            }
        }
        /*
        * DELETE
        *
        * Expected call format: .../api/index.php/delete/folder/<folder_id1;folder_id2;folder_id3>?apikey=<VALID API KEY>
        * Expected call format: .../api/index.php/delete/item>/<item_id1;item_id2;item_id3>?apikey=<VALID API KEY>
        */
        elseif ($GLOBALS['request'][0] == "delete") {
            $_SESSION['settings']['cpassman_dir'] = "..";
            if($GLOBALS['request'][1] == "folder") {
                $array_category = explode(';', $GLOBALS['request'][2]);

                // get user info
                if (isset($GLOBALS['request'][3]) && !empty($GLOBALS['request'][3])) {
                    $userData = DB::queryFirstRow(
                        "SELECT `id` FROM " . $pre . "users WHERE login = %s",
                        $GLOBALS['request'][3]
                    );
                    if (DB::count() == 0) {
                        $user_id = API_USER_ID;
                    } else {
                        $user_id = $userData['id'];
                    }
                }

                if(count($array_category) > 0 && count($array_category) < 5) {
                    // load passwordLib library
                    require_once '../sources/SplClassLoader.php';

                    // prepare tree
                    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                    $tree->register();
                    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title', 'personal_folder');

                    // this will delete all sub folders and items associated
                    for ($i=0; $i < count($array_category); $i ++) {
                        // Get through each subfolder
                        $folders = $tree->getDescendants($array_category[$i], true);
                        print_r($folders);
                        if (count($folders) > 0) {
                            foreach ($folders as $folder) {
                                if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->personal_folder != 1) {
                                    //Store the deleted folder (recycled bin)
                                    DB::insert(
                                        prefix_table("misc"),
                                        array(
                                            'type' => 'folder_deleted',
                                            'intitule' => "f".$array_category[$i],
                                            'valeur' => $folder->id.', '.$folder->parent_id.', '.
                                                $folder->title.', '.$folder->nleft.', '.$folder->nright.', '. $folder->nlevel.', 0, 0, 0, 0'
                                       )
                                    );
                                    //delete folder
                                    DB::delete(prefix_table("nested_tree"), "id = %i", $folder->id);

                                    //delete items & logs
                                    $items = DB::query(
                                        "SELECT id
                                        FROM ".prefix_table("items")."
                                        WHERE id_tree=%i",
                                        $folder->id
                                    );
                                    foreach ($items as $item) {
                                        DB::update(
                                            prefix_table("items"),
                                            array(
                                                'inactif' => '1',
                                            ),
                                            "id = %i",
                                            $item['id']
                                        );
                                        //log
                                        DB::insert(
                                            prefix_table("log_items"),
                                            array(
                                                'id_item' => $item['id'],
                                                'date' => time(),
                                                'id_user' => $user_id,
                                                'action' => 'at_delete'
                                            )
                                        );
                                    }
                                    //Update CACHE table
                                    updateCacheTable("delete_value", $array_category[$i]);
                                }
                            }
                        }
                    }
                } else {
                    rest_error ('NO_CATEGORY');
                }

                $json['status'] = 'OK';

            } elseif($GLOBALS['request'][1] == "item") {
                $array_items = explode(';', $GLOBALS['request'][2]);

                // get user info
                if (isset($GLOBALS['request'][3]) && !empty($GLOBALS['request'][3])) {
                    $userData = DB::queryFirstRow(
                        "SELECT `id` FROM " . $pre . "users WHERE login = %s",
                        $GLOBALS['request'][3]
                    );
                    if (DB::count() == 0) {
                        $user_id = API_USER_ID;
                    } else {
                        $user_id = $userData['id'];
                    }
                }

                for ($i=0; $i < count($array_items); $i ++) {
                    DB::update(
                        prefix_table("items"),
                        array(
                            'inactif' => '1',
                        ),
                        "id = %i",
                        $array_items[$i]
                    );
                    //log
                    DB::insert(
                        prefix_table("log_items"),
                        array(
                            'id_item' => $array_items[$i],
                            'date' => time(),
                            'id_user' => $user_id,
                            'action' => 'at_delete'
                        )
                    );

                    //Update CACHE table
                    updateCacheTable("delete_value", $array_items[$i]);
                }

                $json['status'] = 'OK';
            }

            if ($json) {
                echo json_encode($json);
            } else {
                rest_error ('EMPTY');
            }
        } else if ($GLOBALS['request'][0] == "new_password") {
            if (!empty($GLOBALS['request'][1])) {
                $params = explode(";", $GLOBALS['request'][1]);

                if (empty($params[0])) $params[0] = 8;
                if (empty($params[1])) $params[1] = 0;
                if (empty($params[2])) $params[2] = 0;
                if (empty($params[3])) $params[3] = 0;
                if (empty($params[4])) $params[4] = 0;
                if (empty($params[5])) $params[5] = 0;
                if (empty($params[6])) $params[6] = 0;

                // load library
                require_once '../sources/SplClassLoader.php';
                $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
                $pwgen->register();
                $pwgen = new Encryption\PwGen\pwgen();

                // init
                $pwgen->setLength($params[0]);
                if($params[1] === "1") $pwgen->setSecure(true);
                if($params[2] === "1") $pwgen->setNumerals(true);
                if($params[3] === "1") $pwgen->setCapitalize(true);
                if($params[4] === "1") $pwgen->setAmbiguous(true);
                if($params[5] === "1" && $params[6] === "1") $pwgen->setSymbols(true);

                // generate and send back (generate in base64 if symbols are asked)
                if ($params[6] === "1") {
                    echo '{"password" : "'.base64_encode($pwgen->generate()).'"}';
                } else {
                    echo '{"password" : "'.($pwgen->generate()).'"}';
                }
            } else {
                rest_error ('NO_PARAMETERS');
            }
        } else if ($GLOBALS['request'][0] === "info") {
            if ($GLOBALS['request'][1] === "complexicity_levels_list") {

                require_once '../includes/language/english.php';
                $json = array(
                    0=> $LANG['complex_level0'],
                    25=> $LANG['complex_level1'],
                    50=> $LANG['complex_level2'],
                    60=> $LANG['complex_level3'],
                    70=> $LANG['complex_level4'],
                    80=> $LANG['complex_level5'],
                    90=> $LANG['complex_level6']
                );

                echo json_encode($json);
            } else if ($GLOBALS['request'][1] === "folder") {
                if (!empty($GLOBALS['request'][2]) && is_numeric($GLOBALS['request'][2])) {
                    $data = DB::queryFirstRow(
                        "SELECT * FROM " . $pre . "nested_tree WHERE id = %i",
                        $GLOBALS['request'][2]
                    );
                    if (DB::count() == 0) {
                        rest_error ('NOSUCHFOLDER');
                    }

                    // form id_tree to full foldername
                    require_once '../sources/SplClassLoader.php';
                    //Load Tree
                    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
                    $tree->register();
                    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

                    $folder = "";
                    $arbo = $tree->getPath($GLOBALS['request'][2], true);
                    foreach ($arbo as $elem) {
                        if (empty($folder)) {
                            $folder = stripslashes($elem->title);
                        } else {
                            $folder .= " > ".stripslashes($elem->title);
                        }
                    }

                    // prepare info
                    $json = array(
                        "title" => $data['title'],
                        "personal_folder" => $data['personal_folder'],
                        "renewal_period" => $data['renewal_period'],
                        "parent_id" => $data['parent_id'],
                        "path" => $folder,
                    );

                    echo json_encode($json);
                } else {
                    rest_error ('NO_PARAMETERS');
                }
            } else if ($GLOBALS['request'][1] === "version") {
                echo '{"api-version":"'.$api_version.'"}';
            } else {
                rest_error ('NO_PARAMETERS');
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

    }
}

function rest_error ($type,$detail = 'N/A') {
    switch ($type) {
        case 'APIKEY':
            $message = Array('err' => 'This api_key '.$GLOBALS['apikey'].' doesn\'t exist');
            header('HTTP/1.1 405 Method Not Allowed');
            break;
        case 'NO_CATEGORY':
            $message = Array('err' => 'No folder specified');
            break;
        case 'NO_ITEM':
            $message = Array('err' => 'No item specified');
            break;
        case 'EMPTY':
            $message = Array('err' => 'No results');
            break;
        case 'IPWHITELIST':
            $message = Array('err' => 'Ip address not allowed.');
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
        case 'ITEMBADDEFINITION':
            $message = Array('err' => 'Item definition not complete');
            header('HTTP/1.1 405 Method Not Allowed');
            break;
        case 'ITEM_MALFORMED':
            $message = Array('err' => 'Item definition not numeric');
            header('HTTP/1.1 405 Method Not Allowed');
            break;
        case 'USERBADDEFINITION':
            $message = Array('err' => 'User definition not complete');
            header('HTTP/1.1 405 Method Not Allowed');
            break;
        case 'USERLOGINEMPTY':
            $message = Array('err' => 'Empty Login given');
            header('HTTP/1.1 405 Method Not Allowed');
            break;
        case 'USERALREADYEXISTS':
            $message = Array('err' => 'User already exists');
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
        case 'NO_DATA_EXIST':
            $message = Array('err' => 'No data exists');
            break;
        case 'PASSWORDTOOLONG':
            $message = Array('err' => 'Password is too long');
            break;
        case 'NOSUCHFOLDER':
            $message = Array('err' => 'Folder ID does not exist');
            break;
        case 'PASSWORDEMPTY':
            $message = Array('err' => 'Password is empty');
            break;
        case 'ITEMEXISTS':
            $message = Array('err' => 'Label already exists');
            break;
        case 'ITEMMISSINGDATA':
            $message = Array('err' => 'Label or Password or Folder ID is missing');
            break;
        case 'SET_NO_DATA':
            $message = Array('err' => 'No data to be stored');
            break;
        case 'NO_PF_EXIST_FOR_USER':
            $message = Array('err' => 'No Personal Folder exists for this user');
            break;
        case 'HTML_CODES_NOT_ALLOWED':
            $message = Array('err' => 'HTML tags not allowed');
            break;
        case 'TITLE_ONLY_WITH_NUMBERS':
            $message = Array('err' => 'Title only with numbers not allowed');
            break;
        case 'ALREADY_EXISTS':
            $message = Array('err' => 'Data already exists');
            break;
        case 'COMPLEXICITY_LEVEL_NOT_REACHED':
            $message = Array('err' => 'complexity level was not reached');
            break;
        case 'NO_PARAMETERS':
            $message = Array('err' => 'No parameters given');
            break;
        case 'USER_NOT_EXISTS':
            $message = Array('err' => 'User does not exist');
            break;
        case 'NO_PSALTK_PROVIDED':
            $message = Array('err' => 'No Personal saltkey provided');
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
