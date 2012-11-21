<?php
/**
 * @file 		admin.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');


include('../includes/language/'.$_SESSION['user_language'].'.php');
include '../includes/settings.php' ;
include '../includes/include.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

// connect to the server
require_once("class.database.php");
$db = new Database($server, $user, $pass, $database, $pre);
$db->connect();

switch($_POST['type'])
{
    #CASE for getting informations about the tool
    # connection to author's cpassman website
    case "cpm_status":
        $text = "<ul>";
    	$error ="";
        // Chemin vers le fichier distant
        $remote_file = 'cpm2_config.txt';
        $local_file = $_SESSION['settings']['path_to_files_folder'].'/localfile.txt';

        // Ouverture du fichier pour ?criture
        $handle = fopen($local_file, 'w');

        // Mise en place d'une connexion basique
        $conn_id = ftp_connect("www.teampass.net") or die("Impossible to get connected to the server $ftp_server");

        // Identification avec un nom d'utilisateur et un mot de passe
        $login_result = ftp_login($conn_id, "robot@teampass.net", "Cm3_Pc9l");

        //envoyer la date et ip de connexion
        //....

        // Tente de t?l?chargement le fichier $remote_file et de le sauvegarder dans $handle
        if (ftp_fget($conn_id, $handle, $remote_file, FTP_ASCII, 0)) {
            //READ FILE
            if (file_exists($local_file)) {
                $tableau = file($local_file);
                while(list($cle,$val) = each($tableau)) {
                    if (substr($val,0,1) <> "#") {
                        $tab = explode('|',$val);
                        foreach ($tab as $elem) {
                            $tmp = explode('#',$elem);
                            $text .= '<li><u>'.$txt[$tmp[0]]."</u> : ".$tmp[1].'</li>';
                            if ($tmp[0] == "version") {
                                $text .= '<li><u>'.$txt['your_version']."</u> : ".$k['version'];
                                if (floatval($k['version']) < floatval($tmp[1])) $text .= '&nbsp;&nbsp;<b>'.$txt['please_update'].'</b><br />';
                                $text .= '</li>';
                            }
                        }
                    }
                }
            }
        } else {
        	$error = "connection";
        }

        // Fermeture de la connexion et du pointeur de fichier
        ftp_close($conn_id);
        fclose($handle);

        //DELETE FILE
        unlink($local_file);

    	echo '[{"error":"'.$error.'" , "output":"'.$text.'"}]';
    break;

    ###########################################################
    #CASE for refreshing all Personal Folders
    case "admin_action_check_pf":
        //get through all users
        $rows = $db->fetch_all_array("SELECT id,login,email FROM ".$pre."users ORDER BY login ASC");
        foreach ($rows as $record) {
            //update PF field for user
            $db->query_update(
                'users',
                array(
                    'personal_folder' => '1'
                ),
                "id='".$record['id']."'"
            );

            //if folder doesn't exist then create it
            $data = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."nested_tree WHERE title = '".$record['id']."' AND parent_id = 0");
            if ($data[0] == 0) {
                //If not exist then add it
                $db->query_insert(
                    "nested_tree",
                    array(
                        'parent_id' => '0',
                        'title' => $record['id'],
                        'personal_folder' => '1'
                    )
                );
            } else {
                //If exists then update it
                $db->query_update(
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
			"SELECT COUNT(*) FROM ".$pre."nested_tree AS t
    		LEFT JOIN ".$pre."users AS u ON t.title = u.id
    		WHERE u.id IS NULL AND t.parent_id=0 AND t.title REGEXP '^[0-9]'"
			);

    	//rebuild fuild tree folder
    	require_once('NestedTree.class.php');
    	$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
    	$tree->rebuild();

    	echo '[{"result" : "pf_done"}]';
    break;

    ###########################################################
    #CASE for deleting all items from DB that are linked to a folder that has been deleted
    case "admin_action_db_clean_items":
        //Libraries call
        require_once "NestedTree.class.php";

        //init
        $folders_ids = array();
        $text = "";
        $nb_items_deleted = 0;

        // prepare full tree
        $tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

        // Get an array of all folders
        $folders = $tree->getDescendants();
        foreach ($folders as $folder) {
            if (!in_array($folder->id,$folders_ids)) array_push($folders_ids,$folder->id);
        }

        $items = $db->fetch_all_array("SELECT id,label FROM ".$pre."items WHERE id_tree NOT IN(".implode(',',$folders_ids).")");
        foreach ($items as $item ) {
            $text .= $item['label']."[".$item['id']."] - ";
            //Delete item
            $db->query("DELETE FROM ".$pre."items WHERE id = ".$item['id']);
            //log
            $db->query("DELETE FROM ".$pre."log_items WHERE id_item = ".$item['id']);

            $nb_items_deleted++;
        }

        //Update CACHE table
        UpdateCacheTable("reload");

        //show some info
    	echo '[{"result" : "db_clean_items","nb_items_deleted":"'.$nb_items_deleted.'"}]';
    break;

    ###########################################################
    #CASE for creating a DB backup
    case "admin_action_db_backup":
        require_once('main.functions.php');
        $return = "";

        //Get all tables
        $tables = array();
        $result = mysql_query('SHOW TABLES');
        while($row = mysql_fetch_row($result))
        {
            $tables[] = $row[0];
        }

        //cycle through
        foreach ($tables as $table)
        {
            if (empty($pre) || substr_count($table,$pre) > 0) {
                $result = mysql_query('SELECT * FROM '.$table);
                $num_fields = mysql_num_fields($result);
                // prepare a drop table
                $return.= 'DROP TABLE '.$table.';';
                $row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
                $return.= "\n\n".$row2[1].";\n\n";

                //prepare all fields and datas
                for ($i = 0; $i < $num_fields; $i++)
                {
                    while($row = mysql_fetch_row($result))
                    {
                        $return.= 'INSERT INTO '.$table.' VALUES(';
                        for($j=0; $j<$num_fields; $j++)
                        {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = preg_replace("/\n/","\\n",$row[$j]);
                            if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
                            if ($j<($num_fields-1)) { $return.= ','; }
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
            $handle = fopen($_SESSION['settings']['path_to_files_folder']."/".$filename,'w+');

            //Encrypt the file
            if (!empty($_POST['option'])) $return = encrypt($return,$_POST['option']);

            //write file
            fwrite($handle,$return);
            fclose($handle);

        	//generate 2d key
        	include('../includes/libraries/pwgen/pwgen.class.php');
        	$pwgen = new PWGen();
        	$pwgen->setLength(20);
        	$pwgen->setSecure(true);
        	$pwgen->setSymbols(false);
        	$pwgen->setCapitalize(true);
        	$pwgen->setNumerals(true);
        	$_SESSION['key_tmp'] = $pwgen->generate();

        	//update LOG
        	$db->query_insert(
	        	'log_system',
	        	array(
	        	    'type' => 'admin_action',
	        	    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	        	    'label' => 'Database backup',
	        	    'qui' => $_SESSION['user_id']
	        	)
        	);

        	echo '[{"result":"db_backup" , "href":"sources/downloadFile.php?name='.urlencode($filename).'&sub=files&file='.$filename.'&type=sql&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'"}]';
        }
    break;

    ###########################################################
    #CASE for restoring a DB backup
    case "admin_action_db_restore":
        require_once('main.functions.php');

    	$data_post = explode('&', $_POST['option']);
    	$file = $data_post[0];
    	$key = $data_post[1];

        //create uncrypted file
        if (!empty($key)) {
            //read full file
            $file_array = file($_SESSION['settings']['path_to_files_folder']."/".$file);

            //delete file
            unlink($_SESSION['settings']['path_to_files_folder']."/".$file);

            //create new file with uncrypted data
            $file = $_SESSION['settings']['path_to_files_folder']."/".time().".log";
            $inF = fopen($file,"w");
            while(list($cle,$val) = each($file_array)) {
                fputs($inF,decrypt($val,$key)."\n");
            }
            fclose($inF);
        }

        //read sql file
        if ($handle = fopen($_SESSION['settings']['path_to_files_folder']."/".$file,"r")) {
            $query = "";
            while (!feof($handle)) {
                $query.= fgets($handle, 4096);
                if (substr(rtrim($query), -1) == ';' ) {
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
        while ($table = mysql_fetch_assoc($alltables))
        {
           foreach ($table as $i => $tablename)
           {
               if (substr_count($tablename,$pre) > 0) {
                   // launch optimization quieries
                   mysql_query("ANALYZE TABLE `".$tablename."`");
                   mysql_query("OPTIMIZE TABLE `".$tablename."`");
               }
           }
        }

    	//Clean up LOG_ITEMS table
    	$rows = $db->fetch_all_array("
			SELECT id
			FROM ".$pre."items
			ORDER BY id ASC
    	");
    	foreach ($rows as $item ) {
    		$row = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item=".$item['id']." AND action = 'at_creation'");
    		if($row[0] == 0) {
    			//Create new at_creation entry
    			$row_tmp = $db->query_first("SELECT date FROM ".$pre."log_items WHERE id_item=".$item['id']." ORDER BY date ASC");
    			$db->query_insert(
	    			'log_items',
	    			array(
	    			    'id_item' 	=> $item['id'],
	    			    'date' 		=> $row_tmp['date']-1,
		    			'id_user' 	=> "",
		    			'action' 	=> "at_creation",
		    			'raison'	=> ""
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
        $nb_files_deleted = 0;

        //read folder
        $dir = opendir($_SESSION['settings']['path_to_files_folder']);

        //delete file
        while ($f = readdir($dir)) {
            if( is_file($rep.$f) && (time()-filectime($rep.$f)) > 604800 ) {
                unlink($rep.$f);
                $nb_files_deleted++;
            }
        }
        //Close dir
        closedir($dir);

        //Show done
    	echo '[{"result":"purge_old_files","nb_files_deleted":"'.$nb_files_deleted.'"}]';
    break;

	/*
	* Reload the Cache table
	*/
	case "admin_action_reload_cache_table":
		require_once("main.functions.php");
		UpdateCacheTable("reload", "");
		echo '[{"result":""}]';
	break;

	/*
	* Decrypt a backup file
	*/
	case "admin_action_backup_decrypt":
		require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
		require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation

		//get backups infos
		$rows = $db->fetch_all_array("SELECT * FROM ".$pre."misc WHERE type = 'settings'");
		foreach ($rows as $reccord) {
			$settings[$reccord['intitule']] = $reccord['valeur'];
		}

		//read file
		$return = "";
		$Fnm = $settings['bck_script_path'].'/'.$_POST['option'].'.sql';
		if (file_exists($Fnm)) {
			$inF = fopen($Fnm,"r");
			while (!feof($inF)) {
				$return .= fgets($inF, 4096);
			}
			fclose($inF);
			$return = AesCtr::decrypt($return, $settings['bck_script_key'], 256);

			//save the file
			$handle = fopen($settings['bck_script_path'].'/'.$_POST['option'].'_DECRYPTED'.'.sql','w+');
			fwrite($handle,$return);
			fclose($handle);
		}
	break;

	/*
	* Change SALT Key
	*/
	case "admin_action_change_salt_key":
		include 'main.functions.php' ;
		//put tool in maintenance.
            $db->query_update(
                "misc",
                array(
                    'valeur' => '1',
                ),
                "intitule = 'maintenance_mode' AND type= 'admin'"
            );
            //log
            $db->query_insert(
                "log_system",
                array(
                    'type' => 'system',
                    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
                    'label' => 'change_salt_key',
                    'qui' => $_SESSION['user_id']
                )
            );

		require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
		require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
		$new_salt_key = htmlspecialchars_decode(AesCtr::decrypt($_POST['option'], SALT, 256));

		//change all passwords in DB
		$rows = $db->fetch_all_array("SELECT id,pw FROM ".$pre."items WHERE perso = '0'");
		foreach ($rows as $reccord) {
			$pw = decrypt($reccord['pw']);
			//encrypt with new SALT
			$db->query_update(
                "items",
                array(
                    'pw' => encrypt($pw, $new_salt_key),
                ),
                "id = '".$reccord['id']."'"
            );
		}
		//change all users password in DB
		$rows = $db->fetch_all_array("SELECT id,pw FROM ".$pre."users");
		foreach ($rows as $reccord) {
			$pw = decrypt($reccord['pw']);
			//encrypt with new SALT
			$db->query_update(
                "users",
                array(
                    'pw' => encrypt($pw, $new_salt_key),
                ),
                "id = '".$reccord['id']."'"
            );
		}

		//change salt key in settings.php file
		$filename = "../includes/settings.php";
		$error = "";
		if (file_exists($filename)) {
			//Do a copy of the existing file
			if (!copy($filename, $filename.'.'.date("Y_m_d",mktime(0,0,0,date('m'),date('d'),date('y'))))) {
				$error = "Setting.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.";
				break;
			} else {
				unlink($filename);
			}

			$fh = fopen($filename, 'w');

			fwrite($fh, utf8_encode("<?php
global \$lang, \$txt, \$k, \$chemin_passman, \$url_passman, \$pw_complexity, \$mngPages;
global \$server, \$user, \$pass, \$database, \$pre, \$db;

@define('SALT', '". $new_salt_key ."'); //Define your encryption key => NeverChange it once it has been used !!!!!

### DATABASE connexion parameters ###
\$server = \"". $server ."\";
\$user = \"". $user ."\";
\$pass = \"". str_replace("$", "\\$", $pass) ."\";
\$database = \"". $database ."\";
\$pre = \"". $pre ."\";

@date_default_timezone_set(\$_SESSION['settings']['timezone']);

?>"));

			fclose($fh);
		}

		echo '[{"result":"changed_salt_key", "error":"'.$error.'"}]';
	break;

	/*
	* Test the email configuraiton
	*/
	case "admin_email_test_configuration":
        require_once ("main.functions.php");
		echo '[{"result":"email_test_conf", '.@SendEmail($txt['admin_email_test_subject'], $txt['admin_email_test_body'], $_SESSION['settings']['email_from']).'}]';
	break;

	/*
	* Send emails in backlog
	*/
	case "admin_email_send_backlog":
        require_once ("main.functions.php");

        $rows = $db->fetch_all_array("SELECT * FROM ".$pre."emails WHERE status = 'not_sent' OR status = ''");
        foreach ($rows as $reccord) {
        	//send email
			$ret = json_decode(@SendEmail(
            	$reccord['subject'],
            	$reccord['body'],
            	$reccord['receivers']
            ));

			if(!empty($ret['error'])) {
				//update item_id in files table
				$db->query_update(
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
        $db->query_insert(
	       	'log_system',
	       	array(
	       	    'type' => 'admin_action',
	       	    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
	       	    'label' => 'Emails backlog',
	       	    'qui' => $_SESSION['user_id']
	       	)
        );

		echo '[{"result":"admin_email_send_backlog", '.@SendEmail($txt['admin_email_test_subject'], $txt['admin_email_test_body'], $_SESSION['settings']['email_from']).'}]';
	break;
}
?>