<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/stored_text_search.php';

/** Mixed storage encodings must not change matches, counts, pages or result identity. */
class StoredTextSearchTest extends TestCase
{
    /** Each representation is matched once, while entity fragments stay invisible. */
    public function testPlainHtml5NumericAndMixedEntitiesMatchDisplayedNames(): void
    {
        foreach (["R&D O'Brien \"Ops\" <Guide>", 'R&amp;D O&apos;Brien &quot;Ops&quot; &lt;Guide&gt;',
            'R&#38;D O&#039;Brien &#34;Ops&#34; &#x3c;Guide&#x3e;'] as $label) {
            $row = ['label' => $label];
            foreach (['R&D', "O'Brien", '"Ops"', '<Guide>', 'guide'] as $term) {
                self::assertTrue(storedTextSearchMatches($row, [$term], ['label']), $term);
            }
            foreach (['amp', 'apos', 'quot', '#039', '&#x3c;'] as $term) {
                self::assertFalse(storedTextSearchMatches($row, [$term], ['label']), $term);
            }
        }
        self::assertTrue(storedTextSearchMatches(['label' => '&amp;lt;literal&amp;gt;'], ['&lt;literal&gt;'], ['label']));
        self::assertFalse(storedTextSearchMatches(['label' => '&amp;lt;literal&amp;gt;'], ['<literal>'], ['label']));
        self::assertTrue(storedTextSearchMatches(['label' => 'S&eacute;curit&eacute;'], ['SÉCURITÉ'], ['label']));
    }

    /** A long prefix of false SQL candidates must not consume any result page. */
    public function testFilteringPrecedesCountingAndPagination(): void
    {
        $rows = (static function (): Generator {
            for ($id = 1; $id <= 250; ++$id) {
                yield ['id' => $id, 'label' => 'R&amp;D'];
            }
            for ($id = 251; $id <= 312; ++$id) {
                yield ['id' => $id, 'label' => 'Ampere ' . $id];
            }
        });
        $page = storedTextSearchPage($rows(), ['amp'], ['label'], 30, 30);
        self::assertSame(62, $page['total']);
        self::assertSame(range(281, 310), array_column($page['rows'], 'id'));
        self::assertTrue($page['more']);
        $last = storedTextSearchPage($rows(), ['amp'], ['label'], 60, 30);
        self::assertSame([311, 312], array_column($last['rows'], 'id'));
        self::assertFalse($last['more']);
    }

    /** Sorting uses displayed text and keeps original encoded payloads unchanged. */
    public function testDecodedSortAndTieBreakerAreAppliedBeforeThePage(): void
    {
        $rows = [['id' => 3, 'label' => '&#90;ebra'], ['id' => 2, 'label' => 'Alpha'], ['id' => 1, 'label' => 'Alpha']];
        $compare = static fn (array $a, array $b): int => storedTextSearchCompare($a['label'], $b['label']) ?: $a['id'] <=> $b['id'];
        $page = storedTextSearchPage($rows, [], ['label'], 1, 2, $compare);
        self::assertSame([2, 3], array_column($page['rows'], 'id'));
        self::assertSame('&#90;ebra', $page['rows'][1]['label']);
        self::assertSame($rows[0]['label'], '&#90;ebra');
    }

    /** Multi-term searches AND terms but OR fields, without matching unknown columns. */
    public function testTermsCanMatchDifferentVisibleFields(): void
    {
        $row = ['label' => 'R&amp;D', 'login' => 'O&apos;Brien', 'password' => 'hidden'];
        self::assertTrue(storedTextSearchMatches($row, ['R&D', "O'Brien"], ['label', 'login']));
        self::assertFalse(storedTextSearchMatches($row, ['hidden'], ['label', 'login']));
        self::assertFalse(storedTextSearchMatches($row, ['R&D'], []));
    }

    /** Candidate SQL is a superset; all user text remains in bound parameters. */
    public function testCandidatePredicateBindsWildcardsAndKeepsEncodedRows(): void
    {
        $built = storedTextSearchCandidates(['i.label', 'i.login'], ['100%_R&D', "O'Brien"], 'kb');
        self::assertSame('%&%;%', $built['params']['kb_entity']);
        self::assertSame('100%_R&D', $built['params']['kb0']);
        self::assertStringContainsString('i.label LIKE %ss_kb0', $built['sql']);
        self::assertStringContainsString('i.label LIKE %s_kb_entity', $built['sql']);
        self::assertStringNotContainsString("O'Brien", $built['sql']);
        self::assertSame('(1 = 0)', storedTextSearchCandidates([], ['term'])['sql']);
    }

    /** Identifiers cannot be provided through request text. */
    public function testCandidatePredicateRejectsAnUnsafeColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        storedTextSearchCandidates(['label; DROP TABLE items'], ['term']);
    }

    /** The unbuffered cursor is released even when matching fails. */
    public function testCursorIsFreedAfterAnException(): void
    {
        $cursor = new class extends MeekroDBWalk {
            public bool $released = false;
            /** Avoid opening a real connection. */
            public function __construct() {}
            /** Supply a row until the fixture callback fails. */
            public function next(): array { return ['label' => 'R&amp;D']; }
            /** Record resource cleanup without a database. */
            public function free(): void { $this->released = true; }
        };
        try {
            storedTextSearchPage($cursor, ['term'], ['label'], 0, 1, null,
                ['label' => static function (): string { throw new RuntimeException('fixture'); }]);
            self::fail('The normalizer should throw');
        } catch (RuntimeException $error) {
            self::assertSame('fixture', $error->getMessage());
        }
        self::assertTrue($cursor->released);
    }
}
