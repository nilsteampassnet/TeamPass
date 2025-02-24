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
 * @file      background_tasks___userKeysCreation.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\Process\Process;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language('english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
// increase the maximum amount of time a script is allowed to run
set_time_limit($SETTINGS['task_maximum_run_time']);

// --------------------------------- //

require_once __DIR__.'/background_tasks___functions.php';

$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Get PHP binary
$phpBinaryPath = getPHPBinary();

$processToPerform = DB::queryfirstrow(
    'SELECT *
    FROM ' . prefixTable('background_tasks') . '
    WHERE (finished_at IS NULL OR finished_at = "") AND process_type = %s
    ORDER BY increment_id ASC',
    'create_user_keys'
);

// Check if there is a task to execute
if (DB::count() > 0) {
    // Execute or continue the task
    subtasksHandler($processToPerform['increment_id'], $processToPerform['arguments']);
}

function subtasksHandler($taskId, $taskArguments)
{
    // Check if subtasks are still running
    // This in order to prevent the script from running multiple times on same objects
    while (DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_subtasks') . ' 
        WHERE is_in_progress = 1'
    ) > 0) {
        sleep(10); // Wait 10 seconds before continuing
    }
    // Are server processes still running?
    serverProcessesHandler();

    // Get list of subtasks to be performed for this task id
    $subTasks = getSubTasks($taskId);
    $taskArgumentsArray = json_decode($taskArguments, true);

    if (count($subTasks) === 0) {
        // No subtasks to perform
        // Task is finished
        markTaskAsFinished($taskId, $taskArgumentsArray['new_user_id']);

        // Clear all subtasks
        DB::delete(prefixTable('background_subtasks'), 'task_id=%i', $taskId);        

        // Exit
        return;
    }

    foreach ($subTasks as $subTask) {
        // Launch the process for this subtask

        // Do we have already 5 process running?
        if (countActiveSymfonyProcesses() <= 2) {
            // Extract the subtask parameters
            $subTaskParams = json_decode($subTask['task'], true);

            if (WIP === true) {
                error_log('Subtask in progress: '.$subTask['increment_id']." (".$taskId.") - "./** @scrutinizer ignore-type */ print_r($subTaskParams,true));
            }
            
            // Build all subtasks if first one
            if ((int) $subTaskParams['index'] === 0) {
                if ($subTaskParams['step'] === 'step20') {
                    // Get total number of items
                    DB::query(
                        'SELECT *
                        FROM ' . prefixTable('items') . '
                        '.(isset($taskArgumentsArray['only_personal_items']) === true && $taskArgumentsArray['only_personal_items'] === 1 ? 'WHERE perso = 1' : '')
                    );
                    createAllSubTasks($subTaskParams['step'], DB::count(), $subTaskParams['nb'], $taskId);

                } elseif ($subTaskParams['step'] === 'step30') {
                    // Get total number of items
                    DB::query(
                        'SELECT *
                        FROM ' . prefixTable('log_items') . '
                        WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"'
                    );
                    createAllSubTasks($subTaskParams['step'], DB::count(), $subTaskParams['nb'], $taskId);

                } elseif ($subTaskParams['step'] === 'step40') {
                    // Get total number of items
                    DB::query(
                        'SELECT *
                        FROM ' . prefixTable('categories_items') . '
                        WHERE encryption_type = "teampass_aes"'
                    );
                    createAllSubTasks($subTaskParams['step'], DB::count(), $subTaskParams['nb'], $taskId);

                } elseif ($subTaskParams['step'] === 'step50') {
                    // Get total number of items
                    DB::query(
                        'SELECT *
                        FROM ' . prefixTable('suggestion')
                    );
                    createAllSubTasks($subTaskParams['step'], DB::count(), $subTaskParams['nb'], $taskId);

                } elseif ($subTaskParams['step'] === 'step60') {
                    // Get total number of items
                    DB::query(
                        'SELECT *
                        FROM ' . prefixTable('files') . ' AS f
                        INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = f.id_item
                        WHERE f.status = "' . TP_ENCRYPTION_NAME . '"'
                    );
                    createAllSubTasks($subTaskParams['step'], DB::count(), $subTaskParams['nb'], $taskId);
                }
            }
            
            // Launch the subtask with the current parameters
            $process = new Process(['php', __DIR__.'/background_tasks___userKeysCreation_subtaskHdl.php', $subTask['increment_id'], $subTaskParams['index'], $subTaskParams['nb'], $subTaskParams['step'], $taskArguments, $taskId]);
            $process->start();
            $pid = $process->getPid();
            
            // Update the subtask with the process id
            updateSubTask($subTask['increment_id'], ['pid' => $pid]);
            updateTask($taskId);
        }
    }
    sleep(10); // Wait 10 seconds before continuing

    // Recursively call this function until all subtasks are finished
    subtasksHandler($taskId, $taskArguments);
}

function createAllSubTasks($action, $totalElements, $elementsPerIteration, $taskId)
{
    // Check if subtasks have to be created
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

function countActiveSymfonyProcesses() {
    // Count the number of active processes
    return DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_subtasks') . 
        ' WHERE process_id IS NOT NULL AND finished_at IS NULL'
    );
}

/**
 * Function to get the subtasks of a task
 */
function getSubTasks($taskId) {
    $task_to_perform = DB::query(
        'SELECT *
        FROM ' . prefixTable('background_subtasks') . '
        WHERE task_id = %i AND finished_at IS NULL
        ORDER BY increment_id ASC',
        $taskId
    );
    return $task_to_perform;
}


/**
 * Update a subtask
 * 
 * @param int $subTaskId Subtask identifier
 * @param array $taskParams Task parameters to update
 */
function updateSubTask($subTaskId, $args) {
    if (empty($subTaskId) === true) {
        if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
            error_log('Subtask ID is empty ... are we lost!?!');
        }
        return;
    }
    // Convert task parameters to JSON
    $query = [
        'updated_at' => time(), // Update the last modification date
        'is_in_progress' => 1,
        'sub_task_in_progress' => 1,
    ];
    if (isset($args['task']) === true) {
        $query['task'] = $args['task'];
    }
    if (isset($args['pid']) === true) {
        $query['process_id'] = $args['pid'];
    }

    // Update the subtask in the database
    DB::update(prefixTable('background_subtasks'), $query, 'increment_id=%i', $subTaskId);
}


/**
 * Reload subtask information from the database
 * 
 * @param int $subTaskId Subtask identifier
 * @return array Updated subtask information
 */
function reloadSubTask($subTaskId) {
    // Retrieve subtask information from the database
    $subTask = DB::queryFirstRow(
        'SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE increment_id = %i', 
        $subTaskId
    );

    // Return subtask information
    return $subTask;
}

function updateTask($taskId) {
    // Update the task in the database
    DB::update(prefixTable('background_tasks'), [
        'updated_at' => time(), // Update the last modification date
        'is_in_progress' => 1, // Update the task status
    ], 'increment_id=%i', $taskId);
}


/**
 * Mark a main task as finished
 * 
 * @param int $taskId Task identifier
 */
function markTaskAsFinished($taskId, $userId = null) {
    // Update the task in the database
    DB::update(prefixTable('background_tasks'), [
        'finished_at' => time(),
        'arguments' => '{"new_user_id":'.$userId.'}',
        'is_in_progress' => -1,
    ], 'increment_id=%i', $taskId);
}

function markSubTaskAsFinished($subTaskId) {
    // Update the subtask in the database
    DB::update(prefixTable('background_subtasks'), [
        'finished_at' => time(),
        'is_in_progress' => -1,
        'sub_task_in_progress' => 0,
    ], 'increment_id=%i', $subTaskId);
}

function serverProcessesHandler()
{
    // Get all processes
    $subtasks = DB::query(
        'SELECT *
        FROM ' . prefixTable('background_subtasks') . '
        WHERE process_id IS NOT NULL AND finished_at IS NULL'
    );

    // Check if processes are still running
    foreach ($subtasks as $subtask) {
        $command = ['ps', '-p', $subtask['process_id']];

        // Create a new process to execute the command
        $process = new Process($command);

        // Execute the command
        $process->run();

        // Retrieve the process output
        $output = $process->getOutput();

        // Check if the process is still running by checking the exit code
        if (strpos($output, $subtask['process_id'].' ') === false) {
            markSubTaskAsFinished($subtask['increment_id']);
        }
    }
}