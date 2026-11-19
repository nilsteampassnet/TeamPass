<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * This file is part of TeamPass, distributed under the GNU GPL version 3 or later.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

/** Explicit database adapter for the real network policy functions. */
class DB
{
    public static bool $tableExists = true;
    public static bool $failTableCheck = false;
    public static bool $failRules = false;
    public static int $queries = 0;
    public static array $rules = [];

    /** Execute only the metadata and rule reads expected by the policy. */
    public static function query(string $sql, mixed ...$arguments): array
    {
        ++self::$queries;
        if ($sql === 'SHOW TABLES LIKE %s' && $arguments === [prefixTable('network_acl')]) {
            if (self::$failTableCheck) {
                throw new RuntimeException('database-credential-canary');
            }
            return [];
        }
        if (str_contains($sql, 'FROM ' . prefixTable('network_acl'))) {
            if (self::$failRules) {
                throw new RuntimeException('database-credential-canary');
            }
            return self::$rules;
        }
        throw new LogicException('Unexpected query in network ACL fixture');
    }

    /** Model the result of SHOW TABLES without opening a real database. */
    public static function count(): int
    {
        return self::$tableExists ? 1 : 0;
    }
}
