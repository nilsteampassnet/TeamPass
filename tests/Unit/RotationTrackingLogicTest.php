<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with the reports.queries.php
// rotation report handlers (F5 — Rotation Policy & Tracking).
require_once __DIR__ . '/../../app/sources/rotation.functions.php';

/**
 * Unit tests for the Rotation Policy & Tracking logic (F5).
 *
 * Covers:
 *   - rotationSlaStatus()       — SLA classification (no_sla/unknown/ok/due_soon/overdue)
 *   - rotationBuildOverdueRows() — report shaping, filtering and ordering
 *   - rotationSlaCoverage()     — per-folder coverage rows + summary
 */
class RotationTrackingLogicTest extends TestCase
{
    private const NOW = 1_760_000_000;
    private const DAY = 86400;

    // -------------------------------------------------------------------
    // rotationSlaStatus()
    // -------------------------------------------------------------------

    public function testStatusNoSlaWhenPeriodNotSet(): void
    {
        $this->assertSame('no_sla', rotationSlaStatus(0, self::NOW, self::NOW)['status']);
        $this->assertSame('no_sla', rotationSlaStatus(-5, self::NOW, self::NOW)['status']);
    }

    public function testStatusUnknownWhenNoChangeDate(): void
    {
        // A missing change date must never be treated as epoch 0 (would claim
        // decades of overdue).
        $sla = rotationSlaStatus(30, 0, self::NOW);
        $this->assertSame('unknown', $sla['status']);
        $this->assertSame(0, $sla['days_overdue']);
    }

    public function testStatusOkFarFromDueDate(): void
    {
        $sla = rotationSlaStatus(90, self::NOW - 10 * self::DAY, self::NOW);
        $this->assertSame('ok', $sla['status']);
        $this->assertSame(80, $sla['days_left']);
    }

    public function testStatusDueSoonInsideLookAheadWindow(): void
    {
        $sla = rotationSlaStatus(30, self::NOW - 20 * self::DAY, self::NOW, 14);
        $this->assertSame('due_soon', $sla['status']);
        $this->assertSame(10, $sla['days_left']);
    }

    public function testStatusOverdueWithDayCount(): void
    {
        $sla = rotationSlaStatus(30, self::NOW - 45 * self::DAY, self::NOW);
        $this->assertSame('overdue', $sla['status']);
        $this->assertSame(15, $sla['days_overdue']);
        $this->assertSame(0, $sla['days_left']);
    }

    public function testStatusOverdueAtExactDueDate(): void
    {
        // Due date reached exactly now => overdue (0 full days late).
        $sla = rotationSlaStatus(30, self::NOW - 30 * self::DAY, self::NOW);
        $this->assertSame('overdue', $sla['status']);
        $this->assertSame(0, $sla['days_overdue']);
    }

    // -------------------------------------------------------------------
    // rotationBuildOverdueRows()
    // -------------------------------------------------------------------

    /**
     * Build a raw record as returned by the report SQL.
     */
    private function record(int $id, string $label, int $slaDays, int $ageDays): array
    {
        return [
            'item_id' => $id,
            'label' => $label,
            'folder_id' => 10,
            'folder_title' => 'Servers',
            'sla_days' => $slaDays,
            'last_change' => self::NOW - $ageDays * self::DAY,
        ];
    }

    public function testOverdueRowsKeepOnlyActionableStatuses(): void
    {
        $rows = rotationBuildOverdueRows([
            $this->record(1, 'fresh', 90, 5),          // ok — dropped
            $this->record(2, 'late', 30, 45),          // overdue — kept
            $this->record(3, 'soon', 30, 20),          // due_soon — kept
            ['item_id' => 4, 'label' => 'nodate', 'folder_id' => 1, 'folder_title' => 'X', 'sla_days' => 30, 'last_change' => 0], // unknown — dropped
        ], self::NOW);

        $this->assertSame([2, 3], array_column($rows, 'item_id'));
    }

    public function testOverdueRowsOrderedMostOverdueFirstThenSoonestDue(): void
    {
        $rows = rotationBuildOverdueRows([
            $this->record(1, 'soon-late', 30, 25),   // due_soon, 5 days left
            $this->record(2, 'very-late', 30, 90),   // overdue by 60
            $this->record(3, 'soon-early', 30, 28),  // due_soon, 2 days left
            $this->record(4, 'late', 30, 40),        // overdue by 10
        ], self::NOW);

        $this->assertSame([2, 4, 3, 1], array_column($rows, 'item_id'));
        $this->assertSame('overdue', $rows[0]['status']);
        $this->assertSame(60, $rows[0]['days_overdue']);
        $this->assertSame('due_soon', $rows[2]['status']);
        $this->assertSame(2, $rows[2]['days_left']);
    }

    public function testOverdueRowsCarryHumanReadableDates(): void
    {
        $rows = rotationBuildOverdueRows([$this->record(1, 'late', 30, 45)], self::NOW);

        $this->assertSame(date('Y-m-d', self::NOW - 45 * self::DAY), $rows[0]['last_change']);
        $this->assertSame(date('Y-m-d', self::NOW - 15 * self::DAY), $rows[0]['due_at']);
    }

    // -------------------------------------------------------------------
    // rotationSlaCoverage()
    // -------------------------------------------------------------------

    public function testCoverageSummaryCountsFoldersWithSla(): void
    {
        $coverage = rotationSlaCoverage([
            ['folder_id' => 1, 'folder_title' => 'A', 'sla_days' => 30, 'items' => 5, 'overdue' => 1],
            ['folder_id' => 2, 'folder_title' => 'B', 'sla_days' => 0, 'items' => 3, 'overdue' => 0],
            ['folder_id' => 3, 'folder_title' => 'C', 'sla_days' => 90, 'items' => 0, 'overdue' => 0],
        ]);

        $this->assertSame(3, $coverage['summary']['folders_total']);
        $this->assertSame(2, $coverage['summary']['folders_with_sla']);
        $this->assertSame(66.7, $coverage['summary']['coverage_percent']);
    }

    public function testCoverageOrdersOverdueThenUncoveredThenAlpha(): void
    {
        $coverage = rotationSlaCoverage([
            ['folder_id' => 1, 'folder_title' => 'Zeta', 'sla_days' => 30, 'items' => 5, 'overdue' => 0],
            ['folder_id' => 2, 'folder_title' => 'Beta', 'sla_days' => 0, 'items' => 3, 'overdue' => 0],
            ['folder_id' => 3, 'folder_title' => 'Alpha', 'sla_days' => 30, 'items' => 8, 'overdue' => 4],
            ['folder_id' => 4, 'folder_title' => 'Empty', 'sla_days' => 0, 'items' => 0, 'overdue' => 0],
        ]);

        // Overdue first, then uncovered folders holding items, then alphabetical.
        $this->assertSame([3, 2, 4, 1], array_column($coverage['rows'], 'folder_id'));
    }

    public function testCoverageNeutralisesOverdueOnFoldersWithoutSla(): void
    {
        // A stale aggregate must not claim overdue items on a folder with no SLA.
        $coverage = rotationSlaCoverage([
            ['folder_id' => 1, 'folder_title' => 'A', 'sla_days' => 0, 'items' => 3, 'overdue' => 2],
        ]);

        $this->assertSame(0, $coverage['rows'][0]['overdue']);
    }

    public function testCoverageEmptyInput(): void
    {
        $coverage = rotationSlaCoverage([]);

        $this->assertSame([], $coverage['rows']);
        $this->assertSame(0, $coverage['summary']['folders_total']);
        $this->assertSame(0.0, $coverage['summary']['coverage_percent']);
    }
}
