<?php

declare(strict_types=1);

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
