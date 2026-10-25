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
    public function testReadsTheCurrentOperatorGrantWithoutChangingIt(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tp-operator-');
        $operator = ['token_hash' => str_repeat('a', 64), 'expires_at' => 200];
        $contents = json_encode($operator, JSON_THROW_ON_ERROR);
        file_put_contents($path, $contents);
        try {
            self::assertSame($operator, tpRecoveryReadOperator($path, 100));
            self::assertSame($contents, file_get_contents($path));
        } finally { unlink($path); }
    }

    public function testMissingMalformedAndExpiredOperatorStateRequiresAuthorization(): void
    {
        $path = sys_get_temp_dir() . '/tp-operator-' . bin2hex(random_bytes(8));
        $cases = [[null, 'OPERATOR_CODE_REQUIRED'], ['invalid json', 'OPERATOR_CODE_REQUIRED'],
            ['[]', 'OPERATOR_CODE_REQUIRED'], [str_repeat('x', 4097), 'OPERATOR_CODE_REQUIRED'],
            [json_encode(['token_hash' => 'bad', 'expires_at' => 200]), 'OPERATOR_CODE_REQUIRED'],
            [json_encode(['token_hash' => str_repeat('a', 64), 'expires_at' => '200']), 'OPERATOR_CODE_REQUIRED'],
            [json_encode(['token_hash' => str_repeat('a', 64), 'expires_at' => 100]), 'OPERATOR_CODE_EXPIRED']];
        try {
            foreach ($cases as [$contents, $reason]) {
                if ($contents !== null) { file_put_contents($path, $contents); }
                clearstatcache();
                try {
                    tpRecoveryReadOperator($path, 100);
                    self::fail('Invalid operator state was accepted');
                } catch (TeamPassRecoveryException $exception) {
                    self::assertSame($reason, $exception->reason);
                }
            }
        } finally { if (is_file($path)) { unlink($path); } }
    }

    public function testRefusesASymlinkedOperatorFile(): void
    {
        if (PHP_OS_FAMILY === 'Windows') { self::markTestSkipped('Symlink creation requires additional Windows privileges'); }
        $path = tempnam(sys_get_temp_dir(), 'tp-operator-');
        $link = $path . '.link';
        file_put_contents($path, json_encode(['token_hash' => str_repeat('a', 64), 'expires_at' => 200]));
        symlink($path, $link);
        try {
            $this->expectExceptionMessage('OPERATOR_CODE_REQUIRED');
            tpRecoveryReadOperator($link, 100);
        } finally { unlink($link); unlink($path); }
    }

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

    /** Fail clearly before attempting to load an inaccessible configuration bootstrap. */
    public function testUnreadableBootstrapStopsBeforeAnUncaughtRequireFailure(): void
    {
        $directory = sys_get_temp_dir() . '/tp-recovery-cli-' . bin2hex(random_bytes(8));
        mkdir($directory . '/app/scripts', 0700, true);
        $script = $directory . '/app/scripts/disaster-recovery.php';
        copy(__DIR__ . '/../../app/scripts/disaster-recovery.php', $script);
        try {
            $process = new \Symfony\Component\Process\Process([PHP_BINARY, $script, '--help']);
            $process->setTimeout(10);
            $process->run();
            self::assertSame(1, $process->getExitCode());
            self::assertStringContainsString('RECOVERY_BOOTSTRAP_UNREADABLE', $process->getErrorOutput());
            self::assertStringContainsString('complete PHP service account command', $process->getErrorOutput());
            self::assertStringNotContainsString('Fatal error', $process->getErrorOutput() . $process->getOutput());
            self::assertSame('', $process->getOutput());
        } finally {
            unlink($script);
            rmdir($directory . '/app/scripts');
            rmdir($directory . '/app');
            rmdir($directory);
        }
    }
}
