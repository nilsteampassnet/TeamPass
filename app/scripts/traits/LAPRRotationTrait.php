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
 * @file      LAPRRotationTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Lapr\LAPRSshService;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../includes/libraries/teampassclasses/lapr/src/LAPRSshService.php';
require_once __DIR__ . '/../../sources/lapr.functions.php';

/**
 * LAPR password rotation (Point 4). Four steps: load, generate, SSH push,
 * item update. SSH-first failure model (decision D1/A) with a pre-flight item
 * read so the TP_USER key chain is proven before touching the server. Host-key
 * mismatch BLOCKS the rotation (decision D4). Runs only in the CLI worker.
 */
trait LAPRRotationTrait
{
    /**
     * Entry point for the 'lapr_rotation' process type.
     *
     * Task arguments: { account_id, endpoint_id, trigger: manual|scheduler|enroll, author }
     *
     * @param array $arguments Task arguments
     * @return void
     */
    private function handleLaprRotation(array $arguments): void
    {
        $accountId = (int) ($arguments['account_id'] ?? 0);
        $trigger = (string) ($arguments['trigger'] ?? 'manual');
        $actorId = (int) ($arguments['author'] ?? TP_USER_ID);
        $allowPausedEndpoint = $trigger === 'manual'
            && (bool) ($arguments['allow_paused_endpoint'] ?? false);

        $result = $this->laprRotationExecute($accountId, $trigger, $actorId, $allowPausedEndpoint);

        $this->updateTaskResult($result);
        $this->updateTaskStatus('completed');
    }

    /**
     * Execute one rotation. Returns a secret-free result the poller reads.
     *
     * @param int    $accountId Managed account id
     * @param string $trigger   manual | scheduler | enroll
     * @param int    $actorId   Acting user id (TP_USER_ID for the scheduler)
     * @param bool   $allowPausedEndpoint Explicit manual break-glass confirmation
     *
     * @return array{success: bool, error_code?: string, message?: string}
     */
    private function laprRotationExecute(
        int $accountId,
        string $trigger,
        int $actorId,
        bool $allowPausedEndpoint = false
    ): array
    {
        // Step 1 — load account + endpoint + item
        $account = DB::queryFirstRow(
            'SELECT a.id, a.endpoint_id, a.item_id, a.username_cache, a.policy_id, a.last_rotation_at,
                    e.hostname, e.port, e.ssh_username, e.ssh_auth_method, e.ssh_credential_source,
                    e.label AS endpoint_label, e.ssh_hostkey_fingerprint, e.ssh_hostkey_verified,
                    e.os_info, e.capabilities, e.status AS endpoint_status,
                    i.id_tree, i.label
             FROM ' . prefixTable('lapr_accounts') . ' AS a
             INNER JOIN ' . prefixTable('lapr_endpoints') . ' AS e ON e.id = a.endpoint_id
             INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = a.item_id
             WHERE a.id = %i AND a.status != %s',
            $accountId,
            'deleted'
        );
        if ($account === null) {
            return ['success' => false, 'error_code' => 'ERR_ACCOUNT_NOT_FOUND'];
        }

        if (laprIsModuleEnabledFresh() === false) {
            return ['success' => false, 'error_code' => 'ERR_LAPR_DISABLED', 'message' => 'LAPR_DISABLED'];
        }
        if ((string) $account['endpoint_status'] === 'deleted') {
            return ['success' => false, 'error_code' => 'ERR_ENDPOINT_DELETED', 'message' => 'ENDPOINT_REMOVED'];
        }
        if ((string) $account['endpoint_status'] === 'disabled' && $allowPausedEndpoint === false) {
            return ['success' => false, 'error_code' => 'ERR_ENDPOINT_PAUSED', 'message' => 'ENDPOINT_ROTATIONS_PAUSED'];
        }
        if ((string) $account['endpoint_status'] === 'error' && $trigger !== 'manual') {
            return ['success' => false, 'error_code' => 'ERR_CANNOT_ROTATE', 'message' => 'ENDPOINT_CHECK_REQUIRED'];
        }
        if ((string) $account['endpoint_status'] === 'disabled' && $allowPausedEndpoint === true) {
            laprAuditLog(
                'rotation_pause_override',
                (int) $account['endpoint_id'],
                $actorId,
                ['trigger' => 'manual'],
                'success',
                $accountId,
                null,
                'system'
            );
        }

        $username = (string) $account['username_cache'];
        // R1 — hard username validation before anything reaches SSH.
        if (laprValidateUsername($username) === false) {
            $this->laprFinalizeFailure($account, $actorId, 'ERR_INVALID_USERNAME', $trigger);
            return ['success' => false, 'error_code' => 'ERR_INVALID_USERNAME'];
        }

        $osInfo = json_decode((string) ($account['os_info'] ?? '{}'), true) ?: [];
        $storedSelfTarget = (bool) ($osInfo['lapr_self_target']['is_self'] ?? false);
        $runtimeSelfTarget = laprClassifySelfTarget((string) $account['hostname'], $this->settings);
        if ($trigger !== 'manual' && ($storedSelfTarget === true || $runtimeSelfTarget['is_self'] === true)) {
            return [
                'success' => false,
                'error_code' => 'ERR_SELF_TARGET_AUTOMATIC_ROTATION_BLOCKED',
                'message' => 'SELF_TARGET_MANUAL_ONLY',
            ];
        }

        if (laprEndpointTargetExists(
            (string) $account['hostname'],
            (int) $account['port'],
            (int) $account['endpoint_id']
        ) === true) {
            $this->laprFinalizeFailure($account, $actorId, 'ERR_DUPLICATE_ENDPOINT_TARGET', $trigger);
            return ['success' => false, 'error_code' => 'ERR_DUPLICATE_ENDPOINT_TARGET'];
        }

        // Legacy-data safety: never rotate an item that is also used as an SSH
        // private key, nor a password credential belonging to another login or
        // endpoint. New relationships are blocked at enrollment/account add,
        // but this pre-flight protects databases created before those guards.
        $credentialConflict = laprManagedItemCredentialConflict(
            (int) $account['item_id'],
            (int) $account['endpoint_id'],
            $username
        );
        if ($credentialConflict === 'key_credential') {
            $this->laprFinalizeFailure($account, $actorId, 'ERR_KEY_CREDENTIAL_MANAGED', $trigger);
            return ['success' => false, 'error_code' => 'ERR_KEY_CREDENTIAL_MANAGED'];
        }
        if ($credentialConflict === 'password_credential') {
            $this->laprFinalizeFailure($account, $actorId, 'ERR_CREDENTIAL_RELATION_CONFLICT', $trigger);
            return ['success' => false, 'error_code' => 'ERR_CREDENTIAL_RELATION_CONFLICT'];
        }
        if ((string) $account['ssh_auth_method'] === 'password'
            && $username === (string) $account['ssh_username']
        ) {
            $credentialUseCount = (int) DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable('lapr_endpoints') . '
                 WHERE ssh_credential_source = %i AND status != %s',
                (int) $account['ssh_credential_source'],
                'deleted'
            );
            if ($credentialUseCount > 1) {
                $this->laprFinalizeFailure($account, $actorId, 'ERR_SHARED_PASSWORD_CREDENTIAL', $trigger);
                return ['success' => false, 'error_code' => 'ERR_SHARED_PASSWORD_CREDENTIAL'];
            }
        }

        // Load TP_USER key chain (server-side decryptable)
        try {
            $tpKeys = laprGetTpUserPrivateKey($this->settings);
        } catch (Throwable $e) {
            $this->laprFinalizeFailure($account, $actorId, 'ERR_SERVER_KEY', $trigger);
            return ['success' => false, 'error_code' => 'ERR_SERVER_KEY'];
        }

        // Pre-flight: read the current item password (proves the chain works and
        // gives old_value for history) BEFORE touching the server (D1/A pre-flight).
        $oldPassword = laprReadItemPasswordAsTpUser((int) $account['item_id'], $tpKeys['private_key'], $tpKeys['public_key']);

        // Read the SSH connection credential from its linked item.
        $sshSecret = laprReadItemPasswordAsTpUser((int) $account['ssh_credential_source'], $tpKeys['private_key'], $tpKeys['public_key']);
        if ($sshSecret === '') {
            $this->laprUpdateEndpointRotationState(
                (int) $account['endpoint_id'],
                $actorId,
                LAPRSshService::ERR_AUTH_FAILED
            );
            $this->laprFinalizeFailure($account, $actorId, LAPRSshService::ERR_AUTH_FAILED, $trigger);
            return ['success' => false, 'error_code' => LAPRSshService::ERR_AUTH_FAILED];
        }

        // Step 2 — generate the new password per policy (defaults if none, D3)
        try {
            $newPassword = $this->laprGeneratePasswordForAccount((int) ($account['policy_id'] ?? 0));
        } catch (Throwable $e) {
            $this->laprFinalizeFailure($account, $actorId, 'ERR_PASSWORD_GEN', $trigger);
            return ['success' => false, 'error_code' => 'ERR_PASSWORD_GEN'];
        }

        // Defence in depth (R3): re-check the allowlist against the stored hostname
        // before opening any connection. A stored endpoint whose hostname was later
        // moved outside the allowlist must not be reachable by the rotator.
        if (laprIsHostnameAllowed((string) $account['hostname'], $this->settings) === false) {
            $this->laprUpdateEndpointRotationState((int) $account['endpoint_id'], $actorId, 'ERR_HOSTNAME_NOT_ALLOWED');
            $this->laprFinalizeFailure($account, $actorId, 'ERR_HOSTNAME_NOT_ALLOWED', $trigger);
            return ['success' => false, 'error_code' => 'ERR_HOSTNAME_NOT_ALLOWED'];
        }

        // Step 3 — SSH push
        // D4 — host key verification BLOCKS on mismatch (unless verification was
        // deliberately disabled at enrollment). A changed key while we hold a
        // reusable SSH credential is exactly the MITM signal (risk R2), so the
        // check is enforced inside connect() BEFORE the credential is sent.
        $expectedFingerprint = null;
        if ((int) $account['ssh_hostkey_verified'] === 1
            && (string) $account['ssh_hostkey_fingerprint'] !== ''
        ) {
            $expectedFingerprint = (string) $account['ssh_hostkey_fingerprint'];
        }

        $timeout = (int) ($this->settings['lapr_ssh_connect_timeout'] ?? 10);
        $service = new LAPRSshService($timeout);
        $connect = $service->connect(
            (string) $account['hostname'],
            (int) $account['port'],
            (string) $account['ssh_username'],
            (string) $account['ssh_auth_method'],
            $sshSecret,
            $expectedFingerprint
        );
        if ($connect['success'] !== true) {
            $service->disconnect();
            $code = isset($connect['error_code']) ? (string) $connect['error_code'] : LAPRSshService::ERR_UNKNOWN;
            $this->laprUpdateEndpointRotationState((int) $account['endpoint_id'], $actorId, $code);
            if ($code === LAPRSshService::ERR_HOSTKEY_MISMATCH) {
                laprAuditLog('hostkey_mismatch', (int) $account['endpoint_id'], $actorId, [
                    'account_id' => $accountId,
                ], 'failure', $accountId, 'ERR_HOSTKEY_MISMATCH', 'system');
            }
            $this->laprFinalizeFailure($account, $actorId, $code, $trigger);
            return ['success' => false, 'error_code' => $code];
        }

        // D5 — root vs passwordless sudo for chpasswd
        $caps = json_decode((string) ($account['capabilities'] ?? '{}'), true) ?: [];
        $isRoot = (bool) ($osInfo['is_root'] ?? false);
        $useSudo = $isRoot === false && (bool) ($caps['has_sudo'] ?? false);

        // Re-read the switch and relationship state at the last safe instant.
        // If LAPR was disabled or the managed account was removed while this
        // worker was connecting, do not mutate the remote account. Once
        // changePassword succeeds, the TeamPass update below must always
        // finish to avoid desynchronization.
        if (laprIsModuleEnabledFresh() === false) {
            $service->disconnect();
            return ['success' => false, 'error_code' => 'ERR_LAPR_DISABLED', 'message' => 'LAPR_DISABLED'];
        }
        $accountStillManaged = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('lapr_accounts') . '
             WHERE id = %i AND status != %s',
            $accountId,
            'deleted'
        );
        if ($accountStillManaged === 0) {
            $service->disconnect();
            return ['success' => false, 'error_code' => 'ERR_ACCOUNT_DELETED', 'message' => 'ACCOUNT_REMOVED'];
        }
        $freshEndpointStatus = DB::queryFirstField(
            'SELECT status FROM ' . prefixTable('lapr_endpoints') . ' WHERE id = %i',
            (int) $account['endpoint_id']
        );
        if ($freshEndpointStatus === null || (string) $freshEndpointStatus === 'deleted') {
            $service->disconnect();
            return ['success' => false, 'error_code' => 'ERR_ENDPOINT_DELETED', 'message' => 'ENDPOINT_REMOVED'];
        }
        if ((string) $freshEndpointStatus === 'disabled' && $allowPausedEndpoint === false) {
            $service->disconnect();
            return ['success' => false, 'error_code' => 'ERR_ENDPOINT_PAUSED', 'message' => 'ENDPOINT_ROTATIONS_PAUSED'];
        }
        if ((string) $freshEndpointStatus === 'error' && $trigger !== 'manual') {
            $service->disconnect();
            return ['success' => false, 'error_code' => 'ERR_CANNOT_ROTATE', 'message' => 'ENDPOINT_CHECK_REQUIRED'];
        }

        $change = $service->changePassword($username, $newPassword, $useSudo);
        $service->disconnect();
        if ($change['success'] !== true) {
            $code = isset($change['error_code']) ? (string) $change['error_code'] : LAPRSshService::ERR_CHPASSWD_FAILED;
            // A chpasswd rejection is scoped to this Linux account (PAM,
            // locked account, stale username, etc.) and must not disable every
            // other account on the endpoint. Only a transport loss observed
            // while opening/running the remote command changes endpoint state.
            if (laprIsTransientConnectionError($code) === true) {
                $this->laprUpdateEndpointRotationState((int) $account['endpoint_id'], $actorId, $code);
            }
            $this->laprFinalizeFailure($account, $actorId, $code, $trigger);
            return ['success' => false, 'error_code' => $code];
        }

        // Step 4 — item update (SSH already succeeded — D1/A). A failure here is
        // the "MANUAL RESYNC REQUIRED" case: the server holds the new password.
        $itemUpdated = $this->laprUpdateItemPassword(
            (int) $account['item_id'],
            (int) $account['id_tree'],
            (string) $account['label'],
            $newPassword,
            $oldPassword,
            $tpKeys,
            $actorId
        );
        if ($itemUpdated === false) {
            DB::update(prefixTable('lapr_accounts'), [
                'status' => 'error',
                'last_rotation_at' => date('Y-m-d H:i:s'),
                'last_rotation_status' => 'failure',
                'last_rotation_error' => 'ERR_ITEM_UPDATE_FAILED',
                'retry_count' => 0,
                'retry_at' => null,
                'updated_by' => $actorId,
            ], 'id = %i AND status != %s', $accountId, 'deleted');
            laprAuditLog('rotation', (int) $account['endpoint_id'], $actorId, [
                'trigger' => $trigger,
                'manual_resync_required' => true,
            ], 'failure', $accountId, 'ERR_ITEM_UPDATE_FAILED', 'system');
            $this->laprSendRotationFailureAlert(
                $account,
                'ERR_ITEM_UPDATE_FAILED',
                $trigger,
                'action_required'
            );
            return ['success' => false, 'error_code' => 'ERR_ITEM_UPDATE_FAILED', 'message' => 'MANUAL_RESYNC_REQUIRED'];
        }

        // R5 — SSH credential sync: when the managed account IS the SSH connection
        // account, keep the credential item in step so the next connection works.
        if ((string) $account['ssh_auth_method'] === 'password'
            && (int) $account['ssh_credential_source'] > 0
            && $username === (string) $account['ssh_username']
            && (int) $account['ssh_credential_source'] !== (int) $account['item_id']
        ) {
            $credItem = DB::queryFirstRow(
                'SELECT id, id_tree, label FROM ' . prefixTable('items') . ' WHERE id = %i',
                (int) $account['ssh_credential_source']
            );
            $credentialSynced = false;
            if ($credItem !== null) {
                $syncOld = laprReadItemPasswordAsTpUser((int) $credItem['id'], $tpKeys['private_key'], $tpKeys['public_key']);
                $credentialSynced = $this->laprUpdateItemPassword(
                    (int) $credItem['id'],
                    (int) $credItem['id_tree'],
                    (string) $credItem['label'],
                    $newPassword,
                    $syncOld,
                    $tpKeys,
                    $actorId
                );
            }

            if ($credentialSynced === false) {
                DB::update(prefixTable('lapr_accounts'), [
                    'status' => 'error',
                    'last_rotation_at' => date('Y-m-d H:i:s'),
                    'last_rotation_status' => 'failure',
                    'last_rotation_error' => 'ERR_SSH_CREDENTIAL_SYNC_FAILED',
                    'retry_count' => 0,
                    'retry_at' => null,
                    'updated_by' => $actorId,
                ], 'id = %i AND status != %s', $accountId, 'deleted');
                laprAuditLog('ssh_credential_sync', (int) $account['endpoint_id'], $actorId, [
                    'credential_item_id' => (int) ($account['ssh_credential_source'] ?? 0),
                    'manual_resync_required' => true,
                ], 'failure', $accountId, 'ERR_SSH_CREDENTIAL_SYNC_FAILED', 'system');
                laprAuditLog('rotation', (int) $account['endpoint_id'], $actorId, [
                    'trigger' => $trigger,
                    'manual_resync_required' => true,
                    'managed_item_updated' => true,
                ], 'failure', $accountId, 'ERR_SSH_CREDENTIAL_SYNC_FAILED', 'system');
                $this->laprSendRotationFailureAlert(
                    $account,
                    'ERR_SSH_CREDENTIAL_SYNC_FAILED',
                    $trigger,
                    'action_required'
                );

                return [
                    'success' => false,
                    'error_code' => 'ERR_SSH_CREDENTIAL_SYNC_FAILED',
                    'message' => 'SSH_CREDENTIAL_RESYNC_REQUIRED',
                ];
            }

            laprAuditLog('ssh_credential_sync', (int) $account['endpoint_id'], $actorId, [
                'credential_item_id' => (int) $account['ssh_credential_source'],
            ], 'success', $accountId, null, 'system');
        }

        // Success — update account rotation state + next_rotation_at
        $frequencyDays = $this->laprAccountFrequencyDays((int) ($account['policy_id'] ?? 0));
        $now = date('Y-m-d H:i:s');
        DB::update(prefixTable('lapr_accounts'), [
            'status' => 'active',
            'last_rotation_at' => $now,
            'last_rotation_status' => 'success',
            'last_rotation_error' => null,
            'next_rotation_at' => laprComputeNextRotation($now, $frequencyDays),
            'retry_count' => 0,
            'retry_at' => null,
            'updated_by' => $actorId,
        ], 'id = %i AND status != %s', $accountId, 'deleted');
        $this->laprUpdateEndpointRotationState((int) $account['endpoint_id'], $actorId, null);

        laprAuditLog('rotation', (int) $account['endpoint_id'], $actorId, [
            'trigger' => $trigger,
            'username' => $username,
            'used_sudo' => $useSudo,
        ], 'success', $accountId, null, 'system');

        return ['success' => true, 'message' => 'ROTATION_SUCCESS'];
    }

    /**
     * Re-encrypt and store a new item password with the full TeamPass write-path
     * obligations (correction C4): pw/pw_iv/pw_len, sharekey fan-out to all
     * holders, password-history log (old_value), WebSocket event, HIBP reset.
     *
     * @param int    $itemId      Item id
     * @param int    $folderId    Item folder id (nested_tree)
     * @param string $label       Item label
     * @param string $newPassword New cleartext password
     * @param string $oldPassword Previous cleartext password (for history)
     * @param array  $tpKeys      TP_USER key pair (unused directly, kept for parity)
     * @param int    $actorId     Acting user id
     *
     * @return bool True on success
     */
    private function laprUpdateItemPassword(
        int $itemId,
        int $folderId,
        string $label,
        string $newPassword,
        string $oldPassword,
        array $tpKeys,
        int $actorId
    ): bool {
        try {
            // Encrypt the new password with a fresh objectKey.
            $crypted = doDataEncryption($newPassword);

            DB::update(prefixTable('items'), [
                'pw' => $crypted['encrypted'],
                'pw_iv' => $crypted['meta'],
                'pw_len' => strlen($newPassword),
                'encryption_type' => TP_ENCRYPTION_NAME,
                'updated_at' => time(),
                // Password changed — the previous HIBP verdict no longer applies.
                'hibp_status' => 0,
                'hibp_count' => 0,
                'hibp_checked_at' => '',
            ], 'id = %i', $itemId);

            // Re-encrypt the new objectKey for ALL existing sharekey holders and
            // clean stale keys. Non-personal item ⇒ all-eligible-users branch.
            // apiUserId = TP_USER_ID so no HTTP session is required in the worker.
            storeUsersShareKey(
                'sharekeys_items',
                0,                       // public object (personal items are never LAPR-managed)
                $itemId,
                $crypted['objectKey'],
                true,                    // onlyForUser (deprecated, ignored)
                true,                    // deleteAll — full fan-out cleanup
                [],
                -1,
                (int) TP_USER_ID         // apiUserId — avoids session dependency in CLI
            );

            // Password history (SEC-6: master-key encrypted, independent of sharekeys).
            $previousValue = cryption($oldPassword, '', 'encrypt');
            logItems(
                $this->settings,
                $itemId,
                $label,
                $actorId,
                'at_modification',
                'LAPR',
                'at_pw',
                TP_ENCRYPTION_NAME,
                null,
                isset($previousValue['string']) ? (string) $previousValue['string'] : ''
            );

            // WebSocket sync (CLAUDE.md rule).
            emitItemEvent('updated', $itemId, $folderId, $label, 'LAPR');

            return true;
        } catch (Throwable $e) {
            error_log('TEAMPASS LAPR - laprUpdateItemPassword failed for item ' . $itemId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a password for an account's policy (defaults when no policy, D3).
     *
     * @param int $policyId Policy id (0 = none)
     *
     * @return string
     * @throws Exception When no Linux-safe password could be generated
     */
    private function laprGeneratePasswordForAccount(int $policyId): string
    {
        if ($policyId <= 0) {
            return laprGeneratePassword(LAPR_DEFAULT_PASSWORD_LENGTH, true, true, true, true);
        }

        $policy = DB::queryFirstRow(
            'SELECT password_length, use_uppercase, use_lowercase, use_digits, use_symbols
             FROM ' . prefixTable('lapr_policies') . ' WHERE id = %i',
            $policyId
        );
        if ($policy === null) {
            return laprGeneratePassword(LAPR_DEFAULT_PASSWORD_LENGTH, true, true, true, true);
        }

        return laprGeneratePassword(
            (int) $policy['password_length'],
            (int) $policy['use_uppercase'] === 1,
            (int) $policy['use_lowercase'] === 1,
            (int) $policy['use_digits'] === 1,
            (int) $policy['use_symbols'] === 1
        );
    }

    /**
     * Resolve an account policy frequency in days (default when no policy).
     *
     * @param int $policyId Policy id (0 = none)
     *
     * @return int
     */
    private function laprAccountFrequencyDays(int $policyId): int
    {
        if ($policyId <= 0) {
            return LAPR_DEFAULT_FREQUENCY_DAYS;
        }
        $freq = DB::queryFirstField(
            'SELECT frequency_days FROM ' . prefixTable('lapr_policies') . ' WHERE id = %i',
            $policyId
        );

        return $freq !== null ? (int) $freq : LAPR_DEFAULT_FREQUENCY_DAYS;
    }

    /**
     * Record a rotation failure on the account and in the audit log. For the
     * scheduler trigger (Point 5), apply retry/suspension logic: increment
     * retry_count and schedule a retry until lapr_max_retries is reached, then
     * suspend the account (status 'paused') with a rotation_suspended audit.
     * Manual/enroll failures simply mark the account 'error'.
     *
     * @param array  $account   Account row
     * @param int    $actorId   Acting user id
     * @param string $errorCode LAPR error code (no secret)
     * @param string $trigger   manual | scheduler | enroll
     *
     * @return void
     */
    private function laprFinalizeFailure(array $account, int $actorId, string $errorCode, string $trigger = 'manual'): void
    {
        $accountId = (int) $account['id'];
        $now = date('Y-m-d H:i:s');

        laprAuditLog('rotation', (int) $account['endpoint_id'], $actorId, [
            'trigger' => $trigger,
            'username' => (string) $account['username_cache'],
        ], 'failure', $accountId, $errorCode, 'system');

        $permanentConfigurationFailure = in_array($errorCode, [
            'ERR_KEY_CREDENTIAL_MANAGED',
            'ERR_CREDENTIAL_RELATION_CONFLICT',
            'ERR_SHARED_PASSWORD_CREDENTIAL',
            'ERR_DUPLICATE_ENDPOINT_TARGET',
        ], true);
        $retryableFailure = laprIsTransientConnectionError($errorCode);
        if ($trigger !== 'scheduler'
            || $permanentConfigurationFailure === true
            || $retryableFailure === false
        ) {
            DB::update(prefixTable('lapr_accounts'), [
                'last_rotation_at' => $now,
                'last_rotation_status' => 'failure',
                'last_rotation_error' => $errorCode,
                'status' => 'error',
                'retry_count' => 0,
                'retry_at' => null,
                'updated_by' => $actorId,
            ], 'id = %i AND status != %s', $accountId, 'deleted');
            $this->laprSendRotationFailureAlert($account, $errorCode, $trigger, 'action_required');
            return;
        }

        // Scheduler retry/suspension logic (FUNC-4-like at the LAPR level).
        $maxRetries = max(0, (int) ($this->settings['lapr_max_retries'] ?? 3));
        $retryDelayMinutes = max(1, (int) ($this->settings['lapr_retry_delay_minutes'] ?? 60));

        $current = DB::queryFirstRow(
            'SELECT retry_count FROM ' . prefixTable('lapr_accounts') . ' WHERE id = %i',
            $accountId
        );
        $retryCount = (int) ($current['retry_count'] ?? 0) + 1;

        if ($retryCount > $maxRetries) {
            // Suspend — stop retrying automatically until an admin resets the account.
            DB::update(prefixTable('lapr_accounts'), [
                'last_rotation_at' => $now,
                'last_rotation_status' => 'failure',
                'last_rotation_error' => $errorCode,
                'status' => 'paused',
                'retry_count' => $retryCount,
                'retry_at' => null,
                'updated_by' => $actorId,
            ], 'id = %i AND status != %s', $accountId, 'deleted');

            laprAuditLog('rotation_suspended', (int) $account['endpoint_id'], $actorId, [
                'retry_count' => $retryCount,
                'max_retries' => $maxRetries,
            ], 'warning', $accountId, $errorCode, 'system');
            $this->laprSendRotationFailureAlert($account, $errorCode, $trigger, 'suspended');
            return;
        }

        // Schedule the next retry.
        $retryAt = date('Y-m-d H:i:s', time() + $retryDelayMinutes * 60);
        DB::update(prefixTable('lapr_accounts'), [
            'last_rotation_at' => $now,
            'last_rotation_status' => 'failure',
            'last_rotation_error' => $errorCode,
            'status' => 'active',
            'retry_count' => $retryCount,
            'retry_at' => $retryAt,
            'next_rotation_at' => $retryAt,
            'updated_by' => $actorId,
        ], 'id = %i AND status != %s', $accountId, 'deleted');

        laprAuditLog('rotation_retry_scheduled', (int) $account['endpoint_id'], $actorId, [
            'trigger' => $trigger,
            'retry_count' => $retryCount,
            'retry_at' => $retryAt,
        ], 'warning', $accountId, $errorCode, 'system');
        // Notify immediately on the first outage, then stay quiet while the
        // configured retries run. A second notification is sent only if the
        // account ultimately reaches the suspended state above.
        if ($retryCount === 1) {
            $this->laprSendRotationFailureAlert($account, $errorCode, $trigger, 'retry_scheduled', $retryAt);
        }
    }

    /**
     * Send the optional administrator alert for a rotation failure. Only
     * operational metadata is substituted; passwords and SSH diagnostics are
     * deliberately excluded.
     *
     * @param array       $account   Account/endpoint row
     * @param string      $errorCode LAPR error taxonomy code
     * @param string      $trigger   manual|scheduler|enroll
     * @param string      $state     action_required|retry_scheduled|suspended
     * @param string|null $retryAt   Next retry datetime
     * @return void
     */
    private function laprSendRotationFailureAlert(
        array $account,
        string $errorCode,
        string $trigger,
        string $state,
        ?string $retryAt = null
    ): void {
        if ((int) ($this->settings['lapr_alert_email_enabled'] ?? 0) !== 1) {
            return;
        }
        $recipient = trim((string) ($this->settings['lapr_alert_email_recipient'] ?? ''));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            $lang = new Language((string) ($this->settings['default_language'] ?? 'english'));
            $stateLabels = [
                'action_required' => $lang->get('lapr_alert_state_action_required'),
                'retry_scheduled' => $lang->get('lapr_alert_state_retry_scheduled'),
                'suspended' => $lang->get('lapr_alert_state_suspended'),
            ];
            $body = str_replace(
                ['#endpoint#', '#hostname#', '#username#', '#error_code#', '#trigger#', '#state#', '#retry_at#', '#url#'],
                [
                    (string) ($account['endpoint_label'] ?? ''),
                    (string) ($account['hostname'] ?? ''),
                    (string) ($account['username_cache'] ?? ''),
                    $errorCode,
                    $trigger,
                    (string) ($stateLabels[$state] ?? $state),
                    $retryAt ?? '—',
                    rtrim((string) ($this->settings['cpassman_url'] ?? ''), '/') . '/index.php?page=lapr_accounts',
                ],
                $lang->get('lapr_rotation_failure_email_body')
            );
            prepareSendingEmail(
                getEmailTemplateSubject('lapr_rotation_failure', $lang),
                $body,
                $recipient,
                'LAPR'
            );
        } catch (Throwable $e) {
            if (LOG_TASKS === true) {
                $this->logger->log('LAPR rotation alert could not be sent: ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    /**
     * Keep endpoint availability in step with real rotation connections. A
     * successful rotation confirms reachability; a network failure marks the
     * endpoint unreachable, while host-key/credential failures require action.
     *
     * A rotation is not a full endpoint health check: it does not recollect OS
     * or capability metadata. It therefore never refreshes last_check_at and
     * never postpones next_check_at. A transient observation may only advance
     * the next full check so recovery can be detected sooner.
     *
     * @param int         $endpointId Endpoint id
     * @param int         $actorId    Acting user
     * @param string|null $errorCode  Null on success
     * @return void
     */
    private function laprUpdateEndpointRotationState(int $endpointId, int $actorId, ?string $errorCode): void
    {
        $endpoint = DB::queryFirstRow(
            'SELECT status, next_check_at FROM ' . prefixTable('lapr_endpoints') . ' WHERE id = %i',
            $endpointId
        );
        if ($endpoint === null || (string) $endpoint['status'] === 'deleted') {
            return;
        }

        $currentStatus = (string) $endpoint['status'];
        $paused = $currentStatus === 'disabled';
        $update = [
            'last_error' => $errorCode,
            'updated_by' => $actorId,
        ];

        if ($paused === false) {
            $update['status'] = $errorCode === null ? 'active' : laprEndpointStatusForCheckFailure($errorCode);
        }

        if (laprIsTransientConnectionError((string) $errorCode) === true) {
            $retryCheckAt = laprComputeNextEndpointCheck($this->settings, null, $errorCode);
            $currentNextCheckAt = (string) ($endpoint['next_check_at'] ?? '');
            $currentNextCheckTs = $currentNextCheckAt !== '' ? strtotime($currentNextCheckAt) : false;
            $retryCheckTs = strtotime($retryCheckAt);
            if ($currentNextCheckTs === false || ($retryCheckTs !== false && $retryCheckTs < $currentNextCheckTs)) {
                $update['next_check_at'] = $retryCheckAt;
            }
        }

        // Status qualification makes a concurrent pause authoritative. The
        // second update keeps diagnostics current without ever re-enabling a
        // paused endpoint or changing its normal health-check cadence.
        if ($paused === false) {
            DB::update(prefixTable('lapr_endpoints'), $update, 'id = %i AND status = %s', $endpointId, $currentStatus);
        }
        unset($update['status'], $update['next_check_at']);
        DB::update(prefixTable('lapr_endpoints'), $update, 'id = %i AND status = %s', $endpointId, 'disabled');
    }
}
