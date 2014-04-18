<?php
/**
 * @file          datatable.item_edition.php
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
require_once('../sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");

//Connect to DB
$db = new SplClassLoader('Database\Core', '../../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Columns name
$aColumns = array('e.timestamp', 'u.login', 'i.label', 'u.name', 'u.lastname');

//init SQL variables
$sOrder = $sLimit = $sWhere = "";

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ". $_GET['iDisplayStart'] .", ". $_GET['iDisplayLength'] ;
}

//Ordering

if (isset($_GET['iSortCol_0'])) {
    $sOrder = "ORDER BY  ";
    for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
        if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true") {
            $sOrder .= $aColumns[ intval(mysql_real_escape_string($_GET['iSortCol_'.$i])) ]."
            ".mysql_real_escape_string($_GET['sSortDir_'.$i]) .", ";
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
    $sWhere = " WHERE (";
    for ($i=0; $i<count($aColumns); $i++) {
    }
    $sWhere = substr_replace($sWhere, "", -3).") ";
}

$sql = "SELECT e.timestamp,e.item_id, e.user_id, u.login, u.name, u.lastname, i.label
        FROM ".$pre."items_edition AS e
        INNER JOIN ".$pre."items as i ON (e.item_id=i.id)
        INNER JOIN ".$pre."users as u ON (e.user_id=u.id)
        $sWhere
        $sOrder
        $sLimit";

$rResult = mysql_query($sql) or die(mysql_error()." ; ".$sql);

/* Data set length after filtering */
$sql_f = "
        SELECT FOUND_ROWS()
";
$rResultFilterTotal = mysql_query($sql_f) or die(mysql_error());
$aResultFilterTotal = mysql_fetch_array($rResultFilterTotal);
$iFilteredTotal = $aResultFilterTotal[0];

/* Total data set length */
$sql_c = "
        SELECT COUNT(timestamp)
        FROM   ".$pre."items_edition
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
    $sOutput_item .= '"<img src=\"includes/images/delete.png\" onClick=\"killEntry(\'items_edited\', '.$reccord['item_id'].')\" style=\"cursor:pointer;\" />", ';

    //col2
    $time_diff = intval(time() - $reccord['timestamp']);
    $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
    $minutesDiffRemainder = floor($time_diff % 3600 / 60);
    $sOutput_item .= '"'.$hoursDiff . "h " . $minutesDiffRemainder . "m".'", ';

    //col3
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes($reccord['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).']", ';

    //col5 - TAGS
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'"';

    //Finish the line
    $sOutput_item .= '], ';

    if ($get_item_in_list == true) {
        $sOutput .= $sOutput_item;
    }
}
if ($iFilteredTotal > 0) {
    $sOutput = substr_replace($sOutput, "", -2);
}
$sOutput .= '] }';

echo $sOutput;
