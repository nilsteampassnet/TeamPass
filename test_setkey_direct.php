<?php
/**
 * Test v3 with setKey() instead of setPassword()
 * This bypasses setPassword() to isolate the issue
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

echo "<h2>Testing v3 with setKey() directly</h2>\n";
echo "<pre>\n";

echo "Encrypted key length: " . strlen($encrypted) . " bytes\n";
echo "Remainder / 16: " . (strlen($encrypted) % 16) . "\n\n";

$password = 'D8hDEr)4T2+}';

// Derive keys manually - TEST BOTH 16 AND 32 BYTES
$key16 = hash_pbkdf2('sha1', $password, 'phpseclib/salt', 1000, 16, true);
$key32 = hash_pbkdf2('sha1', $password, 'phpseclib/salt', 1000, 32, true);

echo "Derived key 16 bytes (AES-128): " . bin2hex($key16) . "\n";
echo "Derived key 32 bytes (AES-256): " . bin2hex($key32) . "\n\n";

$tests = [
    'CBC + 16-byte key' => function($enc) use ($key16) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->setKey($key16);
        $cipher->setIV(str_repeat("\0", 16));
        return $cipher->decrypt($enc);
    },

    'CBC + 32-byte key' => function($enc) use ($key32) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->setKey($key32);
        $cipher->setIV(str_repeat("\0", 16));
        return $cipher->decrypt($enc);
    },

    'CBC + disablePadding + 16-byte key' => function($enc) use ($key16) {
        $cipher = new \phpseclib3\Crypt\AES('cbc');
        $cipher->disablePadding();
        $cipher->setKey($key16);
        $cipher->setIV(str_repeat("\0", 16));
        return $cipher->decrypt($enc);
    },

    'CTR + 16-byte key' => function($enc) use ($key16) {
        $cipher = new \phpseclib3\Crypt\AES('ctr');
        $cipher->setKey($key16);
        $cipher->setIV(str_repeat("\0", 16));
        return $cipher->decrypt($enc);
    },

    'CTR + 32-byte key' => function($enc) use ($key32) {
        $cipher = new \phpseclib3\Crypt\AES('ctr');
        $cipher->setKey($key32);
        $cipher->setIV(str_repeat("\0", 16));
        return $cipher->decrypt($enc);
    },
];

echo "=== Testing " . count($tests) . " configurations ===\n\n";

foreach ($tests as $name => $test) {
    echo "Config: $name\n";
    echo str_repeat('-', 70) . "\n";

    try {
        $decrypted = $test($encrypted);

        if (strpos($decrypted, '-----BEGIN') !== false) {
            echo "✅✅✅ SUCCESS! VALID PEM KEY! ✅✅✅\n";
            echo "🎯 USE THIS CONFIG IN CryptoManager! 🎯\n";
            $lines = explode("\n", $decrypted);
            echo "\nFirst 3 lines:\n";
            for ($i = 0; $i < min(3, count($lines)); $i++) {
                echo "  " . $lines[$i] . "\n";
            }
        } else {
            echo "⚠️ Decrypted but not PEM\n";
            echo "Length: " . strlen($decrypted) . " bytes\n";
            echo "First 50: " . substr($decrypted, 0, 50) . "\n";
        }

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "</pre>\n";
echo "<p style='color:red'><strong>⚠️ DELETE THIS FILE!</strong></p>\n";
