<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      LaprLocalizationTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

class LaprLocalizationTest extends TestCase
{
    private const LAPR_PR_LANGUAGE_KEYS = [
        'lapr_account_paused_rotation_blocked',
        'lapr_endpoint_checks_enabled_setting',
        'lapr_endpoint_check_interval_setting',
        'lapr_check_endpoint',
        'lapr_endpoint_check_success',
        'lapr_endpoint_check_reachable_no_rights',
        'lapr_endpoint_check_already_running',
        'lapr_pause_endpoint',
        'lapr_pause_endpoint_notice',
        'lapr_pause_reason',
        'lapr_pause_reason_maintenance',
        'lapr_pause_reason_incident',
        'lapr_pause_reason_access_change',
        'lapr_pause_reason_operator',
        'lapr_resume_endpoint',
        'lapr_resume_endpoint_confirm',
        'lapr_resume_endpoint_success',
        'lapr_resume_endpoint_failed',
        'lapr_resume_due_accounts',
        'lapr_endpoint_status_paused',
        'lapr_endpoint_paused',
        'lapr_endpoint_already_paused',
        'lapr_endpoint_not_paused',
        'lapr_endpoint_paused_confirmation_required',
        'lapr_endpoint_paused_rotation_warning',
        'lapr_rotation_cancelled_endpoint_paused',
        'lapr_item_endpoint_paused',
        'lapr_rotation_failure_email_subject',
        'lapr_rotation_failure_email_body',
        'lapr_alert_state_action_required',
        'lapr_alert_state_retry_scheduled',
        'lapr_alert_state_suspended',
        'lapr_retry_scheduled_detail',
        'lapr_last_error',
        'email_tpl_lapr_rotation_failure',
        'email_tpl_lapr_rotation_failure_desc',
        'lapr_monitor_state_endpoint_paused',
        'lapr_monitor_endpoint_checks',
        'lapr_monitor_endpoint_check_interval',
        'lapr_monitor_endpoint_checks_due',
        'lapr_monitor_endpoint_checks_overdue',
        'lapr_monitor_endpoint_checks_stale',
        'lapr_monitor_endpoint_checks_pending',
        'lapr_monitor_endpoint_checks_running',
        'lapr_monitor_endpoint_checks_failed_24h',
        'lapr_monitor_endpoint_checks_oldest',
        'lapr_monitor_endpoint_checks_next',
        'lapr_monitor_endpoint_checks_disabled',
        'lapr_monitor_endpoint_checks_overdue_status',
        'lapr_monitor_issue_endpoint_paused',
        'lapr_monitor_issue_endpoint_unreachable',
        'lapr_monitor_issue_endpoint_error',
        'lapr_monitor_issue_endpoint_check_worker_failed',
        'lapr_monitor_issue_endpoint_check_queue_stalled',
        'lapr_monitor_issue_endpoint_checks_overdue',
        'ops_lapr_rotations',
        'ops_lapr_success_rate',
        'ops_lapr_rotation_trend',
    ];

    public function testAllLaprUiKeysExistInEnglishAndFrench(): void
    {
        $root = __DIR__ . '/../..';
        $source = (string) file_get_contents($root . '/app/pages/lapr_policies.php')
            . (string) file_get_contents($root . '/app/pages/lapr_policies.js.php')
            . (string) file_get_contents($root . '/app/sources/lapr_policies.queries.php')
            . (string) file_get_contents($root . '/app/pages/lapr_accounts.php')
            . (string) file_get_contents($root . '/app/pages/lapr_accounts.js.php')
            . (string) file_get_contents($root . '/app/sources/lapr_accounts.queries.php')
            . (string) file_get_contents($root . '/app/pages/lapr_endpoints.php')
            . (string) file_get_contents($root . '/app/pages/lapr_endpoints.js.php')
            . (string) file_get_contents($root . '/app/sources/lapr_endpoints.queries.php')
            . (string) file_get_contents($root . '/app/pages/admin_lapr.php')
            . (string) file_get_contents($root . '/app/pages/admin_lapr.js.php')
            . (string) file_get_contents($root . '/app/sources/lapr.functions.php');
        $english = include $root . '/app/includes/language/english.php';
        $french = include $root . '/app/includes/language/french.php';

        preg_match_all('/\$lang->get\(\x27([^\x27]+)\x27\)/', $source, $matches);
        $keys = array_values(array_unique(array_merge(
            $matches[1] ?? [],
            [
                'lapr_preset_standard',
                'lapr_preset_high_security',
                'lapr_preset_weekly_enroll',
                'lapr_policy_option_label_singular',
                'lapr_policy_option_label_plural',
            ]
        )));

        self::assertNotEmpty($keys);
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $english, 'english: ' . $key);
            self::assertArrayHasKey($key, $french, 'french: ' . $key);
            self::assertNotSame('', trim((string) $french[$key]), 'french: ' . $key);
        }
    }

    public function testLaprPrStringsExistInEveryLanguageCatalog(): void
    {
        $languageFiles = glob(__DIR__ . '/../../app/includes/language/*.php');
        self::assertIsArray($languageFiles);
        self::assertNotEmpty($languageFiles);
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        self::assertIsArray($english);

        foreach ($languageFiles as $languageFile) {
            unset($GLOBALS['LANG']);
            $catalog = include $languageFile;
            if (is_array($catalog) === false) {
                $catalog = $GLOBALS['LANG'] ?? null;
            }
            self::assertIsArray($catalog, basename($languageFile));
            foreach (self::LAPR_PR_LANGUAGE_KEYS as $key) {
                $context = basename($languageFile) . ': ' . $key;
                self::assertArrayHasKey($key, $catalog, $context);
                self::assertNotSame('', trim((string) $catalog[$key]), $context);
                if (basename($languageFile) !== 'english.php') {
                    self::assertNotSame(
                        (string) $english[$key],
                        (string) $catalog[$key],
                        $context . ' must not use the English fallback'
                    );
                }

                preg_match_all('/#[a-z_]+#|\{[a-z_]+\}/', (string) $english[$key], $englishTokens);
                preg_match_all('/#[a-z_]+#|\{[a-z_]+\}/', (string) $catalog[$key], $catalogTokens);
                sort($englishTokens[0]);
                sort($catalogTokens[0]);
                self::assertSame($englishTokens[0], $catalogTokens[0], $context . ' placeholders');

                foreach (['<br>', '<strong>', '</strong>', '<code>', '</code>', '<a href="#url#">', '</a>'] as $tag) {
                    self::assertSame(
                        substr_count((string) $english[$key], $tag),
                        substr_count((string) $catalog[$key], $tag),
                        $context . ' HTML: ' . $tag
                    );
                }
            }
        }
    }

    public function testAuditedLaprStringsAreNotHardcodedInTheUi(): void
    {
        $root = __DIR__ . '/../..';
        $policiesJs = (string) file_get_contents(__DIR__ . '/../../app/pages/lapr_policies.js.php');
        $accountsJs = (string) file_get_contents(__DIR__ . '/../../app/pages/lapr_accounts.js.php');
        $accountsQuery = (string) file_get_contents(__DIR__ . '/../../app/sources/lapr_accounts.queries.php');
        $endpointsJs = (string) file_get_contents($root . '/app/pages/lapr_endpoints.js.php');
        $endpointsPage = (string) file_get_contents($root . '/app/pages/lapr_endpoints.php');
        $endpointsQuery = (string) file_get_contents($root . '/app/sources/lapr_endpoints.queries.php');
        $itemsQuery = (string) file_get_contents($root . '/app/sources/items.queries.php');
        $laprFunctions = (string) file_get_contents($root . '/app/sources/lapr.functions.php');

        self::assertStringNotContainsString("emptyTable: 'No policies yet'", $policiesJs);
        self::assertStringNotContainsString("emptyTable: 'No managed accounts yet'", $accountsJs);
        self::assertStringNotContainsString("emptyTable: 'No endpoints yet'", $endpointsJs);
        self::assertStringNotContainsString('>read-only</span>', $policiesJs);
        self::assertStringNotContainsString(". 'd)'", $accountsQuery);
        self::assertStringNotContainsString(".text('User')", $accountsJs);
        self::assertStringNotContainsString(".text('Shell')", $accountsJs);
        self::assertStringNotContainsString("'Showing '", $accountsJs);
        self::assertStringNotContainsString("'Retry scheduled'", $accountsJs);
        self::assertStringNotContainsString('>fail</span>', $accountsJs);
        self::assertStringNotContainsString('DOMPurify.sanitize(r.result)', $accountsJs);
        self::assertStringContainsString('laprTriggerLabels[r.trigger]', $accountsJs);
        self::assertStringContainsString('laprResultLabels[r.result]', $accountsJs);
        self::assertStringNotContainsString('>no host key check</span>', $endpointsJs);
        self::assertStringNotContainsString("'# From the TeamPass server'", $endpointsJs);
        self::assertStringNotContainsString("'# Check the required commands'", $endpointsJs);
        self::assertStringContainsString('laprLang.remediationFromTeamPass', $endpointsJs);
        self::assertStringContainsString('laprLang.remediationCheckCommands', $endpointsJs);
        self::assertStringNotContainsString('aria-label="Close"', $endpointsPage);
        self::assertStringNotContainsString("'message' => 'Unknown action'", $endpointsQuery);
        self::assertStringNotContainsString("'message' => 'Invalid task'", $endpointsQuery);
        self::assertStringNotContainsString("'message' => 'Task not found'", $endpointsQuery);
        self::assertStringNotContainsString("'message' => 'Invalid endpoint'", $endpointsQuery);
        self::assertStringNotContainsString("'message' => 'Endpoint not found'", $endpointsQuery);
        self::assertStringContainsString('laprPolicyDisplayName(', $accountsQuery);
        self::assertStringContainsString('laprPolicyOptionLabel(', $accountsQuery);
        self::assertStringContainsString('p.frequency_days AS policy_frequency_days', $laprFunctions);
        self::assertStringContainsString('p.is_preset AS policy_is_preset', $laprFunctions);
        self::assertStringContainsString('laprPolicyDisplayName($storedPolicyLabel, $policyIsPreset, $lang)', $itemsQuery);
        self::assertStringContainsString('laprPolicyOptionLabel(', $itemsQuery);
        self::assertStringContainsString('laprFormatDateTimeForDisplay(', $accountsQuery);
        self::assertStringContainsString('laprFormatDateTimeForDisplay(', $endpointsQuery);
        self::assertStringContainsString('last_rotation_at_ts', $accountsJs);
        self::assertStringContainsString('next_rotation_at_ts', $accountsJs);
        self::assertStringContainsString('last_check_at_ts', $endpointsJs);
        self::assertStringContainsString("'created_at' => \$createdAt['display']", $accountsQuery);
    }

    public function testLaprTablesUseTheShippedDataTablesLanguageCatalog(): void
    {
        $root = __DIR__ . '/../..';
        $policiesJs = (string) file_get_contents($root . '/app/pages/lapr_policies.js.php');
        $accountsJs = (string) file_get_contents($root . '/app/pages/lapr_accounts.js.php');
        $endpointsJs = (string) file_get_contents($root . '/app/pages/lapr_endpoints.js.php');
        $french = include $root . '/app/includes/language/french.php';
        $dataTablesFrench = json_decode(
            (string) file_get_contents($root . '/public/includes/language/datatables.french.txt'),
            true
        );

        self::assertStringContainsString('language: laprPolDataTablesLang', $policiesJs);
        self::assertStringContainsString('language: laprAccDataTablesLang', $accountsJs);
        self::assertStringContainsString('language: laprEndpointDataTablesLang', $endpointsJs);
        // The catalog is read by the shared helper, which owns the English fallback.
        self::assertStringContainsString('teampassDataTablesLanguage(', $policiesJs);
        self::assertStringContainsString('teampassDataTablesLanguage(', $accountsJs);
        self::assertStringContainsString('teampassDataTablesLanguage(', $endpointsJs);
        $mainFunctions = (string) file_get_contents($root . '/app/sources/main.functions.php');
        self::assertStringContainsString('function teampassDataTablesLanguage(', $mainFunctions);
        self::assertStringContainsString('datatables.english.txt', $mainFunctions);
        // basename() + fixed directory and extension: a tampered language can only miss.
        self::assertStringContainsString("basename(strtolower(\$language))", $mainFunctions);
        self::assertIsArray($dataTablesFrench);
        self::assertSame('Afficher _MENU_ &eacute;l&eacute;ments', $dataTablesFrench['sLengthMenu']);
        self::assertSame(
            "Affichage de l'&eacute;lement 0 &agrave; 0 sur 0 &eacute;l&eacute;ments",
            $dataTablesFrench['sInfoEmpty']
        );
        self::assertSame('Rechercher&nbsp;:', $dataTablesFrench['sSearch']);
        self::assertSame('Pr&eacute;c&eacute;dent', $dataTablesFrench['oPaginate']['sPrevious']);
        self::assertSame('Suivant', $dataTablesFrench['oPaginate']['sNext']);
        self::assertSame('Aucun compte géré pour le moment', $french['lapr_no_accounts']);
        self::assertSame('Aucun serveur géré pour le moment', $french['lapr_no_endpoints']);
        self::assertSame('Aucune politique pour le moment', $french['lapr_no_policies']);
        self::assertSame('Hebdo avec rotation à l’ajout', $french['lapr_preset_weekly_enroll']);
    }

    public function testLaprBackgroundProcessesUseTheTeamPassTimezone(): void
    {
        $root = __DIR__ . '/../..';
        $handler = (string) file_get_contents($root . '/app/scripts/background_tasks___handler.php');
        $worker = (string) file_get_contents($root . '/app/scripts/background_tasks___worker.php');
        $timezoneInitialization = "date_default_timezone_set(\$this->settings['timezone'] ?? 'UTC');";

        self::assertStringContainsString($timezoneInitialization, $handler);
        self::assertStringContainsString($timezoneInitialization, $worker);
    }
}
