<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/otv_render_logic.php';

/**
 * Rendering contract of the One-Time View page (issue #5350).
 *
 * Item fields reach the database entity-encoded — the rich-text description is escaped
 * client-side by purifyData()/escapeHtmlString(), label and login go through
 * FILTER_SANITIZE_FULL_SPECIAL_CHARS. The OTV page was the only consumer that did not
 * decode them, so the recipient read "<p>text</p>" and "&#039;" as literal text.
 *
 * The second half of the contract is what replaced strip_tags(): the page is served
 * unauthenticated, so the description must be sanitized, not merely tag-filtered.
 */
class OtvRenderLogicTest extends TestCase
{
    public function testStoredMarkupIsRenderedAsHtml(): void
    {
        $this->assertSame(
            '<p>testing content, one liner</p>',
            otvSanitizeDescription('&lt;p&gt;testing content, one liner&lt;/p&gt;')
        );
    }

    public function testFormattingSurvivesTheRoundTrip(): void
    {
        $description = otvSanitizeDescription(
            '&lt;p&gt;line1&lt;/p&gt;&lt;p&gt;&lt;b&gt;bold&lt;/b&gt; and &lt;i&gt;italic&lt;/i&gt;&lt;/p&gt;'
        );

        $this->assertStringContainsString('<b>bold</b>', $description);
        $this->assertStringContainsString('<i>italic</i>', $description);
        $this->assertStringContainsString('<p>line1</p>', $description);
    }

    /**
     * Records written before the client started escaping the editor output hold raw
     * markup. They must render, not be dropped the way strip_tags() dropped <p>.
     */
    public function testLegacyRawHtmlStillRenders(): void
    {
        $this->assertSame(
            '<p>legacy raw html</p>',
            otvSanitizeDescription('<p>legacy raw html</p>')
        );
    }

    public function testMultiEncodedRecordIsFullyDecoded(): void
    {
        $this->assertSame(
            '<p>double encoded</p>',
            otvSanitizeDescription('&amp;lt;p&amp;gt;double encoded&amp;lt;/p&amp;gt;')
        );
    }

    public function testEventHandlerIsRemoved(): void
    {
        $description = otvSanitizeDescription('&lt;img src=x onerror=alert(1)&gt;');

        $this->assertStringNotContainsString('onerror', $description);
    }

    public function testDangerousSchemeIsRemoved(): void
    {
        $description = otvSanitizeDescription('&lt;a href="javascript:alert(1)"&gt;click&lt;/a&gt;');

        $this->assertStringNotContainsString('javascript:', $description);
        $this->assertStringContainsString('click', $description);
    }

    public function testScriptIsRemovedEntirely(): void
    {
        $this->assertSame('', otvSanitizeDescription('&lt;script&gt;alert(1)&lt;/script&gt;'));
    }

    /**
     * What the editor leaves behind when the user clears the field.
     */
    /**
     * API clients (browser extension, mobile application) store the text of a textarea:
     * real line breaks and no markup. Rendered as HTML they collapsed into spaces.
     */
    public function testPlainTextLineBreaksAreKept(): void
    {
        $render = static fn (string $stored): string => str_replace(["\r", "\n"], '', otvSanitizeDescription($stored));
        $this->assertSame('Test TOTP<br />Ajout 1<br />New password<br /><br />End',
            $render("Test TOTP\nAjout 1\r\nNew password\r\rEnd"));
        // Entity-encoded storage is decoded first.
        $this->assertSame('a<br />b', $render('a&#10;b'));
        // The text is escaped, never parsed: "<3" and ampersands stay text.
        $this->assertSame('I &lt;3 R&amp;D<br />"quoted"', $render("I <3 R&D\n\"quoted\""));
    }

    /** Editor markup keeps its own layout: its source line breaks are not doubled. */
    public function testMarkupLineBreaksAreNotConverted(): void
    {
        $this->assertSame("<p>a</p>\n<p>b</p>", otvSanitizeDescription("&lt;p&gt;a&lt;/p&gt;\n&lt;p&gt;b&lt;/p&gt;"));
        $this->assertSame('one line', otvSanitizeDescription('one line'));
        // Text holding markup takes the rich-text path: purified, its handlers dropped.
        $this->assertStringNotContainsString('onerror', otvSanitizeDescription("x\n<img src=x onerror=alert(1)>"));
    }

    /**
     * A literal backslash-n (two characters, found in legacy imports) is data, not a line
     * break: converting it would cut "DOMAIN\nicolas" or "C:\new". The web card shows it too.
     */
    public function testLiteralBackslashNIsLeftUntouched(): void
    {
        $this->assertSame('Test TOTP\nAjout 1', otvSanitizeDescription('Test TOTP\nAjout 1'));
    }

    public function testEmptyEditorContentProducesNothing(): void
    {
        $this->assertSame('', otvSanitizeDescription('&lt;p&gt;&lt;br&gt;&lt;/p&gt;'));
        $this->assertSame('', otvSanitizeDescription(''));
    }

    public function testImageOnlyDescriptionIsKept(): void
    {
        $this->assertStringContainsString(
            '<img',
            otvSanitizeDescription('&lt;img src="https://example.com/a.png" alt="a"&gt;')
        );
    }

    /**
     * Decoding then escaping is the exact inverse of the storage encoding, so the
     * recipient reads what the author typed instead of "O&#039;Brien &amp; Co".
     */
    public function testPlainFieldRoundTripsTheStorageEncoding(): void
    {
        $this->assertSame(
            'O&#039;Brien &amp; Co &lt;test&gt;',
            otvRenderPlainField('O&#039;Brien &amp; Co &lt;test&gt;')
        );
    }

    public function testPlainFieldEscapesLegacyRawMarkup(): void
    {
        $this->assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            otvRenderPlainField('<img src=x onerror=alert(1)>')
        );
    }

    /**
     * The decoding loop is bounded, so a crafted multi-encoded record cannot turn it
     * into a denial of service. Six encoding levels are only unwound five times.
     */
    public function testDecodingIsBounded(): void
    {
        $value = '<b>';
        for ($i = 0; $i < 6; ++$i) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        $this->assertSame('&lt;b&gt;', otvDecodeStoredValue($value));
    }
}
