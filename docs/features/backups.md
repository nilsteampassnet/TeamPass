<!-- docs/features/backups.md -->


## Generalities

Teampass provides a complete backup and restore solution to protect your password database. Two modes are available:

- **On the fly**: Manual backup and restore operations
- **Scheduled**: Automated backups with configurable frequency and retention

All backups are encrypted using a passphrase to ensure data security.

> ⚠️ Only administrators have access to the backup functionality.


## On the fly backup

### Performing a backup

The on-the-fly backup allows you to create an immediate backup of your database.

1. Navigate to `Backups` in the left menu
2. Stay on the `On the fly` tab
3. Enter an **encryption key** (or click the random generator button to create one)
4. Click `Perform backup`

> 💡 Store your encryption key in a safe place. Without it, you will not be able to restore the backup.

The backup file will be stored on the server and will appear in the **Server backups** table below.

### Server backups list

All on-the-fly backups stored on the server are displayed in a table showing:

| Column | Description |
|--------|-------------|
| Date | When the backup was created |
| Size | File size of the backup |
| Teampass version | Version of Teampass that created the backup |

Available actions for each backup:
- **Download**: Download the backup file to your local machine
- **Delete**: Remove the backup from the server

### Restoring from a backup

To restore your database from a backup:

1. Navigate to `Backups` in the left menu
2. In the **Restore** section, enter the **encryption key** used during backup
3. Select a backup file:
   - **From server**: Click on a backup in the server backups table
   - **From file**: Click `Choose file` to upload a `.sql` backup file
4. Click `Perform restore`

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

### Email notifications

You can enable email reports for scheduled backups:

| Setting | Description |
|---------|-------------|
| Enable email report | Send email notifications about backup status |
| Only on failures | Only send emails when backups fail |

> 💡 Email settings must be configured in Teampass options for notifications to work.

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

All scheduled backups are displayed in a table with the same columns as on-the-fly backups.

To restore from a scheduled backup:

1. Click on a backup in the list to select it
2. The backup will be highlighted and shown in the restore section
3. Click `Perform restore`

> 💡 Scheduled backups use the **instance encryption key** automatically. No manual key entry is required for restore.

### Using a different encryption key

If you are restoring a backup from a different Teampass instance (migration scenario), you may need to provide the original encryption key:

1. Select the backup to restore
2. Click `Use another key` link
3. Enter the encryption key from the original instance
4. Proceed with the restore


## Storage usage

A progress bar in the page header shows the current disk usage for backup storage:

| Color | Usage level |
|-------|-------------|
| Green | Less than 75% |
| Yellow | 75% to 89% |
| Red | 90% or more |

> ⚠️ Monitor disk usage and adjust retention settings if storage becomes full.


## Best practices

1. **Regular backups**: Enable scheduled backups with daily frequency for critical installations
2. **Off-site storage**: Periodically download backups and store them in a separate location
3. **Test restores**: Regularly test that backups can be restored successfully
4. **Secure keys**: Store encryption keys securely, separate from the backup files
5. **Monitor notifications**: Enable email reports to be alerted of backup failures
6. **Retention policy**: Balance storage space with your recovery requirements


## Compatibility notes

When restoring a backup:

- The backup must be from a compatible Teampass version
- Backups from older versions may require an upgrade after restore
- Backups from significantly different versions may not be compatible

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
