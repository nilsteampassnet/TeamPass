<?php
/**
 * @file          suggestion.queries.table.php
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

require_once('../sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
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
$aColumns = array('id', 'folder_id', 'label', 'description', 'author_id', 'comment');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sWhere = $sOrder = $sLimit = "";

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
    $sWhere = " WHERE ";
    for ($i=0; $i<count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3);
}

DB::query("SELECT id FROM ".$pre."suggestion");
$iTotal = DB::count();

$rows = DB::query(
    "SELECT *
    FROM ".$pre."suggestion
    $sWhere
    $sOrder
    $sLimit",
    array(
        '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '4' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        '5' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
    )
);
$iFilteredTotal = DB::count();

/*
   * Output
*/
$sOutput = '{';
$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
$sOutput .= '"iTotalRecords": '.$iTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
$sOutput .= '"aaData": ';

if ($iFilteredTotal > 0) {
    $sOutput .= '[';
}
foreach ($rows as $record) {
    $sOutput .= '["';

    //col1
    if ((isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1) || (isset($_SESSION['user_manager']) && $_SESSION['user_manager'] == 1)) {
        $sOutput .= '<img src=\"includes/images/envelope--plus.png\" onclick=\"validateSuggestion(\''.$record['id'].'\')\" style=\"cursor:pointer;\" />&nbsp;&nbsp;<img src=\"includes/images/envelope--minus.png\" onclick=\"deleteSuggestion(\''.$record['id'].'\')\" style=\"cursor:pointer;\" />';
    }
    if ($record['author_id'] == $_SESSION['user_id'] && (isset($_SESSION['user_read_only']) && $_SESSION['user_read_only'] == 1)) {
        $sOutput .= '<img src=\"includes/images/envelope--minus.png\" onclick=\"deleteSuggestion(\''.$record['id'].'\')\" style=\"cursor:pointer;\" />';
    }
    $sOutput .= '",';
    
    // col2
    $sOutput .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'",';

    // col3
    $sOutput .= '"'.htmlspecialchars(stripslashes($record['description']), ENT_QUOTES).'",';

    // col4
    $ret_cat = DB::queryFirstRow(
        "SELECT title FROM ".$pre."nested_tree
        WHERE id = %i",
        intval($record['folder_id'])
    );
    $sOutput .= '"'.htmlspecialchars(stripslashes($ret_cat['title']), ENT_QUOTES).'",';

    // col5
    $ret_author = DB::queryFirstRow(
        "SELECT login FROM ".$pre."users
        WHERE id = %i",
        intval($record['author_id'])
    );
    $sOutput .= '"'.html_entity_decode($ret_author['login'], ENT_NOQUOTES).'",';

    // col6
    $sOutput .= '"'.htmlspecialchars(stripslashes($record['comment']), ENT_QUOTES).'"';

    //Finish the line
    $sOutput .= '],';


}

if (count($rows) > 0) {
    $sOutput = substr_replace($sOutput, "", -1);
    $sOutput .= '] }';
} else {
    $sOutput .= '[] }';
}

echo $sOutput;
