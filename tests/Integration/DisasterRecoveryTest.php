<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file DisasterRecoveryTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class DisasterRecoveryTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function scenarios(): array
    {
        return ['same version' => ['current'], 'upgrade from 3.2.2' => ['upgrade'], 'HTTP assistant' => ['http']];
    }

    #[DataProvider('scenarios')]
    public function testRestoresRealPackagesIntoAnEmptyDatabase(string $scenario): void
    {
        if (!getenv('TEAMPASS_RECOVERY_TEST_PORT')) {
            self::markTestSkipped('Requires a disposable local MariaDB on TEAMPASS_RECOVERY_TEST_PORT');
        }
        if ($scenario === 'http' && !extension_loaded('curl')) {
            self::markTestSkipped('HTTP assistant scenario requires cURL');
        }
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'tests/Fixtures/recovery_database.php', $scenario], dirname(__DIR__, 2));
        $process->setTimeout(300);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
        self::assertStringContainsString('OK: real encrypted backup', $process->getOutput());
    }
}
