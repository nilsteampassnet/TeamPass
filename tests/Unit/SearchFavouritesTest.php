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

/** Exercise the named renderer directly, without session or endpoint bootstrap. */
class SearchFavouritesTest extends TestCase
{
    /** Parse the actual helper output to check the browser's view of its attributes. */
    private function document(bool $isFavourite, string $label): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadHTML('<html><head><meta charset="UTF-8"></head><body>'
            . searchFavouriteActionHtml($isFavourite, $label) . '</body></html>');
        return $document;
    }

    /** A disabled feature and administrators get no star button. */
    public function testOnlyEnabledFeatureAndNonAdministratorsGetTheButton(): void
    {
        self::assertTrue(searchCanManageFavourites(true, 0));
        self::assertFalse(searchCanManageFavourites(false, 0));
        self::assertFalse(searchCanManageFavourites(true, 1));
        self::assertFalse(searchCanManageFavourites(false, 1));
    }

    /** The endpoint applies the gate to both the bookmark lookup and the button. */
    public function testEndpointGatesTheLookupAndTheButton(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        self::assertMatchesRegularExpression(
            '/\$canManageFavourites\s*=\s*searchCanManageFavourites\(\s*\$featureFavourites\s*,/',
            $source
        );
        self::assertMatchesRegularExpression(
            '/\$favouriteIds\s*=\s*\$canManageFavourites\s*\?[^;]*getUserFavorites\(\s*\$userId\s*\)[^;]*:\s*\[\]\s*;/',
            $source,
            'Bookmarks must not be read when the button is not offered.'
        );
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*\$canManageFavourites\s*\)\s*\{[^}]*searchFavouriteActionHtml\(/',
            $source,
            'The button must only be rendered behind the gate.'
        );
        self::assertSame(1, substr_count($source, 'getUserFavorites('));
        self::assertSame(1, substr_count($source, 'searchFavouriteActionHtml('));
    }

    /** Pressed state and icon distinguish adding from removing a personal bookmark. */
    public function testButtonReflectsTheCurrentFavouriteState(): void
    {
        foreach ([false, true] as $isFavourite) {
            $document = $this->document($isFavourite, $isFavourite ? 'Unfavorite' : 'Favorite');
            $buttons = $document->getElementsByTagName('button');
            self::assertCount(1, $buttons);
            $button = $buttons->item(0);
            self::assertSame('button', $button->getAttribute('type'));
            self::assertSame('search-row-action infotip', $button->getAttribute('class'));
            self::assertSame('favourite', $button->getAttribute('data-action'));
            self::assertSame($isFavourite ? 'true' : 'false', $button->getAttribute('aria-pressed'));
            self::assertSame($isFavourite ? 'Unfavorite' : 'Favorite', $button->getAttribute('title'));
            self::assertSame($button->getAttribute('title'), $button->getAttribute('aria-label'));
            $icon = $document->getElementsByTagName('i')->item(0);
            self::assertSame($isFavourite ? 'fa-solid fa-star text-warning' : 'fa-regular fa-star', $icon->getAttribute('class'));
            self::assertSame('true', $icon->getAttribute('aria-hidden'));
        }
    }

    /** Labels remain plain text, including quotes, chevrons and already-encoded entities. */
    public function testLabelsAreEscapedInsideTheRenderer(): void
    {
        foreach (['R&D O\'Brien "Ops" <Guide>', 'Ajouter aux favoris : été', 'R&amp;D &quot;Ops&quot;'] as $label) {
            foreach ([false, true] as $isFavourite) {
                $document = $this->document($isFavourite, $label);
                $button = $document->getElementsByTagName('button')->item(0);
                self::assertSame($label, $button->getAttribute('title'));
                self::assertSame($label, $button->getAttribute('aria-label'));
                self::assertCount(0, $document->getElementsByTagName('guide'));
            }
        }
    }

    /** An action label cannot create another attribute, element or executable handler. */
    public function testMarkupInALabelCannotEscapeItsAttribute(): void
    {
        $label = 'Favorite" onclick="alert(1)"><img src=x onerror=alert(1)>';
        $document = $this->document(false, $label);
        $button = $document->getElementsByTagName('button')->item(0);
        self::assertSame($label, $button->getAttribute('title'));
        self::assertSame($label, $button->getAttribute('aria-label'));
        self::assertFalse($button->hasAttribute('onclick'));
        self::assertCount(0, $document->getElementsByTagName('img'));
        self::assertCount(1, $document->getElementsByTagName('button'));
    }
}
