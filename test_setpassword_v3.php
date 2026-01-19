<?php
/**
 * Final test: Use setPassword() in v3 exactly like v1
 * Compare internal state after setPassword()
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config/settings.php';
require_once __DIR__ . '/vendor/autoload.php';

DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = defuse_return_decrypted(DB_PASSWD);
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;

$user = DB::queryFirstRow('SELECT private_key FROM teampass_users WHERE id = 10000000');
$encrypted = base64_decode($user['private_key']);

echo "<h2>Final Test: setPassword() in v3 with exact v1 parameters</h2>\n";
echo "<pre>\n";

echo "Encrypted length: " . strlen($encrypted) . " bytes\n\n";

$password = 'D8hDEr)4T2+}';

// Test: setPassword() WITHOUT parameters (like v1 does)
echo "=== Test 1: v3 setPassword() with NO parameters (like v1) ===\n";
try {
    $cipher = new \phpseclib3\Crypt\AES('cbc');
    $cipher->setIV(str_repeat("\0", 16));
    $cipher->setPassword($password); // NO parameters!

    $decrypted = $cipher->decrypt($encrypted);

    if (strpos($decrypted, '-----BEGIN') !== false) {
        echo "✅✅✅ SUCCESS!\n";
        echo substr($decrypted, 0, 100) . "\n";
    } else {
        echo "⚠️ Decrypted but not PEM\n";
        echo "First 50: " . substr($decrypted, 0, 50) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test 2: v3 setPassword() BEFORE setIV ===\n";
try {
    $cipher = new \phpseclib3\Crypt\AES('cbc');
    $cipher->setPassword($password); // Password FIRST
    $cipher->setIV(str_repeat("\0", 16)); // Then IV

    $decrypted = $cipher->decrypt($encrypted);

    if (strpos($decrypted, '-----BEGIN') !== false) {
        echo "✅✅✅ SUCCESS!\n";
        echo substr($decrypted, 0, 100) . "\n";
    } else {
        echo "⚠️ Decrypted but not PEM\n";
        echo "First 50: " . substr($decrypted, 0, 50) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test 3: Check if v1 and v3 are on same machine ===\n";
echo "v1 available: " . (class_exists('Crypt_AES') ? 'YES' : 'NO') . "\n";
echo "v3 available: " . (class_exists('phpseclib3\\Crypt\\AES') ? 'YES' : 'NO') . "\n";

if (class_exists('Crypt_AES')) {
    echo "\n=== Test 4: Direct comparison - encrypt with v1, decrypt with v3 ===\n";

    $testData = "Test Data 12345";

    // Encrypt with v1
    $c1 = new Crypt_AES();
    $c1->setPassword($password);
    $enc1 = $c1->encrypt($testData);
    echo "v1 encrypted (hex): " . bin2hex($enc1) . "\n";

    // Try to decrypt with v3
    $c3 = new \phpseclib3\Crypt\AES('cbc');
    $c3->setIV(str_repeat("\0", 16));
    $c3->setPassword($password);

    try {
        $dec3 = $c3->decrypt($enc1);
        echo "v3 decrypted: " . $dec3 . "\n";
        echo "Match: " . ($dec3 === $testData ? '✅ YES' : '❌ NO') . "\n";
    } catch (Exception $e) {
        echo "❌ v3 decrypt failed: " . $e->getMessage() . "\n";
    }
}

echo "\n</pre>\n";
