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
 * @file      background_tasks___do_calculation.php
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

use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SuperGlobal\SuperGlobal;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;


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

// log start
$logID = doLog('start', 'do_calculation', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));

// Loop on tree
$ret = [];
$completTree = $tree->getDescendants(0, false, false, false);
foreach ($completTree as $child) {
    // get count of Items in this folder
    $get = DB::queryfirstrow(
        'SELECT count(*) as num_results
        FROM ' . prefixTable('items') . '
        WHERE inactif = %i AND id_tree = %i',
        0,
        $child->id
    );
    $ret[$child->id]['itemsCount'] = (int) $get['num_results'];
    $ret[$child->id]['id'] = $child->id;

    // get number of subfolders
    $nodeDescendants =$tree->getDescendants($child->id, false, false, true);
    $ret[$child->id]['subfoldersCount'] = count($nodeDescendants);

    // get items number in subfolders
    if (count($nodeDescendants) > 0) {
        $get = DB::queryfirstrow(
            'SELECT count(*) as num_results
            FROM ' . prefixTable('items') . '
            WHERE inactif = %i AND id_tree IN (%l)',
            0,
            implode(',', $nodeDescendants)
        );
        $ret[$child->id]['ItemsSubfoldersCount'] = (int) $get['num_results'];
    } else {
        $ret[$child->id]['ItemsSubfoldersCount'] = 0;
    }
}

// Update DB
foreach ($ret as $folder) {
    DB::update(
        prefixTable('nested_tree'),
        array(
            'nb_items_in_folder' => $folder['itemsCount'],
            'nb_subfolders' => $folder['subfoldersCount'],
            'nb_items_in_subfolders' => $folder['ItemsSubfoldersCount'],
        ),
        'id = %i',
        $folder['id']
    );
}

// log end
doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID);