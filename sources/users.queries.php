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
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
$filtered_newvalue = filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_STRING);
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_users")) {
    if (null === $filtered_newvalue) {
        $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
        include $SETTINGS['cpassman_dir'].'/error.php';
        exit();
    } else {
        $filtered_newvalue = filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_STRING);
        // Do special check to allow user to change attributes of his profile
        if (empty($filtered_newvalue) || !checkUser($_SESSION['user_id'], $_SESSION['key'], "profile")) {
            $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
            include $SETTINGS['cpassman_dir'].'/error.php';
            exit();
        }
    }
}


include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

if (null !== filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
    switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
        case "groupes_visibles":
        case "groupes_interdits":
            // Prepare variables
            $post_valeur = filter_input(INPUT_POST, 'valeur', FILTER_SANITIZE_STRING);
            $val = explode(';', $post_valeur);
            $post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);

            // Check if id folder is already stored
            $data = DB::queryfirstrow(
                "SELECT ".$post_type."
                FROM ".prefix_table("users")."
                WHERE id = %i",
                $val[0]
            );
            $new_groupes = $data[$post_type];
            if (empty($data[$post_type]) === false) {
                $groupes = explode(';', $data[$post_type]);
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
                array($post_type => $new_groupes),
                "id = %i",
                $val[0]
            );
            break;
        /**
         * Update a fonction
         */
        case "fonction":
            $val = explode(';', filter_input(INPUT_POST, 'valeur', FILTER_SANITIZE_STRING));

            // Check if ID exists
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
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING),
                "decode"
            );

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
                        'user_language' => $SETTINGS['default_language'],
                        'fonction_id' => $dataReceived['groups'],
                        'groupes_interdits' => $dataReceived['forbidden_flds'],
                        'groupes_visibles' => $dataReceived['allowed_flds'],
                        'isAdministratedByRole' => $dataReceived['isAdministratedByRole'] === "null" ? "0" : $dataReceived['isAdministratedByRole'],
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
                if (empty($SETTINGS['email_server_url'])) {
                    $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
                }
                // Send email to new user
                sendEmail(
                    $LANG['email_subject_new_user'],
                    str_replace(array('#tp_login#', '#tp_pw#', '#tp_link#'), array(" ".addslashes($login), addslashes($pw), $SETTINGS['email_server_url']), $LANG['email_new_user_mail']),
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
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Prepare post variables
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            if (filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) == "delete") {
                // delete user in database
                DB::delete(
                    prefix_table("users"),
                    "id = %i",
                    $post_id
                );
                // delete personal folder and subfolders
                $data = DB::queryfirstrow(
                    "SELECT id FROM ".prefix_table("nested_tree")."
                    WHERE title = %s AND personal_folder = %i",
                    $post_id,
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
                logEvents('user_mngt', 'at_user_deleted', $_SESSION['user_id'], $_SESSION['login'], $post_id);
            } else {
                // lock user in database
                DB::update(
                    prefix_table("users"),
                    array(
                        'disabled' => 1,
                        'key_tempo' => ""
                        ),
                    "id=%i",
                    $post_id
                );
                // update LOG
                logEvents('user_mngt', 'at_user_locked', $_SESSION['user_id'], $_SESSION['login'], $post_id);
            }
            echo '[ { "error" : "no" } ]';
            break;

        /**
         * UPDATE EMAIL OF USER
         */
        case "modif_mail_user":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                // error
                echo '[ { "error" : "yes" } ]';
                break;
            }

            // Prepare post variables
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get old email
            $data = DB::queryfirstrow(
                "SELECT email FROM ".prefix_table("users")."
                WHERE id = %i",
                $post_id
            );

            DB::update(
                prefix_table("users"),
                array(
                    'email' => filter_input(INPUT_POST, 'newemail', FILTER_SANITIZE_STRING)
                    ),
                "id = %i",
                $post_id
            );
            // update LOG
            logEvents(
                'user_mngt',
                'at_user_email_changed:'.$data['email'],
                intval($_SESSION['user_id']),
                filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING),
                $post_id
            );
            echo '[{"error" : "no"}]';
            break;

        /**
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case "can_create_root_folder":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                break;
            }

            DB::update(
                prefix_table("users"),
                array(
                    'can_create_root_folder' => filter_input(INPUT_POST, 'value', FILTER_SANITIZE_STRING)
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            echo prepareExchangedData(array("error" => ""), "encode");
            break;
        /**
         * UPDATE ADMIN RIGHTS FOR USER
         */
        case "admin":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)
                || $_SESSION['is_admin'] !== "1"
            ) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                exit();
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);

            DB::update(
                prefix_table("users"),
                array(
                    'admin' => $post_value,
                    'gestionnaire' => $post_value === 1 ? "0" : "0",
                    'read_only' => $post_value === 1 ? "0" : "0"
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );

            echo prepareExchangedData(array("error" => ""), "encode");
            break;
        /**
         * UPDATE MANAGER RIGHTS FOR USER
         */
        case "gestionnaire":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                break;
            }

            // Get some data
            $data = DB::queryfirstrow(
                "SELECT can_manage_all_users, gestionnaire FROM ".prefix_table("users")."
                WHERE id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);

            DB::update(
                prefix_table("users"),
                array(
                    'gestionnaire' => $post_value,
                    'can_manage_all_users' => ($data['can_manage_all_users'] === "0" && $post_value === "1") ? "0" : (
                        ($data['can_manage_all_users'] === "0" && $post_value === "0") ? "0" : (
                        ($data['can_manage_all_users'] === "1" && $post_value === "0") ? "0" : "1")
                    ),
                    'admin' => $post_value === 1 ? "0" : "0",
                    'read_only' => $post_value === 1 ? "0" : "0"
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            echo prepareExchangedData(array("error" => ""), "encode");
            break;
        /**
         * UPDATE READ ONLY RIGHTS FOR USER
         */
        case "read_only":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);

            DB::update(
                prefix_table("users"),
                array(
                    'read_only' => $post_value,
                    'gestionnaire' => $post_value === 1 ? "0" : "0",
                    'admin' => $post_value === 1 ? 0 : "0"
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            echo prepareExchangedData(array("error" => ""), "encode");
            break;
        /**
         * UPDATE CAN MANAGE ALL USERS RIGHTS FOR USER
         * Notice that this role must be also Manager
         */
        case "can_manage_all_users":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                break;
            }

            // Get some data
            $data = DB::queryfirstrow(
                "SELECT admin, gestionnaire FROM ".prefix_table("users")."
                WHERE id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);

            DB::update(
                prefix_table("users"),
                array(
                    'can_manage_all_users' => $post_value,
                    'gestionnaire' => ($data['gestionnaire'] === "0" && $post_value === 1) ? "1" : (($data['gestionnaire'] === "1" && $post_value === 1) ? "1" : (($data['gestionnaire'] === "1" && $post_value === 0) ? "1" : "0")),
                    'admin' => $post_value === 1 ? "1" : "0",
                    'read_only' => $post_value === 1 ? "1" : "0"
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            echo prepareExchangedData(array("error" => ""), "encode");
            break;
        /**
         * UPDATE PERSONNAL FOLDER FOR USER
         */
        case "personal_folder":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);

            DB::update(
                prefix_table("users"),
                array(
                    'personal_folder' => $post_value === 1 ? "1" : "0"
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            echo prepareExchangedData(array("error" => ""), "encode");
            break;

        /**
         * CHANGE USER FUNCTIONS
         */
        case "open_div_functions":
            $text = "";
            // Refresh list of existing functions
            $data_user = DB::queryfirstrow(
                "SELECT fonction_id FROM ".prefix_table("users")."
                WHERE id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );

            $users_functions = explode(';', $data_user['fonction_id']);
            // array of roles for actual user
            $my_functions = explode(';', $_SESSION['fonction_id']);

            $rows = DB::query("SELECT id,title,creator_id FROM ".prefix_table("roles_title"));
            foreach ($rows as $record) {
                if ($_SESSION['is_admin'] === 1 || ($_SESSION['user_manager'] === 1 && (in_array($record['id'], $my_functions) || $record['creator_id'] === $_SESSION['user_id']))) {
                    $text .= '<input type="checkbox" id="cb_change_function-'.$record['id'].'"';
                    if (in_array($record['id'], $users_functions)) {
                        $text .= ' checked';
                    }
                    $text .= '>&nbsp;'.$record['title'].'<br />';
                }
            }
            // return data
            $return_values = json_encode(array("text" => $text), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            echo $return_values;
            break;
        /**
         * Change user's functions
         */
        case "change_user_functions":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // save data
            DB::update(
                prefix_table("users"),
                array(
                    'fonction_id' => filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING)
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            // display information
            $text = "";
            // Check if POST is empty
            if (empty(filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING)) === false) {
                $rows = DB::query(
                    "SELECT title FROM ".prefix_table("roles_title")." WHERE id IN %ls",
                    explode(";", filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING))
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
        case "open_div_autgroups":
            $text = "";
            // Refresh list of existing functions
            $data_user = DB::queryfirstrow(
                "SELECT groupes_visibles FROM ".prefix_table("users")."
                WHERE id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
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
            $return_values = json_encode(array("text" => $text), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            echo $return_values;
            break;

        /**
         * CHANGE ADMINISTRATED BY
         */
        case "change_user_adminby":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // save data
            DB::update(
                prefix_table("users"),
                array(
                    'isAdministratedByRole' => filter_input(INPUT_POST, 'isAdministratedByRole', FILTER_SANITIZE_STRING)
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'userId', FILTER_SANITIZE_NUMBER_INT)
            );
            echo '[{"done":""}]';
            break;

        /**
         * Change authorized groups
         */
        case "change_user_autgroups":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // save data
            DB::update(
                prefix_table("users"),
                array(
                    'groupes_visibles' => filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING)
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            // display information
            $text = "";
            $val = str_replace(';', ',', filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING));
            // Check if POST is empty
            if (empty(filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING)) === false) {
                $rows = DB::query(
                    "SELECT title,nlevel FROM ".prefix_table("nested_tree")." WHERE id IN %ls",
                    explode(";", filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING))
                );
                foreach ($rows as $record) {
                    $text .= '<i class=\'fa fa-angle-right\'></i>&nbsp;'.$record['title']."<br />";
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
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            DB::update(
                prefix_table("users"),
                array(
                    'disabled' => 0,
                    'no_bad_attempts' => 0
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            // update LOG
            logEvents(
                'user_mngt',
                'at_user_unlocked',
                $_SESSION['user_id'],
                $_SESSION['login'],
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
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
                filter_input(INPUT_POST, 'domain', FILTER_SANITIZE_STRING),
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
                filter_input(INPUT_POST, 'domain', FILTER_SANITIZE_STRING)
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

            // Prepare POST variables
            $post_nb_items_by_page = filter_input(INPUT_POST, 'nb_items_by_page', FILTER_SANITIZE_NUMBER_INT);
            $post_scope = filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING);

            if (filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING) === "user_activity") {
                if (null !== filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING)
                    && !empty(filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING))
                    && filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING) !== "all"
                ) {
                    $sql_filter = " AND l.action = '".filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING)."'";
                }
                // get number of pages
                DB::query(
                    "SELECT *
                    FROM ".prefix_table("log_items")." as l
                    INNER JOIN ".prefix_table("items")." as i ON (l.id_item=i.id)
                    INNER JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i ".$sql_filter,
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                // define query limits
                if (null !== filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)
                    && filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) > 1
                ) {
                    $start = (intval($post_nb_items_by_page)
                        * (intval(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)) - 1)) + 1;
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
                    LIMIT ".intval($start).",".intval($post_nb_items_by_page),
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
            } else {
                // get number of pages
                DB::query(
                    "SELECT *
                    FROM ".prefix_table("log_system")."
                    WHERE type = %s AND field_1=%i",
                    "user_mngt",
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                // define query limits
                if (null !== filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)
                    && filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) > 1
                ) {
                    $start = (intval($post_nb_items_by_page)
                        * (intval(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)) - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = DB::query(
                    "SELECT *
                    FROM ".prefix_table("log_system")."
                    WHERE type = %s AND field_1=%i
                    ORDER BY date DESC
                    LIMIT ".mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)).", ".mysqli_real_escape_string($link, $post_nb_items_by_page),
                    "user_mngt",
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
            }
            // generate data
            if (isset($counter) && $counter != 0) {
                $nb_pages = ceil($counter / intval($post_nb_items_by_page));
                for ($i = 1; $i <= $nb_pages; $i++) {
                    $pages .= '<td onclick=\'displayLogs('.$i.',\"'.$post_scope.'\")\'><span style=\'cursor:pointer;'.(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) === $i ? 'font-weight:bold;font-size:18px;\'>'.$i : '\'>'.$i).'</span></td>';
                }
            }
            $pages .= '</tr></table>';
            if (isset($rows)) {
                foreach ($rows as $record) {
                    if ($post_scope === "user_mngt") {
                        $user = DB::queryfirstrow(
                            "SELECT login
                            from ".prefix_table("users")."
                            WHERE id=%i",
                            $record['qui']
                        );
                        $user_1 = DB::queryfirstrow(
                            "SELECT login
                            from ".prefix_table("users")."
                            WHERE id=%i",
                            filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                        );
                        $tmp = explode(":", $record['label']);
                        // extract action done
                        $label = "";
                        if ($tmp[0] == "at_user_initial_pwd_changed") {
                            $label = $LANG['log_user_initial_pwd_changed'];
                        } elseif ($tmp[0] == "at_user_email_changed") {
                            $label = $LANG['log_user_email_changed'].$tmp[1];
                        } elseif ($tmp[0] == "at_user_added") {
                            $label = $LANG['log_user_created'];
                        } elseif ($tmp[0] == "at_user_locked") {
                            $label = $LANG['log_user_locked'];
                        } elseif ($tmp[0] == "at_user_unlocked") {
                            $label = $LANG['log_user_unlocked'];
                        } elseif ($tmp[0] == "at_user_pwd_changed") {
                            $label = $LANG['log_user_pwd_changed'];
                        }
                        // prepare log
                        $logs .= '<tr><td>'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'</td><td align=\"center\">'.$label.'</td><td align=\"center\">'.$user['login'].'</td><td align=\"center\"></td></tr>';
                    } else {
                        $logs .= '<tr><td>'.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date']).'</td><td align=\"center\">'.str_replace('"', '\"', $record['label']).'</td><td align=\"center\">'.$record['login'].'</td><td align=\"center\">'.$LANG[$record['action']].'</td></tr>';
                    }
                }
            }

            echo '[ { "table_logs": "'.($logs).'", "pages": "'.($pages).'", "error" : "no" } ]';
            break;

        /*
        * Migrate the Admin PF to User
        */
        case "migrate_admin_pf":
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING),
                "decode"
            );
            // Prepare variables
            $user_id = htmlspecialchars_decode($data_received['user_id']);
            $salt_user = htmlspecialchars_decode($data_received['salt_user']);

            if (!isset($_SESSION['user_settings']['clear_psk']) || $_SESSION['user_settings']['clear_psk'] == "") {
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
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
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
                filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT)
            );
            break;

        /**
         * delete the timestamp value for all users
         */
        case "disconnect_all_users":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
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
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $arrData = array();
            $arrFunction = array();
            $arrMngBy = array();
            $arrFldForbidden = array();
            $arrFldAllowed = array();

            //Build tree
            $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

            // get User info
            $rowUser = DB::queryFirstRow(
                "SELECT login, name, lastname, email, disabled, fonction_id, groupes_interdits, groupes_visibles, isAdministratedByRole, gestionnaire, read_only, can_create_root_folder, personal_folder, can_manage_all_users, admin
                FROM ".prefix_table("users")."
                WHERE id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );

            // get FUNCTIONS
            $functionsList = "";
            $users_functions = explode(';', $rowUser['fonction_id']);
            // array of roles for actual user
            $my_functions = explode(';', $_SESSION['fonction_id']);

            $rows = DB::query("SELECT id,title,creator_id FROM ".prefix_table("roles_title"));
            foreach ($rows as $record) {
                if ($_SESSION['is_admin'] == 1 || ($_SESSION['user_manager'] == 1 && (in_array($record['id'], $my_functions) || $record['creator_id'] == $_SESSION['user_id']))) {
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
                    for ($y = 1; $y < $t->nlevel; $y++) {
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
            $arrData['foldersAllow'] = $allowedFolders; //print_r($arrMngBy);
            $arrData['share_function'] = json_encode($arrFunction, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $arrData['share_managedby'] = json_encode($arrMngBy, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $arrData['share_forbidden'] = json_encode($arrFldForbidden, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $arrData['share_allowed'] = json_encode($arrFldAllowed, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $arrData['gestionnaire'] = $rowUser['gestionnaire'];
            $arrData['read_only'] = $rowUser['read_only'];
            $arrData['can_create_root_folder'] = $rowUser['can_create_root_folder'];
            $arrData['personal_folder'] = $rowUser['personal_folder'];
            $arrData['can_manage_all_users'] = $rowUser['can_manage_all_users'];
            $arrData['admin'] = $rowUser['admin'];

            $return_values = json_encode($arrData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            echo $return_values;

            break;

        /**
         * EDIT user
         */
        case "store_user_changes":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING),
                "decode"
            );

            // Empty user
            if (mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['login'])) == "") {
                echo '[ { "error" : "'.addslashes($LANG['error_empty_data']).'" } ]';
                break;
            }

            $account_status_action = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['action_on_user']));

            // Prepare POST variables
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // delete account
            // delete user in database
            if ($account_status_action === "delete") {
                DB::delete(
                    prefix_table("users"),
                    "id = %i",
                    $post_id
                );
                // delete personal folder and subfolders
                $data = DB::queryfirstrow(
                    "SELECT id FROM ".prefix_table("nested_tree")."
                    WHERE title = %s AND personal_folder = %i",
                    $post_id,
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
                logEvents('user_mngt', 'at_user_deleted', $_SESSION['user_id'], $_SESSION['login'], $post_id);
            } else {
                // Get old data about user
                $oldData = DB::queryfirstrow(
                    "SELECT * FROM ".prefix_table("users")."
                    WHERE id = %i",
                    $post_id
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
                    $post_id
                );

                // update SESSION
                if ($_SESSION['user_id'] === $post_id) {
                    $_SESSION['user_email'] = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['email']));
                    $_SESSION['name'] = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['name']));
                    $_SESSION['lastname'] = mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['lastname']));
                }

                // update LOG
                if ($oldData['email'] != mysqli_escape_string($link, htmlspecialchars_decode($dataReceived['email']))) {
                    logEvents('user_mngt', 'at_user_email_changed:'.$oldData['email'], intval($_SESSION['user_id']), $_SESSION['login'], $post_id);
                }

                if ($oldData['disabled'] != $accountDisabled) {
                    // update LOG
                    logEvents('user_mngt', $logDisabledText, $_SESSION['user_id'], $_SESSION['login'], $post_id);
                }
            }

            echo '[ { "error" : "no" } ]';
            break;

        /**
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case "user_edit_login":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            DB::update(
                prefix_table("users"),
                array(
                    'login' => filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING),
                    'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING),
                    'lastname' => filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING)
                ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
            );
            break;

        /**
         * IS LOGIN AVAILABLE?
         */
        case "is_login_available":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            DB::queryfirstrow(
                "SELECT * FROM ".prefix_table("users")."
                WHERE login = %s",
                mysqli_escape_string(
                    $link,
                    htmlspecialchars_decode(filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING))
                )
            );

            echo '[ { "error" : "" , "exists" : "'.DB::count().'"} ]';

            break;

        /**
         * GET USER FOLDER RIGHT
         */
        case "user_folders_rights":
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                break;
            }
            $arrData = array();

            //Build tree
            $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

            // get User info
            $rowUser = DB::queryFirstRow(
                "SELECT login, name, lastname, email, disabled, fonction_id, groupes_interdits, groupes_visibles, isAdministratedByRole, avatar_thumb
                FROM ".prefix_table("users")."
                WHERE id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
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
                foreach ($arrFolders as $fld) {
                    if ($fld['id'] === $record['folder_id']) {
                        // get the level of access on the folder
                        $arrFolders[$x]['type'] = evaluate_folder_acces_level($record['type'], $arrFolders[$x]['type']);
                        $bFound = true;
                        break;
                    }
                    $x++;
                }
                if ($bFound === false && !in_array($record['folder_id'], $arrData['denied_folders'])) {
                    array_push($arrFolders, array("id" => $record['folder_id'], "type" => $record['type']));
                }
            }

            $tree_desc = $tree->getDescendants();
            foreach ($tree_desc as $t) {
                foreach ($arrFolders as $fld) {
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
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array("error" => "not_allowed", "error_text" => addslashes($LANG['error_not_allowed_to'])), "encode");
                break;
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
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Check send values
            if (empty(filter_input(INPUT_POST, 'source_id', FILTER_SANITIZE_NUMBER_INT)) === true
                || empty(filter_input(INPUT_POST, 'destination_ids', FILTER_SANITIZE_NUMBER_INT)) === true
            ) {
                // error
                exit();
            }

            // manage other rights
            /* Possible values: gestionnaire;read_only;can_create_root_folder;personal_folder;can_manage_all_users;admin*/
            $user_other_rights = explode(';', filter_input(INPUT_POST, 'user_otherrights', FILTER_SANITIZE_STRING));

            foreach (explode(';', filter_input(INPUT_POST, 'destination_ids', FILTER_SANITIZE_STRING)) as $dest_user_id) {
                // update user
                DB::update(
                    prefix_table("users"),
                    array(
                        'fonction_id' => filter_input(INPUT_POST, 'user_functions', FILTER_SANITIZE_NUMBER_INT),
                        'isAdministratedByRole' => filter_input(INPUT_POST, 'user_managedby', FILTER_SANITIZE_STRING),
                        'groupes_visibles' => filter_input(INPUT_POST, 'user_fldallowed', FILTER_SANITIZE_STRING),
                        'groupes_interdits' => filter_input(INPUT_POST, 'user_fldforbid', FILTER_SANITIZE_STRING),
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
            break;
    }
// # NEW LOGIN FOR USER HAS BEEN DEFINED ##
} elseif (!empty(filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_STRING))) {
    // Prepare POST variables
    $value = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING));
    $post_newValue = filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_STRING);

    if ($value[0] === "userlanguage") {
        $value[0] = "user_language";
        $post_newValue = strtolower($post_newValue);
    }
    DB::update(
        prefix_table("users"),
        array(
            $value[0] => $post_newValue
            ),
        "id = %i",
        $value[1]
    );
    // update LOG
    logEvents(
        'user_mngt',
        'at_user_new_'.$value[0].':'.$value[1],
        $_SESSION['user_id'],
        $_SESSION['login'],
        filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING)
    );
    // refresh SESSION if requested
    if ($value[0] === "treeloadstrategy") {
        $_SESSION['user_settings']['treeloadstrategy'] = $post_newValue;
    } elseif ($value[0] === "usertimezone") {
    // special case for usertimezone where session needs to be updated
        $_SESSION['user_settings']['usertimezone'] = $post_newValue;
    } elseif ($value[0] === "userlanguage") {
    // special case for user_language where session needs to be updated
        $_SESSION['user_settings']['user_language'] = $post_newValue;
        $_SESSION['user_language'] = $post_newValue;
    } elseif ($value[0] === "agses-usercardid") {
    // special case for agsescardid where session needs to be updated
        $_SESSION['user_settings']['agses-usercardid'] = $post_newValue;
    } elseif ($value[0] === "email") {
    // store email change in session
        $_SESSION['user_email'] = $post_newValue;
    }
    // Display info
    echo htmlentities($post_newValue, ENT_QUOTES);
// # ADMIN FOR USER HAS BEEN DEFINED ##
} elseif (null !== filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT)) {
    $id = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING));
    DB::update(
        prefix_table("users"),
        array(
            'admin' => filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT)
            ),
        "id = %i",
        $id[1]
    );
    // Display info
    if (filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT) === 1) {
        echo "Oui";
    } else {
        echo "Non";
    }
}

/**
 * Return the level of access on a folder
 * @param  string $new_val      New value
 * @param  string $existing_val Current value
 * @return string               Returned index
 */
function evaluate_folder_acces_level($new_val, $existing_val)
{
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
    if (($new_val === "ND" && $existing_val === "NE")
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
