<?php
/**
 * @file          datatable.logs.php
 * @author        Nils Laumaillé
 * @version       2.1.22
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sources/sessions.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_views")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

global $k, $settings;
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

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

if (isset($_GET['action']) && $_GET['action'] == "connections") {
    //Columns name
    $aColumns = array('l.date', 'l.label', 'l.qui', 'u.login');

    //init SQL variables
    $sWhere = $sOrder = $sLimit = "";

    /* BUILD QUERY */
    //Paging
    $sLimit = "";
    if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
        $sLimit = "LIMIT ". mysqli_escape_string($link, $_GET['iDisplayStart']) .", ". mysqli_escape_string($link, $_GET['iDisplayLength']) ."" ;
    }

    //Ordering

    if (isset($_GET['iSortCol_0'])) {
        $sOrder = "ORDER BY  ";
        for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
            if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true") {
                $sOrder .= $aColumns[ intval(mysqli_escape_string($link, $_GET['iSortCol_'.$i])) ]."
                        '".mysqli_escape_string($link, $_GET['sSortDir_'.$i]) ."', ";
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
    $sWhere = "WHERE l.type = 'user_connection'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT date
        FROM ".$pre."log_system AS l
        INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
        WHERE l.type = %s",
        "user_connection"
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, l.label AS label, l.qui AS who, u.login AS login
        FROM ".$pre."log_system AS l
        INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
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
    foreach ($rows AS $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.$record['l.date'].'", ';

        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['l.label']), ENT_QUOTES).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['l.qui']), ENT_QUOTES).'",';

        //col4
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['u.login']), ENT_QUOTES).'",';

        //Finish the line
        $sOutput .= '],';
    }

    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
    /* ERRORS LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "errors") {
    //Columns name
    $aColumns = array('l.date', 'l.label', 'l.qui', 'u.login');

    //init SQL variables
    $sWhere = $sOrder = $sLimit = "";

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
                $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
                        ".mysqli_escape_string($link, $_GET['sSortDir_'.$i]) .", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE l.type = 'error'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT COUNT(*)
        FROM ".$pre."log_system AS l
        INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
        WHERE l.type = %s",
        "error"
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, l.label AS label, l.qui AS who, u.login AS login
        FROM ".$pre."log_system AS l
        INNER JOIN ".$pre."users AS u ON (l.qui=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows AS $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.$record['date'].'", ';

        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['who']), ENT_QUOTES).'",';

        //col4
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'",';

        //Finish the line
        $sOutput .= '],';
    }

    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
    /* ACCESS LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "access") {
    //Columns name
    $aColumns = array('l.date', 'l.label', 'u.login');

    //init SQL variables
    $sWhere = $sOrder = $sLimit = "";

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
                $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
                        ".mysqli_escape_string($link, $_GET['sSortDir_'.$i]) .", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE l.action = 'at_shown'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }
    
    DB::query(
        "SELECT *
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
        WHERE l.action = %s",
        "at_shown"
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, u.login AS login, i.label AS label
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows AS $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['l.label']), ENT_QUOTES).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['u.login']), ENT_QUOTES).'",';

        //Finish the line
        $sOutput .= '],';
    }

    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
    /* COPY LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "copy") {
    //Columns name
    $aColumns = array('l.date', 'i.label', 'u.login');

    //init SQL variables
    $sWhere = $sOrder = $sLimit = "";

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
                $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
                        ".mysqli_escape_string($link, $_GET['sSortDir_'.$i]) .", ";
            }
        }

        $sOrder = substr_replace($sOrder, "", -2);
        if ($sOrder == "ORDER BY") {
            $sOrder = "";
        }
    }

    // Filtering
    $sWhere = " WHERE l.action = 'at_copy'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }
    
    DB::query(
        "SELECT l.date AS date, u.login AS login, i.label AS label
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
        WHERE l.action = %s",
        "at_copy"
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, u.login AS login, i.label AS label
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows AS $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['l.label']), ENT_QUOTES).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['u.login']), ENT_QUOTES).'",';

        //Finish the line
        $sOutput .= '],';
    }

    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
    /* ADMIN LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "admin") {

} elseif (isset($_GET['action']) && $_GET['action'] == "items") {
    //Columns name
    $aColumns = array('l.date', 'u.login', 'i.label', 'i.perso');

    //init SQL variables
    $sOrder = $sLimit = "";

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
    */
    $sWhere = "";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " WHERE (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3);
        $sWhere .= ") ";
    }
    
    DB::query(
        "SELECT i.label
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)"
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, u.login AS login, i.label AS label, i.perso AS perso
        FROM ".$pre."log_items AS l
        INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '3' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
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
    $sOutput .= '"aaData": [ ';

    foreach ($rows AS $record) {
        $get_item_in_list = true;
        $sOutput_item = "[";

        //col1
        $sOutput_item .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'", ';

        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'", ';

        //col4
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['perso']), ENT_QUOTES).'"';


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
}



echo $sOutput;
