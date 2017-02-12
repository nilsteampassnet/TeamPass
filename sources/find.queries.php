<?php
/**
 * @file          find.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
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

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

global $k, $settings, $link;
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

// if no folders are visible then return no results
if (!isset($_SESSION['groupes_visibles']) || empty($_SESSION['groupes_visibles'])) {
    $returnValues = array(
        "items_html" => '',
        "message" => str_replace("%X%", 0, $LANG['find_message'])
    );

    echo prepareExchangedData($returnValues, "encode");
    exit;
}

//Connect to DB
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

//Columns name
$aColumns = array('id', 'label', 'description', 'tags', 'id_tree', 'folder', 'login', 'url');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sOrder = $sLimit = "";
$sWhere = "id_tree IN %ls_idtree";    //limit search to the visible folders

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
    $sLimit = "LIMIT ". filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT) .", ". filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT)."";
}

//Ordering
$sOrder = "";
if (isset($_GET['iSortCol_0'])) {
    if (!in_array(strtoupper($_GET['sSortDir_0']), $aSortTypes)) {
        // possible attack - stop
        echo '[{}]';
        exit;
    }
    else {
        $sOrder = "ORDER BY  ";
        for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
            if (
                $_GET[ 'bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
                preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
            ) {
                $sOrder .= "".$aColumns[ filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT) ]." "
                .mysqli_escape_string($link, $_GET['sSortDir_'.$i]) .", ";
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
if (isset($_GET['sSearch']) && $_GET['sSearch'] != "") {
    $sWhere .= " AND (";
    for ($i=0; $i<count($aColumns); $i++) {
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
    if (isset($_GET['sEcho'])) $sOutput .= '"sEcho": ' . intval($_GET['sEcho']) . ', ';
    $sOutput .= '"iTotalRecords": ' . $iFilteredTotal . ', ';
    $sOutput .= '"iTotalDisplayRecords": ' . $iTotal . ', ';
    $sOutput .= '"aaData": [ ';
    $sOutputConst = "";


    foreach ($rows as $record) {
        $getItemInList = true;
        $sOutputItem = "[";

        // massive move/delete enabled?
        $checkbox = '';
        if (isset($_SESSION['settings']['enable_massive_move_delete']) && $_SESSION['settings']['enable_massive_move_delete'] === "1") {
            // check role access on this folder (get the most restrictive) (2.1.23)
            $accessLevel = 2;
            $arrTmp = [];
//echo $_SESSION['fonction_id'];
            foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                $access = DB::queryFirstRow(
                    "SELECT type FROM ".prefix_table("roles_values")." WHERE role_id = %i AND folder_id = %i",
                    $role,
                    $record['id_tree']
                );
                if ($access['type'] == "R") array_push($arrTmp, 1);
                else if ($access['type'] == "W") array_push($arrTmp, 0);
                else array_push($arrTmp, 2);
            }
            $accessLevel = min($arrTmp);
            if ($accessLevel === 0)
                $checkbox = '&nbsp;<input type=\"checkbox\" value=\"0\" class=\"mass_op_cb\" id=\"mass_op_cb-'.$record['id'].'\">';
        }

        //col1
        $sOutputItem .= '"<i class=\"fa fa-external-link tip\" title=\"'.$LANG['open_url_link'].'\" onClick=\"javascript:window.location.href = &#039;index.php?page=items&amp;group=' . $record['id_tree'] . '&amp;id=' . $record['id'] . '&#039;;\" style=\"cursor:pointer;\"></i>&nbsp;'.
            '<i class=\"fa fa-eye tip\" title=\"'.$LANG['see_item_title'].'\" onClick=\"javascript:see_item(' . $record['id'] . ',' . $record['perso'] . ');\" style=\"cursor:pointer;\"></i>'.$checkbox.'", ';

        //col2
        $sOutputItem .= '"<span id=\"item_label-'.$record['id'].'\">' . htmlspecialchars(stripslashes($record['label']), ENT_QUOTES) . '</span>", ';

        //col3
        $sOutputItem .= '"' . str_replace("&amp;", "&", htmlspecialchars(stripslashes($record['login']), ENT_QUOTES)) . '", ';

        //col4
        //get restriction from ROles
        $restrictedToRole = false;
        $rTmp = DB::query(
            "SELECT role_id FROM ".prefix_table("restriction_to_roles")." WHERE item_id = %i", $record['id']
        );
        foreach ($rTmp as $aTmp) {
            if ($aTmp['role_id'] != "") {
                if (!in_array($aTmp['role_id'], $_SESSION['user_roles'])) {
                    $restrictedToRole = true;
                }
            }
        }

        if (
            ($record['perso'] == 1 && $record['author'] != $_SESSION['user_id'])
            ||
            (
                !empty($record['restricted_to'])
                && !in_array($_SESSION['user_id'], explode(';', $record['restricted_to']))
            )
            ||
            (
                $restrictedToRole == true
            )
        ) {
            $getItemInList = false;
        } else {
            $txt = str_replace(array('\n', '<br />', '\\'), array(' ', ' ', '', ' '), strip_tags($record['description']));
            if (strlen($txt) > 50) {
                $sOutputItem .= '"' . substr(stripslashes(preg_replace('~/<[\/]{0,1}[^>]*>\//|[ \t]/~', '', $txt)), 0, 50) . '", ';
            } else {
                $sOutputItem .= '"' . stripslashes(preg_replace('~/<[^>]*>|[ \t]/~', '', $txt)) . '", ';
            }
        }

        //col5 - TAGS
        $sOutputItem .= '"' . htmlspecialchars(stripslashes($record['tags']), ENT_QUOTES) . '", ';

        // col6 - URL
        if ($record['url'] != "0")
            $sOutputItem .= '"' . htmlspecialchars(stripslashes($record['url']), ENT_QUOTES) . '", ';
        else
            $sOutputItem .= '"", ';

        //col7 - Prepare the Treegrid
        $sOutputItem .= '"' . htmlspecialchars(stripslashes($record['folder']), ENT_QUOTES) . '"';

        //Finish the line
        $sOutputItem .= '], ';

        if ($getItemInList == true) {
            $sOutputConst .= $sOutputItem;
        }
    }
    if (!empty($sOutputConst)) {
        $sOutput .= substr_replace($sOutputConst, "", -2);
    }
    $sOutput .= '] }';

    echo $sOutput;
} else if (isset($_GET['type']) && ($_GET['type'] == "search_for_items" || $_GET['type'] == "search_for_items_with_tags")) {
    require_once 'main.functions.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    $sOutput = "";
    $init_personal_folder = false;

    foreach ($rows as $record) {
        $expirationFlag = '';
        $expired_item = 0;
        if ($_SESSION['settings']['activate_expiration'] == 1) {
            $expirationFlag = '<i class="fa fa-flag mi-green"></i>&nbsp;';
            if (
                $record['renewal_period'] > 0 &&
                ($record['timestamp'] + ($record['renewal_period'] * $k['one_month_seconds'])) < time()
            ) {
                $expirationFlag = '<i class="fa fa-flag mi-red"></i>&nbsp;';
                $expired_item = 1;
            }
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
        if (isset($_POST['restricted'])) {
            $restrictedTo = $_POST['restricted'];
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

        // CASE where item is restricted to a role to which the user is not associated
        if (
            isset($user_is_included_in_role)
            && $user_is_included_in_role == 0
            && isset($item_is_restricted_to_role)
            && $item_is_restricted_to_role == 1
            && !in_array($_SESSION['user_id'], $restricted_users_array)
        ) {
            $perso = '<i class="fa fa-tag mi-red"></i>&nbsp;';
            $findPfGroup = 0;
            $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', \'\', \'\', \''.$record['id_tree'].'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\', \''.$record['id_tree'].'\')';
            $displayItem = $need_sk = $canMove = 0;
        }
        // Case where item is in own personal folder
        elseif (
            in_array($record['id_tree'], $_SESSION['personal_visible_groups'])
            && $record['perso'] == 1
        ) {
            $perso = '<i class="fa fa-warning mi-red"></i>&nbsp;';
            $findPfGroup = 1;
            $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'1\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\', \''.$record['id_tree'].'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'1\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\', \''.$record['id_tree'].'\')';
            $displayItem = $need_sk = $canMove = 1;
        }
        // CAse where item is restricted to a group of users included user
        elseif (
            !empty($record['restricted_to'])
            && in_array($_SESSION['user_id'], $restricted_users_array)
            || (isset($_SESSION['list_folders_editable_by_role'])
                && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role']))
            && in_array($_SESSION['user_id'], $restricted_users_array)
        ) {
            $perso = '<i class="fa fa-tag mi-yellow"></i>&nbsp;';
            $findPfGroup = 0;
            $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\', \''.$record['id_tree'].'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\', \''.$record['id_tree'].'\')';
            $displayItem = 1;
        }
        // CAse where item is restricted to a group of users not including user
        elseif (
            $record['perso'] == 1
            ||
            (
                !empty($record['restricted_to'])
                && !in_array($_SESSION['user_id'], $restricted_users_array)
            )
            ||
            (
                isset($user_is_included_in_role)
                && isset($item_is_restricted_to_role)
                && $user_is_included_in_role == 0
                && $item_is_restricted_to_role == 1
            )
        ) {
            if (
                isset($user_is_included_in_role)
                && isset($item_is_restricted_to_role)
                && $user_is_included_in_role == 0
                && $item_is_restricted_to_role == 1
            ) {
                $perso = '<i class="fa fa-tag mi-red"></i>&nbsp;';
                $findPfGroup = 0;
                $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\',\'\', \'\', \''.$record['id_tree'].'\')';
                $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\', \''.$record['id_tree'].'\')';
                $displayItem = $need_sk = $canMove = 0;
            } else {
                $perso = '<i class="fa fa-tag mi-yellow"></i>&nbsp;';
                $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\', \''.$record['id_tree'].'\')';
                $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\', \''.$record['id_tree'].'\')';
                // reinit in case of not personal group
                if ($init_personal_folder == false) {
                    $findPfGroup = "";
                    $init_personal_folder = true;
                }

                if (!empty($record['restricted_to']) && in_array($_SESSION['user_id'], $restricted_users_array)) {
                    $displayItem = 1;
                }
            }
        } else {
            $perso = '<i class="fa fa-tag mi-green"></i>&nbsp;';
            $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\', \''.$record['id_tree'].'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\', \''.$record['id_tree'].'\')';
            $displayItem = 1;
            // reinit in case of not personal group
            if ($init_personal_folder == false) {
                $findPfGroup = "";
                $init_personal_folder = true;
            }
        }

        // prepare new line
        $sOutput .= '<li class="item" id="'.$record['id'].'" style="margin-left:-30px;"><a id="fileclass'.$record['id'].'" class="file_search">'.
            '<i class="fa fa-key mi-yellow tip" onclick="'.$action_dbl.'" title="'.$LANG['click_to_edit'].'"></i>&nbsp;'.
            '<span onclick="'.$action.'">'.mb_substr(stripslashes(handleBackslash($record['label'])), 0, 65);
        if (!empty($record['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1) {
            $tempo = explode("<br />", $record['description']);
            if (count($tempo) == 1) {
                $sOutput .= '&nbsp;<font size="2px">['.strip_tags(stripslashes(mb_substr(cleanString($record['description']), 0, 30))).']</font>';
            } else {
                $sOutput .= '&nbsp;<font size="2px">['.strip_tags(stripslashes(mb_substr(cleanString($tempo[0]), 0, 30))).']</font>';
            }
        }

        // set folder
        $sOutput .= '&nbsp;<span style="font-size:11px;font-style:italic;"><i class="fa fa-folder-o"></i>&nbsp;'.strip_tags(stripslashes(mb_substr(cleanString($record['folder']), 0, 65))).'</span>';

        $sOutput .= '</span><span style="float:right;margin:2px 10px 0px 0px;">';

        // prepare login mini icon
        if (!empty($record['login'])) {
            $sOutput .= '<i class="fa fa-user fa-lg mi-black mini_login tip" data-clipboard-text="'.str_replace('"', "&quot;", $record['login']).'" title="'.$LANG['item_menu_copy_login'].'"></i>&nbsp;';
        }

        // prepare pwd copy if enabled
        if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
            $data_item = DB::queryFirstRow(
                "SELECT pw
                from ".prefix_table("items")." WHERE id=%i",
                $record['id']
            );

            if ($record['perso'] === "1" && isset($_SESSION['user_settings']['session_psk'])) {
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
            if (isUTF8($pw)) {
                $sOutput .= '<i class="fa fa-lock fa-lg mi-black mini_pw tip" data-clipboard-text="'.str_replace('"', "&quot;", $pw).'" title="'.$LANG['item_menu_copy_pw'].'"></i>&nbsp;';
            }
        }


        // Prepare make Favorite small icon
        if (in_array($record['id'], $_SESSION['favourites'])) {
            $sOutput .= '<i class="fa fa-star fa-lg mi-yellow tip" onclick="ActionOnQuickIcon('.$record['id'].',0)" class="tip" title="'.$LANG['item_menu_del_from_fav'].'"></i>';
        } else {
            $sOutput .= '<i class="fa fa-star-o fa-lg tip" onclick="ActionOnQuickIcon('.$record['id'].',1)" class="tip" title="'.$LANG['item_menu_add_to_fav'].'"></i>';
        }

        $sOutput .= '</li>';
    }

    $returnValues = array(
        "items_html" => $sOutput,
        "message" => str_replace("%X%", $iFilteredTotal, $LANG['find_message'])
    );

    echo prepareExchangedData($returnValues, "encode");
}