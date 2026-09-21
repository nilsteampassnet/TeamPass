<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Every shipped locale must provide the renewal UI with intact substitution tokens. */
class RenewalLocalizationTest extends TestCase
{
    private const KEYS = [
        'select_date_showing_items_expiration', 'renewal_page_info', 'renewal_all_deadlines',
        'renewal_notice_period', 'renewal_notice_none', 'renewal_notice_explanation', 'renewal_notice_due',
        'renewal_notice_estimate', 'renewal_notice_existing', 'renewal_notice_expired', 'renewal_notice_unknown',
        'renewal_notice_unavailable', 'renewal_notice_move_confirm', 'item_renewal_enable', 'item_renewal_period',
        'item_renewal_help', 'renewal_period_invalid', 'at_renewal_period', 'renewal_notice_effective',
        'renewal_notice_source_item', 'renewal_notice_source_folder', 'renewal_notice_source_none',
        'renewal_notice_source_lapr', 'renewal_effective_days', 'renewal_individual_policies', 'renewal_covered_items',
        'renewal_effective_overdue', 'renewal_folder_overdue', 'renewal_badge_scheduled', 'renewal_badge_soon',
        'renewal_badge_expired', 'renewal_badge_unknown', 'compliance_report_rotation_overdue',
        'compliance_report_rotation_overdue_tip', 'compliance_report_rotation_sla_tip',
    ];

    /** Support the legacy Arabic catalog while isolating its global variable. */
    private function catalog(string $path): array
    {
        $hadLang = array_key_exists('LANG', $GLOBALS);
        $original = $GLOBALS['LANG'] ?? null;
        try {
            $loaded = require $path;
            return is_array($loaded) ? $loaded : ($GLOBALS['LANG'] ?? []);
        } finally {
            if ($hadLang) {
                $GLOBALS['LANG'] = $original;
            } else {
                unset($GLOBALS['LANG']);
            }
        }
    }

    /** Compare only the renewal keys so unrelated legacy translations do not mask regressions. */
    public function testRenewalMessagesExistAndPreserveTokensInEveryLanguage(): void
    {
        $directory = __DIR__ . '/../../app/includes/language/';
        $english = $this->catalog($directory . 'english.php');
        $paths = glob($directory . '*.php');
        self::assertCount(25, $paths);
        foreach ($paths as $path) {
            $catalog = $this->catalog($path);
            $source = (string) file_get_contents($path);
            self::assertTrue(mb_check_encoding($source, 'UTF-8'), $path);
            foreach (self::KEYS as $key) {
                $context = basename($path) . ': ' . $key;
                self::assertArrayHasKey($key, $catalog, $context);
                self::assertNotSame('', trim($catalog[$key]), $context);
                self::assertSame(1, preg_match_all('/[\'\"]' . preg_quote($key, '/') . '[\'\"]\s*=>/', $source), $context);
                preg_match_all('/#[a-z_]+#/', $english[$key], $expected);
                preg_match_all('/#[a-z_]+#/', $catalog[$key], $actual);
                sort($expected[0]);
                sort($actual[0]);
                self::assertSame($expected[0], $actual[0], $context);
            }
        }
    }
}
