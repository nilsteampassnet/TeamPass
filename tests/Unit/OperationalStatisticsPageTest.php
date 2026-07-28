<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static regression guards for the operational statistics dashboard.
 */
class OperationalStatisticsPageTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function testDashboardKeepsTheOverviewSecurityActivityAndUsersSections(): void
    {
        $page = $this->source('app/pages/statistics.php');
        $stylesheet = $this->source('public/assets/css/statistics.css');

        self::assertStringContainsString("id='tp-ops-overview'", $page);
        self::assertStringContainsString("id='tp-ops-security'", $page);
        self::assertStringContainsString("id='tp-ops-activity'", $page);
        self::assertStringContainsString("id='tp-ops-users'", $page);
        self::assertStringContainsString("id='tp-ops-priority-list'", $page);
        self::assertStringContainsString("id='tp-items-hibp-chart'", $page);
        self::assertStringContainsString("id='tp-users-ranking-chart'", $page);
        self::assertStringContainsString('.tp-ops-info-box', $stylesheet);
        self::assertStringContainsString('@media (max-width: 575.98px)', $stylesheet);
    }

    public function testPasswordComplianceUsesTheCanonicalHealthPolicy(): void
    {
        $backend = $this->source('app/sources/admin.queries.php');
        $caseStart = strpos($backend, "case 'get_operational_statistics':");
        $caseEnd = strpos($backend, "case 'save_sending_statistics':", $caseStart);
        self::assertIsInt($caseStart);
        self::assertIsInt($caseEnd);
        $statisticsCase = substr($backend, $caseStart, $caseEnd - $caseStart);

        self::assertStringContainsString('$passwordHealthSql = securityPasswordHealthSql();', $statisticsCase);
        self::assertStringContainsString('$passwordMinLength = securityPostureMinPasswordLength();', $statisticsCase);
        self::assertStringContainsString("'basis' => 'security_posture'", $statisticsCase);
        self::assertStringContainsString("'min_complexity' => TP_PW_STRENGTH_3", $statisticsCase);
        self::assertStringContainsString(
            'array(TP_USER_ID, OTV_USER_ID, API_USER_ID, SSH_USER_ID)',
            $statisticsCase
        );
        self::assertStringNotContainsString("'basis' => 'folder_complexity'", $statisticsCase);
    }

    public function testReadableDistributionsAndAjaxFailuresRemainVisible(): void
    {
        $backend = $this->source('app/sources/admin.queries.php');
        $javascript = $this->source('app/pages/statistics.js.php');

        self::assertStringContainsString("'imported' => intval(\$createdItems['imported']", $backend);
        self::assertStringContainsString("get('ops_source_import')", $javascript);
        self::assertStringContainsString('var sourceValues = [', $javascript);
        self::assertSame(3, substr_count($javascript, "type: 'horizontalBar'"));
        self::assertStringNotContainsString("indexAxis: 'y'", $javascript);
        self::assertStringNotContainsString('plugins:', $javascript);
        self::assertStringContainsString('var complexityLabels =', $javascript);
        self::assertStringNotContainsString('$createdSeriesRows', $backend);
        self::assertStringContainsString(').fail(function()', $javascript);
    }

    public function testCalendarPeriodsAndUserRankingsAreAvailable(): void
    {
        $page = $this->source('app/pages/statistics.php');
        $backend = $this->source('app/sources/admin.queries.php');

        self::assertStringContainsString("value='current_week'", $page);
        self::assertStringContainsString("value='current_month'", $page);
        self::assertStringContainsString(
            "\$nowDate->modify('monday this week')->setTime(0, 0, 0)",
            $backend
        );
        self::assertStringContainsString(
            "\$nowDate->modify('first day of this month')->setTime(0, 0, 0)",
            $backend
        );
        self::assertStringContainsString("'rankings' => \$userRankings", $backend);
        self::assertStringContainsString("'overall' => \$buildUserRanking", $backend);
        self::assertStringContainsString("'password_actions' => \$buildUserRanking", $backend);
        self::assertStringContainsString('$topUsersLimit = 5;', $backend);
    }

    public function testDashboardTranslationsExistInEnglishAndFrench(): void
    {
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        $french = include __DIR__ . '/../../app/includes/language/french.php';
        $keys = [
            'ops_tab_overview',
            'ops_tab_security',
            'ops_tab_activity',
            'ops_tab_users',
            'ops_priority_title',
            'ops_hibp_coverage',
            'ops_kpi_created_weak',
            'ops_source_import',
            'ops_period_current_week',
            'ops_period_current_month',
            'ops_users_ranking_title',
            'ops_users_ranking_notice',
            'ops_complexity_chart_help',
            'ops_created_source_help',
        ];

        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $english, 'english: ' . $key);
            self::assertNotSame('', trim((string) $english[$key]), 'english: ' . $key);
            self::assertArrayHasKey($key, $french, 'french: ' . $key);
            self::assertNotSame('', trim((string) $french[$key]), 'french: ' . $key);
        }
    }
}
