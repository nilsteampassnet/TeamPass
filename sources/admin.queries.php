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
 * @file      admin.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (
    isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'options', $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/tp.config.php';

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';

// connect to the server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_NUMBER_INT);
$post_label = filter_input(INPUT_POST, 'label', FILTER_SANITIZE_STRING);
$post_action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$post_cpt = filter_input(INPUT_POST, 'cpt', FILTER_SANITIZE_NUMBER_INT);
$post_object = filter_input(INPUT_POST, 'object', FILTER_SANITIZE_STRING);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
$post_length = filter_input(INPUT_POST, 'length', FILTER_SANITIZE_NUMBER_INT);
$post_option = filter_input(INPUT_POST, 'option', FILTER_SANITIZE_STRING);
$post_nbItems = filter_input(INPUT_POST, 'nbItems', FILTER_SANITIZE_NUMBER_INT);
$post_counter = filter_input(INPUT_POST, 'counter', FILTER_SANITIZE_NUMBER_INT);
$post_list = filter_input(INPUT_POST, 'list', FILTER_SANITIZE_STRING);

switch ($post_type) {
        //CASE for getting informations about the tool
    case 'cpm_status':
        $text = '<ul>';
        $error = '';
        if (TP_ADMIN_NO_INFO === false) {
            if (isset($SETTINGS['get_tp_info']) && (int) $SETTINGS['get_tp_info'] === 1) {
                // Get info about Teampass
                if (
                    isset($SETTINGS['proxy_ip']) === true && empty($SETTINGS['proxy_ip']) === false &&
                    isset($SETTINGS['proxy_port']) === true && empty($SETTINGS['proxy_port']) === false
                ) {
                    $context = stream_context_create(
                        array(
                            'http' => array(
                                'ignore_errors' => true,
                                'proxy' => $SETTINGS['proxy_ip'] . ':' . $SETTINGS['proxy_port'],
                            ),
                        )
                    );
                } else {
                    $context = stream_context_create(
                        array(
                            'http' => array(
                                'ignore_errors' => true,
                            ),
                        )
                    );
                }

                $json = @file_get_contents('https://teampass.net/utils/teampass_info.json', false, $context);
                if ($json !== false) {
                    $json_array = json_decode($json, true);

                    // About version
                    $text .= '<li><u>' . $LANG['your_version'] . '</u> : ' . TP_VERSION_FULL;
                    if (floatval(TP_VERSION_FULL) < floatval($json_array['info']['version'])) {
                        $text .= '&nbsp;&nbsp;<b>' . $LANG['please_update'] . '</b>';
                    }
                    $text .= '</li>';

                    // Libraries
                    $text .= '<li><u>Libraries</u> :</li>';
                    foreach ($json_array['libraries'] as $key => $val) {
                        $text .= "<li>&nbsp;<span class='fa fa-caret-right'></span>&nbsp;" . $key . " (<a href='" . $val . "' target='_blank'>" . $val . '</a>)</li>';
                    }
                }
            } else {
                $error = 'conf_block';
            }
        } else {
            $error = 'conf_block';
        }
        $text .= '</ul>';

        echo '[{"error":"' . $error . '" , "output":"' . str_replace(array("\n", "\t", "\r"), '', $text) . '"}]';
        break;

        //##########################################################
        //CASE for refreshing all Personal Folders
    case 'admin_action_check_pf':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        //get through all users
        $rows = DB::query(
            'SELECT id, login, email
            FROM ' . prefixTable('users') . '
            ORDER BY login ASC'
        );
        foreach ($rows as $record) {
            //update PF field for user
            DB::update(
                prefixTable('users'),
                array(
                    'personal_folder' => '1',
                ),
                'id = %i',
                $record['id']
            );

            //if folder doesn't exist then create it
            $data = DB::queryfirstrow(
                'SELECT id
                FROM ' . prefixTable('nested_tree') . '
                WHERE title = %s AND parent_id = %i',
                $record['id'],
                0
            );
            $counter = DB::count();
            if ($counter === 0) {
                //If not exist then add it
                DB::insert(
                    prefixTable('nested_tree'),
                    array(
                        'parent_id' => '0',
                        'title' => $record['id'],
                        'personal_folder' => '1',
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
                    $record['id'],
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
        }

        // Log
        logEvents(
            $SETTINGS,
            '$SETTINGS,system',
            'admin_action_check_pf',
            (string) $_SESSION['user_id'],
            $_SESSION['login'],
            'success'
        );

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => langHdl('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success ml-2"></i>',
            ),
            'encode'
        );
        break;

        //##########################################################
        //CASE for deleting all items from DB that are linked to a folder that has been deleted
    case 'admin_action_db_clean_items':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        //Libraries call
        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        //init
        $foldersIds = array();
        $nbItemsDeleted = 0;

        // Get an array of all folders
        $folders = $tree->getDescendants();
        foreach ($folders as $folder) {
            if (!in_array($folder->id, $foldersIds)) {
                array_push($foldersIds, $folder->id);
            }
        }

        $items = DB::query('SELECT id,label FROM ' . prefixTable('items') . ' WHERE id_tree NOT IN %li', $foldersIds);
        foreach ($items as $item) {
            //Delete item
            DB::DELETE(prefixTable('items'), 'id = %i', $item['id']);

            // Delete if template related to item
            DB::delete(
                prefixTable('templates'),
                'item_id = %i',
                $item['id']
            );

            //log
            DB::DELETE(prefixTable('log_items'), 'id_item = %i', $item['id']);

            ++$nbItemsDeleted;
        }

        // delete orphan items
        $rows = DB::query(
            'SELECT id
            FROM ' . prefixTable('items') . '
            ORDER BY id ASC'
        );
        foreach ($rows as $item) {
            DB::query(
                'SELECT * FROM ' . prefixTable('log_items') . ' WHERE id_item = %i AND action = %s',
                $item['id'],
                'at_creation'
            );
            $counter = DB::count();
            if ($counter === 0) {
                DB::DELETE(prefixTable('items'), 'id = %i', $item['id']);
                DB::DELETE(prefixTable('categories_items'), 'item_id = %i', $item['id']);
                DB::DELETE(prefixTable('log_items'), 'id_item = %i', $item['id']);
                ++$nbItemsDeleted;
            }
        }

        //Update CACHE table
        updateCacheTable('reload', $SETTINGS, null);

        // Log
        logEvents(
            $SETTINGS,
            'system',
            'admin_action_db_clean_items',
            (string) $_SESSION['user_id'],
            $_SESSION['login'],
            'success'
        );

        //show some info
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => langHdl('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success ml-2 mr-3"></i>
                    <i class="fas fa-chevron-right mr-2"></i>' .
                    $nbItemsDeleted . ' ' . langHdl('deleted_items'),
            ),
            'encode'
        );
        break;

        //##########################################################
        //CASE for creating a DB backup
    case 'admin_action_db_backup':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
        $return = '';

        //Get all tables
        $tables = array();
        $result = DB::query('SHOW TABLES');
        foreach ($result as $row) {
            $tables[] = $row['Tables_in_' . $database];
        }

        //cycle through
        foreach ($tables as $table) {
            if (defined('DB_PREFIX') || substr_count($table, DB_PREFIX) > 0) {
                // Do query
                $result = DB::queryRaw('SELECT * FROM ' . $table);
                DB::queryRaw(
                    'SELECT *
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE table_schema = %s
                    AND table_name = %s',
                    $database,
                    $table
                );
                $numFields = DB::count();

                // prepare a drop table
                $return .= 'DROP TABLE ' . $table . ';';
                $row2 = DB::queryfirstrow('SHOW CREATE TABLE ' . $table);
                $return .= "\n\n" . $row2['Create Table'] . ";\n\n";

                //prepare all fields and datas
                for ($i = 0; $i < $numFields; ++$i) {
                    while ($row = $result->fetch_row()) {
                        $return .= 'INSERT INTO ' . $table . ' VALUES(';
                        for ($j = 0; $j < $numFields; ++$j) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = preg_replace("/\n/", '\\n', $row[$j]);
                            if (isset($row[$j])) {
                                $return .= '"' . $row[$j] . '"';
                            } else {
                                $return .= 'NULL';
                            }
                            if ($j < ($numFields - 1)) {
                                $return .= ',';
                            }
                        }
                        $return .= ");\n";
                    }
                }
                $return .= "\n\n\n";
            }
        }

        if (!empty($return)) {
            // get a token
            $token = GenerateCryptKey(20, false, true, true, false, true, $SETTINGS);

            //save file
            $filename = time() . '-' . $token . '.sql';
            $handle = fopen($SETTINGS['path_to_files_folder'] . '/' . $filename, 'w+');
            if ($handle !== false) {
                //write file
                fwrite($handle, $return);
                fclose($handle);
            }

            // Encrypt the file
            if (empty($post_option) === false) {
                // Encrypt the file
                prepareFileWithDefuse(
                    'encrypt',
                    $SETTINGS['path_to_files_folder'] . '/' . $filename,
                    $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $filename,
                    $SETTINGS,
                    $post_option
                );

                // Do clean
                unlink($SETTINGS['path_to_files_folder'] . '/' . $filename);
                rename(
                    $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $filename,
                    $SETTINGS['path_to_files_folder'] . '/' . $filename
                );
            }

            //generate 2d key
            $_SESSION['key_tmp'] = GenerateCryptKey(20, false, true, true, false, true, $SETTINGS);

            //update LOG
            logEvents($SETTINGS, 'admin_action', 'dataBase backup', (string) $_SESSION['user_id'], $_SESSION['login']);

            echo '[{"result":"db_backup" , "href":"sources/downloadFile.php?name=' . urlencode($filename) . '&sub=files&file=' . $filename . '&type=sql&key=' . $_SESSION['key'] . '&key_tmp=' . $_SESSION['key_tmp'] . '&pathIsFiles=1"}]';
        }
        break;

        //##########################################################
        //CASE for restoring a DB backup
    case 'admin_action_db_restore':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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
        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        $dataPost = explode('&', $post_option);
        $file = htmlspecialchars($dataPost[0]);
        $key = htmlspecialchars($dataPost[1]);

        // Get filename from database
        $data = DB::queryFirstRow(
            'SELECT valeur
            FROM ' . prefixTable('misc') . '
            WHERE increment_id = %i',
            $file
        );

        $file = $data['valeur'];

        // Delete operation id
        DB::delete(
            prefixTable('misc'),
            'increment_id = %i',
            $file
        );

        // Undecrypt the file
        if (empty($key) === false) {
            // Decrypt the file
            $ret = prepareFileWithDefuse(
                'decrypt',
                $SETTINGS['path_to_files_folder'] . '/' . $file,
                $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $file,
                $SETTINGS,
                $key
            );

            if (empty($ret) === false) {
                echo '[{"result":"db_restore" , "message":"' . $ret . '"}]';
                break;
            }

            // Do clean
            fileDelete($SETTINGS['path_to_files_folder'] . '/' . $file, $SETTINGS);
            $file = $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $file;
        } else {
            $file = $SETTINGS['path_to_files_folder'] . '/' . $file;
        }

        //read sql file
        $handle = fopen($file, 'r');
        $query = '';
        while (!feof($handle)) {
            $query .= fgets($handle, 4096);
            if (substr(rtrim($query), -1) === ';') {
                //launch query
                DB::queryRaw($query);
                $query = '';
            }
        }
        fclose($handle);

        //delete file
        unlink($SETTINGS['path_to_files_folder'] . '/' . $file);

        //Show done
        echo '[{"result":"db_restore" , "message":""}]';
        break;

        //##########################################################
        //CASE for optimizing the DB
    case 'admin_action_db_optimize':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        //Get all tables
        $alltables = DB::query('SHOW TABLES');
        foreach ($alltables as $table) {
            foreach ($table as $i => $tablename) {
                if (substr_count($tablename, DB_PREFIX) > 0) {
                    // launch optimization quieries
                    DB::query('ANALYZE TABLE `' . $tablename . '`');
                    DB::query('OPTIMIZE TABLE `' . $tablename . '`');
                }
            }
        }

        //Clean up LOG_ITEMS table
        $rows = DB::query(
            'SELECT id
            FROM ' . prefixTable('items') . '
            ORDER BY id ASC'
        );
        foreach ($rows as $item) {
            DB::query(
                'SELECT * FROM ' . prefixTable('log_items') . ' WHERE id_item = %i AND action = %s',
                $item['id'],
                'at_creation'
            );
            $counter = DB::count();
            if ($counter === 0) {
                //Create new at_creation entry
                $rowTmp = DB::queryFirstRow(
                    'SELECT date, id_user FROM ' . prefixTable('log_items') . ' WHERE id_item=%i ORDER BY date ASC',
                    $item['id']
                );
                DB::insert(
                    prefixTable('log_items'),
                    array(
                        'id_item' => $item['id'],
                        'date' => $rowTmp['date'] - 1,
                        'id_user' => empty($rowTmp['id_user']) === true ? 1 : $rowTmp['id_user'],
                        'action' => 'at_creation',
                        'raison' => '',
                    )
                );
            }
        }

        // Log
        logEvents(
            $SETTINGS,
            'system',
            'admin_action_db_optimize',
            (string) $_SESSION['user_id'],
            $_SESSION['login'],
            'success'
        );

        //Show done
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => langHdl('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success ml-2"></i>',
            ),
            'encode'
        );
        break;

        //##########################################################
        //CASE for deleted old files in folder "files"
    case 'admin_action_purge_old_files':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === false) {
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

        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        //read folder
        if (is_dir($SETTINGS['path_to_files_folder']) === false) {
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('file_folder_not_accessible') . ": " . $SETTINGS['path_to_files_folder'],
                ),
                'encode'
            );
            break;
        }

        $dir = opendir($SETTINGS['path_to_files_folder']);
        if ($dir !== false) {
            //delete file FILES
            while (false !== ($f = readdir($dir))) {
                if ($f !== '.' && $f !== '..' && $f !== '.htaccess') {
                    if (file_exists($dir . $f) && ((time() - filectime($dir . $f)) > 604800)) {
                        fileDelete($dir . '/' . $f, $SETTINGS);
                    }
                }
            }

            //Close dir
            closedir($dir);
        }

        //read folder  UPLOAD
        if (is_dir($SETTINGS['path_to_upload_folder']) === false) {
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('file_folder_not_accessible') . ": " . $SETTINGS['path_to_upload_folder'],
                ),
                'encode'
            );
            break;
        }
        $dir = opendir($SETTINGS['path_to_upload_folder']);
        $nbItemsDeleted = 0;

        if ($dir !== false) {
            //delete file
            while (false !== ($f = readdir($dir))) {
                if ($f !== '.' && $f !== '..') {
                    if (strpos($f, '_delete.') > 0) {
                        fileDelete($SETTINGS['path_to_upload_folder'] . '/' . $f, $SETTINGS);
                    }
                }
            }
            //Close dir
            closedir($dir);
        }

        // Log
        logEvents(
            $SETTINGS,
            'system',
            'admin_action_purge_old_files',
            (string) $_SESSION['user_id'],
            $_SESSION['login'],
            'success'
        );

        //Show done
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => langHdl('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success ml-2 mr-3"></i>
                    <i class="fas fa-chevron-right mr-2"></i>' .
                    $nbItemsDeleted . ' ' . langHdl('deleted_items'),
            ),
            'encode'
        );
        break;

        /*
    * Reload the Cache table
    */
    case 'admin_action_reload_cache_table':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
        updateCacheTable('reload', $SETTINGS, NULL);

        // Log
        logEvents(
            $SETTINGS,
            'system',
            'admin_action_reload_cache_table',
            (string) $_SESSION['user_id'],
            $_SESSION['login'],
            'success'
        );

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            [
                'error' => false,
                'message' => langHdl('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success mr-2"></i>',
            ],
            'encode'
        );
        break;

        /*
       * REBUILD CONFIG FILE
    */
    case 'admin_action_rebuild_config_file':
        // Check KEY
        if ($post_key !== $_SESSION['key']) {
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                [
                    'error' => true,
                    'message' => langHdl('key_is_not_correct'),
                ],
                'encode'
            );
            break;
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        // Perform
        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
        $ret = handleConfigFile('rebuild', $SETTINGS);

        // Log
        logEvents(
            $SETTINGS,
            'system',
            'admin_action_rebuild_config_file',
            (string) $_SESSION['user_id'],
            $_SESSION['login'],
            $ret === true ? 'success' : $ret
        );

        if ($ret !== true) {
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => $ret,
                ),
                'encode'
            );
            break;
        }

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => langHdl('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success ml-2"></i>',
            ),
            'encode'
        );
        break;

        /*
    * Decrypt a backup file
    */
    case 'admin_action_backup_decrypt':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        // Init
        $msg = '';
        $result = '';
        $filename = $post_option;
        $tp_settings = [];
        //get backups infos
        $rows = DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s', 'admin');
        foreach ($rows as $record) {
            $tp_settings[$record['intitule']] = $record['valeur'];
        }

        // check if backup file is in DB.
        // If YES then it is encrypted with DEFUSE
        $bck = DB::queryFirstRow('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'backup', 'filename');

        //read file
        $return = '';
        $Fnm = $tp_settings['bck_script_path'] . '/' . $filename . '.sql';
        if (file_exists($Fnm)) {
            if (!empty($bck) && $bck['valeur'] === $filename) {
                $err = '';

                // it means that file is DEFUSE encrypted
                include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/Crypto.php';
                include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/DerivedKeys.php';
                include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/KeyOrPassword.php';
                include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/File.php';
                include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/Core.php';

                try {
                    \Defuse\Crypto\File::decryptFileWithPassword(
                        $SETTINGS['bck_script_path'] . '/' . $post_option . '.sql',
                        $SETTINGS['bck_script_path'] . '/' . str_replace('encrypted', 'clear', $filename) . '.sql',
                        base64_decode($SETTINGS['bck_script_key'])
                    );
                } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                    $err = 'An attack! Either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.';
                }

                if (!empty($err)) {
                    echo '[{ "result":"backup_decrypt_fails" , "msg":"' . $err . '"}]';
                    break;
                }
            } else {
                // file is bCrypt encrypted
                $inF = fopen($Fnm, 'r');
                if ($inF !== false) {
                    while (feof($inF) === false) {
                        $return .= fgets($inF, 4096);
                    }
                    fclose($inF);
                }

                $return = Encryption\Crypt\aesctr::decrypt(
                    /* @scrutinizer ignore-type */
                    $return,
                    /* @scrutinizer ignore-type */
                    base64_decode($tp_settings['bck_script_key']),
                    256
                );

                //save the file
                $handle = fopen($tp_settings['bck_script_path'] . '/' . $filename . '.clear.sql', 'w+');
                if ($handle !== false) {
                    fwrite($handle, $return);
                    fclose($handle);
                }
            }
            $result = 'backup_decrypt_success';
            $msg = $tp_settings['bck_script_path'] . '/' . $filename . '.clear.sql';
        } else {
            $result = 'backup_decrypt_fails';
            $msg = 'File not found: ' . $Fnm;
        }
        echo '[{ "result":"' . $result . '" , "msg":"' . $msg . '"}]';
        break;

        /*
    * Change SALT Key START
    */
    case 'admin_action_change_salt_key___start':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        $error = '';
        require_once 'main.functions.php';

        // store old sk
        $_SESSION['reencrypt_old_salt'] = file_get_contents(SECUREPATH . '/teampass-seckey.txt');

        // generate new saltkey
        $old_sk_filename = SECUREPATH . '/teampass-seckey.txt.' . date('Y_m_d', mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y'))) . '.' . time();
        copy(
            SECUREPATH . '/teampass-seckey.txt',
            $old_sk_filename
        );
        $new_key = defuse_generate_key();
        file_put_contents(
            SECUREPATH . '/teampass-seckey.txt',
            $new_key
        );

        // store new sk
        $_SESSION['reencrypt_new_salt'] = file_get_contents(SECUREPATH . '/teampass-seckey.txt');

        //put tool in maintenance.
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => '1',
            ),
            'intitule = %s AND type= %s',
            'maintenance_mode',
            'admin'
        );
        //log
        logEvents($SETTINGS, 'system', 'change_salt_key', (string) $_SESSION['user_id'], $_SESSION['login']);

        // get number of items to change
        DB::query('SELECT id FROM ' . prefixTable('items') . ' WHERE perso = %i', 0);
        $nb_of_items = DB::count();

        // create backup table
        DB::query('DROP TABLE IF EXISTS ' . prefixTable('sk_reencrypt_backup'));
        DB::query(
            'CREATE TABLE `' . prefixTable('sk_reencrypt_backup') . '` (
            `id` int(12) NOT null AUTO_INCREMENT,
            `current_table` varchar(100) NOT NULL,
            `current_field` varchar(500) NOT NULL,
            `value_id` varchar(500) NOT NULL,
            `value` text NOT NULL,
            `value2` varchar(500) NOT NULL,
            `current_sql` text NOT NULL,
            `result` text NOT NULL,
            PRIMARY KEY (`id`)
            ) CHARSET=utf8;'
        );

        // store old SK in backup table
        DB::insert(
            prefixTable('sk_reencrypt_backup'),
            array(
                'current_table' => 'old_sk',
                'current_field' => 'old_sk',
                'value_id' => 'old_sk',
                'value' => $_SESSION['reencrypt_old_salt'],
                'current_sql' => 'old_sk',
                'value2' => $old_sk_filename,
                'result' => 'none',
            )
        );

        // delete previous backup files
        $files = glob($SETTINGS['path_to_upload_folder'] . '/*'); // get all file names
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                $file_parts = pathinfo($file);
                if (strpos($file_parts['filename'], '.bck-change-sk') !== false) {
                    unlink($file); // delete file
                }
            }
        }

        // Send back
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
                'nextAction' => 'encrypt_items',
                'nbOfItems' => $nb_of_items,
            ),
            'encode'
        );
        break;

        /*
    * Change SALT Key - ENCRYPT
    */
    case 'admin_action_change_salt_key___encrypt':
        // Check KEY
        if ($post_key !== $_SESSION['key']) {
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('key_is_not_correct'),
                    'nextAction' => '',
                    'nbOfItems' => '',
                ),
                'encode'
            );
            break;
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        $error = '';
        require_once 'main.functions.php';

        // prepare SK
        if (empty($_SESSION['reencrypt_new_salt']) || empty($_SESSION['reencrypt_old_salt'])) {
            // SK is not correct
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => 'saltkeys are empty???',
                    'nbOfItems' => '',
                    'nextAction' => '',
                ),
                'encode'
            );
            break;
        }

        // what objects to treat
        if (empty($post_object) === true) {
            // no more object to treat
            $nextAction = 'finishing';
        } else {
            // manage list of objects
            $objects = explode(',', $post_object);

            // Allowed values for $_POST['object'] : "items,logs,files,categories"
            if (in_array($objects[0], array('items', 'logs', 'files', 'categories')) === false) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => 'Input `' . $objects[0] . '` is not allowed',
                        'nbOfItems' => '',
                        'nextAction' => '',
                    ),
                    'encode'
                );
                break;
            }

            if ($objects[0] === 'items') {
                //change all encrypted data in Items (passwords)
                $rows = DB::query(
                    'SELECT id, pw, pw_iv
                    FROM ' . prefixTable('items') . '
                    WHERE perso = %s
                    LIMIT ' . $post_start . ', ' . $post_length,
                    '0'
                );
                foreach ($rows as $record) {
                    // backup data
                    DB::insert(
                        prefixTable('sk_reencrypt_backup'),
                        array(
                            'current_table' => 'items',
                            'current_field' => 'pw',
                            'value_id' => $record['id'],
                            'value' => $record['pw'],
                            'current_sql' => 'UPDATE ' . prefixTable('items') . " SET pw = '" . $record['pw'] . "' WHERE id = '" . $record['id'] . "';",
                            'value2' => 'none',
                            'result' => 'none',
                        )
                    );
                    $newID = DB::insertId();

                    $pw = cryption(
                        $record['pw'],
                        $_SESSION['reencrypt_old_salt'],
                        'decrypt',
                        $SETTINGS
                    );
                    //encrypt with new SALT
                    $encrypt = cryption(
                        $pw['string'],
                        $_SESSION['reencrypt_new_salt'],
                        'encrypt',
                        $SETTINGS
                    );

                    //save in DB
                    DB::update(
                        prefixTable('items'),
                        array(
                            'pw' => $encrypt['string'],
                            'pw_iv' => '',
                        ),
                        'id = %i',
                        $record['id']
                    );

                    // update backup table
                    DB::update(
                        prefixTable('sk_reencrypt_backup'),
                        array(
                            'result' => 'ok',
                        ),
                        'id=%i',
                        $newID
                    );
                }
                // ---
                // CASE OF LOGS
                // ---
            } elseif ($objects[0] === 'logs') {
                //change all encrypted data in Logs (passwords)
                $rows = DB::query(
                    'SELECT raison, increment_id
                    FROM ' . prefixTable('log_items') . "
                    WHERE action = %s AND raison LIKE 'at_pw :%'
                    LIMIT " . $post_start . ', ' . $post_length,
                    'at_modification'
                );
                foreach ($rows as $record) {
                    // backup data
                    DB::insert(
                        prefixTable('sk_reencrypt_backup'),
                        array(
                            'current_table' => 'log_items',
                            'current_field' => 'raison',
                            'value_id' => $record['increment_id'],
                            'value' => $record['raison'],
                            'current_sql' => 'UPDATE ' . prefixTable('log_items') . " SET raison = '" . $record['raison'] . "' WHERE increment_id = '" . $record['increment_id'] . "';",
                            'value2' => 'none',
                            'result' => 'none',
                        )
                    );
                    $newID = DB::insertId();

                    // extract the pwd
                    $tmp = explode('at_pw :', $record['raison']);
                    if (!empty($tmp[1])) {
                        $pw = cryption(
                            $tmp[1],
                            $_SESSION['reencrypt_old_salt'],
                            'decrypt',
                            $SETTINGS
                        );
                        //encrypt with new SALT
                        $encrypt = cryption(
                            $pw['string'],
                            $_SESSION['reencrypt_new_salt'],
                            'encrypt',
                            $SETTINGS
                        );

                        // save in DB
                        DB::update(
                            prefixTable('log_items'),
                            array(
                                'raison' => 'at_pw :' . $encrypt['string'],
                                'encryption_type' => 'defuse',
                            ),
                            'increment_id = %i',
                            $record['increment_id']
                        );

                        // update backup table
                        DB::update(
                            prefixTable('sk_reencrypt_backup'),
                            array(
                                'result' => 'ok',
                            ),
                            'id=%i',
                            $newID
                        );
                    }
                }
                // ---
                // CASE OF CATEGORIES
                // ---
            } elseif ($objects[0] === 'categories') {
                //change all encrypted data in CATEGORIES (passwords)
                $rows = DB::query(
                    'SELECT id, data
                    FROM ' . prefixTable('categories_items') . '
                    LIMIT ' . $post_start . ', ' . $post_length
                );
                foreach ($rows as $record) {
                    // backup data
                    DB::insert(
                        prefixTable('sk_reencrypt_backup'),
                        array(
                            'current_table' => 'categories_items',
                            'current_field' => 'data',
                            'value_id' => $record['id'],
                            'value' => $record['data'],
                            'current_sql' => 'UPDATE ' . prefixTable('categories_items') . " SET data = '" . $record['data'] . "' WHERE id = '" . $record['id'] . "';",
                            'value2' => 'none',
                            'result' => 'none',
                        )
                    );
                    $newID = DB::insertId();

                    $pw = cryption(
                        $record['data'],
                        $_SESSION['reencrypt_old_salt'],
                        'decrypt',
                        $SETTINGS
                    );
                    //encrypt with new SALT
                    $encrypt = cryption(
                        $pw['string'],
                        $_SESSION['reencrypt_new_salt'],
                        'encrypt',
                        $SETTINGS
                    );
                    // save in DB
                    DB::update(
                        prefixTable('categories_items'),
                        array(
                            'data' => $encrypt['string'],
                            'encryption_type' => 'defuse',
                        ),
                        'id = %i',
                        $record['id']
                    );

                    // update backup table
                    DB::update(
                        prefixTable('sk_reencrypt_backup'),
                        array(
                            'result' => 'ok',
                        ),
                        'id=%i',
                        $newID
                    );
                }
                // ---
                // CASE OF FILES
                // ---
            } elseif ($objects[0] === 'files') {
                // Change all encrypted data in FILES (passwords)
                $rows = DB::query(
                    'SELECT id, file, status
                    FROM ' . prefixTable('files') . "
                    WHERE status = 'encrypted'
                    LIMIT " . $post_start . ', ' . $post_length
                );
                foreach ($rows as $record) {
                    // backup data
                    DB::insert(
                        prefixTable('sk_reencrypt_backup'),
                        array(
                            'current_table' => 'files',
                            'current_field' => 'file',
                            'value_id' => $record['id'],
                            'value' => $record['file'],
                            'current_sql' => 'no_query',
                            'value2' => 'none',
                            'result' => 'none',
                        )
                    );
                    $newID = DB::insertId();

                    if (file_exists($SETTINGS['path_to_upload_folder'] . '/' . $record['file'])) {
                        // make a copy of file
                        if (!copy(
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'],
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'] . '.copy'
                        )) {
                            $error = 'Copy not possible';
                            exit;
                        } else {
                            // prepare a bck of file (that will not be deleted)
                            $backup_filename = $record['file'] . '.bck-change-sk.' . time();
                            copy(
                                $SETTINGS['path_to_upload_folder'] . '/' . $record['file'],
                                $SETTINGS['path_to_upload_folder'] . '/' . $backup_filename
                            );
                        }

                        // Treat the file
                        // STEP1 - Do decryption
                        prepareFileWithDefuse(
                            'decrypt',
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'],
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'] . '_encrypted',
                            $SETTINGS
                        );

                        // Do cleanup of files
                        unlink($SETTINGS['path_to_upload_folder'] . '/' . $record['file']);

                        // STEP2 - Do encryption
                        prepareFileWithDefuse(
                            'encryp',
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'] . '_encrypted',
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'],
                            $SETTINGS
                        );

                        // Do cleanup of files
                        unlink($SETTINGS['path_to_upload_folder'] . '/' . $record['file'] . '_encrypted');

                        // Update backup table
                        DB::update(
                            prefixTable('sk_reencrypt_backup'),
                            array(
                                'value2' => $backup_filename,
                                'result' => 'ok',
                            ),
                            'id=%i',
                            $newID
                        );
                    }
                }
            }

            $nextStart = intval($post_start) + intval($post_length);

            // check if last item to change has been treated
            if ($nextStart >= intval($post_nbItems)) {
                array_shift($objects);
                $nextAction = implode(',', $objects); // remove first object of the list

                // do some things for new object
                if (isset($objects[0])) {
                    if ($objects[0] === 'logs') {
                        DB::query('SELECT increment_id FROM ' . prefixTable('log_items') . " WHERE action = %s AND raison LIKE 'at_pw :%'", 'at_modification');
                    } elseif ($objects[0] === 'files') {
                        DB::query('SELECT id FROM ' . prefixTable('files'));
                    } elseif ($objects[0] === 'categories') {
                        DB::query('SELECT id FROM ' . prefixTable('categories_items'));
                    } elseif ($objects[0] === 'custfields') {
                        DB::query('SELECT raison FROM ' . prefixTable('log_items') . " WHERE action = %s AND raison LIKE 'at_pw :%'", 'at_modification');
                    }
                    $nb_of_items = DB::count();
                } else {
                    // now finishing
                    $nextAction = 'finishing';
                    $nb_of_items = $error = $nextStart = '';
                }
            } else {
                $nextAction = $post_object;
                $nb_of_items = '';
            }
        }

        // Send back
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
                'nextAction' => $nextAction,
                'nextStart' => $nextStart,
                'nbOfItems' => $nb_of_items,
                'oldsk' => $_SESSION['reencrypt_old_salt'],
                'newsk' => $_SESSION['reencrypt_new_salt'],
            ),
            'encode'
        );
        break;

        /*
    * Change SALT Key - END
    */
    case 'admin_action_change_salt_key___end':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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
        $error = '';

        // quit maintenance mode.
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => '0',
            ),
            'intitule = %s AND type= %s',
            'maintenance_mode',
            'admin'
        );

        // Send back
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
                'nextAction' => 'done',
            ),
            'encode'
        );
        break;

        /*
    * Change SALT Key - Restore BACKUP data
    */
    case 'admin_action_change_salt_key___restore_backup':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        // delete files
        $rows = DB::query(
            'SELECT current_table, value, value2, current_sql
            FROM ' . prefixTable('sk_reencrypt_backup')
        );
        foreach ($rows as $record) {
            if ($record['current_table'] === 'items' || $record['current_table'] === 'logs' || $record['current_table'] === 'categories') {
                // excute query
                DB::query(
                    str_replace("\'", "'", $record['current_sql'])
                );
            } elseif ($record['current_table'] === 'files') {
                // restore backup file
                if (file_exists($SETTINGS['path_to_upload_folder'] . '/' . $record['value'])) {
                    unlink($SETTINGS['path_to_upload_folder'] . '/' . $record['value']);
                    if (file_exists($SETTINGS['path_to_upload_folder'] . '/' . $record['value2'])) {
                        rename(
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['value2'],
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['value']
                        );
                    }
                }
            } elseif ($record['current_table'] === 'old_sk') {
                $previous_saltkey_filename = $record['value2'];
            }
        }

        // restore saltkey file
        if (file_exists($previous_saltkey_filename)) {
            unlink(SECUREPATH . '/teampass-seckey.txt');
            rename(
                $previous_saltkey_filename,
                SECUREPATH . '/teampass-seckey.txt'
            );
        }

        // drop table
        DB::query('DROP TABLE IF EXISTS ' . prefixTable('sk_reencrypt_backup'));

        // Send back
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
    * Change SALT Key - Delete BACKUP data
    */
    case 'admin_action_change_salt_key___delete_backup':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        // delete files
        $rows = DB::query(
            'SELECT value, value2
            FROM ' . prefixTable('sk_reencrypt_backup') . "
            WHERE current_table = 'files'"
        );
        foreach ($rows as $record) {
            if (file_exists($SETTINGS['path_to_upload_folder'] . '/' . $record['value2'])) {
                unlink($SETTINGS['path_to_upload_folder'] . '/' . $record['value2']);
            }
        }

        // drop table
        DB::query('DROP TABLE IF EXISTS ' . prefixTable('sk_reencrypt_backup'));

        echo '[{"status":"done"}]';
        break;

        /*
    * Test the email configuraiton
    */
    case 'admin_email_test_configuration':
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
        }

        // User has an email set?
        if (empty($_SESSION['user_email'])) {
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('no_email_set'),
                ),
                'encode'
            );
            break;
        } else {
            require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

            //send email
            sendEmail(
                langHdl('admin_email_test_subject'),
                langHdl('admin_email_test_body'),
                $_SESSION['user_email'],
                $SETTINGS
            );
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
        break;

        /*
    * Send emails in backlog
    */
    case 'admin_email_send_backlog':
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
        }

        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        $rows = DB::query(
            'SELECT *
            FROM ' . prefixTable('emails') . '
            WHERE status = %s OR status = %s',
            'not_sent',
            ''
        );
        $counter = DB::count();
        $error = false;
        $message = '';

        if ($counter > 0) {
            // Only treat first email
            foreach ($rows as $record) {
                //send email
                $ret = json_decode(
                    sendEmail(
                        $record['subject'],
                        $record['body'],
                        $record['receivers'],
                        $SETTINGS
                    ),
                    true
                );

                if (empty($ret['error']) === false) {
                    //update item_id in files table
                    DB::update(
                        prefixTable('emails'),
                        array(
                            'status' => 'not_sent',
                        ),
                        'timestamp = %s',
                        $record['timestamp']
                    );

                    $error = true;
                    $message = $ret['message'];
                } else {
                    //delete from DB
                    DB::delete(
                        prefixTable('emails'),
                        'timestamp = %s',
                        $record['timestamp']
                    );

                    //update LOG
                    logEvents(
                        $SETTINGS,
                        'admin_action',
                        'Emails backlog',
                        (string) $_SESSION['user_id'],
                        $_SESSION['login']
                    );
                }

                // Exit loop
                break;
            }
        }

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => $error,
                'message' => $message,
                'counter' => $counter,
            ),
            'encode'
        );
        break;

        /*
    * Send emails in backlog
    */
    case 'admin_email_send_backlog_old':
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
        }

        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        $rows = DB::query('SELECT * FROM ' . prefixTable('emails') . ' WHERE status = %s OR status = %s', 'not_sent', '');
        foreach ($rows as $record) {
            //send email
            $ret = json_decode(
                sendEmail(
                    $record['subject'],
                    $record['body'],
                    $record['receivers'],
                    $SETTINGS
                ),
                true
            );

            if (empty($ret['error']) === false) {
                //update item_id in files table
                DB::update(
                    prefixTable('emails'),
                    array(
                        'status' => 'not_sent',
                    ),
                    'timestamp = %s',
                    $record['timestamp']
                );
            } else {
                //delete from DB
                DB::delete(prefixTable('emails'), 'timestamp = %s', $record['timestamp']);
            }
        }

        //update LOG
        logEvents($SETTINGS, 'admin_action', 'Emails backlog', (string) $_SESSION['user_id'], $_SESSION['login']);

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
    * Attachments encryption
    */
    case 'admin_action_attachments_cryption':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        // init
        $filesList = array();

        // get through files
        if (null !== $post_option && empty($post_option) === false) {
            // Loop on files
            $rows = DB::query(
                'SELECT id, file, status
                FROM ' . prefixTable('files')
            );
            foreach ($rows as $record) {
                if (is_file($SETTINGS['path_to_upload_folder'] . '/' . $record['file'])) {
                    $addFile = false;
                    if (($post_option === 'attachments-decrypt' && $record['status'] === 'encrypted')
                        || ($post_option === 'attachments-encrypt' && $record['status'] === 'clear')
                    ) {
                        $addFile = true;
                    }

                    if ($addFile === true) {
                        array_push($filesList, $record['id']);
                    }
                }
            }
        } else {
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('error_not_allowed_to'),
                ),
                'encode'
            );
        }

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
                'list' => $filesList,
                'counter' => 0,
            ),
            'encode'
        );
        break;

        /*
     * Attachments encryption - Treatment in several loops
     */
    case 'admin_action_attachments_cryption_continu':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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

        // Prepare variables
        $post_list = filter_var_array($post_list, FILTER_SANITIZE_STRING);
        $post_counter = filter_var($post_counter, FILTER_SANITIZE_NUMBER_INT);

        include $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        $cpt = 0;
        $continu = true;
        $newFilesList = array();
        $message = '';

        // load PhpEncryption library
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/Crypto.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/Encoding.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/DerivedKeys.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/Key.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/KeyOrPassword.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/File.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/RuntimeTests.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/KeyProtectedByPassword.php';
        include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/Core.php';

        // treat 10 files
        foreach ($post_list as $file) {
            if ($cpt < 5) {
                // Get file name
                $file_info = DB::queryfirstrow(
                    'SELECT file
                    FROM ' . prefixTable('files') . '
                    WHERE id = %i',
                    $file
                );

                // skip file is Coherancey not respected
                if (is_file($SETTINGS['path_to_upload_folder'] . '/' . $file_info['file'])) {
                    // Case where we want to decrypt
                    if ($post_option === 'decrypt') {
                        prepareFileWithDefuse(
                            'decrypt',
                            $SETTINGS['path_to_upload_folder'] . '/' . $file_info['file'],
                            $SETTINGS['path_to_upload_folder'] . '/defuse_temp_' . $file_info['file'],
                            $SETTINGS
                        );
                        // Case where we want to encrypt
                    } elseif ($post_option === 'encrypt') {
                        prepareFileWithDefuse(
                            'encrypt',
                            $SETTINGS['path_to_upload_folder'] . '/' . $file_info['file'],
                            $SETTINGS['path_to_upload_folder'] . '/defuse_temp_' . $file_info['file'],
                            $SETTINGS
                        );
                    }
                    // Do file cleanup
                    fileDelete($SETTINGS['path_to_upload_folder'] . '/' . $file_info['file'], $SETTINGS);
                    rename(
                        $SETTINGS['path_to_upload_folder'] . '/defuse_temp_' . $file_info['file'],
                        $SETTINGS['path_to_upload_folder'] . '/' . $file_info['file']
                    );

                    // store in DB
                    DB::update(
                        prefixTable('files'),
                        array(
                            'status' => $post_option === 'attachments-decrypt' ? 'clear' : 'encrypted',
                        ),
                        'id = %i',
                        $file
                    );

                    ++$cpt;
                }
            } else {
                // build list
                array_push($newFilesList, $file);
            }
        }

        // Should we stop
        if (count($newFilesList) === 0) {
            $continu = false;

            //update LOG
            logEvents(
                $SETTINGS,
                'admin_action',
                'attachments_encryption_changed',
                (string) $_SESSION['user_id'],
                $_SESSION['login'],
                $post_option === 'attachments-decrypt' ? 'clear' : 'encrypted'
            );

            $message = langHdl('last_execution') . ' ' .
                date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                '<i class="fas fa-check text-success ml-2 mr-3"></i>';
        }

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => $message,
                'list' => $newFilesList,
                'counter' => $post_cpt + $cpt,
                'continu' => $continu,
            ),
            'encode'
        );
        break;

        /*
     * API save key
     */
    case 'admin_action_api_save_key':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

        $post_label = isset($dataReceived['label']) === true ? filter_var($dataReceived['label'], FILTER_SANITIZE_STRING) : '';
        $post_action = filter_var($dataReceived['action'], FILTER_SANITIZE_STRING);

        // add new key
        if (null !== $post_action && $post_action === 'add') {
            // Generate KEY
            require_once 'main.functions.php';
            $key = GenerateCryptKey(39, false, true, true, false, true, $SETTINGS);

            // Save in DB
            DB::insert(
                prefixTable('api'),
                array(
                    'id' => null,
                    'type' => 'key',
                    'label' => $post_label,
                    'value' => $key,
                    'timestamp' => time(),
                )
            );

            $post_id = DB::insertId();
            // Update existing key
        } elseif (null !== $post_action && $post_action === 'update') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_STRING);

            DB::update(
                prefixTable('api'),
                array(
                    'label' => $post_label,
                    'timestamp' => time(),
                ),
                'id=%i',
                $post_id
            );
            // Delete existing key
        } elseif (null !== $post_action && $post_action === 'delete') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_STRING);

            DB::query(
                'DELETE FROM ' . prefixTable('api') . ' WHERE id = %i',
                $post_id
            );
        }

        // send data
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
                'keyId' => $post_id,
                'key' => isset($key) === true ? $key : '',
            ),
            'encode'
        );
        break;

        /*
       * API save key
    */
    case 'admin_action_api_save_ip':
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
        }
        // Is admin?
        if ($_SESSION['is_admin'] === true) {
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
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

        $post_action = filter_var($dataReceived['action'], FILTER_SANITIZE_STRING);

        // add new key
        if (null !== $post_action && $post_action === 'add') {
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
            $post_ip = filter_var($dataReceived['ip'], FILTER_SANITIZE_STRING);

            // Store in DB
            DB::insert(
                prefixTable('api'),
                array(
                    'id' => null,
                    'type' => 'ip',
                    'label' => $post_label,
                    'value' => $post_ip,
                    'timestamp' => time(),
                )
            );

            $post_id = DB::insertId();
            // Update existing key
        } elseif (null !== $post_action && $post_action === 'update') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_STRING);
            $post_field = filter_var($dataReceived['field'], FILTER_SANITIZE_STRING);
            $post_value = filter_var($dataReceived['value'], FILTER_SANITIZE_STRING);
            if ($post_field === 'value') {
                $arr = array(
                    'value' => $post_value,
                    'timestamp' => time(),
                );
            } else {
                $arr = array(
                    'label' => $post_value,
                    'timestamp' => time(),
                );
            }
            DB::update(
                prefixTable('api'),
                $arr,
                'id=%i',
                $post_id
            );
            // Delete existing key
        } elseif (null !== $post_action && $post_action === 'delete') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_STRING);
            DB::query('DELETE FROM ' . prefixTable('api') . ' WHERE id=%i', $post_id);
        }

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'message' => '',
                'ipId' => $post_id,
            ),
            'encode'
        );
        break;

    case 'save_api_status':
        // Do query
        DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'api');
        $counter = DB::count();
        if ($counter === 0) {
            DB::insert(
                prefixTable('misc'),
                array(
                    'type' => 'admin',
                    'intitule' => 'api',
                    'valeur' => $post_status,
                )
            );
        } else {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $post_status,
                ),
                'type = %s AND intitule = %s',
                'admin',
                'api'
            );
        }
        $SETTINGS['api'] = $post_status;
        break;

    case 'save_duo_status':
        DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'duo');
        $counter = DB::count();
        if ($counter === 0) {
            DB::insert(
                prefixTable('misc'),
                array(
                    'type' => 'admin',
                    'intitule' => 'duo',
                    'valeur' => $post_status,
                )
            );
        } else {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $post_status,
                ),
                'type = %s AND intitule = %s',
                'admin',
                'duo'
            );
        }
        break;

    case 'save_duo_in_sk_file':
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
        }

        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');
        // Store in DB
        foreach ($dataReceived as $key => $value) {
            DB::query(
                'SELECT * 
                FROM ' . prefixTable('misc') . '
                WHERE type = %s AND intitule = %s',
                'admin',
                $key
            );
            $counter = DB::count();
            if ($counter === 0) {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'admin',
                        'intitule' => $key,
                        'valeur' => filter_var($value, FILTER_SANITIZE_STRING),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => filter_var($value, FILTER_SANITIZE_STRING),
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    $key
                );
            }

            // Refresh settings
            $SETTINGS[$key] = $value;

            // save change in config file
            handleConfigFile('update', $SETTINGS, $key, $value);
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

    case 'save_google_options':
        // Check KEY and rights
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
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            $post_data,
            'decode'
        );

        // Google Authentication
        if (htmlspecialchars_decode($dataReceived['google_authentication']) === 'false') {
            $tmp = 0;
        } else {
            $tmp = 1;
        }
        DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'google_authentication');
        $counter = DB::count();
        if ($counter === 0) {
            DB::insert(
                prefixTable('misc'),
                array(
                    'type' => 'admin',
                    'intitule' => 'google_authentication',
                    'valeur' => $tmp,
                )
            );
        } else {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $tmp,
                ),
                'type = %s AND intitule = %s',
                'admin',
                'google_authentication'
            );
        }
        $SETTINGS['google_authentication'] = htmlspecialchars_decode($dataReceived['google_authentication']);

        // ga_website_name
        if (is_null($dataReceived['ga_website_name']) === false) {
            DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'ga_website_name');
            $counter = DB::count();
            if ($counter === 0) {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'admin',
                        'intitule' => 'ga_website_name',
                        'valeur' => htmlspecialchars_decode($dataReceived['ga_website_name']),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['ga_website_name']),
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'ga_website_name'
                );
            }
            $SETTINGS['ga_website_name'] = htmlspecialchars_decode($dataReceived['ga_website_name']);

            // save change in config file
            handleConfigFile('update', $SETTINGS, 'ga_website_name', $SETTINGS['ga_website_name']);
        } else {
            $SETTINGS['ga_website_name'] = '';
        }

        // send data
        echo '[{"result" : "' . addslashes($LANG['done']) . '" , "error" : ""}]';
        break;

    case 'save_agses_options':
        // Check KEY and rights
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
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            $post_data,
            'decode'
        );

        // agses_hosted_url
        if (!is_null($dataReceived['agses_hosted_url'])) {
            DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'agses_hosted_url');
            $counter = DB::count();
            if ($counter === 0) {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'admin',
                        'intitule' => 'agses_hosted_url',
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_url']),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_url']),
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'agses_hosted_url'
                );
            }
            $SETTINGS['agses_hosted_url'] = htmlspecialchars_decode($dataReceived['agses_hosted_url']);
        } else {
            $SETTINGS['agses_hosted_url'] = '';
        }

        // agses_hosted_id
        if (!is_null($dataReceived['agses_hosted_id'])) {
            DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'agses_hosted_id');
            $counter = DB::count();
            if ($counter === 0) {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'admin',
                        'intitule' => 'agses_hosted_id',
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_id']),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_id']),
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'agses_hosted_id'
                );
            }
            $SETTINGS['agses_hosted_id'] = htmlspecialchars_decode($dataReceived['agses_hosted_id']);
        } else {
            $SETTINGS['agses_hosted_id'] = '';
        }

        // agses_hosted_apikey
        if (!is_null($dataReceived['agses_hosted_apikey'])) {
            DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'agses_hosted_apikey');
            $counter = DB::count();
            if ($counter === 0) {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'admin',
                        'intitule' => 'agses_hosted_apikey',
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_apikey']),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_apikey']),
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'agses_hosted_apikey'
                );
            }
            $SETTINGS['agses_hosted_apikey'] = htmlspecialchars_decode($dataReceived['agses_hosted_apikey']);
        } else {
            $SETTINGS['agses_hosted_apikey'] = '';
        }

        // send data
        echo '[{"result" : "' . addslashes($LANG['done']) . '" , "error" : ""}]';
        break;

    case 'save_option_change':
        // Check KEY and rights
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
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            $post_data,
            'decode'
        );
        $type = 'admin';

        require_once 'main.functions.php';

        // In case of key, then encrypt it
        if ($dataReceived['field'] === 'bck_script_passkey') {
            $dataReceived['value'] = cryption(
                $dataReceived['value'],
                '',
                'encrypt',
                $SETTINGS
            )['string'];
        }

        // Check if setting is already in DB. If NO then insert, if YES then update.
        $data = DB::query(
            'SELECT * FROM ' . prefixTable('misc') . '
            WHERE type = %s AND intitule = %s',
            $type,
            $dataReceived['field']
        );
        $counter = DB::count();
        if ($counter === 0) {
            DB::insert(
                prefixTable('misc'),
                array(
                    'valeur' => $dataReceived['value'],
                    'type' => $type,
                    'intitule' => $dataReceived['field'],
                )
            );
            // in case of stats enabled, add the actual time
            if ($dataReceived['field'] === 'send_stats') {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'valeur' => time(),
                        'type' => $type,
                        'intitule' => $dataReceived['field'] . '_time',
                    )
                );
            }
        } else {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $dataReceived['value'],
                ),
                'type = %s AND intitule = %s',
                $type,
                $dataReceived['field']
            );
            // in case of stats enabled, update the actual time
            if ($dataReceived['field'] === 'send_stats') {
                // Check if previous time exists, if not them insert this value in DB
                DB::query(
                    'SELECT * FROM ' . prefixTable('misc') . '
                    WHERE type = %s AND intitule = %s',
                    $type,
                    $dataReceived['field'] . '_time'
                );
                $counter = DB::count();
                if ($counter === 0) {
                    DB::insert(
                        prefixTable('misc'),
                        array(
                            'valeur' => 0,
                            'type' => $type,
                            'intitule' => $dataReceived['field'] . '_time',
                        )
                    );
                } else {
                    DB::update(
                        prefixTable('misc'),
                        array(
                            'valeur' => 0,
                        ),
                        'type = %s AND intitule = %s',
                        $type,
                        $dataReceived['field']
                    );
                }
            }
        }

        // special Cases
        if ($dataReceived['field'] === 'cpassman_url') {
            // update also jsUrl for CSFP protection
            $jsUrl = $dataReceived['value'] . '/includes/libraries/csrfp/js/csrfprotector.js';
            $csrfp_file = '../includes/libraries/csrfp/libs/csrfp.config.php';
            $data = file_get_contents($csrfp_file);
            $posJsUrl = strpos($data, '"jsUrl" => "');
            $posEndLine = strpos($data, '",', $posJsUrl);
            $line = substr($data, $posJsUrl, ($posEndLine - $posJsUrl + 2));
            $newdata = str_replace($line, '"jsUrl" => "' . filter_var($jsUrl, FILTER_SANITIZE_STRING) . '",', $data);
            file_put_contents($csrfp_file, $newdata);
        } elseif ($dataReceived['field'] === 'restricted_to_input' && $dataReceived['value'] === '0') {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => 0,
                ),
                'type = %s AND intitule = %s',
                $type,
                'restricted_to_roles'
            );
        }

        // store in SESSION
        $SETTINGS[$dataReceived['field']] = $dataReceived['value'];

        // save change in config file
        handleConfigFile('rebuild', $SETTINGS, $dataReceived['field'], $dataReceived['value']);

        // Encrypt data to return
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            array(
                'error' => false,
                'misc' => $counter . ' ; ' . $SETTINGS[$dataReceived['field']],
            ),
            'encode'
        );
        break;

    case 'get_values_for_statistics':
        // Check KEY and rights
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
        }

        // Encrypt data to return
        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
            getStatisticsData($SETTINGS),
            'encode'
        );

        break;

    case 'save_sending_statistics':
        // Check KEY and rights
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
        }

        // send statistics
        if (null !== $post_status) {
            DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'send_stats');
            $counter = DB::count();
            if ($counter === 0) {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'admin',
                        'intitule' => 'send_stats',
                        'valeur' => $post_status,
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => $post_status,
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'send_stats'
                );
            }
            $SETTINGS['send_stats'] = $post_status;
        } else {
            $SETTINGS['send_stats'] = '0';
        }

        // save change in config file
        handleConfigFile('update', $SETTINGS, 'send_stats', $SETTINGS['send_stats']);

        // send statistics items
        if (null !== $post_list) {
            DB::query('SELECT * FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s', 'admin', 'send_statistics_items');
            $counter = DB::count();
            if ($counter === 0) {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'admin',
                        'intitule' => 'send_statistics_items',
                        'valeur' => $post_list,
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => $post_list,
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'send_statistics_items'
                );
            }
            $SETTINGS['send_statistics_items'] = $post_list;
        } else {
            $SETTINGS['send_statistics_items'] = '';
        }

        // save change in config file
        handleConfigFile('update', $SETTINGS, 'send_statistics_items', $SETTINGS['send_statistics_items']);

        // send data
        echo '[{"error" : false}]';
        break;

    case 'is_backup_table_existing':
        // Check KEY and rights
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
        }

        if (DB::query("SHOW TABLES LIKE '" . prefixTable('sk_reencrypt_backup') . "'")) {
            if (DB::count() === 1) {
                echo 1;
            } else {
                echo 0;
            }
        } else {
            echo 0;
        }

        break;

    case 'get_list_of_roles':
        // Check KEY and rights
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
        }

        $json = array();
        array_push(
            $json,
            array(
                'id' => 0,
                'title' => langHdl('god'),
                'selected_administrated_by' => isset($SETTINGS['ldap_new_user_is_administrated_by']) && $SETTINGS['ldap_new_user_is_administrated_by'] === '0' ? 1 : 0,
                'selected_role' => isset($SETTINGS['ldap_new_user_role']) && $SETTINGS['ldap_new_user_role'] === '0' ? 1 : 0,
            )
        );

        $rows = DB::query(
            'SELECT id, title
                FROM ' . prefixTable('roles_title') . '
                ORDER BY title ASC'
        );
        foreach ($rows as $record) {
            array_push(
                $json,
                array(
                    'id' => $record['id'],
                    'title' => addslashes($record['title']),
                    'selected_administrated_by' => isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && $SETTINGS['ldap_new_user_is_administrated_by'] === $record['id'] ? 1 : 0,
                    'selected_role' => isset($SETTINGS['ldap_new_user_role']) === true && $SETTINGS['ldap_new_user_role'] === $record['id'] ? 1 : 0,
                )
            );
        }

        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],$json, 'encode');

        break;
}
