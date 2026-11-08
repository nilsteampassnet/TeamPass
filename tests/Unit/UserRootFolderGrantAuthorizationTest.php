<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: only an administrator may change an account's can_create_root_folder flag — static
 * regression guard for GHSA-57ww-wx2c-hx7x.
 *
 * 'update_users_rights_sharing' wrote the client-supplied 'user_rootfolder' on every destination.
 * Its privileged-flags check (GHSA-58ph-5gg6-h2v8) covered admin, HR and manager but not this flag,
 * so a manager or HR account could grant root-folder creation to the users they administrate,
 * including an account they created themselves.
 *
 * Locked invariants:
 * - the propagation drops can_create_root_folder for a non-admin caller, before the write;
 * - the user edit form (store_user_changes) keeps the stored value for a non-admin caller;
 * - account creation (add_new_user) clears it for a non-admin creator, even one who holds it.
 */
class UserRootFolderGrantAuthorizationTest extends TestCase
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

    public function testPropagationDropsTheFlagForNonAdmin(): void
    {
        $case = $this->caseSource('update_users_rights_sharing');

        self::assertSame(
            1,
            substr_count($case, "\$inputData['user_rootfolder']"),
            'The propagated root-folder flag must be written through one place only'
        );

        $column = strpos($case, "'can_create_root_folder' => \$inputData['user_rootfolder'],");
        $drop = strpos(
            $case,
            "if ((int) \$session->get('user-admin') !== 1) {\n                unset(\$userColumns['can_create_root_folder']);\n            }"
        );
        $write = strpos($case, "DB::update(\n                    prefixTable('users'),\n                    \$userColumns,");

        self::assertIsInt($column, 'update_users_rights_sharing no longer builds $userColumns: review this test');
        self::assertIsInt($drop, 'A non-admin propagation must not write can_create_root_folder');
        self::assertIsInt($write, 'The users row must be written from $userColumns');
        self::assertGreaterThan($column, $drop, 'The flag must be dropped after the column list is built');
        self::assertLessThan($write, $drop, 'The flag must be dropped before the users row is written');
    }

    public function testEditFormKeepsTheStoredFlagForNonAdmin(): void
    {
        $case = $this->caseSource('store_user_changes');

        self::assertStringContainsString(
            "'can_create_root_folder' => (int) \$session->get('user-admin') === 1\n"
            . "                    ? (empty(\$post_root_level) === true ? 0 : \$post_root_level)\n"
            . "                    : (int) (\$data_user['can_create_root_folder'] ?? 0),",
            $case,
            'store_user_changes must keep the stored root-folder flag when the caller is not an administrator'
        );
    }

    public function testAccountCreationClearsTheFlagForNonAdmin(): void
    {
        $case = $this->caseSource('add_new_user');

        $read = strpos($case, "\$post_root_level = filter_var(\$dataReceived['form-create-root-folder'], FILTER_SANITIZE_NUMBER_INT);");
        $clear = strpos($case, "if ((int) \$session->get('user-admin') !== 1) {\n                \$post_root_level = 0;\n            }");
        $insert = strpos($case, "'can_create_root_folder' => empty(\$post_root_level) === true ? 0 : \$post_root_level,");

        self::assertIsInt($read, 'add_new_user no longer reads form-create-root-folder: review this test');
        self::assertIsInt($clear, 'A non-admin creator must not grant can_create_root_folder, even when holding it');
        self::assertIsInt($insert, 'add_new_user no longer writes can_create_root_folder: review this test');
        self::assertGreaterThan($read, $clear, 'The flag must be cleared after it is read');
        self::assertLessThan($insert, $clear, 'The flag must be cleared before the account is inserted');

        self::assertStringNotContainsString(
            "(int) \$session->get('user-can_create_root_folder') !== 1) {\n                \$post_root_level = 0;",
            $case,
            'Holding the flag must not let a non-admin grant it'
        );
    }
}
