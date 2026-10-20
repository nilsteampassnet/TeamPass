<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (C) 2009-2026 Teampass.net
 * Distributed under the GNU GPL version 3, WITHOUT ANY WARRANTY.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/licence_trial_logic.php';

class LicenceExpiryPresentationTest extends TestCase
{
    private function source(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../' . $path));
    }

    /** Execute repository-owned presentation fragments without a session or network call. */
    private function render(string $code, array $variables): array
    {
        $fixture = tempnam(sys_get_temp_dir(), 'tp-licence-expiry-');
        self::assertIsString($fixture);
        try {
            self::assertNotFalse(file_put_contents($fixture, '<?php ' . $code));
            extract($variables, EXTR_SKIP);
            return require $fixture;
        } finally {
            unlink($fixture);
        }
    }

    public function testPanelKeepsTheServerDayWhileLinkTimestampsKeepLocalTime(): void
    {
        $source = $this->source('app/sources/licence.functions.php');
        $start = strpos($source, "    \$dateFormat = (\$SETTINGS['date_format']");
        self::assertIsInt($start);
        $end = strpos($source, '    return $vm;', $start);
        self::assertIsInt($end);
        $code = substr($source, $start, $end - $start) . 'return $vm;';
        $raw = '2027-06-16 23:59:59';
        $originalTimezone = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Paris');
            $view = $this->render($code, [
                'SETTINGS' => ['date_format' => 'd/m/Y', 'time_format' => 'H:i'],
                'vm' => ['licence' => ['expiration_date' => $raw],
                    'link_expires_at' => licenceTrialParseServerDate($raw),
                    'offline_link_sent_at' => licenceTrialParseServerDate($raw)],
            ]);
            self::assertSame('16/06/2027', $view['licence']['expiration_display']);
            self::assertSame($raw, $view['licence']['expiration_date']);
            self::assertSame('17/06/2027 01:59', $view['link_expires_at_display']);
            self::assertSame('17/06/2027 01:59', $view['offline_link_sent_display']);
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function testWidgetResponseAddsTheFormattedDateAndPreservesTheRawValue(): void
    {
        $source = $this->source('app/sources/admin.queries.php');
        $start = strpos($source, "case 'get_extension_licence_info':");
        $end = strpos($source, "case 'get_licence_panel':", $start);
        self::assertSame(1, preg_match(
            "/echo prepareExchangedData\(\s*(\[\s*'error' => false,[\s\S]*?\n        \]),\s*'encode'/",
            substr($source, $start, $end - $start), $matches
        ));
        foreach (['2027-06-16 23:59:59' => '16/06/2027', '' => '', 'invalid' => ''] as $raw => $expected) {
            $response = $this->render('return ' . $matches[1] . ';', [
                'SETTINGS' => ['date_format' => 'd/m/Y'],
                'licenceInfo' => ['server_online' => true, 'server_version' => 'test'],
                'licenceExtension' => ['status' => 'VALID', 'expiration_date' => $raw,
                    'consumed' => 1, 'max_users' => 10, 'trial' => false],
                'licenceDaysLeft' => null, 'licenceRefused' => 0, 'licenceHistory' => licenceTrialEmptyHistory(),
            ]);
            self::assertSame($raw, $response['expiration_date']);
            self::assertArrayHasKey('expiration_display', $response);
            self::assertSame($expected, $response['expiration_display']);
        }
    }
}
