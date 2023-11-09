<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version   
 * @file      background_tasks___user_task.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

Use voku\helper\AntiXSS;
Use TeampassClasses\NestedTree\NestedTree;
Use TeampassClasses\SuperGlobal\SuperGlobal;
Use EZimuel\PHPSecureSession;
Use TeampassClasses\PerformChecks\PerformChecks;


// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
session_name('teampass_session');
session_start();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    exit();
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

// log start
$logID = doLog('start', 'user_task', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));


DB::debugmode(false);
$rows = DB::query(
    'SELECT *
    FROM ' . prefixTable('processes') . '
    WHERE is_in_progress = %i AND process_type = %s
    ORDER BY increment_id ASC LIMIT 0,10',
    0,
    'user_build_cache_tree'
);
foreach ($rows as $record) {
    // get email properties
    $arguments = json_decode($record['arguments'], true);

    // update DB - started_at
    DB::update(
        prefixTable('processes'),
        array(
            'started_at' => time(),
        ),
        'increment_id = %i',
        $record['increment_id']
    );

    // update visible_folders HTML
    performVisibleFoldersHtmlUpdate($arguments['user_id'], $SETTINGS);

    // update DB
    DB::update(
        prefixTable('processes'),
        array(
            'updated_at' => time(),
            'finished_at' => time(),
            'is_in_progress' => -1,
        ),
        'increment_id = %i',
        $record['increment_id']
    );
}

// log end
doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID);


function performVisibleFoldersHtmlUpdate(
    int $user_id,
    array $SETTINGS
)
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
                "path" => $path,
                "id" => $idFolder,
                "level" => count($tree_path),
                "title" => $folder['title'],
                "disabled" => 0,
                "parent_id" => $folder['parent_id'],
                "perso" => $folder['personal_folder'],
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