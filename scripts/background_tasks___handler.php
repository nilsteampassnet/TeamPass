<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      background_tasks___handler.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\Process\Process;
use TeampassClasses\ConfigManager\ConfigManager;

require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__ . '/taskLogger.php';

class BackgroundTasksHandler {
    private $settings;
    private $logger;
    private $maxParallelTasks;
    private $maxExecutionTime;
    private $batchSize;
    private $maxTimeBeforeRemoval;

    public function __construct(array $settings) {
        $this->settings = $settings;
        $this->logger = new TaskLogger($settings, LOG_TASKS_FILE);
        $this->maxParallelTasks = $settings['max_parallel_tasks'] ?? 2;
        $this->maxExecutionTime = $settings['task_maximum_run_time'] ?? 600;
        $this->batchSize = $settings['task_batch_size'] ?? 50;
        // Tasks history retention (seconds)
        // Prefer new setting `tasks_history_delay` (stored in seconds in DB),
        // fallback to legacy `history_duration` (days) if present, otherwise 15 days.
        $historyDelay = 0;

        if (isset($settings['tasks_history_delay']) === true) {
            $historyDelay = (int) $settings['tasks_history_delay'];
        } elseif (isset($settings['history_duration']) === true) {
            $historyDelay = (int) $settings['history_duration'] * 86400;
        }

        // Safety: if for any reason it is stored in days, convert to seconds.
        if ($historyDelay > 0 && $historyDelay < 86400) {
            $historyDelay = $historyDelay * 86400;
        }

        $this->maxTimeBeforeRemoval = $historyDelay > 0 ? $historyDelay : (15 * 86400);

    }

    /**
     * Main function to process background tasks
     */
    public function processBackgroundTasks() {
        // Prevent multiple concurrent executions
        if (!$this->acquireProcessLock()) {
            if (LOG_TASKS=== true) $this->logger->log('Process already running', 'INFO');
            return false;
        }

        try {
            $this->cleanupStaleTasks();
            $this->handleScheduledDatabaseBackup();
            $this->processTaskBatches();
            $this->performMaintenanceTasks();
        } catch (Exception $e) {
            if (LOG_TASKS=== true) $this->logger->log('Task processing error: ' . $e->getMessage(), 'ERROR');
        } finally {
            $this->releaseProcessLock();
        }
    }
    /**
     * Scheduler: enqueue a database_backup task when due.
     */
    private function handleScheduledDatabaseBackup(): void
    {
        $enabled = (int)$this->getSettingValue('bck_scheduled_enabled', '0');
        if ($enabled !== 1) {
            return;
        }

        $now = time();

        // Output dir
        $outputDir = (string)$this->getSettingValue('bck_scheduled_output_dir', '');
        if ($outputDir === '') {
            $baseFilesDir = (string)($this->settings['path_to_files_folder'] ?? (__DIR__ . '/../files'));
            $outputDir = rtrim($baseFilesDir, '/') . '/backups';
            $this->upsertSettingValue('bck_scheduled_output_dir', $outputDir);
        }

        // next_run_at
        $nextRunAt = (int)$this->getSettingValue('bck_scheduled_next_run_at', '0');
        if ($nextRunAt <= 0) {
            $nextRunAt = $this->computeNextBackupRunAt($now);
            $this->upsertSettingValue('bck_scheduled_next_run_at', (string)$nextRunAt);
            if (LOG_TASKS === true) $this->logger->log('backup scheduler initialized next_run_at=' . $nextRunAt, 'INFO');
            return;
        }

        if ($now < $nextRunAt) {
            return;
        }

        // Avoid duplicates: if a database_backup task is already pending or running, skip.
        $pending = (int)DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . prefixTable('background_tasks') . '
            WHERE process_type = %s
            AND is_in_progress IN (0,1)
            AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
            'database_backup'
        );

        if ($pending > 0) {
            if (LOG_TASKS === true) $this->logger->log('backup scheduler: a database_backup task is already pending/running', 'INFO');
            return;
        }

        // Enqueue task
        DB::insert(
            prefixTable('background_tasks'),
            [
                'created_at' => (string)$now,
                'process_type' => 'database_backup',
                'arguments' => json_encode(
                    [
                        'output_dir' => $outputDir,
                        'source' => 'scheduler',
                    ],
                    JSON_UNESCAPED_SLASHES
                ),
                'is_in_progress' => 0,
                'status' => 'new',
            ]
        );

    $this->upsertSettingValue('bck_scheduled_last_run_at', (string)$now);
    $this->upsertSettingValue('bck_scheduled_last_status', 'queued');
    $this->upsertSettingValue('bck_scheduled_last_message', 'Task enqueued by scheduler');

        // Compute next run
        $newNext = $this->computeNextBackupRunAt($now + 60);
        $this->upsertSettingValue('bck_scheduled_next_run_at', (string)$newNext);

        if (LOG_TASKS === true) $this->logger->log('backup scheduler: enqueued database_backup, next_run_at=' . $newNext, 'INFO');
    }

    /**
     * Compute next run timestamp based on settings:
     * - bck_scheduled_frequency: daily|weekly|monthly (default daily)
     * - bck_scheduled_time: HH:MM (default 02:00)
     * - bck_scheduled_dow: 1..7 (ISO, Mon=1) for weekly (default 1)
     * - bck_scheduled_dom: 1..31 for monthly (default 1)
     */
    
    private function getTeampassTimezoneName(): string
{
    // TeamPass stores timezone in teampass_misc: type='admin', intitule='timezone'
    $tz = DB::queryFirstField(
        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s LIMIT 1',
        'admin',
        'timezone'
    );

    return (is_string($tz) && $tz !== '') ? $tz : 'UTC';
}
    
    private function computeNextBackupRunAt(int $fromTs): int
    {
        // On se base sur la timezone PHP du serveur (simple et robuste)
        $tzName = $this->getTeampassTimezoneName();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }

        $freq = (string)$this->getSettingValue('bck_scheduled_frequency', 'daily');
        $timeStr = (string)$this->getSettingValue('bck_scheduled_time', '02:00');

        if (!preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
            $timeStr = '02:00';
        }
        [$hh, $mm] = array_map('intval', explode(':', $timeStr));

        $now = (new DateTimeImmutable('@' . $fromTs))->setTimezone($tz);
        $candidate = $now->setTime($hh, $mm, 0);

        if ($freq === 'weekly') {
            $targetDow = (int)$this->getSettingValue('bck_scheduled_dow', '1'); // ISO 1..7
            if ($targetDow < 1 || $targetDow > 7) $targetDow = 1;

            $currentDow = (int)$candidate->format('N');
            $delta = ($targetDow - $currentDow + 7) % 7;
            if ($delta === 0 && $candidate <= $now) {
                $delta = 7;
            }
            $candidate = $candidate->modify('+' . $delta . ' days');

        } elseif ($freq === 'monthly') {
            $dom = (int)$this->getSettingValue('bck_scheduled_dom', '1');
            if ($dom < 1) $dom = 1;
            if ($dom > 31) $dom = 31;

            $year = (int)$now->format('Y');
            $month = (int)$now->format('m');
            $daysInMonth = (int)$now->format('t');
            $day = min($dom, $daysInMonth);

            $candidate = $now->setDate($year, $month, $day)->setTime($hh, $mm, 0);
            if ($candidate <= $now) {
                $nextMonth = $now->modify('first day of next month');
                $year2 = (int)$nextMonth->format('Y');
                $month2 = (int)$nextMonth->format('m');
                $daysInMonth2 = (int)$nextMonth->format('t');
                $day2 = min($dom, $daysInMonth2);

                $candidate = $nextMonth->setDate($year2, $month2, $day2)->setTime($hh, $mm, 0);
            }

        } else {
            // daily
            if ($candidate <= $now) {
                $candidate = $candidate->modify('+1 day');
            }
        }

        return $candidate->getTimestamp();
    }

    /**
     * Read a setting from teampass_misc (type='settings', intitule=key).
     */
    private function getSettingValue(string $key, string $default = ''): string
    {
        $table = prefixTable('misc');

        // Schéma TeamPass classique: misc(type, intitule, valeur)
        $val = DB::queryFirstField(
            'SELECT valeur FROM ' . $table . ' WHERE type = %s AND intitule = %s LIMIT 1',
            'settings',
            $key
        );

        if ($val === null || $val === false || $val === '') {
            return $default;
        }

        return (string)$val;
    }

    /**
     * Upsert a setting into teampass_misc (type='settings', intitule=key).
     */
    private function upsertSettingValue(string $key, string $value): void
    {
        $table = prefixTable('misc');

        $exists = (int)DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE type = %s AND intitule = %s',
            'settings',
            $key
        );

        if ($exists > 0) {
            DB::update($table, ['valeur' => $value], 'type = %s AND intitule = %s', 'settings', $key);
        } else {
            DB::insert($table, ['type' => 'settings', 'intitule' => $key, 'valeur' => $value]);
        }

        // keep in memory too
        $this->settings[$key] = $value;
    }

    /**
     * Acquire a lock to prevent multiple instances of this script from running simultaneously.
     * @return bool
     */
    private function acquireProcessLock(): bool {
        $lockFile = empty(TASKS_LOCK_FILE) ? __DIR__.'/../files/teampass_background_tasks.lock' : TASKS_LOCK_FILE;
        
        $fp = fopen($lockFile, 'w');
        
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            return false;
        }
        
        fwrite($fp, (string)getmypid());
        return true;
    }

    /**
     * Release the lock file.
     */
    private function releaseProcessLock() {
        $lockFile = empty(TASKS_LOCK_FILE) ? __DIR__.'/../files/teampass_background_tasks.lock' : TASKS_LOCK_FILE;
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    /**
     * Cleanup stale tasks that have been running for too long or are marked as failed.
     */
    private function cleanupStaleTasks() {
        // Mark tasks as failed if they've been running too long
        DB::query(
            'UPDATE ' . prefixTable('background_tasks') . ' 
            SET is_in_progress = -1, 
                finished_at = %i, 
                status = "failed",
                error_message = "Task exceeded maximum execution time of '.$this->maxExecutionTime.' seconds"
            WHERE is_in_progress = 1 
            AND updated_at < %i',
            time(),
            time() - $this->maxExecutionTime
        );

        // Remove very old failed tasks
        DB::query(
            'DELETE t, st FROM ' . prefixTable('background_tasks') . ' t
            INNER JOIN ' . prefixTable('background_subtasks') . ' st ON (t.increment_id = st.task_id)
            WHERE t.finished_at > 0
              AND t.finished_at < %i
              AND t.status = %s',
            time() - $this->maxTimeBeforeRemoval,
            'failed'
        );
    }

    /**
     * Process batches of tasks.
     * This method fetches tasks from the database and processes them in parallel.
     */
    private function processTaskBatches() {
        $runningTasks = $this->countRunningTasks();
        
        // Check if the maximum number of parallel tasks is reached
        if ($runningTasks >= $this->maxParallelTasks) {
            if (LOG_TASKS=== true) $this->logger->log('Wait ... '.$runningTasks.' out of '.$this->maxParallelTasks.' are already running ', 'INFO');
            return;
        }

        $availableSlotsCount = $this->maxParallelTasks - $runningTasks;

        // Fetch next batch of tasks
        $tasks = DB::query(
            'SELECT increment_id, process_type, arguments 
            FROM ' . prefixTable('background_tasks') . '
            WHERE is_in_progress = 0 
            AND (finished_at IS NULL OR finished_at = "")
            ORDER BY increment_id ASC
            LIMIT %i',
            min($this->batchSize, $availableSlotsCount)
        );

        foreach ($tasks as $task) {
            if (LOG_TASKS=== true) $this->logger->log('Launching '.$task['increment_id'], 'INFO');
            $this->processIndividualTask($task);
        }
    }

    /**
     * Process an individual task.
     * This method updates the task status in the database and starts a new process for the task.
     * @param array $task The task to process.
     */
    private function processIndividualTask(array $task) {
        if (LOG_TASKS=== true)  $this->logger->log('Starting task: ' . print_r($task, true), 'INFO');

        // Store progress in the database        
        DB::update(
            prefixTable('background_tasks'),
            [
                'is_in_progress' => 1,
                'started_at' => time(),
                'updated_at' => time(),
                'status' => 'in_progress'
            ],
            'increment_id = %i',
            $task['increment_id']
        );

        // Prepare process
        $cmd = sprintf(
            '%s %s %d %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/background_tasks___worker.php'),
            (int) $task['increment_id'],
            escapeshellarg((string) $task['process_type']),
            escapeshellarg((string) $task['arguments'])
        );

        $process = Process::fromShellCommandline($cmd);

        // Launch process
        try {
            $process->setTimeout($this->maxExecutionTime);
            $process->mustRun();

        } catch (Throwable $e) {
            // If Symfony Process cannot spawn, fallback to exec()
            $msg = $e->getMessage();
            $last = error_get_last();
            if (is_array($last)) {
                $msg .= ' | last_error=' . json_encode($last);
            }

            if (strpos($msg, 'Unable to launch a new process') !== false) {
                $out = [];
                $rc = 0;

                // fallback run (blocking)
                exec($cmd . ' 2>&1', $out, $rc);

                if ($rc === 0) {
                    // Worker ran successfully and updated the DB itself
                    if (LOG_TASKS === true) $this->logger->log('Fallback exec succeeded for task ' . $task['increment_id'], 'INFO');
                    return;
                }

                $msg .= ' | fallback_exit=' . $rc . ' | fallback_out=' . implode("\n", array_slice($out, -30));
            }

            if (LOG_TASKS=== true) $this->logger->log('Error launching task: ' . $msg, 'ERROR');

            DB::update(
                prefixTable('background_tasks'),
                [
                    'is_in_progress' => -1,
                    'finished_at' => time(),
                    'status' => 'failed',
                    'error_message' => $msg
                ],
                'increment_id = %i',
                $task['increment_id']
            );
        }
    }

    /**
     * Count the number of currently running tasks.
     * @return int The number of running tasks.
     */
    private function countRunningTasks(): int {
        return DB::queryFirstField(
            'SELECT COUNT(*) 
            FROM ' . prefixTable('background_tasks') . ' 
            WHERE is_in_progress = 1'
        );
    }

    /**
     * Perform maintenance tasks.
     * This method cleans up old items, expired tokens, and finished tasks.
     */
    private function performMaintenanceTasks() {
        $this->cleanMultipleItemsEdition();
        $this->handleItemTokensExpiration();
        $this->cleanOldFinishedTasks();
        $this->cleanOldImportFiles();
    }

    /**
     * Clean up multiple items edition.
     * This method removes duplicate entries in the items_edition table.
     */
    private function cleanMultipleItemsEdition() {
        DB::query(
            'DELETE i1 FROM ' . prefixTable('items_edition') . ' i1
            JOIN (
                SELECT user_id, item_id, MIN(timestamp) AS oldest_timestamp
                FROM ' . prefixTable('items_edition') . '
                GROUP BY user_id, item_id
            ) i2 ON i1.user_id = i2.user_id AND i1.item_id = i2.item_id
            WHERE i1.timestamp > i2.oldest_timestamp'
        );
    }

    /**
     * Handle item tokens expiration.
     * This method removes expired tokens from the items_edition table.
     */
    private function handleItemTokensExpiration() {
        DB::query(
            'DELETE FROM ' . prefixTable('items_edition') . '
            WHERE timestamp < %i',
            time() - ($this->settings['delay_item_edition'] * 60 ?: EDITION_LOCK_PERIOD)
        );
    }

    /**
     * Clean up old finished tasks.
     * This method removes tasks that have been completed for too long.
     */
    private function cleanOldFinishedTasks() {
        // Timestamp cutoff for removal
        $cutoffTimestamp = time() - $this->maxTimeBeforeRemoval;
    
        // 1. Get all finished tasks older than the cutoff timestamp
        //    and that are not in progress
        $tasks = DB::query(
            'SELECT increment_id FROM ' . prefixTable('background_tasks') . '
            WHERE status = %s AND is_in_progress = %i AND finished_at < %i',
            'completed',
            -1,
            $cutoffTimestamp
        );
        
        if (empty($tasks)) {
            return;
        }
    
        $taskIds = array_column($tasks, 'increment_id');
    
        // 2. Delete all subtasks related to these tasks
        DB::query(
            'DELETE FROM ' . prefixTable('background_subtasks') . '
            WHERE task_id IN %ls',
            $taskIds
        );
    
        // 3. Delete the tasks themselves
        DB::query(
            'DELETE FROM ' . prefixTable('background_tasks') . '
            WHERE increment_id IN %ls',
            $taskIds
        );
    
        if (LOG_TASKS=== true) $this->logger->log('Old finished tasks cleaned: ' . count($taskIds), 'INFO');
    }

    /**
     * Clean old temporary import files that were not deleted after failed imports
     * Removes files older than a specified threshold from both filesystem and database
     *
     * @return void
     */
    private function cleanOldImportFiles(): void
    {
        try {
            // Define threshold for old files (1 hour by default)
            $thresholdTimestamp = time() - (1 * 3600);
            
            // Statistics for logging
            $stats = [
                'total_found' => 0,
                'files_deleted' => 0,
                'db_entries_deleted' => 0,
                'errors' => 0
            ];

            // Retrieve all temp_file entries from database
            $tempFiles = DB::query(
                'SELECT increment_id, intitule, valeur 
                FROM %l 
                WHERE type = %s',
                'teampass_misc',
                'temp_file'
            );

            $stats['total_found'] = count($tempFiles);

            if (empty($tempFiles)) {
                if (LOG_TASKS=== true) $this->logger->log('TeamPass Background Task: No temporary import files found in database', 'INFO');
                return;
            }

            foreach ($tempFiles as $fileEntry) {
                $entryId = (int) $fileEntry['increment_id'];
                $timestamp = (int) $fileEntry['intitule'];
                $fileName = (string) $fileEntry['valeur'];

                // Validate timestamp format
                if ($timestamp <= 0) {
                    if (LOG_TASKS=== true) $this->logger->log("TeamPass Background Task: Invalid timestamp for temp_file entry ID {$entryId}: {$timestamp}", 'INFO');
                    $stats['errors']++;
                    continue;
                }

                // Check if file is old enough to be deleted
                if ($timestamp > $thresholdTimestamp) {
                    // File is still recent, skip deletion
                    continue;
                }

                // Construct full file path
                $filePath = './files/' . $fileName;

                // Attempt to delete file from filesystem
                $fileDeleted = $this->deleteImportFile($filePath, $entryId);
                
                if ($fileDeleted) {
                    $stats['files_deleted']++;
                }

                // Delete database entry regardless of file deletion result
                // (file might already be deleted manually)
                try {
                    DB::delete(
                        'teampass_misc',
                        'increment_id = %i',
                        $entryId
                    );
                    $stats['db_entries_deleted']++;
                } catch (Exception $e) {
                    if (LOG_TASKS=== true) $this->logger->log(
                        "TeamPass Background Task: Failed to delete temp_file entry ID {$entryId} from database: " . $e->getMessage(),
                        'INFO'
                    );
                    $stats['errors']++;
                }
            }

            // Log final statistics
            if (LOG_TASKS=== true) $this->logger->log(
                sprintf(
                    'TeamPass Background Task: Import files cleanup completed - Found: %d, Files deleted: %d, DB entries deleted: %d, Errors: %d',
                    $stats['total_found'],
                    $stats['files_deleted'],
                    $stats['db_entries_deleted'],
                    $stats['errors']
                )
                , 'INFO'
            );

        } catch (Exception $e) {
            if (LOG_TASKS=== true) $this->logger->log(
                "TeamPass Background Task: Critical error in cleanOldImportFiles(): " . $e->getMessage(),
                'INFO'
            );
        }
    }

    /**
     * Delete a single import file with proper error handling
     *
     * @param string $filePath Path to the file to delete
     * @param int $entryId Database entry ID (for logging purposes)
     * @return bool True if file was deleted successfully or doesn't exist, false on error
     */
    private function deleteImportFile(string $filePath, int $entryId): bool
    {
        // Check if file exists
        if (!file_exists($filePath)) {
            // File already deleted or never existed, consider as success
            return true;
        }

        // Verify it's actually a file (not a directory)
        if (!is_file($filePath)) {
            if (LOG_TASKS=== true) $this->logger->log(
                "TeamPass Background Task: Path is not a file (entry ID {$entryId}): {$filePath}",
                'INFO'
            );
            return false;
        }

        // Check write permissions
        if (!is_writable($filePath)) {
            if (LOG_TASKS=== true) $this->logger->log(
                "TeamPass Background Task: File is not writable, cannot delete (entry ID {$entryId}): {$filePath}",
                'INFO'
            );
            return false;
        }

        // Attempt deletion
        if (unlink($filePath) === false) {
            if (LOG_TASKS=== true) $this->logger->log(
                "TeamPass Background Task: Failed to delete file (entry ID {$entryId}): {$filePath}",
                'INFO'
            );
            return false;
        }

        return true;
    }
}



// Main execution
try {
    $configManager = new ConfigManager();
    $settings = $configManager->getAllSettings();
    
    $tasksHandler = new BackgroundTasksHandler($settings);
    $tasksHandler->processBackgroundTasks();
} catch (Exception $e) {
    error_log('Teampass Background Tasks Error: ' . $e->getMessage());
}
