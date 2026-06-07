# Encryption Architecture

> Last updated: 2026-05-26 — Analysis in `workReadmeFiles/encryption-analysis.md`

## Two-Layer Encryption Model

1. **Application-Level** (Defuse PHP Encryption)
   - Master key stored in `TEAMPASS_SECRETS/SECUREFILE`
   - Used for: session data, settings.php DB password, misc settings, password history (`log_items.old_value`)

2. **User-Level** (RSA via phpseclib + AES)
   - Each user has an RSA public/private key pair (generated at account creation)
   - Private key stored encrypted in `teampass_users.private_key` (AES-encrypted with user's password via `CryptoManager::aesEncrypt`)
   - Items encrypted with a random **objectKey** (AES-256-CBC)
   - Each user gets a **sharekey**: the objectKey RSA-encrypted with the user's public key
   - Decryption chain: user password → AES-decrypt private key → RSA-decrypt sharekey → objectKey → AES-decrypt item data

## Item Encryption Flow

```
Save item:
1. doDataEncryption() → generate random objectKey (KEY_LENGTH=16 hex, 64-bit entropy),
                        AES-encrypt item data with objectKey
2. DB::insert('items', { pw: encrypted, pw_iv: '' })
   ↑ pw_iv is always '' (IV is fixed, not stored)
3. storeUsersShareKey() [synchronous, only for creator]:
   a. encryptUserObjectKey(objectKey, creatorPublicKey)
      → CryptoManager::rsaEncrypt() with SHA-256/OAEP (phpseclib v3)
   b. batchUpsertSharekeys() → INSERT ... ON DUPLICATE KEY UPDATE
4. [Non-personal items] storeTask('new_item', ...) → background task for all other users

Background task (background_tasks___worker.php):
   → generateUserPasswordKeys() → storeUsersShareKey() for all users except creator
   → generateUserFieldKeys()    → storeUsersShareKey() on 'sharekeys_fields'
   → generateUserFileKeys()     → storeUsersShareKey() on 'sharekeys_files'

Retrieve item:
1. SELECT share_key FROM teampass_sharekeys_items WHERE object_id=? AND user_id=?
2. decryptUserObjectKey(sharekey, userPrivateKey)
   → CryptoManager::rsaDecrypt(): try SHA-256 first, fallback SHA-1 (legacy v1)
3. doDataDecryption(encryptedData, objectKey) → plaintext
```

**Note:** `decryptUserObjectKeyWithMigration()` must be used in new code (auto-upgrades v1→v3 sharekeys).
`decryptUserObjectKey()` (non-migration-aware) is still present in many read paths — existing call sites to watch.

## AES Internals (current implementation — known weaknesses)

```php
// In CryptoManager::aesEncrypt() / aesDecrypt()
$cipher = new AES('cbc');
$cipher->setIV(str_repeat("\0", 16));         // ⚠ Fixed zero IV
$cipher->setPassword($objectKey, 'pbkdf2',
    'sha1',              // hash (sha1 for items, sha256 for private keys)
    'phpseclib/salt',    // ⚠ Fixed salt — never random
    1000                 // ⚠ Only 1000 iterations (NIST: 600 000+)
);
```

**Stored columns:**
- `teampass_items.pw_iv` — exists in schema but always `''` (IV not stored, it's fixed)
- `teampass_categories_items.data_iv` — same, always `''`

These two columns are available without ALTER TABLE to store IV + salt metadata when the AES fix is applied.

**Planned AES improvement** (see `workReadmeFiles/encryption-analysis.md`, section 8):
- New format: `pw_iv = base64(version[1] + IV[16] + salt[32])`, `pw` = AES-GCM ciphertext
- Detection: `pw_iv = ''` → legacy format (backward-compatible lazy migration on item access)
- Sharekeys are **not affected** by this change (RSA layer is independent)

## Sharekeys Database Tables

Identical schema, one per object type:
- `teampass_sharekeys_items` — items
- `teampass_sharekeys_fields` — custom fields
- `teampass_sharekeys_files` — attached files
- `teampass_sharekeys_logs` — log entries (maintained during key regeneration; not used for password history display)
- `teampass_sharekeys_suggestions` — suggestions

```sql
object_id INT, user_id INT, share_key TEXT,
encryption_version TINYINT(1)  -- 1 = phpseclib v1 (SHA-1), 3 = phpseclib v3 (SHA-256)
```

**Password history note:** `log_items.old_value` (previous passwords) is encrypted with the
application master key (Defuse/SECUREFILE), NOT with per-user RSA sharekeys. `sharekeys_logs`
is maintained for key-regeneration flows, not for history display decryption.

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
CryptoManager::rsaDecryptWithVersion(string $data, string $privateKey, int $version): string
CryptoManager::aesEncrypt(string $data, string $key, string $mode = 'cbc', string $hash = 'sha1'): string
CryptoManager::aesDecrypt(string $data, string $key, string $mode = 'cbc', string $hash = 'sha1'): string
CryptoManager::aesDecryptWithVersionDetection(string $data, string $password, string $mode = 'cbc'): array
CryptoManager::generateRSAKeyPair(int $bits = 4096): array
```

**Fallback chain:**
```
rsaDecrypt(sharekey, privateKey)
  → Try phpseclib v3 + SHA-256/OAEP → Success? Return result
  → Failure → Try phpseclib v1 + SHA-1/OAEP → Return result (or '' on total failure)
```

**Auto-migration on item access** (`decryptUserObjectKeyWithMigration()` in `sources/main.functions.php:3792`):
```
decryptUserObjectKeyWithMigration(encryptedKey, privateKey, publicKey, sharekeyId, sharekeyTable)
  → rsaDecryptWithVersionDetection() → {data, version_used}
  → If version_used == 1:
      migrateSharekeyToV3()  ← re-encrypt with v3 (SHA-256)
      UPDATE sharekeys SET share_key=<new>, encryption_version=3
  → Return decrypted objectKey (transparent to caller)
```

**Key functions in `sources/main.functions.php`:**

| Function | Line | Purpose |
|---|---|---|
| `doDataEncryption()` | ~3555 | AES-encrypt item data, generate random objectKey |
| `doDataDecryption()` | ~3581 | AES-decrypt item data with objectKey |
| `teampassDecryptPasswordValue()` | ~3687 | Decrypt + normalize legacy HTML-encoded passwords |
| `encryptUserObjectKey()` | ~3710 | RSA-encrypt objectKey with user public key |
| `decryptUserObjectKey()` | ~3743 | RSA-decrypt sharekey (v3 + v1 fallback, no migration) |
| `decryptUserObjectKeyWithMigration()` | ~3792 | Decrypt + auto-migrate v1→v3 (**use this in new code**) |
| `migrateSharekeyToV3()` | ~3862 | Re-encrypt a single sharekey to v3 |
| `encryptPrivateKey()` | ~2940 | AES-encrypt user private key with password |
| `decryptPrivateKey()` | ~2861 | AES-decrypt private key (2-pass: raw pwd then xss_clean pwd) |
| `decryptPrivateKeyWithMigration()` | ~2984 | Decrypt + auto-migrate private key to v3 AES |
| `migrateAllUserKeysToV3()` | ~3105 | Re-encrypt private_key + backup to SHA-256 |
| `storeUsersShareKey()` | ~4068 | Create/update sharekeys for all eligible users |
| `batchUpsertSharekeys()` | ~4198 | Batch INSERT...ON DUPLICATE KEY UPDATE (chunks of 100) |
| `insertOrUpdateSharekey()` | ~4164 | Upsert a single sharekey row in DB |
| `uniqidReal()` | ~2718 | CSPRNG key generator (random_bytes → hex) |

**storeUsersShareKey() behavior:**
- Queries all active users with a non-empty public key
- Excludes special user IDs: `OTV_USER_ID`, `SSH_USER_ID`, `API_USER_ID`, and optionally `all_users_except_id`
- Calls `encryptUserObjectKey()` for each user → RSA-4096 per user (**throws RuntimeException on failure** — no per-user catch, one failure aborts the whole batch)
- Batch-upserts all rows, then deletes stale sharekeys for users no longer eligible
- ⚠ **Dead parameters** (confirmed by code inspection, `main.functions.php:4068`):
  - `$post_folder_is_personal` (2nd param) — accepted in signature, **never read** in body
  - `$onlyForUser` (5th param, default `false`) — accepted in signature, **never read** in body
  - Consequence: the function **always** creates/updates sharekeys for ALL eligible users, regardless of what callers pass. During `update_item` this means N×RSA-4096 operations happen synchronously in the HTTP request handler — a performance issue, not a security gap.
- `deleteAll=TRUE` IS active: removes sharekeys for users no longer in the eligible set (access removed, not an access gap for current users)

**Forced batch migration** (background tasks via `/scripts/traits/PhpseclibV3MigrationTrait.php`):
- Migrates all v1 sharekeys for a user in batches of 100
- Triggered when `teampass_users.phpseclibv3_migration_completed = 0`
- Covers tables: `sharekeys_items`, `sharekeys_logs`, `sharekeys_fields`, `sharekeys_files`, `sharekeys_suggestions`
- Diagnostic/repair: `/scripts/repair_phpseclib_migration.php`

**Rule: always use `decryptUserObjectKeyWithMigration()` in new code** — never call `rsaDecrypt()` directly for sharekeys.

**Rule: applies to custom field sharekeys too** — SELECT must include `increment_id` so the migration function can update the row in place.

---

## Custom Fields Encryption

Custom fields (`teampass_categories`) can be individually encrypted (`encrypted_data = 1`).

**Tables:**
- `teampass_categories` — field definitions (`encrypted_data` flag, `type`, `masked`, etc.)
- `teampass_categories_items` — field values per item (`data`, `data_iv`, `encryption_type`, `field_id`, `item_id`)
- `teampass_sharekeys_fields` — per-user sharekeys; `object_id` references `categories_items.id` (not `categories.id`)

**`encryption_type` in `teampass_categories_items`:**
- `'not_set'` — plaintext
- `TP_ENCRYPTION_NAME` — AES-encrypted; a sharekey must exist in `sharekeys_fields`

**Encryption flow (save/update):**
```
1. Encrypt before INSERT:
   doDataEncryption($plaintext) → {encrypted, objectKey}
   INSERT categories_items (data=encrypted, data_iv='', encryption_type=TP_ENCRYPTION_NAME)  ← atomic
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

---

## Personal Access Tokens (OAuth2 API access)

OAuth2/SSO users cannot use the password+apikey API path: the API needs the cleartext
password to AES-decrypt the RSA private key (`decryptPrivateKey()`), and OAuth2 users have no
usable password (their `pw` is a hash of the non-secret 64-bit `hashUserId(oid)`). A **Personal
Access Token (PAT)** solves this without weakening anything — it is a second, token-unlockable
copy of the private key, **independent of the RSA sharekey layer**.

**Admin gate:** `oauth2_api_enabled` (setting, default `0`; Settings → OAuth2). Independent of and
additional to the global `api` setting. Off ⇒ no token can be generated or used.

**Table** `teampass_api_tokens` (one row per token/device — created in `run.step5.php` and
`upgrade_run_3.2.0.php`):

```sql
id, user_id, token_hash VARCHAR(64) UNIQUE, wrapped_private_key TEXT,
salt VARCHAR(64), label, created_at, expires_at, last_used_at
```

**Crypto contract (authoritative — server owns both sides):**
```
T          = bin2hex(random_bytes(32))                                    # 64 hex, shown ONCE
token_hash = hash('sha256', T)                                            # DB lookup key
salt       = bin2hex(random_bytes(16))                                    # per token
K          = hash_hkdf('sha256', T, 32, 'teampass-extension-token-v1', hex2bin(salt))   # 32 bytes
wrapped_pk = encrypt_with_session_key($privateKeyClear, K)               # AES-256-GCM (reused helper)
```
Reuses `encrypt_/decrypt_with_session_key()` in `app/api/inc/encryption_utils.php` — no new
primitive. Deterministic `K` ⇒ same key on generation and on auth.

**Generation** (`app/sources/users.queries.php`, `generate_extension_token`): only when
`api=1 && oauth2_api_enabled=1 && session auth_type='oauth2'` and the cleartext private key is in
`$session->get('user-private_key')` (the issuance gate). Returns `T` once; stores only
`token_hash` + `wrapped_private_key` + `salt`.

**Authentication** (`AuthModel::getUserAuthByToken()` ← `POST /api/authorizeToken`): re-checks the
gate, the `^[a-f0-9]{64}$` format, bruteforce, that the user exists / `api_enabled=1` /
`auth_type='oauth2'`; looks up by `sha256(T)`; derives `K`; `decrypt_with_session_key()` → cleartext
private key → `issueJwtForUser()` (the shared tail extracted from the password path). On success
updates `last_used_at`.

**Security properties:** server stores only `sha256(T)` + token-wrapped key + salt → a DB dump
alone cannot decrypt; `T` is 256-bit (bypasses the 64-bit `hashUserId`); AES-256-GCM is
authenticated; revocable per device; optional `expires_at`; same bruteforce + `tp_src=api` logging
as the password path; body-only credentials, HTTPS only. The RSA sharekey layer is **untouched** —
PATs only add an alternate unlock of the existing private key.

---

## Known Security Weaknesses (to address)

See full analysis in `workReadmeFiles/encryption-analysis.md`.

| ID | Issue | Location | Priority |
|---|---|---|---|
| SEC-1 | Fixed zero IV in AES-CBC | `CryptoManager.php:199,262` | 🔴 Critical |
| SEC-2 | PBKDF2 only 1 000 iterations | `CryptoManager.php:205,268` | 🟠 High |
| SEC-3 | Fixed PBKDF2 salt `'phpseclib/salt'` | `CryptoManager.php:205,268` | 🔴 Critical |
| SEC-4 | objectKey only 64-bit entropy (`KEY_LENGTH=16`) | `include.php:46` | 🟠 High |
| SEC-5 | AES-CBC without authentication (no MAC/GCM) | `CryptoManager.php` | 🔴 Critical |
| SEC-6 | Password history encrypted with master key, not per-user RSA | `items.queries.php:1211` | 🟡 Moderate |
| SEC-7 | `decryptUserObjectKey()` (non-migration) used in ~12 read call sites | `items.queries.php` | 🟠 High |
| FUNC-1 | N×RSA-4096 synchronous in HTTP thread during `update_item` (dead `$onlyForUser` param) | `items.queries.php`, `main.functions.php:4068` | 🟡 Performance |
| FUNC-3 | `encryptUserObjectKey()` throws RuntimeException with no per-user catch in batch loop | `main.functions.php` | 🟡 Resilience |
| FUNC-4 | No retry logic for failed background subtasks | `background_tasks___worker.php` | 🟡 Resilience |
| FUNC-5 | Dead params `$post_folder_is_personal` + `$onlyForUser` in `storeUsersShareKey()` | `main.functions.php:4068` | 🟢 Cleanup |
| FUNC-6 | No DB transaction wrapping item INSERT + creator sharekey creation | `items.queries.php` | 🟡 Consistency |

**Fixing SEC-1/3/5 does not affect sharekeys** — the RSA layer is independent. Only `items.pw`,
`categories_items.data`, and `users.private_key` need re-encryption. The `pw_iv` and `data_iv`
columns (already in schema, currently `''`) can store the new format metadata without ALTER TABLE.
Migration strategy: lazy on-access, identical pattern to phpseclib v1→v3 migration already in place.

**Implementation details for all fixes:** see `workReadmeFiles/encryption-analysis.md`, section 10.
