<?php

declare(strict_types=1);

namespace TeamPass\Tests\EmptyPrivateKeyPasswordChange;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function TeamPass\Tests\SourceStructure\functionSource;

require_once __DIR__ . '/../Fixtures/php_source_structure.php';

/** Stands in for the real class, so a failure can be forced without the 600k PBKDF2 rounds. */
final class CryptoManager
{
    public static bool $v2 = false;
    public static bool $fail = false;

    /** Mirror of CryptoManager::aesGcmEncrypt(). */
    public static function aesGcmEncrypt(string $data, string $password, string $aad, string $kdf): array
    {
        if (self::$fail === true) {
            throw new Exception('cipher failure');
        }
        return ['meta' => 'meta', 'ciphertext' => 'gcm:' . $data];
    }

    /** Mirror of CryptoManager::aesEncrypt(). */
    public static function aesEncrypt(string $data, string $password, string $mode, string $hash): string
    {
        if (self::$fail === true) {
            throw new Exception('cipher failure');
        }
        return 'cbc:' . $data;
    }
}

/** Mirror of the aes_v2_write_enabled setting read by the production function. */
function aesV2WriteEnabled(): bool
{
    return CryptoManager::$v2;
}

/**
 * A password change must never re-encrypt an empty private key.
 *
 * decryptPrivateKey() returns '' instead of throwing when the password does not open the key, and
 * the session key is empty when it could not be loaded (for example right after the keys were
 * regenerated during the same session). Re-encrypting '' with the new password stores a valid
 * looking but empty key, and its recovery backup with it: the user can no longer decrypt any item
 * and only an administrator can recover the account by generating new keys.
 */
final class EmptyPrivateKeyPasswordChangeTest extends TestCase
{
    /** Reset the double before each test. */
    protected function setUp(): void
    {
        CryptoManager::$v2 = false;
        CryptoManager::$fail = false;
    }

    /** Source of a production function, located by PHP tokens. */
    private function functionBody(string $file, string $name): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/app/sources/' . $file);
        self::assertIsString($content);

        return functionSource($content, $name);
    }

    /** Load the production encryptPrivateKey() into this namespace, against the double above. */
    private function loadEncryptPrivateKey(): void
    {
        if (function_exists(__NAMESPACE__ . '\\encryptPrivateKey') === false) {
            eval('namespace ' . __NAMESPACE__ . '; use Exception; use RuntimeException; '
                . $this->functionBody('main.functions.php', 'encryptPrivateKey'));
        }
    }

    /**
     * What encryptPrivateKey() threw. fail() stays outside the try: PHPUnit's own assertion
     * failure is a RuntimeException too, and would be caught as the expected refusal.
     */
    private function encryptionRefusal(string $password, string $key): RuntimeException
    {
        $this->loadEncryptPrivateKey();
        try {
            $result = encryptPrivateKey($password, $key);
        } catch (RuntimeException $e) {
            return $e;
        }
        self::fail('encryptPrivateKey() returned a value instead of throwing: ' . $result);
    }

    public function testDialogPasswordChangeRefusesAKeyTheCurrentPasswordCannotOpen(): void
    {
        $block = $this->functionBody('main.queries.php', 'changeUserAuthenticationPassword');

        $decrypt = strpos($block, '$privateKey = decryptPrivateKey($post_current_pwd');
        $guard = strpos($block, "if (\$privateKey === '') {");
        $encrypt = strpos($block, 'encryptPrivateKey($post_new_pwd, $privateKey)');
        self::assertNotFalse($decrypt);
        self::assertNotFalse($guard, 'An empty decrypted key must be refused.');
        self::assertNotFalse($encrypt);
        self::assertGreaterThan($decrypt, $guard);
        self::assertLessThan($encrypt, $guard, 'The check must come before the key is re-encrypted.');
    }

    public function testProfilePasswordChangeRefusesAnEmptyKeyBeforeTouchingTheSession(): void
    {
        $block = $this->functionBody('main.queries.php', 'changePassword');

        $reset = strpos($block, '$privateKeyClear = decryptPrivateKey($post_current_password');
        $guard = strpos($block, "if (\$privateKeyClear === '') {");
        $session = strpos($block, "\$session->set('user-validite_pw', 1);");
        $encrypt = strpos($block, 'encryptPrivateKey($post_new_password, $privateKeyClear)');
        $write = strpos($block, "'private_key' => \$newEncryptedPrivateKey,");
        self::assertNotFalse($reset);
        self::assertNotFalse($guard, 'An empty key must be refused.');
        self::assertNotFalse($session);
        self::assertNotFalse($encrypt);
        self::assertNotFalse($write);
        self::assertGreaterThan($reset, $guard, 'The decrypted key of a forced change is checked too.');
        self::assertLessThan($session, $guard, 'A refused change must not mark the password as valid.');
        self::assertGreaterThan($encrypt, $session, 'Neither must an encryption that throws.');
        self::assertLessThan($encrypt, $guard, 'The check must come before the key is re-encrypted.');
        self::assertLessThan($write, $guard, 'The check must come before any write.');
        self::assertStringNotContainsString(
            "encryptPrivateKey(\$post_new_password, \$session->get('user-private_key'))",
            $block,
            'The key that was checked is the key that is encrypted.'
        );
    }

    public function testTemporaryCodeChangeEncryptsOnlyTheKeyItOpened(): void
    {
        $block = $this->functionBody('main.queries.php', 'changePrivateKeyEncryptionPassword');

        $fallback = strpos($block, "\$privateKey = decryptPrivateKey(\$post_new_code, \$userData['private_key']);");
        $encrypt = strpos($block, '$hashedPrivateKey = encryptPrivateKey($post_new_code, $privateKey);');
        self::assertNotFalse($fallback);
        self::assertNotFalse($encrypt);
        self::assertSame(1, substr_count($block, 'encryptPrivateKey('));
        self::assertGreaterThan(
            $fallback,
            $encrypt,
            'Encrypted before the fallback, the v2 ciphertext of an empty key (88 characters) passed the length check.'
        );
    }

    public function testEncryptionRefusesAnEmptyKey(): void
    {
        foreach ([false, true] as $v2) {
            CryptoManager::$v2 = $v2;
            $refusal = $this->encryptionRefusal('Passw0rd!', '');
            self::assertStringContainsString('empty key', $refusal->getMessage());
        }
    }

    public function testEncryptionRefusesAnEmptyPassword(): void
    {
        $refusal = $this->encryptionRefusal('', base64_encode('-----BEGIN PRIVATE KEY-----'));
        self::assertStringContainsString('empty password', $refusal->getMessage());
    }

    public function testEncryptionFailureIsNeverReturnedAsTheKey(): void
    {
        CryptoManager::$fail = true;

        foreach ([false, true] as $v2) {
            CryptoManager::$v2 = $v2;
            $refusal = $this->encryptionRefusal('Passw0rd!', base64_encode('-----BEGIN PRIVATE KEY-----'));
            self::assertSame('Private key encryption failed.', $refusal->getMessage());
            self::assertInstanceOf(Exception::class, $refusal->getPrevious());
            self::assertSame('cipher failure', $refusal->getPrevious()->getMessage());
        }
    }

    public function testEncryptionStillReturnsTheCiphertext(): void
    {
        $this->loadEncryptPrivateKey();
        $key = '-----BEGIN PRIVATE KEY-----';

        self::assertSame(base64_encode('cbc:' . $key), encryptPrivateKey('Passw0rd!', base64_encode($key)));

        CryptoManager::$v2 = true;
        self::assertSame(
            'v2:' . base64_encode('meta') . ':' . base64_encode('gcm:' . $key),
            encryptPrivateKey('Passw0rd!', base64_encode($key))
        );
    }

    public function testSignInNeverFailsOnARefusedEncryption(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/app/sources/identify.php');
        self::assertIsString($content);

        $calls = substr_count($content, 'encryptPrivateKey(');
        $guarded = preg_match_all(
            '/try\s*\{\s*\$\w+\s*=\s*encryptPrivateKey\([^;]*;.*?\}\s*catch\s*\(RuntimeException \$e\)/s',
            $content
        );
        self::assertGreaterThan(0, $calls);
        self::assertSame($calls, $guarded, 'Every encryptPrivateKey() call of the sign-in must catch its RuntimeException.');
    }
}
