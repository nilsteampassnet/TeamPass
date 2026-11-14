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
 * @file      SecureSendApiTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Secure Send REST endpoints: request normalization, policy, refusal mapping and wiring.
 *
 * Creation itself is secureSendCreateLink(), covered by SecureSendCreateLinkTest; the
 * controller runs end to end in SecureSendControllerTest.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SecureSendApiTest extends TestCase
{
    private const SETTINGS = ['otv_is_enabled' => 1, 'secure_send_api_enabled' => 1, 'cpassman_url' => 'https://vault.example.com',
        'otv_expiration_period' => 5, 'secure_send_max_views' => 3];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../app/sources/secure_send_api_logic.php';
    }

    private function source(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../..' . $path));
    }

    /** Extract one top-level function from a source file. */
    private function functionSource(string $path, string $name): string
    {
        $source = $this->source($path);
        $start = strpos($source, 'function ' . $name . '(');
        self::assertIsInt($start, $name);
        return substr($source, $start, strpos($source, "\n}\n", $start) + 2 - $start);
    }

    public function testFeatureGateNeedsTheEngineAndTheApiOptIn(): void
    {
        self::assertTrue(secureSendApiEnabled(self::SETTINGS));
        foreach (['otv_is_enabled', 'secure_send_api_enabled'] as $setting) {
            foreach ([0, '0', null] as $value) {
                $settings = self::SETTINGS;
                $settings[$setting] = $value;
                self::assertFalse(secureSendApiEnabled($settings), $setting);
            }
            $settings = self::SETTINGS;
            unset($settings[$setting]);
            self::assertFalse(secureSendApiEnabled($settings), $setting . ' missing');
        }
        self::assertTrue(secureSendApiEnabled(['otv_is_enabled' => '1', 'secure_send_api_enabled' => '1']));
    }

    public function testPolicyIsDerivedFromTheEnforcedLimits(): void
    {
        $user = ['is_admin' => 0, 'is_read_only' => 0];
        $policy = secureSendApiPolicy(self::SETTINGS, $user, 1000);
        self::assertSame([
            'enabled' => true, 'item_send_allowed' => true, 'note_send_allowed' => false, 'require_passphrase' => false,
            'passphrase_max_bytes' => 1024, 'note_max_bytes' => 16000,
            'days' => ['max' => 5, 'default' => 5], 'views' => ['max' => 3, 'default' => 1],
            'public_address_available' => false, 'totp_allowed' => true,
            'email' => ['allowed' => false, 'configured' => false], 'sender_name_shown' => false,
        ], $policy);
        // The published bounds are the ones creation applies.
        $limits = secureSendLimits(self::SETTINGS, ['days' => 99, 'views' => 99], 1000);
        self::assertSame([$policy['days']['max'], $policy['views']['max']], [$limits['days'], $limits['views']]);

        // Defaults when the administrator never set the limits.
        $bare = secureSendApiPolicy(['otv_is_enabled' => 1], $user, 1000);
        self::assertSame([['max' => 7, 'default' => 7], ['max' => 5, 'default' => 1], false],
            [$bare['days'], $bare['views'], $bare['enabled']]);
        self::assertFalse($bare['item_send_allowed']);
        self::assertFalse(secureSendApiPolicy(['otv_is_enabled' => 1, 'secure_send_allow_notes' => 1], $user, 1000)['note_send_allowed']);

        $full = secureSendApiPolicy(self::SETTINGS + ['otv_subdomain' => 'https://share.example.com',
            'secure_send_require_passphrase' => 1, 'secure_send_allow_email' => 1, 'email_smtp_server' => 'smtp.example.com',
            'email_from' => 'vault@example.com', 'secure_send_show_sender_name' => 1], $user, 1000);
        self::assertTrue(secureSendApiPolicy(self::SETTINGS + ['secure_send_allow_notes' => 1], $user, 1000)['note_send_allowed']);
        self::assertTrue($full['public_address_available']);
        self::assertTrue($full['require_passphrase']);
        self::assertSame(['allowed' => true, 'configured' => true], $full['email']);
        self::assertTrue($full['sender_name_shown']);
        self::assertFalse(secureSendApiPolicy(self::SETTINGS + ['otv_subdomain' => 'invalid/path'], $user, 1000)['public_address_available']);
    }

    public function testPolicyFollowsTheCallerAccount(): void
    {
        self::assertFalse(secureSendApiPolicy(self::SETTINGS, ['is_admin' => 1, 'is_read_only' => 0])['item_send_allowed']);
        self::assertTrue(secureSendApiPolicy(self::SETTINGS, ['is_admin' => 1, 'is_read_only' => 0])['enabled']);
        self::assertFalse(secureSendApiPolicy(self::SETTINGS, ['is_admin' => 0, 'is_read_only' => 1])['totp_allowed']);
        // Unknown read-only state fails closed.
        self::assertFalse(secureSendApiPolicy(self::SETTINGS, ['is_admin' => 0])['totp_allowed']);
        // A note is bound to no item: like the web, administrators and read-only accounts may send one.
        foreach ([['is_admin' => 1, 'is_read_only' => 0], ['is_admin' => 0, 'is_read_only' => 1], []] as $account) {
            self::assertTrue(secureSendApiPolicy(self::SETTINGS + ['secure_send_allow_notes' => '1'], $account)['note_send_allowed']);
        }
    }

    /** The published passphrase bound is the one secureSendValidateInput() enforces. */
    public function testPassphraseBoundMatchesTheValidation(): void
    {
        $bound = secureSendApiPolicy(self::SETTINGS, [])['passphrase_max_bytes'];
        secureSendValidateInput(['passphrase' => str_repeat('é', intdiv($bound, 2))]);
        $this->expectException(InvalidArgumentException::class);
        secureSendValidateInput(['passphrase' => str_repeat('a', $bound + 1)]);
    }

    public function testItemCreateBodyIsTheDefaultAndNormalizesFlags(): void
    {
        self::assertSame(['send_type' => 'item', 'id' => 12, 'include_totp' => 0, 'shared_globaly' => 0],
            secureSendApiCreateInput(['item_id' => 12]));
        self::assertSame(secureSendApiCreateInput(['item_id' => 12]), secureSendApiCreateInput(['type' => 'item', 'item_id' => 12]));
        self::assertSame(secureSendApiCreateInput(['item_id' => 12]), secureSendApiCreateInput(['type' => null, 'item_id' => 12, 'note' => null]));
        self::assertSame([
            'send_type' => 'item', 'id' => 12, 'days' => 3, 'views' => 2, 'passphrase' => 'correct horse',
            'recipient_email' => 'alice@example.com', 'include_totp' => 1, 'shared_globaly' => 1,
        ], secureSendApiCreateInput(['item_id' => 12, 'days' => 3, 'views' => 2, 'passphrase' => 'correct horse',
            'include_totp' => true, 'public_address' => 1, 'recipient_email' => 'alice@example.com']));
        foreach ([[false, 0], [0, 0], [true, 1], [1, 1]] as [$flag, $expected]) {
            $input = secureSendApiCreateInput(['item_id' => 1, 'include_totp' => $flag, 'public_address' => $flag]);
            // secureSendShouldIncludeTotp() compares with === 1.
            self::assertSame([$expected, $expected], [$input['include_totp'], $input['shared_globaly']]);
        }
        // null = omitted; an empty recipient is present and left to the orchestrator (invalid_email).
        $input = secureSendApiCreateInput(['item_id' => 1, 'days' => null, 'passphrase' => null, 'recipient_email' => null, 'include_totp' => null]);
        self::assertSame(['send_type' => 'item', 'id' => 1, 'include_totp' => 0, 'shared_globaly' => 0], $input);
        self::assertArrayHasKey('recipient_email', secureSendApiCreateInput(['item_id' => 1, 'recipient_email' => '']));
    }

    public function testNoteCreateBodyBecomesTheOrchestratorPayload(): void
    {
        self::assertSame(['send_type' => 'note', 'payload' => ['title' => ' Wi-Fi ', 'secret' => 's3cret'], 'shared_globaly' => 0],
            secureSendApiCreateInput(['type' => 'note', 'note' => ['title' => ' Wi-Fi ', 'secret' => 's3cret']]));
        self::assertSame([
            'send_type' => 'note',
            'payload' => ['title' => 't', 'secret' => 's', 'note' => "a\nb", 'login' => 'l', 'url' => 'https://example.com'],
            'days' => 2, 'views' => 4, 'passphrase' => 'correct horse', 'recipient_email' => 'alice@example.com', 'shared_globaly' => 1,
        ], secureSendApiCreateInput(['type' => 'note', 'note' => ['title' => 't', 'secret' => 's', 'note' => "a\nb", 'login' => 'l',
            'url' => 'https://example.com'], 'days' => 2, 'views' => 4, 'passphrase' => 'correct horse', 'public_address' => true,
            'recipient_email' => 'alice@example.com']));
        // null = omitted, inside the note too; item-only members may be null.
        self::assertSame(['send_type' => 'note', 'payload' => ['note' => 'text'], 'shared_globaly' => 0],
            secureSendApiCreateInput(['type' => 'note', 'item_id' => null, 'include_totp' => null,
                'note' => ['title' => null, 'note' => 'text']]));
        // Emptiness is the orchestrator's decision (empty_note), for {} as for blank members.
        self::assertSame([], secureSendApiCreateInput(['type' => 'note', 'note' => []])['payload']);
        self::assertSame(['secret' => ' '], secureSendApiCreateInput(['type' => 'note', 'note' => ['secret' => ' ']])['payload']);
        // A note never reaches the item branch of the orchestrator.
        $input = secureSendApiCreateInput(['type' => 'note', 'note' => ['secret' => 'x']]);
        self::assertArrayNotHasKey('id', $input);
        self::assertArrayNotHasKey('include_totp', $input);
        secureSendValidateInput($input);
    }

    /** The bound counts UTF-8 bytes of the five members together. */
    public function testNoteBoundCountsBytesAcrossMembers(): void
    {
        $max = secureSendApiNoteMaxBytes();
        self::assertSame($max, secureSendApiPolicy(self::SETTINGS, [])['note_max_bytes']);
        $fits = ['title' => str_repeat('t', 1000), 'secret' => str_repeat('s', 4000), 'note' => str_repeat('n', $max - 6000),
            'login' => str_repeat('l', 500), 'url' => str_repeat('u', 500)];
        self::assertSame($fits, secureSendApiCreateInput(['type' => 'note', 'note' => $fits])['payload']);
        self::assertSame($max, strlen(str_repeat('é', intdiv($max, 2))));
        secureSendApiCreateInput(['type' => 'note', 'note' => ['secret' => str_repeat('é', intdiv($max, 2))]]);
        foreach ([['secret' => str_repeat('é', intdiv($max, 2) + 1)], array_replace($fits, ['url' => $fits['url'] . 'x'])] as $note) {
            try {
                secureSendApiCreateInput(['type' => 'note', 'note' => $note]);
                self::fail('oversized note accepted');
            } catch (InvalidArgumentException $e) {
                self::assertSame('The note exceeds ' . $max . ' bytes (policy note_max_bytes)', $e->getMessage());
            }
        }
    }

    public static function invalidBodies(): array
    {
        return [
            'empty' => [[]], 'list' => [[12]], 'string id' => [['item_id' => '12']], 'zero id' => [['item_id' => 0]],
            'negative id' => [['item_id' => -3]], 'float id' => [['item_id' => 1.5]], 'string days' => [['item_id' => 1, 'days' => '3']],
            'zero views' => [['item_id' => 1, 'views' => 0]], 'bool views' => [['item_id' => 1, 'views' => true]],
            'int passphrase' => [['item_id' => 1, 'passphrase' => 1234]], 'array recipient' => [['item_id' => 1, 'recipient_email' => ['a@example.com']]],
            'string flag' => [['item_id' => 1, 'include_totp' => 'yes']], 'flag 2' => [['item_id' => 1, 'public_address' => 2]],
            'web note type' => [['item_id' => 1, 'send_type' => 'note']], 'web note payload' => [['item_id' => 1, 'payload' => ['secret' => 'x']]],
            'web id' => [['id' => 1]], 'web public flag' => [['item_id' => 1, 'shared_globaly' => 1]],
            'unknown type' => [['type' => 'file', 'item_id' => 1]], 'int type' => [['type' => 1, 'item_id' => 1]],
            'bool type' => [['type' => true, 'note' => ['secret' => 'x']]], 'capitalized type' => [['type' => 'Note', 'note' => ['secret' => 'x']]],
            'item with a note' => [['item_id' => 1, 'note' => ['secret' => 'x']]],
            'item with an empty note' => [['type' => 'item', 'item_id' => 1, 'note' => []]],
            'note without content' => [['type' => 'note']], 'note as a string' => [['type' => 'note', 'note' => 'secret']],
            'note as a list' => [['type' => 'note', 'note' => ['a', 'b']]],
            'note with an item' => [['type' => 'note', 'item_id' => 1, 'note' => ['secret' => 'x']]],
            'note with a TOTP flag' => [['type' => 'note', 'include_totp' => false, 'note' => ['secret' => 'x']]],
            'note unknown member' => [['type' => 'note', 'note' => ['password' => 'x']]],
            'note web payload member' => [['type' => 'note', 'note' => ['payload' => ['secret' => 'x']]]],
            'note int member' => [['type' => 'note', 'note' => ['secret' => 1234]]],
            'note bool member' => [['type' => 'note', 'note' => ['secret' => 'x', 'login' => false]]],
            'note nested member' => [['type' => 'note', 'note' => ['note' => ['x']]]],
            'note too large' => [['type' => 'note', 'note' => ['secret' => str_repeat('x', 16001)]]],
            'note string flag' => [['type' => 'note', 'note' => ['secret' => 'x'], 'public_address' => 'yes']],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidCreateBodiesAreRefused(array $body): void
    {
        $this->expectException(InvalidArgumentException::class);
        secureSendApiCreateInput($body);
    }

    /** Every reason the orchestrator or the adapter can raise has a status, a detail and is documented. */
    public function testEveryRefusalReasonIsMappedAndDocumented(): void
    {
        require_once __DIR__ . '/../../../app/sources/secure_send_create.php';
        $statuses = secureSendApiRefusalStatuses();
        $reasons = array_merge(SecureSendCreateRefusal::CODES, ['secure_send_disabled', 'not_found']);
        self::assertEqualsCanonicalizing($reasons, array_keys($statuses));
        $generic = secureSendApiRefusalDetail('server_error');
        foreach ($reasons as $reason) {
            if ($reason !== 'server_error') {
                self::assertNotSame($generic, secureSendApiRefusalDetail($reason), $reason);
            }
        }
        self::assertSame(500, $statuses['server_error']);
        self::assertSame(503, $statuses['secure_send_disabled']);
        self::assertSame(422, $statuses['cannot_decrypt']);
        self::assertSame(429, $statuses['email_rate_limited']);
        foreach ($statuses as $reason => $status) {
            self::assertContains($status, [400, 403, 404, 422, 429, 500, 503], $reason);
        }

        $spec = json_decode($this->source('/app/api/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
        $documented = $spec['components']['schemas']['SecureSendProblem']['allOf'][1]['properties']['reason']['enum'];
        self::assertEqualsCanonicalizing(array_keys($statuses), $documented);
        $responses = $spec['paths']['/secureSend/create']['post']['responses'];
        foreach ($statuses as $reason => $status) {
            self::assertArrayHasKey((string) $status, $responses, $reason);
        }
        self::assertSame('#/components/schemas/SecureSendCreateBody',
            $spec['paths']['/secureSend/create']['post']['requestBody']['content']['application/json']['schema']['$ref']);
        self::assertSame(array_keys(secureSendApiPolicy(self::SETTINGS, [])), $spec['components']['schemas']['SecureSendPolicy']['required']);
        self::assertSame(array_keys(secureSendApiCreatedBody(self::createdResult())), $spec['components']['schemas']['SecureSendCreated']['required']);
        self::assertEqualsCanonicalizing(['type', 'item_id', 'note', 'days', 'views', 'passphrase', 'include_totp', 'public_address',
            'recipient_email'], array_keys($spec['components']['schemas']['SecureSendCreateBody']['properties']));
        // The documented note members are the ones the orchestrator stores.
        $note = $spec['components']['schemas']['SecureSendNote'];
        self::assertSame(['title', 'secret', 'note', 'login', 'url'], array_keys($note['properties']));
        self::assertFalse($note['additionalProperties']);
        self::assertSame(secureSendApiNoteMaxBytes(), $spec['components']['schemas']['SecureSendPolicy']['properties']['note_max_bytes']['const']);
        // Item and note links share one documented shape.
        self::assertSame(array_keys(secureSendApiCreatedBody(self::createdResult())), array_keys(secureSendApiCreatedBody(self::createdResult(true))));
        self::assertSame(['integer', 'null'], $spec['components']['schemas']['SecureSendCreated']['properties']['item_id']['type']);

        // Revocation answers with the same problem envelope and reasons.
        $revoke = $spec['paths']['/secureSend/revoke']['delete'];
        foreach ([400 => 'invalid_payload', 404 => 'not_found', 500 => 'server_error'] as $status => $reason) {
            self::assertSame($statuses[$reason], $status, $reason);
            self::assertSame('#/components/schemas/SecureSendProblem',
                $revoke['responses'][$status]['content']['application/problem+json']['schema']['$ref'], $reason);
        }
        self::assertSame('#/components/schemas/SecureSendRevoked', $revoke['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame(['id'], $spec['components']['schemas']['SecureSendRevoked']['required']);
        self::assertSame([['id', 'query', true]], array_map(static fn (array $p): array => [$p['name'], $p['in'], $p['required']], $revoke['parameters']));
        self::assertArrayNotHasKey('requestBody', $revoke);
    }

    private static function createdResult(bool $note = false): array
    {
        return ['url' => 'https://vault.example.com/index.php?otv=1&code=c&key=k&stamp=1', 'otv_id' => 9,
            'send_type' => $note ? 'note' : 'item', 'item_id' => $note ? null : 12, 'has_passphrase' => 1,
            'description_truncated' => false, 'totp_included' => !$note, 'email_queued' => false, 'expires_at' => 1795000000,
            'max_views' => 2, 'public_address' => false];
    }

    public function testCreatedBodyExposesOnlyTheDocumentedFields(): void
    {
        self::assertSame([
            'id' => 9, 'type' => 'item', 'url' => 'https://vault.example.com/index.php?otv=1&code=c&key=k&stamp=1', 'item_id' => 12,
            'expires_at' => 1795000000, 'max_views' => 2, 'has_passphrase' => true, 'public_address' => false,
            'totp_included' => true, 'description_truncated' => false, 'email_queued' => false,
        ], secureSendApiCreatedBody(self::createdResult()));
        $note = secureSendApiCreatedBody(self::createdResult(true));
        self::assertSame(['note', null, false], [$note['type'], $note['item_id'], $note['totp_included']]);
    }

    public static function revocationQueries(): array
    {
        return [
            'none' => [[]], 'empty' => [['id' => '']], 'zero' => [['id' => '0']], 'negative' => [['id' => '-1']],
            'leading zero' => [['id' => '01']], 'decimal' => [['id' => '1.5']], 'exponent' => [['id' => '1e3']],
            'hex' => [['id' => '0x1f']], 'spaces' => [['id' => ' 5']], 'trailing space' => [['id' => '5 ']],
            'array' => [['id' => ['5']]], 'above int' => [['id' => '2147483648']], 'eleven digits' => [['id' => '99999999999']],
            'integer type' => [['id' => 5]], 'owner' => [['id' => '5', 'originator' => '42']], 'item id' => [['item_id' => '5']],
            'web payload' => [['id' => '5', 'data' => 'x']],
        ];
    }

    #[DataProvider('revocationQueries')]
    public function testInvalidRevocationQueriesAreRefused(array $query): void
    {
        $this->expectException(InvalidArgumentException::class);
        secureSendApiRevokeId($query);
    }

    public function testRevocationIdentifierIsAPositiveColumnValue(): void
    {
        self::assertSame(5, secureSendApiRevokeId(['id' => '5']));
        self::assertSame(2147483647, secureSendApiRevokeId(['id' => '2147483647']));
    }

    /** Extension members are added after, and never override, the RFC 9457 members. */
    public function testProblemResponsesCarryTheReasonWithoutOverridingStandardMembers(): void
    {
        require_once __DIR__ . '/../../../app/api/Controller/Api/BaseController.php';
        $controller = new class extends BaseController {
            public string $body = '';
            public function problem(int $status, string $detail, array $members): void { $this->sendProblem($status, $detail, [], $members); }
            protected function sendOutput($data, array $httpHeaders = []): void { $this->body = (string) $data; }
        };
        $controller->problem(429, 'Too many email invitations, retry later', ['reason' => 'email_rate_limited', 'status' => 200, 'detail' => 'x']);
        self::assertSame(['type' => 'about:blank', 'title' => 'Too Many Requests', 'status' => 429,
            'detail' => 'Too many email invitations, retry later', 'error' => 'Too many email invitations, retry later',
            'reason' => 'email_rate_limited'], json_decode($controller->body, true, 512, JSON_THROW_ON_ERROR));
        $controller->problem(404, 'Unknown route', []);
        self::assertArrayNotHasKey('reason', json_decode($controller->body, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testOnlyPolicyCreateAndRevokeAreRoutedWithTheReadRight(): void
    {
        // The bootstrap cannot be loaded without an instance: run the repository's own function.
        eval($this->functionSource('/app/api/inc/bootstrap.php', 'checkSecureSendCRUDRights'));
        $reader = ['allowed_to_create' => 0, 'allowed_to_read' => 1, 'allowed_to_update' => 0, 'allowed_to_delete' => 0];
        $writer = ['allowed_to_create' => 1, 'allowed_to_read' => 0, 'allowed_to_update' => 1, 'allowed_to_delete' => 1];
        // Whoever may create a link may revoke it: the delete right is about items.
        foreach (['policy', 'create', 'revoke'] as $action) {
            self::assertTrue(checkSecureSendCRUDRights($reader, $action), $action);
            self::assertFalse(checkSecureSendCRUDRights($writer, $action), $action);
            self::assertFalse(checkSecureSendCRUDRights([], $action), $action);
        }
        // The inventory is not part of this version.
        foreach (['list', 'get', 'delete', 'Create', 'Revoke', ''] as $action) {
            self::assertFalse(checkSecureSendCRUDRights($reader + $writer, $action), $action);
        }
        $wrapper = $this->functionSource('/app/api/inc/bootstrap.php', 'secureSendAction');
        self::assertLessThan(strpos($wrapper, 'SecureSendController.php'), strpos($wrapper, 'checkSecureSendCRUDRights('));
    }

    public function testRouterRevalidatesTheReadOnlyFlagOnEveryRequest(): void
    {
        $router = $this->source('/app/api/index.php');
        self::assertStringContainsString("\$controller === 'secureSend'", $router);
        self::assertStringContainsString('u.read_only,', $router);
        self::assertStringContainsString("\$userData['data']['is_read_only'] = (int) \$freshUser['read_only'];", $router);
        // The override sits with the other per-request claims, before any controller runs.
        self::assertLessThan(strpos($router, "\$controller === 'secureSend'"), strpos($router, "['is_read_only']"));
    }

    /** Source-level guarantees of the controller that need a live instance to exercise. */
    public function testCreateActionKeepsTheBearerSecretOutOfCachesLogsAndReplays(): void
    {
        $controller = $this->source('/app/api/Controller/Api/SecureSendController.php');
        $create = substr($controller, (int) strpos($controller, 'public function createAction('));
        $create = substr($create, 0, (int) strpos($create, "\n    }\n"));
        // Every answer of the endpoint, refusals included, is no-store.
        self::assertLessThan(strpos($create, 'requireMethod('), strpos($create, "header('Cache-Control: no-store');"));
        self::assertStringContainsString("header('Pragma: no-cache');", $create);
        self::assertStringContainsString("headers->has('Idempotency-Key')", $create);
        self::assertStringContainsString('getQueryString()', $create);
        self::assertStringNotContainsString('ApiIdempotency', $controller);
        // Feature gate first, then visibility, keys, and the shared orchestrator.
        $order = ['secureSendApiEnabled(', 'canAccessItemInFolder(', 'satisfiesItemRestriction(', 'get_user_keys(',
            'secureSendCreateLink(', 'markApiFunctionalActivity(', 'secureSendApiCreatedBody('];
        $positions = array_map(static fn (string $needle): int|false => strpos($create, $needle), $order);
        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);
        self::assertStringContainsString("'read_only' => (int) (\$userData['is_read_only'] ?? 1) === 1", $create);
        // The vault is reached only for item sends: a note never unwraps the private key.
        $itemBranch = strpos($create, "if (\$input['send_type'] === 'item') {");
        self::assertIsInt($itemBranch);
        foreach (['loadClasses(', 'canAccessItemInFolder(', 'get_user_keys('] as $needle) {
            self::assertGreaterThan($itemBranch, strpos($create, $needle), $needle);
        }
        self::assertStringContainsString("\$userKeys = ['private_key' => '', 'public_key' => ''];", $create);
        // Logs carry the exception class only, never the message, the body or the link.
        preg_match_all('/error_log\((.*)\);/', $controller, $logs);
        self::assertSame(["'[API] Secure Send creation failed (' . get_class(\$e) . ')'",
            "'[API] Secure Send revocation failed (' . get_class(\$e) . ')'"], $logs[1]);
        foreach (['SecureSendProblem', 'secureSendStoreLink(', 'cryption('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller, $forbidden);
        }
        // Revocation: owner from the token, ownership and audit in secureSendRevokeLink(), no feature gate.
        $revoke = substr($controller, (int) strpos($controller, 'public function revokeAction('));
        $revoke = substr($revoke, 0, (int) strpos($revoke, "\n    }\n"));
        self::assertStringContainsString("secureSendRevokeLink(\$sendId, (int) \$userData['id'], \$this->settings())", $revoke);
        self::assertStringContainsString("headers->has('Idempotency-Key')", $revoke);
        foreach (['secureSendApiEnabled(', 'DB::', 'otv_is_enabled', 'getQueryStringParams('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $revoke, $forbidden);
        }
        self::assertLessThan(strpos($revoke, 'markApiFunctionalActivity('), strpos($revoke, 'if ($revoked === false)'));
        // Reading the policy is a settings refresh, not user activity.
        $policy = substr($controller, (int) strpos($controller, 'public function policyAction('), (int) strpos($controller, 'public function createAction(') - (int) strpos($controller, 'public function policyAction('));
        self::assertStringNotContainsString('markApiFunctionalActivity', $policy);
    }
}
