<?php
/**
 * Simulate phpseclib v1 AES decryption using native PHP functions
 * This allows testing v1 compatibility even when only v3 is installed
 */

declare(strict_types=1);

$encryptedPrivateKey = filter_input(INPUT_GET, 'key', FILTER_UNSAFE_RAW);
$password = filter_input(INPUT_GET, 'pwd', FILTER_UNSAFE_RAW);

if (!$encryptedPrivateKey || !$password) {
    die("Usage: test_native_php_decrypt.php?key=BASE64_KEY&pwd=PASSWORD\n");
}

echo "<h2>Native PHP AES Decryption (Simulating phpseclib v1)</h2>\n";
echo "<pre>\n";

$ciphertext = base64_decode($encryptedPrivateKey);
echo "Ciphertext length: " . strlen($ciphertext) . " bytes\n";
echo "Remainder / 16: " . (strlen($ciphertext) % 16) . "\n\n";

/**
 * Simulate phpseclib v1 setPassword() behavior
 * Default: PBKDF2, SHA-1, salt='phpseclib/salt', 1000 iterations
 */
function deriveKeyPBKDF2($password, $salt = 'phpseclib/salt', $iterations = 1000, $keyLength = 32) {
    return hash_pbkdf2('sha1', $password, $salt, $iterations, $keyLength, true);
}

$strategies = [];

// Strategy 1: AES-128-CBC with PBKDF2-derived key
$strategies['aes-128-cbc'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 16); // 128 bits = 16 bytes
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
};

// Strategy 2: AES-192-CBC
$strategies['aes-192-cbc'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 24); // 192 bits = 24 bytes
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-192-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
};

// Strategy 3: AES-256-CBC (most likely for 4096-bit RSA)
$strategies['aes-256-cbc'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 32); // 256 bits = 32 bytes
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
};

// Strategy 4: AES-128-CTR (stream mode, no padding)
$strategies['aes-128-ctr'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 16);
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-128-ctr', $key, OPENSSL_RAW_DATA, $iv);
};

// Strategy 5: AES-256-CTR
$strategies['aes-256-ctr'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 32);
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
};

// Strategy 6: AES-128-CFB
$strategies['aes-128-cfb'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 16);
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-128-cfb', $key, OPENSSL_RAW_DATA, $iv);
};

// Strategy 7: AES-256-CFB
$strategies['aes-256-cfb'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 32);
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-256-cfb', $key, OPENSSL_RAW_DATA, $iv);
};

// Strategy 8: CBC with default padding (no ZERO_PADDING flag)
$strategies['aes-256-cbc-pkcs7'] = function($data, $pwd) {
    $key = deriveKeyPBKDF2($pwd, 'phpseclib/salt', 1000, 32);
    $iv = str_repeat("\0", 16);
    return openssl_decrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
};

echo "=== Testing " . count($strategies) . " native PHP strategies ===\n\n";

foreach ($strategies as $name => $strategy) {
    echo "Strategy: $name\n";
    echo str_repeat('-', 70) . "\n";

    try {
        $decrypted = $strategy($ciphertext, $password);

        if ($decrypted === false) {
            echo "❌ openssl_decrypt() returned false\n";
        } else {
            echo "✓ Decryption succeeded\n";
            echo "Decrypted length: " . strlen($decrypted) . " bytes\n";

            // Check if it looks like a PEM key
            if (strpos($decrypted, '-----BEGIN') !== false) {
                echo "✅✅✅ SUCCESS! VALID PEM PRIVATE KEY! ✅✅✅\n";
                echo "Key type: ";
                if (strpos($decrypted, 'RSA PRIVATE KEY') !== false) {
                    echo "RSA PRIVATE KEY\n";
                }

                echo "\n🎯 THIS IS THE CORRECT CONFIGURATION! 🎯\n";
                echo "Mode: $name\n";
                echo "PBKDF2: SHA-1, salt='phpseclib/salt', iterations=1000\n";
                echo "IV: Zero IV (16 null bytes)\n";

                $lines = explode("\n", $decrypted);
                echo "\nFirst 3 lines of key:\n";
                for ($i = 0; $i < min(3, count($lines)); $i++) {
                    echo "  " . $lines[$i] . "\n";
                }

            } else {
                echo "⚠️ Decrypted but doesn't look like PEM\n";
                echo "First 50 chars: " . substr($decrypted, 0, 50) . "\n";
                echo "Hex (first 32 bytes): " . bin2hex(substr($decrypted, 0, 32)) . "\n";
            }
        }

    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "</pre>\n";
echo "<p><strong style='color: red;'>⚠️ DELETE THIS FILE!</strong></p>\n";
