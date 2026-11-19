<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

namespace TeampassClasses\ConfigManager {
    /** No settings cache exists in this isolated fixture. */
    final class ConfigManager { public static function invalidateCache(): void {} }
}
namespace {
    require __DIR__ . '/../../app/sources/backup_restore.php';
    /** Route every operation to a fictitious table prefix. */
    function prefixTable(string $table): string { return 'fixture_' . $table; }
    /** Record real invalidation operations without connecting to SQL. */
    final class DB
    {
        public static array $operations = [];
        /** The fixture contains the ephemeral tables, but no Secure Send links. */
        public static function query(string $sql, ...$args): array {
            self::$operations[] = [$sql, $args];
            return $sql === 'SHOW TABLES' ? array_map(static fn ($table): array => ['name' => 'fixture_' . $table],
                ['users', 'misc', 'api', 'api_sessions', 'api_tokens', 'device_pairings', 'tokens', 'websocket_tokens',
                    'websocket_connections', 'background_subtasks', 'background_tasks', 'api_idempotency']) : [];
        }
        /** Record configuration invalidations. */
        public static function update(string $table, array $values, string $where, ...$args): int {
            self::$operations[] = ['update', [$table, $values, $where, $args]];
            return 1;
        }
        /** Record revocation of authorization and upload bookkeeping. */
        public static function delete(...$args): void { self::$operations[] = ['delete', $args]; }
        /** The fixture configuration rows already exist. */
        public static function affectedRows(): int { return 1; }
        /** Record the transaction boundary. */
        public static function startTransaction(): void { self::$operations[] = ['begin']; }
        /** Record the transaction boundary. */
        public static function commit(): void { self::$operations[] = ['commit']; }
        /** Record an unexpected rollback. */
        public static function rollback(): void { self::$operations[] = ['rollback']; }
    }
    tpBackupInvalidateRestoredState();
    echo json_encode(DB::$operations, JSON_THROW_ON_ERROR);
}
