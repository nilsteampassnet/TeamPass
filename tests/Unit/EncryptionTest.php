<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\Encryption\Encryption;

/**
 * Unit tests for the Encryption class (AES-256-CBC + PBKDF2/SHA-512).
 *
 * All methods are static, so no instantiation is needed.
 */
class EncryptionTest extends TestCase
{
    private string $key = 'unit-test-key-for-teampass-encryption';

    // --- encrypt() output format ---

    public function testEncryptReturnsNonEmptyString(): void
    {
        $result = Encryption::encrypt('hello world', $this->key);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testEncryptReturnsValidBase64(): void
    {
        $result  = Encryption::encrypt('hello world', $this->key);
        $decoded = base64_decode($result, true);

        $this->assertNotFalse($decoded, 'Encrypted output must be valid base64');
    }

    public function testEncryptedOutputContainsExpectedJsonFields(): void
    {
        $encrypted = Encryption::encrypt('test payload', $this->key);
        $payload   = json_decode(base64_decode($encrypted), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('ciphertext', $payload);
        $this->assertArrayHasKey('iv', $payload);
        $this->assertArrayHasKey('salt', $payload);
        $this->assertArrayHasKey('iterations', $payload);
    }

    public function testEncryptUsesExpectedIterationCount(): void
    {
        $encrypted = Encryption::encrypt('test', $this->key);
        $payload   = json_decode(base64_decode($encrypted), true);

        $this->assertEquals(999, $payload['iterations']);
    }

    // --- encrypt() randomness ---

    public function testEncryptSameInputProducesDifferentCiphertexts(): void
    {
        // Random IV + random salt must make each encryption unique
        $plain      = 'same message every time';
        $encrypted1 = Encryption::encrypt($plain, $this->key);
        $encrypted2 = Encryption::encrypt($plain, $this->key);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    // --- decrypt() round-trips ---

    public function testDecryptRoundTripWithSimpleString(): void
    {
        $original  = 'This is a secret message';
        $encrypted = Encryption::encrypt($original, $this->key);

        $this->assertEquals($original, Encryption::decrypt($encrypted, $this->key));
    }

    public function testDecryptRoundTripWithEmptyString(): void
    {
        $original  = '';
        $encrypted = Encryption::encrypt($original, $this->key);

        $this->assertEquals($original, Encryption::decrypt($encrypted, $this->key));
    }

    public function testDecryptRoundTripWithSpecialCharacters(): void
    {
        $original  = 'P@$$w0rd!#%^&*()_+-=[]{}|;:,.<>?`~\\"\'éàü中文日本語';
        $encrypted = Encryption::encrypt($original, $this->key);

        $this->assertEquals($original, Encryption::decrypt($encrypted, $this->key));
    }

    public function testDecryptRoundTripWithLongString(): void
    {
        $original  = str_repeat('abcdefghij', 1000); // 10 000 chars
        $encrypted = Encryption::encrypt($original, $this->key);

        $this->assertEquals($original, Encryption::decrypt($encrypted, $this->key));
    }

    public function testDecryptRoundTripWithMultilineString(): void
    {
        $original  = "line one\nline two\r\nline three\ttabbed";
        $encrypted = Encryption::encrypt($original, $this->key);

        $this->assertEquals($original, Encryption::decrypt($encrypted, $this->key));
    }

    // --- decrypt() with invalid inputs ---

    public function testDecryptWithWrongKeyDoesNotReturnOriginal(): void
    {
        // AES decryption with a wrong key produces garbage, not the original plaintext
        $original  = 'my secret message';
        $encrypted = Encryption::encrypt($original, $this->key);

        $result = Encryption::decrypt($encrypted, 'completely-different-wrong-key');

        $this->assertNotEquals($original, $result);
    }

    public function testDecryptMalformedBase64ReturnsNull(): void
    {
        // json_decode fails → $json is not an array → early return null
        $result = Encryption::decrypt('!!!not-valid-base64!!!', $this->key);

        $this->assertNull($result);
    }

    public function testDecryptValidBase64ButInvalidJsonReturnsNull(): void
    {
        // Valid base64, but content is not JSON → same guard → null
        $result = Encryption::decrypt(base64_encode('this is not json'), $this->key);

        $this->assertNull($result);
    }
}
