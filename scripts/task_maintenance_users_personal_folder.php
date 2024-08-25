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
 * @file      task_maintenance_users_personal_folder.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
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

    //get through all users with enabled personnal folder.
    $users = DB::query(
        'SELECT id, login, email
        FROM ' . prefixTable('users') . '
        WHERE id NOT IN ('.OTV_USER_ID.', '.TP_USER_ID.', '.SSH_USER_ID.', '.API_USER_ID.')
        AND personal_folder = 1
        ORDER BY login ASC'
    );
    foreach ($users as $user) {
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
