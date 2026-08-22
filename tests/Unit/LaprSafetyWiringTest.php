<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      LaprSafetyWiringTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Static integration guards for LAPR safety rules that span web handlers,
 * background processes, settings, and the item-detail interface.
 */
class LaprSafetyWiringTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function testDisablingTheModuleCancelsPendingTasksAndStopsWorkers(): void
    {
        $admin = $this->source('app/sources/admin.queries.php');
        $worker = $this->source('app/scripts/background_tasks___worker.php');
        $rotation = $this->source('app/scripts/traits/LAPRRotationTrait.php');

        self::assertStringContainsString("\$post_field === 'lapr_enabled'", $admin);
        foreach (['lapr_ssh_test', 'lapr_discover', 'lapr_rotation'] as $taskType) {
            self::assertStringContainsString("'" . $taskType . "'", $admin);
        }
        self::assertStringContainsString("strpos(\$this->processType, 'lapr_') === 0", $worker);
        self::assertStringContainsString("'ERR_LAPR_DISABLED'", $worker);

        $mutationPosition = strpos($rotation, '$service->changePassword(');
        $lastSwitchPosition = strrpos($rotation, 'if (laprIsModuleEnabledFresh() === false)');
        self::assertIsInt($mutationPosition);
        self::assertIsInt($lastSwitchPosition);
        self::assertLessThan($mutationPosition, $lastSwitchPosition);
    }

    public function testEndpointAndManagedAccountRelationshipsAreGuardedServerSide(): void
    {
        $functions = $this->source('app/sources/lapr.functions.php');
        $endpoints = $this->source('app/sources/lapr_endpoints.queries.php');
        $accounts = $this->source('app/sources/lapr_accounts.queries.php');
        $rotation = $this->source('app/scripts/traits/LAPRRotationTrait.php');

        foreach ([
            'function laprEndpointTargetExists',
            'function laprEndpointCredentialConflict',
            'function laprManagedItemCredentialConflict',
            'function laprRemoteAccountAlreadyManaged',
        ] as $helper) {
            self::assertStringContainsString($helper, $functions);
        }

        self::assertGreaterThanOrEqual(2, substr_count($endpoints, 'laprEndpointTargetExists('));
        self::assertGreaterThanOrEqual(2, substr_count($endpoints, 'laprEndpointCredentialConflict('));
        self::assertStringContainsString('laprAddEndpoint($dataReceived, $session,', $endpoints);
        self::assertStringContainsString('laprUserCanReadFolder((int) $credentialItem', $endpoints);
        self::assertStringContainsString('laprRemoteAccountAlreadyManaged(', $accounts);
        self::assertStringContainsString('laprManagedItemCredentialConflict(', $accounts);
        self::assertStringContainsString('laprManagedItemCredentialConflict(', $rotation);
        self::assertStringContainsString("'ERR_KEY_CREDENTIAL_MANAGED'", $rotation);
        self::assertStringContainsString("'ERR_SHARED_PASSWORD_CREDENTIAL'", $rotation);
        self::assertStringContainsString("'ERR_DUPLICATE_ENDPOINT_TARGET'", $rotation);
        self::assertStringContainsString('$permanentConfigurationFailure', $rotation);
    }

    public function testPrivateKeyCredentialCannotBeOverwrittenDuringPasswordRotation(): void
    {
        $rotation = $this->source('app/scripts/traits/LAPRRotationTrait.php');
        $credentialSync = strpos($rotation, '// R5 — SSH credential sync:');
        self::assertIsInt($credentialSync);
        $credentialSyncBlock = substr($rotation, $credentialSync, 1800);

        self::assertStringContainsString(
            "(string) \$account['ssh_auth_method'] === 'password'",
            $credentialSyncBlock
        );
        self::assertStringContainsString('$this->laprUpdateItemPassword(', $credentialSyncBlock);
    }

    public function testTeamPassHostManagementRequiresBreakGlassControls(): void
    {
        $functions = $this->source('app/sources/lapr.functions.php');
        $endpoints = $this->source('app/sources/lapr_endpoints.queries.php');
        $endpointPage = $this->source('app/pages/lapr_endpoints.php');
        $handler = $this->source('app/scripts/background_tasks___handler.php');
        $rotation = $this->source('app/scripts/traits/LAPRRotationTrait.php');
        $itemsJavascript = $this->source('app/pages/items.js.php');
        $adminPage = $this->source('app/pages/admin_lapr.php');
        $install = $this->source('public/install/install-steps/run.step5.php');
        $upgrade = $this->source('public/install/upgrade_run_3.2.2.php');

        self::assertStringContainsString('function laprClassifySelfTarget', $functions);
        self::assertStringContainsString("\$SETTINGS['lapr_allow_self_management']", $endpoints);
        self::assertStringContainsString('self_management_ack', $endpoints);
        self::assertStringContainsString('id="lapr-ep-self-management-ack"', $endpointPage);
        self::assertStringContainsString("\$trigger !== 'manual'", $rotation);
        self::assertStringContainsString("'ERR_SELF_TARGET_AUTOMATIC_ROTATION_BLOCKED'", $rotation);
        self::assertStringContainsString('laprClassifySelfTarget(', $handler);
        self::assertStringContainsString("'data-self-target'", $itemsJavascript);
        self::assertStringContainsString('selfRotationWarning', $itemsJavascript);
        self::assertStringContainsString('lapr_allow_self_management', $adminPage);
        self::assertStringContainsString("'lapr_allow_self_management', '0'", $install);
        self::assertStringContainsString("'lapr_allow_self_management', '0'", $upgrade);
    }

    public function testDisabledMonitoringIsNeutralAndKeepsLaprTabsVisible(): void
    {
        $monitoring = $this->source('app/sources/lapr.monitoring.functions.php');
        $statistics = $this->source('app/pages/statistics.js.php');
        $health = $this->source('app/pages/utilities.health.js.php');

        self::assertStringContainsString('return laprMonitoringDisabledSnapshot();', $monitoring);
        self::assertStringContainsString('return laprMonitoringEmptyStatisticsPayload(', $monitoring);
        self::assertStringContainsString("$('#tp-ops-lapr-tab').closest('li').show()", $statistics);
        self::assertStringContainsString('if (lapr.enabled === false)', $statistics);
        self::assertStringContainsString('if (lapr.enabled === false)', $health);
        self::assertStringContainsString('lapr_module_disabled', $health);
    }

    public function testRotateOnEnrollmentQueuesAnImmediateBackgroundRotation(): void
    {
        $accounts = $this->source('app/sources/lapr_accounts.queries.php');
        $javascript = $this->source('app/pages/lapr_accounts.js.php');
        $addStart = strpos($accounts, 'function laprAddAccount(');
        $addEnd = strpos($accounts, 'function laprDeleteAccount(', (int) $addStart);
        self::assertIsInt($addStart);
        self::assertIsInt($addEnd);
        $addBlock = substr($accounts, $addStart, $addEnd - $addStart);

        self::assertStringContainsString('SELECT frequency_days, rotate_on_enroll', $addBlock);
        self::assertStringContainsString('laprShouldQueueEnrollmentRotation(', $addBlock);
        self::assertStringContainsString('DB::startTransaction();', $addBlock);
        self::assertStringContainsString('DB::commit();', $addBlock);
        self::assertGreaterThanOrEqual(2, substr_count($addBlock, 'DB::rollback();'));
        self::assertStringContainsString('SELECT increment_id FROM ', $addBlock);
        self::assertStringContainsString('ORDER BY increment_id DESC LIMIT 1', $addBlock);
        self::assertStringNotContainsString(
            "'SELECT id FROM ' . prefixTable('background_tasks')",
            $addBlock
        );
        self::assertStringContainsString("'process_type' => 'lapr_rotation'", $addBlock);
        self::assertStringContainsString("'trigger' => 'enroll'", $addBlock);
        self::assertStringContainsString("'item_id' => \$accountId", $addBlock);
        self::assertStringContainsString('triggerBackgroundHandler();', $addBlock);
        self::assertStringContainsString("'task_id' => \$rotationTaskId", $addBlock);
        self::assertStringContainsString('rotation_skipped_manual_only', $addBlock);

        self::assertStringContainsString("'triggerEnroll' => \$lang->get('lapr_trigger_enroll')", $javascript);
        self::assertStringContainsString('enroll: laprAccLang.triggerEnroll', $javascript);
        self::assertStringContainsString('laprPollRotation(taskId, 0)', $javascript);
    }

    public function testAutomaticSchedulerUsesTheLaprAdminSettingsNamespace(): void
    {
        $handler = $this->source('app/scripts/background_tasks___handler.php');
        $schedulerStart = strpos($handler, 'private function handleScheduledLAPRRotations(): void');
        $schedulerEnd = strpos($handler, 'private function computeNextDailyRunAt(', (int) $schedulerStart);
        self::assertIsInt($schedulerStart);
        self::assertIsInt($schedulerEnd);
        $scheduler = substr($handler, $schedulerStart, $schedulerEnd - $schedulerStart);

        foreach ([
            "getSettingValue('lapr_enabled', '0', 'admin')",
            "getSettingValue('lapr_scheduler_enabled', '0', 'admin')",
            "getSettingValue('lapr_scheduler_interval_minutes', '5', 'admin')",
            "getSettingValue('lapr_scheduler_next_run_at', '0', 'admin')",
        ] as $settingRead) {
            self::assertStringContainsString($settingRead, $scheduler);
        }

        self::assertGreaterThanOrEqual(
            2,
            substr_count($scheduler, "'lapr_scheduler_next_run_at'")
        );
        // Four reads and both next-run writes must all target the same namespace.
        self::assertGreaterThanOrEqual(6, substr_count($scheduler, "'admin'"));
        self::assertDoesNotMatchRegularExpression(
            "/getSettingValue\\('lapr_[^']+',\\s*'[^']*'\\)/",
            $handler
        );
        self::assertStringContainsString(
            "getSettingValue('lapr_audit_retention_days', '365', 'admin')",
            $handler
        );
        self::assertStringContainsString(
            "private function getSettingValue(string \$key, string \$default = '', string \$type = 'settings')",
            $handler
        );
        self::assertStringContainsString(
            "private function upsertSettingValue(string \$key, string \$value, string \$type = 'settings')",
            $handler
        );
        self::assertStringContainsString("\$type = \$type === 'admin' ? 'admin' : 'settings';", $handler);
        self::assertStringContainsString('ConfigManager::invalidateCache();', $handler);
    }

    public function testItemHistoryIdentifiesAutomaticLaprRotations(): void
    {
        $items = $this->source('app/sources/items.queries.php');
        $english = $this->source('app/includes/language/english.php');
        $french = $this->source('app/includes/language/french.php');
        $historyStart = strpos($items, "case 'load_item_history':");
        $historyEnd = strpos($items, "case 'suggest_item_change':", (int) $historyStart);
        self::assertIsInt($historyStart);
        self::assertIsInt($historyEnd);
        $history = substr($items, $historyStart, $historyEnd - $historyStart);

        self::assertStringContainsString('l.id_user as id_user', $history);
        self::assertStringContainsString('$isLaprSystemPasswordChange', $history);
        self::assertStringContainsString("(int) TP_USER_ID", $history);
        self::assertStringContainsString("\$reason[0] === 'at_pw'", $history);
        self::assertGreaterThanOrEqual(
            2,
            substr_count($history, "\$lang->get('lapr_system_scheduler')")
        );
        self::assertStringContainsString("'lapr_system_scheduler' => 'LAPR system'", $english);
        self::assertStringContainsString("'lapr_system_scheduler' => 'Système LAPR'", $french);
    }

    public function testRemovingAnAccountCancelsPendingRotationsWithoutResurrection(): void
    {
        $accounts = $this->source('app/sources/lapr_accounts.queries.php');
        $rotation = $this->source('app/scripts/traits/LAPRRotationTrait.php');
        $deleteStart = strpos($accounts, 'function laprDeleteAccount(');
        $deleteEnd = strpos($accounts, 'function laprUpdateAccountPolicy(', (int) $deleteStart);
        self::assertIsInt($deleteStart);
        self::assertIsInt($deleteEnd);
        $deleteBlock = substr($accounts, $deleteStart, $deleteEnd - $deleteStart);

        self::assertStringContainsString("prefixTable('background_tasks')", $deleteBlock);
        self::assertStringContainsString("'is_in_progress' => -1", $deleteBlock);
        self::assertStringContainsString("'ERR_ACCOUNT_DELETED'", $deleteBlock);
        self::assertStringContainsString("'lapr_rotation'", $deleteBlock);

        $freshStateCheck = strpos($rotation, '$accountStillManaged = (int) DB::queryFirstField(');
        $remoteMutation = strpos($rotation, '$service->changePassword(');
        self::assertIsInt($freshStateCheck);
        self::assertIsInt($remoteMutation);
        self::assertLessThan($remoteMutation, $freshStateCheck);
        self::assertGreaterThanOrEqual(
            6,
            substr_count($rotation, "'id = %i AND status != %s', \$accountId, 'deleted'")
        );
    }

    public function testLaprInterfaceNeverUsesNativeBrowserDialogs(): void
    {
        $source = '';
        foreach ([
            'app/pages/lapr_endpoints.js.php',
            'app/pages/lapr_accounts.js.php',
            'app/pages/lapr_policies.js.php',
            'app/pages/admin_lapr.js.php',
            'app/pages/statistics.js.php',
            'app/pages/utilities.health.js.php',
        ] as $script) {
            $source .= "\n" . $this->source($script);
        }

        // The item page is shared by many features. Audit only its complete
        // LAPR integration block so an unrelated legacy dialog cannot make
        // this focused contract ambiguous.
        $items = $this->source('app/pages/items.js.php');
        $itemLaprStart = strpos($items, 'const TP_LAPR_ITEM_LANG');
        $itemLaprEnd = strpos($items, 'var tpFolderProgress', (int) $itemLaprStart);
        self::assertIsInt($itemLaprStart);
        self::assertIsInt($itemLaprEnd);
        $source .= "\n" . substr($items, $itemLaprStart, $itemLaprEnd - $itemLaprStart);

        // Exclude method calls such as alertify.alert(): the forbidden forms
        // are the browser globals alert(), confirm(), and prompt(), including
        // calls explicitly qualified through a browser global object.
        self::assertDoesNotMatchRegularExpression(
            '/(?:^|[^A-Za-z0-9_$.])(?:(?:window|globalThis|self|top|parent)\s*\.\s*)?(?:alert|confirm|prompt)\s*\(/mi',
            $source
        );
        self::assertGreaterThanOrEqual(5, substr_count($source, 'launchConfirmDialog('));
        self::assertStringContainsString(".modal('show')", $source);
    }
}
