<?php
/**
 * Teampass - Restore corrupted item passwords from backup tables
 *
 * Workflow:
 *   1. SCAN   – Identify corrupted items via TP_USER decryption path
 *   2. CONFIG – Ask for backup table names and field mappings
 *   3. RESTORE – Decrypt backup pw → verify → restore live items.pw + regenerate sharekeys
 *   4. REPORT – Summary
 *
 * Usage:
 *   php scripts/restore_from_backup.php [--dry-run] [--help]
 *
 * @file      restore_from_backup.php
 * @author    Nils Laumaillé (nils@teampass.net)
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
loadClasses('DB');

// ─── CLI Helpers ────────────────────────────────────────────────────────────

function prompt(string $question, string $default = ''): string
{
    $hint = $default !== '' ? " [\033[33m{$default}\033[0m]" : '';
    echo "  " . $question . $hint . ": ";
    $input = trim((string) fgets(STDIN));
    return $input !== '' ? $input : $default;
}

function promptYesNo(string $question, bool $default = true): bool
{
    $hint = $default ? '[\033[32mY\033[0m/n]' : '[y/\033[31mN\033[0m]';
    echo "  " . $question . " " . $hint . ": ";
    $input = strtolower(trim((string) fgets(STDIN)));
    if ($input === '') return $default;
    return in_array($input, ['y', 'yes', 'o', 'oui'], true);
}

function printHeader(string $title, int $step): void
{
    echo "\n\033[1;34m═══ STEP {$step}: {$title} ═══\033[0m\n\n";
}

function ok(string $msg): void   { echo "    \033[32m✓\033[0m {$msg}\n"; }
function err(string $msg): void  { echo "    \033[31m✗\033[0m {$msg}\n"; }
function warn(string $msg): void { echo "    \033[33m⚠\033[0m {$msg}\n"; }
function info(string $msg): void { echo "    {$msg}\n"; }

// ─── Options ────────────────────────────────────────────────────────────────

$opts      = getopt('', ['dry-run', 'help', 'export-csv:']);
$isDryRun  = isset($opts['dry-run']);
$exportCsv = isset($opts['export-csv']) ? (string) $opts['export-csv'] : '';

if (isset($opts['help'])) {
    echo <<<HELP

\033[1mTeamPass - Restore corrupted passwords from backup tables\033[0m
═══════════════════════════════════════════════════════════

Usage:
  php scripts/restore_from_backup.php [options]

Options:
  --dry-run           Simulate only, no database changes
  --export-csv=FILE   Export corrupted items list to CSV and exit
  --help              Show this help

What the script does:
  1. Scans all items via TP_USER decryption path to find corrupted passwords
  2. Asks you for backup table names and field mappings
  3. For each corrupted item:
       - Finds TP_USER sharekey in the backup sharekeys table
       - Decrypts the backup item key (K_backup) with TP_USER RSA private key
       - Decrypts the backup items.pw with K_backup → verifies plaintext
       - If valid: restores items.pw from backup + regenerates all sharekeys
  4. Reports results

Backup table assumptions:
  - Backup tables are in the same MySQL database as TeamPass
  - They were imported from a mysqldump of a prior healthy state
  - TP_USER must have had a sharekey for the item in the backup (enc_v=1 or 3)

HELP;
    exit(0);
}

// ─── Banner ─────────────────────────────────────────────────────────────────

echo "\n";
echo "\033[1;37m╔════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;37m║  TeamPass - Restore corrupted passwords from backup    ║\033[0m\n";
echo "\033[1;37m╚════════════════════════════════════════════════════════╝\033[0m\n";

if ($isDryRun) {
    echo "\n\033[33m  ⚠  DRY-RUN mode — no database changes will be made\033[0m\n";
}
if ($exportCsv !== '') {
    echo "\n\033[36m  ℹ  CSV export mode → {$exportCsv}\033[0m\n";
}

// ════════════════════════════════════════════════════════════════════════════
// STEP 1 — Load TP_USER credentials
// ════════════════════════════════════════════════════════════════════════════

printHeader('Load TP_USER credentials', 1);

$tp = DB::queryFirstRow(
    'SELECT id, login, pw, encryption_version FROM ' . prefixTable('users') . ' WHERE id = %i',
    TP_USER_ID
);
if (empty($tp)) {
    err("TP_USER (id=" . TP_USER_ID . ") not found in users table.");
    exit(1);
}

$tpPk = DB::queryFirstRow(
    'SELECT private_key FROM ' . prefixTable('user_private_keys') . ' WHERE user_id = %i AND is_current = 1',
    TP_USER_ID
);
if (empty($tpPk) || empty($tpPk['private_key'])) {
    err("TP_USER private key not found in user_private_keys.");
    exit(1);
}

$tpPwdResult = cryption($tp['pw'], '', 'decrypt');
if (empty($tpPwdResult['string'])) {
    err("Failed to decrypt TP_USER password. Is SECUREFILE correct?");
    exit(1);
}
$tpPassword = $tpPwdResult['string'];

$tpPrivateKeyB64 = decryptPrivateKey($tpPassword, $tpPk['private_key']);
if (empty($tpPrivateKeyB64)) {
    err("Failed to decrypt TP_USER private key.");
    exit(1);
}
$tpPrivateKeyPem = base64_decode($tpPrivateKeyB64);
if (strpos($tpPrivateKeyPem, '-----BEGIN') === false) {
    err("TP_USER private key is not a valid PEM.");
    exit(1);
}

ok("TP_USER login={$tp['login']}, enc_v={$tp['encryption_version']}");
ok("Private key decrypted OK (" . strlen($tpPrivateKeyPem) . " bytes PEM)");

// ════════════════════════════════════════════════════════════════════════════
// STEP 2 — Scan current DB for corrupted items
// ════════════════════════════════════════════════════════════════════════════

printHeader('Scan for corrupted items via TP_USER', 2);

$tpSharekeys = DB::query(
    'SELECT sk.object_id, sk.share_key, i.label, i.pw, i.pw_len
     FROM ' . prefixTable('sharekeys_items') . ' sk
     JOIN ' . prefixTable('items') . ' i ON i.id = sk.object_id
     WHERE sk.user_id = %i
     ORDER BY sk.object_id',
    TP_USER_ID
);

$totalScanned  = count($tpSharekeys);
echo "  Scanning {$totalScanned} items...\n\n";

$okCount       = 0;
$corruptedList = []; // [id => ['label'=>, 'reason'=>, 'pw_len'=>, 'actual_len'=>]]

foreach ($tpSharekeys as $row) {
    $id = (int) $row['object_id'];
    try {
        $itemKey = decryptUserObjectKey($row['share_key'], $tpPrivateKeyB64);
        if (empty($itemKey)) {
            $corruptedList[$id] = ['label' => $row['label'], 'reason' => 'empty_key', 'pw_len' => (int)$row['pw_len'], 'actual_len' => 0];
            continue;
        }

        $decryptedB64 = doDataDecryption($row['pw'], $itemKey);
        if (empty($decryptedB64) && !empty($row['pw'])) {
            $corruptedList[$id] = ['label' => $row['label'], 'reason' => 'decrypt_failed', 'pw_len' => (int)$row['pw_len'], 'actual_len' => 0];
            continue;
        }

        $plaintext  = base64_decode((string) $decryptedB64);
        $storedLen  = (int) $row['pw_len'];
        $actualLen  = strlen($plaintext);
        $isBinary   = !empty($plaintext) && !mb_check_encoding($plaintext, 'UTF-8');
        $lenBadly   = ($storedLen > 0 && abs($storedLen - $actualLen) > 2);

        if ($isBinary || $lenBadly) {
            $corruptedList[$id] = [
                'label'      => $row['label'],
                'reason'     => $isBinary ? 'binary_bytes' : 'len_mismatch',
                'pw_len'     => $storedLen,
                'actual_len' => $actualLen,
            ];
        } else {
            $okCount++;
        }
    } catch (Exception $e) {
        $corruptedList[$id] = ['label' => $row['label'], 'reason' => 'exception: ' . $e->getMessage(), 'pw_len' => (int)$row['pw_len'], 'actual_len' => 0];
    }
}

$corruptedCount = count($corruptedList);
echo "  Items OK         : \033[32m{$okCount}\033[0m\n";
echo "  Items corrupted  : \033[31m{$corruptedCount}\033[0m\n\n";

if ($corruptedCount === 0) {
    ok("No corrupted items found — nothing to restore.");
    exit(0);
}

// Display corrupted list
echo "  \033[1mCorrupted items:\033[0m\n";
foreach ($corruptedList as $id => $d) {
    printf(
        "    id=\033[33m%-6s\033[0m  pw_len=%-4s  actual=%-4s  reason=%-20s  [%s]\n",
        $id,
        $d['pw_len'],
        $d['actual_len'],
        $d['reason'],
        substr($d['label'], 0, 50)
    );
}

// ─── Optional CSV export ─────────────────────────────────────────────────────

if ($exportCsv !== '') {
    $fp = fopen($exportCsv, 'w');
    if ($fp === false) {
        err("Cannot open {$exportCsv} for writing.");
        exit(1);
    }
    fputcsv($fp, ['item_id', 'label', 'reason', 'pw_len_stored', 'pw_len_actual']);
    foreach ($corruptedList as $id => $d) {
        fputcsv($fp, [$id, $d['label'], $d['reason'], $d['pw_len'], $d['actual_len']]);
    }
    fclose($fp);
    echo "\n";
    ok("Exported {$corruptedCount} items to {$exportCsv}");
    echo "\n  Run without --export-csv to proceed with restoration.\n\n";
    exit(0);
}

// ════════════════════════════════════════════════════════════════════════════
// STEP 3 — Configure backup tables
// ════════════════════════════════════════════════════════════════════════════

printHeader('Backup table configuration', 3);

echo "  The backup tables must be imported into the same MySQL database.\n";
echo "  TP_USER must have had a sharekey for each corrupted item in the backup.\n\n";

echo "  \033[1m— Backup sharekeys table —\033[0m\n";
$skTable    = prompt("Table name",                       "teampass_sharekeys_items_backup");
$skUserId   = prompt("Field for user_id",                "user_id");
$skObjId    = prompt("Field for object_id (item id)",    "object_id");
$skKeyField = prompt("Field for share_key",              "share_key");

echo "\n  \033[1m— Backup items table —\033[0m\n";
$itemTable    = prompt("Table name",                     "teampass_items_backup");
$itemIdField  = prompt("Field for item id",              "id");
$itemPwField  = prompt("Field for encrypted password",   "pw");

echo "\n  \033[1m— Options —\033[0m\n";
$verbose = promptYesNo("Show decrypted password (masked) for each restored item?", false);
echo "\n";

// Verify that the backup tables exist
foreach ([$skTable, $itemTable] as $tbl) {
    try {
        DB::queryFirstRow('SELECT 1 FROM %l LIMIT 1', $tbl);
        ok("Table \033[36m{$tbl}\033[0m found");
    } catch (Exception $e) {
        err("Table \033[36m{$tbl}\033[0m not accessible: " . $e->getMessage());
        echo "\n  Aborting — check table name and MySQL permissions.\n\n";
        exit(1);
    }
}

echo "\n";
if (!promptYesNo("Start restoration of {$corruptedCount} items?", true)) {
    echo "\n  Aborted.\n\n";
    exit(0);
}

// ════════════════════════════════════════════════════════════════════════════
// STEP 4 — Restore each corrupted item
// ════════════════════════════════════════════════════════════════════════════

printHeader('Restore corrupted items from backup', 4);

$cntRestored = 0;
$cntFailed   = 0;
$cntSkipped  = 0;
$failedIds   = [];

foreach ($corruptedList as $itemId => $d) {
    $label = substr($d['label'], 0, 60);
    echo "  \033[1mItem #{$itemId}\033[0m [{$label}]  (pw_len={$d['pw_len']}, reason={$d['reason']})\n";

    // 4a. Get TP_USER sharekey from backup sharekeys table
    try {
        $backupSk = DB::queryFirstRow(
            'SELECT %l FROM %l WHERE %l = %i AND %l = %i',
            $skKeyField,
            $skTable,
            $skObjId,   $itemId,
            $skUserId,  TP_USER_ID
        );
    } catch (Exception $e) {
        err("Cannot query backup sharekeys table: " . $e->getMessage());
        $cntFailed++;
        $failedIds[] = $itemId;
        continue;
    }

    if (empty($backupSk) || empty($backupSk[$skKeyField])) {
        warn("No backup sharekey found for TP_USER → cannot restore (try a different backup)");
        $cntSkipped++;
        continue;
    }

    // 4b. Decrypt backup sharekey → backup item key K_backup
    $backupItemKey = decryptUserObjectKey($backupSk[$skKeyField], $tpPrivateKeyB64);
    if (empty($backupItemKey)) {
        err("Cannot decrypt backup sharekey with TP_USER RSA key");
        $cntFailed++;
        $failedIds[] = $itemId;
        continue;
    }

    // 4c. Get backup items.pw
    try {
        $backupItem = DB::queryFirstRow(
            'SELECT %l FROM %l WHERE %l = %i',
            $itemPwField,
            $itemTable,
            $itemIdField, $itemId
        );
    } catch (Exception $e) {
        err("Cannot query backup items table: " . $e->getMessage());
        $cntFailed++;
        $failedIds[] = $itemId;
        continue;
    }

    if (empty($backupItem) || !isset($backupItem[$itemPwField]) || $backupItem[$itemPwField] === '') {
        warn("Item not found in backup items table or has no password → skipping");
        $cntSkipped++;
        continue;
    }

    $backupPw = $backupItem[$itemPwField];

    // 4d. Decrypt backup items.pw → verify plaintext
    $decryptedB64 = doDataDecryption($backupPw, $backupItemKey);
    if (empty($decryptedB64)) {
        err("Cannot decrypt backup items.pw with backup item key (backup may also be corrupted)");
        $cntFailed++;
        $failedIds[] = $itemId;
        continue;
    }

    $plaintext = base64_decode((string) $decryptedB64);

    if (!mb_check_encoding($plaintext, 'UTF-8')) {
        err("Decrypted backup password contains binary bytes — backup is also corrupted for this item");
        $cntFailed++;
        $failedIds[] = $itemId;
        continue;
    }

    $pwLen = strlen($plaintext);
    if ($verbose) {
        info("Recovered password : [" . str_repeat('*', $pwLen) . "] ({$pwLen} chars)");
    } else {
        info("Recovered password : {$pwLen} chars (use --verbose to display masked)");
    }

    // Compare with expected pw_len
    if ($d['pw_len'] > 0 && abs($d['pw_len'] - $pwLen) > 2) {
        warn("pw_len mismatch: stored={$d['pw_len']}, recovered={$pwLen} — proceeding anyway");
    }

    if ($isDryRun) {
        ok("[DRY-RUN] Would restore items.pw and regenerate all sharekeys");
        $cntRestored++;
        continue;
    }

    // ── 4e. Restore live items.pw from backup ──────────────────────────────

    DB::update(
        prefixTable('items'),
        [
            'pw'     => $backupPw,
            'pw_len' => $pwLen,
        ],
        'id = %i',
        $itemId
    );

    // ── 4f. Regenerate all sharekeys using K_backup ────────────────────────

    $liveSharekeys = DB::query(
        'SELECT sk.increment_id, sk.user_id, sk.encryption_version, u.login, u.public_key
         FROM ' . prefixTable('sharekeys_items') . ' sk
         LEFT JOIN ' . prefixTable('users') . ' u ON u.id = sk.user_id
         WHERE sk.object_id = %i
         ORDER BY u.login',
        $itemId
    );

    $skFixed  = 0;
    $skErrors = 0;

    foreach ($liveSharekeys as $sk) {
        $pubKey = $sk['public_key'] ?? '';
        if (empty($pubKey)) {
            continue; // skip users with no public key (system users or orphans)
        }

        try {
            $newShareKey = encryptUserObjectKey($backupItemKey, $pubKey);
            if (empty($newShareKey)) {
                throw new RuntimeException("encryptUserObjectKey returned empty");
            }

            DB::update(
                prefixTable('sharekeys_items'),
                [
                    'share_key'          => $newShareKey,
                    'encryption_version' => 3,
                ],
                'increment_id = %i',
                $sk['increment_id']
            );
            $skFixed++;
        } catch (Exception $e) {
            $skErrors++;
        }
    }

    $skMsg = "{$skFixed} sharekeys regenerated";
    if ($skErrors > 0) {
        $skMsg .= ", \033[31m{$skErrors} errors\033[0m";
    }
    ok("Restored — items.pw updated, {$skMsg}");
    $cntRestored++;
}

// ════════════════════════════════════════════════════════════════════════════
// STEP 5 — Report
// ════════════════════════════════════════════════════════════════════════════

printHeader('Summary', 5);

$pad = 35;
echo str_pad("  Total items scanned", $pad) . ": {$totalScanned}\n";
echo str_pad("  Total corrupted found", $pad) . ": {$corruptedCount}\n";
echo str_pad("  \033[32mSuccessfully restored\033[0m", $pad + 9) . ": {$cntRestored}\n";
echo str_pad("  \033[33mSkipped (no backup data)\033[0m", $pad + 18) . ": {$cntSkipped}\n";
echo str_pad("  \033[31mFailed\033[0m", $pad + 9) . ": {$cntFailed}\n";

if (!empty($failedIds)) {
    echo "\n  Failed item IDs: " . implode(', ', $failedIds) . "\n";
}

if ($isDryRun) {
    echo "\n  \033[33m[DRY-RUN] No database changes were made.\033[0m\n";
    echo "  Run without --dry-run to apply the restoration.\n";
} elseif ($cntRestored > 0) {
    echo "\n  \033[32mUsers must log out and back in to see restored passwords.\033[0m\n";
    echo "  (Session cache may still hold the old key material.)\n";
}

echo "\n";
