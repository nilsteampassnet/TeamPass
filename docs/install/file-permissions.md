<!-- docs/install/file-permissions.md -->

## File system permissions

Correct file system permissions are critical for a password manager. This page defines the minimal permission set required by Teampass 3.2.x, explains the rationale for each directory, and provides ready-to-use commands for the most common server configurations.

The upgrade wizard (**Step 1 — Requirements check**) verifies every item listed here and will block the upgrade if a required permission is missing.

---

## Directory layout (3.2.x)

```
/path/to/teampass/            ← project root
├── app/                      ← application code  (must NOT be writable by web server)
│   ├── config/               ← writable during install/upgrade only
│   │   └── settings.php      ← writable during install/upgrade only
│   ├── includes/
│   │   └── libraries/
│   │       └── csrfp/
│   │           ├── libs/     ← writable during install/upgrade only
│   │           └── log/      ← writable at runtime (always)
│   ├── vendor/               ← read-only (Composer dependencies)
│   └── websocket/
│       └── logs/             ← writable at runtime (WebSocket daemon only)
├── public/                   ← webroot — DocumentRoot must point here
│   │                            (must NOT be writable by web server)
│   ├── install/              ← restrict or remove after installation
│   └── assets/
│       └── avatars/          ← writable at runtime (optional — avatar uploads)
├── storage/                  ← runtime data  (writable at runtime)
│   ├── files/                ← required writable
│   ├── upload/               ← optional writable (file attachments)
│   └── backups/              ← optional writable (SQL dumps)
└── secrets/                  ← encryption key  (readable by web server, NOT in webroot)
    └── teampass-seckey.txt
```

---

## Principles

Teampass follows the **principle of least privilege**: each path is granted only the minimum access it genuinely needs.

| Notation | Meaning |
|----------|---------|
| `0755` (dir) / `0644` (files) | Owner full, group and world read-only — application code |
| `0750` (dir) / `0640` (files) | Owner full, group read-only, world none — config and runtime data |
| `0700` (dir) / `0600` (files) | Owner only — encryption key |

> :warning: **Never use `0777` or `0775`** on any Teampass directory. World-writable paths allow any system user or compromised process to plant or overwrite files.

---

## Directory reference

### Must NOT be writable by the web server

The upgrade wizard raises a warning (non-blocking) when these directories are writable — it indicates a configuration weakness that should be corrected.

| Directory | Recommended perms | Rationale |
|-----------|------------------|-----------|
| `app/` | `0755` dir / `0644` files | Application source code — writable only by the deployment user, not the web server |
| `public/` | `0755` dir / `0644` files | Webroot — must not allow the web server to upload or overwrite files |
| `app/vendor/` | `0755` / `0644` | Composer dependencies — installed at deploy time only |

---

### Writable during install/upgrade only

These paths are written by the web installer or upgrade wizard. Between runs, they should revert to read-only for hardened deployments.

| Path | Recommended perms | What is written |
|------|------------------|-----------------|
| `app/config/` | `0750` (dir), owned by web server user | Contains `settings.php` |
| `app/config/settings.php` | `0640`, owned by web server user | Encrypted DB credentials, `TEAMPASS_SECRETS` path |
| `app/includes/libraries/csrfp/libs/` | `0750`, owned by web server user | `csrfp.config.php` (CSRF token configuration) |

> :bulb: **Hardening tip:** after installation you can lock these paths:
> ```bash
> sudo chmod 0550 /path/to/teampass/app/config
> sudo chmod 0440 /path/to/teampass/app/config/settings.php
> sudo chmod 0550 /path/to/teampass/app/includes/libraries/csrfp/libs
> ```
> Restore write access before running an upgrade, then lock again afterwards.

---

### Writable at runtime (permanent)

These paths must remain writable by the web server process during normal operation.

| Path | Required | Recommended perms | What is written |
|------|:--------:|------------------|-----------------|
| `storage/` | **yes** | `0750` | Parent directory — PHP creates sub-directories at runtime |
| `storage/logs/` | **yes** | `0750` | Background task trigger/lock files and task log. **The task handler aborts silently if it cannot write here** (see Troubleshooting below) |
| `storage/files/` | **yes** | `0750` | Temporary import files, restore logs |
| `storage/upload/` | optional | `0750` | Encrypted file attachments uploaded by users |
| `storage/backups/` | optional | `0750` | SQL backup files generated before schema migrations |
| `public/assets/avatars/` | optional | `0750` | User avatar images |
| `app/includes/libraries/csrfp/log/` | **yes** | `0750` | CSRF protection audit log |
| `app/websocket/logs/` *(WebSocket only)* | optional | `0750` | WebSocket daemon log file |

**Required** means the upgrade wizard blocks until the path is writable.
**Optional** means a warning is shown but the wizard still proceeds.

---

### Encryption key (TEAMPASS_SECRETS)

The Defuse encryption master key must be stored **outside the webroot** (`public/`) whenever possible.

| Path | Recommended perms | Notes |
|------|------------------|-------|
| `secrets/` | `0750`, owned by web server user | Readable by the web server — must not be web-accessible |
| `secrets/teampass-seckey.txt` | `0600` | The key file itself — owner read-only |

> :warning: **`secrets/` must not be inside `public/`.** It lives at the project root, one level above the webroot, and is therefore unreachable via HTTP. In Docker deployments the key is stored at `/var/www/html/secrets/`.

---

## Ownership

All files and directories must be owned by the user that runs the web server (PHP-FPM) process.

| Server stack | Typical user | Typical group |
|-------------|-------------|---------------|
| Apache + mod_php | `www-data` | `www-data` |
| Apache + PHP-FPM | `www-data` | `www-data` |
| Nginx + PHP-FPM (Debian/Ubuntu) | `www-data` | `www-data` |
| Nginx + PHP-FPM (RHEL/Alpine) | `nginx` | `nginx` |
| Docker (official image) | `nginx` | `nginx` |

### Advanced: separate owner from web server user

For higher security, own the files with a dedicated non-login account (`teampass`) and give the web server only group read access:

```
owner : teampass   (non-login system account)
group : www-data   (web server process)
```

With this model:
- PHP code cannot overwrite itself (web server has no write permission on code files)
- Writable runtime directories are owned `teampass:www-data` with `0770`

This prevents a compromised PHP process from modifying application files.

---

## Quick-setup commands

Replace `/var/www/html/teampass` with your actual installation path and `www-data` with your web server user.

### Standard install (Apache / Nginx on Debian / Ubuntu)

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=www-data

# ── Ownership ────────────────────────────────────────────────────────────────
sudo chown -R ${WEB_USER}:${WEB_USER} ${TEAMPASS}

# ── Application code: not writable by web server ─────────────────────────────
sudo find ${TEAMPASS}/app     -type d -exec chmod 0755 {} \;
sudo find ${TEAMPASS}/app     -type f -exec chmod 0644 {} \;
sudo find ${TEAMPASS}/public  -type d -exec chmod 0755 {} \;
sudo find ${TEAMPASS}/public  -type f -exec chmod 0644 {} \;

# ── Writable during install/upgrade only ─────────────────────────────────────
sudo chmod 0750 ${TEAMPASS}/app/config
sudo chmod 0640 ${TEAMPASS}/app/config/settings.php
sudo chmod 0750 ${TEAMPASS}/app/includes/libraries/csrfp/libs

# ── Always-writable runtime directories ──────────────────────────────────────
sudo chmod 0750 ${TEAMPASS}/storage
sudo chmod 0750 ${TEAMPASS}/storage/logs
sudo chmod 0750 ${TEAMPASS}/storage/files
sudo chmod 0750 ${TEAMPASS}/storage/upload
sudo chmod 0750 ${TEAMPASS}/storage/backups
sudo chmod 0750 ${TEAMPASS}/public/assets/avatars
sudo chmod 0750 ${TEAMPASS}/app/includes/libraries/csrfp/log

# ── Encryption key directory ──────────────────────────────────────────────────
sudo chmod 0750 ${TEAMPASS}/secrets
sudo chmod 0600 ${TEAMPASS}/secrets/teampass-seckey.txt
```

### RHEL / AlmaLinux / Rocky Linux (SELinux environments)

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=apache   # or nginx, depending on your setup

sudo chown -R ${WEB_USER}:${WEB_USER} ${TEAMPASS}

# Same chmod rules as above, then apply SELinux contexts:
sudo semanage fcontext -a -t httpd_sys_rw_content_t "${TEAMPASS}/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "${TEAMPASS}/public/assets/avatars(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t \
    "${TEAMPASS}/app/includes/libraries/csrfp/log(/.*)?"
sudo semanage fcontext -a -t httpd_sys_content_t    "${TEAMPASS}/secrets(/.*)?"
sudo restorecon -Rv ${TEAMPASS}
```

### Docker

Permissions are set automatically by the Docker entrypoint. No manual action is required.

The image enforces:

| Path | Permissions | Owner |
|------|------------|-------|
| `secrets/` | `750` | `nginx:nginx` |
| `storage/files/` | `750` | `nginx:nginx` |
| `storage/upload/` | `750` | `nginx:nginx` |
| `storage/backups/` | `750` | `nginx:nginx` |
| `app/includes/libraries/csrfp/log/` | `750` | `nginx:nginx` |

---

## After installation: locking down the installer

Once Teampass is running, prevent HTTP access to the install directory.

### Apache

```apache
<Directory /var/www/html/teampass/public/install>
    Require all denied
</Directory>
```

### Nginx

```nginx
location ^~ /install/ {
    deny all;
    return 403;
}
```

Alternatively, remove the directory entirely:

```bash
sudo rm -rf /var/www/html/teampass/public/install
```

> :warning: You will need to restore `public/install/` from the release archive before running a future upgrade.

---

## Verification checklist

Run this after installation or after an upgrade to confirm the permission state:

```bash
TEAMPASS=/var/www/html/teampass

echo "=== Must NOT be writable (expect 755) ==="
stat -c "%a %n" ${TEAMPASS}/app ${TEAMPASS}/public

echo "=== Writable at install/upgrade (expect 750 or 550 post-hardening) ==="
stat -c "%a %n" \
    ${TEAMPASS}/app/config \
    ${TEAMPASS}/app/includes/libraries/csrfp/libs

echo "=== settings.php (expect 640 or 440 post-hardening) ==="
stat -c "%a %n" ${TEAMPASS}/app/config/settings.php

echo "=== Runtime-writable (expect 750) ==="
stat -c "%a %n" \
    ${TEAMPASS}/storage \
    ${TEAMPASS}/storage/files \
    ${TEAMPASS}/storage/upload \
    ${TEAMPASS}/storage/backups \
    ${TEAMPASS}/public/assets/avatars \
    ${TEAMPASS}/app/includes/libraries/csrfp/log

echo "=== Encryption key directory (expect 750) ==="
stat -c "%a %n" ${TEAMPASS}/secrets

echo "=== Encryption key file (expect 600) ==="
stat -c "%a %n" ${TEAMPASS}/secrets/teampass-seckey.txt

echo "=== World-writable check (expect no output) ==="
find ${TEAMPASS} -not -path "*/vendor/*" -perm -o+w -ls
```

---

## Troubleshooting

### Background tasks never run (dashboard shows "Delayed", PHP log shows "cannot create lock file")

**Symptoms**

- Admin dashboard → *System Health* → *Cron Jobs* shows **Delayed** (or **Error**). Hover the
  info icon next to the badge for the same hint.
- Tasks pile up in *Tasks → In progress* and only complete when the handler is launched by hand.
- The PHP / web-server error log contains one of:
  - `Teampass Background Tasks: cannot create lock file ".../storage/logs/teampass_background_tasks.lock" - check that the web server user can write to this directory.`
  - `Teampass: cannot write background tasks trigger file ".../storage/logs/...".`

**Cause**

The web server user (for example `www-data`) cannot write to `storage/logs/`. The task handler
writes its lock and trigger files there; if it cannot create the lock file it aborts immediately
and **silently**, so no background task is processed. Running the handler from a shell as your own
user appears to work because that user owns the directory — which is exactly why the problem is
easy to miss.

**Fix**

Make `storage/logs/` (and the rest of `storage/`) writable and owned by the web server user:

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=www-data
sudo chown -R ${WEB_USER}:${WEB_USER} ${TEAMPASS}/storage
sudo find ${TEAMPASS}/storage -type d -exec chmod 2750 {} \;
```

Then confirm a background task completes — create or edit an item, or run the handler once
**as the web server user**:

```bash
sudo -u ${WEB_USER} php ${TEAMPASS}/app/scripts/background_tasks___handler.php
```

> The cron that runs `app/sources/scheduler.php` must also be configured (see
> [Tasks](../manage/tasks.md)). The event-driven trigger only covers tasks created by a web
> action; purely scheduled jobs (scheduled backups, inactive-user housekeeping, maintenance)
> rely on the cron.

---

## Summary table

| Path | Upgrade wizard check | Install/Upgrade | Runtime | Recommended perms |
|------|:--------------------:|:---------------:|:-------:|:-----------------:|
| `app/` | warning if writable | read | read | `0755` / `0644` |
| `public/` | warning if writable | read | read | `0755` / `0644` |
| `app/config/` | **required writable** | write | read | `0750` → `0550` post-install |
| `app/config/settings.php` | **required writable** | write | read | `0640` → `0440` post-install |
| `app/includes/libraries/csrfp/libs/` | **required writable** | write | read | `0750` → `0550` post-install |
| `app/includes/libraries/csrfp/log/` | **required writable** | write | write | `0750` |
| `app/vendor/` | — | read | read | `0755` / `0644` |
| `public/assets/avatars/` | optional writable | — | write | `0750` |
| `public/install/` | — | read | **none** | Remove or deny via web server |
| `storage/` | **required writable** | write | write | `0750` |
| `storage/logs/` | **required writable** | write | write | `0750` |
| `storage/files/` | **required writable** | write | write | `0750` |
| `storage/upload/` | optional writable | — | write | `0750` |
| `storage/backups/` | optional writable | write | write | `0750` |
| `secrets/` | **required readable** | write | read | `0750` |
| `secrets/teampass-seckey.txt` | **required readable** | write | read | `0600` |
| `app/websocket/logs/` *(WebSocket)* | — | — | write (daemon) | `0750` |
