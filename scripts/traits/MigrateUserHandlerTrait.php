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
 * @file      MigrateUserHandlerTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Trait for user migration handling
 * 
 * Requires UserHandlerTrait to be used in the same class
 * 
 * @method array getOwnerInfos(int $owner_id, string $owner_pwd, ?int $only_personal_items = 0, ?string $owner_private_key = '') Get owner information (from UserHandlerTrait)
 */
trait MigrateUserHandlerTrait {
    abstract protected function completeTask();

    /**
     * Generate user keys
     * @param array $taskData Données de la tâche
     * @param array $arguments Arguments nécessaires pour la création des clés
     * @return void
     */
    private function migratePersonalItems($arguments) {
        // Get all subtasks related to this task
        $subtasks = DB::query(
            'SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE task_id = %i AND is_in_progress = 0 ORDER BY `task` ASC',
            $this->taskId
        );
    
        if (empty($subtasks)) {
            if (LOG_TASKS=== true) $this->logger->log("No subtask was found for task {$this->taskId}");
            return;
        }
    
        // Process each subtask
        foreach ($subtasks as $subtask) {
            if (LOG_TASKS=== true) $this->logger->log("Processing subtask {$subtask['increment_id']} for task {$this->taskId}");
            $this->processMigratePersonalItemsSubtask($subtask, $arguments);
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
    

    /**
     * Process a subtask for generating user keys.
     * @param array $subtask The subtask to process.
     * @param array $arguments Arguments for the task.
     * @return void
     */
    private function processMigratePersonalItemsSubtask(array $subtask, array $arguments) {
        try {
            $taskData = json_decode($subtask['task'], true);
            
            // Mark the subtask as in progress
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
            
            if (LOG_TASKS=== true) $this->logger->log("Subtask is in progress: ".$taskData['step'], 'INFO');
            switch ($taskData['step'] ?? '') {
                case 'user-personal-items-migration-step10':
                    $this->migratePersonalItemsStep10($taskData, $arguments);
                    break;
                case 'user-personal-items-migration-step20':
                    $this->migratePersonalItemsStep20($taskData, $arguments);
                    break;
                case 'user-personal-items-migration-step30':
                    $this->migratePersonalItemsStep30($taskData, $arguments);
                    break;
                case 'user-personal-items-migration-step40':
                    $this->migratePersonalItemsStep40($taskData, $arguments);
                    break;
                case 'user-personal-items-migration-step50':
                    $this->migratePersonalItemsStep50($taskData, $arguments);
                    break;
                case 'user-personal-items-migration-step-final':
                    $this->migratePersonalItemsStepFinal($arguments);
                    break;
                break;
                default:
                    throw new Exception("Type of subtask unknown: {$this->processType}");
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
            // Failure handling
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
            
            $this->logger->log("Subtask {$subtask['increment_id']} failure: " . $e->getMessage(), 'ERROR');
        }
    }
    

    /**
     * Generate new user keys
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     */
    private function migratePersonalItemsStep10($taskData, $arguments): void
    {
        // get user private key
        $userInfo = $this->getOwnerInfos(
            $arguments['user_id'],
            $arguments['user_pwd'],
            1,
            $arguments['user_private_key'] ?? ''
        );

        // get TP_USER private key
        $userTP = DB::queryFirstRow(
            'SELECT pw, public_key, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            TP_USER_ID
        );

        // Start transaction for better performance
        DB::startTransaction();

        // Loop on items
        $rows = DB::query(
            'SELECT id, pw
            FROM ' . prefixTable('items') . '
            WHERE perso =  1
            AND id_tree IN %li
            ORDER BY id ASC
            LIMIT %i, %i',
            json_decode($arguments['personal_folders_ids']),
            $taskData['index'],
            $taskData['nb']
        );
        
        foreach ($rows as $item) {
            // Get itemKey from current user
            $shareKeyForItem = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE object_id = %i AND user_id = %i',
                $item['id'],
                (int) $arguments['user_id']
            );

            // do we have any input? (#3481)
            if ($shareKeyForItem === null || count($shareKeyForItem) === 0) {
                continue;
            }

            // Decrypt itemkey with user private key
            $itemKey = decryptUserObjectKey($shareKeyForItem['share_key'], $userInfo['private_key']);
            
            // Now create sharekey for user
            $this->createUserShareKey(
                'sharekeys_items',
                (string) $itemKey,
                (string) $userInfo['public_key'],
                (int) $item['id'],
                (int) $arguments['user_id']
            );

            // Now create sharekey for TP_USER
            $this->createUserShareKey(
                'sharekeys_items',
                (string) $itemKey,
                (string) $userTP['public_key'],
                (int) $item['id'],
                (int) TP_USER_ID
            );
        }

        // Commit transaction
        DB::commit();
    }

    /**
     * Create the sharekey for user
     * 
     * @param string $table Table name (sharekeys_items, sharekeys_fields, sharekeys_files, sharekeys_suggestions)
     * @param string $itemKey Item encryption key in hexadecimal format
     * @param string $publicKey User's RSA public key (PEM format)
     * @param int $recordId Record ID (object_id in sharekeys table)
     * @param int $userId User ID who will receive access to the encrypted key
     * @return void
     */
    private function createUserShareKey(string $table, string $itemKey, string $publicKey, int $recordId, int $userId): void
    {
        // Prevent to change key if its key is empty
        if (empty($itemKey) === true) {
            $share_key_for_item = '';
        } else {
            // Encrypt Item key
            $share_key_for_item = encryptUserObjectKey($itemKey, $publicKey);
        }
        
        // Save the new sharekey correctly encrypted in DB
        $affected = DB::update(
            prefixTable($table),
            array(
                'object_id' => $recordId,
                'user_id' => $userId,
                'share_key' => $share_key_for_item,
            ),
            'object_id = %i AND user_id = %i',
            $recordId,
            $userId
        );
        
        // If now row was updated, it means the user has no key for this item, so we need to insert it
        if ($affected === 0) {
            DB::insert(
                prefixTable($table),
                array(
                    'object_id' => $recordId,
                    'user_id' => $userId,
                    'share_key' => $share_key_for_item,
                )
            );
        }
    }


    /**
     * Generate new user keys - step 20
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @return void
     */
    private function migratePersonalItemsStep20($taskData, $arguments) {
        // get user private key
        $userInfo = $this->getOwnerInfos(
            $arguments['user_id'],
            $arguments['user_pwd'],
            1,
            $arguments['user_private_key'] ?? ''
        );

        // get TP_USER private key
        $userTP = DB::queryFirstRow(
            'SELECT pw, public_key, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            TP_USER_ID
        );

        // Start transaction for better performance
        DB::startTransaction();

        // Loop on logs
        $rows = DB::query(
            'SELECT increment_id
            FROM ' . prefixTable('log_items') . '
            WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"
            ORDER BY increment_id ASC
            LIMIT ' . $taskData['index'] . ', ' . $taskData['nb']
        );
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_logs') . '
                WHERE object_id = %i AND user_id = %i',
                $record['increment_id'],
                $arguments['user_id']
            );

            // do we have any input? (#3481)
            if ($currentUserKey === null || count($currentUserKey) === 0) {
                continue;
            }

            // Decrypt itemkey with admin key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $userInfo['private_key']);
            
            // Now create sharekey for user
            $this->createUserShareKey(
                'sharekeys_logs',
                (string) $itemKey,
                (string) $userInfo['public_key'],
                (int) $record['id'],
                (int) $arguments['user_id'],
            );

            // Now create sharekey for TP_USER
            $this->createUserShareKey(
                'sharekeys_logs',
                (string) $itemKey,
                (string) $userTP['public_key'],
                (int) $record['id'],
                (int) TP_USER_ID,
            );
        }

        // Commit transaction
        DB::commit();
    }


    /**
     * Generate new user keys - step 30
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @return void
     */
    private function migratePersonalItemsStep30($taskData, $arguments) {
        // get user private key
        $userInfo = $this->getOwnerInfos(
            $arguments['user_id'],
            $arguments['user_pwd'],
            1,
            $arguments['user_private_key'] ?? ''
        );

        // get TP_USER private key
        $userTP = DB::queryFirstRow(
            'SELECT pw, public_key, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            TP_USER_ID
        );

        // Start transaction for better performance
        DB::startTransaction();

        // Loop on fields
        $rows = DB::query(
            'SELECT id
            FROM ' . prefixTable('categories_items') . '
            WHERE encryption_type = "teampass_aes"
            ORDER BY id ASC
            LIMIT %i, %i',
            $taskData['index'],
            $taskData['nb']
        );
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_fields') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $arguments['user_id']
            );

            if (isset($currentUserKey['share_key']) === true) {
                // Decrypt itemkey with user key
                $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $userInfo['private_key']);

                // Now create sharekey for user
                $this->createUserShareKey(
                    'sharekeys_fields',
                    (string) $itemKey,
                    (string) $userInfo['public_key'],
                    (int) $record['id'],
                    (int) $arguments['user_id']
                );

                // Now create sharekey for TP_USER
                $this->createUserShareKey(
                    'sharekeys_fields',
                    (string) $itemKey,
                    (string) $userTP['public_key'],
                    (int) $record['id'],
                    (int) TP_USER_ID
                );
            }
        }

        // Commit transaction
        DB::commit();
    }


    /**
     * Generate new user keys - step 40
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @return void
     */
    private function migratePersonalItemsStep40($taskData, $arguments) {
        // get user private key
        $userInfo = $this->getOwnerInfos(
            $arguments['user_id'],
            $arguments['user_pwd'],
            1,
            $arguments['user_private_key'] ?? ''
        );

        // get TP_USER private key
        $userTP = DB::queryFirstRow(
            'SELECT pw, public_key, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            TP_USER_ID
        );

        // Start transaction for better performance
        DB::startTransaction();

        // Loop on suggestions
        $rows = DB::query(
            'SELECT id
            FROM ' . prefixTable('suggestion') . '
            ORDER BY id ASC
            LIMIT %i, %i',
            $taskData['index'],
            $taskData['nb']
        );
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_suggestions') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $arguments['user_id']
            );

            // do we have any input? (#3481)
            if ($currentUserKey === null || count($currentUserKey) === 0) {
                continue;
            }

            // Decrypt itemkey with user key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $userInfo['private_key']);

            // Now create sharekey for user
            $this->createUserShareKey(
                'sharekeys_suggestions',
                (string) $itemKey,
                (string) $userInfo['public_key'],
                (int) $record['id'],
                (int) $arguments['user_id'],
            );

            // Now create sharekey for TP_USER
            $this->createUserShareKey(
                'sharekeys_fields',
                (string) $itemKey,
                (string) $userTP['public_key'],
                (int) $record['id'],
                (int) TP_USER_ID,
            );
        }

        // Commit transaction
        DB::commit();
    }


    /**
     * Generate new user keys - step 50
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @return void
     */
    private function migratePersonalItemsStep50($taskData, $arguments) {
        // get user private key
        $userInfo = $this->getOwnerInfos(
            $arguments['user_id'],
            $arguments['user_pwd'],
            1,
            $arguments['user_private_key'] ?? ''
        );

        // get TP_USER private key
        $userTP = DB::queryFirstRow(
            'SELECT pw, public_key, private_key
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            TP_USER_ID
        );

        // Start transaction for better performance
        DB::startTransaction();

        // Loop on files
        $rows = DB::query(
            'SELECT f.id AS id, i.perso AS perso
            FROM ' . prefixTable('files') . ' AS f
            INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = f.id_item
            WHERE f.status = "' . TP_ENCRYPTION_NAME . '"
            LIMIT %i, %i',
            $taskData['index'],
            $taskData['nb']
        ); //aes_encryption
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_files') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                (int) $arguments['user_id']
            );

            // do we have any input? (#3481)
            if ($currentUserKey === null || count($currentUserKey) === 0) {
                continue;
            }

            // Decrypt itemkey with user key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $userInfo['private_key']);

            // Now create sharekey for user
            $this->createUserShareKey(
                'sharekeys_suggestions',
                (string) $itemKey,
                (string) $userInfo['public_key'],
                (int) $record['id'],
                (int) $arguments['user_id'],
            );

            // Now create sharekey for TP_USER
            $this->createUserShareKey(
                'sharekeys_fields',
                (string) $itemKey,
                (string) $userTP['public_key'],
                (int) $record['id'],
                (int) TP_USER_ID,
            );
        }

        // Commit transaction
        DB::commit();
    }


    /**
     * Generate new user keys - step final
     * @param array $arguments Arguments for the task
     */
    private function migratePersonalItemsStepFinal($arguments) {
        // update LOG
        logEvents(
            $this->settings,
            'user_mngt',
            'at_user_new_keys',
            TP_USER_ID,
            "",
            (string) $arguments['user_id']
        );

        // Set user as ready for usage
        DB::update(
            prefixTable('users'),
            array(
                'ongoing_process_id' => NULL,
                'updated_at' => time(),
                'personal_items_migrated' => 1,
                'is_ready_for_usage' => 1,
            ),
            'id = %i',
            $arguments['user_id']
        );
    }    
}
