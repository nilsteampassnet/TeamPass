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
    private string $adminSource;
    private string $healthPage;
    private string $healthJavascript;

    protected function setUp(): void
    {
        $root = __DIR__ . '/../..';
        $this->mainSource = $this->read($root . '/app/sources/main.functions.php');
        $this->backupSource = $this->read($root . '/app/sources/backup.functions.php');
        $this->utilitiesSource = $this->read($root . '/app/sources/utilities.queries.php');
        $this->adminSource = $this->read($root . '/app/sources/admin.queries.php');
        $this->healthPage = $this->read($root . '/app/pages/utilities.health.php');
        $this->healthJavascript = $this->read($root . '/app/pages/utilities.health.js.php');
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
