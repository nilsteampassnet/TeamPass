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
 * @file      lapr_policies.queries.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('lapr_policies') === false) {
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
    case 'list_policies':
        laprListPolicies($lang);
        break;
    case 'create_policy':
        laprCreatePolicy($dataReceived, $userId, $lang);
        break;
    case 'update_policy':
        laprUpdatePolicy($dataReceived, $lang);
        break;
    case 'delete_policy':
        laprDeletePolicy($dataReceived, $lang);
        break;
    case 'preview_password':
        laprPreviewPassword($dataReceived, $lang);
        break;
    default:
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_unknown_action')], 'encode');
}

/**
 * List all rotation policies (presets first), with in-use count.
 *
 * @param Language $lang Language helper
 * @return void
 */
function laprListPolicies(Language $lang): void
{
    $rows = DB::query(
        'SELECT p.id, p.label, p.frequency_days, p.password_length, p.use_uppercase,
                p.use_lowercase, p.use_digits, p.use_symbols, p.rotate_on_enroll, p.is_preset,
                (SELECT COUNT(*) FROM ' . prefixTable('lapr_accounts') . ' a
                 WHERE a.policy_id = p.id AND a.status != "deleted") AS in_use
         FROM ' . prefixTable('lapr_policies') . ' AS p
         ORDER BY p.is_preset DESC, p.label ASC'
    );

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'id' => (int) $r['id'],
            'label' => laprPolicyDisplayName(
                (string) $r['label'],
                (int) $r['is_preset'] === 1,
                $lang
            ),
            'frequency_days' => (int) $r['frequency_days'],
            'password_length' => (int) $r['password_length'],
            'use_uppercase' => (int) $r['use_uppercase'],
            'use_lowercase' => (int) $r['use_lowercase'],
            'use_digits' => (int) $r['use_digits'],
            'use_symbols' => (int) $r['use_symbols'],
            'rotate_on_enroll' => (int) $r['rotate_on_enroll'],
            'is_preset' => (int) $r['is_preset'],
            'in_use' => (int) $r['in_use'],
        ];
    }

    echo prepareExchangedData(['error' => false, 'data' => $data], 'encode');
}

/**
 * Extract and normalize policy fields from the client payload.
 *
 * @param array $data Decoded client payload
 *
 * @return array{label: string, frequency_days: int, password_length: int, use_uppercase: bool, use_lowercase: bool, use_digits: bool, use_symbols: bool, rotate_on_enroll: int}
 */
function laprExtractPolicyFields(array $data): array
{
    return [
        'label' => trim((string) ($data['label'] ?? '')),
        'frequency_days' => (int) ($data['frequency_days'] ?? 30),
        'password_length' => (int) ($data['password_length'] ?? 24),
        'use_uppercase' => (int) ($data['use_uppercase'] ?? 0) === 1,
        'use_lowercase' => (int) ($data['use_lowercase'] ?? 0) === 1,
        'use_digits' => (int) ($data['use_digits'] ?? 0) === 1,
        'use_symbols' => (int) ($data['use_symbols'] ?? 0) === 1,
        'rotate_on_enroll' => (int) ($data['rotate_on_enroll'] ?? 0) === 1 ? 1 : 0,
    ];
}

/**
 * Create a rotation policy after validating bounds (Point 3).
 *
 * @param array    $data   Decoded client payload
 * @param int      $userId Acting user id
 * @param Language $lang   Language helper
 * @return void
 */
function laprCreatePolicy(array $data, int $userId, Language $lang): void
{
    $f = laprExtractPolicyFields($data);
    if ($f['label'] === '') {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_empty_data')], 'encode');
        return;
    }
    if (laprValidatePolicyParams(
        $f['frequency_days'],
        $f['password_length'],
        $f['use_uppercase'],
        $f['use_lowercase'],
        $f['use_digits'],
        $f['use_symbols']
    ) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_policy_bounds')], 'encode');
        return;
    }

    DB::insert(prefixTable('lapr_policies'), [
        'label' => $f['label'],
        'frequency_days' => $f['frequency_days'],
        'password_length' => $f['password_length'],
        'use_uppercase' => $f['use_uppercase'] ? 1 : 0,
        'use_lowercase' => $f['use_lowercase'] ? 1 : 0,
        'use_digits' => $f['use_digits'] ? 1 : 0,
        'use_symbols' => $f['use_symbols'] ? 1 : 0,
        'rotate_on_enroll' => $f['rotate_on_enroll'],
        'is_preset' => 0,
        'created_by' => $userId,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_policy_saved'), 'id' => (int) DB::insertId()], 'encode');
}

/**
 * Update a non-preset rotation policy.
 *
 * @param array    $data Decoded client payload
 * @param Language $lang Language helper
 * @return void
 */
function laprUpdatePolicy(array $data, Language $lang): void
{
    $policyId = (int) ($data['id'] ?? 0);
    if ($policyId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_policy')], 'encode');
        return;
    }

    $policy = DB::queryFirstRow(
        'SELECT id, is_preset FROM ' . prefixTable('lapr_policies') . ' WHERE id = %i',
        $policyId
    );
    if ($policy === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_policy_not_found')], 'encode');
        return;
    }
    if ((int) $policy['is_preset'] === 1) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_preset_readonly')], 'encode');
        return;
    }

    $f = laprExtractPolicyFields($data);
    if ($f['label'] === '') {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error_empty_data')], 'encode');
        return;
    }
    if (laprValidatePolicyParams(
        $f['frequency_days'],
        $f['password_length'],
        $f['use_uppercase'],
        $f['use_lowercase'],
        $f['use_digits'],
        $f['use_symbols']
    ) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_policy_bounds')], 'encode');
        return;
    }

    DB::update(prefixTable('lapr_policies'), [
        'label' => $f['label'],
        'frequency_days' => $f['frequency_days'],
        'password_length' => $f['password_length'],
        'use_uppercase' => $f['use_uppercase'] ? 1 : 0,
        'use_lowercase' => $f['use_lowercase'] ? 1 : 0,
        'use_digits' => $f['use_digits'] ? 1 : 0,
        'use_symbols' => $f['use_symbols'] ? 1 : 0,
        'rotate_on_enroll' => $f['rotate_on_enroll'],
    ], 'id = %i', $policyId);

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_policy_saved')], 'encode');
}

/**
 * Delete a rotation policy. Blocked when it is a preset or is in use.
 *
 * @param array    $data Decoded client payload
 * @param Language $lang Language helper
 * @return void
 */
function laprDeletePolicy(array $data, Language $lang): void
{
    $policyId = (int) ($data['id'] ?? 0);
    if ($policyId <= 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_invalid_policy')], 'encode');
        return;
    }

    $policy = DB::queryFirstRow(
        'SELECT id, is_preset FROM ' . prefixTable('lapr_policies') . ' WHERE id = %i',
        $policyId
    );
    if ($policy === null) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_policy_not_found')], 'encode');
        return;
    }
    if ((int) $policy['is_preset'] === 1) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_preset_readonly')], 'encode');
        return;
    }

    $inUse = DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('lapr_accounts') . ' WHERE policy_id = %i AND status != "deleted"',
        $policyId
    );
    if ((int) $inUse > 0) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_policy_in_use')], 'encode');
        return;
    }

    DB::delete(prefixTable('lapr_policies'), 'id = %i', $policyId);

    echo prepareExchangedData(['error' => false, 'message' => $lang->get('lapr_policy_deleted')], 'encode');
}

/**
 * Preview a generated password for the given policy parameters (Linux-safe).
 *
 * @param array    $data Decoded client payload
 * @param Language $lang Language helper
 * @return void
 */
function laprPreviewPassword(array $data, Language $lang): void
{
    $f = laprExtractPolicyFields($data);
    if (laprValidatePolicyParams(
        $f['frequency_days'],
        $f['password_length'],
        $f['use_uppercase'],
        $f['use_lowercase'],
        $f['use_digits'],
        $f['use_symbols']
    ) === false) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('lapr_policy_bounds')], 'encode');
        return;
    }

    try {
        $password = laprGeneratePassword(
            $f['password_length'],
            $f['use_uppercase'],
            $f['use_lowercase'],
            $f['use_digits'],
            $f['use_symbols']
        );
    } catch (Throwable $e) {
        echo prepareExchangedData(['error' => true, 'message' => $lang->get('error')], 'encode');
        return;
    }

    echo prepareExchangedData(['error' => false, 'password' => $password], 'encode');
}
