# External device credentials

External device pairing uses a dedicated Personal Access Token (PAT), with the same
HKDF-SHA256 and AES-256-GCM private-key wrapping format as existing tokens. The
user's private key remains on the server. Account and API permissions remain authoritative.

The feature is experimental and disabled by default. The dedicated pairing
policy requires global and per-account API access, independently of the
extension/OAuth2 PAT toggles. Enabling pairing does not enable legacy token
authentication for additional account types.

PATs issued by device pairing have `purpose = external_device`. Their JWTs carry `source_token_id`, and
their API session records carry the same indexed identifier. Each request checks
the credential's current existence, expiry, owner and policy. Deleting a credential
therefore also stops its existing JWTs. Revocation marks the linked sessions revoked
before deleting the credential, in one transaction. Source-bound JWTs require their
session record and never use the historical single-row fallback.

Device authentication and revocation lock the user before the credential, so a
concurrent authentication cannot publish a new session after revocation. Existing
password-based sessions and legacy extension PATs keep their previous behavior.

Run the normal upgrade wizard before using the feature, including on a 3.2.3.x
installation. Its script manager replays `upgrade_run_3.2.3.php`; the additive
credential columns and indexes are idempotent. Fresh installation includes the same
columns. `device_pairing_schema` records successful schema installation;
`device_pairing_enabled` remains `0` until the administrator opts in.

Pairing transaction lifetime, credential lifetime and JWT lifetime are separate.
A short-lived invitation does not make a durable credential short-lived, and a
server-side revocation does not immediately erase data cached by an offline client.

The complete versioned protocol and its reference test client are described in
[external device pairing](device-pairing.md). External device PATs and JWTs always
require HTTPS, independently of the optional HTTPS setting for older API clients.
