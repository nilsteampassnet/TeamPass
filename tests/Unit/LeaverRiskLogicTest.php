<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free part) shared with the users.queries.php
// leaver-risk handlers (F3 — Leaver / Offboarding Risk view).
require_once __DIR__ . '/../../app/sources/leaver.functions.php';

/**
 * Unit tests for the Leaver / Offboarding Risk logic (F3).
 *
 * Covers:
 *   - leaverRiskItemDisplayStatus()   — flag lifecycle derived at read time
 *   - leaverRiskValidateItemIds()     — server-side eligibility enforcement
 *   - leaverRiskBuildFlagRows()       — rotation-flag row construction
 *   - leaverRiskDecorateReportRows()  — shared filter + status decoration
 */
class LeaverRiskLogicTest extends TestCase
{
    // -------------------------------------------------------------------
    // leaverRiskItemDisplayStatus()
    // -------------------------------------------------------------------

    public function testNeverFlaggedItemIsNotFlagged(): void
    {
        $this->assertSame('not_flagged', leaverRiskItemDisplayStatus(null, 1000, null));
        $this->assertSame('not_flagged', leaverRiskItemDisplayStatus(0, 1000, null));
    }

    public function testFlaggedItemWithoutPasswordChangeIsPending(): void
    {
        // Password last changed BEFORE the flag → rotation still due
        $this->assertSame('pending', leaverRiskItemDisplayStatus(2000, 1000, 'pending'));
    }

    public function testFlaggedItemWithNoKnownPasswordChangeIsPending(): void
    {
        $this->assertSame('pending', leaverRiskItemDisplayStatus(2000, 0, 'pending'));
    }

    public function testPasswordChangedAfterFlagResolvesTheFlag(): void
    {
        $this->assertSame('resolved', leaverRiskItemDisplayStatus(2000, 3000, 'pending'));
    }

    public function testPasswordChangedExactlyAtFlagTimeStaysPending(): void
    {
        // Strict inequality: a change at the very same second does not prove
        // the rotation happened after the flag was raised.
        $this->assertSame('pending', leaverRiskItemDisplayStatus(2000, 2000, 'pending'));
    }

    public function testDismissedFlagStaysDismissedEvenAfterPasswordChange(): void
    {
        $this->assertSame('dismissed', leaverRiskItemDisplayStatus(2000, 3000, 'dismissed'));
    }

    // -------------------------------------------------------------------
    // leaverRiskValidateItemIds()
    // -------------------------------------------------------------------

    public function testValidateKeepsOnlyEligibleIds(): void
    {
        $this->assertSame(
            [2, 3],
            leaverRiskValidateItemIds([1, 2, 3], [2, 3, 4])
        );
    }

    public function testValidateRejectsEverythingWhenNoEligibleId(): void
    {
        $this->assertSame([], leaverRiskValidateItemIds([1, 2, 3], []));
    }

    public function testValidateDeduplicatesAndCastsToInt(): void
    {
        $this->assertSame(
            [5],
            leaverRiskValidateItemIds(['5', 5, '5 '], [5])
        );
    }

    public function testValidateWithEmptyRequestReturnsEmpty(): void
    {
        $this->assertSame([], leaverRiskValidateItemIds([], [1, 2]));
    }

    // -------------------------------------------------------------------
    // leaverRiskBuildFlagRows()
    // -------------------------------------------------------------------

    public function testBuildFlagRowsProducesOneRowPerItem(): void
    {
        $rows = leaverRiskBuildFlagRows([10, 20], 7, 1, 123456);

        $this->assertCount(2, $rows);
        $this->assertSame(
            [
                'item_id' => 10,
                'flagged_at' => 123456,
                'flagged_by' => 1,
                'leaver_id' => 7,
                'reason' => 'leaver',
                'status' => 'pending',
            ],
            $rows[0]
        );
        $this->assertSame(20, $rows[1]['item_id']);
    }

    public function testBuildFlagRowsSkipsInvalidIds(): void
    {
        $rows = leaverRiskBuildFlagRows([0, -3, 42], 7, 1, 123456);

        $this->assertCount(1, $rows);
        $this->assertSame(42, $rows[0]['item_id']);
    }

    public function testBuildFlagRowsWithNoItemsReturnsEmpty(): void
    {
        $this->assertSame([], leaverRiskBuildFlagRows([], 7, 1, 123456));
    }

    // -------------------------------------------------------------------
    // leaverRiskDecorateReportRows()
    // -------------------------------------------------------------------

    public function testDecorateDropsItemsWithNoOtherReader(): void
    {
        $rows = leaverRiskDecorateReportRows([
            ['item_id' => 1, 'other_users' => 0, 'last_pw_change' => 100, 'flagged_at' => null, 'flag_status' => null],
            ['item_id' => 2, 'other_users' => 3, 'last_pw_change' => 100, 'flagged_at' => null, 'flag_status' => null],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['item_id']);
    }

    public function testDecorateAddsDisplayStatus(): void
    {
        $rows = leaverRiskDecorateReportRows([
            ['item_id' => 1, 'other_users' => 2, 'last_pw_change' => 100, 'flagged_at' => null, 'flag_status' => null],
            ['item_id' => 2, 'other_users' => 2, 'last_pw_change' => 100, 'flagged_at' => 50, 'flag_status' => 'pending'],
            ['item_id' => 3, 'other_users' => 2, 'last_pw_change' => 100, 'flagged_at' => 200, 'flag_status' => 'pending'],
        ]);

        $this->assertSame('not_flagged', $rows[0]['display_status']);
        $this->assertSame('resolved', $rows[1]['display_status']);
        $this->assertSame('pending', $rows[2]['display_status']);
    }

    public function testDecorateHandlesStringNumbersFromSql(): void
    {
        // MeekroDB returns strings — the decoration must cast safely.
        $rows = leaverRiskDecorateReportRows([
            ['item_id' => 1, 'other_users' => '2', 'last_pw_change' => '300', 'flagged_at' => '200', 'flag_status' => 'pending'],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('resolved', $rows[0]['display_status']);
    }

    public function testDecorateWithEmptyInputReturnsEmpty(): void
    {
        $this->assertSame([], leaverRiskDecorateReportRows([]));
    }

    // -------------------------------------------------------------------
    // leaverRiskNormalizeFolderIds()
    // -------------------------------------------------------------------

    public function testNormalizeFolderIdsCastsAndDeduplicates(): void
    {
        $this->assertSame([3, 7, 12], leaverRiskNormalizeFolderIds(['3', 7, '3', '12', 7]));
    }

    public function testNormalizeFolderIdsDropsInvalidValues(): void
    {
        $this->assertSame([5], leaverRiskNormalizeFolderIds([0, -2, 'abc', '', null, 5]));
    }

    public function testNormalizeFolderIdsWithEmptyInput(): void
    {
        $this->assertSame([], leaverRiskNormalizeFolderIds([]));
    }

    // -------------------------------------------------------------------
    // leaverRiskExpandFolderScope()
    // -------------------------------------------------------------------

    public function testExpandFolderScopeMergesDescendants(): void
    {
        $scope = leaverRiskExpandFolderScope(
            [10, 20],
            [[11, 12], [21]]
        );

        $this->assertSame([10, 20, 11, 12, 21], $scope);
    }

    public function testExpandFolderScopeDeduplicatesOverlappingSubtrees(): void
    {
        // Selecting a parent and one of its children must not duplicate ids
        $scope = leaverRiskExpandFolderScope(
            [10, 11],
            [[11, 12], [12]]
        );

        $this->assertSame([10, 11, 12], $scope);
    }

    public function testExpandFolderScopeWithoutDescendants(): void
    {
        $this->assertSame([10], leaverRiskExpandFolderScope([10], [[]]));
        $this->assertSame([], leaverRiskExpandFolderScope([], []));
    }
}
