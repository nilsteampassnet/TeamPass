<?php
/**
 * Test script for hybrid migration mode
 *
 * This script checks the migration setup and provides instructions for testing
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config/include.php';
require_once __DIR__ . '/includes/config/settings.php';
require_once __DIR__ . '/sources/main.functions.php';

echo "\n";
echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║  Hybrid Migration Mode - Test Setup                   ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n";
echo "\n";

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

// 1. Check migration mode setting
echo "1. Checking migration mode setting...\n";

$result = mysqli_query(
    $db_link,
    "SELECT valeur FROM `{$pre}misc`
     WHERE type = 'admin' AND intitule = 'phpseclib_migration_mode'"
);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $mode = $row['valeur'];
    echo "   Current mode: {$mode}\n";

    if ($mode !== 'hybrid') {
        echo "   ⚠️  Setting migration mode to 'hybrid'...\n";
        mysqli_query(
            $db_link,
            "UPDATE `{$pre}misc`
             SET valeur = 'hybrid'
             WHERE type = 'admin' AND intitule = 'phpseclib_migration_mode'"
        );
        echo "   ✅ Migration mode set to 'hybrid'\n";
    } else {
        echo "   ✅ Already in hybrid mode\n";
    }
} else {
    echo "   ⚠️  Setting not found, creating it...\n";
    mysqli_query(
        $db_link,
        "INSERT INTO `{$pre}misc` (type, intitule, valeur)
         VALUES ('admin', 'phpseclib_migration_mode', 'hybrid')"
    );
    echo "   ✅ Migration mode setting created and set to 'hybrid'\n";
}

echo "\n";

// 2. Check for v1 encrypted sharekeys
echo "2. Checking for v1 encrypted sharekeys...\n";

$sharekeys_tables = [
    'sharekeys_items',
    'sharekeys_logs',
    'sharekeys_fields',
    'sharekeys_suggestions',
    'sharekeys_files'
];

$totalV1 = 0;
$totalV3 = 0;

foreach ($sharekeys_tables as $table) {
    $result = mysqli_query(
        $db_link,
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN encryption_version = 1 THEN 1 ELSE 0 END) as v1,
            SUM(CASE WHEN encryption_version = 3 THEN 1 ELSE 0 END) as v3
         FROM `{$pre}{$table}`"
    );

    if ($result) {
        $stats = mysqli_fetch_assoc($result);
        $totalV1 += (int)$stats['v1'];
        $totalV3 += (int)$stats['v3'];

        if ((int)$stats['v1'] > 0) {
            echo "   {$table}: {$stats['v1']} v1, {$stats['v3']} v3\n";
        }
    }
}

echo "\n   Total: {$totalV1} v1 sharekeys, {$totalV3} v3 sharekeys\n";

if ($totalV1 > 0) {
    echo "   ✅ Found v1 sharekeys to migrate\n";
} else {
    echo "   ⚠️  No v1 sharekeys found. Migration already complete or no data to migrate.\n";
}

echo "\n";

// 3. Test decryption with version detection
echo "3. Testing CryptoManager version detection...\n";

try {
    // Check if the new method exists
    $reflection = new ReflectionClass('TeampassClasses\CryptoManager\CryptoManager');

    if ($reflection->hasMethod('rsaDecryptWithVersionDetection')) {
        echo "   ✅ rsaDecryptWithVersionDetection() method exists\n";
    } else {
        echo "   ❌ rsaDecryptWithVersionDetection() method NOT FOUND!\n";
    }

    if ($reflection->hasMethod('getCurrentVersion')) {
        $version = \TeampassClasses\CryptoManager\CryptoManager::getCurrentVersion();
        echo "   ✅ Current encryption version: {$version}\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Instructions
echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║  Testing Instructions                                  ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n";
echo "\n";

if ($totalV1 > 0) {
    echo "To test the hybrid migration:\n\n";
    echo "1. Log in to TeamPass web interface\n";
    echo "2. Navigate to an item (password)\n";
    echo "3. Click to view/copy the password\n";
    echo "4. Check the database to verify migration:\n\n";
    echo "   mysql -u " . DB_USER . " -p " . DB_NAME . " -e \"\n";
    echo "   SELECT s.increment_id, s.object_id, s.user_id, s.encryption_version,\n";
    echo "          i.label\n";
    echo "   FROM {$pre}sharekeys_items s\n";
    echo "   JOIN {$pre}items i ON i.id = s.object_id\n";
    echo "   WHERE s.user_id = YOUR_USER_ID\n";
    echo "   ORDER BY s.increment_id DESC\n";
    echo "   LIMIT 10;\n";
    echo "   \"\n\n";
    echo "5. Look for records where encryption_version changed from 1 to 3\n\n";
    echo "Expected behavior:\n";
    echo "- When you view a password with encryption_version=1,\n";
    echo "  it should automatically be re-encrypted with v3\n";
    echo "- encryption_version should change from 1 to 3\n";
    echo "- The password should still decrypt correctly\n\n";

    if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
        echo "6. Check error logs for migration messages:\n";
        echo "   tail -f " . (defined('LOG_FILE') ? LOG_FILE : '/var/log/teampass.log') . "\n\n";
        echo "   Look for messages like:\n";
        echo "   TEAMPASS Migration - Sharekey X in sharekeys_items migrated from v1 to v3\n\n";
    }
} else {
    echo "No v1 sharekeys found to test.\n\n";
    echo "To test with new data:\n";
    echo "1. The system will now automatically use v3 for all new items\n";
    echo "2. Create a new password item and check its encryption_version\n\n";
}

echo "Migration Statistics:\n";
echo "You can query the migration progress at any time:\n\n";
echo "mysql -u " . DB_USER . " -p " . DB_NAME . " -e \"SELECT * FROM {$pre}encryption_migration_stats;\"\n\n";

mysqli_close($db_link);

echo "✅ Setup complete!\n\n";
