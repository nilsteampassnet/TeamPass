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

    // -------------------------------------------------------------------
    // reviewsReviewerFolderScope() — manager delegation perimeter
    // -------------------------------------------------------------------

    public function testAdminHasNoFolderRestriction(): void
    {
        // Admin => empty array means "no restriction"; the input is ignored.
        $this->assertSame([], reviewsReviewerFolderScope(true, [1, 2, 3], [2]));
    }

    public function testManagerScopeExcludesPersonalFolders(): void
    {
        $scope = reviewsReviewerFolderScope(false, [10, 11, 12, 13], [11, 13]);

        $this->assertSame([10, 12], $scope);
    }

    public function testManagerScopeCastsAndDeduplicates(): void
    {
        $scope = reviewsReviewerFolderScope(false, ['5', 5, '7', 0, -3, ''], []);

        $this->assertSame([5, 7], $scope);
    }

    public function testManagerWithNoAccessibleFolderHasEmptyScope(): void
    {
        $this->assertSame([], reviewsReviewerFolderScope(false, [], []));
    }

    // -------------------------------------------------------------------
    // reviewsCanActOnFolder()
    // -------------------------------------------------------------------

    public function testAdminCanActOnAnyFolder(): void
    {
        $this->assertTrue(reviewsCanActOnFolder(999, true, []));
    }

    public function testManagerCanActOnlyWithinPerimeter(): void
    {
        $this->assertTrue(reviewsCanActOnFolder(12, false, [10, 12]));
        $this->assertFalse(reviewsCanActOnFolder(11, false, [10, 12]));
        $this->assertFalse(reviewsCanActOnFolder(12, false, []));
    }

    // -------------------------------------------------------------------
    // reviewsReviewerWriteScope() — revocation needs write access
    // -------------------------------------------------------------------

    public function testAdminWriteScopeIsUnrestricted(): void
    {
        $this->assertSame([], reviewsReviewerWriteScope(true, [1, 2, 3], [2]));
    }

    public function testManagerWriteScopeExcludesReadOnlyFolders(): void
    {
        // Perimeter 10,11,12 with 11 read-only -> can revoke only on 10,12
        $writeScope = reviewsReviewerWriteScope(false, [10, 11, 12], [11]);

        $this->assertSame([10, 12], $writeScope);
    }

    public function testManagerCanAttestReadOnlyButNotRevoke(): void
    {
        $readScope = [10, 11];        // both visible (can attest)
        $writeScope = reviewsReviewerWriteScope(false, $readScope, [11]); // 11 read-only

        // Attest allowed on both, revoke only on the writable one
        $this->assertTrue(reviewsCanActOnFolder(11, false, $readScope));
        $this->assertFalse(reviewsCanActOnFolder(11, false, $writeScope));
        $this->assertTrue(reviewsCanActOnFolder(10, false, $writeScope));
    }

    public function testManagerWriteScopeEmptyWhenAllReadOnly(): void
    {
        $this->assertSame([], reviewsReviewerWriteScope(false, [10, 11], [10, 11]));
    }

    // -------------------------------------------------------------------
    // reviewsCanManageCampaign() — ownership of a campaign
    // -------------------------------------------------------------------

    public function testAdminManagesAnyCampaign(): void
    {
        $this->assertTrue(reviewsCanManageCampaign(true, 42, 7));
    }

    public function testManagerManagesOnlyOwnCampaigns(): void
    {
        $this->assertTrue(reviewsCanManageCampaign(false, 7, 7));
        $this->assertFalse(reviewsCanManageCampaign(false, 42, 7));
    }
}
