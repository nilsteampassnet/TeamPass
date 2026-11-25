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

// Load functions
require_once __DIR__ . '/main.functions.php';
require_once __DIR__ . '/user_logs_logic.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
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
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$query = $request->query->all();
$draw = max(0, (int) filter_var($query['draw'] ?? null, FILTER_VALIDATE_INT));
$targetUserId = (int) filter_var($query['userId'] ?? null, FILTER_VALIDATE_INT);

// Users page access is required even for one's own logs; other accounts must also be in scope.
if ($targetUserId <= 0
    || ($targetUserId !== (int) $session->get('user-id')
        && callerMayManageUser($targetUserId) === false)
) {
    echo json_encode(['sEcho' => $draw, 'iTotalRecords' => 0, 'iTotalDisplayRecords' => 0, 'aaData' => []]);
    exit;
}

$dateRange = userLogsDateRange($query, new DateTimeImmutable('today'));
if ($dateRange === null) {
    echo json_encode(['sEcho' => $draw, 'iTotalRecords' => 0, 'iTotalDisplayRecords' => 0, 'aaData' => []]);
    exit;
}

$activity = is_string($query['activity'] ?? null) ? $query['activity'] : '';
$channel = is_string($query['channel'] ?? null) ? $query['channel'] : '';
$search = $query['search']['value'] ?? '';
$term = is_string($search) ? mb_substr(trim($search), 0, 100, 'UTF-8') : '';
$start = max(0, (int) filter_var($query['start'] ?? null, FILTER_VALIDATE_INT));
$length = (int) filter_var($query['length'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$length = $length > 0 ? min($length, 100) : 25;
$direction = ($query['order'][0]['dir'] ?? null) === 'asc' ? 'ASC' : 'DESC';

$itemWhere = new WhereClause('AND');
$itemWhere->add('l.id_user = %i', $targetUserId);
$systemWhere = new WhereClause('AND');
// qui stores different kinds of identifiers; compare account IDs as text.
$systemWhere->add('l.qui = %s', (string) $targetUserId);
$systemWhere->add('l.type <> %s', 'failed_auth');

foreach ([$itemWhere, $systemWhere] as $where) {
    foreach (['>=' => $dateRange[0], '<' => $dateRange[1]] as $operator => $boundary) {
        if ($boundary === null) {
            continue;
        }
        // Unix seconds in the log tables are VARCHAR. Preserve indexable comparisons for
        // ten-digit boundaries, with a numeric comparison for outlying custom dates.
        if ($boundary >= 1000000000 && $boundary <= 9999999999) {
            $where->add('l.date ' . $operator . ' %s', (string) $boundary);
        } else {
            $where->add('CAST(l.date AS SIGNED) ' . $operator . ' %i', $boundary);
        }
    }
}

$itemsFrom = ' FROM ' . prefixTable('log_items') . ' AS l
    INNER JOIN ' . prefixTable('items') . ' AS i ON l.id_item = i.id
    INNER JOIN ' . prefixTable('users') . ' AS u ON l.id_user = u.id WHERE %l';
$systemFrom = ' FROM ' . prefixTable('log_system') . ' AS l WHERE %l';
$countSql = 'SELECT (SELECT COUNT(*)' . $itemsFrom . ') + (SELECT COUNT(*)' . $systemFrom . ')';
$total = (int) DB::queryFirstField($countSql, $itemWhere, $systemWhere);
$filteredTotal = $total;

userLogsApplyFilters($itemWhere, $systemWhere, $activity, $channel);
$pageSql = 'SELECT l.date AS date, 1 AS is_item, l.increment_id AS event_id,
        l.id_item AS item_id, i.label AS label, l.action AS action, NULL AS type, l.raison AS log_detail'
    . $itemsFrom . ' UNION ALL '
    . 'SELECT l.date, 0, l.id, NULL, l.label, NULL, l.type, l.field_1' . $systemFrom
    . ' ORDER BY date ' . $direction . ', is_item ' . $direction . ', event_id ' . $direction;

$rows = [];
if ($total > 0) {
    if ($term !== '') {
        // Search activity text and item titles in one bounded-memory pass. Account logins
        // are resolved only after pagination, so target visibility cannot affect the count.
        $page = storedTextSearchPage(
            DB::queryWalk($pageSql, $itemWhere, $systemWhere),
            [$term],
            ['activity', 'target'],
            $start,
            $length,
            null,
            [
                'activity' => static fn (string $value, array $row): string => userLogsActivityText($row, $lang),
                'target' => static fn (string $value, array $row): string => userLogsTargetText($row),
            ]
        );
        $rows = $page['rows'];
        $filteredTotal = $page['total'];
    } else {
        if ($activity !== '' || $channel !== '') {
            $filteredTotal = (int) DB::queryFirstField($countSql, $itemWhere, $systemWhere);
        }
        if ($start < $filteredTotal) {
            $rows = DB::query($pageSql . ' LIMIT %i, %i', $itemWhere, $systemWhere, $start, $length);
        }
    }
}

// Resolve only the authorised account targets on this page, after the search cursor is closed.
$adminTargetIds = userLogsManageableAdminTargetIds($rows, 'callerMayManageUser');
$adminLogins = $adminTargetIds === [] ? [] : array_column(DB::query(
    'SELECT id, login FROM ' . prefixTable('users') . ' WHERE id IN %li AND deleted_at IS NULL',
    $adminTargetIds
), 'login', 'id');

$aaData = [];
foreach ($rows as $row) {
    $isItem = (int) $row['is_item'] === 1;
    $hint = (string) ($row['log_detail'] ?? '');
    $isApi = $isItem
        ? str_contains($hint, 'tp_src=api')
        : logsSystemRowIsApi((string) $row['type'], (string) $row['label'], $hint);
    $aaData[] = [
        htmlspecialchars(date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $row['date']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars(userLogsActivityText($row, $lang), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars(userLogsTargetText($row, $adminLogins), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $isApi ? 'api' : 'web',
        $isItem ? (int) $row['item_id'] : null,
    ];
}

echo json_encode([
    'sEcho' => $draw,
    'iTotalRecords' => $total,
    'iTotalDisplayRecords' => $filteredTotal,
    'aaData' => $aaData,
], JSON_INVALID_UTF8_SUBSTITUTE);
