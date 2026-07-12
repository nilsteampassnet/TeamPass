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
 * @file      notifications.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * In-app Notification Centre (D2 — Scale & polish).
 *
 * Pure, DB-free notification logic (unit-tested by
 * tests/Unit/NotificationCenterLogicTest.php). Persistence and the AJAX
 * handlers live in main.functions.php / main.queries.php; these functions
 * decide what gets stored and shape the stored rows for the client.
 */

/**
 * The user-target WebSocket event types worth keeping in the inbox.
 *
 * Deliberately excludes transient events (session_expired: the user is
 * logged out; task_progress: a heartbeat, not an outcome).
 *
 * @return string[]
 */
function notificationPersistableEvents(): array
{
    return [
        'security_nudge',
        'user_keys_ready',
        'task_completed',
        'folder_permission_changed',
    ];
}

/**
 * Decide whether an emitted WebSocket event belongs in the notification inbox.
 *
 * @param string   $eventType  Event type (e.g. 'task_completed')
 * @param string   $targetType Event target ('user', 'folder', 'broadcast', ...)
 * @param int|null $targetId   Target user id for 'user' events
 *
 * @return bool True when the event must be persisted for the target user
 */
function notificationShouldPersist(string $eventType, string $targetType, ?int $targetId): bool
{
    return $targetType === 'user'
        && $targetId !== null
        && $targetId > 0
        && in_array($eventType, notificationPersistableEvents(), true);
}

/**
 * Keep only the payload keys the inbox needs, typed and bounded.
 *
 * Whitelist per event type — internal routing keys (exclude_user_id,
 * server_timestamp) and anything unexpected are dropped so the stored JSON
 * stays small and never leaks transport metadata.
 *
 * @param string               $eventType Event type
 * @param array<string, mixed> $payload   Raw event payload
 *
 * @return array<string, int|string> Sanitized payload to store
 */
function notificationSanitizePayload(string $eventType, array $payload): array
{
    $clean = [];
    switch ($eventType) {
        case 'security_nudge':
            foreach (['breached', 'weak', 'reused', 'overdue', 'total'] as $key) {
                if (isset($payload[$key]) === true) {
                    $clean[$key] = max(0, (int) $payload[$key]);
                }
            }
            break;

        case 'user_keys_ready':
            $clean['status'] = substr((string) ($payload['status'] ?? 'ready'), 0, 20);
            break;

        case 'task_completed':
            $clean['task_type'] = substr((string) ($payload['task_type'] ?? ''), 0, 100);
            $clean['status'] = substr((string) ($payload['status'] ?? ''), 0, 20);
            // Item encryption tasks carry the affected item's label so the
            // inbox can name it; kept only when non-empty, length-bounded.
            if (isset($payload['item_label']) === true && $payload['item_label'] !== '') {
                $clean['item_label'] = substr((string) $payload['item_label'], 0, 100);
            }
            break;

        case 'folder_permission_changed':
            // The folder list is enough for a human message; cap it defensively.
            if (isset($payload['folders']) === true && is_array($payload['folders'])) {
                $clean['folders_count'] = count($payload['folders']);
            }
            break;

        default:
            // Unknown type: store nothing rather than arbitrary data.
            break;
    }

    return $clean;
}

/**
 * Shape stored notification rows for the client (decode payload, cast types).
 *
 * @param array<int, array<string, mixed>> $records Raw user_notifications rows
 *
 * @return array<int, array<string, mixed>> Rows {id, type, payload, created_at, is_read}
 */
function notificationShapeRows(array $records): array
{
    $rows = [];
    foreach ($records as $record) {
        $payload = json_decode((string) ($record['payload'] ?? ''), true);
        $rows[] = [
            'id' => (int) ($record['increment_id'] ?? 0),
            'type' => (string) ($record['event_type'] ?? ''),
            'payload' => is_array($payload) ? $payload : [],
            'created_at' => (int) ($record['created_at'] ?? 0),
            'is_read' => (int) ($record['is_read'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    return $rows;
}

/**
 * Sanitize the ids posted by the "mark as read" action.
 *
 * @param mixed $rawIds Client-provided value (expected: array of ids)
 *
 * @return int[] Unique positive ids, re-indexed, capped at 100
 */
function notificationSanitizeIds($rawIds): array
{
    if (is_array($rawIds) === false) {
        return [];
    }

    $ids = [];
    foreach ($rawIds as $rawId) {
        $id = (int) $rawId;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_slice(array_values(array_unique($ids)), 0, 100);
}
