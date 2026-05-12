<!-- docs/misc/tips.md -->

## You have lost your admin account

If you have no admin user set, the only way to define a new one is to follow these instructions.

* Select a user who is currently able to log in to Teampass and who will temporarily receive admin rights.
* Ensure this user is not currently logged in.
* Grant `admin` in the database:
```sql
UPDATE `<TABLE PREFIX>_users` SET admin = 1 WHERE login = '<USER LOGIN>';
```
* Ask the user to log in.
* Go to the **Users** page and create a new user with Administrator privileges.
* Log out of the temporary admin account.
* Remove the temporary admin flag:
```sql
UPDATE `<TABLE PREFIX>_users` SET admin = 0 WHERE login = '<USER LOGIN>';
```

---

## A user cannot log in after a password reset

When an administrator resets a user's password, the user's private encryption key must be re-encrypted with the new password. If the **Generate new keys** step was skipped, the user may be unable to log in or see their items.

**Fix:**
1. Go to **Users**.
2. Open the action menu for the affected user.
3. Click **Generate new keys**.
4. Wait for the background task to complete (visible in **Tasks**).

The user can then log in with their new password.

---

## Items are empty or show decryption errors after a key change

If a user changed their password outside the normal flow (e.g. via a direct database update) without re-encrypting their keys, their private key will no longer match their password and items will fail to decrypt.

**Fix:**
1. Go to **Tools** in the administration menu.
2. Use the **Fix Items Empty After User OTP Change** tool.
3. Select the affected user.
4. Enter the user's current (correct) password.
5. Click **Perform**.

The tool re-decrypts and re-encrypts all affected sharekeys.

---

## Unlocking a brute-force locked account

After too many failed login attempts, an account is locked. To unlock it:

1. Go to **Users**.
2. Open the action menu for the locked user.
3. Click **Bruteforce reset**.

The counter is cleared and the user can attempt to log in again immediately.

---

## Forcing a re-enrollment of Google Authenticator

If a user loses their authenticator app or switches phones:

1. Go to **Users**.
2. Open the action menu for the user.
3. Click **Email Google Auth QR**.
4. A new QR code is sent to the user's registered email address.
5. The user scans the new code with their authenticator app.

---

## Checking the table prefix

If you are unsure of your database table prefix (needed for manual SQL queries), check the file `/app/config/settings.php` — the constant `DB_PREFIX` holds the prefix value (default: `teampass_`).

---

## Clearing the APCu settings cache

If you changed a setting directly in the database (e.g. during troubleshooting) and the change does not appear in the interface, the APCu cache may be serving the old value. To force a refresh:

* Restart PHP-FPM: `systemctl reload php8.x-fpm`
* Or wait up to 60 seconds for the cache TTL to expire automatically.

---

## Moving Teampass to a new server

1. Copy all files to the new server.
2. Export the database and import it on the new server.
3. Copy the secure key file from the `secrets/` directory (its filename is stored in `SECUREFILE` — see `app/config/settings.php`).
4. Update `/app/config/settings.php` with the new database credentials and paths.
5. Update **Settings → General Info** with the new installation path and URL.
6. Test login before decommissioning the old server.

> 🔔 The secure key file is critical — without it, all encrypted data (sessions, settings passwords) becomes unreadable. Always back it up separately from the database.
