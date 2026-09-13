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
 * ---
 * Vault passkeys: TeamPass acting as a WebAuthn authenticator for third-party sites.
 *
 * A credential is attached to an item and follows its folder rights, item restriction and
 * recycle bin. Its private key is encrypted like an item password and is only ever decrypted
 * by assertCredential(), which signs on the server: the key never leaves it.
 *
 * @file      WebauthnModel.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;

require_once API_ROOT_PATH . '/../sources/webauthn.functions.php';

class WebauthnModel
{
    /**
     * Returned when the caller holds no usable sharekey on a credential. The usual cause is
     * transient: the background task has not distributed the keys of a new item yet.
     */
    private const KEY_RECOVERY_ERROR = 'The passkey cannot be decrypted with your keys yet. '
        . 'Retry once its encryption keys have been distributed to your account, '
        . 'or ask an administrator to run the encryption keys repair task.';

    /**
     * Create a passkey on an item.
     *
     * @param array<string, mixed> $userData JWT user data
     * @param array<string, mixed> $request  Output of webauthnNormalizeCreateRequest()
     *
     * @return array<string, mixed> Error envelope (error, error_header, error_message) or the credential
     */
    public function createCredential(array $userData, array $request): array
    {
        $userId = (int) $userData['id'];
        $itemId = (int) $request['item_id'];
        $transactionStarted = false;

        try {
            $SETTINGS = (new ConfigManager())->getAllSettings();

            DB::startTransaction();
            $transactionStarted = true;

            $item = DB::queryFirstRow(
                'SELECT id, id_tree, label, deleted_at FROM ' . prefixTable('items') . ' WHERE id = %i FOR UPDATE',
                $itemId
            );
            if ($item === null || $item['deleted_at'] !== null) {
                DB::rollback();
                return $this->error(404, 'Item not found');
            }

            $folderId = (int) $item['id_tree'];
            if ($this->canEditItem($userData, $folderId, $itemId) === false) {
                DB::rollback();
                return $this->error(403, 'Access denied: you are not allowed to edit this item');
            }

            // excludeCredentials: the relying party lists the credentials the account already has.
            // Answering "already registered" is how its own duplicate-passkey flow works.
            if ($request['excluded_credential_ids'] !== []) {
                $alreadyRegistered = DB::queryFirstField(
                    'SELECT COUNT(*) FROM ' . prefixTable('webauthn_credentials') . '
                    WHERE rp_id = %s AND credential_id IN %ls',
                    $request['rp_id'],
                    $request['excluded_credential_ids']
                );
                if ((int) $alreadyRegistered > 0) {
                    DB::rollback();
                    return $this->error(409, 'A passkey for this account is already registered.');
                }
            }

            $folder = getFolderIdentityWithPersonalFlag($folderId);
            if ($folder === null) {
                DB::rollback();
                return $this->error(404, 'Item not found');
            }

            $keyPair = webauthnGenerateCredentialKeyPair();
            $credentialId = webauthnGenerateCredentialId();
            $encrypted = doDataEncryption($keyPair['pem']);
            $now = time();

            DB::insert(
                prefixTable('webauthn_credentials'),
                [
                    'item_id' => $itemId,
                    'credential_id' => webauthnBase64UrlEncode($credentialId),
                    'rp_id' => $request['rp_id'],
                    'rp_name' => $request['rp_name'] !== '' ? $request['rp_name'] : null,
                    'user_handle' => webauthnBase64UrlEncode($request['user_handle']),
                    'user_name' => $request['user_name'] !== '' ? $request['user_name'] : null,
                    'user_display_name' => $request['user_display_name'] !== '' ? $request['user_display_name'] : null,
                    'algorithm' => TP_WEBAUTHN_ALG_ES256,
                    'private_key' => $encrypted['encrypted'],
                    'private_key_meta' => $encrypted['meta'],
                    'public_key_cose' => base64_encode($keyPair['cose']),
                    'sign_count' => 0,
                    'discoverable' => 1,
                    'created_at' => $now,
                    'created_by' => $userId,
                ]
            );
            $credentialRowId = (int) DB::insertId();

            // Personal folder: owner + TP_USER_ID only (SEC-8). Otherwise every eligible user, like
            // any other object of the vault; folder rights decide who may actually use it.
            storeUsersShareKey(
                'sharekeys_webauthn',
                (int) $folder['personal_folder'],
                $credentialRowId,
                $encrypted['objectKey'],
                false,
                true,
                [],
                -1,
                $userId
            );

            // A credential its creator cannot use would be registered on the site and unusable.
            $callerKey = DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('sharekeys_webauthn') . ' WHERE object_id = %i AND user_id = %i',
                $credentialRowId,
                $userId
            );
            if ((int) $callerKey === 0) {
                throw new RuntimeException('No sharekey could be created for the passkey creator.');
            }

            logItems(
                $SETTINGS,
                $itemId,
                (string) $item['label'],
                $userId,
                'at_modification',
                (string) $userData['username'],
                'at_webauthn_credential_added : ' . $request['rp_id'],
                null,
                null,
                null,
                false,
                true
            );

            emitItemEvent('updated', $itemId, $folderId, (string) $item['label'], (string) $userData['username'], $userId);

            $authenticatorData = webauthnBuildAuthenticatorData(
                $request['rp_id'],
                webauthnBuildFlags($request['user_verified']),
                0,
                $credentialId,
                $keyPair['cose']
            );

            DB::commit();
            $transactionStarted = false;

            emitItemSyslog($SETTINGS, $itemId, (string) $item['label'], 'at_modification', (string) $userData['username']);

            return [
                'error' => false,
                'id' => $credentialRowId,
                'item_id' => $itemId,
                'rp_id' => $request['rp_id'],
                'credential_id' => webauthnBase64UrlEncode($credentialId),
                'user_handle' => webauthnBase64UrlEncode($request['user_handle']),
                'attestation_object' => webauthnBase64UrlEncode(webauthnBuildAttestationObject($authenticatorData)),
                'authenticator_data' => webauthnBase64UrlEncode($authenticatorData),
                'public_key' => webauthnBase64UrlEncode(webauthnBuildSpkiPublicKey($keyPair['x'], $keyPair['y'])),
                'public_key_algorithm' => TP_WEBAUTHN_ALG_ES256,
                'transports' => TP_WEBAUTHN_TRANSPORTS,
            ];
        } catch (Throwable $e) {
            if ($transactionStarted === true) {
                DB::rollback();
                resetItemRevisionMemo();
            }
            $this->logFailure('createCredential', $itemId, $e);

            return $this->error(500, 'An internal error occurred while creating the passkey.');
        }
    }

    /**
     * List the passkeys the caller can use, without any key material.
     *
     * @param array<string, mixed> $userData JWT user data
     * @param string|null          $rpId     Normalized relying party id filter
     * @param int|null             $itemId   Item filter
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCredentials(array $userData, ?string $rpId, ?int $itemId): array
    {
        $userId = (int) $userData['id'];
        $folderAccessModel = new FolderAccessModel();

        $safeFolders = implode(',', $folderAccessModel->normalizeFolderIds($userData['folders_list'] ?? '')) ?: '0';
        $visibility = ' AND (i.id_tree IN (' . $safeFolders . ')';
        $restrictedItems = $folderAccessModel->normalizeItemIds($userData['restricted_items_list'] ?? []);
        if ($restrictedItems !== []) {
            $visibility .= ' OR i.id IN (' . implode(',', $restrictedItems) . ')';
        }
        $visibility .= ')'
            . $folderAccessModel->getItemFolderSqlConstraint('i.id_tree', $userId)
            . $folderAccessModel->getItemRestrictionSqlConstraint('i', $userId);

        $filters = '';
        $params = [$userId];
        if ($rpId !== null) {
            $filters .= ' AND w.rp_id = %s';
            $params[] = $rpId;
        }
        if ($itemId !== null) {
            $filters .= ' AND w.item_id = %i';
            $params[] = $itemId;
        }

        // Folder membership is the access control. The sharekey condition only hides the
        // credentials whose keys have not reached this user yet: they could not be used.
        $rows = DB::query(
            'SELECT w.id, w.credential_id, w.rp_id, w.rp_name, w.user_handle, w.user_name,
                w.user_display_name, w.item_id, w.created_at, w.last_used_at, i.label, i.id_tree
            FROM ' . prefixTable('webauthn_credentials') . ' AS w
            INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = w.item_id)
            WHERE i.deleted_at IS NULL
            AND EXISTS (
                SELECT 1 FROM ' . prefixTable('sharekeys_webauthn') . ' AS s
                WHERE s.object_id = w.id AND s.user_id = %i
            )' . $filters . $visibility . '
            ORDER BY w.last_used_at IS NULL, w.last_used_at DESC, w.id DESC',
            ...$params
        );

        $ret = [];
        foreach ($rows as $row) {
            $ret[] = [
                'id' => (int) $row['id'],
                'credential_id' => (string) $row['credential_id'],
                'rp_id' => (string) $row['rp_id'],
                'rp_name' => $row['rp_name'],
                'user_handle' => (string) $row['user_handle'],
                'user_name' => $row['user_name'],
                'user_display_name' => $row['user_display_name'],
                'item_id' => (int) $row['item_id'],
                'item_label' => (string) $row['label'],
                'folder_id' => (int) $row['id_tree'],
                'created_at' => (int) $row['created_at'],
                'last_used_at' => $row['last_used_at'] === null ? null : (int) $row['last_used_at'],
            ];
        }

        return $ret;
    }

    /**
     * Sign an assertion with a passkey. The only path that decrypts a credential private key.
     *
     * @param array<string, mixed> $userData   JWT user data
     * @param array<string, mixed> $request    Output of webauthnNormalizeAssertRequest()
     * @param string               $privateKey Caller's cleartext private key
     * @param string               $publicKey  Caller's public key
     *
     * @return array<string, mixed> Error envelope or the assertion
     */
    public function assertCredential(array $userData, array $request, string $privateKey, string $publicKey): array
    {
        $userId = (int) $userData['id'];
        $credentialRowId = 0;
        $transactionStarted = false;

        try {
            $SETTINGS = (new ConfigManager())->getAllSettings();

            $credential = DB::queryFirstRow(
                'SELECT w.id, w.item_id, w.rp_id, w.user_handle, w.private_key, w.private_key_meta,
                    i.id_tree, i.label, i.deleted_at
                FROM ' . prefixTable('webauthn_credentials') . ' AS w
                INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = w.item_id)
                WHERE w.credential_id = %s',
                $request['credential_id']
            );
            // A relying party id that differs from the stored one is reported like an unknown
            // credential: the caller learns nothing about credentials of other sites.
            if ($credential === null || $credential['deleted_at'] !== null || $credential['rp_id'] !== $request['rp_id']) {
                return $this->error(404, 'Passkey not found');
            }

            $credentialRowId = (int) $credential['id'];
            $itemId = (int) $credential['item_id'];
            $folderAccessModel = new FolderAccessModel();
            if ($folderAccessModel->canAccessItemInFolder($userData, (int) $credential['id_tree'], $itemId) === false
                || $folderAccessModel->satisfiesItemRestriction($itemId, $userId) === false
            ) {
                return $this->error(403, 'Access denied to this item');
            }

            $sharekey = DB::queryFirstRow(
                'SELECT share_key, increment_id FROM ' . prefixTable('sharekeys_webauthn') . '
                WHERE object_id = %i AND user_id = %i',
                $credentialRowId,
                $userId
            );
            if ($sharekey === null) {
                return $this->error(422, self::KEY_RECOVERY_ERROR);
            }

            $objectKey = decryptUserObjectKeyWithMigration(
                (string) $sharekey['share_key'],
                $privateKey,
                $publicKey,
                (int) $sharekey['increment_id'],
                'sharekeys_webauthn'
            );
            // doDataDecryption() hands the plaintext back base64-encoded, '' on failure.
            $credentialPem = $objectKey === ''
                ? ''
                : (string) base64_decode(
                    doDataDecryption((string) $credential['private_key'], $objectKey, (string) ($credential['private_key_meta'] ?? '')),
                    true
                );
            if (str_starts_with($credentialPem, '-----BEGIN PRIVATE KEY-----') === false) {
                return $this->error(422, self::KEY_RECOVERY_ERROR);
            }

            DB::startTransaction();
            $transactionStarted = true;

            // The counter is the relying party's clone detector: two concurrent assertions must
            // never sign the same value.
            $signCount = DB::queryFirstField(
                'SELECT sign_count FROM ' . prefixTable('webauthn_credentials') . ' WHERE id = %i FOR UPDATE',
                $credentialRowId
            );
            if ($signCount === null) {
                DB::rollback();
                return $this->error(404, 'Passkey not found');
            }
            $newSignCount = min((int) $signCount + 1, 0xFFFFFFFF);

            $authenticatorData = webauthnBuildAuthenticatorData(
                $request['rp_id'],
                webauthnBuildFlags($request['user_verified']),
                $newSignCount
            );
            $signature = webauthnSignAssertion($credentialPem, $authenticatorData, $request['client_data_json']);
            $credentialPem = '';

            DB::update(
                prefixTable('webauthn_credentials'),
                [
                    'sign_count' => $newSignCount,
                    'last_used_at' => time(),
                    'last_used_by' => $userId,
                ],
                'id = %i',
                $credentialRowId
            );

            // Every use of a shared credential is attributable: that is the point of signing here.
            logItems(
                $SETTINGS,
                $itemId,
                (string) $credential['label'],
                $userId,
                'at_webauthn_credential_used',
                (string) $userData['username'],
                $request['rp_id'],
                null,
                null,
                null,
                false,
                true
            );

            DB::commit();
            $transactionStarted = false;

            emitItemSyslog($SETTINGS, $itemId, (string) $credential['label'], 'at_webauthn_credential_used', (string) $userData['username']);

            return [
                'error' => false,
                'credential_id' => $request['credential_id'],
                'authenticator_data' => webauthnBase64UrlEncode($authenticatorData),
                'signature' => webauthnBase64UrlEncode($signature),
                'user_handle' => (string) $credential['user_handle'],
            ];
        } catch (Throwable $e) {
            if ($transactionStarted === true) {
                DB::rollback();
            }
            $this->logFailure('assertCredential', $credentialRowId, $e);

            return $this->error(500, 'An internal error occurred while signing with the passkey.');
        }
    }

    /**
     * Delete a passkey and its sharekeys.
     *
     * @param array<string, mixed> $userData        JWT user data
     * @param int                  $credentialRowId webauthn_credentials.id
     *
     * @return array<string, mixed> Error envelope or the deletion result
     */
    public function deleteCredential(array $userData, int $credentialRowId): array
    {
        $userId = (int) $userData['id'];
        $transactionStarted = false;

        try {
            $SETTINGS = (new ConfigManager())->getAllSettings();

            DB::startTransaction();
            $transactionStarted = true;

            $credential = DB::queryFirstRow(
                'SELECT w.id, w.item_id, w.rp_id, i.id_tree, i.label, i.deleted_at
                FROM ' . prefixTable('webauthn_credentials') . ' AS w
                INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = w.item_id)
                WHERE w.id = %i
                FOR UPDATE',
                $credentialRowId
            );
            if ($credential === null || $credential['deleted_at'] !== null) {
                DB::rollback();
                return $this->error(404, 'Passkey not found');
            }

            $itemId = (int) $credential['item_id'];
            $folderId = (int) $credential['id_tree'];
            if ($this->canEditItem($userData, $folderId, $itemId) === false) {
                DB::rollback();
                return $this->error(403, 'Access denied: you are not allowed to edit this item');
            }

            DB::delete(prefixTable('sharekeys_webauthn'), 'object_id = %i', $credentialRowId);
            DB::delete(prefixTable('webauthn_credentials'), 'id = %i', $credentialRowId);

            logItems(
                $SETTINGS,
                $itemId,
                (string) $credential['label'],
                $userId,
                'at_modification',
                (string) $userData['username'],
                'at_webauthn_credential_deleted : ' . $credential['rp_id'],
                null,
                null,
                null,
                false,
                true
            );

            emitItemEvent('updated', $itemId, $folderId, (string) $credential['label'], (string) $userData['username'], $userId);

            DB::commit();
            $transactionStarted = false;

            emitItemSyslog($SETTINGS, $itemId, (string) $credential['label'], 'at_modification', (string) $userData['username']);

            return [
                'error' => false,
                'id' => $credentialRowId,
                'item_id' => $itemId,
            ];
        } catch (Throwable $e) {
            if ($transactionStarted === true) {
                DB::rollback();
                resetItemRevisionMemo();
            }
            $this->logFailure('deleteCredential', $credentialRowId, $e);

            return $this->error(500, 'An internal error occurred while deleting the passkey.');
        }
    }

    /**
     * Tell whether the caller may modify an item: folder access, item restriction and edit
     * right (NE, NDNE and R block it).
     *
     * @param array<string, mixed> $userData JWT user data
     * @param int                  $folderId Item folder
     * @param int                  $itemId   Item
     *
     * @return bool
     */
    private function canEditItem(array $userData, int $folderId, int $itemId): bool
    {
        $userId = (int) $userData['id'];
        $folderAccessModel = new FolderAccessModel();

        return $folderAccessModel->canAccessItemInFolder($userData, $folderId, $itemId) === true
            && $folderAccessModel->satisfiesItemRestriction($itemId, $userId) === true
            && $folderAccessModel->canEditInFolder($folderId, $userId) === true;
    }

    /**
     * Build the error envelope the controllers expect.
     *
     * @param int    $status  HTTP status
     * @param string $message Message returned to the client
     *
     * @return array{error: true, error_header: string, error_message: string}
     */
    private function error(int $status, string $message): array
    {
        return [
            'error' => true,
            'error_header' => 'HTTP/1.1 ' . $status . ' ' . (BaseController::HTTP_REASONS[$status] ?? 'Error'),
            'error_message' => $message,
        ];
    }

    /**
     * Log an unexpected failure server-side; the client only receives a generic message.
     *
     * @param string    $operation Model method
     * @param int       $objectId  Item or credential id
     * @param Throwable $e         Failure
     *
     * @return void
     */
    private function logFailure(string $operation, int $objectId, Throwable $e): void
    {
        error_log(
            '[API] WebauthnModel::' . $operation . ' failed for ' . $objectId
            . ' [' . get_class($e) . ':' . (int) $e->getCode() . '] ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine()
        );
    }
}
