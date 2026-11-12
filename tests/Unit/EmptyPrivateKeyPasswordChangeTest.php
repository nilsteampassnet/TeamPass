<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * A password change must never re-encrypt an empty private key.
 *
 * decryptPrivateKey() returns '' instead of throwing when the password does not open the key, and
 * the session key is empty when it could not be loaded (for example right after the keys were
 * regenerated during the same session). Re-encrypting '' with the new password stores a valid
 * looking but empty key, and its recovery backup with it: the user can no longer decrypt any item
 * and only an administrator can recover the account by generating new keys.
 */
class EmptyPrivateKeyPasswordChangeTest extends TestCase
{
    private function source(string $file): string
    {
        $path = __DIR__ . '/../../app/sources/' . $file;
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    /**
     * Returns the part of $file between two markers.
     */
    private function block(string $file, string $startMarker, string $endMarker): string
    {
        $content = $this->source($file);
        $start = strpos($content, $startMarker);
        self::assertNotFalse($start, 'Marker not found: ' . $startMarker);
        $end = strpos($content, $endMarker, $start + strlen($startMarker));
        self::assertNotFalse($end, 'Marker not found: ' . $endMarker);

        return substr($content, $start, $end - $start);
    }

    public function testDialogPasswordChangeRefusesAKeyTheCurrentPasswordCannotOpen(): void
    {
        $block = $this->block('main.queries.php', 'function changeUserAuthenticationPassword(', '* Change user LDAP auth password');

        $decrypt = strpos($block, '$privateKey = decryptPrivateKey($post_current_pwd');
        $guard = strpos($block, "if (\$privateKey === '') {");
        $encrypt = strpos($block, 'encryptPrivateKey($post_new_pwd, $privateKey)');
        self::assertNotFalse($decrypt);
        self::assertNotFalse($guard, 'An empty decrypted key must be refused.');
        self::assertNotFalse($encrypt);
        self::assertGreaterThan($decrypt, $guard);
        self::assertLessThan($encrypt, $guard, 'The check must come before the key is re-encrypted.');
    }

    public function testProfilePasswordChangeRefusesAnEmptySessionKey(): void
    {
        $block = $this->block('main.queries.php', 'function changePassword(', 'function generateQRCode(');

        $guard = strpos($block, "if (empty(\$session->get('user-private_key')) === true) {");
        $encrypt = strpos($block, "encryptPrivateKey(\$post_new_password, \$session->get('user-private_key'))");
        $write = strpos($block, "'private_key' => \$newEncryptedPrivateKey,");
        self::assertNotFalse($guard, 'An empty session key must be refused.');
        self::assertNotFalse($encrypt);
        self::assertNotFalse($write);
        self::assertLessThan($encrypt, $guard, 'The check must come before the key is re-encrypted.');
        self::assertLessThan($write, $guard, 'The check must come before any write.');
    }
}
