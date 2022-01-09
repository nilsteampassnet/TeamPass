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
 * @file      user.logs.datatables.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit;
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';
// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

//Columns name
$aColumns = ['date', 'label', 'action'];
$aSortTypes = ['asc', 'desc'];
//init SQL variables
$sWhere = $sOrder = $sLimit = '';
/* BUILD QUERY */
//Paging
$sLimit = '';
if (isset($_GET['length']) === true && (int) $_GET['length'] !== -1) {
    $sLimit = ' LIMIT '.filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT).', '.filter_var($_GET['length'], FILTER_SANITIZE_NUMBER_INT).'';
}

//Ordering
if (isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], $aSortTypes)) {
    $sOrder = ' ORDER BY ';
    if (preg_match('#^(asc|desc)$#i', $_GET['order'][0]['dir'])
    ) {
        $sOrder .= ''.$aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '
        .filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING).', ';
    }

    $sOrder = substr_replace($sOrder, '', -2);
    if ($sOrder === ' ORDER BY') {
        $sOrder = '';
    }
} else {
    $sOrder = ' ORDER BY date DESC';
}

/*
   * Filtering
   * NOTE this does not match the built-in DataTables filtering which does it
   * word by word on any field. It's possible to do here, but concerned about efficiency
   * on very large tables, and MySQL's regex functionality is very limited
*/
$sWhere = '';
if (isset($_GET['letter']) === true
    && $_GET['letter'] !== ''
    && $_GET['letter'] !== 'None'
) {
    $sWhere = ' AND ';
    $sWhere .= $aColumns[1]." LIKE '".filter_var($_GET['letter'], FILTER_SANITIZE_STRING)."%' OR ";
    $sWhere .= ' EXISTS ('.$aColumns[2]." LIKE '".filter_var($_GET['letter'], FILTER_SANITIZE_STRING)."%')";
} elseif (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
    $sWhere = ' AND ';
    $sWhere .= $aColumns[1]." LIKE '".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%' OR ";
    $sWhere .= ' EXISTS ('.$aColumns[2]." LIKE '".filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING)."%')";
}

$rows = DB::query(
    'SELECT l.date as date, i.label as label, l.action as action
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE u.id = '.filter_var($_GET['userId'], FILTER_SANITIZE_NUMBER_INT).
    ' UNION '.
    'SELECT s.date AS date, s.label AS label, s.field_1 AS field1
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = '.filter_var($_GET['userId'], FILTER_SANITIZE_NUMBER_INT).
    (string) $sWhere
);
$iTotal = DB::count();
$rows = DB::query(
    'SELECT l.date as date, i.label as label, l.action as action, i.id as id
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE u.id = '.filter_var($_GET['userId'], FILTER_SANITIZE_NUMBER_INT).
    ' UNION
    SELECT s.date AS date, s.label AS label, s.field_1 AS field1, s.id as id
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = '.filter_var($_GET['userId'], FILTER_SANITIZE_NUMBER_INT).
    (string) $sOrder.
    (string) $sLimit
);
$iFilteredTotal = DB::count();
$sOutput = '{';
$sOutput .= '"aaData": ';
if (DB::count() > 0) {
    $sOutput .= '[';
} else {
    $sOutput .= '';
}

foreach ($rows as $record) {
    if (empty($record['action']) === true
        || $record['action'] === filter_var($_GET['userId'], FILTER_SANITIZE_NUMBER_INT)
    ) {
        if (strpos($record['label'], 'at_') === 0) {
            if (strpos($record['label'], '#') >= 0) {
                $col2 = preg_replace('/#[\s\S]+?#/', '', langHdl($record['label']));
            } else {
                $col2 = str_replace('"', '\"', langHdl($record['label']));
            }
        } else {
            $col2 = str_replace('"', '\"', $record['label']);
        }
        $col3 = '';
    } else {
        $col2 = langHdl($record['action']).' '.langHdl('id').' '.$record['id'];
        $col3 = str_replace('"', '\"', $record['label']);
    }

    $sOutput .= '["'.
        date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", '.
        '"'.$col2.'", '.
        '"'.$col3.'"],';
}

if (count($rows) > 0) {
    if (strrchr($sOutput, '[') !== '[') {
        $sOutput = substr_replace($sOutput, '', -1);
    }
    $sOutput .= ']';
} else {
    $sOutput .= '[]';
}

echo $sOutput.', '.
    '"sEcho": '.intval($_GET['draw']).', '.
    '"iTotalRecords": '.$iFilteredTotal.', '.
    '"iTotalDisplayRecords": '.$iTotal.'}';
