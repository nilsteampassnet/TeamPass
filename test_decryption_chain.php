<?php
/**
 * Test decryption chain for a specific user
 * Access: http://localhost/TeamPass/test_decryption_chain.php?user_id=X&password=XXX
 *
 * DELETE THIS FILE AFTER USE - CONTAINS SECURITY SENSITIVE CODE
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/includes/config/settings.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/install/tp.functions.php';
require_once __DIR__ . '/sources/main.functions.php';

use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;

// Get parameters
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$userPassword = filter_input(INPUT_GET, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$userId || !$userPassword) {
    die("Usage: test_decryption_chain.php?user_id=123&password=yourpassword\n");
}

// Connect to database
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = defuse_return_decrypted(DB_PASSWD);
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

echo "<h2>Decryption Chain Test for User ID: $userId</h2>\n";
echo "<pre>\n";

// Step 1: Get user from database
echo "=== Step 1: Fetch User Data ===\n";
$user = DB::queryFirstRow(
    'SELECT id, login, private_key, public_key FROM ' . prefixTable('users') . ' WHERE id = %i',
    $userId
);

if (!$user) {
    die("❌ User not found\n");
}

echo "✅ User found: {$user['login']}\n";
echo "Private key length: " . strlen($user['private_key']) . " bytes\n";
echo "Public key length: " . strlen($user['public_key']) . " bytes\n";
echo "Private key (first 50 chars): " . substr($user['private_key'], 0, 50) . "...\n";
echo "\n";

// Step 2: Decrypt private key
echo "=== Step 2: Decrypt Private Key ===\n";
require_once __DIR__ . '/sources/main.functions.php';

try {
    $decryptedPrivateKey = decryptPrivateKey($userPassword, $user['private_key']);

    if (empty($decryptedPrivateKey)) {
        echo "❌ decryptPrivateKey() returned empty string\n";
        echo "This means AES decryption failed!\n";
        echo "\nDEBUG: Testing direct AES decryption...\n";

        // Test direct AES decryption
        $rawEncrypted = base64_decode($user['private_key']);
        echo "Encrypted data length: " . strlen($rawEncrypted) . " bytes\n";

        try {
            $directDecrypt = \TeampassClasses\CryptoManager\CryptoManager::aesDecrypt(
                $rawEncrypted,
                $userPassword
            );
            echo "Direct decryption result length: " . strlen($directDecrypt) . " bytes\n";
            echo "Result (first 50 chars): " . substr($directDecrypt, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "❌ Direct AES decryption failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✅ Private key decrypted successfully\n";
        echo "Decrypted key length: " . strlen($decryptedPrivateKey) . " bytes\n";
        echo "Decrypted key (first 100 chars): " . substr($decryptedPrivateKey, 0, 100) . "...\n";

        // Verify it's a valid PEM key
        $decoded = base64_decode($decryptedPrivateKey);
        if (strpos($decoded, '-----BEGIN') !== false) {
            echo "✅ Looks like a valid PEM key (contains -----BEGIN)\n";
        } else {
            echo "⚠️ Doesn't look like a PEM key (missing -----BEGIN)\n";
            echo "Decoded (first 100 chars): " . substr($decoded, 0, 100) . "...\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception during decryptPrivateKey(): " . $e->getMessage() . "\n";
}

echo "\n";

// Step 3: Test with a real item (if we got the private key)
if (!empty($decryptedPrivateKey)) {
    echo "=== Step 3: Test with Real Item ===\n";

    // Get first item accessible to this user
    $item = DB::queryFirstRow(
        'SELECT i.id, i.label, i.pw, s.share_key
         FROM ' . prefixTable('items') . ' i
         INNER JOIN ' . prefixTable('sharekeys_items') . ' s ON i.id = s.object_id
         WHERE s.user_id = %i
         LIMIT 1',
        $userId
    );

    if ($item) {
        echo "Testing with item: {$item['label']} (ID: {$item['id']})\n";
        echo "Encrypted password length: " . strlen($item['pw']) . " bytes\n";
        echo "ShareKey length: " . strlen($item['share_key']) . " bytes\n\n";

        // Step 3a: Decrypt sharekey with private key
        echo "Step 3a: Decrypt ShareKey with Private Key\n";
        try {
            $decryptedShareKey = decryptUserObjectKey(
                $item['share_key'],
                base64_decode($decryptedPrivateKey)
            );

            if (empty($decryptedShareKey)) {
                echo "❌ ShareKey decryption returned empty\n";
            } else {
                echo "✅ ShareKey decrypted\n";
                echo "ShareKey length: " . strlen($decryptedShareKey) . " bytes\n\n";

                // Step 3b: Decrypt item password with sharekey
                echo "Step 3b: Decrypt Item Password with ShareKey\n";
                try {
                    $decryptedPassword = doDataDecryption($item['pw'], $decryptedShareKey);

                    if (empty($decryptedPassword)) {
                        echo "❌ Item password decryption returned empty\n";
                    } else {
                        echo "✅ Item password decrypted successfully!\n";
                        echo "Password: " . base64_decode($decryptedPassword) . "\n";
                    }
                } catch (Exception $e) {
                    echo "❌ Item password decryption failed: " . $e->getMessage() . "\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ ShareKey decryption failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No items found for this user\n";
    }
}

echo "\n=== Phpseclib Version Check ===\n";
if (class_exists('phpseclib3\\Crypt\\AES')) {
    echo "✅ phpseclib v3 loaded\n";
} elseif (class_exists('Crypt_AES')) {
    echo "✅ phpseclib v1 loaded\n";
} else {
    echo "❌ No phpseclib found\n";
}

echo "\n</pre>\n";
echo "<p><strong style='color: red;'>⚠️ DELETE THIS FILE IMMEDIATELY - IT EXPOSES PASSWORDS!</strong></p>\n";
