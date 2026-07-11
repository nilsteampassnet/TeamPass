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
 * @file      classification.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Data Classification & Ownership (F4 — Enterprise governance).
 * Per-item classification label (Public / Internal / Confidential /
 * Restricted). Metadata only — no crypto involved. Changes are written
 * to the item history as audit evidence.
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
require_once __DIR__ . '/classification.functions.php';

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
        'user_key' => returnIfSet($session->get('key', 'SESSION'), null),
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

require_once TEAMPASS_APP . '/includes/language/' . $session->get('user-language') . '.php';

// Feature gate: data classification must be enabled by an admin.
if ((int) ($SETTINGS['data_classification_enabled'] ?? 0) !== 1) {
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
$post_item_id = (int) $request->request->filter('item_id', 0, FILTER_SANITIZE_NUMBER_INT);
$post_level = (int) $request->request->filter('level', -1, FILTER_SANITIZE_NUMBER_INT);

// Check KEY on every action
if ($post_key !== $session->get('key')) {
    echo (string) prepareExchangedData(
        ['error' => true, 'message' => $lang->get('key_is_not_correct')],
        'encode'
    );
    exit;
}

$userId = (int) $session->get('user-id');

/**
 * Load the item and verify the current user can see its folder.
 *
 * @param int   $itemId  Item id from the request
 * @param array $session Accessible folders come from the session
 *
 * @return array|null Item row {id, label, id_tree} or null when not allowed
 */
$loadAccessibleItem = function (int $itemId) use ($session): ?array {
    if ($itemId <= 0) {
        return null;
    }

    $item = DB::queryFirstRow(
        'SELECT id, label, id_tree
        FROM ' . prefixTable('items') . '
        WHERE id = %i AND inactif = %i AND deleted_at IS NULL',
        $itemId,
        0
    );
    if ($item === null) {
        return null;
    }

    $accessibleFolders = $session->get('user-accessible_folders');
    if (is_array($accessibleFolders) === false
        || in_array((int) $item['id_tree'], array_map('intval', $accessibleFolders), true) === false
    ) {
        return null;
    }

    return $item;
};

switch ($post_type) {
    /*
     * GET the classification of one item
     */
    case 'get_item_classification':
        $item = $loadAccessibleItem($post_item_id);
        if ($item === null) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $classification = DB::queryFirstRow(
            'SELECT dc.level, dc.updated_at, u.login AS updated_by_login
            FROM ' . prefixTable('data_classification') . ' AS dc
            LEFT JOIN ' . prefixTable('users') . ' AS u ON u.id = dc.updated_by
            WHERE dc.item_id = %i',
            $post_item_id
        );

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'item_id' => $post_item_id,
                'level' => $classification === null ? 0 : (int) $classification['level'],
                'updated_by' => $classification === null ? '' : (string) ($classification['updated_by_login'] ?? ''),
                'updated_at' => $classification === null || empty($classification['updated_at']) === true
                    ? '' : date('Y-m-d H:i', (int) $classification['updated_at']),
            ],
            'encode'
        );
        break;

    /*
     * SET the classification of one item (level 0 clears it)
     */
    case 'set_item_classification':
        if (classificationValidLevel($post_level) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $item = $loadAccessibleItem($post_item_id);
        if ($item === null) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        // Read-only users cannot classify — global flag or read-only access
        // to the item's folder (same signal as the frontend Edit gate).
        $readOnlyFolders = array_map('intval', $session->get('user-read_only_folders') ?? []);
        if ((int) $session->get('user-read_only') === 1
            || in_array((int) $item['id_tree'], $readOnlyFolders, true) === true
        ) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        if ($post_level === 0) {
            DB::delete(prefixTable('data_classification'), 'item_id = %i', $post_item_id);
        } else {
            DB::insertUpdate(
                prefixTable('data_classification'),
                [
                    'item_id' => $post_item_id,
                    'level' => $post_level,
                    // MVP ownership model: the last classifier is the recorded owner
                    'owner_id' => $userId,
                    'updated_by' => $userId,
                    'updated_at' => time(),
                ]
            );
        }

        // Item history = audit evidence of the classification change
        logItems(
            $SETTINGS,
            (int) $item['id'],
            (string) $item['label'],
            $userId,
            'at_modification',
            (string) $session->get('user-login'),
            'at_classification_changed:' . $post_level
        );

        echo (string) prepareExchangedData(
            ['error' => false, 'level' => $post_level],
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
