<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoverySqlTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recovery_sql.php';

final class RecoverySqlTest extends TestCase
{
    public function testQuotedSemicolonsNewlinesAndEntityNamesArePreserved(): void
    {
        $sql = "INSERT INTO `custom_items` VALUES(\"R&amp;D; O'Brien \\\"<test>\\\"\\n\",NULL);\n";
        $path = tempnam(sys_get_temp_dir(), 'tp-sql-');
        file_put_contents($path, $sql);
        try {
            $statements = iterator_to_array(tpRecoverySqlStatements($path));
            self::assertSame([trim($sql)], $statements);
            self::assertSame('insert', tpRecoverySqlStatementInfo($statements[0], 'custom_')['type']);
        } finally {
            unlink($path);
        }
    }

    /** @return array<string,array{string}> */
    public static function unsafeSql(): array
    {
        return array_map(static fn ($sql) => [$sql], [
            'DROP DATABASE production;', 'CREATE USER admin;',
            'INSERT INTO `other_items` VALUES("secret");',
            'INSERT INTO `custom_items` VALUES(LOAD_FILE("/etc/passwd"));',
            'CREATE TABLE `custom_items` (id int) ENGINE=FEDERATED;',
            'CREATE TABLE `custom_items` (id int) ENGINE=InnoDB DATA DIRECTORY="/tmp";',
            'CREATE TABLE `custom_items` (id int) ENGINE=InnoDB AS SELECT 1;',
        ]);
    }

    #[DataProvider('unsafeSql')]
    public function testRefusesStatementsOutsideTheTeamPassDumpDialect(string $sql): void
    {
        $this->expectException(TeamPassRecoveryException::class);
        tpRecoverySqlStatementInfo($sql, 'custom_');
    }

    public function testRejectsATruncatedFinalStatement(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tp-sql-');
        file_put_contents($path, 'INSERT INTO `custom_items` VALUES("unfinished');
        try {
            $this->expectExceptionMessage('SQL_TRUNCATED');
            iterator_to_array(tpRecoverySqlStatements($path));
        } finally {
            unlink($path);
        }
    }

    public function testAcceptsLargeBlobRowsWithoutDependingOnThePcreJitStack(): void
    {
        $sql = 'INSERT INTO `custom_files` VALUES("' . str_repeat('a', 2097152) . '",NULL);';
        self::assertSame('insert', tpRecoverySqlStatementInfo($sql, 'custom_')['type']);
    }
}
