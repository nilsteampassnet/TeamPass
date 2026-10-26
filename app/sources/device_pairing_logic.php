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
 * @file      device_pairing_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/api_device_logic.php';

const TP_DEVICE_PAIRING_TTL = 180;
const TP_DEVICE_PAIRING_DELIVERY_TTL = 60;
const TP_DEVICE_PAIRING_POLL_INTERVAL = 2;

/** A fixed public reason and status; never include exception text or submitted secrets. */
class DevicePairingException extends RuntimeException
{
    /** Construct a protocol failure with a machine-readable reason. */
    public function __construct(public readonly string $reason, int $status = 400, public readonly int $retryAfter = 2)
    {
        parent::__construct($reason, $status);
    }
}

/** Encode opaque bytes without padding. */
function devicePairingBase64Url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/** Validate an opaque field without normalizing it or accepting structured values. */
function devicePairingInput(array $input, string $field, string $pattern): string
{
    $value = $input[$field] ?? null;
    if (!is_string($value) || strlen($value) > 2048 || preg_match($pattern, $value) !== 1) {
        throw new DevicePairingException('invalid_request');
    }
    return $value;
}

/** Validate a UTF-8 device label; callers render it as text, never HTML. */
function devicePairingLabel(array $input): string
{
    $label = $input['device_label'] ?? null;
    if (!is_string($label) || !mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > 128
        || trim($label) === '' || preg_match('/[\x00-\x1f\x7f]/', $label)
    ) {
        throw new DevicePairingException('invalid_request');
    }
    return trim($label);
}

/** Derive a canonical HTTPS instance URL only from administrator-owned configuration. */
function devicePairingServerUrl(array $settings): string
{
    $url = rtrim((string) ($settings['cpassman_url'] ?? ''), '/');
    try {
        $parts = parse_url($url);
    } catch (ValueError $e) {
        $parts = false;
    }
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
        || $parts === false || ($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host']) || isset($parts['user'])
        || isset($parts['query']) || isset($parts['fragment']) || filter_var($url, FILTER_VALIDATE_URL) === false
        || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', rawurldecode($parts['path'] ?? ''))
    ) {
        throw new DevicePairingException('https_required', 403);
    }
    return $url;
}

/** Validate the web-session markers without storing the session key or cookie in a handoff. */
function devicePairingOwnerSessionValid(array $pairing, array $user, int $now): bool
{
    return (int) ($pairing['web_expires_at'] ?? 0) > $now
        && (int) ($user['session_end'] ?? 0) > $now
        && !empty($user['key_tempo'])
        && hash_equals((string) $pairing['session_marker'], hash('sha256', (string) $user['key_tempo']));
}

/** Decide expiry from server time, independently of polling and maintenance scheduling. */
function devicePairingNeedsExpiration(array $pairing, array $user, array $settings, int $now): bool
{
    if (!in_array($pairing['state'], ['awaiting_scan', 'awaiting_approval', 'approved', 'delivered'], true)) {
        return false;
    }
    return (int) $pairing['expires_at'] <= $now
        || !hash_equals((string) $pairing['server_url'], rtrim((string) ($settings['cpassman_url'] ?? ''), '/'))
        || ($pairing['state'] === 'delivered' && (int) $pairing['delivery_until'] <= $now)
        || !devicePairingOwnerSessionValid($pairing, $user, $now)
        || !apiDeviceAccountAllowed($settings, $user)
        || (int) ($user['is_ready_for_usage'] ?? 0) !== 1
        || !hash_equals((string) $pairing['user_key_fingerprint'], hash('sha256', (string) ($user['public_key'] ?? '')));
}

/** Create the public state projection; no wrapping material, secret hash or verifier leaves storage. */
function devicePairingPublicState(array $row, int $now, bool $web): array
{
    $result = ['pairing_id' => $row['id'], 'state' => $row['state'], 'expires_at' => (int) $row['expires_at'],
        'server_time' => $now, 'poll_interval' => TP_DEVICE_PAIRING_POLL_INTERVAL];
    if ($web && $row['claim_id'] !== null) {
        $result += ['claim_id' => $row['claim_id'], 'key_fingerprint' => $row['key_fingerprint'],
            'device_label' => $row['device_label'], 'comparison_code' => $row['comparison_code']];
    }
    return $result;
}

/** DDL shared by installation, upgrade and real-database integration tests. */
function devicePairingSchemaSql(string $table): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        throw new InvalidArgumentException('Invalid pairing table identifier');
    }
    return 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        user_id INT NOT NULL,
        server_url VARCHAR(2048) NOT NULL,
        owner_binding CHAR(64) CHARACTER SET ascii NOT NULL,
        session_marker CHAR(64) CHARACTER SET ascii NOT NULL,
        user_key_fingerprint CHAR(64) CHARACTER SET ascii NOT NULL,
        web_expires_at BIGINT NOT NULL,
        state VARCHAR(24) NOT NULL,
        secret_hash CHAR(64) CHARACTER SET ascii NULL,
        claim_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
        challenge CHAR(43) CHARACTER SET ascii COLLATE ascii_bin NULL,
        public_key TEXT NULL,
        key_fingerprint CHAR(64) CHARACTER SET ascii NULL,
        device_label VARCHAR(128) NULL,
        comparison_code CHAR(6) CHARACTER SET ascii NULL,
        token_hash CHAR(64) CHARACTER SET ascii NULL,
        wrapped_private_key TEXT NULL,
        salt CHAR(32) CHARACTER SET ascii NULL,
        encrypted_token TEXT NULL,
        credential_id INT NULL,
        created_at BIGINT NOT NULL,
        expires_at BIGINT NOT NULL,
        updated_at BIGINT NOT NULL,
        delivery_until BIGINT NULL,
        client_polled_at BIGINT NULL,
        KEY user_state(user_id, state),
        KEY expires_at(expires_at),
        KEY state_updated(state, updated_at),
        UNIQUE KEY credential_id(credential_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}
