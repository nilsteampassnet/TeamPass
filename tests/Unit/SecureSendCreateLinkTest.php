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
 * @file      SecureSendCreateLinkTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Session-free creation shared by the web handler and the REST API.
 *
 * Links are created with real Defuse encryption and redeemed through the production
 * recipient path. Email delivery runs against SQL in SecureSendEmailTest.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SecureSendCreateLinkTest extends TestCase
{
    private const SENDER = ['id' => 42, 'private_key' => 'private', 'public_key' => 'public', 'read_only' => false];

    private array $settings;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../app/sources/otp.functions.php';
        require_once __DIR__ . '/../Fixtures/secure_send_memory_db.php';
        require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
        require_once __DIR__ . '/../../app/sources/secure_send.functions.php';
        require_once __DIR__ . '/../../app/sources/secure_send_create.php';
        DB::reset();
        $this->settings = ['otv_is_enabled' => 1, 'cpassman_url' => 'https://vault.example.com',
            'otv_subdomain' => 'https://share.example.com/vault', 'otv_expiration_period' => 7,
            'secure_send_max_views' => 5, 'secure_send_allow_notes' => 1];
    }

    /** Return the created link, or the refusal code. */
    private function create(array $input, array $sender = self::SENDER): array|string
    {
        try {
            return secureSendCreateLink($input, $sender, $this->settings);
        } catch (SecureSendCreateRefusal $e) {
            return $e->getMessage();
        }
    }

    /** Redeem a created link through the production recipient path. */
    private function reveal(string $url, string $passphrase = ''): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $parameters = secureSendRequestParameters($query);
        self::assertIsArray($parameters);
        return secureSendRedeem($parameters, $passphrase, $this->settings, (string) parse_url($url, PHP_URL_HOST));
    }

    private function assertNothingCreated(): void
    {
        self::assertSame([], DB::$links);
        self::assertSame([], DB::$secureAudit);
        self::assertFalse(DB::inTransaction());
    }

    public function testItemSendReturnsTheApiContractAndRedeemsTheSnapshot(): void
    {
        $before = time();
        $result = $this->create(['id' => 123, 'days' => 3, 'views' => 2]);
        self::assertIsArray($result);
        self::assertSame(['url', 'otv_id', 'send_type', 'item_id', 'has_passphrase', 'description_truncated',
            'totp_included', 'email_queued', 'expires_at', 'max_views', 'public_address'], array_keys($result));
        self::assertSame(1, $result['otv_id']);
        self::assertSame('item', $result['send_type']);
        self::assertSame(123, $result['item_id']);
        self::assertSame(0, $result['has_passphrase']);
        self::assertFalse($result['description_truncated']);
        self::assertFalse($result['totp_included']);
        self::assertFalse($result['email_queued']);
        self::assertFalse($result['public_address']);
        self::assertSame(2, $result['max_views']);
        self::assertGreaterThanOrEqual($before + 3 * 86400, $result['expires_at']);
        self::assertLessThanOrEqual(time() + 3 * 86400, $result['expires_at']);
        self::assertStringStartsWith('https://vault.example.com/index.php?otv=1&code=', $result['url']);

        $link = DB::$links[1];
        self::assertSame(['item_v2', 123, 42, 2, $result['expires_at'], 0],
            [$link['send_type'], $link['item_id'], $link['originator'], $link['max_views'], $link['time_limit'], $link['shared_globaly']]);
        // Only the wrapped key is stored: the URL secret exists nowhere else.
        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);
        self::assertStringNotContainsString($query['key'], json_encode($link, JSON_THROW_ON_ERROR));
        self::assertSame([['created', 42]], array_map(static fn (array $row): array => [$row['event'], $row['actor_id']], DB::$secureAudit));

        $revealed = $this->reveal($result['url']);
        self::assertSame('', $revealed['error']);
        self::assertSame('secret-at-creation', $revealed['fields']['password']);
        self::assertSame('Original label', $revealed['fields']['label']);
        self::assertSame(1, $revealed['remaining_views']);
    }

    public function testLimitsAreClampedToTheAdministratorPolicy(): void
    {
        $result = $this->create(['id' => 123, 'days' => 99, 'views' => 99]);
        self::assertSame(5, $result['max_views']);
        self::assertLessThanOrEqual(time() + 7 * 86400, $result['expires_at']);
        self::assertGreaterThan(time() + 6 * 86400, $result['expires_at']);
        DB::reset();
        $default = $this->create(['id' => 123]);
        self::assertSame(1, $default['max_views']);
        self::assertGreaterThan(time() + 6 * 86400, $default['expires_at']);
    }

    public function testDisabledFeatureSendersAndInaccessibleItemsAreRefused(): void
    {
        $this->settings['otv_is_enabled'] = 0;
        self::assertSame('not_allowed', $this->create(['id' => 123]));
        $this->settings['otv_is_enabled'] = 1;
        self::assertSame('not_allowed', $this->create(['id' => 123], ['id' => 0] + self::SENDER));
        self::assertSame('not_allowed', $this->create(['id' => 999]));
        DB::$admin = true;
        self::assertSame('not_allowed', $this->create(['id' => 123]));
        DB::$admin = false;
        DB::$access = false;
        self::assertSame('not_allowed', $this->create(['id' => 123]));
        DB::$access = true;
        DB::$activeUser = false;
        self::assertSame('not_allowed', $this->create(['id' => 123]));
        $this->assertNothingCreated();
    }

    public function testTotpIsIncludedOnlyOnExplicitOptInFromAnEligibleSender(): void
    {
        DB::$otp = ['secret' => cryption('JBSWY3DPEHPK3PXP', '', 'encrypt')['string'],
            'algorithm' => 'sha1', 'digits' => 6, 'period' => 30];
        $readOnly = ['read_only' => true] + self::SENDER;
        $missing = self::SENDER;
        unset($missing['read_only']);
        foreach ([$readOnly, $missing] as $sender) {
            $result = $this->create(['id' => 123, 'include_totp' => 1], $sender);
            self::assertFalse($result['totp_included']);
        }
        // The web payload carries an integer; the API adapter normalizes booleans before calling.
        self::assertFalse($this->create(['id' => 123, 'include_totp' => true])['totp_included']);
        self::assertFalse($this->create(['id' => 123])['totp_included']);
        self::assertSame(0, DB::$otpQueries, 'The TOTP secret must not even be read');

        $included = $this->create(['id' => 123, 'include_totp' => 1]);
        self::assertTrue($included['totp_included']);
        self::assertSame(1, DB::$otpQueries);
        $revealed = $this->reveal($included['url']);
        self::assertSame('', $revealed['error']);
        self::assertMatchesRegularExpression('/^\d{6}$/', $revealed['fields']['otp_code']);
        self::assertArrayNotHasKey('totp', $revealed['fields']);
    }

    public function testDecryptionFailuresCreateNothing(): void
    {
        DB::$otp = ['secret' => 'not-a-ciphertext', 'algorithm' => 'sha1', 'digits' => 6, 'period' => 30];
        self::assertSame('totp_unusable', $this->create(['id' => 123, 'include_totp' => 1]));
        DB::$otp = null;
        DB::$hasSharekey = false;
        self::assertSame('cannot_decrypt', $this->create(['id' => 123]));
        DB::$hasSharekey = true;
        DB::$password = '';
        self::assertSame('cannot_decrypt', $this->create(['id' => 123]));
        $this->assertNothingCreated();
    }

    public function testPassphrasePolicyAndRecipientPrompt(): void
    {
        $this->settings['secure_send_require_passphrase'] = 1;
        foreach (['', '   '] as $passphrase) {
            self::assertSame('passphrase_required', $this->create(['id' => 123, 'passphrase' => $passphrase]));
        }
        self::assertSame('invalid_payload', $this->create(['id' => 123, 'passphrase' => str_repeat('a', 1025)]));
        $this->assertNothingCreated();

        $result = $this->create(['id' => 123, 'passphrase' => 'correct horse']);
        self::assertSame(1, $result['has_passphrase']);
        self::assertSame(1, DB::$links[1]['has_passphrase']);
        self::assertSame('passphrase_required', $this->reveal($result['url'])['error']);
        self::assertSame('wrong_passphrase', $this->reveal($result['url'], 'wrong')['error']);
        self::assertSame('secret-at-creation', $this->reveal($result['url'], 'correct horse')['fields']['password']);
    }

    public function testInvalidInputNotesPolicyAndEmailAreRefusedBeforeAnyWrite(): void
    {
        foreach ([['send_type' => 'file'], ['id' => [123]], ['days' => ['7']], ['send_type' => 'note', 'payload' => 'text']] as $input) {
            self::assertSame('invalid_payload', $this->create($input));
        }
        self::assertSame('empty_note', $this->create(['send_type' => 'note', 'payload' => ['title' => 'Only a title']]));
        $this->settings['secure_send_allow_notes'] = 0;
        self::assertSame('notes_not_allowed', $this->create(['send_type' => 'note', 'payload' => ['secret' => 'x']]));
        // Email keys on the presence of the field: an empty value is an invalid recipient, not "no email".
        self::assertSame('email_not_allowed', $this->create(['id' => 123, 'recipient_email' => 'alice@example.com']));
        $this->settings['secure_send_allow_email'] = 1;
        foreach (['', 'not-an-address', ['alice@example.com']] as $recipient) {
            self::assertSame('invalid_email', $this->create(['id' => 123, 'recipient_email' => $recipient]));
        }
        $this->assertNothingCreated();
    }

    public function testNoteSendKeepsItsOwnContract(): void
    {
        $result = $this->create(['send_type' => 'note', 'id' => 123, 'payload' => ['title' => ' Wi-Fi ', 'secret' => 'note-secret']]);
        self::assertSame(['note', null, false], [$result['send_type'], $result['item_id'], $result['totp_included']]);
        self::assertSame(['note', null], [DB::$links[1]['send_type'], DB::$links[1]['item_id']]);
        $revealed = $this->reveal($result['url']);
        self::assertSame(['Wi-Fi', 'note-secret'], [$revealed['fields']['title'], $revealed['fields']['secret']]);
    }

    public function testPublicAddressIsOptInAndValidated(): void
    {
        $result = $this->create(['id' => 123, 'shared_globaly' => 1]);
        self::assertTrue($result['public_address']);
        self::assertStringStartsWith('https://share.example.com/vault/index.php?otv=1&', $result['url']);
        self::assertSame(1, DB::$links[1]['shared_globaly']);
        self::assertSame('', $this->reveal($result['url'])['error']);

        DB::reset();
        $this->settings['otv_subdomain'] = 'invalid/path';
        self::assertSame('invalid_public_url', $this->create(['id' => 123, 'shared_globaly' => 1]));
        $this->assertNothingCreated();
        self::assertFalse($this->create(['id' => 123])['public_address']);
    }

    public function testStorageFailureIsGenericAndLogsOnlyTheExceptionClass(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'tp-secure-send-create-');
        $previous = ini_set('error_log', $log);
        try {
            DB::$failSecureAudit = true;
            self::assertSame('server_error', $this->create(['id' => 123, 'passphrase' => 'phrase-canary']));
            $this->assertNothingCreated();
            $diagnostic = (string) file_get_contents($log);
            self::assertStringContainsString('TEAMPASS Secure Send creation failed (RuntimeException)', $diagnostic);
            foreach (['secret-canary', 'phrase-canary', 'secret-at-creation'] as $secret) {
                self::assertStringNotContainsString($secret, $diagnostic);
            }
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($log);
        }
    }

    public function testRefusalMessagesStayWithinTheDeclaredCodes(): void
    {
        foreach (SecureSendCreateRefusal::CODES as $code) {
            self::assertSame($code, (new SecureSendCreateRefusal($code))->getMessage());
        }
        self::assertSame('server_error', (new SecureSendCreateRefusal('Unexpected <b>detail</b>'))->getMessage());
    }
}
