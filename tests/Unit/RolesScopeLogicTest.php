<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) behind reconcileManualUserRoles(), used when
// saving the roles of a user from the edit form and from the rights propagation.
require_once __DIR__ . '/../../app/sources/roles_scope.functions.php';

/**
 * Unit tests for the role assignment scope logic.
 *
 * The user edit form replaces the whole manual role set, but a manager only sees
 * the roles they are entitled to grant. These tests pin the invariant that makes
 * that safe: a role outside the caller's scope is never added nor removed by a
 * submission, whatever the submission contains.
 */
class RolesScopeLogicTest extends TestCase
{
    // -------------------------------------------------------------------
    // rolesScopeNormalizeIds()
    // -------------------------------------------------------------------

    public function testNormalizeDropsEmptyAndNonNumericEntries(): void
    {
        // explode(';', '') yields [''] — the classic source of a phantom role.
        $this->assertSame([], rolesScopeNormalizeIds(['']));
        $this->assertSame([], rolesScopeNormalizeIds([]));
        $this->assertSame([3, 5], rolesScopeNormalizeIds(['3', '', 'abc', '5']));
    }

    public function testNormalizeCastsToIntAndRejectsZeroOrNegative(): void
    {
        $this->assertSame([1, 2], rolesScopeNormalizeIds(['1', 2]));
        $this->assertSame([], rolesScopeNormalizeIds([0, '0', -4]));
    }

    // -------------------------------------------------------------------
    // mergeGrantableRoleSets() — an administrator grants everything
    // -------------------------------------------------------------------

    public function testAdministratorScopeAppliesTheSubmissionAsIs(): void
    {
        // Every role is grantable: the submitted set is the final set.
        $result = mergeGrantableRoleSets([1, 2, 3], [2, 4], [1, 2, 3, 4]);

        sort($result);
        $this->assertSame([2, 4], $result);
    }

    // -------------------------------------------------------------------
    // mergeGrantableRoleSets() — the regression this logic exists for
    // -------------------------------------------------------------------

    public function testRoleOutsideScopeIsPreservedWhenNotSubmitted(): void
    {
        // The user holds 7 (manager's role) and 9 (out of scope, not in the form).
        // Saving the form must not revoke 9.
        $result = mergeGrantableRoleSets([7, 9], [7], [7]);

        sort($result);
        $this->assertSame([7, 9], $result);
    }

    public function testRoleOutsideScopeCannotBeRevokedByAForgedSubmission(): void
    {
        // Submitting an empty set still leaves the out-of-scope roles in place.
        $this->assertSame([9], mergeGrantableRoleSets([7, 9], [], [7]));
    }

    public function testRoleOutsideScopeCannotBeGrantedByAForgedSubmission(): void
    {
        // 42 is neither held by the user nor grantable: it must not be written.
        $result = mergeGrantableRoleSets([7], [7, 42], [7]);

        $this->assertSame([7], $result);
    }

    public function testInScopeRoleCanStillBeAddedAndRevoked(): void
    {
        // The caller may grant 7 and 8: they keep full control over those two.
        $this->assertSame([8], mergeGrantableRoleSets([7], [8], [7, 8]));
    }

    public function testMixedCaseKeepsOutOfScopeAndAppliesInScope(): void
    {
        // Holds 7 (in scope), 9 and 10 (out of scope). Submits 8 instead of 7.
        $result = mergeGrantableRoleSets([7, 9, 10], [8], [7, 8]);

        sort($result);
        $this->assertSame([8, 9, 10], $result);
    }

    // -------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------

    public function testCallerWithoutAnyGrantableRoleChangesNothing(): void
    {
        // A manager holding no role can neither add nor remove anything.
        $result = mergeGrantableRoleSets([3, 4], [1, 2, 3], []);

        sort($result);
        $this->assertSame([3, 4], $result);
    }

    public function testResultIsUniqueAndReindexed(): void
    {
        $result = mergeGrantableRoleSets([5, 5], ['5', 5], [5]);

        $this->assertSame([5], $result);
        $this->assertSame([0], array_keys($result));
    }

    public function testStringAndIntegerIdsAreEquivalent(): void
    {
        // GROUP_CONCAT returns strings, the session stores strings, the form
        // posts strings: the comparison must never depend on the type.
        $this->assertSame(
            mergeGrantableRoleSets([7, 9], [7], [7]),
            mergeGrantableRoleSets(['7', '9'], ['7'], ['7'])
        );
    }
}
