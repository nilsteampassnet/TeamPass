<!-- docs/features/lapr.md -->

## Overview

**LAPR** (Linux Account Password Rotation) rotates the passwords of **local Linux accounts** directly from TeamPass, over SSH, **without any agent** installed on the target servers. TeamPass generates a new password following a policy, pushes it to the server with `chpasswd`, and re-encrypts the corresponding TeamPass item so the vault stays the single source of truth.

Everything runs in **background tasks** — no SSH work ever happens in the web request thread — and every action is audited without ever writing a secret to a log.

> 🔒 LAPR reuses TeamPass's existing item encryption (object key + per-user RSA sharekeys). There is **no parallel secret store**. The server-side `TP_USER` key is used to read credentials and re-encrypt items without any human password.

---

## Concepts

| Term | Meaning |
|------|---------|
| **Managed endpoint** | A Linux server enrolled in TeamPass (SSH host + port + credential + trusted host key). |
| **Managed account** | An *existing* TeamPass item whose `login` is the Linux account to rotate. LAPR never creates items — it manages the password of one you already store. |
| **Policy** | Rotation frequency (days) + generated-password rules (length, character sets) + an optional "rotate on enrollment" flag. Reusable across accounts. |
| **Rotation** | Generate a password → push it with `chpasswd` → re-encrypt the item and refresh every sharekey → audit. |
| **Scheduler** | A periodic scan that enqueues rotations for accounts whose next rotation is due, with retry and suspension logic. |

---

## Enabling LAPR

LAPR is **disabled by default** and fully opt-in.

1. Go to **Admin → Password Rotation → LAPR settings → General**.
2. Toggle **Enable LAPR module** on.
3. (Recommended) Under **Security**, enable the **allowlist** and list the hostnames/domains LAPR is allowed to reach.
4. (Optional) Under **Scheduler**, enable automatic rotations and set the scan interval.

Once enabled, a **Password Rotation** section appears in the sidebar for admins and for users who hold the *Can manage LAPR* permission.

| Setting | Key | Default |
|---------|-----|---------|
| Enable LAPR module | `lapr_enabled` | `0` |
| Restrict endpoints to an allowlist | `lapr_allowlist_enabled` | `0` |
| Allowed hostnames | `lapr_allowlist` | *(empty)* |
| SSH connect timeout (seconds) | `lapr_ssh_connect_timeout` | `10` |
| Rate limit: max attempts / window / block | `lapr_rate_limit_*` | `5 / 60s / 300s` |
| Enable automatic rotations | `lapr_scheduler_enabled` | `0` |
| Scheduler scan interval (minutes) | `lapr_scheduler_interval_minutes` | `5` |
| Max retries before suspension | `lapr_max_retries` | `3` |
| Retry delay (minutes) | `lapr_retry_delay_minutes` | `60` |
| Audit log retention (days, 0 = forever) | `lapr_audit_retention_days` | `365` |

---

## Enrolling an endpoint

**Password Rotation → Managed endpoints → Enroll an endpoint.**

1. Fill the label, hostname/IP, SSH port, SSH username and authentication method.
2. Select the **TeamPass item that holds the SSH credential** (the item's password field). LAPR reads it server-side through the `TP_USER` key chain — it is never typed into the enrollment form and never transmitted.
3. Click **Test connection**. TeamPass runs a background SSH test that:
   - connects and records the **host key fingerprint** (trust on first use);
   - collects OS information and capabilities (`chpasswd`, `sudo`);
   - checks that the account can actually change passwords — it must be **root** or have **passwordless sudo**. If neither, enrollment is refused (every future rotation would fail).
4. On success, click **Save**. The tested parameters are protected by a signed snapshot so the saved endpoint always matches what was tested.

> ⚠️ **Store SSH credential items in a tightly restricted folder.** Anyone who can read that folder obtains the endpoint's SSH credential — often root. This folder is part of your crown jewels.

> ℹ️ The host key fingerprint format is **TeamPass-internal** and will not match `ssh-keygen -lf`. Enroll from a trusted network segment; TOFU then protects against later host-key changes.

---

## Managing accounts

**Password Rotation → Managed accounts → Add a managed account.**

- Pick the endpoint, then an eligible TeamPass item (accessible to you, non-personal, active, with a login, not already managed), then a policy.
- The item's `login` must be a **valid Linux username**. Free-text logins that aren't valid usernames are rejected.
- **Discover accounts** scans the endpoint (`getent passwd`) and lists real login accounts to help you pick which to manage.

Deleting a TeamPass item that a managed account still references is **blocked** — remove the managed account first.

---

## Rotation policies

**Password Rotation → Rotation policies.**

Three read-only presets ship by default (Standard 30 days, High Security 7 days, Weekly + rotate on enroll). Create your own with:

- **Frequency** (1–3650 days),
- **Password length** (8–128),
- **Character sets** (at least one of uppercase / lowercase / digits / symbols),
- **Rotate on enrollment**.

Generated passwords are always filtered to be **safe for `chpasswd`** — no `:` (the field separator), whitespace, backslash or quotes. Use **Preview** to see a sample.

---

## Rotating

- **Rotate now** on a managed account runs an immediate rotation and shows live progress.
- The **scheduler** rotates due accounts automatically when enabled.
- **History** shows a per-account, read-only, paginated timeline of every rotation, retry, suspension and reset.

Each rotation:

1. reads the current item password (proving the key chain works and capturing the previous value for password history);
2. generates the new password per policy;
3. pushes it with `chpasswd` (via `sudo -n` when the SSH account is not root);
4. re-encrypts the item and refreshes every sharekey, logs the change (with history), and emits a real-time update event.

### Failure handling

- **SSH-first model.** The password is pushed to the server first. If the *item* update then fails, the account is marked **error** with a **MANUAL RESYNC REQUIRED** note — the server holds the new password, so reset it manually and re-sync.
- **Host-key mismatch blocks rotation.** Because LAPR connects with a reusable, privileged credential, a changed host key (a MITM signal) **aborts** the rotation. Verify the server, then explicitly trust the new key.
- **Scheduler retries** failed rotations up to `lapr_max_retries`, then **suspends** the account until an admin uses **Reset & resume**.

---

## Permissions

- **Admins** always have full LAPR access.
- Grant non-admin users the **Can manage LAPR** permission under **LAPR settings → Permissions**. Folder access rules still apply: a user can only manage accounts whose item folder they can write to.

---

## Requirements

- PHP with the Composer dependency `phpseclib/phpseclib ^3` (already bundled). No `sshpass`, no `php-ssh2` extension, no `exec()`.
- Background tasks must be running (cron or the FPM trigger) — see [Tasks](../manage/tasks.md).
- Network reachability from the TeamPass host to the target SSH ports.
- On the target: `chpasswd` available, and the SSH account either **root** or granted **passwordless** `sudo` for `chpasswd`.
