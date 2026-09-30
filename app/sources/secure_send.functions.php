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
 * @file      secure_send.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Secure Send recipient lifecycle.
 */
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;

require_once __DIR__ . '/secure_send_access.php';
require_once __DIR__ . '/secure_send_logic.php';
require_once __DIR__ . '/secure_send_url.php';

/**
 * Find a link by both its random lookup code and its original timestamp.
 *
 * @param array $parameters Validated request parameters
 * @param bool $lock Serialize redemption, failed attempts, updates and revocation
 * @return array Stored link or an empty array
 */
function secureSendFindLink(array $parameters, bool $lock = false): array
{
    return DB::queryFirstRow(
        'SELECT * FROM ' . prefixTable('otv') . ' WHERE code = %s AND timestamp = %s' . ($lock ? ' FOR UPDATE' : ''),
        $parameters['code'], $parameters['stamp']
    ) ?: [];
}

/**
 * Decrypt the supported payload formats without interpreting legacy passwords as JSON.
 *
 * @param array $link Stored link
 * @param string $linkSecret Secret from the URL
 * @param string $passphrase Recipient passphrase (unchanged, including whitespace)
 * @param array $settings Application settings
 * @return array Decrypted fields
 */
function secureSendDecryptPayload(array $link, string $linkSecret, string $passphrase, array $settings): array
{
    $key = $linkSecret;
    $hasProtectedKey = !empty($link['protected_key']);
    if ($hasProtectedKey) {
        $wrapPassword = (int) $link['has_passphrase'] === 1 ? hash('sha256', $linkSecret . '|' . $passphrase) : $linkSecret;
        $key = defuse_validate_personal_key($wrapPassword, $link['protected_key']);
        // The existing wrapper distinguishes an incorrect secret from a broken environment.
        if ($key === 'Error - The saltkey is not the correct one.') {
            throw new WrongKeyOrModifiedCiphertextException('invalid_link');
        }
        if (str_starts_with($key, 'Error')) {
            throw new RuntimeException('key_unwrap_error');
        }
    }
    try {
        $decrypted = cryption($link['encrypted'], $key, 'decrypt', $settings);
    } catch (BadFormatException $e) {
        // Only legacy links take a serialized cipher key directly from the recipient URL.
        if (!$hasProtectedKey) {
            throw new WrongKeyOrModifiedCiphertextException('invalid_link');
        }
        throw $e;
    }
    if (($decrypted['error'] ?? false) === 'wrong_key_or_modified_ciphertext' && !$hasProtectedKey) {
        throw new WrongKeyOrModifiedCiphertextException('invalid_link');
    }
    if (!empty($decrypted['error'])) {
        // Once the stored key was unlocked, a payload failure is not a bad passphrase.
        throw new RuntimeException('payload_decryption_error');
    }
    if (($link['send_type'] ?? 'item') === 'item') {
        return ['password' => $decrypted['string']];
    }
    $payload = json_decode($decrypted['string'], true, 16, JSON_THROW_ON_ERROR);
    $fields = $link['send_type'] === 'note' ? ['title', 'secret', 'note', 'login', 'url'] : ['label', 'password', 'description', 'login', 'url'];
    foreach ($fields as $field) {
        if (!is_array($payload) || !isset($payload[$field]) || !is_string($payload[$field])) {
            throw new UnexpectedValueException('invalid_payload');
        }
    }
    if ($link['send_type'] !== 'note' && array_key_exists('totp', $payload)) {
        $profile = $payload['totp'];
        if (!is_array($profile)
            || !isset($profile['secret'], $profile['algorithm'], $profile['digits'], $profile['period'])
            || !is_string($profile['secret'])
            || !is_string($profile['algorithm'])
            || !is_int($profile['digits'])
            || !is_int($profile['period'])
        ) {
            throw new UnexpectedValueException('invalid_payload');
        }
        try {
            $totp = createItemTotp(
                $profile['secret'],
                $profile['algorithm'],
                $profile['digits'],
                $profile['period']
            );
            $payload['otp_code'] = $totp->now();
            $payload['otp_expires_in'] = $totp->expiresIn();
        } catch (Throwable $e) {
            throw new UnexpectedValueException('invalid_payload');
        } finally {
            // The recipient only needs the short-lived code, never the shared seed.
            unset($payload['totp'], $profile);
        }
    }
    return $payload;
}

/**
 * Reveal only after the caller has validated an explicit, session-bound POST confirmation.
 *
 * Row locks serialize different recipients and links on the same auto-deleting item.
 * No view is consumed on an invalid key, passphrase or item. The guarded UPDATE
 * is an additional invariant: a view must be reserved before any plaintext is returned.
 *
 * @param array $parameters Validated link credentials
 * @param string $passphrase Recipient passphrase
 * @param array $settings Application settings
 * @param string $hostHeader Raw HTTP Host (used only for public links)
 * @return array Result with fields only after a committed successful redemption
 */
function secureSendRedeem(array $parameters, string $passphrase, array $settings, string $hostHeader = ''): array
{
    DB::startTransaction();
    try {
        $link = secureSendFindLink($parameters, true);
        if ($link === [] || !secureSendIsAvailable($link, $settings, time())
            || !secureSendHostIsAllowed($settings, $link, $hostHeader)
        ) {
            DB::rollback();
            return ['error' => 'invalid_link'];
        }
        $isNote = ($link['send_type'] ?? 'item') === 'note';
        $item = [];
        $automatic = [];
        if ($isNote && !DB::queryFirstField('SELECT id FROM ' . prefixTable('users') . ' WHERE id = %i AND disabled = 0 AND deleted_at IS NULL', $link['originator'])) {
            DB::delete(prefixTable('otv'), 'id = %i', $link['id']);
            DB::commit();
            return ['error' => 'invalid_link'];
        }
        if (!$isNote) {
            $item = secureSendReadItem((int) $link['item_id'], (int) $link['originator'], true);
            if ($item === []) {
                DB::delete(prefixTable('otv'), 'id = %i', $link['id']);
                DB::commit();
                return ['error' => 'invalid_link'];
            }
            if ((int) ($settings['enable_delete_after_consultation'] ?? 0) === 1) {
                $automatic = DB::queryFirstRow(
                    'SELECT * FROM ' . prefixTable('automatic_del') . ' WHERE item_id = %i FOR UPDATE',
                    $link['item_id']
                ) ?: [];
                if ((int) ($automatic['del_enabled'] ?? 0) === 1
                    && (((int) $automatic['del_type'] === 1 && (int) $automatic['del_value'] <= 0)
                    || ((int) $automatic['del_type'] === 2 && (int) $automatic['del_value'] <= time()))
                ) {
                    secureSendDeactivateItem($item, $settings);
                    DB::commit();
                    return ['error' => 'invalid_link'];
                }
            }
        }
        if ((int) ($link['has_passphrase'] ?? 0) === 1 && $passphrase === '') {
            DB::rollback();
            return ['error' => 'passphrase_required'];
        }
        try {
            $fields = secureSendDecryptPayload($link, $parameters['key'], $passphrase, $settings);
        } catch (WrongKeyOrModifiedCiphertextException $e) {
            $attempts = (int) ($link['failed_attempts'] ?? 0) + 1;
            if ($attempts >= 5) {
                DB::delete(prefixTable('otv'), 'id = %i', $link['id']);
            } else {
                DB::update(prefixTable('otv'), ['failed_attempts' => $attempts], 'id = %i', $link['id']);
            }
            DB::commit();
            return ['error' => $attempts >= 5 ? 'too_many_attempts' : ((int) ($link['has_passphrase'] ?? 0) === 1 ? 'wrong_passphrase' : 'invalid_link')];
        }
        if (($link['send_type'] ?? 'item') === 'item') {
            // Historical links contain only the password; keep their original rendering contract.
            foreach (['label', 'login', 'url', 'description'] as $field) {
                $fields[$field] = (string) ($item[$field] ?? '');
            }
        }
        $reserved = DB::query(
            'UPDATE ' . prefixTable('otv') . ' SET views = views + 1
            WHERE id = %i AND views < max_views AND CAST(time_limit AS UNSIGNED) > %i AND failed_attempts < 5',
            $link['id'], time()
        );
        if ($reserved !== 1) {
            DB::rollback();
            return ['error' => 'invalid_link'];
        }
        if ((int) ($automatic['del_enabled'] ?? 0) === 1 && (int) $automatic['del_type'] === 1) {
            $consumed = DB::query(
                'UPDATE ' . prefixTable('automatic_del') . ' SET del_value = del_value - 1
                WHERE item_id = %i AND del_enabled = 1 AND del_type = 1 AND del_value > 0',
                $link['item_id']
            );
            if ($consumed !== 1) {
                DB::rollback();
                return ['error' => 'invalid_link'];
            }
        }
        logItems($settings, $isNote ? 0 : (int) $link['item_id'], $isNote ? 'secure-send-note' : $item['label'],
            (int) OTV_USER_ID, 'at_shown', 'otv', null, null, null, null, true, true);
        if ((int) ($automatic['del_enabled'] ?? 0) === 1
            && (int) $automatic['del_type'] === 1 && (int) $automatic['del_value'] === 1
        ) {
            // The final permitted reveal succeeds; subsequent links cannot read the inactive item.
            secureSendDeactivateItem($item, $settings);
        }
        DB::commit();
        return ['error' => '', 'fields' => $fields, 'send_type' => $isNote ? 'note' : 'item',
            'time_limit' => (int) $link['time_limit'], 'remaining_views' => (int) $link['max_views'] - (int) $link['views'] - 1];
    } catch (Throwable $e) {
        DB::rollback();
        // Never include the URL, request, payload or exception message in public output/logs.
        error_log('TEAMPASS Secure Send redemption failed (' . get_class($e) . ')');
        return ['error' => 'server_error'];
    }
}

/**
 * Apply automatic deletion inside the same transaction as the view reservation.
 *
 * @param array $item Locked item
 * @param array $settings Application settings
 * @return void
 */
function secureSendDeactivateItem(array $item, array $settings): void
{
    DB::update(prefixTable('items'), ['inactif' => 1, 'deleted_at' => time()], 'id = %i', (int) $item['id']);
    // Keep the automatic-deletion settings, as the authenticated item-view path does.
    logItems($settings, (int) $item['id'], $item['label'], (int) OTV_USER_ID,
        'at_delete', 'otv', 'at_automatically_deleted', null, null, null, true, true);
    updateCacheTable('delete_value', (int) $item['id']);
    adjustFolderItemsCounter((int) $item['id_tree'], -1);
    emitItemEvent('deleted', (int) $item['id'], (int) $item['id_tree'], $item['label'], 'otv');
}

/**
 * Prepare the public recipient page without revealing anything on GET or an unconfirmed POST.
 *
 * @param array $input Query or form fields
 * @param string $method HTTP method
 * @param array $settings Application settings
 * @param array $confirmations Bounded, session-owned map of single-use confirmation tokens
 * @param string $hostHeader Raw HTTP Host (used only for public links)
 * @return array Page state; plaintext fields are only present after a committed redemption
 */
function secureSendPrepareRecipient(array $input, string $method, array $settings, array &$confirmations, string $hostHeader = ''): array
{
    $parameters = secureSendRequestParameters($input);
    $link = [];
    $result = null;
    $error = '';
    $token = '';
    try {
        $link = $parameters === null ? [] : secureSendFindLink($parameters);
        if ($link === [] || !secureSendIsAvailable($link, $settings, time())
            || !secureSendHostIsAllowed($settings, $link, $hostHeader)
        ) {
            $link = [];
            $error = 'secure_send_invalid_link';
        } else {
            $confirmationId = secureSendConfirmationId($parameters);
            if ($method === 'POST') {
                $submitted = is_string($input['confirmation'] ?? null) ? $input['confirmation'] : '';
                $expected = (string) ($confirmations[$confirmationId] ?? '');
                unset($confirmations[$confirmationId]);
                if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
                    $error = 'secure_send_confirmation_expired';
                } else {
                    $passphrase = is_string($input['passphrase'] ?? null) ? $input['passphrase'] : '';
                    if (strlen($passphrase) > 1024) {
                        $error = 'secure_send_invalid_link';
                    } else {
                        $result = secureSendRedeem($parameters, $passphrase, $settings, $hostHeader);
                        $errors = [
                            'invalid_link' => 'secure_send_invalid_link',
                            'wrong_passphrase' => 'secure_send_wrong_passphrase',
                            'passphrase_required' => 'secure_send_enter_passphrase',
                            'too_many_attempts' => 'secure_send_too_many_attempts',
                            'server_error' => 'server_answer_error',
                        ];
                        $error = $errors[$result['error']] ?? '';
                        if (in_array($result['error'], ['invalid_link', 'too_many_attempts'], true)) {
                            $link = [];
                        }
                    }
                }
            }
            if ($link !== [] && ($result === null || $result['error'] !== '')) {
                $token = bin2hex(random_bytes(32));
                // Bound anonymous session storage even when many links are opened.
                $confirmations = array_slice($confirmations, -19, null, true);
                $confirmations[$confirmationId] = $token;
            }
        }
    } catch (Throwable $e) {
        error_log('TEAMPASS Secure Send request failed (' . get_class($e) . ')');
        $error = 'secure_send_invalid_link';
        $link = [];
    }
    return ['parameters' => $parameters, 'link' => $link, 'result' => $result, 'error' => $error, 'token' => $token];
}
