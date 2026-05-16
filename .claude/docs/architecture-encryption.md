# Encryption Architecture

## Two-Layer Encryption Model

1. **Application-Level** (Defuse PHP Encryption)
   - Master key stored in `TEAMPASS_SECRETS/SECUREFILE`
   - Used for: session data, settings.php DB password, misc settings

2. **User-Level** (RSA via phpseclib + AES)
   - Each user has an RSA public/private key pair (generated at account creation)
   - Private key stored encrypted in `teampass_users.private_key` (AES-encrypted with user's password via `CryptoManager::aesEncrypt`)
   - Items encrypted with a random **objectKey** (AES-256-CBC)
   - Each user gets a **sharekey**: the objectKey RSA-encrypted with the user's public key
   - Decryption chain: user password → AES-decrypt private key → RSA-decrypt sharekey → objectKey → AES-decrypt item data

## Item Encryption Flow

```
Save item:
1. doDataEncryption() → generate random objectKey, AES-encrypt item data
2. storeUsersShareKey() → for each user with folder access:
   a. encryptUserObjectKey(objectKey, userPublicKey)
      → CryptoManager::rsaEncrypt() with SHA-256/OAEP (phpseclib v3)
   b. INSERT INTO teampass_sharekeys_items (object_id, user_id, share_key, encryption_version=3)

Retrieve item:
1. SELECT share_key FROM teampass_sharekeys_items WHERE object_id=? AND user_id=?
2. decryptUserObjectKey(sharekey, userPrivateKey)
   → CryptoManager::rsaDecrypt(): try SHA-256 first, fallback SHA-1 (legacy v1)
3. doDataDecryption(encryptedData, objectKey) → plaintext
```

## Sharekeys Database Tables

Identical schema, one per object type:
- `teampass_sharekeys_items` — items
- `teampass_sharekeys_fields` — custom fields
- `teampass_sharekeys_files` — attached files
- `teampass_sharekeys_logs` — log entries
- `teampass_sharekeys_suggestions` — suggestions

```sql
object_id INT, user_id INT, share_key TEXT,
encryption_version TINYINT(1)  -- 1 = phpseclib v1 (SHA-1), 3 = phpseclib v3 (SHA-256)
```

---

## phpseclib Version Management (v1 ↔ v3)

TeamPass supports two versions of phpseclib simultaneously for backward compatibility.

| | phpseclib v1 (legacy) | phpseclib v3 (current) |
|---|---|---|
| Location | `/includes/libraries/phpseclibV1/` | `/vendor/phpseclib/` (Composer) |
| RSA class | `Crypt_RSA` | `phpseclib3\Crypt\RSA` |
| RSA padding | OAEP + SHA-1 + MGF1-SHA1 | OAEP + SHA-256 + MGF1-SHA256 |
| AES class | `Crypt_AES` | `phpseclib3\Crypt\AES` |
| AES PBKDF2 hash | SHA-1 | SHA-256 |
| `encryption_version` | 1 | 3 |

**CryptoManager** (`/includes/libraries/teampassclasses/cryptomanager/src/CryptoManager.php`) is the single entry point — never call phpseclib directly:

```php
CryptoManager::rsaEncrypt(string $data, string $publicKey): string
CryptoManager::rsaDecrypt(string $data, string $privateKey, bool $tryLegacy = true): string
CryptoManager::rsaDecryptWithVersionDetection(string $data, string $privateKey): array
CryptoManager::aesEncrypt(string $data, string $key, string $mode = 'cbc', string $hash = 'sha1'): string
CryptoManager::aesDecrypt(string $data, string $key, string $mode = 'cbc', string $hash = 'sha1'): string
```

**Fallback chain:**
```
rsaDecrypt(sharekey, privateKey)
  → Try phpseclib v3 + SHA-256/OAEP → Success? Return result
  → Failure → Try phpseclib v1 + SHA-1/OAEP → Return result (or '' on total failure)
```

**Auto-migration on item access** (`decryptUserObjectKeyWithMigration()` in `sources/main.functions.php:3031`):
```
decryptUserObjectKeyWithMigration(sharekey, privateKey, objectId, userId, table)
  → rsaDecryptWithVersionDetection() → {data, version_used}
  → If version_used == 1:
      migrateSharekeyToV3()  ← re-encrypt with v3 (SHA-256)
      UPDATE sharekeys SET share_key=<new>, encryption_version=3
  → Return decrypted objectKey (transparent to caller)
```

**Key functions in `sources/main.functions.php`:**

| Function | Line | Purpose |
|---|---|---|
| `doDataEncryption()` | ~2887 | AES-encrypt item data, generate random objectKey |
| `doDataDecryption()` | ~2913 | AES-decrypt item data with objectKey |
| `encryptUserObjectKey()` | ~2949 | RSA-encrypt objectKey with user public key |
| `decryptUserObjectKey()` | ~2982 | RSA-decrypt sharekey (v3 + v1 fallback) |
| `decryptUserObjectKeyWithMigration()` | ~3031 | Decrypt + auto-migrate v1→v3 |
| `migrateSharekeyToV3()` | ~3101 | Re-encrypt a single sharekey to v3 |
| `storeUsersShareKey()` | ~3307 | Create/update sharekeys for all eligible users |
| `insertOrUpdateSharekey()` | ~3402 | Upsert a single sharekey row in DB |

**Forced batch migration** (background tasks via `/scripts/traits/PhpseclibV3MigrationTrait.php`):
- Migrates all v1 sharekeys for a user in batches of 100
- Triggered when `teampass_users.phpseclibv3_migration_completed = 0`
- Diagnostic/repair: `/scripts/repair_phpseclib_migration.php`

**Rule: always use `decryptUserObjectKeyWithMigration()` in new code** — never call `rsaDecrypt()` directly for sharekeys.

**Rule: applies to custom field sharekeys too** — SELECT must include `increment_id` so the migration function can update the row in place.

---

## Custom Fields Encryption

Custom fields (`teampass_categories`) can be individually encrypted (`encrypted_data = 1`).

**Tables:**
- `teampass_categories` — field definitions (`encrypted_data` flag, `type`, `masked`, etc.)
- `teampass_categories_items` — field values per item (`data`, `encryption_type`, `field_id`, `item_id`)
- `teampass_sharekeys_fields` — per-user sharekeys; `object_id` references `categories_items.id` (not `categories.id`)

**`encryption_type` in `teampass_categories_items`:**
- `'not_set'` — plaintext
- `TP_ENCRYPTION_NAME` — AES-encrypted; a sharekey must exist in `sharekeys_fields`

**Encryption flow (save/update):**
```
1. Encrypt before INSERT:
   doDataEncryption($plaintext) → {encrypted, objectKey}
   INSERT categories_items (data=encrypted, encryption_type=TP_ENCRYPTION_NAME)   ← atomic
   $newId = DB::insertId()
2. storeUsersShareKey('sharekeys_fields', ..., $newId, objectKey)
```

**Rule: always encrypt before INSERT** — never insert plaintext and update afterwards.

**Decryption flow (read):**
```php
$userKey = DB::queryFirstRow(
    'SELECT share_key, increment_id FROM teampass_sharekeys_fields
     WHERE user_id = %i AND object_id = %i',
    $userId, $categoriesItemsId
);
$objectKey = decryptUserObjectKeyWithMigration(
    $userKey['share_key'], $privateKey, $publicKey,
    intval($userKey['increment_id']), 'sharekeys_fields'
);
$plaintext = doDataDecryption($row['data'], $objectKey);
```

**Move item: personal folder → public folder**

For custom fields, the loop must:
1. Skip fields with `encryption_type = 'not_set'`
2. If the moving user has no sharekey, the object key is **unrecoverable** — delete the `categories_items` row and log

**Orphaned sharekey invariant:** a `categories_items` row with `encryption_type = TP_ENCRYPTION_NAME` must always have at least one corresponding row in `sharekeys_fields`. If violated, the encrypted value cannot be recovered — delete the field row.
