<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class BackupScheduledCopyTest extends TestCase
{
    public function testCopiesTheExactEncryptedSnapshotAndRetriesWithoutCreatingAnotherArchive(): void
    {
        $directory = sys_get_temp_dir() . '/tp-scheduled-copy-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        mkdir($directory . '/source', 0700);
        mkdir($directory . '/target', 0700);
        $name = 'scheduled-1794990000-fixture-sl100.sql';
        $source = $directory . '/source/' . $name;
        try {
            file_put_contents($directory . '/plain.sql', "fictitious SQL\nR&D O'Brien literal\\n");
            \Defuse\Crypto\File::encryptFileWithPassword($directory . '/plain.sql', $source, 'fictitious-passphrase');
            tpBackupWriteProtectedFile(tpGetBackupMetadataPath($source), '{"schema_level":"100","source":"scheduled"}');
            $hash = tpBackupFileSha256($source);
            $settings = ['path_to_files_folder' => $directory . '/source'];
            $first = tpBackupCopyScheduledArchive($directory . '/source', $name, $directory . '/target', $hash, $settings);
            $second = tpBackupCopyScheduledArchive($directory . '/source', $name, $directory . '/target', $hash, $settings);
            self::assertSame($first['filepath'], $second['filepath']);
            self::assertSame($hash, tpBackupFileSha256($first['filepath']));
            self::assertCount(1, glob($directory . '/target/*.sql'));
            self::assertSame([], glob($directory . '/target/tpbackup_tmp_*'));
            self::assertSame($name, tpReadBackupMetadata($first['filepath'])['copied_from']);
            \Defuse\Crypto\File::decryptFileWithPassword($first['filepath'], $directory . '/restored.sql', 'fictitious-passphrase');
            self::assertSame(file_get_contents($directory . '/plain.sql'), file_get_contents($directory . '/restored.sql'));
            file_put_contents($source, 'changed source');
            $this->expectExceptionMessage('scheduled backup changed');
            tpBackupCopyScheduledArchive($directory . '/source', $name, $directory . '/target', $hash, $settings);
        } finally {
            $real = realpath($directory);
            $base = realpath(sys_get_temp_dir());
            if ($real !== false && $base !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR . 'tp-scheduled-copy-')) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $entry) { $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
                rmdir($real);
            }
        }
    }
}
