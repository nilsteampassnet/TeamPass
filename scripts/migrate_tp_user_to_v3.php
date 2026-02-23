<?php
/**
 * Teampass - Migrate TP_USER internal account to phpseclib v3 (SHA-256)
 *
 * TP_USER (ID=9999997) is a special internal TeamPass account that holds a copy of
 * all item sharekeys, enabling key redistribution when new users are added.
 * It is excluded from the standard per-user migration (which runs at login time)
 * because TP_USER never logs in interactively.
 *
 * This script handles TP_USER's migration specifically:
 *
 * 1. Decrypts TP_USER's Defuse-encrypted password from the database
 * 2. Detects if TP_USER's RSA private key is AES-SHA-1 or AES-SHA-256 encrypted
 * 3. If SHA-1: re-encrypts private key with AES-SHA-256 and updates user_private_keys
 * 4. For each sharekey row with encryption_version=1:
 *    a. Attempts RSA-SHA-256 decryption (phpseclib v3)
 *    b. If that fails, attempts RSA-SHA-1 decryption (phpseclib v1)
 *    c. If SHA-1 was used: re-encrypts item key with RSA-SHA-256 and updates share_key column
 *    d. Sets encryption_version=3 in both cases
 * 5. Updates users.encryption_version=3 and phpseclibv3_migration_completed=1 for TP_USER
 *
 * NOTE: RSA-OAEP provides reliable version detection (unlike AES-CBC which can produce
 * false positives). A SHA-256 failure is definitive - the data was SHA-1 encrypted.
 *
 * Usage:
 *   php scripts/migrate_tp_user_to_v3.php [--dry-run|--migrate]
 *
 * Options:
 *   --dry-run   Show analysis without making changes (default)
 *   --migrate   Apply all migration changes
 *
 * @file      migrate_tp_user_to_v3.php
 * @author    Nils Laumaille (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/includes/config/settings.php';
require_once $rootPath . '/includes/config/include.php';
require_once $rootPath . '/sources/main.functions.php';

use TeampassClasses\CryptoManager\CryptoManager;

loadClasses('DB');

// Batch size for sharekey processing (memory/performance tradeoff)
const BATCH_SIZE = 50;

$options = getopt('', ['dry-run', 'migrate', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
TeamPass TP_USER Migration to phpseclib v3
==========================================

TP_USER never logs in, so it is excluded from the normal login-time migration.
This script performs the migration for TP_USER specifically.

Usage: php scripts/migrate_tp_user_to_v3.php [OPTIONS]

Options:
  --dry-run   Analyse and report without making any changes (default)
  --migrate   Apply all changes to the database
  --help      Show this help

What this script does:
  1. Decrypts TP_USER password (stored reversibly via Defuse encryption)
  2. Detects AES encryption version of TP_USER's RSA private key (SHA-1 or SHA-256)
  3. Re-encrypts private key with AES-SHA-256 if it was SHA-1 (older installations)
  4. For each sharekey with encryption_version=1:
       - Attempts RSA-SHA-256 decryption (phpseclib v3)
       - If that fails definitively, attempts RSA-SHA-1 (phpseclib v1)
       - If SHA-1: re-encrypts item key with RSA-SHA-256 and updates the share_key column
       - Sets encryption_version=3 in both cases
  5. Updates users.encryption_version=3 and migration_completed=1 for TP_USER

HELP;
    exit(0);
}

$dryRun = !isset($options['migrate']);

echo "\n=== TeamPass TP_USER Migration to phpseclib v3 ===\n\n";
if ($dryRun) {
    echo "DRY-RUN mode (default): no changes will be made. Use --migrate to apply.\n\n";
} else {
    echo "MIGRATE mode: changes will be written to the database.\n\n";
}

// Tables containing sharekeys (primary key column: increment_id)
$sharekeysTablesList = [
    'sharekeys_items',
    'sharekeys_fields',
    'sharekeys_files',
    'sharekeys_logs',
    'sharekeys_suggestions',
];

// ─────────────────────────────────────────────────────────────
// STEP 1 — Load TP_USER data
// ─────────────────────────────────────────────────────────────
echo "[1] Loading TP_USER data...\n";

$userTP = DB::queryFirstRow(
    'SELECT u.id, u.login, u.pw, u.public_key, u.encryption_version,
            u.phpseclibv3_migration_completed,
            pk.private_key, pk.id AS pk_id
     FROM ' . prefixTable('users') . ' AS u
     LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk
         ON (u.id = pk.user_id AND pk.is_current = 1)
     WHERE u.id = %i',
    TP_USER_ID
);

if (empty($userTP)) {
    echo "  ERROR: TP_USER (ID=" . TP_USER_ID . ") not found in database.\n\n";
    exit(1);
}

$tpLogin          = strval($userTP['login'] ?? '');
$tpEncVersion     = intval($userTP['encryption_version'] ?? 1);
$tpMigDone        = intval($userTP['phpseclibv3_migration_completed'] ?? 0);
$tpPkId           = intval($userTP['pk_id'] ?? 0);
$tpPublicKey      = strval($userTP['public_key'] ?? '');
$tpPrivateKeyEnc  = strval($userTP['private_key'] ?? '');
$tpPwEnc          = strval($userTP['pw'] ?? '');

echo "  login={$tpLogin} | encryption_version={$tpEncVersion} | migration_completed={$tpMigDone}\n";
echo "  user_private_keys.id={$tpPkId}\n\n";

// ─────────────────────────────────────────────────────────────
// STEP 2 — Decrypt TP_USER password
// ─────────────────────────────────────────────────────────────
echo "[2] Decrypting TP_USER's password (stored as Defuse-encrypted in users.pw)...\n";

$configManager = new \TeampassClasses\ConfigManager\ConfigManager();
$SETTINGS = $configManager->getAllSettings();

$decryptionResult = cryption($tpPwEnc, '', 'decrypt', $SETTINGS);
if ($decryptionResult['error'] !== false) {
    echo "  ERROR: Failed to decrypt TP_USER's password: " . strval($decryptionResult['error']) . "\n";
    echo "  Ensure the application master key (SECUREFILE) is accessible.\n\n";
    exit(1);
}

$tpUserPasswordClear = strval($decryptionResult['string'] ?? '');
if (empty($tpUserPasswordClear)) {
    echo "  ERROR: Decrypted password is empty.\n\n";
    exit(1);
}

echo "  Password decrypted successfully (length=" . strlen($tpUserPasswordClear) . " chars).\n\n";

// ─────────────────────────────────────────────────────────────
// STEP 3 — Detect & validate TP_USER private key encryption version
// ─────────────────────────────────────────────────────────────
echo "[3] Detecting TP_USER private key encryption version (AES)...\n";

if (empty($tpPrivateKeyEnc)) {
    echo "  ERROR: TP_USER has no private key in user_private_keys table.\n\n";
    exit(1);
}

$privateKeyRaw           = base64_decode($tpPrivateKeyEnc);
$detectedAesVersion      = 0;
$privateKeyClear         = '';
// $tpUserPasswordEffective tracks which form of the password successfully decrypted
// the private key. generateUserKeys() runs xss_clean() internally before AES key
// derivation, but users.pw stores the raw password (via Defuse). They differ when
// the password contains characters modified by AntiXSS (e.g. '<' or '>').
$tpUserPasswordEffective = $tpUserPasswordClear;

/**
 * Attempt to AES-decrypt the private key blob and validate the PEM output.
 * Tries SHA-256 first (v3), then SHA-1 (v1), with a PEM validity check.
 *
 * @param string $password The AES key derivation password to try.
 * @return array{decrypted: string, version: int}
 * @throws Exception If decryption fails with both algorithms.
 */
$attemptKeyDecrypt = static function (string $password) use ($privateKeyRaw): array {
    $result    = CryptoManager::aesDecryptWithVersionDetection($privateKeyRaw, $password, 'cbc');
    $decrypted = strval($result['data']);
    $version   = intval($result['version_used']);

    // AES-CBC has no MAC: SHA-256 can silently "succeed" with garbage (~0.4% chance
    // that PKCS7 padding accidentally validates). RSA private keys always begin with
    // '-----BEGIN', so if the result is not PEM, retry with SHA-1.
    if ($version === 3 && strpos($decrypted, '-----BEGIN') === false) {
        echo "  SHA-256 produced invalid PEM (false positive PKCS7 padding) — retrying with SHA-1...\n";
        $decrypted = (string) CryptoManager::aesDecrypt($privateKeyRaw, $password, 'cbc', 'sha1');
        $version   = 1;
    }

    return ['decrypted' => $decrypted, 'version' => $version];
};

// ── Attempt 1: raw Defuse-decrypted password ──────────────────────────────
$step3FirstError     = '';
$rawAttemptSucceeded = false;
try {
    ['decrypted' => $decrypted, 'version' => $versionUsed] = $attemptKeyDecrypt($tpUserPasswordClear);
    if (!empty($decrypted) && strpos($decrypted, '-----BEGIN') !== false) {
        $rawAttemptSucceeded = true;
        $detectedAesVersion  = $versionUsed;
        $privateKeyClear     = $decrypted;
        // $tpUserPasswordEffective already set to $tpUserPasswordClear
    } else {
        $step3FirstError = 'Decrypted data is not a valid RSA PEM key';
    }
} catch (Exception $e) {
    $step3FirstError = $e->getMessage();
}

// ── Attempt 2: AntiXSS-sanitized password (fallback) ─────────────────────
// generateUserKeys() calls $antiXss->xss_clean($userPwd) before using the
// password for AES key derivation, but the password stored in users.pw via
// Defuse encryption is the RAW value. When the password contains characters
// that xss_clean() modifies (e.g. '<', '>' from the symbol set of
// ComputerPasswordGenerator), the two values differ and the raw password
// cannot decrypt the private key.
if (!$rawAttemptSucceeded) {
    $antiXss                 = new \voku\helper\AntiXSS();
    $tpUserPasswordSanitized = $antiXss->xss_clean($tpUserPasswordClear);

    if ($tpUserPasswordSanitized === $tpUserPasswordClear) {
        // AntiXSS made no changes — the failure is not an xss_clean mismatch.
        echo "  ERROR: Decryption failed with raw password.\n";
        echo "  Details: " . $step3FirstError . "\n";
        echo "  AntiXSS did not modify the password, so the mismatch hypothesis does not apply.\n";
        echo "  The stored password or application master key may have changed since installation.\n\n";
        exit(1);
    }

    echo "  Raw password failed — retrying with AntiXSS-sanitized password...\n";
    echo "  Reason: The password contains characters modified by xss_clean() (e.g. '<' or '>').\n";
    echo "          generateUserKeys() sanitizes the password internally before AES key derivation,\n";
    echo "          but users.pw stores the raw password. This mismatch prevented decryption.\n";

    try {
        ['decrypted' => $decrypted, 'version' => $versionUsed] = $attemptKeyDecrypt($tpUserPasswordSanitized);
        if (empty($decrypted) || strpos($decrypted, '-----BEGIN') === false) {
            echo "  ERROR: AntiXSS-sanitized password also produced an invalid PEM result.\n";
            echo "  The private key data may be corrupted in the database.\n\n";
            exit(1);
        }
        $detectedAesVersion      = $versionUsed;
        $privateKeyClear         = $decrypted;
        $tpUserPasswordEffective = $tpUserPasswordSanitized;
        echo "  Decryption succeeded with AntiXSS-sanitized password.\n";
    } catch (Exception $e) {
        echo "  ERROR: Both raw and AntiXSS-sanitized passwords failed.\n";
        echo "  Raw password error:       " . $step3FirstError . "\n";
        echo "  Sanitized password error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

$aesLabel = $detectedAesVersion === 3 ? 'AES-SHA-256 (v3) — no re-encryption needed' : 'AES-SHA-1 (v1) — WILL re-encrypt to SHA-256';
echo "  Detected: {$aesLabel}\n";
echo "  PEM header: '" . substr($privateKeyClear, 0, 27) . "...'\n\n";

// ─────────────────────────────────────────────────────────────
// STEP 4 — Re-encrypt private key if AES-SHA-1
// ─────────────────────────────────────────────────────────────
$privateKeyNeedsUpdate  = false;
$newEncryptedPrivateKey = '';

if ($detectedAesVersion === 1) {
    echo "[4] Re-encrypting TP_USER private key: AES-SHA-1 → AES-SHA-256...\n";
    try {
        // Use $tpUserPasswordEffective: either the raw password (normal case) or the
        // AntiXSS-sanitized password (when xss_clean modified the original password).
        // This must match the password used by decryptPrivateKey() for future operations.
        $reEncrypted = CryptoManager::aesEncrypt($privateKeyClear, $tpUserPasswordEffective, 'cbc', 'sha256');
        $newEncryptedPrivateKey = base64_encode($reEncrypted);

        // Verify round-trip
        $verifyDecrypted = (string) CryptoManager::aesDecrypt($reEncrypted, $tpUserPasswordEffective, 'cbc', 'sha256');
        if ($verifyDecrypted !== $privateKeyClear) {
            echo "  ERROR: Round-trip verification failed! Aborting for safety.\n\n";
            exit(1);
        }

        $privateKeyNeedsUpdate = true;
        echo "  Re-encryption verified successfully.\n\n";
    } catch (Exception $e) {
        echo "  ERROR: Re-encryption failed: " . $e->getMessage() . "\n\n";
        exit(1);
    }
} else {
    echo "[4] Private key already AES-SHA-256 — no re-encryption needed.\n\n";
}

// ─────────────────────────────────────────────────────────────
// STEP 5 — Analyse sharekeys for TP_USER
// ─────────────────────────────────────────────────────────────
echo "[5] Analysing sharekeys for TP_USER...\n";

$tableStats = [];
$totalV1    = 0;
$totalAll   = 0;

foreach ($sharekeysTablesList as $tbl) {
    $stats = DB::queryFirstRow(
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as already_v3,
            SUM(CASE WHEN encryption_version != 3 OR encryption_version IS NULL THEN 1 ELSE 0 END) as needs_check,
            SUM(CASE WHEN (encryption_version != 3 OR encryption_version IS NULL) AND share_key != '' THEN 1 ELSE 0 END) as needs_check_nonempty,
            SUM(CASE WHEN (encryption_version != 3 OR encryption_version IS NULL) AND share_key = '' THEN 1 ELSE 0 END) as empty_keys
         FROM " . prefixTable($tbl) . "
         WHERE user_id = %i",
        TP_USER_ID
    );

    $total              = intval($stats['total'] ?? 0);
    $alreadyV3          = intval($stats['already_v3'] ?? 0);
    $needsCheck         = intval($stats['needs_check'] ?? 0);
    $needsCheckNonEmpty = intval($stats['needs_check_nonempty'] ?? 0);
    $emptyKeys          = intval($stats['empty_keys'] ?? 0);

    $tableStats[$tbl] = compact('total', 'alreadyV3', 'needsCheck', 'needsCheckNonEmpty', 'emptyKeys');
    $totalAll += $total;
    $totalV1  += $needsCheck;

    if ($total > 0) {
        echo "  {$tbl}: {$total} total | {$alreadyV3} already v3 | {$needsCheckNonEmpty} to decrypt+verify | {$emptyKeys} empty keys\n";
    } else {
        echo "  {$tbl}: (no rows)\n";
    }
}

echo "\n  Total: {$totalAll} sharekeys — {$totalV1} require RSA decryption check.\n";
echo "\n  NOTE: RSA-OAEP provides definitive version detection (no false positives).\n";
echo "  Each sharekey will be decrypted: if SHA-1, it will be re-encrypted with SHA-256.\n\n";

// ─────────────────────────────────────────────────────────────
// STEP 6 — Summary
// ─────────────────────────────────────────────────────────────
echo "[6] Summary of changes to apply:\n";

if ($privateKeyNeedsUpdate) {
    echo "  - Re-encrypt TP_USER private key: AES-SHA-1 → AES-SHA-256\n";
}
if ($tpEncVersion !== 3) {
    echo "  - users.encryption_version: {$tpEncVersion} → 3\n";
}
if ($tpMigDone !== 1) {
    echo "  - users.phpseclibv3_migration_completed: {$tpMigDone} → 1\n";
}
echo "  - Process {$totalV1} sharekeys: RSA decrypt → re-encrypt with SHA-256 if needed → set encryption_version=3\n";
echo "\n";

if ($dryRun) {
    echo "DRY-RUN: no changes applied. Run with --migrate to apply.\n\n";
    echo "=== Done ===\n\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────
// STEP 7 — Apply private key re-encryption + user table update
// ─────────────────────────────────────────────────────────────
echo "[7] Applying private key and user table changes...\n";

DB::startTransaction();
try {
    if ($privateKeyNeedsUpdate && $tpPkId > 0) {
        DB::update(
            prefixTable('user_private_keys'),
            ['private_key' => $newEncryptedPrivateKey],
            'id = %i',
            $tpPkId
        );
        echo "  user_private_keys updated (id={$tpPkId}): private key re-encrypted to AES-SHA-256.\n";
    }

    $usersUpdate = [];
    if ($tpEncVersion !== 3) {
        $usersUpdate['encryption_version'] = 3;
    }
    if ($tpMigDone !== 1) {
        $usersUpdate['phpseclibv3_migration_completed'] = 1;
        $usersUpdate['phpseclibv3_migration_task_id']   = null;
    }
    if (!empty($usersUpdate)) {
        DB::update(prefixTable('users'), $usersUpdate, 'id = %i', TP_USER_ID);
        echo "  users table updated: " . implode(', ', array_map(
            static fn(string $k, mixed $v): string => $k . '=' . ($v === null ? 'NULL' : (string) $v),
            array_keys($usersUpdate),
            $usersUpdate
        )) . "\n";
    }

    DB::commit();
    echo "  Committed.\n\n";
} catch (Exception $e) {
    DB::rollback();
    echo "  ERROR (rolled back): " . $e->getMessage() . "\n\n";
    exit(1);
}

// ─────────────────────────────────────────────────────────────
// STEP 8 — Migrate sharekeys: decrypt, re-encrypt if SHA-1, update
// ─────────────────────────────────────────────────────────────
echo "[8] Migrating sharekeys (batch size=" . BATCH_SIZE . ")...\n\n";

$totalProcessed   = 0;
$totalReEncrypted = 0;
$totalMetaOnly    = 0;
$totalEmpty       = 0;
$totalErrors      = 0;

foreach ($sharekeysTablesList as $tbl) {
    $stat = $tableStats[$tbl];
    if ($stat['needsCheck'] === 0) {
        echo "  [{$tbl}] No rows to process — skipping.\n";
        continue;
    }

    echo "  [{$tbl}] Processing {$stat['needsCheck']} rows...\n";

    $tableReEncrypted = 0;
    $tableMetaOnly    = 0;
    $tableEmpty       = 0;
    $tableErrors      = 0;

    do {
        // No OFFSET: rows already processed are excluded by the WHERE clause
        // (encryption_version=3 after update), so each query always fetches
        // the next batch of unprocessed rows from the beginning.
        $rows = DB::query(
            "SELECT increment_id, share_key
             FROM " . prefixTable($tbl) . "
             WHERE user_id = %i
               AND (encryption_version != 3 OR encryption_version IS NULL)
             ORDER BY increment_id ASC
             LIMIT %i",
            TP_USER_ID,
            BATCH_SIZE
        );

        if (empty($rows)) {
            break;
        }

        DB::startTransaction();
        try {
            foreach ($rows as $row) {
                $rowId    = intval($row['increment_id']);
                $shareKey = strval($row['share_key']);

                // Empty sharekey: just update metadata
                if ($shareKey === '') {
                    DB::update(
                        prefixTable($tbl),
                        ['encryption_version' => 3],
                        'increment_id = %i',
                        $rowId
                    );
                    $tableEmpty++;
                    continue;
                }

                $rawEncrypted = base64_decode($shareKey, true);
                if ($rawEncrypted === false) {
                    echo "    WARNING: increment_id={$rowId} — invalid base64 in share_key, skipping.\n";
                    $tableErrors++;
                    continue;
                }

                // Attempt RSA decryption with version detection.
                // RSA-OAEP is reliable: SHA-256 failure is definitive (no false positives).
                try {
                    $result      = CryptoManager::rsaDecryptWithVersionDetection($rawEncrypted, $privateKeyClear);
                    $itemKey    = strval($result['data']);
                    $rsaVersion = intval($result['version_used']);
                } catch (Exception $e) {
                    echo "    WARNING: increment_id={$rowId} — RSA decryption failed: " . $e->getMessage() . "\n";
                    $tableErrors++;
                    continue;
                }

                if ($rsaVersion === 1) {
                    // SHA-1 encrypted: re-encrypt with SHA-256
                    try {
                        $reEncrypted = CryptoManager::rsaEncrypt($itemKey, $tpPublicKey);
                        DB::update(
                            prefixTable($tbl),
                            [
                                'share_key'          => base64_encode($reEncrypted),
                                'encryption_version' => 3,
                            ],
                            'increment_id = %i',
                            $rowId
                        );
                        $tableReEncrypted++;
                    } catch (Exception $e) {
                        echo "    WARNING: increment_id={$rowId} — RSA re-encryption failed: " . $e->getMessage() . "\n";
                        $tableErrors++;
                    }
                } else {
                    // SHA-256: data already correct, just fix metadata
                    DB::update(
                        prefixTable($tbl),
                        ['encryption_version' => 3],
                        'increment_id = %i',
                        $rowId
                    );
                    $tableMetaOnly++;
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            echo "    ERROR: Batch at offset={$offset} rolled back: " . $e->getMessage() . "\n";
            $tableErrors += count($rows);
        }

        $totalProcessed += count($rows);

        // Progress indicator every 500 rows
        if ($totalProcessed % 500 === 0) {
            echo "    ... {$totalProcessed} rows processed so far\n";
        }

    } while (count($rows) === BATCH_SIZE);

    $totalReEncrypted += $tableReEncrypted;
    $totalMetaOnly    += $tableMetaOnly;
    $totalEmpty       += $tableEmpty;
    $totalErrors      += $tableErrors;

    echo "    Done: {$tableReEncrypted} re-encrypted (SHA-1→SHA-256), {$tableMetaOnly} metadata-only, {$tableEmpty} empty, {$tableErrors} errors.\n\n";
}

// ─────────────────────────────────────────────────────────────
// STEP 9 — Verification
// ─────────────────────────────────────────────────────────────
echo "[9] Verification...\n";

$verifyUser = DB::queryFirstRow(
    'SELECT encryption_version, phpseclibv3_migration_completed
     FROM ' . prefixTable('users') . '
     WHERE id = %i',
    TP_USER_ID
);

$verifyEncV    = intval($verifyUser['encryption_version'] ?? 0);
$verifyMigDone = intval($verifyUser['phpseclibv3_migration_completed'] ?? 0);
echo "  users.encryption_version = {$verifyEncV}         (expected: 3)\n";
echo "  users.phpseclibv3_migration_completed = {$verifyMigDone} (expected: 1)\n\n";

$hasWarnings = false;
foreach ($sharekeysTablesList as $tbl) {
    $remaining = intval(DB::queryFirstField(
        "SELECT COUNT(*) FROM " . prefixTable($tbl) . "
         WHERE user_id = %i AND (encryption_version != 3 OR encryption_version IS NULL)",
        TP_USER_ID
    ));
    if ($remaining > 0) {
        echo "  WARNING: {$tbl} still has {$remaining} rows with encryption_version != 3 (likely errors above)\n";
        $hasWarnings = true;
    }
}

if (!$hasWarnings) {
    echo "  All sharekeys are now encryption_version=3.\n";
}

echo "\n";
echo "=== Migration Complete ===\n";
echo "  Re-encrypted (RSA SHA-1→SHA-256): {$totalReEncrypted}\n";
echo "  Metadata-only update (already SHA-256): {$totalMetaOnly}\n";
echo "  Empty keys (metadata only): {$totalEmpty}\n";
echo "  Errors (check warnings above): {$totalErrors}\n";
echo "\n";
