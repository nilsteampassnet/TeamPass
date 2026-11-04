<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the folder-rights map of an account is reserved to the callers entitled to
 * administrate that account — static regression guard for GHSA-28mq-vx4v-72h8.
 *
 * 'user_folders_rights' reads its target from a plain POST 'user_id'. The generic target-scope
 * guard of users.queries.php keys on the encrypted 'user_id' and never ran, so any manager or HR
 * account read the folder titles, rights and contributing roles of any user, administrators and
 * accounts outside its roles included.
 *
 * Locked invariant: the case checks callerMayManageUser() on that id before loading the account.
 */
class UserFolderRightsAuthorizationTest extends TestCase
{
    /**
     * Returns the source of one case of the users.queries.php switch.
     */
    private function caseSource(string $case): string
    {
        $src = file_get_contents(__DIR__ . '/../../app/sources/users.queries.php');
        self::assertIsString($src);

        $start = strpos($src, "case '" . $case . "':");
        self::assertIsInt($start, "case '$case' not found");
        $end = strpos($src, "\n        case '", $start + 1);
        self::assertIsInt($end, "end of case '$case' not found");

        return substr($src, $start, $end - $start);
    }

    public function testTargetIsCheckedBeforeTheAccountIsLoaded(): void
    {
        $case = $this->caseSource('user_folders_rights');

        $read = strpos($case, "\$post_id = (int) filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);");
        $guard = strpos($case, 'if (callerMayManageUser($post_id) === false) {');
        $load = strpos($case, 'getUserCompleteData(null, $post_id)');

        self::assertIsInt($read, 'user_folders_rights no longer reads a plain POST user_id: review this test');
        self::assertIsInt($guard, 'user_folders_rights must check the caller may administrate the target');
        self::assertIsInt($load, 'user_folders_rights no longer loads the target account: review this test');
        self::assertGreaterThan($read, $guard, 'The check must run on the id actually used');
        self::assertLessThan($load, $guard, 'The check must run before the account is loaded');
    }

    public function testUnknownAccountIsRefused(): void
    {
        $case = $this->caseSource('user_folders_rights');

        self::assertMatchesRegularExpression(
            '/\$rowUser = getUserCompleteData\(null, \$post_id\);\s*if \(empty\(\$rowUser\) === true\) \{/',
            $case,
            'An unknown or deleted account must be refused before its data is read'
        );
    }
}
