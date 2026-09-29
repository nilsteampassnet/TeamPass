<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Guards the custom logo of the login page (Settings → Options → custom_logo).
 *
 * - The logo was capped by an inline max-width of 100px, sized for the default
 *   100px logo: any custom logo rendered tiny whatever its format.
 * - The value is stored HTML-encoded by save_option_change and was echoed as is.
 *   It is now decoded then escaped, so it is escaped exactly once whatever the
 *   way it was written.
 */
class LoginCustomLogoTest extends TestCase
{
    private static function loginSource(): string
    {
        $source = file_get_contents(__DIR__ . '/../../app/core/login.php');
        self::assertIsString($source);

        return $source;
    }

    /**
     * Renders the src attribute value exactly as login.php builds it.
     */
    private static function renderSrc(string $customLogo): string
    {
        $source = self::loginSource();
        $start = strpos($source, "'<img src=\"' . ");
        self::assertNotFalse($start, 'The custom logo markup must exist.');
        $start += strlen("'<img src=\"' . ");
        $end = strpos($source, " . '\" alt=\"\"", $start);
        self::assertNotFalse($end);
        $expression = substr($source, $start, $end - $start);

        $SETTINGS = ['custom_logo' => $customLogo];

        // Evaluates the repository's own expression, never external input.
        return eval('return ' . $expression . ';');
    }

    public function testUrlStoredEncodedIsEscapedOnce(): void
    {
        self::assertSame(
            'https://intranet/logo.png?v=1&amp;size=2',
            self::renderSrc('https://intranet/logo.png?v=1&amp;size=2')
        );
        self::assertSame(
            'https://intranet/logo.png?v=1&amp;size=2',
            self::renderSrc('https://intranet/logo.png?v=1&size=2')
        );
    }

    public function testQuotesCannotLeaveTheAttribute(): void
    {
        foreach (['x" onerror="alert(1)', 'x&quot; onerror=&quot;alert(1)'] as $value) {
            self::assertSame('x&quot; onerror=&quot;alert(1)', self::renderSrc($value));
        }
    }

    public function testLogoIsNoLongerCappedAtTheDefaultLogoWidth(): void
    {
        $source = self::loginSource();

        self::assertStringNotContainsString('max-width:100px', $source);
        self::assertStringContainsString('style="max-width:100%; max-height:150px;"', $source);
    }
}
