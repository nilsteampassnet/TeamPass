<?php
/**
 * @file          find.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

global $settings, $link;
require $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

// if no folders are visible then return no results
if (!isset($_SESSION['groupes_visibles']) || empty($_SESSION['groupes_visibles'])) {
    echo '{"sEcho": '.intval($_GET['sEcho']).' ,"iTotalRecords": "0", "iTotalDisplayRecords": "0", "aaData": [] }';
    exit;
}

//Connect to DB
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

//Columns name
$aColumns = array('id', 'label', 'description', 'tags', 'id_tree', 'folder', 'login', 'url');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sOrder = $sLimit = "";
$sWhere = "id_tree IN %ls_idtree"; //limit search to the visible folders

//Get current user "personal folder" ID
$row = DB::query(
    "SELECT id FROM ".prefix_table("nested_tree")." WHERE title = %i",
    intval($_SESSION['user_id'])
);

//get list of personal folders
$arrayPf = $crit = array();
$listPf = "";
if (!empty($row['id'])) {
    $rows = DB::query(
        "SELECT id FROM ".prefix_table("nested_tree")."
        WHERE personal_folder=1 AND NOT parent_id = %i AND NOT title = %i",
        "1",
        filter_var($row['id'], FILTER_SANITIZE_NUMBER_INT),
        filter_var($_SESSION['user_id'], FILTER_SANITIZE_NUMBER_INT)
    );
    foreach ($rows as $record) {
        if (!in_array($record['id'], $arrayPf)) {
            //build an array of personal folders ids
            array_push($arrayPf, $record['id']);
            //build also a string with those ids
            if (empty($listPf)) {
                $listPf = $record['id'];
            } else {
                $listPf .= ', '.$record['id'];
            }
        }
    }
}


/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ".filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT).", ".filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT)."";
}

//Ordering
$sOrder = "";
if (isset($_GET['iSortCol_0'])) {
    if (!in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        // possible attack - stop
        echo '[{}]';
        exit;
    } else {
        $sOrder = "ORDER BY  ";
        for ($i = 0; $i < intval($_GET['iSortingCols']); $i++) {
            if ($_GET['bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]).", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }
}

/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */
if (isset($_GET['sSearch']) === true && empty($_GET['sSearch']) === false) {
    $sWhere .= " AND (";
    for ($i = 0; $i < count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3).") ";

    $crit = array(
        'idtree' => $_SESSION['groupes_visibles'],
        '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '5' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '6' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '7' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        'pf' => $arrayPf
    );
}

if (isset($_GET['tagSearch']) && $_GET['tagSearch'] != "") {
    $sWhere .= " AND tags LIKE %ss_0";

    $crit = array(
        'idtree' => $_SESSION['groupes_visibles'],
        '0' => filter_var($_GET['tagSearch'], FILTER_SANITIZE_STRING),
        'pf' => $arrayPf
    );
}

if (count($crit) == 0) {
    $crit = array(
        'idtree' => $_SESSION['groupes_visibles'],
        '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '5' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '6' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '7' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        'pf' => $arrayPf
    );
}

// Do NOT show the items in PERSONAL FOLDERS
if (!empty($listPf)) {
    if (!empty($sWhere)) {
        $sWhere .= " AND ";
    }
    $sWhere = "WHERE ".$sWhere."id_tree NOT IN %ls_pf ";
} else {
    $sWhere = "WHERE ".$sWhere;
}

DB::query(
    "SELECT id FROM ".prefix_table("cache")."
    $sWhere
    $sOrder",
    $crit
);
$iTotal = DB::count();

$rows = DB::query(
    "SELECT id, label, description, tags, id_tree, perso, restricted_to, login, folder, author, renewal_period, url, timestamp
    FROM ".prefix_table("cache")."
    $sWhere
    $sOrder
    $sLimit",
    $crit
);
$iFilteredTotal = DB::count();

/*
 * Output
 */
if (!isset($_GET['type'])) {
    $sOutput = '{';
    if (isset($_GET['sEcho'])) {
        $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    }
    $sOutput .= '"aaData": [ ';
    $sOutputConst = "";


    foreach ($rows as $record) {
        $getItemInList = true;
        $sOutputItem = "[";

        // massive move/delete enabled?
        $checkbox = '';
        if (isset($SETTINGS['enable_massive_move_delete']) && $SETTINGS['enable_massive_move_delete'] === "1") {
            // check role access on this folder (get the most restrictive) (2.1.23)
            $accessLevel = 2;
            $arrTmp = [];

            foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                $access = DB::queryFirstRow(
                    "SELECT type FROM ".prefix_table("roles_values")." WHERE role_id = %i AND folder_id = %i",
                    $role,
                    $record['id_tree']
                );
                if ($access['type'] == "R") {
                    array_push($arrTmp, 1);
                } elseif ($access['type'] == "W") {
                    array_push($arrTmp, 0);
                } else {
                    array_push($arrTmp, 2);
                }
            }
            $accessLevel = min($arrTmp);
            if ($accessLevel === 0) {
                $checkbox = '&nbsp;<input type=\"checkbox\" value=\"0\" class=\"mass_op_cb\" id=\"mass_op_cb-'.$record['id'].'\">';
            }
        }

        // Expiration
        if ($SETTINGS['activate_expiration'] === "1") {
            if ($record['renewal_period'] > 0 &&
                ($record['timestamp'] + ($record['renewal_period'] * $SETTINGS_EXT['one_month_seconds'])) < time()
            ) {
                $expired = 1;
            } else {
                $expired = 0;
            }
        } else {
            $expired = 0;
        }

        // Manage the restricted_to variable
        if (null !== filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING)) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING);
        } else {
            $restrictedTo = "";
        }

        if (isset($_SESSION['list_folders_editable_by_role']) && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role'])) {
            if (empty($restrictedTo)) {
                $restrictedTo = $_SESSION['user_id'];
            } else {
                $restrictedTo .= ','.$_SESSION['user_id'];
            }
        }

        //col1
        $sOutputItem .= '"<i class=\"fa fa-external-link tip\" title=\"'.$LANG['open_url_link'].'\" onClick=\"window.location.href = &#039;index.php?page=items&amp;group='.$record['id_tree'].'&amp;id='.$record['id'].'&#039;;\" style=\"cursor:pointer;\"></i>&nbsp;'.
            '<i class=\"fa fa-eye tip\" title=\"'.$LANG['see_item_title'].'\" onClick=\"see_item('.$record['id'].','.$record['perso'].','.$record['id_tree'].','.$expired.','.$restrictedTo.');\" style=\"cursor:pointer;\"></i>'.$checkbox.'", ';

        //col2
        $sOutputItem .= '"<span id=\"item_label-'.$record['id'].'\">'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'</span>", ';

        //col3
        $sOutputItem .= '"'.str_replace("&amp;", "&", htmlspecialchars(stripslashes($record['login']), ENT_QUOTES)).'", ';

        //col4
        //get restriction from ROles
        $restrictedToRole = false;
        $rTmp = DB::queryFirstColumn(
            "SELECT role_id FROM ".prefix_table("restriction_to_roles")." WHERE item_id = %i",
            $record['id']
        );
        // We considere here that if user has at least one group similar to the object ones
        // then user can see item
        if (count(array_intersect($rTmp, $_SESSION['user_roles'])) === 0 && count($rTmp) > 0) {
            $restrictedToRole = true;
        }
        
        if ((
            $record['perso'] == 1 && $record['author'] != $_SESSION['user_id'])
            ||
            (
                empty($record['restricted_to']) === false
                && in_array($_SESSION['user_id'], explode(';', $record['restricted_to'])) === false
            )
            ||
            (
                $restrictedToRole === true
            )
        ) {
            $getItemInList = false;
        } else {
            $txt = str_replace(array('\n', '<br />', '\\'), array(' ', ' ', '', ' '), strip_tags($record['description']));
            if (strlen($txt) > 50) {
                $sOutputItem .= '"'.substr(stripslashes(preg_replace('~/<[\/]{0,1}[^>]*>\//|[ \t]/~', '', $txt)), 0, 50).'", ';
            } else {
                $sOutputItem .= '"'.stripslashes(preg_replace('~/<[^>]*>|[ \t]/~', '', $txt)).'", ';
            }
        }

        //col5 - TAGS
        $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['tags']), ENT_QUOTES).'", ';

        // col6 - URL
        if ($record['url'] != "0") {
                    $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['url']), ENT_QUOTES).'", ';
        } else {
                    $sOutputItem .= '"", ';
        }

        //col7 - Prepare the Treegrid
        $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['folder']), ENT_QUOTES).'"';

        //Finish the line
        $sOutputItem .= '], ';

        if ($getItemInList === true) {
            $sOutputConst .= $sOutputItem;
        } else {
            $iFilteredTotal --;
            $iTotal --;
        }
    }
    if (!empty($sOutputConst)) {
        $sOutput .= substr_replace($sOutputConst, "", -2);
    }
    $sOutput .= '], ';


    $sOutput .= '"iTotalRecords": '.$iFilteredTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.' }';

    echo $sOutput;
} elseif (isset($_GET['type']) && ($_GET['type'] == "search_for_items" || $_GET['type'] == "search_for_items_with_tags")) {
    require_once 'main.functions.php';
    require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    $sOutput = "";
    $init_personal_folder = false;
    $arr_data = [];

    foreach ($rows as $record) {
        $arr_data[$record['id']]['item_id'] = $record['id'];
        $arr_data[$record['id']]['tree_id'] = $record['id_tree'];
        $arr_data[$record['id']]['label'] = strip_tags((cleanString($record['label'])));
        $arr_data[$record['id']]['desc'] = strip_tags((cleanString(explode("<br>", $record['description'])[0])));
        $arr_data[$record['id']]['folder'] = strip_tags(stripslashes(mb_substr(cleanString($record['folder']), 0, 65)));
        $arr_data[$record['id']]['login'] = strtr($record['login'], '"', "&quot;");

        if ($SETTINGS['activate_expiration'] === "1") {
            if ($record['renewal_period'] > 0 &&
                ($record['timestamp'] + ($record['renewal_period'] * $SETTINGS_EXT['one_month_seconds'])) < time()
            ) {
                $arr_data[$record['id']]['expired'] = 1;
                $arr_data[$record['id']]['expirationFlag'] = 'red';
            } else {
                $arr_data[$record['id']]['expired'] = 0;
                $arr_data[$record['id']]['expirationFlag'] = 'green';
            }
        } else {
            $arr_data[$record['id']]['expired'] = 0;
            $arr_data[$record['id']]['expirationFlag'] = '';
        }
        // list of restricted users
        $restricted_users_array = explode(';', $record['restricted_to']);
        $itemPw = $itemLogin = "";
        $displayItem = $need_sk = $canMove = $item_is_restricted_to_role = 0;
        // TODO: Element is restricted to a group. Check if element can be seen by user
        // => récupérer un tableau contenant les roles associés à cet ID (a partir table restriction_to_roles)
        $user_is_included_in_role = 0;
        $roles = DB::query(
            "SELECT role_id FROM ".prefix_table("restriction_to_roles")." WHERE item_id=%i",
            $record['id']
        );
        if (count($roles) > 0) {
            $item_is_restricted_to_role = 1;
            foreach ($roles as $val) {
                if (in_array($val['role_id'], $_SESSION['user_roles'])) {
                    $user_is_included_in_role = 1;
                    break;
                }
            }
        }
        // Manage the restricted_to variable
        if (null !== filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING)) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING);
        } else {
            $restrictedTo = "";
        }

        if (isset($_SESSION['list_folders_editable_by_role']) && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role'])) {
            if (empty($restrictedTo)) {
                $restrictedTo = $_SESSION['user_id'];
            } else {
                $restrictedTo .= ','.$_SESSION['user_id'];
            }
        }

        $arr_data[$record['id']]['restricted'] = $restrictedTo;

        // CASE where item is restricted to a role to which the user is not associated
        if (isset($user_is_included_in_role) === true
            && $user_is_included_in_role == 0
            && isset($item_is_restricted_to_role) === true
            && $item_is_restricted_to_role == 1
            && in_array($_SESSION['user_id'], $restricted_users_array) === false
        ) {
            // $perso = '<i class="fa fa-tag mi-red"></i>&nbsp;';
            $arr_data[$record['id']]['perso'] = "fa-tag mi-red";
            $arr_data[$record['id']]['sk'] = 0;
            $arr_data[$record['id']]['display'] = "no_display";
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = "";

        // Case where item is in own personal folder
        } elseif (in_array($record['id_tree'], $_SESSION['personal_visible_groups'])
            && $record['perso'] == 1
        ) {
            $arr_data[$record['id']]['perso'] = "fa-warning mi-red";
            $arr_data[$record['id']]['sk'] = 1;
            $arr_data[$record['id']]['display'] = "";
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = "";

        // CAse where item is restricted to a group of users included user
        } elseif (!empty($record['restricted_to'])
            && in_array($_SESSION['user_id'], $restricted_users_array)
            || (isset($_SESSION['list_folders_editable_by_role'])
                && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role']))
            && in_array($_SESSION['user_id'], $restricted_users_array)
        ) {
            $arr_data[$record['id']]['perso'] = "fa-tag mi-yellow";
            $arr_data[$record['id']]['sk'] = 0;
            $arr_data[$record['id']]['display'] = "";
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = "";

        // CAse where item is restricted to a group of users not including user
        } elseif ($record['perso'] == 1
            ||
            (
                empty($record['restricted_to']) === false
                && in_array($_SESSION['user_id'], $restricted_users_array) === false
            )
            ||
            (
                isset($user_is_included_in_role) === true
                && isset($item_is_restricted_to_role) === true
                && $user_is_included_in_role == 0
                && $item_is_restricted_to_role == 1
            )
        ) {
            if (isset($user_is_included_in_role)
                && isset($item_is_restricted_to_role)
                && $user_is_included_in_role == 0
                && $item_is_restricted_to_role == 1
            ) {
                $arr_data[$record['id']]['perso'] = "fa-tag mi-red";
                $arr_data[$record['id']]['sk'] = 0;
                $arr_data[$record['id']]['display'] = "no_display";
                $arr_data[$record['id']]['open_edit'] = 1;
                $arr_data[$record['id']]['reload'] = "";
            } else {
                $arr_data[$record['id']]['perso'] = "fa-tag mi-yellow";
                $arr_data[$record['id']]['sk'] = 0;
                $arr_data[$record['id']]['display'] = "";
                $arr_data[$record['id']]['open_edit'] = 1;
                $arr_data[$record['id']]['reload'] = "";
            }
        } else {
            $arr_data[$record['id']]['perso'] = "fa-tag mi-green";
            $arr_data[$record['id']]['sk'] = 0;
            $arr_data[$record['id']]['display'] = "";
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = "";
        }

        // prepare pwd copy if enabled
        $arr_data[$record['id']]['pw_status'] = '';
        if (isset($SETTINGS['copy_to_clipboard_small_icons']) === true && $SETTINGS['copy_to_clipboard_small_icons'] === "1") {
            $data_item = DB::queryFirstRow(
                "SELECT pw
                from ".prefix_table("items")." WHERE id=%i",
                $record['id']
            );

            if ($record['perso'] === "1" && isset($_SESSION['user_settings']['session_psk']) === true) {
                $pw = cryption(
                    $data_item['pw'],
                    $_SESSION['user_settings']['session_psk'],
                    "decrypt"
                );
            } else {
                $pw = cryption(
                    $data_item['pw'],
                    "",
                    "decrypt"
                );
            }

            // test charset => may cause a json error if is not utf8
            $pw = $pw['string'];
            if (isUTF8($pw) === false) {
                $pw = "";
                $arr_data[$record['id']]['pw_status'] = "encryption_error";
            }
        } else {
            $pw = "";
        }
        $arr_data[$record['id']]['pw'] = strtr($pw, '"', "&quot;");
        $arr_data[$record['id']]['copy_to_clipboard_small_icons'] = isset($SETTINGS['copy_to_clipboard_small_icons']) === true ? $SETTINGS['copy_to_clipboard_small_icons'] : '0';
        $arr_data[$record['id']]['enable_favourites'] = isset($SETTINGS['enable_favourites']) === true ? $SETTINGS['enable_favourites'] : '0';
        if (in_array($record['id'], $_SESSION['favourites'])) {
            $arr_data[$record['id']]['is_favorite'] = 1;
        } else {
            $arr_data[$record['id']]['is_favorite'] = 0;
        }
    }

    $returnValues = array(
        "html_json" => $arr_data,
        "message" => str_replace("%X%", $iFilteredTotal, $LANG['find_message'])
    );

    echo prepareExchangedData($returnValues, "encode");
}
