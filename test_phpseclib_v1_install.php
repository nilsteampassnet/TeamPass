<?php
/**
 * Test phpseclib v1 manual installation
 * Verifies that v1 classes are accessible via include_path
 */

declare(strict_types=1);

require_once __DIR__ . '/sources/main.functions.php';

echo "=== Test Installation phpseclib v1 Manuelle ===\n\n";

// Test 1: Vérifier que v1 est dans l'include_path
echo "Test 1: Include Path\n";
$includePaths = explode(PATH_SEPARATOR, get_include_path());
$phpseclibV1Found = false;
foreach ($includePaths as $path) {
    if (strpos($path, 'phpseclibV1') !== false) {
        echo "✅ phpseclibV1 trouvé dans include_path: $path\n";
        $phpseclibV1Found = true;
        break;
    }
}
if (!$phpseclibV1Found) {
    echo "❌ phpseclibV1 NOT dans include_path\n";
}
echo "\n";

// Test 2: Vérifier que les classes v1 existent
echo "Test 2: Classes phpseclib v1\n";
echo class_exists('Crypt_AES', true) ? "✅ Crypt_AES disponible\n" : "❌ Crypt_AES manquant\n";
echo class_exists('Crypt_RSA', true) ? "✅ Crypt_RSA disponible\n" : "❌ Crypt_RSA manquant\n";
echo "\n";

// Test 3: Vérifier que v3 est toujours disponible
echo "Test 3: Classes phpseclib v3\n";
echo class_exists('phpseclib3\\Crypt\\AES') ? "✅ phpseclib3\\Crypt\\AES disponible\n" : "❌ phpseclib3\\Crypt\\AES manquant\n";
echo class_exists('phpseclib3\\Crypt\\RSA') ? "✅ phpseclib3\\Crypt\\RSA disponible\n" : "❌ phpseclib3\\Crypt\\RSA manquant\n";
echo "\n";

// Test 4: Test basique de chiffrement/déchiffrement v1
echo "Test 4: AES v1 - Chiffrement/Déchiffrement basique\n";
try {
    $cipher = new Crypt_AES();
    $cipher->setPassword('TestPassword123');

    $plaintext = "Test data for v1";
    $encrypted = $cipher->encrypt($plaintext);
    echo "✅ Chiffrement v1 réussi (" . strlen($encrypted) . " bytes)\n";

    $cipher2 = new Crypt_AES();
    $cipher2->setPassword('TestPassword123');
    $decrypted = $cipher2->decrypt($encrypted);

    if ($decrypted === $plaintext) {
        echo "✅ Déchiffrement v1 réussi - données identiques!\n";
    } else {
        echo "❌ Déchiffrement v1 échoué - données différentes\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur v1: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Test basique de chiffrement/déchiffrement v3
echo "Test 5: AES v3 - Chiffrement/Déchiffrement basique\n";
try {
    $cipher = new \phpseclib3\Crypt\AES('cbc');
    $cipher->setIV(str_repeat("\0", 16));
    $cipher->setPassword('TestPassword123', 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);

    $plaintext = "Test data for v3";
    $encrypted = $cipher->encrypt($plaintext);
    echo "✅ Chiffrement v3 réussi (" . strlen($encrypted) . " bytes)\n";

    $cipher2 = new \phpseclib3\Crypt\AES('cbc');
    $cipher2->setIV(str_repeat("\0", 16));
    $cipher2->setPassword('TestPassword123', 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
    $decrypted = $cipher2->decrypt($encrypted);

    if ($decrypted === $plaintext) {
        echo "✅ Déchiffrement v3 réussi - données identiques!\n";
    } else {
        echo "❌ Déchiffrement v3 échoué - données différentes\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur v3: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Test cross-version - v1 encrypt, v3 decrypt
echo "Test 6: Cross-version - v1 encrypt, v3 decrypt\n";
try {
    $password = 'CrossVersionTest';
    $plaintext = "Data encrypted with v1";

    // Encrypt with v1
    $cipherV1 = new Crypt_AES();
    $cipherV1->setPassword($password);
    $encryptedV1 = $cipherV1->encrypt($plaintext);
    echo "✅ Chiffré avec v1\n";

    // Try to decrypt with v3
    $cipherV3 = new \phpseclib3\Crypt\AES('cbc');
    $cipherV3->setIV(str_repeat("\0", 16));
    $cipherV3->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
    $decryptedV3 = $cipherV3->decrypt($encryptedV1);

    if ($decryptedV3 === $plaintext) {
        echo "✅ v3 peut déchiffrer les données v1!\n";
    } else {
        echo "❌ v3 NE PEUT PAS déchiffrer les données v1 (données différentes)\n";
    }
} catch (Exception $e) {
    echo "❌ v3 NE PEUT PAS déchiffrer les données v1: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Test cross-version - v3 encrypt, v1 decrypt
echo "Test 7: Cross-version - v3 encrypt, v1 decrypt\n";
try {
    $password = 'CrossVersionTest';
    $plaintext = "Data encrypted with v3";

    // Encrypt with v3
    $cipherV3 = new \phpseclib3\Crypt\AES('cbc');
    $cipherV3->setIV(str_repeat("\0", 16));
    $cipherV3->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
    $encryptedV3 = $cipherV3->encrypt($plaintext);
    echo "✅ Chiffré avec v3\n";

    // Try to decrypt with v1
    $cipherV1 = new Crypt_AES();
    $cipherV1->setPassword($password);
    $decryptedV1 = $cipherV1->decrypt($encryptedV3);

    if ($decryptedV1 === $plaintext) {
        echo "✅ v1 peut déchiffrer les données v3!\n";
    } else {
        echo "❌ v1 NE PEUT PAS déchiffrer les données v3 (données différentes)\n";
    }
} catch (Exception $e) {
    echo "❌ v1 NE PEUT PAS déchiffrer les données v3: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== Fin des tests ===\n";
