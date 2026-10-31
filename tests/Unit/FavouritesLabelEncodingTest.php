<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/search.functions.php';
require_once __DIR__ . '/../../app/sources/find.functions.php';

/** The favorites JSON bypasses the shared client decoder, so its labels must be plain text. */
class FavouritesLabelEncodingTest extends TestCase
{
    /** Execute the shipped payload assembly without opening a session or reading secrets. */
    public function testDisplayedAndSearchableFavoriteValuesDecodeExactlyOneLayer(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/favourites.queries.php');
        $start = strpos($source, '$userLogin =');
        self::assertNotFalse($start);
        $end = strpos($source, '// Favourites left without a readable item.', $start);
        self::assertNotFalse($end);
        $session = new class {
            /** Supply a test login for personal root fallback labels. */
            public function get(string $key): string
            {
                return 'test.user';
            }
        };
        $userId = 7;
        $rows = [[
            'id' => 1, 'label' => 'R&amp;D O&apos;Brien &quot;Ops&quot; &lt;Guide&gt;',
            'description' => '', 'login' => 'O&#039;Brien', 'url' => 'https://example.test/?a=1&amp;b=2',
            'folder_path' => 'R&amp;D / &amp;lt;literal&amp;gt;', 'id_tree' => 10,
            'tags' => 'R&amp;D &quot;Ops&quot;', 'renewal_period' => 0, 'item_timestamp' => 0,
            'perso' => 0,
        ]];
        eval(substr($source, $start, $end - $start));
        self::assertCount(1, $items);
        self::assertSame('R&D O\'Brien "Ops" <Guide>', $items[0]['label']);
        self::assertSame("O'Brien", $items[0]['login']);
        self::assertSame('https://example.test/?a=1&b=2', $items[0]['url']);
        self::assertSame('R&D / &lt;literal&gt;', $items[0]['folder']);
        self::assertSame(['R&D', '"Ops"'], $items[0]['tags']);
        foreach (['amp', 'apos', 'quot', '#039'] as $fragment) {
            self::assertStringNotContainsString($fragment, implode(' ', [$items[0]['label'], $items[0]['login'], ...$items[0]['tags']]));
        }
    }
}
