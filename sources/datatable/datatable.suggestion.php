<?php
/**
 * @file          suggestion.queries.table.php
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

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
header("Content-type: text/html; charset=utf-8");

//Connect to DB
$db = new SplClassLoader('Database\Core', '../../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

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
if ($_GET['sSearch'] != "") {
    $sWhere = " WHERE ";
    for ($i=0; $i<count($aColumns); $i++) {
        $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3);
}

// Do NOT show the kb the user iis not allowed to
if (!empty($list_pf)) {
    if (empty($sWhere)) {
        $sWhere = " WHERE ";
    } else {
        $sWhere .= "AND ";
    }
    $sWhere .= "id_tree NOT IN (".$list_pf.") ";
}

$sql = "SELECT SQL_CALC_FOUND_ROWS *
        FROM ".$pre."suggestion
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
        FROM   ".$pre."suggestion
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
$sOutput .= '"aaData": ';

$rows = $db->fetchAllArray($sql);
if (count($rows) > 0) {
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
    $ret_cat = $db->queryGetRow(
        "nested_tree",
        array(
            "title"
        ),
        array(
            "id" => intval($record['folder_id'])
        )
    );
    $sOutput .= '"'.htmlspecialchars(stripslashes($ret_cat[0]), ENT_QUOTES).'",';

    // col5
    $ret_author = $db->queryGetRow(
        "users",
        array(
            "login"
        ),
        array(
            "id" => intval($record['author_id'])
        )
    );
    $sOutput .= '"'.html_entity_decode($ret_author[0], ENT_NOQUOTES).'",';

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
