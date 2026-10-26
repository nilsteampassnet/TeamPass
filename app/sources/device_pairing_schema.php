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
 * @file      device_pairing_schema.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/device_pairing_logic.php';

/**
 * Apply the replayable additive migration using installer-owned prefixed table names.
 * DDL may commit independently; readiness is published only after every step succeeds.
 * @param array<string,string> $tables Logical names mapped through prefixTable() by the caller
 */
function devicePairingUpgradeSchema(mysqli $connection, array $tables): void
{
    foreach (['users', 'api', 'api_tokens', 'api_sessions', 'api_rate_limit', 'log_system', 'misc', 'device_pairings'] as $name) {
        if (preg_match('/^[A-Za-z0-9_]+$/', $tables[$name] ?? '') !== 1) {
            throw new InvalidArgumentException('Invalid device pairing migration table');
        }
    }
    $columns = ['api_tokens' => ['purpose' => "VARCHAR(32) NOT NULL DEFAULT 'legacy'", 'pairing_id' => 'CHAR(32) NULL DEFAULT NULL', 'revoked_at' => 'INT(12) NULL DEFAULT NULL'],
        'api_sessions' => ['source_token_id' => 'INT(12) NULL DEFAULT NULL']];
    foreach ($columns as $name => $definitions) {
        $existing = [];
        $result = $connection->query('SHOW COLUMNS FROM `' . $tables[$name] . '`');
        if ($result === false) {
            throw new RuntimeException('Could not read device credential schema');
        }
        while ($column = $result->fetch_assoc()) {
            $existing[] = $column['Field'];
        }
        foreach ($definitions as $column => $definition) {
            if (!in_array($column, $existing, true)
                && !$connection->query('ALTER TABLE `' . $tables[$name] . '` ADD `' . $column . '` ' . $definition)
            ) {
                throw new RuntimeException('Could not add device credential column');
            }
        }
    }
    $indexes = $connection->query("SHOW INDEX FROM `" . $tables['api_sessions'] . "` WHERE Key_name = 'source_token_id'");
    if ($indexes === false || ($indexes->num_rows === 0 && !$connection->query(
        'ALTER TABLE `' . $tables['api_sessions'] . '` ADD INDEX `source_token_id` (`source_token_id`)'
    ))) {
        throw new RuntimeException('Could not index device sessions');
    }
    if (!$connection->query(devicePairingSchemaSql($tables['device_pairings']))) {
        throw new RuntimeException('Could not create pairing transactions');
    }
    // Transaction guarantees require every participating table, including the audit, to be transactional.
    foreach ($tables as $table) {
        $result = $connection->query("SHOW TABLE STATUS WHERE Name = '" . $table . "'");
        $status = $result === false ? null : $result->fetch_assoc();
        if (strtolower((string) ($status['Engine'] ?? '')) !== 'innodb') {
            throw new RuntimeException('External device pairing requires InnoDB tables');
        }
    }
    if (!$connection->query('INSERT IGNORE INTO `' . $tables['misc'] . "` (type, intitule, valeur)
        VALUES ('admin', 'device_pairing_enabled', '0'), ('admin', 'device_pairing_schema', '2'),
            ('admin', 'device_pairing_token_lifetime_days', '90')")
    ) {
        throw new RuntimeException('Could not install device policy settings');
    }
    $policy = $connection->query('SELECT valeur FROM `' . $tables['misc'] . "` WHERE type = 'admin' AND intitule = 'device_pairing_token_lifetime_days'");
    $days = apiDeviceLifetimeDays(['device_pairing_token_lifetime_days' => $policy === false ? 90 : ($policy->fetch_assoc()['valeur'] ?? 90)]);
    // Give existing experimental credentials a bounded migration grace period exactly once.
    if (!$connection->query('UPDATE `' . $tables['api_tokens'] . '` SET expires_at = ' . (time() + $days * 86400)
        . " WHERE purpose = 'external_device' AND expires_at IS NULL AND revoked_at IS NULL")
        || !$connection->query('UPDATE `' . $tables['misc'] . "` SET valeur = '3' WHERE type = 'admin' AND intitule = 'device_pairing_schema'")
    ) {
        throw new RuntimeException('Could not publish the pairing schema version');
    }
}
