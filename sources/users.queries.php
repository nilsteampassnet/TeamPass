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
 * @file      users.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\PasswordManager\PasswordManager;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('profile') === false) {
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

// Prepare post variables
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$isprofileupdate = filter_input(INPUT_POST, 'isprofileupdate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password_do_not_change = 'do_not_change';


//Load Tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

if (null !== $post_type) {
    switch ($post_type) {
            /*
         * ADD NEW USER
         */
        case 'add_new_user':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $login = filter_var($dataReceived['login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $email = filter_var($dataReceived['email'], FILTER_SANITIZE_EMAIL);
            $password = '';//filter_var($dataReceived['pw'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $lastname = filter_var($dataReceived['lastname'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $name = filter_var($dataReceived['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $is_admin = filter_var($dataReceived['admin'], FILTER_SANITIZE_NUMBER_INT);
            $is_manager = filter_var($dataReceived['manager'], FILTER_SANITIZE_NUMBER_INT);
            $is_hr = filter_var($dataReceived['hr'], FILTER_SANITIZE_NUMBER_INT);
            $is_read_only = filter_var($dataReceived['read_only'], FILTER_SANITIZE_NUMBER_INT) || 0;
            $has_personal_folder = filter_var($dataReceived['personal_folder'], FILTER_SANITIZE_NUMBER_INT);
            $new_folder_role_domain = filter_var($dataReceived['new_folder_role_domain'], FILTER_SANITIZE_NUMBER_INT);
            $domain = filter_var($dataReceived['domain'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $is_administrated_by = filter_var($dataReceived['isAdministratedByRole'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $groups = filter_var_array($dataReceived['groups'], FILTER_SANITIZE_NUMBER_INT);
            $allowed_flds = filter_var_array($dataReceived['allowed_flds'], FILTER_SANITIZE_NUMBER_INT);
            $forbidden_flds = filter_var_array($dataReceived['forbidden_flds'], FILTER_SANITIZE_NUMBER_INT);
            $post_root_level = filter_var($dataReceived['form-create-root-folder'], FILTER_SANITIZE_NUMBER_INT);
            $mfa_enabled = filter_var($dataReceived['mfa_enabled'], FILTER_SANITIZE_NUMBER_INT);

            // Empty user
            if (empty($login) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_empty_data'),
                    ),
                    'encode'
                );
                break;
            }
            // Check if user already exists
            $data = DB::query(
                'SELECT id, fonction_id, groupes_interdits, groupes_visibles
                FROM ' . prefixTable('users') . '
                WHERE login = %s
                AND deleted_at IS NULL',
                $login
            );

            if (DB::count() === 0) {
                // check if admin role is set. If yes then check if originator is allowed
                if ($dataReceived['admin'] === true && (int) $session->get('user-admin') !== 1) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Generate pwd
                $password = generateQuickPassword();

                // GEnerate new keys
                $userKeys = generateUserKeys($password);

                // load password library
                $passwordManager = new PasswordManager();

                // Prepare variables
                $hashedPassword = $passwordManager->hashPassword($password);
                if ($passwordManager->verifyPassword($hashedPassword, $password) === false) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('pw_hash_not_correct'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Add user in DB
                DB::insert(
                    prefixTable('users'),
                    array(
                        'login' => $login,
                        'name' => $name,
                        'lastname' => $lastname,
                        'pw' => $hashedPassword,
                        'email' => $email,
                        'admin' => empty($is_admin) === true ? 0 : $is_admin,
                        'can_manage_all_users' => empty($is_hr) === true ? 0 : $is_hr,
                        'gestionnaire' => empty($is_manager) === true ? 0 : $is_manager,
                        'read_only' => empty($is_read_only) === true ? 0 : $is_read_only,
                        'personal_folder' => empty($has_personal_folder) === true ? 0 : $has_personal_folder,
                        'user_language' => $SETTINGS['default_language'],
                        'fonction_id' => is_null($groups) === true ? '' : implode(';', $groups),
                        'groupes_interdits' => is_null($forbidden_flds) === true ? '' : implode(';', $forbidden_flds),
                        'groupes_visibles' => is_null($allowed_flds) === true ? '' : implode(';', $allowed_flds),
                        'isAdministratedByRole' => $is_administrated_by,
                        'encrypted_psk' => '',
                        'last_pw_change' => time(),
                        'public_key' => $userKeys['public_key'],
                        'private_key' => $userKeys['private_key'],
                        'special' => 'auth-pwd-change',
                        'is_ready_for_usage' => 0,
                        'otp_provided' => 0,
                        'can_create_root_folder' => empty($post_root_level) === true ? 0 : $post_root_level,
                        'mfa_enabled' => empty($mfa_enabled) === true ? 0 : $mfa_enabled,
                        'created_at' => time(),
                    )
                );
                $new_user_id = DB::insertId();
                // Create personnal folder
                if ($has_personal_folder === 1) {
                    DB::insert(
                        prefixTable('nested_tree'),
                        array(
                            'parent_id' => '0',
                            'title' => $new_user_id,
                            'bloquer_creation' => '0',
                            'bloquer_modification' => '0',
                            'personal_folder' => '1',
                            'categories' => '',
                        )
                    );
                    $tree->rebuild();
                }
                // Create folder and role for domain
                if ($new_folder_role_domain === 1) {
                    // create folder
                    DB::insert(
                        prefixTable('nested_tree'),
                        array(
                            'parent_id' => 0,
                            'title' => $domain,
                            'personal_folder' => 0,
                            'renewal_period' => 0,
                            'bloquer_creation' => '0',
                            'bloquer_modification' => '0',
                        )
                    );
                    $new_folder_id = DB::insertId();
                    // Add complexity
                    DB::insert(
                        prefixTable('misc'),
                        array(
                            'type' => 'complex',
                            'intitule' => $new_folder_id,
                            'valeur' => 50,
                            'created_at' => time(),
                        )
                    );
                    // Create role
                    DB::insert(
                        prefixTable('roles_title'),
                        array(
                            'title' => $domain,
                        )
                    );
                    $new_role_id = DB::insertId();
                    // Associate new role to new folder
                    DB::insert(
                        prefixTable('roles_values'),
                        array(
                            'folder_id' => $new_folder_id,
                            'role_id' => $new_role_id,
                        )
                    );
                    // Add the new user to this role
                    DB::update(
                        prefixTable('users'),
                        array(
                            'fonction_id' => is_int($new_role_id),
                        ),
                        'id=%i',
                        $new_user_id
                    );
                    // rebuild tree
                    $tree->rebuild();
                }

                // Create the API key
                DB::insert(
                    prefixTable('api'),
                    array(
                        'type' => 'user',
                        'user_id' => $new_user_id,
                        'value' => encryptUserObjectKey(base64_encode(base64_encode(uniqidReal(39))), $userKeys['public_key']),
                        'timestamp' => time(),
                    )
                );

                // get links url
                if (empty($SETTINGS['email_server_url']) === true) {
                    $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
                }

                // Launch process for user keys creation
                // No OTP is provided here, no need.
                handleUserKeys(
                    (int) $new_user_id,
                    (string) $password,
                    (int) isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    "",
                    true,
                    true,
                    true,
                    false,
                    (string) $lang->get('email_body_user_config_6'),
                );

                // update LOG
                logEvents(
                    $SETTINGS,
                    'user_mngt',
                    'at_user_added',
                    (string) $session->get('user-id'),
                    $session->get('user-login'),
                    (string) $new_user_id
                );

                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'user_id' => $new_user_id,
                        'message' => '',
                    ),
                    'encode'
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_user_exists'),
                    ),
                    'encode'
                );
            }
            break;

            /*
         * Delete the user
         */
        case 'delete_user':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT login, admin, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                // delete user in database
                /*DB::delete(
                    prefixTable('users'),
                    'id = %i',
                    $post_id
                );*/
                DB::update(
                    prefixTable('users'),
                    array(
                        'login' => $data_user['login'].'_deleted_'.time(),
                        'deleted_at' => time(),
                    ),
                    'id = %i',
                    $post_id
                );
                // delete user api
                DB::delete(
                    prefixTable('api'),
                    'user_id = %i',
                    $post_id
                );
                // delete personal folder and subfolders
                $data = DB::queryfirstrow(
                    'SELECT id FROM ' . prefixTable('nested_tree') . '
                    WHERE title = %s AND personal_folder = %i',
                    $post_id,
                    '1'
                );
                // Get through each subfolder
                if (!empty($data['id'])) {
                    $folders = $tree->getDescendants($data['id'], true);
                    foreach ($folders as $folder) {
                        // delete folder
                        DB::delete(prefixTable('nested_tree'), 'id = %i AND personal_folder = %i', $folder->id, '1');
                        // delete items & logs
                        $items = DB::query(
                            'SELECT id FROM ' . prefixTable('items') . '
                            WHERE id_tree=%i AND perso = %i',
                            $folder->id,
                            '1'
                        );
                        foreach ($items as $item) {
                            // Delete item
                            DB::delete(prefixTable('items'), 'id = %i', $item['id']);
                            // log
                            DB::delete(prefixTable('log_items'), 'id_item = %i', $item['id']);
                        }
                    }
                    // rebuild tree
                    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
                    $tree->rebuild();
                }

                // Delete objects keys
                deleteUserObjetsKeys((int) $post_id, $SETTINGS);


                // Delete any process related to user
                $processes = DB::query(
                    'SELECT increment_id
                    FROM ' . prefixTable('background_tasks') . '
                    WHERE JSON_EXTRACT(arguments, "$.new_user_id") = %i',
                    $post_id
                );
                $process_id = -1;
                foreach ($processes as $process) {
                    // Delete task
                    DB::delete(
                        prefixTable('background_subtasks'),
                        'task_id = %i',
                        $process['increment_id']
                    );
                    $process_id = $process['increment_id'];
                }
                // Delete main process
                if ($process_id > -1) {
                    DB::delete(
                        prefixTable('background_tasks'),
                        'increment_id = %i',
                        $process_id
                    );
                }
                
                // update LOG
                logEvents($SETTINGS, 'user_mngt', 'at_user_deleted', (string) $session->get('user-id'), $session->get('user-login'), $post_id);

                //Send back
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                    ),
                    'encode'
                );
            } else {
                //Send back
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
            }
            break;

            /*
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case 'can_create_root_folder':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => $lang->get('error_not_allowed_to')), 'encode');
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'can_create_root_folder' => filter_input(INPUT_POST, 'value', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                    ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
            /*
         * UPDATE ADMIN RIGHTS FOR USER
         */
        case 'admin':
            // Check KEY
            if (
                filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)
                || $session->get('user-admin') === 0
            ) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => $lang->get('error_not_allowed_to')), 'encode');
                exit();
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'admin' => $post_value,
                        'gestionnaire' => $post_value === 1 ? '0' : '0',
                        'read_only' => $post_value === 1 ? '0' : '0',
                    ),
                    'id = %i',
                    $post_id
                );

                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
            /*
         * UPDATE MANAGER RIGHTS FOR USER
         */
        case 'gestionnaire':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => $lang->get('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, can_manage_all_users, gestionnaire
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'gestionnaire' => $post_value,
                        'can_manage_all_users' => ($data_user['can_manage_all_users'] === '0' && $post_value === '1') ? '0' : (
                            ($data_user['can_manage_all_users'] === '0' && $post_value === '0') ? '0' : (
                                ($data_user['can_manage_all_users'] === '1' && $post_value === '0') ? '0' : '1')),
                        'admin' => $post_value === 1 ? '0' : '0',
                        'read_only' => $post_value === 1 ? '0' : '0',
                    ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
            /*
         * UPDATE READ ONLY RIGHTS FOR USER
         */
        case 'read_only':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => $lang->get('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'read_only' => $post_value,
                        'gestionnaire' => $post_value === 1 ? '0' : '0',
                        'admin' => $post_value === 1 ? 0 : '0',
                    ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
            /*
         * UPDATE CAN MANAGE ALL USERS RIGHTS FOR USER
         * Notice that this role must be also Manager
         */
        case 'can_manage_all_users':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => $lang->get('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'can_manage_all_users' => $post_value,
                        'gestionnaire' => ($data_user['gestionnaire'] === '0' && $post_value === 1) ? '1' : (($data_user['gestionnaire'] === '1' && $post_value === 1) ? '1' : (($data_user['gestionnaire'] === '1' && $post_value === 0) ? '1' : '0')),
                        'admin' => $post_value === 1 ? '1' : '0',
                        'read_only' => $post_value === 1 ? '1' : '0',
                    ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
            /*
         * UPDATE PERSONNAL FOLDER FOR USER
         */
        case 'personal_folder':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => $lang->get('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'personal_folder' => $post_value === '1' ? '1' : '0',
                    ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;

            /*
         * Unlock user
         */
        case 'unlock_account':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'disabled' => 0,
                        'no_bad_attempts' => 0,
                    ),
                    'id = %i',
                    $post_id
                );
                // update LOG
                logEvents(
                    $SETTINGS,
                    'user_mngt',
                    'at_user_unlocked',
                    (string) $session->get('user-id'),
                    $session->get('user-login'),
                    $post_id
                );
            }
            break;

            /*
        * Check the domain
        */
        case 'check_domain':
            $return = array();
            // Check if folder exists
            $data = DB::query(
                'SELECT * FROM ' . prefixTable('nested_tree') . '
                WHERE title = %s AND parent_id = %i',
                filter_input(INPUT_POST, 'domain', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                '0'
            );
            $counter = DB::count();
            if ($counter != 0) {
                $return['folder'] = 'exists';
            } else {
                $return['folder'] = 'not_exists';
            }
            // Check if role exists
            $data = DB::query(
                'SELECT * FROM ' . prefixTable('roles_title') . '
                WHERE title = %s',
                filter_input(INPUT_POST, 'domain', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            );
            $counter = DB::count();
            if ($counter != 0) {
                $return['role'] = 'exists';
            } else {
                $return['role'] = 'not_exists';
            }

            echo json_encode($return);
            break;

            /*
        * Get logs for a user
        */
        case 'user_log_items':
            $nb_pages = 1;
            $logs = $sql_filter = '';
            $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>' . $lang->get('pages') . '&nbsp;:&nbsp;</td>';

            // Prepare POST variables
            $post_nb_items_by_page = filter_input(INPUT_POST, 'nb_items_by_page', FILTER_SANITIZE_NUMBER_INT);
            $post_scope = filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($post_scope === 'user_activity') {
                if (
                    null !== filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
                    && !empty(filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS))
                    && filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== 'all'
                ) {
                    $sql_filter = " AND l.action = '" . filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "'";
                }
                // get number of pages
                DB::query(
                    'SELECT *
                    FROM ' . prefixTable('log_items') . ' as l
                    INNER JOIN ' . prefixTable('items') . ' as i ON (l.id_item=i.id)
                    INNER JOIN ' . prefixTable('users') . ' as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i ' . $sql_filter,
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                // define query limits
                if (
                    null !== filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)
                    && filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) > 1
                ) {
                    $start = (intval($post_nb_items_by_page)
                        * (intval(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)) - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = DB::query(
                    'SELECT l.date as date, u.login as login, i.label as label, l.action as action
                    FROM ' . prefixTable('log_items') . ' as l
                    INNER JOIN ' . prefixTable('items') . ' as i ON (l.id_item=i.id)
                    INNER JOIN ' . prefixTable('users') . ' as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i ' . $sql_filter . '
                    ORDER BY date DESC
                    LIMIT ' . intval($start) . ',' . intval($post_nb_items_by_page),
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
            } else {
                // get number of pages
                DB::query(
                    'SELECT *
                    FROM ' . prefixTable('log_system') . '
                    WHERE type = %s AND field_1=%i',
                    'user_mngt',
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                // define query limits
                if (
                    null !== filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)
                    && filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) > 1
                ) {
                    $start = (intval($post_nb_items_by_page)
                        * (intval(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)) - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = DB::query(
                    'SELECT *
                    FROM ' . prefixTable('log_system') . '
                    WHERE type = %s AND field_1 = %i
                    ORDER BY date DESC
                    LIMIT %i, %i',
                    'user_mngt',
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT),
                    filter_var($start, FILTER_SANITIZE_NUMBER_INT),
                    $post_nb_items_by_page
                );
            }
            // generate data
            if (isset($counter) && $counter != 0) {
                $nb_pages = ceil($counter / intval($post_nb_items_by_page));
                for ($i = 1; $i <= $nb_pages; ++$i) {
                    $pages .= '<td onclick=\'displayLogs(' . $i . ',\"' . $post_scope . '\")\'><span style=\'cursor:pointer;' . (filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) === $i ? 'font-weight:bold;font-size:18px;\'>' . $i : '\'>' . $i) . '</span></td>';
                }
            }
            $pages .= '</tr></table>';
            if (isset($rows)) {
                foreach ($rows as $record) {
                    if ($post_scope === 'user_mngt') {
                        $user = DB::queryfirstrow(
                            'SELECT login
                            from ' . prefixTable('users') . '
                            WHERE id=%i',
                            $record['qui']
                        );
                        $tmp = explode(':', $record['label']);
                        // extract action done
                        $label = '';
                        if ($tmp[0] == 'at_user_initial_pwd_changed') {
                            $label = $lang->get('log_user_initial_pwd_changed');
                        } elseif ($tmp[0] == 'at_user_email_changed') {
                            $label = $lang->get('log_user_email_changed') . $tmp[1];
                        } elseif ($tmp[0] == 'at_user_added') {
                            $label = $lang->get('log_user_created');
                        } elseif ($tmp[0] == 'at_user_locked') {
                            $label = $lang->get('log_user_locked');
                        } elseif ($tmp[0] == 'at_user_unlocked') {
                            $label = $lang->get('log_user_unlocked');
                        } elseif ($tmp[0] == 'at_user_pwd_changed') {
                            $label = $lang->get('log_user_pwd_changed');
                        }
                        // prepare log
                        $logs .= '<tr><td>' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']) . '</td><td align=\"center\">' . $label . '</td><td align=\"center\">' . $user['login'] . '</td><td align=\"center\"></td></tr>';
                    } else {
                        $logs .= '<tr><td>' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']) . '</td><td align=\"center\">' . str_replace('"', '\"', $record['label']) . '</td><td align=\"center\">' . $record['login'] . '</td><td align=\"center\">' . $lang->get($record['action']) . '</td></tr>';
                    }
                }
            }

            echo '[ { "table_logs": "' . ($logs) . '", "pages": "' . ($pages) . '", "error" : "no" } ]';
            break;

            /*
        * Migrate the Admin PF to User
        */
        case 'migrate_admin_pf':
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES),
                'decode'
            );
            // Prepare variables
            $user_id = htmlspecialchars_decode($data_received['user_id']);
            $salt_user = htmlspecialchars_decode($data_received['salt_user']);

            if ($salt_user == '') {
                echo '[ { "error" : "no_sk_user" } ]';
            } elseif ($user_id == '') {
                echo '[ { "error" : "no_user_id" } ]';
            } else {
                // Get folder id for Admin
                $admin_folder = DB::queryFirstRow(
                    'SELECT id FROM ' . prefixTable('nested_tree') . '
                    WHERE title = %i AND personal_folder = %i',
                    (int) $session->get('user-id'),
                    '1'
                );
                
                // Get through each subfolder
                foreach ($tree->getDescendants($admin_folder['id'], true) as $folder) {
                    // Get each Items in PF
                    $rows = DB::query(
                        'SELECT i.pw, i.label, l.id_user
                        FROM ' . prefixTable('items') . ' as i
                        LEFT JOIN ' . prefixTable('log_items') . ' as l ON (l.id_item=i.id)
                        WHERE l.action = %s AND i.perso=%i AND i.id_tree=%i',
                        'at_creation',
                        '1',
                        intval($folder->id)
                    );
                    foreach ($rows as $record) {
                        echo $record['label'] . ' - ';
                        // Change user
                        DB::update(
                            prefixTable('log_items'),
                            array(
                                'id_user' => $user_id,
                            ),
                            'id_item = %i AND id_user $ %i AND action = %s',
                            $record['id'],
                            $user_id,
                            'at_creation'
                        );
                    }
                }
                $tree->rebuild();
                echo '[ { "error" : "no" } ]';
            }

            break;

            /*
         * delete the timestamp value for specified user => disconnect
         */
        case 'disconnect_user':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_user_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                // Do
                DB::update(
                    prefixTable('users'),
                    array(
                        'timestamp' => '',
                        'key_tempo' => '',
                        'session_end' => '',
                    ),
                    'id = %i',
                    $post_user_id
                );
            }
            break;

            /*
         * delete the timestamp value for all users
         */
        case 'disconnect_all_users':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== filter_var($session->get('key'), FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Do
            $rows = DB::query(
                'SELECT id FROM ' . prefixTable('users') . '
                WHERE timestamp != %s AND admin != %i',
                '',
                '1'
            );
            foreach ($rows as $record) {
                // Get info about user to delete
                $data_user = DB::queryfirstrow(
                    'SELECT admin, isAdministratedByRole, gestionnaire
                    FROM ' . prefixTable('users') . '
                    WHERE id = %i',
                    $record['id']
                );

                // Is this user allowed to do this?
                if (
                    (int) $session->get('user-admin') === 1
                    || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                    || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
                ) {
                    DB::update(
                        prefixTable('users'),
                        array(
                            'timestamp' => '',
                            'key_tempo' => '',
                            'session_end' => '',
                        ),
                        'id = %i',
                        intval($record['id'])
                    );
                }
            }
            break;
            /*
         * Get user info
         */
        case 'get_user_info':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );
            
            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            // Get info about user
            $rowUser = DB::queryfirstrow(
                'SELECT *
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($rowUser['isAdministratedByRole'], $session->get('user-roles_array')) === true)
                || ((int) $session->get('user-can_manage_all_users') === 1 && $rowUser['admin'] !== '1')
            ) {
                $arrData = array();
                $arrFunction = array();
                $arrMngBy = array();
                $arrFldForbidden = array();
                $arrFldAllowed = array();

                //Build tree
                $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

                // get FUNCTIONS
                $functionsList = array();
                $selected = '';
                $users_functions = array_filter(array_unique(explode(';', empty($rowUser['fonction_id'].';'.$rowUser['roles_from_ad_groups']) === true ? '' : $rowUser['fonction_id'].';'.$rowUser['roles_from_ad_groups'])));
                // array of roles for actual user
                //$my_functions = explode(';', $rowUser['fonction_id']);

                $rows = DB::query('SELECT id,title,creator_id FROM ' . prefixTable('roles_title'));
                foreach ($rows as $record) {
                    if (
                        (int) $session->get('user-admin') === 1
                        || (((int) $session->get('user-manager') === 1 || (int) $session->get('user-can_manage_all_users') === 1)
                            //&& (in_array($record['id'], $my_functions) || $record['creator_id'] == $session->get('user-id'))
                            )
                    ) {
                        if (in_array($record['id'], $users_functions)) {
                            $selected = 'selected';

                            array_push(
                                $arrFunction,
                                array(
                                    'title' => $record['title'],
                                    'id' => $record['id'],
                                )
                            );
                        } else {
                            $selected = '';
                        }

                        array_push(
                            $functionsList,
                            array(
                                'title' => $record['title'],
                                'id' => $record['id'],
                                'selected' => $selected,
                            )
                        );
                    }
                }

                // get MANAGEDBY
                $rolesList = array();
                $managedBy = array();
                $selected = '';
                $rows = DB::query('SELECT id,title FROM ' . prefixTable('roles_title') . ' ORDER BY title ASC');
                foreach ($rows as $reccord) {
                    $rolesList[$reccord['id']] = array('id' => $reccord['id'], 'title' => $reccord['title']);
                }

                array_push(
                    $managedBy,
                    array(
                        'title' => $lang->get('administrators_only'),
                        'id' => 0,
                    )
                );
                foreach ($rolesList as $fonction) {
                    if ($session->get('user-admin') === 1 || in_array($fonction['id'], $session->get('user-roles_array'))) {
                        if ($rowUser['isAdministratedByRole'] == $fonction['id']) {
                            $selected = 'selected';

                            array_push(
                                $arrMngBy,
                                array(
                                    'title' => $fonction['title'],
                                    'id' => $fonction['id'],
                                )
                            );
                        } else {
                            $selected = '';
                        }

                        array_push(
                            $managedBy,
                            array(
                                'title' => $lang->get('managers_of') . ' ' . $fonction['title'],
                                'id' => $fonction['id'],
                                'selected' => $selected,
                            )
                        );
                    }
                }

                if (count($arrMngBy) === 0) {
                    array_push(
                        $arrMngBy,
                        array(
                            'title' => $lang->get('administrators_only'),
                            'id' => '0',
                        )
                    );
                }

                // get FOLDERS FORBIDDEN
                $forbiddenFolders = array();
                $userForbidFolders = explode(';', is_null($rowUser['groupes_interdits']) === true ? '' : $rowUser['groupes_interdits']);
                $tree_desc = $tree->getDescendants();
                foreach ($tree_desc as $t) {
                    if (in_array($t->id, $session->get('user-accessible_folders')) && in_array($t->id, $session->get('user-personal_visible_folders')) === false) {
                        $selected = '';
                        if (in_array($t->id, $userForbidFolders)) {
                            $selected = 'selected';

                            array_push(
                                $arrFldForbidden,
                                array(
                                    'title' => htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                                    'id' => $t->id,
                                )
                            );
                        }
                        array_push(
                            $forbiddenFolders,
                            array(
                                'id' => $t->id,
                                'selected' => $selected,
                                'title' => @htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                            )
                        );
                    }
                }

                // get FOLDERS ALLOWED
                $allowedFolders = array();
                $userAllowFolders = explode(';', $rowUser['groupes_visibles']);
                $tree_desc = $tree->getDescendants();
                foreach ($tree_desc as $t) {
                    if (
                        in_array($t->id, $session->get('user-accessible_folders')) === true
                        && in_array($t->id, $session->get('user-personal_visible_folders')) === false
                    ) {
                        $selected = '';
                        if (in_array($t->id, $userAllowFolders)) {
                            $selected = 'selected';

                            array_push(
                                $arrFldAllowed,
                                array(
                                    'title' => htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                                    'id' => $t->id,
                                )
                            );
                        }

                        array_push(
                            $allowedFolders,
                            array(
                                'id' => $t->id,
                                'selected' => $selected,
                                'title' => @htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                            )
                        );
                    }
                }

                // get USER STATUS
                if ($rowUser['disabled'] == 1) {
                    $arrData['info'] = $lang->get('user_info_locked') . '<br><input type="checkbox" value="unlock" name="1" class="chk">&nbsp;<label for="1">' . $lang->get('user_info_unlock_question') . '</label><br><input type="checkbox"  value="delete" id="account_delete" class="chk mr-2" name="2" onclick="confirmDeletion()">label for="2">' . $lang->get('user_info_delete_question') . '</label>';
                } else {
                    $arrData['info'] = $lang->get('user_info_active') . '<br><input type="checkbox" value="lock" class="chk">&nbsp;' . $lang->get('user_info_lock_question');
                }

                $arrData['error'] = false;
                $arrData['login'] = $rowUser['login'];
                $arrData['name'] = empty($rowUser['name']) === false && $rowUser['name'] !== NULL ? htmlspecialchars_decode($rowUser['name'], ENT_QUOTES) : '';
                $arrData['lastname'] = empty($rowUser['lastname']) === false && $rowUser['lastname'] !== NULL ? htmlspecialchars_decode($rowUser['lastname'], ENT_QUOTES) : '';
                $arrData['email'] = $rowUser['email'];
                $arrData['function'] = $functionsList;
                $arrData['managedby'] = $managedBy;
                $arrData['foldersForbid'] = $forbiddenFolders;
                $arrData['foldersAllow'] = $allowedFolders;
                $arrData['share_function'] = $arrFunction;
                $arrData['share_managedby'] = $arrMngBy;
                $arrData['share_forbidden'] = $arrFldForbidden;
                $arrData['share_allowed'] = $arrFldAllowed;
                $arrData['disabled'] = (int) $rowUser['disabled'];
                $arrData['gestionnaire'] = (int) $rowUser['gestionnaire'];
                $arrData['read_only'] = (int) $rowUser['read_only'];
                $arrData['can_create_root_folder'] = (int) $rowUser['can_create_root_folder'];
                $arrData['personal_folder'] = (int) $rowUser['personal_folder'];
                $arrData['can_manage_all_users'] = (int) $rowUser['can_manage_all_users'];
                $arrData['admin'] = (int) $rowUser['admin'];
                $arrData['password'] = $password_do_not_change;
                $arrData['mfa_enabled'] = (int) $rowUser['mfa_enabled'];

                echo prepareExchangedData(
                    $arrData,
                    'encode'
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
            }

            break;

            /*
         * EDIT user
         */
        case 'store_user_changes':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_login = filter_var($dataReceived['login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_email = filter_var($dataReceived['email'], FILTER_SANITIZE_EMAIL);
            $post_lastname = filter_var($dataReceived['lastname'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_name = filter_var($dataReceived['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_is_admin = filter_var($dataReceived['admin'], FILTER_SANITIZE_NUMBER_INT);
            $post_is_manager = filter_var($dataReceived['manager'], FILTER_SANITIZE_NUMBER_INT);
            $post_is_hr = filter_var($dataReceived['hr'], FILTER_SANITIZE_NUMBER_INT);
            $post_is_read_only = filter_var($dataReceived['read_only'], FILTER_SANITIZE_NUMBER_INT);
            $post_has_personal_folder = filter_var($dataReceived['personal_folder'], FILTER_SANITIZE_NUMBER_INT);
            $post_is_administrated_by = filter_var($dataReceived['isAdministratedByRole'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_groups = filter_var_array($dataReceived['groups'], FILTER_SANITIZE_NUMBER_INT);
            $post_allowed_flds = filter_var_array($dataReceived['allowed_flds'], FILTER_SANITIZE_NUMBER_INT);
            $post_forbidden_flds = filter_var_array($dataReceived['forbidden_flds'], FILTER_SANITIZE_NUMBER_INT);
            $post_root_level = filter_var($dataReceived['form-create-root-folder'], FILTER_SANITIZE_NUMBER_INT);
            $post_mfa_enabled = filter_var($dataReceived['mfa_enabled'], FILTER_SANITIZE_NUMBER_INT);

            // If user disables administrator role 
            // then ensure that it exists still one administrator
            if (empty($post_is_admin) === true) {
                // count number of admins
                $users = DB::query(
                    'SELECT id
                    FROM ' . prefixTable('users') . '
                    WHERE admin = 1 AND email != "" AND pw != "" AND id != %i',
                    $post_id
                );
                if (DB::count() === 0) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('at_least_one_administrator_is_requested'),
                        ),
                        'encode'
                    );
                    break;
                }
            }
            
            // Init post variables
            $post_action_to_perform = filter_var(htmlspecialchars_decode($dataReceived['action_on_user']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $action_to_perform_after = '';
            
            // Exclude roles from AD - PR #3635
            $adRoles = DB::query(
                'SELECT roles_from_ad_groups
                FROM ' . prefixTable('users') . '
                WHERE id = '. $dataReceived['user_id']
            )[0]['roles_from_ad_groups'];
            $fonctions = [];
            if (!is_null($post_groups) && !empty($adRoles)) {
                foreach ($post_groups as $post_group) {
                    if (!in_array($post_group, explode(';', $adRoles))) {
                        $fonctions[] = $post_group;
                    }
                }
            }
            $post_groups = empty($fonctions) === true ? $post_groups : $fonctions;

            // Build array of update
            $changeArray = array(
                'login' => $post_login,
                'name' => $post_name,
                'lastname' => $post_lastname,
                'email' => $post_email,
                'admin' => empty($post_is_admin) === true ? 0 : $post_is_admin,
                'can_manage_all_users' => empty($post_is_hr) === true ? 0 : $post_is_hr,
                'gestionnaire' => empty($post_is_manager) === true ? 0 : $post_is_manager,
                'read_only' => empty($post_is_read_only) === true ? 0 : $post_is_read_only,
                'personal_folder' => empty($post_has_personal_folder) === true ? 0 : $post_has_personal_folder,
                'user_language' => $SETTINGS['default_language'],
                'fonction_id' => is_null($post_groups) === true ? '' : implode(';', array_unique($post_groups)),
                'groupes_interdits' => is_null($post_forbidden_flds) === true ? '' : implode(';', array_unique($post_forbidden_flds)),
                'groupes_visibles' => is_null($post_allowed_flds) === true ? '' : implode(';', array_unique($post_allowed_flds)),
                'isAdministratedByRole' => $post_is_administrated_by,
                'can_create_root_folder' => empty($post_root_level) === true ? 0 : $post_root_level,
                'mfa_enabled' => empty($post_mfa_enabled) === true ? 0 : $post_mfa_enabled,
            );

            // Manage user password change
            // This can occur only if user changes his own password
            // In other case, next condition must be wrong
            if (
                isset($post_password) === true
                && $post_password !== $password_do_not_change
                && $post_id === $session->get('user-id')
            ) {
                // load password library
                $passwordManager = new PasswordManager();

                $changeArray['pw'] = $passwordManager->hashPassword($post_password);
                $changeArray['key_tempo'] = '';

                // We need to adapt the private key with new password
                $session->set('user-private_key', encryptPrivateKey($post_password, $session->get('user-private_key')));

                // TODO
            }

            // Empty user
            if (empty($post_login) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_empty_data'),
                    ),
                    'encode'
                );
                break;
            }

            // User has email?
            if (empty($post_email) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_no_email'),
                    ),
                    'encode'
                );
                break;
            }

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                // delete account
                // delete user in database
                if ($post_action_to_perform === 'delete') {
                    DB::delete(
                        prefixTable('users'),
                        'id = %i',
                        $post_id
                    );
                    // delete personal folder and subfolders
                    $data = DB::queryfirstrow(
                        'SELECT id FROM ' . prefixTable('nested_tree') . '
                        WHERE title = %s AND personal_folder = %i',
                        $post_id,
                        '1'
                    );
                    // Get through each subfolder
                    if (!empty($data['id'])) {
                        $folders = $tree->getDescendants($data['id'], true);
                        foreach ($folders as $folder) {
                            // delete folder
                            DB::delete(prefixTable('nested_tree'), 'id = %i AND personal_folder = %i', $folder->id, '1');
                            // delete items & logs
                            $items = DB::query(
                                'SELECT id FROM ' . prefixTable('items') . '
                                WHERE id_tree=%i AND perso = %i',
                                $folder->id,
                                '1'
                            );
                            foreach ($items as $item) {
                                // Delete item
                                DB::delete(prefixTable('items'), 'id = %i', $item['id']);
                                // log
                                DB::delete(prefixTable('log_items'), 'id_item = %i', $item['id']);
                            }
                        }
                        // rebuild tree
                        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
                        $tree->rebuild();
                    }
                    // update LOG
                    logEvents($SETTINGS, 'user_mngt', 'at_user_deleted', (string) $session->get('user-id'), $session->get('user-login'), $post_id);
                } else {
                    // Get old data about user
                    $oldData = DB::queryfirstrow(
                        'SELECT * FROM ' . prefixTable('users') . '
                        WHERE id = %i',
                        $post_id
                    );

                    // update SESSION
                    if ($session->get('user-id') === $post_id) {
                        $session->set('user-lastname', $post_lastname);
                        $session->set('user-name', $post_name);
                        $session->set('user-email', $post_email);
                    }

                    // Has the groups changed? If yes then ask for a keys regeneration
                    $arrOldData = array_filter(explode(';', $oldData['fonction_id']));
                    $post_groups = is_null($post_groups) === true ? array() : array_filter($post_groups);

                    if ($arrOldData != $post_groups && (int) $oldData['admin'] !== 1) {
                        $action_to_perform_after = 'encrypt_keys';
                    }

                    // update user
                    DB::update(
                        prefixTable('users'),
                        $changeArray,
                        'id = %i',
                        $post_id
                    );

                    // CLear cache tree for this user to force tree
                    DB::delete(
                        prefixTable('cache_tree'),
                        'user_id = %i',
                        $post_id
                    );

                    // update LOG
                    if ($oldData['email'] !== $post_email) {
                        logEvents($SETTINGS, 'user_mngt', 'at_user_email_changed:' . $oldData['email'], (string) $session->get('user-id'), $session->get('user-login'), $post_id);
                    }
                }
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'post_action' => $action_to_perform_after,
                    ),
                    'encode'
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
            }
            break;

            /*
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case 'user_edit_login':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data, 
                'decode'
            );

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'login' => filter_input(INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                        'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                        'lastname' => filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                    ),
                    'id = %i',
                    $post_id
                );
            }
            break;

            /*
         * IS LOGIN AVAILABLE?
         */
        case 'is_login_available':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            DB::queryfirstrow(
                'SELECT * FROM ' . prefixTable('users') . '
                WHERE login = %s
                AND deleted_at IS NULL',
                filter_input(INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            );

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'login_exists' => DB::count(),
                ),
                'encode'
            );

            break;

            /*
         * GET USER FOLDER RIGHT
         */
        case 'user_folders_rights':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Prepare variables
            $post_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

            $arrData = array();

            //Build tree
            $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // get User info
            $rowUser = DB::queryFirstRow(
                'SELECT login, name, lastname, email, disabled, fonction_id, groupes_interdits, groupes_visibles, isAdministratedByRole, avatar_thumb, roles_from_ad_groups
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            // get rights
            $arrFolders = [];
            $html = '';

            if (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1 && isset($SETTINGS['enable_ad_users_with_ad_groups']) === true && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1) {
                $rowUser['fonction_id'] = empty($rowUser['fonction_id'])  === true ? $rowUser['roles_from_ad_groups'] : $rowUser['fonction_id']. ';' . $rowUser['roles_from_ad_groups'];
            }
            $arrData['functions'] = array_filter(explode(';', $rowUser['fonction_id']));
            $arrData['allowed_folders'] = array_filter(explode(';', $rowUser['groupes_visibles']));
            $arrData['denied_folders'] = array_filter(explode(';', $rowUser['groupes_interdits']));

            // Exit if no roles
            if (count($arrData['functions']) > 0) {
                // refine folders based upon roles
                $rows = DB::query(
                    'SELECT folder_id, type
                    FROM ' . prefixTable('roles_values') . '
                    WHERE role_id IN %ls
                    ORDER BY folder_id ASC',
                    $arrData['functions']
                );
                foreach ($rows as $record) {
                    $bFound = false;
                    $x = 0;
                    foreach ($arrFolders as $fld) {
                        if ($fld['id'] === $record['folder_id']) {
                            // get the level of access on the folder
                            $arrFolders[$x]['type'] = evaluateFolderAccesLevel($record['type'], $arrFolders[$x]['type']);
                            $bFound = true;
                            break;
                        }
                        ++$x;
                    }
                    if ($bFound === false && in_array($record['folder_id'], $arrData['denied_folders']) === false) {
                        array_push($arrFolders, array('id' => $record['folder_id'], 'type' => $record['type'], 'special' => false));
                    }
                }

                // add allowed folders
                foreach($arrData['allowed_folders'] as $Fld) {
                    array_push($arrFolders, array('id' => $Fld, 'type' => 'W', 'special' => true));
                }
                
                $tree_desc = $tree->getDescendants();
                foreach ($tree_desc as $t) {
                    foreach ($arrFolders as $fld) {
                        if ($fld['id'] === $t->id) {
                            // get folder name
                            $row = DB::queryFirstRow(
                                'SELECT title, nlevel, id
                                FROM ' . prefixTable('nested_tree') . '
                                WHERE id = %i',
                                $fld['id']
                            );

                            // manage indentation
                            $ident = '';
                            for ($y = 1; $y < $row['nlevel']; ++$y) {
                                $ident .= '<i class="fas fa-long-arrow-alt-right mr-2"></i>';
                            }

                            // manage right icon
                            if ($fld['type'] == 'W') {
                                $label = '<i class="fas fa-indent infotip text-success mr-2" title="' . $lang->get('write') . '"></i>' .
                                    '<i class="fas fa-edit infotip text-success mr-2" title="' . $lang->get('edit') . '"></i>' .
                                    '<i class="fas fa-eraser infotip text-success" title="' . $lang->get('delete') . '"></i>';
                            } elseif ($fld['type'] == 'ND') {
                                $label = '<i class="fas fa-indent infotip text-warning mr-2" title="' . $lang->get('write') . '"></i>' .
                                    '<i class="fas fa-edit infotip text-success mr-2" title="' . $lang->get('edit') . '"></i>' .
                                    '<i class="fas fa-eraser infotip text-danger" title="' . $lang->get('no_delete') . '"></i>';
                            } elseif ($fld['type'] == 'NE') {
                                $label = '<i class="fas fa-indent infotip text-warning mr-2" title="' . $lang->get('write') . '"></i>' .
                                    '<i class="fas fa-edit infotip text-danger mr-2" title="' . $lang->get('no_edit') . '"></i>' .
                                    '<i class="fas fa-eraser infotip text-success" title="' . $lang->get('delete') . '"></i>';
                            } elseif ($fld['type'] == 'NDNE') {
                                $label = '<i class="fas fa-indent infotip text-warning mr-2" title="' . $lang->get('write') . '"></i>' .
                                    '<i class="fas fa-edit infotip text-danger mr-2" title="' . $lang->get('no_edit') . '"></i>' .
                                    '<i class="fas fa-eraser infotip text-danger" title="' . $lang->get('no_delete') . '"></i>';
                            } elseif ($fld['type'] == '') {
                                $label = '<i class="fas fa-eye-slash infotip text-danger mr-2" title="' . $lang->get('no_access') . '"></i>';
                            } else {
                                $label = '<i class="fas fa-eye infotip text-info mr-2" title="' . $lang->get('read') . '"></i>';
                            }

                            $html .= '<tr><td>' . $ident . $row['title'] .
                                ' <small class="text-info">[' . $row['id'] . ']</small>'.
                                ($fld['special'] === true ? '<i class="fas fa-user-tag infotip text-primary ml-5" title="' . $lang->get('user_specific_right') . '"></i>' : '').
                                '</td><td>' . $label . '</td></tr>';
                            break;
                        }
                    }
                }

                $html_full = '<table id="table-folders" class="table table-bordered table-striped dt-responsive nowrap" style="width:100%"><tbody>' .
                    $html . '</tbody></table>';
            } else {
                $html_full = '';
            }

            echo prepareExchangedData(
                array(
                    'html' => $html_full,
                    'error' => false,
                    'login' => $rowUser['login'],
                    'message' => '',
                ),
                'encode'
            );
            break;

            /*
         * GET LIST OF USERS
         */
        case 'get_list_of_users_for_sharing':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $arrUsers = [];

            if ((int) $session->get('user-admin') === 0 && (int) $session->get('user-can_manage_all_users') === 0) {
                $rows = DB::query(
                    'SELECT *
                    FROM ' . prefixTable('users') . '
                    WHERE admin = %i AND isAdministratedByRole IN %ls',
                    '0',
                    array_filter($session->get('user-roles_array'))
                );
            } else {
                $rows = DB::query(
                    'SELECT *
                    FROM ' . prefixTable('users') . '
                    WHERE admin = %i',
                    '0'
                );
            }

            foreach ($rows as $record) {
                // Get roles
                $groups = [];
                $groupIds = [];
                foreach (explode(';', $record['fonction_id']) as $group) {
                    $tmp = DB::queryfirstrow(
                        'SELECT id, title FROM ' . prefixTable('roles_title') . '
                        WHERE id = %i',
                        $group
                    );
                    if ($tmp !== null) {
                        array_push($groups, $tmp['title']);
                        array_push($groupIds, $tmp['id']);
                    }
                }

                // Get managed_by
                $managedBy = DB::queryfirstrow(
                    'SELECT id, title FROM ' . prefixTable('roles_title') . '
                    WHERE id = %i',
                    $record['isAdministratedByRole']
                );

                // Get Allowed folders
                $foldersAllowed = [];
                $foldersAllowedIds = [];
                foreach (explode(';', $record['groupes_visibles']) as $role) {
                    $tmp = DB::queryfirstrow(
                        'SELECT id, title FROM ' . prefixTable('nested_tree') . '
                        WHERE id = %i',
                        $role
                    );
                    array_push($foldersAllowed, $tmp !== null ? $tmp['title'] : $lang->get('none'));
                    array_push($foldersAllowedIds, $tmp !== null ? $tmp['id'] : -1);
                }

                // Get denied folders
                $foldersForbidden = [];
                $foldersForbiddenIds = [];
                foreach (explode(';', $record['groupes_interdits']) as $role) {
                    $tmp = DB::queryfirstrow(
                        'SELECT id, title FROM ' . prefixTable('nested_tree') . '
                        WHERE id = %i',
                        $role
                    );
                    array_push($foldersForbidden, $tmp !== null ? $tmp['title'] : $lang->get('none'));
                    array_push($foldersForbiddenIds, $tmp !== null ? $tmp['id'] : -1);
                }

                // Store
                array_push(
                    $arrUsers,
                    array(
                        'id' => $record['id'],
                        'name' => $record['name'],
                        'lastname' => $record['lastname'],
                        'login' => $record['login'],
                        'groups' => implode(', ', $groups),
                        'groupIds' => $groupIds,
                        'managedBy' => $managedBy=== null ? $lang->get('administrator') : $managedBy['title'],
                        'managedById' => $managedBy === null ? 0 : $managedBy['id'],
                        'foldersAllowed' => implode(', ', $foldersAllowed),
                        'foldersAllowedIds' => $foldersAllowedIds,
                        'foldersForbidden' => implode(', ', $foldersForbidden),
                        'foldersForbiddenIds' => $foldersForbiddenIds,
                        'admin' => $record['admin'],
                        'manager' => $record['gestionnaire'],
                        'hr' => $record['can_manage_all_users'],
                        'readOnly' => $record['read_only'],
                        'personalFolder' => $record['personal_folder'],
                        'rootFolder' => $record['can_create_root_folder'],
                    )
                );
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'values' => $arrUsers,
                ),
                'encode'
            );

            break;

            /*
         * UPDATE USERS RIGHTS BY SHARING
         */
        case 'update_users_rights_sharing':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            $post_source_id = filter_var(htmlspecialchars_decode($dataReceived['source_id']), FILTER_SANITIZE_NUMBER_INT);
            $post_destination_ids = filter_var_array($dataReceived['destination_ids'], FILTER_SANITIZE_NUMBER_INT);
            $post_user_functions = filter_var(htmlspecialchars_decode($dataReceived['user_functions']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_user_managedby = filter_var(htmlspecialchars_decode($dataReceived['user_managedby']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_user_fldallowed = filter_var(htmlspecialchars_decode($dataReceived['user_fldallowed']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_user_fldforbid = filter_var(htmlspecialchars_decode($dataReceived['user_fldforbid']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_user_admin = filter_var(htmlspecialchars_decode($dataReceived['user_admin']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_manager = filter_var(htmlspecialchars_decode($dataReceived['user_manager']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_hr = filter_var(htmlspecialchars_decode($dataReceived['user_hr']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_readonly = filter_var(htmlspecialchars_decode($dataReceived['user_readonly']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_personalfolder = filter_var(htmlspecialchars_decode($dataReceived['user_personalfolder']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_rootfolder = filter_var(htmlspecialchars_decode($dataReceived['user_rootfolder']), FILTER_SANITIZE_NUMBER_INT);

            // Check send values
            if (
                empty($post_source_id) === true
                || $post_destination_ids === 0
            ) {
                // error
                exit();
            }

            // Get info about user
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_source_id
            );

            // Is this user allowed to do this?
            if (
                (int) $session->get('user-admin') === 1
                || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
            ) {
                foreach ($post_destination_ids as $dest_user_id) {
                    // Is this user allowed to do this?
                    if (
                        (int) $session->get('user-admin') === 1
                        || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
                        || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
                    ) {
                        // update user
                        DB::update(
                            prefixTable('users'),
                            array(
                                'fonction_id' => $post_user_functions,
                                'isAdministratedByRole' => $post_user_managedby,
                                'groupes_visibles' => $post_user_fldallowed,
                                'groupes_interdits' => $post_user_fldforbid,
                                'gestionnaire' => $post_user_manager,
                                'read_only' => $post_user_readonly,
                                'can_create_root_folder' => $post_user_rootfolder,
                                'personal_folder' => $post_user_personalfolder,
                                'can_manage_all_users' => $post_user_hr,
                                'admin' => $post_user_admin,
                            ),
                            'id = %i',
                            $dest_user_id
                        );
                    }
                }
            }
            break;

            /*
         * UPDATE USER PROFILE
         */
        case 'user_profile_update':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // Check user
            if (
                null === $session->get('user-id')
                || empty($session->get('user-id')) === true
            ) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('no_user'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            if (empty($dataReceived) === false) {
                // Sanitize
                $data = [
                    'email' => isset($dataReceived['email']) === true ? $dataReceived['email'] : '',
                    'timezone' => isset($dataReceived['timezone']) === true ? $dataReceived['timezone'] : '',
                    'language' => isset($dataReceived['language']) === true ? $dataReceived['language'] : '',
                    'treeloadstrategy' => isset($dataReceived['treeloadstrategy']) === true ? $dataReceived['treeloadstrategy'] : '',
                    'agsescardid' => isset($dataReceived['agsescardid']) === true ? $dataReceived['agsescardid'] : '',
                    'name' => isset($dataReceived['name']) === true ? $dataReceived['name'] : '',
                    'lastname' => isset($dataReceived['lastname']) === true ? $dataReceived['lastname'] : '',
                ];
                
                $filters = [
                    'email' => 'trim|escape',
                    'timezone' => 'trim|escape',
                    'language' => 'trim|escape',
                    'treeloadstrategy' => 'trim|escape',
                    'agsescardid' => 'trim|escape',
                    'name' => 'trim|escape',
                    'lastname' => 'trim|escape',
                ];
                
                $inputData = dataSanitizer(
                    $data,
                    (array) $filters
                );

                // Prevent LFI.
                $inputData['language'] = preg_replace('/[^a-z]/', "", $inputData['language']);

                // Force english if non-existent language.
                if (!file_exists(__DIR__."/../includes/language/".$inputData['language'].".php")) {
                    $inputData['language'] = 'english';
                }

                // update user
                DB::update(
                    prefixTable('users'),
                    array(
                        'email' => $inputData['email'],
                        'usertimezone' => $inputData['timezone'],
                        'user_language' => $inputData['language'],
                        'treeloadstrategy' => $inputData['treeloadstrategy'],
                        'agses-usercardid' => $inputData['agsescardid'],
                        'name' => $inputData['name'],
                        'lastname' => $inputData['lastname'],
                    ),
                    'id = %i',
                    $session->get('user-id')
                );

                // Update SETTINGS
                $session->set('user-timezone', $inputData['timezone']);
                $session->set('user-name', $inputData['name']);
                $session->set('user-lastname', $inputData['lastname']);
                $session->set('user-email', $inputData['email']);
                $session->set('user-tree_load_strategy', $inputData['treeloadstrategy']);
                //$_SESSION['user_agsescardid'] = $inputData['agsescardid'];
                $session->set('user-language', $inputData['language']);

            } else {
                // An error appears on JSON format
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('json_error_format'),
                    ),
                    'encode'
                );
            }

            // Encrypt data to return
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'name' => $session->get('user-name'),
                    'lastname' => $session->get('user-lastname'),
                    'email' => $session->get('user-email'),
                ),
                'encode'
            );
            break;

            //CASE where refreshing table
        case 'save_user_change':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // prepare variables
            $post_user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_field = filter_var($dataReceived['field'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_new_value = filter_var($dataReceived['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_context = filter_var($dataReceived['context'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // If
            if (empty($post_context) === false && $post_context === 'add_one_role_to_user') {
                $data_user = DB::queryfirstrow(
                    'SELECT fonction_id, public_key
                    FROM ' . prefixTable('users') . '
                    WHERE id = %i',
                    $post_user_id
                );

                if ($data_user) {
                    // Ensure array is unique
                    $post_new_value = str_replace(',', ';', $data_user['fonction_id']) . ';' . $post_new_value;
                    $post_new_value = implode(';', array_unique(explode(';', $post_new_value)));
                } else {
                    // User not found
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('user_not_exists'),
                        ),
                        'encode'
                    );
                    break;
                }
            }

            // Manage specific case of api key
            if($post_field === 'user_api_key') {
                $encrypted_key = encryptUserObjectKey(base64_encode($post_new_value), $session->get('user-public_key'));
                $session->set('user-api_key', $post_new_value);

                DB::update(
                    prefixTable('api'),
                    array(
                        'value' => $encrypted_key,
                        'timestamp' => time()
                    ),
                    'user_id = %i',
                    $post_user_id
                );

                // send data
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => ''
                    ),
                    'encode'
                );

                break;
            }

            DB::update(
                prefixTable('users'),
                array(
                    $post_field => $post_new_value,
                ),
                'id = %i',
                $post_user_id
            );

            // special case
            if ($post_field === 'auth_type' && $post_new_value === 'ldap') {
                /*DB::update(
                    prefixTable('users'),
                    array(
                        'special' => 'recrypt-private-key',
                    ),
                    'id = %i',
                    $post_user_id
                );*/
            }

            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => ''
                ),
                'encode'
            );

            break;

        /*
        * GET LDAP LIST OF USERS
        */
        case 'get_list_of_users_in_ldap':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }
            
            // Build ldap configuration array
            $config = [
                // Mandatory Configuration Options
                'hosts'            => explode(',', (string) $SETTINGS['ldap_hosts']),
                'base_dn'          => $SETTINGS['ldap_bdn'],
                'username'         => $SETTINGS['ldap_username'],
                'password'         => $SETTINGS['ldap_password'],
            
                // Optional Configuration Options
                'port'             => $SETTINGS['ldap_port'],
                'use_ssl'          => (int) $SETTINGS['ldap_ssl'] === 1 ? true : false,
                'use_tls'          => (int) $SETTINGS['ldap_tls'] === 1 ? true : false,
                'version'          => 3,
                'timeout'          => 5,
                'follow_referrals' => false,
            
                // Custom LDAP Options
                'options' => [
                    // See: http://php.net/ldap_set_option
                    LDAP_OPT_X_TLS_REQUIRE_CERT => isset($SETTINGS['ldap_tls_certifacte_check']) === false ? 'LDAP_OPT_X_TLS_NEVER' : $SETTINGS['ldap_tls_certifacte_check'],
                ]
            ];
            //prepare connection
            $connection = new Connection($config);

            // Connect to LDAP
            try {
                $connection->connect();
            
            } catch (\LdapRecord\Auth\BindException $e) {
                $error = $e->getDetailedError();
                error_log('TEAMPASS Error - Users - '.$error->getErrorCode()." - ".$error->getErrorMessage(). " - ".$error->getDiagnosticMessage());
                // deepcode ignore ServerLeak: No important data is sent and it is encrypted before sending
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => "An error occurred.",
                    ),
                    'encode'
                );
                break;
            }

            $adUsersToSync = array();
            $adRoles = array();
            $usersAlreadyInTeampass = array();
            $adUsedAttributes = array(
                'dn', 'mail', 'givenname', 'samaccountname', 'sn', $SETTINGS['ldap_user_attribute'],
                'memberof', 'name', 'displayname', 'cn', 'shadowexpire', 'distinguishedname'
            );

            try {
                $results = $connection->query()
                    ->rawfilter($SETTINGS['ldap_user_object_filter'])
                    ->in((empty($SETTINGS['ldap_dn_additional_user_dn']) === false ? $SETTINGS['ldap_dn_additional_user_dn'].',' : '').$SETTINGS['ldap_bdn'])
                    ->whereHas($SETTINGS['ldap_user_attribute'])
                    ->paginate(100);
            } catch (\LdapRecord\Auth\BindException $e) {
                $error = $e->getDetailedError();
                error_log('TEAMPASS Error - Users - '.$error->getErrorCode()." - ".$error->getErrorMessage(). " - ".$error->getDiagnosticMessage());
                // deepcode ignore ServerLeak: No important data is sent and it is encrypted before sending
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => "An error occurred.",
                    ),
                    'encode'
                );
                break;
            }
            
            foreach ($results as $adUser) {
                if (isset($adUser[$SETTINGS['ldap_user_attribute']][0]) === false) continue;
                // Build the list of all groups in AD
                if (isset($adUser['memberof']) === true) {
                    foreach($adUser['memberof'] as $j => $adUserGroup) {
                        if (empty($adUserGroup) === false && $j !== "count") {
                            $adGroup = substr($adUserGroup, 3, strpos($adUserGroup, ',') - 3);
                            if (in_array($adGroup, $adRoles) === false && empty($adGroup) === false) {
                                array_push($adRoles, $adGroup);
                            }
                        }
                    }
                }

                // Is user in Teampass ?
                $userLogin = $adUser[$SETTINGS['ldap_user_attribute']][0];
                if (null !== $userLogin) {
                    // Get his ID
                    $userInfo = DB::queryfirstrow(
                        'SELECT id, login, fonction_id, auth_type
                        FROM ' . prefixTable('users') . '
                        WHERE login = %s',
                        $userLogin
                    );
                    // Loop on all user attributes
                    $tmp = [
                        'userInTeampass' => DB::count() > 0 ? (int) $userInfo['id'] : DB::count(),
                        'userAuthType' => isset($userInfo['auth_type']) === true ? $userInfo['auth_type'] : 0,                        
                    ];
                    foreach ($adUsedAttributes as $userAttribute) {
                        if (isset($adUser[$userAttribute]) === true) {
                            if (is_array($adUser[$userAttribute]) === true && array_key_first($adUser[$userAttribute]) !== 'count') {
                                // Loop on all entries
                                $tmpAttrValue = '';
                                foreach ($adUser[$userAttribute] as $userAttributeEntry) {
                                    if ($userAttribute === 'memberof') {
                                        $userAttributeEntry = substr($userAttributeEntry, 3, strpos($userAttributeEntry, ',') - 3);
                                    }
                                    if (empty($tmpAttrValue) === true) {
                                        $tmpAttrValue = $userAttributeEntry;
                                    } else {
                                        $tmpAttrValue .= ','.$userAttributeEntry;
                                    }
                                }
                                $tmp[$userAttribute] = $tmpAttrValue;
                            } else {
                                $tmp[$userAttribute] = $adUser[$userAttribute];
                            }
                        }
                    }
                    array_push($adUsersToSync, $tmp);
                }
            }

            // Get all groups in Teampass
            $teampassRoles = array();
            $rows = DB::query('SELECT id,title FROM ' . prefixTable('roles_title'));
            foreach ($rows as $record) {
                array_push(
                    $teampassRoles,
                    array(
                        'id' => $record['id'],
                        'title' => $record['title']
                    )
                );
            }

            echo (string) prepareExchangedData(
                array(
                    'error' => false,
                    'entries' => $adUsersToSync,
                    'ldap_groups' => $adRoles,
                    'teampass_groups' => $teampassRoles,
                    'usersAlreadyInTeampass' => $usersAlreadyInTeampass,
                ), 
                'encode'
            );

            break;

        /*
         * ADD USER FROM LDAP
         */
        case 'add_user_from_ldap':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_login = filter_var($dataReceived['login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_name = filter_var($dataReceived['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_lastname = filter_var($dataReceived['lastname'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_email = filter_var($dataReceived['email'], FILTER_SANITIZE_EMAIL);
            $post_roles = filter_var_array(
                $dataReceived['roles'],
                FILTER_SANITIZE_NUMBER_INT
            );

            // Empty user
            if (empty($post_login) === true || empty($post_email) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('user_must_have_login_and_email'),
                    ),
                    'encode'
                );
                break;
            }
            // Check if user already exists
            $data = DB::query(
                'SELECT id, fonction_id, groupes_interdits, groupes_visibles
                FROM ' . prefixTable('users') . '
                WHERE login = %s',
                $post_login
            );

            if (DB::count() === 0) {
                // check if admin role is set. If yes then check if originator is allowed
                if ((int) $session->get('user-admin') !== 1 && (int) $session->get('user-manager') !== 1) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_empty_data'),
                        ),
                        'encode'
                    );
                    break;
                }

                // load password library
                $passwordManager = new PasswordManager();

                // Prepare variables
                $password = generateQuickPassword(12, true);
                $hashedPassword = $passwordManager->hashPassword($password);
                if ($passwordManager->verifyPassword($hashedPassword, $password) === false) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to'),
                        ),
                        'encode'
                    );
                    break;
                }
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_user_exists'),
                    ),
                    'encode'
                );
                break;
            }

            
            // We need to create his keys
            $userKeys = generateUserKeys($password);

            // Insert user in DB
            DB::insert(
                prefixTable('users'),
                array(
                    'login' => $post_login,
                    'pw' => $hashedPassword,
                    'email' => $post_email,
                    'name' => $post_name,
                    'lastname' => $post_lastname,
                    'admin' => '0',
                    'gestionnaire' => '0',
                    'can_manage_all_users' => '0',
                    'personal_folder' => (int) $SETTINGS['enable_pf_feature'] === 1 ? 1 : 0,
                    'fonction_id' => implode(';', $post_roles),
                    'groupes_interdits' => '',
                    'groupes_visibles' => '',
                    'last_pw_change' => time(),
                    'user_language' => $SETTINGS['default_language'],
                    'encrypted_psk' => '',
                    'isAdministratedByRole' => (isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && empty($SETTINGS['ldap_new_user_is_administrated_by']) === false) ? $SETTINGS['ldap_new_user_is_administrated_by'] : 0,
                    'public_key' => $userKeys['public_key'],
                    'private_key' => $userKeys['private_key'],
                    'special' => 'user_added_from_ldap',
                    'auth_type' => 'ldap',
                    'is_ready_for_usage' => isset($SETTINGS['enable_tasks_manager']) === true && (int) $SETTINGS['enable_tasks_manager'] === 1 ? 0 : 1,
                    'created_at' => time(),
                )
            );
            $newUserId = DB::insertId();

            // Create the API key
            DB::insert(
                prefixTable('api'),
                array(
                    'type' => 'user',
                    //'label' => $newUserId,
                    'value' => encryptUserObjectKey(base64_encode(uniqidReal(39)), $userKeys['public_key']),
                    'timestamp' => time(),
                    'user_id' => $newUserId,
                    'allowed_folders' => '',
                )
            );

            // Create personnal folder
            if (isset($SETTINGS['enable_pf_feature']) === true && (int) $SETTINGS['enable_pf_feature'] === 1) {
                DB::insert(
                    prefixTable('nested_tree'),
                    array(
                        'parent_id' => '0',
                        'title' => $newUserId,
                        'bloquer_creation' => '0',
                        'bloquer_modification' => '0',
                        'personal_folder' => '1',
                        'fa_icon' => 'fas fa-folder',
                        'fa_icon_selected' => 'fas fa-folder-open',
                        'categories' => '',
                    )
                );

                // Rebuild tree
                $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
                $tree->rebuild();
            }

            // Send email to new user
            if (isset($SETTINGS['enable_tasks_manager']) === false || (int) $SETTINGS['enable_tasks_manager'] === 0) {
                sendEmail(
                    $lang->get('email_subject_new_user'),
                    str_replace(
                        array('#tp_login#', '#enc_code#', '#tp_link#'),
                        array(addslashes($post_login), addslashes($password), $SETTINGS['email_server_url']),
                        $lang->get('email_body_user_added_from_ldap_encryption_code')
                    ),
                    $post_email,
                    $SETTINGS
                );
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'user_id' => $newUserId,
                    'user_code' => $password,
                    'visible_otp' => ADMIN_VISIBLE_OTP_ON_LDAP_IMPORT,
                    'post_action' => isset($SETTINGS['enable_tasks_manager']) === true && (int) $SETTINGS['enable_tasks_manager'] === 1 ? 'prepare_tasks' : 'encrypt_keys',
                    //'extra' => decryptPrivateKey($password, $userKeys['private_key']),
                    //'extra2' => $userKeys['private_key'],
                ),
                'encode'
            );

            break;

        /*
        * ADD USER KEYS CREATION - FINISHING
        */
        case 'finishing_user_keys_creation':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_userId = filter_var($dataReceived['user_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_otp = filter_var($dataReceived['user_new_otp'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            DB::update(
                prefixTable('users'),
                array(
                    'is_ready_for_usage' => 1,
                ),
                'id = %i',
                $post_userId
            );

            // Send mail to user with new OTP
            $userInfo = DB::queryFirstRow(
                'SELECT email
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_userId
            );
            sendEmail(
                'TEAMPASS - ' . $lang->get('temporary_encryption_code'),
                str_replace(
                    array('#enc_code#'),
                    array($post_otp),
                    $lang->get('email_body_user_added_from_ldap_encryption_code')
                ),
                $userInfo['email'],
                $SETTINGS
            );


            echo prepareExchangedData(
                array(
                    'message' => '',
                    'error' => false,
                ),
                'encode'
            );

            break;

            /*
         * CHANGE USER AUTHENTICATION TYPE
         */
        case 'change_user_auth_type':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
            $post_auth = filter_var($dataReceived['auth_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);


            // Empty user
            if (empty($post_id) === true || empty($post_id) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('user_not_exists'),
                    ),
                    'encode'
                );
                break;
            }
            // Check if user already exists
            DB::query(
                'SELECT id
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            if (DB::count() > 0) {
                // Change authentication type in DB
                DB::update(
                    prefixTable('users'),
                    array(
                        'auth_type' => $post_auth,
                    ),
                    'id = %i',
                    $post_id
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('user_not_exists'),
                    ),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array(
                    'message' => '',
                    'error' => false,
                ),
                'encode'
            );

            break;

            /*
         * CHANGE USER PRIVATE KEY WITH OTC / PWD
         */
        case 'change_user_privkey_with_otc':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_userid = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_password = filter_var($dataReceived['password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_otc = filter_var($dataReceived['otc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Empty user
            if (empty($post_userid) === true || empty($post_password) === true || empty($post_otc) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('data_are_missing'),
                    ),
                    'encode'
                );
                break;
            }


            // Check if user already exists
            $userInfo = DB::queryfirstrow(
                'SELECT id, private_key, public_key
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_userid
            );
            if (DB::count() === 0) {
                // Error - user not exists
                echo prepareExchangedData(
                    array(
                        'message' => $lang->get('user_not_exists'),
                        'error' => true,
                    ),
                    'encode'
                );
                break;
            }

            if (empty($userInfo['private_key']) === true) {
                // Error - user has private key
                echo prepareExchangedData(
                    array(
                        'message' => $lang->get('error_no_user_encryption_keys'),
                        'error' => true,
                    ),
                    'encode'
                );
                break;
            }

            // Encrypte private key with user password
            // and not the OTC

            // Uncrypt private key with OTC
            $userPrivateKey = decryptPrivateKey($post_otc, $userInfo['private_key']);
            // Crypt it with user's password
            $userPrivateKeyEncrypted = encryptPrivateKey(
                $post_password,
                $userPrivateKey
            );
            // Save it in session
            $session->set('user-private_key', $userPrivateKey);

            // Check if this user has some private items.
            // If yes then upgrade needed for them
            // If no then upgrade is now finished
            if (count($session->get('user-personal_folders')) > 0) {
                DB::query(
                    'SELECT id
                    FROM ' . prefixTable('items') . '
                    WHERE id_tree IN %ls',
                    $session->get('user-personal_folders')
                );

                if (DB::count() === 0) {
                    $upgrade_needed = 0;
                    $special_status = 'none';
                } else {
                    $upgrade_needed = 1;
                    $special_status = 'private_items_to_encrypt';
                }
            } else {
                $upgrade_needed = 0;
                $special_status = 'none';
            }

            // Update table
            DB::update(
                prefixTable('users'),
                array(
                    'private_key' => $userPrivateKeyEncrypted,
                    'special' => $special_status,
                    'upgrade_needed' => $upgrade_needed,
                ),
                'id=%i',
                $userInfo['id']
            );

            // Send back
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );

            break;

        /*
         * CHANGE USER DISABLE
         */
        case 'manage_user_disable_status':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_user_disabled = filter_var($dataReceived['disabled_status'], FILTER_SANITIZE_NUMBER_INT);


            // Empty user
            if (empty($post_id) === true || empty($post_id) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('user_not_exists'),
                    ),
                    'encode'
                );
                break;
            }
            // Check if user already exists
            DB::query(
                'SELECT id
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $post_id
            );

            if (DB::count() > 0) {
                // Change authentication type in DB
                DB::update(
                    prefixTable('users'),
                    array(
                        'disabled' => empty($post_user_disabled) === true ? 0 : $post_user_disabled,
                    ),
                    'id = %i',
                    $post_id
                );

                // update LOG
                logEvents(
                    $SETTINGS,
                    'user_mngt',
                    $post_user_disabled === 1 ? 'at_user_locked' : 'at_user_unlocked',
                    (string) $session->get('user-id'),
                    $session->get('user-login'),
                    $post_id
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('user_not_exists'),
                    ),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array(
                    'message' => '',
                    'error' => false,
                ),
                'encode'
            );

            break;

        case "create_new_user_tasks":
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_user_pwd = isset($dataReceived['user_pwd']) === true ? ($dataReceived['user_pwd']) : '';
            $post_user_code = ($dataReceived['user_code']);

            // Create process
            DB::insert(
                prefixTable('background_tasks'),
                array(
                    'created_at' => time(),
                    'process_type' => 'create_user_keys',
                    'arguments' => json_encode([
                        'new_user_id' => (int) $post_user_id,
                        'new_user_pwd' => empty($post_user_pwd) === true ? '' : cryption($post_user_pwd, '','encrypt', $SETTINGS)['string'],
                        'new_user_code' => cryption($post_user_code, '','encrypt', $SETTINGS)['string'],
                        'owner_id' => (int) $session->get('user-id'),
                        'creator_pwd' => cryption($session->get('user-password'), '','encrypt', $SETTINGS)['string'],
                        'email_body' => $lang->get('email_body_user_config_5'),
                        'send_email' => 1,
                    ]),
                    'updated_at' => '',
                    'finished_at' => '',
                    'output' => '',
                )
            );
            $processId = DB::insertId();

            // Create tasks
            DB::insert(
                prefixTable('background_subtasks'),
                array(
                    'task_id' => $processId,
                    'created_at' => time(),
                    'task' => json_encode([
                        'step' => 'step0',
                        'index' => 0,
                        'nb' => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    ]),
                )
            );

            DB::insert(
                prefixTable('background_subtasks'),
                array(
                    'task_id' => $processId,
                    'created_at' => time(),
                    'task' => json_encode([
                        'step' => 'step10',
                        'index' => 0,
                        'nb' => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    ]),
                )
            );

            DB::insert(
                prefixTable('background_subtasks'),
                array(
                    'task_id' => $processId,
                    'created_at' => time(),
                    'task' => json_encode([
                        'step' => 'step20',
                        'index' => 0,
                        'nb' => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    ]),
                )
            );

            DB::insert(
                prefixTable('background_subtasks'),
                array(
                    'task_id' => $processId,
                    'created_at' => time(),
                    'task' => json_encode([
                        'step' => 'step30',
                        'index' => 0,
                        'nb' => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    ]),
                )
            );

            DB::insert(
                prefixTable('background_subtasks'),
                array(
                    'task_id' => $processId,
                    'created_at' => time(),
                    'task' => json_encode([
                        'step' => 'step40',
                        'index' => 0,
                        'nb' => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    ]),
                )
            );

            DB::insert(
                prefixTable('background_subtasks'),
                array(
                    'task_id' => $processId,
                    'created_at' => time(),
                    'task' => json_encode([
                        'step' => 'step50',
                        'index' => 0,
                        'nb' => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    ]),
                )
            );

            DB::insert(
                prefixTable('background_subtasks'),
                array(
                    'task_id' => $processId,
                    'created_at' => time(),
                    'task' => json_encode([
                        'step' => 'step60',
                        'index' => 0,
                        'nb' => isset($SETTINGS['maximum_number_of_items_to_treat']) === true ? $SETTINGS['maximum_number_of_items_to_treat'] : NUMBER_ITEMS_IN_BATCH,
                    ]),
                )
            );

            //update user is not ready
            DB::update(
                prefixTable('users'),
                array(
                    'is_ready_for_usage' => 1,
                    'otp_provided' => 0,
                    'ongoing_process_id' => $processId,
                    'special' => 'generate-keys',
                ),
                'id = %i',
                $post_user_id
            );

            echo prepareExchangedData(
                array(
                    'message' => '',
                    'error' => false,
                ),
                'encode'
            );

            break;

        /*
         * GENERATE NEW KEYS AND OTP FOR A USER - STEP 1
         */
        case 'generate_new_otp__preparation':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            // get user info
            $userInfo = DB::queryFirstRow(
                'SELECT *
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $user_id
            );

            // Generate pwd
            $password = generateQuickPassword();

            // GEnerate new keys
            $userKeys = generateUserKeys($password);

            // Prepare values to change
            $values = array(
                'public_key' => $userKeys['public_key'],
                'private_key' => $userKeys['private_key'],
                'is_ready_for_usage' => 0,
                'otp_provided' => 0,
                'special' => $userInfo['auth_type'] === 'local' ? 'otc_is_required_on_next_login' : ($userInfo['auth_type'] === 'ldap' ? 'user_added_from_ldap' : ''),
            );

            // update profil
            DB::update(
                prefixTable('users'),
                $values,
                'id = %i',
                $user_id
            );

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'user_id' => $user_id,
                    'user_code' => $password,
                    'visible_otp' => ADMIN_VISIBLE_OTP_ON_LDAP_IMPORT,
                    'post_action' => 'prepare_tasks',
                ),
                'encode'
            );

            break;

        /*
        * WHAT IS THE PROGRESS OF GENERATING NEW KEYS AND OTP FOR A USER
        */
        case 'get_generate_keys_progress':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            if (isset($dataReceived['user_id']) === false) {
                // Exit nothing to be done
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => '',
                        'user_id' => '',
                        'status' => '',
                        'debug' => '',
                    ),
                    'encode'
                );
            }

            // Prepare variables
            $user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            // get user info
            $processesProgress = DB::query(
                'SELECT u.ongoing_process_id, pt.task, pt.updated_at, pt.finished_at, pt.is_in_progress
                FROM ' . prefixTable('users') . ' AS u
                INNER JOIN ' . prefixTable('background_subtasks') . ' AS pt ON (pt.task_id = u.ongoing_process_id)
                WHERE u.id = %i',
                $user_id
            );

            //print_r($processesProgress);
            $finished_steps = 0;
            $nb_steps = count($processesProgress);
            foreach($processesProgress as $process) {
                if ((int) $process['is_in_progress'] === -1) {
                    $finished_steps ++;
                }
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'user_id' => $user_id,
                    'status' => $finished_steps === $nb_steps ? 'finished' : number_format($finished_steps/$nb_steps*100, 0).'%',
                    'debug' => $finished_steps.",".$nb_steps,
                ),
                'encode'
            );

            break;


        /**
         * CHECK IF USER IS FINISHED WITH GENERATING NEW KEYS AND OTP FOR A USER
         */
        case "get-user-infos":
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );
            // Prepare variables
            $user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            $userInfos = getFullUserInfos((int) $user_id);

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'user_id' => $user_id,
                    'user_infos' => $userInfos,
                    'debug' => '',
                ),
                'encode'
            );

            break;
    }
    // # NEW LOGIN FOR USER HAS BEEN DEFINED ##
} elseif (!empty(filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_FULL_SPECIAL_CHARS))) {
    // Prepare POST variables
    $value = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $post_newValue = filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Get info about user
    $data_user = DB::queryfirstrow(
        'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
        WHERE id = %i',
        $value[1]
    );

    // Is this user allowed to do this?
    if (
        (int) $session->get('user-admin') === 1
        || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
        || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
        || ($session->get('user-id') === $value[1])
    ) {
        if ($value[0] === 'userlanguage') {
            $value[0] = 'user_language';
            $post_newValue = strtolower($post_newValue);
        }
        // Check that operation is allowed
        if (in_array(
            $value[0],
            array('login', 'pw', 'email', 'treeloadstrategy', 'usertimezone', 'yubico_user_key', 'yubico_user_id', 'agses-usercardid', 'user_language', 'psk')
        )) {
            DB::update(
                prefixTable('users'),
                array(
                    $value[0] => $post_newValue,
                ),
                'id = %i',
                $value[1]
            );
            // update LOG
            logEvents(
                $SETTINGS,
                'user_mngt',
                'at_user_new_' . $value[0] . ':' . $value[1],
                (string) $session->get('user-id'),
                $session->get('user-login'),
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            );
            // refresh SESSION if requested
            if ($value[0] === 'treeloadstrategy') {
                $session->set('user-tree_load_strategy', $post_newValue);
            } elseif ($value[0] === 'usertimezone') {
                // special case for usertimezone where session needs to be updated
                $session->set('user-timezone', $post_newValue);
            } elseif ($value[0] === 'userlanguage') {
                // special case for user_language where session needs to be updated
                $session->set('user-language', $post_newValue);
            } elseif ($value[0] === 'agses-usercardid') {
                // special case for agsescardid where session needs to be updated
                //$_SESSION['user_agsescardid'] = $post_newValue;
            } elseif ($value[0] === 'email') {
                // store email change in session
                $session->set('user-email', $post_newValue);
            }
            // Display info
            echo htmlentities($post_newValue, ENT_QUOTES);
        }
    }
    // # ADMIN FOR USER HAS BEEN DEFINED ##
} elseif (null !== filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT)) {
    $id = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    // Get info about user
    $data_user = DB::queryfirstrow(
        'SELECT admin, isAdministratedByRole FROM ' . prefixTable('users') . '
        WHERE id = %i',
        $value[1]
    );

    // Is this user allowed to do this?
    if (
        (int) $session->get('user-admin') === 1
        || (in_array($data_user['isAdministratedByRole'], $session->get('user-roles_array')))
        || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $data_user['admin'] !== 1)
        || ($session->get('user-id') === $value[1])
    ) {
        DB::update(
            prefixTable('users'),
            array(
                'admin' => filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT),
            ),
            'id = %i',
            $id[1]
        );
        // Display info
        if (filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT) === 1) {
            echo 'Oui';
        } else {
            echo 'Non';
        }
    }
}

/**
 * Return the level of access on a folder.
 *
 * @param string $new_val      New value
 * @param string $existing_val Current value
 *
 * @return string Returned index
 */
function evaluateFolderAccesLevel($new_val, $existing_val)
{
    $levels = array(
        'W' => 30,
        'ND' => 20,
        'NE' => 15,
        'NDNE' => 10,
        'R' => 10,
    );

    $current_level_points = empty($existing_val) === true ? 0 : $levels[$existing_val];
    $new_level_points = empty($new_val) === true ? 0 : $levels[$new_val];

    // check if new is > to current one (always keep the highest level)
    if (($new_val === 'ND' && $existing_val === 'NE')
        || ($new_val === 'NE' && $existing_val === 'ND')
    ) {
        return 'NDNE';
    } else {
        if ($current_level_points > $new_level_points) {
            return $existing_val;
        } else {
            return $new_val;
        }
    }
}
