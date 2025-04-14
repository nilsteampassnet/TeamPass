<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * Optimized Scheduler for Background Tasks
 */

use GO\Scheduler;
use TeampassClasses\ConfigManager\ConfigManager;

// Load config
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';

// Load library
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../sources/main.functions.php';

// Create a new scheduler
$scheduler = new Scheduler();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Update or insert last cron execution timestamp
$updated = DB::update(
    prefixTable('misc'),
    ['valeur' => time()],
    'type = "admin" AND intitule = "last_cron_exec"'
);

if ($updated === 0) {
    DB::insert(
        prefixTable('misc'), [
            'type' => 'admin',
            'intitule' => 'last_cron_exec',
            'valeur' => time()
    ]);
}

// Configuration des tâches de fond avec des fréquences dynamiques
$backgroundTasks = [
    'items_statistics' => [
        'script' => __DIR__.'/../scripts/background_tasks___do_calculation.php',
        'frequency' => $SETTINGS['items_statistics_job_frequency'] ?? 5
    ],
    'items_handler' => [
        'script' => __DIR__.'/../scripts/background_tasks___handler.php',
        'frequency' => $SETTINGS['items_ops_job_frequency'] ?? 1
    ]
];

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

// Ajouter les tâches de fond
foreach ($backgroundTasks as $taskName => $taskConfig) {
    $scheduler->php($taskConfig['script'])->everyMinute($taskConfig['frequency']);
}

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