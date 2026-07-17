# LAPR Architecture (Linux Account Password Rotation)

> Feature branch `feature/lapr-mvp1`, target release **3.2.2**. Analysis and design in
> `workReadmeFiles/lapr/` (README + roadmap + spec-vs-codebase corrections C1–C16 +
> decisions/risks). MVP1 = Points 1–7.

Agentless, push-based rotation of **local Linux account passwords** over SSH, driven from
TeamPass. Reuses the existing item encryption model (objectKey + per-user RSA sharekeys), the
background-task pipeline, and phpseclib3. No parallel secret store, no new crypto primitive.

## Component map

| Concern | File |
|---|---|
| SSH transport | `app/includes/libraries/teampassclasses/lapr/src/LAPRSshService.php` (namespace `TeampassClasses\Lapr`, loaded via `require_once` — **not** PSR-4/Composer, decision D8) |
| Shared helpers | `app/sources/lapr.functions.php` |
| AJAX handlers | `app/sources/lapr_endpoints.queries.php`, `lapr_accounts.queries.php`, `lapr_policies.queries.php` |
| Pages | `app/pages/lapr_endpoints.{php,js.php}`, `lapr_accounts.{php,js.php}`, `lapr_policies.{php,js.php}`, `admin_lapr.{php,js.php}` |
| Background traits | `app/scripts/traits/LAPRSshTestTrait.php`, `LAPRDiscoverTrait.php`, `LAPRRotationTrait.php` (global namespace, `use`d by `TaskWorker`) |
| Worker dispatch | `app/scripts/background_tasks___worker.php` — process types `lapr_ssh_test`, `lapr_discover`, `lapr_rotation` |
| Scheduler | `app/scripts/background_tasks___handler.php` → `handleScheduledLAPRRotations()` + `cleanLAPRMaintenance()` |
| Admin permission handlers | `app/sources/admin.queries.php` → `lapr_list_users`, `set_user_lapr_permission` |
| DB migration | `public/install/upgrade_run_3.2.2.php` (+ `upgrade_scripts_manager.php` entry) and `public/install/install-steps/run.step5.php` (+ `install.js` check68–72) |
| Routing / access | `public/index.php` (sidebar + JS include), `app/config/include.php` (`$mngPages['admin_lapr']`), `PerformChecks::$pagesRights` (BOTH copies) |
| Session flag | `app/sources/identify.php` → `user-can_manage_lapr` |
| Item delete guard | `app/sources/items.queries.php` `case 'delete_item'` (D6) |
| Language | `app/includes/language/{english,french}.php` (`lapr_*` keys) |
| Tests | `tests/Unit/LaprFunctionsTest.php` (DB-free helpers + fingerprint) |

## Database (no FK constraints — TeamPass convention, correction C6)

- `teampass_lapr_endpoints` — enrolled servers (`ssh_credential_source` = item id holding the SSH secret; `os_info`/`capabilities` JSON; `ssh_hostkey_fingerprint` TOFU; `status`).
- `teampass_lapr_accounts` — managed items (`item_id` UNIQUE, `username_cache`, `policy_id`, `next_rotation_at`, `retry_count`/`retry_at`, `status`).
- `teampass_lapr_policies` — frequency + charset rules; 3 seeded presets (`is_preset=1`, `created_by=TP_USER_ID`).
- `teampass_lapr_audit_log` — `action_type` VARCHAR, indexed `account_id`; **never contains a secret**.
- `teampass_lapr_rate_limit` — per-IP + per-hostname sliding window.
- `teampass_users.can_manage_lapr` TINYINT.
- 15 `lapr_*` settings (`teampass_misc` type `admin`).

## Permission model

`laprCheckPermission($session, $SETTINGS)` = `lapr_enabled == 1` **AND** (admin **OR** `user-can_manage_lapr == 1`).
Every LAPR handler calls it after the standard `PerformChecks` preamble (C8). `admin_lapr` is admin-only
(also in `$mngPages`, so `admin.js.php` wires its settings toggles via the generic `save_option_change`).
Folder scope: **write** = `user-accessible_folders` − `user-read_only_folders` (add account / rotate / reset);
**read** = `user-accessible_folders` (history). Admin bypasses both (C9).

## Encryption integration (corrections C2/C3/C4)

- **Read a credential/item password as the server:** `laprGetTpUserPrivateKey($settings)` (SharekeysRepairTrait
  pattern — `cryption()` on `users.pw` + `decryptPrivateKey()`, private key from `user_private_keys` where
  `is_current=1`) → `laprReadItemPasswordAsTpUser($itemId, $priv, $pub)` (uses
  `decryptUserObjectKeyWithMigration()` on `sharekeys_items`, then `doDataDecryption(pw, objectKey, pw_iv)` +
  `base64_decode`). Only works for **non-personal** items (TP_USER holds a sharekey for those).
- **Write a rotated password** (`LAPRRotationTrait::laprUpdateItemPassword`): `doDataEncryption($new)` →
  update `pw` + **`pw_iv` = `meta`** + `pw_len` + `updated_at`, reset HIBP fields; then
  `storeUsersShareKey('sharekeys_items', 0, $itemId, $objectKey, true, true, [], -1, TP_USER_ID)` — the
  all-eligible-users fan-out with `deleteAll` cleanup, `apiUserId=TP_USER_ID` so **no HTTP session** is needed
  in the CLI worker. Then password-history `logItems(... old_value)` (master-key encrypted, SEC-6),
  `emitItemEvent('updated', ...)` (WebSocket rule).

## Background flow

- **Test / Discover** = standalone `background_tasks` rows the modal polls (`test_status`, `discover_status`).
  The trait writes a secret-free JSON result into `background_tasks.output`; the worker's `completeTask()` still
  runs (the LAPR handlers set status/output themselves; `emitItemEncryptionEvent` early-returns for LAPR types).
  New worker helpers `updateTaskStatus()/updateTaskResult()/updateTaskStep()` (correction C7).
- **Rotation** = `lapr_rotation` rows with `background_tasks.item_id = accountId` (indexed dedup, correction
  C12 — replaces the fragile `arguments LIKE`). Enqueued by `start_rotation` (manual) or the scheduler.
- **Scheduler** (`handleScheduledLAPRRotations`, paced by `lapr_scheduler_interval_minutes`): selects due active
  accounts on active endpoints (`next_rotation_at <= now`, `retry_at` honoured), dedups, enqueues as
  `trigger=scheduler` / `author=TP_USER_ID`. Runs under the handler's global process lock.

## Rotation semantics (Point 4 — the critical phase)

Four steps in `laprRotationExecute()`: **load → generate → SSH push → item update**.
Confirmed decisions:
- **D1/A SSH-first** with a **pre-flight item read** (proves the TP_USER chain + captures `old_value` before
  touching the server). Post-SSH item-update failure ⇒ account `error` + **MANUAL RESYNC REQUIRED** audit.
- **D2** `printf '%s:%s\n' user pw | chpasswd`; **D5** `sudo -n chpasswd` when `!is_root && has_sudo`.
- **D4** host-key mismatch **BLOCKS** rotation (`hash_equals` on the stored fingerprint) unless
  `ssh_hostkey_verified=0` — diverges from the spec's "log only" (risk R2: reusable privileged credential).
- **R1** mandatory `laprValidateUsername()` (`^[a-z_][a-z0-9_-]{0,31}\$?$`) before any command is built.
- **R9** mandatory `laprIsPasswordSafeForLinux()` (no `:`/whitespace/backslash/quotes, printable ASCII);
  `laprGeneratePassword()` regenerates until safe.
- **R5** SSH-credential sync: when `username_cache == ssh_username`, the credential item is rotated too.
- Scheduler failures: `retry_count`/`retry_at` until `lapr_max_retries`, then **suspend** (`status=paused`) with
  `rotation_suspended` audit; `account_reset` ("Reset & resume") clears the retry state.

## Rules for new LAPR code

- **Never** put SSH work in a `*.queries.php` request thread — always a background trait.
- **Never** log a secret. `laprAuditLog()` details are whitelisted; `action_details` never holds a password.
- Reading a credential/item as the server ⇒ `laprReadItemPasswordAsTpUser()` (TP_USER chain, migration-aware).
- Writing an item password ⇒ mirror `laprUpdateItemPassword()` (pw_iv, sharekey fan-out via `TP_USER_ID`,
  history `old_value`, WebSocket) — do not hand-roll a reduced version.
- New setting ⇒ seed in `upgrade_run_3.2.2.php` **and** `run.step5.php`; new table ⇒ add a `run.step5` method
  **and** an `install.js` check entry, plus DDL in the upgrade script.
- `PerformChecks` edits go in **both** the `includes/libraries` and `vendor` copies.
