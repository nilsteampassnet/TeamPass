<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/item_restriction_logic.php';

/**
 * Behavioural tests for the canonical item-level restriction decision.
 *
 * The rule is shared by the web paths, the REST API and Security Posture: an item narrowed to a
 * subset of users or roles is readable only by that subset, and an item with no restriction at
 * all stays open to every folder member. Assert semantics here; the wiring of each consumer is
 * asserted in its own sentinel test.
 */
class ItemRestrictionLogicTest extends TestCase
{
    public function testIdListParsingKeepsPositiveIntegersOnly(): void
    {
        self::assertSame([], itemRestrictionParseIdList(null));
        self::assertSame([], itemRestrictionParseIdList(''));
        self::assertSame([], itemRestrictionParseIdList('   '));
        self::assertSame([], itemRestrictionParseIdList(';;'));
        self::assertSame([12], itemRestrictionParseIdList('12'));
        self::assertSame([3, 7], itemRestrictionParseIdList(' 3 ; 7 '));
        self::assertSame([3, 7], itemRestrictionParseIdList('3;7;3'));

        // Junk, negatives and zero are not user ids and must never satisfy a restriction.
        self::assertSame([5], itemRestrictionParseIdList('5;0;-2;abc;1.5'));
    }

    public function testUnrestrictedItemIsOpenToEveryFolderMember(): void
    {
        self::assertTrue(itemRestrictionAllows(null, [], 42, []));
        self::assertTrue(itemRestrictionAllows('', [], 42, []));
        self::assertTrue(itemRestrictionAllows('   ', [], 42, [7]));
    }

    public function testListedUserPassesTheRestriction(): void
    {
        self::assertTrue(itemRestrictionAllows('42', [], 42, []));
        self::assertTrue(itemRestrictionAllows('7;42;9', [], 42, []));
    }

    public function testExcludedUserIsDeniedEvenWhenTheyHoldAShareKey(): void
    {
        // The exact GHSA-gxc6-rgv6-wx99 scenario: Bob keeps his sharekey after Alice narrows the
        // item to herself. Holding the key is not holding the right.
        self::assertFalse(itemRestrictionAllows('7', [], 42, []));
        self::assertFalse(itemRestrictionAllows('7;9', [], 42, [3, 4]));
    }

    public function testHeldRoleSatisfiesARoleRestriction(): void
    {
        self::assertTrue(itemRestrictionAllows(null, [5], 42, [5]));
        self::assertTrue(itemRestrictionAllows(null, [5, 6], 42, [9, 6]));
        self::assertTrue(itemRestrictionAllows('', [5], 42, ['5']));
    }

    public function testMissingRoleIsDeniedWhenOnlyRolesRestrictTheItem(): void
    {
        self::assertFalse(itemRestrictionAllows(null, [5], 42, []));
        self::assertFalse(itemRestrictionAllows(null, [5], 42, [6, 7]));
    }

    public function testEitherSourceIsEnoughWhenBothRestrictionsExist(): void
    {
        self::assertTrue(itemRestrictionAllows('42', [5], 42, []));
        self::assertTrue(itemRestrictionAllows('7', [5], 42, [5]));
        self::assertFalse(itemRestrictionAllows('7', [5], 42, [6]));
    }

    public function testSqlPredicateFailsClosedOnUnsafeInput(): void
    {
        $table = 'teampass_restriction_to_roles';

        self::assertSame('(1 = 0)', itemRestrictionSqlPredicate(0, [], 'i', $table));
        self::assertSame('(1 = 0)', itemRestrictionSqlPredicate(-1, [], 'i', $table));
        self::assertSame('(1 = 0)', itemRestrictionSqlPredicate(1, [], 'i; DROP TABLE x', $table));
        self::assertSame('(1 = 0)', itemRestrictionSqlPredicate(1, [], '', $table));
        self::assertSame('(1 = 0)', itemRestrictionSqlPredicate(1, [], 'i', 'tbl; DROP TABLE x'));
    }

    public function testSqlPredicateCoversTheThreeAcceptingCases(): void
    {
        $sql = itemRestrictionSqlPredicate(42, [5, 6], 'i', 'teampass_restriction_to_roles');

        // 1. no restriction at all
        self::assertStringContainsString("COALESCE(i.restricted_to, '') = ''", $sql);
        self::assertStringContainsString('NOT EXISTS', $sql);
        // 2. the caller is listed, matched on a delimited list so 4 never matches 42
        self::assertStringContainsString("CONCAT(';', COALESCE(i.restricted_to, ''), ';')", $sql);
        self::assertStringContainsString("LIKE '%;42;%'", $sql);
        // 3. the caller holds one of the item's roles
        self::assertStringContainsString('role_id IN (5,6)', $sql);
    }

    public function testSqlPredicateOmitsTheRoleBranchForARolelessUser(): void
    {
        $sql = itemRestrictionSqlPredicate(42, [], 'i', 'teampass_restriction_to_roles');

        self::assertStringNotContainsString('role_id IN', $sql);
        self::assertStringContainsString("LIKE '%;42;%'", $sql);
    }

    public function testSqlPredicateOnlyEmbedsSanitizedRoleIds(): void
    {
        $sql = itemRestrictionSqlPredicate(42, ['5', 0, -3, '7 OR 1=1'], 'i', 'teampass_restriction_to_roles');

        self::assertStringContainsString('role_id IN (5,7)', $sql);
        self::assertStringNotContainsString('OR 1=1', $sql);
    }

    public function testRequestedIdsAreNormalizedToUniquePositiveIntegers(): void
    {
        self::assertSame([], itemRestrictionNormalizeRequestedIds(null, 'restricted_to'));
        self::assertSame([], itemRestrictionNormalizeRequestedIds([], 'restricted_to'));
        self::assertSame([42, 7], itemRestrictionNormalizeRequestedIds([42, '7', ' 42 '], 'restricted_to'));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function malformedRequestedIdsProvider(): array
    {
        return [
            'scalar string' => ['5;7'],
            'integer' => [5],
            'zero' => [[0]],
            'negative' => [[-3]],
            'float' => [[5.0]],
            'boolean' => [[true]],
            'junk string' => [['7 OR 1=1']],
            'empty string' => [['']],
            'nested list' => [[[5]]],
        ];
    }

    /**
     * A dropped restriction would leave the item open to the whole folder, so malformed input is
     * refused rather than filtered.
     *
     * @dataProvider malformedRequestedIdsProvider
     */
    public function testMalformedRequestedIdsAreRefused($raw): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('restricted_to_roles must be an array of positive integer IDs.');
        itemRestrictionNormalizeRequestedIds($raw, 'restricted_to_roles');
    }

    public function testRequestedIdsAreCapped(): void
    {
        self::assertCount(3, itemRestrictionNormalizeRequestedIds([1, 2, 3, 3], 'restricted_to', 3));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('restricted_to accepts at most 3 IDs.');
        itemRestrictionNormalizeRequestedIds([1, 2, 3, 4], 'restricted_to', 3);
    }

    public function testAuthorIsKeptInAnyRestriction(): void
    {
        // No restriction: nothing is stored, the author is not added.
        self::assertSame([], itemRestrictionUsersWithAuthor([], [], 42));

        self::assertSame([7, 42], itemRestrictionUsersWithAuthor([7], [], 42));
        self::assertSame([42, 7], itemRestrictionUsersWithAuthor([42, 7], [], 42));

        // Role-only restriction: without the author, the creator loses its own item.
        self::assertSame([42], itemRestrictionUsersWithAuthor([], [5], 42));
        self::assertTrue(itemRestrictionAllows(
            implode(';', itemRestrictionUsersWithAuthor([], [5], 42)),
            [5],
            42,
            []
        ));
    }

    public function testPostureWrappersDelegateToTheCanonicalDecision(): void
    {
        require_once __DIR__ . '/../../app/sources/security_posture_logic.php';

        self::assertSame([3, 7], securityPostureParseIdList('3;7;3'));
        self::assertFalse(securityPostureItemRestrictionAllows('7', [], 42, []));
        self::assertTrue(securityPostureItemRestrictionAllows('7;42', [], 42, []));
    }
}
