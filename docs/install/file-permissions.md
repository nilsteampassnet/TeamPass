<!-- docs/install/file-permissions.md -->

## File system permissions

Correct file system permissions are critical for a password manager. This page defines the minimal permission set required by Teampass, explains the rationale for each directory, and provides ready-to-use commands for the most common server configurations.

---

## Principles

Teampass follows the **principle of least privilege**: each directory is granted only the access it genuinely needs at runtime.

| Notation | Directories | Files | Meaning |
|----------|------------|-------|---------|
| **`0750 / 0640`** | `rwxr-x---` | `rw-r-----` | Owner full access, group read-only, world none |
| **`0700 / 0600`** | `rwx------` | `rw-------` | Owner only — for the encryption key |

> :warning: **Never use `0777` or `0775`** on any Teampass directory. World-writable directories allow any system user or compromised process to plant files or overwrite sensitive data.

---

## Directory reference

### Read-only at runtime

These directories contain application code and static assets. They must **never** be writable by the web server process after installation.

| Directory | Recommended perms | Notes |
|-----------|------------------|-------|
| `/` (Teampass root) | `0750` (dir) / `0640` (files) | Application code — writable only during install/upgrade |
| `api/` | `0750` / `0640` | REST API controllers |
| `sources/` | `0750` / `0640` | AJAX handlers |
| `pages/` | `0750` / `0640` | HTML/JS templates |
| `includes/core/` | `0750` / `0640` | Login/logout logic |
| `includes/libraries/` (except csrfp sub-dirs) | `0750` / `0640` | Third-party libraries |
| `vendor/` | `0750` / `0640` | Composer dependencies |
| `install/` | `0750` / `0640` | **Remove or restrict access after installation** (see below) |

> :bulb: The `install/` directory should be removed or made inaccessible via web server configuration once the installation is complete. Leaving it writable serves no purpose at runtime.

---

### Writable at install/upgrade only

These directories are written to during the web installer or upgrade process. After that, they should revert to read-only.

| Directory / File | Recommended perms | What is written |
|-----------------|------------------|-----------------|
| `includes/config/` | `0750` (dir) | `settings.php` (DB credentials, encrypted) |
| `includes/config/settings.php` | `0640` | Encrypted DB password, SECUREPATH/SECUREFILE paths |
| `includes/libraries/csrfp/libs/` | `0750` (dir) | `csrfp.config.php` (CSRF token configuration) |

> :bulb: **Hardening tip:** once the installation is complete, you can make `includes/config/` read-only for the web server:
> ```bash
> chmod 550 /path/to/teampass/includes/config
> chmod 440 /path/to/teampass/app/config/settings.php
> ```
> You must restore write access before running an upgrade, then lock it again afterwards.

---

### Writable at runtime (permanent)

These directories must remain writable by the web server process during normal operation.

| Directory | Recommended perms | What is written |
|-----------|------------------|-----------------|
| `files/` | `0750` | Background task trigger/lock files, exported backups, restore logs |
| `files/backups/` | `0750` | Database backup files (`.sql`) |
| `upload/` | `0750` | Encrypted file attachments uploaded by users |
| `includes/avatars/` | `0750` | User avatar images |
| `includes/libraries/csrfp/log/` | `0750` | CSRF protection audit log |
| `websocket/logs/` *(if WebSocket enabled)* | `0750` | WebSocket server log files |

---

### Encryption key directory (SECUREPATH)

The Defuse encryption key must be stored **outside the web root** whenever possible.

| Path | Recommended perms | Notes |
|------|------------------|-------|
| `SECUREPATH/` | `0700` | Accessible only by the web server user |
| `SECUREPATH/<keyfile>` | `0600` | The key file itself — owner read-only |

> :warning: **Never place SECUREPATH inside the web root.** If that is unavoidable (e.g. shared hosting), use the `sk/` directory which is protected by its `.htaccess`. In Docker deployments, `/var/www/html/sk/` is used with `chmod 700`.

---

## Ownership

All files and directories must be owned by the user that runs the web server (PHP-FPM) process.

| Server stack | Typical user | Typical group |
|-------------|-------------|---------------|
| Apache + mod_php | `www-data` | `www-data` |
| Apache + PHP-FPM | `www-data` | `www-data` |
| Nginx + PHP-FPM | `www-data` (Debian/Ubuntu) / `nginx` (RHEL/Alpine) | same |
| Docker (official image) | `nginx` | `nginx` |

### Advanced: separate owner from web server

For higher security, you can own files with a dedicated non-login account (`teampass`) and give the web server group read access:

```
owner : teampass   (non-login system account)
group : www-data   (web server process)
```

With this model:
- PHP code cannot overwrite itself (web server has no write permission on code files)
- Writable runtime directories (`files/`, `upload/`, etc.) are owned by `teampass:www-data` with `0770`

This prevents a compromised PHP process from modifying application files.

---

## Quick-setup commands

Replace `/var/www/html/teampass` with your actual installation path and `www-data` with your web server user.

### Standard install (Apache/Nginx on Debian/Ubuntu)

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=www-data

# Ownership
sudo chown -R ${WEB_USER}:${WEB_USER} ${TEAMPASS}

# Application code: read-only for web server
sudo find ${TEAMPASS} -type d -not -path "*/files*" -not -path "*/upload*" \
    -not -path "*/includes/avatars*" -not -path "*/includes/libraries/csrfp/log*" \
    -exec chmod 0750 {} \;
sudo find ${TEAMPASS} -type f -not -path "*/files/*" -not -path "*/upload/*" \
    -exec chmod 0640 {} \;

# Runtime-writable directories
sudo chmod 0750 ${TEAMPASS}/files
sudo chmod 0750 ${TEAMPASS}/upload
sudo chmod 0750 ${TEAMPASS}/includes/avatars
sudo chmod 0750 ${TEAMPASS}/includes/libraries/csrfp/log

# Encryption key directory (outside web root is strongly preferred)
sudo chmod 0700 /var/opt/teampass/sk
sudo chmod 0600 /var/opt/teampass/sk/*
```

### RHEL / AlmaLinux / Rocky Linux (SELinux environments)

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=apache   # or nginx, depending on your setup

sudo chown -R ${WEB_USER}:${WEB_USER} ${TEAMPASS}

# Same chmod rules as above, then fix SELinux context:
sudo semanage fcontext -a -t httpd_sys_rw_content_t "${TEAMPASS}/files(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "${TEAMPASS}/upload(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "${TEAMPASS}/includes/avatars(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t \
    "${TEAMPASS}/includes/libraries/csrfp/log(/.*)?"
sudo restorecon -Rv ${TEAMPASS}
```

### Docker

Permissions are set automatically by the Docker entrypoint. No manual action is required.

The image enforces:

| Path | Permissions | Owner |
|------|------------|-------|
| `sk/` | `700` | `nginx:nginx` |
| `files/` | `750` | `nginx:nginx` |
| `upload/` | `750` | `nginx:nginx` |
| `includes/libraries/csrfp/log/` | `750` | `nginx:nginx` |

---

## After installation: locking down the installer

Once Teampass is running, prevent access to the install directory at the web server level.

### Apache

```apache
<Directory /var/www/html/teampass/install>
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

Alternatively, remove the `install/` directory entirely:

```bash
sudo rm -rf /var/www/html/teampass/install
```

> :warning: You will need to restore this directory from the release archive before running a future upgrade.

---

## Verification checklist

Run this after installation or after an upgrade to confirm the permission state:

```bash
TEAMPASS=/var/www/html/teampass

echo "=== Writable runtime dirs (expect 750) ==="
stat -c "%a %n" ${TEAMPASS}/files ${TEAMPASS}/upload \
    ${TEAMPASS}/includes/avatars \
    ${TEAMPASS}/includes/libraries/csrfp/log

echo "=== Config dir (expect 750 or 550 post-install) ==="
stat -c "%a %n" ${TEAMPASS}/includes/config

echo "=== settings.php (expect 640 or 440 post-install) ==="
stat -c "%a %n" ${TEAMPASS}/app/config/settings.php

echo "=== Encryption key dir (expect 700) ==="
stat -c "%a %n" $(php -r "include '${TEAMPASS}/app/config/settings.php'; echo SECUREPATH;")

echo "=== World-writable check (expect no output) ==="
find ${TEAMPASS} -not -path "*/vendor/*" -perm -o+w -ls
```

---

## Summary table

| Directory | Install/Upgrade | Runtime | Recommended perms |
|-----------|:--------------:|:-------:|:-----------------:|
| `/` (app root) | Write | Read | `0750` / `0640` |
| `files/` | Write | Write | `0750` |
| `upload/` | — | Write | `0750` |
| `includes/config/` | Write | Read | `0750` → `0550` after install |
| `includes/config/settings.php` | Write | Read | `0640` → `0440` after install |
| `includes/avatars/` | — | Write | `0750` |
| `includes/libraries/csrfp/libs/` | Write | Read | `0750` → `0550` after install |
| `includes/libraries/csrfp/log/` | — | Write | `0750` |
| `install/` | Read | **None** | Remove or deny via web server |
| `SECUREPATH/` | Write | Read | `0700` |
| `SECUREPATH/<keyfile>` | Write | Read | `0600` |
| `websocket/logs/` | — | Write (daemon) | `0750` |
| `vendor/` | — | Read | `0750` / `0640` |
