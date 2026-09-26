<?php

declare(strict_types=1);

/** Transactional in-memory adapter. Real SQL/concurrency is exercised separately in CI. */
class DB
{
    public static array $links = [];
    public static array $audit = [];
    public static array $item = [];
    public static array $automatic = [];
    public static bool $access = true;
    public static bool $activeUser = true;
    public static bool $admin = false;
    public static bool $hasSharekey = true;
    public static bool $rejectReservation = false;
    public static bool $failAudit = false;
    public static string $objectKey = 'object-key';
    public static string $password = 'secret-at-creation';
    private static array $snapshot = [];
    private static int $affected = 0;
    private static int $id = 0;

    public static function reset(): void
    {
        self::$links = self::$audit = self::$automatic = self::$snapshot = [];
        self::$access = self::$activeUser = self::$hasSharekey = true;
        self::$admin = self::$rejectReservation = self::$failAudit = false;
        self::$objectKey = 'object-key';
        self::$password = 'secret-at-creation';
        self::$id = 0;
        self::$item = ['id' => 123, 'id_tree' => 7, 'label' => 'Original label', 'login' => 'alice', 'url' => 'https://example.com',
            'description' => '&lt;p&gt;Original note&lt;/p&gt;', 'pw' => 'encrypted-password', 'pw_iv' => '', 'pw_len' => 18, 'inactif' => 0, 'deleted_at' => null];
    }

    public static function queryFirstRow(string $sql, ...$args): ?array
    {
        if (str_contains($sql, prefixTable('otv'))) {
            foreach (self::$links as $row) {
                if ($row['code'] === $args[0] && (string) $row['timestamp'] === (string) $args[1]) {
                    return $row;
                }
            }
            return null;
        }
        if (str_contains($sql, prefixTable('users'))) {
            return self::$activeUser ? ['id' => $args[0], 'admin' => self::$admin ? 1 : 0] : null;
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
        if ($table === prefixTable('automatic_del')) {
            self::$automatic = [];
            return;
        }
        unset(self::$links[$id]);
    }

    public static function query(string $sql, ...$args): int
    {
        self::$affected = 0;
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
        self::$snapshot = [self::$links, self::$audit, self::$automatic, self::$item];
    }

    public static function commit(): void { self::$snapshot = []; }
    public static function rollback(): void
    {
        if (self::$snapshot !== []) {
            [self::$links, self::$audit, self::$automatic, self::$item] = self::$snapshot;
            self::$snapshot = [];
        }
    }
}
