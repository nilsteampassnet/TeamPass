<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

/** Execute the production inventory SQL against an isolated SQLite database. */
class DB
{
    public static SQLite3 $connection;
    public static array $queries = [];
    public static array $folders = [42 => [7], 43 => [8]];
    public static array $resolvedSenders = [];

    /** Adapt MeekroDB integer bindings, preserving the production query semantics. */
    public static function query(string $sql, mixed ...$parameters): array
    {
        self::$queries[] = $sql;
        $index = 0;
        $bindings = [];
        $translated = preg_replace_callback('/%li|%i/', static function (array $match) use ($parameters, &$index, &$bindings): string {
            $value = $parameters[$index++];
            if ($match[0] === '%li') {
                foreach ($value as $id) {
                    $bindings[] = (int) $id;
                }
                return '(' . implode(',', array_fill(0, count($value), '?')) . ')';
            }
            $bindings[] = (int) $value;
            return '?';
        }, $sql);
        $statement = self::$connection->prepare($translated);
        foreach ($bindings as $position => $value) {
            $statement->bindValue($position + 1, $value, SQLITE3_INTEGER);
        }
        $result = $statement->execute();
        $rows = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }
}

/** Exercise installation-specific prefixes. */
function prefixTable(string $table): string
{
    return 'active_fixture_' . $table;
}

/** Folder and restriction resolution itself is covered by SecurityPostureLogicTest. */
function securityPostureItemAccessSql(int $userId, string $alias): string
{
    DB::$resolvedSenders[] = $userId;
    $folders = DB::$folders[$userId] ?? [];
    return $folders === [] ? '(1 = 0)' : $alias . '.id_tree IN (' . implode(',', $folders) . ')';
}
