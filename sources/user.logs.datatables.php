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
 * @file      user.logs.datatables.php
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

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
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
    $checkUserAccess->userAccessPage('users') === false ||
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

// --------------------------------- //
// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

$params = $request->query->all();

//Columns name
$aColumns = ['date', 'label', 'action'];
$aSortTypes = ['asc', 'desc'];
//init SQL variables
$sWhere = $sOrder = $sLimit = '';
/* BUILD QUERY */
//Paging
$sLimit = '';
if (isset($params['length']) && (int) $params['length'] !== -1) {
    $sLimit = ' LIMIT '.filter_var($params['start'], FILTER_SANITIZE_NUMBER_INT).', '.filter_var($params['length'], FILTER_SANITIZE_NUMBER_INT);
}

//Ordering
if (isset($params['order'][0]['dir']) && in_array($params['order'][0]['dir'], $aSortTypes)) {
    $sOrder = ' ORDER BY ';
    if (preg_match('#^(asc|desc)$#i', $params['order'][0]['dir'])) {
        $sOrder .= ''.$aColumns[filter_var($params['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '
        .filter_var($params['order'][0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS).', ';
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
*/
$sWhere = '';
if (isset($params['letter']) && $params['letter'] !== '' && $params['letter'] !== 'None') {
    $sWhere = ' AND ';
    $sWhere .= $aColumns[1]." LIKE '".filter_var($params['letter'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
    $sWhere .= ' EXISTS ('.$aColumns[2]." LIKE '".filter_var($params['letter'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%')";
} elseif (isset($params['search']['value']) && $params['search']['value'] !== '') {
    $sWhere = ' AND ';
    $sWhere .= $aColumns[1]." LIKE '".filter_var($params['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%' OR ";
    $sWhere .= ' EXISTS ('.$aColumns[2]." LIKE '".filter_var($params['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)."%')";
}

$userId = filter_var($params['userId'], FILTER_SANITIZE_NUMBER_INT);

// Première requête pour obtenir le total
$rows = DB::query(
    'SELECT l.date as date, i.label as label, l.action as action
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE u.id = '.$userId.
    ' UNION '.
    'SELECT s.date AS date, s.label AS label, s.field_1 AS field1
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = '.$userId.
    $sWhere
);
$iTotal = DB::count();

// Deuxième requête avec ORDER et LIMIT
$rows = DB::query(
    'SELECT l.date as date, i.label as label, l.action as action, i.id as id
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE u.id = '.$userId.
    ' UNION
    SELECT s.date AS date, s.label AS label, s.field_1 AS field1, s.id as id
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = '.$userId.
    $sOrder.
    $sLimit
);
$iFilteredTotal = DB::count();
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
        || $record['action'] === $userId
    ) {
        if (strpos($record['label'], 'at_') === 0) {
            if (strpos($record['label'], '#') >= 0) {
                $col2 = preg_replace('/#[\s\S]+?#/', '', $lang->get($record['label']));
            } else {
                $col2 = str_replace('"', '\"', $lang->get($record['label']));
            }
        } else {
            $col2 = str_replace('"', '\"', $record['label']);
        }
        $col3 = '';
    } else {
        $col2 = $lang->get($record['action']).' '.$lang->get('id').' '.$record['id'];
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

echo ($sOutput).', '.
    '"sEcho": '.(int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT).', '.
    '"iTotalRecords": '.(int) $iFilteredTotal.', '.
    '"iTotalDisplayRecords": '.(int) $iTotal.'}';
