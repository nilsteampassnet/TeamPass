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

1. As a TeamPass administrator, go to **Operations → LAPR settings → General**.
2. Toggle **Enable LAPR module** on.
3. (Recommended) Under **Security**, enable the **allowlist** and list the hostnames/domains LAPR is allowed to reach.
4. (Optional) Under **Scheduler**, enable automatic rotations and set the scan interval.

Once enabled, an **LAPR** section appears directly below **Passwords** for non-admin users who hold the *Can manage LAPR* permission. TeamPass administrators keep access to **LAPR settings** only; they cannot use the item-dependent operational pages.

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

**LAPR → Managed endpoints → Enroll an endpoint.**

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

**LAPR → Managed accounts → Add a managed account.**

- Pick the endpoint, then search for an eligible TeamPass item (writable by you, non-personal, active, with a login, not already managed), then select a policy. The picker searches by item label or login and displays the folder path when labels are ambiguous.
- The item's `login` must be a **valid Linux username**. Free-text logins that aren't valid usernames are rejected.
- **Discover accounts** scans the endpoint (`getent passwd`) and lists real login accounts to help you pick which to manage. **Manage this account** opens the same form with the discovered endpoint locked and the item search restricted to the discovered Linux login.
- Removing a managed account is a soft deletion so its audit history is preserved. Adding the same TeamPass item again safely reactivates the existing LAPR row and resets its scheduling/retry state.

LAPR does not create vault items during discovery. Endpoint enrollment and managed-account creation are two separate relationships:

- the endpoint's **SSH credential item** lets LAPR connect to the server;
- a **managed-account item** represents one local Linux account whose password LAPR rotates.

The same endpoint can therefore own several managed accounts. An item may also play both roles when the SSH account itself is managed.

Deleting a TeamPass item that a managed account or enrolled endpoint still references is **blocked** — remove the managed account or reconfigure/remove the endpoint first.

### LAPR item integration

TeamPass identifies LAPR-linked items throughout the item vault:

- **LAPR managed** marks the item whose Linux login and password are controlled by a managed account. The badge colour reflects the account state.
- **LAPR SSH credential** marks an item used to authenticate an enrolled endpoint.

Both badges appear in item lists, search results, and the item detail header. For an authorized LAPR operator with write access to the item's folder, the detail panel also shows the endpoint, policy, next rotation, scheduler state, and any endpoint-availability warning.

For a managed item, the normal item form keeps the `login` and password read-only while leaving metadata such as label, URL, description, tags, and custom fields editable. The same ownership rule is enforced by the web handler and REST API, so bypassing the browser cannot silently desynchronize TeamPass from Linux. Remove the managed-account relationship first if the login itself must change.

For an SSH credential item, manual editing remains available as a recovery path, but TeamPass warns that changing the stored value does **not** change the remote Linux password and may break future LAPR connections.

Three operations are refused while an item is linked to LAPR, on **every** path — single actions, bulk actions and the REST API:

| Operation | Why |
|---|---|
| Changing the `login` or password of a **managed** item | LAPR owns them; a manual change desynchronizes TeamPass from Linux |
| **Deleting** a managed item or an SSH credential item | the relationship has no database foreign key and would be orphaned |
| **Moving** either kind of item into a **personal folder** | LAPR reads the item as the server, which is only possible in a shared folder |

Bulk move and bulk delete skip the linked items and tell you how many were skipped, instead of failing the whole selection. The REST API answers `409 Conflict` in all three cases. Resending an unchanged password is **not** a conflict, so an API client that reads an item and writes it back untouched keeps working.

> **Disabling the module releases everything.** When **Enable LAPR module** is off, linked items behave exactly like ordinary items: no badge, no read-only field, no blocked delete or move. This is deliberate — the only way to remove a managed account goes through the LAPR pages, which are themselves hidden when the module is off. Managed accounts are kept and resume where they left off when you re-enable LAPR.

---

## Rotation policies

**LAPR → Rotation policies.**

Three read-only presets ship by default (Standard 30 days, High Security 7 days, Weekly + rotate on enroll). Create your own with:

- **Frequency** (1–3650 days),
- **Password length** (8–128),
- **Character sets** (at least one of uppercase / lowercase / digits / symbols),
- **Rotate on enrollment**.

Generated passwords are always filtered to be **safe for `chpasswd`** — no `:` (the field separator), whitespace, backslash or quotes. Use **Preview** to see a sample.

---

## Rotating

- **Rotate now** on a managed account, or from its TeamPass item detail panel, runs an immediate rotation and shows live progress after the standard TeamPass confirmation dialog.
- The **scheduler** rotates due accounts automatically when enabled.
- **History** shows a per-account, read-only, paginated timeline of every rotation, retry, suspension and reset.

Editing an item's password never triggers an implicit remote operation. A rotation is always explicit (or scheduler-driven), runs as a background task, and preserves the SSH-first safety model described below.

Each rotation:

1. reads the current item password (proving the key chain works and capturing the previous value for password history);
2. generates the new password per policy;
3. pushes it with `chpasswd` (via `sudo -n` when the SSH account is not root);
4. re-encrypts the item and refreshes every sharekey, logs the change (with history), and emits a real-time update event.

### Failure handling

- **SSH-first model.** The password is pushed to the server first. If the *item* update then fails, the account is marked **error** with a **MANUAL RESYNC REQUIRED** note — the server holds the new password, so reset it manually and re-sync.
- **Host-key mismatch blocks rotation.** Because LAPR connects with a reusable, privileged credential, a changed host key (a MITM signal) **aborts** the rotation. Verify the server, then explicitly trust the new key.
- **Scheduler retries** failed rotations up to `lapr_max_retries`, then **suspends** the account until an authorized LAPR operator uses **Reset & resume**.

---

## Permissions

- **TeamPass administrators** configure and enable LAPR under **Operations → LAPR settings**, but cannot open managed endpoints, managed accounts, or rotation policies because administrators do not have access to TeamPass items.
- Grant non-admin users the **Can manage LAPR** permission under **LAPR settings → Permissions**. Folder access rules still apply: an authorized user can only manage accounts whose item folder they can write to.

---

## Requirements

- PHP with the Composer dependency `phpseclib/phpseclib ^3` (already bundled). No `sshpass`, no `php-ssh2` extension, no `exec()`.
- Background tasks must be running (cron or the FPM trigger) — see [Tasks](../manage/tasks.md).
- Network reachability from the TeamPass host to the target SSH ports.
- On the target: `chpasswd` available, and the SSH account either **root** or granted **passwordless** `sudo` for `chpasswd`.
