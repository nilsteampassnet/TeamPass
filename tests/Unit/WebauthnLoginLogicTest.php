<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Webauthn\CredentialRecord;

require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/webauthn_login_logic.php';

/**
 * Sign-in passkeys (part B): TeamPass as a relying party.
 *
 * The ceremonies are played by a simulated authenticator — the part A primitives, which a real
 * relying party already accepts (WebauthnAuthenticatorTest) — and verified by the webauthn-lib
 * validators exactly as the profile and login handlers call them.
 */
final class WebauthnLoginLogicTest extends TestCase
{
    private const URL = 'https://tp.example.com/teampass';
    private const ORIGIN = 'https://tp.example.com';
    private const RP_ID = 'tp.example.com';

    public function testModeDefaultsToDisabled(): void
    {
        $this->assertSame(TP_WEBAUTHN_LOGIN_MODE_DISABLED, webauthnLoginMode([]));
        $this->assertSame(TP_WEBAUTHN_LOGIN_MODE_DISABLED, webauthnLoginMode(['webauthn_login_mode' => '7']));
        $this->assertSame(TP_WEBAUTHN_LOGIN_MODE_SECOND_FACTOR, webauthnLoginMode(['webauthn_login_mode' => '1']));
        $this->assertSame(TP_WEBAUTHN_LOGIN_MODE_PASSWORDLESS, webauthnLoginMode(['webauthn_login_mode' => 2]));
    }

    public function testOriginIsWhatTheBrowserWrites(): void
    {
        $this->assertSame('https://tp.example.com', webauthnLoginOriginOf('https://TP.example.com:443/teampass/'));
        $this->assertSame('http://localhost:8080', webauthnLoginOriginOf('http://localhost:8080/TeamPass'));
        $this->assertSame('http://localhost', webauthnLoginOriginOf('http://localhost/TeamPass'));
        $this->assertSame('', webauthnLoginOriginOf('localhost/TeamPass'));
    }

    public function testRelyingPartyIdIsTheHostOrAParentDomainOfIt(): void
    {
        $this->assertSame(self::RP_ID, webauthnLoginRpId(['cpassman_url' => self::URL]));
        $this->assertSame('example.com', webauthnLoginRpId(['cpassman_url' => self::URL, 'webauthn_rp_id' => ' Example.COM ']));
        // A domain the page is not served from would make every ceremony fail in the browser.
        $this->assertSame(self::RP_ID, webauthnLoginRpId(['cpassman_url' => self::URL, 'webauthn_rp_id' => 'evil.com']));
        $this->assertSame(self::RP_ID, webauthnLoginRpId(['cpassman_url' => self::URL, 'webauthn_rp_id' => 'mple.com']));
        $this->assertSame(self::RP_ID, webauthnLoginRpId(['cpassman_url' => self::URL, 'webauthn_rp_id' => 'com']));
        $this->assertSame('192.168.1.10', webauthnLoginRpId(['cpassman_url' => 'https://192.168.1.10/tp', 'webauthn_rp_id' => '168.1.10']));
    }

    public function testOnlyLocalAccountsInPasswordlessModeGetAWrap(): void
    {
        $passwordless = ['webauthn_login_mode' => '2'];
        $this->assertTrue(webauthnLoginCanWrap($passwordless, 'local'));
        // Signing in without the directory password would bypass the directory.
        $this->assertFalse(webauthnLoginCanWrap($passwordless, 'ldap'));
        $this->assertFalse(webauthnLoginCanWrap($passwordless, 'oauth2'));
        $this->assertFalse(webauthnLoginCanWrap(['webauthn_login_mode' => '1'], 'local'));
    }

    public function testPasskeySecondFactorIsOptIn(): void
    {
        $secondFactor = ['webauthn_login_mode' => '1'];

        $this->assertTrue(webauthnLoginIsSecondFactor($secondFactor, 1, true));
        $this->assertTrue(webauthnLoginIsSecondFactor(['webauthn_login_mode' => '2'], 1, true));
        // Turning passkeys on imposes nothing on an account without one
        $this->assertFalse(webauthnLoginIsSecondFactor($secondFactor, 1, false));
        // The administrator turned MFA off for this account
        $this->assertFalse(webauthnLoginIsSecondFactor($secondFactor, 0, true));
        // Passkeys turned off: a registered passkey is not asked for
        $this->assertFalse(webauthnLoginIsSecondFactor([], 1, true));
    }

    public function testPrfInputIsOneConstantForEveryPasskey(): void
    {
        // Constant, so a discoverable sign-in can evaluate the PRF of whichever passkey is picked
        $this->assertSame(32, strlen(webauthnLoginPrfInput()));
        $this->assertSame(webauthnLoginPrfInput(), webauthnLoginPrfInput());
    }

    public function testPasswordlessSignInIsRefusedOutsideItsScope(): void
    {
        $on = ['webauthn_login_mode' => '2'];
        $local = ['auth_type' => 'local', 'special' => 'none', 'key_wrap_mode' => TP_WEBAUTHN_LOGIN_WRAP_PRF];

        $this->assertNull(webauthnLoginPasswordlessRefusal($on, $local));
        $this->assertNull(webauthnLoginPasswordlessRefusal($on, ['key_wrap_mode' => TP_WEBAUTHN_LOGIN_WRAP_SERVER] + $local));
        $this->assertSame('webauthn_login_disabled', webauthnLoginPasswordlessRefusal(['webauthn_login_mode' => '1'], $local));
        $this->assertSame('webauthn_login_passwordless_unavailable', webauthnLoginPasswordlessRefusal($on, ['auth_type' => 'ldap'] + $local));
        $this->assertSame('webauthn_login_passwordless_unavailable', webauthnLoginPasswordlessRefusal($on, ['auth_type' => 'oauth2'] + $local));
        foreach (['generate-keys', 'recrypt-private-key', 'otc_is_required_on_next_login', 'user_added_from_ad'] as $special) {
            $this->assertSame('webauthn_login_passwordless_unavailable', webauthnLoginPasswordlessRefusal($on, ['special' => $special] + $local), $special);
        }
        $this->assertSame('webauthn_login_passwordless_not_enabled', webauthnLoginPasswordlessRefusal($on, ['key_wrap_mode' => TP_WEBAUTHN_LOGIN_WRAP_NONE] + $local));
    }

    public function testImposedMfaBlocksPasswordlessOnlyWhenTheAdministratorSaysSo(): void
    {
        $this->assertFalse(webauthnLoginPasswordlessBlockedByMfa([], true));
        $this->assertFalse(webauthnLoginPasswordlessBlockedByMfa(['webauthn_passwordless_satisfies_mfa' => '1'], true));
        $this->assertTrue(webauthnLoginPasswordlessBlockedByMfa(['webauthn_passwordless_satisfies_mfa' => '0'], true));
        $this->assertFalse(webauthnLoginPasswordlessBlockedByMfa(['webauthn_passwordless_satisfies_mfa' => '0'], false));
    }

    public function testUserHandleIsStableAndOpaque(): void
    {
        $handle = webauthnLoginUserHandle(42, 'secret');

        $this->assertSame(32, strlen($handle));
        $this->assertSame($handle, webauthnLoginUserHandle(42, 'secret'));
        $this->assertNotSame($handle, webauthnLoginUserHandle(43, 'secret'));
        $this->assertNotSame($handle, webauthnLoginUserHandle(42, 'other secret'));
    }

    public function testPrfWrapOpensOnlyWithTheSamePrfOutput(): void
    {
        $salt = random_bytes(32);
        $prf = random_bytes(32);
        $wrapped = webauthnLoginWrapPrivateKey('PRIVATE-KEY', webauthnLoginPrfWrapKey($prf, $salt));

        $this->assertSame('PRIVATE-KEY', webauthnLoginUnwrapPrivateKey($wrapped, webauthnLoginPrfWrapKey($prf, $salt)));
        $this->assertNull(webauthnLoginUnwrapPrivateKey($wrapped, webauthnLoginPrfWrapKey(random_bytes(32), $salt)));
        $this->assertNull(webauthnLoginUnwrapPrivateKey($wrapped, webauthnLoginPrfWrapKey($prf, random_bytes(32))));

        $this->expectException(InvalidArgumentException::class);
        webauthnLoginPrfWrapKey('short', $salt);
    }

    public function testServerWrapIsBoundToItsPasskey(): void
    {
        $salt = random_bytes(32);
        $credentialId = random_bytes(32);
        $wrapped = webauthnLoginWrapPrivateKey('PRIVATE-KEY', webauthnLoginServerWrapKey('instance secret', $salt, $credentialId));

        $this->assertSame('PRIVATE-KEY', webauthnLoginUnwrapPrivateKey($wrapped, webauthnLoginServerWrapKey('instance secret', $salt, $credentialId)));
        $this->assertNull(webauthnLoginUnwrapPrivateKey($wrapped, webauthnLoginServerWrapKey('instance secret', $salt, random_bytes(32))));
        $this->assertNull(webauthnLoginUnwrapPrivateKey($wrapped, webauthnLoginServerWrapKey('another instance', $salt, $credentialId)));
        // PRF and server keys never coincide, even from the same inputs.
        $this->assertNotSame(webauthnLoginPrfWrapKey($salt, $salt), webauthnLoginServerWrapKey($salt, $salt, $salt));
    }

    public function testAnEmptyPrivateKeyIsNeverWrapped(): void
    {
        $this->expectException(RuntimeException::class);
        webauthnLoginWrapPrivateKey('', random_bytes(32));
    }

    public function testLabelIsPlainBoundedText(): void
    {
        $this->assertSame('MacBook Touch ID', webauthnLoginNormalizeLabel("  <b>MacBook</b>\tTouch ID "));
        $this->assertSame(TP_WEBAUTHN_LOGIN_LABEL_MAX, mb_strlen(webauthnLoginNormalizeLabel(str_repeat('é', 300))));
        $this->assertSame('', webauthnLoginNormalizeLabel(['array']));
        $this->assertSame('', webauthnLoginNormalizeLabel("\xff\xfe"));
    }

    public function testReadBytesRejectsMalformedInput(): void
    {
        $bytes = random_bytes(32);
        $this->assertSame($bytes, webauthnLoginReadBytes(webauthnBase64UrlEncode($bytes), 32));
        $this->assertSame('', webauthnLoginReadBytes(webauthnBase64UrlEncode($bytes), 16));
        $this->assertSame('', webauthnLoginReadBytes('not/base64url+'));
        $this->assertSame('', webauthnLoginReadBytes(null));
    }

    public function testPendingCeremonyIsSingleUseAndShortLived(): void
    {
        $now = 1_800_000_000;
        $pending = ['purpose' => 'register', 'options' => '{}', 'created_at' => $now - 10];

        $this->assertTrue(webauthnLoginPendingIsUsable($pending, 'register', $now));
        $this->assertFalse(webauthnLoginPendingIsUsable($pending, 'passwordless', $now));
        $this->assertFalse(webauthnLoginPendingIsUsable($pending, 'register', $now + TP_WEBAUTHN_LOGIN_CEREMONY_TTL));
        $this->assertFalse(webauthnLoginPendingIsUsable(null, 'register', $now));
        $this->assertFalse(webauthnLoginPendingIsUsable(['created_at' => $now] + $pending, 'register', $now - 1));
    }

    public function testPasswordlessRegistrationIsDiscoverableAndVerifiesTheUser(): void
    {
        $options = json_decode(webauthnLoginSerializeOptions($this->creationOptions([], true)), true);

        $this->assertSame('required', $options['authenticatorSelection']['residentKey']);
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->assertSame('none', $options['attestation']);
        $this->assertSame([-7, -257], array_column($options['pubKeyCredParams'], 'alg'));

        $secondFactor = json_decode(webauthnLoginSerializeOptions($this->creationOptions([], false)), true);
        $this->assertSame('preferred', $secondFactor['authenticatorSelection']['userVerification']);
    }

    public function testRegistrationThenAssertionAreAccepted(): void
    {
        [$record, $pair, $credentialId] = $this->register();

        $this->assertSame($credentialId, $record->publicKeyCredentialId);
        $this->assertSame($pair['cose'], $record->credentialPublicKey);

        // What the handlers store, then rebuild before an assertion.
        $row = [
            'credential_id' => webauthnBase64UrlEncode($record->publicKeyCredentialId),
            'public_key_cose' => base64_encode($record->credentialPublicKey),
            'sign_count' => $record->counter,
            'aaguid' => (string) $record->aaguid,
            'transports' => json_encode($record->transports),
            'backup_eligible' => $record->backupEligible === true ? 1 : 0,
            'backup_state' => $record->backupStatus === true ? 1 : 0,
        ];
        $stored = webauthnLoginRecordFromRow($row, $this->userHandle());

        $updated = $this->assert($stored, $pair['pem'], $credentialId, 1);
        $this->assertSame(1, $updated->counter);
    }

    public function testAssertionFromAnotherOriginIsRejected(): void
    {
        [$record, $pair, $credentialId] = $this->register();

        $this->expectException(InvalidArgumentException::class);
        $this->assert($record, $pair['pem'], $credentialId, 1, 'https://tp.example.com.evil.net');
    }

    public function testReplayedCounterIsRejected(): void
    {
        [$record, $pair, $credentialId] = $this->register();
        $record = $this->assert($record, $pair['pem'], $credentialId, 5);

        // A cloned authenticator signs with a counter the server has already seen.
        $this->expectException(InvalidArgumentException::class);
        $this->assert($record, $pair['pem'], $credentialId, 5);
    }

    public function testAssertionOfAnotherPasskeyIsRejected(): void
    {
        [$record, $pair] = $this->register();

        $this->expectException(InvalidArgumentException::class);
        $this->assert($record, $pair['pem'], random_bytes(32), 1);
    }

    public function testRegistrationWithoutUserVerificationIsRejectedForPasswordless(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->register(false);
    }

    public function testRegistrationAgainstOtherOptionsIsRejected(): void
    {
        $pair = webauthnGenerateCredentialKeyPair();
        $credentialId = random_bytes(32);
        $played = $this->creationOptions([], true);
        $expected = webauthnLoginSerializeOptions($this->creationOptions([], true));

        $this->expectException(InvalidArgumentException::class);
        webauthnLoginVerifyRegistration($this->attestation($played->challenge, $pair, $credentialId, true), $expected, self::ORIGIN);
    }

    /**
     * @return array{0: CredentialRecord, 1: array{pem: string, x: string, y: string, cose: string}, 2: string}
     */
    private function register(bool $userVerified = true): array
    {
        $pair = webauthnGenerateCredentialKeyPair();
        $credentialId = random_bytes(32);
        $options = $this->creationOptions([], true);

        $record = webauthnLoginVerifyRegistration(
            $this->attestation($options->challenge, $pair, $credentialId, $userVerified),
            webauthnLoginSerializeOptions($options),
            self::ORIGIN
        );

        return [$record, $pair, $credentialId];
    }

    /**
     * @param array{pem: string, x: string, y: string, cose: string} $pair
     *
     * @return array<string, mixed>
     */
    private function attestation(string $challenge, array $pair, string $credentialId, bool $userVerified): array
    {
        $clientData = (string) json_encode([
            'type' => 'webauthn.create',
            'challenge' => webauthnBase64UrlEncode($challenge),
            'origin' => self::ORIGIN,
            'crossOrigin' => false,
        ]);
        $authData = webauthnBuildAuthenticatorData(self::RP_ID, webauthnBuildFlags($userVerified), 0, $credentialId, $pair['cose']);

        return [
            'id' => webauthnBase64UrlEncode($credentialId),
            'rawId' => webauthnBase64UrlEncode($credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => webauthnBase64UrlEncode($clientData),
                'attestationObject' => webauthnBase64UrlEncode(webauthnBuildAttestationObject($authData)),
                'transports' => ['internal'],
            ],
            // Dropped before deserialization: it would carry the PRF output.
            'clientExtensionResults' => ['prf' => ['enabled' => true]],
        ];
    }

    private function assert(CredentialRecord $record, string $pem, string $credentialId, int $counter, string $origin = self::ORIGIN): CredentialRecord
    {
        $options = webauthnLoginRequestOptions(self::RP_ID, [$record->publicKeyCredentialId], true);
        $clientData = (string) json_encode([
            'type' => 'webauthn.get',
            'challenge' => webauthnBase64UrlEncode($options->challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ]);
        $authData = webauthnBuildAuthenticatorData(self::RP_ID, webauthnBuildFlags(true), $counter);

        return webauthnLoginVerifyAssertion(
            $record,
            [
                'id' => webauthnBase64UrlEncode($credentialId),
                'rawId' => webauthnBase64UrlEncode($credentialId),
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => webauthnBase64UrlEncode($clientData),
                    'authenticatorData' => webauthnBase64UrlEncode($authData),
                    'signature' => webauthnBase64UrlEncode(webauthnSignAssertion($pem, $authData, $clientData)),
                    'userHandle' => webauthnBase64UrlEncode($this->userHandle()),
                ],
            ],
            webauthnLoginSerializeOptions($options),
            self::ORIGIN
        );
    }

    /**
     * @param string[] $excludeIds
     */
    private function creationOptions(array $excludeIds, bool $forPasswordless): \Webauthn\PublicKeyCredentialCreationOptions
    {
        return webauthnLoginCreationOptions(self::RP_ID, 'TeamPass', $this->userHandle(), 'jdoe', 'John Doe', $excludeIds, $forPasswordless);
    }

    private function userHandle(): string
    {
        return webauthnLoginUserHandle(7, 'instance secret');
    }
}
