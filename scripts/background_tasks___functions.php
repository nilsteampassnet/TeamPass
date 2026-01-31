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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;

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
function doLog(string $status, string $job, int $enable_tasks_log = 0, ?int $id = null, ?int $treated_objects = null): int
{
    clearTasksLog();
    clearTasksHistory();

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

function clearTasksHistory(): void
{
    global $SETTINGS;

    // Run only once per PHP process (doLog can be called multiple times in a run)
    static $alreadyDone = false;
    if ($alreadyDone === true) {
        return;
    }
    $alreadyDone = true;

    $historyDelay = isset($SETTINGS['tasks_history_delay']) === true ? (int) $SETTINGS['tasks_history_delay'] : 0;

    // Safety: this setting is meant to be in seconds (admin UI stores seconds),
    // but if for any reason it is stored in days, convert it.
    if ($historyDelay > 0 && $historyDelay < 86400) {
        $historyDelay = $historyDelay * 86400;
    }

    if ($historyDelay <= 0) {
        return;
    }

    $threshold = time() - $historyDelay;

    // Delete subtasks linked to old finished tasks (avoid orphans)
    DB::query(
        'DELETE s
         FROM ' . prefixTable('background_subtasks') . ' s
         INNER JOIN ' . prefixTable('background_tasks') . ' t ON t.increment_id = s.task_id
         WHERE t.finished_at > 0
           AND t.finished_at < %i',
        $threshold
    );

    // Delete old finished tasks from history
    DB::delete(
        prefixTable('background_tasks'),
        'finished_at > 0 AND finished_at < %i',
        $threshold
    );
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

    // Check if cache exists and has data
    $folders = null;
    if (!empty($cache_tree) && !empty($cache_tree['data']) && $cache_tree['data'] !== '[]' && $cache_tree['data'] !== '[{}]') {
        $folders = json_decode($cache_tree['data'], true);
    }

    // If no cache data exists, build visible folders from user's roles
    if (empty($folders) || !is_array($folders)) {
        $visibleFolderIds = buildUserVisibleFolderIds($user_id, $tree);

        foreach ($visibleFolderIds as $folderId) {
            // Get path
            $path = '';
            $tree_path = $tree->getPath($folderId, false);
            foreach ($tree_path as $fld) {
                $path .= empty($path) === true ? $fld->title : '/'.$fld->title;
            }

            // get folder info
            $folderInfo = DB::queryFirstRow(
                'SELECT title, parent_id, personal_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                $folderId
            );

            if ($folderInfo) {
                $html[] = [
                    "id" => $folderId,
                    "level" => count($tree_path),
                    "title" => $folderInfo['title'],
                    "disabled" => 0,
                    "parent_id" => $folderInfo['parent_id'],
                    "perso" => $folderInfo['personal_folder'],
                    "path" => $path,
                    "is_visible_active" => 1,
                ];
            }
        }
    } else {
        // Use existing cache data
        foreach ($folders as $folder) {
            if (!isset($folder['id'])) continue;

            $idFolder = is_numeric($folder['id']) ? (int) $folder['id'] : (int) explode("li_", $folder['id'])[1];

            // Get path
            $path = '';
            $tree_path = $tree->getPath($idFolder, false);
            foreach ($tree_path as $fld) {
                $path .= empty($path) === true ? $fld->title : '/'.$fld->title;
            }

            // get folder info
            $folderInfo = DB::queryFirstRow(
                'SELECT title, parent_id, personal_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                $idFolder
            );

            if ($folderInfo) {
                $html[] = [
                    "id" => $idFolder,
                    "level" => count($tree_path),
                    "title" => $folderInfo['title'],
                    "disabled" => 0,
                    "parent_id" => $folderInfo['parent_id'],
                    "perso" => $folderInfo['personal_folder'],
                    "path" => $path,
                    "is_visible_active" => 1,
                ];
            }
        }
    }

    // Update or insert cache_tree entry
    if (!empty($cache_tree) && isset($cache_tree['increment_id'])) {
        DB::update(
            prefixTable('cache_tree'),
            array(
                'visible_folders' => json_encode($html),
                'timestamp' => time(),
            ),
            'increment_id = %i',
            (int) $cache_tree['increment_id']
        );
    } else {
        // Create new cache_tree entry for this user
        DB::insert(
            prefixTable('cache_tree'),
            array(
                'user_id' => $user_id,
                'data' => '[]',
                'visible_folders' => json_encode($html),
                'timestamp' => time(),
            )
        );
    }
}

/**
 * Build list of visible folder IDs for a user based on their roles
 * This is used when no cache exists (e.g., for newly created users)
 *
 * @param int $user_id User ID
 * @param NestedTree $tree Tree object
 * @return array Array of visible folder IDs
 */
function buildUserVisibleFolderIds(int $user_id, $tree): array
{
    $visibleFolders = [];

    // Get user's roles
    $userRoles = DB::queryFirstColumn(
        'SELECT role_id FROM ' . prefixTable('users_roles') . ' WHERE user_id = %i',
        $user_id
    );

    if (empty($userRoles)) {
        return $visibleFolders;
    }

    // Get folders accessible via roles
    $roleFolders = DB::query(
        'SELECT DISTINCT folder_id FROM ' . prefixTable('roles_values') . ' WHERE role_id IN %ls AND type IN %ls',
        $userRoles,
        ['W', 'ND', 'NE', 'NDNE', 'R']
    );

    foreach ($roleFolders as $row) {
        $visibleFolders[] = (int) $row['folder_id'];
    }

    // Get folders directly allowed to the user via users_groups
    $userGroups = DB::queryFirstColumn(
        'SELECT group_id FROM ' . prefixTable('users_groups') . ' WHERE user_id = %i',
        $user_id
    );

    foreach ($userGroups as $groupId) {
        $visibleFolders[] = (int) $groupId;
    }

    // Get user's personal folder if it exists
    $personalFolder = DB::queryFirstRow(
        'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE title = %s AND personal_folder = 1',
        (string) $user_id
    );

    if (!empty($personalFolder)) {
        $visibleFolders[] = (int) $personalFolder['id'];

        // Get all descendants of personal folder
        $descendants = $tree->getDescendants($personalFolder['id'], false, false, true);
        foreach ($descendants as $descId) {
            $visibleFolders[] = (int) $descId;
        }
    }

    return array_unique($visibleFolders);
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
