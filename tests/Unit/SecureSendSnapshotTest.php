<?php

declare(strict_types=1);

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secure_send_snapshot.php';

class SecureSendSnapshotTest extends TestCase
{
    /** Small copies retain all fields and need no warning. */
    public function testSmallSnapshotIsUnchanged(): void
    {
        $item = ['label' => 'Label', 'login' => 'alice', 'url' => 'https://example.com', 'description' => '&lt;p&gt;Note&lt;/p&gt;'];
        $result = secureSendEncodeSnapshot($item, 'secret');
        self::assertFalse($result['description_truncated']);
        self::assertSame($item + ['password' => 'secret', 'description_truncated' => false],
            array_replace($item, json_decode($result['plaintext'], true, 512, JSON_THROW_ON_ERROR)));
    }

    /** Large HTML, escape-heavy text and multibyte descriptions fit the real hex ciphertext column. */
    public function testOnlyDescriptionIsTruncatedToTheActualCiphertextBudget(): void
    {
        foreach ([str_repeat('x', 70000), str_repeat("中文 😀 \"\\\n", 10000), str_repeat('&lt;p&gt;Text&lt;/p&gt;', 5000)] as $description) {
            $item = ['label' => 'Original label', 'login' => 'alice', 'url' => 'https://example.com/path', 'description' => $description];
            $result = secureSendEncodeSnapshot($item, ' complete password ');
            $key = Key::createNewRandomKey();
            $ciphertext = Crypto::encrypt($result['plaintext'], $key);
            self::assertLessThanOrEqual(65535, strlen($ciphertext));
            $payload = json_decode(Crypto::decrypt($ciphertext, $key), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($result['description_truncated']);
            self::assertTrue($payload['description_truncated']);
            self::assertSame($item['label'], $payload['label']);
            self::assertSame($item['login'], $payload['login']);
            self::assertSame($item['url'], $payload['url']);
            self::assertSame(' complete password ', $payload['password']);
            self::assertNotSame('', $payload['description']);
            self::assertTrue(str_starts_with($description, $payload['description']));
            self::assertTrue(mb_check_encoding($payload['description'], 'UTF-8'));
            self::assertSame($description, $item['description']);
        }
    }

    /** Oversized credentials are rejected; they must never be silently shortened. */
    public function testRequiredFieldsCannotBeTruncated(): void
    {
        $this->expectExceptionMessage('invalid_payload');
        secureSendEncodeSnapshot(['label' => 'Label', 'login' => '', 'url' => '', 'description' => 'Note'], str_repeat('s', 40000));
    }

    /** Malformed text is not silently replaced in a shared secret. */
    public function testInvalidUtf8IsRejected(): void
    {
        $this->expectExceptionMessage('invalid_payload');
        secureSendEncodeSnapshot(['label' => 'Label', 'login' => '', 'url' => '', 'description' => ''], "\xff");
    }
}
