<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel 3: UserModel safe-column whitelist.
 *
 * Verifies that UserModel::getUsers() never returns credential or key
 * material regardless of what the DB query returns. This test uses a
 * simple SQL introspection approach: it reads the query string from
 * UserModel and asserts banned columns are absent.
 *
 * This test will FAIL as long as C-1 is not fixed, acting as a CI
 * guardrail against accidental reintroduction of SELECT *.
 */
class UserModelColumnsTest extends TestCase
{
    private const BANNED_COLUMNS = [
        'pw',
        'private_key',
        'public_key',
        'api_key',
        'mfa_secret',
        'key_tempo',
        'session_key',
        'last_pw',
        'otp_secret',
    ];

    /**
     * The UserModel source must not contain SELECT * or any banned column name.
     */
    public function testUserModelQueryDoesNotSelectSensitiveColumns(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../app/api/Model/UserModel.php');
        self::assertIsString($source, 'UserModel.php must be readable');

        // Guard against SELECT * — the whole point of C-1
        self::assertStringNotContainsStringIgnoringCase(
            'SELECT *',
            $source,
            'UserModel must not use SELECT * — it leaks credential columns'
        );

        // Guard against every banned column being referenced directly in the SELECT list
        foreach (self::BANNED_COLUMNS as $col) {
            // Simple heuristic: look for the column name preceded by a comma, whitespace or SELECT
            self::assertDoesNotMatchRegularExpression(
                '/\bSELECT\b[^;]*\b' . preg_quote($col, '/') . '\b/i',
                $source,
                "UserModel must not SELECT column '$col' — it is a credential / key field"
            );
        }
    }

    /**
     * The UserModel must enforce a hard limit on the number of rows returned.
     * A missing LIMIT clause opens the door for DoS via huge result sets.
     */
    public function testUserModelQueryEnforcesLimit(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../app/api/Model/UserModel.php');
        self::assertIsString($source);
        self::assertStringContainsStringIgnoringCase('LIMIT', $source, 'UserModel query must include a LIMIT clause');
    }

    /**
     * The UserController must check is_admin before dispatching to UserModel.
     */
    public function testUserControllerRequiresAdminCheck(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../app/api/Controller/Api/UserController.php');
        self::assertIsString($source, 'UserController.php must be readable');

        self::assertStringContainsString(
            'is_admin',
            $source,
            'UserController must check is_admin before returning user list'
        );

        self::assertStringContainsString(
            '403',
            $source,
            'UserController must return 403 when admin check fails'
        );
    }
}
