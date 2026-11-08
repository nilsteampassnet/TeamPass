<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the Users administration datatable must stay reserved to the callers of that page —
 * static regression guards for GHSA-5cwc-6hfp-2mr7.
 *
 * The endpoint was gated on the 'items' page, which every authenticated user holds. Its only
 * scoping is "accounts administrated by a role the caller holds", so a plain user holding a team
 * role listed the whole team: logins, names, roles, privilege flags, last IP, MFA and lock state.
 * The sibling users.logs.datatable.php had the same defect (GHSA-qhff-v9qj-75wc).
 *
 * Locked invariants:
 * - the page gate names the Users administration page ('users'), never 'items';
 * - only the columns the page displays can be sorted: ordering on a hidden column (email, GA
 *   secret) turns the row order into an oracle on values the response never returns.
 */
class UsersDatatableAccessAuthorizationTest extends TestCase
{
    private function datatableSource(): string
    {
        $path = __DIR__ . '/../../app/sources/users.datatable.php';
        self::assertFileExists($path, 'users.datatable.php not found');
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    /**
     * 'items' is held by every authenticated user, so it can never gate the users directory.
     */
    public function testPageGateIsTheUsersAdministrationPage(): void
    {
        $src = $this->datatableSource();

        self::assertStringContainsString(
            "\$checkUserAccess->userAccessPage('users') === false",
            $src,
            'The datatable must be gated on the Users administration page'
        );

        self::assertStringNotContainsString(
            "\$checkUserAccess->userAccessPage('items')",
            $src,
            "Gating on 'items' exposes the users directory to every authenticated user"
        );
    }

    /**
     * The page that calls the datatable must require the same right, otherwise the gate above
     * would lock out a caller the page lets in (or the reverse).
     */
    public function testCallingPageRequiresTheSameRight(): void
    {
        $page = file_get_contents(__DIR__ . '/../../app/pages/users.js.php');
        self::assertIsString($page);

        self::assertStringContainsString('/sources/users.datatable.php', $page, 'users.js.php no longer calls the datatable');
        self::assertStringContainsString(
            "\$checkUserAccess->userAccessPage('users') === false",
            $page,
            'The Users page and its datatable must share the same page right'
        );
    }

    /**
     * Ordering is looked up in the displayed columns only, and the hidden ones are not in it.
     */
    public function testOnlyDisplayedColumnsAreSortable(): void
    {
        $src = $this->datatableSource();

        self::assertMatchesRegularExpression(
            '/\$aSortableColumns = array_slice\(\$aColumns, 0, (\d+)\);/',
            $src,
            'The sortable columns must be a bounded slice of $aColumns'
        );
        self::assertStringContainsString(
            '$sOrder .= $aSortableColumns[$columnIndex]',
            $src,
            'ORDER BY must take its column from the sortable list'
        );
        self::assertStringNotContainsString(
            '$sOrder .= $aColumns[$columnIndex]',
            $src,
            'ORDER BY must not reach the full column list'
        );

        // Rebuild both lists from the source and check the hidden columns are out of reach.
        self::assertSame(1, preg_match('/\$aColumns = (\[[^\]]+\]);/', $src, $columnsMatch));
        self::assertSame(1, preg_match('/\$aSortableColumns = array_slice\(\$aColumns, 0, (\d+)\);/', $src, $sliceMatch));
        self::assertGreaterThan(0, preg_match_all("/'([^']+)'/", $columnsMatch[1], $names));
        $sortable = array_slice($names[1], 0, (int) $sliceMatch[1]);

        foreach (['u.email', 'u.ga'] as $hidden) {
            self::assertContains($hidden, $names[1], "$hidden vanished from \$aColumns: review this test");
            self::assertNotContains($hidden, $sortable, "$hidden must not be sortable: it is never displayed");
        }
    }
}
