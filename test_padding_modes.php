<?php
/**
 * Test if private key was encrypted with padding disabled
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$encryptedPrivateKey = filter_input(INPUT_GET, 'key', FILTER_UNSAFE_RAW);
$password = filter_input(INPUT_GET, 'pwd', FILTER_UNSAFE_RAW);

if (!$encryptedPrivateKey || !$password) {
    die("Usage: test_padding_modes.php?key=BASE64_KEY&pwd=PASSWORD\n");
}

echo "<h2>Testing Padding Modes</h2>\n";
echo "<pre>\n";

$decoded = base64_decode($encryptedPrivateKey);
echo "Encrypted key length: " . strlen($decoded) . " bytes\n";
echo "Remainder when divided by 16: " . (strlen($decoded) % 16) . "\n\n";

$strategies = [];

// phpseclib v3 - Cannot disable padding in v3, it's always enabled
if (class_exists('phpseclib3\\Crypt\\AES')) {
    // Try with enablePadding() explicitly
    $strategies['v3_cbc_explicit_padding'] = function($data, $pwd) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->setIV(str_repeat("\0", 16));
        $cipher->setPassword($pwd, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
        $cipher->enablePadding(); // Explicit
        return $cipher->decrypt($data);
    };
}

// phpseclib v1 - CAN disable padding!
if (class_exists('Crypt_AES')) {
    $strategies['v1_cbc_default_padding'] = function($data, $pwd) {
        $cipher = new Crypt_AES(CRYPT_MODE_CBC);
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };

    $strategies['v1_cbc_disabled_padding'] = function($data, $pwd) {
        $cipher = new Crypt_AES(CRYPT_MODE_CBC);
        $cipher->disablePadding();
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };

    $strategies['v1_ctr_default'] = function($data, $pwd) {
        $cipher = new Crypt_AES(CRYPT_MODE_CTR);
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };

    $strategies['v1_cfb_default'] = function($data, $pwd) {
        $cipher = new Crypt_AES(CRYPT_MODE_CFB);
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };

    $strategies['v1_ofb_default'] = function($data, $pwd) {
        $cipher = new Crypt_AES(CRYPT_MODE_OFB);
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };

    // Default constructor (no mode specified)
    $strategies['v1_default_no_mode'] = function($data, $pwd) {
        $cipher = new Crypt_AES();
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };
}

echo "=== Testing " . count($strategies) . " strategies ===\n\n";

foreach ($strategies as $name => $strategy) {
    echo "Strategy: $name\n";
    echo str_repeat('-', 70) . "\n";

    try {
        $decrypted = $strategy($decoded, $password);

        if ($decrypted === false) {
            echo "❌ Decryption returned false\n";
        } else {
            echo "✓ Decryption succeeded\n";
            echo "Decrypted length: " . strlen($decrypted) . " bytes\n";

            // Check if it looks like a PEM key
            if (strpos($decrypted, '-----BEGIN') !== false) {
            echo "✅✅✅ SUCCESS! VALID PEM KEY! ✅✅✅\n";
            echo "Key type: ";
            if (strpos($decrypted, 'RSA PRIVATE KEY') !== false) {
                echo "RSA PRIVATE KEY\n";
            }

            echo "\n🎯 THIS IS THE CORRECT MODE AND PADDING! 🎯\n";

            $lines = explode("\n", $decrypted);
            echo "\nFirst 3 lines:\n";
            for ($i = 0; $i < min(3, count($lines)); $i++) {
                echo "  " . $lines[$i] . "\n";
            }

            } else {
                echo "⚠️ Decrypted but not PEM\n";
                echo "First 50 chars: " . substr($decrypted, 0, 50) . "\n";
            }
        }

    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "</pre>\n";
echo "<p><strong style='color: red;'>⚠️ DELETE THIS FILE!</strong></p>\n";
