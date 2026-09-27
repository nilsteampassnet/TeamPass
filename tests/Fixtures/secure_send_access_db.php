<?php

declare(strict_types=1);

/** SQLite executes the access helper's SQL; ACL and cryptography are independent adapters. */
class DB
{
    public static SQLite3 $connection;
    public static array $folders = [42 => [7]];
    public static array $keyCalls = [];
    public static string $objectKey = 'item-key';
    public static string $password = 'shared-secret';
    public static bool $cryptoFailure = false;

    /** Execute the integer-parameterized read queries used by the access helper. */
    public static function queryFirstRow(string $sql, ...$parameters): ?array
    {
        $statement = self::$connection->query(vsprintf(str_replace('%i', '%d', $sql), array_map('intval', $parameters)));
        return $statement->fetchArray(SQLITE3_ASSOC) ?: null;
    }
}

/** Exercise a non-default table prefix. */
function prefixTable(string $name): string
{
    return 'sharing_fixture_' . $name;
}

/** Detached folder resolution is covered separately by SecurityPostureLogicTest. */
function securityPostureItemAccessSql(int $userId, string $alias): string
{
    $folders = DB::$folders[$userId] ?? [];
    return $folders === [] ? '(1 = 0)' : $alias . '.id_tree IN (' . implode(',', $folders) . ')';
}

/** Record use of the migration-aware helper while independently controlling key possession. */
function decryptUserObjectKeyWithMigration(...$arguments): string
{
    DB::$keyCalls[] = $arguments;
    if (DB::$cryptoFailure) {
        throw new RuntimeException('Synthetic decryption failure');
    }
    return DB::$objectKey;
}

/** Item cipher formats are covered by the dedicated cryptography suites. */
function teampassDecryptPasswordValue(...$arguments): string
{
    return DB::$password;
}
