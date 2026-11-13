<?php

declare(strict_types=1);

namespace TeampassTests\LoginDeletedHomonym;

use Exception;
use PHPUnit\Framework\TestCase;

/** MeekroDB stand-in: one deleted account "jdoe_deleted_1700000000" may exist. */
final class DB
{
    public static int $count = 0;
    public static bool $deletedAccount = true;

    /** @return array<string, mixed>|null */
    public static function queryFirstRow(string $sql, mixed ...$args): ?array
    {
        if (str_contains($sql, 'deleted_at IS NOT NULL') === true) {
            self::$count = self::$deletedAccount ? 1 : 0;

            return self::$deletedAccount ? ['id' => 2, 'login' => 'jdoe_deleted_1700000000'] : null;
        }

        return null;
    }

    public static function count(): int
    {
        return self::$count;
    }
}

final class Session
{
    public function get(string $key): mixed
    {
        return null;
    }
}

final class SessionManager
{
    public static function getSession(): Session
    {
        return new Session();
    }
}

function prefixTable(string $table): string
{
    return 'teampass_' . $table;
}

function isOauth2LoginBoundToUser(string $login): bool
{
    return false;
}

/** @return array<string, mixed>|null The active account "jdoe", when the test says it exists */
function getUserCompleteData(?string $login): ?array
{
    DB::$count = LoginDeletedHomonymTest::$activeAccount ? 1 : 0;

    return LoginDeletedHomonymTest::$activeAccount ? ['id' => 1, 'login' => 'jdoe', 'auth_type' => 'local'] : null;
}

/**
 * A deleted account ("<login>_deleted_<time>") blocked the sign-in of every account holding the
 * same login again: the check ran before looking at whether the login located an active account.
 * It must only explain an unknown login, and keep LDAP / OAuth2 from creating an account in place
 * of the deleted one.
 *
 * initialChecks::getUserInfo() is evaluated from the repository source, so the test exercises
 * the shipped code rather than a copy.
 */
final class LoginDeletedHomonymTest extends TestCase
{
    public static bool $activeAccount = true;

    public static function setUpBeforeClass(): void
    {
        if (class_exists(__NAMESPACE__ . '\\InitialChecks') === true) {
            return;
        }

        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php'));
        $start = strpos($source, '    public function getUserInfo(');
        self::assertNotFalse($start);
        $end = strpos($source, "\n    }\n", $start);
        self::assertNotFalse($end);

        // Evaluates the repository's own code, never external input.
        eval(
            'namespace ' . __NAMESPACE__ . '; use Exception; final class InitialChecks {'
            . substr($source, $start, $end - $start + 7) . '}'
        );
    }

    /** @return array<string, mixed>|string The user data, or the refusal */
    private static function signIn(bool $activeAccount, bool $deletedAccount): array|string
    {
        self::$activeAccount = $activeAccount;
        DB::$deletedAccount = $deletedAccount;
        $class = __NAMESPACE__ . '\\InitialChecks';

        try {
            // LDAP auto-creation and OAuth2 enabled: a new account could be created
            return (new $class())->getUserInfo('jdoe', '1', '1');
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function testAnActiveAccountSignsInDespiteADeletedHomonym(): void
    {
        $result = self::signIn(true, true);

        self::assertIsArray($result);
        self::assertSame(1, $result['id']);
    }

    public function testADeletedAccountStillExplainsAnUnknownLogin(): void
    {
        self::assertSame('error_user_deleted_exists', self::signIn(false, true));
    }

    public function testAnUnknownLoginWithoutDeletedAccountMayBeCreated(): void
    {
        $result = self::signIn(false, false);

        self::assertIsArray($result);
        self::assertTrue($result['ldap_user_to_be_created']);
    }
}
