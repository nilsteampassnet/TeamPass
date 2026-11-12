<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: how the "Test a user" check of the OAuth page is wired. It must stay an
 * administrator-only, read-only mirror of the sign-in: same groups, same role resolution, same
 * order of refusals. The decisions are covered by Oauth2TestUserLogicTest, the Graph queries by
 * OAuth2EntraDirectoryTest.
 */
final class Oauth2TestUserWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testBothActionsAreForAdministratorsOnly(): void
    {
        $admin = $this->source('app/sources/admin.queries.php');

        foreach (['oauth2_test_user', 'oauth2_check_groups'] as $action) {
            $case = $this->between($admin, "case '" . $action . "':", 'break;' . "\n\n    ");
            $key = strpos($case, '$post_key !== $session->get(\'key\')');
            $adminGate = strpos($case, '(int) $session->get(\'user-admin\') !== 1');
            $decode = strpos($case, 'prepareExchangedData(' . "\n            \$post_data");
            $this->assertIsInt($key, $action);
            $this->assertIsInt($adminGate, $action);
            $this->assertIsInt($decode, $action);
            $this->assertLessThan($decode, $adminGate, $action . ': the admin gate must come before the payload is read');
        }
    }

    public function testTheReportIsReadOnly(): void
    {
        $admin = $this->source('app/sources/admin.queries.php');
        foreach (['oauth2TestCallDirectory', 'oauth2TestUserReport', 'oauth2CheckGroupsReport'] as $function) {
            $body = $this->functionBody($admin, $function);
            foreach (['DB::update', 'DB::insert', 'DB::delete', 'DB::replace', 'logEvents('] as $write) {
                $this->assertStringNotContainsString($write, $body, $function);
            }
        }
    }

    public function testTheTestReadsTheGroupsLikeTheSignIn(): void
    {
        $controller = $this->source('app/vendor/teampassclasses/oauth2controller/src/OAuth2Controller.php');

        $this->assertStringContainsString('$this->entraUserGroups(\'me\', $token->getToken())', $this->functionBody($controller, 'callback'));
        $this->assertStringContainsString(
            '$this->entraUserGroups(\'users/\' . rawurlencode((string) $user[\'id\']), $token)',
            $this->functionBody($controller, 'describeDirectoryUsers')
        );
    }

    public function testTheRolesAreResolvedLikeAtTheAccountCreation(): void
    {
        $creation = $this->functionBody($this->source('app/sources/identify.php'), 'externalAdCreateUser');
        $report = $this->functionBody($this->source('app/sources/admin.queries.php'), 'oauth2TestUserReport');

        foreach ([$creation, $report] as $body) {
            $this->assertStringContainsString('oauth2RoleTitleCandidates(', $body);
            $this->assertStringContainsString('WHERE title IN %ls', $body);
            $this->assertStringContainsString("\$SETTINGS['oauth_selfregistered_user_belongs_to_role']", $body);
        }
        // Without the groups that only open the self-registration, as createOauth2User() does
        $this->assertStringContainsString('oauth2GroupsForRoleMatching($allowedGroups, $groups)', $report);
    }

    public function testTheReportMirrorsTheChecksOfTheSignIn(): void
    {
        $report = $this->functionBody($this->source('app/sources/admin.queries.php'), 'oauth2TestUserReport');

        // The account as initialChecks::getUserInfo() locates it, the identity as the callback builds it
        $this->assertStringContainsString('WHERE login = %s AND deleted_at IS NULL', $report);
        $this->assertStringContainsString('OAuth2Controller::entraIdentity([\'id\' => $directoryUser[\'id\']])', $report);
        $this->assertStringContainsString('teampassGetSystemAccountIds()', $report);
        $this->assertStringContainsString("\$userLogin . '_deleted_%'", $report);
        $this->assertStringContainsString("\$SETTINGS['oauth2_link_existing_accounts']", $report);
        $this->assertStringContainsString("\$SETTINGS['ldap_mode']", $report);
    }

    public function testTheResultsAreRenderedAsText(): void
    {
        $page = $this->source('app/pages/oauth.php');
        $script = $this->source('app/pages/oauth.js.php');

        // Typed into the page, never saved as a setting by the admin pages' auto-save
        $this->assertMatchesRegularExpression("/class='form-control form-control-sm no-save' id='oauth2-test-user-login'/", $page);

        foreach (['oauth2TestRenderUsers', 'oauth2TestRenderGroups'] as $function) {
            $this->assertStringNotContainsString('.html(', $this->functionBody($script, $function), $function);
        }
        // The LDAP test handler copied here was dead code
        $this->assertStringNotContainsString('ldap.queries.php', $script);
        $this->assertStringNotContainsString('console.log', $script);
    }

    public function testGraphErrorsAreEscapedBeforeReachingTheNotification(): void
    {
        $call = $this->functionBody($this->source('app/sources/admin.queries.php'), 'oauth2TestCallDirectory');

        $this->assertStringContainsString("htmlspecialchars((string) \$answer['message'], ENT_QUOTES, 'UTF-8')", $call);
        $this->assertStringContainsString("=== 403", $call);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(self::ROOT . '/' . $path);
        $this->assertIsString($source, $path);

        return str_replace("\r\n", "\n", $source);
    }

    /** Body of a function or method, up to the next declaration. */
    private function functionBody(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertIsInt($start, 'Function not found: ' . $name);
        $found = preg_match(
            '/\n[ \t]*(?:(?:public|protected|private|static)[ \t]+)*function[ \t]+\w+\(/',
            $source,
            $next,
            PREG_OFFSET_CAPTURE,
            $start + 1
        );

        return $found === 1 ? substr($source, $start, $next[0][1] - $start) : substr($source, $start);
    }

    private function between(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        $this->assertIsInt($start, 'Start marker not found: ' . $startMarker);
        $end = strpos($source, $endMarker, $start + strlen($startMarker));
        $this->assertIsInt($end, 'End marker not found: ' . $endMarker);

        return substr($source, $start, $end - $start);
    }
}
