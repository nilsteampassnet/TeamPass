<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BackupFailedRunRetentionTest extends TestCase
{
    public function testFailedRunsNeverPurgePreviousArchives(): void
    {
        foreach (['database_backup', 'externalized_backup'] as $type) {
            $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_failed_run.php', $type]);
            $process->mustRun();
            $calls = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertNotContains('purgeOldScheduledBackups', $calls);
            self::assertNotContains('purgeOldExternalizedBackups', $calls);
            self::assertContains($type === 'database_backup' ? 'updateSchedulerState' : 'updateExternalizedState', $calls);
        }
    }
}
