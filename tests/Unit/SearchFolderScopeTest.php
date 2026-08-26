<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared by find.queries.php and
// search.queries.php.
require_once __DIR__ . '/../../app/sources/search.functions.php';

/**
 * Unit tests for the search authorization primitives.
 *
 * Covers:
 *   - searchResolveFolderScope()       — the folder-scope ACL (regression guard)
 *   - searchApplyPersonalFolderScope() — personal/shared folder-result scope
 *   - searchBuildFolderWhere()         — ACL-bound folder-title predicate
 *   - searchBuildOrderClause()         — ORDER BY hardening (GHSA-fqg6-xvv8-w228 form)
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
        $this->assertIsString($view);
        $this->assertIsString($script);

        $folderSectionPosition = strpos($view, 'id="search-folder-results"');
        $itemTablePosition = strpos($view, 'id="search-results-items"');
        $this->assertIsInt($folderSectionPosition);
        $this->assertIsInt($itemTablePosition);
        $this->assertLessThan($itemTablePosition, $folderSectionPosition);

        $this->assertStringContainsString('renderFolderResults(json.folders', $script);
        $this->assertStringContainsString(".text(title)", $script);
        $this->assertStringNotContainsString(".html(title)", $script);
        $this->assertStringContainsString('index.php?page=items&group=', $script);
    }

    public function testFolderPathsCannotRevealAnUnauthorizedAncestorTitle(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($source);
        $this->assertMatchesRegularExpression(
            '/LEFT JOIN .*? AS ancestor\s+ON ancestor\.id > 0\s+AND ancestor\.id IN %li_folder_scope/s',
            $source
        );
    }

    public function testFolderFilterOptionsUseTheAuthorizedScopeForFoldersAndPaths(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $this->assertIsString($source);

        $optionsStart = strpos($source, "if (\$request->request->get('type') === 'filter_options')");
        $searchStart = strpos($source, '// Search.');
        $this->assertIsInt($optionsStart);
        $this->assertIsInt($searchStart);
        $optionsSource = substr($source, $optionsStart, $searchStart - $optionsStart);

        $this->assertStringContainsString("'folders' => []", $optionsSource);
        $this->assertStringContainsString('ancestor.id IN %li_folder_scope', $optionsSource);
        $this->assertStringContainsString('WHERE folder.id IN %li_folder_scope', $optionsSource);
        $this->assertStringContainsString("['folder_scope' => \$folderScope]", $optionsSource);
        $this->assertStringContainsString("\$options['folders'][]", $optionsSource);
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
        $this->assertStringContainsString(".text(label).appendTo('#search-folder')", $script);
        $this->assertStringNotContainsString(".html(label).appendTo('#search-folder')", $script);
        $this->assertStringContainsString("$('#search-reset, #search-clear-all').on('click', resetSearch)", $script);
        $this->assertStringContainsString("$('#search-term').val('')", $script);
        $this->assertStringContainsString("pendingSelectRestore = null", $script);
    }

    public function testSearchControlsAreTranslatedInEveryShippedLanguage(): void
    {
        $languageDirectory = __DIR__ . '/../../app/includes/language';
        $english = require $languageDirectory . '/english.php';
        $keys = ['search_reset', 'search_folder_filter', 'search_folder_any'];
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
