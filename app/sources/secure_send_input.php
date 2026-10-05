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
 * @file      secure_send_input.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Reject malformed input and passphrases the recipient cannot submit.
 *
 * @param array $input Decrypted sender request
 * @return void
 * @throws InvalidArgumentException When a field is invalid
 */
function secureSendValidateInput(array $input): void
{
    foreach (['send_type', 'id', 'passphrase', 'shared_globaly', 'include_totp', 'days', 'views'] as $field) {
        if (isset($input[$field]) && !is_scalar($input[$field])) {
            throw new InvalidArgumentException('invalid_payload');
        }
    }
    if (!in_array($input['send_type'] ?? 'item', ['item', 'note'], true)
        || strlen((string) ($input['passphrase'] ?? '')) > 1024
    ) {
        throw new InvalidArgumentException('invalid_payload');
    }
    if (($input['send_type'] ?? 'item') !== 'note') {
        return;
    }
    if (isset($input['payload']) && !is_array($input['payload'])) {
        throw new InvalidArgumentException('invalid_payload');
    }
    $payload = $input['payload'] ?? [];
    foreach (['title', 'secret', 'note', 'login', 'url'] as $field) {
        if (isset($payload[$field]) && (!is_scalar($payload[$field])
            || !mb_check_encoding((string) $payload[$field], 'UTF-8'))
        ) {
            throw new InvalidArgumentException('invalid_payload');
        }
    }
}

/**
 * Decide whether an authorized item snapshot may include its enabled TOTP profile.
 *
 * @param array $input Validated sender request
 * @param bool $readOnly Whether the sender has a read-only account
 * @return bool True only for an explicit opt-in from an eligible sender
 */
function secureSendShouldIncludeTotp(array $input, bool $readOnly): bool
{
    return $readOnly === false && ($input['include_totp'] ?? 0) === 1;
}

/**
 * Bound requested validity and views to administrator policy and integer storage.
 *
 * @param array $settings Application settings
 * @param array $input Validated sender request
 * @param int $now Creation timestamp
 * @return array{days:int, views:int, time_limit:int}
 */
function secureSendLimits(array $settings, array $input, int $now): array
{
    $maxDays = (int) ($settings['otv_expiration_period'] ?? 7);
    $maxDays = min(2147483647, $maxDays > 0 ? $maxDays : 7);
    $maxViews = min(2147483647, max(1, (int) ($settings['secure_send_max_views'] ?? 5)));
    $days = max(1, min($maxDays, (int) ($input['days'] ?? $maxDays)));
    return [
        'days' => $days,
        'views' => max(1, min($maxViews, (int) ($input['views'] ?? 1))),
        'time_limit' => $now + $days * 86400,
    ];
}
