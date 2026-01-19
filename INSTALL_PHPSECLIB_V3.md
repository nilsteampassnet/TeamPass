# Installation de phpseclib v3 - Instructions

## Problème actuel

Votre `composer.json` spécifie phpseclib v3, mais **v1.0.24 est toujours installé**.

Vérification:
```bash
composer show phpseclib/phpseclib
# Résultat actuel: versions : * 1.0.24
```

## Solution: Mettre à jour vers v3

### Étape 1: Mettre à jour composer.lock

```bash
cd /home/user/TeamPass
composer update phpseclib/phpseclib
```

Si vous obtenez une erreur avec wpackagist.org, essayez:

```bash
# Option A: Désactiver temporairement wpackagist
composer config repo.wpackagist false
composer update phpseclib/phpseclib
composer config --unset repo.wpackagist

# Option B: Mettre à jour sans plugins
composer update phpseclib/phpseclib --no-plugins

# Option C: Update complet (recommandé)
composer update
```

### Étape 2: Régénérer l'autoloader

```bash
composer dump-autoload
```

### Étape 3: Vérifier l'installation

```bash
# Doit afficher v3.x.x
composer show phpseclib/phpseclib | grep versions

# Tester que les classes v3 sont disponibles
php -r "require 'vendor/autoload.php';
echo class_exists('phpseclib3\Crypt\AES') ? '✅ v3 disponible' : '❌ v3 manquant';
echo PHP_EOL;"
```

## Résultat attendu

Après ces étapes:
- ✅ phpseclib v3.0.x installé (la dernière version stable)
- ✅ Namespace `phpseclib3\` disponible
- ✅ CryptoManager fonctionnel avec v3
- ✅ Backward compatibility avec v1 (via fallbacks SHA-1)

## Ensuite: Tester la compatibilité

Une fois v3 installé, exécutez:

```bash
# Via navigateur
http://localhost/TeamPass/test_v3_compatibility.php

# Ou via CLI
php test_v3_compatibility.php
```

Ce test vérifiera si v3 peut décrypter vos clés privées v1 existantes.

## Aide au dépannage

### Erreur: "Class phpseclib3\Crypt\AES not found"
→ L'autoloader n'est pas à jour
```bash
composer dump-autoload
```

### Erreur: "Package phpseclib/phpseclib is locked to version 1.0.24"
→ Forcer la mise à jour
```bash
rm composer.lock
composer install
```

### Erreur réseau avec wpackagist.org
→ Désactiver temporairement (voir Option A ci-dessus)

## Résumé des changements

Le fichier `composer.json` a été mis à jour avec:

1. **Requirement v3**: `"phpseclib/phpseclib": "^3.0"`
2. **Autoload namespace**: `"phpseclib3\\": "vendor/phpseclib/phpseclib/phpseclib/"`
3. **CryptoManager autoload**: Ajouté pour faciliter les tests

**Important**: Les fichiers vendor ne sont PAS commités (bonne pratique). Vous devez exécuter `composer update` localement.
