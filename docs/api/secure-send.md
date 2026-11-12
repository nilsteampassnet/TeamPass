<!-- docs/api/secure-send.md -->

# Secure Send through the API

API clients (the browser extension, a paired mobile device, a script) can create
**Secure Send** links: limited-view, expiring links to a copy of an item, optionally
protected by a passphrase and optionally sent by email. The recipient opens the link
in a browser and needs no TeamPass account.

A link created through the API is the same link as one created from the web interface. The
same administrator policy, item permissions, encryption, audit journal and email delivery
apply. The creation is recorded as a `created` event in the Secure Send audit journal, and the
sender can revoke the link from **My secure sends** in the web interface.

This version covers **item sends** only. Notes, the link inventory and revocation are not
available through the API yet.

## Prerequisites (administrator)

| Setting | Where | Why |
| ------- | ----- | --- |
| **API access enabled** | Settings → API | The API itself |
| API access and the **read** right for the user | Settings → API → *Users* tab | A link discloses what `item/get` returns to the same user |
| **User can propose One-Time-View links** (`otv_is_enabled`) | Options → Collaborative & advanced features → Secure sharing | The Secure Send engine |
| **Allow Secure Send from API clients** (`secure_send_api_enabled`) | Settings → API, main tab | Explicit opt-in for API clients. Off by default on new installations and after an upgrade |
| **Allow Secure Send email invitations** + a configured email transport | Options → … → Secure sharing, and Emails | Only for `recipient_email`. The sharing address must be HTTPS |

Enabling Secure Send on the web never enables it for API clients: paired phones and personal
access tokens make link creation reachable outside a web session, so the second switch is
required.

Administrator accounts cannot send items, through the web or the API: they have no item access.

## Get the policy {#policy}

> 📋 Returns the limits and options the client needs to build its form

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `secureSend/policy` |
| **Method** | GET |
| **URL** | `<Teampass URL>/api/index.php/secureSend/policy` |
| **Headers** | `Authorization: Bearer <token>` |

Always answers `200`, so that a client can hide the action instead of showing an error. Call it
after each authentication and when the user opens the Secure Send form.

**Response:**
```json
{
  "enabled": true,
  "item_send_allowed": true,
  "require_passphrase": false,
  "passphrase_max_bytes": 1024,
  "days": { "max": 7, "default": 7 },
  "views": { "max": 5, "default": 1 },
  "public_address_available": true,
  "totp_allowed": true,
  "email": { "allowed": true, "configured": true },
  "sender_name_shown": true
}
```

| Field | Type | Description |
| ----- | ---- | ----------- |
| `enabled` | boolean | One-time view links **and** *Allow Secure Send from API clients* are on |
| `item_send_allowed` | boolean | `enabled`, and the user is not an administrator. Hide the action when `false` |
| `require_passphrase` | boolean | Every link needs a passphrase |
| `passphrase_max_bytes` | integer | Maximum passphrase length, in bytes (UTF-8) |
| `days.max` / `days.default` | integer | Validity in days. Without `days`, a link gets the maximum |
| `views.max` / `views.default` | integer | Allowed views. Without `views`, a link gets one view |
| `public_address_available` | boolean | A public sharing address is configured, so `public_address` can be requested |
| `totp_allowed` | boolean | `false` for read-only accounts: their links never include the TOTP |
| `email.allowed` / `email.configured` | boolean | Show the recipient field only when both are `true` |
| `sender_name_shown` | boolean | The recipient sees the sender's name |

Reading the policy does not count as user activity.

## Create a link {#create}

> 📋 Creates a Secure Send link for an item the user can read

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `secureSend/create` |
| **Method** | POST, JSON body only |
| **URL** | `<Teampass URL>/api/index.php/secureSend/create` |
| **Headers** | `Authorization: Bearer <token>`, `Content-Type: application/json` |

**Body:**
```json
{
  "item_id": 123,
  "days": 3,
  "views": 1,
  "passphrase": "correct horse battery",
  "include_totp": false,
  "public_address": false,
  "recipient_email": "alice@example.com"
}
```

| Field | Type | Description |
| ----- | ---- | ----------- |
| `item_id` | integer | **Required.** The item to share |
| `days` | integer ≥ 1 | Validity. A value above `days.max` is lowered to it |
| `views` | integer ≥ 1 | Allowed views. A value above `views.max` is lowered to it |
| `passphrase` | string | The recipient must type it. Required when `require_passphrase` is `true` |
| `include_totp` | boolean | Copies the item's TOTP into the link. Ignored for read-only accounts |
| `public_address` | boolean | Builds the link on the public sharing address |
| `recipient_email` | string | Also sends the link by email from the instance mailbox. The passphrase is never in the email |

Only `item_id` is required. A `null` member counts as omitted. Unknown members are refused, and
so are parameters in the URL.

**Response `201`:**
```json
{
  "id": 456,
  "url": "https://vault.example.com/index.php?otv=1&code=…&key=…&stamp=…",
  "item_id": 123,
  "expires_at": 1795000000,
  "max_views": 1,
  "has_passphrase": true,
  "public_address": false,
  "totp_included": false,
  "description_truncated": false,
  "email_queued": true
}
```

| Field | Type | Description |
| ----- | ---- | ----------- |
| `id` | integer | Link identifier |
| `url` | string | The link. **It is a secret**: see [Client rules](#client-rules) |
| `expires_at` | integer | Unix timestamp. The effective value, after the policy limit |
| `max_views` | integer | The effective number of views |
| `has_passphrase` | boolean | The recipient will be asked for the passphrase |
| `totp_included` | boolean | The link shows the current TOTP code |
| `description_truncated` | boolean | The item description was shortened to fit the link |
| `email_queued` | boolean | The invitation was queued. Delivery happens in the background |

The link holds a copy of the item at creation time: later changes to the item do not reach it.
Losing access to the item, or deleting it, makes the link unusable.

**Example:**
```bash
curl -X POST "https://your-teampass.com/api/index.php/secureSend/create" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"item_id": 123, "days": 1, "views": 1, "passphrase": "correct horse battery"}'
```

### Errors {#errors}

Errors use the `application/problem+json` format of the API, with one more member, `reason`:
a stable code to translate in the client. Do not display or parse `detail`, which may change.

```json
{
  "type": "about:blank",
  "title": "Unprocessable Entity",
  "status": 422,
  "detail": "A passphrase is required by the administrator policy",
  "error": "A passphrase is required by the administrator policy",
  "reason": "passphrase_required"
}
```

| HTTP | `reason` | Meaning |
| ---- | -------- | ------- |
| 400 | `invalid_payload` | Invalid body or type, unknown member, parameters in the URL, `Idempotency-Key` header, passphrase too long |
| 400 | `invalid_email` | `recipient_email` is not one valid address |
| 403 | `not_allowed` | The user cannot read the item, is an administrator, or is disabled |
| 403 | `email_not_allowed` | Email invitations are disabled |
| 404 | `not_found` | The item does not exist or was deleted |
| 422 | `passphrase_required` | The policy requires a passphrase |
| 422 | `cannot_decrypt` | The user's keys cannot open the item **yet**. Retry later: the background task is still distributing the keys of a recently created or changed item |
| 422 | `totp_unusable` | The item's TOTP cannot be shared |
| 422 | `email_requires_https` | Email invitations need an HTTPS sharing address |
| 429 | `email_rate_limited` | Ten email invitations per user per rolling hour. No `Retry-After` |
| 500 | `server_error` | Internal error, logged on the server |
| 503 | `secure_send_disabled` | One-time view links or *Allow Secure Send from API clients* is off |
| 503 | `email_not_configured` | No email transport is configured |
| 503 | `invalid_public_url` | The public sharing address is misconfigured |

A response **without** `reason` comes from the API itself, not from Secure Send: `401` (expired
token or keys unavailable), `403` (read right missing), `405` (method), `429` with `Retry-After`
(API rate limit), `503` (API disabled).

## Client rules {#client-rules}

These rules apply to every client: the browser extension, the mobile application and scripts.

- **The URL is a bearer secret.** Anyone who holds it, and the passphrase when there is one,
  can open the link. Never log it, including in HTTP diagnostics, crash reports and analytics.
  The server answers with `Cache-Control: no-store`.
- **Copy it as a sensitive value.** Clear the clipboard after a short delay. On Android, show it
  on a screen with `FLAG_SECURE` and flag the clip with `ClipDescription.EXTRA_IS_SENSITIVE`
  (Android 13 and later). The system share sheet hands the secret to the chosen app: say so to
  the user.
- **Never send the passphrase with the link.** Remind the user to send it through another
  channel: a telephone call or another messaging application, not a second message in the same
  thread.
- **Do not retry a create whose response was lost.** The endpoint has no idempotency key, on
  purpose: replaying a response would require the server to store the link. A lost response
  leaves at most one unused link, which expires on its own. Tell the user that a link may exist
  and that it can be revoked from **My secure sends** in the web interface.
- **Translate `reason`, not `detail`.** Treat `cannot_decrypt` as temporary.
- **Hide the action** when `enabled` or `item_send_allowed` is `false`, the TOTP option when
  `totp_allowed` is `false` or the item has no TOTP, the public address option when
  `public_address_available` is `false`, and the email field unless `email.allowed` and
  `email.configured` are both `true`.
- **Respect the bounds** of `days`, `views` and `passphrase_max_bytes` in the form. The server
  applies them anyway and returns the effective values.

Device tokens from [device pairing](device-pairing.md) work like any other token: no extra scope
is needed.

## See also

- [API documentation](api-basic.md)
- [Secure Send deployment and hardening](../install/secure-send.md)
- [Items: Secure Send](../features/items.md#secure-send)
- OpenAPI contract: `GET /api/v1/openapi.json`
