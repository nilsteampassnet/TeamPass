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

Routes are available both with and without a version prefix — `BaseController::getUriSegments()` strips the `/vN/` segment transparently:

```
GET /api/item/get          # legacy, treated as v1
GET /api/v1/item/get       # explicit v1
```

All responses include `X-Api-Version: 1`.

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

### JWT Structure

- Algorithm: HS256
- Signing key: `api_jwt_secret` in `teampass_misc` (256-bit hex, lazy-generated on first use — **distinct from DB password**)
- Expiry: `api_token_duration` seconds (configurable in Settings → API)
- Key claims: `id`, `username`, `exp`, `key_tempo`, `is_admin`, `is_manager`, `allowed_to_create`, `allowed_to_read`, `allowed_to_update`, `allowed_to_delete`, `folders_list`

**Private key architecture:** User private key is encrypted with a per-session AES-256-GCM key (`session_aes_key`) stored server-side in `teampass_api`. The JWT carries only `key_tempo` (a reference). A stolen JWT alone cannot decrypt the private key.

**No refresh token.** Re-authenticate via `/authorize` when expired.

---

## Item Endpoints

All require `Authorization: Bearer <jwt>`.

### `GET /api/item/get`

Get item(s) by ID or label.

**Params:** `id` (int) OR `label` (string) OR `description` (string), optional `limit` (default 50, max 500).

**Response:** array of item objects `{ id, label, description, login, email, url, password, path, folder_id, folder_label, has_otp, favicon_url, tags }`.

**Permissions:** `allowed_to_read`. Uses folder access constraint — IDOR protection via sharekey (item skipped if no sharekey found for user).

**LIKE search:** `label` and `description` params trigger a `LIKE %value%` search. The `%` and `_` characters in the input are escaped to prevent LIKE injection.

---

### `GET /api/item/inFolders`

Get items in one or more folders.

**Params:** `folders` (comma-separated or JSON array of folder IDs), optional `limit` (default 50, max 500).

**Permissions:** `allowed_to_read`.

---

### `GET /api/item/findByUrl`

Find items by URL match.

**Params:** `url` (string). The `%` and `_` characters are escaped before the LIKE query.

**Response:** array of `{ id, label, login, url, folder_id, has_otp, favicon_url }`.

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

**Body:** `label`, `password`, `folder_id`, optional `description`, `login`, `email`, `url`, `tags`, `totp`.

**Response 200:** item object with `id`.

**Permissions:** `allowed_to_create`. Blocked with 403 if folder is read-only for user.

---

### `PUT /api/item/update`

Update an existing item. **Only PUT is accepted** — POST returns 405.

**Body:** `id` (required), at least one of: `label`, `password`, `description`, `login`, `email`, `url`, `tags`, `anyone_can_modify`, `icon`, `folder_id`, `totp`.

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

**Response:** array of folder objects with hierarchy info.

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

**Body:** `title`, `parent_id`, `complexity`, `duration`, `create_auth_without`, `edit_auth_without`, `icon`, `icon_selected`, `access_rights`.

**Permissions:** `allowed_to_create` + admin/manager checks. Returns 403 if not allowed.

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
| 200 | Success |
| 204 | Empty result (no folders accessible) |
| 400 | Missing or invalid parameters |
| 401 | Missing or invalid JWT |
| 403 | Permission denied (folder read-only, admin required, CRUD rights missing) |
| 404 | Resource not found |
| 405 | HTTP method not supported for this endpoint |
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

---

## Known Gaps (Vague 3 Roadmap)

**Items:** move, copy, history, favorites (toggle), attachments (upload/download/delete), OTV (one-time view link), request_access, edition_lock.

**Folders:** update, delete, copy.

**Users (admin scope):** create, update, delete, disable, folder_rights.

**Auth:** refresh token (`POST /api/v1/auth/refresh`), logout/revoke (`POST /api/v1/auth/logout`), JWT scopes (`scope=full|extension|mobile|readonly`).

**Discovery:** unified search (`GET /api/v1/search?q=...`), OpenAPI 3.1 spec (`/api/v1/openapi.json`).
