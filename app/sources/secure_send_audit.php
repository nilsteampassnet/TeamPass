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
 * @file      secure_send_audit.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Return the shared, replayable schema for fresh installs and upgrades.
 *
 * No foreign keys: deleting a link, item or account must not erase its evidence.
 *
 * @param string $tableName Installer prefix or prefixTable('secure_send_audit')
 * @return string MySQL/MariaDB DDL
 */
function secureSendAuditSchemaSql(string $tableName): string
{
    if (preg_match('/^[a-zA-Z0-9_]+$/D', $tableName) !== 1) {
        throw new InvalidArgumentException('Invalid audit table name');
    }

    return 'CREATE TABLE IF NOT EXISTS `' . $tableName . '` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `send_id` INT UNSIGNED NOT NULL,
        `event` VARCHAR(20) NOT NULL,
        `reason` VARCHAR(32) NOT NULL,
        `occurred_at` BIGINT UNSIGNED NOT NULL,
        `originator` INT UNSIGNED NOT NULL,
        `actor_id` INT UNSIGNED NULL DEFAULT NULL,
        `item_id` INT UNSIGNED NULL DEFAULT NULL,
        `send_type` VARCHAR(10) NOT NULL,
        `created_at` BIGINT UNSIGNED NOT NULL,
        `has_passphrase` TINYINT UNSIGNED NOT NULL,
        `is_public` TINYINT UNSIGNED NOT NULL,
        `max_views` INT UNSIGNED NOT NULL,
        `expires_at` BIGINT UNSIGNED NOT NULL,
        `views` INT UNSIGNED NOT NULL,
        `failed_attempts` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_send_history` (`send_id`, `id`),
        KEY `idx_event_period` (`event`, `occurred_at`),
        KEY `idx_originator_period` (`originator`, `occurred_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}

/**
 * Build an explicit metadata allowlist; never copy a payload, URL or credentials.
 *
 * Recipient operations have a null actor: possession of a link is not identity.
 * created_at is the original link timestamp, not evidence of an audited creation.
 *
 * @param array $link Stored link metadata (may contain secrets, which are discarded)
 * @param string $event Lifecycle event
 * @param string $reason Fixed reason code, never exception text or request input
 * @param int|null $actorId Authenticated sender for creation/revocation; null otherwise
 * @param int|null $now Observation time, injectable for tests
 * @return array<string, int|string|null> Safe database/syslog row
 */
function secureSendAuditRecord(array $link, string $event, string $reason = '', ?int $actorId = null, ?int $now = null): array
{
    $reasons = [
        'created' => [''],
        'revealed' => [''],
        'reveal_failed' => ['wrong_credentials'],
        'revoked' => ['sender_revoked'],
        'invalidated' => ['sender_unavailable', 'item_access_lost', 'attempts_exhausted', 'item_auto_deleted'],
        'expired' => ['deadline_elapsed'],
    ];
    if (!isset($reasons[$event]) || !in_array($reason, $reasons[$event], true)) {
        throw new InvalidArgumentException('Invalid Secure Send audit event');
    }
    $originator = (int) ($link['originator'] ?? 0);
    if ((int) ($link['id'] ?? 0) <= 0 || $originator <= 0
        || (in_array($event, ['created', 'revoked'], true) ? $actorId !== $originator : $actorId !== null)
    ) {
        throw new InvalidArgumentException('Invalid Secure Send audit actor');
    }
    $type = $link['send_type'] ?? 'item';

    return [
        'send_id' => (int) $link['id'],
        'event' => $event,
        'reason' => $reason,
        'occurred_at' => $now ?? time(),
        'originator' => $originator,
        'actor_id' => $actorId,
        'item_id' => empty($link['item_id']) ? null : (int) $link['item_id'],
        'send_type' => $type === 'note' ? 'note' : (in_array($type, ['item', 'item_v2'], true) ? 'item' : 'unknown'),
        'created_at' => max(0, (int) ($link['timestamp'] ?? 0)),
        'has_passphrase' => (int) ($link['has_passphrase'] ?? 0) === 1 ? 1 : 0,
        'is_public' => (int) ($link['shared_globaly'] ?? 0) === 1 ? 1 : 0,
        'max_views' => max(0, (int) ($link['max_views'] ?? 0)),
        'expires_at' => max(0, (int) ($link['time_limit'] ?? 0)),
        'views' => max(0, (int) ($link['views'] ?? 0)),
        'failed_attempts' => max(0, (int) ($link['failed_attempts'] ?? 0)),
    ];
}

/**
 * Insert an event inside the caller's transaction; audit failures must propagate.
 *
 * @param array $link Stored link metadata
 * @param string $event Lifecycle event
 * @param string $reason Fixed reason code
 * @param int|null $actorId Authenticated sender, or null for anonymous/system events
 * @param int|null $now Observation time
 * @return array Safe event to forward only after commit
 */
function secureSendAudit(array $link, string $event, string $reason = '', ?int $actorId = null, ?int $now = null): array
{
    $record = secureSendAuditRecord($link, $event, $reason, $actorId, $now);
    DB::insert(prefixTable('secure_send_audit'), $record);
    return $record;
}

/**
 * Forward a committed metadata event through the existing optional syslog transport.
 *
 * Transport failures must not turn an already committed operation into a failure.
 *
 * @param array $record Row returned by secureSendAudit()
 * @param array $settings Application/syslog settings
 * @return void
 */
function secureSendEmitAudit(array $record, array $settings): void
{
    if ((int) ($settings['syslog_enable'] ?? 0) !== 1) {
        return;
    }
    try {
        send_syslog(
            'action=secure_send ' . json_encode($record, JSON_THROW_ON_ERROR),
            $settings['syslog_host'],
            $settings['syslog_port'],
            'teampass'
        );
    } catch (Throwable $e) {
        error_log('TEAMPASS Secure Send audit forwarding failed (' . get_class($e) . ')');
    }
}
