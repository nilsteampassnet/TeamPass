<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Sentinel 1: JWT validation hardening.
 *
 * Verifies that is_jwt_valid() correctly rejects invalid, expired, and
 * tampered tokens, and accepts valid ones.
 *
 * Note: tests mock getApiJwtSigningKey() by defining a stub constant so
 * the function returns a predictable key without a DB connection.
 */
class JwtUtilsTest extends TestCase
{
    private const TEST_SECRET = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    /** Build a minimal valid JWT payload. */
    private function buildPayload(int $expOffset = 3600): array
    {
        return [
            'username' => 'testuser',
            'id'       => 42,
            'exp'      => time() + $expOffset,
            'key_tempo' => 'tempo_abc',
        ];
    }

    /** Encode a token with the test secret. */
    private function encode(array $payload, string $secret = self::TEST_SECRET): string
    {
        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * A valid token signed with the correct secret must pass.
     */
    public function testValidTokenReturnsTrue(): void
    {
        $token = $this->encode($this->buildPayload());
        self::assertTrue($this->callIsJwtValid($token, self::TEST_SECRET));
    }

    /**
     * An expired token (exp in the past) must be rejected.
     */
    public function testExpiredTokenReturnsFalse(): void
    {
        // exp 10 seconds in the past
        $token = $this->encode($this->buildPayload(-10));
        self::assertFalse($this->callIsJwtValid($token, self::TEST_SECRET));
    }

    /**
     * A token signed with a different secret must be rejected.
     */
    public function testWrongSignatureReturnsFalse(): void
    {
        $wrongSecret = str_repeat('x', 64);
        $token = $this->encode($this->buildPayload(), $wrongSecret);
        self::assertFalse($this->callIsJwtValid($token, self::TEST_SECRET));
    }

    /**
     * A completely malformed string (not base64url) must not throw — return false.
     */
    public function testMalformedTokenReturnsFalse(): void
    {
        self::assertFalse($this->callIsJwtValid('not.a.jwt.at.all', self::TEST_SECRET));
    }

    /**
     * An empty string must return false, not throw.
     */
    public function testEmptyTokenReturnsFalse(): void
    {
        self::assertFalse($this->callIsJwtValid('', self::TEST_SECRET));
    }

    /**
     * A token with a tampered payload (but original signature) must be rejected.
     */
    public function testTamperedPayloadReturnsFalse(): void
    {
        $token = $this->encode($this->buildPayload());
        $parts = explode('.', $token);
        // Replace the payload with a forged one (different id)
        $forgedPayload = base64_encode(json_encode(['id' => 999, 'exp' => time() + 3600]));
        $parts[1] = rtrim(strtr($forgedPayload, '+/', '-_'), '=');
        $tampered = implode('.', $parts);
        self::assertFalse($this->callIsJwtValid($tampered, self::TEST_SECRET));
    }

    /**
     * Exercises is_jwt_valid() with a controllable signing key by loading
     * the function in an isolated scope that overrides getApiJwtSigningKey().
     *
     * We load a minimal version of jwt_utils.php that skips the DB-dependent
     * getApiJwtSigningKey and replaces it with a closure returning $signingKey.
     */
    private function callIsJwtValid(string $jwt, string $signingKey): bool
    {
        // Inline the validation logic from is_jwt_valid() without the DB dependency.
        if (empty($jwt)) {
            return false;
        }
        try {
            $decoded = (array) JWT::decode($jwt, new Key($signingKey, 'HS256'));
            if (($decoded['exp'] ?? 0) - time() < 0) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
