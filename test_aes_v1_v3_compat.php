<?php
/**
 * Test AES v1/v3 compatibility with actual encrypted data
 * This tests if data encrypted with v1 can be decrypted with v3 and vice versa
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/vendor/autoload.php';

echo "<h2>AES v1/v3 Compatibility Test</h2>\n";
echo "<pre>\n";

$testPassword = 'test_password_123';
$testData = 'This is a test private key with some special chars: éàç@#$%';

echo "=== Configuration ===\n";
echo "Test password: $testPassword\n";
echo "Test data: $testData\n";
echo "Test data length: " . strlen($testData) . " bytes\n\n";

// Check which version is available
$hasV3 = class_exists('phpseclib3\\Crypt\\AES');
$hasV1 = class_exists('Crypt_AES');

echo "phpseclib v3 available: " . ($hasV3 ? 'YES ✅' : 'NO ❌') . "\n";
echo "phpseclib v1 available: " . ($hasV1 ? 'YES ✅' : 'NO ❌') . "\n\n";

// Test 1: Encrypt with v1, decrypt with CryptoManager
if ($hasV1) {
    echo "=== Test 1: v1 encrypt → CryptoManager decrypt ===\n";

    $cipher_v1 = new Crypt_AES();
    $cipher_v1->setPassword($testPassword);
    $encrypted_v1 = $cipher_v1->encrypt($testData);

    echo "v1 encrypted length: " . strlen($encrypted_v1) . " bytes\n";
    echo "v1 encrypted (base64): " . base64_encode($encrypted_v1) . "\n";

    try {
        $decrypted = \TeampassClasses\CryptoManager\CryptoManager::aesDecrypt(
            $encrypted_v1,
            $testPassword
        );

        if ($decrypted === $testData) {
            echo "✅ SUCCESS: CryptoManager decrypted v1 data correctly\n";
        } else {
            echo "❌ FAIL: Decrypted data doesn't match\n";
            echo "Expected: $testData\n";
            echo "Got: $decrypted\n";
        }
    } catch (Exception $e) {
        echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Test 2: Encrypt with CryptoManager, decrypt with v1
if ($hasV1) {
    echo "=== Test 2: CryptoManager encrypt → v1 decrypt ===\n";

    $encrypted_cm = \TeampassClasses\CryptoManager\CryptoManager::aesEncrypt(
        $testData,
        $testPassword
    );

    echo "CryptoManager encrypted length: " . strlen($encrypted_cm) . " bytes\n";
    echo "CryptoManager encrypted (base64): " . base64_encode($encrypted_cm) . "\n";

    $cipher_v1 = new Crypt_AES();
    $cipher_v1->setPassword($testPassword);
    $decrypted_v1 = $cipher_v1->decrypt($encrypted_cm);

    if ($decrypted_v1 === $testData) {
        echo "✅ SUCCESS: v1 decrypted CryptoManager data correctly\n";
    } else {
        echo "❌ FAIL: Decrypted data doesn't match\n";
        echo "Expected: $testData\n";
        echo "Got: $decrypted_v1\n";
    }
    echo "\n";
}

// Test 3: Detailed PBKDF2 parameter test
echo "=== Test 3: Explicit PBKDF2 Parameters ===\n";

if ($hasV3) {
    echo "Testing phpseclib v3 with explicit parameters...\n";

    $cipher_v3 = new \phpseclib3\Crypt\AES('cbc');
    $cipher_v3->setIV(str_repeat("\0", 16));
    $cipher_v3->setPassword($testPassword, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
    $encrypted_v3 = $cipher_v3->encrypt($testData);

    echo "v3 encrypted length: " . strlen($encrypted_v3) . " bytes\n";
    echo "v3 encrypted (base64): " . base64_encode($encrypted_v3) . "\n";

    // Decrypt with same v3 setup
    $cipher_v3_dec = new \phpseclib3\Crypt\AES('cbc');
    $cipher_v3_dec->setIV(str_repeat("\0", 16));
    $cipher_v3_dec->setPassword($testPassword, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
    $decrypted_v3 = $cipher_v3_dec->decrypt($encrypted_v3);

    if ($decrypted_v3 === $testData) {
        echo "✅ v3 → v3 decryption: SUCCESS\n";
    } else {
        echo "❌ v3 → v3 decryption: FAIL\n";
        echo "Expected: $testData\n";
        echo "Got: $decrypted_v3\n";
    }

    // Cross-test: v3 encrypt → v1 decrypt
    if ($hasV1) {
        $cipher_v1_dec = new Crypt_AES();
        $cipher_v1_dec->setPassword($testPassword);
        $decrypted_by_v1 = $cipher_v1_dec->decrypt($encrypted_v3);

        if ($decrypted_by_v1 === $testData) {
            echo "✅ v3 → v1 decryption: SUCCESS\n";
        } else {
            echo "❌ v3 → v1 decryption: FAIL\n";
            echo "Expected: $testData\n";
            echo "Got: $decrypted_by_v1\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "If all tests pass, v1 and v3 are fully compatible.\n";
echo "If tests fail, there's a parameter mismatch in PBKDF2 or IV setup.\n";

echo "\n</pre>\n";
