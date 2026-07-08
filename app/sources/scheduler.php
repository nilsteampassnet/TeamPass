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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 * 
 * Optimized Scheduler for Background Tasks
 */

use GO\Scheduler;
use TeampassClasses\ConfigManager\ConfigManager;

// Load config
require_once __DIR__.'/../config/include.php';
require_once __DIR__.'/../config/settings.php';

// Load library
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/main.functions.php';

// Create a new scheduler
$scheduler = new Scheduler();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Update last cron execution timestamp (upsert)
DB::query(
    'INSERT INTO %l (type, intitule, valeur, updated_at)
     VALUES (%s, %s, %i, %i)
     ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_at = VALUES(updated_at)',
    prefixTable('misc'),
    'admin',
    'last_cron_exec',
    time(),
    time()
);

// Send HTTP 200 to cron-job.org immediately so the connection closes,
// then continue processing in the background (fastcgi_finish_request).
// This prevents the web server from killing the PHP process mid-task.
http_response_code(200);
header('Content-Type: text/plain');
header('Content-Length: 2');
echo 'OK';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
}
set_time_limit(0);
ignore_user_abort(true);

// Run background task handler directly — no exec()/proc_open() required.
// The handler has its own flock-based concurrency guard.
require_once __DIR__.'/../scripts/background_tasks___handler.php';

// Ajout dynamique des tâches de maintenance
$maintenanceTasks = [
    'users_personal_folder' => [
        'script' => __DIR__.'/../scripts/task_maintenance_users_personal_folder.php',
        'setting' => 'users_personal_folder_task'
    ],
    'clean_orphan_objects' => [
        'script' => __DIR__.'/../scripts/task_maintenance_clean_orphan_objects.php',
        'setting' => 'clean_orphan_objects_task'
    ],
    'purge_temporary_files' => [
        'script' => __DIR__.'/../scripts/task_maintenance_purge_old_files.php',
        'setting' => 'purge_temporary_files_task'
    ],
    'reload_cache_table' => [
        'script' => __DIR__.'/../scripts/task_maintenance_reload_cache_table.php',
        'setting' => 'reload_cache_table_task'
    ]
];

// Ajouter les tâches de maintenance configurées
foreach ($maintenanceTasks as $taskName => $taskConfig) {
    if (isset($SETTINGS[$taskConfig['setting']]) && !empty($SETTINGS[$taskConfig['setting']])) {
        $maintenanceTaskParams = explode(';', $SETTINGS[$taskConfig['setting']]);
        
        if (count($maintenanceTaskParams) === 2) {
            if ($maintenanceTaskParams[0] === 'hourly') {
                $time = explode(':', $maintenanceTaskParams[1]);
                $scheduler->php($taskConfig['script'])->hourly(is_numeric($time[0]) ? $time[0] : 0);
            } elseif (!empty($maintenanceTaskParams[0])) {
                $scheduler->php($taskConfig['script'])->{$maintenanceTaskParams[0]}($maintenanceTaskParams[1]);
            }
        }
    }
}

// Exécuter le scheduler
$scheduler->run();
