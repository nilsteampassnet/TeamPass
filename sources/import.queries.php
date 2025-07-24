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
 * @file      import.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;
use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$antiXss = new AntiXSS();

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
    $checkUserAccess->userAccessPage('import') === false ||
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
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

// Set some constants for program readability
define('KP_PATH', 0);
define('KP_GROUP', 1);
define('KP_TITLE', 2);
define('KP_PASSWORD', 3);
define('KP_USERNAME', 4);
define('KP_URL', 5);
define('KP_UUID', 6);
define('KP_NOTES', 7);


// Prepare POST variables
$data = [
    'type' => $request->request->filter('type', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'data' => $request->request->filter('data', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'key' => $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'file' => $request->request->filter('file', '', FILTER_SANITIZE_SPECIAL_CHARS),
];

$filters = [
    'type' => 'trim|escape',
    'data' => 'trim|escape',
    'key' => 'trim|escape',
    'file' => 'cast:integer',
];

$inputData = dataSanitizer(
    $data,
    $filters
);


$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Build query
switch ($inputData['type']) {
    //Check if import CSV file format is what expected
    case 'import_file_format_csv':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        //load full tree
        $tree->rebuild();
        $tree = $tree->getDescendants();

        // Init post variable
        $post_operation_id = $inputData['file'];

        // Get filename from database
        $data = DB::queryFirstRow(
            'SELECT valeur
            FROM '.prefixTable('misc').'
            WHERE increment_id = %i AND type = "temp_file"',
            $post_operation_id
        );

        // Delete operation id
        DB::delete(
            prefixTable('misc'),
            "increment_id = %i AND type = 'temp_file'",
            $post_operation_id
        );

        // Initialisation
        $file = $SETTINGS['path_to_files_folder'] . '/' . $data['valeur'];
        $importation_possible = true;
        $valuesToImport = [];
        $items_number = 0;
        $batchInsert = [];
        $uniqueFolders = [];
        $comment = '';

        // Vérifier si le fichier est accessible
        if (!file_exists($file) || !is_readable($file)) {
            echo prepareExchangedData(
                array('error' => true, 'message' => $lang->get('cannot_open_file')),
                'encode'
            );
            unlink($file);
            break;
        }

        // Ouvrir le fichier pour récupérer l'en-tête
        $fp = fopen($file, 'r');
        $header = fgetcsv($fp);
        fclose($fp);

        // Vérifier si l'en-tête est valide
        if ($header === false || empty($header)) {
            echo prepareExchangedData(
                array('error' => true, 'message' => $lang->get('import_error_no_read_possible')),
                'encode'
            );
            unlink($file);
            break;
        }

        // Initialize the array to store values to import
        $valuesToImport = [];

        // Configuration du parser CSV
        $config = new LexerConfig();
        $lexer = new Lexer($config);
        $config->setIgnoreHeaderLine(true);

        /**
         * Get the list of available encodings.
         *
         * @return array List of available encodings.
         */
        function getAvailableEncodings(): array
        {
            // List of encodings we want to support (in order of priority)
            $desiredEncodings = [
                'UTF-8',
                'UTF-16',
                'UTF-16LE',
                'UTF-16BE',
                'ISO-8859-1',
                'ISO-8859-15',
                'Windows-1252',
                'Windows-1251',  // Cyrillique
                'CP1251',        // Cyrillique alternatif
                'KOI8-R',        // Cyrillique russe
                'Shift_JIS',     // Japonais
                'EUC-JP',        // Japonais
                'ISO-2022-JP',   // Japonais
                'TIS-620',       // Thaï
                'Windows-874',   // Thaï Windows
                'Big5',          // Chinois traditionnel
                'GB2312',        // Chinois simplifié
                'GBK',           // Chinois simplifié étendu
                'EUC-KR',        // Coréen
                'ISO-8859-2',    // Europe centrale
                'ISO-8859-5',    // Cyrillique ISO
                'ISO-8859-7',    // Grec
                'Windows-1250',  // Europe centrale
                'Windows-1253',  // Grec
                'Windows-1254',  // Turc
                'Windows-1255',  // Hébreu
                'Windows-1256',  // Arabe
            ];

            // Get the list of encodings supported by the system
            $systemEncodings = mb_list_encodings();

            // Filter to keep only those that are available
            $availableEncodings = [];
            foreach ($desiredEncodings as $encoding) {
                if (in_array($encoding, $systemEncodings)) {
                    $availableEncodings[] = $encoding;
                }
            }

            // Ensure UTF-8 is always present
            if (!in_array('UTF-8', $availableEncodings)) {
                array_unshift($availableEncodings, 'UTF-8');
            }

            return $availableEncodings;
        }

        /**
         * Detects the encoding of a file using available encodings.
         * @param string $filepath The path to the file to be checked.
         * @return string The detected encoding or 'UTF-8' if detection fails.
         */
        function detectFileEncoding($filepath): string
        {
            $content = file_get_contents($filepath);
            $availableEncodings = getAvailableEncodings();

            $detected = mb_detect_encoding($content, $availableEncodings, true);
            return $detected ?: 'UTF-8';
        }

        // Detect file encoding and set it in the config
        $detectedEncoding = detectFileEncoding($file);
        $config->setFromCharset($detectedEncoding);
        $config->setToCharset('UTF-8');

        // Get the data and ensure columns are correctly mapped
        $interpreter = new Interpreter();
        $interpreter->addObserver(function (array $row) use (&$valuesToImport, $header) {
            $rowData = array_combine($header, $row);

            if ($rowData !== false) {
                $valuesToImport[] = array(
                    'Label' => isset($rowData['label']) ? trim($rowData['label']) : '',
                    'Login' => isset($rowData['login']) ? trim($rowData['login']) : '',
                    'Password' => isset($rowData['password']) ? trim($rowData['password']) : '',
                    'url' => isset($rowData['url']) ? trim($rowData['url']) : '',
                    'Comments' => isset($rowData['description']) ? trim($rowData['description']) : '',
                    'Folder' => isset($rowData['folder']) ? trim($rowData['folder']) : '',
                );
            }
        });

        // Launch parsing with `Lexer`
        $lexer->parse($file, $interpreter);

        // Check if some date were imported
        if (empty($valuesToImport)) {
            echo prepareExchangedData(
                array('error' => true, 'message' => $lang->get('import_error_no_read_possible')),
                'encode'
            );
            unlink($file);
            break;
        }

        // Process lines
        $continue_on_next_line = false;
        $comment = "";
        foreach ($valuesToImport as $row) {
            // Check that each line has 6 columns
            if (count($row) !== 6) {
                echo prepareExchangedData(
                    array('error' => true, 'message' => $lang->get('import_error_invalid_structure')),
                    'encode'
                );
                unlink($file);
                break;
            }

            // CLean data
            $label    = cleanInput($row['Label']);
            $login    = cleanInput($row['Login']);
            $pwd      = cleanInput($row['Password']);
            $url      = cleanInput($row['url']);
            $folder   = cleanInput($row['Folder']);
            $comments = cleanInput($row['Comments']);

            // Handle multiple lignes description
            if (strpos($comments, '<br>') !== false || strpos($label, '<br>') !== false) {
                $continue_on_next_line = true;
                $comment .= " " . $label . " " . $comments;
            } else {
                // Insert previous line if changing line
                if (!empty($label)) {
                    $items_number++;

                    // Insert in batch
                    $batchInsert[] = array(
                        'label'        => $label,
                        'description'  => $comment . $comments,
                        'pwd'          => $pwd,
                        'url'          => $url,
                        'folder'       => ((int) $session->get('user-admin') === 1 || (int) $session->get('user-manager') === 1 || (int) $session->get('user-can_manage_all_users') === 1) ? $folder : '',
                        'login'        => $login,
                        'operation_id' => $post_operation_id,
                    );

                    // Store unique folders
                    // Each folder is the unique element of the path located inside $folder and delimited by '/' or '\'
                    $folders = preg_split('/[\/\\\\]/', $folder);
                    foreach ($folders as $folder) {
                        if (!empty($folder)) {
                            $uniqueFolders[$folder] = $folder;
                        }
                    }
                    $label = '';
                }
                // Update current variables
                $comment = '';
                $continue_on_next_line = false;
            }
        }

        // Insert last line
        if (!empty($label)) {
            $items_number++;

            // Insert in batch
            $batchInsert[] = array(
                'label'        => $label,
                'description'  => $comment . $comments,
                'pwd'          => $pwd,
                'url'          => $url,
                'folder'       => ((int) $session->get('user-admin') === 1 || (int) $session->get('user-manager') === 1 || (int) $session->get('user-can_manage_all_users') === 1) ? $folder : '',
                'login'        => $login,
                'operation_id' => $post_operation_id,
            );

            // Store unique folders
            // Each folder is the unique element of the path located inside $folder and delimited by '/' or '\'
            $folders = preg_split('/[\/\\\\]/', $folder);
            foreach ($folders as $folder) {
                if (!empty($folder)) {
                    $uniqueFolders[$folder] = $folder;
                }
            }
        }

        // Insert in database (with batch optimisation)
        if (!empty($batchInsert)) {
            $tableName = prefixTable('items_importations');
            $values = [];

            foreach ($batchInsert as $data) {
                $values[] = "('" . implode("','", array_map('addslashes', $data)) . "')";
            }

            $sql = "INSERT INTO `$tableName` (`label`, `description`, `pwd`, `url`, `folder`, `login`, `operation_id`) VALUES " . implode(',', $values);

            DB::query($sql);
        }

        // Display results
        echo prepareExchangedData(
            array('error' => false,
                'operation_id' => $post_operation_id,
                'items_number' => $items_number,
                'folders_number' => count($uniqueFolders),
                'userCanManageFolders' => ((int) $session->get('user-admin') === 1 || (int) $session->get('user-manager') === 1 || (int) $session->get('user-can_manage_all_users') === 1) ? 1 : 0
            ),
            'encode'
        );

        // Delete file after processing
        unlink($file);
        break;

    // Create new folders
    case 'import_csv_folders':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Check if user is manager at least.
        if ((int) $session->get('user-admin') === 0 && (int) $session->get('user-manager') === 0 && (int) $session->get('user-can_manage_all_users') === 0) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('import_error_no_rights'),
                ),
                'encode'
            );
            break;
        }

        // Decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // Is this a personal folder?
        $personalFolder = in_array($dataReceived['folderId'], $session->get('user-personal_folders')) ? 1 : 0;

        // Get all folders from objects in DB
        $itemsPath = DB::query(
            'SELECT folder, increment_id
            FROM '.prefixTable('items_importations').'
            WHERE operation_id = %i
            LIMIT %i, %i',
            $dataReceived['csvOperationId'],
            $dataReceived['offset'],
            $dataReceived['limit']
        );


        // Save matches "path -> ID" to prevent against multiple insertions
        $folderIdMap = $dataReceived['folderIdMap'] ?? [];

        require_once 'folders.class.php';
        $folderManager = new FolderManager($lang);

        // Prepare some variables
        $folderCreationError = [];

        // Get all folders from objects in DB
        foreach ($itemsPath as $item) {
            $path = $item['folder'];
            $importId = $item['increment_id']; // Entry ID in items_importations

            $parts = explode("\\", $path); // Décomposer le chemin en sous-dossiers
            $currentPath = "";
            $parentId = $dataReceived['folderId']; // Strating with provided folder

            foreach ($parts as $part) {
                $currentPath = trim($currentPath . "/" . $part, "/");
                $currentFolder = $part;

                // Check if this folder has already been created
                if (isset($folderIdMap[$currentPath]) || empty($currentFolder)) {
                    $parentId = $folderIdMap[$currentPath];
                    continue; // Jump to next iteration
                }

                // Vérifier si le dossier existe déjà en base
                $existingId = DB::queryFirstField(
                    'SELECT id FROM '.prefixTable('nested_tree').' WHERE title = %s AND parent_id = %i',
                    $currentFolder, // Searching only by name
                    $parentId // Ensure we search the correct parent
                );

                if ($existingId) {
                    $folderIdMap[$currentPath] = $existingId;
                    $parentId = $existingId;
                } else {
                    // Insert folder and get its ID
                    $params = [
                        'title' => (string) $currentFolder,
                        'parent_id' => isset($parentId) && $parentId !== null ? (int) $parentId : 0,
                        'personal_folder' => (int) $personalFolder,
                        'complexity' => $dataReceived['folderPasswordComplexity'] ?? 0,
                        'duration' => 0,
                        'access_rights' => $dataReceived['folderAccessRight'] ?? '',
                        'user_is_admin' => (int) $session->get('user-admin'),
                        'user_accessible_folders' => (array) $session->get('user-accessible_folders'),
                        'user_is_manager' => (int) $session->get('user-manager'),
                        'user_can_create_root_folder' => (int) $session->get('user-can_create_root_folder'),
                        'user_can_manage_all_users' => (int) $session->get('user-can_manage_all_users'),
                        'user_id' => (int) $session->get('user-id'),
                        'user_roles' => (string) $session->get('user-roles')
                    ];
                    $options = [
                        'rebuildFolderTree' => false,
                        'setFolderCategories' => false,
                        'manageFolderPermissions' => true,
                        'copyCustomFieldsCategories' => false,
                        'refreshCacheForUsersWithSimilarRoles' => true,
                    ];
                    $creationStatus = $folderManager->createNewFolder($params, $options);
                    $folderCreationDone = $creationStatus['error'];

                    if ((int) $folderCreationDone === 0) {
                        $newFolderId = $creationStatus['newId'];
                        // User created the folder
                        // Add new ID to list of visible ones
                        if ((int) $session->get('user-admin') === 0 && $creationStatus['error'] === false && $newFolderId !== 0) {
                            SessionManager::addRemoveFromSessionArray('user-accessible_folders', [$newFolderId], 'add');
                        }

                        // Save ID in map to avoid recreating it
                        $folderIdMap[$currentPath] = $newFolderId;
                        $parentId = $newFolderId;
                    } elseif ($creationStatus['error'] === true && $creationStatus['message'] !== '' && empty($creationStatus['newId'])) {
                        // Error during folder creation
                        $folderCreationError[] = [
                            'path' => $currentPath,
                            'message' => $creationStatus['message'],
                            'importId' => $importId,
                        ];
                    } else {
                        // Get ID of existing folder
                        $ret = DB::queryFirstRow(
                            'SELECT *
                            FROM ' . prefixTable('nested_tree') . '
                            WHERE title = %s',
                            $currentFolder
                        );
                        $newFolderId = $ret['id'];
                        $parentId = $newFolderId;
                    }
                }
            }

            // Update the importation entry with the new folder ID
            DB::update(prefixTable('items_importations'), [
                'folder_id' => $parentId,
            ], "increment_id=%i", $importId);
        }

        if (count($folderCreationError) > 0) {
            $errorMessage = [];
            $existingPaths = [];
            foreach ($folderCreationError as $error) {
                $path = $error['path'];
                
                // Check if the path already exists in the error message
                // to avoid duplicates in the response
                if (!isset($existingPaths[$path])) {
                    $existingPaths[$path] = true;
                    
                    $errorMessage[] = [
                        'errorPath' => $path,
                        'errorMessage' => $error['message'],
                    ];
                }
            }
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => json_encode($errorMessage),
                    'processedCount' => count($itemsPath),
                    'folderIdMap' => $folderIdMap,
                ),
                'encode'
            );
            break;
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'processedCount' => count($itemsPath),
                'folderIdMap' => $folderIdMap,
            ),
            'encode'
        );

        break;


    //Insert into DB the items the user has selected
    case 'import_csv_items':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }


        // Decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        //Get some info about personal folder
        $personalFolder = in_array($dataReceived['folderId'], $session->get('user-personal_folders')) ? 1 : 0;

        // Prepare some variables
        $targetFolderId = $dataReceived['folderId'];
        $targetFolderName = DB::queryFirstField(
            'SELECT title
            FROM '.prefixTable('nested_tree').'
            WHERE id = %i',
            $targetFolderId
        );
        $personalFolder = in_array($dataReceived['folderId'], $session->get('user-personal_folders')) ? 1 : 0;

        // Prepare some variables
        $targetFolderId = $dataReceived['folderId'];
        $targetFolderName = DB::queryFirstField(
            'SELECT title
            FROM '.prefixTable('nested_tree').'
            WHERE id = %i',
            $targetFolderId
        );

        // Get all folders from objects in DB
        if ($dataReceived['foldersNumber'] > 0) {
            $items = DB::query(
                'SELECT ii.label, ii.login, ii.pwd, ii.url, ii.description, ii.folder_id, ii.increment_id, nt.title
                FROM '.prefixTable('items_importations').' AS ii
                INNER JOIN '.prefixTable('nested_tree').' AS nt ON ii.folder_id = nt.id
                WHERE ii.operation_id = %i
                LIMIT %i, %i',
                $dataReceived['csvOperationId'],
                $dataReceived['offset'],
                $dataReceived['limit']
            );
        } else {
            $items = DB::query(
                'SELECT ii.label, ii.login, ii.pwd, ii.url, ii.description, ii.folder_id, ii.increment_id
                FROM '.prefixTable('items_importations').' AS ii
                WHERE ii.operation_id = %i
                LIMIT %i, %i',
                $dataReceived['csvOperationId'],
                $dataReceived['offset'],
                $dataReceived['limit']
            );
        }

        // Init some variables
        $insertedItems = $dataReceived['insertedItems'] ?? 0;
        $failedItems = [];
        
        // Loop on items
        foreach ($items as $item) {
            if (is_null($item['folder_id']) && is_null($targetFolderId)) {
                continue; // Skip if folder_id is null
            }
            try {
                $importId = $item['increment_id']; // Entry ID in items_importations

                // Handle case where password is empty
                if (($session->has('user-create_item_without_password') &&
                    null !== $session->get('user-create_item_without_password') &&
                    (int) $session->get('user-create_item_without_password') !== 1) ||
                    !empty($item['pwd'])) {
                    // Encrypt password
                    $cryptedStuff = doDataEncryption($item['pwd']);
                } else {
                    $cryptedStuff['encrypted'] = '';
                    $cryptedStuff['objectKey'] = '';
                }
                $itemPassword = $cryptedStuff['encrypted'];

                // Insert new item in table ITEMS
                DB::insert(
                    prefixTable('items'),
                    array(
                        'label' => substr($item['label'], 0, 500),
                        'description' => empty($item['description']) === true ? '' : $item['description'],
                        'pw' => $itemPassword,
                        'pw_iv' => '',
                        'url' => empty($item['url']) === true ? '' : substr($item['url'], 0, 500),
                        'id_tree' => is_null($item['folder_id']) === true ? $targetFolderId : (int) $item['folder_id'],
                        'login' => empty($item['login']) === true ? '' : substr($item['login'], 0, 200),
                        'anyone_can_modify' => $dataReceived['editAll'],
                        'encryption_type' => 'teampass_aes',
                        'item_key' => uniqidReal(50),
                        'created_at' => time(),
                    )
                );
                $newId = DB::insertId();

                // Create new task for the new item
                // If it is not a personnal one
                if ((int) $personalFolder === 0) {
                    if ($dataReceived['keysGenerationWithTasksHandler'] === 'tasksHandler') {
                        // Create task for the new item
                        storeTask(
                            'new_item',
                            $session->get('user-id'),
                            0,
                            (int) $item['folder_id'],
                            (int) $newId,
                            $cryptedStuff['objectKey'],
                        );
                    } else {
                        // Create sharekeys for current user
                        storeUsersShareKey(
                            prefixTable('sharekeys_items'),
                            (int) $item['folder_id'],
                            (int) $newId,
                            $cryptedStuff['objectKey'],
                            false
                        );
                    }
                } else {
                    // Create sharekeys for current user
                    storeUsersShareKey(
                        prefixTable('sharekeys_items'),
                        (int) $personalFolder,
                        (int) $newId,
                        $cryptedStuff['objectKey'],
                        true
                    );
                }

                //if asked, anyone in role can modify
                if ((int) $dataReceived['editRole'] === 1) {
                    foreach ($session->get('system-array_roles') as $role) {
                        DB::insert(
                            prefixTable('restriction_to_roles'),
                            array(
                                'role_id' => $role['id'],
                                'item_id' => $newId,
                            )
                        );
                    }
                }

                // Insert new item in table LOGS_ITEMS
                DB::insert(
                    prefixTable('log_items'),
                    array(
                        'id_item' => $newId,
                        'date' => time(),
                        'id_user' => $session->get('user-id'),
                        'action' => 'at_creation',
                    )
                );

                // Add item to cache table
                updateCacheTable('add_value', (int) $newId);

                // Update the importation entry with the importation date
                DB::update(prefixTable('items_importations'), [
                    'imported_at' => time(),
                ], "increment_id=%i", $importId);

                // Update the counter of inserted items
                $insertedItems++;

            } catch (Exception $e) {
                // Log the error and store the failed item
                $failedItems[] = [
                    'increment_id' => $item['increment_id'],
                    'error' => $e->getMessage(),
                ];

                error_log(
                    'SQL Error during import | increment_id: ' . $item['increment_id'] .
                    ' | Message: ' . $e->getMessage() .
                    ' | StackTrace: ' . $e->getTraceAsString()
                );
            }
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'insertedItems' => $insertedItems,
                'failedItems' => $failedItems,
            ),
            'encode'
        );
        break;

    case 'import_csv_items_finalization':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $csvOperationId = filter_var($receivedParameters['csvOperationId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Delete operation id
        DB::delete(
            prefixTable('items_importations'),
            'operation_id = %i',
            $csvOperationId
        );

        // Clean previous not imported items
        DB::delete(
            prefixTable('items_importations'),
            'imported_at IS NULL AND created_at < %s',
            date('Y-m-d')
        );

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
        break;


    //Check if import KEEPASS file format is what expected
    case 'import_file_format_keepass':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $post_operation_id = filter_var($receivedParameters['file'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $destinationFolderId = filter_var($receivedParameters['folder_id'], FILTER_SANITIZE_NUMBER_INT);

        // Get filename from database
        $data = DB::queryFirstRow(
            'SELECT valeur
            FROM '.prefixTable('misc').'
            WHERE increment_id = %i AND type = "temp_file"',
            $post_operation_id
        );

        // Delete operation id
        DB::delete(
            prefixTable('misc'),
            'increment_id = %i AND type = "temp_file"',
            $post_operation_id
        );

        // do some initializations
        $file = $data['valeur'];

        //read xml file
        if (file_exists($SETTINGS['path_to_files_folder'].'/'.$file)) {
            $xml = simplexml_load_file(
                $SETTINGS['path_to_files_folder'].'/'.$file
            );
        }

        // Convert XML to associative array
        $xmlfile = file_get_contents($SETTINGS['path_to_files_folder'].'/'.$file);
        $new = simplexml_load_string($xmlfile);
        $con = json_encode($new);
        $newArr = json_decode($con, true);


        /**
         * Recursive function to process the Keepass XML structure.
         *
         * @param array $array The current array to process.
         * @param string $previousFolder The parent folder ID.
         * @param array $newItemsToAdd The new items to add to the database.
         * @param int $level The current level of the recursion.
         *
         * @return array The new items to add to the database.
         */
        function recursive($array, $previousFolder, $newItemsToAdd, $level) : array
        {
            // Handle entries (items)
            if (isset($array['Entry']) && is_array($array['Entry'])) {
                $newItemsToAdd = handleEntries($array['Entry'], $previousFolder, $newItemsToAdd);
            }

            // Handle groups (folders)
            if (isset($array['Group']) && is_array($array['Group'])) {
                $newItemsToAdd = handleGroups($array['Group'], $previousFolder, $newItemsToAdd, $level);
            }

            return $newItemsToAdd;
        }

        /**
         * Handle entries (items) within the structure.
         * It processes each entry and adds it to the new items list.
         *
         * @param array $entries The entries to process.
         * @param string $previousFolder The parent folder ID.
         * @param array $newItemsToAdd The new items to add to the database.
         *
         * @return array The new items to add to the database.
         */
        function handleEntries(array $entries, string $previousFolder, array $newItemsToAdd) : array
        {
            foreach ($entries as $key => $value) {
                // Check if the entry has a 'String' field and process it
                if (isset($value['String'])) {
                    $newItemsToAdd['items'][] = buildItemDefinition($value['String'], $previousFolder);
                }
                // If it's a direct 'String' item, build a simple item
                elseif ($key === 'String') {
                    $newItemsToAdd['items'][] = buildSimpleItem($value, $previousFolder);
                }
            }

            return $newItemsToAdd;
        }

        /**
         * Build an item definition from the 'String' fields.
         * Converts the key-value pairs into a usable item format.
         *
         * @param array $strings The 'String' fields to process.
         * @param string $previousFolder The parent folder ID.
         *
         * @return array The item definition.
         */
        function buildItemDefinition(array $strings, string $previousFolder) : array
        {
            $itemDefinition = [];
            // Loop through each 'String' entry and map keys and values
            foreach ($strings as $entry) {
                $itemDefinition[$entry['Key']] = is_array($entry['Value']) ? '' : $entry['Value'];
            }

            // Set the parent folder and ensure default values for certain fields
            $itemDefinition['parentFolderId'] = $previousFolder;
            $itemDefinition['Notes'] = $itemDefinition['Notes'] ?? '';
            $itemDefinition['URL'] = $itemDefinition['URL'] ?? '';
            $itemDefinition['Password'] = base64_encode($itemDefinition['Password']) ?? '';

            return $itemDefinition;
        }

        /**
         * Build a simple item with predefined fields.
         * This is used when there is no associated key, just ordered values.
         *
         * @param array $value The ordered values to process.
         * @param string $previousFolder The parent folder ID.
         *
         * @return array The simple item definition.
         */
        function buildSimpleItem(array $value, string $previousFolder) : array
        {
            return [
                'Notes' => is_array($value[0]['Value']) ? '' : $value[0]['Value'],
                'Title' => is_array($value[2]['Value']) ? '' : $value[2]['Value'],
                'Password' => is_array($value[1]['Value']) ? '' : $value[1]['Value'],
                'URL' => is_array($value[3]['Value']) ? '' : $value[3]['Value'],
                'UserName' => is_array($value[4]['Value']) ? '' : $value[4]['Value'],
                'parentFolderId' => $previousFolder,
            ];
        }

        /**
         * Handle groups (folders) within the structure.
         * It processes each group and recursively goes deeper into subgroups and subentries.
         *
         * @param array $groups The groups to process.
         * @param string $previousFolder The parent folder ID.
         * @param array $newItemsToAdd The new items to add to the database.
         *
         * @return array The new items to add to the database.
         */
        function handleGroups($groups, string $previousFolder, array $newItemsToAdd, int $level) : array
        {
            // If a single group is found, wrap it into an array
            if (isset($groups['UUID'])) {
                $groups = [$groups];
            }

            foreach ($groups as $group) {
                // Add the current group (folder) to the list
                $newItemsToAdd['folders'][] = [
                    'folderName' => $group['Name'],
                    'uuid' => $group['UUID'],
                    'parentFolderId' => $previousFolder,
                    'level' => $level,
                ];

                // Recursively process entries and subgroups inside this group
                $newItemsToAdd = recursive(
                    [
                        'Entry' => $group['Entry'] ?? '',
                        'Group' => $group['Group'] ?? '',
                    ],
                    $group['UUID'],
                    $newItemsToAdd,
                    $level + 1
                );
            }

            return $newItemsToAdd;
        }

        // Start the recursive processing
        $ret = recursive(
            array_merge(
                ['Entry' => $newArr['Root']['Group']['Entry']],
                ['Group' => $newArr['Root']['Group']['Group']],
            ),
            $destinationFolderId,
            [
                'folders' => [],
                'items' => []
            ],
            1,
        );

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'data' => $ret,
            ),
            'encode'
        );

        break;

    // KEEPASS - CREATE FOLDERS
    case 'keepass_create_folders':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        $destinationFolderId = filter_var($receivedParameters['folder-id'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['editAll'] = filter_var($receivedParameters['edit-all'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['editRole'] = filter_var($receivedParameters['edit-role'], FILTER_SANITIZE_NUMBER_INT);
        $post_folders = filter_var_array(
            $receivedParameters['folders'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        // get destination folder informations
        $destinationFolderInfos = getFolderComplexity((int) $destinationFolderId, $session->get('user-personal_folders'));
        $arrFolders[$destinationFolderId] = [
            'id' => (int) $destinationFolderId,
            'level' => 1,
            'isPF' => false,
        ];
        $startPathLevel = 1;

        foreach($post_folders as $folder) {
            // get parent id
            if (!isset($arrFolders[$folder['parentFolderId']])) {
                // If parent folder is not in the array, it means it is the destination folder
                $parentId = $destinationFolderId;
            } else {
                $parentId = $arrFolders[$folder['parentFolderId']]['id'];
            }

            // create folder in DB
            $folderId = createFolder(
                $folder['folderName'],
                is_null($parentId) ? 0 : (int) $parentId,
                $folder['level'],
                $startPathLevel,
                $destinationFolderInfos['levelPwComplexity']
            );

            // manage parent
            $arrFolders[$folder['uuid']] = [
                'id' => (int) $folderId,
                'level' => (int) ($folder['level'] + $startPathLevel),
                'isPF' => $destinationFolderInfos['importPF'],
            ];
        }
        //rebuild full tree
        $tree->rebuild();


        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'folders' => $arrFolders,
            ),
            'encode'
        );

        break;

    // KEEPASS - CREATE ITEMS
    case 'keepass_create_items':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        $$inputData['editAll'] = filter_var($receivedParameters['edit-all'], FILTER_SANITIZE_NUMBER_INT);
        $$inputData['editRole'] = filter_var($receivedParameters['edit-role'], FILTER_SANITIZE_NUMBER_INT);
        $post_folders = filter_var_array(
            $receivedParameters['folders'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $post_items = filter_var_array(
            $receivedParameters['items'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $returnedItems = array();

        // Start transaction for better performance
        DB::startTransaction();

        // Import all items
        foreach($post_items as $item) {
            // get info about this folder
            $destinationFolderMore = DB::queryFirstRow(
                'SELECT title FROM '.prefixTable('nested_tree').' WHERE id = %i',
                (int) $post_folders[$item['parentFolderId']]['id']
            );

            // Handle case where pw is empty
            // if not allowed then warn user
            if (($session->has('user-create_item_without_password') && null !== $session->get('user-create_item_without_password')
                && (int) $session->get('user-create_item_without_password') !== 1
                ) ||
                empty($item['Password']) === false
            ) {
                // NEW ENCRYPTION
                $itemPassword = base64_decode($item['Password']);
                $cryptedStuff = doDataEncryption($itemPassword);
            } else {
                $cryptedStuff['encrypted'] = '';
                $cryptedStuff['objectKey'] = '';
            }
            $post_password = $cryptedStuff['encrypted'];

            //ADD item
            DB::insert(
                prefixTable('items'),
                array(
                    'label' => substr($item['Title'], 0, 500),
                    'description' => $item['Notes'],
                    'pw' => $cryptedStuff['encrypted'],
                    'pw_iv' => '',
                    'url' => substr($item['URL'], 0, 500),
                    'id_tree' => isset($post_folders[$item['parentFolderId']]['id']) ? (int)$post_folders[$item['parentFolderId']]['id'] : 0,
                    'login' => substr($item['UserName'], 0, 500),
                    'anyone_can_modify' => $$inputData['editAll'],
                    'encryption_type' => 'teampass_aes',
                    'inactif' => 0,
                    'restricted_to' => '',
                    'perso' => $post_folders[$item['parentFolderId']]['isPF'] === true ? 1 : 0,
                    'item_key' => uniqidReal(50),
                    'created_at' => time(),
                )
            );
            $newId = DB::insertId();

            // Create sharekeys for users
            storeUsersShareKey(
                prefixTable('sharekeys_items'),
                $post_folders[$item['parentFolderId']]['isPF'] === true ? 1 : 0,
                (int) $newId,
                $cryptedStuff['objectKey'],
            );

            //if asked, anyone in role can modify
            if ($$inputData['editRole'] === 1) {
                foreach ($session->get('system-array_roles') as $role) {
                    DB::insert(
                        prefixTable('restriction_to_roles'),
                        array(
                            'role_id' => $role['id'],
                            'item_id' => $newId,
                        )
                    );
                }
            }

            //Add log
            DB::insert(
                prefixTable('log_items'),
                array(
                    'id_item' => $newId,
                    'date' => time(),
                    'id_user' => $session->get('user-id'),
                    'action' => 'at_creation',
                    'raison' => 'at_import',
                )
            );

            // Add item to cache table
            updateCacheTable('add_value', (int) $newId);

            // prepare return
            $returnedItems[] = array(
                'title' => substr(stripslashes($item['Title']), 0, 500),
                'folder' => $destinationFolderMore['title']
            );
        }

        // Commit transaction.
        DB::commit();

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'items' => $returnedItems,
            ),
            'encode'
        );

        break;
    }



/**
 * Create folders during importation
 *
 * @param string $folderTitle
 * @param integer $parentId
 * @param integer $folderLevel
 * @param integer $startPathLevel
 * @param integer $levelPwComplexity
 * @return integer
 */
function createFolder($folderTitle, $parentId, $folderLevel, $startPathLevel, $levelPwComplexity)
{
    $session = SessionManager::getSession();

    // Is parent folder a personal folder?
    $isPersonalFolder = in_array($parentId, $session->get('user-personal_folders')) ? 1 : 0;

    //create folder - if not exists at the same level
    DB::query(
        'SELECT * FROM '.prefixTable('nested_tree').'
        WHERE nlevel = %i AND title = %s AND parent_id = %i LIMIT 1',
        intval($folderLevel + $startPathLevel),
        $folderTitle,
        $parentId
    );
    if (DB::count() === 0) {
        //do query
        DB::insert(
            prefixTable('nested_tree'),
            array(
                'parent_id' => (int) $parentId,
                'title' => (string) html_entity_decode(stripslashes($folderTitle), ENT_QUOTES, 'UTF-8'),
                'nlevel' => (int) $folderLevel,
                'categories' => '',
            )
        );
        $id = DB::insertId();
        //Add complexity level => level is set to "medium" by default.
        DB::insert(
            prefixTable('misc'),
            array(
                'type' => 'complex',
                'intitule' => (int) $id,
                'valeur' => (int) $levelPwComplexity,
                'created_at' => time(),
            )
        );

        // Indicate that a change has been done to force tree user reload
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => time(),
                'updated_at' => time(),
            ),
            'type = %s AND intitule = %s',
            'timestamp',
            'last_folder_change'
        );

        //For each role to which the user depends on, add the folder just created.
        // (if not personal, otherwise, add to user-personal_folders)
        if ( $isPersonalFolder ) {
            SessionManager::addRemoveFromSessionArray('user-personal_folders', [$id], 'add');
        } else {
            foreach ($session->get('system-array_roles') as $role) {
                DB::insert(
                    prefixTable('roles_values'),
                    array(
                        'role_id' => $role['id'],
                        'folder_id' => $id,
                        'type' => 'W',
                    )
                );
            }
        }

        //Add this new folder to the list of visible folders for the user.
        $session->set('user-accessible_folders', array_unique(array_merge($session->get('user-accessible_folders'), [$id]), SORT_NUMERIC));

        return $id;
    }

    //get folder actual ID
    $data = DB::queryFirstRow(
        'SELECT id FROM '.prefixTable('nested_tree').'
        WHERE nlevel = %i AND title = %s AND parent_id = %i',
        intval($folderLevel + $startPathLevel),
        $folderTitle,
        $parentId
    );
    return $data['id'];
}

/**
 * getFolderComplexity
 *
 * @param int $folderId
 * @param boolean $isFolderPF
 *
 * @return array
*/
function getFolderComplexity($folderId, $isFolderPF)
{
    // If destination is not ROOT then get the complexity level
    if ($isFolderPF === true) {
        return [
            'levelPwComplexity' => 50,
            'startPathLevel' => 1,
            'importPF' => true
        ];
    } elseif ($folderId > 0) {
        $data = DB::queryFirstRow(
            'SELECT m.valeur as value, t.nlevel as nlevel
            FROM '.prefixTable('misc').' as m
            INNER JOIN '.prefixTable('nested_tree').' as t ON (m.intitule = t.id)
            WHERE m.type = %s AND m.intitule = %s',
            'complex',
            $folderId
        );
        return [
            'levelPwComplexity' => $data['value'],
            'startPathLevel' => $data['nlevel'],
            'importPF' => false
        ];
    }
    return [
        'levelPwComplexity' => 50,
        'startPathLevel' => 0,
        'importPF' => false
    ];
}

spl_autoload_register(function ($class) {
    $prefix = 'League\\Csv\\';
    $base_dir = __DIR__.'/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir.str_replace('\\', '/', $relative_class).'.php';
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Used to format the string ready for insertion in to the database.
 *
 * @param string $str             String to clean
 * @param string $crLFReplacement Replacement
 *
 * @return string
 */
function sanitiseString($str, $crLFReplacement)
{
    $str = preg_replace('#[\r\n]#', $crLFReplacement, (string) $str);
    $str = str_replace('\\', '&#92;', $str);
    $str = str_replace('"', '&quot;', $str);
    if (!empty($str)) {
        addslashes($str);
    }

    return $str;
}

/**
 * Clean array values.
 *
 * @param string $value String to clean
 *
 * @return string
 */
function cleanOutput(&$value)
{
    return htmlspecialchars_decode($value);
}

/**
 * Clean a string.
 *
 * @param string $value The string to clean.
 *
 * @return string The cleaned string.
 */
function cleanInput($value): string
{
    return stripEmojis(
        cleanString(
            html_entity_decode($value, ENT_QUOTES | ENT_XHTML, 'UTF-8'),
            true
        )
    );
}

/**
 * Strip any emoji icons from the string.
 *
 * @param string $string
 *   The string to remove emojis from.
 *
 * @return string
 *   The string with emojis removed.
 */
function stripEmojis($string): string
{
    // Convert question marks to a special thing so that we can remove
    // question marks later without any problems.
    $string = str_replace("?", "{%}", $string);
    // Convert the text into UTF-8.
    $string = mb_convert_encoding($string, "ISO-8859-1", "UTF-8");
    // Convert the text to ASCII.
    $string = mb_convert_encoding($string, "UTF-8", "ISO-8859-1");
    // Replace anything that is a question mark (left over from the conversion.
    $string = preg_replace('/(\s?\?\s?)/', ' ', $string);
    // Put back the .
    $string = str_replace("{%}", "?", $string);
    // Trim and return.
    return trim($string);
  }
