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
 * @file      expired.datatables.php
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
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';
// Connect to mysql server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
// Class loader
require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'] . '/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
//Columns name
$aColumns = ['a.item_id', 'i.label', 'a.del_value', 'i.id_tree'];
$aSortTypes = ['asc', 'desc'];
//init SQL variables
$sWhere = ' WHERE a.del_type = 2';
$sOrder = $sLimit = '';
// Is a date sent?
if (isset($_GET['dateCriteria']) === true && empty($_GET['dateCriteria']) === false) {
    $sWhere .= ' AND a.del_value < ' . round(filter_var($_GET['dateCriteria'], FILTER_SANITIZE_NUMBER_INT) / 1000, 0);
}
//echo $sWhere;
/* BUILD QUERY */
//Paging
$sLimit = '';
if (isset($_GET['length']) === true && (int) $_GET['length'] !== -1) {
    $sLimit = ' LIMIT ' . filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT) . ', ' . filter_var($_GET['length'], FILTER_SANITIZE_NUMBER_INT) . '';
}

//Ordering
if (isset($_GET['order'][0]['dir']) && in_array($_GET['order'][0]['dir'], $aSortTypes)) {
    $sOrder = 'ORDER BY  ';
    if (preg_match('#^(asc|desc)$#i', $_GET['order'][0]['column'])) {
        $sOrder .= '' . $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)] . ' '
            . filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_STRING) . ', ';
    }

    $sOrder = substr_replace($sOrder, '', -2);
    if ($sOrder === 'ORDER BY') {
        $sOrder = '';
    }
}

/*
   * Filtering
   * NOTE this does not match the built-in DataTables filtering which does it
   * word by word on any field. It's possible to do here, but concerned about efficiency
   * on very large tables, and MySQL's regex functionality is very limited
*/
if (
    isset($_GET['letter']) === true
    && $_GET['letter'] !== ''
    && $_GET['letter'] !== 'None'
) {
    $sWhere .= ' AND ';
    $sWhere .= $aColumns[1] . " LIKE '" . filter_var($_GET['letter'], FILTER_SANITIZE_STRING) . "%' OR ";
    $sWhere .= $aColumns[2] . " LIKE '" . filter_var($_GET['letter'], FILTER_SANITIZE_STRING) . "%' OR ";
    $sWhere .= $aColumns[3] . " LIKE '" . filter_var($_GET['letter'], FILTER_SANITIZE_STRING) . "%' ";
} elseif (isset($_GET['search']['value']) === true && $_GET['search']['value'] !== '') {
    $sWhere = ' AND ';
    $sWhere .= $aColumns[1] . " LIKE '" . filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING) . "%' OR ";
    $sWhere .= $aColumns[2] . " LIKE '" . filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING) . "%' OR ";
    $sWhere .= $aColumns[3] . " LIKE '" . filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING) . "%' ";
}

$rows = DB::query(
    'SELECT a.item_id, i.label, a.del_value, i.id_tree
    FROM ' . prefixTable('automatic_del') . ' AS a
    INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = a.item_id)' .
    $sWhere.
    (string) $sOrder
);
$iTotal = DB::count();
$rows = DB::query(
    'SELECT a.item_id, i.label, a.del_value, i.id_tree
    FROM ' . prefixTable('automatic_del') . ' AS a
    INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = a.item_id)' .
        $sWhere .
        $sLimit
);
$iFilteredTotal = DB::count();
/*
   * Output
*/

if ($iTotal > 0) {
    $sOutput = '[';
} else {
    $sOutput = '';
}

foreach ($rows as $record) {
    // start the line
    $sOutput .= '[';
    // Column 1
    $sOutput .= '"<i class=\"fas fa-external-link-alt pointer text-primary mr-2\" onclick=\"showItemCard($(this))\" data-item-id=\"' . $record['item_id'] . '\"  data-item-tree-id=\"' . $record['id_tree'] . '\"></i>", ';
    // Column 2
    $sOutput .= '"' . $record['label'] . '", ';
    // Column 3
    $sOutput .= '"' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['del_value']) . '", ';
    // Column 4
    $path = [];
    $treeDesc = $tree->getPath($record['id_tree'], true);
    foreach ($treeDesc as $t) {
        array_push($path, $t->title);
    }
    $sOutput .= '"' . implode('<i class=\"fas fa-angle-right ml-1 mr-1\"></i>', $path) . '"],';
}

if ($iTotal > 0) {
    if (strrchr($sOutput, '[') !== '[') {
        $sOutput = substr_replace($sOutput, '', -1);
    }
    $sOutput .= ']}';
} else {
    $sOutput .= '[] }';
}

// finalize output
echo '{"recordsTotal": ' . $iTotal . ', "recordsFiltered": ' . $iFilteredTotal . ', "data": ' . $sOutput;
