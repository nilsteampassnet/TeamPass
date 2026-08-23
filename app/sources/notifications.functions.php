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
        'local_password_expiring',
        'kb_article_created',
        'backup_failed',
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

        case 'local_password_expiring':
            $clean['days_remaining'] = min(36500, max(0, (int) ($payload['days_remaining'] ?? 0)));
            $clean['threshold'] = min(36500, max(0, (int) ($payload['threshold'] ?? 0)));
            $clean['expires_at'] = max(0, (int) ($payload['expires_at'] ?? 0));
            break;

        case 'kb_article_created':
            $clean['kb_id'] = max(0, (int) ($payload['kb_id'] ?? 0));
            $clean['label'] = mb_substr((string) ($payload['label'] ?? ''), 0, 200);
            break;

        case 'backup_failed':
            $clean['backup_type'] = (string) ($payload['backup_type'] ?? '') === 'externalized'
                ? 'externalized'
                : 'scheduled';
            // Backup failures often quote shell, driver or filesystem output
            // that is not valid UTF-8. Repair it first: the /u modifier would
            // otherwise return null and silently drop the whole cause.
            $rawMessage = trim((string) ($payload['message'] ?? ''));
            if ($rawMessage !== '' && mb_check_encoding($rawMessage, 'UTF-8') === false) {
                $rawMessage = (string) mb_convert_encoding($rawMessage, 'UTF-8', 'UTF-8');
            }
            $message = preg_replace('/\s+/u', ' ', $rawMessage);
            $clean['message'] = mb_substr(is_string($message) ? $message : '', 0, 300);
            break;

        default:
            // Unknown type: store nothing rather than arbitrary data.
            break;
    }

    return $clean;
}

/**
 * Select the warning milestone for a local password expiry.
 *
 * The returned value doubles as the notification's stable threshold. A user
 * who first connects between milestones receives the closest applicable one,
 * while the persistence dedupe key prevents it from being repeated on every
 * page load.
 *
 * @param int $daysRemaining Whole days before expiry (0 or less means expired)
 *
 * @return int|null 14, 7, 3, 1, 0 (expired), or null when no warning is due
 */
function notificationPasswordExpiryThreshold(int $daysRemaining): ?int
{
    if ($daysRemaining <= 0) {
        return 0;
    }

    foreach ([1, 3, 7, 14] as $threshold) {
        if ($daysRemaining <= $threshold) {
            return $threshold;
        }
    }

    return null;
}

/**
 * Build the idempotency key for one password cycle and warning milestone.
 */
function notificationPasswordExpiryDedupeKey(int $expiresAt, int $threshold): string
{
    return 'local_password_expiry:' . max(0, $expiresAt) . ':' . max(0, $threshold);
}

/**
 * Build the idempotency key for a knowledge-base publication fan-out.
 */
function notificationKbPublicationDedupeKey(int $kbId): string
{
    return 'kb_article_created:' . max(0, $kbId);
}

/**
 * Build the idempotency key for an administrator backup-failure alert.
 *
 * A created task uses its numeric id; a scheduler preflight uses a stable
 * date-and-cause identifier. The backup type is part of the key because a
 * successful scheduled task may still report that its chained
 * externalization could not be queued.
 */
function notificationBackupFailureDedupeKey(int|string $failureId, string $backupType): string
{
    $normalizedType = $backupType === 'externalized' ? 'externalized' : 'scheduled';
    $normalizedFailureId = preg_replace(
        '/[^a-zA-Z0-9_.:-]+/',
        '_',
        substr(trim((string) $failureId), 0, 80)
    );
    if (is_string($normalizedFailureId) === false || $normalizedFailureId === '') {
        $normalizedFailureId = 'unknown';
    }

    return 'backup_failed:' . $normalizedType . ':' . $normalizedFailureId;
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
