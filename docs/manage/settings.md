<!-- docs/manage/settings.md -->

## Overview

The **Settings** page (Options) is the central configuration area for administrators. It contains all global parameters that control how Teampass behaves.

---

## Finding an option

With over 100 settings available, use the **search box** at the top right of the page to filter by keyword. For example, typing `password` narrows the list to all password-related options across every category.

![Searching for options](../../_media/tp3_settings_keyword_search.png)

You can also **star** individual settings to pin them to the **Favourites** section at the top of the navigation, creating a personal quick-access list for the options you adjust most often.

---

## General Info

Basic installation paths and branding.

| Option | Description |
|--------|-------------|
| **TeamPass installation directory** | Absolute server path where Teampass is installed |
| **TeamPass URL** | Public URL used to generate links (e.g. One Time View links, email links) |
| **Path to upload folder** | Server path for temporary uploads |
| **Path to files folder** | Server path for item file attachments |
| **Favicon URL** | Custom favicon path or URL |
| **Public entity name** | Organisation name displayed to Secure Send recipients |
| **Custom logo** | Replaces the Teampass logo on the login page and, when stored locally, on Secure Send pages — see [Public branding](#public-branding) |
| **Custom login background** | Replaces the background image of the login page — see [Public branding](#public-branding) |
| **Custom login text** | Message displayed on the login page |

### Public branding

The **Public entity name** is plain text used to identify the organisation on Secure Send pages.

The **Custom logo** and **Custom login background** options each take one of:

- **the name of an image placed in `public/assets/custom/`**, for example `logo.png` or `background.jpg`. Enter the file name alone, without any folder: TeamPass looks for it in that folder. Accepted formats are PNG, JPG, GIF and WebP;
- **a full URL**, for an image hosted elsewhere. The background only accepts an absolute `http://` or `https://` URL.

Leave an option empty to keep the image shipped with TeamPass.

Secure Send only uses a logo stored in `public/assets/custom/`. A remote logo remains available
to the login page but is deliberately ignored on secret-sharing pages, so opening a link never
notifies an external image host and the restrictive public-page CSP remains unchanged.

The `public/assets/custom/` folder exists for these images. TeamPass only ships a `README.md` and an `.htaccess` file there, so an upgrade never replaces your images, and the file integrity check does not report them. Do not replace the shipped images or `public/assets/css/teampass.css` instead: the next upgrade overwrites them.

> `public/` is the web root: it never appears in a URL. If you prefer a URL to an image of the folder, write `https://teampass.example.com/assets/custom/logo.png`, not `.../public/assets/custom/logo.png`. The latter does not exist, and the server answers it with the TeamPass page itself, which the browser cannot display as an image.

The logo is resized to the width of the login box, up to 150 pixels high.

With Docker, mount the images into the container: see [Custom login logo and background](../install/docker.md#custom-login-logo-and-background).

---

## System

General behaviour and defaults.

| Option | Description |
|--------|-------------|
| **Maintenance mode** | When enabled, only administrators can log in. Use during upgrades or maintenance |
| **Default session expiration** | Session duration in minutes for a standard user (default: 60) |
| **Maximum session expiration** | Hard cap on session duration regardless of user activity |
| **Timezone** | Server timezone used for dates, logs, and scheduled tasks |
| **Date format** | Display format for dates across the interface |
| **Time format** | Display format for times |
| **Default language** | Language applied to new accounts and the login page |
| **Get TeamPass info** | Allows Teampass to check for available updates |
| **Password maximum length** | Upper bound on password length for items |
| **Password default length** | Pre-filled length in the password generator. The generator enforces the folder's minimum complexity, so the effective length may be higher than this value |

---

## Security

Access control, encryption, and session security parameters.

| Option | Description |
|--------|-------------|
| **Encrypt client-server communication** | Encrypts AJAX payloads between browser and server (AES) |
| **Enable HTTP request login** | Allows authentication via HTTP request parameters (use with caution) |
| **Enable STS/HSTS** | Adds the `Strict-Transport-Security` HTTP header; requires HTTPS |
| **Password life duration** | Number of days before a user's login password expires (0 = never) |
| **Maximum login attempts before account lockout** | Maximum failed logins before the account is locked. Default: 10; `0` disables the lockout |
| **Secure image display** | Serves item attachments through Teampass instead of direct URLs |
| **Password overview delay** | Seconds a revealed password stays visible before being masked again |
| **Activate item expiration** | Enables password expiration tracking on items (see [Renewal](../features/renewal.md)) |
| **Delete after consultation** | Marks items for deletion after a user views them (one-shot credentials) |
| **Clipboard password lifetime** | Seconds before the copied password is cleared from the clipboard |
| **Restrict item access to roles** | Item access is further restricted to explicitly listed roles |
| **Enable local password recovery** | Shows a *Forgot password?* link on the login page. Users can recover account access without contacting an administrator, using a PBKDF2-derived key. LDAP and OAuth2 accounts are excluded (see [Local password recovery](../features/keys.md#local-password-recovery)) |
| **PBKDF2 iterations** | Number of iterations for the PBKDF2 key derivation used by local password recovery. Higher values increase brute-force resistance at the cost of slightly longer key derivation time (default is suitable for most deployments) |

---

## WebSocket / Realtime

Real-time synchronisation between browser tabs and users.

### WebSocket

| Option | Description |
|--------|-------------|
| **Enable WebSocket** | Activates real-time item and folder change notifications |
| **WebSocket host** | Host the WebSocket daemon listens on (default: `127.0.0.1`) |
| **WebSocket port** | Port for the WebSocket daemon (default: `8080`) |

See [WebSocket](../install/websocket.md) for the full server setup guide.

### Redis session storage

| Option | Description |
|--------|-------------|
| **Enable Redis sessions** | Stores PHP sessions in Redis instead of the filesystem |
| **Redis host** | Redis server address (default: `127.0.0.1`) |
| **Redis port** | Redis port (default: `6379`) |
| **Redis key prefix** | Prefix for session keys in Redis (default: `teampass_sess_`) |

See [Performance](../install/performance.md) for when and how to enable Redis sessions.

---

## Logging

Audit trail and email notification settings.

| Option | Description |
|--------|-------------|
| **Log item access** | Records every time a user opens an item in the audit log |
| **Send email on user login** | Sends a notification email to the user when they log in |
| **Send email when item is shown** | Sends an email when a user views an item's password |
| **Send email on password change** | Notifies the user by email when their login password changes |
| **Manual history entries** | Allows users to add free-text notes to an item's history |

---

## Integration

External service connections.

| Option | Description |
|--------|-------------|
| **Enable Syslog** | Forwards audit events to a remote syslog server |
| **Syslog host** | Hostname or IP of the syslog receiver |
| **Syslog port** | UDP port for syslog (typically 514) |

---

## API

Options governing the REST API and the clients built on it (browser extension, mobile application). The endpoints themselves are described in the [API documentation](api/api-basic.md).

| Option | Description |
|--------|-------------|
| **Offline synchronization window (days)** (`offline_sync_window_days`) | How long a mobile device may stay offline and still catch up incrementally when it reconnects. Beyond this delay the device rebuilds its local cache instead. Default: 90. Set to 0 for no limit |
| **Allow the browser extension to save passkeys** (`webauthn_provider_enabled`) | *Browser Extension* tab. Lets the extension keep the passkeys of third-party sites in items and sign in with them. Default: off. See [Passkeys](../features/passkeys.md) |
| **Notify users when a passkey is saved** (`webauthn_email_on_add`) | *Browser Extension* tab. Emails the user who saved a passkey. Default: on |

> 🔔 **The synchronization window is not a data retention.** Lowering it deletes no item, no password and no history — item history and audit logs are governed separately and are never affected. It only bounds how far back the incremental catch-up reaches: a device outside the window downloads its vault again instead of downloading only what changed. Raising it costs database space; lowering it costs bandwidth for devices that were offline a long time.

---

## Items

Item and folder behaviour.

| Option | Description |
|--------|-------------|
| **Allow folder duplication** | Users can duplicate entire folders |
| **Allow item duplication** | Users can copy items to another folder |
| **Allow item duplication in same folder** | Users can copy an item within the same folder |
| **Show only accessible folders** | Hides folders the user has no access to, instead of greying them out |
| **Create items without password** | Allows saving an item with an empty password field |
| **Maximum last items** | Number of recently viewed items shown in the widget (default: 7) |
| **Edition lock release delay** | Seconds of inactivity before an item's edit lock is automatically released (default: 9) |
| **Allow users to create folders** | Standard users can create sub-folders within their accessible tree |
| **Enable favourites** | Activates the Favourites feature (see [Favourites](../features/favourites.md)) |
| **Show copy icons** | Displays quick copy-to-clipboard icons on the item list row |
| **Show item description** | Displays the description field in the item list |
| **Show folder tree counters** | Adds item counts next to folder names in the tree |
| **Restricted search by default** | Search is scoped to a subset of folders unless the user explicitly widens it |
| **Highlight selected items** | Highlights the currently selected item row |
| **Highlight favourite items** | Applies a visual marker to favourite items in the item list |

---

## Users

User account and permission behaviour.

| Option | Description |
|--------|-------------|
| **Manager can edit items** | Allows managers to edit items in folders they manage |
| **Manager can move items** | Allows managers to move items between folders they manage |
| **Subfolders inherit parent rights** | A sub-folder automatically inherits the permission configuration of its parent |
| **Anyone can modify items** | All users with folder access can edit items regardless of permission type |
| **Users can create root folders** | Standard users can create top-level folders |
| **Enable massive operations** | Allows bulk move and delete on multiple items at once |
| **Disable profile editing** | Prevents users from changing their name, last name, and email |
| **Disable language preference** | Locks the interface language to the system default for all users |
| **Disable timezone preference** | Locks the timezone to the system default for all users |
| **Disable tree load strategy preference** | Removes the user's ability to choose between lazy and full folder tree loading |
| **Disable drag-and-drop** | Prevents reordering items or folders via drag-and-drop |
| **Enable personal folders** | Each user gets a private folder visible only to them (see [Folders](../features/folders.md#personal-folders)) |

---

## Collaboration

Sharing, export, and content features.

| Option | Description |
|--------|-------------|
| **User can propose One-Time-View links** | Enables Secure Send: users can generate time-limited sharing links for items (see [Items — Secure Send](../features/items.md#secure-send)) |
| **One-time-view (OTV) links expire after XX days** | Maximum validity of a Secure Send link, in days, also proposed by default in the sender form (default: 7) |
| **Public sharing address** | HTTPS base URL used for external item and note links. The existing `otv_subdomain` setting also accepts a hostname or legacy short prefix; see below. |
| **Secure Send maximum number of views per link** | Upper bound that a sender may assign to one link; use `1` unless the use case explicitly requires more |
| **Secure Send audit retention (days)** | `0` keeps all audit events (default); `1`–`36500` opts into irreversible deletion of older events, up to 1000 per scheduled orphan-object maintenance run. Statistics may become incomplete; see [Audit retention](../install/secure-send.md#audit-retention). Backups and external logs retain separate policies. |
| **Force a passphrase on every Secure Send link** | Requires the sender to protect every new link with a separate passphrase; recommended for Internet-facing links |
| **Allow sending ad-hoc notes/secrets** | Permits links that are not attached to an item or folder; leave disabled unless that independent lifecycle is required |
| **Show the sender’s profile name** | Publishes the sender’s first and last name on the public page before passphrase entry. Enabled on fresh installs; existing instances must opt in after upgrading. |
| **Allow printing** | Enables the print / export-to-PDF feature |
| **Roles allowed to print** | Restricts the print feature to selected roles |
| **Allow import** | Enables CSV and KeePass2 XML import (see [Import](../features/import.md)) |
| **Enable offline mode** | Users can export an encrypted HTML snapshot for offline consultation |
| **Offline mode key complexity** | Minimum password complexity required to protect an offline export |
| **Enable knowledge base** | Activates the built-in knowledge base feature. When enabled, the Knowledge Base entry appears in the navigation menu for all users (see [Knowledge Base](../features/knowledge-base.md)) |
| **Enable suggestions** | Users can submit password change suggestions to administrators |

### Public sharing address

Prefer an explicit HTTPS base URL, for example `https://share.example.com` or
`https://share.example.com:9443/vault`. This allows the public route to differ from
the main TeamPass route, including its port and installation path.

For a main address of `https://vault.example.com:8443/team`:

| Setting value | Public base address |
| --- | --- |
| `share` (legacy prefix) | `https://share.vault.example.com:8443/team` |
| `share.example.com` (hostname) | `https://share.example.com:8443/team` |
| `https://share.example.com` (full URL) | `https://share.example.com` |
| Empty | No public address; links use the main TeamPass URL |

A legacy prefix replaces a leading `www.` only. Public addresses use HTTPS;
the internal/main address retains its configured scheme for existing LAN installations.
Credentials, query parameters, fragments and unsafe paths are rejected when saving
the public setting. No DNS lookup or reachability probe is performed, so DNS setup
order cannot cause a silent fallback. Reload an already-open item page after changing
the setting to refresh its address preview.

#### Upgrading from a short prefix

Existing `otv_subdomain` values are resolved differently for newly generated links:

- Public links now always use HTTPS, even when the main TeamPass URL uses HTTP.
- A short prefix now retains the main URL's port and installation path. Verify that
  the public virtual host serves that path, or configure an explicit HTTPS base URL
  matching the public route.
- A dotted value such as `share.dmz` is now treated as a complete hostname. It is no
  longer prepended to the main TeamPass hostname.

Existing links remain usable when their hostname is unchanged because redemption
checks the host, not the URL path. If a dotted legacy value changes the resolved
hostname, verify or regenerate the affected links. Check the public route after the
upgrade; an explicit URL such as `https://share.example.com/vault` removes ambiguity.

Create DNS for the **resolved hostname**, configure a matching TLS certificate and
route that host/path to TeamPass's `public/` directory. DNS alone does not configure
the web server. For public links, the proxy must preserve the public `Host` header; arbitrary
`X-Forwarded-Host` values are not accepted as proof of the destination hostname.
Changing the configured hostname also changes where existing links may be redeemed;
regenerate links when changing public routing. Internal links do not require an exact
Host match, preserving reverse-proxy rewrites, DNS aliases and LAN names containing
underscores.

The public hostname is not an isolation boundary for the full TeamPass application.
If the vault should remain private, configure the public virtual host/reverse proxy
to expose only the exact generated OTV GET query, the `index.php?otv=1`
confirmation POST and required static assets, while retaining the internal route
for authenticated use. Do not redirect
public OTV requests to the private hostname. Never log link query parameters or
recipient POST bodies, which contain sharing credentials.

For deny-by-default Apache, Nginx/PHP-FPM and reverse-proxy examples, operating
system differences, validation tests and operational policy, see
[Secure Send deployment and hardening](../install/secure-send.md).

---

## Security posture & breach detection

Password hygiene features, all disabled by default. They are described in [Security posture](../features/security-posture.md).

| Option | Description |
|--------|-------------|
| **Enable HaveIBeenPwned password check** (`hibp_enabled`) | Checks item passwords against the Have I Been Pwned Pwned Passwords API when they are opened, using k-anonymity: only the first 5 characters of the password's SHA-1 hash leave the server |
| **Re-check interval (days)** (`hibp_check_interval_days`) | Days before a password is checked again when its item is opened. Default: 7 (1 to 365) |
| **Security posture dashboard** (`security_dashboard_enabled`) | Enables the per-user Security posture page and the security score badge |
| **Widely-shared threshold (users)** (`security_dashboard_overshared_threshold`) | An item shared with more users than this is flagged as widely shared. Default: 10 |
| **Minimum password length (characters)** (`security_dashboard_min_password_length`) | A shorter password is reported as weak. Default: 12 |
| **Proactive health nudges** (`security_nudges_enabled`) | In-app banner and item list marker for breached, weak, reused or overdue passwords (requires the Security posture dashboard) |
| **Email digest of at-risk passwords** (`security_nudges_email_enabled`) | Periodic counts-only email to each user concerned |
| **Email digest frequency (days)** (`security_nudges_email_frequency_days`) | Minimum number of days between two digests for a user. Default: 7 |
| **Stale scan threshold (days)** (`security_nudges_stale_scan_days`) | Beyond this age, the banner invites the user to run a new scan. Default: 14 |

> 🔔 Breach detection needs outbound HTTPS access to `api.pwnedpasswords.com`. Verify it if your server operates behind a strict firewall.

---

## Inactive Users

Automated management of accounts without recent web login or functional API/extension activity.

| Option | Description |
|--------|-------------|
| **Enable inactive user management** | Activates the automatic inactivity handling |
| **Inactivity threshold** | Days without recorded activity before an account is considered inactive (default: 90) |
| **Grace period** | Additional days before the action is applied (default: 7) |
| **Action** | What happens at threshold + grace period: `Disable`, `Soft delete`, or `Hard delete` |
| **Execution time** | Time of day at which the background job runs (default: 02:00) |

The **Run now** button executes the inactive user check immediately. The status panel shows the last run time, result, and a summary of affected accounts.

Functional API/extension activity includes user-visible item actions such as item reads, item search results, OTP retrieval, creations, updates, deletions, and imports. Authentication, token refresh, settings refresh, and folder list refreshes do not count as activity on their own.

> 🔔 **Hard delete** permanently removes the account and all its data. Use `Disable` or `Soft delete` if you may need to restore inactive accounts.
