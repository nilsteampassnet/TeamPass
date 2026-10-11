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

namespace TeamPass\Tests\SecureSendRetentionMaintenance;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../app/sources/secure_send_retention.php';

/** Isolate task orchestration from separately tested database transactions. */
class MaintenanceProbe
{
    public static bool $fail = false;
    public static ?\Throwable $failure = null;
    public static int $deleted = 0;
    public static array $statuses = [];
}

/** Simulate either a committed batch or a rolled-back failure. */
function secureSendPruneAuditHistory(array $settings): int
{
    if (MaintenanceProbe::$failure !== null) {
        throw MaintenanceProbe::$failure;
    }
    if (MaintenanceProbe::$fail) {
        throw new RuntimeException('Synthetic storage failure: secret-canary');
    }
    return MaintenanceProbe::$deleted;
}

/** Capture the existing task log contract. */
function doLog(string $status, string $job, int $enabled, int $id): void
{
    MaintenanceProbe::$statuses[] = [$status, $job, $enabled, $id];
}

class SecureSendRetentionMaintenanceTest extends TestCase
{
    public function testTaskLogsOnlyKnownRefusalsAndSingleLineDatabaseDiagnostics(): void
    {
        require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
        $source = (string) file_get_contents(__DIR__ . '/../../app/scripts/task_maintenance_clean_orphan_objects.php');
        $start = strpos($source, '$prunedSecureSendEvents = 0;');
        $end = strpos($source, '/**', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $previousLog = (string) ini_get('error_log');
        $log = tempnam(sys_get_temp_dir(), 'tp-retention-reasons-');
        ini_set('error_log', $log);
        $messages = ["Table 'fixture_secure_send_audit' doesn't exist", 'Unknown column occurred_at',
            "Échec de connexion /srv/mysql/data\r\n\tsecond line", "Invalid UTF-8 /srv/mysql/\xB1\nsecond line"];
        $failures = [new \SecureSendRetentionRefusal('invalid_policy'), new \SecureSendRetentionRefusal('unsafe_tables')];
        foreach ($messages as $message) {
            $failures[] = new \MeekroDBException($message, 'DELETE secret-canary FROM private_table', 1146);
        }
        $failures[] = new RuntimeException('Synthetic storage failure: secret-canary');
        $failures[] = new \InvalidArgumentException('Invalid policy: secret-canary');
        try {
            foreach ($failures as $failure) {
                $before = strlen((string) file_get_contents($log));
                MaintenanceProbe::$failure = $failure;
                MaintenanceProbe::$statuses = [];
                $SETTINGS = [];
                $integritySummary = ['count' => 2];
                $prunedRevisions = 3;
                $prunedIdempotencyRecords = 4;
                $logID = 12;
                ob_start();
                try {
                    eval('namespace ' . __NAMESPACE__ . '; use Throwable;' . substr($source, $start, $end - $start));
                    $output = (string) ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                $diagnostic = substr((string) file_get_contents($log), $before);
                self::assertSame([['error', '', 1, 12]], MaintenanceProbe::$statuses);
                self::assertStringContainsString('0 Secure Send audit event(s) pruned.', $output);
                self::assertStringNotContainsString($failure->getMessage(), $output);
                self::assertStringNotContainsString('secret-canary', $output . $diagnostic);
                self::assertStringNotContainsString('DELETE', $diagnostic);
                self::assertSame(1, substr_count($diagnostic, "\n"));
                if ($failure instanceof \SecureSendRetentionRefusal) {
                    self::assertStringContainsString($failure->getMessage(), $diagnostic);
                } elseif ($failure instanceof \MeekroDBException) {
                    self::assertStringContainsString('MeekroDBException): '
                        . json_encode($failure->getMessage(), JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $diagnostic);
                } else {
                    self::assertStringContainsString('(' . get_class($failure) . ')', $diagnostic);
                }
            }
        } finally {
            MaintenanceProbe::$failure = null;
            ini_set('error_log', $previousLog);
            unlink($log);
        }
    }

    public function testTaskReportsCommittedCountsAndFailuresWithoutSecrets(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/scripts/task_maintenance_clean_orphan_objects.php');
        $start = strpos($source, '$prunedSecureSendEvents = 0;');
        $end = strpos($source, '/**', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        self::assertStringContainsString("require_once __DIR__.'/../sources/secure_send_retention.php';", $source);
        self::assertLessThan($start, strpos($source, '$prunedIdempotencyRecords = pruneApiIdempotencyRecords();'));
        $previousLog = (string) ini_get('error_log');
        $log = tempnam(sys_get_temp_dir(), 'tp-retention-task-');
        ini_set('error_log', $log);
        try {
            foreach ([[false, 0], [false, 1000], [true, 0]] as [$fail, $deleted]) {
                MaintenanceProbe::$fail = $fail;
                MaintenanceProbe::$deleted = $deleted;
                MaintenanceProbe::$statuses = [];
                $SETTINGS = [];
                $integritySummary = ['count' => 2];
                $prunedRevisions = 3;
                $prunedIdempotencyRecords = 4;
                $logID = 12;
                ob_start();
                try {
                    eval('namespace ' . __NAMESPACE__ . '; use Throwable;' . substr($source, $start, $end - $start));
                    $output = (string) ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                self::assertSame([[$fail ? 'error' : 'completed', '', 1, 12]], MaintenanceProbe::$statuses);
                self::assertStringContainsString($deleted . ' Secure Send audit event(s) pruned.', $output);
                self::assertSame($fail, str_contains($output, 'Secure Send retention failed'));
                self::assertStringContainsString('2 active corrupted item(s). 3 journal entry(ies) pruned. 4 API idempotency record(s) pruned.', $output);
                self::assertStringNotContainsString('secret-canary', $output);
            }
            self::assertStringContainsString('retention failed (RuntimeException)', (string) file_get_contents($log));
            self::assertStringNotContainsString('secret-canary', (string) file_get_contents($log));
        } finally {
            ini_set('error_log', $previousLog);
            unlink($log);
        }
    }
}
