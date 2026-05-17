# API Reference

## Entry Points

- **Public entry:** `public/api/index.php` (proxies to `app/api/index.php`)
- **App entry:** `app/api/index.php` — router, CORS, network ACL, JWT validation
- **Bootstrap:** `app/api/inc/bootstrap.php` — DB, autoloader, CRUD rights check
- **JWT utils:** `app/api/inc/jwt_utils.php` — `is_jwt_valid()`, `getApiJwtSigningKey()`
- **Controllers:** `app/api/Controller/Api/`
- **Models:** `app/api/Model/`

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
- Expiry: `api_token_duration + 600` seconds (configurable in settings)
- Key claims: `id`, `username`, `exp`, `key_tempo`, `is_admin`, `is_manager`, `allowed_to_create`, `allowed_to_read`, `allowed_to_update`, `allowed_to_delete`, `folders_list`

**Private key architecture:** User private key is encrypted with a per-session AES-256-GCM key (`session_aes_key`) stored server-side in `teampass_api`. The JWT carries only `key_tempo` (a reference). A stolen JWT alone cannot decrypt the private key.

**No refresh token.** Re-authenticate via `/authorize` when expired.

---

## Item Endpoints

All require `Authorization: Bearer <jwt>`.

### `GET /api/item/get`

Get item(s) by ID or label.

**Params:** `id` (int) OR `label` (string) OR `description` (string), optional `limit` (default 50).

**Response:** array of item objects `{ id, label, description, login, email, url, password, path, folder_id, folder_label, has_otp, favicon_url, tags }`.

**Permissions:** `allowed_to_read`. Uses folder access constraint — IDOR protection via sharekey (item skipped if no sharekey found for user).

---

### `GET /api/item/inFolders`

Get items in one or more folders.

**Params:** `folders` (comma-separated or JSON array of folder IDs), optional `limit`.

**Permissions:** `allowed_to_read`.

---

### `GET /api/item/findByUrl`

Find items by URL match.

**Params:** `url` (string).

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

**Permissions:** `allowed_to_create`. Blocked if folder is read-only for user.

---

### `PUT /api/item/update`

Update an existing item.

**Body:** `id` (required), at least one of: `label`, `password`, `description`, `login`, `email`, `url`, `tags`, `anyone_can_modify`, `icon`, `folder_id`, `totp`.

**Permissions:** `allowed_to_update`. Source folder must not be read-only. If `folder_id` changes (move), **target folder** must also not be read-only for the user.

---

### `DELETE /api/item/delete`

Soft-delete an item.

**Params:** `id` (int).

**Permissions:** `allowed_to_delete`. Blocked if folder is read-only.

---

## Folder Endpoints

### `GET /api/folder/listFolders`

List all folders accessible to the authenticated user.

**Response:** array of folder objects with hierarchy info.

**Permissions:** `allowed_to_read`.

---

### `GET /api/folder/writableFolders`

List folders from `folders_list` with label and level info.

**Note:** name is historical — returns all accessible folders (read-only included). Use folder metadata to determine write access.

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

### `POST /api/misc/refreshExtensionSettings`

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

Current (v3.2): `Access-Control-Allow-Origin` reflects the server's own `Host` header. Browser extensions (`chrome-extension://`, `moz-extension://`) are blocked by CORS — this will be fixed in Vague 2 with an origin whitelist configurable in `teampass_misc` (`api_cors_origins`).

Security headers present: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`.

Missing (Vague 2): `Strict-Transport-Security`, `Content-Security-Policy`.

---

## Versioning

No API versioning yet — all routes are `/api/<controller>/<action>`. Vague 2 will introduce `/api/v1/` with backward compatibility, plus `X-Api-Version` response header.

---

## Security Architecture Notes

1. **JWT secret** is stored in `teampass_misc` (key: `api_jwt_secret`, type: `admin`), lazily generated on first call. Rotation: delete or update the row + wait for existing tokens to expire.
2. **User private key** never leaves the server unencrypted. The JWT carries only `key_tempo` (a reference). The server-side `session_aes_key` in `teampass_api` is required to decrypt the private key on each request.
3. **Bruteforce** thresholds: `nb_bad_authentication` (default 10), `nb_bad_authentication_by_ip` (default 30), `bruteforce_lock_duration` (default 10 min). Configure in TeamPass admin settings.
4. **Read-only folders**: enforced in create/update/delete item operations and folder create. An item move to a read-only target folder is also blocked (as of wave 1).
5. **Logging**: successful logins logged as `user_connection` with `tp_src=api`. Failed auth logged as `failed_auth` with `tp_src=api`. Visible in Admin > Logs.

---

## Known Gaps (Roadmap)

**Vague 2 (robustness):** CORS origin whitelist for extensions, versioning `/api/v1/`, HSTS, uniform response format, `decryptUserObjectKeyWithMigration` in `ItemModel::getItems`, strict HTTP verbs, cap `limit` on all endpoints.

**Vague 3 (completeness):** item move/copy/history/favorites/attachments/OTV, folder update/delete, user CRUD (admin), search, refresh token, JWT scopes, logout/revoke, OpenAPI 3.1.
