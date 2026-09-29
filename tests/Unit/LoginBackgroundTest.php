<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the custom login background (Settings → Options → custom_login_background).
 *
 * The wallpaper was hard-coded in public/assets/css/teampass.css, so any
 * customization was overwritten by the next upgrade. The setting takes an absolute
 * http(s) URL, like the favicon; the value lands inside a CSS url() in a style
 * attribute, so nothing that could leave it may be rendered.
 */
class LoginBackgroundTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertIsString($content, $relativePath . ' must be readable');

        return str_replace("\r\n", "\n", $content);
    }

    /**
     * Runs the production block of login.php and returns the style it builds.
     */
    private static function renderStyle(string $storedValue): string
    {
        $source = self::source('app/core/login.php');
        $start = strpos($source, '// Custom login background (Settings');
        self::assertNotFalse($start, 'The login background block must exist.');
        $end = strpos($source, "\necho '", $start);
        self::assertNotFalse($end);

        $SETTINGS = ['custom_login_background' => $storedValue];
        // Evaluates the repository's own code, never external input.
        eval(substr($source, $start, $end - $start));

        return $loginBackgroundStyle;
    }

    public function testNoSettingKeepsTheShippedWallpaper(): void
    {
        self::assertSame('', self::renderStyle(''));
    }

    public function testAbsoluteUrlBecomesTheBackground(): void
    {
        self::assertSame(
            ' style="background-image: url(\'https://intranet.example/wall.jpg\');"',
            self::renderStyle('https://intranet.example/wall.jpg')
        );
    }

    public function testUrlStoredEncodedIsEscapedOnce(): void
    {
        self::assertSame(
            ' style="background-image: url(\'https://intranet.example/wall.jpg?v=1&amp;s=2\');"',
            self::renderStyle('https://intranet.example/wall.jpg?v=1&amp;s=2')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedValues(): array
    {
        return [
            'relative path' => ['/branding/wall.jpg'],
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

    #[DataProvider('refusedValues')]
    public function testUnsafeOrNonAbsoluteValuesAreIgnored(string $value): void
    {
        self::assertSame('', self::renderStyle($value));
    }

    public function testBodyCarriesTheStyle(): void
    {
        self::assertStringContainsString(
            '<body class="hold-transition login-page \'.($theme_body ?? \'\').\'"\'.$loginBackgroundStyle.\'>',
            self::source('app/core/login.php')
        );
    }

    public function testSettingIsSeededAndLabelled(): void
    {
        self::assertStringContainsString(
            "array('admin', 'custom_login_background', ''),",
            self::source('public/install/install-steps/run.step5.php')
        );
        self::assertStringContainsString(
            "VALUES ('admin', 'custom_login_background', '')",
            self::source('public/install/upgrade_run_3.2.2.php')
        );
        self::assertStringContainsString("id='custom_login_background'", self::source('app/pages/options.php'));

        $english = self::source('app/includes/language/english.php');
        self::assertStringContainsString("'admin_misc_custom_login_background' =>", $english);
        self::assertStringContainsString("'admin_misc_custom_login_background_tip' =>", $english);
    }
}
