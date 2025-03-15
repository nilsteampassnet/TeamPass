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
 * @file      backups.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;


// Load functions
require_once 'main.functions.php';
$session = SessionManager::getSession();


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
if (
    $checkUserAccess->userAccessPage('backups') === false ||
    $checkUserAccess->checkSession() === false
) {
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
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-admin') === 0) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_key = filter_var($dataReceived['encryptionKey'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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
                    $return .= "\n\n" . $row2['Create Table'] . ";\n\n";

                    //prepare all fields and datas
                    for ($i = 0; $i < $numFields; ++$i) {
                        while ($row = $result->fetch_row()) {
                            $return .= 'INSERT INTO ' . $table . ' VALUES(';
                            for ($j = 0; $j < $numFields; ++$j) {
                                // Manage NULL values
                                $value = $row[$j] === null ? 'NULL' : '"' . addslashes(preg_replace("/\n/", '\\n', $row[$j])) . '"';                    
                                $return .= $value;
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
                if (empty($post_key) === false) {
                    // Encrypt the file
                    prepareFileWithDefuse(
                        'encrypt',
                        $SETTINGS['path_to_files_folder'] . '/' . $filename,
                        $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $filename,
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
                $session->set('user-key_tmp', GenerateCryptKey(16, false, true, true, false, true));

                //update LOG
                logEvents(
                    $SETTINGS,
                    'admin_action',
                    'dataBase backup',
                    (string) $session->get('user-id'),
                    $session->get('user-login')
                );

                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'download' => 'sources/downloadFile.php?name=' . urlencode($filename) .
                            '&sub=files&file=' . $filename . '&type=sql&key=' . $session->get('key') . '&key_tmp=' .
                            $session->get('user-key_tmp') . '&pathIsFiles=1',
                    ),
                    'encode'
                );

                break;
            }

            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => '',
                ),
                'encode'
            );
            break;

        case 'onthefly_restore':
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
            } elseif ($session->get('user-admin') === 0) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_key = filter_var($dataReceived['encryptionKey'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_backupFile = filter_var($dataReceived['backupFile'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_clearFilename = filter_var($dataReceived['clearFilename'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_offset = (int) filter_var($dataReceived['offset'], FILTER_SANITIZE_NUMBER_INT);
            $post_totalSize = (int) filter_var($dataReceived['totalSize'], FILTER_SANITIZE_NUMBER_INT);
            $batchSize = 500;

            // Check if the offset is greater than the total size
            if (empty($post_offset) === false && $post_offset >= $post_totalSize) {
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => 'operation_finished',
                    ),
                    'encode'
                );
                break;
            }
            
            if (WIP === true) error_log('DEBUG: Offset -> '.$post_offset.'/'.$post_totalSize.' | File -> '.$post_clearFilename.' | key -> '.$post_key);

            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

            if (empty($post_clearFilename) === true) {
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
                
                // Decrypt the file
                if (empty($post_key) === false) {
                    // Decrypt the file
                    
                    $ret = prepareFileWithDefuse(
                        'decrypt',
                        $SETTINGS['path_to_files_folder'] . '/' . $post_backupFile,
                        $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $post_backupFile,
                        $post_key
                    );
                    
                    if (empty($ret) === false && $ret !== true) {
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => 'An error occurred.',
                            ),
                            'encode'
                        );
                        break;
                    }

                    // Do clean
                    fileDelete($SETTINGS['path_to_files_folder'] . '/' . $post_backupFile, $SETTINGS);
                    $post_backupFile = $SETTINGS['path_to_files_folder'] . '/defuse_temp_' . $post_backupFile;
                } else {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => 'An error occurred. No encryption key provided.',
                        ),
                        'encode'
                    );
                    break;
                }
            } else {
                $post_backupFile = $post_clearFilename;
            }
                 

            //read sql file
            $handle = fopen($post_backupFile, 'r');

            // get total file size
            if ((int) $post_totalSize === 0) {
                $post_totalSize = filesize($post_backupFile);
            }

            if ($handle !== false) {
                // Move the file pointer to the current offset
                fseek($handle, $post_offset);
                $query = '';
                $executedQueries = 0;
                while (!feof($handle) && $executedQueries < $batchSize) {
                    $line = fgets($handle);
                    // Check if not false
                    if ($line !== false) {
                        // Check if the line is part of an SQL statement
                        if (substr(trim($line), -1) != ';') {
                            $query .= $line;
                        } else {
                            // Execute the complete SQL statement
                            $query .= $line;
                            DB::query($query);
                            $query = '';
                            $executedQueries++;
                        }
                    }
                }

                // Calculate the new offset
                $newOffset = ftell($handle);

                // Check if the end of the file has been reached
                $isEndOfFile = feof($handle);
                fclose($handle);

                // Respond with the new offset
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'newOffset' => $newOffset,
                        'totalSize' => $post_totalSize,
                        'clearFilename' => $post_backupFile,
                    ),
                    'encode'
                );

                // Check if the end of the file has been reached to delete the file
                if ($isEndOfFile) {
                    error_log('DEBUG: End of file reached. Deleting file '.$post_backupFile);
                    unlink($post_backupFile);
                }
            } else {
                // Handle file opening error
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => 'Unable to open backup file.',
                    ),
                    'encode'
                );
            }
            break;
    }
}

