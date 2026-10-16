<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      BackupRestoreFilePermissionsTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BackupRestoreFilePermissionsTest extends TestCase
{
    /** @return iterable<string,array{string,int}> */
    public static function restorationScenarios(): iterable
    {
        foreach (['restore', 'all_wrong', 'blocked', 'zip', 'zip_existing', 'zip_document', 'chmod'] as $scenario) {
            foreach ([0000, 0022, 0077] as $mask) {
                if ($scenario === 'chmod' && $mask === 0077) {
                    continue;
                }
                yield $scenario . '-' . sprintf('%04o', $mask) => [$scenario, $mask];
            }
        }
    }

    #[DataProvider('restorationScenarios')]
    public function testRestoreProtectionAndCandidateRetryCleanup(string $scenario, int $mask): void
    {
        if ($scenario === 'chmod' && PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('This case requires POSIX permissions needing chmod.');
        }
        if (str_starts_with($scenario, 'zip') && !class_exists('ZipArchive')) {
            self::markTestSkipped('ZipArchive is required.');
        }
        $root = sys_get_temp_dir() . '/teampass-restore-permissions-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0700));
        try {
            $process = new Process(array_merge([PHP_BINARY],
                $scenario === 'chmod' ? ['-d', 'disable_functions=chmod'] : [],
                [__DIR__ . '/../Fixtures/backup_restore_permissions.php', $root, $scenario, sprintf('%04o', $mask)]));
            $process->setTimeout(20);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $success = in_array($scenario, ['restore', 'zip', 'zip_existing', 'zip_document'], true);
            self::assertSame($success, $result['success']);
            if ($success) {
                self::assertSame('SQL restore fixture content', $result['contents']);
                if (PHP_OS_FAMILY !== 'Windows') {
                    $expectedMode = $scenario === 'zip_document' && $mask !== 0077 ? 0640 : 0600;
                    self::assertSame($expectedMode, $result['mode']);
                }
            } elseif ($scenario === 'blocked') {
                self::assertTrue($result['is_directory']);
            } else {
                self::assertFalse($result['exists']);
            }
            if ($scenario === 'blocked' || $scenario === 'chmod' || str_starts_with($scenario, 'zip')) {
                self::assertSame([], $result['decryption_modes']);
            } else {
                self::assertCount(2, $result['decryption_modes']);
                self::assertNotContains(null, $result['decryption_modes'], 'Pre-create every candidate target before decryption.');
                if (PHP_OS_FAMILY !== 'Windows') {
                    self::assertSame([0600, 0600], $result['decryption_modes']);
                }
            }
        } finally {
            foreach (new DirectoryIterator($root) as $entry) {
                if (!$entry->isDot()) {
                    $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                }
            }
            rmdir($root);
        }
    }
}
