<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 TeamPass.net
 * SPDX-License-Identifier: GPL-3.0-only
 * Distributed without any warranty. See LICENSE.md.
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
