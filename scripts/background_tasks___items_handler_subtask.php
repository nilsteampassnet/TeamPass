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
 * @file      background_tasks___items_handler_subtask.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$request = SymfonyRequest::createFromGlobals();

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

$subtask = DB::queryfirstrow(
    'SELECT *
    FROM ' . prefixTable('background_subtasks') . '
    WHERE process_id = %i AND finished_at IS NULL
    ORDER BY increment_id ASC',
    (int) $request->request->get('subTask')
);

list($taskArguments) = DB::queryFirstField(
    'SELECT arguments
    FROM ' . prefixTable('background_tasks') . '
    WHERE increment_id = %i',
    $subtask['process_id']
);
$args = json_decode($taskArguments, true);

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