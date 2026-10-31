<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/** Run production SELECTs against disposable SQLite data with MeekroDB-style binding. */
class StoredTextSearchFixtureDatabase
{
    public static SQLite3 $connection;
    public static array $calls = [];
    public static int $activeCursors = 0;

    /** Open a private fixture; no installation configuration is loaded. */
    public static function reset(): void
    {
        self::$connection = new SQLite3(':memory:');
        self::$connection->enableExceptions(true);
        self::$connection->createFunction('CONCAT', static fn (...$parts): string => implode('', $parts));
        self::$calls = [];
        self::$activeCursors = 0;
    }

    /** Translate only parameter binding, leaving production predicates and joins intact. */
    private static function execute(string $query, array $params): SQLite3Result
    {
        if (self::$activeCursors !== 0) {
            throw new RuntimeException('A query ran before its metadata cursor was released');
        }
        self::$calls[] = [$query, $params];
        $bindings = [];
        $query = preg_replace_callback('/%(li|ss|s|i)_([a-zA-Z][a-zA-Z0-9_]*)/', static function (array $match) use ($params, &$bindings): string {
            [$token, $kind, $key] = $match;
            if (!array_key_exists($key, $params)) {
                throw new RuntimeException('Missing query parameter: ' . $key);
            }
            if ($kind === 'li') {
                $names = [];
                foreach (array_values($params[$key]) as $index => $value) {
                    $name = $key . '_' . $index;
                    $bindings[$name] = (int) $value;
                    $names[] = ':' . $name;
                }
                return '(' . ($names === [] ? 'NULL' : implode(',', $names)) . ')';
            }
            $bindings[$key] = $kind === 'ss'
                ? '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $params[$key]) . '%'
                : $params[$key];
            return ':' . $key . ($kind === 'ss' ? " ESCAPE '\\'" : '');
        }, $query);
        $statement = self::$connection->prepare($query);
        foreach ($bindings as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        return $statement->execute();
    }

    /** Emulate the unbuffered driver; nested queries are rejected until free(). */
    public static function queryWalk(string $query, array $params = []): MeekroDBWalk
    {
        $result = self::execute($query, $params);
        ++self::$activeCursors;
        return new class ($result) extends MeekroDBWalk {
            private ?SQLite3Result $rows;

            /** Keep the local SELECT result without invoking the real MySQL driver. */
            public function __construct(SQLite3Result $rows) { $this->rows = $rows; }

            /** Fetch one fixture row just as the production streaming driver does. */
            public function next(): ?array
            {
                return $this->rows === null ? null : ($this->rows->fetchArray(SQLITE3_ASSOC) ?: null);
            }

            /** Make releasing a cursor idempotent, including destructor cleanup. */
            public function free(): void
            {
                if ($this->rows !== null) {
                    $this->rows->finalize();
                    $this->rows = null;
                    --StoredTextSearchFixtureDatabase::$activeCursors;
                }
            }
        };
    }

    /** Fetch a bounded result for the production handler's non-streaming queries. */
    public static function query(string $query, array $params = []): array
    {
        $result = self::execute($query, $params);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        $result->finalize();
        return $rows;
    }

    /** Preserve the production count-query contract. */
    public static function queryFirstRow(string $query, array $params = []): ?array
    {
        return self::query($query, $params)[0] ?? null;
    }
}
