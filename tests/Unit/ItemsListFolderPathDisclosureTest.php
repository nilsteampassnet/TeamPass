<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/item_access_logic.php';

/**
 * Guards for the breadcrumb returned by do_items_list_in_folder (GHSA-5mff-6g7v-9v6v).
 *
 * The handler builds the path of the requested folder before its authorization check,
 * and returned it with the not_authorized answer whatever the folder. Any user could
 * send folder ids one by one and read the title and full path of every folder of the
 * instance, other users' personal trees included. The path of a folder outside the
 * user's scope may only go back when the tree shows that folder, that is when one of
 * its descendants is in the user's scope.
 */
class ItemsListFolderPathDisclosureTest extends TestCase
{
    // -------------------------------------------------------------------
    // itemAccessScopeFolderIds()
    // -------------------------------------------------------------------

    /** Accessible and own personal folders form the scope, once each, as integers. */
    public function testScopeMergesAccessibleAndPersonalFolders(): void
    {
        self::assertSame([11, 12, 20, 21], itemAccessScopeFolderIds(['11', 12, '20'], [20, '21'], [], []));
    }

    /** Explicit denials and other users' personal folders never count. */
    public function testScopeDropsDenialsAndForeignPersonalFolders(): void
    {
        self::assertSame([12], itemAccessScopeFolderIds([11, 12, 30], [], ['11'], [30]));
    }

    /** The root (0) and malformed values are not folders. */
    public function testScopeDropsNonPositiveIds(): void
    {
        self::assertSame([5], itemAccessScopeFolderIds([0, '', 'abc', -3, 5], [], [], []));
    }

    public function testEmptySessionGivesAnEmptyScope(): void
    {
        self::assertSame([], itemAccessScopeFolderIds([], [], [], []));
    }

    // -------------------------------------------------------------------
    // do_items_list_in_folder wiring
    // -------------------------------------------------------------------

    /**
     * Returns the not_authorized branch of do_items_list_in_folder, from the folder
     * check to the answer it sends.
     */
    private function notAuthorizedBranch(): string
    {
        $content = file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        self::assertIsString($content);

        $caseStart = strpos($content, "case 'do_items_list_in_folder':");
        self::assertNotFalse($caseStart, 'The do_items_list_in_folder case must exist.');
        $caseEnd = strpos($content, "\n    case '", $caseStart + 1);
        self::assertNotFalse($caseEnd, 'A case must follow do_items_list_in_folder.');
        $case = substr($content, $caseStart, $caseEnd - $caseStart);

        $check = strpos($case, "if (!in_array(\$inputData['id'], \$session->get('user-accessible_folders'))) {");
        self::assertNotFalse($check, 'The folder authorization check must exist.');
        // The later answers (next pages, authorized folder) come after this check.
        $answer = strpos($case, "'arborescence' =>");
        self::assertNotFalse($answer);
        self::assertGreaterThan($check, $answer, 'No path may be returned before the folder check.');

        return substr($case, $check, $answer - $check);
    }

    /** The path is emptied unless the requested folder leads to a folder in scope. */
    public function testPathIsEmptiedUnlessAFolderInScopeLiesBelow(): void
    {
        $branch = $this->notAuthorizedBranch();

        self::assertStringContainsString(
            '$arr_arbo = [];',
            $branch,
            'The path of a folder unrelated to the user\'s scope must not be returned.'
        );
        self::assertMatchesRegularExpression(
            '/WHERE id IN %li AND nleft > %i AND nright < %i/',
            $branch,
            'The disclosure must depend on a folder in scope strictly below the requested one.'
        );
        self::assertMatchesRegularExpression(
            '/\$requestedFolder->nleft.*\$requestedFolder->nright/s',
            $branch,
            'The descendant lookup must use the bounds of the requested folder.'
        );
    }

    /** The scope used for the lookup excludes denials and other users' personal trees. */
    public function testDescendantLookupUsesTheResolvedScope(): void
    {
        $branch = $this->notAuthorizedBranch();

        self::assertMatchesRegularExpression(
            "/itemAccessScopeFolderIds\(\s*\(array\) \\\$session->get\('user-accessible_folders'\),"
            . "\s*\(array\) \\\$session->get\('user-personal_folders'\),"
            . "\s*\(array\) \\\$session->get\('user-no_access_folders'\),"
            . "\s*\(array\) \\\$session->get\('user-forbiden_personal_folders'\)\s*\)/",
            $branch
        );
        self::assertStringContainsString(
            'count($scopeFolderIds) === 0',
            $branch,
            'An empty scope must not reach an SQL IN clause, and leads to no folder.'
        );
    }
}
