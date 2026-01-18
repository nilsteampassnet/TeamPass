<?php
/**
 * Test different AES decryption strategies for private key
 * This will try multiple parameter combinations to find which one works
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$encryptedPrivateKey = filter_input(INPUT_GET, 'key', FILTER_UNSAFE_RAW);
$password = filter_input(INPUT_GET, 'pwd', FILTER_UNSAFE_RAW);

if (!$encryptedPrivateKey || !$password) {
    die("Usage: test_private_key_decrypt.php?key=BASE64_ENCRYPTED_KEY&pwd=PASSWORD\n\nYou can find the encrypted key in teampass_users.private_key column\n");
}

echo "<h2>Private Key Decryption Strategy Test</h2>\n";
echo "<pre>\n";

echo "Encrypted key length: " . strlen($encryptedPrivateKey) . " bytes\n";
echo "Password length: " . strlen($password) . " chars\n";
$decoded = base64_decode($encryptedPrivateKey);
echo "Decoded key length: " . strlen($decoded) . " bytes\n\n";

$strategies = [];

// Strategy 1: phpseclib v3 with explicit parameters + setIV BEFORE setPassword
if (class_exists('phpseclib3\\Crypt\\AES')) {
    $strategies['v3_iv_before'] = function($data, $pwd) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->setIV(str_repeat("\0", 16));
        $cipher->setPassword($pwd, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
        return $cipher->decrypt($data);
    };

    // Strategy 2: v3 with setPassword BEFORE setIV
    $strategies['v3_iv_after'] = function($data, $pwd) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->setPassword($pwd, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
        $cipher->setIV(str_repeat("\0", 16));
        return $cipher->decrypt($data);
    };

    // Strategy 3: v3 with default setPassword (no params)
    $strategies['v3_default'] = function($data, $pwd) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->setIV(str_repeat("\0", 16));
        $cipher->setPassword($pwd); // Default params
        return $cipher->decrypt($data);
    };

    // Strategy 4: v3 without setIV at all
    $strategies['v3_no_iv'] = function($data, $pwd) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->setPassword($pwd, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
        return $cipher->decrypt($data);
    };
}

// Strategy 5: phpseclib v1 (if available)
if (class_exists('Crypt_AES')) {
    $strategies['v1_default'] = function($data, $pwd) {
        $cipher = new Crypt_AES();
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };

    $strategies['v1_explicit'] = function($data, $pwd) {
        $cipher = new Crypt_AES(CRYPT_MODE_CBC);
        $cipher->setPassword($pwd, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
        return $cipher->decrypt($data);
    };
}

echo "=== Testing " . count($strategies) . " decryption strategies ===\n\n";

foreach ($strategies as $name => $strategy) {
    echo "Strategy: $name\n";
    echo str_repeat('-', 60) . "\n";

    try {
        $decrypted = $strategy($decoded, $password);
        $decryptedB64 = base64_encode($decrypted);

        echo "✓ Decryption succeeded\n";
        echo "Decrypted length: " . strlen($decrypted) . " bytes\n";
        echo "First 100 bytes: " . substr($decrypted, 0, 100) . "\n";

        // Check if it looks like a PEM key
        if (strpos($decrypted, '-----BEGIN') !== false) {
            echo "✅ SUCCESS! This looks like a valid PEM private key!\n";
            echo "Key type: ";
            if (strpos($decrypted, 'RSA PRIVATE KEY') !== false) {
                echo "RSA PRIVATE KEY\n";
            } elseif (strpos($decrypted, 'PRIVATE KEY') !== false) {
                echo "PRIVATE KEY (PKCS#8)\n";
            }

            // Show full key
            echo "\n--- DECRYPTED PRIVATE KEY ---\n";
            echo $decrypted . "\n";
            echo "--- END ---\n";
        } else {
            echo "⚠️ Decrypted but doesn't look like PEM (missing -----BEGIN)\n";
            echo "Hex dump (first 32 bytes): " . bin2hex(substr($decrypted, 0, 32)) . "\n";
        }

    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "</pre>\n";
echo "<p><strong style='color: red;'>⚠️ DELETE THIS FILE - IT EXPOSES PRIVATE KEYS!</strong></p>\n";
