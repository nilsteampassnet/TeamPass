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
 * @file      secure_send_create.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/secure_send_access.php';
require_once __DIR__ . '/secure_send_snapshot.php';
require_once __DIR__ . '/secure_send_input.php';
require_once __DIR__ . '/secure_send_url.php';
require_once __DIR__ . '/secure_send_storage.php';
require_once __DIR__ . '/secure_send_email.php';

/**
 * Refusal raised by secureSendCreateLink().
 *
 * The message is always one of CODES: a stable, data-free error code shared by the
 * web handler and the REST API. An unexpected code degrades to 'server_error'.
 */
final class SecureSendCreateRefusal extends RuntimeException
{
    /** Every code secureSendCreateLink() can raise. */
    public const CODES = [
        'not_allowed', 'invalid_payload', 'email_not_allowed', 'invalid_email', 'notes_not_allowed',
        'passphrase_required', 'cannot_decrypt', 'totp_unusable', 'empty_note', 'invalid_public_url',
        'email_rate_limited', 'email_not_configured', 'email_requires_https', 'server_error',
    ];

    /** Keep the message within the fixed code list. */
    public function __construct(string $code)
    {
        parent::__construct(in_array($code, self::CODES, true) ? $code : 'server_error');
    }
}

/**
 * Create a Secure Send link on behalf of an authenticated sender, without any session access.
 *
 * The caller owns authentication and request integrity (web session key, API JWT). Policy,
 * item access, encryption, storage, audit and optional email delivery are enforced here.
 * Relies on the main.functions.php helpers (GenerateCryptKey, cryption, defuse_*) being loaded.
 *
 * @param array $input Request in the web payload shape: send_type, id, passphrase, days, views,
 *                     shared_globaly, include_totp (int), recipient_email?, payload? (note)
 * @param array $sender ['id' => int, 'private_key' => string, 'public_key' => string, 'read_only' => bool]
 * @param array $settings TeamPass settings
 * @return array{url: string, otv_id: int, send_type: string, item_id: ?int, has_passphrase: int,
 *               description_truncated: bool, totp_included: bool, email_queued: bool,
 *               expires_at: int, max_views: int, public_address: bool}
 * @throws SecureSendCreateRefusal On every functional refusal (message = error code)
 */
function secureSendCreateLink(array $input, array $sender, array $settings): array
{
    // Master gate: Secure Send relies on the OTV engine
    if ((int) ($settings['otv_is_enabled'] ?? 0) !== 1) {
        throw new SecureSendCreateRefusal('not_allowed');
    }
    $senderId = (int) ($sender['id'] ?? 0);
    if ($senderId <= 0) {
        throw new SecureSendCreateRefusal('not_allowed');
    }
    // Fail closed: only an explicit false lets the sender include the item TOTP.
    $senderReadOnly = ($sender['read_only'] ?? true) !== false;

    try {
        secureSendValidateInput($input);
    } catch (InvalidArgumentException $e) {
        throw new SecureSendCreateRefusal('invalid_payload');
    }

    $secureSendRecipient = null;
    if (array_key_exists('recipient_email', $input)) {
        if ((int) ($settings['secure_send_allow_email'] ?? 0) !== 1) {
            throw new SecureSendCreateRefusal('email_not_allowed');
        }
        try {
            $secureSendRecipient = secureSendEmailRecipient($input['recipient_email']);
        } catch (InvalidArgumentException $e) {
            throw new SecureSendCreateRefusal('invalid_email');
        }
    }

    // Determine the kind of send: an existing item or an ad-hoc note/secret
    $secureSendType = (isset($input['send_type']) === true && $input['send_type'] === 'note') ? 'note' : 'item';
    if ($secureSendType === 'note' && (int) ($settings['secure_send_allow_notes'] ?? 0) !== 1) {
        throw new SecureSendCreateRefusal('notes_not_allowed');
    }

    // Optional recipient passphrase (transmitted out-of-band by the sender)
    $secureSendPassphrase = (string) ($input['passphrase'] ?? '');
    if ((int) ($settings['secure_send_require_passphrase'] ?? 0) === 1 && trim($secureSendPassphrase) === '') {
        throw new SecureSendCreateRefusal('passphrase_required');
    }

    $secureSendLimits = secureSendLimits($settings, $input, time());

    // Build the plaintext payload to share
    $secureSendDescriptionTruncated = false;
    $secureSendTotp = null;
    if ($secureSendType === 'item') {
        // Item sends keep a coherent encrypted copy of all displayed fields.
        $secureSendItemId = (int) ($input['id'] ?? 0);
        $itemQ = secureSendReadItem($secureSendItemId, $senderId);
        if ($itemQ === []) {
            throw new SecureSendCreateRefusal('not_allowed');
        }
        try {
            $secureSendPlaintext = secureSendItemPassword(
                $itemQ,
                $senderId,
                (string) ($sender['private_key'] ?? ''),
                (string) ($sender['public_key'] ?? '')
            );
            $secureSendTotp = secureSendShouldIncludeTotp($input, $senderReadOnly)
                ? secureSendItemTotp($secureSendItemId)
                : null;
            $snapshot = secureSendEncodeSnapshot($itemQ, $secureSendPlaintext, $secureSendTotp);
            $secureSendPlaintext = $snapshot['plaintext'];
            $secureSendDescriptionTruncated = $snapshot['description_truncated'];
            $secureSendType = 'item_v2';
        } catch (InvalidArgumentException $e) {
            $secureSendError = $e->getMessage();
            throw new SecureSendCreateRefusal(in_array($secureSendError, ['invalid_payload', 'totp_unusable'], true)
                ? $secureSendError
                : 'cannot_decrypt');
        }
    } else {
        // Note send: self-contained encrypted JSON, not bound to any item
        $secureSendItemId = null;
        $secureSendPayload = (isset($input['payload']) === true && is_array($input['payload']) === true)
            ? $input['payload']
            : array();
        if (trim((string) ($secureSendPayload['secret'] ?? '')) === ''
            && trim((string) ($secureSendPayload['note'] ?? '')) === ''
        ) {
            throw new SecureSendCreateRefusal('empty_note');
        }
        $secureSendPlaintext = json_encode(
            array(
                'title'  => trim((string) ($secureSendPayload['title'] ?? '')),
                'secret' => (string) ($secureSendPayload['secret'] ?? ''),
                'note'   => (string) ($secureSendPayload['note'] ?? ''),
                'login'  => trim((string) ($secureSendPayload['login'] ?? '')),
                'url'    => trim((string) ($secureSendPayload['url'] ?? '')),
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    // Opportunistic cleanup keeps evidence before removing expired ciphertext.
    try {
        secureSendPurgeExpiredLinks($settings);
        secureSendEmailPruneMetadata(time());
    } catch (Throwable $e) {
        error_log('TEAMPASS Secure Send cleanup failed (' . get_class($e) . ')');
    }

    // Generate the lookup code and the link secret carried in the URL.
    // The Defuse key that encrypts the payload is wrapped by the link secret
    // (and, when set, the passphrase) so the URL alone is not enough to decrypt.
    $otv_code = GenerateCryptKey(32, false, true, true, false, true);
    $secureSendLinkSecret = GenerateCryptKey(32, false, true, true, false, true);
    $secureSendWrapPassword = $secureSendPassphrase === ''
        ? $secureSendLinkSecret
        : hash('sha256', $secureSendLinkSecret . '|' . $secureSendPassphrase);

    $secureSendProtectedKey = defuse_generate_personal_key($secureSendWrapPassword);
    $secureSendKeyEncoded = defuse_validate_personal_key(
        $secureSendWrapPassword,
        $secureSendProtectedKey
    );

    // Encrypt the payload with DEFUSE using the wrapped key
    $passwd = cryption(
        (string) $secureSendPlaintext,
        $secureSendKeyEncoded,
        'encrypt',
        $settings
    );
    if (!empty($passwd['error']) || strlen($passwd['string']) > 65535) {
        throw new SecureSendCreateRefusal('invalid_payload');
    }
    $timestampReference = time();

    // Item copies and standalone notes use the same administrator-controlled address.
    // The public address is opt-in: a request that does not ask for it gets the main URL.
    $secureSendShared = (int) ($input['shared_globaly'] ?? 0) === 1 ? 1 : 0;
    try {
        $url = secureSendUrl($settings, $secureSendShared === 1, [
            'otv' => 1, 'code' => $otv_code, 'key' => $secureSendLinkSecret, 'stamp' => $timestampReference,
        ]);
    } catch (InvalidArgumentException $e) {
        throw new SecureSendCreateRefusal('invalid_public_url');
    }
    try {
        $newID = secureSendStoreLink(
            array(
                'id' => null,
                'item_id' => $secureSendItemId,
                'send_type' => $secureSendType,
                'timestamp' => $timestampReference,
                'originator' => $senderId,
                'code' => $otv_code,
                'encrypted' => $passwd['string'],
                'protected_key' => $secureSendProtectedKey,
                'has_passphrase' => $secureSendPassphrase === '' ? 0 : 1,
                'failed_attempts' => 0,
                'time_limit' => $secureSendLimits['time_limit'],
                'max_views' => $secureSendLimits['views'],
                'shared_globaly' => $secureSendShared,
            ),
            $settings,
            $secureSendRecipient === null ? null : ['recipient' => $secureSendRecipient, 'url' => $url]
        );
    } catch (InvalidArgumentException $e) {
        // email_not_allowed: the setting was switched off after the recipient check above.
        throw new SecureSendCreateRefusal(in_array($e->getMessage(), [
            'invalid_email', 'email_rate_limited', 'email_not_configured', 'email_requires_https', 'not_allowed',
            'email_not_allowed',
        ], true) ? $e->getMessage() : 'server_error');
    } catch (Throwable $e) {
        error_log('TEAMPASS Secure Send creation failed (' . get_class($e) . ')');
        throw new SecureSendCreateRefusal('server_error');
    }

    return [
        'url' => $url,
        'otv_id' => $newID,
        'send_type' => $secureSendItemId === null ? 'note' : 'item',
        'item_id' => $secureSendItemId,
        'has_passphrase' => $secureSendPassphrase === '' ? 0 : 1,
        'description_truncated' => $secureSendDescriptionTruncated,
        'totp_included' => $secureSendTotp !== null,
        'email_queued' => $secureSendRecipient !== null,
        'expires_at' => $secureSendLimits['time_limit'],
        'max_views' => $secureSendLimits['views'],
        'public_address' => $secureSendShared === 1,
    ];
}
