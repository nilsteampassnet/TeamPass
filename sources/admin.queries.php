<?php

/**
 * @file          admin.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link    	  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

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

switch ($_POST['type']) {
    #CASE for getting informations about the tool
    # connection to author's cpassman website
    case "cpm_status":
        $text = "<ul>";
        $error ="";
        if (!isset($k['admin_no_info']) || (isset($k['admin_no_info']) && $k['admin_no_info'] == 0)) {
            if (isset($_SESSION['settings']['get_tp_info']) && $_SESSION['settings']['get_tp_info'] == 1) {
                $handleDistant = array();
                if (isset($_SESSION['settings']['proxy_ip']) && !empty($_SESSION['settings']['proxy_ip'])) {
                    $fp = fsockopen($_SESSION['settings']['proxy_ip'], $_SESSION['settings']['proxy_port']);
                } else {
                    $fp = @fsockopen("www.teampass.net", 80);
                }
                if (!$fp) {
                    $error = "connection";
                } else {
                    $out = "GET http://www.teampass.net/TP/cpm2_config.txt HTTP/1.0\r\n";
                    $out .= "Host: www.teampass.net\r\n";
                    $out .= "Connection: Close\r\n\r\n";
                    fwrite($fp, $out);

                    while (($line = fgets($fp, 4096)) !== false) {
                        $handleDistant[] = $line;
                    }
                    if (!feof($fp)) {
                        $error = "Error: unexpected fgets() fail\n";
                    }
                    fclose($fp);
                }

                if (count($handleDistant) > 0) {
                    while (list($cle,$val) = each($handleDistant)) {
                        if (substr($val, 0, 3) == "nom") {
                            $tab = explode('|', $val);
                            foreach ($tab as $elem) {
                                $tmp = explode('#', $elem);
                                $text .= '<li><u>'.$txt[$tmp[0]]."</u> : ".$tmp[1].'</li>';
                                if ($tmp[0] == "version") {
                                    $text .= '<li><u>'.$txt['your_version']."</u> : ".$k['version'];
                                    if (floatval($k['version']) < floatval($tmp[1])) {
                                        $text .= '&nbsp;&nbsp;<b>'.$txt['please_update'].'</b><br />';
                                    }
                                    $text .= '</li>';
                                }
                            }
                        }
                    }
                }
            } else {
                $error = "conf_block";
            }
        } else {
            $error = "conf_block";
        }

        echo '[{"error":"'.$error.'" , "output":"'.$text.'"}]';
        break;

    ###########################################################
    #CASE for refreshing all Personal Folders
    case "admin_action_check_pf":
        //get through all users
        $rows = $db->fetchAllArray("SELECT id,login,email FROM ".$pre."users ORDER BY login ASC");
        foreach ($rows as $record) {
            //update PF field for user
            $db->queryUpdate(
                'users',
                array(
                    'personal_folder' => '1'
               ),
                "id='".$record['id']."'"
            );

            //if folder doesn't exist then create it
            //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree WHERE title = '".$record['id']."' AND parent_id = 0");
            $data = $db->queryCount(
                "nested_tree",
                array(
                    "title" => $record['id'],
                    "parent_id" => 0
                )
            );
            if ($data[0] == 0) {
                //If not exist then add it
                $db->queryInsert(
                    "nested_tree",
                    array(
                        'parent_id' => '0',
                        'title' => $record['id'],
                        'personal_folder' => '1'
                   )
                );
            } else {
                //If exists then update it
                $db->queryUpdate(
                    'nested_tree',
                    array(
                        'personal_folder' => '1'
                   ),
                    array(
                        "title" =>$record['id'],
                        'parent_id' => '0'
                   )
                );
            }
        }

        //Delete PF for deleted users
        $db->query(
            "SELECT COUNT(*) FROM ".$pre."nested_tree as t
            LEFT JOIN ".$pre."users as u ON t.title = u.id
            WHERE u.id IS null AND t.parent_id=0 AND t.title REGEXP '^[0-9]'"
        );

        //rebuild fuild tree folder
        $tree->rebuild();

        echo '[{"result" : "pf_done"}]';
        break;

    ###########################################################
    #CASE for deleting all items from DB that are linked to a folder that has been deleted
    case "admin_action_db_clean_items":
        //Libraries call
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

        //init
        $foldersIds = array();
        $text = "";
        $nbItemsDeleted = 0;

        // Get an array of all folders
        $folders = $tree->getDescendants();
        foreach ($folders as $folder) {
            if (!in_array($folder->id, $foldersIds)) {
                array_push($foldersIds, $folder->id);
            }
        }

        $items = $db->fetchAllArray("SELECT id,label FROM ".$pre."items WHERE id_tree NOT IN(".implode(',', $foldersIds).")");
        foreach ($items as $item) {
            $text .= $item['label']."[".$item['id']."] - ";
            //Delete item
            $db->query("DELETE FROM ".$pre."items WHERE id = ".$item['id']);
            //log
            $db->query("DELETE FROM ".$pre."log_items WHERE id_item = ".$item['id']);

            $nbItemsDeleted++;
        }

        // delete orphan items
        $rows = $db->fetchAllArray(
            "SELECT id
            FROM ".$pre."items
            ORDER BY id ASC"
        );
        foreach ($rows as $item) {
            //$row = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item=".$item['id']." AND action = 'at_creation'");
            $row = $db->queryCount(
                "log_items",
                array(
                    "id_item" => $item['id'],
                    "action" => "at_creation"
                )
            );
            if ($row[0] == 0) {
                $db->query("DELETE FROM ".$pre."items WHERE id = ".$item['id']);
                $db->query("DELETE FROM ".$pre."categories_items WHERE item_id = ".$item['id']);
                $db->query("DELETE FROM ".$pre."log_items WHERE id_item = ".$item['id']);
                $nbItemsDeleted++;
            }
        }

        //Update CACHE table
        updateCacheTable("reload");

        //show some info
        echo '[{"result" : "db_clean_items","nb_items_deleted":"'.$nbItemsDeleted.'"}]';
        break;

    ###########################################################
    #CASE for creating a DB backup
    case "admin_action_db_backup":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
        $return = "";

        //Get all tables
        /*$tables = array();
        $result = mysql_query('SHOW TABLES');
        while ($row = mysql_fetch_row($result)) {
            $tables[] = $row[0];
        }*/
        $tables = array();
        $result = $db->query('SHOW TABLES');
        while ($row = $db->fetchArray($result)) {
            $tables[] = $row["Tables_in_".$database];
        }

        //cycle through
        foreach ($tables as $table) {
            if (empty($pre) || substr_count($table, $pre) > 0) {
                $result = $db->query('SELECT * FROM '.$table);
                $numFields = $db->queryNumFields('SELECT * FROM '.$table);
                // prepare a drop table
                $return.= 'DROP TABLE '.$table.';';
                $row2 = $db->queryFirst('SHOW CREATE TABLE '.$table);
                $return.= "\n\n".$row2["Create Table"].";\n\n";

                //prepare all fields and datas
                for ($i = 0; $i < $numFields; $i++) {
                    while ($row = mysql_fetch_row($result)) {
                        $return.= 'INSERT INTO '.$table.' VALUES(';
                        for ($j=0; $j<$numFields; $j++) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = preg_replace("/\n/", "\\n", $row[$j]);
                            if (isset($row[$j])) {
                                $return.= '"'.$row[$j].'"';
                            } else {
                                $return.= '""';
                            }
                            if ($j<($numFields-1)) {
                                $return.= ',';
                            }
                        }
                        $return.= ");\n";
                    }
                }
                $return.="\n\n\n";
            }
        }

        if (!empty($return)) {
            //save file
            $filename = 'db-backup-'.time().'.sql';
            $handle = fopen($_SESSION['settings']['path_to_files_folder']."/".$filename, 'w+');

            //Encrypt the file
            if (!empty($_POST['option'])) {
                $return = encrypt($return, $_POST['option']);
            }

            //write file
            fwrite($handle, $return);
            fclose($handle);

            //generate 2d key
            $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
            $pwgen->register();
            $pwgen = new Encryption\PwGen\pwgen();
            $pwgen->setLength(20);
            $pwgen->setSecure(true);
            $pwgen->setSymbols(false);
            $pwgen->setCapitalize(true);
            $pwgen->setNumerals(true);
            $_SESSION['key_tmp'] = $pwgen->generate();

            //update LOG
            $db->queryInsert(
                'log_system',
                array(
                    'type' => 'admin_action',
                    'date' => time(),
                    'label' => 'dataBase backup',
                    'qui' => $_SESSION['user_id']
               )
            );

            echo '[{"result":"db_backup" , "href":"sources/downloadFile.php?name='.urlencode($filename).'&sub=files&file='.$filename.'&type=sql&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'&pathIsFiles=1"}]';
        }
        break;

    ###########################################################
    #CASE for restoring a DB backup
    case "admin_action_db_restore":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

        $dataPost = explode('&', $_POST['option']);
        $file = $dataPost[0];
        $key = $dataPost[1];

        //create uncrypted file
        if (!empty($key)) {
            //read full file
            $fileArray = file($_SESSION['settings']['path_to_files_folder']."/".$file);

            //delete file
            unlink($_SESSION['settings']['path_to_files_folder']."/".$file);

            //create new file with uncrypted data
            $file = $_SESSION['settings']['path_to_files_folder']."/".time().".log";
            $inF = fopen($file, "w");
            while (list($cle, $val) = each($fileArray)) {
                fputs($inF, decrypt($val, $key)."\n");
            }
            fclose($inF);
        }

        //read sql file
        if ($handle = fopen($_SESSION['settings']['path_to_files_folder']."/".$file, "r")) {
            $query = "";
            while (!feof($handle)) {
                $query.= fgets($handle, 4096);
                if (substr(rtrim($query), -1) == ';') {
                    //launch query
                    $db->query($query);
                    $query = '';
                }
            }
            fclose($handle);
        }

        //delete file
        unlink($_SESSION['settings']['path_to_files_folder']."/".$file);

        //Show done
        echo '[{"result":"db_restore"}]';
        break;

    ###########################################################
    #CASE for optimizing the DB
    case "admin_action_db_optimize":
        //Get all tables
        $alltables = mysql_query("SHOW TABLES");
        while ($table = mysql_fetch_assoc($alltables)) {
            foreach ($table as $i => $tablename) {
                if (substr_count($tablename, $pre) > 0) {
                    // launch optimization quieries
                    mysql_query("ANALYZE TABLE `".$tablename."`");
                    mysql_query("OPTIMIZE TABLE `".$tablename."`");
                }
            }
        }

        //Clean up LOG_ITEMS table
        $rows = $db->fetchAllArray(
            "SELECT id
            FROM ".$pre."items
            ORDER BY id ASC"
        );
        foreach ($rows as $item) {
            //$row = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item=".$item['id']." AND action = 'at_creation'");
            $row = $db->queryCount(
                "log_items",
                array(
                    "id_item" => $item['id'],
                    "action" => "at_creation"
                )
            );
            if ($row[0] == 0) {
                //Create new at_creation entry
                $rowTmp = $db->queryFirst("SELECT date FROM ".$pre."log_items WHERE id_item=".$item['id']." ORDER BY date ASC");
                $db->queryInsert(
                    'log_items',
                    array(
                        'id_item'     => $item['id'],
                        'date'         => $rowTmp['date']-1,
                        'id_user'     => "",
                        'action'     => "at_creation",
                        'raison'    => ""
                   )
                );
            }
        }

        //Show done
        echo '[{"result":"db_optimize"}]';
        break;

    ###########################################################
    #CASE for deleted old files in folder "files"
    case "admin_action_purge_old_files":
        $nbFilesDeleted = 0;

        //read folder
        $dir = opendir($_SESSION['settings']['path_to_files_folder']);

        //delete file
        while ($f = readdir($dir)) {
            if (is_file($dir.$f) && (time()-filectime($dir.$f)) > 604800) {
                unlink($dir.$f);
                $nbFilesDeleted++;
            }
        }
        //Close dir
        closedir($dir);

        //Show done
        echo '[{"result":"purge_old_files","nb_files_deleted":"'.$nbFilesDeleted.'"}]';
        break;

    /*
    * Reload the Cache table
    */
    case "admin_action_reload_cache_table":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
        updateCacheTable("reload", "");
        echo '[{"result":"cache_reload"}]';
        break;

    /*
    * Decrypt a backup file
    */
    case "admin_action_backup_decrypt":
        //get backups infos
        $rows = $db->fetchAllArray("SELECT * FROM ".$pre."misc WHERE type = 'settings'");
        foreach ($rows as $reccord) {
            $settings[$reccord['intitule']] = $reccord['valeur'];
        }

        //read file
        $return = "";
        $Fnm = $settings['bck_script_path'].'/'.$_POST['option'].'.sql';
        if (file_exists($Fnm)) {
            $inF = fopen($Fnm, "r");
            while (!feof($inF)) {
                $return .= fgets($inF, 4096);
            }
            fclose($inF);
            $return = Encryption\Crypt\aesctr::decrypt($return, $settings['bck_script_key'], 256);

            //save the file
            $handle = fopen($settings['bck_script_path'].'/'.$_POST['option'].'_DECRYPTED'.'.sql', 'w+');
            fwrite($handle, $return);
            fclose($handle);
        }
        break;

    /*
    * Change SALT Key
    */
    case "admin_action_change_salt_key":
        $error = "";
        include 'main.functions.php';
        //put tool in maintenance.
            $db->queryUpdate(
                "misc",
                array(
                    'valeur' => '1',
               ),
                "intitule = 'maintenance_mode' AND type= 'admin'"
            );
            //log
            $db->queryInsert(
                "log_system",
                array(
                    'type' => 'system',
                    'date' => time(),
                    'label' => 'change_salt_key',
                    'qui' => $_SESSION['user_id']
               )
            );

        $new_salt_key = htmlspecialchars_decode(Encryption\Crypt\aesctr::decrypt($_POST['option'], SALT, 256));

        //change all passwords in DB
        $rows = $db->fetchAllArray("SELECT id,pw FROM ".$pre."items WHERE perso = '0'");
        foreach ($rows as $reccord) {
            $pw = decrypt($reccord['pw']);
            //encrypt with new SALT
            $db->queryUpdate(
                "items",
                array(
                    'pw' => encrypt($pw, $new_salt_key),
               ),
                "id = '".$reccord['id']."'"
            );
        }
        //change all users password in DB
        $rows = $db->fetchAllArray("SELECT id,pw FROM ".$pre."users");
        foreach ($rows as $reccord) {
            $pw = decrypt($reccord['pw']);
            //encrypt with new SALT
            $db->queryUpdate(
                "users",
                array(
                    'pw' => encrypt($pw, $new_salt_key),
               ),
                "id = '".$reccord['id']."'"
            );
        }

        // get path to sk.php
        $filename = "../includes/settings.php";
        if (file_exists($filename)) {
            //copy some constants from this existing file
            $settings_file = file($filename);
            while (list($key,$val) = each($settings_file)) {
                if (substr_count($val, 'require_once "')>0) {
                    $skfile = substr($val, 14, strpos($val, '";')-14);
                    break;
                }
            }
        }
        //Do a copy of the existing file
        @copy($skfile, $skfile.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))));
        unlink($skfile);
        $fh = fopen($skfile, 'w');
        fwrite(
            $fh,
            utf8_encode(
                "<?php
@define('SALT', '".$new_salt_key."'); //Never Change it once it has been used !!!!!
?>"
            )
        );
        fclose($fh);

        echo '[{"result":"changed_salt_key", "error":"'.$error.'"}]';
        break;

    /*
    * Test the email configuraiton
    */
    case "admin_email_test_configuration":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
        echo '[{"result":"email_test_conf", '.sendEmail($txt['admin_email_test_subject'], $txt['admin_email_test_body'], $_SESSION['user_email']).'}]';
        break;

    /*
    * Send emails in backlog
    */
    case "admin_email_send_backlog":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

        $rows = $db->fetchAllArray("SELECT * FROM ".$pre."emails WHERE status = 'not_sent' OR status = ''");
        foreach ($rows as $reccord) {
            //send email
            $ret = json_decode(
                @sendEmail(
                    $reccord['subject'],
                    $reccord['body'],
                    $reccord['receivers']
                )
            );

            if (!empty($ret['error'])) {
                //update item_id in files table
                $db->queryUpdate(
                    'emails',
                    array(
                        'status' => "not sent"
                   ),
                    "timestamp='".$reccord['timestamp']."'"
                );
            } else {
                //delete from DB
                $db->query("DELETE FROM ".$pre."emails WHERE timestamp = '".$reccord['timestamp']."'");
            }
        }

        //update LOG
        $db->queryInsert(
            'log_system',
            array(
               'type' => 'admin_action',
               'date' => time(),
               'label' => 'Emails backlog',
               'qui' => $_SESSION['user_id']
            )
        );

        echo '[{"result":"admin_email_send_backlog", '.@sendEmail($txt['admin_email_test_subject'], $txt['admin_email_test_body'], $_SESSION['settings']['email_from']).'}]';
        break;

    /*
    * Generate exchanges encryption keys
    */
    case "admin_action_generate_encrypt_keys":
        require_once("../includes/libraries/jCryption/jcryption.php");
        $keyLength = 1024;
        $jCryption = new jCryption();
        $numberOfPairs = 100;
        $arrKeyPairs = array();
        for ($i=0; $i < $numberOfPairs; $i++) {
            $arrKeyPairs[] = $jCryption->generateKeypair($keyLength);
        }
        $file = array();
        $file[] = '<?php';
        $file[] = '$arrKeys = ';
        $file[] = var_export($arrKeyPairs, true);
        $file[] = ';';
        file_put_contents(SECUREPATH."/".$numberOfPairs . "_". $keyLength . "_keys.inc.php", implode("\n", $file));

        echo '[{"result":"generated_keys_file", "error":""}]';
        break;

    /*
    * Correct passwords prefix
    */
    case "admin_action_pw_prefix_correct":
        include 'main.functions.php';
        $numOfItemsChanged = 0;
        // go for all Items and get their PW
        $rows = $db->fetchAllArray("SELECT id, pw FROM ".$pre."items WHERE perso = '0'");
        foreach ($rows as $reccord) {
            // check if key exists for this item
            //$row = @$db->fetchRow("SELECT COUNT(*) FROM ".$pre."keys WHERE `id`='".$reccord['id']."' AND `table` = 'items'");
            $row = $db->queryCount(
                "keys",
                array(
                    "id" => $reccord['id'],
                    "table" => "items"
                )
            );
            if ($row[0] == 0) {
                $storePrefix = false;
                // decrypt pw
                $pw = decrypt($reccord['pw']);
                if (!empty($pw) && strlen($pw) > 15 && isutf8($pw)) {
                    // Pw seems to have a prefix
                    // get old prefix
                    $randomKey = substr($pw, 0, 15);
                    // check if prefix contains only lowercase and numerics
                    //TODO
                    // should we store?
                    $storePrefix = true;
                } elseif (!empty($pw) && isutf8($pw)) {
                    // Pw doesn't seem to have a prefix

                    // re-encrypt with key prefix
                    $randomKey = generateKey();
                    $pw = $randomKey.$pw;
                    $pw = encrypt($pw);

                    // store pw
                    $db->queryUpdate(
                        'items',
                        array(
                            'pw' => $pw
                        ),
                        "id='".$reccord['id']."'"
                    );
                    // should we store?
                    $storePrefix = true;
                }
                if ($storePrefix == true) {
                    // store key prefix
                    $db->queryInsert(
                        'keys',
                        array(
                            'table'     => 'items',
                            'id'        => $reccord['id'],
                            'rand_key'  => $randomKey
                        )
                    );
                }

                $numOfItemsChanged++;
            }
        }
        echo '[{"result":"pw_prefix_correct", "error":"", "ret":"'.$txt['alert_message_done'].' '.$numOfItemsChanged.' '.$txt['items_changed'].'"}]';
        break;

    /*
    * Attachments encryption
    */
    case "admin_action_attachments_cryption":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

        // init
        $error = "";
        $ret = "";
        $cpt = 0;
        $checkCoherancy = false;
        $filesList = "";
        $continu = true;

        // get through files
        if (isset($_POST['option']) && !empty($_POST['option'])) {
            if ($handle = opendir($_SESSION['settings']['path_to_upload_folder'].'/')) {
                while (false !== ($entry = readdir($handle))) {
                    $entry = basename($entry);
                    if ($entry != "." && $entry != ".." && $entry != ".htaccess") {
                        if (strpos($entry, ".") == false) {
                            // check if user query is coherant
                            if ($checkCoherancy == false) {
                                $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$entry, "rb");
                                $line = fgets($fp);

                                // check if isUTF8. If yes, then check if process = encryption, and vice-versa
                                if (isUTF8($line) && $_POST['option'] == "decrypt") {
                                    $error = "file_not_encrypted";
                                    $continu = false;
                                    break;
                                } elseif (!isUTF8($line) && $_POST['option'] == "encrypt") {
                                    $error = "file_not_clear";
                                    $continu = false;
                                    break;
                                }
                                fclose($fp);
                                $checkCoherancy = true;

                                // check if to stop
                                if (!empty($error)) {
                                    break;
                                }
                            }

                            // build list
                            if (empty($filesList)) {
                                $filesList = $entry;
                            } else {
                                $filesList .= ";".$entry;
                            }
                        }
                    }
                }
                closedir($handle);
            }
        } else {
            $error = "No option";
        }

        echo '[{"result":"attachments_cryption", "error":"'.$error.'", "continu":"'.$continu.'", "list":"'.$filesList.'", "cpt":"0"}]';
        break;

        /*
         * Attachments encryption - Treatment in several loops
         */
        case "admin_action_attachments_cryption_continu":
            include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
            require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

            $cpt = 0;
            $newFilesList = "";
            $continu = true;
            $error = "";

            // Prepare encryption options
            $iv = substr(md5("\x1B\x3C\x58".SALT, true), 0, 8);
            $key = substr(
                md5("\x2D\xFC\xD8".SALT, true).
                md5("\x2D\xFC\xD9".SALT, true),
                0,
                24
            );
            $opts = array('iv'=>$iv, 'key'=>$key);

            // treat 10 files
            $filesList = explode(';', $_POST['list']);
            foreach ($filesList as $file) {
                if ($cpt < 5) {
                    // skip file is Coherancey not respected
                    $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$file, "rb");
                    $line = fgets($fp);
                    $skipFile = false;
                    // check if isUTF8. If yes, then check if process = encryption, and vice-versa
                    if (isUTF8($line) && $_POST['option'] == "decrypt") {
                        $skipFile = true;
                    } elseif (!isUTF8($line) && $_POST['option'] == "encrypt") {
                        $skipFile = true;
                    }
                    fclose($fp);

                    if ($skipFile == true) {
                        // make a copy of file
                        if (!copy(
                                $_SESSION['settings']['path_to_upload_folder'].'/'.$file,
                                $_SESSION['settings']['path_to_upload_folder'].'/'.$file.".copy"
                        )) {
                            $error = "Copy not possible";
                            exit;
                        }

                        // Open the file
                        unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$file);
                        $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$file.".copy", "rb");
                        $out = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$file, 'wb');

                        if ($_POST['option'] == "decrypt") {
                            stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts);
                        } else if ($_POST['option'] == "encrypt") {
                            stream_filter_append($out, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);
                        }

                        // read file and create new one
                        $check = false;
                        while (($line = fgets($fp)) !== false) {
                            fputs($out, $line);
                        }
                        fclose($fp);
                        fclose($out);

                        $cpt ++;
                    }
                } else {
                    // build list
                    if (empty($newFilesList)) {
                        $newFilesList = $file;
                    } else {
                        $newFilesList .= ";".$file;
                    }
                }
            }

            if (empty($newFilesList)) $continu = false;

            echo '[{"error":"'.$error.'", "continu":"'.$continu.'", "list":"'.$newFilesList.'", "cpt":"'.($_POST['cpt']+$cpt).'"}]';
            break;
}
