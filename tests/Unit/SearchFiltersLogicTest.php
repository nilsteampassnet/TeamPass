<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with the search.queries.php handler.
require_once __DIR__ . '/../../app/sources/search.functions.php';

/**
 * Unit tests for the faceted search filter logic.
 *
 * Covers:
 *   - searchNormalizeTerms()      — multi-term splitting and bounds
 *   - searchNormalizeFilters()    — allow-list validation of the payload
 *   - searchItemRestrictionSql()  — item-level restriction predicate
 *   - searchBuildWhere()          — predicate assembly and parameter binding
 */
class SearchFiltersLogicTest extends TestCase
{
    /**
     * Minimal context accepted by searchBuildWhere().
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function context(array $overrides = []): array
    {
        return array_merge([
            'user_id' => 7,
            'role_ids' => [3, 4],
            'folder_scope' => [10, 11],
            'now' => 1000000,
            'day_seconds' => 86400,
            'overshared_threshold' => 10,
            'visible_field_ids' => [21, 22],
            'tables' => [
                'restriction_to_roles' => 'teampass_restriction_to_roles',
                'files' => 'teampass_files',
                'tags' => 'teampass_tags',
                'users_favorites' => 'teampass_users_favorites',
                'users_latest_items' => 'teampass_users_latest_items',
                'item_health' => 'teampass_item_health',
                'rotation_flags' => 'teampass_rotation_flags',
                'sharekeys_items' => 'teampass_sharekeys_items',
                'categories_items' => 'teampass_categories_items',
            ],
            'weak_sql' => '(CASE WHEN 1 = 1 THEN 1 ELSE 0 END)',
        ], $overrides);
    }

    // -------------------------------------------------------------------
    // searchNormalizeTerms()
    // -------------------------------------------------------------------

    public function testTermsAreSplitOnWhitespace(): void
    {
        $this->assertSame(['backup', 'prod'], searchNormalizeTerms('  backup   prod '));
    }

    public function testTermsShorterThanTwoCharsAreDropped(): void
    {
        $this->assertSame(['prod'], searchNormalizeTerms('a prod'));
    }

    public function testTermsAreDeduplicatedAndCapped(): void
    {
        $this->assertSame(['aa', 'bb'], searchNormalizeTerms('aa bb aa'));
        $this->assertCount(5, searchNormalizeTerms('t1 t2 t3 t4 t5 t6 t7'));
    }

    public function testTermLengthIsBounded(): void
    {
        $this->assertSame(100, mb_strlen(searchNormalizeTerms(str_repeat('x', 400))[0]));
    }

    // -------------------------------------------------------------------
    // searchNormalizeFilters()
    // -------------------------------------------------------------------

    public function testUnknownFieldsFallBackToTheCheapDefaults(): void
    {
        $filters = searchNormalizeFilters(['fields' => ['pw', 'secret']]);
        $this->assertSame(['label', 'login', 'url', 'tags', 'folder'], $filters['fields']);
    }

    public function testDescriptionIsOptInOnly(): void
    {
        $filters = searchNormalizeFilters(['fields' => ['label', 'description']]);
        $this->assertSame(['label', 'description'], $filters['fields']);
    }

    public function testFolderIsAnAllowedSearchField(): void
    {
        $filters = searchNormalizeFilters(['fields' => ['folder']]);
        $this->assertSame(['folder'], $filters['fields']);
    }

    public function testFolderFacetAcceptsOnePositiveFolderIdOnly(): void
    {
        $filters = searchNormalizeFilters(['folder' => ['42', '43', 0, -1]]);
        $this->assertSame([42], $filters['folder']);
    }

    public function testClassificationKeepsZeroButRejectsOutOfScale(): void
    {
        // 0 means "unclassified" and is a legitimate filter value.
        $filters = searchNormalizeFilters(['classification' => [0, 3, 9, -2]]);
        $this->assertSame([0, 3], $filters['classification']);
    }

    public function testHealthFlagsAreAllowListed(): void
    {
        $filters = searchNormalizeFilters(['health' => ['weak', 'nonsense', 'breached']]);
        $this->assertSame(['weak', 'breached'], $filters['health']);
    }

    public function testAttachmentExtensionsAreSanitised(): void
    {
        $filters = searchNormalizeFilters([
            'attachment_extensions' => ['PDF', 'do cx', '../etc', 'zip'],
        ]);
        $this->assertSame(['pdf', 'zip'], $filters['attachment_extensions']);
    }

    public function testRotationStatusIsAllowListed(): void
    {
        $filters = searchNormalizeFilters(['rotation_status' => ['pending', 'DROP']]);
        $this->assertSame(['pending'], $filters['rotation_status']);
    }

    public function testUnknownKeysAreDropped(): void
    {
        $filters = searchNormalizeFilters(['evil' => '1 OR 1=1']);
        $this->assertArrayNotHasKey('evil', $filters);
    }

    // -------------------------------------------------------------------
    // searchHasActiveFacet()
    // -------------------------------------------------------------------

    public function testBareTermIsNotAFacet(): void
    {
        $this->assertFalse(searchHasActiveFacet(searchNormalizeFilters(['term' => 'gitlab'])));
    }

    public function testAnyFacetIsDetected(): void
    {
        $this->assertTrue(searchHasActiveFacet(searchNormalizeFilters(['favourites' => '1'])));
        $this->assertTrue(searchHasActiveFacet(searchNormalizeFilters(['classification' => [0]])));
        $this->assertTrue(searchHasActiveFacet(searchNormalizeFilters(['attachment_has' => true])));
        $this->assertTrue(searchHasActiveFacet(searchNormalizeFilters(['folder' => '42'])));
    }

    // -------------------------------------------------------------------
    // searchItemRestrictionSql()
    // -------------------------------------------------------------------

    public function testRestrictionAllowsUnrestrictedItemsAndNamedUser(): void
    {
        $sql = searchItemRestrictionSql(7, [], 'i', 'teampass_restriction_to_roles');
        $this->assertStringContainsString("COALESCE(i.restricted_to, '') = ''", $sql);
        $this->assertStringContainsString("LIKE '%;7;%'", $sql);
    }

    public function testRestrictionAddsRoleGrantWhenUserHoldsRoles(): void
    {
        $sql = searchItemRestrictionSql(7, [3, 4], 'i', 'teampass_restriction_to_roles');
        $this->assertStringContainsString('search_role_grant.role_id IN (3,4)', $sql);
    }

    public function testRestrictionOmitsRoleClauseWithoutRoles(): void
    {
        $sql = searchItemRestrictionSql(7, [], 'i', 'teampass_restriction_to_roles');
        $this->assertStringNotContainsString('search_role_grant', $sql);
    }

    public function testRestrictionFailsClosedOnBadInput(): void
    {
        $this->assertSame('(1 = 0)', searchItemRestrictionSql(0, [], 'i', 'teampass_restriction_to_roles'));
        $this->assertSame('(1 = 0)', searchItemRestrictionSql(7, [], 'i; DROP', 'teampass_restriction_to_roles'));
        $this->assertSame('(1 = 0)', searchItemRestrictionSql(7, [], 'i', 'bad table'));
    }

    public function testUnrestrictedBranchAcceptsOnlyTheCanonicalEmptyValue(): void
    {
        // Regression guard for the command palette (PR #5359).
        //
        // The "no restriction at all" branch is an equality against the empty
        // string, so it accepts exactly what items.restricted_to stores for an
        // unrestricted item and nothing else. updateCacheTable() normalizes an
        // empty restriction to '0' before writing the search cache, which is
        // why this predicate must always be built on the items alias: fed the
        // cache alias, every unrestricted item fails all three branches and
        // disappears from the results.
        $sql = searchItemRestrictionSql(7, [], 'i', 'teampass_restriction_to_roles');

        $this->assertSame(
            1,
            preg_match("/COALESCE\(i\.restricted_to, ''\) = '(?<accepted>[^']*)'/", $sql, $matches),
            'The predicate must keep an explicit "no restriction" branch.'
        );

        // What items.restricted_to holds for an unrestricted item.
        $this->assertSame('', $matches['accepted']);
        // What teampass_cache.restricted_to holds for that very same item.
        $this->assertNotSame('0', $matches['accepted']);
    }

    public function testNamedUserBranchDoesNotMatchTheCacheNormalizedValue(): void
    {
        // Same regression, second branch: '0' must not be read as "restricted
        // to user 0" and match by accident. The needle is taken from the
        // generated SQL and evaluated the way MySQL evaluates
        // CONCAT(';', restricted_to, ';') LIKE '%;<user>;%'.
        $sql = searchItemRestrictionSql(7, [], 'i', 'teampass_restriction_to_roles');

        $this->assertSame(
            1,
            preg_match("/LIKE '%(?<needle>;[0-9]+;)%'/", $sql, $matches),
            'The predicate must keep an explicit "named user" branch.'
        );

        $wrap = static fn (string $storedValue): string => ';' . $storedValue . ';';

        // items.restricted_to for an unrestricted item: no match, the branch above answers.
        $this->assertFalse(str_contains($wrap(''), $matches['needle']));
        // cache.restricted_to for the same item: must not match either.
        $this->assertFalse(str_contains($wrap('0'), $matches['needle']));
        // A real restriction naming the caller: match.
        $this->assertTrue(str_contains($wrap('7;9'), $matches['needle']));
        // A real restriction naming somebody else: no match.
        $this->assertFalse(str_contains($wrap('9;70'), $matches['needle']));
    }

    // -------------------------------------------------------------------
    // searchBuildWhere()
    // -------------------------------------------------------------------

    public function testBaseScopeIsAlwaysPresent(): void
    {
        $built = searchBuildWhere(searchNormalizeFilters([]), $this->context());
        $this->assertStringContainsString('c.id_tree IN %li_scope', $built['sql']);
        $this->assertStringContainsString('i.deleted_at IS NULL', $built['sql']);
        $this->assertSame([10, 11], $built['params']['scope']);
    }

    public function testFailsClosedWithoutFolderScope(): void
    {
        $built = searchBuildWhere(searchNormalizeFilters([]), $this->context(['folder_scope' => []]));
        $this->assertSame('(1 = 0)', $built['sql']);
        $this->assertSame([], $built['params']);
    }

    public function testFailsClosedWithoutUser(): void
    {
        $built = searchBuildWhere(searchNormalizeFilters([]), $this->context(['user_id' => 0]));
        $this->assertSame('(1 = 0)', $built['sql']);
    }

    public function testEachTermBecomesItsOwnAndedGroup(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['term' => 'backup prod', 'fields' => ['label', 'login']]),
            $this->context()
        );
        // Terms are ANDed, fields ORed inside a term.
        $this->assertStringContainsString('(c.label LIKE %ss_term0 OR c.login LIKE %ss_term0)', $built['sql']);
        $this->assertStringContainsString('(c.label LIKE %ss_term1 OR c.login LIKE %ss_term1)', $built['sql']);
        $this->assertSame('backup', $built['params']['term0']);
        $this->assertSame('prod', $built['params']['term1']);
    }

    public function testFolderOnlyTextSearchReturnsNoItemsInsteadOfTheWholeScope(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['term' => 'backup', 'fields' => ['folder']]),
            $this->context()
        );

        $this->assertStringContainsString('(1 = 0)', $built['sql']);
        $this->assertArrayNotHasKey('term0', $built['params']);
    }

    public function testNoArrayPlaceholderIsEmittedForEmptyArrays(): void
    {
        // MeekroDB throws "array can't be empty" on an empty array placeholder.
        $built = searchBuildWhere(searchNormalizeFilters([]), $this->context());
        foreach ($built['params'] as $value) {
            if (is_array($value)) {
                $this->assertNotCount(0, $value);
            }
        }
        $this->assertStringNotContainsString('%li_classification', $built['sql']);
        $this->assertStringNotContainsString('%ls_tags', $built['sql']);
    }

    public function testClassificationUsesCoalesceSoUnclassifiedMatches(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['classification' => [0, 3]]),
            $this->context()
        );
        $this->assertStringContainsString('COALESCE(dc.level, 0) IN %li_classification', $built['sql']);
        $this->assertSame([0, 3], $built['params']['classification']);
    }

    public function testAttachmentNameDecodesBase64AndKeepsLegacyFallback(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['attachment_name' => 'financier']),
            $this->context()
        );
        // The b64: prefix is stripped CONDITIONALLY: legacy rows are stored as
        // bare base64 with no prefix, so assuming the prefix would silently
        // never match any pre-existing attachment.
        $this->assertStringContainsString("CASE WHEN search_file.name LIKE 'b64:%'", $built['sql']);
        $this->assertStringContainsString('ELSE search_file.name END', $built['sql']);
        $this->assertStringContainsString('FROM_BASE64(SUBSTRING_INDEX(', $built['sql']);
        // Case-insensitive comparison (FROM_BASE64 returns a BLOB).
        $this->assertStringContainsString('USING utf8mb4', $built['sql']);
        // Fallback for names that are genuinely plaintext.
        $this->assertStringContainsString('OR search_file.name LIKE %ss_attachmentname', $built['sql']);
        $this->assertSame('financier', $built['params']['attachmentname']);
    }

    public function testOverdueUsesCacheTimestampWithoutJoiningLogs(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['health' => ['overdue']]),
            $this->context()
        );
        $this->assertStringContainsString('c.renewal_period > 0', $built['sql']);
        $this->assertStringContainsString('86400', $built['sql']);
        $this->assertStringNotContainsString('log_items', $built['sql']);
    }

    public function testOversharedUsesTheConfiguredThreshold(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['health' => ['overshared']]),
            $this->context(['overshared_threshold' => 25])
        );
        $this->assertStringContainsString('> 25', $built['sql']);
    }

    public function testDatesAreCastFromTheVarcharTimestampColumns(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['created_from' => '1700000000']),
            $this->context()
        );
        $this->assertStringContainsString('CAST(i.created_at AS UNSIGNED) >= %i_created_from', $built['sql']);
        $this->assertSame(1700000000, $built['params']['created_from']);
    }

    public function testCustomFieldSearchIsRestrictedToVisibleFields(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['custom_field_value' => 'acme']),
            $this->context()
        );
        $this->assertStringContainsString('search_cf.field_id IN (21,22)', $built['sql']);
        $this->assertStringContainsString("search_cf.encryption_type = 'not_set'", $built['sql']);
    }

    public function testCustomFieldSearchOnAForbiddenFieldReturnsNothing(): void
    {
        // Asking for a field the user's roles cannot see must not silently
        // widen the search to the fields they can see: it is an oracle.
        $built = searchBuildWhere(
            searchNormalizeFilters(['custom_field_value' => 'acme', 'custom_field_id' => [99]]),
            $this->context()
        );
        $this->assertStringContainsString('(1 = 0)', $built['sql']);
        $this->assertArrayNotHasKey('customfieldvalue', $built['params']);
    }

    public function testCustomFieldSearchIsSkippedWhenNoFieldIsVisible(): void
    {
        $built = searchBuildWhere(
            searchNormalizeFilters(['custom_field_value' => 'acme']),
            $this->context(['visible_field_ids' => []])
        );
        $this->assertStringNotContainsString('search_cf', $built['sql']);
    }

    public function testPersonalScopeFilter(): void
    {
        $personal = searchBuildWhere(searchNormalizeFilters(['scope_perso' => 'personal']), $this->context());
        $shared = searchBuildWhere(searchNormalizeFilters(['scope_perso' => 'shared']), $this->context());
        $this->assertStringContainsString('c.perso = 1', $personal['sql']);
        $this->assertStringContainsString('c.perso = 0', $shared['sql']);
    }
}
