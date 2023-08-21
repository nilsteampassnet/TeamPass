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

require_once __DIR__.'/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
$_SESSION['CPM'] = 1;

// Load config
require_once __DIR__.'/../includes/config/tp.config.php';
require_once __DIR__.'/background_tasks___functions.php';

// increase the maximum amount of time a script is allowed to run
set_time_limit($SETTINGS['task_maximum_run_time']);

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
DB::$ssl = DB_SSL;
DB::$connect_options = DB_CONNECT_OPTIONS;

// log start
$logID = doLog('start', 'user_task', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));

// Manage emails to send in queue.
// Only manage 10 emails at time
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
    require_once __DIR__. '/../sources/SplClassLoader.php';
    require_once __DIR__. '/../includes/libraries/Tree/NestedTree/NestedTree.php';
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
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