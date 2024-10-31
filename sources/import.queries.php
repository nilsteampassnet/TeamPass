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
 * @copyright 2009-2024 Teampass.net
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
    $checkUserAccess->userAccessPage('import') === false ||
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


$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// POST Varaibles
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

// Build query
switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
    //Check if import CSV file format is what expected
    case 'import_file_format_csv':
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

        //load full tree
        $tree->rebuild();
        $tree = $tree->getDescendants();

        // Init post variable
        $post_operation_id = filter_input(INPUT_POST, 'file', FILTER_SANITIZE_NUMBER_INT);

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

        // do some initializations
        $file = $SETTINGS['path_to_files_folder'].'/'.$data['valeur'];
        $importation_possible = true;
        $itemsArray = array();
        $line_number = 0;
        $account = $text = '';
        $continue_on_next_line = false;

        // Open file
        if ($fp = fopen($file, 'r')) {
            // data from CSV
            $valuesToImport = array();
            $header = fgetcsv($fp);
            // Lexer configuration
            $config = new LexerConfig();
            $lexer = new Lexer($config);
            $config->setIgnoreHeaderLine('true');
            $interpreter = new Interpreter();
            $interpreter->addObserver(function (array $row) use (&$valuesToImport,$header) {
                $rowData = array_combine($header, $row);
                $valuesToImport[] = array(
                    'Label' => $rowData['label'],
                    'Login' => $rowData['login'],
                    'Password' => $rowData['password'],
                    'url' => $rowData['url'],
                    'Comments' => $rowData['description'],
                );
            });
            $lexer->parse($file, $interpreter);
            
            // extract one line
            foreach ($valuesToImport as $key => $row) {
                //increment number of lines found
                ++$line_number;

                //Check number of fields. MUST be 5. if not stop importation
                if (count($row) != 5) {
                    $importation_possible = false;
                    //Stop if file has not expected structure
                    if ($importation_possible === false) {
                        echo '[{"error":"bad_structure"}]';
                        break;
                    }
                }

                //If any comment is on several lines, then replace 'lf' character
                $row['Comments'] = isset($row['Comments']) ? str_replace(array("\r\n", "\n", "\r"), '<br>', $row['Comments']) : '';

                // Check if current line contains a "<br>" character in order to identify an ITEM on several CSV lines
                if (substr_count($row['Comments'], '<br>') > 0 || substr_count($row['Label'], '<br>') > 0) {
                    $continue_on_next_line = true;
                    $comment .= addslashes($row['Label']);
                } else {
                    // Store in variable values from previous line
                    if (empty($account) === false) {
                        if ($continue_on_next_line === false) {
                            // Prepare listing that will be shown to user
                            array_push(
                                $itemsArray,
                                array(
                                    'label' => $account,
                                    'login' => $login,
                                    'pwd' => $pwd,
                                    'url' => $url,
                                    'comment' => $comment,
                                )
                            );

                            // Initialize this variable in order to restart from scratch
                            $account = '';
                        }
                    }
                }
                
                // Get values of current line
                if ($account === '' && $continue_on_next_line === false) {
                    $account = isset($row['Label']) && is_string($row['Label']) ? trim(htmlspecialchars($row['Label'], ENT_QUOTES, 'UTF-8')) : '';
                    $login = isset($row['Login']) && is_string($row['Login']) ? trim(htmlspecialchars($row['Login'], ENT_QUOTES, 'UTF-8')) : '';
                    $pwd = isset($row['Password']) && is_string($row['Password']) ? trim(str_replace('"', '&quot;', $row['Password'])) : '';
                    $url = isset($row['url']) && is_string($row['url']) ? trim($row['url']) : '';
                    $to_find = array('"', "'");
                    $to_ins = array('&quot', '&#39;');
                    $comment = htmlentities(
                        addslashes(str_replace($to_find, $to_ins, $row['Comments'])),
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $continue_on_next_line = false;
                }
            }
            // close file
            fclose($fp);
        } else {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('cannot_open_file'),
                ),
                'encode'
            );

            //delete file
            unlink($file);
            break;
        }

        if ($line_number > 0) {
            array_push(
                $itemsArray,
                array(
                    'label' => $account,
                    'login' => $login,
                    'pwd' => $pwd,
                    'url' => $url,
                    'comment' => $comment,
                )
            );

            // Show results to user.
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'output' => $itemsArray,
                    'number' => $line_number++,
                ),
                'encode'
            );
        }

        //delete file
        unlink($file);

        break;

    //Insert into DB the items the user has selected
    case 'import_items':
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

        // Init
        $list = [];

        // Decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $post_data,
            'decode'
        );

        // Init post variable
        $post_folder = filter_var($dataReceived['folder-id'], FILTER_SANITIZE_NUMBER_INT);

        // Clean each array entry and exclude password as it will be hashed
        $post_items = [];
        foreach ($dataReceived['items'] as $item) {
            $filtered_item = [];
            foreach ($item as $key => $value) {
                if ($key !== 'pwd') {
                    $value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                $filtered_item[$key] = $value;
            }
            $post_items[] = $filtered_item;
        }

        $post_edit_role = filter_var($dataReceived['edit-role'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_all = filter_var($dataReceived['edit-all'], FILTER_SANITIZE_NUMBER_INT);

        // Get title for this folder
        $data_fld = DB::queryFirstRow(
            'SELECT title
            FROM '.prefixTable('nested_tree').'
            WHERE id = %i',
            $post_folder
        );

        //Get some info about personal folder
        if (in_array($post_folder, $session->get('user-personal_folders')) === true) {
            $personalFolder = 1;
        } else {
            $personalFolder = 0;
        }

        // Clean each array entry
        array_walk_recursive($post_items, 'cleanOutput');
        
        // Loop on array
        foreach ($post_items as $item) {
            //For each item, insert into DB

            // Handle case where pw is empty
            // if not allowed then warn user
            if (($session->has('user-create_item_without_password') && null !== $session->get('user-create_item_without_password')
                && (int) $session->get('user-create_item_without_password') !== 1
                ) ||
                empty($item['pwd']) === false
            ) {
                // NEW ENCRYPTION
                $cryptedStuff = doDataEncryption($item['pwd']);
            } else {
                $cryptedStuff['encrypted'] = '';
            }
            $post_password = $cryptedStuff['encrypted'];
            
            // Insert new item in table ITEMS
            DB::insert(
                prefixTable('items'),
                array(
                    'label' => substr($item['label'], 0, 500),
                    'description' => empty($item['comment']) === true ? '' : $item['comment'],
                    'pw' => $post_password,
                    'pw_iv' => '',
                    'url' => empty($item['url']) === true ? '' : substr($item['url'], 0, 500),
                    'id_tree' => $post_folder,
                    'login' => empty($item['login']) === true ? '' : substr($item['login'], 0, 200),
                    'anyone_can_modify' => $post_edit_all,
                    'encryption_type' => 'teampass_aes',
                    'item_key' => uniqidReal(50),
                    'created_at' => time(),
                )
            );
            $newId = DB::insertId();

            // Create sharekeys for users
            storeUsersShareKey(
                prefixTable('sharekeys_items'),
                (int) $personalFolder,
                (int) $post_folder,
                (int) $newId,
                $cryptedStuff['objectKey'],
            );

            //if asked, anyone in role can modify
            if ((int) $post_edit_role === 1) {
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

            array_push($list, $item['row']);

            //Add entry to cache table
            DB::insert(
                prefixTable('cache'),
                array(
                    'id' => $newId,
                    'label' => substr($item['label'], 0, 500),
                    'description' => empty($item['comment']) ? '' : $item['comment'],
                    'id_tree' => $post_folder,
                    'url' => '0',
                    'perso' => $personalFolder === 0 ? 0 : 1,
                    'login' => empty($item['login']) ? '' : substr($item['login'], 0, 500),
                    'folder' => $data_fld['title'],
                    'author' => $session->get('user-id'),
                    'timestamp' => time(),
                    'tags' => '',
                    'restricted_to' => '0',
                    'renewal_period' => '0',
                    'timestamp' => time(),
                )
            );
        }

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'items' => $list,
            ),
            'encode'
        );
        break;

    //Check if import KEEPASS file format is what expected
    case 'import_file_format_keepass':
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

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $post_data,
            'decode'
        );
        $post_operation_id = filter_var($receivedParameters['file'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_folder_id = filter_var($receivedParameters['folder-id'], FILTER_SANITIZE_NUMBER_INT);

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
            if (isset($array['Entry'])) {
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
            $itemDefinition['Password'] = $itemDefinition['Password'] ?? '';

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
            $post_folder_id,
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

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $post_data,
            'decode'
        );

        $post_folder_id = filter_var($receivedParameters['folder-id'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_all = filter_var($receivedParameters['edit-all'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_role = filter_var($receivedParameters['edit-role'], FILTER_SANITIZE_NUMBER_INT);
        $post_folders = filter_var_array(
            $receivedParameters['folders'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        // get destination folder informations
        $destinationFolderInfos = getFolderComplexity($post_folder_id, $session->get('user-personal_folders'));
        $arrFolders[$post_folder_id] = [
            'id' => (int) $post_folder_id,
            'level' => 1,
            'isPF' => false,
        ];
        $startPathLevel = 1;

        foreach($post_folders as $folder) {
            // get parent id
            $parentId = $arrFolders[$folder['parentFolderId']];

            // create folder in DB
            $folderId = createFolder(
                $folder['folderName'],
                $parentId['id'],
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

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $post_data,
            'decode'
        );

        $post_edit_all = filter_var($receivedParameters['edit-all'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_role = filter_var($receivedParameters['edit-role'], FILTER_SANITIZE_NUMBER_INT);
        $post_folders = filter_var_array(
            $receivedParameters['folders'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $post_items = filter_var_array(
            $receivedParameters['items'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $ret = '';

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
                $cryptedStuff = doDataEncryption($item['Password']);
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
                    'id_tree' => $post_folders[$item['parentFolderId']]['id'],
                    'login' => substr($item['UserName'], 0, 500),
                    'anyone_can_modify' => $post_edit_all,
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
                (int) $post_folders[$item['parentFolderId']]['id'],
                (int) $newId,
                $cryptedStuff['objectKey'],
            );

            //if asked, anyone in role can modify
            if ($post_edit_role === 1) {
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

            //Add entry to cache table
            DB::insert(
                prefixTable('cache'),
                array(
                    'id' => $newId,
                    'label' => substr(stripslashes($item['Title']), 0, 500),
                    'description' => stripslashes($item['Notes']),
                    'url' => substr(stripslashes($item['URL']), 0, 500),
                    'tags' => '',
                    'id_tree' => $post_folders[$item['parentFolderId']]['id'],
                    'perso' => $post_folders[$item['parentFolderId']]['isPF'] === 0 ? 0 : 1,
                    'login' => substr(stripslashes($item['UserName']), 0, 500),
                    'restricted_to' => '0',
                    'folder' => $destinationFolderMore['title'],
                    'author' => $session->get('user-id'),
                    'renewal_period' => '0',
                    'timestamp' => time(),
                )
            );

            // prepare return
            $ret .= "<li>".substr(stripslashes($item['Title']), 0, 500)." [".$destinationFolderMore['title']."]</li>";
        }

        // Commit transaction.
        DB::commit();

        echo prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
                'info' => "<ul>".$ret."</ul>",
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
                'parent_id' => $parentId,
                'title' => stripslashes($folderTitle),
                'nlevel' => $folderLevel,
                'categories' => '',
            )
        );
        $id = DB::insertId();
        //Add complexity level => level is set to "medium" by default.
        DB::insert(
            prefixTable('misc'),
            array(
                'type' => 'complex',
                'intitule' => $id,
                'valeur' => $levelPwComplexity,
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
