<?php
/**
 * Teampass - upgrade script for phpseclib v3 migration tracking
 *
 * Adds encryption_version tracking to support progressive migration from v1 to v3
 *
 * @package TeamPass
 * @version 3.1.6.0
 * @category Upgrade
 */

declare(strict_types=1);

require_once __DIR__ . '/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);
$_SESSION['CPM'] = 1;

require_once __DIR__ . '/../includes/language/english.php';
require_once __DIR__ . '/../includes/config/include.php';
require_once __DIR__ . '/../includes/config/settings.php';
require_once __DIR__ . '/libs/SecureHandler.php';
require_once __DIR__ . '/../sources/main.functions.php';

// Database connection
$db_link = mysqli_connect(
    DB_HOST,
    DB_USER,
    DB_PASSWD,
    DB_NAME,
    (int) DB_PORT
);
if (!$db_link) {
    echo '[{"finish":"1", "msg":"", "error":"DB connection failed: ' . mysqli_connect_error() . '"}]';
    exit();
}

$pre = DB_PREFIX;

echo "Starting phpseclib v3 migration tracking setup...\n\n";

// ============================================
// STEP 1: Add encryption_version to users table
// ============================================
echo "Step 1: Adding encryption_version to users table...\n";

$res = addColumnIfNotExist(
    $pre . 'users',
    'encryption_version',
    "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=phpseclib v1 (SHA-1), 3=phpseclib v3 (SHA-256)'"
);

if ($res === false) {
    echo "ERROR: Failed to add encryption_version to users table\n";
    echo "MySQL Error: " . mysqli_error($db_link) . "\n";
    mysqli_close($db_link);
    exit(1);
}
echo "✓ encryption_version added to users table\n\n";

// Initialize all existing users to version 1 (phpseclib v1)
echo "Initializing existing users to encryption_version=1...\n";
mysqli_query(
    $db_link,
    "UPDATE `" . $pre . "users`
     SET encryption_version = 1
     WHERE encryption_version = 0 OR encryption_version IS NULL"
);
echo "✓ " . mysqli_affected_rows($db_link) . " users initialized\n\n";

// ============================================
// STEP 2: Add encryption_version to sharekeys tables
// ============================================
$sharekeys_tables = [
    'sharekeys_items',
    'sharekeys_logs',
    'sharekeys_fields',
    'sharekeys_suggestions',
    'sharekeys_files'
];

echo "Step 2: Adding encryption_version to sharekeys tables...\n";

foreach ($sharekeys_tables as $table) {
    echo "Processing " . $table . "...\n";

    $res = addColumnIfNotExist(
        $pre . $table,
        'encryption_version',
        "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=phpseclib v1 (SHA-1), 3=phpseclib v3 (SHA-256)'"
    );

    if ($res === false) {
        echo "ERROR: Failed to add encryption_version to " . $table . "\n";
        echo "MySQL Error: " . mysqli_error($db_link) . "\n";
        mysqli_close($db_link);
        exit(1);
    }

    // Initialize existing sharekeys to version 1
    mysqli_query(
        $db_link,
        "UPDATE `" . $pre . $table . "`
         SET encryption_version = 1
         WHERE encryption_version = 0 OR encryption_version IS NULL"
    );

    echo "✓ " . $table . " updated (" . mysqli_affected_rows($db_link) . " rows)\n";
}

echo "\n";

// ============================================
// STEP 3: Add index for performance
// ============================================
echo "Step 3: Adding indexes for performance...\n";

foreach ($sharekeys_tables as $table) {
    $res = checkIndexExist(
        $pre . $table,
        'encryption_version',
        "ADD KEY `encryption_version` (`encryption_version`)"
    );

    if ($res === false) {
        echo "WARNING: Could not add index to " . $table . " (may already exist)\n";
    } else {
        echo "✓ Index added to " . $table . "\n";
    }
}

echo "\n";

// ============================================
// STEP 4: Create migration statistics table
// ============================================
echo "Step 4: Creating migration statistics table...\n";

mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'encryption_migration_stats` (
        `id` int(12) NOT NULL AUTO_INCREMENT,
        `table_name` varchar(100) NOT NULL,
        `total_records` int(12) NOT NULL DEFAULT 0,
        `v1_records` int(12) NOT NULL DEFAULT 0,
        `v3_records` int(12) NOT NULL DEFAULT 0,
        `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `table_name` (`table_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT="Tracks phpseclib v1 to v3 migration progress";'
);

if (mysqli_error($db_link)) {
    echo "ERROR: Failed to create migration statistics table\n";
    echo "MySQL Error: " . mysqli_error($db_link) . "\n";
} else {
    echo "✓ Migration statistics table created\n\n";

    // Initialize statistics
    echo "Calculating initial statistics...\n";

    // Users statistics
    $result = mysqli_query(
        $db_link,
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN encryption_version = 1 THEN 1 ELSE 0 END) as v1,
            SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3
         FROM `" . $pre . "users`
         WHERE private_key IS NOT NULL AND private_key != ''"
    );
    $stats = mysqli_fetch_assoc($result);

    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "encryption_migration_stats`
         (table_name, total_records, v1_records, v3_records)
         VALUES ('users', " . $stats['total'] . ", " . $stats['v1'] . ", " . $stats['v3'] . ")
         ON DUPLICATE KEY UPDATE
            total_records = " . $stats['total'] . ",
            v1_records = " . $stats['v1'] . ",
            v3_records = " . $stats['v3']
    );

    echo "✓ Users: " . $stats['total'] . " total (" . $stats['v1'] . " v1, " . $stats['v3'] . " v3)\n";

    // Sharekeys statistics
    foreach ($sharekeys_tables as $table) {
        $result = mysqli_query(
            $db_link,
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN encryption_version = 1 THEN 1 ELSE 0 END) as v1,
                SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3
             FROM `" . $pre . $table . "`"
        );
        $stats = mysqli_fetch_assoc($result);

        mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "encryption_migration_stats`
             (table_name, total_records, v1_records, v3_records)
             VALUES ('" . $table . "', " . $stats['total'] . ", " . $stats['v1'] . ", " . $stats['v3'] . ")
             ON DUPLICATE KEY UPDATE
                total_records = " . $stats['total'] . ",
                v1_records = " . $stats['v1'] . ",
                v3_records = " . $stats['v3']
        );

        echo "✓ " . $table . ": " . $stats['total'] . " total (" . $stats['v1'] . " v1, " . $stats['v3'] . " v3)\n";
    }
}

echo "\n";

// ============================================
// STEP 5: Add migration setting
// ============================================
echo "Step 5: Adding migration setting...\n";

$result = mysqli_query(
    $db_link,
    "SELECT * FROM `" . $pre . "misc`
     WHERE type = 'admin' AND intitule = 'phpseclib_migration_mode'"
);

if (mysqli_num_rows($result) === 0) {
    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (type, intitule, valeur)
         VALUES ('admin', 'phpseclib_migration_mode', 'progressive')"
    );
    echo "✓ Migration mode setting added (default: progressive)\n";
} else {
    echo "✓ Migration mode setting already exists\n";
}

echo "\n";

// ============================================
// Summary
// ============================================
echo "=====================================\n";
echo "Migration tracking setup completed!\n";
echo "=====================================\n\n";

echo "Next steps:\n";
echo "1. New data will be automatically encrypted with phpseclib v3\n";
echo "2. Old data remains encrypted with v1 (backward compatible)\n";
echo "3. Use the batch re-encryption script to migrate existing data (optional)\n";
echo "4. Monitor progress in teampass_encryption_migration_stats table\n\n";

echo "Migration modes:\n";
echo "- 'progressive': New data uses v3, old data stays v1 (current)\n";
echo "- 'batch': Run batch re-encryption script\n";
echo "- 'hybrid': Decrypt with auto-detect, re-encrypt on access with v3\n\n";

mysqli_close($db_link);

/**
 * Helper function to add column if not exists
 */
function addColumnIfNotExist(string $table, string $column, string $definition): bool
{
    global $db_link;

    $result = mysqli_query(
        $db_link,
        "SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'"
    );

    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE `" . $table . "` ADD `" . $column . "` " . $definition;
        return mysqli_query($db_link, $sql) !== false;
    }

    return true; // Already exists
}

/**
 * Helper function to check if index exists
 */
function checkIndexExist(string $table, string $index, string $definition): bool
{
    global $db_link;

    $result = mysqli_query(
        $db_link,
        "SHOW INDEX FROM `" . $table . "` WHERE Key_name = '" . $index . "'"
    );

    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE `" . $table . "` " . $definition;
        return mysqli_query($db_link, $sql) !== false;
    }

    return true; // Already exists
}
