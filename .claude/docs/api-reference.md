# API Reference

## Entry Points

- **Public entry:** `public/api/index.php` (proxies to `app/api/index.php`)
- **App entry:** `app/api/index.php` — router, CORS, network ACL, JWT validation
- **Bootstrap:** `app/api/inc/bootstrap.php` — DB, autoloader, CRUD rights check
- **JWT utils:** `app/api/inc/jwt_utils.php` — `is_jwt_valid()`, `getApiJwtSigningKey()`
- **Controllers:** `app/api/Controller/Api/`
- **Models:** `app/api/Model/`

---

## Versioning

Routes are available both with and without the `v1` prefix — `BaseController::getUriSegments()` strips only `/v1/`:

```
GET /api/item/get          # legacy, treated as v1
GET /api/v1/item/get       # explicit v1
GET /api/v2/item/get       # unknown version → 404 "Unknown route"
```

All responses include `X-Api-Version: 1`.

**OpenAPI contract:** `GET /api/v1/openapi.json` serves the machine-readable OpenAPI 3.1 spec (static file `app/api/openapi.json`, no JWT required, gated by the global `api` setting). The sentinel test `tests/Unit/Api/OpenApiContractTest.php` keeps the spec and the controllers in sync (every documented path ↔ a `*Action` method).

---

## Error envelope (RFC 9457)

All error responses use `Content-Type: application/problem+json`:

```json
{ "type": "about:blank", "title": "Bad Request", "status": 400, "detail": "<message>", "error": "<message>" }
```

The legacy `error` member duplicates `detail` and is kept for backward compatibility (browser extension, scripts) for one major version. Status lines use standard reason phrases only. `405` responses carry an `Allow:` header listing the supported methods. Empty collections return `200` + `[]` (never a 204 with a body). Exposed headers for browser clients: `Access-Control-Expose-Headers: X-Api-Version, X-Total-Count, Location, Allow`.

---

## Transport & throttling

**HTTPS enforcement** — setting `api_require_https` (Settings → API; **`1` on new installs, `0` after an upgrade** so existing HTTP integrations keep working — a health-check warning is raised instead). When enabled, any API request over plain HTTP gets `403` + problem body. `X-Forwarded-Proto: https` is honoured for TLS-terminating reverse proxies.

**Trusted TLS certificate required for browser clients** — the browser extension (and any `fetch()`-based browser client) only completes a request over HTTPS when the server presents a **fully trusted certificate whose CN/SAN matches the FQDN** the client targets. With a self-signed, expired, or FQDN-mismatched certificate the browser **silently drops the connection and the `Authorization` header** in the background: the request never reaches PHP, so there is **no server-side log**, and the client sees `Failed to fetch` / missing-auth errors. This is a **server/environment** issue, not an application bug — install a CA-trusted certificate matching the configured `cpassman_url` / `browser_extension_fqdn`. (Field-confirmed: a test environment with an untrusted/mismatched cert failed while the same build worked on a production server with a valid certificate.)

**Rate limiting** — setting `api_rate_limit_per_minute` (Settings → API; **`120` on new installs, `0` = disabled after an upgrade**). Sliding-window counter applied **per user and per IP** on every authenticated endpoint, after JWT validation (`teampass_api_rate_limit` table). Above the limit: `429` + `Retry-After: <seconds>` + problem body. `/authorize*` stays covered by the anti-bruteforce lock instead.

---

## Authentication

### `POST /api/authorize`

Generates a JWT. Does **not** require an existing JWT.

**Request body (JSON or form-data):**
```json
{ "login": "user", "password": "s3cr3t", "apikey": "<user api key>" }
```
Credentials must be in the body — query string is rejected (400).

**Response 200:**
```json
{
  "token": "<jwt>",
  "teampass_version": "3.2.1.0",
  "teampass_version_major": "3.2.1",
  "teampass_version_minor": "0"
}
```

**Server version** — `teampass_version` is `TP_VERSION . '.' . TP_VERSION_MINOR`; the two parts are also returned separately so a client needs no parsing. Added by `AuthModel::issueJwtForUser()`, so **both** `/authorize` and `/authorizeToken` return it. It is deliberately **not** a JWT claim: the token stays a pure credential, and an instance upgraded during a token's lifetime reports its new version at the next authentication instead of at token expiry. The same three keys are also served by `GET|POST /api/misc/refreshExtensionSettings` for clients that need to refresh the value without re-authenticating.

**Error responses:**

| Status | Condition |
|---|---|
| 400 | Missing parameters or credentials in query string |
| 401 | Invalid credentials (uniform message — no enumeration) |
| 401 | Account temporarily locked (bruteforce) |
| 503 | API disabled in settings |
| 500 | Internal error |

**Anti-bruteforce:** Failed attempts are recorded in `teampass_auth_failures` using the same thresholds as the web interface (`nb_bad_authentication`, `nb_bad_authentication_by_ip`, `bruteforce_lock_duration`). Events are logged in `teampass_log_system` with `tp_src=api`.

### `POST /api/authorizeToken`

Generates a JWT for an **OAuth2 (SSO) user** using a **Personal Access Token (PAT)** instead of a password + API key. Does **not** require an existing JWT.

OAuth2 users have no usable cleartext password (their stored `pw` is a hash of the non-secret Azure object id), so they cannot use `/api/authorize`. The PAT, generated from the web profile, carries a server-stored copy of the user's private key re-wrapped under a key derived from the token — letting the API unwrap it without the password. See `architecture-encryption.md` → "Personal Access Tokens".

**Gate:** requires the admin toggle **`oauth2_api_enabled`** (Settings → OAuth2, default off) **in addition to** the global `api` setting. Disabled → uniform 401 (`OAuth2 API access is disabled`).

**Request body (JSON or form-data):**
```json
{ "login": "user", "token": "<64-hex-char extension token>" }
```
Credentials must be in the body — query string is rejected (400). The token must match `^[a-f0-9]{64}$`.

**Response 200:** identical shape to `/api/authorize` — `{ "token": "<jwt>" }` plus the `teampass_version*` keys. The returned JWT is used exactly the same way (`Authorization: Bearer <jwt>`).

**Error responses:**

| Status | Condition |
|---|---|
| 400 | Missing parameters or credentials in query string |
| 401 | Invalid/expired token, unknown login, non-OAuth2 user, API access disabled (uniform message — no enumeration) |
| 401 | Account temporarily locked (bruteforce) |
| 503 | Global API disabled in settings |
| 500 | Internal error |

**Restrictions:** only `auth_type = 'oauth2'` users are accepted; local/LDAP users are rejected (they keep using `/api/authorize`). Same bruteforce protection and `tp_src=api` logging as the password path. On success, `teampass_api_tokens.last_used_at` is updated.

### `POST /api/auth/logout`

Revokes the **current API session** (requires `Authorization: Bearer <jwt>`). The `teampass_api_sessions` row matching the token's `jti` is flagged `revoked_at` — the JWT is then rejected with `401` on **every** endpoint until it expires. Legacy tokens without a session row wipe the user's single-row `teampass_api` session instead.

**Response 200:** `{ "error": false, "message": "Session revoked" }`. Only POST is accepted (405 + `Allow: POST` otherwise).

### API sessions (one per token)

Every `/authorize*` call inserts a row in `teampass_api_sessions` keyed by the JWT's `jti`: per-token wrapped private key (`encrypted_private_key` + `session_aes_key`), `key_tempo`, `user_agent`, `created_at`/`expires_at`/`last_used_at`/`revoked_at`. This enables **concurrent API clients on the same account** (each token decrypts with its own session row), per-token revocation, and the **"Active API sessions"** list in the user profile (list/revoke — handlers `list_api_sessions`/`revoke_api_session` in `users.queries.php`; key material is never returned). Per-request check in `api/index.php`: a revoked or expired session row → uniform `401`. Tokens issued before the table existed have no row and fall back to the legacy single-row session until expiry (max 24h). Expired rows are purged opportunistically at each authentication (24h grace).

### JWT Structure

- Algorithm: HS256
- Signing key: `api_jwt_secret` in `teampass_misc` (256-bit hex, lazy-generated on first use — **distinct from DB password**)
- Expiry: `api_token_duration` **minutes** (configurable in Settings → API, default 60). The server computes `exp = now + duration * 60`. The claim is carried in the JWT payload as the raw number so clients can compute `token_expiry = issue_time + api_token_duration * 60 * 1000` ms.
- Standard claims: `iss` (instance `cpassman_url`), `aud` (`teampass-api`), `iat`, `nbf`, `jti` (random 128-bit). `iss`/`aud` are validated only when present so pre-3.2.1 tokens keep working until expiry. Decode leeway: 60s.
- Key claims: `id`, `username`, `exp`, `key_tempo`, `is_admin`, `is_manager`, `allowed_to_create`, `allowed_to_read`, `allowed_to_update`, `allowed_to_delete`, `folders_list`

**Per-request revalidation:** `api/index.php` re-reads `disabled`/`deleted_at`/`api.enabled` and overrides `is_admin`, `is_manager` and the 4 CRUD claims from the DB on every request — disabling a user or revoking API rights takes effect immediately, not at token expiry.

**Private key architecture:** User private key is encrypted with a per-session AES-256-GCM key (`session_aes_key`) stored server-side in `teampass_api`. The JWT carries only `key_tempo` (a reference). A stolen JWT alone cannot decrypt the private key.

**No refresh token.** Re-authenticate via `/authorize` when expired.

---

## Item Endpoints

All require `Authorization: Bearer <jwt>`.

### `GET /api/item/get`

Get item(s) by ID or label.

**Params:** `id` (int) OR `label` (string) OR `description` (string), optional `limit` (default 50, max 500) and `offset` (default 0) for searches. Missing all three → `400`.

**Pagination:** label/description searches return `X-Total-Count` (total matches in accessible folders, before per-item sharekey filtering).

**Response:** array of item objects `{ id, revision, revision_changed_at, label, description, login, email, url, password, path, folder_id, folder_label, has_otp, favicon_url, tags, fields }`.

**`revision`** — monotonic item revision, allocated from the `teampass_items_revisions` journal on every content change (item row, custom fields, tags, attachments, OTP, move, delete, restore). `0` = never changed since the column was introduced. Also returned by `item/inFolders`, `item/findByUrl`, `item/create` and `item/update`. See `architecture-item-revisions.md`.

**`revision_changed_at`** — Unix UTC timestamp in seconds paired with `revision`. It changes only
when a functional content revision is allocated, never on a read or ciphertext-only rewrite.
`null` means the exact date is not known, notably for revision 0 or when an upgrade could not find
the current revision in the prunable journal. Returned everywhere `revision` is returned.

**Custom fields:** `fields` is an array of `{ id, title, type, masked, value }` for the item's folder-associated categories. Encrypted values are decrypted via `decryptUserObjectKeyWithMigration()` on `sharekeys_fields` (+ `base64_decode`); empty when no sharekey is available yet. Only present when `item_extra_fields` is enabled. Also returned by `item/inFolders`.

**Field role visibility:** a field restricted via `categories.role_visibility` (Custom Fields → "Restrict Visibility to") is **omitted entirely** from `fields` for a user holding none of those roles — its value is never decrypted nor returned. `role_visibility = 'all'` (or a role the user holds) ⇒ returned. The check uses the requesting user's `fonction_id` and mirrors the web item card (`core.php` *LOAD CATEGORIES*); there is **no admin bypass**. Enforced in `ItemModel::getItemCustomFields()`.

**Permissions:** `allowed_to_read`. Uses folder access constraint — IDOR protection via sharekey (item skipped if no sharekey found for user).

**LIKE search:** `label` and `description` params trigger a `LIKE %value%` search. The `%` and `_` characters in the input are escaped to prevent LIKE injection.

---

### `GET /api/item/inFolders`

Get items in one or more folders.

**Params:** `folders` (comma-separated or JSON array of folder IDs), optional `limit` (default unlimited, max 500) and `offset` (default 0; forces `limit=50` if no limit given). Returns `X-Total-Count`; empty result → `200` + `[]`.

**Permissions:** `allowed_to_read`.

---

### `GET /api/item/findByUrl`

Find items by URL match.

**Params:** `url` (string). The `%` and `_` characters are escaped before the LIKE query.

**Response:** array of `{ id, revision, revision_changed_at, label, login, url, folder_id, has_otp, favicon_url }`. Empty result → `200` + `[]`.

**Permissions:** `allowed_to_read`.

---

### `GET /api/item/getOtp`

Get current TOTP code for an item.

**Params:** `id` (int, required).

**Response 200:**
```json
{ "otp_code": "123456", "expires_in": 25, "item_id": 123 }
```

**Error codes:** 400 (missing id), 403 (access denied / OTP not enabled), 404 (item not found / OTP not configured), 500 (decrypt failed).

**Permissions:** `allowed_to_read` + folder access + item-level restriction check.

---

### `GET /api/item/changes`

Delta feed for offline clients (mobile vault). Answers "what must I apply since revision N".

**Params:** `since` (int, **required**, exclusive; `0` on a first sync), `limit` (default 200, max 1000).

**Response:** `{ cursor, has_more, full_sync_required, changed[], removed[] }`. `changed` carries full item payloads (same shape and same code path as `item/get`); `removed` is `{ id, revision, revision_changed_at, reason }` with `reason` in `deleted` | `purged` | `out_of_scope`. The revision/date pair comes from the winning journal row after deduplication.

**Cursor-based, not offset-based** — a change feed has no stable total, so no `X-Total-Count`. Store `cursor`, resend it as `since`, repeat while `has_more`.

**`full_sync_required: true`** when `since === 0` or `since < MIN(revision) - 1`. Covers a first sync (items untouched since the column was introduced are at revision `0` and have **no journal entry**, so a delta would miss them), a client older than the sync window, and an empty journal. The client reads the vault via `item/inFolders` and adopts the returned `cursor`.

**The journal is the scan target, not `items`** — it is the only place where a hard-deleted item, or one that left the caller's folders, still leaves a trace. `items_revisions.previous_folder_id` (set on move) is what makes `out_of_scope` detectable without leaking any item id the caller never had access to.

**Rule: the cursor stops before an undeliverable change.** An item whose sharekeys are still being distributed by the background task is visible but not readable; advancing past it would hide it from that client permanently. `has_more` stays true and it is offered again.

**Not covered:** losing access to a whole folder produces **no** journal entry (nothing changed on the items). Clients must also reconcile against `folder/writableFolders` and drop cached items whose folder disappeared.

**Permissions:** `allowed_to_read` (`'changes'` is in the `checkUSerCRUDRights()` read whitelist, `api/inc/bootstrap.php`).

---

### `GET /api/item/allTags`

Get all distinct item tags accessible to the user.

**Response:** array of tag strings.

**Permissions:** `allowed_to_read`.

---

### `POST /api/item/create`

Create a new item.

**Body:** `label`, `password`, `folder_id`, optional `description`, `login`, `email`, `url`, `tags`, `totp`, `fields`.

**Custom fields:** `fields` = array of `{ id, value }` (field id + value). Encrypt-before-INSERT for encrypted categories; creator sharekey created synchronously, other users via the `new_item` background task. Only fields tied to the folder are stored; empty values ignored. Requires `item_extra_fields`.

**Response 201:** `{ error: false, message, newId, revision, revision_changed_at }` + `Location: /api/v1/item/get?id=<newId>` (path-absolute reference). Validation failures → `422`; missing fields → `400`; folder not allowed / read-only → `403`.

**Permissions:** `allowed_to_create`. Blocked with 403 if folder is read-only for user.

---

### `PUT /api/item/update`

Update an existing item. **Only PUT is accepted** — POST returns 405.

**Body:** `id` (required), at least one of: `label`, `password`, `description`, `login`, `email`, `url`, `tags`, `anyone_can_modify`, `icon`, `folder_id`, `totp`, `fields`.

**Optimistic concurrency — `revision` (optional).** The revision the client's edit was based on. When it differs from `items.revision`, the update is rejected with `409` and **nothing is written**; omitting it keeps last-writer-wins, so existing clients are unaffected. It is a **precondition, not an updatable field**: it is absent from the `$updateableFields` list in `ItemController::updateAction()`, so `{id, revision}` alone still answers `400 'At least one supported field to update must be provided.'`, and it never triggers the personal→shared move conflict guard.

**Password history.** When a non-empty `password` is supplied, the server decrypts the current
value before any mutation and compares it with `hash_equals()`. An unchanged password is a password
no-op: by itself it causes no ciphertext rewrite, sharekey fan-out, password-history row or revision. A real change stores the
previous value in `log_items.old_value`, encrypted with the application master key and tagged
`at_pw`, exactly like the web UI. Failure to recover or prepare the old value aborts before mutation.

**Rule: a password update needs a usable sharekey and fails closed without one** — this is a
behaviour change, clients must handle it. When the caller holds no `sharekeys_items` row for the
item, or the row cannot be decrypted, the update is rejected with **`422`** and nothing is written;
previously it silently succeeded and left a hole in the password history. The common transient
cause is the FUNC-1 background fan-out: after another user creates or updates a public item, only
that editor holds a sharekey until the background task distributes the rest. **Retry** — the
message says so — and only treat it as permanent if it survives the encryption-keys repair task.
Every other field stays updatable in the meantime; only `password` needs the current value.

**Custom fields:** `fields` = array of `{ id, value }`. Created if absent, updated only when the value changed (current value decrypted for comparison); encrypted fields re-encrypted and sharekeys refreshed synchronously for all eligible users (consistent with the password path). Empty values ignored. Requires `item_extra_fields`.

**Move (`folder_id` change).** A `folder_id` equal to the current one is a no-op, not a move. Any real move now emits the same side effects as the web UI — `at_moved` audit log, item cache refresh, source/destination folder counters, and an `item_moved` WebSocket event to both folders — for **every** transition type, not only personal→shared.

**Personal → shared** is special: the item's object keys must be recovered and redistributed to every eligible user, which `movePersonalItemToSharedFolderSynchronously()` (`sources/main.functions.php`) does in its own transaction. Consequences for clients:

| Condition | Status |
|---|---|
| Combined with a field that would also be written (`label`, `password`, `description`, `login`, `email`, `url`, `tags`, `anyone_can_modify`, `icon`, `fields`, `totp*`) | `422` — submit the move as a dedicated request |
| A source encryption key is missing or unusable | `422` — the item stays personal, nothing is destroyed |
| The item was moved or re-encrypted concurrently | `409` — retry |

Unknown extra keys in the payload are **not** a conflict: the guard keys off the fields that would actually be written, exactly like the rest of `updateItem()`. Other transitions (shared→shared, shared→personal, personal→personal) keep their previous behaviour and are unaffected by the 422/409 rules.

**LAPR-owned items** (Linux Account Password Rotation, see `architecture-lapr.md`). Two independent guards, both returning `409` and both **inactive when the LAPR module is disabled**:

| Condition | Status |
|---|---|
| The item is a LAPR **managed target** and `login` or `password` would actually change | `409` — rotate through LAPR or remove the managed account first |
| The item is a managed target **or** an SSH credential and `folder_id` points to a **personal** folder | `409` — LAPR reads the item as the server, which only works in a shared folder |

The password guard compares against the **decrypted** current value, so resending the unchanged password (typical read-modify-write client) is not a conflict; a password that cannot be decrypted fails closed. Every other field (`label`, `description`, `url`, `tags`, custom fields, …) stays editable on a managed item, and a move between shared folders is unaffected.

**Error bodies:** validation failures (`InvalidArgumentException` / `UnexpectedValueException`) return their message with `422`. Every other internal failure returns a generic `500` — the exception message is written to the server log only, never to the client.

**Permissions:** `allowed_to_update`. Source folder must not be read-only. If `folder_id` changes (move), **target folder** must also not be read-only for the user.

---

### `DELETE /api/item/delete`

Soft-delete an item.

**Params:** `id` (int).

**LAPR:** `409` while the item is still referenced by a non-deleted managed account or enrolled endpoint — remove the managed account or reconfigure the endpoint first. The relationship has no FK, so deleting the item would orphan it and break rotation or endpoint authentication. Inactive when the LAPR module is disabled.

**Permissions:** `allowed_to_delete`. Blocked with 403 if folder is read-only.

---

## Folder Endpoints

### `GET /api/folder/listFolders`

List all folders accessible to the authenticated user.

**Params:** optional `limit`/`offset` — applied to the **root-level** entries of the hierarchical tree; `X-Total-Count` is the number of root entries.

**Response:** array of folder objects with hierarchy info (`{ id, title, isVisible, complexity, childrens[] }`). No accessible folder → `200` + `[]`.

**`complexity`** — minimum password strength required in the folder (`0` | `20` | `38` | `48` | `60`, the `TP_PW_STRENGTH_*` scale). Read from `misc` (`type='complex'`, `intitule=<folder id>`) via a LEFT JOIN; `0` when the folder has no row (personal roots).

**Permissions:** `allowed_to_read`.

---

### `GET /api/folder/writableFolders`

List all folders accessible to the user with label, level, and read-only flag, **as a flat list in tree order**.

**Response:** array of `{ id, label, level, parent_id, first_position, position, complexity, is_readonly, access_type, can_create, can_edit, can_delete, is_personal, is_personal_root, can_create_subfolder, can_rename_folder, can_move_folder, can_delete_folder }`.

- `complexity` — minimum password strength required in the folder (`0` | `20` | `38` | `48` | `60`, the `TP_PW_STRENGTH_*` scale). LEFT JOIN on `misc` (`type='complex'`, `intitule=<folder id>`); `0` when no row exists (personal roots). Same field as in `listFolders`.
- `access_type` — effective level resolved least-permissive-wins across every role: `W` | `ND` | `NE` | `NDNE` | `R`
- `is_readonly: 1` ⟺ `access_type === 'R'` (no create, no edit, no delete)
- `can_create` / `can_edit` / `can_delete` — **item rights** on the folder's contents. **`is_readonly: 0` does not mean full write**: `ND` blocks delete, `NE` blocks edit, `NDNE` blocks both. Clients must read the granular flags or they will hit surprise `403`s on update/delete.
- `position` — the folder's `nested_tree.nleft`; rows are sorted `ORDER BY nleft ASC` (MPTT pre-order), so `parent_id` + `level` + `position` rebuild the exact hierarchy **including sibling order**. Before 3.2.2 the ordering was `nlevel ASC, title ASC` (alphabetical, sibling order lost).

**Folder-management capabilities** (added 3.2.2) — a second family, about the **folder itself**, not its items. `can_delete: 0` + `can_delete_folder: 1` on an `ND` folder is the canonical illustration of why the two must not be conflated.

- `is_personal` — `nested_tree.personal_folder`. Only the caller's own personal tree is ever listed (`AuthModel::buildUserFoldersList()` adds personal folders by `title = <user id>`; admins get `personal_folder = 0` only), so `1` means "mine" in practice.
- `is_personal_root` — `is_personal && parent_id === 0`. Never renamable / movable / deletable.
- `can_create_subfolder` / `can_rename_folder` / `can_move_folder` / `can_delete_folder` — computed by `FolderAccessModel::getFolderManagementCapabilities()` from: effective access + `!is_readonly` + the global folder-management gate (`hasFolderManagementPrivilege()`) + `!is_personal_root` (except create) + the matching API CRUD claim (`allowed_to_create` / `allowed_to_update` / `allowed_to_delete`).

**Rule: these four flags are UI hints, never an authorization decision.** `FolderModel::createFolder()/updateFolder()/deleteFolder()` re-run every check on mutation. In particular `can_move_folder` describes the **source only** — `updateFolder()` separately validates the destination (access, read-only, self/descendant cycle, personal ↔ shared boundary, root permission), so a `1` can still end in `403`/`422`.

**Global folder-management gate** — `FolderAccessModel::hasFolderManagementPrivilege($userData, $isPersonal, $enableUserCanCreateFolders)`: true when the folder is personal **or** the user is admin / manager / `user_can_manage_all_users` / `user_can_create_root_folder`, **or** the `enable_user_can_create_folders` setting is on. Reused by the three mutation adapters *and* by the capability evaluator, so the hint cannot drift from the route. `FolderManager::canCreateFolder()` (`sources/folders.class.php`) evaluates the same rule and stays the authoritative backstop on create — the adapter's copy is a documented fail-fast mirror. **The `$isPersonal` argument short-circuits the whole gate**, so callers must first establish the folder really belongs to the caller (`canUseFolder()` → `isFolderInsideAllowedPersonalRoot()`).

**Note:** the name is historical — the endpoint returns all accessible folders, not only writable ones. This is the endpoint to point API clients at when they need "the whole folder tree in one call" — `listFolders` returns a nested tree but carries no access rights.

**Known cost:** the access level is resolved per folder by `FolderAccessModel::getFolderAccessLevelForUser()` (up to 3 queries per folder, one resolution feeding all four item flags). The management capabilities add **no** query — they are pure computation over that resolution plus the JWT claims, and `getAllSettings()` is called once outside the loop. A batch resolver is a pending optimization.

**Known limitation:** `user_can_create_root_folder` and `user_can_manage_all_users` are **not** refreshed per request by `api/index.php` (unlike `is_admin`, `is_manager` and the four CRUD claims) — they stay frozen in the JWT until it expires. Both the hints and the mutation routes read the same stale claim, so they never disagree; revoking those two rights simply takes effect at the next authentication.

**Permissions:** `allowed_to_read`.

---

### `POST /api/folder/create`

Create a new folder (shared or personal, root or subfolder). Delegates to the shared `FolderManager` create engine — the same one used by the web UI — so the new folder is fully wired: nested tree rebuilt, `roles_values` populated, and same-role users' `cache_tree` refreshed. A WebSocket `folder_created` event is emitted.

**Body:** `title`, `parent_id`, `complexity` (required → `400` listing missing fields), `duration`, `create_auth_without`, `edit_auth_without`, `icon`, `icon_selected`, `access_rights`, `private`.

- **`personal_folder` is never accepted from the client** — it is derived server-side from the resolved parent. A `parent_id` inside the caller's own personal tree yields a personal folder.
- **`private` (optional boolean):** convenience for the extension to create a personal folder without knowing the personal-root id. When `true`: personal folders must be enabled for the user (`403` otherwise); `parent_id` is optional (defaults to the caller's personal root) and, when given, must be inside the caller's own personal tree (`422` otherwise); `complexity` is optional (personal folders skip the complexity ceiling).
- Creating **inside a read-only parent** or **another user's personal tree** → `403`.

**Response 201:** `{ error: false, newId }` — no `Location` header (no folder get-by-id endpoint yet). Invalid `complexity`/`access_rights`, numeric title, duplicate title, or complexity below the parent ceiling → `422`.

**Permissions:** `allowed_to_create` + admin/manager checks. Returns 403 if not allowed, or if the user has no accessible folders.

---

### `PUT /api/folder/update`

Update an existing folder. **Only PUT is accepted** — other methods return 405 + `Allow: PUT`. Delegates the write to `FolderManager::updateFolder()`; a WebSocket `folder_updated` event is emitted.

**Body:** `id` (required); every other field optional — **partial update**, unspecified fields keep their current value: `title`, `parent_id` (move), `complexity`, `duration`, `create_auth_without`, `edit_auth_without`, `icon`, `icon_selected`. At least one updatable field must be present → else `400 'Nothing to update'`.

- **`access_rights` is not updatable here** — editing the rights of an existing folder is a roles-management operation (`roles_values`), not a folder-form field (mirrors the web `update_folder`, which also ignores it on update).
- **`personal_folder` is derived**, never client-controlled.
- **Personal ROOT folders cannot be renamed or moved** → `403`.
- **Move guards:** a folder cannot be moved into itself or into one of its descendants (`422`); the target parent must be accessible, not read-only, and inside the caller's own personal tree (`403`). **Cross-domain moves (personal ↔ shared) are rejected** with `422 'Moving a folder between personal and shared trees is not supported'`.
- Numeric title, duplicate title (when `duplicate_folder = 0`), or complexity below the parent ceiling → `422`.

**Response 200:** `{ error: false, message: "Folder updated", id }`.

**Permissions:** `allowed_to_update`. The folder (and, on a move, the target parent) must not be read-only for the user.

---

### `DELETE /api/folder/delete`

Soft-delete a folder and **all its descendants** into the recycle bin; every contained item is soft-deleted. The `nested_tree` rows are removed but preserved as JSON in `misc` (`type='folder_deleted'`), restorable from **Utilities → Recycled bin** — the byte-compatible format the web delete produces. **Only DELETE is accepted** — other methods return 405 + `Allow: DELETE`. A WebSocket `folder_deleted` event is emitted per removed folder.

**Params:** `id` (single folder id — query string `?id=N` or JSON body). `id = 0` (root) is rejected with `400`.

- **Personal ROOT folders cannot be deleted** → `403`.
- Inaccessible / read-only / another user's personal tree → `403`.

**Response 200:** `{ error: false, message: "Folder deleted", deleted_folders: [57, 58, 61], deleted_items_count: 12 }` — `deleted_folders` lists the folder plus every descendant that was removed.

**Sharekeys note:** soft-deleted items keep their `sharekeys_items` rows so a restore works — no sharekey work is performed.

**Permissions:** `allowed_to_delete`. Blocked with 403 if the folder is read-only.

---

## User Endpoints

### `GET /api/user/list`

List users. **Admin only** (`is_admin = 1` in JWT).

**Params:** `limit` (default 10, max 500).

**Response:** array of `{ id, login, name, lastname, email, admin, gestionnaire, disabled, last_connection_time, is_ready_for_usage, personal_folder, auth_type }`. Sensitive columns (`pw`, `private_key`, `api_key`, `mfa_secret`, etc.) are never returned.

**Permissions:** `is_admin = 1`. Returns 403 for non-admin users.

---

## Misc Endpoints

### `GET|POST /api/misc/refreshExtensionSettings`

Returns browser extension connection settings.

**Response:** `{ extension_fqdn, extension_key, extension_url, teampass_version, teampass_version_major, teampass_version_minor }`.

The key is `extension_url` (value = `cpassman_url`) — the doc previously named it `cpassman_url`, which never matched the code. The `teampass_version*` keys are the same ones returned by `/authorize`, so a long-lived client can refresh the server version without re-authenticating.

**Permissions:** any valid JWT.

---

## HTTP Status Codes

| Code | Meaning in API context |
|---|---|
| 200 | Success (collections return `[]` when empty — 204 is no longer used) |
| 201 | Resource created (`item/create` adds a `Location` header) |
| 400 | Missing or invalid parameters |
| 401 | `"Missing Authorization header"` — no bearer token received (check webserver vhost passes Authorization on GET). `"Invalid or expired token"` — token present but rejected (bad signature, expired, malformed). Match on HTTP 401 status rather than the body string. |
| 403 | Permission denied (folder read-only, admin required, CRUD rights missing) |
| 404 | Resource not found / unknown route |
| 405 | HTTP method not supported for this endpoint (`Allow:` header lists supported methods) |
| 409 | The supplied `revision` no longer matches the item (optimistic concurrency on `item/update`), the resource changed while the request was being processed (concurrent personal→shared item move), or the operation conflicts with a LAPR relationship (managed login/password update, move to a personal folder, delete of a linked item) |
| 422 | Validation failed (password rules, invalid complexity/access_rights, personal→shared move combined with another update or with unrecoverable keys, current password not recoverable on a password update — retryable while the sharekey fan-out is still running) |
| 429 | Rate limit exceeded (`api_rate_limit_per_minute`) — `Retry-After` header gives the wait in seconds |
| 500 | Internal server error (details logged server-side, not returned to client) |
| 503 | API disabled in TeamPass settings |

---

## CORS

Behaviour depends on the **Allowed CORS origins** field in Settings → API (`api_cors_origins` in `teampass_misc`):

| Field value | Behaviour |
|---|---|
| **Empty** (default) | `Access-Control-Allow-Origin: *` — all origins accepted. JWT is the real auth layer. |
| **Comma-separated origins** | Only listed origins get the header. Unlisted browser clients are blocked. |

When a whitelist is active, the server echoes the matching `Origin` back (`Access-Control-Allow-Origin: <origin>`) and adds `Vary: Origin`. Browser tool and curl calls without an `Origin` header receive the server's own host.

Security headers on all responses: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`.

On HTTPS: `Strict-Transport-Security: max-age=31536000; includeSubDomains`.

---

## Security Architecture Notes

1. **JWT secret** is stored in `teampass_misc` (key: `api_jwt_secret`, type: `admin`), lazily generated on first call. Rotation: delete or update the row + wait for existing tokens to expire.
2. **User private key** never leaves the server unencrypted. The JWT carries only `key_tempo` (a reference). The server-side `session_aes_key` in `teampass_api` is required to decrypt the private key on each request.
3. **Sharekey decryption** uses `decryptUserObjectKeyWithMigration()` — transparently upgrades phpseclib v1 (SHA-1) sharekeys to v3 (SHA-256) on access.
4. **Bruteforce** thresholds: `nb_bad_authentication` (default 10), `nb_bad_authentication_by_ip` (default 30), `bruteforce_lock_duration` (default 10 min). Configure in TeamPass admin settings.
5. **Folder rights parity with the web** (see `docs/features/rights.md`): `FolderAccessModel::getFolderAccessLevelForUser()` is the single API resolver. It folds every role type on the folder through `evaluateFolderAccesLevel()` — the same function the web uses in `getRoleBasedAccess()` — so the **least permissive wins** (`R` > `NDNE` > `NE` = `ND` > `W`). A direct per-user grant (`users_groups`) always yields `W` and overrides a role restriction, exactly like `identUser()`. Roles of **both** sources count (manual + AD/LDAP): filtering on `source = "manual"` used to hide folders *and* make a role-granted `R` folder look unrestricted.
   - `isFolderReadOnlyForUser()` ⟺ resolved type is `R`. It gates operations that only need *create* semantics: item create, folder create/update/delete, and the target folder of a move.
   - `canEditInFolder()` / `canDeleteInFolder()` gate `PUT /item/update` and `DELETE /item/delete` — `ND`/`NE`/`NDNE` are writable but restricted, which the read-only boolean alone cannot express.
   - **`AuthModel::buildUserFoldersList()` is a visibility list only**, never a rights list. It mirrors `identifyUserRights()`: an administrator gets every shared folder (`identAdmin()`) and is exempt from the deny list; for everyone else `users_groups_forbidden` (`groupes_interdits`) is subtracted **last** — a denial beats every grant. The cache-rebuild query in `api/index.php` must select `admin`, `groupes_interdits` and `roles_from_ad_groups` so it resolves identically to the `/authorize` path.
6. **Logging**: successful logins logged as `user_connection` with `tp_src=api`. Failed auth logged as `failed_auth` with `tp_src=api`. Visible in Admin > Logs.
7. **Input sanitization**: body and query-string params are trimmed only — no HTML encoding — so passwords containing `<>&"'` are stored correctly. SQL injection is prevented by MeekroDB placeholders throughout.
8. **Personal Access Tokens (OAuth2)**: `teampass_api_tokens` stores only `sha256(token)` + the private key wrapped under `HKDF-SHA256(token, salt)` (AES-256-GCM). The raw token is never persisted, so a DB dump alone cannot decrypt. The token is 256-bit (bypassing the weak 64-bit `hashUserId(oid)` derivation), revocable per device, optionally time-limited (`expires_at`), and gated by `oauth2_api_enabled`. Generation requires the cleartext private key to be present in the web session — the security gate on issuance. Audit: `extension_token_generated` / `extension_token_revoked` (`user_mngt`), failed token auth as `failed_auth` (`tp_src=api`).

---

## Known Gaps (Vague 3 Roadmap)

**Items:** move, copy, history, favorites (toggle), attachments (upload/download/delete), OTV (one-time view link), request_access, edition_lock.

**Folders:** copy. ~~update, delete~~ — done (`PUT /api/v1/folder/update`, `DELETE /api/v1/folder/delete`).

**Users (admin scope):** create, update, delete, disable, folder_rights.

**Auth:** refresh token (`POST /api/v1/auth/refresh`), JWT scopes (`scope=full|extension|mobile|readonly`). ~~logout/revoke~~ — done (`POST /api/v1/auth/logout` + profile sessions list/revoke).

**Discovery:** unified search (`GET /api/v1/search?q=...`). ~~OpenAPI 3.1 spec~~ — done (`/api/v1/openapi.json`).
