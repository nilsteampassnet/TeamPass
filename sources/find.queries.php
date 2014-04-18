<?php
/**
 * @file          find.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.19
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

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");

//Connect to DB
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Columns name
$aColumns = array('id', 'label', 'description', 'tags', 'id_tree', 'folder', 'login');

//init SQL variables
$sOrder = $sLimit = "";
$sWhere = "id_tree IN(".implode(', ', $_SESSION['groupes_visibles']).")";    //limit search to the visible folders

//Get current user "personal folder" ID
// $row = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE title = '".intval($_SESSION['user_id'])."'");
$row = $db->queryGetRow(
    "nested_tree",
    array(
        "id"
    ),
    array(
        "title" => intval($_SESSION['user_id'])
    )
);

//get list of personal folders
$arrayPf = array();
$listPf = "";
if (empty($row[0])) {
	$rows = $db->fetchAllArray(
	    "SELECT id FROM ".$pre."nested_tree WHERE personal_folder=1 AND NOT parent_id = '".intval($row[0]).
	    "' AND NOT title = '".intval($_SESSION['user_id'])."'"
	);
	foreach ($rows as $reccord) {
		if (!in_array($reccord['id'], $arrayPf)) {
			//build an array of personal folders ids
			array_push($arrayPf, $reccord['id']);
			//build also a string with those ids
			if (empty($listPf)) {
				$listPf = $reccord['id'];
			} else {
				$listPf .= ', '.$reccord['id'];
			}
		}
	}
}


/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ". intval($_GET['iDisplayStart']) .", ". intval($_GET['iDisplayLength'])."";
}

//Ordering
if (isset($_GET['iSortCol_0'])) {
    $sOrder = "ORDER BY  ";
    for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
        if (
            $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" &&
            preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
        ) {
            $sOrder .= "".$aColumns[ intval($_GET['iSortCol_'.$i]) ]." "
            .mysql_real_escape_string($_GET['sSortDir_'.$i]) .", ";
        }
    }

    $sOrder = substr_replace($sOrder, "", -2);
    if ($sOrder == "ORDER BY") {
            $sOrder = "";
    }
}
//echo $sOrder;
/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */
if ($_GET['sSearch'] != "") {
    $sWhere .= " AND (";
    for ($i=0; $i<count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3).") ";
}

// Do NOT show the items in PERSONAL FOLDERS
if (!empty($listPf)) {
    if (!empty($sWhere)) {
        $sWhere .= " AND ";
    }
    $sWhere = "WHERE ".$sWhere."id_tree NOT IN (".$listPf.") ";
} else {
    $sWhere = "WHERE ".$sWhere;
}

$sql = "SELECT SQL_CALC_FOUND_ROWS *
        FROM ".$pre."cache
        $sWhere
        $sOrder
        $sLimit";

$rResult = mysql_query($sql) or die(mysql_error()." ; ".$sql);    //$rows = $db->fetchAllArray("

/* Data set length after filtering */
$sqlF = "
        SELECT FOUND_ROWS()
";
$rResultFilterTotal = mysql_query($sqlF) or die(mysql_error());
$aResultFilterTotal = mysql_fetch_array($rResultFilterTotal);
$iFilteredTotal = $aResultFilterTotal[0];

/* Total data set length */
$sqlC = "
        SELECT COUNT(id)
        FROM   ".$pre."cache
";
$rResultTotal = mysql_query($sqlC) or die(mysql_error());
$aResultTotal = mysql_fetch_array($rResultTotal);
$iTotal = $aResultTotal[0];


/*
 * Output
 */
$sOutput = '{';
$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
$sOutput .= '"iTotalRecords": '.$iTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
$sOutput .= '"aaData": [ ';
$sOutputConst = "";

$rows = $db->fetchAllArray($sql);
foreach ($rows as $reccord) {
    $getItemInList = true;
    $sOutputItem = "[";

    //col1
    $sOutputItem .= '"<img src=\"includes/images/key__arrow.png\" onClick=\"javascript:window.location.href = &#039;index.php?page=items&amp;group='.$reccord['id_tree'].'&amp;id='.$reccord['id'].'&#039;;\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/eye.png\" onClick=\"javascript:see_item('.$reccord['id'].','.$reccord['perso'].');\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/key_copy.png\" onClick=\"javascript:copy_item('.$reccord['id'].');\" style=\"cursor:pointer;\" />", ';

    //col2
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'", ';

    //col3
    $sOutputItem .= '"'.str_replace("&amp;", "&", htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES)).'", ';

    //col4
    //get restriction from ROles
    $restrictedToRole = false;
    $rTmp = mysql_query(
        "SELECT role_id FROM ".$pre."restriction_to_roles WHERE item_id = '".$reccord['id']."'"
    ) or die(mysql_error());
    while ($aTmp = mysql_fetch_row($rTmp)) {
        if ($aTmp[0] != "") {
            if (!in_array($aTmp[0], $_SESSION['user_roles'])) {
                $restrictedToRole = true;
            }
        }
    }

    //echo in_array($_SESSION['user_roles'], $a);
    if (
        ($reccord['perso']==1 && $reccord['author'] != $_SESSION['user_id'])
        ||
        (
            !empty($reccord['restricted_to'])
            && !in_array($_SESSION['user_id'], explode(';', $reccord['restricted_to']))
        )
        ||
        (
            $restrictedToRole == true
        )
    ) {
        $getItemInList = false;
    } else {
        $txt = str_replace(array('\n', '<br />', '\\'), array(' ', ' ', ''), strip_tags($reccord['description']));
        if (strlen($txt) > 50) {
            $sOutputItem .= '"'.substr(stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)), 0, 50).'", ';
        } else {
            $sOutputItem .= '"'.stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)).'", ';
        }
    }

    //col5 - TAGS
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($reccord['tags']), ENT_QUOTES).'", ';

    //col6 - Prepare the Treegrid
    $sOutputItem .= '"'.htmlspecialchars(stripslashes($reccord['folder']), ENT_QUOTES).'"';

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
