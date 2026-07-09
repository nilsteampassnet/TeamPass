<!-- docs/install/security-hardening.md -->

# Encryption Security Hardening — Administrator Guide

## About this document

This **cumulative** document tracks the encryption and data-security hardening changes of TeamPass.
Each change (a "phase") adds a section: **what changes**, **why**, **the administrator action (if
any)**, **how to verify** and **how to roll back**.

> 📌 **Read this before any upgrade that touches encryption.** Some changes ship a **data
> remediation script** that must be run manually, always **after a database backup**.

**Audience:** TeamPass instance administrators.
**Technical references:** `.claude/docs/architecture-encryption.md` (architecture),
[phpseclib v1→v3 migration](../PHPSECLIB_V3_MIGRATION.md).

| Phase | Topic | Status | Admin action required |
|---|---|---|---|
| [Phase 1](#phase-1-personal-items-isolation-sec-8) | Personal items isolation (SEC-8) | ✅ Shipped | Optional — remediation script |
| Phase 2 | Authenticated AES encryption (AES-GCM, random IV/salt) | ⬜ Planned | To be documented |
| Phase 3 | Object key entropy (`KEY_LENGTH`) | ⬜ Planned | None (backward compatible) |
| Phase 4 | Key-distribution resilience (retry, fan-out) | ⬜ Planned | To be documented |

---

## Golden rule: always back up before running a remediation script

Any remediation script that **deletes** or **rewrites** data is **irreversible** without a backup.
Before running anything with `--execute`:

```bash
mysqldump -u <user> -p <database> > teampass_backup_$(date +%Y%m%d_%H%M%S).sql
```

Ideally, first replay the remediation on a **staging copy** restored from that dump.

---

## Phase 1 — Personal items isolation (SEC-8)

**Available from:** branch `improve/encryption-mecanisms` (commit `32a82be8d`) — 3.2.x.
**Database impact:** none (no `ALTER TABLE`, no new setting).
**User impact:** no visible change.

### What changes

When **creating a personal item** (in a personal folder), TeamPass now distributes the share key
**only to the owner** and to the internal recovery account **`TP_USER_ID`**. Previously, due to a
parameter bug, the key was distributed to **all** users of the instance.

This fix covers web **and** API creation, as well as import.

### Why (the risk being fixed)

A personal item must remain decryptable only by its owner (cryptographic isolation). With the old
code, any account holding a public key received a share key for someone else's personal item: only
the application access control (folder rights) still prevented reading it. The cryptographic layer
itself was bypassed.

> ℹ️ The internal `TP_USER_ID` account deliberately keeps a recovery key on personal items (this
> already matched the cleanup and migration behaviour). It enables server-side recovery without
> restoring access for any other user.

### Administrator action — remediating existing data

Personal items **created before** this fix and **never modified since** may still carry foreign
share keys in the database. A script cleans them up.

> ⚠️ The script **deletes rows**: **back up the database first** (see the golden rule). It
> **never** deletes anything for an item with an ambiguous owner (see "Reading the report").

**1. Analyse (`--dry-run` mode, the default, makes no change):**

```bash
# from the TeamPass installation root
php app/scripts/remediate_personal_sharekeys.php --dry-run
```

**2. Back up the database** (required before step 3).

**3. Apply:**

```bash
php app/scripts/remediate_personal_sharekeys.php --execute
```

### Reading the report

```
=== Summary ===
Personal items analysed   : 45    ← total number of personal items
Already clean             : 42    ← nothing to do (already compliant)
Items with foreign keys   : 0     ← items carrying foreign share keys
Skipped (unresolved owner): 1     ← owner could not be resolved → left untouched
Skipped (owner conflict)  : 2     ← folder owner ≠ creator → left untouched
Foreign sharekeys found   : 0     ← total foreign keys detected
```

- **Already clean** — compliant items (earlier automatic cleanups already did the work). No action.
- **Skipped (unresolved owner)** — a personal item whose tree root is not a valid personal folder
  (data inconsistency). **Intentionally left untouched.**
- **Skipped (owner conflict)** — the owner derived from the personal folder differs from the user
  recorded as the creator (`at_creation`). **Intentionally left untouched** — inspect such items
  manually if needed.
- **Items with foreign keys** — only these are cleaned in `--execute` mode.

### Verification

Re-run `--dry-run` after applying: `Foreign sharekeys found` must be `0`.

Additional SQL check (replace `<item_id>`; the system account ids are `9999997` TP, `9999999` API,
`9999991` OTV, `9999998` SSH):

```sql
-- Share key holders for a given personal item: only the owner and the system
-- accounts should remain.
SELECT user_id
FROM teampass_sharekeys_items
WHERE object_id = <item_id>;
```

### Rollback

- **Code** — `git revert 32a82be8d` (the function reverts to over-distribution, no data breakage).
- **Script deletions** — only reversible by **restoring the database backup** taken at step 2.

### Technical details

- Distribution: `storeUsersShareKey()` (`app/sources/main.functions.php`) — owner-only branch on
  the "personal folder" flag.
- Key reads unified on the migration-aware path `decryptUserObjectKeyWithMigration()` (SEC-7); item
  creation made atomic with a transaction (FUNC-6).
- Tested decision logic: `app/scripts/personal_sharekeys_logic.php` +
  `tests/Unit/PersonalSharekeysLogicTest.php`.
- Full analysis: `.claude/docs/architecture-encryption.md`.

---

## Template for future phases

> Copy this template to document a new change.

### Phase N — <Title> (<ID>)

**Available from:** <version>.
**Database impact:** <none | ALTER / new setting>.
**User impact:** <none | description>.

- **What changes:** …
- **Why (the risk being fixed):** …
- **Administrator action:** <none | script + procedure + backup>.
- **Verification:** <command / SQL query / counter>.
- **Rollback:** <code revert / disable flag / restore database>.
- **Technical details:** <links>.

---

## FAQ

**Q: Do I have to run the Phase 1 remediation script?**
A: No. The fix protects every **new** creation. The script only cleans foreign share keys inherited
by old personal items. If it reports `Foreign sharekeys found: 0`, no action is needed.

**Q: Can the script delete a legitimate key?**
A: No, by design: it only touches keys belonging neither to the owner nor to the system accounts,
and it **skips** any item with an ambiguous owner (reported under "Skipped").

**Q: Do users lose access to their personal items?**
A: No. The owner always keeps their key; only the cryptographic access wrongly granted to other
accounts is removed.

**Q: Can I run the script multiple times?**
A: Yes. It is idempotent: a second run will find no more foreign keys.

**Q: Is a maintenance window / downtime required?**
A: No. `--dry-run` is read-only; `--execute` works item by item in short transactions. A prior
backup remains mandatory.
