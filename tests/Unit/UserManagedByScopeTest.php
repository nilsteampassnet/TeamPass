<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the administrating role ("managed by") of an account is bounded by the roles the
 * caller may grant, on creation as on update.
 *
 * store_user_changes already kept the stored value when a forged role came in; add_new_user
 * wrote the client value as-is, so a manager could hand a new account to the managers of any
 * role. The Users datatable scopes a manager's view on this column (GHSA-5cwc-6hfp-2mr7).
 */
class UserManagedByScopeTest extends TestCase
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

    public function testCreationRefusesANonGrantableAdministratingRole(): void
    {
        $case = $this->caseSource('add_new_user');

        $guard = strpos($case, 'in_array((int) $is_administrated_by, callerGrantableRoleIds(), true) === false');
        $insert = strpos($case, "'isAdministratedByRole' => \$is_administrated_by");

        self::assertIsInt($guard, 'add_new_user must check the administrating role against the grantable roles');
        self::assertIsInt($insert, 'add_new_user no longer writes isAdministratedByRole: review this test');
        self::assertLessThan($insert, $guard, 'The check must run before the account is written');
        self::assertStringContainsString(
            '(int) $is_administrated_by !== 0',
            $case,
            '0 (administrators only) must stay accepted, as on update'
        );
    }

    public function testUpdateKeepsTheStoredRoleWhenTheSubmittedOneIsNotGrantable(): void
    {
        $case = $this->caseSource('store_user_changes');

        self::assertStringContainsString(
            'in_array((int) $post_is_administrated_by, $grantableRoleIds, true) === false',
            $case,
            'store_user_changes must check the administrating role against the grantable roles'
        );
        self::assertStringContainsString(
            "\$post_is_administrated_by = (string) \$data_user['isAdministratedByRole'];",
            $case,
            'A forged role must fall back to the stored value'
        );
    }
}
