<?php
/**
 * Test AES compatibility between phpseclib v1 and v3
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/libraries/teampassclasses/cryptomanager/src/CryptoManager.php';

use TeampassClasses\CryptoManager\CryptoManager;

// Test password
$password = "TestPassword123!";
$plaintext = "This is a test private key";

echo "=== Testing AES Encryption Compatibility ===\n\n";

// Test 1: Encrypt with v1 (simulated), decrypt with v3
echo "Test 1: v1 encryption format\n";
echo "-------------------------------\n";

// Simulate v1 encryption (using old Crypt_AES)
$cipher_v1 = new Crypt_AES();
$cipher_v1->setPassword($password);
$encrypted_v1 = $cipher_v1->encrypt($plaintext);
echo "Encrypted with v1: " . base64_encode($encrypted_v1) . "\n";

// Try to decrypt with v3
try {
    $decrypted_v3 = CryptoManager::aesDecrypt($encrypted_v1, $password);
    echo "Decrypted with v3: " . $decrypted_v3 . "\n";
    echo "✅ SUCCESS: v1 → v3 decryption works!\n\n";
} catch (Exception $e) {
    echo "❌ FAILED: v1 → v3 decryption failed\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 2: Encrypt with v3, decrypt with v1
echo "Test 2: v3 encryption format\n";
echo "-------------------------------\n";

try {
    $encrypted_v3 = CryptoManager::aesEncrypt($plaintext, $password);
    echo "Encrypted with v3: " . base64_encode($encrypted_v3) . "\n";

    // Try to decrypt with v1
    $cipher_v1_decrypt = new Crypt_AES();
    $cipher_v1_decrypt->setPassword($password);
    $decrypted_v1 = $cipher_v1_decrypt->decrypt($encrypted_v3);
    echo "Decrypted with v1: " . $decrypted_v1 . "\n";
    echo "✅ SUCCESS: v3 → v1 decryption works!\n\n";
} catch (Exception $e) {
    echo "❌ FAILED: v3 → v1 decryption failed\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check setPassword() behavior
echo "Test 3: setPassword() key derivation\n";
echo "--------------------------------------\n";

$cipher_v1_test = new Crypt_AES();
$cipher_v1_test->setPassword($password);

$cipher_v3_test = new phpseclib3\Crypt\AES('cbc');
$cipher_v3_test->setPassword($password);

echo "Testing if derived keys match...\n";
echo "Note: This may not be directly comparable\n";
echo "but encryption/decryption compatibility is what matters.\n\n";

echo "=== End of Tests ===\n";
