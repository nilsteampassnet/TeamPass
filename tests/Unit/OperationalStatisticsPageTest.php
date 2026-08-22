<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Wiring guards for the operational statistics dashboard.
 *
 * Only checks what cannot be asserted from the DB-free logic (covered by
 * OperationalStatisticsLogicTest): that the page, its JavaScript and its translations stay
 * consistent with each other. Assertions target contracts, not source formatting.
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

    private function page(): string
    {
        return $this->source('app/pages/statistics.php');
    }

    private function javascript(): string
    {
        return $this->source('app/pages/statistics.js.php');
    }

    private function backend(): string
    {
        return $this->source('app/sources/admin.queries.php');
    }

    /**
     * Extract the body of the get_operational_statistics case, whatever case follows it.
     */
    private function statisticsCase(): string
    {
        $backend = $this->backend();
        $start = strpos($backend, "case 'get_operational_statistics':");
        self::assertIsInt($start, 'The get_operational_statistics case disappeared.');

        $next = preg_match('/^case /m', $backend, $matches, PREG_OFFSET_CAPTURE, $start + 1) === 1
            ? $matches[0][1]
            : strlen($backend);

        return substr($backend, $start, $next - $start);
    }

    /**
     * @return array<int,string>
     */
    private function domIds(string $markup): array
    {
        preg_match_all('/\bid=[\'"]([A-Za-z0-9_-]+)[\'"]/', $markup, $matches);

        return $matches[1];
    }

    // ------------------------------------------------------------ page wiring

    public function testEveryDashboardSectionIsPresent(): void
    {
        $ids = $this->domIds($this->page());

        foreach (['tp-ops-overview', 'tp-ops-security', 'tp-ops-activity', 'tp-ops-users', 'tp-ops-lapr'] as $section) {
            self::assertContains($section, $ids, 'Missing dashboard section: ' . $section);
        }
    }

    public function testThePageDeclaresNoDuplicateDomId(): void
    {
        $ids = $this->domIds($this->page());
        $duplicates = array_keys(array_filter(array_count_values($ids), static fn (int $c): bool => $c > 1));

        self::assertSame([], $duplicates, 'Duplicate DOM ids: ' . implode(', ', $duplicates));
    }

    public function testEveryJavascriptSelectorResolvesToAnElementOfThePage(): void
    {
        $ids = $this->domIds($this->page());
        preg_match_all(
            '/(?:getElementById\(\'|\$\(\'#)([A-Za-z0-9_-]+)\'/',
            $this->javascript(),
            $matches
        );

        $missing = array_values(array_unique(array_diff($matches[1], $ids)));
        self::assertSame([], $missing, 'JavaScript targets unknown ids: ' . implode(', ', $missing));
    }

    public function testCalendarPeriodsAreOfferedAndAcceptedByTheBackend(): void
    {
        $page = $this->page();
        $case = $this->statisticsCase();

        foreach (['current_week', 'current_month'] as $period) {
            self::assertMatchesRegularExpression(
                '/value=[\'"]' . $period . '[\'"]/',
                $page,
                'Period not offered in the page: ' . $period
            );
            self::assertStringContainsString(
                "'" . $period . "'",
                $case,
                'Period not accepted by the backend: ' . $period
            );
        }
    }

    // -------------------------------------------------------- backend contract

    public function testPasswordComplianceUsesTheCanonicalHealthHelpers(): void
    {
        $case = $this->statisticsCase();

        self::assertStringContainsString('securityPasswordHealthSql()', $case);
        self::assertStringContainsString('securityPostureMinPasswordLength()', $case);
        // The page must not reintroduce its own thresholds next to the canonical ones.
        self::assertStringNotContainsString('$pwMinLen = ', $case);
        self::assertStringNotContainsString('$pwMinComplexity = ', $case);
    }

    public function testServiceAccountsAreExcludedFromUserMetrics(): void
    {
        $case = $this->statisticsCase();

        foreach (['TP_USER_ID', 'OTV_USER_ID', 'API_USER_ID', 'SSH_USER_ID'] as $constant) {
            self::assertStringContainsString($constant, $case, 'Service account not excluded: ' . $constant);
        }
    }

    public function testTheRankingQueryIsBoundedAtSqlLevel(): void
    {
        $case = $this->statisticsCase();

        self::assertStringContainsString('OPS_STATS_RANKING_CANDIDATES', $case);
        self::assertStringContainsString('opsStatsBuildUserRanking(', $case);
    }

    public function testCreationSourcesAreExposedOnceFromTheItemsPayload(): void
    {
        $case = $this->statisticsCase();
        $javascript = $this->javascript();

        // Web / API / Import counts live under items.created only.
        self::assertStringContainsString("'imported' =>", $case);
        self::assertStringNotContainsString("'created_web' =>", $case);
        self::assertStringNotContainsString("'created_import' =>", $case);
        self::assertStringNotContainsString('actions.created_web', $javascript);
    }

    public function testEveryPayloadKeyRenderedByTheJavascriptIsProducedByTheBackend(): void
    {
        $case = $this->statisticsCase();
        $javascript = $this->javascript();

        foreach (['top_copied', 'rankings', 'created_complexity', 'hibp', 'lapr'] as $key) {
            self::assertStringContainsString("'" . $key . "' =>", $case, 'Payload key not produced: ' . $key);
            self::assertStringContainsString($key, $javascript, 'Payload key not consumed: ' . $key);
        }
    }

    public function testLaprDashboardKeepsSnapshotAndPeriodMetricsSeparate(): void
    {
        $case = $this->statisticsCase();
        $page = $this->page();
        $javascript = $this->javascript();

        self::assertStringContainsString('laprBuildOperationalStatistics(', $case);
        self::assertStringContainsString('tp-lapr-rotation-chart', $page);
        self::assertStringContainsString('tp-lapr-state-chart', $page);
        self::assertStringContainsString('retention_limited', $javascript);
        self::assertStringContainsString('worker_failures', $javascript);
        self::assertStringContainsString("type: 'horizontalBar'", $javascript);
    }

    public function testDisabledLaprTabRemainsVisibleAndClearsResidualMetrics(): void
    {
        $javascript = $this->javascript();

        self::assertStringContainsString("$('#tp-ops-lapr-tab').closest('li').show()", $javascript);
        self::assertStringContainsString('if (lapr.enabled === false)', $javascript);
        self::assertStringContainsString("$('#tp-lapr-content').hide()", $javascript);
        self::assertStringContainsString("tpOpsCharts[chartKey].destroy()", $javascript);
        self::assertStringNotContainsString('.toggle(hasLaprData)', $javascript);
    }

    // -------------------------------------------------------- chart.js contract

    public function testChartConfigurationTargetsTheBundledChartJsVersion(): void
    {
        $javascript = $this->javascript();

        // Chart.js 2.9.4 is bundled: v3 option names would be silently ignored.
        self::assertStringNotContainsString('plugins:', $javascript);
        self::assertStringNotContainsString("indexAxis: 'y'", $javascript);
        self::assertStringNotContainsString('scales: { y:', $javascript);
        self::assertStringNotContainsString('scales: { x:', $javascript);
        self::assertStringContainsString('xAxes:', $javascript);
        self::assertStringContainsString('yAxes:', $javascript);
    }

    public function testAjaxFailuresRestoreTheInterface(): void
    {
        $javascript = $this->javascript();

        self::assertStringContainsString('.fail(function()', $javascript);
        self::assertGreaterThanOrEqual(
            3,
            substr_count($javascript, "removeClass('fa-spin')"),
            'The refresh button must be restored on every failure path.'
        );
    }

    // ------------------------------------------------------------ translations

    public function testEveryTranslationKeyUsedByTheDashboardExistsInEnglishAndFrench(): void
    {
        /** @var array<string,string> $english */
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        /** @var array<string,string> $french */
        $french = include __DIR__ . '/../../app/includes/language/french.php';

        preg_match_all(
            '/\$lang->get\(\'([A-Za-z0-9_]+)\'\)/',
            $this->page() . $this->javascript(),
            $matches
        );
        $keys = array_unique($matches[1]);
        self::assertNotEmpty($keys);

        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $english, 'english: ' . $key);
            self::assertNotSame('', trim((string) $english[$key]), 'english: ' . $key);
            self::assertArrayHasKey($key, $french, 'french: ' . $key);
            self::assertNotSame('', trim((string) $french[$key]), 'french: ' . $key);
        }
    }

    public function testResponsiveStylesAreShipped(): void
    {
        $stylesheet = $this->source('public/assets/css/statistics.css');

        self::assertStringContainsString('.tp-ops-info-box', $stylesheet);
        self::assertMatchesRegularExpression('/@media\s*\(max-width:/', $stylesheet);
    }
}
