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
 *   - searchResolveFolderScope()       — the folder-scope ACL (regression guard)
 *   - searchApplyPersonalFolderScope() — personal/shared folder-result scope
 *   - folder title normalization       — encoded storage and decoded display
 *   - searchBuildFolderWhere()         — ACL-bound folder-title predicate
 *   - searchBuildOrderClause()         — ORDER BY hardening (GHSA-fqg6-xvv8-w228 form)
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

    public function testPaletteBuildsItemRestrictionFromTheCanonicalItemsRow(): void
    {
        // Empty item restrictions are normalized to '0' in the search cache,
        // while the items table retains the canonical empty value the predicate
        // tests for (SearchFiltersLogicTest covers that side). Built on the
        // cache alias, the predicate hid every unrestricted item instead of
        // hiding the restricted ones.
        //
        // Only the alias argument is asserted, so the surrounding expressions
        // stay free to be refactored.
        $source = file_get_contents(__DIR__ . '/../../app/sources/palette.queries.php');
        $this->assertIsString($source);

        // The alias is only usable because the items row is joined.
        $this->assertStringContainsString('AS i ON (i.id = c.id)', $source);

        $this->assertSame(
            1,
            preg_match('/searchItemRestrictionSql\((?<args>[^;]*)\);/s', $source, $matches),
            'palette.queries.php must build the item-level restriction predicate.'
        );

        $this->assertStringContainsString("'i'", $matches['args']);
        $this->assertStringNotContainsString("'c'", $matches['args']);
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

    public function testSearchHandlerAddsContainmentAwareForeignPersonalFoldersToDenials(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('getForeignPersonalFolderIds($userId)', $source);
        $this->assertMatchesRegularExpression(
            '/\$forbiddenPersonalFolders\s*=\s*array_merge\(.*?getForeignPersonalFolderIds\(\$userId\).*?\);\s*\$folderScope\s*=\s*searchResolveFolderScope/s',
            $source
        );
        $this->assertStringContainsString(
            "\$ownPersonalFolderIds = array_map('intval', (array) \$session->get('user-personal_folders'))",
            $source
        );
        $this->assertStringNotContainsString('getOwnPersonalFolderIds($userId)', $source);
    }

    public function testOwnPersonalFolderLookupUsesOneContainmentQuery(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/main.functions.php');
        $this->assertIsString($source);
        $start = strpos($source, 'function getOwnPersonalFolderIds(');
        $end = strpos($source, '/**', $start + 1);
        $this->assertIsInt($start);
        $this->assertIsInt($end);
        $functionSource = substr($source, $start, $end - $start);

        $this->assertStringContainsString("INNER JOIN ' . prefixTable('nested_tree') . ' AS personal_root", $functionSource);
        $this->assertStringContainsString('folder.nleft >= personal_root.nleft', $functionSource);
        $this->assertStringNotContainsString('new NestedTree(', $functionSource);
    }

    // -------------------------------------------------------------------
    // searchApplyPersonalFolderScope()
    // -------------------------------------------------------------------

    public function testPersonalModeKeepsOnlyTheUsersOwnPersonalTree(): void
    {
        $this->assertSame(
            [7, 8],
            searchApplyPersonalFolderScope([3, 7, 8, 9], [7, 8, 42], 'personal')
        );
    }

    public function testSharedModeRemovesTheUsersOwnPersonalTree(): void
    {
        $this->assertSame(
            [3, 9],
            searchApplyPersonalFolderScope([3, 7, 8, 9], [7, 8, 42], 'shared')
        );
    }

    public function testEmptyPersonalModeLeavesTheAuthorizedScopeUntouched(): void
    {
        $this->assertSame(
            [3, 7, 8, 9],
            searchApplyPersonalFolderScope([3, 7, 8, 9], [7, 8], '')
        );
    }

    // -------------------------------------------------------------------
    // searchBuildFolderWhere()
    // -------------------------------------------------------------------

    public function testFolderPredicateBindsTheAuthorizedScopeAndEveryTerm(): void
    {
        $built = searchBuildFolderWhere(['backup', 'prod'], [3, 7, 9]);

        $this->assertSame(
            'folder.id IN %li_folder_scope AND folder.title LIKE %ss_folder_term0'
                . ' AND folder.title LIKE %ss_folder_term1',
            $built['sql']
        );
        $this->assertSame(
            [
                'folder_scope' => [3, 7, 9],
                'folder_term0' => 'backup',
                'folder_term1' => 'prod',
            ],
            $built['params']
        );
    }

    public function testFolderPredicateKeepsLikeWildcardsInBoundValues(): void
    {
        $built = searchBuildFolderWhere(['100%_prod'], [7]);

        $this->assertSame('100%_prod', $built['params']['folder_term0']);
        $this->assertStringContainsString('%ss_folder_term0', $built['sql']);
    }

    public function testFolderPredicateMatchesTheHtmlEncodedStorageFormat(): void
    {
        $built = searchBuildFolderWhere(['R&D', "Client's", '"Production"'], [7]);

        $this->assertSame('R&amp;D', $built['params']['folder_term0']);
        $this->assertSame('Client&#039;s', $built['params']['folder_term1']);
        $this->assertSame('&quot;Production&quot;', $built['params']['folder_term2']);
    }

    public function testFolderPredicateFailsClosedWhenSanitizationEmptiesTheTerm(): void
    {
        $this->assertSame(
            ['sql' => '(1 = 0)', 'params' => []],
            searchBuildFolderWhere(['<script>'], [7])
        );
    }

    public function testFolderTitlesAreDecodedAndPersonalRootsUseTheLogin(): void
    {
        $this->assertSame('R&D', searchDecodeFolderTitle('R&amp;D'));
        $this->assertSame("Client's data", searchDecodeFolderTitle('Client&#039;s data'));
        $this->assertSame('alice', searchFolderDisplayTitle('42', 1, 42, 'alice'));
        $this->assertSame('42', searchFolderDisplayTitle('42', 2, 42, 'alice'));
    }

    public function testFolderResultAndOptionLimitsAreNamedConstants(): void
    {
        $this->assertSame(20, SEARCH_FOLDER_RESULTS_LIMIT);
        $this->assertSame(50, SEARCH_FOLDER_OPTIONS_PAGE_SIZE);
    }

    public function testFolderPredicateFailsClosedWithoutScopeOrUsableTerms(): void
    {
        $this->assertSame(
            ['sql' => '(1 = 0)', 'params' => []],
            searchBuildFolderWhere(['prod'], [])
        );
        $this->assertSame(
            ['sql' => '(1 = 0)', 'params' => []],
            searchBuildFolderWhere(['x'], [7])
        );
    }

    public function testFolderResultsStayDistinctFromTheItemTableAndEscapeTheirText(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/pages/search.php');
        $script = file_get_contents(__DIR__ . '/../../app/pages/search.js.php');
        $source = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($view);
        $this->assertIsString($script);
        $this->assertIsString($source);

        $folderSectionPosition = strpos($view, 'id="search-folder-results"');
        $itemTablePosition = strpos($view, 'id="search-results-items"');
        $this->assertIsInt($folderSectionPosition);
        $this->assertIsInt($itemTablePosition);
        $this->assertLessThan($itemTablePosition, $folderSectionPosition);

        $this->assertStringContainsString('renderFolderResults(json.folders', $script);
        $this->assertStringContainsString(".text(title)", $script);
        $this->assertStringNotContainsString(".html(title)", $script);
        $this->assertStringContainsString('index.php?page=items&group=', $script);
        $this->assertStringContainsString("searchDecodeFolderTitle((string) \$record['folder'])", $source);
    }

    public function testFolderPathsCannotRevealAnUnauthorizedAncestorTitle(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('function searchHydrateFolderRows(', $source);
        $this->assertMatchesRegularExpression(
            '/INNER JOIN .*? AS ancestor\s+ON ancestor\.id > 0\s+AND ancestor\.id IN %li_folder_scope/s',
            $source
        );
        $this->assertStringNotContainsString('GROUP_CONCAT(', $source);
    }

    public function testFolderFilterOptionsAreAclBoundAndPaginated(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($source);

        $optionsStart = strpos($source, "if (\$request->request->get('type') === 'folder_options')");
        $searchStart = strpos($source, '// Remaining filter options.');
        $this->assertIsInt($optionsStart);
        $this->assertIsInt($searchStart);
        $optionsSource = substr($source, $optionsStart, $searchStart - $optionsStart);

        $this->assertStringContainsString("'folder.id IN %li_folder_scope'", $optionsSource);
        $this->assertStringContainsString('searchEncodeFolderTerm($term)', $optionsSource);
        $this->assertStringContainsString('SEARCH_FOLDER_OPTIONS_PAGE_SIZE + 1', $optionsSource);
        $this->assertStringContainsString('LIMIT %i_folder_option_limit OFFSET %i_folder_option_offset', $optionsSource);
        $this->assertStringContainsString("'pagination' => ['more' => false]", $optionsSource);
        $this->assertStringContainsString('searchHydrateFolderRows(', $optionsSource);
    }

    public function testRestoredFolderQueryUsesOnlyNamedMeekroArguments(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($source);

        $where = 'WHERE folder.id = %i_selected_folder_id AND folder.id IN %li_folder_scope';
        $this->assertStringContainsString($where, $source);
        $this->assertStringNotContainsString(
            'WHERE folder.id = %i AND folder.id IN %li_folder_scope',
            $source
        );

        $meekro = new MeekroDB();
        $this->assertSame(
            'WHERE folder.id = 7 AND folder.id IN (7,8)',
            $meekro->parse(
                $where,
                [
                    'selected_folder_id' => 7,
                    'folder_scope' => [7, 8],
                ]
            )
        );
    }

    public function testFolderFacetAndResetControlsAreWiredIntoTheSearchPage(): void
    {
        $view = file_get_contents(__DIR__ . '/../../app/pages/search.php');
        $script = file_get_contents(__DIR__ . '/../../app/pages/search.js.php');
        $this->assertIsString($view);
        $this->assertIsString($script);

        $this->assertStringContainsString('id="search-folder"', $view);
        $this->assertStringContainsString('data-facet="folder"', $view);
        $this->assertStringContainsString('id="search-reset"', $view);
        $this->assertStringContainsString("$('#search-folder').select2({", $script);
        $this->assertStringContainsString("type: 'folder_options'", $script);
        $this->assertStringContainsString('.text(text)', $script);
        $this->assertStringNotContainsString('.html(text)', $script);
        $this->assertStringContainsString("$('#search-reset, #search-clear-all').on('click', resetSearch)", $script);
        $this->assertStringContainsString("$('#search-term').val('')", $script);
        $this->assertStringContainsString("pendingSelectRestore = null", $script);
        $this->assertStringContainsString('id="search-items-heading"', $view);
        $this->assertStringContainsString("$('#search-items-heading').toggleClass('hidden', hasCriteria === false)", $script);
    }

    public function testSearchControlsAreTranslatedInEveryShippedLanguage(): void
    {
        $languageDirectory = __DIR__ . '/../../app/includes/language';
        $english = require $languageDirectory . '/english.php';
        $keys = ['search_reset', 'search_folder_filter', 'search_folder_any', 'search_folder_results_more'];
        $languageFiles = glob($languageDirectory . '/*.php');
        $this->assertIsArray($languageFiles);

        foreach ($languageFiles as $languageFile) {
            unset($GLOBALS['LANG']);
            $loadedCatalog = require $languageFile;
            $catalog = is_array($loadedCatalog) ? $loadedCatalog : ($GLOBALS['LANG'] ?? null);
            $this->assertIsArray($catalog, basename($languageFile));
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $catalog, basename($languageFile));
                $this->assertNotSame('', trim((string) $catalog[$key]), basename($languageFile) . ': ' . $key);
                if (basename($languageFile) !== 'english.php') {
                    $this->assertNotSame(
                        $english[$key],
                        $catalog[$key],
                        basename($languageFile) . ': ' . $key . ' must not fall back to English'
                    );
                }
            }
        }
        unset($GLOBALS['LANG']);
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
