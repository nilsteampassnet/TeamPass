<?php

declare(strict_types=1);

/** Secure Send recipient lifecycle. Distributed under the GPL-3.0 license. */
require_once __DIR__ . '/secure_send_access.php';
require_once __DIR__ . '/secure_send_logic.php';

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
    if (!empty($link['protected_key'])) {
        $wrapPassword = (int) $link['has_passphrase'] === 1 ? hash('sha256', $linkSecret . '|' . $passphrase) : $linkSecret;
        $key = defuse_validate_personal_key($wrapPassword, $link['protected_key']);
        if (str_starts_with($key, 'Error')) {
            throw new InvalidArgumentException('invalid_link');
        }
    }
    $decrypted = cryption($link['encrypted'], $key, 'decrypt', $settings);
    if (!empty($decrypted['error'])) {
        throw new InvalidArgumentException('invalid_link');
    }
    if (($link['send_type'] ?? 'item') === 'item') {
        return ['password' => $decrypted['string']];
    }
    $payload = json_decode($decrypted['string'], true, 16, JSON_THROW_ON_ERROR);
    $fields = ['title', 'secret', 'note', 'login', 'url'];
    foreach ($fields as $field) {
        if (!is_array($payload) || !isset($payload[$field]) || !is_string($payload[$field])) {
            throw new InvalidArgumentException('invalid_link');
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
 * @return array Result with fields only after a committed successful redemption
 */
function secureSendRedeem(array $parameters, string $passphrase, array $settings): array
{
    DB::startTransaction();
    try {
        $link = secureSendFindLink($parameters, true);
        if ($link === [] || !secureSendIsAvailable($link, $settings, time())) {
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
        } catch (\Defuse\Crypto\Exception\EnvironmentIsBrokenException $e) {
            throw $e;
        } catch (Throwable $e) {
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
            $fields += array_intersect_key($item, array_flip(['label', 'login', 'url', 'description']));
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
    DB::update(prefixTable('items'), ['inactif' => 1], 'id = %i', (int) $item['id']);
    DB::delete(prefixTable('automatic_del'), 'item_id = %i', (int) $item['id']);
    logItems($settings, (int) $item['id'], $item['label'], (int) OTV_USER_ID,
        'at_delete', 'otv', 'at_automatically_deleted', null, null, null, true, true);
    emitItemEvent('deleted', (int) $item['id'], (int) $item['id_tree'], $item['label'], 'otv');
}

/**
 * Prepare the public recipient page without revealing anything on GET or an unconfirmed POST.
 *
 * @param array $input Query or form fields
 * @param string $method HTTP method
 * @param array $settings Application settings
 * @param array $confirmations Bounded, session-owned map of single-use confirmation tokens
 * @return array Page state; plaintext fields are only present after a committed redemption
 */
function secureSendPrepareRecipient(array $input, string $method, array $settings, array &$confirmations): array
{
    $parameters = secureSendRequestParameters($input);
    $link = [];
    $result = null;
    $error = '';
    $token = '';
    try {
        $link = $parameters === null ? [] : secureSendFindLink($parameters);
        if ($link === [] || !secureSendIsAvailable($link, $settings, time())) {
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
                        $result = secureSendRedeem($parameters, $passphrase, $settings);
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
