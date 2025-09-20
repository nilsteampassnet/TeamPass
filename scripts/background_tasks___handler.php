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
        $this->maxTimeBeforeRemoval = isset($settings['history_duration']) ? ($settings['history_duration'] * 24 * 3600) : (15 * 24 * 3600);
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
            $this->processTaskBatches();
            $this->performMaintenanceTasks();
        } catch (Exception $e) {
            if (LOG_TASKS=== true) $this->logger->log('Task processing error: ' . $e->getMessage(), 'ERROR');
        } finally {
            $this->releaseProcessLock();
        }
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
        unlink($lockFile);
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
                status = "failed"
            WHERE is_in_progress = 1 
            AND started_at < %i',
            time(),
            time() - $this->maxExecutionTime
        );

        // Remove very old failed tasks
        DB::query(
            'DELETE t, st FROM ' . prefixTable('background_tasks') . ' t
            INNER JOIN ' . prefixTable('background_subtasks') . ' st ON (t.increment_id = st.task_id)
            WHERE t.finished_at > %i 
            AND t.status = %s',
            time() - $this->maxTimeBeforeRemoval,
            "failed"
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
                'status' => 'in_progress'
            ],
            'increment_id = %i',
            $task['increment_id']
        );

        // Prepare process
        $process = new Process([
            PHP_BINARY,
            __DIR__ . '/background_tasks___worker.php',
            $task['increment_id'],
            $task['process_type'],
            $task['arguments']
        ]);

        // Launch process
        try{
            $process->setTimeout($this->maxExecutionTime);
            $process->mustRun();

        } catch (Exception $e) {
            if (LOG_TASKS=== true) $this->logger->log('Error launching task: ' . $e->getMessage(), 'ERROR');
            DB::update(
                prefixTable('background_tasks'),
                [
                    'is_in_progress' => -1,
                    'finished_at' => time(),
                    'status' => 'failed'
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
            WHERE status = %s AND is_in_progress = %i AND finished_at < %s',
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