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
        $fingerprint = isset($connect['fingerprint']) ? (string) $connect['fingerprint'] : '';
        $service->disconnect();

        // D5: can this endpoint actually rotate? root, or non-root with passwordless sudo.
        $isRoot = (bool) ($collected['os_info']['is_root'] ?? false);
        $hasSudo = (bool) ($collected['capabilities']['has_sudo'] ?? false);
        $hasChpasswd = (bool) ($collected['capabilities']['has_chpasswd'] ?? false);
        $canRotate = $hasChpasswd && ($isRoot || $hasSudo);

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
