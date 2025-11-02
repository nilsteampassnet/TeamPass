<?php
/**
 * Diagnostic script for Transparent Recovery feature
 *
 * This script checks if the database has been properly migrated
 * and displays the status of user recovery data.
 *
 * Usage: php install/diagnose_transparent_recovery.php
 */

// Load TeamPass configuration
require_once __DIR__ . '/../includes/config/include.php';
require_once __DIR__ . '/../sources/main.functions.php';

// Load classes
loadClasses('DB');

echo "\n=== TRANSPARENT RECOVERY DIAGNOSTIC ===\n\n";

// 1. Check if columns exist
echo "1. Checking database schema...\n";
$columns = ['user_derivation_seed', 'private_key_backup', 'key_integrity_hash', 'last_pw_change'];
$missingColumns = [];

foreach ($columns as $column) {
    $result = DB::query("SHOW COLUMNS FROM " . prefixTable('users') . " LIKE %s", $column);
    if (DB::count() === 0) {
        echo "   ❌ Column '$column' is MISSING\n";
        $missingColumns[] = $column;
    } else {
        echo "   ✅ Column '$column' exists\n";
    }
}

if (!empty($missingColumns)) {
    echo "\n⚠️  MIGRATION REQUIRED!\n";
    echo "   Run: php install/upgrade_run_3.2.0_transparent_recovery.php\n\n";
    exit(1);
}

// 2. Check settings
echo "\n2. Checking configuration settings...\n";
$settings = [
    'transparent_key_recovery_enabled',
    'transparent_key_recovery_pbkdf2_iterations',
    'transparent_key_recovery_integrity_check',
    'transparent_key_recovery_max_age_days'
];

foreach ($settings as $setting) {
    $value = DB::queryFirstField(
        "SELECT valeur FROM " . prefixTable('misc') . " WHERE intitule = %s AND type = 'admin'",
        $setting
    );
    if ($value === null) {
        echo "   ❌ Setting '$setting' is MISSING\n";
    } else {
        echo "   ✅ Setting '$setting' = $value\n";
    }
}

// 3. Check users status
echo "\n3. Checking users migration status...\n";

$totalUsers = DB::queryFirstField(
    "SELECT COUNT(*) FROM " . prefixTable('users') . "
     WHERE disabled = 0
     AND private_key IS NOT NULL
     AND private_key != 'none'"
);

$usersWithSeed = DB::queryFirstField(
    "SELECT COUNT(*) FROM " . prefixTable('users') . "
     WHERE user_derivation_seed IS NOT NULL
     AND disabled = 0"
);

$usersWithBackup = DB::queryFirstField(
    "SELECT COUNT(*) FROM " . prefixTable('users') . "
     WHERE user_derivation_seed IS NOT NULL
     AND private_key_backup IS NOT NULL
     AND disabled = 0"
);

$usersNeedingBackup = DB::queryFirstField(
    "SELECT COUNT(*) FROM " . prefixTable('users') . "
     WHERE user_derivation_seed IS NOT NULL
     AND private_key_backup IS NULL
     AND disabled = 0"
);

echo "   Total active users: $totalUsers\n";
echo "   Users with seed: $usersWithSeed (" . round($usersWithSeed/$totalUsers*100, 1) . "%)\n";
echo "   Users with backup: $usersWithBackup (" . round($usersWithBackup/$totalUsers*100, 1) . "%)\n";
echo "   Users needing backup: $usersNeedingBackup\n";

if ($usersNeedingBackup > 0) {
    echo "\n   ℹ️  $usersNeedingBackup users will have their backup created on next login\n";
}

// 4. Check server secret
echo "\n4. Checking server secret file...\n";
$secretPath = __DIR__ . '/../files/recovery_secret.key';
if (file_exists($secretPath)) {
    $perms = substr(sprintf('%o', fileperms($secretPath)), -4);
    echo "   ✅ Secret file exists\n";
    echo "   Permissions: $perms ";
    if ($perms === '0400') {
        echo "(✅ Correct)\n";
    } else {
        echo "(⚠️  Should be 0400)\n";
    }
} else {
    echo "   ℹ️  Secret file will be created on first use\n";
}

// 5. Sample user data
echo "\n5. Sample user data (first user)...\n";
$sampleUser = DB::queryFirstRow(
    "SELECT id, login,
     user_derivation_seed,
     CASE WHEN private_key_backup IS NULL THEN 'NULL' ELSE 'SET' END AS backup_status,
     CASE WHEN key_integrity_hash IS NULL THEN 'NULL' ELSE 'SET' END AS integrity_status,
     last_pw_change
     FROM " . prefixTable('users') . "
     WHERE disabled = 0
     AND private_key IS NOT NULL
     AND private_key != 'none'
     LIMIT 1"
);

if ($sampleUser) {
    echo "   User ID: {$sampleUser['id']}\n";
    echo "   Login: {$sampleUser['login']}\n";
    echo "   Seed: " . ($sampleUser['user_derivation_seed'] ? 'SET (' . strlen($sampleUser['user_derivation_seed']) . ' chars)' : 'NULL') . "\n";
    echo "   Backup: {$sampleUser['backup_status']}\n";
    echo "   Integrity: {$sampleUser['integrity_status']}\n";
    echo "   Last password change: " . ($sampleUser['last_pw_change'] ? date('Y-m-d H:i:s', $sampleUser['last_pw_change']) : 'NULL') . "\n";
}

// 6. Check recent events
echo "\n6. Recent transparent recovery events...\n";
$events = DB::query(
    "SELECT date, type, label
     FROM " . prefixTable('log_system') . "
     WHERE type IN ('auto_reencryption_success', 'auto_reencryption_failed', 'auto_reencryption_critical_failure')
     ORDER BY date DESC
     LIMIT 5"
);

if (count($events) > 0) {
    foreach ($events as $event) {
        echo "   " . date('Y-m-d H:i:s', $event['date']) . " - {$event['type']} - {$event['label']}\n";
    }
} else {
    echo "   No events yet\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n\n";

// Summary
if (empty($missingColumns)) {
    if ($usersWithBackup == $totalUsers) {
        echo "✅ ALL SYSTEMS OK - All users have transparent recovery enabled\n\n";
    } else if ($usersWithSeed == $totalUsers) {
        echo "⚠️  PARTIAL - All users have seeds, but some need to login to create backup\n\n";
    } else {
        echo "⚠️  INCOMPLETE - Some users haven't been migrated yet\n\n";
    }
} else {
    echo "❌ MIGRATION REQUIRED - Run the migration script first\n\n";
}
