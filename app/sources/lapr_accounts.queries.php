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
 * @file      lapr_accounts.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
        ['type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8')],
        ['type' => 'trim|escape'],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('lapr_accounts') === false) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// LAPR module gate
if (laprCheckPermission($session, $SETTINGS) === false) {
    echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_not_allowed')], 'encode');
    exit;
}

date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');
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
    case 'list_accounts':
        laprListAccounts($lang, $SETTINGS);
        break;
    case 'list_endpoints_options':
        laprListEndpointsOptions();
        break;
    case 'list_manageable_items':
        laprListManageableItems($session, $dataReceived);
        break;
    case 'search_manageable_items':
        laprSearchManageableItems($session, $dataReceived);
        break;
    case 'list_policies_options':
        laprListPoliciesOptions($lang);
        break;
    case 'add_account':
        laprAddAccount($dataReceived, $session, $userId, $lang, $SETTINGS);
        break;
    case 'delete_account':
        laprDeleteAccount($dataReceived, $userId, $lang);
        break;
    case 'update_account_policy':
        laprUpdateAccountPolicy($dataReceived, $userId, $lang);
        break;
    case 'start_discover':
        laprStartDiscover($dataReceived, $userId, $lang);
        break;
    case 'discover_status':
        laprDiscoverStatus($dataReceived, $lang);
        break;
    case 'start_rotation':
        laprStartRotation($dataReceived, $session, $userId, $lang);
        break;
    case 'rotation_status':
        laprRotationStatus($dataReceived, $lang);
        break;
    case 'reset_account':
        laprResetAccount($dataReceived, $session, $userId, $lang);
        break;
    case 'list_account_history':
        laprListAccountHistory($dataReceived, $session, $lang, $SETTINGS);
        break;
    default:
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_unknown_action')], 'encode');
}

/**
 * List managed accounts with endpoint + item context for the DataTable.
 *
 * @param Language             $lang     Language helper
 * @param array<string, mixed> $settings TeamPass regional settings
 * @return void
 */
function laprListAccounts(Language $lang, array $settings): void
{
    $rows = DB::query(
        'SELECT a.id, a.username_cache, a.status, a.last_rotation_at, a.last_rotation_status,
                a.next_rotation_at, a.policy_id, a.item_id,
                e.label AS ep_label, e.hostname, e.os_info, e.status AS ep_status,
                p.label AS policy_label, p.frequency_days AS policy_frequency_days,
                p.is_preset AS policy_is_preset
         FROM ' . prefixTable('lapr_accounts') . ' AS a
         INNER JOIN ' . prefixTable('lapr_endpoints') . ' AS e ON e.id = a.endpoint_id
         LEFT JOIN ' . prefixTable('lapr_policies') . ' AS p ON p.id = a.policy_id
         WHERE a.status != %s
         ORDER BY e.label ASC, a.username_cache ASC',
        'deleted'
    );

    $data = [];
    foreach ($rows as $r) {
        $lastRotationAt = laprFormatDateTimeForDisplay(
            $r['last_rotation_at'] === null ? null : (string) $r['last_rotation_at'],
            $settings
        );
        $nextRotationAt = laprFormatDateTimeForDisplay(
            $r['next_rotation_at'] === null ? null : (string) $r['next_rotation_at'],
            $settings
        );
        $policyLabel = '';
        if ($r['policy_label'] !== null) {
            $policyLabel = laprPolicyDisplayName(
                (string) $r['policy_label'],
                (int) $r['policy_is_preset'] === 1,
                $lang
            );
            if ((int) $r['policy_is_preset'] === 1) {
                $policyLabel = laprPolicyOptionLabel(
                    $policyLabel,
                    (int) $r['policy_frequency_days'],
                    $lang
                );
            }
        }
        $osInfo = json_decode((string) ($r['os_info'] ?? ''), true) ?: [];
        $storedSelfTarget = (bool) ($osInfo['lapr_self_target']['is_self'] ?? false);
        $runtimeSelfTarget = laprClassifySelfTarget((string) $r['hostname'], $settings);

        $data[] = [
            'id' => (int) $r['id'],
            'username' => $r['username_cache'],
            'item_id' => (int) $r['item_id'],
            'endpoint' => $r['ep_label'] . ' (' . $r['hostname'] . ')',
            'policy' => $policyLabel,
            'policy_id' => $r['policy_id'] !== null ? (int) $r['policy_id'] : 0,
            'status' => $r['status'],
            'last_rotation_at' => $lastRotationAt['display'],
            'last_rotation_at_ts' => $lastRotationAt['timestamp'],
            'last_rotation_status' => $r['last_rotation_status'],
            'next_rotation_at' => $nextRotationAt['display'],
            'next_rotation_at_ts' => $nextRotationAt['timestamp'],
            'is_self_target' => $storedSelfTarget || $runtimeSelfTarget['is_self'],
        ];
    }

    echo prepareExchangedData(['error' => false, 'data' => $data], 'encode');
}

/**
 * List active endpoints as {id,label} for the add-account form.
 *
 * @return void
 */
function laprListEndpointsOptions(): void
{
    $rows = DB::query(
        'SELECT id, label, hostname FROM ' . prefixTable('lapr_endpoints') . '
         WHERE status = %s ORDER BY label ASC',
        'active'
    );
    $data = [];
    foreach ($rows as $r) {
        $data[] = ['id' => (int) $r['id'], 'label' => $r['label'] . ' (' . $r['hostname'] . ')'];
    }
    echo prepareExchangedData(['error' => false, 'data' => $data], 'encode');
}

/**
 * List rotation policies as {id,label,frequency_days} for the account forms.
 *
 * @param Language $lang Language helper
 * @return void
 */
function laprListPoliciesOptions(Language $lang): void
{
    $rows = DB::query(
        'SELECT id, label, frequency_days, is_preset FROM ' . prefixTable('lapr_policies') . '
         ORDER BY is_preset DESC, label ASC'
    );
    $data = [];
    foreach ($rows as $r) {
        $displayName = laprPolicyDisplayName(
            (string) $r['label'],
            (int) $r['is_preset'] === 1,
            $lang
        );
        $data[] = [
            'id' => (int) $r['id'],
            'label' => laprPolicyOptionLabel($displayName, (int) $r['frequency_days'], $lang),
        ];
    }
    echo prepareExchangedData(['error' => false, 'data' => $data], 'encode');
}

/**
 * List candidate items to manage: writable, non-personal, active, with a
 * non-empty login, not already managed. Folder scope always applies.
 *
 * @param SessionInterface $session Current session
 * @param array            $data    Optional exact login filter used by discovery
 * @return void
 */
function laprListManageableItems(SessionInterface $session, array $data = []): void
{
    $rows = laprFindManageableItems($session, $data, 500);

    $items = [];
    foreach ($rows as $r) {
        $items[] = ['id' => (int) $r['id'], 'label' => $r['label'], 'login' => $r['login']];
    }
    echo prepareExchangedData(['error' => false, 'data' => $items], 'encode');
}

/**
 * Search eligible managed-account items for the Select2 picker.
 *
 * @param SessionInterface $session Current session
 * @param array            $data    Search term and optional exact login filter
 * @return void
 */
function laprSearchManageableItems(SessionInterface $session, array $data): void
{
    $rows = laprFindManageableItems($session, $data, 20);
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $pathCache = [];
    $results = [];

    foreach ($rows as $row) {
        $folderId = (int) $row['id_tree'];
        if (isset($pathCache[$folderId]) === false) {
            $segments = [];
            foreach ($tree->getPath($folderId, true) as $node) {
                $segments[] = (string) $node->title;
            }
            $pathCache[$folderId] = implode(' / ', $segments);
        }

        $results[] = [
            'id' => (int) $row['id'],
            'text' => (string) $row['label'],
            'login' => (string) $row['login'],
            'path' => $pathCache[$folderId],
        ];
    }

    echo prepareExchangedData(['error' => false, 'results' => $results], 'encode');
}

/**
 * Return TeamPass items that are eligible to become managed LAPR accounts.
 *
 * @param SessionInterface $session Current session
 * @param array            $data    Search term and optional exact login filter
 * @param int              $limit   Maximum number of returned rows
 * @return array<int, array<string, mixed>>
 */
function laprFindManageableItems(SessionInterface $session, array $data, int $limit): array
{
    $accessible = array_map('intval', (array) ($session->get('user-accessible_folders') ?? []));
    $readOnly = array_map('intval', (array) ($session->get('user-read_only_folders') ?? []));
    $writable = array_values(array_diff($accessible, $readOnly));
    if ((int) $session->get('user-admin') === 1 || count($writable) === 0) {
        return [];
    }

    $login = substr(trim((string) ($data['login'] ?? '')), 0, 100);
    $term = substr(trim((string) ($data['term'] ?? '')), 0, 100);
    $likeTerm = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    $safeLimit = max(1, min($limit, 500));
    $loginClause = $login === '' ? '' : ' AND i.login = %s_login';
    $queryParameters = [
        'folders' => $writable,
        'term_label' => $likeTerm,
        'term_login' => $likeTerm,
        'limit' => $safeLimit,
    ];
    if ($login !== '') {
        $queryParameters['login'] = $login;
    }

    return DB::query(
        'SELECT i.id, i.label, i.login, i.id_tree
         FROM ' . prefixTable('items') . ' AS i
         WHERE i.perso = 0 AND i.inactif = 0 AND i.deleted_at IS NULL
         AND i.login IS NOT NULL AND i.login != ""
         AND (i.label LIKE %s_term_label OR i.login LIKE %s_term_login)
         AND i.id NOT IN (
            SELECT item_id FROM ' . prefixTable('lapr_accounts') . ' WHERE status != "deleted"
         ) AND i.id_tree IN %li_folders' . $loginClause . '
         ORDER BY i.label ASC LIMIT %i_limit',
        $queryParameters
    );
}

/**
 * Add a managed account. Validates the item (accessible, writable folder,
 * non-personal, valid Linux username), computes the first next_rotation_at,
 * and queues an enrollment rotation when requested by the policy.
 *
 * @param array               $data     Decoded client payload
 * @param SessionInterface    $session  Current session
 * @param int                 $userId   Acting user id
 * @param Language            $lang     Language helper
 * @param array<string,mixed> $settings TeamPass settings used for self-target detection
 * @return void
 */
function laprAddAccount(
    array $data,
    SessionInterface $session,
    int $userId,
    Language $lang,
    array $settings
): void
{
    $endpointId = (int) ($data['endpoint_id'] ?? 0);
    $itemId = (int) ($data['item_id'] ?? 0);
    $policyId = (int) ($data['policy_id'] ?? 0);

    if ($endpointId <= 0 || $itemId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_empty_data')], 'encode');
        return;
    }

    $endpoint = DB::queryFirstRow(
        'SELECT id, hostname, os_info, ssh_username, ssh_auth_method, ssh_credential_source
         FROM ' . prefixTable('lapr_endpoints') . ' WHERE id = %i AND status = %s',
        $endpointId,
        'active'
    );
    if ($endpoint === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        return;
    }

    $item = DB::queryFirstRow(
        'SELECT id, login, id_tree, perso FROM ' . prefixTable('items') . '
         WHERE id = %i AND inactif = 0 AND deleted_at IS NULL',
        $itemId
    );
    if ($item === null || (int) $item['perso'] === 1) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        return;
    }

    // Rotation writes the item → require write access to its folder.
    if (laprUserCanWriteFolder((int) $item['id_tree'], $session) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        return;
    }

    $login = trim((string) ($item['login'] ?? ''));
    if ($login === '') {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_item_no_login')], 'encode');
        return;
    }
    // R1: the login becomes username_cache and is interpolated into a remote command later.
    if (laprValidateUsername($login) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_username')], 'encode');
        return;
    }

    // The unique item_id remains occupied after a soft deletion. Reuse that row
    // instead of inserting a duplicate and losing the relationship history.
    $existing = DB::queryFirstRow(
        'SELECT id, status FROM ' . prefixTable('lapr_accounts') . ' WHERE item_id = %i',
        $itemId
    );
    if ($existing !== null && (string) $existing['status'] !== 'deleted') {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_item_already_managed')], 'encode');
        return;
    }

    if (laprRemoteAccountAlreadyManaged($endpointId, $login, $existing === null ? 0 : (int) $existing['id']) === true) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_remote_account_already_managed')], 'encode');
        return;
    }

    $credentialConflict = laprManagedItemCredentialConflict($itemId, $endpointId, $login);
    if ($credentialConflict === 'key_credential') {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_key_credential_cannot_be_managed')], 'encode');
        return;
    }
    if ($credentialConflict === 'password_credential') {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_credential_item_wrong_managed_account')], 'encode');
        return;
    }

    // Resolve frequency for the first next_rotation_at.
    $frequencyDays = LAPR_DEFAULT_FREQUENCY_DAYS;
    $rotateOnEnroll = false;
    if ($policyId > 0) {
        $policy = DB::queryFirstRow(
            'SELECT frequency_days, rotate_on_enroll FROM ' . prefixTable('lapr_policies') . ' WHERE id = %i',
            $policyId
        );
        if ($policy !== null) {
            $frequencyDays = (int) $policy['frequency_days'];
            $rotateOnEnroll = (int) $policy['rotate_on_enroll'] === 1;
        } else {
            $policyId = 0;
        }
    }
    $nextRotation = laprComputeNextRotation(null, $frequencyDays);
    $osInfo = json_decode((string) ($endpoint['os_info'] ?? ''), true) ?: [];
    $storedSelfTarget = (bool) ($osInfo['lapr_self_target']['is_self'] ?? false);
    $runtimeSelfTarget = laprClassifySelfTarget((string) $endpoint['hostname'], $settings);
    $isSelfTarget = $storedSelfTarget || $runtimeSelfTarget['is_self'];
    $queueEnrollmentRotation = laprShouldQueueEnrollmentRotation($rotateOnEnroll, $isSelfTarget);

    $reactivated = $existing !== null;
    $accountId = $reactivated === true ? (int) $existing['id'] : 0;
    $rotationTaskId = 0;

    // Account creation/reactivation, audit, and the optional enrollment task
    // form one contract. A queueing failure must not leave an active account
    // whose requested first rotation was silently lost.
    DB::startTransaction();
    try {
        if ($reactivated === true) {
            DB::update(prefixTable('lapr_accounts'), [
                'endpoint_id' => $endpointId,
                'username_cache' => $login,
                'policy_id' => $policyId > 0 ? $policyId : null,
                'last_rotation_at' => null,
                'last_rotation_status' => 'never',
                'last_rotation_error' => null,
                'next_rotation_at' => $nextRotation,
                'retry_count' => 0,
                'retry_at' => null,
                'status' => 'active',
                'updated_by' => $userId,
            ], 'id = %i', $accountId);
        } else {
            DB::insert(prefixTable('lapr_accounts'), [
                'endpoint_id' => $endpointId,
                'item_id' => $itemId,
                'username_cache' => $login,
                'policy_id' => $policyId > 0 ? $policyId : null,
                'next_rotation_at' => $nextRotation,
                'status' => 'active',
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $accountId = (int) DB::insertId();
        }

        laprAuditLog('account_add', $endpointId, $userId, [
            'item_id' => $itemId,
            'username' => $login,
            'policy_id' => $policyId,
            'reactivated' => $reactivated,
        ], 'success', $accountId);

        if ($queueEnrollmentRotation === true) {
            // A reactivated row may still have a pending task. Reuse it instead
            // of creating two workers for the same managed account.
            $rotationTaskId = (int) DB::queryFirstField(
                'SELECT increment_id FROM ' . prefixTable('background_tasks') . '
                 WHERE process_type = %s AND item_id = %i AND is_in_progress IN (0,1)
                 AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)
                 ORDER BY increment_id DESC LIMIT 1',
                'lapr_rotation',
                $accountId
            );
            if ($rotationTaskId <= 0) {
                DB::insert(prefixTable('background_tasks'), [
                    'created_at' => (string) time(),
                    'process_type' => 'lapr_rotation',
                    'arguments' => json_encode([
                        'account_id' => $accountId,
                        'trigger' => 'enroll',
                        'author' => $userId,
                    ], JSON_UNESCAPED_SLASHES),
                    'is_in_progress' => 0,
                    'item_id' => $accountId,
                    'status' => 'new',
                ]);
                $rotationTaskId = (int) DB::insertId();
            }
        }

        DB::commit();
    } catch (MeekroDBException $exception) {
        DB::rollback();
        if ($reactivated === false) {
            // A concurrent request may have claimed the same item after the
            // availability check. Return a controlled conflict, not HTTP 500.
            $conflictingAccountId = (int) DB::queryFirstField(
                'SELECT id FROM ' . prefixTable('lapr_accounts') . ' WHERE item_id = %i',
                $itemId
            );
            if ($conflictingAccountId > 0) {
                echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_item_already_managed')], 'encode');
                return;
            }
        }
        throw $exception;
    } catch (Throwable $exception) {
        DB::rollback();
        throw $exception;
    }

    if ($queueEnrollmentRotation === true) {
        triggerBackgroundHandler();
    }

    echo prepareExchangedData([
        'error' => false,
        'message' => $lang->get('lapr_account_added'),
        'id' => $accountId,
        'task_id' => $rotationTaskId,
        'rotation_queued' => $rotationTaskId > 0,
        'rotation_skipped_manual_only' => $rotateOnEnroll === true && $isSelfTarget === true,
    ], 'encode');
}

/**
 * Soft-delete a managed account.
 *
 * @param array    $data   Decoded client payload
 * @param int      $userId Acting user id
 * @param Language $lang   Language helper
 * @return void
 */
function laprDeleteAccount(array $data, int $userId, Language $lang): void
{
    $accountId = (int) ($data['id'] ?? 0);
    if ($accountId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_account')], 'encode');
        return;
    }

    $account = DB::queryFirstRow(
        'SELECT id, endpoint_id FROM ' . prefixTable('lapr_accounts') . ' WHERE id = %i AND status != %s',
        $accountId,
        'deleted'
    );
    if ($account === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_account_not_found')], 'encode');
        return;
    }

    DB::update(prefixTable('lapr_accounts'), [
        'status' => 'deleted',
        'updated_by' => $userId,
    ], 'id = %i', $accountId);

    // A removed relationship must not retain an executable rotation. Complete
    // every task that has not started yet; a worker already running performs a
    // fresh account-state check immediately before the remote password change.
    $timestamp = time();
    DB::update(prefixTable('background_tasks'), [
        'is_in_progress' => -1,
        'finished_at' => $timestamp,
        'updated_at' => $timestamp,
        'status' => 'completed',
        'output' => json_encode([
            'success' => false,
            'error_code' => 'ERR_ACCOUNT_DELETED',
            'message' => 'ACCOUNT_REMOVED',
        ], JSON_UNESCAPED_SLASHES),
    ], 'process_type = %s AND item_id = %i AND is_in_progress = 0
        AND (finished_at IS NULL OR finished_at = %s OR finished_at = 0)',
        'lapr_rotation',
        $accountId,
        ''
    );

    laprAuditLog('account_add', (int) $account['endpoint_id'], $userId, ['deleted' => true], 'success', $accountId);

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_account_deleted')], 'encode');
}

/**
 * Change the policy of a managed account and recompute next_rotation_at
 * (spec Option A: last_rotation_at + new frequency, clamped to now).
 *
 * @param array    $data   Decoded client payload
 * @param int      $userId Acting user id
 * @param Language $lang   Language helper
 * @return void
 */
function laprUpdateAccountPolicy(array $data, int $userId, Language $lang): void
{
    $accountId = (int) ($data['id'] ?? 0);
    $policyId = (int) ($data['policy_id'] ?? 0);
    if ($accountId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_account')], 'encode');
        return;
    }

    $account = DB::queryFirstRow(
        'SELECT id, last_rotation_at, endpoint_id FROM ' . prefixTable('lapr_accounts') . '
         WHERE id = %i AND status != %s',
        $accountId,
        'deleted'
    );
    if ($account === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_account_not_found')], 'encode');
        return;
    }

    $frequencyDays = LAPR_DEFAULT_FREQUENCY_DAYS;
    if ($policyId > 0) {
        $policy = DB::queryFirstRow(
            'SELECT frequency_days FROM ' . prefixTable('lapr_policies') . ' WHERE id = %i',
            $policyId
        );
        if ($policy === null) {
            echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_policy_not_found')], 'encode');
            return;
        }
        $frequencyDays = (int) $policy['frequency_days'];
    }

    $nextRotation = laprComputeNextRotation($account['last_rotation_at'], $frequencyDays);

    DB::update(prefixTable('lapr_accounts'), [
        'policy_id' => $policyId > 0 ? $policyId : null,
        'next_rotation_at' => $nextRotation,
        'updated_by' => $userId,
    ], 'id = %i', $accountId);

    laprAuditLog('account_add', (int) $account['endpoint_id'], $userId, [
        'policy_id' => $policyId,
        'policy_changed' => true,
    ], 'success', $accountId);

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_account_updated')], 'encode');
}

/**
 * Start a background local-account discovery scan on an endpoint.
 *
 * @param array    $data   Decoded client payload
 * @param int      $userId Acting user id
 * @param Language $lang   Language helper
 * @return void
 */
function laprStartDiscover(array $data, int $userId, Language $lang): void
{
    $endpointId = (int) ($data['endpoint_id'] ?? 0);
    if ($endpointId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_endpoint')], 'encode');
        return;
    }

    $endpoint = DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('lapr_endpoints') . ' WHERE id = %i AND status = %s',
        $endpointId,
        'active'
    );
    if ((int) $endpoint === 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_endpoint_not_found')], 'encode');
        return;
    }

    DB::insert(prefixTable('background_tasks'), [
        'created_at' => (string) time(),
        'process_type' => 'lapr_discover',
        'arguments' => json_encode(['endpoint_id' => $endpointId, 'author' => $userId], JSON_UNESCAPED_SLASHES),
        'is_in_progress' => 0,
        'status' => 'new',
    ]);
    $taskId = (int) DB::insertId();

    triggerBackgroundHandler();

    echo prepareExchangedData(['error' => false, 'task_id' => $taskId], 'encode');
}

/**
 * Start a manual rotation for an account. Enforces write access to the item's
 * folder and a duplicate-rotation guard (C12 — exact match via item_id column).
 *
 * @param array            $data    Decoded client payload
 * @param SessionInterface $session Current session
 * @param int              $userId  Acting user id
 * @param Language         $lang    Language helper
 * @return void
 */
function laprStartRotation(array $data, SessionInterface $session, int $userId, Language $lang): void
{
    $accountId = (int) ($data['id'] ?? 0);
    if ($accountId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_account')], 'encode');
        return;
    }

    $account = DB::queryFirstRow(
        'SELECT a.id, a.status, i.id_tree
         FROM ' . prefixTable('lapr_accounts') . ' AS a
         INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = a.item_id
         WHERE a.id = %i AND a.status != %s',
        $accountId,
        'deleted'
    );
    if ($account === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_account_not_found')], 'encode');
        return;
    }

    // Rotation writes the item → require write access to its folder.
    if (laprUserCanWriteFolder((int) $account['id_tree'], $session) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        return;
    }

    // C12 — duplicate-rotation guard: exact match on the indexed item_id column
    // (populated with the account id for lapr_rotation tasks), no fragile LIKE.
    $pending = DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
         WHERE process_type = %s AND item_id = %i AND is_in_progress IN (0,1)
         AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
        'lapr_rotation',
        $accountId
    );
    if ((int) $pending > 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_rotation_already_running')], 'encode');
        return;
    }

    DB::insert(prefixTable('background_tasks'), [
        'created_at' => (string) time(),
        'process_type' => 'lapr_rotation',
        'arguments' => json_encode(['account_id' => $accountId, 'trigger' => 'manual', 'author' => $userId], JSON_UNESCAPED_SLASHES),
        'is_in_progress' => 0,
        'item_id' => $accountId,
        'status' => 'new',
    ]);
    $taskId = (int) DB::insertId();

    triggerBackgroundHandler();

    echo prepareExchangedData(['error' => false, 'task_id' => $taskId, 'message' => $lang->get('lapr_rotation_started')], 'encode');
}

/**
 * Reset a paused/error account and resume rotations ("Reset & Resume", Point 5):
 * clears the retry state and recomputes next_rotation_at from now.
 *
 * @param array            $data    Decoded client payload
 * @param SessionInterface $session Current session
 * @param int              $userId  Acting user id
 * @param Language         $lang    Language helper
 * @return void
 */
function laprResetAccount(array $data, SessionInterface $session, int $userId, Language $lang): void
{
    $accountId = (int) ($data['id'] ?? 0);
    if ($accountId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_account')], 'encode');
        return;
    }

    $account = DB::queryFirstRow(
        'SELECT a.id, a.policy_id, a.last_rotation_at, a.endpoint_id, i.id_tree
         FROM ' . prefixTable('lapr_accounts') . ' AS a
         INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = a.item_id
         WHERE a.id = %i AND a.status != %s',
        $accountId,
        'deleted'
    );
    if ($account === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_account_not_found')], 'encode');
        return;
    }

    if (laprUserCanWriteFolder((int) $account['id_tree'], $session) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        return;
    }

    $frequencyDays = LAPR_DEFAULT_FREQUENCY_DAYS;
    if ((int) ($account['policy_id'] ?? 0) > 0) {
        $policy = DB::queryFirstRow(
            'SELECT frequency_days FROM ' . prefixTable('lapr_policies') . ' WHERE id = %i',
            (int) $account['policy_id']
        );
        if ($policy !== null) {
            $frequencyDays = (int) $policy['frequency_days'];
        }
    }

    DB::update(prefixTable('lapr_accounts'), [
        'status' => 'active',
        'retry_count' => 0,
        'retry_at' => null,
        'last_rotation_error' => null,
        'next_rotation_at' => laprComputeNextRotation(null, $frequencyDays),
        'updated_by' => $userId,
    ], 'id = %i', $accountId);

    laprAuditLog('account_reset', (int) $account['endpoint_id'], $userId, [], 'success', $accountId);

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_account_reset_done')], 'encode');
}

/**
 * Poll a rotation task and return the outcome on completion.
 *
 * @param array    $data Decoded client payload
 * @param Language $lang Language helper
 * @return void
 */
function laprRotationStatus(array $data, Language $lang): void
{
    $taskId = (int) ($data['task_id'] ?? 0);
    if ($taskId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_task')], 'encode');
        return;
    }

    $task = DB::queryFirstRow(
        'SELECT status, output, is_in_progress FROM ' . prefixTable('background_tasks') . '
         WHERE increment_id = %i AND process_type = %s',
        $taskId,
        'lapr_rotation'
    );
    if ($task === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_task_not_found')], 'encode');
        return;
    }

    $output = json_decode((string) ($task['output'] ?? '{}'), true) ?: [];
    $finished = (int) $task['is_in_progress'] === -1 || (string) $task['status'] === 'completed' || (string) $task['status'] === 'failed';

    if ($finished === false) {
        echo prepareExchangedData(['error' => false, 'finished' => false], 'encode');
        return;
    }

    echo prepareExchangedData([
        'error' => false,
        'finished' => true,
        'success' => (bool) ($output['success'] ?? false),
        'error_code' => $output['error_code'] ?? '',
        'message_code' => $output['message'] ?? '',
    ], 'encode');
}

/**
 * Paginated, read-only rotation history for one account (Point 6). Reads the
 * LAPR audit log filtered by account_id; never decrypts anything. Enforces read
 * access to the item's folder.
 *
 * @param array                $data     Decoded client payload
 * @param SessionInterface     $session  Current session
 * @param Language             $lang     Language helper
 * @param array<string, mixed> $settings TeamPass regional settings
 * @return void
 */
function laprListAccountHistory(array $data, SessionInterface $session, Language $lang, array $settings): void
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $limit = (int) ($data['limit'] ?? 20);
    $offset = (int) ($data['offset'] ?? 0);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    if ($accountId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_account')], 'encode');
        return;
    }

    $account = DB::queryFirstRow(
        'SELECT a.id, a.username_cache, a.item_id, e.label AS ep_label, i.id_tree
         FROM ' . prefixTable('lapr_accounts') . ' AS a
         INNER JOIN ' . prefixTable('lapr_endpoints') . ' AS e ON e.id = a.endpoint_id
         INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = a.item_id
         WHERE a.id = %i AND a.status != %s',
        $accountId,
        'deleted'
    );
    if ($account === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_account_not_found')], 'encode');
        return;
    }

    if (laprUserCanReadFolder((int) $account['id_tree'], $session) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        return;
    }

    $total = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('lapr_audit_log') . ' WHERE account_id = %i',
        $accountId
    );

    $rows = DB::query(
        'SELECT al.id, al.action_type, al.result, al.error_message, al.action_details,
                al.created_at, al.user_id, u.login AS user_login
         FROM ' . prefixTable('lapr_audit_log') . ' AS al
         LEFT JOIN ' . prefixTable('users') . ' AS u ON u.id = al.user_id
         WHERE al.account_id = %i
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT %i OFFSET %i',
        $accountId,
        $limit,
        $offset
    );

    $items = [];
    foreach ($rows as $r) {
        $details = json_decode((string) ($r['action_details'] ?? '{}'), true) ?: [];
        $isSystem = (int) $r['user_id'] === (int) TP_USER_ID;
        $createdAt = laprFormatDateTimeForDisplay(
            $r['created_at'] === null ? null : (string) $r['created_at'],
            $settings
        );
        $items[] = [
            'created_at' => $createdAt['display'],
            'action_type' => $r['action_type'],
            'trigger' => $details['trigger'] ?? null,
            'result' => $r['result'],
            'error' => $r['error_message'] ?? ($details['error'] ?? null),
            'user_login' => $isSystem ? null : ($r['user_login'] ?? null),
            'is_system' => $isSystem,
        ];
    }

    echo prepareExchangedData([
        'error' => false,
        'account' => [
            'username' => $account['username_cache'],
            'ep_label' => $account['ep_label'],
        ],
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'rows' => $items,
    ], 'encode');
}

/**
 * Poll a discovery task and return the discovered accounts on completion.
 *
 * @param array    $data Decoded client payload
 * @param Language $lang Language helper
 * @return void
 */
function laprDiscoverStatus(array $data, Language $lang): void
{
    $taskId = (int) ($data['task_id'] ?? 0);
    if ($taskId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_task')], 'encode');
        return;
    }

    $task = DB::queryFirstRow(
        'SELECT status, output, is_in_progress FROM ' . prefixTable('background_tasks') . '
         WHERE increment_id = %i AND process_type = %s',
        $taskId,
        'lapr_discover'
    );
    if ($task === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_task_not_found')], 'encode');
        return;
    }

    $output = json_decode((string) ($task['output'] ?? '{}'), true) ?: [];
    $finished = (int) $task['is_in_progress'] === -1 || (string) $task['status'] === 'completed' || (string) $task['status'] === 'failed';

    if ($finished === false) {
        echo prepareExchangedData(['error' => false, 'finished' => false, 'step' => $output['step'] ?? 'connecting'], 'encode');
        return;
    }

    echo prepareExchangedData([
        'error' => false,
        'finished' => true,
        'success' => (bool) ($output['success'] ?? false),
        'accounts' => $output['accounts'] ?? [],
        'error_code' => $output['error_code'] ?? '',
    ], 'encode');
}
