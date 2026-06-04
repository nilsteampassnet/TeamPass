<!-- docs/features/backups.md -->


## Generalities

Teampass provides a complete backup and restore solution to protect your password database. Three backup modes are available:

- **On the fly**: Manual backup and restore operations
- **Scheduled**: Automated backups with configurable frequency and retention
- **Externalized**: Automated or manual copies to an external destination

All backups are encrypted to ensure data security. On-the-fly backups use the encryption key entered by the administrator. Scheduled and externalized backups use the Teampass instance key automatically.

> ⚠️ Only administrators have access to the backup functionality.


## On the fly backup

### Performing a backup

The on-the-fly backup allows you to create an immediate backup of your database.

1. Navigate to `Backups` in the left menu
2. Stay on the `On the fly` tab
3. Enter an **encryption key** (or click the random generator button to create one)
4. Optionally enter a **comment**
5. Optionally enable **Include documents**
6. Click `Perform backup`

> 💡 Store your encryption key in a safe place. Without it, you will not be able to restore the backup.

When **Include documents** is enabled, Teampass creates a `.tpbackup` package containing the database and supported document files. Otherwise, it creates a legacy encrypted SQL backup.

The backup file will be stored on the server and will appear in the **Server backups** table below.

### Server backups list

All on-the-fly backups stored on the server are displayed in a table showing:

| Column | Description |
|--------|-------------|
| Date | When the backup was created |
| Size | File size of the backup |
| Teampass version | Version of Teampass that created the backup |
| Comment | Optional administrator note |

Available actions for each backup:
- **Download**: Download the backup file to your local machine
- **Use for restore**: Select this backup in the restore form
- **Edit comment**: Update the optional backup comment
- **Delete**: Remove the backup from the server

If a backup contains documents, an indicator is displayed next to the backup date.

### Restoring from a backup

To restore your database from a backup:

1. Navigate to `Backups` in the left menu
2. In the **Restore** section, enter the **encryption key** used during backup
3. Select a backup file:
   - **From server**: Click on a backup in the server backups table
   - **From file**: Click `Choose file` to upload a `.sql` or `.tpbackup` backup file
4. Click `Prepare restore`

> ⚠️ A restore operation will replace your current database. All connected users will be disconnected and you will be logged out automatically.

### Exclusive mode

Before starting a backup or restore operation, Teampass checks if other users are connected. If so, a dialog will appear allowing you to:

- View the list of connected users
- Disconnect individual users
- Disconnect all users at once
- Continue once no other users are connected

This ensures data integrity during backup and restore operations.


## Scheduled backups

Scheduled backups automate the backup process, running at specified times without manual intervention.

### Configuration

Navigate to the `Scheduled` tab to configure automated backups:

| Setting | Description |
|---------|-------------|
| Enable | Turn scheduled backups on or off |
| Frequency | `Daily`, `Weekly`, or `Monthly` |
| Time | Time of day to run the backup (e.g., 02:00) |
| Day of week | For weekly frequency, which day to run |
| Day of month | For monthly frequency, which day to run |
| Retention days | Number of days to keep old backups (1-3650) |
| Output directory | Custom directory for backup files (optional) |
| Include documents | Include item attachments, knowledge base attachments, and avatars in the `.tpbackup` package |

> 💡 The output directory must stay inside the Teampass backup storage area or inside the configured files folder.

When **Include documents** is enabled, scheduled backups are generated as `.tpbackup` packages. Otherwise, they use the legacy encrypted SQL format.

### Email notifications

You can enable email reports for scheduled backups:

| Setting | Description |
|---------|-------------|
| Enable email report | Send email notifications about backup status |
| Only on failures | Only send emails when backups fail |

> 💡 Email settings must be configured in Teampass options for notifications to work.

When externalized backups are configured to run after scheduled backups, Teampass sends a single consolidated report after the externalized step completes. No separate scheduled-only email is sent for that run.

### Buttons

| Button | Description |
|--------|-------------|
| Save | Save the current configuration |
| Run now | Execute a backup immediately |
| Refresh | Reload settings and backup list |

### Status information

The status card displays information about scheduled backup operations:

| Field | Description |
|-------|-------------|
| Next run at | When the next backup is scheduled |
| Last run at | When the last backup was executed |
| Last status | Status of the last backup (success/error) |
| Last message | Details about the last operation |
| Last purge at | When old backups were last cleaned up |
| Last purge deleted | Number of old backups removed |

### Scheduled backups list

All scheduled backups are displayed in a table showing the backup date, size, Teampass version, and available actions.

To restore from a scheduled backup:

1. Click on a backup in the list to select it
2. The backup will be highlighted and shown in the restore section
3. Click `Prepare restore`

> 💡 Scheduled backups use the **instance encryption key** automatically. No manual key entry is required for restore.

### Using a different encryption key

If you are restoring a backup from a different Teampass instance (migration scenario), you may need to provide the original encryption key:

1. Select the backup to restore
2. Click `Use another key` link
3. Enter the encryption key from the original instance
4. Proceed with the restore


## Externalized backups

Externalized backups allow Teampass to copy encrypted backups to a destination outside the standard server backup storage.

### Configuration

Navigate to the `Externalized` tab to configure the external destination:

| Setting | Description |
|---------|-------------|
| Enable externalization | Turn externalized backups on or off |
| Destination | Local directory, SFTP, WebDAV, or S3 |
| Target directory / remote path / object prefix | Where externalized backup files are stored |
| Format | `.tpbackup` package or `Legacy encrypted SQL` |
| Include documents | Force the `.tpbackup` format and include supported document files |
| Run after scheduled backup | Queue an externalized backup after each successful scheduled backup |
| Schedule externalized backups | Run externalized backups using their own schedule |
| Frequency | `Daily`, `Weekly`, or `Monthly` |
| Time | Time of day to run the externalized backup |
| Retention days | Number of days to keep old externalized backups (1-3650) |
| Max files | Maximum number of externalized backups to keep |
| Attempts | Number of retry attempts if the backup fails |
| Delay between attempts | Delay between retries, in seconds |

> 💡 `Run after scheduled backup` and `Schedule externalized backups` are mutually exclusive. Enabling one trigger disables the other because both use the same external destination, backup list, status, and retention settings.

### Destination types

| Destination | Required information |
|-------------|----------------------|
| Local directory / mounted share | A writable directory outside Teampass directories |
| SFTP | Host, port, username, authentication method, and writable remote path |
| WebDAV | WebDAV URL, username, password, and writable remote path |
| S3 | Endpoint, region, bucket, access key, secret key, path-style option, and optional object prefix |

For SFTP, the remote directory must already exist. For WebDAV, the remote path must already exist and be writable. For S3, the bucket must already exist and the credentials must allow listing, uploading, downloading, and deleting objects in the configured prefix.

> 💡 Leave the S3 endpoint empty when using AWS S3. Set it when using an S3-compatible provider.

### Buttons

| Button | Description |
|--------|-------------|
| Save | Save the current externalized configuration |
| Test | Check that the destination is reachable and writable |
| Run now | Queue an externalized backup immediately |
| Refresh | Reload settings, status, and backup list |

### Status information

The status card displays information about externalized backup operations:

| Field | Description |
|-------|-------------|
| Last test | When the destination was last tested |
| Next run | When the next externalized backup is scheduled |
| Last run | When the last externalized backup was queued |
| Last completed | When the last externalized backup completed |
| Last status | Status of the last operation |
| Last message | Details about the last operation |
| Last file | Name of the last externalized backup |
| Last file size | Size of the last externalized backup |
| Last purge | When old externalized backups were last cleaned up |
| Last purge deleted | Number of old externalized backups removed |
| Configured format | Current externalized backup format |
| Configured destination | Current externalized destination path or prefix |

### Externalized backups list

All backups found in the configured externalized destination are displayed in a table with the same columns as scheduled backups.

Available actions for each backup:
- **Download**: Download the encrypted backup file
- **Delete**: Remove the backup from the externalized destination
- **Use for restore**: Select the backup for restore

To restore from an externalized backup:

1. Click on a backup in the externalized backups list
2. The backup will be highlighted and shown in the restore section
3. Click `Prepare restore`

> 💡 Externalized backups use the **instance encryption key** automatically. If the backup comes from another Teampass instance, click `Use another key` before restoring.

For remote destinations, Teampass temporarily stages the selected backup locally before starting the restore process.


## Recovery Package

The `Recovery Package` tab generates an encrypted emergency package containing sensitive recovery material such as the secret file, minimal configuration data, and the backup instance key.

1. Navigate to the `Recovery Package` tab
2. Enter and confirm a passphrase of at least 12 characters
3. Click `Generate and download`

> ⚠️ The Recovery Package is not a database backup. Store it offline, protect its passphrase, and do not send it to an automatic externalized destination.


## Storage usage

A progress bar in the page header shows the current disk usage for backup storage:

| Color | Usage level |
|-------|-------------|
| Green | Less than 75% |
| Yellow | 75% to 89% |
| Red | 90% or more |

> ⚠️ Monitor disk usage and adjust retention settings if storage becomes full.

Remote externalized destinations are not included in this local storage usage indicator.


## Best practices

1. **Regular backups**: Enable scheduled backups with daily frequency for critical installations
2. **Off-site storage**: Use externalized backups or periodically download backups and store them in a separate location
3. **Test restores**: Regularly test that backups can be restored successfully
4. **Secure keys**: Store encryption keys securely, separate from the backup files
5. **Monitor notifications**: Enable email reports to be alerted of backup failures
6. **Retention policy**: Balance storage space with your recovery requirements
7. **Destination tests**: Use the externalized destination test before relying on a remote target
8. **Recovery Package**: Generate a Recovery Package and keep it in a secure offline location


## Compatibility notes

When restoring a backup:

- The backup must be from a compatible Teampass version
- Backups from older versions may require an upgrade after restore
- Backups from significantly different versions may not be compatible
- `.tpbackup` packages may contain documents and require package support to be available on the target instance

If a version mismatch is detected, an error message will display the backup version and the expected version.


## Troubleshooting

### Backup fails

- Check available disk space
- Verify write permissions on the backup directory
- Check PHP error logs for detailed error messages

### Restore fails

- Verify the encryption key is correct
- Ensure the backup file is not corrupted
- Check that no other users are connected during restore
- Verify database connection settings

### Scheduled backups not running

- Ensure the background task handler is configured (cron job)
- Check that scheduled backups are enabled
- Verify the output directory exists and is writable

### Externalized destination test fails

- Verify the destination configuration and credentials
- Ensure the target directory, remote path, or S3 bucket already exists
- Check that the destination is writable
- For SFTP, ensure phpseclib is available
- For WebDAV, ensure Guzzle is available
- For S3, ensure the PHP cURL extension is enabled

### Externalized backups not running

- Ensure externalization is enabled
- If using `Run after scheduled backup`, check that the scheduled backup completed successfully
- If using the externalized schedule, check that it is enabled
- Ensure the background task handler is configured (cron job)
- Check the externalized status message for the latest error
