<?php
/**
 *
 * @file          users.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.23
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
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
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_users")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
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

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        case "groupes_visibles":
        case "groupes_interdits":
            $val = explode(';', $_POST['valeur']);
            $valeur = $_POST['valeur'];
            // Check if id folder is already stored
            $data = DB::queryfirstrow("SELECT ".$_POST['type']." FROM ".prefix_table("users")." WHERE id = %i", $val[0]);
            $new_groupes = $data[$_POST['type']];
            if (!empty($data[$_POST['type']])) {
                $groupes = explode(';', $data[$_POST['type']]);
                if (in_array($val[1], $groupes)) {
                    $new_groupes = str_replace($val[1], "", $new_groupes);
                } else {
                    $new_groupes .= ";".$val[1];
                }
            } else {
                $new_groupes = $val[1];
            }
            while (substr_count($new_groupes, ";;") > 0) {
                $new_groupes = str_replace(";;", ";", $new_groupes);
            }
            // Store id DB
            DB::update(
                prefix_table("users"),
                array($_POST['type'] => $new_groupes
                   ),
                "id = %i",
                $val[0]
            );
            break;
        /**
         * Update a fonction
         */
        case "fonction":
            $val = explode(';', $_POST['valeur']);
            $valeur = $_POST['valeur'];
            // v?rifier si l'id est d?j? pr?sent
            $data = DB::queryfirstrow("SELECT fonction_id FROM ".prefix_table("users")." WHERE id = %i", $val[0]);
            $new_fonctions = $data['fonction_id'];
            if (!empty($data['fonction_id'])) {
                $fonctions = explode(';', $data['fonction_id']);
                if (in_array($val[1], $fonctions)) {
                    $new_fonctions = str_replace($val[1], "", $new_fonctions);
                } elseif (!empty($new_fonctions)) {
                    $new_fonctions .= ";".$val[1];
                } else {
                    $new_fonctions = ";".$val[1];
                }
            } else {
                $new_fonctions = $val[1];
            }
            while (substr_count($new_fonctions, ";;") > 0) {
                $new_fonctions = str_replace(";;", ";", $new_fonctions);
            }
            // Store id DB
            DB::update(
                prefix_table("users"),
                array(
                    'fonction_id' => $new_fonctions
                   ),
                "id = %i",
                $val[0]
            );
            break;
        /**
         * ADD NEW USER
         */
        case "add_new_user":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            // Prepare variables
            $login = htmlspecialchars_decode($dataReceived['login']);
            $name = htmlspecialchars_decode($dataReceived['name']);
            $lastname = htmlspecialchars_decode($dataReceived['lastname']);
            $pw = htmlspecialchars_decode($dataReceived['pw']);

            // Empty user
            if (mysqli_escape_string($link, htmlspecialchars_decode($login)) == "") {
                echo '[ { "error" : "'.addslashes($LANG['error_empty_data']).'" } ]';
                break;
            }
            // Check if user already exists
            $data = DB::query(
                "SELECT id, fonction_id, groupes_interdits, groupes_visibles FROM ".prefix_table("users")."
                WHERE login LIKE %ss",
                mysqli_escape_string($link, stripslashes($login))
            );

            if (DB::count() == 0) {
                // Add user in DB
                DB::insert(
                    prefix_table("users"),
                    array(
                        'login' => $login,
                        'name' => $name,
                        'lastname' => $lastname,
                        'pw' => bCrypt(stringUtf8Decode($pw), COST),
                        'email' => $dataReceived['email'],
                        'admin' => $dataReceived['admin'] == "true" ? '1' : '0',
                        'gestionnaire' => $dataReceived['manager'] == "true" ? '1' : '0',
                        'read_only' => $dataReceived['read_only'] == "true" ? '1' : '0',
                        'personal_folder' => $dataReceived['personal_folder'] == "true" ? '1' : '0',
                        'user_language' => $_SESSION['settings']['default_language'],
                        'fonction_id' => $dataReceived['manager'] == "true" ? $_SESSION['fonction_id'] : '0', // If manager is creater, then assign them roles as creator
                        'groupes_interdits' => ($dataReceived['manager'] == "true" && isset($data['groupes_interdits']) && !is_null($data['groupes_interdits'])) ? $data['groupes_interdits'] : '0',
                        'groupes_visibles' => ($dataReceived['manager'] == "true" && isset($data['groupes_visibles']) && !is_null($data['groupes_visibles'])) ? $data['groupes_visibles'] : '0',
                        'isAdministratedByRole' => $dataReceived['isAdministratedByRole']
                       )
                );
                $new_user_id = DB::insertId();
                // Create personnal folder
                if ($dataReceived['personal_folder'] == "true") {
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
                // Create folder and role for domain
                if ($dataReceived['new_folder_role_domain'] == "true") {
                    // create folder
                    DB::insert(
                        prefix_table("nested_tree"),
                        array(
                            'parent_id' => 0,
                            'title' => mysqli_escape_string($link, stripslashes($dataReceived['domain'])),
                            'personal_folder' => 0,
                            'renewal_period' => 0,
                            'bloquer_creation' => '0',
                            'bloquer_modification' => '0'
                           )
                    );
                    $new_folder_id = DB::insertId();
                    // Add complexity
                    DB::insert(
                        prefix_table("misc"),
                        array(
                            'type' => 'complex',
                            'intitule' => $new_folder_id,
                            'valeur' => 50
                           )
                    );
                    // Create role
                    DB::insert(
                        prefix_table("roles_title"),
                        array(
                            'title' => mysqli_escape_string($link, stripslashes(($dataReceived['domain'])))
                           )
                    );
                    $new_role_id = DB::insertId();
                    // Associate new role to new folder
                    DB::insert(
                        prefix_table("roles_values"),
                        array(
                            'folder_id' => $new_folder_id,
                            'role_id' => $new_role_id
                           )
                    );
                    // Add the new user to this role
                    DB::update(
                        prefix_table("users"),
                        array(
                            'fonction_id' => is_int($new_role_id)
                           ),
                        "id=%i",
                        $new_user_id
                    );
                    // rebuild tree
                    $tree->rebuild();
                }
                // get links url
                if (empty($_SESSION['settings']['email_server_url'])) {
                    $_SESSION['settings']['email_server_url'] = $_SESSION['settings']['cpassman_url'];
                }
                // Send email to new user
                @sendEmail(
                    $LANG['email_subject_new_user'],
                    str_replace(array('#tp_login#', '#tp_pw#', '#tp_link#'), array(" ".addslashes($login), addslashes($pw), $_SESSION['settings']['email_server_url']), $LANG['email_new_user_mail']),
                    $dataReceived['email']
                );
                // update LOG
                DB::insert(
                    prefix_table("log_system"),
                    array(
                        'type' => 'user_mngt',
                        'date' => time(),
                        'label' => 'at_user_added',
                        'qui' => $_SESSION['user_id'],
                        'field_1' => $new_user_id
                       )
                );
                echo '[ { "error" : "no" } ]';
            } else {
                echo '[ { "error" : "'.addslashes($LANG['error_user_exists']).'" } ]';
            }
            break;
        /**
         * Delete the user
         */
        case "delete_user":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                exit();
            }

            if ($_POST['action'] == "delete") {
                // delete user in database
                DB::delete(
                    prefix_table("users"),
                    "id = %i",
                    $_POST['id']
                );
                // delete personal folder and subfolders
                $data = DB::queryfirstrow(
                    "SELECT id FROM ".prefix_table("nested_tree")."
                    WHERE title = %s AND personal_folder = %i",
                    $_POST['id'],
                    "1"
                );
                // Get through each subfolder
                if (!empty($data['id'])) {
                    $folders = $tree->getDescendants($data['id'], true);
                    foreach ($folders as $folder) {
                        // delete folder
                        DB::delete(prefix_table("nested_tree"), "id = %i AND personal_folder = %i", $folder->id, "1");
                        // delete items & logs
                        $items = DB::query(
                            "SELECT id FROM ".prefix_table("items")."
                            WHERE id_tree=%i AND perso = %i",
                            $folder->id,
                            "1"
                        );
                        foreach ($items as $item) {
                            // Delete item
                            DB::delete(prefix_table("items"), "id = %i", $item['id']);
                            // log
                            DB::delete(prefix_table("log_items"), "id_item = %i", $item['id']);
                        }
                    }
                    // rebuild tree
                    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
                    $tree->rebuild();
                }
                // update LOG
                DB::insert(
                    prefix_table("log_system"),
                    array(
                        'type' => 'user_mngt',
                        'date' => time(),
                        'label' => 'at_user_deleted',
                        'qui' => $_SESSION['user_id'],
                        'field_1' => $_POST['id']
                       )
                );
            } else {
                // lock user in database
                DB::update(
                    prefix_table("users"),
                    array(
                        'disabled' => 1,
                        'key_tempo' => ""
                       ),
                    "id=%i",
                    $_POST['id']
                );
                // update LOG
                DB::insert(
                    prefix_table("log_system"),
                    array(
                        'type' => 'user_mngt',
                        'date' => time(),
                        'label' => 'at_user_locked',
                        'qui' => $_SESSION['user_id'],
                        'field_1' => $_POST['id']
                       )
                );
            }
            echo '[ { "error" : "no" } ]';
            break;
        /**
         * UPDATE EMAIL OF USER
         */
        case "modif_mail_user":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                echo '[ { "error" : "yes" } ]';
            }
            // Get old email
            $data = DB::queryfirstrow(
                "SELECT email FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            DB::update(
                prefix_table("users"),
                array(
                    'email' => $_POST['newemail']
                   ),
                "id = %i",
                $_POST['id']
            );
            // update LOG
            DB::insert(
                prefix_table("log_system"),
                array(
                    'type' => 'user_mngt',
                    'date' => time(),
                    'label' => 'at_user_email_changed:'.$data['email'],
                    'qui' => intval($_SESSION['user_id']),
                    'field_1' => intval($_POST['id'])
                   )
            );
        	echo '[{"error" : "no"}]';
            break;
        /**
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case "can_create_root_folder":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'can_create_root_folder' => $_POST['value']
                   ),
                "id = %i",
                $_POST['id']
            );
            break;
        /**
         * UPDATE MANAGER RIGHTS FOR USER
         */
        case "gestionnaire":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'gestionnaire' => $_POST['value']
                   ),
                "id = ".$_POST['id']
            );
            break;
        /**
         * UPDATE READ ONLY RIGHTS FOR USER
         */
        case "read_only":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'read_only' => $_POST['value']
                   ),
                "id = %i",
                $_POST['id']
            );
            break;
        /**
         * UPDATE ADMIN RIGHTS FOR USER
         */
        case "admin":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['is_admin'] != 1) {
                // error
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'admin' => $_POST['value']
                   ),
                "id = %i",
                $_POST['id']
            );
            break;
        /**
         * UPDATE PERSONNAL FOLDER FOR USER
         */
        case "personal_folder":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'personal_folder' => $_POST['value']
                   ),
                "id = %i",
                $_POST['id']
            );
            break;

        /**
         * CHANGE USER FUNCTIONS
         */
        case "open_div_functions";
            $text = "";
            // Refresh list of existing functions
            $data_user = DB::queryfirstrow(
                "SELECT fonction_id FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            $users_functions = explode(';', $data_user['fonction_id']);
            // array of roles for actual user
            $my_functions = explode(';', $_SESSION['fonction_id']);

            $rows = DB::query("SELECT id,title,creator_id FROM ".prefix_table("roles_title"));
            foreach ($rows as $record) {
                if ($_SESSION['is_admin'] == 1  || ($_SESSION['user_manager'] == 1 && (in_array($record['id'], $my_functions) || $record['creator_id'] == $_SESSION['user_id']))) {
                    $text .= '<input type="checkbox" id="cb_change_function-'.$record['id'].'"';
                    if (in_array($record['id'], $users_functions)) {
                        $text .= ' checked';
                    }
                    /*if ((!in_array($record['id'], $my_functions) && $_SESSION['is_admin'] != 1) && !($_SESSION['user_manager'] == 1 && $record['creator_id'] == $_SESSION['user_id'])) {
                        $text .= ' disabled="disabled"';
                    }*/
                    $text .= '>&nbsp;'.$record['title'].'<br />';
                }
            }
            // return data
            $return_values = json_encode(array("text" => $text), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            echo $return_values;
            break;
        /**
         * Change user's functions
         */
        case "change_user_functions";
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }
            // save data
            DB::update(
                prefix_table("users"),
                array(
                    'fonction_id' => $_POST['list']
                   ),
                "id = %i",
                $_POST['id']
            );
            // display information
            $text = "";
            // Check if POST is empty
            if (!empty($_POST['list'])) {
                $rows = DB::query(
                    "SELECT title FROM ".prefix_table("roles_title")." WHERE id IN %ls",
                    explode(";", $_POST['list'])
                );
                foreach ($rows as $record) {
                    $text .= '<i class=\'fa fa-angle-right\'></i>&nbsp;'.$record['title']."<br />";
                }
            } else {
                $text = '<span style=\"text-align:center\"><img src=\"includes/images/error.png\" /></span>';
            }
            // send back data
            echo '[{"text":"'.$text.'"}]';
            break;

        /**
         * CHANGE AUTHORIZED GROUPS
         */
        case "open_div_autgroups";
            $text = "";
            // Refresh list of existing functions
            $data_user = DB::queryfirstrow(
                "SELECT groupes_visibles FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            $user = explode(';', $data_user['groupes_visibles']);

            $tree_desc = $tree->getDescendants();
            foreach ($tree_desc as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
                    $text .= '<input type="checkbox" id="cb_change_autgroup-'.$t->id.'"';
                    $ident = "";
                    for ($y = 1; $y < $t->nlevel; $y++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    if (in_array($t->id, $user)) {
                        $text .= ' checked';
                    }
                    $text .= '>&nbsp;'.$ident.$t->title.'<br />';
                    $prev_level = $t->nlevel;
                }
            }
            // return data
            $return_values = json_encode(array("text" => $text), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            echo $return_values;
            break;

        /**
         * CHANGE ADMINISTRATED BY
         */
        case "change_user_adminby";
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }
            // save data
            DB::update(
                prefix_table("users"),
                array(
                    'isAdministratedByRole' => $_POST['isAdministratedByRole']
                   ),
                "id = %i",
                $_POST['userId']
            );
            echo '[{"done":""}]';
            break;

        /**
         * Change authorized groups
         */
        case "change_user_autgroups";
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }
            // save data
            DB::update(
                prefix_table("users"),
                array(
                    'groupes_visibles' => $_POST['list']
                   ),
                "id = %i",
                $_POST['id']
            );
            // display information
            $text = "";
            $val = str_replace(';', ',', $_POST['list']);
            // Check if POST is empty
            if (!empty($_POST['list'])) {
                $rows = DB::query(
                    "SELECT title,nlevel FROM ".prefix_table("nested_tree")." WHERE id IN %ls",
                    explode(";", $_POST['list'])
                );
                foreach ($rows as $record) {
                    $text .= '<i class=\'fa fa-angle-right\'></i>&nbsp;'.$record['title']."<br />";
                }
            }
            // send back data
            echo '[{"text":"'.$text.'"}]';
            break;

        /**
         * Change forbidden groups
         */
        case "open_div_forgroups";
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            $text = "";
            // Refresh list of existing functions
            $data_user = DB::queryfirstrow(
                "SELECT groupes_interdits FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            $user = explode(';', $data_user['groupes_interdits']);

            $tree_desc = $tree->getDescendants();
            foreach ($tree_desc as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
                    $text .= '<input type="checkbox" id="cb_change_forgroup-'.$t->id.'"';
                    $ident = "";
                    for ($y = 1;$y < $t->nlevel;$y++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    if (in_array($t->id, $user)) {
                        $text .= ' checked';
                    }
                    $text .= '>&nbsp;'.$ident.$t->title.'<br />';
                    $prev_level = $t->nlevel;
                }
            }
            // return data
            $return_values = json_encode(array("text" => $text), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            echo $return_values;
            break;

        /**
         * Change forbidden groups for user
         */
        case "change_user_forgroups";
            // save data
            DB::update(
                prefix_table("users"),
                array(
                    'groupes_interdits' => $_POST['list']
                   ),
                "id = %i",
                $_POST['id']
            );
            // display information
            $text = "";
            $val = str_replace(';', ',', $_POST['list']);
            // Check if POST is empty
            if (!empty($_POST['list'])) {
                $rows = DB::query(
                    "SELECT title,nlevel FROM ".prefix_table("nested_tree")." WHERE id IN %ls",
                    implode(";", $_POST['list'])
                );
                foreach ($rows as $record) {
                    $text .= '<i class=\'fa fa-angle-right\'></i>&nbsp;'.$ident.$record['title']."<br />";
                }
            }
            // send back data
            echo '[{"text":"'.$text.'"}]';
            break;
        /**
         * Unlock user
         */
        case "unlock_account":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'disabled' => 0,
                    'no_bad_attempts' => 0
                   ),
                "id = %i",
                $_POST['id']
            );
            // update LOG
            DB::insert(
                prefix_table("log_system"),
                array(
                    'type' => 'user_mngt',
                    'date' => time(),
                    'label' => 'at_user_unlocked',
                    'qui' => $_SESSION['user_id'],
                    'field_1' => $_POST['id']
                   )
            );
            break;
        /*
        * Check the domain
        */
        case "check_domain":
            $return = array();
            // Check if folder exists
            $data = DB::query(
                "SELECT * FROM ".prefix_table("nested_tree")."
                WHERE title = %s AND parent_id = %i",
                $_POST['domain'],
                "0"
            );
            $counter = DB::count();
            if ($counter != 0) {
                $return["folder"] = "exists";
            } else {
                $return["folder"] = "not_exists";
            }
            // Check if role exists
            $data = DB::query(
                "SELECT * FROM ".prefix_table("roles_title")."
                WHERE title = %s",
                $_POST['domain']
            );
            $counter = DB::count();
            if ($counter != 0) {
                $return["role"] = "exists";
            } else {
                $return["role"] = "not_exists";
            }

            echo json_encode($return);
            break;

        /*
        * Get logs for a user
        */
        case "user_log_items":
            $nb_pages = 1;
            $logs = $sql_filter = "";
            $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$LANG['pages'].'&nbsp;:&nbsp;</td>';

            if ($_POST['scope'] == "user_activity") {
                if (isset($_POST['filter']) && !empty($_POST['filter']) && $_POST['filter'] != "all") {
                    $sql_filter = " AND l.action = '".$_POST['filter']."'";
                }
                // get number of pages
                DB::query(
                    "SELECT *
                    FROM ".prefix_table("log_items")." as l
                    INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
                    INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i",
                    intval($_POST['id'].$sql_filter)
                );
                $counter = DB::count();
                // define query limits
                if (isset($_POST['page']) && $_POST['page'] > 1) {
                    $start = ($_POST['nb_items_by_page'] * ($_POST['page'] - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = DB::query(
                    "SELECT l.date as date, u.login as login, i.label as label, l.action as action
                    FROM ".prefix_table("log_items")." as l
                    INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
                    INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i
                    ORDER BY date DESC
                    LIMIT ".intval($start).",".intval($_POST['nb_items_by_page']),
                    intval($_POST['id'].$sql_filter)
                );
            } else {
                // get number of pages
                DB::query(
                    "SELECT *
                    FROM ".prefix_table("log_system")."
                    WHERE type = %s AND field_1=%i",
                    "user_mngt",
                    $_POST['id']
                );
                $counter = DB::count();
                // define query limits
                if (isset($_POST['page']) && $_POST['page'] > 1) {
                    $start = ($_POST['nb_items_by_page'] * ($_POST['page'] - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = DB::query(
                    "SELECT *
                    FROM ".prefix_table("log_system")."
                    WHERE type = %s AND field_1=%i
                    ORDER BY date DESC
                    LIMIT $start,".$_POST['nb_items_by_page'],
                    "user_mngt",
                    $_POST['id']
                );
            }
            // generate data
            if (isset($counter) && $counter != 0) {
                $nb_pages = ceil($counter / $_POST['nb_items_by_page']);
                for ($i = 1; $i <= $nb_pages; $i++) {
                    $pages .= '<td onclick=\'displayLogs('.$i.',\"user_mngt\")\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
                }
            }
            $pages .= '</tr></table>';
            if (isset($rows)) {
                foreach ($rows as $record) {
                    if ($_POST['scope'] == "user_mngt") {
                        $user = DB::queryfirstrow("SELECT login from ".prefix_table("users")." WHERE id=%i", $record['qui']);
                        $user_1 = DB::queryfirstrow("SELECT login from ".prefix_table("users")." WHERE id=%i", $_POST['id']);
                        $tmp = explode(":", $record['label']);
                        $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"center\">'.str_replace(array('"', '#user_login#'), array('\"', $user_1['login']), $LANG['login']).'</td><td align=\"center\">'.$user['login'].'</td><td align=\"center\"></td></tr>';
                    } else {
                        $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"center\">'.str_replace('"', '\"', $record['label']).'</td><td align=\"center\">'.$record['login'].'</td><td align=\"center\">'.$LANG[$record['action']].'</td></tr>';
                    }
                }
            }

            echo '[ { "table_logs": "'.$logs.'", "pages": "'.$pages.'", "error" : "no" } ]';
            break;

        /*
        * Migrate the Admin PF to User
        */
        case "migrate_admin_pf":
            // decrypt and retreive data in JSON format
        	$dataReceived = prepareExchangedData($_POST['data'], "decode");
            // Prepare variables
            $user_id = htmlspecialchars_decode($data_received['user_id']);
            $salt_user = htmlspecialchars_decode($data_received['salt_user']);

            if (!isset($_SESSION['my_sk']) || $_SESSION['my_sk'] == "") {
                echo '[ { "error" : "no_sk" } ]';
            } elseif ($salt_user == "") {
                echo '[ { "error" : "no_sk_user" } ]';
            } elseif ($user_id == "") {
                echo '[ { "error" : "no_user_id" } ]';
            } else {
                // Get folder id for Admin
                $admin_folder = DB::queryFirstRow(
                    "SELECT id FROM ".prefix_table("nested_tree")."
                    WHERE title = %i AND personal_folder = %i",
                    intval($_SESSION['user_id']),
                    "1"
                );
                // Get folder id for User
                $user_folder = DB::queryFirstRow(
                    "SELECT id FROM ".prefix_table("nested_tree")."
                    WHERE title=%i AND personal_folder = %i",
                    intval($user_id),
                    "1"
                );
                // Get through each subfolder
                foreach ($tree->getDescendants($admin_folder['id'], true) as $folder) {
                    // Create folder if necessary
                    if ($folder->title != $_SESSION['user_id']) {
                        // update folder
                        /*DB::update(
                            "nested_tree",
                            array(
                                'parent_id' => $user_folder['id']
                           ),
                            "id='".$folder->id."'"
                        );*/
                    }
                    // Get each Items in PF
                    $rows = DB::query(
                        "SELECT i.pw, i.label, l.id_user
                        FROM ".prefix_table("items")." as i
                        LEFT JOIN ".prefix_table("log_items")." as l ON (l.id_item=i.id)
                        WHERE l.action = %s AND i.perso=%i AND i.id_tree=%i",
                        "at_creation",
                        "1",
                        intval($folder->id)
                    );
                    foreach ($rows as $record) {
                        echo $record['label']." - ";
                        // Change user
                        DB::update(
                            prefix_table("log_items"),
                            array(
                                'id_user' => $user_id
                               ),
                            "id_item = %i AND id_user $ %i AND action = %s",
                            $record['id'],
                            $user_id,
                            "at_creation"
                        );
                    }
                }
                $tree->rebuild();
                echo '[ { "error" : "no" } ]';
            }

            break;

        /**
        * delete the timestamp value for specified user => disconnect
        */
        case "disconnect_user":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // Do
            DB::update(
                prefix_table("users"),
                array(
                    'timestamp' => "",
                    'key_tempo' => "",
                    'session_end' => ""
                   ),
                "id = %i",
                intval($_POST['user_id'])
            );
            break;

        /**
        * delete the timestamp value for all users
        */
        case "disconnect_all_users":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // Do
            $rows = DB::query(
                "SELECT id FROM ".prefix_table("users")."
                WHERE timestamp != %s AND admin != %i",
                "",
                "1"
            );
            foreach ($rows as $record) {
                DB::update(
                    prefix_table("users"),
                    array(
                        'timestamp' => "",
                        'key_tempo' => "",
                        'session_end' => ""
                       ),
                    "id = %i",
                    intval($record['id'])
                );
            }
            break;
            
        /**
        * delete the timestamp value for all users
        */
        case "load_users_list":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            
            //Build tree
            $tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

            $treeDesc = $tree->getDescendants();
            // Build FUNCTIONS list
            $rolesList = array();
            $rows = DB::query("SELECT id,title FROM ".prefix_table("roles_title")." ORDER BY title ASC");
            foreach ($rows as $reccord) {
                $rolesList[$reccord['id']] = array('id' => $reccord['id'], 'title' => $reccord['title']);
            }
            
            $listAvailableUsers = $listAdmins = $html = "";
            $listAlloFcts_position = false;
            $x = 0;
            // Get through all users
            $rows = DB::query("SELECT * FROM ".prefix_table("users")." ORDER BY login ASC LIMIT ".$_POST['from'].", ".$_POST['nb']."");
            foreach ($rows as $reccord) {
                // Get list of allowed functions
                $listAlloFcts = "";
                if ($reccord['admin'] != 1) {
                    if (count($rolesList) > 0) {
                        foreach ($rolesList as $fonction) {
                            if (in_array($fonction['id'], explode(";", $reccord['fonction_id']))) {
                                $listAlloFcts .= '<i class="fa fa-angle-right"></i>&nbsp;'.@htmlspecialchars($fonction['title'], ENT_COMPAT, "UTF-8").'<br />';
                            }
                        }
                        $listAlloFcts_position = true;
                    }
                    if (empty($listAlloFcts)) {
                        $listAlloFcts = '<i class="fa fa-exclamation fa-lg mi-red tip" title="'.$LANG['user_alarm_no_function'].'"></i>';
                        $listAlloFcts_position = false;
                    }
                }
                // Get list of allowed groups
                $listAlloGrps = "";
                if ($reccord['admin'] != 1) {
                    if (count($treeDesc) > 0) {
                        foreach ($treeDesc as $t) {
                            if (@!in_array($t->id, $_SESSION['groupes_interdits']) && in_array($t->id, $_SESSION['groupes_visibles'])) {
                                $ident = "";
                                if (in_array($t->id, explode(";", $reccord['groupes_visibles']))) {
                                    $listAlloGrps .= '<i class="fa fa-angle-right"></i>&nbsp;'.@htmlspecialchars($ident.$t->title, ENT_COMPAT, "UTF-8").'<br />';
                                }
                                $prev_level = $t->nlevel;
                            }
                        }
                    }
                }
                // Get list of forbidden groups
                $listForbGrps = "";
                if ($reccord['admin'] != 1) {
                    if (count($treeDesc) > 0) {
                        foreach ($treeDesc as $t) {
                            $ident = "";
                            if (in_array($t->id, explode(";", $reccord['groupes_interdits']))) {
                                $listForbGrps .= '<i class="fa fa-angle-right"></i>&nbsp;'.@htmlspecialchars($ident.$t->title, ENT_COMPAT, "UTF-8").'<br />';
                            }
                            $prev_level = $t->nlevel;
                        }
                    }
                }
                // is user locked?
                if ($reccord['disabled'] == 1) {
                }

                //Show user only if can be administrated by the adapted Roles manager
                if (
                    $_SESSION['is_admin'] ||
                    ($reccord['isAdministratedByRole'] > 0 &&
                    in_array($reccord['isAdministratedByRole'], $_SESSION['user_roles']))
                ) {
                    $showUserFolders = true;
                } else {
                    $showUserFolders = false;
                }
                
                // Build list of available users
                if ($reccord['admin'] != 1 && $reccord['disabled'] != 1) {
                    $listAvailableUsers .= '<option value="'.$reccord['id'].'">'.$reccord['login'].'</option>';
                }                
                
                // Display Grid
                if ($showUserFolders == true) {
                    // <-- prepare all possible conditions
                    // If user is active, then you could lock it
                    // If user is locked, you could delete it
                    if ($reccord['disabled'] == 1) {
                        $userTxt = $LANG['user_del'];
                        $actionOnUser = '<i class="fa fa-user-times fa-lg tip" style="cursor:pointer;" onclick="action_on_user(\''.$reccord['id'].'\',\'delete\')" title="'.$userTxt.'">';
                        $userIcon = "user--minus";
                        $row_style = ' style="background-color:#FF8080;font-size:11px;"';
                    } else {
                        $userTxt = $LANG['user_lock'];
                        $actionOnUser = '<i class="fa fa-lock fa-lg tip" style="cursor:pointer;" onclick="action_on_user(\''.$reccord['id'].'\',\'lock\')" title="'.$userTxt.'">';
                        $userIcon = "user-locked";
                        $row_style = ' class="ligne'.($x % 2).' data-row"';     
                    }
                    
                    if ($_SESSION['user_admin'] == 1 || ($_SESSION['user_manager'] == 1 && $reccord['admin'] == 0 && $reccord['gestionnaire'] == 0) && $showUserFolders == true)
                        $show_edit = '<i class="fa fa-pencil fa-lg tip" style="cursor:pointer;" onclick="user_edit_login('.$reccord['id'].')" title="'.$LANG['edit_user'].'">&nbsp;';
                    else
                        $show_edit = '';
                    $td_class = '';
                    
                    if ($reccord['admin'] == 1) {
                        $is_admin = ' style="display:none;"';
                        $admin_checked = ' checked';
                        $admin_disabled = ' disabled="disabled"';
                    } else {
                        $is_admin = '';
                        $admin_checked = '';
                        $admin_disabled = '';
                    }
                    
                    if ($_SESSION['user_manager'] == 1) $is_manager = 'disabled="disabled"';
                    else $is_manager = '';
                    
                    if ($reccord['gestionnaire'] == 1) $is_gest = 'checked';
                    else $is_gest = '';
                    
                    if ($showUserFolders == false) {
                        $show_folders = 'display:none;';
                        $show_folders_disabled = 'disabled="disabled"';
                        $user_action = 'src="includes/images/user--minus_disabled.png"';
                        $pw_change = 'src="includes/images/lock__pencil_disabled.png"';
                        $log_report = 'src="includes/images/report.png" onclick="user_action_log_items(\''.$reccord['id'].'\')" class="button" style="padding:2px; cursor:pointer;" title="'.$LANG['see_logs'].'"';
                    } else {
                        $show_folders = '';
                        $show_folders_disabled = '';
                        $user_action = '<i class="fa fa-user fa-lg tip" style="cursor:pointer;" onclick="'.$actionOnUser.'" title="'.$userTxt.'">';
                        $pw_change = '<i class="fa fa-key fa-lg tip" style="cursor:pointer;" onclick="mdp_user(\''.$reccord['id'].'\')" title="'.$LANG['change_password'].'"></i>';
                        $log_report = '<i class="fa fa-newspaper-o fa-lg tip" onclick="user_action_log_items(\''.$reccord['id'].'\')" style="cursor:pointer;" title="'.$LANG['see_logs'].'"></i>';
                    }
                                        
                    if ($_SESSION['user_manager'] == 1 || $reccord['admin'] == 1) $tmp1 = 'disabled="disabled"';
                    else $tmp1 = '';
                    
                    if ($reccord['read_only'] == 1) $is_ro = 'checked';
                    else $is_ro = '';            

                    if ($reccord['can_create_root_folder'] == 1) $can_create_root_folder = 'checked';
                    else $can_create_root_folder = '';
                    
                    if ($reccord['personal_folder'] == 1) $personal_folder = 'checked';
                    else $personal_folder = '';
                    
                    if (empty($reccord['email'])) $email_change = 'mail--exclamation.png';
                    else $email_change = 'mail--pencil.png';
                    
                    if (empty($reccord['ga'])) $ga_code = 'phone_add';
                    else $ga_code = 'phone_sound' ;
                    // -->
                    
                    $html .= '
                            <tr'.$row_style.'>
                                <td align="center">'.$show_edit.$reccord['id'].'</td>
                                <td align="center">';
                    if ($reccord['disabled'] == 1) $html .= '
                                    <i class="fa fa-warning fa-lg mi-red tip" style="padding:2px;cursor:pointer;" onclick="unlock_user(\''.$reccord['id'].'\')" title="'.$LANG['unlock_user'].'"></i>';
                    $html .= '
                                </td>
                                <td align="center">
                                    <p '.$td_class.' id="login_'.$reccord['id'].'">'.$reccord['login'].'</p>
                                </td>
                                <td align="center">
                                    <p '.$td_class.' id="name_'.$reccord['id'].'">'.@$reccord['name'].'</p>
                                </td>
                                <td align="center">
                                    <p'.$td_class.' id="lastname_'.$reccord['id'].'">'.@$reccord['lastname'].'</p>
                                </td>
                                <td align="center">
                                    <div '.$is_admin.'>
                                        <div id="list_adminby_'.$reccord['id'].'" style="text-align:center;">
                                        </div>
                                        <div style="text-align:center;">
                                            <i class="fa fa-edit fa-lg tip" style="cursor:pointer;" onclick="ChangeUSerAdminBy(\''.$reccord['id'].'\')"></i>
                                        </div>'. '
                                    </div>

                                </td>
                                <td>
                                    <div '.$is_admin.'>';
                                    if ($listAlloFcts_position == false)
                                        $html .= '
                                        <div style="text-align:center;'.$show_folders.'">
                                            '.$listAlloFcts.'&nbsp;&nbsp;<i class="fa fa-edit fa-lg tip" style="cursor:pointer;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'functions\')" title="'.$LANG['change_function'].'"></i>
                                        </div>';
                                    else
                                        $html .= '
                                        <div id="list_function_user_'.$reccord['id'].'" style="text-align:center;">
                                            '.$listAlloFcts.'
                                        </div>
                                        <div style="text-align:center; padding-top:2px;'.$show_folders.'">
                                            <i class="fa fa-edit fa-lg tip" style="cursor:pointer;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'functions\')" title="'.$LANG['change_function'].'"></i>
                                        </div>';
                                $html .= '
                                    </div>
                                </td>
                                <td>
                                    <div'.$is_admin.'>
                                        <div id="list_autgroups_user_'.$reccord['id'].'" style="text-align:center;">'
                    .$listAlloGrps.'
                                        </div>
                                        <div style="text-align:center;'.$show_folders.'">
                                            <i class="fa fa-edit fa-lg tip" style="cursor:pointer;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'autgroups\')" title="'.$LANG['change_authorized_groups'].'"></i>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div'.$is_admin.'>
                                        <div id="list_forgroups_user_'.$reccord['id'].'" style="text-align:center;">'
                    .$listForbGrps.'
                                        </div>
                                        <div style="text-align:center;'.$show_folders.'">
                                            <i class="fa fa-edit fa-lg tip" style="cursor:pointer;" onclick="Open_Div_Change(\''.$reccord['id'].'\',\'forgroups\')" title="'.$LANG['change_forbidden_groups'].'"></i>
                                        </div>
                                    </div>
                                </td>
                                <td align="center">
                                    <input type="checkbox" id="admin_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'admin\')"'.$admin_checked.' '.$is_manager.' />
                                </td>
                                <td align="center">
                                    <input type="checkbox" id="gestionnaire_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'gestionnaire\')"'.$is_gest.' '.$tmp1.' />
                                </td>';
                    // Read Only privilege
                    $html .= '
                                <td align="center">
                                    <input type="checkbox" id="read_only_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'read_only\')"'.$is_ro.' '.$show_folders_disabled.' />
                                </td>';
                    // Can create at root
                    $html .= '
                                <td align="center">
                                    <input type="checkbox" id="can_create_root_folder_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'can_create_root_folder\')"'.$can_create_root_folder.''.$admin_disabled.' />
                                </td>';
                    if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) {
                    $html .= '
                                <td align="center">
                                    <input type="checkbox" id="personal_folder_'.$reccord['id'].'" onchange="ChangeUserParm(\''.$reccord['id'].'\',\'personal_folder\')"'.$personal_folder. ''.$admin_disabled.' />
                                </td>';
                    }

                    $html .= '
                                <td align="center">
                                    '.$actionOnUser.'
                                </td>
                                <td align="center">
                                    '.$pw_change.'
                                </td>
                                <td align="center">
                                    &nbsp;';
                    if (empty($reccord['email'])) {
                        $html .= '<i class="fa fa-exclamation fa-lg mi-yellow tip"></i>&nbsp;<i class="fa fa-envelope mi-yellow tip" style="cursor:pointer;" onclick="mail_user(\''.$reccord['id'].'\',\''.addslashes($reccord['email']).'\')" title="'.$LANG['email'].'"></i>';
                    } else {
                        $html .= '<i class="fa fa-envelope-o fa-lg tip" style="cursor:pointer;" onclick="mail_user(\''.$reccord['id'].'\',\''.addslashes($reccord['email']).'\')" title="'.$reccord['email'].'"></i>';
                    }
                    $html .= '
                                </td>';
                    // Log reports
                    $html .= '
                                <td style="text-align:center;">
                                    '.$log_report.'
                                </td>';
                    // GA code
                    if (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1) {
                        $html .= '
                                <td style="text-align:center;">
                                    <i class="fa fa-qrcode fa-lg tip" style="cursor:pointer;" onclick="user_action_ga_code(\''.$reccord['id'].'\')" title="'.$LANG['user_ga_code'].'"></i>
                                </td>';
                    }
                    // end
                    $html .= '
                            </tr>';
                    $x++;
                }
            }
            
            // check if end
            $next_start = intval($_POST['from']) + intval($_POST['nb']);
            DB::query("SELECT * FROM ".prefix_table("users"));
            if ($next_start > DB::count()) {
                $end_is_reached = 1;
            } else {
                $end_is_reached = 0;
            }
            
            //echo $html;
            
            echo prepareExchangedData(
                array(
                    "html" => $html,
                    "error" => "",
                    "from" => $next_start,
                    "end_reached" => $end_is_reached
                ),
                "encode"
            );
            
            break;

        /**
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case "user_edit_login":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'login' => $_POST['login'],
                    'name' => $_POST['name'],
                    'lastname' => $_POST['lastname']
                ),
                "id = %i",
                $_POST['id']
            );
            break;
    }
}
// # NEW LOGIN FOR USER HAS BEEN DEFINED ##
elseif (!empty($_POST['newValue'])) {
    $value = explode('_', $_POST['id']);
    DB::update(
        prefix_table("users"),
        array(
            $value[0] => $_POST['newValue']
           ),
        "id = %i",
        $value[1]
    );
    // update LOG
    DB::insert(
        prefix_table("log_system"),
        array(
            'type' => 'user_mngt',
            'date' => time(),
            'label' => 'at_user_new_'.$value[0].':'.$value[1],
            'qui' => $_SESSION['user_id'],
            'field_1' => $_POST['id']
           )
    );
    // Display info
    echo $_POST['newValue'];
}
// # ADMIN FOR USER HAS BEEN DEFINED ##
elseif (isset($_POST['newadmin'])) {
    $id = explode('_', $_POST['id']);
    DB::update(
        prefix_table("users"),
        array(
            'admin' => $_POST['newadmin']
           ),
        "id = %i",
        $id[1]
    );
    // Display info
    if ($_POST['newadmin'] == "1") {
        echo "Oui";
    } else {
        echo "Non";
    }
}
