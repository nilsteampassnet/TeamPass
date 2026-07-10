<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with the reviews.queries.php
// recertification handlers (F2 — Access Recertification Campaigns).
require_once __DIR__ . '/../../app/sources/reviews.functions.php';

/**
 * Unit tests for the Access Recertification logic (F2).
 *
 * Covers:
 *   - reviewsValidDecision()      — decision vocabulary
 *   - reviewsCanDecide()          — decisions are immutable, campaign must be open
 *   - reviewsCanClose()           — evidence completeness rule
 *   - reviewsBuildSnapshotRows()  — grant snapshot construction
 *   - reviewsProgress()           — progress counters
 */
class AccessReviewsLogicTest extends TestCase
{
    // -------------------------------------------------------------------
    // reviewsValidDecision()
    // -------------------------------------------------------------------

    public function testOnlyAttestedAndRevokedAreValidDecisions(): void
    {
        $this->assertTrue(reviewsValidDecision('attested'));
        $this->assertTrue(reviewsValidDecision('revoked'));
        $this->assertFalse(reviewsValidDecision('pending'));
        $this->assertFalse(reviewsValidDecision(''));
        $this->assertFalse(reviewsValidDecision('ATTESTED'));
        $this->assertFalse(reviewsValidDecision('delete'));
    }

    // -------------------------------------------------------------------
    // reviewsCanDecide()
    // -------------------------------------------------------------------

    public function testPendingItemOfOpenCampaignCanBeDecided(): void
    {
        $this->assertTrue(reviewsCanDecide('open', 'pending'));
    }

    public function testDecisionsAreImmutable(): void
    {
        // Already decided items cannot be re-decided (evidence integrity)
        $this->assertFalse(reviewsCanDecide('open', 'attested'));
        $this->assertFalse(reviewsCanDecide('open', 'revoked'));
    }

    public function testClosedCampaignAcceptsNoDecision(): void
    {
        $this->assertFalse(reviewsCanDecide('closed', 'pending'));
    }

    // -------------------------------------------------------------------
    // reviewsCanClose()
    // -------------------------------------------------------------------

    public function testCampaignClosesOnlyWhenNothingIsPending(): void
    {
        $this->assertTrue(reviewsCanClose('open', 0));
        $this->assertFalse(reviewsCanClose('open', 1));
        $this->assertFalse(reviewsCanClose('open', 42));
    }

    public function testClosedCampaignCannotBeClosedAgain(): void
    {
        $this->assertFalse(reviewsCanClose('closed', 0));
    }

    // -------------------------------------------------------------------
    // reviewsBuildSnapshotRows()
    // -------------------------------------------------------------------

    public function testSnapshotBuildsOneRowPerGrant(): void
    {
        $grants = [
            ['role_id' => 1, 'role_title' => 'Dev', 'folder_id' => 10, 'folder_title' => 'Servers', 'type' => 'W'],
            ['role_id' => 2, 'role_title' => 'Ops', 'folder_id' => 11, 'folder_title' => 'Network', 'type' => 'R'],
        ];

        $rows = reviewsBuildSnapshotRows($grants, 7);

        $this->assertCount(2, $rows);
        $this->assertSame(
            [
                'review_id' => 7,
                'role_id' => 1,
                'role_title' => 'Dev',
                'folder_id' => 10,
                'folder_title' => 'Servers',
                'access_type' => 'W',
                'decision' => 'pending',
            ],
            $rows[0]
        );
    }

    public function testSnapshotDeduplicatesRoleFolderPairs(): void
    {
        $grants = [
            ['role_id' => 1, 'role_title' => 'Dev', 'folder_id' => 10, 'folder_title' => 'Servers', 'type' => 'W'],
            ['role_id' => 1, 'role_title' => 'Dev', 'folder_id' => 10, 'folder_title' => 'Servers', 'type' => 'R'],
        ];

        $this->assertCount(1, reviewsBuildSnapshotRows($grants, 7));
    }

    public function testSnapshotSkipsInvalidGrants(): void
    {
        $grants = [
            ['role_id' => 0, 'role_title' => 'X', 'folder_id' => 10, 'folder_title' => 'A', 'type' => 'W'],
            ['role_id' => 1, 'role_title' => 'Y', 'folder_id' => 0, 'folder_title' => 'B', 'type' => 'W'],
        ];

        $this->assertSame([], reviewsBuildSnapshotRows($grants, 7));
    }

    // -------------------------------------------------------------------
    // reviewsProgress()
    // -------------------------------------------------------------------

    public function testProgressComputesTotalsAndPercent(): void
    {
        $progress = reviewsProgress(2, 5, 3);

        $this->assertSame(10, $progress['total']);
        $this->assertSame(8, $progress['decided']);
        $this->assertSame(80.0, $progress['percent']);
    }

    public function testProgressOnEmptyCampaignIsZero(): void
    {
        $progress = reviewsProgress(0, 0, 0);

        $this->assertSame(0, $progress['total']);
        $this->assertSame(0.0, $progress['percent']);
    }
}
