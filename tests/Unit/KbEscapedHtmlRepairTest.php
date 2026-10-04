<?php

declare(strict_types=1);

namespace TeamPass\Tests\KbEscapedHtmlRepair;

use PHPUnit\Framework\TestCase;

/** The legacy escaped-HTML repair must never decode a genuine code sample. */
final class KbEscapedHtmlRepairTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (function_exists(__NAMESPACE__ . '\\kbDecodeEscapedRichHtml')) {
            return;
        }
        // kb.queries.php is a request handler: load only the three pure functions under test.
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/kb.queries.php'));
        foreach (['kbRichTextLooksLikeHtml', 'kbEscapedRichTextLooksLikeHtml', 'kbDecodeEscapedRichHtml'] as $name) {
            $start = strpos($source, 'function ' . $name . '(');
            self::assertNotFalse($start, 'Missing ' . $name . '() in kb.queries.php');
            $end = strpos($source, "\n}", $start) + 2;
            eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start, $end - $start));
        }
    }

    public function testLegacyEscapedRowIsRepaired(): void
    {
        self::assertSame('<p>Hello <strong>team</strong></p>',
            kbDecodeEscapedRichHtml('&lt;p&gt;Hello &lt;strong&gt;team&lt;/strong&gt;&lt;/p&gt;'));
    }

    public function testLegacyRowWrappedByTheEditorIsRepaired(): void
    {
        self::assertSame("<p>Hello</p>\n<ul><li>x</li></ul>",
            kbDecodeEscapedRichHtml('<p>&lt;p&gt;Hello&lt;/p&gt;<br>&lt;ul&gt;&lt;li&gt;x&lt;/li&gt;&lt;/ul&gt;</p>'));
    }

    public function testCodeSamplesStayLiteral(): void
    {
        $block = "<p>Add:</p>\n<pre><code>&lt;div class=\"a\"&gt;Hi&lt;/div&gt;</code></pre>";
        $inline = '<ul><li>Use <code>&lt;br&gt;</code></li></ul>';
        self::assertSame($block, kbDecodeEscapedRichHtml($block));
        self::assertSame($inline, kbDecodeEscapedRichHtml($inline));
    }
}
