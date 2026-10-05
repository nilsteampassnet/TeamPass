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
 * @file      secure_send_statistics.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Aggregate observed Secure Send events for the administrator statistics dashboard.
 *
 * The caller must enforce the authenticated admin/session-key boundary. This helper
 * reads only audit metadata and sender account identity, never live links or items.
 * Personal/API provenance was not captured by the journal; only the period applies.
 * Counters are period events, not the subsequent lifecycle of a creation cohort.
 *
 * @param int $fromTs Inclusive period start resolved by opsStatsResolvePeriodRange()
 * @param int $toTs Inclusive observation cutoff
 * @param array $settings Application settings (current feature state only)
 * @param array<int,int|string> $systemUserIds Internal accounts excluded by original sender id
 * @return array<string,mixed> Stable metadata-only contract; null counters on query failure
 * @throws InvalidArgumentException When the requested range exceeds dashboard limits
 */
function secureSendBuildOperationalStatistics(int $fromTs, int $toTs, array $settings, array $systemUserIds): array
{
    if ($fromTs < 0 || $toTs < $fromTs || $toTs - $fromTs > 90 * 86400) {
        throw new InvalidArgumentException('Invalid Secure Send statistics period');
    }

    $payload = [
        'enabled' => (int) ($settings['otv_is_enabled'] ?? 0) === 1,
        'available' => false,
        'error' => true,
        'reason' => 'query_failed',
        'meta' => [
            'from' => $fromTs,
            'to' => $toTs,
            'filters_applied' => ['period'],
            'historical_backfill' => false,
            'recipient_identity_known' => false,
        ],
        'totals' => null,
        'creations' => null,
        'top_senders' => [],
    ];
    $excludedIds = array_values(array_unique(array_filter(array_map('intval', $systemUserIds),
        static fn (int $id): bool => $id > 0)));
    $where = 'a.occurred_at BETWEEN %i AND %i';
    $parameters = [$fromTs, $toTs];
    if ($excludedIds !== []) {
        $where .= ' AND a.originator NOT IN %li';
        $parameters[] = $excludedIds;
    }

    try {
        $summary = DB::queryFirstRow(
            "SELECT
                SUM(CASE WHEN a.event = 'created' THEN 1 ELSE 0 END) AS created,
                SUM(CASE WHEN a.event = 'revealed' THEN 1 ELSE 0 END) AS revealed,
                SUM(CASE WHEN a.event = 'reveal_failed' THEN 1 ELSE 0 END) AS reveal_failed,
                SUM(CASE WHEN a.event = 'revoked' THEN 1 ELSE 0 END) AS revoked,
                SUM(CASE WHEN a.event = 'invalidated' THEN 1 ELSE 0 END) AS invalidated,
                SUM(CASE WHEN a.event = 'expired' THEN 1 ELSE 0 END) AS expired,
                COUNT(DISTINCT CASE WHEN a.event = 'revealed' THEN a.send_id END) AS sends_revealed,
                COUNT(DISTINCT CASE WHEN a.event = 'created' THEN a.originator END) AS senders,
                SUM(CASE WHEN a.event = 'created' AND a.send_type = 'item' THEN 1 ELSE 0 END) AS items,
                SUM(CASE WHEN a.event = 'created' AND a.send_type = 'note' THEN 1 ELSE 0 END) AS notes,
                SUM(CASE WHEN a.event = 'created' AND a.send_type NOT IN ('item', 'note') THEN 1 ELSE 0 END) AS unknown,
                SUM(CASE WHEN a.event = 'created' AND a.has_passphrase = 1 THEN 1 ELSE 0 END) AS protected,
                SUM(CASE WHEN a.event = 'created' AND a.has_passphrase = 0 THEN 1 ELSE 0 END) AS unprotected,
                SUM(CASE WHEN a.event = 'created' AND a.is_public = 1 THEN 1 ELSE 0 END) AS public_links,
                SUM(CASE WHEN a.event = 'created' AND a.is_public = 0 THEN 1 ELSE 0 END) AS internal_links
            FROM " . prefixTable('secure_send_audit') . " a
            WHERE a.event IN ('created', 'revealed', 'reveal_failed', 'revoked', 'invalidated', 'expired')
                AND {$where}",
            ...$parameters
        );
        if (!is_array($summary) || !array_key_exists('created', $summary)) {
            throw new RuntimeException('Secure Send statistics aggregate missing');
        }

        // Rank the full sender population in SQL, not a pre-truncated activity sample.
        // Join identity only after aggregation: deleted/missing accounts retain their history.
        $rows = DB::query(
            "SELECT s.originator, s.created, s.revealed, s.reveal_failed, s.last_created,
                u.id AS account_id, u.login, u.name, u.lastname, u.disabled, u.deleted_at
            FROM (
                SELECT a.originator,
                    SUM(CASE WHEN a.event = 'created' THEN 1 ELSE 0 END) AS created,
                    SUM(CASE WHEN a.event = 'revealed' THEN 1 ELSE 0 END) AS revealed,
                    SUM(CASE WHEN a.event = 'reveal_failed' THEN 1 ELSE 0 END) AS reveal_failed,
                    MAX(CASE WHEN a.event = 'created' THEN a.occurred_at END) AS last_created
                FROM " . prefixTable('secure_send_audit') . " a
                WHERE a.event IN ('created', 'revealed', 'reveal_failed') AND {$where}
                GROUP BY a.originator
                HAVING created > 0
            ) s
            LEFT JOIN " . prefixTable('users') . " u ON u.id = s.originator
            ORDER BY s.created DESC, s.last_created DESC, s.originator ASC
            LIMIT 5",
            ...$parameters
        );

        $totals = [];
        foreach (['created', 'revealed', 'reveal_failed', 'revoked', 'invalidated', 'expired', 'sends_revealed', 'senders'] as $key) {
            $totals[$key] = (int) ($summary[$key] ?? 0);
        }
        $creations = [];
        foreach (['items', 'notes', 'unknown', 'protected', 'unprotected', 'public_links', 'internal_links'] as $key) {
            $creations[$key] = (int) ($summary[$key] ?? 0);
        }
        $senders = [];
        foreach ($rows as $row) {
            $deleted = !in_array($row['deleted_at'] ?? null, [null, '', 0, '0'], true);
            $senders[] = [
                'id' => (int) $row['originator'],
                'login' => (string) ($row['login'] ?? ''),
                'name' => trim((string) ($row['name'] ?? '') . ' ' . (string) ($row['lastname'] ?? '')),
                'account_state' => empty($row['account_id']) ? 'missing' : ($deleted ? 'deleted' : ((int) $row['disabled'] === 1 ? 'disabled' : 'active')),
                'created' => (int) $row['created'],
                'revealed' => (int) $row['revealed'],
                'reveal_failed' => (int) $row['reveal_failed'],
                'last_created' => (int) $row['last_created'],
            ];
        }

        $payload['available'] = true;
        $payload['error'] = false;
        $payload['reason'] = 'ok';
        $payload['totals'] = $totals;
        $payload['creations'] = $creations;
        $payload['top_senders'] = $senders;
    } catch (Throwable $e) {
        // Do not leak SQL, account identity, connection details or exception messages.
        error_log('TEAMPASS Secure Send statistics failed (' . get_class($e) . ')');
    }

    return $payload;
}
