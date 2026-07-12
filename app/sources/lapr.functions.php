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
 * @file      lapr.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Session\SessionInterface;

// Defaults used when a managed account has no policy (decision D3 — Option B)
if (defined('LAPR_DEFAULT_PASSWORD_LENGTH') === false) {
    define('LAPR_DEFAULT_PASSWORD_LENGTH', 24);
}
if (defined('LAPR_DEFAULT_FREQUENCY_DAYS') === false) {
    define('LAPR_DEFAULT_FREQUENCY_DAYS', 30);
}
// POSIX/useradd username rule (risk R1 — mandatory server-side validation)
if (defined('LAPR_USERNAME_REGEX') === false) {
    define('LAPR_USERNAME_REGEX', '/^[a-z_][a-z0-9_-]{0,31}\$?$/');
}

/**
 * Whether the current user may use the LAPR module.
 *
 * Central permission gate (Point 7 §5b revised): the module must be enabled
 * globally AND the user must be an admin or hold the per-user flag.
 *
 * @param SessionInterface $session  Current session
 * @param array            $SETTINGS TeamPass settings
 *
 * @return bool
 */
function laprCheckPermission(SessionInterface $session, array $SETTINGS): bool
{
    if ((int) ($SETTINGS['lapr_enabled'] ?? 0) !== 1) {
        return false;
    }

    return (int) $session->get('user-admin') === 1
        || (int) $session->get('user-can_manage_lapr') === 1;
}

/**
 * Write an entry into the LAPR audit log. Secrets must NEVER be passed in
 * $details or $errorMessage (guard-rail G9) — callers are responsible for
 * whitelisting the fields they log.
 *
 * @param string      $actionType   Event type (endpoint_add|endpoint_test|rotation|...)
 * @param int|null    $endpointId   Related endpoint id
 * @param int         $userId       Acting user id (TP_USER_ID for the scheduler)
 * @param array       $details      Whitelisted context, stored as JSON
 * @param string      $result       'success' | 'failure' | 'warning'
 * @param int|null    $accountId    Related managed account id
 * @param string|null $errorMessage Error code / short message (no secrets)
 * @param string|null $ip           Client IP; resolved automatically when null
 *
 * @return void
 */
function laprAuditLog(
    string $actionType,
    ?int $endpointId,
    int $userId,
    array $details,
    string $result,
    ?int $accountId = null,
    ?string $errorMessage = null,
    ?string $ip = null
): void {
    if ($ip === null) {
        $ip = PHP_SAPI === 'cli' ? 'system' : getClientIpServer();
    }

    DB::insert(
        prefixTable('lapr_audit_log'),
        [
            'action_type' => $actionType,
            'endpoint_id' => $endpointId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'ip_address' => substr($ip, 0, 45),
            'action_details' => json_encode($details, JSON_UNESCAPED_SLASHES),
            'result' => in_array($result, ['success', 'failure', 'warning'], true) ? $result : 'warning',
            'error_message' => $errorMessage,
            'created_at' => date('Y-m-d H:i:s'),
        ]
    );
}

/**
 * Sliding-window rate limit on SSH tests / endpoint adds, keyed by client IP
 * and by target hostname (both must pass). Uses settings
 * lapr_rate_limit_max_attempts / _window_seconds / _block_seconds.
 *
 * @param string $ip       Client IP (from getClientIpServer(), correction C14)
 * @param string $hostname Target hostname
 * @param array  $SETTINGS TeamPass settings
 *
 * @return array{allowed: bool, retry_after: int}
 */
function laprCheckRateLimit(string $ip, string $hostname, array $SETTINGS): array
{
    $maxAttempts = max(1, (int) ($SETTINGS['lapr_rate_limit_max_attempts'] ?? 5));
    $windowSeconds = max(1, (int) ($SETTINGS['lapr_rate_limit_window_seconds'] ?? 60));
    $blockSeconds = max(1, (int) ($SETTINGS['lapr_rate_limit_block_seconds'] ?? 300));
    $now = time();

    $scopes = [
        ['scope' => 'ip', 'value' => substr($ip, 0, 255)],
        ['scope' => 'hostname', 'value' => substr(strtolower($hostname), 0, 255)],
    ];

    foreach ($scopes as $entry) {
        $row = DB::queryFirstRow(
            'SELECT id, attempts, window_start, blocked_until FROM ' . prefixTable('lapr_rate_limit') . '
             WHERE scope = %s AND scope_value = %s',
            $entry['scope'],
            $entry['value']
        );

        if ($row === null) {
            DB::insert(
                prefixTable('lapr_rate_limit'),
                [
                    'scope' => $entry['scope'],
                    'scope_value' => $entry['value'],
                    'attempts' => 1,
                    'window_start' => $now,
                ]
            );
            continue;
        }

        // Currently blocked?
        if ($row['blocked_until'] !== null && (int) $row['blocked_until'] > $now) {
            return ['allowed' => false, 'retry_after' => (int) $row['blocked_until'] - $now];
        }

        if ($now - (int) $row['window_start'] > $windowSeconds) {
            // Window expired: reset
            DB::update(
                prefixTable('lapr_rate_limit'),
                ['attempts' => 1, 'window_start' => $now, 'blocked_until' => null],
                'id = %i',
                $row['id']
            );
            continue;
        }

        $attempts = (int) $row['attempts'] + 1;
        if ($attempts > $maxAttempts) {
            DB::update(
                prefixTable('lapr_rate_limit'),
                ['attempts' => $attempts, 'blocked_until' => $now + $blockSeconds],
                'id = %i',
                $row['id']
            );
            return ['allowed' => false, 'retry_after' => $blockSeconds];
        }

        DB::update(
            prefixTable('lapr_rate_limit'),
            ['attempts' => $attempts],
            'id = %i',
            $row['id']
        );
    }

    return ['allowed' => true, 'retry_after' => 0];
}

/**
 * Whether a hostname is allowed by the admin allowlist (risk R3 — primary
 * SSRF control). When the allowlist is disabled, everything is allowed.
 *
 * Allowlist format (setting lapr_allowlist): comma- or newline-separated
 * entries; an entry is an exact hostname/IP, or a domain suffix written as
 * '*.example.com'.
 *
 * @param string $hostname Target hostname
 * @param array  $SETTINGS TeamPass settings
 *
 * @return bool
 */
function laprIsHostnameAllowed(string $hostname, array $SETTINGS): bool
{
    if ((int) ($SETTINGS['lapr_allowlist_enabled'] ?? 0) !== 1) {
        return true;
    }

    $hostname = strtolower(trim($hostname));
    $raw = (string) ($SETTINGS['lapr_allowlist'] ?? '');
    $entries = preg_split('/[\s,]+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY);
    if ($entries === false || count($entries) === 0) {
        // Allowlist enabled but empty: deny everything (fail closed)
        return false;
    }

    foreach ($entries as $entry) {
        if (strpos($entry, '*.') === 0) {
            $suffix = substr($entry, 1); // '.example.com'
            if (strlen($hostname) > strlen($suffix)
                && substr($hostname, -strlen($suffix)) === $suffix
            ) {
                return true;
            }
        } elseif ($entry === $hostname) {
            return true;
        }
    }

    return false;
}

/**
 * Server-side validation of a managed account username (risk R1 — mandatory
 * before every rotation and at account add; item.login is free text).
 *
 * @param string $username Candidate Linux account name
 *
 * @return bool
 */
function laprValidateUsername(string $username): bool
{
    return preg_match(LAPR_USERNAME_REGEX, $username) === 1;
}

/**
 * Whether a generated password is safe for the chpasswd pipeline (risk R9):
 * no ':' (chpasswd field separator), no whitespace/newline, no backslash,
 * no quotes, printable ASCII only.
 *
 * @param string $password Candidate password
 *
 * @return bool
 */
function laprIsPasswordSafeForLinux(string $password): bool
{
    if ($password === '') {
        return false;
    }

    return preg_match('/^[\x21-\x7E]+$/', $password) === 1
        && preg_match('/[:\\\\\'"]/', $password) !== 1;
}

/**
 * Generate a rotation password per policy rules, filtered for Linux/chpasswd
 * safety (R9): regenerates until the candidate passes
 * laprIsPasswordSafeForLinux(). Bounded loop — the filter rejects only a
 * small fraction of candidates, so a cap of 50 tries is far beyond need.
 *
 * @param int  $length    Password length (8–128, defaults clamped)
 * @param bool $uppercase Include uppercase letters
 * @param bool $lowercase Include lowercase letters
 * @param bool $digits    Include digits
 * @param bool $symbols   Include symbols
 *
 * @return string
 * @throws Exception When no safe password could be generated
 */
function laprGeneratePassword(
    int $length = LAPR_DEFAULT_PASSWORD_LENGTH,
    bool $uppercase = true,
    bool $lowercase = true,
    bool $digits = true,
    bool $symbols = true
): string {
    $length = max(8, min(128, $length));
    if ($uppercase === false && $lowercase === false && $digits === false && $symbols === false) {
        $lowercase = true;
    }

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = GenerateCryptKey($length, false, $digits, $uppercase, $symbols, $lowercase);
        if (laprIsPasswordSafeForLinux($candidate) === true) {
            return $candidate;
        }
    }

    throw new Exception('LAPR: unable to generate a Linux-safe password after 50 attempts');
}

/**
 * Compute the next rotation datetime from the last rotation and a policy
 * frequency. When the computed date is in the past (e.g. frequency was
 * shortened), it is clamped to now (spec Option A).
 *
 * @param string|null $lastRotationAt 'Y-m-d H:i:s' of the last rotation, or null (never rotated)
 * @param int         $frequencyDays  Policy frequency in days
 * @param int|null    $now            Unix timestamp reference (defaults to time())
 *
 * @return string 'Y-m-d H:i:s'
 */
function laprComputeNextRotation(?string $lastRotationAt, int $frequencyDays, ?int $now = null): string
{
    $now = $now ?? time();
    $frequencyDays = max(1, $frequencyDays);

    if ($lastRotationAt === null || $lastRotationAt === '') {
        $base = $now;
    } else {
        $ts = strtotime($lastRotationAt);
        $base = $ts === false ? $now : $ts;
    }

    $next = $base + $frequencyDays * 86400;
    if ($next < $now) {
        $next = $now;
    }

    return date('Y-m-d H:i:s', $next);
}

/**
 * Load and decrypt the TP_USER (server) RSA key pair — the server-side
 * decryptable chain used by all LAPR background work (correction C3; same
 * pattern as SharekeysRepairTrait).
 *
 * @param array $settings TeamPass settings (needed by cryption())
 *
 * @return array{private_key: string, public_key: string}
 * @throws Exception When the TP_USER private key cannot be decrypted
 */
function laprGetTpUserPrivateKey(array $settings): array
{
    $userTpInfo = DB::queryFirstRow(
        'SELECT u.pw, u.public_key, pk.private_key
        FROM ' . prefixTable('users') . ' AS u
        LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk ON (u.id = pk.user_id AND pk.is_current = 1)
        WHERE u.id = %i',
        TP_USER_ID
    );
    $decryptedData = cryption((string) ($userTpInfo['pw'] ?? ''), '', 'decrypt', $settings);
    $tpPrivateKey = decryptPrivateKey($decryptedData['string'] ?? '', (string) ($userTpInfo['private_key'] ?? ''));
    if (empty($tpPrivateKey) === true) {
        throw new Exception('LAPR: cannot decrypt TP_USER private key');
    }

    return [
        'private_key' => $tpPrivateKey,
        'public_key' => (string) ($userTpInfo['public_key'] ?? ''),
    ];
}

/**
 * Whether the user can WRITE in a folder (Point 7 §4 / correction C9):
 * writable = user-accessible_folders minus user-read_only_folders.
 * Admin bypass.
 *
 * @param int              $folderId Folder id (nested_tree)
 * @param SessionInterface $session  Current session
 *
 * @return bool
 */
function laprUserCanWriteFolder(int $folderId, SessionInterface $session): bool
{
    if ((int) $session->get('user-admin') === 1) {
        return true;
    }

    $accessible = $session->get('user-accessible_folders') ?? [];
    $readOnly = $session->get('user-read_only_folders') ?? [];

    return in_array($folderId, array_map('intval', (array) $accessible), true)
        && in_array($folderId, array_map('intval', (array) $readOnly), true) === false;
}

/**
 * Whether the user can READ a folder (Point 6 §6.2): read access uses
 * user-accessible_folders directly (reading a read-only folder is allowed).
 * Admin bypass.
 *
 * @param int              $folderId Folder id (nested_tree)
 * @param SessionInterface $session  Current session
 *
 * @return bool
 */
function laprUserCanReadFolder(int $folderId, SessionInterface $session): bool
{
    if ((int) $session->get('user-admin') === 1) {
        return true;
    }

    $accessible = $session->get('user-accessible_folders') ?? [];

    return in_array($folderId, array_map('intval', (array) $accessible), true);
}
