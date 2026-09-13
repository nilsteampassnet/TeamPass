<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\Exception\CounterException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

require_once __DIR__ . '/../../app/sources/webauthn.functions.php';

/**
 * Passkey authenticator primitives.
 *
 * The ceremony tests are the ones that matter: they hand what webauthn.functions.php produces
 * to web-auth/webauthn-lib's relying-party validators, which run the W3C registration and
 * authentication procedures. If they accept it, a real site accepts it.
 */
final class WebauthnAuthenticatorTest extends TestCase
{
    private const RP_ID = 'github.com';
    private const ORIGIN = 'https://github.com';

    public function testBase64UrlRoundTripsAndRejectsForeignAlphabet(): void
    {
        $bytes = "\xfb\xff\x00\x3e?";
        $encoded = webauthnBase64UrlEncode($bytes);

        $this->assertSame('-_8APj8', $encoded);
        $this->assertSame($bytes, webauthnBase64UrlDecode($encoded));
        $this->assertSame($bytes, webauthnBase64UrlDecode($encoded . '='));

        $this->expectException(InvalidArgumentException::class);
        webauthnBase64UrlDecode('+/8APj8');
    }

    public function testAaguidIsSixteenBytesOfTheFrozenValue(): void
    {
        $this->assertSame('7c30bcef-f035-4e21-9175-5c985b4b239c', TP_WEBAUTHN_AAGUID);
        $this->assertSame(16, strlen(webauthnAaguidBytes()));
        $this->assertSame('7c30bceff0354e2191755c985b4b239c', bin2hex(webauthnAaguidBytes()));
    }

    public function testCoseKeyMatchesHandComputedEncoding(): void
    {
        $x = str_repeat("\x11", 32);
        $y = str_repeat("\x22", 32);

        // map(5) { 1: 2, 3: -7, -1: 1, -2: bstr(32), -3: bstr(32) }
        $expected = "\xa5" . "\x01\x02" . "\x03\x26" . "\x20\x01"
            . "\x21\x58\x20" . $x
            . "\x22\x58\x20" . $y;

        $this->assertSame(bin2hex($expected), bin2hex(webauthnBuildCoseKey($x, $y)));
    }

    public function testCoseKeyRefusesCoordinatesOfTheWrongSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        webauthnBuildCoseKey(str_repeat("\x01", 31), str_repeat("\x01", 32));
    }

    public function testShortCoordinateIsLeftPadded(): void
    {
        $this->assertSame("\x00\x00" . str_repeat("\x07", 30), webauthnPadCoordinate(str_repeat("\x07", 30)));
        $this->assertSame(32, strlen(webauthnPadCoordinate('')));

        $this->expectException(InvalidArgumentException::class);
        webauthnPadCoordinate(str_repeat("\x07", 33));
    }

    public function testGeneratedKeyPairIsConsistent(): void
    {
        $pair = webauthnGenerateCredentialKeyPair();

        $this->assertStringStartsWith('-----BEGIN PRIVATE KEY-----', $pair['pem']);
        $this->assertSame(32, strlen($pair['x']));
        $this->assertSame(32, strlen($pair['y']));
        $this->assertSame(webauthnBuildCoseKey($pair['x'], $pair['y']), $pair['cose']);

        // The private key signs, and the public key rebuilt from its coordinates verifies.
        $signature = webauthnSignAssertion($pair['pem'], 'authenticator-data', '{"type":"webauthn.get"}');
        $payload = 'authenticator-data' . hash('sha256', '{"type":"webauthn.get"}', true);
        $this->assertSame(1, openssl_verify($payload, $signature, $this->publicKeyPem($pair['x'], $pair['y']), OPENSSL_ALGO_SHA256));
    }

    public function testAssertionAuthenticatorDataLayout(): void
    {
        $authData = webauthnBuildAuthenticatorData(self::RP_ID, webauthnBuildFlags(true), 0x01020304);

        $this->assertSame(37, strlen($authData));
        $this->assertSame(hash('sha256', self::RP_ID, true), substr($authData, 0, 32));
        $this->assertSame(TP_WEBAUTHN_FLAG_UP | TP_WEBAUTHN_FLAG_UV | TP_WEBAUTHN_FLAG_BE | TP_WEBAUTHN_FLAG_BS, ord($authData[32]));
        $this->assertSame("\x01\x02\x03\x04", substr($authData, 33, 4));
    }

    public function testRegistrationAuthenticatorDataCarriesAttestedCredential(): void
    {
        $pair = webauthnGenerateCredentialKeyPair();
        $credentialId = webauthnGenerateCredentialId();

        // AT is derived from the attested data, whatever the caller passed.
        $authData = webauthnBuildAuthenticatorData(self::RP_ID, webauthnBuildFlags(false) | TP_WEBAUTHN_FLAG_AT, 0, $credentialId, $pair['cose']);

        $this->assertSame(37 + 16 + 2 + 32 + 77, strlen($authData));
        $this->assertSame(TP_WEBAUTHN_FLAG_UP | TP_WEBAUTHN_FLAG_BE | TP_WEBAUTHN_FLAG_BS | TP_WEBAUTHN_FLAG_AT, ord($authData[32]));
        $this->assertSame(webauthnAaguidBytes(), substr($authData, 37, 16));
        $this->assertSame("\x00\x20", substr($authData, 53, 2));
        $this->assertSame($credentialId, substr($authData, 55, 32));
        $this->assertSame($pair['cose'], substr($authData, 87));

        $withoutAttestedData = webauthnBuildAuthenticatorData(self::RP_ID, TP_WEBAUTHN_FLAG_UP | TP_WEBAUTHN_FLAG_AT, 0);
        $this->assertSame(TP_WEBAUTHN_FLAG_UP, ord($withoutAttestedData[32]));
    }

    public function testAuthenticatorDataRefusesInconsistentInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        webauthnBuildAuthenticatorData(self::RP_ID, TP_WEBAUTHN_FLAG_UP, 0, webauthnGenerateCredentialId(), null);
    }

    public function testSignCountOutOfRangeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        webauthnBuildAuthenticatorData(self::RP_ID, TP_WEBAUTHN_FLAG_UP, 0x100000000);
    }

    public function testAttestationObjectMatchesHandComputedEncoding(): void
    {
        $authData = str_repeat("\xab", 164);

        // map(3) { "fmt": "none", "attStmt": {}, "authData": bstr(164) }
        $expected = "\xa3"
            . "\x63fmt" . "\x64none"
            . "\x67attStmt" . "\xa0"
            . "\x68authData" . "\x58\xa4" . $authData;

        $this->assertSame(bin2hex($expected), bin2hex(webauthnBuildAttestationObject($authData)));
    }

    public function testRelyingPartyAcceptsRegistrationAndSuccessiveAssertions(): void
    {
        $userHandle = random_bytes(16);
        [$credentialRecord, $pair, $credentialId] = $this->register($userHandle, true);

        $this->assertSame($credentialId, $credentialRecord->publicKeyCredentialId);
        $this->assertSame(TP_WEBAUTHN_AAGUID, $credentialRecord->aaguid->toRfc4122());
        $this->assertSame($pair['cose'], $credentialRecord->credentialPublicKey);
        $this->assertTrue($credentialRecord->backupEligible);
        $this->assertTrue($credentialRecord->backupStatus);

        foreach ([1, 2] as $signCount) {
            $credentialRecord = $this->assert($credentialRecord, $pair['pem'], $credentialId, $userHandle, $signCount, self::ORIGIN);
            $this->assertSame($signCount, $credentialRecord->counter);
        }
    }

    public function testRelyingPartyRefusesAssertionFromAnotherOrigin(): void
    {
        $userHandle = random_bytes(16);
        [$credentialRecord, $pair, $credentialId] = $this->register($userHandle, true);

        $this->expectException(AuthenticatorResponseVerificationException::class);
        $this->assert($credentialRecord, $pair['pem'], $credentialId, $userHandle, 1, 'https://github.com.evil.example');
    }

    public function testRelyingPartyRefusesAssertionSignedByAnotherKey(): void
    {
        $userHandle = random_bytes(16);
        [$credentialRecord, , $credentialId] = $this->register($userHandle, true);
        $otherPair = webauthnGenerateCredentialKeyPair();

        $this->expectException(AuthenticatorResponseVerificationException::class);
        $this->assert($credentialRecord, $otherPair['pem'], $credentialId, $userHandle, 1, self::ORIGIN);
    }

    public function testRelyingPartyRefusesReplayedCounter(): void
    {
        $userHandle = random_bytes(16);
        [$credentialRecord, $pair, $credentialId] = $this->register($userHandle, true);
        $credentialRecord = $this->assert($credentialRecord, $pair['pem'], $credentialId, $userHandle, 5, self::ORIGIN);

        // A cloned authenticator shows up as a counter that does not move forward.
        $this->expectException(CounterException::class);
        $this->assert($credentialRecord, $pair['pem'], $credentialId, $userHandle, 5, self::ORIGIN);
    }

    public function testRelyingPartyRequiringUserVerificationRefusesUnverifiedAssertion(): void
    {
        $userHandle = random_bytes(16);
        [$credentialRecord, $pair, $credentialId] = $this->register($userHandle, true);

        $this->expectException(AuthenticatorResponseVerificationException::class);
        $this->assert($credentialRecord, $pair['pem'], $credentialId, $userHandle, 1, self::ORIGIN, false);
    }

    public function testAlgorithmSelection(): void
    {
        $this->assertSame(-7, webauthnSelectAlgorithm([]));
        $this->assertSame(-7, webauthnSelectAlgorithm([-257, -7]));
        $this->assertNull(webauthnSelectAlgorithm([-257]));
        $this->assertNull(webauthnSelectAlgorithm(['-7']));
    }

    public function testRelyingPartyIdNormalization(): void
    {
        $this->assertSame('github.com', webauthnNormalizeRpId(' GitHub.com '));
        $this->assertSame('login.example.co.uk', webauthnNormalizeRpId('login.example.co.uk'));
        $this->assertSame('localhost', webauthnNormalizeRpId('localhost'));

        foreach (['', 'com', 'https://github.com', 'github.com:443', 'github.com/login', '192.168.1.10', '-github.com', 'git_hub.com', 'github..com', '[::1]'] as $invalid) {
            $this->assertNull(webauthnNormalizeRpId($invalid), $invalid);
        }
    }

    public function testOriginMatching(): void
    {
        $this->assertTrue(webauthnOriginMatchesRpId('https://github.com', 'github.com'));
        $this->assertTrue(webauthnOriginMatchesRpId('https://gist.github.com', 'github.com'));
        $this->assertTrue(webauthnOriginMatchesRpId('https://github.com:8443', 'github.com'));
        $this->assertTrue(webauthnOriginMatchesRpId('http://localhost:8080', 'localhost'));

        $this->assertFalse(webauthnOriginMatchesRpId('http://github.com', 'github.com'));
        $this->assertFalse(webauthnOriginMatchesRpId('https://evilgithub.com', 'github.com'));
        $this->assertFalse(webauthnOriginMatchesRpId('https://github.com.evil.example', 'github.com'));
        $this->assertFalse(webauthnOriginMatchesRpId('https://github.com/login', 'github.com'));
        $this->assertFalse(webauthnOriginMatchesRpId('https://user@github.com', 'github.com'));
        $this->assertFalse(webauthnOriginMatchesRpId('https://github.com', 'gist.github.com'));
    }

    public function testClientDataParsing(): void
    {
        $json = json_encode(['type' => 'webauthn.get', 'challenge' => 'q83vEjRWeJA', 'origin' => self::ORIGIN, 'crossOrigin' => false]);
        $parsed = webauthnParseClientDataJson((string) $json, 'webauthn.get');

        $this->assertSame(['type' => 'webauthn.get', 'challenge' => 'q83vEjRWeJA', 'origin' => self::ORIGIN, 'crossOrigin' => false], $parsed);
    }

    public function testClientDataOfAnotherTypeIsRefused(): void
    {
        $json = json_encode(['type' => 'webauthn.create', 'challenge' => 'q83vEjRWeJA', 'origin' => self::ORIGIN]);

        $this->expectException(InvalidArgumentException::class);
        webauthnParseClientDataJson((string) $json, 'webauthn.get');
    }

    public function testClientDataWithoutOriginIsRefused(): void
    {
        $json = json_encode(['type' => 'webauthn.get', 'challenge' => 'q83vEjRWeJA']);

        $this->expectException(InvalidArgumentException::class);
        webauthnParseClientDataJson((string) $json, 'webauthn.get');
    }

    public function testUserHandleLength(): void
    {
        $this->assertTrue(webauthnIsValidUserHandle(str_repeat('a', 64)));
        $this->assertFalse(webauthnIsValidUserHandle(''));
        $this->assertFalse(webauthnIsValidUserHandle(str_repeat('a', 65)));
    }

    /**
     * Create a credential the way the API will, and have the relying party register it.
     *
     * @return array{0: CredentialRecord, 1: array{pem: string, x: string, y: string, cose: string}, 2: string}
     */
    private function register(string $userHandle, bool $userVerified): array
    {
        $pair = webauthnGenerateCredentialKeyPair();
        $credentialId = webauthnGenerateCredentialId();
        $challenge = random_bytes(32);

        $authData = webauthnBuildAuthenticatorData(self::RP_ID, webauthnBuildFlags($userVerified), 0, $credentialId, $pair['cose']);
        $clientDataJson = (string) json_encode([
            'type' => 'webauthn.create',
            'challenge' => webauthnBase64UrlEncode($challenge),
            'origin' => self::ORIGIN,
            'crossOrigin' => false,
        ]);

        $credential = $this->serializer()->deserialize((string) json_encode([
            'id' => webauthnBase64UrlEncode($credentialId),
            'rawId' => webauthnBase64UrlEncode($credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => webauthnBase64UrlEncode($clientDataJson),
                'attestationObject' => webauthnBase64UrlEncode(webauthnBuildAttestationObject($authData)),
                'transports' => TP_WEBAUTHN_TRANSPORTS,
            ],
        ]), PublicKeyCredential::class, 'json');
        $this->assertInstanceOf(AuthenticatorAttestationResponse::class, $credential->response);

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create('GitHub', self::RP_ID),
            PublicKeyCredentialUserEntity::create('svc-ci', $userHandle, 'CI service account'),
            $challenge,
            [PublicKeyCredentialParameters::create('public-key', -7)],
            AuthenticatorSelectionCriteria::create(null, AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED, AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE
        );

        $record = AuthenticatorAttestationResponseValidator::create($this->ceremonyFactory()->creationCeremony())
            ->check($credential->response, $options, self::RP_ID);

        return [$record, $pair, $credentialId];
    }

    /**
     * Sign an assertion the way the API will, and have the relying party verify it.
     */
    private function assert(
        CredentialRecord $record,
        string $privateKeyPem,
        string $credentialId,
        string $userHandle,
        int $signCount,
        string $origin,
        bool $userVerified = true
    ): CredentialRecord {
        $challenge = random_bytes(32);
        $authData = webauthnBuildAuthenticatorData(self::RP_ID, webauthnBuildFlags($userVerified), $signCount);
        $clientDataJson = (string) json_encode([
            'type' => 'webauthn.get',
            'challenge' => webauthnBase64UrlEncode($challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ]);

        $credential = $this->serializer()->deserialize((string) json_encode([
            'id' => webauthnBase64UrlEncode($credentialId),
            'rawId' => webauthnBase64UrlEncode($credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => webauthnBase64UrlEncode($clientDataJson),
                'authenticatorData' => webauthnBase64UrlEncode($authData),
                'signature' => webauthnBase64UrlEncode(webauthnSignAssertion($privateKeyPem, $authData, $clientDataJson)),
                'userHandle' => webauthnBase64UrlEncode($userHandle),
            ],
        ]), PublicKeyCredential::class, 'json');
        $this->assertInstanceOf(AuthenticatorAssertionResponse::class, $credential->response);

        $options = PublicKeyCredentialRequestOptions::create(
            $challenge,
            self::RP_ID,
            [PublicKeyCredentialDescriptor::create('public-key', $credentialId)],
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
        );

        return AuthenticatorAssertionResponseValidator::create($this->ceremonyFactory()->requestCeremony())
            ->check($record, $credential->response, $options, self::RP_ID, $userHandle);
    }

    private function ceremonyFactory(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([self::ORIGIN]);

        return $factory;
    }

    private function serializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory(
            new AttestationStatementSupportManager([new NoneAttestationStatementSupport()])
        ))->create();
    }

    /**
     * Wrap the SubjectPublicKeyInfo the API returns into PEM, as a relying party would.
     */
    private function publicKeyPem(string $x, string $y): string
    {
        $der = webauthnBuildSpkiPublicKey($x, $y);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
