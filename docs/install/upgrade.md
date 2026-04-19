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

#### Using Git (recommended)

```bash
cd /path/to/teampass
git pull
```

#### Manual — zip package

* Download the latest release from [Teampass releases](https://github.com/nilsteampassnet/TeamPass/releases/latest)
* Unzip and overwrite the existing files in the Teampass folder

#### Install Composer dependencies

```bash
cd /path/to/teampass
composer install --no-dev --optimize-autoloader
```

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
| `--dry-run`          | Show what would be done without making any change        |
| `--web-user=USER`    | Web server user for permission setup (default: `www-data`) |
| `--no-color`         | Disable ANSI colour output                               |

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

### Step 5 — Update Apache / Nginx configuration (3.2.0 only)

If upgrading from 3.1.x, update your virtual host so `DocumentRoot` points to the `public/` subdirectory:

**Apache example:**
```apache
DocumentRoot /var/www/html/teampass/public
<Directory /var/www/html/teampass/public>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx example:**
```nginx
root /var/www/html/teampass/public;
```

Reload your web server after making the change.

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
