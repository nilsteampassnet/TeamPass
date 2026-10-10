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

/** SQLite transport; the real MariaDB harness also executes the unadapted DELETE. */
class DB
{
    public static SQLite3 $connection;
    public static array $queries = [];
    public static int $transactionalTables = 2;
    public static bool $failDelete = false;
    public static bool $failInsert = false;
    public static bool $failCommit = false;
    public static bool $inTransaction = false;
    public static bool $failForward = false;
    public static array $forwarded = [];

    /** Model the explicit engine prerequisite without pretending SQLite has engines. */
    public static function queryFirstField(string $sql, mixed ...$values): int
    {
        self::$queries[] = [$sql, $values];
        if (!str_contains($sql, 'information_schema.tables')) {
            throw new LogicException('Unexpected retention prerequisite SQL');
        }
        return self::$transactionalTables;
    }

    /** Translate precisely the MySQL bounded DELETE into SQLite's equivalent subquery. */
    public static function query(string $sql, int $cutoff): void
    {
        self::$queries[] = [$sql, [$cutoff]];
        if (self::$failDelete) {
            throw new RuntimeException('Synthetic deletion failure: secret-canary');
        }
        if (preg_match('/^DELETE FROM (\w+)\s+WHERE occurred_at < %i ORDER BY occurred_at ASC, id ASC LIMIT 1000$/D', $sql, $match) !== 1) {
            throw new LogicException('Unexpected retention DELETE');
        }
        $statement = self::$connection->prepare('DELETE FROM ' . $match[1]
            . ' WHERE id IN (SELECT id FROM ' . $match[1]
            . ' WHERE occurred_at < ? ORDER BY occurred_at ASC, id ASC LIMIT 1000)');
        $statement->bindValue(1, $cutoff, SQLITE3_INTEGER);
        $statement->execute();
    }

    /** Report actual SQLite changes immediately after the delete. */
    public static function affectedRows(): int { return self::$connection->changes(); }

    /** Execute the real summary insert; a failure must roll back the prior DELETE. */
    public static function insert(string $table, array $row): void
    {
        if (self::$failInsert) {
            throw new RuntimeException('Synthetic summary failure: secret-canary');
        }
        $statement = self::$connection->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row))
            . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
        foreach (array_values($row) as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $statement->execute();
    }

    /** Begin a real SQLite transaction. */
    public static function startTransaction(): void
    {
        self::$connection->exec('BEGIN');
        self::$inTransaction = true;
    }

    /** Simulate commit failure before the underlying transaction commits. */
    public static function commit(): void
    {
        if (self::$failCommit) {
            throw new RuntimeException('Synthetic commit failure: secret-canary');
        }
        self::$connection->exec('COMMIT');
        self::$inTransaction = false;
    }

    /** Roll back both the summary and deletion. */
    public static function rollback(): void
    {
        self::$connection->exec('ROLLBACK');
        self::$inTransaction = false;
    }
}

/** Use only isolated tables, with a non-default prefix. */
function prefixTable(string $table): string { return 'retention_fixture_' . $table; }

/** Capture optional forwarding and reject a call before commit. */
function send_syslog(string $message, string $host, int|string $port, string $tag): void
{
    if (DB::$inTransaction) {
        throw new LogicException('Forwarding attempted before commit');
    }
    DB::$forwarded[] = [$message, $host, $port, $tag];
    if (DB::$failForward) {
        throw new RuntimeException('Synthetic forwarding failure: secret-canary');
    }
}
