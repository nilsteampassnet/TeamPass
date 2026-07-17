<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\CryptoManager\CryptoManager;

/**
 * Unit tests for CryptoManager.
 *
 * Covers RSA and AES operations (phpseclib v3).
 * RSA key pairs are generated once per class (1024-bit) for speed.
 *
 * DB-dependent operations (sharekey migration, per-item encryption flow)
 * belong in integration tests and are not covered here.
 */
class CryptoManagerTest extends TestCase
{
    /** @var array{privatekey:string,publickey:string} */
    private static array $keyPair;

    /**
     * Generate one RSA key pair for the whole test class.
     * 1024-bit keys are fast enough for unit tests.
     */
    public static function setUpBeforeClass(): void
    {
        self::$keyPair = CryptoManager::generateRSAKeyPair(1024);
    }

    // =========================================================================
    // generateRSAKeyPair
    // =========================================================================

    public function testGenerateRSAKeyPairReturnsPrivateKey(): void
    {
        $this->assertNotEmpty(self::$keyPair['privatekey']);
        $this->assertStringContainsString('-----BEGIN', self::$keyPair['privatekey']);
    }

    public function testGenerateRSAKeyPairReturnsPublicKey(): void
    {
        $this->assertNotEmpty(self::$keyPair['publickey']);
        $this->assertStringContainsString('-----BEGIN', self::$keyPair['publickey']);
    }

    public function testGenerateRSAKeyPairKeysAreDifferent(): void
    {
        $this->assertNotEquals(self::$keyPair['privatekey'], self::$keyPair['publickey']);
    }

    public function testGenerateRSAKeyPairProducesDifferentPairsEachCall(): void
    {
        $pair1 = CryptoManager::generateRSAKeyPair(1024);
        $pair2 = CryptoManager::generateRSAKeyPair(1024);

        $this->assertNotEquals($pair1['privatekey'], $pair2['privatekey']);
        $this->assertNotEquals($pair1['publickey'], $pair2['publickey']);
    }

    // =========================================================================
    // rsaEncrypt / rsaDecrypt — round-trip
    // =========================================================================

    public function testRsaEncryptDecryptRoundTrip(): void
    {
        $plain     = 'my-secret-object-key';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);
        $decrypted = CryptoManager::rsaDecrypt($encrypted, self::$keyPair['privatekey']);

        $this->assertSame($plain, $decrypted);
    }

    public function testRsaEncryptedOutputDiffersFromPlainText(): void
    {
        $plain     = 'plaintext-data';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $this->assertNotEquals($plain, $encrypted);
    }

    public function testRsaEncryptSameInputProducesDifferentCiphertexts(): void
    {
        // OAEP padding embeds random bytes → two encryptions must differ
        $plain = 'same-plaintext';
        $c1    = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);
        $c2    = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $this->assertNotEquals($c1, $c2);
    }

    public function testRsaEncryptDecryptRoundTripWithBase64EncodedPublicKey(): void
    {
        // Production code stores keys base64-encoded in the DB
        $plain         = 'base64-key-test';
        $base64PubKey  = base64_encode(self::$keyPair['publickey']);
        $base64PrivKey = base64_encode(self::$keyPair['privatekey']);

        $encrypted = CryptoManager::rsaEncrypt($plain, $base64PubKey);
        $decrypted = CryptoManager::rsaDecrypt($encrypted, $base64PrivKey);

        $this->assertSame($plain, $decrypted);
    }

    public function testRsaEncryptDecryptRoundTripWithSpecialCharacters(): void
    {
        $plain     = "P@\$\$w0rd!#%^&*()_+-=[]{}|\nLine2\téàü中文";
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);
        $decrypted = CryptoManager::rsaDecrypt($encrypted, self::$keyPair['privatekey']);

        $this->assertSame($plain, $decrypted);
    }

    public function testRsaDecryptWithWrongKeyThrowsException(): void
    {
        $this->expectException(Exception::class);

        $wrongPair = CryptoManager::generateRSAKeyPair(1024);
        $encrypted = CryptoManager::rsaEncrypt('secret', self::$keyPair['publickey']);

        // Decrypt with mismatched private key must throw
        CryptoManager::rsaDecrypt($encrypted, $wrongPair['privatekey'], false);
    }

    public function testRsaDecryptWithInvalidDataThrowsException(): void
    {
        $this->expectException(Exception::class);

        CryptoManager::rsaDecrypt('not-valid-ciphertext', self::$keyPair['privatekey'], false);
    }

    public function testRsaEncryptWithInvalidKeyThrowsException(): void
    {
        $this->expectException(Exception::class);

        CryptoManager::rsaEncrypt('data', 'this-is-not-a-valid-key');
    }

    // =========================================================================
    // rsaDecryptWithVersionDetection
    // =========================================================================

    public function testRsaDecryptWithVersionDetectionReturnsVersionThreeForCurrentData(): void
    {
        $plain     = 'version-detection-test';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $result = CryptoManager::rsaDecryptWithVersionDetection($encrypted, self::$keyPair['privatekey']);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(3, $result['version_used']);
    }

    public function testRsaDecryptWithVersionDetectionResultHasRequiredKeys(): void
    {
        $encrypted = CryptoManager::rsaEncrypt('test', self::$keyPair['publickey']);
        $result    = CryptoManager::rsaDecryptWithVersionDetection($encrypted, self::$keyPair['privatekey']);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('version_used', $result);
    }

    public function testRsaDecryptWithVersionDetectionVersionIsIntegerOneOrThree(): void
    {
        $encrypted = CryptoManager::rsaEncrypt('test', self::$keyPair['publickey']);
        $result    = CryptoManager::rsaDecryptWithVersionDetection($encrypted, self::$keyPair['privatekey']);

        $this->assertContains($result['version_used'], [1, 3]);
    }

    // =========================================================================
    // rsaDecryptWithVersion
    // =========================================================================

    public function testRsaDecryptWithVersionThreeMatchesStandardDecrypt(): void
    {
        $plain     = 'explicit-version-3';
        $encrypted = CryptoManager::rsaEncrypt($plain, self::$keyPair['publickey']);

        $decrypted = CryptoManager::rsaDecryptWithVersion($encrypted, self::$keyPair['privatekey'], 3);

        $this->assertSame($plain, $decrypted);
    }

    // =========================================================================
    // getCurrentVersion
    // =========================================================================

    public function testGetCurrentVersionReturnsThree(): void
    {
        $this->assertSame(3, CryptoManager::getCurrentVersion());
    }

    // =========================================================================
    // aesEncrypt / aesDecrypt — round-trip (sha1)
    // =========================================================================

    public function testAesEncryptDecryptRoundTripWithSha1(): void
    {
        $plain    = 'aes-secret-with-sha1';
        $password = 'my-password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha1');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha1');

        $this->assertSame($plain, $decrypted);
    }

    public function testAesEncryptDecryptRoundTripWithSha256(): void
    {
        $plain    = 'aes-secret-with-sha256';
        $password = 'my-password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha256');

        $this->assertSame($plain, $decrypted);
    }

    public function testAesEncryptedOutputDiffersFromPlainText(): void
    {
        $plain     = 'visible-data';
        $encrypted = CryptoManager::aesEncrypt($plain, 'password', 'cbc', 'sha256');

        $this->assertNotEquals($plain, $encrypted);
    }

    public function testAesEncryptDecryptRoundTripWithSpecialCharacters(): void
    {
        $plain    = "P@\$\$w0rd!#%^&*()\nLine2\téàü中文日本語";
        $password = 'complex-password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha256');

        $this->assertSame($plain, $decrypted);
    }

    public function testAesEncryptDecryptRoundTripWithLongData(): void
    {
        $plain    = str_repeat('abcdefghij', 500); // 5000 chars
        $password = 'password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $decrypted = CryptoManager::aesDecrypt($encrypted, $password, 'cbc', 'sha256');

        $this->assertSame($plain, $decrypted);
    }

    // =========================================================================
    // Cross-version AES: data encrypted with sha1, decrypted with sha256 must fail
    // =========================================================================

    public function testAesDecryptWithWrongHashAlgorithmDoesNotReturnOriginal(): void
    {
        // AES-CBC has no authentication; wrong key derivation (sha1 vs sha256)
        // produces binary garbage. CryptoManager detects this and throws.
        $plain    = 'cross-version-test';
        $password = 'password';

        $encryptedWithSha1 = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha1');

        $this->expectException(Exception::class);
        // Attempt to decrypt sha1-encrypted data using sha256 key derivation
        CryptoManager::aesDecrypt($encryptedWithSha1, $password, 'cbc', 'sha256');
    }

    // =========================================================================
    // aesDecryptWithVersionDetection
    // =========================================================================

    public function testAesDecryptWithVersionDetectionRecognisesVersionThree(): void
    {
        $plain    = 'v3-detection';
        $password = 'password';

        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha256');
        $result    = CryptoManager::aesDecryptWithVersionDetection($encrypted, $password);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(3, $result['version_used']);
    }

    public function testAesDecryptWithVersionDetectionRecognisesVersionOne(): void
    {
        $plain    = 'v1-detection';
        $password = 'password';

        // Encrypt with sha1 (v1 style) and check that detection falls back to v1
        $encrypted = CryptoManager::aesEncrypt($plain, $password, 'cbc', 'sha1');
        $result    = CryptoManager::aesDecryptWithVersionDetection($encrypted, $password);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(1, $result['version_used']);
    }

    public function testAesDecryptWithVersionDetectionResultHasRequiredKeys(): void
    {
        $encrypted = CryptoManager::aesEncrypt('test', 'pwd', 'cbc', 'sha256');
        $result    = CryptoManager::aesDecryptWithVersionDetection($encrypted, 'pwd');

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('version_used', $result);
    }

    // =========================================================================
    // createAESCipher / loadRSAKey — smoke tests
    // =========================================================================

    public function testCreateAESCipherReturnsCipherObject(): void
    {
        $cipher = CryptoManager::createAESCipher('cbc');

        $this->assertIsObject($cipher);
    }

    public function testLoadRSAKeyWithPublicKeyReturnsObject(): void
    {
        $key = CryptoManager::loadRSAKey(self::$keyPair['publickey']);

        $this->assertIsObject($key);
    }

    public function testLoadRSAKeyWithPrivateKeyReturnsObject(): void
    {
        $key = CryptoManager::loadRSAKey(self::$keyPair['privatekey']);

        $this->assertIsObject($key);
    }

    public function testLoadRSAKeyWithBase64EncodedKeyReturnsObject(): void
    {
        $key = CryptoManager::loadRSAKey(base64_encode(self::$keyPair['publickey']));

        $this->assertIsObject($key);
    }

    public function testLoadRSAKeyWithInvalidDataThrowsException(): void
    {
        $this->expectException(Exception::class);

        CryptoManager::loadRSAKey('this-is-not-a-key');
    }

    // =========================================================================
    // AES v2 (authenticated GCM) — aesGcmEncrypt / aesGcmDecrypt
    // =========================================================================

    public function testAesGcmEncryptDecryptRoundTrip(): void
    {
        $objectKey = random_bytes(16);
        $plain     = 'gcm-secret-value';

        $enc = CryptoManager::aesGcmEncrypt($plain, $objectKey);
        $dec = CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], $objectKey);

        $this->assertSame($plain, $dec);
    }

    public function testAesGcmRoundTripWithSpecialCharacters(): void
    {
        $objectKey = random_bytes(16);
        $plain     = "P@\$\$w0rd!#%^&*()_+-=[]{}|\nLine2\téàü中文日本語<>&\"'";

        $enc = CryptoManager::aesGcmEncrypt($plain, $objectKey);
        $dec = CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], $objectKey);

        $this->assertSame($plain, $dec);
    }

    public function testAesGcmRoundTripWithLongData(): void
    {
        $objectKey = random_bytes(16);
        $plain     = str_repeat('abcdefghij', 500); // 5000 chars

        $enc = CryptoManager::aesGcmEncrypt($plain, $objectKey);
        $dec = CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], $objectKey);

        $this->assertSame($plain, $dec);
    }

    public function testAesGcmRoundTripWithEmptyPlaintext(): void
    {
        $objectKey = random_bytes(16);

        $enc = CryptoManager::aesGcmEncrypt('', $objectKey);
        $dec = CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], $objectKey);

        $this->assertSame('', $dec);
    }

    public function testAesGcmMetaHasExpectedLength(): void
    {
        // meta = version[1] | nonce[12] | salt[32] = 45 raw bytes
        $enc = CryptoManager::aesGcmEncrypt('x', random_bytes(16));

        $this->assertSame(45, strlen($enc['meta']));
        $this->assertSame(2, ord($enc['meta'][0])); // version byte = 0x02
    }

    public function testAesGcmAppendsSixteenByteTag(): void
    {
        // ciphertext length == plaintext length + 16-byte GCM tag (no block padding in GCM)
        $plain = 'exact-length-check';
        $enc   = CryptoManager::aesGcmEncrypt($plain, random_bytes(16));

        $this->assertSame(strlen($plain) + 16, strlen($enc['ciphertext']));
    }

    public function testAesGcmSameInputProducesDifferentCiphertext(): void
    {
        // Random nonce + salt per call → identical plaintext/key must yield different output,
        // unlike the legacy fixed-IV CBC path (SEC-1).
        $objectKey = random_bytes(16);
        $plain     = 'same-plaintext';

        $a = CryptoManager::aesGcmEncrypt($plain, $objectKey);
        $b = CryptoManager::aesGcmEncrypt($plain, $objectKey);

        $this->assertNotEquals($a['ciphertext'], $b['ciphertext']);
        $this->assertNotEquals($a['meta'], $b['meta']);
    }

    public function testAesGcmTamperedTagThrows(): void
    {
        $objectKey = random_bytes(16);
        $enc       = CryptoManager::aesGcmEncrypt('authentic', $objectKey);

        // Flip the last byte (part of the auth tag) → authentication must fail.
        $tampered = $enc['ciphertext'];
        $last     = strlen($tampered) - 1;
        $tampered[$last] = chr(ord($tampered[$last]) ^ 0xFF);

        $this->expectException(Exception::class);
        CryptoManager::aesGcmDecrypt($tampered, $enc['meta'], $objectKey);
    }

    public function testAesGcmTamperedCiphertextThrows(): void
    {
        $objectKey = random_bytes(16);
        $enc       = CryptoManager::aesGcmEncrypt('authentic-body', $objectKey);

        // Flip a byte inside the ciphertext body (before the tag).
        $tampered     = $enc['ciphertext'];
        $tampered[0]  = chr(ord($tampered[0]) ^ 0xFF);

        $this->expectException(Exception::class);
        CryptoManager::aesGcmDecrypt($tampered, $enc['meta'], $objectKey);
    }

    public function testAesGcmWrongKeyThrows(): void
    {
        $enc = CryptoManager::aesGcmEncrypt('secret', random_bytes(16));

        $this->expectException(Exception::class);
        CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], random_bytes(16));
    }

    public function testAesGcmTruncatedMetaThrows(): void
    {
        $objectKey = random_bytes(16);
        $enc       = CryptoManager::aesGcmEncrypt('secret', $objectKey);

        $this->expectException(Exception::class);
        CryptoManager::aesGcmDecrypt($enc['ciphertext'], substr($enc['meta'], 0, 10), $objectKey);
    }

    public function testAesGcmUnsupportedVersionThrows(): void
    {
        $objectKey = random_bytes(16);
        $enc       = CryptoManager::aesGcmEncrypt('secret', $objectKey);

        $badMeta    = $enc['meta'];
        $badMeta[0] = chr(99); // unknown version byte

        $this->expectException(Exception::class);
        CryptoManager::aesGcmDecrypt($enc['ciphertext'], $badMeta, $objectKey);
    }

    public function testAesGcmUnknownKdfThrows(): void
    {
        $this->expectException(Exception::class);
        CryptoManager::aesGcmEncrypt('secret', random_bytes(16), '', 'bogus-kdf');
    }

    public function testAesGcmPbkdf2RoundTrip(): void
    {
        // private_key path: human password as key material, PBKDF2-SHA256.
        $password = 'human-password-123';
        $plain    = '-----BEGIN RSA PRIVATE KEY----- ... -----END RSA PRIVATE KEY-----';

        $enc = CryptoManager::aesGcmEncrypt($plain, $password, '', 'pbkdf2');
        $dec = CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], $password, '', 'pbkdf2');

        $this->assertSame($plain, $dec);
    }

    public function testAesGcmHkdfKeyDerivationIsDeterministic(): void
    {
        // Same key material + same meta (nonce/salt) → identical decryption,
        // proving HKDF derivation is deterministic for a given salt.
        $objectKey = random_bytes(16);
        $plain     = 'deterministic-kdf';

        $enc = CryptoManager::aesGcmEncrypt($plain, $objectKey);
        $d1  = CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], $objectKey);
        $d2  = CryptoManager::aesGcmDecrypt($enc['ciphertext'], $enc['meta'], $objectKey);

        $this->assertSame($d1, $d2);
        $this->assertSame($plain, $d1);
    }

    // =========================================================================
    // aesDecryptAuto — v1/v2 format dispatch
    // =========================================================================

    public function testAesDecryptAutoDetectsV2(): void
    {
        $objectKey = random_bytes(16);
        $plain     = 'auto-v2';

        $enc    = CryptoManager::aesGcmEncrypt($plain, $objectKey);
        $result = CryptoManager::aesDecryptAuto($enc['ciphertext'], $enc['meta'], $objectKey);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(2, $result['version_used']);
    }

    public function testAesDecryptAutoDetectsLegacyCbc(): void
    {
        // Legacy data has empty meta → CBC path, version_used = 1.
        $objectKey = random_bytes(16);
        $plain     = 'auto-legacy';

        $legacyCiphertext = CryptoManager::aesEncrypt($plain, $objectKey, 'cbc', 'sha256');
        $result           = CryptoManager::aesDecryptAuto($legacyCiphertext, '', $objectKey);

        $this->assertSame($plain, $result['data']);
        $this->assertSame(1, $result['version_used']);
    }

    // =========================================================================
    // private_key v2 format — "v2:<base64(meta)>:<base64(ciphertext)>" (PBKDF2)
    // Mirrors the encryptPrivateKey()/decryptPrivateKey() v2 path.
    // =========================================================================

    public function testPrivateKeyV2FormatRoundTrip(): void
    {
        $password = 'user-Password-<>&é中';
        $pem      = "-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJ...placeholder...\n-----END RSA PRIVATE KEY-----";

        // encryptPrivateKey() v2 emission
        $v2     = CryptoManager::aesGcmEncrypt($pem, $password, '', 'pbkdf2');
        $stored = 'v2:' . base64_encode($v2['meta']) . ':' . base64_encode($v2['ciphertext']);

        $this->assertStringStartsWith('v2:', $stored);

        // decryptPrivateKey() v2 parse
        $parts = explode(':', $stored, 3);
        $this->assertCount(3, $parts);

        $decrypted = CryptoManager::aesGcmDecrypt(
            base64_decode($parts[2]),
            base64_decode($parts[1]),
            $password,
            '',
            'pbkdf2'
        );

        $this->assertSame($pem, $decrypted);
        $this->assertStringContainsString('-----BEGIN', $decrypted);
    }

    public function testPrivateKeyV2WrongPasswordThrows(): void
    {
        // GCM authentication rejects a wrong password cleanly (no CBC-style garbage output).
        $v2 = CryptoManager::aesGcmEncrypt('-----BEGIN RSA PRIVATE KEY-----x', 'right-password', '', 'pbkdf2');

        $this->expectException(Exception::class);
        CryptoManager::aesGcmDecrypt($v2['ciphertext'], $v2['meta'], 'wrong-password', '', 'pbkdf2');
    }
}
