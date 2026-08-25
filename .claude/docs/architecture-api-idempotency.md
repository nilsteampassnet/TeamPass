# API Item Mutation Idempotency

> Target release: 3.2.2

TeamPass accepts an optional `Idempotency-Key` on `POST /item/create` and
`DELETE /item/delete`. It is intended for offline clients that may lose an HTTP response and must
retry without creating a second item or deleting a restored item again.

## Public contract

The header is an opaque value containing 1–128 visible ASCII characters (`0x21`–`0x7e`), without
spaces. A UUID is recommended but not required. Empty, non-ASCII, control-character and oversized
values return `400`.

An identity is the tuple `(authenticated user, operation, HMAC(key))`. Create and delete are
separate scopes, and the same raw key used by two users never collides.

For a valid key:

| State | Result |
|---|---|
| First request | Normal mutation: `201` for create, `200` for delete |
| Completed, same fingerprint | Stored status/body, plus `Idempotency-Replayed: true` |
| Same identity, different fingerprint | `409 Conflict`, no write |
| Another request owns the active processing lease | `409 Conflict` plus `Retry-After` |
| Expired processing lease | One request atomically takes ownership and resumes |

Without the header, no idempotency record is created and the legacy API behavior is retained.

Create fingerprints include the folder, label, login, password, email, URL, description, tags,
TOTP secret and parameters, custom fields, icon and `anyone_can_modify`. Delete fingerprints
include the item id and the optional expected revision. Associative keys are sorted recursively;
list order remains significant.

The fingerprint and key hash are HMAC-SHA-256 values derived from the TeamPass instance secret
with a domain-separated subkey. The table never stores or logs the raw key, JSON body, password,
TOTP secret or custom-field values. Stored response bodies contain only replay-safe identifiers,
revision metadata and public success messages.

## Persistence

`teampass_api_idempotency` contains:

- identity: `user_id`, `operation`, `key_hash` with a composite unique key;
- intent: `request_fingerprint`;
- ownership: `status`, `owner_token_hash`, `locked_until`;
- replay metadata: `resource_id`, `http_status`, `response_body`;
- lifecycle: `created_at`, `updated_at`, `expires_at`.

`teampass_items.api_idempotency_id` is a nullable unique link used only by keyed creates. It makes
a committed item discoverable from a stale reservation even if the PHP process died before it
could return the response. Historical and non-keyed items keep `NULL`.

## Transaction and crash behavior

Reservation uses a short, persistent five-minute processing lease. Syntax, global rights, basic
payload and folder rights are checked before create reservation. Delete rights are checked before
its reservation in the current controller flow. Model-level validation may still fail after
reservation; a failed or rolled-back request removes only the reservation owned by that request,
so a corrected retry is not blocked.

External favicon/DNS resolution is completed before the create transaction. The functional SQL
writes then share one transaction with idempotency completion:

- item row and the durable create link;
- item/sharekey/custom-field/tag/TOTP writes;
- audit and revision writes;
- cache, health and background-task rows;
- the queued WebSocket event;
- replay status and response.

Delete similarly locks the item row, evaluates the optional revision and LAPR guard, applies the
soft delete and its SQL side effects, then completes the replay record before commit.

This ordering covers the important failure windows:

- crash before mutation: the transaction has no functional write; the lease can later be taken;
- crash during mutation: InnoDB rolls back both functional writes and completion;
- commit followed by a lost HTTP response: the completed response is replayed;
- stale create row with a committed linked item: recovery reconstructs and finalizes the safe
  create response without running side effects;
- concurrent duplicate: the unique identity gives one owner; contenders receive processing or
  replay state.

The queued WebSocket event is a database row and therefore follows the transaction. Actual
delivery occurs later and is naturally outside it. External UDP syslog emission is deliberately
deferred until after commit; a replay never emits it again. There is therefore a narrow at-most-once
window where a process crash immediately after commit can omit that external datagram. The durable
TeamPass audit row remains in the SQL transaction.

TeamPass's audit/revision helpers remain best-effort for historical callers. These two
transactional API paths enable their strict mode and additionally verify that a new revision/date
pair was persisted; a failure therefore rolls back create/delete instead of completing an
idempotency record with a missing audit or tombstone.

A completed delete is replayed, never executed again. If the item is restored later, retrying the
old key cannot delete the restored state. If the item is eventually hard-purged or the caller no
longer passes the controller's current access checks, the endpoint may return `404`/`403` before
reaching its completed record; it still never repeats the mutation. Normal replay is guaranteed
while the item remains addressable and the record remains in its replay window.

## Cleanup

Completed records are kept for 90 days, matching the default offline synchronization window. The
existing orphan-maintenance task clears expired `items.api_idempotency_id` links and deletes the
corresponding records. A processing record is removed only when both its replay window and lease
have expired, so maintenance never deletes an active request.

After cleanup, reusing an old key is a new operation. Clients must therefore keep retryable
offline intents within the documented 90-day window, just as they must perform a full sync after
falling outside the revision window.

## Examples

First creation:

```bash
curl -i -X POST "https://teampass.example/api/index.php/item/create" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{"folder_id":3,"label":"Router","password":"secret","login":"admin","email":"","url":"","tags":"","anyone_can_modify":0}'
```

Repeating the same command returns the original `201`, `Location` and body, with:

```text
Idempotency-Replayed: true
```

Changing `label`, `password` or any other functional field while keeping the key returns `409`.

Conditional, idempotent deletion:

```bash
curl -i -X DELETE \
  "https://teampass.example/api/index.php/item/delete?id=123&revision=456" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: 8fe52f47-6f72-4a1a-b0d8-68a01c884d41"
```

A stale `revision` returns `409` before the delete or any of its side effects. Omitting `revision`
keeps the historical last-writer-wins behavior.
