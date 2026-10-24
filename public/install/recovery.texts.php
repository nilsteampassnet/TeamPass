<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * English installer/recovery text, independent from application language settings.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery.texts.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

return [
    'drp_title' => 'Restore a TeamPass instance',
    'drp_intro' => 'Restore a backup and its Recovery Package on a fresh deployment. Compatible database upgrades are applied before activation.',
    'drp_operator' => 'Operator code',
    'drp_enable_hint' => 'On this server, run the following command as the PHP service account, then enter the one-hour code here.',
    'drp_unlock' => 'Open the recovery assistant',
    'drp_kit' => 'Recovery Package (.tprecovery)',
    'drp_kit_password' => 'Recovery Package passphrase',
    'drp_backup' => 'Complete backup (.tpbackup)',
    'drp_backup_password' => 'Backup encryption key (leave empty to use the key in the Recovery Package)',
    'drp_server_file' => 'Or enter a filename already placed in storage/recovery/incoming/',
    'drp_target' => 'Empty destination database',
    'drp_host' => 'Database host',
    'drp_port' => 'Port',
    'drp_database' => 'Database name',
    'drp_user' => 'Database user',
    'drp_password' => 'Database password',
    'drp_url' => 'Restored HTTPS address',
    'drp_prepare' => 'Check and prepare restoration',
    'drp_preparing' => 'Checking the archives and destination…',
    'drp_ready' => 'Step 1 — Preliminary checks passed. Run the complete command below on the server to restore, upgrade and verify the database. Wait for it to finish, then click Refresh progress.',
    'drp_verified' => 'Step 2 — Database and decryption checks passed. Run the complete command below to publish the restored files and configuration, then click Refresh progress.',
    'drp_complete' => 'Step 3 — The instance is restored in maintenance. Check access, permissions, MFA, documents and HTTPS, then run the command below to acknowledge the checks and reopen the instance.',
    'drp_counts' => '%s database tables · %s documents',
    'drp_running' => 'Restoration is in progress. Refresh the status before continuing.',
    'drp_failed' => 'Restoration stopped. Check the diagnostic and recovery documentation before preparing another empty destination.',
    'drp_activate_hint' => 'After the terminal reports that the database is verified, click Refresh progress to display the activation command. TeamPass stays in maintenance until the final operational checks are acknowledged.',
    'drp_status' => 'Refresh progress',
    'drp_error' => 'The recovery operation stopped. Diagnostic:',
    'drp_transport' => 'Use HTTPS or a local SSH tunnel to submit recovery secrets.',
    'drp_scope' => 'Requires a fresh deployment, an empty dedicated database, the original Recovery Package and a complete backup. Database credentials are for the destination.',
    'drp_back' => 'Back to installation',
    'drp_restore_choice' => 'Recover an existing instance from a backup and Recovery Package',
    'drp_pending' => 'A recovery is prepared or running. Continue through the recovery assistant instead of starting a new installation.',
];
