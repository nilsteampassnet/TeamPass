# Security Architecture Study: Public/Private Directory Structure for TeamPass

> **Status:** Proposal — Reference document for future implementation  
> **Author:** TeamPass Security Architecture Review  
> **Date:** 2026-04-08  
> **Scope:** Full directory restructuring to reduce attack surface

---

## 1. Executive Summary

TeamPass is a security-critical application: it stores and manages credentials for its users. As such, its own security posture must be exemplary. A key weakness in the current layout is that the entire application — including PHP source files, query handlers, configuration backups, and security libraries — lives directly under the webroot.

Even though `.htaccess` rules and nginx `deny` directives protect the most sensitive paths, every PHP file that is reachable via HTTP is a potential attack vector: misconfigured web servers, `.htaccess` overrides ignored in nginx, or future web server changes could accidentally expose files that were never meant to be public.

The proposed solution is to split the application into a **`/public`** directory (everything the web server needs to serve) and a **`/app`** directory (everything else: business logic, configuration, libraries, scripts). Only `/public` is exposed to the internet. This is a well-established pattern (Laravel, Symfony, Zend all follow it) and dramatically reduces the attack surface.

**Benefits at a glance:**

| Concern | Current | After restructuring |
|---|---|---|
| Config files in webroot | Yes (protected by .htaccess) | No |
| Sources accessible if nginx misconfigured | Yes | No |
| Backup .php files in webroot | Yes | No |
| Security library classes in webroot | Yes (protected by nginx) | No |
| Vendor/Composer packages in webroot | Yes (protected by nginx) | No |
| Scripts (CLI) accessible via HTTP | Yes (protected by nginx) | No |
| Surface area for path traversal | High | Minimal |

---

## 2. Current Security Analysis

### 2.1 Existing Protections

TeamPass already implements several layers of defense. These must be preserved (and improved) in the new structure:

**nginx rules (docker/nginx/teampass.conf):**
```nginx
location ~ /\. { deny all; }   # hidden files
location ~ /(includes/config|backups|install/upgrade_scripts|vendor)/ { deny all; return 404; }
location ~ /includes/libraries/.*\.php$ { deny all; return 404; }
```

**`.htaccess` files:**
- `/includes/config/.htaccess` — `Deny from all` (strongest protection)
- `/files/.htaccess` — disables PHP execution (protects against upload attacks)
- `/upload/.htaccess` — same
- `/includes/avatars/.htaccess` — same

**PHP-level routing:**
- All pages routed through `index.php` with session validation
- AJAX endpoints require active session with valid `key_tempo`

### 2.2 Remaining Weaknesses

Despite these protections, the following risks persist:

1. **Implicit trust in web server config** — if nginx is reconfigured or `.htaccess` support is re-enabled without updating rules, `/includes/config/settings.php` (database credentials) and dozens of query handler files become directly accessible.

2. **Backup configuration files** — `/includes/config/settings.php.2024_12_04_053553.bak` exists in the webroot. If nginx deny rules don't match the extension, these could be served as plaintext.

3. **CLI scripts in webroot** — `/scripts/` contains `restore.php`, `install-cli.php`, `migrate_*.php`, `repair_*.php`. These are not meant for web access but currently rely entirely on web server rules for protection.

4. **Sources directory** — `/sources/` contains 100+ `*.queries.php` files with raw database operations. Direct HTTP access to these, bypassing `index.php` session validation, is protected only by web server rules.

5. **`/install/` directory** — remains accessible after installation. Currently protected by a redirect to the upgrade page, but the installer itself could be re-triggered on a clean DB.

6. **Information disclosure** — `version-commit.php`, `composer.json`, `README.md` expose version and dependency information useful for targeted attacks.

---

## 3. Proposed Directory Structure

### 3.1 High-level layout

```
/var/www/html/TeamPass/          ← repository root (not the webroot)
├── public/                      ← WEB SERVER DOCUMENT ROOT (only this)
│   ├── index.php
│   ├── error.php
│   ├── self-unlock.php
│   ├── favicon.ico
│   ├── api/
│   │   └── index.php
│   ├── assets/                  ← formerly /includes/css, /includes/js, etc.
│   │   ├── css/
│   │   ├── js/
│   │   ├── fonts/
│   │   ├── images/
│   │   └── avatars/
│   ├── plugins/                 ← frontend libraries (jQuery, Bootstrap, DataTables…)
│   └── install/                 ← only during setup phase (see §3.3)
│       ├── index.php            ← entry point for installer
│       ├── assets/
│       └── ...
│
├── app/                         ← APPLICATION CORE (never served by web server)
│   ├── config/                  ← formerly /includes/config/
│   │   ├── settings.php         ← DB credentials, SECUREPATH reference
│   │   └── include.php          ← application constants
│   ├── core/                    ← formerly /includes/core/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── otv.php
│   │   └── 2fa/
│   ├── sources/                 ← formerly /sources/
│   │   ├── core.php
│   │   ├── main.functions.php
│   │   └── *.queries.php
│   ├── pages/                   ← formerly /pages/
│   │   ├── *.php
│   │   └── *.js.php
│   ├── includes/                ← formerly /includes/ (non-public parts)
│   │   ├── language/
│   │   ├── libraries/           ← teampassclasses, phpseclibV1, etc.
│   │   └── .externals/
│   ├── api/                     ← formerly /api/ (controllers, models, not entry point)
│   │   ├── Controller/
│   │   ├── Model/
│   │   └── inc/
│   ├── scripts/                 ← formerly /scripts/ (CLI only)
│   ├── websocket/               ← formerly /websocket/
│   └── vendor/                  ← Composer packages
│
├── storage/                     ← runtime-generated data
│   ├── files/                   ← formerly /files/ (encrypted user files)
│   ├── upload/                  ← formerly /upload/ (encrypted uploads)
│   └── logs/                    ← application logs
│
└── secrets/                     ← outside webroot (or on external path)
    └── <SECUREFILE>             ← master encryption key (ideally on separate volume)
```

### 3.2 Web server document root change

```
Before: DocumentRoot /var/www/html/TeamPass
After:  DocumentRoot /var/www/html/TeamPass/public
```

This single change ensures that **nothing outside `/public`** can be served by the web server, regardless of nginx/Apache configuration.

### 3.3 Install directory lifecycle

The `/public/install/` directory represents a special case: it must be accessible during initial setup, but must be completely removed (or disabled) afterward.

**Recommended lifecycle:**
1. **Before installation:** `/public/install/` exists and is accessible
2. **After installation:** a flag file `app/config/.installed` is created; the installer checks for it and refuses to run
3. **Security hardening step:** a post-install script renames or removes `/public/install/` (or nginx denies access based on flag)
4. **Upgrade:** the upgrade wizard runs from `/public/install/upgrade.php` but checks for a valid existing installation

---

## 4. File Access Rights

### 4.1 Principle

Apply the principle of least privilege at the filesystem level. No directory should be writable by the web server unless it absolutely must be.

### 4.2 Ownership model

```
Owner:  www-data (web server user, for files that must be read/written at runtime)
Group:  teampass (deployment group, for developers and CI/CD)
```

### 4.3 Permission matrix

| Path | Owner | Group | Mode | Notes |
|---|---|---|---|---|
| `public/` | root | teampass | `755` | Web server reads, no write needed |
| `public/index.php` | root | teampass | `644` | Read-only |
| `public/assets/` | root | teampass | `755` | Static files, read-only |
| `public/assets/avatars/` | www-data | teampass | `750` | Written by PHP (avatar upload) |
| `public/plugins/` | root | teampass | `755` | Read-only |
| `app/` | root | teampass | `750` | Web server reads, never writes |
| `app/config/` | root | teampass | `640` | Config files: root only |
| `app/config/settings.php` | root | teampass | `640` | **Critical** |
| `app/sources/` | root | teampass | `750` | Read-only by PHP |
| `app/pages/` | root | teampass | `750` | Read-only by PHP |
| `app/vendor/` | root | teampass | `750` | Read-only by PHP |
| `app/scripts/` | root | teampass | `750` | Read by cron/CLI only |
| `storage/` | www-data | teampass | `750` | PHP must write here |
| `storage/files/` | www-data | teampass | `750` | Encrypted user files |
| `storage/upload/` | www-data | teampass | `750` | Temp uploads |
| `storage/logs/` | www-data | teampass | `750` | Log files |
| `secrets/` | root | root | `700` | **Isolated, 0 web access** |
| `secrets/<SECUREFILE>` | root | root | `600` | Master key: root only |

> **Note on `app/config/settings.php`:** The PHP process (running as `www-data`) needs to read this file. The recommended solution is to add `www-data` to the `teampass` group and set mode `640`. This avoids giving `www-data` ownership of critical config files.

### 4.4 Protecting `/storage/files/` and `/storage/upload/`

These directories contain encrypted files uploaded by users. They must be writable by PHP, but file execution must be disabled at every level:

**nginx:**
```nginx
location /storage/ {
    deny all;
    return 404;
}
```
Files are never served directly — always through a PHP download handler that verifies permissions before streaming.

**Filesystem:** no execute bit for group/other; `chmod o-rwx` on the entire storage tree.

---

## 5. nginx Configuration (New)

```nginx
server {
    listen 443 ssl;
    server_name teampass.example.com;

    # Document root points only to /public
    root /var/www/html/TeamPass/public;
    index index.php;

    # Global security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # Static assets with long cache
    location ~* \.(css|js|woff|woff2|ttf|eot|ico|png|jpg|jpeg|gif|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Main application (PHP)
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # REST API
    location /api/ {
        try_files $uri $uri/ /api/index.php?$args;
    }

    # PHP processing
    location ~ \.php$ {
        # Only allow PHP in the document root (public/)
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    # Block hidden files
    location ~ /\. {
        deny all;
        return 404;
    }

    # Storage is completely blocked (served via PHP only)
    # (storage/ is NOT in public/ so this is already implicit)
    # If symlinked or misconfigured, belt-and-suspenders:
    location ~ /storage/ {
        deny all;
        return 404;
    }

    # Disable directory listing
    autoindex off;
}
```

**Key improvement vs. current config:** there are **zero `deny` rules for application subdirectories** because those directories simply **do not exist** under the document root. No deny rule can be forgotten or misconfigured.

---

## 6. Apache / .htaccess Configuration (Alternative)

For deployments using Apache, the `/public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Route all requests through index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Disable directory listing
Options -Indexes

# Block execution in upload directories (belt-and-suspenders even if not in public/)
<FilesMatch "\.(php|php3|php4|phtml|pl|py|jsp|asp|sh|cgi)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

---

## 7. Required Code Adaptations

### 7.1 Scope of changes

This is the most significant work in the migration. TeamPass uses `__DIR__` and relative paths throughout. All path references must be updated to reflect the new layout.

### 7.2 Bootstrap constant (critical path)

Introduce a single bootstrap constant that all files use to locate the application root:

```php
// public/index.php (and public/api/index.php, public/self-unlock.php)
define('TEAMPASS_ROOT', dirname(__DIR__));  // Points to /var/www/html/TeamPass
define('TEAMPASS_APP',  TEAMPASS_ROOT . '/app');
define('TEAMPASS_PUBLIC', __DIR__);
define('TEAMPASS_STORAGE', TEAMPASS_ROOT . '/storage');
```

All `require_once` calls change from:
```php
// Before
require_once __DIR__ . '/includes/config/settings.php';
require_once __DIR__ . '/sources/main.functions.php';
```

To:
```php
// After
require_once TEAMPASS_APP . '/config/settings.php';
require_once TEAMPASS_APP . '/sources/main.functions.php';
```

### 7.3 Settings.php path constants

`/app/config/settings.php` currently defines:
```php
define('TEAMPASS_ROOT_PATH', __DIR__.'/../../');
```

This must be updated:
```php
// app/config/settings.php
define('TEAMPASS_ROOT_PATH', dirname(__DIR__, 2) . '/');  // /var/www/html/TeamPass/
define('TEAMPASS_STORAGE_PATH', TEAMPASS_ROOT_PATH . 'storage/');
```

And constants that reference storage paths:
```php
// Before
define('LOG_TASKS_FILE', '../files/teampass_tasks.log');

// After
define('LOG_TASKS_FILE', TEAMPASS_STORAGE_PATH . 'logs/teampass_tasks.log');
```

### 7.4 Asset URLs in PHP templates

`/app/pages/*.php` and PHP includes that generate HTML referencing CSS/JS:

```php
// Before
<link rel="stylesheet" href="includes/css/animate.min.css">
<script src="plugins/jquery/jquery.min.js"></script>

// After (paths are relative to document root /public, so they stay the same if
// assets are at /public/assets/ and /public/plugins/)
<link rel="stylesheet" href="assets/css/animate.min.css">
<script src="plugins/jquery/jquery.min.js"></script>
```

> Note: if the `includes/` URL prefix is kept in `/public/assets/includes/` for backwards compatibility (e.g. to avoid changing all JS AJAX call URLs), the asset directory can be renamed during a second migration phase.

### 7.5 AJAX endpoint URLs (JavaScript)

Currently, JavaScript files make AJAX calls to relative paths:
```javascript
// In pages/*.js.php
url: 'sources/items.queries.php',
url: 'includes/core/login.php',
```

These must route through `index.php` in the new structure, OR the AJAX handlers must be exposed as thin wrappers in `/public/` that include the actual handlers from `/app/sources/`.

**Option A (recommended):** Proxy wrappers in `/public/sources/`
```php
// public/sources/items.queries.php (thin wrapper)
<?php
define('TEAMPASS_ROOT', dirname(__DIR__, 1));
require_once TEAMPASS_ROOT . '/app/sources/items.queries.php';
```
Zero JavaScript changes needed. The symlink or wrapper file is in `/public/sources/`.

**Option B:** Route all AJAX through `index.php` using `?source=items&action=...` parameter. Requires significant JS refactoring — not recommended for initial migration.

**Option C (cleanest):** Expose a single `/public/api/ajax.php` entry point that dispatches to handlers. More secure, but larger effort.

### 7.6 File download/upload paths

```php
// Before (in sources/items.queries.php and others)
$filePath = __DIR__ . '/../files/' . $filename;

// After
$filePath = TEAMPASS_STORAGE . '/files/' . $filename;
```

### 7.7 SECUREPATH

`SECUREPATH` is already defined as an absolute path outside the webroot (`/var/www/TP_secrets/20220213/`). This requires **no change**. It is already correctly isolated.

### 7.8 Installer

`/public/install/index.php` must:
1. Define `TEAMPASS_ROOT` (pointing to `dirname(__DIR__)`)
2. Look for `TEAMPASS_APP . '/config/settings.php'` to detect existing installation
3. Write new `settings.php` to `TEAMPASS_APP . '/config/settings.php'`

### 7.9 CLI scripts (`/app/scripts/`)

Scripts called by cron need `TEAMPASS_ROOT` too:
```php
// app/scripts/background_tasks___handler.php
define('TEAMPASS_ROOT', dirname(__DIR__, 2));
define('TEAMPASS_APP',  TEAMPASS_ROOT . '/app');
require_once TEAMPASS_APP . '/config/settings.php';
```

### 7.10 Summary of affected files

| Category | Files affected | Estimated impact |
|---|---|---|
| Entry points | `public/index.php`, `public/api/index.php`, `public/self-unlock.php` | Low (3 files) |
| Config constants | `app/config/settings.php`, `app/config/include.php` | Low (2 files) |
| Core bootstrap | `app/sources/core.php` | Medium |
| Source handlers | `app/sources/*.queries.php` (100+ files) | High (path constants only) |
| Page templates | `app/pages/*.php`, `app/pages/*.js.php` | Medium (asset paths) |
| CLI scripts | `app/scripts/*.php` | Medium |
| Installer | `public/install/*.php` + install helpers | High |
| AJAX source wrappers | `public/sources/*.php` (new thin wrappers) | Medium (new files) |

---

## 8. Migration Plan

### Phase 0 — Preparation

- [ ] Create feature branch `feature/public-private-structure`
- [ ] Audit all hardcoded paths (grep for `__DIR__`, `'../'`, `'includes/'`, `'sources/'`, `'pages/'`)
- [ ] Audit all AJAX URLs in JavaScript files
- [ ] Set up a staging environment for testing
- [ ] Document rollback procedure

### Phase 1 — Directory Scaffolding

- [ ] Create `/app/` structure (config, core, sources, pages, includes, api, scripts, websocket, vendor)
- [ ] Create `/public/` structure (index, error, self-unlock, assets, plugins, api entry point)
- [ ] Create `/storage/` structure (files, upload, logs)
- [ ] Move Composer `vendor/` to `/app/vendor/`; update `composer.json` `vendor-dir`
- [ ] Update `autoload.psr-4` paths in `composer.json` to point to `app/` locations

### Phase 2 — Bootstrap and Constants

- [ ] Add `TEAMPASS_ROOT`, `TEAMPASS_APP`, `TEAMPASS_STORAGE` constants to all entry points
- [ ] Update `/app/config/settings.php` to use new path constants
- [ ] Update `/app/config/include.php` constants
- [ ] Run PHPStan after each change to catch undefined constant references

### Phase 3 — Source and Page Migration

- [ ] Move `/sources/` → `/app/sources/`
- [ ] Move `/pages/` → `/app/pages/`
- [ ] Move `/includes/config/` → `/app/config/`
- [ ] Move `/includes/core/` → `/app/core/`
- [ ] Move `/includes/language/` → `/app/includes/language/`
- [ ] Move `/includes/libraries/` → `/app/includes/libraries/`
- [ ] Create thin proxy wrappers in `/public/sources/` for each query handler

### Phase 4 — Public Asset Migration

- [ ] Move `/includes/css/` → `/public/assets/css/`
- [ ] Move `/includes/js/` → `/public/assets/js/`
- [ ] Move `/includes/fonts/` → `/public/assets/fonts/`
- [ ] Move `/includes/images/` → `/public/assets/images/`
- [ ] Move `/includes/avatars/` → `/public/assets/avatars/`
- [ ] Move `/plugins/` → `/public/plugins/`
- [ ] Update all HTML `href`/`src` references in page templates

### Phase 5 — Storage Migration

- [ ] Move `/files/` → `/storage/files/`
- [ ] Move `/upload/` → `/storage/upload/`
- [ ] Update log file path constants
- [ ] Verify cron job configurations use new absolute paths

### Phase 6 — Installer Migration

- [ ] Adapt `/install/` to work from `/public/install/`
- [ ] Update installer to write config to `/app/config/settings.php`
- [ ] Implement post-install detection and redirect

### Phase 7 — Web Server Reconfiguration

- [ ] Update nginx `root` directive to `/public`
- [ ] Remove now-redundant `deny` rules (sources, config, vendor, etc. no longer in webroot)
- [ ] Add `deny all` for `/storage/` if any symlink puts it under `/public`
- [ ] Test all routes

### Phase 8 — Filesystem Permissions

- [ ] Apply permission matrix from §4.3
- [ ] Verify PHP process can read `/app/` but not write
- [ ] Verify PHP process can write to `/storage/`
- [ ] Verify cron/CLI can read `/app/scripts/`
- [ ] Run full regression test suite

### Phase 9 — Security Validation

- [ ] Verify no PHP sources accessible via HTTP (nikto scan, manual checks)
- [ ] Verify config files not accessible
- [ ] Verify directory listing disabled everywhere
- [ ] Verify SECUREFILE path still valid
- [ ] Test LDAP/OAuth2 authentication flows
- [ ] Test file upload/download
- [ ] Test background task execution

---

## 9. Benefits Summary

### Security improvements

1. **Eliminates entire attack surface classes:** even with a completely broken nginx/Apache config, application source code is not served. There is nothing in `/public` to protect except `index.php` and static assets.

2. **Defense in depth for config:** credentials in `/app/config/settings.php` are unreachable via HTTP by construction (not directory — filesystem boundary).

3. **No more reliance on .htaccess for security:** `.htaccess` files in the current `/includes/config/` protect against misconfiguration, but only within Apache. In nginx they are silently ignored. The new structure makes the web server irrelevant for non-public directories.

4. **Principle of least exposure:** web server user (`www-data`) has read access to `/app/` (to include PHP files) but write access only to `/storage/`. If the PHP process is compromised, the attacker cannot modify source files.

5. **Install directory safety:** `install/` is in `/public/install/` and can be removed from the filesystem after setup, or disabled by the web server, without touching application code.

6. **Cleaner `.gitignore` and secret management:** secrets and runtime data live in directories clearly separated from source code, making it easier to exclude them from version control and backup strategies.

### Operational improvements

1. **Simpler web server config:** no deny rules for internal directories. Any new directory created outside `/public/` is automatically not web-accessible.

2. **Easier deployment:** deploy script can `rsync /app/` and `rsync /public/` separately; restart PHP-FPM. No risk of accidentally deploying config over web-accessible path.

3. **Prepared for CDN:** static assets in `/public/assets/` and `/public/plugins/` can be served from a CDN without any code changes.

4. **Cleaner cron setup:** CLI scripts in `/app/scripts/` have no ambiguity about web access. Permissions can be tighter.

---

## 10. Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Path references broken after move | High | High | Introduce `TEAMPASS_ROOT` constant; grep audit before migrating |
| AJAX endpoint URLs broken in JS | High | High | Use thin proxy wrappers in `/public/sources/`; no JS changes needed |
| Installer writes config to wrong path | Medium | High | Test installer in staging; write integration test |
| Cron jobs with hardcoded paths | Medium | Medium | Audit all crontabs; update to absolute paths |
| File upload/download breaks | Medium | High | Test all file operations in staging before switching |
| Session / SECUREPATH issues | Low | Critical | Path already absolute; no change needed |
| `.htaccess` in `/public/` silently ignored (nginx) | Low | Low | nginx config already handles routing; no security rules needed in .htaccess |
| Permission regression (www-data can write `/app/`) | Low | High | Enforce after each deployment via permission script |

---

## 11. Compatibility with Existing Tooling

### Docker

`/docker/nginx/teampass.conf` must update the `root` directive. The Dockerfile `COPY` instructions must be updated to copy to the new layout.

### Composer

```json
// composer.json changes
{
  "config": {
    "vendor-dir": "app/vendor"
  },
  "autoload": {
    "psr-4": {
      "TeampassClasses\\": "app/includes/libraries/teampassclasses/"
    }
  }
}
```

After change: `composer dump-autoload`

### PHPStan

`phpstan.neon` paths update:
```yaml
parameters:
  paths:
    - app/
    - public/
  excludePaths:
    - app/vendor/
```

### Background Tasks (cron)

```cron
# Before
* * * * * php /var/www/html/TeamPass/scripts/background_tasks___handler.php

# After
* * * * * php /var/www/html/TeamPass/app/scripts/background_tasks___handler.php
```

### WebSocket Daemon (systemd)

```ini
# websocket/config/teampass-websocket.service
[Service]
ExecStart=/usr/bin/php /var/www/html/TeamPass/app/websocket/bin/server.php
```

---

## 12. Conclusion and Recommendation

The public/private directory split is a **high-value, medium-effort** security improvement. It eliminates entire categories of vulnerabilities by construction rather than by configuration. Once implemented, the system becomes significantly more resilient to:

- Web server misconfiguration
- Nginx/Apache rule oversights
- Future contributors forgetting to protect a new directory

**Recommended approach:** implement in phases as described in §8, starting with a staging environment. Phase 2 (bootstrap constants) is the keystone — get that right and the rest becomes mechanical. The thin proxy wrapper approach (Option A in §7.5) minimizes JavaScript changes and allows a safe incremental rollout.

**Estimated effort:**
- Phase 0–2 (preparation + constants): 2–3 days
- Phase 3–4 (source + asset migration): 3–5 days
- Phase 5–6 (storage + installer): 2–3 days
- Phase 7–9 (web server + validation): 1–2 days
- **Total:** approximately 2–3 weeks of focused development, including testing

This document should serve as the reference specification for the implementation branch. Each phase should be delivered as a separate pull request to allow incremental review.

---

*This document is a living reference. Update it as implementation decisions are made.*
