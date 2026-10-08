<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * TeamPass is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See <https://www.gnu.org/licenses/> for the GNU General Public License.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/**
 * Validate the administrator policy without coercing malformed values to a purge.
 *
 * @param mixed $value Canonical integer days, 0 (keep all) through 36500
 * @return int Retention days
 */
function secureSendAuditRetentionDays(mixed $value): int
{
    if (is_int($value)) {
        $valid = $value >= 0 && $value <= 36500;
    } elseif (is_string($value)) {
        $value = trim($value);
        $valid = preg_match('/^(0|[1-9][0-9]{0,4})$/D', $value) === 1 && (int) $value <= 36500;
    } else {
        $valid = false;
    }
    if (!$valid) {
        throw new InvalidArgumentException('Invalid Secure Send audit retention');
    }
    return (int) $value;
}

/**
 * Prune one bounded batch and retain its summary in the same transaction.
 *
 * Retention is independent of Secure Send enablement and link/account existence.
 * Missing/disabled policies do not access the database. Errors propagate so the
 * maintenance task can report failure; no deletion may commit without a summary.
 *
 * @param array $settings Administrator and optional syslog settings
 * @param int|null $now Observation time, injectable for boundary tests
 * @return int Actual number of deleted events (at most 1000)
 */
function secureSendPruneAuditHistory(array $settings, ?int $now = null): int
{
    $days = secureSendAuditRetentionDays($settings['secure_send_audit_retention_days'] ?? 0);
    $now = $now ?? time();
    if ($now < 0) {
        throw new InvalidArgumentException('Invalid Secure Send retention time');
    }
    $cutoff = max(0, $now - $days * 86400);
    if ($days === 0 || $cutoff === 0) {
        return 0;
    }

    // Older installations may still have non-transactional system logs. Fail
    // closed rather than claiming atomic evidence on a MyISAM table.
    $transactionalTables = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM information_schema.tables
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN %ls AND ENGINE = %s',
        [prefixTable('secure_send_audit'), prefixTable('log_system')],
        'InnoDB'
    );
    if ($transactionalTables !== 2) {
        throw new RuntimeException('Secure Send retention requires transactional audit tables');
    }

    DB::startTransaction();
    try {
        DB::query(
            'DELETE FROM ' . prefixTable('secure_send_audit') . '
            WHERE occurred_at < %i ORDER BY occurred_at ASC, id ASC LIMIT 1000',
            $cutoff
        );
        $deleted = DB::affectedRows();
        $summary = [
            'retention_days' => $days,
            'cutoff' => $cutoff,
            'deleted_count' => $deleted,
            'batch_limit' => 1000,
        ];
        if ($deleted > 0) {
            // logEvents() deliberately swallows insert failures; use the raw
            // insert here to make the deletion and its evidence inseparable.
            DB::insert(prefixTable('log_system'), [
                'type' => 'admin_action',
                'date' => (string) $now,
                'label' => 'secure_send_audit_retention_purge',
                'qui' => (string) TP_USER_ID,
                'field_1' => json_encode($summary, JSON_THROW_ON_ERROR),
            ]);
        }
        DB::commit();
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }

    if ($deleted > 0 && (int) ($settings['syslog_enable'] ?? 0) === 1) {
        try {
            send_syslog(
                'action=secure_send_audit_retention ' . json_encode($summary, JSON_THROW_ON_ERROR),
                $settings['syslog_host'],
                $settings['syslog_port'],
                'teampass'
            );
        } catch (Throwable $e) {
            error_log('TEAMPASS Secure Send retention forwarding failed (' . get_class($e) . ')');
        }
    }
    return $deleted;
}
