<!-- docs/install/security-hardening.md -->

## Security hardening

This page is a **checklist for putting a TeamPass instance into production**, and for reviewing an existing one. Each point says what to check, why it matters and where the details are.

> 📌 Work through it after the installation **and after every upgrade**. Some defaults differ between a new installation and an upgraded one, so that an upgrade never changes the behaviour of an existing instance behind its administrator's back — see [New installation or upgrade?](#new-installation-or-upgrade).

---

## Checklist at a glance

| # | Check | Details |
|---|-------|---------|
| 1 | The web server serves `public/` only, over HTTPS with a trusted certificate | [Web server and TLS](#web-server-and-tls) |
| 2 | `public/install/` is blocked once the installation or upgrade is finished | [Web server and TLS](#web-server-and-tls) |
| 3 | File permissions follow least privilege; the encryption key sits outside the webroot | [File system](#file-system) |
| 4 | MFA is enabled and the anti-bruteforce thresholds are set | [Authentication](#authentication) |
| 5 | The real client IP is detected correctly; network ACLs restrict access if possible | [Network](#network) |
| 6 | The API requires HTTPS and is rate-limited — or is disabled | [API](#api) |
| 7 | New data is written with authenticated encryption (AES-256-GCM) | [Encryption at rest](#encryption-at-rest) |
| 8 | Logs leave the server and the health checks are green | [Monitoring and audit](#monitoring-and-audit) |
| 9 | Backups run, are stored off-site, and the Recovery Package is kept offline | [Backups](#backups) |

---

## New installation or upgrade?

These settings came with a secure default for new installations. When an upgrade adds them to an existing instance, they keep the behaviour the instance had before, so check them explicitly after upgrading from an older version.

| Setting | New installation | Added by an upgrade |
|---------|------------------|------------------|
| **Authenticated encryption (AES-256-GCM) for new data** (`aes_v2_write_enabled`) | Enabled | Disabled — enable it, see [Encryption at rest](#encryption-at-rest) |
| **Require HTTPS for API requests** (`api_require_https`) | Enabled | Disabled |
| **API rate limit (requests per minute)** (`api_rate_limit_per_minute`) | 120 | 0 (no limit) |

One default is worth changing on **every** installation: **Maximum login attempts before account lockout** (`nb_bad_authentication`) is `0` on a new installation, which disables the per-account lockout. See [Authentication](#authentication).

---

## Web server and TLS

- **Point the document root at `public/`.** Application code (`app/`), runtime data (`storage/`) and the encryption key (`secrets/`) live next to it and must not be reachable over HTTP. See [Installation](installation.md) and [File permissions](file-permissions.md).
- **Serve TeamPass over HTTPS only**, with a certificate issued by a certificate authority and matching the server name. Set the **TeamPass URL** (**Settings → Options → General information & installation paths**) to the `https://` address: every link TeamPass generates is built from it. A trusted certificate is also required by the [browser extension](../misc/extension.md), which cannot reach a server whose certificate the browser rejects.
- **Enable HSTS** once the certificate is in place: **HTTPS Strict Transport Security (HSTS)** in **Settings → Options → Security & authentication**. Browsers then refuse any plain HTTP connection to the server. A self-signed certificate does not work with HSTS.
- **Block `public/install/`** as soon as the installation wizard completes, and again after each upgrade. The installer and upgrade scripts must not stay reachable on a running instance. See [Installation](installation.md) and [Upgrade](upgrade.md) for the Apache and Nginx directives.
- **Keep the WebSocket daemon on `127.0.0.1`** (its default) and expose it only through the reverse proxy. See [WebSocket](websocket.md).

---

## File system

- **Apply the permission model of [File permissions](file-permissions.md)**: application code not writable by the web server, `app/config/settings.php` writable only during an installation or an upgrade, runtime directories writable, never `0777` nor `0775`.
- **Protect the encryption key.** `secrets/` sits outside the webroot, readable by the web server only (`0750`), and the key file is readable by its owner only (`0600`). The split-owner model described in [File permissions](file-permissions.md) goes further: the web server can read the key but never rewrite or replace it.
- **Run the verification checklist** of [File permissions](file-permissions.md) after the installation and after each upgrade.
- **Scan file integrity** from **Utilities → System Health → File integrity**. The scan compares the TeamPass files with the release manifest and reports modified, missing and unknown files.

---

## Authentication

- **Enable multi-factor authentication** (Settings → MFA). Once a method is enabled, every user must provide a code; exceptions can be granted to administrators globally and to individual users. Review the users list regularly: a user exempted from MFA is marked with a red fingerprint. See [Authentication](../features/authentication.md#multi-factor-authentication-mfa).
- **Set the anti-bruteforce thresholds** — see the table below.
- **Keep automatic login from HTTP credentials disabled**: **Automatic login using http header credentials** (`enable_http_request_login`) is off by default.
- **Keep sessions short**: **Default session expiration** and **Maximum session expiration** default to 60 minutes. See [Session management](../misc/session-management.md).

The anti-bruteforce settings are in **Settings → Options → Security & authentication**, group *Anti-bruteforce*. The same counters protect the API authentication, and current locks are listed in **Utilities → Logs**.

| Setting | Effect | Default |
|---------|--------|---------|
| **Maximum login attempts before account lockout** (`nb_bad_authentication`) | Locks the account after this many failed attempts. `0` disables the per-account lockout | `0` on a new installation — **set a value**, for example 10 |
| **Maximum failed login attempts per IP before lockout** (`nb_bad_authentication_by_ip`) | Locks the client address after this many failed attempts. `0` disables it | 30 |
| **Lock duration after threshold is reached** (`bruteforce_lock_duration`) | Minutes the lock lasts | 10 |

---

## Network

- **Declare your reverse proxy.** When a proxy, load balancer or WAF sits in front of TeamPass, set **IP detection mode** to *Reverse proxy / WAF* and list the proxy addresses in **Trusted proxies** (**Settings → Options → Networks**). The client address is then read from the proxy header only when the request comes from a trusted proxy. This is the address recorded in the audit log and locked by the per-IP anti-bruteforce counter. Leave *Direct access* when no proxy sits in front of TeamPass.
- **Restrict access with the network ACL** when TeamPass is only used from known networks: a whitelist limits access to listed addresses and ranges, a blacklist blocks known attackers. Add your own address before enabling the whitelist. See [Network ACL](../manage/network-acl.md).

---

## API

If nobody uses the REST API, the browser extension or the mobile application, leave **API access enabled** off (the default).

Otherwise, in **Settings → API**:

- **Require HTTPS for API requests** — rejects any API request sent over plain HTTP, so credentials and tokens never travel unencrypted. Enabled on new installations, disabled when an upgrade adds it. Keep it disabled only if TLS is terminated upstream without an `X-Forwarded-Proto` header.
- **API rate limit (requests per minute)** — caps authenticated requests per user and per IP address (`429` above the limit). 120 on new installations, `0` (no limit) when an upgrade adds it.
- **Allowed CORS origins** — an empty field accepts any origin; the token remains the real protection. Fill in a list of origins if only known clients should call the API.
- **Allow OAuth2 users to access the API** (**Settings → OAuth2**) and **Allow extension auto-configuration for all users** (**Settings → API**, *Browser extension* tab) — enable them only if you use these features: both are off by default.

The administrator dashboard warns when the API accepts plain HTTP or any origin. The endpoints and their security model are described in the [API documentation](../api/api-basic.md).

---

## Encryption at rest

### Authenticated encryption for new data

**Authenticated encryption (AES-256-GCM) for new data** (**Settings → Options → Security & authentication**, group *Encryption & key management*) writes item passwords, encrypted custom fields and private keys in the AES-256-GCM format: random IV, per-value salt and an integrity tag that detects any tampering.

- It is **enabled on new installations**.
- On an instance **upgraded from a version older than 3.2.1**, it is **disabled** until you enable it. Existing data stays readable either way.

Once it is enabled, legacy data is upgraded gradually, when it is accessed or saved again: nothing to schedule, no downtime. The **Encryption format migration** panel, just below the setting, shows the share of item passwords, encrypted custom fields and user private keys already converted. Disabling the setting only affects new writes: data already converted stays readable. See [Encryption hardening (3.2)](encryption-improvements.md).

### Encrypted client/server exchanges

Keep **Encrypt client/server** (`encryptClientServer`) enabled — it is by default. The data exchanged between the browser and the server is then encrypted on top of TLS.

### Personal items isolation

A personal item must be decryptable only by its owner. Each user holds a *sharekey* — the item key encrypted for them — so a personal item carries a sharekey for its owner and for the internal `TP` recovery account only. The recovery account lets TeamPass repair the owner's keys; it never gives another user access.

An instance that ran a version older than 3.2.1.2 may hold **foreign sharekeys** on personal items: sharekeys given to other users by those versions. The upgrade wizard **removes them automatically**, once — the first time the instance is upgraded to 3.2.1.2 or later — and writes its counts to the PHP error log (`personal sharekeys remediation`). Nothing is required from the administrator.

A command-line script lets you audit the result, or run the cleanup again, for instance after restoring an old database dump:

```bash
# From the TeamPass installation root — analysis only, changes nothing (default)
php app/scripts/remediate_personal_sharekeys.php --dry-run

# Delete the foreign sharekeys it reports — back up the database first
php app/scripts/remediate_personal_sharekeys.php --execute
```

`--execute` deletes rows: take a database backup before running it.

```bash
mysqldump -u <user> -p <database> > teampass_backup_$(date +%Y%m%d_%H%M%S).sql
```

The script ends with a summary:

```
=== Summary ===
Personal items analysed   : 45    ← personal items examined
Already clean             : 42    ← only the owner and system accounts hold a key
Items with foreign keys   : 0     ← items that will be cleaned with --execute
Skipped (unresolved owner): 1     ← owner cannot be determined — left untouched
Skipped (owner conflict)  : 2     ← folder owner differs from the creator — left untouched
Foreign sharekeys found   : 0     ← foreign keys detected in total
```

- The owner of a personal item is the owner of its personal folder, cross-checked with the user who created the item. When the two disagree, or when the owner cannot be determined, the item is **skipped and left untouched**: the script never deletes a key on doubt. Inspect such items manually if needed.
- Only keys held neither by the owner nor by a system account are deleted, for the item itself, its custom fields, its attachments and its history. The owner never loses access.
- Each item is cleaned in its own transaction; the script needs no maintenance window and can be run again safely — a second run finds nothing more to delete.
- `Foreign sharekeys found: 0` on a new dry run confirms the instance is clean.

To check a single item, list the users holding a sharekey on it: only its owner and the system accounts (`9999997` TP, `9999999` API, `9999991` OTV, `9999998` SSH) should remain. Replace `teampass_` with your table prefix.

```sql
SELECT user_id FROM teampass_sharekeys_items WHERE object_id = <item_id>;
```

Users who see an item but cannot open it are missing a sharekey, which is a different problem: see [Tools → Restore missing sharekeys](../manage/tools.md).

---

## Monitoring and audit

- **Check the health indicators** on the administrator dashboard: missing PHP extensions, an open API, a stalled background task queue, the file integrity result. See [Performance](performance.md).
- **Keep item views logged**: **Log password item views by users** (`log_accessed`, **Settings → Options → Logging & history**) is enabled by default.
- **Send the audit events to your syslog or SIEM** (**Settings → Options → Integrations & automation**), so that a copy survives a compromise of the server.
- **Review access periodically** with [Access reviews](../manage/access-reviews.md), the [Compliance reports](../manage/compliance-reports.md) and, when someone leaves, the [Leaver risk](../features/leaver-risk.md) view.
- **Watch password hygiene**: [breach detection and the security posture page](../features/security-posture.md) flag weak, reused, breached and overdue passwords.

---

## Backups

- **Schedule backups** and copy them **off-site** with the externalized backups. See [Backups](../features/backups.md).
- **Test a restore** regularly.
- **Generate a Recovery Package** and store it offline with its passphrase. It contains the encryption key and the configuration needed to restore an instance; it is not a database backup and must not be sent to an automatic destination.
- **Keep the encryption key apart from the backups**: a backup and the key that opens it must never be stored together.
