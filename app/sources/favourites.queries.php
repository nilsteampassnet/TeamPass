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
 * @file      favourites.queries.php
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
require_once 'find.functions.php';
$session = SessionManager::getSession();


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
    $checkUserAccess->userAccessPage('favourites') === false ||
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

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_key = (string) $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS);

$userId = (int) $session->get('user-id');

// Feature gate. Administrators have no item access at all, so the page - and
// every handler below - is closed to them exactly as favourites.php closes it.
if ((int) ($SETTINGS['enable_favourites'] ?? 0) !== 1
    || (int) $session->get('user-admin') === 1
) {
    echo json_encode(['error' => true, 'message' => $lang->get('error_not_allowed_to')]);
    exit;
}

// Session key guard, same as the other AJAX handlers (dashboard.queries.php).
if ($post_key !== $session->get('key')) {
    echo json_encode(['error' => true, 'message' => $lang->get('key_is_not_correct')]);
    exit;
}

/**
 * Refresh the session copy of the favourites list after a write.
 *
 * @param int $userId Current user.
 *
 * @return int Number of favourites now stored for this user.
 */
function favouritesRefreshSession(int $userId): int
{
    $session = SessionManager::getSession();
    $favs = getUserFavorites($userId);
    $session->set('user-favorites', $favs);

    return count($favs);
}

// manage action required
if (null !== $post_type) {
    switch ($post_type) {
        /*
         * CASE
         * Return the user's favourites with everything the page renders, in one call.
         *
         * A favourite row is only a bookmark: it survives folder right changes and item
         * deletion. The list is therefore re-authorized here with the same predicate the
         * Security Posture uses (folder grants, denials, personal tree and per-item
         * restrictions), never with the sharekey alone. Rows that do not pass are not
         * returned; they are only counted so the page can offer a cleanup.
         */
        case 'get_favorites':
            $stored = DB::query(
                'SELECT item_id, UNIX_TIMESTAMP(created_at) AS added_at
                FROM ' . prefixTable('users_favorites') . '
                WHERE user_id = %i
                ORDER BY created_at DESC',
                $userId
            );
            $totalStored = count($stored);

            $rows = [];
            if ($totalStored > 0) {
                $rows = DB::query(
                    'SELECT f.item_id AS id, UNIX_TIMESTAMP(f.created_at) AS added_at,
                        i.label, i.description, i.login, i.url, i.id_tree, i.perso,
                        i.fa_icon, i.restricted_to, i.viewed_no,
                        c.folder AS folder_path, c.renewal_period, c.timestamp AS item_timestamp,
                        nt.title AS folder_title,
                        tg.tags AS tags,
                        UNIX_TIMESTAMP(uli.accessed_at) AS last_used
                    FROM ' . prefixTable('users_favorites') . ' AS f
                    INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = f.item_id)
                    LEFT JOIN ' . prefixTable('cache') . ' AS c ON (c.id = i.id)
                    LEFT JOIN ' . prefixTable('nested_tree') . ' AS nt ON (nt.id = i.id_tree)
                    LEFT JOIN (
                        SELECT item_id, GROUP_CONCAT(tag SEPARATOR " ") AS tags
                        FROM ' . prefixTable('tags') . '
                        WHERE tag != %s
                        GROUP BY item_id
                    ) AS tg ON (tg.item_id = i.id)
                    LEFT JOIN ' . prefixTable('users_latest_items') . ' AS uli
                        ON (uli.item_id = i.id AND uli.user_id = %i)
                    WHERE f.user_id = %i AND i.inactif = %i
                        AND ' . securityPostureItemAccessSql($userId, 'i') . '
                    ORDER BY f.created_at DESC',
                    '',
                    $userId,
                    $userId,
                    0
                );
            }

            $expirationActive = (int) ($SETTINGS['activate_expiration'] ?? 0) === 1;
            $userLogin = (string) $session->get('user-login');
            $items = [];

            foreach ($rows as $record) {
                // Folder path: the cache holds the readable path with personal folder ids
                // already resolved to logins. Fall back to the folder title when the cache
                // row has not been built yet.
                $folderPath = trim((string) ($record['folder_path'] ?? ''));
                if ($folderPath === '') {
                    $folderTitle = (string) ($record['folder_title'] ?? '');
                    $folderPath = ($folderTitle === (string) $userId) ? $userLogin : $folderTitle;
                }

                $expired = false;
                if ($expirationActive === true
                    && (int) $record['renewal_period'] > 0
                    && ((int) $record['item_timestamp'] + ((int) $record['renewal_period'] * TP_ONE_DAY_SECONDS)) < time()
                ) {
                    $expired = true;
                }

                $url = (string) ($record['url'] ?? '');
                if ($url === '0') {
                    $url = '';
                }

                $tags = array_values(array_filter(
                    array_map('trim', explode(' ', (string) ($record['tags'] ?? ''))),
                    static fn (string $tag): bool => $tag !== ''
                ));

                $items[] = [
                    'id' => (int) $record['id'],
                    'label' => (string) $record['label'],
                    'description' => findBuildDescriptionPreview((string) ($record['description'] ?? ''), 140),
                    'login' => (string) ($record['login'] ?? ''),
                    'url' => $url,
                    'folder_id' => (int) $record['id_tree'],
                    'folder' => $folderPath,
                    'icon' => (string) ($record['fa_icon'] ?? ''),
                    'perso' => (int) $record['perso'],
                    'restricted' => (string) ($record['restricted_to'] ?? ''),
                    'tags' => $tags,
                    'expired' => $expired === true ? 1 : 0,
                    'added_at' => (int) ($record['added_at'] ?? 0),
                    'last_used' => (int) ($record['last_used'] ?? 0),
                    'views' => (int) ($record['viewed_no'] ?? 0),
                ];
            }

            // Favourites left without a readable item. Only the id and the date the
            // bookmark was created are exposed: the label, the folder and every other
            // attribute belong to an item this user is not allowed to read anymore.
            $visibleIds = array_column($items, 'id');
            $unavailable = [];
            foreach ($stored as $record) {
                if (in_array((int) $record['item_id'], $visibleIds, true) === false) {
                    $unavailable[] = [
                        'id' => (int) $record['item_id'],
                        'added_at' => (int) ($record['added_at'] ?? 0),
                    ];
                }
            }

            // Keep the session list aligned with what the user can actually reach.
            $session->set('user-favorites', getUserFavorites($userId));

            echo json_encode([
                'error' => false,
                'items' => $items,
                'unavailable' => count($unavailable),
                'unavailable_items' => $unavailable,
            ]);
            break;

        /*
         * CASE
         * Remove one item from the favourites.
         */
        case 'del_fav':
            // Remove from database using the dedicated table
            removeUserFavorite($userId, (int) $post_id);

            echo json_encode([
                'error' => false,
                'count' => favouritesRefreshSession($userId),
            ]);
            break;

        /*
         * CASE
         * Put back an item removed by mistake (undo). The folder is re-checked: a
         * favourite must never become a way to bookmark an item the user lost access to.
         */
        case 'add_fav':
            $itemId = (int) $post_id;
            if ($itemId <= 0 || accessToItemIsGranted($itemId, $SETTINGS) !== true) {
                echo json_encode([
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to_access_this_folder'),
                ]);
                break;
            }

            addUserFavorite($userId, $itemId);

            echo json_encode([
                'error' => false,
                'count' => favouritesRefreshSession($userId),
            ]);
            break;

        /*
         * CASE
         * Drop the favourites pointing at an item that was deleted or that the user may
         * no longer read. Computed server-side from the very same query as the list, so
         * the cleanup can never remove a row the page was still displaying.
         */
        case 'cleanup_unavailable':
            $visibleIds = DB::queryFirstColumn(
                'SELECT f.item_id
                FROM ' . prefixTable('users_favorites') . ' AS f
                INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = f.item_id)
                WHERE f.user_id = %i AND i.inactif = %i
                    AND ' . securityPostureItemAccessSql($userId, 'i'),
                $userId,
                0
            );

            if (count($visibleIds) > 0) {
                DB::query(
                    'DELETE FROM ' . prefixTable('users_favorites') . '
                    WHERE user_id = %i AND item_id NOT IN %li',
                    $userId,
                    array_map('intval', $visibleIds)
                );
            } else {
                DB::query(
                    'DELETE FROM ' . prefixTable('users_favorites') . ' WHERE user_id = %i',
                    $userId
                );
            }

            echo json_encode([
                'error' => false,
                'count' => favouritesRefreshSession($userId),
            ]);
            break;

        default:
            echo json_encode(['error' => true, 'message' => $lang->get('error_not_allowed_to')]);
    }
}
