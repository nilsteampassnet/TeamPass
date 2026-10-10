<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * TeamPass is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See <https://www.gnu.org/licenses/> for the GNU General Public License.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('sqlite3')) {
            self::markTestSkipped('SQLite3 is required for SQL-backed retention tests.');
        }
        require_once __DIR__ . '/../Fixtures/secure_send_retention_db.php';
        require_once __DIR__ . '/../../app/sources/secure_send_retention.php';
        require_once __DIR__ . '/../../app/config/include.php';
        DB::$connection = new SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$connection->exec('CREATE TABLE retention_fixture_secure_send_audit (
            id INTEGER PRIMARY KEY, occurred_at INTEGER, created_at INTEGER, event TEXT, originator INTEGER)');
        DB::$connection->exec('CREATE TABLE retention_fixture_log_system (
            id INTEGER PRIMARY KEY, type TEXT, date TEXT, label TEXT, qui TEXT, field_1 TEXT)');
    }

    protected function tearDown(): void
    {
        if (class_exists('DB', false) && isset(DB::$connection)) {
            DB::$connection->close();
        }
    }

    private function seed(int $id, int $occurredAt, int $createdAt = 0, string $event = 'created'): void
    {
        $statement = DB::$connection->prepare('INSERT INTO retention_fixture_secure_send_audit VALUES (?, ?, ?, ?, 42)');
        foreach ([$id, $occurredAt, $createdAt, $event] as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $statement->execute();
    }

    private function rowCount(string $table): int
    {
        return (int) DB::$connection->querySingle('SELECT COUNT(*) FROM ' . prefixTable($table));
    }

    public function testDisabledOrMissingPolicyDoesNotTouchTheDatabase(): void
    {
        foreach ([[], ['secure_send_audit_retention_days' => 0], ['secure_send_audit_retention_days' => '0']] as $settings) {
            self::assertSame(0, secureSendPruneAuditHistory($settings, 200000));
        }
        self::assertSame(0, secureSendPruneAuditHistory(['secure_send_audit_retention_days' => 36500], 200000));
        self::assertSame([], DB::$queries);
        self::assertSame(0, $this->rowCount('log_system'));
    }

    public function testPolicyValidationRejectsCoercionsBeforeAnyDatabaseAccess(): void
    {
        foreach ([null, false, true, [], ['30'], 1.0, -1, '-1', '', '01', '1.0', '1e2', '+30', '36501', '9999999999999999999999', '30 days'] as $invalid) {
            try {
                secureSendAuditRetentionDays($invalid);
                self::fail('Invalid retention policy accepted: ' . json_encode($invalid));
            } catch (InvalidArgumentException $e) {
                self::assertSame('Invalid Secure Send audit retention', $e->getMessage());
            }
            if ($invalid !== null) {
                try {
                    secureSendPruneAuditHistory(['secure_send_audit_retention_days' => $invalid], 200000);
                    self::fail('Malformed policy caused a purge');
                } catch (InvalidArgumentException $e) {
                    self::assertSame([], DB::$queries);
                }
            }
        }
        foreach ([0, 1, 36500, '0', '1', '36500', ' 30 '] as $valid) {
            self::assertSame((int) $valid, secureSendAuditRetentionDays($valid));
        }
    }

    public function testCutoffUsesObservationTimeAndPreservesTheExactBoundary(): void
    {
        $now = 200000;
        $cutoff = $now - 86400;
        foreach (['created', 'revealed', 'reveal_failed', 'revoked', 'invalidated', 'expired'] as $index => $event) {
            $this->seed($index + 1, $cutoff - 1, $now, $event);
        }
        $this->seed(7, $cutoff, 0);
        $this->seed(8, $cutoff + 1, 0);
        $this->seed(9, $now + 1, 0);
        self::assertSame(6, secureSendPruneAuditHistory(['secure_send_audit_retention_days' => 1, 'otv_is_enabled' => 0], $now));
        self::assertSame(3, $this->rowCount('secure_send_audit'));
        $row = DB::$connection->querySingle('SELECT * FROM retention_fixture_log_system', true);
        self::assertSame('admin_action', $row['type']);
        self::assertSame((string) TP_USER_ID, $row['qui']);
        self::assertSame((string) $now, $row['date']);
        self::assertSame('secure_send_audit_retention_purge', $row['label']);
        self::assertSame(['retention_days' => 1, 'cutoff' => $cutoff, 'deleted_count' => 6, 'batch_limit' => 1000],
            json_decode($row['field_1'], true, 512, JSON_THROW_ON_ERROR));
        self::assertLessThanOrEqual(250, strlen($row['field_1']));
        self::assertSame(0, secureSendPruneAuditHistory(['secure_send_audit_retention_days' => 1], $now));
        self::assertSame(1, $this->rowCount('log_system'), 'Empty runs must not generate duplicate evidence');
    }

    public function testOneRunIsBoundedAndOldestEventsAreRemovedFirst(): void
    {
        // Newer events have lower ids: ordering must use occurred_at first.
        for ($id = 1; $id <= 1003; ++$id) {
            $this->seed($id, 2000 - $id);
        }
        $settings = ['secure_send_audit_retention_days' => 1];
        self::assertSame(1000, secureSendPruneAuditHistory($settings, 200000));
        self::assertSame(3, $this->rowCount('secure_send_audit'));
        self::assertSame(3, (int) DB::$connection->querySingle('SELECT MAX(id) FROM retention_fixture_secure_send_audit'));
        self::assertSame(3, secureSendPruneAuditHistory($settings, 200000));
        self::assertSame(0, secureSendPruneAuditHistory($settings, 200000));
        self::assertSame(2, $this->rowCount('log_system'));
    }

    public function testStorageAndCommitFailuresRollBackTheEntireBatch(): void
    {
        $this->seed(1, 1);
        foreach (['failDelete', 'failInsert', 'failCommit'] as $failure) {
            DB::${$failure} = true;
            try {
                secureSendPruneAuditHistory(['secure_send_audit_retention_days' => 1, 'syslog_enable' => 1], 200000);
                self::fail('Failed retention transaction committed');
            } catch (RuntimeException $e) {
                self::assertSame(1, $this->rowCount('secure_send_audit'));
                self::assertSame(0, $this->rowCount('log_system'));
                self::assertFalse(DB::$inTransaction);
                self::assertSame([], DB::$forwarded);
            } finally {
                DB::${$failure} = false;
            }
        }
    }

    public function testNonTransactionalOrMissingTablesPreventDeletion(): void
    {
        $this->seed(1, 1);
        foreach ([0, 1] as $count) {
            DB::$transactionalTables = $count;
            try {
                secureSendPruneAuditHistory(['secure_send_audit_retention_days' => 1], 200000);
                self::fail('Unsafe tables accepted');
            } catch (RuntimeException $e) {
                self::assertSame(1, $this->rowCount('secure_send_audit'));
                self::assertSame(0, $this->rowCount('log_system'));
                self::assertFalse(DB::$inTransaction);
            }
        }
    }

    public function testForwardingHappensAfterCommitAndFailureCannotUndoThePurge(): void
    {
        $this->seed(1, 1);
        $settings = ['secure_send_audit_retention_days' => 1, 'syslog_enable' => 1, 'syslog_host' => 'collector.example', 'syslog_port' => 514];
        self::assertSame(1, secureSendPruneAuditHistory($settings, 200000));
        self::assertCount(1, DB::$forwarded);
        self::assertStringStartsWith('action=secure_send_audit_retention {', DB::$forwarded[0][0]);
        $this->seed(2, 1);
        DB::$failForward = true;
        $previousLog = (string) ini_get('error_log');
        $log = tempnam(sys_get_temp_dir(), 'tp-retention-');
        ini_set('error_log', $log);
        try {
            self::assertSame(1, secureSendPruneAuditHistory($settings, 200000));
            self::assertSame(0, $this->rowCount('secure_send_audit'));
            self::assertSame(2, $this->rowCount('log_system'));
            self::assertStringContainsString('retention forwarding failed (RuntimeException)', (string) file_get_contents($log));
            self::assertStringNotContainsString('secret-canary', (string) file_get_contents($log));
        } finally {
            ini_set('error_log', $previousLog);
            unlink($log);
        }
    }
}
