<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      HealthSystemConsistencyTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

class HealthSystemConsistencyTest extends TestCase
{
    private string $mainSource;
    private string $backupSource;
    private string $utilitiesSource;
    private string $healthLogsFunctionsSource;
    private string $laprMonitoringSource;
    private string $adminSource;
    private string $healthPage;
    private string $healthJavascript;
    private string $optionsPage;
    private string $optionsJavascript;
    private string $installStep;
    private string $upgradeScripts;
    private string $englishLanguage;
    private string $frenchLanguage;

    protected function setUp(): void
    {
        $root = __DIR__ . '/../..';
        $this->mainSource = $this->read($root . '/app/sources/main.functions.php');
        $this->backupSource = $this->read($root . '/app/sources/backup.functions.php');
        $this->utilitiesSource = $this->read($root . '/app/sources/utilities.queries.php');
        $this->healthLogsFunctionsSource = $this->read($root . '/app/sources/health.logs.functions.php');
        $this->laprMonitoringSource = $this->read($root . '/app/sources/lapr.monitoring.functions.php');
        $this->adminSource = $this->read($root . '/app/sources/admin.queries.php');
        $this->healthPage = $this->read($root . '/app/pages/utilities.health.php');
        $this->healthJavascript = $this->read($root . '/app/pages/utilities.health.js.php');
        $this->optionsPage = $this->read($root . '/app/pages/options.php');
        $this->optionsJavascript = $this->read($root . '/app/pages/options.js.php');
        $this->installStep = $this->read($root . '/public/install/install-steps/run.step5.php');
        // A setting may be seeded by the release that introduced it, so every
        // upgrade script carrying health defaults is searched as one corpus.
        $this->upgradeScripts = $this->read($root . '/public/install/upgrade_run_3.1.7.php')
            . $this->read($root . '/public/install/upgrade_run_3.2.2.php');
        $this->englishLanguage = $this->read($root . '/app/includes/language/english.php');
        $this->frenchLanguage = $this->read($root . '/app/includes/language/french.php');
    }

    public function testSensitiveHealthActionsRequireHealthPageAuthorization(): void
    {
        foreach (array(
            'get_health_report',
            'health_scan_corrupted_items',
            'health_get_corrupted_items_list',
            'health_check_runtime_logs',
        ) as $caseName) {
            $caseBlock = $this->switchCaseBlock($this->utilitiesSource, $caseName);
            $this->assertStringContainsString(
                "userAccessPage('utilities.health')",
                $caseBlock,
                $caseName . ' must enforce the admin-only Health permission.'
            );
        }
    }

    public function testDashboardAndHealthUseTheSameSystemSnapshot(): void
    {
        $this->assertStringContainsString(
            'function teampassGetSystemHealthOverview',
            $this->mainSource
        );
        $this->assertStringContainsString(
            'teampassGetSystemHealthOverview($SETTINGS, $lang)',
            $this->adminSource
        );
        $this->assertStringContainsString(
            'teampassGetSystemHealthOverview($SETTINGS, $lang)',
            $this->utilitiesSource
        );
        $adminHealthBlock = $this->switchCaseBlock($this->adminSource, 'get_system_health');
        $this->assertStringNotContainsString(
            "\$SETTINGS['TEAMPASS_SECRETS'] . DIRECTORY_SEPARATOR . \$SETTINGS['securefile']",
            $adminHealthBlock
        );
    }

    public function testHealthReportIncludesAnAdminSafeLaprDiagnostic(): void
    {
        $this->assertStringContainsString('laprBuildMonitoringSnapshot($SETTINGS, $now)', $this->utilitiesSource);
        $this->assertStringContainsString("'lapr' => \$laprHealth", $this->utilitiesSource);
        $this->assertStringContainsString('id="tab-health-lapr"', $this->healthPage);
        $this->assertStringContainsString('id="health-lapr"', $this->healthPage);
        $this->assertStringContainsString('tpRenderLapr(report)', $this->healthJavascript);
        $this->assertStringContainsString("'operators' => \$operators", $this->laprMonitoringSource);
        $this->assertStringContainsString(
            'WHERE admin = 0 AND deleted_at IS NULL AND can_manage_lapr = 1',
            $this->laprMonitoringSource
        );
        $this->assertStringContainsString('teampassGetSystemAccountIds()', $this->laprMonitoringSource);
        $this->assertStringContainsString('id="health-lapr-operators"', $this->healthPage);
        $this->assertStringContainsString('id="health-lapr-disabled-grants"', $this->healthPage);
        $this->assertStringContainsString("prefixTable('background_tasks')", $this->laprMonitoringSource);
        $this->assertStringContainsString("prefixTable('lapr_audit_log')", $this->laprMonitoringSource);
        $this->assertStringNotContainsString('managed_item.pw', $this->laprMonitoringSource);
        $this->assertStringNotContainsString('credential.pw', $this->laprMonitoringSource);
    }

    public function testEveryHealthTranslationKeyExistsInEnglishAndFrench(): void
    {
        /** @var array<string,string> $english */
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        /** @var array<string,string> $french */
        $french = include __DIR__ . '/../../app/includes/language/french.php';

        preg_match_all(
            '/\$lang->get\(\'([A-Za-z0-9_]+)\'\)/',
            $this->healthPage . $this->healthJavascript,
            $matches
        );
        foreach (array_unique($matches[1]) as $key) {
            $this->assertArrayHasKey($key, $english, 'english: ' . $key);
            $this->assertNotSame('', trim((string) $english[$key]), 'english: ' . $key);
            $this->assertArrayHasKey($key, $french, 'french: ' . $key);
            $this->assertNotSame('', trim((string) $french[$key]), 'french: ' . $key);
        }
    }

    public function testSecureFileHealthUsesRuntimeConstantsAndValidatesTheKey(): void
    {
        $this->assertStringContainsString("defined('TEAMPASS_SECRETS')", $this->mainSource);
        $this->assertStringContainsString("defined('SECUREFILE')", $this->mainSource);
        $this->assertStringContainsString('is_readable($path)', $this->mainSource);
        $this->assertStringContainsString('Key::loadFromAsciiSafeString', $this->mainSource);
    }

    public function testAesV2ProgressUsesOnlyNonEmptyEncryptedValues(): void
    {
        $this->assertStringContainsString("pw != '' AND pw_iv IS NOT NULL", $this->mainSource);
        $this->assertStringContainsString("pw_iv IS NULL OR pw_iv = ''", $this->mainSource);
        $this->assertStringContainsString("data != ''", $this->mainSource);
        $this->assertStringContainsString("data_iv IS NULL OR data_iv = ''", $this->mainSource);
        $this->assertStringContainsString("'applicable' => \$total > 0", $this->mainSource);
        $this->assertStringContainsString("'aes_v2' => \$aesV2Migration", $this->utilitiesSource);
        $this->assertStringContainsString('health-aes-v2-stores', $this->healthPage);
        $this->assertStringContainsString('health-aes-v2-overall-bar', $this->healthJavascript);
    }

    public function testAesV2PrivateKeyProgressExcludesEverySystemAccount(): void
    {
        $this->assertStringContainsString(
            "array('TP_USER_ID', 'OTV_USER_ID', 'SSH_USER_ID', 'API_USER_ID')",
            $this->mainSource
        );
        $this->assertStringContainsString(
            '$serviceAccountIds = teampassGetSystemAccountIds();',
            $this->mainSource
        );
        $this->assertSame(
            2,
            substr_count($this->mainSource, '$serviceAccountFilter,'),
            'The same system-account exclusion must be applied to the v2 and legacy private-key counts.'
        );
    }

    public function testPreviouslyDormantChecksReceiveTheirSettings(): void
    {
        foreach (array(
            "'redis_session_enabled'",
            "'websocket_enabled'",
            "'websocket_host'",
            "'websocket_port'",
            "'aes_v2_write_enabled'",
        ) as $setting) {
            $this->assertStringContainsString($setting, $this->utilitiesSource);
        }
    }

    public function testBackupHealthUsesSharedSchemaCompatibilityAndSchedulerCadence(): void
    {
        $this->assertStringContainsString(
            'function tpHealthGetBackupCompatibility',
            $this->backupSource
        );
        $this->assertStringContainsString(
            'tpHealthGetBackupSchedulerGracePeriod',
            $this->utilitiesSource
        );
        $this->assertStringContainsString("'items_ops_job_frequency'", $this->utilitiesSource);
        $this->assertStringContainsString(
            "'health_text_key' => 'health_backup_scheduler_disabled'",
            $this->utilitiesSource
        );
        $this->assertStringContainsString(
            'health_backup_scheduler_overdue',
            $this->utilitiesSource
        );
    }

    public function testResourceMetricsAreContainerAwareAndShowEveryFilesystem(): void
    {
        $this->assertStringContainsString('/sys/fs/cgroup/memory.max', $this->utilitiesSource);
        $this->assertStringContainsString('/sys/fs/cgroup/cpu.max', $this->utilitiesSource);
        $this->assertStringContainsString("'paths' => array(\$real)", $this->utilitiesSource);
        $this->assertStringContainsString("disk.map(function(d)", $this->healthJavascript);
        $this->assertStringContainsString("'runtime_scope'", $this->utilitiesSource);
    }

    public function testRuntimeLogsIncludeWebSocketReadAndWriteDiagnostics(): void
    {
        $runtimeLogsBlock = $this->switchCaseBlock($this->utilitiesSource, 'health_check_runtime_logs');

        // The handler must stay a thin delegation: a second inline copy of the payload would
        // drift from tpHealthReadRuntimeLogs() the next time a log is added.
        $this->assertStringContainsString(
            'tpHealthReadRuntimeLogs($SETTINGS, $lines)',
            $runtimeLogsBlock
        );
        $this->assertStringNotContainsString('tpHealthResolveLogResult(', $runtimeLogsBlock);
        $this->assertStringContainsString(
            "'websocket' => tpHealthResolveWebSocketLogResult(\$SETTINGS, \$lines)",
            $this->utilitiesSource
        );
        $this->assertStringContainsString(
            "'server_access' => tpHealthResolveLogResult('server_access', \$SETTINGS, \$lines)",
            $this->utilitiesSource
        );
        $this->assertStringContainsString('function tpHealthGetWebSocketLogPath', $this->utilitiesSource);
        $this->assertStringContainsString('function tpHealthNormalizeLogPath', $this->utilitiesSource);
        $this->assertStringContainsString('function tpHealthIsWindowsAbsolutePath', $this->utilitiesSource);
        $this->assertStringContainsString('function tpHealthResolveWebSocketLogResult', $this->utilitiesSource);
        $this->assertStringContainsString("'write_access'", $this->utilitiesSource);
        $this->assertStringContainsString('health-websocket-log-content', $this->healthPage);
        $this->assertStringContainsString("tpApplyRuntimeLogResult('health-websocket-log'", $this->healthJavascript);
        $this->assertStringContainsString('reportToExport.logs.websocket_log', $this->healthJavascript);
    }

    public function testRuntimeLogsUseBoundedLogicalRotationReader(): void
    {
        $this->assertStringContainsString('function tpHealthReadLogicalLog', $this->healthLogsFunctionsSource);
        $this->assertStringContainsString('function tpHealthReadGzipLogTail', $this->healthLogsFunctionsSource);
        $this->assertStringContainsString("'status' => 'limit_reached'", $this->healthLogsFunctionsSource);
        $this->assertStringContainsString("'source_files'", $this->utilitiesSource);
        $this->assertStringContainsString('tpHealthReadLogicalLog($logPath, $lines, 1024 * 1024 * 2)', $this->utilitiesSource);
    }

    public function testServerAccessLogIsIntegratedAcrossHealthSystem(): void
    {
        $this->assertStringContainsString('function tpHealthExtractApacheAccessLogsFromConfig', $this->utilitiesSource);
        $this->assertStringContainsString('function tpHealthExtractNginxAccessLogsFromConfig', $this->utilitiesSource);
        $this->assertStringContainsString('health-server-access-log-content', $this->healthPage);
        $this->assertStringContainsString("tpApplyRuntimeLogResult('health-server-access-log'", $this->healthJavascript);
        $this->assertStringContainsString("tpCopyRuntimeLogToClipboard('server_access')", $this->healthJavascript);
        $this->assertStringContainsString('reportToExport.logs.server_access_log', $this->healthJavascript);
        $this->assertStringContainsString("'health_server_access_log' => 'Web server access log'", $this->englishLanguage);
        $this->assertStringContainsString("'health_server_access_log' => 'Log d’accès du serveur web'", $this->frenchLanguage);
    }

    public function testRuntimeLogContentIsRedactedBeforeBeingReturned(): void
    {
        $this->assertStringContainsString('function tpHealthRedactLogLine', $this->healthLogsFunctionsSource);
        $this->assertStringContainsString('function tpHealthRedactLogContent', $this->healthLogsFunctionsSource);
        // The redaction must wrap the reader output, not sit in an unused helper.
        $this->assertStringContainsString(
            "tpHealthRedactLogContent(\$logicalLog['content'])",
            $this->utilitiesSource
        );
        foreach (array('code', 'key_tmp', 'token', 'password') as $parameter) {
            $this->assertStringContainsString("'" . $parameter . "'", $this->healthLogsFunctionsSource);
        }
    }

    public function testBudgetExhaustedReadIsReportedAsTruncatedNotEmpty(): void
    {
        $this->assertStringContainsString("'access' => 'truncated'", $this->utilitiesSource);
        $this->assertStringContainsString(
            "in_array(\$readStatus, array('limit_reached', 'gzip_unavailable'), true)",
            $this->utilitiesSource
        );
        $this->assertStringContainsString("'partial' => \$readStatus === 'limit_reached'", $this->utilitiesSource);
        $this->assertStringContainsString("result.access === 'truncated'", $this->healthJavascript);
        $this->assertStringContainsString('runtime_log_truncated_fmt', $this->healthJavascript);
        $this->assertStringContainsString("'health_runtime_log_truncated_fmt'", $this->englishLanguage);
        $this->assertStringContainsString("'health_runtime_log_truncated_fmt'", $this->frenchLanguage);
    }

    public function testLogExcerptMetadataIsSurfacedInTheUserInterface(): void
    {
        $this->assertStringContainsString('function tpRuntimeLogNotes', $this->healthJavascript);
        $this->assertStringContainsString('runtime_log_sources_fmt', $this->healthJavascript);
        $this->assertStringContainsString('server_access_log_shared_notice', $this->healthJavascript);
        $this->assertStringContainsString('runtime_log_redacted', $this->healthJavascript);
        foreach (array('server', 'server-access', 'teampass', 'php-fpm', 'websocket') as $card) {
            $this->assertStringContainsString('id="health-' . $card . '-log-meta"', $this->healthPage);
        }
        $this->assertStringContainsString("\$('#' + prefix + '-meta').hide().text('')", $this->healthJavascript);
    }

    public function testSharedAccessLogIsFlaggedAsNotInstanceScoped(): void
    {
        $this->assertStringContainsString('function tpHealthGetInstanceAccessLogPaths', $this->utilitiesSource);
        $this->assertStringContainsString('function tpHealthFlagInstanceScopedLog', $this->utilitiesSource);
        $this->assertStringContainsString("\$result['instance_scoped'] = false", $this->utilitiesSource);
        $this->assertStringContainsString("\$result['selection_source'] = 'server_fallback'", $this->utilitiesSource);
        $this->assertStringContainsString('result.instance_scoped === false', $this->healthJavascript);
        $this->assertStringContainsString("'health_server_access_log_shared_notice'", $this->englishLanguage);
        $this->assertStringContainsString("'health_server_access_log_shared_notice'", $this->frenchLanguage);
    }

    public function testManualLogOverridesAreIndependentAndCoverEveryRuntimeRole(): void
    {
        $settings = array(
            'health_webserver_log_path',
            'health_webserver_access_log_path',
            'health_teampass_log_path',
            'health_php_fpm_log_path',
        );

        foreach ($settings as $setting) {
            $this->assertStringContainsString("'" . $setting . "'", $this->adminSource);
            $this->assertStringContainsString("'" . $setting . "'", $this->utilitiesSource);
            $this->assertStringContainsString("id='" . $setting . "'", $this->optionsPage);
            $this->assertStringContainsString("$('#" . $setting . "')", $this->optionsJavascript);
            $this->assertStringContainsString("'" . $setting . "'", $this->installStep);
            $this->assertStringContainsString("'" . $setting . "'", $this->upgradeScripts);
        }

        $this->assertStringContainsString("'server' => 'health_webserver_log_path'", $this->utilitiesSource);
        $this->assertStringContainsString("'server_access' => 'health_webserver_access_log_path'", $this->utilitiesSource);
        $this->assertStringContainsString("'teampass' => 'health_teampass_log_path'", $this->utilitiesSource);
        $this->assertStringContainsString("'php_fpm' => 'health_php_fpm_log_path'", $this->utilitiesSource);
        $this->assertStringContainsString("if (\$mode === 'manual' && \$manualPath !== '')", $this->utilitiesSource);
        $this->assertStringNotContainsString("teampassSaveAdminSetting('health_webserver_log_path', '');", $this->adminSource);
    }

    public function testDeclaredAccessLogIsNotHiddenByServerWideFallback(): void
    {
        $this->assertStringContainsString('$preferDeclaredAccessLog', $this->utilitiesSource);
        $this->assertStringContainsString('Do not hide a missing or', $this->utilitiesSource);
        $this->assertStringContainsString("'selection_source'", $this->utilitiesSource);
        $this->assertStringContainsString('server_access_log_manual_notice', $this->healthJavascript);
        $this->assertStringContainsString("'health_server_access_log_manual_notice'", $this->englishLanguage);
        $this->assertStringContainsString("'health_server_access_log_manual_notice'", $this->frenchLanguage);
    }

    public function testDeclaredButUnavailableAccessLogTellsTheAdministratorWhyNothingIsShown(): void
    {
        // The break that protects the declaration is silent by itself: without
        // this notice the administrator only sees an unexplained "not found".
        $this->assertStringContainsString('server_access_log_declared_unavailable', $this->healthJavascript);
        $this->assertStringContainsString("result.selection_source === 'vhost_config'", $this->healthJavascript);
        $this->assertStringContainsString("result.access === 'not_found'", $this->healthJavascript);
        $this->assertStringContainsString("result.access === 'not_readable'", $this->healthJavascript);
        $this->assertStringContainsString("'health_server_access_log_declared_unavailable'", $this->englishLanguage);
        $this->assertStringContainsString("'health_server_access_log_declared_unavailable'", $this->frenchLanguage);
    }

    public function testServerWideScopeIsDecidedBeforeTheVhostDeclaration(): void
    {
        // Scope and provenance are two independent axes: a vhost that declares
        // the shared server-wide log must still be reported as not instance
        // scoped, otherwise the sharing warning is silently suppressed.
        $this->assertStringContainsString('$isDeclared = in_array(', $this->utilitiesSource);
        $this->assertStringContainsString('$isServerWide = in_array(', $this->utilitiesSource);

        $scopeBlock = strpos($this->utilitiesSource, '$isServerWide = in_array(');
        $this->assertNotFalse($scopeBlock);

        $serverWideScope = strpos($this->utilitiesSource, 'if ($isServerWide === true) {', $scopeBlock);
        $declaredScope = strpos($this->utilitiesSource, '} elseif ($isDeclared === true) {', $scopeBlock);
        $this->assertNotFalse($serverWideScope);
        $this->assertNotFalse($declaredScope);
        $this->assertLessThan($declaredScope, $serverWideScope);

        // Provenance keeps the declaration as the more precise answer.
        $this->assertStringContainsString("if (\$isDeclared === true) {\n        \$result['selection_source'] = 'vhost_config';", $this->utilitiesSource);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, 'Unable to read ' . $path);

        return (string) $contents;
    }

    private function switchCaseBlock(string $source, string $caseName): string
    {
        $startNeedle = "case '" . $caseName . "':";
        $start = strpos($source, $startNeedle);
        $this->assertNotFalse($start, 'Missing switch case ' . $caseName);

        $nextCandidates = array_filter(array(
            strpos($source, "\n        case '", (int) $start + strlen($startNeedle)),
            strpos($source, "\ncase '", (int) $start + strlen($startNeedle)),
        ), static function ($position): bool {
            return $position !== false;
        });
        $next = empty($nextCandidates) === true ? false : min($nextCandidates);
        if ($next === false) {
            $next = strpos($source, "\n    }\n}", (int) $start + strlen($startNeedle));
        }
        $this->assertNotFalse($next, 'Unable to delimit switch case ' . $caseName);

        return substr($source, (int) $start, (int) $next - (int) $start);
    }
}
