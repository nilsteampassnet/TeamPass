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
    WHERE is_in_progress = %i AND process_type IN ("item_copy", "new_item", "update_item", "item_update_create_keys")
    ORDER BY increment_id ASC',
    1
);

// Vérifie s'il y a une tâche à exécuter
if (DB::count() > 0) {
    // Exécute ou continue la tâche
    handleTask2($processToPerform['increment_id']);
}

function handleTask2($task) {
    // Récupérer les sous-tâches de la tâche
    $subTasks = getSubTasks($task['increment_id']);

    foreach ($subTasks as $subTask) {
        // Continue tant que la sous-tâche n'est pas terminée
        while ($subTask['finished_at'] === null) {
            // Paramètres de la sous-tâche
            $taskParams = json_decode($subTask['task'], true);
            
            // Lance la sous-tâche avec les paramètres actuels
            $process = new Process(['php', __DIR__.'background_tasks___items_handler_subtask.php', $subTask['increment_id']]);
            $process->start();
            $process->wait();

            // Mise à jour pour la prochaine itération
            $taskParams['index'] += $taskParams['nb'];
            updateSubTask($subTask['id'], $taskParams);

            // Recharger les informations de la sous-tâche pour vérifier si elle est terminée
            $subTask = reloadSubTask($subTask['id']);
        }
    }

    // Marquer la tâche principale comme terminée
    markTaskAsFinished($task['increment_id']);
}

/**
 * Fonction pour obtenir les sous-tâches d'une tâche
 */
function getSubTasks($taskId) {
    $task_to_perform = DB::query(
        'SELECT *
        FROM ' . prefixTable('processes_tasks') . '
        WHERE process_id = %i AND finished_at IS NULL
        ORDER BY increment_id ASC',
        $taskId
    );
    return $task_to_perform;
}

/**
 * Fonction pour marquer une sous-tâche comme terminée
 */
function markSubTaskAsFinished($subTaskId) {
    // ... Code pour mettre à jour l'état de la sous-tâche dans la base de données ...
}

/**
 * Fonction pour marquer une tâche comme terminée
 */
function markTaskAsFinished($taskId) {
    // ... Code pour mettre à jour l'état de la tâche dans la base de données ...
}