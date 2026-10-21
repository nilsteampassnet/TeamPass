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
 * @file      device_pairing.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\CryptoManager\CryptoManager;

require_once __DIR__ . '/device_pairing_logic.php';
require_once __DIR__ . '/api_device_credentials.php';

/** Enforce HTTPS independently of the optional, historical API HTTPS setting. */
function devicePairingRequireHttps(array $server, array $settings): void
{
    if (!apiDeviceIsHttps($server, $settings)) {
        throw new DevicePairingException('https_required', 403);
    }
}

/** Enforce a persistent fixed-window budget, even if the generic API limiter is disabled. */
function devicePairingThrottle(string $scope, int $limit): void
{
    $window = intdiv(time(), 60) * 60;
    $key = 'pair:' . hash('sha256', $scope);
    DB::query('INSERT INTO ' . prefixTable('api_rate_limit') . ' (scope_key, window_start, hits)
        VALUES (%s, %i, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', $key, $window);
    $hits = (int) DB::queryFirstField('SELECT hits FROM ' . prefixTable('api_rate_limit') . ' WHERE scope_key = %s AND window_start = %i', $key, $window);
    if ($hits > $limit) {
        throw new DevicePairingException('rate_limited', 429, max(1, $window + 60 - time()));
    }
}

/** Read the live account; locking this row serializes all of its pairing/credential writes. */
function devicePairingAccount(int $userId, bool $lock = false): ?array
{
    if ($lock) {
        DB::queryFirstRow('SELECT id FROM ' . prefixTable('users') . ' WHERE id = %i FOR UPDATE', $userId);
    }
    return DB::queryFirstRow('SELECT u.*, a.enabled AS api_enabled FROM ' . prefixTable('users') . ' u
        LEFT JOIN ' . prefixTable('api') . ' a ON a.user_id = u.id WHERE u.id = %i', $userId);
}

/** Require the installed protocol and the existing account/PAT policy. */
function devicePairingRequireAccount(array $settings, ?array $user): void
{
    if ((int) ($settings['device_pairing_schema'] ?? 0) < 3 || !apiDeviceAccountAllowed($settings, $user)) {
        throw new DevicePairingException('unavailable', 403);
    }
    if ((int) ($user['is_ready_for_usage'] ?? 0) !== 1 || empty($user['public_key'])) {
        throw new DevicePairingException('keys_unavailable', 403);
    }
}

/** Record only fixed lifecycle metadata; this write is part of the caller's transaction. */
function devicePairingAudit(array $row, string $event): void
{
    if (!in_array($event, ['created', 'claimed', 'approved', 'rejected', 'cancelled', 'expired', 'delivered', 'completed'], true)) {
        throw new InvalidArgumentException('Invalid pairing audit event');
    }
    DB::insert(prefixTable('log_system'), ['type' => 'user_mngt', 'date' => time(),
        'label' => 'at_device_pairing_' . $event, 'qui' => (string) $row['user_id'],
        'field_1' => json_encode(['pairing_id' => $row['id'], 'credential_id' => $row['credential_id'] ?? null], JSON_THROW_ON_ERROR)]);
}

/** Wipe all provisional credential and proof material, including an unconfirmed published PAT. */
function devicePairingFinishLocked(array $row, string $state): array
{
    if (!in_array($state, ['rejected', 'cancelled', 'expired', 'completed'], true)) {
        throw new InvalidArgumentException('Invalid terminal pairing state');
    }
    if ($state !== 'completed' && !empty($row['credential_id']) && $row['state'] !== 'completed') {
        apiDeviceRevokeLockedCredential((int) $row['user_id'], (int) $row['credential_id']);
    }
    $changes = ['state' => $state, 'updated_at' => time(), 'secret_hash' => null,
        'public_key' => null, 'token_hash' => null,
        'wrapped_private_key' => null, 'salt' => null, 'encrypted_token' => null];
    DB::update(prefixTable('device_pairings'), $changes, 'id = %s', $row['id']);
    devicePairingAudit($row, $state);
    return array_replace($row, $changes);
}

/** Open the common user-first transaction for a known opaque invitation. */
function devicePairingTransaction(string $id, array $settings, callable $operation): array
{
    $userId = (int) DB::queryFirstField('SELECT user_id FROM ' . prefixTable('device_pairings') . ' WHERE id = %s', $id);
    if ($userId <= 0) {
        throw new DevicePairingException('invalid_proof', 401);
    }
    DB::startTransaction();
    try {
        $user = devicePairingAccount($userId, true);
        $row = DB::queryFirstRow('SELECT * FROM ' . prefixTable('device_pairings') . ' WHERE id = %s FOR UPDATE', $id);
        if ($row === null) {
            throw new DevicePairingException('invalid_proof', 401);
        }
        $user ??= ['id' => $userId, 'disabled' => 1, 'api_enabled' => 0];
        $result = $operation($row, $user, apiDeviceCurrentSettings($settings));
        DB::commit();
        return $result;
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
}

/** Create an invitation from a validated own web session, without creating a PAT. */
function devicePairingCreate(int $userId, string $binding, string $sessionKey, int $webExpiresAt, string $privateKey, array $settings): array
{
    devicePairingThrottle('create:' . $userId, 6);
    DB::startTransaction();
    try {
        $user = devicePairingAccount($userId, true);
        $settings = apiDeviceCurrentSettings($settings);
        devicePairingRequireAccount($settings, $user);
        $url = devicePairingServerUrl($settings);
        $now = time();
        if ($webExpiresAt <= $now || !hash_equals((string) $user['key_tempo'], $sessionKey)
            || (int) $user['session_end'] <= $now || !apiDevicePrivateKeyMatches($privateKey, (string) $user['public_key'])
        ) {
            throw new DevicePairingException('keys_unavailable', 403);
        }
        foreach (DB::query('SELECT * FROM ' . prefixTable('device_pairings') . '
            WHERE user_id = %i AND owner_binding = %s AND state IN %ls FOR UPDATE',
            $userId, $binding, ['awaiting_scan', 'awaiting_approval', 'approved', 'delivered']) as $old) {
            devicePairingFinishLocked($old, 'cancelled');
        }
        $secret = devicePairingBase64Url(random_bytes(32));
        $row = ['id' => bin2hex(random_bytes(16)), 'user_id' => $userId, 'server_url' => $url, 'owner_binding' => $binding,
            'session_marker' => hash('sha256', $sessionKey), 'user_key_fingerprint' => hash('sha256', (string) $user['public_key']),
            'web_expires_at' => min($webExpiresAt, (int) $user['session_end']), 'state' => 'awaiting_scan',
            'secret_hash' => hash('sha256', $secret), 'created_at' => $now,
            'expires_at' => min($now + TP_DEVICE_PAIRING_TTL, $webExpiresAt, (int) $user['session_end']), 'updated_at' => $now];
        DB::insert(prefixTable('device_pairings'), $row);
        devicePairingAudit($row, 'created');
        DB::commit();
        return devicePairingPublicState($row + ['claim_id' => null], $now, true) + ['qr' => [
            'type' => 'teampass.device-pairing', 'version' => 1, 'server_url' => $url,
            'pairing_id' => $row['id'], 'pairing_secret' => $secret]];
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
}

/** Reserve exactly one immutable device claim; an identical retry recovers that claim. */
function devicePairingClaim(array $input, array $settings): array
{
    $id = devicePairingInput($input, 'pairing_id', '/^[a-f0-9]{32}$/');
    $secret = devicePairingInput($input, 'pairing_secret', '/^[A-Za-z0-9_-]{43}$/');
    $challenge = devicePairingInput($input, 'challenge', '/^[A-Za-z0-9_-]{43}$/');
    $label = devicePairingLabel($input);
    $pem = devicePairingInput($input, 'public_key', '/\A[\s\S]{1,2048}\z/');
    try {
        $key = CryptoManager::devicePairingPublicKey($pem);
    } catch (Throwable $e) {
        throw new DevicePairingException('invalid_request');
    }
    return devicePairingTransaction($id, $settings, function (array $row, array $user, array $settings) use ($secret, $challenge, $label, $key): array {
        if ($row['secret_hash'] === null || !hash_equals($row['secret_hash'], hash('sha256', $secret))) {
            throw new DevicePairingException('invalid_proof', 401);
        }
        if (devicePairingNeedsExpiration($row, $user, $settings, time())) {
            return devicePairingPublicState(devicePairingFinishLocked($row, 'expired'), time(), false);
        }
        devicePairingRequireAccount($settings, $user);
        if ($row['state'] === 'awaiting_scan') {
            $changes = ['state' => 'awaiting_approval', 'claim_id' => bin2hex(random_bytes(16)),
                'challenge' => $challenge, 'public_key' => $key['pem'], 'key_fingerprint' => $key['fingerprint'],
                'device_label' => $label, 'comparison_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 'updated_at' => time()];
            DB::update(prefixTable('device_pairings'), $changes, 'id = %s', $row['id']);
            $row = array_replace($row, $changes);
            devicePairingAudit($row, 'claimed');
        } elseif ($row['challenge'] !== $challenge || $row['key_fingerprint'] !== $key['fingerprint'] || $row['device_label'] !== $label) {
            throw new DevicePairingException('claim_conflict', 409);
        }
        return devicePairingPublicState($row, time(), false) + ['claim_id' => $row['claim_id'],
            'comparison_code' => $row['comparison_code'], 'key_fingerprint' => $row['key_fingerprint']];
    });
}

/** Read or explicitly decide the exact claim from the initiating authenticated web session. */
function devicePairingWebAction(string $action, array $input, int $userId, string $binding, string $privateKey, array $settings): array
{
    $id = devicePairingInput($input, 'pairing_id', '/^[a-f0-9]{32}$/');
    devicePairingThrottle('web:' . $userId, 90);
    return devicePairingTransaction($id, $settings, function (array $row, array $user, array $settings) use ($action, $input, $userId, $binding, $privateKey): array {
        if ((int) $row['user_id'] !== $userId || !hash_equals($row['owner_binding'], $binding)) {
            throw new DevicePairingException('invalid_proof', 403);
        }
        if (devicePairingNeedsExpiration($row, $user, $settings, time())) {
            $row = devicePairingFinishLocked($row, 'expired');
        }
        if ($action === 'status' || in_array($row['state'], ['rejected', 'cancelled', 'expired', 'completed'], true)) {
            return devicePairingPublicState($row, time(), true);
        }
        if ($action === 'cancel') {
            return devicePairingPublicState(devicePairingFinishLocked($row, 'cancelled'), time(), true);
        }
        $claimId = devicePairingInput($input, 'claim_id', '/^[a-f0-9]{32}$/');
        $fingerprint = devicePairingInput($input, 'key_fingerprint', '/^[a-f0-9]{64}$/');
        if ($row['claim_id'] !== $claimId || $row['key_fingerprint'] !== $fingerprint || $row['state'] !== 'awaiting_approval') {
            throw new DevicePairingException('claim_conflict', 409);
        }
        if ($action === 'reject') {
            return devicePairingPublicState(devicePairingFinishLocked($row, 'rejected'), time(), true);
        }
        if ($action !== 'approve') {
            throw new DevicePairingException('invalid_request');
        }
        devicePairingRequireAccount($settings, $user);
        try {
            $prepared = apiDevicePrepareToken($privateKey, (string) $user['public_key']);
            $encrypted = CryptoManager::encryptDevicePairingToken($prepared['token'], $row['public_key']);
        } catch (Throwable $e) {
            throw new DevicePairingException('keys_unavailable', 403);
        }
        $changes = ['state' => 'approved', 'token_hash' => $prepared['token_hash'],
            'wrapped_private_key' => $prepared['wrapped_private_key'], 'salt' => $prepared['salt'],
            'encrypted_token' => devicePairingBase64Url($encrypted), 'updated_at' => time()];
        DB::update(prefixTable('device_pairings'), $changes, 'id = %s', $row['id']);
        $row = array_replace($row, $changes);
        devicePairingAudit($row, 'approved');
        return devicePairingPublicState($row, time(), true);
    });
}

/** Poll or consume only as the holder of the verifier bound to the immutable approved claim. */
function devicePairingDeviceAction(string $action, array $input, array $settings): array
{
    $id = devicePairingInput($input, 'pairing_id', '/^[a-f0-9]{32}$/');
    $claimId = devicePairingInput($input, 'claim_id', '/^[a-f0-9]{32}$/');
    $verifier = devicePairingInput($input, 'verifier', '/^[A-Za-z0-9_-]{43}$/');
    devicePairingThrottle('device:' . $id, 90);
    return devicePairingTransaction($id, $settings, function (array $row, array $user, array $settings) use ($action, $claimId, $verifier): array {
        if ($row['claim_id'] !== $claimId || $row['challenge'] === null
            || !hash_equals($row['challenge'], devicePairingBase64Url(hash('sha256', $verifier, true)))
        ) {
            throw new DevicePairingException('invalid_proof', 401);
        }
        if (devicePairingNeedsExpiration($row, $user, $settings, time())) {
            $row = devicePairingFinishLocked($row, 'expired');
        }
        if (in_array($row['state'], ['expired', 'cancelled', 'rejected', 'completed'], true)) {
            return devicePairingPublicState($row, time(), false);
        }
        devicePairingRequireAccount($settings, $user);
        if ($action === 'status') {
            if ((int) ($row['client_polled_at'] ?? 0) + TP_DEVICE_PAIRING_POLL_INTERVAL > time()) {
                throw new DevicePairingException('rate_limited', 429);
            }
            DB::update(prefixTable('device_pairings'), ['client_polled_at' => time()], 'id = %s', $row['id']);
            return devicePairingPublicState($row, time(), false);
        }
        if ($action !== 'consume') {
            throw new DevicePairingException('invalid_request');
        }
        if ($row['state'] === 'approved') {
            DB::insert(prefixTable('api_tokens'), ['user_id' => (int) $row['user_id'], 'purpose' => 'external_device',
                'pairing_id' => $row['id'], 'token_hash' => $row['token_hash'], 'wrapped_private_key' => $row['wrapped_private_key'],
                'salt' => $row['salt'], 'label' => $row['device_label'], 'created_at' => time(),
                'expires_at' => time() + apiDeviceLifetimeDays($settings) * 86400]);
            $changes = ['state' => 'delivered', 'credential_id' => (int) DB::insertId(),
                'delivery_until' => min((int) $row['expires_at'], time() + TP_DEVICE_PAIRING_DELIVERY_TTL),
                'token_hash' => null, 'wrapped_private_key' => null, 'salt' => null, 'updated_at' => time()];
            DB::update(prefixTable('device_pairings'), $changes, 'id = %s', $row['id']);
            $row = array_replace($row, $changes);
            devicePairingAudit($row, 'delivered');
        }
        $result = devicePairingPublicState($row, time(), false);
        if ($row['state'] === 'delivered') {
            $result['handoff'] = ['version' => 1, 'algorithm' => 'RSA-OAEP-256', 'key_fingerprint' => $row['key_fingerprint'],
                'ciphertext' => $row['encrypted_token'], 'delivery_until' => (int) $row['delivery_until'],
                'server_url' => devicePairingServerUrl($settings),
                'username' => html_entity_decode((string) $user['login'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'credential_id' => (int) $row['credential_id'], 'credential_expires_at' => (int) DB::queryFirstField(
                    'SELECT expires_at FROM ' . prefixTable('api_tokens') . ' WHERE id = %i', (int) $row['credential_id'])];
        }
        return $result;
    });
}

/**
 * Confirm the first use of a delivered PAT inside the authentication transaction.
 * The caller already holds the user and credential locks; failure publishes no JWT.
 */
function devicePairingConfirmCredential(array $token, array $user, array $settings): bool
{
    $row = DB::queryFirstRow('SELECT * FROM ' . prefixTable('device_pairings') . ' WHERE id = %s FOR UPDATE', $token['pairing_id']);
    if ($row === null || $row['state'] !== 'delivered' || (int) $row['credential_id'] !== (int) $token['id']
        || (int) $row['user_id'] !== (int) $user['id'] || devicePairingNeedsExpiration($row, $user, $settings, time())
    ) {
        return false;
    }
    devicePairingFinishLocked($row, 'completed');
    DB::update(prefixTable('api_tokens'), ['pairing_id' => null], 'id = %i', (int) $token['id']);
    return true;
}

/** Expire and wipe bounded batches; authorization checks never depend on this job running. */
function devicePairingPurge(array $settings): int
{
    if ((int) ($settings['device_pairing_schema'] ?? 0) < 3) {
        return 0;
    }
    $count = 0;
    $rows = DB::query('SELECT id FROM ' . prefixTable('device_pairings') . ' WHERE state IN %ls ORDER BY expires_at LIMIT 200',
        ['awaiting_scan', 'awaiting_approval', 'approved', 'delivered']);
    foreach ($rows as $row) {
        $result = devicePairingTransaction($row['id'], $settings, function (array $row, array $user, array $settings): array {
            if (devicePairingNeedsExpiration($row, $user, $settings, time())) {
                devicePairingFinishLocked($row, 'expired');
                return ['expired' => true];
            }
            return ['expired' => false];
        });
        $count += (int) $result['expired'];
    }
    DB::query('DELETE FROM ' . prefixTable('device_pairings') . ' WHERE state IN %ls AND updated_at < %i LIMIT 500',
        ['expired', 'cancelled', 'rejected', 'completed'], time() - 86400);
    DB::delete(prefixTable('api_rate_limit'), 'scope_key LIKE %s AND window_start < %i', 'pair:%', time() - 180);
    return $count;
}
