<!-- docs/install/disaster-recovery.md -->

## Disaster recovery on a fresh server

The recovery assistant restores a complete encrypted `.tpbackup` and the original
`.tprecovery` Recovery Package without first running a normal installation.
It imports into an empty dedicated database, checks the original encryption key,
applies supported database migrations, verifies item decryption, and only then
publishes the files and a newly generated destination configuration.

The initial supported source releases are **3.2.2.x and 3.2.3.x**. The target must
be at least as recent as the backup. The shipped 3.2.2 and 3.2.3 migrations are
reused, including replayable changes in the source release. A missing upgrade
path, a newer source schema, or a newer source version stops recovery. Historical
SQL-only backups and pre-3.2.2 migrations require a separate recovery procedure.

### Material to preserve before a disaster

- A complete `.tpbackup`, with **Include documents** enabled. Missing attachments,
  KB attachments, avatars or scan warnings prevent a complete recovery.
- The original Recovery Package and its passphrase, kept separately from backups.
- The encryption key of a manual backup, or the matching scheduled-backup key.
  A kit contains the scheduled key current when it was generated; an older backup
  may require its original key to be entered explicitly.
- A known compatible TeamPass distribution with its production dependencies.
- Access to the destination server and to a working administrator authentication
  method. Recovering the database does not recreate an unavailable LDAP/OAuth2
  provider or a lost hardware authenticator.
- An infrastructure runbook: HTTPS, DNS, web-server/PHP configuration, scheduled
  tasks, external certificate files and any custom persistent paths or branding.
  The application packages do not replace a server configuration backup.

Preserve the master key exactly. A newly generated `SECUREFILE` cannot replace
the original one. Do not run a fresh installation over restored data.

### Prepare the destination

1. Deploy the code with `public/` as the web root. Do not run the installer yet.
2. Provide a new empty database and a SQL account restricted to that database.
   It needs the schema/data privileges used by the installer and migrations;
   it does not need global `FILE`, user-management or administrator privileges.
3. Make the configuration, secrets and persistent data directories writable by
   the PHP service account during recovery. Existing configuration, key material
   or application documents cause the fresh-server checks to stop.
4. Configure HTTPS. A new valid certificate is acceptable. Keeping the original
   domain avoids changing the origin/RP ID of sign-in passkeys and integration
   addresses. A database restored under another domain needs separate checks.
5. Run recovery commands **as the PHP service account**, so owner-only temporary
   data and the generated configuration remain readable by the web application.
   The examples below use Debian/Ubuntu's `www-data`; adapt it to your deployment.

The first implementation uses a local or otherwise protected SQL connection.
It does not import external TLS private-key files from a recovery kit or configure
the web server, DNS or an identity provider.

### Prepare and restore from the command line

Prepare a private JSON request containing `kit_path`, `kit_password`,
`backup_path`, `backup_password`, `url`, and `connection` (`host`, `port`,
`database`, `user`, `password`). Use `--prepare=-` to read it from standard input,
or protect a request file with owner-only permissions:

```bash
sudo -u www-data php app/scripts/disaster-recovery.php --prepare=/private/request.json
sudo -u www-data php app/scripts/disaster-recovery.php --run=JOB_ID
sudo -u www-data php app/scripts/disaster-recovery.php --activate=JOB_ID
```

The preparation command prints a random job identifier. The `--run` command
imports and verifies the dedicated database and applies supported upgrades.
`--activate` publishes verified documents and configuration, leaving the service
in maintenance. The archived `settings.php` is never executed. New destination
credentials are encrypted with the original master key. `settings.php` is
published last. Successful activation removes private staging and leaves a
non-sensitive receipt. Remove the private request file after preparation.
### Operational checks and resuming service

The restored instance stays in maintenance, and background processing is held.
Backup schedules and LAPR are disabled. Sessions, API authorizations, temporary
tokens, pending background tasks and idempotency results are invalidated.
Restored Secure Send links are invalidated with a transactional lifecycle audit.

Before reopening, check administrator access, a standard user's decryption and
permissions, personal folders, read-only/manager roles, documents, MFA/passkeys,
HTTPS, the URL and the external authentication services actually used.

```bash
sudo -u www-data php app/scripts/disaster-recovery.php --resume=JOB_ID
```

This releases maintenance and the recovery background-task hold. Reconfigure
and enable the backup schedules and LAPR deliberately. Restoring a backup does
not roll back passwords on remote servers; reconcile rotations since the backup.
Force a full refresh of external clients holding an offline cache after recovery.

### CLI preparation and failures

`--prepare=FILE` reads a private JSON request; `--prepare=-` reads standard input.
The request has `kit_path`, `kit_password`, `backup_path`, `backup_password`,
`url`, and `connection` (`host`, `port`, `database`, `user`, `password`). Passwords
must not be placed in command-line arguments. Protect and remove any request
file containing them after preparation.

`--status=JOB_ID` prints non-sensitive progress. `--discard=JOB_ID` removes the
private staging of a prepared/failed job; it does not delete or reset the SQL
destination. A failed import may leave a partial database. Use a new empty
database after investigating the diagnostic rather than treating DDL as globally
rollbackable. Preserve the original encrypted archives separately.

Useful diagnostics include `ARCHIVE_AUTHENTICATION_FAILED` (incorrect key or
modified archive), `MASTER_KEY_DATABASE_MISMATCH`, `DOCUMENTS_DATABASE_MISMATCH`,
`TARGET_DATABASE_NOT_EMPTY`, `DOWNGRADE_NOT_SUPPORTED`, `UPGRADE_FAILED` and
`TARGET_SCHEMA_INCOMPLETE`. A successful archive integrity check alone is not a
proof that the source database and files were captured consistently: regularly
exercise the complete disaster-recovery process and measure the actual RPO/RTO.
