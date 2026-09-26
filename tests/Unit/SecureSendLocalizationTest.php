<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

/** Directly maintained Secure Send catalogs; other languages are synchronized via POEditor. */
class SecureSendLocalizationTest extends TestCase
{
    public function testEnglishAndFrenchMessagesAreCompleteAndPreserveTokens(): void
    {
        $directory = dirname(__DIR__, 2) . '/app/includes/language/';
        $english = require $directory . 'english.php';
        $keys = array_filter(array_keys($english), static fn (string $key): bool =>
            $key === 'secure_send' || str_starts_with($key, 'secure_send_')
        );
        self::assertNotEmpty($keys);
        foreach (['english', 'french'] as $locale) {
            $path = $directory . $locale . '.php';
            $catalog = require $path;
            $language = new Language($locale, $directory);
            $source = (string) file_get_contents($path);
            self::assertTrue(mb_check_encoding($source, 'UTF-8'), $locale);
            foreach ($keys as $key) {
                $context = $locale . ': ' . $key;
                self::assertArrayHasKey($key, $catalog, $context);
                self::assertNotSame('', trim($catalog[$key]), $context);
                self::assertSame($catalog[$key], $language->getShipped($key), $context);
                self::assertSame(1, preg_match_all('/[\'"]' . preg_quote($key, '/') . '[\'"]\s*=>/', $source), $context);
                preg_match_all('/#[A-Za-z_]+#/', $english[$key], $expected);
                preg_match_all('/#[A-Za-z_]+#/', $catalog[$key], $actual);
                sort($expected[0]);
                sort($actual[0]);
                self::assertSame($expected[0], $actual[0], $context);
            }
            foreach (['otv_message', 'settings_otv_subdomain', 'settings_otv_subdomain_tip'] as $obsolete) {
                self::assertArrayNotHasKey($obsolete, $catalog);
            }
        }
    }

    public function testPendingPoeditorTranslationsUseTheEnglishFallback(): void
    {
        $directory = dirname(__DIR__, 2) . '/app/includes/language/';
        $english = require $directory . 'english.php';
        $hadLang = array_key_exists('LANG', $GLOBALS);
        $original = $GLOBALS['LANG'] ?? null;
        try {
            foreach (glob($directory . '*.php') as $path) {
                $catalog = require $path;
                $language = new Language(basename($path, '.php'), $directory);
                foreach (['secure_send_reveal', 'secure_send_description_truncated', 'secure_send_invalid_public_url'] as $key) {
                    $expected = !empty($catalog[$key]) ? $catalog[$key] : $english[$key];
                    self::assertSame($expected, $language->getShipped($key), basename($path) . ': ' . $key);
                }
            }
        } finally {
            if ($hadLang) {
                $GLOBALS['LANG'] = $original;
            } else {
                unset($GLOBALS['LANG']);
            }
        }
    }
}
