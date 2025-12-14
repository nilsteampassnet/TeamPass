<!-- docs/api/api-basic.md -->

# Teampass API Documentation

## Table of Contents

1. [Generalities](#generalities)
   - [Apache Configuration](#apache-configuration)
   - [Teampass Setup](#teampass-setup)
   - [Request Structure](#request-structure)
2. [Authentication](#authentication)
   - [Get JWT Token](#authorize)
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
4. [Folders Endpoints](#folders-endpoints)
   - [List accessible folders](#list-folders)
   - [Create a folder](#create-folder)
5. [Error Handling](#error-handling)
6. [Best Practices](#best-practices)
7. [Complete Workflow Example](#example-workflow)

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
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

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
  "item_id": 123
}
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `otp_code` | string | 6-digit TOTP code |
| `expires_in` | integer | Seconds until code expires |
| `item_id` | integer | Item ID |

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
  "icon": "fa-solid fa-key"
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
| `tags` | string | ❌ | Comma-separated tags |
| `anyone_can_modify` | integer | ❌ | Anyone can modify (0/1, default: 0) |
| `icon` | string | ❌ | FontAwesome icon code |

**Response (success):**
```json
{
  "error": false,
  "message": "Item created successfully",
  "newId": "658"
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
| `label` | string | ❌ | New label |
| `password` | string | ❌ | New password |
| `description` | string | ❌ | New description |
| `login` | string | ❌ | New login identifier |
| `email` | string | ❌ | New email address |
| `url` | string | ❌ | New URL |
| `tags` | string | ❌ | New tags (comma-separated) |
| `anyone_can_modify` | integer | ❌ | Anyone can modify (0/1) |
| `icon` | string | ❌ | New FontAwesome icon code |
| `folder_id` | integer | ❌ | Move to new folder |
| `totp` | string | ❌ | TOTP/OTP secret |

> ⚠️ **Important**: At least one field to update must be provided in addition to the ID.

**Response (success):**
```json
{
  "error": false,
  "message": "Item updated successfully",
  "item_id": "123"
}
```

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | Item updated successfully |
| 400 | Missing ID or no fields to update |
| 401 | Invalid session or user keys not found |
| 403 | Update permission denied or access denied |
| 404 | Item not found |
| 422 | HTTP method not supported |
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
| 403 | Delete permission denied or access denied |
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

**Response (success):**
```json
[
  {
    "id": 1,
    "title": "Production",
    "parent_id": 0,
    "nlevel": 0,
    "personal_folder": 0
  },
  {
    "id": 2,
    "title": "Servers",
    "parent_id": 1,
    "nlevel": 1,
    "personal_folder": 0
  }
]
```

**Response Fields:**

| Field | Type | Description |
| ----- | ---- | ----------- |
| `id` | integer | Unique folder ID |
| `title` | string | Folder name |
| `parent_id` | integer | Parent folder ID (0 for root) |
| `nlevel` | integer | Depth level in tree |
| `personal_folder` | integer | Personal folder (0/1) |

**Response Codes:**

| Code | Description |
| ---- | ----------- |
| 200 | List returned successfully |
| 401 | Invalid or expired token |
| 403 | Access denied |

**Example:**
```bash
curl -X GET "https://your-teampass.com/api/index.php/folder/listFolders" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
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
| `parent_id` | integer | ✅ | Parent folder ID (0 for root if authorized) |
| `complexity` | integer | ❌ | Complexity level: 0 (Weak), 20 (Medium), 38 (Strong), 48 (Heavy), 60 (Very heavy) |
| `duration` | integer | ❌ | Expiration delay in minutes (0 = no expiration) |
| `create_auth_without` | integer | ❌ | Allow creation even if complexity insufficient (0/1) |
| `edit_auth_without` | integer | ❌ | Allow update even if complexity insufficient (0/1) |
| `icon` | string | ❌ | FontAwesome icon code (closed state) |
| `icon_selected` | string | ❌ | FontAwesome icon code (open/selected state) |
| `access_rights` | string | ❌ | Access type: R (Read), W (Write), ND (No deletion), NE (No edit), NDNE (No deletion and No edit) |

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
| 200 | Folder created successfully |
| 400 | Missing or invalid parameters |
| 401 | Invalid token or expired session |
| 403 | Create permission denied |
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
