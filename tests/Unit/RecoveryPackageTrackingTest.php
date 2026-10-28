<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryPackageTrackingTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class RecoveryPackageTrackingTest extends TestCase
{
    /** @return iterable<string,array{string,bool,string}> */
    public static function scenarios(): iterable
    {
        yield 'current kit' => ['current', true, 'current'];
        yield 'same clear key, different storage' => ['same_key_reencrypted', true, 'current'];
        yield 'key changed before download' => ['rotated', false, 'missing'];
        yield 'restored before download' => ['restored', false, 'missing'];
        yield 'audit failure rolls back receipt' => ['audit_failure', false, 'missing'];
        yield 'state failure rolls back receipt' => ['state_failure', false, 'missing'];
        yield 'caller transaction preserved' => ['caller_transaction', false, 'missing'];
        yield 'malformed historical metadata' => ['bad_history', false, 'missing'];
        yield 'older download cannot overwrite new receipt' => ['older_download', false, 'current'];
    }

    #[DataProvider('scenarios')]
    public function testRecordingUsesFreshInputsAndPreservesTheLastCurrentReceipt(string $scenario, bool $recorded, string $state): void
    {
        $result = $this->probe($scenario);
        self::assertTrue($result['read_only']);
        self::assertSame($recorded, $result['recorded']);
        self::assertSame($state, $result['state']);
        self::assertSame($scenario === 'caller_transaction' ? 1 : 0, $result['depth']);
        if ($scenario === 'older_download') {
            self::assertTrue($result['preserved_newer_receipt']);
            self::assertSame(8, $result['receipt']['downloaded_by']);
        } elseif ($recorded) {
            self::assertSame(7, $result['receipt']['downloaded_by']);
            self::assertCount(1, $result['audit']);
        } else {
            self::assertNull($result['receipt']);
            self::assertCount(in_array($scenario, ['rotated', 'restored'], true) ? 1 : 0, $result['audit']);
        }
        $json = json_encode($result['receipt']);
        self::assertStringNotContainsString('fixture-master', $json);
        self::assertStringNotContainsString(str_repeat('a', 40), $json);
        if (in_array($scenario, ['audit_failure', 'state_failure', 'caller_transaction', 'bad_history'], true)) {
            self::assertNull($result['cached_state']);
        } else {
            self::assertSame($state, $result['cached_state']['state']);
        }
    }

    public function testSettingChangesInvalidateTheCacheWithoutCreatingDownloadHistory(): void
    {
        foreach (['setting_same_key' => 'current', 'setting_rotated_key' => 'material_changed',
            'setting_deployment' => 'configuration_changed'] as $scenario => $state) {
            $result = $this->probe($scenario);
            self::assertSame($state, $result['state']);
            self::assertTrue($result['receipt_unchanged']);
        }
    }

    public function testGenerationDoesNotRecordADownloadAndFingerprintsTheActualCapturedMaterial(): void
    {
        if (!class_exists('ZipArchive')) {
            self::markTestSkipped('ZipArchive is required.');
        }
        foreach (['create', 'source_changed'] as $scenario) {
            $result = $this->probe($scenario);
            self::assertSame('fixture-master', $result['packaged_master']);
            self::assertTrue($result['snapshot_matches_capture']);
            self::assertSame('missing', $result['state']);
            self::assertSame(0, $result['writes']);
            self::assertFalse($result['archive_has_receipt']);
        }
    }

    /** @return iterable<string,array{string,int,bool,bool}> */
    public static function downloads(): iterable
    {
        yield 'complete authorized transfer' => ['download_success', 200, true, false];
        yield 'invalid session key' => ['download_bad_key', 403, false, true];
        yield 'invalid secondary key' => ['download_bad_temp', 403, false, true];
        yield 'invalid one-time token' => ['download_bad_token', 403, false, true];
        yield 'non-administrator' => ['download_non_admin', 403, false, true];
        yield 'expired package' => ['download_expired', 410, false, false];
        yield 'missing file' => ['download_missing_file', 404, false, false];
        yield 'empty file' => ['download_empty', 200, false, false];
        yield 'changed key' => ['download_old_snapshot', 200, false, false];
        yield 'pre-feature pending package' => ['download_no_snapshot', 200, false, false];
    }

    #[DataProvider('downloads')]
    public function testTheControllerOnlyRecordsAuthorizedCompleteCurrentDownloads(string $scenario, int $status, bool $recorded, bool $retained): void
    {
        $result = $this->probe($scenario);
        self::assertSame($status, $result['status']);
        self::assertSame($recorded, is_array($result['receipt']));
        self::assertCount($status === 200 && $scenario !== 'download_empty' ? 1 : 0, $result['audit']);
        self::assertSame($retained, $result['file_exists']);
        self::assertSame($retained, $result['session_exists']);
        self::assertSame($status === 200 && $scenario !== 'download_empty' ? 'fixture encrypted package' : '', $result['output']);
    }

    /** Run against synthetic configuration and a fake DB in a separate PHP process. */
    private function probe(string $scenario): array
    {
        $directory = sys_get_temp_dir() . '/tp-recovery-tracking-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/recovery_package.php', $directory, $scenario]);
            $process->setTimeout(30);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
            if (str_starts_with($scenario, 'download_')) {
                return json_decode(file_get_contents($directory . '/result.json'), true, 512, JSON_THROW_ON_ERROR)
                    + ['output' => $process->getOutput()];
            }
            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($directory);
        }
    }
}
