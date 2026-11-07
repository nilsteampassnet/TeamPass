<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/** SQLite executes the conditional ownership logic; adapt only MySQL's two syntax differences. */
final class DB
{
    public static PDO $connection;
    public static array $arguments = [];
    private static int $affected = 0;

    /** Execute parameterized production queries against the isolated database. */
    public static function query(string $sql, ...$arguments): array
    {
        return self::execute($sql, $arguments)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Return the first production query result. */
    public static function queryFirstRow(string $sql, ...$arguments): ?array
    {
        $row = self::execute($sql, $arguments)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Return a scalar from the active configuration query. */
    public static function queryFirstField(string $sql, ...$arguments)
    {
        return self::execute($sql, $arguments)->fetchColumn();
    }

    /** Report actual conditional update results. */
    public static function affectedRows(): int
    {
        return self::$affected;
    }

    private static function execute(string $sql, array $arguments): PDOStatement
    {
        self::$arguments[] = $arguments;
        $sql = preg_replace('/%[si]/', '?', $sql);
        $sql = str_replace(['INSERT IGNORE', 'UNIX_TIMESTAMP()'], ['INSERT OR IGNORE', "CAST(strftime('%s', 'now') AS INTEGER)"], $sql);
        $statement = self::$connection->prepare($sql);
        $statement->execute($arguments);
        self::$affected = $statement->rowCount();
        return $statement;
    }
}
