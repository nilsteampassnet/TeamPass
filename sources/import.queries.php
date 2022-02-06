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
 * @file      import.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) === false) {
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

// No time limit
set_time_limit(0);

// Set some constants for program readability
define('KP_PATH', 0);
define('KP_GROUP', 1);
define('KP_TITLE', 2);
define('KP_PASSWORD', 3);
define('KP_USERNAME', 4);
define('KP_URL', 5);
define('KP_UUID', 6);
define('KP_NOTES', 7);

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

// Class loader
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

//User's language loading
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

// POST Varaibles
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

// Build query
switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
    //Check if import CSV file format is what expected
    case 'import_file_format_csv':
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
            // load libraries
            include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Goodby/CSV/Import/Standard/Lexer.php';
            include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Goodby/CSV/Import/Standard/Interpreter.php';
            include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Goodby/CSV/Import/Standard/LexerConfig.php';

            // Lexer configuration
            $config = new LexerConfig();
            $lexer = new Lexer($config);
            $config->setIgnoreHeaderLine('true');
            // extract data from CSV file
            $interpreter = new Interpreter();
            $interpreter->addObserver(function (array $row) use (&$valuesToImport) {
                $valuesToImport[] = array(
                    'Label' => $row[0],
                    'Login' => $row[1],
                    'Password' => $row[2],
                    'url' => $row[3],
                    'Comments' => $row[4],
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
                $row['Comments'] = str_replace(array("\r\n", "\n", "\r"), '<br>', $row['Comments']);

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
                                    'label' => $account,
                                    'label' => $account,
                                )
                            );

                            // Initialize this variable in order to restart from scratch
                            $account = '';
                        }
                    }
                }

                // Get values of current line
                if ($account === '' && $continue_on_next_line === false) {
                    $account = trim(htmlspecialchars($row['Label'], ENT_QUOTES, 'UTF-8'));
                    $login = trim(htmlspecialchars($row['Login'], ENT_QUOTES, 'UTF-8'));
                    $pwd = trim(str_replace('"', '&quot;', $row['Password']));
                    $url = trim($row['url']);
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
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => true,
                    'message' => langHdl('cannot_open_file'),
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
                    'label' => $account,
                    'label' => $account,
                )
            );

            // Show results to user.
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
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

        // Init
        $list = [];

        // Decrypt and retreive data in JSON format
        $post_items = prepareExchangedData(
            $SETTINGS['cpassman_dir'],
            $post_data,
            'decode'
        );

        // Init post variable
        $post_folder = filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_NUMBER_INT);

        // Get title for this folder
        $data_fld = DB::queryFirstRow(
            'SELECT title
            FROM '.prefixTable('nested_tree').'
            WHERE id = %i',
            $post_folder
        );

        //Get some info about personal folder
        if (in_array($post_folder, $_SESSION['personal_folders']) === true) {
            $personalFolder = 1;
        } else {
            $personalFolder = 0;
        }

        //Prepare variables
        //$listItems = json_decode($post_items, true);

        // Clean each array entry
        if (is_array($post_items) === true) {
            array_walk_recursive($post_items, 'cleanOutput');
        }

        // Loop on array
        foreach ($post_items as $item) {
            //For each item, insert into DB

            // Handle case where pw is empty
            // if not allowed then warn user
            if ((isset($_SESSION['user']['create_item_without_password']) === true
                && (int) $_SESSION['user']['create_item_without_password'] !== 1
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
                    'description' => empty($item['description']) === true ? '' : $item['description'],
                    'pw' => $post_password,
                    'pw_iv' => '',
                    'url' => empty($item['url']) === true ? '' : substr($item['url'], 0, 500),
                    'id_tree' => filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_NUMBER_INT),
                    'login' => empty($item['login']) === true ? '' : substr($item['login'], 0, 200),
                    'anyone_can_modify' => filter_input(INPUT_POST, 'import_csv_anyone_can_modify', FILTER_SANITIZE_STRING) === 'true' ? 1 : 0,
                    'encryption_type' => 'teampass_aes',
                )
            );
            $newId = DB::insertId();

            // Create sharekeys for users
            storeUsersShareKey(
                prefixTable('sharekeys_items'),
                (int) $personalFolder,
                (int) filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_NUMBER_INT),
                (int) $newId,
                $cryptedStuff['objectKey'],
                $SETTINGS
            );

            //if asked, anyone in role can modify
            if (null !== filter_input(INPUT_POST, 'import_csv_anyone_can_modify_in_role', FILTER_SANITIZE_STRING)
                && filter_input(INPUT_POST, 'import_csv_anyone_can_modify_in_role', FILTER_SANITIZE_STRING) === 'true'
            ) {
                foreach ($_SESSION['arr_roles'] as $role) {
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
                    'id_user' => $_SESSION['user_id'],
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
                    'description' => empty($item['description']) ? '' : $item['description'],
                    'id_tree' => filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_NUMBER_INT),
                    'url' => '0',
                    'perso' => $personalFolder === 0 ? 0 : 1,
                    'login' => empty($item['login']) ? '' : substr($item['login'], 0, 500),
                    'folder' => $data_fld['title'],
                    'author' => $_SESSION['user_id'],
                    'timestamp' => time(),
                    'tags' => '',
                    'restricted_to' => '0',
                    'renewal_period' => '0',
                    'timestamp' => time(),
                )
            );
        }

        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
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

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $SETTINGS['cpassman_dir'],$post_data,
            'decode'
        );
        $post_operation_id = filter_var($receivedParameters['file'], FILTER_SANITIZE_STRING);
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
         * Undocumented function
         *
         * @param array $array
         * @param integer $previousFolder
         * @param array $newItemsToAdd
         * @param integer $level
         * @return array
         */
        function recursive($array, $previousFolder, $newItemsToAdd, $level) : array
        {
            // Manage entries
            if (isset($array['Entry']) === true) {
                foreach($array['Entry'] as $key => $value) {
                    if (isset($value['String']) === true) {
                        $itemDefinition = [];
                        for($i = 0; $i < count($value['String']); ++$i) {
                            $itemDefinition[$value['String'][$i]['Key']] = is_array($value['String'][$i]['Value']) === false ? $value['String'][$i]['Value'] : '';
                        }
                        $itemDefinition['parentFolderId'] = $previousFolder;
                        isset($itemDefinition['Notes']) === false ? $itemDefinition['Notes'] = '' : '';
                        isset($itemDefinition['URL']) === false ? $itemDefinition['URL'] = '' : '';
                        isset($itemDefinition['Password']) === false ? $itemDefinition['Password'] = '' : '';
                        array_push(
                            $newItemsToAdd['items'],
                            $itemDefinition
                        );
                        continue;
                    }
                    
                    if ($key === "String") {
                        array_push(
                            $newItemsToAdd['items'],
                            [
                                'Notes' => is_array($value[0]['Value']) === false ? $value[0]['Value'] : '',
                                'Title' => is_array($value[2]['Value']) === false ? $value[2]['Value'] : '',
                                'Password' => is_array($value[1]['Value']) === false ? $value[1]['Value'] : '',
                                'URL' => is_array($value[3]['Value']) === false ? $value[3]['Value'] : '',
                                'UserName' => is_array($value[4]['Value']) === false ? $value[4]['Value'] : '',
                                'parentFolderId' => $previousFolder,
                            ]
                        );
                    }
                }
            }

            // Manage GROUPS
            if (isset($array['Group']) === true && is_array($array['Group'])=== true) {
                $currentFolderId = $previousFolder;
                if (isset($array['Group']['UUID']) === true) {
                    // build expect array format
                    $array['Group'] = [$array['Group']];
                }
                foreach($array['Group'] as $key => $value){
                    // Add this new folder
                    array_push(
                        $newItemsToAdd['folders'],
                        [
                            'folderName' => $value['Name'],
                            'uuid' => $value['UUID'],
                            'parentFolderId' => $previousFolder,
                            'level' => $level,
                        ]
                    );
                    $previousFolder = $value['UUID'];
                    
                    if (isset($value['Entry']) === true) {
                        // recursive inside this entry
                        $newItemsToAdd = recursive(
                            array_merge(
                                ['Entry' => $value['Entry']],
                                ['Group' => isset($value['Group']) === true ? $value['Group'] : ''],
                            ),
                            $previousFolder,
                            $newItemsToAdd,
                            $level + 1
                        );
                    }
                    $previousFolder = $currentFolderId;
                }
            }
            
            return $newItemsToAdd;
        }
        
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
            $SETTINGS['cpassman_dir'],
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

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $SETTINGS['cpassman_dir'],$post_data,
            'decode'
        );

        $post_folder_id = filter_var($receivedParameters['folder-id'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_all = filter_var($receivedParameters['edit-all'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_role = filter_var($receivedParameters['edit-role'], FILTER_SANITIZE_NUMBER_INT);
        $post_folders = filter_var_array(
            $receivedParameters['folders'],
            FILTER_SANITIZE_STRING
        );

        // get destination folder informations
        $destinationFolderInfos = getFolderComplexity($post_folder_id, $_SESSION['personal_folders']);
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
            $SETTINGS['cpassman_dir'],
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

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData(
            $SETTINGS['cpassman_dir'],$post_data,
            'decode'
        );

        $post_edit_all = filter_var($receivedParameters['edit-all'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_role = filter_var($receivedParameters['edit-role'], FILTER_SANITIZE_NUMBER_INT);
        $post_folders = filter_var_array(
            $receivedParameters['folders'],
            FILTER_SANITIZE_STRING
        );
        $post_items = filter_var_array(
            $receivedParameters['items'],
            FILTER_SANITIZE_STRING
        );
        $ret = '';

        foreach($post_items as $item) {
            // get info about this folder
            $destinationFolderMore = DB::queryFirstRow(
                'SELECT title FROM '.prefixTable('nested_tree').' WHERE id = %i',
                (int) $post_folders[$item['parentFolderId']]['id']
            );

            // Handle case where pw is empty
            // if not allowed then warn user
            if ((isset($_SESSION['user']['create_item_without_password']) === true
                && (int) $_SESSION['user']['create_item_without_password'] !== 1
                ) ||
                empty($item['Password']) === false
            ) {
                // NEW ENCRYPTION
                $cryptedStuff = doDataEncryption($item['Password']);
            } else {
                $cryptedStuff['encrypted'] = '';
            }
            $post_password = $cryptedStuff['encrypted'];

            //ADD item
            DB::insert(
                prefixTable('items'),
                array(
                    'label' => substr(stripslashes($item['Title']), 0, 500),
                    'description' => stripslashes($item['Notes']),
                    'pw' => $cryptedStuff['encrypted'],
                    'pw_iv' => '',
                    'url' => substr(stripslashes($item['URL']), 0, 500),
                    'id_tree' => $post_folders[$item['parentFolderId']]['id'],
                    'login' => substr(stripslashes($item['UserName']), 0, 500),
                    'anyone_can_modify' => $post_edit_all,
                    'encryption_type' => 'teampass_aes',
                    'inactif' => 0,
                    'restricted_to' => '',
                    'perso' => $post_folders[$item['parentFolderId']]['isPF'] === true ? 1 : 0,
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
                $SETTINGS
            );

            //if asked, anyone in role can modify
            if ($post_edit_role === 1) {
                foreach ($_SESSION['arr_roles'] as $role) {
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
                    'id_user' => $_SESSION['user_id'],
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
                    'author' => $_SESSION['user_id'],
                    'renewal_period' => '0',
                    'timestamp' => time(),
                )
            );

            // prepare return
            $ret .= "<li>".substr(stripslashes($item['Title']), 0, 500)." [".$destinationFolderMore['title']."]</li>";
        }


        echo prepareExchangedData(
            $SETTINGS['cpassman_dir'],
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
            )
        );

        //For each role to which the user depends on, add the folder just created.
        foreach ($_SESSION['arr_roles'] as $role) {
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
        array_push($_SESSION['groupes_visibles'], $id);

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
