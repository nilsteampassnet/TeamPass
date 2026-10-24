<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: a pending database upgrade must refuse every sign-in path on the server.
 *
 * The login page only disabled its own button. The OAuth2 callback calls identify.php directly,
 * so an OAuth2 user reached identUser() on a stale schema and crashed every login with an
 * HTTP 500 (NestedTree selecting nested_tree.deletion_protected before the 3.2.2.7 migration).
 */
final class LoginUpgradeGateTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function functionBody(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        self::assertIsInt($start, $name . '() not found');
        $end = strpos($source, "\nfunction ", $start + 1);

        return substr($source, $start, $end === false ? null : $end - $start);
    }

    public function testInitialChecksRefuseSignInBeforeAnyOtherCheck(): void
    {
        $identify = (string) file_get_contents(self::root() . '/app/sources/identify.php');
        $checks = self::functionBody($identify, 'identifyDoInitialChecks');

        $gate = strpos($checks, 'upgradeRequired() === true');
        $this->assertIsInt($gate, 'identifyDoInitialChecks() must test upgradeRequired()');
        $this->assertLessThan((int) strpos($checks, 'isTooManyPasswordAttempts('), $gate);
        $this->assertLessThan((int) strpos($checks, 'getUserInfo('), $gate);

        // Not a failed authentication: the account must not be locked by an upgrade.
        $refusal = substr($checks, $gate, (int) strpos($checks, 'isTooManyPasswordAttempts(') - $gate);
        $this->assertStringContainsString("'skip_anti_bruteforce' => true", $refusal);
        $this->assertStringContainsString("\$lang->get('upgrade_requested_more')", $refusal);
    }

    public function testPasswordAndPasskeySignInBothRunTheInitialChecks(): void
    {
        $identify = (string) file_get_contents(self::root() . '/app/sources/identify.php');

        foreach (['identifyUser', 'identifyUserWithPasskey'] as $entryPoint) {
            $this->assertStringContainsString(
                'identifyDoInitialChecks(',
                self::functionBody($identify, $entryPoint),
                $entryPoint . '() bypasses the upgrade gate'
            );
        }
    }

    public function testLoginPageDisablesEverySignInButtonAndOauth2AutoLogin(): void
    {
        $page = (string) file_get_contents(self::root() . '/app/core/login.js.php');

        $this->assertStringContainsString(
            "\$('#but_identify_user, #but_login_with_oauth2, #but_login_with_passkey').prop('disabled', true);",
            $page
        );
        $this->assertStringContainsString(
            'if (autoLogin === 1 && loginForm === 0 && upgradePending === false) {',
            $page
        );
    }

    public function testNestedTreeCopiesAreByteIdentical(): void
    {
        $vendor = self::root() . '/app/vendor/teampassclasses/nestedtree/src/NestedTree.php';
        $includes = self::root() . '/app/includes/libraries/teampassclasses/nestedtree/src/NestedTree.php';

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two NestedTree copies have diverged — edit both identically (only vendor/ is autoloaded).'
        );
    }

    public function testNestedTreeNeverFetchesFromAnUncheckedQuery(): void
    {
        $tree = (string) file_get_contents(self::root() . '/app/vendor/teampassclasses/nestedtree/src/NestedTree.php');
        $lines = explode("\n", $tree);

        foreach ($lines as $index => $line) {
            if (str_contains($line, '$result = mysqli_query(') === false) {
                continue;
            }
            // A raw mysqli_query() is allowed only where the next lines handle a false result.
            $window = implode("\n", array_slice($lines, $index + 1, 10));
            if (str_contains($window, 'mysqli_fetch_') === false) {
                continue;
            }
            $this->assertMatchesRegularExpression(
                '/\$result !== false|\$result &&/',
                $window,
                'NestedTree.php:' . ($index + 1) . ' fetches from a query that may have failed; use runQuery()'
            );
        }
    }
}
