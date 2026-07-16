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
    // This datatable feeds the Users administration page, not the items page: gate it on the
    // page that actually offers it. 'items' is held by every authenticated user and was far
    // too wide for an audit trail (GHSA-qhff-v9qj-75wc).
    $checkUserAccess->userAccessPage('users') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
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
    'userId' => $request->query->filter('userId', '', FILTER_SANITIZE_NUMBER_INT),
    'draw' => $request->query->filter('draw', '', FILTER_SANITIZE_NUMBER_INT),
];

$filters = [
    'start' => 'cast:integer',
    'length' => 'cast:integer',
    'letter' => 'trim|escape',
    'search' => 'cast:array',
    'order' => 'cast:array',
    'userId' => 'cast:integer',
    'draw' => 'cast:integer',
];

$inputData = dataSanitizer(
    $data,
    $filters
);

// Holding the Users page is not enough: 'userId' is client-supplied and is the only predicate
// scoping the log queries below. Without this check a manager - or, before the page gate above,
// any authenticated user - could read the audit trail of any account, administrators included,
// simply by iterating the id (GHSA-qhff-v9qj-75wc).
// Reading one's own logs is always permitted; any other target must be within the caller's
// administration scope, using the same rule as the user-management actions.
$targetUserId = (int) $inputData['userId'];
if ($targetUserId <= 0
    || ($targetUserId !== (int) $session->get('user-id')
        && callerMayManageUser($targetUserId) === false)
) {
    // Answer with a well-formed but empty datatable: it keeps the client working and tells an
    // out-of-scope caller nothing about whether the target exists.
    echo json_encode([
        'sEcho' => (int) $inputData['draw'],
        'iTotalRecords' => 0,
        'iTotalDisplayRecords' => 0,
        'aaData' => [],
    ]);
    exit;
}

/* BUILD QUERY */
// Paging
$sLimit = '';
if (isset($inputData['length']) && (int) $inputData['length'] !== -1) {
    $start = $inputData['start'];
    $length = $inputData['length'];
    $sLimit = " LIMIT $start, $length";
}

// Ordering
// Build the ORDER BY clause only from the fixed server-side column map and a
// direction that is strictly validated against the allow-list. No request
// value is ever concatenated verbatim, which prevents SQL injection.
$sOrder = '';
$order = $inputData['order'][0] ?? null;
if ($order !== null && isset($order['column'], $order['dir'])) {
    $columnIndex = (int) $order['column'];
    if (
        isset($aColumns[$columnIndex])
        && in_array(strtolower((string) $order['dir']), $aSortTypes, true)
    ) {
        $dir = strtoupper((string) $order['dir']);
        $sOrder = ' ORDER BY ' . $aColumns[$columnIndex] . ' ' . $dir;
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
$searchValue = $inputData['search']['value'] ?? '';
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
    WHERE u.id = %i'.
    (string) $sWhere.
    ' UNION '.
    'SELECT s.date AS date, s.label AS label, s.field_1 AS field1
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = %i',
    $targetUserId,
    $targetUserId
);
$iTotal = DB::count();
$rows = DB::query(
    'SELECT l.date as date, i.label as label, l.action as action, i.id as id
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE u.id = %i'.
    (string) $sWhere.
    ' UNION
    SELECT s.date AS date, s.label AS label, s.field_1 AS field1, s.id as id
    FROM '.prefixTable('log_system').' AS s
    WHERE s.qui = %i'.
    (string) $sOrder.
    (string) $sLimit,
    $targetUserId,
    $targetUserId
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
            if (strpos($record['label'], '#') !== false) {
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