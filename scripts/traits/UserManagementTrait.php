<?php

use TeampassClasses\Language\Language;

trait UserManagementTrait {

    /**
     * Handle user build cache tree
     * @param array $arguments Useful arguments for the task
     * @return void
     */
    private function handleUserBuildCacheTree($arguments) {
        // update visible_folders HTML
        performVisibleFoldersHtmlUpdate($arguments['user_id']);
    }


    /**
     * Generate user keys
     * @param array $taskData Données de la tâche
     * @param array $arguments Arguments nécessaires pour la création des clés
     * @return void
     */
    private function generateUserKeys($arguments) {
        // User keys creation

        // Get all subtasks related to this task
        $subtasks = DB::query(
            'SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE task_id = %i AND is_in_progress = 0 ORDER BY `task` ASC',
            $this->taskId
        );
    
        if (empty($subtasks)) {
            error_log("Aucune sous-tâche trouvée pour la tâche {$this->taskId}");
            return;
        }
    
        foreach ($subtasks as $subtask) {
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

    


    private function processGenerateUserKeysSubtask(array $subtask, array $arguments) {
        try {
            $taskData = json_decode($subtask['task'], true);
            
            // Marquer la sous-tâche comme en cours
            DB::update(
                prefixTable('background_subtasks'),
                ['is_in_progress' => 1],
                'increment_id = %i',
                $subtask['increment_id']
            );
            
            // Exécuter la logique de la sous-tâche
            switch ($taskData['step'] ?? '') {
                case 'create_users_pwd_key':
                    $this->generateUserPasswordKeys($taskData, $arguments);
                    break;
                case 'create_users_fields_key':
                    $this->generateUserFieldKeys($taskData, $arguments);
                    break;
                case 'create_users_files_key':
                    $this->generateUserFileKeys($taskData, $arguments);
                    break;
                case 'step0':
                    $this->generateNewUserStep0($taskData, $arguments);
                    break;
                case 'step10':
                    $this->generateNewUserStep10($taskData, $arguments, $subtask['task_id']);
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
                    throw new Exception("Type de sous-tâche inconnu.");
            }
    
            // Marquer la sous-tâche comme terminée
            DB::update(
                prefixTable('background_subtasks'),
                [
                    'is_in_progress' => -1,
                    'finished_at' => time(),
                    'updated_at' => time(),
                    'status' => 'completed',
                ],
                'increment_id = %i',
                $subtask['increment_id']
            );
    
        } catch (Exception $e) {
            // Gérer l'échec
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
    
            error_log("Échec de la sous-tâche {$subtask['increment_id']} : " . $e->getMessage());
        }
    }

    


    private function generateNewUserStep0($taskData, $arguments) {
        // New user creation - step 0

        // CLear old sharekeys
        if ($arguments['user_self_change'] === 0) {
            deleteUserObjetsKeys($arguments['new_user_id'], $this->settings);
        }
    }


    private function generateNewUserStep10($taskData, $arguments, $taskId) {
        // New user creation - step 10
        $this->logger->log('Processing task: STEP 10', 'DEBUG');
        // Create all expected subtasks
        // Prepare the subtask queries
        $queries = [
            'step20' => 'SELECT * FROM ' . prefixTable('items') . 
                        (isset($arguments['only_personal_items']) && $arguments['only_personal_items'] == 1 ? ' WHERE perso = 1' : ''),
    
            'step30' => 'SELECT * FROM ' . prefixTable('log_items') . 
                        ' WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"',
    
            'step40' => 'SELECT * FROM ' . prefixTable('categories_items') . 
                        ' WHERE encryption_type = "teampass_aes"',
    
            'step50' => 'SELECT * FROM ' . prefixTable('suggestion'),
    
            'step60' => 'SELECT * FROM ' . prefixTable('files') . ' AS f
                            INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = f.id_item
                            WHERE f.status = "' . TP_ENCRYPTION_NAME . '"'
        ];
    
        // Perform loop on $queries to create sub-tasks
        foreach ($queries as $step => $query) {
            DB::query($query);
            $this->logger->log('Processing task: '.$query." ; ".$step." ; ".DB::count()."/".$taskData['nb'], 'DEBUG');
            createAllSubTasks($step, DB::count(), $taskData['nb'], $taskId);
        }
    }

    /**
     * Create all subtasks for a given action
     * @param string $action The action to be performed
     * @param int $totalElements Total number of elements to process
     * @param int $elementsPerIteration Number of elements per iteration
     * @param int $taskId The ID of the task
     */
    private function createAllSubTasks($action, $totalElements, $elementsPerIteration, $taskId) {
        $this->logger->log('createAllSubTasks: ' . $action, 'DEBUG');
        DB::query(
            'SELECT *
            FROM ' . prefixTable('background_subtasks') . '
            WHERE task_id = %i AND task LIKE %ss',
            $taskId,
            $action
        );

        if (DB::count() > 1) {
            return;
        }
        $iterations = ceil($totalElements / $elementsPerIteration);

        for ($i = 1; $i < $iterations; $i++) {
            DB::insert(prefixTable('background_subtasks'), [
                'task_id' => $taskId,
                'created_at' => time(),
                'task' => json_encode([
                    "step" => $action,
                    "index" => $i * $elementsPerIteration,
                    "nb" => $elementsPerIteration,
                ]),
            ]);
        }
    }

    /**
     * Generate new user keys
     * @param array $taskData Task data
     * @param array $arguments Arguments for the task
     * @param int $taskId Task ID
     * @return array Next step and index
     */
    private function generateNewUserStep20($taskData, $arguments, $taskId) {
        // New user creation - step 20
        // get user private key
        $ownerInfo = getOwnerInfo($arguments['owner_id'], $arguments['creator_pwd'], $this->settings);
        $userInfo = getOwnerInfo($arguments['new_user_id'], $arguments['new_user_pwd'], $this->settings);

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

        // SHould we change step?
        DB::query(
            'SELECT *
            FROM ' . prefixTable('items')
        );

        // Commit transaction
        DB::commit();

        $next_start = (int) $taskData['index'] + (int) $taskData['nb'];
        return [
            'new_index' => $next_start > DB::count() ? 0 : $next_start,
            'new_action' => $next_start > DB::count() ? 'step30' : 'step20',
            'treated_items' => $next_start > DB::count() ? (DB::count() - (int) $taskData['index']) : $taskData['nb'],
        ];
    }


    private function generateNewUserStep30($taskData, $arguments, $taskId) {
        // New user creation - step 30

    }


    private function generateNewUserStep40($taskData, $arguments, $taskId) {
        // New user creation - step 40

    }


    private function generateNewUserStep50($taskData, $arguments, $taskId) {
        // New user creation - step 50

    }


    private function generateNewUserStep60($arguments) {
        // New user creation - step 60

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

    private function getOwnerInfo(int $owner_id, string $owner_pwd, array $settings): array
    {
        $userInfo = DB::queryFirstRow(
            'SELECT pw, public_key, private_key, login, name
            FROM ' . prefixTable('users') . '
            WHERE id = %i',
            $owner_id
        );
        
        // decrypt owner password
        $pwd = cryption($owner_pwd, '','decrypt', $settings)['string'];
        // decrypt private key and send back
        return [
            'private_key' => decryptPrivateKey($pwd, $userInfo['private_key']),
            'login' => $userInfo['login'],
            'name' => $userInfo['name'],
        ];
    }

}