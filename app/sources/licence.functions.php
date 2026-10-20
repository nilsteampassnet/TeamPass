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
 * Adapters between TeamPass and the licence server: HTTP transport, RSA signature
 * verification, and persistence of the trial state in teampass_misc.
 *
 * Every decision lives in the DB-free app/sources/licence_trial_logic.php; this file only
 * performs the input/output. It is a plain functions file, not a *.queries.php endpoint, so
 * it needs no public/sources/ proxy shim. It is required by app/sources/admin.queries.php.
 *
 * @file      licence.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;

require_once __DIR__ . '/licence_trial_logic.php';

/** Production licence server. */
const LICENCE_SERVER_BASE_URL = 'https://licence.teampass.net';

/** Route of the self-service trial, used when discovery did not provide one. */
const LICENCE_TRIAL_DEFAULT_PATH = '/api/v1.2/trial.php';

/** Route returning the licence fact sheet (no seat is consumed by it). */
const LICENCE_INFO_PATH = '/api/v1.2/info.php';

/**
 * Human-facing page an administrator opens from a machine that has Internet access, when
 * this server has none. It performs, behind a captcha, the POST that this server could not
 * make: trial.php is POST-only JSON, so a link in an e-mail can never reach it directly.
 *
 * The licence key IS carried in this link. The destination is the licence server itself —
 * the legitimate holder of that token — and the alternative is asking an administrator to
 * copy 64 characters by hand off an air-gapped console, which is the very problem being
 * solved. The residue is the browser history of the machine that opens the link.
 */
const LICENCE_TRIAL_REQUEST_PATH = '/api/v1.2/trial-request.php';

/** Commercial contact offered on every terminal refusal. */
const LICENCE_CONTACT_EMAIL = 'contact@teampass.net';

/** Transport limits. */
const LICENCE_SERVER_TIMEOUT_CONNECT = 3;
const LICENCE_SERVER_TIMEOUT_TOTAL = 8;
const LICENCE_SERVER_MAX_RESPONSE_BYTES = 262144;

/**
 * Public key of the licence server, RSA 4096.
 *
 * Copied verbatim from _things/licence-server-api/LICENCE-SERVER-API-DOCUMENTATION.md
 * ("Clé publique"). SHA-256 of the DER form:
 * ac1c599e654c67770e193d0708a3461a46d0f71481f9f2ab8738c2723b7e9256 — the fingerprint served
 * by GET / can be compared against it to confirm this key belongs to the deployed server.
 */
const LICENCE_SERVER_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAwctSwUyHcTw1S+VK/pRL
GNzznVO5LqXMwrCpG167lLN5Z9J11m37yCcibJwkD0xD8101UpTNyIM0k3SA6hXA
jZdDIjdD9lHr7K4H8m2BO5/leLNOQIgXFGhbgX9/jLkdHv5zfp2+J9y9caHhRylL
EuMo9E/I9SGHSyMZrHHWln5EnOEeSann6jaYUSzk00naGHJGCsf97ZhUc8f1Tr8A
QFiwKpkf6HFqgCt5v1GFh2QA5bs+NyYmohNjqW/vHSGQFjD759CuEHWJZKAuFwa6
qQ3CoS6hUeRGWsDNlKvSL42RORPxLB3q4YVoMoVNAfKa84cA5kT1qe3HkHxw6Dy4
EMLsOu6Go682kw6lU5W79jnwDay4xoLrAZJqBmWQXWYmqf1ZIbhGO9B+lKdTeQ0N
iDGPqJZUFVb8FUmefgzT0j5FxnbpnyILuw4xr7pq1FEciICIveAWUVWH2QccFpw1
JZ2rAHCp7unbzt3yiktBRXKaiaCp8vHbtvnbIPYklBd6nVmzy/P7QkcZb0Twl3st
sotrYAERr03X1OuMjKVuyNsl/OC8BoR1xNM7xVjrP0mBRUWWpHi2MpEu6X1Bbz+a
Ii4JIBGfET9GyooUiu5C7gwl9w0xPbbJecfXdN+sUEOPaJXuRCItYrGDKJt/8O/X
11f6ebrIZZQ8C5T0tkEZiA8CAwEAAQ==
-----END PUBLIC KEY-----
PEM;

/**
 * SHA-256 of the embedded public key, in its DER form.
 *
 * This is the value GET / advertises under security.rsa_public_key_fingerprint, so the two can
 * be compared without transporting anything secret.
 *
 * @return string Lowercase hexadecimal digest, empty when the constant is unusable.
 */
function licenceServerPublicKeyFingerprint(): string
{
    $body = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', LICENCE_SERVER_PUBLIC_KEY);
    $der = base64_decode((string) $body, true);

    return $der === false || $der === '' ? '' : hash('sha256', $der);
}

/**
 * Resolve the licence server to talk to.
 *
 * licence_server_base_url is an escape hatch for staging: it is not exposed in the interface,
 * and only HTTPS — or a loopback address, for a local stub — is accepted, so it cannot be
 * turned into a plaintext exfiltration channel by someone who reached the settings table.
 *
 * @param array $SETTINGS TeamPass settings.
 *
 * @return string Base URL without a trailing slash.
 */
function licenceServerBaseUrl(array $SETTINGS): string
{
    $custom = rtrim(trim((string) ($SETTINGS['licence_server_base_url'] ?? '')), '/');

    if ($custom !== ''
        && preg_match('#^(https://[a-z0-9.\-]+|http://(127\.0\.0\.1|localhost))(:\d{1,5})?$#i', $custom) === 1
    ) {
        return $custom;
    }

    return LICENCE_SERVER_BASE_URL;
}

/**
 * Verify the RSA-SHA256 signature carried by X-Payload-Signature.
 *
 * The verification runs on the RAW body as it arrived. Re-encoding a decoded payload would
 * change the bytes — the server already sorted its keys before signing — and the check would
 * fail on perfectly valid answers (contract §7).
 *
 * @param string      $rawBody      Response body, untouched.
 * @param string      $signatureB64 Base64 header value.
 * @param string|null $publicKeyPem Alternative key, used by the unit tests.
 *
 * @return bool True when the body is authentic.
 */
function licenceVerifySignature(string $rawBody, string $signatureB64, ?string $publicKeyPem = null): bool
{
    if ($rawBody === '' || trim($signatureB64) === '' || function_exists('openssl_verify') === false) {
        return false;
    }

    $signature = base64_decode(trim($signatureB64), true);
    if ($signature === false || $signature === '') {
        return false;
    }

    $key = openssl_pkey_get_public($publicKeyPem ?? LICENCE_SERVER_PUBLIC_KEY);
    if ($key === false) {
        return false;
    }

    return openssl_verify($rawBody, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
}

/**
 * Perform one request against the licence server.
 *
 * Modelled on the hardened client of get_teampass_latest_release(), not on the licence
 * closures it replaces. Two differences matter:
 *   - CURLOPT_FAILONERROR is false: trial.php puts the whole refusal reason in its 4xx
 *     bodies, and dropping them would leave the administrator with a bare status code;
 *   - the response is capped, so a hostile or broken endpoint cannot exhaust the memory limit.
 *
 * @param string     $method   'GET' or 'POST'.
 * @param string     $path     Path starting with a slash.
 * @param array|null $payload  JSON body for POST.
 * @param array      $SETTINGS TeamPass settings.
 *
 * @return array {ok, http_code, raw_body, signature, signature_valid, json, network_error, error}
 */
function licenceHttpRequest(string $method, string $path, ?array $payload, array $SETTINGS): array
{
    $result = [
        'ok' => false,
        'http_code' => 0,
        'raw_body' => '',
        'signature' => '',
        'signature_valid' => false,
        'json' => null,
        'network_error' => true,
        'error' => '',
    ];

    if (function_exists('curl_init') === false) {
        $result['error'] = 'curl_missing';

        return $result;
    }

    $handle = curl_init(licenceServerBaseUrl($SETTINGS) . $path);
    if ($handle === false) {
        $result['error'] = 'curl_init_failed';

        return $result;
    }

    $body = '';
    $signature = '';
    $tooLarge = false;

    $options = [
        CURLOPT_CONNECTTIMEOUT => LICENCE_SERVER_TIMEOUT_CONNECT,
        CURLOPT_TIMEOUT => LICENCE_SERVER_TIMEOUT_TOTAL,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FAILONERROR => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'TeamPass/' . TP_VERSION . '.' . TP_VERSION_MINOR,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$signature): int {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2 && strcasecmp(trim($parts[0]), 'X-Payload-Signature') === 0) {
                $signature = trim($parts[1]);
            }

            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > LICENCE_SERVER_MAX_RESPONSE_BYTES) {
                $tooLarge = true;

                // Returning anything but the chunk length aborts the transfer.
                return 0;
            }

            $body .= $chunk;

            return strlen($chunk);
        },
    ];

    // Corporate proxies: these two settings are seeded at install and were consumed nowhere
    // before this feature. Without them an instance behind a proxy cannot reach the licence
    // server from PHP at all.
    $proxyIp = trim((string) ($SETTINGS['proxy_ip'] ?? ''));
    if ($proxyIp !== '') {
        $options[CURLOPT_PROXY] = $proxyIp;
        $proxyPort = trim((string) ($SETTINGS['proxy_port'] ?? ''));
        if ($proxyPort !== '') {
            $options[CURLOPT_PROXYPORT] = (int) $proxyPort;
        }
    }

    if (strtoupper($method) === 'POST') {
        $encoded = (string) json_encode($payload ?? [], JSON_UNESCAPED_SLASHES);
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $encoded;
        $options[CURLOPT_HTTPHEADER] = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($encoded),
        ];
    }

    curl_setopt_array($handle, $options);
    curl_exec($handle);
    $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($tooLarge === true) {
        $result['error'] = 'response_too_large';

        return $result;
    }

    if ($httpCode === 0) {
        $result['error'] = $curlError !== '' ? 'transport' : 'no_response';

        return $result;
    }

    $decoded = json_decode($body, true);

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'raw_body' => $body,
        'signature' => $signature,
        'signature_valid' => licenceVerifySignature($body, $signature),
        'json' => is_array($decoded) === true ? $decoded : null,
        'network_error' => false,
        'error' => '',
    ];
}

/**
 * Read a JSON-encoded admin setting.
 *
 * @param array  $SETTINGS TeamPass settings.
 * @param string $key      Setting name.
 *
 * @return array Decoded value, empty when unset or unusable.
 */
function licenceReadJsonSetting(array $SETTINGS, string $key): array
{
    $raw = (string) ($SETTINGS[$key] ?? '');
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) === true ? $decoded : [];
}

/**
 * Persist a JSON-encoded admin setting and drop the APCu settings cache.
 *
 * The pre-existing licence cache wrote teampass_misc directly and never invalidated, so the
 * stale value could be served for the whole APCu window. Everything here goes through the
 * shared writer instead.
 *
 * @param string $key   Setting name.
 * @param array  $value Value to store.
 *
 * @return void
 */
function licenceWriteJsonSetting(string $key, array $value): void
{
    teampassSaveAdminSetting($key, (string) json_encode($value, JSON_UNESCAPED_SLASHES));
    ConfigManager::invalidateCache();
}

/**
 * Read the persisted trial state of every product.
 *
 * @param array $SETTINGS TeamPass settings.
 *
 * @return array
 */
function licenceReadTrialState(array $SETTINGS): array
{
    return licenceReadJsonSetting($SETTINGS, 'licence_trial_state');
}

/**
 * Persist the trial state of every product.
 *
 * @param array $state Whole state.
 *
 * @return void
 */
function licenceWriteTrialState(array $state): void
{
    licenceWriteJsonSetting('licence_trial_state', $state);
}

/**
 * Fetch, cache and normalise the discovery document served by GET /.
 *
 * The signature is checked when the header is present but its absence is not fatal here: this
 * document decides whether a button is displayed, never whether a licence is valid. Every
 * answer that carries licence state — trial.php and info.php — is verified strictly.
 *
 * On a failed attempt the last known document is kept so a transient outage does not make the
 * feature vanish; only 'server_online' is forced to false, which is what the interface reacts
 * to.
 *
 * @param array $SETTINGS TeamPass settings.
 * @param bool  $force    Ignore the cache.
 *
 * @return array Output of licenceTrialParseDiscovery(), with server_online reflecting the
 *               last actual attempt.
 */
function licenceDiscover(array $SETTINGS, bool $force = false): array
{
    $now = time();
    $cache = licenceReadJsonSetting($SETTINGS, 'licence_server_discovery');
    $fetchedAt = (int) ($cache['fetched_at'] ?? 0);
    $reachable = (bool) ($cache['reachable'] ?? false);
    $payload = isset($cache['payload']) === true && is_array($cache['payload']) === true
        ? $cache['payload']
        : null;

    $ttl = $reachable === true ? LICENCE_DISCOVERY_TTL : LICENCE_INFO_TTL_OFFLINE;
    if ($force === false && $fetchedAt > 0 && ($now - $fetchedAt) < $ttl) {
        $parsed = licenceTrialParseDiscovery($payload);
        $parsed['server_online'] = $reachable === true && $parsed['server_online'] === true;

        return $parsed;
    }

    $response = licenceHttpRequest('GET', '/', null, $SETTINGS);
    $succeeded = $response['ok'] === true && is_array($response['json']) === true;

    licenceWriteJsonSetting('licence_server_discovery', [
        'fetched_at' => $now,
        'reachable' => $succeeded,
        // Keep the previous document when the call failed.
        'payload' => $succeeded === true ? $response['json'] : $payload,
    ]);

    $parsed = licenceTrialParseDiscovery($succeeded === true ? $response['json'] : $payload);
    $parsed['server_online'] = $succeeded === true && $parsed['server_online'] === true;

    return $parsed;
}

/**
 * Tell whether the licence server still signs with the key embedded in this TeamPass.
 *
 * An unadvertised fingerprint is not a mismatch: not knowing is not knowing it is wrong.
 *
 * @param array $discovery Output of licenceTrialParseDiscovery().
 *
 * @return bool False only when both fingerprints are known and differ.
 */
function licenceServerKeyFingerprintMatches(array $discovery): bool
{
    $advertised = (string) ($discovery['public_key_fingerprint'] ?? '');
    if ($advertised === '') {
        return true;
    }

    return hash_equals(licenceServerPublicKeyFingerprint(), $advertised);
}

/**
 * Fetch, cache and normalise the licence fact sheet served by info.php.
 *
 * Budget (contract §5): the licence server allows 10 calls per hour on this bucket and asks
 * for 6 at most. One shared counter covers every caller — the dashboard widget, the Licence
 * tab and the manual button — because they all hit the same bucket. A cache hit never
 * consumes it, and a request that never reached the server does not either: the licence
 * server counts what it receives, and the offline interval is what throttles those.
 *
 * Asks for the seat history at no cost: same call, same budget; an older server ignores the field.
 *
 * @param array      $SETTINGS  TeamPass settings.
 * @param bool       $force     Bypass the freshness window (still subject to the budget).
 * @param array|null $discovery Already-resolved discovery, to avoid fetching GET / twice in
 *                              the same request when the cache is cold.
 *
 * @return array {fetched_at, server_online, server_version, http_code, signature_valid,
 *                untrusted, info, from_cache, budget_exhausted, retry_after, no_licence_key}
 */
function licenceFetchInfo(array $SETTINGS, bool $force = false, ?array $discovery = null): array
{
    $now = time();
    $token = (string) ($SETTINGS['browser_extension_key'] ?? '');
    $fqdn = licenceTrialNormalizeFqdn((string) ($SETTINGS['browser_extension_fqdn'] ?? ''));

    $cache = licenceReadJsonSetting($SETTINGS, 'extension_licence_cache');
    $cachedAt = (int) ($SETTINGS['extension_licence_cache_at'] ?? 0);
    $cached = [
        'fetched_at' => $cachedAt,
        'server_online' => (bool) ($cache['server_online'] ?? false),
        'server_version' => (string) ($cache['server_version'] ?? ''),
        'http_code' => (int) ($cache['http_code'] ?? 0),
        'signature_valid' => (bool) ($cache['signature_valid'] ?? false),
        'untrusted' => (bool) ($cache['untrusted'] ?? false),
        'info' => isset($cache['info']) === true && is_array($cache['info']) === true
            ? $cache['info']
            : licenceTrialNormalizeInfo(null),
        'from_cache' => true,
        'budget_exhausted' => false,
        'retry_after' => 0,
        'no_licence_key' => false,
    ];

    if ($token === '' || $fqdn === '') {
        return array_merge($cached, ['no_licence_key' => true, 'from_cache' => true]);
    }

    $state = licenceReadTrialState($SETTINGS);
    $pending = false;
    foreach (LICENCE_TRIAL_PRODUCTS as $product) {
        $entry = licenceTrialExpirePending(licenceTrialStateForProduct($state, $product), $now);
        if ($entry['state'] === LICENCE_TRIAL_STATE_PENDING) {
            $pending = true;
        }
    }

    $ttl = licenceTrialInfoTtl($pending, (bool) $cached['server_online']);
    if ($force === false && $cachedAt > 0 && ($now - $cachedAt) < $ttl) {
        return $cached;
    }

    $budget = licenceReadJsonSetting($SETTINGS, 'licence_info_budget');
    if (licenceTrialBudgetAllows($budget, $now) === false) {
        return array_merge($cached, [
            'budget_exhausted' => true,
            'retry_after' => licenceTrialBudgetRetryAfter($budget, $now),
        ]);
    }

    if ($discovery === null) {
        $discovery = licenceDiscover($SETTINGS);
    }

    if ($discovery['server_online'] === false) {
        $result = array_merge($cached, [
            'fetched_at' => $now,
            'server_online' => false,
            'server_version' => $discovery['server_version'],
            'from_cache' => false,
        ]);
        licenceStoreInfoCache($result);

        return $result;
    }

    $response = licenceHttpRequest(
        'POST',
        LICENCE_INFO_PATH,
        ['instance_fqdn' => $fqdn, 'license_token' => $token, 'history_days' => LICENCE_SEATS_HISTORY_DAYS],
        $SETTINGS
    );

    if ($response['network_error'] === true) {
        $result = array_merge($cached, [
            'fetched_at' => $now,
            'server_online' => false,
            'from_cache' => false,
        ]);
        licenceStoreInfoCache($result);

        return $result;
    }

    // The request reached the server, so it counted against the real rate limit.
    licenceWriteJsonSetting('licence_info_budget', licenceTrialBudgetConsume($budget, $now));

    // A body that does not verify is discarded whatever it says.
    $untrusted = $response['signature_valid'] === false;

    $result = [
        'fetched_at' => $now,
        'server_online' => true,
        'server_version' => $discovery['server_version'],
        'http_code' => $response['http_code'],
        'signature_valid' => $response['signature_valid'],
        'untrusted' => $untrusted,
        'info' => $untrusted === true
            ? licenceTrialNormalizeInfo(null)
            : licenceTrialNormalizeInfo($response['json']),
        'from_cache' => false,
        'budget_exhausted' => false,
        'retry_after' => 0,
        'no_licence_key' => false,
    ];

    licenceStoreInfoCache($result);

    return $result;
}

/**
 * Persist the info.php cache, keeping the two historical setting names.
 *
 * @param array $result Output of licenceFetchInfo().
 *
 * @return void
 */
function licenceStoreInfoCache(array $result): void
{
    teampassSaveAdminSetting('extension_licence_cache', (string) json_encode([
        'server_online' => $result['server_online'],
        'server_version' => $result['server_version'],
        'http_code' => $result['http_code'],
        'signature_valid' => $result['signature_valid'],
        'untrusted' => $result['untrusted'],
        'info' => $result['info'],
    ], JSON_UNESCAPED_SLASHES));
    teampassSaveAdminSetting('extension_licence_cache_at', (string) $result['fetched_at']);
    ConfigManager::invalidateCache();
}

/**
 * Ask the licence server for a trial.
 *
 * Only the five documented fields are sent: the endpoint rejects anything it does not know
 * rather than ignoring it (contract §2), so an extra key is a 422, not a warning.
 *
 * @param array  $SETTINGS     TeamPass settings.
 * @param string $product      'extension' or 'mobile_app'.
 * @param string $contactEmail Contact address, must be on the FQDN domain.
 * @param string $trialPath    Route advertised by discovery.
 *
 * @return array Output of licenceTrialClassifyResponse(), plus 'fqdn' and 'contact_email'.
 */
function licenceRequestTrial(
    array $SETTINGS,
    string $product,
    string $contactEmail,
    string $trialPath = LICENCE_TRIAL_DEFAULT_PATH
): array {
    $fqdn = licenceTrialNormalizeFqdn((string) ($SETTINGS['browser_extension_fqdn'] ?? ''));
    $token = (string) ($SETTINGS['browser_extension_key'] ?? '');

    $response = licenceHttpRequest(
        'POST',
        $trialPath,
        [
            'instance_fqdn' => $fqdn,
            'license_token' => $token,
            'contact_email' => $contactEmail,
            'product' => $product,
            'teampass_version' => TP_VERSION . '.' . TP_VERSION_MINOR,
        ],
        $SETTINGS
    );

    $classified = licenceTrialClassifyResponse(
        $response['http_code'],
        $response['json'],
        $response['signature_valid'],
        $response['network_error']
    );

    $classified['fqdn'] = $fqdn;
    $classified['contact_email'] = $contactEmail;

    return $classified;
}

/**
 * Build the prefilled request link handed to the administrator when this server has no
 * outbound access.
 *
 * Everything trial.php needs travels in the URL, the licence key included — see
 * LICENCE_TRIAL_REQUEST_PATH for why. The destination page never submits on its own: mail
 * security gateways and antivirus link scanners follow links, and an auto-submit would
 * consume the one and only trial before the administrator opened the message.
 *
 * @param array  $SETTINGS TeamPass settings (for the staging base URL override).
 * @param string $fqdn     Instance FQDN.
 * @param string $email    Contact e-mail.
 * @param string $token    Licence key.
 * @param string $product  Product.
 *
 * @return string
 */
function licenceTrialOfflineRequestUrl(
    array $SETTINGS,
    string $fqdn,
    string $email,
    string $token,
    string $product
): string {
    $parameters = [
        'fqdn' => $fqdn,
        'email' => $email,
        'token' => $token,
        'product' => $product,
    ];

    // Metadata only, never blocking (contract §2): omitted rather than sent empty when the
    // version constants are not loaded.
    if (defined('TP_VERSION') === true && defined('TP_VERSION_MINOR') === true) {
        $parameters['version'] = TP_VERSION . '.' . TP_VERSION_MINOR;
    }

    return licenceServerBaseUrl($SETTINGS) . LICENCE_TRIAL_REQUEST_PATH . '?' . http_build_query($parameters);
}

/**
 * Record that an offline request link was e-mailed, so the panel can say so afterwards.
 *
 * This is not a state transition: nothing was requested yet, and the licence server knows
 * nothing about it. It is only a trace of what this instance handed over, and to whom.
 *
 * @param array  $SETTINGS TeamPass settings.
 * @param string $product  Product the link was built for.
 * @param string $email    Address the link was sent to.
 * @param int    $now      Current timestamp.
 *
 * @return void
 */
function licenceMarkOfflineLinkSent(array $SETTINGS, string $product, string $email, int $now): void
{
    $state = licenceReadTrialState($SETTINGS);
    $entry = licenceTrialStateForProduct($state, $product);

    $entry['offline_link_sent_at'] = $now;
    $entry['offline_link_sent_to'] = $email;

    $state[$product] = $entry;
    licenceWriteTrialState($state);
}

/**
 * Assemble everything the Licence tab needs, in one object.
 *
 * @param array  $SETTINGS     TeamPass settings.
 * @param string $defaultEmail Address to prefill the form with (the administrator's).
 * @param bool   $force        Force a refresh of the licence fact sheet.
 * @param string $product      Product the panel is about.
 *
 * @return array View model.
 */
function licenceBuildPanelViewModel(
    array $SETTINGS,
    string $defaultEmail,
    bool $force = false,
    string $product = LICENCE_TRIAL_DEFAULT_PRODUCT
): array {
    $now = time();
    $discovery = licenceDiscover($SETTINGS);
    $info = licenceFetchInfo($SETTINGS, $force, $discovery);
    $state = licenceReadTrialState($SETTINGS);

    $fqdn = (string) ($SETTINGS['browser_extension_fqdn'] ?? '');
    $token = (string) ($SETTINGS['browser_extension_key'] ?? '');

    // A licence call that failed contradicts a discovery answer cached hours ago. But when
    // there is no key or no FQDN yet, no call was attempted at all: reporting the server as
    // unreachable would hide the real problem, which is the missing identity.
    if ($info['no_licence_key'] === false) {
        $discovery['server_online'] = $discovery['server_online'] === true
            && $info['server_online'] === true;
    }

    $vm = licenceTrialResolveDisplay($state, $info['info'], $discovery, $now, $fqdn, $token, $product);

    // A body we could not authenticate must never look like a normal answer.
    // An unusable identity stays the message: it is the blocker, and the offline request
    // link cannot be built from an FQDN the licence server would refuse anyway.
    $vm['key_rotated'] = $vm['panel'] !== LICENCE_PANEL_INVALID_FQDN
        && licenceServerKeyFingerprintMatches($discovery) === false;
    if ($vm['panel'] !== LICENCE_PANEL_INVALID_FQDN
        && ($info['untrusted'] === true || $vm['key_rotated'] === true)
    ) {
        $vm['panel'] = LICENCE_PANEL_UNREACHABLE;
        $vm['untrusted'] = true;
    } else {
        $vm['untrusted'] = false;
    }

    // The address the administrator last used for the offline link is a better suggestion than
    // the account address: the panel is re-rendered right after the message is sent, and the
    // suggestion is what the offline link is built on.
    $email = $vm['contact_email'] !== '' ? $vm['contact_email'] : (string) $vm['offline_link_sent_to'];
    if ($email === '') {
        $email = $defaultEmail;
    }

    $vm['contact_email_suggestion'] = $email;
    $vm['email_domain_aligned'] = $email === ''
        || licenceTrialEmailDomainLooksAligned($email, $vm['fqdn']);
    $vm['instance_domain'] = licenceTrialRegistrableGuess($vm['fqdn']);
    $vm['offline_url'] = licenceTrialOfflineRequestUrl($SETTINGS, $vm['fqdn'], $email, $token, $product);
    $vm['contact_url'] = 'mailto:' . LICENCE_CONTACT_EMAIL;
    $vm['contact_email_support'] = LICENCE_CONTACT_EMAIL;
    $vm['budget_exhausted'] = (bool) $info['budget_exhausted'];
    $vm['budget_retry_after'] = (int) $info['retry_after'];
    $vm['checked_at'] = (int) $info['fetched_at'];
    $vm['no_licence_key'] = (bool) $info['no_licence_key'];

    $dateFormat = ($SETTINGS['date_format'] ?? 'd/m/Y') . ' ' . ($SETTINGS['time_format'] ?? 'H:i');
    $vm['link_expires_at_display'] = $vm['link_expires_at'] > 0
        ? date($dateFormat, $vm['link_expires_at'])
        : '';
    $vm['offline_link_sent_display'] = $vm['offline_link_sent_at'] > 0
        ? date($dateFormat, $vm['offline_link_sent_at'])
        : '';
    $vm['licence']['expiration_display'] = licenceTrialFormatServerDay(
        (string) $vm['licence']['expiration_date'],
        (string) ($SETTINGS['date_format'] ?? 'd/m/Y')
    );

    return $vm;
}
