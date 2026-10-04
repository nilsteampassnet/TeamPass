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
 * Filtering and pagination rules for the administrator activity feed.
 *
 * @file      admin_activity_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/logs_filter_logic.php';

/** Validate the feed options; cursors order equal-second events deterministically. */
function adminActivityOptions(array $input, int $now, bool $kbEnabled): array
{
    $allowed = ['changes', 'accesses', 'failed', 'connections'];
    if ($kbEnabled) {
        $allowed[] = 'kb';
    }
    $categories = isset($input['categories']) && is_array($input['categories'])
        ? array_values(array_intersect($allowed, array_filter($input['categories'], 'is_string')))
        : array_values(array_diff($allowed, ['failed', 'connections']));
    $minutes = in_array($input['minutes'] ?? 5, [5, 15, 30], true) ? ($input['minutes'] ?? 5) : 5;
    $until = min($now, max($now - 1800, (int) ($input['until'] ?? $now)));
    $since = min($until, max($now - 1800, (int) ($input['since'] ?? ($until - $minutes * 60))));
    $options = [
        'categories' => $categories, 'minutes' => $minutes,
        'since' => $since, 'until' => $until,
        'limit' => ($input['expanded'] ?? false) === true ? 50 : 10,
    ];
    foreach (['before', 'after'] as $key) {
        $cursor = $input[$key] ?? null;
        if ($cursor !== null && (!is_array($cursor) || count($cursor) !== 3
            || !isset($cursor[0], $cursor[1], $cursor[2])
            || !is_int($cursor[0]) || !is_int($cursor[1]) || !is_int($cursor[2])
            || $cursor[0] < 0 || !in_array($cursor[1], [1, 2, 3], true) || $cursor[2] < 1)) {
            throw new InvalidArgumentException('Invalid activity cursor');
        }
        $options[$key] = $cursor;
    }
    return $options;
}

/** Build a bounded union with filters applied before pagination, using trusted table names. */
function adminActivityQuery(array $options, array $tables): array
{
    $queries = [];
    $values = [];
    $accesses = ['at_shown', 'at_access', 'at_password_shown', 'at_password_copied', 'at_password_shown_edit_form'];
    $categories = $options['categories'];
    if (in_array('changes', $categories, true) || in_array('accesses', $categories, true)) {
        $actionFilter = '';
        if (!in_array('changes', $categories, true) || !in_array('accesses', $categories, true)) {
            $actionFilter = ' AND l.action ' . (in_array('accesses', $categories, true) ? 'IN' : 'NOT IN') . ' %ls';
        }
        $queries[] = 'SELECT CAST(l.date AS SIGNED) AS timestamp, 1 AS source_rank, l.increment_id AS event_id,
            l.id_user AS user_id, COALESCE(u.login, \'\') AS user_login, l.action,
            l.raison AS detail, l.id_item AS item_id, i.label AS item_label, \'item\' AS source_type
            FROM ' . $tables['log_items'] . ' l
            LEFT JOIN ' . $tables['users'] . ' u ON l.id_user = u.id
            LEFT JOIN ' . $tables['items'] . ' i ON l.id_item = i.id
            WHERE CAST(l.date AS SIGNED) > %i AND CAST(l.date AS SIGNED) <= %i' . $actionFilter;
        array_push($values, $options['since'], $options['until']);
        if ($actionFilter !== '') {
            $values[] = $accesses;
        }
    }
    $types = [];
    if (in_array('failed', $categories, true)) {
        $types[] = 'failed_auth';
    }
    if (in_array('connections', $categories, true)) {
        $types[] = 'user_connection';
    }
    if ($types !== []) {
        $queries[] = 'SELECT CAST(l.date AS SIGNED) AS timestamp, 2 AS source_rank, l.id AS event_id,
            COALESCE(u.id, 0) AS user_id, COALESCE(u.login, \'\') AS user_login, l.label AS action,
            l.field_1 AS detail, NULL AS item_id, NULL AS item_label, l.type AS source_type
            FROM ' . $tables['log_system'] . ' l
            LEFT JOIN ' . $tables['users'] . ' u ON l.type = \'user_connection\' AND l.qui = CAST(u.id AS CHAR)
            WHERE CAST(l.date AS SIGNED) > %i AND CAST(l.date AS SIGNED) <= %i AND l.type IN %ls
            AND (l.type = \'failed_auth\' OR l.label IN (\'connection\', \'user_connection\'))';
        array_push($values, $options['since'], $options['until'], $types);
    }
    if (in_array('kb', $categories, true)) {
        $queries[] = 'SELECT CAST(created_at AS SIGNED) AS timestamp, 3 AS source_rank, increment_id AS event_id,
            0 AS user_id, \'\' AS user_login, \'\' AS action, valeur AS detail,
            NULL AS item_id, NULL AS item_label, \'kb\' AS source_type
            FROM ' . $tables['misc'] . ' WHERE type = %s AND CAST(created_at AS SIGNED) > %i AND CAST(created_at AS SIGNED) <= %i';
        array_push($values, 'kb_log', $options['since'], $options['until']);
    }
    if ($queries === []) {
        return ['', []];
    }
    return [implode(' UNION ALL ', $queries), $values];
}

/** Build an exclusive keyset predicate; inserts cannot shift subsequent pages. */
function adminActivityCursorPredicate(array $cursor, bool $newer = false): array
{
    $operator = $newer ? '>' : '<';
    return [
        '(timestamp ' . $operator . ' %i OR (timestamp = %i AND source_rank ' . $operator . ' %i)'
        . ' OR (timestamp = %i AND source_rank = %i AND event_id ' . $operator . ' %i))',
        [$cursor[0], $cursor[0], $cursor[1], $cursor[0], $cursor[1], $cursor[2]],
    ];
}

/** Convert a journal row into display data without revealing journal details or secrets. */
function adminActivityFormat(array $row, callable $translate): array
{
    $source = $row['source_type'];
    $action = (string) $row['action'];
    $login = (string) $row['user_login'];
    $label = $row['item_label'];
    $channel = 'web';
    if ($source === 'kb') {
        $payload = json_decode((string) $row['detail'], true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $action = (string) ($payload['action'] ?? '');
        $login = (string) ($payload['user_login'] ?? '');
        $label = (string) ($payload['label'] ?? '');
    } elseif ($source === 'failed_auth' || $source === 'user_connection') {
        $isApi = logsSystemRowIsApi($source, $action, (string) $row['detail']);
        $channel = $isApi ? 'api' : 'web';
        if ($source === 'failed_auth') {
            $login = logsStripApiMarker((string) $row['detail'], $isApi);
        }
    } elseif (strpos((string) $row['detail'], 'tp_src=api') !== false) {
        $channel = 'api';
    }
    $actions = [
        'at_shown' => 'action_accessed', 'at_creation' => 'action_created',
        'at_modification' => 'action_modified', 'at_delete' => 'action_deleted',
        'at_manual' => 'action_manual', 'at_password_shown_edit_form' => 'opened_edit_form_of',
        'at_copy' => 'copied', 'at_restored' => 'at_restored',
    ];
    $actionText = $source === 'failed_auth' ? $translate('admin_activity_failed')
        : ($source === 'user_connection' ? $translate('admin_activity_connected') : $translate($actions[$action] ?? $action));
    return [
        'id' => $row['source_rank'] . ':' . $row['event_id'],
        'cursor' => [(int) $row['timestamp'], (int) $row['source_rank'], (int) $row['event_id']],
        'timestamp' => (int) $row['timestamp'], 'user_id' => (int) $row['user_id'],
        'user_login' => $login !== '' ? $login : $translate('unknown'),
        'action' => $action, 'action_text' => $actionText,
        'reason' => $source === 'failed_auth' ? $translate($action) : '',
        'source_type' => $source, 'channel' => $channel,
        'item_id' => $row['item_id'] === null ? null : (int) $row['item_id'], 'item_label' => $label,
    ];
}
