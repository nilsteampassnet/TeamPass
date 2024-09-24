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
 * @file      task_maintenance_clean_orphan_objects.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;


// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
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
    /*
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
    }*/

    /*
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
    */

    // Delete all item keys for which no user exist
    DB::query(
        'DELETE k FROM ' . prefixTable('sharekeys_items') . ' k
        LEFT JOIN ' . prefixTable('users') . ' u ON k.user_id = u.id
        WHERE u.id IS NULL OR u.deleted_at IS NOT NULL'
    );

    // Delete all files keys for which no user exist
    DB::query(
        'DELETE k FROM ' . prefixTable('sharekeys_files') . ' k
        LEFT JOIN ' . prefixTable('users') . ' u ON k.user_id = u.id
        WHERE u.id IS NULL OR u.deleted_at IS NOT NULL'
    );

    // Delete all fields keys for which no user exist
    DB::query(
        'DELETE k FROM ' . prefixTable('sharekeys_fields') . ' k
        LEFT JOIN ' . prefixTable('users') . ' u ON k.user_id = u.id
        WHERE u.id IS NULL OR u.deleted_at IS NOT NULL'
    );

    // Delete all item logs for which no user exist
    DB::query(
        'DELETE l FROM ' . prefixTable('log_items') . ' l
        LEFT JOIN ' . prefixTable('users') . ' u ON l.id_user = u.id
        WHERE u.id IS NULL OR u.deleted_at IS NOT NULL'
    );

    // Delete all system logs for which no user exist
    DB::query(
        'DELETE l FROM ' . prefixTable('log_system') . ' l
        LEFT JOIN ' . prefixTable('users') . ' u ON l.qui = u.id
        WHERE i.id IS NULL OR u.deleted_at IS NOT NULL'
    );

    // Delete all item keys for which no object exist
    DB::query(
        'DELETE k FROM ' . prefixTable('sharekeys_items') . ' k
        LEFT JOIN ' . prefixTable('items') . ' i ON k.object_id = i.id
        WHERE i.id IS NULL'
    );

    // Delete all files keys for which no object exist
    DB::query(
        'DELETE k FROM ' . prefixTable('sharekeys_files') . ' k
        LEFT JOIN ' . prefixTable('items') . ' i ON k.object_id = i.id
        WHERE i.id IS NULL'
    );

    // Delete all fields keys for which no object exist
    DB::query(
        'DELETE k FROM ' . prefixTable('sharekeys_fields') . ' k
        LEFT JOIN ' . prefixTable('items') . ' i ON k.object_id = i.id
        WHERE i.id IS NULL'
    );

    // Delete all item logs for which no object exist
    DB::query(
        'DELETE l FROM ' . prefixTable('log_items') . ' l
        LEFT JOIN ' . prefixTable('items') . ' i ON k.id_item = i.id
        WHERE i.id IS NULL'
    );


    // Update CACHE table
    updateCacheTable('reload', null);
}