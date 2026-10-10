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

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
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
header('Content-type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
//Columns name
$aColumns = ['i.label', 'expiration_date', 'n.title'];
$aSortTypes = ['ASC', 'DESC'];
//init SQL variables
$sOrder = ' ORDER BY expiration_date ASC, i.id ASC';
$sortColumn = 1;
$sortDirection = 'ASC';
$sLimit = '';

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

// Read current grants and item restrictions from the database, not a stale session scope.
$userId = (int) $session->get('user-id');
$visibleFolders = securityPostureAuthorizedFolderIds($userId);
if (empty($visibleFolders) === true) {
    echo json_encode($emptyOutput);
    exit;
}
$accessScopeSql = securityPostureItemAccessSql($userId);

// No cutoff means all known deadlines, including overdue and future items.
$targetExpirationTimestamp = null;
$dateCriteria = $request->query->get('dateCriteria');
if ($dateCriteria !== null && !empty($dateCriteria)) {
    $dateCriteria = (int) round((int) filter_var($dateCriteria, FILTER_SANITIZE_NUMBER_INT) / 1000, 0);
    $targetExpirationTimestamp = $dateCriteria + TP_ONE_DAY_SECONDS - 1;
}

$lastRelevantDateSql = renewalBaseDateSql();
$effectivePeriodSql = renewalApplicablePeriodSql($SETTINGS);
$expirationDateSql = '(' . $lastRelevantDateSql . ' + (' . $effectivePeriodSql . ' * ' . TP_ONE_DAY_SECONDS . '))';
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
    AND ' . $accessScopeSql . '
    AND ' . $effectivePeriodSql . ' > %i
    AND ' . $lastRelevantDateSql . ' > %i';
$queryParams = [
    'at_creation',
    'at_modification',
    'at_pw%',
    0,
    0,
    0,
];
if ($targetExpirationTimestamp !== null) {
    $fromWhereSql .= ' AND ' . $expirationDateSql . ' <= %i';
    $queryParams[] = $targetExpirationTimestamp;
}
$baseFromWhereSql = $fromWhereSql;
$baseQueryParams = $queryParams;

// Filtering
$search = $request->query->all('search');
$searchValue = isset($search['value']) === true ? trim((string) $search['value']) : '';

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
            $sortColumn = (int) $columnIndex;
            $sortDirection = strtoupper((string) $order[0]['dir']);
            $sOrder = ' ORDER BY ' . $aColumns[$sortColumn] . ' ' . $sortDirection . ', i.id ASC';
        }
    }
}

$totalCountSql = 'SELECT COUNT(*) FROM (SELECT i.id ' . $baseFromWhereSql . ') AS renewal_items';
$iTotal = (int) DB::queryFirstField($totalCountSql, ...$baseQueryParams);
// MySQL cannot decode all named/numeric HTML entities. Only text operations
// need all authorized rows; the default chronological view keeps SQL pagination.
$processDisplayText = $searchValue !== '' || $sortColumn !== 1;
$iFilteredTotal = $iTotal;
$rows = DB::query(
    'SELECT i.id, i.label, i.id_tree, n.title AS folder_title, ' . $expirationDateSql . ' AS expiration_date ' .
    $fromWhereSql .
    $sOrder .
    ($processDisplayText ? '' : $sLimit),
    ...$queryParams
);

if ($processDisplayText) {
    foreach ($rows as &$row) {
        $row['label_display'] = html_entity_decode((string) $row['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $row['folder_display'] = html_entity_decode((string) $row['folder_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    unset($row);
    if ($searchValue !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($searchValue): bool {
            return mb_stripos($row['label_display'], $searchValue, 0, 'UTF-8') !== false
                || mb_stripos($row['folder_display'], $searchValue, 0, 'UTF-8') !== false;
        }));
    }
    $iFilteredTotal = count($rows);
    if ($sortColumn !== 1) {
        $sortField = $sortColumn === 0 ? 'label_display' : 'folder_display';
        usort($rows, static function (array $left, array $right) use ($sortField, $sortDirection): int {
            $comparison = strcmp(mb_strtolower($left[$sortField], 'UTF-8'), mb_strtolower($right[$sortField], 'UTF-8'));
            return ($sortDirection === 'DESC' ? -$comparison : $comparison)
                ?: ((int) $left['id'] <=> (int) $right['id']);
        });
    }
    if ($length !== -1) {
        $rows = array_slice($rows, $start, $length);
    }
}

$data = [];
foreach ($rows as $record) {
    $path = [];
    $treeDesc = $tree->getPath($record['id_tree'], true);
    foreach ($treeDesc as $t) {
        // A visible child does not grant access to its ancestors' names.
        if (in_array((int) $t->id, $visibleFolders, true)) {
            $path[] = htmlspecialchars(html_entity_decode((string) $t->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        }
    }

    $itemUrl = 'index.php?page=items&group=' . (int) $record['id_tree'] . '&id=' . (int) $record['id'];
    $data[] = [
        '<a href="' . htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(html_entity_decode((string) $record['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</a>',
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
