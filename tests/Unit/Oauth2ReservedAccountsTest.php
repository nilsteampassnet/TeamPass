<?php

declare(strict_types=1);

namespace TeampassTests\Oauth2ReservedAccounts;

use PHPUnit\Framework\TestCase;

/**
 * An OAuth2 sign-in must never reach the built-in administrator or an internal account (TP,
 * OTV, SSH, API).
 *
 * The exclusion compared the submitted login with 'admin', while the users table locates the
 * account through a collation that ignores case, accents and trailing spaces: "Admin@contoso.com"
 * reached the account "admin", and "tp@contoso.com" the internal account TP, which nothing
 * excluded at all. The sign-in then converted the account to OAuth2, rewrote its password and
 * opened an administrator session.
 *
 * oauth2AccountIsReserved() is evaluated from the repository source, so the test exercises the
 * shipped code rather than a copy.
 */
final class Oauth2ReservedAccountsTest extends TestCase
{
    private const SYSTEM_ACCOUNT_IDS = [9999991, 9999997, 9999998, 9999999];

    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertIsString($content, $relativePath . ' must be readable');

        return str_replace("\r\n", "\n", $content);
    }

    private static function functionSource(string $source, string $name): string
    {
        $start = strpos($source, "\nfunction " . $name . '(');
        self::assertNotFalse($start, $name . '() must exist.');
        $end = strpos($source, "\n}\n", $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start + 3);
    }

    public static function setUpBeforeClass(): void
    {
        if (function_exists(__NAMESPACE__ . '\\oauth2AccountIsReserved') === true) {
            return;
        }

        // Evaluates the repository's own code, never external input.
        eval(
            'namespace ' . __NAMESPACE__ . ';'
            . self::functionSource(self::source('app/sources/identify.php'), 'oauth2AccountIsReserved')
        );
    }

    /**
     * @param array<string, mixed> $account
     */
    private static function reserved(string $username, array $account): bool
    {
        return oauth2AccountIsReserved($username, $account, self::SYSTEM_ACCOUNT_IDS);
    }

    public function testTheAdministratorIsReservedWhateverTheCaseOfTheLogin(): void
    {
        $admin = ['id' => 1, 'login' => 'admin'];

        foreach (['admin', 'Admin', 'ADMIN', 'ádmin', 'admin '] as $login) {
            self::assertTrue(self::reserved($login, $admin), $login);
        }
    }

    public function testTheInternalAccountsAreReserved(): void
    {
        self::assertTrue(self::reserved('tp', ['id' => 9999997, 'login' => 'TP']));
        self::assertTrue(self::reserved('Otv', ['id' => 9999991, 'login' => 'OTV']));
        self::assertTrue(self::reserved('api', ['id' => 9999999, 'login' => 'API']));
        self::assertTrue(self::reserved('SSH', ['id' => 9999998, 'login' => 'SSH']));
    }

    public function testOtherAccountsAreNot(): void
    {
        self::assertFalse(self::reserved('jdoe', ['id' => 12, 'login' => 'jdoe']));
        // An account holding the administrator flag through a role may use SSO
        self::assertFalse(self::reserved('boss', ['id' => 13, 'login' => 'boss', 'admin' => 1]));
        self::assertFalse(self::reserved('administrator', ['id' => 14, 'login' => 'administrator']));
    }

    public function testTheSignInRefusesAReservedAccountBeforeAnyConversion(): void
    {
        $identify = self::source('app/sources/identify.php');
        $start = strpos($identify, "\nfunction shouldUserAuthWithOauth2(");
        self::assertNotFalse($start);
        $should = substr($identify, $start, (int) strpos($identify, "\nfunction ", $start + 1) - $start);

        $reserved = strpos($should, '$reservedAccount = oauth2AccountIsReserved($username, $userInfo, teampassGetSystemAccountIds());');
        $refusal = strpos($should, "'message' => 'user_not_allowed_to_auth_to_teampass_app'", (int) $reserved);
        $conversion = strpos($should, "'auth_type' => 'oauth2'");
        self::assertIsInt($reserved);
        self::assertIsInt($refusal);
        self::assertIsInt($conversion);
        self::assertLessThan($conversion, $refusal);

        // The OAuth2 block is gated on the account, not on the submitted login
        self::assertStringContainsString('if ($reservedAccount === false) {', $should);
        self::assertStringNotContainsString("if (\$username !== 'admin') {", $should);
        // The refusal reaches the Logs page
        self::assertStringContainsString("logEvents(\$SETTINGS, 'failed_auth', 'user_not_allowed_to_auth_to_teampass_app'", $should);
    }
}
