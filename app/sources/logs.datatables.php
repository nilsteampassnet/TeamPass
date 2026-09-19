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
 * @file      logs.datatables.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;
use voku\helper\AntiXSS;

// Load functions
require_once 'main.functions.php';
require_once __DIR__ . '/logs_filter_logic.php';

/**
 * Encode a single value as a safe JSON string literal for the manually built DataTables output.
 * Escapes backslashes and HTML-significant characters so a stored JSON unicode escape
 * sequence (ex: <) cannot be re-decoded into HTML by the browser JSON parser.
 *
 * @param mixed $value
 */
function tpDatatableJsonCell($value): string
{
    return (string) json_encode(
        (string) $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

/**
 * Return the display name for a user ID, or a user-slash icon if not found.
 */
function getBackgroundTaskUserDisplayFromUserId($userId): string
{
    if (empty($userId) === true) {
        return "<i class='fa-solid fa-user-slash'></i>";
    }

    $dataUser = DB::queryFirstRow(
        'SELECT name, lastname, login FROM ' . prefixTable('users') . '
        WHERE id = %i
        LIMIT 1',
        (int) $userId
    );

    if (DB::count() === 0 || $dataUser === null) {
        return "<i class='fa-solid fa-user-slash'></i>";
    }

    $displayName = trim(
        normalizeLogDisplayValue($dataUser['name'] ?? '')
        . ' ' .
        normalizeLogDisplayValue($dataUser['lastname'] ?? '')
    );

    if ($displayName === '') {
        $displayName = trim(normalizeLogDisplayValue($dataUser['login'] ?? ''));
    }

    return $displayName === '' ? "<i class='fa-solid fa-user-slash'></i>" : $displayName;
}

/**
 * Resolve the user display string for a background task row based on its process type and arguments.
 */
function resolveBackgroundTaskUserDisplay(array $arguments, string $processType): string
{
    if (in_array($processType, ['create_user_keys', 'user_build_cache_tree', 'migrate_user_personal_items'], true) === true) {
        $userId = $arguments['new_user_id'] ?? ($arguments['user_id'] ?? null);
        return getBackgroundTaskUserDisplayFromUserId($userId);
    }

    if (in_array($processType, ['item_copy', 'new_item', 'item_update_create_keys', 'update_item'], true) === true) {
        $userId = $arguments['author'] ?? ($arguments['user_id'] ?? ($arguments['owner_id'] ?? null));
        return getBackgroundTaskUserDisplayFromUserId($userId);
    }

    if ($processType === 'send_email') {
        $receiverName = trim((string) ($arguments['receiver_name'] ?? ($arguments['login'] ?? '')));
        if ($receiverName !== '') {
            return normalizeLogDisplayValue($receiverName);
        }

        $email = trim((string) ($arguments['receivers'] ?? ($arguments['email'] ?? '')));
        if ($email !== '') {
            $dataUser = DB::queryFirstRow(
                'SELECT name, lastname, login FROM ' . prefixTable('users') . '
                WHERE email = %s
                ORDER BY id DESC
                LIMIT 1',
                $email
            );

            if (DB::count() > 0 && $dataUser !== null) {
                $displayName = trim(
                    normalizeLogDisplayValue($dataUser['name'] ?? '')
                    . ' ' .
                    normalizeLogDisplayValue($dataUser['lastname'] ?? '')
                );

                if ($displayName === '') {
                    $displayName = trim(normalizeLogDisplayValue($dataUser['login'] ?? ''));
                }

                if ($displayName !== '') {
                    return $displayName;
                }
            }

            return normalizeLogDisplayValue($email);
        }

        return "<i class='fa-solid fa-user-slash'></i>";
    }

    return "<i class='fa-solid fa-user-slash'></i>";
}

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
    $checkUserAccess->userAccessPage('utilities.logs') === false ||
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

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Get the data
$params = $request->query->all();

// Init
$searchValue = $sWhere = $sOrder = $sOutput = '';
$aSortTypes = ['ASC', 'DESC'];
$sLimitStart = $request->query->has('start') 
    ? $request->query->filter('start', 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]) 
    : 0;
$sLimitLength = $request->query->has('length') 
    ? $request->query->filter('length', 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]) 
    : 10;

// Check search parameters
if (isset($params['search']['value'])) {
    // Case 1: search[value]
    $searchValue = (string) $params['search']['value'];
} elseif (isset($params['sSearch'])) {
    // Case 2: sSearch
    $searchValue = (string) $params['sSearch'];
}

// Ordering
// Default to a string, never null: the facet option lists below are not DataTables sources and
// send no order parameter, and strtoupper(null) emits a deprecation that lands in front of the
// JSON body — which is why the pickers reported "the results could not be loaded".
$order = strtoupper((string) ($params['order'][0]['dir'] ?? ''));
$orderDirection = in_array($order, $aSortTypes, true) ? $order : 'DESC';
    
// Start building the query and output depending on the action
if (isset($params['action']) && $params['action'] === 'logs') {
    // One canonical payload drives the read query here and the purge in utilities.queries.php.
    // That is what guarantees a purge deletes exactly the rows the table displayed.
    $rawFilters = json_decode((string) $request->query->get('filters', ''), true);
    $filters = logsNormalizeFilters(is_array($rawFilters) === true ? $rawFilters : []);
    $filters['term'] = $searchValue !== '' ? mb_substr(trim($searchValue), 0, 100) : $filters['term'];

    // Knowledge base logs are misc rows served by kb.queries.php, which owns their admin gate.
    // Falling through to the system branch here would answer system rows under the knowledge base
    // column set, which is exactly what DataTables reports as an unknown parameter.
    if ($filters['source'] === 'kb') {
        http_response_code(400);
        echo (string) json_encode(
            [
                'draw' => (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $lang->get('error_not_allowed_to'),
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        exit;
    }

    $visibleColumns = logsVisibleColumns($filters['source'], $filters['types']);
    $sortableColumns = $filters['source'] === 'items'
        ? [
            'date' => 'l.date', 'id' => 'i.id', 'label' => 'i.label', 'folder' => 't.title',
            'user' => 'u.login', 'action' => 'l.action', 'api' => 'l.raison', 'personal' => 't.personal_folder',
        ]
        : [
            'date' => 'l.date', 'type' => 'l.type', 'label' => 'l.label', 'user' => 'u.login',
            'source' => 'l.field_1', 'ip' => 'l.qui', 'channel' => 'l.label',
            'target' => 'l.field_1', 'actions' => 'l.date',
        ];

    // The ordering index addresses the columns the client was told to display, so it is resolved
    // against that same list rather than against a second one that could drift from it.
    $orderedKey = $visibleColumns[(int) ($params['order'][0]['column'] ?? 0)] ?? 'date';
    $orderColumn = $sortableColumns[$orderedKey] ?? 'l.date';

    $data = [];

    if ($filters['source'] === 'items') {
        $sWhere = buildItemLogFilter($filters, $lang);

        // The joins are mandatory, not decorative: the predicate reaches i.id_tree and
        // t.personal_folder, and an item hard-deleted since drops out of the view.
        $from = prefixTable('log_items') . ' AS l
            INNER JOIN ' . prefixTable('items') . ' AS i ON (l.id_item = i.id)
            INNER JOIN ' . prefixTable('users') . ' AS u ON (l.id_user = u.id)
            INNER JOIN ' . prefixTable('nested_tree') . ' AS t ON (i.id_tree = t.id)';

        $iTotal = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . $from . ' WHERE %l',
            $sWhere
        );

        $rows = DB::query(
            'SELECT l.date AS date, l.action AS action, l.raison AS raison,
                i.id AS id, i.label AS label, t.title AS folder, t.personal_folder AS personal_folder,
                u.login AS login, u.name AS name, u.lastname AS lastname
            FROM ' . $from . ' WHERE %l ORDER BY %l %l LIMIT %i, %i',
            $sWhere,
            $orderColumn,
            $orderDirection,
            $sLimitStart,
            $sLimitLength
        );

        foreach ($rows as $record) {
            $fullname = trim(
                normalizeLogDisplayValue($record['name'] ?? '') . ' ' .
                normalizeLogDisplayValue($record['lastname'] ?? '')
            );
            $data[] = [
                'date' => date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']),
                'id' => (int) $record['id'],
                'label' => normalizeLogDisplayValue(trim((string) $record['label'])),
                'folder' => normalizeLogDisplayValue(trim((string) $record['folder'])),
                'user' => ($fullname !== '' ? $fullname . ' ' : '')
                    . '[' . normalizeLogDisplayValue(trim((string) $record['login'])) . ']',
                // The action code travels as-is: the client owns its translation, so no markup
                // and no already-escaped sentence transits through the JSON payload.
                'action' => (string) $record['action'],
                'api' => strpos((string) ($record['raison'] ?? ''), 'tp_src=api') !== false,
                'personal' => (int) ($record['personal_folder'] ?? 0) === 1,
            ];
        }
    } else {
        $sWhere = buildSystemLogFilter($filters);

        // LEFT JOIN, never INNER: on a failed authentication qui holds an IP address, and any row
        // whose author has since been deleted must still be readable.
        $from = prefixTable('log_system') . ' AS l
            LEFT JOIN ' . prefixTable('users') . ' AS u ON (l.qui = u.id)';

        $iTotal = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . $from . ' WHERE %l',
            $sWhere
        );

        $rows = DB::query(
            'SELECT l.date AS date, l.type AS type, l.label AS label, l.field_1 AS field_1,
                l.qui AS who, u.login AS login, u.name AS name, u.lastname AS lastname
            FROM ' . $from . ' WHERE %l ORDER BY %l %l LIMIT %i, %i',
            $sWhere,
            $orderColumn,
            $orderDirection,
            $sLimitStart,
            $sLimitLength
        );
        $rows = is_array($rows) === true ? $rows : [];

        // Administration rows name their target by user id in field_1. Resolving it row by row
        // was one query per line; the whole page is resolved in a single one instead.
        $targetIds = [];
        foreach ($rows as $record) {
            if (logsSystemTypeKey((string) $record['type']) !== 'admin'
                || (string) $record['label'] === 'authentication_lockout_removed'
            ) {
                continue;
            }
            // filter_var, not a cast: '1758988164-D5Hc....sql' is a backup file name, not user
            // 1758988164. The display path below applies the same rule.
            $targetId = filter_var($record['field_1'] ?? null, FILTER_VALIDATE_INT);
            if ($targetId !== false && $targetId > 0 && in_array($targetId, $targetIds, true) === false) {
                $targetIds[] = $targetId;
            }
        }
        $targets = [];
        if ($targetIds !== []) {
            foreach (DB::query(
                'SELECT id, login, name, lastname FROM ' . prefixTable('users') . ' WHERE id IN %li',
                $targetIds
            ) as $target) {
                $targets[(int) $target['id']] = $target;
            }
        }

        $isAdminSession = (int) ($session->get('user-admin') ?? 0) === 1;

        foreach ($rows as $record) {
            $type = (string) $record['type'];
            $typeKey = logsSystemTypeKey($type);
            $label = (string) ($record['label'] ?? '');
            $field1 = stripslashes((string) ($record['field_1'] ?? ''));
            $isApi = logsSystemRowIsApi($type, $label, $field1);

            $fullname = trim(
                normalizeLogDisplayValue($record['name'] ?? '') . ' ' .
                normalizeLogDisplayValue($record['lastname'] ?? '')
            );
            $user = empty($record['login']) === false
                ? ($fullname !== '' ? $fullname . ' ' : '') . '[' . normalizeLogDisplayValue((string) $record['login']) . ']'
                : '';

            if ($typeKey === 'failed') {
                // Failed authentications carry the submitted login, not a user id: there is no
                // account to join, and the value must be shown exactly as it was submitted.
                $user = normalizeLogDisplayValue(logsStripApiMarker($field1, $isApi));
                $display = normalizeLogDisplayValue((string) $lang->get($label));
            } elseif ($typeKey === 'admin') {
                $display = normalizeLogDisplayValue(formatAdminLogLabel($label, $lang));
            } else {
                $display = normalizeLogDisplayValue(str_replace([chr(10), chr(13)], [' ', ' '], stripslashes($label)));
                if ($user === '') {
                    $user = 'IP: ' . normalizeLogDisplayValue((string) ($record['who'] ?? ''));
                }
            }

            $target = '';
            if ($typeKey === 'admin' && $field1 !== '') {
                // Only user management names its target by id. Administration rows carry free
                // text there (a backup file name, a recipient address), and resolving those as an
                // id labelled every one of them "Removed user (...)".
                $targetId = filter_var($field1, FILTER_VALIDATE_INT);
                if ($label === 'authentication_lockout_removed' || $targetId === false || $targetId <= 0) {
                    $target = normalizeLogDisplayValue($field1);
                } elseif (isset($targets[$targetId]) === true) {
                    $target = trim(
                        normalizeLogDisplayValue($targets[$targetId]['name'] ?? '') . ' ' .
                        normalizeLogDisplayValue($targets[$targetId]['lastname'] ?? '')
                    );
                } else {
                    $target = normalizeLogDisplayValue('Removed user (' . $field1 . ')');
                }
            }

            $ip = $typeKey === 'failed' ? normalizeLogDisplayValue(stripslashes((string) ($record['who'] ?? ''))) : '';

            $data[] = [
                'date' => date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']),
                // Keys, not sentences: the client owns every translation and every badge, so the
                // payload carries no markup at all.
                'type' => $typeKey,
                'label' => $display,
                'user' => $user,
                'source' => $isApi === true ? 'api' : 'web',
                'ip' => $ip,
                'channel' => $isApi === true ? 'api' : 'web',
                'target' => $target,
                // The unlock action needs a routable IPv4 rule and an administrator session.
                'can_blacklist' => $typeKey === 'failed' && $isAdminSession === true
                    && teampassNormalizeIpv4Rule((string) ($record['who'] ?? '')) !== null,
            ];
        }
    }

    // json_encode once, on a structure, instead of the hand-built string the other branches use:
    // that concatenation is what let a stored value escape its cell in the past.
    echo (string) json_encode(
        [
            'draw' => (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT),
            'recordsTotal' => $iTotal,
            'recordsFiltered' => $iTotal,
            'columns' => $visibleColumns,
            'data' => $data,
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    exit;
/* FACET OPTION LISTS
   Served here rather than by utilities.queries.php: this file is the one gated on the
   'utilities.logs' page right, which managers hold and 'utilities' does not imply. */
} elseif (isset($params['action']) && $params['action'] === 'user_options') {
    $term = mb_substr(trim((string) $request->query->get('term', '')), 0, 100);
    $page = max(1, (int) $request->query->get('page', 1));
    $perPage = 30;

    $where = new WhereClause('AND');
    $where->add('id > 0');
    $where->add(
        'id NOT IN %li',
        [(int) OTV_USER_ID, (int) TP_USER_ID, (int) SSH_USER_ID, (int) API_USER_ID]
    );
    if ($term !== '') {
        $search = $where->addClause('OR');
        foreach (['login', 'name', 'lastname'] as $column) {
            $search->add($column . ' LIKE %ss', $term);
        }
    }

    // One row beyond the page is fetched to answer "is there more" without a second COUNT.
    $rows = DB::query(
        'SELECT id, login, name, lastname FROM ' . prefixTable('users') . '
        WHERE %l ORDER BY login ASC LIMIT %i, %i',
        $where,
        ($page - 1) * $perPage,
        $perPage + 1
    );
    $rows = is_array($rows) === true ? $rows : [];
    $hasMore = count($rows) > $perPage;

    $results = [];
    foreach (array_slice($rows, 0, $perPage) as $record) {
        $displayName = trim(
            normalizeLogDisplayValue($record['name'] ?? '') . ' ' .
            normalizeLogDisplayValue($record['lastname'] ?? '')
        );
        $results[] = [
            'id' => (int) $record['id'],
            'text' => ($displayName === '' ? '' : $displayName . ' ')
                . '[' . normalizeLogDisplayValue((string) $record['login']) . ']',
        ];
    }

    echo (string) json_encode(
        ['results' => $results, 'pagination' => ['more' => $hasMore]],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    exit;
} elseif (isset($params['action']) && $params['action'] === 'folder_options') {
    $term = mb_substr(trim((string) $request->query->get('term', '')), 0, 100);
    $page = max(1, (int) $request->query->get('page', 1));
    $perPage = 30;

    $where = new WhereClause('AND');
    if ($term !== '') {
        $where->add('title LIKE %ss', $term);
    }

    $rows = DB::query(
        'SELECT id, title, nlevel FROM ' . prefixTable('nested_tree') . '
        WHERE %l ORDER BY nleft ASC LIMIT %i, %i',
        $where,
        ($page - 1) * $perPage,
        $perPage + 1
    );
    $rows = is_array($rows) === true ? $rows : [];
    $hasMore = count($rows) > $perPage;

    $results = [];
    foreach (array_slice($rows, 0, $perPage) as $record) {
        $results[] = [
            'id' => (int) $record['id'],
            // The indent conveys the tree depth without sending the whole hierarchy.
            'text' => str_repeat('— ', max(0, (int) $record['nlevel'] - 1))
                . normalizeLogDisplayValue((string) $record['title']),
        ];
    }

    echo (string) json_encode(
        ['results' => $results, 'pagination' => ['more' => $hasMore]],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    exit;
/* ACTIVE AUTHENTICATION LOCKOUTS */
} elseif (isset($params['action']) && $params['action'] === 'authentication_lockouts') {
    if ((int) ($session->get('user-admin') ?? 0) !== 1) {
        http_response_code(403);
        echo (string) json_encode(
            [
                'draw' => (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $lang->get('error_not_allowed_to'),
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        exit;
    }

    $now = date('Y-m-d H:i:s', time());
    $lockoutBaseSql = 'SELECT
        af.source,
        af.value,
        MAX(u.id) AS user_id,
        MAX(u.login) AS login,
        MAX(u.name) AS name,
        MAX(u.lastname) AS lastname,
        COALESCE(
            NULLIF(TRIM(CONCAT(COALESCE(MAX(u.name), \'\'), \' \', COALESCE(MAX(u.lastname), \'\'))), \'\'),
            MAX(u.login),
            \'\'
        ) AS user_display,
        COUNT(DISTINCT af.id) AS failure_count,
        MIN(af.date) AS first_failure,
        MAX(af.date) AS last_failure,
        MAX(af.unlock_at) AS unlock_at
        FROM ' . prefixTable('auth_failures') . ' AS af
        LEFT JOIN ' . prefixTable('users') . ' AS u
            ON (af.source = %s AND u.login = af.value AND u.deleted_at IS NULL)
        GROUP BY af.source, af.value
        HAVING MAX(af.unlock_at) > %s';
    $lockoutBaseParams = ['login', $now];

    $totalRecords = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM (' . $lockoutBaseSql . ') AS all_active_lockouts',
        ...$lockoutBaseParams
    );

    $lockoutFilteredSql = 'SELECT * FROM (' . $lockoutBaseSql . ') AS active_lockouts';
    $lockoutQueryParams = $lockoutBaseParams;
    if ($searchValue !== '') {
        $lockoutFilteredSql .= ' WHERE source LIKE %ss
            OR value LIKE %ss
            OR login LIKE %ss
            OR name LIKE %ss
            OR lastname LIKE %ss';
        $lockoutQueryParams = array_merge(
            $lockoutQueryParams,
            [$searchValue, $searchValue, $searchValue, $searchValue, $searchValue]
        );
    }

    $filteredRecords = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM (' . $lockoutFilteredSql . ') AS filtered_active_lockouts',
        ...$lockoutQueryParams
    );

    // Order columns match the client column indexes. Column 2 sorts on the very expression the
    // cell displays, so the visible order is never at odds with the sorted one.
    $lockoutOrderColumns = [
        'source',
        'value',
        'user_display',
        'failure_count',
        'first_failure',
        'last_failure',
        'unlock_at',
    ];
    $requestedOrderColumn = (int) ($params['order'][0]['column'] ?? 6);
    $lockoutOrderColumn = $lockoutOrderColumns[$requestedOrderColumn] ?? 'unlock_at';
    // Upper bound kept in sync with the client 'lengthMenu' so a page never returns fewer rows
    // than the paginator announces.
    $lockoutMaxPageLength = 100;
    $lockoutLimit = $sLimitLength > 0 ? min($sLimitLength, $lockoutMaxPageLength) : 10;
    $lockoutRows = DB::query(
        $lockoutFilteredSql . ' ORDER BY ' . $lockoutOrderColumn . ' ' . $orderDirection . ' LIMIT %i, %i',
        ...array_merge($lockoutQueryParams, [$sLimitStart, $lockoutLimit])
    );

    $dateTimeFormat = ($SETTINGS['date_format'] ?? 'Y-m-d') . ' ' . ($SETTINGS['time_format'] ?? 'H:i:s');
    $lockoutData = [];
    foreach ($lockoutRows as $lockoutRow) {
        $source = (string) ($lockoutRow['source'] ?? '');
        // Built by the query so the ordering applied above matches what the cell shows.
        $userDisplay = '';
        if ($source === 'login') {
            $userDisplay = trim((string) ($lockoutRow['user_display'] ?? ''));
            if ($userDisplay === '') {
                $userDisplay = $lang->get('authentication_lockout_unknown_user');
            }
            $userDisplay = normalizeLogDisplayValue($userDisplay);
        }

        $firstFailureTimestamp = strtotime((string) ($lockoutRow['first_failure'] ?? ''));
        $lastFailureTimestamp = strtotime((string) ($lockoutRow['last_failure'] ?? ''));
        $unlockTimestamp = strtotime((string) ($lockoutRow['unlock_at'] ?? ''));

        $lockoutData[] = [
            'source' => $source,
            'value' => (string) ($lockoutRow['value'] ?? ''),
            'user_display' => $userDisplay,
            'failure_count' => (int) ($lockoutRow['failure_count'] ?? 0),
            'first_failure' => $firstFailureTimestamp === false ? '' : date($dateTimeFormat, $firstFailureTimestamp),
            'last_failure' => $lastFailureTimestamp === false ? '' : date($dateTimeFormat, $lastFailureTimestamp),
            'unlock_at' => $unlockTimestamp === false ? '' : date($dateTimeFormat, $unlockTimestamp),
            'unlock_at_timestamp' => $unlockTimestamp === false ? 0 : $unlockTimestamp,
        ];
    }

    echo (string) json_encode(
        [
            'draw' => (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $lockoutData,
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    exit;
/* FAILED AUTHENTICATION */
} elseif (isset($params['action']) && $params['action'] === 'items_in_edition') {
    //Columns name
    $aColumns = ['e.timestamp', 'u.login', 'i.label', 'u.name', 'u.lastname'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('OR');
    if ($searchValue !== '') {        
        foreach ($aColumns as $column) {
            $sWhere->add($column.' LIKE %ss', $searchValue);
        }
    }

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('items_edition').' AS e
        INNER JOIN '.prefixTable('items').' as i ON (e.item_id=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (e.user_id=u.id)
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT e.timestamp, e.item_id, e.user_id, u.login, u.name, u.lastname, i.label
    FROM '.prefixTable('items_edition').' AS e
    INNER JOIN '.prefixTable('items').' as i ON (e.item_id=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (e.user_id=u.id)
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $record = secureOutput($record, ['name', 'lastname', 'login', 'label']);
        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-id=\"'.$record['item_id'].'\">", ';
        //col2
        $time_diff = intval(time() - $record['timestamp']);
        $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
        $minutesDiffRemainder = floor($time_diff % 3600 / 60);
        $sOutput .= '"'.$hoursDiff.'h '.$minutesDiffRemainder.'m'.'", ';
        //col3
        $sOutput .= tpDatatableJsonCell((string) $record['name'].' '.(string) $record['lastname'].' ['.(string) $record['login'].']').', ';
        //col5 - TAGS
        $sOutput .= tpDatatableJsonCell((string) $record['label'].' ['.$record['item_id'].']');
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($params['action']) && $params['action'] === 'users_logged_in') {
    //Columns name
    $aColumns = ['login', 'name', 'lastname', 'timestamp', 'last_connexion'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }    

    // API presence is derived from teampass_api_sessions (one row per issued JWT).
    // A user is "API connected" while at least one session is neither revoked nor
    // expired — expires_at already encodes the token lifetime (api_token_duration),
    // so no extra grace computation is needed and log_system is no longer scanned.
    $nowTime = time();

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $subclause2 = $sWhere->addClause('OR');
    $subclause2->add('u.session_end >= %i', $nowTime);
    $subclause2->add(
        'EXISTS (SELECT 1 FROM '.prefixTable('api_sessions').' aps
            WHERE aps.user_id = u.id
                AND aps.revoked_at IS NULL
                AND aps.expires_at >= %i
        )',
        $nowTime
    );

    // Get the total number of records - use alias 'u'
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('users').' u
        WHERE %l',
        $sWhere
    );

    // Prepare the SQL query
    $sql = 'SELECT u.*,
        api_conn.last_api_date AS api_last_connection
    FROM '.prefixTable('users').' u
    LEFT JOIN (
        SELECT user_id, MAX(created_at) as last_api_date
        FROM '.prefixTable('api_sessions').'
        WHERE revoked_at IS NULL
            AND expires_at >= %i
        GROUP BY user_id
    ) api_conn ON api_conn.user_id = u.id
    WHERE %l
    ORDER BY %l %l
    LIMIT %i, %i';

    $params = [$nowTime, $sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $record = secureOutput($record, ['name', 'lastname', 'login']);
        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-id=\"'.$record['id'].'\">", ';
        //col2
        $sOutput .= tpDatatableJsonCell((string) $record['name'].' '.(string) $record['lastname'].' ['.(string) $record['login'].']').', ';
        //col3
        if ($record['admin'] === '1') {
            $user_role = $lang->get('god');
        } else {
            $user_role = $lang->get('user');
        }
        $sOutput .= '"'.$user_role.'", ';
        //col4
        $connectedSince = (int) $record['timestamp'];
        if ((int) $record['session_end'] < time() && !empty($record['api_last_connection'])) {
            $connectedSince = (int) $record['api_last_connection'];
        }
        $time_diff = time() - $connectedSince;
        $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
        $minutesDiffRemainder = floor($time_diff % 3600 / 60);
        $sOutput .= '"'.$hoursDiff.'h '.$minutesDiffRemainder.'m", ';
        //col5 (API connected)
        $apiConnected = !empty($record['api_last_connection']) ? 1 : 0;
        $sOutput .= '"'.$apiConnected.'" ';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($params['action']) && $params['action'] === 'tasks_in_progress') {
    //Columns name
    $aColumns = ['p.increment_id', 'p.created_at', 'p.updated_at', 'p.process_type', 'p.is_in_progress'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }    

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $subclause2 = $sWhere->addClause('OR');
    $subclause2->add('p.finished_at = ""');
    $subclause2->add('p.finished_at IS NULL');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('background_tasks').' AS p 
        LEFT JOIN '.prefixTable('users').' AS u ON %l
        WHERE %l ORDER BY %l %l',
        'u.id = json_extract(p.arguments, "$[0]")',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT p.increment_id, p.created_at, p.updated_at, p.process_type,
                p.is_in_progress, p.arguments
            FROM '.prefixTable('background_tasks').' AS p 
            LEFT JOIN '.prefixTable('users').' AS u ON %l
            WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = ['u.id = json_extract(p.arguments, "$[0]")',$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        // Get subtask progress
        $subtaskProgress = getSubtaskProgress($record['increment_id']);        

        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-done=\"'.$record['is_in_progress'].'\" data-type=\"'.$record['process_type'].'\" data-process-id=\"'.$record['increment_id'].'\"></span>", ';
        //col2
        $sOutput .= '"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['created_at']).'", ';
        //col3
        //$sOutput .= '"'.($record['updated_at'] === '' ? '-' : date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['updated_at'])).'", ';
        $sOutput .= '"<div class=\"progress mt-2\"><div class=\"progress-bar\" style=\"width: '.$subtaskProgress.'\">'.$subtaskProgress.'</div></div>", ';
        //col4
        $sOutput .= '"'.$record['process_type'].'", ';

        // col5
        $args = json_decode($record['arguments'], true);
        $args = is_array($args) ? $args : [];

        $sOutput .= tpDatatableJsonCell(resolveBackgroundTaskUserDisplay($args, (string) $record['process_type'])).', ';

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
} elseif (isset($params['action']) && $params['action'] === 'tasks_finished') {
    //Columns name
    $aColumns = ['p.created_at', 'p.finished_at', 'p.process_type', 'u.name'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('p.finished_at > 0');
    
    $historyDelay = isset($SETTINGS['tasks_history_delay']) === true ? (int) $SETTINGS['tasks_history_delay'] : 0;
    if ($historyDelay > 0 && $historyDelay < 86400) {
        $historyDelay = $historyDelay * 86400;
    }
    if ($historyDelay > 0) {
        $threshold = time() - $historyDelay;
        $sWhere->add('p.finished_at >= %i', $threshold);
    }

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('background_tasks').' AS p 
        LEFT JOIN '.prefixTable('users').' AS u ON u.id = json_extract(p.arguments, "$[0]")
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT p.*
    FROM '.prefixTable('background_tasks').' AS p 
    LEFT JOIN '.prefixTable('users').' AS u ON %l
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = ['u.id = json_extract(p.arguments, "$[0]")',$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
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
        $errMsg = is_null($record['error_message']) ? '' : (string) $record['error_message'];
        $errMsg = preg_replace('/\r\n|\r|\n/', ' ', $errMsg);
        $sOutput .= tpDatatableJsonCell($errMsg).', ';
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
            $processIcon = '<i class=\"fa-solid fa-user-plus infotip\" style=\"cursor: pointer;\" title=\"'.$lang->get('user_creation').'\"></i>';
        } else if ($record['process_type'] === 'send_email') {
            $processIcon = '<i class=\"fa-solid fa-envelope-circle-check infotip\" style=\"cursor: pointer;\" title=\"'.$lang->get('send_email_to_user').'\"></i>';
        } else if ($record['process_type'] === 'user_build_cache_tree') {
            $processIcon = '<i class=\"fa-solid fa-folder-tree infotip\" style=\"cursor: pointer;\" title=\"'.$lang->get('reload_user_cache_table').'\"></i>';
        } else if ($record['process_type'] === 'item_copy') {
            $processIcon = '<i class=\"fa-solid fa-copy infotip\" style=\"cursor: pointer;\" title=\"'.$lang->get('item_copied').'\"></i>';
        } else if ($record['process_type'] === 'item_update_create_keys') {
            $processIcon = '<i class=\"fa-solid fa-pencil infotip\" style=\"cursor: pointer;\" title=\"'.$lang->get('item_updated').'\"></i>';
        } else if ($record['process_type'] === 'new_item') {
            $processIcon = '<i class=\"fa-solid fa-square-plus infotip\" style=\"cursor: pointer;\" title=\"'.$lang->get('new_item').'\"></i>';
        } else {
            $processIcon = '<i class=\"fa-solid fa-question\"></i> ('.$record['process_type'].')';
        }
        $sOutput .= '"'.$processIcon.'", ';
        // col6
        $arguments = json_decode($record['arguments'], true);
        $arguments = is_array($arguments) ? $arguments : [];
        $sOutput .= tpDatatableJsonCell(resolveBackgroundTaskUserDisplay($arguments, (string) $record['process_type']));
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

// deepcode ignore XSS: data comes from database. Before being stored it is clean with feature antiXss->xss_clean
echo (string) $sOutput;

function getSubtaskProgress($id)
{
    $subtasks = DB::query(
        'SELECT *
        FROM ' . prefixTable('background_subtasks') . '
        WHERE task_id = %i',
        $id
    );

    $i = 0;
    $nb = count($subtasks);
    $finished_nb = 0;
    foreach ($subtasks as $task) {
        if (is_null($task['finished_at']) === false) {
            $finished_nb++;
        }

        $i++;
    }

    return ($finished_nb !== 0 ? pourcentage($finished_nb, $nb, 100) : 0) .'%';
}
