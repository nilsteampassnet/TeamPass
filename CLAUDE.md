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

```bash
vendor/bin/phpstan analyse          # PHPStan level 4
vendor/bin/composer-license-checker # License compliance
composer install                    # PHP dependencies
php scripts/background_tasks___handler.php  # Background tasks
```

Database schema: initial install via `/install/install.php`, upgrades via `/install/upgrade.php` + `/install/upgrade_run_*.php`.

## Architecture Overview

**Entry Points:**
- Web: `/index.php` → page routing via `?page=` parameter
- API: `/api/index.php` → JWT-authenticated REST
- Install: `/install/install.php` or `/install/upgrade.php`

**Request flow:** `index.php` → `/pages/*.php` (HTML) → `/pages/*.js.php` (JS) → AJAX → `/sources/*.queries.php` (JSON response)

**Directory Structure:**
```
/sources/       - Backend AJAX handlers (*.queries.php) + core.php, identify.php, main.functions.php
/pages/         - Frontend templates (*.php) and JS (*.js.php)
/includes/      - Config, core libs, teampassclasses/
/api/           - REST API (Controller/Api/, Model/, inc/)
/install/       - Installer and upgrade scripts
/scripts/       - Background tasks (cron)
/vendor/        - Composer dependencies
```

**Custom TeamPass Classes** (in `/includes/libraries/teampassclasses/`, PSR-4):
- `SessionManager` — Symfony Session + EncryptedSessionProxy (Redis opt-in or filesystem)
- `ConfigManager` — settings from `teampass_misc` DB table, APCu-cached 60s; call `invalidateCache()` after writes
- `PasswordManager` — bcrypt/argon2 via Symfony PasswordHasher
- `Encryption` — AES-256-CBC for client-server comms
- `NestedTree` — MPTT folder hierarchy (`nleft`/`nright`/`nlevel`); never update these columns manually
- `CryptoManager` — single entry point for all RSA/AES crypto; never call phpseclib directly

**Dual-location classes:** `ConfigManager` and `SessionManager` exist in both `includes/libraries/teampassclasses/` and `vendor/teampassclasses/`. Always edit both.

## Database Layer: MeekroDB

```php
DB::query('SELECT * FROM %l WHERE id=%i', 'teampass_users', $userId);
DB::queryFirstRow('SELECT * FROM %l WHERE id=%i', 'teampass_items', $itemId);
DB::insert('teampass_items', ['label' => 'Test', 'password' => $encrypted]);
DB::update('teampass_users', ['timestamp' => time()], 'id=%i', $userId);
DB::delete('teampass_log_items', 'id_item=%i', $itemId);
// %s=string  %i=integer  %l=literal(table/col)  %ls=array for IN
```

**Key Tables:** `teampass_users`, `teampass_items`, `teampass_nested_tree`, `teampass_misc`, `teampass_log_items`, `teampass_sharekeys_items`, `teampass_roles_title`

## Authentication and Session Management

**Login Flow:** `login.php` → `sources/identify.php` → DB/LDAP/OAuth2 validation → `SessionManager::getSession()` → set session vars → load encryption keys

**Key session vars:**
```php
$session = SessionManager::getSession();
$session->get('user-id');               // User ID
$session->get('user-login');            // Username
$session->get('user-admin');            // Admin flag (1/0)
$session->get('user-roles');            // Semicolon-separated role IDs
$session->get('user-accessible_folders'); // Array of folder IDs
$session->get('user-privatekey');       // User's private encryption key
$session->get('user-session_duration'); // Expiration timestamp
$session->get('key');                   // Random session encryption key
```

Session validation on every request via `sources/core.php` (checks `user-session_duration` + `key_tempo`).

MFA: Google Authenticator (TOTP), Duo Security, YubiKey, AGSES.

## Encryption — Critical Rules

> Full architecture details: @.claude/docs/architecture-encryption.md

**Rule: always use `decryptUserObjectKeyWithMigration()` in new code** — never call `rsaDecrypt()` directly for sharekeys. This transparently upgrades phpseclib v1 → v3 on access.

**Rule: applies to custom field sharekeys too** — every read path on `sharekeys_fields` must use `decryptUserObjectKeyWithMigration()`. The SELECT must include `increment_id`.

**Rule: always encrypt before INSERT for custom fields** — never insert plaintext and update afterwards. A failed UPDATE leaves plaintext with `encryption_type='not_set'`, silently bypassing decryption.

**Encryption version:** `encryption_version=1` = phpseclib v1 (SHA-1/OAEP, legacy), `encryption_version=3` = phpseclib v3 (SHA-256/OAEP, current).

## WebSocket

> Full architecture details: @.claude/docs/architecture-websocket.md

**Rule: always call the high-level helpers** (`emitItemEvent`, `emitFolderEvent`, etc.) after any write on items/folders in `sources/*.queries.php`. Never insert into `teampass_websocket_events` directly.

## PHP-FPM

> Full architecture details: @.claude/docs/architecture-php-fpm.md

**Rule: spawn background tasks with `getPHPBinary()`** — it resolves a real PHP CLI binary under FPM (never `php-fpm` / `'false'`). **Rule: `tpFinishRequestEarly()` only after the full response is echoed** — later output is not delivered. Admin settings: `cli_php_binary_path`, `enable_fastcgi_finish_request`.

## API

> Full reference: @.claude/docs/api-reference.md

Controllers in `/api/Controller/Api/`. JWT auth via `Authorization: Bearer <token>`. Key endpoints: `/api/authorize`, `/api/item/get`, `/api/item/create`, `/api/item/getOtp`, `/api/folder/listFolders`.

## Browser Extension Auto-Configuration

> Full architecture details: @.claude/docs/architecture-extension-autoconfig.md

One-click setup of the browser extension from the web app: a same-origin `window.postMessage` bridge detects the extension (content script on `<all_urls>`) and pushes a config bundle; a downloadable JSON file is the fallback. Credentials use token mode (a durable PAT) — **the password is never transmitted**.

**Rule: both PAT gates (issuance in `users.queries.php`, consumption in `AuthModel::getUserAuthByToken`) are relaxed only behind the admin toggle `extension_token_all_auth_types`** (default `0`, Settings → API → Browser extension). Off ⇒ OAuth2-only behaviour preserved. **Rule: the auto-config PAT is durable** (`expires_at = NULL`), not single-use — token mode reuses it for silent re-auth; only the bundle has a 24h staleness window. **Rule: reload the unpacked extension fully after changing `content/`, `background/`, or `confirm/`** — Chrome does not hot-reload them.

## Security Considerations

1. **Parameterized queries always:**
   ```php
   DB::query('SELECT * FROM %l WHERE id=%i', 'teampass_items', $itemId); // GOOD
   DB::query("SELECT * FROM teampass_items WHERE id=" . $itemId);         // NEVER
   ```

2. **Session validation on every sensitive operation:**
   ```php
   $session = SessionManager::getSession();
   if (!$session->has('user-id')) { echo json_encode(['error' => 'Not authenticated']); exit; }
   ```

3. **CSRF:** `owasp/csrf-protector-php` — tokens validated on all state-changing requests
4. **XSS:** `voku/anti-xss` (server) + DOMPurify (client) + `htmlspecialchars()`
5. **Input:** `elegantweb/sanitizer` for user input
6. **Never commit** `includes/config/settings.php` or `TEAMPASS_SECRETS/SECUREFILE`
7. **Never use** `exec()`/`shell_exec()`/`system()` with user input
8. **Path traversal:** validate file paths with `realpath()`
9. **Timing attacks:** use `hash_equals()` for secret comparisons

## Code Patterns and Conventions

PHP Code must pass **PHPStan level 4**.

- `declare(strict_types=1)` in all new PHP files
- Custom classes: `TeampassClasses\*` namespace
- Constants: defined in `/app/config/include.php`

**Standard AJAX handler** (`/sources/*.queries.php`):
```php
<?php
declare(strict_types=1);
require_once 'core.php';
$session = SessionManager::getSession();
if (!$session->has('user-id')) { echo json_encode(['error' => 'Not authenticated']); exit; }
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
switch ($type) {
    case 'action_name':
        echo json_encode(['status' => 'success', 'data' => $result]);
        break;
    default:
        echo json_encode(['error' => 'Unknown action']);
}
```

**JavaScript** (per `.eslintrc`): single quotes, no semicolons, 2-space indent, `const` over `let`, arrow functions, ES6.

**Folder tree:** always use `NestedTree` class — never manually update `nleft`/`nright`/`nlevel`.

## Testing and Debugging

No PHPUnit test suite. Debugging: PHP error logging, TeamPass Admin > Logs, MeekroDB query hooks, browser console + network tab.

**Manual testing checklist:** multiple user roles (admin, standard, read-only), personal folders on/off, encryption scenarios, audit log creation, LDAP/OAuth2 if auth changed.

## Common Development Workflows

**New setting:** INSERT into `teampass_misc`, access via `ConfigManager::getSetting()`, add to upgrade script, call `ConfigManager::invalidateCache()` after writes.

**New page:** `/pages/mypage.php` + `/pages/mypage.js.php` + `/sources/mypage.queries.php` + route in `index.php` + permission check in `sources/core.php`.

**New API endpoint:** controller in `/api/Controller/Api/`, route in `/api/index.php`, JWT validation, JSON response.

**DB schema change:** new `/install/upgrade_run_X.X.X.php`, update version in `/app/config/include.php`, test upgrade path.

**PR from GitHub:**
1. Analyse the original issue and the PR changes
2. Confirm the fix is appropriate
3. Ensure PHPStan level 4 compatibility

## Important Files

- `index.php` — entry point, session validation, page routing
- `sources/core.php` — session init and validation (loaded by all backends)
- `sources/main.functions.php` — shared utility functions (150KB+)
- `sources/identify.php` — login authentication logic
- `includes/config/include.php` — application constants
- `includes/config/settings.php` — DB credentials (never commit)
- `install/upgrade.php` — version detection and upgrade orchestration
- `api/index.php` — API router with JWT validation
- `scripts/background_tasks___handler.php` — cron job entry point

## Version Information

Constants in `/app/config/include.php`: `TP_VERSION` (major.minor), `TP_VERSION_MINOR` (patch). Upgrades via `/install/upgrade_run_*.php`.

## IMPORTANT Rules

- Always prioritize minimal modifications.
- Never suggest a complete refactor.
- Respect the existing coding style.
- New functions must remain compatible with PHP 8.2.
- Secure all user inputs (SQLi, XSS).
- Do not invent things; rely strictly on factual data.

### SQL Compatibility (ONLY_FULL_GROUP_BY)

MySQL default since 5.7.5. Rules:
- Every non-aggregated SELECT column must be in GROUP BY, or functionally dependent on the GROUP BY key
- Never `SELECT *` with partial GROUP BY
- Prefer `SELECT DISTINCT` over GROUP BY when no aggregation needed
- Aliases defined in SELECT can be referenced in GROUP BY (MySQL extension, valid)

## Expected Style

- Readable code, useful but not excessive comments
- No external libraries without explicit request
- Comments in English

## Commit

- Commit messages must always be in English
- Use simple and concise sentences

## PR Review Conventions

- PR branches must target `pr-XXXX` with XXX = GitHub ID
- All public functions must have a docblock
- Variable names in English only
- No `var_dump()` or `console.log()` in production
- Watch for impacts on `install` and `upgrade`

## MCP Tools: code-review-graph

**IMPORTANT: This project has a knowledge graph. ALWAYS use the
code-review-graph MCP tools BEFORE using Grep/Glob/Read to explore
the codebase.** The graph is faster, cheaper (fewer tokens), and gives
you structural context (callers, dependents, test coverage) that file
scanning cannot.

### When to use graph tools FIRST

- **Exploring code**: `semantic_search_nodes` or `query_graph` instead of Grep
- **Understanding impact**: `get_impact_radius` instead of manually tracing imports
- **Code review**: `detect_changes` + `get_review_context` instead of reading entire files
- **Finding relationships**: `query_graph` with callers_of/callees_of/imports_of/tests_for
- **Architecture questions**: `get_architecture_overview` + `list_communities`

Fall back to Grep/Glob/Read **only** when the graph doesn't cover what you need.

### Key Tools

| Tool | Use when |
| ------ | ---------- |
| `detect_changes` | Reviewing code changes — gives risk-scored analysis |
| `get_review_context` | Need source snippets for review — token-efficient |
| `get_impact_radius` | Understanding blast radius of a change |
| `get_affected_flows` | Finding which execution paths are impacted |
| `query_graph` | Tracing callers, callees, imports, tests, dependencies |
| `semantic_search_nodes` | Finding functions/classes by name or keyword |
| `get_architecture_overview` | Understanding high-level codebase structure |
| `refactor_tool` | Planning renames, finding dead code |

### Workflow

1. The graph auto-updates on file changes (via hooks).
2. Use `detect_changes` for code review.
3. Use `get_affected_flows` to understand impact.
4. Use `query_graph` pattern="tests_for" to check coverage.
