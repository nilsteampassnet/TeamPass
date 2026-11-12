<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      SecureSendApiSettingTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

/**
 * Secure Send through the REST API is an explicit administrator opt-in.
 *
 * Device tokens and personal access tokens make link creation reachable outside a web
 * session, so enabling Secure Send on the web must never enable it for API clients.
 */
final class SecureSendApiSettingTest extends TestCase
{
    private function source(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testFreshInstallSeedsTheToggleOff(): void
    {
        self::assertStringContainsString(
            "array('admin', 'secure_send_api_enabled', '0'),",
            $this->source('public/install/install-steps/run.step5.php')
        );
    }

    public function testUpgradeSeedsTheToggleOffWithoutOverridingAChoice(): void
    {
        $upgrade = $this->source('public/install/upgrade_run_3.2.4.php');
        $section = substr($upgrade, (int) strpos($upgrade, '//--->BEGIN 3.2.4'),
            (int) strpos($upgrade, '//--->END 3.2.4') - (int) strpos($upgrade, '//--->BEGIN 3.2.4'));
        self::assertMatchesRegularExpression(
            "/INSERT IGNORE INTO `\" \\. \\\$pre \\. \"misc` \\(`type`, `intitule`, `valeur`\\)\\s+VALUES \\('admin', 'secure_send_api_enabled', '0'\\)/",
            $section
        );
        // Recorded before the upgrade timestamp, so a failure leaves the upgrade replayable.
        self::assertLessThan(strpos($section, "'upgrade_timestamp'"), strpos($section, "'secure_send_api_enabled'"));
    }

    /** Render the actual Settings → API row: a missing row and any value but 1 read as off. */
    public function testApiSettingsToggleReflectsTheStoredValue(): void
    {
        $page = $this->source('app/pages/api.php');
        $key = strpos($page, "\$lang->get('secure_send_api_enabled')");
        self::assertIsInt($key);
        $start = strrpos(substr($page, 0, $key), "<div class='row mb-2'>");
        self::assertIsInt($start);
        $end = strpos($page, "<div class='row mb-3'>", $key);
        self::assertIsInt($end);
        $row = substr($page, $start, $end - $start);
        $lang = new class {
            public function get(string $key): string { return '<' . $key . '>'; }
        };
        foreach ([[[], 'false', '0'], [['secure_send_api_enabled' => '0'], 'false', '0'],
            [['secure_send_api_enabled' => '2'], 'false', '0'], [['secure_send_api_enabled' => '1'], 'true', '1']] as [$SETTINGS, $on, $value]) {
            ob_start();
            try {
                eval('?>' . $row);
                $html = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            self::assertStringContainsString("id='secure_send_api_enabled' data-toggle-on='" . $on . "'", $html);
            self::assertStringContainsString("id='secure_send_api_enabled_input' value='" . $value . "'", $html);
            // Labels are escaped like the other admin strings of this page.
            self::assertStringContainsString('&lt;secure_send_api_enabled_tip&gt;', $html);
        }
    }

    public function testEnglishAndFrenchStringsExist(): void
    {
        foreach (['english', 'french'] as $language) {
            $strings = require dirname(__DIR__, 2) . '/app/includes/language/' . $language . '.php';
            foreach (['secure_send_api_enabled', 'secure_send_api_enabled_tip'] as $key) {
                self::assertNotSame('', trim((string) ($strings[$key] ?? '')), $language . ': ' . $key);
            }
        }
    }
}
