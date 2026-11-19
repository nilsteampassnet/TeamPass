<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class BackupManualKeyTest extends TestCase
{
    public function testControllerRequiresAnEncryptionKeyAndPreservesAcceptedBytes(): void
    {
        foreach ([null, '', 123, str_repeat('a', 4097), '0', "R&D O'Brien <secret> \"é\""] as $key) {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_manual_key.php',
                base64_encode(json_encode(['encryptionKey' => $key], JSON_THROW_ON_ERROR))]);
            $process->mustRun();
            $response = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $valid = is_string($key) && $key !== '' && strlen($key) <= 4096;
            self::assertSame(!$valid, $response['error']);
            if ($valid) {
                self::assertSame($key, $response['key']);
            }
        }
    }

    public function testManualPassphrasesPreserveBytesAndReadHistoricalBackups(): void
    {
        $raw = "R&D O'Brien <secret> \"é\"";
        $keys = tpBackupManualKeyCandidates($raw);
        self::assertSame($raw, $keys[0]);
        self::assertSame(filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS), $keys[1]);
        self::assertSame(['0'], tpBackupManualKeyCandidates('0'));
        self::assertSame([], tpBackupManualKeyCandidates(''));
    }
}
