<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/sources/webauthn.functions.php';

/**
 * Passkey API: request validation and route wiring.
 *
 * The normalizers decide which HTTP status a malformed request gets (400) and which requests are
 * refused on substance (422), before any database access.
 */
final class WebauthnApiTest extends TestCase
{
    private const ORIGIN = 'https://github.com';

    public function testValidCreateRequestIsNormalized(): void
    {
        $userHandle = random_bytes(16);
        $excluded = random_bytes(32);

        $request = webauthnNormalizeCreateRequest([
            'item_id' => '42',
            'rp_id' => 'GitHub.com',
            'rp_name' => ' GitHub ',
            'user_handle' => webauthnBase64UrlEncode($userHandle),
            'user_name' => 'svc-ci',
            'pub_key_cred_params' => [['type' => 'public-key', 'alg' => -257], ['type' => 'public-key', 'alg' => -7]],
            'client_data_json' => $this->clientData('webauthn.create', self::ORIGIN),
            'user_verified' => true,
            'excluded_credential_ids' => [webauthnBase64UrlEncode($excluded) . '=', webauthnBase64UrlEncode($excluded)],
        ]);

        $this->assertSame(42, $request['item_id']);
        $this->assertSame('github.com', $request['rp_id']);
        $this->assertSame('GitHub', $request['rp_name']);
        $this->assertSame($userHandle, $request['user_handle']);
        $this->assertSame('', $request['user_display_name']);
        $this->assertSame(-7, $request['algorithm']);
        $this->assertTrue($request['user_verified']);
        $this->assertSame([webauthnBase64UrlEncode($excluded)], $request['excluded_credential_ids']);
        $this->assertSame('webauthn.create', json_decode($request['client_data_json'], true)['type']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: int}>
     */
    public static function invalidCreateRequests(): array
    {
        $valid = [
            'item_id' => 1,
            'rp_id' => 'github.com',
            'user_handle' => 'dXNlcg',
        ];

        return [
            'missing item' => [array_diff_key($valid, ['item_id' => 0]), 400],
            'negative item' => [['item_id' => -1] + $valid, 400],
            'missing rp_id' => [array_diff_key($valid, ['rp_id' => 0]), 400],
            'rp_id with scheme' => [['rp_id' => 'https://github.com'] + $valid, 422],
            'missing user handle' => [array_diff_key($valid, ['user_handle' => 0]), 400],
            'user handle too long' => [['user_handle' => webauthnBase64UrlEncode(str_repeat('a', 65))] + $valid, 422],
            'user handle not base64url' => [['user_handle' => 'not base64!'] + $valid, 400],
            'RS256 only' => [['pub_key_cred_params' => [-257]] + $valid, 422],
            'unusable algorithm entries' => [['pub_key_cred_params' => [['type' => 'public-key']]] + $valid, 422],
            'user_verified not boolean' => [['user_verified' => 'yes'] + $valid, 400],
            'name too long' => [['user_name' => str_repeat('é', 256)] + $valid, 422],
            'too many excluded ids' => [['excluded_credential_ids' => array_fill(0, 101, 'AAAA')] + $valid, 400],
        ];
    }

    /**
     * @dataProvider invalidCreateRequests
     *
     * @param array<string, mixed> $input
     */
    public function testInvalidCreateRequestIsRefusedWithItsStatus(array $input, int $status): void
    {
        if (isset($input['client_data_json']) === false) {
            $input['client_data_json'] = $this->clientData('webauthn.create', self::ORIGIN);
        }

        try {
            webauthnNormalizeCreateRequest($input);
            $this->fail('The request was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($status, $e->getCode(), $e->getMessage());
        }
    }

    public function testCreateRequestFromAnotherOriginIsRefused(): void
    {
        $this->expectExceptionCode(422);
        webauthnNormalizeCreateRequest([
            'item_id' => 1,
            'rp_id' => 'github.com',
            'user_handle' => 'dXNlcg',
            'client_data_json' => $this->clientData('webauthn.create', 'https://github.com.evil.example'),
        ]);
    }

    public function testCreateRequestWithAssertionClientDataIsRefused(): void
    {
        $this->expectExceptionCode(400);
        webauthnNormalizeCreateRequest([
            'item_id' => 1,
            'rp_id' => 'github.com',
            'user_handle' => 'dXNlcg',
            'client_data_json' => $this->clientData('webauthn.get', self::ORIGIN),
        ]);
    }

    public function testCrossOriginRequestIsRefused(): void
    {
        $this->expectExceptionCode(422);
        webauthnNormalizeAssertRequest([
            'credential_id' => webauthnBase64UrlEncode(random_bytes(32)),
            'rp_id' => 'github.com',
            'client_data_json' => $this->clientData('webauthn.get', self::ORIGIN, true),
        ]);
    }

    public function testValidAssertRequestIsNormalized(): void
    {
        $credentialId = random_bytes(32);

        $request = webauthnNormalizeAssertRequest([
            'credential_id' => webauthnBase64UrlEncode($credentialId) . '=',
            'rp_id' => 'github.com',
            'client_data_json' => $this->clientData('webauthn.get', 'https://gist.github.com'),
            'user_verified' => '1',
        ]);

        $this->assertSame(webauthnBase64UrlEncode($credentialId), $request['credential_id']);
        $this->assertSame('github.com', $request['rp_id']);
        $this->assertTrue($request['user_verified']);
    }

    public function testAssertRequestWithShortCredentialIdIsRefused(): void
    {
        $this->expectExceptionCode(400);
        webauthnNormalizeAssertRequest([
            'credential_id' => webauthnBase64UrlEncode('short'),
            'rp_id' => 'github.com',
            'client_data_json' => $this->clientData('webauthn.get', self::ORIGIN),
        ]);
    }

    public function testOversizedClientDataIsRefused(): void
    {
        $this->expectExceptionCode(400);
        webauthnNormalizeAssertRequest([
            'credential_id' => webauthnBase64UrlEncode(random_bytes(32)),
            'rp_id' => 'github.com',
            'client_data_json' => webauthnBase64UrlEncode(str_repeat(' ', TP_WEBAUTHN_CLIENT_DATA_MAX_BYTES + 1)),
        ]);
    }

    public function testSpkiPublicKeyIsUsableByOpenssl(): void
    {
        $pair = webauthnGenerateCredentialKeyPair();
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(webauthnBuildSpkiPublicKey($pair['x'], $pair['y'])), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        $this->assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        $this->assertSame('prime256v1', $details['ec']['curve_name']);
    }

    public function testEveryActionNeedsTheRightCrudRight(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../app/api/inc/bootstrap.php');

        $this->assertMatchesRegularExpression("/\['list', 'assert'\], true\) === true\) \{\s*return \(int\) \\\$userData\['allowed_to_read'\] === 1;/", $source);
        $this->assertMatchesRegularExpression("/\['create', 'delete'\], true\) === true\) \{\s*return \(int\) \\\$userData\['allowed_to_update'\] === 1;/", $source);
        $this->assertStringContainsString("webauthn_provider_enabled", $source);

        $router = (string) file_get_contents(__DIR__ . '/../../../app/api/index.php');
        $this->assertStringContainsString("\$controller === 'webauthn'", $router);
    }

    private function clientData(string $type, string $origin, bool $crossOrigin = false): string
    {
        return webauthnBase64UrlEncode((string) json_encode([
            'type' => $type,
            'challenge' => webauthnBase64UrlEncode(random_bytes(32)),
            'origin' => $origin,
            'crossOrigin' => $crossOrigin,
        ]));
    }
}
