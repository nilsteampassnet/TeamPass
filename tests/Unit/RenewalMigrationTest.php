<?php

declare(strict_types=1);

namespace TeamPass\Tests\RenewalMigration;

use PHPUnit\Framework\TestCase;

/** Adapt only the mysqli transport; execute the production idempotent migration. */
function mysqli_query(\SQLite3 $connection, string $sql): \SQLite3Result|bool
{
    if (preg_match('/^show columns from ([a-z_]+)$/i', $sql, $match)) {
        return $connection->query('PRAGMA table_info(' . $match[1] . ')');
    }
    if (preg_match('/^SHOW INDEX FROM ([a-z_]+) WHERE key_name LIKE "([a-z_]+)"$/', $sql, $match)) {
        return $connection->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = '" . $match[1] . "' AND name = '" . $match[2] . "'");
    }
    if (preg_match('/^ALTER TABLE `([a-z_]+)` ADD INDEX `([a-z_]+)` \(`([a-z_]+)`\)$/', $sql, $match)) {
        return $connection->exec('CREATE INDEX ' . $match[2] . ' ON ' . $match[1] . ' (' . $match[3] . ')');
    }
    return $connection->exec($sql);
}

/** Expose index existence through the production mysqli API. */
function mysqli_fetch_row(\SQLite3Result $result): array|false
{
    return $result->fetchArray(SQLITE3_NUM);
}

/** Expose SQLite column names through mysqli's SHOW COLUMNS field name. */
function mysqli_fetch_assoc(\SQLite3Result $result): array|false
{
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row === false ? false : ['Field' => $row['name']];
}

/** Exercise the migration with a non-default table prefix. */
function prefixTable(string $table): string { return 'custom_' . $table; }

/** Instance key the private key backups are sealed with. */
function getServerSecret(): string { return $GLOBALS['renewal_migration_server_secret']; }

/** Adapt only MeekroDB's transport; the migration's statements and placeholders run as written. */
final class DB
{
    /** @return array<int, array<string, mixed>> */
    public static function query(string $sql, mixed ...$args): array
    {
        $result = self::prepare($sql, $args)->execute();
        $rows = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @param array<string, mixed> $data */
    public static function update(string $table, array $data, string $where, mixed ...$args): void
    {
        $set = implode(', ', array_map(static fn (string $column): string => $column . ' = %s', array_keys($data)));
        self::prepare('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where, [...array_values($data), ...$args])->execute();
    }

    /** @param array<int, mixed> $args */
    private static function prepare(string $sql, array $args): \SQLite3Stmt
    {
        $statement = $GLOBALS['db_link']->prepare((string) preg_replace('/%[is]/', '?', $sql));
        foreach (array_values($args) as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        return $statement;
    }
}

class RenewalMigrationTest extends TestCase
{
    public function testUpgradePreservesExistingItemsAndCanBeReplayed(): void
    {
        if (!extension_loaded('sqlite3')) self::markTestSkipped('SQLite is required.');
        if (!function_exists(__NAMESPACE__ . '\\addColumnIfNotExist')) {
            $root = __DIR__ . '/../..';
            $functions = str_replace("\r\n", "\n", file_get_contents($root . '/public/install/tp.functions.php'));
            foreach (['addColumnIfNotExist', 'checkIndexExist'] as $name) {
                $start = strpos($functions, 'function ' . $name . '(');
                $end = strpos($functions, "\n}", $start) + 2;
                eval('namespace ' . __NAMESPACE__ . ';' . substr($functions, $start, $end - $start));
            }
        }
        require_once __DIR__ . '/../../app/sources/private_key_backup_logic.php';
        $instanceKey = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();
        $GLOBALS['renewal_migration_server_secret'] = $instanceKey;
        $legacyValue = 'q3J+L0m9vYw2Xh8bT1sR/4uKcZ0eN5pW7aD6gF2iH9jE1kQ3oU8yV4xC5tB0nM=';
        $alreadySealed = \privateKeyBackupSeal('bGVnYWN5', $instanceKey);
        $previous = $GLOBALS['db_link'] ?? null;
        $db = new \SQLite3(':memory:');
        $db->enableExceptions(true);
        $GLOBALS['db_link'] = $db;
        try {
            $db->exec("CREATE TABLE custom_items (id INTEGER PRIMARY KEY, label TEXT, created_at TEXT)");
            $db->exec("INSERT INTO custom_items VALUES (1, 'Existing item', '1000')");
            $db->exec('CREATE TABLE custom_lapr_endpoints (id INTEGER PRIMARY KEY, ssh_credential_source INTEGER)');
            $db->exec('INSERT INTO custom_lapr_endpoints VALUES (1, 42), (2, 42), (3, NULL)');
            $db->exec('CREATE TABLE custom_roles_title (id INTEGER PRIMARY KEY, title TEXT)');
            $db->exec("INSERT INTO custom_roles_title VALUES (1, 'Existing role')");
            $db->exec('CREATE TABLE custom_nested_tree (id INTEGER PRIMARY KEY, title TEXT)');
            $db->exec("INSERT INTO custom_nested_tree VALUES (1, 'Existing folder')");
            $db->exec('CREATE TABLE custom_users (id INTEGER PRIMARY KEY, private_key_backup TEXT)');
            $db->exec("INSERT INTO custom_users VALUES (1, '" . $legacyValue . "'), (2, '" . $alreadySealed . "'), (3, NULL), (4, '')");
            $chain = file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.2.php');
            $start = strpos($chain, '// Individual item renewal policies');
            $end = strpos($chain, '// Save upgrade timestamp');
            self::assertNotFalse($start);
            self::assertNotFalse($end);
            self::assertLessThan($end, $start);
            // Execute the actual inline upgrade statements before the schema floor is recorded.
            $migration = substr($chain, $start, $end - $start);
            eval('namespace ' . __NAMESPACE__ . ';' . $migration);
            self::assertSame(['id' => 1, 'label' => 'Existing item', 'created_at' => '1000', 'renewal_period' => 0],
                $db->querySingle('SELECT * FROM custom_items', true));
            // Existing roles keep the Security posture "Fix" shortcuts visible.
            self::assertSame(1, $db->querySingle('SELECT allow_security_posture_fix FROM custom_roles_title WHERE id = 1'));
            self::assertSame(
                ['id' => 1, 'title' => 'Existing folder', 'deletion_protected' => 0],
                $db->querySingle('SELECT * FROM custom_nested_tree', true)
            );
            // Legacy recovery backups are sealed with the instance key without being opened (GHSA-fv78-jwjv-pj25).
            $sealed = $db->querySingle('SELECT private_key_backup FROM custom_users WHERE id = 1');
            self::assertTrue(\privateKeyBackupIsSealed($sealed));
            self::assertSame($legacyValue, \privateKeyBackupUnseal($sealed, $instanceKey));
            self::assertSame($alreadySealed, $db->querySingle('SELECT private_key_backup FROM custom_users WHERE id = 2'));
            self::assertNull($db->querySingle('SELECT private_key_backup FROM custom_users WHERE id = 3'));
            self::assertSame('', $db->querySingle('SELECT private_key_backup FROM custom_users WHERE id = 4'));
            $db->exec('UPDATE custom_items SET renewal_period = 30 WHERE id = 1');
            $db->exec('UPDATE custom_roles_title SET allow_security_posture_fix = 0 WHERE id = 1');
            $db->exec('UPDATE custom_nested_tree SET deletion_protected = 1 WHERE id = 1');
            eval('namespace ' . __NAMESPACE__ . ';' . $migration);
            self::assertSame(30, $db->querySingle('SELECT renewal_period FROM custom_items WHERE id = 1'));
            self::assertSame(0, $db->querySingle('SELECT allow_security_posture_fix FROM custom_roles_title WHERE id = 1'));
            self::assertSame(1, $db->querySingle('SELECT deletion_protected FROM custom_nested_tree WHERE id = 1'));
            // Sealing is randomized: an unchanged value proves the replay skipped the sealed rows.
            self::assertSame($sealed, $db->querySingle('SELECT private_key_backup FROM custom_users WHERE id = 1'));
            self::assertSame($alreadySealed, $db->querySingle('SELECT private_key_backup FROM custom_users WHERE id = 2'));
            self::assertSame(1, $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_ssh_credential_source'"));
            self::assertSame(3, $db->querySingle('SELECT COUNT(*) FROM custom_lapr_endpoints'));
            // Multiple endpoints may share the same connection credential: this is not a unique index.
            $db->exec('INSERT INTO custom_lapr_endpoints VALUES (4, 42)');
            self::assertSame(3, $db->querySingle('SELECT COUNT(*) FROM custom_lapr_endpoints WHERE ssh_credential_source = 42'));
            $installer = file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
            self::assertStringContainsString('`renewal_period` INT UNSIGNED NOT NULL DEFAULT 0', $installer);
            self::assertStringContainsString('INDEX `idx_ssh_credential_source` (`ssh_credential_source`)', $installer);
            self::assertStringContainsString("`allow_security_posture_fix` TINYINT(1) NOT NULL DEFAULT '1'", $installer);
            self::assertFileDoesNotExist(__DIR__ . '/../../public/install/upgrade_run_3.2.2.5.php');
        } finally {
            $GLOBALS['db_link'] = $previous;
            unset($GLOBALS['renewal_migration_server_secret']);
            $db->close();
        }
    }
}
