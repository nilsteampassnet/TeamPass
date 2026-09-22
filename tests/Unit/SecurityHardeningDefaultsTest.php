<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static wiring guards for three security defaults that have no DB-free module to test.
 *
 * - A new installation must enable the per-account lockout, and the health check must flag an
 *   instance where it is still disabled (older installations were seeded with 0).
 * - The Security posture scan must never call Have I Been Pwned when breach detection is off.
 * - Saving a new password must clear the stored breach status even when the Security posture
 *   dashboard is disabled, otherwise a replaced password keeps its "compromised" badge.
 */
class SecurityHardeningDefaultsTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return str_replace("\r\n", "\n", $source);
    }

    public function testNewInstallationEnablesThePerAccountLockout(): void
    {
        $installer = $this->source('public/install/install-steps/run.step5.php');

        self::assertStringContainsString("array('admin', 'nb_bad_authentication', '10'),", $installer);
        self::assertStringNotContainsString("array('admin', 'nb_bad_authentication', '0'),", $installer);
    }

    public function testHealthCheckFlagsADisabledAccountLockout(): void
    {
        $utilities = $this->source('app/sources/utilities.queries.php');

        // Same resolution as addFailedAuthentication(): missing or empty means the default of 10.
        self::assertStringContainsString(
            "getBruteforceIntegerSetting(\$tpSettings, 'nb_bad_authentication', 10, 0) === 0",
            $utilities
        );
        self::assertStringContainsString("'health_check_account_lockout_off'", $utilities);

        $english = $this->source('app/includes/language/english.php');
        self::assertStringContainsString("'health_check_account_lockout_off' =>", $english);
        self::assertStringContainsString("'health_check_account_lockout_off_message' =>", $english);
    }

    public function testPostureScanCallsHibpOnlyWhenBreachDetectionIsEnabled(): void
    {
        $handler = $this->source('app/sources/dashboard.queries.php');

        $gate = strpos($handler, "if ((int) (\$SETTINGS['hibp_enabled'] ?? 0) !== 1) {\n    \$post_include_hibp = 0;\n}");
        $call = strpos($handler, 'checkPasswordWithHIBP(');
        self::assertIsInt($gate);
        self::assertIsInt($call);
        self::assertLessThan($call, $gate);

        $page = $this->source('app/pages/dashboard.php');
        $pageGate = strpos($page, "if ((int) (\$SETTINGS['hibp_enabled'] ?? 0) === 1) { ?>");
        $checkbox = strpos($page, 'id="dashboard-include-hibp"');
        self::assertIsInt($pageGate);
        self::assertIsInt($checkbox);
        self::assertLessThan($checkbox, $pageGate);
    }

    public function testSavingAPasswordClearsTheBreachStatusWhateverTheDashboardSetting(): void
    {
        $functions = $this->source('app/sources/main.functions.php');
        $start = strpos($functions, 'function refreshItemHealthAfterSave(');
        self::assertIsInt($start);
        $end = strpos($functions, "\nfunction ", $start + 1);
        self::assertIsInt($end);
        $body = substr($functions, $start, $end - $start);

        $reset = strpos($body, "'hibp_status' => 0,");
        $dashboardGate = strpos($body, "\$SETTINGS['security_dashboard_enabled']");
        self::assertIsInt($reset);
        self::assertIsInt($dashboardGate);
        self::assertLessThan($dashboardGate, $reset);
    }
}
