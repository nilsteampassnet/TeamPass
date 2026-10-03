<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/branding_logic.php';

/**
 * Behavioural tests for the login branding images (custom logo and background).
 *
 * Both settings take the bare name of an image placed in public/assets/custom/, or a URL.
 * Entering the file path instead of a URL made administrators type ".../public/assets/..."
 * although public/ is the web root: the request fell through to the front controller and
 * the "image" was the HTML page itself.
 */
class BrandingLogicTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/tp-branding-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        foreach (['logo.png', 'Wall.JPG', 'shape.svg', 'script.php', 'notes.txt'] as $file) {
            file_put_contents($this->directory . '/' . $file, 'x');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testOnlyBareImageNamesAreCustomImageNames(): void
    {
        foreach (['logo.png', 'Wall.JPG', 'a.jpeg', 'b.gif', 'c.webp', 'my-logo_2.png'] as $name) {
            self::assertTrue(brandingIsCustomImageName($name), $name);
        }
        foreach (['', 'shape.svg', 'script.php', 'notes.txt', 'logo', '.logo.png', '../logo.png',
            'sub/logo.png', 'sub\\logo.png', 'logo png.png', 'https://x.example/logo.png'] as $name) {
            self::assertFalse(brandingIsCustomImageName($name), $name);
        }
    }

    public function testFileNameResolvesToTheCustomFolder(): void
    {
        self::assertSame('./assets/custom/logo.png', brandingLogoUrl('logo.png', $this->directory));
        self::assertSame('./assets/custom/logo.png', brandingLogoUrl(' logo.png ', $this->directory));
        self::assertSame('./assets/custom/Wall.JPG', brandingBackgroundUrl('Wall.JPG', $this->directory));
    }

    public function testFilesThatAreNotImagesAreNeverServedFromTheCustomFolder(): void
    {
        foreach (['shape.svg', 'script.php', 'notes.txt'] as $name) {
            self::assertSame('', brandingBackgroundUrl($name, $this->directory), $name);
            self::assertStringNotContainsString('assets/custom', brandingLogoUrl($name, $this->directory), $name);
        }
    }

    public function testEmptySettingKeepsTheShippedImages(): void
    {
        self::assertSame('', brandingLogoUrl('', $this->directory));
        self::assertSame('', brandingLogoUrl('   ', $this->directory));
        self::assertSame('', brandingBackgroundUrl('', $this->directory));
    }

    /** Secure Send only loads same-origin files from the controlled custom folder. */
    public function testSecureSendLogoRefusesRemoteAndArbitraryPaths(): void
    {
        self::assertSame('./assets/custom/logo.png', brandingSecureSendLogoUrl('logo.png', $this->directory));
        foreach (['https://cdn.example/logo.png', '/branding/logo.png', '../logo.png', 'shape.svg', 'missing.png'] as $value) {
            self::assertSame('', brandingSecureSendLogoUrl($value, $this->directory), $value);
        }
    }

    /** The public entity is plain display text with a small, deterministic storage budget. */
    public function testPublicEntityNameIsDecodedTrimmedAndBounded(): void
    {
        self::assertSame('ACME & Partners', brandingPublicEntityName('  ACME &amp; Partners  '));
        self::assertSame('<ACME>', brandingPublicEntityName('&lt;ACME&gt;'));
        self::assertSame(
            str_repeat('é', BRANDING_PUBLIC_ENTITY_MAX_LENGTH),
            brandingPublicEntityName(str_repeat('é', BRANDING_PUBLIC_ENTITY_MAX_LENGTH + 20))
        );
    }

    public function testLogoKeepsAcceptingWhatItAcceptedBefore(): void
    {
        // A value naming no image of the folder is used as entered, as before the folder existed.
        self::assertSame('missing.png', brandingLogoUrl('missing.png', $this->directory));
        self::assertSame('/branding/logo.png', brandingLogoUrl('/branding/logo.png', $this->directory));
        self::assertSame(
            'https://intranet.example/logo.png?v=1&s=2',
            brandingLogoUrl('https://intranet.example/logo.png?v=1&amp;s=2', $this->directory)
        );
    }

    public function testAbsoluteUrlBecomesTheBackground(): void
    {
        self::assertSame(
            'https://intranet.example/wall.jpg',
            brandingBackgroundUrl('https://intranet.example/wall.jpg', $this->directory)
        );
        self::assertSame(
            'https://intranet.example/wall.jpg?v=1&s=2',
            brandingBackgroundUrl('https://intranet.example/wall.jpg?v=1&amp;s=2', $this->directory)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedBackgrounds(): array
    {
        return [
            'file name absent from the folder' => ['missing.jpg'],
            'relative path' => ['/branding/wall.jpg'],
            'path instead of URL' => ['public/assets/custom/wall.jpg'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data URI' => ['data:image/png;base64,AAAA'],
            'other scheme' => ['ftp://intranet.example/wall.jpg'],
            'quote leaving url()' => ["https://x.example/a.jpg');background:url('https://evil.example/b.jpg"],
            'quote stored encoded' => ['https://x.example/a.jpg&#039;);color:red'],
            'double quote stored encoded' => ['https://x.example/a.jpg&quot; onload=&quot;alert(1)'],
            'parenthesis' => ['https://x.example/a(1).jpg'],
            'backslash' => ['https://x.example/a\\.jpg'],
            'whitespace' => ['https://x.example/a b.jpg'],
            'not a URL' => ['wallpaper'],
        ];
    }

    #[DataProvider('refusedBackgrounds')]
    public function testUnsafeOrUnusableBackgroundsAreIgnored(string $value): void
    {
        self::assertSame('', brandingBackgroundUrl($value, $this->directory));
    }
}
