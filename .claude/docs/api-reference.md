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
{ "token": "<jwt>" }
```

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

**Response 200:** identical shape to `/api/authorize` — `{ "token": "<jwt>" }`. The returned JWT is used exactly the same way (`Authorization: Bearer <jwt>`).

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

**Response:** array of item objects `{ id, label, description, login, email, url, password, path, folder_id, folder_label, has_otp, favicon_url, tags, fields }`.

**Custom fields:** `fields` is an array of `{ id, title, type, masked, value }` for the item's folder-associated categories. Encrypted values are decrypted via `decryptUserObjectKeyWithMigration()` on `sharekeys_fields` (+ `base64_decode`); empty when no sharekey is available yet. Only present when `item_extra_fields` is enabled. Also returned by `item/inFolders`.

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

**Response:** array of `{ id, label, login, url, folder_id, has_otp, favicon_url }`. Empty result → `200` + `[]`.

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

### `GET /api/item/allTags`

Get all distinct item tags accessible to the user.

**Response:** array of tag strings.

**Permissions:** `allowed_to_read`.

---

### `POST /api/item/create`

Create a new item.

**Body:** `label`, `password`, `folder_id`, optional `description`, `login`, `email`, `url`, `tags`, `totp`, `fields`.

**Custom fields:** `fields` = array of `{ id, value }` (field id + value). Encrypt-before-INSERT for encrypted categories; creator sharekey created synchronously, other users via the `new_item` background task. Only fields tied to the folder are stored; empty values ignored. Requires `item_extra_fields`.

**Response 201:** `{ error: false, message, newId }` + `Location: /api/v1/item/get?id=<newId>` (path-absolute reference). Validation failures → `422`; missing fields → `400`; folder not allowed / read-only → `403`.

**Permissions:** `allowed_to_create`. Blocked with 403 if folder is read-only for user.

---

### `PUT /api/item/update`

Update an existing item. **Only PUT is accepted** — POST returns 405.

**Body:** `id` (required), at least one of: `label`, `password`, `description`, `login`, `email`, `url`, `tags`, `anyone_can_modify`, `icon`, `folder_id`, `totp`, `fields`.

**Custom fields:** `fields` = array of `{ id, value }`. Created if absent, updated only when the value changed (current value decrypted for comparison); encrypted fields re-encrypted and sharekeys refreshed synchronously for all eligible users (consistent with the password path). Empty values ignored. Requires `item_extra_fields`.

**Permissions:** `allowed_to_update`. Source folder must not be read-only. If `folder_id` changes (move), **target folder** must also not be read-only for the user.

---

### `DELETE /api/item/delete`

Soft-delete an item.

**Params:** `id` (int).

**Permissions:** `allowed_to_delete`. Blocked with 403 if folder is read-only.

---

## Folder Endpoints

### `GET /api/folder/listFolders`

List all folders accessible to the authenticated user.

**Params:** optional `limit`/`offset` — applied to the **root-level** entries of the hierarchical tree; `X-Total-Count` is the number of root entries.

**Response:** array of folder objects with hierarchy info (`{ id, title, isVisible, childrens[] }`). No accessible folder → `200` + `[]`.

**Permissions:** `allowed_to_read`.

---

### `GET /api/folder/writableFolders`

List all folders accessible to the user with label, level, and read-only flag.

**Response:** array of `{ id, label, level, parent_id, first_position, is_readonly }`.

- `is_readonly: 1` — user has read access only (R-type role on this folder)
- `is_readonly: 0` — user can write

**Note:** the name is historical — the endpoint returns all accessible folders, not only writable ones. Check `is_readonly` on each entry.

**Permissions:** `allowed_to_read`.

---

### `POST /api/folder/create`

Create a new folder.

**Body:** `title`, `parent_id`, `complexity` (required → `400` listing missing fields), `duration`, `create_auth_without`, `edit_auth_without`, `icon`, `icon_selected`, `access_rights`.

**Response 201:** `{ error: false, newId }` — no `Location` header (no folder get-by-id endpoint yet). Invalid `complexity`/`access_rights` → `422`.

**Permissions:** `allowed_to_create` + admin/manager checks. Returns 403 if not allowed, or if the user has no accessible folders.

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

**Response:** `{ extension_fqdn, extension_key, cpassman_url }`.

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
| 422 | Validation failed (password rules, invalid complexity/access_rights) |
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
5. **Read-only folders**: enforced in create/update/delete item operations and folder create. An item move to a read-only target folder is also blocked.
6. **Logging**: successful logins logged as `user_connection` with `tp_src=api`. Failed auth logged as `failed_auth` with `tp_src=api`. Visible in Admin > Logs.
7. **Input sanitization**: body and query-string params are trimmed only — no HTML encoding — so passwords containing `<>&"'` are stored correctly. SQL injection is prevented by MeekroDB placeholders throughout.
8. **Personal Access Tokens (OAuth2)**: `teampass_api_tokens` stores only `sha256(token)` + the private key wrapped under `HKDF-SHA256(token, salt)` (AES-256-GCM). The raw token is never persisted, so a DB dump alone cannot decrypt. The token is 256-bit (bypassing the weak 64-bit `hashUserId(oid)` derivation), revocable per device, optionally time-limited (`expires_at`), and gated by `oauth2_api_enabled`. Generation requires the cleartext private key to be present in the web session — the security gate on issuance. Audit: `extension_token_generated` / `extension_token_revoked` (`user_mngt`), failed token auth as `failed_auth` (`tp_src=api`).

---

## Known Gaps (Vague 3 Roadmap)

**Items:** move, copy, history, favorites (toggle), attachments (upload/download/delete), OTV (one-time view link), request_access, edition_lock.

**Folders:** update, delete, copy.

**Users (admin scope):** create, update, delete, disable, folder_rights.

**Auth:** refresh token (`POST /api/v1/auth/refresh`), JWT scopes (`scope=full|extension|mobile|readonly`). ~~logout/revoke~~ — done (`POST /api/v1/auth/logout` + profile sessions list/revoke).

**Discovery:** unified search (`GET /api/v1/search?q=...`). ~~OpenAPI 3.1 spec~~ — done (`/api/v1/openapi.json`).
