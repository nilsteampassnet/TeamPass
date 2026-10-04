<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secret_settings_logic.php';

/**
 * Credential settings stored encrypted at rest (secret_settings_logic.php): which settings they
 * are, and how a stored value is told apart from plaintext stored before the change.
 */
class SecretSettingsLogicTest extends TestCase
{
    /**
     * Real Defuse output: "example" encrypted under a throwaway random key, so the format check is
     * tested against what cryption() actually writes.
     */
    private const DEFUSE_CIPHERTEXT = 'def50200ef54a13c4ecb59e2837e34c506e1112f9ff967ebe73225c68194768999b7486bc430d7521f8feba7205505b8d9538b10d08a8fbe206f3e0c9dface105c18b558a25e9f15ac5b63cfb7655cd9f780920be8d3b850faa924';

    public function testTheLdapBindAndSmtpPasswordsAreCredentialSettings(): void
    {
        $this->assertSame(['ldap_password', 'email_auth_pwd'], tpSecretSettingNames());
    }

    public function testDefuseCiphertextIsRecognised(): void
    {
        $this->assertTrue(tpIsInstanceKeyCiphertext(self::DEFUSE_CIPHERTEXT));
    }

    public function testPlaintextIsNotTakenForCiphertext(): void
    {
        $this->assertFalse(tpIsInstanceKeyCiphertext(''));
        $this->assertFalse(tpIsInstanceKeyCiphertext('Passw0rd!'));
        $this->assertFalse(tpIsInstanceKeyCiphertext("Te&st'x\"<b>1',2"));
        // A password made only of hex digits is still plaintext without the version header.
        $this->assertFalse(tpIsInstanceKeyCiphertext('0123456789abcdef'));
    }

    public function testTheHeaderAloneOrMisplacedIsNotCiphertext(): void
    {
        $this->assertFalse(tpIsInstanceKeyCiphertext('def50200'));
        $this->assertFalse(tpIsInstanceKeyCiphertext('x' . self::DEFUSE_CIPHERTEXT));
        $this->assertFalse(tpIsInstanceKeyCiphertext(self::DEFUSE_CIPHERTEXT . ' '));
        $this->assertFalse(tpIsInstanceKeyCiphertext('def50200zz' . substr(self::DEFUSE_CIPHERTEXT, 10)));
    }
}
