<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with the adapters in app/sources/main.functions.php.
require_once __DIR__ . '/../../app/sources/security_posture_logic.php';

/**
 * Behavioural unit tests for the Security Posture authorization boundary and password health.
 *
 * These exercise the decision logic itself — not the presence of a string in a source file — so a
 * change in the grant/denial/restriction contract fails here with a readable truth-table diff.
 *
 * Covers:
 *   - securityPostureResolveAuthorizedFolders() — grants, denial priority, personal-tree scoping
 *   - securityPostureParseIdList()              — the ';'-separated id column format
 *   - securityPostureItemRestrictionAllows()    — per-item user/role restrictions
 *   - securityPasswordHealthClassify()          — the four health states and both thresholds
 */
class SecurityPostureLogicTest extends TestCase
{
    private const WEAK_COMPLEXITY = 38; // TP_PW_STRENGTH_3
    private const MIN_LENGTH = 12;      // TP_SECURITY_PASSWORD_MIN_LENGTH

    // ---------------------------------------------------------------- folders

    public function testDirectGrantMakesASharedFolderReadable(): void
    {
        self::assertSame(
            [10],
            securityPostureResolveAuthorizedFolders([10], [], [], [], [])
        );
    }

    public function testReadableRoleGrantMakesASharedFolderReadable(): void
    {
        self::assertSame(
            [20],
            securityPostureResolveAuthorizedFolders([], [20], [], [], [])
        );
    }

    public function testNoGrantAtAllYieldsNoFolder(): void
    {
        self::assertSame(
            [],
            securityPostureResolveAuthorizedFolders([], [], [], [], [])
        );
    }

    public function testExplicitDenialBeatsBothDirectAndRoleGrants(): void
    {
        self::assertSame(
            [11],
            securityPostureResolveAuthorizedFolders([10, 11], [10], [10], [], [])
        );
    }

    public function testASharedGrantCanNeverReachAPersonalFolder(): void
    {
        // A stale or inconsistent grant on somebody's personal folder must not open it.
        self::assertSame(
            [10],
            securityPostureResolveAuthorizedFolders([10, 99], [99], [], [], [99, 98])
        );
    }

    public function testOwnPersonalTreeIsReadableWithoutAnyGrant(): void
    {
        self::assertSame(
            [50, 51],
            securityPostureResolveAuthorizedFolders([], [], [], [50, 51], [50, 51, 99])
        );
    }

    public function testOwnPersonalTreeIsNotSubjectToTheDenialList(): void
    {
        // identUserGetPFList() only diffs the denials against the shared set.
        self::assertSame(
            [50],
            securityPostureResolveAuthorizedFolders([], [], [50], [50], [50])
        );
    }

    public function testAnotherUsersPersonalTreeStaysOutEvenForAManagerOfThatUser(): void
    {
        // Managing a user grants no access to their personal folders — folders 90/91 are theirs.
        $resolved = securityPostureResolveAuthorizedFolders([10], [20], [], [50], [50, 90, 91]);

        self::assertSame([10, 20, 50], $resolved);
        self::assertNotContains(90, $resolved);
        self::assertNotContains(91, $resolved);
    }

    public function testAdministratorWithoutGrantsResolvesToOwnPersonalTreeOnly(): void
    {
        // identAdmin() grants folder browsing in session but writes no users_groups/users_roles
        // row, and show_details_item returns show_details = 0 on every non-personal item.
        self::assertSame(
            [50],
            securityPostureResolveAuthorizedFolders([], [], [], [50], [50, 90])
        );
    }

    public function testResultIsDeduplicatedAndSorted(): void
    {
        self::assertSame(
            [3, 7, 12],
            securityPostureResolveAuthorizedFolders([12, 3, 12], [7, 3], [], [], [])
        );
    }

    public function testStringIdsFromTheDatabaseAreNormalisedToIntegers(): void
    {
        self::assertSame(
            [4, 8],
            securityPostureResolveAuthorizedFolders(['8', '4'], [], ['9'], [], ['9'])
        );
    }

    // ------------------------------------------------------------- id parsing

    /**
     * @return array<string, array{0: string|null, 1: int[]}>
     */
    public static function idListProvider(): array
    {
        return [
            'null' => [null, []],
            'empty' => ['', []],
            'blank' => ['   ', []],
            'single' => ['7', [7]],
            'several' => ['7;12;3', [7, 12, 3]],
            'trailing separator' => ['7;12;', [7, 12]],
            'duplicates' => ['7;7;12', [7, 12]],
            'non numeric ignored' => ['7;abc;12', [7, 12]],
            'zero ignored' => ['0;7', [7]],
            'negative ignored' => ['-3;7', [7]],
        ];
    }

    /**
     * @param int[] $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('idListProvider')]
    public function testIdListParsing(?string $raw, array $expected): void
    {
        self::assertSame($expected, securityPostureParseIdList($raw));
    }

    // -------------------------------------------------------- item restrictions

    public function testUnrestrictedItemIsOpenToEveryFolderMember(): void
    {
        self::assertTrue(securityPostureItemRestrictionAllows(null, [], 5, []));
        self::assertTrue(securityPostureItemRestrictionAllows('', [], 5, [3]));
    }

    public function testUserListedInRestrictedToIsAllowed(): void
    {
        self::assertTrue(securityPostureItemRestrictionAllows('4;5;6', [], 5, []));
    }

    public function testUserAbsentFromRestrictedToIsBlocked(): void
    {
        self::assertFalse(securityPostureItemRestrictionAllows('4;6', [], 5, []));
    }

    public function testRestrictedToDoesNotMatchOnAnIdSubstring(): void
    {
        // The ';'-delimited comparison must not let user 5 through on a list holding 15 or 51.
        self::assertFalse(securityPostureItemRestrictionAllows('15;51', [], 5, []));
    }

    public function testHeldRoleSatisfiesARoleRestriction(): void
    {
        self::assertTrue(securityPostureItemRestrictionAllows('', [8], 5, [2, 8]));
    }

    public function testMissingRoleBlocksARoleRestrictedItem(): void
    {
        self::assertFalse(securityPostureItemRestrictionAllows('', [8], 5, [2, 3]));
    }

    public function testEitherRestrictionKindIsEnoughToPass(): void
    {
        // Restricted to user 9 and to role 8: holding role 8 is enough.
        self::assertTrue(securityPostureItemRestrictionAllows('9', [8], 5, [8]));
        // Being user 9 is enough too.
        self::assertTrue(securityPostureItemRestrictionAllows('9', [8], 9, []));
        // Neither -> blocked.
        self::assertFalse(securityPostureItemRestrictionAllows('9', [8], 5, [3]));
    }

    // ------------------------------------------------------------ health states

    /**
     * @return array<string, array{0: int|string|null, 1: int|null, 2: bool, 3: string}>
     */
    public static function healthProvider(): array
    {
        return [
            // complexity, length, has ciphertext, expected
            'no password at all' => ['48', 0, false, 'empty'],
            'no password and no metadata' => [null, null, false, 'empty'],
            'legacy complexity -1' => ['-1', 0, true, 'unassessed'],
            'legacy default row' => ['-1', 20, true, 'unassessed'],
            'empty complexity string' => ['', 20, true, 'unassessed'],
            'null complexity' => [null, 20, true, 'unassessed'],
            'non numeric complexity' => ['abc', 20, true, 'unassessed'],
            'ciphertext but zero length' => ['48', 0, true, 'unassessed'],
            'ciphertext but null length' => ['48', null, true, 'unassessed'],
            'weak by complexity' => ['20', 20, true, 'weak'],
            'weak by length' => ['60', 8, true, 'weak'],
            'weak by both' => ['20', 8, true, 'weak'],
            'healthy at exact thresholds' => ['38', 12, true, 'healthy'],
            'healthy well above' => ['60', 32, true, 'healthy'],
            'integer complexity accepted' => [48, 20, true, 'healthy'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('healthProvider')]
    public function testPasswordHealthClassification(
        int|string|null $complexity,
        ?int $length,
        bool $hasCiphertext,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            securityPasswordHealthClassify(
                $complexity,
                $length,
                $hasCiphertext,
                self::WEAK_COMPLEXITY,
                self::MIN_LENGTH
            )
        );
    }

    public function testEmptyPasswordIsNeverWeakNorUnassessed(): void
    {
        // The state that must stay distinct: nothing stored is a known fact, not missing metadata.
        self::assertSame(
            'empty',
            securityPasswordHealthClassify('-1', 0, false, self::WEAK_COMPLEXITY, self::MIN_LENGTH)
        );
    }

    public function testMinimumLengthThresholdIsConfigurable(): void
    {
        // 16 characters: healthy under the default, weak once the admin raises the bar to 20.
        self::assertSame(
            'healthy',
            securityPasswordHealthClassify('48', 16, true, self::WEAK_COMPLEXITY, 12)
        );
        self::assertSame(
            'weak',
            securityPasswordHealthClassify('48', 16, true, self::WEAK_COMPLEXITY, 20)
        );
    }

    public function testComplexityThresholdIsHonoured(): void
    {
        self::assertSame(
            'weak',
            securityPasswordHealthClassify('37', 20, true, self::WEAK_COMPLEXITY, self::MIN_LENGTH)
        );
        self::assertSame(
            'healthy',
            securityPasswordHealthClassify('38', 20, true, self::WEAK_COMPLEXITY, self::MIN_LENGTH)
        );
    }
}
