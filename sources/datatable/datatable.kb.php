<?php
/**
 * @file          kb.queries.table.php
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
$aColumns = array('id', 'category_id', 'label', 'description', 'author_id');

//init SQL variables
$sWhere = $sOrder = $sLimit = "";

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ". mysql_real_escape_string($_GET['iDisplayStart']) .", "
            . mysql_real_escape_string($_GET['iDisplayLength']) ;
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
        FROM ".$pre."kb
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
        FROM   ".$pre."kb
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
foreach ($rows as $reccord) {
    $sOutput .= "[";

    //col1
    $sOutput .= '"<img src=\"includes/images/direction_arrow.png\" onclick=\"openKB(\''.$reccord['id'].'\')\" style=\"cursor:pointer;\" />';
    if ($reccord['anyone_can_modify'] == 1 || $reccord['author_id'] == $_SESSION['user_id']) {
        $sOutput .= '<img src=\"includes/images/direction_minus.png\" onclick=\"deleteKB(\''.$reccord['id'].'\')\" style=\"cursor:pointer;\" />';
    }
    $sOutput .= '",';

    //col2
    //$ret_cat = $db->fetchRow("SELECT category FROM ".$pre."kb_categories WHERE id = ".$reccord['category_id']);
    $ret_cat = $db->queryGetRow(
        "kb_categories",
        array(
            "category"
        ),
        array(
            "id" => intval($reccord['category_id'])
        )
    );
    $sOutput .= '"'.htmlspecialchars(stripslashes($ret_cat[0]), ENT_QUOTES).'",';

    //col3
    $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'",';
    /*
    //col4
    if (!empty($reccord['restricted_to']) || !in_array($_SESSION['user_id'],explode(';',$reccord['restricted_to'])) != $_SESSION['user_id']) {
        $sOutput .= '"<img src=\"includes/images/lock.png\" />",';
    } else {
        $sOutput .= '"'.substr(trim(html_entity_decode(strip_tags(str_replace(array('\n'), array(''), $reccord['description'])), ENT_NOQUOTES, $k['charset'])), 0, 50).'",';
    }
    */
    //col5
    //$ret_author = $db->fetchRow("SELECT login FROM ".$pre."users WHERE id = ".$reccord['author_id']);
    $ret_author = $db->queryGetRow(
        "users",
        array(
            "login"
        ),
        array(
            "id" => intval($reccord['author_id'])
        )
    );
    $sOutput .= '"'.html_entity_decode($ret_author[0], ENT_NOQUOTES).'"';

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
