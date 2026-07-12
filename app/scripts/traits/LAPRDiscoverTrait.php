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
 * @file      LAPRDiscoverTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Lapr\LAPRSshService;

require_once __DIR__ . '/../../includes/libraries/teampassclasses/lapr/src/LAPRSshService.php';
require_once __DIR__ . '/../../sources/lapr.functions.php';

/**
 * Background local-account discovery for a LAPR endpoint (Point 2). Scans the
 * endpoint with `getent passwd` and returns candidate accounts (login shells,
 * uid >= 1000 or root) the operator can add as managed accounts. Read-only —
 * never changes anything on the endpoint.
 */
trait LAPRDiscoverTrait
{
    /**
     * Entry point for the 'lapr_discover' process type.
     *
     * Task arguments: { endpoint_id, author }
     *
     * @param array $arguments Task arguments
     * @return void
     */
    private function handleLaprDiscover(array $arguments): void
    {
        $endpointId = (int) ($arguments['endpoint_id'] ?? 0);
        $authorId = (int) ($arguments['author'] ?? 0);

        $this->updateTaskStep('connecting');

        $endpoint = DB::queryFirstRow(
            'SELECT id, hostname, port, ssh_username, ssh_auth_method, ssh_credential_source
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

        try {
            $tpKeys = laprGetTpUserPrivateKey($this->settings);
        } catch (Throwable $e) {
            $this->updateTaskResult(['success' => false, 'error_code' => LAPRSshService::ERR_UNKNOWN]);
            $this->updateTaskStatus('completed');
            return;
        }

        $secret = laprReadItemPasswordAsTpUser(
            (int) $endpoint['ssh_credential_source'],
            $tpKeys['private_key'],
            $tpKeys['public_key']
        );
        if ($secret === '') {
            $this->updateTaskResult(['success' => false, 'error_code' => LAPRSshService::ERR_AUTH_FAILED]);
            $this->updateTaskStatus('completed');
            return;
        }

        $timeout = (int) ($this->settings['lapr_ssh_connect_timeout'] ?? 10);
        $service = new LAPRSshService($timeout);
        $connect = $service->connect(
            (string) $endpoint['hostname'],
            (int) $endpoint['port'],
            (string) $endpoint['ssh_username'],
            (string) $endpoint['ssh_auth_method'],
            $secret
        );
        if ($connect['success'] !== true) {
            $service->disconnect();
            $this->updateTaskResult([
                'success' => false,
                'error_code' => isset($connect['error_code']) ? (string) $connect['error_code'] : LAPRSshService::ERR_UNKNOWN,
            ]);
            $this->updateTaskStatus('completed');
            return;
        }

        $this->updateTaskStep('scanning');
        $accounts = $service->listLocalAccounts();
        $service->disconnect();

        // Keep real login accounts (a shell other than nologin/false), and
        // usernames that pass the POSIX validation used for rotation (R1).
        $candidates = [];
        foreach ($accounts as $acct) {
            $shell = (string) $acct['shell'];
            $username = (string) $acct['username'];
            $isLoginShell = $shell !== '' && strpos($shell, 'nologin') === false && strpos($shell, '/false') === false;
            if ($isLoginShell === true && laprValidateUsername($username) === true) {
                $candidates[] = [
                    'username' => $username,
                    'uid' => (int) $acct['uid'],
                    'shell' => $shell,
                ];
            }
        }

        $this->updateTaskResult(['success' => true, 'accounts' => $candidates]);
        $this->updateTaskStatus('completed');

        laprAuditLog(
            'endpoint_test',
            $endpointId,
            $authorId,
            ['discovered_count' => count($candidates)],
            'success',
            null,
            null,
            'system'
        );
    }
}
