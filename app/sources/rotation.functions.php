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
 * @file      rotation.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Rotation Policy & Tracking (F5 — Scale & polish).
 *
 * Pure, DB-free rotation-SLA logic (unit-tested by
 * tests/Unit/RotationTrackingLogicTest.php). The SLA itself is the existing
 * per-folder `nested_tree.renewal_period` (days); these functions turn raw
 * item/folder rows into the "overdue rotations" and "SLA coverage" report
 * rows without touching any secret value.
 */

/**
 * Classify an item against its folder rotation SLA.
 *
 * @param int $slaDays      Folder SLA in days (nested_tree.renewal_period; <= 0 = no SLA)
 * @param int $lastChangeTs Unix timestamp of the last password change (0 = unknown)
 * @param int $nowTs        Current timestamp
 * @param int $dueSoonDays  Look-ahead window flagging upcoming rotations
 *
 * @return array{status: string, days_overdue: int, days_left: int}
 *         status: 'no_sla' | 'unknown' | 'ok' | 'due_soon' | 'overdue'
 */
function rotationSlaStatus(int $slaDays, int $lastChangeTs, int $nowTs, int $dueSoonDays = 14): array
{
    if ($slaDays <= 0) {
        return ['status' => 'no_sla', 'days_overdue' => 0, 'days_left' => 0];
    }
    if ($lastChangeTs <= 0) {
        // No reliable change date — never claim a due date computed from epoch 0.
        return ['status' => 'unknown', 'days_overdue' => 0, 'days_left' => 0];
    }

    $dueTs = $lastChangeTs + $slaDays * 86400;
    if ($dueTs <= $nowTs) {
        return [
            'status' => 'overdue',
            'days_overdue' => (int) floor(($nowTs - $dueTs) / 86400),
            'days_left' => 0,
        ];
    }

    $daysLeft = (int) ceil(($dueTs - $nowTs) / 86400);

    return [
        'status' => $daysLeft <= $dueSoonDays ? 'due_soon' : 'ok',
        'days_overdue' => 0,
        'days_left' => $daysLeft,
    ];
}

/**
 * Build the "overdue rotations" report rows from raw item records.
 *
 * Keeps only items that are overdue or due within the look-ahead window,
 * sorted most-overdue first (then soonest-due). Items without a usable
 * change date are excluded — they cannot be honestly scheduled.
 *
 * @param array<int, array<string, mixed>> $records     Rows {item_id, label, folder_id, folder_title, sla_days, last_change}
 * @param int                              $nowTs       Current timestamp
 * @param int                              $dueSoonDays Look-ahead window in days
 *
 * @return array<int, array<string, int|string>> Rows {item_id, label, folder_id, folder, sla_days, last_change, due_at, days_overdue, days_left, status}
 */
function rotationBuildOverdueRows(array $records, int $nowTs, int $dueSoonDays = 14): array
{
    $rows = [];
    foreach ($records as $record) {
        $slaDays = (int) ($record['sla_days'] ?? 0);
        $lastChange = (int) ($record['last_change'] ?? 0);
        $sla = rotationSlaStatus($slaDays, $lastChange, $nowTs, $dueSoonDays);
        if ($sla['status'] !== 'overdue' && $sla['status'] !== 'due_soon') {
            continue;
        }

        $rows[] = [
            'item_id' => (int) ($record['item_id'] ?? 0),
            'label' => (string) ($record['label'] ?? ''),
            'folder_id' => (int) ($record['folder_id'] ?? 0),
            'folder' => (string) ($record['folder_title'] ?? ''),
            'sla_days' => $slaDays,
            'last_change' => date('Y-m-d', $lastChange),
            'due_at' => date('Y-m-d', $lastChange + $slaDays * 86400),
            'days_overdue' => $sla['days_overdue'],
            'days_left' => $sla['days_left'],
            'status' => $sla['status'],
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        // Overdue block first (most overdue on top), then due-soon (soonest first).
        if ($a['status'] !== $b['status']) {
            return $a['status'] === 'overdue' ? -1 : 1;
        }
        if ($a['status'] === 'overdue') {
            return $b['days_overdue'] <=> $a['days_overdue'];
        }

        return $a['days_left'] <=> $b['days_left'];
    });

    return $rows;
}

/**
 * Build the per-folder SLA coverage rows + the coverage summary.
 *
 * @param array<int, array<string, mixed>> $folderRecords Rows {folder_id, folder_title, sla_days, items, overdue}
 *
 * @return array{rows: array<int, array<string, int|string>>, summary: array{folders_total: int, folders_with_sla: int, coverage_percent: float}}
 */
function rotationSlaCoverage(array $folderRecords): array
{
    $rows = [];
    $foldersTotal = 0;
    $foldersWithSla = 0;

    foreach ($folderRecords as $record) {
        $slaDays = (int) ($record['sla_days'] ?? 0);
        $foldersTotal++;
        if ($slaDays > 0) {
            $foldersWithSla++;
        }

        $rows[] = [
            'folder_id' => (int) ($record['folder_id'] ?? 0),
            'folder' => (string) ($record['folder_title'] ?? ''),
            'sla_days' => $slaDays > 0 ? $slaDays : 0,
            'items' => max(0, (int) ($record['items'] ?? 0)),
            'overdue' => $slaDays > 0 ? max(0, (int) ($record['overdue'] ?? 0)) : 0,
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        // Folders bleeding the most first; then uncovered folders holding items;
        // then alphabetical for a stable, readable report.
        if ($a['overdue'] !== $b['overdue']) {
            return $b['overdue'] <=> $a['overdue'];
        }
        $aUncovered = ($a['sla_days'] === 0 && $a['items'] > 0) ? 1 : 0;
        $bUncovered = ($b['sla_days'] === 0 && $b['items'] > 0) ? 1 : 0;
        if ($aUncovered !== $bUncovered) {
            return $bUncovered <=> $aUncovered;
        }

        return strcasecmp($a['folder'], $b['folder']);
    });

    return [
        'rows' => $rows,
        'summary' => [
            'folders_total' => $foldersTotal,
            'folders_with_sla' => $foldersWithSla,
            'coverage_percent' => $foldersTotal > 0 ? round($foldersWithSla * 100 / $foldersTotal, 1) : 0.0,
        ],
    ];
}
