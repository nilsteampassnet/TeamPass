<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryArchiveTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recovery.functions.php';

final class RecoveryArchiveTest extends TestCase
{
    public function testAuthenticatedDecryptionRemovesPartialOutputOnTampering(): void
    {
        $plain = tempnam(sys_get_temp_dir(), 'tp-drf-');
        $encrypted = $plain . '.encrypted';
        $output = $plain . '.output';
        try {
            file_put_contents($plain, str_repeat('synthetic fixture', 100));
            \Defuse\Crypto\File::encryptFileWithPassword($plain, $encrypted, 'fixture-password');
            $bytes = file_get_contents($encrypted);
            $bytes[100] = chr(ord($bytes[100]) ^ 1);
            file_put_contents($encrypted, $bytes);
            try {
                tpRecoveryDecrypt($encrypted, $output, 'fixture-password');
                self::fail('Tampered input was accepted');
            } catch (TeamPassRecoveryException $exception) {
                self::assertSame('ARCHIVE_AUTHENTICATION_FAILED', $exception->reason);
                self::assertFileDoesNotExist($output);
            }
        } finally {
            foreach ([$plain, $encrypted, $output] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testGeneratedPhpQuotesDestinationValuesWithoutExecutingThem(): void
    {
        $key = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();
        $job = ['connection' => ['host' => 'localhost', 'user' => 'R&D O\'Brien "<>"',
            'password' => '${never_evaluate_this}', 'database' => 'recovery', 'port' => 3306],
            'kit' => ['prefix' => 'custom_', 'secure_name' => 'key.txt', 'legacy_constants' => []]];
        $php = tpRecoverySettingsPhp($job, $key);
        self::assertStringContainsString("'DB_USER', 'R&D O\\'Brien \"<>\"'", $php);
        self::assertStringNotContainsString('${never_evaluate_this}', $php);
        self::assertStringNotContainsString('require', $php);
    }

    public function testUnsafeDestinationDatabaseNameIsRejected(): void
    {
        $this->expectExceptionMessage('TARGET_DATABASE_CONFIG_INVALID');
        tpRecoveryConnectionOptions(['host' => 'localhost', 'user' => 'test', 'password' => '', 'database' => 'other`; DROP DATABASE prod;--']);
    }

    public function testRecoveryInvalidationIsAuditedWithoutAnInventedSenderAction(): void
    {
        require_once __DIR__ . '/../../app/sources/secure_send_audit.php';
        $event = secureSendAuditRecord(['id' => 10, 'originator' => 2], 'invalidated', 'disaster_recovery');
        self::assertSame('disaster_recovery', $event['reason']);
        self::assertNull($event['actor_id']);
    }
}
