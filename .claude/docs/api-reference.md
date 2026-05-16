# API Reference

## API Architecture

**Entry point:** `/api/index.php`

**Authentication:** JWT tokens (generated via `/api/authorize`)
```
Authorization: Bearer <jwt_token>

Token payload:
- User ID, username
- Allowed folders (comma-separated IDs)
- CRUD permissions (allowed_to_create, allowed_to_read, etc.)
- Session key (for server-side validation)
```

**Controllers:** `/api/Controller/Api/`
- `AuthController.php` - JWT generation
- `ItemController.php` - Item CRUD operations
- `FolderController.php` - Folder listing
- `UserController.php` - User operations

**Common Pattern:**
```php
class ItemController extends BaseController {
    public function getAction(array $userData): void {
        // 1. Validate HTTP method
        // 2. Check permissions from JWT
        // 3. Retrieve user's private key from DB
        // 4. Query items
        // 5. Decrypt items using sharekeys
        // 6. Return JSON response
    }
}
```

**Key Endpoints:**
- `POST /api/authorize` - Get JWT token
- `GET /api/item/get?id=123` - Get single item
- `GET /api/item/inFolders?folders=[1,2,3]` - Items in folders
- `POST /api/item/create` - Create item
- `GET /api/item/getOtp?id=123` - Get current TOTP/MFA code
- `GET /api/folder/listFolders` - List accessible folders

---

## OTP/TOTP Endpoint

**Endpoint:** `GET /api/item/getOtp`

**Parameters:** `id` (required) — item ID

**Auth:** JWT with `allowed_to_read`, user must have folder access

**Response (200 OK):**
```json
{ "otp_code": "123456", "expires_in": 25, "item_id": 123 }
```

**Error Responses:**

| Status | Error |
|---|---|
| 400 | `"Item id is mandatory"` |
| 403 | `"Access denied to this item"` / `"OTP is not enabled for this item"` |
| 404 | `"Item not found"` / `"OTP not configured for this item"` |
| 500 | `"Failed to decrypt OTP secret"` / `"Failed to generate OTP code: ..."` |

**Implementation:**
- OTP secrets stored encrypted in `items_otp` table
- Decrypted using application master key (Defuse Crypto)
- TOTP generated via OTPHP library — 6 digits, 30-second window
- Checks folder-level and item-level permissions
