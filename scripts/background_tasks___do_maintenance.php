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
 * @file      background_tasks___do_maintenance.php
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
$logID = doLog('start', 'do_maintenance - '.$_COOKIE['task_to_run'], (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));

// Perform maintenance tasks
if ($_COOKIE['task_to_run'] === "users-personal-folder") createUserPersonalFolder();
elseif ($_COOKIE['task_to_run'] === "clean-orphan-objects") cleanOrphanObjects();
elseif ($_COOKIE['task_to_run'] === "purge-old-files") purgeTemporaryFiles();
elseif ($_COOKIE['task_to_run'] === "reload-cache-table") reloadCacheTable();
elseif ($_COOKIE['task_to_run'] === "rebuild-config-file") rebuildConfigFile();

// log end
doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID);


/**
 * Permits to create the personal folder for each user.
 *
 * @return void
 */
function createUserPersonalFolder(): void
{
    //Libraries call
    require_once __DIR__. '/../sources/main.functions.php';
    require_once __DIR__. '/../includes/libraries/Tree/NestedTree/NestedTree.php';
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    //get through all users
    $rows = DB::query(
        'SELECT id, login, email
        FROM ' . prefixTable('users') . '
        WHERE id NOT IN ('.OTV_USER_ID.', '.TP_USER_ID.', '.SSH_USER_ID.', '.API_USER_ID.')
        ORDER BY login ASC'
    );
    foreach ($rows as $record) {
        //update PF field for user
        DB::update(
            prefixTable('users'),
            array(
                'personal_folder' => '1',
            ),
            'id = %i',
            $record['id']
        );

        //if folder doesn't exist then create it
        $data = DB::queryfirstrow(
            'SELECT id
            FROM ' . prefixTable('nested_tree') . '
            WHERE title = %s AND parent_id = %i',
            $record['id'],
            0
        );
        $counter = DB::count();
        if ($counter === 0) {
            //If not exist then add it
            DB::insert(
                prefixTable('nested_tree'),
                array(
                    'parent_id' => '0',
                    'title' => $record['id'],
                    'personal_folder' => '1',
                    'categories' => '',
                )
            );

            //rebuild fuild tree folder
            $tree->rebuild();
        } else {
            //If exists then update it
            DB::update(
                prefixTable('nested_tree'),
                array(
                    'personal_folder' => '1',
                ),
                'title=%s AND parent_id=%i',
                $record['id'],
                0
            );
            //rebuild fuild tree folder
            $tree->rebuild();

            // Get an array of all folders
            $folders = $tree->getDescendants($data['id'], false, true, true);
            foreach ($folders as $folder) {
                //update PF field for user
                DB::update(
                    prefixTable('nested_tree'),
                    array(
                        'personal_folder' => '1',
                    ),
                    'id = %s',
                    $folder
                );
            }
        }
    }
}

/**
 * Delete all orphan objects from DB
 *
 * @return void
 */
function cleanOrphanObjects(): void
{
    //Libraries call
    require_once __DIR__. '/../sources/main.functions.php';
    include __DIR__. '/../includes/config/tp.config.php';
    require_once __DIR__. '/../includes/libraries/Tree/NestedTree/NestedTree.php';
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    //init
    $foldersIds = array();

    // Get an array of all folders
    $folders = $tree->getDescendants();
    foreach ($folders as $folder) {
        if (!in_array($folder->id, $foldersIds)) {
            array_push($foldersIds, $folder->id);
        }
    }

    $items = DB::query('SELECT id,label FROM ' . prefixTable('items') . ' WHERE id_tree NOT IN %li', $foldersIds);
    foreach ($items as $item) {
        //Delete item
        DB::DELETE(prefixTable('items'), 'id = %i', $item['id']);

        // Delete if template related to item
        DB::delete(
            prefixTable('templates'),
            'item_id = %i',
            $item['id']
        );

        //log
        DB::DELETE(prefixTable('log_items'), 'id_item = %i', $item['id']);
    }

    // delete orphan items
    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('items') . '
        ORDER BY id ASC'
    );
    foreach ($rows as $item) {
        DB::query(
            'SELECT * FROM ' . prefixTable('log_items') . ' WHERE id_item = %i AND action = %s',
            $item['id'],
            'at_creation'
        );
        $counter = DB::count();
        if ($counter === 0) {
            DB::DELETE(prefixTable('items'), 'id = %i', $item['id']);
            DB::DELETE(prefixTable('categories_items'), 'item_id = %i', $item['id']);
            DB::DELETE(prefixTable('log_items'), 'id_item = %i', $item['id']);
        }
    }

    // Update CACHE table
    updateCacheTable('reload', $SETTINGS, null);
}

/**
 * Purge old files in FILES and UPLOAD folders.
 *
 * @return void
 */
function purgeTemporaryFiles(): void
{
    // Load expected files
    require_once __DIR__. '/../sources/main.functions.php';
    include __DIR__. '/../includes/config/tp.config.php';

    //read folder
    if (is_dir($SETTINGS['path_to_files_folder']) === true) {
        $dir = opendir($SETTINGS['path_to_files_folder']);
        if ($dir !== false) {
            //delete file FILES
            while (false !== ($f = readdir($dir))) {
                if ($f !== '.' && $f !== '..' && $f !== '.htaccess') {
                    if (file_exists($dir . $f) && ((time() - filectime($dir . $f)) > 604800)) {
                        fileDelete($dir . '/' . $f, $SETTINGS);
                    }
                }
            }

            //Close dir
            closedir($dir);
        }
    }

    //read folder  UPLOAD
    if (is_dir($SETTINGS['path_to_upload_folder']) === true) {
        $dir = opendir($SETTINGS['path_to_upload_folder']);

        if ($dir !== false) {
            //delete file
            while (false !== ($f = readdir($dir))) {
                if ($f !== '.' && $f !== '..') {
                    if (strpos($f, '_delete.') > 0) {
                        fileDelete($SETTINGS['path_to_upload_folder'] . '/' . $f, $SETTINGS);
                    }
                }
            }
            //Close dir
            closedir($dir);
        }
    }
    
}

/**
 * Relead cache table
 *
 * @return void
 */
function reloadCacheTable(): void
{
    // Load expected files
    require_once __DIR__. '/../sources/main.functions.php';
    include __DIR__. '/../includes/config/tp.config.php';

    updateCacheTable('reload', $SETTINGS, NULL);
}

/**
 * Rebuild configuration file
 *
 * @return void
 */
function rebuildConfigFile(): void
{
    // Load expected files
    require_once __DIR__. '/../sources/main.functions.php';
    include __DIR__. '/../includes/config/tp.config.php';

    handleConfigFile('rebuild', $SETTINGS);
}