# Migration Automatique v1 → v3 (Simplifiée)

## Fonctionnement

La migration de phpseclib v1 vers v3 est maintenant **toujours active** et **automatique**.

### Comportement

Lorsqu'un utilisateur visualise un mot de passe:

1. **Décryptage avec détection de version**
   - Essaie v3 (SHA-256) en premier
   - Si échec, essaie v3 avec SHA-1 (mode compatibilité v1)
   - Détecte automatiquement quelle version a été utilisée

2. **Migration automatique**
   - Si v1 a été utilisé pour décrypter
   - Ré-chiffre immédiatement avec v3 (SHA-256)
   - Met à jour `encryption_version` de 1 à 3 dans la base de données
   - L'utilisateur ne voit aucune différence

3. **Robustesse**
   - Si la migration échoue, le mot de passe est quand même affiché
   - L'erreur est loguée mais n'impacte pas l'utilisateur
   - La prochaine visualisation réessaiera la migration

## Optimisations pour Performances

### Ce qui a été supprimé

1. **Vérification du mode de migration** - Migration toujours active, pas de vérification de configuration
2. **Mise à jour des statistiques** - Fonction désactivée car elle causait des lenteurs (COUNT sur grandes tables)
3. **Logs de succès** - Seuls les échecs sont loggés

### Impact

- **Avant**: 2 requêtes SQL (UPDATE + COUNT complet de la table) + vérification config
- **Maintenant**: 1 seule requête SQL (UPDATE) sans overhead

## Vérification de la Migration

### Méthode Simple

Vérifier directement dans la base de données:

```sql
-- Compter les sharekeys par version
SELECT encryption_version, COUNT(*) as count
FROM teampass_sharekeys_items
GROUP BY encryption_version;
```

Résultat attendu:
```
encryption_version | count
-------------------+-------
                 1 |   450   ← Diminue au fil du temps
                 3 |    50   ← Augmente au fil du temps
```

### Vérifier une Migration Spécifique

```sql
-- Avant visualisation
SELECT increment_id, object_id, encryption_version
FROM teampass_sharekeys_items
WHERE user_id = VOTRE_ID AND encryption_version = 1
LIMIT 1;

-- Notez l'increment_id, puis visualisez le mot de passe dans TeamPass

-- Après visualisation
SELECT encryption_version
FROM teampass_sharekeys_items
WHERE increment_id = ID_NOTÉ;
-- Devrait maintenant être 3
```

## Fichiers Modifiés

### 1. CryptoManager.php
`includes/libraries/teampassclasses/cryptomanager/src/CryptoManager.php`

**Ajout:** Méthode `rsaDecryptWithVersionDetection()`
- Retourne les données décryptées + la version utilisée (1 ou 3)

### 2. main.functions.php
`sources/main.functions.php`

**Fonctions ajoutées:**
- `decryptUserObjectKeyWithMigration()` - Décrypte avec migration automatique
- `migrateSharekeyToV3()` - Ré-chiffre avec v3 et met à jour la BDD

**Fonction désactivée:**
- `updateMigrationStatistics()` - Commentée pour performance

### 3. items.queries.php
`sources/items.queries.php`

**Modifications dans `get_item_password`:**
- Requête SQL étendue pour récupérer `sharekey_id` et `user_public_key`
- Utilise `decryptUserObjectKeyWithMigration()` au lieu de `decryptUserObjectKey()`

## Avantages

✅ **Transparence totale** - Utilisateur ne voit aucun changement
✅ **Migration progressive** - Items accédés fréquemment migrés en premier
✅ **Aucun downtime** - Fonctionne pendant l'utilisation normale
✅ **Performance optimisée** - Overhead minimal (1 UPDATE SQL seulement)
✅ **Robuste** - Échec de migration n'empêche pas l'accès
✅ **Simple** - Aucune configuration nécessaire, toujours actif

## Dépannage

### Logs d'Erreur (si LOG_TO_SERVER activé)

Les erreurs de migration sont loguées:
```
TEAMPASS Migration Error - sharekeys_items:123 - Failed to encrypt with RSA: ...
```

**Causes possibles:**
- Clé publique corrompue ou invalide
- Problème de permissions base de données

**Solution:**
- Vérifier les logs détaillés
- L'item continuera à fonctionner en v1
- La migration sera réessayée au prochain accès

### Migration Manuelle (Si Nécessaire)

Pour forcer la migration de tous les items:

```bash
php scripts/maintenance_reencrypt_v1_to_v3.php --dry-run --verbose  # Test
php scripts/maintenance_reencrypt_v1_to_v3.php  # Migration complète
```

## Sécurité

- ✅ Aucune clé sensible n'est stockée
- ✅ Migration atomique par UPDATE SQL
- ✅ Permissions respectées (seuls les users avec accès peuvent déclencher)
- ✅ Pas d'exposition de données dans les logs

## Migration Complète

La migration sera **complète** quand:

```sql
SELECT COUNT(*) FROM teampass_sharekeys_items WHERE encryption_version = 1;
-- Retourne 0
```

Tous les items auront été migrés au fur et à mesure de leur utilisation.
