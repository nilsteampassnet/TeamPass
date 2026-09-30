<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      SecureSendSnapshotTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secure_send_snapshot.php';
require_once __DIR__ . '/../../app/sources/secure_send_input.php';

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

    /** TOTP is optional and its complete profile stays inside the encrypted payload. */
    public function testTotpProfileIsIncludedOnlyWhenPresent(): void
    {
        $item = ['label' => 'Label', 'login' => 'alice', 'url' => '', 'description' => ''];
        $withoutTotp = json_decode(secureSendEncodeSnapshot($item, 'secret')['plaintext'], true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('totp', $withoutTotp);

        $profile = ['secret' => 'JBSWY3DPEHPK3PXP', 'algorithm' => 'sha256', 'digits' => 8, 'period' => 60];
        $withTotp = json_decode(secureSendEncodeSnapshot($item, 'secret', $profile)['plaintext'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($profile, $withTotp['totp']);
    }

    /** An available TOTP profile stays out of the snapshot until the sender opts in. */
    public function testTotpProfileIsExcludedWithoutTheIncludeFlag(): void
    {
        $item = ['label' => 'Label', 'login' => 'alice', 'url' => '', 'description' => ''];
        $profile = ['secret' => 'JBSWY3DPEHPK3PXP', 'algorithm' => 'sha1', 'digits' => 6, 'period' => 30];
        $totp = secureSendShouldIncludeTotp(['include_totp' => 0], false) ? $profile : null;
        $payload = json_decode(secureSendEncodeSnapshot($item, 'secret', $totp)['plaintext'], true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('totp', $payload);
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
            self::assertArrayNotHasKey('totp', $payload);
            self::assertNotSame('', $payload['description']);
            self::assertTrue(str_starts_with($description, $payload['description']));
            self::assertTrue(mb_check_encoding($payload['description'], 'UTF-8'));
            self::assertSame($description, $item['description']);
        }
    }

    /** Cuts inside named or numeric entities back off, while complete entities survive. */
    public function testTruncationPreservesEntityBoundaries(): void
    {
        $item = ['label' => 'Label', 'login' => 'alice', 'url' => '', 'description' => str_repeat('x', 70000)];
        $baseline = json_decode(secureSendEncodeSnapshot($item, 'secret')['plaintext'], true, 512, JSON_THROW_ON_ERROR);
        $capacity = strlen($baseline['description']);
        $key = Key::createNewRandomKey();

        foreach (['&amp;', '&lt;', '&#039;', '&#x1F600;'] as $entity) {
            for ($cutBytes = 1; $cutBytes <= strlen($entity); $cutBytes++) {
                $prefix = '中文 😀 ';
                $prefix .= str_repeat('x', $capacity - strlen($prefix) - $cutBytes);
                $item['description'] = $prefix . $entity . str_repeat('x', 70000);
                $result = secureSendEncodeSnapshot($item, 'secret');
                $ciphertext = Crypto::encrypt($result['plaintext'], $key);
                $payload = json_decode(Crypto::decrypt($ciphertext, $key), true, 512, JSON_THROW_ON_ERROR);

                self::assertLessThanOrEqual(65535, strlen($ciphertext));
                self::assertTrue($result['description_truncated']);
                self::assertSame($prefix . ($cutBytes === strlen($entity) ? $entity : ''), $payload['description']);
                self::assertSame('secret', $payload['password']);
                self::assertTrue(mb_check_encoding($payload['description'], 'UTF-8'));
            }
        }
    }

    /** Oversized credentials are rejected; they must never be silently shortened. */
    public function testRequiredFieldsCannotBeTruncated(): void
    {
        $this->expectExceptionMessage('invalid_payload');
        secureSendEncodeSnapshot(['label' => 'Label', 'login' => '', 'url' => '', 'description' => 'Note'], str_repeat('s', 40000));
    }

    /** A TOTP seed is credential data and must never be shortened to fit the column. */
    public function testOversizedTotpCannotBeTruncated(): void
    {
        $this->expectExceptionMessage('invalid_payload');
        secureSendEncodeSnapshot(
            ['label' => 'Label', 'login' => '', 'url' => '', 'description' => str_repeat('d', 70000)],
            'secret',
            ['secret' => str_repeat('A', 40000), 'algorithm' => 'sha1', 'digits' => 6, 'period' => 30]
        );
    }

    /** Malformed text is not silently replaced in a shared secret. */
    public function testInvalidUtf8IsRejected(): void
    {
        $this->expectExceptionMessage('invalid_payload');
        secureSendEncodeSnapshot(['label' => 'Label', 'login' => '', 'url' => '', 'description' => ''], "\xff");
    }
}
