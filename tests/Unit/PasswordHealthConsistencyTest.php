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

        self::assertStringContainsString("define('TP_SECURITY_PASSWORD_MIN_LENGTH', 12);", $config);
        self::assertStringContainsString('function securityPasswordHealthStatus(', $functions);
        self::assertStringContainsString('function securityPasswordHealthSql(', $functions);
        self::assertStringContainsString("return 'unassessed';", $functions);
        self::assertStringContainsString('TP_SECURITY_PASSWORD_MIN_LENGTH', $functions);
        self::assertStringContainsString('.pw_len', $functions);
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
