<?php
/**
 * Teampass - Recovery script for a single corrupted item password
 *
 * Uses TP_USER's v1 sharekey (never migrated, always intact) to decrypt
 * the item key, verify/recover the item password, and optionally re-generate
 * all corrupted sharekeys for the target item.
 *
 * Usage:
 *   php scripts/recover_item_password.php --item=1765 --diagnose
 *   php scripts/recover_item_password.php --item=1765 --fix
 *
 * @file      recover_item_password.php
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

$options = getopt('', ['item:', 'diagnose', 'fix', 'help']);

if (isset($options['help']) || empty($options) || !isset($options['item'])) {
    echo <<<HELP
TeamPass - Item password recovery via TP_USER v1 sharekey
==========================================================

Usage:
  php scripts/recover_item_password.php --item=ID --diagnose
  php scripts/recover_item_password.php --item=ID --fix

Options:
  --item=ID     Item ID to recover
  --diagnose    Show what password is recoverable (read-only, no DB changes)
  --fix         Re-generate all sharekeys for the item using the recovered item key
  --help        Show this help

What --fix does:
  1. Reads TP_USER password from Defuse master key (SECUREFILE)
  2. Decrypts TP_USER private key
  3. Decrypts TP_USER v1 sharekey for the item → correct item key K
  4. Verifies decrypted password (shows it for confirmation)
  5. Re-encrypts K with each user's public key → updates ALL sharekeys to v3

HELP;
    exit(0);
}

$itemId = (int) $options['item'];
$isDiagnose = isset($options['diagnose']);
$isFix = isset($options['fix']);

if (!$isDiagnose && !$isFix) {
    echo "ERROR: Specify --diagnose or --fix\n";
    exit(1);
}

echo "\n=== TeamPass Item Recovery (item #{$itemId}) ===\n\n";

// ─── Step 1: Load item ─────────────────────────────────────────────────────
$item = DB::queryFirstRow(
    'SELECT id, label, pw, pw_len, encryption_type FROM ' . prefixTable('items') . ' WHERE id = %i',
    $itemId
);
if (empty($item)) {
    echo "ERROR: Item {$itemId} not found.\n";
    exit(1);
}
echo "Item: [{$itemId}] {$item['label']}\n";
echo "  pw length in DB : " . strlen($item['pw']) . " chars\n";
echo "  pw_len (stored) : {$item['pw_len']}\n";
echo "  encryption_type : {$item['encryption_type']}\n\n";

// ─── Step 2: Load TP_USER data ────────────────────────────────────────────
$tp = DB::queryFirstRow(
    'SELECT id, login, pw, encryption_version FROM ' . prefixTable('users') . ' WHERE id = %i',
    TP_USER_ID
);
if (empty($tp)) {
    echo "ERROR: TP_USER (id=" . TP_USER_ID . ") not found.\n";
    exit(1);
}
echo "TP_USER: login={$tp['login']}, enc_v={$tp['encryption_version']}\n";

// Load TP_USER private key
$tpPk = DB::queryFirstRow(
    'SELECT private_key FROM ' . prefixTable('user_private_keys') . '
     WHERE user_id = %i AND is_current = 1',
    TP_USER_ID
);
if (empty($tpPk) || empty($tpPk['private_key'])) {
    echo "ERROR: TP_USER private key not found in user_private_keys.\n";
    exit(1);
}

// Load TP_USER sharekey for this item
$tpSharekey = DB::queryFirstRow(
    'SELECT share_key, encryption_version FROM ' . prefixTable('sharekeys_items') . '
     WHERE object_id = %i AND user_id = %i',
    $itemId,
    TP_USER_ID
);
if (empty($tpSharekey)) {
    echo "ERROR: TP_USER has no sharekey for item {$itemId}.\n";
    exit(1);
}
echo "  TP_USER sharekey for item {$itemId}: v{$tpSharekey['encryption_version']}\n\n";

// ─── Step 3: Decrypt TP_USER password from DB ─────────────────────────────
echo "--- [1] Decrypting TP_USER password from DB (Defuse master key)...\n";
$tpPwdResult = cryption($tp['pw'], '', 'decrypt');
if (empty($tpPwdResult['string'])) {
    echo "ERROR: Failed to decrypt TP_USER password. Is SECUREFILE correct?\n";
    exit(1);
}
$tpPassword = $tpPwdResult['string'];
echo "  TP_USER password decrypted OK (" . strlen($tpPassword) . " chars)\n\n";

// ─── Step 4: Decrypt TP_USER private key ──────────────────────────────────
echo "--- [2] Decrypting TP_USER private key (AES SHA-1 v1)...\n";
$tpPrivateKeyB64 = decryptPrivateKey($tpPassword, $tpPk['private_key']);
if (empty($tpPrivateKeyB64)) {
    echo "ERROR: Failed to decrypt TP_USER private key.\n";
    exit(1);
}
// Quick PEM sanity check
$tpPrivateKeyPem = base64_decode($tpPrivateKeyB64);
if (strpos($tpPrivateKeyPem, '-----BEGIN') === false) {
    echo "ERROR: Decrypted private key is not a valid PEM (might need SHA-1 retry).\n";
    exit(1);
}
echo "  Private key decrypted OK, PEM valid (" . strlen($tpPrivateKeyPem) . " bytes)\n\n";

// ─── Step 5: Decrypt TP_USER sharekey → item key K ────────────────────────
echo "--- [3] Decrypting TP_USER sharekey for item {$itemId} (RSA v{$tpSharekey['encryption_version']})...\n";
$itemKeyB64 = decryptUserObjectKey($tpSharekey['share_key'], $tpPrivateKeyB64);
if (empty($itemKeyB64)) {
    echo "ERROR: Failed to decrypt sharekey. TP_USER sharekey might be corrupted.\n";
    exit(1);
}
echo "  Item key recovered (" . strlen(base64_decode($itemKeyB64)) . " bytes raw)\n\n";

// ─── Step 6: Decrypt item password ────────────────────────────────────────
echo "--- [4] Decrypting item password with recovered key...\n";
$decryptedPwB64 = doDataDecryption($item['pw'], $itemKeyB64);
if (empty($decryptedPwB64)) {
    echo "ERROR: Failed to decrypt item password with recovered item key.\n";
    echo "  → The item key from TP_USER sharekey might not match the current pw encryption.\n";
    exit(1);
}
$decryptedPw = base64_decode($decryptedPwB64);
echo "  Recovered password : [{$decryptedPw}]\n";
echo "  Length             : " . strlen($decryptedPw) . " chars (pw_len in DB: {$item['pw_len']})\n\n";

if (!$isFix) {
    echo "=== DIAGNOSE COMPLETE (read-only, no changes made) ===\n\n";
    echo "To re-generate all sharekeys for this item with the correct item key, run:\n";
    echo "  php scripts/recover_item_password.php --item={$itemId} --fix\n\n";
    exit(0);
}

// ─── Step 7: FIX - Re-generate all sharekeys ──────────────────────────────
echo "--- [5] FIX: Re-generating all sharekeys for item {$itemId} with recovered item key...\n\n";

// Load all users who have a sharekey for this item
$sharekeys = DB::query(
    'SELECT sk.increment_id, sk.user_id, sk.encryption_version, u.login, u.public_key
     FROM ' . prefixTable('sharekeys_items') . ' sk
     LEFT JOIN ' . prefixTable('users') . ' u ON u.id = sk.user_id
     WHERE sk.object_id = %i
     ORDER BY sk.encryption_version, u.login',
    $itemId
);

$fixed = 0;
$skipped = 0;
$errors = 0;

foreach ($sharekeys as $sk) {
    $userId   = (int) $sk['user_id'];
    $login    = $sk['login'] ?? "user_id:{$userId}";
    $pubKey   = $sk['public_key'] ?? '';

    if (empty($pubKey)) {
        echo "  SKIP {$login}: no public key\n";
        $skipped++;
        continue;
    }

    try {
        // Re-encrypt the correct item key K with this user's public key
        $newShareKey = encryptUserObjectKey($itemKeyB64, $pubKey);
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

        $status = $sk['encryption_version'] == 3 ? 'FIXED(was v3)' : 'FIXED(was v1)';
        echo "  {$status}: {$login}\n";
        $fixed++;

    } catch (Exception $e) {
        echo "  ERROR {$login}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n--- Summary ---\n";
echo "  Fixed  : {$fixed}\n";
echo "  Skipped: {$skipped}\n";
echo "  Errors : {$errors}\n\n";

if ($errors === 0) {
    echo "=== FIX COMPLETE ===\n";
    echo "All sharekeys for item {$itemId} now use the correct item key.\n";
    echo "Users should now see the password correctly without any manual re-entry.\n\n";
} else {
    echo "=== FIX PARTIALLY COMPLETE ({$errors} errors) ===\n";
    echo "Some sharekeys could not be updated. Check the errors above.\n\n";
}
