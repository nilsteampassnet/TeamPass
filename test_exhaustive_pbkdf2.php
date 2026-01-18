<?php
/**
 * Exhaustive test of ALL PBKDF2 parameter combinations
 */

declare(strict_types=1);

$encryptedPrivateKey = filter_input(INPUT_GET, 'key', FILTER_UNSAFE_RAW);
$password = filter_input(INPUT_GET, 'pwd', FILTER_UNSAFE_RAW);

if (!$encryptedPrivateKey || !$password) {
    die("Usage: test_exhaustive_pbkdf2.php?key=BASE64_KEY&pwd=PASSWORD\n");
}

echo "<h2>Exhaustive PBKDF2 Parameter Test</h2>\n";
echo "<pre>\n";

$ciphertext = base64_decode($encryptedPrivateKey);
echo "Original password: [HIDDEN]\n";
echo "Password length: " . strlen($password) . " chars\n";
echo "Ciphertext length: " . strlen($ciphertext) . " bytes\n\n";

$strategies = [];

// Test different salts
$salts = [
    'phpseclib/salt',
    'phpseclib',
    '',
    $password, // Password as salt
];

// Test different hash algorithms
$hashes = ['sha1', 'md5', 'sha256'];

// Test different iteration counts
$iterations = [1000, 1, 100, 10000];

// Test different key lengths (AES-128=16, AES-192=24, AES-256=32)
$keyLengths = [16, 24, 32];

// Test with password transformations
$passwordVariants = [
    'original' => $password,
    'md5' => md5($password),
    'sha1' => sha1($password),
    'sha256' => hash('sha256', $password),
];

$count = 0;
foreach ($salts as $saltName => $salt) {
    foreach ($hashes as $hash) {
        foreach ($iterations as $iter) {
            foreach ($keyLengths as $keyLen) {
                foreach ($passwordVariants as $pwdName => $pwd) {
                    $aesMode = ($keyLen == 16) ? 'aes-128-ctr' : (($keyLen == 24) ? 'aes-192-ctr' : 'aes-256-ctr');

                    $saltDisplay = is_int($saltName) ? substr($salt, 0, 20) : $saltName;
                    $name = "pwd:{$pwdName}|hash:{$hash}|salt:{$saltDisplay}|iter:{$iter}|keylen:{$keyLen}";

                    $strategies[$name] = function($data) use ($pwd, $hash, $salt, $iter, $keyLen, $aesMode) {
                        $key = hash_pbkdf2($hash, $pwd, $salt, $iter, $keyLen, true);
                        $iv = str_repeat("\0", 16);
                        return openssl_decrypt($data, $aesMode, $key, OPENSSL_RAW_DATA, $iv);
                    };

                    $count++;
                }
            }
        }
    }
}

echo "=== Testing $count parameter combinations ===\n";
echo "(Only showing successful PEM decryptions)\n\n";

$successCount = 0;

foreach ($strategies as $name => $strategy) {
    try {
        $decrypted = $strategy($ciphertext);

        if ($decrypted !== false && strpos($decrypted, '-----BEGIN') !== false) {
            $successCount++;

            echo "✅✅✅ SUCCESS #$successCount! ✅✅✅\n";
            echo "Strategy: $name\n";
            echo str_repeat('=', 70) . "\n";

            // Parse parameters from name
            preg_match('/pwd:(\w+).*hash:(\w+).*salt:([^|]+).*iter:(\d+).*keylen:(\d+)/', $name, $matches);

            echo "🎯 WORKING CONFIGURATION:\n";
            echo "  Password variant: {$matches[1]}\n";
            echo "  Hash algorithm: {$matches[2]}\n";
            echo "  Salt: {$matches[3]}\n";
            echo "  Iterations: {$matches[4]}\n";
            echo "  Key length: {$matches[5]} bytes\n";

            $lines = explode("\n", $decrypted);
            echo "\nFirst 3 lines of decrypted key:\n";
            for ($i = 0; $i < min(3, count($lines)); $i++) {
                echo "  " . $lines[$i] . "\n";
            }
            echo "\n";
        }

    } catch (Exception $e) {
        // Silent - too many to show
    }
}

if ($successCount === 0) {
    echo "❌ No successful PEM decryption found in $count combinations.\n\n";
    echo "This suggests:\n";
    echo "1. Password is transformed in a way not tested\n";
    echo "2. Encryption uses a non-standard method\n";
    echo "3. The encrypted data might be corrupted\n";
    echo "4. A different encryption library was used\n";
}

echo "\n</pre>\n";
echo "<p><strong style='color: red;'>⚠️ DELETE THIS FILE!</strong></p>\n";
