<?php
/**
 *
 * @file          users.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_users")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Construction de la requ?te en fonction du type de valeur
if (!empty($_POST['type'])) {
    switch ($_POST['type']) {
        case "groupes_visibles":
        case "groupes_interdits":
            $val = explode(';', $_POST['valeur']);
            $valeur = $_POST['valeur'];
            // Check if id folder is already stored
            // $data = $db->fetchRow("SELECT ".$_POST['type']." FROM ".$pre."users WHERE id = ".$val[0]);
            $data = $db->queryGetRow(
                "users",
                array(
                    $_POST['type']
                ),
                array(
                    "id" => intval($val[0])
                )
            );
            $new_groupes = $data[0];
            if (!empty($data[0])) {
                $groupes = explode(';', $data[0]);
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
            $db->queryUpdate(
                "users",
                array($_POST['type'] => $new_groupes
                   ),
                "id = ".$val[0]
            );
            break;
        /**
         * Update a fonction
         */
        case "fonction":
            $val = explode(';', $_POST['valeur']);
            $valeur = $_POST['valeur'];
            // v?rifier si l'id est d?j? pr?sent
            // $data = $db->fetchRow("SELECT fonction_id FROM ".$pre."users WHERE id = $val[0]");
            $data = $db->queryGetRow(
                "users",
                array(
                    "fonction_id"
                ),
                array(
                    "id" => intval($val[0])
                )
            );
            $new_fonctions = $data[0];
            if (!empty($data[0])) {
                $fonctions = explode(';', $data[0]);
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
            $db->queryUpdate(
                "users",
                array(
                    'fonction_id' => $new_fonctions
                   ),
                "id = ".$val[0]
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
            // Empty user
            if (mysql_real_escape_string(htmlspecialchars_decode($_POST['login'])) == "") {
                echo '[ { "error" : "'.addslashes($txt['error_empty_data']).'" } ]';
                break;
            }
            // Check if user already exists
            $db->query("SELECT id, fonction_id, groupes_interdits, groupes_visibles FROM ".$pre."users WHERE login LIKE '".mysql_real_escape_string(stripslashes($_POST['login']))."'");
            $data = $db->fetchArray();
            if (empty($data['id'])) {
                // Add user in DB
                $new_user_id = $db->queryInsert(
                    "users",
                    array(
                        'login' => mysql_real_escape_string(htmlspecialchars_decode($_POST['login'])),
                        'name' => mysql_real_escape_string(htmlspecialchars_decode($_POST['name'])),
                        'lastname' => mysql_real_escape_string(htmlspecialchars_decode($_POST['lastname'])),
                        'pw' => bCrypt(stringUtf8Decode($_POST['pw']), COST),
                        'email' => $_POST['email'],
                        'admin' => $_POST['admin'] == "true" ? '1' : '0',
                        'gestionnaire' => $_POST['manager'] == "true" ? '1' : '0',
                        'read_only' => $_POST['read_only'] == "true" ? '1' : '0',
                        'personal_folder' => $_POST['personal_folder'] == "true" ? '1' : '0',
                        'fonction_id' => $_POST['manager'] == "true" ? $_SESSION['fonction_id'] : '0', // If manager is creater, then assign them roles as creator
                        'groupes_interdits' => $_POST['manager'] == "true" ? $data['groupes_interdits'] : '0',
                        'groupes_visibles' => $_POST['manager'] == "true" ? $data['groupes_visibles'] : '0',
                        'isAdministratedByRole' => $_POST['isAdministratedByRole']
                       )
                );
                // Create personnal folder
                if ($_POST['personal_folder'] == "true") {
                    $db->queryInsert(
                        "nested_tree",
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
                if ($_POST['new_folder_role_domain'] == "true") {
                    // create folder
                    $new_folder_id = $db->queryInsert(
                        "nested_tree",
                        array(
                            'parent_id' => 0,
                            'title' => mysql_real_escape_string(stripslashes($_POST['domain'])),
                            'personal_folder' => 0,
                            'renewal_period' => 0,
                            'bloquer_creation' => '0',
                            'bloquer_modification' => '0'
                           )
                    );
                    // Add complexity
                    $db->queryInsert(
                        "misc",
                        array(
                            'type' => 'complex',
                            'intitule' => $new_folder_id,
                            'valeur' => 50
                           )
                    );
                    // Create role
                    $new_role_id = $db->queryInsert(
                        "roles_title",
                        array(
                            'title' => mysql_real_escape_string(stripslashes(($_POST['domain'])))
                           )
                    );
                    // Associate new role to new folder
                    $db->queryInsert(
                        'roles_values',
                        array(
                            'folder_id' => $new_folder_id,
                            'role_id' => $new_role_id
                           )
                    );
                    // Add the new user to this role
                    $db->queryUpdate(
                        'users',
                        array(
                            'fonction_id' => is_int($new_role_id)
                           ),
                        "id=".$new_user_id
                    );
                    // rebuild tree
                    $tree->rebuild();
                }
                // Send email to new user
                @sendEmail(
                    $txt['email_subject_new_user'],
                    str_replace(array('#tp_login#', '#tp_pw#', '#tp_link#'), array(" ".addslashes(mysql_real_escape_string(htmlspecialchars_decode($_POST['login']))), addslashes(stringUtf8Decode($_POST['pw'])), $_SESSION['settings']['cpassman_url']), $txt['email_new_user_mail']),
                    $_POST['email']
                );
                // update LOG
                $db->queryInsert(
                    'log_system',
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
                echo '[ { "error" : "'.addslashes($txt['error_user_exists']).'" } ]';
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
                $db->queryDelete(
                    'users',
                    array(
                        'id' => $_POST['id']
                       )
                );
                // delete personal folder and subfolders
                // $data = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE title = '".$_POST['id']."' AND personal_folder = 1");    // Get personal folder ID
                $data = $db->queryGetRow(
                    "nested_tree",
                    array(
                        "id"
                    ),
                    array(
                        "title" => intval($_POST['id']),
                        "personal_folder" => "1"
                    )
                );
                // Get through each subfolder
                if (!empty($data[0])) {
                    $folders = $tree->getDescendants($data[0], true);
                    foreach ($folders as $folder) {
                        // delete folder
                        $db->query("DELETE FROM ".$pre."nested_tree WHERE id = '".$folder->id."' AND personal_folder = 1");
                        // delete items & logs
                        $items = $db->fetchAllArray("SELECT id FROM ".$pre."items WHERE id_tree='".$folder->id."' AND perso = 1");
                        foreach ($items as $item) {
                            // Delete item
                            $db->query("DELETE FROM ".$pre."items WHERE id = ".$item['id']);
                            // log
                            $db->query("DELETE FROM ".$pre."log_items WHERE id_item = ".$item['id']);
                        }
                    }
                    // rebuild tree
                    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
                    $tree->rebuild();
                }
                // update LOG
                $db->queryInsert(
                    'log_system',
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
                $db->queryUpdate(
                    'users',
                    array(
                        'disabled' => 1,
                        'key_tempo' => ""
                       ),
                    "id=".$_POST['id']
                );
                // update LOG
                $db->queryInsert(
                    'log_system',
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
            // $data = $db->fetchRow("SELECT email FROM ".$pre."users WHERE id = '".$_POST['id']."'");
            $data = $db->queryGetRow(
                "users",
                array(
                    "email"
                ),
                array(
                    "id" => intval($_POST['id'])
                )
            );

            $db->queryUpdate(
                "users",
                array(
                    'email' => $_POST['newemail']
                   ),
                "id = ".$_POST['id']
            );
            // update LOG
            $db->queryInsert(
                'log_system',
                array(
                    'type' => 'user_mngt',
                    'date' => time(),
                    'label' => 'at_user_email_changed:'.$data[0],
                    'qui' => $_SESSION['user_id'],
                    'field_1' => $_POST['id']
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

            $db->queryUpdate(
                "users",
                array(
                    'can_create_root_folder' => $_POST['value']
                   ),
                "id = ".$_POST['id']
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

            $db->queryUpdate(
                "users",
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

            $db->queryUpdate(
                "users",
                array(
                    'read_only' => $_POST['value']
                   ),
                "id = ".$_POST['id']
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

            $db->queryUpdate(
                "users",
                array(
                    'admin' => $_POST['value']
                   ),
                "id = ".$_POST['id']
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

            $db->queryUpdate(
                "users",
                array(
                    'personal_folder' => $_POST['value']
                   ),
                "id = ".$_POST['id']
            );
            break;

        /**
         * CHANGE USER FUNCTIONS
         */
        case "open_div_functions";
            $text = "";
            // Refresh list of existing functions
            // $data_user = $db->fetchRow("SELECT fonction_id FROM ".$pre."users WHERE id = ".$_POST['id']);
            $data = $db->queryGetRow(
                "users",
                array(
                    "fonction_id"
                ),
                array(
                    "id" => intval($_POST['id'])
                )
            );
            $users_functions = explode(';', $data_user[0]);
            // array of roles for actual user
            $my_functions = explode(';', $_SESSION['fonction_id']);

            $rows = $db->fetchAllArray("SELECT id,title,creator_id FROM ".$pre."roles_title");
            foreach ($rows as $reccord) {
                if ($_SESSION['is_admin'] == 1  || ($_SESSION['user_manager'] == 1 && (in_array($reccord['id'], $my_functions) || $reccord['creator_id'] == $_SESSION['user_id']))) {
                    $text .= '<input type="checkbox" id="cb_change_function-'.$reccord['id'].'"';
                    if (in_array($reccord['id'], $users_functions)) {
                        $text .= ' checked';
                    }
                    /*if ((!in_array($reccord['id'], $my_functions) && $_SESSION['is_admin'] != 1) && !($_SESSION['user_manager'] == 1 && $reccord['creator_id'] == $_SESSION['user_id'])) {
                        $text .= ' disabled="disabled"';
                    }*/
                    $text .= '>&nbsp;'.$reccord['title'].'<br />';
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
            $db->queryUpdate(
                "users",
                array(
                    'fonction_id' => $_POST['list']
                   ),
                "id = ".$_POST['id']
            );
            // display information
            $text = "";
            $val = str_replace(';', ',', $_POST['list']);
            // Check if POST is empty
            if (!empty($val)) {
                $rows = $db->fetchAllArray("SELECT title FROM ".$pre."roles_title WHERE id IN (".$val.")");
                foreach ($rows as $reccord) {
                    $text .= '<img src=\"includes/images/arrow-000-small.png\" />'.$reccord['title']."<br />";
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
            // $data_user = $db->fetchRow("SELECT groupes_visibles FROM ".$pre."users WHERE id = ".$_POST['id']);
            $data_user = $db->queryGetRow(
                "users",
                array(
                    "groupes_visibles"
                ),
                array(
                    "id" => intval($_POST['id'])
                )
            );
            $user = explode(';', $data_user[0]);

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
            $db->queryUpdate(
                "users",
                array(
                    'isAdministratedByRole' => $_POST['isAdministratedByRole']
                   ),
                "id = ".$_POST['userId']
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
            $db->queryUpdate(
                "users",
                array(
                    'groupes_visibles' => $_POST['list']
                   ),
                "id = ".$_POST['id']
            );
            // display information
            $text = "";
            $val = str_replace(';', ',', $_POST['list']);
            // Check if POST is empty
            if (!empty($_POST['list'])) {
                $rows = $db->fetchAllArray("SELECT title,nlevel FROM ".$pre."nested_tree WHERE id IN (".$val.")");
                foreach ($rows as $reccord) {
                    $ident = "";
                    for ($y = 1; $y < $reccord['nlevel']; $y++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    $text .= '<img src=\"includes/images/arrow-000-small.png\" />'.$ident.$reccord['title']."<br />";
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
            // $data_user = $db->fetchRow("SELECT groupes_interdits FROM ".$pre."users WHERE id = ".$_POST['id']);
            $data_user = $db->queryGetRow(
                "users",
                array(
                    "groupes_interdits"
                ),
                array(
                    "id" => intval($_POST['id'])
                )
            );
            $user = explode(';', $data_user[0]);

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
            $db->queryUpdate(
                "users",
                array(
                    'groupes_interdits' => $_POST['list']
                   ),
                "id = ".$_POST['id']
            );
            // display information
            $text = "";
            $val = str_replace(';', ',', $_POST['list']);
            // Check if POST is empty
            if (!empty($_POST['list'])) {
                $rows = $db->fetchAllArray("SELECT title,nlevel FROM ".$pre."nested_tree WHERE id IN (".$val.")");
                foreach ($rows as $reccord) {
                    $ident = "";
                    for ($y = 1; $y < $reccord['nlevel']; $y++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    $text .= '<img src=\"includes/images/arrow-000-small.png\" />'.$ident.$reccord['title']."<br />";
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

            $db->queryUpdate(
                "users",
                array(
                    'disabled' => 0,
                    'no_bad_attempts' => 0
                   ),
                "id = ".$_POST['id']
            );
            // update LOG
            $db->queryInsert(
                'log_system',
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
            //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree WHERE title = '".$_POST['domain']."' AND parent_id = 0");
            $data = $db->queryCount(
                "nested_tree",
                array(
                    "title" => $_POST['domain'],
                    "parent_id" => "0"
                )
            );
            if ($data[0] != 0) {
                $return["folder"] = "exists";
            } else {
                $return["folder"] = "not_exists";
            }
            // Check if role exists
            //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."roles_title WHERE title = '".$_POST['domain']."'");
            $data = $db->queryCount(
                "roles_title",
                array(
                    "title" => $_POST['domain']
                )
            );
            if ($data[0] != 0) {
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
            $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.$txt['pages'].'&nbsp;:&nbsp;</td>';

            if ($_POST['scope'] == "user_activity") {
                if (isset($_POST['filter']) && !empty($_POST['filter']) && $_POST['filter'] != "all") {
                    $sql_filter = " AND l.action = '".$_POST['filter']."'";
                }
                // get number of pages
                $data = $db->fetchRow(
                    "SELECT COUNT(*)
                    FROM ".$pre."log_items as l
                    INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
                    INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
                    WHERE l.id_user = ".intval($_POST['id'].$sql_filter)
                );
                // define query limits
                if (isset($_POST['page']) && $_POST['page'] > 1) {
                    $start = ($_POST['nb_items_by_page'] * ($_POST['page'] - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = $db->fetchAllArray(
                    "SELECT l.date as date, u.login as login, i.label as label, l.action as action
                    FROM ".$pre."log_items as l
                    INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
                    INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
                    WHERE l.id_user = ".intval($_POST['id'].$sql_filter)."
                    ORDER BY date DESC
                    LIMIT ".intval($start).",".intval($_POST['nb_items_by_page'])
                );
            } else {
                // get number of pages
                //$data = $db->fetchRow(
                //    "SELECT COUNT(*)
                //    FROM ".$pre."log_system
                //    WHERE type = 'user_mngt' AND field_1=".$_POST['id']
                //);
                $data = $db->queryCount(
                    "log_system",
                    array(
                        "type" => "user_mngt",
                        "field_1" => $_POST['id']
                    )
                );
                // define query limits
                if (isset($_POST['page']) && $_POST['page'] > 1) {
                    $start = ($_POST['nb_items_by_page'] * ($_POST['page'] - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = $db->fetchAllArray(
                    "SELECT *
                    FROM ".$pre."log_system
                    WHERE type = 'user_mngt' AND field_1=".$_POST['id']."
                    ORDER BY date DESC
                    LIMIT $start,".$_POST['nb_items_by_page']
                );
            }
            // generate data
            if (isset($data) && $data[0] != 0) {
                $nb_pages = ceil($data[0] / $_POST['nb_items_by_page']);
                for ($i = 1; $i <= $nb_pages; $i++) {
                    $pages .= '<td onclick=\'displayLogs('.$i.',\'user_mngt\')\'><span style=\'cursor:pointer;'.($_POST['page'] == $i ? 'font-weight:bold;font-size:18px;\'>'.$i:'\'>'.$i).'</span></td>';
                }
            }
            $pages .= '</tr></table>';
            if (isset($rows)) {
                foreach ($rows as $reccord) {
                    if ($_POST['scope'] == "user_mngt") {
                        $user = $db->fetchRow("SELECT login from ".$pre."users WHERE id=".$reccord['qui']);
                        $user_1 = $db->fetchRow("SELECT login from ".$pre."users WHERE id=".$_POST['id']);
                        $tmp = explode(":", $reccord['label']);
                        $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'</td><td align=\"center\">'.str_replace(array('"', '#user_login#'), array('\"', $user_1[0]), $txt[$tmp[0]]).'</td><td align=\"center\">'.$user[0].'</td><td align=\"center\"></td></tr>';
                    } else {
                        $logs .= '<tr><td>'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'</td><td align=\"center\">'.str_replace('"', '\"', $reccord['label']).'</td><td align=\"center\">'.$reccord['login'].'</td><td align=\"center\">'.$txt[$reccord['action']].'</td></tr>';
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
                $admin_folder = $db->queryFirst("SELECT id FROM ".$pre."nested_tree WHERE title='".intval($_SESSION['user_id'])."' AND personal_folder = 1");
                // Get folder id for User
                $user_folder = $db->queryFirst("SELECT id FROM ".$pre."nested_tree WHERE title='".intval($user_id)."' AND personal_folder = 1");
                // Get through each subfolder
                foreach ($tree->getDescendants($admin_folder['id'], true) as $folder) {
                    // Create folder if necessary
                    if ($folder->title != $_SESSION['user_id']) {
                        // update folder
                        /*$db->queryUpdate(
                            "nested_tree",
                            array(
                                'parent_id' => $user_folder['id']
                           ),
                            "id='".$folder->id."'"
                        );*/
                    }
                    // Get each Items in PF
                    $rows = $db->fetchAllArray(
                        "SELECT i.pw, i.label, l.id_user
                        FROM ".$pre."items as i
                        LEFT JOIN ".$pre."log_items as l ON (l.id_item=i.id)
                        WHERE l.action = 'at_creation' AND i.perso=1 AND i.id_tree=".intval($folder->id)
                    );
                    foreach ($rows as $reccord) {
                        echo $reccord['label']." - ";
                        // Change user
                        $db->queryUpdate(
                            "log_items",
                            array(
                                'id_user' => $user_id
                               ),
                            array(
                                "id_item='".$reccord['id']."'",
                                "id_user='".$user_id."'",
                                "action='at_creation'"
                               )
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
            $db->queryUpdate(
                    "users",
                    array(
                        'timestamp' => "",
                        'key_tempo' => "",
                        'session_end' => ""
                       ),
                    "id = ".intval($_POST['user_id'])
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
            $rows = $db->fetchAllArray("SELECT id FROM ".$pre."users WHERE timestamp != '' AND admin != 1");
            foreach ($rows as $reccord) {
                $db->queryUpdate(
                    "users",
                    array(
                        'timestamp' => "",
                        'key_tempo' => "",
                        'session_end' => ""
                       ),
                    "id = ".intval($reccord['id'])
                );
            }
            break;
    }
}
// # NEW LOGIN FOR USER HAS BEEN DEFINED ##
elseif (!empty($_POST['newValue'])) {
    $value = explode('_', $_POST['id']);
    $db->queryUpdate(
        "users",
        array(
            $value[0] => $_POST['newValue']
           ),
        "id = ".$value[1]
    );
    // update LOG
    $db->queryInsert(
        'log_system',
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
    $db->queryUpdate(
        "users",
        array(
            'admin' => $_POST['newadmin']
           ),
        "id = ".$id[1]
    );
    // Display info
    if ($_POST['newadmin'] == "1") {
        echo "Oui";
    } else {
        echo "Non";
    }
}
