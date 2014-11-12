<?php
/**
 * @file 		install.queries.php
 * @author		Nils Laumaillé
 * @version 	2.1.22
 * @copyright 	(c) 2009-2014 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once('../sources/sessions.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
header("Content-type: text/html; charset=utf-8");
$_SESSION['db_encoding'] = "utf8";

$_SESSION['CPM'] = 1;

function chmod_r($dir, $dirPermissions, $filePermissions) {
	$dp = opendir($dir);
	$res = true;
	while($file = readdir($dp)) {
		if (($file == ".") || ($file == ".."))
			continue;

		$fullPath = $dir."/".$file;

		if(is_dir($fullPath)) {
			if ($res = @chmod($fullPath, $dirPermissions))
				$res = @chmod_r($fullPath, $dirPermissions, $filePermissions);
			} else {
				$res = chmod($fullPath, $filePermissions);
			}
			if (!$res) {
				closedir($dp);
				return false;
			}
	}
	closedir($dp);
	if (is_dir($dir) && $res)
		$res = @chmod($dir, $dirPermissions);

	return $res;
}

if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        case "step_2":
            //decrypt
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';  // AES Counter Mode implementation
            $json = Encryption\Crypt\aesctr::decrypt($_POST['data'], "cpm", 128);
            $data = json_decode($json, true);
            $json = Encryption\Crypt\aesctr::decrypt($_POST['activity'], "cpm", 128);
            $data = array_merge($data, array("activity" => $json));
            $json = Encryption\Crypt\aesctr::decrypt($_POST['task'], "cpm", 128);
            $data = array_merge($data, array("task" => $json));

            $abspath = str_replace('\\', '/', $data['root_path']);
            if (substr($abspath, strlen($abspath)-1) == "/") {
                $abspath = substr($abspath, 0, strlen($abspath)-1);
            }
            $_SESSION['abspath'] = $abspath;
            $_SESSION['url_path'] = $data['url_path'];
            
            if (isset($data['activity']) && $data['activity'] == "folder") {
                if (is_writable($abspath."/".$data['task']."/") == true) {
                    echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                } else {
                    echo '[{"error" : " Path '.$data['task'].' is not writable!", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                }
                break;
            }
            
            if (isset($data['activity']) && $data['activity'] == "extension") {
                if (extension_loaded($data['task'])) {
                    echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                } else {
                    echo '[{"error" : " Extension '.$data['task'].' is not loaded!", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                }
                break;
            }
 
            if (isset($data['activity']) && $data['activity'] == "function") {
                if (function_exists($data['task'])) {
                    echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                } else {
                    echo '[{"error" : " Function '.$data['task'].' is not available!", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                }
                break;
            }
           
            if (isset($data['activity']) && $data['activity'] == "version") {
                if (version_compare(phpversion(), '5.3.0', '>=')) {
                    echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                } else {
                    echo '[{"error" : "PHP version '.phpversion().' is not OK (minimum is 5.3.0)", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                }
                break;
            }
            
            if (isset($data['activity']) && $data['activity'] == "ini") {
                if (ini_get($data['task'])>=60) {
                    echo '[{"error" : "", "index" : "'.$_POST['index'].'"}]';
                } else {
                    echo '[{"error" : "PHP \"Maximum execution time\" is set to '.ini_get('max_execution_time').' seconds. Please try to set to 60s at least during installation.", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                }
                break;
            }
        break;

        case "step_3":
            //decrypt
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';  // AES Counter Mode implementation
            $json = Encryption\Crypt\aesctr::decrypt($_POST['data'], "cpm", 128);
            $data = json_decode($json, true);
            // launch
            if ($dbTmp = mysqli_connect($data['db_host'], $data['db_login'], $data['db_pw'], $data['db_bdd'], $data['db_port'])) {
                // create temporary INSTALL mysqli table
                $mysqli_result = mysqli_query($dbTmp,
                    "CREATE TABLE IF NOT EXISTS `_install` (
                    `key` varchar(100) NOT NULL,
                    `value` varchar(500) NOT NULL
                    ) CHARSET=utf8;"
                );
                // store values
                foreach($data as $key=>$value) {
                    $_SESSION[$key] = $value;
                    $tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `_install` WHERE `key` = '".$key."'"));
                    if ($tmp[0] == 0 || empty($tmp[0])) {
                        mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('".$key."', '".$value."');");
                    } else {
                        mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '".$value."' WHERE `key` = '".$key."';");
                    }
                }
                $tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `_install` WHERE `key` = 'url_path'"));
                if ($tmp[0] == 0 || empty($tmp[0])) {
                    mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('url_path', '".$_SESSION['url_path']."');");
                } else {
                    mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '".$_SESSION['url_path']."' WHERE `key` = 'url_path';");
                }
                $tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `_install` WHERE `key` = 'abspath'"));
                if ($tmp[0] == 0 || empty($tmp[0])) {
                    mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('abspath', '".$_SESSION['abspath']."');");
                } else {
                    mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '".$_SESSION['abspath']."' WHERE `key` = 'abspath';");
                }
                
                echo '[{"error" : "", "result" : "Connection is successful", "multiple" : ""}]';
            } else {
                echo '[{"error" : "'.addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_connect_error())).'", "result" : "Failed", "multiple" : ""}]';
            }
            mysqli_close($dbTmp);
        break;

        case "step_4":
            //decrypt
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';  // AES Counter Mode implementation
            $json = Encryption\Crypt\aesctr::decrypt($_POST['data'], "cpm", 128);
            $data = json_decode($json, true);
            
            $dbTmp = mysqli_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw'], $_SESSION['db_bdd'], $_SESSION['db_port']);

            // prepare data
            foreach($data as $key=>$value) {
                $data[$key] = str_replace(array('&quot;', '&#92;'), array('""','\\\\'), $value);
            }

            // check skpath
            if (empty($data['sk_path'])) {
                $data['sk_path'] = $_SESSION['abspath']."/includes";
            } else {
                $data['sk_path'] = str_replace("&#92;", "/", $data['sk_path']);
            }
            if (substr($data['sk_path'], strlen($data['sk_path'])-1) == "/" || substr($data['sk_path'], strlen($data['sk_path'])-1) == "\"") {
                $data['sk_path'] = substr($data['sk_path'], 0, strlen($data['sk_path'])-1);
            }
            if (is_dir($data['sk_path'])) {
                if (is_writable($data['sk_path'])) {
                    // store all variables in SESSION
                    foreach($data as $key=>$value) {
                        $_SESSION[$key] = $value;
                        $tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `_install` WHERE `key` = '".$key."'"));
                        if ($tmp[0] == 0 || empty($tmp[0])) {
                            mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('".$key."', '".$value."');");
                        } else {
                            mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '".$value."' WHERE `key` = '".$key."';");
                        }
                    }
                    echo '[{"error" : "", "result" : "Information stored", "multiple" : ""}]';
                } else {
                    echo '[{"error" : "The Directory must be writable!", "result" : "Information stored", "multiple" : ""}]';
                }
            } else {
                echo '[{"error" : "'.$data['sk_path'].' is not a Directory!", "result" : "Information stored", "multiple" : ""}]';
            }
            mysqli_close($dbTmp);
            break;

        case "step_5":
            //decrypt
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';  // AES Counter Mode implementation
            $activity = Encryption\Crypt\aesctr::decrypt($_POST['activity'], "cpm", 128);
            $task = Encryption\Crypt\aesctr::decrypt($_POST['task'], "cpm", 128);

            // launch
            $dbTmp = mysqli_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw'], $_SESSION['db_bdd'], $_SESSION['db_port']);
            $dbBdd = $_SESSION['db_bdd'];
            if ($dbTmp) {
                $mysqli_result = "";

                // read install variables
                $result = mysqli_query($dbTmp, "SELECT * FROM `_install`");
                while ($row = $result->fetch_array()) {
                    $var[$row[0]] = $row[1];
                }

                if ($activity == "table") {
                    //FORCE UTF8 DATABASE
                    mysqli_query($dbTmp, "ALTER DATABASE `".$dbBdd."` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
                    if ($task == "items") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."items` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `label` varchar(100) NOT NULL,
                            `description` text NOT NULL,
                            `pw` text NOT NULL,
                            `url` varchar(250) DEFAULT NULL,
                            `id_tree` varchar(10) DEFAULT NULL,
                            `perso` tinyint(1) NOT null DEFAULT '0',
                            `login` varchar(200) DEFAULT NULL,
                            `inactif` tinyint(1) NOT null DEFAULT '0',
                            `restricted_to` varchar(200) NOT NULL,
                            `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
                            `email` varchar(100) DEFAULT NULL,
                            `notification` varchar(250) DEFAULT NULL,
                            `viewed_no` int(12) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "log_items") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."log_items` (
                            `id_item` int(8) NOT NULL,
                            `date` varchar(50) NOT NULL,
                            `id_user` int(8) NOT NULL,
                            `action` varchar(250) NOT NULL,
                            `raison` text NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "misc") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."misc` (
                            `type` varchar(50) NOT NULL,
                            `intitule` varchar(100) NOT NULL,
                            `valeur` varchar(100) NOT NULL
                            ) CHARSET=utf8;"
                        );
                        
                        // include constants 
                        require_once "../includes/include.php";
                         
                        // add by default settings
                        $aMiscVal = array(
                            array('admin', 'max_latest_items', '10'),
                            array('admin', 'enable_favourites', '1'),
                            array('admin', 'show_last_items', '1'),
                            array('admin', 'enable_pf_feature', '0'),
                            array('admin', 'log_connections', '0'),
                            array('admin', 'log_accessed', '1'),
                            array('admin', 'time_format', 'H:i:s'),
                            array('admin', 'date_format', 'd/m/Y'),
                            array('admin', 'duplicate_folder', '0'),
                            array('admin', 'item_duplicate_in_same_folder', '0'),
                            array('admin', 'duplicate_item', '0'),
                            array('admin', 'number_of_used_pw', '3'),
                            array('admin', 'manager_edit', '1'),
                            array('admin', 'cpassman_dir', $var['abspath']),
                            array('admin', 'cpassman_url', $var['url_path']),
                            array('admin', 'favicon', $var['url_path'].'/favicon.ico'),
                            array('admin', 'path_to_upload_folder', $var['abspath'].'/upload'),
                            array('admin', 'url_to_upload_folder', $var['url_path'].'/upload'),
                            array('admin', 'path_to_files_folder', $var['abspath'].'/files'),
                            array('admin', 'url_to_files_folder', $var['url_path'].'/files'),
                            array('admin', 'activate_expiration', '0'),
                            array('admin','pw_life_duration','0'),
                            array('admin','maintenance_mode','1'),
                            array('admin','enable_sts','0'),
                            array('admin','encryptClientServer','1'),
                            array('admin','cpassman_version',$k['version']),
                            array('admin','ldap_mode','0'),
                            array('admin','ldap_type','0'),
                            array('admin','ldap_suffix','0'),
                            array('admin','ldap_domain_dn','0'),
                            array('admin','ldap_domain_controler','0'),
                            array('admin','ldap_user_attribute','0'),
                            array('admin','ldap_ssl','0'),
                            array('admin','ldap_tls','0'),
                            array('admin','ldap_elusers','0'),
                            array('admin','richtext','0'),
                            array('admin','allow_print','0'),
                            array('admin','roles_allowed_to_print','0'),
                            array('admin','show_description','1'),
                            array('admin','anyone_can_modify','0'),
                            array('admin','anyone_can_modify_bydefault','0'),
                            array('admin','nb_bad_authentication','0'),
                            array('admin','utf8_enabled','1'),
                            array('admin','restricted_to','0'),
                            array('admin','restricted_to_roles','0'),
                            array('admin','enable_send_email_on_user_login','0'),
                            array('admin','enable_user_can_create_folders','0'),
                            array('admin','insert_manual_entry_item_history','0'),
                            array('admin','enable_kb','0'),
                            array('admin','enable_email_notification_on_item_shown','0'),
                            array('admin','enable_email_notification_on_user_pw_change','0'),
                            array('admin','custom_logo',''),
                            array('admin','custom_login_text',''),
                            array('admin','default_language','english'),
                            array('admin','send_stats', $var['send_stats']),
                            array('admin','get_tp_info', '1'),
                            array('admin','send_mail_on_user_login', '0'),
                            array('cron', 'sending_emails', '0'),
                            array('admin','nb_items_by_query', 'auto'),
                            array('admin','enable_delete_after_consultation', '0'),
                            array('admin','enable_personal_saltkey_cookie', '0'),
                            array('admin','personal_saltkey_cookie_duration', '31'),
                            array('admin','email_smtp_server', $var['smtp_server']),
                            array('admin','email_smtp_auth', $var['smtp_auth']),
                            array('admin','email_auth_username', $var['smtp_auth_username']),
                            array('admin','email_auth_pwd', $var['smtp_auth_password']),
                            array('admin','email_port', $var['smtp_port']),
                            array('admin','email_server_url', $var['url_path']),
                            array('admin','email_from', $var['email_from']),
                            array('admin','email_from_name', $var['email_from_name']),
                            array('admin','pwd_maximum_length', '40'),
                            array('admin','2factors_authentication', '0'),
                            array('admin','delay_item_edition', '0'),
                            array('admin','allow_import','0'),
                            array('admin','proxy_ip',''),
                            array('admin','proxy_port',''),
                            array('admin','upload_maxfilesize','10mb'),
                            array('admin','upload_docext','doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx'),
                            array('admin','upload_imagesext','jpg,jpeg,gif,png'),
                            array('admin','upload_pkgext','7z,rar,tar,zip'),
                            array('admin','upload_otherext','sql,xml'),
                            array('admin','upload_imageresize_options','1'),
                            array('admin','upload_imageresize_width','800'),
                            array('admin','upload_imageresize_height','600'),
                            array('admin','upload_imageresize_quality','90'),
                            array('admin','use_md5_password_as_salt','0'),
                            array('admin','ga_website_name','TeamPass for ChangeMe'),
                            array('admin','api','0'),
                            array('admin','subfolder_rights_as_parent','0'),
                            array('admin','show_only_accessible_folders','0'),
                            array('admin','enable_suggestion','0'),
                            array('admin','otv_expiration_period','7')
                        );
                        foreach ($aMiscVal as $elem) {
                            //Check if exists before inserting
                            $mysqli_result = mysqli_query($dbTmp,
                                "SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."misc
                                WHERE type='".$elem[0]."' AND intitule='".$elem[1]."'"
                            );
                            if ($mysqli_result) {
	                            $resTmp = mysqli_fetch_row($queryRes);
    	                        if ($resTmp[0] == 0) {
        	                        $queryRes = mysqli_query($dbTmp,
            	                        "INSERT INTO `".$_SESSION['tbl_prefix']."misc`
                	                    (`type`, `intitule`, `valeur`) VALUES
                    	                ('".$elem[0]."', '".$elem[1]."', '".
                        	            str_replace("'", "", $elem[2])."');"
                            	    );
                            	}
                            }
                        }
                        
                    } else if ($task == "nested_tree") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."nested_tree` (
                            `id` bigint(20) unsigned NOT null AUTO_INCREMENT,
                            `parent_id` int(11) NOT NULL,
                            `title` varchar(255) NOT NULL,
                            `nleft` int(11) NOT NULL DEFAULT '0',
                            `nright` int(11) NOT NULL DEFAULT '0',
                            `nlevel` int(11) NOT NULL DEFAULT '0',
                            `bloquer_creation` tinyint(1) NOT null DEFAULT '0',
                            `bloquer_modification` tinyint(1) NOT null DEFAULT '0',
                            `personal_folder` tinyint(1) NOT null DEFAULT '0',
                            `renewal_period` TINYINT(4) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `id` (`id`),
                            KEY `nested_tree_parent_id` (`parent_id`),
                            KEY `nested_tree_nleft` (`nleft`),
                            KEY `nested_tree_nright` (`nright`),
                            KEY `nested_tree_nlevel` (`nlevel`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "rights") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."rights` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `tree_id` int(12) NOT NULL,
                            `fonction_id` int(12) NOT NULL,
                            `authorized` tinyint(1) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "users") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."users` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `login` varchar(50) NOT NULL,
                            `pw` varchar(400) NOT NULL,
                            `groupes_visibles` varchar(250) NOT NULL,
                            `derniers` text NOT NULL,
                            `key_tempo` varchar(100) NOT NULL,
                            `last_pw_change` varchar(30) NOT NULL,
                            `last_pw` text NOT NULL,
                            `admin` tinyint(1) NOT null DEFAULT '0',
                            `fonction_id` varchar(255) NOT NULL,
                            `groupes_interdits` varchar(255) NOT NULL,
                            `last_connexion` varchar(30) NOT NULL,
                            `gestionnaire` int(11) NOT null DEFAULT '0',
                            `email` varchar(300) NOT NULL,
                            `favourites` varchar(300) NOT NULL,
                            `latest_items` varchar(300) NOT NULL,
                            `personal_folder` int(1) NOT null DEFAULT '0',
                            `disabled` tinyint(1) NOT null DEFAULT '0',
                            `no_bad_attempts` tinyint(1) NOT null DEFAULT '0',
                            `can_create_root_folder` tinyint(1) NOT null DEFAULT '0',
                            `read_only` tinyint(1) NOT null DEFAULT '0',
                            `timestamp` varchar(30) NOT null DEFAULT '0',
                            `user_language` varchar(30) NOT null DEFAULT 'english',
                            `name` varchar(100) NULL,
                            `lastname` varchar(100) NULL,
                            `session_end` varchar(30) NULL,
                            `isAdministratedByRole` tinyint(5) NOT null DEFAULT '0',
                            `psk` varchar(400) NULL,
                            `ga` varchar(50) NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `login` (`login`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "tags") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."tags` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `tag` varchar(30) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `id` (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "log_system") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."log_system` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `type` varchar(20) NOT NULL,
                            `date` varchar(30) NOT NULL,
                            `label` text NOT NULL,
                            `qui` varchar(30) NOT NULL,
                            `field_1` varchar(250) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "files") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."files` (
                            `id` int(11) NOT null AUTO_INCREMENT,
                            `id_item` int(11) NOT NULL,
                            `name` varchar(100) NOT NULL,
                            `size` int(10) NOT NULL,
                            `extension` varchar(10) NOT NULL,
                            `type` varchar(50) NOT NULL,
                            `file` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                           ) CHARSET=utf8;"
                        );
                    } else if ($task == "cache") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."cache` (
                            `id` int(12) NOT NULL,
                            `label` varchar(50) NOT NULL,
                            `description` text NOT NULL,
                            `tags` text NOT NULL,
                            `id_tree` int(12) NOT NULL,
                            `perso` tinyint(1) NOT NULL,
                            `restricted_to` varchar(200) NOT NULL,
                            `login` varchar(200) DEFAULT NULL,
                            `folder` varchar(300) NOT NULL,
                            `author` varchar(50) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "roles_title") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."roles_title` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `title` varchar(50) NOT NULL,
                            `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
                            `complexity` INT(5) NOT null DEFAULT '0',
                            `creator_id` int(11) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "roles_values") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."roles_values` (
                            `role_id` int(12) NOT NULL,
                            `folder_id` int(12) NOT NULL,
                            `type` varchar(1) NOT NULL DEFAULT 'R'
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "kb") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."kb` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `category_id` int(12) NOT NULL,
                            `label` varchar(200) NOT NULL,
                            `description` text NOT NULL,
                            `author_id` int(12) NOT NULL,
                            `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "kb_categories") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."kb_categories` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `category` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "kb_items") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."kb_items` (
                            `kb_id` tinyint(12) NOT NULL,
                            `item_id` tinyint(12) NOT NULL
                           ) CHARSET=utf8;"
                        );
                    } else if ($task == "restriction_to_roles") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."restriction_to_roles` (
                            `role_id` int(12) NOT NULL,
                            `item_id` int(12) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "keys") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."keys` (
                            `table` varchar(25) NOT NULL,
                            `id` int(20) NOT NULL,
                            `rand_key` varchar(25) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "languages") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."languages` (
                            `id` INT(10) NOT null AUTO_INCREMENT PRIMARY KEY ,
                            `name` VARCHAR(50) NOT null ,
                            `label` VARCHAR(50) NOT null ,
                            `code` VARCHAR(10) NOT null ,
                            `flag` VARCHAR(30) NOT NULL
                            ) CHARSET=utf8;"
                        );

                        // add lanaguages
                        $tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$var['tbl_prefix']."languages` WHERE name = 'french'"));
                        if ($tmp[0] == 0 || empty($tmp[0])) {
                            $mysql_result = mysqli_query($dbTmp,
                                "INSERT INTO `".$var['tbl_prefix']."languages` (`name`, `label`, `code`, `flag`) VALUES
                                ('french', 'French' , 'fr', 'fr.png'),
                                ('english', 'English' , 'us', 'us.png'),
                                ('spanish', 'Spanish' , 'es', 'es.png'),
                                ('german', 'German' , 'de', 'de.png'),
                                ('czech', 'Czech' , 'cz', 'cz.png'),
                                ('italian', 'Italian' , 'it', 'it.png'),
                                ('russian', 'Russian' , 'ru', 'ru.png'),
                                ('turkish', 'Turkish' , 'tr', 'tr.png'),
                                ('norwegian', 'Norwegian' , 'no', 'no.png'),
                                ('japanese', 'Japanese' , 'ja', 'ja.png'),
                                ('portuguese', 'Portuguese' , 'pr', 'pr.png'),
                                ('chinese', 'Chinese' , 'cn', 'cn.png'),
                                ('swedish', 'Swedish' , 'se', 'se.png'),
                                ('dutch', 'Dutch' , 'nl', 'nl.png'),
                                ('catalan', 'Catalan' , 'ct', 'ct.png');"
                            );
                        }
                    } else if ($task == "emails") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."emails` (
                            `timestamp` INT(30) NOT null ,
                            `subject` VARCHAR(255) NOT null ,
                            `body` TEXT NOT null ,
                            `receivers` VARCHAR(255) NOT null ,
                            `status` VARCHAR(30) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "automatic_del") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."automatic_del` (
                            `item_id` int(11) NOT NULL,
                            `del_enabled` tinyint(1) NOT NULL,
                            `del_type` tinyint(1) NOT NULL,
                            `del_value` varchar(35) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "items_edition") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."items_edition` (
                            `item_id` int(11) NOT NULL,
                            `user_id` int(11) NOT NULL,
                            `timestamp` varchar(50) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "categories") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."categories` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `parent_id` int(12) NOT NULL,
                            `title` varchar(255) NOT NULL,
                            `level` int(2) NOT NULL,
                            `description` text NOT NULL,
                            `type` varchar(50) NOT NULL,
                            `order` int(12) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "categories_items") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."categories_items` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `field_id` int(11) NOT NULL,
                            `item_id` int(11) NOT NULL,
                            `data` text NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "categories_folders") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."categories_folders` (
                            `id_category` int(12) NOT NULL,
                            `id_folder` int(12) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "api") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."api` (
                            `id` int(20) NOT NULL AUTO_INCREMENT,
                            `type` varchar(15) NOT NULL,
                            `label` varchar(255) NOT NULL,
                            `value` varchar(255) NOT NULL,
                            `timestamp` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "otv") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."otv` (
                            `id` int(10) NOT NULL AUTO_INCREMENT,
                            `timestamp` text NOT NULL,
                            `code` varchar(100) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            `originator` tinyint(12) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($task == "suggestion") {
                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."suggestion` (
                            `id` tinyint(12) NOT NULL AUTO_INCREMENT,
                            `label` varchar(255) NOT NULL,
                            `password` text NOT NULL,
                            `description` text NOT NULL,
                            `author_id` int(12) NOT NULL,
                            `folder_id` int(12) NOT NULL,
                            `comment` text NOT NULL,
                            `key` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );

                        $mysqli_result = mysqli_query($dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."export` (
                            `id` int(12) NOT NULL,
                            `label` varchar(255) NOT NULL,
                            `login` varchar(100) NOT NULL,
                            `description` text NOT NULL,
                            `pw` text NOT NULL,
                            `path` varchar(255) NOT NULL
                            ) CHARSET=utf8;"
                        );
                    }
                } else if ($activity == "entry") {
                    if ($task == "admin") {
                        require_once '../sources/main.functions.php';
                        // check that admin accounts doesn't exist
                        $tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$var['tbl_prefix']."users` WHERE login = 'admin'"));
                        if ($tmp[0] == 0 || empty($tmp[0])) {
                            $mysqli_result = mysqli_query($dbTmp,
                                "INSERT INTO `".$var['tbl_prefix']."users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`) VALUES (NULL, 'admin', '".bCrypt($var['admin_pwd'],'13' )."', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0')"
                            );
                        } else {
                            $mysqli_result = mysqli_query($dbTmp, "UPDATE `".$var['tbl_prefix']."users` SET `pw` = '".bCrypt($var['admin_pwd'],'13' )."' WHERE login = 'admin' AND id = '1'");
                        }
                    }
                }
                // answer back
                if ($mysqli_result) {
                    echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                } else {
                    echo '[{"error" : "true", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                }
            } else {
                echo '[{"error" : "'.addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_connect_error())).'", "result" : "Failed", "multiple" : ""}]';
            }

            mysqli_close($dbTmp);
            // Destroy session without writing to disk
			define('NODESTROY_SESSION','true');
			session_destroy();
            break;

        case "step_6":
            //decrypt
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';  // AES Counter Mode implementation
            $activity = Encryption\Crypt\aesctr::decrypt($_POST['activity'], "cpm", 128);
            $task = Encryption\Crypt\aesctr::decrypt($_POST['task'], "cpm", 128);

            $dbTmp = @mysqli_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw'], $_SESSION['db_bdd'], $_SESSION['db_port']);

            // read install variables
            $result = mysqli_query($dbTmp, "SELECT * FROM `_install`");
            while ($row = $result->fetch_array()) {
                $var[$row[0]] = $row[1];
            }

            // launch
            if (empty($var['sk_path'])) {
                $skFile = $var['abspath'].'/includes/sk.php';
                $securePath = $var['abspath'];
            } else {
                $skFile = $var['sk_path'].'/sk.php';
                $securePath = $var['sk_path'];
            }
            $events = "";

            if ($activity == "file") {
                if ($task == "settings.php") {
                    $filename = "../includes/settings.php";

                    if (file_exists($filename)) {
                        if (!copy($filename, $filename.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                            echo '[{"error" : "Setting.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                            break;
                        } else {
                            $events .= "The file $filename already exist. A copy has been created.<br />";
                            unlink($filename);
                        }
                    }
                    $fh = fopen($filename, 'w');

                    $result = fwrite(
                        $fh,
                        utf8_encode(
                            "<?php
global \$lang, \$txt, \$k, \$pathTeampas, \$urlTeampass, \$pwComplexity, \$mngPages;
global \$server, \$user, \$pass, \$database, \$pre, \$db, \$port;

### DATABASE connexion parameters ###
\$server = \"".$_SESSION['db_host']."\";
\$user = \"".$_SESSION['db_login']."\";
\$pass = \"".str_replace("$", "\\$", $_SESSION['db_pw'])."\";
\$database = \"".$_SESSION['db_bdd']."\";
\$pre = \"".$_SESSION['tbl_prefix']."\";
\$port = ".$_SESSION['db_port'].";
\$encoding = \"".$_SESSION['db_encoding']."\";

@date_default_timezone_set(\$_SESSION['settings']['timezone']);
@define('SECUREPATH', '".$securePath."');
require_once \"".str_replace('\\', '/', $skFile)."\";
?>"
                        )
                    );
                    fclose($fh);
                    if ($result === false) {
                        echo '[{"error" : "Setting.php file could not be created. Please check the path and the rights", "result":"", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                    } else {
                        echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                    }

                } else if ($task == "sk.php") {
//Create sk.php file
                    if (file_exists($skFile)) {
                        if (!copy($skFile, $skFile.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                            echo '[{"error" : "sk.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                            break;
                        } else {
                            unlink($skFile);
                        }
                    }
                    $fh = fopen($skFile, 'w');

                    $result = fwrite(
                        $fh,
                        utf8_encode(
                            "<?php
@define('SALT', '".$var['encrypt_key']."'); //Never Change it once it has been used !!!!!
@define('COST', '13'); // Don't change this.
?>")
                    );
                    fclose($fh);
                    if ($result === false) {
                        echo '[{"error" : "sk.php file could not be created. Please check the path and the rights.", "result":"", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                    } else {
                        echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                    }
                } else if ($task == "security") {
            		# Sort out the file permissions

                    // is server Windows or Linux?
                    if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
                        // Change directory permissions
                        $result = chmod_r($_SESSION['abspath'], 0550, 0440);
                        if ($result )
                            $result = chmod_r($_SESSION['abspath'].'/files', 0770, 0440);
                        if  ($result)
                            $result = chmod_r($_SESSION['abspath'].'/upload', 0770, 0440);
                    }
  
            	    if ($result === false) {
                        echo '[{"error" : "Cannot change directory permissions - please fix manually", "result":"", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                    } else {
                        echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                    }
            	}  
            }

            mysqli_close($dbTmp);
            // Destroy session without writing to disk
            define('NODESTROY_SESSION','true');
            session_destroy();
            break;
		case "step_7":
			
            //decrypt
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';  // AES Counter Mode implementation
            $activity = Encryption\Crypt\aesctr::decrypt($_POST['activity'], "cpm", 128);
            $task = Encryption\Crypt\aesctr::decrypt($_POST['task'], "cpm", 128);
            // launch
			$dbTmp = @mysqli_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw'], $_SESSION['db_bdd'], $_SESSION['db_port']);
                                        
            if ($activity == "file") {
            	if ($task == "deleteInstall") {
                    function delTree($dir) {
                        $files = array_diff(scandir($dir), array('.','..'));

                        foreach ($files as $file) {
                            (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
                        }
                        return rmdir($dir);
                    }

                    $result=true;
                    $errorMsg = "Cannot delete installation directory";
                    if (file_exists($_SESSION['abspath'].'/install')) {
                        // set the permissions on the install directory and delete
                        // is server Windows or Linux?
                        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
                            chmod_r($_SESSION['abspath'].'/install', 0755, 0440);
                        }
                        $result = delTree($_SESSION['abspath'].'/install');
                    }

                    // delete temporary install table
                    $result = mysqli_query($dbTmp, "DROP TABLE `_install`");
                    $errorMsg = "Cannot remove _install table";

                    if ($result === false) {
                        echo '[{"error" : "'.$errorMsg.'", "index" : "'.$_POST['index'].'", "result" : "Failed", "multiple" : ""}]';
                    } else {
                        echo '[{"error" : "", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                    }
         		}
            }
            // delete install table
            //            	
           	mysqli_close($dbTmp);
           	// Destroy session without writing to disk
           	define('NODESTROY_SESSION','true');
           	session_destroy();
           	break;            
    }
}
