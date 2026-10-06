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

/** Exercise the actual card/template, including dynamic keys and hostile translations. */
class SecureSendStatisticsPresentationTest extends TestCase
{
    private function source(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../' . $path));
    }

    private function cardTemplate(): string
    {
        $page = $this->source('app/pages/statistics.php');
        $start = strpos($page, "<div class='card card-outline card-info' id='tp-secure-send-card'");
        $end = strpos($page, "<div class='tab-pane fade' id='tp-ops-lapr'", $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        return substr($page, $start, $end - $start);
    }

    /** Render repository-owned template fragments without a live session or database. */
    private function render(string $template, array $messages, ?string $override = null): string
    {
        $lang = new class($messages, $override) {
            public function __construct(private array $messages, private ?string $override)
            {
            }

            public function get(string $key): string
            {
                if (!isset($this->messages[$key]) || trim($this->messages[$key]) === '') {
                    throw new RuntimeException('Missing translation: ' . $key);
                }
                return $this->override ?? $this->messages[$key];
            }
        };
        $fixture = tempnam(sys_get_temp_dir(), 'tp-stats-translation-');
        self::assertIsString($fixture);
        $bufferLevel = ob_get_level();
        try {
            self::assertNotFalse(file_put_contents($fixture, $template));
            ob_start();
            require $fixture;
            return (string) ob_get_contents();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            unlink($fixture);
        }
    }

    public function testCardRendersAllDynamicMetricsInBothCatalogs(): void
    {
        foreach (['english', 'french'] as $language) {
            $messages = include __DIR__ . '/../../app/includes/language/' . $language . '.php';
            $html = $this->render($this->cardTemplate(), $messages);
            preg_match_all("/data-tp-secure-send-count='([^']+)'/", $html, $fields);
            self::assertCount(15, $fields[1]);
            self::assertCount(15, array_unique($fields[1]));
            self::assertStringContainsString('totals.sends_revealed', $html);
            self::assertStringContainsString('creations.protected', $html);
            self::assertStringContainsString('creations.public_links', $html);
            self::assertStringNotContainsString('<?php', $html);
        }
    }

    public function testTranslationsCannotInjectHtmlIntoTheCard(): void
    {
        $messages = include __DIR__ . '/../../app/includes/language/english.php';
        $html = $this->render($this->cardTemplate(), $messages, '<img src=x onerror="alert(1)"> & \'"');
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp;', $html);
    }

    public function testJavascriptTranslationsRoundTripAndCannotCloseTheScript(): void
    {
        $javascript = $this->source('app/pages/statistics.js.php');
        $start = strpos($javascript, 'function tpOpsSecureSendText(');
        $end = strpos($javascript, 'function tpOpsSecureSendCount(', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $template = substr($javascript, $start, $end - $start);
        $hostile = "</script><img title=\"x\"> & apostrophe' \\ newline\n中文";
        foreach (['english', 'french'] as $language) {
            $messages = include __DIR__ . '/../../app/includes/language/' . $language . '.php';
            $this->render($template, $messages);
            $rendered = $this->render($template, $messages, $hostile);
            $pattern = <<<'REGEX'
/\b(?:loading|unavailable|empty|sender|disabled|deleted|missing): ("(?:[^"\\]|\\.)*")/
REGEX;
            preg_match_all($pattern, $rendered, $values);
            self::assertCount(7, $values[1]);
            foreach ($values[1] as $value) {
                self::assertSame($hostile, json_decode(rtrim($value, ','), true, 512, JSON_THROW_ON_ERROR));
            }
            self::assertStringNotContainsString('</script', strtolower($rendered));
        }
    }
}
