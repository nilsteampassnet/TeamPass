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

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;


// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
session_name('teampass_session');
session_start();

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
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

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
    updateCacheTable('reload', null);
}