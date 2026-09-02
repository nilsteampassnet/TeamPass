<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared by find.queries.php,
// search.queries.php, and palette.queries.php.
require_once __DIR__ . '/../../app/sources/search.functions.php';

/**
 * Unit tests for the search authorization primitives.
 *
 * Covers:
 *   - searchResolveFolderScope()  — the folder-scope ACL (regression guard)
 *   - searchBuildOrderClause()    — ORDER BY hardening (GHSA-fqg6-xvv8-w228 form)
 *   - the three item-search handlers, asserted at the source level, so neither
 *     the folder scope nor the item-level restriction can be dropped from one
 *     entry point while the other two keep it.
 */
class SearchFolderScopeTest extends TestCase
{
    // -------------------------------------------------------------------
    // searchResolveFolderScope()
    // -------------------------------------------------------------------

    public function testScopeIsTheAccessibleFoldersWhenNoNarrowingAsked(): void
    {
        $this->assertSame([3, 7, 9], searchResolveFolderScope([3, 7, 9]));
    }

    public function testRequestedSubtreeOnlyNarrowsAndNeverWidens(): void
    {
        // The regression this guards: a client-supplied subtree used to
        // REPLACE the accessible-folder scope, exposing folders the user
        // holds no right on. Folder 42 must not survive.
        $this->assertSame(
            [7],
            searchResolveFolderScope([3, 7, 9], [], [7, 42])
        );
    }

    public function testRequestedSubtreeDisjointFromScopeYieldsNothing(): void
    {
        $this->assertSame([], searchResolveFolderScope([3, 7, 9], [], [42, 43]));
    }

    public function testForeignPersonalFoldersAreSubtracted(): void
    {
        $this->assertSame([3, 9], searchResolveFolderScope([3, 7, 9], [7]));
    }

    public function testOwnDeeplyNestedPersonalFoldersRemainSearchable(): void
    {
        // 10 -> 11 -> 12 models a personal root, child, and grandchild.
        // Only the foreign personal tree (20 -> 21) must be removed.
        $this->assertSame(
            [10, 11, 12],
            searchResolveFolderScope([10, 11, 12, 20, 21], [20, 21])
        );
    }

    public function testLimitedSearchCanTargetADeepOwnPersonalFolder(): void
    {
        $this->assertSame(
            [12],
            searchResolveFolderScope([10, 11, 12], [], [12])
        );
    }

    public function testAllItemSearchHandlersUseTheSharedFolderScope(): void
    {
        foreach (['find.queries.php', 'search.queries.php', 'palette.queries.php'] as $handler) {
            $source = file_get_contents(__DIR__ . '/../../app/sources/' . $handler);

            $this->assertIsString($source);
            $this->assertStringContainsString('searchResolveFolderScope(', $source, $handler);
        }
    }

    public function testEveryItemSearchHandlerEnforcesTheItemLevelRestriction(): void
    {
        // The folder scope is not an authorization decision on its own: an item
        // restricted to named users or to roles lives in a folder the caller can
        // otherwise see. The three handlers reach the same rule by three routes,
        // so each is asserted on the call it actually makes.

        // search.queries.php goes through searchBuildWhere(), which emits the
        // predicate itself (covered by SearchFiltersLogicTest).
        $searchSource = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($searchSource);
        $this->assertStringContainsString('searchBuildWhere(', $searchSource);

        $builderSource = file_get_contents(__DIR__ . '/../../app/sources/search.functions.php');
        $this->assertIsString($builderSource);
        $this->assertStringContainsString('searchItemRestrictionSql(', $builderSource);

        // palette.queries.php builds its own query, so it calls the predicate.
        $paletteSource = file_get_contents(__DIR__ . '/../../app/sources/palette.queries.php');
        $this->assertIsString($paletteSource);
        $this->assertStringContainsString('searchItemRestrictionSql(', $paletteSource);

        // find.queries.php predates the predicate and drops the row at render time.
        $findSource = file_get_contents(__DIR__ . '/../../app/sources/find.queries.php');
        $this->assertIsString($findSource);
        $this->assertStringContainsString('restriction_to_roles', $findSource);
        $this->assertStringContainsString('$getItemInList = false', $findSource);
    }

    public function testPaletteFiltersDeletedAndForeignPersonalItems(): void
    {
        // The palette reads the cache, which is pruned on delete but drifts, and
        // which holds personal items whose folder flag was never written. Both
        // guards must stay in the query, not in the rendering loop, so they apply
        // before the LIMIT.
        $source = file_get_contents(__DIR__ . '/../../app/sources/palette.queries.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('i.deleted_at IS NULL', $source);
        $this->assertStringContainsString('c.perso = 0 OR CAST(c.author AS UNSIGNED)', $source);
    }

    public function testLegacyPersonalFolderDepthHeuristicIsNotUsed(): void
    {
        foreach (['find.queries.php', 'palette.queries.php'] as $handler) {
            $source = file_get_contents(__DIR__ . '/../../app/sources/' . $handler);

            $this->assertIsString($source);
            $this->assertStringNotContainsString('NOT parent_id = %i AND NOT title = %i', $source, $handler);
            $this->assertStringNotContainsString('c.id_tree NOT IN %ls_pf', $source, $handler);
        }
    }

    public function testDenialBeatsAnExplicitlyRequestedSubtree(): void
    {
        // A denied folder cannot be re-obtained by asking for it as a subtree.
        $this->assertSame([], searchResolveFolderScope([3, 7], [7], [7]));
    }

    public function testStringIdsFromTheDriverAreAccepted(): void
    {
        // Every column comes back from the driver as a string.
        $this->assertSame([3, 7], searchResolveFolderScope(['3', '7']));
    }

    public function testNonPositiveAndNonScalarIdsAreDropped(): void
    {
        $this->assertSame([5], searchResolveFolderScope([0, -1, '5', '', null, ['x']]));
    }

    public function testDuplicatesAreCollapsedAndKeysReindexed(): void
    {
        $this->assertSame([4, 8], searchResolveFolderScope([4, '4', 8]));
    }

    public function testEmptyAccessibleFoldersYieldsEmptyScope(): void
    {
        $this->assertSame([], searchResolveFolderScope([]));
        $this->assertSame([], searchResolveFolderScope([], [], [1, 2]));
    }

    public function testEmptyRequestedSubtreeIsNotTreatedAsNoNarrowing(): void
    {
        // An empty subtree means "nothing matched", not "no filter".
        $this->assertSame([], searchResolveFolderScope([3, 7], [], []));
    }

    // -------------------------------------------------------------------
    // searchBuildOrderClause()
    // -------------------------------------------------------------------

    public function testOrderClauseUsesTheServerSideColumnMap(): void
    {
        $this->assertSame(
            'ORDER BY c.label ASC',
            searchBuildOrderClause(['c.id', 'c.label'], 1, 'asc')
        );
    }

    public function testOrderDirectionIsReDerivedAsAConstant(): void
    {
        // The advisory form: the emitted direction is a constant, never the
        // request value, so nothing can ride along after it.
        $this->assertSame(
            'ORDER BY c.id DESC',
            searchBuildOrderClause(['c.id'], 0, 'DeSc')
        );
    }

    public function testOrderRejectsInjectedDirection(): void
    {
        $this->assertSame(
            '',
            searchBuildOrderClause(['c.id'], 0, 'ASC, (SELECT 1)')
        );
    }

    public function testOrderRejectsOutOfRangeColumnIndex(): void
    {
        $this->assertSame('', searchBuildOrderClause(['c.id'], 9, 'asc'));
        $this->assertSame('', searchBuildOrderClause(['c.id'], -1, 'asc'));
    }

    public function testOrderFallsBackToTheProvidedDefault(): void
    {
        $default = 'ORDER BY c.label ASC';
        $this->assertSame($default, searchBuildOrderClause(['c.id'], null, 'asc', $default));
        $this->assertSame($default, searchBuildOrderClause(['c.id'], 0, null, $default));
    }
}
