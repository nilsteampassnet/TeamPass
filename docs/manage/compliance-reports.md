<!-- docs/manage/compliance-reports.md -->

## Overview

The **Reports** page gives administrators one-click, auditor-ready **compliance reports**. Where the syslog/SIEM export targets engineers, these reports target **auditors**: they answer standing questions from ISO 27001, SOC 2 or NIS2 audits without manual log digging, and every report can be exported to CSV as evidence.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → Compliance reports**). It is disabled by default. The Reports page is **admin-only**.

---

## Available reports

### Access matrix — *who can access what*

One row per **user / role / folder** grant, with the access type (`W`, `R`, `ND`, `NE`, `NDNE`). This is the raw least-privilege evidence: it lists every grant contributing to a user's access, without merging, so the auditor can trace each entitlement to the role that provides it.

Personal folders and system accounts (API, OTV, SSH, TeamPass internal) are excluded.

### Access changes in period

All **user management events** recorded in the system log over a selectable period (default: last 30 days): account creations, locks/unlocks, role and permission changes, leaver flags... Each row shows the date, the action, the author and the target user.

### Vault posture summary

Aggregated counts from the [Security Posture Dashboard](../features/breach-detection.md) health scan: how many items are **weak**, **reused**, **breached**, **overdue**, without expiry, over-shared or orphaned — with percentages over the scanned population.

**Zero-knowledge preserved:** this report contains counts only. No password value and no item name ever appears — the admin view aggregates metadata flags, never cross-user plaintext.

### Rotation evidence (leaver flags)

Every credential flagged for rotation by the [Leaver risk](../features/leaver-risk.md) workflow, with the item, folder, leaver, author, date, and the **current state** (pending / rotated / dismissed). This closes the audit loop: "you flagged 37 credentials on the leaver's departure — were they actually rotated?"

---

## CSV export

After generating a report, click **Export CSV**. Files are UTF-8 (BOM included for spreadsheet compatibility) with RFC 4180 quoting. Cell values starting with `=`, `+`, `-` or `@` are prefixed with a quote to neutralise spreadsheet formula injection — evidence files are safe to open in Excel/LibreOffice.

---

## Permissions & settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `compliance_reports_enabled` | `0` | Master switch for the Reports page |

The page and all its AJAX handlers are gated by both the setting and the **admin** role. Reports are read-only: generating one changes nothing in the vault.
