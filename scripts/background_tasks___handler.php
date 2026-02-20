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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

use Symfony\Component\Process\Process;
use TeampassClasses\ConfigManager\ConfigManager;

require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__ . '/taskLogger.php';

class BackgroundTasksHandler {
    private array $settings;
    private TaskLogger $logger;
    private int $maxParallelTasks;
    private int $maxExecutionTime;
    private int $batchSize;
    private int $maxTimeBeforeRemoval;
    private mixed $lockFileHandle = null;
    /** @var array<int, array{process: Process, task: array<string, mixed>, resourceKey: ?string}> Running process pool */
    private array $pool = [];
    private string $triggerFile;

    public function __construct(array $settings) {
        $this->settings = $settings;
        $this->logger = new TaskLogger($settings, LOG_TASKS_FILE);
        $this->maxParallelTasks = (int) ($settings['max_parallel_tasks'] ?? 2);
        $this->maxExecutionTime = (int) ($settings['task_maximum_run_time'] ?? 600);
        $this->batchSize = (int) ($settings['task_batch_size'] ?? 50);
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

        // Initialize trigger file path (default: files/ directory, same as lock file)
        $this->triggerFile = defined('TASKS_TRIGGER_FILE') && TASKS_TRIGGER_FILE !== ''
            ? TASKS_TRIGGER_FILE
            : __DIR__ . '/../files/teampass_background_tasks.trigger';
    }

    /**
     * Main function to process background tasks
     */
    public function processBackgroundTasks(): bool {
        // Prevent multiple concurrent executions
        if (!$this->acquireProcessLock()) {
            if (LOG_TASKS === true) $this->logger->log('Process already running', 'INFO');
            return false;
        }

        try {
            $this->cleanupStaleTasks();
            $this->handleScheduledDatabaseBackup();
            $this->handleScheduledInactiveUsersMgmt();
            $this->drainTaskPool();
            $this->performMaintenanceTasks();
        } catch (Exception $e) {
            if (LOG_TASKS === true) $this->logger->log('Task processing error: ' . $e->getMessage(), 'ERROR');
        } finally {
            $this->stopRunningProcesses();
            $this->releaseProcessLock();
        }
        return true;
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
        $pending = intval(DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . prefixTable('background_tasks') . '
            WHERE process_type = %s
            AND is_in_progress IN (0,1)
            AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
            'database_backup'
        ));

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
     * Scheduler: enqueue an inactive_users_housekeeping task when due (daily at a fixed time).
     */
    private function handleScheduledInactiveUsersMgmt(): void
    {
        $enabled = (int)$this->getSettingValue('inactive_users_mgmt_enabled', '0');
        if ($enabled !== 1) {
            return;
        }

        $timeStr = (string)$this->getSettingValue('inactive_users_mgmt_time', '02:00');
        if (!preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
            $timeStr = '02:00';
        }

        $now = time();
        $nextRunAt = (int)$this->getSettingValue('inactive_users_mgmt_next_run_at', '0');

        if ($nextRunAt <= 0) {
            $nextRunAt = $this->computeNextDailyRunAt($now, $timeStr);
            $this->upsertSettingValue('inactive_users_mgmt_next_run_at', (string)$nextRunAt);
            return;
        }

        if ($now < $nextRunAt) {
            return;
        }

        $pending = intval(DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . prefixTable('background_tasks') . '
            WHERE process_type = %s
            AND is_in_progress IN (0,1)
            AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
            'inactive_users_housekeeping'
        ));
        if ($pending > 0) {
            $newNext = $this->computeNextDailyRunAt($now + 60, $timeStr);
            $this->upsertSettingValue('inactive_users_mgmt_next_run_at', (string)$newNext);
            return;
        }

        DB::insert(
            prefixTable('background_tasks'),
            [
                'created_at' => (string)$now,
                'process_type' => 'inactive_users_housekeeping',
                'arguments' => json_encode(['source' => 'scheduler'], JSON_UNESCAPED_SLASHES),
                'is_in_progress' => 0,
                'status' => 'new',
            ]
        );

        $this->upsertSettingValue('inactive_users_mgmt_last_run_at', (string)$now);
        $this->upsertSettingValue('inactive_users_mgmt_last_status', 'queued');
        $this->upsertSettingValue('inactive_users_mgmt_last_message', 'inactive_users_mgmt_msg_task_enqueued');

        $newNext = $this->computeNextDailyRunAt($now + 60, $timeStr);
        $this->upsertSettingValue('inactive_users_mgmt_next_run_at', (string)$newNext);
    }

    /**
     * Compute next daily run timestamp (HH:MM) using TeamPass timezone stored in teampass_misc.
     */
    private function computeNextDailyRunAt(int $fromTs, string $hhmm): int
    {
        $tzName = $this->getTeampassTimezoneName();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable) {
            $tz = new DateTimeZone('UTC');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
            $hhmm = '02:00';
        }
        [$hh, $mm] = array_map('intval', explode(':', $hhmm));

        $now = (new DateTimeImmutable('@' . $fromTs))->setTimezone($tz);
        $candidate = $now->setTime($hh, $mm, 0);

        if ($candidate->getTimestamp() <= $fromTs) {
            $candidate = $candidate->modify('+1 day')->setTime($hh, $mm, 0);
        }

        return (int) $candidate->getTimestamp();
    }

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
    
    /**
     * Compute next run timestamp based on settings:
     * - bck_scheduled_frequency: daily|weekly|monthly (default daily)
     * - bck_scheduled_time: HH:MM (default 02:00)
     * - bck_scheduled_dow: 1..7 (ISO, Mon=1) for weekly (default 1)
     * - bck_scheduled_dom: 1..31 for monthly (default 1)
     */
    private function computeNextBackupRunAt(int $fromTs): int
    {
        $tzName = $this->getTeampassTimezoneName();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable) {
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

        return strval($val);
    }

    /**
     * Upsert a setting into teampass_misc (type='settings', intitule=key).
     */
    private function upsertSettingValue(string $key, string $value): void
    {
        $table = prefixTable('misc');

        $exists = intval(DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE type = %s AND intitule = %s',
            'settings',
            $key
        ));

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
        if ($fp === false) {
            return false;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        fwrite($fp, (string)getmypid());
        $this->lockFileHandle = $fp;
        return true;
    }

    /**
     * Release the lock file.
     */
    private function releaseProcessLock(): void {
        if ($this->lockFileHandle !== null) {
            flock($this->lockFileHandle, LOCK_UN);
            fclose($this->lockFileHandle);
            $this->lockFileHandle = null;
        }

        $lockFile = empty(TASKS_LOCK_FILE) ? __DIR__.'/../files/teampass_background_tasks.lock' : TASKS_LOCK_FILE;
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    /**
     * Check if a trigger file exists, indicating new urgent tasks were added.
     * If found, the trigger file is consumed (deleted) and returns true.
     *
     * @return bool True if trigger file was found and consumed
     */
    private function checkAndConsumeTrigger(): bool
    {
        if (!file_exists($this->triggerFile)) {
            return false;
        }

        // Read the timestamp for logging purposes
        $triggerTime = @file_get_contents($this->triggerFile);

        // Delete the trigger file (consume it)
        @unlink($this->triggerFile);

        if (LOG_TASKS === true) {
            $this->logger->log(
                'Trigger file detected (created at ' . $triggerTime . '), extending drain time for new tasks',
                'INFO'
            );
        }

        return true;
    }

    /**
     * Cleanup stale tasks that have been running for too long or are marked as failed.
     */
    private function cleanupStaleTasks(): void {
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
     * Drain the task pool: launch tasks in parallel, poll for completion,
     * refill slots, and repeat until no pending tasks remain or time limit is reached.
     *
     * The drain time can be extended if a trigger file is detected, indicating
     * that new urgent tasks have been added by triggerBackgroundHandler().
     */
    private function drainTaskPool(): void {
        $startTime = time();
        $maxDrainTime = (int)($this->settings['tasks_max_drain_time'] ?? 55);
        $launchingEnabled = true;
        $loopCount = 0;
        $triggerCheckInterval = 10; // Check for trigger every 10 loops (~1 second)

        while (true) {
            $loopCount++;

            // 1. Poll completed processes
            $this->pollCompletedProcesses();

            // 2. Check for trigger file periodically (new urgent tasks notification)
            // This allows extending drain time when new tasks are added mid-execution
            if ($loopCount % $triggerCheckInterval === 0) {
                if ($this->checkAndConsumeTrigger()) {
                    // Reset drain timer to process new urgent tasks
                    $startTime = time();
                    $launchingEnabled = true;
                }
            }

            // 3. Fill slots with new tasks (if still within drain time)
            if ($launchingEnabled) {
                $this->fillPoolSlots();
            }

            // 4. Nothing running and nothing to launch → done
            if (empty($this->pool)) {
                if (!$launchingEnabled || $this->countPendingTasks() === 0) {
                    break;
                }
            }

            // 5. Check drain time limit (stop launching, but wait for running processes)
            if ($launchingEnabled && (time() - $startTime) >= $maxDrainTime) {
                $launchingEnabled = false;
                if (LOG_TASKS === true) {
                    $pending = $this->countPendingTasks();
                    $this->logger->log(
                        "Drain time limit reached ({$maxDrainTime}s), waiting for "
                        . count($this->pool) . " running, {$pending} pending deferred",
                        'INFO'
                    );
                }
                if (empty($this->pool)) break;
            }

            // 6. Short pause before next poll
            usleep(100000); // 100ms
        }
    }

    /**
     * Poll pool for completed or timed-out processes and handle their results.
     */
    private function pollCompletedProcesses(): void {
        foreach ($this->pool as $taskId => $entry) {
            $process = $entry['process'];

            // Check per-process timeout
            try {
                $process->checkTimeout();
            } catch (Throwable $e) {
                $this->markTaskFailed($taskId, 'Process timeout: ' . $e->getMessage());
                try { $process->stop(5); } catch (Throwable) {}
                unset($this->pool[$taskId]);
                continue;
            }

            // Check if process finished
            if (!$process->isRunning()) {
                $this->handleProcessCompletion($entry);
                unset($this->pool[$taskId]);
            }
        }
    }

    /**
     * Fill available pool slots with compatible pending tasks.
     * Respects exclusive task rules and resource key conflict detection.
     */
    private function fillPoolSlots(): void {
        $maxPool = $this->maxParallelTasks;
        $availableSlots = $maxPool - count($this->pool);
        if ($availableSlots <= 0) return;

        // Don't launch anything while an exclusive task is running
        foreach ($this->pool as $entry) {
            if ($this->isExclusiveTask($entry['task']['process_type'])) {
                return;
            }
        }

        // Collect resource keys of running tasks
        $runningKeys = [];
        foreach ($this->pool as $entry) {
            if ($entry['resourceKey'] !== null) {
                $runningKeys[] = $entry['resourceKey'];
            }
        }

        // Fetch candidate tasks from DB
        $candidates = DB::query(
            'SELECT increment_id, process_type, arguments
            FROM ' . prefixTable('background_tasks') . '
            WHERE is_in_progress = 0
            AND (finished_at IS NULL OR finished_at = "")
            ORDER BY increment_id ASC
            LIMIT %i',
            $this->batchSize
        );

        foreach ($candidates as $task) {
            if ($availableSlots <= 0) break;

            $isExclusive = $this->isExclusiveTask($task['process_type']);
            $resourceKey = $this->getResourceKey($task);

            // Exclusive task: only launch if pool is completely empty
            if ($isExclusive && !empty($this->pool)) {
                continue;
            }

            // Resource key conflict: skip if same key is already running
            if ($resourceKey !== null && in_array($resourceKey, $runningKeys, true)) {
                if (LOG_TASKS === true) $this->logger->log(
                    'Task ' . strval($task['increment_id']) . ' deferred: resource conflict (' . $resourceKey . ')',
                    'INFO'
                );
                continue;
            }

            // Launch the task
            if (LOG_TASKS === true) $this->logger->log(
                'Launching task ' . strval($task['increment_id']) . ' (' . strval($task['process_type']) . ')',
                'INFO'
            );

            $process = $this->launchTask($task);
            if ($process !== null) {
                $this->pool[intval($task['increment_id'])] = [
                    'process' => $process,
                    'task' => $task,
                    'resourceKey' => $resourceKey,
                ];
                if ($resourceKey !== null) {
                    $runningKeys[] = $resourceKey;
                }
                $availableSlots--;

                // If we just launched an exclusive task, stop filling
                if ($isExclusive) break;
            }
        }
    }

    /**
     * Launch a task as a non-blocking subprocess.
     * Returns the Process object on success, null if the task was handled via fallback or failed.
     *
     * @param array $task Task row from the database.
     * @return Process|null
     */
    private function launchTask(array $task): ?Process {
        // Mark task as in progress
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

        // Build command
        $cmd = sprintf(
            '%s %s %d %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/background_tasks___worker.php'),
            (int) $task['increment_id'],
            escapeshellarg((string) $task['process_type']),
            escapeshellarg((string) $task['arguments'])
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout($this->maxExecutionTime);

        try {
            $process->start();
            return $process;
        } catch (Throwable $e) {
            // Symfony Process failed to start, try exec() fallback (blocking)
            if (LOG_TASKS === true) $this->logger->log(
                'Process::start() failed for task ' . $task['increment_id'] . ': ' . $e->getMessage() . ', trying exec fallback',
                'WARNING'
            );

            $out = [];
            $rc = 0;
            exec($cmd . ' 2>&1', $out, $rc);

            if ($rc === 0) {
                // Worker ran successfully via fallback and updated the DB itself
                if (LOG_TASKS === true) $this->logger->log(
                    'Fallback exec succeeded for task ' . $task['increment_id'],
                    'INFO'
                );
                return null; // Already completed, nothing to poll
            }

            $msg = $e->getMessage()
                . ' | fallback_exit=' . $rc
                . ' | fallback_out=' . implode("\n", array_slice($out, -30));

            $this->markTaskFailed((int) $task['increment_id'], $msg);
            return null;
        }
    }

    /**
     * Handle a completed process: check exit status and update DB if the worker didn't.
     *
     * @param array $entry Pool entry with 'process', 'task', and 'resourceKey'.
     */
    private function handleProcessCompletion(array $entry): void {
        $process = $entry['process'];
        $taskId = (int) $entry['task']['increment_id'];

        if ($process->isSuccessful()) {
            if (LOG_TASKS === true) $this->logger->log('Task ' . $taskId . ' completed successfully', 'INFO');
            return;
        }

        // Process exited with error - check if worker already updated the DB
        $currentStatus = DB::queryFirstField(
            'SELECT status FROM ' . prefixTable('background_tasks') . ' WHERE increment_id = %i',
            $taskId
        );

        // Worker may have already handled the failure via handleTaskFailure()
        if ($currentStatus !== 'in_progress') {
            if (LOG_TASKS === true) $this->logger->log(
                'Task ' . $taskId . ' process failed (exit ' . strval($process->getExitCode()) . ') but worker already set status=' . strval($currentStatus),
                'INFO'
            );
            return;
        }

        // Worker didn't update - mark as failed from handler side
        $msg = 'Process exited with code ' . strval($process->getExitCode());
        $stderr = $process->getErrorOutput();
        if (!empty($stderr)) {
            $msg .= ': ' . mb_substr($stderr, -500);
        }
        $this->markTaskFailed($taskId, $msg);
    }

    /**
     * Mark a task as failed in the database.
     *
     * @param int $taskId Task ID.
     * @param string $message Error message.
     */
    private function markTaskFailed(int $taskId, string $message): void {
        DB::update(
            prefixTable('background_tasks'),
            [
                'is_in_progress' => -1,
                'finished_at' => time(),
                'status' => 'failed',
                'error_message' => mb_substr($message, 0, 1000)
            ],
            'increment_id = %i',
            $taskId
        );
        if (LOG_TASKS === true) $this->logger->log('Task ' . $taskId . ' failed: ' . $message, 'ERROR');
    }

    /**
     * Gracefully stop all processes remaining in the pool (safety net for shutdown).
     */
    private function stopRunningProcesses(): void {
        foreach ($this->pool as $taskId => $entry) {
            $process = $entry['process'];
            if ($process->isRunning()) {
                try {
                    $process->stop(10);
                } catch (Throwable) {
                    // best effort
                }
                $this->markTaskFailed($taskId, 'Handler shutdown: process forcibly stopped');
            }
        }
        $this->pool = [];
    }

    /**
     * Count the number of pending tasks (not started and not finished).
     * @return int The number of pending tasks.
     */
    private function countPendingTasks(): int {
        return intval(DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . prefixTable('background_tasks') . '
            WHERE is_in_progress = 0
            AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)'
        ));
    }

    /**
     * Determine whether a task type must run exclusively (no other task in parallel).
     * Exclusive tasks perform bulk operations across many rows or have global side-effects.
     *
     * @param string $processType The process_type value from background_tasks.
     * @return bool
     */
    private function isExclusiveTask(string $processType): bool {
        return in_array($processType, [
            'create_user_keys',            // bulk delete + regenerate all sharekeys for a user
            'phpseclibv3_migration',       // iterates all sharekeys for a user
            'migrate_user_personal_items', // modifies personal item sharekeys
            'database_backup',             // disconnects users, heavy I/O
        ], true);
    }

    /**
     * Extract a resource key from a task for conflict detection.
     * Two tasks with the same resource key must not run in parallel.
     * Returns null for exclusive tasks (handled separately) and for
     * independent tasks that never conflict.
     *
     * @param array $task Task row with 'process_type' and 'arguments'.
     * @return string|null Resource key or null if not applicable.
     */
    private function getResourceKey(array $task): ?string {
        // Exclusive tasks are handled by isExclusiveTask(), no key needed
        if ($this->isExclusiveTask($task['process_type'])) {
            return null;
        }

        $args = json_decode($task['arguments'] ?? '', true);
        if (!is_array($args)) {
            $args = [];
        }

        switch ($task['process_type']) {
            case 'new_item':
            case 'item_copy':
            case 'update_item':
            case 'item_update_create_keys':
                return 'item:' . strval($args['item_id'] ?? 0);

            case 'user_build_cache_tree':
                return 'user:' . strval($args['user_id'] ?? 0);

            case 'send_email':
                // Emails never conflict with each other
                return null;

            default:
                // Unknown type: use task ID as unique key (never conflicts)
                return null;
        }
    }

    /**
     * Perform maintenance tasks.
     * This method cleans up old items, expired tokens, and finished tasks.
     */
    private function performMaintenanceTasks(): void {
        $this->cleanMultipleItemsEdition();
        $this->handleItemTokensExpiration();
        $this->cleanOldFinishedTasks();
        $this->cleanOldImportFiles();
    }

    /**
     * Clean up multiple items edition.
     * This method removes duplicate entries in the items_edition table.
     */
    private function cleanMultipleItemsEdition(): void {
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
    private function handleItemTokensExpiration(): void {
        // Use heartbeat timeout: locks not renewed within the timeout are considered stale
        $heartbeatTimeout = defined('EDITION_LOCK_HEARTBEAT_TIMEOUT')
            ? EDITION_LOCK_HEARTBEAT_TIMEOUT
            : 300;

        DB::query(
            'DELETE FROM ' . prefixTable('items_edition') . '
            WHERE timestamp < %i',
            time() - $heartbeatTimeout
        );
    }

    /**
     * Clean up old finished tasks.
     * This method removes tasks that have been completed for too long.
     */
    private function cleanOldFinishedTasks(): void {
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
                prefixTable('misc'),
                'temp_file'
            );

            $stats['total_found'] = count($tempFiles);

            if (empty($tempFiles)) {
                if (LOG_TASKS=== true) $this->logger->log('TeamPass Background Task: No temporary import files found in database', 'INFO');
                return;
            }

            foreach ($tempFiles as $fileEntry) {
                $entryId = intval($fileEntry['increment_id']);
                $timestamp = intval($fileEntry['intitule']);
                $fileName = strval($fileEntry['valeur']);

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
                        prefixTable('misc'),
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
