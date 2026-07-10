<!-- docs/features/classification.md -->

## Overview

**Data classification** lets your organisation label each item with a sensitivity level, as required by governance frameworks (ISO 27001, SOC 2, internal data-handling policies):

| Level | Badge colour | Typical meaning |
|-------|--------------|-----------------|
| **Public** | green | No harm if disclosed |
| **Internal** | blue | For employees only |
| **Confidential** | orange | Restricted business impact if disclosed |
| **Restricted** | red | Severe impact — tightest handling rules |

Items without a label are **Unclassified**.

> 🔔 This feature must be enabled by an administrator (**Settings → Options → Data classification labels**). It is disabled by default.

Classification is **metadata**: it is stored next to the item (never inside the encrypted content), involves no cryptography, and is visible to anyone who can see the item.

---

## Classifying an item

1. Open an item: a coloured **tag badge** appears next to its title.
2. Click the badge and pick a level (or *Unclassified* to clear it).
3. The change is saved immediately and recorded in the **item history** as audit evidence (`classification changed`), with the author and timestamp.

Read-only users see the badge but cannot change it.

### Ownership

Each classification records who set it. In this version the **last classifier is the recorded owner** of the label — an explicit, separately assignable owner field exists in the data model for future use.

---

## Reporting

When [Compliance reports](../manage/compliance-reports.md) are enabled, the **Classification coverage** report shows how many shared items carry each label and how many remain unclassified — the classic "is our data classified?" audit artefact, exportable to CSV.

---

## Scope and limits (by design, in this version)

- **Labelling, filtering and reporting only** — there is no policy *enforcement* engine yet ("Restricted ⇒ MFA-gated view" etc. is deliberately deferred).
- Item-level labels only; folder-level classification defaults may come later.
- Labels apply to items in shared folders; personal items can be labelled by their owner but are excluded from the coverage report.

---

## Settings & storage

| Setting | Default | Purpose |
|---------|---------|---------|
| `data_classification_enabled` | `0` | Master switch for classification labels |

Labels are stored in the companion table `teampass_data_classification` (one row per classified item — no `ALTER` on the items table).
