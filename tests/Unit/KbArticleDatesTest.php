<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/kb_dates.php';

/** Article dates describe recorded editorial changes, independently of the audit journal. */
final class KbArticleDatesTest extends TestCase
{
    /** Missing, malformed and inverted dates never turn into an epoch date or a false edit. */
    public function testUnknownAndSameSecondDates(): void
    {
        foreach ([null, '', 0, -1, 'invalid', [], true, 1.5] as $invalid) {
            self::assertNull(kbDateTimestamp($invalid));
        }
        self::assertSame(['created_at' => null, 'updated_at' => null], kbArticleDates([]));
        self::assertSame(['created_at' => 100, 'updated_at' => null],
            kbArticleDates(['created_at' => '100', 'updated_at' => 99]));
        self::assertSame(['created_at' => 100, 'updated_at' => 100],
            kbArticleDates(['created_at' => '100', 'updated_at' => 100]));
        self::assertSame(['created_at' => null, 'updated_at' => 200],
            kbArticleDates(['updated_at' => 200]));
    }

    /** The instance formats and timezone apply to compact dates and full timestamps. */
    public function testConfiguredDateLabels(): void
    {
        $previousZone = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Paris');
            $timestamp = strtotime('2026-10-16T22:30:00Z');
            $dates = kbArticleDatePayload(['created_at' => $timestamp],
                ['date_format' => 'd/m/Y', 'time_format' => 'H:i:s']);
            self::assertSame('17/10/2026', $dates['created_date_label']);
            self::assertSame('17/10/2026 00:30:00', $dates['created_at_label']);
            self::assertSame($dates['created_at_label'], kbFormatTimestamp($timestamp,
                ['date_format' => 'd/m/Y', 'time_format' => 'H:i:s']));
            self::assertSame('2026-10-16T22:30:00Z', $dates['created_at_iso']);
            self::assertSame('', $dates['updated_at_label']);
            self::assertSame('', $dates['updated_at_iso']);
            date_default_timezone_set('UTC');
            self::assertSame('2026-10-16', kbArticleDatePayload(['updated_at' => $timestamp], [])['updated_date_label']);
        } finally {
            date_default_timezone_set($previousZone);
        }
    }

    /** The shared formatter preserves defaults, 12-hour clocks and date-only settings. */
    public function testSharedTimestampFormatter(): void
    {
        $previousZone = date_default_timezone_get();
        try {
            date_default_timezone_set('UTC');
            $timestamp = strtotime('2026-10-16T22:30:00Z');
            self::assertSame('2026-10-16 22:30', kbFormatTimestamp($timestamp, []));
            self::assertSame('10/16/2026 10:30 PM', kbFormatTimestamp($timestamp,
                ['date_format' => 'm/d/Y', 'time_format' => 'g:i A']));
            self::assertSame('16.10.2026', kbFormatTimestamp($timestamp,
                ['date_format' => 'd.m.Y', 'time_format' => '']));
        } finally {
            date_default_timezone_set($previousZone);
        }
    }

    /** Item ordering and unchanged settings are no-ops; every actual field change is detected. */
    public function testActualArticleChanges(): void
    {
        $before = ['label' => 'R&amp;D O&#039;Brien &quot;x&quot; &lt;y&gt;', 'category' => 'Network',
            'description' => '<p>Body</p>', 'anyone_can_modify' => '0', 'allow_comments' => '1'];
        self::assertSame([], kbArticleChangedFields($before, $before, [2, 1], [1, 2]));
        foreach (array_keys($before) as $field) {
            $after = $before;
            $after[$field] = is_numeric($before[$field]) ? 1 - (int) $before[$field] : 'Changed';
            self::assertSame([$field], kbArticleChangedFields($before, $after, [1], [1]));
        }
        self::assertSame(['associated_items'], kbArticleChangedFields($before, $before, [1], [1, 2]));
    }

    /** Retained logs recover attributable dates without treating comments or no-op saves as edits. */
    public function testConservativeHistoryRecovery(): void
    {
        $event = static fn (int $id, string $action, int $date, string $reason = ''): array => [
            'valeur' => json_encode(['kb_id' => $id, 'action' => $action, 'date' => $date, 'reason' => $reason]),
            'created_at' => $date,
        ];
        $dates = kbArticleDatesFromLogs([
            $event(1, 'at_creation', 100),
            $event(1, 'at_modification', 200, 'label, description'),
            $event(1, 'at_modification', 210, 'associated_items'),
            $event(1, 'at_modification', 220, 'comment_add'),
            $event(1, 'at_modification', 230, 'comment_delete'),
            $event(1, 'at_modification', 240),
            $event(1, 'at_shown', 250),
            $event(1, 'at_restored', 260),
            $event(2, 'at_modification', 200, 'attachments_upload'),
            $event(2, 'at_modification', 300, 'attachments_delete'),
            $event(3, 'at_creation', 100),
            $event(3, 'at_modification', 110, 'description'),
            $event(3, 'at_creation', 400),
            $event(4, 'at_creation', 500),
            $event(4, 'at_modification', 500, 'category'),
            ['valeur' => '{invalid'],
            ['valeur' => json_encode(['label' => 'No article id', 'date' => 500, 'action' => 'at_creation'])],
            $event(5, 'at_modification', -1, 'description'),
            $event(6, 'at_modification', 500, 'description, unknown'),
        ]);
        self::assertSame(['created_at' => 100, 'updated_at' => 210], $dates[1]);
        self::assertSame(['created_at' => null, 'updated_at' => 300], $dates[2]);
        self::assertSame(['created_at' => 400, 'updated_at' => null], $dates[3]);
        self::assertSame(['created_at' => 500, 'updated_at' => 500], $dates[4]);
        self::assertArrayNotHasKey(5, $dates);
        self::assertArrayNotHasKey(6, $dates);
        self::assertSame([], kbArticleDatesFromLogs([]));
    }
}
