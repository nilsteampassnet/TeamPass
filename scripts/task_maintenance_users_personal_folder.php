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

require_once __DIR__.'/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
$_SESSION['CPM'] = 1;

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
    require_once __DIR__. '/../sources/main.functions.php';
    require_once __DIR__. '/../includes/libraries/Tree/NestedTree/NestedTree.php';
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

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
