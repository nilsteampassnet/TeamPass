<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $page = $this->source('app/pages/utilities.logs.php');

        foreach (
            [
                'api_invalid_credentials',
                'api_invalid_apikey',
                'api_invalid_token',
                'api_token_decrypt_failed',
            ] as $label
        ) {
            self::assertStringContainsString("'{$label}'", $dataTable);
        }

        self::assertStringContainsString("'tp_src=api'", $dataTable);
        self::assertStringContainsString("authentication_channel_api", $dataTable);
        self::assertStringContainsString("authentication_channel_web_unknown", $dataTable);
        self::assertStringContainsString("\$lang->get('authentication_channel')", $page);
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
