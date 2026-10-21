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
 * @file      ApiDeviceLogicTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;
use TeampassClasses\CryptoManager\CryptoManager;

require_once __DIR__ . '/../../app/sources/api_device_logic.php';

class ApiDeviceLogicTest extends TestCase
{
    /** Device pairing has its own opt-in; API/account rights remain mandatory for every auth type. */
    public function testAccountPolicy(): void
    {
        $settings = ['device_pairing_schema' => 3, 'api' => 1, 'device_pairing_enabled' => 1,
            'oauth2_api_enabled' => 0, 'extension_token_all_auth_types' => 0];
        $user = ['id' => 42, 'api_enabled' => 1, 'disabled' => 0, 'auth_type' => 'oauth2'];
        self::assertTrue(apiDeviceAccountAllowed($settings, $user));
        foreach (['device_pairing_schema', 'api', 'device_pairing_enabled'] as $gate) {
            self::assertFalse(apiDeviceAccountAllowed(array_replace($settings, [$gate => 0]), $user));
        }
        foreach (['local', 'ldap'] as $type) {
            $account = array_replace($user, ['auth_type' => $type]);
            self::assertTrue(apiDeviceAccountAllowed($settings, $account));
        }
        self::assertFalse(apiDeviceAccountAllowed($settings, null));
        foreach (['disabled' => 1, 'api_enabled' => 0, 'deleted_at' => 123] as $field => $value) {
            self::assertFalse(apiDeviceAccountAllowed($settings, array_replace($user, [$field => $value])));
        }
    }

    /** The result remains consumable by the current authorizeToken wrapping contract. */
    public function testPreparedTokenUsesTheExistingWrappingFormat(): void
    {
        $keys = CryptoManager::generateRSAKeyPair(2048);
        $prepared = apiDevicePrepareToken($keys['privatekey'], $keys['publickey']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $prepared['token']);
        self::assertSame(hash('sha256', $prepared['token']), $prepared['token_hash']);
        $key = hash_hkdf('sha256', $prepared['token'], 32, 'teampass-extension-token-v1', hex2bin($prepared['salt']));
        self::assertSame($keys['privatekey'], decrypt_with_session_key($prepared['wrapped_private_key'], $key));
        self::assertStringNotContainsString($keys['privatekey'], $prepared['wrapped_private_key']);
        self::assertFalse(apiDevicePrivateKeyMatches('none', $keys['publickey']));
        $other = CryptoManager::generateRSAKeyPair(2048);
        self::assertFalse(apiDevicePrivateKeyMatches($keys['privatekey'], $other['publickey']));
        $this->expectException(RuntimeException::class);
        apiDevicePrepareToken($keys['privatekey'], $other['publickey']);
    }

    /** A deleted, expired or cross-user source must never authorize an existing JWT. */
    public function testSourceValidity(): void
    {
        $settings = ['device_pairing_schema' => 3, 'api' => 1, 'device_pairing_enabled' => 1, 'extension_token_all_auth_types' => 1];
        $source = ['user_id' => 42, 'purpose' => 'external_device', 'expires_at' => null, 'api_enabled' => 1, 'auth_type' => 'local'];
        self::assertTrue(apiDeviceSourceIsValid($settings, $source, 42, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, null, 42, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, $source, 43, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, array_replace($source, ['expires_at' => 100]), 42, 100));
        self::assertTrue(apiDeviceSourceIsValid($settings, array_replace($source, ['expires_at' => 101]), 42, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, array_replace($source, ['purpose' => 'legacy']), 42, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, array_replace($source, ['revoked_at' => 99]), 42, 100));
    }

    /** Invalid values cannot silently create an unbounded or coerced credential lifetime. */
    public function testDeviceLifetimePolicy(): void
    {
        self::assertSame(90, apiDeviceLifetimeDays([]));
        foreach ([1, '90', 3650] as $days) {
            self::assertSame((int) $days, apiDeviceValidateLifetime($days));
        }
        foreach ([0, -1, 3651, true, false, 1.5, [], '1.0', '90 days', null] as $invalid) {
            self::assertSame(90, apiDeviceLifetimeDays(['device_pairing_token_lifetime_days' => $invalid]));
            try {
                apiDeviceValidateLifetime($invalid);
                self::fail('Invalid lifetime accepted');
            } catch (InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    /** An administrator's role never grants vault access; this decision scopes credential metadata only. */
    public function testCredentialManagementScope(): void
    {
        self::assertTrue(apiDeviceCanManageCredentials(['id' => 42, 'read_only' => 1], 42));
        self::assertFalse(apiDeviceCanManageCredentials(['id' => 43, 'gestionnaire' => 1], 42));
        self::assertFalse(apiDeviceCanManageCredentials(null, 42));
        self::assertFalse(apiDeviceCanManageCredentials(['id' => 0, 'admin' => 1], 42));
        self::assertFalse(apiDeviceCanManageCredentials(['id' => 45, 'admin' => 1], 0));
        self::assertTrue(apiDeviceCanManageCredentials(['id' => 45, 'admin' => 1], 42));
        self::assertFalse(apiDeviceCanManageCredentials(['id' => 45, 'admin' => 1, 'disabled' => 1], 42));
        self::assertFalse(apiDeviceCanManageCredentials(['id' => 45, 'admin' => 1, 'deleted_at' => 123], 42));
    }
}
