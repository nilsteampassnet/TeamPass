<!-- docs/install/file-permissions.md -->

## File system permissions

Correct file system permissions are critical for a password manager. This page defines the minimal permission set required by Teampass 3.2.x, explains the rationale for each directory, and provides ready-to-use commands for the most common server configurations.

The upgrade wizard (**Step 1 — Requirements check**) verifies the paths it needs for an upgrade and blocks when a required one is not writable or readable. The File integrity permission audit checks the broader **normal-runtime** posture, including background-task logs and whether PHP can modify application code. These are different phases: temporarily unlock installation paths for the wizard, then restore the hardened runtime model afterwards.

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
│   ├── logs/                 ← required writable (background tasks and integrity reports)
│   ├── files/                ← required writable
│   ├── upload/               ← optional writable (file attachments)
│   └── backups/              ← optional writable (SQL dumps)
└── secrets/                  ← encryption key  (readable by web server, NOT in webroot)
    └── <SECUREFILE>          ← random file name chosen by the installer
```

> :information_source: **The key file name is random.** The installer generates it and stores it in the `SECUREFILE` constant of `app/config/settings.php`. `teampass-seckey.txt` is only the legacy name still found on installs upgraded from older versions. To resolve it on your server:
> ```bash
> sudo -u www-data php -r 'require $argv[1]; echo SECUREFILE, PHP_EOL;' /path/to/teampass/app/config/settings.php
> ```
> Replace `www-data` with the account that runs PHP. This prints only the file name, never the key contents, and accepts either quote style in `settings.php`.

---

## Principles

Teampass follows the **principle of least privilege**: each path is granted only the minimum access it genuinely needs.

| Notation | Meaning |
|----------|---------|
| `0755` (dir) / `0644` (files) | Owner can write, group and others read-only — code with a **non-web owner** |
| `0750` (dir) / `0640` (files) | Owner can write, group read-only, others none — hardened code/configuration and private runtime data |
| `0700` (dir) / `0600` (files) | Owner only — encryption key in the simple ownership model |

The owner decides who can write: the generated runtime plan gives code/configuration to a non-web owner with the PHP group, runtime data to the PHP account, and secrets to `root` with the PHP group. Preserved executable code files keep execute permission (`0750` rather than `0640`). Modes alone do not prove that PHP has read-only access.

> :warning: **Do not use `0777` or `0775` as a permission fix.** `0777` allows every system user to write. `0775` grants group write and exposes directory contents to other users. Keep code non-writable by PHP and runtime data private.

---

## Directory reference

### Must NOT be writable by the web server

The upgrade wizard raises a warning (non-blocking) when these directories are writable — it indicates a configuration weakness that should be corrected.

> :information_source: **Why `0755` alone does not clear this warning.** `0755` grants the *owner* full `rwx`. If the owner **is** the PHP account (`www-data`), PHP can still write. Use the hardened split-owner model — a non-web owner and group read/execute access for PHP — on the code, its directories and the project root. Changing just the `app/` directory node does not protect its writable descendants, and removing owner-write alone is not a boundary: an owner can restore that bit with `chmod`. The upgrade warning is non-blocking; the normal-runtime integrity audit also checks the descendants.

| Directory | Recommended perms | Rationale |
|-----------|------------------|-----------|
| `app/` | `0750` dir / `0640` files | Non-web owner, PHP group read-only; explicit runtime subdirectories are writable |
| `public/` | `0750` dir / `0640` files | Non-web owner; avatar uploads are the explicit writable exception |
| `app/vendor/` | `0750` / `0640` | Composer dependencies — installed at deploy time only |

`0755`/`0644` with a non-web owner also works for non-sensitive code. The generated plan uses `0750`/`0640` to remove access for unrelated accounts. If the HTTP server and PHP use different accounts, ensure both can read the served files and traverse their parent directories through the intended group.

---

### Writable during install/upgrade only

These paths are written by the web installer or upgrade wizard. Between runs, they should revert to read-only for hardened deployments.

| Path | Recommended perms | What is written |
|------|------------------|-----------------|
| `app/config/` | `0750` (dir), owned by web server user | Contains `settings.php` |
| `app/config/settings.php` | `0640`, owned by web server user | Encrypted DB credentials, `TEAMPASS_SECRETS` path |
| `app/includes/libraries/csrfp/libs/` | `0750`, owned by web server user | `csrfp.config.php` (CSRF token configuration) |

> :bulb: **Hardening tip:** after the wizard completes, restore a non-web owner with PHP group read access using the [normal-runtime repair plan](#quick-setup-commands). This protects the configuration without relying on `0550`/`0440` owned by PHP, which PHP could change back. Before the next wizard run, use the [temporary installation/upgrade access](#temporary-installupgrade-write-access) below, then restore the runtime plan again.

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

**Required** means the normal-runtime permission audit expects the directory to exist and be writable. The wizard checks most of these paths, but does not explicitly check `storage/logs/` or WebSocket logs. Optional directories are audited when present; the corresponding feature still needs write access when used. See the [summary table](#summary-table) for wizard checks.

---

### Encryption key (TEAMPASS_SECRETS)

The Defuse encryption master key must be stored **outside the webroot** (`public/`) whenever possible.

| Path | Recommended perms | Notes |
|------|------------------|-------|
| `secrets/` | `0750`, `root:PHP-group` | Hardened model: PHP can read/traverse, but cannot create, rename or delete files |
| `secrets/<SECUREFILE>` | `0640`, `root:PHP-group` | Hardened model: PHP can read the key, but cannot rewrite it or change its mode |
| Simple model / official Docker image | Directory `0700`, key `0600`, owned by PHP | Owner-only access; PHP owns the key and can change it |

> :warning: **`secrets/` must not be inside `public/`.** It lives at the project root, one level above the webroot, and is therefore unreachable via HTTP. In Docker deployments the key is stored at `/var/www/html/secrets/`.

> :bulb: **Owner matters more than `0600` versus `0400`.** At runtime Teampass only *reads* the key. A PHP-owned key in `0400` can still be changed back to `0600` by PHP. A root-owned key in `0600`, conversely, cannot be read by a non-root PHP account: use `0640` with its private group for the split-owner model. Write access is needed when the installer *creates* the key, and when the upgrade wizard migrates a legacy `teampass-seckey.txt` (copy + rename) — the latter needs write on the **directory**, not on the file.
>
> For real defense-in-depth, apply the hardened split-owner model to the key as well — the web server can then read the key but never rewrite or replace it:
> ```bash
> TEAMPASS=/var/www/html/teampass
> WEB_USER=www-data
> WEB_GROUP=$(id -gn "$WEB_USER")
> SECKEY=$(sudo -u "$WEB_USER" php -r 'require $argv[1]; echo SECUREFILE;' "$TEAMPASS/app/config/settings.php")
>
> if [ -n "$SECKEY" ] && [ "$SECKEY" = "$(basename -- "$SECKEY")" ] \
>     && [ -f "$TEAMPASS/secrets/$SECKEY" ] \
>     && [ ! -L "$TEAMPASS/secrets" ] && [ ! -L "$TEAMPASS/secrets/$SECKEY" ]; then
>     sudo chown -- "root:$WEB_GROUP" "$TEAMPASS/secrets" "$TEAMPASS/secrets/$SECKEY" \
>         && sudo chmod 0750 -- "$TEAMPASS/secrets" \
>         && sudo chmod 0640 -- "$TEAMPASS/secrets/$SECKEY"
> else
>     printf '%s\n' 'Key path unresolved or symlinked: stop and review it manually.'
> fi
> ```
> Unlock the directory only for planned key creation or migration. Never re-run the installer to repair an existing instance.

---

## Ownership

Two ownership models work, but the generated normal-runtime repair plan deliberately implements the hardened model. Installation and upgrade need temporary write access; Docker has its own entrypoint policy.

**Simple model — everything owned by the web server user** (`www-data:www-data`)
The easiest setup: every path is owned by the account that runs PHP (see the table below). It works, but because the web server *is* the owner, `app/` and `public/` stay writable by it even at `0755`, so the upgrade wizard shows a **non-blocking** "should not be writable" warning for those two paths (see [Must NOT be writable by the web server](#must-not-be-writable-by-the-web-server)).

**Hardened model — code owned by a separate account** (recommended)
Own the application code with `root` or a dedicated deployment account and give PHP only group *read/execute* access, so a compromised PHP process cannot rewrite its own code. Keep the project root non-writable by PHP as well, to prevent replacement of protected directories. This is the model the "must NOT be writable" checks expect, and it clears the warning.

The table below gives common defaults, not a guarantee: check the actual PHP-FPM pool, Apache process or container identity. The PHP group may differ from the user name; `id -gn USER` shows its primary group. Run the diagnostic CLI as that account, not as your deployment user. A root CLI scan falls back to `www-data` on Debian/Ubuntu or `apache` on RHEL and cannot infer a custom pool account.

| Server stack | Web server user | Web server group |
|-------------|-------------|---------------|
| Apache + mod_php | `www-data` | `www-data` |
| Apache + PHP-FPM | `www-data` | `www-data` |
| Nginx + PHP-FPM (Debian/Ubuntu) | `www-data` | `www-data` |
| Nginx + PHP-FPM (RHEL/Alpine) | `nginx` | `nginx` |
| Docker (official image) | `nginx` | `nginx` |

### Advanced: separate owner from web server user

For example, a dedicated deployment account (`teampass`) can own the protected files with the PHP group:

```
owner : teampass   (non-login system account)
group : www-data   (web server process)
```

With this model:

- PHP code cannot overwrite itself (web server has no write permission on code files)
- The generated plan preserves the existing non-web owner of the project root, or uses `root` when the root was owned by PHP
- Writable runtime directories are owned by the PHP account with `0750`; their files use `0640`
- Secrets are owned by `root` with the PHP group, using `0750`/`0640`

Giving a trusted PHP group runtime write access with `0770` is another workable model, but is not what the generated plan applies. Never give that group write access to code or secrets. POSIX modes do not override ACLs, SELinux, read-only mounts or NFS ownership restrictions; review those separately.

---

## Quick-setup commands

For a **new installation**, start with [Installation → Set folder permissions](installation.md#set-folder-permissions), run the wizard, then apply the normal-runtime plan below. Do not run the installer again to repair an existing instance: it may replace its encryption key.

For an **existing installation**, use the detected repair plan instead of recursively giving the whole tree to PHP. Replace the path and account below with the actual ones. The plan is intended for a normal writable deployment, not an immutable container image or a custom symlink layout.

### Normal runtime (Debian / Ubuntu)

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=www-data

# Refresh the saved report, including runtime file contents.
# Exit code 2 means findings require review, not that the scan failed.
sudo -u "$WEB_USER" php "$TEAMPASS/app/scripts/file_integrity.php" --deep-permissions

# Print the proposed sudo commands; this does not execute them.
sudo -u "$WEB_USER" php "$TEAMPASS/app/scripts/file_integrity.php" --permissions-plan
```

Review the identity, owner/group, paths and mount layout in that output. Stop application writes before applying the selected commands yourself, then run a new scan as the PHP account. Do not pipe the output into a shell. The plan restores the entire documented permission model, not just the paths sampled in the report, and its runtime-file mode is `0640` (existing owner-only files may become readable by the selected private group).

If the PHP account cannot even run the scan, first restore its read/traverse access using the installation guide. With the conventional distribution account only, a root CLI scan can instead produce the plan; inspect the reported identity before applying it. A root-generated report may itself need its log ownership repaired before the PHP account can refresh it.

### Temporary install/upgrade write access

The web wizard needs write access to the configuration nodes below. This is **temporary**, not the normal-runtime posture. The commands assume the standard layout with no symlinked configuration directories; use the Docker entrypoint or manually review custom targets instead.

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=www-data
WEB_GROUP=$(id -gn "$WEB_USER")

sudo install -d -o "$WEB_USER" -g "$WEB_GROUP" -m 0750 -- \
    "$TEAMPASS/app/config" \
    "$TEAMPASS/app/includes/libraries/csrfp/libs"

for path in app/config/settings.php app/includes/libraries/csrfp/libs/csrfp.config.php; do
    if [ -f "$TEAMPASS/$path" ] && [ ! -L "$TEAMPASS/$path" ]; then
        sudo chown -- "$WEB_USER:$WEB_GROUP" "$TEAMPASS/$path"
        sudo chmod 0640 -- "$TEAMPASS/$path"
    fi
done
```

For initial key creation, or an upgrade that explicitly needs to migrate a legacy key, temporarily give the PHP account ownership of the real `secrets/` directory with `0700`. Do not overwrite, delete or regenerate an existing key. After the wizard completes, restore the **normal-runtime plan**, including root ownership and PHP group read access on secrets, then block `public/install/` again.

### RHEL / AlmaLinux / Rocky Linux (SELinux environments)

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=apache   # or nginx, depending on your setup

sudo -u "$WEB_USER" php "$TEAMPASS/app/scripts/file_integrity.php" --deep-permissions
sudo -u "$WEB_USER" php "$TEAMPASS/app/scripts/file_integrity.php" --permissions-plan
```

The generated plan uses the selected PHP user/group and proposes `httpd_sys_rw_content_t` for runtime paths, including WebSocket logs when present. It adds or updates the context rule when `semanage` is available, then runs `restorecon`. This does not install SELinux tools or configure the read-only contexts for code and secrets on a custom deployment path: those must already be correct. POSIX permissions alone cannot resolve a SELinux denial.

### Docker

The official Docker entrypoint sets its own volume permissions at each start. Use the container's `nginx` account, never the Debian/Ubuntu host's `www-data`. The image uses the simple ownership model, not the split-owner hardened plan: code owned by `nginx` can legitimately trigger the audit's writable-code warnings. Alpine supports scanning but deliberately receives no generated Debian/RHEL repair commands. A custom non-root image or RWX volume can also impose ownership restrictions that the entrypoint cannot repair.

The image enforces:

| Path | Permissions | Owner |
|------|------------|-------|
| `secrets/` | `700` | `nginx:nginx` |
| `secrets/<SECUREFILE>` | `600` when created by the installer | PHP account; not reset recursively by the entrypoint |
| `storage/` | `750` | `nginx:nginx` |
| `storage/sk/` | `700` | `nginx:nginx` |
| `storage/config/` | `750` | `nginx:nginx` |
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

Run this after installation or after an upgrade, **once runtime hardening is restored**. These commands only inspect access. A valid split-owner installation uses `0750`/`0640` with a non-web owner; `0755`/`0644` can also be read-only for PHP. A PHP-owned key in `0600` is readable but does not satisfy the split-owner no-write check.

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=www-data

echo '=== Protected paths: PHP must read, but not write ==='
for path in "$TEAMPASS" "$TEAMPASS/app" "$TEAMPASS/public" \
    "$TEAMPASS/app/config" "$TEAMPASS/app/config/settings.php" \
    "$TEAMPASS/app/includes/libraries/csrfp/libs" \
    "$TEAMPASS/app/includes/libraries/csrfp/libs/csrfp.config.php" "$TEAMPASS/secrets"; do
    stat -Lc '%a %U:%G %n' -- "$path"
    if sudo -u "$WEB_USER" test -r "$path" \
        && ! sudo -u "$WEB_USER" test -w "$path" \
        && { [ ! -d "$path" ] || sudo -u "$WEB_USER" test -x "$path"; }; then
        printf 'OK: %s\n' "$path"
    else
        printf 'REVIEW: %s\n' "$path"
    fi
done

echo '=== Key: inspect ownership and effective read-only access, not just 600 ==='
if SECKEY=$(sudo -u "$WEB_USER" php -r 'require $argv[1]; echo SECUREFILE;' "$TEAMPASS/app/config/settings.php") \
    && [ -n "$SECKEY" ] && [ "$SECKEY" = "$(basename -- "$SECKEY")" ]; then
    stat -Lc '%a %U:%G %n' -- "$TEAMPASS/secrets/$SECKEY"
    if sudo -u "$WEB_USER" test -r "$TEAMPASS/secrets/$SECKEY" \
        && ! sudo -u "$WEB_USER" test -w "$TEAMPASS/secrets/$SECKEY"; then
        echo 'OK: key readable but not writable by PHP'
    else
        echo 'REVIEW: key access/ownership'
    fi
else
    echo 'REVIEW: cannot resolve the key file name'
fi

echo '=== Runtime directories: PHP needs read, write and traverse access ==='
for path in storage storage/logs storage/files storage/upload storage/backups \
    public/assets/avatars app/includes/libraries/csrfp/log app/websocket/logs; do
    if [ -d "$TEAMPASS/$path" ]; then
        stat -Lc '%a %U:%G %n' -- "$TEAMPASS/$path"
        if sudo -u "$WEB_USER" test -r "$TEAMPASS/$path" \
            && sudo -u "$WEB_USER" test -w "$TEAMPASS/$path" \
            && sudo -u "$WEB_USER" test -x "$TEAMPASS/$path"; then
            printf 'OK: %s\n' "$path"
        else
            printf 'REVIEW: %s\n' "$path"
        fi
    else
        printf 'ABSENT: %s (check required paths and enabled features)\n' "$path"
    fi
done

# Also audit code descendants, unsafe modes and runtime file contents.
sudo -u "$WEB_USER" php "$TEAMPASS/app/scripts/file_integrity.php" --deep-permissions
```

> :information_source: The permission audit ignores symlink modes (`0777` on Linux) and does not follow links. The checklist's `stat -L` and access tests inspect the listed configuration targets; manually review custom targets outside the scanned tree. CLI access tests do not establish that the web process has the correct SELinux context.

---

## File integrity verification

**Utilities → System Health → File integrity** starts a read-only background scan. Detailed findings are stored in `storage/logs/file-integrity-report.json`, while the Dashboard and Health polling read the bounded `storage/logs/file-integrity-summary.json`. Both files carry the same scan identifier, and detailed findings are rejected if the identifiers do not match.

The background-task lock and trigger, the file-integrity scan/enqueue locks and
the optional task journal (`LOG_TASKS_FILE`, normally `storage/logs/teampass_tasks.log`)
are runtime files, not release-manifest entries. TeamPass attempts to restrict their
POSIX permissions to `0640` or `0600` whenever it opens them for writing, including
when a deleted file is recreated. Existing `0600` modes are preserved and owner
read/write access is ensured; the process umask is not changed.

Locks and signals continue with existing access, with a warning, if opening
succeeds but `chmod` fails. The journal is stricter because task arguments may be
sensitive: after attempted repair it refuses any POSIX access for other users
(`mode & 0007`). A group-writable `0660` journal owned by another account remains
usable when `chmod` is denied; a `0664` journal does not. An invalid target or an
actual open/write failure still prevents that operation. The permission scan
continues to report unsafe permissions and access problems. No manual manifest
update or permission-scan exclusion is needed.

The journal is written only when `enable_tasks_log` is on. Absolute custom paths
are used as-is; legacy relative paths resolve against `app/scripts/`. An empty
log path retains the explicitly configured PHP error-log destination. A failed
configured destination produces one diagnostic per process, without copying the
lost entry into another log. Further writes are still attempted, so logging resumes
if the destination is repaired; background processing is not stopped by a log failure.
Concurrent appends preserve existing entries. Identity and permissions are checked
again after waiting for the lock. If rotation replaced the journal during that wait,
the writer reopens and retries once, before writing anything. Rotation must preserve
the runtime owner/group and restricted permissions; this is not an atomic guarantee
against arbitrary external replacements.

Runtime directories must not be writable by untrusted users. Descriptor/path
identity checks detect observed file replacements, but do not make path-based
`chmod` atomic. Signal writers never wait on a competing producer in a web request;
contention is distinguished from an I/O error. The scan-status probe opens existing
locks read-only and neither creates them nor changes their permissions.

The background-task lock is deleted by the handler that owns it, while still
holding the lock and only when the path still names its inode. After acquiring
a lock, the handler checks the same identity before writing its PID or starting
work: an already-open descriptor to a deleted file must not admit another handler.
A failed acquisition or repeated release cannot delete another handler's file.
After an abrupt stop, a leftover file is reusable when the next handler can open
it; file presence or an old PID alone does not indicate activity. Run manual
handlers as the normal background/web account: a root-owned leftover may require
an administrator to correct its ownership.

The scan compares protected files with `app/files_reference.txt` and reports separate categories for modified, missing, unknown, legacy-layout and Composer development files. Instance-owned data under `storage/`, `secrets/` and legacy runtime directories (`files/`, `upload/`, `backups/`) is excluded. Repository and development-only artifacts such as `.claude/`, `.github/`, `docs/`, `tests/` and their root tooling files are neutral: they do not affect integrity health, are not audited for runtime permissions and are not included in permission remediation. Any top-level hidden *directory* is treated the same way, so a tool directory added later is covered without updating the policy, and the same applies to repository metadata vendored inside dependencies (`app/vendor/*/.github/`, `.gitignore`, `.travis.yml`, `.editorconfig`, …). The release checksum generator consumes this same canonical policy, keeping those paths out of future manifests. This is an explicit path policy, not a blanket hidden-file exclusion; top-level hidden *files* stay in scope, and `.htaccess`, `.user.ini`, `.env*`, `.gitkeep`, `app/includes/.externals/`, Composer deployment metadata, Docker assets and application scripts remain protected. Ordinary avatar files are ignored, but executable files or symbolic links planted in the writable public avatar directory are reported as critical. A deliberately removed `public/install/` directory is accepted; when that directory exists, its files are fully checked. The reference manifest itself and generated configuration files are excluded from self-comparison.

The same background run also audits the effective POSIX permissions of TeamPass code and the runtime directory nodes. It checks the normal-runtime posture rather than the temporary upgrade posture:

- application code and the webroot must be readable but not writable by the PHP/web account;
- `storage/`, CSRF logs and the other documented runtime paths must remain readable and writable;
- `secrets/` must be readable without being exposed to other system users;
- symbolic-link modes are ignored because Linux enforces the target mode and reports links themselves as `0777`.

To keep the Health scan predictable on installations with many attachments or backups, it does not recursively descend into `storage/files/`, `storage/upload/`, `storage/backups/`, `public/assets/avatars/`, or their legacy root equivalents. Those directory nodes are still checked. Use the CLI `--deep-permissions` option when an exhaustive audit of their contents is required.

Repeated permission findings are grouped by reason and protected scope in the detailed report. Counters remain exact, while each group retains at most five representative paths so the JSON report stays bounded.

The web account is taken from the background process. If the CLI scan is run as root, the conventional account for the detected distribution is used instead, avoiding root-access false positives. The hardened repair plan preserves the current code owner when it is already different from the web account and otherwise falls back to `root`; runtime paths are returned to the detected web owner. Permission repair commands are generated only for Debian/Ubuntu and the common RHEL family (RHEL, Rocky Linux, AlmaLinux and CentOS). Other Linux distributions can be scanned, but deliberately receive no guessed remediation. RHEL-family guidance also restores SELinux `httpd_sys_rw_content_t` contexts on runtime paths when `semanage` is available.

Ownership and mode repairs use the same `find -P -xdev` traversal: neither follows
symbolic links or crosses into another filesystem below a starting point, and
both prune the shared repository metadata names. Every known runtime directory
remains an explicit starting point, including `storage/files/` and
`storage/upload/`, so their contents can be repaired even on separate filesystems.
Additional mount points are not recursively repaired; review them separately.
Runtime paths with a symbolic-link component, and symlinked secrets or legacy
data roots, are left for manual target review rather than creating or changing
directories through the link. These commands are not atomic: stop application
writes during repair and do not allow untrusted users to replace the paths.

The manifest is a **release artifact**. A checkout of the development branch can legitimately report files added or changed since the latest published manifest; production releases should ship with an updated manifest.

The same scanner is available from SSH:

```bash
TEAMPASS=/var/www/html/teampass

# Run a scan and persist the report (use the same account as background tasks).
sudo -u www-data php ${TEAMPASS}/app/scripts/file_integrity.php

# Include every attachment, backup and avatar in the permission audit.
sudo -u www-data php ${TEAMPASS}/app/scripts/file_integrity.php --deep-permissions

# Display the latest report without scanning again.
sudo -u www-data php ${TEAMPASS}/app/scripts/file_integrity.php --status
sudo -u www-data php ${TEAMPASS}/app/scripts/file_integrity.php --status --json

# Print reviewed cleanup guidance; this command does not move or delete anything.
sudo -u www-data php ${TEAMPASS}/app/scripts/file_integrity.php --cleanup-plan

# Print sudo-based permission remediation for the detected supported distribution.
sudo -u www-data php ${TEAMPASS}/app/scripts/file_integrity.php --permissions-plan
```

The command exits with `0` only for a clean result, `2` when findings or a stale report require review, and `1` when the scan itself cannot run.

There is deliberately no web action that deletes unknown files or changes permissions. In the hardened ownership model, PHP can read `app/` and `public/` but cannot modify them. The Health page therefore provides copyable, `sudo`-based SSH commands for an administrator to review and run separately.

Development dependencies are removed by the bundled offline CLI below, which uses `composer.lock` and the same deterministic logic as install/upgrade:

```bash
sudo php ${TEAMPASS}/app/scripts/cleanup_dev_dependencies.php
```

This replaces the former `composer install --no-dev --optimize-autoloader` cleanup suggestion. A production host no longer needs a globally installed Composer executable, network access or temporary write ownership on the application tree just to remove packages already identified in `packages-dev`.

---

## Troubleshooting

### Background tasks never run (dashboard shows "Delayed", PHP log shows "cannot create lock file")

**Symptoms**

- Admin dashboard → *System Health* → *Cron Jobs* shows **Delayed** (or **Error**). Hover the info icon next to the badge for the same hint.
- Tasks pile up in *Tasks → In progress* and only complete when the handler is launched by hand.
- The PHP / web-server error log contains one of:
  - `Teampass Background Tasks: cannot open a valid lock file ".../storage/logs/teampass_background_tasks.lock" - check that the web server user can write to this directory.`
  - `Teampass: cannot write background tasks trigger file ".../storage/logs/...".`

> **Task log fixed in 3.2.2.** Between the move to the `storage/` layout and this
> release, `enable_tasks_log` wrote nothing: the writer resolved the already
> absolute `LOG_TASKS_FILE` against `app/scripts/`, producing a path whose parent
> directory does not exist. An absolute value is now used verbatim, a legacy
> relative one still resolves against `app/scripts/`, and the log is created with
> the runtime permission policy above.

**Cause**

The web server user (for example `www-data`) cannot write to `storage/logs/`. The task handler writes its lock and trigger files there; if it cannot create the lock file it aborts immediately and **silently**, so no background task is processed. Running the handler from a shell as your own user appears to work because that user owns the directory — which is exactly why the problem is easy to miss.

**Fix**

For the standard, non-symlinked layout, restore the log directory and existing regular log/lock/report files to the actual PHP account. Review mounted paths and stop application writes first; do not delete active lock files.

```bash
TEAMPASS=/var/www/html/teampass
WEB_USER=www-data
WEB_GROUP=$(id -gn "$WEB_USER")
sudo install -d -o "$WEB_USER" -g "$WEB_GROUP" -m 0750 -- "$TEAMPASS/storage" "$TEAMPASS/storage/logs"
sudo find -P "$TEAMPASS/storage/logs" -xdev \( -type d -o -type f \) \
    -exec chown -h -- "$WEB_USER:$WEB_GROUP" {} +
sudo find -P "$TEAMPASS/storage/logs" -xdev -type d -exec chmod 0750 {} +
sudo find -P "$TEAMPASS/storage/logs" -xdev -type f -exec chmod 0640 {} +
```

Then confirm a background task completes — create or edit an item, or run the handler once
**as the web server user**:

```bash
sudo -u "$WEB_USER" php "$TEAMPASS/app/scripts/background_tasks___handler.php"
```

> The cron that runs `app/sources/scheduler.php` must also be configured (see [Tasks](../manage/tasks.md)). The event-driven trigger only covers tasks created by a web action; purely scheduled jobs (scheduled backups, inactive-user housekeeping, maintenance) rely on the cron.

---

## Summary table

| Path | Upgrade wizard check | Install/Upgrade | Runtime | Recommended perms |
|------|:--------------------:|:---------------:|:-------:|:-----------------:|
| `app/` | warning if writable | read | read | `0750` / `0640`, non-web owner |
| `public/` | warning if writable | read | read | `0750` / `0640`, non-web owner |
| `app/config/` | **required writable** | write | read | `0750`, PHP owner temporarily; non-web owner at runtime |
| `app/config/settings.php` | **required writable** | write | read | `0640`, PHP owner temporarily; non-web owner at runtime |
| `app/includes/libraries/csrfp/libs/` | **required writable** | write | read | `0750`, PHP owner temporarily; non-web owner at runtime |
| `app/includes/libraries/csrfp/log/` | **required writable** | write | write | `0750` |
| `app/vendor/` | — | read | read | `0750` / `0640`, non-web owner |
| `public/assets/avatars/` | optional writable | — | write | `0750` |
| `public/install/` | — | read | **none** | Remove or deny via web server |
| `storage/` | **required writable** | write | write | `0750` |
| `storage/logs/` | not explicitly checked; required by runtime audit | write | write | `0750` |
| `storage/files/` | **required writable** | write | write | `0750` |
| `storage/upload/` | optional writable | — | write | `0750` |
| `storage/backups/` | optional writable | write | write | `0750` |
| `secrets/` | **required readable** | write | read | Hardened: `0750`, `root:PHP-group` |
| `secrets/<SECUREFILE>` | Key loading requires readable | write | read | Hardened: `0640`, `root:PHP-group`; simple: `0600`, PHP owner |
| `app/websocket/logs/` *(WebSocket)* | — | — | write (daemon) | `0750` |

This table describes the hardened runtime plan. Non-sensitive code can also use `0755`/`0644` with a non-web owner. Runtime files use `0640`; preserved executable code files keep execute bits. The official Docker image uses PHP ownership with a `0700` secrets directory and installer-created `0600` key instead.
