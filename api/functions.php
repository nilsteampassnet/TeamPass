<?php
/**
 *
 * @file          configapi.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
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
        $category_query = "";

        if ($GLOBALS['request'][0] == "read") {
            if($GLOBALS['request'][1] == "category") {
                // get ids
                if (strpos($GLOBALS['request'][2],";") > 0) {
                    $condition = "id_tree IN %ls";
                    $condition_value = explode(';', $GLOBALS['request'][2]);
                } else {
                    $condition = "id_tree = %s";
                    $condition_value = $GLOBALS['request'][2];
                }
                DB::debugMode(false);
                
                // get items in this module
                $response = DB::query("SELECT id,label,login,pw, pw_iv FROM ".prefix_table("items")." WHERE ".$condition, $condition_value);
                foreach ($response as $data)
                {
                    // prepare output
                    $id = $data['id'];
                    $json[$id]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                    $json[$id]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                    $json[$id]['pw'] = cryption($data['pw'], SALT, $data['pw_iv'], "decrypt" );
                }

                /* load folders */
                $response = DB::query(
                    "SELECT id,parent_id,title,nleft,nright,nlevel FROM ".prefix_table("nested_tree")." WHERE parent_id=%i ORDER BY `title` ASC",
                    $GLOBALS['request'][2]
                );
                $rows = array();
                $i = 0;
                foreach ($response as $row)
                {                    
                    $response = DB::query("SELECT id,label,login,pw, pw_iv FROM ".prefix_table("items")." WHERE id_tree=%i", $row['id']);
                    foreach ($response as $data)
                    {
                        // prepare output
                        $id = $data['id'];
                        $json[$id]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                        $json[$id]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                        $json[$id]['pw'] = cryption($data['pw'], SALT, $data['pw_iv'], "decrypt" );
                    }
                }
            }
            elseif($GLOBALS['request'][1] == "items") {
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

                $response = DB::query("select id,label,login,pw, pw_iv, id_tree from ".prefix_table("items")." where id IN %ls", $array_items);
                foreach ($response as $data)
                {
                    // prepare output
                    $id = $data['id'];
                    $json[$id]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                    $json[$id]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                    $json[$id]['pw'] = cryption($data['pw'], SALT, $data['pw_iv'], "decrypt" );
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
                    "select id, label, login, pw, pw_iv, id_tree
                    from ".prefix_table("items")."
                    where id_tree = (%s)
                    and label LIKE %ss",
                    $category_query,
                    $item
                );
                foreach ($response as $data)
                {
                    // prepare output
                    $json['id'] = mb_convert_encoding($data['id'], mb_detect_encoding($data['id']), 'UTF-8');
                    $json['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                    $json['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                    $json['pw'] = cryption($data['pw'], SALT, $data['pw_iv'], "decrypt" );
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
                $array_item = explode(';', urldecode($GLOBALS['request'][2]));
                if (count($array_item) != 9) {
                    rest_error ('ITEMBADDEFINITION');
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
                        rest_error ('PASSWORDTOOLONG');
                    }

                    // Check Folder ID
                    DB::query("SELECT * FROM ".prefix_table("nested_tree")." WHERE id = %i", $item_folder_id);
                    $counter = DB::count();
                    if ($counter == 0) {
                        rest_error ('NOSUCHFOLDER');
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
                        $encrypt = cryption($item_pwd, SALT, "", "encrypt");
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
                                    'pw_iv' => $encrypt['iv'],
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
                        rest_error ('ITEMEXISTS');
                    }
                } else {
                    rest_error ('ITEMMISSINGDATA');
                }
            }
            /*
             * Case where a new user has to be added
             * 
             * Expected call format: .../api/index.php/add/user/<LOGIN>;<NAME>;<LASTNAME>;<PASSWORD>;<EMAIL>;<ADMINISTRATEDBY>;<READ_ONLY>;<ROLE1|ROLE2|...>;<IS_ADMIN>;<ISMANAGER>;<PERSONAL_FOLDER>?apikey=<VALID API KEY>
             * with:
             * for READ_ONLY, IS_ADMIN, IS_MANAGER, PERSONAL_FOLDER, accepted value is 1 for TRUE and 0 for FALSE
             * for ADMINISTRATEDBY and ROLE1, accepted value is the real label (not the IDs)
             *
             * Example: /api/index.php/add/user/U4;Nils;Laumaille;test;nils@laumaille.fr;Users;0;Managers|Users;0;1;1?apikey=sae6iekahxiseL3viShoo0chahc1ievei8aequi
             *
             */
            elseif($GLOBALS['request'][1] == "user") {
                
                // get user definition
                $array_user = explode(';', $GLOBALS['request'][2]);
                if (count($array_user) != 11) {
                    rest_error ('USERBADDEFINITION');
                }
                
                $login = $array_user[0];
                $name = $array_user[1];
                $lastname = $array_user[2];
                $password = $array_user[3];
                $email = $array_user[4];
                $adminby = $array_user[5];
                $isreadonly = $array_user[6];
                $roles = $array_user[7];
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
                        foreach (explode('|', $roles) as $role) {echo $role."-";
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
                        // Send email to new user
                        @sendEmail(
                            $LANG['email_subject_new_user'],
                            str_replace(array('#tp_login#', '#tp_pw#', '#tp_link#'), array(" ".addslashes($login), addslashes($password), $_SESSION['settings']['email_server_url']), $LANG['email_new_user_mail']),
                            $email
                        );
                        // update LOG
            logEvents('user_mngt', 'at_user_added', 'api - '.$GLOBALS['apikey'], $new_user_id);
                        echo '{"status":"user added"}';
                    } catch(PDOException $ex) {
                        echo '<br />' . $ex->getMessage();
                    }
                } else {
                    rest_error ('USERALREADYEXISTS');
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
                    
                    // load passwordLib library
                    $_SESSION['settings']['cpassman_dir'] = "..";
                    require_once '../sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();
                    
                    if ($pwdlib->verifyPasswordHash($GLOBALS['request'][4], $user['pw']) === true) {
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
                                    ($data['restricted_to'] != "" && in_array($user['id'], explode(";", $data['restricted_to'])))
                                ) {                            
                                    // prepare export
                                    $json[$data['id']]['label'] = mb_convert_encoding($data['label'], mb_detect_encoding($data['label']), 'UTF-8');
                                    $json[$data['id']]['login'] = mb_convert_encoding($data['login'], mb_detect_encoding($data['login']), 'UTF-8');
                                    $json[$data['id']]['pw'] = cryption($data['pw'], SALT, $data['pw_iv'], "decrypt");
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
        } elseif ($GLOBALS['request'][0] == "set") {
            /*
             * Expected call format: .../api/index.php/set/<login_to_save>/<password_to_save>/<url>/<user_login>/<user_password>?apikey=<VALID API KEY>
             * Example: https://127.0.0.1/teampass/api/index.php/auth/myLogin/myPassword/USER1/test/76?apikey=chahthait5Aidood6johh6Avufieb6ohpaixain
             *
             * NEW ITEM WILL BE STORED IN SPECIFIC FOLDER
             */
            // get user credentials
            if(isset($GLOBALS['request'][4]) && isset($GLOBALS['request'][5])) {
                // get url
                if (isset($GLOBALS['request'][1]) && isset($GLOBALS['request'][2]) && isset($GLOBALS['request'][3])) {
                    // is user granted?
                    $user = DB::queryFirstRow(
                        "SELECT `id`, `pw`, `groupes_interdits`, `groupes_visibles`, `fonction_id` FROM " . $pre . "users WHERE login = %s",
                        $GLOBALS['request'][4]
                    );

                    // load passwordLib library
                    $_SESSION['settings']['cpassman_dir'] = "..";
                    require_once '../sources/SplClassLoader.php';
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    // is user identified?
                    if ($pwdlib->verifyPasswordHash($GLOBALS['request'][5], $user['pw']) === true) {
                        // does the personal folder of this user exists?
                        DB::queryFirstRow(
                            "SELECT `id`
                            FROM " . $pre . "nested_tree
                            WHERE title = %s AND personal_folder = 1",
                            $user['id']
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
                                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
                                $tree->rebuild();
                            } else {
                                $tpc_folder_id = $folder['id'];
                            }

                            // encrypt password
                            $encrypt = cryption($GLOBALS['request'][2], SALT, "", "encrypt");

                            // add new item
                            DB::insert(
                                prefix_table("items"),
                                array(
                                    'label' => "Credentials for ".urldecode($GLOBALS['request'][3].'%'),
                                    'description' => "Imported with Teampass-Connect",
                                    'pw' => $encrypt['string'],
                                    'pw_iv' => $encrypt['iv'],
                                    'email' => "",
                                    'url' => urldecode($GLOBALS['request'][3].'%'),
                                    'id_tree' => $tpc_folder_id,
                                    'login' => $GLOBALS['request'][1],
                                    'inactif' => '0',
                                    'restricted_to' => $user['id'],
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
                                $user['id'],
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

