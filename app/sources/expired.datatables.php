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
 * @copyright 2009-2026 Teampass.net
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
    $checkUserAccess->userAccessPage('utilities.renewal') === false ||
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
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
//Columns name
$aColumns = ['i.label', 'expiration_date', 'n.title'];
$aSortTypes = ['ASC', 'DESC'];
//init SQL variables
$sOrder = $sLimit = '';

$draw = (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT);
$emptyOutput = [
    'draw' => $draw,
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => [],
    'sEcho' => $draw,
    'iTotalRecords' => 0,
    'iTotalDisplayRecords' => 0,
    'aaData' => [],
];

if ((int) ($SETTINGS['activate_expiration'] ?? 0) !== 1) {
    echo json_encode($emptyOutput);
    exit;
}

$visibleFolders = $session->get('user-accessible_folders');
if (is_array($visibleFolders) === false || empty($visibleFolders) === true) {
    echo json_encode($emptyOutput);
    exit;
}

$visibleFolders = array_values(
    array_unique(
        array_map(
            'intval',
            array_diff(
                $visibleFolders,
                is_array($session->get('user-forbiden_personal_folders')) === true ? $session->get('user-forbiden_personal_folders') : []
            )
        )
    )
);

if (empty($visibleFolders) === true) {
    echo json_encode($emptyOutput);
    exit;
}

// Is a date sent?
$dateCriteria = $request->query->get('dateCriteria');
if ($dateCriteria !== null && !empty($dateCriteria)) {
    $dateCriteria = (int) round((int) filter_var($dateCriteria, FILTER_SANITIZE_NUMBER_INT) / 1000, 0);
    $targetExpirationTimestamp = $dateCriteria + TP_ONE_DAY_SECONDS - 1;
} else {
    $targetExpirationTimestamp = time();
}

$lastRelevantDateSql = 'COALESCE(NULLIF(l.last_relevant_date, 0), NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0)';
$expirationDateSql = '(' . $lastRelevantDateSql . ' + (n.renewal_period * ' . TP_ONE_DAY_SECONDS . '))';
$fromWhereSql = '
    FROM ' . prefixTable('items') . ' AS i
    INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)
    LEFT JOIN (
        SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
        FROM ' . prefixTable('log_items') . '
        WHERE action = %s
        OR (action = %s AND raison LIKE %s)
        GROUP BY id_item
    ) AS l ON (l.id_item = i.id)
    WHERE i.inactif = %i
    AND i.deleted_at IS NULL
    AND i.id_tree IN %ls
    AND n.renewal_period > %i
    AND ' . $lastRelevantDateSql . ' > %i
    AND ' . $expirationDateSql . ' <= %i';
$queryParams = [
    'at_creation',
    'at_modification',
    'at_pw%',
    0,
    $visibleFolders,
    0,
    0,
    (int) $targetExpirationTimestamp,
];
$baseFromWhereSql = $fromWhereSql;
$baseQueryParams = $queryParams;

// Filtering
$search = $request->query->all('search');
$searchValue = isset($search['value']) === true ? trim((string) $search['value']) : '';
if ($searchValue !== '') {
    $fromWhereSql .= ' AND (i.label LIKE %ss OR n.title LIKE %ss)';
    $queryParams[] = $searchValue;
    $queryParams[] = $searchValue;
}

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
    $order = $request->query->all('order');

    // Vérifiez si la direction 'dir' est définie et est valide
    if (isset($order[0]['dir']) && in_array(strtoupper((string) $order[0]['dir']), $aSortTypes, true)) {
        $columnIndex = filter_var($order[0]['column'], FILTER_SANITIZE_NUMBER_INT);

        if (array_key_exists($columnIndex, $aColumns)) {
            $sOrder = ' ORDER BY ' . $aColumns[$columnIndex] . ' ' . strtoupper((string) $order[0]['dir']);
        }
    }
}

$totalCountSql = 'SELECT COUNT(*) FROM (SELECT i.id ' . $baseFromWhereSql . ') AS renewal_items';
$filteredCountSql = 'SELECT COUNT(*) FROM (SELECT i.id ' . $fromWhereSql . ') AS renewal_items';
$iTotal = (int) DB::queryFirstField($totalCountSql, ...$baseQueryParams);
$iFilteredTotal = (int) DB::queryFirstField($filteredCountSql, ...$queryParams);
$rows = DB::query(
    'SELECT i.label, i.id_tree, ' . $expirationDateSql . ' AS expiration_date ' .
    $fromWhereSql .
    $sOrder .
    $sLimit,
    ...$queryParams
);

$data = [];
foreach ($rows as $record) {
    $path = [];
    $treeDesc = $tree->getPath($record['id_tree'], true);
    foreach ($treeDesc as $t) {
        $path[] = htmlspecialchars((string) $t->title, ENT_QUOTES, 'UTF-8');
    }

    $data[] = [
        htmlspecialchars((string) $record['label'], ENT_QUOTES, 'UTF-8'),
        date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['expiration_date']),
        implode('<i class="fas fa-angle-right ml-1 mr-1"></i>', $path),
    ];
}

// finalize output
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $iTotal,
    'recordsFiltered' => $iFilteredTotal,
    'data' => $data,
    'sEcho' => $draw,
    'iTotalRecords' => $iTotal,
    'iTotalDisplayRecords' => $iFilteredTotal,
    'aaData' => $data,
]);
