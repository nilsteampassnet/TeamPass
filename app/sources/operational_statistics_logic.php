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
 * @file      operational_statistics_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Decision logic of the operational statistics dashboard, kept free of any database or session
 * access so it can be unit-tested on its own — same pattern as security_posture_logic.php.
 */

/**
 * Maximum number of aggregated user rows pulled from the database before ranking.
 *
 * The ranking query orders by activity_total, which is the sum of every ranked metric. A user
 * cannot therefore rank in the top five of one metric without holding at least that many actions
 * in activity_total, so this cap only ever discards users far below every top five.
 */
const OPS_STATS_RANKING_CANDIDATES = 500;

/**
 * Resolve the start timestamp and chart granularity of a reporting period.
 *
 * Calendar periods (current week, current month) are resolved in the timezone already applied by
 * the caller through date_default_timezone_set(); rolling periods are plain offsets.
 *
 * @param string $period Period identifier, as validated by the caller.
 * @param int    $nowTs  Reference timestamp (end of the range).
 *
 * @return array{from:int,granularity:string} Start timestamp and 'hour' or 'day' granularity.
 */
function opsStatsResolvePeriodRange(string $period, int $nowTs): array
{
    $now = (new DateTimeImmutable())->setTimestamp($nowTs);

    switch ($period) {
        case 'current_week':
            return [
                'from' => $now->modify('monday this week')->setTime(0, 0, 0)->getTimestamp(),
                'granularity' => 'day',
            ];
        case 'current_month':
            return [
                'from' => $now->modify('first day of this month')->setTime(0, 0, 0)->getTimestamp(),
                'granularity' => 'day',
            ];
        case '7d':
            return ['from' => $nowTs - (7 * 24 * 3600), 'granularity' => 'day'];
        case '30d':
            return ['from' => $nowTs - (30 * 24 * 3600), 'granularity' => 'day'];
        case '90d':
            return ['from' => $nowTs - (90 * 24 * 3600), 'granularity' => 'day'];
        default:
            return ['from' => $nowTs - (24 * 3600), 'granularity' => 'hour'];
    }
}

/**
 * Build the top ranking of users for a single activity metric.
 *
 * Users with no action for that metric are dropped, so a top five never pads itself with zeros.
 * Ties fall back to the most recent activity, then to the login, to keep the order stable across
 * two consecutive calls on unchanged data.
 *
 * @param array<int,array<string,mixed>> $rows   Aggregated user rows.
 * @param string                         $metric Column to rank on.
 * @param int                            $limit  Maximum number of users returned.
 *
 * @return array<int,array<string,mixed>> Ranked rows, at most $limit entries.
 */
function opsStatsBuildUserRanking(array $rows, string $metric, int $limit): array
{
    if ($limit <= 0) {
        return [];
    }

    $ranked = array_values(
        array_filter(
            $rows,
            static fn (array $row): bool => (int) ($row[$metric] ?? 0) > 0
        )
    );

    usort(
        $ranked,
        static function (array $left, array $right) use ($metric): int {
            $metricOrder = (int) ($right[$metric] ?? 0) <=> (int) ($left[$metric] ?? 0);
            if ($metricOrder !== 0) {
                return $metricOrder;
            }

            $activityOrder = (int) ($right['last_activity'] ?? 0)
                <=> (int) ($left['last_activity'] ?? 0);
            if ($activityOrder !== 0) {
                return $activityOrder;
            }

            return strcasecmp((string) ($left['login'] ?? ''), (string) ($right['login'] ?? ''));
        }
    );

    return array_slice($ranked, 0, $limit);
}

/**
 * Turn raw complexity rows into the label/count/value triplets consumed by the chart.
 *
 * NULL, empty and '-1' complexity levels all mean "never assessed" and are merged into a single
 * bucket, so an item cannot be counted twice depending on how its level was stored.
 *
 * @param array<int,array<string,mixed>> $rows   Rows holding a complexity_level and a count 'c'.
 * @param array<int,string>              $labels Human labels indexed by complexity level.
 *
 * @return array{labels:array<int,string>,counts:array<int,int>,values:array<int,int>}
 */
function opsStatsFormatComplexityDistribution(array $rows, array $labels): array
{
    // Levels are bucketed as integers: PHP normalises numeric array keys to int anyway, and an
    // unassessed level must compare equal whether it was stored as NULL, '' or '-1'.
    $bucketed = [];
    foreach ($rows as $row) {
        $rawValue = isset($row['complexity_level']) ? (string) $row['complexity_level'] : '';
        $value = $rawValue === '' ? -1 : (int) $rawValue;
        $bucketed[$value] = ($bucketed[$value] ?? 0) + (int) ($row['c'] ?? 0);
    }

    ksort($bucketed, SORT_NUMERIC);

    $formatted = ['labels' => [], 'counts' => [], 'values' => []];
    foreach ($bucketed as $value => $count) {
        $label = $labels[$value] ?? (string) $value;
        if ($value !== -1) {
            $label .= ' (' . $value . ')';
        }
        $formatted['labels'][] = $label;
        $formatted['counts'][] = (int) $count;
        $formatted['values'][] = $value;
    }

    return $formatted;
}
