<!-- docs/misc/troubleshooting.md -->

## Custom field shows "decryption failed" or appears empty

### Symptom

In the item detail view, one or more custom fields shows an empty value with a warning icon, or the tooltip mentions `decryption_failed` or `error_no_sharekey_yet`.

### Cause A — Keys are still being generated (`error_no_sharekey_yet`)

After an item is created or a user's keys are regenerated, the background task distributes sharekeys to all eligible users. Until that task completes, the field value is encrypted but the user's personal sharekey does not yet exist.

**Fix:** wait for the background task to complete, then refresh the page.

1. Go to **Admin → Tasks**.
2. Locate the key generation task for the relevant user or item.
3. Once the status is `done`, reload the item — the field value should appear.

### Cause B — Sharekey is missing or corrupted (`decryption_failed`)

The user's sharekey for this specific field exists but could not decrypt the value. Possible causes:

- The user's private key was regenerated after the item was last saved, and the re-encryption background task did not complete successfully.
- The field was encrypted with a legacy phpseclib v1 key and the migration is still in progress.

**Fix:**

1. Go to **Admin → Tasks** and check whether a key generation or migration task for this user is pending or failed.
2. If a task failed, check its error message and re-run the background task handler:
   ```bash
   php scripts/background_tasks___handler.php
   ```
3. If the problem persists for a specific user, go to **Admin → Users**, open the user, and use the **Re-encrypt** action to trigger a full sharekey rebuild.

### Cause C — Orphaned encrypted value (permanent data loss)

If an item was moved from a personal folder to a public folder while the encrypted field had no sharekey (due to a previous inconsistency), TeamPass deletes the field value during the move and logs an error. The value cannot be recovered.

**How to confirm:** search the application error log or the TeamPass log for an entry containing `orphaned row deleted during personal→public move` with the item ID.

**Fix:** re-enter the field value manually by editing the item.

---

## LDAP login shows "wrong passphrase" on first-time login

### Symptom
During first-time LDAP logins, users may see:
> "Wrong / not accepted passphrase"

and cannot proceed to the database key setup screen.

### Root cause
This can occur if the PHP-FPM pool is saturated or too small, causing POST requests (especially the passphrase submission) to time out.

**In php-fpm logs:**
`WARNING: [pool www] server reached pm.max_children setting (5), consider raising it`

**In Apache access logs:**
`HTTP 408 (Request Timeout) entries around the same time`

These timeouts make Teampass appear to reject the passphrase, while in fact the request was never processed.

### Solution

Tune your PHP-FPM pool settings. Edit `/etc/php/8.x/fpm/pool.d/www.conf`:

```
pm = dynamic
pm.max_children = 20      ; was 5
pm.start_servers = 4      ; was 2
pm.min_spare_servers = 4  ; was 1
pm.max_spare_servers = 10 ; was 3
pm.max_requests = 500     ; optional but recommended
```

Then reload:
```bash
systemctl reload php8.x-fpm
systemctl reload apache2
```

> **TL;DR** — If you see "wrong passphrase" but logs show FPM warnings or HTTP 408s: it is not a bad passphrase, it is PHP-FPM capacity. Increase pool size and try again.

---

## Page loads but items or folders do not appear

### Possible causes and fixes

**1. Session expired silently**
Reload the page. If you are redirected to the login page, your session expired. See [Session management](session-management.md).

**2. Folder tree not loading**
Open the browser console (F12 → Console). If you see JavaScript errors, clear your browser cache and reload.

**3. No access to any folder**
If the folder tree is empty after login, your account has no role assigned. Ask an administrator to assign a role with folder access (see [Users](../features/users.md)).

**4. APCu cache serving stale settings**
If a setting change is not reflected, restart PHP-FPM to clear the 60-second APCu cache: `systemctl reload php8.x-fpm`.

---

## Emails are not being sent

### Checklist

1. **Verify email settings** — Go to **Admin → Emails** and confirm the SMTP host, port, and credentials are correct.
2. **Test sending** — Use the **Send test email** button on the Emails page.
3. **Check the task queue** — Email sending is handled by background tasks. Go to **Tasks** and verify the email task is not stuck or in error.
4. **PHP mail function** — If using `mail()` instead of SMTP, verify that the server's mail transfer agent (Postfix, Sendmail, etc.) is running.
5. **Firewall** — Verify outbound connections on port 25, 465, or 587 are not blocked.

---

## Background tasks are not running

Teampass uses a cron job to run background tasks (key generation, email, statistics). If tasks are stuck:

1. **Verify the cron entry**:
```bash
crontab -l -u www-data
```
It should contain something like:
```
* * * * * php /var/www/html/teampass/scripts/background_tasks___handler.php
```

2. **Run manually to see errors**:
```bash
php /var/www/html/teampass/scripts/background_tasks___handler.php
```

3. **Check file permissions** — The `www-data` user must be able to read and write inside the Teampass directory.

4. **Check the Tasks page** — In the Admin menu, **Tasks** shows each task's last execution time, status, and any error messages.

---

## A user's items all show as empty after re-encryption

After a key regeneration or migration, items may temporarily appear empty while the background task processes the sharekeys. This is normal and should resolve within a few minutes.

To monitor progress:

1. Go to **Admin → Tasks**.
2. Find the key generation task for that user.
3. Wait for it to complete (status changes from `in progress` to `done`).

If the task completed but items are still empty, use the **Tools** page to run a diagnostic and repair.

---

## Upgrade fails or leaves the interface broken

If an upgrade via `upgrade.php` fails partway through:

1. **Do not run the upgrade script again immediately** — check the error message first.
2. **Restore your database backup** (taken before the upgrade).
3. **Check PHP error logs**: `tail -f /var/log/php_errors.log` or the Apache/Nginx error log.
4. **Common cause**: PHP version mismatch. Teampass requires PHP 8.1+. Verify with `php -v`.
5. **Permissions**: ensure the web server can write to the Teampass directory during upgrade.

If you cannot resolve the issue, open a ticket on [GitHub Issues](https://github.com/nilsteampassnet/TeamPass/issues) with the error message and your PHP / database versions.

---

## OAuth2 / Azure Entra users cannot log in on second attempt

### Symptom
A user authenticates successfully via Azure the first time, but on subsequent logins sees "Login credentials do not correspond" or is redirected back to the login page.

### Cause
The account creation background task (key generation) may not have completed before the second login attempt. The account is created but `is_ready_for_usage` is still `0`.

### Fix
1. Go to **Admin → Tasks** and verify the key generation task for that user completed successfully.
2. If the task failed, check the task error message. Common causes: missing email address in the Azure profile, or the background task cron job is not running.
3. Once the task completes, the user can log in normally.

---

## MFA QR code does not appear / 2FA reset is refused

### Symptom

During Google Authenticator setup, no QR code is shown (not even a broken image), or the user
gets the message *"Your administrator has disabled self-reset of the 2FA code…"* when trying to
get a new code from the login page.

### Cause / resolution

- **No QR code** — the QR is generated **locally in the browser** from the `otpauth://` URI and
  needs **no external service or internet access**. If nothing appears, do a hard refresh (the QR
  library is served with a version query string and may be cached) and check the browser console
  for JavaScript errors.
- **"…disabled self-reset of the 2FA code…"** — the **User can reset his 2FA code** option
  (`Settings → MFA`) is disabled, so users cannot reset their own code. Ask an administrator to
  send a new code from the **Users** page (this always works), or enable the option.
- **User enrolled but no working code** — an administrator must reset it from the **Users** page.

See [Authentication → Multi Factor Authentication](../features/authentication.md) for the full
enrollment flow.

---

## Migrating TeamPass to another environment

This section covers moving an existing TeamPass installation to a new server or from a test environment to production. **Never run the installer on an existing installation** — doing so regenerates the encryption key (SECUREFILE) and permanently invalidates all encrypted data.

### Prerequisites

Before starting, gather the following from the **source** server:
- `app/config/settings.php` (contains DB credentials and the SECUREFILE name)
- The SECUREFILE itself (path and filename come from `settings.php` — the file is stored outside the web root at `TEAMPASS_SECRETS/SECUREFILE`)
- A full database dump: `mysqldump -u <user> -p <dbname> > teampass_backup.sql`
- The full TeamPass application folder

### Step-by-step migration

**1. Copy the application**

Transfer the entire TeamPass folder to the destination server. Preserve file permissions (`www-data` or equivalent must own the files).

**2. Copy the SECUREFILE**

The encryption master key is stored in the `secrets/` directory at the project root. Its filename is recorded in `app/config/settings.php`:

```php
// In app/config/settings.php (written by the installer):
define('SECUREFILE', 'sk_xxxxxxxx');   // encryption key filename

// TEAMPASS_SECRETS is auto-derived in app/config/include.php — do not edit:
// define('TEAMPASS_SECRETS', TEAMPASS_ROOT . '/secrets');
```

Copy that file to the **exact same path** on the destination server. If the path changes, update the constants in `settings.php`.

> ⚠️ If the SECUREFILE is lost or regenerated, all encrypted data (passwords, sessions, settings) becomes unrecoverable.

**3. Import the database**

```bash
mysql -u <dest_user> -p <dest_dbname> < teampass_backup.sql
```

**4. Update `app/config/settings.php`**

Edit the file on the destination server and update:
- `DB_HOST`, `DB_USER`, `DB_PASSWD`, `DB_BDDNAME` — destination DB credentials
- `TEAMPASS_SECRETS` / `SECUREFILE` — only if the path changed in step 2

**5. Update paths in the database**

If the server URL or absolute path changed, update the `teampass_misc` settings table:

```sql
UPDATE teampass_misc SET valeur = '/var/www/html/teampass/' WHERE intitule = '_absolute_path';
UPDATE teampass_misc SET valeur = 'https://teampass.example.com/' WHERE intitule = '_url_path';
```

**6. Delete stale PHP sessions**

Old session files from the source server will fail to decrypt with the destination server's environment. Remove them:

```bash
rm /var/lib/php/sessions/sess_*
# or wherever your session.save_path points
```

**7. Fix file permissions**

```bash
chown -R www-data:www-data /var/www/html/teampass/
chmod -R 755 /var/www/html/teampass/
```

**8. Restart PHP-FPM**

Clears the APCu settings cache and reloads environment:

```bash
systemctl restart php8.x-fpm
```

**9. Verify**

- Log in as administrator.
- Go to **Admin → Tools** and run a diagnostic check.
- Verify that items and passwords are accessible for regular users.
- Check **Admin → Tasks** — background tasks should be running normally.

### Common errors during migration

| Error | Likely cause | Fix |
|---|---|---|
| `Ciphertext is too short` or `Integrity check failed` | SECUREFILE mismatch or stale PHP sessions | Verify SECUREFILE path/content matches source; delete old session files (step 6) |
| `Access denied for user '...'@'...'` | DB credentials mismatch between `settings.php` and destination DB | Update `settings.php` or recreate the DB user with the source credentials |
| `Cannot redeclare isHex()` | PHP file included twice — symptom of a botched installer re-run | Restore `settings.php` from the source server and do not re-run the installer |
| Domain users see empty passwords | Session decryption failure upstream of sharekey decryption | Fix SECUREFILE issue first; passwords will reappear once sessions work |
| Items appear empty after migration | Background key-generation task pending | Wait for the task to complete (Admin → Tasks), or run the background handler manually |

### What NOT to do

- ❌ **Do not run `install/install.php`** on an existing installation — it regenerates the SECUREFILE.
- ❌ **Do not run `install/upgrade.php`** unless you are intentionally upgrading the TeamPass version.
- ❌ **Do not regenerate user keys** unless explicitly needed — it triggers a full re-encryption of all items.
