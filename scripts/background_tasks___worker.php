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
 * @file      background_tasks___worker.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;
require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__.'/background_tasks___functions.php';
require_once __DIR__.'/traits/ItemHandlerTrait.php';
require_once __DIR__.'/traits/UserHandlerTrait.php';
require_once __DIR__.'/traits/EmailTrait.php';
require_once __DIR__ . '/TaskLogger.php';

class TaskWorker {
    use ItemHandlerTrait;
    use UserHandlerTrait;
    use EmailTrait;

    private $taskId;
    private $processType;
    private $taskData;
    private $settings;
    private $logger;

    public function __construct(int $taskId, string $processType, array $taskData) {
        $this->taskId = $taskId;
        $this->processType = $processType;
        $this->taskData = $taskData;
        $this->settings['enable_tasks_log'] = true;
        $this->logger = new TaskLogger($this->settings, LOG_TASKS_FILE);
        
        $configManager = new ConfigManager();
        $this->settings = $configManager->getAllSettings();
    }

    /**
     * Execute the task based on its type.
     * This method will handle different types of tasks such as item copy, new item creation,
     * user cache tree building, email sending, and user key generation.
     * 
     * @return void
     */
    public function execute() {
        try {
            if (LOG_TASKS=== true) $this->logger->log('Processing task: ' . print_r($this->taskData, true), 'DEBUG');
            // Dispatch selon le type de processus
            switch ($this->processType) {
                case 'item_copy':
                    $this->processSubTasks($this->taskData);
                    break;
                case 'new_item':
                    $this->processSubTasks($this->taskData);
                    break;
                case 'item_update_create_keys':
                    $this->processSubTasks($this->taskData);
                    break;
                case 'user_build_cache_tree':
                    $this->handleUserBuildCacheTree($this->taskData);
                    break;
                case 'send_email':
                    $this->sendEmail($this->taskData);
                    break;
                case 'create_user_keys':
                    $this->generateUserKeys($this->taskData);
                    break;
                default:
                    throw new Exception("Type of subtask unknown: {$this->processType}");
            }

            // Mark the task as completed
            $this->completeTask();

        } catch (Exception $e) {
            $this->handleTaskFailure($e);
        }
    }
    
    /**
     * Mark the task as completed in the database.
     * This method updates the task status to 'completed' and sets the finished_at timestamp.
     * 
     * @return void
     */
    private function completeTask() {
        // Prepare data for updating the task status
        $updateData = [
            'is_in_progress' => -1,
            'finished_at' => time(),
            'status' => 'completed'
        ];

        // Prepare anonimzation of arguments
        if ($this->processType === 'send_mail') {
            $arguments = json_encode(
                [
                    'email' => $this->taskData['receivers'],
                    'login' => $this->taskData['receiver_name'],
                ]
            );
        } elseif ($this->processType === 'create_user_keys') {
            $arguments = json_encode(
                [
                    'user_id' => $this->taskData['new_user_id'],
                ]
            );
        } elseif ($this->processType === 'item_update_create_keys') {
            $arguments = json_encode(
                [
                    'item_id' => $this->taskData['new_user_id'],
                    'author' => $this->taskData['author'],
                ]
            );
        } else {
            $arguments = '';
        }

        if (LOG_TASKS=== true) $this->logger->log('Process: '.$this->processType.' -- '.print_r($arguments, true), 'DEBUG');

        // Add 'arguments' only if not empty
        if (!empty($arguments)) {
            $updateData['arguments'] = $arguments;
        }

        // Store completed status in the database
        DB::update(
            prefixTable('background_tasks'),
            $updateData,
            'increment_id = %i',
            $this->taskId
        );
        if (LOG_TASKS=== true) $this->logger->log('Finishing task: ' . $this->taskId, 'DEBUG');
    }

    /**
     * Handle task failure by updating the task status in the database.
     * This method sets the task status to 'failed', updates the finished_at timestamp,
     * and logs the error message.
     * 
     * @param Exception $e The exception that occurred during task processing.
     * @return void
     */
    private function handleTaskFailure(Exception $e) {
        DB::update(
            prefixTable('background_tasks'),
            [
                'is_in_progress' => -1,
                'finished_at' => time(),
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ],
            'increment_id = %i',
            $this->taskId
        );
        $this->logger->log('Task failure: ' . $e->getMessage(), 'ERROR');
    }

    /**
     * Handle subtasks for the current task.
     * This method retrieves all subtasks related to the current task and processes them.
     * If all subtasks are completed, it marks the main task as completed.
     * 
     * @param array $arguments Arguments for the subtasks.
     * @return void
     */
    private function processSubTasks($arguments) {
        // Get all subtasks related to this task
        $subtasks = DB::query(
            'SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE task_id = %i AND is_in_progress = 0 ORDER BY `task` ASC',
            $this->taskId
        );
    
        // Check if there are any subtasks to process
        if (empty($subtasks)) {
            if (LOG_TASKS=== true) $this->logger->log('No subtask was found for task: ' . $this->taskId, 'DEBUG');
            return;
        }
    
        // Process each subtask
        foreach ($subtasks as $subtask) {
            try {
                // Get the subtask data
                $subtaskData = json_decode($subtask['task'], true);

                if (LOG_TASKS=== true) $this->logger->log('Processing subtask: ' . $subtaskData['step'], 'DEBUG');

                // Process the subtask based on its type
                switch ($subtaskData['step'] ?? '') {
                    case 'create_users_pwd_key':
                        $this->generateUserPasswordKeys($arguments);
                        break;
                    case 'create_users_fields_key':
                        $this->generateUserFieldKeys($subtaskData);
                        break;
                    case 'create_users_files_key':
                        $this->generateUserFileKeys($subtaskData);
                        break;
                    default:
                        throw new Exception("Type de sous-tâche inconnu (".$subtaskData['step'].")");
                }                
        
                // Mark subtask as completed
                DB::update(
                    prefixTable('background_subtasks'),
                    [
                        'is_in_progress' => -1,
                        'finished_at' => time(),
                        'status' => 'completed',
                    ],
                    'increment_id = %i',
                    $subtask['increment_id']
                );
        
            } catch (Exception $e) {
                // Mark subtask as failed
                DB::update(
                    prefixTable('background_subtasks'),
                    [
                        'is_in_progress' => -1,
                        'finished_at' => time(),
                        'updated_at' => time(),
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ],
                    'increment_id = %i',
                    $subtask['increment_id']
                );
        
                $this->logger->log('processSubTasks : ' . $e->getMessage(), 'ERROR');
            }
        }
    
        // Are all subtasks completed?
        $remainingSubtasks = DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('background_subtasks') . ' WHERE task_id = %i AND is_in_progress = 0',
            $this->taskId
        );
    
        if ($remainingSubtasks == 0) {
            $this->completeTask();
        }
    }
}

// Prepare the environment
// Get the task ID and process type from command line arguments
if ($argc < 3) {
    error_log("Usage: php background_tasks___worker.php <task_id> <process_type> [<task_data>]");
    exit(1);
}
$taskId = (int)$argv[1];
$processType = $argv[2];
$taskData = $argv[3] ?? null;
if ($taskData) {
    $taskData = json_decode($taskData, true);
} else {
    $taskData = [];
}

// Initialize the worker
$worker = new TaskWorker($taskId, $processType, $taskData);
$worker->execute();