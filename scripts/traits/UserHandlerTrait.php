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
 * @file      UserHandlerTrait.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;

trait UserHandlerTrait {

    /**
     * Handle user build cache tree
     * @param array $arguments Useful arguments for the task
     * @return void
     */
    private function handleUserBuildCacheTree($arguments) {
        performVisibleFoldersHtmlUpdate($arguments['user_id']);
    }


    /**
     * Generate user keys
     * @param array $taskData Données de la tâche
     * @param array $arguments Arguments nécessaires pour la création des clés
     * @return void
     */
    private function generateUserKeys($arguments) {
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
            $this->processGenerateUserKeysSubtask($subtask, $arguments);
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
    private function processGenerateUserKeysSubtask(array $subtask, array $arguments) {
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
                case 'step0':
                    $this->generateNewUserStep0($arguments);
                    break;
                case 'step20':
                    $this->generateNewUserStep20($taskData, $arguments, $subtask['task_id']);
                    break;
                case 'step30':
                    $this->generateNewUserStep30($taskData, $arguments, $subtask['task_id']);
                    break;
                case 'step40':
                    $this->generateNewUserStep40($taskData, $arguments, $subtask['task_id']);
                    break;
                case 'step50':
                    $this->generateNewUserStep50($taskData, $arguments, $subtask['task_id']);
                    break;
                case 'step60':
                    $this->generateNewUserStep60($arguments);
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
     * Generate new user keys - step 0
     * @param array $arguments Arguments for the task
     * @return void
     */
    private function generateNewUserStep0($arguments) {
        // CLear old sharekeys
        if ($arguments['user_self_change'] === 0) {
            deleteUserObjetsKeys($arguments['new_user_id'], $this->settings);
        }
    }


    /**
     * Generate new user keys
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @param int $taskId Task ID
     */
    private function generateNewUserStep20($taskData, $arguments, $taskId) {
        // get user private key
        $ownerInfo = $this->getOwnerInfos($arguments['owner_id'], $arguments['creator_pwd']);
        $userInfo = $this->getOwnerInfos($arguments['new_user_id'], $arguments['new_user_pwd']);
        
        // Start transaction for better performance
        DB::startTransaction();

        // Loop on items
        $rows = DB::query(
            'SELECT id, pw, perso
            FROM ' . prefixTable('items') . '
            WHERE perso =  %i
            ORDER BY id ASC
            LIMIT ' . $taskData['index'] . ', ' . $taskData['nb'],
            ($arguments['only_personal_items'] ?? 0) === 1 ? 1 : 0
        );

        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                (int) $record['perso'] === 0 ? $arguments['owner_id'] : $arguments['new_user_id']
            );

            // do we have any input? (#3481)
            if ($currentUserKey === null || count($currentUserKey) === 0) {
                continue;
            }

            // Decrypt itemkey with admin key
            $itemKey = decryptUserObjectKey(
                $currentUserKey['share_key'],
                (int) $record['perso'] === 0 ? $ownerInfo['private_key'] : $userInfo['private_key']
            );
            
            // Prevent to change key if its key is empty
            if (empty($itemKey) === true) {
                $share_key_for_item = '';
            } else {
                // Encrypt Item key
                $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);
            }
            
            $currentUserKey = DB::queryFirstRow(
                'SELECT increment_id
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $arguments['new_user_id']
            );

            if ($currentUserKey) {
                // NOw update
                DB::update(
                    prefixTable('sharekeys_items'),
                    array(
                        'share_key' => $share_key_for_item,
                    ),
                    'increment_id = %i',
                    $currentUserKey['increment_id']
                );
            } else {
                DB::insert(
                    prefixTable('sharekeys_items'),
                    array(
                        'object_id' => (int) $record['id'],
                        'user_id' => (int) $arguments['new_user_id'],
                        'share_key' => $share_key_for_item,
                    )
                );
            }
        }

        // Commit transaction
        DB::commit();
    }


    /**
     * Generate new user keys - step 30
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @param int $taskId Task ID
     * @return void
     */
    private function generateNewUserStep30($taskData, $arguments, $taskId) {
        // get user private key
        $ownerInfo = $this->getOwnerInfos($arguments['owner_id'], $arguments['creator_pwd']);
        $userInfo = $this->getOwnerInfos($arguments['new_user_id'], $arguments['new_user_pwd']);

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
                $arguments['owner_id']
            );

            // do we have any input? (#3481)
            if ($currentUserKey === null || count($currentUserKey) === 0) {
                continue;
            }

            // Decrypt itemkey with admin key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $ownerInfo['private_key']);

            // Encrypt Item key
            $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

            // Save the key in DB
            if ($arguments['user_self_change'] === false) {
                DB::insert(
                    prefixTable('sharekeys_logs'),
                    array(
                        'object_id' => (int) $record['increment_id'],
                        'user_id' => (int) $arguments['new_user_id'],
                        'share_key' => $share_key_for_item,
                    )
                );
            } else {
                // Get itemIncrement from selected user
                if ((int) $arguments['new_user_id'] !== (int) $arguments['owner_id']) {
                    $currentUserKey = DB::queryFirstRow(
                        'SELECT increment_id
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE object_id = %i AND user_id = %i',
                        $record['id'],
                        $arguments['new_user_id']
                    );
                }

                // NOw update
                DB::update(
                    prefixTable('sharekeys_logs'),
                    array(
                        'share_key' => $share_key_for_item,
                    ),
                    'increment_id = %i',
                    $currentUserKey['increment_id']
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
     * @param int $taskId Task ID
     * @return void
     */
    private function generateNewUserStep40($taskData, $arguments, $taskId) {
        // get user private key
        $ownerInfo = $this->getOwnerInfos($arguments['owner_id'], $arguments['creator_pwd']);
        $userInfo = $this->getOwnerInfos($arguments['new_user_id'], $arguments['new_user_pwd']);

        // Start transaction for better performance
        DB::startTransaction();

        // Loop on fields
        $rows = DB::query(
            'SELECT id
            FROM ' . prefixTable('categories_items') . '
            WHERE encryption_type = "teampass_aes"
            ORDER BY id ASC
            LIMIT ' . $taskData['index'] . ', ' . $taskData['nb']
        );
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_fields') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $arguments['owner_id']
            );

            if (isset($currentUserKey['share_key']) === true) {
                // Decrypt itemkey with admin key
                $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $ownerInfo['private_key']);

                // Encrypt Item key
                $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

                // Save the key in DB
                if ($arguments['user_self_change'] === false) {
                    DB::insert(
                        prefixTable('sharekeys_fields'),
                        array(
                            'object_id' => (int) $record['id'],
                            'user_id' => (int) $arguments['new_user_id'],
                            'share_key' => $share_key_for_item,
                        )
                    );
                } else {
                    // Get itemIncrement from selected user
                    if ((int) $arguments['new_user_id'] !== (int) $arguments['owner_id']) {
                        $currentUserKey = DB::queryFirstRow(
                            'SELECT increment_id
                            FROM ' . prefixTable('sharekeys_items') . '
                            WHERE object_id = %i AND user_id = %i',
                            $record['id'],
                            $arguments['new_user_id']
                        );
                    }

                    // NOw update
                    DB::update(
                        prefixTable('sharekeys_fields'),
                        array(
                            'share_key' => $share_key_for_item,
                        ),
                        'increment_id = %i',
                        $currentUserKey['increment_id']
                    );
                }
            }
        }

        // Commit transaction
        DB::commit();
    }


    /**
     * Generate new user keys - step 50
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @param int $taskId Task ID
     * @return void
     */
    private function generateNewUserStep50($taskData, $arguments, $taskId) {
        // get user private key
        $ownerInfo = $this->getOwnerInfos($arguments['owner_id'], $arguments['creator_pwd']);
        $userInfo = $this->getOwnerInfos($arguments['new_user_id'], $arguments['new_user_pwd']);

        // Start transaction for better performance
        DB::startTransaction();

        // Loop on suggestions
        $rows = DB::query(
            'SELECT id
            FROM ' . prefixTable('suggestion') . '
            ORDER BY id ASC
            LIMIT ' . $taskData['index'] . ', ' . $taskData['nb']
        );
        foreach ($rows as $record) {
            // Get itemKey from current user
            $currentUserKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_suggestions') . '
                WHERE object_id = %i AND user_id = %i',
                $record['id'],
                $arguments['owner_id']
            );

            // do we have any input? (#3481)
            if ($currentUserKey === null || count($currentUserKey) === 0) {
                continue;
            }

            // Decrypt itemkey with admin key
            $itemKey = decryptUserObjectKey($currentUserKey['share_key'], $ownerInfo['private_key']);

            // Encrypt Item key
            $share_key_for_item = encryptUserObjectKey($itemKey, $userInfo['public_key']);

            // Save the key in DB
            if ($arguments['user_self_change'] === false) {
                DB::insert(
                    prefixTable('sharekeys_suggestions'),
                    array(
                        'object_id' => (int) $record['id'],
                        'user_id' => (int) $arguments['new_user_id'],
                        'share_key' => $share_key_for_item,
                    )
                );
            } else {
                // Get itemIncrement from selected user
                if ((int) $arguments['new_user_id'] !== (int) $arguments['owner_id']) {
                    $currentUserKey = DB::queryFirstRow(
                        'SELECT increment_id
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE object_id = %i AND user_id = %i',
                        $record['id'],
                        $arguments['new_user_id']
                    );
                }

                // NOw update
                DB::update(
                    prefixTable('sharekeys_suggestions'),
                    array(
                        'share_key' => $share_key_for_item,
                    ),
                    'increment_id = %i',
                    $currentUserKey['increment_id']
                );
            }
        }

        // Commit transaction
        DB::commit();
    }


    /**
     * Generate new user keys - step 60
     * @param array $arguments Arguments for the task
     */
    private function generateNewUserStep60($arguments) {
        $lang = new Language('english');

        // IF USER IS NOT THE SAME
        if ((int) $arguments['new_user_id'] === (int) $arguments['owner_id']) {
            return [
                'new_index' => 0,
                'new_action' => 'finished',
            ];
        }
        
        // update LOG
        logEvents(
            $this->settings,
            'user_mngt',
            'at_user_new_keys',
            TP_USER_ID,
            "",
            (string) $arguments['new_user_id']
        );

        // if done then send email to new user
        // get user info
        $userInfo = DB::queryFirstRow(
            'SELECT email, login, auth_type, special, lastname, name
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $arguments['new_user_id']
        );

        // SEND EMAIL TO USER depending on context
        // Config 1: new user is a local user
        // Config 2: new user is an LDAP user
        // Config 3: send new password
        // COnfig 4: send new encryption code
        if (isset($arguments['send_email']) === true && (int) $arguments['send_email'] === 1) {
            sendMailToUser(
                filter_var($userInfo['email'], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                // @scrutinizer ignore-type
                empty($arguments['email_body']) === false ? $arguments['email_body'] : $lang->get('email_body_user_config_1'),
                'TEAMPASS - ' . $lang->get('login_credentials'),
                (array) filter_var_array(
                    [
                        '#code#' => cryption($arguments['new_user_code'], '','decrypt', $this->settings)['string'],
                        '#lastname#' => isset($userInfo['name']) === true ? $userInfo['name'] : '',
                        '#login#' => isset($userInfo['login']) === true ? $userInfo['login'] : '',
                    ],
                    FILTER_SANITIZE_FULL_SPECIAL_CHARS
                ),
                false,
                $arguments['new_user_pwd']
            );
        }
            
        // Set user as ready for usage
        DB::update(
            prefixTable('users'),
            array(
                'is_ready_for_usage' => 1,
                'otp_provided' => isset($arguments['otp_provided_new_value']) === true && (int) $arguments['otp_provided_new_value'] === 1 ? $arguments['otp_provided_new_value'] : 0
            ),
            'id = %i',
            $arguments['new_user_id']
        );
    }
    

    /**
     * Get owner info
     * @param int $owner_id Owner ID
     * @param string $owner_pwd Owner password
     * @return array Owner information
     */
    private function getOwnerInfos(int $owner_id, string $owner_pwd) {
        $userInfo = DB::queryFirstRow(
            'SELECT pw, public_key, private_key, login, name
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $owner_id
        );

        // decrypt owner password
        $pwd = cryption($owner_pwd, '','decrypt', $this->settings)['string'];
        // decrypt private key and send back
        return [
            'private_key' => decryptPrivateKey($pwd, $userInfo['private_key']),
            'public_key' => $userInfo['public_key'],
            'login' => $userInfo['login'],
            'name' => $userInfo['name'],
        ];
    }
}