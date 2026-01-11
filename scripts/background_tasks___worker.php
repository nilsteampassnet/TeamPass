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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;
require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__.'/background_tasks___functions.php';
require_once __DIR__.'/traits/ItemHandlerTrait.php';
require_once __DIR__.'/traits/UserHandlerTrait.php';
require_once __DIR__.'/traits/EmailTrait.php';
require_once __DIR__.'/traits/MigrateUserHandlerTrait.php';
require_once __DIR__ . '/taskLogger.php';

class TaskWorker {
    use ItemHandlerTrait;
    use UserHandlerTrait;
    use EmailTrait;
    use MigrateUserHandlerTrait;

    private $taskId;
    private $processType;
    private $taskData;
    private $settings;
    private $logger;

    public function __construct(int $taskId, string $processType, array $taskData) {
        $this->taskId = $taskId;
        $this->processType = $processType;
        $this->taskData = $taskData;
        
        $configManager = new ConfigManager();
        $this->settings = $configManager->getAllSettings();
        $this->logger = new TaskLogger($this->settings, LOG_TASKS_FILE);
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
                case 'migrate_user_personal_items':
                    $this->migratePersonalItems($this->taskData);
                    break;
                case 'database_backup':
                    $this->handleDatabaseBackup($this->taskData);
                    break;
                default:
                    throw new Exception("Type of subtask unknown: {$this->processType}");
            }

            // Mark the task as completed
            try {
                $this->completeTask();
            } catch (Exception $e) {
                $this->handleTaskFailure($e);
            }

        } catch (Exception $e) {
            $this->handleTaskFailure($e);
        }

    }
    /**
     * Perform a scheduled database backup (encrypted) into files/backups.
     */
    private function handleDatabaseBackup(array $taskData): void
    {
        require_once __DIR__ . '/../sources/backup.functions.php';

        // Default target dir: <path_to_files_folder>/backups
        $baseFilesDir = (string)($this->settings['path_to_files_folder'] ?? (__DIR__ . '/../files'));
        $targetDir = rtrim($baseFilesDir, '/') . '/backups';

        // Allow override via task arguments (optional)
        if (!empty($taskData['output_dir']) && is_string($taskData['output_dir'])) {
            $targetDir = rtrim($taskData['output_dir'], '/');
        }

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0770, true) && !is_dir($targetDir)) {
                throw new Exception('Cannot create backup target dir: ' . $targetDir);
            }
        }
        if (!is_writable($targetDir)) {
            throw new Exception('Backup target dir is not writable: ' . $targetDir);
        }

        // Use stored encryption key (same as UI)
        $encryptionKey = (string)($this->settings['bck_script_passkey'] ?? '');
        if ($encryptionKey === '') {
            throw new Exception('Missing encryption key (bck_script_passkey).');
        }

// Auto-disconnect connected users before running a scheduled backup.
// Exclude the user who enqueued the task (manual run), if provided.
try {
    if (function_exists('loadClasses') && !class_exists('DB')) {
        loadClasses('DB');
    }
    $excludeUserId = (int) ($taskData['initiator_user_id'] ?? 0);
    $now = time();

    if ($excludeUserId > 0) {
        $connectedUsers = DB::query(
            'SELECT id FROM ' . prefixTable('users') . ' WHERE session_end >= %i AND id != %i',
            $now,
            $excludeUserId
        );
    } else {
        $connectedUsers = DB::query(
            'SELECT id FROM ' . prefixTable('users') . ' WHERE session_end >= %i',
            $now
        );
    }

    foreach ($connectedUsers as $u) {
        DB::update(
            prefixTable('users'),
            [
                'key_tempo' => '',
                'timestamp' => '',
                'session_end' => '',
            ],
            'id = %i',
            (int) $u['id']
        );
    }
} catch (Throwable $ignored) {
    // Best effort only - do not block backups if disconnection cannot be done
}

        $res = tpCreateDatabaseBackup($this->settings, $encryptionKey, [
            'output_dir' => $targetDir,
            'filename_prefix' => 'scheduled-',
        ]);

        if (($res['success'] ?? false) !== true) {
            throw new Exception($res['message'] ?? 'Backup failed');
        }

        // Store a tiny summary for the task completion "arguments" field (no secrets)
        $this->taskData['backup_file'] = $res['filename'] ?? '';
        $this->taskData['backup_size_bytes'] = (int)($res['size_bytes'] ?? 0);
        $this->taskData['backup_encrypted'] = (bool)($res['encrypted'] ?? false);

        // Retention purge (scheduled backups only)
        $backupSource = (string)($taskData['source'] ?? '');
        $backupDir = (string)($taskData['output_dir'] ?? '');   // from task arguments (reliable)
        if ($backupDir === '') {
            $backupDir = (string) $targetDir;
        }

        // keep for debug/trace
        $this->taskData['output_dir'] = $backupDir;

        if ($backupSource === 'scheduler' && $backupDir !== '') {
            $days = (int)$this->getMiscSetting('bck_scheduled_retention_days', '30');
            $deleted = $this->purgeOldScheduledBackups($backupDir, $days);

            $this->upsertMiscSetting('bck_scheduled_last_purge_at', (string)time());
            $this->upsertMiscSetting('bck_scheduled_last_purge_deleted', (string)$deleted);

            if (LOG_TASKS === true) {
                $this->logger->log("database_backup: purge retention={$days}d dir={$backupDir} deleted={$deleted}", 'INFO');
            }
        }

        // If launched by scheduler, update scheduler status in teampass_misc
        if (!empty($taskData['source']) && $taskData['source'] === 'scheduler') {
            $this->updateSchedulerState('completed', 'Backup created: ' . ($this->taskData['backup_file'] ?? ''));
        }

        if (LOG_TASKS === true) {
            $this->logger->log(
                'database_backup: created ' . ($this->taskData['backup_file'] ?? '') . ' (' . $this->taskData['backup_size_bytes'] . ' bytes)',
                'INFO'
            );
        }
    }

    /**
     * Mark the task as completed in the database.
     * This method updates the task status to 'completed' and sets the finished_at timestamp.
     * 
     * @return void
     */
    private function updateSchedulerState(string $status, string $message): void
    {
        $this->upsertMiscSetting('bck_scheduled_last_status', $status);
        $this->upsertMiscSetting('bck_scheduled_last_message', mb_substr($message, 0, 500));
        $this->upsertMiscSetting('bck_scheduled_last_completed_at', (string)time());
    }

    private function upsertMiscSetting(string $key, string $value): void
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
    }

    private function getMiscSetting(string $key, string $default = ''): string
    {
        $table = prefixTable('misc');

        $val = DB::queryFirstField(
            'SELECT valeur FROM ' . $table . ' WHERE type = %s AND intitule = %s LIMIT 1',
            'settings',
            $key
        );

        if ($val === null || $val === false || $val === '') {
            return $default;
        }

        return (string) $val;
    }

    private function purgeOldScheduledBackups(string $dir, int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0; // 0 => désactivé
        }

        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = time() - ($retentionDays * 86400);
        $deleted = 0;

        foreach (glob(rtrim($dir, '/') . '/scheduled-*.sql') as $file) {
            if (!is_file($file)) {
                continue;
            }

            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function completeTask() {
        // Prepare data for updating the task status
        $updateData = [
            'is_in_progress' => -1,
            'finished_at' => time(),
            'status' => 'completed',
            'error_message' => null,   // <-- on efface toute erreur précédente
        ];

        // Prepare anonimzation of arguments
        if ($this->processType === 'send_mail') {
            $arguments = json_encode(
                [
                    'email' => $this->taskData['receivers'],
                    'login' => $this->taskData['receiver_name'],
                ]
            );
        } elseif ($this->processType === 'create_user_keys' || $this->processType === 'migrate_user_personal_items') {
            $arguments = json_encode(
                [
                    'user_id' => $this->taskData['new_user_id'],
                ]
            );
        } elseif ($this->processType === 'item_update_create_keys') {
            $arguments = json_encode(
                [
                    'item_id' => $this->taskData['item_id'],
                    'author' => $this->taskData['author'],
                ]
            );
        } elseif ($this->processType === 'database_backup') {
            $arguments = json_encode(
                [
                    'file' => $this->taskData['backup_file'] ?? '',
                    'size_bytes' => $this->taskData['backup_size_bytes'] ?? 0,
                    'encrypted' => $this->taskData['backup_encrypted'] ?? false,
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
    private function handleTaskFailure(Throwable $e) {
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
    // Purge retention even on failure (safe: only scheduled-*.sql)
        $backupDir = (string)($this->taskData['output_dir'] ?? '');
        if ($backupDir !== '' && is_dir($backupDir)) {
        $days = (int)$this->getMiscSetting('bck_scheduled_retention_days', '30');
        $deleted = $this->purgeOldScheduledBackups($backupDir, $days);

        $this->upsertMiscSetting('bck_scheduled_last_purge_at', (string)time());
        $this->upsertMiscSetting('bck_scheduled_last_purge_deleted', (string)$deleted);
        }
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
        if (LOG_TASKS=== true) $this->logger->log('processSubTasks: '.print_r($arguments, true), 'DEBUG');
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

                // Mark subtask as in progress
                DB::update(
                    prefixTable('background_tasks'),
                    ['updated_at' => time()],
                    'increment_id = %i',
                    $this->taskId
                );

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
    if (!is_array($taskData)) {
        $taskData = [];
    }
} else {
    $taskData = [];
}

// Initialize the worker
$worker = new TaskWorker($taskId, $processType, $taskData);
$worker->execute();
