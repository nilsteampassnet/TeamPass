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
 * @file      background_tasks___items_handler.php
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

// Load config
require_once __DIR__.'/../includes/config/tp.config.php';
require_once __DIR__.'/background_tasks___functions.php';

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

// Get PHP binary
$phpBinaryPath = getPHPBinary();

// log start
$logID = doLog('start', 'item_keys', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));


// Manage the tasks in queue.
// Task to treat selection is:
// 1- take first is_in_progress === 1
// 2- take first is_in_progress === 0 and finished_at === null
DB::debugmode(false);
$process_to_perform = DB::queryfirstrow(
    'SELECT *
    FROM ' . prefixTable('processes') . '
    WHERE is_in_progress = %i AND process_type IN ("item_copy", "new_item", "update_item")
    ORDER BY increment_id ASC',
    1
);

if (DB::count() > 0) {
    // handle tasks inside this process
    handleTask(
        $process_to_perform['increment_id'],
        json_decode($process_to_perform['arguments'], true),
        $SETTINGS,
    );
} else {
    // search for next process to handle
    $process_to_perform = DB::queryfirstrow(
        'SELECT *
        FROM ' . prefixTable('processes') . '
        WHERE is_in_progress = %i AND finished_at = "" AND process_type IN ("item_copy", "new_item", "update_item")
        ORDER BY increment_id ASC',
        0
    );
    //print_r($process_to_perform);
    if (DB::count() > 0) {
        // update DB - started_at
        DB::update(
            prefixTable('processes'),
            array(
                'started_at' => time(),
            ),
            'increment_id = %i',
            $process_to_perform['increment_id']
        );

        provideLog('[PROCESS][#'. $process_to_perform['increment_id'].'][START]', $SETTINGS);
        handleTask(
            $process_to_perform['increment_id'],
            json_decode($process_to_perform['arguments'], true),
            $SETTINGS,
        );
    } else {

    }
}

// log end
doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID);

// launch a new iterative process
$process_to_perform = DB::queryfirstrow(
    'SELECT *
    FROM ' . prefixTable('processes') . '
    WHERE is_in_progress = %i AND process_type IN ("item_copy", "new_item", "update_item")
    ORDER BY increment_id ASC',
    1
);
if (DB::count() > 0) {
    $process = new Symfony\Component\Process\Process([$phpBinaryPath, __FILE__]);
    $process->start();
    $process->wait();
}

/**
 * Handle the task
 *
 * @param int   $processId
 * @param array $ProcessArguments
 * @param array $SETTINGS
 *
 * @return bool
 */
function handleTask(int $processId, array $ProcessArguments, array $SETTINGS): bool
{
    provideLog('[PROCESS][#'. $processId.'][START]', $SETTINGS);
    //DB::debugmode(false);
    $task_to_perform = DB::queryfirstrow(
        'SELECT *
        FROM ' . prefixTable('processes_tasks') . '
        WHERE process_id = %i AND finished_at IS NULL
        ORDER BY increment_id ASC',
        $processId
    );

    // get the process object
    $processObject = json_decode($ProcessArguments['object_key'], true);

    /*
    print_r($processObject);
    //echo " ;; ".cryption($ProcessArguments['new_user_pwd'], '','decrypt', $SETTINGS)['string']." ;; ".cryption($ProcessArguments['new_user_code'], '','decrypt', $SETTINGS)['string']." ;; ";
    return false;
    */

    
    if (DB::count() > 0) {
        // check if a linux process is not currently on going
        // if sub_task_in_progress === 1 then exit
        if ((int) $task_to_perform['sub_task_in_progress'] === 0) {
            provideLog('[TASK][#'. $task_to_perform['increment_id'].'][START]', $SETTINGS);

            // handle next task
            $args = json_decode($task_to_perform['task'], true);
            //print_r($args);return false;

            // flag as in progress
            DB::update(
                prefixTable('processes'),
                array(
                    'updated_at' => time(),
                    'is_in_progress' => 1,
                ),
                'increment_id = %i',
                $processId
            );

            // flag task as on going
            if ((int) $args['index'] === 0) {
                DB::update(
                    prefixTable('processes_tasks'),
                    array(
                        'is_in_progress' => 1,
                    ),
                    'increment_id = %i',
                    $task_to_perform['increment_id']
                );
            }

            // flag sub task in progress as on going
            DB::update(
                prefixTable('processes_tasks'),
                array(
                    'sub_task_in_progress' => 1,
                ),
                'increment_id = %i',
                $task_to_perform['increment_id']
            );

            // perform the task step "create_users_files_key"
            if ($args['step'] === 'create_users_files_key') {
                storeUsersShareKey(
                    prefixTable('sharekeys_files'),
                    0,
                    -1,
                    (int) $ProcessArguments['item_id'],
                    '',
                    $SETTINGS,
                    false,
                    false,
                    $processObject['files']
                );
            } elseif ($args['step'] === 'create_users_fields_key') {
                storeUsersShareKey(
                    prefixTable('sharekeys_fields'),
                    0,
                    -1,
                    (int) $ProcessArguments['item_id'],
                    '',
                    $SETTINGS,
                    false,
                    false,
                    $processObject['fields']
                );
            } elseif ($args['step'] === 'create_users_pwd_key') {
                storeUsersShareKey(
                    prefixTable('sharekeys_items'),
                    0,
                    -1,
                    (int) $ProcessArguments['item_id'],
                    (string) $processObject['pwd'],
                    $SETTINGS,
                    false,
                    false
                );
            }

            // update the task status
            DB::update(
                prefixTable('processes_tasks'),
                array(
                    'sub_task_in_progress' => 0,    // flag sub task is no more in prgoress
                    'task' => json_encode(["status" => "Done"]),
                    'is_in_progress' => -1,
                    'finished_at' => time(),
                    'updated_at' => time(),
                ),
                'increment_id = %i',
                $task_to_perform['increment_id']
            );

            provideLog('[TASK]['.$args['step'].'] starting at '.$args['index'].' is done.', $SETTINGS);

            // If step concerns files then all steps are now finished
            // so we can flag the process as finished
            if ($args['step'] === 'create_users_files_key') {
                provideLog('[PROCESS]['.$processId.'][FINISHED]', $SETTINGS);
                DB::debugmode(false);
                DB::update(
                    prefixTable('processes'),
                    array(
                        'finished_at' => time(),
                        'is_in_progress' => -1,
                        'arguments' => json_encode([
                            'new_user_id' => isset($ProcessArguments['new_user_id']) === true ? $ProcessArguments['new_user_id'] : '',
                        ])
                    ),
                    'increment_id = %i',
                    $processId
                );
            }
            return false;

        } else {
            // Task is currently being in progress by another server process
            provideLog('[TASK][#'. $task_to_perform['increment_id'].'][WARNING] Similar task already being processes', $SETTINGS);
            return false;
        }
    }
}