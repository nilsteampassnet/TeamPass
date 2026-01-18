<?php
/**
 * Teampass - Batch re-encryption script from phpseclib v1 to v3
 *
 * This script re-encrypts all sharekeys from phpseclib v1 (SHA-1) to v3 (SHA-256)
 *
 * Usage:
 *   php scripts/maintenance_reencrypt_v1_to_v3.php [--table=TABLE_NAME] [--limit=N] [--dry-run]
 *
 * Options:
 *   --table=TABLE_NAME    Re-encrypt specific table only (e.g., sharekeys_items)
 *   --limit=N             Process only N records (default: all)
 *   --dry-run             Simulate without making changes
 *   --verbose             Show detailed progress
 *
 * @package TeamPass
 * @version 3.1.6.0
 * @category Maintenance
 */

declare(strict_types=1);

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line' . PHP_EOL);
}

// Parse command line arguments
$options = getopt('', ['table::', 'limit::', 'dry-run', 'verbose', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

$targetTable = $options['table'] ?? null;
$limit = isset($options['limit']) ? (int)$options['limit'] : 0;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Bootstrap TeamPass
require_once __DIR__ . '/../includes/config/include.php';
require_once __DIR__ . '/../includes/config/settings.php';
require_once __DIR__ . '/../sources/main.functions.php';

use TeampassClasses\CryptoManager\CryptoManager;

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  TeamPass - phpseclib v1 → v3 Batch Re-encryption       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($dryRun) {
    echo "⚠️  DRY RUN MODE - No changes will be made\n\n";
}

// Database connection
$db_link = mysqli_connect(
    DB_HOST,
    DB_USER,
    DB_PASSWD,
    DB_NAME,
    (int) DB_PORT
);

if (!$db_link) {
    die('❌ Database connection failed: ' . mysqli_connect_error() . PHP_EOL);
}

mysqli_set_charset($db_link, 'utf8');
$pre = DB_PREFIX;

// Tables to process
$sharekeys_tables = $targetTable
    ? [$targetTable]
    : [
        'sharekeys_items',
        'sharekeys_logs',
        'sharekeys_fields',
        'sharekeys_suggestions',
        'sharekeys_files'
    ];

$totalProcessed = 0;
$totalSuccess = 0;
$totalFailed = 0;
$totalSkipped = 0;

foreach ($sharekeys_tables as $table) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Processing table: {$table}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // Check if table exists
    $tableExists = mysqli_query(
        $db_link,
        "SHOW TABLES LIKE '{$pre}{$table}'"
    );

    if (mysqli_num_rows($tableExists) === 0) {
        echo "⚠️  Table {$pre}{$table} does not exist, skipping...\n\n";
        continue;
    }

    // Check if encryption_version column exists
    $columnExists = mysqli_query(
        $db_link,
        "SHOW COLUMNS FROM `{$pre}{$table}` LIKE 'encryption_version'"
    );

    if (mysqli_num_rows($columnExists) === 0) {
        echo "❌ Table {$pre}{$table} missing encryption_version column!\n";
        echo "   Please run upgrade_run_3.1.6.0_phpseclib_v3_tracking.php first\n\n";
        continue;
    }

    // Count records to process
    $countQuery = "SELECT COUNT(*) as total
                   FROM `{$pre}{$table}`
                   WHERE encryption_version = 1";
    if ($limit > 0) {
        $countQuery .= " LIMIT {$limit}";
    }

    $countResult = mysqli_query($db_link, $countQuery);
    $count = mysqli_fetch_assoc($countResult)['total'];

    if ($count === 0) {
        echo "✓ No v1 records to migrate in {$table}\n\n";
        continue;
    }

    echo "Found {$count} v1 encrypted records\n";

    // Fetch records to re-encrypt
    $selectQuery = "SELECT s.increment_id, s.object_id, s.user_id, s.share_key, u.private_key, u.public_key
                    FROM `{$pre}{$table}` s
                    INNER JOIN `{$pre}users` u ON u.id = s.user_id
                    WHERE s.encryption_version = 1
                    AND u.private_key IS NOT NULL
                    AND u.public_key IS NOT NULL";
    if ($limit > 0) {
        $selectQuery .= " LIMIT {$limit}";
    }

    $result = mysqli_query($db_link, $selectQuery);

    if (!$result) {
        echo "❌ Query failed: " . mysqli_error($db_link) . "\n\n";
        continue;
    }

    $recordsProcessed = 0;
    $recordsSuccess = 0;
    $recordsFailed = 0;

    echo "Re-encrypting";
    if (!$verbose) {
        echo " [. = 10 records]";
    }
    echo ":\n";

    $progressCounter = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $recordsProcessed++;
        $totalProcessed++;
        $progressCounter++;

        try {
            // Decrypt with v1 (SHA-1)
            $decryptedKey = CryptoManager::rsaDecryptWithVersion(
                base64_decode($row['share_key']),
                $row['private_key'],
                1  // Version 1 (SHA-1)
            );

            // Re-encrypt with v3 (SHA-256)
            $reencryptedKey = CryptoManager::rsaEncrypt(
                $decryptedKey,
                $row['public_key']
            );

            // Update database
            if (!$dryRun) {
                $updateQuery = "UPDATE `{$pre}{$table}`
                                SET share_key = '" . mysqli_real_escape_string($db_link, base64_encode($reencryptedKey)) . "',
                                    encryption_version = 3
                                WHERE increment_id = {$row['increment_id']}";

                if (!mysqli_query($db_link, $updateQuery)) {
                    throw new Exception(mysqli_error($db_link));
                }
            }

            $recordsSuccess++;
            $totalSuccess++;

            if ($verbose) {
                echo "  ✓ Record {$row['increment_id']} (user {$row['user_id']}, object {$row['object_id']})\n";
            } elseif ($progressCounter % 10 === 0) {
                echo ".";
                if ($progressCounter % 500 === 0) {
                    echo " {$progressCounter}/{$count}\n";
                }
            }
        } catch (Exception $e) {
            $recordsFailed++;
            $totalFailed++;

            if ($verbose) {
                echo "  ❌ Record {$row['increment_id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    if (!$verbose && $progressCounter > 0) {
        echo " {$progressCounter}/{$count}\n";
    }

    echo "\n";
    echo "Table {$table} results:\n";
    echo "  ✓ Success: {$recordsSuccess}\n";
    if ($recordsFailed > 0) {
        echo "  ❌ Failed:  {$recordsFailed}\n";
    }
    echo "\n";

    // Update statistics
    if (!$dryRun && $recordsSuccess > 0) {
        $statsQuery = "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN encryption_version = 1 THEN 1 ELSE 0 END) as v1,
                        SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3
                       FROM `{$pre}{$table}`";
        $statsResult = mysqli_query($db_link, $statsQuery);
        $stats = mysqli_fetch_assoc($statsResult);

        mysqli_query(
            $db_link,
            "INSERT INTO `{$pre}encryption_migration_stats`
             (table_name, total_records, v1_records, v3_records)
             VALUES ('{$table}', {$stats['total']}, {$stats['v1']}, {$stats['v3']})
             ON DUPLICATE KEY UPDATE
                total_records = {$stats['total']},
                v1_records = {$stats['v1']},
                v3_records = {$stats['v3']}"
        );
    }
}

// Summary
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  Summary                                                  ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

echo "Total processed: {$totalProcessed}\n";
echo "  ✓ Success:     {$totalSuccess}\n";

if ($totalFailed > 0) {
    echo "  ❌ Failed:      {$totalFailed}\n";
}

if ($dryRun) {
    echo "\n⚠️  This was a DRY RUN - no changes were made\n";
    echo "   Remove --dry-run flag to perform actual re-encryption\n";
}

echo "\n";

mysqli_close($db_link);

exit($totalFailed > 0 ? 1 : 0);

/**
 * Show help message
 */
function showHelp(): void
{
    echo "\n";
    echo "TeamPass - Batch Re-encryption Script (phpseclib v1 → v3)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "Usage:\n";
    echo "  php scripts/maintenance_reencrypt_v1_to_v3.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --table=TABLE_NAME    Re-encrypt specific table only\n";
    echo "                        Tables: sharekeys_items, sharekeys_logs,\n";
    echo "                                sharekeys_fields, sharekeys_suggestions,\n";
    echo "                                sharekeys_files\n\n";
    echo "  --limit=N             Process only N records (useful for testing)\n\n";
    echo "  --dry-run             Simulate without making changes\n\n";
    echo "  --verbose             Show detailed progress for each record\n\n";
    echo "  --help                Show this help message\n\n";
    echo "Examples:\n";
    echo "  # Dry run to test the process\n";
    echo "  php scripts/maintenance_reencrypt_v1_to_v3.php --dry-run --verbose\n\n";
    echo "  # Re-encrypt only sharekeys_items table\n";
    echo "  php scripts/maintenance_reencrypt_v1_to_v3.php --table=sharekeys_items\n\n";
    echo "  # Process only first 100 records (testing)\n";
    echo "  php scripts/maintenance_reencrypt_v1_to_v3.php --limit=100 --dry-run\n\n";
    echo "  # Full migration of all tables\n";
    echo "  php scripts/maintenance_reencrypt_v1_to_v3.php\n\n";
    echo "Note:\n";
    echo "  - Run upgrade_run_3.1.6.0_phpseclib_v3_tracking.php first!\n";
    echo "  - Backup your database before running without --dry-run\n";
    echo "  - Large databases may take considerable time\n\n";
}
