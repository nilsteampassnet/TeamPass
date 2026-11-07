<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\SearchFavourites;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/search.functions.php';
require_once __DIR__ . '/../../app/sources/find.functions.php';

/** Return DB-like string IDs and observe that rendering reads bookmarks once per page. */
function getUserFavorites(int $userId): array
{
    ++SearchFavouritesTest::$reads;
    return $userId === 7 ? ['1'] : ['2'];
}

/** Exercise the shipped result renderer without installation/session bootstrap. */
class SearchFavouritesTest extends TestCase
{
    public static int $reads = 0;

    /** Render rows using the same escaped labels and action markup as the live endpoint. */
    private function render(bool $enabled = true, int $admin = 0, int $userId = 7): array
    {
        self::$reads = 0;
        $featureFavourites = $enabled;
        $featureClassification = false;
        $folderAccessLevel = [];
        $massOperationEnabled = false;
        $session = new class ($admin) {
            public function __construct(private int $admin) {}
            /** Supply only the account flag needed by the renderer. */
            public function get(string $key): int { return $this->admin; }
        };
        $lang = new class {
            /** Include quotes and markup to check contextual escaping. */
            public function get(string $key): string { return $key . ' "O\'Brien" <R&D>'; }
        };
        $rows = array_map(static fn (int $id): array => [
            'id' => $id, 'id_tree' => 10, 'renewal_period' => 0, 'timestamp' => 0,
            'perso' => 0, 'restricted_to' => '', 'label' => 'R&amp;D O&#039;Brien &quot;Ops&quot; &lt;Guide&gt;',
            'login' => 'O&#039;Brien', 'description' => '', 'tags' => '', 'url' => '', 'folder' => 'R&amp;D',
        ], [1, 2]);
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $start = strpos($source, '$classificationLabels =');
        $end = strpos($source, 'echo json_encode([', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start, $end - $start));
        return $data;
    }

    /** Inspect actual HTML attributes rather than asserting a duplicate rendering implementation. */
    private function button(string $html): ?\DOMElement
    {
        $document = new \DOMDocument();
        $document->loadHTML('<html><body>' . $html . '</body></html>');
        $button = (new \DOMXPath($document))->query('//button[@data-action="favourite"]')->item(0);
        return $button instanceof \DOMElement ? $button : null;
    }

    /** A single database read supplies the correct per-user state and accessible button labels. */
    public function testResultsRenderTheCurrentUsersFavouriteState(): void
    {
        $rows = $this->render();
        self::assertSame(1, self::$reads);
        foreach (['true', 'false'] as $index => $pressed) {
            $button = $this->button($rows[$index][7]);
            self::assertNotNull($button);
            self::assertSame($pressed, $button->getAttribute('aria-pressed'));
            self::assertSame($button->getAttribute('title'), $button->getAttribute('aria-label'));
            self::assertSame(($index === 0 ? 'unfavorite' : 'favorite') . ' "O\'Brien" <R&D>', $button->getAttribute('title'));
            self::assertSame('button', $button->getAttribute('type'));
        }
        self::assertStringContainsString('fa-solid fa-star text-warning', $rows[0][7]);
        self::assertStringContainsString('fa-regular fa-star', $rows[1][7]);
        $otherUser = $this->render(true, 0, 42);
        self::assertSame('false', $this->button($otherUser[0][7])->getAttribute('aria-pressed'));
        self::assertSame('true', $this->button($otherUser[1][7])->getAttribute('aria-pressed'));
    }

    /** A disabled feature and administrators cannot expose a usable bookmark action. */
    public function testDisabledFeatureAndAdministratorsHaveNoFavouriteButtonOrLookup(): void
    {
        foreach ([[false, 0], [true, 1]] as [$enabled, $admin]) {
            foreach ($this->render($enabled, $admin) as $row) {
                self::assertNull($this->button($row[7]));
            }
            self::assertSame(0, self::$reads);
        }
    }

    /** The extra action must preserve DataTables column indexes and displayed-name encoding. */
    public function testAddingTheButtonPreservesEscapedNamesAndColumnIndexes(): void
    {
        foreach ($this->render() as $row) {
            self::assertCount(8, $row);
            self::assertSame('R&D O\'Brien "Ops" <Guide>', html_entity_decode(strip_tags($row[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            self::assertSame("O'Brien", html_entity_decode($row[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            self::assertSame('R&amp;D', $row[6]);
            self::assertStringNotContainsString('<Guide>', $row[1]);
        }
    }
}
