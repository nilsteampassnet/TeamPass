<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/backup.functions.php';

final class BackupRestoreJournalTest extends TestCase
{
    public function testJournalSurvivesWithoutDatabaseAuthorizationAndRejectsAnotherToken(): void
    {
        $path = sys_get_temp_dir() . '/tp-resume-' . bin2hex(random_bytes(8));
        $hash = hash('sha256', 'fictitious-original-token');
        $journal = ['version' => 1, 'token_hash' => $hash, 'payload' => ['file' => ['sha256' => str_repeat('a', 64)]],
            'settings' => [], 'key_candidates' => ['encrypted-fixture-key']];
        try {
            tpBackupWriteRestoreJournal($journal, $path);
            self::assertSame($journal, tpBackupReadRestoreJournal($hash, $path));
            if (PHP_OS_FAMILY !== 'Windows') {
                self::assertSame(0600, fileperms($path) & 0777);
            }
            self::assertSame([], glob($path . '.*.tmp'));
            $this->expectExceptionMessage('RESTORE_RESUME_UNAVAILABLE');
            tpBackupReadRestoreJournal(hash('sha256', 'another-token'), $path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testASecondWriterCannotOverwriteAnExistingHold(): void
    {
        $path = sys_get_temp_dir() . '/tp-resume-' . bin2hex(random_bytes(8));
        file_put_contents($path, 'original hold');
        try {
            try {
                tpBackupWriteRestoreJournal([], $path);
                self::fail('Existing hold was overwritten.');
            } catch (TeamPassRecoveryException $exception) {
                self::assertSame('RESTORE_HOLD_FAILED', $exception->reason);
                self::assertSame('original hold', file_get_contents($path));
                self::assertSame([], glob($path . '.*.tmp'));
            }
        } finally {
            unlink($path);
        }
    }
}
