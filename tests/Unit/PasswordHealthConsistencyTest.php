<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static regression guards for password-health consistency, authorization, and presentation.
 */
class PasswordHealthConsistencyTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function testMinimumLengthAndCanonicalClassifiersAreDefinedOnce(): void
    {
        $config = $this->source('app/config/include.php');
        $functions = $this->source('app/sources/main.functions.php');
        $logic = $this->source('app/sources/security_posture_logic.php');

        // Default, used whenever the admin setting is absent (every upgraded instance).
        self::assertStringContainsString("define('TP_SECURITY_PASSWORD_MIN_LENGTH', 12);", $config);
        self::assertStringContainsString('function securityPasswordHealthStatus(', $functions);
        self::assertStringContainsString('function securityPasswordHealthSql(', $functions);
        self::assertStringContainsString('TP_SECURITY_PASSWORD_MIN_LENGTH', $functions);
        self::assertStringContainsString('.pw_len', $functions);

        // The four states are decided in one DB-free place only.
        self::assertStringContainsString("return 'unassessed';", $logic);
        self::assertStringContainsString("return 'empty';", $logic);
        self::assertStringContainsString("return 'weak';", $logic);
        self::assertStringContainsString("return 'healthy';", $logic);
    }

    public function testMinimumLengthIsAdminConfigurableWithoutAnUpgradeScript(): void
    {
        $functions = $this->source('app/sources/main.functions.php');
        $options = $this->source('app/pages/options.php');
        $installer = $this->source('public/install/install-steps/run.step5.php');
        $english = $this->source('app/includes/language/english.php');
        $french = $this->source('app/includes/language/french.php');

        self::assertStringContainsString('function securityPostureMinPasswordLength(', $functions);
        self::assertStringContainsString(
            "\$settings['security_dashboard_min_password_length']",
            $functions
        );
        // Absent row must fall back to the constant — no upgrade script needed, save_option_change
        // upserts the row the first time it is changed.
        self::assertStringContainsString(
            '$configured > 0 ? $configured : (int) TP_SECURITY_PASSWORD_MIN_LENGTH',
            $functions
        );
        self::assertStringContainsString("id='security_dashboard_min_password_length'", $options);
        self::assertStringContainsString("'security_dashboard_min_password_length', '12'", $installer);
        self::assertStringContainsString('settings_security_dashboard_min_password_length', $english);
        self::assertStringContainsString('settings_security_dashboard_min_password_length', $french);
    }

    public function testItemCardAssessesFromThePlaintextItAlreadyHolds(): void
    {
        $items = $this->source('app/sources/items.queries.php');
        $start = strpos($items, "\$arrData['pw_length'] = \$pwLength;");
        self::assertIsInt($start);
        $cardSource = substr($items, $start, 2500);

        // The card is the only health surface holding the decrypted password: it must classify
        // from the live value and repair the stored metadata, not report "unassessed" on data it
        // can assess for free.
        self::assertStringContainsString('$assessedPasswordLength = $pwLength;', $cardSource);
        self::assertStringContainsString('$metadataUpdates[\'pw_len\'] = $pwLength;', $cardSource);
        self::assertStringContainsString('$metadataUpdates[\'complexity_level\']', $cardSource);
        self::assertStringContainsString(
            "DB::update(prefixTable('items'), \$metadataUpdates, 'id = %i', (int) \$inputData['id']);",
            $cardSource
        );
    }

    public function testDashboardCardAndReportsUseTheCanonicalClassification(): void
    {
        $dashboard = $this->source('app/sources/dashboard.queries.php');
        $items = $this->source('app/sources/items.queries.php');
        $reports = $this->source('app/sources/reports.queries.php');

        self::assertStringContainsString('$passwordHealthSql = securityPasswordHealthSql();', $dashboard);
        self::assertStringContainsString('flag_unassessed', $dashboard);
        self::assertStringContainsString('securityPasswordHealthStatus(', $items);
        self::assertStringContainsString("'unassessed' => \$passwordHealthStatus === 'unassessed' ? 1 : 0", $items);
        self::assertStringContainsString('$passwordHealthSql = securityPasswordHealthSql();', $reports);
        self::assertStringNotContainsString('$pwLength < 12', $items);
    }

    public function testDeepScanRepairsLegacyPasswordMetadata(): void
    {
        $dashboard = $this->source('app/sources/dashboard.queries.php');
        $backgroundTask = $this->source('app/scripts/background_tasks___do_calculation.php');

        self::assertStringContainsString('i.pw_len, i.complexity_level', $dashboard);
        self::assertStringContainsString('$actualPasswordLength = strlen($plaintext);', $dashboard);
        self::assertStringContainsString('new \ZxcvbnPhp\Zxcvbn()', $dashboard);
        self::assertStringContainsString("\$metadataUpdates['pw_len']", $dashboard);
        self::assertStringContainsString("\$metadataUpdates['complexity_level']", $dashboard);
        self::assertStringContainsString(
            "DB::update(prefixTable('items'), \$metadataUpdates, 'id = %i', \$itemId);",
            $dashboard
        );
        self::assertStringContainsString('decryptUserObjectKeyWithMigration(', $backgroundTask);
        self::assertStringContainsString('i.complexity_level = ""', $backgroundTask);
    }

    public function testItemsListRequestsEveryBadgeChunkAndIgnoresStaleResponses(): void
    {
        $itemsJs = $this->source('app/pages/items.js.php');

        self::assertStringContainsString('let healthBadgeRequestGeneration = 0', $itemsJs);
        self::assertStringContainsString('offset += 500', $itemsJs);
        self::assertStringContainsString('Promise.all(requests)', $itemsJs);
        self::assertStringContainsString('requestGeneration !== healthBadgeRequestGeneration', $itemsJs);
        self::assertStringContainsString('.tp-item-health-marker', $itemsJs);
    }

    public function testPasswordHealthActionsAndMarkersKeepReadableContrast(): void
    {
        $nudges = $this->source('app/core/security-nudges.js.php');
        $stylesheet = $this->source('public/assets/css/teampass.css');

        self::assertStringContainsString('btn btn-sm btn-light text-dark ml-1', $nudges);
        self::assertStringContainsString(
            '.list-item-row.bg-yellow .tp-item-health-marker.text-warning',
            $stylesheet
        );
        self::assertStringContainsString(
            '.list-item-row.bg-yellow .tp-item-health-marker.text-secondary',
            $stylesheet
        );
        self::assertStringContainsString(
            '.list-item-row.bg-yellow .list-item-description.bg-black .tp-item-health-marker.text-warning',
            $stylesheet
        );
        self::assertStringContainsString(
            '.list-item-row.bg-yellow .list-item-description.bg-black .tp-item-health-marker.text-secondary',
            $stylesheet
        );
    }

    public function testHibpAuthorizationIsCheckedBeforeSharekeyDecryption(): void
    {
        $items = $this->source('app/sources/items.queries.php');
        $caseStart = strpos($items, "case 'check_hibp_password':");
        self::assertIsInt($caseStart);
        $caseSource = substr($items, $caseStart);

        $authorizationPosition = strpos($caseSource, 'securityPostureItemAccessSql(');
        $decryptionPosition = strpos($caseSource, 'decryptUserObjectKeyWithMigration(');
        self::assertIsInt($authorizationPosition);
        self::assertIsInt($decryptionPosition);
        self::assertLessThan($decryptionPosition, $authorizationPosition);
        self::assertStringContainsString('AND i.inactif = 0', $caseSource);
        self::assertStringContainsString('AND i.deleted_at IS NULL', $caseSource);
    }
}
