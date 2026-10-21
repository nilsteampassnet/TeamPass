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
 * @file      api_device_credentials.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/api_device_logic.php';

/** Read decisive policy without the shared configuration cache. */
function apiDeviceCurrentSettings(array $settings): array
{
    $settings['device_pairing_token_lifetime_days'] = 90;
    $settings = array_replace($settings, array_fill_keys(
        ['api', 'device_pairing_enabled', 'device_pairing_schema', 'oauth2_api_enabled', 'extension_token_all_auth_types'], 0
    ));
    foreach (DB::query(
        'SELECT intitule, valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule IN %ls',
        'admin', ['api', 'device_pairing_enabled', 'device_pairing_schema', 'oauth2_api_enabled', 'extension_token_all_auth_types', 'cpassman_url', 'device_pairing_token_lifetime_days']
    ) as $row) {
        $settings[(string) $row['intitule']] = $row['valeur'];
    }
    return $settings;
}

/**
 * Serialize device authentication with revocation. Lock order: user, then credential.
 * The callback must issue the JWT and its source-linked session on this connection.
 *
 * @param callable(array,array):(array{token:string,teampass_version:string,teampass_version_major:string,teampass_version_minor:string}|array{error:string,info:string}) $issue JWT issuer
 * @return array{token:string,teampass_version:string,teampass_version_major:string,teampass_version_minor:string}|array{error:string,info:string}|null Authentication result
 */
function apiDeviceAuthenticate(int $userId, int $tokenId, array $settings, callable $issue): ?array
{
    DB::startTransaction();
    try {
        $user = DB::queryFirstRow('SELECT id, login FROM ' . prefixTable('users') . ' WHERE id = %i FOR UPDATE', $userId);
        $token = DB::queryFirstRow('SELECT * FROM ' . prefixTable('api_tokens') . ' WHERE id = %i AND user_id = %i FOR UPDATE', $tokenId, $userId);
        $account = $user === null ? null : getUserCompleteData((string) $user['login']);
        $settings = apiDeviceCurrentSettings($settings);
        if ($token === null || ($token['purpose'] ?? '') !== 'external_device'
            || ($token['revoked_at'] ?? null) !== null
            || ($token['expires_at'] !== null && (int) $token['expires_at'] <= time())
            || apiDeviceAccountAllowed($settings, $account) === false
        ) {
            DB::rollback();
            return null;
        }
        if (!empty($token['pairing_id'])) {
            require_once __DIR__ . '/device_pairing.functions.php';
            if (!devicePairingConfirmCredential($token, $account, $settings)) {
                DB::rollback();
                return null;
            }
        }
        $result = $issue($token, $account);
        if (isset($result['token']) === false) {
            DB::rollback();
            return $result;
        }
        DB::commit();
        return $result;
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
}

/**
 * Revoke an owned device credential and all its JWTs, retaining session tombstones.
 * Available even when the API or pairing feature has subsequently been disabled.
 */
function apiDeviceRevokeCredential(int $userId, int $tokenId, ?int $actorId = null): bool
{
    $actorId ??= $userId;
    DB::startTransaction();
    try {
        DB::queryFirstRow('SELECT id FROM ' . prefixTable('users') . ' WHERE id = %i FOR UPDATE', $userId);
        $actor = DB::queryFirstRow('SELECT id, admin, disabled, deleted_at FROM ' . prefixTable('users') . ' WHERE id = %i', $actorId);
        if (!apiDeviceCanManageCredentials($actor, $userId)) {
            DB::rollback();
            return false;
        }
        $token = DB::queryFirstRow(
            'SELECT id FROM ' . prefixTable('api_tokens') . ' WHERE id = %i AND user_id = %i AND purpose = %s AND revoked_at IS NULL FOR UPDATE',
            $tokenId, $userId, 'external_device'
        );
        if ($token === null) {
            DB::rollback();
            return false;
        }
        apiDeviceRevokeLockedCredential($userId, $tokenId);
        DB::insert(prefixTable('log_system'), [
            'type' => 'user_mngt', 'date' => time(), 'label' => 'at_device_credential_revoked',
            'qui' => (string) $actorId,
            'field_1' => json_encode(['credential_id' => $tokenId, 'user_id' => $userId], JSON_THROW_ON_ERROR),
        ]);
        DB::commit();
        return true;
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
}

/** Return a bounded metadata page to a freshly verified administrator; never select credential material. */
function apiDeviceAdminList(int $actorId, int $ownerId = 0, int $offset = 0): array
{
    require_once __DIR__ . '/device_pairing_logic.php';
    $actor = DB::queryFirstRow('SELECT id, admin, disabled, deleted_at FROM ' . prefixTable('users') . ' WHERE id = %i', $actorId);
    if ($actor === null || (int) $actor['admin'] !== 1 || (int) $actor['disabled'] !== 0 || $actor['deleted_at'] !== null) {
        throw new DevicePairingException('forbidden', 403);
    }
    if ($ownerId < 0 || $offset < 0 || $offset > 1000000) {
        throw new DevicePairingException('invalid_request');
    }
    $query = 'SELECT t.id, t.user_id, t.label, t.created_at, t.last_used_at, t.expires_at, u.login
        FROM ' . prefixTable('api_tokens') . ' t INNER JOIN ' . prefixTable('users') . ' u ON u.id = t.user_id
        WHERE t.purpose = %s AND t.pairing_id IS NULL AND t.revoked_at IS NULL';
    $parameters = ['external_device'];
    if ($ownerId > 0) {
        $query .= ' AND t.user_id = %i';
        $parameters[] = $ownerId;
    }
    $query .= ' ORDER BY t.created_at DESC, t.id DESC LIMIT 31 OFFSET %i';
    $parameters[] = $offset;
    $rows = DB::query($query, ...$parameters);
    $hasMore = count($rows) > 30;
    if ($hasMore) {
        array_pop($rows);
    }
    foreach ($rows as &$row) {
        $row['login'] = html_entity_decode((string) $row['login'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    unset($row);
    return ['devices' => $rows, 'has_more' => $hasMore];
}

/** Revoke inside the caller's transaction, after locking the user and credential. */
function apiDeviceRevokeLockedCredential(int $userId, int $tokenId): void
{
    DB::update(prefixTable('api_sessions'), ['revoked_at' => time()],
        'user_id = %i AND source_token_id = %i AND revoked_at IS NULL', $userId, $tokenId);
    // Keep the hash as a possession-proof tombstone, while destroying provisioning material.
    DB::update(prefixTable('api_tokens'), ['revoked_at' => time(), 'wrapped_private_key' => '', 'salt' => '', 'pairing_id' => null],
        'id = %i AND user_id = %i AND purpose = %s AND revoked_at IS NULL', $tokenId, $userId, 'external_device');
}

/** Revalidate an external-device JWT's source, independently of the encrypted-key read path. */
function apiDeviceSessionSourceAllowed(int $userId, int $tokenId, array $settings): bool
{
    $settings = apiDeviceCurrentSettings($settings);
    if ((int) ($settings['device_pairing_schema'] ?? 0) < 3) {
        return false;
    }
    $source = DB::queryFirstRow(
        'SELECT t.user_id, t.purpose, t.expires_at, t.revoked_at, u.disabled, u.deleted_at, u.auth_type, a.enabled AS api_enabled
         FROM ' . prefixTable('api_tokens') . ' t
         INNER JOIN ' . prefixTable('users') . ' u ON u.id = t.user_id
         LEFT JOIN ' . prefixTable('api') . ' a ON a.user_id = u.id
         WHERE t.id = %i AND t.user_id = %i', $tokenId, $userId
    );
    return apiDeviceSourceIsValid($settings, $source, $userId, time());
}

/**
 * Return credential state only to the holder of its exact high-entropy PAT, including after revocation.
 * Unknown proofs remain indistinguishable. This is independent of API/pairing enablement so a
 * client can learn a definitive loss of authorization without treating a generic 401 as a wipe signal.
 */
function apiDeviceCredentialStatus(string $proof, array $settings): ?array
{
    require_once __DIR__ . '/device_pairing.functions.php';
    if (preg_match('/^[a-f0-9]{64}$/', $proof) !== 1) {
        return null;
    }
    $identity = DB::queryFirstRow('SELECT id, user_id FROM ' . prefixTable('api_tokens')
        . ' WHERE token_hash = %s AND purpose = %s', hash('sha256', $proof), 'external_device');
    if ($identity === null) {
        return null;
    }
    DB::startTransaction();
    try {
        $user = devicePairingAccount((int) $identity['user_id'], true);
        $token = DB::queryFirstRow('SELECT * FROM ' . prefixTable('api_tokens') . ' WHERE id = %i FOR UPDATE', (int) $identity['id']);
        if ($token === null || !hash_equals((string) $token['token_hash'], hash('sha256', $proof))) {
            DB::rollback();
            return null;
        }
        $settings = apiDeviceCurrentSettings($settings);
        $reason = null;
        if ($token['revoked_at'] !== null) {
            $reason = 'revoked';
        } elseif ($token['expires_at'] !== null && (int) $token['expires_at'] <= time()) {
            $reason = 'expired';
        } elseif (!apiDeviceAccountAllowed($settings, $user)) {
            $reason = 'access_removed';
        } elseif (!empty($token['pairing_id'])) {
            // An unconfirmed handoff is not an established offline authorization.
            $reason = 'not_confirmed';
        } else {
            $salt = (string) $token['salt'];
            $key = preg_match('/^[a-f0-9]{32}$/', $salt) === 1
                ? hash_hkdf('sha256', $proof, 32, 'teampass-extension-token-v1', (string) hex2bin($salt)) : '';
            $privateKey = $key === '' ? false : decrypt_with_session_key((string) $token['wrapped_private_key'], $key);
            if (!is_string($privateKey) || !apiDevicePrivateKeyMatches($privateKey, (string) ($user['public_key'] ?? ''))) {
                $reason = 'keys_changed';
            }
        }
        $state = match ($reason) {
            null => 'active',
            'not_confirmed' => 'pending',
            'access_removed' => 'suspended',
            default => 'invalidated',
        };
        $result = ['credential_id' => (int) $token['id'], 'state' => $state,
            'reason' => $reason, 'expires_at' => $token['expires_at'] === null ? null : (int) $token['expires_at'],
            'server_time' => time(), 'server_url' => devicePairingServerUrl($settings)];
        DB::commit();
        return $result;
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
}
