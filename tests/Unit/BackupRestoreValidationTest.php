<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class BackupRestoreValidationTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'tp_restore_validation_');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    private function dump(string $schema = '100', string $suffix = ''): string
    {
        $sql = '';
        foreach (['misc' => "`type` TEXT,\n`intitule` TEXT,\n`valeur` TEXT",
            'users' => "`id` INT,\n`pw` TEXT", 'items' => '`id` INT', 'nested_tree' => '`id` INT',
            'sharekeys_items' => '`id` INT', 'user_private_keys' => '`id` INT'] as $table => $columns) {
            $sql .= "DROP TABLE IF EXISTS `fixture_$table`;\nCREATE TABLE `fixture_$table` (\n$columns\n) ENGINE=InnoDB;\n";
            if ($table === 'misc') {
                $sql .= 'INSERT INTO `fixture_misc` VALUES("admin","teampass_version_full","3.2.4.2");' . "\n"
                    . 'INSERT INTO `fixture_misc` VALUES("admin","teampass_schema_level","' . $schema . '");' . "\n";
            } elseif ($table === 'users') {
                $sql .= 'INSERT INTO `fixture_users` VALUES("99","fictitious-master-proof");' . "\n";
            }
        }
        return $sql . $suffix;
    }

    public function testCompleteDumpIsCheckedBeforeImportAndPatchVersionsShareTheSchemaBoundary(): void
    {
        file_put_contents($this->path, $this->dump());
        $inventory = tpBackupInspectRestoreSql($this->path, 'fixture_', '100', '3.2.4.3', [],
            static fn (string $proof): bool => $proof === 'fictitious-master-proof', 99);
        self::assertCount(6, $inventory['tables']);
        self::assertSame(2, $inventory['rows']['fixture_misc']);
        self::assertArrayNotHasKey('pw', $inventory);
    }

    public function testTruncatedForeignAndIncompatibleInputsAreRefused(): void
    {
        foreach ([[$this->dump('100', 'INSERT INTO `fixture_items` VALUES("1")'), [], 'SQL_TRUNCATED'],
            [$this->dump('100', 'DROP TABLE IF EXISTS `foreign_items`;'), [], 'SQL_SCOPE_INVALID'],
            [$this->dump('101'), [], 'SCHEMA_MISMATCH'],
            [$this->dump(), ['teampass' => ['schema_level' => '99']], 'SCHEMA_MISMATCH']] as [$sql, $manifest, $reason]) {
            file_put_contents($this->path, $sql);
            try {
                tpBackupInspectRestoreSql($this->path, 'fixture_', '100', '3.2.4.3', $manifest);
                self::fail('Invalid restore accepted: ' . $reason);
            } catch (TeamPassRecoveryException $exception) {
                self::assertSame($reason, $exception->reason);
            }
        }
    }

    public function testAnArchiveFromAnotherMasterKeyIsRefused(): void
    {
        file_put_contents($this->path, $this->dump());
        $this->expectExceptionMessage('MASTER_KEY_DATABASE_MISMATCH');
        tpBackupInspectRestoreSql($this->path, 'fixture_', '100', '3.2.4.3', [], static fn (): bool => false, 99);
    }

    public function testLastMigrationReleaseMayDifferFromTheBackupFilesWithTheSameSchema(): void
    {
        file_put_contents($this->path, str_replace('3.2.4.2', '3.2.2.7', $this->dump()));
        $inventory = tpBackupInspectRestoreSql($this->path, 'fixture_', '100', '3.2.4.3',
            ['teampass' => ['schema_level' => '100', 'files_version' => '3.2.4.3']]);
        self::assertCount(6, $inventory['tables']);
    }

    public function testReplacingAnAuthorizedFileWithoutChangingItsPathOrSizeIsRefused(): void
    {
        file_put_contents($this->path, 'first archive');
        $hash = tpBackupFileSha256($this->path);
        tpBackupAssertRestoreFileUnchanged($this->path, $hash);
        file_put_contents($this->path, 'other archive');
        $this->expectExceptionMessage('RESTORE_INPUT_CHANGED');
        tpBackupAssertRestoreFileUnchanged($this->path, $hash);
    }

    public function testLiteralBytesSurviveTheDumpAndRestoreDialect(): void
    {
        $bytes = implode('', array_map('chr', range(0, 255)));
        $sql = 'INSERT INTO `fixture_items` VALUES("' . tpBackupEscapeSqlString($bytes) . '",NULL,"literal\\\\n");';
        self::assertSame([$bytes, null, 'literal\\n'], tpBackupSqlValues($sql));
        $mode = tpBackupRestoreSqlMode('ANSI_QUOTES,NO_BACKSLASH_ESCAPES,ONLY_FULL_GROUP_BY');
        self::assertStringNotContainsString('ANSI_QUOTES', $mode);
        self::assertStringNotContainsString('NO_BACKSLASH_ESCAPES', $mode);
        self::assertStringContainsString('ONLY_FULL_GROUP_BY', $mode);
        self::assertStringContainsString('NO_AUTO_VALUE_ON_ZERO', $mode);
    }
}
