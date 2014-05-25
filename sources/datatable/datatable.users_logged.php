<?php
/**
 * @file          datatable.users_logged.php
 * @author        Nils Laumaillé
 * @version       2.1.20
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
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

//Connect to DB
$db = new SplClassLoader('Database\Core', '../../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Columns name
$aColumns = array('login', 'name', 'lastname', 'timestamp', 'last_connexion');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sOrder = $sLimit = "";

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ". filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT) .", ". filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT)."";
}

//Ordering

if (isset($_GET['iSortCol_0']) && in_array($_GET['iSortCol_0'], $aSortTypes)) {
    $sOrder = "ORDER BY  ";
	for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
		if (
			$_GET[ 'bSortable_'.filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT)] == "true" &&
			preg_match("#^(asc|desc)\$#i", $_GET['sSortDir_'.$i])
		) {
			$sOrder .= "".$aColumns[ filter_var($_GET['iSortCol_'.$i], FILTER_SANITIZE_NUMBER_INT) ]." "
			.mysql_real_escape_string($_GET['sSortDir_'.$i]) .", ";
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
$sWhere = " WHERE ((timestamp != '' AND session_end >= '".time()."')";
if ($_GET['sSearch'] != "") {
    $sWhere .= " AND (";
    for ($i=0; $i<count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3);
    $sWhere .= ") ";
}
$sWhere .= ") ";

$sql = "SELECT *
        FROM ".$pre."users
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
        FROM   ".$pre."users
        WHERE timestamp != '' AND session_end >= '".time()."'
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
    $sOutput_item .= '"<img src=\"includes/images/delete.png\" onClick=\"killEntry(\'disconnect_user\', '.$reccord['id'].')\" style=\"cursor:pointer;\" />", ';

    //col2
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes($reccord['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).']", ';

    //col3
    if ($reccord['admin'] == "1") {
        $user_role = $LANG['god'];
    } elseif ($reccord['gestionnaire'] == 1) {
        $user_role = $LANG['gestionnaire'];
    } else {
        $user_role = $LANG['user'];
    }
    $sOutput_item .= '"'.$user_role.'", ';

    //col4
    $time_diff = intval(time() - $reccord['timestamp']);
    $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
    $minutesDiffRemainder = floor($time_diff % 3600 / 60);
    $sOutput_item .= '"'.$hoursDiff . 'h ' . $minutesDiffRemainder . 'm" ';


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
