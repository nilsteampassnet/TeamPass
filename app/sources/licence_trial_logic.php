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
 * Decision logic of the self-service licence trial, kept free of any database, session or
 * network access so it can be unit-tested on its own — same pattern as item_revisions_logic.php.
 *
 * It is included by both:
 *   - app/sources/licence.functions.php        (production adapters: HTTP + teampass_misc)
 *   - tests/Unit/LicenceTrialLogicTest.php     (unit tests)
 *
 * The client contract this implements is workReadmeFiles/CLIENT-INTEGRATION-TRIAL.md. That
 * document is authoritative and is newer than the server-side reference in
 * _things/licence-server-api/LICENCE-SERVER-API-DOCUMENTATION.md, which predates the
 * e-mail confirmation flow.
 *
 * @file      licence_trial_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Products the licence server can grant a trial for. Only 'extension' is surfaced in the
 * administration page today; the state and the handlers are keyed by product so adding
 * 'mobile_app' is a user-interface change only.
 */
const LICENCE_TRIAL_PRODUCTS = ['extension', 'mobile_app'];

/** Default product when the caller does not say. Matches the licence server default. */
const LICENCE_TRIAL_DEFAULT_PRODUCT = 'extension';

/**
 * Budget of info.php calls. The licence server allows 10 per hour and per bucket; the client
 * contract (§5) asks for 6 at most so that a manual check never collides with an automatic
 * refresh. The window opens on the first call, it is not a calendar hour.
 */
const LICENCE_INFO_MAX_CALLS_PER_HOUR = 6;
const LICENCE_INFO_BUDGET_WINDOW = 3600;

/** Automatic info.php refresh intervals, in seconds. */
const LICENCE_INFO_TTL_PENDING = 900;   // 15 min while a confirmation is pending (<= 4/hour)
const LICENCE_INFO_TTL_ONLINE = 3600;   // the pre-existing behaviour when all is settled
const LICENCE_INFO_TTL_OFFLINE = 600;   // shorter retry when the server did not answer

/** Discovery (GET /) cache lifetime. */
const LICENCE_DISCOVERY_TTL = 21600;    // 6 hours

/** A trial expiring within this many days raises the no-grace-period warning. */
const LICENCE_TRIAL_EXPIRY_WARNING_DAYS = 7;

/** Days of seat history the dashboard widget asks info.php for (today included). */
const LICENCE_SEATS_HISTORY_DAYS = 31;

/** Points kept from an answer whatever the server sent; matches the server-side clamp. */
const LICENCE_SEATS_HISTORY_MAX_POINTS = 62;

/** Persisted trial states, per product. */
const LICENCE_TRIAL_STATE_NONE = 'none';
const LICENCE_TRIAL_STATE_PENDING = 'pending';
const LICENCE_TRIAL_STATE_GRANTED = 'granted';
const LICENCE_TRIAL_STATE_REFUSED = 'refused';

/** Outcomes returned by licenceTrialClassifyResponse(). */
const LICENCE_OUTCOME_GRANTED = 'granted';
const LICENCE_OUTCOME_PENDING = 'pending';
const LICENCE_OUTCOME_REFUSED_PERMANENT = 'refused_permanent';
const LICENCE_OUTCOME_REFUSED_RETRY = 'refused_retry';
const LICENCE_OUTCOME_DISABLED = 'disabled';
const LICENCE_OUTCOME_UNREACHABLE = 'unreachable';
const LICENCE_OUTCOME_UNTRUSTED = 'untrusted';

/** Panels the Licence tab can render. */
const LICENCE_PANEL_NONE = 'none';                  // no licence, trial can be requested
const LICENCE_PANEL_INVALID_FQDN = 'invalid_fqdn';  // the FQDN would burn the one-shot trial
const LICENCE_PANEL_PENDING = 'pending';            // waiting for the confirmation e-mail
const LICENCE_PANEL_GRANTED = 'granted';            // a licence is active (trial or paid)
const LICENCE_PANEL_REFUSED = 'refused';            // terminal refusal
const LICENCE_PANEL_UNREACHABLE = 'unreachable';    // this server cannot reach the licence server
const LICENCE_PANEL_CLOSED = 'closed';              // trials are not offered by this server

/**
 * Normalise a host the way the licence server does before storing it.
 *
 * Trailing dots, surrounding whitespace and case are removed. Nothing else: a value carrying
 * a scheme, a port or a path must fail validation rather than be silently repaired, because
 * the FQDN has to be byte-identical in the trial request, in the licence and in every later
 * validation the extension performs (contract §9.1).
 *
 * @param string $fqdn Raw value read from the settings.
 *
 * @return string Normalised host.
 */
function licenceTrialNormalizeFqdn(string $fqdn): string
{
    return strtolower(trim(trim($fqdn), '.'));
}

/**
 * Tell whether a host can safely be used as instance_fqdn.
 *
 * A trial is granted once per (FQDN, product), forever. browser_extension_fqdn legitimately
 * holds 'localhost' or a subfolder name on local installs (getDomainFromSettingsUrl() returns
 * the first path segment there), and sending that would spend the only trial the instance
 * will ever get on a meaningless name. So the rule is deliberately strict: at least two
 * letter-digit-hyphen labels, no bare IP address, no scheme, no port, no path.
 *
 * @param string $fqdn Host to check, already normalised or not.
 *
 * @return bool True when the host may be sent to the licence server.
 */
function licenceTrialIsValidFqdn(string $fqdn): bool
{
    $host = licenceTrialNormalizeFqdn($fqdn);

    if ($host === '' || strlen($host) > 255) {
        return false;
    }

    // A bare address identifies a machine, not an organisation, and cannot be proven.
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return false;
    }

    return (bool) preg_match(
        '/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/',
        $host
    );
}

/**
 * Tell whether the licence key is within the bounds the licence server accepts.
 *
 * Contract §2: 16 to 255 printable ASCII characters, no space.
 *
 * @param string $token Licence key generated by this TeamPass instance.
 *
 * @return bool True when the key may be sent.
 */
function licenceTrialIsValidToken(string $token): bool
{
    return (bool) preg_match('/^[\x21-\x7E]{16,255}$/', $token);
}

/**
 * Best-effort guess of the registrable domain of a host.
 *
 * This is the last two labels, nothing more: TeamPass ships no public suffix list, so the
 * guess is wrong for hosts under a multi-label suffix. It is only ever used to *warn* the
 * administrator, never to refuse a request — the licence server owns that rule and answers
 * EMAIL_DOMAIN_MISMATCH when it is broken.
 *
 * @param string $host Host or e-mail domain.
 *
 * @return string The last two labels, or the host itself when it has fewer.
 */
function licenceTrialRegistrableGuess(string $host): string
{
    $labels = explode('.', licenceTrialNormalizeFqdn($host));
    $count = count($labels);

    if ($count < 2) {
        return implode('.', $labels);
    }

    return $labels[$count - 2] . '.' . $labels[$count - 1];
}

/**
 * Tell whether an e-mail address plausibly belongs to the domain of the instance.
 *
 * Warning only — see licenceTrialRegistrableGuess().
 *
 * @param string $email Contact e-mail address.
 * @param string $fqdn  Instance FQDN.
 *
 * @return bool False when the two registrable-domain guesses differ.
 */
function licenceTrialEmailDomainLooksAligned(string $email, string $fqdn): bool
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return false;
    }

    $emailDomain = licenceTrialRegistrableGuess(substr($email, $at + 1));
    $instanceDomain = licenceTrialRegistrableGuess($fqdn);

    if ($emailDomain === '' || $instanceDomain === '') {
        return false;
    }

    return $emailDomain === $instanceDomain;
}

/**
 * Read the discovery document served by GET / on the licence server.
 *
 * The absence of the 'trial' endpoint means self-service trials are closed on that server —
 * the whole block must then be hidden. The contract insists this is read from the server and
 * never hard-coded: it is the mechanism used to roll the feature back without a redeployment.
 *
 * @param array|null $json Decoded body, or null when the body was unusable.
 *
 * @return array Normalised discovery description.
 */
function licenceTrialParseDiscovery(?array $json): array
{
    $trial = [];
    if (is_array($json) === true
        && isset($json['endpoints']) === true
        && is_array($json['endpoints']) === true
        && isset($json['endpoints']['trial']) === true
        && is_array($json['endpoints']['trial']) === true
    ) {
        $trial = $json['endpoints']['trial'];
    }

    // The server advertises the fingerprint of the key it signs with. Comparing it with the
    // key embedded here turns a key rotation - which would make every signed answer look
    // tampered with - into a diagnosable "this TeamPass is too old" message.
    $fingerprint = '';
    if (is_array($json) === true
        && isset($json['security']) === true
        && is_array($json['security']) === true
    ) {
        $fingerprint = strtolower(trim((string) ($json['security']['rsa_public_key_fingerprint'] ?? '')));
        if (strpos($fingerprint, 'sha256:') === 0) {
            $fingerprint = substr($fingerprint, 7);
        }
    }

    return [
        'server_online' => is_array($json) === true && ($json['status'] ?? '') === 'online',
        'server_version' => is_array($json) === true ? (string) ($json['version'] ?? '') : '',
        'public_key_fingerprint' => $fingerprint,
        'trial_available' => $trial !== [] && isset($trial['url']) === true,
        'trial_url' => (string) ($trial['url'] ?? ''),
        'trial_method' => strtoupper((string) ($trial['method'] ?? 'POST')),
        'trial_days' => (int) ($trial['trial_days'] ?? 0),
        'requires_email_confirmation' => (bool) ($trial['requires_email_confirmation'] ?? false),
    ];
}

/**
 * Turn a trial.php answer into a decision.
 *
 * Two rules are easy to get wrong and are handled explicitly here:
 *   - 202 CONFIRMATION_SENT is a SUCCESS. A client treating "anything but 201" as a failure
 *     reports an outage while everything went well; the contract calls it the most likely
 *     integration defect.
 *   - a 5xx whose signature does not verify is reported as unreachable, not as tampered: an
 *     incident page from a reverse proxy is not an attack, and the two need different wording.
 *
 * @param int        $httpCode       HTTP status code, 0 when nothing was received.
 * @param array|null $json           Decoded body, or null.
 * @param bool       $signatureValid Result of the RSA verification on the raw body.
 * @param bool       $networkError   True when the request never reached the server.
 *
 * @return array {outcome, status, message_key, retry_after, expires_at, resend_after, errors}
 */
function licenceTrialClassifyResponse(
    int $httpCode,
    ?array $json,
    bool $signatureValid,
    bool $networkError
): array {
    $base = [
        'outcome' => LICENCE_OUTCOME_REFUSED_RETRY,
        'status' => '',
        'message_key' => 'licence_trial_error_unexpected',
        'retry_after' => 0,
        'expires_at' => 0,
        'resend_after' => 0,
        'errors' => [],
    ];

    if ($networkError === true) {
        return array_merge($base, [
            'outcome' => LICENCE_OUTCOME_UNREACHABLE,
            'message_key' => 'licence_trial_error_unreachable',
        ]);
    }

    // An incident answer is unavailability, whatever it looks like.
    if ($httpCode >= 500 && $signatureValid === false) {
        return array_merge($base, [
            'outcome' => LICENCE_OUTCOME_UNREACHABLE,
            'message_key' => 'licence_trial_error_unreachable',
        ]);
    }

    if ($signatureValid === false) {
        return array_merge($base, [
            'outcome' => LICENCE_OUTCOME_UNTRUSTED,
            'message_key' => 'licence_trial_error_untrusted',
        ]);
    }

    $status = is_array($json) === true ? (string) ($json['status'] ?? '') : '';
    $retryAfter = is_array($json) === true ? (int) ($json['retry_after'] ?? 0) : 0;
    $errors = [];
    if (is_array($json) === true && isset($json['errors']) === true && is_array($json['errors']) === true) {
        foreach ($json['errors'] as $error) {
            $errors[] = (string) (is_array($error) ? json_encode($error) : $error);
        }
    }

    $result = array_merge($base, [
        'status' => $status,
        'retry_after' => $retryAfter,
        'errors' => $errors,
    ]);

    switch ($status) {
        case 'TRIAL_GRANTED':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_GRANTED,
                'message_key' => 'licence_trial_granted',
            ]);

        case 'CONFIRMATION_SENT':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_PENDING,
                'message_key' => 'licence_trial_confirmation_sent',
                'expires_at' => licenceTrialParseServerDate((string) ($json['expires_at'] ?? '')),
                'resend_after' => (int) ($json['resend_after'] ?? 0),
            ]);

        case 'CONFIRMATION_PENDING':
            // Already sent, the resend delay has not elapsed. Still pending, not an error.
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_PENDING,
                'message_key' => 'licence_trial_confirmation_pending',
                'resend_after' => $retryAfter,
            ]);

        case 'TRIAL_ALREADY_USED':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_PERMANENT,
                'message_key' => 'licence_trial_error_already_used',
            ]);

        case 'PRODUCT_ALREADY_LICENSED':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_PERMANENT,
                'message_key' => 'licence_trial_error_already_licensed',
            ]);

        case 'UNAUTHORIZED':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_PERMANENT,
                'message_key' => 'licence_trial_error_unauthorized',
            ]);

        case 'LICENCE_REVOKED':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_PERMANENT,
                'message_key' => 'licence_trial_error_revoked',
            ]);

        case 'EMAIL_DOMAIN_MISMATCH':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_RETRY,
                'message_key' => 'licence_trial_error_email_domain',
            ]);

        case 'RATE_LIMITED':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_RETRY,
                'message_key' => 'licence_trial_error_rate_limited',
            ]);

        case 'INVALID_REQUEST':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_RETRY,
                'message_key' => 'licence_trial_error_invalid_request',
            ]);

        case 'TRIAL_API_DISABLED':
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_DISABLED,
                'message_key' => 'licence_trial_error_disabled',
            ]);

        case 'ERROR':
            // Contract §3: the message could not be sent and nothing was created.
            return array_merge($result, [
                'outcome' => LICENCE_OUTCOME_REFUSED_RETRY,
                'message_key' => 'licence_trial_error_send_failed',
            ]);

        default:
            break;
    }

    // No usable status: fall back on the HTTP code alone.
    if ($httpCode >= 500) {
        return array_merge($result, [
            'outcome' => LICENCE_OUTCOME_UNREACHABLE,
            'message_key' => 'licence_trial_error_unreachable',
        ]);
    }

    return $result;
}

/**
 * Convert a licence server date ("2026-08-24 16:30:07", UTC) into a timestamp.
 *
 * @param string $value Raw date as served.
 *
 * @return int Unix timestamp, 0 when unusable.
 */
function licenceTrialParseServerDate(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $timestamp = strtotime($value . ' UTC');

    return $timestamp === false ? 0 : $timestamp;
}

/**
 * Format a licence server calendar day without applying the instance timezone.
 *
 * @param string $serverDate Raw UTC date supplied by the licence server.
 * @param string $format     Configured PHP date format.
 *
 * @return string Formatted day, or an empty string when the date is unusable.
 */
function licenceTrialFormatServerDay(string $serverDate, string $format): string
{
    $timestamp = licenceTrialParseServerDate($serverDate);

    return $timestamp === 0 ? '' : gmdate($format, $timestamp);
}

/**
 * Return the stored state of one product, with every key present.
 *
 * @param array  $state   Whole decoded licence_trial_state.
 * @param string $product Product name.
 *
 * @return array Per-product state.
 */
function licenceTrialStateForProduct(array $state, string $product): array
{
    $entry = isset($state[$product]) === true && is_array($state[$product]) === true
        ? $state[$product]
        : [];

    return [
        'state' => (string) ($entry['state'] ?? LICENCE_TRIAL_STATE_NONE),
        'status' => (string) ($entry['status'] ?? ''),
        'contact_email' => (string) ($entry['contact_email'] ?? ''),
        'fqdn' => (string) ($entry['fqdn'] ?? ''),
        'pending_since' => (int) ($entry['pending_since'] ?? 0),
        'link_expires_at' => (int) ($entry['link_expires_at'] ?? 0),
        'resend_allowed_at' => (int) ($entry['resend_allowed_at'] ?? 0),
        'requested_at' => (int) ($entry['requested_at'] ?? 0),
        'last_error' => (string) ($entry['last_error'] ?? ''),
        // Offline flow: this instance could not reach the licence server, so the request
        // link was handed to the administrator instead. Both keys survive every transition
        // of licenceTrialNextState(), which merges onto this entry.
        'offline_link_sent_at' => (int) ($entry['offline_link_sent_at'] ?? 0),
        'offline_link_sent_to' => (string) ($entry['offline_link_sent_to'] ?? ''),
    ];
}

/**
 * Apply a classified trial answer to the persisted state of one product.
 *
 * Implements the state machine of contract §4. The FQDN and the e-mail are stored alongside
 * so a later change of browser_extension_fqdn can be detected and reported: the confirmation
 * link was minted for the FQDN that was sent, and changing it silently would leave the
 * administrator confirming a licence for a name the instance no longer uses.
 *
 * @param array  $current    Whole current state (all products).
 * @param string $product    Product the answer is about.
 * @param array  $classified Output of licenceTrialClassifyResponse().
 * @param int    $now        Current timestamp.
 * @param string $fqdn       FQDN that was sent.
 * @param string $email      Contact e-mail that was sent.
 *
 * @return array The whole new state, ready to be persisted.
 */
function licenceTrialNextState(
    array $current,
    string $product,
    array $classified,
    int $now,
    string $fqdn,
    string $email
): array {
    $entry = licenceTrialStateForProduct($current, $product);

    switch ($classified['outcome']) {
        case LICENCE_OUTCOME_GRANTED:
            $entry = array_merge($entry, [
                'state' => LICENCE_TRIAL_STATE_GRANTED,
                'status' => $classified['status'],
                'contact_email' => $email,
                'fqdn' => $fqdn,
                'requested_at' => $entry['requested_at'] > 0 ? $entry['requested_at'] : $now,
                'link_expires_at' => 0,
                'resend_allowed_at' => 0,
                'last_error' => '',
            ]);
            break;

        case LICENCE_OUTCOME_PENDING:
            // CONFIRMATION_SENT carries a fresh link; CONFIRMATION_PENDING only refreshes the
            // resend delay and must not move the link expiry, which still belongs to the
            // message already in the administrator's mailbox.
            $linkExpiresAt = $classified['expires_at'] > 0
                ? $classified['expires_at']
                : $entry['link_expires_at'];
            $resendAllowedAt = $classified['resend_after'] > 0
                ? $now + $classified['resend_after']
                : $entry['resend_allowed_at'];

            $entry = array_merge($entry, [
                'state' => LICENCE_TRIAL_STATE_PENDING,
                'status' => $classified['status'],
                'contact_email' => $email,
                'fqdn' => $fqdn,
                'pending_since' => $entry['pending_since'] > 0 ? $entry['pending_since'] : $now,
                'requested_at' => $now,
                'link_expires_at' => $linkExpiresAt,
                'resend_allowed_at' => $resendAllowedAt,
                'last_error' => '',
            ]);
            break;

        case LICENCE_OUTCOME_REFUSED_PERMANENT:
            $entry = array_merge($entry, [
                'state' => LICENCE_TRIAL_STATE_REFUSED,
                'status' => $classified['status'],
                'contact_email' => $email,
                'fqdn' => $fqdn,
                'requested_at' => $now,
                'link_expires_at' => 0,
                'resend_allowed_at' => 0,
                'last_error' => $classified['message_key'],
            ]);
            break;

        default:
            // Retryable refusals, unavailability and untrusted answers leave the state alone:
            // nothing was created server-side, so the instance is still where it was.
            $entry['last_error'] = $classified['message_key'];
            break;
    }

    $current[$product] = $entry;

    return $current;
}

/**
 * Expire a pending request whose confirmation link is dead.
 *
 * Contract §4: past link_expires_at the instance goes back to "no request", and a new one may
 * be made. Nothing was consumed — the trial registry is only written when the link is opened.
 *
 * @param array $entry Per-product state.
 * @param int   $now   Current timestamp.
 *
 * @return array Possibly reset per-product state.
 */
function licenceTrialExpirePending(array $entry, int $now): array
{
    if ($entry['state'] !== LICENCE_TRIAL_STATE_PENDING) {
        return $entry;
    }

    if ($entry['link_expires_at'] > 0 && $now > $entry['link_expires_at']) {
        return array_merge($entry, [
            'state' => LICENCE_TRIAL_STATE_NONE,
            'status' => '',
            'link_expires_at' => 0,
            'resend_allowed_at' => 0,
            'last_error' => 'licence_trial_link_expired',
        ]);
    }

    return $entry;
}

/**
 * Normalise an info.php answer into the two product views.
 *
 * The unsuffixed keys are the extension figures — the licence server keeps them that way so
 * clients written before the mobile product keep reading what they always read.
 *
 * @param array|null $json Decoded info.php body, or null.
 *
 * @return array {has_licence, extension:{...}, mobile_app:{...}}
 */
function licenceTrialNormalizeInfo(?array $json): array
{
    if (is_array($json) === false) {
        return [
            'has_licence' => false,
            'extension' => licenceTrialEmptyProductInfo(),
            'mobile_app' => licenceTrialEmptyProductInfo(),
        ];
    }

    return [
        'has_licence' => ($json['status'] ?? '') !== '' || ($json['status_mobile'] ?? '') !== '',
        'extension' => [
            'status' => (string) ($json['status'] ?? ''),
            'expiration_date' => (string) ($json['expiration_date'] ?? ''),
            'max_users' => (int) ($json['max_users'] ?? 0),
            'consumed' => (int) ($json['consumed_this_month'] ?? 0),
            'refused' => licenceTrialCount($json['refused_this_month'] ?? null) ?? 0,
            'trial' => (bool) ($json['trial'] ?? false),
            'allowed' => (bool) ($json['extension_allowed'] ?? false),
            'history' => licenceTrialNormalizeHistory($json['history'] ?? null),
        ],
        'mobile_app' => [
            'status' => (string) ($json['status_mobile'] ?? ''),
            'expiration_date' => (string) ($json['expiration_date_mobile'] ?? ''),
            'max_users' => (int) ($json['max_users_mobile'] ?? 0),
            'consumed' => (int) ($json['consumed_this_month_mobile'] ?? 0),
            'refused' => licenceTrialCount($json['refused_this_month_mobile'] ?? null) ?? 0,
            'trial' => (bool) ($json['trial_mobile'] ?? false),
            'allowed' => (bool) ($json['mobile_app_allowed'] ?? false),
            // The server only sends the extension's history.
            'history' => licenceTrialEmptyHistory(),
        ],
    ];
}

/**
 * Empty product figures, used when info.php said nothing usable.
 *
 * @return array
 */
function licenceTrialEmptyProductInfo(): array
{
    return [
        'status' => '',
        'expiration_date' => '',
        'max_users' => 0,
        'consumed' => 0,
        'refused' => 0,
        'trial' => false,
        'allowed' => false,
        'history' => licenceTrialEmptyHistory(),
    ];
}

/**
 * Empty seat history, used when info.php sent none (older server, failure, untrusted body).
 *
 * @return array{days: int, today: string, first_snapshot: string, points: array}
 */
function licenceTrialEmptyHistory(): array
{
    return ['days' => 0, 'today' => '', 'first_snapshot' => '', 'points' => []];
}

/**
 * Normalise the seat history info.php returns when history_days was sent.
 *
 * The body is already signature-verified; this guards the renderer against a malformed series.
 * A series whose dates are invalid or not strictly increasing is dropped whole rather than
 * drawn wrong. Only the last point may be live.
 *
 * @param mixed $history Raw `history` value of the info.php body.
 *
 * @return array{days: int, today: string, first_snapshot: string, points: array}
 */
function licenceTrialNormalizeHistory($history): array
{
    if (is_array($history) === false || is_array($history['points'] ?? null) === false) {
        return licenceTrialEmptyHistory();
    }

    $rawPoints = array_slice(array_values($history['points']), -LICENCE_SEATS_HISTORY_MAX_POINTS);
    $lastIndex = count($rawPoints) - 1;
    $points = [];
    $previous = '';
    foreach ($rawPoints as $index => $point) {
        if (is_array($point) === false) {
            return licenceTrialEmptyHistory();
        }
        $date = is_string($point['date'] ?? null) === true ? $point['date'] : '';
        if (licenceTrialIsServerDay($date) === false || strcmp($date, $previous) <= 0) {
            return licenceTrialEmptyHistory();
        }
        $previous = $date;
        $points[] = [
            'date' => $date,
            'seats_used' => licenceTrialCount($point['seats_used'] ?? null),
            'seats_quota' => licenceTrialCount($point['seats_quota'] ?? null),
            'active_users' => licenceTrialCount($point['active_users'] ?? null),
            'live' => $index === $lastIndex && ($point['live'] ?? false) === true,
        ];
    }

    $firstSnapshot = is_string($history['first_snapshot'] ?? null) === true ? $history['first_snapshot'] : '';

    return [
        'days' => count($points),
        // Taken from the last point, so the two can never disagree.
        'today' => $points === [] ? '' : $points[$lastIndex]['date'],
        'first_snapshot' => licenceTrialIsServerDay($firstSnapshot) === true ? $firstSnapshot : '',
        'points' => $points,
    ];
}

/**
 * Whether a value is a licence-server calendar day, "Y-m-d".
 *
 * @param string $value Candidate date.
 *
 * @return bool
 */
function licenceTrialIsServerDay(string $value): bool
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m) !== 1) {
        return false;
    }

    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * A non-negative integer count, or null when the value is anything else.
 *
 * @param mixed $value Decoded JSON value.
 *
 * @return int|null
 */
function licenceTrialCount($value): ?int
{
    return is_int($value) === true && $value >= 0 ? $value : null;
}

/**
 * Days remaining before an expiration date, counted from the end of the expiry day.
 *
 * @param string $expirationDate Licence server date, "Y-m-d H:i:s" in UTC.
 * @param int    $now            Current timestamp.
 *
 * @return int|null Remaining whole days, negative when past; null when there is no date.
 */
function licenceTrialDaysLeft(string $expirationDate, int $now): ?int
{
    $timestamp = licenceTrialParseServerDate($expirationDate);
    if ($timestamp === 0) {
        return null;
    }

    return (int) floor(($timestamp - $now) / 86400);
}

/**
 * How long an info.php answer may be reused before calling again.
 *
 * @param bool $pending     True when a confirmation is being waited for.
 * @param bool $serverOnline True when the last call reached the licence server.
 *
 * @return int Seconds.
 */
function licenceTrialInfoTtl(bool $pending, bool $serverOnline): int
{
    if ($serverOnline === false) {
        return LICENCE_INFO_TTL_OFFLINE;
    }

    return $pending === true ? LICENCE_INFO_TTL_PENDING : LICENCE_INFO_TTL_ONLINE;
}

/**
 * Tell whether one more info.php call fits in the hourly budget.
 *
 * @param array $budget {window_start, count}
 * @param int   $now    Current timestamp.
 * @param int   $max    Maximum calls per window.
 *
 * @return bool
 */
function licenceTrialBudgetAllows(array $budget, int $now, int $max = LICENCE_INFO_MAX_CALLS_PER_HOUR): bool
{
    $windowStart = (int) ($budget['window_start'] ?? 0);
    $count = (int) ($budget['count'] ?? 0);

    if ($windowStart === 0 || ($now - $windowStart) >= LICENCE_INFO_BUDGET_WINDOW) {
        return true;
    }

    return $count < $max;
}

/**
 * Record one info.php call, rolling the window when it has elapsed.
 *
 * @param array $budget {window_start, count}
 * @param int   $now    Current timestamp.
 *
 * @return array The new budget.
 */
function licenceTrialBudgetConsume(array $budget, int $now): array
{
    $windowStart = (int) ($budget['window_start'] ?? 0);
    $count = (int) ($budget['count'] ?? 0);

    if ($windowStart === 0 || ($now - $windowStart) >= LICENCE_INFO_BUDGET_WINDOW) {
        return ['window_start' => $now, 'count' => 1];
    }

    return ['window_start' => $windowStart, 'count' => $count + 1];
}

/**
 * Seconds to wait before the budget allows another call.
 *
 * @param array $budget {window_start, count}
 * @param int   $now    Current timestamp.
 * @param int   $max    Maximum calls per window.
 *
 * @return int Zero when a call is allowed right away.
 */
function licenceTrialBudgetRetryAfter(
    array $budget,
    int $now,
    int $max = LICENCE_INFO_MAX_CALLS_PER_HOUR
): int {
    if (licenceTrialBudgetAllows($budget, $now, $max) === true) {
        return 0;
    }

    $windowStart = (int) ($budget['window_start'] ?? 0);

    return max(1, ($windowStart + LICENCE_INFO_BUDGET_WINDOW) - $now);
}

/**
 * Build the single view model the Licence tab renders.
 *
 * Resolution order matters. An active licence wins over everything: it is the answer to the
 * only question a client really has (contract §5), and it also closes a pending request and
 * supersedes a past refusal. Then a pending request, then the reasons no request can be made.
 *
 * @param array      $state     Whole persisted licence_trial_state.
 * @param array      $info      Output of licenceTrialNormalizeInfo().
 * @param array      $discovery Output of licenceTrialParseDiscovery().
 * @param int        $now       Current timestamp.
 * @param string     $fqdn      Current browser_extension_fqdn.
 * @param string     $token     Current browser_extension_key.
 * @param string     $product   Product the panel is about.
 *
 * @return array View model.
 */
function licenceTrialResolveDisplay(
    array $state,
    array $info,
    array $discovery,
    int $now,
    string $fqdn,
    string $token,
    string $product = LICENCE_TRIAL_DEFAULT_PRODUCT
): array {
    $entry = licenceTrialExpirePending(licenceTrialStateForProduct($state, $product), $now);
    $productInfo = isset($info[$product]) === true && is_array($info[$product]) === true
        ? $info[$product]
        : licenceTrialEmptyProductInfo();

    $fqdnValid = licenceTrialIsValidFqdn($fqdn);
    $tokenValid = licenceTrialIsValidToken($token);
    $daysLeft = licenceTrialDaysLeft((string) $productInfo['expiration_date'], $now);
    $hasLicence = $productInfo['status'] !== '' && $productInfo['max_users'] > 0;

    $vm = [
        'panel' => LICENCE_PANEL_NONE,
        'product' => $product,
        'fqdn' => licenceTrialNormalizeFqdn($fqdn),
        'fqdn_valid' => $fqdnValid,
        'token_valid' => $tokenValid,
        'token_present' => $token !== '',
        'server_online' => (bool) $discovery['server_online'],
        'server_version' => (string) $discovery['server_version'],
        'trial_available' => (bool) $discovery['trial_available'],
        'trial_days' => (int) $discovery['trial_days'],
        'requires_email_confirmation' => (bool) $discovery['requires_email_confirmation'],
        'state' => $entry['state'],
        'status' => $entry['status'],
        'contact_email' => $entry['contact_email'],
        'pending_since' => $entry['pending_since'],
        'link_expires_at' => $entry['link_expires_at'],
        'link_expires_in' => max(0, $entry['link_expires_at'] - $now),
        'resend_allowed_at' => $entry['resend_allowed_at'],
        'resend_in' => max(0, $entry['resend_allowed_at'] - $now),
        'fqdn_changed_since_request' => $entry['fqdn'] !== ''
            && $entry['fqdn'] !== licenceTrialNormalizeFqdn($fqdn),
        'offline_link_sent_at' => $entry['offline_link_sent_at'],
        'offline_link_sent_to' => $entry['offline_link_sent_to'],
        'licence' => [
            'status' => $productInfo['status'],
            'expiration_date' => $productInfo['expiration_date'],
            'max_users' => $productInfo['max_users'],
            'consumed' => $productInfo['consumed'],
            'trial' => (bool) $productInfo['trial'],
            'days_left' => $daysLeft,
            'expiring_soon' => (bool) $productInfo['trial']
                && $daysLeft !== null
                && $daysLeft <= LICENCE_TRIAL_EXPIRY_WARNING_DAYS,
        ],
    ];

    if ($hasLicence === true) {
        $vm['panel'] = LICENCE_PANEL_GRANTED;

        return $vm;
    }

    if ($entry['state'] === LICENCE_TRIAL_STATE_PENDING) {
        $vm['panel'] = LICENCE_PANEL_PENDING;

        return $vm;
    }

    if ($entry['state'] === LICENCE_TRIAL_STATE_REFUSED) {
        $vm['panel'] = LICENCE_PANEL_REFUSED;

        return $vm;
    }

    // The identity is checked before the network state on purpose. It is a local fact,
    // it is actionable without Internet access, and the offline request link cannot be
    // built without it - telling an administrator "the server is unreachable" when the
    // real blocker is an FQDN of "localhost" sends them looking at their firewall.
    if ($fqdnValid === false || $tokenValid === false) {
        $vm['panel'] = LICENCE_PANEL_INVALID_FQDN;

        return $vm;
    }

    if ($discovery['server_online'] === false) {
        $vm['panel'] = LICENCE_PANEL_UNREACHABLE;

        return $vm;
    }

    if ($discovery['trial_available'] === false) {
        $vm['panel'] = LICENCE_PANEL_CLOSED;

        return $vm;
    }

    return $vm;
}
