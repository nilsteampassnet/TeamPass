<?php
/**
 * @file          datatable.item_edition.php
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
require_once('../SecureHandler.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");

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
$aColumns = array('e.timestamp', 'u.login', 'i.label', 'u.name', 'u.lastname');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sOrder = $sLimit = $sWhere = "";

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
            .mysqli_escape_string($link, $_GET['sSortDir_'.$i]) .", ";
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
        $sWhere .= $aColumns[$i]." LIKE %ss_".$aColumns[$i]." OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3).") ";
}

DB::query("SELECT timestamp FROM ".$pre."items_edition");
$iTotal = DB::count();

$rows = DB::query(
    "SELECT e.timestamp, e.item_id, e.user_id, u.login, u.name, u.lastname, i.label
    FROM ".$pre."items_edition AS e
    INNER JOIN ".$pre."items as i ON (e.item_id=i.id)
    INNER JOIN ".$pre."users as u ON (e.user_id=u.id)
    $sWhere
    $sOrder
    $sLimit",
    array(
        '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
    )
);

$iFilteredTotal = DB::count();

/*
 * Output
*/
$sOutput = '{';
//$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
$sOutput .= '"iTotalRecords": '.$iTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
$sOutput .= '"aaData": [ ';

foreach ($rows as $record) {
    $get_item_in_list = true;
    $sOutput_item = "[";

    //col1
    $sOutput_item .= '"<span onClick=\"killEntry(\'items_edited\', '.$record['item_id'].')\" style=\"cursor:pointer; font-size:16px;\" /><i class=\"fa fa-trash mi-red\"></i>", ';

    //col2
    $time_diff = intval(time() - $record['timestamp']);
    $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
    $minutesDiffRemainder = floor($time_diff % 3600 / 60);
    $sOutput_item .= '"'.$hoursDiff . "h " . $minutesDiffRemainder . "m".'", ';

    //col3
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes($record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).']", ';

    //col5 - TAGS
    $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'"';

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
