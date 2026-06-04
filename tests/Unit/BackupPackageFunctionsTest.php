<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      BackupPackageFunctionsTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

class BackupPackageFunctionsTest extends TestCase
{
    public function testRecognizesTpbackupFilenameCaseInsensitively(): void
    {
        $this->assertTrue(tpBackupIsPackageFilename('scheduled-123.TPBACKUP'));
        $this->assertFalse(tpBackupIsPackageFilename('scheduled-123.sql'));
    }

    public function testRequestedFormatDefaultsToSql(): void
    {
        $this->assertSame('sql', tpBackupNormalizeRequestedFormat(''));
        $this->assertSame('sql', tpBackupNormalizeRequestedFormat('unknown'));
        $this->assertSame('tpbackup', tpBackupNormalizeRequestedFormat('.TPBACKUP'));
    }

    public function testAbsolutePathDetectionSupportsCommonPlatforms(): void
    {
        $this->assertTrue(tpBackupPathLooksAbsolute('/mnt/backups/teampass'));
        $this->assertTrue(tpBackupPathLooksAbsolute('D:\\TeampassBackups'));
        $this->assertTrue(tpBackupPathLooksAbsolute('\\\\server\\share\\teampass'));
        $this->assertFalse(tpBackupPathLooksAbsolute('storage/backups'));
        $this->assertFalse(tpBackupPathLooksAbsolute(''));
    }

    public function testExternalizedBackupFilenameGuard(): void
    {
        $this->assertTrue(tpBackupExternalizedBackupFilenameIsAllowed('externalized-20260604.sql'));
        $this->assertTrue(tpBackupExternalizedBackupFilenameIsAllowed('externalized-20260604.tpbackup'));
        $this->assertFalse(tpBackupExternalizedBackupFilenameIsAllowed('scheduled-20260604.sql'));
        $this->assertFalse(tpBackupExternalizedBackupFilenameIsAllowed('../externalized-20260604.sql'));
        $this->assertFalse(tpBackupExternalizedBackupFilenameIsAllowed('externalized-20260604.zip'));
    }

    public function testSftpDestinationIsSupportedForFileOperations(): void
    {
        $this->assertTrue(tpBackupExternalizedDestinationTypeIsSupported('sftp'));
        $this->assertTrue(tpBackupExternalizedDestinationTypeSupportsFileOperations('sftp'));
        $this->assertTrue(tpBackupExternalizedDestinationTypeSupportsFileOperations('local_directory'));
        $this->assertFalse(tpBackupExternalizedDestinationTypeSupportsFileOperations('unknown'));
    }

    public function testSftpRemotePathNormalization(): void
    {
        $this->assertSame('/backups/teampass', tpBackupNormalizeExternalizedSftpRemotePath('/backups//teampass/'));
        $this->assertSame('', tpBackupNormalizeExternalizedSftpRemotePath('relative/path'));
        $this->assertSame('', tpBackupNormalizeExternalizedSftpRemotePath('/backups/../secret'));
    }

    public function testSftpRemoteFilePathKeepsBackupsInsideRemoteDirectory(): void
    {
        $this->assertSame(
            '/backups/teampass/externalized-20260604.sql',
            tpBackupExternalizedSftpRemoteFilePath('/backups/teampass/', 'externalized-20260604.sql')
        );
        $this->assertSame(
            '/backups/teampass/externalized-20260604.tpbackup',
            tpBackupExternalizedSftpRemoteFilePath('/backups/teampass', '../externalized-20260604.tpbackup')
        );
        $this->assertSame('', tpBackupExternalizedSftpRemoteFilePath('/backups/teampass', 'scheduled-20260604.sql'));
        $this->assertSame('', tpBackupExternalizedSftpRemoteFilePath('relative/path', 'externalized-20260604.sql'));
    }

    public function testExternalizedLocalProviderListsAndDeletesBackups(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp_ext_provider_' . bin2hex(random_bytes(4));
        $this->assertTrue(mkdir($dir));

        $first = $dir . DIRECTORY_SEPARATOR . 'externalized-first.sql';
        $second = $dir . DIRECTORY_SEPARATOR . 'externalized-second.tpbackup';
        $ignored = $dir . DIRECTORY_SEPARATOR . 'scheduled-ignored.sql';
        file_put_contents($first, 'sql');
        file_put_contents($second, 'tpbackup');
        file_put_contents($ignored, 'ignored');
        file_put_contents(tpGetBackupMetadataPath($first), '{}');

        try {
            $listed = tpBackupListExternalizedBackups('local_directory', $dir);
            $this->assertTrue($listed['success']);
            $this->assertSame(realpath($dir), $listed['path']);
            $this->assertCount(2, $listed['files']);
            $names = array_column($listed['files'], 'name');
            sort($names);
            $this->assertSame(
                ['externalized-first.sql', 'externalized-second.tpbackup'],
                $names
            );

            $staged = tpBackupStageExternalizedBackupForRestore('local_directory', $dir, 'externalized-second.tpbackup');
            $this->assertTrue($staged['success']);
            $this->assertFalse($staged['cleanup_required']);
            $this->assertSame(realpath($second), $staged['path']);

            $deleted = tpBackupDeleteExternalizedBackup('local_directory', $dir, 'externalized-first.sql');
            $this->assertTrue($deleted['success']);
            $this->assertTrue($deleted['deleted']);
            $this->assertFileDoesNotExist($first);
            $this->assertFileDoesNotExist(tpGetBackupMetadataPath($first));
            $this->assertFileExists($ignored);
        } finally {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testPackageEntryPathNormalizesSafeRelativePaths(): void
    {
        $this->assertSame('database/dump.sql', tpBackupNormalizePackageEntryPath('database\\./dump.sql'));
        $this->assertSame('documents/item/file.bin', tpBackupNormalizePackageEntryPath('documents//item//file.bin'));
    }

    public function testPackageEntryPathRejectsUnsafePaths(): void
    {
        $this->assertSame('', tpBackupNormalizePackageEntryPath('../dump.sql'));
        $this->assertSame('', tpBackupNormalizePackageEntryPath('/database/dump.sql'));
        $this->assertSame('', tpBackupNormalizePackageEntryPath('C:/temp/dump.sql'));
        $this->assertSame('', tpBackupNormalizePackageEntryPath('database/..'));
        $this->assertSame('', tpBackupNormalizePackageEntryPath('database/file:stream'));
    }

    public function testStorageBasenameAcceptsOnlySimpleStoredFilenames(): void
    {
        $this->assertSame('EncryptedFile_abc.def', tpBackupSafeStorageBasename('EncryptedFile_abc.def'));
        $this->assertSame('', tpBackupSafeStorageBasename('../secret'));
        $this->assertSame('', tpBackupSafeStorageBasename('..'));
        $this->assertSame('', tpBackupSafeStorageBasename('bad:name'));
    }

    public function testCanonicalJsonSortsAssociativeKeysRecursively(): void
    {
        $json = tpBackupCanonicalJson([
            'z' => 1,
            'a' => [
                'd' => 4,
                'b' => 2,
            ],
        ]);

        $this->assertNotSame('', $json);
        $aPos = strpos($json, '"a"');
        $zPos = strpos($json, '"z"');
        $bPos = strpos($json, '"b"');
        $dPos = strpos($json, '"d"');
        $this->assertIsInt($aPos);
        $this->assertIsInt($zPos);
        $this->assertIsInt($bPos);
        $this->assertIsInt($dPos);
        $this->assertLessThan($zPos, $aPos);
        $this->assertLessThan($dPos, $bPos);
        $this->assertSame(
            ['a' => ['b' => 2, 'd' => 4], 'z' => 1],
            json_decode($json, true)
        );
    }

    public function testPackageMetadataKeepsPublicFormatFields(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'tpbackup_meta_');
        if ($tmpFile === false) {
            $this->fail('Unable to create temporary metadata test file.');
        }
        file_put_contents($tmpFile, 'encrypted package bytes');

        try {
            $manifestJson = tpBackupCanonicalJson([
                'backup_format' => tpBackupGetPackageFormatId(),
                'backup_format_version' => tpBackupGetPackageFormatVersion(),
                'backup_type' => 'database_only',
                'created_at' => '2026-01-01T00:00:00+00:00',
                'documents' => ['included' => false],
                'source' => 'unit',
                'teampass' => [
                    'files_version' => '3.2.0.1',
                    'schema_level' => '1780331401',
                ],
                'warnings' => ['DOCUMENTS_NOT_INCLUDED'],
            ]);

            $metadata = tpBackupBuildPackagePublicMetadata(
                [
                    'backup_type' => 'database_only',
                    'created_at' => '2026-01-01T00:00:00+00:00',
                    'documents' => ['included' => false],
                    'document_entries' => [
                        [
                            'kind' => 'item_attachment',
                            'package_path' => 'documents/item_attachments/EncryptedFile_secret',
                        ],
                    ],
                    'source' => 'unit',
                    'teampass' => [
                        'files_version' => '3.2.0.1',
                        'schema_level' => '1780331401',
                    ],
                    'warnings' => ['DOCUMENTS_NOT_INCLUDED'],
                ],
                $tmpFile,
                $manifestJson,
                'Comment'
            );

            $this->assertSame(tpBackupGetPackageFormatId(), $metadata['backup_format']);
            $this->assertSame(tpBackupGetPackageFormatVersion(), $metadata['backup_format_version']);
            $this->assertSame('3.2.0.1', $metadata['tp_files_version']);
            $this->assertSame('1780331401', $metadata['schema_level']);
            $this->assertTrue($metadata['encrypted']);
            $this->assertSame('defuse', $metadata['encryption']['engine']);
            $this->assertSame(hash('sha256', $manifestJson), $metadata['manifest_checksum']);
            $this->assertArrayNotHasKey('document_entries', $metadata);
            $this->assertSame('Comment', $metadata['comment']);
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    public function testDocumentSummaryUsesPublicAggregateShape(): void
    {
        $summary = tpBackupBuildEmptyDocumentSummary(true, 'included');

        $this->assertTrue($summary['included']);
        $this->assertSame('included', $summary['mode']);
        $this->assertSame(0, $summary['item_attachments']['count']);
        $this->assertSame(0, $summary['kb_attachments']['included_count']);
        $this->assertSame(0, $summary['avatars']['missing_count']);
        $this->assertArrayHasKey('manifest_checksum', $summary);
        $this->assertArrayHasKey('warnings', $summary);
    }
}
