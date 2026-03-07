<?php
/**
 * Teampass - Fix key_integrity_hash for transparent recovery
 *
 * This script recalculates and fixes the key_integrity_hash for all users
 * where the stored hash doesn't match the expected hash based on current data.
 * It also verifies if the private_key_backup can be decrypted.
 *
 * Usage: php scripts/fix_integrity_hash.php [--dry-run] [--verify-backup]
 *
 * Options:
 *   --dry-run        Show what would be fixed without making changes
 *   --verify-backup  Test if private_key_backup can be decrypted (slower)
 */

declare(strict_types=1);

// Change to root directory
chdir(__DIR__ . '/..');

require_once 'includes/config/settings.php';
require_once 'includes/config/include.php';
require_once 'vendor/autoload.php';
require_once 'sources/main.functions.php';

use Defuse\Crypto\Key;
use TeampassClasses\ConfigManager\ConfigManager;

// Parse arguments
$dryRun = in_array('--dry-run', $argv);
$verifyBackup = in_array('--verify-backup', $argv);

echo "=== TeamPass Key Integrity Hash Fix ===\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes)" : "LIVE (will update DB)") . "\n";
echo "Verify backup: " . ($verifyBackup ? "YES" : "NO (use --verify-backup to enable)") . "\n\n";

// Initialize database connection
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

// Get server secret
try {
    $ascii_key = file_get_contents(SECUREPATH . '/' . SECUREFILE);
    $key = Key::loadFromAsciiSafeString($ascii_key);
    $serverSecret = $key->saveToAsciiSafeString();
    echo "Server secret loaded successfully.\n";
} catch (Exception $e) {
    die("ERROR: Failed to load server secret: " . $e->getMessage() . "\n");
}

// Load settings for PBKDF2 iterations
$SETTINGS = [];
try {
    $configManager = new ConfigManager();
    $SETTINGS = $configManager->getAllSettings();
    echo "Settings loaded successfully.\n\n";
} catch (Exception $e) {
    echo "Warning: Could not load settings, using defaults.\n\n";
}

/**
 * Derive backup key from user seed and public key
 */
function deriveBackupKeyLocal(string $userSeed, string $publicKey, array $SETTINGS): string
{
    $salt = hash('sha256', $publicKey, true);
    $iterations = isset($SETTINGS['transparent_key_recovery_pbkdf2_iterations'])
        ? (int) $SETTINGS['transparent_key_recovery_pbkdf2_iterations']
        : 100000;

    return hash_pbkdf2(
        'sha256',
        hex2bin($userSeed),
        $salt,
        $iterations,
        32,
        true
    );
}

/**
 * Test if backup can be decrypted
 */
function testBackupDecryption(string $backupEncrypted, string $derivedKey): array
{
    try {
        // Try SHA-256 first (v3)
        $decrypted = \TeampassClasses\CryptoManager\CryptoManager::aesDecrypt(
            base64_decode($backupEncrypted),
            $derivedKey,
            'cbc',
            'sha256'
        );
        if (!empty($decrypted) && strpos($decrypted, '-----BEGIN') !== false) {
            return ['success' => true, 'version' => 'v3 (SHA-256)'];
        }
    } catch (Exception $e) {
        // Try SHA-1 fallback (v1)
    }

    try {
        $decrypted = \TeampassClasses\CryptoManager\CryptoManager::aesDecrypt(
            base64_decode($backupEncrypted),
            $derivedKey,
            'cbc',
            'sha1'
        );
        if (!empty($decrypted) && strpos($decrypted, '-----BEGIN') !== false) {
            return ['success' => true, 'version' => 'v1 (SHA-1)'];
        }
    } catch (Exception $e) {
        // Both failed
    }

    return ['success' => false, 'version' => 'none'];
}

// Find all users with transparent recovery data
$users = DB::query(
    "SELECT id, login, user_derivation_seed, public_key, key_integrity_hash, private_key_backup
     FROM " . prefixTable('users') . "
     WHERE user_derivation_seed IS NOT NULL
       AND public_key IS NOT NULL
       AND disabled = 0 AND deleted_at IS NULL"
);

$totalUsers = count($users);
$mismatched = 0;
$fixed = 0;
$noHash = 0;
$backupOk = 0;
$backupFailed = 0;
$noBackup = 0;
$usersWithIssues = [];

echo "Checking $totalUsers users with transparent recovery data...\n\n";

foreach ($users as $user) {
    $expectedHash = hash_hmac(
        'sha256',
        strval($user['user_derivation_seed']) . strval($user['public_key']),
        $serverSecret
    );

    $hashStatus = 'OK';
    $needsFix = false;

    // Check if hash is missing
    if (empty($user['key_integrity_hash'])) {
        $noHash++;
        $hashStatus = 'MISSING';
        $needsFix = true;
    } elseif (!hash_equals($expectedHash, $user['key_integrity_hash'])) {
        $mismatched++;
        $hashStatus = 'MISMATCH';
        $needsFix = true;
    }

    // Verify backup decryption if requested
    $backupStatus = 'NOT TESTED';
    if ($verifyBackup) {
        if (empty($user['private_key_backup'])) {
            $backupStatus = 'NO BACKUP';
            $noBackup++;
        } else {
            $derivedKey = deriveBackupKeyLocal(
                $user['user_derivation_seed'],
                $user['public_key'],
                $SETTINGS
            );
            $decryptResult = testBackupDecryption($user['private_key_backup'], $derivedKey);
            if ($decryptResult['success']) {
                $backupStatus = 'OK (' . $decryptResult['version'] . ')';
                $backupOk++;
            } else {
                $backupStatus = 'DECRYPT FAILED';
                $backupFailed++;
            }
        }
    }

    // Output status for problematic users
    if ($needsFix || ($verifyBackup && $backupStatus === 'DECRYPT FAILED')) {
        echo "[{$hashStatus}] " . strval($user['login']) . " (ID: " . strval($user['id']) . ")\n";

        if ($hashStatus === 'MISMATCH') {
            echo "         Hash stored:   " . strval($user['key_integrity_hash']) . "\n";
            echo "         Hash expected: {$expectedHash}\n";
        }

        if ($verifyBackup) {
            echo "         Backup: {$backupStatus}\n";
        }

        if ($needsFix && !$dryRun) {
            DB::update(
                prefixTable('users'),
                ['key_integrity_hash' => $expectedHash],
                'id = %i',
                $user['id']
            );
            $fixed++;
            echo "         -> Hash fixed\n";
        }

        // Track users with backup issues
        if ($verifyBackup && $backupStatus === 'DECRYPT FAILED') {
            $usersWithIssues[] = [
                'id' => $user['id'],
                'login' => $user['login'],
                'issue' => 'Backup cannot be decrypted - transparent recovery will fail'
            ];
        }

        echo "\n";
    }
}

echo "=== Summary ===\n";
echo "Total users checked: $totalUsers\n";
echo "Hash missing: $noHash\n";
echo "Hash mismatched: $mismatched\n";

if ($verifyBackup) {
    echo "\nBackup verification:\n";
    echo "  Backup OK: $backupOk\n";
    echo "  Backup decrypt failed: $backupFailed\n";
    echo "  No backup stored: $noBackup\n";
}

if ($dryRun) {
    echo "\nDRY RUN - No changes made.\n";
    echo "Run without --dry-run to apply fixes.\n";
} else {
    echo "\nFixed: $fixed\n";
}

// Report critical issues
if (!empty($usersWithIssues)) {
    echo "\n=== CRITICAL: Users with unrecoverable backup ===\n";
    echo "These users will NOT be able to use transparent recovery even after hash fix:\n\n";
    foreach ($usersWithIssues as $issue) {
        echo "  - " . strval($issue['login']) . " (ID: " . strval($issue['id']) . ")\n";
        echo "    {$issue['issue']}\n";
    }
    echo "\nFor these users, the public_key likely changed after the backup was created.\n";
    echo "Solution: They must login with their CURRENT password to regenerate keys,\n";
    echo "or an admin must reset their encryption keys (losing access to existing items).\n";
}

if ($mismatched > 0 || $noHash > 0) {
    echo "\n=== Notes ===\n";
    echo "The integrity hash mismatch typically occurs when:\n";
    echo "  1. The public_key was regenerated after the hash was created\n";
    echo "  2. The hash was created with different data than what was stored\n";
    echo "  3. A migration or update modified the keys without updating the hash\n";
}
