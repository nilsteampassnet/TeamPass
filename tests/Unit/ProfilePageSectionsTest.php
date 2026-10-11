<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      ProfilePageSectionsTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

/**
 * The profile page is split into sections reached from a menu. These tests pin what the
 * split must not lose: its translations, the links that already point to a section, and
 * a script that keeps running whatever the instance has enabled.
 */
class ProfilePageSectionsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static function source(string $path): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $path);
    }

    public function testEveryProfileTranslationExistsInEverySynchronizedLanguage(): void
    {
        $source = self::source('app/pages/profile.php') . self::source('app/pages/profile.js.php');
        preg_match_all('/\$lang->get\(\x27(profile_[a-z_]+)\x27\)/', $source, $matches);
        $keys = array_values(array_unique($matches[1]));
        self::assertNotEmpty($keys);

        foreach ((array) glob(self::ROOT . '/app/includes/language/*.php') as $file) {
            $name = basename((string) $file, '.php');
            // arabic.php is not part of the synchronized set
            if ($name === 'arabic') {
                continue;
            }
            $strings = include $file;
            foreach ($keys as $key) {
                self::assertArrayHasKey($key, $strings, $name . ': ' . $key);
                self::assertNotSame('', trim((string) $strings[$key]), $name . ': ' . $key);
            }
        }
    }

    public function testEverySectionOfTheMenuHasItsPane(): void
    {
        $profile = self::source('app/pages/profile.php');

        foreach (['overview', 'security', 'extension', 'settings', 'timeline', 'advanced'] as $section) {
            self::assertStringContainsString("'" . $section . "' => [", $profile, 'menu entry: ' . $section);
            self::assertStringContainsString('id="tab_' . $section . '"', $profile, 'pane: ' . $section);
        }
        // The menu links are built from the same keys as the panes
        self::assertStringContainsString('href="#tab_<?php echo $section; ?>"', $profile);
        // A section with nothing to show is left out of the menu
        self::assertStringContainsString('if ($profileSections[$section] === false) {', $profile);
    }

    public function testExistingTabLinksKeepOpeningTheirSection(): void
    {
        $profile = self::source('app/pages/profile.php');

        // ?tab=settings and ?tab=timeline predate the menu
        self::assertStringContainsString("'settings' => true,", $profile);
        self::assertStringContainsString("'timeline' => true,", $profile);
        // An unknown or unavailable section falls back to the overview
        self::assertStringContainsString(
            "(\$profileSections[\$get['tab']] ?? false) === true ? \$get['tab'] : 'overview'",
            $profile
        );
    }

    public function testThePasswordBlockFollowsTheAccountMenu(): void
    {
        $profile = self::source('app/pages/profile.php');
        $index = self::source('public/index.php');
        $javascript = self::source('app/pages/profile.js.php');

        // Same accounts as the "Change password" entry: not a directory, not SSO
        self::assertStringContainsString(
            "empty(\$session_auth_type) === false && \$session_auth_type !== 'ldap' && \$session_auth_type !== 'oauth2'",
            $index
        );
        self::assertStringContainsString(
            "\$passwordManagedHere = \$userAuthType !== '' && \$userAuthType !== 'ldap' && \$userAuthType !== 'oauth2';",
            $profile
        );
        // The button runs the menu entry rather than a second implementation
        self::assertStringContainsString("\$('.user-menu[data-name=\"password-change\"]').first().trigger('click');", $javascript);
    }

    public function testTheScriptDoesNotDependOnAnOptionalBlock(): void
    {
        $javascript = self::source('app/pages/profile.js.php');

        // The API key is absent while the API is disabled: binding it directly threw and
        // stopped everything declared after it, the passkeys among them
        self::assertStringNotContainsString("document.getElementById('copy-api-key').addEventListener", $javascript);
        self::assertStringContainsString("\$(document).on('click', '#copy-api-key', function() {", $javascript);
        // The lists are no longer tables: the rename dialog reads the name from the line itself
        self::assertStringContainsString("closest('[data-label]')", $javascript);
        self::assertStringNotContainsString("closest('tr')", $javascript);
    }

    public function testTheProfileShowsWhatTheExtensionDetectionFound(): void
    {
        $bridge = self::source('app/core/extension-autoconfig.js.php');
        $javascript = self::source('app/pages/profile.js.php');

        self::assertStringContainsString("\$(document).trigger('tpExtAutoconfig:state')", $bridge);
        self::assertSame(2, substr_count($bridge, "            publishState()\n"));
        self::assertStringContainsString("\$(document).on('tpExtAutoconfig:state', renderExtensionStatus);", $javascript);
        // Read once at load too: the handshake may have settled before this script ran
        self::assertStringContainsString("    renderExtensionStatus();\n", $javascript);
    }
}
