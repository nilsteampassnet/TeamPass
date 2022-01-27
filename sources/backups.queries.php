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
 * @file      backups.queries.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'options', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_data = filter_input(
    INPUT_POST,
    'data',
    FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    FILTER_FLAG_NO_ENCODE_QUOTES
);

// manage action required
if (null !== $post_type) {
    switch ($post_type) {
            //CASE adding a new function
        case 'onthefly_backup':
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
            } elseif ($_SESSION['is_admin'] === false) {
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

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Prepare variables
            $post_key = filter_var($dataReceived['encryptionKey'], FILTER_SANITIZE_STRING);

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
                if (empty($pre) || substr_count($table, $pre) > 0) {
                    // Do query
                    $result = DB::queryRaw('SELECT * FROM ' . $table);
                    DB::queryRaw(
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

            if (empty($return) === false) {
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
                if (empty($post_key) === false) {
                    // Encrypt the file
                    prepareFileWithDefuse(
                        'encrypt',
                        $SETTINGS['path_to_files_folder'] . '/' . $filename,
                        $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $filename,
                        $SETTINGS,
                        $post_key
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
                logEvents(
                    $SETTINGS,
                    'admin_action',
                    'dataBase backup',
                    (string) $_SESSION['user_id'],
                    $_SESSION['login']
                );

                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => false,
                        'message' => '',
                        'download' => 'sources/downloadFile.php?name=' . urlencode($filename) .
                            '&sub=files&file=' . $filename . '&type=sql&key=' . $_SESSION['key'] . '&key_tmp=' .
                            $_SESSION['key_tmp'] . '&pathIsFiles=1',
                    ),
                    'encode'
                );

                break;
            }

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => '',
                ),
                'encode'
            );
            break;

        case 'onthefly_restore':
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
            } elseif ($_SESSION['is_admin'] === false) {
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

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Prepare variables
            $post_key = filter_var($dataReceived['encryptionKey'], FILTER_SANITIZE_STRING);
            $post_backupFile = filter_var($dataReceived['backupFile'], FILTER_SANITIZE_STRING);

            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

            // Get filename from database
            $data = DB::queryFirstRow(
                'SELECT valeur
                FROM ' . prefixTable('misc') . '
                WHERE increment_id = %i',
                $post_backupFile
            );

            // Delete operation id
            DB::delete(
                prefixTable('misc'),
                'increment_id = %i',
                $post_backupFile
            );

            $post_backupFile = $data['valeur'];

            // Undecrypt the file
            if (empty($post_key) === false) {
                // Decrypt the file
                $ret = prepareFileWithDefuse(
                    'decrypt',
                    $SETTINGS['path_to_files_folder'] . '/' . $post_backupFile,
                    $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $post_backupFile,
                    $SETTINGS,
                    $post_key
                );

                if (empty($ret) === false) {
                    echo '[{"result":"db_restore" , "message":"' . $ret . '"}]';
                    break;
                }

                // Do clean
                fileDelete($SETTINGS['path_to_files_folder'] . '/' . $post_backupFile, $SETTINGS);
                $post_backupFile = $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $post_backupFile;
            } else {
                $post_backupFile = $SETTINGS['path_to_files_folder'] . '/' . $post_backupFile;
            }

            //read sql file
            $handle = fopen($post_backupFile, 'r');
            $query = '';
            while (!feof($handle)) {
                $query .= fgets($handle, 4096);
                if (substr(rtrim($query), -1) == ';') {
                    //launch query
                    DB::queryRaw($query);
                    $query = '';
                }
            }
            fclose($handle);

            //delete file
            unlink($post_backupFile);

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
