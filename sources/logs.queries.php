<?php
/**
 * @file          logs.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.24
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
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_views")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset==utf-8");

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

//init SQL variables
$sOrder = $sLimit = "";
$where = new WhereClause('and');
$where->add('id_tree IN %ls', $_SESSION['groupes_visibles']);   //limit search to the visible folders

//get list of personal folders
$array_pf = array();
$list_pf = "";
$rows = DB::query("SELECT id FROM ".prefix_table("nested_tree")." WHERE personal_folder=%i AND NOT title = %s", 1, $_SESSION['user_id']);
foreach ($rows as $reccord) {
    if (!in_array($reccord['id'], $array_pf)) {
        //build an array of personal folders ids
        array_push($array_pf, $reccord['id']);
        //build also a string with those ids
        if (empty($list_pf)) {
            $list_pf = $reccord['id'];
        } else {
            $list_pf .= ','.$reccord['id'];
        }
    }
}

/* BUILD QUERY */
//Paging
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $sLimit = "LIMIT ". mysqli_real_escape_string($link, filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT));
}

//Ordering

if (isset($_GET['iSortCol_0'])) {
    $sOrder = "ORDER BY  ";
    for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
        if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true") {
            $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
                    ".mysqli_escape_string($link, $_GET['sSortDir_'.$i]) .", ";
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
    //$sWhere .= " AND ";
    $subclause = $where->addClause('or');
    for ($i=0; $i<count($aColumns); $i++) {
        $subclause->add($aColumns[$i]." LIKE %ls", $_GET['sSearch']);
    }
}

// Do NOT show the items in PERSONAL FOLDERS
if (!empty($list_pf)) {
    $where->add('id_tree NOT IN %ls', $list_pf);
}

$rows = DB::query("SELECT * FROM ".prefix_table("cache")." WHERE %l ".$sOrder." ".$sLimit, $where);
$iFilteredTotal = DB::count();

DB::query("SELECT * FROM ".prefix_table("cache"));
$iTotal = DB::count();

/*
   * Output
*/
$sOutput = '{';
$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
$sOutput .= '"iTotalRecords": '.$iTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
$sOutput .= '"aaData": [ ';

//$rows = DB::fetchAllArray($sql);
foreach ($rows as $reccord) {
    $sOutput .= "[";

    //col1
    $sOutput .= '"<img src=\"includes/images/key__arrow.png\" onClick=\"javascript:window.location.href = &#039;index.php?page=items&amp;group='.$reccord['id_tree'].'&amp;id='.$reccord['id'].'&#039;;\" style=\"cursor:pointer;\" /><img src=\"includes/images/key_copy.png\" onClick=\"javascript:copy_item('.$reccord['id'].');\" style=\"cursor:pointer;\" />",';

    //col2
    $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'",';

    //col3
    $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).'",';

    //col4
    if (
        $reccord['perso']==1
        || !empty($reccord['restricted_to'])
        || !in_array($_SESSION['user_id'], explode(';', $reccord['restricted_to'])) != $_SESSION['user_id']
    ) {
        $sOutput .= '"<img src=\"includes/images/lock.png\" />",';
    } else {
        $txt = str_replace(array('\n', '<br />', '\\'), array(' ', ' ', ''), strip_tags(mysqli_escape_string($link, $reccord['description'])));
        if (strlen($txt) > 50) {
            $sOutput .= '"'.(substr(htmlspecialchars(stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)), ENT_QUOTES), 0, 50)).'",';
        } else {
            $sOutput .= '"'.(htmlspecialchars(stripslashes(preg_replace('/<[^>]*>|[\t]/', '', $txt)), ENT_QUOTES)).'",';
        }
    }

    //col5 - TAGS
    $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['tags']), ENT_QUOTES).'",';

    //col6 - Prepare the Treegrid
    $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['folder']), ENT_QUOTES).'"';

    //Finish the line
    $sOutput .= '],';


}
$sOutput = substr_replace($sOutput, "", -1);
$sOutput .= '] }';

echo $sOutput;
