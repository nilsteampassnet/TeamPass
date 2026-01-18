<?php
/**
 * Test ALL AES modes to find which one was used to encrypt private keys
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$encryptedPrivateKey = filter_input(INPUT_GET, 'key', FILTER_UNSAFE_RAW);
$password = filter_input(INPUT_GET, 'pwd', FILTER_UNSAFE_RAW);

if (!$encryptedPrivateKey || !$password) {
    die("Usage: test_all_modes.php?key=BASE64_KEY&pwd=PASSWORD\n");
}

echo "<h2>Testing ALL AES Modes</h2>\n";
echo "<pre>\n";

$decoded = base64_decode($encryptedPrivateKey);
echo "Encrypted key length: " . strlen($encryptedPrivateKey) . " bytes (base64)\n";
echo "Decoded length: " . strlen($decoded) . " bytes\n";
echo "Is multiple of 16? " . (strlen($decoded) % 16 === 0 ? 'YES ✅' : 'NO ❌ (' . strlen($decoded) % 16 . ' remainder)') . "\n\n";

$strategies = [];

// phpseclib v3 modes
if (class_exists('phpseclib3\\Crypt\\AES')) {
    $modes = ['cbc', 'ctr', 'ecb', 'cfb', 'ofb', 'gcm'];

    foreach ($modes as $mode) {
        $strategies["v3_$mode"] = function($data, $pwd) use ($mode) {
            $cipher = new \phpseclib3\Crypt\AES($mode);

            // Only set IV for modes that need it
            if (in_array($mode, ['cbc', 'ctr', 'cfb', 'ofb'])) {
                $cipher->setIV(str_repeat("\0", 16));
            }

            $cipher->setPassword($pwd, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
            return $cipher->decrypt($data);
        };
    }
}

// phpseclib v1 modes
if (class_exists('Crypt_AES')) {
    $v1_modes = [
        'ctr' => CRYPT_MODE_CTR,
        'ecb' => CRYPT_MODE_ECB,
        'cbc' => CRYPT_MODE_CBC,
        'cfb' => CRYPT_MODE_CFB,
        'ofb' => CRYPT_MODE_OFB,
    ];

    foreach ($v1_modes as $name => $constant) {
        $strategies["v1_$name"] = function($data, $pwd) use ($constant) {
            $cipher = new Crypt_AES($constant);
            $cipher->setPassword($pwd);
            return $cipher->decrypt($data);
        };
    }

    // v1 default (no mode specified)
    $strategies['v1_default'] = function($data, $pwd) {
        $cipher = new Crypt_AES();
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };

    // v1 with disablePadding
    $strategies['v1_cbc_no_padding'] = function($data, $pwd) {
        $cipher = new Crypt_AES(CRYPT_MODE_CBC);
        $cipher->disablePadding();
        $cipher->setPassword($pwd);
        return $cipher->decrypt($data);
    };
}

echo "=== Testing " . count($strategies) . " mode/version combinations ===\n\n";

foreach ($strategies as $name => $strategy) {
    echo "Strategy: $name\n";
    echo str_repeat('-', 70) . "\n";

    try {
        $decrypted = $strategy($decoded, $password);

        echo "✓ Decryption succeeded\n";
        echo "Decrypted length: " . strlen($decrypted) . " bytes\n";

        // Check if it looks like a PEM key
        if (strpos($decrypted, '-----BEGIN') !== false) {
            echo "✅✅✅ SUCCESS! This is a valid PEM private key! ✅✅✅\n";
            echo "Key type: ";
            if (strpos($decrypted, 'RSA PRIVATE KEY') !== false) {
                echo "RSA PRIVATE KEY\n";
            } elseif (strpos($decrypted, 'PRIVATE KEY') !== false) {
                echo "PRIVATE KEY (PKCS#8)\n";
            }

            echo "\n🎯 USE THIS MODE IN CryptoManager! 🎯\n";

            // Show first few lines
            $lines = explode("\n", $decrypted);
            echo "\nFirst 5 lines of key:\n";
            for ($i = 0; $i < min(5, count($lines)); $i++) {
                echo "  " . $lines[$i] . "\n";
            }

        } else {
            echo "⚠️ Decrypted but doesn't look like PEM\n";
            echo "First 50 chars: " . substr($decrypted, 0, 50) . "\n";
            echo "Hex dump (first 32 bytes): " . bin2hex(substr($decrypted, 0, 32)) . "\n";
        }

    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "</pre>\n";
echo "<p><strong style='color: red;'>⚠️ DELETE THIS FILE IMMEDIATELY!</strong></p>\n";
