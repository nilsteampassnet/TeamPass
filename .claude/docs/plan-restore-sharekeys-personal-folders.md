# Restore missing sharekeys — extension to personal folders

> Branch `improve/restore-sharekeys-personal-folders`, cut from `develop` at `966318be0`
> (the merge of `fix/5342-custom-fields-data-loss`). **Plan only — no implementation yet.**
> Target release: 3.2.2. Delete this file once the feature has shipped — it is a plan,
> not an architecture reference.
>
> Origin: public commitment made on
> [issue #5342](https://github.com/nilsteampassnet/TeamPass/issues/5342) —
> *"[the tool] only covers shared folders — I am extending it to personal folders as part of
> this fix."* The reporter's own data is entirely in shared folders, so this is not a blocker
> for #5342 and was split out deliberately.

---

## 1. What the tool does today

**Tools → Restore missing sharekeys** (admin only) rebuilds the per-user RSA sharekeys that let a
user decrypt an object. Four AJAX handlers plus one background task:

| Step | Handler | File |
|---|---|---|
| 1. Analyze (read-only) | `restore_missing_sharekeys-analyze` | `app/sources/tools.queries.php:870` |
| 1b. Show details | `restore_missing_sharekeys-details` | `app/sources/tools.queries.php:952` |
| 2. Seed TP_USER (synchronous, batches of 25) | `restore_missing_sharekeys-seed` | `app/sources/tools.queries.php:1062` |
| 3. Launch background repair | `restore_missing_sharekeys-launch` | `app/sources/tools.queries.php:1178` |
| Fan-out | `handleRestoreMissingSharekeys()` | `app/scripts/traits/SharekeysRepairTrait.php:45` |

Scope definition shared by steps 1–2: `restoreSharekeysScopeDefs()`
(`app/sources/tools.queries.php:1255`) — three scopes (`items`, `fields`, `files`), each
**filtering personal objects out** with `perso = 0`.

UI: `app/pages/tools.php:350-380`, JS `app/pages/tools.js.php:667-900`.

**Why two steps.** The background task runs in CLI, with no human session, so it can only use
`TP_USER_ID`'s private key — the one key the server can unwrap on its own
(`cryption(users.pw)` → `decryptPrivateKey()`, `SharekeysRepairTrait.php:56-60`). Step 2 exists to
create that reference key when it is missing, using the **admin's in-session** private key. Step 3
then fans the object key out to every eligible user.

---

## 2. Why the naive extension is a security regression

Dropping `perso = 0` would make `restoreScopeMissingSharekeys()`
(`SharekeysRepairTrait.php:142`) distribute personal objects' keys to **every eligible user** —
which is exactly SEC-8, the critical leak fixed in June 2026
(`.claude/docs/architecture-encryption.md` → `storeUsersShareKey()` behavior, and the
`project_personal_items_leak_tp_user_redistribution` regression that followed it).

**Invariant I1 — a personal object carries sharekeys for its owner and `TP_USER_ID` only.**
Enforced at creation by the owner-only branch of `storeUsersShareKey()`, lazily by
`EnsurePersonalItemHasOnlyKeysForOwner()` (`app/sources/main.functions.php:9401`), and in bulk by
`app/scripts/remediate_personal_sharekeys.php`.

So the personal path is **not the same algorithm with a different filter**. It is a second
algorithm: *repair one key, for one user*, instead of *fan one key out to N users*.

---

## 3. The three states of a personal object

For a personal object, only two rows may exist. Which one survives decides who can repair it.

| State | `TP_USER` key | Owner key | Repairable by | Notes |
|---|---|---|---|---|
| **A** | present | missing / empty / v1 | **the server** (background task) | The normal repairable case. Symmetric to the shared path. |
| **B** | missing | present | **the owner only**, from their own session | The admin cannot help: the owner's private key is AES-wrapped with the owner's password. |
| **C** | missing | missing | **nobody automatically** | Report it. Only a pre-SEC-8 leftover foreign key holder could re-save the item — and `remediate_personal_sharekeys.php` is designed to delete exactly those. Treat as unrecoverable. |

**Consequence for step 2.** The admin-seeded reference key (`-seed`) has **no equivalent** on the
personal path: post-SEC-8 an admin holds no key on somebody else's personal object, and relying on
a pre-SEC-8 leftover would be both unreliable and contrary to the remediation script. Therefore:

> **Rule: `restore_missing_sharekeys-seed` must never be run on a personal scope.**
> State B is seeded by the owner, in the owner's own session, or not at all.

---

## 4. Bugs found while designing (Phase 0 — do these first, they stand alone)

### R1 — the personal exclusion is flag-only, so it leaks legacy personal sub-folders

`SharekeysRepairTrait.php:76` uses `getAllPersonalFolderIds()`, which selects
`nested_tree WHERE personal_folder = 1` — the folder's **own flag**. A sub-folder created under a
personal root keeps `personal_folder = 0` when the flag was never written (legacy data,
`copy_folder`, import) — this is documented on `getPersonalFolderIdsWithDescendants()`
(`app/sources/main.functions.php:907`), which exists precisely to fix that class of bug and is
**not** used here. The `items` scope has the same hole (`n.personal_folder = 0`,
`SharekeysRepairTrait.php:86`), and `items.perso` is not a fallback — the trait's own comment
(`:73-75`) says it is `0` on items created while the client sent `folder_is_personal = 0`.

⇒ An item in a legacy personal sub-folder is currently **fanned out to every eligible user** by
the repair task. Same-shaped hole in `getForeignPersonalFolderIds()`
(`app/sources/main.functions.php:942`), which also builds on `getAllPersonalFolderIds()`.

**Fix:** switch both personal-exclusion sites to `getPersonalFolderIdsWithDescendants()`.
Small, standalone, and worth landing before anything else here — it is a live SEC-8-class leak,
independent of this feature.

### R2 — Analyze and the repair task do not cover the same objects

`restoreSharekeysScopeDefs()` filters on `perso` **only** (no `nested_tree` join at all), while the
trait additionally excludes personal folders. Analyze therefore counts objects the repair task will
never touch: `missing_pairs` is inflated, and an admin can watch a number stay non-zero across
repeated repairs with no explanation.

**Fix:** make one definition authoritative. Extract the scope/exclusion SQL into a shared helper
(see §6) so `tools.queries.php` and `SharekeysRepairTrait.php` cannot drift again — this is also a
prerequisite for adding a fourth "personal" scope cleanly.

---

## 5. Target behaviour

### Admin side (Tools page)

1. Analyze reports a **second table**, "Personal objects", with per-scope columns:
   `objects`, `owner_key_missing` (state A + B), `recoverable` (state A), `unrecoverable`
   (state C), `owner_unresolved`.
2. Repair processes personal objects in **state A only**, owner-only, in the same background task.
3. States B and C are listed in "Show details" with the owner's login, so the admin can tell the
   right person to run the self-repair — never presented as something the admin can fix.
4. Step 2 (`-seed`) keeps its current scopes untouched.

### Owner side (self-repair) — state B

The owner's session holds the cleartext private key, so a bounded pass can seed `TP_USER` for their
own personal objects. Options considered:

| Option | Pros | Cons |
|---|---|---|
| **(a) Explicit button in the user profile** *(recommended)* | Explicit, auditable, bounded, reuses the `-seed` batching pattern | The user must be told to click it |
| (b) Lazy seed at login | Zero user action | N×RSA-4096 on the login path — the exact cost FUNC-1 moved off the HTTP thread |
| (c) Background task | Consistent with the rest | Impossible: no session, no owner private key. **Ruled out.** |

**Recommendation: (a)**, plus a discreet notice shown only when the user actually has personal
objects missing their reference key (one `COUNT(*)`, cached per session).

---

## 6. Implementation

### 6.1 Shared scope definition (Phase 0, fixes R2)

New DB-free-ish helper next to `restoreSharekeysScopeDefs()`, used by **both** consumers:

```php
/**
 * @param bool $personal false = shared objects only, true = personal objects only
 * @return array<string, array{table: string, from: string, where: string}>
 */
function restoreSharekeysScopeDefs(bool $personal = false): array
```

Personal containment is folder-based, never `perso`:

```sql
-- shared  (existing behaviour, now folder-aware)
... AND i.id_tree NOT IN (:personalFolderIdsWithDescendants)
-- personal
... AND i.id_tree     IN (:personalFolderIdsWithDescendants)
```

`getPersonalFolderIdsWithDescendants()` returns `[]` when the feature was never used — both
branches must handle the empty list (shared ⇒ no filter, personal ⇒ no objects, skip entirely).

### 6.2 Owner resolution

Reuse the existing, unit-tested module — do **not** write a third copy:

- `app/scripts/personal_sharekeys_logic.php`
  - `personalRootOwnerId(?array $rootNode, array $systemUserIds): ?int` (`:66`)
  - `personalOwnerConflictsWithCreator(int $folderOwner, int|string|null $atCreationUserId): bool` (`:92`)
  - `personalSharekeyKeepList(int $ownerId, array $systemUserIds): array` (`:109`)
- `app/scripts/personal_sharekeys_remediation.php`
  - `resolvePersonalFolderOwner(int $folderId, array $systemUserIds): ?int` (`:74`)

**Rule: unresolved or conflicting owner ⇒ skip and report, never guess.** Same conservative stance
as the remediation script.

### 6.3 Background task — personal pass

New private method in `SharekeysRepairTrait`, called from `handleRestoreMissingSharekeys()` after
the three shared scopes:

```php
/**
 * @return array{created: int, unrecoverable: int, owner_unresolved: int}
 */
private function restoreScopePersonalSharekeys(
    string $objectsQuery,
    string $sharekeysTable,
    string $tpPrivateKey,
    string $tpPublicKey
): array
```

Per object: resolve the owner from the folder → cross-check `log_items.at_creation` → decrypt the
object key from the **`TP_USER` sharekey** with `decryptUserObjectKeyWithMigration()` → write
**one** row for the owner via `insertOrUpdateSharekey()`.

Then, in the same pass, **delete any foreign sharekey** on that object
(`user_id NOT IN [owner, TP_USER_ID, API_USER_ID, OTV_USER_ID, SSH_USER_ID]`) — the repair is the
natural place to restore I1, and it mirrors `EnsurePersonalItemHasOnlyKeysForOwner()`. Guard it the
same way the remediation script does: only when the owner was resolved **without conflict**.

> **Rule: no `batchUpsertSharekeys()` fan-out on this path.** One object ⇒ at most one new row.

Reuse `restoreScopeMissingSharekeys()`'s batching skeleton (id-ascending, `LIMIT 100`, periodic
`background_tasks.updated_at` heartbeat) — do not invent a second pagination.

### 6.4 Owner self-repair handler

New case in `app/sources/users.queries.php` (user-scoped, **not** admin-only), modelled on
`restore_missing_sharekeys-seed`:

```
case 'seed_personal_sharekeys':
  - session gate: user-id present, key check
  - scope: objects in THIS user's personal tree only (folder title = user id)
  - for each object where TP_USER has no valid v3 key and the caller HAS one:
      decryptUserObjectKeyWithMigration(caller key) -> encryptUserObjectKey(TP_USER public key)
      -> insertOrUpdateSharekey()
  - batches of 25, returns {seeded, failed, lastId, finished}
```

Then a `restore_missing_sharekeys-launch`-equivalent is **not** needed: once `TP_USER` holds the
key, the object is in state A and the admin's normal repair (or the next scheduled run) finishes
the job.

Remember the shim rule: this lives in an existing handler file, so no new `public/sources/` proxy
is required. A **new** `*.queries.php` would need one.

### 6.5 Files to touch

| File | Change |
|---|---|
| `app/sources/tools.queries.php` | `restoreSharekeysScopeDefs(bool $personal)`; personal counters in `-analyze`; personal rows in `-details`; **`-seed` unchanged** |
| `app/scripts/traits/SharekeysRepairTrait.php` | `getPersonalFolderIdsWithDescendants()` (R1); shared defs (R2); new `restoreScopePersonalSharekeys()` |
| `app/sources/main.functions.php` | `getForeignPersonalFolderIds()` → descendants-aware (R1) |
| `app/sources/users.queries.php` | `seed_personal_sharekeys` |
| `app/pages/tools.php` / `tools.js.php` | second results table + personal details rows |
| `app/pages/profile.php` / `profile.js.php` | self-repair button + notice |
| `app/includes/language/english.php` + `french.php` | keys below |
| `tests/Unit/` | see §7 |

### 6.6 Language keys (append after `restore_missing_sharekeys_seed_report`, english.php:49)

```
restore_missing_sharekeys_personal            'Personal objects'
restore_missing_sharekeys_personal_tip        explains owner+TP_USER only, and that the admin cannot repair state B
restore_missing_sharekeys_owner               'Owner'
restore_missing_sharekeys_owner_unresolved    'Owner could not be determined - skipped'
restore_missing_sharekeys_owner_action        'Only <owner> can repair this, from their own profile'
personal_sharekeys_selfrepair                 'Repair my personal items encryption keys'
personal_sharekeys_selfrepair_tip             one sentence, no jargon
personal_sharekeys_selfrepair_report          'Keys repaired: #seeded#. Not repairable: #failed#.'
personal_sharekeys_selfrepair_none            'No repair needed.'
```

**No schema change.** No new setting. No new background process type.

---

## 7. Tests

Unit (DB-free / sentinel, the house style):

1. `restoreSharekeysScopeDefs(true)` and `(false)` produce **disjoint** object sets — a sentinel on
   the SQL, so the two can never overlap again (R2).
2. Neither definition uses `getAllPersonalFolderIds()`; both use
   `getPersonalFolderIdsWithDescendants()` (R1).
3. `SharekeysRepairTrait` calls `batchUpsertSharekeys()` **only** from the shared path — a regex
   guard proving the personal path cannot fan out (SEC-8 / I1).
4. `restore_missing_sharekeys-seed` source contains no personal scope.
5. Owner resolution delegates to `personalRootOwnerId()` / `personalOwnerConflictsWithCreator()`
   rather than re-implementing the `title`-is-user-id rule.

Extend `tests/Unit/PersonalSharekeysLogicTest.php` where the logic module gains cases; new
`tests/Unit/RestoreSharekeysScopeTest.php` for 1–4.

Manual matrix (each in a personal folder, with a second non-admin user to prove non-leakage):

| # | Setup | Expected |
|---|---|---|
| 1 | State A item | Repaired; owner + TP_USER only; user 2 gets nothing |
| 2 | State B item | Listed, owner named, admin repair changes nothing; owner's self-repair fixes it |
| 3 | State C item | Reported unrecoverable, untouched |
| 4 | Item in a **legacy personal sub-folder** (`personal_folder = 0`, `perso = 0`) | Treated as personal — this is the R1 regression test |
| 5 | Personal item with a leftover foreign sharekey | Foreign key removed, I1 restored |
| 6 | Personal folders disabled instance-wide | Personal table absent, zero extra queries |
| 7 | Encrypted custom field + attachment on a personal item | All three scopes covered |

---

## 8. Non-goals

- Repairing another user's state B objects as an admin. Impossible without their password;
  presenting it as possible would be worse than not offering it.
- Touching `sharekeys_logs` (password history is master-key encrypted — see SEC-6).
- Reviving `perform_fix_pf_items` (`tools.queries.php:94-300`), the legacy 2.x→3.x
  "reset personal items to Defuse" tool. Unrelated, and it deletes sharekeys.
- Any change to `storeUsersShareKey()`. The write paths are correct; this is a repair tool.

---

## 9. Suggested commit split

1. `Fix: exclude legacy personal sub-folders from sharekey redistribution` (R1)
2. `Refactor: one scope definition for the sharekeys repair tool` (R2)
3. `Add personal objects to the Restore missing sharekeys analysis`
4. `Repair personal object sharekeys owner-only in the background task`
5. `Let a user rebuild the reference key of their own personal items`
6. `Add tests for the personal sharekeys repair scope`
