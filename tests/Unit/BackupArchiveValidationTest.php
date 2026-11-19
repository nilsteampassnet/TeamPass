<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class BackupArchiveValidationTest extends TestCase
{
    public function testMissingChecksumsAndAmbiguousArchiveNamesAreRejected(): void
    {
        if (!class_exists(ZipArchive::class)) { self::markTestSkipped('ZipArchive required.'); }
        $directory = sys_get_temp_dir() . '/tp-archive-validation-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            foreach (['valid', 'missing_hash', 'duplicate'] as $scenario) {
                $zip = new ZipArchive();
                $zip->open($directory . '/fixture.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $dump = 'INSERT INTO `fixture_items` VALUES("fictitious");';
                $manifest = ['backup_format' => tpBackupGetPackageFormatId(), 'backup_format_version' => 1,
                    'backup_type' => 'database_only', 'database' => ['path' => 'database/dump.sql']];
                if ($scenario !== 'missing_hash') { $manifest['database']['sha256'] = hash('sha256', $dump); }
                $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
                $zip->addFromString('database/dump.sql', $dump);
                if ($scenario === 'duplicate') { $zip->addFromString('DATABASE/DUMP.SQL', $dump); }
                $zip->close();
                $result = tpBackupValidatePackageZipAndExtractSql($directory . '/fixture.zip', $directory . '/out.sql');
                self::assertSame($scenario === 'valid', $result['success']);
                if ($scenario !== 'valid') {
                    self::assertSame($scenario === 'duplicate' ? 'ARCHIVE_DUPLICATE_ENTRY' : 'DATABASE_CHECKSUM_INVALID', $result['error_code']);
                    self::assertFileDoesNotExist($directory . '/out.sql');
                } else { unlink($directory . '/out.sql'); }
            }
        } finally {
            foreach (['fixture.zip', 'out.sql'] as $file) { if (is_file($directory . '/' . $file)) { unlink($directory . '/' . $file); } }
            rmdir($directory);
        }
    }

    public function testPublicAvatarsCannotRestoreExecutableFilesOrServerConfiguration(): void
    {
        foreach (['payload.php', '.htaccess', 'payload.phtml'] as $name) {
            $result = tpBackupResolveDocumentRestoreTarget(['kind' => 'user_avatar', 'stored_name' => $name,
                'package_path' => 'documents/user_avatars/' . $name], [], false);
            self::assertFalse($result['success']);
            self::assertSame('DOCUMENT_TARGET_INVALID', $result['error_code']);
        }
    }
}
