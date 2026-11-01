<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryPackageInformationTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class RecoveryPackageInformationTest extends TestCase
{
    public function testDownloadDateUsesTheInstanceTimezoneAndDoesNotHideTheNeedForANewKit(): void
    {
        foreach (['info_current' => 'current', 'info_changed' => 'material_changed', 'info_unavailable' => 'unavailable'] as $scenario => $state) {
            $info = $this->probe($scenario);
            self::assertSame($state, $info['state']);
            self::assertSame(1700000000, $info['downloaded_at']);
            self::assertStringContainsString('14/11/2023 23:13', $info['download_label']);
            self::assertNotEmpty($info['status_label']);
            self::assertSame(1, $info['writes']);
            self::assertSame($state, $info['cached_state']['state']);
            self::assertArrayNotHasKey('snapshot', $info);
        }
    }

    public function testMissingAndMalformedHistoryHaveNoFabricatedDate(): void
    {
        foreach (['info_missing', 'info_malformed'] as $scenario) {
            $info = $this->probe($scenario);
            self::assertSame(0, $info['downloaded_at']);
            self::assertSame('Aucun téléchargement du kit de récupération enregistré.', $info['download_label']);
            self::assertSame('', $info['status_label']);
        }
    }

    public function testTheRefreshEndpointRequiresTheSessionKeyAndAdministratorRole(): void
    {
        foreach (['info_bad_key', 'info_non_admin'] as $scenario) {
            $info = $this->probe($scenario);
            self::assertTrue($info['error']);
            self::assertArrayNotHasKey('downloaded_at', $info);
        }
        $info = $this->probe('info_endpoint');
        self::assertFalse($info['error']);
        self::assertSame(1700000000, $info['downloaded_at']);
        self::assertArrayNotHasKey('snapshot', $info);
    }

    public function testEmptyKeysAreMissingWhileUnreadableOrInvalidKeysRemainUnavailable(): void
    {
        foreach (['info_empty_key' => 'missing', 'info_invalid_key' => 'unavailable',
            'info_decrypt_error' => 'unavailable'] as $scenario => $state) {
            $info = $this->probe($scenario);
            self::assertSame($state, $info['state']);
            self::assertSame($state, $info['cached_state']['state']);
        }
    }

    public function testFirstVisitCalculatesInformationAfterGeneratingAnEmptyKey(): void
    {
        $info = $this->probe('info_first_visit');
        self::assertTrue($info['backup_key_generated']);
        self::assertSame('missing', $info['state']);
        self::assertSame('', $info['status_label']);
        self::assertSame(0, $info['downloaded_at']);
        self::assertSame('', $info['receipt']);
    }

    /** Exercise the real adapter with isolated fixture data and no installation. */
    private function probe(string $scenario): array
    {
        $directory = sys_get_temp_dir() . '/tp-recovery-info-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/recovery_package.php', $directory, $scenario]);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
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
