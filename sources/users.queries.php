<?php
/**
 *
 * @file          users.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link        http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_users")) {
    if (!isset($_POST['newValue'])) {
        $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
        include $_SESSION['settings']['cpassman_dir'].'/error.php';
        exit();
    } else {
        $filtered_newvalue = filter_var($_POST['newValue'], FILTER_SANITIZE_STRING);
        if (empty($filtered_newvalue)) {
            $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
            include $_SESSION['settings']['cpassman_dir'].'/error.php';
            exit();
        }
    }
}

include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
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
            // ensure no double ; exists
            $new_fonctions = str_replace(";;", ";", $new_fonctions);
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
            $login = noHTML(htmlspecialchars_decode($dataReceived['login']));
            $name = noHTML(htmlspecialchars_decode($dataReceived['name']));
            $lastname = noHTML(htmlspecialchars_decode($dataReceived['lastname']));
            $pw = htmlspecialchars_decode($dataReceived['pw']);

            // Empty user
            if (mysqli_escape_string($link, htmlspecialchars_decode($login)) == "") {
                echo '[ { "error" : "'.addslashes($LANG['error_empty_data']).'" } ]';
                break;
            }
            // Check if user already exists
            $data = DB::query(
                "SELECT id, fonction_id, groupes_interdits, groupes_visibles FROM ".prefix_table("users")."
                WHERE login = %s",
                mysqli_escape_string($link, stripslashes($login))
            );

            if (DB::count() == 0) {
                // check if admin role is set. If yes then check if originator is allowed
                if ($dataReceived['admin'] === "true" && $_SESSION['user_admin'] !== "1") {
                    echo '[ { "error" : "'.addslashes($LANG['error_not_allowed_to']).'" } ]';
                    break;
                }

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
                        'fonction_id' => $dataReceived['groups'],
                        'groupes_interdits' => $dataReceived['forbidden_flds'],
                        'groupes_visibles' => $dataReceived['allowed_flds'],
                        'isAdministratedByRole' => $dataReceived['isAdministratedByRole'],
                        'encrypted_psk' => ''
                       )
                );
                $new_user_id = DB::insertId();
                // Create personnal folder
                if ($dataReceived['personal_folder'] === "true") {
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
                    $tree->rebuild();
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
                logEvents('user_mngt', 'at_user_added', $_SESSION['user_id'], $_SESSION['login'], $new_user_id);

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
        logEvents('user_mngt', 'at_user_deleted', $_SESSION['user_id'], $_SESSION['login'], $_POST['id']);
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
        logEvents('user_mngt', 'at_user_locked', $_SESSION['user_id'], $_SESSION['login'], $_POST['id']);
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
        logEvents('user_mngt', 'at_user_email_changed:'.$data['email'], intval($_SESSION['user_id']), $_SESSION['login'], intval($_POST['id']));
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
            echo prepareExchangedData(array("error" => ""), "encode");
            break;
        /**
         * UPDATE ADMIN RIGHTS FOR USER
         */
        case "admin":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['is_admin'] != 1) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                exit();
            }

            DB::update(
                prefix_table("users"),
                array(
                    'admin' => $_POST['value'],
                    'gestionnaire' => $_POST['value'] == 1 ? "0" : "0",
                    'read_only' => $_POST['value'] == 1 ? "0" : "0"
                   ),
                "id = %i",
                $_POST['id']
            );

            echo prepareExchangedData(array("error" => ""), "encode");
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

            // Get some data
            $data = DB::queryfirstrow(
                "SELECT can_manage_all_users, gestionnaire FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            DB::update(
                prefix_table("users"),
                array(
                    'gestionnaire' => $_POST['value'],
                    'can_manage_all_users' => ($data['can_manage_all_users'] == 0 && $_POST['value'] == 1) ? "0" : (
                        ($data['can_manage_all_users'] == 0 && $_POST['value'] == 0) ? "0" : (
                        ($data['can_manage_all_users'] == 1 && $_POST['value'] == 0) ? "0" :
                        "1")
                    ),
                    'admin' => $_POST['value'] == 1 ? "0" : "0",
                    'read_only' => $_POST['value'] == 1 ? "0" : "0"
                   ),
                "id = ".$_POST['id']
            );
            echo prepareExchangedData(array("error" => ""), "encode");
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
                    'read_only' => $_POST['value'],
                    'gestionnaire' => $_POST['value'] == 1 ? "0" : "0",
                    'admin' => $_POST['value'] == 1 ? "0" : "0"
                   ),
                "id = %i",
                $_POST['id']
            );
            echo prepareExchangedData(array("error" => ""), "encode");
            break;
        /**
         * UPDATE CAN MANAGE ALL USERS RIGHTS FOR USER
         * Notice that this role must be also Manager
         */
        case "can_manage_all_users":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            // Get some data
            $data = DB::queryfirstrow(
                "SELECT admin, gestionnaire FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            DB::update(
                prefix_table("users"),
                array(
                    'can_manage_all_users' => $_POST['value'],
                    'gestionnaire' => ($data['gestionnaire'] == 0 && $_POST['value'] == 1) ? "1" : (($data['gestionnaire'] == 1 && $_POST['value'] == 1) ? "1" : (($data['gestionnaire'] == 1 && $_POST['value'] == 0) ? "1" : "0")),
                    'admin' => $_POST['value'] == 1 ? "0" : "0",
                    'read_only' => $_POST['value'] == 1 ? "0" : "0"
                   ),
                "id = ".$_POST['id']
            );
            echo prepareExchangedData(array("error" => ""), "encode");
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
            echo prepareExchangedData(array("error" => ""), "encode");
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
                $text = '<span style=\"text-align:center\"><i class=\"fa fa-warning mi-red\" ></i>&nbsp;</span>';
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
        logEvents('user_mngt', 'at_user_unlocked', $_SESSION['user_id'], $_SESSION['login'], $_POST['id']);
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

            if ($_POST['scope'] === "user_activity") {
                if (isset($_POST['filter']) && !empty($_POST['filter']) && $_POST['filter'] != "all") {
                    $sql_filter = " AND l.action = '".$_POST['filter']."'";
                }
                // get number of pages
                DB::query(
                    "SELECT *
                    FROM ".prefix_table("log_items")." as l
                    INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
                    INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i ".$sql_filter,
                    intval($_POST['id'])
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
                    WHERE l.id_user = %i ".$sql_filter."
                    ORDER BY date DESC
                    LIMIT ".intval($start).",".intval($_POST['nb_items_by_page']),
                    intval($_POST['id'])
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
                    LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($_POST['nb_items_by_page'], FILTER_SANITIZE_NUMBER_INT)),
                    "user_mngt",
                    $_POST['id']
                );
            }
            // generate data
            if (isset($counter) && $counter != 0) {
                $nb_pages = ceil($counter / $_POST['nb_items_by_page']);
                for ($i = 1; $i <= $nb_pages; $i++) {
                    $pages .= '<td onclick=\'displayLogs('.$i.',\"'.$_POST['scope'].'\")\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
                }
            }
            $pages .= '</tr></table>';
            if (isset($rows)) {
                foreach ($rows as $record) {
                    if ($_POST['scope'] == "user_mngt") {
                        $user = DB::queryfirstrow("SELECT login from ".prefix_table("users")." WHERE id=%i", $record['qui']);
                        $user_1 = DB::queryfirstrow("SELECT login from ".prefix_table("users")." WHERE id=%i", $_POST['id']);
                        $tmp = explode(":", $record['label']);
                        // extract action done
                        $label = "";
                        if ($tmp[0] == "at_user_initial_pwd_changed") {
                            $label = $LANG['log_user_initial_pwd_changed'];
                        } else if ($tmp[0] == "at_user_email_changed") {
                            $label = $LANG['log_user_email_changed'].$tmp[1];
                        } else if ($tmp[0] == "at_user_added") {
                            $label = $LANG['log_user_created'];
                        } else if ($tmp[0] == "at_user_locked") {
                            $label = $LANG['log_user_locked'];
                        } else if ($tmp[0] == "at_user_unlocked") {
                            $label = $LANG['log_user_unlocked'];
                        } else if ($tmp[0] == "at_user_pwd_changed") {
                            $label = $LANG['log_user_pwd_changed'];
                        }
                        // prepare log
                        $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'</td><td align=\"center\">'.$label.'</td><td align=\"center\">'.$user['login'].'</td><td align=\"center\"></td></tr>';
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
         * Get user info
         */
        case "get_user_info":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            $arrData = array();
            $arrFunction = array();
            $arrMngBy = array();
            $arrFldForbidden = array();
            $arrFldAllowed = array();

            //Build tree
            $tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

            // get User info
            $rowUser = DB::queryFirstRow(
                "SELECT login, name, lastname, email, disabled, fonction_id, groupes_interdits, groupes_visibles, isAdministratedByRole, gestionnaire, read_only, can_create_root_folder, personal_folder, can_manage_all_users, admin
                FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            // get FUNCTIONS
            $functionsList = "";
            $users_functions = explode(';', $rowUser['fonction_id']);
            // array of roles for actual user
            $my_functions = explode(';', $_SESSION['fonction_id']);

            $rows = DB::query("SELECT id,title,creator_id FROM ".prefix_table("roles_title"));
            foreach ($rows as $record) {
                if ($_SESSION['is_admin'] == 1  || ($_SESSION['user_manager'] == 1 && (in_array($record['id'], $my_functions) || $record['creator_id'] == $_SESSION['user_id']))) {
                    if (in_array($record['id'], $users_functions)) {
                        $tmp = ' selected="selected"';

                        //
                        array_push(
                            $arrFunction,
                            array(
                                'title' => $record['title'],
                                'id' => $record['id']
                            )
                        );
                    } else {
                        $tmp = "";
                    }
                    $functionsList .= '<option value="'.$record['id'].'" class="folder_rights_role"'.$tmp.'>'.$record['title'].'</option>';
                }
            }

            // get MANAGEDBY
            $rolesList = array();
            $rows = DB::query("SELECT id,title FROM ".prefix_table("roles_title")." ORDER BY title ASC");
            foreach ($rows as $reccord) {
                $rolesList[$reccord['id']] = array('id' => $reccord['id'], 'title' => $reccord['title']);
            }
            $managedBy = '<option value="0">'.$LANG['administrators_only'].'</option>';
            foreach ($rolesList as $fonction) {
                if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
                    if ($rowUser['isAdministratedByRole'] == $fonction['id']) {
                        $tmp = ' selected="selected"';

                        //
                        array_push(
                            $arrMngBy,
                            array(
                                'title' => $fonction['title'],
                                'id' => $fonction['id']
                            )
                        );
                    } else {
                        $tmp = "";
                    }
                    $managedBy .= '<option value="'.$fonction['id'].'"'.$tmp.'>'.$LANG['managers_of'].' '.$fonction['title'].'</option>';
                }
            }

            if (count($arrMngBy) === 0) {
                array_push(
                    $arrMngBy,
                    array(
                        'title' => $LANG['administrators_only'],
                        'id' => "0"
                    )
                );
            }

            // get FOLDERS FORBIDDEN
            $forbiddenFolders = "";
            $userForbidFolders = explode(';', $rowUser['groupes_interdits']);
            $tree_desc = $tree->getDescendants();
            foreach ($tree_desc as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
                    $tmp = "";
                    $ident = "";
                    for ($y = 1;$y < $t->nlevel;$y++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    if (in_array($t->id, $userForbidFolders)) {
                        $tmp = ' selected="selected"';

                        //
                        array_push(
                            $arrFldForbidden,
                            array(
                                'title' => htmlspecialchars($t->title, ENT_COMPAT, "UTF-8"),
                                'id' => $t->id
                            )
                        );
                    }
                    $forbiddenFolders .= '<option value="'.$t->id.'"'.$tmp.'>'.$ident.@htmlspecialchars($t->title, ENT_COMPAT, "UTF-8").'</option>';

                    $prev_level = $t->nlevel;
                }
            }

            // get FOLDERS ALLOWED
            $allowedFolders = "";
            $userAllowFolders = explode(';', $rowUser['groupes_visibles']);
            $tree_desc = $tree->getDescendants();
            foreach ($tree_desc as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
                    $tmp = "";
                    $ident = "";
                    for ($y = 1; $y < $t->nlevel; $y++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    if (in_array($t->id, $userAllowFolders)) {
                        $tmp = ' selected="selected"';

                        //
                        array_push(
                            $arrFldAllowed,
                            array(
                                'title' => htmlspecialchars($t->title, ENT_COMPAT, "UTF-8"),
                                'id' => $t->id
                            )
                        );
                    }
                    $allowedFolders .= '<option value="'.$t->id.'"'.$tmp.'>'.$ident.@htmlspecialchars($t->title, ENT_COMPAT, "UTF-8").'</option>';

                    $prev_level = $t->nlevel;
                }
            }

            // get USER STATUS
            if ($rowUser['disabled'] == 1) {
                $arrData['info'] = $LANG['user_info_locked'].'<br /><input type="checkbox" value="unlock" name="1" class="chk">&nbsp;<label for="1">'.$LANG['user_info_unlock_question'].'</label><br /><input type="checkbox"  value="delete" id="account_delete" class="chk" name="2" onclick="confirmDeletion()">&nbsp;<label for="2">'.$LANG['user_info_delete_question']."</label>";
            } else {
                $arrData['info'] = $LANG['user_info_active'].'<br /><input type="checkbox" value="lock" class="chk">&nbsp;'.$LANG['user_info_lock_question'];
            }

            $arrData['error'] = "no";
            $arrData['log'] = $rowUser['login'];
            $arrData['name'] = $rowUser['name'];
            $arrData['lastname'] = $rowUser['lastname'];
            $arrData['email'] = $rowUser['email'];
            $arrData['function'] = $functionsList;
            $arrData['managedby'] = $managedBy;
            $arrData['foldersForbid'] = $forbiddenFolders;
            $arrData['foldersAllow'] = $allowedFolders;//print_r($arrMngBy);
            $arrData['share_function'] = json_encode($arrFunction, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            $arrData['share_managedby'] = json_encode($arrMngBy, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            $arrData['share_forbidden'] = json_encode($arrFldForbidden, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            $arrData['share_allowed'] = json_encode($arrFldAllowed, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            $arrData['gestionnaire'] = $rowUser['gestionnaire'];
            $arrData['read_only'] = $rowUser['read_only'];
            $arrData['can_create_root_folder'] = $rowUser['can_create_root_folder'];
            $arrData['personal_folder'] = $rowUser['personal_folder'];
            $arrData['can_manage_all_users'] = $rowUser['can_manage_all_users'];
            $arrData['admin'] = $rowUser['admin'];

            $return_values = json_encode($arrData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            echo $return_values;

            break;

        /**
         * EDIT user
         */
        case "store_user_changes":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            // Empty user
            if (mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['login'])) == "") {
                echo '[ { "error" : "'.addslashes($LANG['error_empty_data']).'" } ]';
                break;
            }

            $account_status_action = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['action_on_user']));

            // delete account
            // delete user in database
            if ($account_status_action === "delete") {
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
                logEvents('user_mngt', 'at_user_deleted', $_SESSION['user_id'], $_SESSION['login'], $_POST['id']);

            } else {

                // Get old data about user
                $oldData = DB::queryfirstrow(
                    "SELECT * FROM ".prefix_table("users")."
                    WHERE id = %i",
                    $_POST['id']
                );

                // manage account status
                $accountDisabled = 0;
                if ($account_status_action == "unlock") {
                    $accountDisabled = 0;
                    $logDisabledText = "at_user_unlocked";
                } elseif ($account_status_action == "lock") {
                    $accountDisabled = 1;
                    $logDisabledText = "at_user_locked";
                }

                // update user
                DB::update(
                    prefix_table("users"),
                    array(
                        'login' => mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['login'])),
                        'name' => mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['name'])),
                        'lastname' => mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['lastname'])),
                        'email' => mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['email'])),
                        'disabled' => $accountDisabled,
                        'isAdministratedByRole' => $dataReceived['managedby'],
                        'groupes_interdits' => empty($dataReceived['forbidFld']) ? '0' : rtrim($dataReceived['forbidFld'], ";"),
                        'groupes_visibles' => empty($dataReceived['allowFld']) ? '0' : rtrim($dataReceived['allowFld'], ";"),
                        'fonction_id' => empty($dataReceived['functions']) ? '0' : rtrim($dataReceived['functions'], ";"),
                       ),
                    "id = %i",
                    $_POST['id']
                );

                // update SESSION
                if ($_SESSION['user_id'] === $_POST['id']) {
                    $_SESSION['user_email'] = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['email']));
                    $_SESSION['name'] = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['name']));
                    $_SESSION['lastname'] = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['lastname']));
                }

                // update LOG
                if ($oldData['email'] != mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['email']))) {
                    logEvents('user_mngt', 'at_user_email_changed:'.$oldData['email'], intval($_SESSION['user_id']), $_SESSION['login'], intval($_POST['id']));
                }

                if ($oldData['disabled'] != $accountDisabled) {
                    // update LOG
                    logEvents('user_mngt', $logDisabledText, $_SESSION['user_id'], $_SESSION['login'], $_POST['id']);
                }

    /*
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
        logEvents('user_mngt', 'at_user_unlocked', $_SESSION['user_id'], $_SESSION['login'], $_POST['id']);
                */
            }

            echo '[ { "error" : "no" } ]';
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

        /**
         * IS LOGIN AVAILABLE?
         */
        case "is_login_available":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            DB::queryfirstrow(
                "SELECT * FROM ".prefix_table("users")."
                WHERE login = %s",
                mysqli_escape_string($link, htmlspecialchars_decode($_POST['login']))
            );

            echo '[ { "error" : "" , "exists" : "'.DB::count().'"} ]';

            break;

        /**
         * GET USER FOLDER RIGHT
         */
        case "user_folders_rights":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }
            $arrData = array();

            //Build tree
            $tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

            // get User info
            $rowUser = DB::queryFirstRow(
                "SELECT login, name, lastname, email, disabled, fonction_id, groupes_interdits, groupes_visibles, isAdministratedByRole, avatar_thumb
                FROM ".prefix_table("users")."
                WHERE id = %i",
                $_POST['id']
            );

            // get rights
            $functionsList = "";
            $arrFolders = [];
            $html = '<div style="padding:5px; margin-bottom:10px; height:40px;" class="ui-state-focus ui-corner-all">';
            if (!empty($rowUser['avatar_thumb'])) {
                $html .= '<div style="float:left; margin-right:30px;"><img src="includes/avatars/'.$rowUser['avatar_thumb'].'"></div>';
            }
            $html .= '<div style="float:left;font-size:20px; margin-top:8px; text-align:center;">'.$rowUser['name'].' '.$rowUser['lastname'].' ['.$rowUser['login'].']</div></div><table>';

            $arrData['functions'] = array_filter(explode(';', $rowUser['fonction_id']));
            $arrData['allowed_folders'] = array_filter(explode(';', $rowUser['groupes_visibles']));
            $arrData['denied_folders'] = array_filter(explode(';', $rowUser['groupes_interdits']));

            // refine folders based upon roles
            $rows = DB::query(
                "SELECT folder_id, type
                FROM ".prefix_table("roles_values")."
                WHERE role_id IN %ls
                ORDER BY folder_id ASC",
                $arrData['functions']
            );
            foreach ($rows as $record) {
                $bFound = false;
                $x = 0;
                foreach($arrFolders as $fld) {
                    if ($fld['id'] === $record['folder_id']) {
                        // get the level of access on the folder
                        $arrFolders[$x]['type'] = evaluate_folder_acces_level($record['type'], $arrFolders[$x]['type']);
                        $bFound = true;
                        break;
                    }
                    $x++;
                }
                if ($bFound === false && !in_array($record['folder_id'], $arrData['denied_folders'])) {
                    array_push($arrFolders, array("id" => $record['folder_id'] , "type" => $record['type']));
                }
            }

            $tree_desc = $tree->getDescendants();
            foreach ($tree_desc as $t) {
                foreach($arrFolders as $fld) {
                    if ($fld['id'] === $t->id) {
                        // get folder name
                        $row = DB::queryFirstRow(
                            "SELECT title, nlevel
                            FROM ".prefix_table("nested_tree")."
                            WHERE id = %i",
                            $fld['id']
                        );

                        // manage indentation
                        $ident = '';
                        for ($y = 1; $y < $row['nlevel']; $y++) {
                            $ident .= '<i class="fa fa-sm fa-caret-right"></i>&nbsp;';
                        }

                        // manage right icon
                        if ($fld['type'] == "W") {
                            $color = '#008000';
                            $allowed = "W";
                            $title = $LANG['write'];
                            $label = '
                            <span class="fa-stack" title="'.$LANG['write'].'" style="color:#008000;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-indent fa-stack-1x"></i>
                            </span>
                            <span class="fa-stack" title="'.$LANG['write'].'" style="color:#008000;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-edit fa-stack-1x"></i>
                            </span>
                            <span class="fa-stack" title="'.$LANG['write'].'" style="color:#008000;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-eraser fa-stack-1x"></i>
                            </span>';
                        } elseif ($fld['type'] == "ND") {
                            $color = '#4E45F7';
                            $allowed = "ND";
                            $title = $LANG['no_delete'];
                            $label = '
                            <span class="fa-stack" title="'.$LANG['no_delete'].'" style="color:#4E45F7;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-indent fa-stack-1x"></i>
                            </span>
                            <span class="fa-stack" title="'.$LANG['no_delete'].'" style="color:#4E45F7;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-edit fa-stack-1x"></i>
                            </span>';
                        } elseif ($fld['type'] == "NE") {
                            $color = '#4E45F7';
                            $allowed = "NE";
                            $title = $LANG['no_edit'];
                            $label = '
                            <span class="fa-stack" title="'.$LANG['no_edit'].'" style="color:#4E45F7;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-indent fa-stack-1x"></i>
                            </span>
                            <span class="fa-stack" title="'.$LANG['no_edit'].'" style="color:#4E45F7;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-eraser fa-stack-1x"></i>
                            </span>';
                        } elseif ($fld['type'] == "NDNE") {
                            $color = '#4E45F7';
                            $allowed = "NDNE";
                            $title = $LANG['no_edit_no_delete'];
                            $label = '
                            <span class="fa-stack" title="'.$LANG['no_edit_no_delete'].'" style="color:#4E45F7;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-indent fa-stack-1x"></i>
                            </span>';
                        } else {
                            $color = '#FEBC11';
                            $allowed = "R";
                            $title = $LANG['read'];
                            $label = '
                            <span class="fa-stack" title="'.$LANG['read'].'" style="color:#ff9000;">
                                <i class="fa fa-square-o fa-stack-2x"></i>
                                <i class="fa fa-eye fa-stack-1x"></i>
                            </span>';
                        }

                        $html .= '<tr><td>'.$ident.$row['title'].'</td><td>'.$label."</td></tr>";
                        break;
                    }
                }
            }

            $html .= '</table><div style="margin-top:15px; padding:3px;" class="ui-widget-content ui-state-default ui-corner-all"><span class="fa fa-info"></span>&nbsp;'.$LANG['folders_not_visible_are_not_displayed'].'</div>';

            $return_values = prepareExchangedData(
                array(
                    'html' => $html,
                    'error' => '',
                    'login' => $rowUser['login']
                ),
                "encode"
            );
            echo $return_values;
            break;

        /**
         * GET LIST OF USERS
         */
        case "get_list_of_users_for_sharing":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            $list_users_from = '';
            $list_users_to = '';

            if (!$_SESSION['is_admin'] && !$_SESSION['user_can_manage_all_users']) {
                $rows = DB::query(
                    "SELECT id, login, name, lastname, gestionnaire, read_only, can_manage_all_users
                    FROM ".prefix_table("users")."
                    WHERE admin = %i AND isAdministratedByRole IN %ls",
                    "0",
                    array_filter($_SESSION['user_roles'])
                );
            } else {
                $rows = DB::query(
                    "SELECT id, login, name, lastname, gestionnaire, read_only, can_manage_all_users
                    FROM ".prefix_table("users")."
                    WHERE admin = %i",
                    "0"
                );
            }

            foreach ($rows as $record) {
                $list_users_from .= '<option id="share_from-'.$record['id'].'">'.$record['name'].' '.$record['lastname'].' ['.$record['login'].']</option>';
                $list_users_to .= '<option id="share_to-'.$record['id'].'">'.$record['name'].' '.$record['lastname'].' ['.$record['login'].']</option>';
            }

            $return_values = prepareExchangedData(
                array(
                    'users_list_from' => $list_users_from,
                    'users_list_to' => $list_users_to,
                    'error' => ''
                ),
                "encode"
            );
            echo $return_values;

            break;

        /**
         * UPDATE USERS RIGHTS BY SHARING
         */
        case "update_users_rights_sharing":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            // Check send values
            if (empty($_POST['source_id']) || empty($_POST['destination_ids'])) {
                // error
                exit();
            }

            // manage other rights
            $user_other_rights = explode(';', $_POST['user_otherrights']); //gestionnaire;read_only;can_create_root_folder;personal_folder;can_manage_all_users;admin

            foreach (explode(';', $_POST['destination_ids']) as $dest_user_id) {
                // update user
                DB::update(
                    prefix_table("users"),
                    array(
                        'fonction_id' => $_POST['user_functions'],
                        'isAdministratedByRole' => $_POST['user_managedby'],
                        'groupes_visibles' => $_POST['user_fldallowed'],
                        'groupes_interdits' => $_POST['user_fldforbid'],
                        'gestionnaire' => $user_other_rights[0],
                        'read_only' => $user_other_rights[1],
                        'can_create_root_folder' => $user_other_rights[2],
                        'personal_folder' => $user_other_rights[3],
                        'can_manage_all_users' => $user_other_rights[4],
                        'admin' => $user_other_rights[5],
                       ),
                    "id = %i",
                    $dest_user_id
                );
            }

/*
            $list_users_from = $list_users_to = '';

            $rows = DB::query(
                "SELECT id, login, name, lastname, gestionnaire, read_only, can_manage_all_users
                FROM ".prefix_table("users")."
                WHERE admin = %i",
                "0"
            );
            foreach ($rows as $record) {
                $list_users_from .= '<option id="share_from-'.$record['id'].'">'.$record['name'].' '.$record['lastname'].' ['.$record['login'].']</option>';
                $list_users_to .= '<option id="share_to-'.$record['id'].'">'.$record['name'].' '.$record['lastname'].' ['.$record['login'].']</option>';
            }

            $return_values = prepareExchangedData(
                array(
                    'users_list_from' => $list_users_from,
                    'users_list_to' => $list_users_to,
                    'error' => ''
                ),
                "encode"
            );
            echo $return_values;
*/
            break;
    }
}
// # NEW LOGIN FOR USER HAS BEEN DEFINED ##
elseif (!empty($_POST['newValue'])) {
    $value = explode('_', $_POST['id']);
    if ($value[0] === "userlanguage") {
        $value[0]  = "user_language";
        $_POST['newValue'] = strtolower($_POST['newValue']);
    }
    DB::update(
        prefix_table("users"),
        array(
            $value[0] => $_POST['newValue']
           ),
        "id = %i",
        $value[1]
    );
    // update LOG
    logEvents('user_mngt', 'at_user_new_'.$value[0].':'.$value[1], $_SESSION['user_id'], $_SESSION['login'], $_POST['id']);
    // refresh SESSION if requested
    if ($value[0] === "treeloadstrategy") {
        $_SESSION['user_settings']['treeloadstrategy'] = $_POST['newValue'];

    } else if ($value[0] === "usertimezone") {
    // special case for usertimezone where session needs to be updated
        $_SESSION['user_settings']['usertimezone'] = $_POST['newValue'];

    } else if ($value[0] === "userlanguage") {
    // special case for user_language where session needs to be updated
        $_SESSION['user_settings']['user_language'] = $_POST['newValue'];
        $_SESSION['user_language'] = $_POST['newValue'];

    } else if ($value[0] === "agses-usercardid") {
    // special case for agsescardid where session needs to be updated
        $_SESSION['user_settings']['agses-usercardid'] = $_POST['newValue'];

    } else if ($value[0] === "email") {
    // store email change in session
        $_SESSION['user_email'] = $_POST['newValue'];
    }
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

function evaluate_folder_acces_level($new_val, $existing_val) {
    $levels = array(
        "W" => 4,
        "ND" => 3,
        "NE" => 3,
        "NDNE" => 2,
        "R" => 1
    );

    if (empty($existing_val)) {
        $current_level_points = 0;
    } else {
        $current_level_points = $levels[$existing_val];
    }
    $new_level_points = $levels[$new_val];

    // check if new is > to current one (always keep the highest level)
    if (
        ($new_val === "ND" && $existing_val === "NE")
        ||
        ($new_val === "NE" && $existing_val === "ND")
    ) {
        return "NDNE";
    } else {
        if ($current_level_points > $new_level_points) {
            return  $existing_val;
        } else {
            return  $new_val;
        }
    }
}