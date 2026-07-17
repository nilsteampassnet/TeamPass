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
 * @file      reports.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Compliance Reports & Evidence Export (F6 — Enterprise governance).
 * Admin-only, metadata-only aggregations. No password value ever leaves
 * this handler.
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
require_once __DIR__ . '/reports.functions.php';
require_once __DIR__ . '/leaver.functions.php';
require_once __DIR__ . '/classification.functions.php';
require_once __DIR__ . '/rotation.functions.php';

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
    $checkUserAccess->userAccessPage('reports') === false ||
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

// Feature + role gate: compliance reports are admin-only and must be enabled.
if ((int) ($SETTINGS['compliance_reports_enabled'] ?? 0) !== 1
    || (int) $session->get('user-admin') !== 1
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
$post_start = (string) $request->request->filter('start', '', FILTER_SANITIZE_NUMBER_INT);
$post_end = (string) $request->request->filter('end', '', FILTER_SANITIZE_NUMBER_INT);

// Check KEY on every action
if ($post_key !== $session->get('key')) {
    echo (string) prepareExchangedData(
        ['error' => true, 'message' => $lang->get('key_is_not_correct')],
        'encode'
    );
    exit;
}

switch ($post_type) {
    /*
     * ACCESS MATRIX — one row per (user, role, folder) grant
     */
    case 'report_access_matrix':
        // Active human users with their roles (all sources: manual, AD, LDAP, OAuth2)
        $users = DB::query(
            'SELECT u.id, u.login, u.name, u.lastname,
                GROUP_CONCAT(DISTINCT ur.role_id ORDER BY ur.role_id SEPARATOR ";") AS fonction_id
            FROM ' . prefixTable('users') . ' AS u
            LEFT JOIN ' . prefixTable('users_roles') . ' AS ur ON (u.id = ur.user_id)
            WHERE u.deleted_at IS NULL
            AND u.id NOT IN %li
            GROUP BY u.id, u.login, u.name, u.lastname
            ORDER BY u.login ASC',
            [(int) TP_USER_ID, (int) OTV_USER_ID, (int) SSH_USER_ID, (int) API_USER_ID]
        );

        // Role → folder grants (non-personal folders only)
        $roleFolders = DB::query(
            'SELECT rv.role_id, rt.title AS role_title, rv.folder_id, nt.title AS folder_title, rv.type
            FROM ' . prefixTable('roles_values') . ' AS rv
            INNER JOIN ' . prefixTable('roles_title') . ' AS rt ON rt.id = rv.role_id
            INNER JOIN ' . prefixTable('nested_tree') . ' AS nt ON nt.id = rv.folder_id
            WHERE nt.personal_folder = %i
            ORDER BY rt.title ASC, nt.title ASC',
            0
        );

        $matrix = reportsBuildAccessMatrix($users, $roleFolders);

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'rows' => $matrix,
                'csv' => reportsBuildCsv(
                    ['Login', 'Name', 'Role', 'Folder id', 'Folder', 'Access'],
                    $matrix,
                    ['login', 'name', 'role', 'folder_id', 'folder', 'access']
                ),
            ],
            'encode'
        );
        break;

    /*
     * ACCESS CHANGES IN PERIOD — user management events from the system log
     */
    case 'report_access_changes':
        $bounds = reportsPeriodBounds(
            $post_start !== '' ? $post_start : null,
            $post_end !== '' ? $post_end : null,
            time()
        );

        $records = DB::query(
            'SELECT ls.date, ls.label, ls.qui, ls.field_1,
                u1.login AS author_login, u2.login AS target_login
            FROM ' . prefixTable('log_system') . ' AS ls
            LEFT JOIN ' . prefixTable('users') . ' AS u1 ON u1.id = ls.qui
            LEFT JOIN ' . prefixTable('users') . ' AS u2 ON u2.id = ls.field_1
            WHERE ls.type = %s
            AND CAST(ls.date AS UNSIGNED) BETWEEN %i AND %i
            ORDER BY CAST(ls.date AS UNSIGNED) DESC',
            'user_mngt',
            $bounds['start'],
            $bounds['end']
        );

        $rows = [];
        foreach ($records as $record) {
            $translated = $lang->get((string) $record['label']);
            $rows[] = [
                'date' => date('Y-m-d H:i:s', (int) $record['date']),
                'action' => empty($translated) === false ? $translated : (string) $record['label'],
                'author' => empty($record['author_login']) === false ? $record['author_login'] : (string) $record['qui'],
                'target' => empty($record['target_login']) === false ? $record['target_login'] : (string) ($record['field_1'] ?? ''),
            ];
        }

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'period' => $bounds,
                'rows' => $rows,
                'csv' => reportsBuildCsv(
                    ['Date', 'Action', 'Author', 'Target'],
                    $rows,
                    ['date', 'action', 'author', 'target']
                ),
            ],
            'encode'
        );
        break;

    /*
     * POSTURE SUMMARY — aggregated health flags (metadata only, no item name)
     *
     * Metadata-derived flags (weak, breached, overshared, overdue, no_expiry)
     * are recomputed LIVE here so the report is always current — no dependency
     * on when the last deep scan ran. Only reused/orphaned genuinely require
     * the deep scan (a decryption context the report handler does not have),
     * so they are read from the item_health snapshot and dated accordingly.
     */
    case 'report_posture_summary':
        $nowTs = time();
        $weakThreshold = (int) TP_PW_STRENGTH_3;
        $oversharedThreshold = (int) ($SETTINGS['security_dashboard_overshared_threshold'] ?? 10);
        if ($oversharedThreshold <= 0) {
            $oversharedThreshold = 10;
        }

        // Live metadata flags over all active items in non-personal folders.
        // Integer thresholds/timestamps are cast and embedded as literals (same
        // pattern as dashboard.queries.php); only string actions are bound.
        $lastRelevantSql = 'COALESCE(NULLIF(l.last_relevant_date, 0), NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0)';
        $live = DB::queryFirstRow(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN i.complexity_level <> \'\' AND CAST(i.complexity_level AS SIGNED) >= 0
                    AND CAST(i.complexity_level AS SIGNED) < ' . $weakThreshold . ' THEN 1 ELSE 0 END) AS weak,
                SUM(CASE WHEN i.hibp_status = 2 THEN 1 ELSE 0 END) AS breached,
                SUM(CASE WHEN COALESCE(sc.share_count, 0) > ' . $oversharedThreshold . ' THEN 1 ELSE 0 END) AS overshared,
                SUM(CASE WHEN n.renewal_period <= 0 THEN 1 ELSE 0 END) AS no_expiry,
                SUM(CASE WHEN n.renewal_period > 0 AND ' . $lastRelevantSql . ' > 0
                    AND (' . $lastRelevantSql . ' + n.renewal_period * ' . (int) TP_ONE_DAY_SECONDS . ') <= ' . $nowTs . ' THEN 1 ELSE 0 END) AS overdue
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)
            LEFT JOIN (
                SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
                FROM ' . prefixTable('log_items') . '
                WHERE action = %s OR (action = %s AND raison LIKE %s)
                GROUP BY id_item
            ) AS l ON (l.id_item = i.id)
            LEFT JOIN (
                SELECT object_id, COUNT(*) AS share_count
                FROM ' . prefixTable('sharekeys_items') . '
                GROUP BY object_id
            ) AS sc ON (sc.object_id = i.id)
            WHERE i.inactif = 0 AND i.deleted_at IS NULL AND n.personal_folder = 0',
            'at_creation',
            'at_modification',
            'at_pw%'
        );

        // Scan-bound flags (need a decryption context) come from the snapshot.
        $scan = DB::queryFirstRow(
            'SELECT
                COUNT(DISTINCT CASE WHEN flag_reused = 1 THEN item_id END) AS reused,
                COUNT(DISTINCT CASE WHEN flag_orphaned = 1 THEN item_id END) AS orphaned,
                COUNT(DISTINCT item_id) AS scanned,
                COALESCE(MAX(last_scan_at), 0) AS last_scan
            FROM ' . prefixTable('item_health')
        );

        $summary = reportsPostureSummary(
            [
                'weak' => (int) ($live['weak'] ?? 0),
                'breached' => (int) ($live['breached'] ?? 0),
                'overshared' => (int) ($live['overshared'] ?? 0),
                'overdue' => (int) ($live['overdue'] ?? 0),
                'no_expiry' => (int) ($live['no_expiry'] ?? 0),
            ],
            (int) ($live['total'] ?? 0),
            [
                'reused' => (int) ($scan['reused'] ?? 0),
                'orphaned' => (int) ($scan['orphaned'] ?? 0),
            ],
            (int) ($scan['scanned'] ?? 0),
            (int) ($scan['last_scan'] ?? 0)
        );

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'total_items' => $summary['total_items'],
                'scanned_items' => $summary['scanned_items'],
                'last_scan_at' => $summary['last_scan_at'],
                'rows' => $summary['issues'],
                'csv' => reportsBuildCsv(
                    ['Issue', 'Items', 'Percent', 'Source'],
                    $summary['issues'],
                    ['issue', 'items', 'percent', 'source']
                ),
            ],
            'encode'
        );
        break;

    /*
     * ROTATION EVIDENCE — leaver rotation flags and their current state (F3)
     */
    case 'report_rotation_evidence':
        $records = DB::query(
            'SELECT rf.item_id, rf.flagged_at, rf.status AS flag_status,
                i.label, n.title AS folder_title,
                u1.login AS flagged_by_login, u2.login AS leaver_login,
                COALESCE(l.last_relevant_date, NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0) AS last_pw_change
            FROM ' . prefixTable('rotation_flags') . ' AS rf
            INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = rf.item_id
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON n.id = i.id_tree
            LEFT JOIN ' . prefixTable('users') . ' AS u1 ON u1.id = rf.flagged_by
            LEFT JOIN ' . prefixTable('users') . ' AS u2 ON u2.id = rf.leaver_id
            LEFT JOIN (
                SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
                FROM ' . prefixTable('log_items') . '
                WHERE action = %s OR (action = %s AND raison LIKE %s)
                GROUP BY id_item
            ) AS l ON l.id_item = i.id
            ORDER BY rf.flagged_at DESC',
            'at_creation',
            'at_modification',
            'at_pw%'
        );

        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'label' => (string) $record['label'],
                'folder' => (string) $record['folder_title'],
                'leaver' => (string) ($record['leaver_login'] ?? ''),
                'flagged_by' => (string) ($record['flagged_by_login'] ?? ''),
                'flagged_at' => date('Y-m-d H:i:s', (int) $record['flagged_at']),
                'status' => leaverRiskItemDisplayStatus(
                    (int) $record['flagged_at'],
                    (int) $record['last_pw_change'],
                    (string) $record['flag_status']
                ),
            ];
        }

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'rows' => $rows,
                'csv' => reportsBuildCsv(
                    ['Item', 'Folder', 'Leaver', 'Flagged by', 'Flagged at', 'Status'],
                    $rows,
                    ['label', 'folder', 'leaver', 'flagged_by', 'flagged_at', 'status']
                ),
            ],
            'encode'
        );
        break;

    /*
     * CLASSIFICATION COVERAGE — items per classification level (F4)
     */
    case 'report_classification':
        // Total active shared items (the classifiable population)
        $totalItems = (int) DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON n.id = i.id_tree
            WHERE i.inactif = %i AND i.deleted_at IS NULL AND i.perso = %i AND n.personal_folder = %i',
            0,
            0,
            0
        );

        // Classified counts per level (active shared items only)
        $levelRecords = DB::query(
            'SELECT dc.level, COUNT(*) AS nb
            FROM ' . prefixTable('data_classification') . ' AS dc
            INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = dc.item_id
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON n.id = i.id_tree
            WHERE i.inactif = %i AND i.deleted_at IS NULL AND i.perso = %i AND n.personal_folder = %i
            GROUP BY dc.level',
            0,
            0,
            0
        );
        $levelCounts = [];
        foreach ($levelRecords as $levelRecord) {
            $levelCounts[(int) $levelRecord['level']] = (int) $levelRecord['nb'];
        }

        $rows = [];
        foreach (classificationCoverage($levelCounts, $totalItems) as $row) {
            $translated = $lang->get('classification_level_' . $row['slug']);
            $rows[] = [
                'level' => empty($translated) === false ? $translated : (string) $row['slug'],
                'items' => $row['items'],
                'percent' => $row['percent'],
            ];
        }

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'total_items' => $totalItems,
                'rows' => $rows,
                'csv' => reportsBuildCsv(
                    ['Classification', 'Items', 'Percent'],
                    $rows,
                    ['level', 'items', 'percent']
                ),
            ],
            'encode'
        );
        break;

    /*
     * OVERDUE ROTATIONS — items past (or nearing) their folder rotation SLA (F5)
     *
     * The SLA is the per-folder renewal_period (days). Metadata only — item
     * label + dates, consistent with the rotation evidence report.
     */
    case 'report_rotation_overdue':
        if ((int) ($SETTINGS['rotation_tracking_enabled'] ?? 0) !== 1) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $nowTs = time();
        $dueSoonDays = 14;
        $lastRelevantSql = 'COALESCE(NULLIF(l.last_relevant_date, 0), NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0)';

        // Items in SLA-covered shared folders whose due date falls inside the
        // window (already overdue or due within the look-ahead). Items without
        // a usable change date are excluded (same rule as the F1 overdue flag).
        $records = DB::query(
            'SELECT i.id AS item_id, i.label, n.id AS folder_id, n.title AS folder_title,
                n.renewal_period AS sla_days,
                ' . $lastRelevantSql . ' AS last_change
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)
            LEFT JOIN (
                SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
                FROM ' . prefixTable('log_items') . '
                WHERE action = %s OR (action = %s AND raison LIKE %s)
                GROUP BY id_item
            ) AS l ON (l.id_item = i.id)
            WHERE i.inactif = 0 AND i.deleted_at IS NULL AND n.personal_folder = 0
            AND n.renewal_period > 0
            AND ' . $lastRelevantSql . ' > 0
            AND (' . $lastRelevantSql . ' + n.renewal_period * ' . (int) TP_ONE_DAY_SECONDS . ') <= ' . ($nowTs + $dueSoonDays * (int) TP_ONE_DAY_SECONDS),
            'at_creation',
            'at_modification',
            'at_pw%'
        );

        $rows = rotationBuildOverdueRows($records, $nowTs, $dueSoonDays);
        foreach ($rows as &$row) {
            $translated = $lang->get('rotation_status_' . $row['status']);
            $row['status'] = empty($translated) === false ? $translated : (string) $row['status'];
        }
        unset($row);

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'rows' => $rows,
                'csv' => reportsBuildCsv(
                    ['Item', 'Folder', 'SLA (days)', 'Last change', 'Due', 'Days overdue', 'Status'],
                    $rows,
                    ['label', 'folder', 'sla_days', 'last_change', 'due_at', 'days_overdue', 'status']
                ),
            ],
            'encode'
        );
        break;

    /*
     * ROTATION SLA COVERAGE — per-folder SLA and overdue counts (F5)
     */
    case 'report_rotation_sla':
        if ((int) ($SETTINGS['rotation_tracking_enabled'] ?? 0) !== 1) {
            echo (string) prepareExchangedData(
                ['error' => true, 'message' => $lang->get('error_not_allowed_to')],
                'encode'
            );
            break;
        }

        $nowTs = time();
        $lastRelevantSql = 'COALESCE(NULLIF(l.last_relevant_date, 0), NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0)';

        // One row per shared folder: SLA, live item count, overdue count.
        $folderRecords = DB::query(
            'SELECT n.id AS folder_id, n.title AS folder_title, n.renewal_period AS sla_days,
                COUNT(i.id) AS items,
                SUM(CASE WHEN n.renewal_period > 0 AND ' . $lastRelevantSql . ' > 0
                    AND (' . $lastRelevantSql . ' + n.renewal_period * ' . (int) TP_ONE_DAY_SECONDS . ') <= ' . $nowTs . ' THEN 1 ELSE 0 END) AS overdue
            FROM ' . prefixTable('nested_tree') . ' AS n
            LEFT JOIN ' . prefixTable('items') . ' AS i
                ON (i.id_tree = n.id AND i.inactif = 0 AND i.deleted_at IS NULL)
            LEFT JOIN (
                SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
                FROM ' . prefixTable('log_items') . '
                WHERE action = %s OR (action = %s AND raison LIKE %s)
                GROUP BY id_item
            ) AS l ON (l.id_item = i.id)
            WHERE n.personal_folder = 0
            GROUP BY n.id, n.title, n.renewal_period',
            'at_creation',
            'at_modification',
            'at_pw%'
        );

        $coverage = rotationSlaCoverage($folderRecords);

        echo (string) prepareExchangedData(
            [
                'error' => false,
                'rows' => $coverage['rows'],
                'folders_total' => $coverage['summary']['folders_total'],
                'folders_with_sla' => $coverage['summary']['folders_with_sla'],
                'coverage_percent' => $coverage['summary']['coverage_percent'],
                'csv' => reportsBuildCsv(
                    ['Folder id', 'Folder', 'SLA (days)', 'Items', 'Overdue'],
                    $coverage['rows'],
                    ['folder_id', 'folder', 'sla_days', 'items', 'overdue']
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
