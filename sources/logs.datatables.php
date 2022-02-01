<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      logs.datatables.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (! isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || ! isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'utilities.logs', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit;
}

/*
 * Define Timezone
*/
if (isset($SETTINGS['timezone']) === true) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';
//Connect to DB
include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
// prepare the queries
if (isset($_GET['action']) === true) {
    //init SQL variables
    $sWhere = $sOrder = $sLimit = '';
    $aSortTypes = ['asc', 'desc'];
    /* BUILD QUERY */
    //Paging
    if (isset($_GET['length']) === true && (int) $_GET['length'] !== -1) {
        $sLimit = ' LIMIT '.filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT).', '.filter_var($_GET['length'], FILTER_SANITIZE_NUMBER_INT).'';
    }
}

if (isset($_GET['action']) === true && $_GET['action'] === 'connections') {
    //Columns name
    $aColumns = ['l.date', 'l.label', 'l.qui', 'u.login', 'u.name', 'u.lastname'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    // Filtering
    $sWhere = "WHERE l.type = 'user_connection'";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
        }
        $sWhere = substr_replace((string) $sWhere, '', -3).') ';
    }

    DB::query(
        'SELECT l.date as date, l.label as label, l.qui as who, u.login as login
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id)'.
        $sWhere.
        $sOrder
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT l.date as date, l.label as label, l.qui as who, 
        u.login as login, u.name AS name, u.lastname AS lastname
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id)'.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    /*
           * Output
        */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.str_replace([chr(10), chr(13)], [' ', ' '], htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES)).'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

    /* ERRORS LOG */
} elseif (isset($_GET['action']) && $_GET['action'] === 'access') {
    //Columns name
    $aColumns = ['l.date', 'i.label', 'u.login'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    // Filtering
    $sWhere = " WHERE l.action = 'at_shown'";
    if ($_GET['sSearch'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i].' LIKE %ss_'.$i.' OR ';
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    DB::query(
        'SELECT *
        FROM '.prefixTable('log_items').' as l
        INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)'.
        $sWhere,
        [
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        ]
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT l.date as date, u.login as login, i.label as label
        FROM '.prefixTable('log_items').' as l
        INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
        $sWhere
        $sOrder
        $sLimit',
        [
            '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
            '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_STRING),
        ]
);
    $iFilteredTotal = DB::count();
    // Output
    if ($iTotal === '') {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.str_replace([chr(10), chr(13)], [' ', ' '], htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES)).'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).'"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

    /* COPY LOG */
} elseif (isset($_GET['action']) && $_GET['action'] === 'copy') {
    //Columns name
    $aColumns = ['l.date', 'i.label', 'u.login'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    // Filtering
    $sWhere = "WHERE l.action = 'at_copy'";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    DB::query(
        'SELECT *
        FROM '.prefixTable('log_items').' as l
        INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id) '.
        $sWhere.
        $sOrder
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT l.date as date, u.login as login, u.name AS name, u.lastname AS lastname, i.label as label
        FROM '.prefixTable('log_items').' as l
        INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id) '.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES).'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).'"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

    /*
    * ADMIN LOG
     */
} elseif (isset($_GET['action']) && $_GET['action'] === 'admin') {
    //Columns name
    $aColumns = ['l.date', 'u.login', 'l.label'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    // Filtering
    $sWhere = "WHERE l.type = 'admin_action'";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    DB::query(
        'SELECT *
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) '.
        $sWhere
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT l.date as date, u.login as login, u.name AS name, u.lastname AS lastname, l.label as label
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) '.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    /*
         * Output
        */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';
    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = '[';
        //col1
        $sOutput_item .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).'", ';
        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES).'" ';
        //Finish the line
        $sOutput_item .= '], ';
        if ($get_item_in_list === true) {
            $sOutput .= $sOutput_item;
        }
    }
    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, '', -2);
    }
    $sOutput .= '] }';
/* ITEMS */
} elseif (isset($_GET['action']) && $_GET['action'] === 'items') {
    require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    //Columns name
    $aColumns = ['l.date', 'i.label', 'u.login', 'l.action', 'i.perso', 'i.id', 't.title'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    // Filtering
    $sWhere = '';
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' WHERE (';
        if (isset($_GET['search']['column']) === true && $_GET['search']['column'] !== 'all') {
            $sWhere .= $_GET['search']['column']." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%') ";
        } else {
            for ($i = 0; $i < count($aColumns); ++$i) {
                $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
            }
            $sWhere = substr_replace($sWhere, '', -3).') ';
        }
    }

    DB::query(
        'SELECT *
        FROM '.prefixTable('log_items').' AS l
        INNER JOIN '.prefixTable('items').' AS i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' AS u ON (l.id_user=u.id)
        INNER JOIN '.prefixTable('nested_tree').' AS t ON (i.id_tree=t.id) '.
        $sWhere.
        $sOrder
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT l.date AS date, u.login AS login, u.name AS name, u.lastname AS lastname, i.label AS label,
            i.perso AS perso, l.action AS action, t.title AS folder,
            i.id AS id
            FROM '.prefixTable('log_items').' AS l
            INNER JOIN '.prefixTable('items').' AS i ON (l.id_item=i.id)
            INNER JOIN '.prefixTable('users').' AS u ON (l.id_user=u.id)
            INNER JOIN '.prefixTable('nested_tree').' AS t ON (i.id_tree=t.id) '.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    /*
         * Output
        */
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';
    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = '[';
        //col1
        $sOutput_item .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes((string) $record['id']), ENT_QUOTES).'", ';
        //col3
        $sOutput_item .= '"'.htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES).'", ';
        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes((string) $record['folder']), ENT_QUOTES).'", ';
        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']", ';
        //col4
        $sOutput_item .= '"'.htmlspecialchars(stripslashes(langHdl($record['action'])), ENT_QUOTES).'", ';
        //col5
        if ($record['perso'] === 1) {
            $sOutput_item .= '"'.htmlspecialchars(stripslashes(langHdl('yes')), ENT_QUOTES).'"';
        } else {
            $sOutput_item .= '"'.htmlspecialchars(stripslashes(langHdl('no')), ENT_QUOTES).'"';
        }

        //Finish the line
        $sOutput_item .= '], ';
        if ($get_item_in_list === true) {
            $sOutput .= $sOutput_item;
        }
    }
    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, '', -2);
    }
    $sOutput .= '] }';
/* FAILED AUTHENTICATION */
} elseif (isset($_GET['action']) && $_GET['action'] === 'failed_auth') {
    //Columns name
    $aColumns = ['l.date', 'l.label', 'l.qui', 'l.field_1'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    /*
       * Filtering
       * NOTE this does not match the built-in DataTables filtering which does it
       * word by word on any field. It's possible to do here, but concerned about efficiency
       * on very large tables, and MySQL's regex functionality is very limited
    */
    $sWhere = "WHERE l.type = 'failed_auth'";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    DB::query(
        'SELECT *
        FROM '.prefixTable('log_system').' as l '.
        $sWhere
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT l.date as auth_date, l.label as label, l.qui as who, l.field_1
        FROM '.prefixTable('log_system').' as l '.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    // Output
    if ($iTotal === '') {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['auth_date']).'", ';
        //col2 - 3
        if ($record['label'] === 'password_is_not_correct' || $record['label'] === 'user_not_exists') {
            $sOutput .= '"'.langHdl($record['label']).'", "'.$record['field_1'].'", ';
        } else {
            $sOutput .= '"'.langHdl($record['label']).'", "", ';
        }

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['who']), ENT_QUOTES).'"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'errors') {
    //Columns name
    $aColumns = ['l.date', 'l.label', 'l.qui', 'u.login', 'u.name', 'u.lastname'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    /*
       * Filtering
       * NOTE this does not match the built-in DataTables filtering which does it
       * word by word on any field. It's possible to do here, but concerned about efficiency
       * on very large tables, and MySQL's regex functionality is very limited
    */
    $sWhere = "WHERE l.type = 'error'";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    DB::query(
        'SELECT *
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) '.
        $sWhere.
        $sOrder
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT l.date as date, l.label as label, l.qui as who,
        u.login as login, u.name AS name, u.lastname AS lastname
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) '.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.addslashes(str_replace([chr(10), chr(13), '`', '<br />@', "'"], ['<br>', '<br>', "'", '', '&#39;'], $record['label'])).'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'items_in_edition') {
    //Columns name
    $aColumns = ['e.timestamp', 'u.login', 'i.label', 'u.name', 'u.lastname'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    /*
       * Filtering
       * NOTE this does not match the built-in DataTables filtering which does it
       * word by word on any field. It's possible to do here, but concerned about efficiency
       * on very large tables, and MySQL's regex functionality is very limited
    */
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere = ' WHERE (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    DB::query(
        'SELECT e.timestamp
        FROM '.prefixTable('items_edition').' AS e
        INNER JOIN '.prefixTable('items').' as i ON (e.item_id=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (e.user_id=u.id) '.
        $sWhere.
        $sOrder
    );
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT e.timestamp, e.item_id, e.user_id, u.login, u.name, u.lastname, i.label
        FROM '.prefixTable('items_edition').' AS e
        INNER JOIN '.prefixTable('items').' as i ON (e.item_id=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (e.user_id=u.id) '.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-id=\"'.$record['item_id'].'\">", ';
        //col2
        $time_diff = intval(time() - $record['timestamp']);
        $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
        $minutesDiffRemainder = floor($time_diff % 3600 / 60);
        $sOutput .= '"'.$hoursDiff.'h '.$minutesDiffRemainder.'m'.'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']", ';
        //col5 - TAGS
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES).'"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'users_logged_in') {
    //Columns name
    $aColumns = ['login', 'name', 'lastname', 'timestamp', 'last_connexion'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).' ';
    }

    $sWhere = ' WHERE ((timestamp != "" AND session_end >= "'.time().'")';
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere = ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }
    $sWhere .= ') ';
    DB::query(
    'SELECT COUNT(timestamp)
        FROM '.prefixTable('users').' '.
        $sWhere
);
    $iTotal = DB::count();
    $rows = DB::query(
    'SELECT *
        FROM '.prefixTable('users').' '.
        $sWhere.
        $sOrder.
        $sLimit
);
    $iFilteredTotal = DB::count();
    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '.intval($_GET['draw']).', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-id=\"'.$record['id'].'\">", ';
        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']", ';
        //col3
        if ($record['admin'] === '1') {
            $user_role = langHdl('god');
        } elseif (langHdl('gestionnaire') === 1) {
            $user_role = langHdl('gestionnaire');
        } else {
            $user_role = langHdl('user');
        }
        $sOutput .= '"'.$user_role.'", ';
        //col4
        $time_diff = intval(time() - $record['timestamp']);
        $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
        $minutesDiffRemainder = floor($time_diff % 3600 / 60);
        $sOutput .= '"'.$hoursDiff.'h '.$minutesDiffRemainder.'m" ';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
}

echo $sOutput;
