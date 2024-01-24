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
 * @file      background_tasks___do_calculation.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;

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