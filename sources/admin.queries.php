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
 * @file      admin.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;
use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;
use TeampassClasses\EmailService\EmailSettings;
use TeampassClasses\EmailService\EmailService;

// Load functions
require_once 'main.functions.php';
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
loadClasses('DB');
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('admin') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

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
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_NUMBER_INT);
$post_label = filter_input(INPUT_POST, 'label', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_cpt = filter_input(INPUT_POST, 'cpt', FILTER_SANITIZE_NUMBER_INT);
$post_object = filter_input(INPUT_POST, 'object', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
$post_length = filter_input(INPUT_POST, 'length', FILTER_SANITIZE_NUMBER_INT);
$post_option = filter_input(INPUT_POST, 'option', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_nbItems = filter_input(INPUT_POST, 'nbItems', FILTER_SANITIZE_NUMBER_INT);
$post_counter = filter_input(INPUT_POST, 'counter', FILTER_SANITIZE_NUMBER_INT);
$post_list = filter_input(INPUT_POST, 'list', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

switch ($post_type) {
    //##########################################################
    //CASE for creating a DB backup
    case 'admin_action_db_backup':
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
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
            $tables[] = $row['Tables_in_' . DB_NAME];
        }

        //cycle through
        foreach ($tables as $table) {
            if (defined('DB_PREFIX') || substr_count($table, DB_PREFIX) > 0) {
                $table = (is_string($table) ? $table : strval($table));
                // Do query
                $result = DB::query('SELECT * FROM ' . $table);
                DB::query(
                    'SELECT *
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE table_schema = %s
                    AND table_name = %s',
                    DB_NAME,
                    $table
                );
                $numFields = DB::count();

                // prepare a drop table
                $return .= 'DROP TABLE ' . $table . ';';
                $row2 = DB::queryFirstRow('SHOW CREATE TABLE ' . $table);
                $return .= "\n\n" . strval($row2['Create Table']) . ";\n\n";

                //prepare all fields and datas
                for ($i = 0; $i < $numFields; ++$i) {
                    if (is_object($result)) {
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
                }
                $return .= "\n\n\n";
            }
        }

        if (!empty($return)) {
            // get a token
            $token = GenerateCryptKey(20, false, true, true, false, true);

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
            $session->set('user-key_tmp', GenerateCryptKey(20, false, true, true, false, true));

            //update LOG
            logEvents($SETTINGS, 'admin_action', 'dataBase backup', (string) $session->get('user-id'), $session->get('user-login'));

            echo '[{"result":"db_backup" , "href":"sources/downloadFile.php?name=' . urlencode($filename) . '&sub=files&file=' . $filename . '&type=sql&key=' . $session->get('key') . '&key_tmp=' . $session->get('user-key_tmp') . '&pathIsFiles=1"}]';
        }
        break;

        //##########################################################
        //CASE for restoring a DB backup
    case 'admin_action_db_restore':
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
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

        $file = is_string($data['valeur']) ? $data['valeur'] : '';

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
                $key
            );

            if (empty($ret) === false) {
                // deepcode ignore ServerLeak: $ret can only be an error string, so no important data here to be sent to client
                echo '[{"result":"db_restore" , "message":"An error occurred ('.(string) $ret.')"}]';
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
                DB::query($query);
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        //Get all tables
        $alltables = DB::query('SHOW TABLES');
        foreach ($alltables as $table) {
            foreach ($table as $i => $tablename) {
                $tablename = (is_string($tablename) ? $tablename : strval($tablename));
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
            (string) $session->get('user-id'),
            $session->get('user-login'),
            'success'
        );

        //Show done
        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => $lang->get('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success ml-2"></i>',
            ),
            'encode'
        );
        break;

        

        /*
    * Reload the Cache table
    */
    case 'admin_action_reload_cache_table':
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
        updateCacheTable('reload', NULL);

        // Log
        logEvents(
            $SETTINGS,
            'system',
            'admin_action_reload_cache_table',
            (string) $session->get('user-id'),
            $session->get('user-login'),
            'success'
        );

        echo prepareExchangedData(
            [
                'error' => false,
                'message' => $lang->get('last_execution') . ' ' .
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                    '<i class="fas fa-check text-success mr-2"></i>',
            ],
            'encode'
        );
        break;


    /*
    * Change SALT Key START
    */
    case 'admin_action_change_salt_key___start':
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        $error = '';
        require_once 'main.functions.php';

        // store old sk
        $session->set('user-reencrypt_old_salt', file_get_contents(SECUREPATH.'/'.SECUREFILE));

        // generate new saltkey
        $old_sk_filename = SECUREPATH.'/'.SECUREFILE . date('Y_m_d', mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y'))) . '.' . time();
        copy(
            SECUREPATH.'/'.SECUREFILE,
            $old_sk_filename
        );
        $new_key = defuse_generate_key();
        file_put_contents(
            SECUREPATH.'/'.SECUREFILE,
            $new_key
        );

        // store new sk
        $session->set('user-reencrypt_new_salt', file_get_contents(SECUREPATH.'/'.SECUREFILE));

        //put tool in maintenance.
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => '1',
                'updated_at' => time(),
            ),
            'intitule = %s AND type= %s',
            'maintenance_mode',
            'admin'
        );
        //log
        logEvents($SETTINGS, 'system', 'change_salt_key', (string) $session->get('user-id'), $session->get('user-login'));

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
                'value' => $session->get('user-reencrypt_old_salt'),
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
        if ($post_key !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                    'nextAction' => '',
                    'nbOfItems' => '',
                ),
                'encode'
            );
            break;
        }
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        $error = '';
        require_once 'main.functions.php';

        // prepare SK
        if (empty($session->get('user-reencrypt_new_salt')) === true || empty($session->get('user-reencrypt_old_salt')) === true) {
            // SK is not correct
            echo prepareExchangedData(
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

        // Do init
        $nb_of_items = 0;
        $error = $nextStart = '';


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
                        $session->get('user-reencrypt_old_salt'),
                        'decrypt',
                        $SETTINGS
                    );
                    //encrypt with new SALT
                    $encrypt = cryption(
                        $pw['string'],
                        $session->get('user-reencrypt_new_salt'),
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
                            $session->get('user-reencrypt_old_salt'),
                            'decrypt',
                            $SETTINGS
                        );
                        //encrypt with new SALT
                        $encrypt = cryption(
                            $pw['string'],
                            $session->get('user-reencrypt_new_salt'),
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
                        $session->get('user-reencrypt_old_salt'),
                        'decrypt',
                        $SETTINGS
                    );
                    //encrypt with new SALT
                    $encrypt = cryption(
                        $pw['string'],
                        $session->get('user-reencrypt_new_salt'),
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
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'] . '_encrypted'
                        );

                        // Do cleanup of files
                        unlink($SETTINGS['path_to_upload_folder'] . '/' . $record['file']);

                        // STEP2 - Do encryption
                        prepareFileWithDefuse(
                            'encryp',
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file'] . '_encrypted',
                            $SETTINGS['path_to_upload_folder'] . '/' . $record['file']
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
                }
            } else {
                $nextAction = $post_object;
                $nb_of_items = 0;
            }
        }

        // Send back
        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'nextAction' => $nextAction,
                'nextStart' => (int) $nextStart,
                'nbOfItems' => $nb_of_items,
                'oldsk' => $session->get('user-reencrypt_old_salt'),
                'newsk' => $session->get('user-reencrypt_new_salt'),
            ),
            'encode'
        );
        break;

        /*
    * Change SALT Key - END
    */
    case 'admin_action_change_salt_key___end':
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
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
                'updated_at' => time(),
            ),
            'intitule = %s AND type= %s',
            'maintenance_mode',
            'admin'
        );

        // Send back
        echo prepareExchangedData(
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // delete files
        $previous_saltkey_filename = '';
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
            unlink(SECUREPATH.'/'.SECUREFILE);
            rename(
                $previous_saltkey_filename,
                SECUREPATH.'/'.SECUREFILE
            );
        }

        // drop table
        DB::query('DROP TABLE IF EXISTS ' . prefixTable('sk_reencrypt_backup'));

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
    * Change SALT Key - Delete BACKUP data
    */
    case 'admin_action_change_salt_key___delete_backup':
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
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

        // User has an email set?
        if (empty($session->get('user-email')) === true) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('no_email_set'),
                ),
                'encode'
            );
        } else {
            require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

            //send email
            $emailSettings = new EmailSettings($SETTINGS);
            $emailService = new EmailService();
            $emailService->sendMail(
                $lang->get('admin_email_test_subject'),
                $lang->get('admin_email_test_body'),
                $session->get('user-email'),
                $emailSettings
            );
            
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
        }
        break;

        /*
    * Send emails in backlog
    */
    case 'admin_email_send_backlog':
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

        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
        $emailSettings = new EmailSettings($SETTINGS);
        $emailService = new EmailService();

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
                $email = $emailService->sendMail(
                    $record['subject'],
                    $record['body'],
                    $record['receivers'],
                    $emailSettings
                );
                $ret = json_decode(
                    $email,
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
                        (string) $session->get('user-id'),
                        $session->get('user-login')
                    );
                }

                // Exit loop
                break;
            }
        }

        echo prepareExchangedData(
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

        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        // Instatiate email settings and service
        $emailSettings = new EmailSettings($SETTINGS);
        $emailService = new EmailService();

        $rows = DB::query('SELECT * FROM ' . prefixTable('emails') . ' WHERE status = %s OR status = %s', 'not_sent', '');
        foreach ($rows as $record) {
            //send email
            $email = $emailService->sendMail(
                $record['subject'],
                $record['body'],
                $record['receivers'],
                $emailSettings
            );
            $ret = json_decode(
                $email,
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
        logEvents($SETTINGS, 'admin_action', 'Emails backlog', (string) $session->get('user-id'), $session->get('user-login'));

        echo prepareExchangedData(
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
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
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
        }

        echo prepareExchangedData(
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
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
        $post_list = filter_var_array($post_list, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_counter = filter_var($post_counter, FILTER_SANITIZE_NUMBER_INT);

        include $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
        include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

        $cpt = 0;
        $continu = true;
        $newFilesList = array();
        $message = '';

        // treat 10 files
        foreach ($post_list as $file) {
            if ($cpt < 5) {
                // Get file name
                $file_info = DB::queryFirstRow(
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
                        );
                        // Case where we want to encrypt
                    } elseif ($post_option === 'encrypt') {
                        prepareFileWithDefuse(
                            'encrypt',
                            $SETTINGS['path_to_upload_folder'] . '/' . $file_info['file'],
                            $SETTINGS['path_to_upload_folder'] . '/defuse_temp_' . $file_info['file'],
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
                (string) $session->get('user-id'),
                $session->get('user-login'),
                $post_option === 'attachments-decrypt' ? 'clear' : 'encrypted'
            );

            $message = $lang->get('last_execution') . ' ' .
                date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) time()) .
                '<i class="fas fa-check text-success ml-2 mr-3"></i>';
        }

        echo prepareExchangedData(
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
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

        $post_label = isset($dataReceived['label']) === true ? filter_var($dataReceived['label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
        $post_action = filter_var($dataReceived['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $timestamp = time();

        // add new key
        if (null !== $post_action && $post_action === 'add') {
            // Generate KEY
            require_once 'main.functions.php';
            $key = GenerateCryptKey(39, false, true, true, false, true);

            // Generate objectKey
            //$object = doDataEncryption($key, SECUREFILE.':'.$timestamp);
            
            // Save in DB
            DB::insert(
                prefixTable('api'),
                array(
                    'increment_id' => null,
                    'type' => 'key',
                    'label' => $post_label,
                    'value' => $key, //$object['encrypted'],
                    'timestamp' => $timestamp,
                    //'user_id' => -1,
                )
            );

            $post_id = DB::insertId();
            // Update existing key
        } elseif (null !== $post_action && $post_action === 'update') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);

            DB::update(
                prefixTable('api'),
                array(
                    'label' => $post_label,
                    'timestamp' => $timestamp,
                ),
                'increment_id=%i',
                $post_id
            );
            // Delete existing key
        } elseif (null !== $post_action && $post_action === 'delete') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);

            DB::query(
                'DELETE FROM ' . prefixTable('api') . ' WHERE increment_id = %i',
                $post_id
            );
        }

        // send data
        echo prepareExchangedData(
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
        // Is admin?
        if ($session->get('user-admin') === 1) {
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

        $post_action = filter_var($dataReceived['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // add new key
        if (null !== $post_action && $post_action === 'add') {
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_ip = filter_var($dataReceived['ip'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Store in DB
            DB::insert(
                prefixTable('api'),
                array(
                    'increment_id' => null,
                    'type' => 'ip',
                    'label' => $post_label,
                    'value' => $post_ip,
                    'timestamp' => time(),
                )
            );

            $post_id = DB::insertId();
            // Update existing key
        } elseif (null !== $post_action && $post_action === 'update') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_field = filter_var($dataReceived['field'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_value = filter_var($dataReceived['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                'increment_id=%i',
                $post_id
            );
            // Delete existing key
        } elseif (null !== $post_action && $post_action === 'delete') {
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            DB::query('DELETE FROM ' . prefixTable('api') . ' WHERE increment_id=%i', $post_id);
        }

        echo prepareExchangedData(
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
                    'created_at' => time(),
                )
            );
        } else {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $post_status,
                    'updated_at' => time(),
                ),
                'type = %s AND intitule = %s',
                'admin',
                'api'
            );
        }
        $SETTINGS['api'] = $post_status;
        break;

    case 'run_duo_config_check':
        //Libraries call
        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
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

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $post_data,
            'decode'
        );

        // Check if we have what we need first
        if (empty($dataReceived['duo_ikey']) || empty($dataReceived['duo_skey']) || empty($dataReceived['duo_host'])) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('data_are_missing'),
                ),
                'encode'
            );
            break;
        }

        // Run Duo Config Check
        try {
            $duo_client = new Client(
                $dataReceived['duo_ikey'],
                $dataReceived['duo_skey'],
                $dataReceived['duo_host'],
                $SETTINGS['cpassman_url'].'/'.DUO_CALLBACK
            );
        } catch (DuoException $e) {
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS Error - duo config - '.$e->getMessage());
            }
            // deepcode ignore ServerLeak: Data is encrypted before being sent
            echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('duo_config_error'),
                    ),
                    'encode'
            );
            break;
        }

        // Run healthcheck against Duo with the config
        try {
            $duo_client->healthCheck();
        } catch (DuoException $e) {
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS Error - duo config - '.$e->getMessage());
            }
            // deepcode ignore ServerLeak: Data is encrypted before being sent
            echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('duo_error_check_config'),
                    ),
                    'encode'
            );
            break;
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

    case 'save_google_options':
        // Check KEY and rights
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
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
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
                    'created_at' => time(),
                )
            );
        } else {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $tmp,
                    'updated_at' => time(),
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
                        'created_at' => time(),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['ga_website_name']),
                        'updated_at' => time(),
                    ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'ga_website_name'
                );
            }
            $SETTINGS['ga_website_name'] = htmlspecialchars_decode($dataReceived['ga_website_name']);
        } else {
            $SETTINGS['ga_website_name'] = '';
        }

        // send data
        echo '[{"result" : "' . addslashes($lang['done']) . '" , "error" : ""}]';
        break;

    case 'save_agses_options':
        // Check KEY and rights
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
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
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
                        'created_at' => time(),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_url']),
                        'updated_at' => time(),
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
                        'created_at' => time(),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_id']),
                        'updated_at' => time(),
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
                        'created_at' => time(),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_apikey']),
                        'updated_at' => time(),
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
        echo '[{"result" : "' . addslashes($lang['done']) . '" , "error" : ""}]';
        break;

    case 'save_option_change':
        // Check KEY and rights
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
        
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $post_data,
            'decode'
        );
        
        // prepare data
        $post_value = filter_var($dataReceived['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_field = filter_var($dataReceived['field'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_translate = isset($dataReceived['translate']) === true ? filter_var($dataReceived['translate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
        
        require_once 'main.functions.php';

        // In case of key, then encrypt it
        if ($post_field === 'bck_script_passkey') {
            $post_value = cryption(
                $post_value,
                '',
                'encrypt',
                $SETTINGS
            )['string'];
        }

        // Check if setting is already in DB. If NO then insert, if YES then update.
        $data = DB::query(
            'SELECT * FROM ' . prefixTable('misc') . '
            WHERE type = %s AND intitule = %s',
            'admin',
            $post_field
        );
        $counter = DB::count();
        if ($counter === 0) {
            DB::insert(
                prefixTable('misc'),
                array(
                    'valeur' => $post_value,
                    'type' => 'admin',
                    'intitule' => $post_field,
                    'created_at' => time(),
                )
            );
            // in case of stats enabled, add the actual time
            if ($post_field === 'send_stats') {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'valeur' => time(),
                        'type' => 'admin',
                        'intitule' => $post_field . '_time',
                        'updated_at' => time(),
                    )
                );
            }
        } else {
            // Update DB settings
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $post_value,
                    'updated_at' => time(),
                ),
                'type = %s AND intitule = %s',
                'admin',
                $post_field
            );

            // in case of stats enabled, update the actual time
            if ($post_field === 'send_stats') {
                // Check if previous time exists, if not them insert this value in DB
                DB::query(
                    'SELECT * FROM ' . prefixTable('misc') . '
                    WHERE type = %s AND intitule = %s',
                    'admin',
                    $post_field . '_time'
                );
                $counter = DB::count();
                if ($counter === 0) {
                    DB::insert(
                        prefixTable('misc'),
                        array(
                            'valeur' => 0,
                            'type' => 'admin',
                            'intitule' => $post_field . '_time',
                            'created_at' => time(),
                        )
                    );
                } else {
                    DB::update(
                        prefixTable('misc'),
                        array(
                            'valeur' => 0,
                            'updated_at' => time(),
                        ),
                        'type = %s AND intitule = %s',
                        'admin',
                        $post_field
                    );
                }
            }
        }

        // special Cases
        if ($post_field === 'cpassman_url') {
            // update also jsUrl for CSFP protection
            $jsUrl = $post_value . '/includes/libraries/csrfp/js/csrfprotector.js';
            $csrfp_file = '../includes/libraries/csrfp/libs/csrfp.config.php';
            $data = file_get_contents($csrfp_file);
            $posJsUrl = strpos($data, '"jsUrl" => "');
            $posEndLine = strpos($data, '",', $posJsUrl);
            $line = substr($data, $posJsUrl, ($posEndLine - $posJsUrl + 2));
            $newdata = str_replace($line, '"jsUrl" => "' . filter_var($jsUrl, FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '",', $data);
            file_put_contents($csrfp_file, $newdata);
        } elseif ($post_field === 'restricted_to_input' && (int) $post_value === 0) {
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => 0,
                    'updated_at' => time(),
                ),
                'type = %s AND intitule = %s',
                'admin',
                'restricted_to_roles'
            );
        }

        // Update last settings change timestamp
        // This to ensure that the settings are refreshed in the session
        $settings = $session->get('teampass-settings');
        $settings['timestamp'] = time();
        $session->set('teampass-settings', $settings);

        // Encrypt data to return
        echo prepareExchangedData(
            array(
                'error' => false,
                'misc' => $counter . ' ; ' . $SETTINGS[$post_field],
                'message' => empty($post_translate) === false ? $lang->get($post_translate) : '',
            ),
            'encode'
        );
        break;

    case 'get_values_for_statistics':
        // Check KEY and rights
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

        // Encrypt data to return
        echo prepareExchangedData(
            getStatisticsData($SETTINGS),
            'encode'
        );

        break;

    case 'save_sending_statistics':
        // Check KEY and rights
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
                        'created_at' => time(),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => $post_status,
                        'updated_at' => time(),
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
                        'created_at' => time(),
                    )
                );
            } else {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => $post_list,
                        'updated_at' => time(),
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

        // send data
        echo '[{"error" : false}]';
        break;

    case 'is_backup_table_existing':
        // Check KEY and rights
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

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $post_data,
            'decode'
        );

        // prepare data
        $sourcePage = filter_var($dataReceived['source_page'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($sourcePage === 'ldap') {
            $selected_administrated_by = isset($SETTINGS['ldap_new_user_is_administrated_by']) && $SETTINGS['ldap_new_user_is_administrated_by'] === '0' ? 1 : 0;
            $selected_new_user_role = isset($SETTINGS['ldap_new_user_role']) && $SETTINGS['ldap_new_user_role'] === '0' ? 1 : 0;
        } elseif ($sourcePage === 'oauth') {
            $selected_administrated_by = isset($SETTINGS['oauth_new_user_is_administrated_by']) && $SETTINGS['oauth_new_user_is_administrated_by'] === '0' ? 1 : 0;
            $selected_new_user_role = isset($SETTINGS['oauth_selfregistered_user_belongs_to_role']) && $SETTINGS['oauth_selfregistered_user_belongs_to_role'] === '0' ? 1 : '';
        } else {
            echo prepareExchangedData(
                [], 
                'encode'
            );
    
            break;
        }

        $json = array();
        array_push(
            $json,
            array(
                'id' => '0',
                'title' => $lang->get('god'),
                'selected_administrated_by' => $selected_administrated_by,
                'selected_role' => $selected_new_user_role,
            )
        );

        $rows = DB::query(
            'SELECT id, title
                FROM ' . prefixTable('roles_title') . '
                ORDER BY title ASC'
        );
        foreach ($rows as $record) {
            if ($sourcePage === 'ldap') {
                $selected_administrated_by = isset($SETTINGS['ldap_new_user_is_administrated_by']) && $SETTINGS['ldap_new_user_is_administrated_by'] === $record['id'] ? 1 : 0;
                $selected_new_user_role = isset($SETTINGS['ldap_new_user_role']) && $SETTINGS['ldap_new_user_role'] === $record['id'] ? 1 : 0;
            } elseif ($sourcePage === 'oauth') {
                $selected_administrated_by = isset($SETTINGS['oauth_new_user_is_administrated_by']) && $SETTINGS['oauth_new_user_is_administrated_by'] === $record['id'] ? 1 : 0;
                $selected_new_user_role = isset($SETTINGS['oauth_selfregistered_user_belongs_to_role']) && $SETTINGS['oauth_selfregistered_user_belongs_to_role'] === $record['id'] ? 1 : 0;
            }
            array_push(
                $json,
                array(
                    'id' => $record['id'],
                    'title' => addslashes($record['title']),
                    'selected_administrated_by' => $selected_administrated_by,
                    'selected_role' => $selected_new_user_role,
                )
            );
        }

        echo prepareExchangedData(
            $json, 
            'encode'
        );

        break;

    case 'save_user_change':
        // Check KEY and rights
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

        $post_increment_id = isset($dataReceived['increment_id']) === true ? filter_var($dataReceived['increment_id'], FILTER_SANITIZE_NUMBER_INT) : '';
        $post_field = filter_var($dataReceived['field'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_value = filter_var($dataReceived['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (is_numeric($post_increment_id) === false) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('no_user'),
                ),
                'encode'
            );
            break;
        }
        
        //update.
        DB::debugMode(false);
        DB::update(
            prefixTable('api'),
            array(
                $post_field => $post_value,
            ),
            'increment_id = %i',
            (int) $post_increment_id
        );
        DB::debugMode(false);
        //log
        logEvents($SETTINGS, 'system', 'api_user_readonly', (string) $session->get('user-id'), $session->get('user-login'));

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );

        break;
    
    case "tablesIntegrityCheck":
        // Check KEY and rights
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

        $ret = tablesIntegrityCheck();
        
        echo prepareExchangedData(
            array(
                'error' => $ret['error'],
                'message' => $ret['message'],
                'tables' => json_encode($ret['array'], JSON_FORCE_OBJECT),
            ),
            'encode'
        );

        break;

    case "filesIntegrityCheck":
        // Check KEY and rights
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

        // New hash-based integrity check
        $SETTINGS['cpassman_dir'] = rtrim($SETTINGS['cpassman_dir'], '/');
        $ret = verifyFileHashes($SETTINGS['cpassman_dir'], __DIR__.'/../files_reference.txt');

        $ignoredFiles = DB::queryFirstField(
            'SELECT valeur 
            FROM ' . prefixTable('misc') . ' 
            WHERE type = %s AND intitule = %s',
            'admin',
            'ignored_unknown_files'
        );
        $ignoredFilesKeys = !is_null($ignoredFiles) && !empty($ignoredFiles) ? json_decode($ignoredFiles) : [];
        
        echo prepareExchangedData(
            array(
                'error' => $ret['error'],
                'message' => $ret['message'],
                'files' => json_encode($ret['array'], JSON_FORCE_OBJECT),
                'ignoredNumber' => count($ignoredFilesKeys),
            ),
            'encode'
        );

        break;

    case "ignoreFile":
        // Check KEY and rights
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

        $post_id = isset($dataReceived['id']) === true ? filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT) : '';

        // Get ignored unknown files
        $existingData = DB::queryFirstRow(
            'SELECT valeur 
            FROM ' . prefixTable('misc') . ' 
            WHERE type = %s AND intitule = %s',
            'admin',
            'ignored_unknown_files'
        );

        // Get the json list ignored unknown files
        $unknownFilesArray = [];
        if (!empty($existingData) && !empty($existingData['valeur'])) {
            $unknownFilesArray = json_decode($existingData['valeur'], true) ?: [];
        }

        // Add the new file to the list
        $unknownFilesArray[] = $post_id;

        // Save the new list
        DB::insertUpdate(
            prefixTable('misc'),
            [
                'type' => 'admin',
                'intitule' => 'ignored_unknown_files',
                'valeur' => json_encode($unknownFilesArray),
                'created_at' => time(),
            ],
            [
                'valeur' => json_encode($unknownFilesArray),
                'updated_at' => time(),
            ]
        );

        
        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );

        break;

    case "deleteFilesIntegrityCheck":
        // Check KEY and rights
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

        // Get the list of files to delete
        $filesToDelete = DB::queryFirstField(
            'SELECT valeur 
            FROM ' . prefixTable('misc') . ' 
            WHERE type = %s AND intitule = %s',
            'admin',
            'unknown_files'
        );

        if (is_null($filesToDelete)) {
            echo prepareExchangedData(
                array(
                    'deletionResults' => null,
                ),
                'encode'
            );
            break;
        }
        $referenceFiles = (array) json_decode($filesToDelete);
        
        //  Launch
        $ret = deleteFiles($referenceFiles, true);
        
        echo prepareExchangedData(
            array(
                'deletionResults' => $ret,
            ),
            'encode'
        );

        break;
    
}

/**
 * Delete multiple files with cross-platform compatibility
 * 
 * This function deletes a list of files while ensuring compatibility
 * across different operating systems (Windows, Linux, etc.)
 * 
 * @param array $files Array of file paths to delete
 * @param bool $ignoreErrors If true, continues execution even if a file cannot be deleted
 * @return array Results of deletion operations (success/failure for each file)
 */
function deleteFiles(array $files, bool $ignoreErrors = false): array
{
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    $results = [];
    $fullPath = __DIR__ . '/../';
    
    foreach ($files as $file) {
        // Normalize path separators for cross-platform compatibility
        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $fullPath.$file);
        
        // Check if file exists
        if (!file_exists($normalizedPath)) {
            $results[$normalizedPath] = [
                'success' => false,
                'error' => $lang->get('file_not_exists')
            ];
            
            if (!$ignoreErrors) {
                return $results;
            }
            
            continue;
        }
        
        // Check if path is actually a file (not a directory)
        if (!is_file($normalizedPath)) {
            $results[$normalizedPath] = [
                'success' => false,
                'error' => $lang->get('path_not_a_file')
            ];
            
            if (!$ignoreErrors) {
                return $results;
            }
            
            continue;
        }
        
        // Check if file is writable (can be deleted)
        if (!is_writable($normalizedPath)) {
            $results[$normalizedPath] = [
                'success' => false,
                'error' => $lang->get('file_not_writable')
            ];
            
            if (!$ignoreErrors) {
                return $results;
            }
            
            continue;
        }
        
        // Try to delete the file
        $deleteResult = '';//@unlink($normalizedPath);
        
        if ($deleteResult) {
            $results[$normalizedPath] = [
                'success' => true,
                'error' => '',
            ];
        } else {
            $results[$normalizedPath] = [
                'success' => false,
                'error' => $lang->get('failed_to_delete')
            ];
            
            if (!$ignoreErrors) {
                return $results;
            }
        }
    }
    
    return $results;
}

/**
 * Check the integrity of the files
 * 
 * @param string $baseDir
 * @return array
 */
function filesIntegrityCheck($baseDir): array
{
    $referenceFile = __DIR__ . '/../files_reference.txt';

    $unknownFiles = findUnknownFiles($baseDir, $referenceFile);

    if (empty($unknownFiles)) {
        return [
            'error' => false,
            'array' => [],
            'message' => ''
        ];
    }

    // Check if the files are in the integrity file
    return [
        'error' => true,
        'array' => $unknownFiles,
        'message' => ''
    ];
}

/*
 * Get all files in a directory
 * 
 * @param string $dir
 * @return array
 */
function getAllFiles($dir): array
{
    $files = [];
    $excludeDirs = ['upload', 'files', 'install', '_tools', 'random_compat', 'avatars']; // Répertoires à exclure
    $excludeFilePrefixes = ['csrfp.config.php', 'settings.php', 'version-commit.php', 'phpstan.neon']; // Fichiers à exclure par préfixe

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(
                $dir,
                FilesystemIterator::SKIP_DOTS
            ),
            function ($current, $key, $iterator) {
                // Ignore hidden files and folders
                if ($current->getFilename()[0] === '.') {
                    return false;
                }
                return true;
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        try {
            if ($file->isFile()) {
                $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath); // Normalisation Windows/Linux

                // Split relatif path into parts
                $pathParts = explode('/', $relativePath);

                // Check if any part of the path is in the excludeDirs array
                foreach ($pathParts as $part) {
                    if (in_array($part, $excludeDirs, true)) {
                        continue 2; // Skip the file
                    }
                }

                // Check if the file name starts with any of the prefixes in excludeFilePrefixes
                $filename = basename($relativePath);
                foreach ($excludeFilePrefixes as $prefix) {
                    if (strpos($filename, $prefix) === 0) {
                        continue 2; // Skip the file
                    }
                }

                // If OK then add to the list
                $files[] = $relativePath;
            }
        } catch (UnexpectedValueException $e) {
            continue; // Ignore the file if an error occurs
        }
    }

    return $files;
}

/**
 * Find unknown files
 * 
 * @param string $baseDir
 * @param string $referenceFile
 * @return array
 */
function findUnknownFiles($baseDir, $referenceFile): array
{
    $currentFiles = getAllFiles($baseDir);
    $referenceFiles = file($referenceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Remove empty lines and comments from the reference file
    $unknownFiles = array_diff($currentFiles, $referenceFiles);

    // Save list to DB
    DB::insertUpdate(
        prefixTable('misc'),
        array(
            'type' => 'admin',
            'intitule' => 'unknown_files',
            'valeur' => json_encode($unknownFiles),
            'created_at' => time(),
        ),
        [
            'valeur' => json_encode($unknownFiles),
            'updated_at' => time(),
        ]
    );

    // NOw remove ignored files from the list returned to user
    // Get ignored files
    $ignoredFiles = DB::queryFirstField(
        'SELECT valeur 
        FROM ' . prefixTable('misc') . ' 
        WHERE type = %s AND intitule = %s',
        'admin',
        'ignored_unknown_files'
    );
    $ignoredFilesKeys = !is_null($ignoredFiles) && !empty($ignoredFiles) ? array_flip(json_decode($ignoredFiles)) : [];
    $unknownFiles = array_diff_key($unknownFiles, $ignoredFilesKeys);

    return $unknownFiles;
}

/**
 * Check the integrity of the tables
 * 
 * @return array
 */
function tablesIntegrityCheck(): array
{
    // Get integrity tables file
    $integrityTablesFile = TEAMPASS_ROOT_PATH . '/includes/tables_integrity.json';
    if (file_exists($integrityTablesFile) === false) {
        return [
            'error' => true,
            'message' => "Integrity file has not been found."
        ];
    }
    // Convert json to array
    $integrityTables = json_decode(file_get_contents($integrityTablesFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => true,
            'message' => "Integrity file is corrupted"
        ];
    }
    // Get all tables
    $tables = [];
    foreach (DB::queryFirstColumn("SHOW TABLES") as $table) {
        $tables[] = str_replace(DB_PREFIX, "", $table);;
    }
    // Prepare the integrity check
    $tablesInError = [];
    // Check if the tables are in the integrity file
    foreach ($tables as $table) {
        $tableFound = false;
        $tableHash = "";
        foreach ($integrityTables as $integrityTable) {
            if ($integrityTable["table_name"] === $table) {
                $tableFound = true;
                $tableHash = $integrityTable["structure_hash"];
                break; // Sortir de la boucle si la table est trouvée
            }
        }

        if ($tableFound === true) {
            $createTable = DB::queryFirstRow("SHOW CREATE TABLE ".DB_PREFIX."$table");
            $currentHash = hash('sha256', $createTable['Create Table']);
            if ($currentHash !== $tableHash) {
                $tablesInError[] = DB_PREFIX.$table;
            }            
        }
    }
    return [
        'error' => false,
        'array' => $tablesInError,
        'message' => ""
    ];
}

/**
 * Verify file integrity by checking presence & MD5 hashes.
 *
 * This function compares the current files in the project directory against a reference file
 * containing expected file paths and their MD5 hashes. It reports any missing files or files
 * whose hashes do not match the reference.
 *
 * Steps:
 *  - Load reference file data (file => hash)
 *  - Get all current files in the base directory
 *  - For each file, check if it exists in the reference; if so, compare hashes
 *  - Collect issues for missing or changed files
 *
 * @param string $baseDir        Base directory to scan for files
 * @param string $referenceFile  Path to reference file with known hashes
 * @return array                 Result with 'error', 'array' of issues, and 'message'
 */
function verifyFileHashes($baseDir, $referenceFile): array
{
    // Load reference data (file => hash)
    $referenceData = parseReferenceFile($referenceFile);
    // Get list of all current files in the project
    $allFiles = getAllFiles($baseDir);

    // Debug output
    error_log("DEBUG: Reference keys: " . json_encode(array_keys($referenceData)));
    error_log("DEBUG: Current files: " . json_encode($allFiles));

    $issues = [];

    // Compare current files to reference
    foreach ($allFiles as $file) {
        // Check if file exists in reference list
        if (!isset($referenceData[$file])) {
            error_log("DEBUG: File not found in reference: $file");
            $issues[] = "$file is not listed in reference file";
            continue;
        }

        // Compare hashes
        $expectedHash = $referenceData[$file];
        $actualHash = md5_file($baseDir . '/' . $file);

        if ($expectedHash !== $actualHash) {
            error_log("DEBUG: Hash mismatch for $file => expected: $expectedHash, actual: $actualHash");
            $issues[] = "$file (expected: $expectedHash, actual: $actualHash)";
        }
    }

    // Return summary
    return [
        'error' => !empty($issues),
        'array' => $issues,
        'message' => empty($issues) ? 'Project files integrity check is successful.' : 'Integrity issues found.'
    ];
}

/**
 * Parse the reference file into an associative array.
 *
 * Each line in the reference file should be of the form: "path hash".
 * The function returns an array mapping file paths to their expected MD5 hashes.
 *
 * @param string $referenceFile  Path to reference file
 * @return array                 [ 'file/path' => 'md5hash' ]
 */
function parseReferenceFile($referenceFile) {
    $data = [];
    $lines = file($referenceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        [$path, $hash] = explode(' ', $line, 2);
        $data[$path] = trim($hash);
    }
    return $data;
}

/**
 * Get all files in a directory with their md5 hashes.
 *
 * Recursively scans a directory, returning an associative array of file paths
 * (relative to $dir) mapped to their MD5 hashes. Paths are normalized for cross-platform compatibility.
 *
 * @param string $dir    Directory to scan
 * @return array         [ 'file/path' => 'md5hash' ]
 */
function getAllFilesWithHashes(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            // Build relative path
            $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath); // Normalize for Windows
            // Calculate hash
            $files[$relativePath] = md5_file($file->getPathname());
        }
    }
    return $files;
}