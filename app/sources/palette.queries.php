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
 * @file      palette.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Universal Search / Command Palette (F15 — Scale & polish).
 * Lightweight ACL-bound search endpoint: items (from the search cache), the
 * folders the user can see, and the knowledge base entries (title +
 * description). No password value is ever read or returned.
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
require_once __DIR__ . '/palette.functions.php';
require_once __DIR__ . '/search.functions.php';

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
            'type' => null !== $request->request->get('type') ? htmlspecialchars($request->request->get('type')) : '',
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
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);

// Feature gate: the command palette must be enabled by an admin.
if ((int) ($SETTINGS['command_palette_enabled'] ?? 0) !== 1) {
    echo (string) prepareExchangedData(
        ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
        'encode'
    );
    exit;
}

// --------------------------------- //

// Read POST variables
$post_type = (string) $request->request->filter('type', '', FILTER_SANITIZE_SPECIAL_CHARS);
$post_key = (string) $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS);
$post_term = $request->request->get('term', '');
$post_term = is_string($post_term) ? $post_term : '';

if ($post_key !== $session->get('key')) {
    echo (string) prepareExchangedData(
        ['error' => true, 'message' => $lang->get('key_is_not_correct')],
        'encode'
    );
    exit;
}

switch ($post_type) {
    /*
     * CASE
     * Palette search: items + folders visible to the user and knowledge base
     * entries, ranked, bounded.
     */
    case 'palette_search':
        $term = paletteNormalizeTerm($post_term);
        if ($term === '') {
            echo (string) prepareExchangedData(
                ['error' => false, 'items' => [], 'folders' => [], 'kb' => []],
                'encode'
            );
            break;
        }

        $items = [];
        $folders = [];

        // ACL scope: strictly the folders the session user can see.
        $accessibleFolders = array_filter(
            array_map('intval', (array) ($session->get('user-accessible_folders') ?? [])),
            static fn (int $id): bool => $id > 0
        );

        if (count($accessibleFolders) > 0) {
            $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // Use the same ACL primitive as both search pages. In particular,
            // own personal folders remain searchable at every nesting depth.
            $searchableFolders = searchResolveFolderScope(
                $accessibleFolders,
                array_merge(
                    (array) ($session->get('user-forbiden_personal_folders') ?? []),
                    getForeignPersonalFolderIds((int) $session->get('user-id'))
                )
            );

            if (count($searchableFolders) > 0) {
                // Item-level ACL. The folder scope alone is not an authorization
                // decision: an item can be restricted to named users or to roles
                // inside a folder the caller can otherwise see. Both other search
                // entry points enforce it — search.queries.php through this very
                // predicate, find.queries.php by dropping the row after the fact —
                // and the palette must not be the way around them.
                $userRoleIds = array_values(array_filter(array_map(
                    'intval',
                    is_array($session->get('user-roles_array')) === true ? $session->get('user-roles_array') : []
                )));
                $restrictionSql = searchItemRestrictionSql(
                    (int) $session->get('user-id'),
                    $userRoleIds,
                    'i',
                    prefixTable('restriction_to_roles')
                );

                // Items — from the search cache (labels/logins/urls/tags, no secret).
                // Authorization stays in SQL; decoded matching and relevance are
                // applied to the stream before the display limit.
                // Two further guards mirror the other entry points:
                //  - deleted_at, because the cache is pruned on delete but drifts
                //    (search.queries.php carries the same defence in depth);
                //  - perso/author, which catches a personal item sitting in a folder
                //    whose personal_folder flag was never written, the case the
                //    folder scope cannot see (same test as find.queries.php).
                $candidate = storedTextSearchCandidates(['c.label', 'c.login', 'c.url', 'c.tags'], [$term], 'palette_item');
                $itemPage = storedTextSearchPage(DB::queryWalk(
                    'SELECT c.id, c.label, c.login, c.url, c.tags, c.id_tree
                    FROM ' . prefixTable('cache') . ' AS c
                    INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = c.id)
                    WHERE c.id_tree IN %li_palette_scope
                    AND ' . $candidate['sql'] . '
                    AND i.deleted_at IS NULL
                    AND ' . $restrictionSql . '
                    AND (c.perso = 0 OR CAST(c.author AS UNSIGNED) = %i_palette_user)',
                    $candidate['params'] + ['palette_scope' => $searchableFolders, 'palette_user' => (int) $session->get('user-id')]
                ), [$term], ['label', 'login', 'url', 'tags'], 0, 8,
                    static fn (array $left, array $right): int => paletteCompareRows($left, $right, $term)
                );
                $itemRecords = $itemPage['rows'];

                foreach ($itemRecords as $record) {
                    $path = [];
                    foreach ($tree->getPath((int) $record['id_tree'], true) as $node) {
                        // Raw values: the client escapes them at render time.
                        $path[] = (string) $node->title;
                    }
                    $items[] = [
                        'id' => (int) $record['id'],
                        'label' => (string) $record['label'],
                        'login' => (string) $record['login'],
                        'folder_id' => (int) $record['id_tree'],
                        'path' => implode(' › ', $path),
                    ];
                }

                // Folders — visible tree nodes matching by title.
                $candidate = storedTextSearchCandidates(['title'], [$term], 'palette_folder');
                $folderPage = storedTextSearchPage(DB::queryWalk(
                    'SELECT id, title FROM ' . prefixTable('nested_tree') . '
                    WHERE id IN %li_palette_scope AND ' . $candidate['sql'],
                    $candidate['params'] + ['palette_scope' => $searchableFolders]
                ), [$term], ['title'], 0, 5,
                    static fn (array $left, array $right): int => paletteCompareRows($left, $right, $term, 'title')
                );
                $folderRecords = $folderPage['rows'];

                foreach ($folderRecords as $record) {
                    $path = [];
                    foreach ($tree->getPath((int) $record['id'], true) as $node) {
                        $path[] = (string) $node->title;
                    }
                    $folders[] = [
                        'id' => (int) $record['id'],
                        'title' => (string) $record['title'],
                        'path' => implode(' › ', $path),
                    ];
                }
            }
        }

        // Knowledge base — titles and descriptions. Same gate as the KB menu
        // and the KB page: feature enabled, non-admin, page allowed. Entries
        // carry no per-user ACL, so no folder scope applies here.
        $kb = [];
        if (
            (int) ($SETTINGS['enable_kb'] ?? 0) === 1
            && (int) $session->get('user-admin') !== 1
            && $checkUserAccess->userAccessPage('kb') === true
        ) {
            $candidate = storedTextSearchCandidates(['k.label', 'k.description'], [$term], 'palette_kb');
            $kbPage = storedTextSearchPage(DB::queryWalk(
                'SELECT k.id, k.label, k.description, c.category AS category
                FROM ' . prefixTable('kb') . ' AS k
                LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON (c.id = k.category_id)
                WHERE k.deleted_at IS NULL
                AND ' . $candidate['sql'],
                $candidate['params']
            ), [$term], ['label', 'description'], 0, 5,
                static fn (array $left, array $right): int => paletteCompareRows($left, $right, $term),
                ['description' => static fn (string $description): string => paletteFlattenRichText($description)]
            );
            $kbRecords = $kbPage['rows'];

            foreach ($kbRecords as $record) {
                $plainDescription = paletteFlattenRichText((string) $record['description']);
                $kb[] = [
                    'id' => (int) $record['id'],
                    'label' => (string) $record['label'],
                    'excerpt' => paletteBuildExcerpt($plainDescription, $term),
                    'category' => (string) ($record['category'] ?? ''),
                ];
            }
        }

        echo (string) prepareExchangedData(
            ['error' => false, 'items' => $items, 'folders' => $folders, 'kb' => $kb],
            'encode'
        );
        break;

    default:
        echo (string) prepareExchangedData(
            ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
            'encode'
        );
        break;
}
