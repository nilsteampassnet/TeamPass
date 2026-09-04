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

        self::assertStringContainsString('id="ldap-users-search"', $view);
        self::assertMatchesRegularExpression(
            '/<input(?=[^\r\n]*id="ldap-users-search")(?=[^\r\n]*aria-label=)[^\r\n]*>/s',
            $view
        );
        self::assertMatchesRegularExpression(
            '/<button(?=[^\r\n]*id="ldap-users-search-clear")(?=[^\r\n]*aria-label=)(?=[^\r\n]*disabled)[^\r\n]*>/s',
            $view
        );
        self::assertStringContainsString('id="ldap-users-search-no-results" role="status"', $view);
    }

    public function testSearchFiltersIdentityFieldsAndCanBeCleared(): void
    {
        $script = self::source('app/pages/users.js.php');

        self::assertStringContainsString('function filterLdapUsersTable()', $script);
        self::assertStringContainsString("$(document).on('input keyup search', '#ldap-users-search', filterLdapUsersTable)", $script);
        self::assertStringContainsString("$('#ldap-users-search').val('').trigger('input').focus()", $script);
        self::assertStringContainsString('entry.displayname', $script);
        self::assertStringContainsString('entry.givenname', $script);
        self::assertStringContainsString('entry.sn', $script);
        self::assertStringContainsString('entry.mail', $script);
        self::assertStringContainsString('data-search="\' + htmlEncode(searchText) + \'"', $script);
    }

    public function testFilterIsReappliedAfterAjaxRendering(): void
    {
        $script = self::source('app/pages/users.js.php');
        $renderPosition = strpos($script, "$('#row-ldap-body').html(html)");
        $filterPosition = strpos($script, 'filterLdapUsersTable()', (int) $renderPosition);

        self::assertIsInt($renderPosition);
        self::assertIsInt($filterPosition);
        self::assertGreaterThan($renderPosition, $filterPosition);
    }
}
