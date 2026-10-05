# Secure Send audit journal

## Scope and consumers

`secure_send_audit` is the structured, metadata-only journal of Secure Send
operations. It is separate from `otv`, which holds disposable encrypted links,
and from the existing `log_items` audit under the OTV system account.

This first change provides storage, lifecycle instrumentation and migration.
The next dependent changes add server-side statistics, then the Users statistics
card. Governance/Reports can later consume this same journal through their
existing administrator-only, feature-gated handlers; this change adds no read,
export or purge endpoint and grants no access to another user's item contents.

## Data contract

The immutable-at-application-level rows contain only:

- `send_id`: internal `otv.id`, retained after the link disappears;
- `event`, `reason`: fixed, validated codes;
- `occurred_at`: observation time as a Unix timestamp;
- `originator`: original sender account id, never the OTV system account;
- `actor_id`: sender id for creation/revocation; NULL for recipients or cleanup;
- `item_id`: source item id, or NULL for standalone notes;
- `send_type`: `item` (both historical and snapshot formats), `note`, or `unknown`
  for unsupported historical metadata encountered by cleanup;
- `created_at`: original link timestamp, not proof of an audited creation;
- `has_passphrase`, `is_public`, `max_views`, `expires_at`: policy metadata;
- `views`, `failed_attempts`: counters after the observed operation.

No payload, item label, note title, login, email, key, lookup code, URL, passphrase,
IP address, request body or free-text error is accepted into the journal.
`secureSendAuditRecord()` uses an explicit allowlist rather than copying a row.
Anonymous reveal attempts cannot establish the recipient's identity. A committed
`revealed` event proves server-side decryption and reservation, not that the
recipient received the HTTP response or copied/read the content.

Indexes support per-link history, per-event periods and per-originator periods.
There are no cascading foreign keys: account, item and link deletion must not
erase evidence. Future consumers must preserve rows whose account or item no
longer exists (for example, LEFT JOIN and an identifier fallback).

## Events and atomicity

| Event | Reason | When recorded |
| --- | --- | --- |
| `created` | empty | Authorized encrypted link insertion |
| `revealed` | empty | Successful server-side reveal/view reservation |
| `reveal_failed` | `wrong_credentials` | Incorrect URL key or passphrase on a confirmed attempt |
| `revoked` | `sender_revoked` | Owner-authorized removal |
| `invalidated` | `sender_unavailable` | Note sender no longer eligible |
| `invalidated` | `item_access_lost` | Item missing/inactive or sender no longer allowed to read it |
| `invalidated` | `attempts_exhausted` | Fifth credential failure, alongside its `reveal_failed` row |
| `invalidated` | `item_auto_deleted` | Elapsed/exhausted source-item auto-deletion policy observed before reveal |
| `expired` | `deadline_elapsed` | Expired ciphertext removed by opportunistic cleanup |

The journal INSERT and affected operation share an InnoDB transaction. An audit
failure aborts creation, reveal, revocation, attempt increment or cleanup; no
decrypted fields or usable new URL are returned. Recipient row locks serialize
attempts and reveal limits. Revocation locks the same link and rechecks ownership.
Cleanup processes at most 100 links in id order, auditing before removal; an error
rolls back the whole batch. Repeated or concurrent cleanup cannot duplicate an
event for a removed link. Expired rows left beyond a batch remain unusable and
are omitted from the active-link list.

The final allowed reveal is identifiable from `views == max_views`; it does not
delete the link immediately. When a successful reveal deactivates a source item,
the existing item-deletion audit is retained; other links are invalidated when
their lost access is subsequently observed. Invalidations are observations,
not a proactive scan of every outstanding link after each permission change.

GET, missing/forged/replayed confirmations, unknown links, wrong hosts and already
unavailable links do not create events. This avoids a public, unbounded audit
amplification path. Missing required passphrases consume neither an attempt nor
an event. Storage/crypto runtime errors retain the existing generic error log,
without treating broken storage as a bad recipient credential.

## External forwarding

After commit, `secureSendEmitAudit()` forwards the safe row as
`action=secure_send {JSON metadata}` through the existing optional syslog settings.
The legacy item syslog and item-deletion WebSocket events also wait for commit.
External transport failures do not undo committed operations; diagnostics contain
only the exception class. Syslog is best effort, with no new queue or delivery
guarantee. The database journal is authoritative for application statistics.

## Installation, upgrade and retention

Fresh installation adds the `secure_send_audit` action to run.step5/install.js.
The 3.2.2 patch upgrade calls the same `secureSendAuditSchemaSql()` DDL with
`CREATE TABLE IF NOT EXISTS`; replay preserves existing records. `UPGRADE_MIN_DATE`
is raised so existing installations must run the patch migration. No dependency,
secret/configuration file, release version or cipher format is changed.

The journal starts with operations observed after installation/upgrade. Existing
links remain usable with the historical recipient contracts; no synthetic
creation or past-attempt/reveal events are inserted. Statistics must count
`created` events by `occurred_at`, not infer historical volume from `created_at`.
Expiry observation time and configured deadline are distinct.

This first change has no automatic audit retention or application purge route.
Rows persist independently from encrypted-link cleanup and general log cleanup.
Operators must plan capacity, restricted database/backup access and a documented
retention policy; future purge tooling must be administrator-authorized and itself
audited. Administrators with SQL access can alter this table: it is not a
tamper-proof evidence store. Centralized syslog, protected archives and synchronized
clocks are separate operational controls, not a claim of regulatory compliance.

## Validation

`SecureSendAuditTest` exercises real Defuse payloads with a transactional adapter:
metadata privacy, actor separation, atomicity, failure rollback, terminal attempts,
owner checks, bounded cleanup and forwarding after commit. The existing access,
legacy/snapshot, note, TOTP and automatic-deletion suites remain applicable.
`SecureSendAuditSchemaTest` guards the shared DDL and its fresh/upgrade wiring.
`tests/Integration/secure_send_database.php` executes the shared schema/replay and
real concurrent operations plus audit SQL failures against disposable MariaDB
tables with a non-default prefix. It runs in the existing concurrency CI job.
