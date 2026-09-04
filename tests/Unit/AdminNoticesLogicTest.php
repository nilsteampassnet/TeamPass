<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/admin_notices_logic.php';

/**
 * Behavioural tests of the admin dashboard notices presentation logic.
 */
class AdminNoticesLogicTest extends TestCase
{
    /**
     * Builds a minimal notice.
     *
     * @param string $id          Identifier.
     * @param string $severity    Severity.
     * @param bool   $dismissible Whether the notice can be dismissed.
     *
     * @return array Notice.
     */
    private function notice(string $id, string $severity, bool $dismissible = false): array
    {
        return [
            'id' => $id,
            'severity' => $severity,
            'dismissible' => $dismissible,
        ];
    }

    /**
     * Severities rank from the most to the least urgent.
     */
    public function testSeverityRanking(): void
    {
        self::assertGreaterThan(adminNoticeSeverityRank('warning'), adminNoticeSeverityRank('danger'));
        self::assertGreaterThan(adminNoticeSeverityRank('info'), adminNoticeSeverityRank('warning'));
        self::assertGreaterThan(adminNoticeSeverityRank('unknown'), adminNoticeSeverityRank('info'));
    }

    /**
     * An unknown severity must never drive the colour of the card.
     */
    public function testUnknownSeverityRanksLast(): void
    {
        self::assertSame(0, adminNoticeSeverityRank('unknown'));
        self::assertSame(0, adminNoticeSeverityRank(''));
    }

    /**
     * The card reflects the most urgent notice it holds.
     */
    public function testMaxSeverity(): void
    {
        $notices = [
            $this->notice('a', 'info'),
            $this->notice('b', 'danger'),
            $this->notice('c', 'warning'),
        ];

        self::assertSame('danger', adminNoticesMaxSeverity($notices));
        self::assertSame('', adminNoticesMaxSeverity([]));
    }

    /**
     * Notices are ordered by severity, and notices sharing a severity keep the order
     * in which the collectors declared them.
     */
    public function testSortIsSeverityFirstAndStable(): void
    {
        $notices = [
            $this->notice('info_1', 'info'),
            $this->notice('warning_1', 'warning'),
            $this->notice('info_2', 'info'),
            $this->notice('danger_1', 'danger'),
            $this->notice('warning_2', 'warning'),
        ];

        self::assertSame(
            ['danger_1', 'warning_1', 'warning_2', 'info_1', 'info_2'],
            array_column(adminNoticesSort($notices), 'id')
        );
    }

    /**
     * Card and badge classes follow the most urgent severity.
     */
    public function testCardAndBadgeClasses(): void
    {
        self::assertSame('card-danger', adminNoticesCardClass([$this->notice('a', 'danger')]));
        self::assertSame('card-warning', adminNoticesCardClass([$this->notice('a', 'warning')]));
        self::assertSame('card-info', adminNoticesCardClass([$this->notice('a', 'info')]));
        self::assertSame('card-default', adminNoticesCardClass([]));

        self::assertSame('badge-danger', adminNoticesBadgeClass([$this->notice('a', 'danger')]));
        self::assertSame('badge-warning', adminNoticesBadgeClass([$this->notice('a', 'warning')]));
        self::assertSame('badge-info', adminNoticesBadgeClass([$this->notice('a', 'info')]));
        self::assertSame('badge-secondary', adminNoticesBadgeClass([]));
    }

    /**
     * Icon colours are derived from the severity alone.
     */
    public function testIconClasses(): void
    {
        self::assertSame('text-danger', adminNoticeIconClass('danger'));
        self::assertSame('text-warning', adminNoticeIconClass('warning'));
        self::assertSame('text-info', adminNoticeIconClass('info'));
        self::assertSame('text-muted', adminNoticeIconClass('unknown'));
    }

    /**
     * With no notice the card is not rendered at all and system health takes the full
     * width: an empty card is exactly what made a fresh installation look broken.
     */
    public function testLayoutCollapsesWhenNothingToShow(): void
    {
        self::assertSame(['health' => 'col-lg-12', 'notices' => ''], adminNoticesLayoutColumns([]));
    }

    /**
     * As soon as one notice exists, both cards share the row.
     */
    public function testLayoutKeepsBothCardsWhenNoticesExist(): void
    {
        self::assertSame(
            ['health' => 'col-lg-5', 'notices' => 'col-lg-7'],
            adminNoticesLayoutColumns([$this->notice('a', 'info')])
        );
    }
}
