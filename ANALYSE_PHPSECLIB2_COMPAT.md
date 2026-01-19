# Analyse factuelle de phpseclib2_compat

## Ce que dit la documentation

D'après le README officiel:

> phpseclib 2.0 polyfill built with phpseclib 3.0
>
> phpseclib 3.0 breaks backwards compatability with phpseclib 2.0.
> phpseclib2_compat provides phpseclib 2.0 API on top of phpseclib 3.0

## Version du package

- **Version actuelle:** 1.0.6 (pas 2.x comme j'ai dit à tort)
- **Commande d'installation:** `composer require phpseclib/phpseclib2_compat:~1.0`

## Namespace fourni

D'après le code source:
```php
// Dans phpseclib2_compat/src/Crypt/RSA.php
namespace phpseclib\Crypt;

class RSA {
    // Implementation...
}
```

**Autoload:**
```json
{
    "autoload": {
        "psr-4": {"phpseclib\\": "src/"}
    }
}
```

## Ce que fournit phpseclib2_compat

phpseclib2_compat fournit l'**API de phpseclib 2.0** avec namespace:
- ✅ `phpseclib\Crypt\RSA` (v2 API)
- ✅ `phpseclib\Crypt\AES` (v2 API)
- ✅ `phpseclib\Net\SSH2` (v2 API)

## Ce que TeamPass utilise

TeamPass utilise l'**API de phpseclib 1.0** SANS namespace:
- ❌ `Crypt_RSA` (v1 API - classe globale)
- ❌ `Crypt_AES` (v1 API - classe globale)
- ❌ `Net_SSH2` (v1 API - classe globale)

## Conclusion factuelle

**phpseclib2_compat NE résoudra PAS notre problème** car:

1. **API incompatible**: Il fournit l'API v2 (`phpseclib\Crypt\AES`), pas v1 (`Crypt_AES`)
2. **Namespace différent**: v1 utilise des classes globales, v2 utilise des namespaces
3. **Génération ciblée**: Conçu pour migrer de v2→v3, pas v1→v3

## Vérification avec le CryptoManager

Dans le code actuel:
```php
// Ligne 273 de CryptoManager.php
if (class_exists('Crypt_AES')) {  // Cherche la classe globale v1
    $cipher = new \Crypt_AES();   // API v1
```

Avec phpseclib2_compat installé:
- `class_exists('Crypt_AES')` → **FALSE** (classe n'existe pas)
- `class_exists('phpseclib\Crypt\AES')` → **TRUE** (fournie par phpseclib2_compat)

Donc le fallback ne fonctionnera toujours pas.

## Solutions réelles possibles

### Option 1: Installer phpseclib v1 en parallèle de v3
**Problème:** Composer ne permet pas d'installer deux versions du même package

### Option 2: Adapter le fallback pour utiliser l'API v2
Modifier CryptoManager pour utiliser `phpseclib\Crypt\AES` au lieu de `Crypt_AES`

**Faisabilité:** À vérifier si l'API v2 peut déchiffrer les données v1

### Option 3: Créer notre propre wrapper v1→v3
Écrire une classe `Crypt_AES` qui wraps phpseclib3

### Option 4: Migration forcée (éliminée)
Nécessite les mots de passe de tous les utilisateurs

## Prochaine étape recommandée

Tester si l'**API v2** (fournie par phpseclib2_compat) peut déchiffrer les données **v1**.

Si oui, on peut:
1. Installer phpseclib2_compat
2. Modifier le fallback pour utiliser `phpseclib\Crypt\AES` au lieu de `Crypt_AES`
3. Tester le déchiffrement

Souhaitez-vous que je teste cette approche?
