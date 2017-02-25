<?php
/**
 * @file          import.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// No time limit
set_time_limit(0);

// Set some constants for program readability
define('KP_PATH',0);
define('KP_GROUP',1);
define('KP_TITLE',2);
define('KP_PASSWORD',3);
define('KP_USERNAME',4);
define('KP_URL',5);
define('KP_UUID',6);
define('KP_NOTES',7);

/*
 * sanitiseString
 *
 * Used to format the string ready for insertion in to the database
 */
function sanitiseString($str, $crLFReplacement) {
    $str = preg_replace('#[\r\n]#', $crLFReplacement, $str);
    $str = str_replace('\\', '&#92;', $str);
    $str = str_replace('"', "&quot;", $str);
    if (!empty($str)) {
        addslashes($str);
    }
    return $str;
}

global $k, $settings;
header("Content-type: text/html; charset=utf-8");
error_reporting(E_ERROR);
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';

//Class loader
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// call needed functions
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

// connect to the server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

//User's language loading
$k['langage'] = @$_SESSION['user_language'];
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

// Build query
switch ($_POST['type']) {
    //Check if import CSV file format is what expected
    case "import_file_format_csv":
        //load full tree
        $tree->rebuild();
        $tree = $tree->getDescendants();

        // do some initializations
        $file = $_SESSION['settings']['path_to_files_folder']."/".$_POST['file'];
        $size = 4096;
        $separator = ",";
        $enclosure = '"';
        $fields_expected = array("Label","Login","Password","URL","Comments");  //requiered fields from CSV
        $importation_possible = true;
        $display = "<table>";
        $line_number = $prev_level = 0;
        $account = $text = "";
        $continue_on_next_line = false;

        // Open file
        if ($fp = fopen($file, "r")) {
            // data from CSV
            $valuesToImport = array();
            // load libraries
            require_once($_SESSION['settings']['cpassman_dir'].'/includes/libraries/Goodby/CSV/Import/Standard/Lexer.php');
            require_once($_SESSION['settings']['cpassman_dir'].'/includes/libraries/Goodby/CSV/Import/Standard/Interpreter.php');
            require_once($_SESSION['settings']['cpassman_dir'].'/includes/libraries/Goodby/CSV/Import/Standard/LexerConfig.php');

            // Lexer configuration
            $config = new LexerConfig();
            $lexer = new Lexer($config);
            $config
                ->setIgnoreHeaderLine("true")
                //->setToCharset('UTF-8')
                //->setFromCharset('SJIS-win')
            ;
            // extract data from CSV file
            $interpreter = new Interpreter();
            $interpreter->addObserver(function(array $row) use (&$valuesToImport) {
                $valuesToImport[] = array(
                    'Label'     => $row[0],
                    'Login'     => $row[1],
                    'Password'  => $row[2],
                    'url'       => $row[3],
                    'Comments'  => $row[4],
                );
            });
            $lexer->parse($file, $interpreter);

            // extract one line
            foreach ($valuesToImport as $key => $row) {
                //Check number of fields. MUST be 5. if not stop importation
                if (count($row) != 5) {
                    $importation_possible = false;
                    //Stop if file has not expected structure
                    if ($importation_possible == false) {
                        echo '[{"error":"bad_structure"}]';
                        break;
                    }
                }

                //If any comment is on several lines, then replace 'lf' character
                $row['Comments'] = str_replace(array("\r\n", "\n", "\r"), "<br />", $row['Comments']);

                // Check if current line contains a "<br />" character in order to identify an ITEM on several CSV lines
                if (substr_count('<br />', $row['Comments']) > 0 || substr_count('<br />', $row['Label']) > 0) {
                    $continue_on_next_line = true;
                    $comment .= addslashes($row['Label']);
                } else {
                    // Store in variable values from previous line
                    if (!empty($account)) {
                        if ($continue_on_next_line == false) {
                            // Prepare listing that will be shown to user
                            $display .= '<tr><td><input type=\"checkbox\" class=\"item_checkbox\" id=\"item_to_import-'.$line_number.'\" /></td><td><span id=\"item_text-'.$line_number.'\">'.$account.'</span><input type=\"hidden\" value=\"'.$account.'@|@'.$login.'@|@'.$pw.'@|@'.$url.'@|@'.$comment.'@|@'.$line_number.'\" id=\"item_to_import_values-'.$line_number.'\" /></td></tr>';

                            // Initialize this variable in order to restart from scratch
                            $account = "";
                        }
                    }
                }

                // Get values of current line
                if ($account == "" && $continue_on_next_line == false) {
                    $account = addslashes($row['Label']);
                    $login = addslashes($row['Login']);
                    $pw = str_replace('"', "&quot;", $row['Password']);
                    $url = addslashes($row['url']);
                    $to_find = array ( "\"" , "'" );
                    $to_ins = array ( "&quot" , "&#39;");
                    $comment = htmlentities(addslashes(str_replace($to_find, $to_ins, $row['Comments'])), ENT_QUOTES, 'UTF-8');

                    $continue_on_next_line = false;
                }

                //increment number of lines found
                $line_number++;
            }
            // close file
            fclose($fp);

            // remove file
            unlink($file);
        } else {
            echo '[{"error":"cannot_open"}]';
            break;
        }

        if ($line_number > 0) {
            //add last line
            $display .= '<tr><td><input type=\"checkbox\" class=\"item_checkbox\" id=\"item_to_import-'.$line_number.'\" /></td><td><span id=\"item_text-'.$line_number.'\">'.$account.'</span><input type=\"hidden\" value=\"'.$account.'@|@'.$login.'@|@'.str_replace('"', "&quote;", $pw).'@|@'.$url.'@|@'.$comment.'@|@'.$line_number.'\" id=\"item_to_import_values-'.$line_number.'\" /></td></tr>';

            // Add a checkbox for select/unselect all others
            $display .= '<tr><td colspan=\"2\"><br><input type=\"checkbox\" id=\"item_all_selection\" />&nbsp;'.$LANG['all'].'</td></tr>';

            // Prepare a list of all folders that the user can choose
            $display .= '</table><div style=\"margin-top:10px;\"><label><b>'.$LANG['import_to_folder'].'</b></label>&nbsp;<select id=\"import_items_to\">';
            foreach ($tree as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles'])) {
                    $ident="";
                    for ($x=1; $x<$t->nlevel; $x++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    if (isset($_POST['folder_id']) && $_POST['folder_id'] == $t->id) $selected = " selected";
                    else $selected = "";
                    if ($prev_level != null && $prev_level < $t->nlevel) {
                        $display .= '<option value=\"'.$t->id.'\"'.$selected.'>'.$ident.str_replace(array("&", '"'), array("&amp;", "&quot;"), $t->title).'</option>';
                    } elseif ($prev_level != null && $prev_level == $t->nlevel) {
                        $display .= '<option value=\"'.$t->id.'\"'.$selected.'>'.$ident.str_replace(array("&", '"'), array("&amp;", "&quot;"), $t->title).'</option>';
                    } else {
                        $display .= '<option value=\"'.$t->id.'\"'.$selected.'>'.$ident.str_replace(array("&", '"'), array("&amp;", "&quot;"), $t->title).'</option>';
                    }
                    $prev_level = $t->nlevel;
                }
            }
            $display .= '</select></div>';

            // Show results to user.
            echo '[{"error":"no" , "output" : "'.$display.'"}]';
        }
        break;

    //Insert into DB the items the user has selected
    case "import_items":
        //decrypt and retreive data in JSON format
        $dataReceived = (Encryption\Crypt\aesctr::decrypt($_POST['data'], $_SESSION['key'], 256));

        //Get some info about personal folder
        if ($_POST['folder'] == $_SESSION['user_id']) {
            $personalFolder = 1;
        } else {
            $personalFolder = 0;
        }
        $data_fld = DB::queryFirstRow("SELECT title FROM ".prefix_table("nested_tree")." WHERE id = %i", intval($_POST['folder']));

        //Prepare variables
        $listItems = htmlspecialchars_decode($dataReceived);
        $list = "";

        foreach (explode('@_#sep#_@', mysqli_escape_string($link, stripslashes($listItems))) as $item) {
            //For each item, insert into DB
            $item = explode('@|@', $item);   //explode item to get all fields

            //Encryption key
            if ($personalFolder == 1) {
                $encrypt = cryption(
                    $item[2],
                    $_SESSION['user_settings']['session_psk'],
                    "encrypt"
                );
            } else {
                $encrypt = cryption(
                    $item[2],
                    "",
                    "encrypt"
                );
            }

            // Insert new item in table ITEMS
            DB::insert(
                prefix_table("items"),
                array(
                    'label' => substr($item[0], 0, 500),
                    'description' => $item[4],
                    'pw' => $encrypt['string'],
                    'pw_iv' => $encrypt['iv'],
                    'url' => $item[3],
                    'id_tree' => $_POST['folder'],
                    'login' => $item[1],
                    'anyone_can_modify' => $_POST['import_csv_anyone_can_modify'] == "true" ? 1 : 0
               )
            );
            $newId = DB::insertId();

            //if asked, anyone in role can modify
            if (isset($_POST['import_csv_anyone_can_modify_in_role']) && $_POST['import_csv_anyone_can_modify_in_role'] == "true") {
                foreach ($_SESSION['arr_roles'] as $role) {
                    DB::insert(
                        prefix_table("restriction_to_roles"),
                        array(
                            'role_id' => $role['id'],
                            'item_id' => $newId
                       )
                    );
                }
            }

            // Insert new item in table LOGS_ITEMS
            DB::insert(
                prefix_table("log_items"),
                array(
                    'id_item' => $newId,
                    'date' => time(),
                    'id_user' => $_SESSION['user_id'],
                    'action' => 'at_creation'
               )
            );

            if (empty($list)) {
                $list = $item[5];
            } else {
                $list .= ";".$item[5];
            }

            //Add entry to cache table
            DB::insert(
                prefix_table("cache"),
                array(
                    'id' => $newId,
                    'label' => substr($item[0], 0, 500),
                    'description' => $item[4],
                    'id_tree' => $_POST['folder'],
                    'perso' => $personalFolder == 0 ? 0 : 1,
                    'login' => $item[1],
                    'folder' => $data_fld['title'],
                    'author' => $_SESSION['user_id'],
                    'timestamp' => time(),
                    'tags' => '',
                    'restricted_to' => ''
               )
            );
        }
        echo '[{"items":"'.$list.'"}]';
        break;

    //Check if import KEEPASS file format is what expected
    case "import_file_format_keepass":
        //Initialization
        $root = $meta = $group = $entry = $key = $title = $notes = $pw = $username = $url = $notKeepassFile = $newItem = $history = $generatorFound = false;
        $name = $levelInProgress = $previousLevel = $fullPath = $historyLevel = $path = $display = $keepassVersion = "";
        $numGroups = $numItems = 0;
        $temparray = $arrFolders = array();
        $levelMin = 2;
        $foldersSeparator = '@&##&@';
        $itemsSeparator = '<=|#|=>';
        $lineEndSeparator = '@*1|#9*|@';

        //prepare CACHE files
        $cacheFileName = $_SESSION['settings']['path_to_files_folder']."/cpassman_cache_".md5(time().mt_rand());
        $cacheFileNameFolder = $cacheFileName."_folders";
        $cacheFile = fopen($cacheFileName, "w");
        $cacheFileF = fopen($cacheFileNameFolder, "w");
        $logFileName = "/keepassImport_" . date('YmdHis');
        $cacheLogFile = fopen($_SESSION['settings']['path_to_files_folder'].$logFileName,'w');

        //read xml file
        if (file_exists("'".$_SESSION['settings']['path_to_files_folder']."/".$_POST['file'])."'") {
            $xml = simplexml_load_file($_SESSION['settings']['path_to_files_folder']."/".$_POST['file']);
        }

        /**
        Recursive function that will permit to read each level of XML nodes
        */
        function recursiveKeepassXML($xmlRoot, $xmlLevel = 0)
        {
            global $meta, $root, $group, $name, $entry, $levelMin, $key, $title, $notes, $pw, $username, $url,
                $newItem, $temparray, $history, $levelInProgress, $historyLevel, $nbItems,
                $path, $previousLevel, $generatorFound, $cacheFile, $cacheFileF, $numGroups,
                $numItems, $foldersSeparator, $itemsSeparator, $lineEndSeparator, $keepassVersion, $arrFolders;

            $groupsArray = array();

            // For each node, get the name and SimpleXML balise
            foreach ($xmlRoot as $nom => $elem) {
                /*
                * check if file is generated by keepass 1
                * key "pwentry" is only used in KP1.xx XML files
                */
                //echo $nom."-";
                if ($nom == "pwentry") {
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
                    if ($entry == true && $nom == "expiretime") {
                        //save previous keepass entry
                        $tree = preg_replace('/\\\\/', $foldersSeparator, $temparray['tree']);
                        fputs(
                            $cacheFile,
                            $tree.$itemsSeparator.$temparray[KP_GROUP].$itemsSeparator.$temparray[KP_TITLE].
                            $itemsSeparator.$temparray[KP_PW].$itemsSeparator.$temparray[KP_USERNAME].
                            $itemsSeparator.$temparray[KP_URL].$itemsSeparator.$temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n"
                        );

                        if (!in_array($temparray['tree'], $arrFolders)) {
                            fwrite($cacheFileF, $tree."\n");
                            array_push($arrFolders, $temparray['tree']);
                        }

                        $temparray = array();
                        $newItem++;
                    }

                    if ($entry == true && $nom == "group") {
                        $temparray[KP_GROUP] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                        foreach ($elem->attributes() as $attributeskey0 => $attributesvalue1) {
                            if ($attributeskey0 == "tree") {
                                $path = explode('\\', $attributesvalue1);
                                if (count($path) > 1) {
                                    unset($path[0]);
                                    $temparray['tree'] = implode('\\', $path).'\\'.$temparray[KP_GROUP];
                                } else {
                                    $temparray['tree'] = $temparray[KP_GROUP];
                                }
                            }
                        }
                        $numGroups ++;
                    } elseif ($entry == true && $nom == "title") {
                        $temparray[KP_TITLE] = sanitiseString($elem, '');
                    } elseif ($entry == true && $nom == "username") {
                        $temparray[KP_USERNAME] = sanitiseString($elem, '');
                    } elseif ($entry == true && $nom == "url") {
                        $temparray[KP_URL] = sanitiseString($elem, '');
                    } elseif ($entry == true && $nom == "uuid") {
                        $temparray[KP_UUID] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                    } elseif ($entry == true && $nom == "password") {
                        $temparray[KP_PW] = sanitiseString($elem, '');
                    } elseif ($entry == true && $nom == "notes") {
                        $temparray[KP_NOTES] = sanitiseString($elem, '');
                    }
                }

                /*
                   * check if file is generated by keepass 2
                */
                if (trim($elem) == "" && $keepassVersion != 1) {
                    //check if file is generated by keepass 2
                    if ($nom == "Meta") {
                        $meta = true;
                    }
                    if ($nom == "Root") {
                        $root = true;
                    }

                    if ($nom == "Group") {
                        $group = true;
                        $entry = false;
                        $name = "";

                        // recap previous info
                        if (!empty($temparray[KP_TITLE])) {
                            //store data
                            fputs(
                                $cacheFile,
                                $temparray[KP_PATH].$itemsSeparator.$temparray[KP_GROUP].
                                $itemsSeparator.$temparray[KP_TITLE].$itemsSeparator.$temparray[KP_PW].
                                $itemsSeparator.$temparray[KP_USERNAME].$itemsSeparator.
                                $temparray[KP_URL].$itemsSeparator.$temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n"
                            );

                            //Clean temp array
                            $temparray[KP_TITLE] = $temparray[KP_NOTES] = $temparray[KP_PW] = $temparray[KP_USERNAME] = $temparray[KP_URL] = "";

                            //increment number
                            $numItems++;
                        }
                        $historyLevel = 0;
                    }

                    //History node needs to be managed in order to not polluate final list
                    if ($nom == "History") {
                        $history = true;
                        $entry = false;
                        $historyLevel = $xmlLevel;
                    }

                    if ($nom == "Entry" && ($xmlLevel < $historyLevel || empty($historyLevel))) {
                        $entry = true;
                        $group = false;

                        // recap previous info
                        if (!empty($temparray[KP_TITLE])) {
                            //store data
                            fputs($cacheFile, $temparray[KP_PATH].$itemsSeparator.$temparray[KP_GROUP].$itemsSeparator.$temparray[KP_TITLE].$itemsSeparator.$temparray[KP_PW].$itemsSeparator.$temparray[KP_USERNAME].$itemsSeparator.$temparray[KP_URL].$itemsSeparator.$temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n");

                            //Clean temp array
                            $temparray[KP_TITLE] = $temparray[KP_NOTES] = $temparray[KP_PW] = $temparray[KP_USERNAME] = $temparray[KP_URL] = $temparray[KP_UUID] = "";

                            //increment number
                            $numItems++;
                        }
                        $historyLevel = 0;
                    }

                    //get children
                    $xmlChildren = $elem->children();

                    //recursive call
                    recursiveKeepassXML($xmlChildren, $xmlLevel + 1);

                    //IMPORTING KEEPASS 2 XML FILE
                } elseif ($keepassVersion != 1) {
                    // exit if XML file not generated by KeePass
                    if ($meta == true && $nom == "Generator" && $elem == "KeePass") {
                        $generatorFound = true;
                        $keepassVersion = 2;
                        break;
                    } elseif ($root == true && $xmlLevel > $levelMin) {
 //                       error_log($nom.",".$elem." - ");
                        //Check each node name and get data from some of them
                        if ($entry == true && $nom == "Key" && $elem == "Title") {
                            $title = true;
                            $notes = $pw = $url = $username = false;
                        } elseif ($entry == true && $nom == "Key" && $elem == "Notes") {
                            $notes = true;
                            $title = $pw = $url = $username = false;
                        } elseif ($entry == true && $nom == "UUID") {
                            $temparray[KP_UUID] = $elem;
                        } elseif ($entry == true && $nom == "Key" && $elem == "Password") {
                            $pw = true;
                            $notes = $title = $url = $username = false;
                        } elseif ($entry == true && $nom == "Key" && $elem == "URL") {
                            $url = true;
                            $notes = $pw = $title = $username = false;
                        } elseif ($entry == true && $nom == "Key" && $elem == "UserName") {
                            $username = true;
                            $notes = $pw = $url = $title = false;
                        } elseif ($group == true && $nom == "Name") {
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
                                    $temparray[KP_PATH] = substr($temparray[KP_PATH], 0, strrpos($temparray[KP_PATH], $foldersSeparator)+strlen($foldersSeparator)).$temparray[KP_GROUP];
                                }
                            } else {
                                $diff = abs($xmlLevel-$levelInProgress)+1;
                                $tmp = explode($foldersSeparator, $temparray[KP_PATH]);
                                $temparray[KP_PATH] = "";
                                for ($x=0; $x<(count($tmp)-$diff); $x++) {
                                    if (!empty($temparray[KP_PATH])) {
                                        $temparray[KP_PATH] = $temparray[KP_PATH]. $foldersSeparator.$tmp[$x];
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
                                $numGroups ++;
                            }

                            //Store actual level
                            $levelInProgress = $xmlLevel;
                            $previousLevel = $temparray[KP_GROUP];
                        } elseif ($title == true && $nom == "Value") {
                            $title = false;
                            $temparray[KP_TITLE] = sanitiseString($elem, '');
                        } elseif ($notes == true && $nom == "Value") {
                            $notes = false;
                            $temparray[KP_NOTES] = sanitiseString($elem, '');
                        } elseif ($pw == true && $nom == "Value") {
                            $pw = false;
                            $temparray[KP_PW] = sanitiseString($elem, '');
                        } elseif ($url == true && $nom == "Value") {
                            $url = false;
                            $temparray[KP_URL] = sanitiseString($elem, '');
                        } elseif ($username == true && $nom == "Value") {
                            $username = false;
                            $temparray[KP_USERNAME] = sanitiseString($elem, '');
                        }
                    }
                }
            }
        }

        fputs($cacheLogFile, date('H:i:s ') . "Writing XML File ".$_POST['file']."\n");

        // Go through each node of XML file
        recursiveKeepassXML($xml);

        //Stop if not a keepass file
        if ($generatorFound == false) {
            //Close file & delete it
            fclose($cacheFileF);
            fclose($cacheFile);
            unlink($cacheFileName);
            unlink($cacheFileNameFolder);
            unlink($_SESSION['settings']['url_to_files_folder']."/".$_POST['file']);

            fputs($cacheLogFile, date('H:i') . $LANG['import_error_no_read_possible_kp']."\n");

            echo '[{"error":"not_kp_file" , "message":"'.$LANG['import_error_no_read_possible_kp'].'"}]';
            break;
        }

        //save last item
        if (!empty($temparray[KP_TITLE])) {
            //store data
            fputs(
                $cacheFile,
                $temparray[KP_PATH].$itemsSeparator.$temparray[KP_GROUP].$itemsSeparator.
                $temparray[KP_TITLE].$itemsSeparator.$temparray[KP_PW].$itemsSeparator.$temparray[KP_USERNAME].
                $itemsSeparator.$temparray[KP_URL].$itemsSeparator.$temparray[KP_UUID].$itemsSeparator.$temparray[KP_NOTES]."\n"
            );

            //increment number
            $numItems++;
        }

        ##################
        ## STARTING IMPORTING IF NO ERRORS OR NOT EMPTY
        ##################
        if ($numItems>0 || $numGroups>0) {

            fputs($cacheLogFile, date('H:i:s ') . $LANG['nb_folders']. ' '.$numGroups."\n");
            fputs($cacheLogFile, date('H:i:s ') . $LANG['nb_items'] . ' '. $numItems."\n");

            $import_perso = false;
            $itemsArray = array();
            $text = '<span class="fa fa-folder-open"></span>&nbsp;'.$LANG['nb_folders'].': '.
                $numGroups.'<br /><span class="fa fa-tag"></span>>&nbsp;'.$LANG['nb_items'].': '.
                $numItems.'<br /><br />';

            //if destination is not ROOT then get the complexity level
            if (strpos($_POST['destination'], "perso") != false) {
                $levelPwComplexity = 50;
                $startPathLevel = 1;
                $import_perso = true;
            } else if ($_POST['destination'] > 0) {
                $data = DB::queryFirstRow(
                    "SELECT m.valeur as value, t.nlevel as nlevel
                    FROM ".prefix_table("misc")." as m
                    INNER JOIN ".prefix_table("nested_tree")." as t ON (m.intitule = t.id)
                    WHERE m.type = %s AND m.intitule = %s",
                    "complex",
                    mysqli_escape_string($link, $_POST['destination'])
                );
                $levelPwComplexity = $data['value'];
                $startPathLevel = $data['nlevel'];
            } else {
                $levelPwComplexity = 50;
                $startPathLevel = 0;
            }

            //Get all folders from file
            fclose($cacheFileF);
            $cacheFileF = fopen($cacheFileNameFolder, "r");

            //Create folders
            $i=1;
            $level = 0;
            $foldersArray = array();
            $nbFoldersImported = 0;

            fputs($cacheLogFile, date('H:i:s ') . "Creating Folders\n");
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
                        $fold = substr($folder, strrpos($folder, $foldersSeparator)+strlen($foldersSeparator));
                        //$parent_id = $foldersArray[$path[$folderLevel-2]]['id'];
                        $parent = implode($foldersSeparator, array_slice($path, 0, -1));
                        $parent_id = $foldersArray[$parent]['id'];
                    } else {
                        $fold = $folder;
                        $parent_id = $_POST['destination']; //permits to select the folder destination
                    }

                    $fold=stripslashes($fold);
                    //create folder - if not exists at the same level
                    DB::query(
                        "SELECT * FROM ".prefix_table("nested_tree")."
                        WHERE nlevel = %i AND title = %s AND parent_id = %i LIMIT 1",
                        intval($folderLevel+$startPathLevel),
                        $fold,
                        $parent_id
                    );
                    $results.= str_replace($foldersSeparator, '\\', $folder);
                    $counter = DB::count();
                    if ($counter == 0) {
                        $results.= " - Inserting\n";
                        //do query
                        DB::insert(
                            prefix_table("nested_tree"),
                            array(
                                'parent_id' => $parent_id,
                                'title' => stripslashes($fold),
                                'nlevel' => $folderLevel
                           )
                        );
                        $id = DB::insertId();
                        //Add complexity level => level is set to "medium" by default.
                        DB::insert(
                            prefix_table("misc"),
                            array(
                                'type' => 'complex',
                                'intitule' => $id,
                                'valeur' => $levelPwComplexity
                           )
                        );

                        //For each role to which the user depends on, add the folder just created.
                        foreach ($_SESSION['arr_roles'] as $role) {
                            DB::insert(
                                prefix_table("roles_values"),
                                array(
                                    'role_id' => $role['id'],
                                    'folder_id' => $id,
                                    'type' => "W"
                               )
                            );
                        }

                        //Add this new folder to the list of visible folders for the user.
                        array_push($_SESSION['groupes_visibles'], $id);

                        //show
                        //$text .= '- '.(($fold)).'<br />';
                        //increment number of imported folders
                        $nbFoldersImported++;
                    } else {
                        $results.= " - Skipped\n";
                        //get folder actual ID
                        $data = DB::queryFirstRow(
                            "SELECT id FROM ".prefix_table("nested_tree")."
                            WHERE nlevel = %i AND title = %s AND parent_id = %i",
                            intval($folderLevel+$startPathLevel),
                            $fold,
                            $parent_id
                        );
                        $id = $data['id'];
                    }

                    //store in array
                    $foldersArray[$folder] = array(
                        'folder' => $fold,
                        'nlevel' => $folderLevel,
                        'id' => $id
                    );

                    $_SESSION['nb_folders'] ++;
                    $i++;
                }
            }

            $results .= "\n\nItems\n\n";
            //if no new folders them inform
            if ($nbFoldersImported == 0) {
                //$text .= $LANG['none'].'<br />';
            } else {
                fputs($cacheLogFile, date('H:i:s ') . "Setting User Rights\n");
                //Refresh the rights of actual user
                identifyUserRights(implode(';', $_SESSION['groupes_visibles']).';'.$newId, $_SESSION['groupes_interdits'], $_SESSION['is_admin'], $_SESSION['fonction_id'], true);

                fputs($cacheLogFile, date('H:i:s ') . "Rebuilding Tree\n");
                //rebuild full tree
                $tree->rebuild();
            }
            //show
            //$text .= '<br /><b>'.$LANG['importing_items'].':</b><br />';

            fputs($cacheLogFile, date('H:i:s ') . "Importing Items\n");

            // Now import ITEMS
            $nbItemsImported = 0;
            $count = 0;

            //Get some info about personal folder
            if ($_POST['destination'] == $_SESSION['user_id']) {
                $personalFolder = 1;
            } else {
                $personalFolder = 0;
            }

            //prepare file to be read
            fclose($cacheFile);
            $cacheFile = fopen($cacheFileName, "r");

            while (!feof($cacheFile)) {
                //prepare an array with item to import
                $full_item = fgets($cacheFile, 8192);
                $full_item = str_replace(array("\r\n", "\n", "\r"), '', $full_item);
                $item = explode($itemsSeparator, $full_item);

                $count++;
                if (!($count % 10)) {
                    fputs($cacheLogFile, date('H:i:s ') . "  Imported $count items (" . number_format(($count / $numItems) * 100, 1) . ")\n");
                }

                if (!empty($item[KP_TITLE])) {
                    //$count++;
                    //check if not exists
                    $results .= str_replace($foldersSeparator,"\\",$item[KP_PATH]).'\\'.$item[KP_TITLE];

                    $pw = $item[KP_PASSWORD];

                    //Get folder label
                    if (count($foldersArray)==0 || empty($item[KP_PATH])) {
                        $folderId = $_POST['destination'];
                    } else {
                        $folderId = $foldersArray[$item[KP_PATH]]['id'];
                    }
                    $data = DB::queryFirstRow(
                        "SELECT title FROM ".prefix_table("nested_tree")." WHERE id = %i",
                        intval($folderId)
                    );

                    // escape if folderId is empty
                    if (!empty($folderId)) {
                        $results .= " - Inserting\n";

                        // prepare PW
                        if ($import_perso == true) {
                            $encrypt = cryption(
                                $pw,
                                $_SESSION['user_settings']['session_psk'],
                                "encrypt"
                            );
                        } else {
                            $encrypt = cryption(
                                $pw,
                                "",
                                "encrypt"
                            );
                        }

                        //ADD item
                        DB::insert(
                            prefix_table("items"),
                            array(
                                'label' => substr(stripslashes($item[KP_TITLE]), 0, 500),
                                'description' => stripslashes(str_replace($lineEndSeparator, '<br />', $item[KP_NOTES])),
                                'pw' => $encrypt['string'],
                                'pw_iv' => $encrypt['iv'],
                                'url' => stripslashes($item[KP_URL]),
                                'id_tree' => $folderId,
                                'login' => stripslashes($item[KP_USERNAME]),
                                'anyone_can_modify' => $_POST['import_kps_anyone_can_modify'] == "true" ? 1 : 0
                           )
                        );
                        $newId = DB::insertId();

                        //if asked, anyone in role can modify
                        if (isset($_POST['import_kps_anyone_can_modify_in_role']) && $_POST['import_kps_anyone_can_modify_in_role'] == "true") {
                            foreach ($_SESSION['arr_roles'] as $role) {
                                DB::insert(
                                    prefix_table("restriction_to_roles"),
                                    array(
                                        'role_id' => $role['id'],
                                        'item_id' => $newId
                                   )
                                );
                            }
                        }

                        //Add log
                        DB::insert(
                            prefix_table("log_items"),
                            array(
                                'id_item' => $newId,
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_creation',
                                'raison' => 'at_import'
                           )
                        );

                        //Add entry to cache table
                        DB::insert(
                            prefix_table("cache"),
                            array(
                                'id' => $newId,
                                'label' => substr(stripslashes($item[KP_TITLE]), 0, 500),
                                'description' => stripslashes(str_replace($lineEndSeparator, '<br />', $item[KP_NOTES])),
                                'id_tree' => $folderId,
                                'perso' => $personalFolder == 0 ? 0 : 1,
                                'login' => stripslashes($item[KP_USERNAME]),
                                'folder' => $data['title'],
                                'author' => $_SESSION['user_id'],
                                'timestamp' => time()
                           )
                        );

                        //show
                        //$text .= '- '.addslashes($item[2]).'<br />';

                        //increment number of imported items
                        $nbItemsImported++;
                    } else {
                        $results .= " - ".$item[KP_TITLE]." was not imported\n";
                    }
                }
            }

            //if no new items them inform
            /*if ($nbItemsImported == 0) {
                $text .= $LANG['none'].'<br />';
            }*/

            //SHow finished
            $text .= "Folders imported: $nbFoldersImported<br />";
            $text .= "Items imported: $nbItemsImported<br />";
            $text .= '</div><br /><br /><b>'.$LANG['import_kp_finished'].'</b>';
            $text .= '<a href=\''.$_SESSION['settings']['url_to_files_folder'].'/'.$logFileName.'\' target=\'_blank\'>'.$LANG['pdf_download'].'</a>';

            fputs($cacheLogFile, date('H:i:s ') . "Import finished\n");
            fputs($cacheLogFile, date('H:i:s ') . "Statistics\n");
            fputs($cacheLogFile, date('H:i:s ') . "Folders imported: $nbFoldersImported\n");
            fputs($cacheLogFile, date('H:i:s ') . "Items imported: $nbItemsImported\n\n".$results);

            //Delete cache file
            fclose($cacheFileF);
            fclose($cacheFile);
            fclose($cacheLogFile);
            unlink($cacheFileName);
            unlink($cacheFileNameFolder);
            unlink($_SESSION['settings']['path_to_files_folder']."/".$_POST['file']);

            //Display all messages to user
            echo '[{"error":"no" , "message":"'.str_replace('"', "&quote;", strip_tags($text, '<br /><a><div><b><br>')).'"}]';
        } else {
            echo '[{"error":"yes" , "message":""}]';
        }
        break;
}
spl_autoload_register(function ($class) {
    $prefix = 'League\\Csv\\';echo "ici2";
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        echo "ici";
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
