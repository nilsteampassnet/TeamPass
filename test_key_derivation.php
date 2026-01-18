<?php
/**
 * Compare key derivation between phpseclib v1 and v3
 * Shows the actual derived key to identify differences
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$password = $_POST['password'] ?? '';

if (!$password) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Key Derivation Test</title></head>
    <body>
        <h2>Compare v1 and v3 Key Derivation</h2>
        <form method="POST">
            <label>Password:</label>
            <input type="password" name="password" required>
            <button type="submit">Test</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

echo "<pre>\n";
echo "=== Testing PBKDF2 Key Derivation ===\n\n";

// Test data
$testData = "Hello World Test Data";
echo "Test data: $testData\n";
echo "Password length: " . strlen($password) . " chars\n\n";

// Native PHP PBKDF2 (reference)
echo "=== Reference: Native PHP hash_pbkdf2 ===\n";
$keyPhp16 = hash_pbkdf2('sha1', $password, 'phpseclib/salt', 1000, 16, true);
$keyPhp32 = hash_pbkdf2('sha1', $password, 'phpseclib/salt', 1000, 32, true);
echo "16-byte key: " . bin2hex($keyPhp16) . "\n";
echo "32-byte key: " . bin2hex($keyPhp32) . "\n\n";

// Test with phpseclib v1 if available
if (class_exists('Crypt_AES')) {
    echo "=== phpseclib v1 ===\n";

    $cipher = new Crypt_AES();
    $cipher->setPassword($password); // Default parameters

    // Encrypt test data
    $encrypted = $cipher->encrypt($testData);
    echo "Encrypted (hex): " . bin2hex($encrypted) . "\n";
    echo "Encrypted length: " . strlen($encrypted) . " bytes\n";

    // Decrypt
    $cipher2 = new Crypt_AES();
    $cipher2->setPassword($password);
    $decrypted = $cipher2->decrypt($encrypted);
    echo "Decrypted: $decrypted\n";
    echo "Match: " . ($decrypted === $testData ? '✅ YES' : '❌ NO') . "\n\n";
}

// Test with phpseclib v3 if available
if (class_exists('phpseclib3\\Crypt\\AES')) {
    echo "=== phpseclib v3 - Strategy 1: IV before setPassword ===\n";

    $cipher = new \phpseclib3\Crypt\AES('cbc');
    $cipher->setIV(str_repeat("\0", 16));
    $cipher->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);

    // Try to encrypt test data
    $encrypted = $cipher->encrypt($testData);
    echo "Encrypted (hex): " . bin2hex($encrypted) . "\n";
    echo "Encrypted length: " . strlen($encrypted) . " bytes\n";

    // Decrypt
    $cipher2 = new \phpseclib3\Crypt\AES('cbc');
    $cipher2->setIV(str_repeat("\0", 16));
    $cipher2->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
    $decrypted = $cipher2->decrypt($encrypted);
    echo "Decrypted: $decrypted\n";
    echo "Match: " . ($decrypted === $testData ? '✅ YES' : '❌ NO') . "\n\n";

    // Now test if v3 can decrypt v1-encrypted data
    if (class_exists('Crypt_AES')) {
        echo "=== Cross-test: v1 encrypt → v3 decrypt ===\n";

        $cipherV1 = new Crypt_AES();
        $cipherV1->setPassword($password);
        $encryptedV1 = $cipherV1->encrypt($testData);
        echo "v1 encrypted (hex): " . bin2hex($encryptedV1) . "\n";

        $cipherV3 = new \phpseclib3\Crypt\AES('cbc');
        $cipherV3->setIV(str_repeat("\0", 16));
        $cipherV3->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);

        try {
            $decryptedByV3 = $cipherV3->decrypt($encryptedV1);
            echo "v3 decrypted: $decryptedByV3\n";
            echo "Match: " . ($decryptedByV3 === $testData ? '✅ YES' : '❌ NO') . "\n";
        } catch (Exception $e) {
            echo "❌ v3 decrypt failed: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n</pre>\n";
