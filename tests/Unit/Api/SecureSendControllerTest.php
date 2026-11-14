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
 * @file      SecureSendControllerTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * The real SecureSendController on the transactional memory database: note sends, the link
 * inventory and revocation end to end, with real Defuse encryption and the production
 * recipient path.
 *
 * Request decoding and policy decisions are covered by SecureSendApiTest.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SecureSendControllerTest extends TestCase
{
    private const USER = ['id' => 42, 'is_admin' => 0, 'is_read_only' => 0, 'allowed_to_read' => 1,
        'key_tempo' => 'tempo', 'jti' => 'jti'];
    private const NOTE = ['title' => ' Wi-Fi ', 'secret' => 'note-secret-canary', 'note' => "Line 1\nLine 2",
        'login' => 'guest', 'url' => 'https://wifi.example.com'];

    private array $settings;
    private string $log;
    private string $previousLog;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../app/sources/otp.functions.php';
        require_once __DIR__ . '/../../Fixtures/secure_send_memory_db.php';
        require_once __DIR__ . '/../../Fixtures/secure_send_dependencies.php';
        require_once __DIR__ . '/../../Fixtures/secure_send_api_controller.php';
        require_once __DIR__ . '/../../../app/sources/secure_send.functions.php';
        require_once __DIR__ . '/../../../app/api/Controller/Api/BaseController.php';
        require_once __DIR__ . '/../../../app/api/Controller/Api/SecureSendController.php';
        DB::reset();
        $GLOBALS['secureSendApiActivity'] = [];
        $this->settings = ['otv_is_enabled' => 1, 'secure_send_api_enabled' => 1, 'secure_send_allow_notes' => 1,
            'cpassman_url' => 'https://vault.example.com', 'otv_expiration_period' => 7, 'secure_send_max_views' => 5];
        $this->log = (string) tempnam(sys_get_temp_dir(), 'tp-secure-send-api-');
        $this->previousLog = (string) ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        $diagnostic = (string) file_get_contents($this->log);
        ini_set('error_log', $this->previousLog);
        unlink($this->log);
        // Neither note content nor link material ever reaches the server log.
        self::assertStringNotContainsString('canary', $diagnostic);
    }

    /**
     * Run one controller action on a synthetic request.
     *
     * @return array{status: int, headers: array, body: array}
     */
    private function call(string $action, string $method, array $query = [], ?array $body = null, array $headers = [],
        array $user = self::USER): array
    {
        $server = [];
        if ($body !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $request = SymfonyRequest::create('https://vault.example.com/api/index.php/secureSend/' . $action
            . ($query === [] ? '' : '?' . http_build_query($query)), $method, [], [], [], $server,
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR));
        $controller = new class ($this->settings, $request) extends SecureSendController {
            public array $headers = [];
            public string $output = '';

            public function __construct(private array $testSettings, private SymfonyRequest $testRequest)
            {
            }

            protected function settings(): array
            {
                return $this->testSettings;
            }

            protected function currentRequest(): SymfonyRequest
            {
                return $this->testRequest;
            }

            protected function sendOutput($data, array $httpHeaders = []): void
            {
                $this->output = (string) $data;
                $this->headers = $httpHeaders;
            }
        };
        $controller->{$action . 'Action'}($user);
        $statusLines = array_values(preg_grep('/^HTTP\/1\.1 \d{3} /', $controller->headers));
        self::assertCount(1, $statusLines, 'one status line');

        return [
            'status' => (int) substr($statusLines[0], 9, 3),
            'headers' => $controller->headers,
            'body' => json_decode($controller->output, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /** Seed an owned link without going through creation. */
    private function seedLink(int $originator = 42, string $type = 'item_v2', array $changes = []): int
    {
        DB::insert(prefixTable('otv'), array_replace([
            'originator' => $originator, 'item_id' => $type === 'note' ? null : 123, 'send_type' => $type,
            'timestamp' => (string) time(), 'time_limit' => time() + 3600, 'max_views' => 2, 'views' => 0,
            'has_passphrase' => 0, 'shared_globaly' => 0, 'failed_attempts' => 0,
            'encrypted' => 'ciphertext-canary', 'code' => 'credential-canary', 'protected_key' => 'wrapped-key-canary',
        ], $changes));
        return DB::insertId();
    }

    /** Response header value, or null. */
    private static function header(array $response, string $name): ?string
    {
        foreach ($response['headers'] as $header) {
            if (stripos($header, $name . ': ') === 0) {
                return substr($header, strlen($name) + 2);
            }
        }
        return null;
    }

    /** Redeem a link through the production recipient path. */
    private function reveal(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $parameters = secureSendRequestParameters($query);
        self::assertIsArray($parameters);
        return secureSendRedeem($parameters, '', $this->settings, (string) parse_url($url, PHP_URL_HOST));
    }

    private function assertNothingChanged(): void
    {
        self::assertSame([], DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], $GLOBALS['secureSendApiActivity']);
        self::assertFalse(DB::inTransaction());
    }

    private function assertRefusal(array $response, int $status, string $reason): void
    {
        self::assertSame([$status, $reason], [$response['status'], $response['body']['reason'] ?? null]);
        self::assertContains('Content-Type: application/problem+json; charset=UTF-8', $response['headers']);
    }

    public function testNoteSendCreatesARedeemableLinkWithoutTouchingTheVault(): void
    {
        $before = time();
        $response = $this->call('create', 'POST', [], ['type' => 'note', 'note' => self::NOTE, 'days' => 2, 'views' => 3]);

        self::assertSame(201, $response['status']);
        $body = $response['body'];
        self::assertSame(['id', 'type', 'url', 'item_id', 'expires_at', 'max_views', 'has_passphrase', 'public_address',
            'totp_included', 'description_truncated', 'email_queued'], array_keys($body));
        self::assertSame([1, 'note', null, 3, false, false, false, false],
            [$body['id'], $body['type'], $body['item_id'], $body['max_views'], $body['has_passphrase'],
                $body['totp_included'], $body['description_truncated'], $body['email_queued']]);
        self::assertGreaterThanOrEqual($before + 2 * 86400, $body['expires_at']);
        self::assertStringStartsWith('https://vault.example.com/index.php?otv=1&code=', $body['url']);

        // Stored as a note of the token's user; the content exists only as ciphertext.
        $link = DB::$links[1];
        self::assertSame(['note', null, 42, 3], [$link['send_type'], $link['item_id'], $link['originator'], $link['max_views']]);
        self::assertStringNotContainsString('note-secret-canary', json_encode($link, JSON_THROW_ON_ERROR));
        self::assertSame([['created', 42, null, 'note']], array_map(
            static fn (array $row): array => [$row['event'], $row['actor_id'], $row['item_id'], $row['send_type']],
            DB::$secureAudit
        ));
        self::assertSame([42], $GLOBALS['secureSendApiActivity']);

        // A note discloses nothing from the vault: no item lookup, no private key unwrapped.
        self::assertSame([], FolderAccessModel::$calls);
        self::assertFalse(function_exists('get_user_keys'), 'jwt_utils.php must not be loaded for a note');

        $revealed = $this->reveal($body['url']);
        self::assertSame('', $revealed['error']);
        self::assertSame(['Wi-Fi', 'note-secret-canary', "Line 1\nLine 2", 'guest', 'https://wifi.example.com'],
            [$revealed['fields']['title'], $revealed['fields']['secret'], $revealed['fields']['note'],
                $revealed['fields']['login'], $revealed['fields']['url']]);
        self::assertSame(2, $revealed['remaining_views']);
    }

    /** The published bound keeps every note storable, even one whose JSON escaping doubles it. */
    public function testANoteAtTheMaximumSizeIsStorable(): void
    {
        // Quotes and backslashes double when JSON-encoded; line breaks too, but a note of line breaks only is empty.
        foreach ([str_repeat('"', 16000), str_repeat('\\', 16000), str_repeat("x\n", 8000), str_repeat('é', 8000)] as $secret) {
            DB::reset();
            self::assertSame(16000, strlen($secret));
            $response = $this->call('create', 'POST', [], ['type' => 'note', 'note' => ['secret' => $secret]]);
            self::assertSame(201, $response['status'], (string) ($response['body']['reason'] ?? ''));
            self::assertSame($secret, $this->reveal($response['body']['url'])['fields']['secret']);
        }
    }

    public function testNoteRefusalsCreateNothingAndEchoNoContent(): void
    {
        $note = ['type' => 'note', 'note' => self::NOTE];
        $cases = [
            'oversized note' => [['type' => 'note', 'note' => ['secret' => str_repeat('x', 16001)]], 400, 'invalid_payload'],
            'note with an item id' => [$note + ['item_id' => 123], 400, 'invalid_payload'],
            'note with a TOTP flag' => [$note + ['include_totp' => true], 400, 'invalid_payload'],
            'unknown note member' => [['type' => 'note', 'note' => ['password' => 'note-secret-canary']], 400, 'invalid_payload'],
            'empty note' => [['type' => 'note', 'note' => ['title' => 'Only a title', 'secret' => ' ']], 400, 'empty_note'],
            'item body carrying a note' => [['item_id' => 123, 'note' => self::NOTE], 400, 'invalid_payload'],
        ];
        foreach ($cases as $case => [$body, $status, $reason]) {
            $response = $this->call('create', 'POST', [], $body);
            $this->assertRefusal($response, $status, $reason);
            self::assertStringNotContainsString('canary', json_encode($response, JSON_THROW_ON_ERROR), $case);
            $this->assertNothingChanged();
        }

        $this->settings['secure_send_allow_notes'] = 0;
        $this->assertRefusal($this->call('create', 'POST', [], $note), 403, 'notes_not_allowed');
        $this->assertNothingChanged();

        $this->settings['secure_send_allow_notes'] = 1;
        $this->settings['secure_send_api_enabled'] = 0;
        $this->assertRefusal($this->call('create', 'POST', [], $note), 503, 'secure_send_disabled');
        $this->assertNothingChanged();
        self::assertSame([], FolderAccessModel::$calls);
    }

    /** Item bodies, with or without an explicit type, still go through the API visibility pre-check. */
    public function testItemSendsKeepTheVisibilityPreCheck(): void
    {
        $this->assertRefusal($this->call('create', 'POST', [], ['item_id' => 999]), 404, 'not_found');
        FolderAccessModel::$canAccess = false;
        $this->assertRefusal($this->call('create', 'POST', [], ['type' => 'item', 'item_id' => 123]), 403, 'not_allowed');
        self::assertSame([['canAccessItemInFolder', 42, 7, 123]], FolderAccessModel::$calls);
        FolderAccessModel::$canAccess = true;
        FolderAccessModel::$satisfiesRestriction = false;
        $this->assertRefusal($this->call('create', 'POST', [], ['item_id' => 123]), 403, 'not_allowed');
        self::assertSame(['satisfiesItemRestriction', 42, 123], FolderAccessModel::$calls[2]);
        self::assertFalse(function_exists('get_user_keys'), 'keys are unwrapped only after the pre-check');
        $this->assertNothingChanged();
    }

    public function testListReturnsOnlyTheCallersOpenableLinksNewestFirst(): void
    {
        $expiry = time() + 3600;
        $item = $this->seedLink(42, 'item_v2', ['time_limit' => $expiry, 'has_passphrase' => 1]);
        $this->seedLink(43);
        $note = $this->seedLink(42, 'note', ['time_limit' => $expiry, 'views' => 1, 'max_views' => 3]);
        $expired = $this->seedLink(42, 'item_v2', ['time_limit' => time() - 10]);
        $exhausted = $this->seedLink(42, 'note', ['views' => 2]);
        $blocked = $this->seedLink(42, 'note', ['failed_attempts' => 5]);

        $response = $this->call('list', 'GET');
        self::assertSame(200, $response['status']);
        self::assertSame([
            ['id' => $note, 'type' => 'note', 'item_id' => null, 'folder_id' => null, 'label' => null, 'has_passphrase' => false,
                'expires_at' => $expiry, 'max_views' => 3, 'remaining_views' => 2, 'suspended' => false, 'email_status' => null],
            ['id' => $item, 'type' => 'item', 'item_id' => 123, 'folder_id' => 7, 'label' => 'Original label', 'has_passphrase' => true,
                'expires_at' => $expiry, 'max_views' => 2, 'remaining_views' => 2, 'suspended' => false, 'email_status' => null],
        ], $response['body']);
        self::assertSame('2', self::header($response, 'X-Total-Count'));
        // No code, key or ciphertext: the server has no URL to give back.
        self::assertStringNotContainsString('canary', json_encode($response, JSON_THROW_ON_ERROR));
        self::assertSame([42], $GLOBALS['secureSendApiActivity']);

        // The expired link left with its audit event; exhausted and blocked links stay unlisted, untouched.
        self::assertArrayNotHasKey($expired, DB::$links);
        self::assertSame([['expired', $expired]], array_map(static fn (array $row): array => [$row['event'], $row['send_id']], DB::$secureAudit));
        self::assertArrayHasKey($exhausted, DB::$links);
        self::assertArrayHasKey($blocked, DB::$links);
        self::assertFalse(DB::inTransaction());
    }

    public function testListIsPaginatedAndCountsEveryLink(): void
    {
        $ids = [$this->seedLink(), $this->seedLink(), $this->seedLink()];
        $page = $this->call('list', 'GET', ['limit' => '1', 'offset' => '1']);
        self::assertSame([200, [$ids[1]], '3'], [$page['status'], array_column($page['body'], 'id'), self::header($page, 'X-Total-Count')]);
        self::assertSame([$ids[2], $ids[1]], array_column($this->call('list', 'GET', ['limit' => '2'])['body'], 'id'));

        $GLOBALS['secureSendApiActivity'] = [];
        $beyond = $this->call('list', 'GET', ['offset' => '3']);
        self::assertSame([200, [], '3'], [$beyond['status'], $beyond['body'], self::header($beyond, 'X-Total-Count')]);
        self::assertSame([], $GLOBALS['secureSendApiActivity'], 'an empty page is not user activity');
    }

    public function testListFollowsCurrentAccessAndPolicies(): void
    {
        $item = $this->seedLink();
        $note = $this->seedLink(42, 'note');

        // An item the sender can no longer read makes its link unusable: it is not listed.
        DB::$access = false;
        self::assertSame([$note], array_column($this->call('list', 'GET')['body'], 'id'));
        DB::$access = true;

        // The API opt-in governs creation only: the links stay listed so that they can be revoked.
        $this->settings['secure_send_api_enabled'] = 0;
        self::assertSame([$note, $item], array_column($this->call('list', 'GET')['body'], 'id'));

        // Paused policies keep the owner's links listed, flagged as suspended.
        $this->settings['secure_send_allow_notes'] = 0;
        $this->settings['secure_send_require_passphrase'] = 1;
        $suspended = $this->call('list', 'GET')['body'];
        self::assertSame([[$note, true], [$item, true]], array_map(static fn (array $link): array => [$link['id'], $link['suspended']], $suspended));
    }

    public function testDisabledOneTimeViewLinksNeitherListNorCleanUp(): void
    {
        $this->seedLink();
        $expired = $this->seedLink(42, 'item_v2', ['time_limit' => time() - 10]);
        $this->settings['otv_is_enabled'] = 0;
        $response = $this->call('list', 'GET');
        self::assertSame([200, [], '0'], [$response['status'], $response['body'], self::header($response, 'X-Total-Count')]);
        self::assertArrayHasKey($expired, DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], $GLOBALS['secureSendApiActivity']);
    }

    public function testCleanupFailureDoesNotBlockTheList(): void
    {
        $id = $this->seedLink();
        $this->seedLink(42, 'item_v2', ['time_limit' => time() - 10]);
        DB::$failCleanupQuery = true;
        $response = $this->call('list', 'GET');
        self::assertSame([200, [$id]], [$response['status'], array_column($response['body'], 'id')]);
        self::assertSame([], DB::$secureAudit);
        self::assertFalse(DB::inTransaction());
        self::assertStringContainsString('[API] Secure Send cleanup failed (RuntimeException)', (string) file_get_contents($this->log));
    }

    public function testMalformedListRequestsAreRefused(): void
    {
        $this->seedLink();
        foreach ([['limit' => '0'], ['limit' => '501'], ['offset' => '-1'], ['limit' => 'all'], ['originator' => '43'], ['id' => '1']] as $query) {
            $this->assertRefusal($this->call('list', 'GET', $query), 400, 'invalid_payload');
        }
        foreach (['POST', 'DELETE'] as $method) {
            $response = $this->call('list', $method);
            self::assertSame(405, $response['status'], $method);
            self::assertContains('Allow: GET', $response['headers']);
        }
        self::assertSame([], $GLOBALS['secureSendApiActivity']);
    }

    public function testALinkCreatedListedAndRevokedThroughTheApi(): void
    {
        $created = $this->call('create', 'POST', [], ['type' => 'note', 'note' => self::NOTE, 'views' => 2]);
        $listed = $this->call('list', 'GET')['body'];
        self::assertSame([[$created['body']['id'], 'note', 2, $created['body']['expires_at']]],
            array_map(static fn (array $link): array => [$link['id'], $link['type'], $link['remaining_views'], $link['expires_at']], $listed));
        self::assertSame(200, $this->call('revoke', 'DELETE', ['id' => (string) $listed[0]['id']])['status']);
        self::assertSame([], $this->call('list', 'GET')['body']);
    }

    public function testOwnerRevokesWhateverTheSecureSendSettings(): void
    {
        $variants = [
            'all enabled' => [],
            'one-time view links off' => ['otv_is_enabled' => 0],
            'API opt-in off' => ['secure_send_api_enabled' => 0],
            'note sends off' => ['secure_send_allow_notes' => 0],
        ];
        $base = $this->settings;
        foreach ($variants as $variant => $overrides) {
            foreach (['item_v2', 'note'] as $type) {
                DB::reset();
                $GLOBALS['secureSendApiActivity'] = [];
                $this->settings = $overrides + $base;
                $id = $this->seedLink(42, $type);
                $response = $this->call('revoke', 'DELETE', ['id' => (string) $id]);
                self::assertSame([200, ['id' => $id]], [$response['status'], $response['body']], $variant . ' ' . $type);
                self::assertContains('Content-Type: application/json', $response['headers']);
                self::assertSame([], DB::$links);
                self::assertSame([['revoked', 'sender_revoked', 42, 42]], array_map(
                    static fn (array $row): array => [$row['event'], $row['reason'], $row['actor_id'], $row['originator']],
                    DB::$secureAudit
                ));
                self::assertSame([42], $GLOBALS['secureSendApiActivity']);
                self::assertFalse(DB::inTransaction());
            }
        }
    }

    public function testALinkCreatedThroughTheApiIsRevokedThroughTheApi(): void
    {
        $created = $this->call('create', 'POST', [], ['type' => 'note', 'note' => self::NOTE]);
        self::assertSame(201, $created['status']);
        $revoked = $this->call('revoke', 'DELETE', ['id' => (string) $created['body']['id']]);
        self::assertSame([200, ['id' => $created['body']['id']]], [$revoked['status'], $revoked['body']]);
        self::assertSame(['created', 'revoked'], array_column(DB::$secureAudit, 'event'));
        self::assertNotSame('', $this->reveal($created['body']['url'])['error']);
        // A retry after a lost response finds nothing left to revoke.
        $this->assertRefusal($this->call('revoke', 'DELETE', ['id' => (string) $created['body']['id']]), 404, 'not_found');
        self::assertSame(['created', 'revoked'], array_column(DB::$secureAudit, 'event'));
    }

    public function testForeignUnknownAndMalformedRevocationsChangeNothing(): void
    {
        $id = $this->seedLink(42);
        $link = DB::$links[$id];

        // Another user's link and a missing link are indistinguishable.
        $foreign = $this->call('revoke', 'DELETE', ['id' => (string) $id], null, [], ['id' => 43] + self::USER);
        $unknown = $this->call('revoke', 'DELETE', ['id' => '999']);
        $this->assertRefusal($foreign, 404, 'not_found');
        self::assertSame('Secure Send link not found', $foreign['body']['detail']);
        self::assertSame($foreign, $unknown);

        $malformed = [
            'no id' => [[], null, []],
            'non-numeric id' => [['id' => 'abc'], null, []],
            'zero id' => [['id' => '0'], null, []],
            'id above the column' => [['id' => '2147483648'], null, []],
            'owner in the request' => [['id' => (string) $id, 'originator' => '43'], null, []],
            'id in a JSON body' => [[], ['id' => $id], []],
            'Idempotency-Key header' => [['id' => (string) $id], null, ['Idempotency-Key' => 'retry-1']],
        ];
        foreach ($malformed as $case => [$query, $body, $headers]) {
            $this->assertRefusal($this->call('revoke', 'DELETE', $query, $body, $headers), 400, 'invalid_payload');
        }
        foreach (['GET', 'POST', 'PUT'] as $method) {
            $response = $this->call('revoke', $method, ['id' => (string) $id]);
            self::assertSame(405, $response['status'], $method);
            self::assertContains('Allow: DELETE', $response['headers']);
        }

        self::assertSame([$id => $link], DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], $GLOBALS['secureSendApiActivity']);
        self::assertFalse(DB::inTransaction());
    }

    public function testRevocationFailureKeepsTheLinkAndLogsOnlyTheExceptionClass(): void
    {
        $id = $this->seedLink(42);
        DB::$failSecureAudit = true;
        $response = $this->call('revoke', 'DELETE', ['id' => (string) $id]);
        $this->assertRefusal($response, 500, 'server_error');
        self::assertSame('An internal error occurred. Please contact support.', $response['body']['detail']);
        self::assertArrayHasKey($id, DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], $GLOBALS['secureSendApiActivity']);
        self::assertFalse(DB::inTransaction());
        self::assertStringContainsString('[API] Secure Send revocation failed (RuntimeException)', (string) file_get_contents($this->log));
    }
}
