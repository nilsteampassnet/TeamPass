<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/find.functions.php';

/**
 * Unit tests for item-description previews returned by search handlers.
 */
class FindDescriptionPreviewTest extends TestCase
{
    public function testEncodedRichTextBecomesPlainText(): void
    {
        $description = '&lt;p&gt;&lt;b&gt;coucou&lt;/b&gt;&lt;/p&gt;'
            . '&lt;p&gt;comment ça va ici ? c&#039;est l&#039;heure de rentrer&lt;/p&gt;'
            . '&lt;p&gt;&lt;br&gt;&lt;/p&gt;';

        $this->assertSame(
            "coucou comment ça va ici ? c'est l'heure de rentrer",
            findBuildDescriptionPreview($description)
        );
    }

    public function testLegacyRawHtmlIsAlsoSupported(): void
    {
        $description = '<p>first <strong>important</strong> line</p><p>second line</p>';

        $this->assertSame(
            'first important line second line',
            findBuildDescriptionPreview($description)
        );
    }

    public function testPreviewIsTruncatedAfterHtmlNormalization(): void
    {
        $description = '&lt;p&gt;&lt;b&gt;coucou&lt;/b&gt;&lt;/p&gt;'
            . '&lt;p&gt;comment ça va ici ? c&#039;est l&#039;heure de rentrer&lt;/p&gt;';
        $preview = findBuildDescriptionPreview($description, 50);

        $this->assertSame("coucou comment ça va ici ? c'est l'heure de rentre", $preview);
        $this->assertSame(50, mb_strlen($preview));
        $this->assertStringNotContainsString('&lt;', $preview);
        $this->assertStringNotContainsString('<', $preview);
        $this->assertStringNotContainsString('&', substr($preview, -1));
    }

    public function testUnicodeCharactersAreNotSplitByTheLimit(): void
    {
        $preview = findBuildDescriptionPreview('&lt;p&gt;ééé&lt;/p&gt;', 2);

        $this->assertSame('éé', $preview);
        $this->assertSame(2, mb_strlen($preview));
    }

    public function testMarkupAndDangerousAttributesAreNotReturned(): void
    {
        $description = '&lt;p onclick=&quot;alert(1)&quot;&gt;safe&lt;/p&gt;'
            . '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;';

        $this->assertSame('safe', findBuildDescriptionPreview($description));
    }

    public function testEmptyDescriptionOrNonPositiveLimitReturnsEmptyPreview(): void
    {
        $this->assertSame('', findBuildDescriptionPreview(''));
        $this->assertSame('', findBuildDescriptionPreview('<p>text</p>', 0));
        $this->assertSame('', findBuildDescriptionPreview('<p>text</p>', -1));
    }
}
