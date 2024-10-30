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
 * ---3.0.0.22
 * @file      background_tasks___functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\ConfigManager\ConfigManager;

// Load config
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');


/**
 * Permits to log task status
 *
 * @param string $status
 * @param string $job
 * @param integer $enable_tasks_log
 * @param integer|null $id
 * @return integer
 */
function doLog(string $status, string $job, int $enable_tasks_log = 0, int $id = null, int $treated_objects = null): int
{
    clearTasksLog();

    // is log enabled?
    if ((int) $enable_tasks_log === 1) {
        // is log start?
        if (is_null($id) === true) {
            DB::insert(
                prefixTable('background_tasks_logs'),
                array(
                    'created_at' => time(),
                    'job' => $job,
                    'status' => $status,
                )
            );
            return DB::insertId();
        }

        // Case is an update
        DB::update(
            prefixTable('background_tasks_logs'),
            array(
                'status' => $status,
                'finished_at' => time(),
                'treated_objects' => $treated_objects,
            ),
            'increment_id = %i',
            $id
        );
    }
    
    return -1;
}

function clearTasksLog()
{
    global $SETTINGS;
    $retentionDays = isset($SETTINGS['tasks_log_retention_delay']) === true ? $SETTINGS['tasks_log_retention_delay'] : 30;
    $timestamp = strtotime('-'.$retentionDays.' days');
    DB::delete(
        prefixTable('background_tasks_logs'),
        'created_at < %s',
        $timestamp
    );
}

/**
 * Permits to run a task
 *
 * @param array $message
 * @param string $SETTINGS
 * @return void
 */
function provideLog(string $message, array $SETTINGS)
{
    if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
        error_log((string) date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], time()) . ' - '.$message);
    }
}

function performVisibleFoldersHtmlUpdate (int $user_id)
{
    $html = [];

    // rebuild tree
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $tree->rebuild();

    // get current folders visible for user
    $cache_tree = DB::queryFirstRow(
        'SELECT increment_id, data FROM ' . prefixTable('cache_tree') . ' WHERE user_id = %i',
        $user_id
    );
    $folders = json_decode($cache_tree['data'], true);//print_r($folders);
    foreach ($folders as $folder) {
        $idFolder = (int) explode("li_", $folder['id'])[1];

        // Get path
        $path = '';
        $tree_path = $tree->getPath($idFolder, false);
        foreach ($tree_path as $fld) {
            $path .= empty($path) === true ? $fld->title : '/'.$fld->title;
        }

        // get folder info
        $folder = DB::queryFirstRow(
            'SELECT title, parent_id, personal_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
            $idFolder
        );

        // finalize
        array_push(
            $html,
            [
                "id" => $idFolder,
                "level" => count($tree_path),
                "title" => $folder['title'],
                "disabled" => 0,
                "parent_id" => $folder['parent_id'],
                "perso" => $folder['personal_folder'],
                "path" => $path,
                "is_visible_active" => 1,
            ]
        );
    }

    DB::update(
        prefixTable('cache_tree'),
        array(
            'visible_folders' => json_encode($html),
            'timestamp' => time(),
        ),
        'increment_id = %i',
        (int) $cache_tree['increment_id']
    );
}


function subTaskStatus($taskId)
{
    $subTasks = DB::query(
        'SELECT * FROM ' . prefixTable('background_subtasks') . ' WHERE task_id = %i',
        $taskId
    );

    $status = 0;
    foreach ($subTasks as $subTask) {
        if ($subTask['is_in_progress'] === 1) {
            $status = 1;
            break;
        }
    }

    return $status;
}