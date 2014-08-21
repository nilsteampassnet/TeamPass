<?php
/**
 * @file          find.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.21
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

global $k, $settings, $link;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");

//Connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);

//Columns name
$aColumns = array('id', 'label', 'description', 'tags', 'id_tree', 'folder', 'login');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sOrder = $sLimit = "";
$sWhere = "id_tree IN %ls_idtree";    //limit search to the visible folders

//Get current user "personal folder" ID
$row = DB::query(
    "SELECT id FROM ".$pre."nested_tree WHERE title = %i",
    intval($_SESSION['user_id'])
);

//get list of personal folders
$arrayPf = array();
$listPf = "";
if (!empty($row['id'])) {
	$rows = DB::query(
	    "SELECT id FROM ".$pre."nested_tree
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
if ($_GET['sSearch'] != "") {
    $sWhere .= " AND (";
    for ($i=0; $i<count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE %ss_".i." OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3).") ";
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

DB::query("SELECT id FROM ".$pre."cache");
$iTotal = DB::count();

$rows = DB::query(
    "SELECT *
    FROM ".$pre."cache
    $sWhere
    $sOrder
    $sLimit",
    array(
        'idtree' => $_SESSION['groupes_visibles'],
        '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '5' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '6' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        'pf' => $arrayPf
    )
);
$iFilteredTotal = DB::count();

/*
 * Output
 */
$sOutput = '{';
$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
$sOutput .= '"iTotalRecords": '.$iFilteredTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
$sOutput .= '"aaData": [ ';
$sOutputConst = "";


foreach ($rows as $record) {
    $getItemInList = true;
    $sOutputItem = "[";

    //col1
    $sOutputItem .= '"<img src=\"includes/images/key__arrow.png\" onClick=\"javascript:window.location.href = &#039;index.php?page=items&amp;group='.$record['id_tree'].'&amp;id='.$record['id'].'&#039;;\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/eye.png\" onClick=\"javascript:see_item('.$record['id'].','.$record['perso'].');\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/key_copy.png\" onClick=\"javascript:copy_item('.$record['id'].');\" style=\"cursor:pointer;\" />", ';

    //col2
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'", ';

    //col3
    $sOutputItem .= '"'.str_replace("&amp;", "&", htmlspecialchars(stripslashes($record['login']), ENT_QUOTES)).'", ';

    //col4
    //get restriction from ROles
    $restrictedToRole = false;
    $rTmp = DB::query(
        "SELECT role_id FROM ".$pre."restriction_to_roles WHERE item_id = %i", $record['id']
    );
    foreach ($rTmp as $aTmp) {
        if ($aTmp['role_id'] != "") {
            if (!in_array($aTmp['role_id'], $_SESSION['user_roles'])) {
                $restrictedToRole = true;
            }
        }
    }

    if (
        ($record['perso']==1 && $record['author'] != $_SESSION['user_id'])
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
            $sOutputItem .= '"'.substr(stripslashes(preg_replace('/<[\/]{0,1}[^>]*>|[ \t]/', '', $txt)), 0, 50).'", ';
        } else {
            $sOutputItem .= '"'.stripslashes(preg_replace('/<[^>]*>|[ \t]/', '', $txt)).'", ';
        }
    }

    //col5 - TAGS
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['tags']), ENT_QUOTES).'", ';

    //col6 - Prepare the Treegrid
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['folder']), ENT_QUOTES).'"';

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
