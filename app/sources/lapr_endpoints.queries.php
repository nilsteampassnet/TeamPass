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
 * @file      lapr_endpoints.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
require_once 'lapr.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('lapr_endpoints') === false) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// LAPR module gate (module enabled + admin-or-flag)
if (laprCheckPermission($session, $SETTINGS) === false) {
    echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_not_allowed')], 'encode');
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

if ($post_key !== $session->get('key')) {
    echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
    exit;
}

$dataReceived = empty($post_data) === false ? prepareExchangedData($post_data, 'decode') : [];
$userId = (int) $session->get('user-id');

switch ($post_type) {
    case 'list_endpoints':
        laprListEndpoints($lang);
        break;
    case 'search_credential_items':
        laprSearchCredentialItems($dataReceived, $session, $lang);
        break;
    case 'start_test':
        laprStartTest($dataReceived, $session, $SETTINGS, $userId, $lang);
        break;
    case 'test_status':
        laprTestStatus($dataReceived, $lang);
        break;
    case 'add_endpoint':
        laprAddEndpoint($dataReceived, $userId, $SETTINGS, $lang);
        break;
    case 'delete_endpoint':
        laprDeleteEndpoint($dataReceived, $userId, $lang);
        break;
    case 'restore_endpoint':
        laprRestoreEndpoint($dataReceived, $userId, $lang);
        break;
    case 'trust_hostkey':
        laprTrustHostkey($dataReceived, $userId, $lang);
        break;
    default:
        echo prepareExchangedData(['error' => true, 'message' => 'Unknown action'], 'encode');
}

/**
 * List all non-deleted endpoints for the DataTable.
 *
 * @param Language $lang Language helper
 * @return void
 */
function laprListEndpoints(Language $lang): void
{
    $rows = DB::query(
        'SELECT id, label, hostname, port, ssh_username, ssh_auth_method, status,
                ssh_hostkey_verified, last_check_at, last_error, capabilities, os_info
         FROM ' . prefixTable('lapr_endpoints') . '
         WHERE status != %s
         ORDER BY label ASC',
        'deleted'
    );

    $data = [];
    foreach ($rows as $r) {
        $caps = json_decode((string) ($r['capabilities'] ?? '{}'), true) ?: [];
        $os = json_decode((string) ($r['os_info'] ?? '{}'), true) ?: [];
        $data[] = [
            'id' => (int) $r['id'],
            'label' => $r['label'],
            'hostname' => $r['hostname'],
            'port' => (int) $r['port'],
            'ssh_username' => $r['ssh_username'],
            'ssh_auth_method' => $r['ssh_auth_method'],
            'status' => $r['status'],
            'hostkey_verified' => (int) $r['ssh_hostkey_verified'],
            'last_check_at' => $r['last_check_at'],
            'os_name' => $os['os_name'] ?? '',
            'has_sudo' => (bool) ($caps['has_sudo'] ?? false),
            'is_root' => (bool) ($os['is_root'] ?? false),
        ];
    }

    echo prepareExchangedData(['error' => false, 'data' => $data], 'encode');
}

/**
 * Search candidate SSH credential items: accessible, non-personal, active items.
 * TP_USER must hold a sharekey, which is guaranteed for non-personal items only.
 *
 * Feeds the select2 picker of the enroll modal: the search runs server-side and
 * returns a bounded page of results, so the vault size does not matter. Matching
 * covers label and login, since an SSH credential is often identified by its
 * account name (root, ansible, deploy) rather than by its label.
 *
 * @param array    $data    Decoded client payload ('term')
 * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
 * @param Language $lang    Language helper
 * @return void
 */
function laprSearchCredentialItems(array $data, $session, Language $lang): void
{
    // Escape the LIKE wildcards so a '%' typed by the user stays a literal.
    $term = trim((string) ($data['term'] ?? ''));
    $likeTerm = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

    $accessible = array_map('intval', (array) ($session->get('user-accessible_folders') ?? []));
    $isAdmin = (int) $session->get('user-admin') === 1;

    if ($isAdmin === false && count($accessible) === 0) {
        echo prepareExchangedData(['error' => false, 'results' => []], 'encode');
        return;
    }

    // Folder scope: admins see every non-personal item, others only their own scope.
    $scopeClause = $isAdmin === true ? '' : ' AND i.id_tree IN %li ';
    $sql = 'SELECT i.id, i.label, i.login, i.id_tree
            FROM ' . prefixTable('items') . ' AS i
            WHERE i.perso = 0 AND i.inactif = 0 AND i.deleted_at IS NULL
            AND (i.label LIKE %s OR i.login LIKE %s)'
        . $scopeClause .
           'ORDER BY i.label ASC LIMIT 20';

    $rows = $isAdmin === true
        ? DB::query($sql, $likeTerm, $likeTerm)
        : DB::query($sql, $likeTerm, $likeTerm, $accessible);

    // Same label can exist in several folders: show the path to disambiguate.
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $pathCache = [];

    $results = [];
    foreach ($rows as $r) {
        $folderId = (int) $r['id_tree'];
        if (isset($pathCache[$folderId]) === false) {
            $segments = [];
            foreach ($tree->getPath($folderId, true) as $node) {
                $segments[] = (string) $node->title;
            }
            $pathCache[$folderId] = implode(' / ', $segments);
        }

        $results[] = [
            'id' => (int) $r['id'],
            // Raw values: the client escapes them at render time.
            'text' => (string) $r['label'],
            'login' => (string) $r['login'],
            'path' => $pathCache[$folderId],
        ];
    }

    echo prepareExchangedData(['error' => false, 'results' => $results], 'encode');
}

/**
 * Start a background SSH test. Applies rate limiting (IP + hostname) and the
 * optional allowlist, then enqueues a 'lapr_ssh_test' task the modal polls.
 *
 * @param array    $data     Decoded client payload
 * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
 * @param array    $SETTINGS TeamPass settings
 * @param int      $userId   Acting user id
 * @param Language $lang     Language helper
 * @return void
 */
function laprStartTest(array $data, $session, array $SETTINGS, int $userId, Language $lang): void
{
    $hostname = trim((string) ($data['hostname'] ?? ''));
    $port = (int) ($data['port'] ?? 22);
    $sshUsername = trim((string) ($data['ssh_username'] ?? ''));
    $authMethod = ((string) ($data['ssh_auth_method'] ?? 'password')) === 'key' ? 'key' : 'password';
    $credentialItemId = (int) ($data['credential_item_id'] ?? 0);

    if ($hostname === '' || $sshUsername === '' || $credentialItemId <= 0 || $port <= 0 || $port > 65535) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_empty_data')], 'encode');
        return;
    }

    // Allowlist (SSRF primary control, R3)
    if (laprIsHostnameAllowed($hostname, $SETTINGS) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_hostname_not_allowed')], 'encode');
        return;
    }

    // Rate limiting (IP + hostname)
    $ip = getClientIpServer();
    $rl = laprCheckRateLimit($ip, $hostname, $SETTINGS);
    if ($rl['allowed'] === false) {
        echo prepareExchangedData([
            'error' => true,
            'message' => $lang->get('lapr_rate_limited'),
            'retry_after' => $rl['retry_after'],
        ], 'encode');
        return;
    }

    // The credential item must be accessible to the user (folder scope) and non-personal.
    $item = DB::queryFirstRow(
        'SELECT id, id_tree, perso FROM ' . prefixTable('items') . '
         WHERE id = %i AND inactif = 0 AND deleted_at IS NULL',
        $credentialItemId
    );
    if ($item === null || (int) $item['perso'] === 1
        || laprUserCanReadFolder((int) $item['id_tree'], $session) === false
    ) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        return;
    }

    $arguments = [
        'endpoint' => [
            'hostname' => $hostname,
            'port' => $port,
            'ssh_username' => $sshUsername,
            'ssh_auth_method' => $authMethod,
        ],
        'credential_item_id' => $credentialItemId,
        'author' => $userId,
    ];

    DB::insert(prefixTable('background_tasks'), [
        'created_at' => (string) time(),
        'process_type' => 'lapr_ssh_test',
        'arguments' => json_encode($arguments, JSON_UNESCAPED_SLASHES),
        'is_in_progress' => 0,
        'status' => 'new',
    ]);
    $taskId = (int) DB::insertId();

    triggerBackgroundHandler();

    echo prepareExchangedData(['error' => false, 'task_id' => $taskId], 'encode');
}

/**
 * Poll a running SSH test task. On success, returns an HMAC-signed snapshot the
 * client sends back at save time (test→save integrity).
 *
 * @param array    $data Decoded client payload
 * @param Language $lang Language helper
 * @return void
 */
function laprTestStatus(array $data, Language $lang): void
{
    $taskId = (int) ($data['task_id'] ?? 0);
    if ($taskId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => 'Invalid task'], 'encode');
        return;
    }

    $task = DB::queryFirstRow(
        'SELECT increment_id, process_type, status, output, arguments, is_in_progress
         FROM ' . prefixTable('background_tasks') . '
         WHERE increment_id = %i AND process_type = %s',
        $taskId,
        'lapr_ssh_test'
    );
    if ($task === null) {
        echo prepareExchangedData(['error' => true, 'message' => 'Task not found'], 'encode');
        return;
    }

    $output = json_decode((string) ($task['output'] ?? '{}'), true) ?: [];
    // The connection parameters that were actually tested (and allowlist-checked in
    // start_test) live in the server-stored task arguments — NOT in any client field.
    // Binding them into the signed snapshot is what prevents an allowlist bypass at
    // save time (a valid snapshot from an allowed host cannot be replayed to store a
    // different, non-allowlisted hostname).
    $taskArgs = json_decode((string) ($task['arguments'] ?? '{}'), true) ?: [];
    $testedEndpoint = is_array($taskArgs['endpoint'] ?? null) ? $taskArgs['endpoint'] : [];
    $finished = (int) $task['is_in_progress'] === -1 || (string) $task['status'] === 'completed' || (string) $task['status'] === 'failed';

    if ($finished === false) {
        echo prepareExchangedData([
            'error' => false,
            'finished' => false,
            'step' => $output['step'] ?? 'connecting',
        ], 'encode');
        return;
    }

    if (($output['success'] ?? false) !== true) {
        echo prepareExchangedData([
            'error' => false,
            'finished' => true,
            'success' => false,
            'error_code' => $output['error_code'] ?? 'ERR_UNKNOWN',
        ], 'encode');
        return;
    }

    // Sign a snapshot of the tested connection so the save step cannot be tampered.
    // The connection parameters are the authoritative, allowlist-checked values from
    // the task arguments; laprAddEndpoint derives the stored endpoint from these.
    $snapshot = [
        'task_id' => $taskId,
        'hostname' => (string) ($testedEndpoint['hostname'] ?? ''),
        'port' => (int) ($testedEndpoint['port'] ?? 22),
        'ssh_username' => (string) ($testedEndpoint['ssh_username'] ?? ''),
        'ssh_auth_method' => (string) ($testedEndpoint['ssh_auth_method'] ?? 'password'),
        'credential_item_id' => (int) ($taskArgs['credential_item_id'] ?? 0),
        'fingerprint' => (string) ($output['fingerprint'] ?? ''),
        'os_info' => $output['os_info'] ?? [],
        'capabilities' => $output['capabilities'] ?? [],
        'can_rotate' => (bool) ($output['can_rotate'] ?? false),
    ];
    $signed = laprSignSnapshot($snapshot);

    echo prepareExchangedData([
        'error' => false,
        'finished' => true,
        'success' => true,
        'can_rotate' => (bool) ($output['can_rotate'] ?? false),
        'fingerprint' => (string) ($output['fingerprint'] ?? ''),
        'os_info' => $output['os_info'] ?? [],
        'capabilities' => $output['capabilities'] ?? [],
        'snapshot' => $signed['payload'],
        'snapshot_sig' => $signed['sig'],
    ], 'encode');
}

/**
 * Persist an endpoint after a verified SSH test (snapshot HMAC + save form).
 *
 * The connection parameters (hostname/port/user/auth/credential) are taken from
 * the HMAC-signed snapshot — the values that were actually tested AND
 * allowlist-checked in start_test — never from separate client fields. This is
 * what stops a valid snapshot from an allowed host being replayed to store a
 * different, non-allowlisted hostname (SSRF / allowlist bypass). Only the label
 * and the host-key-skip choice are honoured from the save form.
 *
 * @param array    $data     Decoded client payload
 * @param int      $userId   Acting user id
 * @param array    $SETTINGS TeamPass settings (allowlist re-check)
 * @param Language $lang     Language helper
 * @return void
 */
function laprAddEndpoint(array $data, int $userId, array $SETTINGS, Language $lang): void
{
    $label = trim((string) ($data['label'] ?? ''));
    $skipHostkey = (int) ($data['skip_hostkey_verification'] ?? 0) === 1 ? 1 : 0;
    $snapshot = (array) ($data['snapshot'] ?? []);
    $snapshotSig = (string) ($data['snapshot_sig'] ?? '');

    // Verify the test snapshot (test→save integrity) BEFORE trusting any of its fields.
    if ($snapshotSig === '' || laprVerifySnapshot($snapshot, $snapshotSig) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_test_required_before_save')], 'encode');
        return;
    }

    // Connection params are authoritative from the signed snapshot, not the client form.
    $hostname = trim((string) ($snapshot['hostname'] ?? ''));
    $port = (int) ($snapshot['port'] ?? 22);
    $sshUsername = trim((string) ($snapshot['ssh_username'] ?? ''));
    $authMethod = ((string) ($snapshot['ssh_auth_method'] ?? 'password')) === 'key' ? 'key' : 'password';
    $credentialItemId = (int) ($snapshot['credential_item_id'] ?? 0);

    if ($label === '' || $hostname === '' || $sshUsername === '' || $credentialItemId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_empty_data')], 'encode');
        return;
    }

    // Defence in depth: re-check the allowlist against the (snapshot) hostname, in case
    // the allowlist changed between test and save, or the snapshot predates a tightening.
    if (laprIsHostnameAllowed($hostname, $SETTINGS) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_hostname_not_allowed')], 'encode');
        return;
    }

    // D5: refuse enrollment when the endpoint cannot rotate, unless host-key
    // verification is deliberately skipped (lab escape hatch still requires rotate capability).
    if ((bool) ($snapshot['can_rotate'] ?? false) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_endpoint_cannot_rotate')], 'encode');
        return;
    }

    $fingerprint = (string) ($snapshot['fingerprint'] ?? '');
    $osInfo = json_encode($snapshot['os_info'] ?? [], JSON_UNESCAPED_SLASHES);
    $capabilities = json_encode($snapshot['capabilities'] ?? [], JSON_UNESCAPED_SLASHES);

    DB::insert(prefixTable('lapr_endpoints'), [
        'label' => $label,
        'hostname' => $hostname,
        'port' => $port,
        'ssh_username' => $sshUsername,
        'ssh_auth_method' => $authMethod,
        'ssh_credential_source' => $credentialItemId,
        'os_info' => $osInfo,
        'capabilities' => $capabilities,
        'ssh_hostkey_fingerprint' => $fingerprint,
        'ssh_hostkey_verified' => $skipHostkey === 1 ? 0 : 1,
        'status' => 'active',
        'last_check_at' => date('Y-m-d H:i:s'),
        'created_by' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $endpointId = (int) DB::insertId();

    laprAuditLog('endpoint_add', $endpointId, $userId, [
        'label' => $label,
        'hostname' => $hostname,
        'port' => $port,
        'hostkey_verified' => $skipHostkey === 1 ? 0 : 1,
    ], 'success', null);

    echo prepareExchangedData([
        'error' => false,
        'message' => $lang->get('lapr_endpoint_saved'),
        'id' => $endpointId,
    ], 'encode');
}

/**
 * Soft-delete an endpoint and pause its managed accounts.
 *
 * @param array    $data   Decoded client payload
 * @param int      $userId Acting user id
 * @param Language $lang   Language helper
 * @return void
 */
function laprDeleteEndpoint(array $data, int $userId, Language $lang): void
{
    $endpointId = (int) ($data['id'] ?? 0);
    if ($endpointId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => 'Invalid endpoint'], 'encode');
        return;
    }

    $exists = DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('lapr_endpoints') . ' WHERE id = %i AND status != %s',
        $endpointId,
        'deleted'
    );
    if ((int) $exists === 0) {
        echo prepareExchangedData(['error' => true, 'message' => 'Endpoint not found'], 'encode');
        return;
    }

    DB::update(prefixTable('lapr_endpoints'), [
        'status' => 'deleted',
        'updated_by' => $userId,
    ], 'id = %i', $endpointId);

    // Pause managed accounts on this endpoint (no cascade — soft-delete/pause, C6)
    DB::update(prefixTable('lapr_accounts'), [
        'status' => 'paused',
        'updated_by' => $userId,
    ], 'endpoint_id = %i AND status = %s', $endpointId, 'active');

    laprAuditLog('endpoint_delete', $endpointId, $userId, [], 'success');

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_endpoint_deleted')], 'encode');
}

/**
 * Restore a soft-deleted endpoint (accounts stay paused until manually resumed).
 *
 * @param array    $data   Decoded client payload
 * @param int      $userId Acting user id
 * @param Language $lang   Language helper
 * @return void
 */
function laprRestoreEndpoint(array $data, int $userId, Language $lang): void
{
    $endpointId = (int) ($data['id'] ?? 0);
    if ($endpointId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => 'Invalid endpoint'], 'encode');
        return;
    }

    DB::update(prefixTable('lapr_endpoints'), [
        'status' => 'active',
        'updated_by' => $userId,
    ], 'id = %i AND status = %s', $endpointId, 'deleted');

    laprAuditLog('endpoint_edit', $endpointId, $userId, ['restored' => true], 'success');

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_endpoint_restored')], 'encode');
}

/**
 * Trust a new host key after a mismatch (re-TOFU). Requires a verified test
 * snapshot carrying the new fingerprint.
 *
 * @param array    $data   Decoded client payload
 * @param int      $userId Acting user id
 * @param Language $lang   Language helper
 * @return void
 */
function laprTrustHostkey(array $data, int $userId, Language $lang): void
{
    $endpointId = (int) ($data['id'] ?? 0);
    $snapshot = (array) ($data['snapshot'] ?? []);
    $snapshotSig = (string) ($data['snapshot_sig'] ?? '');

    if ($endpointId <= 0 || $snapshotSig === '' || laprVerifySnapshot($snapshot, $snapshotSig) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_test_required_before_save')], 'encode');
        return;
    }

    // Bind the verified snapshot to THIS endpoint: the re-TOFU test must have been
    // run against the same host/port/user/credential we are about to trust. This
    // stops a fingerprint obtained from a different (attacker-controlled) host being
    // trusted onto an existing endpoint.
    $endpoint = DB::queryFirstRow(
        'SELECT hostname, port, ssh_username, ssh_credential_source
         FROM ' . prefixTable('lapr_endpoints') . ' WHERE id = %i AND status != %s',
        $endpointId,
        'deleted'
    );
    if ($endpoint === null
        || (string) $endpoint['hostname'] !== (string) ($snapshot['hostname'] ?? '')
        || (int) $endpoint['port'] !== (int) ($snapshot['port'] ?? 0)
        || (string) $endpoint['ssh_username'] !== (string) ($snapshot['ssh_username'] ?? '')
        || (int) $endpoint['ssh_credential_source'] !== (int) ($snapshot['credential_item_id'] ?? 0)
    ) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_test_required_before_save')], 'encode');
        return;
    }

    DB::update(prefixTable('lapr_endpoints'), [
        'ssh_hostkey_fingerprint' => (string) ($snapshot['fingerprint'] ?? ''),
        'ssh_hostkey_verified' => 1,
        'status' => 'active',
        'updated_by' => $userId,
    ], 'id = %i', $endpointId);

    laprAuditLog('endpoint_edit', $endpointId, $userId, ['hostkey_trusted' => true], 'success');

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_hostkey_updated')], 'encode');
}
