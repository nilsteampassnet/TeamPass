<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * TeamPass is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See <https://www.gnu.org/licenses/> for the GNU General Public License.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

namespace TeamPass\Tests\SecureSendRetentionMigration;

use PHPUnit\Framework\TestCase;

/** Adapt mysqli transport only; the MariaDB harness runs the original SQL too. */
function mysqli_query(\SQLite3 $connection, string $sql): \SQLite3Result|bool
{
    if (preg_match('/^SHOW INDEX FROM (\w+) WHERE key_name LIKE "(\w+)"$/', $sql, $match)) {
        return $connection->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = '" . $match[1] . "' AND name = '" . $match[2] . "'");
    }
    if (preg_match('/^ALTER TABLE `(\w+)` ADD INDEX `(\w+)` \(`occurred_at`, `id`\)$/', $sql, $match)) {
        return $connection->exec('CREATE INDEX ' . $match[2] . ' ON ' . $match[1] . ' (occurred_at, id)');
    }
    return $connection->exec(str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql));
}

/** Expose SQLite index existence through the installer's mysqli API. */
function mysqli_fetch_row(\SQLite3Result $result): array|false { return $result->fetchArray(SQLITE3_NUM); }

/** Isolate the migration from live installation settings. */
function prefixTable(string $table): string { return 'retention_migration_' . $table; }

/** Observe cache invalidation without live APCu. */
class ConfigManager
{
    public static int $invalidations = 0;
    /** Count completed migration cache invalidations. */
    public static function invalidateCache(): void { ++self::$invalidations; }
}

class SecureSendRetentionMigrationTest extends TestCase
{
    public function testActualMigrationCanBeReplayedWithoutChangingPolicyOrEvidence(): void
    {
        if (!extension_loaded('sqlite3')) {
            self::markTestSkipped('SQLite3 is required.');
        }
        if (!function_exists(__NAMESPACE__ . '\\checkIndexExist')) {
            $functions = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../public/install/tp.functions.php'));
            $start = strpos($functions, 'function checkIndexExist(');
            $end = strpos($functions, "\n}", $start) + 2;
            eval('namespace ' . __NAMESPACE__ . ';' . substr($functions, $start, $end - $start));
        }
        $source = (string) file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.3.php');
        $start = strpos($source, '// Secure Send retention migration:');
        $end = strpos($source, '// End Secure Send retention migration.', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        self::assertLessThan(strpos($source, '// Save upgrade timestamp'), $end);
        $migration = substr($source, $start, $end - $start);
        $previous = $GLOBALS['db_link'] ?? null;
        $db_link = new \SQLite3(':memory:');
        $db_link->enableExceptions(true);
        $GLOBALS['db_link'] = $db_link;
        $pre = prefixTable('');
        try {
            $db_link->exec('CREATE TABLE ' . prefixTable('misc') . ' (type TEXT, intitule TEXT, valeur TEXT, UNIQUE (type, intitule))');
            $db_link->exec('CREATE TABLE ' . prefixTable('secure_send_audit') . ' (id INTEGER PRIMARY KEY, occurred_at INTEGER)');
            $db_link->exec('INSERT INTO ' . prefixTable('secure_send_audit') . ' VALUES (1, 100)');
            eval('namespace ' . __NAMESPACE__ . ';' . $migration);
            self::assertSame('0', $db_link->querySingle('SELECT valeur FROM ' . prefixTable('misc')));
            $db_link->exec("UPDATE " . prefixTable('misc') . " SET valeur = '30'");
            eval('namespace ' . __NAMESPACE__ . ';' . $migration);
            self::assertSame('30', $db_link->querySingle('SELECT valeur FROM ' . prefixTable('misc')));
            self::assertSame(1, $db_link->querySingle('SELECT COUNT(*) FROM ' . prefixTable('misc')));
            self::assertSame(1, $db_link->querySingle('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit')));
            self::assertSame(1, $db_link->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_retention_period'"));
            self::assertSame(2, ConfigManager::$invalidations);
        } finally {
            $GLOBALS['db_link'] = $previous;
            $db_link->close();
        }
        $install = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
        self::assertStringContainsString("array('admin', 'secure_send_audit_retention_days', '0')", $install);
        require_once __DIR__ . '/../../app/config/include.php';
        // Instances upgraded to the merged audit foundation must also run retention migration.
        self::assertGreaterThan(1791525735, (int) UPGRADE_MIN_DATE);
        self::assertStringNotContainsString('secure_send_audit_retention_days',
            (string) file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.2.php'));
    }
}
