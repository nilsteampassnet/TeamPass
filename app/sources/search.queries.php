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
 * @file      search.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Faceted search backend for the Search page.
 *
 * No query is performed per rendered row: every permission rule is expressed
 * in SQL and applied before the LIMIT, so the item row counts are exact and
 * the pagination consistent.
 *
 * Requests are POSTed so search terms never land in web server access logs
 * or Referer headers.
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
require_once 'find.functions.php';
require_once 'search.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
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
    $checkUserAccess->userAccessPage('search') === false ||
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

// --------------------------------- //

// Session key guard, same as the other AJAX handlers (dashboard.queries.php).
$post_key = (string) $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS);
if ($post_key !== $session->get('key')) {
    echo json_encode([
        'draw' => (int) $request->request->filter('draw', 0, FILTER_SANITIZE_NUMBER_INT),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'folders' => [],
        'folders_truncated' => false,
    ]);
    exit;
}

$userId = (int) $session->get('user-id');
$userRoleIds = array_values(array_filter(array_map(
    'intval',
    is_array($session->get('user-roles_array')) === true ? $session->get('user-roles_array') : []
)));

// Feature gates. A facet whose feature is off must not merely be hidden in the
// UI: it is stripped server-side so a crafted payload cannot reach its SQL.
$featureClassification = (int) ($SETTINGS['data_classification_enabled'] ?? 0) === 1;
$featureHealth = (int) ($SETTINGS['security_dashboard_enabled'] ?? 0) === 1;
$featureRotation = (int) ($SETTINGS['leaver_risk_enabled'] ?? 0) === 1
    || (int) ($SETTINGS['rotation_tracking_enabled'] ?? 0) === 1;
$featureCustomFields = (int) ($SETTINGS['item_extra_fields'] ?? 0) === 1;
$featureFavourites = (int) ($SETTINGS['enable_favourites'] ?? 0) === 1;

/**
 * Resolve the custom fields whose values this user may search.
 *
 * Only unencrypted fields are reachable at all, and role_visibility is
 * enforced exactly as the item card does (core.php "LOAD CATEGORIES"):
 * a match on a hidden field would be an oracle on its value (issue #5176).
 * Note the asymmetric separators: user roles are ';'-joined, role_visibility
 * is ','-joined.
 *
 * @param string $userRoles Session `user-roles` value.
 *
 * @return array<int, array{id: int, title: string}>
 */
function searchVisibleCustomFields(string $userRoles): array
{
    $visible = [];
    $rows = DB::query(
        'SELECT id, title, role_visibility
        FROM ' . prefixTable('categories') . '
        WHERE encrypted_data = %i',
        0
    );
    $roles = explode(';', $userRoles);
    foreach ($rows as $row) {
        if ((string) $row['role_visibility'] === 'all'
            || count(array_intersect($roles, explode(',', (string) $row['role_visibility']))) > 0
        ) {
            $visible[] = ['id' => (int) $row['id'], 'title' => (string) $row['title']];
        }
    }

    return $visible;
}

// Resolve the folder scope once: accessible folders, minus foreign personal
// folders, optionally narrowed to a requested subtree (intersection only).
$rawFilters = [];
$filtersPayload = $request->request->get('filters');
if (is_string($filtersPayload) === true && $filtersPayload !== '') {
    $decoded = json_decode($filtersPayload, true);
    if (is_array($decoded) === true) {
        $rawFilters = $decoded;
    }
}
$filters = searchNormalizeFilters($rawFilters);

// Strip facets whose feature is disabled.
if ($featureClassification === false) {
    $filters['classification'] = [];
}
if ($featureHealth === false) {
    $filters['health'] = [];
    $filters['health_scan'] = [];
}
if ($featureRotation === false) {
    $filters['rotation_status'] = [];
}
if ($featureCustomFields === false) {
    $filters['custom_field_value'] = '';
    $filters['custom_field_id'] = [];
}
if ($featureFavourites === false) {
    $filters['favourites'] = false;
}

$requestedSubtree = null;
if (count($filters['folder']) > 0) {
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $requestedSubtree = array_keys($tree->getDescendants($filters['folder'][0], true));
}

$forbiddenPersonalFolders = array_merge(
    (array) $session->get('user-forbiden_personal_folders'),
    getForeignPersonalFolderIds($userId)
);
$folderScope = searchResolveFolderScope(
    (array) $session->get('user-accessible_folders'),
    $forbiddenPersonalFolders,
    $requestedSubtree
);

$draw = (int) $request->request->filter('draw', 0, FILTER_SANITIZE_NUMBER_INT);
$emptyOutput = [
    'draw' => $draw,
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => [],
    'folders' => [],
    'folders_truncated' => false,
];

// --------------------------------- //
// Filter options feeding the facet panel dropdowns.
// Everything is bounded by the same folder scope as the search itself:
// a global list would disclose the existence of items the user cannot see.
if ($request->request->get('type') === 'filter_options') {
    $options = [
        'folders' => [],
        'tags' => [],
        'custom_fields' => [],
    ];

    if (count($folderScope) > 0) {
        $folderOptionRows = DB::query(
            'SELECT folder.id, folder.title,
                COALESCE(GROUP_CONCAT(ancestor.title ORDER BY ancestor.nleft SEPARATOR \' / \'), \'\') AS folder_path
            FROM ' . prefixTable('nested_tree') . ' AS folder
            LEFT JOIN ' . prefixTable('nested_tree') . ' AS ancestor
                ON ancestor.id > 0
                AND ancestor.id IN %li_folder_scope
                AND ancestor.id != folder.id
                AND ancestor.nleft <= folder.nleft
                AND ancestor.nright >= folder.nright
            WHERE folder.id IN %li_folder_scope
            GROUP BY folder.id, folder.title, folder.nleft
            ORDER BY folder.nleft ASC, folder.title ASC',
            ['folder_scope' => $folderScope]
        );
        foreach ($folderOptionRows as $folderOptionRow) {
            $options['folders'][] = [
                'id' => (int) $folderOptionRow['id'],
                'title' => (string) $folderOptionRow['title'],
                'path' => (string) $folderOptionRow['folder_path'],
            ];
        }

        $tagRows = DB::query(
            'SELECT DISTINCT t.tag
            FROM ' . prefixTable('tags') . ' AS t
            INNER JOIN ' . prefixTable('cache') . ' AS c ON (c.id = t.item_id)
            WHERE c.id_tree IN %li AND t.tag != %s
            ORDER BY t.tag ASC
            LIMIT 300',
            $folderScope,
            ''
        );
        foreach ($tagRows as $tagRow) {
            $options['tags'][] = (string) $tagRow['tag'];
        }
    }

    if ($featureCustomFields === true) {
        $options['custom_fields'] = searchVisibleCustomFields((string) $session->get('user-roles'));
    }

    echo json_encode($options);
    exit;
}

// --------------------------------- //
// Search.

// Nothing visible at all, or a term too short with no facet to bound the scan.
if (count($folderScope) === 0
    || (count($filters['terms']) === 0 && searchHasActiveFacet($filters) === false)
) {
    echo json_encode($emptyOutput);
    exit;
}

// Folder matches are a separate result type. Item-only facets do not apply
// to them; the requested subtree and personal/shared scope do. The query is
// capped one row above the display limit so the UI can ask for refinement
// without running a second COUNT query.
$folderResults = [];
$folderResultsTruncated = false;
if (count($filters['terms']) > 0 && in_array('folder', (array) $filters['fields'], true)) {
    $folderResultScope = $folderScope;
    if ($filters['scope_perso'] !== '') {
        $folderResultScope = searchApplyPersonalFolderScope(
            $folderScope,
            getOwnPersonalFolderIds($userId),
            (string) $filters['scope_perso']
        );
    }

    $folderBuilt = searchBuildFolderWhere((array) $filters['terms'], $folderResultScope);
    if (count($folderBuilt['params']) > 0) {
        $folderRows = DB::query(
            'SELECT folder.id, folder.title,
                COALESCE(GROUP_CONCAT(ancestor.title ORDER BY ancestor.nleft SEPARATOR \' / \'), \'\') AS folder_path
            FROM ' . prefixTable('nested_tree') . ' AS folder
            LEFT JOIN ' . prefixTable('nested_tree') . ' AS ancestor
                ON ancestor.id > 0
                AND ancestor.id IN %li_folder_scope
                AND ancestor.id != folder.id
                AND ancestor.nleft <= folder.nleft
                AND ancestor.nright >= folder.nright
            WHERE ' . $folderBuilt['sql'] . '
            GROUP BY folder.id, folder.title, folder.nleft
            ORDER BY folder.title ASC, folder.id ASC
            LIMIT 21',
            $folderBuilt['params']
        );

        $folderResultsTruncated = count($folderRows) > 20;
        foreach (array_slice($folderRows, 0, 20) as $folderRow) {
            $folderResults[] = [
                'id' => (int) $folderRow['id'],
                'title' => (string) $folderRow['title'],
                'path' => (string) $folderRow['folder_path'],
            ];
        }
    }
}

$visibleFieldIds = [];
if ($featureCustomFields === true && $filters['custom_field_value'] !== '') {
    foreach (searchVisibleCustomFields((string) $session->get('user-roles')) as $field) {
        $visibleFieldIds[] = $field['id'];
    }
}

$healthSql = securityPasswordHealthSql('i');
$built = searchBuildWhere(
    $filters,
    [
        'user_id' => $userId,
        'role_ids' => $userRoleIds,
        'folder_scope' => $folderScope,
        'now' => time(),
        'day_seconds' => TP_ONE_DAY_SECONDS,
        'overshared_threshold' => (int) ($SETTINGS['security_dashboard_overshared_threshold'] ?? 10),
        'visible_field_ids' => $visibleFieldIds,
        'weak_sql' => $healthSql['weak'],
        'tables' => [
            'restriction_to_roles' => prefixTable('restriction_to_roles'),
            'files' => prefixTable('files'),
            'tags' => prefixTable('tags'),
            'users_favorites' => prefixTable('users_favorites'),
            'users_latest_items' => prefixTable('users_latest_items'),
            'item_health' => prefixTable('item_health'),
            'rotation_flags' => prefixTable('rotation_flags'),
            'sharekeys_items' => prefixTable('sharekeys_items'),
            'categories_items' => prefixTable('categories_items'),
        ],
    ]
);

// The classification join is needed both to filter and to render the badge.
$classificationJoin = '';
$classificationSelect = '';
if ($featureClassification === true) {
    $classificationJoin = ' LEFT JOIN ' . prefixTable('data_classification') . ' AS dc ON (dc.item_id = c.id)';
    $classificationSelect = ', COALESCE(dc.level, 0) AS classification_level';
}

$fromClause = ' FROM ' . prefixTable('cache') . ' AS c'
    . ' INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = c.id)'
    . $classificationJoin
    . ' WHERE ' . $built['sql'];

// Exact count: all filtering is in SQL, nothing is dropped afterwards.
$countRow = DB::queryFirstRow('SELECT COUNT(DISTINCT c.id) AS nb' . $fromClause, $built['params']);
$iTotal = (int) ($countRow['nb'] ?? 0);

$sortColumns = searchSortColumnMap();
$orderParam = $request->request->all()['order'] ?? null;
$sOrder = searchBuildOrderClause(
    $sortColumns,
    is_array($orderParam) ? ($orderParam[0]['column'] ?? null) : null,
    is_array($orderParam) ? ($orderParam[0]['dir'] ?? null) : null,
    'ORDER BY c.label ASC'
);

// Bound the page size: this endpoint has no rate limiter in front of it.
$start = max(0, (int) $request->request->filter('start', 0, FILTER_SANITIZE_NUMBER_INT));
$length = (int) $request->request->filter('length', 25, FILTER_SANITIZE_NUMBER_INT);
$length = ($length <= 0 || $length > 100) ? 100 : $length;

$rows = DB::query(
    'SELECT c.id, c.label, c.login, c.description, c.tags, c.url, c.folder,
        c.id_tree, c.perso, c.author, c.restricted_to, c.renewal_period, c.timestamp'
    . $classificationSelect
    . $fromClause
    . ' GROUP BY c.id'
    . ' ' . $sOrder
    . ' LIMIT ' . $start . ', ' . $length,
    $built['params']
);

// --------------------------------- //
// Mass-operation rights, resolved in ONE query for the whole page instead of
// one query per role per row.
$massOperationEnabled = (int) ($SETTINGS['enable_massive_move_delete'] ?? 0) === 1;
$folderAccessLevel = [];
if ($massOperationEnabled === true && count($rows) > 0 && count($userRoleIds) > 0) {
    $pageFolderIds = array_values(array_unique(array_map(
        static fn (array $row): int => (int) $row['id_tree'],
        $rows
    )));
    $accessRows = DB::query(
        'SELECT folder_id, type
        FROM ' . prefixTable('roles_values') . '
        WHERE role_id IN %li AND folder_id IN %li',
        $userRoleIds,
        $pageFolderIds
    );
    // Least permissive wins, same scale as before: W=0, R/NE/NDNE=1, ND=2.
    $levelByType = ['W' => 0, 'R' => 1, 'NE' => 1, 'NDNE' => 1, 'ND' => 2];
    foreach ($accessRows as $accessRow) {
        $folderId = (int) $accessRow['folder_id'];
        $level = $levelByType[(string) $accessRow['type']] ?? 4;
        $folderAccessLevel[$folderId] = isset($folderAccessLevel[$folderId])
            ? min($folderAccessLevel[$folderId], $level)
            : $level;
    }
}

$expirationActive = (int) ($SETTINGS['activate_expiration'] ?? 0) === 1;
$classificationLabels = [1 => 'public', 2 => 'internal', 3 => 'confidential', 4 => 'restricted'];
$classificationColours = [1 => 'success', 2 => 'info', 3 => 'warning', 4 => 'danger'];

$data = [];
foreach ($rows as $record) {
    $itemId = (int) $record['id'];
    $folderId = (int) $record['id_tree'];

    $expired = 0;
    if ($expirationActive === true
        && (int) $record['renewal_period'] > 0
        && ((int) $record['timestamp'] + ((int) $record['renewal_period'] * TP_ONE_DAY_SECONDS)) < time()
    ) {
        $expired = 1;
    }

    $accessLevel = $folderAccessLevel[$folderId] ?? 4;
    $canMassOperate = $massOperationEnabled === true && $accessLevel === 0;

    // Column 0 — selection only. The row actions live in the last column, where
    // the eye ends its scan; the row itself opens the detail modal.
    $controls = $canMassOperate === true
        ? '<input type="checkbox" value="0" class="mass_op_cb" data-id="' . $itemId . '">'
        : '';

    // Last column — quick actions, revealed on hover/focus. Real buttons, so
    // they are reachable with the keyboard. Everything the detail modal needs
    // travels with them rather than being re-queried.
    $actions = '<div class="search-row-actions"'
        . ' data-id="' . $itemId . '"'
        . ' data-perso="' . (int) $record['perso'] . '"'
        . ' data-tree-id="' . $folderId . '"'
        . ' data-expired="' . $expired . '"'
        . ' data-restricted-to="' . htmlspecialchars((string) $record['restricted_to'], ENT_QUOTES, 'UTF-8') . '"'
        . ' data-rights="' . ($canMassOperate ? 0 : 10) . '">';
    if ((string) $record['login'] !== '') {
        $actions .= '<button type="button" class="search-row-action infotip" data-action="login" title="'
            . htmlspecialchars($lang->get('favorites_copy_login'), ENT_QUOTES, 'UTF-8')
            . '"><i class="fa-regular fa-clone" aria-hidden="true"></i></button>';
    }
    $actions .= '<button type="button" class="search-row-action infotip" data-action="password" title="'
        . htmlspecialchars($lang->get('favorites_copy_password'), ENT_QUOTES, 'UTF-8')
        . '"><i class="fa-solid fa-key" aria-hidden="true"></i></button>'
        . '<button type="button" class="search-row-action infotip" data-action="open" title="'
        . htmlspecialchars($lang->get('favorites_open_item'), ENT_QUOTES, 'UTF-8')
        . '"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></button>'
        . '</div>';

    // Column 1 — label, with the classification badge when the feature is on.
    // The label is a real button: clicking anywhere on the row opens the detail
    // modal, and this is what makes the same thing reachable with the keyboard.
    $label = '<button type="button" class="search-label-btn" id="item_label-' . $itemId . '">'
        . htmlspecialchars((string) $record['label'], ENT_QUOTES, 'UTF-8') . '</button>';
    if ($featureClassification === true && (int) ($record['classification_level'] ?? 0) > 0) {
        $level = (int) $record['classification_level'];
        $label .= ' <span class="badge badge-' . ($classificationColours[$level] ?? 'secondary') . ' ml-1">'
            . htmlspecialchars($lang->get('classification_level_' . ($classificationLabels[$level] ?? '')), ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    $url = '';
    if ((string) $record['url'] !== '0' && (string) $record['url'] !== '') {
        $url = htmlspecialchars((string) filter_var($record['url'], FILTER_SANITIZE_URL), ENT_QUOTES, 'UTF-8');
    }

    $data[] = [
        $controls,
        $label,
        htmlspecialchars((string) $record['login'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(
            findBuildDescriptionPreview((string) $record['description'], 50),
            ENT_QUOTES,
            'UTF-8'
        ),
        htmlspecialchars(stripslashes((string) $record['tags']), ENT_QUOTES, 'UTF-8'),
        $url,
        htmlspecialchars(stripslashes((string) $record['folder']), ENT_QUOTES, 'UTF-8'),
        $actions,
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $iTotal,
    'recordsFiltered' => $iTotal,
    'data' => $data,
    'folders' => $folderResults,
    'folders_truncated' => $folderResultsTruncated,
]);
