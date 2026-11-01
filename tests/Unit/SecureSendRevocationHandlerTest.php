<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\SecureSendRevocationHandler;

use DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Observe payload decoding after the existing session-key boundary. */
final class Exchange
{
    public static int $decodes = 0;
}

/** Isolate transport encoding; the handler and audited revocation run unchanged. */
function prepareExchangedData(string $data, string $direction): array
{
    ++Exchange::$decodes;
    return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendRevocationHandlerTest extends TestCase
{
    private string $log;
    private string $previousLog;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Fixtures/secure_send_memory_db.php';
        require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
        require_once __DIR__ . '/../../app/sources/secure_send_storage.php';
        DB::reset();
        Exchange::$decodes = 0;
        $this->log = tempnam(sys_get_temp_dir(), 'tp-revoke-handler-');
        $this->previousLog = (string) ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        $diagnostic = (string) file_get_contents($this->log);
        ini_set('error_log', $this->previousLog);
        unlink($this->log);
        self::assertStringNotContainsString('canary', $diagnostic);
    }

    /** Seed metadata without needing to decrypt or generate a recipient URL. */
    private function link(string $type = 'item_v2'): int
    {
        DB::insert(prefixTable('otv'), [
            'originator' => 42, 'item_id' => $type === 'note' ? null : 123,
            'send_type' => $type, 'timestamp' => (string) time(),
            'time_limit' => time() + 3600, 'max_views' => 2, 'views' => 0,
            'has_passphrase' => 0, 'shared_globaly' => 0, 'failed_attempts' => 0,
            'encrypted' => 'ciphertext-canary', 'code' => 'credential-canary',
        ]);
        return DB::insertId();
    }

    /** Execute the production handler including its key, decode and owner checks. */
    private function dispatch(int $id, array $SETTINGS, int $userId = 42, string $key = 'session-key'): array
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $start = strpos($source, "case 'revoke_secure_send':");
        $end = strpos($source, "case 'image_preview_preparation':", $start);
        $handler = substr($source, $start, $end - $start);
        $session = new class ($userId) {
            public function __construct(private int $id) {}
            public function get(string $name): string|int { return $name === 'key' ? 'session-key' : $this->id; }
        };
        $inputData = ['key' => $key, 'data' => json_encode(['id' => $id, 'originator' => 42])];
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; use Throwable; switch (true) { case true: ' . $handler . ' }');
            return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
    }

    public function testOwnerCanRevokeDespiteGlobalOrPerLinkPolicySuspension(): void
    {
        foreach (['item_v2', 'note'] as $type) {
            foreach ([['otv_is_enabled' => 0], ['otv_is_enabled' => 1, 'secure_send_allow_notes' => 0],
                ['otv_is_enabled' => 1, 'secure_send_require_passphrase' => 1]] as $settings) {
                DB::reset();
                $id = $this->link($type);
                self::assertSame(['error' => '', 'id' => $id], $this->dispatch($id, $settings));
                self::assertSame([], DB::$links);
                self::assertSame(['revoked'], array_column(DB::$secureAudit, 'event'));
                self::assertSame([42], array_column(DB::$secureAudit, 'actor_id'));
                self::assertSame('sender_revoked', DB::$secureAudit[0]['reason']);
                self::assertFalse(DB::inTransaction());
            }
        }
    }

    public function testDisabledFeatureDoesNotBypassSessionOrOwnershipChecks(): void
    {
        $id = $this->link();
        self::assertSame([['error' => 'key_not_conform']], $this->dispatch($id, ['otv_is_enabled' => 0], 42, 'wrong-key'));
        self::assertSame(0, Exchange::$decodes);
        self::assertSame(['error' => 'not_allowed'], $this->dispatch($id, ['otv_is_enabled' => 0], 43));
        self::assertSame(1, Exchange::$decodes);
        self::assertArrayHasKey($id, DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertFalse(DB::inTransaction());
    }

    public function testAuditFailureKeepsTheLinkAndReturnsOnlyAGenericError(): void
    {
        $id = $this->link();
        DB::$failSecureAudit = true;
        self::assertSame(['error' => 'server_error'], $this->dispatch($id, ['otv_is_enabled' => 0]));
        self::assertArrayHasKey($id, DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertFalse(DB::inTransaction());
    }
}
