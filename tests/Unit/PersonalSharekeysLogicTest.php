<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with app/scripts/remediate_personal_sharekeys.php and
// the storeUsersShareKey() owner-only branch contract in app/sources/main.functions.php.
require_once __DIR__ . '/../../app/scripts/personal_sharekeys_logic.php';

/**
 * Unit tests for the personal-item sharekey decision logic (SEC-8).
 *
 * Covers:
 *   - isPersonalSharekeyDistribution()      — the owner-only trigger (personal flag, NOT onlyForUser)
 *   - personalRootOwnerId()                 — owner resolution from the personal tree root
 *   - personalOwnerConflictsWithCreator()   — at_creation cross-check (skip on disagreement)
 *   - personalSharekeyKeepList()            — owner + system/recovery accounts
 *   - foreignSharekeyUserIds()              — the "user_id NOT IN (...)" deletion spec
 *
 * Several cases reproduce real situations found while validating the remediation tool:
 *   - a personal item whose direct folder has an inconsistent personal_folder flag but whose
 *     absolute root is personal (must resolve to the root owner);
 *   - a personal item sitting under a public root (must NOT be resolved → left untouched);
 *   - a personal item whose at_creation user differs from the folder owner (must be skipped).
 */
class PersonalSharekeysLogicTest extends TestCase
{
    /** System / recovery accounts (mirror app/config/include.php). */
    private const SYSTEM_USERS = [9999997, 9999999, 9999991, 9999998]; // TP, API, OTV, SSH

    // =========================================================================
    // isPersonalSharekeyDistribution() — SEC-8 owner-only trigger
    // =========================================================================

    public function testPersonalFlagOneTriggersOwnerOnly(): void
    {
        $this->assertTrue(isPersonalSharekeyDistribution(1));
    }

    public function testPersonalFlagZeroKeepsAllUsersDistribution(): void
    {
        $this->assertFalse(isPersonalSharekeyDistribution(0));
    }

    /**
     * SEC-8 regression guard: the decision depends ONLY on the personal flag. The legacy
     * $onlyForUser parameter is not even an input here — callers passed it as true on public
     * paths too, so it must never drive the owner-only branch.
     */
    public function testOnlyForUserIsNotAFactor(): void
    {
        // Whatever a caller intended via onlyForUser, a public folder stays all-users.
        $this->assertFalse(isPersonalSharekeyDistribution(0));
        // And a personal folder is always owner-only.
        $this->assertTrue(isPersonalSharekeyDistribution(1));
    }

    // =========================================================================
    // personalRootOwnerId() — owner resolution
    // =========================================================================

    public function testResolvesOwnerFromPersonalRoot(): void
    {
        $root = ['personal_folder' => 1, 'title' => '10000000'];
        $this->assertSame(10000000, personalRootOwnerId($root, self::SYSTEM_USERS));
    }

    /**
     * The intermediate folder of item 3207 had personal_folder = 0, but its absolute root was a
     * personal folder (title = owner id). The DB walk hands this function the ROOT node, which
     * must resolve correctly despite the inconsistent intermediate flag.
     */
    public function testResolvesOwnerWhenRootIsPersonalRegardlessOfIntermediateFlags(): void
    {
        $root = ['personal_folder' => 1, 'title' => '10000000'];
        $this->assertSame(10000000, personalRootOwnerId($root, self::SYSTEM_USERS));
    }

    /**
     * Item 3305: a perso=1 item sitting under a public root. The owner cannot be resolved and
     * the item must be left untouched (no deletion).
     */
    public function testReturnsNullWhenRootIsPublic(): void
    {
        $root = ['personal_folder' => 0, 'title' => 'To treat'];
        $this->assertNull(personalRootOwnerId($root, self::SYSTEM_USERS));
    }

    public function testReturnsNullWhenTitleIsNotNumeric(): void
    {
        $root = ['personal_folder' => 1, 'title' => 'JBON IMPORT'];
        $this->assertNull(personalRootOwnerId($root, self::SYSTEM_USERS));
    }

    public function testReturnsNullWhenRootNodeIsNull(): void
    {
        $this->assertNull(personalRootOwnerId(null, self::SYSTEM_USERS));
    }

    public function testReturnsNullWhenTitleIsASystemUser(): void
    {
        $root = ['personal_folder' => 1, 'title' => '9999997']; // TP_USER_ID
        $this->assertNull(personalRootOwnerId($root, self::SYSTEM_USERS));
    }

    public function testReturnsNullWhenTitleIsEmpty(): void
    {
        $root = ['personal_folder' => 1, 'title' => ''];
        $this->assertNull(personalRootOwnerId($root, self::SYSTEM_USERS));
    }

    public function testHandlesStringPersonalFolderFlag(): void
    {
        // MeekroDB returns column values as strings.
        $root = ['personal_folder' => '1', 'title' => '10000205'];
        $this->assertSame(10000205, personalRootOwnerId($root, self::SYSTEM_USERS));
    }

    // =========================================================================
    // personalOwnerConflictsWithCreator() — at_creation cross-check
    // =========================================================================

    public function testNoConflictWhenCreatorIsNull(): void
    {
        $this->assertFalse(personalOwnerConflictsWithCreator(10000000, null));
    }

    public function testNoConflictWhenCreatorIsEmptyString(): void
    {
        $this->assertFalse(personalOwnerConflictsWithCreator(10000000, ''));
    }

    public function testNoConflictWhenCreatorMatchesOwner(): void
    {
        $this->assertFalse(personalOwnerConflictsWithCreator(10000000, 10000000));
        $this->assertFalse(personalOwnerConflictsWithCreator(10000000, '10000000'));
    }

    /**
     * Item 2399: folder owner 10000000 vs at_creation user 10000165 → conflict → must be skipped.
     */
    public function testConflictWhenCreatorDiffersFromOwner(): void
    {
        $this->assertTrue(personalOwnerConflictsWithCreator(10000000, 10000165));
        $this->assertTrue(personalOwnerConflictsWithCreator(10000205, '10000002'));
    }

    // =========================================================================
    // personalSharekeyKeepList() — owner + recovery accounts
    // =========================================================================

    public function testKeepListContainsOwnerAndAllSystemUsers(): void
    {
        $keep = personalSharekeyKeepList(10000000, self::SYSTEM_USERS);

        $this->assertContains(10000000, $keep);
        foreach (self::SYSTEM_USERS as $sys) {
            $this->assertContains($sys, $keep);
        }
        $this->assertCount(5, $keep); // owner + 4 system users
    }

    public function testKeepListDeduplicatesOwnerThatIsAlsoListed(): void
    {
        $keep = personalSharekeyKeepList(9999997, self::SYSTEM_USERS); // owner == TP_USER_ID
        $this->assertCount(4, $keep);
    }

    // =========================================================================
    // foreignSharekeyUserIds() — the "NOT IN (keep)" deletion spec
    // =========================================================================

    public function testForeignKeysAreHoldersNotInKeepList(): void
    {
        $keep = personalSharekeyKeepList(10000000, self::SYSTEM_USERS);
        // SEC-8 situation: a personal item that wrongly received keys for many users.
        $existing = [10000000, 9999997, 10000001, 10000002, 10000003];

        $foreign = foreignSharekeyUserIds($existing, $keep);

        sort($foreign);
        $this->assertSame([10000001, 10000002, 10000003], $foreign);
    }

    public function testNoForeignKeysWhenOnlyOwnerAndSystemUsersHold(): void
    {
        $keep = personalSharekeyKeepList(10000000, self::SYSTEM_USERS);
        $existing = [10000000, 9999997]; // owner + TP only

        $this->assertSame([], foreignSharekeyUserIds($existing, $keep));
    }

    public function testForeignKeysHandleStringIdsAndDuplicates(): void
    {
        $keep = personalSharekeyKeepList(10000000, self::SYSTEM_USERS);
        $existing = ['10000000', '10000001', '10000001', '9999999']; // strings + duplicate

        $this->assertSame([10000001], foreignSharekeyUserIds($existing, $keep));
    }

    public function testEmptyHoldersYieldNoForeignKeys(): void
    {
        $keep = personalSharekeyKeepList(10000000, self::SYSTEM_USERS);
        $this->assertSame([], foreignSharekeyUserIds([], $keep));
    }
}
