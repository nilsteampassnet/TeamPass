<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 TeamPass.net
 * SPDX-License-Identifier: GPL-3.0-only
 * Distributed without any warranty. See LICENSE.md.
 */

use TeampassClasses\CryptoManager\CryptoManager;

require_once __DIR__ . '/../api/inc/encryption_utils.php';
require_once __DIR__ . '/api_auth_logic.php';

/** Tell whether the schema supporting external device credentials has been installed. */
function apiDeviceSchemaReady(array $settings): bool
{
    return (int) ($settings['device_pairing_schema'] ?? 0) === 1;
}

/** Evaluate the existing PAT policy as well as the explicit external-device opt-in. */
function apiDeviceAccountAllowed(array $settings, ?array $user): bool
{
    return apiDeviceSchemaReady($settings)
        && (int) ($settings['api'] ?? 0) === 1
        && (int) ($settings['device_pairing_enabled'] ?? 0) === 1
        && ((int) ($settings['oauth2_api_enabled'] ?? 0) === 1
            || (int) ($settings['extension_token_all_auth_types'] ?? 0) === 1)
        && $user !== null
        && ($user['deleted_at'] ?? null) === null
        && apiAuthAccountRefusalReason($user, true, (int) ($settings['extension_token_all_auth_types'] ?? 0) === 1) === '';
}

/** Validate that the session's cleartext private key belongs to the current account key pair. */
function apiDevicePrivateKeyMatches(string $privateKey, string $publicKey): bool
{
    if ($privateKey === '' || $privateKey === 'none' || $publicKey === '') {
        return false;
    }
    try {
        $probe = random_bytes(32);
        $encrypted = CryptoManager::rsaEncrypt($probe, $publicKey);
        return hash_equals($probe, CryptoManager::rsaDecryptWithVersion($encrypted, $privateKey, 3));
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Prepare the existing PAT wrapping format in memory, without publishing a credential.
 * The caller must never persist or log token; only token_hash, salt and wrapped_private_key.
 *
 * @return array{token:string,token_hash:string,salt:string,wrapped_private_key:string}
 */
function apiDevicePrepareToken(string $privateKey, string $publicKey): array
{
    if (apiDevicePrivateKeyMatches($privateKey, $publicKey) === false) {
        throw new RuntimeException('The session encryption key is unavailable or outdated.');
    }
    $token = bin2hex(random_bytes(32));
    $salt = bin2hex(random_bytes(16));
    $key = hash_hkdf('sha256', $token, 32, 'teampass-extension-token-v1', (string) hex2bin($salt));
    $wrapped = encrypt_with_session_key($privateKey, $key);
    if ($wrapped === false) {
        throw new RuntimeException('Could not protect the device credential.');
    }
    return ['token' => $token, 'token_hash' => hash('sha256', $token), 'salt' => $salt, 'wrapped_private_key' => $wrapped];
}

/** Evaluate a source credential freshly read for an external-device API session. */
function apiDeviceSourceIsValid(array $settings, ?array $source, int $userId, int $now): bool
{
    return $source !== null
        && (int) ($source['user_id'] ?? 0) === $userId
        && ($source['purpose'] ?? '') === 'external_device'
        && ($source['expires_at'] === null || (int) $source['expires_at'] > $now)
        && apiDeviceAccountAllowed($settings, $source);
}
