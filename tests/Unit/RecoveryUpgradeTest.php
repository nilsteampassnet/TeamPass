<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryUpgradeTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recovery_upgrade.php';

final class RecoveryUpgradeTest extends TestCase
{
    public function testReusesShippedMigrationsInOrderIncludingSourceRelease(): void
    {
        $plan = tpRecoveryPlanUpgrade(['teampass' => ['files_version' => '3.2.2.7', 'schema_level' => '1']], '3.2.3.2', '2');
        self::assertSame(['3.2.2', '3.2.3'], array_keys(tpRecoveryUpgradeScripts($plan, __DIR__ . '/../../public/install')));
    }

    public function testRejectsMissingFutureUpgradePath(): void
    {
        $plan = tpRecoveryPlanUpgrade(['teampass' => ['files_version' => '3.2.3.2', 'schema_level' => '1']], '3.2.4.0', '2');
        $this->expectExceptionMessage('UPGRADE_PATH_UNSUPPORTED');
        tpRecoveryUpgradeScripts($plan, __DIR__ . '/../../public/install');
    }

    public function testRequiresAnExplicitMigrationSuccessResponse(): void
    {
        tpRecoveryCheckMigrationResult('[{"finish":"1","error":""}]');
        $this->expectExceptionMessage('UPGRADE_FAILED');
        tpRecoveryCheckMigrationResult('[{"finish":"1","error":"SQL failed"}]');
    }

    public function testRejectsEmptyOutputEvenIfTheScriptExitedSuccessfully(): void
    {
        $this->expectExceptionMessage('UPGRADE_FAILED');
        tpRecoveryCheckMigrationResult('');
    }

    public function testLibraryOnlyBootstrapDoesNotRequireAnInstalledDatabase(): void
    {
        $command = [PHP_BINARY, '-r', 'define("TEAMPASS_FUNCTIONS_ONLY", true); require "app/vendor/autoload.php"; require "app/config/include.php"; require "app/sources/main.functions.php"; echo function_exists("decryptUserObjectKeyWithMigration") ? "OK" : "FAIL";'];
        $process = new \Symfony\Component\Process\Process($command, dirname(__DIR__, 2));
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame('OK', $process->getOutput());
    }
}
