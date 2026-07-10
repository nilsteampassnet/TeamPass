<!-- docs/manage/access-reviews.md -->

## Overview

**Access recertification campaigns** answer the standing audit question *"does this role still need access to this folder?"* — a hard requirement of ISO 27001 (A.5.18), SOC 2 and DORA.

A campaign takes a **snapshot** of the role ↔ folder access grants at launch. Each grant is then reviewed and either **attested** (access is still needed) or **revoked** (the grant is removed from TeamPass, for real). Every decision is stored with its author and timestamp: the campaign itself is the least-privilege evidence you hand to the auditor.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → Access recertification campaigns**). It is disabled by default. The Recertification page is **admin-only** in this version.

---

## Running a campaign

1. Open the **Recertification** page (admin sidebar).
2. Give the campaign a label (e.g. *Q3 access review*) and choose a **scope**: all folders, or one folder and its subfolders.
3. Click **Launch campaign**. TeamPass snapshots every role/folder grant in scope.
4. Review each grant:
   - **Attest** — the role still needs this access. The decision is recorded, nothing changes.
   - **Revoke** — the role should no longer have this access. The grant is **removed from the Roles configuration immediately** (same effect as removing it on the Roles page: affected users lose the folder on their next tree refresh, and are notified in real time). A confirmation is required.
5. When every grant is decided, the **Close** button appears. Closing freezes the campaign as evidence.

### Rules that protect the evidence

- **Decisions are immutable** — a decided grant cannot be re-decided.
- **A campaign closes only when 100% decided** — no half-reviewed evidence.
- **Titles are snapshotted** — role and folder names are copied into the campaign, so the evidence stays readable even if a role or folder is later renamed or deleted.
- Every campaign start, close and revocation is also written to the **system log** (visible in the *Access changes* compliance report).

---

## Exporting evidence

Each campaign can be exported to **CSV** (button in the campaigns list): one row per grant with role, folder, access type, decision, reviewer and timestamp. The file uses RFC 4180 quoting with spreadsheet formula injection neutralised.

---

## What is in scope

The snapshot covers **role → folder grants** (`W`, `ND`, `NE`, `NDNE`, `R`) on non-personal folders — the same grants managed on the Roles page. Personal folders are never included. User-specific folder allowances/denials (per-user overrides) are not part of the campaign in this version.

---

## Settings & storage

| Setting | Default | Purpose |
|---------|---------|---------|
| `access_reviews_enabled` | `0` | Master switch for the Recertification page |

Campaigns are stored in two companion tables: `teampass_access_reviews` (campaign header) and `teampass_access_review_items` (one row per grant, with the decision). No existing table is altered.
