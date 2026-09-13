<!-- docs/features/renewal.md -->

## Overview

The **Renewal** page helps you identify items whose passwords are approaching or have passed their expiration date, so you can plan and execute password rotations in a timely manner.

It is available to non-administrator accounts, including users, managers, HR managers and read-only users. Results only include items you can access, respecting current folder permissions, personal folders and item-level user or role restrictions. Administrators configure expiration and can use the existing compliance reports for supervision.

> 🔔 Password expiration must be enabled by your administrator (**Settings → Security → Activate item expiration feature**). The expiration period per folder is set in the folder configuration.

---

## How expiration works

Each folder can have a **password renewal period** (in days) defined by its administrator. When an item's password has not been changed for longer than that period, it is considered expired.

Expired items are visually flagged in the main item list (coloured indicator next to the item label).

---

## Using the Renewal page

1. Navigate to **Renewal** in the user sidebar, next to **Favourites**. The entry is shown when password expiration is enabled.
2. Use the **date picker** to select a target date.
3. The table updates to show accessible items that will have expired **by that date**. With no date selected, it shows items that are already expired.

The results table includes:

| Column | Content |
|--------|---------|
| **Label** | Item name |
| **Expiration date** | The date on which the item's password expires |
| **Folder** | Folder containing the item |

Click an item's name to open its normal item page. Opening it does not grant additional permissions; read-only users can consult the item but cannot change its password.

> 💡 Use the date picker to look ahead: setting the date to a month from now lets you plan renewals in advance rather than reacting to expired items.

---

## Renewing a password

The Renewal page itself does not allow editing items directly. To renew a password:

1. Click the item name in the results table.
2. If you have edit permission, edit the item and change the password.
3. Save.

The expiration timer resets from the date of the password change.

---

## Folder-level renewal reminders

In addition to the Renewal page, administrators can configure **renewal reminders** at the folder level. When enabled, users with access to the folder receive an email notification a configurable number of days before items in that folder expire.

Renewal reminder settings are configured per folder in the **Folders** administration page. See [Folders](folders.md) for details.
