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
 * @file      LAPRSshTestTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Lapr\LAPRSshService;

require_once __DIR__ . '/../../includes/libraries/teampassclasses/lapr/src/LAPRSshService.php';
require_once __DIR__ . '/../../sources/lapr.functions.php';

/**
 * Background SSH connection test + system-info collection for LAPR endpoint
 * enrollment (Point 1). Runs as a standalone background task the enrollment
 * modal polls (test_status). Never runs in the HTTP thread.
 */
trait LAPRSshTestTrait
{
    /**
     * Entry point for the 'lapr_ssh_test' process type.
     *
     * Task arguments:
     *   - endpoint: {hostname, port, ssh_username, ssh_auth_method}
     *   - credential_item_id: teampass_items.id holding the SSH secret
     *   - author: user id who launched the test
     *
     * Writes the result (fingerprint, os_info, capabilities, can_rotate) into
     * the task `output` JSON. Secrets are never written anywhere.
     *
     * @param array $arguments Task arguments
     * @return void
     */
    private function handleLaprSshTest(array $arguments): void
    {
        // Existing endpoint checks use the stored, already-enrolled connection
        // data. Keeping the same process type preserves the background-only SSH
        // guarantee and the existing module-disable cancellation path.
        if ((int) ($arguments['endpoint_id'] ?? 0) > 0) {
            $this->handleLaprEndpointCheck($arguments);
            return;
        }

        $hostname = (string) ($arguments['endpoint']['hostname'] ?? '');
        $port = (int) ($arguments['endpoint']['port'] ?? 22);
        $sshUsername = (string) ($arguments['endpoint']['ssh_username'] ?? '');
        $authMethod = (string) ($arguments['endpoint']['ssh_auth_method'] ?? 'password');
        $credentialItemId = (int) ($arguments['credential_item_id'] ?? 0);
        $authorId = (int) ($arguments['author'] ?? 0);

        $this->updateTaskStep('connecting');

        // Resolve the SSH secret from the linked TeamPass item via TP_USER chain.
        try {
            $tpKeys = laprGetTpUserPrivateKey($this->settings);
        } catch (Throwable $e) {
            $this->laprTestFail($authorId, $hostname, LAPRSshService::ERR_UNKNOWN, 'cannot_load_server_key');
            return;
        }

        $secret = laprReadItemPasswordAsTpUser($credentialItemId, $tpKeys['private_key'], $tpKeys['public_key']);
        if ($secret === '') {
            $this->laprTestFail($authorId, $hostname, LAPRSshService::ERR_AUTH_FAILED, 'credential_unreadable');
            return;
        }

        $timeout = (int) ($this->settings['lapr_ssh_connect_timeout'] ?? 10);
        $service = new LAPRSshService($timeout);

        $connect = $service->connect($hostname, $port, $sshUsername, $authMethod, $secret);
        if ($connect['success'] !== true) {
            $service->disconnect();
            $errCode = isset($connect['error_code']) ? (string) $connect['error_code'] : LAPRSshService::ERR_UNKNOWN;
            $errDetail = isset($connect['error_detail']) ? (string) $connect['error_detail'] : '';
            $this->laprTestFail($authorId, $hostname, $errCode, $errDetail);
            return;
        }

        $this->updateTaskStep('collecting');
        $collected = $service->testAndCollect();
        $transportFailure = $service->getLastTransportFailure();
        $fingerprint = isset($connect['fingerprint']) ? (string) $connect['fingerprint'] : '';
        $service->disconnect();
        if ($transportFailure !== null) {
            $this->laprTestFail(
                $authorId,
                $hostname,
                $transportFailure['error_code'],
                $transportFailure['error_detail']
            );
            return;
        }

        // D5: can this endpoint actually rotate? Trust the faithful probe run by
        // testAndCollect() — it executes the real (no-op) chpasswd command over
        // the exact path a rotation would take, so this is accurate even when
        // sudo is scoped to chpasswd or /usr/sbin is missing from a non-root PATH.
        $canRotate = (bool) ($collected['capabilities']['can_rotate'] ?? false);

        $result = [
            'success' => true,
            'fingerprint' => $fingerprint,
            'os_info' => $collected['os_info'],
            'capabilities' => $collected['capabilities'],
            'can_rotate' => $canRotate,
        ];
        $this->updateTaskResult($result);
        $this->updateTaskStatus('completed');

        // Audit — no secret, whitelisted fields only.
        laprAuditLog(
            'endpoint_test',
            null,
            $authorId,
            [
                'hostname' => $hostname,
                'port' => $port,
                'os_name' => $collected['os_info']['os_name'] ?? '',
                'can_rotate' => $canRotate,
            ],
            'success',
            null,
            null,
            'system'
        );
    }

    /**
     * Re-check an enrolled endpoint, refresh its operating-system metadata and
     * verify that the SSH identity can still execute the rotation command.
     *
     * @param array $arguments Task arguments
     * @return void
     */
    private function handleLaprEndpointCheck(array $arguments): void
    {
        $endpointId = (int) ($arguments['endpoint_id'] ?? 0);
        $authorId = (int) ($arguments['author'] ?? TP_USER_ID);
        $trigger = (string) ($arguments['trigger'] ?? 'scheduler');
        $resumeOnSuccess = (bool) ($arguments['resume_on_success'] ?? false);
        $endpoint = DB::queryFirstRow(
            'SELECT id, hostname, port, ssh_username, ssh_auth_method, ssh_credential_source, status,
                    ssh_hostkey_fingerprint, ssh_hostkey_verified, os_info
             FROM ' . prefixTable('lapr_endpoints') . '
             WHERE id = %i AND status != %s',
            $endpointId,
            'deleted'
        );
        if ($endpoint === null) {
            $this->updateTaskResult(['success' => false, 'error_code' => 'ERR_ENDPOINT_NOT_FOUND']);
            $this->updateTaskStatus('completed');
            return;
        }

        $hostname = (string) $endpoint['hostname'];
        $this->updateTaskStep('connecting');

        // The allowlist remains authoritative after enrollment. A later policy
        // tightening must prevent both checks and rotations from reaching a host.
        if (laprIsHostnameAllowed($hostname, $this->settings) === false) {
            $this->laprEndpointCheckFail(
                $endpoint,
                $authorId,
                $trigger,
                'ERR_HOSTNAME_NOT_ALLOWED',
                'hostname_not_allowed'
            );
            return;
        }

        try {
            $tpKeys = laprGetTpUserPrivateKey($this->settings);
        } catch (Throwable $e) {
            $this->laprEndpointCheckFail($endpoint, $authorId, $trigger, 'ERR_SERVER_KEY', 'cannot_load_server_key');
            return;
        }

        $secret = laprReadItemPasswordAsTpUser(
            (int) $endpoint['ssh_credential_source'],
            $tpKeys['private_key'],
            $tpKeys['public_key']
        );
        if ($secret === '') {
            $this->laprEndpointCheckFail(
                $endpoint,
                $authorId,
                $trigger,
                LAPRSshService::ERR_AUTH_FAILED,
                'credential_unreadable'
            );
            return;
        }

        $expectedFingerprint = null;
        if ((int) $endpoint['ssh_hostkey_verified'] === 1
            && (string) $endpoint['ssh_hostkey_fingerprint'] !== ''
        ) {
            $expectedFingerprint = (string) $endpoint['ssh_hostkey_fingerprint'];
        }

        $service = new LAPRSshService((int) ($this->settings['lapr_ssh_connect_timeout'] ?? 10));
        $connect = $service->connect(
            $hostname,
            (int) $endpoint['port'],
            (string) $endpoint['ssh_username'],
            (string) $endpoint['ssh_auth_method'],
            $secret,
            $expectedFingerprint
        );
        if ($connect['success'] !== true) {
            $service->disconnect();
            $errorCode = isset($connect['error_code'])
                ? (string) $connect['error_code']
                : LAPRSshService::ERR_UNKNOWN;
            $this->laprEndpointCheckFail(
                $endpoint,
                $authorId,
                $trigger,
                $errorCode,
                (string) ($connect['error_detail'] ?? '')
            );
            return;
        }

        $this->updateTaskStep('collecting');
        $collected = $service->testAndCollect();
        $transportFailure = $service->getLastTransportFailure();
        $service->disconnect();
        if ($transportFailure !== null) {
            $this->laprEndpointCheckFail(
                $endpoint,
                $authorId,
                $trigger,
                $transportFailure['error_code'],
                $transportFailure['error_detail']
            );
            return;
        }

        // Preserve the enrollment-time self-target classification: it is local
        // TeamPass metadata, not information collected from the remote OS.
        $previousOsInfo = json_decode((string) ($endpoint['os_info'] ?? '{}'), true) ?: [];
        $osInfo = $collected['os_info'];
        if (isset($previousOsInfo['lapr_self_target']) === true) {
            $osInfo['lapr_self_target'] = $previousOsInfo['lapr_self_target'];
        }
        $capabilities = $collected['capabilities'];
        $canRotate = (bool) ($capabilities['can_rotate'] ?? false);
        $errorCode = $canRotate ? null : 'ERR_CANNOT_ROTATE';
        $resumed = false;
        $now = date('Y-m-d H:i:s');
        $commonUpdate = [
            'os_info' => json_encode($osInfo, JSON_UNESCAPED_SLASHES),
            'capabilities' => json_encode($capabilities, JSON_UNESCAPED_SLASHES),
            'last_check_at' => $now,
            'last_error' => $errorCode,
            'updated_by' => $authorId,
        ];

        if ($resumeOnSuccess === true) {
            // Resume is deliberately conditional: only the paused state that
            // initiated this health check may be changed to active.
            DB::update(prefixTable('lapr_endpoints'), array_merge($commonUpdate, [
                'status' => $canRotate ? 'active' : 'disabled',
                'next_check_at' => laprComputeNextEndpointCheck($this->settings),
            ]), 'id = %i AND status = %s', $endpointId, 'disabled');
            $resumed = $canRotate && (int) DB::affectedRows() > 0;
        } else {
            // Normal checks may refresh active/error/unreachable endpoints but
            // must never overwrite a pause that happened after the initial read.
            DB::update(prefixTable('lapr_endpoints'), array_merge($commonUpdate, [
                'status' => $canRotate ? 'active' : 'error',
                'next_check_at' => laprComputeNextEndpointCheck($this->settings, null, $errorCode),
            ]), 'id = %i AND status NOT IN %ls', $endpointId, ['disabled', 'deleted']);
            DB::update(prefixTable('lapr_endpoints'), array_merge($commonUpdate, [
                'next_check_at' => laprComputeNextEndpointCheck($this->settings),
            ]), 'id = %i AND status = %s', $endpointId, 'disabled');
        }

        $result = [
            'success' => $canRotate,
            'reachable' => true,
            'error_code' => $errorCode,
            'os_info' => $osInfo,
            'capabilities' => $capabilities,
            'can_rotate' => $canRotate,
            'resumed' => $resumed,
        ];
        $this->updateTaskResult($result);
        $this->updateTaskStatus('completed');

        laprAuditLog(
            'endpoint_test',
            $endpointId,
            $authorId,
            [
                'trigger' => $trigger,
                'hostname' => $hostname,
                'os_name' => (string) ($osInfo['os_name'] ?? ''),
                'can_rotate' => $canRotate,
            ],
            $canRotate ? 'success' : 'failure',
            null,
            $errorCode,
            'system'
        );
        if ($resumeOnSuccess === true) {
            laprAuditLog(
                'endpoint_resume',
                $endpointId,
                $authorId,
                ['trigger' => $trigger, 'can_rotate' => $canRotate],
                $resumed ? 'success' : 'failure',
                null,
                $resumed ? null : ($canRotate ? 'ERR_ENDPOINT_STATE_CHANGED' : 'ERR_CANNOT_ROTATE'),
                'system'
            );
        }
    }

    /**
     * Persist and expose a failed enrolled-endpoint check.
     *
     * @param array  $endpoint  Enrolled endpoint row
     * @param int    $authorId  Acting user
     * @param string $trigger   manual|scheduler
     * @param string $errorCode LAPR error taxonomy code
     * @param string $detail    Secret-free diagnostic detail
     * @return void
     */
    private function laprEndpointCheckFail(
        array $endpoint,
        int $authorId,
        string $trigger,
        string $errorCode,
        string $detail
    ): void {
        $endpointId = (int) $endpoint['id'];
        $commonUpdate = [
            'last_check_at' => date('Y-m-d H:i:s'),
            'last_error' => $errorCode,
            'updated_by' => $authorId,
        ];
        DB::update(prefixTable('lapr_endpoints'), array_merge($commonUpdate, [
            'status' => laprEndpointStatusForCheckFailure($errorCode),
            'next_check_at' => laprComputeNextEndpointCheck($this->settings, null, $errorCode),
        ]), 'id = %i AND status NOT IN %ls', $endpointId, ['disabled', 'deleted']);
        // A deliberate pause keeps normal monitoring cadence. The conditional
        // update also covers a pause racing this in-flight SSH check.
        DB::update(prefixTable('lapr_endpoints'), array_merge($commonUpdate, [
            'next_check_at' => laprComputeNextEndpointCheck($this->settings),
        ]), 'id = %i AND status = %s', $endpointId, 'disabled');

        $this->updateTaskResult([
            'success' => false,
            'reachable' => false,
            'error_code' => $errorCode,
            'error_detail' => substr($detail, 0, 300),
        ]);
        $this->updateTaskStatus('completed');

        laprAuditLog(
            'endpoint_test',
            $endpointId,
            $authorId,
            [
                'trigger' => $trigger,
                'hostname' => (string) $endpoint['hostname'],
                'error_code' => $errorCode,
            ],
            'failure',
            null,
            $errorCode,
            'system'
        );
        if ($trigger === 'resume') {
            laprAuditLog(
                'endpoint_resume',
                $endpointId,
                $authorId,
                ['trigger' => $trigger, 'error_code' => $errorCode],
                'failure',
                null,
                $errorCode,
                'system'
            );
        }
    }

    /**
     * Record a failed SSH test into the task output and the audit log.
     *
     * @param int    $authorId  User who launched the test
     * @param string $hostname  Target hostname
     * @param string $errorCode LAPR error taxonomy code
     * @param string $detail    Short, secret-free detail
     * @return void
     */
    private function laprTestFail(int $authorId, string $hostname, string $errorCode, string $detail): void
    {
        $this->updateTaskResult([
            'success' => false,
            'error_code' => $errorCode,
            'error_detail' => substr($detail, 0, 300),
        ]);
        $this->updateTaskStatus('completed');

        laprAuditLog(
            'endpoint_test',
            null,
            $authorId,
            ['hostname' => $hostname, 'error_code' => $errorCode],
            'failure',
            null,
            $errorCode,
            'system'
        );
    }
}
