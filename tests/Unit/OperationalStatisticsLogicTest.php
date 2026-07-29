<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/operational_statistics_logic.php';

/**
 * Behavioural tests of the operational statistics decision logic.
 */
class OperationalStatisticsLogicTest extends TestCase
{
    private string $previousTimezone = 'UTC';

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
    }

    private function timestamp(string $localDateTime): int
    {
        return (new DateTimeImmutable($localDateTime))->getTimestamp();
    }

    // ---------------------------------------------------------------- periods

    public function testCurrentWeekStartsOnMondayMidnightLocalTime(): void
    {
        // Thursday 16 July 2026, 14:35 local time.
        $range = opsStatsResolvePeriodRange('current_week', $this->timestamp('2026-07-16 14:35:00'));

        self::assertSame($this->timestamp('2026-07-13 00:00:00'), $range['from']);
        self::assertSame('day', $range['granularity']);
    }

    public function testCurrentWeekOnASundayStillStartsOnThePrecedingMonday(): void
    {
        // Sunday is the last day of the ISO week, not the first.
        $range = opsStatsResolvePeriodRange('current_week', $this->timestamp('2026-07-19 23:59:00'));

        self::assertSame($this->timestamp('2026-07-13 00:00:00'), $range['from']);
    }

    public function testCurrentWeekOnAMondayStartsTheSameDay(): void
    {
        $range = opsStatsResolvePeriodRange('current_week', $this->timestamp('2026-07-13 09:00:00'));

        self::assertSame($this->timestamp('2026-07-13 00:00:00'), $range['from']);
    }

    public function testCurrentMonthStartsOnTheFirstDayMidnightLocalTime(): void
    {
        $range = opsStatsResolvePeriodRange('current_month', $this->timestamp('2026-07-16 14:35:00'));

        self::assertSame($this->timestamp('2026-07-01 00:00:00'), $range['from']);
        self::assertSame('day', $range['granularity']);
    }

    public function testCalendarPeriodsFollowTheConfiguredTimezone(): void
    {
        $reference = $this->timestamp('2026-07-16 14:35:00');
        $parisStart = opsStatsResolvePeriodRange('current_week', $reference)['from'];

        date_default_timezone_set('Pacific/Auckland');
        $aucklandStart = opsStatsResolvePeriodRange('current_week', $reference)['from'];

        self::assertNotSame($parisStart, $aucklandStart);
    }

    /**
     * @return array<string,array{0:string,1:int,2:string}>
     */
    public static function rollingPeriodProvider(): array
    {
        return [
            '7 days' => ['7d', 7 * 24 * 3600, 'day'],
            '30 days' => ['30d', 30 * 24 * 3600, 'day'],
            '90 days' => ['90d', 90 * 24 * 3600, 'day'],
            '24 hours' => ['24h', 24 * 3600, 'hour'],
        ];
    }

    /**
     * @dataProvider rollingPeriodProvider
     */
    public function testRollingPeriodsArePlainOffsets(string $period, int $offset, string $granularity): void
    {
        $now = $this->timestamp('2026-07-16 14:35:00');
        $range = opsStatsResolvePeriodRange($period, $now);

        self::assertSame($now - $offset, $range['from']);
        self::assertSame($granularity, $range['granularity']);
    }

    public function testAnUnknownPeriodFallsBackToTheLastTwentyFourHours(): void
    {
        $now = $this->timestamp('2026-07-16 14:35:00');
        $range = opsStatsResolvePeriodRange('last_century', $now);

        self::assertSame($now - (24 * 3600), $range['from']);
        self::assertSame('hour', $range['granularity']);
    }

    // ---------------------------------------------------------------- ranking

    /**
     * @return array<int,array<string,mixed>>
     */
    private function activityRows(): array
    {
        return [
            ['id' => 1, 'login' => 'alice', 'views' => 10, 'created' => 0, 'activity_total' => 10, 'last_activity' => 500],
            ['id' => 2, 'login' => 'bob', 'views' => 40, 'created' => 3, 'activity_total' => 43, 'last_activity' => 400],
            ['id' => 3, 'login' => 'carol', 'views' => 25, 'created' => 9, 'activity_total' => 34, 'last_activity' => 900],
            ['id' => 4, 'login' => 'dave', 'views' => 0, 'created' => 0, 'activity_total' => 0, 'last_activity' => 100],
        ];
    }

    public function testRankingOrdersByTheRequestedMetric(): void
    {
        $ranking = opsStatsBuildUserRanking($this->activityRows(), 'created', 5);

        self::assertSame(['carol', 'bob'], array_column($ranking, 'login'));
    }

    public function testRankingByAnotherMetricReordersTheSameRows(): void
    {
        $ranking = opsStatsBuildUserRanking($this->activityRows(), 'views', 5);

        self::assertSame(['bob', 'carol', 'alice'], array_column($ranking, 'login'));
    }

    public function testRankingDropsUsersWithNoActionForThatMetric(): void
    {
        $ranking = opsStatsBuildUserRanking($this->activityRows(), 'created', 5);

        self::assertNotContains('alice', array_column($ranking, 'login'));
        self::assertNotContains('dave', array_column($ranking, 'login'));
    }

    public function testRankingIsCappedByTheRequestedLimit(): void
    {
        self::assertCount(2, opsStatsBuildUserRanking($this->activityRows(), 'views', 2));
        self::assertSame([], opsStatsBuildUserRanking($this->activityRows(), 'views', 0));
    }

    public function testTiesFallBackToTheMostRecentActivityThenToTheLogin(): void
    {
        $rows = [
            ['login' => 'zoe', 'views' => 5, 'last_activity' => 100],
            ['login' => 'adam', 'views' => 5, 'last_activity' => 100],
            ['login' => 'mike', 'views' => 5, 'last_activity' => 900],
        ];

        $ranking = opsStatsBuildUserRanking($rows, 'views', 5);

        self::assertSame(['mike', 'adam', 'zoe'], array_column($ranking, 'login'));
    }

    public function testRankingIgnoresAnUnknownMetric(): void
    {
        self::assertSame([], opsStatsBuildUserRanking($this->activityRows(), 'nonexistent', 5));
    }

    // -------------------------------------------------- complexity buckets

    /**
     * PHP normalises numeric array keys to int, which is exactly how the backend map behaves.
     *
     * @return array<int,string>
     */
    private function labels(): array
    {
        return [
            -1 => 'Unknown',
            0 => 'Very weak',
            20 => 'Weak',
            38 => 'Good',
            48 => 'Strong',
            60 => 'Very strong',
        ];
    }

    public function testDistributionLabelsCarryTheirNumericLevelExceptForUnknown(): void
    {
        $formatted = opsStatsFormatComplexityDistribution(
            [
                ['complexity_level' => '38', 'c' => 4],
                ['complexity_level' => '-1', 'c' => 2],
            ],
            $this->labels()
        );

        self::assertSame(['Unknown', 'Good (38)'], $formatted['labels']);
        self::assertSame([2, 4], $formatted['counts']);
        self::assertSame([-1, 38], $formatted['values']);
    }

    public function testNullEmptyAndMinusOneLevelsAreMergedIntoASingleUnknownBucket(): void
    {
        $formatted = opsStatsFormatComplexityDistribution(
            [
                ['complexity_level' => null, 'c' => 1],
                ['complexity_level' => '', 'c' => 2],
                ['complexity_level' => '-1', 'c' => 3],
                ['complexity_level' => '60', 'c' => 5],
            ],
            $this->labels()
        );

        self::assertSame(['Unknown', 'Very strong (60)'], $formatted['labels']);
        self::assertSame([6, 5], $formatted['counts']);
    }

    public function testBucketsAreSortedNumericallyNotAlphabetically(): void
    {
        $formatted = opsStatsFormatComplexityDistribution(
            [
                ['complexity_level' => '60', 'c' => 1],
                ['complexity_level' => '0', 'c' => 1],
                ['complexity_level' => '38', 'c' => 1],
                ['complexity_level' => '20', 'c' => 1],
            ],
            $this->labels()
        );

        self::assertSame([0, 20, 38, 60], $formatted['values']);
    }

    public function testAnUnmappedLevelFallsBackToItsRawValue(): void
    {
        $formatted = opsStatsFormatComplexityDistribution(
            [['complexity_level' => '77', 'c' => 1]],
            $this->labels()
        );

        self::assertSame(['77 (77)'], $formatted['labels']);
        self::assertSame([77], $formatted['values']);
    }

    public function testAnEmptyDistributionStaysEmpty(): void
    {
        $formatted = opsStatsFormatComplexityDistribution([], $this->labels());

        self::assertSame(['labels' => [], 'counts' => [], 'values' => []], $formatted);
    }

    public function testTheRankingCandidateCapLeavesRoomForEveryTopFive(): void
    {
        self::assertGreaterThanOrEqual(100, OPS_STATS_RANKING_CANDIDATES);
    }
}
