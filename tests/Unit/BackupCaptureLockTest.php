<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BackupCaptureLockTest extends TestCase
{
    public function testManualCaptureHoldsTheWorkerLockAndReleasesItOnSuccessAndRefusal(): void
    {
        $directory = sys_get_temp_dir() . '/tp-capture-lock-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_capture_lock.php', $directory]);
            $process->mustRun();
            self::assertSame(['blocked' => true, 'released' => true, 'refused' => true, 'releasedAfterFailure' => true],
                json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR));
        } finally {
            if (is_file($directory . '/handler.lock')) { unlink($directory . '/handler.lock'); }
            rmdir($directory);
        }
    }
}
