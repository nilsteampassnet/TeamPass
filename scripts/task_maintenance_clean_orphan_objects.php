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
 * @file      task_maintenance_clean_orphan_objects.php
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
$logID = doLog('start', 'do_maintenance - clean-orphan-objects', 1);

// Perform maintenance tasks
cleanOrphanObjects();

// log end
doLog('end', '', 1, $logID);

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
    if (isset($SETTINGS) === true) {
        updateCacheTable('reload', $SETTINGS, null);
    }
}