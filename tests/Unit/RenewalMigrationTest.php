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
    return $connection->exec($sql);
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
        if (!function_exists(__NAMESPACE__ . '\\upgradeItemRenewalPolicy')) {
            $root = __DIR__ . '/../..';
            $functions = str_replace("\r\n", "\n", file_get_contents($root . '/public/install/tp.functions.php'));
            $start = strpos($functions, 'function addColumnIfNotExist(');
            $end = strpos($functions, "\n}", $start) + 2;
            eval('namespace ' . __NAMESPACE__ . ';' . substr($functions, $start, $end - $start));
            $migration = file_get_contents($root . '/public/install/upgrade_run_3.2.2.5.php');
            eval('namespace ' . __NAMESPACE__ . ';' . substr($migration, strpos($migration, 'function upgradeItemRenewalPolicy(')));
        }
        $previous = $GLOBALS['db_link'] ?? null;
        $db = new \SQLite3(':memory:');
        $db->enableExceptions(true);
        $GLOBALS['db_link'] = $db;
        try {
            $db->exec("CREATE TABLE custom_items (id INTEGER PRIMARY KEY, label TEXT, created_at TEXT)");
            $db->exec("INSERT INTO custom_items VALUES (1, 'Existing item', '1000')");
            self::assertTrue(upgradeItemRenewalPolicy());
            self::assertSame(['id' => 1, 'label' => 'Existing item', 'created_at' => '1000', 'renewal_period' => 0],
                $db->querySingle('SELECT * FROM custom_items', true));
            $db->exec('UPDATE custom_items SET renewal_period = 30 WHERE id = 1');
            self::assertTrue(upgradeItemRenewalPolicy());
            self::assertSame(30, $db->querySingle('SELECT renewal_period FROM custom_items WHERE id = 1'));
            $installer = file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
            self::assertStringContainsString('`renewal_period` INT UNSIGNED NOT NULL DEFAULT 0', $installer);
            $chain = file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.2.php');
            self::assertLessThan(strpos($chain, '// Save upgrade timestamp'), strpos($chain, 'upgradeItemRenewalPolicy()'));
        } finally {
            $GLOBALS['db_link'] = $previous;
            $db->close();
        }
    }
}
