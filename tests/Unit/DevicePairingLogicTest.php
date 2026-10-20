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

require_once __DIR__ . '/../../app/sources/device_pairing.functions.php';

class DevicePairingLogicTest extends TestCase
{
    /** Server deadlines, session invalidation and policy changes always win over client polling. */
    public function testExpiryDoesNotDependOnMaintenance(): void
    {
        $settings = ['device_pairing_schema' => 2, 'device_pairing_enabled' => 1, 'api' => 1,
            'extension_token_all_auth_types' => 1, 'cpassman_url' => 'https://vault.test/team'];
        $user = ['api_enabled' => 1, 'auth_type' => 'local', 'public_key' => 'key', 'key_tempo' => 'session',
            'session_end' => 200, 'is_ready_for_usage' => 1];
        $row = ['state' => 'approved', 'expires_at' => 100, 'web_expires_at' => 150,
            'session_marker' => hash('sha256', 'session'), 'user_key_fingerprint' => hash('sha256', 'key'),
            'server_url' => 'https://vault.test/team'];
        self::assertFalse(devicePairingNeedsExpiration($row, $user, $settings, 99));
        self::assertTrue(devicePairingNeedsExpiration($row, $user, $settings, 100));
        self::assertTrue(devicePairingNeedsExpiration($row, array_replace($user, ['key_tempo' => 'new']), $settings, 50));
        self::assertTrue(devicePairingNeedsExpiration($row, array_replace($user, ['is_ready_for_usage' => 0]), $settings, 50));
        self::assertTrue(devicePairingNeedsExpiration($row, $user, array_replace($settings, ['cpassman_url' => 'https://other.test']), 50));
        self::assertTrue(devicePairingNeedsExpiration($row, $user, array_replace($settings, ['device_pairing_enabled' => 0]), 50));
        self::assertTrue(devicePairingNeedsExpiration(array_replace($row, ['state' => 'delivered', 'delivery_until' => 60]), $user, $settings, 60));
        self::assertFalse(devicePairingNeedsExpiration(array_replace($row, ['state' => 'completed']), $user, $settings, 999));
    }

    /** Private addresses, subdirectories and ports are legitimate with trusted TLS. */
    public function testCanonicalInstanceUrls(): void
    {
        foreach (['https://vault.example.test/team', 'https://192.168.1.10:8443/team', 'https://[fd00::1]:8443/team'] as $url) {
            self::assertSame($url, devicePairingServerUrl(['cpassman_url' => $url . '/']));
        }
        foreach (['http://vault.test', 'https://user:pass@vault.test', 'https://vault.test?key=x', 'https://vault.test/#token',
            'https://vault.test/a/../b', "https://vault.test/\n", 'https://vault.test:99999'] as $url) {
            try {
                devicePairingServerUrl(['cpassman_url' => $url]);
                self::fail('Unsafe URL accepted');
            } catch (DevicePairingException $e) {
                self::assertSame('https_required', $e->reason);
            }
        }
    }

    /** A forwarded scheme from an arbitrary peer cannot authorize credential transport. */
    public function testTransportRequiresDirectTlsOrATrustedProxy(): void
    {
        foreach (['off', 'OFF', 'false', '0', ''] as $insecure) {
            self::assertFalse(apiDeviceIsHttps(['HTTPS' => $insecure], []));
        }
        devicePairingRequireHttps(['HTTPS' => 'on'], []);
        devicePairingRequireHttps(['REMOTE_ADDR' => '192.0.2.2', 'HTTP_X_FORWARDED_PROTO' => 'https'],
            ['network_security_mode' => 'reverse_proxy', 'network_trusted_proxies' => '192.0.2.0/24']);
        $this->expectException(DevicePairingException::class);
        devicePairingRequireHttps(['REMOTE_ADDR' => '198.51.100.2', 'HTTP_X_FORWARDED_PROTO' => 'https'],
            ['network_security_mode' => 'reverse_proxy', 'network_trusted_proxies' => '192.0.2.0/24']);
    }

    /** Public metadata never includes a usable credential or a verifier. */
    public function testStateProjectionAndLabelHandling(): void
    {
        foreach (["R&D", "O'Brien", '"<device>"'] as $label) {
            self::assertSame($label, devicePairingLabel(['device_label' => $label]));
        }
        $row = ['id' => 'id', 'state' => 'approved', 'expires_at' => 120, 'claim_id' => 'claim', 'key_fingerprint' => 'fp',
            'device_label' => '<script>', 'comparison_code' => '123456', 'encrypted_token' => 'protected', 'challenge' => 'hash', 'wrapped_private_key' => 'wrapped'];
        $web = devicePairingPublicState($row, 100, true);
        self::assertSame('<script>', $web['device_label']);
        self::assertArrayNotHasKey('encrypted_token', $web);
        self::assertArrayNotHasKey('challenge', $web);
        self::assertArrayNotHasKey('device_label', devicePairingPublicState($row, 100, false));
        $this->expectException(DevicePairingException::class);
        devicePairingLabel(['device_label' => "device\r\nInjected"]);
    }

    /** OAEP parameters, key type, exponent and size are fixed by the versioned contract. */
    public function testProtectedHandoff(): void
    {
        $keys = CryptoManager::generateRSAKeyPair(3072);
        $validated = CryptoManager::devicePairingPublicKey($keys['publickey']);
        self::assertSame($validated, CryptoManager::devicePairingPublicKey($validated['pem']));
        $token = bin2hex(random_bytes(32));
        $encrypted = CryptoManager::encryptDevicePairingToken($token, $validated['pem']);
        self::assertSame(384, strlen($encrypted));
        self::assertSame($token, CryptoManager::rsaDecryptWithVersion($encrypted, $keys['privatekey'], 3));
        try {
            CryptoManager::devicePairingPublicKey($keys['privatekey']);
            self::fail('Private key accepted as a claim key');
        } catch (Exception $e) {
            self::assertStringContainsString('Invalid device public key', $e->getMessage());
        }
        $this->expectException(Exception::class);
        CryptoManager::encryptDevicePairingToken('not-a-token', $validated['pem']);
    }
}
