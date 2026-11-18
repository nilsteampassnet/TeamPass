<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Resolve the persistent restore hold independently of database settings. */
function tpBackupRestoreGuardPath(): string
{
    return (defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE : dirname(__DIR__, 2) . '/storage')
        . '/logs/teampass_restore.pending';
}

/** A crashed or failed import stays closed until an operator completes recovery. */
function tpBackupRestoreIsInProgress(): bool
{
    clearstatcache(true, tpBackupRestoreGuardPath());
    return file_exists(tpBackupRestoreGuardPath()) || is_link(tpBackupRestoreGuardPath());
}
