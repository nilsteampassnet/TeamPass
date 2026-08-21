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
 * @file      lapr.monitoring.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;

/**
 * Return the grace period used before a due rotation or scheduler tick is
 * considered late. Two scheduler intervals avoid false alarms while the
 * background handler is between two normal runs; ten minutes is the floor.
 */
function laprMonitoringGraceSeconds(array $settings): int
{
    $intervalMinutes = max(1, (int) ($settings['lapr_scheduler_interval_minutes'] ?? 5));

    return max(600, $intervalMinutes * 120);
}

/**
 * Normalize the human LAPR access counters. A granted enabled user becomes an
 * effective operator only while the module itself is enabled.
 *
 * @param array<string,mixed> $row
 * @return array<string,int>
 */
function laprMonitoringAccessSummary(bool $moduleEnabled, array $row): array
{
    $grantedActive = max(0, (int) ($row['granted_active'] ?? 0));
    $disabledGrants = max(0, (int) ($row['disabled_grants'] ?? 0));
    $grantedTotal = max(0, (int) ($row['granted_total'] ?? ($grantedActive + $disabledGrants)));

    return [
        'active' => $moduleEnabled ? $grantedActive : 0,
        'granted_active' => $grantedActive,
        'disabled_grants' => $disabledGrants,
        'granted_total' => $grantedTotal,
    ];
}

/**
 * Classify one managed account without touching the database.
 *
 * @param array<string,mixed> $account
 */
function laprMonitoringClassifyAccount(array $account, int $nowTs, int $graceSeconds): string
{
    $status = (string) ($account['status'] ?? 'error');
    if ($status === 'paused') {
        return 'paused';
    }
    if ($status !== 'active') {
        return 'error';
    }

    if (empty($account['endpoint_id_resolved']) === true
        || (string) ($account['endpoint_status'] ?? '') !== 'active'
        || empty($account['managed_item_id']) === true
        || (bool) ($account['monitoring_integrity_error'] ?? false) === true
    ) {
        return 'error';
    }

    $lastStatus = (string) ($account['last_rotation_status'] ?? 'never');
    $retryAt = laprMonitoringTimestamp($account['retry_at'] ?? null);
    $nextRotationAt = laprMonitoringTimestamp($account['next_rotation_at'] ?? null);

    if ($lastStatus === 'failure') {
        if ($retryAt !== null && $retryAt >= ($nowTs - $graceSeconds)) {
            return 'retrying';
        }

        return $retryAt !== null ? 'overdue' : 'error';
    }

    if ($nextRotationAt === null) {
        return 'error';
    }
    if ($nextRotationAt < ($nowTs - $graceSeconds)) {
        return 'overdue';
    }

    return $lastStatus === 'never' ? 'scheduled' : 'healthy';
}

/**
 * Map a secret-free LAPR error code to a stable presentation category.
 */
function laprMonitoringFailureCategory(string $errorCode): string
{
    if (in_array($errorCode, ['ERR_TIMEOUT', 'ERR_REFUSED', 'ERR_HOST_UNREACHABLE', 'ERR_NOT_CONNECTED'], true)) {
        return 'connectivity';
    }
    if ($errorCode === 'ERR_AUTH_FAILED') {
        return 'authentication';
    }
    if ($errorCode === 'ERR_HOSTKEY_MISMATCH') {
        return 'hostkey';
    }
    if ($errorCode === 'ERR_CHPASSWD_FAILED') {
        return 'password_change';
    }
    if (in_array($errorCode, ['ERR_ITEM_UPDATE_FAILED', 'ERR_SSH_CREDENTIAL_SYNC_FAILED'], true)) {
        return 'synchronization';
    }
    if ($errorCode === 'ERR_PASSWORD_GEN') {
        return 'password_generation';
    }
    if ($errorCode === 'ERR_SERVER_KEY') {
        return 'encryption';
    }
    if (in_array($errorCode, ['ERR_INVALID_USERNAME', 'ERR_HOSTNAME_NOT_ALLOWED', 'ERR_ACCOUNT_NOT_FOUND'], true)) {
        return 'configuration';
    }

    return 'other';
}

/**
 * Build the point-in-time LAPR report shared by Health System and statistics.
 * Only operational metadata is returned; item contents and SSH secrets are
 * deliberately never selected.
 *
 * @return array<string,mixed>
 */
function laprBuildMonitoringSnapshot(array $settings, int $nowTs): array
{
    $enabled = (int) ($settings['lapr_enabled'] ?? 0) === 1;
    $requiredTables = [
        prefixTable('lapr_endpoints'),
        prefixTable('lapr_accounts'),
        prefixTable('lapr_policies'),
        prefixTable('lapr_audit_log'),
        prefixTable('items'),
        prefixTable('background_tasks'),
        prefixTable('users'),
    ];
    foreach ($requiredTables as $tableName) {
        if (teampassTableExists($tableName) === false) {
            return laprMonitoringEmptySnapshot($enabled, 'schema_missing');
        }
    }
    if (teampassTableColumnExists(prefixTable('users'), 'can_manage_lapr') === false) {
        return laprMonitoringEmptySnapshot($enabled, 'schema_missing');
    }

    $graceSeconds = laprMonitoringGraceSeconds($settings);
    $operatorSql =
        'SELECT
            SUM(CASE WHEN disabled = 0 THEN 1 ELSE 0 END) AS granted_active,
            SUM(CASE WHEN disabled = 1 THEN 1 ELSE 0 END) AS disabled_grants,
            COUNT(*) AS granted_total
         FROM ' . prefixTable('users') . '
         WHERE admin = 0 AND deleted_at IS NULL AND can_manage_lapr = 1';
    $systemAccountIds = teampassGetSystemAccountIds();
    $operatorRow = empty($systemAccountIds) === true
        ? DB::queryFirstRow($operatorSql)
        : DB::queryFirstRow($operatorSql . ' AND id NOT IN %li', $systemAccountIds);
    $operators = laprMonitoringAccessSummary($enabled, is_array($operatorRow) ? $operatorRow : []);

    $endpointRows = DB::query(
        'SELECT endpoint.id, endpoint.label, endpoint.hostname, endpoint.port,
                endpoint.ssh_credential_source, endpoint.capabilities,
                endpoint.ssh_hostkey_verified, endpoint.status,
                credential_item.id AS credential_item_id,
                credential_item.inactif AS credential_item_inactive,
                credential_item.perso AS credential_item_personal,
                credential_item.deleted_at AS credential_item_deleted_at
         FROM ' . prefixTable('lapr_endpoints') . ' AS endpoint
         LEFT JOIN ' . prefixTable('items') . ' AS credential_item
            ON credential_item.id = endpoint.ssh_credential_source
         WHERE endpoint.status != %s
         ORDER BY endpoint.label ASC',
        'deleted'
    );

    $accountRows = DB::query(
        'SELECT a.id, a.endpoint_id, a.item_id, a.username_cache, a.policy_id,
                a.last_rotation_at, a.last_rotation_status, a.last_rotation_error,
                a.next_rotation_at, a.retry_count, a.retry_at, a.status,
                e.id AS endpoint_id_resolved, e.label AS endpoint_label,
                e.hostname, e.status AS endpoint_status,
                i.id AS managed_item_id, i.login AS managed_item_login,
                i.inactif AS managed_item_inactive, i.perso AS managed_item_personal,
                i.deleted_at AS managed_item_deleted_at,
                p.id AS policy_id_resolved
         FROM ' . prefixTable('lapr_accounts') . ' AS a
         LEFT JOIN ' . prefixTable('lapr_endpoints') . ' AS e ON e.id = a.endpoint_id
         LEFT JOIN ' . prefixTable('items') . ' AS i ON i.id = a.item_id
         LEFT JOIN ' . prefixTable('lapr_policies') . ' AS p ON p.id = a.policy_id
         WHERE a.status != %s
         ORDER BY e.label ASC, a.username_cache ASC',
        'deleted'
    );

    $endpointCounts = [
        'total' => count($endpointRows),
        'active' => 0,
        'problem' => 0,
        'unverified' => 0,
        'incapable' => 0,
        'duplicate_targets' => 0,
        'shared_credentials' => 0,
    ];
    $accountCounts = [
        'total' => count($accountRows),
        'healthy' => 0,
        'scheduled' => 0,
        'retrying' => 0,
        'overdue' => 0,
        'error' => 0,
        'paused' => 0,
        'compliant' => 0,
        'attention' => 0,
        'compliance_pct' => 0,
    ];
    $issues = [];
    $targetMap = [];
    $credentialMap = [];
    $invalidEndpointIds = [];

    foreach ($endpointRows as $endpoint) {
        $endpointId = (int) $endpoint['id'];
        $endpointLabel = (string) $endpoint['label'];
        $endpointStatus = (string) $endpoint['status'];
        if ($endpointStatus === 'active') {
            ++$endpointCounts['active'];
        } else {
            ++$endpointCounts['problem'];
            $invalidEndpointIds[$endpointId] = true;
            laprMonitoringAddIssue($issues, 'danger', 'endpoint_inactive', $endpointLabel, '', $endpointId, null);
        }

        if ((int) $endpoint['ssh_hostkey_verified'] !== 1) {
            ++$endpointCounts['unverified'];
            laprMonitoringAddIssue($issues, 'warning', 'hostkey_unverified', $endpointLabel, '', $endpointId, null);
        }

        $credentialSource = (int) ($endpoint['ssh_credential_source'] ?? 0);
        if ($credentialSource <= 0 || empty($endpoint['credential_item_id']) === true) {
            $invalidEndpointIds[$endpointId] = true;
            laprMonitoringAddIssue($issues, 'danger', 'credential_missing', $endpointLabel, '', $endpointId, null);
        } elseif (laprMonitoringItemUnavailable(
            $endpoint['credential_item_inactive'] ?? null,
            $endpoint['credential_item_personal'] ?? null,
            $endpoint['credential_item_deleted_at'] ?? null
        ) === true) {
            $invalidEndpointIds[$endpointId] = true;
            laprMonitoringAddIssue($issues, 'danger', 'credential_unavailable', $endpointLabel, '', $endpointId, null);
        }

        $capabilities = json_decode((string) ($endpoint['capabilities'] ?? ''), true);
        if (is_array($capabilities) === false || (bool) ($capabilities['can_rotate'] ?? false) === false) {
            ++$endpointCounts['incapable'];
            $invalidEndpointIds[$endpointId] = true;
            laprMonitoringAddIssue($issues, 'danger', 'capability_missing', $endpointLabel, '', $endpointId, null);
        }

        $targetKey = strtolower(trim((string) $endpoint['hostname'])) . ':' . (int) $endpoint['port'];
        $targetMap[$targetKey][] = ['id' => $endpointId, 'label' => $endpointLabel];
        $credentialId = (int) ($endpoint['ssh_credential_source'] ?? 0);
        if ($credentialId > 0 && $endpointStatus === 'active') {
            $credentialMap[$credentialId][] = ['id' => $endpointId, 'label' => $endpointLabel];
        }
    }

    foreach ($targetMap as $endpoints) {
        if (count($endpoints) < 2) {
            continue;
        }
        ++$endpointCounts['duplicate_targets'];
        laprMonitoringAddIssue(
            $issues,
            'warning',
            'duplicate_endpoint',
            implode(', ', array_column($endpoints, 'label')),
            '',
            (int) $endpoints[0]['id'],
            null
        );
    }
    foreach ($credentialMap as $endpoints) {
        if (count($endpoints) < 2) {
            continue;
        }
        ++$endpointCounts['shared_credentials'];
        laprMonitoringAddIssue(
            $issues,
            'warning',
            'shared_credential',
            implode(', ', array_column($endpoints, 'label')),
            '',
            (int) $endpoints[0]['id'],
            null
        );
    }

    foreach ($accountRows as &$account) {
        $endpointLabel = (string) ($account['endpoint_label'] ?? ('#' . (int) $account['endpoint_id']));
        $username = (string) $account['username_cache'];
        $accountId = (int) $account['id'];
        $endpointId = (int) $account['endpoint_id'];
        $accountIntegrityError = isset($invalidEndpointIds[$endpointId]);

        if (empty($account['endpoint_id_resolved']) === true) {
            $accountIntegrityError = true;
            laprMonitoringAddIssue($issues, 'danger', 'endpoint_missing', $endpointLabel, $username, $endpointId, $accountId);
        }
        if (empty($account['managed_item_id']) === true) {
            $accountIntegrityError = true;
            laprMonitoringAddIssue($issues, 'danger', 'managed_item_missing', $endpointLabel, $username, $endpointId, $accountId);
        } elseif (laprMonitoringItemUnavailable(
            $account['managed_item_inactive'] ?? null,
            $account['managed_item_personal'] ?? null,
            $account['managed_item_deleted_at'] ?? null
        ) === true) {
            $accountIntegrityError = true;
            laprMonitoringAddIssue($issues, 'danger', 'managed_item_unavailable', $endpointLabel, $username, $endpointId, $accountId);
        }

        if ((int) ($account['policy_id'] ?? 0) > 0 && empty($account['policy_id_resolved']) === true) {
            $accountIntegrityError = true;
            laprMonitoringAddIssue($issues, 'danger', 'policy_missing', $endpointLabel, $username, $endpointId, $accountId);
        }
        if (empty($account['managed_item_id']) === false
            && trim((string) ($account['managed_item_login'] ?? '')) !== trim($username)
        ) {
            $accountIntegrityError = true;
            laprMonitoringAddIssue($issues, 'danger', 'username_mismatch', $endpointLabel, $username, $endpointId, $accountId);
        }

        $account['monitoring_integrity_error'] = $accountIntegrityError;
        $state = laprMonitoringClassifyAccount($account, $nowTs, $graceSeconds);
        $account['monitoring_state'] = $state;
        ++$accountCounts[$state];

        if (in_array($state, ['overdue', 'error'], true)) {
            laprMonitoringAddIssue($issues, 'danger', 'account_' . $state, $endpointLabel, $username, $endpointId, $accountId);
        } elseif (in_array($state, ['retrying', 'paused'], true)) {
            laprMonitoringAddIssue($issues, 'warning', 'account_' . $state, $endpointLabel, $username, $endpointId, $accountId);
        }
    }
    unset($account);

    $accountCounts['compliant'] = $accountCounts['healthy'] + $accountCounts['scheduled'];
    $accountCounts['attention'] = $accountCounts['retrying'] + $accountCounts['overdue']
        + $accountCounts['error'] + $accountCounts['paused'];
    $accountCounts['compliance_pct'] = $accountCounts['total'] > 0
        ? (int) round(($accountCounts['compliant'] / $accountCounts['total']) * 100)
        : 0;

    $scheduler = laprMonitoringScheduler($settings, $nowTs, $graceSeconds);
    if ((int) $scheduler['failed_24h'] > 0) {
        laprMonitoringAddIssue($issues, 'danger', 'worker_failed', '', '', null, null);
    }
    if ((string) $scheduler['status'] === 'danger' && (string) $scheduler['reason'] !== 'worker_failed') {
        laprMonitoringAddIssue($issues, 'danger', 'scheduler_unhealthy', '', '', null, null);
    }

    $integrityCounts = ['critical' => 0, 'warning' => 0];
    foreach ($issues as $issue) {
        if ($issue['severity'] === 'danger') {
            ++$integrityCounts['critical'];
        } elseif ($issue['severity'] === 'warning') {
            ++$integrityCounts['warning'];
        }
    }

    usort($issues, static function (array $left, array $right): int {
        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2];
        $severityOrder = ($rank[$left['severity']] ?? 3) <=> ($rank[$right['severity']] ?? 3);
        if ($severityOrder !== 0) {
            return $severityOrder;
        }

        return strcasecmp((string) $left['endpoint_label'], (string) $right['endpoint_label']);
    });

    $overallStatus = 'success';
    $overallReason = 'healthy';
    if ($enabled === false) {
        $overallStatus = 'info';
        $overallReason = 'module_disabled';
    } elseif ($integrityCounts['critical'] > 0) {
        $overallStatus = 'danger';
        $overallReason = 'action_required';
    } elseif ($integrityCounts['warning'] > 0) {
        $overallStatus = 'warning';
        $overallReason = 'warnings';
    } elseif ($accountCounts['total'] === 0) {
        $overallStatus = 'info';
        $overallReason = 'no_accounts';
    }

    $recentFailures = DB::query(
        'SELECT l.created_at, l.error_message, l.action_details,
                a.username_cache, e.label AS endpoint_label
         FROM ' . prefixTable('lapr_audit_log') . ' AS l
         LEFT JOIN ' . prefixTable('lapr_accounts') . ' AS a ON a.id = l.account_id
         LEFT JOIN ' . prefixTable('lapr_endpoints') . ' AS e ON e.id = l.endpoint_id
         WHERE l.action_type = %s AND l.result = %s
         ORDER BY l.created_at DESC
         LIMIT 20',
        'rotation',
        'failure'
    );
    $recentFailurePayload = [];
    foreach ($recentFailures as $failure) {
        $details = json_decode((string) ($failure['action_details'] ?? ''), true);
        $errorCode = (string) ($failure['error_message'] ?? 'ERR_UNKNOWN');
        $recentFailurePayload[] = [
            'created_at' => (string) $failure['created_at'],
            'endpoint_label' => (string) ($failure['endpoint_label'] ?? ''),
            'username' => (string) ($failure['username_cache'] ?? ''),
            'error_code' => $errorCode,
            'category' => laprMonitoringFailureCategory($errorCode),
            'trigger' => is_array($details) ? (string) ($details['trigger'] ?? '') : '',
            'manual_resync_required' => is_array($details) && (bool) ($details['manual_resync_required'] ?? false),
        ];
    }

    return [
        'available' => true,
        'enabled' => $enabled,
        'error' => false,
        'overall' => [
            'status' => $overallStatus,
            'reason' => $overallReason,
            'attention' => $integrityCounts['critical'],
        ],
        'endpoints' => $endpointCounts,
        'accounts' => $accountCounts,
        'operators' => $operators,
        'scheduler' => $scheduler,
        'integrity' => $integrityCounts,
        'action_items' => array_slice($issues, 0, 50),
        'recent_failures' => $recentFailurePayload,
        'grace_seconds' => $graceSeconds,
    ];
}

/**
 * Build the period-based LAPR statistics payload.
 *
 * @return array<string,mixed>
 */
function laprBuildOperationalStatistics(
    array $settings,
    Language $lang,
    int $fromTs,
    int $toTs,
    string $granularity
): array {
    $snapshot = laprBuildMonitoringSnapshot($settings, $toTs);
    if ((bool) ($snapshot['available'] ?? false) === false) {
        return $snapshot;
    }

    $fromDate = date('Y-m-d H:i:s', $fromTs);
    $toDate = date('Y-m-d H:i:s', $toTs);
    $totals = DB::queryFirstRow(
        'SELECT
            SUM(CASE WHEN result = %s THEN 1 ELSE 0 END) AS successes,
            SUM(CASE WHEN result = %s THEN 1 ELSE 0 END) AS failures,
            COUNT(*) AS total
         FROM ' . prefixTable('lapr_audit_log') . '
         WHERE action_type = %s AND created_at BETWEEN %s AND %s',
        'success',
        'failure',
        'rotation',
        $fromDate,
        $toDate
    );
    $successes = (int) ($totals['successes'] ?? 0);
    $failures = (int) ($totals['failures'] ?? 0);
    $total = (int) ($totals['total'] ?? 0);

    $groupExpression = $granularity === 'hour'
        ? "CONCAT(DATE(created_at), ' ', LPAD(HOUR(created_at), 2, '0'), ':00')"
        : 'DATE(created_at)';
    $seriesRows = DB::query(
        'SELECT ' . $groupExpression . ' AS period_bucket,
                SUM(CASE WHEN result = %s THEN 1 ELSE 0 END) AS successes,
                SUM(CASE WHEN result = %s THEN 1 ELSE 0 END) AS failures
         FROM ' . prefixTable('lapr_audit_log') . '
         WHERE action_type = %s AND created_at BETWEEN %s AND %s
         GROUP BY period_bucket
         ORDER BY period_bucket ASC',
        'success',
        'failure',
        'rotation',
        $fromDate,
        $toDate
    );
    $series = ['labels' => [], 'successes' => [], 'failures' => []];
    foreach ($seriesRows as $row) {
        $series['labels'][] = (string) $row['period_bucket'];
        $series['successes'][] = (int) $row['successes'];
        $series['failures'][] = (int) $row['failures'];
    }

    $failureRows = DB::query(
        'SELECT COALESCE(NULLIF(error_message, %s), %s) AS error_code, COUNT(*) AS total
         FROM ' . prefixTable('lapr_audit_log') . '
         WHERE action_type = %s AND result = %s AND created_at BETWEEN %s AND %s
         GROUP BY error_code
         ORDER BY total DESC',
        '',
        'ERR_UNKNOWN',
        'rotation',
        'failure',
        $fromDate,
        $toDate
    );
    $failureCategories = [];
    foreach ($failureRows as $row) {
        $category = laprMonitoringFailureCategory((string) $row['error_code']);
        $failureCategories[$category] = ($failureCategories[$category] ?? 0) + (int) $row['total'];
    }
    arsort($failureCategories);
    $failurePayload = [];
    foreach ($failureCategories as $category => $count) {
        $failurePayload[] = ['category' => $category, 'count' => $count];
    }

    $endpointRows = DB::query(
        'SELECT e.id, e.label,
                SUM(CASE WHEN l.result = %s THEN 1 ELSE 0 END) AS successes,
                SUM(CASE WHEN l.result = %s THEN 1 ELSE 0 END) AS failures,
                COUNT(*) AS total,
                MAX(CASE WHEN l.result = %s THEN l.created_at ELSE NULL END) AS last_failure_at
         FROM ' . prefixTable('lapr_audit_log') . ' AS l
         LEFT JOIN ' . prefixTable('lapr_endpoints') . ' AS e ON e.id = l.endpoint_id
         WHERE l.action_type = %s AND l.created_at BETWEEN %s AND %s
         GROUP BY e.id, e.label
         HAVING failures > 0
         ORDER BY failures DESC, total DESC
         LIMIT 10',
        'success',
        'failure',
        'failure',
        'rotation',
        $fromDate,
        $toDate
    );
    $topEndpoints = [];
    foreach ($endpointRows as $row) {
        $endpointTotal = (int) $row['total'];
        $endpointSuccesses = (int) $row['successes'];
        $topEndpoints[] = [
            'id' => (int) ($row['id'] ?? 0),
            'label' => (string) ($row['label'] ?? ''),
            'successes' => $endpointSuccesses,
            'failures' => (int) $row['failures'],
            'success_rate' => $endpointTotal > 0 ? (int) round(($endpointSuccesses / $endpointTotal) * 100) : null,
            'last_failure_at' => $row['last_failure_at'],
        ];
    }

    $policyRows = DB::query(
        'SELECT a.policy_id, p.label, p.is_preset, COUNT(*) AS total
         FROM ' . prefixTable('lapr_accounts') . ' AS a
         LEFT JOIN ' . prefixTable('lapr_policies') . ' AS p ON p.id = a.policy_id
         WHERE a.status != %s
         GROUP BY a.policy_id, p.label, p.is_preset
         ORDER BY total DESC',
        'deleted'
    );
    $policies = [];
    foreach ($policyRows as $row) {
        $policyId = (int) ($row['policy_id'] ?? 0);
        $policyLabel = $policyId > 0 && $row['label'] !== null
            ? laprPolicyDisplayName((string) $row['label'], (int) $row['is_preset'] === 1, $lang)
            : $lang->get('lapr_no_policy');
        $policies[] = ['label' => $policyLabel, 'count' => (int) $row['total']];
    }

    $workerFailures = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
         WHERE process_type = %s AND status = %s
         AND CAST(finished_at AS UNSIGNED) BETWEEN %i AND %i',
        'lapr_rotation',
        'failed',
        $fromTs,
        $toTs
    );
    $retentionDays = max(0, (int) ($settings['lapr_audit_retention_days'] ?? 365));
    $requestedSeconds = max(0, $toTs - $fromTs);

    return array_merge($snapshot, [
        'period' => [
            'from' => $fromTs,
            'to' => $toTs,
            'retention_days' => $retentionDays,
            'retention_limited' => $retentionDays > 0 && $requestedSeconds > ($retentionDays * 86400),
        ],
        'rotations' => [
            'total' => $total,
            'successes' => $successes,
            'failures' => $failures,
            'success_rate' => $total > 0 ? (int) round(($successes / $total) * 100) : null,
            'worker_failures' => $workerFailures,
            'series' => $series,
        ],
        'failure_categories' => $failurePayload,
        'top_endpoints' => $topEndpoints,
        'policies' => $policies,
    ]);
}

/**
 * Add the global TeamPass background-handler result to the LAPR Health view.
 * Statistics intentionally remains based on LAPR-specific data only.
 *
 * @param array<string,mixed> $snapshot
 *
 * @return array<string,mixed>
 */
function laprMonitoringApplyCronStatus(array $snapshot, string $cronStatus): array
{
    if ((bool) ($snapshot['available'] ?? false) === false
        || (bool) ($snapshot['enabled'] ?? false) === false
        || (bool) ($snapshot['scheduler']['enabled'] ?? false) === false
        || $cronStatus === 'success'
    ) {
        return $snapshot;
    }

    $severity = $cronStatus === 'danger' ? 'danger' : 'warning';
    $snapshot['scheduler']['status'] = $severity;
    $snapshot['scheduler']['reason'] = 'cron_unhealthy';
    $issue = [
        'severity' => $severity,
        'code' => 'cron_unhealthy',
        'endpoint_label' => '',
        'username' => '',
        'endpoint_id' => null,
        'account_id' => null,
    ];
    array_unshift($snapshot['action_items'], $issue);
    $snapshot['action_items'] = array_slice($snapshot['action_items'], 0, 50);
    $counter = $severity === 'danger' ? 'critical' : 'warning';
    $snapshot['integrity'][$counter] = (int) ($snapshot['integrity'][$counter] ?? 0) + 1;

    $currentOverallStatus = (string) ($snapshot['overall']['status'] ?? 'info');
    if ($severity === 'danger' || $currentOverallStatus !== 'danger') {
        $snapshot['overall']['status'] = $severity;
        $snapshot['overall']['reason'] = $severity === 'danger' ? 'action_required' : 'warnings';
    }
    if ($severity === 'danger') {
        $snapshot['overall']['attention'] = (int) ($snapshot['overall']['attention'] ?? 0) + 1;
    }

    return $snapshot;
}

/**
 * @return array<string,mixed>
 */
function laprMonitoringScheduler(array $settings, int $nowTs, int $graceSeconds): array
{
    $moduleEnabled = (int) ($settings['lapr_enabled'] ?? 0) === 1;
    $enabled = (int) ($settings['lapr_scheduler_enabled'] ?? 0) === 1;
    $intervalMinutes = max(1, (int) ($settings['lapr_scheduler_interval_minutes'] ?? 5));
    $nextRunAt = max(0, (int) ($settings['lapr_scheduler_next_run_at'] ?? 0));

    $pending = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
         WHERE process_type = %s AND is_in_progress = 0
         AND (finished_at IS NULL OR finished_at = %s OR finished_at = 0)',
        'lapr_rotation',
        ''
    );
    $running = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
         WHERE process_type = %s AND is_in_progress = 1
         AND (finished_at IS NULL OR finished_at = %s OR finished_at = 0)',
        'lapr_rotation',
        ''
    );
    $failed24h = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
         WHERE process_type = %s AND status = %s
         AND CAST(finished_at AS UNSIGNED) >= %i',
        'lapr_rotation',
        'failed',
        $nowTs - 86400
    );
    $oldestPending = (int) DB::queryFirstField(
        'SELECT COALESCE(MIN(CAST(created_at AS UNSIGNED)), 0)
         FROM ' . prefixTable('background_tasks') . '
         WHERE process_type = %s AND is_in_progress = 0
         AND (finished_at IS NULL OR finished_at = %s OR finished_at = 0)',
        'lapr_rotation',
        ''
    );

    $status = 'success';
    $reason = 'healthy';
    if ($moduleEnabled === false || $enabled === false) {
        $status = 'info';
        $reason = $moduleEnabled === false ? 'module_disabled' : 'scheduler_disabled';
    } elseif ($nextRunAt > 0 && $nextRunAt < ($nowTs - $graceSeconds)) {
        $status = 'danger';
        $reason = 'scheduler_overdue';
    } elseif ($nextRunAt === 0) {
        $status = 'warning';
        $reason = 'scheduler_initializing';
    }
    if ($oldestPending > 0 && $oldestPending < ($nowTs - $graceSeconds)) {
        $status = 'danger';
        $reason = 'queue_stalled';
    }
    if ($failed24h > 0) {
        $status = 'danger';
        $reason = 'worker_failed';
    }

    return [
        'enabled' => $enabled,
        'status' => $status,
        'reason' => $reason,
        'interval_minutes' => $intervalMinutes,
        'next_run_at' => $nextRunAt,
        'pending' => $pending,
        'running' => $running,
        'failed_24h' => $failed24h,
        'oldest_pending_at' => $oldestPending,
    ];
}

/**
 * @param mixed $value
 */
function laprMonitoringTimestamp($value): ?int
{
    if ($value === null || $value === '' || $value === '0') {
        return null;
    }
    if (is_numeric($value) === true) {
        $timestamp = (int) $value;
        return $timestamp > 0 ? $timestamp : null;
    }

    $timestamp = strtotime((string) $value);

    return $timestamp === false ? null : $timestamp;
}

/**
 * @param mixed $inactive
 * @param mixed $personal
 * @param mixed $deletedAt
 */
function laprMonitoringItemUnavailable($inactive, $personal, $deletedAt): bool
{
    $deleted = $deletedAt !== null && $deletedAt !== '' && $deletedAt !== '0';

    return (int) $inactive === 1 || (int) $personal === 1 || $deleted;
}

/**
 * @param array<int,array<string,mixed>> $issues
 */
function laprMonitoringAddIssue(
    array &$issues,
    string $severity,
    string $code,
    string $endpointLabel,
    string $username,
    ?int $endpointId,
    ?int $accountId
): void {
    $issues[] = [
        'severity' => $severity,
        'code' => $code,
        'endpoint_label' => $endpointLabel,
        'username' => $username,
        'endpoint_id' => $endpointId,
        'account_id' => $accountId,
    ];
}

/**
 * @return array<string,mixed>
 */
function laprMonitoringEmptySnapshot(bool $enabled, string $reason): array
{
    return [
        'available' => false,
        'enabled' => $enabled,
        'error' => false,
        'overall' => [
            'status' => $enabled ? 'danger' : 'info',
            'reason' => $reason,
            'attention' => $enabled ? 1 : 0,
        ],
        'endpoints' => [
            'total' => 0,
            'active' => 0,
            'problem' => 0,
            'unverified' => 0,
            'incapable' => 0,
            'duplicate_targets' => 0,
            'shared_credentials' => 0,
        ],
        'accounts' => [
            'total' => 0,
            'healthy' => 0,
            'scheduled' => 0,
            'retrying' => 0,
            'overdue' => 0,
            'error' => 0,
            'paused' => 0,
            'compliant' => 0,
            'attention' => 0,
            'compliance_pct' => 0,
        ],
        'operators' => [
            'active' => 0,
            'granted_active' => 0,
            'disabled_grants' => 0,
            'granted_total' => 0,
        ],
        'scheduler' => [
            'enabled' => false,
            'status' => 'info',
            'reason' => $reason,
            'interval_minutes' => 0,
            'next_run_at' => 0,
            'pending' => 0,
            'running' => 0,
            'failed_24h' => 0,
            'oldest_pending_at' => 0,
        ],
        'integrity' => ['critical' => 0, 'warning' => 0],
        'action_items' => [],
        'recent_failures' => [],
        'grace_seconds' => 0,
    ];
}
