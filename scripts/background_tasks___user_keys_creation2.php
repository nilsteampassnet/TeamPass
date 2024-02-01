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
 * @file      background_tasks___items_handler.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

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
    WHERE finished_at IS NULL AND is_in_progress = %i AND process_type = %s
    ORDER BY increment_id ASC',
    0,
    'create_user_keys'
);
//error_log('DEBUT: '.print_r($processToPerform, true));
// Vérifie s'il y a une tâche à exécuter
if (DB::count() > 0) {
    // Exécute ou continue la tâche
    handleTask2($processToPerform['increment_id']);
}

function handleTask2($taskId) {
    // Récupérer les sous-tâches de la tâche
    $subTasks = getSubTasks($taskId);
    
    foreach ($subTasks as $subTask) {
        error_log('DEBUT: '.print_r($subTask, true));
        // Continue tant que la sous-tâche n'est pas terminée
        while ($subTask['finished_at'] === null) {
            // Vérifie si le nombre de processus en cours est inférieur à 5
            if (countActiveSymfonyProcesses() < 5) {
                // Paramètres de la sous-tâche
                $subTaskParams = json_decode($subTask['task'], true);

                error_log('Subtask in progress: '.$subTask['increment_id']." - ".print_r($subTaskParams,true));
                
                // Lance la sous-tâche avec les paramètres actuels
                $process = new Process(['php', __DIR__.'background_tasks___items_handler_subtask.php1', $subTask['increment_id']]);
                $process->start();
                error_log('PID: '.$process->getPid());
                addNewSymfonyProcess($process->getPid(), $subTask['increment_id']);
                
                $process->wait();

                // Mise à jour pour la prochaine itération
                $subTaskParams['index'] += $subTaskParams['nb'];
                updateSubTask($subTask['id'], $subTaskParams);
                updateTask($taskId);

                // Recharger les informations de la sous-tâche pour vérifier si elle est terminée
                $subTask = reloadSubTask($subTask['increment_id']);
            } else {
                // Attendre avant de réessayer
                error_log('Attendre 10 secondes de plus');
                sleep(10); // Attendre 10 secondes avant de réessayer
            }
        }

        // Après la fin de la boucle, marquer la sous-tâche comme terminée
        markSubTaskAsFinished($subTask['increment_id']);
    }

    // Marquer la tâche principale comme terminée
    markTaskAsFinished($taskId);
}

function addNewSymfonyProcess($pid, $subtaskId)
{
    // Mettre à jour le processus dans la base de données
    DB::insert(prefixTable('background_symfony_process'), [
        'start_timestamp' => time(), // Mettre à jour la date de début
        'subtask_id' => $subtaskId,
        'pid' => $pid, // Mettre à jour l'identifiant du processus
    ]);

    return DB::insertId();
}

function endSymfonyProcess($processIncrementId)
{
    // Mettre à jour le processus dans la base de données
    DB::update(prefixTable('background_symfony_process'), [
        'end_timestamp' => time() // Mettre à jour la date de fin
    ], 'increment_id=%i', $processIncrementId);
}

function countActiveSymfonyProcesses() {
    // Compter le nombre de processus actifs
    return DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('background_symfony_process') . 
        ' WHERE end_timestamp IS NULL'
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
function updateSubTask($subTaskId, $taskParams) {
    // Convertir les paramètres de la tâche en JSON
    $taskParamsJson = json_encode($taskParams);

    error_log('Task update : '.$subTaskId." - ".print_r($taskParams,true));

    // Mettre à jour la sous-tâche dans la base de données
    DB::update(prefixTable('background_subtasks'), [
        'task' => $taskParamsJson,
        'updated_at' => date('Y-m-d H:i:s') // Mettre à jour la date de dernière modification
    ], 'increment_id=%i', $subTaskId);
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
 * Marquer une sous-tâche comme terminée
 * 
 * @param int $subTaskId Identifiant de la sous-tâche
 */
function markSubTaskAsFinished($subTaskId) {
    // Mettre à jour la sous-tâche dans la base de données
    
    error_log('Task finised : '.$subTaskId);

    DB::update(prefixTable('background_subtasks'), [
        'finished_at' => date('Y-m-d H:i:s') // Mettre à jour la date de fin
    ], 'increment_id=%i', $subTaskId);

    endSymfonyProcess($subTaskId);
}


/**
 * Marquer une tâche principale comme terminée
 * 
 * @param int $taskId Identifiant de la tâche
 */
function markTaskAsFinished($taskId) {
    // Mettre à jour la tâche dans la base de données
    DB::update(prefixTable('background_tasks'), [
        'finished_at' => date('Y-m-d H:i:s') // Mettre à jour la date de fin
    ], 'increment_id=%i', $taskId);
}
