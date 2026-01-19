<?php
/**
 * Test phpseclib v1 autoloader
 * Vérifie que les classes v1 se chargent automatiquement sans include_once explicites
 */

declare(strict_types=1);

require_once __DIR__ . '/sources/main.functions.php';

echo "=== Test Autoloader phpseclib v1 ===\n\n";

// Test 1: Vérifier que l'autoloader v1 est chargé
echo "Test 1: Vérification autoloader\n";
$autoloaders = spl_autoload_functions();
$v1AutoloaderFound = false;
foreach ($autoloaders as $autoloader) {
    if (is_array($autoloader)) {
        $info = $autoloader[0] . '::' . $autoloader[1];
    } elseif (is_string($autoloader)) {
        $info = $autoloader;
    } else {
        $info = 'Closure';
        // Check if it's our v1 autoloader (defined in phpseclibV1_autoload.php)
        $reflection = new ReflectionFunction($autoloader);
        if (strpos($reflection->getFileName(), 'phpseclibV1_autoload.php') !== false) {
            $v1AutoloaderFound = true;
            echo "✅ Autoloader v1 enregistré\n";
        }
    }
}

if (!$v1AutoloaderFound) {
    echo "⚠️  Autoloader v1 non trouvé (peut être une closure anonyme)\n";
}
echo "\n";

// Test 2: Classes se chargent automatiquement
echo "Test 2: Chargement automatique des classes v1\n";

// NE PAS faire include_once - tester l'autoload!
echo class_exists('Crypt_AES', true) ? "✅ Crypt_AES auto-chargé\n" : "❌ Crypt_AES échec autoload\n";
echo class_exists('Crypt_RSA', true) ? "✅ Crypt_RSA auto-chargé\n" : "❌ Crypt_RSA échec autoload\n";
echo class_exists('Crypt_Hash', true) ? "✅ Crypt_Hash auto-chargé\n" : "❌ Crypt_Hash échec autoload\n";
echo class_exists('Math_BigInteger', true) ? "✅ Math_BigInteger auto-chargé\n" : "❌ Math_BigInteger échec autoload\n";
echo "\n";

// Test 3: Classes avec plusieurs underscores (System_SSH_Agent)
echo "Test 3: Classes avec chemins profonds\n";
echo class_exists('System_SSH_Agent', true) ? "✅ System_SSH_Agent auto-chargé\n" : "❌ System_SSH_Agent échec autoload\n";
echo "\n";

// Test 4: Instanciation et utilisation réelle
echo "Test 4: Utilisation réelle des classes\n";
try {
    $aes = new Crypt_AES();
    echo "✅ Crypt_AES instancié\n";

    $aes->setPassword('test');
    $encrypted = $aes->encrypt('test data');
    echo "✅ Crypt_AES.encrypt() fonctionne\n";

    $decrypted = $aes->decrypt($encrypted);
    if ($decrypted === 'test data') {
        echo "✅ Crypt_AES.decrypt() fonctionne\n";
    } else {
        echo "❌ Crypt_AES.decrypt() ne retourne pas les bonnes données\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Vérifier que v3 fonctionne toujours
echo "Test 5: Coexistence avec v3\n";
echo class_exists('phpseclib3\\Crypt\\AES') ? "✅ phpseclib3\\Crypt\\AES disponible\n" : "❌ v3 non disponible\n";

try {
    $aes3 = new \phpseclib3\Crypt\AES('cbc');
    echo "✅ phpseclib3 instancié\n";
} catch (Exception $e) {
    echo "❌ Erreur v3: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== Conclusion ===\n";
echo "Si tous les tests sont ✅, l'autoloader fonctionne correctement\n";
echo "et vous pouvez retirer les include_once() de main.functions.php\n";
