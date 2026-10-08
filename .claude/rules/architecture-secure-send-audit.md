---
paths:
  - "app/sources/secure_send*.php"
  - "app/sources/admin.queries.php"
  - "app/pages/statistics*.php"
  - "public/assets/css/statistics.css"
  - "app/includes/language/english.php"
  - "app/includes/language/french.php"
  - "tests/Unit/SecureSend*.php"
  - "tests/Fixtures/secure_send_*.php"
  - "tests/Integration/secure_send_*.php"
  - "tests/JavaScript/secure-send*.test.cjs"
  - "docs/install/secure-send.md"
---

# Secure Send audit journal

## Scope and consumers

`secure_send_audit` is the structured, metadata-only journal of Secure Send
operations. It is separate from `otv`, which holds disposable encrypted links,
and from the existing `log_items` audit under the OTV system account.

The audit foundation provides storage, lifecycle instrumentation and migration.
Its dependent statistics change adds aggregates to the existing administrator
statistics endpoint; the Users statistics card displays them below item activity.
Governance/Reports can later consume the journal through their existing
administrator-only, feature-gated handlers. No raw journal read, export or purge
endpoint is added, and no access to another user's item contents is granted.

## Data contract

The append-only lifecycle rows (until administrator-configured retention) contain only:

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

Indexes support per-link history, per-event periods, per-originator periods and
bounded retention ordered by observation time and id.
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

Cleanup is opportunistic: a failed batch is rolled back and logged (exception
class only), but does not abort an otherwise authorized creation or list request.
The creation itself still requires its own committed audit event. Historical rows
with a non-positive link/sender identifier cannot satisfy the audit allowlist:
cleanup retains them, reports a metadata-validation diagnostic and excludes them
before the batch limit so they cannot starve valid rows. They remain expired and
unusable, and require separate operator investigation rather than silent deletion.

The final allowed reveal is identifiable from `views == max_views`; it does not
delete the link immediately. When a successful reveal deactivates a source item,
the existing item-deletion audit is retained; other links are invalidated when
their lost access is subsequently observed. Invalidations are observations,
not a proactive scan of every outstanding link after each permission change.

Behaviour change: if the source item's automatic-deletion policy is already
elapsed or exhausted before reveal, the link itself is now removed with an
`invalidated` / `item_auto_deleted` event, in addition to deactivating the item.
Previously that denied reveal retained the link. Successful final-view behaviour
and the existing item-deletion audit remain unchanged.

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

## Statistics consumer

`secureSendBuildOperationalStatistics()` adds `users.secure_send` to the existing
`get_operational_statistics` response from `admin.queries.php`. The endpoint keeps
its authenticated session, administrator page permission and session-key checks;
its existing public proxy is reused. The separate presentation change consumes this
contract without adding an endpoint, database query or permission bypass.

The contract contains:

- `enabled`: current Secure Send setting, not a filter erasing past activity;
- `available`, `error`, `reason`: distinguish a valid empty period from failed
  storage/schema queries. Failures return NULL totals/creation breakdowns and an
  empty ranking, never fabricated zeros or a partially successful aggregate;
- `meta`: inclusive `from`/`to` timestamps, `filters_applied: ['period']`,
  `historical_backfill: false`, `recipient_identity_known: false`;
- `totals`: `created`, `revealed`, `reveal_failed`, `revoked`, `invalidated`,
  `expired`, distinct `sends_revealed`, and distinct creation `senders`;
- `creations`: `items`, `notes`, `unknown`, `protected`, `unprotected`,
  `public_links`, `internal_links`, counted only on `created` events;
- `top_senders`: up to five original senders with creations in the period, their
  period creation/reveal/failure counts, last creation timestamp and current
  account identity/status (`active`, `disabled`, `deleted`, `missing`).

Counters use each event's `occurred_at`, not link `created_at`, configured expiry
or cumulative view/failure counters. They describe events in the selected period,
not the subsequent lifecycle of that period's creation cohort. A legacy link can
contribute reveals without an audited creation. Distinct senders refer to audited
creations, not anonymous recipients. Failed reveals are recipient attempts against
a sender's links, not proof of malicious activity by that sender.

Period bounds reuse `opsStatsResolvePeriodRange()` in the configured PHP timezone,
including calendar week/month and daylight-saving transitions. Native timestamp
predicates and fixed event filters can use the journal's event/period index;
there is no SQL timezone conversion or CAST on `occurred_at`. The helper rejects
invalid or greater-than-90-day ranges. Summary and ranking are two read queries,
so newly committed events can change the database between those reads; no
transactional snapshot across the entire dashboard is claimed.

The complete sender population is grouped in SQL before the top-five limit.
Ties sort by latest creation, then original sender id. User identity is LEFT JOINed
after aggregation; disabled, soft-deleted, purged accounts and missing source
items do not erase history. Only internal service account originators are excluded.
No content/keys, individual send/item identifiers or recipient identity are exposed
in the response. Login/display name are administrator-facing sender identity only.

The journal does not snapshot personal-item or API provenance. Applying those
dashboard toggles through current item/account state would silently remove durable
history. They therefore do not affect this block; its filter metadata makes that
scope explicit in the card. There is no live-link inventory, abuse
threshold, automatic quota/block, CSV export, backfill or schema change in this step.

The Users card shows period event totals, creation-policy breakdowns and the top
five creation senders. It keeps historical data visible when Secure Send is
disabled, labels disabled/deleted/removed accounts, and explains coverage and
anonymous-recipient limitations. A valid empty period shows zero counters; missing
or failed aggregates show an unavailable message instead. Loading/failure clears
the prior card values and cached payload; overlapping requests ignore superseded
responses so tab switches cannot restore a previous period's activity. Sender
identity uses HTML escaping and translation strings are JSON/HTML-context encoded.
No Chart.js dependency is introduced for this card.

## Installation, upgrade and retention

Fresh installation adds the `secure_send_audit` action to run.step5/install.js.
The 3.2.3 feature upgrade (`public/install/upgrade_run_3.2.3.php`) calls the same
`secureSendAuditSchemaSql()` DDL with
`CREATE TABLE IF NOT EXISTS`; replay preserves existing records. `UPGRADE_MIN_DATE`
is raised so existing installations must run the feature migration. The maintained
3.2.2.x hotfix migration does not introduce this feature's schema. No dependency,
secret/configuration file, release version or cipher format is changed.

The journal starts with operations observed after installation/upgrade. Existing
links remain usable with the historical recipient contracts; no synthetic
creation or past-attempt/reveal events are inserted. Statistics must count
`created` events by `occurred_at`, not infer historical volume from `created_at`.
Expiry observation time and configured deadline are distinct.

The first three changes do not delete journal evidence. The fourth change adds
opt-in retention through the existing orphan-object maintenance task, without an
application purge endpoint. Link/account deletion still does not cascade to the
journal. Operators must plan capacity, restricted database/backup access and a
documented retention policy. Administrators with SQL access can alter this table: it is not a
tamper-proof evidence store. Centralized syslog, protected archives and synchronized
clocks are separate operational controls, not a claim of regulatory compliance.

### Step 4/4: administrator-controlled retention

`secure_send_audit_retention_days` is an administrator setting: `0` (default)
keeps all evidence; whole numbers from `1` to `36500` set the retention window.
The existing authenticated, administrator-only `save_option_change` handler
validates the raw value before sanitization and invalidates ConfigManager's cache
after persistence. Malformed values are rejected rather than rounded or clamped.
Saving a policy never runs a purge itself. Increasing it cannot restore deleted
events. Missing/disabled runtime policies do not access the audit database;
invalid policies fail closed and mark the maintenance run as an error.

`app/scripts/task_maintenance_clean_orphan_objects.php` calls
`secureSendPruneAuditHistory()` once per invocation. Configure the existing
`clean_orphan_objects_task` schedule and keep the scheduler running: the setting
alone does not schedule work. Each invocation deletes at most **1000** events,
oldest `occurred_at` then `id` first, with a strict
`occurred_at < now - days * 86400` predicate. Events exactly at the cutoff,
newer events and future timestamps remain. UTC elapsed days, not local calendar
midnights, define the cutoff. The link's creation/expiry time is not used.
Backlogs drain over successive runs; this is not a guarantee that every old event
disappears precisely when it ages out. Retention applies to all lifecycle event
types and remains active when Secure Send itself is disabled.

Deletion and its `log_system` summary share one transaction; both tables must be
InnoDB or retention refuses to run. A database/summary/commit error rolls back
the whole batch. The maintenance task reports an error, without logging SQL,
exception messages or secrets, and other completed maintenance remains intact.
An empty batch writes no summary. In **Utilities / Logs / Administration**, the
`secure_send_audit_retention_purge` action is attributed to the TeamPass system
account. Its JSON details contain only `retention_days`, `cutoff`, actual
`deleted_count` and `batch_limit`; there are no send/item ids, payloads or keys.
The summary lives outside the pruned lifecycle journal. Optional syslog forwards
it only after commit as `action=secure_send_audit_retention {JSON}`. Transport
failure cannot undo a committed purge and does not provide delivery guarantees.

Fresh installation initializes the setting to `0` and creates
`idx_retention_period (occurred_at, id)`. The replayable **3.2.3** feature migration
adds the index to an existing audit table and inserts only a missing setting,
preserving any administrator policy and existing evidence. It invalidates the
configuration cache before recording upgrade completion. `UPGRADE_MIN_DATE` is
raised again so deployments of the first three steps run this addition. The
3.2.2.x hotfix migration remains unchanged.

Statistics describe **retained events only**; a selected period can be incomplete
after retention, and zero must not be interpreted as proof of no past activity.
Choose retention to cover the reporting and investigation windows required by
your organization. Policy changes, database copies, backups, general system-log
purges and external collectors have independent retention/access rules. This
maintenance does not erase backup/collector copies, preserve summaries forever,
implement legal holds or provide tamper-proof/regulatory-compliance guarantees.

## Validation

`SecureSendAuditTest` exercises real Defuse payloads with a transactional adapter:
metadata privacy, actor separation, atomicity, failure rollback, terminal attempts,
owner checks, bounded cleanup and forwarding after commit. The existing access,
legacy/snapshot, note, TOTP and automatic-deletion suites remain applicable.
`SecureSendAuditSchemaTest` guards the shared DDL and its fresh/upgrade wiring.
`tests/Integration/secure_send_database.php` executes the shared schema/replay and
real concurrent operations plus audit SQL failures against disposable MariaDB
tables with a non-default prefix. It runs in the existing concurrency CI job.
`SecureSendStatisticsTest` executes the actual aggregate SQL through SQLite parameter
binding, covering period boundaries, historical links/accounts, protection/type
breakdowns, unavailable data, service-account exclusions, a population exceeding
500 senders, deterministic top five and the admin endpoint boundary. The existing
MariaDB harness also calls `secure_send_statistics_database.php` to verify production
MeekroDB binding and queries with `ONLY_FULL_GROUP_BY` enabled.
`SecureSendRetentionTest` covers strict policy validation, disabled policies,
cutoff boundaries, bounded/repeated deletion, transactional failure rollback,
engine prerequisites and safe post-commit forwarding. Settings tests execute the
existing handler with the real encrypted/plain exchange wrapper and verify cache
invalidation and rejection before persistence. Migration tests replay the actual
installer block against an already populated journal. The disposable MariaDB
harness additionally exercises migration replay, actual bounded DELETE syntax,
summary insert failures, MyISAM refusal, independent concurrent workers and
statistics over retained events.
