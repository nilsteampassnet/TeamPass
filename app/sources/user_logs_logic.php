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

/** Return the event key without the legacy email detail appended by user management. */
function userLogsUserManagementAction(string $label): string
{
    return str_starts_with($label, 'at_user_email_changed:') ? 'at_user_email_changed' : $label;
}

/**
 * Read the target id only for user-management events with a documented account payload.
 *
 * @param array<string, mixed> $row Projected journal row.
 */
function userLogsAdminTargetId(array $row): ?int
{
    if ((int) $row['is_item'] === 1 || ($row['type'] ?? '') !== 'user_mngt') {
        return null;
    }

    $detail = $row['log_detail'] ?? null;
    // Some numeric details are token, session or review ids. Extension token revocation
    // even stores different kinds of ids under the same event key.
    $target = match (userLogsUserManagementAction((string) $row['label'])) {
        'at_user_added', 'at_user_deleted', 'at_user_email_changed', 'at_user_locked', 'at_user_unlocked',
        'at_user_pwd_changed', 'at_user_forgot_local_password_requested', 'at_user_new_keys', 'at_restored',
        'at_user_oauth2_identity_bound', 'at_user_oauth2_identity_reset', 'at_leaver_items_flagged',
        'at_user_webauthn_added', 'at_user_webauthn_deleted', 'at_user_webauthn_passwordless_enabled',
        'at_user_webauthn_passwordless_disabled', 'user_purged' => $detail,
        'at_device_credential_revoked' => is_string($detail) ? (json_decode($detail, true)['user_id'] ?? null) : null,
        default => null,
    };
    if (!is_string($target) && !is_int($target)) {
        return null;
    }
    $id = filter_var($target, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

/**
 * Apply the caller's account scope once per distinct target on the returned page.
 *
 * @param array<array<string, mixed>> $rows Journal entries on the returned page.
 * @param callable(int): bool $mayManage Caller permission check.
 * @return list<int> The only ids whose logins may be loaded.
 */
function userLogsManageableAdminTargetIds(array $rows, callable $mayManage): array
{
    $ids = [];
    foreach ($rows as $row) {
        $id = userLogsAdminTargetId($row);
        if ($id !== null) {
            $ids[$id] = $id;
        }
    }

    return array_values(array_filter($ids, $mayManage));
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
    if ($type === 'user_mngt') {
        // The target belongs in its own column. Keep activity text independent of both the
        // caller's permissions and legacy details such as the previous email address.
        $activityKey = match (userLogsUserManagementAction($label)) {
            'at_user_locked' => 'user_logs_user_locked',
            'at_user_unlocked' => 'user_logs_user_unlocked',
            'at_user_email_changed' => 'user_logs_user_email_changed',
            'at_user_pwd_changed' => 'user_logs_user_password_changed',
            'at_user_initial_pwd_changed' => 'user_logs_user_initial_password_changed',
            'at_user_forgot_local_password_requested' => 'user_logs_user_password_recovery',
            default => null,
        };
        if ($activityKey !== null) {
            return userLogsDisplayText($lang->getShipped($activityKey));
        }
    }
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
 * Render the item title or an already authorised account login.
 *
 * @param array<string, mixed> $row Projected journal row.
 * @param array<int, string> $adminLogins Authorised account logins for the current page.
 */
function userLogsTargetText(array $row, array $adminLogins = []): string
{
    if ((int) $row['is_item'] === 1) {
        return userLogsDisplayText((string) $row['label']);
    }
    $id = $adminLogins === [] ? null : userLogsAdminTargetId($row);

    return $id === null ? '' : userLogsDisplayText($adminLogins[$id] ?? '');
}
