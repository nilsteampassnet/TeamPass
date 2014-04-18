<?php
/**
 * @file          datatable.users_logged.php
 * @author        Nils Laumaill�
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaill�
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

if (isset($_GET['action']) && $_GET['action'] == "connections") {
    //Columns name
    $aColumns = array('l.date', 'l.label', 'l.qui', 'u.login');

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
    $sWhere = "WHERE l.type = 'user_connection'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    $sql = "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
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
            SELECT COUNT(date)
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            WHERE l.type = 'user_connection'
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
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    $rows = $db->fetchAllArray($sql);
    if (count($rows) > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $reccord) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10),CHR(13)),array(' ',' '),htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).'"';

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
        $sLimit = "LIMIT ". $_GET['iDisplayStart'] .", ". $_GET['iDisplayLength'] ;
    }

    //Ordering
    if (isset($_GET['iSortCol_0'])) {
        $sOrder = "ORDER BY  ";
        for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
            if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true") {
                $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
                        ".mysql_real_escape_string($_GET['sSortDir_'.$i]) .", ";
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
            $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    $sql = "SELECT l.date as date, l.label as label, l.qui as who, u.login as login
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
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
            SELECT COUNT(*)
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            WHERE l.type = 'error'
    ";
    $rResultTotal = mysql_query($sql_c) or die(mysql_error());
    $aResultTotal = mysql_fetch_array($rResultTotal);
    $iTotal = $aResultTotal[0];

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    $rows = $db->fetchAllArray($sql);
    if (count($rows) > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $reccord) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10),CHR(13)),array(' ',' '),htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).'"';

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
        $sLimit = "LIMIT ". $_GET['iDisplayStart'] .", ". $_GET['iDisplayLength'] ;
    }

    //Ordering
    if (isset($_GET['iSortCol_0'])) {
        $sOrder = "ORDER BY  ";
        for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
            if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true") {
                $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
                        ".mysql_real_escape_string($_GET['sSortDir_'.$i]) .", ";
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
            $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    $sql = "SELECT l.date as date, u.login as login, i.label as label
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
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
            SELECT l.date as date, u.login as login, i.label as label
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            $sWhere
    ";
    $rResultTotal = mysql_query($sql_c) or die(mysql_error());
    $aResultTotal = mysql_fetch_array($rResultTotal);
    $iTotal = $aResultTotal[0];

    // Output
    if ($iTotal == "") {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    $rows = $db->fetchAllArray($sql);
    if (count($rows) > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $reccord) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'", ';

        //col2
        $sOutput .= '"'.str_replace(array(CHR(10),CHR(13)),array(' ',' '),htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES)).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).'"';

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
        $sLimit = "LIMIT ". $_GET['iDisplayStart'] .", ". $_GET['iDisplayLength'] ;
    }

    //Ordering
    if (isset($_GET['iSortCol_0'])) {
        $sOrder = "ORDER BY  ";
        for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
            if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true") {
                $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
                        ".mysql_real_escape_string($_GET['sSortDir_'.$i]) .", ";
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
            $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3).") ";
    }

    $sql = "SELECT l.date as date, u.login as login, i.label as label
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
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
            SELECT COUNT(*)
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
            WHERE l.action = 'at_copy'
    ";
    $rResultTotal = mysql_query($sql_c) or die(mysql_error());
    $aResultTotal = mysql_fetch_array($rResultTotal);
    $iTotal = $aResultTotal[0];

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';

    $rows = $db->fetchAllArray($sql);
    if (count($rows) > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $reccord) {
        $sOutput .= "[";

        //col1
        $sOutput .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'", ';

        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'", ';

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).'"';

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
        $sLimit = "LIMIT ". $_GET['iDisplayStart'] .", ". $_GET['iDisplayLength'] ;
    }

    //Ordering

    if (isset($_GET['iSortCol_0'])) {
        $sOrder = "ORDER BY  ";
        for ($i=0; $i<intval($_GET['iSortingCols']); $i++) {
            if ($_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true") {
                $sOrder .= $aColumns[ intval($_GET['iSortCol_'.$i]) ]."
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
    */
    $sWhere = "WHERE l.type = 'admin_action'";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " AND (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3);
        $sWhere .= ") ";
    }

    $sql = "SELECT l.date as date, u.login as login, l.label as label
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
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
            SELECT COUNT(*)
            FROM ".$pre."log_system as l
            INNER JOIN ".$pre."users as u ON (l.qui=u.id)
            $sWhere
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
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';

    $rows = $db->fetchAllArray($sql);
    foreach ($rows as $reccord) {
        $get_item_in_list = true;
        $sOutput_item = "[";

        //col1
        $sOutput_item .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'", ';

        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).'", ';

        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'" ';

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
    */
    $sWhere = "";
    if ($_GET['sSearch'] != "") {
        $sWhere .= " WHERE (";
        for ($i=0; $i<count($aColumns); $i++) {
            $sWhere .= $aColumns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
        }
        $sWhere = substr_replace($sWhere, "", -3);
        $sWhere .= ") ";
    }

    $sql = "SELECT l.date as date, u.login as login, i.label as label,
            i.perso as perso
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
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
            SELECT COUNT(*)
            FROM ".$pre."log_items as l
            INNER JOIN ".$pre."items as i ON (l.id_item=i.id)
            INNER JOIN ".$pre."users as u ON (l.id_user=u.id)
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
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';

    $rows = $db->fetchAllArray($sql);
    foreach ($rows as $reccord) {
        $get_item_in_list = true;
        $sOutput_item = "[";

        //col1
        $sOutput_item .= '"'.date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date']).'", ';

        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['login']), ENT_QUOTES).'", ';

        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($reccord['label']), ENT_QUOTES).'", ';

        //col4
        if ($reccord['perso'] == 1) {
            $sOutput_item .= '"'. htmlspecialchars(stripslashes($txt['yes']), ENT_QUOTES). '"';
        } else {
            $sOutput_item .= '"'. htmlspecialchars(stripslashes($txt['no']), ENT_QUOTES). '"';
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
