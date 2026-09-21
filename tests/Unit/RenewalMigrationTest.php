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
        $previous = $GLOBALS['db_link'] ?? null;
        $db = new \SQLite3(':memory:');
        $db->enableExceptions(true);
        $GLOBALS['db_link'] = $db;
        try {
            $db->exec("CREATE TABLE custom_items (id INTEGER PRIMARY KEY, label TEXT, created_at TEXT)");
            $db->exec("INSERT INTO custom_items VALUES (1, 'Existing item', '1000')");
            $db->exec('CREATE TABLE custom_lapr_endpoints (id INTEGER PRIMARY KEY, ssh_credential_source INTEGER)');
            $db->exec('INSERT INTO custom_lapr_endpoints VALUES (1, 42), (2, 42), (3, NULL)');
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
            $db->exec('UPDATE custom_items SET renewal_period = 30 WHERE id = 1');
            eval('namespace ' . __NAMESPACE__ . ';' . $migration);
            self::assertSame(30, $db->querySingle('SELECT renewal_period FROM custom_items WHERE id = 1'));
            self::assertSame(1, $db->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_ssh_credential_source'"));
            self::assertSame(3, $db->querySingle('SELECT COUNT(*) FROM custom_lapr_endpoints'));
            // Multiple endpoints may share the same connection credential: this is not a unique index.
            $db->exec('INSERT INTO custom_lapr_endpoints VALUES (4, 42)');
            self::assertSame(3, $db->querySingle('SELECT COUNT(*) FROM custom_lapr_endpoints WHERE ssh_credential_source = 42'));
            $installer = file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
            self::assertStringContainsString('`renewal_period` INT UNSIGNED NOT NULL DEFAULT 0', $installer);
            self::assertStringContainsString('INDEX `idx_ssh_credential_source` (`ssh_credential_source`)', $installer);
            self::assertFileDoesNotExist(__DIR__ . '/../../public/install/upgrade_run_3.2.2.5.php');
        } finally {
            $GLOBALS['db_link'] = $previous;
            $db->close();
        }
    }
}
