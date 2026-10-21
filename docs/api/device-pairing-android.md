# Android client pairing implementation guide

This is the implementation handoff for an Android client of TeamPass Server.
The server protocol is **experimental version 1**. Its source of truth is
[device-pairing.md](device-pairing.md) and [OpenAPI](../../app/api/openapi.json).
This guide does not claim that a scanner, PAT profile mode or end-to-end Android
prototype already exists. iOS is outside the current scope.

## Intended experience

The user opens the pairing dialog in their authenticated web profile, then
opens **Scan a pairing QR** inside the Android app. The scanner reads JSON;
opening the app through the phone's native camera or a deep link is not part
of version 1. Preserve the existing manual configuration path.

Scanning reserves a request, not authorization. Show the instance and the
six-digit comparison code in the app. The user compares it with the browser
and explicitly approves that request in the browser. Only then may the app
receive its dedicated credential and authenticate.

```mermaid
sequenceDiagram
    participant W as Authenticated browser
    participant S as TeamPass Server
    participant A as Android client
    W->>S: Create short-lived invitation (session + CSRF)
    S-->>W: QR JSON, expiry
    A->>S: Claim (scanned secret + verifier challenge + public key)
    S-->>A: Claim ID, comparison code, key fingerprint
    W->>S: Approve the exact claim after code comparison
    A->>S: Consume (claim ID + verifier)
    S-->>A: PAT encrypted to the ephemeral public key
    A->>S: authorizeToken (login + PAT)
    S-->>A: JWT; delivery confirmed
    W->>S: Poll browser state
    S-->>W: Completed
```

Server prerequisites: normal upgrade completed, pairing enabled explicitly,
global and per-account API access, usable encryption keys, and trusted HTTPS.
This policy is independent of browser-extension token settings. For TLS
termination at a proxy, the server needs reverse proxy network mode and trusted
proxy addresses. Never bypass certificate checks to make pairing work.

## Capture an immutable attempt

Before starting asynchronous work, capture an attempt ID and the profile ID,
canonical instance URL and account identity involved. A callback must still
match this captured attempt before changing a profile or showing completion.
Cancel/discard late responses after profile changes, a new scan or cancellation.
Do not modify an existing profile until the newly paired identity is confirmed.

Bound QR input size and validate its exact type/version:

```json
{"type":"teampass.device-pairing","version":1,"server_url":"https://vault.example.test/team","pairing_id":"<32 lowercase hex>","pairing_secret":"<43 base64url characters>"}
```

Accept only an absolute HTTPS instance URL with no embedded credentials, query
or fragment. Display it before proceeding. Retain the configured path and port;
append `/api/v1/...` to that captured instance base. In particular, a leading
slash passed to `new Uri(baseUri, relativePath)` would discard a subdirectory.
Reject redirects rather than forwarding a credential to a different location.
Use the platform's trusted TLS configuration; never install a permissive
certificate-validation callback. Treat an unrecognized QR as untrusted input.

Never send an existing profile's JWT, PAT, password, API key, license secret or
cookie to a scanned URL. The initial capability/claim requests use none of them.
Do not log QR contents, request bodies, PATs or private keys, including through
HTTP diagnostics and crash reporting. Do not use clipboard/history to transport
the QR in the production scanner.

## Generate the attempt's verifier and ephemeral key

Generate a new verifier and RSA-3072 key once per attempt. Keep them for an
identical network retry; replacing either would create a competing claim.

Illustrative .NET code (to integrate with cancellation and profile binding):

```csharp
using System.Security.Cryptography;
using System.Text;

static string Base64Url(byte[] value) => Convert.ToBase64String(value)
    .TrimEnd('=').Replace('+', '-').Replace('/', '_');

string verifier = Base64Url(RandomNumberGenerator.GetBytes(32));
string challenge = Base64Url(SHA256.HashData(Encoding.ASCII.GetBytes(verifier)));
using RSA attemptKey = RSA.Create(3072);
byte[] publicDer = attemptKey.ExportSubjectPublicKeyInfo();
string publicPem = attemptKey.ExportSubjectPublicKeyInfoPem();
string fingerprint = Convert.ToHexString(SHA256.HashData(publicDer)).ToLowerInvariant();
```

Requirements: exponent 65537, canonical SPKI public key, OAEP SHA-256,
MGF1 SHA-256, and an empty OAEP label. The server refuses private PEMs,
different key sizes/exponents and malformed keys. This key protects only the
temporary handoff: it is not the user's vault key or hardware attestation.
Do not require an AndroidKeyStore alias or hardware backing for this ephemeral
RSA key. Test the actual .NET/Android provider against the server; do not assume
an OAEP transformation name implies the right MGF1 digest on every provider.
Dispose of the attempt key after completion, cancellation or expiry.

## Capability, claim, comparison and delivery

1. `GET /api/v1/devicePairing/capabilities`. Check type, version, algorithm,
   `key_bits`, and supported fields. The server advertises
   `credential_status: true` and `credential_lifetime_days`. Keep the client
   integration experimental until this complete flow is validated.
2. `POST /api/v1/devicePairing/claim`, JSON body:

   ```json
   {"pairing_id":"<QR id>","pairing_secret":"<QR secret>","challenge":"<challenge>","public_key":"<public PEM>","device_label":"My Android device"}
   ```

   Match the returned pairing ID and key fingerprint. Retain `claim_id` and
   display `comparison_code` exactly, including leading zeroes. Device labels
   are declarative text, not verified device identities.
3. Poll `POST /api/v1/devicePairing/status` with
   `{"pairing_id":"...","claim_id":"...","verifier":"..."}`. Wait at least
   the advertised two seconds. Honor HTTP 429 `Retry-After` and keep retries
   within the original server deadline. Calculate the displayed countdown from
   `server_time`/`expires_at`; polling does not extend it.
4. On `approved`, call `POST /api/v1/devicePairing/consume` with the same proof.
   Verify the handoff version, algorithm, fingerprint and exact instance URL.
   Retain its `username`, `credential_id` and `credential_expires_at` for the
   captured profile; do not choose a login supplied by the scanning UI.
5. Decode the ciphertext as base64url. It must be 384 bytes. Decrypt with the
   same attempt key and explicit OAEP SHA-256, then require exactly 64 lowercase
   ASCII hex characters:

   ```csharp
   byte[] clear = attemptKey.Decrypt(ciphertext, RSAEncryptionPadding.OaepSHA256);
   try
   {
       string pat = Encoding.ASCII.GetString(clear);
       // Validate format, bind to the captured identity, and authenticate below.
       // Do not persist until delivery is confirmed by successful authentication.
   }
   finally
   {
       CryptographicOperations.ZeroMemory(clear);
   }
   ```

   Managed strings cannot be reliably zeroed: minimize their lifetime and
   avoid extra copies. Protect the durable PAT using the existing storage modes.
6. `POST /api/v1/authorizeToken` with `{"login":"<handoff username>","token":"<PAT>"}`.
   Successful authentication confirms delivery and clears the server's provisional
   envelope. Persist the new authentication mode only after this success, still
   checking the captured attempt/profile/server/account before applying it.

If consume's response is lost, retry the same proven request. The server returns
the same encrypted result for at most 60 seconds, bounded by the QR deadline;
it does not issue a second PAT. Refusal, cancellation, expiry or a competing
claim requires a new QR. A pending attempt must not erase an existing profile.

## Profile persistence and JWT renewal

Introduce an explicit profile authentication mode such as `PasswordApiKey` and
`DeviceToken`, preserving existing profile migration/defaults. Store the PAT in
platform secure storage only for a remembered profile; session-only profiles
keep their credential in memory and clear it when the session ends. Store only
non-secret device metadata in preferences. Honor local locking, backup and
deletion policies. A PAT is not an API key, JWT or license token.

Adapt the shared session service, including Android Autofill, to renew device
profiles through `/authorizeToken` with the PAT. Such profiles must not depend
on non-empty password/API-key fields. Coalesce concurrent renewal requests and
bind results to the captured identity; do not overwrite another profile after
a switch. Test a second authorization after JWT expiry, not only the first JWT
obtained during pairing. New JWTs must not change the initiating web-session key.

New PATs expire after the administrator's configured duration (default 90 days).
Display the actual handoff expiry; changing server defaults does not extend an
existing device's deadline. Expiry requires a new pairing. This duration does
not configure or renew the offline lease or license.

## Rejected JWT, invalidation and offline vault

After a rejected JWT, attempt renewal once using this profile's PAT. Do not
interpret a generic 401 as a remote wipe instruction. For a definitive check,
POST `{"token":"<original PAT>"}` to the already bound instance's
`/api/v1/devicePairing/credentialStatus`. This route requires no JWT and remains
available when API/pairing is disabled, subject to trusted TLS and network ACLs.

Only accept a well-formed HTTP 200 from that trusted origin with the expected
credential ID and instance identity:

| State | Handling |
| --- | --- |
| `active` | The credential remains authorized; back off or ask the user about the authentication failure. Do not purge. |
| `pending` / `not_confirmed` | Finish the captured pairing attempt or restart it; never invalidate another profile. |
| `suspended` / `access_removed` | Lock local access and suspend renewal. Account/API/pairing policy can be restored; do not treat a temporary switch as terminal revocation or automatically destroy local work. Revalidate before unlocking. |
| `invalidated` with `revoked`, `expired`, `keys_changed` | Stop automatic renewal and invalidate server-derived local access for this exact established profile. Require re-pairing/reauthorization. |
| Unknown proof, 401/403, 404/unsupported endpoint, 429, 5xx, malformed response, identity mismatch or network failure | No proven invalidation. Lock or request reauthentication as appropriate; never erase solely because of this response. |

Define the client invalidation operation before shipping: invalidate JWT/PAT
use, server-derived snapshots, associated keys, memory caches and Autofill
projections. Audit diagnostics must remain free of secrets. Keep identity
binding checks around the entire operation so a late response cannot clear
the newly selected profile.

Unsynchronized local edits need an explicit policy: an outbox may contain
server-derived baselines as well as new user input. Do not quietly delete user
work, retain readable revoked server data through an outbox, or destroy a shared
cache/outbox encryption key without resolving this design. Separate cache
invalidation from any protected handling of pending user edits, and test both.

An offline device cannot learn a new server revocation. Its existing local
locking and offline lease remain applicable; a failed refresh does not renew
that lease. Pairing does not automatically enable offline storage or Autofill,
and no immediate offline erasure is promised.

## Acceptance checklist before removing the experimental flag

- Actual Android scanner to browser approval to decryption to first and renewed JWT.
- Actual target runtime/provider: RSA-3072, exponent 65537, both OAEP digests,
  SPKI fingerprint, private key lifetime and trusted TLS/subdirectory handling.
- API-enabled local, directory and SSO accounts according to the test instance's
  authentication/MFA setup; unavailable session keys are handled explicitly.
- Browser sign-in older than one hour, cancellation, concurrent scans/approvals,
  lost consume response, expiry and old callbacks after profile/server changes.
- Remembered/session-only profiles, local locking, secure credential deletion,
  shared renewal and Autofill entry points.
- User/admin revocation, finite expiry, disabled API/pairing and changed keys;
  previously issued JWTs stop working and known proof reports invalidation.
- Network loss, temporary authentication locks, unknown proofs and server errors
  never cause an unproven purge; offline leases are not silently extended.
- No cross-profile erasure; explicit behavior for queued edits and encrypted
  outbox baselines; server-derived secrets remain inaccessible after invalidation.
- No secret in diagnostics, screenshots, analytics, intents or crash reports.

The PHP [reference client](../../tests/Integration/device_pairing_client.php)
helps exercise a test installation but does not replace this Android prototype.
