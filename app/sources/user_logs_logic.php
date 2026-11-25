<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use TeampassClasses\Language\Language;

require_once __DIR__ . '/logs_filter_logic.php';
require_once __DIR__ . '/log_display_logic.php';
require_once __DIR__ . '/stored_text_search.php';

/**
 * Resolve the selected calendar period in the TeamPass timezone.
 *
 * @param array<string, mixed> $query Request parameters.
 * @return array{?int, ?int}|null Start and exclusive end, or null for an invalid custom range.
 */
function userLogsDateRange(array $query, DateTimeImmutable $today): ?array
{
    $period = $query['period'] ?? 'all';
    if ($period === 'all') {
        return [null, null];
    }
    if ($period === 'custom') {
        $start = logsParseDateBoundary($query['date_from'] ?? null, false);
        $end = logsParseDateBoundary($query['date_to'] ?? null, true);

        return $start !== null && $end !== null && $start < $end ? [$start, $end] : null;
    }

    return [
        $today->modify($period === '7' ? '-6 days' : '-29 days')->getTimestamp(),
        $today->modify('+1 day')->getTimestamp(),
    ];
}

/** @return array<string, list<string>> Selectable groups and their recorded item actions. */
function userLogsItemActionGroups(): array
{
    return [
        'views' => ['at_shown'],
        'changes' => ['at_creation', 'at_modification', 'at_copy', 'at_delete', 'at_restored', 'at_moved', 'at_manual', 'at_import'],
        'passwords' => ['at_password_copied', 'at_password_shown', 'at_password_shown_edit_form', 'at_export'],
    ];
}

/**
 * Apply activity and channel filters to the two independently scoped journals.
 */
function userLogsApplyFilters(WhereClause $items, WhereClause $system, string $activity, string $channel): void
{
    $itemGroups = userLogsItemActionGroups();
    if (isset($itemGroups[$activity])) {
        $items->add('l.action IN %ls', $itemGroups[$activity]);
        $system->add('1 = 0');
    } elseif ($activity === 'connections' || $activity === 'admin') {
        $items->add('1 = 0');
        $system->add('l.type IN %ls', logsSystemTypeDbValues([$activity]));
    }

    logsAddChannelClause($items, 'items', $channel);
    logsAddChannelClause($system, 'system', $channel);
}

/**
 * Format the activity as plain text for matching and for the escaped DataTables response.
 *
 * @param array<string, mixed> $row Projected journal row.
 */
function userLogsActivityText(array $row, Language $lang): string
{
    $label = (string) $row['label'];
    if ((int) $row['is_item'] === 1) {
        $action = (string) $row['action'];
        return match ($action) {
            'at_shown' => $lang->getShipped('user_logs_item_viewed'),
            '' => $lang->getShipped('activity'),
            default => userLogsDisplayText($lang->getShipped($action)),
        };
    }

    $type = (string) $row['type'];
    if ($type === 'user_connection') {
        if ($label === 'connection' || $label === 'user_connection') {
            return $lang->getShipped('connection');
        }
        if ($label === 'disconnect') {
            return $lang->getShipped('disconnect');
        }
    }
    if (logsSystemTypeKey($type) === 'admin') {
        $label = formatAdminLogLabel($label, $lang);
    }
    if (str_starts_with($label, 'at_')) {
        $label = $lang->getShipped($label);
    }

    return userLogsDisplayText($label);
}

/** Decode legacy log text to the same plain text the interface presents. */
function userLogsDisplayText(string $text): string
{
    return storedTextSearchDecode(normalizeLogDisplayValue($text));
}

/**
 * Render the audit target using an item label only. System metadata is used for classification.
 *
 * @param array<string, mixed> $row Projected journal row.
 */
function userLogsTargetText(array $row): string
{
    return (int) $row['is_item'] === 1 ? userLogsDisplayText((string) $row['label']) : '';
}
