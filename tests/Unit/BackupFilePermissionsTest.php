<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      BackupFilePermissionsTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

class BackupFilePermissionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/teampass-backup-permissions-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        $root = realpath($this->root);
        $temporaryRoot = realpath(sys_get_temp_dir());
        if ($root === false || $temporaryRoot === false
            || !str_starts_with($root, $temporaryRoot . DIRECTORY_SEPARATOR . 'teampass-backup-permissions-')) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }

    /** @return iterable<string,array{string,int}> */
    public static function formatsAndMasks(): iterable
    {
        foreach (['plain', 'sql', 'tpbackup'] as $format) {
            foreach ([0000, 0002, 0022, 0027, 0077] as $mask) {
                yield $format . '-' . sprintf('%04o', $mask) => [$format, $mask];
            }
        }
    }

    #[DataProvider('formatsAndMasks')]
    public function testGeneratedBackupsAndMetadataStayProtectedAndRestorable(string $format, int $mask): void
    {
        $probe = $this->runProbe($format, $mask);
        self::assertSame(PHP_OS_FAMILY === 'Windows' ? 0 : $mask, $probe['umask']);
        self::assertCount(3, $probe['results']);
        foreach ($probe['results'] as $result) {
            self::assertTrue($result['success'], $result['message']);
            self::assertTrue($result['roundtrip']);
            self::assertSame('updated', $result['metadata']['comment']);
            if (PHP_OS_FAMILY !== 'Windows') {
                $expectedMode = $mask === 0077 ? 0600 : 0640;
                self::assertSame($format === 'plain' ? 0600 : $expectedMode, $result['mode']);
                self::assertSame($expectedMode, $result['metadata_mode']);
            }
        }
        self::assertCount(3, $probe['plaintext_modes']);
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame([0600, 0600, 0600], $probe['plaintext_modes']);
        }
        if ($format !== 'plain') {
            self::assertCount(3, $probe['encrypted_modes']);
            self::assertNotContains(null, $probe['encrypted_modes'], 'Protect the destination before Defuse writes.');
            if (PHP_OS_FAMILY !== 'Windows') {
                self::assertSame(array_fill(0, 3, $mask === 0077 ? 0600 : 0640), $probe['encrypted_modes']);
            }
        }
        self::assertSame([], glob($this->root . '/*.part'));
        self::assertSame([], glob($this->root . '/tpbackup_tmp_*'));
    }

    /** @return iterable<string,array{string,string}> */
    public static function failureScenarios(): iterable
    {
        foreach (['sql', 'tpbackup'] as $format) {
            foreach (['encrypt', 'protection'] as $failure) {
                yield $format . '-' . $failure => [$format, $failure];
            }
        }
    }

    #[DataProvider('failureScenarios')]
    public function testFailedProtectionOrEncryptionNeverPublishesABackup(string $format, string $failure): void
    {
        $probe = $this->runProbe($format, 0022, $failure);
        foreach ($probe['results'] as $result) {
            self::assertFalse($result['success']);
            self::assertFileDoesNotExist($result['filepath']);
            self::assertStringContainsString($failure === 'encrypt' ? 'Fixture encryption failure' : 'protect', $result['message']);
        }
        if ($failure === 'protection') {
            self::assertSame([], $probe['encrypted_modes'], 'Defuse must not write to an unprotected target.');
        }
        self::assertSame([], glob($this->root . '/*.sql'));
        self::assertSame([], glob($this->root . '/*.tpbackup'));
        self::assertSame([], glob($this->root . '/*.meta.json'));
        self::assertSame([], glob($this->root . '/tpbackup_tmp_*'));
        self::assertSame([], glob($this->root . '/*.sql.part'));
    }

    public function testExclusiveCreationDoesNotTruncateAnExistingFile(): void
    {
        $path = $this->root . '/existing.sql.part';
        file_put_contents($path, 'keep existing contents');
        self::assertFalse(tpBackupOpenProtectedFile($path));
        self::assertSame('keep existing contents', file_get_contents($path));
        self::assertFalse(tpBackupOpenProtectedFile($path, 'wb'));
        self::assertSame('keep existing contents', file_get_contents($path));
        self::assertFalse(tpBackupOpenProtectedFile('php://memory', 'c+b'));
    }

    /** @return iterable<string,array{string,bool}> */
    public static function filesystemResponses(): iterable
    {
        foreach (['noop', 'chmod_false', 'disabled', 'partial', 'stat_failure', 'replacement'] as $scenario) {
            foreach ([false, true] as $existing) {
                yield $scenario . ($existing ? '-existing' : '-new') => [$scenario, $existing];
            }
        }
    }

    #[DataProvider('filesystemResponses')]
    public function testFilesystemCompatibilityAndFailureCleanup(string $scenario, bool $existing): void
    {
        $path = $this->root . '/probe.sql.part';
        $process = new Process([
            PHP_BINARY, __DIR__ . '/../Fixtures/backup_protection_probe.php',
            $path, $scenario, $existing ? 'existing' : 'new',
        ]);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($scenario === 'noop', $result['accepted']);
        self::assertCount($scenario === 'noop' ? 1 : 0, $result['warnings']);
        self::assertSame($scenario === 'disabled' ? 0 : 1, $result['chmod_calls']);
        if ($scenario === 'replacement') {
            self::assertTrue($result['exists']);
            self::assertSame('replacement contents', $result['contents']);
        } elseif ($existing) {
            self::assertTrue($result['exists']);
            self::assertSame('keep existing metadata', $result['contents']);
        } else {
            self::assertSame($scenario === 'noop', $result['exists']);
        }
    }

    public function testUnavailableChmodFailsBeforeWritingOrPublishingPlaintext(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX permissions are required.');
        }
        foreach (['sql', 'tpbackup'] as $format) {
            $probe = $this->runProbe($format, 0022, 'chmod');
            foreach ($probe['results'] as $result) {
                self::assertFalse($result['success']);
                self::assertStringContainsString('protect', $result['message']);
                self::assertFileDoesNotExist($result['filepath']);
            }
            self::assertSame([], $probe['plaintext_modes']);
            self::assertSame([], $probe['encrypted_modes']);
        }
        self::assertSame([], glob($this->root . '/*.part'));
        self::assertSame([], glob($this->root . '/tpbackup_tmp_*'));
    }

    public function testMetadataWriteAndUpsertRefuseNonRegularTargets(): void
    {
        $backup = $this->root . '/backup.tpbackup';
        $meta = tpGetBackupMetadataPath($backup);
        self::assertTrue(mkdir($meta, 0700));
        self::assertFalse(tpWriteBackupMetadata($backup, 'test', '123')['success']);
        self::assertFalse(tpUpsertBackupMetadata($backup, ['comment' => 'new'])['success']);
        self::assertDirectoryExists($meta);
    }

    public function testUnavailableChmodDoesNotOverwriteMetadataOrLeaveANewUnsafeFile(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX permissions are required.');
        }
        $probe = $this->runProbe('metadata', 0022, 'chmod');
        self::assertFalse($probe['write']['success']);
        self::assertFalse($probe['update']['success']);
        self::assertFalse($probe['new']['success']);
        self::assertSame('{"comment":"keep"}', $probe['contents']);
        self::assertFalse($probe['new_exists']);
    }

    public function testMetadataUpdatesKeepOwnerOnlyPermissionsAndExistingFields(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX permissions are required.');
        }
        $backup = $this->root . '/backup.tpbackup';
        $meta = tpGetBackupMetadataPath($backup);
        file_put_contents($meta, '{"comment":"old","custom":"keep"}');
        self::assertTrue(chmod($meta, 0600));
        self::assertTrue(tpUpsertBackupMetadata($backup, ['comment' => 'new'])['success']);
        clearstatcache(true, $meta);
        self::assertSame(0600, fileperms($meta) & 07777);
        self::assertSame(['comment' => 'new', 'custom' => 'keep'], tpReadBackupMetadata($backup));
        self::assertTrue(tpWriteBackupMetadata($backup, 'test', '123')['success']);
        clearstatcache(true, $meta);
        self::assertSame(0600, fileperms($meta) & 07777);
    }

    public function testMetadataUpdatesRestrictExistingPermissiveFilesAndRejectSymlinks(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX permissions and symlinks are required.');
        }
        $backup = $this->root . '/backup.sql';
        $meta = tpGetBackupMetadataPath($backup);
        file_put_contents($meta, '{"comment":"old"}');
        self::assertTrue(chmod($meta, 0666));
        self::assertTrue(tpUpsertBackupMetadata($backup, ['comment' => 'new'])['success']);
        clearstatcache(true, $meta);
        self::assertSame(0640, fileperms($meta) & 07777);

        $linkBackup = $this->root . '/link.sql';
        self::assertTrue(symlink($meta, tpGetBackupMetadataPath($linkBackup)));
        self::assertFalse(tpWriteBackupMetadata($linkBackup, 'test', '123')['success']);
        self::assertFalse(tpUpsertBackupMetadata($linkBackup, ['comment' => 'overwrite'])['success']);
        self::assertSame('new', tpReadBackupMetadata($backup)['comment']);
    }

    private function runProbe(string $format, int $mask, string $failure = ''): array
    {
        if ($format === 'tpbackup' && !class_exists('ZipArchive')) {
            self::markTestSkipped('ZipArchive is required.');
        }
        $process = new Process(array_merge(
            [PHP_BINARY],
            $failure === 'chmod' ? ['-d', 'disable_functions=chmod'] : [],
            [
                __DIR__ . '/../Fixtures/backup_file_permissions.php',
                $this->root,
                $format,
                sprintf('%04o', $mask),
                $failure,
            ]
        ));
        $process->setTimeout(30);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
        $probe = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($probe);
        return $probe;
    }
}
