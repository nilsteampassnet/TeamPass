<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryPackageStoredStateTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../../app/sources/recovery_package_logic.php';

final class RecoveryPackageStoredStateTest extends TestCase
{
    public function testMarkersDetectApplicationChangesAndRestoresWithoutOpeningKeys(): void
    {
        $settings = ['bck_script_passkey' => 'synthetic-encrypted-key', 'recovery_generation' => 'first'];
        $cached = ['version' => 1, 'state' => 'current', 'inputs' => tpRecoveryPackageStateInputs($settings)];
        self::assertSame('current', tpRecoveryPackageStoredStatus($cached, $settings));
        self::assertSame('material_changed', tpRecoveryPackageStoredStatus($cached,
            array_replace($settings, ['bck_script_passkey' => 'rotated'])));
        self::assertSame('configuration_changed', tpRecoveryPackageStoredStatus($cached,
            array_replace($settings, ['recovery_generation' => 'restored'])));
        foreach (['cpassman_url', 'cpassman_dir', 'path_to_files_folder', 'path_to_upload_folder'] as $name) {
            self::assertSame('configuration_changed', tpRecoveryPackageStoredStatus($cached,
                array_replace($settings, [$name => 'changed'])));
        }
        self::assertSame('current', tpRecoveryPackageStoredStatus($cached,
            $settings + ['teampass_version' => '3.2.4', 'timezone' => 'Europe/Paris']));
        self::assertSame('missing', tpRecoveryPackageStoredStatus($cached, ['bck_script_passkey' => '']));
    }

    public function testAbsentOrMalformedStateCannotAcknowledgeAKit(): void
    {
        $settings = ['bck_script_passkey' => 'synthetic-encrypted-key'];
        self::assertSame('missing', tpRecoveryPackageStoredStatus([], $settings));
        self::assertSame('unavailable', tpRecoveryPackageStoredStatus(['state' => 'current'], $settings));
        $cached = ['version' => 1, 'state' => 'unknown', 'inputs' => []];
        self::assertSame('unavailable', tpRecoveryPackageStoredStatus($cached, $settings));
        foreach (['missing', 'unavailable', 'material_changed', 'configuration_changed'] as $state) {
            $cached = ['version' => 1, 'state' => $state, 'inputs' => tpRecoveryPackageStateInputs($settings)];
            self::assertSame($state, tpRecoveryPackageStoredStatus($cached, $settings));
            if ($state !== 'configuration_changed') {
                self::assertSame($state, tpRecoveryPackageStoredStatus($cached, $settings + ['cpassman_url' => 'changed']));
            }
        }
    }

    public function testMarkersDoNotCopyEncryptedOrLegacyClearKeys(): void
    {
        foreach (['synthetic-encrypted-key', str_repeat('a', 40)] as $key) {
            $inputs = tpRecoveryPackageStateInputs(['bck_script_passkey' => $key]);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $inputs['backup_key']);
            self::assertStringNotContainsString($key, json_encode($inputs));
        }
    }

    public function testRealDashboardCollectionUsesOneLookupAndLoadsNoBackupHelpers(): void
    {
        foreach (['current' => 0, 'restored' => 1] as $scenario => $notices) {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/recovery_package_dashboard.php', $scenario]);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($notices, $result['recovery_notices']);
            self::assertSame(1, $result['state_reads']);
            self::assertFalse($result['backup_loaded']);
            self::assertFalse($result['capture_loaded']);
        }
    }
}
