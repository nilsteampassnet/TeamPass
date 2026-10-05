<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Guards the show/hide button of the item edit form password.
 *
 * It used to reveal the password only while held down, which made editing a
 * visible password impossible and never worked with a keyboard or a touch screen.
 * It is now a toggle whose state is remembered in the browser, masked by default.
 * Once revealed the field is a text input, so spellcheck and autocorrect must stay
 * off: some browsers send spellchecked text to a remote service.
 */
class ItemFormPasswordToggleTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertIsString($content, $relativePath . ' must be readable');

        return $content;
    }

    /**
     * Returns the items.php line holding the given id (the markup embeds PHP, so a
     * regular expression cannot rely on '>' to delimit a tag).
     */
    private static function markupLine(string $id): string
    {
        foreach (explode("\n", self::source('app/pages/items.php')) as $line) {
            if (str_contains($line, 'id="' . $id . '"')) {
                return $line;
            }
        }
        self::fail('No markup holds id="' . $id . '".');
    }

    public function testRevealedFieldIsNeverSpellchecked(): void
    {
        $input = self::markupLine('form-item-password');

        self::assertStringContainsString('<input id="form-item-password" type="password"', $input);
        self::assertStringContainsString('spellcheck="false" autocapitalize="off" autocorrect="off"', $input);
    }

    public function testButtonIsAnAccessibleToggle(): void
    {
        $button = self::markupLine('item-button-password-show');

        self::assertStringStartsWith('<button type="button"', trim($button));
        self::assertStringContainsString('aria-label="', $button);
        self::assertStringContainsString('aria-pressed="false"', $button);

        $js = self::source('app/pages/items.js.php');
        self::assertStringContainsString("\$('#item-button-password-show').on('click', function() {", $js);
        self::assertDoesNotMatchRegularExpression(
            '/#item-button-password-show\'\)\s*\.(?:mouseup|mousedown)\(/',
            $js,
            'The password must not be revealed only while the button is held down.'
        );
    }

    public function testChoiceIsRememberedAndMaskedByDefault(): void
    {
        $js = self::source('app/pages/items.js.php');

        self::assertStringContainsString("localStorage.setItem('tp_item_form_pw_visible'", $js);
        self::assertStringContainsString("visible = localStorage.getItem('tp_item_form_pw_visible') === '1'", $js);
        self::assertStringContainsString('let visible = false', $js);
    }
}
