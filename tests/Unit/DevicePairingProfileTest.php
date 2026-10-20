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
 * @file      DevicePairingProfileTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

class DevicePairingProfileTest extends TestCase
{
    /** Actual view output must escape its server URL, messages and translated attributes. */
    public function testViewEscapesDynamicContentInBothAvailabilityModes(): void
    {
        $lang = new class {
            public function get(string $key): string { return 'R&D O\'Brien "<script>" ' . $key; }
        };
        $devicePairingServer = 'https://vault.test/"><img src=x onerror=alert(1)>';
        $devicePairingUnavailableReason = '<script>unavailable</script>';
        foreach ([true, false] as $devicePairingAllowed) {
            ob_start();
            require __DIR__ . '/../../app/pages/partials/device_pairing.php';
            $html = (string) ob_get_clean();
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML('<meta charset="utf-8">' . $html);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            self::assertSame(0, $document->getElementsByTagName('script')->length);
            self::assertSame(0, $document->getElementsByTagName('img')->length);
            self::assertStringContainsString('R&amp;D O&#039;Brien', $html);
            self::assertStringContainsString('&lt;script&gt;', $html);
        }
    }

    /** Every new pairing label, including states and error messages, is shipped in English/French. */
    public function testPairingTranslationsAndPlaceholdersAreComplete(): void
    {
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        $french = include __DIR__ . '/../../app/includes/language/french.php';
        foreach ($english as $key => $value) {
            if (strpos($key, 'device_pairing_') !== 0 && strpos($key, 'at_device_') !== 0) {
                continue;
            }
            self::assertArrayHasKey($key, $french);
            self::assertNotSame('', $value);
            self::assertNotSame('', $french[$key]);
            preg_match_all('/#[a-z_]+#/', $value, $tokens);
            preg_match_all('/#[a-z_]+#/', $french[$key], $translatedTokens);
            self::assertSame($tokens[0], $translatedTokens[0], $key);
        }
    }
}
