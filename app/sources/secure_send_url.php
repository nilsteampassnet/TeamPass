<?php

declare(strict_types=1);

/**
 * Address rules for One-Time View / Secure Send.
 *
 * This file is part of TeamPass, distributed under the GPL-3.0 license.
 */

/**
 * Validate an absolute base URL without credentials, query or fragment.
 *
 * @param string $url Configured application address
 * @param bool $public Whether HTTPS is mandatory
 * @return string Normalized base URL, without a trailing slash
 */
function secureSendNormalizeBaseUrl(string $url, bool $public = false): string
{
    $url = trim($url);
    if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
        throw new InvalidArgumentException('invalid_public_url');
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host']) || empty($parts['scheme'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || !in_array(strtolower($parts['scheme']), $public ? ['https'] : ['https', 'http'], true)
    ) {
        throw new InvalidArgumentException('invalid_public_url');
    }
    $host = strtolower(rtrim($parts['host'], '.'));
    if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false
        && filter_var($host, FILTER_VALIDATE_DOMAIN, $public ? FILTER_FLAG_HOSTNAME : 0) === false
    ) {
        throw new InvalidArgumentException('invalid_public_url');
    }
    $path = $parts['path'] ?? '';
    // Reject encoded separators/control bytes and dot segments as well as their literal forms.
    $decodedPath = rawurldecode($path);
    if (preg_match('/[\x00-\x20\x7f\\\\?#]/', $decodedPath) === 1
        || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $decodedPath) === 1
        || str_contains($decodedPath, '//')
    ) {
        throw new InvalidArgumentException('invalid_public_url');
    }
    return strtolower($parts['scheme']) . '://' . $host
        . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($path, '/');
}

/**
 * Resolve the public address, retaining support for the historical single-label prefix.
 *
 * A hostname inherits the main URL's path/port. An absolute HTTPS URL is independent.
 * No DNS or HTTP request is made: an invalid configuration must never silently fall back.
 *
 * @param array $settings Application settings
 * @param bool $public Use the configured public address
 * @return string Validated base URL
 */
function secureSendBaseUrl(array $settings, bool $public): string
{
    if (!$public) {
        return secureSendNormalizeBaseUrl((string) ($settings['cpassman_url'] ?? ''));
    }
    $configured = trim((string) ($settings['otv_subdomain'] ?? ''));
    if ($configured === '') {
        throw new InvalidArgumentException('invalid_public_url');
    }
    if (str_contains($configured, '://')) {
        return secureSendNormalizeBaseUrl($configured, true);
    }
    if (preg_match('/[\x00-\x20\x7f\\\\\/:@?#]/', $configured) === 1
        || filter_var(rtrim($configured, '.'), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
    ) {
        throw new InvalidArgumentException('invalid_public_url');
    }
    $main = parse_url(secureSendNormalizeBaseUrl((string) ($settings['cpassman_url'] ?? '')));
    $host = $configured;
    if (!str_contains($configured, '.')) {
        if (filter_var(trim($main['host'], '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new InvalidArgumentException('invalid_public_url');
        }
        $host = $configured . '.' . (str_starts_with($main['host'], 'www.') ? substr($main['host'], 4) : $main['host']);
    }
    return secureSendNormalizeBaseUrl('https://' . $host
        . (isset($main['port']) ? ':' . $main['port'] : '') . ($main['path'] ?? ''), true);
}

/**
 * Build a link exclusively from administrator-controlled addresses.
 *
 * @param array $settings Application settings
 * @param bool $public Use the public address
 * @param array $parameters Link credentials
 * @return string Link URL
 */
function secureSendUrl(array $settings, bool $public, array $parameters): string
{
    return secureSendBaseUrl($settings, $public) . '/index.php?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Enforce the configured hostname only for public links.
 *
 * Internal links retain compatibility with proxy rewrites, aliases and LAN names.
 * Read the actual Host header; never trust arbitrary forwarded-host headers.
 *
 * @param array $settings Application settings
 * @param array $link Stored link
 * @param string $hostHeader Raw HTTP Host, possibly containing a port
 * @return bool Whether the request can redeem this link
 */
function secureSendHostIsAllowed(array $settings, array $link, string $hostHeader): bool
{
    if ((int) ($link['shared_globaly'] ?? 0) !== 1) {
        return true;
    }
    try {
        if ($hostHeader === '' || preg_match('~[/@?#\s\\\\]~', $hostHeader) === 1) {
            return false;
        }
        $actual = parse_url(secureSendNormalizeBaseUrl('https://' . $hostHeader, true), PHP_URL_HOST);
        $expected = parse_url(secureSendBaseUrl($settings, true), PHP_URL_HOST);
        return is_string($actual) && is_string($expected) && hash_equals($expected, $actual);
    } catch (InvalidArgumentException $e) {
        return false;
    }
}
