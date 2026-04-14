<!-- docs/features/items.md -->

## Overview

An **item** is the core object in Teampass. It stores credentials (login, password, URL) along with optional metadata: a description, custom fields, file attachments, and an OTP secret. Items always live inside a folder; the folder determines who can access them and with what permissions.

---

## Items list

When you open a folder, Teampass displays the list of items it contains. Each row shows the item label and, depending on your settings, quick-action icons (copy login, copy password, open URL).

Clicking a row expands it to reveal the full item details without leaving the list.

> 💡 To show items from sub-folders inside the same list, see [Show sub-directories](#show-sub-directories-with-items-list).

---

## Creating an item

1. Open the folder where you want to create the item.
2. Click the **New item** button (top of the list).
3. Fill in at minimum the **Label** field. All other fields are optional.
4. Click **Save**.

### Main fields

| Field | Description |
|-------|-------------|
| **Label** | Name of the item — the only required field |
| **Login** | Username or account identifier |
| **Password** | The credential. Use the generator icon to create a random password that meets the folder's complexity requirements |
| **URL** | Address of the associated service |
| **Description** | Free-text notes; supports basic formatting |
| **Tags** | Comma-separated keywords for filtering |
| **Icon** | FontAwesome icon code (see [Adding an icon](#adding-icon-to-item-or-folder)) |

### Details tab

The **Details** tab in the creation / edit form exposes additional fields:

| Field | Description |
|-------|-------------|
| **Custom fields** | Any fields defined by an administrator for the containing folder's category |
| **OTP secret key** | TOTP secret for two-factor authentication codes (see [OTP code](#otp-code-for-item)) |
| **Phone number** | Optional recovery phone number associated with the OTP |
| **Restricted to** | Limits who can see this item within the folder (specific users or roles) |

---

## Viewing an item

Click on an item row to expand it. The detail panel shows:

- Login, password (masked by default — click the eye icon to reveal), and URL.
- A **copy** icon next to each sensitive field.
- The password **strength indicator**.
- The **OTP code** if configured (rotating every 30 seconds).
- File attachments (download links).
- Item history (last modification date and author).

> 🔔 If **Log password item views** is enabled by the administrator, every time you reveal a password it is recorded in the audit log.

---

## Editing an item

1. Expand the item or open it via the action menu.
2. Click the **Edit** button (pencil icon).
3. Modify the fields you need.
4. Click **Save**.

> 🔔 Editing requires at least `W`, `ND`, or `NE` permission on the containing folder. Read-only (`R`) users see the item but the Edit button is hidden.

---

## Deleting an item

1. Open the item action menu (the `…` or gear icon on the item row).
2. Click **Delete**.
3. Confirm the deletion in the dialog.

> 🔔 Deletion requires `W` or `NE` permission. Users with `ND` (no delete) permission do not see the Delete option.

Deleted items may be recoverable by an administrator depending on the server's retention settings.

---

## Copying an item

Copying duplicates an item into the same folder or a different folder you have write access to.

1. Open the item action menu.
2. Click **Copy**.
3. Select the destination folder.
4. Confirm.

The copy is created as a new independent item. Changes to the copy do not affect the original.

---

## Moving an item

Moving transfers an item to another folder.

1. Open the item action menu.
2. Click **Move**.
3. Select the destination folder.
4. Confirm.

> 🔔 You need write permission on both the source folder (to remove the item) and the destination folder (to create it).

---

## File attachments

Items can have files attached to them (certificates, key files, documents, screenshots, etc.).

### Attaching a file

In the edit form, scroll to the **Files** section:

1. Click **Choose file** or drag and drop a file onto the drop zone.
2. The file uploads immediately.
3. Save the item.

### Downloading an attachment

In the item detail view, attached files appear as download links in the **Attachments** section. Click the file name to download it.

> 🔔 If **Secure image display** is enabled by the administrator, images are served through Teampass rather than as direct links, providing an additional layer of access control.

### Limits

The maximum file size is defined by the administrator in **Settings**. Exceeding it will show an error at upload time.

---

## Adding icon to Item or Folder

For each Item or Folder, it is possible to add an icon displayed as a prefix next to the label.

### FontAwesome icons

Teampass uses [FontAwesome Icons](https://fontawesome.com/search?o=r&m=free).

In edition mode:

* Set focus in the `Icon` field.
* Enter the FA code of the icon you want.
* Once you leave the field, the icon preview appears.

```
# Example — display a hippo icon
fa-solid fa-hippo
```

💡 You can also add FA modifier attributes such as `fa-xl`, `fa-rotate-90`. See [Styling with Font Awesome](https://fontawesome.com/docs/web/style/styling).

```
# Hippo icon, extra-large
fa-solid fa-hippo fa-xl
```

### Color themes

Icons are text-based, so you can apply a theme color using the CSS classes below:

| Name | Code |
| ---- | ---- |
| <span style="color:#007bff">Primary</span> | `text-primary` |
| <span style="color:#6c757d">Secondary</span> | `text-secondary` |
| <span style="color:#28a745">Success</span> | `text-success` |
| <span style="color:#17a2b8">Info</span> | `text-info` |
| <span style="color:#ffc107">Warning</span> | `text-warning` |
| <span style="color:#dc3545">Danger</span> | `text-danger` |
| <span style="color:#6610f2">Indigo</span> | `text-indigo` |
| <span style="color:#001f3f">Navy</span> | `text-navy` |
| <span style="color:#6f42c1">Purple</span> | `text-purple` |
| <span style="color:#f012be">Fuchsia</span> | `text-fuchsia` |
| <span style="color:#e83e8c">Pink</span> | `text-pink` |
| <span style="color:#d81b60">Maroon</span> | `text-maroon` |
| <span style="color:#fd7e14">Orange</span> | `text-orange` |
| <span style="color:#01ff70">Lime</span> | `text-lime` |
| <span style="color:#20c997">Teal</span> | `text-teal` |
| <span style="color:#3d9970">Olive</span> | `text-olive` |

```
# Hippo icon, extra-large, red
fa-solid fa-hippo fa-xl text-danger
```

In the items list, the icon is prefixed to the item label.

---

## One Time View

> OTV lets you share an item securely with someone who has no Teampass account.

Once enabled by the administrator, this feature generates a time-limited link for a single item. The link:

- Expires after a configurable duration (default: 7 days).
- Is valid for a configurable number of views (default: 1).

If the administrator has defined an **external subdomain**, the generated link uses that subdomain, making it accessible outside your organization's network even if the main Teampass instance is internal-only.

When one or more valid OTV links exist for an item, a badge showing the count is displayed on the item row.

**To create a One Time View link:**
1. Open the item action menu.
2. Click **One Time View**.
3. Configure expiry date and number of views.
4. Copy the generated link and share it.

---

## OTP code for Item

> Teampass can store and display a rotating TOTP code for an item, replacing the need for a separate authenticator app.

### Viewing the OTP code

When viewing an item that has OTP configured, the current 6-digit code is shown and refreshes automatically every 30 seconds. A progress indicator shows time remaining before the next rotation.

### Setting up OTP on an item

1. Open the item in edit mode.
2. Select the **Details** tab.
3. Fill in the **Secret key** field (provided by the target service, usually shown alongside the QR code).
4. Optionally fill in **Phone number** (useful for account recovery).
5. Enable the **Show OTP** toggle.
6. Save the item.

All users with access to the item will then see the same rotating code.

---

## Show sub-directories with Items list

Sub-folders can be shown inline inside the parent folder's item list, so you don't have to navigate into each sub-folder separately.

This feature is disabled by default. To enable it:

1. Open **My Profile** (top-right menu).
2. Go to the **Settings** tab.
3. Enable **Show sub-directories in main items list**.
4. Save.

Once active, you can also toggle the sub-folder list on the fly from the item list toolbar: **Item menu → Show/Hide directories**.

---

## Item history

Every change to an item (creation, edit, password view, deletion) is recorded. The history is visible in the item detail panel under the **History** tab.

If the **Manual insertions in item history log** option is enabled by the administrator, you can also add a free-text note to the history manually.

---

## Restricted items

An item can be restricted to a subset of users within the folder. When a restriction is set, only the listed users (or members of the listed roles) can see the item — even if they otherwise have access to the folder.

Restrictions are configured in the edit form under the **Details** tab → **Restricted to** field.

> 🔔 Item restrictions narrow access within a folder; they cannot expand it. A user who does not have folder access will never see the item regardless of restrictions.
