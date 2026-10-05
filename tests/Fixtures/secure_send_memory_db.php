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
 * @file      secure_send_memory_db.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/** Transactional in-memory adapter. Real SQL/concurrency is exercised separately in CI. */
class DB
{
    public static array $links = [];
    public static array $audit = [];
    public static array $secureAudit = [];
    public static array $forwarded = [];
    public static array $item = [];
    public static array $automatic = [];
    public static array $cache = [];
    public static array $folderCounts = [];
    public static bool $access = true;
    public static bool $activeUser = true;
    public static bool $admin = false;
    public static bool $hasSharekey = true;
    public static bool $rejectReservation = false;
    public static bool $failAudit = false;
    public static bool $failSecureAudit = false;
    public static ?int $failSecureAuditAfter = null;
    public static bool $failForward = false;
    public static bool $failDelete = false;
    public static bool $failCache = false;
    public static bool $failCounter = false;
    public static string|false $cipherError = false;
    public static string $unwrapError = '';
    public static string $objectKey = 'object-key';
    public static string $password = 'secret-at-creation';
    private static array $snapshot = [];
    private static int $affected = 0;
    private static int $id = 0;

    public static function reset(): void
    {
        self::$links = self::$audit = self::$secureAudit = self::$forwarded = self::$automatic = self::$snapshot = [];
        self::$access = self::$activeUser = self::$hasSharekey = true;
        self::$admin = self::$rejectReservation = self::$failAudit = false;
        self::$failSecureAudit = self::$failForward = self::$failDelete = false;
        self::$failSecureAuditAfter = null;
        self::$failCache = self::$failCounter = false;
        self::$cipherError = false;
        self::$unwrapError = '';
        self::$cache = [123 => ['id' => 123]];
        self::$folderCounts = [7 => 1];
        self::$objectKey = 'object-key';
        self::$password = 'secret-at-creation';
        self::$id = 0;
        self::$item = ['id' => 123, 'id_tree' => 7, 'label' => 'Original label', 'login' => 'alice', 'url' => 'https://example.com',
            'description' => '&lt;p&gt;Original note&lt;/p&gt;', 'pw' => 'encrypted-password', 'pw_iv' => '', 'pw_len' => 18, 'inactif' => 0, 'deleted_at' => null];
    }

    public static function queryFirstRow(string $sql, ...$args): ?array
    {
        if (str_contains($sql, prefixTable('otv'))) {
            if (str_contains($sql, 'WHERE id = %i')) {
                return self::$links[$args[0]] ?? null;
            }
            foreach (self::$links as $row) {
                if ($row['code'] === $args[0] && (string) $row['timestamp'] === (string) $args[1]) {
                    return $row;
                }
            }
            return null;
        }
        if (str_contains($sql, prefixTable('users'))) {
            return self::$activeUser ? [
                'id' => $args[0],
                'admin' => self::$admin ? 1 : 0,
                'name' => 'Alice',
                'lastname' => 'Sender',
            ] : null;
        }
        if (str_contains($sql, prefixTable('items'))) {
            return self::$access && !str_contains($sql, '(1 = 0)') && $args[0] === 123
                && self::$item['inactif'] === 0 && self::$item['deleted_at'] === null ? self::$item : null;
        }
        if (str_contains($sql, prefixTable('sharekeys_items'))) {
            return self::$hasSharekey ? ['share_key' => 'wrapped-item-key', 'increment_id' => 1] : null;
        }
        if (str_contains($sql, prefixTable('automatic_del'))) {
            return self::$automatic ?: null;
        }
        throw new RuntimeException('Unexpected test query: ' . $sql);
    }

    public static function queryFirstField(string $sql, ...$args): ?int
    {
        return self::$activeUser ? (int) $args[0] : null;
    }

    public static function insert(string $table, array $row): void
    {
        if ($table === prefixTable('otv')) {
            $row['id'] = ++self::$id;
            self::$links[self::$id] = $row;
        } elseif ($table === prefixTable('send_audit')) {
            if (self::$failAudit) {
                throw new RuntimeException('Test audit failure');
            }
            self::$audit[] = $row;
        } elseif ($table === prefixTable('secure_send_audit')) {
            if (self::$failSecureAudit || (self::$failSecureAuditAfter !== null
                && count(self::$secureAudit) >= self::$failSecureAuditAfter)
            ) {
                throw new RuntimeException('Synthetic secure audit failure containing secret-canary');
            }
            self::$secureAudit[] = $row;
        } else {
            throw new RuntimeException('Unexpected test insert');
        }
    }

    public static function insertId(): int { return self::$id; }
    public static function affectedRows(): int { return self::$affected; }

    public static function update(string $table, array $changes, string $where, int $id): void
    {
        if ($table === prefixTable('items')) {
            self::$item = array_replace(self::$item, $changes);
            return;
        }
        self::$links[$id] = array_replace(self::$links[$id], $changes);
    }

    public static function delete(string $table, string $where, int $id): void
    {
        if (self::$failDelete) {
            throw new RuntimeException('Synthetic deletion failure');
        }
        if ($table === prefixTable('cache')) {
            if (self::$failCache) {
                throw new RuntimeException('Synthetic cache failure');
            }
            unset(self::$cache[$id]);
            return;
        }
        if ($table === prefixTable('automatic_del')) {
            self::$automatic = [];
            return;
        }
        unset(self::$links[$id]);
    }

    public static function query(string $sql, ...$args): int|array
    {
        self::$affected = 0;
        if (str_contains($sql, 'ORDER BY id ASC LIMIT 100 FOR UPDATE')) {
            return array_slice(array_values(array_filter(self::$links,
                static fn (array $row): bool => (int) $row['time_limit'] < $args[0])), 0, 100);
        }
        if (str_contains($sql, 'SET views = views + 1')) {
            $id = $args[0];
            if (!self::$rejectReservation && isset(self::$links[$id]) && self::$links[$id]['views'] < self::$links[$id]['max_views']
                && self::$links[$id]['time_limit'] > $args[1] && self::$links[$id]['failed_attempts'] < 5
            ) {
                ++self::$links[$id]['views'];
                self::$affected = 1;
            }
        } elseif (str_contains($sql, 'SET del_value = del_value - 1')) {
            if (self::$automatic['del_value'] > 0) {
                --self::$automatic['del_value'];
                self::$affected = 1;
            }
        } elseif (str_contains($sql, 'SET nb_items_in_folder =')) {
            if (self::$failCounter) {
                throw new RuntimeException('Synthetic folder counter failure');
            }
            self::$folderCounts[$args[1]] = max(0, self::$folderCounts[$args[1]] + $args[0]);
            self::$affected = 1;
        } else {
            throw new RuntimeException('Unexpected test write');
        }
        return self::$affected;
    }

    public static function startTransaction(): void
    {
        if (self::$snapshot !== []) {
            throw new RuntimeException('Unclosed test transaction');
        }
        self::$snapshot = [self::$links, self::$audit, self::$secureAudit, self::$automatic, self::$item, self::$cache, self::$folderCounts];
    }

    public static function commit(): void { self::$snapshot = []; }
    public static function inTransaction(): bool { return self::$snapshot !== []; }
    public static function rollback(): void
    {
        if (self::$snapshot !== []) {
            [self::$links, self::$audit, self::$secureAudit, self::$automatic, self::$item, self::$cache, self::$folderCounts] = self::$snapshot;
            self::$snapshot = [];
        }
    }
}
