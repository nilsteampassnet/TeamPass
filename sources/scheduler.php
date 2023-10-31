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
 * @file      scheduler.php
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

use GO\Scheduler;

require_once __DIR__.'/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();

// Load config
require_once __DIR__.'/../includes/config/tp.config.php';
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';

// Load library
//require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../sources/main.functions.php';

// Create a new scheduler
$scheduler = new scheduler();

// Build the scheduler jobs
// https://github.com/peppeocchi/php-cron-scheduler
$scheduler->php(__DIR__.'/../scripts/background_tasks___user_keys_creation.php')->everyMinute($SETTINGS['user_keys_job_frequency'] ?? '1');
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

if (isset($SETTINGS['rebuild_config_file_task']) === true && empty($SETTINGS['rebuild_config_file_task']) === false) {
    runTask(
        explode(';', $SETTINGS['rebuild_config_file_task']),
        __DIR__.'/../scripts/task_maintenance_rebuild_config_file.php',
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
            $scheduler->php($fileName)->hourly($time[1]);
        } elseif ($parameters[0] !== '') {
            $param = (string) $parameters[0];
            $scheduler->php($fileName)->$param($parameters[1]);
        }
    } else {
        $param = (string) $parameters[0];
        $scheduler->php($fileName)->$param('*', $parameters[2], $parameters[1]);
    }
}