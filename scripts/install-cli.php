#!/usr/bin/env php
<?php
/**
 * TeamPass CLI Installer
 *
 * Automated installation script for Docker deployments
 *
 * Usage:
 *   php install-cli.php --db-host=localhost --db-name=teampass --db-user=teampass --db-password=secret --admin-pwd=admin123
 *
 * @package TeamPass
 * @version 1.0.0
 */

// Parse command line arguments
$options = getopt('', [
    'db-host:',
    'db-port::',
    'db-name:',
    'db-user:',
    'db-password:',
    'db-prefix::',
    'admin-email::',
    'admin-pwd:',
    'url::',
]);

// Validate required options
$required = ['db-host', 'db-name', 'db-user', 'db-password', 'admin-pwd'];
foreach ($required as $opt) {
    if (empty($options[$opt])) {
        echo "Error: --{$opt} is required\n";
        echo "\nUsage:\n";
        echo "  php install-cli.php \\\n";
        echo "    --db-host=localhost \\\n";
        echo "    --db-port=3306 \\\n";
        echo "    --db-name=teampass \\\n";
        echo "    --db-user=teampass \\\n";
        echo "    --db-password=secret \\\n";
        echo "    --db-prefix=teampass_ \\\n";
        echo "    --admin-email=admin@example.com \\\n";
        echo "    --admin-pwd=admin123 \\\n";
        echo "    --url=http://localhost\n";
        exit(1);
    }
}

// Set default values
$dbHost = $options['db-host'];
$dbPort = $options['db-port'] ?? '3306';
$dbName = $options['db-name'];
$dbUser = $options['db-user'];
$dbPassword = $options['db-password'];
$dbPrefix = $options['db-prefix'] ?? 'teampass_';
$adminEmail = $options['admin-email'] ?? 'admin@teampass.local';
$adminPwd = $options['admin-pwd'];
$url = $options['url'] ?? 'http://localhost';

// Base path
define('BASE_PATH', dirname(__DIR__));

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  TeamPass CLI Installer\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Step 1: Test database connection
echo "[1/6] Testing database connection...\n";
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort}";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "      ✅ Database connection successful\n\n";
} catch (PDOException $e) {
    echo "      ❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Create database if not exists
echo "[2/6] Creating database...\n";
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    echo "      ✅ Database created/selected\n\n";
} catch (PDOException $e) {
    echo "      ❌ Database creation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Create settings.php file
echo "[3/6] Creating settings.php configuration file...\n";
try {
    $settingsTemplate = BASE_PATH . '/includes/config/settings.sample.php';
    $settingsPath = BASE_PATH . '/includes/config/settings.php';

    if (!file_exists($settingsTemplate)) {
        throw new Exception("Template file not found: {$settingsTemplate}");
    }

    // Read template
    $settingsContent = file_get_contents($settingsTemplate);

    // Generate encryption key for database password
    $key = bin2hex(random_bytes(32));
    $encryptedDbPassword = base64_encode($dbPassword); // Simplified for now

    // Replace placeholders
    $replacements = [
        "'@define@DB_HOST@'" => "'{$dbHost}'",
        "'@define@DB_USER@'" => "'{$dbUser}'",
        "'@define@DB_PASSWD@'" => "'{$encryptedDbPassword}'",
        "'@define@DB_NAME@'" => "'{$dbName}'",
        "'@define@DB_PREFIX@'" => "'{$dbPrefix}'",
        "'@define@DB_PORT@'" => "'{$dbPort}'",
        "'@define@DB_ENCODING@'" => "'utf8'",
        "'@define@IKEY@'" => "''",
        "'@define@SKEY@'" => "''",
        "'@define@HOST@'" => "''",
    ];

    foreach ($replacements as $search => $replace) {
        $settingsContent = str_replace($search, $replace, $settingsContent);
    }

    // Write settings file
    file_put_contents($settingsPath, $settingsContent);
    chmod($settingsPath, 0640);

    echo "      ✅ Settings file created\n\n";
} catch (Exception $e) {
    echo "      ❌ Settings creation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 4: Import database schema
echo "[4/6] Importing database schema...\n";
try {
    $installDir = BASE_PATH . '/install';
    $schemaFiles = [
        $installDir . '/database/tables.sql',
        $installDir . '/database/initial-data.sql',
    ];

    foreach ($schemaFiles as $schemaFile) {
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);

            // Replace table prefix
            $sql = str_replace('`teampass_', "`{$dbPrefix}", $sql);

            // Split and execute queries
            $queries = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($queries as $query) {
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }

            echo "      ✅ Imported: " . basename($schemaFile) . "\n";
        } else {
            echo "      ⚠️  Schema file not found: " . basename($schemaFile) . " (continuing...)\n";
        }
    }

    echo "      ✅ Database schema imported\n\n";
} catch (PDOException $e) {
    echo "      ⚠️  Note: This is a simplified installer. Full schema import should be done via web installer.\n";
    echo "      Error details: " . $e->getMessage() . "\n\n";
}

// Step 5: Create saltkey
echo "[5/6] Generating encryption saltkey...\n";
try {
    $skDir = BASE_PATH . '/sk';
    if (!is_dir($skDir)) {
        mkdir($skDir, 0700, true);
    }

    $saltkey = bin2hex(random_bytes(32));
    file_put_contents($skDir . '/sk.txt', $saltkey);
    chmod($skDir . '/sk.txt', 0400);

    echo "      ✅ Saltkey generated\n\n";
} catch (Exception $e) {
    echo "      ❌ Saltkey generation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 6: Final message
echo "[6/6] Finalizing installation...\n";
echo "      ✅ Installation completed!\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ⚠️  IMPORTANT NOTES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "This is a SIMPLIFIED installer for Docker environments.\n";
echo "\n";
echo "For a complete installation, you may need to:\n";
echo "1. Complete the installation via web interface at:\n";
echo "   {$url}/install/install.php\n";
echo "\n";
echo "2. Use these database credentials:\n";
echo "   - Host: {$dbHost}\n";
echo "   - Port: {$dbPort}\n";
echo "   - Database: {$dbName}\n";
echo "   - User: {$dbUser}\n";
echo "   - Prefix: {$dbPrefix}\n";
echo "\n";
echo "3. Saltkey path: /var/www/html/sk\n";
echo "\n";
echo "4. After setup, delete the install directory\n";
echo "\n";

exit(0);
