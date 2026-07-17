<!-- docs/features/leaver-risk.md -->

## Overview

The **Leaver risk** view answers a classic offboarding question: *"which shared credentials did this person have access to — and should therefore be rotated now that they are leaving?"*

When an employee leaves, disabling their TeamPass account stops future access, but every password they could **read** is still in their head (or their notes). The Leaver risk view lists exactly those credentials so the security team can rotate them, instead of guessing or rotating everything.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → Leaver / offboarding risk view**). It is disabled by default.

---

## What the report contains

For a given user, the report lists every item that meets **all** of these conditions:

- the user holds a decryption key on the item (they could actually read the password),
- the item is **shared**: at least one *other* active user can also read it,
- the item is not personal and does not sit in a personal folder,
- the item is active (not deleted).

Personal items are excluded by design: they concern only the leaver and nobody else can read them anyway. System accounts (API, OTV, SSH, TeamPass internal) never count as "other users".

Each row shows:

| Column | Content |
|--------|---------|
| **Label** | Item name |
| **Folder** | Folder containing the item |
| **Other users with access** | How many other people can read this credential |
| **Last password change** | Date of the most recent password change |
| **Status** | Rotation flag status (see below) |

No password value is ever included in the report — it is metadata only.

---

## Using the report

1. Open **Users** administration page.
2. In the action menu (cog) of the user, choose **Leaver risk**.
3. Review the list of shared credentials the account could read.
4. Click **Flag all for rotation** to mark every listed credential as *rotation pending*.

Flagging is recorded in the system log (`Leaver credentials flagged for rotation`) as governance evidence.

### Filtering by folder

Large access graphs can be narrowed down with the **folder filter** at the top of the report:

- select one or more folders to limit the report to items stored in them (no selection = all folders),
- tick **Include subfolders of the selected folders** to expand each selection to its whole subtree,
- click **Apply** to regenerate the report.

The filter is enforced **server-side**, and **Flag all for rotation** only covers the filtered report — you can safely flag one team's perimeter at a time.

### Flag lifecycle

| Status | Meaning |
|--------|---------|
| **Not flagged** | No rotation was requested for this item |
| **Rotation pending** | The item was flagged; its password has not been changed since |
| **Rotated** | The item password was changed *after* the flag was raised — done |
| **Dismissed** | The flag was manually dismissed |

The *Rotated* status is derived automatically: as soon as someone saves a new password on the item, the pending flag resolves itself. No manual bookkeeping is needed.

---

## Automatic flagging on disable

With **Settings → Options → Auto-flag credentials when a user is disabled** enabled, disabling an account automatically flags every shared credential it could read. This is the "zero-thought" offboarding mode: disable the account, and the rotation worklist is created for you.

---

## Permissions

The report and the flagging action are restricted to **administrators** and users allowed to **manage all users**. The feature is additionally gated by the `leaver_risk_enabled` setting.

---

## Related settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `leaver_risk_enabled` | `0` | Master switch for the Leaver risk view |
| `leaver_risk_auto_flag` | `0` | Auto-flag all shared credentials when an account is disabled |

Rotation flags are stored in the `teampass_rotation_flags` table (one row per item, updated on re-flag).
