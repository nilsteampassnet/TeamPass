<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel tests for the accessibility & responsive baseline (D4/D5).
 *
 * These guard the a11y affordances against accidental removal during
 * refactors of the large template files: the skip link, the ARIA labels on
 * icon-only navbar controls, the keyboard-focusable theme toggle, the OS
 * dark-mode preference, the focus-visible CSS and the login form labels.
 */
class AccessibilitySentinelTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertNotFalse($content, $relativePath . ' must be readable');

        return (string) $content;
    }

    // -------------------------------------------------------------------
    // public/index.php — navbar + landmarks
    // -------------------------------------------------------------------

    public function testIndexHasSkipLinkAndMainLandmark(): void
    {
        $index = self::source('public/index.php');

        $this->assertStringContainsString('class="tp-skip-link" href="#tp-main-content"', $index);
        $this->assertStringContainsString('id="tp-main-content" role="main"', $index);
    }

    public function testIconOnlyNavbarControlsCarryAriaLabels(): void
    {
        $index = self::source('public/index.php');

        // Push-menu (burger), control sidebar and theme toggle are icon-only:
        // without an aria-label a screen reader announces nothing useful.
        $this->assertMatchesRegularExpression('/data-widget="pushmenu"[^>]*aria-label=/', $index);
        $this->assertMatchesRegularExpression('/id="controlsidebar"[^>]*aria-label=/', $index);
    }

    public function testThemeToggleIsKeyboardFocusable(): void
    {
        $index = self::source('public/index.php');

        // The toggle must be an anchor with role=button (focusable), not a bare <i>.
        $this->assertMatchesRegularExpression(
            '/id="switch-theme"[^>]*>\s*<a[^>]*role="button"[^>]*aria-label=/s',
            $index
        );
    }

    // -------------------------------------------------------------------
    // app/core/load.js.php — theme behaviour
    // -------------------------------------------------------------------

    public function testThemeDefaultsToOsPreference(): void
    {
        $loadJs = self::source('app/core/load.js.php');

        $this->assertStringContainsString('prefers-color-scheme: dark', $loadJs);
    }

    // -------------------------------------------------------------------
    // app/core/login.php — form labels
    // -------------------------------------------------------------------

    public function testLoginFormInputsCarryAriaLabels(): void
    {
        $login = self::source('app/core/login.php');

        $this->assertMatchesRegularExpression('/id="login"[^>]*aria-label=/', $login);
        $this->assertMatchesRegularExpression('/id="pw"[^>]*aria-label=/', $login);
        $this->assertMatchesRegularExpression('/id="session_duration"[^>]*\n?[^>]*aria-label=/', $login);
    }

    // -------------------------------------------------------------------
    // public/assets/css/teampass.css — focus + responsive baseline
    // -------------------------------------------------------------------

    public function testStylesheetKeepsFocusVisibleAndSkipLinkRules(): void
    {
        $css = self::source('public/assets/css/teampass.css');

        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('.tp-skip-link', $css);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $css);
    }

    // -------------------------------------------------------------------
    // Language files — the aria strings must exist and stay non-empty
    // -------------------------------------------------------------------

    public function testAriaLangKeysExistInEnglishAndFrench(): void
    {
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        $french = include __DIR__ . '/../../app/includes/language/french.php';

        foreach (['a11y_skip_to_content', 'a11y_toggle_menu', 'a11y_toggle_theme', 'a11y_open_sidebar'] as $key) {
            $this->assertArrayHasKey($key, $english, 'english: ' . $key);
            $this->assertNotSame('', trim((string) $english[$key]), 'english: ' . $key);
            $this->assertArrayHasKey($key, $french, 'french: ' . $key);
            $this->assertNotSame('', trim((string) $french[$key]), 'french: ' . $key);
        }
    }
}
