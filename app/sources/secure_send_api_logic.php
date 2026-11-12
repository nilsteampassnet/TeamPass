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
 * Decisions of the Secure Send REST endpoints (GET /secureSend/policy, POST /secureSend/create).
 * DB-free: the creation itself is secureSendCreateLink(), shared with the web handler.
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
        'require_passphrase' => (int) ($settings['secure_send_require_passphrase'] ?? 0) === 1,
        'passphrase_max_bytes' => 1024,
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
 * Only item sends exist in this version: send_type is forced, so notes stay unreachable.
 * A null optional member counts as omitted.
 *
 * @param array $body Decoded JSON object
 * @return array Input for secureSendCreateLink()
 * @throws InvalidArgumentException With a client-facing message (HTTP 400)
 */
function secureSendApiCreateInput(array $body): array
{
    $allowed = ['item_id', 'days', 'views', 'passphrase', 'include_totp', 'public_address', 'recipient_email'];
    foreach (array_keys($body) as $member) {
        if (!in_array($member, $allowed, true)) {
            throw new InvalidArgumentException('Unknown member: ' . substr((string) $member, 0, 64));
        }
    }
    if (!is_int($body['item_id'] ?? null) || $body['item_id'] <= 0) {
        throw new InvalidArgumentException('item_id is mandatory and must be a positive integer');
    }
    $input = ['send_type' => 'item', 'id' => $body['item_id']];
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
    foreach (['include_totp' => 'include_totp', 'public_address' => 'shared_globaly'] as $member => $internal) {
        $value = $body[$member] ?? false;
        if (!is_bool($value) && $value !== 0 && $value !== 1) {
            throw new InvalidArgumentException($member . ' must be a boolean');
        }
        $input[$internal] = ($value === true || $value === 1) ? 1 : 0;
    }

    return $input;
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
        'empty_note' => 'The note is empty',
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
        'url' => (string) $result['url'],
        'item_id' => (int) $result['item_id'],
        'expires_at' => (int) $result['expires_at'],
        'max_views' => (int) $result['max_views'],
        'has_passphrase' => (int) $result['has_passphrase'] === 1,
        'public_address' => (bool) $result['public_address'],
        'totp_included' => (bool) $result['totp_included'],
        'description_truncated' => (bool) $result['description_truncated'],
        'email_queued' => (bool) $result['email_queued'],
    ];
}
