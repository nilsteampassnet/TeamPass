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
 * @file      logs.datatables.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$session = SessionManager::getSession();
$lang = new Language(); 

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('utilities.logs') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

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
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    // Filtering
    $sWhere = "WHERE l.type = 'user_connection'";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace((string) $sWhere, '', -3).') ';
    }

    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
            FROM '.prefixTable('log_system').' as l
            INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id)'.
            $sWhere.
            $sOrder
    );
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
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    // Filtering
    $sWhere = " WHERE l.action = 'at_shown'";
    if ($_GET['sSearch'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i].' LIKE %ss_'.$i.' OR ';
        }
        $sWhere = substr_replace((string) $sWhere, '', -3).') ';
    }

    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
            FROM '.prefixTable('log_items').' as l
            INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
            INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)'.
            $sWhere,
            [
                '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            ]
    );
    $rows = DB::query(
        'SELECT l.date as date, u.login as login, i.label as label
            FROM '.prefixTable('log_items').' as l
            INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
            INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
            $sWhere
            $sOrder
            $sLimit',
            [
                '0' => filter_var($_GET['sSearch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                '1' => filter_var($_GET['sSearch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                '2' => filter_var($_GET['sSearch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            ]
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
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    // Filtering
    $sWhere = "WHERE l.action = 'at_copy'";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace((string) $sWhere, '', -3).') ';
    }

    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
            FROM '.prefixTable('log_items').' as l
            INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
            INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id) '.
            $sWhere.
            $sOrder
    );
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
    $aColumns = ['l.date', 'u.login', 'l.label', 'l.field_1'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    // Filtering
    $sWhere = "WHERE l.type IN ('admin_action', 'user_mngt')";
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace((string) $sWhere, '', -3).') ';
    }

    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) '.
        $sWhere
    );
    $rows = DB::query(
        'SELECT l.date as date, u.login as login, u.name AS name, u.lastname AS lastname, l.label as label, l.field_1 as field_1
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
        if ($record['label'] === 'at_user_added') {
            $cell = $lang->get('user_creation');
        } elseif ($record['label'] === 'at_user_deleted' || $record['label'] === 'user_deleted') {
            $cell = $lang->get('user_deletion');
        } elseif ($record['label'] === 'at_user_updated') {
            $cell = $lang->get('user_updated');
        } elseif (strpos($record['label'], 'at_user_email_changed') !== false) {
            $change = explode(':', $record['label']);
            $cell = $lang->get('log_user_email_changed').' '.$change[1];
        } elseif ($record['label'] === 'at_user_new_keys') {
            $cell = $lang->get('new_keys_generated');
        } elseif ($record['label'] === 'at_user_keys_download') {
            $cell = $lang->get('user_keys_downloaded');
        } elseif ($record['label'] === 'at_2fa_google_code_send_by_email') {
            $cell = $lang->get('mfa_code_send_by_email');
        } else {
            $cell = htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES);
        }
        $sOutput_item .= '"'.$cell.'" ';
        //col4
        if (empty($record['field_1']) === false) {
            // get user name
            $info = DB::queryfirstrow(
                'SELECT u.login as login, u.name AS name, u.lastname AS lastname
                    FROM '.prefixTable('users').' as u
                    WHERE u.id = %i',
                    $record['field_1']
            );
            $sOutput_item .= ', "'.(empty($info['name']) === false ? htmlspecialchars(stripslashes((string) $info['name'].' '.$info['lastname']), ENT_QUOTES) : 'Removed user ('.$record['field_1'].')').'" ';
        } else {
            $sOutput_item .= ', "" ';
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
/* ITEMS */
} elseif (isset($_GET['action']) && $_GET['action'] === 'items') {
    require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    //Columns name
    $aColumns = ['l.date', 'i.label', 'u.login', 'l.action', 'i.perso', 'i.id', 't.title'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    // Filtering
    $sWhere = '';
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' WHERE (';
        if (isset($_GET['search']['column']) === true && $_GET['search']['column'] !== 'all') {
            $sWhere .= $_GET['search']['column']." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%') ";
        } else {
            for ($i = 0; $i < count($aColumns); ++$i) {
                $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
            }
            $sWhere = substr((string) $sWhere, 0, -3).') ';
        }
    }
    
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
            FROM '.prefixTable('log_items').' AS l
            INNER JOIN '.prefixTable('items').' AS i ON (l.id_item=i.id)
            INNER JOIN '.prefixTable('users').' AS u ON (l.id_user=u.id)
            INNER JOIN '.prefixTable('nested_tree').' AS t ON (i.id_tree=t.id) '.
            $sWhere.
            $sOrder
    );

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
    // Output
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
        $sOutput_item .= '"'.htmlspecialchars(stripslashes($lang->get($record['action'])), ENT_QUOTES).'", ';
        //col5
        if ($record['perso'] === 1) {
            $sOutput_item .= '"'.htmlspecialchars(stripslashes($lang->get('yes')), ENT_QUOTES).'"';
        } else {
            $sOutput_item .= '"'.htmlspecialchars(stripslashes($lang->get('no')), ENT_QUOTES).'"';
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
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
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
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
            FROM '.prefixTable('log_system').' as l '.
            $sWhere
    );
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
            $sOutput .= '"'.$lang->get($record['label']).'", "'.$record['field_1'].'", ';
        } else {
            $sOutput .= '"'.$lang->get($record['label']).'", "", ';
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
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
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
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
            FROM '.prefixTable('log_system').' as l
            INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) '.
            $sWhere.
            $sOrder
    );
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
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
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
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }

    $iTotal = DB::queryFirstField(
        'SELECT COUNT(e.timestamp)
            FROM '.prefixTable('items_edition').' AS e
            INNER JOIN '.prefixTable('items').' as i ON (e.item_id=i.id)
            INNER JOIN '.prefixTable('users').' as u ON (e.user_id=u.id) '.
            $sWhere.
            $sOrder
    );
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
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    $sWhere = ' WHERE ((timestamp != "" AND session_end >= "'.time().'")';
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere = ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }
    $sWhere .= ') ';
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(timestamp)
            FROM '.prefixTable('users').' '.
            $sWhere
    );
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
            $user_role = $lang->get('god');
        } elseif ($lang->get('gestionnaire') === 1) {
            $user_role = $lang->get('gestionnaire');
        } else {
            $user_role = $lang->get('user');
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
} elseif (isset($_GET['action']) && $_GET['action'] === 'tasks_in_progress') {
    //Columns name
    $aColumns = ['p.increment_id', 'p.created_at', 'p.updated_at', 'p.process_type', 'p.is_in_progress'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    $sWhere = ' WHERE ((p.finished_at = "")';
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }
    $sWhere .= ') ';
    DB::debugmode(false);
    $iTotal = DB::queryFirstField(
    'SELECT COUNT(p.increment_id)
        FROM '.prefixTable('processes').' AS p 
        LEFT JOIN '.prefixTable('users').' AS u ON u.id = json_extract(p.arguments, "$[0]")'.
        $sWhere
    );
    $rows = DB::query(
        'SELECT p.*
            FROM '.prefixTable('processes').' AS p 
            LEFT JOIN '.prefixTable('users').' AS u ON u.id = json_extract(p.arguments, "$[0]")'.
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
        $sOutput .= '"<span data-done=\"'.$record['is_in_progress'].'\" data-type=\"'.$record['process_type'].'\" data-process-id=\"'.$record['increment_id'].'\"></span>", ';
        //col2
        $sOutput .= '"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['created_at']).'", ';
        //col3
        $sOutput .= '"'.($record['updated_at'] === '' ? '-' : date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['updated_at'])).'", ';
        //col4
        $sOutput .= '"'.$record['process_type'].'", ';
        // col5
        if (in_array($record['process_type'], array('create_user_keys', 'item_copy')) === true) {
            $data_user = DB::queryfirstrow(
                'SELECT name, lastname FROM ' . prefixTable('users') . '
                WHERE id = %i',
                json_decode($record['arguments'], true)['new_user_id']
            );
            $sOutput .= '"'.$data_user['name'].' '.$data_user['lastname'].'", ';
        } elseif ($record['process_type'] === 'send_email') {
            $sOutput .= '"'.json_decode($record['arguments'], true)['receiver_name'].'", ';
        }
        // col6
        $sOutput .= '""';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'tasks_finished') {
    //Columns name
    $aColumns = ['p.created_at', 'p.finished_at', 'p.process_type', 'u.name'];
    //Ordering
    if (isset($_GET['order'][0]['dir']) === true
        && in_array($_GET['order'][0]['dir'], $aSortTypes) === true
        && isset($_GET['order'][0]['column']) === true
        && (int) $_GET['order'][0]['column'] !== 0
        && $_GET['order'][0]['column'] !== 'asc'
    ) {
        $sOrder = 'ORDER BY  '.
            $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '.
            filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).' ';
    } else {
        $sOrder = 'ORDER BY  '.
            $aColumns[0].' DESC';
    }

    $sWhere = ' WHERE ((finished_at != "")';
    if (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
        $sWhere .= ' AND (';
        for ($i = 0; $i < count($aColumns); ++$i) {
            $sWhere .= $aColumns[$i]." LIKE '%".filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
        }
        $sWhere = substr_replace($sWhere, '', -3).') ';
    }
    $sWhere .= ') ';
    
    DB::debugmode(false);
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(p.increment_id)
            FROM '.prefixTable('processes').' AS p 
            LEFT JOIN '.prefixTable('users').' AS u ON u.id = json_extract(p.arguments, "$[0]")'.
            $sWhere
    );

    $rows = DB::query(
        'SELECT p.*
            FROM '.prefixTable('processes').' AS p 
            LEFT JOIN '.prefixTable('users').' AS u ON u.id = json_extract(p.arguments, "$[0]")'.
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
        // play with dates
        $start = strtotime(date('Y-m-d H:i:s', (int) $record['created_at']));
        $end = strtotime(date('Y-m-d H:i:s', (int) $record['finished_at']));
        
        $sOutput .= '[';
        //col1
        $sOutput .= '"", ';
        //col2
        $sOutput .= '"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['created_at']).'", ';
        //col3
        $sOutput .= is_null($record['started_at']) === false ?
            ('"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['started_at']).'", ') :
                ('"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['created_at']).'", ');
        //col4
        $sOutput .= '"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['finished_at']).'", ';
        // col7
        $sOutput .= '"'.gmdate('H:i:s', (int) $record['finished_at'] - (is_null($record['started_at']) === false ? (int) $record['started_at'] : (int) $record['created_at'])).'",';
        //col5
        if ($record['process_type'] === 'create_user_keys') {
            $processIcon = '<i class=\"fa-solid fa-user-gear infotip\" title=\"'.$lang->get('user_creation').'\"></i>';
        } else if ($record['process_type'] === 'send_email') {
            $processIcon = '<i class=\"fa-solid fa-envelope-circle-check infotip\" title=\"'.$lang->get('send_email_to_user').'\"></i>';
        } else if ($record['process_type'] === 'user_build_cache_tree') {
            $processIcon = '<i class=\"fa-solid fa-folder-tree infotip\" title=\"'.$lang->get('reload_user_cache_table').'\"></i>';
        } else {
            $processIcon = '<i class=\"fa-solid fa-question\"></i> ('.$record['process_type'].')';
        }
        $sOutput .= '"'.$processIcon.'", ';
        // col6
        $arguments = json_decode($record['arguments'], true);
        $newUserId = array_key_exists('new_user_id', $arguments) ? $arguments['new_user_id'] : null;
        if ($record['process_type'] === 'create_user_keys' && is_null($newUserId) === false && empty($newUserId) === false) {
            $data_user = DB::queryfirstrow(
                'SELECT name, lastname, login FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $newUserId
            );
            if (DB::count() > 0) {
                $txt = (isset($data_user['name']) === true ? $data_user['name'] : '').(isset($data_user['lastname']) === true ? ' '.$data_user['lastname'] : '');
                $sOutput .= '"'.(empty($txt) === false ? $txt : $data_user['login']).'"';
            } else {
                $sOutput .= '"<i class=\"fa-solid fa-user-slash\"></i>"';
            }
        } elseif ($record['process_type'] === 'send_email') {
            $user = json_decode($record['arguments'], true)['receiver_name'];
            $sOutput .= '"'.(is_null($user) === true || empty($user) === true ? '<i class=\"fa-solid fa-user-slash\"></i>' : $user).'"';
        } elseif ($record['process_type'] === 'user_build_cache_tree') {
            $user = json_decode($record['arguments'], true)['user_id'];
            $data_user = DB::queryfirstrow(
                'SELECT name, lastname, login FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $user
            );
            if (DB::count() > 0) {
                $txt = (isset($data_user['name']) === true ? $data_user['name'] : '').(isset($data_user['lastname']) === true ? ' '.$data_user['lastname'] : '');
                $sOutput .= '"'.(empty($txt) === false ? $txt : $data_user['login']).'"';
            } else {
                $sOutput .= '"<i class=\"fa-solid fa-user-slash\"></i>"';
            }
        } else {
            $sOutput .= '"<i class=\"fa-solid fa-user-slash\"></i>"';
        }
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
