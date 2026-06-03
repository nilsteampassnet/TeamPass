<?php

declare(strict_types=1);

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
