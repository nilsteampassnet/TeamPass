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

require_once __DIR__.'/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
$_SESSION['CPM'] = 1;

// Load config
require_once __DIR__.'/../includes/config/tp.config.php';

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
DB::insert(
    prefixTable('processes_logs'),
    array(
        'created_at' => time(),
        'job' => 'do_calculation',
        'status' => 'start',
    )
);
$logID = DB::insertId();

require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Tree/NestedTree/NestedTree.php';
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

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
DB::update(
    prefixTable('processes_logs'),
    array(
        'status' => 'end',
        'finished_at' => time(),
    ),
    'increment_id = %i',
    $logID
);