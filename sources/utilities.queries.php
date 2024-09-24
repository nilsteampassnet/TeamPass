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
 * @file      utilities.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once 'main.functions.php';

// init
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
    $checkUserAccess->userAccessPage('utilities.database') === false ||
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

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Construction de la requ?te en fonction du type de valeur
if (null !== $post_type) {
    switch ($post_type) {
            //CASE list of recycled elements
        case 'recycled_bin_elements':
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

            // Get list of deleted FOLDERS
            $arrFolders = array();
            $rows = DB::query(
                'SELECT valeur, intitule
                FROM ' . prefixTable('misc') . '
                WHERE type  = %s',
                'folder_deleted'
            );
            foreach ($rows as $record) {
                $folderInfo = explode(', ', $record['valeur']);
                array_push(
                    $arrFolders,
                    array(
                        'id' => $folderInfo[0],
                        'label' => $folderInfo[2],
                    )
                );
            }

            //Get list of deleted ITEMS
            $arrItems = array();
            //DB::debugmode(true);
            $rows = DB::query(
                'SELECT u.login as login, u.name as name, u.lastname as lastname,
                i.id as id, i.label as label,
                i.id_tree as id_tree, l.date as date, n.title as folder_title
                FROM ' . prefixTable('log_items') . ' as l
                INNER JOIN ' . prefixTable('items') . ' as i ON (l.id_item=i.id)
                LEFT JOIN ' . prefixTable('users') . ' as u ON (l.id_user=u.id)
                LEFT JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree=n.id)
                WHERE i.inactif = %i
                AND l.action = %s',
                1,
                'at_delete'
            );
            $prev_id = '';
            foreach ($rows as $record) {
                if ($record['id'] !== $prev_id) {
                    if (array_search($record['id_tree'], array_column($arrFolders, 'id')) !== false) {
                        $thisFolder = true;
                    } else {
                        $thisFolder = false;
                    }

                    array_push(
                        $arrItems,
                        array(
                            'id' => $record['id'],
                            'label' => $record['label'],
                            'date' => date($SETTINGS['date_format'], (int) $record['date']),
                            'login' => $record['login'],
                            'name' => $record['name'] . ' ' . $record['lastname'],
                            'folder_label' => $record['folder_title'],
                            'folder_deleted' => $thisFolder,
                        )
                    );
                }
                $prev_id = $record['id'];
            }
            
            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'folders' => $arrFolders,
                    'items' => $arrItems,
                ),
                'encode'
            );
            break;

        //CASE recycle selected recycled elements
        case 'restore_selected_objects':
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
            $post_folders = filter_var_array($dataReceived['folders'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_items = filter_var_array($dataReceived['items'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Folders restore
            foreach ($post_folders as $folderId) {
                $data = DB::queryfirstrow(
                    'SELECT valeur
                    FROM ' . prefixTable('misc') . "
                    WHERE type = 'folder_deleted'
                    AND intitule = %s",
                    'f' . $folderId
                );
                if ((int) $data['valeur'] !== 0) {
                    $folderData = explode(', ', $data['valeur']);
                    //insert deleted folder
                    DB::insert(
                        prefixTable('nested_tree'),
                        array(
                            'id' => $folderData[0],
                            'parent_id' => $folderData[1],
                            'title' => $folderData[2],
                            'nleft' => $folderData[3],
                            'nright' => $folderData[4],
                            'nlevel' => $folderData[5],
                            'bloquer_creation' => $folderData[6],
                            'bloquer_modification' => $folderData[7],
                            'personal_folder' => $folderData[8],
                            'renewal_period' => $folderData[9],
                            'categories' => '',
                        )
                    );

                    //delete log
                    DB::delete(
                        prefixTable('misc'),
                        'type = %s AND intitule = %s',
                        'folder_deleted',
                        'f' . $folderId
                    );

                    // Restore all items in this folder
                    DB::update(
                        prefixTable('items'),
                        array(
                            'inactif' => '0',
                        ),
                        'id_tree = %s',
                        (int) $folderId
                    );

                    // Get list of all items in thos folder
                    $items = DB::query(
                        'SELECT id
                        FROM ' . prefixTable('items') . '
                        WHERE id_tree = %i',
                        $folderId
                    );

                    // log
                    foreach ($items as $item) {
                        logItems(
                            $SETTINGS,
                            (int) $item['id'],
                            '',
                            $session->get('user-id'),
                            'at_restored',
                            $session->get('user-login')
                        );
                    }
                }
            }

            //restore ITEMS
            foreach ($post_items as $itemId) {
                DB::update(
                    prefixTable('items'),
                    array(
                        'inactif' => '0',
                    ),
                    'id = %i',
                    $itemId
                );
                //log
                logItems(
                    $SETTINGS,
                    (int) $itemId,
                    '',
                    $session->get('user-id'),
                    'at_restored',
                    $session->get('user-login')
                );
            }

            updateCacheTable('reload', null);

            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

        //CASE delete selected recycled elements
        case 'delete_selected_objects':
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
            $post_folders = filter_var_array($dataReceived['folders'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_items = filter_var_array($dataReceived['items'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Folders to delete
            foreach ($post_folders as $folderId) {
                $data = DB::queryfirstrow(
                    'SELECT valeur
                    FROM ' . prefixTable('misc') . "
                    WHERE type = 'folder_deleted'
                    AND intitule = %s",
                    'f' . $folderId
                );
                if ((int) $data['valeur'] !== 0) {
                    $exploded = explode(',', $data['valeur']);
                    $folderData = array_map('trim', $exploded);

                    //delete log
                    DB::delete(
                        prefixTable('misc'),
                        'type = %s AND intitule = %s',
                        'folder_deleted',
                        'f' . $folderData[0]
                    );

                    // Delete all items in this folder
                    DB::delete(
                        prefixTable('items'),
                        'id_tree = %s AND inactif = %i',
                        $folderData[0],
                        1
                    );

                    // Get list of all items in thos folder
                    $items = DB::query(
                        'SELECT id
                        FROM ' . prefixTable('items') . '
                        WHERE id_tree = %i',
                        $folderData[0]
                    );

                    // log
                    foreach ($items as $item) {
                        // delete logs
                        DB::delete(
                            prefixTable('log_items'),
                            'id_item = %i',
                            $item['id']
                        );

                        // Delete all fields
                        $fields = DB::query(
                            'SELECT id
                            FROM ' . prefixTable('categories_items') . '
                            WHERE item_id = %i',
                            $folderData[0]
                        );
                        foreach ($fields as $field) {
                            DB::delete(
                                prefixTable('sharekeys_fields'),
                                'object_id = %i',
                                $field['id']
                            );
                        }
                        DB::delete(
                            prefixTable('categories_items'),
                            'item_id = %i',
                            $item['id']
                        );
                        
                        // Delete all files
                        $files = DB::query(
                            'SELECT id
                            FROM ' . prefixTable('files') . '
                            WHERE id_item = %i',
                            $folderData[0]
                        );
                        foreach ($files as $file) {
                            DB::delete(
                                prefixTable('sharekeys_files'),
                                'object_id = %i',
                                $file['id']
                            );
                        }
                        DB::delete(
                            prefixTable('files'),
                            'id_item = %i',
                            $item['id']
                        );

                        // delete all sharekeys
                        DB::delete(
                            prefixTable('sharekeys_items'),
                            'object_id = %i',
                            $item['id']
                        );
                    }
                }
            }

            //delete ITEMS
            foreach ($post_items as $itemId) {
                DB::delete(
                    prefixTable('items'),
                    'id = %i',
                    $itemId
                );
                DB::delete(
                    prefixTable('log_items'),
                    'id_item = %i',
                    $itemId
                );     
                
                // Delete all fields
                DB::delete(
                    prefixTable('categories_items'),
                    'item_id = %i',
                    $itemId
                );

                // Delete all sharekey
                DB::delete(
                    prefixTable('sharekeys_items'),
                    'object_id = %i',
                    $itemId
                );

                // Delete sharekey fields
                $itemFields = DB::query(
                    'SELECT id
                    FROM ' . prefixTable('categories_items') . '
                    WHERE item_id = %i',
                    $itemId
                );
                foreach ($itemFields as $field) {
                    DB::delete(
                        prefixTable('sharekeys_fields'),
                        'object_id = %i',
                        $field
                    );
                }

                // Delete sharekey files
                $itemFiles = DB::query(
                    'SELECT id
                    FROM ' . prefixTable('files') . '
                    WHERE id_item = %i',
                    $itemId
                );
                foreach ($itemFiles as $file) {
                    DB::delete(
                        prefixTable('sharekeys_files'),
                        'object_id = %i',
                        $field
                    );
                }
                DB::delete(
                    prefixTable('files'),
                    'id_item = %i',
                    $itemId
                );
            }

            updateCacheTable('reload', NULL);

            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

            /*
        * CASE purging logs
        */
        case 'purge_logs':
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
            $post_log_type = filter_var($dataReceived['dataType'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_date_from = strtotime(filter_var($dataReceived['dateStart'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_date_to = strtotime(filter_var($dataReceived['dateEnd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $post_filter_user = filter_var($dataReceived['filter_user'], FILTER_SANITIZE_NUMBER_INT);
            $post_filter_action = filter_var($dataReceived['filter_action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Check conditions
            if (
                empty($post_date_from) === false
                && empty($post_date_to) === false
                && empty($post_log_type) === false
                && ($session->has('user-admin') && (int) $session->get('user-admin') && null !== $session->get('user-admin') && (int) $session->get('user-admin') === 1)
            ) {
                if ($post_log_type === 'items') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_items') . '
                        WHERE (date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND action = "'.$post_filter_action.'"')
                        . ((int) $post_filter_user === -1 ? '' : ' AND id_user = '.(int) $post_filter_user),
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_items'),
                        '(date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND action = "'.$post_filter_action.'"')
                        . ((int) $post_filter_user === -1 ? '' : ' AND id_user = '.(int) $post_filter_user),
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'connections') {
                    //db::debugmode(true);
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . '
                        WHERE type=%s '
                        . 'AND (date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND action = '.$post_filter_action)
                        . ((int) $post_filter_user === -1 ? '' : ' AND field_1 = '.(int) $post_filter_user),
                        'user_connection',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s '
                        . 'AND (date BETWEEN %i AND %i)'
                        . ($post_filter_action === 'all' ? '' : ' AND action = '.$post_filter_action)
                        . ((int) $post_filter_user === -1 ? '' : ' AND field_1 = '.(int) $post_filter_user),
                        'user_connection',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'errors') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . ' WHERE type=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'error',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND (date BETWEEN %i AND %i)',
                        'error',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'copy') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_items') . ' WHERE action=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'at_copy',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_items'),
                        'action=%s AND (date BETWEEN %i AND %i)',
                        'at_copy',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'admin') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . ' WHERE type=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'admin_action',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND (date BETWEEN %i AND %i)',
                        'admin_action',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'failed') {
                    DB::query(
                        'SELECT * FROM ' . prefixTable('log_system') . ' WHERE type=%s ' .
                            'AND (date BETWEEN %i AND %i)',
                        'failed_auth',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND (date BETWEEN %i AND %i)',
                        'failed_auth',
                        $post_date_from,
                        $post_date_to
                    );
                } else {
                    $counter = 0;
                }

                // send data
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'nb_deleted' => $counter,
                    ),
                    'encode'
                );

                break;
            }

            // send data
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );

            break;

        //CASE delete a task
        case 'task_delete':
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

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about task
            $taskInfo = DB::queryfirstrow(
                'SELECT p.process_type as process_type
                FROM ' . prefixTable('background_tasks') . ' as p
                WHERE p.increment_id = %i',
                $post_id
            );
            if ($taskInfo !== null) {
                // delete task
                DB::query(
                    'DELETE FROM ' . prefixTable('background_subtasks') . '
                    WHERE task_id = %i',
                    $post_id
                );
                DB::query(
                    'DELETE FROM ' . prefixTable('background_tasks') . '
                    WHERE increment_id = %i',
                    $post_id
                );

                // if user creation then update user status
                if ($taskInfo['process_type'] === 'create_user_keys') {
                    DB::update(
                        prefixTable('users'),
                        array(
                            'ongoing_process_id' => NULL,
                        ),
                        'ongoing_process_id = %i',
                        (int) $post_id
                    );
                }     
            }

            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

        //CASE handle crontab job
        case 'handle_crontab_job':
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

            // get PHP binary path
            $phpBinaryPath = getPHPBinary();

            // Instantiate the adapter and repository
            try {
                $crontabRepository = new CrontabRepository(new CrontabAdapter());
                $results = $crontabRepository->findJobByRegex('/Teampass\ scheduler/');
                if (count($results) === 0) {
                    // Add the job
                    $crontabJob = new CrontabJob();
                    $crontabJob
                        ->setMinutes('*')
                        ->setHours('*')
                        ->setDayOfMonth('*')
                        ->setMonths('*')
                        ->setDayOfWeek('*')
                        ->setTaskCommandLine($phpBinaryPath . ' ' . $SETTINGS['cpassman_dir'] . '/sources/scheduler.php')
                        ->setComments('Teampass scheduler');
                    
                    $crontabRepository->addJob($crontabJob);
                    $crontabRepository->persist();
                }
            } catch (Exception $e) {
                // do nothing
                print_r($e);
            }

            // send data
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;
    }
}
