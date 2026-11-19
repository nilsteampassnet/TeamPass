<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class BackupTemporaryLifecycleTest extends TestCase
{
    public function testRestoreStagingIsPrivateAndRemovedAfterUse(): void
    {
        $path = tpBackupRestoreTempPath('sql');
        self::assertNotSame('', $path);
        $directory = dirname($path);
        try {
            if (PHP_OS_FAMILY !== 'Windows') { self::assertSame(0700, fileperms($directory) & 0777); }
            file_put_contents($path, 'fictitious plaintext');
            self::assertTrue(tpBackupCleanupRestoreFile($path));
            self::assertDirectoryDoesNotExist($directory);
        } finally {
            if (is_dir($directory)) { tpBackupRemoveTemporaryDirectory($directory); }
        }
    }

    public function testSensitiveRemnantPurgePreservesRecentAndUnrelatedFiles(): void
    {
        $directory = sys_get_temp_dir() . '/tp-sensitive-purge-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $names = ['tpbackup_restore_old.sql', 'recovery-123-test.tprecovery', 'tpbackup_restore_recent.zip', 'unrelated.sql'];
        try {
            foreach ($names as $name) { file_put_contents($directory . '/' . $name, 'fictitious'); }
            touch($directory . '/' . $names[0], time() - 172800);
            touch($directory . '/' . $names[1], time() - 172800);
            touch($directory . '/' . $names[3], time() - 172800);
            self::assertSame(['deleted' => 2, 'failed' => 0], tpBackupPurgeSensitiveTemporaryFiles($directory));
            self::assertFileExists($directory . '/' . $names[2]);
            self::assertFileExists($directory . '/' . $names[3]);
            self::assertFalse(tpBackupCleanupRestoreFile($directory . '/' . $names[3]));
        } finally {
            foreach ($names as $name) { if (is_file($directory . '/' . $name)) { unlink($directory . '/' . $name); } }
            rmdir($directory);
        }
    }

    public function testArchivesAndTheirMetadataAreProtectedFromGenericHousekeeping(): void
    {
        foreach (['scheduled-old.sql', 'externalized-old.tpbackup.meta.json', '1794990000-random-sl100.sql',
            '1794990000-random-sl100.tpbackup.meta.json', 'pre-upgrade-1794990000-random.sql'] as $name) {
            self::assertTrue(tpBackupIsPersistentArchiveFilename($name));
        }
        foreach (['random-import.sql', 'image.png', 'tpbackup_restore_old.sql', '../scheduled-old.sql'] as $name) {
            self::assertFalse(tpBackupIsPersistentArchiveFilename($name));
        }
    }

    public function testDirectoryCreationNeverCarriesUnresolvedTraversalSegments(): void
    {
        self::assertSame('', tpBackupResolveDirectoryCreationPath(sys_get_temp_dir() . '/missing/../../outside'));
        self::assertSame('', tpBackupResolveDirectoryCreationPath('php://filter/resource=backup'));
        self::assertSame('', tpBackupRestoreTempPath('../sql'));
    }
}
