<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * TeamPass is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See <https://www.gnu.org/licenses/> for the GNU General Public License.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

/** Render the shipped settings fragment without booting a live administrator session. */
class SecureSendRetentionPresentationTest extends TestCase
{
    private function render(array $settings, string $language, ?string $hostileTranslation = null): string
    {
        $messages = include __DIR__ . '/../../app/includes/language/' . $language . '.php';
        $lang = new class($messages, $hostileTranslation) {
            public function __construct(private array $messages, private ?string $override) {}
            public function get(string $key): string
            {
                if (empty($this->messages[$key])) {
                    throw new RuntimeException('Missing retention translation: ' . $key);
                }
                return $this->override ?? $this->messages[$key];
            }
        };
        $SETTINGS = $settings;
        $source = (string) file_get_contents(__DIR__ . '/../../app/pages/options.php');
        $start = strpos($source, '<!-- Secure Send audit retention -->');
        $end = strpos($source, '<!-- End Secure Send audit retention -->', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        ob_start();
        try {
            // Only repository-owned PHP is evaluated; test values remain variables.
            eval('?>' . substr($source, $start, $end - $start));
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function testBothCatalogsRenderSafeDefaultsAndSavedPolicies(): void
    {
        foreach (['english', 'french'] as $language) {
            foreach ([[], ['secure_send_audit_retention_days' => '90']] as $settings) {
                $html = $this->render($settings, $language);
                $document = new DOMDocument();
                self::assertTrue($document->loadHTML('<?xml encoding="UTF-8">' . $html));
                $input = $document->getElementById('secure_send_audit_retention_days');
                self::assertInstanceOf(DOMElement::class, $input);
                self::assertSame((string) ($settings['secure_send_audit_retention_days'] ?? 0), $input->getAttribute('value'));
                self::assertSame('number', $input->getAttribute('type'));
                self::assertSame('0', $input->getAttribute('min'));
                self::assertSame('36500', $input->getAttribute('max'));
                self::assertSame('1', $input->getAttribute('step'));
                self::assertStringContainsString('form-control-sm', $input->getAttribute('class'));
                self::assertSame('secure_send_audit_retention_hint secure_send_audit_retention_warning', $input->getAttribute('aria-describedby'));
                self::assertNotNull($document->getElementById('secure_send_audit_retention_hint'));
                self::assertNotNull($document->getElementById('secure_send_audit_retention_warning'));
                self::assertSame('secure_send_audit_retention_days', $document->getElementsByTagName('label')->item(0)->getAttribute('for'));
            }
        }
        $javascript = (string) file_get_contents(__DIR__ . '/../../app/pages/admin.js.php');
        self::assertStringContainsString('type: "save_option_change"', $javascript);
        self::assertStringContainsString(".form-control-sm:not(.select2[multiple])", $javascript);
    }

    public function testTranslationsAndStoredValuesCannotInjectMarkupOrAttributes(): void
    {
        $hostile = "'><img src=x onerror=alert(1)> & \"";
        $html = $this->render(['secure_send_audit_retention_days' => $hostile], 'english', $hostile);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
        $document = new DOMDocument();
        self::assertTrue($document->loadHTML('<?xml encoding="UTF-8">' . $html));
        self::assertSame(0, $document->getElementsByTagName('img')->length);
        self::assertSame($hostile, $document->getElementById('secure_send_audit_retention_days')->getAttribute('value'));
        self::assertSame('', $document->getElementById('secure_send_audit_retention_days')->getAttribute('onerror'));
    }
}
