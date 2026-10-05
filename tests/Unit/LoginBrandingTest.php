<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/branding_logic.php';

/**
 * Guards the wiring of the login branding images (custom logo and background).
 *
 * - The logo was capped by an inline max-width of 100px, sized for the default logo.
 * - The background was hard-coded in teampass.css, so upgrades overwrote any custom one.
 * - Both settings now take the name of an image of public/assets/custom/ or a URL; the
 *   resolution rules are covered by BrandingLogicTest, the page output is checked here.
 */
class LoginBrandingTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertIsString($content, $relativePath . ' must be readable');

        return str_replace("\r\n", "\n", $content);
    }

    /**
     * Runs the branding block of login.php against a TeamPass root holding one custom image.
     *
     * @return array<string, mixed> Variables defined by the block
     */
    private static function runBrandingBlock(array $SETTINGS): array
    {
        $root = sys_get_temp_dir() . '/tp-login-branding-' . bin2hex(random_bytes(6));
        mkdir($root . '/public/assets/custom', 0777, true);
        file_put_contents($root . '/public/assets/custom/wall.jpg', 'x');

        $source = self::source('app/core/login.php');
        $start = strpos($source, '// Custom logo and background (Settings');
        self::assertNotFalse($start, 'The login branding block must exist.');
        $end = strpos($source, "\necho '", $start);
        self::assertNotFalse($end);
        $block = str_replace('TEAMPASS_ROOT', var_export($root, true), substr($source, $start, $end - $start));

        try {
            // Evaluates the repository's own code, never external input.
            eval($block);
        } finally {
            unlink($root . '/public/assets/custom/wall.jpg');
            rmdir($root . '/public/assets/custom');
            rmdir($root . '/public/assets');
            rmdir($root . '/public');
            rmdir($root);
        }

        return get_defined_vars();
    }

    public function testBackgroundFileNameBecomesTheBodyStyle(): void
    {
        $vars = self::runBrandingBlock(['custom_login_background' => 'wall.jpg']);

        self::assertSame(' style="background-image: url(\'./assets/custom/wall.jpg\');"', $vars['loginBackgroundStyle']);
    }

    public function testBackgroundUrlIsEscapedOnce(): void
    {
        $vars = self::runBrandingBlock(['custom_login_background' => 'https://intranet.example/wall.jpg?v=1&amp;s=2']);

        self::assertSame(
            ' style="background-image: url(\'https://intranet.example/wall.jpg?v=1&amp;s=2\');"',
            $vars['loginBackgroundStyle']
        );
    }

    public function testNoSettingKeepsTheShippedImages(): void
    {
        $vars = self::runBrandingBlock([]);

        self::assertSame('', $vars['loginBackgroundStyle']);
        self::assertSame('', $vars['loginLogoUrl']);
    }

    public function testPageEscapesBothImagesAndNoLongerShrinksTheLogo(): void
    {
        $login = self::source('app/core/login.php');

        self::assertStringContainsString(
            '<body class="hold-transition login-page \'.($theme_body ?? \'\').\'"\'.$loginBackgroundStyle.\'>',
            $login
        );
        self::assertStringContainsString(
            '\'<img src="\' . htmlspecialchars($loginLogoUrl, ENT_QUOTES, \'UTF-8\') . \'" alt="" style="max-width:100%; max-height:150px;" />\'',
            $login
        );
        self::assertStringNotContainsString('max-width:100px', $login);
    }

    public function testCustomFolderIsShippedWithoutScriptExecution(): void
    {
        self::assertStringContainsString('Options -ExecCGI', self::source('public/assets/custom/.htaccess'));
        self::assertStringContainsString('Settings → Options', self::source('public/assets/custom/README.md'));
    }

    public function testSettingsAreSeededAndExplained(): void
    {
        self::assertStringContainsString(
            "array('admin', 'custom_login_background', ''),",
            self::source('public/install/install-steps/run.step5.php')
        );
        self::assertStringContainsString(
            "VALUES ('admin', 'custom_login_background', '')",
            self::source('public/install/upgrade_run_3.2.2.php')
        );

        $options = self::source('app/pages/options.php');
        self::assertStringContainsString("\$lang->get('admin_misc_custom_logo_tip')", $options);
        self::assertStringContainsString("\$lang->get('admin_misc_custom_login_background_tip')", $options);

        $english = require __DIR__ . '/../../app/includes/language/english.php';
        foreach (['admin_misc_custom_logo_tip', 'admin_misc_custom_login_background_tip'] as $key) {
            self::assertStringContainsString('public/assets/custom/', $english[$key], $key);
        }
    }
}
