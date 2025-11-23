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

2. **User-Level** (Sodium/libsodium)
   - Each user has a public/private key pair
   - Private key encrypted with user's password
   - Items encrypted with **folder-level encryption key**
   - Each user gets a **sharekey** (item key encrypted with their public key)
   - Decryption: user password → private key → sharekey → item key → item data

**Item Encryption Flow:**
```
Save item:
1. Generate random encryption key for item
2. Encrypt item data with this key
3. For each user with folder access:
   a. Encrypt item key with user's public key
   b. Store in teampass_sharekeys_items

Retrieve item:
1. Get user's private key (decrypted with password)
2. Get sharekey for this item+user
3. Decrypt sharekey with private key → item key
4. Decrypt item data with item key
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
- `GET /api/folder/listFolders` - List accessible folders

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
