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
 * Sign-in passkeys: the profile and administration actions, with their database, session,
 * audit and email side. The decisions and the ceremonies are in webauthn_login_logic.php.
 *
 * Expects main.functions.php to be loaded (DB, prefixTable, logEvents, getServerSecret,
 * sendMailToUser), as it is in every *.queries.php handler.
 *
 * @file      webauthn_login.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/webauthn_login_logic.php';

use TeampassClasses\Language\Language;
use TeampassClasses\SessionManager\SessionManager;

/** Session key of the ceremony in progress (options handed to the browser). */
const TP_WEBAUTHN_LOGIN_PENDING_KEY = 'webauthn_login_pending';

/**
 * Run a profile action on the caller's own passkeys.
 *
 * @param string               $type     webauthn_login_* profile action
 * @param array<string, mixed> $data     Decoded request
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language of the caller
 *
 * @return array<string, mixed> Response, error => true with a message on failure
 */
function webauthnLoginProfileAction(string $type, array $data, array $SETTINGS, Language $lang): array
{
    $session = SessionManager::getSession();
    $userId = (int) $session->get('user-id');
    if ($userId <= 0) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }

    try {
        switch ($type) {
            case 'webauthn_login_list':
                return webauthnLoginList($userId, $SETTINGS);
            case 'webauthn_login_register_options':
                return webauthnLoginRegisterOptions($userId, $SETTINGS, $lang);
            case 'webauthn_login_register_verify':
                return webauthnLoginRegisterVerify($userId, $data, $SETTINGS, $lang);
            case 'webauthn_login_rename':
                return webauthnLoginRename($userId, $data, $lang);
            case 'webauthn_login_delete':
                return webauthnLoginDelete($userId, (int) ($data['id'] ?? 0), $userId, $SETTINGS, $lang);
            case 'webauthn_login_passwordless_options':
                return webauthnLoginPasswordlessOptions($userId, (int) ($data['id'] ?? 0), $SETTINGS, $lang);
            case 'webauthn_login_passwordless_verify':
                return webauthnLoginPasswordlessVerify($userId, $data, $SETTINGS, $lang);
            case 'webauthn_login_passwordless_disable':
                return webauthnLoginPasswordlessDisable($userId, (int) ($data['id'] ?? 0), $SETTINGS, $lang);
        }
    } catch (Throwable $e) {
        error_log('TEAMPASS Error - ' . $type . ' for user ' . $userId . ' [' . get_class($e) . '] ' . $e->getMessage());
    }

    return webauthnLoginError($lang->get('error_unknown'));
}

/**
 * Run an administration action on the passkeys of another account. The caller's right to
 * manage that account is checked by users.queries.php before this is reached.
 *
 * @param string               $type     webauthn_login_admin_list|webauthn_login_admin_delete
 * @param array<string, mixed> $data     Decoded request, with user_id
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language of the caller
 *
 * @return array<string, mixed>
 */
function webauthnLoginAdminAction(string $type, array $data, array $SETTINGS, Language $lang): array
{
    $session = SessionManager::getSession();
    $targetId = (int) ($data['user_id'] ?? 0);
    if ($targetId <= 0) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }

    if ($type === 'webauthn_login_admin_list') {
        $target = DB::queryFirstRow('SELECT login, name, lastname FROM ' . prefixTable('users') . ' WHERE id = %i', $targetId);
        if ($target === null) {
            return webauthnLoginError($lang->get('error_not_allowed_to'));
        }

        return [
            'error' => false,
            'user' => trim((string) $target['name'] . ' ' . (string) $target['lastname'] . ' [' . (string) $target['login'] . ']'),
            'credentials' => webauthnLoginRows($targetId),
        ];
    }

    return webauthnLoginDelete($targetId, (int) ($data['id'] ?? 0), (int) $session->get('user-id'), $SETTINGS, $lang);
}

/**
 * List the caller's passkeys, never their key material.
 *
 * @param int                  $userId   Caller
 * @param array<string, mixed> $SETTINGS Settings
 *
 * @return array<string, mixed>
 */
function webauthnLoginList(int $userId, array $SETTINGS): array
{
    return [
        'error' => false,
        'mode' => webauthnLoginMode($SETTINGS),
        'can_wrap' => webauthnLoginSessionCanWrap($SETTINGS),
        'require_prf' => (int) ($SETTINGS['webauthn_login_require_prf'] ?? 0) === 1,
        'credentials' => webauthnLoginRows($userId),
    ];
}

/**
 * Start registering a passkey: options for navigator.credentials.create().
 *
 * @param int                  $userId   Caller
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language
 *
 * @return array<string, mixed>
 */
function webauthnLoginRegisterOptions(int $userId, array $SETTINGS, Language $lang): array
{
    if (webauthnLoginMode($SETTINGS) === TP_WEBAUTHN_LOGIN_MODE_DISABLED) {
        return webauthnLoginError($lang->get('webauthn_login_disabled'));
    }
    $rpId = webauthnLoginRpId($SETTINGS);
    if ($rpId === '' || webauthnLoginOriginOf((string) ($SETTINGS['cpassman_url'] ?? '')) === '') {
        return webauthnLoginError($lang->get('webauthn_login_misconfigured'));
    }
    if (count(webauthnLoginRows($userId)) >= TP_WEBAUTHN_LOGIN_MAX_CREDENTIALS) {
        return webauthnLoginError($lang->get('webauthn_login_too_many'));
    }

    $user = DB::queryFirstRow('SELECT login, name, lastname FROM ' . prefixTable('users') . ' WHERE id = %i', $userId);
    if ($user === null) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }

    $forPasswordless = webauthnLoginSessionCanWrap($SETTINGS);
    $options = webauthnLoginCreationOptions(
        $rpId,
        webauthnLoginRpName($SETTINGS),
        webauthnLoginUserHandle($userId, getServerSecret()),
        (string) $user['login'],
        trim((string) $user['name'] . ' ' . (string) $user['lastname']),
        webauthnLoginExcludeIds($userId),
        $forPasswordless
    );
    $optionsJson = webauthnLoginSerializeOptions($options);
    $salt = $forPasswordless === true ? webauthnBase64UrlEncode(random_bytes(TP_WEBAUTHN_LOGIN_SECRET_BYTES)) : '';

    SessionManager::getSession()->set(TP_WEBAUTHN_LOGIN_PENDING_KEY, [
        'purpose' => 'register',
        'options' => $optionsJson,
        'created_at' => time(),
        'wrap' => $forPasswordless,
        'salt' => $salt,
    ]);

    return [
        'error' => false,
        'options' => json_decode($optionsJson, true),
        'prf_salt' => $salt,
    ];
}

/**
 * Finish registering a passkey: verify it, wrap the private key when it signs in alone, store it.
 *
 * @param int                  $userId   Caller
 * @param array<string, mixed> $data     credential, label, prf_state (results|enabled|unsupported), prf_output
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language
 *
 * @return array<string, mixed>
 */
function webauthnLoginRegisterVerify(int $userId, array $data, array $SETTINGS, Language $lang): array
{
    $pending = webauthnLoginTakePending('register');
    if ($pending === null) {
        return webauthnLoginError($lang->get('webauthn_login_ceremony_expired'));
    }
    if (webauthnLoginMode($SETTINGS) === TP_WEBAUTHN_LOGIN_MODE_DISABLED) {
        return webauthnLoginError($lang->get('webauthn_login_disabled'));
    }
    if (count(webauthnLoginRows($userId)) >= TP_WEBAUTHN_LOGIN_MAX_CREDENTIALS) {
        return webauthnLoginError($lang->get('webauthn_login_too_many'));
    }

    try {
        $record = webauthnLoginVerifyRegistration(
            is_array($data['credential'] ?? null) === true ? $data['credential'] : [],
            (string) $pending['options'],
            webauthnLoginOriginOf((string) ($SETTINGS['cpassman_url'] ?? ''))
        );
    } catch (InvalidArgumentException $e) {
        error_log('TEAMPASS Error - webauthn_login_register_verify for user ' . $userId . ': ' . $e->getMessage());
        return webauthnLoginError($lang->get('webauthn_login_verification_failed'));
    }

    $credentialIdB64 = webauthnBase64UrlEncode($record->publicKeyCredentialId);
    if ((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('user_webauthn_credentials') . ' WHERE credential_id = %s', $credentialIdB64) > 0) {
        return webauthnLoginError($lang->get('webauthn_login_already_registered'));
    }

    // The passwordless copy of the private key, when this account may sign in without password.
    $wrap = ['mode' => TP_WEBAUTHN_LOGIN_WRAP_NONE, 'wrapped' => null, 'salt' => null];
    $finishPasswordless = false;
    $notice = '';
    if (($pending['wrap'] ?? false) === true && webauthnLoginSessionCanWrap($SETTINGS) === true) {
        $prfOutput = webauthnLoginReadBytes($data['prf_output'] ?? '', TP_WEBAUTHN_LOGIN_SECRET_BYTES);
        $prfState = (string) ($data['prf_state'] ?? '');
        if ($prfOutput === '' && $prfState === 'enabled') {
            // PRF exists but was not evaluated at creation: the browser evaluates it with a
            // second gesture, through the same path as enabling passwordless later.
            $finishPasswordless = true;
        } else {
            $wrap = webauthnLoginBuildWrap(
                $record->publicKeyCredentialId,
                webauthnLoginReadBytes((string) ($pending['salt'] ?? ''), TP_WEBAUTHN_LOGIN_SECRET_BYTES),
                $prfOutput,
                $SETTINGS
            );
            if ($wrap['mode'] === TP_WEBAUTHN_LOGIN_WRAP_NONE) {
                $notice = $lang->get('webauthn_login_prf_required_notice');
            }
        }
    }

    DB::insert(
        prefixTable('user_webauthn_credentials'),
        [
            'user_id' => $userId,
            'credential_id' => $credentialIdB64,
            'public_key_cose' => base64_encode($record->credentialPublicKey),
            'sign_count' => $record->counter,
            'aaguid' => (string) $record->aaguid,
            'transports' => json_encode(array_values($record->transports)),
            'label' => webauthnLoginLabelOrDefault($data['label'] ?? '', $lang),
            'key_wrap_mode' => $wrap['mode'],
            'wrapped_private_key' => $wrap['wrapped'],
            'wrap_salt' => $wrap['salt'],
            'backup_eligible' => $record->backupEligible === true ? 1 : 0,
            'backup_state' => $record->backupStatus === true ? 1 : 0,
            'created_at' => time(),
        ]
    );
    $newId = (int) DB::insertId();

    $session = SessionManager::getSession();
    logEvents($SETTINGS, 'user_mngt', 'at_user_webauthn_added', (string) $userId, (string) $session->get('user-login'), (string) $userId);
    webauthnLoginNotifyAdded($SETTINGS, $userId, webauthnLoginLabelOrDefault($data['label'] ?? '', $lang));

    return [
        'error' => false,
        'id' => $newId,
        'passwordless' => $wrap['mode'] !== TP_WEBAUTHN_LOGIN_WRAP_NONE,
        'finish_passwordless' => $finishPasswordless,
        'notice' => $notice,
    ];
}

/**
 * Rename one of the caller's passkeys.
 *
 * @param int                  $userId Caller
 * @param array<string, mixed> $data   id, label
 * @param Language             $lang   Language
 *
 * @return array<string, mixed>
 */
function webauthnLoginRename(int $userId, array $data, Language $lang): array
{
    $label = webauthnLoginNormalizeLabel($data['label'] ?? '');
    if ($label === '' || webauthnLoginRow($userId, (int) ($data['id'] ?? 0)) === null) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }
    DB::update(prefixTable('user_webauthn_credentials'), ['label' => $label], 'id = %i AND user_id = %i', (int) $data['id'], $userId);

    return ['error' => false];
}

/**
 * Delete a passkey: the caller's own, or another account's by an administrator.
 *
 * @param int                  $ownerId  Owner of the passkey
 * @param int                  $id       Passkey
 * @param int                  $actorId  Who deletes it
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language
 *
 * @return array<string, mixed>
 */
function webauthnLoginDelete(int $ownerId, int $id, int $actorId, array $SETTINGS, Language $lang): array
{
    if (webauthnLoginRow($ownerId, $id) === null) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }
    DB::delete(prefixTable('user_webauthn_credentials'), 'id = %i AND user_id = %i', $id, $ownerId);

    $actorLogin = (string) DB::queryFirstField('SELECT login FROM ' . prefixTable('users') . ' WHERE id = %i', $actorId);
    logEvents($SETTINGS, 'user_mngt', 'at_user_webauthn_deleted', (string) $actorId, $actorLogin, (string) $ownerId);

    return ['error' => false];
}

/**
 * Start enabling passwordless sign-in on an existing passkey: an assertion that proves the
 * passkey is at hand and evaluates its PRF.
 *
 * @param int                  $userId   Caller
 * @param int                  $id       Passkey
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language
 *
 * @return array<string, mixed>
 */
function webauthnLoginPasswordlessOptions(int $userId, int $id, array $SETTINGS, Language $lang): array
{
    if (webauthnLoginSessionCanWrap($SETTINGS) === false) {
        return webauthnLoginError($lang->get('webauthn_login_passwordless_unavailable'));
    }
    $row = webauthnLoginRow($userId, $id);
    if ($row === null || (int) $row['key_wrap_mode'] !== TP_WEBAUTHN_LOGIN_WRAP_NONE) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }

    $optionsJson = webauthnLoginSerializeOptions(webauthnLoginRequestOptions(
        webauthnLoginRpId($SETTINGS),
        [webauthnBase64UrlDecode((string) $row['credential_id'])],
        true
    ));
    $salt = webauthnBase64UrlEncode(random_bytes(TP_WEBAUTHN_LOGIN_SECRET_BYTES));

    SessionManager::getSession()->set(TP_WEBAUTHN_LOGIN_PENDING_KEY, [
        'purpose' => 'passwordless',
        'options' => $optionsJson,
        'created_at' => time(),
        'credential_row_id' => $id,
        'salt' => $salt,
    ]);

    return [
        'error' => false,
        'options' => json_decode($optionsJson, true),
        'prf_salt' => $salt,
    ];
}

/**
 * Finish enabling passwordless sign-in: verify the assertion, then wrap the private key.
 *
 * @param int                  $userId   Caller
 * @param array<string, mixed> $data     id, credential, prf_output
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language
 *
 * @return array<string, mixed>
 */
function webauthnLoginPasswordlessVerify(int $userId, array $data, array $SETTINGS, Language $lang): array
{
    $pending = webauthnLoginTakePending('passwordless');
    $id = (int) ($data['id'] ?? 0);
    if ($pending === null || (int) ($pending['credential_row_id'] ?? 0) !== $id) {
        return webauthnLoginError($lang->get('webauthn_login_ceremony_expired'));
    }
    if (webauthnLoginSessionCanWrap($SETTINGS) === false) {
        return webauthnLoginError($lang->get('webauthn_login_passwordless_unavailable'));
    }
    $row = webauthnLoginRow($userId, $id);
    if ($row === null || (int) $row['key_wrap_mode'] !== TP_WEBAUTHN_LOGIN_WRAP_NONE) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }

    try {
        $record = webauthnLoginVerifyAssertion(
            webauthnLoginRecordFromRow($row, webauthnLoginUserHandle($userId, getServerSecret())),
            is_array($data['credential'] ?? null) === true ? $data['credential'] : [],
            (string) $pending['options'],
            webauthnLoginOriginOf((string) ($SETTINGS['cpassman_url'] ?? ''))
        );
    } catch (InvalidArgumentException $e) {
        error_log('TEAMPASS Error - webauthn_login_passwordless_verify for user ' . $userId . ': ' . $e->getMessage());
        return webauthnLoginError($lang->get('webauthn_login_verification_failed'));
    }

    $wrap = webauthnLoginBuildWrap(
        $record->publicKeyCredentialId,
        webauthnLoginReadBytes((string) ($pending['salt'] ?? ''), TP_WEBAUTHN_LOGIN_SECRET_BYTES),
        webauthnLoginReadBytes($data['prf_output'] ?? '', TP_WEBAUTHN_LOGIN_SECRET_BYTES),
        $SETTINGS
    );
    DB::update(
        prefixTable('user_webauthn_credentials'),
        [
            'sign_count' => $record->counter,
            'backup_state' => $record->backupStatus === true ? 1 : 0,
            'key_wrap_mode' => $wrap['mode'],
            'wrapped_private_key' => $wrap['wrapped'],
            'wrap_salt' => $wrap['salt'],
        ],
        'id = %i AND user_id = %i',
        $id,
        $userId
    );
    if ($wrap['mode'] === TP_WEBAUTHN_LOGIN_WRAP_NONE) {
        return webauthnLoginError($lang->get('webauthn_login_prf_required_notice'));
    }

    $session = SessionManager::getSession();
    logEvents($SETTINGS, 'user_mngt', 'at_user_webauthn_passwordless_enabled', (string) $userId, (string) $session->get('user-login'), (string) $userId);

    return ['error' => false, 'passwordless' => true];
}

/**
 * Remove the passwordless copy of the private key from one of the caller's passkeys. It stays
 * usable as a second factor.
 *
 * @param int                  $userId   Caller
 * @param int                  $id       Passkey
 * @param array<string, mixed> $SETTINGS Settings
 * @param Language             $lang     Language
 *
 * @return array<string, mixed>
 */
function webauthnLoginPasswordlessDisable(int $userId, int $id, array $SETTINGS, Language $lang): array
{
    if (webauthnLoginRow($userId, $id) === null) {
        return webauthnLoginError($lang->get('error_not_allowed_to'));
    }
    DB::update(
        prefixTable('user_webauthn_credentials'),
        ['key_wrap_mode' => TP_WEBAUTHN_LOGIN_WRAP_NONE, 'wrapped_private_key' => null, 'wrap_salt' => null],
        'id = %i AND user_id = %i',
        $id,
        $userId
    );

    $session = SessionManager::getSession();
    logEvents($SETTINGS, 'user_mngt', 'at_user_webauthn_passwordless_disabled', (string) $userId, (string) $session->get('user-login'), (string) $userId);

    return ['error' => false];
}

/**
 * Wrap the session's private key for a passkey: under its PRF output when the authenticator gave
 * one, otherwise under the instance secret unless the administrator requires PRF.
 *
 * @param string               $credentialId Raw credential id
 * @param string               $salt         32-byte salt of the ceremony
 * @param string               $prfOutput    32-byte PRF output, '' when none
 * @param array<string, mixed> $SETTINGS     Settings
 *
 * @return array{mode: int, wrapped: string|null, salt: string|null}
 */
function webauthnLoginBuildWrap(string $credentialId, string $salt, string $prfOutput, array $SETTINGS): array
{
    $none = ['mode' => TP_WEBAUTHN_LOGIN_WRAP_NONE, 'wrapped' => null, 'salt' => null];
    if (strlen($salt) !== TP_WEBAUTHN_LOGIN_SECRET_BYTES) {
        return $none;
    }

    if ($prfOutput !== '') {
        $mode = TP_WEBAUTHN_LOGIN_WRAP_PRF;
        $wrapKey = webauthnLoginPrfWrapKey($prfOutput, $salt);
    } elseif ((int) ($SETTINGS['webauthn_login_require_prf'] ?? 0) !== 1) {
        $mode = TP_WEBAUTHN_LOGIN_WRAP_SERVER;
        $wrapKey = webauthnLoginServerWrapKey(getServerSecret(), $salt, $credentialId);
    } else {
        return $none;
    }

    return [
        'mode' => $mode,
        'wrapped' => webauthnLoginWrapPrivateKey((string) SessionManager::getSession()->get('user-private_key'), $wrapKey),
        'salt' => bin2hex($salt),
    ];
}

/**
 * Tell whether the current session may give a passkey a passwordless copy of the private key:
 * passwordless mode, a local account, and the cleartext private key at hand.
 *
 * @param array<string, mixed> $SETTINGS Settings
 *
 * @return bool
 */
function webauthnLoginSessionCanWrap(array $SETTINGS): bool
{
    $session = SessionManager::getSession();
    $privateKey = (string) $session->get('user-private_key');

    return webauthnLoginCanWrap($SETTINGS, (string) $session->get('user-auth_type'))
        && $privateKey !== ''
        && $privateKey !== 'none';
}

/**
 * Take the ceremony in progress out of the session: each set of options is answered once.
 *
 * @param string $purpose register|passwordless
 *
 * @return array<string, mixed>|null
 */
function webauthnLoginTakePending(string $purpose): ?array
{
    $session = SessionManager::getSession();
    $pending = $session->get(TP_WEBAUTHN_LOGIN_PENDING_KEY);
    $session->remove(TP_WEBAUTHN_LOGIN_PENDING_KEY);

    return webauthnLoginPendingIsUsable($pending, $purpose, time()) === true ? $pending : null;
}

/**
 * The passkeys of an account, without any key material.
 *
 * @param int $userId Owner
 *
 * @return array<int, array<string, mixed>>
 */
function webauthnLoginRows(int $userId): array
{
    $rows = DB::query(
        'SELECT id, label, key_wrap_mode, backup_eligible, backup_state, created_at, last_used_at
        FROM ' . prefixTable('user_webauthn_credentials') . '
        WHERE user_id = %i
        ORDER BY created_at DESC, id DESC',
        $userId
    );

    return array_map(
        static fn (array $row): array => [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'passwordless' => (int) $row['key_wrap_mode'] !== TP_WEBAUTHN_LOGIN_WRAP_NONE,
            'server_wrap' => (int) $row['key_wrap_mode'] === TP_WEBAUTHN_LOGIN_WRAP_SERVER,
            'synced' => (int) $row['backup_state'] === 1,
            'created_at' => (int) $row['created_at'],
            'last_used_at' => $row['last_used_at'] === null ? null : (int) $row['last_used_at'],
        ],
        $rows
    );
}

/**
 * One passkey of an account, with what a ceremony needs.
 *
 * @param int $userId Owner
 * @param int $id     Passkey
 *
 * @return array<string, mixed>|null
 */
function webauthnLoginRow(int $userId, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    return DB::queryFirstRow(
        'SELECT id, credential_id, public_key_cose, sign_count, aaguid, transports, key_wrap_mode,
            backup_eligible, backup_state
        FROM ' . prefixTable('user_webauthn_credentials') . '
        WHERE id = %i AND user_id = %i',
        $id,
        $userId
    );
}

/**
 * Raw ids of an account's passkeys, so an authenticator does not register a second one.
 *
 * @param int $userId Owner
 *
 * @return string[]
 */
function webauthnLoginExcludeIds(int $userId): array
{
    return array_map(
        static fn ($id): string => webauthnBase64UrlDecode((string) $id),
        DB::queryFirstColumn('SELECT credential_id FROM ' . prefixTable('user_webauthn_credentials') . ' WHERE user_id = %i', $userId)
    );
}

/**
 * The device name the user typed, or a dated default.
 *
 * @param mixed    $label Submitted value
 * @param Language $lang  Language
 *
 * @return string
 */
function webauthnLoginLabelOrDefault($label, Language $lang): string
{
    $label = webauthnLoginNormalizeLabel($label);

    return $label !== '' ? $label : $lang->get('webauthn_login_default_label') . ' ' . date('Y-m-d');
}

/**
 * Email the owner when a passkey is added to their TeamPass account, in their language. Queued,
 * never fatal: the passkey is already stored.
 *
 * @param array<string, mixed> $SETTINGS Settings
 * @param int                  $userId   Owner
 * @param string               $label    Name of the passkey
 *
 * @return void
 */
function webauthnLoginNotifyAdded(array $SETTINGS, int $userId, string $label): void
{
    if ((int) ($SETTINGS['webauthn_email_on_add'] ?? 1) !== 1
        || trim((string) ($SETTINGS['email_smtp_server'] ?? '')) === ''
    ) {
        return;
    }

    try {
        $user = DB::queryFirstRow(
            'SELECT email, name, lastname, user_language FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );
        if ($user === null || filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL) === false) {
            return;
        }
        $language = trim((string) ($user['user_language'] ?? ''));
        if ($language === '' || $language === '0') {
            $language = (string) ($SETTINGS['default_language'] ?? 'english');
        }
        $userLang = new Language($language);

        sendMailToUser(
            (string) $user['email'],
            (string) $userLang->get('email_body_webauthn_login_added'),
            getEmailTemplateSubject('webauthn_login_added', $userLang),
            [
                '#passkey_label#' => htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                '#tp_date#' => date((string) ($SETTINGS['date_format'] ?? 'd/m/Y')),
                '#tp_time#' => date((string) ($SETTINGS['time_format'] ?? 'H:i:s')),
                '#tp_ip#' => htmlspecialchars(getClientIpServer(), ENT_QUOTES, 'UTF-8'),
            ],
            false,
            '',
            trim((string) $user['name'] . ' ' . (string) $user['lastname'])
        );
    } catch (Throwable $e) {
        error_log('TEAMPASS Error - webauthnLoginNotifyAdded for user ' . $userId . ': ' . $e->getMessage());
    }
}

/**
 * Error response.
 *
 * @param string $message Message shown to the user
 *
 * @return array{error: true, message: string}
 */
function webauthnLoginError(string $message): array
{
    return ['error' => true, 'message' => $message];
}
