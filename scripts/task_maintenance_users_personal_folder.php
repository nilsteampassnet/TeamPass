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
 * @file      task_maintenance_users_personal_folder.php
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

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
session_name('teampass_session');
session_start();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

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
$logID = doLog('start', 'do_maintenance - users-personal-folder', 1);

// Perform maintenance tasks
createUserPersonalFolder();

// log end
doLog('end', '', 1, $logID);

/**
 * Permits to create the personal folder for each user.
 *
 * @return void
 */
function createUserPersonalFolder(): void
{
    //Libraries call
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    //get through all users
    $users = DB::query(
        'SELECT id, login, email
        FROM ' . prefixTable('users') . '
        WHERE id NOT IN ('.OTV_USER_ID.', '.TP_USER_ID.', '.SSH_USER_ID.', '.API_USER_ID.')
        ORDER BY login ASC'
    );
    foreach ($users as $user) {
        /*
        //update PF field for user
        DB::update(
            prefixTable('users'),
            array(
                'personal_folder' => '1',
            ),
            'id = %i',
            $user['id']
        );
        */

        //if folder doesn't exist then create it
        $data = DB::queryfirstrow(
            'SELECT id
            FROM ' . prefixTable('nested_tree') . '
            WHERE title = %s AND parent_id = %i',
            $user['id'],
            0
        );
        $counter = DB::count();
        if ($counter === 0) {
            //If not exist then add it
            DB::insert(
                prefixTable('nested_tree'),
                array(
                    'parent_id' => '0',
                    'title' => $user['id'],
                    'personal_folder' => '1',
                    'categories' => '',
                )
            );

            //rebuild fuild tree folder
            $tree->rebuild();
        } else {
            //If exists then update it
            DB::update(
                prefixTable('nested_tree'),
                array(
                    'personal_folder' => '1',
                ),
                'title=%s AND parent_id=%i',
                $user['id'],
                0
            );
            //rebuild fuild tree folder
            $tree->rebuild();

            // Get an array of all folders
            $folders = $tree->getDescendants($data['id'], false, true, true);
            foreach ($folders as $folder) {
                //update PF field for user
                DB::update(
                    prefixTable('nested_tree'),
                    array(
                        'personal_folder' => '1',
                    ),
                    'id = %s',
                    $folder
                );
            }
        }

        // Ensure only the user items have a sharekey
        purgeUnnecessaryKeys(false, $user['id']);
    }
}
