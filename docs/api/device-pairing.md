# External device pairing (experimental, version 1)

Pairing transfers a dedicated Personal Access Token (PAT) after approval in the
user's existing web session. It does not transfer a password, API key, licence,
JWT, PHP cookie or user private key in a QR. This is a TeamPass protocol inspired
by PKCE's verifier binding, not an implementation of OAuth Device Authorization.

The feature is disabled by default. A compatible external client is required.
It does not configure offline access or change the account's API or vault rights.
For the Android implementation handoff, see the
[Android client guide](device-pairing-android.md). The current scope is Android;
an Android scanner and end-to-end prototype still need to be implemented and
validated before the experimental contract is frozen. No iOS support is claimed.

## Installation and policy

Run the normal upgrade wizard, including when upgrading an existing 3.2.3.x
installation. The script manager replays `upgrade_run_3.2.3.php`, which installs
the additive columns and shared `device_pairings` DDL and publishes schema
version 3 only after success. Fresh installs create the same tables. The feature
uses InnoDB transactions and row locks, as do its integration tests.
The upgrade verifies InnoDB on the account, credential, session, pairing,
rate-limit, settings and audit tables before publishing readiness. It refuses
nontransactional storage without silently converting existing tables.

Enable mobile app pairing in Settings > API > Mobile application. API access must be enabled
both globally and for the account. The pairing opt-in is its own policy for
locally authenticated, directory and SSO accounts with a usable web-session key.
It does not require or enable `oauth2_api_enabled` or
`extension_token_all_auth_types`; those retain their existing meaning for legacy
credentials. No setting is enabled automatically.
Disabling pairing blocks new claims, handoffs, device PAT authentication and the
JWTs issued from those credentials. The profile keeps device revocation available.
New credentials expire after the configured `device_pairing_token_lifetime_days`
(90 days by default, 1–3650 days). Changing this value affects new pairings only.
Existing experimental credentials without an expiry receive one migration grace
period of the configured duration; replaying the upgrade never extends it.

The canonical `cpassman_url` must use HTTPS, retaining its port and subdirectory.
TLS must be trusted by the client; private networks and private CAs are supported
when trust is installed correctly. Scheme headers count only from configured
trusted proxies. Never disable certificate validation or follow an inter-origin
redirect to complete pairing.
When TLS terminates at a reverse proxy (including Docker), select the reverse
proxy network security mode and configure the trusted proxy addresses. An
untrusted `X-Forwarded-Proto` header does not satisfy this requirement.

## Actors and routes

The profile uses POST actions `create`, `status`, `approve`, `reject`, `cancel`,
`list` and `revoke` at `sources/device_pairing.queries.php`. They require the
initiating user's valid session and synchronizer CSRF key. Approval also uses
the existing sign-in passkey step-up policy: password confirmation where
applicable or a recent sign-in for accounts whose password TeamPass cannot check.
There is no local password requirement invented for an SSO account.
Settings > API > Mobile application also provides an administrator-only device
table, searchable by account or device name across all paginated results.
The search matches displayed account names (including decoded HTML entities)
and literal device labels; percent and underscore are not wildcards.
Its POST actions `admin_list` and `admin_revoke` use the
same session/CSRF protections and fresh server-side administrator checks;
managers cannot manage another account's devices. Only labels, account names,
creation/last-use/expiry dates and IDs are returned. No credential material or
vault contents are selected. Revocation checks the supplied owner against the
credential, audits both acting administrator and target owner, and remains
available when API or pairing is disabled.

The last-use timestamp records successful PAT authentication, rather than every
JWT-authenticated request. It is informational; no inactivity timeout is applied.

The device routes below accept no JWT as a substitute for their pairing proof.
They enforce network ACLs and mandatory HTTPS; API enablement is also required
except for the possession-proven credential status check described below.

| Route under `/api/v1` | Method | Authorization |
| --- | --- | --- |
| `/devicePairing/capabilities` | GET | Public experimental capability only |
| `/devicePairing/claim` | POST | Invitation ID and scanned secret |
| `/devicePairing/status` | POST | Claim ID and its verifier |
| `/devicePairing/consume` | POST | Same verifier and precisely approved claim |
| `/devicePairing/credentialStatus` | POST | Exact device PAT; no JWT required |
| `/authorizeToken` | POST | Existing login/PAT authentication after delivery |

Device requests are JSON objects, at most 8 KiB. No query string is accepted.
Sensitive responses use `Cache-Control: no-store`. These routes do not advertise
open CORS access and do not rely on a web cookie for device authorization.
The proof-based credential status route remains available when the API or
pairing toggle is disabled, while still enforcing trusted HTTPS and network
ACLs. It accepts only `{ "token": "<64 lowercase hex characters>" }` in the body.

## QR and claim

The QR is a compact JSON document:

```json
{"type":"teampass.device-pairing","version":1,"server_url":"https://vault.example.test/team","pairing_id":"<32 lowercase hex characters>","pairing_secret":"<43 base64url characters>"}
```

The ID has 128 random bits. The secret has 256 random bits and is stored only
as SHA-256. Its lifetime is 180 seconds or the initiating session's remaining
lifetime, whichever is shorter. Polling does not extend it. A new QR cancels the
previous unfinished transaction for that same web session.

The client generates an ephemeral RSA-3072 key pair with exponent 65537, and a
fresh 256-bit verifier encoded as 43 base64url characters without padding:

`challenge = BASE64URL(SHA256(ASCII(verifier)))`

Claim body: `pairing_id`, `pairing_secret`, `challenge`, `public_key` (public PEM,
at most 2 KiB), `device_label` (1–128 UTF-8 characters, no control characters).
Private keys, different key sizes/exponents and malformed fields are refused.

One immutable claim is reserved atomically. An identical retry recovers it;
a competing claim returns 409 and cannot replace the installation displayed in
the browser. The response includes `claim_id`, `comparison_code` (six digits),
`key_fingerprint` and state metadata. The fingerprint is SHA-256 of canonical
SPKI DER. The comparison code is not an authentication credential.

The client displays the instance and comparison code. The user compares it with
the browser and approves that exact claim and fingerprint, or refuses it. The
label is declarative and untrusted; it must be rendered as text.

Status/consume body: `pairing_id`, `claim_id`, `verifier`. Knowing the invitation
ID or QR alone cannot read or consume an approved result for another claim.

## State, concurrency and protected delivery

States: `awaiting_scan -> awaiting_approval -> approved -> delivered -> completed`.
Terminal alternatives are `rejected`, `cancelled`, `expired`. State responses
include `pairing_id`, `state`, `expires_at`, `server_time`, `poll_interval`.
Terminal responses never include a handoff. State polling uses HTTP 200; request
failures use RFC 9457 problem responses with a fixed `reason` field.

Approval prepares a PAT, its normal server-side wrapped user key and an envelope
encrypted to the claim's public key. It does not publish an active PAT yet.
Neither PAT nor cleartext user private key is stored in the pairing table.

Consumption locks the account and transaction, rechecks account/API/PAT policy,
session markers, key identity, instance identity and expiry, then inserts one PAT
and records delivery with its audit in one SQL transaction. A failure rolls back
all three. A duplicate request cannot create another credential.

The `handoff` response contains:

- `version: 1`, `algorithm: "RSA-OAEP-256"`;
- `key_fingerprint`, `ciphertext`, `delivery_until`;
- `server_url`, `username` from the authorized account/instance, never from the claimant.
- `credential_id`, `credential_expires_at` for binding and displaying the durable authorization.

Ciphertext is base64url of RSA-OAEP encryption of the PAT's 64 ASCII hex characters.
Both OAEP hash and MGF1 use SHA-256, with an empty label. Its decoded length is
384 bytes. The ephemeral private key stays on the client. Existing phpseclib3
and CryptoManager implement transport encryption without a new library or
server key infrastructure. The user key wrapping format remains unchanged.

On a lost response, the same proven claim may retrieve **the same ciphertext**
for at most 60 seconds, bounded by the invitation's original expiry. No second
PAT is generated. First successful `/authorizeToken` use confirms delivery in the
authentication transaction, clears the provisional envelope and disconnects the
durable credential from the initiating web session. JWT creation and revocation
serialize on the account/credential locks.
Device JWTs use independent API-session markers, so their issuance does not
rotate the initiating browser's `key_tempo`, even when its sign-in is older than
one hour. Existing browser session expiry and administrator kicks still apply.

An unconfirmed published credential is unusable after its delivery window or
initiating session ends. Maintenance and request-time expiry revoke it and wipe
the handoff. Maintenance delay does not extend authorization. Finished transaction
metadata is pruned after 24 hours in bounded batches; the ordinary audit retains
only lifecycle labels, user ID, transaction ID and credential ID.

A completed credential has a finite expiry and is individually revocable.
Its JWTs carry `source_token_id` and
require a live matching source and API session on every request. See
[external device credentials](external-device-credentials.md). Offline client
data retention and licensing remain separate policies.

## Credential invalidation and offline clients

`credentialStatus` returns HTTP 200 only after matching the exact PAT's hash to
an external-device record. The body contains `credential_id`, `state`, `reason`,
`expires_at`, `server_time` and canonical `server_url`. States are `active`,
`pending` (a handoff not yet confirmed by successful authentication), `suspended`
(`access_removed`, for account/API/pairing policy), or `invalidated`. Terminal
reasons are `revoked`, `expired` or `keys_changed`; pending uses `not_confirmed`.
Suspension blocks access without claiming irreversible credential revocation:
lock local access and revalidate after policy restoration, without treating a
temporary administrative switch as a purge instruction. Unknown or legacy proofs
with the expected format receive the same 401 and never enumerate an account.
Malformed proof fields are rejected as invalid requests (400) before lookup;
neither response proves that a credential was invalidated.

Revocation retains the credential hash and minimal metadata as a proof record,
revokes linked JWTs transactionally, and wipes its wrapped private key, wrapping
salt and handoff association. No raw PAT is retained or returned. These records
remain until account deletion; they cannot authorize another JWT.

On a rejected JWT, try renewal once. A generic 401, 403, timeout, 5xx, invalid
JSON or unavailable endpoint is **not** a command to erase local data. A client
can verify definitive invalidation using its original PAT against the already
bound HTTPS instance, validating the response state, credential ID and server
identity. Only apply terminal cache/key invalidation to that established
profile. A pending pairing must never erase an existing profile's vault.

The client must define removal of server-derived caches and keys, invalidate
in-memory/Autofill projections, and explicitly handle encrypted unsynchronized
local edits without silently discarding them. The server cannot notify a fully
offline device: its pre-existing offline lease and local locking policy remain
independent limits. No immediate offline erasure or hardware attestation is
claimed. Capabilities advertise `credential_status: true` and the configured
`credential_lifetime_days`; the feature remains experimental pending validation
with an actual compatible client.

## Throttling and failures

Persistent mandatory limits are independent of the optional generic API limiter:
240 requests/minute per device-route IP, six creations/minute per user,
120 profile requests/minute per user, 90 web-state requests/minute per user and
90 status/consume requests/minute per invitation. Fixed windows may straddle a
minute boundary. Successful device status polls additionally have a two-second
minimum interval. A 429 carries `Retry-After`; clients should back off.

Fixed failure reasons include `invalid_request` (400), `invalid_proof` (401/403),
`claim_conflict` (409), `rate_limited` (429), `https_required` (403),
`keys_unavailable` (403), `unavailable` (403/503). Errors do not enumerate accounts
or include SQL, key material, submitted proofs or the credential. Restart with a
new QR after expiry or refusal. Closing a browser cannot guarantee a callback;
server expiry and session-marker checks remain authoritative.

A photograph can cause an unwanted claim or force a restart; explicit code
comparison prevents approval intended for a different immutable claim. This is
not hardware attestation and does not defeat phishing or a compromised endpoint.

## Reference client and validation

Use only an operator-controlled test account/installation. Run:

```text
php tests/Integration/device_pairing_client.php
```

Generate a QR in the profile. For this developer-only test, obtain its JSON from
the authenticated `create` response in the browser's network inspector and paste
it into the CLI prompt. Do not put it in command arguments, logs or tickets.
The CLI creates a claim, displays the comparison code and waits for web approval.
It verifies instance/key metadata, decrypts the PAT, requests two independent
JWTs, makes an authenticated folder request without printing its content, and
logs out those sessions. Revoke the reference client in the profile afterward.
No credential is printed or persisted. A private CA must be trusted through the
normal PHP/cURL configuration, never with an insecure TLS switch.

This CLI is supplied for the operator's environment test. The automated disposable
MariaDB test exercises the real installer schema, crypto, pairing service,
concurrent claims/approvals/consumptions, protected retry and credential lifecycle.
Its JWT issuer is an explicit fixture adapter; it is not presented as an HTTP
test of a complete TeamPass installation. Unit tests cover policy and transport
decisions. Run the normal PHP/JS/PHPStan checks and the MariaDB integration job.

Future clients must store the PAT securely, renew through `/authorizeToken`, bind
asynchronous results to the captured profile/server/account, and preserve their
manual fallback and local locking/persistence policies. Never send an existing
profile's credentials to a URL obtained from a scanned QR.
