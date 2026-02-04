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
 * @file      PhpseclibV3MigrationTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\CryptoManager\CryptoManager;

/**
 * Trait for phpseclib v1 to v3 forced migration handling
 *
 * This trait handles the complete migration of all user sharekeys from
 * phpseclib v1 (SHA-1) to v3 (SHA-256) when triggered at login.
 */
trait PhpseclibV3MigrationTrait {
    abstract protected function completeTask();

    /**
     * Main migration handler for phpseclib v3 migration
     *
     * Processes all subtasks to migrate user sharekeys from v1 to v3
     *
     * @param array $arguments Task arguments containing user_id, credentials, etc.
     * @return void
     */
    private function migratePhpseclibV3($arguments): void
    {
        // Get all subtasks for this task
        $subtasks = DB::query(
            'SELECT * FROM ' . prefixTable('background_subtasks') . '
             WHERE task_id = %i AND is_in_progress = 0
             ORDER BY increment_id ASC',
            $this->taskId
        );

        if (empty($subtasks)) {
            if (LOG_TASKS === true) {
                $this->logger->log("No subtask found for phpseclibv3_migration task {$this->taskId}");
            }
            return;
        }

        // Process each subtask
        foreach ($subtasks as $subtask) {
            if (LOG_TASKS === true) {
                $this->logger->log("Processing phpseclibv3_migration subtask {$subtask['increment_id']} for task {$this->taskId}");
            }
            $this->processPhpseclibV3MigrationSubtask($subtask, $arguments);
        }

        // Check if all subtasks are completed
        $remainingSubtasks = DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('background_subtasks') . '
             WHERE task_id = %i AND is_in_progress = 0',
            $this->taskId
        );

        if ($remainingSubtasks == 0) {
            $this->completeTask();
        }
    }

    /**
     * Process a single subtask for phpseclib v3 migration
     *
     * @param array $subtask Subtask data from database
     * @param array $arguments Task arguments
     * @return void
     */
    private function processPhpseclibV3MigrationSubtask(array $subtask, array $arguments): void
    {
        try {
            $taskData = json_decode($subtask['task'], true);

            // Mark subtask as in progress
            DB::update(
                prefixTable('background_subtasks'),
                [
                    'is_in_progress' => 1,
                    'updated_at' => time(),
                    'status' => 'in progress'
                ],
                'increment_id = %i',
                $subtask['increment_id']
            );

            if (LOG_TASKS === true) {
                $this->logger->log("phpseclibv3_migration subtask in progress: " . ($taskData['step'] ?? 'unknown'), 'INFO');
            }

            // Route to appropriate handler based on step
            switch ($taskData['step'] ?? '') {
                case 'migrate_sharekeys_table':
                    $this->migrateSharekeysTable($taskData, $arguments);
                    break;

                case 'finalize_phpseclibv3_migration':
                    $this->finalizePhpseclibV3Migration($arguments);
                    break;

                default:
                    throw new Exception("Unknown phpseclibv3_migration step: " . ($taskData['step'] ?? 'none'));
            }

            // Mark subtask as completed
            DB::update(
                prefixTable('background_subtasks'),
                [
                    'is_in_progress' => -1,
                    'finished_at' => time(),
                    'updated_at' => time(),
                    'status' => 'completed'
                ],
                'increment_id = %i',
                $subtask['increment_id']
            );

            if (LOG_TASKS === true) {
                $this->logger->log("phpseclibv3_migration subtask completed: " . ($taskData['step'] ?? 'unknown'), 'SUCCESS');
            }

        } catch (Exception $e) {
            // Mark subtask as failed
            DB::update(
                prefixTable('background_subtasks'),
                [
                    'is_in_progress' => -1,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => time(),
                ],
                'increment_id = %i',
                $subtask['increment_id']
            );

            if (LOG_TASKS === true) {
                $this->logger->log("phpseclibv3_migration subtask failed: " . $e->getMessage(), 'ERROR');
            }

            throw $e;
        }
    }

    /**
     * Migrate sharekeys for a specific table
     *
     * This function:
     * 1. Fetches sharekeys with encryption_version = 1 for the user
     * 2. Decrypts each sharekey with user's private key (v1)
     * 3. Re-encrypts each sharekey with user's public key (v3)
     * 4. Updates encryption_version to 3 in database
     *
     * @param array $taskData Task configuration (table, offset, limit, etc.)
     * @param array $arguments Task arguments (user_id, credentials)
     * @return void
     * @throws Exception
     */
    private function migrateSharekeysTable(array $taskData, array $arguments): void
    {
        // Decrypt arguments
        $userId = (int) $arguments['user_id'];
        $userPwd = cryption($arguments['user_pwd'], '', 'decrypt')['string'];
        $userPrivateKey = cryption($arguments['user_private_key'], '', 'decrypt')['string'];

        $table = $taskData['table'];
        $limit = (int) ($taskData['limit'] ?? 100);
        $batch = (int) ($taskData['batch'] ?? 1);
        $totalBatches = (int) ($taskData['total_batches'] ?? 1);

        if (LOG_TASKS === true) {
            $this->logger->log(
                "Migrating {$table} - batch {$batch}/{$totalBatches} (limit: {$limit}, always starting from first v1 sharekey)",
                'INFO'
            );
        }

        // Get user's public key
        $userPublicKey = DB::queryFirstField(
            'SELECT public_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );

        if (empty($userPublicKey)) {
            throw new Exception("User {$userId} has no public key");
        }

        // Fetch sharekeys to migrate (encryption_version = 1)
        // IMPORTANT: Always use OFFSET 0 because as we migrate sharekeys,
        // they disappear from the WHERE encryption_version = 1 filter.
        // So the "remaining" sharekeys are always at the beginning.
        $sharekeys = DB::query(
            'SELECT increment_id, share_key, user_id, object_id
             FROM ' . prefixTable($table) . '
             WHERE user_id = %i AND encryption_version = 1
             ORDER BY increment_id ASC
             LIMIT %i',
            $userId,
            $limit
        );

        if (empty($sharekeys)) {
            if (LOG_TASKS === true) {
                $this->logger->log("No sharekeys to migrate in {$table} batch {$batch}", 'INFO');
            }
            return;
        }

        $migratedCount = 0;
        $errorCount = 0;

        foreach ($sharekeys as $sharekey) {
            try {
                // Decrypt sharekey with user's private key (v1 - SHA-1)
                // Uses CryptoManager::rsaDecrypt with tryLegacy=true (tries v3 first, then v1)
                $decryptedSharekey = CryptoManager::rsaDecrypt(
                    base64_decode($sharekey['share_key']),
                    $userPrivateKey,
                    true // tryLegacy - allows v1 decryption with SHA-1
                );

                // Re-encrypt sharekey with user's public key (v3 - SHA-256)
                $reencryptedSharekey = CryptoManager::rsaEncrypt(
                    $decryptedSharekey,
                    $userPublicKey
                );

                // Update sharekey in database with new encryption
                DB::update(
                    prefixTable($table),
                    [
                        'share_key' => base64_encode($reencryptedSharekey),
                        'encryption_version' => 3,
                    ],
                    'increment_id = %i',
                    $sharekey['increment_id']
                );

                $migratedCount++;

            } catch (Exception $e) {
                $errorCount++;

                if (LOG_TASKS === true) {
                    $this->logger->log(
                        "Failed to migrate sharekey {$sharekey['increment_id']} in {$table}: " . $e->getMessage(),
                        'ERROR'
                    );
                }

                // Continue with next sharekey instead of failing entire batch
                continue;
            }
        }

        if (LOG_TASKS === true) {
            $this->logger->log(
                "Migrated {$migratedCount} sharekeys in {$table} batch {$batch}/{$totalBatches} " .
                ($errorCount > 0 ? "({$errorCount} errors)" : ""),
                'SUCCESS'
            );
        }
    }

    /**
     * Finalize phpseclib v3 migration
     *
     * Checks if all sharekeys have been migrated before marking user as complete.
     * Only marks the user as fully migrated if no v1 sharekeys remain.
     *
     * @param array $arguments Task arguments containing user_id
     * @return void
     */
    private function finalizePhpseclibV3Migration(array $arguments): void
    {
        $userId = (int) $arguments['user_id'];

        // Check if there are remaining v1 sharekeys for this user
        $sharekeysTablesV1Count = [];
        $totalV1Remaining = 0;

        $sharekeysTablesList = [
            'sharekeys_items',
            'sharekeys_fields',
            'sharekeys_files',
            'sharekeys_logs',
            'sharekeys_suggestions',
        ];

        foreach ($sharekeysTablesList as $table) {
            $count = DB::queryFirstField(
                'SELECT COUNT(*) FROM ' . prefixTable($table) . '
                 WHERE user_id = %i AND encryption_version = 1',
                $userId
            );
            $sharekeysTablesV1Count[$table] = (int) $count;
            $totalV1Remaining += (int) $count;
        }

        // Log remaining v1 sharekeys
        if (LOG_TASKS === true) {
            $this->logger->log(
                "phpseclibv3_migration finalize check for user {$userId}: " .
                "v1 remaining = {$totalV1Remaining} (" . json_encode($sharekeysTablesV1Count) . ")",
                'INFO'
            );
        }

        // Only mark as completed if ALL sharekeys are migrated
        if ($totalV1Remaining === 0) {
            DB::update(
                prefixTable('users'),
                [
                    'phpseclibv3_migration_completed' => 1,
                    'phpseclibv3_migration_task_id' => null,
                ],
                'id = %i',
                $userId
            );

            if (LOG_TASKS === true) {
                $this->logger->log(
                    "phpseclibv3_migration completed for user {$userId} - all sharekeys migrated",
                    'SUCCESS'
                );
            }

            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log("TEAMPASS phpseclibv3_migration - User {$userId} migration completed successfully");
            }
        } else {
            // Some sharekeys failed to migrate - don't mark as completed
            // Clear the task_id so user can retry on next login
            DB::update(
                prefixTable('users'),
                [
                    'phpseclibv3_migration_completed' => 0,
                    'phpseclibv3_migration_task_id' => null,
                ],
                'id = %i',
                $userId
            );

            if (LOG_TASKS === true) {
                $this->logger->log(
                    "phpseclibv3_migration incomplete for user {$userId} - {$totalV1Remaining} v1 sharekeys remaining",
                    'WARNING'
                );
            }

            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log("TEAMPASS phpseclibv3_migration - User {$userId} has {$totalV1Remaining} v1 sharekeys remaining - migration incomplete");
            }
        }
    }
}
