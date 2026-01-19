# Installation de phpseclib2_compat

## Pourquoi ce package ?

Le test a révélé que phpseclib v3 **ne peut pas** déchiffrer les données chiffrées avec v1, même avec les paramètres de compatibilité (SHA-1, PBKDF2, etc.).

**Problème identifié:**
- Le CryptoManager tentait un fallback vers v1 avec `class_exists('Crypt_AES')`
- Mais cette classe n'existe pas car seule v3 est installée
- Le fallback ne fonctionnait donc jamais

**Solution:**
`phpseclib/phpseclib2_compat` est un package officiel qui fournit les classes v1/v2 (`Crypt_AES`, `Crypt_RSA`, etc.) **implémentées au-dessus de v3**.

Cela permet:
- ✅ Utiliser l'API v1 pour déchiffrer les anciennes données
- ✅ L'implémentation v1 est fournie par v3 (pas de v1 réellement installée)
- ✅ Le fallback dans CryptoManager fonctionnera automatiquement

## Installation

```bash
cd /home/user/TeamPass

# Installer phpseclib2_compat
composer require phpseclib/phpseclib2_compat:^2.0

# Régénérer l'autoloader
composer dump-autoload
```

### En cas d'erreur réseau (wpackagist)

Si vous obtenez l'erreur "curl error 56... wpackagist.org":

```bash
# Option 1: Désactiver temporairement wpackagist
composer config repo.wpackagist false
composer require phpseclib/phpseclib2_compat:^2.0
composer config --unset repo.wpackagist

# Option 2: Sans plugins
composer require phpseclib/phpseclib2_compat:^2.0 --no-plugins
```

## Vérification

Après installation, vérifiez que les classes v1 sont disponibles:

```bash
php -r "
require 'vendor/autoload.php';
echo class_exists('Crypt_AES') ? '✅ Crypt_AES disponible' : '❌ Crypt_AES manquant';
echo PHP_EOL;
echo class_exists('phpseclib3\Crypt\AES') ? '✅ phpseclib v3 disponible' : '❌ v3 manquant';
echo PHP_EOL;
"
```

**Résultat attendu:**
```
✅ Crypt_AES disponible
✅ phpseclib v3 disponible
```

## Fonctionnement du fallback

Avec `phpseclib2_compat` installé, le CryptoManager fonctionnera ainsi:

```php
// 1. Essaie v3 avec paramètres v1 (SHA-1, PBKDF2)
try {
    $cipher = new \phpseclib3\Crypt\AES('cbc');
    return $cipher->decrypt($data);  // ❌ Échoue avec padding error
} catch (Exception $e) {

    // 2. Fallback vers v1 (maintenant disponible via phpseclib2_compat)
    if (class_exists('Crypt_AES')) {  // ✅ Maintenant TRUE!
        $cipher = new \Crypt_AES();
        return $cipher->decrypt($data);  // ✅ Réussit!
    }
}
```

## Retester après installation

Une fois `phpseclib2_compat` installé:

```bash
# Via navigateur
http://localhost/TeamPass/test_v3_compatibility.php

# Résultat attendu:
# ✅ Password is VALID
# ✅ Decryption succeeded! (via v1 fallback)
# ✅✅✅ SUCCESS! Valid PEM private key!
```

## Pourquoi phpseclib2_compat fonctionne

Ce package:
1. **Fournit l'API v1/v2** (`Crypt_AES`, `Crypt_RSA`, etc.)
2. **Implémenté au-dessus de v3** (utilise v3 en interne)
3. **Gère les différences** de comportement entre v1 et v3
4. **Officiel** - Maintenu par les auteurs de phpseclib

C'est exactement le cas d'usage pour lequel ce package a été créé: permettre au code écrit pour v1/v2 de fonctionner avec v3.

## Alternative testée et rejetée

Nous avons d'abord essayé de configurer v3 avec les mêmes paramètres que v1:
- SHA-1 pour le hash
- PBKDF2 pour la dérivation de clé
- Zero IV pour CBC
- Salt 'phpseclib/salt'
- 1000 itérations

**Résultat:** Même avec ces paramètres identiques, v3 échoue à déchiffrer les données v1.

**Erreurs observées:**
- "invalid padding length (129) compared to block size (16)"
- "ciphertext length (1514) needs to be a multiple of block size (16)"

Cela indique des différences plus profondes dans l'implémentation AES entre v1 et v3.

## Conclusion

`phpseclib2_compat` est la **solution officielle et recommandée** pour:
- Maintenir la compatibilité avec les données v1
- Utiliser v3 pour les nouvelles opérations
- Migrer progressivement sans re-chiffrement forcé

Le commit est déjà prêt dans `composer.json`. Il ne reste qu'à exécuter `composer require` pour installer le package.
