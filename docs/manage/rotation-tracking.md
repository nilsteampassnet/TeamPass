<!-- docs/manage/rotation-tracking.md -->

## Overview

**Rotation policy tracking** turns the per-folder renewal period that TeamPass has always had into a *managed* capability: it answers the standing audit question **"are credentials rotated on schedule?"** with two dedicated reports on the [Reports](compliance-reports.md) page.

The rotation **SLA is the folder's renewal period** (in days), set when creating or editing a folder. No new field is introduced: if a folder has a renewal period of 90 days, every credential it holds is expected to be rotated at least every 90 days.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → Rotation policy tracking**). It is disabled by default and requires **Compliance reports** to be enabled too (the reports live on that page, which is admin-only).

---

## Reports

### Overdue rotations (folder SLA)

Every credential that is **past** — or within **14 days** of — the rotation SLA of its folder, most overdue first. Each row shows:

| Column | Meaning |
|--------|---------|
| Item / Folder | The credential and where it lives |
| SLA (days) | The folder's renewal period |
| Last change | Date of the last password change (from the item log; falls back to the creation date) |
| Due | Last change + SLA |
| Days overdue | How late the rotation is (0 for "due soon" rows) |
| Status | **Overdue** or **Due soon** (within the 14-day look-ahead) |

Items without any usable change date are excluded rather than reported with a bogus due date — the same rule the [Security Posture Dashboard](../features/breach-detection.md) applies to its *overdue* flag.

### Rotation SLA coverage per folder

One row per shared folder: its SLA, how many credentials it holds and how many are overdue. Sorted so the folders needing attention come first: most overdue items on top, then folders **holding items but without any SLA** (the coverage gap), then the rest alphabetically. The report title shows the overall coverage (e.g. *12/20 folders with an SLA (60%)*).

Use this report to find where a rotation policy is simply **not defined** — an auditor's first question after "is it followed?".

---

## How "last change" is determined

The last password change is read from the item audit log (`at_creation`, or `at_modification` with a password-change reason), exactly like the *overdue* flag of the Security Posture Dashboard — the three features always agree.

Personal folders are excluded from both reports.

---

## Permissions & settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `rotation_tracking_enabled` | `0` | Adds the two rotation reports to the Reports page |
| `compliance_reports_enabled` | `0` | Required — hosts the Reports page itself |

Both reports are **metadata-only**: item labels, folder titles and dates. No password value is ever read or displayed. Like every compliance report, they are read-only and exportable to CSV (formula-injection-safe).

---

## Related features

- [Compliance reports](compliance-reports.md) — the hosting page and the *rotation evidence (leaver flags)* report
- [Leaver risk](../features/leaver-risk.md) — flags credentials for rotation when a user leaves
- [Item renewal](../features/renewal.md) — the per-folder renewal period used as the SLA
