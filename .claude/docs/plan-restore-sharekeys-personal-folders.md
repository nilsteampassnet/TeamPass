# Restore missing sharekeys — extension to personal folders

> Branch `improve/restore-sharekeys-personal-folders`, cut from `develop` at `966318be0`
> (the merge of `fix/5342-custom-fields-data-loss`). **Implemented 2026-08-25** — see §10 for what
> shipped and what did not.
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

## 4. Bugs found while designing (Phase 0 — ✅ both shipped on `develop`)

### R1 — the personal exclusion is flag-only, so it leaks legacy personal sub-folders — ✅ FIXED

`SharekeysRepairTrait.php` resolved the exclusion list with `getAllPersonalFolderIds()`, which
selects `nested_tree WHERE personal_folder = 1` — the folder's **own flag**. A sub-folder created
under a personal root keeps `personal_folder = 0` when the flag was never written (legacy data,
`copy_folder`, import); this is documented on `getPersonalFolderIdsWithDescendants()`
(`app/sources/main.functions.php:907`), which exists precisely to fix that class of bug and was
not used here. The `items` scope had the same hole through its `n.personal_folder = 0` join, and
`items.perso` is no fallback — the trait's own comment says it is `0` on items created while the
client sent `folder_is_personal = 0`.

⇒ An item in a legacy personal sub-folder was **fanned out to every eligible user** by the repair
task. Same-shaped hole in `getForeignPersonalFolderIds()` (`app/sources/main.functions.php:942`),
which also built on `getAllPersonalFolderIds()`.

**Fixed on `develop` at `398b63ebc`** (2026-08-25), ahead of this feature: the three sites now
call `getPersonalFolderIdsWithDescendants()`, the repair task filters its `items` scope on the
folder list instead of joining `nested_tree` to read the flag, and
`tests/Unit/PersonalFolderContainmentTest.php` guards all three. A third site was found while
fixing: `securityPostureAuthorizedFolders()` (`main.functions.php:1187`) passed the flag-only
list to `securityPostureResolveAuthorizedFolders()`, which subtracts it from the shared grants —
a foreign personal sub-folder could therefore stay authorized through a role grant.

`getAllPersonalFolderIds()` is now unused outside its own definition. It was kept deliberately:
it is a correct primitive ("folders whose own flag is set"), and the sentinel test is what stops
it being wired back into an exclusion path.

### R2 — Analyze and the repair task did not cover the same objects — ✅ FIXED

`restoreSharekeysScopeDefs()` filtered on `perso` **only** (no `nested_tree` join at all), while the
trait additionally excluded personal folders. Analyze therefore counted objects the repair task
would never touch: `missing_pairs` inflated, and a number that never reaches zero across repeated
repairs with nothing on screen to explain it. Measured on the development database: **27 phantom
items**, 0 fields, 0 files.

**Fixed on `develop` at `16fa65333`** (2026-08-25): `restoreSharekeysScopeDefs()` moved to
`app/sources/main.functions.php` — loaded by `tools.queries.php:42` and
`background_tasks___worker.php:33` alike — and now carries the personal exclusion itself
(`$notPersonal($alias)`, containment-based). `SharekeysRepairTrait` builds its paginated query from
those definitions instead of repeating the `FROM` clauses; its `$scopes` array is gone.
`tests/Unit/PersonalFolderContainmentTest.php` guards the single definition and the task's use of
it.

Side effect worth remembering: `-seed` no longer relies on an administrator sharekey left on a
personal object by a pre-SEC-8 install — a key `remediate_personal_sharekeys.php` exists to delete.

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

### 6.1 Shared scope definition — mostly done

`restoreSharekeysScopeDefs()` already lives in `app/sources/main.functions.php`, is shared by both
consumers and resolves personal containment through `$notPersonal($alias)` (R2). What is left is
the parameter that flips the exclusion into a selection:

```php
/**
 * @param bool $personal false = shared objects only, true = personal objects only
 * @return array<string, array{table: string, from: string, where: string}>
 */
function restoreSharekeysScopeDefs(bool $personal = false): array
```

```sql
-- shared   (current behaviour)
... AND i.id_tree NOT IN (:personalFolderIdsWithDescendants)
-- personal (to add)
... AND i.id_tree     IN (:personalFolderIdsWithDescendants)
```

Watch the empty list: `getPersonalFolderIdsWithDescendants()` returns `[]` when the feature was
never used. Today the shared branch correctly degrades to "no filter"; the personal branch must
degrade to **no objects at all** — an `IN ()` is invalid SQL, so the caller has to skip the scope
outright rather than build a query.

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
| `app/sources/main.functions.php` | `restoreSharekeysScopeDefs()` gains the `$personal` parameter |
| `app/sources/tools.queries.php` | personal counters in `-analyze`; personal rows in `-details`; **`-seed` unchanged** |
| `app/scripts/traits/SharekeysRepairTrait.php` | new `restoreScopePersonalSharekeys()` |
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
   `getPersonalFolderIdsWithDescendants()` — extend
   `tests/Unit/PersonalFolderContainmentTest.php` rather than starting a new guard (R1).
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

1. ~~`Fix: exclude legacy personal sub-folders from sharekey redistribution` (R1)~~ — done,
   `398b63ebc` on `develop`
2. ~~`Refactor: one scope definition for the sharekeys repair tool` (R2)~~ — done,
   `16fa65333` on `develop`
3. `Add personal objects to the Restore missing sharekeys analysis`
4. `Repair personal object sharekeys owner-only in the background task`
5. `Let a user rebuild the reference key of their own personal items`
6. `Add tests for the personal sharekeys repair scope`

---

## 10. What shipped (2026-08-25)

| Commit | Content |
|---|---|
| `398b63ebc` (develop) | R1 — personal exclusion by containment, three sites |
| `16fa65333` (develop) | R2 — one shared scope definition |
| `e73b5309f` | `$personal` scope + `personalFolderOwnersMap()` + owner-only repair pass |
| `f84b81085` | Personal objects in the Analyze table and in the details list |
| `484c85980` | Owner self-repair (`seed_personal_sharekeys` + My Profile) |

### Where the implementation diverged from this plan

- **The three states were wrong in §3, and the first analysis shipped them wrong.** §3 listed state B
  as "TP_USER key missing, owner key present" but the counters first written for it measured
  `owner_missing AND tp_missing`, which is state C. Caught by running the owner self-repair query on
  real data: it found 27 candidate objects where the analysis reported 1. The table now carries five
  columns — objects, owner cannot read, repairable here, needs the owner, not recoverable — and the
  details list says, per row, which of the last two a row is.
- **The scopes are one predicate and its negation**, not two independent WHERE clauses. That makes
  them a partition, so no object can fall outside both. It also settled the empty-list case §6.1
  worried about: with no personal folder the predicate degrades to `perso = 1` / `NOT (perso = 1)`,
  no `IN ()` is ever built and no scope has to be skipped.
- **`COALESCE(id_tree, 0)`** — an item whose folder was deleted has `id_tree = NULL`, so the
  predicate and its negation were both NULL and the object belonged to neither scope. 23 such items
  exist on the development database; they were invisible to the repair task before this branch.
- **`personalFolderOwnersMap()` had to restrict to the absolute root** (`parent_id = 0`). Sub-folders
  of a personal tree carry `personal_folder = 1` too, so the first version matched several roots per
  folder and dropped 35 folders as ambiguous — 41 of 50 personal items came back "owner unresolved".
- **Foreign sharekeys are removed only once the object is known to be recoverable.** §6.3 had the
  cleanup unconditional. On an object with no reference key those foreign keys are the only way back:
  a holder can still open the item and save it again, which is exactly what the Tools page advises.
- **`objectWhere`** was added to the scope definitions so the self-repair can scope objects itself
  (one user's own tree) without re-deriving the object-type condition.
- **The self-repair treats a legacy v1 reference key as missing**, like the shared seed (#5252). It
  therefore repairs strictly more than the analysis reports as "needs the owner"; the task can still
  decrypt a v1 key, it just should not have to.

### Not done

- **The manual matrix in §7 was not executed** — it needs a live instance with a second non-admin
  user. Everything was verified read-only against the development database instead: the scope
  partition, the owner map, the classification of all 51 personal objects, the analysis counters and
  the self-repair candidate query.
- **No write was performed on the development database.** The repair pass and the self-repair have
  never actually written a sharekey; only their SELECTs, their classification and their arithmetic
  were run.
- The three objects the pass refuses on that database are the intended refusals: an imported item in
  another user's tree, an item flagged personal outside any personal tree, and a personal root whose
  owner is not the item creator.
