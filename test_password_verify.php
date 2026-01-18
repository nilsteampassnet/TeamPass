<?php
/**
 * Verify user password hash in database
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config/settings.php';
require_once __DIR__ . '/vendor/autoload.php';

use TeampassClasses\PasswordManager\PasswordManager;

$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$password = filter_input(INPUT_GET, 'password', FILTER_UNSAFE_RAW);

if (!$userId || !$password) {
    die("Usage: test_password_verify.php?user_id=123&password=XXX\n");
}

// Connect to database
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = defuse_return_decrypted(DB_PASSWD);
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

echo "<h2>Password Verification Test</h2>\n";
echo "<pre>\n";

$user = DB::queryFirstRow(
    'SELECT id, login, pw FROM ' . prefixTable('users') . ' WHERE id = %i',
    $userId
);

if (!$user) {
    die("❌ User not found\n");
}

echo "User ID: {$user['id']}\n";
echo "Login: {$user['login']}\n";
echo "Password hash length: " . strlen($user['pw']) . " chars\n";
echo "Hash (first 60 chars): " . substr($user['pw'], 0, 60) . "...\n\n";

// Identify hash type
if (substr($user['pw'], 0, 4) === '$2y$') {
    echo "Hash type: bcrypt\n";
} elseif (substr($user['pw'], 0, 9) === '$argon2i$') {
    echo "Hash type: argon2i\n";
} elseif (substr($user['pw'], 0, 9) === '$argon2id') {
    echo "Hash type: argon2id\n";
} elseif (strlen($user['pw']) === 32) {
    echo "Hash type: MD5\n";
} elseif (strlen($user['pw']) === 40) {
    echo "Hash type: SHA-1\n";
} else {
    echo "Hash type: Unknown\n";
}

echo "\n=== Testing password verification ===\n";

// Test with PasswordManager
$passwordManager = new TeampassClasses\PasswordManager\PasswordManager();
$isValid = $passwordManager->verifyPassword($password, $user['pw']);

if ($isValid) {
    echo "✅ Password is VALID (PasswordManager verified)\n";
} else {
    echo "❌ Password is INVALID (PasswordManager failed)\n";
}

// Test with password_verify
if (password_verify($password, $user['pw'])) {
    echo "✅ Password is VALID (password_verify)\n";
} else {
    echo "❌ Password is INVALID (password_verify)\n";
}

// Test with MD5 (legacy)
if (md5($password) === $user['pw']) {
    echo "✅ Password is VALID (MD5 match)\n";
} else {
    echo "❌ Password is INVALID (MD5 no match)\n";
}

// Test with SHA1 (legacy)
if (sha1($password) === $user['pw']) {
    echo "✅ Password is VALID (SHA-1 match)\n";
} else {
    echo "❌ Password is INVALID (SHA-1 no match)\n";
}

echo "\n=== Conclusion ===\n";
if ($isValid) {
    echo "The password is correct. The problem is in AES encryption parameters.\n";
} else {
    echo "⚠️ The password does NOT match the hash in database!\n";
    echo "This explains why decryption fails.\n";
    echo "Please verify you're using the correct password.\n";
}

echo "\n</pre>\n";
