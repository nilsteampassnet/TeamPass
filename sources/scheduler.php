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
 * @file      scheduler.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use GO\Scheduler;
use EZimuel\PHPSecureSession;
use TeampassClasses\ConfigManager\ConfigManager;


// Load config
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';

// Load library
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../sources/main.functions.php';

// Create a new scheduler
$scheduler = new scheduler();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Update row if exists
$updated = DB::update(
    prefixTable('misc'),
    ['valeur' => time()],
    'type = "admin" AND intitule = "last_cron_exec"'
);

// Insert row if not exists
if ($updated === 0) {
    DB::insert(
        prefixTable('misc'), [
            'type' => 'admin',
            'intitule' => 'last_cron_exec',
            'valeur' => time()
    ]);
}

// Build the scheduler jobs
// https://github.com/peppeocchi/php-cron-scheduler
$scheduler->php(__DIR__.'/../scripts/background_tasks___userKeysCreation.php')->everyMinute($SETTINGS['user_keys_job_frequency'] ?? '1');
$scheduler->php(__DIR__.'/../scripts/background_tasks___sending_emails.php')->everyMinute($SETTINGS['sending_emails_job_frequency'] ?? '2');
$scheduler->php(__DIR__.'/../scripts/background_tasks___do_calculation.php')->everyMinute($SETTINGS['items_statistics_job_frequency'] ?? '5');
$scheduler->php(__DIR__.'/../scripts/background_tasks___user_task.php')->everyMinute($SETTINGS['user_keys_job_frequency'] ?? '1');
$scheduler->php(__DIR__.'/../scripts/background_tasks___items_handler.php')->everyMinute($SETTINGS['items_ops_job_frequency'] ?? '1');

if (isset($SETTINGS['users_personal_folder_task']) === true && empty($SETTINGS['users_personal_folder_task']) === false) {
    runTask(
        explode(';', $SETTINGS['users_personal_folder_task']),
        __DIR__.'/../scripts/task_maintenance_users_personal_folder.php',
        $scheduler
    );
}

if (isset($SETTINGS['clean_orphan_objects_task']) === true && empty($SETTINGS['clean_orphan_objects_task']) === false) {
    runTask(
        explode(';', $SETTINGS['clean_orphan_objects_task']),
        __DIR__.'/../scripts/task_maintenance_clean_orphan_objects.php',
        $scheduler
    );
}

if (isset($SETTINGS['purge_temporary_files_task']) === true && empty($SETTINGS['purge_temporary_files_task']) === false) {
    runTask(
        explode(';', $SETTINGS['purge_temporary_files_task']),
        __DIR__.'/../scripts/task_maintenance_purge_old_files.php',
        $scheduler
    );
}

if (isset($SETTINGS['reload_cache_table_task']) === true && empty($SETTINGS['reload_cache_table_task']) === false) {
    runTask(
        explode(';', $SETTINGS['reload_cache_table_task']),
        __DIR__.'/../scripts/task_maintenance_reload_cache_table.php',
        $scheduler
    );
}

// Let the scheduler execute jobs which are due.
$scheduler->run();

/**
 * Permits to prepare the task definition
 *
 * @param array $parameters
 * @param string $fileName
 * @return void
 */
function runTask($parameters, $fileName, $scheduler): void
{
    if (count($parameters) === 2) {
        if ($parameters[0] === 'hourly') {
            $time = explode(':', $parameters[1]);
            $scheduler->php($fileName)->hourly(is_numeric($time[0]) ? $time[0] : 0);
        } elseif ($parameters[0] !== '') {
            $param = (string) $parameters[0];
            $scheduler->php($fileName)->$param($parameters[1]);
        }
    } else {
        $param = (string) $parameters[0];
        $scheduler->php($fileName)->$param('*', $parameters[2], $parameters[1]);
    }
}