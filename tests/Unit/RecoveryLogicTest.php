<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryLogicTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recovery_logic.php';

final class RecoveryLogicTest extends TestCase
{
    public function testPlansAnUpgradeAndAcceptsAnUnchangedSchema(): void
    {
        $manifest = ['teampass' => ['files_version' => '3.2.2.7', 'schema_level' => '100']];
        self::assertTrue(tpRecoveryPlanUpgrade($manifest, '3.2.3.2', '200')['upgrade_required']);
        self::assertTrue(tpRecoveryPlanUpgrade($manifest, '3.2.3.2', '100')['upgrade_required']);
        self::assertFalse(tpRecoveryPlanUpgrade($manifest, '3.2.2.7', '100')['upgrade_required']);
    }

    /** @return array<string,array{string,string,string}> */
    public static function incompatibleVersions(): array
    {
        return [
            'newer code' => ['3.2.3.3', '100', 'DOWNGRADE_NOT_SUPPORTED'],
            'newer schema' => ['3.2.2.7', '300', 'DOWNGRADE_NOT_SUPPORTED'],
            'missing schema' => ['3.2.2.7', '', 'SCHEMA_UNKNOWN'],
            'legacy layout' => ['3.1.7.4', '100', 'SOURCE_VERSION_UNSUPPORTED'],
            'unknown version' => ['', '100', 'VERSION_UNKNOWN'],
        ];
    }

    #[DataProvider('incompatibleVersions')]
    public function testRejectsIncompatibleBackups(string $version, string $schema, string $reason): void
    {
        $this->expectExceptionMessage($reason);
        tpRecoveryPlanUpgrade(['teampass' => ['files_version' => $version, 'schema_level' => $schema]], '3.2.3.2', '200');
    }

    /** @return array<string,array{string}> */
    public static function unsafePaths(): array
    {
        return array_map(static fn ($path) => [$path], [
            '../settings.php', '/etc/passwd', 'a/../b', 'C:/file', 'a\\b', 'a//b',
            'a/./b', "a\0b", 'a/filename.', 'a/filename ',
        ]);
    }

    #[DataProvider('unsafePaths')]
    public function testRefusesUnsafeArchivePaths(string $path): void
    {
        $this->expectExceptionMessage('ARCHIVE_PATH_INVALID');
        tpRecoveryEntryPath($path);
    }

    public function testReadsAnExistingKitWithoutExecutingArchivedPhp(): void
    {
        $this->withKit(function (ZipArchive $zip, string $key): void {
            $result = tpRecoveryReadKit($zip);
            self::assertSame($key, $result['master_key']);
            self::assertSame('custom_', $result['prefix']);
            self::assertSame('backup-password', $result['backup_key']);
        });
    }

    public function testRejectsUnexpectedExecutableEntries(): void
    {
        $this->withKit(function (ZipArchive $zip): void {
            $zip->addFromString('public/shell.php', '<?php exit;');
            $this->expectExceptionMessage('RECOVERY_UNEXPECTED_ENTRY');
            tpRecoveryReadKit($zip);
        });
    }

    public function testEnforcesExpansionBudgetBeforeReadingContents(): void
    {
        $this->withKit(function (ZipArchive $zip): void {
            $this->expectExceptionMessage('ARCHIVE_SIZE_LIMIT');
            tpRecoveryInspectZip($zip, 10);
        });
    }

    public function testRejectsSymlinks(): void
    {
        $this->withKit(function (ZipArchive $zip): void {
            $zip->addFromString('symlink', '/etc/passwd');
            $zip->setExternalAttributesName('symlink', ZipArchive::OPSYS_UNIX, 0120777 << 16);
            $this->expectExceptionMessage('ARCHIVE_SPECIAL_ENTRY');
            tpRecoveryInspectZip($zip);
        });
    }

    /** Create a synthetic historical kit containing a deliberately invalid PHP settings file. */
    private function withKit(callable $check): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZIP extension required');
        }
        $path = tempnam(sys_get_temp_dir(), 'tp-recovery-test-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $key = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();
        $zip->addFromString('manifest.json', json_encode([
            'recovery_format' => 'teampass.recovery', 'recovery_format_version' => 1,
            'contents' => ['config/constants.json', 'config/settings.php', 'secrets/key.txt', 'backup/instance_key.txt'],
        ]));
        $zip->addFromString('config/constants.json', json_encode(['DB_PREFIX' => 'custom_', 'SECUREFILE' => 'key.txt']));
        $zip->addFromString('config/settings.php', '<?php this must never be evaluated !!!');
        $zip->addFromString('secrets/key.txt', $key);
        $zip->addFromString('backup/instance_key.txt', "backup-password\n");
        $zip->close();
        $zip->open($path);
        try {
            $check($zip, $key);
        } finally {
            $zip->close();
            unlink($path);
        }
    }
}
