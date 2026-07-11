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
 * @file      reports.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Compliance Reports & Evidence Export (F6 — Enterprise governance).
 *
 * Pure, DB-free report-shaping logic (unit-tested by
 * tests/Unit/ComplianceReportsLogicTest.php). The DB queries live in
 * reports.queries.php; these functions turn raw rows into auditor-ready
 * report rows without touching any secret value.
 */

/**
 * Parse a semicolon-separated role id list (users.fonction_id format).
 *
 * @param string|null $fonctionId Raw column value (e.g. "3;7;12")
 *
 * @return int[] Unique positive role ids
 */
function reportsParseRoleIds(?string $fonctionId): array
{
    if ($fonctionId === null || trim($fonctionId) === '') {
        return [];
    }

    $ids = [];
    foreach (explode(';', $fonctionId) as $part) {
        $id = (int) trim($part);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Build the access-matrix rows: one row per (user, role, folder) grant.
 *
 * This is the raw grant list an auditor asks for — no effective-permission
 * merge is applied, every contributing grant is listed with its type.
 *
 * @param array<int, array<string, mixed>> $users       Rows {id, login, name, lastname, fonction_id}
 * @param array<int, array<string, mixed>> $roleFolders Rows {role_id, role_title, folder_id, folder_title, type}
 *
 * @return array<int, array<string, string|int>> Rows {login, name, role, folder_id, folder, access}
 */
function reportsBuildAccessMatrix(array $users, array $roleFolders): array
{
    // Index grants by role for O(users × their roles) composition
    $grantsByRole = [];
    foreach ($roleFolders as $grant) {
        $grantsByRole[(int) $grant['role_id']][] = $grant;
    }

    $matrix = [];
    foreach ($users as $user) {
        foreach (reportsParseRoleIds($user['fonction_id'] ?? null) as $roleId) {
            foreach ($grantsByRole[$roleId] ?? [] as $grant) {
                $matrix[] = [
                    'login' => (string) $user['login'],
                    'name' => trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['lastname'] ?? '')),
                    'role' => (string) $grant['role_title'],
                    'folder_id' => (int) $grant['folder_id'],
                    'folder' => (string) $grant['folder_title'],
                    'access' => (string) $grant['type'],
                ];
            }
        }
    }

    return $matrix;
}

/**
 * Normalize a report period: sane defaults, bounded, start <= end.
 *
 * @param string|null $start Unix timestamp (as string) or null
 * @param string|null $end   Unix timestamp (as string) or null
 * @param int         $now   Current timestamp
 *
 * @return array{start:int, end:int} Normalized bounds
 */
function reportsPeriodBounds(?string $start, ?string $end, int $now): array
{
    $endTs = ($end !== null && (int) $end > 0) ? (int) $end : $now;
    // Default window: last 30 days
    $startTs = ($start !== null && (int) $start > 0) ? (int) $start : $endTs - (30 * 86400);

    if ($startTs > $endTs) {
        [$startTs, $endTs] = [$endTs, $startTs];
    }

    return ['start' => $startTs, 'end' => $endTs];
}

/**
 * Shape the vault posture summary, distinguishing live flags from scan flags.
 *
 * Metadata only — the input is counters, never a password or an item name,
 * preserving zero-knowledge between users in the admin view.
 *
 * The report separates two families of flags:
 *  - **live** flags (weak, breached, overshared, overdue, no_expiry) are
 *    recomputed from item metadata at report time — always current, no scan
 *    needed. Their percentage base is the whole live population.
 *  - **scan** flags (reused, orphaned) can only come from the deep health
 *    scan (they need a decryption context), so they reflect the last scan.
 *    Their base is the scanned population and each row carries the scan date.
 *
 * @param array<string, int> $liveCounts   Metadata flags recomputed now
 * @param int                $totalItems   Live population (base for live %)
 * @param array<string, int> $scanCounts   Flags from the last deep scan
 * @param int                $scannedItems Scanned population (base for scan %)
 * @param int                $lastScanAt   Timestamp of the most recent scan (0 = never)
 *
 * @return array{total_items: int, scanned_items: int, last_scan_at: int, issues: array<int, array<string, int|string|float>>}
 */
function reportsPostureSummary(array $liveCounts, int $totalItems, array $scanCounts, int $scannedItems, int $lastScanAt): array
{
    $buildRows = static function (array $counts, int $base, string $source) use ($lastScanAt): array {
        $rows = [];
        foreach ($counts as $issue => $count) {
            $count = max(0, (int) $count);
            $rows[] = [
                'issue' => (string) $issue,
                'items' => $count,
                'percent' => $base > 0 ? round($count * 100 / $base, 1) : 0.0,
                'source' => $source,
                'as_of' => $source === 'scan' ? $lastScanAt : 0,
            ];
        }
        // Most impacted first within each family — that is what the auditor reads
        usort($rows, static fn (array $a, array $b): int => $b['items'] <=> $a['items']);

        return $rows;
    };

    // Live flags first (always current), then the scan-bound ones
    $issues = array_merge(
        $buildRows($liveCounts, $totalItems, 'live'),
        $buildRows($scanCounts, $scannedItems, 'scan')
    );

    return [
        'total_items' => $totalItems,
        'scanned_items' => $scannedItems,
        'last_scan_at' => $lastScanAt,
        'issues' => $issues,
    ];
}

/**
 * Build a CSV string from headers + rows (RFC 4180 quoting).
 *
 * Cells starting with =, +, - or @ are prefixed with a single quote to
 * neutralise spreadsheet formula injection in exported evidence files.
 *
 * @param string[]                         $headers Column headers
 * @param array<int, array<string, mixed>> $rows    Report rows
 * @param string[]                         $columns Keys to pick from each row, in order
 *
 * @return string CSV content (CRLF line endings, UTF-8)
 */
function reportsBuildCsv(array $headers, array $rows, array $columns): string
{
    $escape = static function ($value): string {
        $value = (string) $value;
        // Neutralise spreadsheet formula injection
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            $value = "'" . $value;
        }
        if (strpbrk($value, "\",\r\n") !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    };

    $lines = [implode(',', array_map($escape, $headers))];
    foreach ($rows as $row) {
        $cells = [];
        foreach ($columns as $column) {
            $cells[] = $escape($row[$column] ?? '');
        }
        $lines[] = implode(',', $cells);
    }

    return implode("\r\n", $lines) . "\r\n";
}
