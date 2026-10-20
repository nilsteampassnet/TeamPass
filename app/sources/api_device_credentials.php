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
    $settings = array_replace($settings, array_fill_keys(
        ['api', 'device_pairing_enabled', 'device_pairing_schema', 'oauth2_api_enabled', 'extension_token_all_auth_types'], 0
    ));
    foreach (DB::query(
        'SELECT intitule, valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule IN %ls',
        'admin', ['api', 'device_pairing_enabled', 'device_pairing_schema', 'oauth2_api_enabled', 'extension_token_all_auth_types', 'cpassman_url']
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
function apiDeviceRevokeCredential(int $userId, int $tokenId): bool
{
    DB::startTransaction();
    try {
        DB::queryFirstRow('SELECT id FROM ' . prefixTable('users') . ' WHERE id = %i FOR UPDATE', $userId);
        $token = DB::queryFirstRow(
            'SELECT id FROM ' . prefixTable('api_tokens') . ' WHERE id = %i AND user_id = %i AND purpose = %s FOR UPDATE',
            $tokenId, $userId, 'external_device'
        );
        if ($token === null) {
            DB::rollback();
            return false;
        }
        apiDeviceRevokeLockedCredential($userId, $tokenId);
        DB::insert(prefixTable('log_system'), [
            'type' => 'user_mngt', 'date' => time(), 'label' => 'at_device_credential_revoked',
            'qui' => (string) $userId, 'field_1' => (string) $tokenId,
        ]);
        DB::commit();
        return true;
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
}

/** Revoke inside the caller's transaction, after locking the user and credential. */
function apiDeviceRevokeLockedCredential(int $userId, int $tokenId): void
{
    DB::update(prefixTable('api_sessions'), ['revoked_at' => time()],
        'user_id = %i AND source_token_id = %i AND revoked_at IS NULL', $userId, $tokenId);
    DB::delete(prefixTable('api_tokens'), 'id = %i AND user_id = %i AND purpose = %s', $tokenId, $userId, 'external_device');
}

/** Revalidate an external-device JWT's source, independently of the encrypted-key read path. */
function apiDeviceSessionSourceAllowed(int $userId, int $tokenId, array $settings): bool
{
    $settings = apiDeviceCurrentSettings($settings);
    $source = DB::queryFirstRow(
        'SELECT t.user_id, t.purpose, t.expires_at, u.disabled, u.deleted_at, u.auth_type, a.enabled AS api_enabled
         FROM ' . prefixTable('api_tokens') . ' t
         INNER JOIN ' . prefixTable('users') . ' u ON u.id = t.user_id
         LEFT JOIN ' . prefixTable('api') . ' a ON a.user_id = u.id
         WHERE t.id = %i AND t.user_id = %i', $tokenId, $userId
    );
    return apiDeviceSourceIsValid($settings, $source, $userId, time());
}
