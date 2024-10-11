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
 * @file      background_tasks___items_handler.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
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

$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

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
    FROM ' . prefixTable('background_tasks') . '
    WHERE is_in_progress = %i AND process_type IN ("item_copy", "new_item", "update_item", "item_update_create_keys")
    ORDER BY increment_id ASC',
    1
);

if (DB::count() > 0) {
    // handle tasks inside this process
    if (WIP === true) error_log("Process in progress: ".$process_to_perform['increment_id']);
    handleTask(
        $process_to_perform['increment_id'],
        json_decode($process_to_perform['arguments'], true),
        $SETTINGS,
    );
} else {
    // search for next process to handle
    $process_to_perform = DB::queryfirstrow(
        'SELECT *
        FROM ' . prefixTable('background_tasks') . '
        WHERE is_in_progress = %i AND (finished_at = "" OR finished_at IS NULL) AND process_type IN ("item_copy", "new_item", "update_item", "item_update_create_keys")
        ORDER BY increment_id ASC',
        0
    );
    
    if (DB::count() > 0) {
        if (WIP === true) error_log("New process ta start: ".$process_to_perform['increment_id']);
        // update DB - started_at
        DB::update(
            prefixTable('background_tasks'),
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
            $process_to_perform['item_id']
        );
    } else {

    }
}


// Do special tasks
performRecuringItemTasks($SETTINGS);

// log end
doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID);

// launch a new iterative process
$process_to_perform = DB::queryfirstrow(
    'SELECT *
    FROM ' . prefixTable('background_tasks') . '
    WHERE is_in_progress = %i AND process_type IN ("item_copy", "new_item", "update_item", "item_update_create_keys")
    ORDER BY increment_id DESC',
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
function handleTask(int $processId, array $ProcessArguments, array $SETTINGS, int $itemId = null): bool
{
    provideLog('[PROCESS][#'. $processId.'][START]', $SETTINGS);
    $task_to_perform = DB::queryfirstrow(
        'SELECT *
        FROM ' . prefixTable('background_subtasks') . '
        WHERE task_id = %i AND finished_at IS NULL
        ORDER BY increment_id ASC',
        $processId
    );

    // get the process object
    //$processObject = json_decode($ProcessArguments['object_key'], true);
    
    if (DB::count() > 0) {
        // check if a linux process is not currently on going
        // if sub_task_in_progress === 1 then exit
        if ((int) $task_to_perform['sub_task_in_progress'] === 0) {
            // handle next task
            $args = json_decode($task_to_perform['task'], true);
            provideLog('[TASK][#'. $task_to_perform['increment_id'].'][START]Task '.$args['step'], $SETTINGS);

            // flag as in progress
            DB::update(
                prefixTable('background_tasks'),
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
                    prefixTable('background_subtasks'),
                    array(
                        'is_in_progress' => 1,
                    ),
                    'increment_id = %i',
                    $task_to_perform['increment_id']
                );
            }

            // flag sub task in progress as on going
            DB::update(
                prefixTable('background_subtasks'),
                array(
                    'sub_task_in_progress' => 1,
                ),
                'increment_id = %i',
                $task_to_perform['increment_id']
            );

            // handle the task step
            handleTaskStep($args, $ProcessArguments, $SETTINGS);

            // update the task status
            DB::update(
                prefixTable('background_subtasks'),
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

            // are all tasks done?
            DB::query(
                'SELECT *
                FROM ' . prefixTable('background_subtasks') . '
                WHERE task_id = %i AND finished_at IS NULL',
                $processId
            );
            if (DB::count() === 0) {
                // all tasks are done
                provideLog('[PROCESS]['.$processId.'][FINISHED]', $SETTINGS);
                DB::debugmode(false);
                DB::update(
                    prefixTable('background_tasks'),
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

                // if item was being updated then remove the edition lock
                if (is_null($itemId) === false) {
                    DB::delete(prefixTable('items_edition'), 'item_id = %i', $itemId);
                }
            }
            return false;

        } else {
            // Task is currently being in progress by another server process
            provideLog('[TASK][#'. $task_to_perform['increment_id'].'][WARNING] Similar task already being processes', $SETTINGS);
            return false;
        }
    } else {
        // no more task to perform
        provideLog('[PROCESS]['.$processId.'][FINISHED]', $SETTINGS);
        DB::update(
            prefixTable('background_tasks'),
            array(
                'finished_at' => time(),
                'is_in_progress' => -1,
                'arguments' => json_encode([
                    'item_id' => isset($ProcessArguments['item_id']) === true ? $ProcessArguments['item_id'] : '',
                ])
            ),
            'increment_id = %i',
            $processId
        );

        // if item was being updated then remove the edition lock
        if (is_null($itemId) === false) {
            DB::delete(prefixTable('items_edition'), 'item_id = %i', $itemId);
        }
    }
    return false;
}

/**
 * Handle the task step
 *
 * @param array $args
 * @param array $ProcessArguments
 * @param array $SETTINGS
 *
 * @return void
 */
function handleTaskStep(
    array $args,
    array $ProcessArguments,
    array $SETTINGS
)
{
    // perform the task step "create_users_files_key"
    if ($args['step'] === 'create_users_files_key') {
        // Loop on all files for this item
        // and encrypt them for each user
        if (WIP === true) provideLog('[DEBUG] '.print_r($args['files_keys'], true), $SETTINGS);
        foreach($args['files_keys'] as $file) {
            storeUsersShareKey(
                prefixTable('sharekeys_items'),
                0,
                -1,
                (int) $file['object_id'],
                (string) $file['object_key'],
                false,
                false,
                [],
                array_key_exists('all_users_except_id', $ProcessArguments) === true ? $ProcessArguments['all_users_except_id'] : -1,
            );
        }
    } elseif ($args['step'] === 'create_users_fields_key') {
        // Loop on all encrypted fields for this item
        // and encrypt them for each user
        if (WIP === true) provideLog('[DEBUG] '.print_r($args, true), $SETTINGS);
        foreach($args['fields_keys'] as $field) {
            storeUsersShareKey(
                prefixTable('sharekeys_fields'),
                0,
                -1,
                (int) $field['object_id'],
                (string) $field['object_key'],
                false,
                false,
                [],
                array_key_exists('all_users_except_id', $ProcessArguments) === true ? $ProcessArguments['all_users_except_id'] : -1,
            );
        }
    } elseif ($args['step'] === 'create_users_pwd_key') {
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            0,
            -1,
            (int) $ProcessArguments['item_id'],
            (string) (array_key_exists('pwd', $ProcessArguments) === true ? $ProcessArguments['pwd'] : (array_key_exists('object_key', $ProcessArguments) === true ? $ProcessArguments['object_key'] : '')),
            false,
            false,
            [],
            array_key_exists('all_users_except_id', $ProcessArguments) === true ? $ProcessArguments['all_users_except_id'] : -1
        );
    }
}

/**
 * Perform recuring tasks
 * 
 * @param array $SETTINGS
 * 
 * @return void
 */
function performRecuringItemTasks($SETTINGS): void
{
    // Clean multeple items edition
    DB::query(
        'DELETE i1 FROM '.prefixTable('items_edition').' i1
        JOIN (
            SELECT user_id, item_id, MIN(timestamp) AS oldest_timestamp
            FROM '.prefixTable('items_edition').'
            GROUP BY user_id, item_id
        ) i2 ON i1.user_id = i2.user_id AND i1.item_id = i2.item_id
        WHERE i1.timestamp > i2.oldest_timestamp'
    );

    // Handle item tokens expiration
    // Delete entry if token has expired
    // Based upon SETTINGS['delay_item_edition'] or EDITION_LOCK_PERIOD (1 day by default)
    DB::query(
        'DELETE FROM '.prefixTable('items_edition').'
        WHERE timestamp < %i',
        ($SETTINGS['delay_item_edition'] > 0) ? time() - ($SETTINGS['delay_item_edition']*60) : time() - EDITION_LOCK_PERIOD
    );
}