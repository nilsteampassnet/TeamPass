<!-- docs/api/api-basic.md -->

> :warning: **Warning:** API are still in development.

## Progress

- [x] Global API structure
- [x] Authentication
- [x] Items - list with criteria
- [x] Items - get item info
- [x] Items - find by URL
- [x] Items - get OTP code
- [x] Items - create new
- [x] Folders - list accessible folders
- [x] Folders - create new
- [ ] Items - edit an item
- [ ] Items - delete an item


## Generalities

Teampass v3 comes with an API permitting several operations on items and folders.\
Its usage relies on a JWT token generated on demand.\
Queries via API are possible until this token is valid.\
API is by default disabled.

> The usage of the API requires <mark>a valid account and a valid API key</mark>.

## Define _LimitRequestFieldsize_ directive in Apache settings

Before starting using Teampass API, it is requested to change the default value _LimitRequestFieldsize_ directive in Apache settings.\
This directive defines the limit on the allowed size of an HTTP request-header field below the normal input buffer size compiled with the server.

> Set `LimitRequestFieldSize 200000` in _apache2.conf_ file.

## Setup API in Teampass

Once enabled, the default auth token is set for a duration of 60 seconds. You can adapt this value to your needs.

You need to create an API key.

> :bulb: **Tip:** Provide a label for each key so that you know in what context it is used.


## API usage

The base API url is: `<Teampass url>/api/index.php/<action criteria>`

### Authorize

> :memo: **Note:** Returns the JWT token requested for next API queries

| Info | Description |
| ---- | ----------- |
| Criteria | authorize |
| Type | POST |
| URL | `<Teampass url>/api/index.php/authorize` |
| BODY | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"apikey": "_generated api key in Teampass_",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "_teampass user login_",<br>&nbsp;&nbsp;&nbsp;&nbsp;"password": "_user password_"<br>} |
| Return | A token valid for a specific duration.<br>Return format is:<br>{<br>&nbsp;&nbsp;&nbsp;&nbsp;"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."<br>} |

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

## Items endpoints

### List items in folders

> :memo: **Note:** Returns a list of items belonging to the provided folders (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| Criteria | item/inFolders |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/inFolders?folders=[590,12]` |
| PARAMETERS | folders=[<folder_id>,<folder_id>] |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of items in json format.<br>Example:<br>[<br>&nbsp;&nbsp;{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 1027,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "Teampass production",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "Use for administration",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "Ajdh-652Syw-625sWW-Ca18",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "https://teampass.net",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "tpAdmin",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "nils@teampass.net",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 54,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": null,<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0<br>&nbsp;&nbsp;}<br>] |

**Example:**

```bash
curl -X GET "https://your-teampass.com/api/index.php/item/inFolders?folders=[1,2,3]" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Get item data by ID

> :memo: **Note:** Returns the item definition based upon its ID (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| Criteria | item/get |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/get?id=2052` |
| PARAMETERS | id=<item_id> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 2053,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "new object for #3500 v3",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "<p>bla bla</p>",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "SK^dsf123s_6A}]V$t^]",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "Me",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 2,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 670,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "MACHINES",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": "issue3317>issue 3325>ITI 2>PROD"<br>} |

**Example:**

```bash
curl -X GET "https://your-teampass.com/api/index.php/item/get?id=123" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Get item data by LABEL

> :memo: **Note:** Returns an item list definition based upon its LABEL (taking into account the user access rights)

This query accepts an optional parameter called `like` that permits to perform a search on the field `label`.\
If `like=1` then you can add in parameter `label` the symbol `%` to refine the search.

Example:
* `label="%some text"` will search for all labels finishing by `some text`.
* `label="%some text%"` will search for all labels containing `some text`.
* `label="some text%"` will search for all labels starting by `some text`.

| Info | Description |
| ---- | ----------- |
| Criteria | item/get |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/get?label="some text"&like=0` |
| PARAMETERS | label="some text"&like=<0 or 1> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 21,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "bug 1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "Voici un é1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 13,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "F1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": ""<br>&nbsp;&nbsp;&nbsp;&nbsp;},<br>&nbsp;&nbsp;&nbsp;{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 22,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "bug 1 - 1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "EwS5jc+S}Y6x",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 4,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "F1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": ""<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |

**Example:**

```bash
curl -X GET "https://your-teampass.com/api/index.php/item/get?label=%25production%25&like=1" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Get item data by DESCRIPTION

> :memo: **Note:** Returns an item list definition based upon its DESCRIPTION (taking into account the user access rights)

This query accepts an optional parameter called `like` that permits to perform a search on the field `description`.\
If `like=1` then you can add in parameter `description` the symbol `%` to refine the search.

| Info | Description |
| ---- | ----------- |
| Criteria | item/get |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/get?description="some text"&like=0` |
| PARAMETERS | description="some text"&like=<0 or 1> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 21,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "bug 1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "some text",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "Voici un é1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 13,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "F1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": ""<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |

**Example:**

```bash
curl -X GET "https://your-teampass.com/api/index.php/item/get?description=%25server%25&like=1" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Find items by URL

> :memo: **Note:** Find any item using its URL (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| Criteria | item/findByUrl |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/findByUrl?url=https://example.com` |
| PARAMETERS | url=<url_to_search> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 123,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "Example Login",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "user@example.com",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "https://example.com",<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_id": 5,<br>&nbsp;&nbsp;&nbsp;&nbsp;"has_otp": 1<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |

**Example:**

```bash
curl -X GET "https://your-teampass.com/api/index.php/item/findByUrl?url=https://example.com" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Get OTP code for an item

> :memo: **Note:** Returns the current TOTP (Time-based One-Time Password) code for an item with OTP enabled

| Info | Description |
| ---- | ----------- |
| Criteria | item/getOtp |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/getOtp?id=123` |
| PARAMETERS | id=<item_id> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An object with OTP information in json format.<br>Example:<br>{<br>&nbsp;&nbsp;&nbsp;&nbsp;"otp_code": "123456",<br>&nbsp;&nbsp;&nbsp;&nbsp;"expires_in": 25,<br>&nbsp;&nbsp;&nbsp;&nbsp;"item_id": 123<br>} |

**Response Fields:**
- `otp_code`: The current 6-digit TOTP code
- `expires_in`: Number of seconds until the code expires
- `item_id`: The ID of the item

**Error Responses:**

- `400 Bad Request`: Item ID is missing
- `403 Forbidden`: Access denied or OTP not enabled for this item
- `404 Not Found`: Item or OTP configuration not found
- `500 Internal Server Error`: Decryption or generation failure

**Example:**

```bash
curl -X GET "https://your-teampass.com/api/index.php/item/getOtp?id=123" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Add a new item

> :memo: **Note:** Creates a new item based upon provided parameters

**Warning:**
* User must have create permission
* `folder_id` must be a valid folder ID that the user has access to
* All required fields must be provided

| Info | Description |
| ---- | ----------- |
| Criteria | item/create |
| Type | POST |
| URL | `<Teampass url>/api/index.php/item/create` |
| PARAMETERS | label=<item_label><br>folder_id=<folder_id><br>password=<password><br>description=<description><br>login=<login><br>email=<email><br>url=<url><br>tags=<tag1,tag2><br>anyone_can_modify=<0 or 1><br>icon=<fontawesome_icon> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An object with creation result in json format.<br>Example:<br>{<br>&nbsp;&nbsp;&nbsp;&nbsp;"error": false,<br>&nbsp;&nbsp;&nbsp;&nbsp;"message": "Item created successfully",<br>&nbsp;&nbsp;&nbsp;&nbsp;"newId": "658"<br>} |

**Example:**

```bash
curl -X POST "https://your-teampass.com/api/index.php/item/create?label=My%20Item&folder_id=5&password=SecureP@ss123&description=Test%20item&login=user&email=user@example.com&url=https://example.com&tags=api,test&anyone_can_modify=0&icon=fa-solid%20fa-key" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Folders endpoints

### List accessible folders

> :memo: **Note:** Returns the list of folders accessible to the authenticated user

| Info | Description |
| ---- | ----------- |
| Criteria | folder/listFolders |
| Type | GET |
| URL | `<Teampass url>/api/index.php/folder/listFolders` |
| PARAMETERS | None |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of folder objects in json format.<br>Example:<br>[<br>&nbsp;&nbsp;{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"title": "Production",<br>&nbsp;&nbsp;&nbsp;&nbsp;"parent_id": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"nlevel": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"personal_folder": 0<br>&nbsp;&nbsp;},<br>&nbsp;&nbsp;{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 2,<br>&nbsp;&nbsp;&nbsp;&nbsp;"title": "Servers",<br>&nbsp;&nbsp;&nbsp;&nbsp;"parent_id": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"nlevel": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"personal_folder": 0<br>&nbsp;&nbsp;}<br>] |

**Example:**

```bash
curl -X GET "https://your-teampass.com/api/index.php/folder/listFolders" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Add a new folder

> :memo: **Note:** Creates a new folder based upon provided parameters

**Warning:**
* User must have create permission
* `parent_id` must be valid (or 0 for root level if user has permission)
* `complexity` must be one of the values: 0 (Weak) ; 20 (Medium) ; 38 (Strong) ; 48 (Heavy) ; 60 (Very heavy)
* `access_rights` must be one of the values: R (Read) ; W (Write) ; ND (No deletion) ; NE (No edit) ; NDNE (No deletion and No edit)

| Info | Description |
| ---- | ----------- |
| Criteria | folder/create |
| Type | POST |
| URL | `<Teampass url>/api/index.php/folder/create` |
| PARAMETERS | title=<folder_title><br>parent_id=<parent_folder_id><br>complexity=<0, 20, 38, 48, or 60><br>duration=<expiration_delay_in_minutes><br>create_auth_without=<0 or 1><br>edit_auth_without=<0 or 1><br>icon=<fontawesome_icon><br>icon_selected=<fontawesome_icon_selected><br>access_rights=<R, W, ND, NE, or NDNE> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An object with creation result in json format.<br>Example:<br>{<br>&nbsp;&nbsp;&nbsp;&nbsp;"error": false,<br>&nbsp;&nbsp;&nbsp;&nbsp;"message": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"newId": "148"<br>} |

**Example:**

```bash
curl -X POST "https://your-teampass.com/api/index.php/folder/create?title=New%20Folder&parent_id=1&complexity=38&duration=0&create_auth_without=0&edit_auth_without=0&icon=fa-folder&icon_selected=fa-folder-open&access_rights=W" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Error Handling

All API endpoints may return the following standard error responses:

| HTTP Status | Description |
| ----------- | ----------- |
| 400 Bad Request | Missing or invalid parameters |
| 401 Unauthorized | Invalid or expired JWT token, or insufficient permissions |
| 403 Forbidden | User doesn't have permission to perform the action |
| 404 Not Found | Resource not found or API is disabled |
| 422 Unprocessable Entity | HTTP method not supported |
| 500 Internal Server Error | Server-side error occurred |

Error responses are returned in JSON format:

```json
{
  "error": "Error description message"
}
```

## Best Practices

1. **Token Management**: Store the JWT token securely and refresh it before expiration
2. **Error Handling**: Always check for error responses and handle them appropriately
3. **URL Encoding**: Ensure all parameters are properly URL-encoded, especially special characters
4. **Rate Limiting**: Be mindful of request frequency to avoid overloading the server
5. **Security**: Never commit API keys to version control or share them in plain text
6. **Permissions**: Verify that the user account has the necessary permissions for the intended operations

## Example Workflow

Here's a complete example workflow demonstrating how to authenticate and retrieve items:

```bash
# 1. Authenticate and get JWT token
TOKEN=$(curl -X POST "https://your-teampass.com/api/index.php/authorize" \
  -H "Content-Type: application/json" \
  -d '{
    "apikey": "your-api-key",
    "login": "username",
    "password": "password"
  }' | jq -r '.token')

# 2. List accessible folders
curl -X GET "https://your-teampass.com/api/index.php/folder/listFolders" \
  -H "Authorization: Bearer $TOKEN"

# 3. Get items from specific folders
curl -X GET "https://your-teampass.com/api/index.php/item/inFolders?folders=[1,2,3]" \
  -H "Authorization: Bearer $TOKEN"

# 4. Search for items by URL
curl -X GET "https://your-teampass.com/api/index.php/item/findByUrl?url=https://example.com" \
  -H "Authorization: Bearer $TOKEN"

# 5. Get OTP code for an item
curl -X GET "https://your-teampass.com/api/index.php/item/getOtp?id=123" \
  -H "Authorization: Bearer $TOKEN"
```
