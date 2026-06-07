<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the OAuth2 API access feature (Personal Access Tokens / extension tokens).
 *
 * Two layers:
 *  1. Functional — exercises the real crypto contract used by both the web generation
 *     path (users.queries.php) and the API auth path (AuthModel::getUserAuthByToken):
 *     HKDF-SHA256(token, salt) → AES-256-GCM wrap/unwrap via the shared encryption_utils
 *     helpers. This proves a token can recover the private key and that a wrong/tampered
 *     token cannot.
 *  2. Static regression — guards the gating and wiring so the OAuth2-only + admin-toggle
 *     constraints and the non-regression of the password path are not silently removed.
 */
class ExtensionTokenAuthTest extends TestCase
{
    /** Domain separation string — must match the server on both generation and auth. */
    private const HKDF_INFO = 'teampass-extension-token-v1';

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../../app/api/inc/encryption_utils.php';
    }

    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    /**
     * Reproduce the server-side generation step (users.queries.php).
     *
     * @return array{token:string, salt:string, token_hash:string, wrapped:string}
     */
    private function generateToken(string $privateKeyClear): array
    {
        $token = bin2hex(random_bytes(32));
        $salt  = bin2hex(random_bytes(16));
        $key   = hash_hkdf('sha256', $token, 32, self::HKDF_INFO, (string) hex2bin($salt));
        $wrapped = encrypt_with_session_key($privateKeyClear, $key);
        self::assertIsString($wrapped, 'encrypt_with_session_key must succeed with a 32-byte key');

        return [
            'token'      => $token,
            'salt'       => $salt,
            'token_hash' => hash('sha256', $token),
            'wrapped'    => $wrapped,
        ];
    }

    // -------------------------------------------------------------------------
    // Functional: the crypto contract
    // -------------------------------------------------------------------------

    public function testTokenRoundTripRecoversPrivateKey(): void
    {
        $privateKey = "-----BEGIN PRIVATE KEY-----\n" . base64_encode(random_bytes(256)) . "\n-----END PRIVATE KEY-----";
        $row = $this->generateToken($privateKey);

        // Auth side: re-derive the wrapping key from the presented token + stored salt.
        $key = hash_hkdf('sha256', $row['token'], 32, self::HKDF_INFO, (string) hex2bin($row['salt']));
        $recovered = decrypt_with_session_key($row['wrapped'], $key);

        self::assertSame($privateKey, $recovered, 'A valid token must recover the exact private key');
    }

    public function testTokenIsExactly64HexChars(): void
    {
        $row = $this->generateToken('dummy-key');
        self::assertSame(1, preg_match('/^[a-f0-9]{64}$/', $row['token']));
        self::assertSame(64, strlen($row['token_hash']), 'sha256 hex hash is 64 chars');
    }

    public function testWrappingKeyIsThirtyTwoBytes(): void
    {
        $token = bin2hex(random_bytes(32));
        $salt  = bin2hex(random_bytes(16));
        $key   = hash_hkdf('sha256', $token, 32, self::HKDF_INFO, (string) hex2bin($salt));
        self::assertSame(32, strlen($key), 'AES-256-GCM helper requires a 32-byte key');
    }

    public function testWrongTokenCannotDecrypt(): void
    {
        $row = $this->generateToken('super-secret-private-key');

        // Attacker presents a different token but the genuine stored salt.
        $wrongToken = bin2hex(random_bytes(32));
        $key = hash_hkdf('sha256', $wrongToken, 32, self::HKDF_INFO, (string) hex2bin($row['salt']));

        self::assertFalse(
            decrypt_with_session_key($row['wrapped'], $key),
            'A wrong token must not decrypt the wrapped private key'
        );
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $row = $this->generateToken('another-private-key');
        $key = hash_hkdf('sha256', $row['token'], 32, self::HKDF_INFO, (string) hex2bin($row['salt']));

        // Flip one byte of the base64 ciphertext — GCM auth tag must catch it.
        $raw = base64_decode($row['wrapped'], true);
        self::assertIsString($raw);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] === "\x00" ? "\x01" : "\x00";

        self::assertFalse(
            decrypt_with_session_key(base64_encode($raw), $key),
            'Tampered ciphertext must fail the GCM authentication tag'
        );
    }

    public function testTokenHashIsDeterministicAndTokenNotDerivableFromIt(): void
    {
        $token = bin2hex(random_bytes(32));
        self::assertSame(hash('sha256', $token), hash('sha256', $token), 'lookup hash is deterministic');
        // The stored hash is one-way: it is not the token itself.
        self::assertNotSame($token, hash('sha256', $token));
    }

    // -------------------------------------------------------------------------
    // Static regression: gating + wiring
    // -------------------------------------------------------------------------

    public function testAuthModelExposesTokenAuthAndSharedIssuance(): void
    {
        $src = $this->readSource('/app/api/Model/AuthModel.php');

        self::assertStringContainsString('public function getUserAuthByToken(', $src);
        self::assertStringContainsString('private function issueJwtForUser(', $src);
        // Shared issuance is reused by the password path (non-regression).
        self::assertStringContainsString('return $this->issueJwtForUser($userInfo, $privateKeyClear', $src);
    }

    public function testTokenAuthIsGatedAndOauth2Only(): void
    {
        $src = $this->readSource('/app/api/Model/AuthModel.php');

        // Admin toggle gate.
        self::assertStringContainsString("\$SETTINGS['oauth2_api_enabled']", $src);
        // OAuth2-only restriction.
        self::assertStringContainsString("auth_type'] ?? '') !== 'oauth2'", $src);
        // Strict token format.
        self::assertStringContainsString("/^[a-f0-9]{64}\$/", $src);
        // Lookup by hash, never by the raw token.
        self::assertStringContainsString("hash('sha256', \$inputData['token'])", $src);
        // Domain-separated HKDF + authenticated unwrap.
        self::assertStringContainsString(self::HKDF_INFO, $src);
        self::assertStringContainsString('decrypt_with_session_key(', $src);
        // Reuses existing bruteforce protection.
        self::assertStringContainsString('checkBruteforceProtection(', $src);
    }

    public function testControllerHasTokenActionAndRejectsQueryStringCredentials(): void
    {
        $src = $this->readSource('/app/api/Controller/Api/AuthController.php');

        self::assertStringContainsString('public function authorizeTokenAction()', $src);
        self::assertStringContainsString("\$sensitiveParams = ['login', 'token']", $src);
        self::assertStringContainsString('getUserAuthByToken(', $src);
    }

    public function testRouterDispatchesAuthorizeToken(): void
    {
        $src = $this->readSource('/app/api/index.php');
        self::assertStringContainsString("\$uri[0] === 'authorizeToken'", $src);
    }

    public function testWebGenerationIsGatedAndReturnsTokenOnce(): void
    {
        $src = $this->readSource('/app/sources/users.queries.php');

        self::assertStringContainsString("case 'generate_extension_token':", $src);
        self::assertStringContainsString("case 'list_extension_tokens':", $src);
        self::assertStringContainsString("case 'revoke_extension_token':", $src);
        // Gate: api + oauth2_api_enabled + oauth2 session.
        self::assertStringContainsString("\$SETTINGS['oauth2_api_enabled']", $src);
        self::assertStringContainsString("\$session->get('user-auth_type') !== 'oauth2'", $src);
        // Requires the cleartext private key (issuance gate).
        self::assertStringContainsString("\$session->get('user-private_key')", $src);
        // Only the hash is persisted; the raw token is returned in the response.
        self::assertStringContainsString("hash('sha256', \$tokenPlain)", $src);
        self::assertStringContainsString("'token' => \$tokenPlain", $src);
        // Audit logging.
        self::assertStringContainsString('at_extension_token_generated', $src);
        self::assertStringContainsString('at_extension_token_revoked', $src);
    }

    public function testTableAndSettingAreInstalledAndUpgraded(): void
    {
        $install = $this->readSource('/public/install/install-steps/run.step5.php');
        $upgrade = $this->readSource('/public/install/upgrade_run_3.2.0.php');

        // Fresh install creates the table + seeds the setting and registers the step.
        self::assertStringContainsString('api_tokens', $install);
        self::assertStringContainsString("array('admin', 'oauth2_api_enabled', '0')", $install);

        // Upgrade is idempotent and additive.
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS', $upgrade);
        self::assertStringContainsString('api_tokens', $upgrade);
        self::assertStringContainsString("'admin', 'oauth2_api_enabled', '0'", $upgrade);
    }

    public function testToggleExistsOnOauthAdminPage(): void
    {
        $src = $this->readSource('/app/pages/oauth.php');
        self::assertStringContainsString("id='oauth2_api_enabled'", $src);
        self::assertStringContainsString('settings_oauth2_api_enabled', $src);
    }
}
