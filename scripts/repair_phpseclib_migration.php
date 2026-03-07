<?php
/**
 * Teampass - Repair script for phpseclib v3 migration issues
 *
 * This script diagnoses and repairs common issues that can occur during
 * the phpseclib v1 to v3 migration process.
 *
 * Usage:
 *   php scripts/repair_phpseclib_migration.php [--diagnose|--repair|--reset-user=ID]
 *
 * Options:
 *   --diagnose     Show detailed diagnostics without making changes
 *   --repair       Attempt to repair detected issues
 *   --reset-user=ID Reset migration status for a specific user
 *
 * @file      repair_phpseclib_migration.php
 * @author    Nils Laumaille (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

declare(strict_types=1);

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Load TeamPass configuration
$rootPath = dirname(__DIR__);
require_once $rootPath . '/includes/config/settings.php';
require_once $rootPath . '/includes/config/include.php';
require_once $rootPath . '/sources/main.functions.php';

// Initialize database
loadClasses('DB');

// Parse command line arguments
$options = getopt('', ['diagnose', 'repair', 'reset-user:', 'help']);

if (isset($options['help']) || empty($options)) {
    echo <<<HELP
TeamPass phpseclib v3 Migration Repair Tool
============================================

Usage: php scripts/repair_phpseclib_migration.php [OPTIONS]

Options:
  --diagnose        Show detailed diagnostics without making changes
  --repair          Attempt to repair detected issues
  --reset-user=ID   Reset migration status for a specific user ID
  --help            Show this help message

Examples:
  php scripts/repair_phpseclib_migration.php --diagnose
  php scripts/repair_phpseclib_migration.php --repair
  php scripts/repair_phpseclib_migration.php --reset-user=5

HELP;
    exit(0);
}

echo "\n=== TeamPass phpseclib v3 Migration Repair Tool ===\n\n";

// Tables containing sharekeys
$sharekeysTablesList = [
    'sharekeys_items',
    'sharekeys_fields',
    'sharekeys_files',
    'sharekeys_logs',
    'sharekeys_suggestions',
];

// DIAGNOSE MODE
if (isset($options['diagnose']) || isset($options['repair'])) {
    echo "--- DIAGNOSTIC REPORT ---\n\n";

    // 1. Check users migration status
    echo "[1] Users Migration Status:\n";
    $usersStats = DB::query(
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3_users,
            SUM(CASE WHEN encryption_version = 1 OR encryption_version IS NULL THEN 1 ELSE 0 END) as v1_users,
            SUM(CASE WHEN phpseclibv3_migration_completed = 1 THEN 1 ELSE 0 END) as migration_complete,
            SUM(CASE WHEN phpseclibv3_migration_completed = 0 OR phpseclibv3_migration_completed IS NULL THEN 1 ELSE 0 END) as migration_incomplete
        FROM " . prefixTable('users') . "
        WHERE id NOT IN (%i, %i, %i)",
        OTV_USER_ID,
        SSH_USER_ID,
        API_USER_ID
    );
    $stats    = $usersStats[0];
    $stTotal  = intval($stats['total'] ?? 0);
    $stV3     = intval($stats['v3_users'] ?? 0);
    $stV1     = intval($stats['v1_users'] ?? 0);
    $stDone   = intval($stats['migration_complete'] ?? 0);
    $stIncomp = intval($stats['migration_incomplete'] ?? 0);
    echo "    Total users: {$stTotal}\n";
    echo "    Users with v3 keys: {$stV3}\n";
    echo "    Users with v1 keys: {$stV1}\n";
    echo "    Migration completed: {$stDone}\n";
    echo "    Migration incomplete: {$stIncomp}\n\n";

    // 2. Check sharekeys by version
    echo "[2] Sharekeys Distribution by Version:\n";
    foreach ($sharekeysTablesList as $table) {
        $tableStats = DB::queryFirstRow(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3,
                SUM(CASE WHEN encryption_version = 1 OR encryption_version IS NULL THEN 1 ELSE 0 END) as v1
            FROM " . prefixTable($table)
        );
        $tblTotal  = intval($tableStats['total'] ?? 0);
        $tblV3     = intval($tableStats['v3'] ?? 0);
        $tblV1     = intval($tableStats['v1'] ?? 0);
        $percentV3 = $tblTotal > 0 ? round(($tblV3 / $tblTotal) * 100, 1) : 0;
        echo "    {$table}: {$tblTotal} total, {$tblV3} v3 ({$percentV3}%), {$tblV1} v1\n";
    }
    echo "\n";

    // 3. Find users marked as migrated but with v1 sharekeys
    echo "[3] Users with Inconsistent Migration State:\n";
    $inconsistentUsers = DB::query(
        "SELECT DISTINCT u.id, u.login, u.encryption_version, u.phpseclibv3_migration_completed
        FROM " . prefixTable('users') . " u
        INNER JOIN " . prefixTable('sharekeys_items') . " sk ON sk.user_id = u.id
        WHERE u.phpseclibv3_migration_completed = 1
        AND sk.encryption_version = 1
        AND u.id NOT IN (%i, %i, %i)
        ORDER BY u.id",
        OTV_USER_ID,
        SSH_USER_ID,
        API_USER_ID
    );

    if (empty($inconsistentUsers)) {
        echo "    No inconsistent users found.\n\n";
    } else {
        echo "    Found " . count($inconsistentUsers) . " user(s) marked as migrated but with v1 sharekeys:\n";
        foreach ($inconsistentUsers as $user) {
            $incUserId    = intval($user['id'] ?? 0);
            $incUserLogin = strval($user['login'] ?? '');
            // Count v1 sharekeys for this user
            $v1Count = 0;
            foreach ($sharekeysTablesList as $table) {
                $v1Count += intval(DB::queryFirstField(
                    "SELECT COUNT(*) FROM " . prefixTable($table) . "
                    WHERE user_id = %i AND encryption_version = 1",
                    $incUserId
                ));
            }
            echo "      - User ID {$incUserId} ({$incUserLogin}): {$v1Count} v1 sharekeys remaining\n";
        }
        echo "\n";
    }

    // 4. Find users with encryption_version = 3 but private key still encrypted with v1
    echo "[4] Users with Potential Key Mismatch:\n";
    $potentialMismatch = DB::query(
        "SELECT id, login, encryption_version, phpseclibv3_migration_completed
        FROM " . prefixTable('users') . "
        WHERE encryption_version = 3
        AND phpseclibv3_migration_completed = 0
        AND id NOT IN (%i, %i, %i)",
        OTV_USER_ID,
        SSH_USER_ID,
        API_USER_ID
    );

    if (empty($potentialMismatch)) {
        echo "    No potential key mismatches found.\n\n";
    } else {
        echo "    Found " . count($potentialMismatch) . " user(s) with v3 keys but incomplete migration:\n";
        foreach ($potentialMismatch as $user) {
            $pmUserId    = intval($user['id'] ?? 0);
            $pmUserLogin = strval($user['login'] ?? '');
            echo "      - User ID {$pmUserId} ({$pmUserLogin})\n";
        }
        echo "\n";
    }

    // 5. Check for NULL encryption_version values
    echo "[5] Sharekeys with NULL/Missing encryption_version:\n";
    $hasNullVersions = false;
    foreach ($sharekeysTablesList as $table) {
        $nullCountRaw = DB::queryFirstField(
            "SELECT COUNT(*) FROM " . prefixTable($table) . "
            WHERE encryption_version IS NULL"
        );
        $nullCount = intval($nullCountRaw);
        if ($nullCount > 0) {
            echo "    {$table}: {$nullCount} rows with NULL encryption_version\n";
            $hasNullVersions = true;
        }
    }
    if (!$hasNullVersions) {
        echo "    No NULL encryption_version values found.\n";
    }
    echo "\n";
}

// REPAIR MODE
if (isset($options['repair'])) {
    echo "--- REPAIR ACTIONS ---\n\n";

    // Repair 1: Set NULL encryption_version to 1 (assume v1 if not set)
    echo "[R1] Setting NULL encryption_version to 1 (legacy)...\n";
    foreach ($sharekeysTablesList as $table) {
        $affected = DB::query(
            "UPDATE " . prefixTable($table) . "
            SET encryption_version = 1
            WHERE encryption_version IS NULL"
        );
        $count = DB::affectedRows();
        if ($count > 0) {
            echo "     {$table}: Updated {$count} rows\n";
        }
    }
    echo "\n";

    // Repair 2: Reset migration status for inconsistent users
    echo "[R2] Resetting migration status for inconsistent users...\n";
    if (!empty($inconsistentUsers)) {
        foreach ($inconsistentUsers as $user) {
            DB::update(
                prefixTable('users'),
                [
                    'phpseclibv3_migration_completed' => 0,
                    'phpseclibv3_migration_task_id' => null,
                ],
                'id = %i',
                $user['id']
            );
            echo "     Reset user ID " . strval($user['id']) . " (" . strval($user['login']) . ") - will retry migration on next login\n";
        }
    } else {
        echo "     No users to reset.\n";
    }
    echo "\n";

    echo "Repair completed. Users with reset migration will retry on their next login.\n\n";
}

// RESET SPECIFIC USER
if (isset($options['reset-user'])) {
    $userId = (int) $options['reset-user'];
    echo "--- RESET USER {$userId} ---\n\n";

    // Check if user exists
    $user = DB::queryFirstRow(
        "SELECT id, login, encryption_version, phpseclibv3_migration_completed
        FROM " . prefixTable('users') . "
        WHERE id = %i",
        $userId
    );

    if (empty($user)) {
        echo "Error: User ID {$userId} not found.\n\n";
        exit(1);
    }

    $userLogin   = strval($user['login'] ?? '');
    $userEncV    = intval($user['encryption_version'] ?? 1);
    $userMigDone = intval($user['phpseclibv3_migration_completed'] ?? 0);
    echo "User: {$userLogin} (ID: {$userId})\n";
    echo "Current encryption_version: {$userEncV}\n";
    echo "Current migration_completed: {$userMigDone}\n\n";

    // Show sharekeys stats
    echo "Sharekeys for this user:\n";
    foreach ($sharekeysTablesList as $table) {
        $stats    = DB::queryFirstRow(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3,
                SUM(CASE WHEN encryption_version = 1 THEN 1 ELSE 0 END) as v1
            FROM " . prefixTable($table) . "
            WHERE user_id = %i",
            $userId
        );
        $skTotal = intval($stats['total'] ?? 0);
        $skV3    = intval($stats['v3'] ?? 0);
        $skV1    = intval($stats['v1'] ?? 0);
        echo "  {$table}: {$skTotal} total ({$skV3} v3, {$skV1} v1)\n";
    }
    echo "\n";

    // Reset migration status
    DB::update(
        prefixTable('users'),
        [
            'phpseclibv3_migration_completed' => 0,
            'phpseclibv3_migration_task_id' => null,
        ],
        'id = %i',
        $userId
    );

    echo "Migration status reset. User will retry migration on next login.\n\n";
}

echo "=== Done ===\n\n";
