<!-- docs/api/secure-send.md -->

# Secure Send through the API

API clients (the browser extension, a paired mobile device, a script) can create
**Secure Send** links: limited-view, expiring links to a copy of an item or to a note,
optionally protected by a passphrase and optionally sent by email. The recipient opens the
link in a browser and needs no TeamPass account. The sender can revoke a link at any time.

A link created through the API is the same link as one created from the web interface. The
same administrator policy, item permissions, encryption, audit journal and email delivery
apply. The creation is recorded as a `created` event in the Secure Send audit journal, a
revocation as a `revoked` event. A link can be revoked from the API or from **My secure sends**
in the web interface, whichever channel created it.

The link inventory is not available through the API yet: a client can revoke the links whose
`id` it kept from the creation response.

## Prerequisites (administrator)

| Setting | Where | Why |
| ------- | ----- | --- |
| **API access enabled** | Settings → API | The API itself |
| API access and the **read** right for the user | Settings → API → *Users* tab | A link discloses what `item/get` returns to the same user |
| **User can propose One-Time-View links** (`otv_is_enabled`) | Options → Collaborative & advanced features → Secure sharing | The Secure Send engine |
| **Allow Secure Send from API clients** (`secure_send_api_enabled`) | Settings → API, main tab | Explicit opt-in for API clients. Off by default on new installations and after an upgrade |
| **Allow Secure Send email invitations** + a configured email transport | Options → … → Secure sharing, and Emails | Only for `recipient_email`. The sharing address must be HTTPS |
| **Allow sending ad-hoc notes/secrets** (`secure_send_allow_notes`) | Options → … → Secure sharing | Only for note sends |

Enabling Secure Send on the web never enables it for API clients: paired phones and personal
access tokens make link creation reachable outside a web session, so the second switch is
required.

Administrator accounts cannot send items, through the web or the API: they have no item access.
They can send notes, like in the web interface.

Revoking a link needs only the **read** right, and works whatever these settings: removing a
link only reduces exposure.

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
  "note_send_allowed": true,
  "require_passphrase": false,
  "passphrase_max_bytes": 1024,
  "note_max_bytes": 16000,
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
| `item_send_allowed` | boolean | `enabled`, and the user is not an administrator. Hide the item action when `false` |
| `note_send_allowed` | boolean | `enabled`, and note sends are allowed. Hide the note action when `false` |
| `require_passphrase` | boolean | Every link needs a passphrase |
| `passphrase_max_bytes` | integer | Maximum passphrase length, in bytes (UTF-8) |
| `note_max_bytes` | integer | Maximum size of a note: the five members together, in bytes (UTF-8) |
| `days.max` / `days.default` | integer | Validity in days. Without `days`, a link gets the maximum |
| `views.max` / `views.default` | integer | Allowed views. Without `views`, a link gets one view |
| `public_address_available` | boolean | A public sharing address is configured, so `public_address` can be requested |
| `totp_allowed` | boolean | `false` for read-only accounts: their links never include the TOTP |
| `email.allowed` / `email.configured` | boolean | Show the recipient field only when both are `true` |
| `sender_name_shown` | boolean | The recipient sees the sender's name |

Reading the policy does not count as user activity.

## Create a link {#create}

> 📋 Creates a Secure Send link for an item the user can read, or for a note

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `secureSend/create` |
| **Method** | POST, JSON body only |
| **URL** | `<Teampass URL>/api/index.php/secureSend/create` |
| **Headers** | `Authorization: Bearer <token>`, `Content-Type: application/json` |

**Body of an item send:**
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
| `type` | string | `"item"` (default) or `"note"` |
| `item_id` | integer | **Required for an item send.** The item to share |
| `note` | object | **Required for a note send.** See [Note sends](#note) |
| `days` | integer ≥ 1 | Validity. A value above `days.max` is lowered to it |
| `views` | integer ≥ 1 | Allowed views. A value above `views.max` is lowered to it |
| `passphrase` | string | The recipient must type it. Required when `require_passphrase` is `true` |
| `include_totp` | boolean | Item sends only. Copies the item's TOTP into the link. Ignored for read-only accounts |
| `public_address` | boolean | Builds the link on the public sharing address |
| `recipient_email` | string | Also sends the link by email from the instance mailbox. The passphrase is never in the email |

Only `item_id`, or `note` for a note send, is required. A `null` member counts as omitted.
Unknown members are refused, and so are parameters in the URL.

### Note sends {#note}

A note send shares content typed by the user, not an item: a password to give to a contractor,
a Wi-Fi key, a recovery code. It needs `note_send_allowed`.

```json
{
  "type": "note",
  "note": {
    "title": "Guest Wi-Fi",
    "secret": "correct horse battery staple",
    "note": "Valid until Friday",
    "login": "guest",
    "url": "https://wifi.example.com"
  },
  "days": 1,
  "views": 1,
  "passphrase": "blue door"
}
```

| `note` member | Shown to the recipient as |
| ------------- | ------------------------- |
| `title` | The label |
| `secret` | The password |
| `note` | The description, with its line breaks |
| `login` | The login |
| `url` | The URL |

Every member is an optional string, but `secret` or `note` must contain more than spaces. The
five members together hold at most `note_max_bytes` bytes. `title`, `login` and `url` are
trimmed. Other members are refused. A note send cannot carry `item_id` or `include_totp`, and
an item send cannot carry `note`.

The note is encrypted for the link only: it is not saved in the vault, and it disappears with
the link.

**Response `201`:**
```json
{
  "id": 456,
  "type": "item",
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
| `id` | integer | Link identifier. Keep it to [revoke](#revoke) the link |
| `type` | string | `"item"` or `"note"` |
| `url` | string | The link. **It is a secret**: see [Client rules](#client-rules) |
| `item_id` | integer or null | The shared item, `null` for a note |
| `expires_at` | integer | Unix timestamp. The effective value, after the policy limit |
| `max_views` | integer | The effective number of views |
| `has_passphrase` | boolean | The recipient will be asked for the passphrase |
| `totp_included` | boolean | The link shows the current TOTP code. Always `false` for a note |
| `description_truncated` | boolean | The item description was shortened to fit the link. Always `false` for a note |
| `email_queued` | boolean | The invitation was queued. Delivery happens in the background |

The link holds a copy of the item at creation time: later changes to the item do not reach it.
Losing access to the item, or deleting it, makes the link unusable. A note link becomes
unusable when its sender's account is disabled or deleted, and cannot be opened while an
administrator keeps note sends off.

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
| 400 | `invalid_payload` | Invalid body or type, unknown member, member of the other send type, note above `note_max_bytes`, parameters in the URL, `Idempotency-Key` header, passphrase too long |
| 400 | `invalid_email` | `recipient_email` is not one valid address |
| 400 | `empty_note` | The note has neither a `secret` nor a `note` |
| 403 | `not_allowed` | The user cannot read the item, is an administrator, or is disabled |
| 403 | `notes_not_allowed` | Note sends are disabled |
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

## Revoke a link {#revoke}

> 📋 Deletes one of the user's links: it can no longer be opened

| Info | Description |
| ---- | ----------- |
| **Endpoint** | `secureSend/revoke` |
| **Method** | DELETE |
| **URL** | `<Teampass URL>/api/index.php/secureSend/revoke?id=456` |
| **Headers** | `Authorization: Bearer <token>` |

| Parameter | Type | Description |
| --------- | ---- | ----------- |
| `id` | integer | **Required**, in the URL. The `id` returned by the creation. No other parameter is accepted |

Revokes a link the user created, from the API or from the web interface. The link is deleted
at once and a `revoked` event is recorded in the audit journal. An email invitation still
waiting in the queue is not sent.

**Response `200`:**
```json
{ "id": 456 }
```

| HTTP | `reason` | Meaning |
| ---- | -------- | ------- |
| 400 | `invalid_payload` | Missing or invalid `id`, another parameter, `Idempotency-Key` header |
| 404 | `not_found` | The user has no link with this `id`: it does not exist, has expired, was already revoked, or belongs to someone else |
| 500 | `server_error` | Internal error, logged on the server. The link was **not** revoked |

The answer is the same for a link that does not exist and for a link of another user, so link
identifiers cannot be probed. After a lost response, send the request again: `404` then means
the link is gone.

**Example:**
```bash
curl -X DELETE "https://your-teampass.com/api/index.php/secureSend/revoke?id=456" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Client rules {#client-rules}

These rules apply to every client: the browser extension, the mobile application and scripts.

- **The URL is a bearer secret.** Anyone who holds it, and the passphrase when there is one,
  can open the link. Never log it, including in HTTP diagnostics, crash reports and analytics.
  The server answers with `Cache-Control: no-store`.
- **Copy it as a sensitive value.** Clear the clipboard after a short delay. On Android, show it
  on a screen with `FLAG_SECURE` and flag the clip with `ClipDescription.EXTRA_IS_SENSITIVE`
  (Android 13 and later). The system share sheet hands the secret to the chosen app: say so to
  the user.
- **A note is a secret too.** Never log the body of a note send, and clear the note form once
  the link is created.
- **Never send the passphrase with the link.** Remind the user to send it through another
  channel: a telephone call or another messaging application, not a second message in the same
  thread.
- **Do not retry a create whose response was lost.** The endpoint has no idempotency key, on
  purpose: replaying a response would require the server to store the link. A lost response
  leaves at most one unused link, which expires on its own. Tell the user that a link may exist
  and that it can be revoked from **My secure sends** in the web interface. A revocation, on
  the contrary, can be sent again: see [Revoke a link](#revoke).
- **Offer to revoke** the links the client created: keep their `id` and `expires_at`, never
  their `url`.
- **Translate `reason`, not `detail`.** Treat `cannot_decrypt` as temporary.
- **Hide the actions** when `enabled` is `false`, the item action when `item_send_allowed` is
  `false` and the note action when `note_send_allowed` is `false`. Hide the TOTP option when
  `totp_allowed` is `false` or the item has no TOTP, the public address option when
  `public_address_available` is `false`, and the email field unless `email.allowed` and
  `email.configured` are both `true`.
- **Respect the bounds** of `days`, `views`, `passphrase_max_bytes` and `note_max_bytes` in the
  form. The server applies them anyway and returns the effective values.

Device tokens from [device pairing](device-pairing.md) work like any other token: no extra scope
is needed.

## See also

- [API documentation](api-basic.md)
- [Secure Send deployment and hardening](../install/secure-send.md)
- [Items: Secure Send](../features/items.md#secure-send)
- OpenAPI contract: `GET /api/v1/openapi.json`
