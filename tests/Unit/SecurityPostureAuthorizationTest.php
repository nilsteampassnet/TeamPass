<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static wiring guards for the Security Posture authorization boundary.
 *
 * Security Posture needs an item sharekey to perform its checks, but holding that key does not
 * grant access to the item. These tests lock the *wiring*: that the canonical predicate exists,
 * reads the right grant/denial tables, delegates every decision to the DB-free logic module, and
 * is applied by every dashboard, nudge, score and persisted-health path.
 *
 * The decision logic itself (grants, denials, personal trees, item restrictions) is covered
 * behaviourally in SecurityPostureLogicTest — assert semantics there, wiring here.
 */
class SecurityPostureAuthorizationTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private function mainFunctionsSource(): string
    {
        return $this->source('app/sources/main.functions.php');
    }

    private function dashboardSource(): string
    {
        return $this->source('app/sources/dashboard.queries.php');
    }

    public function testDecisionLogicLivesInTheDbFreeModule(): void
    {
        $functions = $this->mainFunctionsSource();
        $logic = $this->source('app/sources/security_posture_logic.php');

        self::assertStringContainsString(
            "require_once __DIR__ . '/security_posture_logic.php';",
            $functions
        );
        self::assertStringContainsString('function securityPostureResolveAuthorizedFolders(', $logic);
        self::assertStringContainsString('function securityPostureItemRestrictionAllows(', $logic);
        self::assertStringContainsString('function securityPasswordHealthClassify(', $logic);

        // The module must stay unit-testable: no DB, no session, no settings lookup.
        self::assertStringNotContainsString('DB::', $logic);
        self::assertStringNotContainsString('prefixTable(', $logic);
        self::assertStringNotContainsString('SessionManager', $logic);
    }

    public function testCanonicalPredicateRequiresFolderAuthorizationBeyondSharekey(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString(
            'function securityPostureItemAccessSql(int $userId, string $itemAlias',
            $source
        );
        self::assertStringContainsString('function securityPostureAuthorizedFolderIds(', $source);
        self::assertStringContainsString("prefixTable('users_groups')", $source);
        self::assertStringContainsString("prefixTable('users_roles')", $source);
        self::assertStringContainsString("prefixTable('roles_values')", $source);
        self::assertStringContainsString(
            "['W', 'ND', 'NE', 'NDNE', 'R']",
            $source
        );
        self::assertStringContainsString('securityPostureResolveAuthorizedFolders(', $source);
    }

    public function testPredicateIsAMaterialisedFolderSetNotACorrelatedFolderWalk(): void
    {
        $source = $this->mainFunctionsSource();
        $start = strpos($source, 'function securityPostureItemAccessSql');
        self::assertIsInt($start);
        $predicate = substr($source, $start);
        $end = strpos($predicate, "\n}\n");
        self::assertIsInt($end);
        $predicate = substr($predicate, 0, $end);

        // Folder authorization is resolved once in PHP and embedded as a set.
        self::assertStringContainsString(".id_tree IN (' . implode(',', \$authorizedFolders)", $predicate);

        // No nested_tree self-join, and no reliance on the unreliable items.perso flag.
        self::assertStringNotContainsString('nested_tree', $predicate);
        self::assertStringNotContainsString('.perso', $predicate);
    }

    public function testFolderResolutionIsMemoisedPerUser(): void
    {
        $source = $this->mainFunctionsSource();

        foreach (['securityPostureAuthorizedFolderIds', 'securityPostureUserRoleIds'] as $function) {
            $start = strpos($source, 'function ' . $function);
            self::assertIsInt($start, $function . '() must exist');
            $body = substr($source, $start, 700);
            self::assertStringContainsString(
                'static $cache = [];',
                $body,
                $function . '() runs several times per request and must be memoised'
            );
        }
    }

    public function testInvalidPredicateInputsFailClosed(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString(
            "preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', \$itemAlias) !== 1",
            $source
        );
        self::assertStringContainsString("return '(1 = 0)';", $source);
        // An empty authorized set must also fail closed, never degrade to "no filter".
        self::assertStringContainsString('if (count($authorizedFolders) === 0) {', $source);
    }

    public function testExplicitFolderDenialIsRead(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString("prefixTable('users_groups_forbidden')", $source);
        self::assertStringContainsString('$deniedFolders', $source);
    }

    public function testPersonalTreeScopingIsGatedAndOwnerBased(): void
    {
        $source = $this->mainFunctionsSource();

        self::assertStringContainsString('function getOwnPersonalFolderIds(', $source);
        self::assertStringContainsString("\$postureSettings['enable_pf_feature']", $source);
        self::assertStringContainsString('$userPersonalFolderEnabled === 1', $source);
        self::assertStringContainsString('getAllPersonalFolderIds()', $source);
    }

    public function testAdministratorExclusionIsDocumentedAsIntentional(): void
    {
        // The exclusion is a design decision (identAdmin() grants browsing, show_details_item
        // returns show_details = 0), not an accident of having no users_groups row. Keep it
        // written down so it is not "fixed" back in.
        $source = $this->mainFunctionsSource();

        self::assertMatchesRegularExpression('/administrators.*identAdmin\(\)/s', $source);
        self::assertStringContainsString('show_details = 0', $source);
    }

    public function testItemRestrictionsCanOnlyNarrowFolderAccess(): void
    {
        $source = $this->mainFunctionsSource();
        $folderScope = strpos($source, ".id_tree IN (' . implode(',', \$authorizedFolders)");
        $restrictionScope = strpos($source, 'posture_any_restricted_role.item_id');

        self::assertIsInt($folderScope);
        self::assertIsInt($restrictionScope);
        self::assertLessThan(
            $restrictionScope,
            $folderScope,
            'Folder scoping must be applied before the item restriction clause'
        );
        self::assertStringContainsString("prefixTable('restriction_to_roles')", $source);
        self::assertStringContainsString('COALESCE(\' . $itemAlias . \'.restricted_to', $source);
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

    public function testTheLegacyPersonalOnlyGuardIsGone(): void
    {
        // It had no caller left once every posture query moved to the canonical predicate;
        // keeping a half-check named "scope" around invites reuse as an authorization decision.
        self::assertStringNotContainsString(
            'function personalScopeSqlForUser(',
            $this->mainFunctionsSource()
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
