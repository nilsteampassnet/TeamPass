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
 * @file      secure_send_api_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/*
 * Decisions of the Secure Send REST endpoints (GET /secureSend/policy, POST /secureSend/create,
 * GET /secureSend/list, DELETE /secureSend/revoke). DB-free: creation is secureSendCreateLink(),
 * the inventory secureSendActiveLinks() and revocation secureSendRevokeLink(), all shared with
 * the web handlers.
 */

require_once __DIR__ . '/secure_send_input.php';
require_once __DIR__ . '/secure_send_url.php';
require_once __DIR__ . '/secure_send_email.php';

/**
 * Whether API clients may create Secure Send links on this instance.
 *
 * @param array $settings TeamPass settings
 * @return bool True only when both the OTV engine and the API opt-in are enabled
 */
function secureSendApiEnabled(array $settings): bool
{
    return (int) ($settings['otv_is_enabled'] ?? 0) === 1
        && (int) ($settings['secure_send_api_enabled'] ?? 0) === 1;
}

/**
 * Largest note an API client may send: the UTF-8 bytes of its five members together.
 *
 * Derived from the storage bound secureSendCreateLink() enforces (ciphertext of at most 65535
 * characters): Defuse hex-encodes 84 bytes of overhead plus the plaintext, so the JSON-encoded
 * note may hold 32683 bytes. Escaping a quote, a backslash or a line break doubles it; half of
 * what remains after the JSON envelope, rounded down, keeps every such note storable.
 *
 * @return int Bound in bytes
 */
function secureSendApiNoteMaxBytes(): int
{
    return 16000;
}

/**
 * Build the instance policy an API client needs to render its Secure Send form.
 *
 * Limits are derived from secureSendLimits(), the function that enforces them, so the
 * published policy cannot drift from the creation path.
 *
 * @param array $settings TeamPass settings
 * @param array $userData Authenticated API user (fresh is_admin / is_read_only)
 * @param int|null $now Reference time
 * @return array Policy body
 */
function secureSendApiPolicy(array $settings, array $userData, ?int $now = null): array
{
    $now ??= time();
    $enabled = secureSendApiEnabled($settings);
    $defaults = secureSendLimits($settings, [], $now);
    $ceiling = secureSendLimits($settings, ['views' => PHP_INT_MAX], $now);
    try {
        secureSendBaseUrl($settings, true);
        $publicAddress = true;
    } catch (InvalidArgumentException $e) {
        $publicAddress = false;
    }

    return [
        'enabled' => $enabled,
        // Administrators have no item access (secureSendReadItem() refuses them).
        'item_send_allowed' => $enabled && (int) ($userData['is_admin'] ?? 0) !== 1,
        // A note is not bound to any item: the web offers it to every account, administrators included.
        'note_send_allowed' => $enabled && (int) ($settings['secure_send_allow_notes'] ?? 0) === 1,
        'require_passphrase' => (int) ($settings['secure_send_require_passphrase'] ?? 0) === 1,
        'passphrase_max_bytes' => 1024,
        'note_max_bytes' => secureSendApiNoteMaxBytes(),
        'days' => ['max' => $defaults['days'], 'default' => $defaults['days']],
        'views' => ['max' => $ceiling['views'], 'default' => $defaults['views']],
        'public_address_available' => $publicAddress,
        // Fail closed when the read-only flag is unknown, like secureSendCreateLink().
        'totp_allowed' => (int) ($userData['is_read_only'] ?? 1) !== 1,
        'email' => [
            'allowed' => (int) ($settings['secure_send_allow_email'] ?? 0) === 1,
            'configured' => secureSendEmailIsConfigured($settings),
        ],
        'sender_name_shown' => (int) ($settings['secure_send_show_sender_name'] ?? 0) === 1,
    ];
}

/**
 * Validate a create body and translate it into the orchestrator's input shape.
 *
 * `type` selects an item send (default) or a note send; the members of the other kind are
 * refused, so a note can never carry an item id and an item send never carries note content.
 * A null optional member counts as omitted.
 *
 * @param array $body Decoded JSON object
 * @return array Input for secureSendCreateLink()
 * @throws InvalidArgumentException With a client-facing message (HTTP 400)
 */
function secureSendApiCreateInput(array $body): array
{
    $allowed = ['type', 'item_id', 'note', 'days', 'views', 'passphrase', 'include_totp', 'public_address', 'recipient_email'];
    foreach (array_keys($body) as $member) {
        if (!in_array($member, $allowed, true)) {
            throw new InvalidArgumentException('Unknown member: ' . substr((string) $member, 0, 64));
        }
    }
    $type = $body['type'] ?? 'item';
    if ($type !== 'item' && $type !== 'note') {
        throw new InvalidArgumentException('type must be "item" or "note"');
    }
    if ($type === 'item') {
        if (($body['note'] ?? null) !== null) {
            throw new InvalidArgumentException('note is only valid when type is "note"');
        }
        if (!is_int($body['item_id'] ?? null) || $body['item_id'] <= 0) {
            throw new InvalidArgumentException('item_id is mandatory and must be a positive integer');
        }
        $input = ['send_type' => 'item', 'id' => $body['item_id']];
    } else {
        foreach (['item_id', 'include_totp'] as $member) {
            if (($body[$member] ?? null) !== null) {
                throw new InvalidArgumentException($member . ' is only valid for item sends');
            }
        }
        $input = ['send_type' => 'note', 'payload' => secureSendApiNotePayload($body['note'] ?? null)];
    }
    foreach (['days', 'views'] as $member) {
        if (($body[$member] ?? null) === null) {
            continue;
        }
        if (!is_int($body[$member]) || $body[$member] <= 0) {
            throw new InvalidArgumentException($member . ' must be a positive integer');
        }
        $input[$member] = $body[$member];
    }
    foreach (['passphrase', 'recipient_email'] as $member) {
        if (($body[$member] ?? null) === null) {
            continue;
        }
        if (!is_string($body[$member])) {
            throw new InvalidArgumentException($member . ' must be a string');
        }
        // The orchestrator keys email delivery on the presence of recipient_email.
        $input[$member] = $body[$member];
    }
    // The web payload carries integers and the orchestrator compares include_totp strictly.
    $flags = $type === 'item'
        ? ['include_totp' => 'include_totp', 'public_address' => 'shared_globaly']
        : ['public_address' => 'shared_globaly'];
    foreach ($flags as $member => $internal) {
        $value = $body[$member] ?? false;
        if (!is_bool($value) && $value !== 0 && $value !== 1) {
            throw new InvalidArgumentException($member . ' must be a boolean');
        }
        $input[$internal] = ($value === true || $value === 1) ? 1 : 0;
    }

    return $input;
}

/**
 * Validate the `note` member of a note send and return the orchestrator's payload.
 *
 * Members are strings (null = omitted) and their total size is bounded by
 * secureSendApiNoteMaxBytes(), published in the policy. Emptiness is left to the
 * orchestrator, which answers empty_note.
 *
 * @param mixed $note Decoded `note` member
 * @return array<string, string> Payload with the members the client sent
 * @throws InvalidArgumentException With a client-facing message (HTTP 400)
 */
function secureSendApiNotePayload(mixed $note): array
{
    // JSON {} and [] both decode to an empty array; a non-empty list is not an object.
    if (!is_array($note) || ($note !== [] && array_is_list($note))) {
        throw new InvalidArgumentException('note is mandatory for note sends and must be an object');
    }
    $payload = [];
    $bytes = 0;
    foreach ($note as $member => $value) {
        if (!in_array($member, ['title', 'secret', 'note', 'login', 'url'], true)) {
            throw new InvalidArgumentException('Unknown note member: ' . substr((string) $member, 0, 64));
        }
        if ($value === null) {
            continue;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('note.' . $member . ' must be a string');
        }
        $bytes += strlen($value);
        $payload[$member] = $value;
    }
    if ($bytes > secureSendApiNoteMaxBytes()) {
        throw new InvalidArgumentException('The note exceeds ' . secureSendApiNoteMaxBytes() . ' bytes (policy note_max_bytes)');
    }

    return $payload;
}

/**
 * Read the link identifier of a revocation request.
 *
 * The identifier is not a secret, so it travels in the query string like item/delete. Any
 * other parameter is refused so that a malformed client fails loudly instead of revoking
 * something else than intended.
 *
 * @param array $query Query-string parameters
 * @return int Link identifier (otv.id, a signed 32-bit column)
 * @throws InvalidArgumentException With a client-facing message (HTTP 400)
 */
function secureSendApiRevokeId(array $query): int
{
    foreach (array_keys($query) as $member) {
        if ($member !== 'id') {
            throw new InvalidArgumentException('Unknown parameter: ' . substr((string) $member, 0, 64));
        }
    }
    $id = $query['id'] ?? null;
    if (!is_string($id) || preg_match('/^[1-9][0-9]{0,9}$/', $id) !== 1 || (int) $id > 2147483647) {
        throw new InvalidArgumentException('id is mandatory and must be a positive integer');
    }

    return (int) $id;
}

/**
 * Read the page of a list request: `limit` (1 to 500, default 100) and `offset` (default 0).
 *
 * @param array $query Query-string parameters
 * @return array{limit: int, offset: int}
 * @throws InvalidArgumentException With a client-facing message (HTTP 400)
 */
function secureSendApiListWindow(array $query): array
{
    $window = ['limit' => 100, 'offset' => 0];
    $bounds = ['limit' => [1, 500], 'offset' => [0, 2147483647]];
    foreach ($query as $member => $value) {
        if (!array_key_exists($member, $bounds)) {
            throw new InvalidArgumentException('Unknown parameter: ' . substr((string) $member, 0, 64));
        }
        [$min, $max] = $bounds[$member];
        if (!is_string($value) || preg_match('/^(0|[1-9][0-9]{0,9})$/', $value) !== 1
            || (int) $value < $min || (int) $value > $max
        ) {
            throw new InvalidArgumentException($member . ' must be an integer from ' . $min . ' to ' . $max);
        }
        $window[$member] = (int) $value;
    }

    return $window;
}

/**
 * Shape the caller's links for GET /secureSend/list.
 *
 * Built from an explicit allowlist: an inventory row can carry other columns, and no URL,
 * code, key, ciphertext or recipient may ever leave the server.
 *
 * @param array $rows secureSendActiveLinks() personal rows (labels mode)
 * @param array<int, string> $emailStatuses secureSendEmailStatuses() of the same owner
 * @return array<int, array<string, mixed>> Response items, in the order of $rows
 */
function secureSendApiListBody(array $rows, array $emailStatuses): array
{
    $links = [];
    foreach ($rows as $row) {
        $isNote = $row['send_type'] === 'note';
        $links[] = [
            'id' => (int) $row['id'],
            'type' => $isNote ? 'note' : 'item',
            'item_id' => $isNote ? null : (int) $row['item_id'],
            'folder_id' => $isNote ? null : (int) $row['folder_id'],
            // A note has no plaintext label: its title only exists inside the encrypted link.
            'label' => $isNote ? null : (string) $row['item_label'],
            'has_passphrase' => (int) ($row['has_passphrase'] ?? 0) === 1,
            'expires_at' => (int) $row['time_limit'],
            'max_views' => (int) $row['max_views'],
            'remaining_views' => max(0, (int) $row['max_views'] - (int) $row['views']),
            'suspended' => ($row['suspended'] ?? false) === true,
            'email_status' => $emailStatuses[(int) $row['id']] ?? null,
        ];
    }

    return $links;
}

/**
 * HTTP status of every reason a Secure Send endpoint can refuse with.
 *
 * Keys are the stable `reason` members of the problem responses: every
 * SecureSendCreateRefusal::CODES entry plus the adapter's own refusals.
 *
 * @return array<string, int>
 */
function secureSendApiRefusalStatuses(): array
{
    return [
        'secure_send_disabled' => 503,
        'invalid_payload' => 400,
        'invalid_email' => 400,
        'empty_note' => 400,
        'not_found' => 404,
        'not_allowed' => 403,
        'email_not_allowed' => 403,
        'notes_not_allowed' => 403,
        'passphrase_required' => 422,
        'cannot_decrypt' => 422,
        'totp_unusable' => 422,
        'email_requires_https' => 422,
        'email_rate_limited' => 429,
        'email_not_configured' => 503,
        'invalid_public_url' => 503,
        'server_error' => 500,
    ];
}

/**
 * Human-readable explanation of a refusal (clients should map the reason, not this text).
 *
 * @param string $reason Refusal reason
 * @return string Detail member, never carrying request data
 */
function secureSendApiRefusalDetail(string $reason): string
{
    return match ($reason) {
        'secure_send_disabled' => 'Secure Send is disabled for API clients',
        'invalid_payload' => 'Invalid Secure Send request',
        'invalid_email' => 'Invalid recipient email address',
        'empty_note' => 'The note has neither a secret nor a text',
        'not_found' => 'Item not found',
        'not_allowed' => 'Access denied to this item',
        'email_not_allowed' => 'Email invitations are disabled',
        'notes_not_allowed' => 'Note sends are disabled',
        'passphrase_required' => 'A passphrase is required by the administrator policy',
        'cannot_decrypt' => 'The item cannot be decrypted with your keys yet. Retry once the encryption keys have been distributed.',
        'totp_unusable' => 'The TOTP of this item cannot be shared',
        'email_requires_https' => 'Email invitations require an HTTPS TeamPass address',
        'email_rate_limited' => 'Too many email invitations, retry later',
        'email_not_configured' => 'Email delivery is not configured',
        'invalid_public_url' => 'The public Secure Send address is not configured correctly',
        default => 'An internal error occurred. Please contact support.',
    };
}

/**
 * Shape the 201 body. The URL is a bearer secret: it must never be logged or stored.
 *
 * @param array $result secureSendCreateLink() result
 * @return array Response body
 */
function secureSendApiCreatedBody(array $result): array
{
    return [
        'id' => (int) $result['otv_id'],
        'type' => $result['send_type'] === 'note' ? 'note' : 'item',
        'url' => (string) $result['url'],
        // Notes are not bound to any item.
        'item_id' => $result['item_id'] === null ? null : (int) $result['item_id'],
        'expires_at' => (int) $result['expires_at'],
        'max_views' => (int) $result['max_views'],
        'has_passphrase' => (int) $result['has_passphrase'] === 1,
        'public_address' => (bool) $result['public_address'],
        'totp_included' => (bool) $result['totp_included'],
        'description_truncated' => (bool) $result['description_truncated'],
        'email_queued' => (bool) $result['email_queued'],
    ];
}
