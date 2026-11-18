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

final class BackupTransportSecurityTest extends TestCase
{
    public function testUnverifiedServersNeverReceiveCredentials(): void
    {
        foreach (['missing' => 'SFTP_HOST_KEY_REQUIRED', 'changed' => 'SFTP_HOST_KEY_MISMATCH', 'trusted' => ''] as $scenario => $reason) {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_sftp_identity.php', $scenario]);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($reason, $result['reason']);
            self::assertSame($scenario === 'trusted', $result['success']);
            self::assertSame($scenario === 'trusted' ? 1 : 0, $result['logins']);
        }
    }

    public function testTlsIsRequiredOutsideLoopbackAndCredentialsStayOutOfUrls(): void
    {
        foreach (['https://backup.example.test' => true, 'http://backup.example.test' => false,
            'http://127.0.0.1:8080' => true, 'http://127.0.0.1.example.test' => false,
            'https://user:password@backup.example.test' => false] as $url => $valid) {
            self::assertSame($valid, tpBackupValidateExternalizedWebdavConfig(['url' => $url,
                'username' => 'fixture', 'password' => 'fixture', 'remote_path' => '/backups'])['success']);
            self::assertSame($valid, tpBackupValidateExternalizedS3Config(['endpoint' => $url,
                'bucket' => 'fixture', 'access_key' => 'fixture', 'secret_key' => 'fixture'])['success']);
        }
    }
}
