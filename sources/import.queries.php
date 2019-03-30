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
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
//$link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWD_CLEAR, DB_NAME, DB_PORT);
//$link->set_charset(DB_ENCODING);

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
        $size = 4096;
        $separator = ',';
        $enclosure = '"';
        $fields_expected = array('Label', 'Login', 'Password', 'URL', 'Comments'); //requiered fields from CSV
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
                if (substr_count('<br>', $row['Comments']) > 0 || substr_count('<br>', $row['Label']) > 0) {
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
                if ($account == '' && $continue_on_next_line === false) {
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
        $post_items = prepareExchangedData($post_data, 'decode');

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
        array_walk_recursive($post_items, 'cleanOutput');

        // Loop on array
        foreach ($post_items as $item) {
            //For each item, insert into DB

            //Encryption key
            if ($personalFolder == 1) {
                $encrypt = cryption(
                    $item['pwd'],
                    $_SESSION['user_settings']['session_psk'],
                    'encrypt',
                    $SETTINGS
                );
            } else {
                $encrypt = cryption(
                    $item['pwd'],
                    '',
                    'encrypt',
                    $SETTINGS
                );
            }

            // Insert new item in table ITEMS
            DB::insert(
                prefixTable('items'),
                array(
                    'label' => substr($item['label'], 0, 500),
                    'description' => empty($item['description']) === true ? '' : $item['description'],
                    'pw' => $encrypt['string'],
                    'pw_iv' => '',
                    'url' => empty($item['url']) === true ? '' : substr($item['url'], 0, 500),
                    'id_tree' => filter_input(INPUT_POST, 'folder', FILTER_SANITIZE_NUMBER_INT),
                    'login' => empty($item['login']) === true ? '' : substr($item['login'], 0, 200),
                    'anyone_can_modify' => filter_input(INPUT_POST, 'import_csv_anyone_can_modify', FILTER_SANITIZE_STRING) === 'true' ? 1 : 0,
                )
            );
            $newId = DB::insertId();

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
                    'perso' => $personalFolder == 0 ? 0 : 1,
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
                array(
                    'error' => true,
                    'message' => langHdl('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Decrypt and retreive data in JSON format
        $receivedParameters = prepareExchangedData($post_data, 'decode');

        $post_folder_id = filter_var($receivedParameters['folder-id'], FILTER_SANITIZE_NUMBER_INT);
        $post_operation_id = filter_var($receivedParameters['file'], FILTER_SANITIZE_STRING);
        $post_edit_all = filter_var($receivedParameters['edit-all'], FILTER_SANITIZE_NUMBER_INT);
        $post_edit_role = filter_var($receivedParameters['edit-role'], FILTER_SANITIZE_NUMBER_INT);

        //Initialization
        $root = $meta = $group = $entry = $key = $title = $notes = $pwd = $username = $url = $notKeepassFile = $newItem = $history = $generatorFound = false;
        $name = $levelInProgress = $previousLevel = $fullPath = $historyLevel = $path = $display = $keepassVersion = '';
        $numGroups = $numItems = 0;
        $arrFolders = array();
        $temparray = array();
        $levelMin = 2;
        $foldersSeparator = '@&##&@';
        $itemsSeparator = '<=|#|=>';
        $lineEndSeparator = '@*1|#9*|@';

        //prepare CACHE files
        $cacheFileName = $SETTINGS['path_to_files_folder'].'/cpassman_cache_'.md5(time().mt_rand());
        $cacheFileNameFolder = $cacheFileName.'_folders';
        $cacheFile = fopen($cacheFileName, 'w');
        $cacheFileF = fopen($cacheFileNameFolder, 'w');
        $logFileName = '/keepassImport_'.date('YmdHis').'.log';
        $cacheLogFile = fopen($SETTINGS['path_to_files_folder'].$logFileName, 'w');

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

        /**
         ** Recursive function that will permit to read each level of XML nodes.
         */
        function recursiveKeepassXML($xmlRoot, $xmlLevel = 0)
        {
            global $meta, $root, $group, $name, $entry, $levelMin, $title, $notes, $pwd, $username, $url,
                $newItem, $history, $levelInProgress, $historyLevel, $temparray,
                $path, $previousLevel, $generatorFound, $cacheFile, $cacheFileF, $numGroups,
                $numItems, $foldersSeparator, $itemsSeparator, $keepassVersion, $arrFolders;

            $groupsArray = array();
            if (count($temparray) === 0) {
                $temparray = initTempArray();
            }

            // For each node, get the name and SimpleXML balise
            foreach ($xmlRoot as $nom => $elem) {
                /*
                * check if file is generated by keepass 1
                * key "pwentry" is only used in KP1.xx XML files
                */
                if ($nom == 'pwentry') {
                    if (empty($keepassVersion)) {
                        $keepassVersion = 1;
                        $generatorFound = true;
                        $entry = true;
                    } else {
                        $entry = true;
                    }

                    //get children
                    $xmlChildren = $elem->children();

                    //recursive call
                    recursiveKeepassXML($xmlChildren, $xmlLevel + 1);
                }
                //IMPORTING KEEPASS 1 XML FILE
                if ($keepassVersion == 1) {
                    if ($entry === true && $nom == 'expiretime') {
                        //save previous keepass entry
                        $tree = preg_replace('/\\\\/', $foldersSeparator, $temparray['tree']);
                        fputs(
                            $cacheFile,
                            $tree.$itemsSeparator.$temparray[KP_GROUP].$itemsSeparator.$temparray[KP_TITLE].
                            $itemsSeparator.$temparray[KP_PASSWORD].$itemsSeparator.$temparray[KP_USERNAME].
                            $itemsSeparator.$temparray[KP_URL].$itemsSeparator.$temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n"
                        );

                        if (!in_array($temparray['tree'], $arrFolders)) {
                            fwrite($cacheFileF, $tree."\n");
                            array_push($arrFolders, $temparray['tree']);
                        }

                        $temparray = initTempArray();
                        ++$newItem;
                    }

                    if ($entry === true && $nom == 'group') {
                        $temparray[KP_GROUP] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                        foreach ($elem->attributes() as $attributeskey0 => $attributesvalue1) {
                            if ($attributeskey0 == 'tree') {
                                $path = explode('\\', $attributesvalue1);
                                if (count($path) > 1) {
                                    unset($path[0]);
                                    $temparray['tree'] = implode('\\', $path).'\\'.$temparray[KP_GROUP];
                                } else {
                                    $temparray['tree'] = $temparray[KP_GROUP];
                                }
                            }
                        }
                        ++$numGroups;
                    } elseif ($entry === true && $nom == 'title') {
                        $temparray[KP_TITLE] = sanitiseString($elem, '');
                    } elseif ($entry === true && $nom == 'username') {
                        $temparray[KP_USERNAME] = sanitiseString($elem, '');
                    } elseif ($entry === true && $nom == 'url') {
                        $temparray[KP_URL] = sanitiseString($elem, '');
                    } elseif ($entry === true && $nom == 'uuid') {
                        $temparray[KP_UUID] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                    } elseif ($entry === true && $nom == 'password') {
                        $temparray[KP_PASSWORD] = sanitiseString($elem, '');
                    } elseif ($entry === true && $nom == 'notes') {
                        $temparray[KP_NOTES] = sanitiseString($elem, '');
                    }
                }

                /*
                   * check if file is generated by keepass 2
                */
                if (trim($elem) == '' && $keepassVersion != 1) {
                    //check if file is generated by keepass 2
                    if ($nom == 'Meta') {
                        $meta = true;
                    }
                    if ($nom == 'Root') {
                        $root = true;
                    }

                    if ($nom == 'Group') {
                        $group = true;
                        $entry = false;
                        $name = '';

                        // recap previous info
                        if (!empty($temparray[KP_TITLE])) {
                            //store data
                            fputs(
                                $cacheFile,
                                $temparray[KP_PATH].$itemsSeparator.$temparray[KP_GROUP].$itemsSeparator
                                .$temparray[KP_TITLE].$itemsSeparator.$temparray[KP_PASSWORD].$itemsSeparator
                                .$temparray[KP_USERNAME].$itemsSeparator.$temparray[KP_URL].$itemsSeparator.
                                $temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n"
                            );

                            //Clean temp array
                            $temparray = initTempArray();

                            //increment number
                            ++$numItems;
                        }
                        $historyLevel = 0;
                    }

                    //History node needs to be managed in order to not polluate final list
                    if ($nom == 'History') {
                        $history = true;
                        $entry = false;
                        $historyLevel = $xmlLevel;
                    }

                    if ($nom == 'Entry' && ($xmlLevel < $historyLevel || empty($historyLevel))) {
                        $entry = true;
                        $group = false;
                        $history = false;

                        // recap previous info
                        if (!empty($temparray[KP_TITLE])) {
                            //store data
                            fputs(
                                $cacheFile,
                                $temparray[KP_PATH].$itemsSeparator.$temparray[KP_GROUP].$itemsSeparator
                                .$temparray[KP_TITLE].$itemsSeparator.$temparray[KP_PASSWORD].$itemsSeparator
                                .$temparray[KP_USERNAME].$itemsSeparator.$temparray[KP_URL].$itemsSeparator
                                .$temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n"
                            );

                            //Clean temp array
                            $temparray = initTempArray();

                            //increment number
                            ++$numItems;
                        }
                        $historyLevel = 0;
                    }

                    //get children
                    $xmlChildren = $elem->children();

                    //recursive call
                    if ($history !== true) {
                        recursiveKeepassXML($xmlChildren, $xmlLevel + 1);
                    }

                    // Force History to false
                    $history = false;

                //IMPORTING KEEPASS 2 XML FILE
                } elseif ($keepassVersion != 1) {
                    // exit if XML file not generated by KeePass
                    if ($meta === true && $nom == 'Generator' && $elem == 'KeePass') {
                        $generatorFound = true;
                        $keepassVersion = 2;
                        break;
                    } elseif ($root === true && $xmlLevel > $levelMin) {
                        //echo $elem.' - '.$nom.' - ';
                        //Check each node name and get data from some of them
                        if ($entry === true && $nom == 'Key' && $elem == 'Title') {
                            $title = true;
                            $notes = $pwd = $url = $username = false;
                        } elseif ($entry === true && $nom == 'Key' && $elem == 'Notes') {
                            $notes = true;
                            $title = $pwd = $url = $username = false;
                        } elseif ($entry === true && $nom == 'UUID') {
                            $temparray[KP_UUID] = $elem;
                        } elseif ($entry === true && $nom == 'Key' && $elem == 'Password') {
                            $pwd = true;
                            $notes = $title = $url = $username = false;
                        } elseif ($entry === true && $nom == 'Key' && $elem == 'URL') {
                            $url = true;
                            $notes = $pwd = $title = $username = false;
                        } elseif ($entry === true && $nom == 'Key' && $elem == 'UserName') {
                            $username = true;
                            $notes = $pwd = $url = $title = false;
                        } elseif ($group === true && $nom == 'Name') {
                            $temparray[KP_GROUP] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                            $temparray['level'] = $xmlLevel;
                            //build current path
                            if ($xmlLevel > $levelInProgress) {
                                if (!empty($temparray[KP_PATH])) {
                                    $temparray[KP_PATH] .= $foldersSeparator.$temparray[KP_GROUP];
                                } else {
                                    $temparray[KP_PATH] = $temparray[KP_GROUP];
                                }
                            } elseif ($xmlLevel == $levelInProgress) {
                                if ($levelInProgress == 3) {
                                    $temparray[KP_PATH] = $temparray[KP_GROUP];
                                } else {
                                    $temparray[KP_PATH] = substr($temparray[KP_PATH], 0, strrpos($temparray[KP_PATH], $foldersSeparator) + strlen($foldersSeparator)).$temparray[KP_GROUP];
                                }
                            } else {
                                $diff = abs($xmlLevel - $levelInProgress) + 1;
                                $tmp = explode($foldersSeparator, $temparray[KP_PATH]);
                                $temparray[KP_PATH] = '';
                                for ($x = 0; $x < (count($tmp) - $diff); ++$x) {
                                    if (!empty($temparray[KP_PATH])) {
                                        $temparray[KP_PATH] = $temparray[KP_PATH].$foldersSeparator.$tmp[$x];
                                    } else {
                                        $temparray[KP_PATH] = $tmp[$x];
                                    }
                                }
                                if (!empty($temparray[KP_PATH])) {
                                    $temparray[KP_PATH] .= $foldersSeparator.$temparray[KP_GROUP];
                                } else {
                                    $temparray[KP_PATH] = $temparray[KP_GROUP];
                                }
                            }

                            //store folders
                            if (!in_array($temparray[KP_PATH], $groupsArray)) {
                                fwrite($cacheFileF, $temparray[KP_PATH]."\n");
                                array_push($groupsArray, $temparray[KP_PATH]);
                                //increment number
                                ++$numGroups;
                            }

                            //Store actual level
                            $levelInProgress = $xmlLevel;
                            $previousLevel = $temparray[KP_GROUP];
                        } elseif ($title === true && $nom == 'Value') {
                            $title = false;
                            $temparray[KP_TITLE] = sanitiseString($elem, '');
                        } elseif ($notes === true && $nom == 'Value') {
                            $notes = false;
                            $temparray[KP_NOTES] = sanitiseString($elem, '');
                        } elseif ($pwd === true && $nom == 'Value') {
                            $pwd = false;
                            $temparray[KP_PASSWORD] = sanitiseString($elem, '');
                        } elseif ($url === true && $nom == 'Value') {
                            $url = false;
                            $temparray[KP_URL] = sanitiseString($elem, '');
                        } elseif ($username === true && $nom == 'Value') {
                            $username = false;
                            $temparray[KP_USERNAME] = sanitiseString($elem, '');
                        }
                    }
                }
            }
        }

        function initTempArray()
        {
            $temparray = array();
            $temparray[KP_PATH] = '';
            $temparray[KP_GROUP] = '';
            $temparray[KP_TITLE] = '';
            $temparray[KP_PASSWORD] = '';
            $temparray[KP_USERNAME] = '';
            $temparray[KP_URL] = '';
            $temparray[KP_UUID] = '';
            $temparray[KP_NOTES] = '';

            return $temparray;
        }

        fputs($cacheLogFile, date('H:i:s ').'Writing XML File '.filter_input(INPUT_POST, 'file', FILTER_SANITIZE_STRING)."\n");

        // Go through each node of XML file
        recursiveKeepassXML($xml);

        //Stop if not a keepass file
        if ($generatorFound === false) {
            //Close file & delete it
            fclose($cacheFileF);
            fclose($cacheFile);
            unlink($cacheFileName);
            unlink($cacheFileNameFolder);
            unlink($SETTINGS['path_to_files_folder'].'/'.filter_input(INPUT_POST, 'file', FILTER_SANITIZE_STRING));

            fputs($cacheLogFile, date('H:i').langHdl('import_error_no_read_possible_kp')."\n");

            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => langHdl('import_error_no_read_possible_kp'),
                ),
                'encode'
            );
            break;
        }

        //save last item
        if (!empty($temparray[KP_TITLE])) {
            //store data
            fputs(
                $cacheFile,
                $temparray[KP_PATH].$itemsSeparator.$temparray[KP_GROUP].$itemsSeparator.
                $temparray[KP_TITLE].$itemsSeparator.$temparray[KP_PASSWORD].$itemsSeparator
                .$temparray[KP_USERNAME].$itemsSeparator.$temparray[KP_URL].$itemsSeparator
                .$temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n"
            );

            //increment number
            ++$numItems;
        }

        //#################
        //# STARTING IMPORTING IF NO ERRORS OR NOT EMPTY
        //#################
        if ($numItems > 0 || $numGroups > 0) {
            // Write in file
            fputs($cacheLogFile, date('H:i:s ').langHdl('nb_folders').' '.$numGroups."\n");
            fputs($cacheLogFile, date('H:i:s ').langHdl('nb_items').' '.$numItems."\n");

            $import_perso = false;
            $itemsArray = array();
            $text = '<span class="fas fa-folder-open"></span>&nbsp;'.langHdl('nb_folders').': '.
                $numGroups.'<br><span class="fas fa-tag"></span>>&nbsp;'.langHdl('nb_items').': '.
                $numItems.'<br><br>';

            // If destination is not ROOT then get the complexity level
            if (in_array($post_folder_id, $_SESSION['personal_folders']) === true) {
                $levelPwComplexity = 50;
                $startPathLevel = 1;
                $import_perso = true;
            } elseif ($post_folder_id > 0) {
                $data = DB::queryFirstRow(
                    'SELECT m.valeur as value, t.nlevel as nlevel
                    FROM '.prefixTable('misc').' as m
                    INNER JOIN '.prefixTable('nested_tree').' as t ON (m.intitule = t.id)
                    WHERE m.type = %s AND m.intitule = %s',
                    'complex',
                    $post_folder_id
                );
                $levelPwComplexity = $data['value'];
                $startPathLevel = $data['nlevel'];
            } else {
                $levelPwComplexity = 50;
                $startPathLevel = 0;
            }

            //Get all folders from file
            fclose($cacheFileF);
            $cacheFileF = fopen($cacheFileNameFolder, 'r');

            //Create folders
            $i = 1;
            $level = 0;
            $foldersArray = array();
            $nbFoldersImported = 0;

            fputs($cacheLogFile, date('H:i:s ')."Creating Folders\n");
            $results = "Folders\n\n";

            while (!feof($cacheFileF)) {
                $folder = fgets($cacheFileF, 4096);
                if (!empty($folder)) {
                    $folder = str_replace(array("\r\n", "\n", "\r"), '', $folder);
                    //get number of levels in path
                    $path = explode($foldersSeparator, $folder);
                    $folderLevel = count($path);

                    //get folder name
                    if (strrpos($folder, $foldersSeparator) > 0) {
                        $fold = substr($folder, strrpos($folder, $foldersSeparator) + strlen($foldersSeparator));
                        $parent = implode($foldersSeparator, array_slice($path, 0, -1));
                        $parent_id = $foldersArray[$parent]['id'];
                    } else {
                        $fold = $folder;
                        $parent_id = $post_folder_id; //permits to select the folder destination
                    }

                    $fold = stripslashes($fold);
                    //create folder - if not exists at the same level
                    DB::query(
                        'SELECT * FROM '.prefixTable('nested_tree').'
                        WHERE nlevel = %i AND title = %s AND parent_id = %i LIMIT 1',
                        intval($folderLevel + $startPathLevel),
                        $fold,
                        $parent_id
                    );
                    $results .= str_replace($foldersSeparator, '\\', $folder);
                    $counter = DB::count();
                    if ($counter == 0) {
                        $results .= " - Inserting\n";
                        //do query
                        DB::insert(
                            prefixTable('nested_tree'),
                            array(
                                'parent_id' => $parent_id,
                                'title' => stripslashes($fold),
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

                        //increment number of imported folders
                        ++$nbFoldersImported;
                    } else {
                        $results .= " - Skipped\n";
                        //get folder actual ID
                        $data = DB::queryFirstRow(
                            'SELECT id FROM '.prefixTable('nested_tree').'
                            WHERE nlevel = %i AND title = %s AND parent_id = %i',
                            intval($folderLevel + $startPathLevel),
                            $fold,
                            $parent_id
                        );
                        $id = $data['id'];
                    }

                    //store in array
                    $foldersArray[$folder] = array(
                        'folder' => $fold,
                        'nlevel' => $folderLevel,
                        'id' => $id,
                    );

                    ++$_SESSION['nb_folders'];
                    ++$i;
                }
            }

            $results .= "\n\nItems\n\n";
            //if no new folders them inform
            if ($nbFoldersImported > 0) {
                fputs($cacheLogFile, date('H:i:s ')."Setting User Rights\n");
                //Refresh the rights of actual user
                identifyUserRights(
                    implode(';', $_SESSION['groupes_visibles']).';'.$id,
                    $_SESSION['groupes_interdits'],
                    $_SESSION['is_admin'],
                    $_SESSION['fonction_id'],
                    $SETTINGS
                );

                fputs($cacheLogFile, date('H:i:s ')."Rebuilding Tree\n");
                //rebuild full tree
                $tree->rebuild();
            }

            fputs($cacheLogFile, date('H:i:s ')."Importing Items\n");

            // Now import ITEMS
            $nbItemsImported = 0;
            $count = 0;

            //Get some info about personal folder
            if (in_array($post_folder_id, $_SESSION['personal_folders']) === true) {
                $personalFolder = 1;
            } else {
                $personalFolder = 0;
            }

            //prepare file to be read
            fclose($cacheFile);
            $cacheFile = fopen($cacheFileName, 'r');

            while (!feof($cacheFile)) {
                //prepare an array with item to import
                $full_item = fgets($cacheFile, 8192);
                $full_item = str_replace(array("\r\n", "\n", "\r"), '', $full_item);
                $item = explode($itemsSeparator, $full_item);

                ++$count;
                if (!($count % 10)) {
                    fputs($cacheLogFile, date('H:i:s ')."  Imported $count items (".number_format(($count / $numItems) * 100, 1).")\n");
                }

                if (!empty($item[KP_TITLE])) {
                    //$count++;
                    //check if not exists
                    $results .= str_replace($foldersSeparator, '\\', $item[KP_PATH]).'\\'.$item[KP_TITLE];

                    $pwd = $item[KP_PASSWORD];

                    //Get folder label
                    if (count($foldersArray) == 0 || empty($item[KP_PATH])) {
                        $folderId = $post_folder_id;
                    } else {
                        $folderId = $foldersArray[$item[KP_PATH]]['id'];
                    }
                    $data = DB::queryFirstRow(
                        'SELECT title FROM '.prefixTable('nested_tree').' WHERE id = %i',
                        intval($folderId)
                    );

                    // escape if folderId is empty
                    if (!empty($folderId)) {
                        $results .= " - Inserting\n";

                        // prepare PW
                        if ($import_perso === true) {
                            $encrypt = cryption(
                                $pwd,
                                $_SESSION['user_settings']['session_psk'],
                                'encrypt',
                                $SETTINGS
                            );
                        } else {
                            $encrypt = cryption(
                                $pwd,
                                '',
                                'encrypt',
                                $SETTINGS
                            );
                        }

                        //ADD item
                        DB::insert(
                            prefixTable('items'),
                            array(
                                'label' => substr(stripslashes($item[KP_TITLE]), 0, 500),
                                'description' => stripslashes(str_replace($lineEndSeparator, '<br>', $item[KP_NOTES])),
                                'pw' => $encrypt['string'],
                                'pw_iv' => '',
                                'url' => substr(stripslashes($item[KP_URL]), 0, 500),
                                'id_tree' => $folderId,
                                'login' => substr(stripslashes($item[KP_USERNAME]), 0, 500),
                                'anyone_can_modify' => $post_edit_all,
                            )
                        );
                        $newId = DB::insertId();

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
                                'label' => substr(stripslashes($item[KP_TITLE]), 0, 500),
                                'description' => stripslashes(str_replace($lineEndSeparator, '<br>', $item[KP_NOTES])),
                                'url' => substr(stripslashes($item[KP_URL]), 0, 500),
                                'tags' => '',
                                'id_tree' => $folderId,
                                'perso' => $personalFolder == 0 ? 0 : 1,
                                'login' => substr(stripslashes($item[KP_USERNAME]), 0, 500),
                                'restricted_to' => '0',
                                'folder' => $data['title'],
                                'author' => $_SESSION['user_id'],
                                'renewal_period' => '0',
                                'timestamp' => time(),
                            )
                        );

                        //increment number of imported items
                        ++$nbItemsImported;
                    } else {
                        $results .= ' - '.$item[KP_TITLE]." was not imported\n";
                    }
                    fputs($cacheLogFile, date('H:i:s ').' '.$results."\n");
                }
            }

            //SHow finished
            $text = '<div class="row ml-3">'.
                '<div class="col-12"><i class="far fa-hand-point-right fa-sm mr-2"></i>'.langHdl('number_of_folders_imported').': <b>'.$nbFoldersImported.'</b></div>'.
                '<div class="col-12"><i class="far fa-hand-point-right fa-sm mr-2"></i>'.langHdl('number_of_items_imported').': <b>'.$nbItemsImported.'</b></div>'.
                '</div>'.
                '<div class="row col-12 ml-3 mt-3 font-weight-bold"><i class="fas fa-check fa-lg mr-2"></i>'.langHdl('import_kp_finished').'</div>';

            //Delete cache file
            fclose($cacheFileF);
            fclose($cacheFile);
            fclose($cacheLogFile);
            unlink($cacheFileName);
            unlink($cacheFileNameFolder);
            unlink($SETTINGS['path_to_files_folder'].'/'.$file);
            unlink($SETTINGS['path_to_files_folder'].$logFileName);

            //Display all messages to user
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'info' => $text,
                ),
                'encode'
            );
        } else {
            echo prepareExchangedData(
                array(
                    'error' => true,
                    'message' => langHdl('no_item_found'),
                ),
                'encode'
            );
        }
        break;
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
    $str = preg_replace('#[\r\n]#', $crLFReplacement, $str);
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
