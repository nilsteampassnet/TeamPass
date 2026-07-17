<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the users.queries.php request dispatch must stay behind the target-scope
 * guard — static regression guards for GHSA-58ph-5gg6-h2v8.
 *
 * The advisory covered a legacy branch selected by the mere *absence* of a "type"
 * parameter. Sitting outside the `if (null !== $post_type)` block, it skipped the
 * manager/admin target-scope guard and re-implemented a laxer check, letting a manager
 * write a target account's 'pw' verbatim (unhashed), plus 'login' / 'email'. Its sibling
 * branch wrote the 'admin' flag the same way; chained, the two escalated a role-scoped
 * manager to full administrator.
 *
 * Locked invariants:
 * - the legacy 'newValue' and 'newadmin' branches stay removed;
 * - the whole request dispatch stays inside the `null !== $post_type` block, so no
 *   handler can be reached without the guard;
 * - the guard still refuses a target that is an administrator or another manager;
 * - the 'save_user_change' allow-list never grows to cover credential or privilege
 *   columns.
 */
class UserFieldUpdateAuthorizationTest extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    private function usersQueriesSource(): string
    {
        return $this->readSource('/app/sources/users.queries.php');
    }

    private function mainFunctionsSource(): string
    {
        return $this->readSource('/app/sources/main.functions.php');
    }

    /**
     * The two legacy branches must not come back: they were reachable by any
     * authenticated caller and bypassed the guard entirely.
     */
    public function testLegacyUnguardedBranchesAreRemoved(): void
    {
        $src = $this->usersQueriesSource();

        self::assertDoesNotMatchRegularExpression(
            "/elseif\s*\(\s*!empty\s*\(\s*filter_input\s*\(\s*INPUT_POST\s*,\s*'newValue'/",
            $src,
            "GHSA-58ph-5gg6-h2v8: the legacy 'newValue' branch wrote 'pw'/'login'/'email' verbatim outside the target-scope guard"
        );

        self::assertDoesNotMatchRegularExpression(
            "/elseif\s*\(\s*null\s*!==\s*filter_input\s*\(\s*INPUT_POST\s*,\s*'newadmin'/",
            $src,
            "GHSA-58ph-5gg6-h2v8: the legacy 'newadmin' branch set the 'admin' flag outside the target-scope guard"
        );

        self::assertStringNotContainsString(
            '$post_newValue',
            $src,
            'Leftover of the removed legacy branch'
        );
    }

    /**
     * Every handler must be dispatched from inside the `null !== $post_type` block,
     * which is what subjects it to the guard. A top-level `} elseif` after it would
     * reintroduce a guard-free entry point.
     */
    public function testNoRequestHandlingOutsideTheTypeGuardedBlock(): void
    {
        $src = $this->usersQueriesSource();

        self::assertSame(
            1,
            preg_match_all('/^if \(null !== \$post_type\) \{$/m', $src),
            'The single `null !== $post_type` dispatch block is the only entry point expected'
        );

        self::assertSame(
            0,
            preg_match_all('/^\} elseif /m', $src),
            'A top-level `} elseif` branch bypasses the target-scope guard — handle the action inside the switch instead'
        );
    }

    /**
     * The guard itself: a manager must never be able to act on an administrator or on
     * another manager, and a plain manager stays confined to their own roles.
     */
    public function testTargetScopeGuardRefusesPrivilegedTargets(): void
    {
        $src = $this->usersQueriesSource();

        self::assertStringContainsString(
            "if ((int) \$targetUserInfos['admin'] === 1 ||\n            (int) \$targetUserInfos['can_manage_all_users'] === 1 ||\n            (int) \$targetUserInfos['gestionnaire'] === 1) {",
            $src,
            'Managers must not be able to edit an administrator or another manager'
        );

        self::assertStringContainsString(
            "in_array(\$targetUserInfos['isAdministratedByRole'], \$session->get('user-roles_array'))",
            $src,
            'A plain manager must stay confined to the users administrated by their own roles'
        );
    }

    /**
     * 'save_user_change' is listed in $all_users_can_access and therefore carries its own
     * check. Its allow-list must never cover credential or privilege columns — that is
     * exactly what the removed branch got wrong.
     */
    public function testSaveUserChangeAllowListExcludesCredentialAndPrivilegeColumns(): void
    {
        $src = $this->usersQueriesSource();

        self::assertStringContainsString(
            "\$writableUserFields = ['login', 'name', 'lastname', 'isAdministratedByRole', 'auth_type'];",
            $src,
            'The writable-field allow-list must stay restricted to non-privileged columns'
        );

        // Guard the intent rather than the literal above: these must never appear in it.
        // 'fonction_id' was removed by the GHSA-gjc5 fix (the users column was dropped in
        // 3.1.5; roles live in users_roles) — it must not come back as a latent bypass.
        self::assertSame(1, preg_match('/\$writableUserFields = \[(.*?)\];/s', $src, $matches));
        foreach (['pw', 'email', 'admin', 'gestionnaire', 'can_manage_all_users', 'read_only', 'private_key', 'api_key', 'fonction_id'] as $forbidden) {
            self::assertStringNotContainsString(
                "'" . $forbidden . "'",
                $matches[1],
                "Column '$forbidden' must never be writable from a request-supplied field name"
            );
        }

        self::assertStringContainsString(
            '$callerMayManageTargetUser((int) $post_user_id) === false',
            $src,
            'save_user_change must confirm the caller may manage the target user'
        );
    }

    /**
     * 'update_users_rights_sharing' designates its targets through 'destination_ids', so the
     * target-scope guard — which keys on 'user_id' — never covers it. It used to authorize on the
     * *source* account only, leaving the destinations unchecked: a manager could write the privilege
     * columns of any account, including their own.
     */
    public function testRightsSharingValidatesEveryDestination(): void
    {
        $src = $this->usersQueriesSource();

        self::assertStringContainsString(
            'foreach (array_merge([$inputData[\'source_id\']], $inputData[\'destination_ids\']) as $userToCheck) {',
            $src,
            'The source and every destination must be authorization-checked'
        );

        self::assertStringContainsString(
            'if (callerMayManageUser((int) $userToCheck) === false) {',
            $src,
            'Each propagation target must be checked against the caller scope'
        );

        // The old source-only authorization must not come back.
        self::assertStringNotContainsString(
            "\$data_user = DB::queryFirstRow(\n                'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '\n                WHERE id = %i',\n                \$inputData['source_id']\n            );",
            $src,
            'Authorizing on the source account alone left every destination unchecked'
        );
    }

    /**
     * The propagated values are supplied by the client, not read from the source row, so a manager
     * must not be able to hand himself the privileged flags. A legitimate propagation never carries
     * them: get_list_of_users_for_sharing only ever offers accounts with admin = 0.
     */
    public function testRightsSharingRefusesPrivilegeGrantByNonAdmin(): void
    {
        $src = $this->usersQueriesSource();

        self::assertStringContainsString(
            "if ((int) \$session->get('user-admin') !== 1\n                && ((int) \$inputData['user_admin'] === 1\n                    || (int) \$inputData['user_hr'] === 1\n                    || (int) \$inputData['user_manager'] === 1)) {",
            $src,
            'Only an administrator may propagate admin / can_manage_all_users / gestionnaire'
        );

        self::assertStringContainsString(
            'WHERE u.admin = %i AND u.isAdministratedByRole IN %ls',
            $src,
            'The sharing list offered to a manager must stay restricted to non-admin, in-scope users'
        );
    }

    /**
     * The shared scope rule itself. Its behaviour is what every non-'user_id' action relies on.
     */
    public function testSharedScopeHelperRefusesPrivilegedTargets(): void
    {
        // The helper lives in main.functions.php so every entry point whose target is not
        // carried by 'user_id' shares one rule instead of re-implementing it.
        $src = $this->mainFunctionsSource();

        self::assertStringContainsString(
            'function callerMayManageUser(int $targetUserId): bool',
            $src,
            'The shared scope helper must exist for actions the target-scope guard cannot cover'
        );

        self::assertSame(
            1,
            preg_match('/function callerMayManageUser\(int \$targetUserId\): bool\n\{(.*?)\n\}/s', $src, $matches),
            'callerMayManageUser must be a top-level function'
        );
        $body = $matches[1];

        self::assertStringContainsString("(int) \$target['admin'] === 1", $body, 'A manager must never act on an administrator');
        self::assertStringContainsString("(int) \$target['can_manage_all_users'] === 1", $body, 'A manager must never act on an HR user');
        self::assertStringContainsString("(int) \$target['gestionnaire'] === 1", $body, 'A manager must never act on another manager');
        self::assertStringContainsString('DB::count() === 0', $body, 'An unknown target must be refused');
        self::assertStringContainsString(
            "in_array(\$target['isAdministratedByRole'], \$session->get('user-roles_array')) === false",
            $body,
            'A plain manager must stay confined to the users administrated by their own roles'
        );
    }
}
