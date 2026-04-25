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
 * @file      kb.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;

// Load functions
require_once 'main.functions.php';

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
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('kb') === false ||
    $checkUserAccess->checkSession() === false
) {
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

$antiXss = new AntiXSS();

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key  = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$userId    = (int) $session->get('user-id');
$isAdmin   = (int) $session->get('user-admin') === 1;

// CSRF check
if ($post_key !== $session->get('key')) {
    echo prepareExchangedData(['error' => true, 'message' => 'key_not_conform'], 'encode');
    exit;
}

if (null === $post_type) {
    echo prepareExchangedData(['error' => true, 'message' => 'type_not_set'], 'encode');
    exit;
}

// Decode JSON data
$data = [];
if (null !== $post_data && '' !== $post_data) {
    $data = json_decode(
        html_entity_decode($post_data, ENT_QUOTES, 'UTF-8'),
        true
    ) ?? [];
}

switch ($post_type) {

    // -------------------------------------------------------
    case 'get_list':
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : 0;

        $where = 'k.deleted_at IS NULL';
        $params = [];

        if ($categoryId > 0) {
            $where .= ' AND k.category_id = %i';
            $params[] = $categoryId;
        }

        $rows = DB::query(
            'SELECT k.id, k.label, k.anyone_can_modify, k.author_id,
                    c.category, u.login AS author_login
             FROM ' . prefixTable('kb') . ' AS k
             LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON k.category_id = c.id
             LEFT JOIN ' . prefixTable('users') . ' AS u ON k.author_id = u.id
             WHERE ' . $where . '
             ORDER BY c.category ASC, k.label ASC',
            ...$params
        );

        $articles = [];
        foreach ($rows as $row) {
            $canEdit = $isAdmin || (int) $row['author_id'] === $userId || (int) $row['anyone_can_modify'] === 1;
            $canDelete = $isAdmin || (int) $row['author_id'] === $userId;
            $articles[] = [
                'id'               => (int) $row['id'],
                'label'            => htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'),
                'category'         => htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'),
                'author'           => htmlspecialchars($row['author_login'] ?? '', ENT_QUOTES, 'UTF-8'),
                'anyone_can_modify'=> (int) $row['anyone_can_modify'],
                'can_edit'         => $canEdit,
                'can_delete'       => $canDelete,
            ];
        }

        echo prepareExchangedData(['error' => false, 'articles' => $articles], 'encode');
        break;

    // -------------------------------------------------------
    case 'get_entry':
        $kbId = isset($data['id']) ? (int) $data['id'] : 0;
        if ($kbId === 0) {
            echo prepareExchangedData(['error' => true, 'message' => 'id_not_set'], 'encode');
            break;
        }

        $row = DB::queryFirstRow(
            'SELECT k.id, k.label, k.description, k.category_id, k.author_id, k.anyone_can_modify,
                    c.category, u.login AS author_login
             FROM ' . prefixTable('kb') . ' AS k
             LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON k.category_id = c.id
             LEFT JOIN ' . prefixTable('users') . ' AS u ON k.author_id = u.id
             WHERE k.id = %i AND k.deleted_at IS NULL',
            $kbId
        );

        if (null === $row) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_found'], 'encode');
            break;
        }

        $canEdit = $isAdmin || (int) $row['author_id'] === $userId || (int) $row['anyone_can_modify'] === 1;

        echo prepareExchangedData([
            'error'            => false,
            'id'               => (int) $row['id'],
            'label'            => htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'),
            'description'      => $row['description'],
            'category_id'      => (int) $row['category_id'],
            'category'         => htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'),
            'author'           => htmlspecialchars($row['author_login'] ?? '', ENT_QUOTES, 'UTF-8'),
            'anyone_can_modify'=> (int) $row['anyone_can_modify'],
            'can_edit'         => $canEdit,
        ], 'encode');
        break;

    // -------------------------------------------------------
    case 'save_entry':
        $kbId            = isset($data['id']) ? (int) $data['id'] : 0;
        $label           = $antiXss->xss_clean($data['label'] ?? '');
        $description     = $antiXss->xss_clean($data['description'] ?? '');
        $categoryName    = $antiXss->xss_clean($data['category'] ?? '');
        $anyoneCanModify = isset($data['anyone_can_modify']) && (int) $data['anyone_can_modify'] === 1 ? 1 : 0;

        if (empty($label) || empty($description) || empty($categoryName)) {
            echo prepareExchangedData(['error' => true, 'message' => 'missing_fields'], 'encode');
            break;
        }

        // Check edit permission for existing entry
        if ($kbId > 0) {
            $existing = DB::queryFirstRow(
                'SELECT author_id, anyone_can_modify FROM ' . prefixTable('kb') . ' WHERE id = %i AND deleted_at IS NULL',
                $kbId
            );
            if (null === $existing) {
                echo prepareExchangedData(['error' => true, 'message' => 'not_found'], 'encode');
                break;
            }
            if (!$isAdmin && (int) $existing['author_id'] !== $userId && (int) $existing['anyone_can_modify'] !== 1) {
                echo prepareExchangedData(['error' => true, 'message' => 'not_allowed'], 'encode');
                break;
            }
        }

        // Resolve or create category
        $catRow = DB::queryFirstRow(
            'SELECT id FROM ' . prefixTable('kb_categories') . ' WHERE category = %s',
            $categoryName
        );
        if (null === $catRow) {
            DB::insert(prefixTable('kb_categories'), ['category' => $categoryName]);
            $categoryId = DB::insertId();
        } else {
            $categoryId = (int) $catRow['id'];
        }

        if ($kbId > 0) {
            DB::update(
                prefixTable('kb'),
                [
                    'label'            => $label,
                    'description'      => $description,
                    'category_id'      => $categoryId,
                    'anyone_can_modify'=> $anyoneCanModify,
                ],
                'id = %i',
                $kbId
            );
        } else {
            DB::insert(
                prefixTable('kb'),
                [
                    'label'            => $label,
                    'description'      => $description,
                    'category_id'      => $categoryId,
                    'author_id'        => $userId,
                    'anyone_can_modify'=> $anyoneCanModify,
                    'deleted_at'       => null,
                ]
            );
            $kbId = DB::insertId();
        }

        echo prepareExchangedData(['error' => false, 'id' => $kbId], 'encode');
        break;

    // -------------------------------------------------------
    case 'delete_entry':
        $kbId = isset($data['id']) ? (int) $data['id'] : 0;
        if ($kbId === 0) {
            echo prepareExchangedData(['error' => true, 'message' => 'id_not_set'], 'encode');
            break;
        }

        $existing = DB::queryFirstRow(
            'SELECT author_id FROM ' . prefixTable('kb') . ' WHERE id = %i AND deleted_at IS NULL',
            $kbId
        );
        if (null === $existing) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_found'], 'encode');
            break;
        }
        if (!$isAdmin && (int) $existing['author_id'] !== $userId) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_allowed'], 'encode');
            break;
        }

        DB::update(
            prefixTable('kb'),
            ['deleted_at' => date('Y-m-d H:i:s')],
            'id = %i',
            $kbId
        );

        echo prepareExchangedData(['error' => false], 'encode');
        break;

    // -------------------------------------------------------
    case 'restore_entry':
        if (!$isAdmin) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_allowed'], 'encode');
            break;
        }

        $kbId = isset($data['id']) ? (int) $data['id'] : 0;
        if ($kbId === 0) {
            echo prepareExchangedData(['error' => true, 'message' => 'id_not_set'], 'encode');
            break;
        }

        DB::update(
            prefixTable('kb'),
            ['deleted_at' => null],
            'id = %i',
            $kbId
        );

        echo prepareExchangedData(['error' => false], 'encode');
        break;

    // -------------------------------------------------------
    case 'get_categories':
        $rows = DB::query(
            'SELECT c.id, c.category
             FROM ' . prefixTable('kb_categories') . ' AS c
             INNER JOIN ' . prefixTable('kb') . ' AS k ON k.category_id = c.id
             WHERE k.deleted_at IS NULL
             GROUP BY c.id, c.category
             ORDER BY c.category ASC'
        );

        $categories = [];
        foreach ($rows as $row) {
            $categories[] = [
                'id'       => (int) $row['id'],
                'category' => htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'),
            ];
        }

        echo prepareExchangedData(['error' => false, 'categories' => $categories], 'encode');
        break;

    // -------------------------------------------------------
    case 'get_deleted_list':
        if (!$isAdmin) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_allowed'], 'encode');
            break;
        }

        $rows = DB::query(
            'SELECT k.id, k.label, k.deleted_at, c.category, u.login AS author_login
             FROM ' . prefixTable('kb') . ' AS k
             LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON k.category_id = c.id
             LEFT JOIN ' . prefixTable('users') . ' AS u ON k.author_id = u.id
             WHERE k.deleted_at IS NOT NULL
             ORDER BY k.deleted_at DESC'
        );

        $articles = [];
        foreach ($rows as $row) {
            $articles[] = [
                'id'         => (int) $row['id'],
                'label'      => htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'),
                'category'   => htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'),
                'author'     => htmlspecialchars($row['author_login'] ?? '', ENT_QUOTES, 'UTF-8'),
                'deleted_at' => htmlspecialchars($row['deleted_at'], ENT_QUOTES, 'UTF-8'),
            ];
        }

        echo prepareExchangedData(['error' => false, 'articles' => $articles], 'encode');
        break;

    // -------------------------------------------------------
    case 'get_linked_items':
        $kbId = isset($data['id']) ? (int) $data['id'] : 0;
        if ($kbId === 0) {
            echo prepareExchangedData(['error' => true, 'message' => 'id_not_set'], 'encode');
            break;
        }

        $rows = DB::query(
            'SELECT ki.increment_id, i.id AS item_id, i.label, t.title AS folder
             FROM ' . prefixTable('kb_items') . ' AS ki
             INNER JOIN ' . prefixTable('items') . ' AS i ON ki.item_id = i.id
             LEFT JOIN ' . prefixTable('nested_tree') . ' AS t ON i.id_tree = t.id
             WHERE ki.kb_id = %i
             ORDER BY i.label ASC',
            $kbId
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'link_id' => (int) $row['increment_id'],
                'item_id' => (int) $row['item_id'],
                'label'   => htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'),
                'folder'  => htmlspecialchars($row['folder'] ?? '', ENT_QUOTES, 'UTF-8'),
            ];
        }

        echo prepareExchangedData(['error' => false, 'items' => $items], 'encode');
        break;

    // -------------------------------------------------------
    case 'search_items_for_kb':
        $search = $antiXss->xss_clean($data['search'] ?? '');
        if (mb_strlen($search) < 2) {
            echo prepareExchangedData(['error' => false, 'items' => []], 'encode');
            break;
        }

        $rows = DB::query(
            'SELECT i.id, i.label, t.title AS folder
             FROM ' . prefixTable('items') . ' AS i
             LEFT JOIN ' . prefixTable('nested_tree') . ' AS t ON i.id_tree = t.id
             WHERE i.label LIKE %ss AND i.inactif = 0
             ORDER BY i.label ASC
             LIMIT 20',
            $search
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'item_id' => (int) $row['id'],
                'label'   => htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'),
                'folder'  => htmlspecialchars($row['folder'] ?? '', ENT_QUOTES, 'UTF-8'),
            ];
        }

        echo prepareExchangedData(['error' => false, 'items' => $items], 'encode');
        break;

    // -------------------------------------------------------
    case 'add_item_link':
        $kbId   = isset($data['kb_id'])   ? (int) $data['kb_id']   : 0;
        $itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;

        if ($kbId === 0 || $itemId === 0) {
            echo prepareExchangedData(['error' => true, 'message' => 'id_not_set'], 'encode');
            break;
        }

        $kbRow = DB::queryFirstRow(
            'SELECT author_id, anyone_can_modify FROM ' . prefixTable('kb') . ' WHERE id = %i AND deleted_at IS NULL',
            $kbId
        );
        if (null === $kbRow) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_found'], 'encode');
            break;
        }
        if (!$isAdmin && (int) $kbRow['author_id'] !== $userId && (int) $kbRow['anyone_can_modify'] !== 1) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_allowed'], 'encode');
            break;
        }

        // Verify item exists
        $itemRow = DB::queryFirstRow('SELECT id FROM ' . prefixTable('items') . ' WHERE id = %i AND inactif = 0', $itemId);
        if (null === $itemRow) {
            echo prepareExchangedData(['error' => true, 'message' => 'item_not_found'], 'encode');
            break;
        }

        // Avoid duplicate
        $existing = DB::queryFirstRow(
            'SELECT increment_id FROM ' . prefixTable('kb_items') . ' WHERE kb_id = %i AND item_id = %i',
            $kbId, $itemId
        );
        if (null !== $existing) {
            echo prepareExchangedData(['error' => false, 'message' => 'already_linked'], 'encode');
            break;
        }

        DB::insert(prefixTable('kb_items'), ['kb_id' => $kbId, 'item_id' => $itemId]);

        echo prepareExchangedData(['error' => false], 'encode');
        break;

    // -------------------------------------------------------
    case 'remove_item_link':
        $kbId   = isset($data['kb_id'])   ? (int) $data['kb_id']   : 0;
        $itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;

        if ($kbId === 0 || $itemId === 0) {
            echo prepareExchangedData(['error' => true, 'message' => 'id_not_set'], 'encode');
            break;
        }

        $kbRow = DB::queryFirstRow(
            'SELECT author_id, anyone_can_modify FROM ' . prefixTable('kb') . ' WHERE id = %i AND deleted_at IS NULL',
            $kbId
        );
        if (null === $kbRow) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_found'], 'encode');
            break;
        }
        if (!$isAdmin && (int) $kbRow['author_id'] !== $userId && (int) $kbRow['anyone_can_modify'] !== 1) {
            echo prepareExchangedData(['error' => true, 'message' => 'not_allowed'], 'encode');
            break;
        }

        DB::delete(prefixTable('kb_items'), 'kb_id = %i AND item_id = %i', $kbId, $itemId);

        echo prepareExchangedData(['error' => false], 'encode');
        break;

    // -------------------------------------------------------
    default:
        echo prepareExchangedData(['error' => true, 'message' => 'unknown_type'], 'encode');
        break;
}
