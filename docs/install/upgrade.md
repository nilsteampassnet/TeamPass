<!-- docs/install/upgrade.md -->

## Upgrading to version 3.2.x

> You want to upgrade your current Teampass installation to the latest 3.2.x release.

### Overview of 3.2.0 directory layout change

TeamPass 3.2.0 reorganises the codebase into separate trees. Your web server's `DocumentRoot` must now point to `public/`, not to the project root.

| Directory   | Purpose                                                        | Web-accessible |
|-------------|----------------------------------------------------------------|----------------|
| `app/`      | Application source code, configuration, Composer dependencies  | No             |
| `public/`   | Webroot — Apache/Nginx `DocumentRoot` must point here          | Yes            |
| `storage/`  | Runtime data: uploads, task files, SQL backups                 | No             |
| `secrets/`  | Encryption key file (`teampass-seckey.txt`)                    | No             |

---

### Step 1 — Back up your instance

Before anything else:

1. Put your Teampass instance in **Maintenance mode** (Admin → Utilities → Maintenance)
2. Create a **database dump**
3. Create a **zip archive** of the entire Teampass folder
4. Clear your browser cache (`Ctrl + F5`)

---

### Step 2 — Get the new code

> **Recommended for 3.1.x → 3.2.0 upgrades: use the release archive (Option A).**
>
> `git pull` removes files that were deleted from the repository between 3.1.x and 3.2.0
> (language files, library stubs, template files, etc.).  Your **user data** is safe because
> it lives in gitignored directories (`files/`, `upload/`, `backups/`, `includes/config/`),
> but the deletion of other tracked files can leave the repository in an inconsistent state
> that is harder to reason about.
>
> Extracting the release archive over the existing installation is simpler and unambiguous:
> it **adds** new files and **overwrites** existing ones without deleting anything, so the
> filesystem migration script (Step 3) always finds what it expects.

#### Option A — Release archive (recommended)

1. Download the latest release from [TeamPass releases](https://github.com/nilsteampassnet/TeamPass/releases/latest)
2. Extract the archive **over** your existing TeamPass folder:

```bash
cd /var/www/html   # parent of your TeamPass folder

# Download (adjust version and folder name as needed)
wget https://github.com/nilsteampassnet/TeamPass/archive/refs/tags/3.2.0.zip

# Extract to a temp directory, then rsync into place
unzip -q 3.2.0.zip -d /tmp/tp-new
rsync -av --no-perms /tmp/tp-new/TeamPass-3.2.0/ teampass/

# Clean up
rm -rf /tmp/tp-new 3.2.0.zip
```

> `rsync` copies new and updated files without touching your data directories.
> Any old 3.1.x code files that were removed from the repository will simply remain
> on disk — they are harmless because after Step 3 the web server DocumentRoot will
> point to `public/`, leaving the old root-level code outside the webroot.

#### Option B — Git (existing git-based deployments)

```bash
cd /path/to/teampass
git pull
```

> **Important:** `git pull` will delete files that were removed from the repository.
> Your user data is safe only if all data directories (`files/`, `upload/`, `backups/`,
> `includes/config/`, `includes/avatars/`) are listed in `.gitignore`.
> If you have any doubt, use Option A instead.

---

### Step 3 — Run the filesystem migration script (3.2.0 only)

> **Required when upgrading from any version earlier than 3.2.0 (including all 3.1.x releases).**
> Skip this step if you are already on 3.2.x.

TeamPass 3.2.0 moves user data from the old flat layout to the new `app/` / `storage/` structure.
This migration **must** be done from the command line before the web-based upgrade wizard is launched.

```bash
cd /path/to/teampass
php migrate_3.2.x.php
```

**Available options:**

| Option               | Description                                              |
|----------------------|----------------------------------------------------------|
| `--check`            | Inspect prerequisites and show what will be migrated, without making any change |
| `--dry-run`          | Simulate every step in detail without making any change  |
| `--web-user=USER`    | Web server user for permission setup (default: `www-data`) |
| `--no-color`         | Disable ANSI colour output                               |

> **Tip:** Run `--check` first for a quick pre-flight summary, then `--dry-run` for a
> full step-by-step simulation, and finally run without flags to apply the migration.

**What the script does:**

1. Moves `includes/config/settings.php` → `app/config/settings.php`
2. Moves `files/` → `storage/files/`
3. Moves `upload/` → `storage/upload/`
4. Moves `backups/` → `storage/backups/`
5. Copies user avatars to `public/assets/avatars/`
6. Adjusts file ownership and permissions for `www-data`

> **Tip:** Run with `--dry-run` first to preview all operations, then run again without the flag to apply them.

> **Important:** If the upgrade page detects that the database version is still below 3.2.0 while the code is already at 3.2.0, it will display a blocking warning and refuse to start until this script has been executed.

Once the script completes successfully, refresh the upgrade page and proceed to Step 4.

---

### Step 4 — Run the web-based upgrade wizard

* Browse to `https://<your_teampass_instance>/install/upgrade.php`
* Authenticate with your **Administrator** account
* Follow all wizard steps

---

### Step 4b — Restart WebSocket daemon (if WebSocket is enabled)

> Skip this step if the WebSocket feature is disabled in your instance
> (Admin → Settings → WebSocket → `websocket_enabled = 0`).

Any upgrade that modifies files under `app/websocket/src/` requires a daemon restart.
Without it, the running process continues to use the old code and silently ignores new
client actions (e.g., `start_item_view`).

```bash
sudo systemctl restart teampass-websocket.service
```

Check that the service restarted cleanly:

```bash
sudo systemctl status teampass-websocket.service
```

**Hard browser refresh recommended** if the application version number is unchanged between
releases, because `teampass-websocket-init.js` is cached using the version as a query string.
Use `Ctrl + Shift + R` (or `Cmd + Shift + R` on macOS) to bypass the cache.

---

### Step 5 — Update Apache / Nginx configuration (3.2.0 only)

If upgrading from 3.1.x, update your virtual host so `DocumentRoot` points to the `public/` subdirectory.

**Apache example:**

```apache
DocumentRoot /var/www/html/teampass/public
<Directory /var/www/html/teampass/public>
    AllowOverride All
    Require all granted
</Directory>
```

Enable `mod_rewrite` if not already active:

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

> :warning: **`AllowOverride All` is required.** TeamPass ships `.htaccess` files that handle URL rewriting, PHP execution control, and access restrictions. Without it, these rules are silently ignored.

**Nginx example:**

The Nginx configuration must route requests for the web UI through `index.php` and API requests through `api/index.php`. It must also block direct access to `core.php`.

```nginx
server {
    listen 443 ssl;
    server_name teampass.yourdomain.com;
    root /var/www/html/teampass/public;
    index index.php;

    # Block direct access to the bootstrap include
    location ~* /core\.php$ {
        deny all;
        return 403;
    }

    # API — route through the API front controller
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    # Web UI — route through the main front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Subdirectory install (Apache Alias)**

If TeamPass is served from a sub-path such as `https://example.com/teampass/`, adjust `RewriteBase` in `public/.htaccess`:

```apache
RewriteBase /teampass/
```

Reload your web server after making the change.

---

### Step 5b — Lock down the install directory

After the upgrade wizard completes, block HTTP access to `public/install/`.

**Apache** — uncomment the deny directive in `public/install/.htaccess`:

```apache
Require all denied
```

**Nginx** — add a location block before the catch-all:

```nginx
location /install/ {
    deny all;
    return 403;
}
```

> :warning: Leaving the install directory accessible on a running instance exposes upgrade scripts to unauthenticated users.

---

## Upgrading from 2.x or 3.0.x

> You want to migrate a legacy Teampass v2 or early v3 installation to the current version.

### Prerequisites

* Set your Teampass instance in `Maintenance` mode
* Your current Teampass instance is 2.1.27.36 or a 3.0.x release
* Perform a **database backup**
* Save the main folder

### Steps

1. Rename the current Teampass folder (e.g. `teampass_old`)
2. Download the latest release from [Teampass releases](https://github.com/nilsteampassnet/TeamPass/releases/latest)
3. Unzip and place the new folder at the same web path (e.g. `teampass`)
4. Copy the following files from the old folder to the new one:

```
# Old path (2.x / 3.0.x)      →  New path (3.2.x)
./includes/config/settings.php →  ./app/config/settings.php
./includes/libraries/csrfp/libs/csrfp.config.php
                                →  ./app/includes/libraries/csrfp/libs/csrfp.config.php
./includes/teampass-seckey.txt  →  ./secrets/teampass-seckey.txt
./assets/avatars/*              →  ./public/assets/avatars/
./files/*                       →  ./storage/files/
./upload/*                      →  ./storage/upload/
```

5. Edit `./app/config/settings.php`:
   * Set `define("DB_ENCODING", "utf8mb4");`
   * Add `define('SECUREFILE', 'teampass-seckey.txt');` if missing

6. Ensure writable directories have correct permissions (see [installation guide](install/installation.md))

7. Browse to `https://<your_teampass_instance>/install/upgrade.php` and run all steps

8. After the wizard completes, lock down the install directory (see [Step 5b](#step-5b--lock-down-the-install-directory) above).

#### How it works

The password encryption library changed in v3. On first login after the upgrade, all item keys are migrated to the new encryption library automatically.

The background task system also changed. After authenticating with the **Admin** account, check the Tasks parameters page and adjust `task_maximum_run_time` if a warning is displayed with a suggested value.

---

## Known issues

### Undefined constant "SECUREFILE"

```
Fatal error: Uncaught Error: Undefined constant "SECUREFILE" in .../sources/main.functions.php
```

* Open `./app/config/settings.php`
* After the `define("TEAMPASS_SECRETS", "...");` line, add:
  ```php
  define("SECUREFILE", "teampass-seckey.txt");
  ```

### Unknown column 'created_at' in 'SELECT'

```
Fatal error: Uncaught MeekroDBException: Unknown column 'created_at' in 'SELECT'
```

Connect to MySQL and run:

```sql
ALTER TABLE teampass_misc ADD COLUMN created_at VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE teampass_misc ADD COLUMN updated_at VARCHAR(255) NULL DEFAULT NULL;
```

### Background task timeout

The default background task timeout is 600 seconds. Adjust it to match your data volume:

```sql
UPDATE `teampass_misc` SET `valeur` = '<YOUR_VALUE>' WHERE `intitule` = 'task_maximum_run_time';
```
