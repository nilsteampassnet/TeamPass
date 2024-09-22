<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      expired.datatables.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
$session = SessionManager::getSession();


// init
loadClasses('DB');
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('folders') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
//Columns name
$aColumns = ['a.item_id', 'i.label', 'a.del_value', 'i.id_tree'];
$aSortTypes = ['asc', 'desc'];
//init SQL variables
$sWhere = ' WHERE a.del_type = 2';
$sOrder = $sLimit = '';
// Is a date sent?
$dateCriteria = $request->query->get('dateCriteria');
if ($dateCriteria !== null && !empty($dateCriteria)) {
    $sWhere .= ' AND a.del_value < ' . round(filter_var($dateCriteria, FILTER_SANITIZE_NUMBER_INT) / 1000, 0);
}
//echo $sWhere;
/* BUILD QUERY */
//Paging
$sLimit = '';
$start = $request->query->getInt('start', 0);
$length = $request->query->getInt('length', -1);
if ($length !== -1) {
    $sLimit = ' LIMIT ' . $start . ', ' . $length;
}

//Ordering
if ($request->query->has('order')) {
    $order = $request->query->get('order');

    // Vérifiez si la direction 'dir' est définie et est valide
    if (isset($order[0]['dir']) && in_array($order[0]['dir'], $aSortTypes)) {
        $sOrder = 'ORDER BY ';
        $columnIndex = filter_var($order[0]['column'], FILTER_SANITIZE_NUMBER_INT);

        if (array_key_exists($columnIndex, $aColumns)) {
            $sOrder .= $aColumns[$columnIndex] . ' ' . $order[0]['dir'];
        }

        // Supprimez la virgule finale si elle existe
        $sOrder = rtrim($sOrder, ', ');

        if ($sOrder === 'ORDER BY') {
            $sOrder = '';
        }
    }
}

/*
   * Filtering
   * NOTE this does not match the built-in DataTables filtering which does it
   * word by word on any field. It's possible to do here, but concerned about efficiency
   * on very large tables, and MySQL's regex functionality is very limited
*/
// Vérifiez si 'letter' existe dans la requête GET
if ($request->query->has('letter')) {
    $letter = $request->query->filter('letter', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($letter !== '' && $letter !== 'None') {
        $sWhere .= ' AND ';
        $sWhere .= $aColumns[1] . " LIKE '" . $letter . "%' OR ";
        $sWhere .= $aColumns[2] . " LIKE '" . $letter . "%' OR ";
        $sWhere .= $aColumns[3] . " LIKE '" . $letter . "%' ";
    }
}

// Si 'letter' n'est pas défini ou est vide, vérifiez 'search[value]'
if (!isset($letter) || $letter === '') {
    if ($request->query->has('search[value]')) {
        $searchValue = $request->query->filter('search[value]', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($searchValue !== '') {
            $sWhere = ' AND ';
            $sWhere .= $aColumns[1] . " LIKE '" . $searchValue . "%' OR ";
            $sWhere .= $aColumns[2] . " LIKE '" . $searchValue . "%' OR ";
            $sWhere .= $aColumns[3] . " LIKE '" . $searchValue . "%' ";
        }
    }
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
echo '{"recordsTotal": ' . (int) $iTotal . ', "recordsFiltered": ' . (int) $iFilteredTotal . ', "data": ' . htmlspecialchars($sOutput);
