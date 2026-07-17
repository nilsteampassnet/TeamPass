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
 * Lightweight ACL-bound search endpoint: items (from the search cache) and
 * folders the user can see. No password value is ever read or returned.
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
$post_term = (string) $request->request->filter('term', '', FILTER_SANITIZE_SPECIAL_CHARS);

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
     * Palette search: items + folders visible to the user, ranked, bounded.
     */
    case 'palette_search':
        $term = paletteNormalizeTerm($post_term);
        if ($term === '') {
            echo (string) prepareExchangedData(
                ['error' => false, 'items' => [], 'folders' => []],
                'encode'
            );
            break;
        }

        // ACL scope: strictly the folders the session user can see.
        $accessibleFolders = array_filter(
            array_map('intval', (array) ($session->get('user-accessible_folders') ?? [])),
            static fn (int $id): bool => $id > 0
        );
        if (count($accessibleFolders) === 0) {
            echo (string) prepareExchangedData(
                ['error' => false, 'items' => [], 'folders' => []],
                'encode'
            );
            break;
        }

        $likeTerm = '%' . paletteEscapeLikeTerm($term) . '%';
        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

        // Belt-and-braces: exclude foreign personal folders (same rule as the
        // search page, on top of the accessible-folders scope). The user's own
        // personal tree (root titled with the user id + its children) stays in.
        $otherPersonalFolders = [];
        $pfRoot = DB::queryFirstRow(
            'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE title = %i',
            (int) $session->get('user-id')
        );
        if (empty($pfRoot['id']) === false) {
            $pfRows = DB::query(
                'SELECT id FROM ' . prefixTable('nested_tree') . '
                WHERE personal_folder = 1 AND NOT parent_id = %i AND NOT title = %i',
                (int) $pfRoot['id'],
                (int) $session->get('user-id')
            );
            foreach ($pfRows as $pfRow) {
                $otherPersonalFolders[] = (int) $pfRow['id'];
            }
        }
        $searchableFolders = array_values(array_diff($accessibleFolders, $otherPersonalFolders));
        if (count($searchableFolders) === 0) {
            echo (string) prepareExchangedData(
                ['error' => false, 'items' => [], 'folders' => []],
                'encode'
            );
            break;
        }

        // Items — from the search cache (labels/logins/urls/tags, no secret).
        $itemRecords = DB::query(
            'SELECT c.id, c.label, c.login, c.id_tree
            FROM ' . prefixTable('cache') . ' AS c
            WHERE c.id_tree IN %li
            AND (c.label LIKE %s OR c.login LIKE %s OR c.url LIKE %s OR c.tags LIKE %s)
            LIMIT 30',
            $searchableFolders,
            $likeTerm,
            $likeTerm,
            $likeTerm,
            $likeTerm
        );

        $items = [];
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
        $items = array_slice(paletteRankRows($items, $term, 'label'), 0, 8);

        // Folders — visible tree nodes matching by title.
        $folderRecords = DB::query(
            'SELECT id, title FROM ' . prefixTable('nested_tree') . '
            WHERE id IN %li AND title LIKE %s
            LIMIT 20',
            $searchableFolders,
            $likeTerm
        );

        $folders = [];
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
        $folders = array_slice(paletteRankRows($folders, $term, 'title'), 0, 5);

        echo (string) prepareExchangedData(
            ['error' => false, 'items' => $items, 'folders' => $folders],
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
