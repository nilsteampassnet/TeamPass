<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the LDAP synchronization user search.
 */
class LdapSynchronizationSearchTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertNotFalse($content, $relativePath . ' must be readable');

        return (string) $content;
    }

    public function testSearchControlsArePresentAndAccessible(): void
    {
        $view = self::source('app/pages/users.php');

        self::assertStringContainsString('id="ldap-users-toolbar"', $view);
        self::assertStringContainsString('id="ldap-users-search-wrapper"', $view);
        self::assertStringContainsString('id="ldap-users-search"', $view);
        self::assertMatchesRegularExpression(
            '/<input(?=[^\r\n]*type="text")(?=[^\r\n]*role="searchbox")(?=[^\r\n]*id="ldap-users-search")(?=[^\r\n]*aria-label=)[^\r\n]*>/s',
            $view
        );
        self::assertMatchesRegularExpression(
            '/<button(?=[^\r\n]*id="ldap-users-search-clear")(?=[^\r\n]*aria-label=)(?=[^\r\n]*disabled)[^\r\n]*>/s',
            $view
        );
        self::assertStringContainsString('id="ldap-users-search-no-results" role="status"', $view);
    }

    public function testToolbarOrdersSearchBeforeListAndRoleActions(): void
    {
        $view = self::source('app/pages/users.php');
        $toolbarStart = strpos($view, 'id="ldap-users-toolbar"');
        self::assertIsInt($toolbarStart);

        $tableStart = strpos($view, 'id="ldap-users-table"', $toolbarStart);
        self::assertIsInt($tableStart);

        $toolbar = substr($view, $toolbarStart, $tableStart - $toolbarStart);
        $searchPosition = strpos($toolbar, 'id="ldap-users-search"');
        $listPosition = strpos($toolbar, 'data-action="ldap-existing-users"');
        $rolePosition = strpos($toolbar, 'data-action="ldap-add-role"');

        self::assertIsInt($searchPosition);
        self::assertIsInt($listPosition);
        self::assertIsInt($rolePosition);
        self::assertLessThan($listPosition, $searchPosition);
        self::assertLessThan($rolePosition, $listPosition);
        self::assertStringContainsString('$lang->get(\'list_users\')', $toolbar);
    }

    public function testSearchFiltersIdentityFieldsAndCanBeCleared(): void
    {
        $script = self::source('app/pages/users.js.php');

        self::assertStringContainsString('function filterLdapUsersTable()', $script);
        self::assertStringContainsString("$(document).on('input', '#ldap-users-search', filterLdapUsersTable)", $script);
        self::assertStringContainsString("$('#ldap-users-search').val('').trigger('input').focus()", $script);
        self::assertStringContainsString('entry.displayname', $script);
        self::assertStringContainsString('entry.givenname', $script);
        self::assertStringContainsString('entry.sn', $script);
        self::assertStringContainsString('entry.mail', $script);
        self::assertStringContainsString("(entry.ldap_user_groups || []).join(' ')", $script);
        self::assertStringContainsString('data-search="\' + htmlEncode(searchText) + \'"', $script);
    }

    public function testListingFailureNeverLeavesTheProgressToastOpen(): void
    {
        $script = self::source('app/pages/users.js.php');
        $start = strpos($script, 'function refreshListUsersLDAP()');
        self::assertIsInt($start);
        $end = strpos($script, 'function refreshListUsersOAuth2()', $start);
        self::assertIsInt($end);

        $function = substr($script, $start, $end - $start);
        self::assertStringContainsString(').fail(function()', $function);
        self::assertSame(
            3,
            substr_count($function, "$('.close-toastr-progress').closest('.toast').remove()"),
            'The progress toast must be closed on success, on an error answer and on a failed request'
        );

        $handler = self::source('app/sources/users.queries.php');
        $start = strpos($handler, "case 'get_list_of_users_in_ldap':");
        self::assertIsInt($start);
        $end = strpos($handler, 'case ', $start + 1);
        self::assertIsInt($end);

        $case = substr($handler, $start, $end - $start);
        self::assertStringNotContainsString(
            'catch (\LdapRecord\Auth\BindException',
            $case,
            'Only catching BindException lets search errors escape as an HTTP 500'
        );
        self::assertSame(2, substr_count($case, 'catch (\LdapRecord\LdapRecordException $e)'));
    }

    public function testFilterIsReappliedAfterAjaxRendering(): void
    {
        $script = self::source('app/pages/users.js.php');
        $renderPosition = strpos($script, "$('#row-ldap-body').html(html)");
        self::assertIsInt($renderPosition);

        $filterPosition = strpos($script, 'filterLdapUsersTable()', $renderPosition);
        self::assertIsInt($filterPosition);
        self::assertGreaterThan($renderPosition, $filterPosition);
    }
}
