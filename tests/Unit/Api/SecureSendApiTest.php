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
 * Creation itself is secureSendCreateLink(), covered by SecureSendCreateLinkTest.
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
            'enabled' => true, 'item_send_allowed' => true, 'require_passphrase' => false, 'passphrase_max_bytes' => 1024,
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

        $full = secureSendApiPolicy(self::SETTINGS + ['otv_subdomain' => 'https://share.example.com',
            'secure_send_require_passphrase' => 1, 'secure_send_allow_email' => 1, 'email_smtp_server' => 'smtp.example.com',
            'email_from' => 'vault@example.com', 'secure_send_show_sender_name' => 1], $user, 1000);
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
    }

    /** The published passphrase bound is the one secureSendValidateInput() enforces. */
    public function testPassphraseBoundMatchesTheValidation(): void
    {
        $bound = secureSendApiPolicy(self::SETTINGS, [])['passphrase_max_bytes'];
        secureSendValidateInput(['passphrase' => str_repeat('é', intdiv($bound, 2))]);
        $this->expectException(InvalidArgumentException::class);
        secureSendValidateInput(['passphrase' => str_repeat('a', $bound + 1)]);
    }

    public function testCreateBodyForcesItemSendsAndNormalizesFlags(): void
    {
        self::assertSame(['send_type' => 'item', 'id' => 12, 'include_totp' => 0, 'shared_globaly' => 0],
            secureSendApiCreateInput(['item_id' => 12]));
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

    public static function invalidBodies(): array
    {
        return [
            'empty' => [[]], 'list' => [[12]], 'string id' => [['item_id' => '12']], 'zero id' => [['item_id' => 0]],
            'negative id' => [['item_id' => -3]], 'float id' => [['item_id' => 1.5]], 'string days' => [['item_id' => 1, 'days' => '3']],
            'zero views' => [['item_id' => 1, 'views' => 0]], 'bool views' => [['item_id' => 1, 'views' => true]],
            'int passphrase' => [['item_id' => 1, 'passphrase' => 1234]], 'array recipient' => [['item_id' => 1, 'recipient_email' => ['a@example.com']]],
            'string flag' => [['item_id' => 1, 'include_totp' => 'yes']], 'flag 2' => [['item_id' => 1, 'public_address' => 2]],
            'note type' => [['item_id' => 1, 'send_type' => 'note']], 'note payload' => [['item_id' => 1, 'payload' => ['secret' => 'x']]],
            'web id' => [['id' => 1]], 'web public flag' => [['item_id' => 1, 'shared_globaly' => 1]],
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
        self::assertEqualsCanonicalizing(['item_id', 'days', 'views', 'passphrase', 'include_totp', 'public_address', 'recipient_email'],
            array_keys($spec['components']['schemas']['SecureSendCreateBody']['properties']));
    }

    private static function createdResult(): array
    {
        return ['url' => 'https://vault.example.com/index.php?otv=1&code=c&key=k&stamp=1', 'otv_id' => 9, 'send_type' => 'item',
            'item_id' => 12, 'has_passphrase' => 1, 'description_truncated' => false, 'totp_included' => true,
            'email_queued' => false, 'expires_at' => 1795000000, 'max_views' => 2, 'public_address' => false];
    }

    public function testCreatedBodyExposesOnlyTheDocumentedFields(): void
    {
        self::assertSame([
            'id' => 9, 'url' => 'https://vault.example.com/index.php?otv=1&code=c&key=k&stamp=1', 'item_id' => 12,
            'expires_at' => 1795000000, 'max_views' => 2, 'has_passphrase' => true, 'public_address' => false,
            'totp_included' => true, 'description_truncated' => false, 'email_queued' => false,
        ], secureSendApiCreatedBody(self::createdResult()));
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

    public function testOnlyPolicyAndCreateAreRoutedWithTheReadRight(): void
    {
        // The bootstrap cannot be loaded without an instance: run the repository's own function.
        eval($this->functionSource('/app/api/inc/bootstrap.php', 'checkSecureSendCRUDRights'));
        $reader = ['allowed_to_create' => 0, 'allowed_to_read' => 1, 'allowed_to_update' => 0, 'allowed_to_delete' => 0];
        $writer = ['allowed_to_create' => 1, 'allowed_to_read' => 0, 'allowed_to_update' => 1, 'allowed_to_delete' => 1];
        foreach (['policy', 'create'] as $action) {
            self::assertTrue(checkSecureSendCRUDRights($reader, $action), $action);
            self::assertFalse(checkSecureSendCRUDRights($writer, $action), $action);
            self::assertFalse(checkSecureSendCRUDRights([], $action), $action);
        }
        // Inventory and revocation are not part of this version.
        foreach (['list', 'revoke', 'get', 'delete', 'Create', ''] as $action) {
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
        // Logs carry the exception class only, never the message, the body or the link.
        preg_match_all('/error_log\((.*)\);/', $controller, $logs);
        self::assertSame(["'[API] Secure Send creation failed (' . get_class(\$e) . ')'"], $logs[1]);
        foreach (['SecureSendProblem', 'secureSendStoreLink(', 'cryption('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller, $forbidden);
        }
        // Reading the policy is a settings refresh, not user activity.
        $policy = substr($controller, (int) strpos($controller, 'public function policyAction('), (int) strpos($controller, 'public function createAction(') - (int) strpos($controller, 'public function policyAction('));
        self::assertStringNotContainsString('markApiFunctionalActivity', $policy);
    }
}
