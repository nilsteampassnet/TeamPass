<?php

/**
 * @file          admin.queries.php
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

require_once 'SecureHandler.php';
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

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
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

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
                    $out = "GET http://teampass.net/teampass_ext_lib.txt HTTP/1.0\r\n";
                    $out .= "Host: teampass.net\r\n";
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
                                $text .= '<li><u>'.$LANG[$tmp[0]]."</u> : ".$tmp[1].'</li>';
                                if ($tmp[0] == "version") {
                                    $text .= '<li><u>'.$LANG['your_version']."</u> : ".$k['version'];
                                    if (floatval($k['version']) < floatval($tmp[1])) {
                                        $text .= '&nbsp;&nbsp;<b>'.$LANG['please_update'].'</b>';
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
        $text .= "</ul>";

        echo '[{"error":"'.$error.'" , "output":"'. str_replace(array("\n", "\t", "\r"), '', $text).'"}]';
        break;

    ###########################################################
    #CASE for refreshing all Personal Folders
    case "admin_action_check_pf":
        //get through all users
        $rows = DB::query(
            "SELECT id, login, email
            FROM ".prefix_table("users")."
            ORDER BY login ASC"
        );
        foreach ($rows as $record) {
            //update PF field for user
            DB::update(
                prefix_table("users"),
                array(
                    'personal_folder' => '1'
               ),
                "id = %i",
                $record['id']
            );

            //if folder doesn't exist then create it
            $data = DB::queryfirstrow(
                "SELECT id
                FROM ".prefix_table("nested_tree")."
                WHERE title = %s AND parent_id = %i",
                $record['id'],
                0
            );
            $counter = DB::count();
            if ($counter == 0) {
                //If not exist then add it
                DB::insert(
                    prefix_table("nested_tree"),
                    array(
                        'parent_id' => '0',
                        'title' => $record['id'],
                        'personal_folder' => '1'
                   )
                );

                //rebuild fuild tree folder
                $tree->rebuild();
            } else {
                //If exists then update it
                DB::update(
                    prefix_table("nested_tree"),
                    array(
                        'personal_folder' => '1'
                   ),
                   "title=%s AND parent_id=%i",
                   $record['id'],
                   0
                );
                //rebuild fuild tree folder
                $tree->rebuild();

                // Get an array of all folders
                $folders = $tree->getDescendants($data['id'], false, true, true);
                foreach ($folders as $folder) {
                 //update PF field for user
                    DB::update(
                        prefix_table("nested_tree"),
                        array(
                            'personal_folder' => '1'
                       ),
                        "id = %s",
                        $folder
                    );
                }
            }
        }


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

        $items = DB::query("SELECT id,label FROM ".prefix_table("items")." WHERE id_tree NOT IN %li", $foldersIds);
        foreach ($items as $item) {
            $text .= $item['label']."[".$item['id']."] - ";
            //Delete item
            DB::DELETE(prefix_table("items"), "id = %i", $item['id']);
            //log
            DB::DELETE(prefix_table("log_items"), "id_item = %i", $item['id']);

            $nbItemsDeleted++;
        }

        // delete orphan items
        $rows = DB::query(
            "SELECT id
            FROM ".prefix_table("items")."
            ORDER BY id ASC"
        );
        foreach ($rows as $item) {
            DB::query(
                "SELECT * FROM ".prefix_table("log_items")." WHERE id_item = %i AND action = %s",
                $item['id'],
                "at_creation"
            );
            $counter = DB::count();
            if ($counter == 0) {
                DB::DELETE(prefix_table("items"), "id = %i", $item['id']);
                DB::DELETE(prefix_table("categories_items"), "item_id = %i", $item['id']);
                DB::DELETE(prefix_table("log_items"), "id_item = %i", $item['id']);
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
        $tables = array();
        $result = DB::query('SHOW TABLES');
        foreach ($result as $row) {
            $tables[] = $row["Tables_in_".$database];
        }

        //cycle through
        foreach ($tables as $table) {
            if (empty($pre) || substr_count($table, $pre) > 0) {

                $result = DB::queryRaw('SELECT * FROM '.$table);
                $mysqli_result = DB::queryRaw(
                    "SELECT *
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE table_schema = %s
                    AND table_name = %s",
                    $database,
                    $table
                );
                $numFields = DB::count();

                // prepare a drop table
                $return.= 'DROP TABLE '.$table.';';
                $row2 = DB::queryfirstrow('SHOW CREATE TABLE '.$table);
                $return.= "\n\n".$row2["Create Table"].";\n\n";

                //prepare all fields and datas
                for ($i = 0; $i < $numFields; $i++) {
                    while ($row = $result->fetch_row()) {
                        $return.= 'INSERT INTO '.$table.' VALUES(';
                        for ($j=0; $j<$numFields; $j++) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = preg_replace("/\n/", "\\n", $row[$j]);
                            if (isset($row[$j])) {
                                $return.= '"'.$row[$j].'"';
                            } else {
                                $return.= 'NULL';
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
            // get a token
            $token = GenerateCryptKey(20);

            //save file
            $filename = time().'-'.$token.'.sql';
            $handle = fopen($_SESSION['settings']['path_to_files_folder']."/".$filename, 'w+');

            //Encrypt the file
            if (!empty($_POST['option'])) {
                $return = encrypt($return, $_POST['option']);
            }

            //write file
            fwrite($handle, $return);
            fclose($handle);

            //generate 2d key
            $_SESSION['key_tmp'] = GenerateCryptKey(20, true);

            //update LOG
            logEvents('admin_action', 'dataBase backup', $_SESSION['user_id'], $_SESSION['login']);

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
            $file = $_SESSION['settings']['path_to_files_folder']."/".time().".txt";
            $inF = fopen($file, "w");
            while (list($cle, $val) = each($fileArray)) {
                fputs($inF, decrypt($val, $key)."\n");
            }
            fclose($inF);
        } else {
            $file = $_SESSION['settings']['path_to_files_folder']."/".$file;
        }

        //read sql file
        if ($handle = fopen($file, "r")) {
            $query = "";
            while (!feof($handle)) {
                $query.= fgets($handle, 4096);
                if (substr(rtrim($query), -1) == ';') {
                    //launch query
                    DB::queryRaw($query);
                    $query = '';
                }
            }
            fclose($handle);
        }

        //delete file
        unlink($file);

        //Show done
        echo '[{"result":"db_restore"}]';
        break;

    ###########################################################
    #CASE for optimizing the DB
    case "admin_action_db_optimize":
        //Get all tables
        $alltables = DB::query("SHOW TABLES");
        foreach ($alltables as $table) {
            foreach ($table as $i => $tablename) {
                if (substr_count($tablename, $pre) > 0) {
                    // launch optimization quieries
                    DB::query("ANALYZE TABLE `".$tablename."`");
                    DB::query("OPTIMIZE TABLE `".$tablename."`");
                }
            }
        }

        //Clean up LOG_ITEMS table
        $rows = DB::query(
            "SELECT id
            FROM ".prefix_table("items")."
            ORDER BY id ASC"
        );
        foreach ($rows as $item) {
            //$row = DB::fetchRow("SELECT COUNT(*) FROM ".prefix_table("log_items")." WHERE id_item=".$item['id']." AND action = 'at_creation'");
            DB::query(
                "SELECT * FROM ".prefix_table("log_items")." WHERE id_item = %i AND action = %s",
                $item['id'],
                "at_creation"
            );
            $counter = DB::count();
            if ($counter == 0) {
                //Create new at_creation entry
                $rowTmp = DB::queryFirstRow(
                    "SELECT date FROM ".prefix_table("log_items")." WHERE id_item=%i ORDER BY date ASC",
                    $item['id']
                );
                DB::insert(
                    prefix_table("log_items"),
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
       * REBUILD CONFIG FILE
    */
    case "admin_action_rebuild_config_file":
        $error = "";

        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
        $ret = handleConfigFile ("rebuild");

        if ($ret !== true) $error = $ret;
        else $error = "rebuild_config_file";

        echo '[{"result":"'.$error.'"}]';
        break;


    /*
    * Decrypt a backup file
    */
    case "admin_action_backup_decrypt":
        //get backups infos
        $rows = DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s", "settings");
        foreach ($rows as $record) {
            $settings[$record['intitule']] = $record['valeur'];
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
    * Change SALT Key START
    */
    case "admin_action_change_salt_key___start":
        $error = "";
        $_SESSION['reencrypt_old_salt'] = require_once 'main.functions.php';

        // store old sk
        $_SESSION['reencrypt_old_salt'] = file_get_contents(SECUREPATH."/teampass-seckey.txt");

        // generate new saltkey
        copy(
            SECUREPATH."/teampass-seckey.txt",
            SECUREPATH."/teampass-seckey.txt".'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))).'.'.time()
        );
        $_SESSION['reencrypt_new_salt'] = defuse_generate_key();
        file_put_contents(
            SECUREPATH."/teampass-seckey.txt",
            $_SESSION['reencrypt_new_salt']
        );

        //put tool in maintenance.
        DB::update(
            prefix_table("misc"),
            array(
                'valeur' => '1',
           ),
            "intitule = %s AND type= %s",
            "maintenance_mode", "admin"
        );
        //log
        logEvents('system', 'change_salt_key', $_SESSION['user_id'], $_SESSION['login']);

        // get number of items to change
        DB::query("SELECT id FROM ".prefix_table("items")." WHERE perso = %i", 0);

        echo '[{"nextAction":"encrypt_items" , "error":"'.$error.'" , "nbOfItems":"'.DB::count().'"}]';
        break;

    /*
    * Change SALT Key - ENCRYPT
    */
    case "admin_action_change_salt_key___encrypt_files":
        $error = "";
        require_once 'main.functions.php';

        // prepare SK
        if (empty($_SESSION['reencrypt_new_salt']) || empty($_SESSION['reencrypt_old_salt'])) {
            // SK is not correct
            echo '[{"nextAction":"" , "error":"saltkeys are empty???" , "nbOfItems":""}]';
            break;
        }

        //change all passwords in DB
        $rows = DB::query("
            SELECT id, pw, pw_iv
            FROM ".prefix_table("items")."
            WHERE perso = %s
            LIMIT ".filter_var($_POST['start'], FILTER_SANITIZE_NUMBER_INT) .", ". filter_var($_POST['length'], FILTER_SANITIZE_NUMBER_INT),
            "0"
        );
        foreach ($rows as $record) {
            $pw = cryption(
                $record['pw'],
                $_SESSION['reencrypt_old_salt'],
                "decrypt"
            );
            //encrypt with new SALT
            $encrypt = cryption(
                $pw['string'],
                $_SESSION['reencrypt_new_salt'],
                "encrypt"
            );
            DB::update(
                prefix_table("items"),
                array(
                    'pw' => $encrypt['string'],
                    'pw_iv' => ""
               ),
                "id = %i",
                $record['id']
            );
        }

        $nextStart = intval($_POST['start']) + intval($_POST['length']);

        // check if last item to change has been treated
        if ($nextStart >= intval($_POST['nbItems'])) {
            $nextAction = "finishing";
        } else {
            $nextAction = "encrypting";
        }

        echo '[{"nextAction":"'.$nextAction.'" , "nextStart":"'.$nextStart.'", "error":"'.$error.'"}]';
        break;

    /*
    * Change SALT Key - END
    */
    case "admin_action_change_salt_key___end":
        $error = "";

        // quit maintenance mode.
        DB::update(
            prefix_table("misc"),
            array(
                'valeur' => '0',
           ),
            "intitule = %s AND type= %s",
            "maintenance_mode", "admin"
        );

        // redefine SALT
        @define(SALT, $new_salt_key);

        echo '[{"nextAction":"done" , "error":"'.$error.'"}]';
        break;

    /*
    * Test the email configuraiton
    */
    case "admin_email_test_configuration":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
        echo '[{"result":"email_test_conf", '.sendEmail($LANG['admin_email_test_subject'], $LANG['admin_email_test_body'], $_SESSION['user_email']).'}]';
        break;

    /*
    * Send emails in backlog
    */
    case "admin_email_send_backlog":
        require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

        $rows = DB::query("SELECT * FROM ".prefix_table("emails")." WHERE status = %s OR status = %s", "not_sent", "");
        foreach ($rows as $record) {
            //send email
            $ret = json_decode(
                @sendEmail(
                    $record['subject'],
                    $record['body'],
                    $record['receivers']
                )
            );

            if (!empty($ret['error'])) {
                //update item_id in files table
                DB::update(
                    prefix_table("emails"),
                    array(
                        'status' => "not_sent"
                   ),
                    "timestamp = %s",
                    $record['timestamp']
                );
            } else {
                //delete from DB
                DB::delete(prefix_table("emails"), "timestamp = %s", $record['timestamp']);
            }
        }

        //update LOG
    logEvents('admin_action', 'Emails backlog', $_SESSION['user_id'], $_SESSION['login']);

        echo '[{"result":"admin_email_send_backlog", '.@sendEmail($LANG['admin_email_test_subject'], $LANG['admin_email_test_body'], $_SESSION['settings']['email_from']).'}]';
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
    /*case "admin_action_pw_prefix_correct":
        require_once 'main.functions.php';
        $numOfItemsChanged = 0;
        // go for all Items and get their PW
        $rows = DB::query("SELECT id, pw, pw_iv FROM ".prefix_table("items")." WHERE perso = %s", "0");
        foreach ($rows as $record) {
            // check if key exists for this item
            DB::query("SELECT * FROM ".prefix_table("keys")." WHERE `id` = %i AND `sql_table` = %s", $record['id'], "items");
            $counter = DB::count();
            if ($counter == 0) {
                $storePrefix = false;
                // decrypt pw
                $pw = cryption($record['pw'], SALT, $record['pw_iv'], "decrypt");
                if (!empty($pw['string']) && strlen($pw['string']) > 15 && isutf8($pw['string'])) {
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
                    DB::update(
                        prefix_table("items"),
                        array(
                            'pw' => $pw
                        ),
                        "id=%s",
                        $record['id']
                    );
                    // should we store?
                    $storePrefix = true;
                }
                if ($storePrefix == true) {
                    // store key prefix
                    DB::insert(
                        prefix_table("keys"),
                        array(
                            'table'     => 'items',
                            'id'        => $record['id'],
                            'rand_key'  => $randomKey
                        )
                    );
                }

                $numOfItemsChanged++;
            }
        }
        echo '[{"result":"pw_prefix_correct", "error":"", "ret":"'.$LANG['alert_message_done'].' '.$numOfItemsChanged.' '.$LANG['items_changed'].'"}]';
        break;*/

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
                    if ($entry != "." && $entry != ".." && $entry != ".htaccess" && $entry != ".gitignore") {
                        if (strpos($entry, ".") == false) {
                            // check if user query is coherant
                            if ($checkCoherancy == false) {
                                $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$entry, "rb");
                                $line = fgets($fp);
$line = "qsd";
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
            include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
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
                    if (!isUTF8($line) && $_POST['option'] == "decrypt") {
                        $skipFile = true;
                    } elseif (isUTF8($line) && $_POST['option'] == "encrypt") {
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

        /*
         * API save key
         */
        case "admin_action_api_save_key":
            $error = "";
            // add new key
            if (isset($_POST['action']) && $_POST['action'] == "add") {
                DB::insert(
                    prefix_table("api"),
                    array(
                        'id'        => null,
                        'type'      => 'key',
                        'label'     => $_POST['label'],
                        'value'       => $_POST['key'],
                        'timestamp' => time()
                    )
                );
            }
            else
            // update existing key
            if (isset($_POST['action']) && $_POST['action'] == "update") {
                DB::update(
                    prefix_table("api"),
                    array(
                        'label'     => $_POST['label'],
                        'timestamp' => time()
                    ),
                    "id=%i",
                    $_POST['id']
                );
            }
            else
            // delete existing key
            if (isset($_POST['action']) && $_POST['action'] == "delete") {
                DB::query("DELETE FROM ".prefix_table("api")." WHERE id = %i", $_POST['id']);
            }
            echo '[{"error":"'.$error.'"}]';
            break;

    /*
       * API save key
    */
    case "admin_action_api_save_ip":
        $error = "";
        // add new key
        if (isset($_POST['action']) && $_POST['action'] == "add") {
            DB::insert(
                prefix_table("api"),
                array(
                    'id'        => null,
                    'type'      => 'ip',
                    'label'     => $_POST['label'],
                    'value'       => $_POST['key'],
                    'timestamp' => time()
                )
            );
        }
        else
            // update existing key
            if (isset($_POST['action']) && $_POST['action'] == "update") {
                DB::update(
                    prefix_table("api"),
                    array(
                        'label'     => $_POST['label'],
                        'value'     => $_POST['key'],
                        'timestamp' => time()
                    ),
                    "id=%i",
                    $_POST['id']
                );
            }
        else
            // delete existing key
            if (isset($_POST['action']) && $_POST['action'] == "delete") {
                DB::query("DELETE FROM ".prefix_table("api")." WHERE id=%i", $_POST['id']);
            }
        echo '[{"error":"'.$error.'"}]';
        break;

    case "save_api_status":
        DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "api");
        $counter = DB::count();
        if ($counter == 0) {
            DB::insert(
                prefix_table("misc"),
                array(
                    'type' => "admin",
                    "intitule" => "api",
                    'valeur' => intval($_POST['status'])
                   )
            );
        } else {
            DB::update(
                prefix_table("misc"),
                array(
                    'valeur' => intval($_POST['status'])
                   ),
                "type = %s AND intitule = %s",
                "admin",
                "api"
            );
        }
        $_SESSION['settings']['api'] = intval($_POST['status']);
        break;

    case "save_duo_status":
        DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "duo");
        $counter = DB::count();
        if ($counter == 0) {
            DB::insert(
                prefix_table("misc"),
                array(
                    'type' => "admin",
                    "intitule" => "duo",
                    'valeur' => intval($_POST['status'])
                   )
            );
        } else {
            DB::update(
                prefix_table("misc"),
                array(
                    'valeur' => intval($_POST['status'])
                   ),
                "type = %s AND intitule = %s",
                "admin",
                "duo"
            );
        }
        $_SESSION['settings']['duo'] = intval($_POST['status']);
        break;

    case "save_duo_in_sk_file":
        // Check KEY and rights
        if ($_POST['key'] != $_SESSION['key']) {
            echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
            break;
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData($_POST['data'], "decode");

        // Prepare variables
        $akey = htmlspecialchars_decode($dataReceived['akey']);
        $ikey = htmlspecialchars_decode($dataReceived['ikey']);
        $skey = htmlspecialchars_decode($dataReceived['skey']);
        $host = htmlspecialchars_decode($dataReceived['host']);

        //get infos from SETTINGS.PHP file
        $filename = $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
        if (file_exists($filename)) {
            // get sk.php file path
            $settingsFile = file($filename);
            while (list($key,$val) = each($settingsFile)) {
                if (substr_count($val, 'require_once "')>0 && substr_count($val, 'sk.php')>0) {
                    $tmp_skfile = substr($val, 14, strpos($val, '";')-14);
                }
            }

            // before perform a copy of sk.php file
            if (file_exists($tmp_skfile)) {
                //Do a copy of the existing file
                if (!copy(
                    $tmp_skfile,
                    $tmp_skfile.'.'.date(
                        "Y_m_d",
                        mktime(0, 0, 0, date('m'), date('d'), date('y'))
                    )
                )) {
                    echo '[{"result" : "" , "error" : "Could NOT perform a copy of file: '.$tmp_skfile.'"}]';
                    break;
                } else {
                    unlink($tmp_skfile);
                }
            } else {
                // send back an error
                echo '[{"result" : "" , "error" : "Could NOT access file: '.$tmp_skfile.'"}]';
                break;
            }
        }

        // Write back values in sk.php file
        $fh = fopen($tmp_skfile, 'w');
        $result2 = fwrite(
            $fh,
            utf8_encode(
"<?php
@define('SALT', '".SALT."'); //Never Change it once it has been used !!!!!
@define('COST', '13'); // Don't change this.
// DUOSecurity credentials
@define('AKEY', \"".$akey."\");
@define('IKEY', \"".$ikey."\");
@define('SKEY', \"".$skey."\");
@define('HOST', \"".$host."\");
?>"
            )
        );
        fclose($fh);



        // send data
        echo '[{"result" : "'.addslashes($LANG['admin_duo_stored']).'" , "error" : ""}]';
        break;

    case "save_google_options":
        // Check KEY and rights
        if ($_POST['key'] != $_SESSION['key']) {
            echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
            break;
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData($_POST['data'], "decode");

        // Google Authentication
        if (htmlspecialchars_decode($dataReceived['google_authentication']) == "false") $tmp = 0;
        else $tmp = 1;
        DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "google_authentication");
        $counter = DB::count();
        if ($counter == 0) {
            DB::insert(
                prefix_table("misc"),
                array(
                    'type' => "admin",
                    "intitule" => "google_authentication",
                    'valeur' => $tmp
                )
            );
        } else {
            DB::update(
                prefix_table("misc"),
                array(
                    'valeur' => $tmp
                ),
                "type = %s AND intitule = %s",
                "admin",
                "google_authentication"
            );
        }
        $_SESSION['settings']['google_authentication'] = htmlspecialchars_decode($dataReceived['google_authentication']);

        // ga_website_name
        if (!is_null($dataReceived['ga_website_name'])) {
            DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "ga_website_name");
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'type' => "admin",
                        "intitule" => "ga_website_name",
                        'valeur' => htmlspecialchars_decode($dataReceived['ga_website_name'])
                    )
                );
            } else {
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['ga_website_name'])
                    ),
                    "type = %s AND intitule = %s",
                    "admin",
                    "ga_website_name"
                );
            }
            $_SESSION['settings']['ga_website_name'] = htmlspecialchars_decode($dataReceived['ga_website_name']);
        } else {
            $_SESSION['settings']['ga_website_name'] = "";
        }

        // send data
        echo '[{"result" : "'.addslashes($LANG['done']).'" , "error" : ""}]';
        break;

    case "save_agses_options":
        // Check KEY and rights
        if ($_POST['key'] != $_SESSION['key']) {
            echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
            break;
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData($_POST['data'], "decode");

        // agses_hosted_url
        if (!is_null($dataReceived['agses_hosted_url'])) {
            DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "agses_hosted_url");
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'type' => "admin",
                        "intitule" => "agses_hosted_url",
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_url'])
                    )
                );
            } else {
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_url'])
                    ),
                    "type = %s AND intitule = %s",
                    "admin",
                    "agses_hosted_url"
                );
            }
            $_SESSION['settings']['agses_hosted_url'] = htmlspecialchars_decode($dataReceived['agses_hosted_url']);
        } else {
            $_SESSION['settings']['agses_hosted_url'] = "";
        }

        // agses_hosted_id
        if (!is_null($dataReceived['agses_hosted_id'])) {
            DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "agses_hosted_id");
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'type' => "admin",
                        "intitule" => "agses_hosted_id",
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_id'])
                    )
                );
            } else {
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_id'])
                    ),
                    "type = %s AND intitule = %s",
                    "admin",
                    "agses_hosted_id"
                );
            }
            $_SESSION['settings']['agses_hosted_id'] = htmlspecialchars_decode($dataReceived['agses_hosted_id']);
        } else {
            $_SESSION['settings']['agses_hosted_id'] = "";
        }

        // agses_hosted_apikey
        if (!is_null($dataReceived['agses_hosted_apikey'])) {
            DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "agses_hosted_apikey");
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'type' => "admin",
                        "intitule" => "agses_hosted_apikey",
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_apikey'])
                    )
                );
            } else {
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => htmlspecialchars_decode($dataReceived['agses_hosted_apikey'])
                    ),
                    "type = %s AND intitule = %s",
                    "admin",
                    "agses_hosted_apikey"
                );
            }
            $_SESSION['settings']['agses_hosted_apikey'] = htmlspecialchars_decode($dataReceived['agses_hosted_apikey']);
        } else {
            $_SESSION['settings']['agses_hosted_apikey'] = "";
        }

        // send data
        echo '[{"result" : "'.addslashes($LANG['done']).'" , "error" : ""}]';
        break;

    case "save_option_change":
        // Check KEY and rights
        if ($_POST['key'] != $_SESSION['key']) {
            echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
            break;
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData($_POST['data'], "decode");
        $type = "admin";

        // Check if setting is already in DB. If NO then insert, if YES then update.
        $data = DB::query(
            "SELECT * FROM ".prefix_table("misc")."
            WHERE type = %s AND intitule = %s",
            $type,
            $dataReceived['field']
        );
        $counter = DB::count();
        if ($counter == 0) {
            DB::insert(
                prefix_table("misc"),
                array(
                    'valeur' => $dataReceived['value'],
                    'type' => $type,
                    'intitule' => $dataReceived['field']
                   )
            );
            // in case of stats enabled, add the actual time
            if ($dataReceived['field'] == 'send_stats') {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'valeur' => time(),
                        'type' => $type,
                        'intitule' => $dataReceived['field'].'_time'
                       )
                );
            }
        } else {
            DB::update(
                prefix_table("misc"),
                array(
                    'valeur' => $dataReceived['value']
                   ),
                "type = %s AND intitule = %s",
                $type,
                $dataReceived['field']
            );
            // in case of stats enabled, update the actual time
            if ($dataReceived['field'] == 'send_stats') {
                // Check if previous time exists, if not them insert this value in DB
                $data_time = DB::query(
                    "SELECT * FROM ".prefix_table("misc")."
                    WHERE type = %s AND intitule = %s",
                    $type,
                    $dataReceived['field'].'_time'
                );
                $counter = DB::count();
                if ($counter == 0) {
                    DB::insert(
                        prefix_table("misc"),
                        array(
                            'valeur' => 0,
                            'type' => $type,
                            'intitule' => $dataReceived['field'].'_time'
                           )
                    );
                } else {
                    DB::update(
                        prefix_table("misc"),
                        array(
                            'valeur' => 0
                           ),
                        "type = %s AND intitule = %s",
                        $type,
                        $dataReceived['field']
                    );
                }
            }
        }

        // special Cases
        if ($dataReceived['field'] == "cpassman_url") {
            // update also jsUrl for CSFP protection
            $jsUrl = $dataReceived['value'].'/includes/libraries/csrfp/js/csrfprotector.js';
            $csrfp_file = "../includes/libraries/csrfp/libs/csrfp.config.php";
            $data = file_get_contents($csrfp_file);
            $posJsUrl = strpos($data, '"jsUrl" => "');
            $posEndLine = strpos($data, '",', $posJsUrl);
            $line = substr($data, $posJsUrl, ($posEndLine - $posJsUrl + 2));
            $newdata = str_replace($line, '"jsUrl" => "'.$jsUrl.'",', $data);
            file_put_contents($csrfp_file, $newdata);
        } else
        if ($dataReceived['field'] == "restricted_to_input" && $dataReceived['value'] == "0") {
            DB::update(
                prefix_table("misc"),
                array(
                    'valeur' => 0
                   ),
                "type = %s AND intitule = %s",
                $type,
                'restricted_to_roles'
            );
        }/* else
        if ($dataReceived['field'] == "use_md5_password_as_salt" && $dataReceived['value'] == "0") {
            // in case this option is changed, we need to warn the users to adapt
            $rows = DB::query(
                "SELECT id FROM ".prefix_table("users")."
                WHERE admin != %i",
                "",
                "1"
            );
            foreach ($rows as $record) {
                DB::update(
                    prefix_table("users"),
                    array(
                        'upgrade_needed' => "1"
                    ),
                    "id = %i"
                );
            }
        }*/

        // store in SESSION
        $_SESSION['settings'][$dataReceived['field']] = $dataReceived['value'];

        // save change in config file
        handleConfigFile ("update", $dataReceived['field'], $dataReceived['value']);

        // Encrypt data to return
        echo prepareExchangedData(
            array(
                "error" => "",
                "misc" => $counter." ; ".$_SESSION['settings'][$dataReceived['field']]
            ),
            "encode"
        );
        break;

    case "get_values_for_statistics":
        // Check KEY and rights
        if ($_POST['key'] != $_SESSION['key']) {
            echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
            break;
        }

        // Encrypt data to return
        echo prepareExchangedData(
            getStatisticsData(),
            "encode"
        );

        break;

    case "save_sending_statistics":
        // Check KEY and rights
        if ($_POST['key'] != $_SESSION['key']) {
            echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
            break;
        }

        // send statistics
        if (!is_null($_POST['status'])) {
            DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "send_stats");
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'type' => "admin",
                        "intitule" => "send_stats",
                        'valeur' => $_POST['status']
                    )
                );
            } else {
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => $_POST['status']
                    ),
                    "type = %s AND intitule = %s",
                    "admin",
                    "send_stats"
                );
            }
            $_SESSION['settings']['send_stats'] = $_POST['status'];
        } else {
            $_SESSION['settings']['send_stats'] = "0";
        }

        // send statistics items
        if (isset($_POST['list'])) {
            DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s", "admin", "send_statistics_items");
            $counter = DB::count();
            if ($counter == 0) {
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'type' => "admin",
                        "intitule" => "send_statistics_items",
                        'valeur' => $_POST['list']
                    )
                );
            } else {
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => $_POST['list']
                    ),
                    "type = %s AND intitule = %s",
                    "admin",
                    "send_statistics_items"
                );
            }
            $_SESSION['settings']['send_statistics_items'] = $_POST['list'];
        } else {
            $_SESSION['settings']['send_statistics_items'] = "";
        }

        // send data
        echo '[{"result" : "'.addslashes($LANG['done']).'" , "error" : ""}]';
        break;
}