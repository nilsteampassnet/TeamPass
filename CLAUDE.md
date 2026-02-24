# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TeamPass is a collaborative on-premise password manager built in PHP. It emphasizes security through multi-layer encryption, comprehensive audit logging, and granular role-based access control.

**Tech Stack:**
- PHP 8.1+ (strict typing throughout)
- MySQL 5.7+ / MariaDB 10.7+
- MeekroDB for database abstraction
- Symfony components (Session, PasswordHasher, HttpFoundation)
- AdminLTE 3 / Bootstrap / jQuery frontend
- Defuse PHP Encryption for data encryption

## Development Commands

### Static Analysis
```bash
# Run PHPStan (level 1, configured in phpstan.neon)
vendor/bin/phpstan analyse

# Check license compliance
vendor/bin/composer-license-checker
```

### JavaScript Linting
```bash
# The project has ESLint configured (.eslintrc) but npm scripts are minimal
# Configuration enforces: single quotes, no semicolons, 2-space indents, ES6 features
```

### Dependency Management
```bash
# Install/update PHP dependencies
composer install
composer update

# Note: composer.json includes custom local packages from includes/libraries/teampassclasses/
# These are NOT installed from Packagist but loaded via path repositories
```

### Database Operations
TeamPass manages its own schema through:
- Initial install: `/install/install.php` (web-based installer)
- Upgrades: `/install/upgrade.php` (detects version and runs migrations)
- Upgrade scripts: `/install/upgrade_run_*.php` (version-specific migrations)

### Background Tasks
```bash
# Background task handler (cron job or manual execution)
php scripts/background_tasks___handler.php

# Maintenance tasks available:
# - task_maintenance_clean_orphan_objects.php
# - task_maintenance_purge_old_files.php
# - task_maintenance_reload_cache_table.php
# - task_maintenance_users_personal_folder.php
```

## Architecture Overview

### Application Pattern: Modified MVC with Front Controller

**Entry Points:**
- Web interface: `/index.php` - Front controller with page-based routing via `?page=` parameter
- API: `/api/index.php` - RESTful API router with JWT authentication
- Installation: `/install/install.php` or `/install/upgrade.php`

**Not a traditional MVC framework** - TeamPass uses a pragmatic page-based architecture:
```
Request flow:
1. index.php → validates session → loads page template from /pages/
2. Page template (e.g., pages/items.php) → HTML structure
3. Page JavaScript (e.g., pages/items.js.php) → AJAX calls to /sources/
4. Backend handler (e.g., sources/items.queries.php) → processes request, returns JSON
```

### Directory Structure

```
/sources/               - Backend query handlers (*.queries.php)
  ├── core.php         - Session validation and initialization
  ├── identify.php     - Authentication logic
  ├── main.functions.php - Core utility functions (150KB+)
  └── *.queries.php    - Domain-specific AJAX handlers (items, users, folders, etc.)

/pages/                - Frontend page templates
  ├── *.php            - HTML views loaded by index.php
  └── *.js.php         - JavaScript with PHP-injected variables

/includes/             - Core libraries and configuration
  ├── config/
  │   ├── include.php      - Constants (TP_VERSION, paths, file extensions)
  │   └── settings.php     - Database credentials (created during install)
  ├── core/
  │   ├── login.php        - Login page
  │   └── logout.php       - Session termination
  └── libraries/teampassclasses/  - Custom PSR-4 classes (see below)

/api/                  - RESTful API
  ├── index.php        - API router
  ├── Controller/Api/  - API controllers (AuthController, ItemController, etc.)
  ├── Model/           - Data models
  └── inc/             - JWT utilities and API bootstrap

/install/              - Installation and upgrade system
  ├── install.php      - Web-based installer
  ├── upgrade.php      - Upgrade orchestrator
  └── upgrade_run_*.php - Version-specific migration scripts

/scripts/              - Background tasks (cron jobs)
/vendor/               - Composer dependencies
/plugins/              - Third-party JS/CSS libraries (AdminLTE, jQuery, DataTables)
```

### Custom TeamPass Classes (PSR-4 Namespaced)

Located in `/includes/libraries/teampassclasses/`, these are local packages managed via Composer's path repositories:

**SessionManager** (`TeampassClasses\SessionManager\SessionManager`)
- Singleton pattern: `SessionManager::getSession()`
- Wraps Symfony Session with custom encrypted storage handler
- Encryption key loaded from `SECUREPATH/SECUREFILE`
- Session data encrypted at rest on server

**ConfigManager** (`TeampassClasses\ConfigManager\ConfigManager`)
- Loads all settings from `teampass_misc` database table
- Caches in memory as `$SETTINGS` array
- Usage: `$configManager->getSetting('setting_key')`

**PasswordManager** (`TeampassClasses\PasswordManager\PasswordManager`)
- Uses Symfony PasswordHasher with 'auto' algorithm (bcrypt/argon2)
- Supports migration from legacy PasswordLib hashes
- Methods: `hashPassword()`, `verifyPassword()`, `migratePassword()`

**Encryption** (`TeampassClasses\Encryption\Encryption`)
- AES-256-CBC encryption for client-server communication
- PBKDF2 key derivation (SHA-512, 999 iterations)
- JSON message format: `{ciphertext, salt, iv}`

**NestedTree** (`TeampassClasses\NestedTree\NestedTree`)
- Modified Preorder Tree Traversal (MPTT) for folder hierarchy
- Manages `nleft`, `nright`, `nlevel` columns in `teampass_nested_tree`
- Direct mysqli queries for performance-critical tree operations

**LdapExtra** (`TeampassClasses\LdapExtra\`)
- LDAP/Active Directory authentication
- Separate classes for OpenLDAP and AD protocols

**OAuth2Controller** (`TeampassClasses\OAuth2Controller\OAuth2Controller`)
- OAuth2/SSO authentication (supports Azure AD)

**EmailService** (`TeampassClasses\EmailService\EmailService`)
- Email sending abstraction using PHPMailer

**FolderServices** (`TeampassClasses\FolderServices\FolderServices`)
- Folder operations and permission management

### Database Layer: MeekroDB

TeamPass uses **MeekroDB**, a simple database abstraction library (not a full ORM):

```php
// Common patterns:
DB::query('SELECT * FROM %l WHERE id=%i', 'teampass_users', $userId);
DB::queryFirstRow('SELECT * FROM %l WHERE id=%i', 'teampass_items', $itemId);
DB::insert('teampass_items', ['label' => 'Test', 'password' => $encrypted]);
DB::update('teampass_users', ['timestamp' => time()], 'id=%i', $userId);
DB::delete('teampass_log_items', 'id_item=%i', $itemId);

// Placeholders:
// %s - string
// %i - integer
// %l - literal (table/column name)
// %ls - array of values for IN clauses
```

**Database Configuration:**
- Created during installation: `/includes/config/settings.php`
- Table prefix: `teampass_` (configurable via `DB_PREFIX`)
- All queries use parameterized statements (no raw SQL concatenation)

**Key Tables:**
- `teampass_users` - User accounts, encrypted private keys
- `teampass_items` - Password items (encrypted)
- `teampass_nested_tree` - Folder hierarchy (MPTT structure)
- `teampass_misc` - Application settings (loaded by ConfigManager)
- `teampass_log_items` - Item access audit logs
- `teampass_sharekeys_items` - Per-user decryption keys for items
- `teampass_roles_title` - Roles/groups

### Authentication and Session Management

**Login Flow:**
1. User visits `/includes/core/login.php` (if no valid session)
2. Credentials submitted to `/sources/identify.php`
3. Validation against database, LDAP, or OAuth2
4. Session created via `SessionManager::getSession()`
5. User encryption keys generated/loaded
6. Session variables set (user-id, user-login, user-roles, etc.)

**Session Variables (accessed via Symfony Session):**
```php
$session = SessionManager::getSession();
$session->get('user-id');                    // User ID
$session->get('user-login');                 // Username
$session->get('user-admin');                 // Admin flag (1/0)
$session->get('user-roles');                 // Semicolon-separated role IDs
$session->get('user-accessible_folders');    // Array of accessible folder IDs
$session->get('user-privatekey');            // User's private encryption key
$session->get('user-session_duration');      // Expiration timestamp
$session->get('key');                        // Random session encryption key
```

**Session Validation (on every request via `/sources/core.php`):**
- Checks `user-session_duration` against current time
- Validates `key_tempo` in session matches database value
- Logs out user if expired or mismatched

**Multi-Factor Authentication:**
Supports Google Authenticator (TOTP), Duo Security, YubiKey, and AGSES.

### Encryption Architecture

**Two-Layer Encryption Model:**

1. **Application-Level** (Defuse PHP Encryption)
   - Master key stored in `SECUREPATH/SECUREFILE`
   - Used for: session data, settings.php DB password, misc settings

2. **User-Level** (RSA via phpseclib + AES)
   - Each user has an RSA public/private key pair (generated at account creation)
   - Private key stored encrypted in `teampass_users.private_key` (AES-encrypted with user's password via `CryptoManager::aesEncrypt`)
   - Items encrypted with a random **objectKey** (AES-256-CBC)
   - Each user gets a **sharekey**: the objectKey RSA-encrypted with the user's public key
   - Decryption chain: user password → AES-decrypt private key → RSA-decrypt sharekey → objectKey → AES-decrypt item data

**Item Encryption Flow:**
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

**Sharekeys Database Tables** (identical schema, one per object type):
- `teampass_sharekeys_items` — items
- `teampass_sharekeys_fields` — custom fields
- `teampass_sharekeys_files` — attached files
- `teampass_sharekeys_logs` — log entries
- `teampass_sharekeys_suggestions` — suggestions

Schema per table:
```sql
object_id INT, user_id INT, share_key TEXT,
encryption_version TINYINT(1)  -- 1 = phpseclib v1 (SHA-1), 3 = phpseclib v3 (SHA-256)
```

---

### phpseclib Version Management (v1 ↔ v3)

TeamPass supports two versions of phpseclib simultaneously for backward compatibility.

**Version differences:**

| | phpseclib v1 (legacy) | phpseclib v3 (current) |
|---|---|---|
| Location | `/includes/libraries/phpseclibV1/` | `/vendor/phpseclib/` (Composer) |
| RSA class | `Crypt_RSA` | `phpseclib3\Crypt\RSA` |
| RSA padding | OAEP + SHA-1 + MGF1-SHA1 | OAEP + SHA-256 + MGF1-SHA256 |
| AES class | `Crypt_AES` | `phpseclib3\Crypt\AES` |
| AES PBKDF2 hash | SHA-1 | SHA-256 |
| `encryption_version` | 1 | 3 |

**CryptoManager** (`/includes/libraries/teampassclasses/cryptomanager/src/CryptoManager.php`) is the single entry point for all crypto operations — never call phpseclib directly:

```php
// RSA encrypt (always uses v3/SHA-256 for new data)
CryptoManager::rsaEncrypt(string $data, string $publicKey): string

// RSA decrypt (tries SHA-256 first, fallback to SHA-1)
CryptoManager::rsaDecrypt(string $data, string $privateKey, bool $tryLegacy = true): string

// RSA decrypt with version detection (returns ['data' => ..., 'version_used' => 1|3])
CryptoManager::rsaDecryptWithVersionDetection(string $data, string $privateKey): array

// AES encrypt/decrypt (hash configurable: 'sha1' or 'sha256')
CryptoManager::aesEncrypt(string $data, string $key, string $mode = 'cbc', string $hash = 'sha1'): string
CryptoManager::aesDecrypt(string $data, string $key, string $mode = 'cbc', string $hash = 'sha1'): string
```

**Fallback chain for decryption:**
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
- Tables: items, fields, files, logs, suggestions
- Triggered when `teampass_users.phpseclibv3_migration_completed = 0`
- Diagnostic/repair: `/scripts/repair_phpseclib_migration.php`

**Rule: always use `decryptUserObjectKeyWithMigration()` in new code** — never call `rsaDecrypt()` directly for sharekeys, so that v1 entries are upgraded transparently on access.

---

### WebSocket Architecture (feature/websockets)

Real-time synchronization layer built on **Ratchet 0.4.4** (PHP) + **ReactPHP** event loop. The WebSocket server runs as a **separate daemon** alongside the web app.

#### Server Structure

```
/websocket/
  bin/server.php              — CLI entry point (daemon, handles SIGTERM/SIGINT)
  src/WebSocketServer.php     — Ratchet MessageComponent (onOpen/onMessage/onClose/onError)
  src/ConnectionManager.php   — Tracks connections + folder subscriptions (SplObjectStorage)
  src/MessageHandler.php      — Routes client actions to handlers
  src/AuthValidator.php       — Validates tokens/sessions on handshake
  src/EventBroadcaster.php    — Polls websocket_events table every 200ms, dispatches
  src/RateLimiter.php         — Sliding window: max 10 msgs/sec per connection
  src/Logger.php              — File-based logger (debug/info/warning/error)
  config/websocket.php        — Server settings
  config/teampass-websocket.service — Systemd unit (user: www-data, restart: always)
```

**Default config** (`/websocket/config/websocket.php`):
- Host: `127.0.0.1` (behind reverse proxy) — Port: `8080`
- Poll interval: 200ms — Batch: 100 events/poll
- Ping: 30s — Pong timeout: 60s — Max connections/user: 5 — Max message: 64KB

**teampass_misc settings** that control WebSocket:

| Setting | Default | Purpose |
|---|---|---|
| `websocket_enabled` | `0` | Enable/disable feature |
| `websocket_host` | `127.0.0.1` | Server host |
| `websocket_port` | `8080` | Server port |

#### Client Structure

```
/includes/js/teampass-websocket.js       — Core client library (auto-reconnect, pub/sub)
/includes/js/teampass-websocket-init.js  — Event handlers + page integration
```

- **Connection URL**: `ws(s)://hostname/ws` (protocol auto-detected from page)
- **Auto-reconnect**: exponential backoff, max 30s, capped at 10 attempts
- **Heartbeat**: client-side ping every 25s (below server's 30s interval)

#### Authentication (3 methods, in priority order)

1. **WebSocket token** — 64-char hex, generated by `generateWebSocketToken()` in `main.functions.php:~6638`, stored in `teampass_websocket_tokens`, injected into page by PHP
2. **JWT token** — for API clients
3. **Session cookie** (`PHPSESSID`) — legacy fallback; `key_tempo` validated against DB

#### Message Protocol

**Client → Server:**
```json
{ "action": "subscribe", "request_id": "req_xxxxx", "channel": "folder", "data": {"folder_id": 123} }
```

**Server → Client:**
```json
{ "type": "event", "event": "item_updated", "data": {...}, "request_id": "req_xxxxx", "timestamp": 1234567890 }
```

**Client actions:**

| Action | Data | Rate limited |
|---|---|---|
| `subscribe` | `channel`, `folder_id` | Yes |
| `unsubscribe` | `channel`, `folder_id` | Yes |
| `ping` / `pong` | — | No |
| `get_status` | — | Yes |
| `get_stats` | — | Yes (admin only) |
| `renew_item_lock` | `item_id` | Yes |

#### All Synchronization Events

Events are queued in `teampass_websocket_events` by PHP helpers, then broadcast by EventBroadcaster.

**Item events** (target: `folder`):

| Event | Triggered by | Key payload fields |
|---|---|---|
| `item_created` | New item saved | `item_id`, `folder_id`, `label`, `created_by` |
| `item_updated` | Item modified | `item_id`, `folder_id`, `label`, `updated_by` |
| `item_deleted` | Item deleted | `item_id`, `folder_id`, `label`, `deleted_by` |
| `item_copied` | Copy operation | `item_id`, `new_item_id`, `folder_id`, `copied_by` |

**Edition lock events** (target: `folder`):

| Event | Triggered by | Key payload fields |
|---|---|---|
| `item_edition_started` | User opens item for edit | `item_id`, `folder_id`, `user_login`, `user_id` |
| `item_edition_stopped` | Lock released or user disconnects | `item_id`, `folder_id`, `user_login`, `reason` |

Locks stored in the pre-existing `teampass_items_edition` table (`user_id`, `item_id`, `timestamp`). Renewed every N seconds via `renew_item_lock` action. Released automatically on WebSocket disconnect.

**Folder events** (target: `broadcast`):

| Event | Triggered by |
|---|---|
| `folder_created` | New folder |
| `folder_updated` | Folder renamed/modified |
| `folder_deleted` | Folder removed |
| `folder_permission_changed` | ACL change |

**User/task events:**

| Event | Target | Triggered by |
|---|---|---|
| `user_keys_ready` | `user` | Encryption key generation complete |
| `task_progress` | `user` | Long-running task heartbeat |
| `task_completed` | `user` | Background task finished |
| `session_expired` | `user` | Session timeout |
| `system_maintenance` | `broadcast` | Admin trigger |

#### PHP Emission Helpers (`sources/main.functions.php`)

```php
// Low-level: insert one event row
emitWebSocketEvent(string $eventType, string $targetType, ?int $targetId, array $payload, ?int $excludeUserId = null): bool
// ~line 6570

// High-level wrappers (use these in sources/*.queries.php)
emitItemEvent(string $action, int $itemId, int $folderId, string $label, string $userLogin, ?int $excludeUserId = null): bool
// action: 'created' | 'updated' | 'deleted'   ~line 6696

emitEditionLockEvent(string $action, int $itemId, int $folderId, string $userLogin, int $userId): bool
// action: 'started' | 'stopped'   ~line 6728

emitFolderEvent(string $action, int $folderId, string $title, string $userLogin, ?int $parentId = null, ?int $excludeUserId = null): bool
// action: 'created' | 'updated' | 'deleted'   ~line 6779

generateWebSocketToken(int $userId): string
// ~line 6638
```

**Rule: always call the high-level helpers** (`emitItemEvent`, `emitFolderEvent`, etc.) after any write operation on items or folders in `sources/*.queries.php`. Never insert into `teampass_websocket_events` directly.

#### Database Tables

```sql
-- Event queue (polled every 200ms, cleaned after 24h)
teampass_websocket_events:
  id, created_at, event_type VARCHAR(50), target_type ENUM('user','folder','broadcast'),
  target_id INT, payload JSON, processed TINYINT, processed_at

-- Auth tokens (single-use, injected into page)
teampass_websocket_tokens:
  id, user_id, token VARCHAR(64) UNIQUE, created_at, expires_at, used TINYINT

-- Connection log (for monitoring)
teampass_websocket_connections:
  id, user_id, resource_id VARCHAR(50), connected_at, disconnected_at, ip_address, user_agent
```

Tables created by `/install1/upgrade_run_3.1.7.php`. Migration script: `/install1/scripts/websocket_migration.php`.

#### End-to-End Event Flow Example

```
1. items.queries.php (case 'update_item'):
   DB::update(...) → emitItemEvent('updated', $itemId, $folderId, $label, $userLogin, $excludeId)

2. emitItemEvent() → emitWebSocketEvent('item_updated', 'folder', $folderId, {...})
   → DB::insert(teampass_websocket_events, {processed: 0})

3. EventBroadcaster (daemon, every 200ms):
   → SELECT unprocessed events → dispatchEvent() → broadcastToFolder($folderId, message)
   → ConnectionManager sends JSON to all connections subscribed to that folder (except excluded user)
   → UPDATE teampass_websocket_events SET processed=1

4. JS client (other users in same folder):
   → tpWs.on('item_updated', handler) fires
   → UI refreshes items list, shows "Updated by UserA"
```

### API Architecture

**API Entry:** `/api/index.php`

**Authentication:** JWT tokens (generated via `/api/authorize`)
```
Authorization: Bearer <jwt_token>

Token payload includes:
- User ID, username
- Allowed folders (comma-separated IDs)
- CRUD permissions (allowed_to_create, allowed_to_read, etc.)
- Session key (for server-side validation)
```

**Controllers:** `/api/Controller/Api/`
- `AuthController.php` - JWT generation
- `ItemController.php` - Item CRUD operations
- `FolderController.php` - Folder listing
- `UserController.php` - User operations

**Common Pattern:**
```php
class ItemController extends BaseController {
    public function getAction(array $userData): void {
        // 1. Validate HTTP method
        // 2. Check permissions from JWT
        // 3. Retrieve user's private key from DB
        // 4. Query items
        // 5. Decrypt items using sharekeys
        // 6. Return JSON response
    }
}
```

**Key Endpoints:**
- `POST /api/authorize` - Get JWT token
- `GET /api/item/get?id=123` - Get single item
- `GET /api/item/inFolders?folders=[1,2,3]` - Items in folders
- `POST /api/item/create` - Create item
- `GET /api/item/getOtp?id=123` - Get current TOTP/MFA code for an item
- `GET /api/folder/listFolders` - List accessible folders

### OTP/TOTP Endpoint

The `getOtp` endpoint allows retrieving the current 6-digit TOTP (Time-based One-Time Password) code for items that have OTP enabled.

**Endpoint:** `GET /api/item/getOtp`

**Parameters:**
- `id` (required): The item ID for which to retrieve the OTP code

**Authentication:**
- Requires valid JWT token with `allowed_to_read` permission
- User must have access to the folder containing the item

**Response (Success - 200 OK):**
```json
{
  "otp_code": "123456",
  "expires_in": 25,
  "item_id": 123
}
```

**Response Fields:**
- `otp_code`: The current 6-digit TOTP code
- `expires_in`: Number of seconds until the code expires
- `item_id`: The ID of the item

**Error Responses:**

- `400 Bad Request`: Item ID is missing
  ```json
  {"error": "Item id is mandatory"}
  ```

- `403 Forbidden`: Access denied or OTP not enabled
  ```json
  {"error": "Access denied to this item"}
  ```
  or
  ```json
  {"error": "OTP is not enabled for this item"}
  ```

- `404 Not Found`: Item or OTP configuration not found
  ```json
  {"error": "Item not found"}
  ```
  or
  ```json
  {"error": "OTP not configured for this item"}
  ```

- `500 Internal Server Error`: Decryption or generation failure
  ```json
  {"error": "Failed to decrypt OTP secret"}
  ```
  or
  ```json
  {"error": "Failed to generate OTP code: [error details]"}
  ```

**Example Usage:**
```bash
curl -X GET "https://your-teampass.com/api/item/getOtp?id=123" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Implementation Details:**
- OTP secrets are stored encrypted in the `items_otp` table
- Secrets are decrypted using the application's master encryption key (Defuse Crypto)
- TOTP codes are generated using the OTPHP library
- Codes are 6 digits and expire every 30 seconds (standard TOTP behavior)
- The endpoint checks both folder-level and item-level permissions

## Security Considerations

### Critical Security Patterns

1. **Always use parameterized queries:**
   ```php
   // GOOD
   DB::query('SELECT * FROM %l WHERE id=%i', 'teampass_items', $itemId);

   // BAD - never concatenate user input
   DB::query("SELECT * FROM teampass_items WHERE id=" . $itemId);
   ```

2. **Session validation on every sensitive operation:**
   ```php
   $session = SessionManager::getSession();
   if (!$session->has('user-id')) {
       echo json_encode(['error' => 'Not authenticated']);
       exit;
   }
   ```

3. **CSRF protection:**
   - Uses `owasp/csrf-protector-php` library
   - Tokens validated on all state-changing requests

4. **XSS prevention:**
   - Server-side: `voku/anti-xss` library
   - Client-side: DOMPurify
   - Output escaping with `htmlspecialchars()`

5. **Input sanitization:**
   - Use `elegantweb/sanitizer` for user input
   - Never trust user input, especially in AJAX handlers

6. **Encryption key management:**
   - Never commit `includes/config/settings.php` or `SECUREPATH/SECUREFILE`
   - Encryption keys must be generated during installation

### Common Vulnerabilities to Avoid

- **Command Injection:** Never use `exec()`, `shell_exec()`, or `system()` with user input
- **SQL Injection:** Always use MeekroDB's parameterized queries
- **Path Traversal:** Validate file paths, use `realpath()` checks
- **Timing Attacks:** Use `hash_equals()` for secret comparisons
- **Session Fixation:** Session regenerated on login (handled by SessionManager)

## Code Patterns and Conventions

PHP Code requires to fit PHPStan level 2.

### PHP Code Style

- **Strict types:** All new PHP files should use `declare(strict_types=1);`
- **Namespaces:** Custom classes use `TeampassClasses\*` namespace
- **Error handling:** Use try/catch blocks, return JSON errors in AJAX handlers
- **Constants:** Defined in `/includes/config/include.php` (e.g., `TP_VERSION`)

### AJAX Request/Response Pattern

**Standard AJAX handler structure** (in `/sources/*.queries.php`):
```php
<?php
declare(strict_types=1);

// Load core
require_once 'core.php';

// Get session
$session = SessionManager::getSession();

// Check authentication
if (!$session->has('user-id')) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get request type
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);

// Handle based on type
switch ($type) {
    case 'action_name':
        // 1. Validate permissions
        // 2. Get and sanitize input
        // 3. Perform operation
        // 4. Return JSON response
        echo json_encode(['status' => 'success', 'data' => $result]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
```

### JavaScript Conventions

Per `.eslintrc`:
- Single quotes for strings
- No semicolons (ASI)
- 2-space indentation
- `const` over `let`, never `var`
- Arrow functions preferred
- ES6 features encouraged

### Folder Tree Operations

When working with folders, always use the `NestedTree` class to maintain tree integrity:
```php
use TeampassClasses\NestedTree\NestedTree;

$tree = new NestedTree('teampass_nested_tree', 'id', 'parent_id', 'title');

// Get all descendants
$descendants = $tree->getDescendants($folderId);

// Add new folder
$newId = $tree->add($title, $parentId);

// Move folder
$tree->move($folderId, $newParentId);
```

**Never manually update `nleft`, `nright`, `nlevel` columns** - this will corrupt the tree.

### Password/Encryption Operations

**Hashing passwords (user authentication):**
```php
use TeampassClasses\PasswordManager\PasswordManager;

$passwordManager = new PasswordManager();
$hash = $passwordManager->hashPassword($plaintext);
$isValid = $passwordManager->verifyPassword($plaintext, $hash);
```

**Encrypting item data:**
```php
// Item encryption happens at a higher level
// See sources/items.queries.php for reference implementation
// Uses Defuse\Crypto\Crypto for item data encryption
// Sharekeys created for each user with folder access
```

## Testing and Debugging

### No Formal Test Suite
TeamPass does not currently have PHPUnit tests or automated testing infrastructure.

### Debugging Approach
1. Enable error logging in PHP (`error_reporting(E_ALL)`)
2. Check logs in TeamPass admin interface (Admin > Logs)
3. Database logging: All queries can be logged via MeekroDB hooks
4. Browser console for JavaScript errors
5. Network tab for AJAX request/response inspection

### Manual Testing Checklist
When making changes to core functionality:
- Test with multiple user roles (admin, standard user, read-only)
- Test with personal folders enabled/disabled
- Test with different encryption scenarios
- Verify audit logs are created correctly
- Check LDAP/OAuth2 integration if authentication changed

## Common Development Workflows

### Adding a New Setting

1. Add entry to `teampass_misc` table:
   ```sql
   INSERT INTO teampass_misc (type, intitule, valeur)
   VALUES ('admin', 'my_new_setting', '0');
   ```

2. Access in PHP:
   ```php
   $configManager = new ConfigManager();
   $value = $configManager->getSetting('my_new_setting');
   ```

3. If adding during upgrade, add to appropriate `/install/upgrade_run_*.php`

### Adding a New Page

1. Create view: `/pages/mypage.php` (HTML structure)
2. Create JavaScript: `/pages/mypage.js.php` (client-side logic)
3. Create handler: `/sources/mypage.queries.php` (server-side AJAX handler)
4. Add route in `/index.php` (add case to page switch)
5. Add permissions check in `/sources/core.php` if needed

### Adding a New API Endpoint

1. Create controller: `/api/Controller/Api/MyController.php`
2. Add route in `/api/index.php`
3. Implement action methods with JWT validation
4. Return JSON responses with proper HTTP status codes

### Database Schema Changes

1. Create new upgrade script: `/install/upgrade_run_X.X.X.php`
2. Add table/column changes with `DB::query()` or `DB::queryRaw()`
3. Update version constants in `/includes/config/include.php`
4. Test upgrade path from previous version

## Important Files and Their Purposes

- **index.php** - Application entry point, session validation, page routing
- **sources/core.php** - Session initialization and validation (loaded by all backends)
- **sources/main.functions.php** - Shared utility functions (150KB+ of helpers)
- **sources/identify.php** - Login authentication logic
- **includes/config/include.php** - Application constants and configuration
- **includes/config/settings.php** - Database credentials (created during install, never commit)
- **install/upgrade.php** - Version detection and upgrade orchestration
- **api/index.php** - API router with JWT validation
- **scripts/background_tasks___handler.php** - Cron job entry point

## Version Information

Current version constants in `/includes/config/include.php`:
- `TP_VERSION` - Major.minor version (e.g., '3.1.5')
- `TP_VERSION_MINOR` - Patch version (e.g., '2')
- Full version: 3.1.5.2

Version upgrades managed through `/install/upgrade_run_*.php` scripts.

## IMPORTANT Rules
- Always prioritize minimal modifications.
- Never suggest a complete refactor.
- Respect the existing coding style.
- New functions must remain compatible with PHP 8.2.
- Secure all user inputs (SQLi, XSS).
- Do not invent things; rely strictly on factual data.

You are working on an existing project.
Absolute priority: integrate with the current code rather than proposing an ideal architecture. Each modification must be minimal, local, and compatible with the rest of the project. If a global improvement is possible, suggest it at the end as a separate recommendation.

## Expected Style
- Readable code.
- Useful but not excessive comments.
- Do not introduce external libraries without an explicit request.
- Comments in English.

## Commit
- Commit messages must always be in English.
- Use simple and concise sentences.
- Des phrases simples et concises