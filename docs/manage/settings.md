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
| **Custom logo URL** | Replaces the Teampass logo in the header |
| **Custom login text** | Message displayed on the login page |

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
| **Password default length** | Pre-filled length in the password generator |

---

## Security

Access control, encryption, and session security parameters.

| Option | Description |
|--------|-------------|
| **Encrypt client-server communication** | Encrypts AJAX payloads between browser and server (AES) |
| **Enable HTTP request login** | Allows authentication via HTTP request parameters (use with caution) |
| **Enable STS/HSTS** | Adds the `Strict-Transport-Security` HTTP header; requires HTTPS |
| **Password life duration** | Number of days before a user's login password expires (0 = never) |
| **False login attempts** | Maximum failed logins before the account is locked |
| **Secure image display** | Serves item attachments through Teampass instead of direct URLs |
| **Password overview delay** | Seconds a revealed password stays visible before being masked again |
| **Activate item expiration** | Enables password expiration tracking on items (see [Renewal](../features/renewal.md)) |
| **Delete after consultation** | Marks items for deletion after a user views them (one-shot credentials) |
| **Clipboard password lifetime** | Seconds before the copied password is cleared from the clipboard |
| **Restrict item access to roles** | Item access is further restricted to explicitly listed roles |
| **PBKDF2 iterations** | Number of iterations for the transparent key recovery derivation function |

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
| **Enable One-Time View** | Users can generate time-limited sharing links for items (see [Items — One Time View](../features/items.md#one-time-view)) |
| **OTV expiration period** | Default validity duration for One Time View links in days (default: 7) |
| **OTV subdomain** | External subdomain used in OTV links, for sharing outside the internal network |
| **Allow printing** | Enables the print / export-to-PDF feature |
| **Roles allowed to print** | Restricts the print feature to selected roles |
| **Allow import** | Enables CSV and KeePass2 XML import (see [Import](../features/import.md)) |
| **Enable offline mode** | Users can export an encrypted HTML snapshot for offline consultation |
| **Offline mode key complexity** | Minimum password complexity required to protect an offline export |
| **Enable knowledge base** | Activates the built-in knowledge base feature |
| **Enable suggestions** | Users can submit password change suggestions to administrators |

---

## Inactive Users

Automated management of accounts that have not logged in for an extended period.

| Option | Description |
|--------|-------------|
| **Enable inactive user management** | Activates the automatic inactivity handling |
| **Inactivity threshold** | Days without login before an account is considered inactive (default: 90) |
| **Grace period** | Additional days before the action is applied (default: 7) |
| **Action** | What happens at threshold + grace period: `Disable`, `Soft delete`, or `Hard delete` |
| **Execution time** | Time of day at which the background job runs (default: 02:00) |

The **Run now** button executes the inactive user check immediately. The status panel shows the last run time, result, and a summary of affected accounts.

> 🔔 **Hard delete** permanently removes the account and all its data. Use `Disable` or `Soft delete` if you may need to restore inactive accounts.
