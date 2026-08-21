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
use TeampassClasses\Language\Language;

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
 * Central permission gate for item-dependent LAPR operations: the module must
 * be enabled globally AND the user must be a non-admin holding the per-user
 * flag. TeamPass administrators configure LAPR through admin_lapr but cannot
 * access items, endpoints, managed accounts, or rotation policies.
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

    return (int) $session->get('user-admin') !== 1
        && (int) $session->get('user-can_manage_lapr') === 1;
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
 * Whether a passwd entry is a candidate for LAPR account discovery.
 *
 * Only the real root account and regular users (UID >= 1000) are eligible.
 * This keeps reserved system accounts such as Debian/Ubuntu's `sync` (UID 4,
 * shell /bin/sync) out of the managed-account picker.
 *
 * @param array{username?: mixed, uid?: mixed, shell?: mixed} $account Passwd entry
 *
 * @return bool
 */
function laprIsDiscoverableAccount(array $account): bool
{
    if (
        isset($account['username'], $account['uid'], $account['shell']) === false
        || is_numeric($account['uid']) === false
    ) {
        return false;
    }

    $username = (string) $account['username'];
    $uid = (int) $account['uid'];
    $shell = trim((string) $account['shell']);
    $isEligibleUid = ($username === 'root' && $uid === 0) || $uid >= 1000;
    $isLoginShell = $shell !== ''
        && strpos($shell, 'nologin') === false
        && strpos($shell, '/false') === false;

    return $isEligibleUid === true
        && $isLoginShell === true
        && laprValidateUsername($username) === true;
}

/**
 * Return the localized display name of a built-in policy preset.
 *
 * Preset labels are stable English identifiers stored in the database by the
 * installer. Custom policy labels and unknown future presets are returned
 * verbatim because they are user/application data, not language keys.
 *
 * @param string   $storedLabel Label stored in lapr_policies
 * @param bool     $isPreset    Whether the row is a built-in preset
 * @param Language $lang        Language helper
 *
 * @return string
 */
function laprPolicyDisplayName(string $storedLabel, bool $isPreset, Language $lang): string
{
    if ($isPreset === false) {
        return $storedLabel;
    }

    $presetKeys = [
        'Standard (30 days)' => 'lapr_preset_standard',
        'High Security (7 days)' => 'lapr_preset_high_security',
        'Weekly + rotate on enroll' => 'lapr_preset_weekly_enroll',
    ];
    $key = $presetKeys[$storedLabel] ?? null;

    return $key === null ? $storedLabel : $lang->get($key);
}

/**
 * Add a localized rotation frequency to a policy name for select options.
 *
 * @param string   $displayName  Localized preset name or custom policy label
 * @param int      $frequencyDays Rotation frequency in days
 * @param Language $lang         Language helper
 *
 * @return string
 */
function laprPolicyOptionLabel(string $displayName, int $frequencyDays, Language $lang): string
{
    $key = $frequencyDays === 1
        ? 'lapr_policy_option_label_singular'
        : 'lapr_policy_option_label_plural';

    return sprintf($lang->get($key), $displayName, $frequencyDays);
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
 * Validate rotation-policy parameters (Point 3 bounds — enforced on both
 * client and server). Length 8–128, frequency 1–3650 days, at least one
 * character set enabled.
 *
 * @param int  $frequencyDays  Rotation frequency in days
 * @param int  $passwordLength Password length
 * @param bool $uppercase      Uppercase enabled
 * @param bool $lowercase      Lowercase enabled
 * @param bool $digits         Digits enabled
 * @param bool $symbols        Symbols enabled
 *
 * @return bool
 */
function laprValidatePolicyParams(
    int $frequencyDays,
    int $passwordLength,
    bool $uppercase,
    bool $lowercase,
    bool $digits,
    bool $symbols
): bool {
    if ($frequencyDays < 1 || $frequencyDays > 3650) {
        return false;
    }
    if ($passwordLength < 8 || $passwordLength > 128) {
        return false;
    }
    if ($uppercase === false && $lowercase === false && $digits === false && $symbols === false) {
        return false;
    }

    return true;
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
 * Prepare a stored LAPR datetime for regional display and chronological sort.
 *
 * LAPR stores SQL DATETIME values in the TeamPass-configured timezone. The
 * request handlers set that timezone before calling this helper, so parsing
 * and formatting remain consistent with the rest of the application.
 *
 * @param string|null         $value    Stored 'Y-m-d H:i:s' value
 * @param array<string, mixed> $settings TeamPass regional settings
 *
 * @return array{display: string, timestamp: int}
 */
function laprFormatDateTimeForDisplay(?string $value, array $settings): array
{
    if ($value === null || trim($value) === '') {
        return ['display' => '', 'timestamp' => 0];
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return ['display' => '', 'timestamp' => 0];
    }

    $dateFormat = trim((string) ($settings['date_format'] ?? ''));
    $timeFormat = trim((string) ($settings['time_format'] ?? ''));
    $dateFormat = $dateFormat === '' ? 'Y-m-d' : $dateFormat;
    $timeFormat = $timeFormat === '' ? 'H:i:s' : $timeFormat;

    return [
        'display' => date($dateFormat . ' ' . $timeFormat, $timestamp),
        'timestamp' => $timestamp,
    ];
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
 * Build an HMAC-signed snapshot of a successful SSH test so it cannot be
 * tampered with between the test and the endpoint save (spec §10). The HMAC
 * key is the server master secret (SECUREFILE).
 *
 * @param array $data Snapshot payload (tested connection params + fingerprint)
 *
 * @return array{payload: array, sig: string}
 */
function laprSignSnapshot(array $data): array
{
    ksort($data);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $sig = hash_hmac('sha256', $json, getServerSecret());

    return ['payload' => $data, 'sig' => $sig];
}

/**
 * Verify an HMAC-signed test snapshot returned by the client at save time.
 *
 * @param array  $payload Snapshot payload
 * @param string $sig     HMAC signature produced by laprSignSnapshot()
 *
 * @return bool
 */
function laprVerifySnapshot(array $payload, string $sig): bool
{
    ksort($payload);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $expected = hash_hmac('sha256', $json, getServerSecret());

    return hash_equals($expected, $sig);
}

/**
 * Read and decrypt the cleartext password of a TeamPass item using the TP_USER
 * key chain (server-side, no human password). Used to read the SSH credential
 * of an endpoint from a linked item. The item must be non-personal so TP_USER
 * holds a sharekey for it.
 *
 * @param int    $itemId        teampass_items.id holding the credential
 * @param string $tpPrivateKey  TP_USER decrypted private key (from laprGetTpUserPrivateKey)
 * @param string $tpPublicKey   TP_USER public key
 *
 * @return string Cleartext password ('' on any failure)
 */
function laprReadItemPasswordAsTpUser(int $itemId, string $tpPrivateKey, string $tpPublicKey): string
{
    $item = DB::queryFirstRow(
        'SELECT id, pw, pw_iv FROM ' . prefixTable('items') . ' WHERE id = %i',
        $itemId
    );
    if ($item === null || (string) $item['pw'] === '') {
        return '';
    }

    $userKey = DB::queryFirstRow(
        'SELECT share_key, increment_id FROM ' . prefixTable('sharekeys_items') . '
         WHERE user_id = %i AND object_id = %i',
        TP_USER_ID,
        $itemId
    );
    if ($userKey === null) {
        return '';
    }

    $objectKey = decryptUserObjectKeyWithMigration(
        (string) $userKey['share_key'],
        $tpPrivateKey,
        $tpPublicKey,
        (int) $userKey['increment_id'],
        'sharekeys_items'
    );
    if ($objectKey === '') {
        return '';
    }

    return base64_decode(doDataDecryption((string) $item['pw'], $objectKey, (string) ($item['pw_iv'] ?? '')));
}

/**
 * Whether the user can WRITE in a folder (Point 7 §4 / correction C9):
 * writable = user-accessible_folders minus user-read_only_folders.
 * Administrators are rejected because operational LAPR access requires items.
 *
 * @param int              $folderId Folder id (nested_tree)
 * @param SessionInterface $session  Current session
 *
 * @return bool
 */
function laprUserCanWriteFolder(int $folderId, SessionInterface $session): bool
{
    if ((int) $session->get('user-admin') === 1) {
        return false;
    }

    $accessible = $session->get('user-accessible_folders') ?? [];
    $readOnly = $session->get('user-read_only_folders') ?? [];

    return in_array($folderId, array_map('intval', (array) $accessible), true)
        && in_array($folderId, array_map('intval', (array) $readOnly), true) === false;
}

/**
 * Whether the user can READ a folder (Point 6 §6.2): read access uses
 * user-accessible_folders directly (reading a read-only folder is allowed).
 * Administrators are rejected because operational LAPR access requires items.
 *
 * @param int              $folderId Folder id (nested_tree)
 * @param SessionInterface $session  Current session
 *
 * @return bool
 */
function laprUserCanReadFolder(int $folderId, SessionInterface $session): bool
{
    if ((int) $session->get('user-admin') === 1) {
        return false;
    }

    $accessible = $session->get('user-accessible_folders') ?? [];

    return in_array($folderId, array_map('intval', (array) $accessible), true);
}

/**
 * Load the active LAPR roles attached to a set of TeamPass items.
 *
 * A vault item can be the password target of one managed account, the SSH
 * credential of one or more endpoints, or both. Deleted LAPR relationships are
 * deliberately excluded so stale links never lock or label an item.
 *
 * The module switch is authoritative: when LAPR is disabled no relation is
 * reported, so items behave exactly like ordinary items (no badge, no locked
 * field, no blocked delete or move). This is what prevents a disabled module
 * from leaving items permanently frozen — the only way to remove a managed
 * account is through the LAPR pages, which are themselves gated on the switch.
 * It also keeps the two extra queries off every item list on installations
 * that do not use LAPR.
 *
 * @param array $itemIds  TeamPass item ids
 * @param array $SETTINGS TeamPass settings
 * @return array<int, array<string, mixed>> Relations indexed by item id
 */
function laprGetItemRelations(array $itemIds, array $SETTINGS): array
{
    if ((int) ($SETTINGS['lapr_enabled'] ?? 0) !== 1) {
        return [];
    }

    $normalizedIds = array_values(array_unique(array_filter(
        array_map('intval', $itemIds),
        static fn (int $itemId): bool => $itemId > 0
    )));
    if (count($normalizedIds) === 0) {
        return [];
    }

    $relations = [];
    foreach ($normalizedIds as $itemId) {
        $relations[$itemId] = [
            'is_managed' => false,
            'is_credential' => false,
            'managed_account' => null,
            'credential_endpoints' => [],
        ];
    }

    $managedRows = DB::query(
        'SELECT a.id, a.item_id, a.username_cache, a.status, a.next_rotation_at,
                a.last_rotation_status, e.id AS endpoint_id, e.label AS endpoint_label,
                e.hostname, e.status AS endpoint_status, p.label AS policy_label,
                p.frequency_days AS policy_frequency_days, p.is_preset AS policy_is_preset
         FROM ' . prefixTable('lapr_accounts') . ' AS a
         LEFT JOIN ' . prefixTable('lapr_endpoints') . ' AS e ON e.id = a.endpoint_id
         LEFT JOIN ' . prefixTable('lapr_policies') . ' AS p ON p.id = a.policy_id
         WHERE a.item_id IN %li AND a.status != %s',
        $normalizedIds,
        'deleted'
    );
    foreach ($managedRows as $row) {
        $itemId = (int) $row['item_id'];
        if (isset($relations[$itemId]) === false) {
            continue;
        }
        $relations[$itemId]['is_managed'] = true;
        $relations[$itemId]['managed_account'] = [
            'id' => (int) $row['id'],
            'username' => (string) $row['username_cache'],
            'status' => (string) $row['status'],
            'next_rotation_at' => $row['next_rotation_at'],
            'last_rotation_status' => (string) $row['last_rotation_status'],
            'endpoint_id' => (int) $row['endpoint_id'],
            'endpoint_label' => (string) $row['endpoint_label'],
            'hostname' => (string) $row['hostname'],
            'endpoint_status' => (string) $row['endpoint_status'],
            'policy_label' => (string) ($row['policy_label'] ?? ''),
            'policy_frequency_days' => (int) ($row['policy_frequency_days'] ?? 0),
            'policy_is_preset' => (int) ($row['policy_is_preset'] ?? 0) === 1,
        ];
    }

    $credentialRows = DB::query(
        'SELECT id, label, hostname, ssh_username, ssh_credential_source, status
         FROM ' . prefixTable('lapr_endpoints') . '
         WHERE ssh_credential_source IN %li AND status != %s
         ORDER BY label ASC',
        $normalizedIds,
        'deleted'
    );
    foreach ($credentialRows as $row) {
        $itemId = (int) $row['ssh_credential_source'];
        if (isset($relations[$itemId]) === false) {
            continue;
        }
        $relations[$itemId]['is_credential'] = true;
        $relations[$itemId]['credential_endpoints'][] = [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'hostname' => (string) $row['hostname'],
            'ssh_username' => (string) $row['ssh_username'],
            'status' => (string) $row['status'],
        ];
    }

    return $relations;
}

/**
 * Language key blocking the deletion of at least one of the given items.
 *
 * Deleting an item still referenced by a managed account or an enrolled
 * endpoint would orphan the LAPR relationship (no FK exists) and break
 * rotation or endpoint authentication. Single and mass deletions share this
 * helper so both paths stay aligned.
 *
 * @param array $itemIds  TeamPass item ids
 * @param array $SETTINGS TeamPass settings
 * @return string Language key, or '' when the deletion is allowed
 */
function laprItemsDeletionBlocker(array $itemIds, array $SETTINGS): string
{
    foreach (laprGetItemRelations($itemIds, $SETTINGS) as $relation) {
        if ((bool) ($relation['is_managed'] ?? false) === true) {
            return 'lapr_item_managed_cannot_delete';
        }
        if ((bool) ($relation['is_credential'] ?? false) === true) {
            return 'lapr_item_credential_cannot_delete';
        }
    }

    return '';
}

/**
 * Language key blocking a move of the given items into a personal folder.
 *
 * LAPR reads item passwords server-side through the TP_USER key chain
 * (`laprReadItemPasswordAsTpUser()`), which only covers non-personal items.
 * Moving a managed target or an SSH credential into a personal folder would
 * therefore silently break every subsequent rotation or connection.
 *
 * @param array $itemIds  TeamPass item ids
 * @param array $SETTINGS TeamPass settings
 * @return string Language key, or '' when the move is allowed
 */
function laprItemsPersonalMoveBlocker(array $itemIds, array $SETTINGS): string
{
    foreach (laprGetItemRelations($itemIds, $SETTINGS) as $relation) {
        if ((bool) ($relation['is_managed'] ?? false) === true
            || (bool) ($relation['is_credential'] ?? false) === true
        ) {
            return 'lapr_item_cannot_move_to_personal';
        }
    }

    return '';
}
