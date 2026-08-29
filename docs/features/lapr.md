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
| Allow TeamPass host self-management | `lapr_allow_self_management` | `0` |
| SSH connect timeout (seconds) | `lapr_ssh_connect_timeout` | `10` |
| Rate limit: max attempts / window / block | `lapr_rate_limit_*` | `5 / 60s / 300s` |
| Send email alerts on rotation failures | `lapr_alert_email_enabled` | `0` |
| Alert email recipient | `lapr_alert_email_recipient` | *(empty)* |
| Enable automatic rotations | `lapr_scheduler_enabled` | `0` |
| Scheduler scan interval (minutes) | `lapr_scheduler_interval_minutes` | `5` |
| Periodically check enrolled servers | `lapr_endpoint_checks_enabled` | `1` |
| Server check interval (minutes) | `lapr_endpoint_check_interval_minutes` | `1440` |
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

The same normalized hostname/IP and SSH port can be enrolled only once. Credential relationships are also checked server-side both before the SSH test and again before saving:

- a password-based SSH credential item belongs to one endpoint only because synchronizing a rotated connection password would invalidate every other endpoint still using the old value;
- a private-key credential item may be shared by several key-authenticated endpoints;
- the same item cannot be mixed between password and key authentication;
- an item already managed as a Linux password cannot later become an endpoint credential.

LAPR also detects targets that certainly or probably host the current TeamPass instance. Self-management is blocked by default. An administrator may enable the high-risk **Allow the TeamPass host to be managed by LAPR** setting, but enrollment still requires an explicit acknowledgement. These endpoints remain manual-rotation-only and are never selected by the scheduler.

> ⚠️ **Store SSH credential items in a tightly restricted folder.** Anyone who can read that folder obtains the endpoint's SSH credential — often root. This folder is part of your crown jewels.

> ℹ️ The host key fingerprint format is **TeamPass-internal** and will not match `ssh-keygen -lf`. Enroll from a trusted network segment; TOFU then protects against later host-key changes.

### Refreshing an enrolled endpoint

The enrolled-server list has a **refresh/check** button next to Delete. It starts a background SSH check using the stored credential and trusted host key, then:

- updates the displayed OS name and kernel metadata;
- confirms whether the server is reachable;
- runs the same no-op `chpasswd` capability probe used during enrollment;
- restores a recovered endpoint to **active**, or records **unreachable/error**, the check time and a secret-free error code; a deliberately paused endpoint keeps its paused state;
- displays OS-specific prerequisite and `sudoers` commands when rotation rights are missing.

When periodic checks are enabled, the background handler performs the same refresh every `lapr_endpoint_check_interval_minutes` (24 hours by default). A transiently unreachable server is checked again after `lapr_retry_delay_minutes`, so recovery does not wait for the normal daily interval. Paused endpoints continue to receive non-destructive checks at the normal interval, without accelerated retries, alerts, or automatic reactivation.

### Pausing rotations for one endpoint

The endpoint list can pause **automatic rotations** without changing the state, retry counter, or due date of any attached account. Pending rotations that have not started are cancelled neutrally. A worker that is already connecting re-reads the endpoint state immediately before `changePassword`, so a concurrent pause still blocks the remote mutation. If the remote password was already changed, the TeamPass synchronization always finishes to prevent a password divergence.

The pause reason is selected from a fixed, secret-free list and recorded with the operator in the LAPR audit log. Availability, OS and privilege checks remain available. A manual rotation is still possible as an explicit break-glass action after an additional confirmation; the override is audited separately and never resumes automatic rotations.

**Resume automatic rotations** first queues a background SSH and privilege check while the endpoint remains paused. Only a successful check with valid rotation privileges changes the endpoint back to active. Existing due dates are preserved, so accounts already overdue become eligible at the next scheduler pass.

---

## Managing accounts

**LAPR → Managed accounts → Add a managed account.**

- Pick the endpoint, then search for an eligible TeamPass item (writable by you, non-personal, active, with a login, not already managed), then select a policy. The picker searches by item label or login and displays the folder path when labels are ambiguous.
- The item's `login` must be a **valid Linux username**. Free-text logins that aren't valid usernames are rejected.
- **Discover accounts** scans the endpoint (`getent passwd`) and lists the real `root` account plus regular login accounts with UID 1000 or greater. Reserved system accounts and accounts using `nologin` or `false` are excluded. This is a discovery filter, not an eligibility rule: on a host with a lower `UID_MIN` (older RHEL derivatives used 500), an account below 1000 simply does not appear in the list and is still added manually through the item picker, which validates the username rather than the UID. **Manage this account** opens the same form with the discovered endpoint locked and the item search restricted to the discovered Linux login.
- Removing a managed account is a soft deletion so its audit history is preserved. Pending rotations are completed without execution, and a worker already connecting re-checks the relationship immediately before changing the remote password. Adding the same TeamPass item again safely reactivates the existing LAPR row, resets its scheduling/retry state, and creates a fresh enrollment task when its policy requests one.

LAPR does not create vault items during discovery. Endpoint enrollment and managed-account creation are two separate relationships:

- the endpoint's **SSH credential item** lets LAPR connect to the server;
- a **managed-account item** represents one local Linux account whose password LAPR rotates.

The same endpoint can therefore own several managed accounts, provided that every item represents a different Linux login. A given endpoint/login pair can be managed only once, and a TeamPass item can be attached as a managed password to only one endpoint.

A password credential item may also be the managed-account item only when it represents the same endpoint and the same SSH login. Private-key credential items can never become managed passwords: rotating one would overwrite the private key and make future SSH connections impossible. The same relationship checks run again immediately before every rotation to protect legacy data created before these guards existed.

When a legacy duplicate endpoint or unsafe credential relationship reaches the worker, the rotation is refused before SSH, audited as a configuration failure, and the managed account is placed in an error state instead of being retried indefinitely.

> ⚠️ **Upgrading from an earlier 3.2.2 pre-release.** These relationship rules are also applied to data created before they existed. A setup that used to work — one password credential item shared by several endpoints, or an item managed as a Linux password while also serving as a private-key credential — now fails its next rotation with `ERR_SHARED_PASSWORD_CREDENTIAL`, `ERR_CREDENTIAL_RELATION_CONFLICT` or `ERR_KEY_CREDENTIAL_MANAGED`. The failure is deliberate and final rather than retried: split the credential items, then use **Reset & resume** on the affected accounts. **System Health → LAPR** lists every such relationship before the first rotation is attempted.

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

> **Disabling the module releases everything and stops LAPR work.** When **Enable LAPR module** is off, linked items behave exactly like ordinary items: no badge, no read-only field, no blocked delete or move. Pending LAPR tasks are cancelled neutrally, and workers re-read the switch before dispatch and again immediately before changing a remote password. If the remote change has already succeeded, TeamPass completes the local item synchronization to avoid leaving different passwords on the server and in the vault. Managed endpoints, accounts, and audit history remain stored and resume where they left off when you re-enable LAPR.

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

- **Rotate now** on a managed account, or from its TeamPass item detail panel, runs an immediate rotation and shows live progress after the standard TeamPass confirmation dialog. On a paused endpoint it remains available as an explicitly confirmed break-glass action and does not resume automatic rotations.
- A policy with **Rotate on enrollment** queues the first rotation as soon as the managed account is added. The SSH operation still runs asynchronously through the background worker and does not depend on the automatic scheduler switch. Endpoints identified as the TeamPass host remain manual-only and skip this automatic first rotation.
- The **scheduler** rotates due accounts automatically when enabled.
- **History** shows a per-account, read-only, paginated timeline of every rotation, retry, suspension and reset.

LAPR configuration is stored in the `admin` namespace of `teampass_misc`. The background handler reads the module switch, scheduler switch, interval, next-run timestamp, and audit-retention value from that same namespace. When `lapr_scheduler_next_run_at` is `0`, the first handler pass initializes it; due rotations are evaluated on a following pass after the configured interval.

To test an overdue rotation, keep the TeamPass host, database, and targets synchronized to the real time. Make one test account due and set the LAPR scheduler next-run timestamp to the past instead of changing the operating-system clocks. Large clock jumps can legitimately make every stored due date appear overdue and can also disturb sessions, logs, certificates, and distributed services.

Endpoint checks, last and next rotations, and history timestamps use the TeamPass-configured timezone, date format, and time format. The web handlers, background scheduler, and worker share this timezone. Date columns keep chronological sorting independently of the selected regional display format.

Editing an item's password never triggers an implicit remote operation. A rotation is always explicit (or scheduler-driven), runs as a background task, and preserves the SSH-first safety model described below.

In the standard TeamPass item history, an automatic password change is attributed to the localized **LAPR system** actor instead of showing the internal TeamPass service account with an empty display name. Manual and enrollment-triggered rotations retain the name of the user who requested them. Existing automatic-rotation entries receive the same display label without a database migration.

Each rotation:

1. reads the current item password (proving the key chain works and capturing the previous value for password history);
2. generates the new password per policy;
3. pushes it with `chpasswd` (via `sudo -n` when the SSH account is not root);
4. re-encrypts the item and refreshes every sharekey, logs the change (with history), and emits a real-time update event.

When the managed Linux login is also the endpoint's password-authenticated SSH login, LAPR synchronizes the dedicated SSH credential item after the managed item. This synchronization is never performed for key authentication, so a private-key item cannot be overwritten with the generated Linux password.

### Failure handling

- **SSH-first model.** The password is pushed to the server first. If the *item* update then fails, the account is marked **error** with a **MANUAL RESYNC REQUIRED** note — the server holds the new password, so reset it manually and re-sync.
- **Host-key mismatch blocks rotation.** Because LAPR connects with a reusable, privileged credential, a changed host key (a MITM signal) **aborts** the rotation. Verify the server, then explicitly trust the new key.
- **Scheduler retries only transient connectivity failures** (`timeout`, connection refused, host unreachable, lost connection), up to `lapr_max_retries`, then **suspends** the account until an authorized LAPR operator uses **Reset & resume**. Authentication, host-key, privilege, configuration and synchronization failures stop immediately in **error** because retrying cannot repair them.
- The managed-account list shows the error code, retry counter and exact next retry time. If LAPR email alerts are enabled, each failed rotation sends the configured recipient its operational state (action required, retry scheduled or suspended), without secrets.

---

## Monitoring and statistics

Administrators can monitor LAPR without opening the item-dependent operational pages:

- **System Health → LAPR** reports scheduler and worker status, endpoint-check freshness and queues, rotation compliance, retries, overdue/error/paused accounts, referential-integrity problems, and recent failures. A transient endpoint outage with a scheduled retry is reported as **retrying**, not duplicated as an account error. A deliberate endpoint pause is a warning and has its own account state; real integrity problems remain critical. An account hosted on the TeamPass server is reported as **manual rotation only**, never as overdue: the scheduler deliberately skips it, so its due date is expected to drift into the past. The report is passive and never opens an SSH connection when the page loads.
- The same Health tab reports effective human LAPR operators and separately highlights permissions still assigned to disabled accounts. Administrators are not counted as operators because they configure LAPR through the administration page rather than using its item-dependent operational pages.
- **Statistics → LAPR** shows rotation-attempt volume and attempt success rate for the selected period, success/failure trends, current account states (including endpoint pauses), the active/paused/unreachable/error endpoint split, failure categories, policy adoption, and endpoints with failures. Retries are attempts, so several failures can belong to one expected rotation. Endpoint checks are intentionally not presented as an uptime percentage because their daily/manual sampling is not availability monitoring.

Both LAPR tabs remain visible when the module is disabled to support feature discovery. They show a neutral **module disabled** state and no retained endpoint, account, operator, queue, or historical metrics, so stored relationships are never mistaken for active management.

The Health report also detects unsafe legacy relationships: duplicate endpoint targets, duplicate managed endpoint/login pairs, shared password credentials, managed items reused as private-key credentials, and endpoints that appear to host TeamPass itself.

System Health treats a newly due rotation as normal during a grace period equal to the greater of ten minutes or two scheduler intervals. This prevents false alarms between two normal background-handler runs.

Disabling automatic rotation is treated as an informational configuration state, not as a failure. Scheduler alerts are raised only for actual operational problems such as an overdue enabled scheduler, a stalled queue, or a failed worker.

The selected Statistics period applies to audit events. Endpoint and account states remain a current snapshot, and the page warns when the requested period exceeds the configured LAPR audit retention.

These administrator reports expose only operational metadata. Passwords, SSH keys, credential contents, and sharekeys are never included, including in the System Health JSON export.

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
