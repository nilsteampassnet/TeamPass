<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryEnvironmentTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recovery_logic.php';
require_once __DIR__ . '/../../app/sources/recovery_environment.php';

final class RecoveryEnvironmentTest extends TestCase
{
    public function testUnixCommandsUseTheActualServiceAccountAndAbsoluteCli(): void
    {
        self::assertSame("sudo -u 'apache' -- '/usr/bin/php8.3' '/srv/TeamPass Lab/app/scripts/disaster-recovery.php'",
            tpRecoveryCommandBase('apache', 'Linux', '/usr/bin/php8.3', '/srv/TeamPass Lab'));
    }

    public function testShellMetacharactersAndApostrophesRemainLiteral(): void
    {
        self::assertSame("'O'\\''Brien & \$(id)'", tpRecoveryCommandArgument("O'Brien & \$(id)", 'Linux'));
        self::assertSame("'C:\\O''Brien & Lab'", tpRecoveryCommandArgument("C:\\O'Brien & Lab", 'Windows'));
    }

    public function testWindowsCommandsUsePowerShellAndDoNotInventASudoAccount(): void
    {
        self::assertSame("& 'C:/PHP/php.exe' 'C:/TeamPass Lab/app/scripts/disaster-recovery.php'",
            tpRecoveryCommandBase('www-data', 'Windows', 'C:/PHP/php.exe', 'C:/TeamPass Lab/'));
    }

    public function testRootWriterIsRejectedWithoutRejectingWindowsOrUnprivilegedUsers(): void
    {
        self::assertTrue(tpRecoveryIsRootIdentity('Linux', 0));
        self::assertFalse(tpRecoveryIsRootIdentity('Linux', 33));
        self::assertFalse(tpRecoveryIsRootIdentity('Windows', null));
        self::assertFalse(tpRecoveryIsRootIdentity('Windows', 0));
    }

    public function testCliBinaryResolverNeverReturnsTheFpmOrCgiExecutable(): void
    {
        self::assertDoesNotMatchRegularExpression('/php-(?:fpm|cgi)/i', tpRecoveryPhpCliBinary());
    }
}
