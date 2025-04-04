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
file_put_contents('/tmp/worker_called.txt', date('Y-m-d H:i:s') . "\n", FILE_APPEND);
require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__.'/background_tasks___functions.php';
require_once __DIR__.'/traits/ItemManagementTrait.php';
require_once __DIR__.'/traits/UserManagementTrait.php';
require_once __DIR__.'/traits/EmailTrait.php';
require_once __DIR__.'/traits/SubTasksTrait.php';
require_once __DIR__ . '/TaskLogger.php';

class TaskWorker {
    use ItemManagementTrait;
    use UserManagementTrait;
    use EmailTrait;
    use SubTasksTrait;

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
        $this->logger = new TaskLogger($this->settings);//, '/tmp/teampass_background_tasks.log'
        
        $configManager = new ConfigManager();
        $this->settings = $configManager->getAllSettings();
    }

    public function execute() {
        try {
            $this->logger->log('Processing task: ' . print_r($this->taskData, true), 'DEBUG');
            // Dispatch selon le type de processus
            switch ($this->processType) {
                case 'item_copy':
                    $this->handleItemCopy($this->taskData);
                    break;
                case 'new_item':
                    $this->handleNewItem($this->taskData);
                    break;
                case 'update_item':
                    $this->handleUpdateItem($this->taskData);
                    break;
                case 'item_update_create_keys':
                    $this->handleItemUpdateCreateKeys($this->taskData);
                    break;
                case 'user_build_cache_tree':
                    // Logique pour construire l'arbre de cache utilisateur
                    $this->handleUserBuildCacheTree($this->taskData);
                    break;
                case 'send_email':
                    // Logic for sending email
                    $this->sendEmail($this->taskData);
                    break;
                case 'user_keys_creation':
                    // Logic for user keys creation
                    $this->generateUserKeys($this->taskData);
                    break;
                case 'create_user_keys':
                    // Logic for new user keys creation
                    $this->generateUserKeys($this->taskData);
                    break;
                default:
                    throw new Exception("Type de processus inconnu : {$this->processType}");
            }

            // Marquer la tâche comme terminée
            $this->completeTask();

        } catch (Exception $e) {
            $this->handleTaskFailure($e);
        }
    }

    private function generateUserPasswordKeys($taskData, $arguments) {  
        // Logique pour générer les clés utilisateur        
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            0,
            (int) $arguments['item_id'],
            (string) (array_key_exists('pwd', $arguments) === true ? $arguments['pwd'] : (array_key_exists('object_key', $arguments) === true ? $arguments['object_key'] : '')),
            false,
            false,
            [],
            array_key_exists('all_users_except_id', $arguments) === true ? $arguments['all_users_except_id'] : -1
        );
    }

    private function generateUserFileKeys($taskData, $arguments) {    
        foreach($arguments['files_keys'] as $file) {
            storeUsersShareKey(
                prefixTable('sharekeys_files'),
                0,
                (int) $file['object_id'],
                (string) $file['object_key'],
                false,
                false,
                [],
                array_key_exists('all_users_except_id', $arguments) === true ? $arguments['all_users_except_id'] : -1,
            );
        }
    }

    private function generateUserFieldKeys($taskData, $arguments) {
        foreach($arguments['fields_keys'] as $field) {
            storeUsersShareKey(
                prefixTable('sharekeys_fields'),
                0,
                (int) $field['object_id'],
                (string) $field['object_key'],
                false,
                false,
                [],
                array_key_exists('all_users_except_id', $arguments) === true ? $arguments['all_users_except_id'] : -1,
            );
        }
    }
    

    private function completeTask() {
        DB::update(
            prefixTable('background_tasks'),
            [
                'is_in_progress' => -1,
                'finished_at' => time(),
                'updated_at' => time(),
                'status' => 'completed'
            ],
            'increment_id = %i',
            $this->taskId
        );
    }

    private function handleTaskFailure(Exception $e) {
        DB::update(
            prefixTable('background_tasks'),
            [
                'is_in_progress' => -1,
                'finished_at' => time(),
                'updated_at' => time(),
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ],
            'increment_id = %i',
            $this->taskId
        );

        error_log("Échec de la tâche {$this->taskId} : " . $e->getMessage());
    }
}

$logger = new TaskLogger(['enable_tasks_log'=> 1, 'time_format'=>'H:i:s', 'date_format'=>'d/m/Y']);//, '/tmp/teampass_background_tasks.log'

$taskId = (int)$argv[1];
$processType = $argv[2];
$taskData = $argv[3] ?? null;
if ($taskData) {
    $taskData = json_decode($taskData, true);
} else {
    $taskData = [];
}

$worker = new TaskWorker($taskId, $processType, $taskData);
$worker->execute();