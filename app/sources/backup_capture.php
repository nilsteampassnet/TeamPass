<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file backup_capture.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use TeampassClasses\ConfigManager\ConfigManager;

/** Share one connection-scoped lock between backup captures and destructive restores. */
function tpBackupDatabaseLockName(): string
{
    return 'tp-backup-' . substr(hash('sha256', DB::getMDB()->dbName . ':' . prefixTable('misc')), 0, 48);
}

/** Keep a package's database reads in one snapshot and maintenance active until publication. */
final class TeamPassBackupCapture
{
    private static int $depth = 0;
    private static string $lock = '';
    private static ?string $previousMaintenance = null;
    private static bool $transaction = false;
    private static ?BackgroundTaskLock $tasksLock = null;
    private bool $acquired = false;

    /** Acquire the capture once; a package and its inner SQL dump share the same scope. */
    public function __construct(?bool $holdBackgroundTasks = null)
    {
        if (self::$depth > 0) {
            self::$depth++;
            $this->acquired = true;
            return;
        }
        // START TRANSACTION must never implicitly commit a caller's changes.
        if (DB::getMDB()->nested_transactions_count !== 0) {
            throw new RuntimeException('A backup must start outside an application transaction.');
        }
        // Scheduled backups are already exclusive workers; a manual web capture must
        // also wait for the handler's pool to drain before reading filesystem documents.
        if (($holdBackgroundTasks ?? PHP_SAPI !== 'cli') && defined('TASKS_LOCK_FILE')) {
            require_once __DIR__ . '/../scripts/backgroundTaskLock.php';
            self::$tasksLock = new BackgroundTaskLock(TASKS_LOCK_FILE);
            if (!self::$tasksLock->acquire()) {
                $failureReason = self::$tasksLock->getFailureReason();
                self::$tasksLock = null;
                if (in_array($failureReason, ['open_failed', 'write_failed', 'lock_failed'], true)) {
                    throw new RuntimeException('The background-task lock cannot be opened or written. Check storage/logs ownership and permissions for PHP and cron.');
                }
                throw new RuntimeException('Background tasks are running. Retry the backup after they finish.');
            }
        }
        $lock = tpBackupDatabaseLockName();
        if ((string) DB::queryFirstField('SELECT GET_LOCK(%s, 0)', $lock) !== '1') {
            self::$tasksLock?->release();
            self::$tasksLock = null;
            throw new RuntimeException('Another database backup is already running.');
        }
        self::$lock = $lock;
        try {
            $row = DB::queryFirstRow('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule=%s AND type=%s',
                'maintenance_mode', 'admin');
            if (!is_array($row) || !isset($row['valeur'])) {
                throw new RuntimeException('Maintenance state is unavailable.');
            }
            self::$previousMaintenance = (string) $row['valeur'];
            DB::update(prefixTable('misc'), ['valeur' => '1', 'updated_at' => time()],
                'intitule=%s AND type=%s', 'maintenance_mode', 'admin');
            self::invalidateSettings();
            DB::query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
            self::$transaction = true;
            self::$depth = 1;
            $this->acquired = true;
        } catch (Throwable $exception) {
            self::release();
            throw new RuntimeException('Could not establish a consistent database backup snapshot.');
        }
    }

    /** Release the snapshot before restoring maintenance, including error and early-return paths. */
    public function __destruct()
    {
        if ($this->acquired && --self::$depth === 0) {
            self::release();
        }
    }

    private static function release(): void
    {
        try {
            if (self::$transaction) {
                DB::query('ROLLBACK');
            }
            if (self::$previousMaintenance !== null) {
                DB::update(prefixTable('misc'), ['valeur' => self::$previousMaintenance, 'updated_at' => time()],
                    'intitule=%s AND type=%s', 'maintenance_mode', 'admin');
                self::invalidateSettings();
            }
        } catch (Throwable $exception) {
            // Keep maintenance enabled on a database failure. Never log driver diagnostics.
            error_log('TeamPass backup: could not release the capture maintenance state.');
        } finally {
            if (self::$lock !== '') {
                try {
                    DB::queryFirstField('SELECT RELEASE_LOCK(%s)', self::$lock);
                } catch (Throwable $exception) {
                    error_log('TeamPass backup: could not release the capture lock.');
                }
            }
            self::$lock = '';
            self::$previousMaintenance = null;
            self::$transaction = false;
            self::$depth = 0;
            self::$tasksLock?->release();
            self::$tasksLock = null;
        }
    }

    private static function invalidateSettings(): void
    {
        if (class_exists(ConfigManager::class)) {
            ConfigManager::invalidateCache();
        }
    }
}
