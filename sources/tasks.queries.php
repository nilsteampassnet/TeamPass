<?php

declare(strict_types=1);

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
 * @file      tasks.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use EZimuel\PHPSecureSession;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
// Load functions
require_once 'main.functions.php';
// Case permit to check if SESSION is still valid        
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');


// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('tasks') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Prepare POST variables
$inputData = dataSanitizer(
    [
        'type' => $request->request->filter('type', '', FILTER_SANITIZE_SPECIAL_CHARS),
        //'data' => $request->request->filter('data', '', FILTER_SANITIZE_SPECIAL_CHARS),
        'key' => $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS),
        'task' => $request->request->filter('task', '', FILTER_SANITIZE_SPECIAL_CHARS),
    ],
    [
        'type' => 'trim|escape',
        //'data' => 'trim|escape',
        'key' => 'trim|escape',
        'type_category' => 'trim|escape',
    ]
);

if (null !== $inputData['type']) {
    // Do checks
    if ($inputData['key'] !== $session->get('key')) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('key_is_not_correct'),
            ),
            'encode'
        );
        return false;
    } elseif ($session->get('user-read_only') === 1) {
        echo prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('error_not_allowed_to'),
            ),
            'encode'
        );
        return false;
    }

    // Get PHP binary
    $phpBinaryPath = getPHPBinary();

    switch ($inputData['type']) {
        case 'perform_task':
            echo performTask($inputData['task'], $phpBinaryPath, $SETTINGS['date_format'].' '.$SETTINGS['time_format']);

            break;

        case 'load_last_tasks_execution':
            echo loadLastTasksExec(
                $SETTINGS['date_format'].' '.$SETTINGS['time_format'],
                isset($SETTINGS['enable_refresh_task_last_execution']) === true && (int) $SETTINGS['enable_refresh_task_last_execution'] === 1 ? 1 : 0
            );

            break;  
    }
}

/**
 * Load the last tasks execution
 *
 * @param string $datetimeFormat
 * @param int $showTaskExecution
 * @return string
 */
function loadLastTasksExec(string $datetimeFormat, int $showTaskExecution): string
{
    if ($showTaskExecution === 0) {
        return prepareExchangedData(
            array(
                'error' => false,
                'task' => '',
                'enabled' => false,
            ),
            'encode'
        );
    }

    $lastExec = [];

    // get exec from processes table
    $rows = DB::query(
        'SELECT max(finished_at), process_type
        FROM ' . prefixTable('background_tasks') . '
        GROUP BY process_type'
    );
    foreach ($rows as $row) {
        array_push(
            $lastExec,
            [
                'task' => loadLastTasksExec_getBadge($row['process_type']),
                'datetime' => date($datetimeFormat, (int) $row['max(finished_at)'])
            ]
        );
    }

    // get exec from background_tasks_log table
    $rows = DB::query(
        'SELECT MAX(finished_at) AS max_finished_at, job AS process_type 
        FROM ' . prefixTable('background_tasks_logs') . '
        WHERE finished_at >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
        GROUP BY process_type'
    );
    foreach ($rows as $row) {
        array_push(
            $lastExec,
            [
                'task' => loadLastTasksExec_getBadge($row['process_type']),
                'datetime' => date($datetimeFormat, (int) $row['max_finished_at'])
            ]
        );
    }

    // Get table log size in MB
    $logSize = DB::queryFirstField(
        'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS "Size (MB)" 
        FROM information_schema.tables 
        WHERE table_schema = %s 
        AND table_name = %s',
        DB_NAME,
        prefixTable('background_tasks_logs')
    );


    return prepareExchangedData(
        array(
            'error' => false,
            'task' => json_encode($lastExec),
            'enabled' => true,
            'logSize' => $logSize,
        ),
        'encode'
    );
}

function loadLastTasksExec_getBadge(string $processLabel): string
{
    $existingTasks = [
        'do_maintenance - clean-orphan-objects' => [
            'db' => 'do_maintenance - clean-orphan-objects',
            'task' => 'clean_orphan_objects_task',
        ],
        'do_maintenance - purge-old-files' => [
            'db' => 'do_maintenance - purge-old-files',
            'task' => 'purge_temporary_files_task',
        ],
        'do_maintenance - reload-cache-table' => [
            'db' => 'do_maintenance - reload-cache-table',
            'task' => 'reload_cache_table_task',
        ],
        'do_maintenance - users-personal-folder' => [
            'db' => 'do_maintenance - users-personal-folder',
            'task' => 'users_personal_folder_task',
        ],
        'send_email' => [
            'db' => 'send_email',
            'task' => 'sending_emails_job_frequency',
        ],
        'do_calculation' => [
            'db' => 'do_calculation',
            'task' => 'items_statistics_job_frequency',
        ],
        'item_keys' => [
            'db' => 'item_keys',
            'task' => 'items_ops_job_frequency',
        ],
        'user_task' => [
            'db' => 'user_task',
            'task' => 'user_keys_job_frequency',
        ],
        'sending_email' => [
            'db' => 'sending_email',
            'task' => 'sending_emails_job_frequency',
        ],
    ];

    return isset($existingTasks[$processLabel]) === true ? $existingTasks[$processLabel]['task'] : $processLabel;
}

/**
 * Perform a task
 *
 * @param string $task
 * @param string $phpBinaryPath
 * @param string $datetimeFormat
 * @return string
 */
function performTask(string $task, string $phpBinaryPath, string $datetimeFormat): string
{
    switch ($task) {
        case 'users_personal_folder_task':

            $process = new Process([
                $phpBinaryPath,
                __DIR__.'/../scripts/task_maintenance_users_personal_folder.php',
            ]);

            break;

        case 'clean_orphan_objects_task':

            $process = new Process([
                $phpBinaryPath,
                __DIR__.'/../scripts/task_maintenance_clean_orphan_objects.php',
            ]);

            break;

        case 'purge_temporary_files_task':

            $process = new Process([
                $phpBinaryPath,
                __DIR__.'/../scripts/task_maintenance_purge_old_files.php',
            ]);

            break;

        case 'reload_cache_table_task':

            $process = new Process([
                $phpBinaryPath,
                __DIR__.'/../scripts/task_maintenance_reload_cache_table.php',
            ]);

            break;
        }

    // execute the process
    if (isset($process) === true) {
        try {
            $process->setTimeout(60);
            $process->start();
            $process->wait();
        
            $output = $process->getOutput();
            $error = false;
        } catch (ProcessFailedException $exception) {
            $error = true;
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS Error - ldap - '.$exception->getMessage());
            }
            $output = 'An error occurred.';
        }

        return prepareExchangedData(
            array(
                'error' => $error,
                'output' => $output,
                'datetime' => date($datetimeFormat, time()),
            ),
            'encode'
        );
    }

    return prepareExchangedData(
        array(
            'error' => true,
            'output' => '',
            'datetime' => date($datetimeFormat, time()),
        ),
        'encode'
    );
}