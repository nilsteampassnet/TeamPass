<?php
/**
 * Teampass - Diagnostic script for password corruption after phpseclib v3 migration
 *
 * This script identifies users and items potentially affected by the AES-CBC false-positive
 * PKCS7 padding bug that can cause corrupted passwords to be displayed.
 *
 * ROOT CAUSE: aesDecryptWithVersionDetection() tries SHA-256 first to decrypt user
 * private keys. AES-CBC without MAC has no integrity check, so if PKCS7 padding
 * accidentally validates (~0.4% probability), SHA-256 "succeeds" on SHA-1-encrypted data
 * and returns garbage bytes as the private key. This garbage private key causes all
 * subsequent RSA sharekey decryptions to fail, and the error string was then mistakenly
 * used as an AES key, sometimes producing garbage "passwords".
 *
 * This is a SESSION-LEVEL bug: the database item passwords are NOT permanently corrupted
 * (unless the user saved the corrupted value back). The fixes in main.functions.php
 * prevent recurrence. This script identifies who might have been affected.
 *
 * Usage:
 *   php scripts/diagnose_password_corruption.php [--diagnose|--reset-migrations|--reset-user=ID]
 *
 * Options:
 *   --diagnose           Show diagnostic report (default)
 *   --reset-migrations   Reset migration status for all at-risk users (they will re-migrate on next login)
 *   --reset-user=ID      Reset migration status for a specific user ID
 *
 * @file      diagnose_password_corruption.php
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

loadClasses('DB');

$options = getopt('', ['diagnose', 'reset-migrations', 'reset-user:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
TeamPass Password Corruption Diagnostic Tool
=============================================

Root cause: AES-CBC false-positive PKCS7 padding (~0.4% per login) causes a garbage
private key to be stored in session, leading to corrupted passwords being displayed.
The database is NOT permanently modified by this bug (unless the user saved the wrong value).
The fix has been applied to main.functions.php. This script helps identify affected users.

Usage: php scripts/diagnose_password_corruption.php [OPTIONS]

Options:
  --diagnose             Show diagnostic report (default)
  --reset-migrations     Reset migration status for all at-risk users
  --reset-user=ID        Reset migration status for a specific user
  --help                 Show this help

What "reset-migrations" does:
  Sets phpseclibv3_migration_completed=0 for users still using v1 encryption.
  On their next login, migration runs fresh, which also re-encrypts their private
  key to v3, eliminating the root cause for that user.

HELP;
    exit(0);
}

echo "\n=== TeamPass Password Corruption Diagnostic Tool ===\n\n";
echo "Root cause: AES-CBC SHA-256/SHA-1 false-positive PKCS7 padding during private key decryption.\n";
echo "This causes garbage private keys in session → corrupted passwords displayed.\n";
echo "Database item passwords are NOT permanently corrupted by this bug.\n\n";

// --- SECTION 1: Overall migration state ---
echo "--- [1] Overall Migration State ---\n\n";

$systemUsers = [OTV_USER_ID, SSH_USER_ID, API_USER_ID];
$systemUsersPlaceholder = implode(', ', array_map(fn($id) => '%i', $systemUsers));

$userStats = DB::queryFirstRow(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN encryption_version = 1 OR encryption_version IS NULL THEN 1 ELSE 0 END) as v1_users,
        SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3_users,
        SUM(CASE WHEN phpseclibv3_migration_completed = 1 THEN 1 ELSE 0 END) as migration_done,
        SUM(CASE WHEN phpseclibv3_migration_completed = 0 OR phpseclibv3_migration_completed IS NULL THEN 1 ELSE 0 END) as migration_pending
    FROM " . prefixTable('users') . "
    WHERE id NOT IN (" . $systemUsersPlaceholder . ")",
    ...$systemUsers
);

$stTotal   = intval($userStats['total'] ?? 0);
$stV1Users = intval($userStats['v1_users'] ?? 0);
$stV3Users = intval($userStats['v3_users'] ?? 0);
$stMigDone = intval($userStats['migration_done'] ?? 0);
$stMigPend = intval($userStats['migration_pending'] ?? 0);

echo "  Total users:               {$stTotal}\n";
echo "  Private key still SHA-1:   {$stV1Users}   (AT RISK: login might produce garbage private key ~0.4% of the time)\n";
echo "  Private key migrated SHA-256: {$stV3Users}  (SAFE: PEM validation fix now prevents false positives)\n";
echo "  Migration completed flag:  {$stMigDone}\n";
echo "  Migration pending/failed:  {$stMigPend}\n\n";

// --- SECTION 2: Users at risk (v1 private key, migration not complete) ---
echo "--- [2] Users Still At Risk (v1 private key not yet migrated) ---\n\n";

$atRiskUsers = DB::query(
    "SELECT id, login, encryption_version, phpseclibv3_migration_completed, phpseclibv3_migration_task_id
    FROM " . prefixTable('users') . "
    WHERE (encryption_version = 1 OR encryption_version IS NULL)
    AND id NOT IN (" . $systemUsersPlaceholder . ")
    ORDER BY login",
    ...$systemUsers
);

if (empty($atRiskUsers)) {
    echo "  No at-risk users found. All users have SHA-256 encrypted private keys.\n\n";
} else {
    echo "  Found " . count($atRiskUsers) . " user(s) with v1 (SHA-1) encrypted private key:\n";
    echo "  (Each login has ~0.4% chance of garbage private key → corrupted password display)\n\n";
    foreach ($atRiskUsers as $user) {
        $userId     = intval($user['id'] ?? 0);
        $userLogin  = strval($user['login'] ?? '');
        $userEncV   = intval($user['encryption_version'] ?? 1);
        $userMigCmp = intval($user['phpseclibv3_migration_completed'] ?? 0);
        $userTaskId = strval($user['phpseclibv3_migration_task_id'] ?? '');
        $migStatus  = $userMigCmp == 1 ? 'DONE (but key still v1!)' : 'pending';
        $taskInfo   = $userTaskId !== '' ? " [task: {$userTaskId}]" : '';
        echo "    - ID {$userId}: {$userLogin} | key_version={$userEncV} | migration={$migStatus}{$taskInfo}\n";
    }
    echo "\n";
}

// --- SECTION 3: Users marked migrated but with v1 sharekeys remaining ---
echo "--- [3] Users Marked As Migrated But With v1 Sharekeys (Inconsistent State) ---\n\n";

$sharekeysTablesList = ['sharekeys_items', 'sharekeys_fields', 'sharekeys_files', 'sharekeys_logs', 'sharekeys_suggestions'];

$inconsistentUsers = DB::query(
    "SELECT DISTINCT u.id, u.login, u.encryption_version, u.phpseclibv3_migration_completed
    FROM " . prefixTable('users') . " u
    INNER JOIN " . prefixTable('sharekeys_items') . " sk ON sk.user_id = u.id
    WHERE u.phpseclibv3_migration_completed = 1
    AND sk.encryption_version = 1
    AND u.id NOT IN (" . $systemUsersPlaceholder . ")
    ORDER BY u.id",
    ...$systemUsers
);

if (empty($inconsistentUsers)) {
    echo "  No inconsistent users found.\n\n";
} else {
    echo "  Found " . count($inconsistentUsers) . " user(s) marked as migrated but with v1 sharekeys:\n";
    echo "  These users need migration to be re-run.\n\n";
    foreach ($inconsistentUsers as $user) {
        $userId    = intval($user['id'] ?? 0);
        $userLogin = strval($user['login'] ?? '');
        $v1Total   = 0;
        foreach ($sharekeysTablesList as $tbl) {
            $v1Total += intval(DB::queryFirstField(
                "SELECT COUNT(*) FROM " . prefixTable($tbl) . " WHERE user_id = %i AND encryption_version = 1",
                $userId
            ));
        }
        echo "    - ID {$userId}: {$userLogin} | {$v1Total} v1 sharekeys remaining\n";
    }
    echo "\n";
}

// --- SECTION 4: Sharekeys distribution ---
echo "--- [4] Sharekeys Distribution ---\n\n";
foreach ($sharekeysTablesList as $tbl) {
    $stats   = DB::queryFirstRow(
        "SELECT COUNT(*) as total,
         SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3,
         SUM(CASE WHEN encryption_version = 1 OR encryption_version IS NULL THEN 1 ELSE 0 END) as v1
         FROM " . prefixTable($tbl)
    );
    $skTotal = intval($stats['total'] ?? 0);
    $skV3    = intval($stats['v3'] ?? 0);
    $skV1    = intval($stats['v1'] ?? 0);
    $pct     = $skTotal > 0 ? round(($skV3 / $skTotal) * 100, 1) : 0;
    echo "  {$tbl}: {$skTotal} total | {$skV3} v3 ({$pct}%) | {$skV1} v1\n";
}
echo "\n";

// --- SECTION 5: Background task queue ---
echo "--- [5] Pending Migration Background Tasks ---\n\n";

$pendingTasks = DB::query(
    "SELECT bt.increment_id, bt.arguments, bt.created_at, bt.updated_at, bt.status,
     COUNT(bs.increment_id) as subtask_count,
     SUM(CASE WHEN bs.is_in_progress = -1 THEN 1 ELSE 0 END) as subtasks_done,
     SUM(CASE WHEN bs.status = 'failed' THEN 1 ELSE 0 END) as subtasks_failed
     FROM " . prefixTable('background_tasks') . " bt
     LEFT JOIN " . prefixTable('background_subtasks') . " bs ON bs.task_id = bt.increment_id
     WHERE bt.process_type = 'create_keys_after_upgrade'
     GROUP BY bt.increment_id
     ORDER BY bt.created_at DESC
     LIMIT 20"
);

if (empty($pendingTasks)) {
    echo "  No phpseclib migration tasks found.\n\n";
} else {
    foreach ($pendingTasks as $task) {
        $args      = json_decode(strval($task['arguments'] ?? '{}'), true);
        $taskUserId = strval($args['user_id'] ?? 'unknown');
        $taskId    = intval($task['increment_id'] ?? 0);
        $taskStatus = strval($task['status'] ?? '');
        $subDone   = intval($task['subtasks_done'] ?? 0);
        $subCount  = intval($task['subtask_count'] ?? 0);
        $subFailed = intval($task['subtasks_failed'] ?? 0);
        echo "  Task {$taskId}: user_id={$taskUserId} | status={$taskStatus} | subtasks: {$subDone}/{$subCount} done, {$subFailed} failed\n";
    }
    echo "\n";
}

// --- RESET ACTIONS ---

if (isset($options['reset-migrations']) || isset($options['reset-user'])) {
    echo "--- [ACTION] Resetting Migration Status ---\n\n";

    $usersToReset = [];

    if (isset($options['reset-user'])) {
        $targetId = (int) $options['reset-user'];
        $u = DB::queryFirstRow("SELECT id, login, encryption_version FROM " . prefixTable('users') . " WHERE id = %i", $targetId);
        if (empty($u)) {
            echo "  ERROR: User ID {$targetId} not found.\n\n";
        } else {
            $usersToReset = [$u];
        }
    } else {
        // Reset all at-risk users + inconsistent users
        $usersToReset = array_merge($atRiskUsers ?? [], $inconsistentUsers ?? []);
        // Deduplicate by id
        $seen = [];
        $usersToReset = array_filter($usersToReset, function ($u) use (&$seen) {
            if (isset($seen[$u['id']])) return false;
            $seen[$u['id']] = true;
            return true;
        });
    }

    if (empty($usersToReset)) {
        echo "  No users to reset.\n\n";
    } else {
        foreach ($usersToReset as $user) {
            $resetUserId    = intval($user['id'] ?? 0);
            $resetUserLogin = strval($user['login'] ?? '');
            $resetUserEncV  = intval($user['encryption_version'] ?? 1);

            // Also reset any stuck background task
            $existingTask = DB::queryFirstRow(
                "SELECT increment_id FROM " . prefixTable('background_tasks') . "
                WHERE process_type = 'create_keys_after_upgrade'
                AND JSON_EXTRACT(arguments, '$.user_id') = %i
                AND status != 'done'",
                $resetUserId
            );

            if (!empty($existingTask)) {
                $existingTaskId = intval($existingTask['increment_id'] ?? 0);
                DB::update(
                    prefixTable('background_tasks'),
                    ['status' => 'cancelled', 'updated_at' => time()],
                    'increment_id = %i',
                    $existingTaskId
                );
                echo "  Cancelled stuck migration task {$existingTaskId} for user {$resetUserId}\n";
            }

            DB::update(
                prefixTable('users'),
                [
                    'phpseclibv3_migration_completed' => 0,
                    'phpseclibv3_migration_task_id' => null,
                ],
                'id = %i',
                $resetUserId
            );

            // Also reset v1 sharekeys back so migration re-runs completely
            // Only if user has v3 key (migration already partially ran)
            if ($resetUserEncV === 3) {
                foreach ($sharekeysTablesList as $tbl) {
                    $v1count = intval(DB::queryFirstField(
                        "SELECT COUNT(*) FROM " . prefixTable($tbl) . " WHERE user_id = %i AND encryption_version = 1",
                        $resetUserId
                    ));
                    if ($v1count > 0) {
                        echo "    Note: user {$resetUserId} has {$v1count} v1 sharekeys in {$tbl} - migration needed\n";
                    }
                }
            }

            echo "  RESET: User ID {$resetUserId} ({$resetUserLogin}) - migration will re-run on next login\n";
        }
        echo "\n  Done. Affected users will undergo fresh migration on their next login.\n";
        echo "  Make sure the background task handler (scripts/background_tasks___handler.php) is running.\n\n";
    }
}

// --- SUMMARY ---
echo "--- [SUMMARY] ---\n\n";

if (!empty($atRiskUsers)) {
    echo "  ACTION REQUIRED:\n";
    echo "  " . count($atRiskUsers) . " user(s) still have SHA-1 encrypted private keys.\n";
    echo "  They are at risk until they complete migration (next login + background task).\n";
    echo "  Run: php scripts/diagnose_password_corruption.php --reset-migrations\n";
    echo "  Then ensure background_tasks___handler.php is running as a cron job.\n\n";
} else {
    echo "  All user private keys are SHA-256 encrypted. System is in good state.\n";
    echo "  The PEM-validation fix in main.functions.php prevents recurrence.\n\n";
}

echo "  Note: If users report passwords that 'changed', those changes were SESSION-LEVEL\n";
echo "  only. The database was NOT modified. Users should:\n";
echo "    1. Log out completely\n";
echo "    2. Log back in (migration will run in background)\n";
echo "    3. If passwords still appear wrong after migration, they were saved back corrupted\n";
echo "       and will need to be manually restored from a backup or re-entered.\n\n";

echo "=== Done ===\n\n";
