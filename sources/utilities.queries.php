<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @version   3.0.7
 * @file      utilities.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;

require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'utilities.database', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

/*
 * Define Timezone
**/
if (isset($SETTINGS['timezone']) === true) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user']['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';

//Connect to DB
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
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
                $SETTINGS['cpassman_dir'],
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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $SETTINGS['cpassman_dir'],
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
                            $_SESSION['user_id'],
                            'at_restored',
                            $_SESSION['login']
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
                    $_SESSION['user_id'],
                    'at_restored',
                    $_SESSION['login']
                );
            }

            updateCacheTable('reload', $SETTINGS, null);

            // send data
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $SETTINGS['cpassman_dir'],
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
                    // Delete folder
                    DB::delete(
                        prefixTable('nested_tree'),
                        'id = %s',
                        $folderData[0]
                    );

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
                        DB::delete(
                            prefixTable('log_items'),
                            'id_item = %i',
                            $item['id']
                        );
                    }
                }
            }

            //restore ITEMS
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
            }

            updateCacheTable('reload', $SETTINGS, NULL);

            // send data
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $SETTINGS['cpassman_dir'],
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
                && (isset($_SESSION['user_admin']) && (int) $_SESSION['user_admin'] === 1)
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
                    $SETTINGS['cpassman_dir'],
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
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('error_not_allowed_to'),
                ),
                'encode'
            );

            break;

        //CASE show process detail
        case 'show_process_detail':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            $tasks = DB::query(
                'SELECT *
                FROM ' . prefixTable('processes_tasks') . '
                WHERE process_id = %i',
                $post_id
            );

            // Get some values
            DB::query(
                'SELECT id
                FROM ' . prefixTable('items') . '
                WHERE perso = 0'
            );
            $items_number = DB::count();

            DB::query(
                'SELECT increment_id
                FROM ' . prefixTable('log_items') . '
                WHERE raison LIKE "at_pw :%" AND encryption_type = "teampass_aes"'
            );
            $logs_number = DB::count();

            DB::query(
                'SELECT id
                FROM ' . prefixTable('categories_items') . '
                WHERE encryption_type = "teampass_aes"'
            );
            $items_categories= DB::count();

            DB::query(
                'SELECT id
                FROM ' . prefixTable('suggestion')
            );
            $items_suggestions= DB::count();

            DB::query(
                'SELECT id
                FROM ' . prefixTable('files') . '
                WHERE status = "' . TP_ENCRYPTION_NAME . '"'
            );
            $items_files= DB::count();


            // get list
            $ret = [];
            $i = 0;
            foreach ($tasks as $task) {
                // get task detail
                $detail = json_decode($task['task'], true);

                // prepare progress information
                if (isset($detail['step']) === true) {
                    if ($detail['step'] === 'step0' || (int) $detail['index'] === 0) {
                        $task_progress = '0%';
                    } elseif ($detail['step'] === 'step1') {
                        $task_progress = pourcentage($detail['index'], $items_number, 100) .'%';
                    } elseif ($detail['step'] === 'step2') {
                        $task_progress = pourcentage($detail['index'], $logs_number, 100) .'%';
                    } elseif ($detail['step'] === 'step3') {
                        $task_progress = pourcentage($detail['index'], $items_categories, 100) .'%';
                    } elseif ($detail['step'] === 'step4') {
                        $task_progress = pourcentage($detail['index'], $items_suggestions, 100) .'%';
                    } elseif ($detail['step'] === 'step5') {
                        $task_progress = pourcentage($detail['index'], $items_files, 100) .'%';
                    } elseif ($detail['step'] === 'step6') {
                        $task_progress = pourcentage($detail['index'], $items_number, 100) .'%';
                    }
                }

                array_push(
                    $ret,
                    [
                        'created_at' => date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $task['created_at']),
                        'updated_at' => is_null($task['updated_at']) === false ? date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $task['updated_at']) : '',
                        'finished_at' => is_null($task['finished_at']) === false ? date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $task['finished_at']) : '',
                        'progress' => $task['finished_at'] !== null ? '100%' : $task_progress,
                        'is_in_progress' => (int) $task['is_in_progress'],
                        'step' => 'step'.$i,
                    ]
                );

                $i++;
            }

            // send data
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'tasks' => $ret,
                ),
                'encode'
            );
            break;

        //CASE delete a task
        case 'task_delete':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            DB::query(
                'DELETE FROM ' . prefixTable('processes_tasks') . '
                WHERE process_id = %i',
                $post_id
            );

            DB::query(
                'DELETE FROM ' . prefixTable('processes') . '
                WHERE increment_id = %i',
                $post_id
            );

            // send data
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabAdapter.php';
            require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabJob.php';
            require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabRepository.php';

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
                        ->setTaskCommandLine('php ' . $SETTINGS['cpassman_dir'] . '/sources/scheduler.php')
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
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;
    }
}
