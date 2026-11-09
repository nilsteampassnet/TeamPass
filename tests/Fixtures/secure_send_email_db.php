<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/** Execute the production queries against an isolated transactional SQL database. */
final class DB
{
    public static SQLite3 $connection;
    public static string $failTable = '';
    public static bool $access = true;
    public static bool $inTransaction = false;
    public static int $wakeups = 0;
    public static string $instanceKey;

    /** Bind the MeekroDB placeholders used by these helpers; adapt row locks for SQLite only. */
    public static function query(string $sql, mixed ...$parameters): array
    {
        $sql = str_replace(' FOR UPDATE', '', $sql);
        $index = 0;
        $sql = preg_replace_callback('/%li|%i|%s/', static function ($match) use ($parameters, &$index): string {
            $value = $parameters[$index++];
            return match ($match[0]) {
                '%i' => (string) (int) $value,
                '%li' => '(' . implode(',', array_map('intval', $value)) . ')',
                default => "'" . SQLite3::escapeString((string) $value) . "'",
            };
        }, $sql);
        $result = self::$connection->query($sql);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $rows[] = $row; }
        return $rows;
    }

    /** Read one row through the same binding boundary. */
    public static function queryFirstRow(string $sql, mixed ...$parameters): ?array
    {
        return self::query($sql, ...$parameters)[0] ?? null;
    }

    /** Configuration overrides are disabled; quota queries execute against SQL. */
    public static function queryFirstField(string $sql, mixed ...$parameters): mixed
    {
        if (str_contains($sql, 'email_test_misc')) { return 0; }
        $row = self::queryFirstRow($sql, ...$parameters);
        return $row ? reset($row) : null;
    }

    /** Read a bounded set of metadata identifiers. */
    public static function queryFirstColumn(string $sql, mixed ...$parameters): array
    {
        return array_map(static fn ($row) => reset($row), self::query($sql, ...$parameters));
    }

    /** The audit body is opaque to this fixture but participates in its actual transaction. */
    public static function insert(string $table, array $row): void
    {
        if (self::$failTable === $table) { throw new RuntimeException('private-failure-canary'); }
        if ($table === prefixTable('secure_send_audit')) { $row = ['record' => json_encode($row)]; }
        $columns = implode(',', array_keys($row));
        $values = implode(',', array_map(static fn ($value) => $value === null ? 'NULL' : "'" . SQLite3::escapeString((string) $value) . "'", $row));
        self::$connection->exec('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $values . ')');
    }

    /** Return the actual auto-generated identifier. */
    public static function insertId(): int { return self::$connection->lastInsertRowID(); }
    /** Delete metadata using the production predicate. */
    public static function delete(string $table, string $where, mixed ...$parameters): void
    {
        self::query('DELETE FROM ' . $table . ' WHERE ' . $where, ...$parameters);
    }
    /** Begin a real rollback boundary. */
    public static function startTransaction(): void { self::$connection->exec('BEGIN'); self::$inTransaction = true; }
    /** Publish the link, evidence and job together. */
    public static function commit(): void { self::$connection->exec('COMMIT'); self::$inTransaction = false; }
    /** Roll back every functional insert. */
    public static function rollback(): void { self::$connection->exec('ROLLBACK'); self::$inTransaction = false; }
}

/** Isolate table names from any installed application. */
function prefixTable(string $name): string { return 'email_test_' . $name; }
/** Access resolution itself has dedicated regression suites. */
function securityPostureItemAccessSql(int $userId, string $alias): string { return DB::$access ? '(1 = 1)' : '(1 = 0)'; }
/** Assert that waking an independent worker cannot happen before the commit. */
function triggerBackgroundHandler(): void
{
    if (DB::$inTransaction) { throw new LogicException('Worker started before commit'); }
    ++DB::$wakeups;
}
/** Real Defuse authenticated encryption, using a random test-only instance key. */
function cryption(string $value, string $key, string $operation, ?array $settings = []): array
{
    $cipherKey = \Defuse\Crypto\Key::loadFromAsciiSafeString($key === '' ? DB::$instanceKey : $key);
    return ['error' => false, 'string' => $operation === 'encrypt'
        ? \Defuse\Crypto\Crypto::encrypt($value, $cipherKey) : \Defuse\Crypto\Crypto::decrypt($value, $cipherKey)];
}

/** Exercise actual creation crypto with random test-only link credentials. */
function GenerateCryptKey(int $length, bool ...$options): string { return substr(bin2hex(random_bytes($length)), 0, $length); }
/** Wrap a real payload key with the sender's selected phrase. */
function defuse_generate_personal_key(string $password): string
{
    return \Defuse\Crypto\KeyProtectedByPassword::createRandomPasswordProtectedKey($password)->saveToAsciiSafeString();
}
/** Unlock the real test payload key before encryption. */
function defuse_validate_personal_key(string $password, string $protected): string
{
    return \Defuse\Crypto\KeyProtectedByPassword::loadFromAsciiSafeString($protected)->unlockKey($password)->saveToAsciiSafeString();
}
/** The exchange transport is outside the authenticated handler's business decisions. */
function prepareExchangedData(string $data, string $direction): array { return json_decode($data, true, 512, JSON_THROW_ON_ERROR); }

// Exercise the actual catalog/subject resolver without starting an installed application.
$source = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../../app/sources/main.functions.php'));
foreach (['emailTemplatesCatalog', 'getEmailTemplateSubject'] as $name) {
    $start = strpos($source, 'function ' . $name . '(');
    $end = strpos($source, "\n}\n", $start) + 2;
    eval(substr($source, $start, $end - $start));
}
