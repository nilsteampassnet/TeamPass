<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BackupUploadedCompatibilityTest extends TestCase
{
    public function testUploadedArchiveReportsTheUnverifiedVersionOnlyOnce(): void
    {
        $directory = sys_get_temp_dir() . '/tp-upload-compat-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory . '/upload-sl100.sql', 'opaque encrypted fixture');
        try {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_uploaded_compatibility.php', $directory]);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['is_compatible']);
            self::assertSame(['VERSION_NOT_VERIFIED'], $result['warnings']);
        } finally {
            unlink($directory . '/upload-sl100.sql');
            rmdir($directory);
        }
    }
}
