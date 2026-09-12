<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
require_once __DIR__ . '/../../app/sources/logs_filter_logic.php';

/**
 * Static regression guards for the administrator authentication lockout workflow.
 */
class AuthenticationLockoutManagementTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function testLockoutTabAndDataEndpointAreAdminOnly(): void
    {
        $page = $this->source('app/pages/utilities.logs.php');
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $branchStart = strpos($dataTable, "\$params['action'] === 'authentication_lockouts'");
        $nextBranch = strpos($dataTable, '/* FAILED AUTHENTICATION */', (int) $branchStart);

        self::assertIsInt($branchStart);
        self::assertIsInt($nextBranch);
        $branch = substr($dataTable, $branchStart, $nextBranch - $branchStart);

        self::assertStringContainsString('$isAdmin === true', $page);
        self::assertStringContainsString('id="authentication-lockouts-tab"', $page);
        self::assertStringContainsString('id="table-authentication-lockouts"', $page);
        self::assertStringContainsString("\$session->get('user-admin')", $branch);
        self::assertStringContainsString('http_response_code(403)', $branch);
        self::assertLessThan(
            strpos($branch, "prefixTable('auth_failures')"),
            strpos($branch, "\$session->get('user-admin')")
        );
    }

    public function testActiveRowsAreGroupedByExactScopeAndNeverExposeUnlockCodes(): void
    {
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $branchStart = strpos($dataTable, "\$params['action'] === 'authentication_lockouts'");
        $nextBranch = strpos($dataTable, '/* FAILED AUTHENTICATION */', (int) $branchStart);

        self::assertIsInt($branchStart);
        self::assertIsInt($nextBranch);
        $branch = substr($dataTable, $branchStart, $nextBranch - $branchStart);

        self::assertStringContainsString(
            'GROUP BY af.source, af.value',
            $branch
        );
        self::assertStringContainsString('HAVING MAX(af.unlock_at) > %s', $branch);
        self::assertStringContainsString("'source' => \$source", $branch);
        self::assertStringContainsString("'value' => (string) (\$lockoutRow['value']", $branch);
        self::assertStringNotContainsString('unlock_code', $branch);
    }

    public function testUnlockActionValidatesAndDeletesOnlyTheRequestedPair(): void
    {
        $adminQueries = $this->source('app/sources/admin.queries.php');
        $caseStart = strpos($adminQueries, "case 'authentication_lockout_remove':");
        $nextCase = strpos($adminQueries, "case 'save_option_change':", (int) $caseStart);

        self::assertIsInt($caseStart);
        self::assertIsInt($nextCase);
        $case = substr($adminQueries, $caseStart, $nextCase - $caseStart);

        self::assertStringContainsString("\$post_key !== \$session->get('key')", $case);
        self::assertStringContainsString("\$session->get('user-admin')", $case);
        self::assertStringContainsString("in_array(\$source, ['login', 'remote_ip'], true)", $case);
        self::assertStringContainsString('FILTER_VALIDATE_IP', $case);
        self::assertStringContainsString("'source = %s AND value = %s'", $case);
        self::assertStringContainsString('$source,', $case);
        self::assertStringContainsString('$value', $case);
        self::assertStringContainsString('DB::affectedRows()', $case);
        self::assertStringContainsString("'authentication_lockout_removed'", $case);
        self::assertLessThan(
            strpos($case, 'DB::delete('),
            strpos($case, "\$post_key !== \$session->get('key')")
        );
        self::assertLessThan(
            strpos($case, 'DB::delete('),
            strpos($case, "\$session->get('user-admin')")
        );
    }

    public function testLockoutUiKeepsAccountAndIpActionsIndependent(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        self::assertStringContainsString("'data': 'source'", $javascript);
        self::assertStringContainsString("'data': 'value'", $javascript);
        self::assertStringContainsString("data-source=\"' + source", $javascript);
        self::assertStringContainsString("type: 'authentication_lockout_remove'", $javascript);
        self::assertStringContainsString('authenticationLockoutMessages.ipWarning', $javascript);
        self::assertStringContainsString('authenticationLockoutMessages.clientWarning', $javascript);
    }

    public function testFailedAuthenticationLogIncludesApiEventsAndChannel(): void
    {
        // The label list moved into the filter module, where the channel facet and the displayed
        // channel both read it, so the two can no longer classify a row differently.
        self::assertSame(
            [
                'api_invalid_credentials',
                'api_invalid_apikey',
                'api_invalid_token',
                'api_token_decrypt_failed',
            ],
            logsApiFailureLabels()
        );

        foreach (logsApiFailureLabels() as $label) {
            self::assertTrue(logsSystemRowIsApi('failed_auth', $label, ''));
        }
        self::assertStringContainsString(
            'tp_src=api',
            logsSystemApiPredicate('')[0] . implode('|', array_filter(
                logsSystemApiPredicate('')[1],
                static fn (mixed $value): bool => is_string($value)
            ))
        );

        // The channel is a column of the merged view and is labelled by the client.
        $javascript = $this->source('app/pages/utilities.logs.js.php');
        self::assertStringContainsString("\$lang->get('authentication_channel')", $javascript);
        self::assertStringContainsString("\$lang->get('logs_channel_web')", $javascript);
        self::assertStringContainsString("\$lang->get('logs_channel_api')", $javascript);
    }

    public function testAuthenticationChannelIsDerivedFromTheLabelNotTheForgeableMarker(): void
    {
        // On the web path field_1 is the submitted login, so an attacker can type the marker.
        // The channel is therefore decided by the label first, and the marker only rescues the
        // one label both channels share.
        self::assertFalse(logsSystemRowIsApi('failed_auth', 'bad_credentials', 'tp_src=api'));
        self::assertFalse(logsSystemRowIsApi('failed_auth', 'bad_credentials', 'attacker | tp_src=api'));
        self::assertTrue(logsSystemRowIsApi('failed_auth', 'api_invalid_credentials', 'jdoe'));
        self::assertTrue(logsSystemRowIsApi('failed_auth', 'bruteforce_account_locked', 'jdoe | tp_src=api'));
        self::assertFalse(logsSystemRowIsApi('failed_auth', 'bruteforce_account_locked', 'jdoe'));

        // Outside failed authentications the marker is the only writer of that field.
        self::assertTrue(logsSystemRowIsApi('user_connection', 'connection', 'api'));
        self::assertTrue(logsSystemRowIsApi('user_connection', 'connection', 'x | tp_src=api'));
        self::assertFalse(logsSystemRowIsApi('user_connection', 'connection', ''));

        // A row that is not a confirmed API failure keeps field_1 verbatim.
        self::assertSame('attacker | tp_src=api', logsStripApiMarker('attacker | tp_src=api', false));
        self::assertSame('jdoe', logsStripApiMarker('jdoe | tp_src=api', true));
    }

    public function testUnlockAcceptsAStoredIpTargetThatIsNotAValidAddress(): void
    {
        $adminQueries = $this->source('app/sources/admin.queries.php');
        $caseStart = strpos($adminQueries, "case 'authentication_lockout_remove':");
        $nextCase = strpos($adminQueries, "case 'save_option_change':", (int) $caseStart);

        self::assertIsInt($caseStart);
        self::assertIsInt($nextCase);
        $case = substr($adminQueries, $caseStart, $nextCase - $caseStart);

        // getClientIpServer() falls back to 'UNKNOWN', and that value does get locked, so such a
        // row must stay removable instead of being listed forever.
        self::assertStringContainsString('$knownTarget', $case);
        self::assertStringContainsString("SELECT COUNT(*) FROM ' . prefixTable('auth_failures')", $case);
        self::assertStringContainsString('$knownTarget === false', $case);
        self::assertLessThan(
            strpos($case, 'DB::delete('),
            strpos($case, '$knownTarget = (int) DB::queryFirstField(')
        );
    }

    public function testUserColumnIsOrderedOnTheDisplayedExpression(): void
    {
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $branchStart = strpos($dataTable, "\$params['action'] === 'authentication_lockouts'");
        $nextBranch = strpos($dataTable, '/* FAILED AUTHENTICATION */', (int) $branchStart);

        self::assertIsInt($branchStart);
        self::assertIsInt($nextBranch);
        $branch = substr($dataTable, $branchStart, $nextBranch - $branchStart);

        self::assertStringContainsString('AS user_display', $branch);
        self::assertStringContainsString("'user_display',", $branch);
        self::assertStringContainsString("\$lockoutRow['user_display']", $branch);
        self::assertStringNotContainsString("'login',\n        'failure_count'", $branch);
    }

    public function testLockoutTimersAreStartedOnlyWithTheAdministratorTable(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        self::assertStringContainsString('function startAuthenticationLockoutTimers()', $javascript);
        self::assertStringContainsString('authenticationLockoutTimersStarted', $javascript);
        self::assertStringContainsString('startAuthenticationLockoutTimers();', $javascript);

        // Every interval must live inside the guarded starter.
        $starterStart = strpos($javascript, 'function startAuthenticationLockoutTimers()');
        $starterEnd = strpos($javascript, 'function showAuthenticationLockouts()', (int) $starterStart);
        self::assertIsInt($starterStart);
        self::assertIsInt($starterEnd);
        $starter = substr($javascript, $starterStart, $starterEnd - $starterStart);

        self::assertSame(2, substr_count($javascript, 'window.setInterval('));
        self::assertSame(2, substr_count($starter, 'window.setInterval('));
    }

    public function testServerPageLengthCapMatchesTheClientLengthMenu(): void
    {
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        self::assertStringContainsString('$lockoutMaxPageLength = 100;', $dataTable);
        self::assertStringContainsString("'lengthMenu': [10, 25, 50, 100]", $javascript);
    }

    public function testAccountUnlockFromUsersPageIsAudited(): void
    {
        $usersQueries = $this->source('app/sources/users.queries.php');
        $caseStart = strpos($usersQueries, 'case "reset_antibruteforce":');
        $nextCase = strpos($usersQueries, 'case "list_deleted_users":', (int) $caseStart);

        self::assertIsInt($caseStart);
        self::assertIsInt($nextCase);
        $case = substr($usersQueries, $caseStart, $nextCase - $caseStart);

        // Same event as the Logs page unlock, so the trail is complete whichever screen was used.
        self::assertStringContainsString("'authentication_lockout_removed'", $case);
        self::assertStringContainsString("'admin_action'", $case);
        self::assertStringContainsString('DB::affectedRows()', $case);
    }

    public function testSecurityOptionsProvideAShortcutToTheLockoutTab(): void
    {
        $options = $this->source('app/pages/options.php');

        self::assertStringContainsString(
            './index.php?page=utilities.logs#authentication-lockouts',
            $options
        );
        self::assertStringContainsString("authentication_lockout_manage_tip", $options);
    }
}
