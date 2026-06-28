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

declare(strict_types=1);

use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\Language\Language;
require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__ . '/../sources/backup.functions.php';
require_once __DIR__ . '/../sources/users_purge.functions.php';
require_once __DIR__.'/background_tasks___functions.php';
require_once __DIR__.'/traits/ItemHandlerTrait.php';
require_once __DIR__.'/traits/UserHandlerTrait.php';
require_once __DIR__.'/traits/EmailTrait.php';
require_once __DIR__.'/traits/MigrateUserHandlerTrait.php';
require_once __DIR__.'/traits/PhpseclibV3MigrationTrait.php';
require_once __DIR__ . '/taskLogger.php';

class TaskWorker {
    use ItemHandlerTrait;
    use UserHandlerTrait;
    use EmailTrait;
    use MigrateUserHandlerTrait;
    use PhpseclibV3MigrationTrait;

    private int $taskId;
    private string $processType;
    private array $taskData;
    private array $settings;
    private TaskLogger $logger;

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
    public function execute(): void {
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
                case 'phpseclibv3_migration':
                    $this->migratePhpseclibV3($this->taskData);
                    break;
                case 'database_backup':
                    $this->handleDatabaseBackup($this->taskData);
                    break;
                case 'externalized_backup':
                    $this->handleExternalizedBackup($this->taskData);
                    break;
                case 'inactive_users_housekeeping':
                    $this->handleInactiveUsersHousekeeping();
                    break;
                default:
                    throw new Exception("Type of subtask unknown: {$this->processType}");
            }

            // Mark the task as completed
            try {
                $this->completeTask();
                $this->emitItemEncryptionEvent('completed');
            } catch (Exception $e) {
                $this->handleTaskFailure($e);
            }

        } catch (Exception $e) {
            $this->handleTaskFailure($e);
        }
    }

    /**
     * Perform a scheduled database backup (encrypted) into storage/backups by default.
     */
    private function handleDatabaseBackup(array $taskData): void
    {
        require_once __DIR__ . '/../sources/backup.functions.php';

        $requestedTargetDir = !empty($taskData['output_dir']) && is_string($taskData['output_dir'])
            ? (string) $taskData['output_dir']
            : '';
        $resolvedTargetDir = tpBackupResolveScheduledOutputDir($requestedTargetDir, $this->settings);
        if ($resolvedTargetDir['success'] === false) {
            throw new Exception('Invalid backup target dir: ' . $requestedTargetDir);
        }

        $targetDir = $resolvedTargetDir['path'];
        $this->taskData['output_dir'] = $targetDir;

        if (!is_writable($targetDir)) {
            throw new Exception('Backup target dir is not writable: ' . $targetDir);
        }

        // Use the resolved clear backup key and self-heal empty values on impacted instances.
        $resolvedBackupScriptPasskey = tpResolveBackupScriptPasskey($this->settings, true);
        $encryptionKey = !empty($resolvedBackupScriptPasskey['success'])
            ? (string) $resolvedBackupScriptPasskey['clear_key']
            : '';
        if ($encryptionKey === '') {
            throw new Exception('Missing encryption key (bck_script_passkey).');
        }

        // Auto-disconnect connected users before running a scheduled backup.
        // Exclude the user who enqueued the task (manual run), if provided.
        $this->disconnectConnectedUsersBeforeBackup((int) ($taskData['initiator_user_id'] ?? 0));

        $includeDocuments = ((int)($taskData['include_documents'] ?? $this->getMiscSetting('bck_scheduled_include_documents', '0')) === 1);
        $backupFormat = tpBackupNormalizeRequestedFormat((string)($taskData['backup_format'] ?? $this->getMiscSetting('bck_scheduled_format', 'sql')));
        if ($includeDocuments === true) {
            $backupFormat = tpBackupGetPackageExtension();
        }
        if ($backupFormat === tpBackupGetPackageExtension()) {
            $res = tpCreateBackupPackage($this->settings, $encryptionKey, [
                'output_dir' => $targetDir,
                'filename_prefix' => 'scheduled-',
                'source' => 'scheduled',
                'include_documents' => $includeDocuments,
            ]);
        } else {
            $res = tpCreateDatabaseBackup($this->settings, $encryptionKey, [
                'output_dir' => $targetDir,
                'filename_prefix' => 'scheduled-',
            ]);
        }

        if ($res['success'] !== true) {
            throw new Exception($res['message']);
        }


        // Best effort: write metadata sidecar next to the backup file
        try {
            if ($backupFormat !== tpBackupGetPackageExtension() && !empty($res['filepath']) && function_exists('tpWriteBackupMetadata')) {
                tpWriteBackupMetadata((string) $res['filepath'], '', '', ['source' => 'scheduled']);
            }
        } catch (Throwable) {
            // do not block backups if metadata cannot be written
        }

        // Store a tiny summary for the task completion "arguments" field (no secrets)
        $this->taskData['backup_file'] = $res['filename'];
        $this->taskData['backup_size_bytes'] = (int)$res['size_bytes'];
        $this->taskData['backup_encrypted'] = (bool)$res['encrypted'];
        $this->taskData['backup_format'] = $backupFormat;
        $this->taskData['include_documents'] = $includeDocuments;

        // Retention purge (scheduled backups only)
        $backupSource = (string)($taskData['source'] ?? '');
        $backupDir = (string)($taskData['output_dir'] ?? '');   // from task arguments (reliable)
        if ($backupDir === '') {
            $backupDir = (string) $targetDir;
        }

        // keep for debug/trace
        $this->taskData['output_dir'] = $backupDir;

        if ($backupSource === 'scheduler') {
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
            $scheduledMessage = 'Backup created: ' . ($this->taskData['backup_file'] ?? '');
            $this->updateSchedulerState('completed', $scheduledMessage);
            $externalizedQueued = false;
            try {
                $externalizedQueued = $this->queueExternalizedBackupAfterScheduled();
            } catch (Throwable $e) {
                $this->taskData['externalized_after_scheduled_expected'] = true;
                $this->taskData['externalized_report_status'] = 'failed';
                $this->taskData['externalized_report_message'] = 'Unable to queue externalized backup after scheduled backup: ' . $e->getMessage();
                if (LOG_TASKS === true) {
                    $this->logger->log(
                        'database_backup: unable to queue externalized backup after scheduled backup: ' . $e->getMessage(),
                        'ERROR'
                    );
                }
            }

            if ($externalizedQueued === false) {
                $reportStatus = 'completed';
                $reportMessage = $scheduledMessage;
                $externalizedReport = [];

                if (!empty($this->taskData['externalized_after_scheduled_expected'])) {
                    $externalizedStatus = (string)($this->taskData['externalized_report_status'] ?? 'failed');
                    $externalizedMessage = (string)($this->taskData['externalized_report_message'] ?? 'Externalized backup was not queued after scheduled backup.');
                    $externalizedReport = $this->buildExternalizedReportContext($externalizedStatus, $externalizedMessage, [
                        'file' => '',
                        'size_bytes' => 0,
                        'destination_type' => (string)$this->getMiscSetting('bck_externalized_destination_type', ''),
                        'target' => (string)$this->getMiscSetting('bck_externalized_target_dir', ''),
                        'retry_attempt' => 0,
                        'retry_attempts' => max(1, min(5, (int)$this->getMiscSetting('bck_externalized_retry_attempts', '3'))),
                    ]);
                    $reportStatus = 'failed';
                    $reportMessage = $scheduledMessage . '. Externalized backup was not completed: ' . $externalizedMessage;
                }

                try {
                    $this->queueScheduledBackupReportEmail($reportStatus, $reportMessage, $externalizedReport);
                } catch (Throwable) {
                    // best effort only - never block the backup process
                }
            }
        }

        if (LOG_TASKS === true) {
            $this->logger->log(
                'database_backup: created ' . ($this->taskData['backup_file'] ?? '') . ' (' . $this->taskData['backup_size_bytes'] . ' bytes)',
                'INFO'
            );
        }
    }

    /**
     * Queue an externalized backup after a successful scheduled database backup.
     */
    private function queueExternalizedBackupAfterScheduled(): bool
    {
        if ((int) $this->getMiscSetting('bck_externalized_enabled', '0') !== 1) {
            $this->taskData['externalized_after_scheduled_expected'] = false;
            return false;
        }
        if ((int) $this->getMiscSetting('bck_externalized_run_after_scheduled', '0') !== 1) {
            $this->taskData['externalized_after_scheduled_expected'] = false;
            return false;
        }
        $this->taskData['externalized_after_scheduled_expected'] = true;

        $destinationType = tpBackupResolveExternalizedDestinationType((string) $this->getMiscSetting('bck_externalized_destination_type', 'local_directory'));
        if (tpBackupExternalizedDestinationTypeSupportsFileOperations($destinationType) === false) {
            $message = 'Externalized destination type is not available after scheduled backup';
            $this->taskData['externalized_report_status'] = 'failed';
            $this->taskData['externalized_report_message'] = $message;
            $this->updateExternalizedState('failed', $message);
            return false;
        }

        $pending = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('background_tasks') . '
             WHERE process_type=%s AND is_in_progress IN (0,1)
               AND (finished_at IS NULL OR finished_at = "" OR finished_at = 0)',
            'externalized_backup'
        );
        if ($pending > 0) {
            $this->taskData['externalized_report_status'] = 'failed';
            $this->taskData['externalized_report_message'] = 'Externalized backup already pending or running after scheduled backup';
            if (LOG_TASKS === true) {
                $this->logger->log('database_backup: externalized backup already pending/running after scheduled backup', 'INFO');
            }
            return false;
        }

        $includeDocuments = ((int) $this->getMiscSetting('bck_externalized_include_documents', '0') === 1) ? 1 : 0;
        $backupFormat = tpBackupNormalizeRequestedFormat((string) $this->getMiscSetting('bck_externalized_format', tpBackupGetPackageExtension()));
        if ($includeDocuments === 1) {
            $backupFormat = tpBackupGetPackageExtension();
        }

        $now = time();
        DB::insert(
            prefixTable('background_tasks'),
            [
                'created_at' => (string) $now,
                'process_type' => 'externalized_backup',
                'arguments' => json_encode(
                    [
                        'output_dir' => (string) $this->getMiscSetting('bck_externalized_target_dir', ''),
                        'destination_type' => $destinationType,
                        'source' => 'scheduled_post_backup',
                        'scheduled_backup_file' => (string) ($this->taskData['backup_file'] ?? ''),
                        'scheduled_backup_size_bytes' => (int) ($this->taskData['backup_size_bytes'] ?? 0),
                        'scheduled_backup_output_dir' => (string) ($this->taskData['output_dir'] ?? ''),
                        'scheduled_backup_format' => (string) ($this->taskData['backup_format'] ?? ''),
                        'scheduled_backup_include_documents' => (int) ($this->taskData['include_documents'] ?? 0),
                        'scheduled_backup_retention_days' => (int) $this->getMiscSetting('bck_scheduled_retention_days', '30'),
                        'scheduled_backup_purge_deleted' => (int) $this->getMiscSetting('bck_scheduled_last_purge_deleted', '0'),
                        'backup_format' => $backupFormat,
                        'include_documents' => $includeDocuments,
                        'retry_attempts' => max(1, min(5, (int) $this->getMiscSetting('bck_externalized_retry_attempts', '3'))),
                        'retry_delay_seconds' => max(0, min(60, (int) $this->getMiscSetting('bck_externalized_retry_delay_seconds', '5'))),
                    ],
                    JSON_UNESCAPED_SLASHES
                ),
                'is_in_progress' => 0,
                'status' => 'new',
            ]
        );

        $this->upsertMiscSetting('bck_externalized_last_run_at', (string) $now);
        $this->updateExternalizedState('queued', 'Externalized backup queued after scheduled backup');

        if (function_exists('triggerBackgroundHandler')) {
            triggerBackgroundHandler();
        }

        if (LOG_TASKS === true) {
            $this->logger->log('database_backup: queued externalized backup after scheduled backup', 'INFO');
        }

        return true;
    }


    /**
     * Perform a manual externalized backup into a validated destination.
     */
    private function handleExternalizedBackup(array $taskData): void
    {
        require_once __DIR__ . '/../sources/backup.functions.php';

        $this->upsertMiscSetting('bck_externalized_last_run_at', (string)time());
        $this->updateExternalizedState('running', 'Externalized backup started');

        $destinationType = !empty($taskData['destination_type']) && is_string($taskData['destination_type'])
            ? (string) $taskData['destination_type']
            : (string) $this->getMiscSetting('bck_externalized_destination_type', 'local_directory');
        $destinationType = tpBackupResolveExternalizedDestinationType($destinationType);
        if (tpBackupExternalizedDestinationTypeSupportsFileOperations($destinationType) === false) {
            throw new Exception('Externalized backup execution is not available yet for destination type: ' . $destinationType);
        }

        $requestedTargetDir = !empty($taskData['output_dir']) && is_string($taskData['output_dir'])
            ? (string) $taskData['output_dir']
            : (string) $this->getMiscSetting('bck_externalized_target_dir', '');
        $maxAttempts = max(1, min(5, (int)($taskData['retry_attempts'] ?? $this->getMiscSetting('bck_externalized_retry_attempts', '3'))));
        $retryDelaySeconds = max(0, min(60, (int)($taskData['retry_delay_seconds'] ?? $this->getMiscSetting('bck_externalized_retry_delay_seconds', '5'))));
        $this->taskData['destination_type'] = $destinationType;
        $this->taskData['retry_attempts'] = $maxAttempts;

        // Use the resolved clear backup key and self-heal empty values on impacted instances.
        $resolvedBackupScriptPasskey = tpResolveBackupScriptPasskey($this->settings, true);
        $encryptionKey = !empty($resolvedBackupScriptPasskey['success'])
            ? (string) $resolvedBackupScriptPasskey['clear_key']
            : '';
        if ($encryptionKey === '') {
            throw new Exception('Missing encryption key (bck_script_passkey).');
        }

        $this->disconnectConnectedUsersBeforeBackup((int) ($taskData['initiator_user_id'] ?? 0));

        $includeDocuments = ((int)($taskData['include_documents'] ?? $this->getMiscSetting('bck_externalized_include_documents', '0')) === 1);
        $backupFormat = tpBackupNormalizeRequestedFormat((string)($taskData['backup_format'] ?? $this->getMiscSetting('bck_externalized_format', tpBackupGetPackageExtension())));
        if ($includeDocuments === true) {
            $backupFormat = tpBackupGetPackageExtension();
        }

        $res = [];
        $targetDir = '';
        $destinationConfig = $this->getExternalizedDestinationConfig($destinationType);
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->taskData['retry_attempt'] = $attempt;
            $this->updateExternalizedState('running', 'Externalized backup attempt ' . $attempt . '/' . $maxAttempts);

            try {
                $attemptOutputDir = '';
                try {
                    if (tpBackupExternalizedDestinationTypeIsRemote($destinationType) === true) {
                        $destinationConfig['remote_path'] = $requestedTargetDir;
                        if ($destinationType === 'sftp') {
                            $resolvedTargetDir = tpBackupValidateExternalizedSftpConfig($destinationConfig);
                        } elseif ($destinationType === 'webdav') {
                            $resolvedTargetDir = tpBackupValidateExternalizedWebdavConfig($destinationConfig);
                        } elseif ($destinationType === 's3') {
                            $destinationConfig['prefix'] = $requestedTargetDir;
                            $resolvedTargetDir = tpBackupValidateExternalizedS3Config($destinationConfig);
                        } else {
                            $resolvedTargetDir = ['success' => false, 'reason' => 'UNSUPPORTED_TYPE', 'path' => ''];
                        }

                        if ($resolvedTargetDir['success'] === false) {
                            throw new Exception('Invalid externalized backup destination: ' . (string) $resolvedTargetDir['reason']);
                        }

                        $targetDir = (string) $resolvedTargetDir['path'];
                        $this->taskData['output_dir'] = $targetDir;
                        $attemptOutputDir = tpBackupCreateTemporaryDirectory((string) sys_get_temp_dir());
                        if ($attemptOutputDir === '') {
                            throw new Exception('Unable to create a temporary directory for remote externalized backup.');
                        }
                    } else {
                        $resolvedTargetDir = tpBackupResolveExternalizedDestination($destinationType, $requestedTargetDir, $this->settings);
                        if ($resolvedTargetDir['success'] === false) {
                            throw new Exception('Invalid externalized backup destination: ' . (string) $resolvedTargetDir['reason']);
                        }

                        $targetDir = (string) $resolvedTargetDir['path'];
                        $this->taskData['output_dir'] = $targetDir;
                        $attemptOutputDir = $targetDir;
                    }

                    if ($backupFormat === tpBackupGetPackageExtension()) {
                        $res = tpCreateBackupPackage($this->settings, $encryptionKey, [
                            'output_dir' => $attemptOutputDir,
                            'filename_prefix' => 'externalized-',
                            'source' => 'externalized',
                            'include_documents' => $includeDocuments,
                        ]);
                    } else {
                        $res = tpCreateDatabaseBackup($this->settings, $encryptionKey, [
                            'output_dir' => $attemptOutputDir,
                            'filename_prefix' => 'externalized-',
                        ]);
                    }

                    if ($res['success'] !== true) {
                        throw new Exception((string) $res['message']);
                    }

                    if (tpBackupExternalizedDestinationTypeIsRemote($destinationType) === true) {
                        try {
                            if ($backupFormat !== tpBackupGetPackageExtension() && !empty($res['filepath']) && function_exists('tpWriteBackupMetadata')) {
                                tpWriteBackupMetadata((string) $res['filepath'], '', '', ['source' => 'externalized']);
                            }
                        } catch (Throwable) {
                            // do not block backups if metadata cannot be written
                        }

                        $upload = tpBackupUploadExternalizedBackup($destinationType, $targetDir, (string) $res['filepath'], $this->settings, $destinationConfig);
                        if ($upload['success'] !== true) {
                            throw new Exception('Externalized backup upload failed: ' . (string) $upload['reason']);
                        }

                        $res['filepath'] = (string) $upload['path'];
                        $res['filename'] = (string) $upload['filename'];
                        $res['size_bytes'] = (int) $upload['size_bytes'];
                    }
                } finally {
                    if (tpBackupExternalizedDestinationTypeIsRemote($destinationType) === true && $attemptOutputDir !== '') {
                        tpBackupRemoveTemporaryDirectory($attemptOutputDir);
                    }
                }

                break;
            } catch (Throwable $e) {
                if (LOG_TASKS === true) {
                    $this->logger->log(
                        'externalized_backup: attempt ' . $attempt . '/' . $maxAttempts . ' failed: ' . $e->getMessage(),
                        'ERROR'
                    );
                }

                if ($attempt >= $maxAttempts) {
                    throw new Exception(
                        'Externalized backup failed after ' . $maxAttempts . ' attempt(s): ' . $e->getMessage(),
                        0,
                        $e
                    );
                }

                $this->updateExternalizedState('running', 'Externalized backup retry scheduled after failure: ' . $e->getMessage());
                if ($retryDelaySeconds > 0) {
                    sleep($retryDelaySeconds);
                }
            }
        }

        if (($res['success'] ?? false) !== true || ($targetDir === '' && $destinationType !== 's3')) {
            throw new Exception('Externalized backup failed.');
        }

        try {
            if ($destinationType === 'local_directory' && $backupFormat !== tpBackupGetPackageExtension() && !empty($res['filepath']) && function_exists('tpWriteBackupMetadata')) {
                tpWriteBackupMetadata((string) $res['filepath'], '', '', ['source' => 'externalized']);
            }
        } catch (Throwable) {
            // do not block backups if metadata cannot be written
        }

        $this->taskData['backup_file'] = (string) $res['filename'];
        $this->taskData['backup_size_bytes'] = (int) $res['size_bytes'];
        $this->taskData['backup_encrypted'] = (bool) $res['encrypted'];
        $this->taskData['backup_format'] = $backupFormat;
        $this->taskData['include_documents'] = $includeDocuments;

        $retentionDays = max(1, (int) $this->getMiscSetting('bck_externalized_retention_days', '30'));
        $retentionCount = max(1, (int) $this->getMiscSetting('bck_externalized_retention_count', '10'));
        $deleted = $this->purgeOldExternalizedBackups($targetDir, $retentionDays, $retentionCount, $destinationType, $destinationConfig);

        $this->upsertMiscSetting('bck_externalized_last_purge_at', (string)time());
        $this->upsertMiscSetting('bck_externalized_last_purge_deleted', (string)$deleted);
        $this->upsertMiscSetting('bck_externalized_last_file', (string) $res['filename']);
        $this->upsertMiscSetting('bck_externalized_last_size_bytes', (string) ((int) $res['size_bytes']));

        $message = 'Externalized backup created: ' . (string) $res['filename'];
        $this->updateExternalizedState('completed', $message);

        if ((string)($taskData['source'] ?? '') === 'scheduled_post_backup') {
            try {
                $scheduledFile = (string)($taskData['scheduled_backup_file'] ?? '');
                $reportMessage = 'Backup created: ' . $scheduledFile . '. ' . $message;
                $this->queueScheduledBackupReportEmail(
                    'completed',
                    $reportMessage,
                    $this->buildExternalizedReportContext('completed', $message),
                    $this->buildScheduledReportContextFromTaskData()
                );
                $this->taskData['scheduled_post_backup_report_sent'] = true;
            } catch (Throwable) {
                // best effort only - never block the backup process
            }
        }

        if (LOG_TASKS === true) {
            $this->logger->log(
                'externalized_backup: created ' . (string) $res['filename'] . ' (' . (int) $res['size_bytes'] . ' bytes)',
                'INFO'
            );
        }
    }

    /**
     * Auto-disconnect connected users before backup operations.
     */
    private function disconnectConnectedUsersBeforeBackup(int $excludeUserId = 0): void
    {
        try {
            if (function_exists('loadClasses') && !class_exists('DB')) {
                loadClasses('DB');
            }
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
                    intval($u['id'])
                );
            }
        } catch (Throwable) {
            // Best effort only - do not block backups if disconnection cannot be done
        }
    }


    /**
     * Housekeeping: warn inactive users then disable/soft-delete/hard-delete after grace period.
     */
    private function handleInactiveUsersHousekeeping(): void
    {
        if (function_exists('loadClasses') && !class_exists('DB')) {
            loadClasses('DB');
        }

        $now = time();

        $enabled = (int)$this->getMiscSetting('inactive_users_mgmt_enabled', '0');
        if ($enabled !== 1) {
            $this->updateInactiveUsersMgmtState('completed', 'inactive_users_mgmt_msg_task_disabled', [
                'checked' => 0,
                'warned' => 0,
                'warned_no_email' => 0,
                'action_disable' => 0,
                'action_soft_delete' => 0,
                'action_hard_delete' => 0,
                'purged' => 0,
                'errors' => 0,
            ]);
            return;
        }

        $inactivityDays = max(1, (int)$this->getMiscSetting('inactive_users_mgmt_inactivity_days', '90'));
        $graceDays = max(0, (int)$this->getMiscSetting('inactive_users_mgmt_grace_days', '7'));
        $defaultAction = (string)$this->getMiscSetting('inactive_users_mgmt_action', 'disable');
        if (!in_array($defaultAction, ['disable', 'soft_delete', 'hard_delete'], true)) {
            $defaultAction = 'disable';
        }

        $this->updateInactiveUsersMgmtState('running', 'inactive_users_mgmt_msg_task_started', [
            'inactivity_days' => $inactivityDays,
            'grace_days' => $graceDays,
            'action' => $defaultAction,
        ]);
        $this->upsertMiscSetting('inactive_users_mgmt_last_run_at', (string)$now);

        // Exclude system accounts by ID (constants if present)
        $excludeIds = [];
        foreach (['TP_USER_ID', 'API_USER_ID', 'OTV_USER_ID', 'SSH_USER_ID'] as $c) {
            if (defined($c) && is_numeric(constant($c))) {
                $excludeIds[] = (int) constant($c);
            }
        }
        $excludeIds = array_values(array_unique($excludeIds));

        $sql = 'SELECT id, login, email, name, lastname, user_language,
                    admin, special, disabled, deleted_at,
                    last_connexion, created_at,
                    inactivity_warned_at, inactivity_action_at, inactivity_action, inactivity_no_email
                FROM ' . prefixTable('users') . '
                WHERE admin = 0
                AND disabled = 0
                AND (deleted_at IS NULL OR deleted_at = "" OR deleted_at = 0)
                AND (special IS NULL OR special = "" OR special = "none")';
        if (count($excludeIds) > 0) {
            $sql .= ' AND id NOT IN %li';
            $users = DB::query($sql, $excludeIds);
        } else {
            $users = DB::query($sql);
        }

        $apiActivityByUser = $this->getApiFunctionalActivityByUser(
            array_map(static fn ($user): int => (int) ($user['id'] ?? 0), $users)
        );
        $warnCutoff = $now - ($inactivityDays * 86400);

        $warned = 0;
        $warnedNoEmail = 0;
        $actionDisable = 0;
        $actionSoftDelete = 0;
        $actionHardDelete = 0;
        $purged = 0;
        $errors = 0;
        $reset = 0;
        $apiActivityBackfilled = 0;

        foreach ($users as $u) {
            $userId = intval($u['id'] ?? 0);
            if ($userId <= 0) continue;

            try {
                $lastConnexionTs = $this->parseUserTs($u['last_connexion'] ?? null);
                $apiActivityTs = (int) ($apiActivityByUser[$userId] ?? 0);
                $createdAtTs = $this->parseUserTs($u['created_at'] ?? null);
                $lastActivityTs = max($lastConnexionTs, $apiActivityTs);
                if ($lastActivityTs <= 0 && $createdAtTs > 0) {
                    $lastActivityTs = $createdAtTs;
                }

                $warnedAt = $this->parseUserTs($u['inactivity_warned_at'] ?? null);
                $actionAt = $this->parseUserTs($u['inactivity_action_at'] ?? null);
                $storedAction = strval($u['inactivity_action'] ?? '');

                $activityUpdate = [];
                $hasApiActivityBackfill = false;
                if ($apiActivityTs > $lastConnexionTs) {
                    $activityUpdate['last_connexion'] = (string) $apiActivityTs;
                    $hasApiActivityBackfill = true;
                }

                // Reset tracking if the user had functional activity after a warning.
                if ($warnedAt > 0 && $lastActivityTs > $warnedAt) {
                    $activityUpdate = array_merge($activityUpdate, [
                        'inactivity_warned_at' => null,
                        'inactivity_action_at' => null,
                        'inactivity_action' => null,
                        'inactivity_no_email' => 0,
                    ]);
                    DB::update(prefixTable('users'), $activityUpdate, 'id = %i', $userId);
                    if ($hasApiActivityBackfill === true) {
                        $apiActivityBackfilled++;
                    }
                    $reset++;
                    continue;
                }
                if (count($activityUpdate) > 0) {
                    // Reaching here with a non-empty update means the only change is the
                    // API-activity backfill (last_connexion); any other update path above
                    // has already continued the loop.
                    DB::update(prefixTable('users'), $activityUpdate, 'id = %i', $userId);
                    $apiActivityBackfilled++;
                }

                // Apply due action
                if ($actionAt > 0 && $actionAt <= $now) {
                    $toApply = $storedAction !== '' ? $storedAction : $defaultAction;
                    $this->applyInactivityAction($userId, $toApply, $now);

                    if ($toApply === 'disable') $actionDisable++;
                    if ($toApply === 'soft_delete') $actionSoftDelete++;
                    if ($toApply === 'hard_delete') { $actionHardDelete++; $purged++; }

                    continue;
                }

                // Warn if threshold reached and not warned yet
                if ($warnedAt <= 0 && $lastActivityTs > 0 && $lastActivityTs <= $warnCutoff) {
                    $email = trim(strval($u['email'] ?? ''));
                    $noEmail = 0;

                    if ($email === '') {
                        $noEmail = 1;
                        $warnedNoEmail++;
                    } else {
                        $userLang = trim(strval($u['user_language'] ?? ''));
                        if ($userLang === '' || $userLang === '0') $userLang = 'english';
                        $langUser = new Language($userLang);

                        $receiverName = trim(strval($u['name'] ?? '') . ' ' . strval($u['lastname'] ?? ''));
                        $firstName = trim(strval($u['name'] ?? ''));
                        if ($firstName === '') {
                            $firstName = trim(strval($u['lastname'] ?? ''));
                        }
                        if ($firstName === '') {
                            $firstName = strval($u['login'] ?? '');
                        }
                        if ($receiverName === '') $receiverName = strval($u['login'] ?? '');

                        $subject = (string)$langUser->get('inactive_users_mgmt_email_subject');
                        $bodyTpl = (string)$langUser->get('inactive_users_mgmt_email_body');
                        $actionLabel = (string)$langUser->get('inactive_users_mgmt_action_' . $defaultAction);

                        $tpUrl = (string)($this->settings['cpassman_url'] ?? '');
                        $body = str_replace(
                            ['#login#', '#firstname#', '#lastname#', '#inactivity_days#', '#grace_days#', '#action#', '#url#'],
                            [strval($u['login'] ?? ''), $firstName, $firstName, (string)$inactivityDays, (string)$graceDays, $actionLabel, $tpUrl],
                            $bodyTpl
                        );

                        prepareSendingEmail($subject, $body, $email, $receiverName);
                    }

                    $actionAtNew = $now + ($graceDays * 86400);

                    DB::update(prefixTable('users'), [
                        'inactivity_warned_at' => (string)$now,
                        'inactivity_action_at' => (string)$actionAtNew,
                        'inactivity_action' => $defaultAction,
                        'inactivity_no_email' => $noEmail,
                    ], 'id = %i', $userId);

                    $warned++;
                }
            } catch (Throwable $e) {
                $errors++;
                if (LOG_TASKS === true) $this->logger->log('inactive_users_housekeeping user_id=' . $userId . ' error: ' . $e->getMessage(), 'ERROR');
            }
        }

        $details = [
            'checked' => count($users),
            'warned' => $warned,
            'warned_no_email' => $warnedNoEmail,
            'action_disable' => $actionDisable,
            'action_soft_delete' => $actionSoftDelete,
            'action_hard_delete' => $actionHardDelete,
            'purged' => $purged,
            'errors' => $errors,
            'reset' => $reset,
            'api_activity_backfilled' => $apiActivityBackfilled,
            'inactivity_days' => $inactivityDays,
            'grace_days' => $graceDays,
            'action' => $defaultAction,
        ];

        $msgKey = $errors > 0 ? 'inactive_users_mgmt_msg_task_completed_with_errors' : 'inactive_users_mgmt_msg_task_completed';
        $this->updateInactiveUsersMgmtState('completed', $msgKey, $details);
    }

    private function updateInactiveUsersMgmtState(string $status, string $messageKey, array $details = []): void
    {
        $this->upsertMiscSetting('inactive_users_mgmt_last_status', $status);
        $this->upsertMiscSetting('inactive_users_mgmt_last_message', $messageKey);
        $this->upsertMiscSetting('inactive_users_mgmt_last_details', json_encode($details, JSON_UNESCAPED_SLASHES));
        if (in_array($status, ['completed', 'failed'], true)) {
            $this->upsertMiscSetting('inactive_users_mgmt_last_completed_at', (string)time());
        }
    }

    private function parseUserTs(mixed $value): int
    {
        if ($value === null) return 0;
        $v = trim(strval($value));
        if ($v === '' || $v === '0') return 0;

        if (preg_match('/^[0-9]{13}$/', $v)) return (int) floor(((int)$v) / 1000);
        if (preg_match('/^[0-9]{1,10}$/', $v)) return (int) $v;

        $ts = strtotime($v);
        return $ts !== false ? (int)$ts : 0;
    }

    private function getApiFunctionalActivityByUser(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($id): bool => $id > 0)));
        if (count($userIds) === 0) {
            return [];
        }

        try {
            $rows = DB::query(
                'SELECT id_user, MAX(date) AS last_api_activity
                FROM ' . prefixTable('log_items') . '
                WHERE id_user IN %li
                AND action IN %ls
                AND raison LIKE %ss
                GROUP BY id_user',
                $userIds,
                teampassApiFunctionalActivityActions(),
                'tp_src=api'
            );
        } catch (Throwable $e) {
            if (LOG_TASKS === true) $this->logger->log('inactive_users_housekeeping api activity lookup error: ' . $e->getMessage(), 'ERROR');
            return [];
        }

        $activity = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['id_user'] ?? 0);
            $timestamp = (int) ($row['last_api_activity'] ?? 0);
            if ($userId > 0 && $timestamp > 0) {
                $activity[$userId] = $timestamp;
            }
        }

        return $activity;
    }

    private function applyInactivityAction(int $userId, string $action, int $now): void
    {
        if ($action === 'disable') {
            DB::update(prefixTable('users'), [
                'disabled' => 1,
                'inactivity_action_at' => null,
                'inactivity_action' => null,
            ], 'id = %i', $userId);
            return;
        }

        if ($action === 'soft_delete' || $action === 'hard_delete') {
            $this->softDeleteUserForInactivity($userId, $now);

            if ($action === 'hard_delete') {
                $res = tpPurgeDeletedUserById($userId);
                if (!empty($res['error'])) {
                    throw new Exception('purge_failed');
                }
            }
        }
    }

    private function softDeleteUserForInactivity(int $userId, int $timestamp): void
    {
        $data_user = DB::queryFirstRow(
            'SELECT id, login FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );
        if (empty($data_user) || empty($data_user['login'])) {
            throw new Exception('user_not_found');
        }

        $deletedSuffix = '_deleted_' . $timestamp;

        DB::update(prefixTable('users'), [
            'login' => strval($data_user['login']) . $deletedSuffix,
            'deleted_at' => (string)$timestamp,
            'disabled' => 1,
            'special' => 'none',
            'inactivity_action_at' => null,
            'inactivity_action' => null,
        ], 'id = %i', $userId);
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

    /**
     * Persist the current externalized backup status and message.
     *
     * @return void
     */
    private function updateExternalizedState(string $status, string $message): void
    {
        $this->upsertMiscSetting('bck_externalized_last_status', $status);
        $this->upsertMiscSetting('bck_externalized_last_message', mb_substr($message, 0, 500));
        if (in_array($status, ['completed', 'failed'], true)) {
            $this->upsertMiscSetting('bck_externalized_last_completed_at', (string)time());
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return (string)$bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        $value = (float)$bytes / 1024.0;
        while ($value >= 1024.0 && $i < count($units) - 1) {
            $value /= 1024.0;
            $i++;
        }
        return number_format($value, 1, '.', '') . ' ' . $units[$i];
    }

    /**
     * Escape a value for safe inclusion in an HTML backup report email.
     *
     * @return string
     */
    private function escapeEmailValue(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildScheduledReportContextFromTaskData(): array
    {
        return [
            'file' => (string)($this->taskData['scheduled_backup_file'] ?? $this->taskData['backup_file'] ?? ''),
            'size_bytes' => (int)($this->taskData['scheduled_backup_size_bytes'] ?? $this->taskData['backup_size_bytes'] ?? 0),
            'output_dir' => (string)($this->taskData['scheduled_backup_output_dir'] ?? $this->taskData['output_dir'] ?? ''),
            'retention_days' => (int)($this->taskData['scheduled_backup_retention_days'] ?? $this->getMiscSetting('bck_scheduled_retention_days', '30')),
            'purge_deleted' => (int)($this->taskData['scheduled_backup_purge_deleted'] ?? $this->getMiscSetting('bck_scheduled_last_purge_deleted', '0')),
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildExternalizedReportContext(string $status, string $message, array $overrides = []): array
    {
        $context = [
            'status' => $status,
            'message' => $message,
            'file' => (string)($this->taskData['backup_file'] ?? ''),
            'size_bytes' => (int)($this->taskData['backup_size_bytes'] ?? 0),
            'destination_type' => (string)($this->taskData['destination_type'] ?? $this->getMiscSetting('bck_externalized_destination_type', '')),
            'target' => (string)($this->taskData['output_dir'] ?? $this->getMiscSetting('bck_externalized_target_dir', '')),
            'retention_days' => (int)$this->getMiscSetting('bck_externalized_retention_days', '30'),
            'retention_count' => (int)$this->getMiscSetting('bck_externalized_retention_count', '10'),
            'purge_deleted' => (int)$this->getMiscSetting('bck_externalized_last_purge_deleted', '0'),
            'retry_attempt' => (int)($this->taskData['retry_attempt'] ?? 0),
            'retry_attempts' => (int)($this->taskData['retry_attempts'] ?? 0),
        ];

        return array_merge($context, $overrides);
    }

    /**
     * Build the HTML block describing the externalized backup step for the report email.
     *
     * @param array<string, mixed> $externalizedReport
     * @return string
     */
    private function buildExternalizedReportEmailBlock(Language $lang, array $externalizedReport): string
    {
        if (empty($externalizedReport)) {
            return '';
        }

        $template = (string)$lang->get('email_body_scheduled_backup_externalized_report');
        if ($template === '' || $template === 'email_body_scheduled_backup_externalized_report') {
            $template = '<br><br><b>Externalized backup</b><br><b>Status</b>: #tp_externalized_status#<br><b>Message</b>: #tp_externalized_message#<br><b>Destination</b>: #tp_externalized_destination#<br><b>Target</b>: #tp_externalized_target#<br><b>Backup file</b>: #tp_externalized_file#<br><b>Size</b>: #tp_externalized_size#<br><b>Retention</b>: #tp_externalized_retention_days# day(s), max #tp_externalized_retention_count# file(s)<br><b>Files deleted on last purge</b>: #tp_externalized_purge_deleted#<br><b>Retry</b>: #tp_externalized_retry#';
        }

        $retryAttempt = (int)($externalizedReport['retry_attempt'] ?? 0);
        $retryAttempts = (int)($externalizedReport['retry_attempts'] ?? 0);
        $retry = ($retryAttempt > 0 && $retryAttempts > 0)
            ? $retryAttempt . '/' . $retryAttempts
            : '-';
        $target = (string)($externalizedReport['target'] ?? '');
        $file = (string)($externalizedReport['file'] ?? '');

        return str_replace(
            [
                '#tp_externalized_status#',
                '#tp_externalized_message#',
                '#tp_externalized_destination#',
                '#tp_externalized_target#',
                '#tp_externalized_file#',
                '#tp_externalized_size#',
                '#tp_externalized_retention_days#',
                '#tp_externalized_retention_count#',
                '#tp_externalized_purge_deleted#',
                '#tp_externalized_retry#',
            ],
            [
                $this->escapeEmailValue((string)($externalizedReport['status'] ?? '')),
                $this->escapeEmailValue((string)($externalizedReport['message'] ?? '')),
                $this->escapeEmailValue((string)($externalizedReport['destination_type'] ?? '')),
                $this->escapeEmailValue($target !== '' ? $target : '-'),
                $this->escapeEmailValue($file !== '' ? $file : '-'),
                $this->escapeEmailValue($this->formatBytes((int)($externalizedReport['size_bytes'] ?? 0))),
                $this->escapeEmailValue((string)((int)($externalizedReport['retention_days'] ?? 0))),
                $this->escapeEmailValue((string)((int)($externalizedReport['retention_count'] ?? 0))),
                $this->escapeEmailValue((string)((int)($externalizedReport['purge_deleted'] ?? 0))),
                $this->escapeEmailValue($retry),
            ],
            $template
        );
    }

    /**
     * Queue the consolidated scheduled (and externalized) backup report email.
     *
     * @param array<string, mixed> $externalizedReport
     * @param array<string, mixed> $scheduledReport
     * @return void
     */
    private function queueScheduledBackupReportEmail(string $status, string $message, array $externalizedReport = [], array $scheduledReport = []): void
    {
        $enabled = (int)$this->getMiscSetting('bck_scheduled_email_report_enabled', '0');
        if ($enabled !== 1) {
            return;
        }

        $onlyFailures = (int)$this->getMiscSetting('bck_scheduled_email_report_only_failures', '0');
        if ($onlyFailures === 1 && $status !== 'failed') {
            return;
        }

        $admins = DB::query(
            'SELECT login, email, user_language FROM ' . prefixTable('users') . " WHERE admin = %i AND disabled = %i AND email != ''",
            1,
            0
        );

        if (empty($admins)) {
            return;
        }

        if (empty($scheduledReport)) {
            $scheduledReport = $this->buildScheduledReportContextFromTaskData();
        }

        $backupFile = (string)($scheduledReport['file'] ?? '');
        $sizeBytes = (int)($scheduledReport['size_bytes'] ?? 0);
        $outputDir = (string)($scheduledReport['output_dir'] ?? '');
        $retentionDays = (int)($scheduledReport['retention_days'] ?? $this->getMiscSetting('bck_scheduled_retention_days', '30'));
        $purgeDeleted = (int)($scheduledReport['purge_deleted'] ?? $this->getMiscSetting('bck_scheduled_last_purge_deleted', '0'));

        $dt = date('Y-m-d H:i:s');

        foreach ($admins as $a) {
            $email = strval($a['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $ul = strval($a['user_language'] ?? 'english');
            if ($ul === '' || $ul === '0') {
                $ul = 'english';
            }

            $lang = new Language($ul);

            $subjectTpl = (string)$lang->get('email_subject_scheduled_backup_report');
            if ($subjectTpl === '') {
                $subjectTpl = 'Scheduled backup report: #tp_status#';
            }
            $bodyTpl = (string)$lang->get('email_body_scheduled_backup_report');
            if ($bodyTpl === '') {
                $bodyTpl = 'Hello,<br><br>Status: #tp_status#<br>Message: #tp_message#<br><br>';
            }
            $externalizedBlock = $this->buildExternalizedReportEmailBlock($lang, $externalizedReport);
            if ($externalizedBlock !== '' && str_contains($bodyTpl, '#tp_externalized_report#') === false) {
                if (str_contains($bodyTpl, '#tp_purge_deleted#') === true) {
                    $bodyTpl = str_replace('#tp_purge_deleted#', '#tp_purge_deleted##tp_externalized_report#', $bodyTpl);
                } else {
                    $bodyTpl .= '#tp_externalized_report#';
                }
            }

            $subject = str_replace(['#tp_status#'], [$status], $subjectTpl);

            $body = str_replace(
                ['#tp_status#', '#tp_datetime#', '#tp_message#', '#tp_file#', '#tp_size#', '#tp_output_dir#', '#tp_retention_days#', '#tp_purge_deleted#', '#tp_externalized_report#'],
                [
                    $this->escapeEmailValue((string)$status),
                    $this->escapeEmailValue((string)$dt),
                    $this->escapeEmailValue((string)$message),
                    $this->escapeEmailValue((string)$backupFile),
                    $this->escapeEmailValue($this->formatBytes($sizeBytes)),
                    $this->escapeEmailValue((string)$outputDir),
                    $this->escapeEmailValue((string)$retentionDays),
                    $this->escapeEmailValue((string)$purgeDeleted),
                    $externalizedBlock,
                ],
                $bodyTpl
            );

            $receiverName = strval($a['login'] ?? $lang->get('administrator'));
            prepareSendingEmail($subject, $body, $email, $receiverName);
        }
    }


    private function upsertMiscSetting(string $key, string $value): void
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

        return strval($val);
    }

    /**
     * Decrypt an externalized destination secret stored in settings; '' when unset or invalid.
     *
     * @return string
     */
    private function decryptExternalizedSecretSetting(string $key): string
    {
        $stored = $this->getMiscSetting($key, '');
        if ($stored === '') {
            return '';
        }

        try {
            $decrypted = cryption($stored, '', 'decrypt', $this->settings);
            if (($decrypted['error'] ?? false) === false && isset($decrypted['string'])) {
                return (string) $decrypted['string'];
            }
        } catch (Throwable) {
            return '';
        }

        return '';
    }

    /**
     * Return the stored SFTP destination configuration with its secrets decrypted.
     *
     * @return array<string, mixed>
     */
    private function getExternalizedSftpConfig(): array
    {
        $password = $this->decryptExternalizedSecretSetting('bck_externalized_sftp_password');
        $privateKey = $this->decryptExternalizedSecretSetting('bck_externalized_sftp_private_key');
        $privateKeyPassphrase = $this->decryptExternalizedSecretSetting('bck_externalized_sftp_private_key_passphrase');
        $authType = $this->getMiscSetting('bck_externalized_sftp_auth_type', 'password');
        if ($authType !== 'private_key') {
            $authType = 'password';
        }

        return [
            'host' => $this->getMiscSetting('bck_externalized_sftp_host', ''),
            'port' => max(1, min(65535, (int) $this->getMiscSetting('bck_externalized_sftp_port', '22'))),
            'username' => $this->getMiscSetting('bck_externalized_sftp_username', ''),
            'auth_type' => $authType,
            'password' => $password,
            'private_key' => $privateKey,
            'private_key_passphrase' => $privateKeyPassphrase,
            'password_available' => $password !== '' || $this->getMiscSetting('bck_externalized_sftp_password', '') !== '',
            'private_key_available' => $privateKey !== '' || $this->getMiscSetting('bck_externalized_sftp_private_key', '') !== '',
            'private_key_passphrase_available' => $privateKeyPassphrase !== '' || $this->getMiscSetting('bck_externalized_sftp_private_key_passphrase', '') !== '',
        ];
    }

    /**
     * Return the stored WebDAV destination configuration with its password decrypted.
     *
     * @return array<string, mixed>
     */
    private function getExternalizedWebdavConfig(): array
    {
        $password = $this->decryptExternalizedSecretSetting('bck_externalized_webdav_password');

        return [
            'url' => $this->getMiscSetting('bck_externalized_webdav_url', ''),
            'username' => $this->getMiscSetting('bck_externalized_webdav_username', ''),
            'password' => $password,
            'password_available' => $password !== '' || $this->getMiscSetting('bck_externalized_webdav_password', '') !== '',
        ];
    }

    /**
     * Return the stored S3 destination configuration with its secret key decrypted.
     *
     * @return array<string, mixed>
     */
    private function getExternalizedS3Config(): array
    {
        $secretKey = $this->decryptExternalizedSecretSetting('bck_externalized_s3_secret_key');

        return [
            'endpoint' => $this->getMiscSetting('bck_externalized_s3_endpoint', ''),
            'region' => $this->getMiscSetting('bck_externalized_s3_region', 'us-east-1'),
            'bucket' => $this->getMiscSetting('bck_externalized_s3_bucket', ''),
            'access_key' => $this->getMiscSetting('bck_externalized_s3_access_key', ''),
            'secret_key' => $secretKey,
            'secret_key_available' => $secretKey !== '' || $this->getMiscSetting('bck_externalized_s3_secret_key', '') !== '',
            'path_style' => ((int) $this->getMiscSetting('bck_externalized_s3_path_style', '1') === 1) ? 1 : 0,
        ];
    }

    /**
     * Return the stored configuration for the given externalized destination type.
     *
     * @return array<string, mixed>
     */
    private function getExternalizedDestinationConfig(string $destinationType): array
    {
        if ($destinationType === 'sftp') {
            return $this->getExternalizedSftpConfig();
        }

        if ($destinationType === 'webdav') {
            return $this->getExternalizedWebdavConfig();
        }

        if ($destinationType === 's3') {
            return $this->getExternalizedS3Config();
        }

        return [];
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

        $paths = array_merge(
            glob(rtrim($dir, '/') . '/scheduled-*.sql') ?: [],
            glob(rtrim($dir, '/') . '/scheduled-*.' . tpBackupGetPackageExtension()) ?: []
        );
        foreach ($paths as $file) {
            if (!is_file($file)) {
                continue;
            }

            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                    // Also remove metadata sidecar if present
                    @unlink($file . '.meta.json');
                }
            }
        }

        return $deleted;
    }

    /**
     * Apply retention to externalized backups (by age and count); return the number of files deleted.
     *
     * @param array<string, mixed> $destinationConfig
     * @return int
     */
    private function purgeOldExternalizedBackups(string $dir, int $retentionDays, int $retentionCount, string $destinationType = 'local_directory', array $destinationConfig = []): int
    {
        $purge = tpBackupPurgeExternalizedBackups($destinationType, $dir, $retentionDays, $retentionCount, $this->settings, $destinationConfig);
        if ($purge['success'] === true) {
            return (int) $purge['deleted'];
        }

        if (LOG_TASKS === true) {
            $this->logger->log(
                'externalized_backup: retention skipped for destination_type=' . $destinationType . ' reason=' . (string) $purge['reason'],
                'WARNING'
            );
        }

        return 0;
    }

    /**
     * Emit a WebSocket event for item encryption tasks (new_item, item_copy, item_update_create_keys).
     * No-op for other task types or when no valid author is found.
     */
    private function emitItemEncryptionEvent(string $status): void
    {
        if (!in_array($this->processType, ['new_item', 'item_copy', 'item_update_create_keys'], true)) {
            return;
        }

        $authorId = (int) ($this->taskData['author'] ?? 0);
        if ($authorId <= 0) {
            return;
        }

        $messages = [
            'completed' => 'Item encryption keys generated successfully',
            'failed'    => 'Failed to generate item encryption keys',
        ];

        emitWebSocketEvent(
            'task_completed',
            'user',
            $authorId,
            [
                'task_id'      => $this->taskId,
                'task_type'    => 'Item encryption',
                'status'       => $status,
                'message'      => $messages[$status] ?? '',
                'item_id'      => (int) ($this->taskData['item_id'] ?? 0),
                'process_type' => $this->processType,
            ]
        );
    }

    /**
     * Build the anonymized arguments JSON string to store on task completion.
     * Sensitive data (passwords, keys) is intentionally excluded.
     */
    private function buildCompletionArguments(): string
    {
        if ($this->processType === 'send_email') {
            return (string) json_encode([
                'email' => $this->taskData['receivers'],
                'login' => $this->taskData['receiver_name'],
            ]);
        }

        if ($this->processType === 'create_user_keys' || $this->processType === 'migrate_user_personal_items') {
            return (string) json_encode([
                'user_id' => $this->taskData['new_user_id'],
            ]);
        }

        if ($this->processType === 'item_update_create_keys') {
            return (string) json_encode([
                'item_id' => $this->taskData['item_id'],
                'author'  => $this->taskData['author'],
            ]);
        }

        if ($this->processType === 'database_backup' || $this->processType === 'externalized_backup') {
            $payload = [
                'file'       => $this->taskData['backup_file'] ?? '',
                'size_bytes' => $this->taskData['backup_size_bytes'] ?? 0,
                'encrypted'  => $this->taskData['backup_encrypted'] ?? false,
                'format'     => $this->taskData['backup_format'] ?? '',
                'include_documents' => $this->taskData['include_documents'] ?? false,
            ];

            if ($this->processType === 'externalized_backup') {
                $payload['destination_type'] = $this->taskData['destination_type'] ?? '';
                $payload['retry_attempt'] = $this->taskData['retry_attempt'] ?? 1;
                $payload['retry_attempts'] = $this->taskData['retry_attempts'] ?? 1;
            }

            return (string) json_encode($payload);
        }

        return '';
    }

    private function completeTask(): void {
        // Prepare data for updating the task status
        $updateData = [
            'is_in_progress' => -1,
            'finished_at' => time(),
            'status' => 'completed',
            'error_message' => null,   // <-- on efface toute erreur précédente
        ];

        // Anonymize arguments for storage
        $arguments = $this->buildCompletionArguments();

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

        // Notify target user for task types not covered by emitItemEncryptionEvent()
        if ($this->processType === 'create_user_keys') {
            $targetUserId = (int) ($this->taskData['new_user_id'] ?? 0);
            if ($targetUserId > 0) {
                // user_keys_ready triggers a page reload so the user can access encrypted content
                emitWebSocketEvent('user_keys_ready', 'user', $targetUserId, [
                    'task_id'   => $this->taskId,
                    'task_type' => $this->processType,
                    'status'    => 'completed',
                ]);
            }
        } elseif ($this->processType === 'migrate_user_personal_items') {
            $targetUserId = (int) ($this->taskData['new_user_id'] ?? 0);
            if ($targetUserId > 0) {
                emitTaskProgress(
                    $targetUserId,
                    (string) $this->taskId,
                    $this->processType,
                    1,
                    1,
                    'completed',
                    'Personal items migration completed'
                );
            }
        }

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
    private function handleTaskFailure(Throwable $e): void {
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

        $this->emitItemEncryptionEvent('failed');

        // If a scheduled backup failed, update scheduler state and optionally send email report (via background tasks)
        if ($this->processType === 'database_backup' && (string)($this->taskData['source'] ?? '') === 'scheduler') {
            try {
                $this->updateSchedulerState('failed', $e->getMessage());
                $this->queueScheduledBackupReportEmail('failed', $e->getMessage());
            } catch (Throwable) {
                // best effort only
            }
        }

        if ($this->processType === 'externalized_backup') {
            try {
                $this->updateExternalizedState('failed', $e->getMessage());
                if (
                    (string)($this->taskData['source'] ?? '') === 'scheduled_post_backup'
                    && empty($this->taskData['scheduled_post_backup_report_sent'])
                ) {
                    $scheduledFile = (string)($this->taskData['scheduled_backup_file'] ?? '');
                    $this->queueScheduledBackupReportEmail(
                        'failed',
                        'Backup created: ' . $scheduledFile . '. Externalized backup failed: ' . $e->getMessage(),
                        $this->buildExternalizedReportContext('failed', $e->getMessage()),
                        $this->buildScheduledReportContextFromTaskData()
                    );
                    $this->taskData['scheduled_post_backup_report_sent'] = true;
                }
            } catch (Throwable) {
                // best effort only
            }
        }

        // Purge retention even on failure (safe: only files matching the task source prefix)
        $backupDir = (string)($this->taskData['output_dir'] ?? '');
        if ($backupDir !== '') {
            if ($this->processType === 'database_backup' && (string)($this->taskData['source'] ?? '') === 'scheduler') {
                if (is_dir($backupDir) === true) {
                    $days = (int)$this->getMiscSetting('bck_scheduled_retention_days', '30');
                    $deleted = $this->purgeOldScheduledBackups($backupDir, $days);

                    $this->upsertMiscSetting('bck_scheduled_last_purge_at', (string)time());
                    $this->upsertMiscSetting('bck_scheduled_last_purge_deleted', (string)$deleted);
                }
            } elseif ($this->processType === 'externalized_backup') {
                $days = max(1, (int)$this->getMiscSetting('bck_externalized_retention_days', '30'));
                $count = max(1, (int)$this->getMiscSetting('bck_externalized_retention_count', '10'));
                $destinationType = (string)($this->taskData['destination_type'] ?? 'local_directory');
                $destinationConfig = $this->getExternalizedDestinationConfig($destinationType);
                if (tpBackupExternalizedDestinationTypeIsRemote($destinationType) === true || is_dir($backupDir) === true) {
                    $deleted = $this->purgeOldExternalizedBackups($backupDir, $days, $count, $destinationType, $destinationConfig);

                    $this->upsertMiscSetting('bck_externalized_last_purge_at', (string)time());
                    $this->upsertMiscSetting('bck_externalized_last_purge_deleted', (string)$deleted);
                }
            }
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
    private function processSubTasks(array $arguments): void {
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

                if (LOG_TASKS=== true) $this->logger->log('Processing subtask: ' . strval($subtaskData['step'] ?? ''), 'DEBUG');

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
                        throw new Exception("Type de sous-tâche inconnu (" . strval($subtaskData['step'] ?? '') . ")");
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
    
        if (intval($remainingSubtasks) === 0) {
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
