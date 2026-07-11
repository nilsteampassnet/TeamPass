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
 * @file      reviews.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Access Recertification Campaigns (F2 — Enterprise governance).
 * Admin-only. A campaign snapshots the role↔folder grants; each grant is
 * attested or revoked, decisions are stored as audit evidence, and a
 * revocation actually removes the grant (same path as the Roles page).
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
require_once __DIR__ . '/reviews.functions.php';
require_once __DIR__ . '/reports.functions.php';

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
    $checkUserAccess->userAccessPage('reviews') === false ||
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

// Feature + role gate: recertification is open to administrators and to
// managers (delegated, restricted to the folders they can access).
$isAdminReviewer = (int) $session->get('user-admin') === 1;
$isManagerReviewer = (int) $session->get('user-manager') === 1
    || (int) $session->get('user-can_manage_all_users') === 1;

if ((int) ($SETTINGS['access_reviews_enabled'] ?? 0) !== 1
    || ($isAdminReviewer === false && $isManagerReviewer === false)
) {
    echo (string) prepareExchangedData(
        ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
        'encode'
    );
    exit;
}

// Folder perimeter for delegated managers (empty & unused for admins)
$accessibleFolders = $session->get('user-accessible_folders');
$personalFolders = $session->get('user-personal_visible_folders');
$reviewerScope = reviewsReviewerFolderScope(
    $isAdminReviewer,
    is_array($accessibleFolders) === true ? $accessibleFolders : [],
    is_array($personalFolders) === true ? $personalFolders : []
);

// Write subset of the perimeter — revocation requires write authority. A
// globally read-only user has no write folder at all.
$readOnlyFolders = $session->get('user-read_only_folders');
$reviewerWriteScope = reviewsReviewerWriteScope(
    $isAdminReviewer,
    (int) $session->get('user-read_only') === 1 ? [] : $reviewerScope,
    is_array($readOnlyFolders) === true ? $readOnlyFolders : []
);

// --------------------------------- //

// Read POST variables
$post_type = (string) $request->request->filter('type', '', FILTER_SANITIZE_SPECIAL_CHARS);
$post_key = (string) $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS);
$post_data = $request->request->get('data');

// Check KEY on every action
if ($post_key !== $session->get('key')) {
    echo (string) prepareExchangedData(
        ['error' => true, 'message' => $lang->get('key_is_not_correct')],
        'encode'
    );
    exit;
}

// decrypt and retrieve data in JSON format
$dataReceived = [];
if (empty($post_data) === false) {
    $dataReceived = prepareExchangedData((string) $post_data, 'decode');
    if (is_array($dataReceived) === false) {
        $dataReceived = [];
    }
}

$currentUserId = (int) $session->get('user-id');
$currentUserLogin = (string) $session->get('user-login');

switch ($post_type) {
    /*
     * START A CAMPAIGN — snapshot the current role↔folder grants
     */
    case 'start_review':
        $post_label = filter_var($dataReceived['label'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_folder_id = (int) filter_var($dataReceived['folder_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        if (empty($post_label) === true) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_empty_data')],
                'encode'
            );
            break;
        }

        // Scope: all non-personal folders, or a folder subtree
        $scopeFolderIds = [];
        if ($post_folder_id > 0) {
            // A manager may only start a campaign on a folder in their perimeter
            if (reviewsCanActOnFolder($post_folder_id, $isAdminReviewer, $reviewerScope) === false) {
                echo (string) prepareExchangedData(
                    ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                    'encode'
                );
                break;
            }
            $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $scopeFolderIds[] = $post_folder_id;
            foreach ($tree->getDescendants($post_folder_id) as $node) {
                $scopeFolderIds[] = (int) $node->id;
            }
            // Descendants outside the manager perimeter are dropped
            if ($isAdminReviewer === false) {
                $scopeFolderIds = array_values(array_intersect($scopeFolderIds, $reviewerScope));
            }
        } elseif ($isAdminReviewer === false) {
            // "All folders" for a manager means their whole perimeter
            $scopeFolderIds = $reviewerScope;
            if (count($scopeFolderIds) === 0) {
                echo (string) prepareExchangedData(
                    ['error' => true, 'message' => $lang->get('access_reviews_no_grants')],
                    'encode'
                );
                break;
            }
        }

        // Snapshot the grants
        $sql = 'SELECT rv.role_id, rt.title AS role_title, rv.folder_id, nt.title AS folder_title, rv.type
            FROM ' . prefixTable('roles_values') . ' AS rv
            INNER JOIN ' . prefixTable('roles_title') . ' AS rt ON rt.id = rv.role_id
            INNER JOIN ' . prefixTable('nested_tree') . ' AS nt ON nt.id = rv.folder_id
            WHERE nt.personal_folder = %i';
        if (count($scopeFolderIds) > 0) {
            $grants = DB::query(
                $sql . ' AND rv.folder_id IN %li ORDER BY rt.title ASC, nt.title ASC',
                0,
                $scopeFolderIds
            );
        } else {
            $grants = DB::query($sql . ' ORDER BY rt.title ASC, nt.title ASC', 0);
        }

        if (count($grants) === 0) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('access_reviews_no_grants')],
                'encode'
            );
            break;
        }

        DB::startTransaction();
        try {
            DB::insert(
                prefixTable('access_reviews'),
                [
                    'label' => $post_label,
                    'folder_scope' => $post_folder_id,
                    'started_by' => $currentUserId,
                    'started_at' => time(),
                    'status' => 'open',
                ]
            );
            $reviewId = (int) DB::insertId();

            foreach (reviewsBuildSnapshotRows($grants, $reviewId) as $row) {
                DB::insert(prefixTable('access_review_items'), $row);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_unknown')],
                'encode'
            );
            break;
        }

        // Governance evidence
        logEvents(
            $SETTINGS,
            'user_mngt',
            'at_review_started',
            (string) $currentUserId,
            $currentUserLogin,
            (string) $reviewId
        );

        echo (string) prepareExchangedData(
            ['error' => false, 'review_id' => $reviewId],
            'encode'
        );
        break;

    /*
     * LIST CAMPAIGNS with progress counters
     */
    case 'list_reviews':
        // Managers only see the campaigns they own; admins see them all
        $ownerFilterSql = $isAdminReviewer === true ? '' : ' WHERE r.started_by = %i';
        $listSql =
            'SELECT r.id, r.label, r.folder_scope, r.started_at, r.status, r.closed_at,
                u.login AS started_by_login,
                SUM(CASE WHEN ri.decision = %s THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN ri.decision = %s THEN 1 ELSE 0 END) AS attested,
                SUM(CASE WHEN ri.decision = %s THEN 1 ELSE 0 END) AS revoked
            FROM ' . prefixTable('access_reviews') . ' AS r
            LEFT JOIN ' . prefixTable('users') . ' AS u ON u.id = r.started_by
            LEFT JOIN ' . prefixTable('access_review_items') . ' AS ri ON ri.review_id = r.id' .
            $ownerFilterSql . '
            GROUP BY r.id, r.label, r.folder_scope, r.started_at, r.status, r.closed_at, u.login
            ORDER BY r.started_at DESC';
        $records = $isAdminReviewer === true
            ? DB::query($listSql, 'pending', 'attested', 'revoked')
            : DB::query($listSql, 'pending', 'attested', 'revoked', $currentUserId);

        $reviews = [];
        foreach ($records as $record) {
            $progress = reviewsProgress((int) $record['pending'], (int) $record['attested'], (int) $record['revoked']);
            $reviews[] = [
                'id' => (int) $record['id'],
                'label' => (string) $record['label'],
                'folder_scope' => (int) $record['folder_scope'],
                'started_at' => date('Y-m-d H:i', (int) $record['started_at']),
                'started_by' => (string) ($record['started_by_login'] ?? ''),
                'status' => (string) $record['status'],
                'closed_at' => empty($record['closed_at']) === false ? date('Y-m-d H:i', (int) $record['closed_at']) : '',
                'pending' => (int) $record['pending'],
                'attested' => (int) $record['attested'],
                'revoked' => (int) $record['revoked'],
                'total' => $progress['total'],
                'percent' => $progress['percent'],
            ];
        }

        echo (string) prepareExchangedData(
            ['error' => false, 'reviews' => $reviews],
            'encode'
        );
        break;

    /*
     * GET THE ITEMS OF A CAMPAIGN
     */
    case 'get_review_items':
        $post_review_id = (int) filter_input(INPUT_POST, 'review_id', FILTER_SANITIZE_NUMBER_INT);

        $review = DB::queryFirstRow(
            'SELECT id, label, status, started_by FROM ' . prefixTable('access_reviews') . ' WHERE id = %i',
            $post_review_id
        );
        if ($review === null
            || reviewsCanManageCampaign($isAdminReviewer, (int) $review['started_by'], $currentUserId) === false
        ) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $records = DB::query(
            'SELECT ri.id, ri.role_title, ri.folder_id, ri.folder_title, ri.access_type, ri.decision,
                ri.decided_at, u.login AS decided_by_login
            FROM ' . prefixTable('access_review_items') . ' AS ri
            LEFT JOIN ' . prefixTable('users') . ' AS u ON u.id = ri.decided_by
            WHERE ri.review_id = %i
            ORDER BY ri.role_title ASC, ri.folder_title ASC',
            $post_review_id
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'id' => (int) $record['id'],
                'role' => (string) $record['role_title'],
                'folder' => (string) $record['folder_title'],
                'access' => (string) $record['access_type'],
                'decision' => (string) $record['decision'],
                'decided_by' => (string) ($record['decided_by_login'] ?? ''),
                'decided_at' => empty($record['decided_at']) === false ? date('Y-m-d H:i', (int) $record['decided_at']) : '',
                // Revocation needs write authority on the folder (admins: always)
                'can_revoke' => reviewsCanActOnFolder((int) $record['folder_id'], $isAdminReviewer, $reviewerWriteScope),
            ];
        }

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'review' => [
                    'id' => (int) $review['id'],
                    'label' => (string) $review['label'],
                    'status' => (string) $review['status'],
                ],
                'items' => $items,
            ],
            'encode'
        );
        break;

    /*
     * RECORD A DECISION — attest keeps the grant, revoke removes it for real
     */
    case 'decide_review_item':
        $post_item_id = (int) filter_var($dataReceived['item_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $post_decision = (string) filter_var($dataReceived['decision'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_comment = (string) filter_var($dataReceived['comment'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (reviewsValidDecision($post_decision) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $item = DB::queryFirstRow(
            'SELECT ri.id, ri.review_id, ri.role_id, ri.folder_id, ri.decision,
                r.status AS review_status, r.started_by
            FROM ' . prefixTable('access_review_items') . ' AS ri
            INNER JOIN ' . prefixTable('access_reviews') . ' AS r ON r.id = ri.review_id
            WHERE ri.id = %i',
            $post_item_id
        );
        if ($item === null
            || reviewsCanDecide((string) $item['review_status'], (string) $item['decision']) === false
        ) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('access_reviews_decision_not_allowed')],
                'encode'
            );
            break;
        }

        // Delegated managers may only decide on their own campaigns, and only
        // on grants whose folder is within their current perimeter.
        if (reviewsCanManageCampaign($isAdminReviewer, (int) $item['started_by'], $currentUserId) === false
            || reviewsCanActOnFolder((int) $item['folder_id'], $isAdminReviewer, $reviewerScope) === false
        ) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        // Revoking removes access, so it needs write authority on the folder —
        // a manager can attest a read-only folder but not revoke on it.
        if ($post_decision === 'revoked'
            && reviewsCanActOnFolder((int) $item['folder_id'], $isAdminReviewer, $reviewerWriteScope) === false
        ) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('access_reviews_revoke_readonly')],
                'encode'
            );
            break;
        }

        // Record the decision (immutable evidence)
        DB::update(
            prefixTable('access_review_items'),
            [
                'decision' => $post_decision,
                'decided_by' => $currentUserId,
                'decided_at' => time(),
                'comment' => $post_comment === '' ? null : substr($post_comment, 0, 500),
            ],
            'id = %i',
            $post_item_id
        );

        // A revocation actually removes the grant — same effects as the Roles page
        if ($post_decision === 'revoked') {
            DB::delete(
                prefixTable('roles_values'),
                'role_id = %i AND folder_id = %i',
                (int) $item['role_id'],
                (int) $item['folder_id']
            );

            // Users holding this role must get a fresh tree
            $usersWithRole = DB::queryFirstColumn(
                'SELECT DISTINCT user_id FROM ' . prefixTable('users_roles') . '
                WHERE role_id = %i',
                (int) $item['role_id']
            );
            invalidateCacheForFolderUsers((int) $item['folder_id'], $usersWithRole);

            foreach ($usersWithRole as $userId) {
                if ((int) $userId === $currentUserId) {
                    continue;
                }
                emitWebSocketEvent(
                    'folder_permission_changed',
                    'user',
                    (int) $userId,
                    [
                        'role_id' => (int) $item['role_id'],
                        'folders' => [(int) $item['folder_id']],
                        'access' => '',
                    ]
                );
            }

            logEvents(
                $SETTINGS,
                'user_mngt',
                'at_review_access_revoked',
                (string) $currentUserId,
                $currentUserLogin,
                (string) $item['review_id']
            );
        }

        echo (string) prepareExchangedData(
            ['error' => false],
            'encode'
        );
        break;

    /*
     * CLOSE A CAMPAIGN — only when every grant has been decided
     */
    case 'close_review':
        $post_review_id = (int) filter_var($dataReceived['review_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

        $review = DB::queryFirstRow(
            'SELECT id, status, started_by FROM ' . prefixTable('access_reviews') . ' WHERE id = %i',
            $post_review_id
        );
        if ($review === null
            || reviewsCanManageCampaign($isAdminReviewer, (int) $review['started_by'], $currentUserId) === false
        ) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $pendingCount = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('access_review_items') . '
            WHERE review_id = %i AND decision = %s',
            $post_review_id,
            'pending'
        );

        if (reviewsCanClose((string) $review['status'], $pendingCount) === false) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('access_reviews_close_blocked')],
                'encode'
            );
            break;
        }

        DB::update(
            prefixTable('access_reviews'),
            [
                'status' => 'closed',
                'closed_by' => $currentUserId,
                'closed_at' => time(),
            ],
            'id = %i',
            $post_review_id
        );

        logEvents(
            $SETTINGS,
            'user_mngt',
            'at_review_closed',
            (string) $currentUserId,
            $currentUserLogin,
            (string) $post_review_id
        );

        echo (string) prepareExchangedData(
            ['error' => false],
            'encode'
        );
        break;

    /*
     * EXPORT A CAMPAIGN AS CSV EVIDENCE
     */
    case 'export_review':
        $post_review_id = (int) filter_input(INPUT_POST, 'review_id', FILTER_SANITIZE_NUMBER_INT);

        // Delegated managers may only export the campaigns they own
        $exportReview = DB::queryFirstRow(
            'SELECT started_by FROM ' . prefixTable('access_reviews') . ' WHERE id = %i',
            $post_review_id
        );
        if ($exportReview === null
            || reviewsCanManageCampaign($isAdminReviewer, (int) $exportReview['started_by'], $currentUserId) === false
        ) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $records = DB::query(
            'SELECT ri.role_title, ri.folder_title, ri.access_type, ri.decision,
                ri.decided_at, ri.comment, u.login AS decided_by_login
            FROM ' . prefixTable('access_review_items') . ' AS ri
            LEFT JOIN ' . prefixTable('users') . ' AS u ON u.id = ri.decided_by
            WHERE ri.review_id = %i
            ORDER BY ri.role_title ASC, ri.folder_title ASC',
            $post_review_id
        );

        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'role' => (string) $record['role_title'],
                'folder' => (string) $record['folder_title'],
                'access' => (string) $record['access_type'],
                'decision' => (string) $record['decision'],
                'decided_by' => (string) ($record['decided_by_login'] ?? ''),
                'decided_at' => empty($record['decided_at']) === false ? date('Y-m-d H:i:s', (int) $record['decided_at']) : '',
                'comment' => (string) ($record['comment'] ?? ''),
            ];
        }

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'csv' => reportsBuildCsv(
                    ['Role', 'Folder', 'Access', 'Decision', 'Decided by', 'Decided at', 'Comment'],
                    $rows,
                    ['role', 'folder', 'access', 'decision', 'decided_by', 'decided_at', 'comment']
                ),
            ],
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
