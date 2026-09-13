<!-- docs/features/renewal.md -->

## Overview

The **Renewal** page helps you identify items whose passwords are approaching or have passed their expiration date, so you can plan and execute password rotations in a timely manner.

It is available to non-administrator accounts, including users, managers, HR managers and read-only users. Results only include items you can access, respecting current folder permissions, personal folders and item-level user or role restrictions. Administrators configure expiration and can use the existing compliance reports for supervision.

Individual item renewal is available independently of the administrator's folder-expiration setting. The **Renewal** menu remains available to non-administrator accounts when folder expiration is disabled.

---

## How expiration works

An item can have an optional **password renewal period** of its own. On the item's main tab, enable **Set a renewal period for this item**, enter a whole number of days (1–36500), and save. This is available for personal and shared items to users who can edit the item. Policy changes are recorded in the item history.

Folders can also have a period configured by an administrator or an authorized folder manager. Folder periods apply only when **Settings → Security → Activate item expiration feature** is enabled. When both policies are active, the **shortest period wins**: an individual choice cannot extend the folder's deadline. Disabling the individual option removes only that rule; any active folder rule remains.

The deadline is calculated from the last password change, or creation if the password has never changed. Changing a renewal setting or moving an item does not reset its password age. Existing items start with no individual rule after the database upgrade.

Expired items are visually flagged in the main item list (coloured indicator next to the item label).

The item detail header shows an expiration badge with the effective deadline, including when it comes from the folder. It is blue for later deadlines, orange when due within 14 days and red when expired. The item list highlights upcoming deadlines within 14 days and expired items with matching badges. A policy with an unknown password age is labelled as active on the detail card, without inventing an expiration date.

Opening a folder displays its active renewal rule above the item list, including when the folder is empty. Individual items may have deadlines even when that folder has no active rule.

The item creation/edit form also displays the selected folder's policy and the applicable deadline. For new or copied items, the date is an estimate based on creation today; saving starts the actual period. For existing items, the preview uses the last password change (or creation date), and changing the destination folder refreshes it. Changing the password recalculates the deadline when saved.

Drag-and-drop moves show the destination policy and deadline before confirmation when a renewal period applies. Bulk moves from Search display the same preview for each selected item. A move does not reset password age: a warning identifies items that would already be expired in the destination folder.

**Renewal expiration does not delete items.** Consultation can be restricted until someone with edit permission renews the password. Automatic deletion by date or number of consultations is a separate feature.

---

## Using the Renewal page

1. Navigate to **Renewal** in the user sidebar, next to **Favourites**.
2. All accessible items with a known expiration date appear by default, ordered by deadline with already expired items first.
3. Use the **date picker** to limit the results to deadlines **up to that date**. Clearing the date restores all deadlines. Items without an active renewal policy or without a known expiration date are excluded.

The results table includes:

| Column | Content |
|--------|---------|
| **Label** | Item name |
| **Expiration date** | The date on which the item's password expires |
| **Folder** | Folder containing the item |

Click an item's name to open its normal item page. Opening it does not grant additional permissions. Read-only users cannot change the policy or password, and expired items can restrict consultation until an editor renews them.

> 💡 Use the date picker to look ahead: setting the date to a month from now lets you plan renewals in advance rather than reacting to expired items.

---

## Renewing a password

The Renewal page itself does not allow editing items directly. To renew a password:

1. Click the item name in the results table.
2. If you have edit permission, edit the item and change the password.
3. Save.

The expiration timer resets from the date of the password change.

---

## Governance and upgrades

For shared items, the posture summary and overdue rotation reports use effective deadlines, including individual policies when folder expiration is off. The folder coverage report distinguishes overdue folder SLAs from effective overdue items and shows individual-policy and covered-item counts. Personal folders and their descendants are excluded from these reports.

This change adds `items.renewal_period`, defaulting to zero, through the normal database upgrade. Run the upgrade wizard before using the new code; Docker runs the same migration on schema-floor replay. Replaying the migration preserves existing individual policies.

The API exposes `renewal_period` on item reads and accepts it on creation/update. Omit it on update to preserve the current value; send `0` to disable the individual policy. Copies retain the individual period and start a new password age. Moves retain both the period and the existing password age.
