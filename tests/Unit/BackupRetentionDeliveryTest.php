<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BackupRetentionDeliveryTest extends TestCase
{
    public function testRetentionFailureStillQueuesTheValidNewArchive(): void
    {
        $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_retention_delivery.php']);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('retention refused', $result['error']);
        self::assertContains('queueExternalizedBackupAfterScheduled', $result['calls']);
        self::assertContains('purgeOldScheduledBackups', $result['calls']);
        self::assertLessThan(array_search('purgeOldScheduledBackups', $result['calls'], true),
            array_search('queueExternalizedBackupAfterScheduled', $result['calls'], true));
    }
}
