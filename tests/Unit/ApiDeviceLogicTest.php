<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 TeamPass.net
 * SPDX-License-Identifier: GPL-3.0-only
 * Distributed without any warranty. See LICENSE.md.
 */

use PHPUnit\Framework\TestCase;
use TeampassClasses\CryptoManager\CryptoManager;

require_once __DIR__ . '/../../app/sources/api_device_logic.php';

class ApiDeviceLogicTest extends TestCase
{
    /** The new opt-in never relaxes the existing API/PAT gates. */
    public function testAccountPolicy(): void
    {
        $settings = ['device_pairing_schema' => 1, 'api' => 1, 'device_pairing_enabled' => 1, 'oauth2_api_enabled' => 1];
        $user = ['id' => 42, 'api_enabled' => 1, 'disabled' => 0, 'auth_type' => 'oauth2'];
        self::assertTrue(apiDeviceAccountAllowed($settings, $user));
        foreach (['device_pairing_schema', 'api', 'device_pairing_enabled', 'oauth2_api_enabled'] as $gate) {
            self::assertFalse(apiDeviceAccountAllowed(array_replace($settings, [$gate => 0]), $user));
        }
        foreach (['local', 'ldap'] as $type) {
            $account = array_replace($user, ['auth_type' => $type]);
            self::assertFalse(apiDeviceAccountAllowed($settings, $account));
            self::assertTrue(apiDeviceAccountAllowed($settings + ['extension_token_all_auth_types' => 1], $account));
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
        $settings = ['device_pairing_schema' => 1, 'api' => 1, 'device_pairing_enabled' => 1, 'extension_token_all_auth_types' => 1];
        $source = ['user_id' => 42, 'purpose' => 'external_device', 'expires_at' => null, 'api_enabled' => 1, 'auth_type' => 'local'];
        self::assertTrue(apiDeviceSourceIsValid($settings, $source, 42, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, null, 42, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, $source, 43, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, array_replace($source, ['expires_at' => 100]), 42, 100));
        self::assertTrue(apiDeviceSourceIsValid($settings, array_replace($source, ['expires_at' => 101]), 42, 100));
        self::assertFalse(apiDeviceSourceIsValid($settings, array_replace($source, ['purpose' => 'legacy']), 42, 100));
    }
}
