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
 * @file      secure_send_statistics_db.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/** SQLite executes production aggregate SQL; only MeekroDB parameter binding is adapted. */
class DB
{
    public static SQLite3 $connection;
    public static array $queries = [];
    public static ?int $failQuery = null;
    public static bool $missingSummary = false;

    /** Execute scalar and integer-list placeholders without changing the query's semantics. */
    public static function query(string $sql, mixed ...$parameters): array
    {
        self::$queries[] = $sql;
        if (self::$failQuery === count(self::$queries)) {
            throw new RuntimeException('Synthetic statistics error with secret-canary');
        }
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

    /** Return the actual aggregate result, including SQL NULL for an empty period. */
    public static function queryFirstRow(string $sql, mixed ...$parameters): ?array
    {
        $row = self::query($sql, ...$parameters)[0] ?? null;
        return self::$missingSummary ? null : $row;
    }
}

/** Use a non-default prefix without any installation settings. */
function prefixTable(string $table): string
{
    return 'stats_fixture_' . $table;
}
