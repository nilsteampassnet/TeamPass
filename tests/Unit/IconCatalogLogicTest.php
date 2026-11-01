<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/icon_catalog_logic.php';

/**
 * Catalogue of the icon picker (item and folder icons).
 *
 * The picker can only offer what the shipped Font Awesome Free renders: the rows keep the free
 * styles alone, and the suggestions must survive a Font Awesome upgrade that renames an icon,
 * which the last test checks against the metadata actually shipped.
 */
class IconCatalogLogicTest extends TestCase
{
    private const SHIPPED_METADATA = __DIR__ . '/../../public/plugins/fontawesome-free/metadata/icons.json';

    public function testRowsListFreeStylesInPickerOrder(): void
    {
        $rows = iconCatalogFromMetadata([
            'envelope' => ['free' => ['regular', 'solid'], 'search' => ['terms' => []]],
            'github' => ['free' => ['brands'], 'search' => ['terms' => []]],
        ]);

        $this->assertSame([['envelope', 'sr', ''], ['github', 'b', '']], $rows);
    }

    public function testIconsWithoutFreeStyleAreDropped(): void
    {
        $rows = iconCatalogFromMetadata([
            'pro-only' => ['free' => [], 'styles' => ['solid']],
            'no-free-key' => ['styles' => ['solid']],
            'key' => ['free' => ['solid']],
        ]);

        $this->assertSame([['key', 's', '']], $rows);
    }

    public function testTermsLeaveOutNameWordsAndDuplicates(): void
    {
        $rows = iconCatalogFromMetadata([
            'credit-card' => [
                'free' => ['solid'],
                'search' => ['terms' => ['Credit', 'card', 'credit-card', 'buy', 'BUY', ' payment ', '']],
            ],
        ]);

        $this->assertSame([['credit-card', 's', 'buy payment']], $rows);
    }

    public function testNamesThatCouldBreakAClassAttributeAreDropped(): void
    {
        $rows = iconCatalogFromMetadata([
            'key" onmouseover="x' => ['free' => ['solid']],
            'Key' => ['free' => ['solid']],
            'lock' => ['free' => ['solid']],
        ]);

        $this->assertSame([['lock', 's', '']], $rows);
    }

    public function testLoadReturnsNullWithoutMetadataFile(): void
    {
        $this->assertNull(iconCatalogLoad(__DIR__ . '/missing-icons.json'));
    }

    public function testSuggestedIconsExistInShippedFontAwesome(): void
    {
        $catalog = iconCatalogLoad(self::SHIPPED_METADATA);
        $this->assertIsArray($catalog);

        $available = [];
        $styleClasses = ['s' => 'fa-solid', 'r' => 'fa-regular', 'b' => 'fa-brands'];
        foreach ($catalog['icons'] as [$name, $styles]) {
            foreach (str_split($styles) as $code) {
                $available[$styleClasses[$code] . ' fa-' . $name] = true;
            }
        }

        $this->assertSame(['item', 'folder'], array_keys($catalog['suggested']));
        foreach ($catalog['suggested'] as $kind => $suggested) {
            foreach ($suggested as $classes) {
                $this->assertArrayHasKey($classes, $available, $kind . ': ' . $classes . ' is not a Font Awesome Free icon any more');
            }
        }
    }
}
