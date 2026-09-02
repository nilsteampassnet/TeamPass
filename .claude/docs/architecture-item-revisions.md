# Item Revisions & Offline Synchronization

> Last updated: 2026-08-24 — target release 3.2.2
> Design study: `workReadmeFiles/item-revision-id-study.md`

Gives every item a **monotonic revision ID** so an offline client (the mobile vault) can tell
which of its cached items are stale, decide which side is newer, and pull only what changed.

## Why the pre-existing signals could not carry it

| Signal | Why it fails |
|---|---|
| `items.updated_at` | `varchar(30)`, 1s resolution, **bumped on a pure read** (`items.queries.php:3415-3424`, alongside `viewed_no+1`) and **skipped** by create, copy, soft delete, restore, attachment writes and the API custom-field-only update (`ItemModel.php:1499` guard). Not selected by the API, not indexed. |
| `misc.last_item_change` | One global epoch (`main.functions.php`, inside `logItems()`). Says *something* changed, never *what*. |
| `websocket_events` | Consumed then purged after `event_retention_hours` (default 24h) — a live push channel, not a replayable feed. |
| `items.item_key` | Random, generated at insert/copy, **never recomputed on update** — an identity, not a change token. |

`movePersonalItemToSharedFolderSynchronously()` (`main.functions.php:6665-6684`) already had to
compare `updated_at` **and** the `pw` ciphertext, with a comment explaining the timestamp alone is
too coarse. That workaround can now be replaced by a revision check.

## Data model

```sql
teampass_items.revision             INT UNSIGNED NOT NULL DEFAULT 0
teampass_items.revision_changed_at  BIGINT UNSIGNED NULL  -- timestamp paired with revision

teampass_items_revisions:
  revision           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY  -- THE global sequence
  item_id            INT(12)
  folder_id          INT(12)   -- folder at change time
  previous_folder_id INT(12)   -- move only
  action             VARCHAR(20)  -- created|updated|deleted|restored|moved|purged
  changed_by, changed_at
  KEY (item_id, revision), KEY (changed_at)
```

**The sequence is global, not per-item.** One column then answers both "did it change?"
(`revA != revB`) and "what changed since X?" (`revision > cursor`). A per-item counter cannot do
the second.

**Revision 0 is not backfilled.** It means "never changed since tracking was installed" and its
`revision_changed_at` is `NULL`. During upgrade, a positive revision timestamp is backfilled only
when the exact `(item_id, revision)` row is still present in the journal. If pruning already removed
that row, the date remains `NULL` rather than being guessed from the unreliable `items.updated_at`.

**The journal is not a history.** It records *that* a change happened and in which folder, never
*what* the value was. Item history stays `teampass_log_items` (+ `old_value` for passwords).

## Bumping

**Choke point: `logItems()`** (`app/sources/main.functions.php`), right after the
`misc.last_item_change` block it mirrors:

```php
if (itemRevisionShouldBump($action) === true) {
    bumpItemRevision($item_id, itemRevisionJournalAction($action, $raison), $id_user);
}
```

Bumping actions: `at_creation`, `at_modification`, `at_delete`, `at_restored`, `at_copy`,
`at_import`. This covers the satellites for free — custom fields, tags, attachments and OTP all log
`at_modification` with a `raison` sub-code.

**Rule: reads never bump.** `at_shown`, `at_password_shown`, `at_password_copied`,
`at_password_shown_edit_form`, `at_export`, `at_access`, `at_manual` are excluded — bumping them
would make every offline client re-download an item nobody touched.

**Rule: ciphertext-only rewrites never bump.** The Defuse→AES re-encryption
(`main.queries.php:3375`) and the custom-field re-encryption (`fields.queries.php`, `edit_field` /
`dataIsEncryptedInDB`) change the stored bytes but not the plaintext the client caches.

`bumpItemRevision()` computes one `changedAt = time()` value and stores it in both the journal row
and `items.revision_changed_at` alongside `items.revision`. **Per-request memo.** `update_item` calls
`logItems()` once per changed attribute (~15× per save);
the memo collapses them into one revision. A later call in the same request **rewrites the stored
row in place** when it knows more — a deletion, or the source folder of a move
(`itemRevisionShouldUpgradeEntry()`) — it never changes the timestamp. `resetItemRevisionMemo()` is called per subtask by
`background_tasks___worker.php`, which is long-lived.

**Rule: a new item write path that does not go through `logItems()` must bump explicitly.** The
five that exist today:

| Path | Why |
|---|---|
| `import.queries.php:1146`, `:1725` | CSV / KeePass import write `DB::insert(log_items)` raw |
| `upload.attachments.php:484` | same raw insert, for `at_add_file` |
| `utilities.queries.php:2156` (`tpHardDeleteItem`) | **before** the delete — it purges the item's `log_items` rows, so the journal row is the only survivor |
| `users.queries.php:1685`, `:5512`, `users_purge.functions.php:109` | hard delete of a leaving user's personal items |
| `fields.queries.php` (`case 'delete'`) | deleting a field or category strips values from every item, with no log and no `updated_at` — `bumpItemRevisionsForField()` journals them **before** the delete |

## API surface

- `revision` and `revision_changed_at` returned by `item/get`, `item/inFolders`,
  `item/findByUrl`, `item/create`, `item/update`
- `GET /api/v1/item/changes?since=&limit=` — the delta feed (see `api-reference.md`)
- `PUT /api/v1/item/update` accepts an optional `revision` precondition → `409` on mismatch

`revision_changed_at` is a Unix UTC timestamp in seconds, or `NULL` when no reliable date exists.
The delta feed takes the pair from the winning journal row after deduplication; tombstones cannot
read from `items` because a purged item no longer has a row.

## The sync window setting

`offline_sync_window_days` (`teampass_misc`, type `admin`, default `90`, `0` = never trim) —
**Settings → API → Offline synchronization window**. Pruning runs inside the existing
`app/scripts/task_maintenance_clean_orphan_objects.php`.

**Rule: never call this a retention.** TeamPass already uses "retention" for records that are
destroyed (`tasks_log_retention_delay`), and it genuinely has an item history — so
"item revisions retention" reads as "how long item history is kept", which is false and alarming.
Nothing depends on the journal except the ability to catch up incrementally: a client outside the
window is answered `full_sync_required` and rebuilds. It is a capability bound, not a deletion
policy, and the admin-facing copy says so explicitly.

## DB-free logic module

`app/sources/item_revisions_logic.php` (required by `main.functions.php` next to the other three
logic modules) holds every decision: `itemRevisionShouldBump()`, `itemRevisionJournalAction()`,
`itemRevisionShouldUpgradeEntry()`, `itemRevisionNeedsFullSync()`, `itemRevisionDedupeScan()`,
`itemRevisionClassifyScanRow()`, `itemRevisionResolveCursor()`, `offlineSyncResolveWindowDays()`,
`offlineSyncPruneCutoff()`. Unit-tested by `tests/Unit/ItemRevisionsLogicTest.php`.

## Known gaps

1. **Rights revocation emits no journal entry** — nothing changed on the items. Clients must also
   reconcile against `folder/writableFolders` and drop items whose folder disappeared.
2. **Attachments are not in the delta payload** — `getItems()` does not return files.
3. **The web UI is not revision-aware** — it uses edition locks (`items_edition`) instead. Only the
   API gets the `409`.
4. **Bulk custom-field deletion** journals one entry per affected item — a visible spike on large
   vaults, accepted as the price of not leaving every client silently stale.
