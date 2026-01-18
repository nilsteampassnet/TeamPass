<?php
/**
 * Master test script with POST form to avoid URL encoding issues
 */

declare(strict_types=1);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $password = $_POST['password'] ?? ''; // No sanitization to preserve special chars
    $encryptedKey = $_POST['encrypted_key'] ?? '';

    if ($userId && $password) {
        require_once __DIR__ . '/includes/config/settings.php';
        require_once __DIR__ . '/vendor/autoload.php';

        DB::$host = DB_HOST;
        DB::$user = DB_USER;
        DB::$password = defuse_return_decrypted(DB_PASSWD);
        DB::$dbName = DB_NAME;
        DB::$port = DB_PORT;
        DB::$encoding = DB_ENCODING;

        echo "<pre>\n";
        echo "=== Password Verification ===\n";

        $user = DB::queryFirstRow(
            'SELECT id, login, pw, private_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );

        if ($user) {
            echo "User: {$user['login']}\n";
            echo "Password entered: " . str_repeat('*', strlen($password)) . " ({" . strlen($password) . "} chars)\n";
            echo "Password preview: " . substr($password, 0, 3) . "..." . substr($password, -3) . "\n\n";

            // Verify password
            $passwordManager = new TeampassClasses\PasswordManager\PasswordManager();
            $isValid = $passwordManager->verifyPassword($user['pw'], $password); // CORRECT ORDER

            if ($isValid) {
                echo "✅ Password is CORRECT!\n\n";

                // Now test private key decryption with v1
                echo "=== Testing Private Key Decryption ===\n";

                $ciphertext = base64_decode($user['private_key']);
                echo "Private key length: " . strlen($ciphertext) . " bytes\n\n";

                // Test with CryptoManager (v3 compatible)
                echo "=== Testing with CryptoManager ===\n";
                try {
                    $decrypted = \TeampassClasses\CryptoManager\CryptoManager::aesDecrypt(
                        $ciphertext,
                        $password
                    );

                    if (strpos($decrypted, '-----BEGIN') !== false) {
                        echo "✅✅✅ SUCCESS! Private key decrypted with CryptoManager! ✅✅✅\n";
                        echo "Key type: RSA PRIVATE KEY\n";
                        $lines = explode("\n", $decrypted);
                        echo "\nFirst 3 lines:\n";
                        for ($i = 0; $i < min(3, count($lines)); $i++) {
                            echo "  " . $lines[$i] . "\n";
                        }
                    } else {
                        echo "⚠️ CryptoManager decrypted but not PEM (length: " . strlen($decrypted) . " bytes)\n";
                        echo "First 50 chars: " . substr($decrypted, 0, 50) . "\n";
                    }
                } catch (Exception $e) {
                    echo "❌ CryptoManager failed: " . $e->getMessage() . "\n";
                }

                echo "\n";

                // Test with v1 if available
                if (class_exists('Crypt_AES')) {
                    echo "=== Testing with phpseclib v1 ===\n";
                    $cipher = new Crypt_AES();
                    $cipher->setPassword($password);

                    try {
                        $decrypted = $cipher->decrypt($ciphertext);

                        if (strpos($decrypted, '-----BEGIN') !== false) {
                            echo "✅✅✅ SUCCESS! Private key decrypted with v1 default! ✅✅✅\n";
                            echo "Key type: RSA PRIVATE KEY\n";
                            $lines = explode("\n", $decrypted);
                            echo "\nFirst 3 lines:\n";
                            for ($i = 0; $i < min(3, count($lines)); $i++) {
                                echo "  " . $lines[$i] . "\n";
                            }
                        } else {
                            echo "⚠️ Decrypted but not PEM (length: " . strlen($decrypted) . " bytes)\n";
                            echo "First 50 chars: " . substr($decrypted, 0, 50) . "\n";
                        }
                    } catch (Exception $e) {
                        echo "❌ Decryption failed: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "=== phpseclib v1 not available ===\n";
                    echo "(This is normal if phpseclib v3 is installed)\n";
                }

            } else {
                echo "❌ Password is INCORRECT!\n";
                echo "Cannot test decryption with wrong password.\n";
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
    <title>TeamPass Decryption Test</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        form { background: #f0f0f0; padding: 20px; border-radius: 5px; max-width: 600px; }
        input { width: 100%; padding: 8px; margin: 5px 0; font-family: monospace; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
        .warning { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>TeamPass Private Key Decryption Test</h1>
    <p class="warning">⚠️ DELETE THIS FILE AFTER USE - SECURITY SENSITIVE!</p>

    <form method="POST">
        <h3>Test Configuration</h3>

        <label>User ID:</label>
        <input type="number" name="user_id" value="10000000" required>

        <label>Password (preserves special characters like +):</label>
        <input type="password" name="password" placeholder="Enter password with special chars" required>

        <p style="color: #666; font-size: 12px;">
            Note: This form uses POST to preserve special characters like + and %<br>
            Your password will NOT be URL-encoded (no + → space conversion)
        </p>

        <button type="submit">Test Password & Decrypt Private Key</button>
    </form>

    <hr>

    <h3>Instructions</h3>
    <ol>
        <li>Enter your user ID (found in teampass_users.id column)</li>
        <li>Enter your password EXACTLY as you use it to login</li>
        <li>Click "Test Password & Decrypt Private Key"</li>
        <li>The script will:
            <ul>
                <li>Verify the password matches the database hash</li>
                <li>Attempt to decrypt your private key with phpseclib v1</li>
                <li>Show if decryption produces a valid PEM key</li>
            </ul>
        </li>
    </ol>
</body>
</html>
