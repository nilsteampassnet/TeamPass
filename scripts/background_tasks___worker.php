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
use TeampassClasses\Language\Language;
require_once __DIR__.'/../sources/main.functions.php';
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
                case 'phpseclibv3_migration':
                    $this->migratePhpseclibV3($this->taskData);
                    break;
                case 'database_backup':
                    $this->handleDatabaseBackup($this->taskData);
                    break;
                case 'inactive_users_housekeeping':
                    $this->handleInactiveUsersHousekeeping($this->taskData);
                    break;
                default:
                    throw new Exception("Type of subtask unknown: {$this->processType}");
            }

            // Mark the task as completed
            try {
                $this->completeTask();

                // Emit WebSocket event for item encryption task completion
                if (in_array($this->processType, ['new_item', 'item_copy', 'item_update_create_keys'], true)) {
                    $authorId = (int) ($this->taskData['author'] ?? 0);
                    if ($authorId > 0) {
                        emitWebSocketEvent(
                            'task_completed',
                            'user',
                            $authorId,
                            [
                                'task_id' => $this->taskId,
                                'task_type' => 'Item encryption',
                                'status' => 'completed',
                                'message' => 'Les clés de chiffrement ont été générées avec succès',
                                'item_id' => (int) ($this->taskData['item_id'] ?? 0),
                                'process_type' => $this->processType,
                            ]
                        );
                    }
                }
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

        
// Best effort: write metadata sidecar next to the backup file
try {
    if (!empty($res['filepath']) && is_string($res['filepath']) && function_exists('tpWriteBackupMetadata')) {
        tpWriteBackupMetadata((string) $res['filepath'], '', '', ['source' => 'scheduled']);
    }
} catch (Throwable $ignored) {
    // do not block backups if metadata cannot be written
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
            try {
                $this->queueScheduledBackupReportEmail('completed', 'Backup created: ' . ($this->taskData['backup_file'] ?? ''));
            } catch (Throwable $ignored) {
                // best effort only - never block the backup process
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
     * Housekeeping: warn inactive users then disable/soft-delete/hard-delete after grace period.
     */
    private function handleInactiveUsersHousekeeping(array $taskData): void
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
        $excludeIds = array_values(array_unique(array_filter($excludeIds, fn($v) => (int)$v > 0)));

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

        $warnCutoff = $now - ($inactivityDays * 86400);

        $warned = 0;
        $warnedNoEmail = 0;
        $actionDisable = 0;
        $actionSoftDelete = 0;
        $actionHardDelete = 0;
        $purged = 0;
        $errors = 0;
        $reset = 0;

        foreach ($users as $u) {
            $userId = (int)($u['id'] ?? 0);
            if ($userId <= 0) continue;

            try {
                $lastConnexionTs = $this->parseUserTs($u['last_connexion'] ?? null);
                $createdAtTs = $this->parseUserTs($u['created_at'] ?? null);
                $lastActivityTs = $lastConnexionTs > 0 ? $lastConnexionTs : ($createdAtTs > 0 ? $createdAtTs : 0);

                $warnedAt = $this->parseUserTs($u['inactivity_warned_at'] ?? null);
                $actionAt = $this->parseUserTs($u['inactivity_action_at'] ?? null);
                $storedAction = (string)($u['inactivity_action'] ?? '');

                // Reset tracking if user logged in after a warning
                if ($warnedAt > 0 && $lastConnexionTs > 0 && $lastConnexionTs > $warnedAt) {
                    DB::update(prefixTable('users'), [
                        'inactivity_warned_at' => null,
                        'inactivity_action_at' => null,
                        'inactivity_action' => null,
                        'inactivity_no_email' => 0,
                    ], 'id = %i', $userId);
                    $reset++;
                    continue;
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
                    $email = trim((string)($u['email'] ?? ''));
                    $noEmail = 0;

                    if ($email === '') {
                        $noEmail = 1;
                        $warnedNoEmail++;
                    } else {
                        $userLang = trim((string)($u['user_language'] ?? ''));
                        if ($userLang === '' || $userLang === '0') $userLang = 'english';
                        $langUser = new Language($userLang);

                        $receiverName = trim((string)($u['name'] ?? '') . ' ' . (string)($u['lastname'] ?? ''));
                        $firstName = trim((string)($u['name'] ?? ''));
                        if ($firstName === '') {
                            $firstName = trim((string)($u['lastname'] ?? ''));
                        }
                        if ($firstName === '') {
                            $firstName = (string)($u['login'] ?? '');
                        }
                        if ($receiverName === '') $receiverName = (string)($u['login'] ?? '');

                        $subject = (string)$langUser->get('inactive_users_mgmt_email_subject');
                        $bodyTpl = (string)$langUser->get('inactive_users_mgmt_email_body');
                        $actionLabel = (string)$langUser->get('inactive_users_mgmt_action_' . $defaultAction);

                        $tpUrl = (string)($this->settings['cpassman_url'] ?? '');
                        $body = str_replace(
                            ['#login#', '#firstname#', '#lastname#', '#inactivity_days#', '#grace_days#', '#action#', '#url#'],
                            [(string)($u['login'] ?? ''), $firstName, $firstName, (string)$inactivityDays, (string)$graceDays, $actionLabel, $tpUrl],
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

    private function parseUserTs($value): int
    {
        if ($value === null) return 0;
        $v = trim((string)$value);
        if ($v === '' || $v === '0') return 0;

        if (preg_match('/^[0-9]{13}$/', $v)) return (int) floor(((int)$v) / 1000);
        if (preg_match('/^[0-9]{1,10}$/', $v)) return (int) $v;

        $ts = strtotime($v);
        return $ts !== false ? (int)$ts : 0;
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
            'login' => (string)$data_user['login'] . $deletedSuffix,
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

    private function queueScheduledBackupReportEmail(string $status, string $message): void
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

        $backupFile = (string)($this->taskData['backup_file'] ?? '');
        $sizeBytes = (int)($this->taskData['backup_size_bytes'] ?? 0);
        $outputDir = (string)($this->taskData['output_dir'] ?? '');
        $retentionDays = (int)$this->getMiscSetting('bck_scheduled_retention_days', '30');
        $purgeDeleted = (int)$this->getMiscSetting('bck_scheduled_last_purge_deleted', '0');

        $dt = date('Y-m-d H:i:s');

        foreach ($admins as $a) {
            $email = (string)($a['email'] ?? '');
            if ($email === '') {
                continue;
            }

            $ul = (string)($a['user_language'] ?? 'english');
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

            $subject = str_replace(['#tp_status#'], [$status], $subjectTpl);

            $body = str_replace(
                ['#tp_status#', '#tp_datetime#', '#tp_message#', '#tp_file#', '#tp_size#', '#tp_output_dir#', '#tp_retention_days#', '#tp_purge_deleted#'],
                [
                    htmlspecialchars((string)$status, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars((string)$dt, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars((string)$message, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars((string)$backupFile, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars($this->formatBytes($sizeBytes), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars((string)$outputDir, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars((string)$retentionDays, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    htmlspecialchars((string)$purgeDeleted, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ],
                $bodyTpl
            );

            $receiverName = (string)($a['login'] ?? $lang->get('administrator'));
            prepareSendingEmail($subject, $body, $email, $receiverName);
        }
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
                    // Also remove metadata sidecar if present
                    @unlink($file . '.meta.json');
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
        if ($this->processType === 'send_email') {
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

        // Emit WebSocket event for item encryption task failure
        if (in_array($this->processType, ['new_item', 'item_copy', 'item_update_create_keys'], true)) {
            $authorId = (int) ($this->taskData['author'] ?? 0);
            if ($authorId > 0) {
                emitWebSocketEvent(
                    'task_completed',
                    'user',
                    $authorId,
                    [
                        'task_id' => $this->taskId,
                        'task_type' => 'Chiffrement',
                        'status' => 'failed',
                        'message' => 'Erreur lors de la génération des clés de chiffrement',
                        'item_id' => (int) ($this->taskData['item_id'] ?? 0),
                        'process_type' => $this->processType,
                    ]
                );
            }
        }

        // If a scheduled backup failed, update scheduler state and optionally send email report (via background tasks)
        if ($this->processType === 'database_backup' && (string)($this->taskData['source'] ?? '') === 'scheduler') {
            try {
                $this->updateSchedulerState('failed', $e->getMessage());
                $this->queueScheduledBackupReportEmail('failed', $e->getMessage());
            } catch (Throwable $ignored) {
                // best effort only
            }
        }

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
