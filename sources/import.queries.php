<?php
/**
 * @file          import.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

global $k, $settings;
header("Content-type: text/html; charset=utf-8");
error_reporting(E_ERROR);
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';

//Class loader
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

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

// Construction de la requ?te en fonction du type de valeur
switch ($_POST['type']) {
    //Check if import CSV file format is what expected
    case "import_file_format_csv":
        //load full tree
        $tree->rebuild();
        $tst = $tree->getDescendants();

        // do some initializations
        $file = $_SESSION['settings']['path_to_files_folder']."/".$_POST['file'];
        $size = 4096;
        $separator = ",";
        $enclosure = '"';
        $fields_expected = array("Account","Login Name","Password","Web Site","Comments");  //requiered fields from CSV
        $importation_possible = true;
        $display = "<table>";
        $line_number = $prev_level = 0;
        $account = $text = "";
        $continue_on_next_line = false;

        // Open file
        if ($fp = fopen($file, "r")) {
            // extract one line
            while (($line = fgetcsv($fp, $size, $separator, $enclosure)) !== false) {
                //Check number of fields. MUST be 5. if not stop importation
                if ($line_number == 0) {
                    if (count($line) != 5) {
                        $importation_possible = false;
                    }
                    //Stop if file has not expected structure
                    if ($importation_possible == false) {
                        echo '[{"error":"bad_structure"}]';
                        break;
                    }
                }
                if ($line_number> 0) {
                    //Clean single/double quotes
                    for ($x=0; $x<5; $x++) {
                        $line[$x] = trim($line[$x], "'");
                        $line[$x] = trim($line[$x], '"');
                    }

                    //If any comment is on several lines, then replace 'lf' character
                    $line[4] = str_replace(array("\r\n", "\n", "\r"), "<br />", $line[4]);

                    // Check if current line contains a "<br />" character in order to identify an ITEM on several CSV lines
                    if (substr_count('<br />', $line[4]) > 0 || substr_count('<br />', $line[0]) > 0) {
                        $continue_on_next_line = true;
                        $comment .= addslashes($line[0]);
                    } else {
                        // Store in variable values from previous line
                        if (!empty($line[0]) && !empty($line[2]) && !empty($account)) {
                            if ($continue_on_next_line == false) {
                                // Prepare listing that will be shown to user
                                $display .= '<tr><td><input type=\"checkbox\" class=\"item_checkbox\" id=\"item_to_import-'.$line_number.'\" /></td><td><span id=\"item_text-'.$line_number.'\">'.$account.'</span><input type=\"hidden\" value=\"'.$account.'@|@'.$login.'@|@'.str_replace('"', "&quote;", $pw).'@|@'.$url.'@|@'.$comment.'@|@'.$line_number.'\" id=\"item_to_import_values-'.$line_number.'\" /></td></tr>';

                                // Initialize this variable in order to restart from scratch
                                $account = "";
                            }
                        }
                    }

                    // Get values of current line
                    if ($account == "" && $continue_on_next_line == false) {
                        $account = addslashes($line[0]);
                        $login = addslashes($line[1]);
                        $pw = $line[2];
                        $url = addslashes($line[3]);
                        $comment = htmlentities(addslashes($line[4]), ENT_QUOTES);

                        $continue_on_next_line = false;
                    }
                }

                //increment number of lines found
                $line_number++;
            }
            // close file
            fclose($fp);
        } else {
            echo '[{"error":"cannot_open"}]';
            break;
        }

        if ($line_number > 0) {
            //add last line
            $display .= '<tr><td><input type=\"checkbox\" class=\"item_checkbox\" id=\"item_to_import-'.$line_number.'\" /></td><td><span id=\"item_text-'.$line_number.'\">'.$account.'</span><input type=\"hidden\" value=\"'.$account.'@|@'.$login.'@|@'.str_replace('"', "&quote;", $pw).'@|@'.$url.'@|@'.$comment.'@|@'.$line_number.'\" id=\"item_to_import_values-'.$line_number.'\" /></td></tr>';

            // Add a checkbox for select/unselect all others
            $display .= '<tr><td><input type=\"checkbox\" id=\"item_all_selection\" /></td><td>'.$txt['all'].'</td></tr>';
            //echo 'function selectAll() {$("input[type=\'checkbox\']:not([disabled=\'disabled\'])").attr(\'checked\', true);}';

            // Prepare a list of all folders that the user can choose
            $display .= '</table><div style=\"margin-top:10px;\"><label><b>'.$txt['import_to_folder'].'</b></label>&nbsp;<select id=\"import_items_to\">';
            foreach ($tst as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles'])) {
                    $ident="";
                    for ($x=1; $x<$t->nlevel; $x++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    if ($prev_level != null && $prev_level < $t->nlevel) {
                        $display .= '<option value=\"'.$t->id.'\">'.$ident.str_replace(array("&", '"'), array("&amp;", "&quot;"), $t->title).'</option>';
                    } elseif ($prev_level != null && $prev_level == $t->nlevel) {
                        $display .= '<option value=\"'.$t->id.'\">'.$ident.str_replace(array("&", '"'), array("&amp;", "&quot;"), $t->title).'</option>';
                    } else {
                        $display .= '<option value=\"'.$t->id.'\">'.$ident.str_replace(array("&", '"'), array("&amp;", "&quot;"), $t->title).'</option>';
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
        // $data_fld = $db->fetchRow("SELECT title FROM ".$pre."nested_tree WHERE id = '".$_POST['folder']."'");
        $data_fld = $db->queryGetRow(
            "nested_tree",
            array(
                "title"
            ),
            array(
                "id" => intval($_POST['folder'])
            )
        );

        //Prepare variables
        $listItems = htmlspecialchars_decode($dataReceived);
        $list = "";

        include 'main.functions.php';
        foreach (explode('@_#sep#_@', mysql_real_escape_string(stripslashes($listItems))) as $item) {
            //For each item, insert into DB
            $item = explode('@|@', $item);   //explode item to get all fields

            //Encryption key
            $randomKey = generateKey();
            $pw = $randomKey.$item[2];

            // Insert new item in table ITEMS
            $newId = $db->queryInsert(
                "items",
                array(
                    'label' => $item[0],
                    'description' => $item[4],
                    'pw' => encrypt(str_replace('&quote;', '"', $pw)),
                    'url' => $item[3],
                    'id_tree' => $_POST['folder'],
                    'login' => $item[1],
                    'anyone_can_modify' => $_POST['import_csv_anyone_can_modify'] == "true" ? 1 : 0
               )
            );

            //Store generated key
            $db->queryInsert(
                'keys',
                array(
                    'table' => 'items',
                    'id' => $newId,
                    'rand_key' => $randomKey
               )
            );

            //if asked, anyone in role can modify
            if (isset($_POST['import_csv_anyone_can_modify_in_role']) && $_POST['import_csv_anyone_can_modify_in_role'] == "true") {
                foreach ($_SESSION['arr_roles'] as $role) {
                    $db->queryInsert(
                        'restriction_to_roles',
                        array(
                            'role_id' => $role['id'],
                            'item_id' => $newId
                       )
                    );
                }
            }

            // Insert new item in table LOGS_ITEMS
            $db->queryInsert(
                'log_items',
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
            $db->queryInsert(
                'cache',
                array(
                    'id' => $newId,
                    'label' => $item[0],
                    'description' => $item[4],
                    'id_tree' => $_POST['folder'],
                    'perso' => $personalFolder == 0 ? 0 : 1,
                    'login' => $item[1],
                    'folder' => $data_fld[0],
                    'author' => $_SESSION['user_id']
               )
            );

        }
        echo '[{"items":"'.$list.'"}]';
        break;

    //Check if import KEEPASS file format is what expected
    case "import_file_format_keepass":
        // call needed functions
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

        //Initialization
        $root = $meta = $group = $entry = $key = $title = $notes = $pw = $username = $url = $notKeepassFile = $newItem = $history = $generatorFound = false;
        $name = $levelInProgress = $previousLevel = $fullPath = $historyLevel = $path = $display = $keepassVersion = "";
        $numGroups = $numItems = 0;
        $tempArray = $arrFolders = array();
        $levelMin = 2;
        $foldersSeparator = '@&##&@';
        $itemsSeparator = '<=|#|=>';
        $lineEndSeparator = '@*1|#9*|@';

        //prepare CACHE files
        $cacheFileName = $_SESSION['settings']['path_to_files_folder']."/cpassman_cache_".md5(time().mt_rand());
        $cacheFileNameFolder = $cacheFileName."_folders";
        $cacheFile = fopen($cacheFileName, "w");
        $cacheFileF = fopen($cacheFileNameFolder, "w");

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
                $newItem, $tempArray, $history, $levelInProgress, $historyLevel, $nbItems,
                $path, $previousLevel, $generatorFound, $cacheFile, $cacheFileF, $numGroups,
                $numItems, $foldersSeparator, $itemsSeparator, $lineEndSeparator, $keepassVersion, $arrFolders;

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
                        $tree = preg_replace('/\\\\/', $foldersSeparator, $tempArray['tree']);
                        fputs(
                            $cacheFile,
                            $tree.$itemsSeparator.$tempArray['group'].$itemsSeparator.$tempArray['title'].
                            $itemsSeparator.$tempArray['pw'].$itemsSeparator.$tempArray['username'].
                            $itemsSeparator.$tempArray['notes'].$itemsSeparator.$tempArray['url']."\n"
                        );

                        if (!in_array($tempArray['tree'], $arrFolders)) {
                            fwrite($cacheFileF, $tree."\n");
                            array_push($arrFolders, $tempArray['tree']);
                        }

                        $tempArray = array();
                        $newItem++;
                    }

                    if ($entry == true && $nom == "group") {
                        $tempArray['group'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                        foreach ($elem->attributes() as $attributeskey0 => $attributesvalue1) {
                            if ($attributeskey0 == "tree") {
                                $path = explode('\\', $attributesvalue1);
                                if (count($path) > 1) {
                                    unset($path[0]);
                                    $tempArray['tree'] = implode('\\', $path).'\\'.$tempArray['group'];
                                } else {
                                    $tempArray['tree'] = $tempArray['group'];
                                }
                            }
                        }
                        $numGroups ++;
                    } elseif ($entry == true && $nom == "title") {
                        $tempArray['title'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                    } elseif ($entry == true && $nom == "username") {
                        $tempArray['username'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                    } elseif ($entry == true && $nom == "url") {
                        $tempArray['url'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                    } elseif ($entry == true && $nom == "password") {
                        $tempArray['pw'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                    } elseif ($entry == true && $nom == "notes") {
                        $tempArray['notes'] = addslashes(preg_replace('#[\r\n]#', $lineEndSeparator, $elem));
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
                        if (!empty($tempArray['title'])) {
                            //store data
                            fputs(
                                $cacheFile,
                                $tempArray['path'].$itemsSeparator.$tempArray['group'].
                                $itemsSeparator.$tempArray['title'].$itemsSeparator.$tempArray['pw'].
                                $itemsSeparator.$tempArray['username'].$itemsSeparator.
                                $tempArray['notes'].$itemsSeparator.$tempArray['url']."\n"
                            );

                            //Clean temp array
                            $tempArray['title'] = $tempArray['notes'] =
                                $tempArray['pw'] = $tempArray['username'] = $tempArray['url'] = "";

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
                        if (!empty($tempArray['title'])) {
                            //store data
                            fputs($cacheFile, $tempArray['path'].$itemsSeparator.$tempArray['group'].$itemsSeparator.$tempArray['title'].$itemsSeparator.$tempArray['pw'].$itemsSeparator.$tempArray['username'].$itemsSeparator.$tempArray['notes'].$itemsSeparator.$tempArray['url']."\n");

                            //Clean temp array
                            $tempArray['title'] = $tempArray['notes'] = $tempArray['pw'] = $tempArray['username'] = $tempArray['url'] = "";

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
                        //echo $nom.",".$elem." - ";
                        //Check each node name and get data from some of them
                        if ($entry == true && $nom == "Key" && $elem == "Title") {
                            $title = true;
                            $notes = $pw = $url = $username = false;
                        } elseif ($entry == true && $nom == "Key" && $elem == "Notes") {
                            $notes = true;
                            $title = $pw = $url = $username = false;
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
                            $tempArray['group'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                            $tempArray['level'] = $xmlLevel;
                            //build current path
                            if ($xmlLevel > $levelInProgress) {
                                if (!empty($tempArray['path'])) {
                                    $tempArray['path'] .= $foldersSeparator.$tempArray['group'];
                                } else {
                                    $tempArray['path'] = $tempArray['group'];
                                }
                            } elseif ($xmlLevel == $levelInProgress) {
                                if ($levelInProgress == 3) {
                                    $tempArray['path'] = $tempArray['group'];
                                } else {
                                    $tempArray['path'] = substr($tempArray['path'], 0, strrpos($tempArray['path'], $foldersSeparator)+strlen($foldersSeparator)).$tempArray['group'];
                                }
                            } else {
                                $diff = abs($xmlLevel-$levelInProgress)+1;
                                $tmp = explode($foldersSeparator, $tempArray['path']);
                                $tempArray['path'] = "";
                                for ($x=0; $x<(count($tmp)-$diff); $x++) {
                                    if (!empty($tempArray['path'])) {
                                        $tempArray['path'] = $tempArray['path']. $foldersSeparator.$tmp[$x];
                                    } else {
                                        $tempArray['path'] = $tmp[$x];
                                    }
                                }
                                if (!empty($tempArray['path'])) {
                                    $tempArray['path'] .= $foldersSeparator.$tempArray['group'];
                                } else {
                                    $tempArray['path'] = $tempArray['group'];
                                }
                            }

                            //store folders
                            if (!in_array($tempArray['path'], $groupsArray)) {
                                fwrite($cacheFileF, $tempArray['path']."\n");

                                //increment number
                                $numGroups ++;
                            }

                            //Store actual level
                            $levelInProgress = $xmlLevel;
                            $previousLevel = $tempArray['group'];
                        } elseif ($title == true && $nom == "Value") {
                            $title = false;
                            $tempArray['title'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                        } elseif ($notes == true && $nom == "Value") {
                            $notes = false;
                            $tempArray['notes'] = addslashes(preg_replace('#[\r\n]#', $lineEndSeparator, $elem));
                        } elseif ($pw == true && $nom == "Value") {
                            $pw = false;
                            $tempArray['pw'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                        } elseif ($url == true && $nom == "Value") {
                            $url = false;
                            $tempArray['url'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                        } elseif ($username == true && $nom == "Value") {
                            $username = false;
                            $tempArray['username'] = addslashes(preg_replace('#[\r\n]#', '', $elem));
                        }
                    }
                }
            }
        }

        // Go through each node of XML file
        recursiveKeepassXML($xml);

        //Stop if not a keepass file
        if ($generatorFound == false) {
            //Close file & delete it
            fclose($cacheFileF);
            fclose($cacheFile);
            unlink($cacheFile);
            unlink($cacheFileF);
            unlink($_SESSION['settings']['url_to_files_folder']."/".$_POST['file']);

            echo '[{"error":"not_kp_file" , "message":"'.$txt['import_error_no_read_possible_kp'].'"}]';
            break;
        }

        //save last item
        if (!empty($tempArray['title'])) {
            //store data
            fputs(
                $cacheFile,
                $tempArray['path'].$itemsSeparator.$tempArray['group'].$itemsSeparator.
                $tempArray['title'].$itemsSeparator.$tempArray['pw'].$itemsSeparator.$tempArray['username'].
                $itemsSeparator.$tempArray['notes'].$itemsSeparator.$tempArray['url']."\n"
            );

            //increment number
            $numItems++;
        }

        ##################
        ## STARTING IMPORTING IF NO ERRORS OR NOT EMPTY
        ##################
        if ($numItems>0 || $numGroups>0) {
            $itemsArray = array();
            $text = '<img src="includes/images/folder_open.png" alt="" \>&nbsp;'.$txt['nb_folders'].': '.
                $numGroups.'<br /><img src="includes/images/tag.png" alt="" \>&nbsp;'.$txt['nb_items'].': '.
                $numItems.'<br /><br />';
            $text .= '<img src="includes/images/magnifier.png" alt="" \>&nbsp;<span onclick="toggle_importing_details()">'.
                $txt['importing_details'].'</span><div id="div_importing_kp_details" style="display:none;margin-left:20px;"><b>'.
                $txt['importing_folders'].':</b><br />';

            //if destination is not ROOT then get the complexity level
            if ($_POST['destination'] > 0) {
                $data = $db->fetchRow(
                    "SELECT m.valeur as value, t.nlevel as nlevel
                    FROM ".$pre."misc as m
                    INNER JOIN ".$pre."nested_tree as t ON (m.intitule = t.id)
                    WHERE m.type = 'complex'
                    AND m.intitule = '".$_POST['destination']."'"
                );
                $levelPwComplexity = $data[0];
                $startPathLevel = $data[1];
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
                        $parent_id = $foldersArray[$path[$folderLevel-2]]['id'];
                    } else {
                        $fold = $folder;
                        $parent_id = $_POST['destination']; //permits to select the folder destination
                    }

                    //create folder - if not exists at the same level
                    //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree WHERE nlevel = ".($folderLevel+$startPathLevel)." AND title = \"".$fold."\" AND parent_id = ".$parent_id);
                    $data = $db->queryCount(
                        "nested_tree",
                        array(
                            "nlevel" => intval($folderLevel+$startPathLevel),
                            "title" => $fold,
                            "parent_id" => intval(parent_id)
                        )
                    );
                    if ($data[0] == 0) {
                        //do query
                        $id = $db->queryInsert(
                            "nested_tree",
                            array(
                                'parent_id' => $parent_id,
                                'title' => stripslashes($fold),
                                'nlevel' => $folderLevel
                           )
                        );
                        //Add complexity level => level is set to "medium" by default.
                        $db->queryInsert(
                            'misc',
                            array(
                                'type' => 'complex',
                                'intitule' => $id,
                                'valeur' => $levelPwComplexity
                           )
                        );

                        //For each role to which the user depends on, add the folder just created.
                        foreach ($_SESSION['arr_roles'] as $role) {
                            $db->queryInsert(
                                "roles_values",
                                array(
                                    'role_id' => $role['id'],
                                    'folder_id' => $id
                               )
                            );
                        }

                        //Add this new folder to the list of visible folders for the user.
                        array_push($_SESSION['groupes_visibles'], $id);

                        //show
                        $text .= '- '.(($fold)).'<br />';
                        //increment number of imported folders
                        $nbFoldersImported++;
                    } else {
                        //get forlder actual ID
                        // $data = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE nlevel = '".($folderLevel+$startPathLevel)."' AND title = '".$fold."' AND parent_id = '".$parent_id."'");
                        $row = $db->queryGetRow(
                            "nested_tree",
                            array(
                                "id"
                            ),
                            array(
                                "nlevel" => intval($folderLevel+$startPathLevel),
                                "title" => $fold,
                                "parent_id" => intval($parent_id)
                            )
                        );
                        $id = $data[0];
                    }

                    //store in array
                    $foldersArray[$fold] = array(
                        'folder' => $fold,
                        'nlevel' => $folderLevel,
                        'id' => $id
                    );

                    $_SESSION['nb_folders'] ++;
                    $i++;
                }
            }


            //if no new folders them inform
            if ($nbFoldersImported == 0) {
                $text .= $txt['none'].'<br />';
            } else {
                //Refresh the rights of actual user
                identifyUserRights(implode(';', $_SESSION['groupes_visibles']).';'.$newId, $_SESSION['groupes_interdits'], $_SESSION['is_admin'], $_SESSION['fonction_id'], true);

                //rebuild full tree
                $tree->rebuild();
            }
            //show
            $text .= '<br /><b>'.$txt['importing_items'].':</b><br />';

            // Now import ITEMS
            $nbItemsImported = 0;

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
                $item = fgets($cacheFile, 4096);
                $item = explode($itemsSeparator, str_replace(array("\r\n", "\n", "\r"), '', $item));

                if (!empty($item[2])) {
                    //check if not exists
                    //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."items WHERE id_tree = '".$foldersArray[$item[1]]['id']."' AND label = \"".$item[2]."\"");
                    $data = $db->queryCount(
                        "items",
                        array(
                            "id_tree" => intval($foldersArray[$item[1]]['id']),
                            "label" => $item[2]
                        )
                    );
                    if ($data[0] == 0) {
                        //Encryption key
                        $randomKey = generateKey();
                        $pw = $randomKey.$item[3];

                        //ADD item
                        $newId = $db->queryInsert(
                            'items',
                            array(
                                'label' => stripslashes($item[2]),
                                'description' => str_replace($lineEndSeparator, '<br />', $item[5]),
                                'pw' => encrypt($pw),
                                'url' => stripslashes($item[6]),
                                'id_tree' => count($foldersArray)==0 ? $_POST['destination'] : $foldersArray[$item[1]]['id'],
                                'login' => stripslashes($item[4]),
                                'anyone_can_modify' => $_POST['import_kps_anyone_can_modify'] == "true" ? 1 : 0
                           )
                        );

                        //Store generated key
                        $db->queryInsert(
                            'keys',
                            array(
                                'table' => 'items',
                                'id' => $newId,
                                'rand_key' => $randomKey
                           )
                        );

                        //if asked, anyone in role can modify
                        if (isset($_POST['import_kps_anyone_can_modify_in_role']) && $_POST['import_kps_anyone_can_modify_in_role'] == "true") {
                            foreach ($_SESSION['arr_roles'] as $role) {
                                $db->queryInsert(
                                    'restriction_to_roles',
                                    array(
                                        'role_id' => $role['id'],
                                        'item_id' => $newId
                                   )
                                );
                            }
                        }

                        //Add log
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $newId,
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_creation',
                                'raison' => 'at_import'
                           )
                        );

                        //Get folder label
                        if (count($foldersArray)==0) {
                            $folderId = $_POST['destination'];
                        } else {
                            $folderId = $foldersArray[$item[1]]['id'];
                        }
                        // $data = $db->fetchRow("SELECT title FROM ".$pre."nested_tree WHERE id = '".$folderId."'");
                        $data = $db->queryGetRow(
                            "nested_tree",
                            array(
                                "title"
                            ),
                            array(
                                "id" => intval($folderId)
                            )
                        );

                        //Add entry to cache table
                        $db->queryInsert(
                            'cache',
                            array(
                                'id' => $newId,
                                'label' => stripslashes($item[2]),
                                'description' => str_replace($lineEndSeparator, '<br />', $item[5]),
                                'id_tree' => $folderId,
                                'perso' => $personalFolder == 0 ? 0 : 1,
                                'login' => stripslashes($item[4]),
                                'folder' => $data[0],
                                'author' => $_SESSION['user_id']
                           )
                        );

                        //show
                        $text .= '- '.addslashes($item[2]).'<br />';

                        //increment number of imported items
                        $nbItemsImported++;
                    }
                }
            }

            //if no new items them inform
            if ($nbItemsImported == 0) {
                $text .= $txt['none'].'<br />';
            }

            //SHow finished
            $text .= '</div><br /><br /><b>'.$txt['import_kp_finished'].'</b>';

            //Delete cache file
            fclose($cacheFileF);
            fclose($cacheFile);
            unlink($cacheFile);
            unlink($cacheFileF);
            unlink($_SESSION['settings']['url_to_files_folder']."/".$_POST['file']);

            //Display all messages to user
            echo '[{"error":"no" , "message":"'.str_replace('"', "&quote;", strip_tags($text, '<br /><a><div><b><br>')).'"}]';
        } else {
            echo '[{"error":"yes" , "message":""}]';
        }
        break;
}
