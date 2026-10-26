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
 * @file      api_device_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\CryptoManager\CryptoManager;
use TeampassClasses\SessionManager\SessionManager;

require_once __DIR__ . '/../api/inc/encryption_utils.php';
require_once __DIR__ . '/api_auth_logic.php';

/** A fixed PAT key-validation reason, reported only after the authentication transaction rolls back. */
final class ApiTokenKeyException extends RuntimeException
{
}

/** Tell whether the schema supporting external device credentials has been installed. */
function apiDeviceSchemaReady(array $settings): bool
{
    return (int) ($settings['device_pairing_schema'] ?? 0) >= 1;
}

/** Accept forwarded HTTPS only from the explicitly configured trusted proxy network. */
function apiDeviceIsHttps(array $server, array $settings): bool
{
    return in_array(strtolower((string) ($server['HTTPS'] ?? '')), ['on', '1'], true)
        || SessionManager::isTrustedForwardedHttps(
            (string) ($server['REMOTE_ADDR'] ?? ''), (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''),
            (string) ($settings['network_security_mode'] ?? 'direct'), (string) ($settings['network_trusted_proxies'] ?? '')
        );
}

/** Evaluate the dedicated device policy without enabling or depending on extension credentials. */
function apiDeviceAccountAllowed(array $settings, ?array $user): bool
{
    return (int) ($settings['device_pairing_schema'] ?? 0) >= 3
        && (int) ($settings['api'] ?? 0) === 1
        && (int) ($settings['device_pairing_enabled'] ?? 0) === 1
        && $user !== null
        && ($user['deleted_at'] ?? null) === null
        && apiAuthAccountRefusalReason($user, false, false) === '';
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
        && ($source['revoked_at'] ?? null) === null
        && ($source['expires_at'] === null || (int) $source['expires_at'] > $now)
        && apiDeviceAccountAllowed($settings, $source);
}

/** Validate an explicitly configured device lifetime, without boolean/float coercion. */
function apiDeviceValidateLifetime(mixed $value): int
{
    if ((!is_int($value) && !is_string($value)) || preg_match('/^[0-9]{1,4}$/', (string) $value) !== 1
        || (int) $value < 1 || (int) $value > 3650
    ) {
        throw new InvalidArgumentException('Device lifetime must be between 1 and 3650 days');
    }
    return (int) $value;
}

/** Corrupt or absent configuration must not create an unlimited credential. */
function apiDeviceLifetimeDays(array $settings): int
{
    try {
        return apiDeviceValidateLifetime($settings['device_pairing_token_lifetime_days'] ?? 90);
    } catch (InvalidArgumentException $e) {
        return 90;
    }
}

/** Owners manage their own credentials; only a currently active administrator manages another account. */
function apiDeviceCanManageCredentials(?array $actor, int $ownerId): bool
{
    return $actor !== null && (int) ($actor['id'] ?? 0) > 0 && $ownerId > 0
        && ((int) $actor['id'] === $ownerId || ((int) ($actor['admin'] ?? 0) === 1
            && (int) ($actor['disabled'] ?? 0) === 0 && ($actor['deleted_at'] ?? null) === null));
}
