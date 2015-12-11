<?php
/**
 * @file          datatable.users_logged.php
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
    $sWhere = "WHERE l.type = 'user_connection'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query("SELECT date
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            WHERE l.type = 'user_connection'");
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
        FROM ".$pre."log_system as l
        INNER JOIN ".$pre."users as u ON (l.qui=u.id)
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
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10),CHR(13)),array(' ',' '),htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
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
        $sLimit = "LIMIT ". mysqli_real_escape_string($link, filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT)) ;
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
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query("SELECT *
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            WHERE l.type = 'error'");
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
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
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10),CHR(13)),array(' ',' '),htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
    /* ACCESS LOG */
} elseif (isset($_GET['action']) && $_GET['action'] == "access") {
    //Columns name
    $aColumns = array('l.date', 'i.label', 'u.login');

    //init SQL variables
    $sWhere = $sOrder = $sLimit = "";

    /* BUILD QUERY */
    //Paging
    $sLimit = "";
    if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
        $sLimit = "LIMIT ". mysqli_real_escape_string($link, filter_var($_GET['iDisplayStart'], FILTER_SANITIZE_NUMBER_INT)) .", ". mysqli_real_escape_string($link, filter_var($_GET['iDisplayLength'], FILTER_SANITIZE_NUMBER_INT)) ;
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
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query(
        "SELECT l.date as date, u.login as login, i.label as label
        FROM ".$pre."log_items as l
        INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users as u ON (l.id_user=u.id)".
        $sWhere,
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, u.login as login, i.label as label
        FROM ".$pre."log_items as l
        INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    // Output
    if ($iTotal == "") {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10),CHR(13)),array(' ',' '),htmlspecialchars(stripslashes($record['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
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

    // Filtering
    $sWhere = " WHERE l.action = 'at_copy'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    DB::query("SELECT *
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            WHERE l.action = 'at_copy'");
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, u.login as login, i.label as label
        FROM ".$pre."log_items as l
        INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
        INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'"';

        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, "", -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
    /*
     * ADMIN LOG
     **/
} elseif (isset($_GET['action']) && $_GET['action'] == "admin") {
    //Columns name
    $aColumns = array('l.date', 'u.login', 'l.label');

    //init SQL variables
    $sOrder = $sLimit = "";

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
    */
    $sWhere = "WHERE l.type = 'admin_action'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3);
        $sWhere .= ") ";
    }

    DB::query(
        "SELECT *
        FROM ".$pre."log_system as l
        INNER JOIN ".$pre."users as u ON (l.qui=u.id)".
        $sWhere,
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date as date, u.login as login, l.label as label
        FROM ".$pre."log_system as l
        INNER JOIN ".$pre."users as u ON (l.qui=u.id)
        $sWhere
        $sOrder
        $sLimit",
        array(
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING)
        )
    );

    $iFilteredTotal = DB::count();

    /*
     * Output
    */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';

    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = "[";

        //col1
        $sOutput_item .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'", ';

        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['label']), ENT_QUOTES).'" ';

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
} elseif (isset($_GET['action']) && $_GET['action'] == "items") {
    //Columns name
    $aColumns = array('l.date', 'i.label', 'u.login', 'l.action', 'i.perso');

    //init SQL variables
    $sOrder = $sLimit = "";

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
        } else {
			$sOrder .= ", l.date ASC";
		}
    }

    /*
     * Filtering
    */
    $sWhere = "";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " WHERE (";
        for ($i=1; $i<count($aColumns)-1; $i++) {
            $sWhere .= $aColumns[$i]." LIKE %ss_".$i." OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3);
        $sWhere .= ") ";
    }

    DB::query("SELECT *
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)"
    );
    $iTotal = DB::count();

    $rows = DB::query(
        "SELECT l.date AS date, u.login AS login, i.label AS label,
            i.perso AS perso, l.action AS action, t.title AS folder
            FROM ".$pre."log_items AS l
            INNER JOIN ".$pre."items AS i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users AS u ON (l.id_user=u.id)
            INNER JOIN ".$pre."nested_tree AS t ON (i.id_tree=t.id)
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
	//DB::debugMode(true);
    $iFilteredTotal = DB::count();

    /*
     * Output
    */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';

    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = "[";

        //col1
        $sOutput_item .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date']).'", ';

        //col3
        $sOutput_item .= '"'.(stripslashes("<b>".$record['label']."</b>&nbsp;<span style='font-size:10px;font-style:italic;'><i class='fa fa-folder-o'></i>&nbsp;".$record['folder']."</span>")).'", ';

        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($record['login']), ENT_QUOTES).'", ';

        //col4
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($LANG[$record['action']]), ENT_QUOTES).'", ';

        //col5
        if ($record['perso'] == 1) {
            $sOutput_item .= '"'. htmlspecialchars(stripslashes($LANG['yes']), ENT_QUOTES). '"';
        } else {
            $sOutput_item .= '"'. htmlspecialchars(stripslashes($LANG['no']), ENT_QUOTES). '"';
        }

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
