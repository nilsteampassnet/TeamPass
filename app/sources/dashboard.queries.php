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
 * @file      dashboard.queries.php
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
    $checkUserAccess->userAccessPage('dashboard') === false ||
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

require_once TEAMPASS_APP . '/includes/language/' . $session->get('user-language') . '.php';

// Feature gate: the Security Posture Dashboard must be enabled by an admin.
// Admins have no access to items, so none of these handlers apply to them.
if ((int) ($SETTINGS['security_dashboard_enabled'] ?? 0) !== 1
    || (int) $session->get('user-admin') === 1
) {
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
$post_offset = (int) $request->request->filter('offset', 0, FILTER_SANITIZE_NUMBER_INT);
$post_limit = (int) $request->request->filter('limit', 50, FILTER_SANITIZE_NUMBER_INT);
$post_include_hibp = (int) $request->request->filter('include_hibp', 0, FILTER_SANITIZE_NUMBER_INT);

$userId = (int) $session->get('user-id');
$nowTs = time();

// Over-shared threshold (number of users above which an item is considered widely shared).
$oversharedThreshold = (int) ($SETTINGS['security_dashboard_overshared_threshold'] ?? 10);
if ($oversharedThreshold <= 0) {
    $oversharedThreshold = 10;
}
// "Weak" = password strength strictly below "medium".
$weakThreshold = TP_PW_STRENGTH_3;

// Reusable SQL fragments — all metadata-only (no decryption, no plaintext).
$lastRelevantSql = 'COALESCE(NULLIF(l.last_relevant_date, 0), NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0)';
$flagWeakSql = "(CASE WHEN i.complexity_level <> '' AND CAST(i.complexity_level AS SIGNED) >= 0 AND CAST(i.complexity_level AS SIGNED) < " . (int) $weakThreshold . " THEN 1 ELSE 0 END)";
$flagNoExpirySql = '(CASE WHEN n.renewal_period <= 0 THEN 1 ELSE 0 END)';
$flagOverdueSql = '(CASE WHEN n.renewal_period > 0 AND ' . $lastRelevantSql . ' > 0 AND (' . $lastRelevantSql . ' + n.renewal_period * ' . TP_ONE_DAY_SECONDS . ') <= ' . (int) $nowTs . ' THEN 1 ELSE 0 END)';
$flagOversharedSql = '(CASE WHEN COALESCE(sc.share_count, 0) > ' . (int) $oversharedThreshold . ' THEN 1 ELSE 0 END)';
$flagBreachedSql = '(CASE WHEN i.hibp_status = 2 THEN 1 ELSE 0 END)';

$logJoinSql = '
    LEFT JOIN (
        SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
        FROM ' . prefixTable('log_items') . '
        WHERE action = %s OR (action = %s AND raison LIKE %s)
        GROUP BY id_item
    ) AS l ON (l.id_item = i.id)';
$shareCountJoinSql = '
    LEFT JOIN (
        SELECT object_id, COUNT(*) AS share_count
        FROM ' . prefixTable('sharekeys_items') . '
        GROUP BY object_id
    ) AS sc ON (sc.object_id = i.id)';

switch ($post_type) {
    /*
     * CASE
     * Per-user posture summary (metadata, no decryption). Reused/orphaned counts
     * come from item_health (populated by the deep scan).
     */
    case 'get_summary':
        $post_filter_flag = (string) $request->request->filter('filter_flag', '', FILTER_SANITIZE_SPECIAL_CHARS);
        $post_filter_folder = (int) $request->request->filter('filter_folder', 0, FILTER_SANITIZE_NUMBER_INT);

        // Flag whitelist -> derived-table column. Filters the list to one issue type.
        $flagColumns = [
            'weak' => 't.flag_weak', 'reused' => 't.flag_reused', 'breached' => 't.flag_breached',
            'overdue' => 't.flag_overdue', 'no_expiry' => 't.flag_no_expiry',
            'overshared' => 't.flag_overshared', 'orphaned' => 't.flag_orphaned',
        ];
        $flagFilterSql = isset($flagColumns[$post_filter_flag]) === true ? ' AND ' . $flagColumns[$post_filter_flag] . ' = 1' : '';
        // Grouping (folder) scope. id_tree is an int, safe to embed; filters the list only.
        $folderClauseSql = $post_filter_folder > 0 ? ' AND i.id_tree = ' . $post_filter_folder : '';

        // Inner flagged-items SELECT — reused by the paged list, the total count and the dropdown.
        $flaggedInnerSql =
            'SELECT i.id, i.label, i.id_tree,
                ' . $flagWeakSql . ' AS flag_weak,
                ' . $flagNoExpirySql . ' AS flag_no_expiry,
                ' . $flagOverdueSql . ' AS flag_overdue,
                ' . $flagOversharedSql . ' AS flag_overshared,
                ' . $flagBreachedSql . ' AS flag_breached,
                COALESCE(ih.flag_reused, 0) AS flag_reused,
                COALESCE(ih.flag_orphaned, 0) AS flag_orphaned
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('sharekeys_items') . ' AS sk ON (sk.object_id = i.id AND sk.user_id = %i)
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)' .
            $logJoinSql . $shareCountJoinSql . '
            LEFT JOIN ' . prefixTable('item_health') . ' AS ih ON (ih.item_id = i.id AND ih.user_id = %i)
            WHERE i.inactif = 0 AND i.deleted_at IS NULL';
        $flagSumSql = '(t.flag_weak + t.flag_no_expiry + t.flag_overdue + t.flag_overshared + t.flag_breached + t.flag_reused + t.flag_orphaned)';

        // Paging window for the flagged-items list (incremental "load more").
        $listLimit = ($post_limit > 0 && $post_limit <= 200) ? $post_limit : 50;
        $listOffset = $post_offset >= 0 ? $post_offset : 0;

        // Paged list of flagged items, filtered by grouping + issue type.
        $rows = DB::query(
            'SELECT t.* FROM (' . $flaggedInnerSql . $folderClauseSql . ') AS t
            WHERE ' . $flagSumSql . ' > 0' . $flagFilterSql . '
            ORDER BY (t.flag_breached + t.flag_reused) DESC, t.flag_weak DESC, t.id DESC
            LIMIT %i OFFSET %i',
            $userId, 'at_creation', 'at_modification', 'at_pw%', $userId, $listLimit, $listOffset
        );

        // Total flagged items matching the current filters — drives the "load more" button.
        $listTotal = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM (' . $flaggedInnerSql . $folderClauseSql . ') AS t
            WHERE ' . $flagSumSql . ' > 0' . $flagFilterSql,
            $userId, 'at_creation', 'at_modification', 'at_pw%', $userId
        );

        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

        $list = [];
        foreach ($rows as $r) {
            $path = [];
            foreach ($tree->getPath((int) $r['id_tree'], true) as $t) {
                // Raw values: the client escapes them at render time via .text().
                $path[] = (string) $t->title;
            }
            $list[] = [
                'id' => (int) $r['id'],
                'folder_id' => (int) $r['id_tree'],
                'label' => (string) $r['label'],
                'path' => implode(' › ', $path),
                'flag_weak' => (int) $r['flag_weak'],
                'flag_no_expiry' => (int) $r['flag_no_expiry'],
                'flag_overdue' => (int) $r['flag_overdue'],
                'flag_overshared' => (int) $r['flag_overshared'],
                'flag_breached' => (int) $r['flag_breached'],
                'flag_reused' => (int) $r['flag_reused'],
                'flag_orphaned' => (int) $r['flag_orphaned'],
            ];
        }

        $response = [
            'error' => false,
            'list' => $list,
            'offset' => $listOffset,
            'limit' => $listLimit,
            'list_total' => $listTotal,
        ];

        // First page only: the global posture counts, the groupings dropdown and the
        // last-scan stamp don't change while paging, so they are computed once (offset 0)
        // and skipped on every "load more" request to keep paging cheap.
        if ($listOffset === 0) {
            // Metadata counts over the user's entitled items (global posture, not filtered).
            $summary = DB::queryFirstRow(
                'SELECT
                    COUNT(*) AS total,
                    SUM(' . $flagWeakSql . ') AS weak,
                    SUM(' . $flagNoExpirySql . ') AS no_expiry,
                    SUM(' . $flagOverdueSql . ') AS overdue,
                    SUM(' . $flagOversharedSql . ') AS overshared,
                    SUM(' . $flagBreachedSql . ') AS breached
                FROM ' . prefixTable('items') . ' AS i
                INNER JOIN ' . prefixTable('sharekeys_items') . ' AS sk ON (sk.object_id = i.id AND sk.user_id = %i)
                INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)' .
                $logJoinSql . $shareCountJoinSql . '
                WHERE i.inactif = 0 AND i.deleted_at IS NULL',
                $userId,
                'at_creation',
                'at_modification',
                'at_pw%'
            );

            // Reused/orphaned and last-scan come from the persisted per-user table.
            $healthAgg = DB::queryFirstRow(
                'SELECT
                    COALESCE(SUM(flag_reused), 0) AS reused,
                    COALESCE(SUM(flag_orphaned), 0) AS orphaned,
                    COALESCE(MAX(last_scan_at), 0) AS last_scan
                FROM ' . prefixTable('item_health') . '
                WHERE user_id = %i',
                $userId
            );

            // Distinct groupings (folders) holding flagged items — unfiltered, drives the dropdown.
            $folderRows = DB::query(
                'SELECT DISTINCT t.id_tree FROM (' . $flaggedInnerSql . ') AS t WHERE ' . $flagSumSql . ' > 0',
                $userId, 'at_creation', 'at_modification', 'at_pw%', $userId
            );

            // Build the groupings dropdown (folder id + readable path).
            $folders = [];
            foreach ($folderRows as $fr) {
                $fid = (int) $fr['id_tree'];
                $fpath = [];
                foreach ($tree->getPath($fid, true) as $t) {
                    // Raw value: the client escapes it at render time via .text().
                    $fpath[] = (string) $t->title;
                }
                $folders[] = ['id' => $fid, 'path' => implode(' › ', $fpath)];
            }
            usort($folders, static function (array $a, array $b): int {
                return strcasecmp((string) $a['path'], (string) $b['path']);
            });

            $response['counts'] = [
                'total' => (int) ($summary['total'] ?? 0),
                'weak' => (int) ($summary['weak'] ?? 0),
                'no_expiry' => (int) ($summary['no_expiry'] ?? 0),
                'overdue' => (int) ($summary['overdue'] ?? 0),
                'overshared' => (int) ($summary['overshared'] ?? 0),
                'breached' => (int) ($summary['breached'] ?? 0),
                'reused' => (int) ($healthAgg['reused'] ?? 0),
                'orphaned' => (int) ($healthAgg['orphaned'] ?? 0),
            ];
            $response['last_scan'] = (int) ($healthAgg['last_scan'] ?? 0);
            $response['folders'] = $folders;
        }

        echo (string) prepareExchangedData($response, 'encode');
        break;

    /*
     * CASE
     * Deep scan chunk — runs in the user's session (private key available).
     * Decrypts a chunk of entitled items to compute reuse buckets and optional
     * fresh HIBP, and stores all per-item flags. flag_reused is finalised later.
     */
    case 'deep_scan_chunk':
        if ($post_key !== $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $privateKey = (string) $session->get('user-private_key');
        $publicKey = (string) $session->get('user-public_key');
        if ($privateKey === '') {
            // No live decryption context — cannot scan plaintext.
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $chunkLimit = ($post_limit > 0 && $post_limit <= 200) ? $post_limit : 50;
        $chunkOffset = $post_offset >= 0 ? $post_offset : 0;

        // Stable per-install secret so identical passwords across users never
        // collide in the stored reuse bucket (no cross-user correlation).
        $reuseSalt = (string) @file_get_contents(TEAMPASS_SECRETS . '/' . SECUREFILE);
        $reuseSalt = $reuseSalt . '|item-health|' . $userId;

        $total = (int) DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('sharekeys_items') . ' AS sk ON (sk.object_id = i.id AND sk.user_id = %i)
            WHERE i.inactif = 0 AND i.deleted_at IS NULL',
            $userId
        );

        $rows = DB::query(
            'SELECT i.id, i.pw, i.pw_iv, i.complexity_level, i.created_at, i.hibp_status,
                n.renewal_period,
                ' . $lastRelevantSql . ' AS last_relevant_date,
                COALESCE(sc.share_count, 0) AS share_count,
                sk.share_key, sk.increment_id AS sk_increment_id
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('sharekeys_items') . ' AS sk ON (sk.object_id = i.id AND sk.user_id = %i)
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)' .
            $logJoinSql . $shareCountJoinSql . '
            WHERE i.inactif = 0 AND i.deleted_at IS NULL
            ORDER BY i.id ASC
            LIMIT %i OFFSET %i',
            $userId,
            'at_creation',
            'at_modification',
            'at_pw%',
            $chunkLimit,
            $chunkOffset
        );

        foreach ($rows as $r) {
            $itemId = (int) $r['id'];

            // Metadata flags (no decryption needed).
            $cl = (string) $r['complexity_level'];
            $flagWeak = ($cl !== '' && (int) $cl >= 0 && (int) $cl < $weakThreshold) ? 1 : 0;
            $renewal = (int) $r['renewal_period'];
            $flagNoExpiry = ($renewal <= 0) ? 1 : 0;
            $base = (int) $r['last_relevant_date'];
            $flagOverdue = ($renewal > 0 && $base > 0 && ($base + $renewal * TP_ONE_DAY_SECONDS) <= $nowTs) ? 1 : 0;
            $flagOvershared = ((int) $r['share_count'] > $oversharedThreshold) ? 1 : 0;
            $flagBreached = ((int) $r['hibp_status'] === 2) ? 1 : 0;

            // Decrypt in the user's own context.
            $flagOrphaned = 0;
            $reuseGroup = '';
            $objectKey = decryptUserObjectKeyWithMigration(
                (string) $r['share_key'],
                $privateKey,
                $publicKey,
                (int) $r['sk_increment_id'],
                'sharekeys_items'
            );

            $plaintext = '';
            if ($objectKey === '') {
                $flagOrphaned = 1;
            } else {
                $plaintext = (string) base64_decode(doDataDecryption((string) $r['pw'], $objectKey, (string) ($r['pw_iv'] ?? '')));
                if ($plaintext === '' && (string) $r['pw'] !== '') {
                    $flagOrphaned = 1;
                }
            }

            if ($plaintext !== '') {
                $reuseGroup = substr(hash_hmac('sha256', $plaintext, $reuseSalt), 0, 32);

                if ($post_include_hibp === 1) {
                    $hibp = checkPasswordWithHIBP($plaintext);
                    if (isset($hibp['pwned']) === true) {
                        $flagBreached = $hibp['pwned'] === true ? 1 : 0;
                        // Persist on the item, consistent with the on-demand HIBP check.
                        DB::update(
                            prefixTable('items'),
                            [
                                'hibp_status' => $flagBreached === 1 ? 2 : 1,
                                'hibp_count' => (int) $hibp['count'],
                                'hibp_checked_at' => (string) $nowTs,
                            ],
                            'id = %i',
                            $itemId
                        );
                    }
                }
            }

            // Persist per-user flags. flag_reused is left untouched here (finalised later).
            DB::query(
                'INSERT INTO ' . prefixTable('item_health') . '
                    (item_id, user_id, flag_weak, flag_breached, flag_overdue, flag_no_expiry, flag_overshared, flag_orphaned, reuse_group, last_scan_at)
                    VALUES (%i, %i, %i, %i, %i, %i, %i, %i, %s, %i)
                ON DUPLICATE KEY UPDATE
                    flag_weak = VALUES(flag_weak),
                    flag_breached = VALUES(flag_breached),
                    flag_overdue = VALUES(flag_overdue),
                    flag_no_expiry = VALUES(flag_no_expiry),
                    flag_overshared = VALUES(flag_overshared),
                    flag_orphaned = VALUES(flag_orphaned),
                    reuse_group = VALUES(reuse_group),
                    last_scan_at = VALUES(last_scan_at)',
                $itemId,
                $userId,
                $flagWeak,
                $flagBreached,
                $flagOverdue,
                $flagNoExpiry,
                $flagOvershared,
                $flagOrphaned,
                $reuseGroup,
                $nowTs
            );

            // Drop the plaintext as soon as possible.
            $plaintext = '';
        }

        $processed = count($rows);
        $nextOffset = $chunkOffset + $processed;
        $done = ($processed === 0 || $nextOffset >= $total);

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'processed' => $processed,
                'next_offset' => $nextOffset,
                'total' => $total,
                'done' => $done,
            ],
            'encode'
        );
        break;

    /*
     * CASE
     * Finalise the scan: compute reuse flags from the buckets, prune stale rows.
     */
    case 'finalize_scan':
        if ($post_key !== $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        // Reset, then flag every item whose bucket has more than one member
        // (canonical logic shared with the save-time refresh).
        finalizeUserReuseFlags($userId);

        // Hygiene: drop rows for items the user can no longer access.
        DB::query(
            'DELETE ih FROM ' . prefixTable('item_health') . ' AS ih
            LEFT JOIN ' . prefixTable('sharekeys_items') . ' AS sk ON (sk.object_id = ih.item_id AND sk.user_id = ih.user_id)
            WHERE ih.user_id = %i AND sk.increment_id IS NULL',
            $userId
        );

        // F10: freeze the score snapshot so the dashboard can show "+N since last scan".
        // Computed after the reuse flags are finalised, so `reused` is accurate.
        $freshScore = (int) securityScoreCompute($userId)['score'];
        $prevScore = DB::queryFirstField(
            'SELECT last_score FROM ' . prefixTable('user_nudges') . ' WHERE user_id = %i',
            $userId
        );
        $scoreDelta = ($prevScore !== null) ? ($freshScore - (int) $prevScore) : null;
        DB::query(
            'INSERT INTO ' . prefixTable('user_nudges') . ' (user_id, last_score, last_score_delta, last_score_at)
            VALUES (%i, %i, %i, %i)
            ON DUPLICATE KEY UPDATE
                last_score = VALUES(last_score),
                last_score_delta = VALUES(last_score_delta),
                last_score_at = VALUES(last_score_at)',
            $userId,
            $freshScore,
            $scoreDelta,
            $nowTs
        );

        // F8: push the fresh actionable posture to the user live so the in-app nudge
        // reacts without a page reload (no plaintext — counts only).
        if ((int) ($SETTINGS['security_nudges_enabled'] ?? 0) === 1) {
            $nudgeCounts = securityNudgeComputeCounts($userId);
            emitWebSocketEvent('security_nudge', 'user', $userId, [
                'breached' => $nudgeCounts['breached'],
                'weak' => $nudgeCounts['weak'],
                'reused' => $nudgeCounts['reused'],
                'overdue' => $nudgeCounts['overdue'],
                'total' => $nudgeCounts['total_flagged'],
            ]);
        }

        echo (string) prepareExchangedData(['error' => false], 'encode');
        break;

    /*
     * CASE
     * F8 — actionable posture summary for the proactive in-app nudge. Returns counts
     * for the four user-fixable triggers (breached/weak/reused/overdue), the worst
     * flagged item for a deep-link, and whether the last scan is stale. Metadata-only.
     */
    case 'get_nudge_summary':
        if ((int) ($SETTINGS['security_nudges_enabled'] ?? 0) !== 1) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $counts = securityNudgeComputeCounts($userId);
        $staleDays = (int) ($SETTINGS['security_nudges_stale_scan_days'] ?? 14);
        if ($staleDays <= 0) {
            $staleDays = 14;
        }
        $stale = ($counts['last_scan'] <= 0) || (($nowTs - $counts['last_scan']) > $staleDays * TP_ONE_DAY_SECONDS);

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'counts' => [
                    'breached' => $counts['breached'],
                    'weak' => $counts['weak'],
                    'reused' => $counts['reused'],
                    'overdue' => $counts['overdue'],
                    'total' => $counts['total_flagged'],
                ],
                'worst_item' => $counts['worst_item'],
                'last_scan' => $counts['last_scan'],
                'stale' => $stale,
            ],
            'encode'
        );
        break;

    /*
     * CASE
     * F8 — per-item health flags for the items-list badge. Complements (never
     * duplicates) the item-card HIBP badge by marking at-risk rows at a glance.
     * Input: item_ids (CSV). Output: { item_id: {breached,weak,reused,overdue} }
     * for flagged items only. Metadata-only, scoped to the user's own entitlement.
     */
    case 'get_folder_flags':
        if ((int) ($SETTINGS['security_nudges_enabled'] ?? 0) !== 1) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $post_item_ids = (string) $request->request->filter('item_ids', '', FILTER_SANITIZE_SPECIAL_CHARS);
        $itemIds = [];
        foreach (explode(',', $post_item_ids) as $rawId) {
            $iid = (int) trim($rawId);
            if ($iid > 0) {
                $itemIds[] = $iid;
            }
        }
        $itemIds = array_values(array_unique($itemIds));
        if (count($itemIds) === 0) {
            echo (string) prepareExchangedData(['error' => false, 'flags' => []], 'encode');
            break;
        }
        // Bound the payload (defensive).
        if (count($itemIds) > 500) {
            $itemIds = array_slice($itemIds, 0, 500);
        }

        // Placeholder order: %i (sharekeys) · %s %s %s (log) · %i (item_health) · IN %li.
        $flagRows = DB::query(
            'SELECT i.id,
                ' . $flagWeakSql . ' AS flag_weak,
                ' . $flagOverdueSql . ' AS flag_overdue,
                ' . $flagBreachedSql . ' AS flag_breached,
                COALESCE(ih.flag_reused, 0) AS flag_reused
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('sharekeys_items') . ' AS sk ON (sk.object_id = i.id AND sk.user_id = %i)
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)' .
            $logJoinSql . '
            LEFT JOIN ' . prefixTable('item_health') . ' AS ih ON (ih.item_id = i.id AND ih.user_id = %i)
            WHERE i.inactif = 0 AND i.deleted_at IS NULL AND i.id IN %li',
            $userId, 'at_creation', 'at_modification', 'at_pw%', $userId, $itemIds
        );

        $flags = [];
        foreach ($flagRows as $fr) {
            $w = (int) $fr['flag_weak'];
            $o = (int) $fr['flag_overdue'];
            $b = (int) $fr['flag_breached'];
            $re = (int) $fr['flag_reused'];
            if ($w + $o + $b + $re > 0) {
                $flags[(string) (int) $fr['id']] = [
                    'breached' => $b,
                    'weak' => $w,
                    'reused' => $re,
                    'overdue' => $o,
                ];
            }
        }

        echo (string) prepareExchangedData(['error' => false, 'flags' => $flags], 'encode');
        break;

    /*
     * CASE
     * F10 — Personal Security Score. Single 0–100 score + qualitative band + the
     * worst three issue categories, computed by the canonical server-side helper so
     * the dashboard gauge and the always-on topbar badge always show the same number.
     * Metadata-only (no decryption), scoped to the user's own entitlement.
     */
    case 'get_score':
        $scoreData = securityScoreCompute($userId);
        echo (string) prepareExchangedData(
            [
                'error' => false,
                'score' => $scoreData['score'],
                'band' => $scoreData['band'],
                'total_items' => $scoreData['total_items'],
                'scanned' => $scoreData['scanned'],
                'last_scan' => $scoreData['last_scan'],
                'delta' => $scoreData['delta'],
                'delta_at' => $scoreData['delta_at'],
                'counts' => $scoreData['counts'],
                'weights' => $scoreData['weights'],
                'top3' => $scoreData['top3'],
                'worst_item' => $scoreData['worst_item'],
            ],
            'encode'
        );
        break;

    default:
        echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        break;
}
