<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/secret_settings_logic.php';
require_once dirname(__DIR__, 2) . '/app/sources/emails_transport_logic.php';

final class EmailTransportConfigurationTest extends TestCase
{
    private function smtp(): array
    {
        return ['email_transport' => 'smtp', 'email_smtp_server' => 'relay.internal', 'email_port' => '25',
            'email_from' => 'sender@example.test', 'email_auth_pwd' => 'def50200aabb', 'email_config_revision' => str_repeat('a', 32)];
    }

    private function encrypt(string $secret): string { return 'def50200' . bin2hex($secret); }

    /** A legacy relay keeps its credentials and defaults until its complete profile is saved. */
    public function testLegacyRelayRemainsCompatibleAndGetsANewRevision(): void
    {
        $existing = $this->smtp() + ['email_smtp_auth' => '0'];
        $candidate = emailTransportCandidate(['email_from_name' => 'R&D O\'Brien "quoted" <label>'], $existing, $this->encrypt(...));
        self::assertSame('none', $candidate['email_smtp_auth_mode']);
        self::assertSame('relay.internal', $candidate['email_smtp_server']);
        self::assertSame($existing['email_auth_pwd'], $candidate['email_auth_pwd']);
        self::assertNotSame($existing['email_config_revision'], $candidate['email_config_revision']);
        self::assertSame('R&D O\'Brien "quoted" <label>', $candidate['email_from_name']);
    }

    /** Secrets are preserved, encrypted before persistence, or explicitly cleared. */
    public function testSecretActionsAndLegacyPlaintextMigration(): void
    {
        $existing = $this->smtp();
        $existing['email_auth_pwd'] = 'legacy-private';
        $kept = emailTransportCandidate([], $existing, $this->encrypt(...));
        self::assertSame($this->encrypt('legacy-private'), $kept['email_auth_pwd']);
        $replaced = emailTransportCandidate(['email_auth_pwd' => 'new-private', 'secret_actions' => ['email_auth_pwd' => 'replace']], $existing, $this->encrypt(...));
        self::assertSame($this->encrypt('new-private'), $replaced['email_auth_pwd']);
        $cleared = emailTransportCandidate(['secret_actions' => ['email_auth_pwd' => 'clear']], $existing, $this->encrypt(...));
        self::assertSame('', $cleared['email_auth_pwd']);
    }

    /** @return array<string,array{array}> */
    public static function invalidProfiles(): array
    {
        return [
            'unknown setting' => [['ldap_password' => 'secret']],
            'debug is not a profile field' => [['email_debug_level' => '2']],
            'revision injection' => [['email_config_revision' => str_repeat('b', 32)]],
            'legacy auth bypass' => [['email_smtp_auth' => '1']],
            'unknown transport' => [['email_transport' => 'unimplemented']],
            'unknown auth' => [['email_smtp_auth_mode' => 'google_oauth']],
            'array field' => [['email_from' => ['sender@example.test']]],
            'header injection' => [['email_from_name' => "sender\r\nBcc: hidden@example.test"]],
            'invalid sender' => [['email_from' => 'not-an-address']],
            'empty host' => [['email_smtp_server' => '']],
            'host list' => [['email_smtp_server' => 'relay.internal;evil.example']],
            'invalid port' => [['email_port' => '65536']],
            'no password' => [['email_smtp_auth_mode' => 'password', 'email_auth_username' => 'sender', 'secret_actions' => ['email_auth_pwd' => 'clear']]],
            'secret without action' => [['email_auth_pwd' => 'private']],
            'empty replacement' => [['secret_actions' => ['email_auth_pwd' => 'replace']]],
            'unknown secret action' => [['secret_actions' => ['email_auth_pwd' => 'unsafe']]],
            'invalid secret mapping' => [['secret_actions' => 'clear']],
        ];
    }

    /** @dataProvider invalidProfiles */
    public function testInvalidProfilesAreRejectedWithoutPublishing(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        emailTransportCandidate($input, $this->smtp(), $this->encrypt(...));
    }

    /** Provider identities cannot inject another tenant endpoint or a different sender. */
    public function testMicrosoftProfileRequiresMatchingSenderAndEncryptedSecret(): void
    {
        $input = ['email_transport' => 'microsoft_graph', 'email_microsoft_tenant_id' => '11111111-1111-1111-1111-111111111111',
            'email_microsoft_client_id' => '22222222-2222-2222-2222-222222222222', 'email_microsoft_mailbox' => 'sender@example.test',
            'email_microsoft_client_secret' => 'private', 'secret_actions' => ['email_microsoft_client_secret' => 'replace']];
        $candidate = emailTransportCandidate($input, $this->smtp(), $this->encrypt(...));
        self::assertSame($this->encrypt('private'), $candidate['email_microsoft_client_secret']);
        $input['email_microsoft_mailbox'] = 'different@example.test';
        $this->expectException(InvalidArgumentException::class);
        emailTransportCandidate($input, $this->smtp(), $this->encrypt(...));
    }

    /** The canonical URL keeps subdirectory installations and refuses injected credentials/query parameters. */
    public function testGoogleRedirectUsesCanonicalHttpsUrl(): void
    {
        self::assertSame('https://example.test/teampass/sources/emails_oauth.php', emailTransportRedirectUri(['cpassman_url' => 'https://example.test/teampass/']));
        self::assertSame('http://localhost/sources/emails_oauth.php', emailTransportRedirectUri(['cpassman_url' => 'http://localhost']));
        $this->expectException(InvalidArgumentException::class);
        emailTransportRedirectUri(['cpassman_url' => 'https://user@example.test/?token=private']);
    }

    /** Expired, cross-user, stale-config and wrong-state callbacks cannot activate a candidate. */
    public function testConsentIsBoundToUserStateRevisionAndExpiry(): void
    {
        $settings = ['cpassman_url' => 'https://example.test', 'email_config_revision' => str_repeat('a', 32)];
        $pending = ['expires_at' => time() + 600, 'user_id' => 10, 'state' => 'random-state',
            'revision' => $settings['email_config_revision'], 'redirect_uri' => emailTransportRedirectUri($settings),
            'candidate' => [], 'verifier' => str_repeat('v', 43)];
        emailTransportValidateConsent($pending, 'random-state', 10, $settings);
        foreach ([['expires_at' => time() - 1], ['user_id' => 11], ['state' => 'other-state'], ['revision' => str_repeat('b', 32)],
            ['redirect_uri' => 'https://evil.example/callback'], ['verifier' => null]] as $override) {
            try {
                emailTransportValidateConsent(array_replace($pending, $override), 'random-state', 10, $settings);
                self::fail('Invalid consent binding accepted');
            } catch (InvalidArgumentException $e) {
                self::assertSame('configuration', $e->getMessage());
            }
        }
    }
}
