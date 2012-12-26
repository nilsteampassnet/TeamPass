<?php
/**
 * @file           find.queries.php
 * @author        Nils Laumaillé
 * @version         2.1.13
 * @copyright     (c) 2009-2012 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link            http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
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
$row = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE title = ".$_SESSION['user_id']);

//get list of personal folders
$array_pf = array();
$list_pf = "";
$rows = $db->fetchAllArray(
    "SELECT id FROM ".$pre."nested_tree WHERE personal_folder=1 AND NOT parent_id = ".$row[0].
    " AND NOT title = ".$_SESSION['user_id']
);
foreach ($rows as $reccord) {
    if (!in_array($reccord['id'], $array_pf)) {
        //build an array of personal folders ids
        array_push($array_pf, $reccord['id']);
        //build also a string with those ids
        if (empty($list_pf)) {
            $list_pf = $reccord['id'];
        } else {
            $list_pf .= ', '.$reccord['id'];
        }
    }
}

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ". intval($_GET['iDisplayStart']) .", ". intval($_GET['iDisplayLength']) ;
}

//Ordering
if (isset($_GET['iSortCol_0'])) {
    $sOrder = "ORDER BY  ";
    for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
        if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" && preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])) {
            $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
            ".$_GET['sSortDir_'.$i] .", ";
        }
    }

    $sOrder = substr_replace($sOrder, "", -2);
    if ($sOrder == "ORDER BY") {
            $sOrder = "";
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
        $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3).") ";
}

// Do NOT show the items in PERSONAL FOLDERS
if (!empty($list_pf)) {
    if (!empty($sWhere)) {
        $sWhere .= " AND ";
    }
    $sWhere = "WHERE ".$sWhere."id_tree NOT IN (".$list_pf.") ";
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
$sql_f = "
        SELECT FOUND_ROWS()
";
$rResultFilterTotal = mysql_query($sql_f) or die(mysql_error());
$aResultFilterTotal = mysql_fetch_array($rResultFilterTotal);
$iFilteredTotal = $aResultFilterTotal[0];

/* Total data set length */
$sql_c = "
        SELECT COUNT(id)
        FROM   ".$pre."cache
";
$rResultTotal = mysql_query($sql_c) or die(mysql_error());
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

$rows = $db->fetchAllArray($sql);
foreach ($rows as $reccord) {
    $get_item_in_list = true;
    $sOutput_item = "[";

    //col1
    $sOutput_item .= '"<img src=\"includes/images/key__arrow.png\" onClick=\"javascript:window.location.href = &#039;index.php?page=items&amp;group='.$reccord['id_tree'].'&amp;id='.$reccord['id'].'&#039;;\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/eye.png\" onClick=\"javascript:see_item('.$reccord['id'].');\" style=\"cursor:pointer;\" />&nbsp;<img src=\"includes/images/key_copy.png\" onClick=\"javascript:copy_item('.$reccord['id'].');\" style=\"cursor:pointer;\" />", ';

    //col2
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'", ';

    //col3
    $sOutput_item .= '"'.str_replace("&amp;", "&", htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES)).'", ';

    //col4
    if (($reccord['perso']==1 && $reccord['author'] != $_SESSION['user_id']) || (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'], explode(';', $reccord['restricted_to'])))) {
        $get_item_in_list = false;
    } else {
        $txt = str_replace(array('\n', '<br />', '\\'), array(' ', ' ', ''), strip_tags($reccord['description']));
        if (strlen($txt) > 50) {
            $sOutput_item .= '"'.substr(stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)), 0, 50).'", ';
        } else {
            $sOutput_item .= '"'.stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)).'", ';
        }
    }

    //col5 - TAGS
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['tags']), ENT_QUOTES).'", ';

    //col6 - Prepare the Treegrid
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['folder']), ENT_QUOTES).'"';

    //Finish the line
    $sOutput_item .= '], ';

    if ($get_item_in_list == true) {
        $sOutput .= $sOutput_item;
    }
}
$sOutput = substr_replace($sOutput, "", -2);
$sOutput .= '] }';

echo $sOutput;
