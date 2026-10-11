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
 * @file      SecureSendStatisticsDiagnosticsTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendStatisticsDiagnosticsTest extends TestCase
{
    /** Representative storage errors, plus an unrelated exception with sensitive text. */
    public static function failures(): array
    {
        return [
            'missing migration' => ["Table 'fixture_secure_send_audit' doesn't exist", true],
            'unknown column' => ["Unknown column 'a.event' in 'field list'", true],
            'connection lost' => ["MySQL server has gone away\nsecond line", true],
            'unrelated failure' => ['secret-canary', false],
        ];
    }

    #[DataProvider('failures')]
    public function testDatabaseDiagnosticsStayServerSideAndNeverIncludeSql(string $message, bool $databaseError): void
    {
        if (!extension_loaded('mysqli')) {
            self::markTestSkipped('MeekroDB requires mysqli.');
        }
        require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
        require_once __DIR__ . '/../../app/sources/secure_send_statistics.php';
        if (!function_exists('prefixTable')) {
            /** Use a fixture prefix without installation settings or a database connection. */
            function prefixTable(string $table): string
            {
                return 'fixture_' . $table;
            }
        }
        $exception = $databaseError
            ? new MeekroDBException($message, 'SELECT secret-canary FROM private_table', 1146)
            : new RuntimeException($message);
        // The hook throws before parsing/connecting: real exception, no live database.
        $hook = DB::addHook('pre_parse', static function () use ($exception): void {
            throw $exception;
        });
        $previousLog = (string) ini_get('error_log');
        $log = tempnam(sys_get_temp_dir(), 'tp-send-diagnostics-');
        ini_set('error_log', $log);
        try {
            $data = secureSendBuildOperationalStatistics(100, 200, [], []);
            $contents = (string) file_get_contents($log);
            self::assertFalse($data['available']);
            self::assertSame('query_failed', $data['reason']);
            self::assertNull($data['totals']);
            self::assertNull($data['creations']);
            self::assertSame([], $data['top_senders']);
            self::assertStringNotContainsString($message, json_encode($data));
            self::assertStringNotContainsString('secret-canary', $contents);
            self::assertStringNotContainsString('SELECT', $contents);
            if ($databaseError) {
                self::assertStringContainsString('MeekroDBException): ' . json_encode($message), $contents);
                self::assertSame(1, substr_count($contents, "\n"));
            } else {
                self::assertStringContainsString('(RuntimeException)', $contents);
            }
        } finally {
            DB::removeHook('pre_parse', $hook);
            ini_set('error_log', $previousLog);
            unlink($log);
        }
    }
}
