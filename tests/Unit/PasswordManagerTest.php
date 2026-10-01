<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TeampassClasses\PasswordManager\PasswordManager;

/**
 * Unit tests for PasswordManager.
 *
 * Note: migratePassword() is excluded because it requires DB access
 * and global constants (WIP, NUMBER_ITEMS_IN_BATCH). It should be
 * covered by integration tests with a test database.
 */
class PasswordManagerTest extends TestCase
{
    private PasswordManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PasswordManager();
    }

    // --- hashPassword ---

    public function testHashPasswordReturnsNonEmptyString(): void
    {
        $hash = $this->manager->hashPassword('mypassword');

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
    }

    public function testHashPasswordDiffersFromPlainText(): void
    {
        $plain = 'mysecretpassword';
        $hash  = $this->manager->hashPassword($plain);

        $this->assertNotEquals($plain, $hash);
    }

    public function testHashPasswordProducesDifferentHashesEachCall(): void
    {
        // bcrypt embeds a random salt, so two hashes of the same password must differ
        $plain = 'samepassword';
        $hash1 = $this->manager->hashPassword($plain);
        $hash2 = $this->manager->hashPassword($plain);

        $this->assertNotEquals($hash1, $hash2);
    }

    // --- verifyPassword ---

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $plain = 'correctpassword';
        $hash  = $this->manager->hashPassword($plain);

        $this->assertTrue($this->manager->verifyPassword($hash, $plain));
    }

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $hash = $this->manager->hashPassword('correctpassword');

        $this->assertFalse($this->manager->verifyPassword($hash, 'wrongpassword'));
    }

    public function testVerifyPasswordReturnsFalseForEmptyPassword(): void
    {
        $hash = $this->manager->hashPassword('correctpassword');

        $this->assertFalse($this->manager->verifyPassword($hash, ''));
    }

    public function testVerifyPasswordHandlesSpecialCharacters(): void
    {
        $plain = 'P@$$w0rd!#%^&*()_+-=[]{}|;:\',.<>?`~\\"\'éàü';
        $hash  = $this->manager->hashPassword($plain);

        $this->assertTrue($this->manager->verifyPassword($hash, $plain));
    }

    public function testVerifyPasswordIsConsistentAcrossMultipleCalls(): void
    {
        $plain = 'consistent-password';
        $hash  = $this->manager->hashPassword($plain);

        // Verify the same hash multiple times — must always return true
        $this->assertTrue($this->manager->verifyPassword($hash, $plain));
        $this->assertTrue($this->manager->verifyPassword($hash, $plain));
    }

    // --- verifyPasswordWithbCrypt ---

    public function testVerifyPasswordWithbCryptReturnsTrueForCorrectPassword(): void
    {
        $plain = 'testpassword';
        $hash  = password_hash($plain, PASSWORD_BCRYPT);

        $this->assertTrue($this->manager->verifyPasswordWithbCrypt($plain, $hash));
    }

    public function testVerifyPasswordWithbCryptReturnsFalseForWrongPassword(): void
    {
        $hash = password_hash('correctpassword', PASSWORD_BCRYPT);

        $this->assertFalse($this->manager->verifyPasswordWithbCrypt('wrongpassword', $hash));
    }

    public function testVerifyPasswordWithbCryptReturnsFalseForEmptyPassword(): void
    {
        $hash = password_hash('correctpassword', PASSWORD_BCRYPT);

        $this->assertFalse($this->manager->verifyPasswordWithbCrypt('', $hash));
    }

    // --- matchLegacyBcryptHash (issue #5389) ---

    /**
     * Legacy hash as stored by 2.x / 3.0.x (PasswordLib or native bcrypt, cost 10).
     */
    private function legacyHash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Passwords that FILTER_SANITIZE_FULL_SPECIAL_CHARS changes: 3.0.x hashed that form.
     *
     * @return array<string, array{string}>
     */
    public static function passwordsChangedBySanitizerProvider(): array
    {
        return [
            'ampersand'    => ['Adm1n&Pass'],
            'double quote' => ['p"quote'],
            'single quote' => ["it's"],
            'angle signs'  => ['a<b>c'],
            'accent'       => ['accenté'],
            'mixed'        => ['P@$$w0rd!&"\'<>éàü'],
        ];
    }

    #[DataProvider('passwordsChangedBySanitizerProvider')]
    public function testLegacyHashOfSanitizedPasswordMatchesRawPassword(string $raw): void
    {
        $sanitized = (string) filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->assertNotSame($raw, $sanitized);

        // The login sends the raw password; the 3.0.x hash was computed on the sanitized one
        $this->assertSame('sanitized', $this->manager->matchLegacyBcryptHash($raw, $this->legacyHash($sanitized)));
    }

    #[DataProvider('passwordsChangedBySanitizerProvider')]
    public function testLegacyHashOfRawPasswordMatchesRawPassword(string $raw): void
    {
        $this->assertSame('raw', $this->manager->matchLegacyBcryptHash($raw, $this->legacyHash($raw)));
    }

    public function testLegacyHashOfPasswordWithoutSpecialCharsMatchesRaw(): void
    {
        $this->assertSame('raw', $this->manager->matchLegacyBcryptHash('plainAlnum123', $this->legacyHash('plainAlnum123')));
    }

    public function testLegacyHashDoesNotMatchWrongPassword(): void
    {
        $hash = $this->legacyHash((string) filter_var('Adm1n&Pass', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

        $this->assertNull($this->manager->matchLegacyBcryptHash('Adm1n&Pas', $hash));
        $this->assertNull($this->manager->matchLegacyBcryptHash('', $hash));
    }

    public function testLegacyHashIsNotMatchedOnHtmlDecodedPassword(): void
    {
        // A raw password holding a literal entity must not be decoded before verification
        $raw = 'lit&amp;eral';

        $this->assertSame('raw', $this->manager->matchLegacyBcryptHash($raw, $this->legacyHash($raw)));
        $this->assertNull($this->manager->matchLegacyBcryptHash($raw, $this->legacyHash('lit&eral')));
    }

    /**
     * checkCredentials() tries the sanitized password after the raw one. That second attempt
     * must never match a legacy hash the first one missed, or the key regeneration of
     * migratePassword() would run with the sanitized password. It holds because sanitizing
     * twice gives the same result as sanitizing once.
     */
    #[DataProvider('passwordsChangedBySanitizerProvider')]
    public function testSanitizerIsIdempotent(string $raw): void
    {
        $once = (string) filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $this->assertSame($once, (string) filter_var($once, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    }

    // --- cross-check: hash from hashPassword is verifiable with bcrypt native ---

    public function testHashedPasswordIsVerifiableWithNativeBcrypt(): void
    {
        // Symfony PasswordHasher with 'auto' produces a bcrypt/argon2 hash.
        // When it produces bcrypt ($2y$), PHP's native password_verify must agree.
        $plain = 'crosscheckpassword';
        $hash  = $this->manager->hashPassword($plain);

        // password_verify handles multiple algorithms; this confirms cross-compatibility
        $this->assertTrue(password_verify($plain, $hash));
    }
}
