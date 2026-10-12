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
 * @file      SecureSendCleanupHandlerTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendCleanupHandlerTest extends TestCase
{
    private string $log;
    private string $previousLog;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../app/sources/otp.functions.php';
        require_once __DIR__ . '/../Fixtures/secure_send_memory_db.php';
        require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
        require_once __DIR__ . '/../../app/sources/secure_send.functions.php';
        require_once __DIR__ . '/../../app/sources/secure_send_storage.php';
        DB::reset();
        $this->log = tempnam(sys_get_temp_dir(), 'tp-cleanup-handler-');
        $this->previousLog = (string) ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousLog);
        unlink($this->log);
    }

    /** Execute the production handler tail after authorization and input validation. */
    private function dispatch(string $action): array
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $case = strpos($source, "case '" . $action . "':");
        self::assertIsInt($case);
        $next = strpos($source, "\n    case '", $case + 1);
        self::assertIsInt($next);
        $handler = substr($source, $case, $next - $case);
        // Keep the actual try/catch, encryption/storage or listing, and response.
        // Only the preceding authorization/input setup is outside this fixture.
        $purge = strpos($handler, 'secureSendPurgeExpiredLinks(');
        self::assertIsInt($purge);
        $cleanup = strrpos(substr($handler, 0, $purge), '        try {');
        self::assertIsInt($cleanup);
        $handler = substr($handler, $cleanup);
        $SETTINGS = ['otv_is_enabled' => 1, 'secure_send_allow_notes' => 1,
            'cpassman_url' => 'https://vault.example.com', 'date_format' => 'Y-m-d', 'time_format' => 'H:i'];
        $session = new class {
            public function get(string $key): int { return 42; }
        };
        $lang = new class {
            public function get(string $key): string { return $key; }
        };
        $dataReceived = [];
        $secureSendPlaintext = json_encode(['secret' => 'new-secret']);
        $secureSendPassphrase = '';
        $secureSendLimits = ['time_limit' => time() + 3600, 'views' => 1];
        $secureSendType = 'note';
        $secureSendItemId = null;
        $secureSendDescriptionTruncated = false;
        ob_start();
        try {
            eval('switch (true) { case true: ' . $handler . ' }');
            return json_decode((string) ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
    }

    public function testCleanupLockFailureDoesNotBlockAuditedCreation(): void
    {
        DB::$failCleanupQuery = true;
        $response = $this->dispatch('generate_OTV_url');
        self::assertSame('', $response['error']);
        self::assertArrayHasKey($response['otv_id'], DB::$links);
        self::assertSame(['created'], array_column(DB::$secureAudit, 'event'));
        self::assertFalse(DB::inTransaction());
        $diagnostic = (string) file_get_contents($this->log);
        self::assertStringContainsString('cleanup failed (RuntimeException)', $diagnostic);
        self::assertStringNotContainsString('secret-canary', $diagnostic);
    }

    public function testCleanupLockFailureDoesNotBlockListingOrExposeExpiredRows(): void
    {
        $expired = secureSendFixtureCreate(['send_type' => 'note']);
        DB::$links[$expired['otv_id']]['time_limit'] = time() - 1;
        $active = secureSendFixtureCreate(['send_type' => 'note']);
        DB::$failCleanupQuery = true;
        $response = $this->dispatch('list_secure_sends');
        self::assertSame('', $response['error']);
        self::assertSame([$active['otv_id']], array_column($response['sends'], 'id'));
        self::assertCount(2, DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertFalse(DB::inTransaction());
        $diagnostic = (string) file_get_contents($this->log);
        self::assertStringContainsString('cleanup failed (RuntimeException)', $diagnostic);
        self::assertStringNotContainsString('secret-canary', $diagnostic);
    }

    public function testMalformedHistoricalRowDoesNotBlockCreationOrListing(): void
    {
        $expired = secureSendFixtureCreate(['send_type' => 'note']);
        DB::$links[$expired['otv_id']]['time_limit'] = time() - 1;
        DB::$links[$expired['otv_id']]['originator'] = 0;
        $created = $this->dispatch('generate_OTV_url');
        self::assertSame('', $created['error']);
        $listed = $this->dispatch('list_secure_sends');
        self::assertSame('', $listed['error']);
        self::assertSame([$created['otv_id']], array_column($listed['sends'], 'id'));
        self::assertArrayHasKey($expired['otv_id'], DB::$links);
        self::assertSame(['created'], array_column(DB::$secureAudit, 'event'));
        self::assertStringContainsString('cleanup skipped invalid metadata (InvalidArgumentException)',
            (string) file_get_contents($this->log));
    }

    public function testIgnoredCleanupFailureDoesNotAllowUnauditedCreation(): void
    {
        DB::$failCleanupQuery = DB::$failSecureAudit = true;
        self::assertSame(['error' => 'server_error'], $this->dispatch('generate_OTV_url'));
        self::assertSame([], DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertFalse(DB::inTransaction());
        self::assertStringNotContainsString('secret-canary', (string) file_get_contents($this->log));
    }
}
