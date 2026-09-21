<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * @file      BackupS3RequestTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

class BackupS3RequestTest extends TestCase
{
    /** Downloaded bytes must not corrupt the encrypted AJAX response (issue #5376). */
    #[DataProvider('responseCases')]
    #[RequiresPhpExtension('curl')]
    public function testS3ResponsesStayOutOfPhpOutput(int $status, string $payload, bool $useSink): void
    {
        $sink = tempnam(sys_get_temp_dir(), 'tp_s3_test_');
        self::assertNotFalse($sink);
        $server = new Process([PHP_BINARY, '-n', __DIR__ . '/../Fixtures/backup_s3_http_response.php', (string) $status]);
        $server->setInput($payload);
        $server->setTimeout(10);

        try {
            $server->start();
            $ready = $server->waitUntil(static function () use ($server): bool {
                return str_contains($server->getOutput(), "\n");
            });
            self::assertTrue($ready, $server->getErrorOutput());
            $address = trim($server->getOutput());
            self::assertMatchesRegularExpression('/^127\.0\.0\.1:[0-9]+$/', $address);

            $config = [
                'endpoint' => 'http://' . $address,
                'region' => 'us-east-1',
                'bucket' => 'test-backups',
                'path_style' => true,
                'access_key' => 'test-access-key',
                'secret_key' => 'test-secret-key',
            ];
            ob_start();
            try {
                $response = tpBackupExternalizedS3Request($config, 'GET', 'backup.tpbackup', $useSink ? ['sink' => $sink] : []);
            } finally {
                $output = (string) ob_get_clean();
            }
            $server->wait();

            self::assertSame(0, $server->getExitCode(), $server->getErrorOutput());
            self::assertSame($status, $response['status']);
            self::assertSame('', $response['error']);
            self::assertSame((string) strlen($payload), $response['headers']['content-length']);
            self::assertSame(0, strlen($output), 'S3 response bytes must never enter the AJAX output.');
            self::assertSame($useSink ? '' : $payload, $response['body']);
            self::assertSame($useSink ? $payload : '', file_get_contents($sink));
            self::assertSame(
                $useSink && $status === 200 && $payload !== '',
                tpBackupExternalizedS3DownloadIsComplete($response, $sink)
            );
        } finally {
            $server->stop(0);
            unlink($sink);
        }
    }

    /** @return array<string, array{int, string, bool}> */
    public static function responseCases(): array
    {
        return [
            'binary backup to file' => [200, str_repeat(implode('', array_map('chr', range(0, 255))), 256), true],
            'empty backup to file' => [200, '', true],
            'S3 error to file' => [403, '<Error><Code>AccessDenied</Code></Error>', true],
            'metadata without file sink' => [200, '{"backup_format":"tpbackup"}', false],
        ];
    }

    /** A 2xx status alone must not send an empty or truncated download to decryption. */
    #[DataProvider('downloadCompletenessCases')]
    public function testS3DownloadIsCompleteOnlyForANonEmptyErrorFreeSuccess(int $status, string $error, ?string $content, bool $expected): void
    {
        $sink = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp_s3_test_' . bin2hex(random_bytes(8));
        if ($content !== null) {
            self::assertNotFalse(file_put_contents($sink, $content));
        }

        try {
            $response = ['status' => $status, 'body' => '', 'error' => $error, 'headers' => []];
            self::assertSame($expected, tpBackupExternalizedS3DownloadIsComplete($response, $sink));
        } finally {
            if (is_file($sink)) {
                unlink($sink);
            }
        }
    }

    /** @return array<string, array{int, string, ?string, bool}> */
    public static function downloadCompletenessCases(): array
    {
        return [
            'complete backup' => [200, '', 'backup', true],
            'empty file' => [200, '', '', false],
            'missing file' => [200, '', null, false],
            'truncated transfer' => [200, 'transfer closed with 42 bytes remaining to read', 'back', false],
            'S3 error body' => [403, '', '<Error><Code>AccessDenied</Code></Error>', false],
        ];
    }
}
