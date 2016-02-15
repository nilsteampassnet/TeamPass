<?php
/**
 * @file          find.queries.php
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

require_once 'sessions.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

global $k, $settings, $link;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

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
$aColumns = array('id', 'label', 'description', 'tags', 'id_tree', 'folder', 'login');
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
        'pf' => $arrayPf
    );
}

// Do NOT show the items in PERSONAL FOLDERS
if (!empty($listPf)) {
    if (!empty($sWhere)) {
        $sWhere .= " AND ";
    }
    //$sWhere = "WHERE ".$sWhere."id_tree NOT IN (".$listPf.") ";
    $sWhere = "WHERE ".$sWhere."id_tree NOT IN %ls_pf ";
} else {
    $sWhere = "WHERE ".$sWhere;
}

DB::query("SELECT id FROM ".prefix_table("cache"));
$iTotal = DB::count();

$rows = DB::query(
    "SELECT id, label, description, tags, id_tree, perso, restricted_to, login, folder, author, renewal_period
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

        //col1
        $sOutputItem .= '"<i class=\"fa fa-external-link tip\" title=\"'.$LANG['open_url_link'].'\" onClick=\"javascript:window.location.href = &#039;index.php?page=items&amp;group=' . $record['id_tree'] . '&amp;id=' . $record['id'] . '&#039;;\" style=\"cursor:pointer;\"></i>&nbsp;'.
            '<i class=\"fa fa-eye tip\" title=\"'.$LANG['see_item_title'].'\" onClick=\"javascript:see_item(' . $record['id'] . ',' . $record['perso'] . ');\" style=\"cursor:pointer;\"></i>&nbsp;'.
            '<i class=\"fa fa-clone tip\" title=\"'.$LANG['item_menu_copy_elem'].'\" onClick=\"javascript:copy_item(' . $record['id'] . ');\" style=\"cursor:pointer;\"></i>&nbsp;", ';

        //col2
        $sOutputItem .= '"' . htmlspecialchars(stripslashes($record['label']), ENT_QUOTES) . '", ';

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

        //col6 - Prepare the Treegrid
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
                ($record['date'] + ($record['renewal_period'] * $k['one_month_seconds'])) < time()
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
            $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', \'\', \'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\')';
            $displayItem = $need_sk = $canMove = 0;
        }
        // Case where item is in own personal folder
        elseif (
            in_array($record['id_tree'], $_SESSION['personal_visible_groups'])
            && $record['perso'] == 1
        ) {
            $perso = '<i class="fa fa-warning mi-red"></i>&nbsp;';
            $findPfGroup = 1;
            $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'1\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'1\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
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
            $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
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
                $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\',\'\', \'\')';
                $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\')';
                $displayItem = $need_sk = $canMove = 0;
            } else {
                $perso = '<i class="fa fa-tag mi-yellow"></i>&nbsp;';
                $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\')';
                $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
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
            $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\')';
            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
            $displayItem = 1;
            // reinit in case of not personal group
            if ($init_personal_folder == false) {
                $findPfGroup = "";
                $init_personal_folder = true;
            }
        }

        // prepare new line
        $sOutput .= '<li ondblclick="'.$action_dbl.'" class="item" id="'.$record['id'].'" style="margin-left:-30px;"><a id="fileclass'.$record['id'].'" class="file_search" onclick="'.$action.'"><i class="fa fa-key mi-yellow"></i>&nbsp;'.substr(stripslashes($record['label']), 0, 65);

        if (!empty($record['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1) {
            $tempo = explode("<br />", $record['description']);
            if (count($tempo) == 1) {
                $sOutput .= '&nbsp;<font size="2px">['.strip_tags(stripslashes(substr(cleanString($record['description']), 0, 30))).']</font>';
            } else {
                $sOutput .= '&nbsp;<font size="2px">['.strip_tags(stripslashes(substr(cleanString($tempo[0]), 0, 30))).']</font>';
            }
        }
        
        // set folder
        $sOutput .= '&nbsp;<span style="font-size:11px;font-style:italic;"><i class="fa fa-folder-o"></i>&nbsp;'.strip_tags(stripslashes(substr(cleanString($record['folder']), 0, 30))).'</span>';

        $sOutput .= '<span style="float:right;margin:2px 10px 0px 0px;">';
        
        // Prepare make Favorite small icon
        $sOutput .= '&nbsp;<span id="quick_icon_fav_'.$record['id'].'" title="Manage Favorite" class="cursor tip">';
        if (in_array($record['id'], $_SESSION['favourites'])) {
            $sOutput .= '<i class="fa fa-star mi-yellow fa-lg" onclick="ActionOnQuickIcon('.$record['id'].',0)" class="tip"></i>&nbsp;';
        } else {
            $sOutput .= '<i class="fa fa-star-o fa-lg" onclick="ActionOnQuickIcon('.$record['id'].',1)" class="tip"></i>&nbsp;';
        }
        $sOutput .= "</span>";

        $sOutput .= '</li>';
    }

    $returnValues = array(
        "items_html" => $sOutput,
        "message" => str_replace("%X%", $iFilteredTotal, $LANG['find_message'])
    );

    echo prepareExchangedData($returnValues, "encode");
}