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
 * @file      users.logs.datatable.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use voku\helper\AntiXSS;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$antiXss = new AntiXSS();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
    $checkUserAccess->userAccessPage('items') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Configure AntiXSS to keep double-quotes
$antiXss->removeEvilAttributes(['style', 'onclick', 'onmouseover', 'onmouseout', 'onmousedown', 'onmouseup', 'onmousemove', 'onkeydown', 'onkeyup', 'onkeypress', 'onchange', 'onblur', 'onfocus', 'onabort', 'onerror', 'onscroll']);
$antiXss->removeEvilHtmlTags(['script', 'iframe', 'embed', 'object', 'applet', 'link', 'style']);

//Columns name
$aColumns = ['date', 'label', 'action'];
$aSortTypes = ['asc', 'desc'];
//init SQL variables
$sWhere = $sOrder = $sLimit = '';

// Prepare POST variables
$data = [
    'start' => $request->query->filter('start', '', FILTER_SANITIZE_NUMBER_INT),
    'length' => $request->query->filter('length', '', FILTER_SANITIZE_NUMBER_INT),
    'letter' => $request->query->filter('letter', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'search' => json_encode($request->query->filter('search', '', FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY)),
    'order' => json_encode($request->query->filter('order', '', FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY)),
    'column' => $request->query->filter('column', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'dir' => $request->query->filter('dir', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'userId' => $request->query->filter('userId', '', FILTER_SANITIZE_NUMBER_INT),
    'draw' => $request->query->filter('draw', '', FILTER_SANITIZE_NUMBER_INT),
];

$filters = [
    'start' => 'cast:integer',
    'length' => 'cast:integer',
    'letter' => 'trim|escape',
    'search' => 'cast:array',
    'order' => 'cast:array',
    'column' => 'trim|escape',
    'dir' => 'trim|escape',
    'userId' => 'cast:integer',
    'draw' => 'cast:integer',
];

$inputData = dataSanitizer(
    $data,
    $filters
);

/* BUILD QUERY */
// Paging
$sLimit = '';
if (isset($inputData['length']) && (int) $inputData['length'] !== -1) {
    $start = $inputData['start'];
    $length = $inputData['length'];
    $sLimit = " LIMIT $start, $length";
}

// Ordering
$sOrder = '';
$order = $inputData['order'][0] ?? null;
if ($order && in_array($order['dir'], $aSortTypes)) {
    $sOrder = ' ORDER BY  ';
    if (isset($order['column']) && preg_match('#^(asc|desc)$#i', $order['dir'])) {
        $columnIndex = $order['column'];
        $dir = $inputData['dir'];
        $sOrder .= $aColumns[$columnIndex] . ' ' . $dir . ', ';
    }

    $sOrder = substr_replace($sOrder, '', -2);
    if ($sOrder === ' ORDER BY') {
        $sOrder = '';
    }
}

/*
   * Filtering
   * NOTE this does not match the built-in DataTables filtering which does it
   * word by word on any field. It's possible to do here, but concerned about efficiency
   * on very large tables, and MySQL's regex functionality is very limited
*/
$sWhere = '';
$letter = $inputData['letter'];
$searchValue = isset($inputData['search']) && isset($inputData['search']['value']) ?? '';
if ($letter !== '' && $letter !== 'None') {
    $sWhere = ' AND (';
    $sWhere .= $aColumns[1]." LIKE '".$letter."%'";
    $sWhere .= ')';
} elseif ($searchValue !== '') {
    $sWhere = ' AND (';
    $sWhere .= $aColumns[1]." LIKE '%".$searchValue."%'";
    $sWhere .= ')';
}

$rows = DB::query(
    'SELECT l.date as date, i.label as label, l.action as action
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE u.id = '.$inputData['userId'].
    (string) $sWhere.
    ' UNION '.
    'SELECT s.date AS date, s.label AS label, s.field_1 AS field1
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = '.$inputData['userId']
);
$iTotal = DB::count();
$rows = DB::query(
    'SELECT l.date as date, i.label as label, l.action as action, i.id as id
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE u.id = '.$inputData['userId'].
    (string) $sWhere.
    ' UNION
    SELECT s.date AS date, s.label AS label, s.field_1 AS field1, s.id as id
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = '.$inputData['userId'].
    (string) $sOrder.
    (string) $sLimit
);
$sOutput = '{';
$sOutput .= '"sEcho": '.$inputData['draw'].', ';
$sOutput .= '"iTotalRecords": '.$iTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
$sOutput .= '"aaData": ';
if (DB::count() > 0) {
    $sOutput .= '[';
} else {
    $sOutput .= '';
}

foreach ($rows as $record) {
    if (empty($record['action']) === true
        || $record['action'] === $inputData['userId']
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


if (count($rows) > 0) {
    if (strrchr($sOutput, '[') !== '[') {
        $sOutput = substr_replace($sOutput, '', -1);
    }
    $sOutput .= ']';
} else {
    $sOutput .= '[]';
}

echo ($sOutput).'}';