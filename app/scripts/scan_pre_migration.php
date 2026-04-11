<?php
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
 * @file      scan_pre_migration.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009 - 2026 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

/**
 * Pre-migration corruption scan — TeamPass 3.1.5.x → 3.1.6.x
 *
 * Run this script on your 3.1.5.x installation BEFORE upgrading to 3.1.6.x.
 * It identifies items whose passwords are already corrupted in the current
 * database (and will remain broken after migration), and simulates the
 * AES SHA-256 false-positive risk (Bug #1, present in 3.1.6.0–3.1.6.7,
 * fixed in 3.1.6.8+ by the PEM guard).
 *
 * This script is READ-ONLY: it never writes to the database.
 *
 * Usage:
 *   php scripts/scan_pre_migration.php [limit]
 *
 *   limit  Maximum number of corrupted items to report (default: 2000).
 *
 * Corruption reasons detected:
 *   empty_key      — sharekey cannot be RSA-decrypted (already broken in v1)
 *   decrypt_failed — sharekey decrypts but AES decryption of the blob fails
 *   binary_bytes   — decryption succeeds but result is not valid UTF-8
 *   len_mismatch   — decrypted length differs significantly from pw_len
 *   exception      — unexpected exception during decryption
 *
 * Bug #1 simulation (aes_sha256_false_positive):
 *   Tries SHA-256 PBKDF2 AES on TP_USER's encrypted private key.
 *   If PKCS#7 padding is valid but the result is not a PEM key, this user
 *   WOULD HAVE lost ALL passwords on an unpatched 3.1.6.0–3.1.6.7 upgrade.
 *   3.1.6.8+ is not affected. Upgrade directly to 3.1.6.8 or later.
 */

// Block direct web access
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

$rootPath = dirname(__DIR__);

// ── Bootstrap ──────────────────────────────────────────────────────────────────

require_once $rootPath . '/includes/config/settings.php';
require_once $rootPath . '/includes/config/include.php';
require_once $rootPath . '/vendor/autoload.php';

// Locate phpseclib v1: try 3.1.6.x path first, then 3.1.5.x typical location
$_v1Candidates = [
    $rootPath . '/includes/libraries/phpseclibV1',   // 3.1.6.x
    $rootPath . '/includes/libraries/phpseclib',     // 3.1.5.x
];
$_phpsecV1Base = null;
foreach ($_v1Candidates as $_candidate) {
    if (file_exists($_candidate . '/Crypt/AES.php')) {
        $_phpsecV1Base = $_candidate;
        break;
    }
}
if ($_phpsecV1Base === null) {
    fwrite(STDERR, 'ERROR: phpseclib v1 not found. Checked: ' . implode(', ', $_v1Candidates) . PHP_EOL);
    exit(1);
}

// Set include path so phpseclib v1 internal require_once calls resolve correctly
set_include_path(get_include_path() . PATH_SEPARATOR . $_phpsecV1Base);
require_once $_phpsecV1Base . '/Crypt/AES.php';
require_once $_phpsecV1Base . '/Crypt/RSA.php';
unset($_v1Candidates, $_candidate, $_phpsecV1Base);

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

// PHPStan stubs for phpseclib v1 classes loaded at runtime via require_once.
// The if (false) block is never executed but gives PHPStan the type information it needs.
/** @phpstan-ignore if.alwaysFalse */
if (false) {
    define('CRYPT_RSA_ENCRYPTION_OAEP', 1);
    class Crypt_AES
    {
        public function setIV(string $iv): void {}
        public function setPassword(string $password, string $method = '', string $hash = '', string $salt = '', int $count = 1000, int $keyLen = 16): void {}
        /** @return string|false */
        public function decrypt(string $data) { return ''; }
    }
    class Crypt_RSA
    {
        public function setEncryptionMode(int $mode): void {}
        public function loadKey(string $key): bool { return true; }
        /** @return string|false */
        public function decrypt(string $data) { return ''; }
    }
}

// ── Database connection ────────────────────────────────────────────────────────

/**
 * Decrypt a Defuse-encrypted value using the master key file.
 * Returns the value unchanged if it is not Defuse-encrypted.
 */
function preMigDefuseDecrypt(string $value): string
{
    if (strncmp($value, 'def', 3) !== 0) {
        return $value;
    }
    try {
        $ascii = file_get_contents(SECUREPATH . '/' . SECUREFILE);
        if ($ascii === false) {
            return '';
        }
        $key = Key::loadFromAsciiSafeString(trim((string) $ascii));
        return Crypto::decrypt($value, $key);
    } catch (Exception $e) {
        return '';
    }
}

DB::$host     = DB_HOST;
DB::$user     = DB_USER;
DB::$password = preMigDefuseDecrypt(DB_PASSWD);
DB::$dbName   = DB_NAME;
DB::$port     = defined('DB_PORT') ? (int) DB_PORT : 3306;
DB::$encoding = defined('DB_ENCODING') ? DB_ENCODING : 'utf8mb4';

/**
 * Returns the full table name for a given base name (applies DB_PREFIX).
 */
function preMigTable(string $name): string
{
    return DB_PREFIX . $name;
}

// ── Crypto layer (phpseclib v1, pure SHA-1) ────────────────────────────────────

/**
 * Decrypt the AES-wrapped RSA private key stored in user_private_keys.private_key.
 *
 * Encryption parameters (phpseclib v1 standard):
 *   - Algorithm : AES-CBC
 *   - Key derivation : PBKDF2-SHA1, salt='phpseclib/salt', 1000 iterations, 16-byte key
 *   - IV : 16 zero bytes (phpseclib v1 PBKDF2 does not derive the IV)
 *
 * Returns base64_encode(PEM) on success, '' on failure.
 *
 * @param string $password            Plaintext password used to wrap the key
 * @param string $encryptedPrivKeyB64 base64-encoded AES ciphertext (from DB column)
 */
function preMigDecryptPrivateKey(string $password, string $encryptedPrivKeyB64): string
{
    if ($password === '' || $encryptedPrivKeyB64 === '') {
        return '';
    }

    $ciphertext = base64_decode($encryptedPrivKeyB64, true);
    if ($ciphertext === false || $ciphertext === '') {
        return '';
    }

    foreach ([$password, (new \voku\helper\AntiXSS())->xss_clean($password)] as $pwd) {
        /** @phpstan-ignore identical.alwaysFalse */
        if ($pwd === '') continue;
        $aes = new Crypt_AES();
        $aes->setIV(str_repeat("\0", 16));
        $aes->setPassword($pwd, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000, 16);
        $pem = $aes->decrypt($ciphertext);
        if ($pem !== false && $pem !== '' && strpos($pem, '-----BEGIN') !== false) {
            return base64_encode($pem);
        }
    }

    return '';
}

/**
 * Decrypt a sharekey (RSA-OAEP-SHA1) using TP_USER's private key.
 *
 * Returns base64_encode(objectKey) on success, '' on failure.
 *
 * @param string $shareKeyB64   base64-encoded RSA ciphertext (share_key column)
 * @param string $privateKeyB64 base64_encode(PEM) returned by preMigDecryptPrivateKey()
 */
function preMigDecryptSharekey(string $shareKeyB64, string $privateKeyB64): string
{
    $ciphertext = base64_decode($shareKeyB64, true);
    $pem        = base64_decode($privateKeyB64, true);
    if ($ciphertext === false || $pem === false || $ciphertext === '' || $pem === '') {
        return '';
    }

    $rsa = new Crypt_RSA();
    $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_OAEP);
    // Default hash in phpseclib v1 OAEP is SHA-1 — no explicit setHash() needed
    if ($rsa->loadKey($pem) === false) {
        return '';
    }

    $objectKey = $rsa->decrypt($ciphertext);
    if ($objectKey === false || $objectKey === '') {
        return '';
    }

    return base64_encode((string) $objectKey);
}

/**
 * Decrypt an item's password blob (AES-CBC PBKDF2-SHA1, 16-byte key, zero IV).
 *
 * Returns base64_encode(plaintext) on success, '' on failure.
 *
 * @param string $pwB64        base64-encoded AES ciphertext (items.pw column)
 * @param string $objectKeyB64 base64_encode(objectKey) returned by preMigDecryptSharekey()
 */
function preMigDecryptPassword(string $pwB64, string $objectKeyB64): string
{
    if ($pwB64 === '' || $objectKeyB64 === '') {
        return '';
    }

    $ciphertext = base64_decode($pwB64, true);
    $objectKey  = base64_decode($objectKeyB64, true);
    if ($ciphertext === false || $objectKey === false || $ciphertext === '' || $objectKey === '') {
        return '';
    }

    $aes = new Crypt_AES();
    $aes->setIV(str_repeat("\0", 16));
    $aes->setPassword($objectKey, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000, 16);
    $plaintext = $aes->decrypt($ciphertext);

    if ($plaintext === false) {
        return '';
    }

    return base64_encode((string) $plaintext);
}

/**
 * Simulate the AES SHA-256 false-positive bug (Bug #1 from 3.1.6.0–3.1.6.7).
 *
 * During migration, migrateAllUserKeysToV3() tried SHA-256 PBKDF2 AES to
 * re-wrap the private key. If the SHA-1 ciphertext happened to produce valid
 * PKCS#7 padding under SHA-256, the garbage result was written back to the DB,
 * permanently destroying all passwords for that user.
 *
 * 3.1.6.8+ added a PEM guard (\`-----BEGIN\` check) that prevents this.
 * This function detects whether TP_USER would have been affected.
 *
 * Returns true if the private key is vulnerable (SHA-256 produces valid PKCS#7
 * padding but the result is NOT a PEM-formatted RSA key).
 *
 * @param string $password            TP_USER's plaintext password
 * @param string $encryptedPrivKeyB64 base64-encoded AES ciphertext from user_private_keys
 */
function preMigSimulateSha256FalsePositive(string $password, string $encryptedPrivKeyB64): bool
{
    $ciphertext = base64_decode($encryptedPrivKeyB64, true);
    if ($password === '' || $ciphertext === false || $ciphertext === '') {
        return false;
    }

    $aes = new Crypt_AES();
    $aes->setIV(str_repeat("\0", 16));
    $aes->setPassword($password, 'pbkdf2', 'sha256', 'phpseclib/salt', 1000, 16);
    $result = $aes->decrypt($ciphertext);

    if ($result === false || $result === '') {
        return false;
    }

    // Validate PKCS#7 padding manually (phpseclib strips it, so we must check
    // the raw decrypt output from a separate instance with padding disabled)
    // Workaround: re-decrypt without PKCS#7 stripping by checking the plaintext length
    // heuristic — if strpos('-----BEGIN') is absent AND the decrypt succeeded, it is
    // a false positive (phpseclib v1 only returns non-false when PKCS#7 is valid).
    $isPem = (strpos($result, '-----BEGIN') !== false);
    return !$isPem;
}

// ── Main scan ──────────────────────────────────────────────────────────────────

/**
 * Scan all items accessible to TP_USER and detect corrupted passwords.
 *
 * @param int $limit Maximum number of corrupted items to collect.
 *
 * @return array{count:int, ok_count:int, items:array<int, array<string, mixed>>, bug1_risk:bool, summary:array<string, mixed>}
 */
function tpPreMigrationScan(int $limit = 2000): array
{
    if ($limit < 1) {
        $limit = 1;
    }

    // Load TP_USER password (Defuse-encrypted in DB)
    $tpUser = DB::queryFirstRow(
        'SELECT pw FROM ' . preMigTable('users') . ' WHERE id=%i',
        TP_USER_ID
    );
    if (!is_array($tpUser) || !isset($tpUser['pw'])) {
        throw new RuntimeException('Unable to load TP_USER (id=' . TP_USER_ID . ') from database.');
    }

    $tpPassword = preMigDefuseDecrypt((string) $tpUser['pw']);
    if ($tpPassword === '') {
        throw new RuntimeException(
            'Failed to decrypt TP_USER password. Verify master key at: ' . SECUREPATH . '/' . SECUREFILE
        );
    }

    // Load TP_USER current private key
    $tpPk = DB::queryFirstRow(
        'SELECT private_key FROM ' . preMigTable('user_private_keys') . ' WHERE user_id=%i AND is_current=1',
        TP_USER_ID
    );
    if (!is_array($tpPk) || !isset($tpPk['private_key'])) {
        throw new RuntimeException('Unable to load TP_USER private key from user_private_keys table.');
    }

    $encryptedPrivKey = (string) $tpPk['private_key'];

    // Decrypt TP_USER private key using v1 AES (SHA-1 PBKDF2)
    $tpPrivKeyB64 = preMigDecryptPrivateKey($tpPassword, $encryptedPrivKey);
    if ($tpPrivKeyB64 === '') {
        throw new RuntimeException(
            'Failed to decrypt TP_USER private key. The private key may already be corrupted, ' .
            'or the master key file is incorrect.'
        );
    }

    // Bug #1 simulation: would SHA-256 AES produce a false positive on this private key?
    $bug1Risk = preMigSimulateSha256FalsePositive($tpPassword, $encryptedPrivKey);

    // Fetch all items accessible to TP_USER via sharekeys
    // Note: 'deleted_at' column was introduced in 3.1.6.x; using 'inactif=0' for 3.1.5.x compat
    $rows = DB::query(
        'SELECT sk.object_id, sk.share_key,
                i.label, i.pw, i.pw_len, i.created_at,
                (SELECT MAX(l.date)
                 FROM ' . preMigTable('log_items') . ' l
                 WHERE l.id_item = sk.object_id AND l.action = \'at_password_shown\') AS last_shown
         FROM ' . preMigTable('sharekeys_items') . ' sk
         JOIN ' . preMigTable('items') . ' i ON i.id = sk.object_id
         WHERE sk.user_id = %i
           AND i.inactif = 0
         ORDER BY sk.object_id',
        TP_USER_ID
    );

    /** @var array<int, array<string, mixed>> $corrupted */
    $corrupted = [];
    $ok        = 0;

    foreach ($rows as $row) {
        $createdAtHuman = ($row['created_at'] !== null && (int) $row['created_at'] > 0)
            ? date('Y-m-d H:i:s', (int) $row['created_at'])
            : 'n/a';
        $lastShownHuman = $row['last_shown'] !== null
            ? date('Y-m-d H:i:s', (int) $row['last_shown'])
            : 'never';

        try {
            // Step 1: RSA-OAEP-SHA1 decrypt the sharekey
            $objectKeyB64 = preMigDecryptSharekey((string) $row['share_key'], $tpPrivKeyB64);
            if ($objectKeyB64 === '') {
                $corrupted[] = [
                    'id'               => (int) $row['object_id'],
                    'label'            => (string) $row['label'],
                    'reason'           => 'empty_key',
                    'len_stored'       => (int) $row['pw_len'],
                    'len_actual'       => 0,
                    'created_at_human' => $createdAtHuman,
                    'last_shown_human' => $lastShownHuman,
                ];
                if (count($corrupted) >= $limit) break;
                continue;
            }

            // Step 2: AES-CBC-SHA1 decrypt the password blob
            $decryptedB64 = preMigDecryptPassword((string) $row['pw'], $objectKeyB64);
            if ($decryptedB64 === '' && (string) $row['pw'] !== '') {
                $corrupted[] = [
                    'id'               => (int) $row['object_id'],
                    'label'            => (string) $row['label'],
                    'reason'           => 'decrypt_failed',
                    'len_stored'       => (int) $row['pw_len'],
                    'len_actual'       => 0,
                    'created_at_human' => $createdAtHuman,
                    'last_shown_human' => $lastShownHuman,
                ];
                if (count($corrupted) >= $limit) break;
                continue;
            }

            $plaintext = base64_decode($decryptedB64, true);
            if ($plaintext === false) {
                $plaintext = '';
            }

            $storedLen   = (int) $row['pw_len'];
            $actualLen   = strlen($plaintext);
            $hasBinary   = $plaintext !== '' && mb_check_encoding($plaintext, 'UTF-8') === false;
            $lenMismatch = $storedLen > 0 && abs($storedLen - $actualLen) > 2;

            if ($hasBinary || $lenMismatch) {
                $corrupted[] = [
                    'id'               => (int) $row['object_id'],
                    'label'            => (string) $row['label'],
                    'reason'           => $hasBinary ? 'binary_bytes' : 'len_mismatch',
                    'len_stored'       => $storedLen,
                    'len_actual'       => $actualLen,
                    'created_at_human' => $createdAtHuman,
                    'last_shown_human' => $lastShownHuman,
                ];
            } else {
                $ok++;
            }

            if (count($corrupted) >= $limit) {
                break;
            }
        } catch (Exception $e) {
            $corrupted[] = [
                'id'               => (int) $row['object_id'],
                'label'            => (string) $row['label'],
                'reason'           => 'exception',
                'exception_message'=> $e->getMessage(),
                'len_stored'       => (int) $row['pw_len'],
                'len_actual'       => 0,
                'created_at_human' => $createdAtHuman,
                'last_shown_human' => $lastShownHuman,
            ];
            if (count($corrupted) >= $limit) {
                break;
            }
        }
    }

    $total = count($corrupted) + $ok;

    // Build summary
    /** @var array<string, int> $byReason */
    $byReason         = [];
    $neverShown       = 0;
    $createdTimestamps = [];

    foreach ($corrupted as $c) {
        $r = (string) ($c['reason'] ?? 'unknown');
        $byReason[$r] = ($byReason[$r] ?? 0) + 1;
        if (($c['last_shown_human'] ?? 'never') === 'never') {
            $neverShown++;
        }
        if (!empty($c['created_at_human']) && $c['created_at_human'] !== 'n/a') {
            $createdTimestamps[] = $c['created_at_human'];
        }
    }
    arsort($byReason);
    sort($createdTimestamps);

    return [
        'count'     => count($corrupted),
        'ok_count'  => $ok,
        'items'     => $corrupted,
        'bug1_risk' => $bug1Risk,
        'summary'   => [
            'total_scanned'    => $total,
            'ok_count'         => $ok,
            'corrupted_count'  => count($corrupted),
            'corruption_rate'  => $total > 0 ? round(count($corrupted) / $total * 100, 2) : 0.0,
            'by_reason'        => $byReason,
            'never_shown'      => $neverShown,
            'oldest_corrupted' => count($createdTimestamps) > 0 ? $createdTimestamps[0] : null,
            'newest_corrupted' => count($createdTimestamps) > 0 ? end($createdTimestamps) : null,
            'truncated'        => count($corrupted) >= $limit,
            'limit'            => $limit,
            'bug1_risk'        => $bug1Risk,
        ],
    ];
}

// ── CLI entrypoint ──────────────────────────────────────────────────────────────

$limit = (isset($argv[1]) && ctype_digit((string) $argv[1])) ? (int) $argv[1] : 2000;

try {
    $result = tpPreMigrationScan($limit);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$s   = $result['summary'];
$sep = str_repeat('─', 60);

// ── Bug #1 risk banner ─────────────────────────────────────────────────────────
if ($s['bug1_risk'] === true) {
    echo $sep . PHP_EOL;
    echo '  [WARNING] BUG #1 RISK — AES SHA-256 FALSE POSITIVE DETECTED' . PHP_EOL;
    echo $sep . PHP_EOL;
    echo '  TP_USER\'s encrypted private key is vulnerable to the AES SHA-256' . PHP_EOL;
    echo '  false-positive bug present in TeamPass 3.1.6.0 through 3.1.6.7.' . PHP_EOL;
    echo PHP_EOL;
    echo '  If you had upgraded to one of those intermediate versions, the first' . PHP_EOL;
    echo '  user login would have silently replaced TP_USER\'s private key with' . PHP_EOL;
    echo '  garbage, permanently destroying ALL passwords managed by TP_USER.' . PHP_EOL;
    echo PHP_EOL;
    echo '  SAFE ACTION: Upgrade directly to 3.1.6.8 or later (includes the PEM' . PHP_EOL;
    echo '  guard fix). Never run any 3.1.6.0–3.1.6.7 version on this database.' . PHP_EOL;
    echo $sep . PHP_EOL . PHP_EOL;
}

// ── Detail ─────────────────────────────────────────────────────────────────────
if ($s['corrupted_count'] > 0) {
    echo 'Detail:' . PHP_EOL;
    foreach ($result['items'] as $c) {
        $reason = (string) ($c['reason'] ?? '');
        if ($reason === 'exception' && isset($c['exception_message'])) {
            $reason .= ': ' . (string) $c['exception_message'];
        }
        echo '  id=' . str_pad((string) ($c['id'] ?? 0), 6)
            . ' len_stored=' . str_pad((string) ($c['len_stored'] ?? 0), 4)
            . ' len_actual=' . str_pad((string) ($c['len_actual'] ?? 0), 4)
            . ' created=' . str_pad(($c['created_at_human'] ?? 'n/a'), 19)
            . ' last_shown=' . str_pad(($c['last_shown_human'] ?? 'never'), 19)
            . ' reason=' . $reason
            . ' [' . substr((string) ($c['label'] ?? ''), 0, 40) . ']'
            . PHP_EOL;
    }
    echo PHP_EOL;
}

// ── Summary ────────────────────────────────────────────────────────────────────
echo $sep . PHP_EOL;
echo '  PRE-MIGRATION SCAN SUMMARY  (3.1.5.x → 3.1.6.x)' . PHP_EOL;
echo $sep . PHP_EOL;
echo sprintf('  Items scanned    : %d', $s['total_scanned']) . PHP_EOL;
echo sprintf('  OK               : %d', $s['ok_count']) . PHP_EOL;
echo sprintf('  Already corrupted: %d  (%.2f %%)', $s['corrupted_count'], $s['corruption_rate']) . PHP_EOL;
echo sprintf('  Bug #1 risk      : %s', $s['bug1_risk'] ? 'YES (see warning above)' : 'no') . PHP_EOL;
echo PHP_EOL;

if (!empty($s['by_reason'])) {
    echo '  Breakdown by reason:' . PHP_EOL;
    foreach ($s['by_reason'] as $reason => $cnt) {
        echo sprintf('    %-20s %d', $reason, $cnt) . PHP_EOL;
    }
    echo PHP_EOL;
}

echo sprintf('  Never shown      : %d  (among corrupted)', $s['never_shown']) . PHP_EOL;
echo sprintf('  Oldest corrupted : %s', $s['oldest_corrupted'] ?? 'n/a') . PHP_EOL;
echo sprintf('  Newest corrupted : %s', $s['newest_corrupted'] ?? 'n/a') . PHP_EOL;

if ($s['truncated']) {
    echo PHP_EOL . '  /!\ Results truncated to ' . $s['limit'] . ' items.' . PHP_EOL;
}

echo $sep . PHP_EOL;

// ── Reason explanations & fixes ────────────────────────────────────────────────
if (!empty($s['by_reason'])) {
    /** @var array<string, array{explanation: string, fix: string}> $reasonDocs */
    $reasonDocs = [
        'empty_key' => [
            'explanation' => 'The sharekey for this item cannot be RSA-decrypted with TP_USER\'s'
                . ' private key, resulting in an empty object key. The sharekey is corrupt or was'
                . ' encrypted with a different RSA key pair.',
            'fix' => 'These items are ALREADY broken before the migration and will remain broken'
                . ' after. Back up any known plaintext values now. After migrating, use Admin >'
                . ' Utilities > Re-encrypt to attempt sharekey regeneration (requires the item'
                . ' owner to be logged in so their key can re-encrypt).',
        ],
        'decrypt_failed' => [
            'explanation' => 'The sharekey decrypts successfully but the AES decryption of the'
                . ' password blob fails. The ciphertext is corrupted or was encrypted with a'
                . ' different object key than the one stored in the sharekey.',
            'fix' => 'The ciphertext is unrecoverable. Back up any known plaintext values now.'
                . ' After migrating, the password must be re-entered manually in the UI.',
        ],
        'binary_bytes' => [
            'explanation' => 'Decryption succeeds but the plaintext contains non-UTF-8 bytes —'
                . ' the decrypted data is garbage. Most likely cause: a previous AES SHA-256'
                . ' false-positive produced a result with valid PKCS#7 padding but wrong content.',
            'fix' => 'Same as decrypt_failed: unrecoverable from the current ciphertext.'
                . ' Back up any known plaintext values now and re-enter after migration.',
        ],
        'len_mismatch' => [
            'explanation' => 'Decryption succeeds and the plaintext is valid UTF-8, but its'
                . ' length differs from pw_len. This is caused by xss_clean() stripping or'
                . ' altering special characters (HTML tags, etc.) before encryption, while'
                . ' pw_len was recorded from the original unmodified input.',
            'fix' => 'Usually harmless — the stored password is likely correct. Verify the'
                . ' decrypted value in the UI before migrating. If correct, simply re-save to'
                . ' update pw_len. If incorrect, re-enter the password.',
        ],
        'exception' => [
            'explanation' => 'An unexpected PHP exception occurred during sharekey or ciphertext'
                . ' decryption. See the exception message in the detail list for the specific'
                . ' error. Common causes: malformed base64, invalid PEM structure, missing'
                . ' PHP extension (openssl, sodium).',
            'fix' => 'Investigate the exception message. For base64/PEM errors, the relevant'
                . ' DB record may be truncated — check column lengths. For missing extensions,'
                . ' ensure openssl is enabled in php.ini before migrating.',
        ],
    ];

    echo PHP_EOL . $sep . PHP_EOL;
    echo '  REASON EXPLANATIONS & SUGGESTED FIXES' . PHP_EOL;
    echo $sep . PHP_EOL;

    foreach (array_keys($s['by_reason']) as $reason) {
        $doc = $reasonDocs[$reason] ?? null;
        echo PHP_EOL . '  [' . strtoupper($reason) . ']' . PHP_EOL;
        if ($doc !== null) {
            echo '  Explanation:' . PHP_EOL;
            foreach (explode("\n", wordwrap('    ' . $doc['explanation'], 76, "\n    ")) as $line) {
                echo $line . PHP_EOL;
            }
            echo '  Fix:' . PHP_EOL;
            foreach (explode("\n", wordwrap('    ' . $doc['fix'], 76, "\n    ")) as $line) {
                echo $line . PHP_EOL;
            }
        } else {
            echo '  No documentation available for this reason code.' . PHP_EOL;
        }
    }

    echo PHP_EOL . $sep . PHP_EOL;
}
