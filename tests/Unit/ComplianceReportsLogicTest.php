<?php

declare(strict_types=1);

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
