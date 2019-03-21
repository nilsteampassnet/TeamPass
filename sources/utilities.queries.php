<?php
/**
 * @author        Nils Laumaillé <nils@teampass.net>
 *
 * @version       2.1.27
 *
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 *
 * @see          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
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
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'utilities.database', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
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

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';

//Connect to DB
include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
//$link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWD_CLEAR, DB_NAME, DB_PORT);
//$link->set_charset(DB_ENCODING);

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_freq = filter_input(INPUT_POST, 'freq', FILTER_SANITIZE_NUMBER_INT);
$post_ids = filter_input(INPUT_POST, 'ids', FILTER_SANITIZE_STRING);
$post_salt_key = filter_input(INPUT_POST, 'salt_key', FILTER_SANITIZE_STRING);
$post_current_id = filter_input(INPUT_POST, 'currentId', FILTER_SANITIZE_NUMBER_INT);
$post_data_to_share = filter_input(INPUT_POST, 'data_to_share', FILTER_SANITIZE_STRING);
$post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

// Construction de la requ?te en fonction du type de valeur
if (null !== $post_type) {
    switch ($post_type) {
        //CASE list of recycled elements
        case 'recycled_bin_elements':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
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
                FROM '.prefixTable('misc').'
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
            $rows = DB::query(
                'SELECT u.login as login, u.name as name, u.lastname as lastname,
                i.id as id, i.label as label,
                i.id_tree as id_tree, l.date as date, n.title as folder_title
                FROM '.prefixTable('log_items').' as l
                INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
                INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
                INNER JOIN '.prefixTable('nested_tree').' as n ON (i.id_tree=n.id)
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
                            'date' => date($SETTINGS['date_format'], $record['date']),
                            'login' => $record['login'],
                            'name' => $record['name'].' '.$record['lastname'],
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

        //CASE list of recycled elements
        case 'restore_selected_objects':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $post_folders = filter_var_array($dataReceived['folders'], FILTER_SANITIZE_STRING);
            $post_items = filter_var_array($dataReceived['items'], FILTER_SANITIZE_STRING);

            // Folders restore
            foreach ($post_folders as $folderId) {
                $data = DB::queryfirstrow(
                    'SELECT valeur
                    FROM '.prefixTable('misc')."
                    WHERE type = 'folder_deleted'
                    AND intitule = %s",
                    'f'.$folderId
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
                        )
                    );

                    //delete log
                    DB::delete(
                        prefixTable('misc'),
                        'type = %s AND intitule = %s',
                        'folder_deleted',
                        'f'.$folderId
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
                        FROM '.prefixTable('items').'
                        WHERE id_tree = %i',
                        $folderId
                    );

                    // log
                    foreach ($items as $item) {
                        logItems(
                            $SETTINGS,
                            $item['id'],
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
                    $itemId,
                    '',
                    $_SESSION['user_id'],
                    'at_restored',
                    $_SESSION['login']
                );
            }

            updateCacheTable('reload', $SETTINGS, '');

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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $post_log_type = filter_var($dataReceived['dataType'], FILTER_SANITIZE_STRING);
            $post_date_from = strtotime(
                date_format(
                    date_create_from_format($SETTINGS['date_format'], filter_var($dataReceived['dateStart'], FILTER_SANITIZE_STRING)),
                    'Y-m-d'
                )
            );
            $post_date_to = strtotime(
                date_format(
                    date_create_from_format($SETTINGS['date_format'], filter_var($dataReceived['dateEnd'], FILTER_SANITIZE_STRING)),
                    'Y-m-d'
                )
            );

            // Check conditions
            if (empty($post_date_from) === false
                && empty($post_date_to) === false
                && empty($post_log_type) === false
                && (isset($_SESSION['user_admin']) && (int) $_SESSION['user_admin'] === 1)
            ) {
                if ($post_log_type === 'items') {
                    DB::query(
                        'SELECT * FROM '.prefixTable('log_items').' WHERE action=%s '.
                        'AND date BETWEEN %i AND %i',
                        'at_shown',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_items'),
                        'action=%s AND date BETWEEN %i AND %i',
                        'at_shown',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'connections') {
                    DB::query(
                        'SELECT * FROM '.prefixTable('log_system').' WHERE type=%s '.
                        'AND date BETWEEN %i AND %i',
                        'user_connection',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND date BETWEEN %i AND %i',
                        'user_connection',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'errors') {
                    DB::query(
                        'SELECT * FROM '.prefixTable('log_system').' WHERE type=%s '.
                        'AND date BETWEEN %i AND %i',
                        'error',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND date BETWEEN %i AND %i',
                        'error',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'copy') {
                    DB::query(
                        'SELECT * FROM '.prefixTable('log_items').' WHERE action=%s '.
                        'AND date BETWEEN %i AND %i',
                        'at_copy',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_items'),
                        'action=%s AND date BETWEEN %i AND %i',
                        'at_copy',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'admin') {
                    DB::query(
                        'SELECT * FROM '.prefixTable('log_system').' WHERE type=%s '.
                        'AND date BETWEEN %i AND %i',
                        'admin_action',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND date BETWEEN %i AND %i',
                        'admin_action',
                        $post_date_from,
                        $post_date_to
                    );
                } elseif ($post_log_type === 'failed') {
                    DB::query(
                        'SELECT * FROM '.prefixTable('log_system').' WHERE type=%s '.
                        'AND date BETWEEN %i AND %i',
                        'failed_auth',
                        $post_date_from,
                        $post_date_to
                    );
                    $counter = DB::count();
                    // Delete
                    DB::delete(
                        prefixTable('log_system'),
                        'type=%s AND date BETWEEN %i AND %i',
                        'failed_auth',
                        $post_date_from,
                        $post_date_to
                    );
                } else {
                    $counter = 0;
                }

                echo '[{"status" : "ok", "nb":"'.$counter.'"}]';
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
                    'message' => langHdl('error_not_allowed_to'),
                ),
                'encode'
            );

            break;
    }
}
