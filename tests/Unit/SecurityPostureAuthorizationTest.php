<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static regression guards for the Security Posture authorization boundary.
 *
 * Security Posture needs an item sharekey to perform its checks, but holding that key does not
 * grant access to the item. These tests lock the database-backed authorization predicate and its
 * use by every dashboard, nudge, score, and persisted-health path.
 */
class SecurityPostureAuthorizationTest extends TestCase
{
    private function mainFunctionsSource(): string
    {
        $path = __DIR__ . '/../../app/sources/main.functions.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private function dashboardSource(): string
    {
        $path = __DIR__ . '/../../app/sources/dashboard.queries.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function testCanonicalPredicateRequiresFolderAuthorizationBeyondSharekey(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString(
            'function securityPostureItemAccessSql(int $userId, string $itemAlias',
            $source
        );
        self::assertStringContainsString("prefixTable('users_groups')", $source);
        self::assertStringContainsString("prefixTable('users_roles')", $source);
        self::assertStringContainsString("prefixTable('roles_values')", $source);
        self::assertStringContainsString('posture_shared_folder.id =', $source);
        self::assertStringContainsString(
            "posture_role_grant.type IN (\\'W\\', \\'ND\\', \\'NE\\', \\'NDNE\\', \\'R\\')",
            $source
        );
    }

    public function testInvalidPredicateInputsFailClosed(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString(
            "preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', \$itemAlias) !== 1",
            $source
        );
        self::assertStringContainsString("return '(1 = 0)';", $source);
    }

    public function testExplicitFolderDenialHasPriorityOverAllSharedFolderGrants(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString("prefixTable('users_groups_forbidden')", $source);
        self::assertStringContainsString('posture_forbidden.user_id = ', $source);
        self::assertStringContainsString(
            'posture_forbidden.group_id = \' . $itemAlias . \'.id_tree',
            $source
        );
    }

    public function testPersonalItemsAreLimitedToTheOwnersRootTree(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString('posture_own_root.personal_folder = 1', $source);
        self::assertStringContainsString('posture_own_root.parent_id = 0', $source);
        self::assertStringContainsString('posture_own_root.title =', $source);
        self::assertStringContainsString('posture_personal_user.personal_folder = 1', $source);
        self::assertStringContainsString(
            "posture_personal_setting.intitule = \\'enable_pf_feature\\'",
            $source
        );
        self::assertStringContainsString(
            'posture_item_folder.nleft >= posture_own_root.nleft',
            $source
        );
        self::assertStringContainsString(
            'posture_item_folder.nright <= posture_own_root.nright',
            $source
        );
        self::assertStringContainsString('$itemAlias . \'.perso = 0', $source);
    }

    public function testItemUserAndRoleRestrictionsCanOnlyNarrowFolderAccess(): void
    {
        $source = $this->mainFunctionsSource();
        $folderScope = strpos($source, 'posture_direct_grant.user_id');
        $restrictionScope = strpos($source, 'posture_any_restricted_role.item_id');

        self::assertIsInt($folderScope);
        self::assertIsInt($restrictionScope);
        self::assertLessThan($restrictionScope, $folderScope);
        self::assertStringContainsString("prefixTable('restriction_to_roles')", $source);
        self::assertStringContainsString('COALESCE(\' . $itemAlias . \'.restricted_to', $source);
        self::assertStringContainsString('posture_restricted_user_role.user_id = ', $source);
    }

    public function testDashboardNeverUsesTheLegacyPersonalOnlyGuard(): void
    {
        $source = $this->dashboardSource();

        self::assertStringNotContainsString('personalScopeSqlForUser(', $source);
        self::assertStringContainsString(
            '$accessScopeSql = securityPostureItemAccessSql($userId);',
            $source
        );
        self::assertGreaterThanOrEqual(
            7,
            substr_count($source, '$accessScopeSql'),
            'All list, summary, health, deep-scan and folder-badge queries must be scoped'
        );
    }

    public function testNudgeAndScoreQueriesUseTheSameAuthorizationPredicate(): void
    {
        $source = $this->mainFunctionsSource();
        $nudgeStart = strpos($source, 'function securityNudgeComputeCounts');
        $finalizerStart = strpos($source, 'function finalizeUserReuseFlags');
        $scoreStart = strpos($source, 'function securityScoreCompute');

        self::assertIsInt($nudgeStart);
        self::assertIsInt($finalizerStart);
        self::assertIsInt($scoreStart);

        $nudgeSource = substr($source, $nudgeStart, $finalizerStart - $nudgeStart);
        $scoreSource = substr($source, $scoreStart);
        self::assertStringContainsString('securityPostureItemAccessSql($userId)', $nudgeSource);
        self::assertStringContainsString('securityPostureItemAccessSql($userId)', $scoreSource);
    }

    public function testFinalizerPrunesUnauthorizedHealthRowsBeforeReuseIsDerived(): void
    {
        $source = $this->mainFunctionsSource();
        $finalizerStart = strpos($source, 'function finalizeUserReuseFlags');
        $refreshStart = strpos($source, 'function refreshItemHealthAfterSave');

        self::assertIsInt($finalizerStart);
        self::assertIsInt($refreshStart);
        $finalizerSource = substr($source, $finalizerStart, $refreshStart - $finalizerStart);

        $deletePosition = strpos($finalizerSource, 'DELETE ih');
        $reuseResetPosition = strpos($finalizerSource, 'SET flag_reused = 0');
        self::assertIsInt($deletePosition);
        self::assertIsInt($reuseResetPosition);
        self::assertLessThan($reuseResetPosition, $deletePosition);
        self::assertStringContainsString('OR NOT \' . $accessScopeSql', $finalizerSource);
        self::assertStringContainsString("prefixTable('sharekeys_items')", $finalizerSource);
    }
}
