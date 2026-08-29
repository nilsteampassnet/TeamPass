<!-- docs/api/api-basic.md -->

# Teampass API Documentation

## Table of Contents

1. [Generalities](#generalities)
   - [Apache Configuration](#apache-configuration)
   - [Teampass Setup](#teampass-setup)
   - [Request Structure](#request-structure)
2. [Authentication](#authentication)
   - [Get JWT Token](#authorize)
   - [Get JWT Token for OAuth2 users (Personal Access Token)](#authorize-token)
3. [Items Endpoints](#items-endpoints)
   - [List items in folders](#list-items-folders)
   - [Get item by ID](#get-item-id)
   - [Search by label](#get-item-label)
   - [Search by description](#get-item-description)
   - [Search by URL](#find-item-url)
   - [Get OTP code](#get-otp)
   - [Create an item](#create-item)
   - [Update an item](#update-item)
   - [Delete an item](#delete-item)
   - [List Tags](#list-tags)
   - [Synchronize a cache](#item-changes)
4. [Folders Endpoints](#folders-endpoints)
   - [List accessible folders](#list-folders)
   - [List folders with access rights](#writable-folders)
     - [Item rights vs folder capabilities](#item-rights-vs-folder-capabilities)
   - [Create a folder](#create-folder)
   - [Update a folder](#folder-update)
   - [Delete a folder](#folder-delete)
5. [Error Handling](#error-handling)
6. [Best Practices](#best-practices)
7. [Command-line client](#cli)

---

## Generalities {#generalities}

Teampass v3 comes with an API permitting several operations on items and folders.

**Key Features:**
- JWT token-based authentication
- API disabled by default
- Requires a valid account and API key

> ⚠️ **Prerequisites**: API usage requires <mark>a valid account and a valid API key</mark>.

### Apache Configuration {#apache-configuration}

Before starting using Teampass API, it is requested to change the default value `LimitRequestFieldSize` directive in Apache settings.

This directive defines the limit on the allowed size of an HTTP request-header field below the normal input buffer size compiled with the server.

> 📝 **Required Configuration**: Set `LimitRequestFieldSize 200000` in `apache2.conf` file.

### Teampass Setup {#teampass-setup}

1. Enable API in the administration interface
2. Set the token validity duration (default: 60 seconds)
3. Create an API key

> 💡 **Tip**: Provide a descriptive label for each API key to identify its usage context.

### Request Structure {#request-structure}

**Base URL:** `<Teampass URL>/api/index.php/<action criteria>`

**Response Format:** JSON

**Authentication:** Bearer Token in `Authorization` header

---

## Authentication {#authentication}

### Get JWT Token {#authorize}

> 📋 Returns the JWT token required for subsequent API queries

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `authorize` |
| **Method** | POST |
| **URL** | `<Teampass URL>/api/index.php/authorize` |
| **Content-Type** | `application/json` |

**Request Body (JSON):**
```json
{
  "apikey": "your-generated-api-key",
  "login": "teampass-user-login",
  "password": "user-password"
}
```

**Response (success):**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "teampass_version": "3.2.1.2",
  "teampass_version_major": "3.2.1",
  "teampass_version_minor": "2"
}
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `token` | string | The JWT to send as `Authorization: Bearer <token>` on every other endpoint |
| `teampass_version` | string | Complete server release, `<major>.<minor>.<patch>.<revision>` |
| `teampass_version_major` | string | Base server version, `<major>.<minor>.<patch>` |
| `teampass_version_minor` | string | Final release revision component |

> The three `teampass_version*` fields are returned in the **response body**, not as JWT claims: the token stays a pure credential, and a server upgraded during a token's lifetime reports its new version at the next authentication rather than at token expiry. A long-lived client that needs to refresh the value without re-authenticating can read the same three fields from `misc/refreshExtensionSettings`.

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Authentication successful, token generated |
| 401 | Invalid credentials |
| 403 | API disabled or invalid API key |
| 500 | Server error |

**Example:**
```bash
curl -X POST "https://your-teampass.com/api/index.php/authorize" \
  -H "Content-Type: application/json" \
  -d '{
    "apikey": "your-api-key",
    "login": "username",
    "password": "password"
  }'
```

---

### Get JWT Token for OAuth2 users (Personal Access Token) {#authorize-token}

> 📋 Returns a JWT token for **OAuth2 (SSO) users**, using a Personal Access Token (PAT) instead of a password + API key.

OAuth2/SSO users have no usable password (their stored credential is a hash of the non-secret identity provider object id), so they cannot use [`authorize`](#authorize). Instead, they generate a **Personal Access Token** from their profile (**Profile → Browser extension tokens → Generate a new token**). The token is displayed **only once** — copy it immediately. The resulting JWT is used exactly like the one from `authorize` (`Authorization: Bearer <jwt>`).

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `authorizeToken` |
| **Method** | POST |
| **URL** | `<Teampass URL>/api/index.php/authorizeToken` |
| **Content-Type** | `application/json` |

> ⚙️ **Prerequisite**: the administrator must enable **Allow OAuth2 users to access the API** (Settings → OAuth2, `oauth2_api_enabled`) **in addition to** the global API setting. When disabled, every request returns `401`.

**Request Body (JSON):**
```json
{
  "login": "teampass-user-login",
  "token": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
}
```

> The `token` must be a 64-character hexadecimal string (`^[a-f0-9]{64}$`). Credentials must be sent in the body — query-string credentials are rejected with `400`.

**Response (success):** identical in shape to [`authorize`](#authorize) — the JWT plus the three server version fields.
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "teampass_version": "3.2.1.2",
  "teampass_version_major": "3.2.1",
  "teampass_version_minor": "2"
}
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Authentication successful, token generated |
| 400 | Missing parameters or credentials passed in the query string |
| 401 | Invalid/expired token, unknown login, non-OAuth2 user, or OAuth2 API access disabled (uniform message) |
| 401 | Account temporarily locked (bruteforce protection) |
| 503 | Global API disabled in settings |
| 500 | Server error |

**Restrictions:** only `auth_type = 'oauth2'` users are accepted; local and LDAP users keep using [`authorize`](#authorize). The same bruteforce protection and `tp_src=api` logging apply.

**Example:**
```bash
curl -X POST "https://your-teampass.com/api/index.php/authorizeToken" \
  -H "Content-Type: application/json" \
  -d '{
    "login": "username",
    "token": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
  }'
```

---

## Items Endpoints {#items-endpoints}

### List items in folders {#list-items-folders}

> 📋 Returns a list of items belonging to the provided folders (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/inFolders` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/inFolders?folders=[590,12]` |
| **Parameters** | `folders`: array of folder IDs (format: [id1,id2,...]) |
| **Headers** | `Authorization: Bearer <token>` |

**Response (success):**
```json
[
  {
    "id": 1027,
    "label": "Teampass production",
    "description": "Use for administration",
    "pwd": "Ajdh-652Syw-625sWW-Ca18",
    "url": "https://teampass.net",
    "login": "tpAdmin",
    "email": "nils@teampass.net",
    "viewed_no": 54,
    "fa_icon": null,
    "inactif": 0,
    "perso": 0
  }
]
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | List returned successfully |
| 400 | Missing or invalid folders parameter |
| 401 | Invalid or expired token |
| 403 | Access denied to requested folders |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/inFolders?folders=[1,2,3]" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Get item by ID {#get-item-id}

> 📋 Returns the item definition based upon its ID (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/get` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/get?id=2052` |
| **Parameters** | `id`: item ID (required) |
| **Headers** | `Authorization: Bearer <token>` |

**Response (success):**
```json
{
  "id": 2053,
  "revision": 4127,
  "revision_changed_at": 1787563378,
  "label": "new object for #3500 v3",
  "description": "<p>bla bla</p>",
  "pwd": "SK^dsf123s_6A}]V$t^]",
  "url": "",
  "login": "Me",
  "email": "",
  "viewed_no": 2,
  "fa_icon": "",
  "inactif": 0,
  "perso": 0,
  "id_tree": 670,
  "folder_label": "MACHINES",
  "path": "issue3317>issue 3325>ITI 2>PROD"
}
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `id` | integer | Unique item ID |
| `revision` | integer | Monotonic revision, bumped on every content change of the item or its custom fields, tags, attachments and OTP. Compare it against a cached copy to detect staleness; the larger value is the newer one. `0` means the item has not changed since revision tracking was installed. |
| `revision_changed_at` | integer or null | Unix UTC timestamp in seconds paired with `revision`. It changes on functional content revisions, never on reads or ciphertext-only rewrites. `null` means the exact date is unknown. |
| `label` | string | Item label |
| `description` | string | Description (may contain HTML) |
| `pwd` | string | Password (decrypted according to rights) |
| `url` | string | Associated URL |
| `login` | string | Login identifier |
| `email` | string | Email address |
| `viewed_no` | integer | Number of views |
| `fa_icon` | string | Custom FontAwesome icon |
| `inactif` | integer | Inactive item (0/1) |
| `perso` | integer | Personal item (0/1) |
| `id_tree` | integer | Parent folder ID |
| `folder_label` | string | Parent folder name |
| `path` | string | Full folder path |
| `fields` | array | Custom fields: array of `{ id, title, type, masked, value }` (value decrypted; empty when no sharekey is available yet). Present only when the *item extra fields* feature is enabled. |

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Item returned successfully |
| 400 | Missing id parameter |
| 401 | Invalid or expired token |
| 403 | Access denied to this item |
| 404 | Item not found |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/get?id=123" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Search by label {#get-item-label}

> 📋 Returns an item list definition based upon its LABEL (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/get` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/get?label="some text"&like=0` |
| **Parameters** | `label`: text to search (required)<br>`like`: search mode (0=exact, 1=pattern with %) |
| **Headers** | `Authorization: Bearer <token>` |

**Search patterns with `like=1`:**

| Pattern | Result |
| ------- | ------ |
| `label="%text"` | Labels ending with "text" |
| `label="%text%"` | Labels containing "text" |
| `label="text%"` | Labels starting with "text" |

**Response (success):**
```json
[
  {
    "id": 21,
    "label": "bug 1",
    "description": "",
    "pwd": "Voici un é1",
    "url": "",
    "login": "",
    "email": "",
    "viewed_no": 13,
    "fa_icon": "",
    "inactif": 0,
    "perso": 0,
    "id_tree": 1,
    "folder_label": "F1",
    "path": ""
  }
]
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Results returned successfully (empty array if no results) |
| 400 | Missing label parameter |
| 401 | Invalid or expired token |
| 403 | Access denied |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/get?label=%25production%25&like=1" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Search by description {#get-item-description}

> 📋 Returns an item list definition based upon its DESCRIPTION (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/get` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/get?description="some text"&like=0` |
| **Parameters** | `description`: text to search (required)<br>`like`: search mode (0=exact, 1=pattern with %) |
| **Headers** | `Authorization: Bearer <token>` |

**Response (success):**
```json
[
  {
    "id": 21,
    "label": "bug 1",
    "description": "some text",
    "pwd": "Voici un é1",
    "url": "",
    "login": "",
    "email": "",
    "viewed_no": 13,
    "fa_icon": "",
    "inactif": 0,
    "perso": 0,
    "id_tree": 1,
    "folder_label": "F1",
    "path": ""
  }
]
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Results returned successfully |
| 400 | Missing description parameter |
| 401 | Invalid or expired token |
| 403 | Access denied |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/get?description=%25server%25&like=1" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Search by URL {#find-item-url}

> 📋 Find items by URL (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/findByUrl` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/findByUrl?url=https://example.com` |
| **Parameters** | `url`: URL to search (required) |
| **Headers** | `Authorization: Bearer <token>` |

**Response (success):**
```json
[
  {
    "id": 123,
    "revision": 4127,
    "revision_changed_at": 1787563378,
    "label": "Example Login",
    "login": "user@example.com",
    "url": "https://example.com",
    "folder_id": 5,
    "has_otp": 1
  }
]
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `id` | integer | Item ID |
| `revision` | integer | Monotonic content revision |
| `revision_changed_at` | integer or null | Unix UTC timestamp in seconds paired with `revision`; `null` when unknown |
| `label` | string | Label |
| `login` | string | Login identifier |
| `url` | string | URL |
| `folder_id` | integer | Parent folder ID |
| `has_otp` | integer | OTP enabled (0/1) |

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Results returned successfully |
| 400 | Missing url parameter |
| 401 | Invalid or expired token |
| 403 | Access denied |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/findByUrl?url=https://example.com" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Get OTP code {#get-otp}

> 📋 Returns the current TOTP (Time-based One-Time Password) code for an item with OTP enabled

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/getOtp` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/getOtp?id=123` |
| **Parameters** | `id`: item ID (required) |
| **Headers** | `Authorization: Bearer <token>` |

**Response (success):**
```json
{
  "otp_code": "123456",
  "expires_in": 25,
  "item_id": 123,
  "algorithm": "sha512",
  "digits": 6,
  "period": 30
}
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `otp_code` | string | Current 6- or 8-digit TOTP code |
| `expires_in` | integer | Seconds until code expires |
| `item_id` | integer | Item ID |
| `algorithm` | string | HMAC algorithm: `sha1`, `sha256`, or `sha512` |
| `digits` | integer | Code length: 6 or 8 |
| `period` | integer | Rotation period in seconds |

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | OTP code generated successfully |
| 400 | Missing item ID |
| 403 | Access denied or OTP not enabled for this item |
| 404 | Item or OTP configuration not found |
| 500 | Decryption or generation failure |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/getOtp?id=123" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Create an item {#create-item}

> 📋 Creates a new item based upon provided parameters

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/create` |
| **Method** | POST |
| **URL** | `<Teampass URL>/api/index.php/item/create` |
| **Content-Type** | `application/json` |
| **Headers** | `Authorization: Bearer <token>` |

**Request Body (JSON):**
```json
{
  "label": "My new item",
  "folder_id": 5,
  "password": "SecureP@ss123",
  "description": "Item description",
  "login": "username",
  "email": "user@example.com",
  "url": "https://example.com",
  "tags": "api,test,production",
  "anyone_can_modify": 0,
  "icon": "fa-solid fa-key",
  "totp": "otpauth://totp/Example:user?secret=BASE32SECRET&algorithm=SHA512&digits=6&period=30"
}
```

**Body Parameters:**

| Field | Type | Required | Description |
| ----- | ---- | -------- | ----------- |
| `label` | string | ✅ | Item label |
| `folder_id` | integer | ✅ | Parent folder ID |
| `password` | string | ✅ | Password (will be encrypted) |
| `description` | string | ❌ | Detailed description |
| `login` | string | ❌ | Login identifier |
| `email` | string | ❌ | Email address |
| `url` | string | ❌ | Associated URL |
| `tags` | string | ❌ | Tags separated by spaces or commas. Each tag is lowercased and capped at 30 characters. |
| `anyone_can_modify` | integer | ❌ | Anyone can modify (0/1, default: 0) |
| `icon` | string | ❌ | FontAwesome icon code |
| `totp` | string | ❌ | Base32 TOTP secret or `otpauth://totp` provisioning URI. Spaces and hyphens are separators and are stripped, so the secret can be sent exactly as the service displays it |
| `totp_algorithm` | string | ❌ | Algorithm for a bare secret: `sha1` (default), `sha256`, or `sha512`; ignored when supplied by a URI |
| `totp_digits` | integer | ❌ | Code length for a bare secret: 6 (default) or 8 |
| `totp_period` | integer | ❌ | Period for a bare secret: 30 seconds by default, from 1 to 86400 |
| `fields` | array | ❌ | Custom fields: array of `{ "id": <field_id>, "value": "<text>" }`. Only fields tied to the item's folder are stored; empty values are ignored. Requires the *item extra fields* feature to be enabled. |

**Response (success):**
```json
{
  "error": false,
  "message": "Item created successfully",
  "newId": 658,
  "revision": 4127,
  "revision_changed_at": 1787563378
}
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Item created successfully |
| 400 | Missing or invalid parameters |
| 401 | Invalid token or expired session |
| 403 | Create permission denied or access denied to folder |
| 500 | Server error |

**Example:**
```bash
curl -X POST "https://your-teampass.com/api/index.php/item/create" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "label": "My new item",
    "folder_id": 5,
    "password": "SecureP@ss123",
    "description": "Item created via API",
    "login": "apiuser",
    "email": "api@example.com",
    "url": "https://example.com",
    "tags": "api,test",
    "anyone_can_modify": 0,
    "icon": "fa-solid fa-key"
  }'
```

---

### Update an item {#update-item}

> 📋 Updates an existing item based upon provided parameters and item ID

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/update` |
| **Method** | PUT |
| **URL** | `<Teampass URL>/api/index.php/item/update` |
| **Content-Type** | `application/json` |
| **Headers** | `Authorization: Bearer <token>` |

**Request Body (JSON):**
```json
{
  "id": 123,
  "label": "Updated label",
  "password": "NewSecureP@ss456",
  "description": "Updated description"
}
```

**Body Parameters:**

| Field | Type | Required | Description |
| ----- | ---- | -------- | ----------- |
| `id` | integer | ✅ | Item ID to update |
| `revision` | integer | ❌ | Precondition, not an updatable field: the revision the edit was based on. The update is refused with `409` when the server has moved on since — see [Synchronize a cache](#item-changes). Omitting it keeps the previous last-writer-wins behaviour. |
| `label` | string | ❌ | New label |
| `password` | string | ❌ | New password |
| `description` | string | ❌ | New description |
| `login` | string | ❌ | New login identifier |
| `email` | string | ❌ | New email address |
| `url` | string | ❌ | New URL |
| `tags` | string | ❌ | New tags, separated by spaces or commas (replaces existing tags). Each tag is lowercased and capped at 30 characters. |
| `anyone_can_modify` | integer | ❌ | Anyone can modify (0/1) |
| `icon` | string | ❌ | New FontAwesome icon code |
| `folder_id` | integer | ❌ | Move to new folder |
| `totp` | string | ❌ | Base32 TOTP secret, `otpauth://totp` URI, or an empty string to remove TOTP. Spaces and hyphens are stripped from the secret. Omit the field to change only the profile: the stored secret is reused |
| `totp_algorithm` | string | ❌ | TOTP algorithm: `sha1`, `sha256`, or `sha512` |
| `totp_digits` | integer | ❌ | TOTP code length: 6 or 8 |
| `totp_period` | integer | ❌ | TOTP period in seconds, from 1 to 86400 |
| `fields` | array | ❌ | Custom fields to set: array of `{ "id": <field_id>, "value": "<text>" }`. A field is created if absent and updated when its value changes; empty values are ignored. Requires the *item extra fields* feature. |

> ⚠️ **Important**: At least one field to update must be provided in addition to the ID.

> ⚠️ **Moving an item out of a personal folder into a shared one must be a request of its own.** That move re-encrypts the item's keys for every user who will now have access, and it is committed immediately. Combining it with any other updatable field (`label`, `password`, `description`, `login`, `email`, `url`, `tags`, `anyone_can_modify`, `icon`, `fields`, `totp*`) is rejected with `422` — send `{ "id": ..., "folder_id": ... }` alone, then send the rest in a second request. All other moves (shared → shared, shared → personal, personal → personal) can still be combined freely with other fields.

**Response (success):**
```json
{
  "error": false,
  "message": "Item updated successfully",
  "item_id": 123,
  "revision": 4128,
  "revision_changed_at": 1787563378
}
```

When `password` is present, TeamPass compares it with the current cleartext value server-side.
Resending the same value is a password no-op: by itself it does not rewrite the ciphertext,
redistribute sharekeys, create a password-history entry, or allocate a revision. A real change stores the previous password
encrypted in the existing `at_pw` history; the client never sends or receives that previous value.

Because the current value must be read first, a password update requires your account to already
hold the item's encryption key. If it does not, the request is refused with `422` and **nothing is
written** — no half-applied update. This is usually temporary: right after another user creates or
changes a shared item, keys are distributed to the other users by a background task. Retry the
request; every other field remains updatable in the meantime. If it keeps failing, ask an
administrator to run the encryption keys repair task.

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Item updated successfully |
| 400 | Missing ID or no fields to update |
| 401 | Invalid session or user keys not found |
| 403 | Update permission denied or access denied — including a folder granted as `R`, `NE` or `NDNE` (check `can_edit` on [`folder/writableFolders`](#writable-folders)) |
| 404 | Item not found |
| 405 | HTTP method not supported (only `PUT` is accepted) |
| 409 | The supplied `revision` no longer matches the item — someone changed it since; resolve the conflict instead of retrying blindly. Also returned when the item was moved or re-encrypted by another request while this move was being prepared, which is a plain retry |
| 422 | Validation failed: a personal → shared move combined with another field, or one of the item's encryption keys could not be recovered — including the current password on a password update (the item is left untouched; retry while keys are still being distributed) |
| 500 | Server error |

**Example - Update password and description:**
```bash
curl -X PUT "https://your-teampass.com/api/index.php/item/update" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": 123,
    "password": "NewSecureP@ss456",
    "description": "Updated description"
  }'
```

**Example - Move to another folder:**
```bash
curl -X PUT "https://your-teampass.com/api/index.php/item/update" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": 123,
    "folder_id": 5
  }'
```

---

### Delete an item {#delete-item}

> 📋 Deletes an existing item based upon its ID

> ⚠️ **Warning**: This action is irreversible!

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/delete` |
| **Method** | DELETE |
| **URL** | `<Teampass URL>/api/index.php/item/delete` |
| **Content-Type** | `application/json` |
| **Headers** | `Authorization: Bearer <token>` |

**Request Body (JSON):**
```json
{
  "id": 123
}
```

**Body Parameters:**

| Field | Type | Required | Description |
| ----- | ---- | -------- | ----------- |
| `id` | integer | ✅ | Item ID to delete |

**Response (success):**
```json
{
  "error": false,
  "message": "Item deleted successfully",
  "item_id": "123"
}
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Item deleted successfully |
| 400 | Missing ID or inconsistent data |
| 403 | Delete permission denied or access denied — including a folder granted as `R`, `ND` or `NDNE` (check `can_delete` on [`folder/writableFolders`](#writable-folders)) |
| 404 | Item not found |
| 422 | HTTP method not supported (must be DELETE) |
| 500 | Server error |

**Example:**
```bash
curl -X DELETE "https://your-teampass.com/api/index.php/item/delete" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "id": 123
  }'
```

---

### Get all tags {#all-tags}

> 📋 Returns the complete list of unique tags existing in the database

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/allTags` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/allTags` |
| **Parameters** | None |
| **Headers** | `Authorization: Bearer <token>` |

**Response (success):**
```json
["confidential", "finance", "infra", "web"]
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | List of tags returned successfully |
| 422 | HTTP method not supported (must be GET) |
| 500 | Server error |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/allTags" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Synchronize a cache {#item-changes}

> 🔄 Returns what changed since a given revision — the endpoint an offline client polls instead of re-downloading the whole vault

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `item/changes` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/item/changes` |
| **Parameters** | `since` (required), `limit` (optional) |
| **Headers** | `Authorization: Bearer <token>` |

**Parameters:**

| Parameter | Type | Required | Description |
| --------- | ---- | -------- | ----------- |
| `since` | integer | Yes | Cursor returned by the previous call, exclusive. `0` on a first synchronization. |
| `limit` | integer | No | Journal entries scanned in one call. Default 200, maximum 1000. |

**Response (success):**
```json
{
  "cursor": 12345,
  "has_more": false,
  "full_sync_required": false,
  "changed": [
    { "id": 77, "revision": 12340, "revision_changed_at": 1787563378, "label": "Prod database", "pwd": "…", "fields": [] }
  ],
  "removed": [
    { "id": 91, "revision": 12331, "revision_changed_at": 1787563100, "reason": "deleted" }
  ]
}
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `cursor` | integer | Store it and send it as `since` on the next call |
| `has_more` | boolean | More changes are waiting — call again with the returned cursor |
| `full_sync_required` | boolean | The cursor cannot be served: rebuild the cache and adopt the returned cursor |
| `changed` | array | Items to upsert, same shape as `item/get` |
| `removed` | array | Items to drop, with `id`, `revision`, `revision_changed_at` and `reason` |

The `revision` and `revision_changed_at` values always come from the same winning change. For a
tombstone, the timestamp comes from the journal because a permanently deleted item has no row left.

`reason` is one of `deleted` (soft deleted), `purged` (permanently removed) or `out_of_scope` (the item still exists but the caller can no longer read it, typically after a move into a folder they have no access to).

**Synchronization protocol:**

1. **First run** — call with `since=0`. The answer is always `full_sync_required: true` plus a cursor: items untouched since revision tracking was installed are still at revision `0` and have no journal entry, so a delta would silently miss them. Read the vault through [item/inFolders](#list-items-folders), store each item with its `revision`, and store the cursor.
2. **Reconnect** — call with the stored cursor. Upsert `changed`, delete `removed`, store the new cursor, and repeat while `has_more` is true. A `full_sync_required: true` sends you back to step 1 — it also happens when the client has been offline longer than the journal retention.
3. **Offline edits** — send the `revision` the edit was based on in [item/update](#update-item). A `409` means the server moved on: resolve the conflict instead of overwriting.
4. **Folder scope** — also refresh [item rights](#writable-folders) and drop any cached item whose folder is no longer listed. Losing access to a folder changes nothing on the items themselves, so it produces no entry in this feed.

**How far back the feed reaches:**

The server keeps its change journal for the duration set in **Settings → API → Offline synchronization window** (`offline_sync_window_days`, 90 days by default, `0` for no limit). A device that reconnects within that window catches up incrementally; one that has been offline longer is answered `full_sync_required` and rebuilds its cache.

> 🔔 This window is **not** a data retention. It deletes no item, no password and no history — those are governed separately and are never affected. It only bounds how far back the incremental catch-up reaches, so the only cost of a short window is bandwidth for devices that were offline a long time.

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Changes returned successfully |
| 400 | Missing `since` parameter |
| 401 | Invalid or expired token |
| 405 | HTTP method not supported (must be GET) |
| 500 | Server error |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/item/changes?since=12300" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Folders Endpoints {#folders-endpoints}

### List accessible folders {#list-folders}

> 📋 Returns the list of folders accessible to the authenticated user

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `folder/listFolders` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/folder/listFolders` |
| **Parameters** | None |
| **Headers** | `Authorization: Bearer <token>` |

| **Query parameters** | `limit`, `offset` (optional, applied to the root-level entries) |

**Response (success):** a nested tree. Each node carries its own children in `childrens`.

```json
[
  {
    "id": 1,
    "title": "Production",
    "isVisible": true,
    "complexity": 38,
    "childrens": [
      {
        "id": 2,
        "title": "Servers",
        "isVisible": true,
        "complexity": 48,
        "childrens": []
      }
    ]
  }
]
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `id` | integer | Unique folder ID |
| `title` | string | Folder name |
| `isVisible` | boolean | `false` when the folder is only returned to carry accessible children |
| `complexity` | integer | Minimum password strength required in this folder (see table below) |
| `childrens` | array | Child nodes, same structure |

**Complexity levels:**

| Value | Level |
| ----- | ----- |
| `0` | Weak |
| `20` | Medium |
| `38` | Strong |
| `48` | Very strong |
| `60` | Heavy |

`0` is also returned when the folder carries no complexity rule (for example a personal root folder).

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | List returned successfully (empty list when no accessible folder) |
| 401 | Invalid or expired token |
| 403 | Access denied |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/folder/listFolders" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

> 💡 Need the access rights on each folder, or a flat list that is easier to iterate? Use [`folder/writableFolders`](#writable-folders) instead.

---

### List folders with access rights {#writable-folders}

> 📋 Returns every folder accessible to the authenticated user as a **flat list in tree order**, with the read-only flag on each entry

The name is historical: the endpoint returns **all** accessible folders, not only the writable ones — check `is_readonly` on each entry.

Rows are sorted by `position` (the folder tree's own order, siblings included), so `parent_id` + `level` + `position` are enough to rebuild the exact hierarchy in a single call.

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `folder/writableFolders` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/folder/writableFolders` |
| **Parameters** | None |
| **Headers** | `Authorization: Bearer <token>` |

**Response (success):**

> The example below is the response seen by a user who passes the global folder-management gate (administrator, manager, or the `enable_user_can_create_folders` setting turned on) and holds the three API CRUD permissions. A standard user who does not pass that gate gets `0` on every `can_*_folder` field of the **shared** folders, while keeping them on his own personal tree.

```json
[
  {
    "id": 12,
    "label": "jdoe",
    "level": 1,
    "parent_id": 0,
    "first_position": 1,
    "position": 23,
    "complexity": 0,
    "is_readonly": 0,
    "access_type": "W",
    "can_create": 1,
    "can_edit": 1,
    "can_delete": 1,
    "is_personal": 1,
    "is_personal_root": 1,
    "can_create_subfolder": 1,
    "can_rename_folder": 0,
    "can_move_folder": 0,
    "can_delete_folder": 0
  },
  {
    "id": 1,
    "label": "Production",
    "level": 1,
    "parent_id": 0,
    "first_position": 0,
    "position": 41,
    "complexity": 38,
    "is_readonly": 0,
    "access_type": "ND",
    "can_create": 1,
    "can_edit": 1,
    "can_delete": 0,
    "is_personal": 0,
    "is_personal_root": 0,
    "can_create_subfolder": 1,
    "can_rename_folder": 1,
    "can_move_folder": 1,
    "can_delete_folder": 1
  },
  {
    "id": 2,
    "label": "Servers",
    "level": 2,
    "parent_id": 1,
    "first_position": 0,
    "position": 42,
    "complexity": 48,
    "is_readonly": 1,
    "access_type": "R",
    "can_create": 0,
    "can_edit": 0,
    "can_delete": 0,
    "is_personal": 0,
    "is_personal_root": 0,
    "can_create_subfolder": 0,
    "can_rename_folder": 0,
    "can_move_folder": 0,
    "can_delete_folder": 0
  }
]
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `id` | integer | Unique folder ID |
| `label` | string | Folder name (the user's login for their personal root folder) |
| `level` | integer | Depth level in the tree |
| `parent_id` | integer | Parent folder ID (0 for root) |
| `first_position` | integer | `1` for the user's personal root folder, to be listed first |
| `position` | integer | Tree position; the list is already sorted on it |
| `complexity` | integer | Minimum password strength required in this folder: `0` Weak, `20` Medium, `38` Strong, `48` Very strong, `60` Heavy (`0` also when no rule is set) |
| `is_readonly` | integer | `1` = read access only (`access_type` is `R`), `0` = the user can write |
| `access_type` | string | Effective access level: `W`, `ND`, `NE`, `NDNE` or `R` |
| `can_create` | integer | **Item right** — `1` when the user may create items in this folder. Does **not** authorize creating a subfolder |
| `can_edit` | integer | **Item right** — `1` when the user may edit existing items (`0` for `NE`, `NDNE`, `R`). Does **not** authorize renaming or moving the folder |
| `can_delete` | integer | **Item right** — `1` when the user may delete items (`0` for `ND`, `NDNE`, `R`). Does **not** authorize deleting the folder |
| `is_personal` | integer | `1` when the folder is part of a personal folder tree, `0` for the shared domain. Only your own personal folders are ever listed |
| `is_personal_root` | integer | `1` for the root of your personal tree. A personal root can never be renamed, moved or deleted |
| `can_create_subfolder` | integer | **Folder capability** — `1` when [`folder/create`](#create-folder) may use this folder as its `parent_id` |
| `can_rename_folder` | integer | **Folder capability** — `1` when this folder is eligible for a rename through [`folder/update`](#folder-update) |
| `can_move_folder` | integer | **Folder capability** — `1` when this folder is eligible to be moved. Describes the **source only**, see the warning below |
| `can_delete_folder` | integer | **Folder capability** — `1` when this folder is eligible for deletion through [`folder/delete`](#folder-delete) |

> ⚠️ `is_readonly: 0` does **not** mean full write access. A folder granted as `ND`, `NE` or `NDNE` is writable but restricts deletion and/or edition — rely on `can_edit` / `can_delete` rather than on `is_readonly` alone, otherwise a legitimate call will come back as `403`.
>
> When several roles grant different levels on the same folder, the **least permissive wins** (`R` > `NDNE` > `NE` = `ND` > `W`), exactly like the web interface. See [Rights management](../features/rights.md).

#### Item rights vs folder capabilities {#item-rights-vs-folder-capabilities}

The two families answer different questions and are **not** interchangeable:

| | `can_create` / `can_edit` / `can_delete` | `can_create_subfolder` / `can_rename_folder` / `can_move_folder` / `can_delete_folder` |
| --- | --- | --- |
| Scope | The **items stored in** the folder | The **folder itself** |
| Driven by | The folder access level (`W`, `ND`, `NE`, `NDNE`, `R`) | Access level **+** the global folder-management gate **+** personal-root protection |

A folder granted as `ND` illustrates the difference: `can_delete: 0` (you may not delete the items it contains) while `can_delete_folder: 1` (you may delete the folder itself).

The global folder-management gate is passed when **any** of these is true: you are an administrator, a manager, you hold *manage all users* or *create root folder*, the `enable_user_can_create_folders` setting is on, or the folder belongs to your personal tree.

> ⚠️ These four fields are **UI hints**. The server stays authoritative and re-runs every check when the mutation is actually attempted — never treat a `1` as a guarantee of success.
>
> ⚠️ `can_move_folder` qualifies the **source folder only**. [`folder/update`](#folder-update) separately validates the chosen destination: accessibility, read-only state, move into itself or into one of its own descendants, personal ↔ shared boundary, and the permission to move to the root. A `can_move_folder: 1` can therefore still be answered with a `403` or `422` depending on the destination you pick.

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | List returned successfully (empty list when no accessible folder) |
| 401 | Invalid or expired token |
| 403 | Access denied |
| 405 | HTTP method not supported (must be GET) |
| 500 | Server error |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/folder/writableFolders" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

Print `id`, depth, name and access on one line per folder, already in tree order:
```bash
curl -s -X GET "https://your-teampass.com/api/index.php/folder/writableFolders" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  | jq -r '.[] | [.id, .level, .label, (if .is_readonly == 1 then "read-only" else "write" end)] | @tsv'
```

---

### Create a folder {#create-folder}

> 📋 Creates a new folder based upon provided parameters

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `folder/create` |
| **Method** | POST |
| **URL** | `<Teampass URL>/api/index.php/folder/create` |
| **Content-Type** | `application/json` |
| **Headers** | `Authorization: Bearer <token>` |

**Request Body (JSON):**
```json
{
  "title": "New folder",
  "parent_id": 1,
  "complexity": 38,
  "duration": 0,
  "create_auth_without": 0,
  "edit_auth_without": 0,
  "icon": "fa-folder",
  "icon_selected": "fa-folder-open",
  "access_rights": "W"
}
```

**Body Parameters:**

| Field | Type | Required | Description |
| ----- | ---- | -------- | ----------- |
| `title` | string | ✅ | Folder name |
| `parent_id` | integer | ✅¹ | Parent folder ID (0 for root if authorized) |
| `complexity` | integer | ✅¹ | Complexity level: 0 (Weak), 20 (Medium), 38 (Strong), 48 (Heavy), 60 (Very heavy) |
| `private` | boolean | ❌ | Create a personal (private) folder under your personal root. When `true`, `parent_id` and `complexity` become optional. Personal folders must be enabled for your account. |
| `duration` | integer | ❌ | Expiration delay in minutes (0 = no expiration) |
| `create_auth_without` | integer | ❌ | Allow creation even if complexity insufficient (0/1) |
| `edit_auth_without` | integer | ❌ | Allow update even if complexity insufficient (0/1) |
| `icon` | string | ❌ | FontAwesome icon code (closed state) |
| `icon_selected` | string | ❌ | FontAwesome icon code (open/selected state) |
| `access_rights` | string | ❌ | Access type granted to your roles on the new folder: R (Read), W (Write), ND (No deletion), NE (No edit), NDNE (No deletion and No edit). **Defaults to `W`** when omitted. |

> ¹ `parent_id` and `complexity` are required for a **shared** folder. When `private` is `true` (personal folder), both are optional — `parent_id` defaults to your personal root and the complexity ceiling does not apply. The `personal_folder` flag is always derived server-side; it is never accepted from the client.
>
> A `title` made only of whitespace is rejected with `422`.

**Possible values for `complexity`:**

| Value | Level |
| ----- | ----- |
| 0 | Weak |
| 20 | Medium |
| 38 | Strong |
| 48 | Heavy |
| 60 | Very heavy |

**Possible values for `access_rights`:**

| Value | Description |
| ----- | ----------- |
| R | Read only |
| W | Read and write |
| ND | No deletion |
| NE | No edit |
| NDNE | No deletion and no edit |

**Response (success):**
```json
{
  "error": false,
  "message": "",
  "newId": "148"
}
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 201 | Folder created successfully |
| 400 | Missing required parameters |
| 401 | Invalid token or expired session |
| 403 | Create permission denied / personal folders disabled / read-only or foreign parent |
| 422 | Invalid parameters, numeric title, duplicate title, or complexity below parent |
| 500 | Server error |

**Example:**
```bash
curl -X POST "https://your-teampass.com/api/index.php/folder/create" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "New folder",
    "parent_id": 1,
    "complexity": 38,
    "duration": 0,
    "create_auth_without": 0,
    "edit_auth_without": 0,
    "icon": "fa-folder",
    "icon_selected": "fa-folder-open",
    "access_rights": "W"
  }'
```

**Example (private folder):**
```bash
curl -X POST "https://your-teampass.com/api/index.php/folder/create" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{ "title": "My private folder", "private": true }'
```

---

### Update a folder {#folder-update}

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `folder/update` |
| **Method** | PUT |
| **URL** | `<Teampass URL>/api/index.php/folder/update` |
| **Content-Type** | `application/json` |
| **Headers** | `Authorization: Bearer <token>` |

Partial update: only `id` is required; any field you omit keeps its current value. Only `PUT` is accepted (other methods return `405`).

**Request Body (JSON):**
```json
{
  "id": 57,
  "title": "Renamed folder",
  "parent_id": 12,
  "complexity": 38
}
```

**Body Parameters:**

| Field | Type | Required | Description |
| ----- | ---- | -------- | ----------- |
| `id` | integer | ✅ | Folder ID to update |
| `title` | string | ❌ | New folder name |
| `parent_id` | integer | ❌ | New parent ID (move). Cross-domain personal ↔ shared moves are rejected. |
| `complexity` | integer | ❌ | New complexity level. Must be one of 0, 20, 38, 48, 60 — any other value is rejected with `422`. |
| `duration` | integer | ❌ | Expiration delay in minutes |
| `create_auth_without` | integer | ❌ | Allow creation even if complexity insufficient (0/1) |
| `edit_auth_without` | integer | ❌ | Allow update even if complexity insufficient (0/1) |
| `icon` | string | ❌ | FontAwesome icon code (closed state) |
| `icon_selected` | string | ❌ | FontAwesome icon code (open/selected state) |

> `access_rights` cannot be changed here — folder rights are a roles-management concern. Personal **root** folders cannot be renamed or moved. At least one updatable field must be provided. An empty or whitespace-only `title` is rejected with `422`.

**Response (success):**
```json
{
  "error": false,
  "message": "Folder updated",
  "id": 57
}
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Folder updated successfully |
| 400 | Missing `id` or nothing to update |
| 401 | Invalid token or expired session |
| 403 | Update permission denied / read-only folder / personal root rename or move |
| 404 | Folder not found |
| 405 | Method not allowed (use PUT) |
| 422 | Empty or numeric title, duplicate title, invalid complexity level, circular/descendant move, cross-domain move, or complexity below parent |
| 500 | Server error |

**Example:**
```bash
curl -X PUT "https://your-teampass.com/api/index.php/folder/update" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{ "id": 57, "title": "Renamed folder" }'
```

---

### Delete a folder {#folder-delete}

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `folder/delete` |
| **Method** | DELETE |
| **URL** | `<Teampass URL>/api/index.php/folder/delete?id=57` |
| **Headers** | `Authorization: Bearer <token>` |

Soft-deletes the folder **and all its descendants** into the recycle bin (restorable from **Utilities → Recycled bin**); every contained item is soft-deleted too. Only `DELETE` is accepted (other methods return `405`).

**Parameters:**

| Field | Type | Required | Description |
| ----- | ---- | -------- | ----------- |
| `id` | integer | ✅ | Folder ID to delete (query string `?id=N` or JSON body). `0` (root) is rejected. |

**Response (success):**
```json
{
  "error": false,
  "message": "Folder deleted",
  "deleted_folders": [57, 58, 61],
  "deleted_items_count": 12
}
```

`deleted_folders` lists the folder plus every descendant that was removed.

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Folder deleted successfully |
| 400 | Missing or invalid `id` (including `0`) |
| 401 | Invalid token or expired session |
| 403 | Delete permission denied / read-only folder / personal root |
| 404 | Folder not found |
| 405 | Method not allowed (use DELETE) |
| 500 | Server error |

**Example:**
```bash
curl -X DELETE "https://your-teampass.com/api/index.php/folder/delete?id=57" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Error Handling {#error-handling}

All API endpoints may return the following standard HTTP error codes:

| HTTP Code | Description |
| --------- | ----------- |
| 200 | Request processed successfully |
| 400 | Missing or invalid parameters |
| 401 | Invalid, expired JWT token or insufficient permissions |
| 403 | User doesn't have permission to perform the action |
| 404 | Resource not found or API is disabled |
| 422 | HTTP method not supported |
| 500 | Internal server error |

**Error Response Format:**
```json
{
  "error": "Error description message"
}
```

**Or:**
```json
{
  "error": true,
  "message": "Error description message"
}
```

---

## Best Practices {#best-practices}

### Security

1. **Token Management**
   - Store JWT tokens securely
   - Refresh tokens before expiration
   - Never share tokens in plain text

2. **API Keys**
   - Never commit API keys to version control
   - Use environment variables
   - Create separate API keys per usage context

3. **HTTPS**
   - Always use HTTPS in production
   - Avoid API requests over unsecured connections
   - **Use a fully trusted certificate whose FQDN matches the API URL.** Browser-based clients
     (the browser extension, any `fetch()` call) silently drop the connection **and the
     `Authorization` header** when the certificate is self-signed, expired, or its CN/SAN does
     not match the host — this usually surfaces as a `Failed to fetch` error. With an untrusted
     or mismatched certificate the request never reaches Teampass, so there is **no server-side
     log**. Command-line tools (`curl`) can bypass this with `-k`, but browsers cannot — this is a
     server/environment requirement, not an application bug.

### Performance

1. **Rate Limiting**
   - Respect request frequency limits
   - Implement retry mechanism with exponential backoff
   - Avoid intensive API call loops

2. **Caching**
   - Cache responses when appropriate
   - Respect data validity durations

### Development

1. **Parameter Encoding**
   - Properly encode all URL parameters
   - Use JSON for complex request bodies
   - Handle special characters in passwords

2. **Error Handling**
   - Always check HTTP response codes
   - Implement robust error handling
   - Log errors for debugging

3. **Permissions**
   - Verify the account has necessary permissions
   - Test with different access right levels

---

## Command-line client {#cli}

Teampass ships a small command-line client that wraps the JWT authentication and the most common endpoints. Two equivalent implementations are provided:

| Script | Platform | Requirements |
|---|---|---|
| `app/scripts/teampass-cli.sh` | Linux / macOS / any Bash shell | `curl` and `jq` |
| `app/scripts/teampass-cli.ps1` | Windows (native PowerShell) | PowerShell 5.1+ (built-in `Invoke-RestMethod`) |

The PowerShell version provides full feature parity with the Bash one — same commands, same options, same output — so Windows environments no longer need WSL, Git Bash or any third-party Bash runtime to use the API from the command line, Task Scheduler or an automation script.

**Configuration** — environment variables, or a configuration file (`~/.config/teampass/config` on Bash, `%LOCALAPPDATA%\teampass\config` on PowerShell):

```bash
export TEAMPASS_URL="https://your-teampass.com"
export TEAMPASS_LOGIN="jdoe"

# password mode
export TEAMPASS_PASSWORD="..."
export TEAMPASS_APIKEY="..."

# ...or token mode (Personal Access Token, see /authorizeToken)
export TEAMPASS_TOKEN="..."
```

On PowerShell, the same variables are set with `$ENV:TEAMPASS_URL = "https://your-teampass.com"`, or written as `TEAMPASS_URL="https://your-teampass.com"` lines in the configuration file.

**Commands (Bash):**

```bash
./app/scripts/teampass-cli.sh folders --tree          # folder tree with access rights
./app/scripts/teampass-cli.sh read 25                 # read an item
./app/scripts/teampass-cli.sh create 5 "My Server" "admin" "S3cr3t!" "Root credentials"
./app/scripts/teampass-cli.sh update 25 label "Updated Server"
./app/scripts/teampass-cli.sh search "server"         # by label
./app/scripts/teampass-cli.sh search "192.168." --by-desc
./app/scripts/teampass-cli.sh search "https://app" --by-url
```

**Commands (PowerShell):**

```powershell
.\app\scripts\teampass-cli.ps1 folders --tree         # folder tree with access rights
.\app\scripts\teampass-cli.ps1 read 25                # read an item
.\app\scripts\teampass-cli.ps1 create 5 "My Server" "admin" "S3cr3t!" "Root credentials"
.\app\scripts\teampass-cli.ps1 update 25 label "Updated Server"
.\app\scripts\teampass-cli.ps1 search "server"        # by label
.\app\scripts\teampass-cli.ps1 search "192.168." --by-desc
.\app\scripts\teampass-cli.ps1 search "https://app" --by-url
```

`folders --tree` renders the hierarchy directly, because [`folder/writableFolders`](#writable-folders) already returns the folders in tree order:

```
jdoe [12]
Production [1]
  Servers [2] (read-only)
    Databases [3] (read-only)
```

**Notes:**
- The JWT is requested **once per invocation** and kept in memory — never written to disk. Each authentication opens a server-side API session, so a script must not re-authenticate on every request.
- A `429` answer is retried once, honouring the `Retry-After` header.
- The configuration file holds credentials in clear text: keep it at `chmod 600` (Bash) or restrict its NTFS permissions to the current user (PowerShell).
- On Windows, an execution policy may block the script. Run it in the current session with `powershell -ExecutionPolicy Bypass -File .\app\scripts\teampass-cli.ps1 <command>`, or unblock the file with `Unblock-File .\app\scripts\teampass-cli.ps1`.
