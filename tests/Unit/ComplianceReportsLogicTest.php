<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See LICENSE.md for details.
 */

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with the reports.queries.php
// compliance report handlers (F6 — Compliance Reports & Evidence Export).
require_once __DIR__ . '/../../app/sources/reports.functions.php';

/**
 * Unit tests for the Compliance Reports logic (F6).
 *
 * Covers:
 *   - reportsParseRoleIds()     — users.fonction_id parsing
 *   - reportsBuildAccessMatrix() — user × role × folder grant composition
 *   - reportsPeriodBounds()     — period normalization
 *   - reportsPostureSummary()   — metadata-only posture shaping
 *   - reportsBuildCsv()         — RFC 4180 + formula-injection hardening
 */
class ComplianceReportsLogicTest extends TestCase
{
    public function testSecureSendCalendarPeriodFollowsDaylightSaving(): void
    {
        $timezone = new DateTimeZone('Europe/Paris');
        $now = (new DateTimeImmutable('2026-11-01T12:00:00+01:00'))->getTimestamp();
        $bounds = reportsSecureSendPeriod('2026-03-29', '2026-03-29', $timezone, $now);
        self::assertSame((new DateTimeImmutable('2026-03-29T00:00:00+01:00'))->getTimestamp(), $bounds['start']);
        self::assertSame((new DateTimeImmutable('2026-03-29T23:59:59+02:00'))->getTimestamp(), $bounds['end']);
        self::assertSame(['start' => $now - 30 * 86400, 'end' => $now],
            reportsSecureSendPeriod('', '', $timezone, $now));
    }

    public function testSecureSendCalendarPeriodRejectsInvalidDates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        reportsSecureSendPeriod('2026-02-30', '2026-03-01', new DateTimeZone('Europe/Paris'), 1790000000);
    }

    public function testSecureSendCalendarPeriodRejectsReversedDates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        reportsSecureSendPeriod('2026-12-01', '2026-11-30', new DateTimeZone('Europe/Paris'), 1790000000);
    }

    public function testSecureSendEvidenceCarriesScopeAndOnlyAggregateValuesInBothLanguages(): void
    {
        $timezone = new DateTimeZone('Europe/Paris');
        $now = (new DateTimeImmutable('2026-04-01T15:05:00+02:00'))->getTimestamp();
        $period = reportsSecureSendPeriod('2026-03-29', '2026-03-29', $timezone, $now);
        $formats = [
            'english' => [
                'm/d/Y g:i:s a',
                '03/29/2026 12:00:00 am +01:00',
                '03/29/2026 11:59:59 pm +02:00',
                '04/01/2026 3:05:00 pm +02:00',
            ],
            'french' => [
                'd/m/Y H:i:s',
                '29/03/2026 00:00:00 +01:00',
                '29/03/2026 23:59:59 +02:00',
                '01/04/2026 15:05:00 +02:00',
            ],
        ];
        foreach ($formats as $language => [$format, $start, $end, $generated]) {
            $messages = require __DIR__ . '/../../app/includes/language/' . $language . '.php';
            $translate = static fn (string $key): string => $messages[$key];
            $rows = reportsSecureSendRows([
                'public_unprotected' => '3',
                'invalidated_access' => 2,
                'channel_unknown' => 4,
                'file_count_unknown' => 1,
                'email_indeterminate' => null,
                'originator' => 'private-originator-canary',
            ], $period, $timezone, $format, $now, 90, $translate);
            $values = array_column($rows, 'value', 'indicator');
            self::assertSame($start, $values[$translate('compliance_report_secure_send_period_start')]);
            self::assertSame($end, $values[$translate('compliance_report_secure_send_period_end')]);
            self::assertSame($generated, $values[$translate('compliance_report_secure_send_generated_at')]);
            self::assertSame('Europe/Paris', $values[$translate('compliance_report_secure_send_timezone')]);
            self::assertSame(90, $values[$translate('secure_send_audit_retention_days')]);
            self::assertSame(3, $values[$translate('ops_secure_send_public_unprotected')]);
            self::assertSame(2, $values[$translate('ops_secure_send_invalidated_access')]);
            self::assertSame(4, $values[$translate('ops_secure_send_channel_unknown')]);
            self::assertSame(1, $values[$translate('ops_secure_send_file_count_unknown')]);
            self::assertSame(0, $values[$translate('ops_secure_send_email_indeterminate')]);
            $csv = reportsBuildCsv(['Section', 'Indicator', 'Value'], $rows, ['section', 'indicator', 'value']);
            self::assertStringContainsString($start, $csv);
            self::assertStringContainsString($end, $csv);
            self::assertStringContainsString($generated, $csv);
            self::assertStringContainsString('Europe/Paris', $csv);
            self::assertStringNotContainsString('private-originator-canary', $csv);
        }
    }

    // -------------------------------------------------------------------
    // reportsParseRoleIds()
    // -------------------------------------------------------------------

    public function testParseRoleIdsHandlesStandardList(): void
    {
        $this->assertSame([3, 7, 12], reportsParseRoleIds('3;7;12'));
    }

    public function testParseRoleIdsHandlesEmptyAndNull(): void
    {
        $this->assertSame([], reportsParseRoleIds(null));
        $this->assertSame([], reportsParseRoleIds(''));
        $this->assertSame([], reportsParseRoleIds(';;'));
    }

    public function testParseRoleIdsDropsInvalidAndDuplicates(): void
    {
        $this->assertSame([3, 7], reportsParseRoleIds('3;abc;0;-2;3;7'));
    }

    // -------------------------------------------------------------------
    // reportsBuildAccessMatrix()
    // -------------------------------------------------------------------

    public function testAccessMatrixOneRowPerGrant(): void
    {
        $users = [
            ['id' => 1, 'login' => 'alice', 'name' => 'Alice', 'lastname' => 'A', 'fonction_id' => '1'],
            ['id' => 2, 'login' => 'bob', 'name' => 'Bob', 'lastname' => 'B', 'fonction_id' => '1;2'],
        ];
        $grants = [
            ['role_id' => 1, 'role_title' => 'Dev', 'folder_id' => 10, 'folder_title' => 'Servers', 'type' => 'W'],
            ['role_id' => 2, 'role_title' => 'Ops', 'folder_id' => 11, 'folder_title' => 'Network', 'type' => 'R'],
        ];

        $matrix = reportsBuildAccessMatrix($users, $grants);

        $this->assertCount(3, $matrix);
        // alice: Dev→Servers ; bob: Dev→Servers + Ops→Network
        $this->assertSame(
            ['login' => 'alice', 'name' => 'Alice A', 'role' => 'Dev', 'folder_id' => 10, 'folder' => 'Servers', 'access' => 'W'],
            $matrix[0]
        );
        $this->assertSame('bob', $matrix[1]['login']);
        $this->assertSame('Ops', $matrix[2]['role']);
        $this->assertSame('R', $matrix[2]['access']);
    }

    public function testAccessMatrixSkipsUsersWithoutRolesAndUnknownRoles(): void
    {
        $users = [
            ['id' => 1, 'login' => 'norole', 'name' => '', 'lastname' => '', 'fonction_id' => ''],
            ['id' => 2, 'login' => 'ghost', 'name' => '', 'lastname' => '', 'fonction_id' => '99'],
        ];
        $grants = [
            ['role_id' => 1, 'role_title' => 'Dev', 'folder_id' => 10, 'folder_title' => 'Servers', 'type' => 'W'],
        ];

        $this->assertSame([], reportsBuildAccessMatrix($users, $grants));
    }

    // -------------------------------------------------------------------
    // reportsPeriodBounds()
    // -------------------------------------------------------------------

    public function testPeriodDefaultsToLast30Days(): void
    {
        $now = 1_000_000_000;
        $bounds = reportsPeriodBounds(null, null, $now);

        $this->assertSame($now, $bounds['end']);
        $this->assertSame($now - 30 * 86400, $bounds['start']);
    }

    public function testPeriodUsesProvidedBounds(): void
    {
        $bounds = reportsPeriodBounds('100', '200', 999);

        $this->assertSame(['start' => 100, 'end' => 200], $bounds);
    }

    public function testPeriodSwapsReversedBounds(): void
    {
        $bounds = reportsPeriodBounds('200', '100', 999);

        $this->assertSame(['start' => 100, 'end' => 200], $bounds);
    }

    // -------------------------------------------------------------------
    // reportsPostureSummary()
    // -------------------------------------------------------------------

    public function testPostureSummaryComputesPercentAndSortsBySeverity(): void
    {
        // Live flags use $totalItems (200); scan flags use $scannedItems (50)
        $summary = reportsPostureSummary(
            ['weak' => 5, 'breached' => 20],
            200,
            ['reused' => 8, 'orphaned' => 2],
            50,
            12345
        );

        $this->assertSame(200, $summary['total_items']);
        $this->assertSame(50, $summary['scanned_items']);
        $this->assertSame(12345, $summary['last_scan_at']);

        // Live family first, sorted by count: breached (20) then weak (5)
        $this->assertSame('breached', $summary['issues'][0]['issue']);
        $this->assertSame('live', $summary['issues'][0]['source']);
        $this->assertSame(0, $summary['issues'][0]['as_of']);
        $this->assertSame(10.0, $summary['issues'][0]['percent']);   // 20 / 200
        $this->assertSame('weak', $summary['issues'][1]['issue']);
        $this->assertSame(2.5, $summary['issues'][1]['percent']);    // 5 / 200

        // Scan family after, sorted by count: reused (8) then orphaned (2)
        $this->assertSame('reused', $summary['issues'][2]['issue']);
        $this->assertSame('scan', $summary['issues'][2]['source']);
        $this->assertSame(12345, $summary['issues'][2]['as_of']);
        $this->assertSame(16.0, $summary['issues'][2]['percent']);   // 8 / 50
        $this->assertSame('orphaned', $summary['issues'][3]['issue']);
    }

    public function testPostureSummaryWithNoItemsHasZeroPercent(): void
    {
        $summary = reportsPostureSummary(['weak' => 0], 0, ['reused' => 0], 0, 0);

        $this->assertSame(0.0, $summary['issues'][0]['percent']);
        $this->assertSame(0, $summary['total_items']);
    }

    public function testPostureSummaryClampsNegativeCounts(): void
    {
        // A stale/inconsistent negative count must not appear as negative
        $summary = reportsPostureSummary(['weak' => -3], 100, [], 0, 0);

        $this->assertSame(0, $summary['issues'][0]['items']);
    }

    // -------------------------------------------------------------------
    // reportsBuildCsv()
    // -------------------------------------------------------------------

    public function testCsvBasicStructure(): void
    {
        $csv = reportsBuildCsv(
            ['Login', 'Folder'],
            [['login' => 'alice', 'folder' => 'Servers']],
            ['login', 'folder']
        );

        $this->assertSame("Login,Folder\r\nalice,Servers\r\n", $csv);
    }

    public function testCsvQuotesSeparatorsQuotesAndNewlines(): void
    {
        $csv = reportsBuildCsv(
            ['A'],
            [['a' => "va\"l,ue\nx"]],
            ['a']
        );

        $this->assertSame("A\r\n\"va\"\"l,ue\nx\"\r\n", $csv);
    }

    public function testCsvNeutralisesFormulaInjection(): void
    {
        $csv = reportsBuildCsv(
            ['A'],
            [
                ['a' => '=SUM(1)'],
                ['a' => '+1'],
                ['a' => '-1'],
                ['a' => '@cmd'],
            ],
            ['a']
        );

        $lines = explode("\r\n", trim($csv));
        $this->assertSame("'=SUM(1)", $lines[1]);
        $this->assertSame("'+1", $lines[2]);
        $this->assertSame("'-1", $lines[3]);
        $this->assertSame("'@cmd", $lines[4]);
    }

    public function testCsvMissingColumnsBecomeEmptyCells(): void
    {
        $csv = reportsBuildCsv(['A', 'B'], [['a' => 'x']], ['a', 'b']);

        $this->assertSame("A,B\r\nx,\r\n", $csv);
    }
}
