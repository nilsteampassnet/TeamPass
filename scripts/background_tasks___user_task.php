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
 * @file      background_tasks___user_task.php
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
$logID = doLog('start', 'user_task', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));

// Never less than 10
$number_users_build_cache_tree = max((int) $SETTINGS['number_users_build_cache_tree'] ?? 0, 10);

DB::debugmode(false);

for ($i = 0; $i < $number_users_build_cache_tree; $i++) {

    // Get one task at a time so we don't duplicate them if the scheduler is
    // launched several times.
    $record = DB::queryFirstRow(
        'SELECT *
        FROM ' . prefixTable('background_tasks') . '
        WHERE is_in_progress = %i AND process_type = %s
        ORDER BY increment_id ASC LIMIT 1',
        0,
        'user_build_cache_tree'
    );

    // No more pending user_build_cache_tree tasks
    if (DB::count() === 0)
        exit;

    // get email properties
    $arguments = json_decode($record['arguments'], true);

    // Update started_at time and is_in_progress state
    DB::update(
        prefixTable('background_tasks'),
        array(
            'started_at' => time(),
            'is_in_progress' => 1,
        ),
        'increment_id = %i',
        $record['increment_id']
    );

    // update visible_folders HTML
    performVisibleFoldersHtmlUpdate($arguments['user_id']);

    // update DB
    DB::update(
        prefixTable('background_tasks'),
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