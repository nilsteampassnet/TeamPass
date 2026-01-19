<?php
/**
 * Test phpseclib v3 compatibility with v1-encrypted data
 * Now that v3 is properly installed, let's test if CryptoManager can decrypt v1 data
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config/settings.php';
require_once __DIR__ . '/vendor/autoload.php';

use TeampassClasses\CryptoManager\CryptoManager;
use TeampassClasses\PasswordManager\PasswordManager;

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $password = $_POST['password'] ?? '';

    if ($userId && $password) {
        DB::$host = DB_HOST;
        DB::$user = DB_USER;
        DB::$password = defuse_return_decrypted(DB_PASSWD);
        DB::$dbName = DB_NAME;
        DB::$port = DB_PORT;
        DB::$encoding = DB_ENCODING;

        echo "<pre>\n";
        echo "=== phpseclib v3 Compatibility Test ===\n\n";

        // Get user data
        $user = DB::queryFirstRow(
            'SELECT id, login, pw, private_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );

        if ($user) {
            echo "User: {$user['login']}\n";
            echo "User ID: {$user['id']}\n\n";

            // Verify password
            echo "=== Step 1: Password Verification ===\n";
            $passwordManager = new PasswordManager();
            $isValid = $passwordManager->verifyPassword($user['pw'], $password);

            if ($isValid) {
                echo "✅ Password is VALID\n\n";

                // Test private key decryption with v3
                echo "=== Step 2: Private Key Decryption with v3 ===\n";
                $ciphertext = base64_decode($user['private_key']);
                echo "Encrypted private key length: " . strlen($ciphertext) . " bytes\n";
                echo "Remainder / 16: " . (strlen($ciphertext) % 16) . " (should be 0 for valid AES-CBC)\n\n";

                try {
                    $decrypted = CryptoManager::aesDecrypt($ciphertext, $password);

                    echo "✅ Decryption succeeded!\n";
                    echo "Decrypted length: " . strlen($decrypted) . " bytes\n";

                    // Check if it's a valid PEM key
                    if (strpos($decrypted, '-----BEGIN') !== false) {
                        echo "✅✅✅ SUCCESS! Valid PEM private key!\n";
                        echo "\nKey type: RSA PRIVATE KEY\n";
                        $lines = explode("\n", $decrypted);
                        echo "\nFirst 3 lines:\n";
                        for ($i = 0; $i < min(3, count($lines)); $i++) {
                            echo "  " . $lines[$i] . "\n";
                        }
                        echo "\nLast 3 lines:\n";
                        for ($i = max(0, count($lines) - 3); $i < count($lines); $i++) {
                            echo "  " . $lines[$i] . "\n";
                        }

                        // Extra validation: try to load the key with phpseclib v3
                        echo "\n=== Step 3: Validate Private Key with phpseclib v3 ===\n";
                        try {
                            $keyObject = \phpseclib3\Crypt\PublicKeyLoader::load($decrypted);
                            if ($keyObject instanceof \phpseclib3\Crypt\RSA\PrivateKey) {
                                echo "✅ Private key is valid and loadable by phpseclib v3\n";
                                echo "Key size: " . $keyObject->getLength() . " bits\n";

                                // Test encryption/decryption with this key
                                echo "\n=== Step 4: Test RSA Operations ===\n";
                                $testData = "Test message for RSA";
                                $publicKey = $keyObject->getPublicKey();
                                $encrypted = $publicKey->encrypt($testData);
                                echo "✅ RSA encryption successful\n";

                                $decryptedTest = $keyObject->decrypt($encrypted);
                                if ($decryptedTest === $testData) {
                                    echo "✅ RSA decryption successful - data matches!\n";
                                    echo "\n🎉 FULL SUCCESS! The migrated key works perfectly with v3!\n";
                                } else {
                                    echo "❌ RSA decryption mismatch\n";
                                }
                            } else {
                                echo "⚠️ Loaded key is not a PrivateKey instance\n";
                            }
                        } catch (Exception $e) {
                            echo "❌ Key validation failed: " . $e->getMessage() . "\n";
                        }

                    } else {
                        echo "❌ Decrypted data is NOT a valid PEM key\n";
                        echo "First 100 bytes (hex): " . bin2hex(substr($decrypted, 0, 100)) . "\n";
                        echo "First 50 chars: " . substr($decrypted, 0, 50) . "\n";
                    }

                } catch (Exception $e) {
                    echo "❌ Decryption FAILED with v3\n";
                    echo "Error: " . $e->getMessage() . "\n\n";

                    echo "=== Diagnostic Information ===\n";
                    echo "This means phpseclib v3 cannot decrypt data encrypted with v1\n";
                    echo "Possible causes:\n";
                    echo "1. Different default cipher modes\n";
                    echo "2. Different key derivation beyond PBKDF2\n";
                    echo "3. Different padding schemes\n";
                    echo "4. Need to re-encrypt all private keys with v3\n";
                }

            } else {
                echo "❌ Password is INVALID\n";
                echo "Cannot proceed with decryption test.\n";
            }
        } else {
            echo "❌ User not found\n";
        }

        echo "</pre>\n";
        echo "<hr>\n";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>phpseclib v3 Compatibility Test</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        form { background: #f0f0f0; padding: 20px; border-radius: 5px; max-width: 600px; }
        input { width: 100%; padding: 8px; margin: 5px 0; font-family: monospace; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
        .warning { color: red; font-weight: bold; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>phpseclib v3 Compatibility Test</h1>
    <p class="info">Testing if phpseclib v3 can decrypt data encrypted with v1</p>
    <p class="warning">⚠️ DELETE THIS FILE AFTER USE!</p>

    <form method="POST">
        <h3>Test Configuration</h3>

        <label>User ID:</label>
        <input type="number" name="user_id" value="10000000" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <p style="color: #666; font-size: 12px;">
            This test will:<br>
            1. Verify your password against the database<br>
            2. Attempt to decrypt your private key with phpseclib v3<br>
            3. Validate the decrypted key structure<br>
            4. Test RSA operations with the decrypted key
        </p>

        <button type="submit">Run Compatibility Test</button>
    </form>

    <hr>

    <h3>Background</h3>
    <p>Previous tests showed that phpseclib v1 and v3 produce different encrypted output even with identical PBKDF2-derived keys. This suggests a deeper incompatibility.</p>

    <p>This test will determine if the CryptoManager implementation correctly handles backward compatibility with v1-encrypted private keys.</p>
</body>
</html>
