<?php

declare(strict_types=1);

/** Secure Send sender input rules. Distributed under the GPL-3.0 license. */

/**
 * Reject malformed input and passphrases the recipient cannot submit.
 *
 * @param array $input Decrypted sender request
 * @return void
 * @throws InvalidArgumentException When a field is invalid
 */
function secureSendValidateInput(array $input): void
{
    foreach (['send_type', 'id', 'passphrase', 'shared_globaly', 'days', 'views'] as $field) {
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
