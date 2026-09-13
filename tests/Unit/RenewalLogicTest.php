<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/renewal_logic.php';

/** Validate policy precedence and its SQL equivalent independently of the interface. */
class RenewalLogicTest extends TestCase
{
    public function testRejectsMalformedAndOutOfRangePeriods(): void
    {
        foreach ([null, true, false, -1, '1.5', '1e2', '', '90 days', [], 36501] as $invalid) {
            try {
                renewalValidatePeriod($invalid);
                self::fail('Accepted an invalid period: ' . json_encode($invalid));
            } catch (InvalidArgumentException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
        foreach ([0, 1, '90', 36500] as $valid) {
            self::assertSame((int) $valid, renewalValidatePeriod($valid));
        }
    }

    public function testSqlAndPhpUseTheShortestActivePolicy(): void
    {
        if (!extension_loaded('sqlite3')) self::markTestSkipped('SQLite is required.');
        $db = new SQLite3(':memory:');
        foreach ([false, true] as $enabled) {
            foreach ([0, 30, 90, 180] as $item) {
                foreach ([0, 30, 90, 180] as $folder) {
                    $sql = renewalPeriodSql($enabled, (string) $item, (string) $folder);
                    self::assertSame(renewalEffectiveDays($item, $folder, $enabled), $db->querySingle('SELECT ' . $sql));
                }
            }
        }
        self::assertNull(renewalDueAt(30, 0));
        self::assertNull(renewalDueAt(0, 1000));
        self::assertSame(2593000, renewalDueAt(30, 1000));
    }
}
