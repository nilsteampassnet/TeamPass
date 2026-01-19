<?php
/**
 * Test simple de l'autoloader phpseclib v1
 * Ne dépend pas de main.functions.php
 */

declare(strict_types=1);

// Charger uniquement composer autoload et l'autoloader v1
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/libraries/phpseclibV1_autoload.php';

echo "=== Test Autoloader phpseclib v1 (Simple) ===\n\n";

// Test 1: Vérifier que les classes v1 sont disponibles
echo "Test 1: Disponibilité des classes v1\n";
echo class_exists('Crypt_AES', true) ? "✅ Crypt_AES disponible\n" : "❌ Crypt_AES manquant\n";
echo class_exists('Crypt_RSA', true) ? "✅ Crypt_RSA disponible\n" : "❌ Crypt_RSA manquant\n";
echo class_exists('Math_BigInteger', true) ? "✅ Math_BigInteger disponible\n" : "❌ Math_BigInteger manquant\n";
echo "\n";

// Test 2: Vérifier que v3 fonctionne toujours
echo "Test 2: Coexistence avec v3\n";
echo class_exists('phpseclib3\\Crypt\\AES') ? "✅ phpseclib3\\Crypt\\AES disponible\n" : "❌ v3 manquant\n";
echo class_exists('phpseclib3\\Crypt\\RSA') ? "✅ phpseclib3\\Crypt\\RSA disponible\n" : "❌ v3 manquant\n";
echo "\n";

// Test 3: Test fonctionnel AES v1
echo "Test 3: Test fonctionnel AES v1\n";
try {
    $cipher = new Crypt_AES();
    $cipher->setPassword('TestPassword');

    $plaintext = "Hello from v1";
    $encrypted = $cipher->encrypt($plaintext);
    echo "✅ Chiffrement v1: " . strlen($encrypted) . " bytes\n";

    $cipher2 = new Crypt_AES();
    $cipher2->setPassword('TestPassword');
    $decrypted = $cipher2->decrypt($encrypted);

    if ($decrypted === $plaintext) {
        echo "✅ Déchiffrement v1: Données correctes!\n";
    } else {
        echo "❌ Déchiffrement v1: Données incorrectes\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur v1: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Test fonctionnel AES v3
echo "Test 4: Test fonctionnel AES v3\n";
try {
    $cipher = new \phpseclib3\Crypt\AES('cbc');
    $cipher->setIV(str_repeat("\0", 16));
    $cipher->setPassword('TestPassword', 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);

    $plaintext = "Hello from v3";
    $encrypted = $cipher->encrypt($plaintext);
    echo "✅ Chiffrement v3: " . strlen($encrypted) . " bytes\n";

    $cipher2 = new \phpseclib3\Crypt\AES('cbc');
    $cipher2->setIV(str_repeat("\0", 16));
    $cipher2->setPassword('TestPassword', 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
    $decrypted = $cipher2->decrypt($encrypted);

    if ($decrypted === $plaintext) {
        echo "✅ Déchiffrement v3: Données correctes!\n";
    } else {
        echo "❌ Déchiffrement v3: Données incorrectes\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur v3: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Vérifier que le chemin de l'autoloader est correct
echo "Test 5: Diagnostic autoloader\n";
$testClass = 'Crypt_AES';
$expectedPath = __DIR__ . '/includes/libraries/phpseclibV1/Crypt/AES.php';
echo "Classe recherchée: $testClass\n";
echo "Chemin attendu: $expectedPath\n";
echo "Fichier existe? " . (file_exists($expectedPath) ? "✅ OUI" : "❌ NON") . "\n";

if (!class_exists('Crypt_AES', false)) {
    echo "⚠️  Classe pas encore chargée, tentative de chargement...\n";
    class_exists('Crypt_AES', true);
    echo class_exists('Crypt_AES', false) ? "✅ Chargée après autoload\n" : "❌ Échec autoload\n";
}
echo "\n";

echo "=== Résumé ===\n";
if (class_exists('Crypt_AES') && class_exists('phpseclib3\\Crypt\\AES')) {
    echo "🎉 SUCCESS! v1 et v3 fonctionnent ensemble!\n";
} else {
    echo "❌ Problème détecté\n";
    if (!class_exists('Crypt_AES')) {
        echo "  - v1 ne se charge pas\n";
    }
    if (!class_exists('phpseclib3\\Crypt\\AES')) {
        echo "  - v3 n'est pas disponible\n";
    }
}
