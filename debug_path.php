<?php
/**
 * Debug script to check actual file paths and fix presence
 * Access via: http://localhost/TeamPass/debug_path.php
 */

echo "<h2>TeamPass Path Debug</h2>\n";
echo "<pre>\n";

echo "=== File Locations ===\n";
echo "Current script: " . __FILE__ . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "\n";

echo "=== CryptoManager Location ===\n";
$cryptoPath = __DIR__ . '/includes/libraries/teampassclasses/cryptomanager/src/CryptoManager.php';
echo "Expected path: $cryptoPath\n";
echo "File exists: " . (file_exists($cryptoPath) ? '✅ YES' : '❌ NO') . "\n";

if (file_exists($cryptoPath)) {
    $realPath = realpath($cryptoPath);
    echo "Real path: $realPath\n";

    echo "\n=== Checking for setIV() fix ===\n";
    $content = file_get_contents($cryptoPath);

    if (strpos($content, 'setIV(str_repeat("\0", 16))') !== false) {
        echo "✅ FIX IS PRESENT: setIV with zero IV found\n";
    } else {
        echo "❌ FIX IS MISSING: setIV with zero IV NOT found\n";
    }

    // Check line 255 specifically
    $lines = file($cryptoPath);
    if (isset($lines[254])) { // Line 255 is index 254
        echo "\nLine 255 content:\n";
        echo htmlspecialchars($lines[254]);
    }
}

echo "\n=== PHP Version & Extensions ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OPcache enabled: " . (function_exists('opcache_get_status') && opcache_get_status() ? 'YES' : 'NO') . "\n";

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    if ($status) {
        echo "OPcache status: " . ($status['opcache_enabled'] ? 'Enabled' : 'Disabled') . "\n";
        echo "Cache full: " . ($status['cache_full'] ? 'YES ⚠️' : 'NO') . "\n";
    }
}

echo "\n=== Autoload Check ===\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    if (class_exists('TeampassClasses\\CryptoManager\\CryptoManager')) {
        echo "✅ CryptoManager class is autoloadable\n";

        $reflection = new ReflectionClass('TeampassClasses\\CryptoManager\\CryptoManager');
        echo "Loaded from: " . $reflection->getFileName() . "\n";

        // Check if the loaded class has the fix
        $source = file_get_contents($reflection->getFileName());
        if (strpos($source, 'setIV(str_repeat("\0", 16))') !== false) {
            echo "✅ Loaded class HAS the fix\n";
        } else {
            echo "❌ Loaded class DOES NOT have the fix\n";
        }
    } else {
        echo "❌ CryptoManager class NOT found\n";
    }

    // Check phpseclib version
    if (class_exists('phpseclib3\\Crypt\\AES')) {
        echo "✅ phpseclib v3 is installed\n";
    } elseif (class_exists('Crypt_AES')) {
        echo "⚠️ phpseclib v1 is installed (not v3)\n";
    } else {
        echo "❌ No phpseclib found\n";
    }
} else {
    echo "❌ vendor/autoload.php not found\n";
}

echo "\n</pre>\n";
echo "<p><strong>⚠️ DELETE THIS FILE after debugging!</strong></p>\n";
