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

// Vérifie s'il y a une tâche à exécuter
if (DB::count() > 0) {
    // Exécute ou continue la tâche
    subtasksHandler($processToPerform['increment_id'], $processToPerform['arguments']);
}

function subtasksHandler($taskId, $taskArguments)
{
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
/*
    $fichier = fopen(__DIR__.'/log.txt', 'a');
    fwrite($fichier, 'Nouvelle iteration : '.count($subTasks)."\n");
    fclose($fichier);
    */

    foreach ($subTasks as $subTask) {
        // Launch the process for this subtask

        // Do we have already 5 process running?
        if (countActiveSymfonyProcesses() <= 2) {
            // Extract the subtask parameters
            $subTaskParams = json_decode($subTask['task'], true);

            error_log('Subtask in progress: '.$subTask['increment_id']." (".$taskId.") - ".print_r($subTaskParams,true));
/*
            $fichier = fopen(__DIR__.'/log.txt', 'a');
            fwrite($fichier, 'Step : '.$subTaskParams['step']." - index : ".$subTaskParams['index']."\n");
            fclose($fichier);
            */
            
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
            
            // Lance la sous-tâche avec les paramètres actuels
            $process = new Process(['php', __DIR__.'/background_tasks___userKeysCreation_subtaskHdl.php', $subTask['increment_id'], $subTaskParams['index'], $subTaskParams['nb'], $subTaskParams['step'], $taskArguments, $taskId]);
            $process->start();
            $pid = $process->getPid();
            
            // Update the subtask with the process id
            updateSubTask($subTask['increment_id'], ['pid' => $pid]);
            updateTask($taskId);
        }
    }
    sleep(10); // Wait 20 seconds before continuing

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
/*
    $fichier = fopen(__DIR__.'/log.txt', 'a');
    fwrite($fichier, 'Nombre records: SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE task LIKE "%%s%" - '.$action.' - '.DB::count()."\n");
    fclose($fichier);*/

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
    // Compter le nombre de processus actifs
    return DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_subtasks') . 
        ' WHERE process_id IS NOT NULL AND finished_at IS NULL'
    );
}

/**
 * Fonction pour obtenir les sous-tâches d'une tâche
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
 * Mise à jour d'une sous-tâche
 * 
 * @param int $subTaskId Identifiant de la sous-tâche
 * @param array $taskParams Paramètres de la tâche à mettre à jour
 */
function updateSubTask($subTaskId, $args) {
    if (empty($subTaskId) === true) {
        if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
            error_log('Subtask ID is empty ... are we lost!?!');
        }
        return;
    }
    // Convertir les paramètres de la tâche en JSON
    $query = [
        'updated_at' => time(), // Mettre à jour la date de dernière modification
        'is_in_progress' => 1,
        'sub_task_in_progress' => 1,
    ];
    if (isset($args['task']) === true) {
        $query['task'] = $args['task'];
    }
    if (isset($args['pid']) === true) {
        $query['process_id'] = $args['pid'];
    }

    // Mettre à jour la sous-tâche dans la base de données
    DB::update(prefixTable('background_subtasks'), $query, 'increment_id=%i', $subTaskId);
}


/**
 * Recharger les informations d'une sous-tâche depuis la base de données
 * 
 * @param int $subTaskId Identifiant de la sous-tâche
 * @return array Informations mises à jour de la sous-tâche
 */
function reloadSubTask($subTaskId) {
    // Récupérer les informations de la sous-tâche de la base de données
    $subTask = DB::queryFirstRow(
        'SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE increment_id = %i', 
        $subTaskId
    );

    // Retourner les informations de la sous-tâche
    return $subTask;
}

function updateTask($taskId) {
    // Mettre à jour la tâche dans la base de données
    DB::update(prefixTable('background_tasks'), [
        'updated_at' => time(), // Mettre à jour la date de dernière modification
        'is_in_progress' => 1, // Mettre à jour le statut de la tâche
    ], 'increment_id=%i', $taskId);
}


/**
 * Marquer une tâche principale comme terminée
 * 
 * @param int $taskId Identifiant de la tâche
 */
function markTaskAsFinished($taskId, $userId = null) {
    // Mettre à jour la tâche dans la base de données
    DB::update(prefixTable('background_tasks'), [
        'finished_at' => time(),
        'arguments' => '{"new_user_id":'.$userId.'}',
        'is_in_progress' => -1,
    ], 'increment_id=%i', $taskId);
}

function markSubTaskAsFinished($subTaskId) {
    // Mettre à jour la sous-tâche dans la base de données
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

        // Créer un nouveau processus pour exécuter la commande
        $process = new Process($command);

        // Exécuter la commande
        $process->run();

        // Récupérer la sortie du processus
        $output = $process->getOutput();
/*
        $fichier = fopen(__DIR__.'/log.txt', 'a');
        fwrite($fichier, 'Processs existe : '.strpos($output, $subtask['process_id'].' ')." \n");
        fclose($fichier);*/

        // Vérifier si le processus est en cours d'exécution en vérifiant le code de sortie
        if (strpos($output, $subtask['process_id'].' ') === false) {
            markSubTaskAsFinished($subtask['increment_id']);
/*
            $fichier = fopen(__DIR__.'/log.txt', 'a');
            fwrite($fichier, 'Killing process: '.$subtask['increment_id']." \n");
            fclose($fichier);*/
        }
    }
}