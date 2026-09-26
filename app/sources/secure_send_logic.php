<?php

declare(strict_types=1);

/** Secure Send recipient and confirmation rules. Distributed under the GPL-3.0 license. */

/**
 * Choose the recipient language without relying on authenticated-page initialization.
 *
 * @param string|null $sessionLanguage Existing visitor preference, if any
 * @param array $settings Application settings, including the instance default
 * @return string Catalog name, falling back to English only when both choices are empty
 */
function secureSendRecipientLanguage(?string $sessionLanguage, array $settings): string
{
    foreach ([$sessionLanguage, $settings['default_language'] ?? null] as $language) {
        if (is_string($language) && trim($language) !== '') {
            return trim($language);
        }
    }
    return 'english';
}

/**
 * Check link state before prompting and again when reserving a view.
 *
 * @param array $link Stored link
 * @param array $settings Application settings
 * @param int $now Current timestamp
 * @return bool Whether the link is still usable
 */
function secureSendIsAvailable(array $link, array $settings, int $now): bool
{
    return (int) ($settings['otv_is_enabled'] ?? 0) === 1
        && (int) ($link['time_limit'] ?? 0) > $now
        && (int) ($link['views'] ?? 0) < (int) ($link['max_views'] ?? 0)
        && (int) ($link['failed_attempts'] ?? 0) < 5
        && ((int) ($link['has_passphrase'] ?? 0) !== 1 || !empty($link['protected_key']))
        && in_array($link['send_type'] ?? 'item', ['item', 'note'], true)
        && (($link['send_type'] ?? 'item') !== 'note' || (int) ($settings['secure_send_allow_notes'] ?? 0) === 1)
        && ((int) ($settings['secure_send_require_passphrase'] ?? 0) !== 1 || (int) ($link['has_passphrase'] ?? 0) === 1);
}

/**
 * Bind a confirmation token to one link and its exact credentials.
 *
 * @param array $parameters Code, key and timestamp from the request
 * @return string Session map key (never contains a plaintext link secret)
 */
function secureSendConfirmationId(array $parameters): string
{
    return hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR));
}

/**
 * Read bounded scalar credentials, rejecting PHP array parameters and malformed timestamps.
 *
 * @param array $input Query or form input
 * @return array{code:string, key:string, stamp:string}|null
 */
function secureSendRequestParameters(array $input): ?array
{
    $result = [];
    foreach (['code' => 100, 'key' => 1024, 'stamp' => 20] as $name => $limit) {
        if (!isset($input[$name]) || !is_scalar($input[$name])) {
            return null;
        }
        $result[$name] = (string) $input[$name];
        if ($result[$name] === '' || strlen($result[$name]) > $limit || preg_match('/[\x00-\x20\x7f]/', $result[$name]) === 1) {
            return null;
        }
    }
    if (filter_var($result['stamp'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return null;
    }
    return $result;
}
