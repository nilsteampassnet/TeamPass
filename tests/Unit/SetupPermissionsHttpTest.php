<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file SetupPermissionsHttpTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class SetupPermissionsHttpTest extends TestCase
{
    /** Exercise the real endpoint in a disposable checkout with its own session storage. */
    public function testSetupAuthorizationAndFinalizationOverHttp(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('The HTTP integration test requires cURL.');
        }
        $ini = php_ini_loaded_file();
        $process = new Process([PHP_BINARY, 'tests/Integration/setup_permissions_http.php'], dirname(__DIR__, 2),
            is_string($ini) ? ['PHPRC' => $ini] : null);
        $process->setTimeout(120);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
        self::assertStringContainsString('OK: setup session binding', $process->getOutput());
    }
}
