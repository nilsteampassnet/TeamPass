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
require_once __DIR__ . '/TaskLogger.php';

class BackgroundTasksHandler {
    private $settings;
    private $logger;
    private $maxParallelTasks;
    private $maxExecutionTime;
    private $batchSize;

    public function __construct(array $settings) {
        $this->settings = $settings;
        $this->settings['enable_tasks_log'] = true;
        $this->logger = new TaskLogger($settings);//, '/tmp/teampass_background_tasks.log'
        $this->maxParallelTasks = $settings['max_parallel_tasks'] ?? 2;
        $this->maxExecutionTime = $settings['task_maximum_run_time'] ?? 300;
        $this->batchSize = $settings['task_batch_size'] ?? 50;
    }

    public function processBackgroundTasks() {
        // Prevent multiple concurrent executions
        if (!$this->acquireProcessLock()) {
            $this->logger->log('Process already running', 'INFO');
            return false;
        }

        try {
            $this->cleanupStaleTasks();
            $this->processTaskBatches();
            $this->performMaintenanceTasks();
        } catch (Exception $e) {
            $this->logger->log('Task processing error: ' . $e->getMessage(), 'ERROR');
        } finally {
            $this->releaseProcessLock();
        }
    }

    private function acquireProcessLock(): bool {
        $lockFile = sys_get_temp_dir() . '/teampass_background_tasks.lock';
        $fp = fopen($lockFile, 'w');
        
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            return false;
        }
        
        fwrite($fp, (string)getmypid());
        return true;
    }

    private function releaseProcessLock() {
        $lockFile = sys_get_temp_dir() . '/teampass_background_tasks.lock';
        unlink($lockFile);
    }

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

        /*
        // Remove very old failed tasks
        DB::query(
            'DELETE FROM ' . prefixTable('background_tasks') . '
            WHERE finished_at < %i 
            AND is_in_progress = -1',
            time() - (24 * 3600)  // 24 hours
        );
        */
    }

    private function processTaskBatches() {
        $runningTasks = $this->countRunningTasks();
        
        if ($runningTasks >= $this->maxParallelTasks) {
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
            $this->processIndividualTask($task);
        }
    }

    private function processIndividualTask(array $task) {
        // Mark task as in progress
        DB::update(
            prefixTable('background_tasks'),
            [
                'is_in_progress' => 1,
                'started_at' => time(),
            ],
            'increment_id = %i',
            $task['increment_id']
        );

        $this->logger->log('Processing task: ' . print_r($task, true), 'DEBUG');

        // Prepare process
        $process = new Process([
            PHP_BINARY,
            __DIR__ . '/background_tasks___worker.php',
            $task['increment_id'],
            $task['process_type'],
            $task['arguments']
        ]);

        $process->run();
    }

    private function countRunningTasks(): int {
        return DB::queryFirstField(
            'SELECT COUNT(*) 
            FROM ' . prefixTable('background_tasks') . ' 
            WHERE is_in_progress = 1'
        );
    }

    private function performMaintenanceTasks() {
        // Existing maintenance tasks from your original code
        $this->cleanMultipleItemsEdition();
        $this->handleItemTokensExpiration();
    }

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

    private function handleItemTokensExpiration() {
        DB::query(
            'DELETE FROM ' . prefixTable('items_edition') . '
            WHERE timestamp < %i',
            time() - ($this->settings['delay_item_edition'] * 60 ?: EDITION_LOCK_PERIOD)
        );
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