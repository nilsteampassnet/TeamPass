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

### Operating systems and validation scope

The recovery engine uses PHP and MySQL/MariaDB, without a Bash, `sudo`, systemd
or Docker dependency. Use 64-bit PHP 8.2 or later with the normal TeamPass
extensions, plus `zip`. The CLI must have the required extensions too, and
`proc_open` must be available for the isolated migration and verification
processes. The Linux commands below are deployment examples, not an OS check.

| Platform | DRP validation status |
| --- | --- |
| Ubuntu 26.04, native installation | Complete recovery, upgrade, HTTP assistant, publication and service resume tested with PHP 8.5 and MariaDB. |
| Native Windows, local filesystem | The same complete scenarios tested with PHP 8.2 and MariaDB, including paths containing spaces. These desktop tests do not certify Windows Server or IIS. |
| Other Debian/Ubuntu releases | Compatibility target with the required PHP/database versions; each deployment still needs a restore rehearsal. |
| RHEL, Rocky Linux and AlmaLinux | Compatibility target; validate the service identity, writable directories and SELinux policy. No end-to-end distribution-specific qualification yet. |
| SLES/openSUSE | Compatibility target; no end-to-end distribution-specific qualification yet. |
| Windows Server | Compatibility target; server/service-account and NTFS ACL acceptance tests remain required. IIS/FastCGI integration is not yet qualified. |

The application's PHP requirements matter more than the distribution name:
an otherwise supported OS with an older PHP installation is not sufficient.
These statements cover DRP; optional TeamPass services such as the WebSocket
daemon have their own platform requirements.

On Linux, adapt the PHP account/group to the deployment and preserve its
SELinux/AppArmor protections. On Windows, use local NTFS storage and restrict
ACLs on `storage/recovery/`, `secrets/` and `app/config/` to the intended
administrators and PHP/CLI service identities. POSIX `chmod` values do not
establish those ACLs. The staging area requires reliable file locking, atomic
same-directory replacement and hard-link creation; network shares and FAT
filesystems have not been qualified. Validate both web and CLI access under
the actual production identities, not only an administrator's interactive
account. HTTPS, web-server configuration and task scheduling remain part of
the destination infrastructure runbook.

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

New backups use a single read-only InnoDB repeatable-read snapshot for the dump
and document inventory. Concurrent backups are rejected, and maintenance stays
enabled until the complete package has been published. Non-InnoDB tables stop
the backup. This does not freeze filesystem writers or requests already running:
drain active writes and stop background/rotation jobs before a recovery-grade
capture, and prevent concurrent schema upgrades. The manifest describes the
database capture without claiming a filesystem snapshot. Older archives lack
these capture guarantees and still require a complete restore rehearsal.

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

The assistant detects the Unix PHP worker account and shows commands with that
account and absolute PHP/script paths for enable, restore, activation and resume.
Do not use `sudo php`: it creates owner-only files as root, which the web worker
cannot read. Mutating recovery CLI commands refuse Unix root execution. Common
accounts are `www-data` on Debian/Ubuntu, `apache` on RHEL-family installations,
and `wwwrun` on SUSE; a custom FPM pool may use another identity. On Windows the
page shows a PowerShell command; use the service identity configured in Apache
or IIS, not an unrelated administrator account.

The first implementation uses a local or otherwise protected SQL connection.
It does not import external TLS private-key files from a recovery kit or configure
the web server, DNS or an identity provider.

### Installation assistant

Like the installation and upgrade wizards, the recovery assistant is in English
and does not use the configured application language or a language selector.

From the project root:

```bash
sudo -u www-data php app/scripts/disaster-recovery.php --enable
```

Open `/install/install.php` and choose **Restore from a backup and Recovery
Package**, or open `/install/recovery.php` directly. Enter the one-hour operator
code printed by the command. It is submitted through the form, never in a URL.
HTTPS is required except over a local loopback/SSH-tunnel connection.

Upload both archives, enter their passphrases, and supply the **destination** SQL
credentials and HTTPS address. For large archives, place them owner-only in
`storage/recovery/incoming/` and enter their bare filenames instead of uploading.
Align PHP and web-server upload/time limits with the archive sizes. The CLI
preparation described below avoids an HTTP upload or request timeout.

The assistant authenticates/decrypts the archives, validates their paths and
checksums, checks the SQL scope and required tables, and confirms the destination
is empty. It shows the source/target versions and prints a command containing
only a random job identifier:

```bash
sudo -u www-data php app/scripts/disaster-recovery.php --run=JOB_ID
```

This imports and validates the database, applies the supported migrations when
needed, and checks item decryption with the restored system key chain. It checks
the complete document references against the imported database. A successful job
reaches `verified`; the web assistant can refresh its progress.

Then publish the restored documents, original master key and new configuration:

```bash
sudo -u www-data php app/scripts/disaster-recovery.php --activate=JOB_ID
```

The archived `settings.php` is never executed. New destination credentials are
encrypted with the original master key. `settings.php` is published last.
Private staging is removed after success; a non-sensitive receipt remains.

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
The item changes API returns a new `generation` after every recovery and requires
a full sync when the client's generation is absent/different or its cursor is
ahead of the restored journal. Clients must adopt both cursor and generation
after rebuilding their cache, then send `generation` on subsequent delta calls.
Older clients that do not support generations continue receiving full-sync
requests after recovery; upgrade or refresh them explicitly.

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
