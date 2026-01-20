# Analyse : Migration des clés utilisateur (phpseclib v1 → v3)

**Date** : 2026-01-20
**Auteur** : Claude
**Contexte** : Suite à l'observation de l'utilisateur concernant les clés privées

---

## 1. Résumé Exécutif

**Constat** : ❌ Les clés utilisateur (`private_key`, `private_key_backup`, `user_derivation_seed`, `key_integrity_hash`) ne sont PAS migrées de v1 à v3.

**Impact** :
- Les clés privées restent chiffrées en v1 (SHA-1) indéfiniment
- La colonne `encryption_version` dans la table `users` n'est jamais mise à jour (reste à 1)
- Contrairement aux sharekeys (items, fields, files) qui migrent automatiquement

**Couverture actuelle de la migration** :
- ✅ **sharekeys_items** : Migration automatique implémentée (~80% des accès)
- ✅ **sharekeys_fields** : Migration automatique implémentée (~15% des accès)
- ✅ **sharekeys_files** : Migration automatique implémentée (~3% des accès)
- ❌ **Clés utilisateur (table users)** : AUCUNE migration automatique

---

## 2. Champs concernés dans la table `users`

| Champ | Description | Chiffrement | État migration |
|-------|-------------|-------------|----------------|
| **private_key** | Clé privée RSA de l'utilisateur | AES-256 (PBKDF2 + mot de passe user) | ❌ Non migré |
| **public_key** | Clé publique RSA | Non chiffré | N/A |
| **user_derivation_seed** | Seed pour transparent recovery | Stocké en clair (?) | ❓ À vérifier |
| **private_key_backup** | Backup de la clé privée | AES-256 (PBKDF2 + clé dérivée du seed) | ❌ Non migré |
| **key_integrity_hash** | Hash d'intégrité des clés | Hash (pas de chiffrement) | N/A |
| **encryption_version** | Version de chiffrement utilisée | 1=v1 (SHA-1), 3=v3 (SHA-256) | ❌ Jamais mis à jour |

---

## 3. Analyse du code actuel

### 3.1 Décryptage de la clé privée (identify.php)

**Fonction** : `handleUserKeyDecryption()` (ligne ~1197)

```php
// Try to uncrypt private key with current password
try {
    $privateKeyClear = decryptPrivateKey($passwordClear, $userInfo['private_key']);

    // If user has seed but no backup, create it on first successful login
    if (!empty($userInfo['user_derivation_seed']) && empty($userInfo['private_key_backup'])) {
        // ... creates backup ...
        $privateKeyBackup = base64_encode(
            \TeampassClasses\CryptoManager\CryptoManager::aesEncrypt(
                base64_decode($privateKeyClear),
                $derivedKey
            )
        );
    }

    return [
        'public_key' => $userInfo['public_key'],
        'private_key_clear' => $privateKeyClear,
        'update_keys_in_db' => [],  // ❌ Aucune mise à jour !
    ];
```

**Problème** :
- La clé privée est décryptée avec `decryptPrivateKey()` qui utilise `CryptoManager::aesDecrypt()` (avec fallback v1/v3)
- Mais elle n'est **jamais ré-encryptée en v3**
- `encryption_version` n'est **jamais mis à jour**

### 3.2 Fonction decryptPrivateKey (main.functions.php)

**Code actuel** (ligne 2228) :

```php
function decryptPrivateKey(string $userPwd, string $userPrivateKey)
{
    // Sanitize
    $antiXss = new AntiXSS();
    $userPwd = $antiXss->xss_clean($userPwd);
    $userPrivateKey = $antiXss->xss_clean($userPrivateKey);

    if (empty($userPwd) === false) {
        try {
            // Decrypt using CryptoManager (phpseclib v3)
            $decrypted = \TeampassClasses\CryptoManager\CryptoManager::aesDecrypt(
                base64_decode($userPrivateKey),
                $userPwd
            );
            return base64_encode((string) $decrypted);
        } catch (Exception $e) {
            // Log error for debugging
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS Error - decryptPrivateKey failed: ' . $e->getMessage());
            }
            // Return empty string on decryption failure
            return '';
        }
    }
    return '';
}
```

**Analyse** :
- ✅ Utilise `CryptoManager::aesDecrypt()` qui a le fallback v1→v3
- ❌ Pas de détection de version utilisée (contrairement à `rsaDecryptWithVersionDetection()`)
- ❌ Pas de migration automatique

### 3.3 CryptoManager::aesDecrypt (CryptoManager.php)

**Code actuel** :

```php
public static function aesDecrypt(string $data, string $password): string
{
    try {
        // Try with phpseclib v3 first (default)
        if (class_exists('phpseclib3\\Crypt\\AES')) {
            $cipher = new \phpseclib3\Crypt\AES('cbc');
            $cipher->setPassword($password, 'pbkdf2', 'sha256', 'phpseclib/salt', 1000);
            $decrypted = $cipher->decrypt($data);

            if ($decrypted !== false && !empty($decrypted)) {
                return $decrypted;
            }
        }

        // Fallback to SHA-1 (v1 compatibility)
        if (class_exists('phpseclib3\\Crypt\\AES')) {
            $cipher = new \phpseclib3\Crypt\AES('cbc');
            $cipher->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
            $decrypted = $cipher->decrypt($data);

            if ($decrypted !== false && !empty($decrypted)) {
                return $decrypted;  // ❌ Pas d'information sur la version utilisée !
            }
        }

        // ... fallback v1 library ...
    } catch (Exception $e) {
        throw new Exception('AES decryption failed: ' . $e->getMessage());
    }
}
```

**Problème** :
- La fonction essaie SHA-256 puis SHA-1
- Mais ne retourne **pas** quelle version a fonctionné
- Contrairement à `rsaDecryptWithVersionDetection()` qui retourne `['data' => ..., 'version_used' => ...]`

---

## 4. Points d'accès aux clés privées

### 4.1 Login (identify.php)

**Fréquence** : Chaque connexion utilisateur

**Code** (ligne ~1197) :
```php
$privateKeyClear = decryptPrivateKey($passwordClear, $userInfo['private_key']);
```

**Opportunité** : ✅ **Point idéal pour migration automatique**
- L'utilisateur fournit son mot de passe
- Mot de passe disponible en clair
- Peut ré-encrypter avec v3 si v1 détecté

### 4.2 Changement de mot de passe (users.queries.php)

**Fréquence** : Occasionnelle

**Opportunité** : ✅ **Point idéal pour forcer v3**
- Nouveau mot de passe fourni
- Peut forcer re-encryption en v3

### 4.3 Migration sanitized password (identify.php)

**Fréquence** : Une fois par utilisateur (migration legacy)

**Code existant** (ligne 2173) :
```php
$userCurrentPrivateKey = decryptPrivateKey($passwordSanitized, $userInfo['private_key']);
$newUserPrivateKey = encryptPrivateKey($passwordClear, $userCurrentPrivateKey);

// Update user with new hash and mark migration as COMPLETE (0 = done)
DB::update(
    prefixTable('users'),
    [
        'pw' => $newHash,
        'needs_password_migration' => 0,
        'private_key' => $newUserPrivateKey,  // ❌ Mais pas encryption_version !
    ],
    'id = %i',
    $userInfo['id']
);
```

**Problème** :
- Ré-encrypte déjà la clé privée
- Mais n'utilise PAS la détection de version
- Et ne met PAS à jour `encryption_version`

---

## 5. Comparaison avec la migration des sharekeys

### Sharekeys (IMPLÉMENTÉ ✅)

```php
function decryptUserObjectKeyWithMigration(
    string $encryptedKey,
    string $privateKey,
    string $publicKey,
    int $sharekeyId,
    string $sharekeyTable
): string {
    // Decrypt with version detection
    $result = \TeampassClasses\CryptoManager\CryptoManager::rsaDecryptWithVersionDetection(
        $decodedKey,
        $privateKey
    );

    $decryptedKey = $result['data'];
    $versionUsed = $result['version_used'];

    // Automatic migration: if v1 was used, re-encrypt with v3
    if ($versionUsed === 1) {
        try {
            migrateSharekeyToV3(
                $sharekeyId,
                $sharekeyTable,
                $decryptedKey,
                $publicKey
            );
        } catch (Exception $migrationError) {
            // Log but don't fail
        }
    }

    return base64_encode($decryptedKey);
}
```

### Clés utilisateur (NON IMPLÉMENTÉ ❌)

**Ce qui manque** :
1. `aesDecryptWithVersionDetection()` - Équivalent de `rsaDecryptWithVersionDetection()` pour AES
2. `decryptPrivateKeyWithMigration()` - Équivalent de `decryptUserObjectKeyWithMigration()`
3. `migrateUserKeysToV3()` - Fonction pour migrer les clés utilisateur
4. Mise à jour de `encryption_version` dans la table `users`

---

## 6. Recommandations

### 6.1 Solution recommandée : Migration automatique au login

**Stratégie** :
1. Créer `aesDecryptWithVersionDetection()` dans CryptoManager.php
2. Créer `decryptPrivateKeyWithMigration()` dans main.functions.php
3. Modifier `handleUserKeyDecryption()` dans identify.php pour utiliser la nouvelle fonction
4. Migrer automatiquement lors de chaque login réussi

**Avantages** :
- Cohérent avec la migration des sharekeys
- Transparent pour l'utilisateur
- Migration progressive (utilisateurs actifs migrés en premier)
- Pas de script batch nécessaire

**Champs à migrer** :
1. **private_key** : Priorité HAUTE (utilisé à chaque login)
2. **private_key_backup** : Priorité MOYENNE (utilisé en cas de recovery)
3. **encryption_version** : Mise à jour obligatoire

### 6.2 Points d'attention

**user_derivation_seed** :
- À vérifier : est-il chiffré ou en clair ?
- Si chiffré, quelle méthode ?

**key_integrity_hash** :
- Hash, pas de chiffrement
- Pas de migration nécessaire

**private_key_backup** :
- Chiffré avec une clé dérivée du seed
- Nécessite migration si la dérivation utilise PBKDF2 SHA-1

### 6.3 Impact performance

**Par login utilisateur** :
- Overhead : ~5-10ms (une seule fois lors de la migration)
- 2 UPDATE en base : `private_key` + `encryption_version`
- Ensuite : 0ms overhead (clé en v3)

**Couverture** :
- Utilisateurs actifs : Migrés en quelques jours/semaines
- Utilisateurs inactifs : Restent en v1 (fonctionnent toujours)

---

## 7. Prochaines étapes recommandées

### Étape 1 : Vérification du chiffrement de user_derivation_seed
```bash
# Vérifier si le seed est stocké chiffré ou en clair
# Examiner le code de création du seed
```

### Étape 2 : Créer aesDecryptWithVersionDetection()
```php
// CryptoManager.php
public static function aesDecryptWithVersionDetection(string $data, string $password): array
{
    // Try SHA-256 (v3)
    try {
        $decrypted = self::aesDecrypt_v3($data, $password);
        return ['data' => $decrypted, 'version_used' => 3];
    } catch (Exception $e) {
        // Try SHA-1 (v1)
        $decrypted = self::aesDecrypt_v1($data, $password);
        return ['data' => $decrypted, 'version_used' => 1];
    }
}
```

### Étape 3 : Créer decryptPrivateKeyWithMigration()
```php
// main.functions.php
function decryptPrivateKeyWithMigration(
    string $userPwd,
    string $userPrivateKey,
    int $userId
): string {
    // Decrypt with version detection
    $result = CryptoManager::aesDecryptWithVersionDetection(
        base64_decode($userPrivateKey),
        $userPwd
    );

    // If v1, migrate to v3
    if ($result['version_used'] === 1) {
        migrateUserPrivateKeyToV3($userId, $userPwd, $result['data']);
    }

    return base64_encode($result['data']);
}
```

### Étape 4 : Modifier handleUserKeyDecryption()
```php
// identify.php
$privateKeyClear = decryptPrivateKeyWithMigration(
    $passwordClear,
    $userInfo['private_key'],
    (int) $userInfo['id']
);
```

---

## 8. Réponses aux questions (analyse complémentaire)

### 8.1 user_derivation_seed

**Stockage** : ✅ **En clair** (pas de chiffrement)
- Généré avec : `bin2hex(openssl_random_pseudo_bytes(32))`
- Stocké directement en base de données
- **Pas de migration nécessaire**

**Source** : `main.functions.php` ligne 2201

### 8.2 private_key_backup

**Chiffrement actuel** : ⚠️ **DOUBLE PBKDF2** (complexe)

**Étape 1 - Dérivation de la clé de backup** (`deriveBackupKey()`) :
```php
// PBKDF2 avec SHA-256 (ligne 2311-2316)
return hash_pbkdf2(
    'sha256',           // ✅ SHA-256
    hex2bin($userSeed),
    $salt,              // hash SHA-256 de la clé publique
    100000,             // 100k iterations
    32,
    true
);
```

**Étape 2 - Chiffrement de la clé privée** (`aesEncrypt()`) :
```php
// CryptoManager::aesEncrypt() - UTILISE TOUJOURS SHA-1 (ligne 204)
$cipher->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
                                           // ❌ SHA-1 !
```

**Conclusion** :
- La clé dérivée (input) utilise **SHA-256**
- Mais le chiffrement AES final utilise **SHA-1** (v1)
- **Migration nécessaire** : Oui, pour le chiffrement AES

### 8.3 CryptoManager::aesEncrypt() - Problème architectural

**Code actuel** (ligne 204) :
```php
// TOUJOURS SHA-1 pour compatibilité v1
$cipher->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);
```

**Problème** :
- `aesEncrypt()` utilise **toujours SHA-1** même avec phpseclib v3
- Conçu pour compatibilité v1
- Mais empêche migration vers v3 !

**Solution requise** :
- Créer `aesEncrypt_v3()` qui utilise SHA-256
- Ou ajouter paramètre `$version = 1` à `aesEncrypt()`

### 8.4 Priorités de migration

**PRIORITÉ 1 - HAUTE** : `private_key`
- ✅ Utilisé à chaque login
- ✅ Impact performance important
- ✅ Point d'accès : `handleUserKeyDecryption()` dans identify.php

**PRIORITÉ 2 - MOYENNE** : `private_key_backup`
- 🔶 Utilisé en cas de transparent recovery (rare)
- 🔶 Impact performance faible
- 🔶 Point d'accès : `attemptTransparentRecovery()` dans main.functions.php

**PRIORITÉ 3 - BASSE** : `encryption_version`
- ✅ Doit être mis à jour avec private_key
- ✅ Permet statistiques de migration

### 8.5 Changement de mot de passe

**Opportunité** : ✅ **Excellent moment pour forcer v3**

**Fichier** : `users.queries.php` (à vérifier)

**Logique** :
```php
// Lors du changement de mot de passe
// 1. Décrypter private_key avec ancien mot de passe
// 2. Ré-encrypter avec NOUVEAU mot de passe ET v3 (SHA-256)
// 3. Forcer encryption_version = 3
```

---

## 9. Problème architectural découvert : aesEncrypt() toujours en SHA-1

**Découverte critique** : `CryptoManager::aesEncrypt()` utilise **toujours SHA-1**, même avec phpseclib v3.

**Impact** :
- ❌ Toutes les nouvelles clés privées créées utilisent SHA-1
- ❌ Impossible de créer des clés v3 actuellement
- ❌ Migration inutile si re-encryption utilise SHA-1

**Fichiers impactés** :
- `private_key` : Chiffré avec SHA-1
- `private_key_backup` : Chiffré avec SHA-1 (après dérivation SHA-256)
- Tous les nouveaux utilisateurs créés

**Solution requise AVANT migration** :
1. Modifier `CryptoManager::aesEncrypt()` pour supporter v3 (SHA-256)
2. Ou créer `aesEncrypt_v3()` et `aesDecrypt_v3()`
3. Mettre à jour `encryptPrivateKey()` pour utiliser v3

---

## Conclusion

### État actuel

**Sharekeys** : ✅ Migration automatique implémentée
- sharekeys_items (~80%)
- sharekeys_fields (~15%)
- sharekeys_files (~3%)
- **Couverture : ~98%**

**Clés utilisateur** : ❌ Aucune migration
- private_key : Reste en v1 SHA-1
- private_key_backup : Reste en v1 SHA-1
- encryption_version : Jamais mis à jour
- **Couverture : 0%**

**Problème bloquant** : ⚠️ CryptoManager::aesEncrypt() utilise toujours SHA-1
- Empêche création de nouvelles clés v3
- Migration inutile sans correction de ce problème

### Actions requises (ordre de priorité)

#### ÉTAPE 1 - CRITIQUE : Corriger CryptoManager::aesEncrypt()
**Sans cette étape, la migration est impossible**

Options :
- **Option A** : Ajouter paramètre `$hashAlgorithm = 'sha1'` à `aesEncrypt()`/`aesDecrypt()`
- **Option B** : Créer `aesEncrypt_v3()` et `aesDecrypt_v3()` séparés

#### ÉTAPE 2 : Créer aesDecryptWithVersionDetection()
```php
public static function aesDecryptWithVersionDetection(string $data, string $password): array
{
    // Try SHA-256 first (v3)
    // Fallback to SHA-1 (v1)
    // Return ['data' => ..., 'version_used' => 1|3]
}
```

#### ÉTAPE 3 : Créer decryptPrivateKeyWithMigration()
```php
function decryptPrivateKeyWithMigration(
    string $userPwd,
    string $userPrivateKey,
    int $userId
): string {
    // Decrypt with version detection
    // If v1 detected → re-encrypt with v3 + update encryption_version
    // Return decrypted key
}
```

#### ÉTAPE 4 : Modifier handleUserKeyDecryption() dans identify.php
```php
$privateKeyClear = decryptPrivateKeyWithMigration(
    $passwordClear,
    $userInfo['private_key'],
    (int) $userInfo['id']
);
```

#### ÉTAPE 5 (OPTIONNEL) : Migrer private_key_backup
Lors de transparent recovery ou création de backup

### Impact utilisateur

- ✅ **Migration transparente** au login
- ✅ **Aucune action requise** de l'utilisateur
- ✅ **Performance** : ~5-10ms une fois par utilisateur
- ✅ **Compatible** : Clés v1 fonctionnent toujours

### Recommandation finale

**Il faut d'abord corriger CryptoManager::aesEncrypt()** avant d'implémenter la migration des clés utilisateur. Sans cela, on ne fait que ré-encrypter en SHA-1, ce qui est inutile.

**Proposition** : Discuter avec l'utilisateur pour choisir l'approche (Option A ou B) avant de continuer.
